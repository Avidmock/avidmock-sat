<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · Practice Tests — Results
 *  /practice-tests/results.php
 * ═══════════════════════════════════════════════════════════════════
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
Auth::requireStudent();

$userId        = $_SESSION['user_id'];
$user          = User::findById($userId);
$firstName     = explode(' ', $user['name'] ?? 'Student')[0];
$currentStreak = StudyStreak::getCurrent($userId);

$attemptId = (int)($_GET['attempt_id'] ?? 0);
$testId    = (int)($_GET['test_id']    ?? 0);

if (!$attemptId && $testId) {
    $row = Database::fetch(
        "SELECT id FROM practice_test_attempts WHERE test_id=? AND user_id=? AND status='submitted' ORDER BY submitted_at DESC LIMIT 1",
        [$testId, $userId]
    );
    $attemptId = $row ? (int)$row['id'] : 0;
}
if (!$attemptId) { header('Location: /practice-tests/'); exit; }

$attempt = Database::fetch(
    "SELECT a.*,t.title,t.total_time,t.id AS test_id
     FROM practice_test_attempts a
     JOIN practice_tests t ON t.id=a.test_id
     WHERE a.id=? AND a.user_id=?",
    [$attemptId, $userId]
);
if (!$attempt || $attempt['status'] !== 'submitted') { header('Location: /practice-tests/'); exit; }

$allAttempts = Database::fetchAll(
    "SELECT * FROM practice_test_attempts WHERE test_id=? AND user_id=? AND status='submitted' ORDER BY submitted_at DESC",
    [$attempt['test_id'], $userId]
);

/* Rank & percentile */
$rank = (int)Database::fetchColumn(
    "SELECT COUNT(DISTINCT user_id)+1 FROM practice_test_attempts WHERE test_id=? AND status='submitted' AND total_score>?",
    [$attempt['test_id'], $attempt['total_score'] ?? 0]
);
$totalTakers = (int)Database::fetchColumn(
    "SELECT COUNT(DISTINCT user_id) FROM practice_test_attempts WHERE test_id=? AND status='submitted'",
    [$attempt['test_id']]
);
$percentile = $totalTakers > 0 ? round((($totalTakers - $rank + 1) / $totalTakers) * 100) : 0;

/* Domain breakdown */
$domainStats = Database::fetchAll(
    "SELECT q.domain, q.subject, COUNT(*) AS total, SUM(pta.is_correct) AS correct
     FROM practice_test_answers pta
     JOIN questions q ON q.id = pta.question_id
     WHERE pta.attempt_id = ? AND q.domain IS NOT NULL AND q.domain != ''
     GROUP BY q.domain, q.subject ORDER BY q.subject, correct/COUNT(*) ASC",
    [$attemptId]
);

$score     = (int)($attempt['total_score'] ?? 0);
$mathScore = (int)($attempt['math_score']  ?? 0);
$rwScore   = (int)($attempt['rw_score']    ?? 0);
$timeTaken = (int)($attempt['time_taken']  ?? 0);
$timeMin   = round($timeTaken / 60);
$scorePct  = $score     > 0 ? round(($score     - 400) / 1200 * 100) : 0;
$mathPct   = $mathScore > 0 ? round(($mathScore - 200) / 600  * 100) : 0;
$rwPct     = $rwScore   > 0 ? round(($rwScore   - 200) / 600  * 100) : 0;

$totalQ   = (int)Database::fetchColumn("SELECT COUNT(*)      FROM practice_test_answers WHERE attempt_id=?", [$attemptId]);
$correctQ = (int)Database::fetchColumn("SELECT SUM(is_correct) FROM practice_test_answers WHERE attempt_id=?", [$attemptId]);
$accuracy = $totalQ > 0 ? round($correctQ / $totalQ * 100) : 0;

$grade      = $score >= 1400 ? 'Excellent' : ($score >= 1200 ? 'Good' : ($score >= 1000 ? 'Average' : 'Needs Work'));
$gradeColor = $score >= 1400 ? '#1fe290'   : ($score >= 1200 ? '#60a5fa' : ($score >= 1000 ? '#f59e0b' : '#ef4444'));

