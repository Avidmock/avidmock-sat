<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · Practice Tests — Review Answers
 *  /practice-tests/review.php
 * ═══════════════════════════════════════════════════════════════════
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
Auth::requireStudent();

$userId        = $_SESSION['user_id'];
$user          = User::findById($userId);
$firstName     = explode(' ', $user['name'] ?? 'Student')[0];
$currentStreak = StudyStreak::getCurrent($userId);

$attemptId = (int)($_GET['attempt_id'] ?? 0);
if (!$attemptId) { header('Location: /practice-tests/'); exit; }

$attempt = Database::fetch(
    "SELECT a.*,t.title,t.id AS test_id
     FROM practice_test_attempts a
     JOIN practice_tests t ON t.id=a.test_id
     WHERE a.id=? AND a.user_id=?",
    [$attemptId, $userId]
);
if (!$attempt || $attempt['status'] !== 'submitted') { header('Location: /practice-tests/'); exit; }

/* Answers grouped by section */
$sections = Database::fetchAll(
    "SELECT * FROM practice_test_sections WHERE test_id=? ORDER BY sort_order ASC",
    [$attempt['test_id']]
);
$sectionAnswers = [];
foreach ($sections as $sec) {
    $answers = Database::fetchAll(
        "SELECT pta.*,q.stem,q.stimulus,q.stimulus_type,q.choice_a,q.choice_b,q.choice_c,q.choice_d,
                q.correct_answer,q.explanation,q.difficulty,q.domain,q.skill,q.subject,q.question_type
         FROM practice_test_section_questions ptsq
         JOIN questions q ON q.id=ptsq.question_id
         LEFT JOIN practice_test_answers pta ON pta.question_id=q.id AND pta.attempt_id=?
         WHERE ptsq.section_id=? ORDER BY ptsq.sort_order ASC",
        [$attemptId, $sec['id']]
    );
    $sectionAnswers[] = ['section' => $sec, 'answers' => $answers];
}

$totalQ  = array_sum(array_map(fn($sa) => count($sa['answers']), $sectionAnswers));
$correct = array_sum(array_map(fn($sa) => count(array_filter($sa['answers'], fn($a) => $a['is_correct'])), $sectionAnswers));
$wrong   = $totalQ - $correct;
$accuracy = $totalQ > 0 ? round($correct / $totalQ * 100) : 0;

$activePage = 'practice_tests';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Review: <?= htmlspecialchars($attempt['title']) ?> — Avidmock SAT</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<link rel="icon" type="image/png" sizes="16x16"   href="/favicon-16x16.png">
<link rel="icon" type="image/png" sizes="32x32"   href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="192x192" href="/android-chrome-192x192.png">
<link rel="icon" type="image/png" sizes="512x512" href="/android-chrome-512x512.png">
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800;1,9..40,400&family=DM+Mono:wght@400;500&family=Fraunces:opsz,wght@9..144,700;9..144,900&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════════
   DESIGN TOKENS
═══════════════════════════════════════════════════ */
:root {
    --dk:  #0f2420;
    --dk2: #143230;
    --dk3: #1a3f3c;
    --ac:  #1fe290;
    --ac2: #13c47a;
    --tx:  #0d1f1c;
    --tx2: #374151;
    --tx3: #6b7280;
    --tx4: #9ca3af;
    --bg:  #f3f7f6;
    --bg2: #ffffff;
    --bd:  #dde8e6;
    --bd2: #ccd8d6;
    --ok:  #10b981;
    --err: #ef4444;
    --warn: #f59e0b;
    --math: #6366f1;
    --ff: 'DM Sans', -apple-system, sans-serif;
    --fh: 'Fraunces', Georgia, serif;
    --fm: 'DM Mono', monospace;
    --sidebar-w: 256px;
    --topbar-h:  64px;
    --r:    14px;
    --r-sm: 10px;
    --r-lg: 18px;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: var(--ff); -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; background: var(--bg); color: var(--tx); min-height: 100vh; }
a { text-decoration: none; color: inherit; }
button { font-family: var(--ff); cursor: pointer; }

