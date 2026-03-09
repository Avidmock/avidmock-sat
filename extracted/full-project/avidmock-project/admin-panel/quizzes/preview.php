<?php
/**
 * quizzes/preview.php
 * Student-view preview of a quiz for admins.
 * Shows exactly what students see, with a banner reminding admin they're in preview mode.
 * Answers are never recorded.
 */
require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

$admin  = currentAdmin();
$db     = Database::connect();
$quizId = (int)($_GET['id'] ?? 0);

if (!$quizId) {
    header('Location: /quizzes/index.php');
    exit;
}

// ── Load quiz ─────────────────────────────────────────────────────────────
$quizStmt = $db->prepare("SELECT * FROM sat_quizzes WHERE id = :id");
$quizStmt->execute([':id' => $quizId]);
$quiz = $quizStmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    header('Location: /quizzes/index.php');
    exit;
}

// ── Detect optional columns ───────────────────────────────────────────────
$quizCols     = $db->query("SHOW COLUMNS FROM sat_quizzes")->fetchAll(PDO::FETCH_COLUMN);
$questionCols = $db->query("SHOW COLUMNS FROM sat_quiz_questions")->fetchAll(PDO::FETCH_COLUMN);

$hasInstructions = in_array('instructions', $quizCols);
$hasHint         = in_array('hint',         $questionCols);
$hasExplanation  = in_array('explanation',  $questionCols);
$hasPoints       = in_array('points',       $questionCols);

// ── Load questions ────────────────────────────────────────────────────────
$questionsStmt = $db->prepare(
    "SELECT * FROM sat_quiz_questions WHERE quiz_id = :id ORDER BY position ASC"
);
$questionsStmt->execute([':id' => $quizId]);
$questions = $questionsStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Shuffle questions server-side if enabled ──────────────────────────────
// We pass the shuffled order as a JSON array to JS so the order is stable
// for the whole session (no re-shuffle on JS re-render).
if (!empty($quiz['shuffle_q'])) {
    shuffle($questions);
}

// ── Build option order per question ──────────────────────────────────────
// If shuffle_opts is on, we shuffle the display order of A/B/C/D here
// and pass the mapping to JS so correct_answer always refers to the right text.
$questionData = [];
foreach ($questions as $q) {
    $opts = [];
    foreach (['a', 'b', 'c', 'd'] as $k) {
        if (!empty($q['option_' . $k])) {
            $opts[] = ['key' => $k, 'text' => $q['option_' . $k]];
        }
    }
    if (!empty($quiz['shuffle_opts'])) {
        shuffle($opts);
    }
    $questionData[] = [
        'id'             => (int)$q['id'],
        'stem'           => $q['stem'] ?? '',
        'difficulty'     => $q['difficulty'] ?? 'medium',
        'correct_answer' => $q['correct_answer'] ?? 'a',
        'hint'           => $hasHint        ? ($q['hint']        ?? '') : '',
        'explanation'    => $hasExplanation ? ($q['explanation'] ?? '') : '',
        'points'         => $hasPoints      ? (int)($q['points'] ?? 1) : 1,
        'opts'           => $opts,
    ];
}

$totalQ     = count($questionData);
$passScore  = (int)($quiz['passing_score'] ?? 70);
$timeLimit  = (int)($quiz['time_limit']    ?? 0);
$showHints  = !empty($quiz['show_hints']);
$xpReward   = (int)($quiz['xp_reward']    ?? 0);
$instructions = ($hasInstructions && !empty($quiz['instructions']))
    ? nl2br(htmlspecialchars($quiz['instructions']))
    : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Preview: <?= htmlspecialchars($quiz['title']) ?> — Avidmock</title>
