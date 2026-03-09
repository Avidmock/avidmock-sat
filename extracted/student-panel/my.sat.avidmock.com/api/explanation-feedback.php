<?php
/**
 * my.sat.avidmock.com / api / explanation-feedback.php
 *
 * Records thumbs up/down feedback on an explanation.
 *
 * POST JSON:
 *   question_id    int
 *   feedback_type  string  "up" | "down"
 *
 * Response JSON:
 *   { success: true }  |  { success: false, error: "..." }
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json; charset=utf-8');

/* ── DB ──────────────────────────────────────────────────────────────────── */
$configPaths = [
    __DIR__ . '/../config/config.php',
    __DIR__ . '/../config/db.php',
    __DIR__ . '/../includes/db.php',
    __DIR__ . '/../init.php',
];
foreach ($configPaths as $p) {
    if (file_exists($p)) { require_once $p; break; }
}
if (!isset($pdo) && isset($db))   $pdo = $db;
if (!isset($pdo) && isset($conn)) $pdo = $conn;

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database not configured']);
    exit;
}

/* ── Auth ────────────────────────────────────────────────────────────────── */
$userId = $_SESSION['user_id'] ?? $_SESSION['student_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}
$userId = (int)$userId;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

/* ── Parse ───────────────────────────────────────────────────────────────── */
$body         = json_decode(file_get_contents('php://input'), true);
$questionId   = (int)($body['question_id']   ?? 0);
$feedbackType = trim($body['feedback_type'] ?? '');

if (!$questionId || !in_array($feedbackType, ['up', 'down'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

/* ── Create table if needed ──────────────────────────────────────────────── */
$pdo->exec("
    CREATE TABLE IF NOT EXISTS explanation_feedback (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id       INT UNSIGNED NOT NULL,
        question_id   INT UNSIGNED NOT NULL,
        feedback_type ENUM('up','down') NOT NULL,
        created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user_question (user_id, question_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/* ── Upsert (allow changing vote) ────────────────────────────────────────── */
$pdo->prepare("
    INSERT INTO explanation_feedback (user_id, question_id, feedback_type)
    VALUES (:uid, :qid, :type)
    ON DUPLICATE KEY UPDATE
        feedback_type = VALUES(feedback_type),
        created_at    = NOW()
")->execute([
    ':uid'  => $userId,
    ':qid'  => $questionId,
    ':type' => $feedbackType,
]);

echo json_encode(['success' => true]);