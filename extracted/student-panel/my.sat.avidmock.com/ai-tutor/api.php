<?php
/**
 * /ai-tutor/api.php — AI Tutor API Endpoint
 *
 * Handles:
 *   POST — Send message → Claude API (SSE stream)
 *   GET  — Load conversation messages by ?conversation_id=
 *
 * Rate-limited: 30 requests/hour per user.
 * SSE format:  data: {"delta":"..."}\n\n
 *              data: {"conversation_id":123}\n\n
 *              data: [DONE]\n\n
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/AITutorHistory.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/RateLimit.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/CategoryPerformance.php';

Auth::requireStudent();
$userId = $_SESSION['user_id'];
$user   = User::findById($userId);

/* ─── CORS / JSON headers ───────────────────────────────────────────── */
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    /* ── Load conversation history ──────────────────────────────────── */
    header('Content-Type: application/json; charset=utf-8');

    $convId = filter_input(INPUT_GET, 'conversation_id', FILTER_VALIDATE_INT);
    if (!$convId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing conversation_id']);
        exit;
    }

    $messages = AITutorHistory::getMessages($userId, $convId);
    if ($messages === false) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }

    echo json_encode(['messages' => $messages]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

/* ─── Parse input ───────────────────────────────────────────────────── */
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

$message        = trim($data['message'] ?? '');
$subject        = in_array($data['subject'] ?? '', ['math','reading_writing','general']) ? $data['subject'] : 'general';
$conversationId = isset($data['conversation_id']) ? (int)$data['conversation_id'] : null;
$history        = is_array($data['history'] ?? null) ? $data['history'] : [];

if (!$message || strlen($message) > 4000) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid message']);
    exit;
}

/* ─── Rate limit: 30 messages/hour ─────────────────────────────────── */
if (!RateLimit::check('ai_tutor:' . $userId, 30, 3600)) {
    http_response_code(429);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Rate limit exceeded. You can send 30 messages per hour.']);
    exit;
}

/* ─── Build system prompt ───────────────────────────────────────────── */
$targetScore  = (int)($user['target_score']  ?? 1200);
$testDate     = $user['test_date'] ?? null;
$daysUntilTest = $testDate ? max(0, (int)ceil((strtotime($testDate) - time()) / 86400)) : null;

$mathPerf  = CategoryPerformance::get($userId, 'math') ?? [];
$rwPerf    = CategoryPerformance::get($userId, 'reading_writing') ?? [];
$weakMath  = CategoryPerformance::getWeakTopics($userId, 'math', 5) ?? [];
$weakRW    = CategoryPerformance::getWeakTopics($userId, 'reading_writing', 5) ?? [];

$weakMathList = implode(', ', array_column($weakMath, 'topic_name'));
$weakRWList   = implode(', ', array_column($weakRW,   'topic_name'));

$subjectInstructions = [
    'math' => 'Focus exclusively on SAT Math. Use step-by-step solutions. Always show work. Render math using LaTeX notation ($...$ for inline, $$...$$ for display). Reference SAT Math domains: Heart of Algebra, Passport to Advanced Math, Problem Solving & Data Analysis, and Additional Topics.',
    'reading_writing' => 'Focus on SAT Reading & Writing. Explain question types: Information & Ideas, Craft & Structure, Expression of Ideas, and Standard English Conventions. Use passage-based reasoning strategies.',
    'general' => 'Cover any SAT topic — both Math and Reading & Writing. Adapt based on what the student asks.',
];

$systemPrompt = <<<PROMPT
You are an expert SAT tutor for Avidmock SAT, a premium test-prep platform. You are knowledgeable, patient, encouraging, and deeply familiar with the current SAT (College Board Digital SAT format).

STUDENT PROFILE:
- Target score: {$targetScore}/1600
- Math accuracy: {$mathPerf['avg_score']}% | R&W accuracy: {$rwPerf['avg_score']}%
- Weak Math topics: {$weakMathList}
- Weak R&W topics: {$weakRWList}
{$daysUntilTest ? "- Days until test: {$daysUntilTest}" : ''}

SUBJECT FOCUS: {$subjectInstructions[$subject]}

