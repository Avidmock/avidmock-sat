<?php
/**
 * admin.sat.avidmock.com / api / save-quiz.php
 *
 * Saves all questions for a quiz from builder.php.
 * Called on every Save / Publish button click.
 *
 * POST JSON:
 *   id         int     quiz ID
 *   status     string  "draft" | "published"
 *   questions  array
 *
 * Response JSON:
 *   { success: true,  question_ids: {0:1, 1:2, ...}, status: "draft" }
 *   { success: false, error: "..." }
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

/* ── MUST match auth-guard.php exactly before anything touches $_SESSION ── */
if (session_status() === PHP_SESSION_NONE) {
    session_name('avidmock_admin_session');
    session_set_cookie_params([
        'lifetime' => 86400 * 30,
        'path'     => '/',
        'domain'   => 'admin.sat.avidmock.com',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

/* ── Database connection ─────────────────────────────────────────────────── */
$configPaths = [
    __DIR__ . '/../config/config.php',
    __DIR__ . '/../config/db.php',
    __DIR__ . '/../includes/db.php',
    __DIR__ . '/../init.php',
];
foreach ($configPaths as $p) {
    if (file_exists($p)) { require_once $p; break; }
}

if (!isset($pdo) && isset($db))   $pdo = $db;
if (!isset($pdo) && isset($conn)) $pdo = $conn;

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database not configured — check config path in save-quiz.php']);
    exit;
}

/* ── Admin auth check ────────────────────────────────────────────────────── */
$adminId   = $_SESSION['admin_id'] ?? null;
$adminRole = $_SESSION['admin_role'] ?? null;
$validRoles = ['admin', 'teacher'];

if (!$adminId || !in_array($adminRole, $validRoles, true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

/* ── Method ──────────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

/* ── Parse JSON body ─────────────────────────────────────────────────────── */
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!$body || !isset($body['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
    exit;
}

$quizId    = (int)$body['id'];
$status    = in_array($body['status'] ?? '', ['draft', 'published', 'archived'])
             ? $body['status'] : 'draft';
$questions = $body['questions'] ?? [];

if (!$quizId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing quiz id']);
    exit;
}

/* ── Verify quiz exists ──────────────────────────────────────────────────── */
$check = $pdo->prepare("SELECT id FROM sat_quizzes WHERE id = :id");
$check->execute([':id' => $quizId]);
if (!$check->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Quiz not found']);
    exit;
}

/* ── Detect optional columns ─────────────────────────────────────────────── */
$questionCols  = $pdo->query("SHOW COLUMNS FROM sat_quiz_questions")->fetchAll(PDO::FETCH_COLUMN);
$hasExpHtml    = in_array('explanation_html',  $questionCols);
$hasExpImage   = in_array('explanation_image', $questionCols);
$hasHint       = in_array('hint',              $questionCols);
$hasPoints     = in_array('points',            $questionCols);
$hasMicroTopic = in_array('micro_topic_id',    $questionCols);

/* ── Save ────────────────────────────────────────────────────────────────── */
try {
    $pdo->beginTransaction();

    /* 1. Update quiz status */
    $pdo->prepare("UPDATE sat_quizzes SET status = :status, updated_at = NOW() WHERE id = :id")
        ->execute([':status' => $status, ':id' => $quizId]);

    /* 2. Delete removed questions */
    $incomingIds = array_values(array_filter(
        array_map(fn($q) => (isset($q['id']) && (int)$q['id'] > 0) ? (int)$q['id'] : null, $questions)
    ));

    if (!empty($incomingIds)) {
        $ph = implode(',', array_fill(0, count($incomingIds), '?'));
        $pdo->prepare("DELETE FROM sat_quiz_questions WHERE quiz_id = ? AND id NOT IN ($ph)")
            ->execute(array_merge([$quizId], $incomingIds));
    } else {
        $pdo->prepare("DELETE FROM sat_quiz_questions WHERE quiz_id = ?")
            ->execute([$quizId]);
    }

    /* 3. Build upsert SQL with only the columns that exist */
    $extraCols = '';
    $extraVals = '';
    $extraUpd  = '';

    if ($hasExpHtml) {
        $extraCols .= ', explanation_html';
        $extraVals .= ', :explanation_html';
        $extraUpd  .= ', explanation_html = VALUES(explanation_html)';
    }
    if ($hasExpImage) {
        $extraCols .= ', explanation_image';
        $extraVals .= ', :explanation_image';
        $extraUpd  .= ', explanation_image = VALUES(explanation_image)';
    }
    if ($hasHint) {
        $extraCols .= ', hint';
        $extraVals .= ', :hint';
        $extraUpd  .= ', hint = VALUES(hint)';
    }
    if ($hasPoints) {
        $extraCols .= ', points';
        $extraVals .= ', :points';
        $extraUpd  .= ', points = VALUES(points)';
    }
    if ($hasMicroTopic) {
        $extraCols .= ', micro_topic_id';
        $extraVals .= ', :micro_topic_id';
        $extraUpd  .= ', micro_topic_id = VALUES(micro_topic_id)';
    }

    $sql = "
        INSERT INTO sat_quiz_questions
            (id, quiz_id, position, type, stem,
             option_a, option_b, option_c, option_d, correct_answer,
             difficulty, explanation{$extraCols})
        VALUES
            (:id, :quiz_id, :position, :type, :stem,
             :option_a, :option_b, :option_c, :option_d, :correct_answer,
             :difficulty, :explanation{$extraVals})
        ON DUPLICATE KEY UPDATE
            position       = VALUES(position),
            stem           = VALUES(stem),
            type           = VALUES(type),
            option_a       = VALUES(option_a),
            option_b       = VALUES(option_b),
            option_c       = VALUES(option_c),
            option_d       = VALUES(option_d),
            correct_answer = VALUES(correct_answer),
            difficulty     = VALUES(difficulty),
            explanation    = VALUES(explanation),
            hint           = VALUES(hint),
            points         = VALUES(points),
            explanation_html  = VALUES(explanation_html),
            explanation_image = VALUES(explanation_image)
    ";

    $stmt = $pdo->prepare($sql);

    /* 4. Upsert each question */
    $questionIds = [];
    foreach ($questions as $i => $q) {
        $qId = (isset($q['id']) && (int)$q['id'] > 0) ? (int)$q['id'] : null;

        $params = [
            ':id'             => $qId,
            ':quiz_id'        => $quizId,
            ':position'       => (int)($q['position'] ?? $i + 1),
            ':type'           => in_array($q['type'] ?? '', ['mcq', 'grid_in']) ? $q['type'] : 'mcq',
            ':stem'           => trim($q['stem'] ?? ''),
            ':option_a'       => trim($q['option_a'] ?? ''),
            ':option_b'       => trim($q['option_b'] ?? ''),
            ':option_c'       => trim($q['option_c'] ?? ''),
            ':option_d'       => trim($q['option_d'] ?? ''),
            ':correct_answer' => strtolower(trim($q['correct_answer'] ?? 'a')),
            ':difficulty'     => in_array($q['difficulty'] ?? '', ['easy','medium','hard']) ? $q['difficulty'] : 'medium',
            ':explanation'    => trim($q['explanation'] ?? ''),
        ];

        if ($hasExpHtml)    $params[':explanation_html']  = trim($q['explanation_html'] ?? '');
        if ($hasExpImage)   $params[':explanation_image'] = trim($q['explanation_image'] ?? '') ?: '';
        if ($hasHint)       $params[':hint']              = trim($q['hint'] ?? '');
        if ($hasPoints)     $params[':points']            = max(1, (int)($q['points'] ?? 1));
        if ($hasMicroTopic) $params[':micro_topic_id']    = !empty($q['micro_topic_id']) ? (int)$q['micro_topic_id'] : null;

        $stmt->execute($params);
        $questionIds[$i] = $qId ?? (int)$pdo->lastInsertId();
    }

    $pdo->commit();

    echo json_encode([
        'success'      => true,
        'question_ids' => $questionIds,
        'status'       => $status,
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[save-quiz] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ]);
}