<?php
/**
 * /schedule/spaced-review.php — Spaced Repetition Review Session
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Schedule.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/StudyStreak.php';

Auth::requireStudent();
$userId    = (int)$_SESSION['user_id'];
$user      = User::findById($userId);
$firstName = explode(' ', $user['name'] ?? 'Student')[0];

/* ── Handle AJAX review submission ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json; charset=utf-8');

    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $itemId  = (int)($body['item_id'] ?? 0);
    $quality = (int)($body['quality'] ?? 3);

    if ($itemId <= 0 || $quality < 0 || $quality > 5) {
        echo json_encode(['success' => false, 'error' => 'Invalid input']);
        exit;
    }

    $result = Schedule::processSpacedReview($userId, $itemId, $quality);

    try {
        StudyStreak::update($userId);
    } catch (Throwable $e) {
        error_log('spaced-review streak: ' . $e->getMessage());
    }

    echo json_encode(['success' => !empty($result), 'data' => $result]);
    exit;
}

/* ── Load due items ── */
$dueItems      = Schedule::getSpacedReviewDue($userId);
$stats         = Schedule::getSpacedReviewStats($userId);
$streak        = StudyStreak::get($userId);
$totalDue      = count($dueItems);
$currentStreak = (int)($streak['current_streak'] ?? 0);

$activePage  = 'schedule';
$topbarTitle = 'Spaced Review';
$topbarSub   = 'Schedule · Review';

$topbarExtra = $totalDue > 0
    ? '<div class="topbar-progress" title="Session progress"><div class="topbar-progress-fill" id="topbarProgress" style="width:0%"></div></div>'
    : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Spaced Review — Avidmock SAT</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<link rel="icon" type="image/png" sizes="16x16"   href="/favicon-16x16.png">
<link rel="icon" type="image/png" sizes="32x32"   href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="192x192" href="/android-chrome-192x192.png">
<link rel="icon" type="image/png" sizes="512x512" href="/android-chrome-512x512.png">
<link rel="apple-touch-icon" sizes="180x180"      href="/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800;1,9..40,400&family=DM+Mono:wght@400;500&family=Fraunces:opsz,wght@9..144,700;9..144,900&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════════
   DESIGN TOKENS
═══════════════════════════════════════════════════ */
:root {
    --dk:  #143230;
    --dk2: #1a3f3c;
    --dk3: #0a1a18;
    --ac:  #1fe290;
    --ac2: #13c47a;
    --tx:  #0d1f1c;
    --tx2: #374151;
    --tx3: #6b7280;
    --tx4: #9ca3af;
    --bg:  #f7faf9;
    --bg2: #ffffff;
    --bd:  #e2ebe9;
    --bd2: #d1d9d6;
    --ok:  #10b981;
    --err: #ef4444;
    --warn: #f59e0b;
    --ff: 'DM Sans', -apple-system, sans-serif;
    --fh: 'Fraunces', Georgia, serif;
    --fm: 'DM Mono', monospace;
    --sidebar-w: 260px;
    --topbar-h:  64px;
    --r:    14px;
    --r-sm: 10px;
    --r-lg: 18px;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body { font-family: var(--ff); -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; background: var(--bg); color: var(--tx); min-height: 100vh; overflow-x: hidden; }
a { text-decoration: none; color: inherit; }
button { font-family: var(--ff); cursor: pointer; }

/* ─────────────────────────────────────────────
   SCROLL REVEAL
───────────────────────────────────────────── */
.sr { opacity: 0; transform: translateY(18px); transition: opacity .5s cubic-bezier(.16,1,.3,1), transform .5s cubic-bezier(.16,1,.3,1); }
.sr.v { opacity: 1; transform: none; }
.d1 { transition-delay: .06s; }
.d2 { transition-delay: .12s; }
.d3 { transition-delay: .18s; }

/* ─────────────────────────────────────────────
   LAYOUT
───────────────────────────────────────────── */
.main { margin-left: var(--sidebar-w); margin-top: var(--topbar-h); padding: 32px 28px 80px; max-width: 1000px; }
.sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 250; opacity: 0; transition: opacity .28s; pointer-events: none; }
.sidebar-overlay.show { opacity: 1; pointer-events: all; display: block; }

