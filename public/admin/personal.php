<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/usuarios_sync.php';
require_admin();

$u  = current_user();
$ok = null; $err = null;

// ---------- Acciones masivas (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'sync_all') {
        // Backfill masivo: crear usuario para cada personal activo sin uno
        $pdo = DB::pdo();
        $rows = $pdo->query("
            SELECT p.id, p.dni, p.activo
            FROM personal p
            LEFT JOIN usuarios u ON u.personal_id = p.id
            WHERE u.id IS NULL
        ")->fetchAll();
        $created = 0; $errors = 0;
        foreach ($rows as $p) {
            $r = sync_user_from_personal($pdo, (int)$p['id'], $p['dni'], (int)$p['activo'], false);
            if ($r['created']) $created++;
            if ($r['reason'])   $errors++;
        }
        $ok = "Sincronizacion completada. Usuarios creados: $created. ";
        if (count($rows) === 0) $ok = 'No hay personales sin usuario. Nada que sincronizar.';
        if ($errors) $ok .= " Errores: $errors.";
    }
}

// ---------- Borrar (GET con CSRF) ----------
if (isset($_GET['del'])) {
    csrf_check_get();
    $id = (int) $_GET['del'];
    try {
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        // Desvincular el usuario (preserva la cuenta, solo quita el link)
        $pdo->prepare('UPDATE usuarios SET personal_id = NULL WHERE personal_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM personal WHERE id = ?')->execute([$id]);
        $pdo->commit();
        $ok = 'Registro eliminado.';
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        $err = 'No se puede eliminar (puede tener papeletas asociadas): ' . $e->getMessage();
    }
}

$q = trim($_GET['q'] ?? '');
$sql = 'SELECT p.*,
               u.id AS user_id, u.username, u.rol, u.activo AS user_activo, u.must_change, u.last_login
        FROM personal p
        LEFT JOIN usuarios u ON u.personal_id = p.id';
$params = [];
if ($q !== '') {
    $sql .= ' WHERE p.dni LIKE ? OR p.apellidos_nombres LIKE ? OR p.dependencia LIKE ?';
    $params = ["%$q%", "%$q%", "%$q%"];
}
$sql .= ' ORDER BY p.apellidos_nombres LIMIT 500';
$st = DB::pdo()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

// Conteo para el boton sincronizar
$stCount = DB::pdo()->query("
    SELECT COUNT(*) FROM personal p
    LEFT JOIN usuarios u ON u.personal_id = p.id
    WHERE u.id IS NULL
")->fetchColumn();
$sinUsuario = (int) $stCount;

layout_header('Personal');
render_flashes();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0">
    <i class="bi bi-person-vcard"></i> Personal registrado
    <span class="badge text-bg-secondary"><?= count($rows) ?></span>
  </h5>
  <div class="d-flex gap-2 flex-wrap">
    <?php if ($sinUsuario > 0): ?>
      <form method="post" onsubmit="return confirm('Crear usuario (DNI/DNI) para los <?= $sinUsuario ?> personal(es) sin cuenta vinculada?');">
        <input type="hidden" name="_csrf"  value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="sync_all">
        <button class="btn btn-warning">
          <i class="bi bi-lightning-charge"></i>
          Sincronizar usuarios (<?= $sinUsuario ?>)
        </button>
      </form>
    <?php endif; ?>
    <a href="<?= url('admin/personal_nuevo.php') ?>" class="btn btn-primary">
      <i class="bi bi-plus-lg"></i> Nuevo personal
    </a>
    <a href="<?= url('admin/importar_personal.php') ?>" class="btn btn-success">
      <i class="bi bi-upload"></i> Importar CSV
    </a>
  </div>
</div>

<form method="get" class="mb-3">
  <div class="input-group">
    <input class="form-control" name="q" placeholder="Buscar por DNI, nombre o dependencia..." value="<?= e($q) ?>">
    <button class="btn btn-primary"><i class="bi bi-search"></i></button>
    <?php if ($q): ?><a href="?" class="btn btn-secondary"><i class="bi bi-x"></i></a><?php endif; ?>
  </div>
</form>

<?php if ($ok):  ?><div class="alert alert-success py-2"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger  py-2"><?= e($err) ?></div><?php endif; ?>

<div class="card">
  <div class="table-responsive">
  <table class="table table-sm table-hover mb-0 align-middle">
    <thead class="table-light">
      <tr>
        <th>DNI</th>
        <th>Apellidos y Nombres</th>
        <th>Regimen</th>
        <th>Dependencia</th>
        <th>Cargo</th>
        <th>Personal</th>
        <th>Usuario</th>
        <th>Clave</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="9" class="text-center text-muted py-4">
          Sin registros. Importe un CSV o cree uno nuevo.
        </td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><code><?= e($r['dni']) ?></code></td>
          <td><?= e($r['apellidos_nombres']) ?></td>
          <td><small><?= e($r['regimen_laboral']) ?></small></td>
          <td><small><?= e($r['dependencia']) ?></small></td>
          <td><small><?= e($r['cargo']) ?></small></td>

          <td>
            <?php if ((int)$r['activo']): ?>
              <span class="badge text-bg-success">Activo</span>
            <?php else: ?>
              <span class="badge text-bg-secondary">Inactivo</span>
            <?php endif; ?>
          </td>

          <td>
            <?php if ($r['user_id']): ?>
              <span class="badge text-bg-<?= $r['rol']==='admin'?'danger':'info' ?>" title="<?= e($r['username']) ?>">
                <?= e($r['rol']) ?>
              </span>
              <?php if (!(int)$r['user_activo']): ?>
                <span class="badge text-bg-secondary">deshabilitado</span>
              <?php endif; ?>
            <?php else: ?>
              <span class="badge text-bg-warning">sin usuario</span>
            <?php endif; ?>
          </td>

          <td>
            <?php if ($r['user_id']): ?>
              <?php if ((int)$r['must_change']): ?>
                <span class="badge text-bg-warning" title="Debe cambiar clave al ingresar">pendiente</span>
              <?php else: ?>
                <span class="badge text-bg-success" title="Ya cambio la clave">OK</span>
              <?php endif; ?>
            <?php else: ?>
              <span class="text-muted">-</span>
            <?php endif; ?>
          </td>

          <td class="text-end" style="white-space:nowrap">
            <a class="btn btn-sm btn-outline-primary"
               href="<?= url('admin/personal_nuevo.php') ?>?edit=<?= (int)$r['id'] ?>"
               title="Editar personal y gestionar usuario">
              <i class="bi bi-pencil"></i>
            </a>
            <a class="btn btn-sm btn-outline-danger"
               href="?del=<?= (int)$r['id'] ?>&_csrf=<?= e(csrf_token()) ?>&q=<?= e($q) ?>"
               onclick="return confirm('Eliminar este personal? Si tiene papeletas no se podra.');"
               title="Eliminar">
              <i class="bi bi-trash"></i>
            </a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
  </div>
</div>

<?php
function csrf_check_get(): void {
    $t = $_GET['_csrf'] ?? '';
    if (!hash_equals($_SESSION['_csrf'] ?? '', $t)) {
        http_response_code(419);
        die('Token CSRF invalido.');
    }
}
layout_footer();
