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
$sql = 'SELECT p.id, p.numero, p.fecha_emision, p.motivo_salida, p.hora_salida, p.hora_retorno,
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
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0"><i class="bi bi-files"></i> Papeletas emitidas (<?= count($rows) ?>)</h5>
</div>

<form method="get" class="card p-3 mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-5">
      <label class="form-label small">Buscar</label>
      <input class="form-control" name="q" placeholder="N°, nombre o DNI" value="<?= e($q) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label small">Año</label>
      <input type="number" class="form-control" name="anio" value="<?= $filtroAnio ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label small">Usuario</label>
      <select class="form-select" name="usuario">
        <option value="0">— Todos —</option>
        <?php foreach ($usuarios as $uu): ?>
          <option value="<?= (int)$uu['id'] ?>" <?= $filtroUser==$uu['id']?'selected':'' ?>><?= e($uu['username']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-1">
      <button class="btn btn-primary w-100"><i class="bi bi-search"></i></button>
    </div>
  </div>
</form>

<div class="card">
  <div class="table-responsive">
  <table class="table table-sm table-hover mb-0 align-middle">
    <thead class="table-light">
      <tr>
        <th>N°</th><th>Fecha</th><th>Solicitante</th><th>DNI</th><th>Motivo</th>
        <th>Hora S/R</th><th>Lugar</th><th>Usuario</th><th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="9" class="text-center text-muted py-4">Sin resultados para el filtro.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><strong><?= e($r['numero']) ?></strong></td>
          <td><small><?= e($r['fecha_emision']) ?></small></td>
          <td><?= e($r['apellidos_nombres']) ?></td>
          <td><?= e($r['dni']) ?></td>
          <td><span class="badge text-bg-info"><?= e(PapeletaPDF::motivoCodigoALabel($r['motivo_salida'])) ?></span></td>
          <td><small><?= e(substr($r['hora_salida'] ?? '', 0, 5)) ?> / <?= e(substr($r['hora_retorno'] ?? '', 0, 5)) ?></small></td>
          <td><small><?= e($r['lugar']) ?></small></td>
          <td><small><?= e($r['username']) ?></small></td>
          <td class="text-end">
            <a class="btn btn-sm btn-primary" href="<?= url('papeleta_descargar.php') ?>?id=<?= (int)$r['id'] ?>">
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