RESPONSE GUIDELINES:
1. Be conversational but precise. Avoid jargon unless explaining it.
2. For math problems: always show every step clearly. Use LaTeX for formulas.
3. Structure longer explanations with headers and clear sections.
4. After explaining a concept, briefly suggest what to practice next.
5. If the student seems stuck or frustrated, be extra encouraging.
6. Never just give the answer without explanation. Always teach the underlying concept.
7. Keep responses focused. Don't pad with unnecessary text.
8. Use concrete examples that match SAT difficulty levels.
9. If the question references a specific problem, ask for the full problem text if not provided.
10. Highlight common SAT traps and mistakes when relevant.
PROMPT;

/* ─── Build messages array for Claude ──────────────────────────────── */
$messages = [];

/* Include conversation history (sanitised) */
foreach (array_slice($history, -14) as $msg) { // max 14 prior messages
    $role    = $msg['role'] === 'user' ? 'user' : 'assistant';
    $content = substr(trim($msg['content'] ?? ''), 0, 3000);
    if ($content) {
        $messages[] = ['role' => $role, 'content' => $content];
    }
}

/* Add current user message */
$messages[] = ['role' => 'user', 'content' => $message];

/* ─── Create/update conversation record ────────────────────────────── */
if (!$conversationId) {
    /* Generate title from first ~60 chars of message */
    $title = mb_strlen($message) > 60 ? mb_substr($message, 0, 57) . '…' : $message;
    $conversationId = AITutorHistory::createConversation($userId, $title, $subject);
}

/* Save user message to DB */
AITutorHistory::addMessage($userId, $conversationId, 'user', $message);

/* ─── SSE headers ───────────────────────────────────────────────────── */
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store');
header('X-Accel-Buffering: no'); // nginx: disable proxy buffering
header('Connection: keep-alive');

/* Flush early so browser knows SSE is starting */
if (ob_get_level()) ob_end_flush();
ob_implicit_flush(true);

/* ─── Send conversation_id event ───────────────────────────────────── */
echo 'data: ' . json_encode(['conversation_id' => $conversationId]) . "\n\n";
flush();

/* ─── Call Claude API with streaming ───────────────────────────────── */
$anthropicKey = defined('ANTHROPIC_API_KEY') ? ANTHROPIC_API_KEY : getenv('ANTHROPIC_API_KEY');

if (!$anthropicKey) {
    echo 'data: ' . json_encode(['delta' => 'Configuration error: API key not set.']) . "\n\n";
    echo "data: [DONE]\n\n";
    flush();
    exit;
}

$payload = json_encode([
    'model'      => 'claude-sonnet-4-5',
    'max_tokens' => 1500,
    'stream'     => true,
    'system'     => $systemPrompt,
    'messages'   => $messages,
]);

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'x-api-key: '         . $anthropicKey,
        'anthropic-version: 2023-06-01',
        'content-type: application/json',
        'accept: text/event-stream',
    ],
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_TIMEOUT        => 90,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_WRITEFUNCTION  => function($curl, $chunk) use (&$fullResponse) {
        /* Parse SSE chunks from Anthropic and re-emit to client */
        $lines = explode("\n", $chunk);
        foreach ($lines as $line) {
            $line = trim($line);
            if (!str_starts_with($line, 'data: ')) continue;
            $jsonStr = substr($line, 6);
            if ($jsonStr === '[DONE]') continue;
            $event = json_decode($jsonStr, true);
            if (!$event) continue;

            $type = $event['type'] ?? '';

            if ($type === 'content_block_delta') {
                $delta = $event['delta']['text'] ?? '';
                if ($delta !== '') {
                    $fullResponse .= $delta;
                    echo 'data: ' . json_encode(['delta' => $delta]) . "\n\n";
                    flush();
                }
            } elseif ($type === 'message_stop') {
                /* Will be handled after curl finishes */
            }
        }
        return strlen($chunk);
    },
]);

$fullResponse = '';
$curlError    = '';

if (!curl_exec($ch)) {
    $curlError = curl_error($ch);
}
curl_close($ch);

/* ─── Save AI response to DB ───────────────────────────────────────── */
if ($fullResponse) {
    AITutorHistory::addMessage($userId, $conversationId, 'assistant', $fullResponse);
    AITutorHistory::updateConversationTimestamp($conversationId);
} elseif ($curlError) {
    echo 'data: ' . json_encode(['delta' => 'Connection error. Please try again.']) . "\n\n";
}

echo "data: [DONE]\n\n";
flush();
exit;