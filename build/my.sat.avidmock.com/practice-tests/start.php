<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · Practice Tests — Start / Confirm
 *  /practice-tests/start.php
 * ═══════════════════════════════════════════════════════════════════
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
Auth::requireStudent();

$userId        = $_SESSION['user_id'];
$user          = User::findById($userId);
$firstName     = explode(' ', $user['name'] ?? 'Student')[0];
$currentStreak = StudyStreak::getCurrent($userId);

$testId = (int)($_GET['test_id'] ?? 0);
if (!$testId) { header('Location: /practice-tests/'); exit; }

$test = Database::fetch("SELECT * FROM practice_tests WHERE id = ? AND is_published = 1", [$testId]);
if (!$test) { header('Location: /practice-tests/'); exit; }

$inProgress = Database::fetch(
    "SELECT * FROM practice_test_attempts WHERE test_id = ? AND user_id = ? AND status = 'in_progress' LIMIT 1",
    [$testId, $userId]
);
$myBest  = Database::fetchColumn("SELECT MAX(total_score) FROM practice_test_attempts WHERE test_id=? AND user_id=? AND status='submitted'", [$testId, $userId]);
$myCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM practice_test_attempts WHERE test_id=? AND user_id=? AND status='submitted'", [$testId, $userId]);
$avgAll  = Database::fetchColumn("SELECT AVG(total_score) FROM practice_test_attempts WHERE test_id=? AND status='submitted'", [$testId]);

/* Sections */
$sections       = Database::fetchAll("SELECT * FROM practice_test_sections WHERE test_id=? ORDER BY sort_order ASC", [$testId]);
$totalQuestions = 0;
foreach ($sections as $s) {
    $totalQuestions += (int)Database::fetchColumn("SELECT COUNT(*) FROM practice_test_section_questions WHERE section_id=?", [$s['id']]);
}
$totalMin = round(($test['total_time'] ?? 8040) / 60);

/* Start POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start'])) {
    $attemptId = Database::insert('practice_test_attempts', [
        'user_id'    => $userId,
        'test_id'    => $testId,
        'status'     => 'in_progress',
        'started_at' => date('Y-m-d H:i:s'),
    ]);
    header("Location: /practice-tests/exam.php?attempt_id={$attemptId}");
    exit;
}

$activePage = 'practice_tests';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Start: <?= htmlspecialchars($test['title']) ?> — Avidmock SAT</title>
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
    --warn: #f59e0b;
    --err:  #ef4444;
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

@keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: none; } }

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
    padding: 40px 28px 80px;
    display: flex; align-items: flex-start; justify-content: center;
    min-height: calc(100vh - var(--topbar-h));
}

/* ─────────────────────────────────────────────
   START CARD WRAPPER
───────────────────────────────────────────── */
.start-wrap { width: 100%; max-width: 700px; animation: fadeUp .5s cubic-bezier(.16,1,.3,1) both; }

.back-link {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: .8125rem; color: var(--tx3);
    margin-bottom: 28px; transition: color .18s;
}
.back-link:hover { color: var(--tx); }
.back-link svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

.start-card { background: var(--bg2); border: 1px solid var(--bd); border-radius: 24px; overflow: hidden; }

