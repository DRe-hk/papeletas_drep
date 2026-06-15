<?php
/**
 * Sincroniza el sistema de usuarios con la tabla personal.
 * Crea un usuario (DNI/DNI, must_change=1) para cada personal
 * que no tenga uno vinculado.
 *
 * Uso:
 *   php tools/sync_all_users.php
 *   php tools/sync_all_users.php --reset
 *   php tools/sync_all_users.php --dry-run
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/usuarios_sync.php';

$args   = array_slice($argv, 1);
$reset  = in_array('--reset',  $args, true);
$dryRun = in_array('--dry-run', $args, true);

$pdo = DB::pdo();

echo "===========================================\n";
echo "  Sync de usuarios desde tabla personal\n";
echo "===========================================\n\n";

$rows = $pdo->query('
    SELECT p.id, p.dni, p.apellidos_nombres, p.activo
    FROM personal p
    LEFT JOIN usuarios u ON u.personal_id = p.id
    WHERE u.id IS NULL
    ORDER BY p.apellidos_nombres
')->fetchAll();

if (!$rows) {
    echo "No hay personales sin usuario. Nada que sincronizar.\n";
    exit(0);
}

echo "Personales sin usuario: " . count($rows) . "\n";
if ($reset)  echo "Modo: RESET (contraseña = DNI, must_change=1)\n";
if ($dryRun) echo "Modo: DRY-RUN (no hace cambios)\n";
echo "\n";

$created = 0; $errors = 0;
foreach ($rows as $p) {
    if ($dryRun) {
        echo sprintf("  [DRY] Crearia usuario: %s  -  %s\n", $p['dni'], $p['apellidos_nombres']);
        $created++;
        continue;
    }
    $r = sync_user_from_personal($pdo, (int)$p['id'], $p['dni'], (int)$p['activo'], $reset);
    if ($r['created']) {
        $created++;
        echo sprintf("  [+] CREADO:  %s  -  %s  (id=%d)\n", $p['dni'], $p['apellidos_nombres'], $r['user_id']);
    } elseif ($r['reason']) {
        $errors++;
        echo sprintf("  [!] ERROR:   %s  -  %s  -  %s\n", $p['dni'], $p['apellidos_nombres'], $r['reason']);
    } else {
        echo sprintf("  [~] sin cambios: %s  -  %s\n", $p['dni'], $p['apellidos_nombres']);
    }
}

echo "\n-------------------------------------------\n";
echo "Resumen: $created usuarios creados";
if ($reset)  echo " (contraseña reseteada)";
if ($dryRun) echo " (DRY-RUN, no se persistio nada)";
echo ", $errors errores\n";
echo "-------------------------------------------\n";