/* ─────────────────────────────────────────────
   SCROLL REVEAL
───────────────────────────────────────────── */
.sr { opacity: 0; transform: translateY(16px); transition: opacity .5s cubic-bezier(.16,1,.3,1), transform .5s cubic-bezier(.16,1,.3,1); }
.sr.v { opacity: 1; transform: none; }
.d1 { transition-delay: .07s; }
.d2 { transition-delay: .14s; }

/* ─────────────────────────────────────────────
   LAYOUT
───────────────────────────────────────────── */
.sidebar-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.45); z-index: 150;
    opacity: 0; transition: opacity .3s; pointer-events: none;
}
.sidebar-overlay.show { opacity: 1; pointer-events: all; display: block; }

.main-content {
    margin-left: var(--sidebar-w); margin-top: var(--topbar-h);
    padding: 36px 28px 80px;
}
.max-w { max-width: 900px; margin: 0 auto; }

/* ─────────────────────────────────────────────
   BREADCRUMB
───────────────────────────────────────────── */
.breadcrumb {
    display: flex; align-items: center; gap: 6px;
    font-size: .8125rem; color: var(--tx3); margin-bottom: 24px;
}
.breadcrumb a { color: var(--tx3); transition: color .15s; }
.breadcrumb a:hover { color: var(--ac); }
.breadcrumb-sep { opacity: .4; }
.breadcrumb-cur { color: var(--tx); font-weight: 600; }

/* ─────────────────────────────────────────────
   SUMMARY BAR
───────────────────────────────────────────── */
.summary-bar {
    background: var(--dk2); border-radius: 20px; padding: 28px 36px;
    color: #fff; margin-bottom: 24px;
    display: flex; align-items: center; gap: 28px; flex-wrap: wrap;
    position: relative; overflow: hidden;
}
.summary-bar::before {
    content: ''; position: absolute; inset: 0; pointer-events: none;
    background-image:
        linear-gradient(rgba(31,226,144,.015) 1px, transparent 1px),
        linear-gradient(90deg, rgba(31,226,144,.015) 1px, transparent 1px);
    background-size: 32px 32px;
}
.summary-stat { text-align: center; position: relative; z-index: 1; }
.summary-stat-val {
    font-family: var(--fm); font-size: 2.5rem; font-weight: 700;
    letter-spacing: -.05em; line-height: 1;
}
.summary-stat-label {
    font-size: .5625rem; color: rgba(255,255,255,.35); margin-top: 5px;
    font-weight: 700; text-transform: uppercase; letter-spacing: .1em;
}
.sum-div { width: 1px; height: 56px; background: rgba(255,255,255,.08); flex-shrink: 0; }
.summary-right { flex: 1; min-width: 200px; position: relative; z-index: 1; }
.summary-title { font-size: 1.125rem; font-weight: 800; letter-spacing: -.02em; margin-bottom: 4px; }
.summary-sub { font-size: .8125rem; color: rgba(255,255,255,.4); }
.summary-acc-bar { height: 5px; background: rgba(255,255,255,.08); border-radius: 3px; overflow: hidden; margin-top: 10px; }
.summary-acc-fill { height: 100%; background: var(--ac); border-radius: 3px; transition: width 1.2s cubic-bezier(.16,1,.3,1); width: 0%; }

/* ─────────────────────────────────────────────
   FILTER BAR
───────────────────────────────────────────── */
.filter-bar { display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; }
.filter-btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 7px 16px; border-radius: 20px;
    border: 1.5px solid var(--bd); background: var(--bg2);
    font-family: var(--ff); font-size: .8125rem; font-weight: 600;
    color: var(--tx3); cursor: pointer; transition: all .15s;
}
.filter-btn svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }
.filter-btn:hover { border-color: var(--bd2); color: var(--tx); background: var(--bg); }
.filter-btn.active { background: var(--ac); border-color: var(--ac); color: var(--dk); }
.filter-count {
    font-family: var(--fm); font-size: .625rem; font-weight: 700;
    padding: 1px 6px; border-radius: 10px;
    background: rgba(0,0,0,.07); color: inherit;
}
.filter-btn.active .filter-count { background: rgba(0,0,0,.1); }

