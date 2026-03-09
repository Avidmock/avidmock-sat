<?php
/**
 * auth/logout.php
 * admin.sat.avidmock.com/auth/logout.php
 *
 * Destroys the admin session and redirects to login.
 * Linked from the sidebar footer logout button.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear all session data
session_unset();
session_destroy();

// Expire the session cookie immediately
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Redirect to login with a goodbye message
header('Location: /auth/login.php?logout=1');
exit;