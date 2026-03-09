<?php
require_once __DIR__ . '/../config/config.php';

/* ── Shared session bootstrap ────────────────────────────────────────────
 *
 *  Every file that needs the admin session (including API endpoints like
 *  upload-explanation-image.php) MUST call this function — or simply
 *  require this file — before touching $_SESSION.
 *
 *  Never call session_start() anywhere else without going through here.
 * ─────────────────────────────────────────────────────────────────────── */
function avidmock_session_start(): void {
    if (session_status() !== PHP_SESSION_NONE) {
        return; // already started — nothing to do
    }
    session_name('avidmock_admin_session');
    session_set_cookie_params([
        'lifetime' => 86400 * 30,
        'path'     => '/',
        'domain'   => 'admin.sat.avidmock.com',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Start the session immediately when this file is included
avidmock_session_start();

$isLoggedIn = isset($_SESSION['admin_id'], $_SESSION['admin_role']);
$validRoles = ['admin', 'teacher'];
$hasRole    = $isLoggedIn && in_array($_SESSION['admin_role'], $validRoles, true);

if (!$hasRole) {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    if ($requestUri && strpos($requestUri, '/auth/login.php') === false) {
        $_SESSION['admin_redirect_after_login'] = $requestUri;
    }
    header('Location: /auth/login.php');
    exit;
}

$timeout = 4 * 60 * 60;
if (isset($_SESSION['admin_last_activity'])) {
    if (time() - $_SESSION['admin_last_activity'] > $timeout) {
        session_unset();
        session_destroy();
        header('Location: /auth/login.php?reason=timeout');
        exit;
    }
}
$_SESSION['admin_last_activity'] = time();

function currentAdmin(): array {
    return [
        'id'    => (int) ($_SESSION['admin_id']    ?? 0),
        'name'  =>       ($_SESSION['admin_name']  ?? 'Admin'),
        'email' =>       ($_SESSION['admin_email'] ?? ''),
        'role'  =>       ($_SESSION['admin_role']  ?? 'teacher'),
    ];
}

function isRole(string $role): bool {
    return ($_SESSION['admin_role'] ?? '') === $role;
}