/* ─────────────────────────────────────────────
   HERO
───────────────────────────────────────────── */
.start-hero {
    background: linear-gradient(135deg, var(--dk2) 0%, #1d4a46 50%, var(--dk2) 100%);
    padding: 44px 48px; position: relative; overflow: hidden;
}
.start-hero::before {
    content: ''; position: absolute; top: -80px; right: -80px;
    width: 320px; height: 320px; border-radius: 50%; pointer-events: none;
    background: radial-gradient(circle, rgba(31,226,144,.07) 0%, transparent 65%);
}
.start-hero::after {
    content: ''; position: absolute; inset: 0; pointer-events: none;
    background-image:
        linear-gradient(rgba(31,226,144,.015) 1px, transparent 1px),
        linear-gradient(90deg, rgba(31,226,144,.015) 1px, transparent 1px);
    background-size: 40px 40px;
}
.hero-type {
    font-size: .625rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .14em; color: rgba(255,255,255,.3);
    margin-bottom: 12px; position: relative; z-index: 1;
}
.hero-title {
    font-family: var(--fh); font-size: 1.75rem; font-weight: 900;
    color: #fff; letter-spacing: -.03em; margin-bottom: 10px;
    line-height: 1.15; position: relative; z-index: 1;
}
.hero-desc {
    font-size: .875rem; color: rgba(255,255,255,.45);
    line-height: 1.65; position: relative; z-index: 1;
}

/* ─────────────────────────────────────────────
   BODY
───────────────────────────────────────────── */
.start-body { padding: 40px 48px; }

/* Resume banner */
.resume-banner {
    background: rgba(245,158,11,.05); border: 1px solid rgba(245,158,11,.2);
    border-radius: 16px; padding: 18px 22px; margin-bottom: 32px;
    display: flex; align-items: flex-start; gap: 16px;
}
.resume-banner-icon {
    width: 38px; height: 38px; border-radius: 10px; flex-shrink: 0;
    background: rgba(245,158,11,.12);
    display: flex; align-items: center; justify-content: center;
}
.resume-banner-icon svg { width: 18px; height: 18px; stroke: var(--warn); fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.resume-text-title { font-size: .875rem; font-weight: 800; color: var(--tx); margin-bottom: 3px; }
.resume-text-sub   { font-size: .8125rem; color: var(--tx3); line-height: 1.5; }

/* Info grid */
.info-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 36px; }
.info-box {
    background: var(--bg); border: 1px solid var(--bd); border-radius: var(--r);
    padding: 18px 14px; text-align: center;
    transition: transform .18s, box-shadow .18s;
}
.info-box:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(20,50,48,.06); }
.info-box-icon {
    width: 38px; height: 38px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 10px;
}
.info-box-icon svg { width: 18px; height: 18px; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.ico-ac     { background: rgba(31,226,144,.08);  } .ico-ac     svg { stroke: var(--ac2); }
.ico-dk     { background: rgba(20,50,48,.06);    } .ico-dk     svg { stroke: var(--dk2); }
.ico-warn   { background: rgba(245,158,11,.08);  } .ico-warn   svg { stroke: var(--warn); }
.ico-blue   { background: rgba(59,130,246,.08);  } .ico-blue   svg { stroke: #3b82f6; }
.ico-purple { background: rgba(99,102,241,.08);  } .ico-purple svg { stroke: var(--math); }
.info-box-val   { font-family: var(--fm); font-size: 1.375rem; font-weight: 700; color: var(--tx); letter-spacing: -.04em; line-height: 1; margin-bottom: 4px; }
.info-box-label { font-size: .6875rem; color: var(--tx3); font-weight: 600; text-transform: uppercase; letter-spacing: .06em; }

/* Modules */
.modules-title { font-size: .9375rem; font-weight: 800; color: var(--tx); margin-bottom: 14px; letter-spacing: -.02em; }
.modules-list  { display: flex; flex-direction: column; gap: 8px; margin-bottom: 32px; }
.module-row {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 16px; border-radius: 12px;
    background: var(--bg); border: 1px solid var(--bd);
    transition: border-color .18s;
}
.module-row:hover { border-color: var(--bd2); }
.module-icon {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.module-icon svg { width: 17px; height: 17px; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.module-info  { flex: 1; }
.module-name  { font-size: .875rem; font-weight: 700; color: var(--tx); margin-bottom: 2px; }
.module-meta  { font-size: .75rem; color: var(--tx3); }
.module-time  { font-family: var(--fm); font-size: .8125rem; font-weight: 700; color: var(--tx2); flex-shrink: 0; }

.module-break {
    text-align: center; padding: 4px 0;
    display: flex; align-items: center; gap: 10px;
}
.module-break::before, .module-break::after { content: ''; flex: 1; height: 1px; background: var(--bd); }
.break-chip {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(245,158,11,.08); color: var(--warn);
    border: 1px solid rgba(245,158,11,.2);
    padding: 4px 12px; border-radius: 20px;
    font-size: .6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em;
    white-space: nowrap;
}
.break-chip svg { width: 12px; height: 12px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

/* Checklist */
.checklist       { margin-bottom: 36px; }
.checklist-title { font-size: .9375rem; font-weight: 800; color: var(--tx); margin-bottom: 14px; letter-spacing: -.02em; }
.check-row {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 11px 0; border-bottom: 1px solid var(--bd);
}
.check-row:last-child { border-bottom: none; }
.check-dot {
    width: 20px; height: 20px; border-radius: 50%;
    background: rgba(31,226,144,.1);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; margin-top: 1px;
}
.check-dot svg { width: 10px; height: 10px; stroke: var(--ac2); fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }
.check-text { font-size: .875rem; color: var(--tx2); line-height: 1.55; }
.check-text strong { color: var(--tx); font-weight: 700; }

/* Actions */
.start-actions { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
.btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 13px 26px; border-radius: 12px;
    font-family: var(--ff); font-size: .875rem; font-weight: 800;
    text-decoration: none; transition: all .2s; border: none; cursor: pointer;
    letter-spacing: -.01em;
}
.btn svg { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }
.btn-primary { background: var(--dk2); color: #fff; }
.btn-primary:hover { background: var(--dk3); box-shadow: 0 8px 24px rgba(15,36,32,.18); transform: translateY(-2px); }
.btn-primary svg { stroke: var(--ac); }
.btn-resume { background: var(--warn); color: #fff; }
.btn-resume:hover { background: #e08e09; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(245,158,11,.25); }
.btn-ghost { background: var(--bg2); color: var(--tx2); border: 1.5px solid var(--bd); }
.btn-ghost:hover { border-color: var(--bd2); background: var(--bg); }

/* ─────────────────────────────────────────────
   RESPONSIVE
───────────────────────────────────────────── */
@media (max-width: 900px) {
    .sidebar { transform: translateX(-100%); }
    .sidebar.open { transform: translateX(0); }
    .sidebar-overlay { display: block; pointer-events: none; }
    .sidebar-overlay.show { pointer-events: all; }
    .main-content { margin-left: 0; padding: 24px 16px 80px; }
    .topbar { left: 0; padding: 0 16px; }
    .topbar-ham { display: flex !important; }
}
@media (max-width: 640px) {
    .info-grid { grid-template-columns: 1fr 1fr; }
    .start-body, .start-hero { padding: 28px 24px; }
    .start-actions { flex-direction: column; align-items: stretch; }
    .start-actions .btn { justify-content: center; }
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
        <div style="font-size:1rem;font-weight:700;color:var(--tx)">Start Practice Test</div>
        <div style="font-size:.75rem;color:var(--tx3);margin-top:1px">
            <a href="/practice-tests/" style="color:var(--tx3)">Practice Tests</a>
            <span style="margin:0 6px;opacity:.4">›</span>
            <?= htmlspecialchars($test['title']) ?>
        </div>
    </div>
</header>

<main class="main-content">
<div class="start-wrap">

    <a href="/practice-tests/" class="back-link">
        <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        All Practice Tests
    </a>

    <div class="start-card">

        <!-- ── HERO ── -->
        <div class="start-hero">
            <div class="hero-type">Full-Length SAT Practice Test</div>
            <h1 class="hero-title"><?= htmlspecialchars($test['title']) ?></h1>
            <p class="hero-desc"><?= htmlspecialchars($test['description'] ?? 'Real SAT format: Reading & Writing and Math across 4 modules with an optional 10-minute break.') ?></p>
        </div>

        <!-- ── BODY ── -->
        <div class="start-body">

            <!-- Resume banner -->
            <?php if ($inProgress): ?>
            <div class="resume-banner">
                <div class="resume-banner-icon">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div>
                    <div class="resume-text-title">You have an attempt in progress</div>
                    <div class="resume-text-sub">Started <?= date('M j \a\t g:i A', strtotime($inProgress['started_at'])) ?> — resume where you left off, or start a new attempt below.</div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Info grid -->
            <div class="info-grid">
                <div class="info-box">
                    <div class="info-box-icon ico-warn">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <div class="info-box-val"><?= $totalMin ?>m</div>
                    <div class="info-box-label">Total Time</div>
                </div>
                <div class="info-box">
                    <div class="info-box-icon ico-dk">
                        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    </div>
                    <div class="info-box-val"><?= $totalQuestions ?: '98' ?></div>
                    <div class="info-box-label">Questions</div>
                </div>
                <div class="info-box">
                    <div class="info-box-icon <?= $myBest ? 'ico-ac' : 'ico-blue' ?>">
                        <?php if ($myBest): ?>
                        <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                        <?php else: ?>
                        <svg viewBox="0 0 24 24"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>
                        <?php endif; ?>
                    </div>
                    <div class="info-box-val"><?= $myBest ?: ($avgAll ? round($avgAll) : '—') ?></div>
                    <div class="info-box-label"><?= $myBest ? 'Your Best' : 'Avg Score' ?></div>
                </div>
                <div class="info-box">
                    <div class="info-box-icon ico-purple">
                        <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
                    </div>
                    <div class="info-box-val"><?= $myCount ?></div>
                    <div class="info-box-label">Attempt<?= $myCount !== 1 ? 's' : '' ?></div>
                </div>
            </div>

            <!-- Test structure -->
            <div class="modules-title">Test Structure</div>
            <div class="modules-list">
                <?php foreach ($sections as $idx => $sec):
                    $isMath = str_contains(strtolower($sec['subject'] ?? ''), 'math');
                    $qc     = (int)Database::fetchColumn("SELECT COUNT(*) FROM practice_test_section_questions WHERE section_id=?", [$sec['id']]);
                    $mins   = round(($sec['time_limit'] ?? 1920) / 60);
                    $iconBg = $isMath ? 'rgba(99,102,241,.1)' : 'rgba(31,226,144,.08)';
                    $iconSt = $isMath ? '#818cf8' : 'var(--ac2)';
                ?>
                <?php if ($idx === 2): ?>
                <div class="module-break">
                    <span class="break-chip">
                        <svg viewBox="0 0 24 24"><path d="M18 8h1a4 4 0 010 8h-1"/><path d="M2 8h16v9a4 4 0 01-4 4H6a4 4 0 01-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>
                        10-min Break
                    </span>
                </div>
                <?php endif; ?>
                <div class="module-row">
                    <div class="module-icon" style="background:<?= $iconBg ?>">
                        <?php if ($isMath): ?>
                        <svg style="width:17px;height:17px;stroke:<?= $iconSt ?>;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        <?php else: ?>
                        <svg style="width:17px;height:17px;stroke:<?= $iconSt ?>;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round" viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
                        <?php endif; ?>
                    </div>
                    <div class="module-info">
                        <div class="module-name"><?= htmlspecialchars($sec['title']) ?></div>
                        <div class="module-meta"><?= $qc ?> questions<?= $isMath && ($sec['module'] ?? 1) == 2 ? ' · Calculator permitted' : '' ?></div>
                    </div>
                    <div class="module-time"><?= $mins ?> min</div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Checklist -->
            <div class="checklist">
                <div class="checklist-title">Before you begin</div>
                <div class="check-row">
                    <div class="check-dot"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
                    <div class="check-text"><strong>Find a quiet space.</strong> You need uninterrupted time — about <?= $totalMin ?> minutes plus a 10-minute break.</div>
                </div>
                <div class="check-row">
                    <div class="check-dot"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
                    <div class="check-text"><strong>Calculator rules.</strong> No calculator on Math Module 1. Calculator allowed on Math Module 2.</div>
                </div>
                <div class="check-row">
                    <div class="check-dot"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
                    <div class="check-text"><strong>Progress auto-saves.</strong> If you lose connection, return to this page and resume your attempt.</div>
                </div>
                <div class="check-row">
                    <div class="check-dot"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
                    <div class="check-text"><strong>Section timers are real.</strong> Each section has its own countdown — when it ends, you move to the next section automatically.</div>
                </div>
            </div>

            <!-- Actions -->
            <div class="start-actions">
                <?php if ($inProgress): ?>
                <a href="/practice-tests/exam.php?attempt_id=<?= $inProgress['id'] ?>" class="btn btn-resume">
                    <svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    Resume Attempt
                </a>
                <?php endif; ?>
                <form method="POST" style="margin:0">
                    <input type="hidden" name="start" value="1">
                    <button type="submit" class="btn btn-primary">
                        <svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                        <?= $myCount > 0 ? 'New Attempt' : 'Begin Test' ?>
                    </button>
                </form>
                <a href="/practice-tests/" class="btn btn-ghost">
                    <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                    Cancel
                </a>
            </div>

        </div><!-- /start-body -->
    </div><!-- /start-card -->
</div><!-- /start-wrap -->
</main>

<script>
(function () {
    'use strict';

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