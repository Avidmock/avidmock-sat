<?php
/**
 * api/get-weakness-analysis.php
 *
 * REST endpoint for the AI Weakness Analyzer.
 *
 *   GET  — Returns cached analysis (or generates fresh if none exists)
 *   POST { action: "refresh" } — Forces a fresh analysis
 *
 * Response JSON:
 *   {
 *     success: true,
 *     data: {
 *       weak_skills: [...],
 *       strong_skills: [...],
 *       ai_analysis: { coaching_paragraph, action_plan, skill_tips },
 *       recommended_quizzes: [...],
 *       predicted_score_impact: { points, description },
 *       domain_scores: { algebra: {...}, ... },
 *       readiness_score: 72,
 *       generated_at: "2026-03-09 14:30:00",
 *       total_answers: 184
 *     }
 *   }
 */

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/WeaknessAnalyzer.php';

/* ── Headers ── */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

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

/* ── Method handling ── */
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET' && $method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $forceRefresh = false;

    if ($method === 'POST') {
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $body['action'] ?? ($_POST['action'] ?? '');

        if ($action === 'refresh') {
            $forceRefresh = true;
        }
    }

    $data = WeaknessAnalyzer::analyse($userId, $forceRefresh);

    echo json_encode([
        'success'      => true,
        'data'         => $data,
        'generated_at' => $data['generated_at'] ?? date('Y-m-d H:i:s'),
        'cached'       => !empty($data['cached']),
    ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    error_log('get-weakness-analysis.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Unable to generate weakness analysis. Please try again later.',
    ]);
}