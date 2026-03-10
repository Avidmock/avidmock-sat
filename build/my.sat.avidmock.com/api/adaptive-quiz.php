<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  api/adaptive-quiz.php — Adaptive Quiz REST API
 *
 *  Endpoints:
 *    GET  ?action=next_question[&domain=algebra]  — Get next adaptive question
 *    POST ?action=answer                          — Submit answer, get ability update + next Q
 *         Body: { question_id, answer, time_spent }
 *    GET  ?action=generate[&count=10][&domain=algebra] — Generate full adaptive quiz
 *    GET  ?action=profile                         — Get student's adaptive profile
 *    GET  ?action=session_stats[&since=ISO_DATE]  — Get running session stats
 *    GET  ?action=history[&limit=50]              — Get response history
 *
 *  All responses: { success: bool, data: {...}, [error: string] }
 * ═══════════════════════════════════════════════════════════════════
 */

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/AdaptiveEngine.php';

/* ── Headers ── */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

/* ── CORS for fetch() calls ── */
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Credentials: true');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN');
    http_response_code(204);
    exit;
}

/* ── Auth ── */
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid session']);
    exit;
}

/* ── Route ── */
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    match ($action) {
        'next_question' => handleNextQuestion($userId),
        'answer'        => handleAnswer($userId, $method),
        'generate'      => handleGenerate($userId),
        'profile'       => handleProfile($userId),
        'session_stats' => handleSessionStats($userId),
        'history'       => handleHistory($userId),
        default         => jsonError('Unknown action. Use: next_question, answer, generate, profile, session_stats, history', 400),
    };
} catch (\Throwable $e) {
    error_log('adaptive-quiz.php error: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'An error occurred. Please try again.',
    ]);
}

/* ================================================================
 *  HANDLERS
 * ================================================================ */

function handleNextQuestion(int $userId): void
{
    $domain = isset($_GET['domain']) && $_GET['domain'] !== '' ? $_GET['domain'] : null;

    $question = AdaptiveEngine::getNextQuestion($userId, $domain);

    if (!$question) {
        echo json_encode([
            'success' => false,
            'error'   => 'No questions available for the selected criteria.',
        ]);
        return;
    }

    echo json_encode([
        'success' => true,
        'data'    => $question,
    ], JSON_UNESCAPED_UNICODE);
}

function handleAnswer(int $userId, string $method): void
{
    if ($method !== 'POST') {
        jsonError('POST method required for answering', 405);
        return;
    }

    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $questionId = (int) ($body['question_id'] ?? 0);
    $answer     = strtoupper(trim($body['answer'] ?? ''));
    $timeSpent  = (int) ($body['time_spent'] ?? 0);

    if ($questionId <= 0) {
        jsonError('question_id is required', 400);
        return;
    }
    if (!in_array($answer, ['A', 'B', 'C', 'D'], true)) {
        jsonError('answer must be A, B, C, or D', 400);
        return;
    }
    $timeSpent = max(1, min(600, $timeSpent)); // Clamp 1-600 seconds

    // Check correct answer
    $question = Database::fetch(
        "SELECT correct_answer FROM sat_quiz_questions WHERE id = ? LIMIT 1",
        [$questionId]
    );
    if (!$question) {
        jsonError('Question not found', 404);
        return;
    }

    $isCorrect = strtoupper($answer) === strtoupper($question['correct_answer']);

    // Update ability
    $result = AdaptiveEngine::updateAbility($userId, $questionId, $isCorrect, $timeSpent);

    // Get next question (same domain if possible)
    $domain = $result['domain'] ?? null;
    $nextQuestion = AdaptiveEngine::getNextQuestion($userId, $domain);

    echo json_encode([
        'success'       => true,
        'data'          => [
            'result'        => $result,
            'next_question' => $nextQuestion,
        ],
    ], JSON_UNESCAPED_UNICODE);
}

function handleGenerate(int $userId): void
{
    $count  = max(5, min(30, (int) ($_GET['count'] ?? 10)));
    $domain = isset($_GET['domain']) && $_GET['domain'] !== '' && $_GET['domain'] !== 'all'
        ? $_GET['domain'] : null;

    $quiz = AdaptiveEngine::generateAdaptiveQuiz($userId, $count, $domain);

    echo json_encode([
        'success' => true,
        'data'    => $quiz,
    ], JSON_UNESCAPED_UNICODE);
}

function handleProfile(int $userId): void
{
    $profile = AdaptiveEngine::getStudentProfile($userId);

    echo json_encode([
        'success' => true,
        'data'    => $profile,
    ], JSON_UNESCAPED_UNICODE);
}

function handleSessionStats(int $userId): void
{
    $since = $_GET['since'] ?? null;
    $stats = AdaptiveEngine::getSessionStats($userId, $since);

    echo json_encode([
        'success' => true,
        'data'    => $stats,
    ], JSON_UNESCAPED_UNICODE);
}

function handleHistory(int $userId): void
{
    $limit = max(10, min(200, (int) ($_GET['limit'] ?? 50)));
    $history = AdaptiveEngine::getResponseHistory($userId, $limit);

    echo json_encode([
        'success' => true,
        'data'    => [
            'responses' => $history,
            'count'     => count($history),
        ],
    ], JSON_UNESCAPED_UNICODE);
}

/* ── Error helper ── */
function jsonError(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
}
