<?php
/**
 * api/submit-review.php  [NEW — v4]
 * POST: Submit a spaced repetition answer → update SM-2 ease factor + next review date
 *
 * SM-2 quality scale (q):
 *   5 = perfect recall
 *   4 = correct, minor hesitation
 *   3 = correct, significant difficulty
 *   2 = incorrect, correct answer was easy to recall
 *   1 = incorrect, correct answer was hard to recall
 *   0 = complete blackout
 *
 * Request JSON:
 *   {
 *     queue_id: int,
 *     question_id: int,
 *     user_answer: string,
 *     quality: int (0-5),
 *     time_spent: int (seconds)
 *   }
 *
 * Response JSON:
 *   {
 *     success: bool,
 *     is_correct: bool,
 *     correct_answer: string,
 *     explanation: string,
 *     next_review_date: string,
 *     interval_days: int,
 *     ease_factor: float,
 *     items_remaining: int
 *   }
 */

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/SpacedRepetition.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/AnswerChecker.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/XPSystem.php';

/* ── Headers ── */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

/* ── Auth ── */
Auth::requireLogin();
$userId = (int) $_SESSION['user_id'];

/* ── Method guard ── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

/* ── AJAX guard ── */
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

/* ── Parse body ── */
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

/* ── Validate ── */
$queueId    = isset($body['queue_id'])    ? (int)    $body['queue_id']    : 0;
$questionId = isset($body['question_id']) ? (int)    $body['question_id'] : 0;
$userAnswer = isset($body['user_answer']) ? trim((string) $body['user_answer']) : '';
$quality    = isset($body['quality'])     ? (int)    $body['quality']     : -1;
$timeSpent  = isset($body['time_spent'])  ? (int)    $body['time_spent']  : 0;

if ($queueId <= 0 || $questionId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'queue_id and question_id required']);
    exit;
}

if ($quality < 0 || $quality > 5) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'quality must be 0–5']);
    exit;
}

/* ── Verify queue item belongs to this user ── */
$queueItem = SpacedRepetition::getQueueItem($queueId);
if (!$queueItem || (int) $queueItem['user_id'] !== $userId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Queue item not found or access denied']);
    exit;
}

/* ── Check answer for authoritative is_correct ── */
try {
    $checkResult = AnswerChecker::check($questionId, $userAnswer);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Answer check failed']);
    exit;
}

$isCorrect = (bool) $checkResult['is_correct'];

/* ── Override quality with answer result if client sent mismatch ── */
// If user got it wrong but sent quality >= 3, cap it at 2
if (!$isCorrect && $quality >= 3) {
    $quality = 2;
}
// If user got it right but sent quality <= 1, floor it at 3
if ($isCorrect && $quality <= 1) {
    $quality = 3;
}

/* ── Run SM-2 algorithm and update queue ── */
try {
    $sm2Result = SpacedRepetition::recordAnswer($queueId, $userId, $quality, $timeSpent);
} catch (Throwable $e) {
    error_log('SpacedRepetition::recordAnswer failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update spaced repetition record']);
    exit;
}

/* ── Award XP for spaced review ── */
try {
    $xp = $isCorrect ? 20 : 5;
    XPSystem::award($userId, 'spaced_review', $xp);
} catch (Throwable $e) {
    error_log('XP award (spaced review) failed: ' . $e->getMessage());
}

/* ── Count remaining items due today ── */
$remaining = 0;
try {
    $remaining = SpacedRepetition::countDue($userId);
} catch (Throwable $e) {
    error_log('countDue failed: ' . $e->getMessage());
}

/* ── Response ── */
echo json_encode([
    'success'          => true,
    'is_correct'       => $isCorrect,
    'correct_answer'   => (string) ($checkResult['correct_answer'] ?? ''),
    'explanation'      => (string) ($checkResult['explanation']    ?? ''),
    'next_review_date' => $sm2Result['next_review_date'] ?? date('Y-m-d', strtotime('+1 day')),
    'interval_days'    => (int)   ($sm2Result['interval_days']    ?? 1),
    'ease_factor'      => round((float) ($sm2Result['ease_factor'] ?? 2.5), 2),
    'quality_used'     => $quality,
    'items_remaining'  => $remaining,
    'xp_earned'        => $isCorrect ? 20 : 5,
]);