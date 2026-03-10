<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  api/predict-score.php — Score Prediction v2 REST API
 *
 *  Endpoints:
 *    GET                     — Full score prediction with CI
 *    GET ?action=history     — Score trend over time
 *    GET ?action=compare     — Percentile comparison
 *    GET ?action=compare&score=1200 — Compare a specific score
 *
 *  All responses: { success: bool, data: {...} }
 * ═══════════════════════════════════════════════════════════════════
 */

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/ScorePredictor.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/AdaptiveEngine.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/ScorePredictor2.php';

/* ── Headers ── */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (isset($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Credentials: true');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, OPTIONS');
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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'GET method required']);
    exit;
}

/* ── Route ── */
$action = $_GET['action'] ?? 'predict';

try {
    match ($action) {
        'predict', '' => handlePredict($userId),
        'history'     => handleHistory($userId),
        'compare'     => handleCompare(),
        default       => handlePredict($userId),
    };
} catch (\Throwable $e) {
    error_log('predict-score.php error: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Unable to generate prediction. Please try again later.',
    ]);
}

/* ================================================================
 *  HANDLERS
 * ================================================================ */

function handlePredict(int $userId): void
{
    $prediction = ScorePredictor2::predict($userId);

    echo json_encode([
        'success' => true,
        'data'    => $prediction,
    ], JSON_UNESCAPED_UNICODE);
}

function handleHistory(int $userId): void
{
    $days = max(7, min(365, (int) ($_GET['days'] ?? 90)));
    $history = ScorePredictor2::getScoreHistory($userId, $days);

    echo json_encode([
        'success' => true,
        'data'    => $history,
    ], JSON_UNESCAPED_UNICODE);
}

function handleCompare(): void
{
    $score = (int) ($_GET['score'] ?? 0);
    if ($score < 400 || $score > 1600) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Score must be between 400 and 1600']);
        return;
    }

    $percentile = ScorePredictor2::compareToPercentile($score);

    echo json_encode([
        'success' => true,
        'data'    => [
            'score'      => $score,
            'percentile' => $percentile,
            'message'    => "A score of {$score} is higher than {$percentile}% of SAT takers.",
        ],
    ], JSON_UNESCAPED_UNICODE);
}