/* ─────────────────────────────────────────────
   TOPBAR PROGRESS
───────────────────────────────────────────── */
.topbar-progress { flex: 1; max-width: 200px; height: 6px; background: var(--bd); border-radius: 3px; overflow: hidden; }
.topbar-progress-fill { height: 100%; background: var(--ac); border-radius: 3px; transition: width .5s cubic-bezier(.16,1,.3,1); }

/* ─────────────────────────────────────────────
   STATS BAR
───────────────────────────────────────────── */
.stats-bar { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 28px; }
.stat-chip {
    background: var(--bg2); border: 1px solid var(--bd);
    border-radius: var(--r); padding: .875rem 1.125rem; text-align: center;
    transition: transform .18s, box-shadow .18s;
}
.stat-chip:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(20,50,48,.06); }
.stat-chip-icon {
    width: 34px; height: 34px; border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 8px;
}
.stat-chip-icon svg { width: 16px; height: 16px; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.ico-ac     { background: rgba(31,226,144,.08); } .ico-ac     svg { stroke: var(--ac2); }
.ico-dk     { background: rgba(20,50,48,.06);   } .ico-dk     svg { stroke: var(--dk2); }
.ico-warn   { background: rgba(245,158,11,.08); } .ico-warn   svg { stroke: var(--warn); }
.ico-ok     { background: rgba(16,185,129,.08); } .ico-ok     svg { stroke: var(--ok); }
.stat-chip-val   { font-family: var(--fm); font-size: 1.5rem; font-weight: 700; color: var(--dk); letter-spacing: -.04em; line-height: 1; }
.stat-chip-label { font-size: .5625rem; color: var(--tx3); font-weight: 700; margin-top: 4px; text-transform: uppercase; letter-spacing: .08em; }
.stat-chip.ac   .stat-chip-val { color: var(--ac2); }
.stat-chip.flame .stat-chip-val { color: var(--warn); }

/* ─────────────────────────────────────────────
   EMPTY STATE
───────────────────────────────────────────── */
.empty-state {
    background: var(--bg2); border: 1px solid var(--bd);
    border-radius: 20px; padding: 4rem 2rem;
    text-align: center; max-width: 480px; margin: 0 auto;
}
.empty-icon {
    width: 72px; height: 72px; border-radius: 50%;
    background: rgba(31,226,144,.08); border: 2px solid rgba(31,226,144,.15);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1.5rem;
}
.empty-icon svg { width: 32px; height: 32px; stroke: var(--ac2); fill: none; stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round; }
.empty-state h2 { font-family: var(--fh); font-size: 1.375rem; font-weight: 900; color: var(--tx); margin-bottom: .5rem; letter-spacing: -.025em; }
.empty-state p  { font-size: .9375rem; color: var(--tx3); line-height: 1.7; margin-bottom: 1.5rem; }
.empty-state-actions { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }

/* ─────────────────────────────────────────────
   BUTTONS
───────────────────────────────────────────── */
.btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 10px 20px; border-radius: 10px;
    font-family: var(--ff); font-size: .875rem; font-weight: 700;
    border: none; cursor: pointer; text-decoration: none; transition: all .22s;
}
.btn svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.btn-primary { background: var(--dk); color: #fff; }
.btn-primary:hover { background: var(--dk2); box-shadow: 0 6px 20px rgba(20,50,48,.15); transform: translateY(-1px); }
.btn-primary svg { stroke: var(--ac); }
.btn-ghost { background: var(--bg2); color: var(--tx2); border: 1.5px solid var(--bd); }
.btn-ghost:hover { border-color: var(--ac); color: var(--dk); }

/* ─────────────────────────────────────────────
   REVIEW CARD
───────────────────────────────────────────── */
.card-area { position: relative; margin-bottom: 28px; }
.review-card {
    background: var(--bg2); border: 1px solid var(--bd);
    border-radius: 22px; padding: 2.5rem; position: relative; overflow: hidden;
    transition: all .45s cubic-bezier(.16,1,.3,1);
}
.review-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--ac), var(--dk)); border-radius: 22px 22px 0 0;
}
.review-card-meta {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 1.5rem; gap: 10px; flex-wrap: wrap;
}
.review-topic-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 12px; border-radius: 8px;
    background: rgba(31,226,144,.08); border: 1px solid rgba(31,226,144,.15);
    font-size: .6875rem; font-weight: 800; color: var(--ac2);
    text-transform: uppercase; letter-spacing: .06em;
}
.review-topic-badge svg { width: 10px; height: 10px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.review-counter { font-family: var(--fm); font-size: .8125rem; font-weight: 700; color: var(--tx3); }
.review-counter span { color: var(--tx); font-weight: 700; }
.review-interval { font-size: .6875rem; color: var(--tx3); display: flex; align-items: center; gap: 4px; }
.review-interval svg { width: 11px; height: 11px; stroke: var(--tx3); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.question-text { font-size: 1.125rem; font-weight: 600; color: var(--tx); line-height: 1.7; margin-bottom: 2rem; min-height: 80px; }
.divider { height: 1px; background: var(--bd); margin: 0 0 1.75rem; }

/* ─────────────────────────────────────────────
   ANSWER SECTION
───────────────────────────────────────────── */
.answer-wrap { opacity: 0; max-height: 0; overflow: hidden; transition: all .5s cubic-bezier(.16,1,.3,1); }
.answer-wrap.show { opacity: 1; max-height: 800px; }
.answer-label {
    font-size: .5625rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: .1em; color: var(--ac2); margin-bottom: .625rem;
    display: flex; align-items: center; gap: 6px;
}
.answer-label-dot { width: 10px; height: 10px; border-radius: 50%; background: var(--ac); flex-shrink: 0; }
.answer-text { font-size: 1rem; font-weight: 700; color: var(--dk); line-height: 1.65; margin-bottom: 1.25rem; }
.explanation-box {
    background: rgba(31,226,144,.04); border: 1px solid rgba(31,226,144,.12);
    border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1.5rem;
}
.explanation-box-label {
    font-size: .5625rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: .1em; color: var(--ac2); margin-bottom: .375rem;
    display: flex; align-items: center; gap: 5px;
}
.explanation-box-label svg { width: 11px; height: 11px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.explanation-box-text { font-size: .875rem; color: var(--tx2); line-height: 1.65; }

/* ─────────────────────────────────────────────
   RATE BUTTONS
───────────────────────────────────────────── */
.rate-section { margin-bottom: 0; }
.rate-label { font-size: .75rem; font-weight: 700; color: var(--tx3); margin-bottom: .625rem; text-align: center; }
.rate-buttons { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
.rate-btn {
    display: flex; flex-direction: column; align-items: center; gap: 5px;
    padding: 12px 8px; border: 2px solid var(--bd); border-radius: 12px;
    cursor: pointer; background: var(--bg2); transition: all .18s;
}
.rate-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.08); }

/* Rate icon boxes */
.rate-icon { width: 34px; height: 34px; border-radius: 9px; display: flex; align-items: center; justify-content: center; }
.rate-icon svg { width: 16px; height: 16px; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.rate-btn.again .rate-icon { background: rgba(239,68,68,.08);  } .rate-btn.again .rate-icon svg { stroke: var(--err); }
.rate-btn.hard  .rate-icon { background: rgba(245,158,11,.08); } .rate-btn.hard  .rate-icon svg { stroke: var(--warn); }
.rate-btn.good  .rate-icon { background: rgba(31,226,144,.08); } .rate-btn.good  .rate-icon svg { stroke: var(--ac2); }
.rate-btn.easy  .rate-icon { background: rgba(16,185,129,.08); } .rate-btn.easy  .rate-icon svg { stroke: var(--ok); }

.rate-label-text { font-size: .6875rem; font-weight: 800; color: var(--tx2); }
.rate-interval   { font-family: var(--fm); font-size: .5625rem; color: var(--tx3); font-weight: 500; }

/* Hover / selected borders */
.rate-btn.again:hover, .rate-btn.again.selected { border-color: var(--err);  background: rgba(239,68,68,.04); }
.rate-btn.hard:hover,  .rate-btn.hard.selected  { border-color: var(--warn); background: rgba(245,158,11,.04); }
.rate-btn.good:hover,  .rate-btn.good.selected  { border-color: var(--ac);   background: rgba(31,226,144,.05); }
.rate-btn.easy:hover,  .rate-btn.easy.selected  { border-color: var(--ok);   background: rgba(16,185,129,.05); }
.rate-btn.selected { border-width: 2.5px; transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,.08); }

/* ─────────────────────────────────────────────
   REVEAL BUTTON
───────────────────────────────────────────── */
.reveal-btn {
    width: 100%; padding: 14px;
    background: var(--dk); color: #fff;
    font-family: var(--ff); font-size: 1rem; font-weight: 800;
    border: none; border-radius: 13px; cursor: pointer;
    transition: all .25s;
    display: flex; align-items: center; justify-content: center; gap: 10px;
    letter-spacing: -.02em;
}
.reveal-btn:hover { background: var(--dk2); box-shadow: 0 8px 28px rgba(20,50,48,.2); transform: translateY(-2px); }
.reveal-btn svg { width: 18px; height: 18px; stroke: var(--ac); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.reveal-btn.hidden { display: none; }

/* ─────────────────────────────────────────────
   SESSION PROGRESS BAR
───────────────────────────────────────────── */
.session-progress {
    background: var(--bg2); border: 1px solid var(--bd);
    border-radius: var(--r); padding: 1rem 1.25rem;
    display: flex; align-items: center; gap: 14px;
}
.sp-text  { font-size: .8125rem; font-weight: 700; color: var(--tx2); white-space: nowrap; }
.sp-bar   { flex: 1; height: 8px; background: var(--bd); border-radius: 4px; overflow: hidden; }
.sp-fill  { height: 100%; background: linear-gradient(90deg, var(--ac), var(--ac2)); border-radius: 4px; transition: width .6s cubic-bezier(.16,1,.3,1); }
.sp-count { font-family: var(--fm); font-size: .8125rem; font-weight: 700; color: var(--dk); white-space: nowrap; }

/* ─────────────────────────────────────────────
   FLIP ANIMATION
───────────────────────────────────────────── */
@keyframes flipOut { 0%   { opacity:1; transform:rotateY(0)     } 50%  { opacity:0; transform:rotateY(-90deg) } 100% { opacity:0; transform:rotateY(-90deg) } }
@keyframes flipIn  { 0%   { opacity:0; transform:rotateY(90deg) } 50%  { opacity:0; transform:rotateY(90deg)  } 100% { opacity:1; transform:rotateY(0)      } }
.card-flip-out { animation: flipOut .35s ease forwards; }
.card-flip-in  { animation: flipIn  .35s ease forwards; }

/* ─────────────────────────────────────────────
   DONE SCREEN
───────────────────────────────────────────── */
.done-screen { display: none; text-align: center; padding: 3rem 1.5rem; background: var(--bg2); border: 1px solid var(--bd); border-radius: 22px; }
.done-screen.show { display: block; }

.done-trophy {
    width: 80px; height: 80px; border-radius: 50%;
    background: linear-gradient(135deg, rgba(31,226,144,.12), rgba(31,226,144,.04));
    border: 2px solid rgba(31,226,144,.2);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1.25rem;
    animation: bounce .6s ease infinite alternate;
}
@keyframes bounce { from { transform: translateY(0); } to { transform: translateY(-8px); } }
.done-trophy svg { width: 36px; height: 36px; stroke: var(--ac2); fill: none; stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round; }

.done-screen h2 { font-family: var(--fh); font-size: 1.5rem; font-weight: 900; color: var(--dk); margin-bottom: .5rem; letter-spacing: -.03em; }
.done-screen p  { font-size: .9375rem; color: var(--tx3); margin-bottom: 1.5rem; line-height: 1.7; }

.done-stats { display: flex; gap: 20px; justify-content: center; margin-bottom: 1.5rem; flex-wrap: wrap; }
.done-stat  { display: flex; flex-direction: column; align-items: center; gap: 3px; }
.done-stat-val   { font-family: var(--fm); font-size: 1.75rem; font-weight: 700; color: var(--dk); letter-spacing: -.04em; }
.done-stat-label { font-size: .5625rem; color: var(--tx3); font-weight: 700; text-transform: uppercase; letter-spacing: .08em; }

/* Streak badge in done screen */
.done-streak {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 8px 18px; border-radius: 20px;
    background: rgba(245,158,11,.08); border: 1px solid rgba(245,158,11,.2);
    margin-bottom: 1.5rem;
}
.done-streak-icon { width: 28px; height: 28px; border-radius: 8px; background: rgba(245,158,11,.12); display: flex; align-items: center; justify-content: center; }
.done-streak-icon svg { width: 14px; height: 14px; stroke: var(--warn); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.done-streak-val   { font-family: var(--fm); font-size: 1.125rem; font-weight: 700; color: var(--warn); }
.done-streak-label { font-size: .75rem; font-weight: 700; color: var(--tx3); }

.done-actions { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }

/* ─────────────────────────────────────────────
   RESPONSIVE
───────────────────────────────────────────── */
@media (max-width: 900px) {
    .main { margin-left: 0; padding: 24px 16px 80px; }
    .stats-bar { grid-template-columns: repeat(2, 1fr); }
    .rate-buttons { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 480px) {
    .review-card { padding: 1.5rem; }
    .question-text { font-size: 1rem; }
}
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/sidebar.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/topbar.php';
?>

<main class="main">

    <!-- ── Stats bar ── -->
    <div class="stats-bar sr">
        <div class="stat-chip ac">
            <div class="stat-chip-icon ico-ac">
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <div class="stat-chip-val"><?= $totalDue ?></div>
            <div class="stat-chip-label">Due Today</div>
        </div>
        <div class="stat-chip">
            <div class="stat-chip-icon ico-dk">
                <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
            </div>
            <div class="stat-chip-val"><?= (int)($stats['total'] ?? 0) ?></div>
            <div class="stat-chip-label">Total Items</div>
        </div>
        <div class="stat-chip">
            <div class="stat-chip-icon ico-ok">
                <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <div class="stat-chip-val"><?= (int)($stats['mastered'] ?? 0) ?></div>
            <div class="stat-chip-label">Mastered</div>
        </div>
        <div class="stat-chip flame">
            <div class="stat-chip-icon ico-warn">
                <svg viewBox="0 0 24 24"><path d="M12 2c0 0-5 4.5-5 9a5 5 0 0010 0c0-4.5-5-9-5-9z"/><path d="M12 12c0 0-2 1.5-2 3a2 2 0 004 0c0-1.5-2-3-2-3z"/></svg>
            </div>
            <div class="stat-chip-val"><?= $currentStreak ?></div>
            <div class="stat-chip-label">Day Streak</div>
        </div>
    </div>

    <?php if ($totalDue === 0): ?>
    <!-- ── Empty state ── -->
    <div class="empty-state sr d1">
        <div class="empty-icon">
            <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <h2>All Caught Up!</h2>
        <p>You have no items due for review today. Come back tomorrow to keep your streak going, or explore new lessons!</p>
        <div class="empty-state-actions">
            <a href="/schedule/" class="btn btn-primary">
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                View Schedule
            </a>
            <a href="/learn/math/" class="btn btn-ghost">
                <svg viewBox="0 0 24 24"><path d="M5 3l14 9-14 9V3z"/></svg>
                Start a Lesson
            </a>
        </div>
    </div>

    <?php else: ?>
    <!-- ── Review session ── -->
    <div class="card-area sr d1" id="cardArea">
        <div class="review-card" id="reviewCard">
            <div class="review-card-meta">
                <span class="review-topic-badge" id="topicBadge">
                    <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
                    Loading…
                </span>
                <span class="review-counter">Card <span id="cardNumDisplay">1</span> of <span id="cardTotalDisplay"><?= $totalDue ?></span></span>
                <span class="review-interval">
                    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Due: <span id="dueDate">today</span>
                </span>
            </div>

            <div class="question-text" id="questionText">Loading…</div>
            <div class="divider"></div>

            <!-- Reveal button -->
            <button class="reveal-btn" id="revealBtn" onclick="revealAnswer()">
                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                Show Answer
            </button>

            <!-- Answer — hidden until revealed -->
            <div class="answer-wrap" id="answerWrap">
                <div class="answer-label">
                    <span class="answer-label-dot"></span>
                    Answer
                </div>
                <div class="answer-text" id="answerText">—</div>

                <div class="explanation-box" id="explanationBox" style="display:none">
                    <div class="explanation-box-label">
                        <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
                        Explanation
                    </div>
                    <div class="explanation-box-text" id="explanationText"></div>
                </div>

                <div class="rate-section">
                    <div class="rate-label">How well did you remember?</div>
                    <div class="rate-buttons">
                        <button class="rate-btn again" onclick="rateCard(1)" type="button">
                            <div class="rate-icon">
                                <!-- Frowning face: X eyes + frown arc -->
                                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="9" y1="9" x2="9" y2="9" stroke-linecap="round" stroke-width="2.5"/><line x1="15" y1="9" x2="15" y2="9" stroke-linecap="round" stroke-width="2.5"/><path d="M9 15.5c.8-1 2.5-1.5 3-1.5s2.2.5 3 1.5"/></svg>
                            </div>
                            <span class="rate-label-text">Again</span>
                            <span class="rate-interval" id="ri-0">Tomorrow</span>
                        </button>
                        <button class="rate-btn hard" onclick="rateCard(3)" type="button">
                            <div class="rate-icon">
                                <!-- Neutral face: dots + flat line -->
                                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="9" y1="9" x2="9" y2="9" stroke-linecap="round" stroke-width="2.5"/><line x1="15" y1="9" x2="15" y2="9" stroke-linecap="round" stroke-width="2.5"/><line x1="9" y1="15" x2="15" y2="15" stroke-linecap="round"/></svg>
                            </div>
                            <span class="rate-label-text">Hard</span>
                            <span class="rate-interval" id="ri-3">Few days</span>
                        </button>
                        <button class="rate-btn good" onclick="rateCard(4)" type="button">
                            <div class="rate-icon">
                                <!-- Slight smile -->
                                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="9" y1="9" x2="9" y2="9" stroke-linecap="round" stroke-width="2.5"/><line x1="15" y1="9" x2="15" y2="9" stroke-linecap="round" stroke-width="2.5"/><path d="M9 14.5c.8 1 2.5 1.5 3 1.5s2.2-.5 3-1.5"/></svg>
                            </div>
                            <span class="rate-label-text">Good</span>
                            <span class="rate-interval" id="ri-4">~1 week</span>
                        </button>
                        <button class="rate-btn easy" onclick="rateCard(5)" type="button">
                            <div class="rate-icon">
                                <!-- Big smile -->
                                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="9" y1="9" x2="9" y2="9" stroke-linecap="round" stroke-width="2.5"/><line x1="15" y1="9" x2="15" y2="9" stroke-linecap="round" stroke-width="2.5"/><path d="M8 13.5c1 2 2.5 3 4 3s3-1 4-3"/></svg>
                            </div>
                            <span class="rate-label-text">Easy</span>
                            <span class="rate-interval" id="ri-5">2+ weeks</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Session progress bar -->
    <div class="session-progress sr d2">
        <span class="sp-text">Progress</span>
        <div class="sp-bar"><div class="sp-fill" id="sessionProgressFill" style="width:0%"></div></div>
        <span class="sp-count"><span id="doneCount">0</span>/<span id="totalCount"><?= $totalDue ?></span></span>
    </div>

    <!-- Done screen -->
    <div class="done-screen sr d1" id="doneScreen">
        <div class="done-trophy">
            <svg viewBox="0 0 24 24"><path d="M6 9H3a1 1 0 00-1 1v2a4 4 0 004 4h1"/><path d="M18 9h3a1 1 0 011 1v2a4 4 0 01-4 4h-1"/><path d="M6 9V4h12v5"/><path d="M9 21v-3a3 3 0 016 0v3"/><line x1="7" y1="21" x2="17" y2="21"/></svg>
        </div>
        <h2>Session Complete!</h2>
        <p>You've reviewed all <strong><?= $totalDue ?></strong> cards due today. Amazing work keeping your streak going!</p>
        <div class="done-stats">
            <div class="done-stat">
                <div class="done-stat-val" id="finalReviewed">0</div>
                <div class="done-stat-label">Reviewed</div>
            </div>
            <div class="done-stat">
                <div class="done-stat-val" id="finalCorrect">0</div>
                <div class="done-stat-label">Correct</div>
            </div>
        </div>
        <div class="done-streak">
            <div class="done-streak-icon">
                <svg viewBox="0 0 24 24"><path d="M12 2c0 0-5 4.5-5 9a5 5 0 0010 0c0-4.5-5-9-5-9z"/><path d="M12 12c0 0-2 1.5-2 3a2 2 0 004 0c0-1.5-2-3-2-3z"/></svg>
            </div>
            <div class="done-streak-val"><?= $currentStreak + 1 ?></div>
            <div class="done-streak-label">Day Streak</div>
        </div>
        <div class="done-actions">
            <a href="/schedule/" class="btn btn-primary">
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Back to Schedule
            </a>
            <a href="/learn/math/" class="btn btn-ghost">
                <svg viewBox="0 0 24 24"><path d="M5 3l14 9-14 9V3z"/></svg>
                Continue Learning
            </a>
        </div>
    </div>
    <?php endif; ?>

</main>

<script>
(function () {
    'use strict';

    /* ── Scroll reveal ── */
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (e.isIntersecting) { e.target.classList.add('v'); io.unobserve(e.target); }
        });
    }, { threshold: .04, rootMargin: '0px 0px -16px 0px' });
    document.querySelectorAll('.sr').forEach(function (el) { io.observe(el); });

    /* ── Sidebar overlay ── */
    var ov = document.getElementById('sidebarOverlay');
    if (ov) {
        ov.addEventListener('click', function () {
            var sb = document.getElementById('sidebar');
            if (sb) sb.classList.remove('open');
            ov.classList.remove('show');
            document.body.style.overflow = '';
        });
    }
    window.addEventListener('resize', function () {
        if (window.innerWidth > 900) {
            var sb = document.getElementById('sidebar');
            if (sb) sb.classList.remove('open');
            if (ov) ov.classList.remove('show');
            document.body.style.overflow = '';
        }
    });

<?php if ($totalDue > 0): ?>
    /* ══ REVIEW SESSION ══════════════════════════════════════════════════ */

    var cards        = <?= json_encode(array_values($dueItems), JSON_UNESCAPED_UNICODE) ?>;
    var currentIdx   = 0;
    var reviewed     = 0;
    var correctCount = 0;
    var totalCards   = cards.length;

    function loadCard(idx) {
        if (idx >= totalCards) { showDoneScreen(); return; }
        var card = cards[idx];

        document.getElementById('topicBadge').childNodes[2 /* text node after SVG */] ? null : null;
        // Update topic text without clobbering the SVG
        var badge = document.getElementById('topicBadge');
        badge.lastChild.textContent = ' ' + (card.topic_name || 'Review');

        document.getElementById('cardNumDisplay').textContent = idx + 1;
        document.getElementById('questionText').textContent   = card.question   || 'Question unavailable';
        document.getElementById('answerText').textContent     = card.answer     || 'Answer unavailable';

        var expBox = document.getElementById('explanationBox');
        var expTxt = document.getElementById('explanationText');
        if (card.explanation) {
            expTxt.textContent   = card.explanation;
            expBox.style.display = 'block';
        } else {
            expBox.style.display = 'none';
        }

        var due = card.next_review_date || 'today';
        document.getElementById('dueDate').textContent = (due === new Date().toISOString().split('T')[0]) ? 'today' : due;

        /* Estimate next intervals */
        var ef = parseFloat(card.ease_factor) || 2.5;
        var iv = parseInt(card.interval_days) || 1;
        document.getElementById('ri-0').textContent = 'Tomorrow';
        document.getElementById('ri-3').textContent = Math.max(1, Math.round(iv * 1.2)) + ' days';
        document.getElementById('ri-4').textContent = Math.max(1, Math.round(iv * ef)) + ' days';
        document.getElementById('ri-5').textContent = Math.max(1, Math.round(iv * ef * 1.3)) + ' days';

        /* Reset UI */
        document.getElementById('revealBtn').classList.remove('hidden');
        document.getElementById('answerWrap').classList.remove('show');
        document.querySelectorAll('.rate-btn').forEach(function (b) {
            b.classList.remove('selected');
            b.disabled = false;
        });
    }

    window.revealAnswer = function () {
        document.getElementById('revealBtn').classList.add('hidden');
        document.getElementById('answerWrap').classList.add('show');
    };

    window.rateCard = function (quality) {
        var card = cards[currentIdx];
        if (!card) return;

        document.querySelectorAll('.rate-btn').forEach(function (b) { b.disabled = true; });
        var btnClass = quality <= 2 ? 'again' : quality === 3 ? 'hard' : quality === 4 ? 'good' : 'easy';
        var clicked  = document.querySelector('.rate-btn.' + btnClass);
        if (clicked) clicked.classList.add('selected');

        if (quality >= 4) correctCount++;
        reviewed++;

        fetch('/schedule/spaced-review.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body:    JSON.stringify({ item_id: card.id, quality: quality })
        }).catch(function (e) { console.error('review submit:', e); });

        setTimeout(function () {
            if (currentIdx + 1 >= totalCards) { showDoneScreen(); return; }
            var rc = document.getElementById('reviewCard');
            rc.classList.add('card-flip-out');
            setTimeout(function () {
                currentIdx++;
                loadCard(currentIdx);
                rc.classList.remove('card-flip-out');
                rc.classList.add('card-flip-in');
                setTimeout(function () { rc.classList.remove('card-flip-in'); }, 400);
            }, 300);
            updateProgress();
        }, 600);
    };

    function updateProgress() {
        var pct     = Math.round((reviewed / totalCards) * 100);
        var fill    = document.getElementById('sessionProgressFill');
        var topFill = document.getElementById('topbarProgress');
        var doneEl  = document.getElementById('doneCount');
        if (fill)    fill.style.width    = pct + '%';
        if (topFill) topFill.style.width = pct + '%';
        if (doneEl)  doneEl.textContent  = reviewed;
    }

    function showDoneScreen() {
        var ca  = document.getElementById('cardArea');
        var sp  = document.querySelector('.session-progress');
        var ds  = document.getElementById('doneScreen');
        if (ca) ca.style.display = 'none';
        if (sp) sp.style.display = 'none';
        if (ds) ds.classList.add('show');
        document.getElementById('finalReviewed').textContent = reviewed;
        document.getElementById('finalCorrect').textContent  = correctCount;
        var topFill = document.getElementById('topbarProgress');
        if (topFill) topFill.style.width = '100%';
    }

    /* ── Keyboard shortcuts ── */
    document.addEventListener('keydown', function (e) {
        var wrap = document.getElementById('answerWrap');
        if (!wrap) return;
        if (!wrap.classList.contains('show')) {
            if (e.code === 'Space' || e.code === 'Enter') { e.preventDefault(); window.revealAnswer(); }
            return;
        }
        if      (e.key === '1') window.rateCard(1);
        else if (e.key === '2') window.rateCard(3);
        else if (e.key === '3') window.rateCard(4);
        else if (e.key === '4') window.rateCard(5);
    });

    /* ── Kick off ── */
    loadCard(0);

<?php endif; ?>
}());
</script>
</body>
</html>