$activePage = 'practice_tests';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Results: <?= htmlspecialchars($attempt['title']) ?> — Avidmock SAT</title>
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
    --blue: #3b82f6;
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
.sr { opacity: 0; transform: translateY(18px); transition: opacity .5s cubic-bezier(.16,1,.3,1), transform .5s cubic-bezier(.16,1,.3,1); }
.sr.v { opacity: 1; transform: none; }
.d1 { transition-delay: .07s; }
.d2 { transition-delay: .14s; }
.d3 { transition-delay: .21s; }
.d4 { transition-delay: .28s; }

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
.max-w { max-width: 980px; margin: 0 auto; }

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
   SCORE HERO
───────────────────────────────────────────── */
.score-hero {
    background: linear-gradient(135deg, var(--dk2) 0%, #1d5050 50%, var(--dk2) 100%);
    border-radius: 24px; padding: 52px; color: #fff;
    position: relative; overflow: hidden; margin-bottom: 24px;
}
.score-hero::before {
    content: ''; position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(31,226,144,.02) 1px, transparent 1px),
        linear-gradient(90deg, rgba(31,226,144,.02) 1px, transparent 1px);
    background-size: 36px 36px; pointer-events: none;
}
.score-hero::after {
    content: ''; position: absolute; top: -100px; right: -80px;
    width: 400px; height: 400px; border-radius: 50%;
    background: radial-gradient(circle, rgba(31,226,144,.07) 0%, transparent 60%);
    pointer-events: none;
}

.hero-inner { display: flex; align-items: center; gap: 52px; flex-wrap: wrap; position: relative; z-index: 1; }

/* Score ring */
.score-ring { width: 200px; height: 200px; position: relative; flex-shrink: 0; }
.score-ring-vals {
    position: absolute; inset: 0;
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px;
}
.score-big {
    font-family: var(--fm); font-size: 56px; font-weight: 700;
    letter-spacing: -.05em; line-height: 1;
    background: linear-gradient(135deg, #1fe290, #00e878);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
}
.score-out { font-size: .8125rem; color: rgba(255,255,255,.3); font-weight: 600; }
.score-grade {
    font-size: .8125rem; font-weight: 800;
    padding: 4px 12px; border-radius: 8px; margin-top: 4px;
    background: rgba(255,255,255,.06);
}

/* Hero right */
.hero-right { flex: 1; min-width: 240px; }
.hero-test-name {
    font-size: .6875rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .1em; color: rgba(255,255,255,.3); margin-bottom: 6px;
}
.hero-date { font-size: .9375rem; color: rgba(255,255,255,.45); margin-bottom: 24px; }

/* Section score boxes */
.hero-section-scores { display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 24px; }
.section-score-box {
    flex: 1; min-width: 120px; padding: 18px 20px;
    background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.07);
    border-radius: 16px;
}
.ss-label {
    font-size: .5625rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .1em; color: rgba(255,255,255,.3); margin-bottom: 8px;
}
.ss-score {
    font-family: var(--fm); font-size: 2.25rem; font-weight: 700;
    color: #fff; letter-spacing: -.04em; line-height: 1; margin-bottom: 8px;
}
.ss-bar { height: 4px; background: rgba(255,255,255,.08); border-radius: 2px; overflow: hidden; }
.ss-bar-fill { height: 100%; border-radius: 2px; transition: width 1.4s cubic-bezier(.16,1,.3,1); width: 0%; }

/* Hero meta chips */
.hero-meta { display: flex; gap: 10px; flex-wrap: wrap; }
.hero-meta-item {
    padding: 10px 18px;
    background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.06);
    border-radius: 12px; text-align: center;
}
.hero-meta-val {
    font-family: var(--fm); font-size: 1.25rem; font-weight: 700;
    color: #fff; letter-spacing: -.03em; line-height: 1;
}
.hero-meta-lbl { font-size: .5625rem; color: rgba(255,255,255,.3); margin-top: 4px; text-transform: uppercase; letter-spacing: .08em; }

