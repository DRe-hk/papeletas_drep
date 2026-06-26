<?php
declare(strict_types=1);

function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Construye una URL absoluta (desde la raiz del DocumentRoot).
 *
 * Auto-detecta el subdirectorio donde esta instalada la app para que las
 * URLs funcionen tanto con VirtualHost (DocumentRoot = raiz del proyecto)
 * como con un subdirectorio (http://server/papeletas/).
 *
 * Prioridad:
 *  1. Si APP_BASE esta definido y NO vacio en config.php -> se usa tal cual.
 *  2. Si no, se detecta del SCRIPT_NAME actual.
 */
function url(string $path = ''): string
{
    static $base = null;
    if ($base === null) {
        $base = detect_app_base();
    }
    if ($path === '') {
        return $base === '' ? '/' : ($base . '/');
    }
    return $base . '/' . ltrim($path, '/');
}

function detect_app_base(): string
{
    if (defined('APP_BASE') && APP_BASE !== '') {
        return rtrim((string) APP_BASE, '/');
    }
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = str_replace('\\', '/', dirname($script));
    $base = preg_replace('~/(admin|tools|storage|vendor|sql)(/.*)?$~', '', $dir);
    return rtrim((string) $base, '/');
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
        die('Token CSRF invalido. Recarga la pagina e intenta de nuevo.');
    }
}

/* =========================================================================
   DESIGN SYSTEM
   - Bootstrap 5 retained for grid + utilities
   - Custom tokens override Bootstrap CSS variables (--bs-*)
   - Warm institutional palette (cream + DREP blue + Andean terracotta)
   - Single sans family (system-ui stack)
   ========================================================================= */

