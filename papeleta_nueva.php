<?php
declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/numeracion.php';
require_once __DIR__ . '/app/pdf.php';
require_login();

$u = current_user();

$personal = null;
if (!empty($u['dni'])) {
    $st = DB::pdo()->prepare('SELECT * FROM personal WHERE dni = ? LIMIT 1');
    $st->execute([$u['dni']]);
    $personal = $st->fetch();
}

$fechaHoy = DateTime::createFromFormat('Y-m-d', date('Y-m-d'));
$errores = [];
$values = [
    'motivo_salida'         => '',
    'fundamentacion'        => '',
    'lugar'                 => '',
    'retorna'               => 'NO',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($values as $k => $_) {
        $values[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $values['retorna'] = ($values['retorna'] === 'SI') ? 'SI' : 'NO';

    if (!array_key_exists($values['motivo_salida'], PapeletaPDF::motivoOpciones())) {
        $errores[] = 'Seleccione un motivo de salida valido.';
    }
    if ($values['fundamentacion'] === '') {
        $errores[] = 'La fundamentacion es obligatoria.';
    }
    if ($values['lugar'] === '') {
        $errores[] = 'Indique el lugar de visita.';
    }

    if (!$errores) {
        try {
            $num = siguiente_numero(DB::pdo());

            $sql = 'INSERT INTO papeletas
                (numero, anio, correlativo, usuario_id, personal_id,
                 motivo_salida, fundamentacion, lugar,
                 dia, mes, anio_dmy, retorna)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)';
            $st = DB::pdo()->prepare($sql);
            $st->execute([
                $num['numero'],
                $num['anio'],
                $num['correlativo'],
                $u['id'],
                $personal['id'] ?? null,
                $values['motivo_salida'],
                $values['fundamentacion'],
                $values['lugar'],
                (int) $fechaHoy->format('d'),
                (int) $fechaHoy->format('m'),
                (int) $fechaHoy->format('Y'),
                $values['retorna'],
            ]);
            $newId = (int) DB::pdo()->lastInsertId();

            flash_set('ok', 'Papeleta ' . $num['numero'] . ' generada. Descargando PDF...');
            redirect('papeleta_descargar.php?id=' . $newId . '&autodl=1');
        } catch (Throwable $e) {
            $errores[] = 'Error al guardar: ' . $e->getMessage();
        }
    }
}

layout_header('Nueva papeleta');
render_flashes();
?>

<div class="page-header">
  <div>
    <h1><i class="bi bi-file-earmark-plus"></i> Nueva papeleta de salida</h1>
    <div class="page-sub">Complete los datos para generar el documento oficial. La papeleta se descarga automaticamente al guardar.</div>
  </div>
</div>

<?php if (!$personal): ?>
  <div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <div>Su usuario <strong><?= e($u['username']) ?></strong> no esta vinculado a un registro de personal. Los campos del solicitante apareceran vacios en el PDF. Pida al administrador que lo vincule desde <em>Administracion &rarr; Personal</em>.</div>
  </div>
<?php endif; ?>

<?php if ($errores): ?>
  <div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <div>
      <strong>No se pudo generar la papeleta:</strong>
      <ul class="mb-0 mt-1"><?php foreach ($errores as $e) echo '<li>' . e($e) . '</li>'; ?></ul>
    </div>
  </div>
<?php endif; ?>

<form method="post" id="formPapeleta">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

  <div class="form-section">
    <h6 class="form-section-title"><i class="bi bi-person-vcard"></i> Datos del solicitante</h6>
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label">DNI</label>
        <div class="readonly-block"><?= e($personal['dni'] ?? $u['dni'] ?? '—') ?></div>
      </div>
      <div class="col-md-8">
        <label class="form-label">Apellidos y Nombres</label>
        <div class="readonly-block"><?= e($personal['apellidos_nombres'] ?? '—') ?></div>
      </div>
      <div class="col-md-4">
        <label class="form-label">Regimen</label>
        <div class="readonly-block"><?= e($personal['regimen'] ?? '—') ?></div>
      </div>
      <div class="col-md-4">
        <label class="form-label">Dependencia</label>
        <div class="readonly-block"><?= e($personal['dependencia'] ?? '—') ?></div>
      </div>
      <div class="col-md-4">
        <label class="form-label">Cargo</label>
        <div class="readonly-block"><?= e($personal['cargo'] ?? '—') ?></div>
      </div>
    </div>
  </div>

  <div class="form-section">
    <h6 class="form-section-title"><i class="bi bi-clipboard2-pulse"></i> Motivo de salida</h6>
    <div class="pill-group">
      <?php foreach (PapeletaPDF::motivoOpciones() as $cod => $label): ?>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="motivo_salida" id="mot_<?= e($cod) ?>" value="<?= e($cod) ?>" <?= $values['motivo_salida'] === $cod ? 'checked' : '' ?> required>
          <label class="form-check-label" for="mot_<?= e($cod) ?>"><?= e($label) ?></label>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="row g-3 mt-1">
      <div class="col-md-12">
        <label class="form-label">Fundamentacion <span class="req">*</span></label>
        <textarea class="form-control" name="fundamentacion" rows="2" required placeholder="Detalle el motivo de la salida"><?= e($values['fundamentacion']) ?></textarea>
      </div>
      <div class="col-md-12">
        <label class="form-label">Lugar de visita <span class="req">*</span></label>
        <div class="input-icon">
          <i class="bi bi-geo-alt"></i>
          <input class="form-control" name="lugar" maxlength="200" required value="<?= e($values['lugar']) ?>" placeholder="Ej: SUNAT Puno, ESSALUD, sede central, etc.">
        </div>
      </div>
    </div>
  </div>

  <div class="form-section">
    <h6 class="form-section-title"><i class="bi bi-clock-history"></i> Fecha y retorno</h6>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Fecha de generacion</label>
        <div class="readonly-block" style="font-family:var(--font-mono)">
          <i class="bi bi-calendar3 me-2" style="color:var(--brand-500)"></i>
          <?= e($fechaHoy->format('d/m/Y')) ?>
        </div>
        <div class="form-text">Fecha del servidor. No editable. Se registra automaticamente.</div>
      </div>
      <div class="col-md-6">
        <label class="form-label d-block">&iquest;Retorna a la sede?</label>
        <div class="toggle-pill mt-1">
          <input type="radio" name="retorna" id="ret_si" value="SI" <?= $values['retorna'] === 'SI' ? 'checked' : '' ?>>
          <label for="ret_si">SI</label>
          <input type="radio" name="retorna" id="ret_no" value="NO" <?= $values['retorna'] === 'NO' ? 'checked' : '' ?>>
          <label for="ret_no">NO</label>
        </div>
        <div class="form-text">Indique si el trabajador regresa a la sede despues de su diligencia.</div>
      </div>
    </div>
  </div>

  <div class="form-section" style="background: oklch(96% 0.04 245 / .4); border-style: dashed;">
    <div class="d-flex align-items-start gap-3">
      <i class="bi bi-info-circle-fill" style="color: var(--brand-500); font-size: 1.3rem; line-height: 1.3;"></i>
      <div>
        <strong>Sobre la papeleta de retorno:</strong>
        <p class="mb-0 text-muted small">La papeleta de retorno se firma al regresar a la institucion. La hora de salida y de retorno se llenan a mano al momento de firmar.</p>
      </div>
    </div>
  </div>

  <div class="sticky-actions">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <a href="<?= url('dashboard.php') ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Cancelar
      </a>
      <button type="submit" class="btn btn-cta">
        <i class="bi bi-file-earmark-pdf-fill"></i> Generar papeleta
      </button>
    </div>
  </div>
</form>
<?php
layout_footer();
