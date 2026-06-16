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
        $err = 'La nueva contrasena debe tener al menos 6 caracteres.';
    } elseif ($nueva !== $nueva2) {
        $err = 'La confirmacion no coincide.';
    } else {
        $st = DB::pdo()->prepare('SELECT password_hash FROM usuarios WHERE id = ?');
        $st->execute([$u['id']]);
        $row = $st->fetch();
        if (!$row || !password_verify($actual, $row['password_hash'])) {
            $err = 'La contrasena actual es incorrecta.';
        } else {
            $hash = password_hash($nueva, PASSWORD_BCRYPT);
            DB::pdo()->prepare('UPDATE usuarios SET password_hash = ?, must_change = 0 WHERE id = ?')
                     ->execute([$hash, $u['id']]);
            $ok = 'Contrasena actualizada.';
        }
    }
}

layout_header('Mi perfil');
render_flashes();
?>

<div class="page-header">
  <div>
    <h1><i class="bi bi-person-circle"></i> Mi perfil</h1>
    <div class="page-sub">Administre su informacion de cuenta y cambie su contrasena cuando lo necesite.</div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header"><i class="bi bi-person-vcard me-1"></i> Informacion de la cuenta</div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-4 text-muted fw-normal small">Usuario</dt>
          <dd class="col-sm-8"><code><?= e($u['username']) ?></code></dd>
          <dt class="col-sm-4 text-muted fw-normal small">Rol</dt>
          <dd class="col-sm-8">
            <?php if (($u['rol'] ?? '') === 'admin'): ?>
              <span class="badge text-bg-danger">admin</span>
            <?php else: ?>
              <span class="badge text-bg-secondary">usuario</span>
            <?php endif; ?>
          </dd>
          <dt class="col-sm-4 text-muted fw-normal small">DNI</dt>
          <dd class="col-sm-8 text-mono"><?= e($u['dni'] ?? '—') ?></dd>
          <dt class="col-sm-4 text-muted fw-normal small">Nombres</dt>
          <dd class="col-sm-8"><?= e($u['apellidos_nombres'] ?? '—') ?></dd>
        </dl>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header"><i class="bi bi-key me-1"></i> Cambiar contrasena</div>
      <div class="card-body">
        <?php if ($err): ?>
          <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div><?= e($err) ?></div>
          </div>
        <?php endif; ?>
        <?php if ($ok): ?>
          <div class="alert alert-success">
            <i class="bi bi-check-circle-fill"></i>
            <div><?= e($ok) ?></div>
          </div>
        <?php endif; ?>
        <form method="post" class="row g-3">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <div class="col-12">
            <label class="form-label">Contrasena actual</label>
            <div class="input-icon">
              <i class="bi bi-lock"></i>
              <input type="password" name="actual" class="form-control" required>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Nueva contrasena</label>
            <div class="input-icon">
              <i class="bi bi-shield-lock"></i>
              <input type="password" name="nueva" class="form-control" minlength="6" required>
            </div>
            <div class="form-text">Minimo 6 caracteres.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Confirmar nueva contrasena</label>
            <div class="input-icon">
              <i class="bi bi-shield-check"></i>
              <input type="password" name="nueva2" class="form-control" minlength="6" required>
            </div>
          </div>
          <div class="col-12 d-flex justify-content-end">
            <button class="btn btn-primary"><i class="bi bi-save"></i> Guardar cambios</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php layout_footer();
