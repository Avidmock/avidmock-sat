<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/CategoryPerformance.php';

header('Content-Type: application/json; charset=utf-8');

$userId = (int)($_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 0);
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

$body       = json_decode(file_get_contents('php://input'), true) ?? [];
$attemptId  = (int)($body['attempt_id'] ?? 0);
$quizId     = (int)($body['quiz_id']    ?? 0);
$timeSpent  = max(0, (int)($body['time_spent'] ?? 0));
$autoSubmit = !empty($body['auto_submit']);

if (!$attemptId || !$quizId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing attempt_id or quiz_id']);
    exit;
}

$stmt = $pdo->prepare("SELECT id,status,correct,incorrect,total FROM sat_quiz_attempts WHERE id=:id AND user_id=:uid AND quiz_id=:qid LIMIT 1");
$stmt->execute([':id' => $attemptId, ':uid' => $userId, ':qid' => $quizId]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attempt) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Attempt not found']);
    exit;
}

if ($attempt['status'] === 'completed') {
    echo json_encode(['success' => true, 'attempt_id' => $attemptId, 'already_done' => true]);
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM sat_quiz_questions WHERE quiz_id=:qid");
$stmt->execute([':qid' => $quizId]);
$totalQ = (int)$stmt->fetchColumn();

$correctCount = (int)($attempt['correct'] ?? 0);
if ($correctCount === 0) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM quiz_attempt_answers WHERE attempt_id=:id AND is_correct=1");
        $stmt->execute([':id' => $attemptId]);
        $correctCount = (int)$stmt->fetchColumn();
    } catch (Throwable) {}
}

$score = $totalQ > 0 ? (int)round(($correctCount / $totalQ) * 100) : 0;

$stmt = $pdo->prepare("SELECT lesson_slug, pass_threshold, xp_reward FROM sat_quizzes WHERE id=:id");
$stmt->execute([':id' => $quizId]);
$quizRow    = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$threshold  = (int)($quizRow['pass_threshold'] ?? 70);
$passed     = ($score >= $threshold);
$xpBase     = (int)($quizRow['xp_reward'] ?? 50);
$xpEarned   = $xpBase + ($score === 100 ? 50 : 0);
$lessonSlug = $quizRow['lesson_slug'] ?? '';

try {
    $pdo->prepare("
        UPDATE sat_quiz_attempts
        SET status='completed', score=:score, correct=:correct, total=:total,
            time_spent=:time, passed=:passed, xp_awarded=:xp,
            completed_at=NOW(), progress_json=NULL, flagged_json=NULL
        WHERE id=:id
    ")->execute([
        ':score'   => $score,
        ':correct' => $correctCount,
        ':total'   => $totalQ,
        ':time'    => $timeSpent,
        ':passed'  => $passed ? 1 : 0,
        ':xp'      => $xpEarned,
        ':id'      => $attemptId,
    ]);
} catch (Throwable $e) {
    error_log('submit-quiz attempt update: ' . $e->getMessage());
}

try {
    if (!empty($lessonSlug)) {
        CategoryPerformance::markLessonComplete($userId, '/math/' . $lessonSlug);
    }
} catch (Throwable $e) {
    error_log('submit-quiz lesson complete: ' . $e->getMessage());
}

try {
    $pdo->prepare("DELETE FROM quiz_progress WHERE attempt_id=:id")
        ->execute([':id' => $attemptId]);
} catch (Throwable) {}

try {
    $pdo->prepare("
        INSERT INTO user_xp (user_id, xp, level, updated_at)
        VALUES (:uid, :xp, 1, NOW())
        ON DUPLICATE KEY UPDATE xp = xp + :xp2, updated_at = NOW()
    ")->execute([':uid' => $userId, ':xp' => $xpEarned, ':xp2' => $xpEarned]);

    $pdo->prepare("
        INSERT INTO xp_events (user_id, event_type, xp_earned, reference_id, created_at)
        VALUES (:uid, 'quiz', :xp, :qid, NOW())
    ")->execute([':uid' => $userId, ':xp' => $xpEarned, ':qid' => $quizId]);
} catch (Throwable $e) {
    error_log('submit-quiz XP: ' . $e->getMessage());
}

try {
    $today     = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    $stmt = $pdo->prepare("SELECT current_streak, last_activity_date FROM study_streaks WHERE user_id=:uid");
    $stmt->execute([':uid' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $pdo->prepare("
            INSERT INTO study_streaks (user_id, current_streak, longest_streak, last_activity_date)
            VALUES (:uid, 1, 1, :today)
        ")->execute([':uid' => $userId, ':today' => $today]);
    } else {
        $last = $row['last_activity_date'];
        $cur  = (int)$row['current_streak'];
        if ($last === $today) {
            // already counted
        } elseif ($last === $yesterday) {
            $cur++;
            $pdo->prepare("
                UPDATE study_streaks
                SET current_streak=:cur, longest_streak=GREATEST(longest_streak,:cur), last_activity_date=:today
                WHERE user_id=:uid
            ")->execute([':cur' => $cur, ':today' => $today, ':uid' => $userId]);
        } else {
            $pdo->prepare("
                UPDATE study_streaks SET current_streak=1, last_activity_date=:today WHERE user_id=:uid
            ")->execute([':today' => $today, ':uid' => $userId]);
        }
    }
} catch (Throwable $e) {
    error_log('submit-quiz streak: ' . $e->getMessage());
}

echo json_encode([
    'success'    => true,
    'attempt_id' => $attemptId,
    'score'      => $score,
    'passed'     => $passed,
    'correct'    => $correctCount,
    'total'      => $totalQ,
    'xp_earned'  => $xpEarned,
]);