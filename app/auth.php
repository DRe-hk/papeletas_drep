<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function refresh_user_session(): void
{
    $u = current_user();
    if (!$u) return;
    $st = DB::pdo()->prepare(
        'SELECT u.id, u.username, u.rol, u.activo, u.must_change, u.personal_id,
                p.dni, p.apellidos_nombres, p.regimen, p.dependencia, p.cargo
         FROM usuarios u LEFT JOIN personal p ON p.id = u.personal_id
         WHERE u.id = ? AND u.activo = 1 LIMIT 1'
    );
    $st->execute([$u['id']]);
    $row = $st->fetch();
    if ($row) {
        unset($row['password_hash']);
        $_SESSION['user'] = $row;
    }
}

function require_login(): void
{
    if (!current_user()) {
        flash_set('warn', 'Debe iniciar sesión.');
        redirect('index.php');
    }
    refresh_user_session();
}

function require_admin(): void
{
    require_login();
    if (($_SESSION['user']['rol'] ?? '') !== 'admin') {
        http_response_code(403);
        die('<h1>403</h1><p>Acceso restringido al administrador.</p>');
    }
}

function try_login(string $username, string $password): ?array
{
    $st = DB::pdo()->prepare('SELECT u.*, p.dni, p.apellidos_nombres, p.regimen, p.dependencia, p.cargo
                              FROM usuarios u
                              LEFT JOIN personal p ON p.id = u.personal_id
                              WHERE u.username = ? AND u.activo = 1 LIMIT 1');
    $st->execute([$username]);
    $row = $st->fetch();
    if (!$row) return null;
    if (!password_verify($password, $row['password_hash'])) return null;

    // Rehash si el algoritmo ha cambiado
    if (password_needs_rehash($row['password_hash'], PASSWORD_BCRYPT)) {
        $new = password_hash($password, PASSWORD_BCRYPT);
        DB::pdo()->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')->execute([$new, $row['id']]);
    }

    DB::pdo()->prepare('UPDATE usuarios SET last_login = NOW() WHERE id = ?')->execute([$row['id']]);

    // No guardar el hash en la sesión
    unset($row['password_hash']);
    return $row;
}
