<?php
/**
 * admin.sat.avidmock.com / api / student-search.php
 *
 * GET: ?q=search_term&page=1&per_page=20
 * Searches students by name or email, returns paginated results with stats
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_name('avidmock_admin_session');
    session_set_cookie_params([
        'lifetime' => 86400 * 30, 'path' => '/', 'domain' => 'admin.sat.avidmock.com',
        'secure' => true, 'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$configPaths = [__DIR__ . '/../config/config.php', __DIR__ . '/../config/db.php'];
foreach ($configPaths as $p) { if (file_exists($p)) { require_once $p; break; } }
if (!isset($pdo) && isset($db)) $pdo = $db;
if (!isset($pdo)) { http_response_code(500); echo json_encode(['success' => false, 'error' => 'Database not configured']); exit; }

$adminId   = $_SESSION['admin_id']   ?? null;
$adminRole = $_SESSION['admin_role'] ?? null;
if (!$adminId || !in_array($adminRole, ['admin', 'teacher'], true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'GET required']);
    exit;
}

$query   = trim($_GET['q'] ?? '');
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = min(50, max(5, (int) ($_GET['per_page'] ?? 20)));
$offset  = ($page - 1) * $perPage;

try {
    if ($query === '') {
        /* No search query — return all students paginated */
        $countStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'");
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT u.id, u.first_name, u.last_name, u.email, u.avatar_url,
                    u.created_at, u.last_login_at, u.is_active, u.target_score, u.test_date,
                    COALESCE(ss.current_streak, 0) AS current_streak,
                    COALESCE(ux.xp, 0) AS total_xp,
                    COALESCE(ux.level, 1) AS level
             FROM users u
             LEFT JOIN study_streaks ss ON ss.user_id = u.id
             LEFT JOIN user_xp ux ON ux.user_id = u.id
             WHERE u.role = 'student'
             ORDER BY u.last_login_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$perPage, $offset]);
    } else {
        /* Search by name or email */
        $searchTerm = '%' . $query . '%';

        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM users
             WHERE role = 'student'
               AND (CONCAT(first_name, ' ', COALESCE(last_name, '')) LIKE ? OR email LIKE ?)"
        );
        $countStmt->execute([$searchTerm, $searchTerm]);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT u.id, u.first_name, u.last_name, u.email, u.avatar_url,
                    u.created_at, u.last_login_at, u.is_active, u.target_score, u.test_date,
                    COALESCE(ss.current_streak, 0) AS current_streak,
                    COALESCE(ux.xp, 0) AS total_xp,
                    COALESCE(ux.level, 1) AS level
             FROM users u
             LEFT JOIN study_streaks ss ON ss.user_id = u.id
             LEFT JOIN user_xp ux ON ux.user_id = u.id
             WHERE u.role = 'student'
               AND (CONCAT(u.first_name, ' ', COALESCE(u.last_name, '')) LIKE ? OR u.email LIKE ?)
             ORDER BY u.last_login_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$searchTerm, $searchTerm, $perPage, $offset]);
    }

    $students = $stmt->fetchAll();

    /* Clean up display names */
    foreach ($students as &$s) {
        $s['name'] = trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? ''));
        $s['id']   = (int) $s['id'];
        $s['current_streak'] = (int) $s['current_streak'];
        $s['total_xp'] = (int) $s['total_xp'];
        $s['level']    = (int) $s['level'];
        unset($s['first_name'], $s['last_name']);
    }
    unset($s);

    echo json_encode([
        'success'      => true,
        'students'     => $students,
        'total'        => $total,
        'page'         => $page,
        'per_page'     => $perPage,
        'last_page'    => max(1, (int) ceil($total / $perPage)),
        'query'        => $query,
    ]);

} catch (Throwable $e) {
    error_log('[admin/api/student-search] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Search failed']);
}
