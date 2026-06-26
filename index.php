<?php
declare(strict_types=1);

require_once __DIR__ . "/app/auth.php";

if (current_user()) {
    redirect("dashboard.php");
}

$error = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_check();
    $u = trim($_POST["username"] ?? "");
    $p = $_POST["password"] ?? "";

    if ($u === "" || $p === "") {
        $error = "Ingrese usuario y contraseña.";
    } else {
        $row = try_login($u, $p);
        if ($row) {
            session_regenerate_id(true);
            $_SESSION["user"] = $row;
            if (!empty($row["must_change"])) {
                flash_set(
                    "warn",
                    "Por seguridad, cambie su contraseña en su primer ingreso.",
                );
                redirect("perfil.php");
            }
            redirect("dashboard.php");
        }
        $error = "Usuario o contraseña incorrectos.";
    }
}

$token = csrf_token();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Iniciar sesion · <?= e(APP_NAME) ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= e(url("favicon.svg")) ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style><?= design_tokens_css() ?>
  body { background: var(--surface-page); }
  </style>
</head>
<body>
<div class="login-shell">
  <div class="login-card">
    <div class="login-head">
      <img src="<?= e(url('assets/logo.png')) ?>" alt="" class="brand-logo">
      <h4>Papeletas de Salida</h4>
      <small><?= e(APP_NAME) ?></small>
    </div>
    <div class="login-body">
      <?php if ($error): ?>
        <div class="alert alert-danger py-2">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <div><?= e($error) ?></div>
        </div>
      <?php endif; ?>
      <?php render_flashes(); ?>
      <form method="post" autocomplete="off" novalidate>
        <input type="hidden" name="_csrf" value="<?= e($token) ?>">
        <div class="mb-3">
          <label class="form-label">Usuario</label>
          <div class="input-icon">
            <i class="bi bi-person"></i>
            <input type="text" name="username" class="form-control" required autofocus value="<?= e(
                $_POST["username"] ?? "",
            ) ?>" placeholder="Su DNI o usuario">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Contrasena</label>
          <div class="input-icon">
            <i class="bi bi-lock"></i>
            <input type="password" name="password" class="form-control" required placeholder="Su contrasena">
          </div>
        </div>
        <button class="btn btn-cta w-100" type="submit">
          <i class="bi bi-box-arrow-in-right"></i> Ingresar
        </button>
      </form>
    </div>
    <div class="login-foot">
      Acceso restringido &middot; Direccion Regional de Educacion &middot; Oficina de Informatica
    </div>
  </div>
</div>
</body>
</html>
