<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/usuarios_sync.php';
require_admin();

$u = current_user();
$ok = null; $err = null; $report = null;

// Carga de archivo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
    csrf_check();
    $f = $_FILES['archivo'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $err = 'Error al subir el archivo (código ' . $f['error'] . ').';
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
 * Importa un CSV con columnas:
 *   dni,apellidos_nombres,regimen_laboral,regimen,dependencia,cargo
 * Primera fila = encabezados (se salta).
 * Codificación: UTF-8.
 * - Si el DNI ya existe -> actualiza.
 * - Si no existe -> inserta.
 * - Por cada fila, sincroniza el usuario del sistema (crea si no existe).
 */
function importar_csv(string $ruta): array
{
    $h = fopen($ruta, 'r');
    if (!$h) return ['insertados' => 0, 'actualizados' => 0, 'usuarios_creados' => 0, 'usuarios_actualizados' => 0, 'errores' => ['No se pudo abrir el archivo.']];

    $cab = fgetcsv($h, 0, ',', '"', '');
    if (!$cab) { fclose($h); return ['insertados' => 0, 'actualizados' => 0, 'usuarios_creados' => 0, 'usuarios_actualizados' => 0, 'errores' => ['CSV vacío.']]; }

    // Normalizar encabezados
    $cab = array_map(fn($c) => strtolower(trim($c)), $cab);
    $req = ['dni', 'apellidos_nombres', 'regimen_laboral', 'regimen', 'dependencia', 'cargo'];
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
    $stIns   = $pdo->prepare('INSERT INTO personal (dni, apellidos_nombres, regimen_laboral, regimen, dependencia, cargo, activo) VALUES (?,?,?,?,?,?,1)');
    $stUpd   = $pdo->prepare('UPDATE personal SET apellidos_nombres=?, regimen_laboral=?, regimen=?, dependencia=?, cargo=? WHERE id=?');

    $linea = 1;
    while (($row = fgetcsv($h, 0, ',', '"', '')) !== false) {
        $linea++;
        if (count($row) < count($cab)) { $errs[] = "Línea $linea: incompleta"; continue; }
        $dni   = trim($row[$idx['dni']] ?? '');
        $nomb  = trim($row[$idx['apellidos_nombres']] ?? '');
        $rl    = trim($row[$idx['regimen_laboral']] ?? '');
        $reg   = trim($row[$idx['regimen']] ?? '');
        $dep   = trim($row[$idx['dependencia']] ?? '');
        $cgo   = trim($row[$idx['cargo']] ?? '');

        if (!preg_match('/^\d{8}$/', $dni)) { $errs[] = "Línea $linea: DNI inválido ($dni)"; continue; }
        if ($nomb === '' || $dep === '' || $cgo === '') { $errs[] = "Línea $linea: faltan datos"; continue; }

        try {
            $stExist->execute([$dni]);
            $rowExist = $stExist->fetch();
            if ($rowExist) {
                $id = (int) $rowExist['id'];
                $stUpd->execute([$nomb, $rl, $reg, $dep, $cgo, $id]);
                $upd++;
                $activo = (int) $rowExist['activo'];
            } else {
                $stIns->execute([$dni, $nomb, $rl, $reg, $dep, $cgo]);
                $id = (int) $pdo->lastInsertId();
                $ins++;
                $activo = 1;
            }
            // Sincronizar usuario (crear o actualizar) sin resetear password
            $r = sync_user_from_personal($pdo, $id, $dni, $activo, false);
            if ($r['created']) $usrNew++;
            elseif ($r['updated']) $usrUpd++;
        } catch (PDOException $e) {
            $errs[] = "Línea $linea: " . $e->getMessage();
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
<div class="row g-3">
  <div class="col-md-7">
    <div class="card">
      <div class="card-header bg-white">
        <strong><i class="bi bi-upload"></i> Cargar archivo CSV</strong>
      </div>
      <div class="card-body">
        <?php if ($ok): ?><div class="alert alert-success py-2"><?= e($ok) ?></div><?php endif; ?>
        <?php if ($err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endif; ?>

        <?php if ($report): ?>
          <div class="alert alert-info">
            <strong>Resultado:</strong><br>
            <span class="badge text-bg-primary">Personales insertados: <?= (int)$report['insertados'] ?></span>
            <span class="badge text-bg-info">Personales actualizados: <?= (int)$report['actualizados'] ?></span>
            <span class="badge text-bg-success">Usuarios creados: <?= (int)$report['usuarios_creados'] ?></span>
            <span class="badge text-bg-secondary">Usuarios sincronizados: <?= (int)$report['usuarios_actualizados'] ?></span>
            <span class="badge text-bg-<?= count($report['errores'])>0?'danger':'secondary' ?>">Errores: <?= count($report['errores']) ?></span>
            <div class="small mt-2">
              Los usuarios nuevos tienen como usuario y contraseña su DNI.
              Deberán cambiar la contraseña en su primer ingreso.
            </div>
            <?php if ($report['errores']): ?>
              <ul class="mb-0 mt-2 small">
                <?php foreach ($report['errores'] as $e) echo '<li>' . e($e) . '</li>'; ?>
              </ul>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <div class="mb-3">
            <label class="form-label">Archivo CSV</label>
            <input type="file" name="archivo" accept=".csv,.txt" class="form-control" required>
            <div class="form-text">UTF-8, primera fila con encabezados, separador coma.</div>
          </div>
          <button class="btn btn-primary"><i class="bi bi-upload"></i> Importar</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-md-5">
    <div class="card">
      <div class="card-header bg-white"><strong><i class="bi bi-info-circle"></i> Formato esperado</strong></div>
      <div class="card-body">
        <p>El CSV debe tener estas 6 columnas (en este orden):</p>
        <pre class="bg-light p-2 small mb-2">dni,apellidos_nombres,regimen_laboral,regimen,dependencia,cargo</pre>
        <p><strong>Ejemplo:</strong></p>
        <pre class="bg-light p-2 small">12345678,PEREZ JUAN,276,D.L. 276,OFICINA DE ADMINISTRACION,TECNICO ADMINISTRATIVO
87654321,GOMEZ MARIA,728,D.L. 728,DIRECCION,ESPECIALISTA</pre>
        <p class="small text-muted">
          • <code>regimen_laboral</code> = código (276, 728, CAS, etc.)<br>
          • <code>regimen</code> = descripción completa (D.L. 276, D.L. 728, etc.)<br>
          • Si el DNI ya existe, se actualiza; si no, se inserta.
        </p>
        <a class="btn btn-sm btn-outline-secondary" href="<?= url('admin/importar_personal_plantilla.php') ?>">
          <i class="bi bi-download"></i> Descargar plantilla CSV
        </a>
      </div>
    </div>
  </div>
</div>
<?php layout_footer();
