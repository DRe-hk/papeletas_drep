<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/numeracion.php';
require_once __DIR__ . '/../app/pdf.php';
require_login();

$u = current_user();

// Datos del personal del usuario (para autocompletar)
$personal = null;
if (!empty($u['dni'])) {
    $st = DB::pdo()->prepare(
        'SELECT * FROM personal WHERE dni = ? LIMIT 1'
    );
    $st->execute([$u['dni']]);
    $personal = $st->fetch();
}

$errores = [];
$values = [
    'motivo_salida'         => '',
    'fundamentacion'        => '',
    'lugar'                 => '',
    'dia'                   => date('d'),
    'mes'                   => date('m'),
    'anio_dmy'              => date('Y'),
    'hora_salida'           => '',
    'hora_retorno'          => '',
    'retorna'               => 'NO',
    'observaciones'         => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($values as $k => $_) {
        $values[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $values['retorna'] = ($values['retorna'] === 'SI') ? 'SI' : 'NO';

    // Validaciones
    if (!array_key_exists($values['motivo_salida'], PapeletaPDF::motivoOpciones())) {
        $errores[] = 'Seleccione un motivo de salida válido.';
    }
    if ($values['fundamentacion'] === '') {
        $errores[] = 'La fundamentación es obligatoria.';
    }
    if ($values['lugar'] === '') {
        $errores[] = 'Indique el lugar de visita.';
    }
    if (!ctype_digit($values['dia']) || (int) $values['dia'] < 1 || (int) $values['dia'] > 31) {
        $errores[] = 'Día inválido.';
    }
    if (!ctype_digit($values['mes']) || (int) $values['mes'] < 1 || (int) $values['mes'] > 12) {
        $errores[] = 'Mes inválido.';
    }
    if (!ctype_digit($values['anio_dmy']) || strlen($values['anio_dmy']) !== 4) {
        $errores[] = 'Año inválido.';
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $values['hora_salida'])) {
        $errores[] = 'Hora de salida inválida (HH:MM).';
    }
    if ($values['retorna'] === 'SI' && !preg_match('/^\d{2}:\d{2}$/', $values['hora_retorno'])) {
        $errores[] = 'Si retorna, indique hora de retorno (HH:MM).';
    }

    if (!$errores) {
        // Generar número correlativo
        try {
            $num = siguiente_numero(DB::pdo());
            $fechaStr = sprintf('%02d/%02d/%04d', (int) $values['anio_dmy'], (int) $values['mes'], (int) $values['dia']);
            // (OJO: en la BD almacenamos día, mes, año dmy; en el PDF la fecha visible es la fecha de EMISIÓN actual)
            $fechaEmision = date('d/m/Y');

            $sql = 'INSERT INTO papeletas
                (numero, anio, correlativo, usuario_id, personal_id,
                 motivo_salida, fundamentacion, lugar,
                 dia, mes, anio_dmy, hora_salida, hora_retorno, retorna,
                 observaciones)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
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
                (int) $values['dia'],
                (int) $values['mes'],
                (int) $values['anio_dmy'],
                $values['hora_salida'] . ':00',
                $values['retorna'] === 'SI' ? ($values['hora_retorno'] . ':00') : null,
                $values['retorna'],
                $values['observaciones'] ?: null,
            ]);
            $newId = (int) DB::pdo()->lastInsertId();

            flash_set('ok', 'Papeleta ' . $num['numero'] . ' generada. Descargando PDF…');
            redirect('papeleta_descargar.php?id=' . $newId . '&autodl=1');
        } catch (Throwable $e) {
            $errores[] = 'Error al guardar: ' . $e->getMessage();
        }
    }
}

