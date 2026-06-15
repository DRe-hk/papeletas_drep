<?php
declare(strict_types=1);

function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Construye una URL absoluta (desde la raiz del DocumentRoot).
 *
 * Importante: usa SIEMPRE rutas absolutas con / inicial para que los links
 * funcionen identico desde /dashboard.php, /admin/usuarios.php o cualquier
 * subdirectorio. Antes esta funcion tomaba dirname() del script actual, lo
 * que producia enlaces tipo /admin/dashboard.php cuando se estaba dentro de
 * /admin/ - bug 404 que reporto el usuario.
 *
 * Si la app se instala en un subdirectorio (ej. http://server/papeletas/),
 * definir APP_BASE = '/papeletas' en config.php.
 */
function url(string $path = ''): string
{
    static $base = null;
    if ($base === null) {
        $base = defined('APP_BASE') ? rtrim((string) APP_BASE, '/') : '';
    }
    if ($path === '') {
        return $base === '' ? '/' : ($base . '/');
    }
    return $base . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function flash_set(string $type, string $msg): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'msg' => $msg];
}

function flash_pop(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['_csrf'];
}

function csrf_check(): void
{
    $t = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['_csrf'] ?? '', $t)) {
        http_response_code(419);
        die('Token CSRF inválido. Recarga la página e intenta de nuevo.');
    }
}

function layout_header(string $titulo): void
{
    $u = current_user();
    ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titulo) ?> · <?= e(APP_NAME) ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= e(url('favicon.svg')) ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
      body { background:#f4f6f9; }
      .navbar-brand strong { letter-spacing:.5px; }
      .card { border:0; box-shadow:0 1px 3px rgba(0,0,0,.06); }
      .badge-anio { background:#0d6efd; }
      main { padding-bottom: 4rem; }
      footer { color:#6c757d; font-size:.85rem; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container">
    <a class="navbar-brand" href="<?= url('dashboard.php') ?>">
      <i class="bi bi-file-earmark-pdf"></i> <strong><?= e(APP_NAME) ?></strong>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="<?= url('dashboard.php') ?>">Inicio</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= url('papeleta_nueva.php') ?>"><i class="bi bi-plus-circle"></i> Nueva papeleta</a></li>
        <?php if ($u && $u['rol'] === 'admin'): ?>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">Administración</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="<?= url('admin/personal.php') ?>"><i class="bi bi-person-vcard"></i> Personal y usuarios</a></li>
            <li><a class="dropdown-item" href="<?= url('admin/importar_personal.php') ?>"><i class="bi bi-upload"></i> Importar personal (CSV)</a></li>
            <li><a class="dropdown-item" href="<?= url('admin/papeletas.php') ?>"><i class="bi bi-files"></i> Todas las papeletas</a></li>
          </ul>
        </li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav">
        <li class="nav-item"><a class="nav-link" href="<?= url('perfil.php') ?>"><i class="bi bi-person-circle"></i> <?= e($u['username'] ?? '') ?></a></li>
        <li class="nav-item"><a class="nav-link" href="<?= url('logout.php') ?>"><i class="bi bi-box-arrow-right"></i> Salir</a></li>
      </ul>
    </div>
  </div>
</nav>
<main class="container py-4">
<?php
}

function layout_footer(): void
{
    ?>
</main>
<footer class="container py-3 text-center">
  <hr><small><?= e(APP_NAME) ?> · <?= date('Y') ?></small>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
}

function render_flashes(): void
{
    foreach (flash_pop() as $f) {
        $cls = match ($f['type']) {
            'ok'   => 'success',
            'err'  => 'danger',
            'warn' => 'warning',
            default => 'info',
        };
        echo '<div class="alert alert-' . $cls . ' alert-dismissible fade show" role="alert">'
           . e($f['msg'])
           . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
}
