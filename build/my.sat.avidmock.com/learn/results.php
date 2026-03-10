<?php
/**
 * /learn/results.php — Quiz Results Page
 * my.sat.avidmock.com/learn/results.php?attempt=123
 *
 * Shows:
 * - Overall score with animated ring
 * - Time taken
 * - Score prediction badge (ScorePredictor integration)
 * - Per-domain breakdown
 * - Strengths & weaknesses
 * - Next steps / recommended actions
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Quiz.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/ScorePredictor.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/CategoryPerformance.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Achievement.php';

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
} catch (Throwable $e) {
    error_log('[learn/results.php] ' . $e->getMessage());
}

if (!$attempt) { header('Location: /learn/'); exit; }

/* ── Load answers ── */
$answersData = [];
try {
    $stmt = $pdo->prepare(
        "SELECT qa.*, qq.stem, qq.correct_answer, qq.explanation, qq.difficulty,
                qq.option_a, qq.option_b, qq.option_c, qq.option_d
         FROM quiz_attempt_answers qa
         JOIN sat_quiz_questions qq ON qq.id = qa.question_id
         WHERE qa.attempt_id = ?
         ORDER BY qq.position ASC"
    );
    $stmt->execute([$attemptId]);
    $answersData = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('[learn/results.php] answers: ' . $e->getMessage());
}

$totalQ   = count($answersData);
$correct  = 0;
$incorrect = 0;
$totalTime = 0;
$byDifficulty = ['easy' => ['c' => 0, 't' => 0], 'medium' => ['c' => 0, 't' => 0], 'hard' => ['c' => 0, 't' => 0]];

foreach ($answersData as $a) {
    if (!empty($a['is_correct'])) { $correct++; } else { $incorrect++; }
    $totalTime += (int) ($a['time_spent'] ?? 0);
    $diff = strtolower($a['difficulty'] ?? 'medium');
    if (!isset($byDifficulty[$diff])) $diff = 'medium';
    $byDifficulty[$diff]['t']++;
    if (!empty($a['is_correct'])) $byDifficulty[$diff]['c']++;
}

$score    = $totalQ > 0 ? round(($correct / $totalQ) * 100) : 0;
$mins     = floor($totalTime / 60);
$secs     = $totalTime % 60;
$avgTime  = $totalQ > 0 ? round($totalTime / $totalQ) : 0;

/* ── Score Prediction ── */
$prediction = ScorePredictor::getLatest($userId);
$hasPrediction = !empty($prediction);

/* ── Weakest areas ── */
$weakAreas = [];
try { $weakAreas = CategoryPerformance::getWeakest($userId, 3); } catch (Throwable) {}

/* ── Check for new achievements ── */
$newAchievements = [];
try {
    if ($score === 100) {
        $newAchievements[] = Achievement::tryUnlock($userId, 'first_perfect');
    }
    Achievement::tryUnlock($userId, 'first_quiz');
} catch (Throwable) {}

$quizTitle  = htmlspecialchars($attempt['quiz_title'] ?? 'Quiz Results', ENT_QUOTES);
$activePage = 'learn';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Results: <?= $quizTitle ?> — Avidmock SAT</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
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
.main-content{margin-left:260px;margin-top:56px;padding:32px 32px 80px;min-height:calc(100vh - 56px)}
@media(max-width:768px){.main-content{margin-left:0;padding:20px 16px 72px}}

.results-shell{max-width:760px;margin:0 auto;padding:32px 20px 80px}

/* ── Hero Section ── */
.results-hero{text-align:center;padding:40px 24px;background:var(--bg2);border-radius:var(--r);border:1px solid var(--bd);margin-bottom:24px}
.results-hero h1{font-size:1.3rem;font-weight:700;color:var(--tx);margin-bottom:4px}
.results-hero .subtitle{font-size:.85rem;color:var(--tx3);margin-bottom:28px}

.score-ring{width:160px;height:160px;margin:0 auto 20px;position:relative}
.score-ring svg{width:100%;height:100%;transform:rotate(-90deg)}
.score-ring .bg{stroke:var(--bd);stroke-width:8;fill:none}
.score-ring .fill{stroke:var(--ac);stroke-width:8;fill:none;stroke-linecap:round;stroke-dasharray:377;stroke-dashoffset:377;transition:stroke-dashoffset 1.5s cubic-bezier(.4,0,.2,1)}
.score-ring .score-inner{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center}
.score-ring .pct{font-size:2.8rem;font-weight:800;color:var(--tx);line-height:1}
.score-ring .label{font-size:.75rem;color:var(--tx3);font-weight:500;margin-top:4px}

