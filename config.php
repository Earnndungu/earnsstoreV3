<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('earns_store_session');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true
    ]);
}
$DB_HOST = getenv('EARN_DB_HOST') ?: '127.0.0.1';
$DB_NAME = getenv('EARN_DB_NAME') ?: 'earn_ecommerce';
$DB_USER = getenv('EARN_DB_USER') ?: 'root';
$DB_PASS = getenv('EARN_DB_PASS') ?: '';
try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed. Import database/earn_ecommerce.sql and check config.php settings.');
}
function json_out($data, int $status=200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function require_admin(): void {
    if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') json_out(['error'=>'Administrator sign-in required.'],401);
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verify_csrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$token || !hash_equals(csrf_token(), $token)) json_out(['error'=>'Security token expired. Refresh the page and try again.'],419);
}
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
