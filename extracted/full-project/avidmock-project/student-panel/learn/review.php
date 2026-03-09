<?php
/**
 * /learn/review.php — Answer Review Page
 * my.sat.avidmock.com/learn/review.php?attempt=123
 *
 * Shows every question with the student's answer, correct answer,
 * explanation, and a link to ask the AI tutor about each question.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';

Auth::requireStudentOrRedirect();
$userId    = (int) $_SESSION['user_id'];
$user      = User::findById($userId);
$firstName = explode(' ', $user['name'] ?? 'Student')[0];

$attemptId = (int) ($_GET['attempt'] ?? 0);
if (!$attemptId) { header('Location: /learn/'); exit; }

/* ── Load attempt ── */
$attempt = null;
try {
    $stmt = $pdo->prepare(
        "SELECT a.*, q.title AS quiz_title, q.lesson_slug
         FROM sat_quiz_attempts a
         JOIN sat_quizzes q ON q.id = a.quiz_id
         WHERE a.id = ? AND a.user_id = ? LIMIT 1"
    );
    $stmt->execute([$attemptId, $userId]);
    $attempt = $stmt->fetch();
} catch (Throwable $e) { error_log('[learn/review.php] ' . $e->getMessage()); }

if (!$attempt) { header('Location: /learn/'); exit; }

/* ── Load all answers with questions ── */
$answers = [];
try {
    $stmt = $pdo->prepare(
        "SELECT qa.question_id, qa.user_answer, qa.is_correct, qa.time_spent,
                qq.stem, qq.correct_answer, qq.explanation, qq.difficulty, qq.type,
                qq.option_a, qq.option_b, qq.option_c, qq.option_d, qq.position
         FROM quiz_attempt_answers qa
         JOIN sat_quiz_questions qq ON qq.id = qa.question_id
         WHERE qa.attempt_id = ?
         ORDER BY qq.position ASC"
    );
    $stmt->execute([$attemptId]);
    $answers = $stmt->fetchAll();
} catch (Throwable $e) { error_log('[learn/review.php] answers: ' . $e->getMessage()); }

/* ── Also try to get explanation_html if column exists ── */
$hasExpHtml = false;
try {
    $cols = $pdo->query("SHOW COLUMNS FROM sat_quiz_questions")->fetchAll(PDO::FETCH_COLUMN);
    $hasExpHtml = in_array('explanation_html', $cols);
} catch (Throwable) {}

if ($hasExpHtml && !empty($answers)) {
    $qIds = array_column($answers, 'question_id');
    $placeholders = implode(',', array_fill(0, count($qIds), '?'));
    try {
        $stmt = $pdo->prepare("SELECT id, explanation_html FROM sat_quiz_questions WHERE id IN ({$placeholders})");
        $stmt->execute($qIds);
        $expHtmlMap = [];
        while ($row = $stmt->fetch()) { $expHtmlMap[$row['id']] = $row['explanation_html']; }
        foreach ($answers as &$a) {
            $a['explanation_html'] = $expHtmlMap[$a['question_id']] ?? '';
        }
        unset($a);
    } catch (Throwable) {}
}

$totalQ  = count($answers);
$correct = count(array_filter($answers, fn($a) => !empty($a['is_correct'])));
$score   = $totalQ > 0 ? round(($correct / $totalQ) * 100) : 0;

$quizTitle  = htmlspecialchars($attempt['quiz_title'] ?? 'Review', ENT_QUOTES);
$activePage = 'learn';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Review: <?= $quizTitle ?> — Avidmock SAT</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/KaTeX/0.16.9/katex.min.css">
<style>
:root {
    --dk:#143230; --ac:#1fe290; --ac2:#17c87a;
    --tx:#1a1a2e; --tx2:#4a4a5a; --tx3:#8a8a9a;
    --bg:#f7faf9; --bg2:#ffffff; --bd:#e2ebe9;
    --err:#e74c3c; --warn:#f39c12; --ok:#10b981; --pr:#5b28a4;
    --ff:'DM Sans',-apple-system,sans-serif;
    --fm:'DM Mono',monospace;
    --r:14px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--ff);-webkit-font-smoothing:antialiased;background:var(--bg);color:var(--tx);min-height:100vh}
.review-shell{max-width:760px;margin:0 auto;padding:32px 20px 80px}

