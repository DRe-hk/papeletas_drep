<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/usuarios_sync.php';
require_admin();

$u = current_user();
$ok = null; $err = null; $report = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
    csrf_check();
    $f = $_FILES['archivo'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $err = 'Error al subir el archivo (codigo ' . $f['error'] . ').';
    } else {
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt'], true)) {
            $err = 'Solo se aceptan archivos .csv o .txt.';
        } else {
            $report = importar_csv($f['tmp_name']);
        }
    }
}

/**
 * Detecta y normaliza el encoding del CSV a UTF-8.
 * Acepta UTF-8 (con o sin BOM) y Windows-1252 (default de Excel en Windows).
 * Devuelve siempre un string UTF-8 valido.
 */
function csv_normalizar_utf8(string $content): string
{
    // Quitar BOM UTF-8 si existe
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        $content = substr($content, 3);
    }

    // Si ya es UTF-8 valido, listo
    if (mb_check_encoding($content, 'UTF-8')) {
        return $content;
    }

    // No es UTF-8: probar Windows-1252 (default Excel) y caer a Latin-1
    foreach (['Windows-1252', 'ISO-8859-1'] as $src) {
        $converted = @iconv($src, 'UTF-8//TRANSLIT//IGNORE', $content);
        if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }
    }

    // Ultimo recurso: devolver como Latin-1 (mejor que romper la importacion)
    return (string) @iconv('Windows-1252', 'UTF-8//IGNORE', $content);
}

/**
 * Importa un CSV con columnas:
 *   dni,apellidos_nombres,regimen,dependencia,cargo
 * Primera fila = encabezados (se salta).
 * Codificacion: UTF-8 o Windows-1252 (auto-detectada).
 * - Si el DNI ya existe -> actualiza.
 * - Si no existe -> inserta.
 * - Por cada fila, sincroniza el usuario del sistema (crea si no existe).
 */
function importar_csv(string $ruta): array
{
    $empty = ['insertados' => 0, 'actualizados' => 0, 'usuarios_creados' => 0, 'usuarios_actualizados' => 0, 'errores' => []];
    if (!is_readable($ruta)) {
        $empty['errores'][] = 'No se pudo abrir el archivo.';
        return $empty;
    }

    // --- Auto-detectar encoding y normalizar a UTF-8 ---
    // Excel al guardar CSV en Windows usa Windows-1252 por defecto.
    // fgetcsv() lee bytes crudos, asi que si el archivo no es UTF-8 los
    // acentos (Á, É, Ñ, etc.) rompen la insercion en columnas utf8mb4.
    $content = file_get_contents($ruta);
    if ($content === false) {
        $empty['errores'][] = 'No se pudo leer el contenido.';
        return $empty;
    }
    $content = csv_normalizar_utf8($content);

    // Volcar el contenido normalizado a un memory stream para usar fgetcsv()
    $h = fopen('php://memory', 'r+');
    fwrite($h, $content);
    rewind($h);

    $cab = fgetcsv($h, 0, ',', '"', '');
    if (!$cab) { fclose($h); $empty['errores'][] = 'CSV vacio.'; return $empty; }

    $cab = array_map(fn($c) => strtolower(trim($c)), $cab);
    $req = ['dni', 'apellidos_nombres', 'regimen', 'dependencia', 'cargo'];
    foreach ($req as $r) {
        if (!in_array($r, $cab, true)) {
            fclose($h);
            return ['insertados' => 0, 'actualizados' => 0, 'usuarios_creados' => 0, 'usuarios_actualizados' => 0, 'errores' => ["Falta columna obligatoria: $r"]];
        }
    }
    $idx = array_flip($cab);

    $pdo = DB::pdo();
    $ins = 0; $upd = 0; $usrNew = 0; $usrUpd = 0; $errs = [];
    $stExist = $pdo->prepare('SELECT id, activo FROM personal WHERE dni = ?');
    $stIns   = $pdo->prepare('INSERT INTO personal (dni, apellidos_nombres, regimen, dependencia, cargo, activo) VALUES (?,?,?,?,?,1)');
    $stUpd   = $pdo->prepare('UPDATE personal SET apellidos_nombres=?, regimen=?, dependencia=?, cargo=? WHERE id=?');

    $linea = 1;
    while (($row = fgetcsv($h, 0, ',', '"', '')) !== false) {
        $linea++;
        if (count($row) < count($cab)) { $errs[] = "Linea $linea: incompleta"; continue; }
        $dni   = trim($row[$idx['dni']] ?? '');
        $nomb  = trim($row[$idx['apellidos_nombres']] ?? '');
        $reg   = trim($row[$idx['regimen']] ?? '');
        $dep   = trim($row[$idx['dependencia']] ?? '');
        $cgo   = trim($row[$idx['cargo']] ?? '');

        if (!preg_match('/^\d{8}$/', $dni)) { $errs[] = "Linea $linea: DNI invalido ($dni)"; continue; }
        if ($nomb === '' || $reg === '' || $dep === '' || $cgo === '') { $errs[] = "Linea $linea: faltan datos"; continue; }

        try {
            $stExist->execute([$dni]);
            $rowExist = $stExist->fetch();
            if ($rowExist) {
                $id = (int) $rowExist['id'];
                $stUpd->execute([$nomb, $reg, $dep, $cgo, $id]);
                $upd++;
                $activo = (int) $rowExist['activo'];
            } else {
                $stIns->execute([$dni, $nomb, $reg, $dep, $cgo]);
                $id = (int) $pdo->lastInsertId();
                $ins++;
                $activo = 1;
            }
            $r = sync_user_from_personal($pdo, $id, $dni, $activo, false);
            if ($r['created']) $usrNew++;
            elseif ($r['updated']) $usrUpd++;
        } catch (PDOException $e) {
            $errs[] = "Linea $linea: " . $e->getMessage();
        }
    }
    fclose($h);
    return [
        'insertados'           => $ins,
        'actualizados'         => $upd,
        'usuarios_creados'     => $usrNew,
        'usuarios_actualizados'=> $usrUpd,
        'errores'              => $errs,
    ];
}