/* ─────────────────────────────────────────────
   SECTION HEADER
───────────────────────────────────────────── */
.section-hdr {
    display: flex; align-items: center; gap: 12px;
    margin-bottom: 14px; margin-top: 28px;
}
.section-hdr:first-of-type { margin-top: 0; }
.section-hdr-title { font-size: 1rem; font-weight: 800; color: var(--tx); letter-spacing: -.02em; }
.section-hdr-tag {
    font-size: .5625rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .08em; padding: 3px 10px; border-radius: 7px;
}
.tag-rw   { background: rgba(31,226,144,.08); color: var(--ac2); border: 1px solid rgba(31,226,144,.2); }
.tag-math { background: rgba(99,102,241,.08); color: var(--math); border: 1px solid rgba(99,102,241,.2); }
.section-stats { margin-left: auto; font-family: var(--fm); font-size: .8125rem; color: var(--tx3); }

/* ─────────────────────────────────────────────
   QUESTION CARDS
───────────────────────────────────────────── */
.q-card {
    background: var(--bg2); border: 1px solid var(--bd);
    border-radius: 16px; margin-bottom: 10px; overflow: hidden;
    transition: box-shadow .2s;
}
.q-card:hover { box-shadow: 0 4px 20px rgba(15,36,32,.06); }

/* Status left-border accent */
.q-card[data-correct="1"] { border-left: 3px solid rgba(31,226,144,.3); }
.q-card[data-correct="0"] { border-left: 3px solid rgba(239,68,68,.25); }
.q-card[data-skip="1"]    { border-left: 3px solid rgba(107,114,128,.2); }

.q-card-hd {
    display: flex; align-items: center; gap: 14px;
    padding: 15px 20px; cursor: pointer; user-select: none;
}

/* Number badge */
.q-num-badge {
    width: 34px; height: 34px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-family: var(--fm); font-size: .8125rem; font-weight: 700; flex-shrink: 0;
}
.badge-correct { background: rgba(31,226,144,.12); color: var(--ac2); }
.badge-wrong   { background: rgba(239,68,68,.1);   color: var(--err); }
.badge-skip    { background: rgba(107,114,128,.1);  color: var(--tx3); }

