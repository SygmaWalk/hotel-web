<?php
declare(strict_types=1);
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Hotel.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');
ini_set('display_errors', '0');
set_exception_handler(function (Throwable $error): void {
    error_log('Hotel: ' . get_class($error) . ' código ' . $error->getCode());
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><title>Servicio temporalmente no disponible</title><h1>No pudimos completar la operación</h1><p>Intentá nuevamente en unos minutos. Si acabás de enviar un formulario, reintentá con el mismo formulario.</p></html>';
});
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
header('Cache-Control: no-store');
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Método no permitido.');
}
session_name('hotel_session');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax', 'path' => '/']);
if (!session_start()) throw new RuntimeException('No se pudo iniciar la sesión.');
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $page): string { return rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/' . $page; }
function redirect(string $page): never { header('Location: ' . url($page), true, 303); exit; }
function csrfInput(): void { echo '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">'; }
function fail(int $code, string $message): never { http_response_code($code); pageStart('No se pudo continuar'); echo '<p class="notice error">' . e($message) . '</p>'; pageEnd(); exit; }
function flash(string $message): void { $_SESSION['flash'] = $message; }
function statusLabel(string $status): string { return ['pending'=>'Pendiente','confirmed'=>'Confirmada','rejected'=>'Rechazada'][$status] ?? $status; }
function currentUser(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $statement = db()->prepare('SELECT id, email, role FROM users WHERE id = ?');
    $statement->execute([$_SESSION['user_id']]);
    return $statement->fetch() ?: null;
}
function requireUser(bool $admin = false): array {
    $user = currentUser();
    if (!$user) redirect('login.php');
    if ($admin && $user['role'] !== 'admin') fail(403, 'Esta sección requiere permisos de administrador.');
    return $user;
}
require_once dirname(__DIR__) . '/views/layout.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf'], field($_POST, 'csrf'))) {
    fail(403, 'El formulario venció o no es válido. Volvé a abrir la página.');
}
function hotel(): Hotel { static $hotel; return $hotel ??= new Hotel(db()); }