layout_header('Importar personal');
render_flashes();
?>

<div class="page-header">
  <div>
    <h1><i class="bi bi-upload"></i> Importar personal (CSV)</h1>
    <div class="page-sub">Cargue un archivo CSV con los datos del personal. El sistema insertara nuevos y actualizara existentes (por DNI).</div>
  </div>
  <a href="<?= url('admin/personal.php') ?>" class="btn btn-secondary">
    <i class="bi bi-arrow-left"></i> Volver al listado
  </a>
</div>

<?php if ($err): ?>
  <div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <div><?= e($err) ?></div>
  </div>
<?php endif; ?>

<?php if ($report): ?>
  <div class="card mb-3">
    <div class="card-header"><i class="bi bi-bar-chart me-1"></i> Resultado de la importacion</div>
    <div class="card-body">
      <div class="d-flex gap-2 flex-wrap mb-3">
        <span class="badge text-bg-primary" style="font-size:.85rem;padding:.5em .8em">
          <i class="bi bi-plus-circle"></i> <?= (int)$report['insertados'] ?> insertados
        </span>
        <span class="badge text-bg-info" style="font-size:.85rem;padding:.5em .8em">
          <i class="bi bi-arrow-repeat"></i> <?= (int)$report['actualizados'] ?> actualizados
        </span>
        <span class="badge text-bg-success" style="font-size:.85rem;padding:.5em .8em">
          <i class="bi bi-person-plus"></i> <?= (int)$report['usuarios_creados'] ?> usuarios nuevos
        </span>
        <span class="badge text-bg-secondary" style="font-size:.85rem;padding:.5em .8em">
          <i class="bi bi-link"></i> <?= (int)$report['usuarios_actualizados'] ?> usuarios sincronizados
        </span>
        <span class="badge text-bg-<?= count($report['errores'])>0?'danger':'success' ?>" style="font-size:.85rem;padding:.5em .8em">
          <i class="bi bi-exclamation-<?= count($report['errores'])>0?'triangle':'check' ?>"></i>
          <?= count($report['errores']) ?> errores
        </span>
      </div>
      <p class="small text-muted mb-2">Los usuarios nuevos tienen como usuario y contrasena su DNI. Deberan cambiar la contrasena en su primer ingreso.</p>
      <?php if ($report['errores']): ?>
        <div class="alert alert-danger mb-0">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <div>
            <strong>Errores encontrados:</strong>
            <ul class="mb-0 mt-1 small"><?php foreach ($report['errores'] as $e) echo '<li>' . e($e) . '</li>'; ?></ul>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-7">
    <form method="post" enctype="multipart/form-data" class="form-section" id="uploadForm">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <h6 class="form-section-title"><i class="bi bi-cloud-upload"></i> Archivo</h6>
      <div class="upload-zone" id="dropzone">
        <i class="bi bi-cloud-arrow-up" style="font-size:2.2rem;color:var(--brand-500)"></i>
        <div class="mt-2 mb-1"><strong>Arrastre el CSV aqui</strong> o haga click para seleccionar</div>
        <small class="text-muted">UTF-8, primera fila con encabezados, separador coma</small>
        <input type="file" name="archivo" id="archivo" accept=".csv,.txt" class="d-none" required>
        <div id="filenameDisplay" class="mt-3" style="display:none">
          <span class="badge text-bg-success" style="font-size:.85rem;padding:.5em .8em">
            <i class="bi bi-file-earmark-check"></i> <span id="filenameText"></span>
          </span>
        </div>
      </div>
      <div class="d-flex justify-content-end mt-3">
        <button class="btn btn-cta">
          <i class="bi bi-upload"></i> Importar archivo
        </button>
      </div>
    </form>
  </div>
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header"><i class="bi bi-info-circle me-1"></i> Formato esperado</div>
      <div class="card-body">
        <p class="mb-2">El CSV debe tener estas 5 columnas (en este orden):</p>
        <pre class="bg-light p-2 small mb-3 rounded"><code>dni,apellidos_nombres,regimen,dependencia,cargo</code></pre>
        <p class="mb-1"><strong>Ejemplo:</strong></p>
        <pre class="bg-light p-2 small mb-3 rounded" style="white-space:pre-wrap">12345678,PEREZ JUAN,D.L. 276,OFICINA DE ADMINISTRACION,TECNICO ADMINISTRATIVO
