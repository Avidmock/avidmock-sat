<?php
/**
 * /practice-tests/index.php — Practice Tests Library
 * my.sat.avidmock.com/practice-tests/
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/StudyStreak.php';

Auth::requireStudent();
$userId    = $_SESSION['user_id'];
$user      = User::findById($userId);
$firstName = explode(' ', trim($user['name'] ?? $user['first_name'] ?? 'Student'))[0];

/* ── Streak ── */
$streakData    = StudyStreak::get($userId) ?? [];
$currentStreak = intval($streakData['current_streak'] ?? 0);

/* ── Test library ── */
$tests = Database::fetchAll(
    "SELECT t.*,
            (SELECT COUNT(*) FROM practice_test_attempts
             WHERE test_id = t.id AND user_id = ? AND status = 'submitted') AS attempts,
            (SELECT MAX(total_score) FROM practice_test_attempts
             WHERE test_id = t.id AND user_id = ? AND status = 'submitted') AS best_score,
            (SELECT MAX(rw_score) FROM practice_test_attempts
             WHERE test_id = t.id AND user_id = ? AND status = 'submitted') AS best_rw,
            (SELECT MAX(math_score) FROM practice_test_attempts
             WHERE test_id = t.id AND user_id = ? AND status = 'submitted') AS best_math,
            (SELECT ROUND(AVG(total_score)) FROM practice_test_attempts
             WHERE test_id = t.id AND status = 'submitted') AS avg_score_all,
            (SELECT id FROM practice_test_attempts
             WHERE test_id = t.id AND user_id = ? AND status = 'in_progress'
             ORDER BY started_at DESC LIMIT 1) AS in_progress_id
     FROM practice_tests t
     WHERE t.is_published = 1
     ORDER BY t.created_at ASC",
    [$userId, $userId, $userId, $userId, $userId]
);

/* ── Global stats ── */
$totalAttempts = (int) Database::fetchColumn(
    "SELECT COUNT(*) FROM practice_test_attempts WHERE user_id = ? AND status = 'submitted'",
    [$userId]
);
$bestEverScore = (int) Database::fetchColumn(
    "SELECT MAX(total_score) FROM practice_test_attempts WHERE user_id = ? AND status = 'submitted'",
    [$userId]
);
$bestRW = (int) Database::fetchColumn(
    "SELECT MAX(rw_score) FROM practice_test_attempts WHERE user_id = ? AND status = 'submitted'",
    [$userId]
);
$bestMath = (int) Database::fetchColumn(
    "SELECT MAX(math_score) FROM practice_test_attempts WHERE user_id = ? AND status = 'submitted'",
    [$userId]
);
$recent = Database::fetchAll(
    "SELECT total_score FROM practice_test_attempts
     WHERE user_id = ? AND status = 'submitted'
     ORDER BY submitted_at DESC LIMIT 5",
    [$userId]
);
$targetScore = (int)($user['target_score'] ?? 1400);

/* ── Score trend ── */
$trendUp   = count($recent) >= 2 && $recent[0]['total_score'] > $recent[1]['total_score'];
$trendFlat = count($recent) >= 2 && $recent[0]['total_score'] == $recent[1]['total_score'];

/* ── Page vars ── */
$activePage  = 'practice_tests';
$topbarTitle = 'Practice Tests';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Practice Tests — Avidmock SAT</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<link rel="icon" type="image/png" sizes="16x16"   href="/favicon-16x16.png">
<link rel="icon" type="image/png" sizes="32x32"   href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="192x192" href="/android-chrome-192x192.png">
<link rel="icon" type="image/png" sizes="512x512" href="/android-chrome-512x512.png">
<link rel="apple-touch-icon" sizes="180x180"      href="/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800;1,9..40,500&display=swap" rel="stylesheet">
<style>
/* ─────────────────────────────────────────────
   TOKENS  (unified platform)
───────────────────────────────────────────── */
:root {
    --dk: #143230;   --dk2: #1a3f3c;
    --ac: #1fe290;   --ac2: #17c87a;
    --tx: #1a1a2e;   --tx2: #4a4a5a;  --tx3: #8a8a9a;
    --bg: #f7faf9;   --bg2: #ffffff;  --bd: #e2ebe9;  --bd2: #d1dcd9;
    --warn: #f59e0b; --err: #ef4444;  --ok: #10b981;
    --blue: #3b82f6; --purple: #8b5cf6;
    --ff: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    --sidebar-w: 260px;
    --topbar-h: 64px;
    --r: 14px;
    --r-sm: 10px;
    --r-lg: 18px;
    --pad: 28px;
    --pad-sm: 16px;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body {
    font-family: var(--ff);
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    background: var(--bg);
    color: var(--tx);
    min-height: 100vh;
    overflow-x: hidden;
}
a { text-decoration: none; color: inherit; }
button { font-family: var(--ff); cursor: pointer; border: none; background: none; }

/* ─────────────────────────────────────────────
   SCROLL REVEAL
───────────────────────────────────────────── */
.sr { opacity: 0; transform: translateY(18px); transition: opacity .5s cubic-bezier(.16,1,.3,1), transform .5s cubic-bezier(.16,1,.3,1); }
.sr.v { opacity: 1; transform: none; }
.d1 { transition-delay: .06s; }
.d2 { transition-delay: .12s; }
.d3 { transition-delay: .18s; }
.d4 { transition-delay: .24s; }

/* ─────────────────────────────────────────────
   LAYOUT
───────────────────────────────────────────── */
.main-content {
    margin-left: var(--sidebar-w);
    margin-top: var(--topbar-h);
    padding: 32px var(--pad) 80px;
    min-height: calc(100vh - var(--topbar-h));
}
.sidebar-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,.45);
    z-index: 250;
    opacity: 0;
    transition: opacity .28s;
    pointer-events: none;
}
.sidebar-overlay.show { opacity: 1; pointer-events: all; }