function design_tokens_css(): string
{
    return <<<'CSS'
:root {
  /* === Brand & semantic palette (OKLCH) === */
  --brand-50:  oklch(97% 0.015 245);
  --brand-100: oklch(93% 0.03  245);
  --brand-200: oklch(86% 0.06  245);
  --brand-400: oklch(62% 0.11  245);
  --brand-500: oklch(48% 0.13  245);   /* DREP blue (primary) */
  --brand-600: oklch(42% 0.14  245);
  --brand-700: oklch(36% 0.13  245);

  --warm-300:  oklch(78% 0.06  60);
  --warm-400:  oklch(68% 0.10  50);
  --warm-500:  oklch(60% 0.15  45);    /* Andean terracotta (accent) */
  --warm-600:  oklch(52% 0.16  42);

  --ok-500:    oklch(60% 0.12  150);
  --warn-500:  oklch(78% 0.13  75);
  --err-500:   oklch(58% 0.18  25);

  /* === Surfaces === */
  --surface-page:    oklch(97.5% 0.012 80);
  --surface-card:    oklch(100%  0.005 80);
  --surface-sunken:  oklch(95%   0.015 80);
  --surface-hover:   oklch(96%   0.012 80);
  --border-soft:     oklch(90%   0.012 70);
  --border-strong:   oklch(82%   0.015 70);

  /* === Text === */
  --text-1: oklch(22% 0.015 60);
  --text-2: oklch(42% 0.012 60);
  --text-3: oklch(58% 0.010 65);
  --text-on-brand: oklch(99% 0.005 245);

  /* === Bootstrap overrides === */
  --bs-body-bg:        var(--surface-page);
  --bs-body-color:     var(--text-1);
  --bs-primary:        var(--brand-500);
  --bs-primary-rgb:    35, 78, 158;
  --bs-link-color:     var(--brand-600);
  --bs-link-hover-color: var(--brand-700);
  --bs-border-color:   var(--border-soft);
  --bs-secondary-color: var(--text-2);
  --bs-tertiary-color:  var(--text-3);
  --bs-tertiary-bg:    var(--surface-sunken);

  /* === Layout === */
  --radius-sm: 6px;
  --radius:    10px;
  --radius-lg: 14px;
  --shadow-1: 0 1px 2px oklch(20% 0.02 60 / .05), 0 1px 3px oklch(20% 0.02 60 / .04);
  --shadow-2: 0 2px 4px oklch(20% 0.02 60 / .06), 0 4px 12px oklch(20% 0.02 60 / .05);
  --shadow-3: 0 4px 8px oklch(20% 0.02 60 / .08), 0 12px 32px oklch(20% 0.02 60 / .08);

  /* === Type === */
  --font-sans: system-ui, -apple-system, "Segoe UI", "Inter", Roboto,
               "Helvetica Neue", Arial, sans-serif;
  --font-mono: ui-monospace, "SFMono-Regular", "Menlo", "Consolas", monospace;
  --leading: 1.55;
}

/* === Base === */
html, body { height: 100%; }
body {
  font-family: var(--font-sans);
  font-size: 15px;
  line-height: var(--leading);
  color: var(--text-1);
  background:
    radial-gradient(1200px 600px at 100% -10%, oklch(95% 0.04 245 / .35), transparent 60%),
    radial-gradient(900px 500px at -10% 110%, oklch(95% 0.05 50 / .30), transparent 60%),
    var(--surface-page);
  background-attachment: fixed;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  font-feature-settings: "ss01", "cv11", "tnum" 0;
}
h1, h2, h3, h4, h5, h6 { color: var(--text-1); letter-spacing: -.01em; font-weight: 600; }
h1 { font-size: 1.7rem; }
h2 { font-size: 1.4rem; }
h3 { font-size: 1.2rem; }
h5 { font-size: 1.0rem; }
h6 { font-size: .85rem; text-transform: uppercase; letter-spacing: .08em; color: var(--text-2); font-weight: 600; }
small, .small { color: var(--text-2); }
code, kbd, pre { font-family: var(--font-mono); font-size: .92em; }
.text-mono { font-family: var(--font-mono); font-variant-numeric: tabular-nums; }

/* === Navbar (professional, cream with brand accent) === */
.app-navbar {
  background: var(--surface-card);
  border-bottom: 1px solid var(--border-soft);
  box-shadow: var(--shadow-1);
  padding: .65rem 0;
}
.app-navbar .navbar-brand {
  display: flex; align-items: center; gap: .65rem;
  color: var(--text-1);
}
.app-navbar .brand-logo { height: 36px; width: auto; border-radius: 8px; }
.app-navbar .brand-text { display: flex; flex-direction: column; line-height: 1.1; }
.app-navbar .brand-text .name { font-weight: 700; font-size: 1rem; color: var(--text-1); letter-spacing: .01em; text-transform: uppercase; }

.app-navbar .nav-link {
  color: var(--text-2);
  font-weight: 500;
  font-size: .92rem;
  padding: .45rem .85rem;
  border-radius: var(--radius-sm);
  transition: background .15s ease, color .15s ease;
}
.app-navbar .nav-link:hover { color: var(--text-1); background: var(--surface-hover); }
.app-navbar .nav-link.active { color: var(--brand-600); background: var(--brand-50); }
.app-navbar .nav-link i { margin-right: .35rem; }

.app-navbar .dropdown-menu {
  border: 1px solid var(--border-soft);
  border-radius: var(--radius);
  box-shadow: var(--shadow-2);
  padding: .35rem;
  margin-top: .35rem;
}
.app-navbar .dropdown-item {
  border-radius: var(--radius-sm);
  padding: .5rem .75rem;
  font-size: .9rem;
  color: var(--text-1);
}
.app-navbar .dropdown-item:hover { background: var(--brand-50); color: var(--brand-700); }
.app-navbar .dropdown-item i { color: var(--text-3); margin-right: .5rem; width: 16px; }
.app-navbar .dropdown-item:hover i { color: var(--brand-500); }

.app-navbar .user-chip {
  display: flex; align-items: center; gap: .55rem;
  padding: .3rem .6rem .3rem .35rem;
  border: 1px solid var(--border-soft);
  border-radius: 999px;
  background: var(--surface-card);
  color: var(--text-1);
  font-size: .85rem;
  font-weight: 500;
  text-decoration: none;
  transition: border-color .15s ease, background .15s ease;
}
.app-navbar .user-chip:hover { border-color: var(--brand-200); background: var(--brand-50); color: var(--brand-700); }
.app-navbar .user-chip i.bi-gear {
  font-size: 1rem;
  color: var(--text-2);
}
.app-navbar .user-chip .role-dot {
  width: 7px; height: 7px; border-radius: 50%;
  background: var(--ok-500);
  box-shadow: 0 0 0 2px oklch(60% 0.12 150 / .18);
}

/* === Cards === */
.card {
  background: var(--surface-card);
  border: 1px solid var(--border-soft);
  border-radius: var(--radius);
  box-shadow: var(--shadow-1);
}
.card .card-header {
  background: transparent;
  border-bottom: 1px solid var(--border-soft);
  padding: .9rem 1.1rem;
  font-weight: 600;
  color: var(--text-1);
  font-size: .95rem;
}
.card .card-body { padding: 1.1rem; }
.card .card-footer { background: transparent; border-top: 1px solid var(--border-soft); padding: .8rem 1.1rem; }

/* === Stat card === */
.stat-card {
  background: var(--surface-card);
  border: 1px solid var(--border-soft);
  border-radius: var(--radius);
  padding: 1.1rem 1.25rem;
  box-shadow: var(--shadow-1);
  display: flex; flex-direction: column; gap: .35rem;
  position: relative;
  overflow: hidden;
}
.stat-card .stat-label {
  font-size: .72rem; text-transform: uppercase; letter-spacing: .08em;
  color: var(--text-3); font-weight: 600;
}
.stat-card .stat-value {
  font-size: 2.1rem; font-weight: 700; line-height: 1; color: var(--text-1);
  font-variant-numeric: tabular-nums;
  letter-spacing: -.02em;
}
.stat-card .stat-sub { font-size: .82rem; color: var(--text-2); }
.stat-card .stat-icon {
  position: absolute; right: 1rem; top: 1rem;
  width: 36px; height: 36px;
  display: inline-flex; align-items: center; justify-content: center;
  border-radius: 8px;
  background: var(--brand-50); color: var(--brand-500);
  font-size: 1.1rem;
}
.stat-card.warm .stat-icon { background: oklch(95% 0.04 50 / .6); color: var(--warm-500); }
.stat-card.ok   .stat-icon { background: oklch(95% 0.05 150 / .5); color: var(--ok-500); }

/* === Buttons === */
.btn { font-weight: 500; font-size: .9rem; border-radius: var(--radius-sm); padding: .45rem .9rem; transition: background .15s ease, color .15s ease, box-shadow .15s ease, transform .1s ease; }
.btn:focus-visible { outline: 2px solid var(--warm-500); outline-offset: 2px; box-shadow: none; }
.btn-primary {
  --bs-btn-bg: var(--brand-500);
  --bs-btn-border-color: var(--brand-500);
  --bs-btn-hover-bg: var(--brand-600);
  --bs-btn-hover-border-color: var(--brand-600);
  --bs-btn-active-bg: var(--brand-700);
  --bs-btn-active-border-color: var(--brand-700);
  --bs-btn-color: #fff;
  --bs-btn-hover-color: #fff;
  --bs-btn-active-color: #fff;
  box-shadow: 0 1px 2px oklch(48% 0.13 245 / .18);
}
.btn-primary:hover { box-shadow: 0 4px 10px oklch(48% 0.13 245 / .25); }
.btn-cta {
  background: linear-gradient(135deg, var(--brand-500), var(--brand-600));
  border: 0;
  color: white;
  font-weight: 600;
  padding: .65rem 1.25rem;
  border-radius: var(--radius);
  box-shadow: 0 2px 6px oklch(48% 0.13 245 / .28), inset 0 1px 0 oklch(100% 0 0 / .12);
  font-size: .95rem;
  display: inline-flex; align-items: center; gap: .5rem;
}
.btn-cta:hover {
  background: linear-gradient(135deg, var(--brand-600), var(--brand-700));
  color: white;
  transform: translateY(-1px);
  box-shadow: 0 6px 14px oklch(48% 0.13 245 / .35), inset 0 1px 0 oklch(100% 0 0 / .15);
}
.btn-cta i { font-size: 1.1rem; }

.btn-outline-primary {
  --bs-btn-color: var(--brand-600);
  --bs-btn-border-color: var(--brand-200);
  --bs-btn-hover-bg: var(--brand-50);
  --bs-btn-hover-border-color: var(--brand-400);
  --bs-btn-hover-color: var(--brand-700);
  --bs-btn-active-bg: var(--brand-100);
  --bs-btn-active-border-color: var(--brand-500);
}
.btn-secondary {
  --bs-btn-bg: var(--surface-card);
  --bs-btn-border-color: var(--border-strong);
  --bs-btn-color: var(--text-1);
  --bs-btn-hover-bg: var(--surface-hover);
  --bs-btn-hover-border-color: var(--text-3);
  --bs-btn-hover-color: var(--text-1);
  --bs-btn-active-bg: var(--surface-sunken);
  --bs-btn-active-border-color: var(--text-3);
}
.btn-danger {
  --bs-btn-bg: var(--err-500);
  --bs-btn-border-color: var(--err-500);
  --bs-btn-hover-bg: oklch(52% 0.18 25);
  --bs-btn-hover-border-color: oklch(52% 0.18 25);
  --bs-btn-active-bg: oklch(46% 0.18 25);
  --bs-btn-active-border-color: oklch(46% 0.18 25);
  --bs-btn-color: #fff;
  --bs-btn-hover-color: #fff;
}
.btn-warning {
  --bs-btn-bg: var(--warn-500);
  --bs-btn-border-color: var(--warn-500);
  --bs-btn-color: var(--text-1);
  --bs-btn-hover-bg: oklch(72% 0.14 75);
  --bs-btn-hover-border-color: oklch(72% 0.14 75);
  --bs-btn-hover-color: var(--text-1);
}
.btn-sm { padding: .25rem .6rem; font-size: .8rem; }
.btn-icon {
  width: 32px; height: 32px; padding: 0;
  display: inline-flex; align-items: center; justify-content: center;
  border-radius: var(--radius-sm);
}

/* === Forms === */
.form-label {
  font-size: .82rem; font-weight: 600;
  color: var(--text-1);
  margin-bottom: .35rem;
  letter-spacing: .01em;
}
.form-label .req { color: var(--err-500); margin-left: .15rem; }
.form-text { font-size: .78rem; color: var(--text-3); margin-top: .25rem; }
.form-control, .form-select {
  border: 1px solid var(--border-strong);
  background: var(--surface-card);
  color: var(--text-1);
  border-radius: var(--radius-sm);
  padding: .55rem .8rem;
  font-size: .92rem;
  transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
}
.form-control:hover, .form-select:hover { border-color: var(--text-3); }
.form-control:focus, .form-select:focus {
  border-color: var(--brand-500);
  box-shadow: 0 0 0 3px oklch(48% 0.13 245 / .15);
  background: var(--surface-card);
}
.form-control[readonly] {
  background: var(--surface-sunken);
  color: var(--text-2);
  border-style: dashed;
}
.form-control::placeholder { color: var(--text-3); }

.form-control[type="date"], .form-control[type="time"], .form-control[type="datetime-local"] {
  font-family: var(--font-mono);
  font-variant-numeric: tabular-nums;
  letter-spacing: .02em;
}
.form-control[type="date"]::-webkit-calendar-picker-indicator,
.form-control[type="time"]::-webkit-calendar-picker-indicator {
  cursor: pointer;
  opacity: .55;
  filter: invert(20%) sepia(60%) saturate(800%) hue-rotate(210deg);
}
.form-control[type="date"]::-webkit-calendar-picker-indicator:hover,
.form-control[type="time"]::-webkit-calendar-picker-indicator:hover { opacity: 1; }

/* === Input group with icon (visual dropdown trigger) === */
.input-icon {
  position: relative;
}
.input-icon > i.bi {
  position: absolute; left: .75rem; top: 50%; transform: translateY(-50%);
  color: var(--text-3); font-size: .95rem; pointer-events: none;
}
.input-icon > .form-control,
.input-icon > .form-select { padding-left: 2.25rem; }

/* === Pill radio group (motivo_salida) === */
.pill-group { display: flex; flex-wrap: wrap; gap: .4rem; }
.pill-group .form-check { margin: 0; padding: 0; }
.pill-group .form-check-input {
  position: absolute; opacity: 0; pointer-events: none;
}
.pill-group .form-check-label {
  display: inline-block;
  padding: .45rem .85rem;
  border: 1px solid var(--border-strong);
  border-radius: 999px;
  background: var(--surface-card);
  color: var(--text-2);
  font-size: .85rem; font-weight: 500;
  cursor: pointer;
  transition: all .12s ease;
  user-select: none;
}
.pill-group .form-check-label:hover {
  border-color: var(--brand-400);
  color: var(--brand-700);
  background: var(--brand-50);
}
.pill-group .form-check-input:checked + .form-check-label {
  background: var(--brand-500);
  border-color: var(--brand-500);
  color: white;
  box-shadow: 0 1px 3px oklch(48% 0.13 245 / .25);
}
.pill-group .form-check-input:focus-visible + .form-check-label {
  outline: 2px solid var(--warm-500);
  outline-offset: 2px;
}

/* === Toggle SI/NO === */
.toggle-pill {
  display: inline-flex;
  background: var(--surface-sunken);
  border: 1px solid var(--border-soft);
  border-radius: 999px;
  padding: 3px;
  gap: 2px;
}
.toggle-pill input[type="radio"] { position: absolute; opacity: 0; }
.toggle-pill label {
  padding: .35rem 1.1rem;
  font-size: .85rem; font-weight: 600;
  border-radius: 999px;
  cursor: pointer;
  color: var(--text-2);
  transition: all .15s ease;
  user-select: none;
  margin: 0;
}
.toggle-pill input[value="SI"]:checked ~ label[for="ret_si"],
.toggle-pill input[value="NO"]:checked ~ label[for="ret_no"] {
  /* matched in markup via :has if available, fallback below */
}
.toggle-pill label:has(input[checked]) { /* placeholder, no-op */ }
.toggle-pill input:checked + label {
  background: var(--surface-card);
  color: var(--text-1);
  box-shadow: var(--shadow-1);
}
.toggle-pill input[value="SI"]:checked + label { color: var(--ok-500); }
.toggle-pill input[value="NO"]:checked + label { color: var(--err-500); }

/* === Tables === */
.table-wrap {
  background: var(--surface-card);
  border: 1px solid var(--border-soft);
  border-radius: var(--radius);
  box-shadow: var(--shadow-1);
  overflow: hidden;
}
.table-wrap .table { margin: 0; }
.table-wrap .table th {
  font-size: .72rem;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: var(--text-3);
  font-weight: 600;
  background: var(--surface-sunken);
  border-bottom: 1px solid var(--border-soft);
  padding: .7rem 1rem;
  white-space: nowrap;
}
.table-wrap .table td {
  padding: .8rem 1rem;
  vertical-align: middle;
  border-bottom: 1px solid var(--border-soft);
  color: var(--text-1);
  font-size: .9rem;
}
.table-wrap .table tr:last-child td { border-bottom: 0; }
.table-wrap .table tbody tr { transition: background .12s ease; }
.table-wrap .table tbody tr:hover { background: var(--surface-hover); }
.table-wrap .table .num { font-variant-numeric: tabular-nums; font-family: var(--font-mono); font-size: .85rem; }
.table-wrap .table .actions { white-space: nowrap; text-align: right; }
.table-wrap .table .actions .btn { margin-left: .25rem; }

/* === Badges === */
.badge {
  font-weight: 500;
  font-size: .72rem;
  padding: .3em .6em;
  border-radius: 6px;
  letter-spacing: .01em;
}
.badge.text-bg-primary { background: var(--brand-100) !important; color: var(--brand-700) !important; }
.badge.text-bg-info    { background: oklch(94% 0.04 220) !important; color: oklch(38% 0.10 220) !important; }
.badge.text-bg-success { background: oklch(94% 0.06 150) !important; color: oklch(36% 0.12 150) !important; }
.badge.text-bg-warning { background: oklch(95% 0.08 80)  !important; color: oklch(38% 0.10 60)  !important; }
.badge.text-bg-danger  { background: oklch(95% 0.05 25)  !important; color: oklch(40% 0.16 25)  !important; }
.badge.text-bg-secondary { background: var(--surface-sunken) !important; color: var(--text-2) !important; }

/* === Alerts === */
.alert {
  border: 0;
  border-radius: var(--radius);
  padding: .85rem 1rem;
  font-size: .9rem;
  display: flex;
  align-items: flex-start;
  gap: .6rem;
}
.alert > i.bi { font-size: 1.05rem; flex-shrink: 0; line-height: 1.4; }
.alert-success { background: oklch(96% 0.05 150); color: oklch(30% 0.10 150); }
.alert-warning { background: oklch(96% 0.07 80);  color: oklch(35% 0.10 70); }
.alert-danger  { background: oklch(96% 0.05 25);  color: oklch(38% 0.16 25); }
.alert-info    { background: var(--brand-50);    color: var(--brand-700); }
.alert .btn-close { margin-left: auto; }

/* === Empty state === */
.empty-state {
  padding: 3rem 1.5rem;
  text-align: center;
  color: var(--text-2);
}
.empty-state .empty-icon {
  width: 56px; height: 56px;
  margin: 0 auto 1rem;
  display: flex; align-items: center; justify-content: center;
  border-radius: 50%;
  background: var(--brand-50);
  color: var(--brand-500);
  font-size: 1.5rem;
}
.empty-state h5 { color: var(--text-1); font-size: 1.05rem; margin-bottom: .25rem; }
.empty-state p  { color: var(--text-2); font-size: .9rem; margin-bottom: 1rem; }

/* === Form section (used in forms) === */
.form-section {
  background: var(--surface-card);
  border: 1px solid var(--border-soft);
  border-radius: var(--radius);
  padding: 1.1rem 1.25rem 1.25rem;
  margin-bottom: 1rem;
  box-shadow: var(--shadow-1);
}
.form-section + .form-section { margin-top: 1rem; }
.form-section-title {
  display: flex; align-items: center; gap: .5rem;
  font-size: .78rem; text-transform: uppercase; letter-spacing: .08em;
  color: var(--text-2); font-weight: 700;
  margin: 0 0 .85rem 0; padding-bottom: .65rem;
  border-bottom: 1px solid var(--border-soft);
}
.form-section-title i { color: var(--brand-500); font-size: .9rem; }

/* === Read-only block (solicitante data) === */
.readonly-block {
  background: var(--surface-sunken);
  border: 1px dashed var(--border-soft);
  border-radius: var(--radius-sm);
  padding: .65rem .85rem;
  font-size: .9rem;
  color: var(--text-1);
  font-variant-numeric: tabular-nums;
}

/* === Sticky action bar (form footers) === */
.sticky-actions {
  position: sticky; bottom: 0;
  background: oklch(97% 0.012 80 / .92);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
  border-top: 1px solid var(--border-soft);
  padding: .85rem 0;
  margin-top: 1.5rem;
  z-index: 10;
}

/* === Page header === */
.page-header {
  display: flex; align-items: center; justify-content: space-between;
  gap: 1rem; flex-wrap: wrap;
  margin-bottom: 1.25rem;
}
.page-header h1 { margin: 0; display: flex; align-items: center; gap: .65rem; }
.page-header h1 i { color: var(--brand-500); }
.page-header .page-sub { color: var(--text-2); font-size: .88rem; margin-top: .15rem; }

/* === Toolbar (filter bar) === */
.toolbar {
  display: flex; gap: .5rem; align-items: center; flex-wrap: wrap;
  padding: .85rem 1rem;
  background: var(--surface-card);
  border: 1px solid var(--border-soft);
  border-radius: var(--radius);
  margin-bottom: 1rem;
}
.toolbar .form-control,
.toolbar .form-select { min-width: 140px; }
.toolbar .toolbar-spacer { flex: 1; }

/* === Footer === */
.app-footer {
  margin-top: 3rem;
  padding: 1.25rem 0 1.5rem;
  border-top: 1px solid var(--border-soft);
  color: var(--text-3);
  font-size: .82rem;
  text-align: center;
}

/* === Login screen === */
.login-shell {
  min-height: 100vh;
  display: flex; align-items: stretch; justify-content: center;
  background:
    radial-gradient(1200px 800px at 85% 20%, oklch(48% 0.13 245 / .25), transparent 55%),
    radial-gradient(900px 700px at 15% 90%, oklch(60% 0.15 45 / .22), transparent 55%),
    linear-gradient(160deg, var(--brand-700) 0%, var(--brand-500) 50%, var(--warm-500) 130%);
  padding: 0;
}
.login-card {
  width: 100%; max-width: 440px;
  margin: auto;
  background: var(--surface-card);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-3);
  overflow: hidden;
  border: 1px solid oklch(100% 0 0 / .15);
}
.login-card .login-head {
  padding: 1.5rem 1.75rem 1rem;
  text-align: center;
  border-bottom: 1px solid var(--border-soft);
  background: linear-gradient(180deg, var(--brand-50) 0%, var(--surface-card) 100%);
}
.login-card .login-mark {
  width: 56px; height: 56px;
  margin: 0 auto .75rem;
  display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, var(--brand-500), var(--brand-700));
  color: white;
  border-radius: 14px;
  font-size: 1.6rem;
  box-shadow: 0 4px 12px oklch(48% 0.13 245 / .30);
}
.login-card .login-head h4 {
  margin: 0; font-size: 1.1rem; font-weight: 700; color: var(--text-1);
}
.login-card .login-head small { color: var(--text-3); font-size: .78rem; }
.login-card .login-body { padding: 1.5rem 1.75rem 1.75rem; }
.login-card .login-foot {
  background: var(--surface-sunken);
  padding: .85rem 1.5rem;
  text-align: center;
  font-size: .76rem; color: var(--text-3);
  border-top: 1px solid var(--border-soft);
}