87654321,GOMEZ MARIA,D.L. 728,DIRECCION,ESPECIALISTA
11223344,RAMOS LUIS,CAS,OFICINA DE PERSONAL,ASISTENTE</pre>
        <ul class="small text-muted mb-3">
          <li><code>regimen</code> acepta codigo corto (<code>276</code>, <code>728</code>, <code>CAS</code>) o descripcion completa (<code>D.L. 276</code>, etc.).</li>
          <li>Si el DNI ya existe, se actualiza; si no, se inserta.</li>
        </ul>
        <a class="btn btn-outline-primary w-100" href="<?= url('admin/importar_personal_plantilla.php') ?>">
          <i class="bi bi-download"></i> Descargar plantilla CSV
        </a>
      </div>
    </div>
  </div>
</div>

<style>
.upload-zone {
  border: 2px dashed var(--border-strong);
  border-radius: var(--radius);
  padding: 2rem 1rem;
  text-align: center;
  background: var(--surface-sunken);
  cursor: pointer;
  transition: all .15s ease;
}
.upload-zone:hover, .upload-zone.dragover {
  border-color: var(--brand-500);
  background: var(--brand-50);
}
</style>

<script>
const dz = document.getElementById('dropzone');
const inp = document.getElementById('archivo');
const fname = document.getElementById('filenameDisplay');
const fnameText = document.getElementById('filenameText');
dz.addEventListener('click', () => inp.click());
['dragenter','dragover'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.add('dragover'); }));
['dragleave','drop'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.remove('dragover'); }));
dz.addEventListener('drop', e => {
  if (e.dataTransfer.files.length) {
    inp.files = e.dataTransfer.files;
    showFile(inp.files[0].name);
  }
});
inp.addEventListener('change', () => {
  if (inp.files.length) showFile(inp.files[0].name);
});
function showFile(n) {
  fnameText.textContent = n;
  fname.style.display = '';
}
</script>
<?php layout_footer();