/* ─────────────────────────────────────────────
   PAGE HEADER
───────────────────────────────────────────── */
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.page-title {
    font-size: clamp(1.25rem, 3vw, 1.625rem);
    font-weight: 800;
    color: var(--tx);
    letter-spacing: -.03em;
    line-height: 1.2;
}
.page-title em { font-style: normal; color: var(--ac); }
.page-subtitle { font-size: .875rem; color: var(--tx3); margin-top: 3px; }

.hdr-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 16px;
    border-radius: var(--r-sm);
    font-family: var(--ff);
    font-size: .8125rem;
    font-weight: 700;
    border: 1.5px solid var(--bd);
    background: var(--bg2);
    color: var(--tx2);
    transition: border-color .18s, color .18s, background .18s;
    white-space: nowrap;
    min-height: 38px;
}
.hdr-btn:hover { border-color: var(--ac); color: var(--dk); background: rgba(31,226,144,.04); }
.hdr-btn svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }

/* ─────────────────────────────────────────────
   SCORE HERO
───────────────────────────────────────────── */
.score-hero {
    background: var(--dk);
    border-radius: 20px;
    padding: 2.25rem 2.5rem;
    margin-bottom: 18px;
    position: relative;
    overflow: hidden;
}
.hero-bg-grid {
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(31,226,144,.025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(31,226,144,.025) 1px, transparent 1px);
    background-size: 36px 36px;
    pointer-events: none;
}
.hero-bg-glow {
    position: absolute;
    top: -80px; right: -80px;
    width: 480px; height: 480px;
    background: radial-gradient(circle, rgba(31,226,144,.07) 0%, transparent 65%);
    border-radius: 50%;
    pointer-events: none;
}
.hero-bg-glow2 {
    position: absolute;
    bottom: -60px; left: 6%;
    width: 220px; height: 220px;
    background: radial-gradient(circle, rgba(31,226,144,.04) 0%, transparent 65%);
    border-radius: 50%;
    pointer-events: none;
}

/* ── Empty hero ── */
.hero-empty {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 24px 0 16px;
    gap: 0;
}
.hero-empty-ico {
    width: 60px; height: 60px;
    border-radius: 16px;
    background: rgba(31,226,144,.1);
    border: 1px solid rgba(31,226,144,.18);
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 16px;
    flex-shrink: 0;
}
.hero-empty-ico svg {
    width: 28px; height: 28px;
    stroke: var(--ac); fill: none;
    stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round;
}
.hero-empty h2 {
    font-size: clamp(1.125rem, 3vw, 1.5rem);
    font-weight: 800;
    color: #fff;
    letter-spacing: -.025em;
    margin-bottom: 8px;
}
.hero-empty p {
    font-size: .9375rem;
    color: rgba(255,255,255,.42);
    max-width: 440px;
    margin: 0 auto 22px;
    line-height: 1.65;
}
.hero-cta-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 26px;
    background: var(--ac);
    color: var(--dk);
    border-radius: 11px;
    font-weight: 800;
    font-size: .875rem;
    font-family: var(--ff);
    transition: background .18s, transform .18s, box-shadow .18s;
    min-height: 44px;
    border: none;
    cursor: pointer;
}
.hero-cta-btn:hover { background: var(--ac2); transform: translateY(-2px); box-shadow: 0 8px 22px rgba(31,226,144,.3); }
.hero-cta-btn svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }

/* ── Has-attempts hero ── */
.hero-inner {
    display: flex;
    align-items: center;
    gap: 32px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
}

