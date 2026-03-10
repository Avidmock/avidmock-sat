<?php
/**
 * api/essay-scorer.php — AI R&W Scoring Endpoint
 * POST: Score a student's R&W response
 * GET ?action=patterns: Get writing pattern analysis
 * GET ?action=prompt: Generate a practice prompt
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/AIEssayScorer.php';

Auth::requireStudent();
$userId = (int) $_SESSION['user_id'];

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? 'score';

switch ($action) {

    case 'score':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'POST required']);
            exit;
        }

        $input   = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $passage  = trim($input['passage']  ?? '');
        $question = trim($input['question'] ?? '');
        $response = trim($input['response'] ?? '');
        $type     = $input['type']           ?? 'grammar';

        if (!$response) {
            echo json_encode(['error' => 'Response text is required']);
            exit;
        }

        // Rate limit
        $tier = current_tier();
        $limit = $tier === TIER_FREE ? 3 : 999;
        if (!RateLimit::check("rw_scorer:{$userId}", $limit)) {
            echo json_encode(['error' => 'Daily scoring limit reached. Upgrade to Pro for unlimited.']);
            exit;
        }

        $result = AIEssayScorer::score($userId, $passage, $question, $response, $type);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        break;

    case 'patterns':
        echo json_encode(AIEssayScorer::getPatternAnalysis($userId));
        break;

    case 'prompt':
        $type       = $_GET['type']       ?? 'grammar';
        $skill      = $_GET['skill']      ?? 'punctuation';
        $difficulty = $_GET['difficulty']  ?? 'medium';
        $prompt = AIEssayScorer::generatePrompt($type, $skill, $difficulty);
        echo json_encode($prompt ?? ['error' => 'Failed to generate prompt']);
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
}
