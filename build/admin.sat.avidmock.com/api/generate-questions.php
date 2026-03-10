<?php
/**
 * api/generate-questions.php — AI Question Generation Endpoint
 * POST: Generate SAT questions using Claude AI
 * POST with save=1: Generate and save to quiz
 */
require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/AIQuestionGenerator.php';

header('Content-Type: application/json; charset=utf-8');

$admin = currentAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$domain     = $input['domain']     ?? '';
$skill      = $input['skill']      ?? '';
$difficulty = $input['difficulty']  ?? 'medium';
$count      = (int)($input['count'] ?? 5);
$quizId     = (int)($input['quiz_id'] ?? 0);
$save       = (bool)($input['save'] ?? false);

// Validate domain
if (!isset(AIQuestionGenerator::DOMAINS[$domain])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid domain', 'valid_domains' => array_keys(AIQuestionGenerator::DOMAINS)]);
    exit;
}

// Validate skill
if (!isset(AIQuestionGenerator::DOMAINS[$domain]['skills'][$skill])) {
    http_response_code(400);
    echo json_encode([
        'error'        => 'Invalid skill for domain',
        'valid_skills' => array_keys(AIQuestionGenerator::DOMAINS[$domain]['skills']),
    ]);
    exit;
}

// Validate difficulty
if (!in_array($difficulty, AIQuestionGenerator::DIFFICULTY_LEVELS)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid difficulty', 'valid' => AIQuestionGenerator::DIFFICULTY_LEVELS]);
    exit;
}

// Rate limit: max 50 questions per day per admin
$db = Database::connect();
try {
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('ai_generation_log', $tables)) {
        $todayCount = (int) $db->prepare(
            "SELECT COALESCE(SUM(question_count),0) FROM ai_generation_log
             WHERE admin_id = ? AND DATE(created_at) = CURDATE()"
        )->execute([$admin['id'] ?? 0]) ? $db->query("SELECT FOUND_ROWS()")->fetchColumn() : 0;
    }
} catch (\Throwable) {
    // Table may not exist yet — skip rate limit
}

if ($save && $quizId > 0) {
    $result = AIQuestionGenerator::generateAndSave($quizId, $domain, $skill, $difficulty, $count);
} else {
    $result = AIQuestionGenerator::generate($domain, $skill, $difficulty, $count);
}

// Log generation
try {
    $db->exec(
        "CREATE TABLE IF NOT EXISTS ai_generation_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT,
            domain VARCHAR(50),
            skill VARCHAR(80),
            difficulty VARCHAR(20),
            question_count INT DEFAULT 0,
            quiz_id INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    );
    $logStmt = $db->prepare(
        "INSERT INTO ai_generation_log (admin_id, domain, skill, difficulty, question_count, quiz_id)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $logStmt->execute([
        $admin['id'] ?? 0,
        $domain,
        $skill,
        $difficulty,
        count($result['questions'] ?? []),
        $quizId ?: null,
    ]);
} catch (\Throwable) {
    // Non-critical
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