<meta name="robots" content="noindex,nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700;9..40,800&family=Fraunces:ital,opsz,wght@0,9..144,900;1,9..144,300&family=DM+Mono:wght@400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css">
<style>
:root{
    --ink:#0c1f1d;--ink2:#0e2522;--dk:#143230;
    --ac:#1fe290;--ac2:#13c474;--ac3:rgba(31,226,144,.08);--ac4:rgba(31,226,144,.15);
    --tx:#e8f3f1;--tx2:#9dbfba;--tx3:#5a8580;
    --bd:rgba(255,255,255,.07);--bd2:rgba(255,255,255,.13);
    --sf:rgba(255,255,255,.04);--sf2:rgba(255,255,255,.07);--sf3:rgba(255,255,255,.10);
    --warn:#f59e0b;--warn2:rgba(245,158,11,.12);
    --err:#ef4444;--err2:rgba(239,68,68,.12);
    --ff:'DM Sans',sans-serif;--fh:'Fraunces',Georgia,serif;--fm:'DM Mono',monospace;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{font-family:var(--ff);background:var(--ink);color:var(--tx);-webkit-font-smoothing:antialiased;min-height:100vh}

/* ── PREVIEW BANNER ─────────────────────────────────────────────────────── */
.preview-banner{
    background:rgba(245,158,11,.1);
    border-bottom:1px solid rgba(245,158,11,.2);
    padding:9px 20px;
    display:flex;align-items:center;justify-content:space-between;gap:12px;
    position:sticky;top:0;z-index:100;
    backdrop-filter:blur(12px);
    flex-wrap:wrap;
}
.preview-banner-text{
    font-size:.75rem;font-weight:700;color:var(--warn);
    display:flex;align-items:center;gap:7px;
}
.preview-banner-text svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:1.9;stroke-linecap:round}
.preview-banner-actions{display:flex;gap:7px;flex-wrap:wrap}
.pbtn{
    display:inline-flex;align-items:center;gap:5px;
    padding:6px 12px;border-radius:7px;
    font-size:.75rem;font-weight:700;text-decoration:none;
    border:1.5px solid rgba(255,255,255,.1);
    background:rgba(255,255,255,.05);
    color:var(--tx2);cursor:pointer;font-family:var(--ff);
    transition:all .16s;white-space:nowrap;
}
.pbtn:hover{background:rgba(255,255,255,.09);color:var(--tx)}
.pbtn svg{width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}

/* ── QUIZ WRAPPER ───────────────────────────────────────────────────────── */
.quiz-wrap{max-width:720px;margin:0 auto;padding:36px 24px 80px}

.quiz-title{
    font-family:var(--fh);font-size:1.75rem;font-weight:900;
    color:var(--tx);letter-spacing:-.035em;margin-bottom:10px;line-height:1.2;
}
.quiz-meta{
    display:flex;align-items:center;gap:14px;
    font-size:.75rem;color:var(--tx3);margin-bottom:10px;flex-wrap:wrap;
}
.quiz-meta-item{display:flex;align-items:center;gap:4px}
.quiz-meta-item svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round}
.quiz-instructions{
    background:var(--sf);border:1px solid var(--bd);border-radius:10px;
    padding:14px 16px;font-size:.875rem;color:var(--tx2);line-height:1.7;
    margin-bottom:24px;
}

/* ── TIMER ──────────────────────────────────────────────────────────────── */
.timer-pill{
    display:inline-flex;align-items:center;gap:5px;
    font-family:var(--fm);font-size:.8125rem;color:var(--tx3);
    background:var(--sf2);padding:4px 10px;border-radius:6px;
    transition:all .3s;
}
.timer-pill svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round}
.timer-pill.warning{background:var(--err2);color:var(--err);animation:pulse .8s ease-in-out infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.65}}

/* ── PROGRESS ───────────────────────────────────────────────────────────── */
.progress-bar-wrap{height:3px;background:var(--sf2);border-radius:2px;margin-bottom:28px;overflow:hidden}
.progress-bar{height:100%;background:linear-gradient(90deg,var(--ac),var(--ac2));border-radius:2px;transition:width .35s ease}

/* ── QUESTION DOTS ──────────────────────────────────────────────────────── */
.q-dots{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:24px}
.q-dot{
    width:28px;height:28px;border-radius:7px;
    background:var(--sf2);border:1px solid var(--bd);
    display:flex;align-items:center;justify-content:center;
    font-size:.5625rem;font-weight:700;color:var(--tx3);
    cursor:pointer;transition:all .16s;
}
.q-dot:hover{background:var(--sf3);color:var(--tx)}
.q-dot.answered{background:var(--ac3);color:var(--ac);border-color:rgba(31,226,144,.2)}
.q-dot.current{background:var(--ac);color:var(--dk);border-color:var(--ac)}
.q-dot.flagged{background:var(--warn2);color:var(--warn);border-color:rgba(245,158,11,.25)}
.q-dot.flagged.answered{background:var(--warn2)}

