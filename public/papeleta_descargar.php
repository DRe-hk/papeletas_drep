<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/pdf.php';
require_login();

$u   = current_user();
$id  = (int) ($_GET['id'] ?? 0);

$st = DB::pdo()->prepare('SELECT * FROM papeletas WHERE id = ?');
$st->execute([$id]);
$p = $st->fetch();

if (!$p) { http_response_code(404); die('Papeleta no encontrada.'); }

// Permisos: el dueño o un admin
if ($p['usuario_id'] != $u['id'] && $u['rol'] !== 'admin') {
    http_response_code(403);
    die('No tiene permiso para ver esta papeleta.');
}

// Cargar datos del personal para el snapshot
$st = DB::pdo()->prepare('SELECT * FROM personal WHERE id = ?');
$st->execute([$p['personal_id']]);
$per = $st->fetch() ?: [];

$payload = array_merge($per, [
    'numero'    => $p['numero'],
    'fecha'     => date('d/m/Y', strtotime($p['fecha_emision'])),
    'dni'       => $per['dni']        ?? '',
    'apellidos_nombres' => $per['apellidos_nombres'] ?? '',
    'regimen'           => $per['regimen']           ?? '',
    'dependencia'       => $per['dependencia']       ?? '',
    'cargo'             => $per['cargo']             ?? '',
    'motivo_salida'     => $p['motivo_salida'],
    'fundamentacion'    => $p['fundamentacion'],
    'lugar'             => $p['lugar'],
    'dia'               => $p['dia'],
    'mes'               => $p['mes'],
    'anio_dmy'          => $p['anio_dmy'],
    'hora_salida'       => $p['hora_salida'],
    'hora_retorno'      => $p['hora_retorno'],
    'retorna'           => $p['retorna'],
    'observaciones'     => $p['observaciones'],
]);

PapeletaPDF::generar($payload, 'D', 'papeleta-' . $p['numero'] . '.pdf');