/* ── Header ── */
.review-header{display:flex;align-items:center;gap:16px;margin-bottom:24px;flex-wrap:wrap}
.review-header a{color:var(--tx3);text-decoration:none;display:flex;align-items:center;gap:6px;font-size:.875rem;transition:color .2s}
.review-header a:hover{color:var(--tx)}
.review-header a svg{width:18px;height:18px}
.review-header h1{flex:1;font-size:1.2rem;font-weight:700}
.review-header .score-badge{padding:6px 14px;border-radius:20px;font-weight:700;font-size:.85rem;color:#fff}

/* ── Filter Tabs ── */
.filter-tabs{display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap}
.filter-tab{padding:8px 16px;border-radius:8px;font-size:.8rem;font-weight:600;border:2px solid var(--bd);background:var(--bg2);color:var(--tx2);cursor:pointer;transition:all .2s;font-family:var(--ff)}
.filter-tab.active{border-color:var(--pr);background:rgba(91,40,164,.06);color:var(--pr)}
.filter-tab:hover{border-color:var(--tx3)}

/* ── Question Card ── */
.q-card{background:var(--bg2);border:1px solid var(--bd);border-radius:var(--r);padding:24px;margin-bottom:16px;transition:all .2s}
.q-card.is-correct{border-left:4px solid var(--ok)}
.q-card.is-incorrect{border-left:4px solid var(--err)}

.q-top{display:flex;align-items:center;gap:10px;margin-bottom:12px}
.q-num{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--tx3)}
.q-status{padding:3px 10px;border-radius:12px;font-size:.7rem;font-weight:700}
.q-status.correct{background:rgba(16,185,129,.1);color:var(--ok)}
.q-status.incorrect{background:rgba(231,76,60,.1);color:var(--err)}
.q-difficulty{margin-left:auto;font-size:.7rem;font-weight:600;padding:3px 8px;border-radius:6px;background:var(--bg);color:var(--tx3);text-transform:capitalize}
.q-time{font-size:.75rem;font-family:var(--fm);color:var(--tx3)}

.q-stem{font-size:.95rem;line-height:1.6;margin-bottom:16px}