/* ── QUESTION CARD ──────────────────────────────────────────────────────── */
.q-card{
    background:var(--sf);border:1px solid var(--bd);border-radius:14px;
    padding:24px;margin-bottom:16px;
    display:none;
}
.q-card.active{
    display:block;
    animation:slideIn .22s cubic-bezier(.16,1,.3,1);
}
@keyframes slideIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}

.q-header{display:flex;align-items:center;gap:10px;margin-bottom:16px}
.q-number{
    font-family:var(--fm);font-size:.625rem;color:var(--tx3);
    background:var(--sf2);padding:3px 9px;border-radius:5px;flex-shrink:0;
}
.q-diff-badge{font-size:.5rem;font-weight:800;padding:2px 7px;border-radius:50px;text-transform:uppercase}
.q-diff-badge.easy  {background:rgba(31,226,144,.1);color:var(--ac)}
.q-diff-badge.medium{background:rgba(245,158,11,.1);color:var(--warn)}
.q-diff-badge.hard  {background:rgba(239,68,68,.1); color:var(--err)}
.q-pts{font-family:var(--fm);font-size:.5rem;color:var(--tx3);margin-left:auto;padding:2px 6px;border-radius:4px;background:var(--sf2)}
.q-flag-btn{
    background:none;border:none;cursor:pointer;color:var(--tx3);
    transition:color .16s;padding:4px;line-height:0;border-radius:5px;
    flex-shrink:0;
}
.q-flag-btn:hover,.q-flag-btn.flagged{color:var(--warn)}
.q-flag-btn svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round}
.q-flag-btn.flagged svg{fill:var(--warn)}

/* ── STEM (KaTeX rendered) ──────────────────────────────────────────────── */
.q-stem{
    font-size:1rem;font-weight:600;color:var(--tx);
    line-height:1.7;margin-bottom:20px;
}
.q-stem .katex{font-size:1.05em}

/* ── OPTIONS ────────────────────────────────────────────────────────────── */
.q-options{display:flex;flex-direction:column;gap:8px}
.q-opt{
    display:flex;align-items:flex-start;gap:12px;
    padding:13px 16px;border-radius:10px;
    border:1.5px solid var(--bd);cursor:pointer;
    transition:all .16s;background:var(--sf);user-select:none;
}
.q-opt:hover:not(.locked){border-color:var(--bd2);background:var(--sf2)}
.q-opt.selected{border-color:rgba(31,226,144,.35);background:var(--ac3)}
.q-opt.correct{border-color:var(--ac);background:rgba(31,226,144,.08)}
.q-opt.wrong{border-color:rgba(239,68,68,.4);background:rgba(239,68,68,.05)}
.q-opt.locked{cursor:default;pointer-events:none}
.opt-key{
    width:24px;height:24px;border-radius:6px;
    background:var(--sf2);border:1px solid var(--bd);
    display:flex;align-items:center;justify-content:center;
    font-size:.625rem;font-weight:800;color:var(--tx3);
    flex-shrink:0;transition:all .16s;margin-top:1px;
}
.q-opt.selected .opt-key{background:var(--ac3);color:var(--ac);border-color:rgba(31,226,144,.3)}
.q-opt.correct  .opt-key{background:var(--ac);color:var(--dk);border-color:var(--ac)}
.q-opt.wrong    .opt-key{background:var(--err2);color:var(--err);border-color:rgba(239,68,68,.3)}
.opt-text{font-size:.9rem;color:var(--tx2);line-height:1.55;flex:1}
.opt-text .katex{font-size:1em}
.q-opt.correct .opt-text,.q-opt.wrong .opt-text{color:var(--tx)}

