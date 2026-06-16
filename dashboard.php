<?php
declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/pdf.php';
require_login();

$u = current_user();

$st = DB::pdo()->prepare(
    'SELECT id, numero, fecha_emision, motivo_salida, retorna, lugar
     FROM papeletas
     WHERE usuario_id = ?
     ORDER BY id DESC
     LIMIT 15'
);
$st->execute([$u['id']]);
$mias = $st->fetchAll();

$st = DB::pdo()->prepare('SELECT COUNT(*) AS total FROM papeletas WHERE anio = ?');
$st->execute([(int) date('Y')]);
$totalAnio = (int) $st->fetch()['total'];

$st = DB::pdo()->prepare('SELECT COUNT(*) AS total FROM papeletas WHERE usuario_id = ?');
$st->execute([$u['id']]);
$totalMias = (int) $st->fetch()['total'];

$firstName = trim(explode(' ', $u['apellidos_nombres'] ?? '')[0] ?? '');
$welcomeMsg = $firstName !== '' ? "Hola, $firstName" : "Bienvenido";

layout_header('Inicio');
render_flashes();
?>

<div class="row g-3 mb-3">
  <div class="col-md-5">
    <div class="stat-card">
      <span class="stat-icon"><i class="bi bi-person-circle"></i></span>
      <span class="stat-label"><?= e($welcomeMsg) ?></span>
      <span class="stat-value" style="font-size:1.35rem"><?= e($u['apellidos_nombres'] ?? $u['username']) ?></span>
      <span class="stat-sub"><?= e($u['cargo'] ?? '') ?><?= !empty($u['dependencia']) ? ' · ' . e($u['dependencia']) : '' ?></span>
    </div>
  </div>
  <div class="col-md-3 col-6">
    <div class="stat-card warm">
      <span class="stat-icon"><i class="bi bi-file-earmark-text"></i></span>
      <span class="stat-label">Mis papeletas</span>
      <span class="stat-value"><?= $totalMias ?></span>
      <span class="stat-sub">en total</span>
    </div>
  </div>
  <div class="col-md-4 col-6">
    <div class="stat-card ok">
      <span class="stat-icon"><i class="bi bi-calendar-check"></i></span>
      <span class="stat-label">Emitidas en <?= (int) date('Y') ?></span>
      <span class="stat-value"><?= $totalAnio ?></span>
      <span class="stat-sub">en toda la institucion</span>
    </div>
  </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-0" style="text-transform:none;letter-spacing:0;font-size:1.05rem;color:var(--text-1);font-weight:600">Mis ultimas papeletas</h5>
    <small class="text-muted">Las 15 mas recientes que has emitido</small>
  </div>
  <a href="<?= url('papeleta_nueva.php') ?>" class="btn btn-cta">
    <i class="bi bi-plus-circle-fill"></i> Generar nueva papeleta
  </a>
</div>

<div class="table-wrap">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th>N&deg;</th>
          <th>Fecha</th>
          <th>Motivo</th>
          <th>Lugar</th>
          <th class="actions">Accion</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$mias): ?>
          <tr>
            <td colspan="5">
              <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-file-earmark-plus"></i></div>
                <h5>Aun no has emitido papeletas</h5>
                <p>Tu primera papeleta se genera en menos de un minuto.</p>
                <a href="<?= url('papeleta_nueva.php') ?>" class="btn btn-cta">
                  <i class="bi bi-plus-circle-fill"></i> Generar mi primera papeleta
                </a>
              </div>
            </td>
          </tr>
        <?php else: foreach ($mias as $r): ?>
          <tr>
            <td><span class="num"><?= e($r['numero']) ?></span></td>
            <td><?= e($r['fecha_emision']) ?></td>
            <td><span class="badge text-bg-info"><?= e(PapeletaPDF::motivoCodigoALabel($r['motivo_salida'])) ?></span></td>
            <td><small class="text-muted"><?= e($r['lugar']) ?></small></td>
            <td class="actions">
              <a class="btn btn-sm btn-outline-primary" href="<?= url('papeleta_descargar.php') ?>?id=<?= (int)$r['id'] ?>" title="Descargar PDF">
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
