<?php
declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';
require_login();

$u = current_user();
$err = null;
$ok  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $actual    = $_POST['actual']    ?? '';
    $nueva     = $_POST['nueva']     ?? '';
    $nueva2    = $_POST['nueva2']    ?? '';

    if ($nueva === '' || strlen($nueva) < 6) {
        $err = 'La nueva contraseña debe tener al menos 6 caracteres.';
    } elseif ($nueva !== $nueva2) {
        $err = 'La confirmación no coincide.';
    } else {
        $st = DB::pdo()->prepare('SELECT password_hash FROM usuarios WHERE id = ?');
        $st->execute([$u['id']]);
        $row = $st->fetch();
        if (!$row || !password_verify($actual, $row['password_hash'])) {
            $err = 'La contraseña actual es incorrecta.';
        } else {
            $hash = password_hash($nueva, PASSWORD_BCRYPT);
            DB::pdo()->prepare('UPDATE usuarios SET password_hash = ?, must_change = 0 WHERE id = ?')
                     ->execute([$hash, $u['id']]);
            $ok = 'Contraseña actualizada.';
        }
    }
}

layout_header('Mi perfil');
render_flashes();
?>
<div class="row g-3">
  <div class="col-md-5">
    <div class="card">
      <div class="card-body">
        <h5 class="card-title"><i class="bi bi-person-circle"></i> Mi cuenta</h5>
        <dl class="row mb-0">
          <dt class="col-sm-4">Usuario</dt><dd class="col-sm-8"><?= e($u['username']) ?></dd>
          <dt class="col-sm-4">Rol</dt>     <dd class="col-sm-8"><?= e($u['rol']) ?></dd>
          <dt class="col-sm-4">DNI</dt>     <dd class="col-sm-8"><?= e($u['dni'] ?? '-') ?></dd>
          <dt class="col-sm-4">Nombres</dt> <dd class="col-sm-8"><?= e($u['apellidos_nombres'] ?? '-') ?></dd>
        </dl>
      </div>
    </div>
  </div>
  <div class="col-md-7">
    <div class="card">
      <div class="card-body">
        <h5 class="card-title"><i class="bi bi-key"></i> Cambiar contraseña</h5>
        <?php if ($err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endif; ?>
        <?php if ($ok):  ?><div class="alert alert-success py-2"><?= e($ok) ?></div><?php endif; ?>
        <form method="post" class="row g-3">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <div class="col-12">
            <label class="form-label">Contraseña actual</label>
            <input type="password" name="actual" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Nueva contraseña</label>
            <input type="password" name="nueva" class="form-control" minlength="6" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Confirmar</label>
            <input type="password" name="nueva2" class="form-control" minlength="6" required>
          </div>
          <div class="col-12">
            <button class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php layout_footer();