/* ── HINT ───────────────────────────────────────────────────────────────── */
.hint-btn{
    margin-top:14px;
    display:inline-flex;align-items:center;gap:6px;
    padding:7px 13px;border-radius:8px;
    background:var(--warn2);border:1px solid rgba(245,158,11,.2);
    color:var(--warn);font-size:.75rem;font-weight:700;
    cursor:pointer;transition:all .16s;font-family:var(--ff);
}
.hint-btn:hover{background:rgba(245,158,11,.2)}
.hint-btn svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round}
.hint-box{
    margin-top:10px;padding:12px 14px;
    background:var(--warn2);border:1px solid rgba(245,158,11,.2);
    border-radius:9px;font-size:.8125rem;color:var(--warn);line-height:1.6;
    display:none;
}

/* ── EXPLANATION ────────────────────────────────────────────────────────── */
.explanation{
    margin-top:12px;padding:14px;
    background:var(--sf2);border-left:3px solid var(--ac);
    border-radius:0 9px 9px 0;
    font-size:.8125rem;color:var(--tx2);line-height:1.65;
    display:none;
}
.explanation-label{font-size:.5rem;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--ac);margin-bottom:5px}

/* ── NAVIGATION ─────────────────────────────────────────────────────────── */
.q-nav{display:flex;align-items:center;justify-content:space-between;margin-top:22px;gap:10px}
.nav-btn{
    display:inline-flex;align-items:center;gap:6px;
    padding:10px 20px;border-radius:9px;
    font-family:var(--ff);font-size:.8125rem;font-weight:700;
    transition:all .18s;border:1.5px solid transparent;cursor:pointer;
}
.nav-btn svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
.nav-btn.prev{background:var(--sf);border-color:var(--bd);color:var(--tx2)}
.nav-btn.prev:hover:not([disabled]){background:var(--sf2);color:var(--tx)}
.nav-btn.prev[disabled]{opacity:.3;pointer-events:none}
.nav-btn.next{background:var(--ac);color:var(--dk)}
.nav-btn.next:hover{background:var(--ac2);transform:translateY(-1px)}
.nav-btn.finish{background:linear-gradient(135deg,var(--ac),var(--ac2));color:var(--dk);box-shadow:0 4px 16px rgba(31,226,144,.2)}
.nav-btn.finish:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(31,226,144,.3)}

/* ── UNANSWERED WARNING ─────────────────────────────────────────────────── */
.unanswered-warn{
    display:none;background:var(--warn2);border:1px solid rgba(245,158,11,.25);
    border-radius:9px;padding:11px 14px;font-size:.8125rem;color:var(--warn);
    margin-top:12px;line-height:1.5;
}
.unanswered-warn.show{display:block}

/* ── RESULTS SCREEN ─────────────────────────────────────────────────────── */
.results-screen{display:none;text-align:center;padding:48px 20px}
.results-screen.show{display:block}
.results-badge{
    display:inline-flex;align-items:center;gap:7px;
    padding:6px 14px;border-radius:50px;
    font-size:.6875rem;font-weight:800;text-transform:uppercase;letter-spacing:.6px;
    margin-bottom:20px;
}
.results-badge.pass{background:var(--ac3);color:var(--ac);border:1px solid rgba(31,226,144,.2)}
.results-badge.fail{background:var(--err2);color:var(--err);border:1px solid rgba(239,68,68,.2)}
.results-score{
    font-family:var(--fh);font-size:5.5rem;font-weight:900;
    letter-spacing:-.05em;line-height:1;margin-bottom:4px;
}
.results-score.pass{color:var(--ac)}
.results-score.fail{color:var(--err)}
.results-verdict{font-size:1rem;color:var(--tx2);margin-bottom:30px}
.results-stats{
    display:flex;gap:0;justify-content:center;margin-bottom:36px;
    background:var(--sf);border:1px solid var(--bd);border-radius:14px;
    overflow:hidden;max-width:420px;margin-left:auto;margin-right:auto;
}
.rs-stat{flex:1;padding:18px 12px;text-align:center;border-right:1px solid var(--bd)}
.rs-stat:last-child{border-right:none}
.rs-val{font-family:var(--fh);font-size:1.5rem;font-weight:900;color:var(--tx);letter-spacing:-.03em}
.rs-label{font-size:.5625rem;color:var(--tx3);text-transform:uppercase;letter-spacing:.8px;margin-top:3px}
.results-actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}

