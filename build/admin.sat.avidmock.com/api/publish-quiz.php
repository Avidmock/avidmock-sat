<?php
/**
 * admin.sat.avidmock.com / api / publish-quiz.php
 *
 * POST JSON: { quiz_id: int }
 * Publishes a draft quiz (sets status to 'published', records published_at)
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

$adminId   = $_SESSION['admin_id']   ?? null;
$adminRole = $_SESSION['admin_role'] ?? null;
if (!$adminId || !in_array($adminRole, ['admin', 'teacher'], true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
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
    /* Verify quiz exists and has questions */
    $quiz = $pdo->prepare("SELECT id, status, title FROM sat_quizzes WHERE id = ?");
    $quiz->execute([$quizId]);
    $quiz = $quiz->fetch();

    if (!$quiz) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Quiz not found']);
        exit;
    }

    if ($quiz['status'] === 'published') {
        echo json_encode(['success' => true, 'message' => 'Quiz is already published', 'quiz_id' => $quizId]);
        exit;
    }

    /* Check question count */
    $qCount = (int) $pdo->prepare("SELECT COUNT(*) FROM sat_quiz_questions WHERE quiz_id = ?");
    $qCount->execute([$quizId]);
    $qCount = (int) $qCount->fetchColumn();

    if ($qCount === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Cannot publish a quiz with no questions']);
        exit;
    }

    /* Publish */
    $stmt = $pdo->prepare(
        "UPDATE sat_quizzes SET status = 'published', published_at = NOW(), updated_at = NOW() WHERE id = ?"
    );
    $stmt->execute([$quizId]);

    echo json_encode([
        'success'        => true,
        'quiz_id'        => $quizId,
        'title'          => $quiz['title'],
        'question_count' => $qCount,
        'status'         => 'published',
    ]);

} catch (Throwable $e) {
    error_log('[admin/api/publish-quiz] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to publish quiz']);
}
