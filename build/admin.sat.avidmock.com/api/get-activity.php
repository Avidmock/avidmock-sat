<?php
/**
 * admin.sat.avidmock.com / api / get-activity.php
 *
 * GET: Recent platform activity (quiz completions, signups, achievements, AI tutor usage)
 * Query params: ?limit=20&offset=0
 *
 * Response JSON:
 *   { success: true, activities: [ { type, student_name, detail, created_at } ] }
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
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

$limit  = min(50, max(1, (int) ($_GET['limit']  ?? 20)));
$offset = max(0, (int) ($_GET['offset'] ?? 0));

try {
    $activities = [];

    /* ── Recent quiz completions ── */
    try {
        $stmt = $pdo->prepare(
            "SELECT 'quiz_complete' AS type,
                    TRIM(CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))) AS student_name,
                    CONCAT('Scored ', a.score, '% on quiz #', a.quiz_id) AS detail,
                    a.completed_at AS created_at
             FROM sat_quiz_attempts a
             JOIN users u ON u.id = a.user_id
             WHERE a.status = 'completed' AND a.completed_at IS NOT NULL
             ORDER BY a.completed_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$limit, $offset]);
        $activities = array_merge($activities, $stmt->fetchAll());
    } catch (Throwable $e) { error_log('[get-activity] quiz: ' . $e->getMessage()); }

    /* ── Recent signups ── */
    try {
        $stmt = $pdo->prepare(
            "SELECT 'signup' AS type,
                    TRIM(CONCAT(first_name, ' ', COALESCE(last_name, ''))) AS student_name,
                    'New student registered' AS detail,
                    created_at
             FROM users
             WHERE role = 'student'
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$limit, $offset]);
        $activities = array_merge($activities, $stmt->fetchAll());
    } catch (Throwable $e) { error_log('[get-activity] signup: ' . $e->getMessage()); }

    /* ── Recent achievements ── */
    try {
        $stmt = $pdo->prepare(
            "SELECT 'achievement' AS type,
                    TRIM(CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))) AS student_name,
                    CONCAT('Unlocked: ', a.name) AS detail,
                    ua.unlocked_at AS created_at
             FROM user_achievements ua
             JOIN users u ON u.id = ua.user_id
             JOIN achievements a ON a.id = ua.achievement_id
             ORDER BY ua.unlocked_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$limit, $offset]);
        $activities = array_merge($activities, $stmt->fetchAll());
    } catch (Throwable $e) { error_log('[get-activity] achievements: ' . $e->getMessage()); }

    /* ── Recent practice test submissions ── */
    try {
        $stmt = $pdo->prepare(
            "SELECT 'practice_test' AS type,
                    TRIM(CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))) AS student_name,
                    CONCAT('Practice test score: ', pta.total_score) AS detail,
                    pta.submitted_at AS created_at
             FROM practice_test_attempts pta
             JOIN users u ON u.id = pta.user_id
             WHERE pta.status = 'submitted'
             ORDER BY pta.submitted_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$limit, $offset]);
        $activities = array_merge($activities, $stmt->fetchAll());
    } catch (Throwable $e) { error_log('[get-activity] practice_test: ' . $e->getMessage()); }

    /* Sort all activities by date descending, take the limit */
    usort($activities, fn($a, $b) => strtotime($b['created_at'] ?? '0') - strtotime($a['created_at'] ?? '0'));
    $activities = array_slice($activities, 0, $limit);

    echo json_encode(['success' => true, 'activities' => $activities]);

} catch (Throwable $e) {
    error_log('[admin/api/get-activity] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch activity']);
}