.hero-score-ring { width: 148px; height: 148px; position: relative; flex-shrink: 0; }
.hero-score-val  {
    position: absolute; inset: 0;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: 2px;
}
.hero-score-num {
    font-size: 40px;
    font-weight: 800;
    letter-spacing: -.04em;
    line-height: 1;
    background: linear-gradient(135deg, #1fe290, #00d47e);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.hero-score-lbl {
    font-size: .5rem;
    color: rgba(255,255,255,.35);
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
}

.hero-info { flex: 1; min-width: 200px; }
.hero-tag  {
    font-size: .5625rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .1em;
    color: rgba(255,255,255,.3);
    margin-bottom: 5px;
}
.hero-best {
    font-size: clamp(1.375rem, 3vw, 1.875rem);
    font-weight: 800;
    letter-spacing: -.03em;
    color: #fff;
    margin-bottom: 5px;
    line-height: 1.1;
}
.hero-target {
    font-size: .8125rem;
    color: rgba(255,255,255,.45);
    margin-bottom: 20px;
}
.hero-target strong { color: var(--ac); }
.hero-target-met {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: var(--ac);
    font-weight: 700;
    margin-left: 4px;
    font-size: .75rem;
}
.hero-target-met svg {
    width: 12px; height: 12px;
    stroke: currentColor; fill: none;
    stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round;
}

.hero-sections { display: flex; gap: 8px; flex-wrap: wrap; }
.hero-sec-box {
    padding: 10px 16px;
    background: rgba(255,255,255,.06);
    border-radius: 11px;
    border: 1px solid rgba(255,255,255,.07);
}
.hero-sec-lbl { font-size: .5625rem; color: rgba(255,255,255,.35); margin-bottom: 3px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
.hero-sec-val { font-size: 1.375rem; font-weight: 800; color: #fff; letter-spacing: -.03em; line-height: 1; }

.hero-right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 9px;
    flex-shrink: 0;
}
.hero-trend {
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 8px 14px;
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.07);
    border-radius: 10px;
    font-size: .8125rem;
    font-weight: 700;
    white-space: nowrap;
}
.hero-trend svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }
.trend-up   { color: var(--ac); }
.trend-flat { color: rgba(255,255,255,.45); }
.trend-down { color: #ff6b6b; }
.trend-sub  { color: rgba(255,255,255,.28); font-weight: 500; font-size: .6875rem; }

.hero-link {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 16px;
    background: rgba(31,226,144,.1);
    border: 1px solid rgba(31,226,144,.18);
    border-radius: 10px;
    color: var(--ac);
    font-size: .8125rem;
    font-weight: 700;
    transition: background .18s;
    white-space: nowrap;
    min-height: 38px;
}
.hero-link:hover { background: rgba(31,226,144,.18); }
.hero-link svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

/* ─────────────────────────────────────────────
   STAT STRIP
───────────────────────────────────────────── */
.stat-strip {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 18px;
}
.stat-pill {
    background: var(--bg2);
    border: 1px solid var(--bd);
    border-radius: var(--r-lg);
    padding: .9375rem 1.125rem;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: border-color .18s, box-shadow .18s;
}
.stat-pill:hover { border-color: rgba(31,226,144,.35); box-shadow: 0 4px 16px rgba(20,50,48,.06); }
.stat-ico {
    width: 38px; height: 38px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.stat-ico svg { width: 17px; height: 17px; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.ico-ac     { background: rgba(31,226,144,.1);  } .ico-ac   svg { stroke: var(--ac2); }
.ico-dk     { background: rgba(20,50,48,.07);   } .ico-dk   svg { stroke: var(--dk); }
.ico-warn   { background: rgba(245,158,11,.1);  } .ico-warn svg { stroke: var(--warn); }
.ico-blue   { background: rgba(59,130,246,.1);  } .ico-blue svg { stroke: var(--blue); }
.stat-info  { min-width: 0; }
.stat-label { font-size: .5625rem; font-weight: 800; color: var(--tx3); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 2px; }
.stat-val   { font-size: 1.375rem; font-weight: 800; color: var(--tx); letter-spacing: -.04em; line-height: 1; }
.stat-sub   { font-size: .6875rem; color: var(--tx3); margin-top: 2px; }

/* ─────────────────────────────────────────────
   SAT FORMAT BANNER
───────────────────────────────────────────── */
.sat-banner {
    background: var(--bg2);
    border: 1px solid var(--bd);
    border-radius: var(--r-lg);
    padding: 16px 22px;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
    transition: border-color .18s;
}
.sat-banner:hover { border-color: rgba(31,226,144,.3); }
.sat-banner-label {
    font-size: .75rem;
    font-weight: 800;
    color: var(--tx);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.sat-banner-label svg {
    width: 13px; height: 13px;
    stroke: var(--ac2); fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
}
.sat-modules {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;
}
.sat-mod {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 5px 11px;
    border-radius: 7px;
    font-size: .6875rem;
    font-weight: 700;
    white-space: nowrap;
}
.sat-mod-rw    { background: rgba(59,130,246,.07);  color: var(--blue);   border: 1px solid rgba(59,130,246,.15); }
.sat-mod-math  { background: rgba(139,92,246,.07);  color: var(--purple); border: 1px solid rgba(139,92,246,.15); }
.sat-mod-break { background: rgba(245,158,11,.07);  color: #d97706;       border: 1px solid rgba(245,158,11,.15); }
.sat-arrow     { color: var(--bd2); font-size: .75rem; flex-shrink: 0; }
.sat-total     { margin-left: auto; text-align: right; flex-shrink: 0; }
.sat-total-num { font-size: 1.25rem; font-weight: 800; color: var(--tx); letter-spacing: -.03em; }
.sat-total-sub { font-size: .6875rem; color: var(--tx3); margin-top: 2px; }

/* ─────────────────────────────────────────────
   FILTER BAR
───────────────────────────────────────────── */
.filter-bar {
    display: flex;
    gap: 6px;
    margin-bottom: 18px;
    overflow-x: auto;
    scrollbar-width: none;
    padding-bottom: 2px;
    -webkit-overflow-scrolling: touch;
}
.filter-bar::-webkit-scrollbar { display: none; }
.filter-btn {
    padding: 7px 15px;
    border-radius: 100px;
    border: 1.5px solid var(--bd);
    background: var(--bg2);
    font-size: .8125rem;
    font-weight: 600;
    color: var(--tx3);
    cursor: pointer;
    white-space: nowrap;
    transition: border-color .15s, color .15s, background .15s;
    font-family: var(--ff);
    min-height: 36px;
}
.filter-btn:hover { border-color: var(--ac2); color: var(--tx); }
.filter-btn.active { background: var(--ac); border-color: var(--ac); color: var(--dk); font-weight: 700; }

/* ─────────────────────────────────────────────
   TESTS GRID
───────────────────────────────────────────── */
.tests-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(330px, 1fr));
    gap: 16px;
}

/* ─────────────────────────────────────────────
   TEST CARD
───────────────────────────────────────────── */
.test-card {
    background: var(--bg2);
    border: 1.5px solid var(--bd);
    border-radius: var(--r-lg);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    position: relative;
    transition: transform .28s cubic-bezier(.16,1,.3,1), box-shadow .28s cubic-bezier(.16,1,.3,1), border-color .2s;
    animation: cardIn .5s cubic-bezier(.16,1,.3,1) both;
}
.test-card::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 3px;
    border-radius: 0 2px 2px 0;
    background: transparent;
    transition: background .2s;
}
.test-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 18px 44px rgba(20,50,48,.1);
    border-color: rgba(31,226,144,.4);
}
.test-card:hover::before { background: var(--ac); }
@keyframes cardIn {
    from { opacity: 0; transform: translateY(20px) scale(.97); }
    to   { opacity: 1; transform: none; }
}

/* Status badge */
.card-status-badge {
    position: absolute;
    top: 14px; right: 14px;
    font-size: .5rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .06em;
    padding: 3px 9px;
    border-radius: 100px;
}

/* Card header */
.card-header {
    padding: 20px 20px 14px;
    border-bottom: 1px solid var(--bd);
    display: flex;
    align-items: flex-start;
    gap: 14px;
}

/* Score ring */
.card-ring { width: 64px; height: 64px; position: relative; flex-shrink: 0; }
.card-ring-num {
    position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 800; line-height: 1;
}

/* No-score icon box */
.card-ico-box {
    width: 64px; height: 64px;
    border-radius: 14px;
    background: var(--bg);
    border: 1px solid var(--bd);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.card-ico-box svg {
    width: 28px; height: 28px;
    stroke: var(--bd2); fill: none;
    stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round;
}

.card-meta { flex: 1; min-width: 0; padding-top: 2px; }
.card-title {
    font-size: 1.0625rem;
    font-weight: 800;
    color: var(--tx);
    letter-spacing: -.02em;
    margin-bottom: 4px;
    padding-right: 70px;
    line-height: 1.25;
}
.card-desc {
    font-size: .8125rem;
    color: var(--tx3);
    line-height: 1.55;
    margin-bottom: 10px;
}
.card-chips { display: flex; gap: 5px; flex-wrap: wrap; }
.chip {
    font-size: .625rem;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 100px;
    white-space: nowrap;
}
.chip-teal   { background: rgba(31,226,144,.1);  color: var(--ac2); }
.chip-blue   { background: rgba(59,130,246,.1);  color: var(--blue); }
.chip-purple { background: rgba(139,92,246,.1);  color: var(--purple); }
.chip-gray   { background: var(--bg); color: var(--tx3); border: 1px solid var(--bd); }

/* Section score bars */
.card-scores {
    padding: 12px 20px;
    border-bottom: 1px solid var(--bd);
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 9px;
}
.sec-score {
    background: var(--bg);
    border-radius: 9px;
    padding: 9px 11px;
}
.sec-score-lbl {
    font-size: .5rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--tx3);
    margin-bottom: 3px;
}
.sec-score-val {
    font-size: 1.0625rem;
    font-weight: 800;
    letter-spacing: -.03em;
    line-height: 1;
}
.sec-score-bar {
    height: 3px;
    border-radius: 2px;
    background: var(--bd);
    margin-top: 6px;
    overflow: hidden;
}
.sec-score-fill {
    height: 100%;
    border-radius: 2px;
    transition: width .4s cubic-bezier(.16,1,.3,1);
}

/* Card footer */
.card-footer {
    padding: 11px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-top: auto;
}
.card-stats { display: flex; gap: 11px; flex-wrap: wrap; }
.card-stat  {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: .75rem;
    color: var(--tx3);
}
.card-stat svg {
    width: 12px; height: 12px;
    stroke: var(--tx3); fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
    opacity: .55;
    flex-shrink: 0;
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 9px 16px;
    border-radius: 9px;
    font-size: .8125rem;
    font-weight: 700;
    transition: all .18s;
    border: none;
    cursor: pointer;
    font-family: var(--ff);
    text-decoration: none;
    white-space: nowrap;
    min-height: 38px;
}
.btn svg {
    width: 13px; height: 13px;
    stroke: currentColor; fill: none;
    stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round;
    flex-shrink: 0;
}
.btn-mint  { background: var(--ac); color: var(--dk); }
.btn-mint:hover  { background: var(--ac2); box-shadow: 0 4px 14px rgba(31,226,144,.3); transform: translateY(-1px); }

.btn-ghost { background: var(--bg); color: var(--tx2); border: 1.5px solid var(--bd); }
.btn-ghost:hover { border-color: var(--ac); color: var(--dk); background: rgba(31,226,144,.03); }

.btn-amber { background: linear-gradient(135deg, #ffb347, #f59e0b); color: #7c3a00; }
.btn-amber:hover { transform: translateY(-1px); box-shadow: 0 5px 16px rgba(245,158,11,.28); }
.btn-amber svg { stroke: #7c3a00; }

.btn-group { display: flex; gap: 6px; }

/* ─────────────────────────────────────────────
   EMPTY STATE
───────────────────────────────────────────── */
.empty-state {
    text-align: center;
    padding: 64px 20px;
    grid-column: 1 / -1;
}
.empty-state-ico {
    width: 56px; height: 56px;
    border-radius: 16px;
    background: var(--bd);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px;
}
.empty-state-ico svg {
    width: 26px; height: 26px;
    stroke: var(--tx3); fill: none;
    stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round;
}
.empty-state h3 { font-size: 1.0625rem; font-weight: 800; color: var(--tx); margin-bottom: 6px; }
.empty-state p  { font-size: .9375rem; color: var(--tx3); line-height: 1.6; }

/* ─────────────────────────────────────────────
   RESPONSIVE
───────────────────────────────────────────── */
@media (max-width: 900px) {
    .main-content { margin-left: 0; padding: 20px var(--pad-sm) 80px; }
}

@media (max-width: 768px) {
    .stat-strip { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .hero-inner { flex-direction: column; gap: 20px; text-align: center; }
    .hero-right { align-items: center; flex-direction: row; flex-wrap: wrap; justify-content: center; }
    .hero-sections { justify-content: center; }
    .score-hero { padding: 1.375rem 1.25rem; }
    .hero-score-ring { width: 120px; height: 120px; }
    .hero-score-num  { font-size: 32px; }
    .page-header { flex-direction: column; align-items: flex-start; }
}

@media (max-width: 600px) {
    .stat-strip { grid-template-columns: repeat(2, 1fr); gap: 8px; }
    .stat-pill  { padding: .75rem .875rem; }
    .stat-ico   { width: 34px; height: 34px; }
    .stat-val   { font-size: 1.1875rem; }

    .tests-grid { grid-template-columns: 1fr; gap: 12px; }

    .sat-banner { flex-direction: column; gap: 12px; padding: 13px 16px; }
    .sat-total  { margin-left: 0; text-align: left; }

    .score-hero { padding: 1.125rem 1rem; border-radius: 16px; }
    .hero-score-ring { width: 100px; height: 100px; }
    .hero-score-num  { font-size: 26px; }
    .hero-sec-box    { padding: 8px 13px; }
    .hero-sec-val    { font-size: 1.125rem; }

    .card-header { padding: 16px 16px 12px; gap: 11px; }
    .card-scores { padding: 10px 16px; gap: 8px; }
    .card-footer { padding: 10px 16px; }
    .card-title  { font-size: .9375rem; padding-right: 60px; }
}

@media (max-width: 480px) {
    .stat-strip { gap: 6px; }
    .stat-pill  { padding: .625rem .75rem; border-radius: 12px; }
    .stat-ico   { display: none; }
    .filter-btn { padding: 6px 13px; font-size: .75rem; }
    .btn        { padding: 8px 13px; font-size: .75rem; }
    .card-status-badge { font-size: .4375rem; padding: 2px 7px; }
}

/* Safe area insets */
@supports (padding: max(0px)) {
    .main-content {
        padding-left:   max(var(--pad-sm), env(safe-area-inset-left));
        padding-right:  max(var(--pad-sm), env(safe-area-inset-right));
        padding-bottom: max(80px, calc(80px + env(safe-area-inset-bottom)));
    }
    @media (min-width: 901px) {
        .main-content {
            padding-left:  max(var(--pad), env(safe-area-inset-left));
            padding-right: max(var(--pad), env(safe-area-inset-right));
        }
    }
}
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/sidebar.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/topbar.php';
?>

<main class="main-content" id="mainContent">

    <!-- ── Page Header ── -->
    <div class="page-header sr">
        <div>
            <h1 class="page-title">Practice <em>Tests</em></h1>
            <p class="page-subtitle">Full-length SAT simulations &middot; Real format &middot; Scored 400–1600</p>
        </div>
        <?php if ($totalAttempts > 0): ?>
        <a href="/practice-tests/history/" class="hdr-btn">
            <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
            History
        </a>
        <?php endif; ?>
    </div>

    <!-- ── Score Hero ── -->
    <div class="score-hero sr d1">
        <div class="hero-bg-grid"></div>
        <div class="hero-bg-glow"></div>
        <div class="hero-bg-glow2"></div>

        <?php if ($totalAttempts === 0): ?>
        <div class="hero-empty">
            <div class="hero-empty-ico">
                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            </div>
            <h2>Take Your First Practice Test</h2>
            <p>Complete a full-length SAT to track your score, find your weak spots, and build a smarter study plan.</p>
            <?php if (!empty($tests)): ?>
            <a href="/practice-tests/start/?test_id=<?= intval($tests[0]['id']) ?>" class="hero-cta-btn">
                <svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                Start Test #1
            </a>
            <?php endif; ?>
        </div>

        <?php else: ?>
        <div class="hero-inner">

            <!-- Score ring -->
            <div class="hero-score-ring">
                <svg width="148" height="148" viewBox="0 0 148 148" style="transform:rotate(-90deg)">
                    <circle cx="74" cy="74" r="63" fill="none" stroke="rgba(255,255,255,.08)" stroke-width="9"/>
                    <circle cx="74" cy="74" r="63" fill="none" stroke="url(#heroGrad)" stroke-width="9"
                            stroke-dasharray="<?= round(2 * M_PI * 63) ?>"
                            stroke-dashoffset="<?= round(2 * M_PI * 63 * (1 - $bestEverScore / 1600)) ?>"
                            stroke-linecap="round"/>
                    <defs>
                        <linearGradient id="heroGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                            <stop offset="0%" style="stop-color:#1fe290"/>
                            <stop offset="100%" style="stop-color:#00d47e"/>
                        </linearGradient>
                    </defs>
                </svg>
                <div class="hero-score-val">
                    <div class="hero-score-num"><?= $bestEverScore ?></div>
                    <div class="hero-score-lbl">Best Score</div>
                </div>
            </div>

            <!-- Info -->
            <div class="hero-info">
                <div class="hero-tag">Personal Best</div>
                <div class="hero-best"><?= $bestEverScore ?> / 1600</div>
                <div class="hero-target">
                    Target: <strong><?= $targetScore ?></strong>
                    <?php $gap = $targetScore - $bestEverScore; ?>
                    <?php if ($gap <= 0): ?>
                    <span class="hero-target-met">
                        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                        Target reached!
                    </span>
                    <?php else: ?>
                    &middot; <span style="color:rgba(255,255,255,.4)"><?= $gap ?> pts to go</span>
                    <?php endif; ?>
                </div>
                <?php if ($bestRW || $bestMath): ?>
                <div class="hero-sections">
                    <?php if ($bestRW): ?>
                    <div class="hero-sec-box">
                        <div class="hero-sec-lbl">Reading &amp; Writing</div>
                        <div class="hero-sec-val"><?= $bestRW ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($bestMath): ?>
                    <div class="hero-sec-box">
                        <div class="hero-sec-lbl">Math</div>
                        <div class="hero-sec-val"><?= $bestMath ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right column -->
            <div class="hero-right">
                <?php if (count($recent) >= 2): ?>
                <div class="hero-trend">
                    <?php if ($trendUp): ?>
                    <svg class="trend-up" viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                    <span class="trend-up">Improving</span>
                    <?php elseif ($trendFlat): ?>
                    <svg class="trend-flat" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <span class="trend-flat">Steady</span>
                    <?php else: ?>
                    <svg class="trend-down" viewBox="0 0 24 24"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/></svg>
                    <span class="trend-down">Dropped</span>
                    <?php endif; ?>
                    <span class="trend-sub">last 2 tests</span>
                </div>
                <?php endif; ?>
                <a href="/practice-tests/history/" class="hero-link">
                    Full History
                    <svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </a>
            </div>

        </div>
        <?php endif; ?>
    </div>

    <!-- ── Stat Strip ── -->
    <?php if ($totalAttempts > 0): ?>
    <div class="stat-strip sr d2">
        <div class="stat-pill">
            <div class="stat-ico ico-ac">
                <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
            </div>
            <div class="stat-info">
                <div class="stat-label">Best Score</div>
                <div class="stat-val"><?= $bestEverScore ?></div>
                <div class="stat-sub">out of 1600</div>
            </div>
        </div>
        <div class="stat-pill">
            <div class="stat-ico ico-dk">
                <svg viewBox="0 0 24 24"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>
            </div>
            <div class="stat-info">
                <div class="stat-label">Tests Taken</div>
                <div class="stat-val"><?= $totalAttempts ?></div>
                <div class="stat-sub">completed</div>
            </div>
        </div>
        <div class="stat-pill">
            <div class="stat-ico ico-blue">
                <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
            </div>
            <div class="stat-info">
                <div class="stat-label">R&amp;W Best</div>
                <div class="stat-val"><?= $bestRW ?: '—' ?></div>
                <div class="stat-sub">out of 800</div>
            </div>
        </div>
        <div class="stat-pill">
            <div class="stat-ico ico-warn">
                <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            </div>
            <div class="stat-info">
                <div class="stat-label">Math Best</div>
                <div class="stat-val"><?= $bestMath ?: '—' ?></div>
                <div class="stat-sub">out of 800</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── SAT Format Banner ── -->
    <div class="sat-banner sr d2">
        <div style="flex:1;min-width:0">
            <div class="sat-banner-label">
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Real Digital SAT Format — 4 Modules
            </div>
            <div class="sat-modules">
                <span class="sat-mod sat-mod-rw">R&amp;W Module 1 · 32 min · 27 Q</span>
                <span class="sat-arrow">&rsaquo;</span>
                <span class="sat-mod sat-mod-rw">R&amp;W Module 2 · 32 min · 27 Q</span>
                <span class="sat-arrow">&rsaquo;</span>
                <span class="sat-mod sat-mod-break">10 min Break</span>
                <span class="sat-arrow">&rsaquo;</span>
                <span class="sat-mod sat-mod-math">Math Module 1 · 35 min · 22 Q</span>
                <span class="sat-arrow">&rsaquo;</span>
                <span class="sat-mod sat-mod-math">Math Module 2 · 35 min · 22 Q</span>
            </div>
        </div>
        <div class="sat-total">
            <div class="sat-total-num">98 Questions</div>
            <div class="sat-total-sub">2h 14min &middot; Scored 400–1600</div>
        </div>
    </div>

    <!-- ── Filter Bar ── -->
    <div class="filter-bar sr d3" id="filterBar">
        <button class="filter-btn active" data-filter="all">All Tests</button>
        <button class="filter-btn" data-filter="not-started">Not Started</button>
        <button class="filter-btn" data-filter="in-progress">In Progress</button>
        <button class="filter-btn" data-filter="completed">Completed</button>
    </div>

    <!-- ── Test Cards ── -->
    <div class="tests-grid sr d4" id="testsGrid">
        <?php if (!empty($tests)): ?>
        <?php foreach ($tests as $i => $test):
            $status      = 'not-started';
            $statusLabel = 'Not Started';
            $statusBg    = 'rgba(20,50,48,.07)';
            $statusColor = 'var(--tx3)';
            $hasInProgress = !empty($test['in_progress_id']);

            if ($hasInProgress) {
                $status      = 'in-progress';
                $statusLabel = 'In Progress';
                $statusBg    = 'rgba(245,158,11,.12)';
                $statusColor = '#d97706';
            } elseif ($test['attempts'] > 0) {
                $status      = 'completed';
                $statusLabel = $test['attempts'] . ' Attempt' . ($test['attempts'] > 1 ? 's' : '');
                $statusBg    = 'rgba(31,226,144,.12)';
                $statusColor = 'var(--ac2)';
            }

            $totalMins    = round(($test['total_time'] ?? 8040) / 60);
            $bestScore    = (int)($test['best_score'] ?? 0);
            $bestRwCard   = (int)($test['best_rw']    ?? 0);
            $bestMathCard = (int)($test['best_math']  ?? 0);

            if ($bestScore >= 1300)     $ringColor = '#1fe290';
            elseif ($bestScore >= 1100) $ringColor = '#ffb347';
            else                        $ringColor = '#ff6b6b';
            $scorePct = $bestScore > 0 ? min(100, round($bestScore / 1600 * 100)) : 0;

            $sectionCount = Database::fetchColumn(
                "SELECT COUNT(*) FROM practice_test_sections WHERE test_id = ?",
                [$test['id']]
            );
        ?>
        <div class="test-card"
             data-status="<?= $status ?>"
             style="animation-delay:<?= round(.04 + $i * .065, 3) ?>s">

            <div class="card-status-badge" style="background:<?= $statusBg ?>;color:<?= $statusColor ?>">
                <?= htmlspecialchars($statusLabel) ?>
            </div>

            <!-- Header -->
            <div class="card-header">
                <?php if ($bestScore > 0): ?>
                <div class="card-ring">
                    <svg width="64" height="64" viewBox="0 0 64 64" style="transform:rotate(-90deg)">
                        <circle cx="32" cy="32" r="26" fill="none" stroke="var(--bd)" stroke-width="5"/>
                        <circle cx="32" cy="32" r="26" fill="none" stroke="<?= $ringColor ?>" stroke-width="5"
                                stroke-dasharray="<?= round(2 * M_PI * 26) ?>"
                                stroke-dashoffset="<?= round(2 * M_PI * 26 * (1 - $scorePct / 100)) ?>"
                                stroke-linecap="round"/>
                    </svg>
                    <div class="card-ring-num" style="color:<?= $ringColor ?>"><?= $bestScore ?></div>
                </div>
                <?php else: ?>
                <div class="card-ico-box">
                    <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                </div>
                <?php endif; ?>

                <div class="card-meta">
                    <div class="card-title"><?= htmlspecialchars($test['title']) ?></div>
                    <div class="card-desc"><?= htmlspecialchars($test['description'] ?? 'Full-length SAT practice test with real format, timing, and scaled scoring.') ?></div>
                    <div class="card-chips">
                        <?php if (!empty($test['avg_score_all'])): ?>
                        <span class="chip chip-gray">Avg: <?= $test['avg_score_all'] ?></span>
                        <?php endif; ?>
                        <span class="chip chip-teal"><?= $totalMins ?> min</span>
                        <?php if ($sectionCount == 4): ?>
                        <span class="chip chip-blue">4 Modules</span>
                        <?php elseif ($sectionCount > 0): ?>
                        <span class="chip chip-gray"><?= $sectionCount ?> Modules</span>
                        <?php endif; ?>
                        <?php if (($test['type'] ?? '') === 'full_length'): ?>
                        <span class="chip chip-purple">Full SAT</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Section scores -->
            <?php if ($bestRwCard || $bestMathCard): ?>
            <div class="card-scores">
                <div class="sec-score">
                    <div class="sec-score-lbl">R&amp;W</div>
                    <div class="sec-score-val" style="color:var(--blue)"><?= $bestRwCard ?: '—' ?></div>
                    <div class="sec-score-bar">
                        <div class="sec-score-fill" style="width:<?= $bestRwCard ? round($bestRwCard / 800 * 100) : 0 ?>%;background:var(--blue)"></div>
                    </div>
                </div>
                <div class="sec-score">
                    <div class="sec-score-lbl">Math</div>
                    <div class="sec-score-val" style="color:var(--purple)"><?= $bestMathCard ?: '—' ?></div>
                    <div class="sec-score-bar">
                        <div class="sec-score-fill" style="width:<?= $bestMathCard ? round($bestMathCard / 800 * 100) : 0 ?>%;background:var(--purple)"></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Footer -->
            <div class="card-footer">
                <div class="card-stats">
                    <div class="card-stat">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <?= $totalMins ?> min
                    </div>
                    <?php if ($test['attempts'] > 0 && !empty($test['avg_score_all'])): ?>
                    <div class="card-stat">
                        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                        <?= number_format((float)$test['avg_score_all']) ?> avg
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($hasInProgress): ?>
                <a href="/practice-tests/exam/?attempt_id=<?= intval($test['in_progress_id']) ?>" class="btn btn-amber">
                    <svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    Resume
                </a>
                <?php elseif ($test['attempts'] > 0): ?>
                <div class="btn-group">
                    <a href="/practice-tests/results/?test_id=<?= intval($test['id']) ?>"   class="btn btn-ghost">Results</a>
                    <a href="/practice-tests/start/?test_id=<?= intval($test['id']) ?>"     class="btn btn-mint">Retake</a>
                </div>
                <?php else: ?>
                <a href="/practice-tests/start/?test_id=<?= intval($test['id']) ?>" class="btn btn-mint">
                    Start Test
                    <svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </a>
                <?php endif; ?>
            </div>

        </div>
        <?php endforeach; ?>

        <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-ico">
                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            </div>
            <h3>No tests available yet</h3>
            <p>New practice tests are added regularly. Check back soon.</p>
        </div>
        <?php endif; ?>
    </div>

</main>

<script>
(function () {
    'use strict';

    /* Scroll reveal */
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (e.isIntersecting) { e.target.classList.add('v'); io.unobserve(e.target); }
        });
    }, { threshold: .04, rootMargin: '0px 0px -16px 0px' });
    document.querySelectorAll('.sr').forEach(function (el) { io.observe(el); });

    /* Sidebar overlay */
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

    /* Filter */
    var fb = document.getElementById('filterBar');
    if (fb) {
        fb.addEventListener('click', function (e) {
            var btn = e.target.closest('.filter-btn');
            if (!btn) return;
            document.querySelectorAll('.filter-btn').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            var f = btn.dataset.filter;
            document.querySelectorAll('.test-card').forEach(function (c) {
                c.style.display = (f === 'all' || c.dataset.status === f) ? '' : 'none';
            });
        });
    }

    /* Ctrl/Cmd+K topbar search */
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