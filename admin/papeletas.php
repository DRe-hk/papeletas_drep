<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/pdf.php';
require_admin();

$ok = null; $err = null;
$u  = current_user();

$q           = trim($_GET['q']            ?? '');
$filtroAnio  = (int) ($_GET['anio']       ?? date('Y'));
$filtroUser  = (int) ($_GET['usuario']    ?? 0);

$params = [];
$sql = 'SELECT p.id, p.numero, p.fecha_emision, p.motivo_salida,
               p.retorna, p.lugar,
               per.apellidos_nombres, per.dni,
               u.username
        FROM papeletas p
        JOIN personal per ON per.id = p.personal_id
        JOIN usuarios u   ON u.id  = p.usuario_id
        WHERE p.anio = ?';
$params[] = $filtroAnio;
if ($q !== '') {
    $sql .= ' AND (p.numero LIKE ? OR per.apellidos_nombres LIKE ? OR per.dni LIKE ?)';
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($filtroUser) {
    $sql .= ' AND u.id = ?';
    $params[] = $filtroUser;
}
$sql .= ' ORDER BY p.id DESC LIMIT 500';
$st = DB::pdo()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$usuarios = DB::pdo()->query('SELECT id, username FROM usuarios ORDER BY username')->fetchAll();

layout_header('Todas las papeletas');
render_flashes();
?>

<div class="page-header">
  <div>
    <h1><i class="bi bi-files"></i> Todas las papeletas</h1>
    <div class="page-sub">Auditoria de papeletas emitidas en la institucion. <span class="badge text-bg-secondary"><?= count($rows) ?> resultados</span></div>
  </div>
</div>

<form method="get" class="toolbar">
  <div class="input-icon flex-grow-1" style="min-width:240px">
    <i class="bi bi-search"></i>
    <input class="form-control" name="q" placeholder="Buscar por N&uacute;mero, nombre o DNI" value="<?= e($q) ?>">
  </div>
  <div style="min-width:120px">
    <label class="form-label small mb-1">A&ntilde;o</label>
    <input type="number" class="form-control" name="anio" value="<?= $filtroAnio ?>">
  </div>
  <div style="min-width:180px">
    <label class="form-label small mb-1">Usuario</label>
    <select class="form-select" name="usuario">
      <option value="0">&mdash; Todos &mdash;</option>
      <?php foreach ($usuarios as $uu): ?>
        <option value="<?= (int)$uu['id'] ?>" <?= $filtroUser==$uu['id']?'selected':'' ?>><?= e($uu['username']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button class="btn btn-primary"><i class="bi bi-funnel"></i> Filtrar</button>
  <?php if ($q || $filtroUser): ?>
    <a href="?" class="btn btn-secondary"><i class="bi bi-x-lg"></i></a>
  <?php endif; ?>
</form>

<div class="table-wrap">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th>N&deg;</th>
          <th>Fecha</th>
          <th>Solicitante</th>
          <th>DNI</th>
          <th>Motivo</th>
          <th>Lugar</th>
          <th>Usuario</th>
          <th class="actions">PDF</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr>
            <td colspan="8">
              <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-funnel"></i></div>
                <h5>Sin resultados para el filtro</h5>
                <p>Pruebe cambiar el a&ntilde;o, el usuario o limpiar la busqueda.</p>
                <a href="?" class="btn btn-outline-primary"><i class="bi bi-x-lg"></i> Limpiar filtros</a>
              </div>
            </td>
          </tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td class="text-mono"><strong><?= e($r['numero']) ?></strong></td>
            <td><small class="text-mono text-muted"><?= e($r['fecha_emision']) ?></small></td>
            <td><?= e($r['apellidos_nombres']) ?></td>
            <td class="text-mono"><?= e($r['dni']) ?></td>
            <td><span class="badge text-bg-info"><?= e(PapeletaPDF::motivoCodigoALabel($r['motivo_salida'])) ?></span></td>
            <td><small class="text-muted"><?= e($r['lugar']) ?></small></td>
            <td><small class="text-muted"><?= e($r['username']) ?></small></td>
            <td class="actions">
              <a class="btn btn-icon btn-outline-primary" href="<?= url('papeleta_descargar.php') ?>?id=<?= (int)$r['id'] ?>" title="Descargar PDF">
                <i class="bi bi-download"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php layout_footer();
