<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';

if (current_user()) {
    redirect('dashboard.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';

    if ($u === '' || $p === '') {
        $error = 'Ingrese usuario y contraseña.';
    } else {
        $row = try_login($u, $p);
        if ($row) {
            session_regenerate_id(true);
            $_SESSION['user'] = $row;
            if (!empty($row['must_change'])) {
                flash_set('warn', 'Por seguridad, cambie su contraseña en su primer ingreso.');
                redirect('perfil.php');
            }
            redirect('dashboard.php');
        }
        $error = 'Usuario o contraseña incorrectos.';
    }
}

$token = csrf_token();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Iniciar sesión · <?= e(APP_NAME) ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= e(url('favicon.svg')) ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body {
      min-height: 100vh;
      background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
      display:flex; align-items:center; justify-content:center;
    }
    .login-card { width: 100%; max-width: 420px; }
  </style>
</head>
<body>
  <div class="login-card">
    <div class="card shadow-lg">
      <div class="card-body p-4">
        <div class="text-center mb-3">
          <i class="bi bi-file-earmark-pdf text-primary" style="font-size:3rem"></i>
          <h4 class="mt-2 mb-0"><?= e(APP_NAME) ?></h4>
          <small class="text-muted">Sistema de Papeletas de Salida</small>
        </div>
        <hr>
        <?php if ($error): ?>
          <div class="alert alert-danger py-2"><?= e($error) ?></div>
        <?php endif; ?>
        <?php render_flashes(); ?>
        <form method="post" autocomplete="off">
          <input type="hidden" name="_csrf" value="<?= e($token) ?>">
          <div class="mb-3">
            <label class="form-label">Usuario</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-person"></i></span>
              <input type="text" name="username" class="form-control" required autofocus value="<?= e($_POST['username'] ?? '') ?>">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Contraseña</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-lock"></i></span>
              <input type="password" name="password" class="form-control" required>
            </div>
          </div>
          <button class="btn btn-primary w-100" type="submit">
            <i class="bi bi-box-arrow-in-right"></i> Ingresar
          </button>
        </form>
      </div>
      <div class="card-footer text-center text-muted small bg-light">
        Acceso restringido · Intranet DRE Puno
      </div>
    </div>
  </div>
</body>
</html>