/* ─────────────────────────────────────────────
   QUICK STATS
───────────────────────────────────────────── */
.qs-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 24px; }
.qs-pill {
    background: var(--bg2); border: 1px solid var(--bd);
    border-radius: var(--r); padding: 18px 20px;
    display: flex; align-items: center; gap: 14px;
    transition: transform .2s, box-shadow .2s;
}
.qs-pill:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(20,50,48,.06); }
.qs-ico { width: 40px; height: 40px; border-radius: 11px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.qs-ico svg { width: 18px; height: 18px; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.ico-ac   { background: rgba(31,226,144,.08);  } .ico-ac   svg { stroke: var(--ac2); }
.ico-math { background: rgba(99,102,241,.08);  } .ico-math svg { stroke: var(--math); }
.ico-warn { background: rgba(245,158,11,.08);  } .ico-warn svg { stroke: var(--warn); }
.ico-dk   { background: rgba(20,50,48,.06);    } .ico-dk   svg { stroke: var(--dk2); }
.qs-label { font-size: .5rem; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: var(--tx3); margin-bottom: 3px; }
.qs-val   { font-family: var(--fm); font-size: 1.375rem; font-weight: 700; color: var(--tx); letter-spacing: -.04em; line-height: 1; }
.qs-sub   { font-size: .6875rem; color: var(--tx3); margin-top: 3px; }

/* ─────────────────────────────────────────────
   ACTION BUTTONS
───────────────────────────────────────────── */
.actions-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 24px; }
.btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 11px 22px; border-radius: 12px;
    font-family: var(--ff); font-size: .875rem; font-weight: 700;
    text-decoration: none; transition: all .18s; border: none; cursor: pointer;
}
.btn svg { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.btn-primary { background: var(--dk2); color: #fff; }
.btn-primary:hover { background: var(--dk3); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(15,50,48,.15); }
.btn-primary svg { stroke: var(--ac); }
.btn-mint { background: var(--ac); color: var(--dk); }
.btn-mint:hover { background: var(--ac2); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(31,226,144,.25); }
.btn-ghost { background: var(--bg2); color: var(--tx2); border: 1.5px solid var(--bd); }
.btn-ghost:hover { border-color: var(--ac); color: var(--dk2); }

/* ─────────────────────────────────────────────
   DOMAIN BREAKDOWN
───────────────────────────────────────────── */
.domain-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; }
.domain-card {
    background: var(--bg2); border: 1px solid var(--bd);
    border-radius: var(--r-lg); padding: 28px; overflow: hidden;
}
.domain-card-title {
    font-size: .9375rem; font-weight: 800; color: var(--tx);
    margin-bottom: 20px; display: flex; align-items: center; gap: 8px;
    letter-spacing: -.01em;
}
.domain-tag {
    font-size: .5rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: .1em; padding: 3px 9px; border-radius: 6px;
}
.domain-row { display: flex; flex-direction: column; gap: 4px; margin-bottom: 14px; }
.domain-row:last-child { margin-bottom: 0; }
.domain-row-top {
    display: flex; align-items: center; justify-content: space-between;
    gap: 8px; margin-bottom: 6px;
}
.domain-name {
    font-size: .8125rem; font-weight: 600; color: var(--tx2);
    flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.domain-score { font-family: var(--fm); font-size: .8125rem; font-weight: 700; color: var(--tx); flex-shrink: 0; }
.domain-bar { height: 6px; background: var(--bg); border-radius: 3px; overflow: hidden; }
.domain-bar-fill { height: 100%; border-radius: 3px; transition: width 1.2s cubic-bezier(.16,1,.3,1); width: 0%; }

/* ─────────────────────────────────────────────
   ATTEMPT HISTORY
───────────────────────────────────────────── */
.hist-card {
    background: var(--bg2); border: 1px solid var(--bd);
    border-radius: var(--r-lg); overflow: hidden;
}
.hist-hd {
    padding: 18px 24px; border-bottom: 1px solid var(--bd);
    display: flex; align-items: center; justify-content: space-between;
}
.hist-hd-title { font-size: 1.0625rem; font-weight: 800; color: var(--tx); letter-spacing: -.02em; }
.hist-hd-link { font-size: .8125rem; font-weight: 700; color: var(--ac2); display: flex; align-items: center; gap: 4px; transition: color .15s; }
.hist-hd-link:hover { color: var(--ac); }
.hist-hd-link svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

.hist-table { width: 100%; border-collapse: collapse; }
.hist-table th {
    font-size: .5625rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .08em; color: var(--tx3);
    padding: 11px 20px; text-align: left; border-bottom: 1px solid var(--bd);
}
.hist-table td {
    padding: 13px 20px; border-bottom: 1px solid var(--bd);
    font-size: .875rem; color: var(--tx2); vertical-align: middle;
}
.hist-table tbody tr:last-child td { border-bottom: none; }
.hist-table tr.curr td { background: rgba(31,226,144,.02); }
.hist-table tbody tr:hover td { background: rgba(247,250,249,.8); }

.hist-rank { font-family: var(--fm); font-size: .8125rem; font-weight: 700; color: var(--tx3); }
.hist-date { font-size: .8125rem; color: var(--tx2); white-space: nowrap; }
.hist-score-pill {
    display: inline-flex; align-items: center;
    padding: 4px 11px; border-radius: 8px;
    font-family: var(--fm); font-weight: 700; font-size: .875rem;
}
.hist-score-pill.high { background: rgba(31,226,144,.1); color: var(--ac2); }
.hist-score-pill.mid  { background: rgba(245,158,11,.1); color: #a06a00; }
.hist-score-pill.low  { background: rgba(239,68,68,.08); color: var(--err); }
.hist-this-badge {
    display: inline-flex; align-items: center; gap: 3px;
    font-size: .5rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .08em; padding: 2px 7px; border-radius: 4px;
    background: rgba(31,226,144,.1); color: var(--ac2); margin-left: 6px; vertical-align: middle;
}
.hist-sec { font-family: var(--fm); font-size: .8125rem; color: var(--tx3); }
.hist-time { font-family: var(--fm); font-size: .8125rem; color: var(--tx3); }
.hist-view-link { font-size: .8125rem; font-weight: 700; color: var(--ac2); transition: color .15s; }
.hist-view-link:hover { color: var(--ac); }

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
    .score-hero { padding: 32px 24px; }
    .hero-inner { gap: 28px; }
    .score-ring { width: 160px; height: 160px; }
    .score-big { font-size: 44px; }
    .domain-grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) { .qs-row { grid-template-columns: 1fr 1fr; } }
@media (max-width: 600px) {
    .qs-row { grid-template-columns: 1fr 1fr; }
    .hero-inner { flex-direction: column; align-items: flex-start; }
    .actions-row { flex-direction: column; }
}
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/sidebar.php'; ?>

<header class="topbar" style="position:fixed;top:0;left:var(--sidebar-w);right:0;height:var(--topbar-h);background:rgba(243,247,246,.94);backdrop-filter:blur(12px);border-bottom:1px solid var(--bd);display:flex;align-items:center;padding:0 28px;gap:16px;z-index:100;">
    <button class="topbar-ham" onclick="openSidebar()" style="display:none;width:38px;height:38px;border-radius:10px;border:1.5px solid var(--bd);background:#fff;align-items:center;justify-content:center;cursor:pointer;flex-direction:column;gap:4px;padding:10px;" id="hamBtn">
        <span style="display:block;height:2px;background:var(--tx);border-radius:1px;width:100%"></span>
        <span style="display:block;height:2px;background:var(--tx);border-radius:1px;width:100%"></span>
        <span style="display:block;height:2px;background:var(--tx);border-radius:1px;width:100%"></span>
    </button>
    <div style="flex:1">
        <div style="font-size:1rem;font-weight:700;color:var(--tx)">Test Results</div>
        <div style="font-size:.75rem;color:var(--tx3);margin-top:1px">
            <a href="/practice-tests/" style="color:var(--tx3)">Practice Tests</a>
            <span style="margin:0 6px;opacity:.4">›</span>
            <?= htmlspecialchars($attempt['title']) ?>
        </div>
    </div>
</header>

<main class="main-content">
<div class="max-w">

    <!-- Breadcrumb -->
    <nav class="breadcrumb sr">
        <a href="/practice-tests/">Practice Tests</a>
        <span class="breadcrumb-sep">›</span>
        <span class="breadcrumb-cur">Results</span>
    </nav>

    <!-- ── SCORE HERO ── -->
    <div class="score-hero sr">
        <div class="hero-inner">

            <!-- Ring -->
            <div class="score-ring">
                <svg width="200" height="200" viewBox="0 0 200 200" style="transform:rotate(-90deg)">
                    <circle cx="100" cy="100" r="85" fill="none" stroke="rgba(255,255,255,.06)" stroke-width="12"/>
                    <circle cx="100" cy="100" r="85" fill="none" stroke="url(#heroGrad)" stroke-width="12"
                            stroke-dasharray="<?= round(2 * M_PI * 85) ?>"
                            stroke-dashoffset="<?= round(2 * M_PI * 85 * (1 - $scorePct / 100)) ?>"
                            stroke-linecap="round"/>
                    <defs>
                        <linearGradient id="heroGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                            <stop offset="0%"   style="stop-color:#1fe290"/>
                            <stop offset="100%" style="stop-color:#00e878"/>
                        </linearGradient>
                    </defs>
                </svg>
                <div class="score-ring-vals">
                    <div class="score-big"><?= $score ?: '—' ?></div>
                    <div class="score-out">out of 1600</div>
                    <div class="score-grade" style="color:<?= $gradeColor ?>"><?= $grade ?></div>
                </div>
            </div>

            <!-- Right side -->
            <div class="hero-right">
                <div class="hero-test-name"><?= htmlspecialchars($attempt['title']) ?></div>
                <div class="hero-date">Submitted <?= date('M j, Y', strtotime($attempt['submitted_at'] ?? 'now')) ?></div>

                <div class="hero-section-scores">
                    <div class="section-score-box">
                        <div class="ss-label">Reading &amp; Writing</div>
                        <div class="ss-score"><?= $rwScore ?: '—' ?></div>
                        <div class="ss-bar">
                            <div class="ss-bar-fill" data-w="<?= $rwPct ?>" style="background:var(--ac)"></div>
                        </div>
                    </div>
                    <div class="section-score-box">
                        <div class="ss-label">Math</div>
                        <div class="ss-score"><?= $mathScore ?: '—' ?></div>
                        <div class="ss-bar">
                            <div class="ss-bar-fill" data-w="<?= $mathPct ?>" style="background:#818cf8"></div>
                        </div>
                    </div>
                </div>

                <div class="hero-meta">
                    <?php if ($percentile > 0): ?>
                    <div class="hero-meta-item">
                        <div class="hero-meta-val"><?= $percentile ?>th</div>
                        <div class="hero-meta-lbl">Percentile</div>
                    </div>
                    <?php endif; ?>
                    <div class="hero-meta-item">
                        <div class="hero-meta-val"><?= $rank ?>/<?= $totalTakers ?></div>
                        <div class="hero-meta-lbl">Rank</div>
                    </div>
                    <?php if ($timeMin): ?>
                    <div class="hero-meta-item">
                        <div class="hero-meta-val"><?= $timeMin ?>m</div>
                        <div class="hero-meta-lbl">Time Taken</div>
                    </div>
                    <?php endif; ?>
                    <div class="hero-meta-item">
                        <div class="hero-meta-val"><?= $accuracy ?>%</div>
                        <div class="hero-meta-lbl">Accuracy</div>
                    </div>
                </div>
            </div>

        </div>
    </div><!-- /score-hero -->

    <!-- ── QUICK STATS ── -->
    <div class="qs-row sr d1">

        <div class="qs-pill">
            <div class="qs-ico ico-ac">
                <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
            </div>
            <div>
                <div class="qs-label">R&amp;W Score</div>
                <div class="qs-val"><?= $rwScore ?: '—' ?></div>
                <div class="qs-sub">200–800</div>
            </div>
        </div>

        <div class="qs-pill">
            <div class="qs-ico ico-math">
                <svg viewBox="0 0 24 24"><path d="M4 19l8-14 8 14H4z"/></svg>
            </div>
            <div>
                <div class="qs-label">Math Score</div>
                <div class="qs-val"><?= $mathScore ?: '—' ?></div>
                <div class="qs-sub">200–800</div>
            </div>
        </div>

        <div class="qs-pill">
            <div class="qs-ico ico-warn">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div>
                <div class="qs-label">Total Time</div>
                <div class="qs-val"><?= $timeMin ?: '—' ?>m</div>
                <div class="qs-sub">used</div>
            </div>
        </div>

        <div class="qs-pill">
            <div class="qs-ico ico-dk">
                <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
            </div>
            <div>
                <div class="qs-label">Correct</div>
                <div class="qs-val"><?= $correctQ ?>/<?= $totalQ ?></div>
                <div class="qs-sub"><?= $accuracy ?>% accuracy</div>
            </div>
        </div>

    </div><!-- /qs-row -->

    <!-- ── ACTION BUTTONS ── -->
    <div class="actions-row sr d2">
        <a href="/practice-tests/review.php?attempt_id=<?= $attemptId ?>" class="btn btn-primary">
            <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            Review All Answers
        </a>
        <a href="/practice-tests/start.php?test_id=<?= $attempt['test_id'] ?>" class="btn btn-mint">
            <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
            Retake Test
        </a>
        <a href="/tutor/" class="btn btn-ghost">
            <svg viewBox="0 0 24 24"><path d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Ask AI Tutor
        </a>
        <a href="/practice-tests/" class="btn btn-ghost">
            <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            All Tests
        </a>
    </div>

    <!-- ── DOMAIN BREAKDOWN ── -->
    <?php if (!empty($domainStats)): ?>
    <div class="domain-grid sr d3">

        <!-- R&W Domains -->
        <div class="domain-card">
            <div class="domain-card-title">
                Reading &amp; Writing
                <span class="domain-tag" style="background:rgba(31,226,144,.08);color:var(--ac2)">R&amp;W</span>
            </div>
            <?php foreach ($domainStats as $ds):
                if (str_contains(strtolower($ds['subject']), 'math')) continue;
                $pct      = $ds['total'] > 0 ? round($ds['correct'] / $ds['total'] * 100) : 0;
                $barColor = $pct >= 70 ? 'var(--ac)' : ($pct >= 50 ? 'var(--warn)' : 'var(--err)');
            ?>
            <div class="domain-row">
                <div class="domain-row-top">
                    <div class="domain-name"><?= htmlspecialchars($ds['domain']) ?></div>
                    <div class="domain-score"><?= $ds['correct'] ?>/<?= $ds['total'] ?></div>
                </div>
                <div class="domain-bar">
                    <div class="domain-bar-fill" data-w="<?= $pct ?>" style="background:<?= $barColor ?>"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Math Domains -->
        <div class="domain-card">
            <div class="domain-card-title">
                Math
                <span class="domain-tag" style="background:rgba(99,102,241,.08);color:var(--math)">Math</span>
            </div>
            <?php foreach ($domainStats as $ds):
                if (!str_contains(strtolower($ds['subject']), 'math')) continue;
                $pct      = $ds['total'] > 0 ? round($ds['correct'] / $ds['total'] * 100) : 0;
                $barColor = $pct >= 70 ? '#818cf8' : ($pct >= 50 ? 'var(--warn)' : 'var(--err)');
            ?>
            <div class="domain-row">
                <div class="domain-row-top">
                    <div class="domain-name"><?= htmlspecialchars($ds['domain']) ?></div>
                    <div class="domain-score"><?= $ds['correct'] ?>/<?= $ds['total'] ?></div>
                </div>
                <div class="domain-bar">
                    <div class="domain-bar-fill" data-w="<?= $pct ?>" style="background:<?= $barColor ?>"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    </div><!-- /domain-grid -->
    <?php endif; ?>

    <!-- ── ATTEMPT HISTORY ── -->
    <?php if (count($allAttempts) > 1): ?>
    <div class="hist-card sr d4">
        <div class="hist-hd">
            <div class="hist-hd-title">Your Attempt History</div>
            <a href="/practice-tests/history.php" class="hist-hd-link">
                All History
                <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>
        </div>
        <div style="overflow-x:auto">
            <table class="hist-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Total</th>
                        <th>R&amp;W</th>
                        <th>Math</th>
                        <th>Time</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($allAttempts as $i => $att):
                    $attSc    = (int)($att['total_score'] ?? 0);
                    $pillCls  = $attSc >= 1300 ? 'high' : ($attSc >= 1100 ? 'mid' : 'low');
                    $isCurr   = ($att['id'] == $attemptId);
                ?>
                <tr class="<?= $isCurr ? 'curr' : '' ?>">
                    <td><span class="hist-rank"><?= $i + 1 ?></span></td>
                    <td><span class="hist-date"><?= date('M j, Y', strtotime($att['submitted_at'])) ?></span></td>
                    <td>
                        <span class="hist-score-pill <?= $pillCls ?>"><?= $attSc ?: '—' ?></span>
                        <?php if ($isCurr): ?>
                        <span class="hist-this-badge">This</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="hist-sec"><?= $att['rw_score']   ?: '—' ?></span></td>
                    <td><span class="hist-sec"><?= $att['math_score'] ?: '—' ?></span></td>
                    <td><span class="hist-time"><?= $att['time_taken'] ? round($att['time_taken'] / 60) . 'm' : '—' ?></span></td>
                    <td>
                        <a href="/practice-tests/results.php?attempt_id=<?= (int)$att['id'] ?>"
                           class="hist-view-link">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

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
                setTimeout(function () { n.target.style.width = (n.target.dataset.w || 0) + '%'; }, 150);
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
        if (ov) { ov.classList.add('show'); ov.style.display = 'block'; }
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