/* Status row */
.q-hd-info { flex: 1; min-width: 0; }
.q-hd-status { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.q-status-icon { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }
.q-status-txt { font-size: .8125rem; font-weight: 700; }
.status-correct { color: var(--ac2); }
.status-wrong   { color: var(--err); }
.status-skip    { color: var(--tx3); }

/* Tags */
.q-tags { display: flex; gap: 6px; margin-top: 5px; flex-wrap: wrap; }
.q-tag {
    font-size: .5625rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .06em; padding: 2px 8px; border-radius: 5px;
}

/* Expand chevron */
.expand-arrow {
    width: 18px; height: 18px; stroke: var(--tx3); fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
    transition: transform .25s; flex-shrink: 0;
}
.q-card.open .expand-arrow { transform: rotate(180deg); }

/* Card body */
.q-card-body { display: none; border-top: 1px solid var(--bd); }
.q-card.open .q-card-body { display: block; }

/* ─────────────────────────────────────────────
   PASSAGE SPLIT LAYOUT
───────────────────────────────────────────── */
.review-split {
    display: grid; grid-template-columns: 1fr 1fr;
    border-bottom: 1px solid var(--bd);
}
.review-passage {
    padding: 24px 28px; border-right: 1px solid var(--bd);
    font-size: .84375rem; line-height: 1.85; color: var(--tx2);
}
.passage-lbl {
    font-size: .5625rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: .12em; color: var(--tx3); margin-bottom: 12px;
    display: flex; align-items: center; gap: 8px;
}
.passage-lbl::after { content: ''; flex: 1; height: 1px; background: var(--bd); }
.review-question-side { padding: 24px 28px; }

/* Full-width body (math / no passage) */
.q-body-full { padding: 24px 28px; }

/* Stem */
.q-stem-review { font-size: .9375rem; line-height: 1.7; color: var(--tx); margin-bottom: 16px; }

/* Choices */
.q-choices-review { display: flex; flex-direction: column; gap: 7px; margin-bottom: 16px; }
.choice-row {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 11px 16px; border-radius: 10px;
    border: 1.5px solid var(--bd); font-size: .875rem;
    transition: border-color .15s;
}
.choice-row.is-correct      { border-color: var(--ac);   background: rgba(31,226,144,.04); }
.choice-row.is-wrong        { border-color: var(--err);  background: rgba(239,68,68,.04); }
.choice-row.is-correct-math { border-color: #818cf8;     background: rgba(99,102,241,.04); }

.choice-ltr {
    width: 26px; height: 26px; border-radius: 50%;
    border: 1.5px solid currentColor; display: flex; align-items: center; justify-content: center;
    font-family: var(--fm); font-size: .6875rem; font-weight: 700; flex-shrink: 0;
}
.choice-row.is-correct      .choice-ltr { background: var(--ac);   border-color: var(--ac);   color: var(--dk); }
.choice-row.is-wrong        .choice-ltr { background: var(--err);  border-color: var(--err);  color: #fff; }
.choice-row.is-correct-math .choice-ltr { background: #818cf8;     border-color: #818cf8;     color: #fff; }

.choice-text { font-size: .875rem; color: var(--tx2); flex: 1; }
.choice-label {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: .625rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em;
    margin-left: 4px; flex-shrink: 0;
}
.choice-label svg { width: 11px; height: 11px; stroke: currentColor; fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }

/* SPR answer box */
.spr-answer {
    padding: 12px 16px; background: var(--bg); border-radius: 10px;
    font-size: .875rem; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.spr-val { font-family: var(--fm); font-weight: 700; }

/* Explanation */
.explanation-box {
    background: rgba(15,36,32,.025); border-left: 3px solid var(--ac);
    padding: 16px 20px; font-size: .875rem; color: var(--tx2); line-height: 1.7;
}
.explanation-box.math-exp { border-left-color: var(--math); }
.exp-header {
    display: flex; align-items: center; gap: 7px;
    font-size: .5625rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: .1em; color: var(--dk); margin-bottom: 10px;
}
.exp-header svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.exp-header.math-header { color: var(--math); }

/* Tutor link */
.tutor-link {
    display: inline-flex; align-items: center; gap: 6px;
    margin: 14px 0 0; padding: 7px 14px; border-radius: 8px;
    background: rgba(31,226,144,.07); border: 1px solid rgba(31,226,144,.2);
    color: var(--ac2); font-size: .75rem; font-weight: 700;
    text-decoration: none; transition: background .18s;
}
.tutor-link:hover { background: rgba(31,226,144,.14); }
.tutor-link svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

/* Stimulus (math) block */
.stimulus-block {
    background: var(--bg); border-radius: 12px;
    padding: 16px 20px; margin-bottom: 14px;
    font-size: .84375rem; line-height: 1.85; color: var(--tx2);
}
.stem-block {
    background: var(--bg); border-radius: 12px;
    padding: 16px 20px; margin-bottom: 14px;
}

/* ─────────────────────────────────────────────
   BOTTOM NAV
───────────────────────────────────────────── */
.bottom-nav { margin-top: 32px; display: flex; gap: 12px; flex-wrap: wrap; }
.btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 12px 24px; border-radius: 12px;
    font-family: var(--ff); font-size: .875rem; font-weight: 700;
    text-decoration: none; transition: all .18s; border: none; cursor: pointer;
}
.btn svg { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.btn-primary { background: var(--dk2); color: #fff; }
.btn-primary:hover { background: var(--dk3); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(15,50,48,.15); }
.btn-primary svg { stroke: var(--ac); }
.btn-ghost { background: var(--bg2); color: var(--tx2); border: 1.5px solid var(--bd); }
.btn-ghost:hover { border-color: var(--ac); color: var(--dk2); }

/* ─────────────────────────────────────────────
   RESPONSIVE
───────────────────────────────────────────── */
@media (max-width: 1024px) { :root { --sidebar-w: 220px; } }
@media (max-width: 900px) {
    .sidebar { transform: translateX(-100%); }
    .sidebar.open { transform: translateX(0); }
    .sidebar-overlay { display: block; pointer-events: none; }
    .sidebar-overlay.show { pointer-events: all; }
    .main-content { margin-left: 0; padding: 24px 16px 80px; }
    .topbar { left: 0; padding: 0 16px; }
    .topbar-ham { display: flex !important; }
}
@media (max-width: 700px) {
    .review-split { grid-template-columns: 1fr; }
    .review-passage { border-right: none; border-bottom: 1px solid var(--bd); max-height: 200px; overflow-y: auto; }
    .summary-bar { gap: 16px; padding: 24px 20px; }
    .sum-div { display: none; }
}
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/sidebar.php'; ?>

<header style="position:fixed;top:0;left:var(--sidebar-w);right:0;height:var(--topbar-h);background:rgba(243,247,246,.94);backdrop-filter:blur(12px);border-bottom:1px solid var(--bd);display:flex;align-items:center;padding:0 28px;gap:16px;z-index:100;">
    <button onclick="openSidebar()" id="hamBtn"
        style="display:none;width:38px;height:38px;border-radius:10px;border:1.5px solid var(--bd);background:#fff;align-items:center;justify-content:center;cursor:pointer;flex-direction:column;gap:4px;padding:10px;">
        <span style="display:block;height:2px;background:var(--tx);border-radius:1px;width:100%"></span>
        <span style="display:block;height:2px;background:var(--tx);border-radius:1px;width:100%"></span>
        <span style="display:block;height:2px;background:var(--tx);border-radius:1px;width:100%"></span>
    </button>
    <div style="flex:1">
        <div style="font-size:1rem;font-weight:700;color:var(--tx)">Review Answers</div>
        <div style="font-size:.75rem;color:var(--tx3);margin-top:1px">
            <a href="/practice-tests/results.php?attempt_id=<?= $attemptId ?>" style="color:var(--tx3)">Results</a>
            <span style="margin:0 6px;opacity:.4">›</span>Review
        </div>
    </div>
</header>

<main class="main-content">
<div class="max-w">

    <!-- Breadcrumb -->
    <nav class="breadcrumb sr">
        <a href="/practice-tests/">Practice Tests</a>
        <span class="breadcrumb-sep">›</span>
        <a href="/practice-tests/results.php?attempt_id=<?= $attemptId ?>">Results</a>
        <span class="breadcrumb-sep">›</span>
        <span class="breadcrumb-cur">Review</span>
    </nav>

    <!-- ── SUMMARY BAR ── -->
    <div class="summary-bar sr">
        <div class="summary-stat">
            <div class="summary-stat-val" style="color:var(--ac)"><?= $correct ?></div>
            <div class="summary-stat-label">Correct</div>
        </div>
        <div class="sum-div"></div>
        <div class="summary-stat">
            <div class="summary-stat-val" style="color:#fca5a5"><?= $wrong ?></div>
            <div class="summary-stat-label">Incorrect</div>
        </div>
        <div class="sum-div"></div>
        <div class="summary-stat">
            <div class="summary-stat-val"><?= $totalQ ?></div>
            <div class="summary-stat-label">Total</div>
        </div>
        <div class="sum-div"></div>
        <div class="summary-right">
            <div class="summary-title"><?= htmlspecialchars($attempt['title']) ?></div>
            <div class="summary-sub"><?= $accuracy ?>% accuracy · <?= date('M j, Y', strtotime($attempt['submitted_at'] ?? 'now')) ?></div>
            <div class="summary-acc-bar">
                <div class="summary-acc-fill" data-w="<?= $accuracy ?>"></div>
            </div>
        </div>
    </div>

    <!-- ── FILTER BAR ── -->
    <div class="filter-bar sr d1">
        <button class="filter-btn active" data-filter="all" onclick="setFilter(this,'all')">
            <svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            All <span class="filter-count"><?= $totalQ ?></span>
        </button>
        <button class="filter-btn" data-filter="correct" onclick="setFilter(this,'correct')">
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            Correct <span class="filter-count"><?= $correct ?></span>
        </button>
        <button class="filter-btn" data-filter="wrong" onclick="setFilter(this,'wrong')">
            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            Incorrect <span class="filter-count"><?= $wrong ?></span>
        </button>
    </div>

    <!-- ── QUESTIONS BY SECTION ── -->
    <div id="reviewWrap" class="sr d2">
    <?php
    $globalN = 0;
    foreach ($sectionAnswers as $saGroup):
        $sec        = $saGroup['section'];
        $answers    = $saGroup['answers'];
        $isMath     = str_contains(strtolower($sec['subject'] ?? ''), 'math');
        $secCorrect = count(array_filter($answers, fn($a) => $a['is_correct']));
        $secTotal   = count($answers);
        $tagCls     = $isMath ? 'tag-math' : 'tag-rw';
        $tagLabel   = $isMath ? 'Math' : 'R&amp;W';
    ?>
    <div class="section-hdr">
        <div class="section-hdr-title"><?= htmlspecialchars($sec['title']) ?></div>
        <span class="section-hdr-tag <?= $tagCls ?>"><?= $tagLabel ?> · Module <?= $sec['module'] ?? 1 ?></span>
        <div class="section-stats"><?= $secCorrect ?>/<?= $secTotal ?> correct</div>
    </div>

    <?php foreach ($answers as $qi => $ans):
        $globalN++;
        $isCorrect  = (bool)$ans['is_correct'];
        $isSkipped  = !isset($ans['user_answer']) || $ans['user_answer'] === null;
        $isSPR      = ($ans['question_type'] ?? 'mcq') === 'spr';
        $correctAns = strtoupper($ans['correct_answer'] ?? '');
        $userAns    = strtoupper($ans['user_answer'] ?? '');
        $choices    = ['A' => $ans['choice_a'], 'B' => $ans['choice_b'], 'C' => $ans['choice_c'], 'D' => $ans['choice_d']];
        $hasPassage = !empty($ans['stimulus']) && ($ans['stimulus_type'] ?? '') !== 'none';
        $diff       = $ans['difficulty'] ?? 'medium';
        $diffBg     = $diff === 'easy' ? 'rgba(31,226,144,.08)'  : ($diff === 'hard' ? 'rgba(239,68,68,.08)'  : 'rgba(245,158,11,.08)');
        $diffFg     = $diff === 'easy' ? 'var(--ac2)'            : ($diff === 'hard' ? 'var(--err)'           : 'var(--warn)');

        if ($isSkipped) {
            $statusClass = 'badge-skip';
            $statusTxt   = 'No Answer';
            $txtClass    = 'status-skip';
            $statusIcon  = '<svg class="q-status-icon" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/></svg>';
        } elseif ($isCorrect) {
            $statusClass = 'badge-correct';
            $statusTxt   = 'Correct';
            $txtClass    = 'status-correct';
            $statusIcon  = '<svg class="q-status-icon" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>';
        } else {
            $statusClass = 'badge-wrong';
            $statusTxt   = 'Incorrect — you answered ' . ($userAns ?: 'nothing');
            $txtClass    = 'status-wrong';
            $statusIcon  = '<svg class="q-status-icon" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
        }
    ?>
    <div class="q-card <?= !$isCorrect ? 'open' : '' ?>"
         data-correct="<?= $isCorrect ? '1' : '0' ?>"
         data-skip="<?= $isSkipped ? '1' : '0' ?>"
         id="qcard-<?= $globalN ?>">

        <div class="q-card-hd" onclick="toggleCard(<?= $globalN ?>)">
            <div class="q-num-badge <?= $statusClass ?>"><?= $globalN ?></div>
            <div class="q-hd-info">
                <div class="q-hd-status">
                    <span class="<?= $txtClass ?>"><?= $statusIcon ?></span>
                    <span class="q-status-txt <?= $txtClass ?>"><?= $statusTxt ?></span>
                </div>
                <div class="q-tags">
                    <?php if ($ans['domain']): ?>
                    <span class="q-tag" style="background:var(--bg);color:var(--tx3);border:1px solid var(--bd)"><?= htmlspecialchars($ans['domain']) ?></span>
                    <?php endif; ?>
                    <span class="q-tag" style="background:<?= $diffBg ?>;color:<?= $diffFg ?>"><?= ucfirst($diff) ?></span>
                </div>
            </div>
            <svg class="expand-arrow" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
        </div>

        <div class="q-card-body">
        <?php

        /* ── Correct / wrong icon SVGs for choice labels ── */
        $svgCheck = '<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>';
        $svgX     = '<svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';

        /* ── Open-book SVG for explanation header ── */
        $svgBook = '<svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>';

        /* ── Sparkle SVG for tutor link ── */
        $svgSparkle = '<svg viewBox="0 0 24 24" style="width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round"><path d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>';

        /* Helper: render choices */
        $renderChoices = function() use ($choices, $correctAns, $userAns, $isCorrect, $isMath, $svgCheck, $svgX) {
            echo '<div class="q-choices-review">';
            foreach ($choices as $letter => $text) {
                if (empty($text)) continue;
                $cls = '';
                if ($letter === $correctAns)          $cls = $isMath ? 'is-correct-math' : 'is-correct';
                elseif ($letter === $userAns && !$isCorrect) $cls = 'is-wrong';
                $ltrColor = $cls === 'is-correct' ? 'var(--ac2)' : ($cls === 'is-correct-math' ? '#818cf8' : ($cls === 'is-wrong' ? 'var(--err)' : 'var(--tx3)'));
                echo '<div class="choice-row ' . $cls . '">';
                echo '<div class="choice-ltr"' . ($cls ? '' : ' style="color:' . $ltrColor . ';border-color:' . $ltrColor . '"') . '>' . $letter . '</div>';
                echo '<div class="choice-text">' . htmlspecialchars($text) . '</div>';
                if ($letter === $correctAns):
                    $lblColor = $isMath ? '#818cf8' : 'var(--ac2)';
                    echo '<span class="choice-label" style="color:' . $lblColor . '">' . $svgCheck . ' Correct</span>';
                endif;
                if ($letter === $userAns && !$isCorrect):
                    echo '<span class="choice-label" style="color:var(--err)">' . $svgX . ' Yours</span>';
                endif;
                echo '</div>';
            }
            echo '</div>';
        };

        /* Helper: render SPR answer */
        $renderSPR = function() use ($correctAns, $userAns, $isCorrect, $isMath) {
            $valColor = $isMath ? '#818cf8' : 'var(--ac2)';
            echo '<div class="spr-answer">';
            echo '<span><strong>Correct:</strong></span> <span class="spr-val" style="color:' . $valColor . '">' . htmlspecialchars($correctAns) . '</span>';
            if (!$isCorrect):
                echo ' <span style="color:var(--tx3)">·</span> <span><strong>Yours:</strong></span> <span class="spr-val" style="color:var(--err)">' . htmlspecialchars($userAns ?: '—') . '</span>';
            endif;
            echo '</div>';
        };

        /* Helper: render explanation */
        $renderExp = function() use ($ans, $isMath, $svgBook, $svgSparkle, $attemptId) {
            if (!empty($ans['explanation'])):
                $expCls    = $isMath ? 'math-exp' : '';
                $hdrCls    = $isMath ? 'math-header' : '';
                echo '<div class="explanation-box ' . $expCls . '">';
                echo '<div class="exp-header ' . $hdrCls . '">' . $svgBook . ' Explanation</div>';
                echo nl2br(htmlspecialchars($ans['explanation']));
                echo '</div>';
            endif;
            echo '<a href="/tutor/?question_id=' . ($ans['question_id'] ?? 0) . '" class="tutor-link">' . $svgSparkle . ' Ask AI Tutor</a>';
        };

        if ($hasPassage && !$isMath): ?>
        <!-- Passage split -->
        <div class="review-split">
            <div class="review-passage">
                <div class="passage-lbl">Passage</div>
                <?= nl2br(htmlspecialchars($ans['stimulus'])) ?>
            </div>
            <div class="review-question-side">
                <div class="q-stem-review"><?= nl2br(htmlspecialchars($ans['stem'])) ?></div>
                <?php if (!$isSPR): $renderChoices(); else: $renderSPR(); endif; ?>
                <?php $renderExp(); ?>
            </div>
        </div>

        <?php else: ?>
        <!-- Full-width body -->
        <div class="q-body-full">
            <?php if ($hasPassage): ?>
            <div class="stimulus-block"><?= nl2br(htmlspecialchars($ans['stimulus'])) ?></div>
            <?php endif; ?>
            <div class="stem-block q-stem-review"><?= nl2br(htmlspecialchars($ans['stem'])) ?></div>
            <?php if (!$isSPR): $renderChoices(); else: $renderSPR(); endif; ?>
            <?php $renderExp(); ?>
        </div>
        <?php endif; ?>

        </div><!-- /q-card-body -->
    </div><!-- /q-card -->
    <?php endforeach; ?>
    <?php endforeach; ?>
    </div><!-- /reviewWrap -->

    <!-- ── BOTTOM NAV ── -->
    <div class="bottom-nav">
        <a href="/practice-tests/results.php?attempt_id=<?= $attemptId ?>" class="btn btn-primary">
            <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Back to Results
        </a>
        <a href="/practice-tests/" class="btn btn-ghost">
            <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            All Tests
        </a>
    </div>

</div><!-- /max-w -->
</main>

<script>
(function () {
    'use strict';

    /* Scroll reveal */
    var io = new IntersectionObserver(function (e) {
        e.forEach(function (n) { if (n.isIntersecting) { n.target.classList.add('v'); io.unobserve(n.target); } });
    }, { threshold: .04, rootMargin: '0px 0px -16px 0px' });
    document.querySelectorAll('.sr').forEach(function (el) { io.observe(el); });

    /* Animated bars */
    var bo = new IntersectionObserver(function (e) {
        e.forEach(function (n) {
            if (n.isIntersecting) {
                setTimeout(function () { n.target.style.width = (n.target.dataset.w || 0) + '%'; }, 200);
                bo.unobserve(n.target);
            }
        });
    }, { threshold: .1 });
    document.querySelectorAll('[data-w]').forEach(function (b) { bo.observe(b); });

    /* Sidebar */
    window.openSidebar = function () {
        var sb = document.getElementById('sidebar');
        var ov = document.getElementById('sidebarOverlay');
        if (sb) sb.classList.add('open');
        if (ov) { ov.style.display = 'block'; ov.classList.add('show'); }
        document.body.style.overflow = 'hidden';
    };
    window.closeSidebar = function () {
        var sb = document.getElementById('sidebar');
        var ov = document.getElementById('sidebarOverlay');
        if (sb) sb.classList.remove('open');
        if (ov) ov.classList.remove('show');
        document.body.style.overflow = '';
    };

    /* Show hamburger on mobile */
    function checkWidth() {
        var ham = document.getElementById('hamBtn');
        if (ham) ham.style.display = window.innerWidth <= 900 ? 'flex' : 'none';
    }
    checkWidth();
    window.addEventListener('resize', checkWidth);

    /* Toggle question card */
    window.toggleCard = function (n) {
        var c = document.getElementById('qcard-' + n);
        if (c) c.classList.toggle('open');
    };

    /* Filter */
    window.setFilter = function (btn, f) {
        document.querySelectorAll('.filter-btn').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        document.querySelectorAll('.q-card').forEach(function (c) {
            if (f === 'all')     c.style.display = '';
            else if (f === 'correct') c.style.display = c.dataset.correct === '1' ? '' : 'none';
            else                 c.style.display = c.dataset.correct === '0' ? '' : 'none';
        });
    };

    /* Auto-open incorrect cards */
    document.querySelectorAll('.q-card[data-correct="0"]').forEach(function (c) { c.classList.add('open'); });

    /* Cmd/Ctrl+K */
    document.addEventListener('keydown', function (e) {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            if (window.tbOpenSearch) tbOpenSearch();
        }
    });
}());
</script>
</body>
</html>