layout_header('Nueva papeleta');
render_flashes();
?>
<div class="card">
  <div class="card-header bg-white">
    <h5 class="mb-0"><i class="bi bi-file-earmark-plus"></i> Nueva Papeleta de Salida</h5>
  </div>
  <div class="card-body">

    <?php if (!$personal): ?>
      <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i>
        Su usuario <strong><?= e($u['username']) ?></strong> no está vinculado a un registro de personal.
        Los campos del solicitante aparecerán vacíos en el PDF. Pida al administrador que lo vincule desde
        <em>Administración → Personal</em>.
      </div>
    <?php endif; ?>

    <?php if ($errores): ?>
      <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($errores as $e) echo '<li>' . e($e) . '</li>'; ?></ul>
      </div>
    <?php endif; ?>

    <form method="post" class="row g-3">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

      <h6 class="text-primary mt-2">Datos del solicitante (autocompletados)</h6>
      <div class="col-md-6">
        <label class="form-label">DNI</label>
        <input class="form-control" value="<?= e($personal['dni'] ?? $u['dni'] ?? '') ?>" readonly>
      </div>
      <div class="col-md-6">
        <label class="form-label">Apellidos y Nombres</label>
        <input class="form-control" value="<?= e($personal['apellidos_nombres'] ?? '') ?>" readonly>
      </div>
      <div class="col-md-4">
        <label class="form-label">Régimen</label>
        <input class="form-control" value="<?= e($personal['regimen'] ?? '') ?>" readonly>
      </div>
      <div class="col-md-4">
        <label class="form-label">Dependencia</label>
        <input class="form-control" value="<?= e($personal['dependencia'] ?? '') ?>" readonly>
      </div>
      <div class="col-md-4">
        <label class="form-label">Cargo</label>
        <input class="form-control" value="<?= e($personal['cargo'] ?? '') ?>" readonly>
      </div>

      <h6 class="text-primary mt-3">Motivo de salida</h6>
      <div class="col-12">
        <?php foreach (PapeletaPDF::motivoOpciones() as $cod => $label): ?>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="motivo_salida" id="mot_<?= e($cod) ?>" value="<?= e($cod) ?>"
                 <?= $values['motivo_salida'] === $cod ? 'checked' : '' ?> required>
          <label class="form-check-label" for="mot_<?= e($cod) ?>"><?= e($label) ?></label>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="col-12">
        <label class="form-label">Fundamentación <span class="text-danger">*</span></label>
        <textarea class="form-control" name="fundamentacion" rows="3" required><?= e($values['fundamentacion']) ?></textarea>
      </div>

      <div class="col-12">
        <label class="form-label">Lugar <span class="text-danger">*</span></label>
        <input class="form-control" name="lugar" maxlength="200" required value="<?= e($values['lugar']) ?>">
      </div>

      <h6 class="text-primary mt-3">Horario</h6>
      <div class="col-md-2">
        <label class="form-label">Día</label>
        <input type="number" min="1" max="31" class="form-control" name="dia" required value="<?= e($values['dia']) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Mes</label>
        <input type="number" min="1" max="12" class="form-control" name="mes" required value="<?= e($values['mes']) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Año</label>
        <input type="number" min="2000" max="2100" class="form-control" name="anio_dmy" required value="<?= e($values['anio_dmy']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Hora Salida <span class="text-danger">*</span></label>
        <input type="time" class="form-control" name="hora_salida" required value="<?= e($values['hora_salida']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Hora Retorno</label>
        <input type="time" class="form-control" name="hora_retorno" id="hora_retorno" value="<?= e($values['hora_retorno']) ?>">
      </div>

      <div class="col-md-3">
        <label class="form-label d-block">¿Retorna?</label>
        <div class="btn-group" role="group">
          <input type="radio" class="btn-check" name="retorna" id="ret_si" value="SI" <?= $values['retorna'] === 'SI' ? 'checked' : '' ?>>
          <label class="btn btn-outline-success" for="ret_si">SI</label>
          <input type="radio" class="btn-check" name="retorna" id="ret_no" value="NO" <?= $values['retorna'] === 'NO' ? 'checked' : '' ?>>
          <label class="btn btn-outline-danger" for="ret_no">NO</label>
        </div>
      </div>
      <div class="col-md-9">
        <label class="form-label">Observaciones</label>
        <input class="form-control" name="observaciones" maxlength="500" value="<?= e($values['observaciones']) ?>">
      </div>

      <h6 class="text-primary mt-3">Datos para papeleta de retorno</h6>
      <div class="col-12">
        <p class="text-muted small mb-0">
          <i class="bi bi-info-circle"></i>
          La papeleta de retorno se firma al regresar a la institucion;
          no requiere datos adicionales al momento de emitir la papeleta de salida.
        </p>
      </div>

      <div class="col-12 d-flex justify-content-between mt-3">
        <a href="<?= url('dashboard.php') ?>" class="btn btn-secondary">
          <i class="bi bi-arrow-left"></i> Cancelar
        </a>
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-file-earmark-pdf"></i> Generar papeleta
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// UX: si eligen NO retorna, deshabilitar hora_retorno
document.querySelectorAll('input[name="retorna"]').forEach(r => {
  r.addEventListener('change', () => {
    const h = document.getElementById('hora_retorno');
    h.disabled = (document.getElementById('ret_no').checked);
    if (h.disabled) h.value = '';
  });
});
document.getElementById('hora_retorno').disabled = document.getElementById('ret_no').checked;
</script>
<?php
layout_footer();