/* ── REVIEW SECTION ─────────────────────────────────────────────────────── */
.review-section{margin-top:40px;text-align:left}
.review-title{font-family:var(--fh);font-size:1.25rem;font-weight:900;color:var(--tx);margin-bottom:16px;letter-spacing:-.02em}
.review-card{background:var(--sf);border:1px solid var(--bd);border-radius:12px;padding:18px;margin-bottom:10px}
.review-q-num{font-family:var(--fm);font-size:.5625rem;color:var(--tx3);margin-bottom:6px}
.review-stem{font-size:.875rem;font-weight:600;color:var(--tx);margin-bottom:10px;line-height:1.55}
.review-answer{font-size:.8125rem;padding:6px 10px;border-radius:7px;margin-bottom:4px;display:flex;align-items:center;gap:8px}
.review-answer.correct{background:var(--ac3);color:var(--ac)}
.review-answer.wrong{background:var(--err2);color:var(--err)}
.review-answer.skipped{background:var(--sf2);color:var(--tx3)}
.review-answer svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;flex-shrink:0}

@media(max-width:600px){
    .quiz-wrap{padding:20px 16px 60px}
    .quiz-title{font-size:1.375rem}
    .q-card{padding:18px 16px}
    .preview-banner{padding:8px 14px}
    .preview-banner-text span{display:none}
    .results-score{font-size:4rem}
}
</style>
</head>
<body>

<!-- ── Preview Banner ──────────────────────────────────────────────────── -->
<div class="preview-banner">
    <div class="preview-banner-text">
        <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        <span>Admin Preview Mode — answers are not recorded</span>
    </div>
    <div class="preview-banner-actions">
        <a href="/quizzes/edit.php?id=<?= $quizId ?>" class="pbtn">
            <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Edit Quiz
        </a>
        <a href="/quizzes/results.php?id=<?= $quizId ?>" class="pbtn">
            <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            Results
        </a>
        <button class="pbtn" onclick="window.close()">
            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            Close
        </button>
    </div>
</div>

<!-- ── Quiz ───────────────────────────────────────────────────────────────  -->
<div class="quiz-wrap" id="quizWrap">

    <h1 class="quiz-title"><?= htmlspecialchars($quiz['title']) ?></h1>

    <div class="quiz-meta">
        <div class="quiz-meta-item">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3" stroke-linecap="round"/><line x1="12" y1="17" x2="12.01" y2="17" stroke-width="2.5"/></svg>
            <?= $totalQ ?> question<?= $totalQ !== 1 ? 's' : '' ?>
        </div>
        <?php if ($timeLimit > 0): ?>
        <div class="quiz-meta-item">
            <div class="timer-pill" id="timerPill">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14" stroke-linecap="round"/></svg>
                <span id="timerDisplay"><?= floor($timeLimit / 60) ?>:<?= str_pad($timeLimit % 60, 2, '0', STR_PAD_LEFT) ?></span>
            </div>
        </div>
        <?php endif; ?>
        <div class="quiz-meta-item">Pass: <?= $passScore ?>%</div>
        <?php if ($xpReward > 0): ?>
        <div class="quiz-meta-item">+<?= $xpReward ?> XP</div>
        <?php endif; ?>
    </div>

    <?php if ($instructions): ?>
    <div class="quiz-instructions"><?= $instructions ?></div>
    <?php endif; ?>

    <!-- Progress bar -->
    <div class="progress-bar-wrap" id="progressWrap">
        <div class="progress-bar" id="progressBar" style="width:0%"></div>
    </div>

    <!-- Question dots -->
    <div class="q-dots" id="qDots"></div>

    <!-- Question cards — rendered from JS using data below -->
    <div id="qCards"></div>

    <!-- Unanswered warning (shown when trying to finish with skipped questions) -->
    <div class="unanswered-warn" id="unansweredWarn"></div>

    <!-- Results screen -->
    <div class="results-screen" id="resultsScreen"></div>

</div>

