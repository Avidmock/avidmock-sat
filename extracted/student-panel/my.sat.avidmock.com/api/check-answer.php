<?php
/**
 * api/check-answer.php
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);

ob_start();

$configPath = __DIR__ . '/../config/config.php';
if (!file_exists($configPath)) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['error' => 'config.php not found at: ' . $configPath]);
    exit;
}
require_once $configPath;
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');

$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 0);
if (!$userId) { http_response_code(401); echo json_encode(['error' => 'Not logged in']); exit; }

if (!isset($pdo) || !($pdo instanceof PDO)) { http_response_code(500); echo json_encode(['error' => 'PDO not available']); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'POST required']); exit; }

$rawBody    = file_get_contents('php://input');
$body       = json_decode($rawBody, true) ?? [];
$attemptId  = (int)   ($body['attempt_id']  ?? 0);
$questionId = (int)   ($body['question_id'] ?? 0);
$userAnswer = strtoupper(trim((string) ($body['user_answer'] ?? '')));
$timeSpent  = max(0, (int) ($body['time_spent'] ?? 0));

if (!$attemptId || !$questionId || $userAnswer === '') { http_response_code(400); echo json_encode(['error' => 'Missing required fields']); exit; }

try {
    $stmt = $pdo->prepare("SELECT id, quiz_id, status FROM sat_quiz_attempts WHERE id = :id AND user_id = :uid LIMIT 1");
    $stmt->execute([':id' => $attemptId, ':uid' => $userId]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) { http_response_code(500); echo json_encode(['error' => 'DB error: ' . $e->getMessage()]); exit; }

if (!$attempt) { http_response_code(403); echo json_encode(['error' => 'Attempt not found']); exit; }
if ($attempt['status'] === 'completed') { http_response_code(409); echo json_encode(['error' => 'Attempt already completed']); exit; }

$questionCols = $pdo->query("SHOW COLUMNS FROM sat_quiz_questions")->fetchAll(PDO::FETCH_COLUMN);
$hasExpHtml   = in_array('explanation_html',  $questionCols);
$hasExpImage  = in_array('explanation_image', $questionCols);

$selectExtras = '';
if ($hasExpHtml)  $selectExtras .= ', explanation_html';
if ($hasExpImage) $selectExtras .= ', explanation_image';

try {
    $stmt = $pdo->prepare("SELECT id, type, correct_answer, explanation{$selectExtras} FROM sat_quiz_questions WHERE id = :id AND quiz_id = :qid LIMIT 1");
    $stmt->execute([':id' => $questionId, ':qid' => $attempt['quiz_id']]);
    $question = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) { http_response_code(500); echo json_encode(['error' => 'DB error: ' . $e->getMessage()]); exit; }

if (!$question) { http_response_code(404); echo json_encode(['error' => 'Question not found']); exit; }

$correctAnswer = strtoupper(trim((string) ($question['correct_answer'] ?? '')));
$isGridIn      = ($question['type'] === 'grid_in');
$isCorrect     = $isGridIn ? gradeGridIn($userAnswer, $correctAnswer) : ($userAnswer === $correctAnswer);

/* ── Parse explanation_image into a proper array ── */
$rawImg = $hasExpImage ? trim($question['explanation_image'] ?? '') : '';
if ($rawImg && $rawImg[0] === '[') {
    $explanationImages = json_decode($rawImg, true) ?? [];
} elseif ($rawImg) {
    $explanationImages = [$rawImg];
} else {
    $explanationImages = [];
}

try {
    $existing = $pdo->prepare("SELECT id FROM quiz_attempt_answers WHERE attempt_id = :aid AND question_id = :qid LIMIT 1");
    $existing->execute([':aid' => $attemptId, ':qid' => $questionId]);
    $alreadyAnswered = (bool) $existing->fetch();
} catch (Throwable $e) { http_response_code(500); echo json_encode(['error' => 'DB error: ' . $e->getMessage()]); exit; }

if (!$alreadyAnswered) {
    try {
        $pdo->prepare("INSERT INTO quiz_attempt_answers (attempt_id, question_id, given_answer, is_correct, time_spent, answered_at) VALUES (:attempt_id, :question_id, :given_answer, :is_correct, :time_spent, NOW())")->execute([':attempt_id'=>$attemptId,':question_id'=>$questionId,':given_answer'=>$userAnswer,':is_correct'=>$isCorrect?1:0,':time_spent'=>$timeSpent]);
    } catch (Throwable $e) { http_response_code(500); echo json_encode(['error' => 'DB error: ' . $e->getMessage()]); exit; }
    try {
        if ($isCorrect) {
            $pdo->prepare("UPDATE sat_quiz_attempts SET correct = correct + 1 WHERE id = :id")->execute([':id'=>$attemptId]);
        } else {
            $pdo->prepare("UPDATE sat_quiz_attempts SET incorrect = incorrect + 1 WHERE id = :id")->execute([':id'=>$attemptId]);
        }
    } catch (Throwable $e) { http_response_code(500); echo json_encode(['error' => 'DB error: ' . $e->getMessage()]); exit; }
}

echo json_encode([
    'is_correct'         => $isCorrect,
    'correct_answer'     => $correctAnswer,
    'explanation'        => $question['explanation']      ?? '',
    'explanation_html'   => $hasExpHtml ? ($question['explanation_html'] ?? '') : '',
    'explanation_image'  => $rawImg,
    'explanation_images' => $explanationImages,
    'xp_delta'           => $isCorrect ? 5 : 0,
], JSON_HEX_TAG | JSON_HEX_AMP);
exit;

function gradeGridIn(string $user, string $correct): bool {
    $u = parseGridIn($user); $c = parseGridIn($correct);
    if ($u === null || $c === null) return strtoupper(trim($user)) === strtoupper(trim($correct));
    return abs($u - $c) < 0.0001;
}
function parseGridIn(string $val): ?float {
    $val = trim($val);
    if (preg_match('#^(-?\d+)\s*/\s*(-?\d+)$#', $val, $m)) { if ((int)$m[2]===0) return null; return (float)$m[1]/(float)$m[2]; }
    return is_numeric($val) ? (float)$val : null;
}