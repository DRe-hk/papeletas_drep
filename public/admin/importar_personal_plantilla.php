<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/auth.php';
require_admin();

$rows = DB::pdo()->query('SELECT * FROM personal ORDER BY apellidos_nombres LIMIT 2000')->fetchAll();

$filename = 'plantilla_personal_' . date('Ymd') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM para que Excel detecte UTF-8
fputcsv($out, ['dni','apellidos_nombres','regimen','dependencia','cargo']);
foreach ($rows as $r) {
    fputcsv($out, [$r['dni'], $r['apellidos_nombres'], $r['regimen'], $r['dependencia'], $r['cargo']]);
}
fclose($out);
