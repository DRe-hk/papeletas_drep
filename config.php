<?php
// =====================================================================
//  Sistema de Papeletas de Salida - DRE Puno
//  Configuración general
// =====================================================================
//  IMPORTANTE: este archivo NO debe estar dentro de /public.
//  Si lo subes a un repo, usa .gitignore para excluirlo.
// =====================================================================

declare(strict_types=1);

// Zona horaria Peru
date_default_timezone_set('America/Lima');

// Rutas absolutas del proyecto (config.php vive en la raíz del proyecto)
define('BASE_PATH',     __DIR__);
define('APP_PATH',      BASE_PATH . '/app');
define('PUBLIC_PATH',   BASE_PATH . '/public');
define('STORAGE_PATH',  BASE_PATH . '/storage');
define('PLANTILLA_PDF', STORAGE_PATH . '/plantilla/Papeleta-de-salida-2026.pdf');

// Cargar Composer
require BASE_PATH . '/vendor/autoload.php';

// -------------------------------------------------------------------------
//  Base de datos - ajusta estos valores a tu entorno Laragon
// -------------------------------------------------------------------------
const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'papeletas_db';
const DB_USER = 'root';
const DB_PASS = '';        // Laragon por defecto deja root sin password
const DB_CHARSET = 'utf8mb4';

// Configuración de la app
const APP_NAME = 'DRE Puno - Papeletas de Salida';
const APP_URL  = '';       // Se detecta automáticamente si está vacío

// Prefijo URL de la app dentro del DocumentRoot del servidor.
// Por defecto '' (la app vive en la raiz del DocumentRoot).
// Si la app se sirve en un subdirectorio (ej. http://server/papeletas/),
// cambiar a '/papeletas'. Dejar '' para el caso tipico de Laragon con
// VirtualHost cuyo DocumentRoot apunta a D:/DREP/PAPELETAS/public.
const APP_BASE = '';

// Sesiones (seguridad básica)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', '1800');
    session_name('PAPELETAS_SESSID');
    session_start();
}

// Errores - en producción: 0
ini_set('display_errors', '1');
error_reporting(E_ALL);