.score-message{font-size:1.1rem;font-weight:600;color:var(--tx);margin-bottom:6px}
.score-breakdown{font-size:.9rem;color:var(--tx2)}

/* ── Stats Grid ── */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:24px}
.stat-card{background:var(--bg2);border:1px solid var(--bd);border-radius:var(--r);padding:16px;text-align:center}
.stat-card .value{font-size:1.4rem;font-weight:800;color:var(--tx)}
.stat-card .label{font-size:.72rem;color:var(--tx3);font-weight:500;text-transform:uppercase;letter-spacing:.04em;margin-top:2px}

/* ── Score Prediction Badge ── */
.prediction-card{background:linear-gradient(135deg,#5b28a4 0%,#7b4cc4 100%);border-radius:var(--r);padding:24px;margin-bottom:24px;color:#fff;display:flex;align-items:center;gap:20px}
.prediction-card .pred-score{font-size:2.4rem;font-weight:800;line-height:1}
.prediction-card .pred-range{font-size:.8rem;opacity:.8;margin-top:2px}
.prediction-card .pred-label{font-size:.85rem;font-weight:600;margin-bottom:4px}
.prediction-card .pred-desc{font-size:.78rem;opacity:.8;line-height:1.4}
.prediction-card .confidence{display:inline-block;background:rgba(255,255,255,.2);padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:600;margin-top:8px}

/* ── Difficulty Breakdown ── */
.difficulty-section{background:var(--bg2);border:1px solid var(--bd);border-radius:var(--r);padding:24px;margin-bottom:24px}
.difficulty-section h2{font-size:1rem;font-weight:700;margin-bottom:16px}
.diff-row{display:flex;align-items:center;gap:12px;margin-bottom:12px}
.diff-label{width:70px;font-size:.8rem;font-weight:600;color:var(--tx2);text-transform:capitalize}
.diff-bar-bg{flex:1;height:8px;background:var(--bd);border-radius:4px;overflow:hidden}
.diff-bar-fill{height:100%;border-radius:4px;transition:width 1s ease}
.diff-pct{width:40px;text-align:right;font-size:.8rem;font-weight:700;font-family:var(--fm)}

/* ── Actions ── */
.actions{display:flex;gap:12px;flex-wrap:wrap;justify-content:center;margin-top:28px}
.action-btn{padding:12px 28px;border-radius:10px;font-weight:600;font-size:.9rem;text-decoration:none;font-family:var(--ff);border:none;cursor:pointer;transition:all .2s}
.action-btn.primary{background:var(--ac);color:var(--dk)}
.action-btn.primary:hover{background:var(--ac2)}
.action-btn.secondary{background:var(--bg);color:var(--tx2);border:2px solid var(--bd)}
.action-btn.secondary:hover{border-color:var(--tx3)}
.action-btn.purple{background:var(--pr);color:#fff}
.action-btn.purple:hover{background:#4a1f87}

/* ── Weak Areas ── */
.weak-section{background:var(--bg2);border:1px solid var(--bd);border-radius:var(--r);padding:24px;margin-bottom:24px}
.weak-section h2{font-size:1rem;font-weight:700;margin-bottom:16px}
.weak-item{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--bd)}
.weak-item:last-child{border-bottom:none}
.weak-item .topic{font-size:.9rem;font-weight:500;color:var(--tx)}
.weak-item .acc{font-size:.85rem;font-weight:700;font-family:var(--fm)}

@media(max-width:600px){
    .stats-grid{grid-template-columns:repeat(2,1fr)}
    .prediction-card{flex-direction:column;text-align:center}
}
</style>
</head>
<body>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/sidebar.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/topbar.php';
?>

<main class="main-content">
<div class="results-shell">

    <!-- Hero -->
    <div class="results-hero">
        <h1><?= $quizTitle ?></h1>
        <div class="subtitle">Completed by <?= htmlspecialchars($firstName) ?></div>

        <div class="score-ring">
            <svg viewBox="0 0 128 128">
                <circle class="bg" cx="64" cy="64" r="60"/>
                <circle class="fill" id="ring" cx="64" cy="64" r="60"/>
            </svg>
            <div class="score-inner">
                <div class="pct" id="pct-display">0%</div>
                <div class="label">Score</div>
            </div>
        </div>

        <div class="score-message" id="msg">&nbsp;</div>
        <div class="score-breakdown"><?= $correct ?> correct, <?= $incorrect ?> incorrect out of <?= $totalQ ?></div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card"><div class="value"><?= $correct ?>/<?= $totalQ ?></div><div class="label">Correct</div></div>
        <div class="stat-card"><div class="value"><?= $mins ?>:<?= str_pad($secs, 2, '0', STR_PAD_LEFT) ?></div><div class="label">Total Time</div></div>
        <div class="stat-card"><div class="value"><?= $avgTime ?>s</div><div class="label">Avg/Question</div></div>
        <div class="stat-card"><div class="value"><?= $score ?>%</div><div class="label">Accuracy</div></div>
    </div>

    <?php if ($hasPrediction): ?>
    <!-- Score Prediction -->
    <div class="prediction-card">
        <div>
            <div class="pred-score"><?= $prediction['predicted_mid'] ?? '—' ?></div>
            <div class="pred-range"><?= $prediction['predicted_low'] ?? '—' ?>–<?= $prediction['predicted_high'] ?? '—' ?></div>
        </div>
        <div>
            <div class="pred-label">Your SAT Score Prediction</div>
            <div class="pred-desc">Based on your practice tests and quiz performance. The more you practice, the more accurate this becomes.</div>
            <div class="confidence"><?= round(($prediction['confidence'] ?? 0) * 100) ?>% confidence</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Difficulty Breakdown -->
    <div class="difficulty-section">
        <h2>Performance by Difficulty</h2>
        <?php foreach ($byDifficulty as $level => $data): if ($data['t'] === 0) continue; ?>
        <?php $pct = round(($data['c'] / $data['t']) * 100); ?>
        <?php $color = $pct >= 80 ? '#10b981' : ($pct >= 50 ? '#f39c12' : '#e74c3c'); ?>
        <div class="diff-row">
            <div class="diff-label"><?= ucfirst($level) ?></div>
            <div class="diff-bar-bg">
                <div class="diff-bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>" data-width="<?= $pct ?>"></div>
            </div>
            <div class="diff-pct" style="color:<?= $color ?>"><?= $data['c'] ?>/<?= $data['t'] ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($weakAreas)): ?>
    <!-- Weak Areas -->
    <div class="weak-section">
        <h2>Areas to Improve</h2>
        <?php foreach ($weakAreas as $area): ?>
        <div class="weak-item">
            <span class="topic"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $area['category'] ?? ''))) ?></span>
            <span class="acc" style="color:<?= ($area['avg_score'] ?? 0) >= 70 ? '#10b981' : '#e74c3c' ?>"><?= round($area['avg_score'] ?? 0) ?>%</span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="actions">
        <a href="/learn/review.php?attempt=<?= $attemptId ?>" class="action-btn primary">Review All Answers</a>
        <a href="/ai-tutor/?subject=math&q=Help+me+with+my+weak+areas" class="action-btn purple">Practice with AI Tutor</a>
        <a href="/learn/" class="action-btn secondary">Back to Lessons</a>
    </div>
</div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const pct = <?= $score ?>;
    const circumference = 2 * Math.PI * 60;

    // Animate score ring
    const ring = document.getElementById('ring');
    ring.style.strokeDasharray = circumference;
    const offset = circumference - (pct / 100) * circumference;

    if (pct >= 80) ring.style.stroke = '#10b981';
    else if (pct >= 60) ring.style.stroke = '#1fe290';
    else if (pct >= 40) ring.style.stroke = '#f39c12';
    else ring.style.stroke = '#e74c3c';

    setTimeout(() => { ring.style.strokeDashoffset = offset; }, 200);

    // Animate percentage counter
    const pctEl = document.getElementById('pct-display');
    let current = 0;
    const step = Math.max(1, Math.floor(pct / 30));
    const counter = setInterval(() => {
        current = Math.min(current + step, pct);
        pctEl.textContent = current + '%';
        if (current >= pct) clearInterval(counter);
    }, 30);

    // Message
    const msgs = pct >= 90 ? 'Outstanding!' : pct >= 70 ? 'Great work!' : pct >= 50 ? 'Good effort! Keep practicing.' : 'Keep going — review and try again!';
    document.getElementById('msg').textContent = msgs;
});
</script>
</body>
</html>