/* ── Options Review ── */
.opts-review{display:flex;flex-direction:column;gap:6px;margin-bottom:16px}
.opt-row{display:flex;align-items:flex-start;gap:10px;padding:8px 12px;border-radius:8px;font-size:.88rem;line-height:1.5}
.opt-row.user-correct{background:rgba(16,185,129,.08);color:var(--ok)}
.opt-row.user-wrong{background:rgba(231,76,60,.08);color:var(--err);text-decoration:line-through;text-decoration-color:rgba(231,76,60,.3)}
.opt-row.correct-answer{background:rgba(16,185,129,.06)}
.opt-letter{width:24px;height:24px;display:flex;align-items:center;justify-content:center;border-radius:6px;font-weight:700;font-size:.75rem;flex-shrink:0}
.opt-row.user-correct .opt-letter{background:var(--ok);color:#fff}
.opt-row.user-wrong .opt-letter{background:var(--err);color:#fff}
.opt-row.correct-answer .opt-letter{background:rgba(16,185,129,.2);color:var(--ok)}

/* ── Explanation ── */
.explanation{padding:16px;background:rgba(91,40,164,.04);border:1px solid rgba(91,40,164,.12);border-radius:10px;font-size:.88rem;line-height:1.6;color:var(--tx2)}
.explanation strong{color:var(--tx)}

/* ── AI Help Link ── */
.ai-ask{display:inline-flex;align-items:center;gap:6px;margin-top:12px;padding:6px 14px;border-radius:8px;background:rgba(91,40,164,.06);color:var(--pr);font-size:.8rem;font-weight:600;text-decoration:none;transition:all .2s}
.ai-ask:hover{background:rgba(91,40,164,.12)}
.ai-ask svg{width:14px;height:14px}

/* ── Bottom Actions ── */
.bottom-actions{display:flex;gap:12px;justify-content:center;margin-top:32px;flex-wrap:wrap}
.btn{padding:12px 24px;border-radius:10px;font-weight:600;font-size:.9rem;text-decoration:none;font-family:var(--ff);transition:all .2s}
.btn-primary{background:var(--ac);color:var(--dk)}.btn-primary:hover{background:var(--ac2)}
.btn-secondary{background:var(--bg2);color:var(--tx2);border:2px solid var(--bd)}.btn-secondary:hover{border-color:var(--tx3)}

@media(max-width:600px){
    .review-shell{padding:20px 14px 70px}
    .q-card{padding:18px 14px}
}
</style>
</head>
<body>

<div class="review-shell">
    <!-- Header -->
    <div class="review-header">
        <a href="/learn/results.php?attempt=<?= $attemptId ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Results
        </a>
        <h1>Review: <?= $quizTitle ?></h1>
        <span class="score-badge" style="background:<?= $score >= 80 ? 'var(--ok)' : ($score >= 50 ? 'var(--warn)' : 'var(--err)') ?>"><?= $score ?>%</span>
    </div>

    <!-- Filters -->
    <div class="filter-tabs">
        <button class="filter-tab active" onclick="filterQuestions('all', this)">All (<?= $totalQ ?>)</button>
        <button class="filter-tab" onclick="filterQuestions('correct', this)">Correct (<?= $correct ?>)</button>
        <button class="filter-tab" onclick="filterQuestions('incorrect', this)">Incorrect (<?= $totalQ - $correct ?>)</button>
    </div>

    <!-- Questions -->
    <?php foreach ($answers as $i => $a):
        $isCorrect   = !empty($a['is_correct']);
        $userAnswer   = strtoupper(trim($a['user_answer'] ?? ''));
        $correctAns   = strtoupper(trim($a['correct_answer'] ?? ''));
        $options      = ['A' => $a['option_a'] ?? '', 'B' => $a['option_b'] ?? '', 'C' => $a['option_c'] ?? '', 'D' => $a['option_d'] ?? ''];
        $explanation   = $a['explanation_html'] ?? $a['explanation'] ?? '';
        $timeSpent    = (int) ($a['time_spent'] ?? 0);
        $difficulty   = $a['difficulty'] ?? 'medium';
        $stemEncoded  = urlencode(substr($a['stem'] ?? '', 0, 100));
    ?>
    <div class="q-card <?= $isCorrect ? 'is-correct' : 'is-incorrect' ?>" data-status="<?= $isCorrect ? 'correct' : 'incorrect' ?>">
        <div class="q-top">
            <span class="q-num">Q<?= $i + 1 ?></span>
            <span class="q-status <?= $isCorrect ? 'correct' : 'incorrect' ?>"><?= $isCorrect ? '✓ Correct' : '✗ Incorrect' ?></span>
            <span class="q-difficulty"><?= htmlspecialchars($difficulty) ?></span>
            <span class="q-time"><?= $timeSpent ?>s</span>
        </div>

        <div class="q-stem"><?= $a['stem'] ?? '' ?></div>

        <div class="opts-review">
            <?php foreach ($options as $letter => $text): if (!$text) continue;
                $isUserAnswer   = ($letter === $userAnswer);
                $isCorrectOpt   = ($letter === $correctAns);
                $rowClass = '';
                if ($isUserAnswer && $isCorrect) $rowClass = 'user-correct';
                elseif ($isUserAnswer && !$isCorrect) $rowClass = 'user-wrong';
                elseif ($isCorrectOpt && !$isCorrect) $rowClass = 'correct-answer';
            ?>
            <div class="opt-row <?= $rowClass ?>">
                <span class="opt-letter"><?= $letter ?></span>
                <span><?= $text ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($explanation): ?>
        <div class="explanation">
            <strong>Explanation:</strong> <?= $explanation ?>
        </div>
        <?php endif; ?>

        <?php if (!$isCorrect): ?>
        <a href="/ai-tutor/?subject=math&q=Explain+this+SAT+problem:+<?= $stemEncoded ?>" class="ai-ask" target="_blank">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a7 7 0 0 1 7 7c0 2.38-1.19 4.47-3 5.74V17a2 2 0 0 1-2 2H10a2 2 0 0 1-2-2v-2.26C6.19 13.47 5 11.38 5 9a7 7 0 0 1 7-7z"/></svg>
            Ask AI Tutor to explain
        </a>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <!-- Bottom Actions -->
    <div class="bottom-actions">
        <a href="/learn/results.php?attempt=<?= $attemptId ?>" class="btn btn-secondary">← Back to Results</a>
        <?php if (!empty($attempt['lesson_slug'])): ?>
        <a href="/learn/quiz.php?lesson=<?= urlencode($attempt['lesson_slug']) ?>" class="btn btn-primary">Retake Quiz</a>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/KaTeX/0.16.9/katex.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/KaTeX/0.16.9/contrib/auto-render.min.js"></script>
<script>
/* ── Math rendering ── */
document.addEventListener('DOMContentLoaded', () => {
    if (typeof renderMathInElement === 'function') {
        renderMathInElement(document.body, {
            delimiters: [
                {left: '$$', right: '$$', display: true},
                {left: '$', right: '$', display: false},
                {left: '\\(', right: '\\)', display: false},
                {left: '\\[', right: '\\]', display: true},
            ],
            throwOnError: false,
        });
    }
});

/* ── Filter ── */
function filterQuestions(status, btn) {
    document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');

    document.querySelectorAll('.q-card').forEach(card => {
        if (status === 'all') { card.style.display = ''; }
        else { card.style.display = card.dataset.status === status ? '' : 'none'; }
    });
}
</script>
</body>
</html>
