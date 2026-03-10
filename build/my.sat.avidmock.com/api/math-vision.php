<?php
/**
 * Avidmock SAT Math Scanner — Math Vision API
 *
 * POST /api/math-vision.php
 *
 * Accepts:
 *   - image: base64 encoded image of math problem
 *   - text: typed/pasted math problem text
 *   - source: "extension" | "notebook" | "web"
 *
 * Returns JSON with solution, SAT mapping, difficulty, strategy tip, and XP.
 */

declare(strict_types=1);

// CORS headers for Chrome Extension
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Accept');
header('Access-Control-Max-Age: 86400');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

// ---- Bootstrap ----

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/MathVision.php';

// ---- Authentication ----

$auth = new Auth();
$user = $auth->authenticate();

if (!$user) {
    http_response_code(401);
    echo json_encode([
        'error' => 'Authentication required. Please sign in at my.sat.avidmock.com.',
        'login_url' => 'https://my.sat.avidmock.com/login?ref=extension',
    ]);
    exit;
}

$userId = (int) $user['id'];
$userTier = $user['tier'] ?? 'free'; // 'free' or 'pro'

// ---- Rate Limiting ----

$db = Database::getInstance();

$today = date('Y-m-d');
$scanCountStmt = $db->prepare(
    'SELECT COUNT(*) as count FROM math_scans WHERE user_id = ? AND DATE(created_at) = ?'
);
$scanCountStmt->execute([$userId, $today]);
$scansToday = (int) $scanCountStmt->fetchColumn();

$dailyLimit = ($userTier === 'pro') ? PHP_INT_MAX : 5;

if ($scansToday >= $dailyLimit) {
    http_response_code(429);
    echo json_encode([
        'error' => 'Daily scan limit reached. Upgrade to Pro for unlimited scans.',
        'scans_today' => $scansToday,
        'limit' => ($userTier === 'pro') ? 'unlimited' : 5,
        'upgrade_url' => 'https://my.sat.avidmock.com/pricing',
    ]);
    exit;
}

// ---- Parse Input ----

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (strpos($contentType, 'application/json') !== false) {
    $rawBody = file_get_contents('php://input');
    $input = json_decode($rawBody, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON body.']);
        exit;
    }
} elseif (strpos($contentType, 'multipart/form-data') !== false) {
    $input = $_POST;
    // Handle file upload for image
    if (isset($_FILES['image'])) {
        $imageData = file_get_contents($_FILES['image']['tmp_name']);
        $input['image'] = base64_encode($imageData);
    }
} else {
    // Try JSON anyway
    $rawBody = file_get_contents('php://input');
    $input = json_decode($rawBody, true) ?: [];
}

$imageBase64 = $input['image'] ?? null;
$problemText = $input['text'] ?? null;
$source = $input['source'] ?? 'web';

// Validate that we have at least one input
if (empty($imageBase64) && empty($problemText)) {
    http_response_code(400);
    echo json_encode(['error' => 'Provide either an image (base64) or text of a math problem.']);
    exit;
}

// Validate source
$allowedSources = ['extension', 'notebook', 'web'];
if (!in_array($source, $allowedSources, true)) {
    $source = 'web';
}

// ---- Process with AI ----

$mathVision = new MathVision();

try {
    if (!empty($imageBase64)) {
        // Validate base64 image (basic check)
        $decoded = base64_decode($imageBase64, true);
        if ($decoded === false || strlen($decoded) < 100) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid image data.']);
            exit;
        }

        // Limit image size (10MB max)
        if (strlen($decoded) > 10 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(['error' => 'Image too large. Maximum size is 10MB.']);
            exit;
        }

        $analysis = $mathVision->analyzeImage($imageBase64);
    } else {
        // Validate text length
        if (strlen($problemText) > 5000) {
            http_response_code(400);
            echo json_encode(['error' => 'Problem text too long. Maximum 5000 characters.']);
            exit;
        }

        $analysis = $mathVision->analyzeText($problemText);
    }
} catch (\Exception $e) {
    error_log('MathVision API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'AI analysis failed. Please try again.',
        'detail' => (defined('APP_DEBUG') && APP_DEBUG) ? $e->getMessage() : null,
    ]);
    exit;
}

// ---- Find Similar Quizzes ----

$domainKey = $analysis['sat_mapping']['domain_key'] ?? 'algebra';
$skillKey = $analysis['sat_mapping']['skill_key'] ?? '';
$similarQuizzes = $mathVision->findSimilarQuizzes($domainKey, $skillKey);

// ---- Award XP ----

$xpEarned = 10;

// Bonus XP for harder problems
$difficulty = $analysis['difficulty'] ?? 'medium';
if ($difficulty === 'hard') {
    $xpEarned = 20;
} elseif ($difficulty === 'easy') {
    $xpEarned = 5;
}

// Award XP to user
$xpStmt = $db->prepare('UPDATE users SET xp = xp + ? WHERE id = ?');
$xpStmt->execute([$xpEarned, $userId]);

// Update streak
$mathVision->updateStreak($userId);

// ---- Log the Scan ----

$scanType = !empty($imageBase64) ? 'image' : 'text';
$mathVision->logScan($userId, $scanType, $analysis, $source);

// ---- Build Response ----

$response = [
    'problem' => $analysis['problem'] ?? '',
    'solution' => [
        'answer' => $analysis['answer'] ?? '',
        'steps' => $analysis['steps'] ?? [],
        'steps_latex' => $analysis['steps_latex'] ?? [],
    ],
    'sat_mapping' => $analysis['sat_mapping'] ?? [
        'domain' => 'Algebra',
        'skill' => 'General',
        'domain_key' => 'algebra',
        'skill_key' => 'general',
    ],
    'difficulty' => $difficulty,
    'strategy_tip' => $analysis['strategy_tip'] ?? '',
    'similar_quizzes' => $similarQuizzes,
    'xp_earned' => $xpEarned,
    'scans_today' => $scansToday + 1,
    'scan_limit' => ($userTier === 'pro') ? 'unlimited' : 5,
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
