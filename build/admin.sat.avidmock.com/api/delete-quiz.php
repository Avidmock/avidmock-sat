<?php
/**
 * admin.sat.avidmock.com / api / delete-quiz.php
 *
 * POST JSON: { quiz_id: int }
 * Soft-deletes a quiz (sets status to 'deleted', preserves data for analytics)
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

$configPaths = [__DIR__ . '/../config/config.php', __DIR__ . '/../config/db.php'];
foreach ($configPaths as $p) { if (file_exists($p)) { require_once $p; break; } }
if (!isset($pdo) && isset($db)) $pdo = $db;
if (!isset($pdo)) { http_response_code(500); echo json_encode(['success' => false, 'error' => 'Database not configured']); exit; }

/* Admin-only (teachers cannot delete) */
$adminId   = $_SESSION['admin_id']   ?? null;
$adminRole = $_SESSION['admin_role'] ?? null;
if (!$adminId || $adminRole !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$quizId = (int) ($body['quiz_id'] ?? 0);

if (!$quizId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing quiz_id']);
    exit;
}

try {
    $quiz = $pdo->prepare("SELECT id, title, status FROM sat_quizzes WHERE id = ?");
    $quiz->execute([$quizId]);
    $quiz = $quiz->fetch();

    if (!$quiz) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Quiz not found']);
        exit;
    }

    if ($quiz['status'] === 'deleted') {
        echo json_encode(['success' => true, 'message' => 'Quiz already deleted']);
        exit;
    }

    /* Check if any students have attempts */
    $attemptCount = $pdo->prepare("SELECT COUNT(*) FROM sat_quiz_attempts WHERE quiz_id = ?");
    $attemptCount->execute([$quizId]);
    $attemptCount = (int) $attemptCount->fetchColumn();

    /* Soft delete — preserve data */
    $stmt = $pdo->prepare(
        "UPDATE sat_quizzes SET status = 'deleted', updated_at = NOW() WHERE id = ?"
    );
    $stmt->execute([$quizId]);

    echo json_encode([
        'success'       => true,
        'quiz_id'       => $quizId,
        'title'         => $quiz['title'],
        'had_attempts'  => $attemptCount > 0,
        'attempt_count' => $attemptCount,
        'message'       => $attemptCount > 0
            ? "Quiz soft-deleted. {$attemptCount} student attempt(s) preserved for analytics."
            : 'Quiz deleted successfully.',
    ]);

} catch (Throwable $e) {
    error_log('[admin/api/delete-quiz] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to delete quiz']);
}
