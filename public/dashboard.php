<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/pdf.php';
require_login();

$u = current_user();

// Papeletas del usuario actual
$st = DB::pdo()->prepare(
    'SELECT id, numero, fecha_emision, motivo_salida, hora_salida, hora_retorno, retorna, lugar
     FROM papeletas
     WHERE usuario_id = ?
     ORDER BY id DESC
     LIMIT 15'
);
$st->execute([$u['id']]);
$mias = $st->fetchAll();

// Total del año actual
$st = DB::pdo()->prepare(
    'SELECT COUNT(*) AS total FROM papeletas WHERE anio = ?'
);
$st->execute([(int) date('Y')]);
$totalAnio = (int) $st->fetch()['total'];

// Total del usuario
$st = DB::pdo()->prepare(
    'SELECT COUNT(*) AS total FROM papeletas WHERE usuario_id = ?'
);
$st->execute([$u['id']]);
$totalMias = (int) $st->fetch()['total'];

layout_header('Inicio');
render_flashes();
?>
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card p-3">
      <small class="text-muted">Bienvenido</small>
      <h5 class="mb-0"><?= e($u['apellidos_nombres'] ?? $u['username']) ?></h5>
      <small class="text-muted"><?= e($u['cargo'] ?? '') ?> · <?= e($u['dependencia'] ?? '') ?></small>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card p-3">
      <small class="text-muted">Mis papeletas</small>
      <h2 class="mb-0"><?= $totalMias ?></h2>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card p-3">
      <small class="text-muted">Total emitidas en <?= (int) date('Y') ?></small>
      <h2 class="mb-0"><?= $totalAnio ?></h2>
    </div>
  </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-2">
  <h5 class="mb-0">Mis últimas papeletas</h5>
  <a href="<?= url('papeleta_nueva.php') ?>" class="btn btn-success">
    <i class="bi bi-plus-circle"></i> Nueva papeleta
  </a>
</div>

<div class="card">
  <div class="table-responsive">
  <table class="table table-hover mb-0">
    <thead class="table-light">
      <tr>
        <th>N°</th>
        <th>Fecha emisión</th>
        <th>Motivo</th>
        <th>Hora Salida</th>
        <th>Hora Retorno</th>
        <th>Lugar</th>
        <th class="text-end">Acción</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$mias): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">Aún no has emitido papeletas.</td></tr>
      <?php else: foreach ($mias as $r): ?>
        <tr>
          <td><strong><?= e($r['numero']) ?></strong></td>
          <td><?= e($r['fecha_emision']) ?></td>
          <td><span class="badge text-bg-info"><?= e(PapeletaPDF::motivoCodigoALabel($r['motivo_salida'])) ?></span></td>
          <td><?= e(substr($r['hora_salida'] ?? '', 0, 5)) ?></td>
          <td><?= e(substr($r['hora_retorno'] ?? '', 0, 5)) ?></td>
          <td><?= e($r['lugar']) ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-primary" href="<?= url('papeleta_descargar.php') ?>?id=<?= (int)$r['id'] ?>">
              <i class="bi bi-download"></i> PDF
            </a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
  </div>
</div>
<?php
layout_footer();