<script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
<script>
// ── Data passed from PHP ──────────────────────────────────────────────────
const QUESTIONS   = <?= json_encode($questionData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
const TOTAL_Q     = <?= $totalQ ?>;
const PASS_SCORE  = <?= $passScore ?>;
const TIME_LIMIT  = <?= $timeLimit ?>;
const SHOW_HINTS  = <?= $showHints ? 'true' : 'false' ?>;
const XP_REWARD   = <?= $xpReward ?>;
const QUIZ_ID     = <?= $quizId ?>;

// ── State ─────────────────────────────────────────────────────────────────
let current   = 0;
let answers   = {};   // { qIdx: { key, correct } }
let flagged   = {};   // { qIdx: bool }
let startTime = Date.now();
let finished  = false;

// ── KaTeX rendering ───────────────────────────────────────────────────────
// Renders $...$ LaTeX in a raw string safely, returns HTML string
function renderMath(raw) {
    if (!raw) return '';
    // Escape HTML entities FIRST, then replace $...$ with rendered KaTeX
    const escaped = raw
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
    return escaped.replace(/\$([^$\n]+)\$/g, (_, math) => {
        try {
            return katex.renderToString(math, { throwOnError: false, displayMode: false });
        } catch {
            return '$' + math + '$';
        }
    });
}

// ── Build all question cards ──────────────────────────────────────────────
function buildCards() {
    const container = document.getElementById('qCards');
    container.innerHTML = '';

    QUESTIONS.forEach((q, i) => {
        const card = document.createElement('div');
        card.className = 'q-card';
        card.id = 'qcard_' + i;

        const optsHtml = q.opts.map(o => `
            <div class="q-opt" id="opt_${i}_${o.key}" onclick="selectOpt(${i}, '${o.key}')">
                <div class="opt-key">${o.key.toUpperCase()}</div>
                <div class="opt-text">${renderMath(o.text)}</div>
            </div>`).join('');

        const hintHtml = (SHOW_HINTS && q.hint) ? `
            <button class="hint-btn" id="hintBtn_${i}" onclick="showHint(${i})">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16" stroke-width="2.5"/></svg>
                Show hint
            </button>
            <div class="hint-box" id="hint_${i}">${renderMath(q.hint)}</div>` : '';

        const expHtml = q.explanation ? `
            <div class="explanation" id="exp_${i}">
                <div class="explanation-label">Explanation</div>
                ${renderMath(q.explanation)}
            </div>` : '';

        const isLast = i === TOTAL_Q - 1;

        card.innerHTML = `
        <div class="q-header">
            <span class="q-number">Question ${i + 1} of ${TOTAL_Q}</span>
            <span class="q-diff-badge ${q.difficulty}">${q.difficulty}</span>
            ${q.points > 1 ? `<span class="q-pts">${q.points} pts</span>` : ''}
            <button class="q-flag-btn" id="flag_${i}" onclick="toggleFlag(${i})" title="Flag for review">
                <svg viewBox="0 0 24 24"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
            </button>
        </div>
        <div class="q-stem">${renderMath(q.stem)}</div>
        <div class="q-options" id="opts_${i}">${optsHtml}</div>
        ${hintHtml}
        ${expHtml}
        <div class="q-nav">
            <button class="nav-btn prev" onclick="goTo(${i - 1})" ${i === 0 ? 'disabled' : ''}>
                <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                Back
            </button>
            ${isLast
                ? `<button class="nav-btn next finish" onclick="tryFinish()">
                    Finish Quiz
                    <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                   </button>`
                : `<button class="nav-btn next" onclick="goTo(${i + 1})">
                    Next
                    <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                   </button>`
            }
        </div>`;

        container.appendChild(card);
    });
}

// ── Dots ──────────────────────────────────────────────────────────────────
function buildDots() {
    const wrap = document.getElementById('qDots');
    wrap.innerHTML = '';
    for (let i = 0; i < TOTAL_Q; i++) {
        const d = document.createElement('div');
        let cls = 'q-dot';
        if (i === current)          cls += ' current';
        else if (answers[i])        cls += ' answered';
        if (flagged[i])             cls += ' flagged';
        d.className   = cls;
        d.textContent = i + 1;
        d.onclick     = () => goTo(i);
        wrap.appendChild(d);
    }
}

// ── Navigation ────────────────────────────────────────────────────────────
function showQ(idx) {
    document.querySelectorAll('.q-card').forEach(c => c.classList.remove('active'));
    const card = document.getElementById('qcard_' + idx);
    if (card) card.classList.add('active');
    current = idx;
    const pct = TOTAL_Q > 0 ? ((idx + 1) / TOTAL_Q) * 100 : 0;
    document.getElementById('progressBar').style.width = pct + '%';
    document.getElementById('unansweredWarn').classList.remove('show');
    buildDots();
    card?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function goTo(idx) {
    if (idx >= 0 && idx < TOTAL_Q && !finished) showQ(idx);
}

// ── Select answer ─────────────────────────────────────────────────────────
function selectOpt(qIdx, key) {
    // Already answered — don't allow re-selection
    if (answers[qIdx]) return;

    const q       = QUESTIONS[qIdx];
    const correct = q.correct_answer;
    const isRight = key === correct;

    answers[qIdx] = { key, correct: isRight };

    // Lock all options for this question
    document.querySelectorAll(`#opts_${qIdx} .q-opt`).forEach(o => o.classList.add('locked'));

    // Flash selected briefly, then reveal correct/wrong
    const selEl = document.getElementById(`opt_${qIdx}_${key}`);
    if (selEl) selEl.classList.add('selected');

    setTimeout(() => {
        if (selEl) {
            selEl.classList.remove('selected');
            selEl.classList.add(isRight ? 'correct' : 'wrong');
        }
        if (!isRight) {
            const corrEl = document.getElementById(`opt_${qIdx}_${correct}`);
            if (corrEl) corrEl.classList.add('correct');
        }
        // Show explanation
        const exp = document.getElementById('exp_' + qIdx);
        if (exp) exp.style.display = 'block';
    }, 380);

    buildDots();
}

// ── Flag ──────────────────────────────────────────────────────────────────
function toggleFlag(idx) {
    flagged[idx] = !flagged[idx];
    const btn = document.getElementById('flag_' + idx);
    if (btn) btn.classList.toggle('flagged', flagged[idx]);
    buildDots();
}

// ── Hint ──────────────────────────────────────────────────────────────────
function showHint(idx) {
    const box = document.getElementById('hint_' + idx);
    const btn = document.getElementById('hintBtn_' + idx);
    if (box) box.style.display = 'block';
    if (btn) btn.style.display = 'none';
}

// ── Try finish (warn about unanswered) ────────────────────────────────────
function tryFinish() {
    const unanswered = TOTAL_Q - Object.keys(answers).length;
    if (unanswered > 0) {
        const warn = document.getElementById('unansweredWarn');
        warn.textContent = `⚠️ You have ${unanswered} unanswered question${unanswered !== 1 ? 's' : ''}. Click Finish again to submit anyway, or go back to answer them.`;
        warn.classList.add('show');
        // Second click within 6 seconds confirms finish
        warn.onclick = () => { warn.classList.remove('show'); finishQuiz(); };
        setTimeout(() => warn.classList.remove('show'), 6000);
    } else {
        finishQuiz();
    }
}

// ── Finish ────────────────────────────────────────────────────────────────
function finishQuiz() {
    if (finished) return;
    finished = true;
    if (timerInterval) clearInterval(timerInterval);

    const correct   = Object.values(answers).filter(a => a.correct).length;
    const answered  = Object.keys(answers).length;
    const score     = TOTAL_Q > 0 ? Math.round(correct / TOTAL_Q * 100) : 0;
    const pass      = score >= PASS_SCORE;
    const elapsed   = Math.round((Date.now() - startTime) / 1000);
    const m         = Math.floor(elapsed / 60);
    const s         = elapsed % 60;

    // Hide quiz UI
    document.getElementById('qCards').style.display    = 'none';
    document.getElementById('qDots').style.display     = 'none';
    document.getElementById('progressWrap').style.display = 'none';
    document.getElementById('unansweredWarn').style.display = 'none';

    // Build review list
    const reviewHtml = QUESTIONS.map((q, i) => {
        const ans = answers[i];
        let statusCls, statusIcon, statusText;
        if (!ans) {
            statusCls  = 'skipped';
            statusIcon = `<svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`;
            statusText = 'Skipped';
        } else if (ans.correct) {
            statusCls  = 'correct';
            statusIcon = `<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>`;
            statusText = `Correct — ${ans.key.toUpperCase()}`;
        } else {
            statusCls  = 'wrong';
            statusIcon = `<svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`;
            const corrKey = q.correct_answer;
            const corrOpt = q.opts.find(o => o.key === corrKey);
            statusText = `Wrong — correct was ${corrKey.toUpperCase()}${corrOpt ? ': ' + corrOpt.text.substring(0, 40) + (corrOpt.text.length > 40 ? '…' : '') : ''}`;
        }
        return `
        <div class="review-card">
            <div class="review-q-num">Q${i + 1}</div>
            <div class="review-stem">${renderMath(q.stem)}</div>
            <div class="review-answer ${statusCls}">${statusIcon} ${statusText}</div>
        </div>`;
    }).join('');

    // Build results screen
    const rs = document.getElementById('resultsScreen');
    rs.innerHTML = `
        <div class="results-badge ${pass ? 'pass' : 'fail'}">
            ${pass ? '✓ Passed' : '✗ Not passed'}
        </div>
        <div class="results-score ${pass ? 'pass' : 'fail'}">${score}%</div>
        <div class="results-verdict">${pass ? '🎉 Great work!' : '📚 Keep practising!'}</div>
        <div class="results-stats">
            <div class="rs-stat">
                <div class="rs-val">${correct}/${TOTAL_Q}</div>
                <div class="rs-label">Correct</div>
            </div>
            <div class="rs-stat">
                <div class="rs-val">${answered}/${TOTAL_Q}</div>
                <div class="rs-label">Answered</div>
            </div>
            <div class="rs-stat">
                <div class="rs-val">${m}m ${String(s).padStart(2,'0')}s</div>
                <div class="rs-label">Time</div>
            </div>
            ${XP_REWARD > 0 ? `<div class="rs-stat"><div class="rs-val">+${XP_REWARD}</div><div class="rs-label">XP</div></div>` : ''}
        </div>
        <div class="results-actions">
            <button class="nav-btn prev" onclick="restartPreview()">
                <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
                Restart
            </button>
            <a href="/quizzes/edit.php?id=${QUIZ_ID}" class="nav-btn next" style="text-decoration:none">
                Back to Editor
                <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
        </div>
        ${TOTAL_Q > 0 ? `<div class="review-section"><div class="review-title">Question Review</div>${reviewHtml}</div>` : ''}
    `;
    rs.classList.add('show');
}

// ── Restart ───────────────────────────────────────────────────────────────
function restartPreview() {
    answers   = {};
    flagged   = {};
    finished  = false;
    startTime = Date.now();
    current   = 0;

    document.getElementById('resultsScreen').classList.remove('show');
    document.getElementById('qCards').style.display     = '';
    document.getElementById('qDots').style.display      = '';
    document.getElementById('progressWrap').style.display = '';

    buildCards();
    buildDots();
    showQ(0);

    // Restart timer if applicable
    if (TIME_LIMIT > 0) startTimer();
}

// ── Timer ─────────────────────────────────────────────────────────────────
let timerInterval = null;
let timeLeft      = TIME_LIMIT;

function startTimer() {
    timeLeft = TIME_LIMIT;
    if (timerInterval) clearInterval(timerInterval);
    timerInterval = setInterval(() => {
        timeLeft--;
        const m   = Math.floor(timeLeft / 60);
        const s   = timeLeft % 60;
        const display = document.getElementById('timerDisplay');
        const pill    = document.getElementById('timerPill');
        if (display) display.textContent = `${m}:${String(s).padStart(2, '0')}`;
        if (pill) pill.classList.toggle('warning', timeLeft <= 60);
        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            finishQuiz();
        }
    }, 1000);
}

// ── Init ──────────────────────────────────────────────────────────────────
buildCards();
buildDots();
showQ(0);
if (TIME_LIMIT > 0) startTimer();
</script>
</body>
</html>