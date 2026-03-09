<?php
// ─── DATABASE ──────────────────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_PORT',    '3306');
define('DB_NAME',    'dbw37mqkzieajt');
define('DB_USER',    'uofkcmd0rmvn2');
define('DB_PASS',    '4(%l1,521)l1');
define('DB_CHARSET', 'utf8mb4');

// ─── ENVIRONMENT ───────────────────────────────────────────────────
define('AVIDMOCK_ENV',   getenv('AVIDMOCK_ENV') ?: 'production');
define('AVIDMOCK_DEBUG', AVIDMOCK_ENV === 'development');

// ─── TIMEZONE ──────────────────────────────────────────────────────
date_default_timezone_set('UTC');

// ─── ERROR HANDLING ────────────────────────────────────────────────
if (AVIDMOCK_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// ─── DATABASE CONNECTION ───────────────────────────────────────────
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// ─── HELPERS ───────────────────────────────────────────────────────
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function redirect(string $url): void {
    header("Location: {$url}");
    exit;
}

function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}