/* === Misc === */
hr { border-color: var(--border-soft); opacity: 1; }
::selection { background: var(--brand-100); color: var(--brand-700); }
a { text-decoration: none; }
a:hover { text-decoration: underline; text-underline-offset: 2px; }

CSS;
}

function chip_name(array $u): string
{
    $full = trim($u['apellidos_nombres'] ?? '');
    if ($full === '') {
        return $u['username'] ?? '';
    }
    $parts = preg_split('/\s+/', $full);
    if (count($parts) >= 3) {
        return $parts[2] . ' ' . $parts[0];
    }
    return $full;
}

function layout_header(string $titulo): void
{
    $u = current_user();
    $tokens = design_tokens_css();
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
  <style><?= $tokens ?></style>
</head>
<body>
<?php if ($u): ?>
<nav class="navbar navbar-expand-lg app-navbar sticky-top">
  <div class="container">
    <a class="navbar-brand" href="<?= url('dashboard.php') ?>">
      <img src="<?= e(url('assets/logo.png')) ?>" alt="" class="brand-logo">
      <span class="brand-text">
        <span class="name">Papeletas de Salida</span>
      </span>
    </a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="Menu">
      <i class="bi bi-list" style="font-size:1.4rem"></i>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="<?= url('dashboard.php') ?>"><i class="bi bi-house"></i>Inicio</a></li>
        <?php if ($u['rol'] === 'admin'): ?>
        <li class="nav-item"><a class="nav-link" href="<?= url('admin/papeletas.php') ?>"><i class="bi bi-files"></i>Papeletas</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= url('admin/personal.php') ?>"><i class="bi bi-person-vcard"></i>Personal</a></li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav align-items-lg-center">
        <li class="nav-item">
          <a class="btn btn-primary rounded-pill" href="<?= url('papeleta_nueva.php') ?>">
            <i class="bi bi-plus-circle"></i> Nueva papeleta
          </a>
        </li>
        <li class="nav-item ms-2">
          <a class="user-chip" href="<?= url('perfil.php') ?>" title="Mi cuenta">
            <i class="bi bi-gear"></i>
            <span><?= e(chip_name($u)) ?></span>
            <?php if (($u['rol'] ?? '') === 'admin'): ?>
              <span class="badge text-bg-danger" style="font-size:.62rem">admin</span>
            <?php endif; ?>
            <span class="role-dot" title="Sesion activa"></span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?= url('logout.php') ?>" title="Cerrar sesion">
            <i class="bi bi-box-arrow-right"></i>
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>
<?php endif; ?>
<main class="container py-4">
<?php
}

function layout_footer(): void
{
    ?>
</main>
<footer class="app-footer">
  <div class="container">
    <small><?= e(APP_NAME) ?> &middot; <?= date('Y') ?></small>
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
}

function render_flashes(): void
{
    $icons = [
        'ok'     => 'check-circle-fill',
        'success'=> 'check-circle-fill',
        'err'    => 'exclamation-triangle-fill',
        'danger' => 'exclamation-triangle-fill',
        'warn'   => 'exclamation-circle-fill',
        'warning'=> 'exclamation-circle-fill',
        'info'   => 'info-circle-fill',
    ];
    foreach (flash_pop() as $f) {
        $cls = match ($f['type']) {
            'ok'   => 'success',
            'err'  => 'danger',
            'warn' => 'warning',
            default => 'info',
        };
        $icon = $icons[$f['type']] ?? $icons[$cls] ?? 'info-circle-fill';
        echo '<div class="alert alert-' . e($cls) . ' alert-dismissible fade show" role="alert">'
           . '<i class="bi bi-' . e($icon) . '"></i>'
           . '<div>' . e($f['msg']) . '</div>'
           . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>'
           . '</div>';
    }
}
