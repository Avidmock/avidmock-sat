<?php
/**
 * api/save-quiz-progress.php
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

$userId = (int)($_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 0);
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

$body         = json_decode(file_get_contents('php://input'), true) ?? [];
$attemptId    = (int)($body['attempt_id']       ?? 0);
$quizId       = (int)($body['quiz_id']          ?? 0);
$currentQ     = (int)($body['current_question'] ?? 0);
$progressData = $body['progress_data']          ?? [];
$flagged      = $body['flagged_questions']       ?? ($progressData['flagged'] ?? []);

if (!$attemptId || !$quizId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing attempt_id or quiz_id']);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM sat_quiz_attempts WHERE id=:id AND user_id=:uid AND quiz_id=:qid LIMIT 1");
$stmt->execute([':id' => $attemptId, ':uid' => $userId, ':qid' => $quizId]);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Attempt not found']);
    exit;
}

$attemptCols = $pdo->query("SHOW COLUMNS FROM sat_quiz_attempts")->fetchAll(PDO::FETCH_COLUMN);
$setClauses  = [];
$params      = [];

if (in_array('progress_json', $attemptCols)) { $setClauses[] = 'progress_json = :pj'; $params[':pj'] = json_encode((object)$progressData); }
if (in_array('flagged_json',  $attemptCols)) { $setClauses[] = 'flagged_json = :fj';  $params[':fj'] = json_encode(array_values((array)$flagged)); }
if (in_array('current_q',    $attemptCols)) { $setClauses[] = 'current_q = :cq';     $params[':cq'] = $currentQ + 1; }
if (in_array('updated_at',   $attemptCols)) { $setClauses[] = 'updated_at = NOW()'; }

if (!empty($setClauses)) {
    $params[':id'] = $attemptId;
    $pdo->prepare("UPDATE sat_quiz_attempts SET " . implode(', ', $setClauses) . " WHERE id=:id")->execute($params);
}

try {
    $tables = $pdo->query("SHOW TABLES LIKE 'quiz_progress'")->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($tables)) {
        $qpCols = $pdo->query("SHOW COLUMNS FROM quiz_progress")->fetchAll(PDO::FETCH_COLUMN);
        $ic  = ['user_id', 'quiz_id', 'attempt_id', 'current_question'];
        $iv  = [':uid', ':qid', ':aid', ':cq'];
        $up  = ['current_question = VALUES(current_question)', 'updated_at = NOW()'];
        $qp  = [':uid' => $userId, ':qid' => $quizId, ':aid' => $attemptId, ':cq' => $currentQ];
        if (in_array('flagged_questions', $qpCols)) { $ic[] = 'flagged_questions'; $iv[] = ':fq'; $up[] = 'flagged_questions = VALUES(flagged_questions)'; $qp[':fq'] = json_encode(array_values((array)$flagged)); }
        if (in_array('progress_data',    $qpCols)) { $ic[] = 'progress_data';     $iv[] = ':pd'; $up[] = 'progress_data = VALUES(progress_data)';         $qp[':pd'] = json_encode((object)$progressData); }
        $pdo->prepare(sprintf("INSERT INTO quiz_progress (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s", implode(',', $ic), implode(',', $iv), implode(',', $up)))->execute($qp);
    }
} catch (Throwable) {}

echo json_encode(['success' => true]);