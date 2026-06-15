<?php
// =====================================================================
// Crea (o resetea) un usuario administrador.
// Uso:  php tools/create_admin.php <username> <password> [personal_id]
// Ej:   php tools/create_admin.php admin admin123
//       php tools/create_admin.php admin NuevoPass#2026 1
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/db.php';

$username   = $argv[1] ?? null;
$password   = $argv[2] ?? null;
$personalId = isset($argv[3]) ? (int) $argv[3] : null;

if (!$username || !$password) {
    fwrite(STDERR, "Uso: php tools/create_admin.php <username> <password> [personal_id]\n");
    exit(1);
}
if (strlen($password) < 6) {
    fwrite(STDERR, "La contraseña debe tener al menos 6 caracteres.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_BCRYPT);
$pdo  = DB::pdo();

$st = $pdo->prepare('SELECT id FROM usuarios WHERE username = ?');
$st->execute([$username]);
$id = $st->fetchColumn();

if ($id) {
    $pdo->prepare('UPDATE usuarios SET password_hash=?, rol="admin", activo=1, must_change=1, personal_id=? WHERE id=?')
        ->execute([$hash, $personalId, $id]);
    echo "Usuario '$username' actualizado (id=$id). Contraseña restablecida.\n";
} else {
    $pdo->prepare('INSERT INTO usuarios (username, password_hash, rol, activo, must_change, personal_id) VALUES (?,?,"admin",1,1,?)')
        ->execute([$username, $hash, $personalId]);
    echo "Usuario administrador '$username' creado.\n";
}
echo "Usuario: $username\n";
echo "Contraseña: $password\n";
echo "Rol: admin\n";
echo "IMPORTANTE: cambie la contraseña tras el primer ingreso.\n";
