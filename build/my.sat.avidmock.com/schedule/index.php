<?php
/**
 * /schedule/index.php — Student Schedule & Study Plan
 * my.sat.avidmock.com/schedule/index.php
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Schedule.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/StudyStreak.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Session.php';

Auth::requireStudent();
$userId    = $_SESSION['user_id'];
$user      = User::findById($userId);
$firstName = explode(' ', $user['name'] ?? 'Student')[0];

/* ── Test date & days remaining ── */
$testDate      = $user['test_date'] ?? null;
$targetScore   = (int)($user['target_score'] ?? 1200);
$studyHours    = (int)($user['study_hours'] ?? 2);
$daysUntilTest = null;
$testDateLabel = null;
if ($testDate) {
    $ts = strtotime($testDate);
    if ($ts && $ts > time()) {
        $daysUntilTest = (int) ceil(($ts - time()) / 86400);
        $testDateLabel = date('M j, Y', $ts);
    }
}

/* ── Today's plan ── */
$todayPlan    = Schedule::getToday($userId) ?? [];
$tasksDone    = count(array_filter($todayPlan, fn($t) => !empty($t['is_complete'])));
$tasksTotal   = count($todayPlan);
$planPct      = $tasksTotal > 0 ? (int) round(($tasksDone / $tasksTotal) * 100) : 0;
$totalMinutes = (int) array_sum(array_column($todayPlan, 'duration_min'));

/* ── Weekly plan (Mon–Sun of current week) ── */
$weekStart = strtotime('monday this week');
$weekDays  = [];
for ($d = 0; $d < 7; $d++) {
    $dayTs  = $weekStart + ($d * 86400);
    $dayKey = date('Y-m-d', $dayTs);
    $weekDays[$dayKey] = [
        'label'    => date('D', $dayTs),
        'num'      => date('j', $dayTs),
        'month'    => date('M', $dayTs),
        'is_today' => $dayKey === date('Y-m-d'),
        'is_past'  => $dayTs < strtotime('today'),
        'tasks'    => Schedule::getRange($userId, $dayKey, $dayKey) ?? [],
    ];
}

/* ── Streak ── */
$streakData    = StudyStreak::get($userId) ?? [];
$currentStreak = intval($streakData['current_streak'] ?? 0);
$longestStreak = intval($streakData['longest_streak'] ?? 0);

/* ── Upcoming sessions ── */
$upcomingSessions = Session::getUpcoming($userId, 3) ?? [];

/* ── Spaced review count ── */
$spacedCount = 0;

/* ── Has schedule been generated? ── */
$hasSchedule = !empty($todayPlan);

/* ── Variables consumed by shared includes ── */
$activePage  = 'schedule';
$topbarTitle = 'My Schedule — ' . date('l, F j');
$topbarSub   = $tasksTotal > 0
    ? $tasksDone . '/' . $tasksTotal . ' tasks complete today' . ($totalMinutes > 0 ? ' · ' . $totalMinutes . ' min planned' : '')
    : ($hasSchedule ? 'No tasks scheduled for today' : 'Generate your personalised AI study plan');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Schedule — Avidmock SAT</title>
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
   TOKENS
───────────────────────────────────────────── */
:root {
    --dk: #143230;   --dk2: #1a3f3c;
    --ac: #1fe290;   --ac2: #17c87a;
    --tx: #1a1a2e;   --tx2: #4a4a5a;  --tx3: #8a8a9a;
    --bg: #f7faf9;   --bg2: #ffffff;  --bd: #e2ebe9;
    --err: #e74c3c;  --warn: #f39c12; --ok: #10b981;
    --ff: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    --sidebar-w: 260px;
    --topbar-h: 64px;
    --r: 14px;
    --r-sm: 10px;
    --r-lg: 18px;
    --content-pad: 28px;
    --content-pad-sm: 16px;
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
img { max-width: 100%; }

/* ─────────────────────────────────────────────
   SCROLL REVEAL
───────────────────────────────────────────── */
.sr { opacity: 0; transform: translateY(18px); transition: opacity .5s cubic-bezier(.16,1,.3,1), transform .5s cubic-bezier(.16,1,.3,1); }
.sr.v { opacity: 1; transform: none; }
.d1 { transition-delay: .06s; }
.d2 { transition-delay: .12s; }
.d3 { transition-delay: .18s; }
.d4 { transition-delay: .24s; }
.d5 { transition-delay: .30s; }

/* ─────────────────────────────────────────────
   LAYOUT SHELL
───────────────────────────────────────────── */
.main-content {
    margin-left: var(--sidebar-w);
    margin-top: var(--topbar-h);
    padding: 32px var(--content-pad) 80px;
    min-height: calc(100vh - var(--topbar-h));
    max-width: calc(1280px + var(--sidebar-w));
}

.sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, .45);
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
    margin-bottom: 24px;
    flex-wrap: wrap;
}
.page-title {
    font-size: clamp(1.25rem, 3vw, 1.625rem);
    font-weight: 800;
    color: var(--tx);
    letter-spacing: -.03em;
    line-height: 1.2;
}
.page-title span { color: var(--ac); }
.page-subtitle { font-size: .875rem; color: var(--tx3); margin-top: 3px; }
.header-actions {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}

/* Header buttons */
.hdr-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 10px 16px;
    border-radius: var(--r-sm);
    font-family: var(--ff);
    font-size: .8125rem;
    font-weight: 700;
    text-decoration: none;
    border: 1.5px solid var(--bd);
    background: var(--bg2);
    color: var(--tx2);
    transition: border-color .2s, color .2s, background .2s, transform .2s, box-shadow .2s;
    cursor: pointer;
    white-space: nowrap;
}
.hdr-btn:hover { border-color: var(--ac); color: var(--dk); }
.hdr-btn svg { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }

.hdr-btn-primary { background: var(--dk); color: #fff; border-color: transparent; }
.hdr-btn-primary:hover { background: var(--dk2); box-shadow: 0 6px 18px rgba(20,50,48,.18); transform: translateY(-1px); }
.hdr-btn-primary svg { stroke: var(--ac); }

.hdr-btn-ac { background: var(--ac); color: var(--dk); border-color: transparent; }
.hdr-btn-ac:hover { background: var(--ac2); box-shadow: 0 6px 18px rgba(31,226,144,.28); transform: translateY(-1px); }

/* ─────────────────────────────────────────────
   STAT STRIP
───────────────────────────────────────────── */
.stat-strip {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}
.stat-pill {
    background: var(--bg2);
    border: 1px solid var(--bd);
    border-radius: var(--r-lg);
    padding: 1rem 1.125rem;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: border-color .2s, box-shadow .2s;
}
.stat-pill:hover { border-color: rgba(31,226,144,.35); box-shadow: 0 4px 16px rgba(20,50,48,.06); }
.stat-pill-ico {
    width: 40px;
    height: 40px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.stat-pill-ico svg { width: 18px; height: 18px; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.ico-ac   { background: rgba(31,226,144,.1); }  .ico-ac   svg { stroke: var(--ac2); }
.ico-dk   { background: rgba(20,50,48,.07); }   .ico-dk   svg { stroke: var(--dk); }
.ico-warn { background: rgba(243,156,18,.1); }  .ico-warn svg { stroke: var(--warn); }
.ico-err  { background: rgba(231,76,60,.08); }  .ico-err  svg { stroke: var(--err); }
.stat-pill-label { font-size: .6875rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: .4px; margin-bottom: 2px; }
.stat-pill-val { font-size: 1.375rem; font-weight: 800; color: var(--tx); letter-spacing: -.04em; line-height: 1; }
.stat-pill-val span { color: var(--ac); font-size: .875rem; font-weight: 600; }
.stat-pill-sub { font-size: .6875rem; color: var(--tx3); margin-top: 3px; }

/* ─────────────────────────────────────────────
   2-COL LAYOUT
───────────────────────────────────────────── */
.layout-2col {
    display: grid;
    grid-template-columns: 1fr 330px;
    gap: 16px;
    align-items: start;
}

/* ─────────────────────────────────────────────
   WEEKLY CALENDAR
───────────────────────────────────────────── */
.week-card {
    background: var(--bg2);
    border: 1px solid var(--bd);
    border-radius: var(--r-lg);
    overflow: hidden;
    margin-bottom: 16px;
}
.week-card-header {
    padding: .875rem 1.25rem;
    border-bottom: 1px solid var(--bd);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.week-card-title { font-size: 1rem; font-weight: 800; color: var(--tx); letter-spacing: -.02em; }
.week-nav-label  { font-size: .8125rem; font-weight: 600; color: var(--tx3); }

.week-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
}
.week-day {
    padding: .75rem .5rem .625rem;
    text-align: center;
    border-right: 1px solid var(--bd);
    min-height: 110px;
    position: relative;
    transition: background .15s;
}
.week-day:last-child { border-right: none; }
.week-day.today { background: rgba(31,226,144,.04); }
.week-day.past  { opacity: .65; }

.week-day-label { font-size: .5625rem; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; color: var(--tx3); }
.week-day-num   { font-size: 1.0625rem; font-weight: 800; color: var(--tx); letter-spacing: -.03em; line-height: 1.2; margin-top: 1px; }
.week-day.today .week-day-num   { color: var(--ac2); }
.week-day.today .week-day-label { color: var(--ac2); }

.week-day-dot { width: 5px; height: 5px; border-radius: 50%; background: var(--ac); margin: 3px auto 0; }

.week-tasks { display: flex; flex-direction: column; gap: 3px; margin-top: 5px; }
.week-task-chip {
    font-size: .575rem;
    font-weight: 700;
    padding: 2px 5px;
    border-radius: 4px;
    text-align: left;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.chip-lesson  { background: rgba(20,50,48,.07);    color: var(--dk); }
.chip-quiz    { background: rgba(31,226,144,.1);   color: var(--ac2); }
.chip-test    { background: rgba(243,156,18,.1);   color: #a06a00; }
.chip-review  { background: rgba(88,101,242,.1);   color: #5865f2; }
.week-task-more { font-size: .575rem; color: var(--tx3); font-weight: 600; text-align: center; margin-top: 2px; }

/* ─────────────────────────────────────────────
   TODAY CARD + TIMELINE
───────────────────────────────────────────── */
.today-card {
    background: var(--bg2);
    border: 1px solid var(--bd);
    border-radius: var(--r-lg);
    overflow: hidden;
}
.today-card-header {
    padding: 1.125rem 1.375rem;
    border-bottom: 1px solid var(--bd);
    display: flex;
    align-items: center;
    gap: 10px;
}
.today-card-title { font-size: 1rem; font-weight: 800; color: var(--tx); letter-spacing: -.02em; flex: 1; }
.today-date-badge {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 5px 11px;
    background: rgba(20,50,48,.06);
    border-radius: 50px;
    font-size: .75rem;
    font-weight: 700;
    color: var(--dk);
    white-space: nowrap;
}
.today-date-badge svg { width: 12px; height: 12px; stroke: var(--dk); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

/* Progress */
.today-progress {
    padding: 1rem 1.375rem;
    border-bottom: 1px solid var(--bd);
}
.progress-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.progress-label { font-size: .8125rem; font-weight: 600; color: var(--tx2); }
.progress-pct   { font-size: .8125rem; font-weight: 800; color: var(--ac2); }
.progress-rail  { height: 5px; background: var(--bd); border-radius: 3px; overflow: hidden; }
.progress-fill  { height: 100%; background: linear-gradient(90deg, var(--ac2), var(--ac)); border-radius: 3px; transition: width 1.2s cubic-bezier(.16,1,.3,1); width: 0%; }
.progress-meta  { font-size: .75rem; color: var(--tx3); margin-top: 6px; display: flex; gap: 14px; flex-wrap: wrap; }

/* Timeline */
.timeline { padding: 1.125rem 1.375rem; }

.timeline-item {
    display: flex;
    align-items: flex-start;
    gap: 0;
    position: relative;
    margin-bottom: 2px;
}
.timeline-left {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 36px;
    flex-shrink: 0;
}
.timeline-dot {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    z-index: 1;
    border: 2px solid var(--bd);
    background: var(--bg2);
    transition: all .2s;
}
.timeline-dot.done   { background: var(--ac); border-color: var(--ac); }
.timeline-dot.done svg.check { display: block !important; }
.timeline-dot.active { background: var(--dk); border-color: var(--dk); box-shadow: 0 0 0 4px rgba(31,226,144,.15); animation: activePulse 2s ease-in-out infinite; }
.timeline-dot.active svg.ring { display: block !important; }

@keyframes activePulse {
    0%, 100% { box-shadow: 0 0 0 4px rgba(31,226,144,.15); }
    50%       { box-shadow: 0 0 0 8px rgba(31,226,144,.06); }
}
.timeline-dot svg { width: 10px; height: 10px; fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; display: none; }
.timeline-dot svg.check { stroke: var(--dk); }
.timeline-dot svg.ring  { stroke: var(--ac); }

.timeline-line {
    width: 2px;
    flex: 1;
    min-height: 18px;
    background: var(--bd);
    margin-top: 0;
}
.timeline-item:last-child .timeline-line { display: none; }
.timeline-item.done-line .timeline-line  { background: var(--ac); }

/* Task card */
.timeline-content {
    flex: 1;
    margin-left: 10px;
    margin-bottom: 12px;
    background: var(--bg);
    border: 1px solid var(--bd);
    border-radius: 12px;
    padding: .75rem .9rem;
    text-decoration: none;
    display: block;
    transition: border-color .2s, background .2s, transform .2s, box-shadow .2s;
    cursor: pointer;
}
.timeline-content:hover        { border-color: rgba(31,226,144,.4); background: rgba(31,226,144,.02); transform: translateX(2px); box-shadow: 0 2px 12px rgba(20,50,48,.06); }
.timeline-content.active-task  { border-color: rgba(20,50,48,.18); background: var(--bg2); box-shadow: 0 3px 14px rgba(20,50,48,.07); }
.timeline-content.done-task    { opacity: .55; }
.timeline-content.done-task .task-title { text-decoration: line-through; color: var(--tx3); }

.task-top { display: flex; align-items: center; gap: 7px; margin-bottom: 4px; flex-wrap: wrap; }
.task-type-badge { font-size: .5625rem; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; padding: 2px 7px; border-radius: 4px; white-space: nowrap; }
.badge-lesson  { background: rgba(20,50,48,.07);   color: var(--dk); }
.badge-quiz    { background: rgba(31,226,144,.1);  color: var(--ac2); }
.badge-test    { background: rgba(243,156,18,.1);  color: #a06a00; }
.badge-review  { background: rgba(88,101,242,.1);  color: #5865f2; }
.badge-tutor   { background: rgba(20,50,48,.05);   color: var(--tx2); }

.task-title { font-size: .875rem; font-weight: 700; color: var(--tx); line-height: 1.3; }
.task-meta  {
    font-size: .75rem;
    color: var(--tx3);
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 4px;
    flex-wrap: wrap;
}
.task-meta svg { width: 11px; height: 11px; stroke: var(--tx3); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }

.task-actions { display: flex; gap: 6px; margin-top: 8px; flex-wrap: wrap; }
.task-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 11px;
    border-radius: 7px;
    font-size: .75rem;
    font-weight: 700;
    border: 1.5px solid var(--bd);
    background: var(--bg2);
    color: var(--tx2);
    text-decoration: none;
    font-family: var(--ff);
    cursor: pointer;
    transition: all .18s;
    white-space: nowrap;
    /* Minimum 44px touch target */
    min-height: 32px;
}
.task-btn:hover { border-color: var(--ac); color: var(--dk); }
.task-btn svg   { width: 11px; height: 11px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }
.task-btn-primary { background: var(--dk); color: #fff; border-color: transparent; }
.task-btn-primary:hover { background: var(--dk2); box-shadow: 0 3px 10px rgba(20,50,48,.18); }
.task-btn-primary svg { stroke: var(--ac); }
.task-btn-done { background: rgba(31,226,144,.07); color: var(--ac2); border-color: rgba(31,226,144,.18); cursor: default; }

/* Empty plan */
.empty-plan { padding: 3rem 2rem; text-align: center; }
.empty-plan svg { width: 44px; height: 44px; stroke: var(--bd); fill: none; stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round; margin: 0 auto 1rem; display: block; }
.empty-plan h3  { font-size: 1rem; font-weight: 800; color: var(--tx); margin-bottom: .5rem; }
.empty-plan p   { font-size: .875rem; color: var(--tx3); line-height: 1.6; max-width: 320px; margin: 0 auto 1.25rem; }

/* ─────────────────────────────────────────────
   RIGHT SIDEBAR CARDS
───────────────────────────────────────────── */
.right-col { display: flex; flex-direction: column; gap: 14px; }

.side-card { background: var(--bg2); border: 1px solid var(--bd); border-radius: var(--r-lg); overflow: hidden; }
.side-card-hd {
    padding: .875rem 1.125rem;
    border-bottom: 1px solid var(--bd);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.side-card-title { font-size: .9375rem; font-weight: 800; color: var(--tx); letter-spacing: -.015em; }
.side-card-link  { font-size: .75rem; font-weight: 700; color: var(--ac2); text-decoration: none; transition: color .15s; }
.side-card-link:hover { color: var(--ac); }
.side-card-body  { padding: 1rem 1.125rem; }

/* Test countdown */
.test-countdown {
    background: var(--dk);
    border-radius: var(--r-lg);
    padding: 1.375rem;
    position: relative;
    overflow: hidden;
}
.test-countdown::before {
    content: '';
    position: absolute;
    top: -60px; right: -60px;
    width: 200px; height: 200px;
    background: radial-gradient(circle, rgba(31,226,144,.08) 0%, transparent 60%);
    border-radius: 50%;
    pointer-events: none;
}
.test-countdown::after {
    content: '';
    position: absolute;
    inset: 0;
    background-image: linear-gradient(rgba(31,226,144,.02) 1px, transparent 1px), linear-gradient(90deg, rgba(31,226,144,.02) 1px, transparent 1px);
    background-size: 24px 24px;
    pointer-events: none;
}
.countdown-label {
    font-size: .5625rem;
    font-weight: 800;
    color: var(--ac);
    text-transform: uppercase;
    letter-spacing: .8px;
    margin-bottom: .5rem;
    display: flex;
    align-items: center;
    gap: 5px;
    position: relative;
    z-index: 1;
}
.countdown-label-dot { width: 5px; height: 5px; border-radius: 50%; background: var(--ac); }
.countdown-days {
    font-size: 3rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: -.06em;
    line-height: 1;
    position: relative;
    z-index: 1;
}
.countdown-days span { font-size: 1rem; font-weight: 600; color: rgba(255,255,255,.4); letter-spacing: 0; }
.countdown-date {
    font-size: .8125rem;
    color: rgba(255,255,255,.38);
    margin-top: .25rem;
    font-weight: 500;
    position: relative;
    z-index: 1;
}
.countdown-bar {
    height: 3px;
    background: rgba(255,255,255,.08);
    border-radius: 2px;
    margin-top: 1rem;
    overflow: hidden;
    position: relative;
    z-index: 1;
}
.countdown-bar-fill {
    height: 100%;
    background: var(--ac);
    border-radius: 2px;
    transition: width 1.5s cubic-bezier(.16,1,.3,1);
    width: 0%;
}
.countdown-progress-label {
    display: flex;
    justify-content: space-between;
    margin-top: 5px;
    font-size: .6875rem;
    font-weight: 600;
    color: rgba(255,255,255,.25);
    position: relative;
    z-index: 1;
}

/* Streak */
.streak-visual { display: flex; align-items: center; gap: 12px; margin-bottom: .75rem; }
.streak-num    { font-size: 2.25rem; font-weight: 800; color: var(--tx); letter-spacing: -.05em; line-height: 1; }
.streak-label  { font-size: .75rem; font-weight: 600; color: var(--tx3); }
.streak-best   { font-size: .75rem; color: var(--tx3); margin-top: 2px; }
.streak-best strong { color: var(--tx2); }

.streak-dots { display: flex; gap: 4px; flex-wrap: wrap; }
.streak-dot {
    width: 20px;
    height: 20px;
    border-radius: 6px;
    background: var(--bd);
    transition: background .3s;
}
.streak-dot.active { background: var(--ac); }
.streak-dot.today  { background: var(--dk); box-shadow: 0 0 0 2px var(--ac); }
.streak-dots-label { font-size: .625rem; color: var(--tx3); font-weight: 600; width: 100%; margin-top: 4px; }

/* Sessions */
.session-item {
    display: flex;
    gap: 10px;
    padding: .75rem 0;
    border-bottom: 1px solid var(--bd);
    align-items: center;
}
.session-item:last-child { border-bottom: none; padding-bottom: 0; }
.session-date {
    width: 36px;
    flex-shrink: 0;
    text-align: center;
    background: var(--dk);
    border-radius: 8px;
    padding: 4px 0;
}
.session-day { font-size: .5625rem; font-weight: 800; color: var(--ac); text-transform: uppercase; letter-spacing: .3px; }
.session-num { font-size: 1.0625rem; font-weight: 800; color: #fff; line-height: 1; }
.session-info { flex: 1; min-width: 0; }
.session-title { font-size: .8125rem; font-weight: 700; color: var(--tx); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.session-meta  { font-size: .6875rem; color: var(--tx3); margin-top: 2px; }
.session-enroll {
    flex-shrink: 0;
    padding: 5px 10px;
    border-radius: 7px;
    font-size: .6875rem;
    font-weight: 700;
    font-family: var(--ff);
    cursor: pointer;
    border: none;
    transition: all .18s;
    white-space: nowrap;
    min-height: 30px;
}
.enroll-btn   { background: var(--ac); color: var(--dk); }
.enroll-btn:hover { background: var(--ac2); }
.enrolled-btn { background: rgba(31,226,144,.08); color: var(--ac2); border: 1px solid rgba(31,226,144,.2); cursor: default; }

.no-sessions { text-align: center; padding: 1.375rem 1rem; color: var(--tx3); font-size: .875rem; }
.no-sessions svg { width: 28px; height: 28px; stroke: var(--bd); fill: none; stroke-width: 1.5; margin: 0 auto .5rem; display: block; }

/* Spaced review CTA */
.spaced-review-cta {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: .875rem 1.125rem;
    background: rgba(88,101,242,.04);
    border: 1px solid rgba(88,101,242,.14);
    border-radius: var(--r-lg);
    text-decoration: none;
    transition: background .2s, border-color .2s;
}
.spaced-review-cta:hover { background: rgba(88,101,242,.08); border-color: rgba(88,101,242,.24); }
.spaced-review-ico {
    width: 40px; height: 40px;
    border-radius: 10px;
    background: rgba(88,101,242,.1);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.spaced-review-ico svg { width: 17px; height: 17px; stroke: #5865f2; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.spaced-review-info { flex: 1; min-width: 0; }
.spaced-review-title { font-size: .875rem; font-weight: 800; color: var(--tx); }
.spaced-review-sub   { font-size: .75rem; color: var(--tx3); margin-top: 1px; }
.spaced-review-count { font-size: 1.25rem; font-weight: 800; color: #5865f2; }

/* ─────────────────────────────────────────────
   TOAST
───────────────────────────────────────────── */
.toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    padding: 11px 18px;
    background: var(--dk);
    color: #fff;
    border-radius: 12px;
    font-size: .875rem;
    font-weight: 700;
    z-index: 1000;
    transform: translateY(80px);
    opacity: 0;
    transition: all .3s cubic-bezier(.16,1,.3,1);
    box-shadow: 0 8px 24px rgba(0,0,0,.14);
    display: flex;
    align-items: center;
    gap: 8px;
    max-width: calc(100vw - 48px);
}
.toast.show { transform: translateY(0); opacity: 1; }
.toast svg  { width: 15px; height: 15px; stroke: var(--ac); fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }

/* ─────────────────────────────────────────────
   RESPONSIVE BREAKPOINTS
   ─────────────────────────────────────────────

   Breakpoint map:
   ≥ 1200px  → full 2-col layout
   900–1199  → 2-col with narrower right col
   768–899   → single col, right-col grid 2-col
   < 768     → single col, stat strip 2-col
   < 600     → single col, stat strip 2-col, smaller padding
   < 480     → tightest — phones
───────────────────────────────────────────── */

/* 900px–1199px: tighten right column */
@media (max-width: 1199px) and (min-width: 901px) {
    .layout-2col { grid-template-columns: 1fr 290px; }
}

/* ≤ 900px: sidebar collapses, full width content */
@media (max-width: 900px) {
    .main-content {
        margin-left: 0;
        padding: 20px var(--content-pad-sm) 80px;
    }
    /* Stack 2-col, right-col goes 2-grid */
    .layout-2col { grid-template-columns: 1fr; }
    .right-col   { display: grid; grid-template-columns: repeat(2, 1fr); }
    .test-countdown { grid-column: 1 / -1; }

    /* Smaller week grid text */
    .week-day-num { font-size: .9375rem; }
}

/* ≤ 768px: stat strip 2-col, week adapts */
@media (max-width: 768px) {
    .stat-strip { grid-template-columns: repeat(2, 1fr); gap: 10px; }

    /* Hide task chips in week, just show dots */
    .week-task-chip { display: none; }
    .week-day-dot   { display: block; }

    .right-col { grid-template-columns: 1fr; }

    .page-header { flex-direction: column; align-items: flex-start; }
    .header-actions { width: 100%; }
    .hdr-btn { flex: 1; justify-content: center; }
}

/* ≤ 600px: fully stacked, tighter spacing */
@media (max-width: 600px) {
    :root {
        --r-lg: 14px;
    }

    .stat-strip { grid-template-columns: repeat(2, 1fr); gap: 8px; }
    .stat-pill  { padding: .75rem .875rem; gap: 10px; }
    .stat-pill-ico { width: 34px; height: 34px; border-radius: 9px; }
    .stat-pill-val { font-size: 1.1875rem; }

    /* Week: tighter */
    .week-day { padding: .625rem .3rem .5rem; min-height: 80px; }
    .week-day-num { font-size: .875rem; }
    .week-day-label { font-size: .5rem; }

    /* Timeline: tighter margins */
    .timeline { padding: .875rem 1rem; }
    .timeline-content { padding: .625rem .75rem; margin-bottom: 8px; }

    /* Cards */
    .today-card-header,
    .today-progress { padding: .875rem 1rem; }

    /* Right col */
    .right-col { gap: 10px; }
    .test-countdown { padding: 1.125rem; }
    .countdown-days { font-size: 2.5rem; }

    /* Toast bottom safe area */
    .toast { bottom: max(16px, env(safe-area-inset-bottom, 16px)); right: 16px; }

}

/* ≤ 480px: tightest phone layout */
@media (max-width: 480px) {
    .stat-strip { grid-template-columns: repeat(2, 1fr); gap: 6px; }
    .stat-pill  { padding: .625rem .75rem; border-radius: 12px; }
    .stat-pill-ico { display: none; }

    .week-card-header { padding: .75rem 1rem; }
    .week-day { min-height: 68px; }

    .header-actions { gap: 6px; }
    .hdr-btn { padding: 9px 12px; font-size: .75rem; }

    /* Hide edit button on smallest screens to reduce clutter */
    .hdr-btn:not(.hdr-btn-primary) { display: none; }
}

/* Safe areas for notched phones */
@supports (padding: max(0px)) {
    .main-content {
        padding-left: max(var(--content-pad-sm), env(safe-area-inset-left));
        padding-right: max(var(--content-pad-sm), env(safe-area-inset-right));
        padding-bottom: max(80px, calc(80px + env(safe-area-inset-bottom)));
    }
    @media (min-width: 901px) {
        .main-content {
            padding-left: max(var(--content-pad), env(safe-area-inset-left));
            padding-right: max(var(--content-pad), env(safe-area-inset-right));
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
            <h1 class="page-title">My <span>Schedule</span></h1>
            <p class="page-subtitle">AI-generated study plan · personalised for your test date and goals</p>
        </div>
        <div class="header-actions">
            <?php if ($hasSchedule): ?>
            <a href="/schedule/spaced-review.php" class="hdr-btn">
                <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
                Spaced Review<?php if ($spacedCount > 0): ?> (<?= $spacedCount ?>)<?php endif; ?>
            </a>
            <a href="/schedule/edit.php" class="hdr-btn">
                <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit Plan
            </a>
            <?php endif; ?>
            <form method="POST" action="/schedule/generate.php" style="margin:0">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <button type="submit" class="hdr-btn hdr-btn-primary" id="regenBtn">
                    <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
                    <?= $hasSchedule ? 'Regenerate Plan' : 'Generate My Plan' ?>
                </button>
            </form>
        </div>
    </div>

    <!-- ── Stat Strip ── -->
    <div class="stat-strip sr d1">
        <div class="stat-pill">
            <div class="stat-pill-ico ico-ac">
                <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
            </div>
            <div>
                <div class="stat-pill-label">Today's Tasks</div>
                <div class="stat-pill-val"><?= $tasksDone ?><span>/<?= $tasksTotal ?></span></div>
                <div class="stat-pill-sub"><?= $planPct ?>% complete</div>
            </div>
        </div>
        <div class="stat-pill">
            <div class="stat-pill-ico ico-dk">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div>
                <div class="stat-pill-label">Study Time</div>
                <div class="stat-pill-val"><?= $totalMinutes ?><span> min</span></div>
                <div class="stat-pill-sub">planned today</div>
            </div>
        </div>
        <div class="stat-pill">
            <div class="stat-pill-ico ico-warn">
                <svg viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
            </div>
            <div>
                <div class="stat-pill-label">Streak</div>
                <div class="stat-pill-val"><?= $currentStreak ?><span> days</span></div>
                <div class="stat-pill-sub">Best: <?= $longestStreak ?> days</div>
            </div>
        </div>
        <div class="stat-pill">
            <div class="stat-pill-ico ico-<?= $daysUntilTest ? 'err' : 'ac' ?>">
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <div>
                <div class="stat-pill-label">SAT In</div>
                <div class="stat-pill-val"><?= $daysUntilTest ?? '&mdash;' ?><span> days</span></div>
                <div class="stat-pill-sub"><?= $testDateLabel ?? 'No date set' ?></div>
            </div>
        </div>
    </div>

    <!-- ── Weekly Calendar ── -->
    <div class="week-card sr d2">
        <div class="week-card-header">
            <div class="week-card-title">This Week</div>
            <div class="week-nav-label"><?= date('M j', $weekStart) ?> – <?= date('M j', $weekStart + 6 * 86400) ?></div>
        </div>
        <div class="week-grid">
            <?php foreach ($weekDays as $dayKey => $day):
                $dayTasks = $day['tasks'];
                $dayTotal = count($dayTasks);
                $typeMap  = ['lesson' => 'chip-lesson', 'quiz' => 'chip-quiz', 'test' => 'chip-test', 'review' => 'chip-review', 'practice_test' => 'chip-test'];
            ?>
            <div class="week-day <?= $day['is_today'] ? 'today' : '' ?> <?= ($day['is_past'] && !$day['is_today']) ? 'past' : '' ?>">
                <div class="week-day-header" style="margin-bottom:.375rem">
                    <div class="week-day-label"><?= $day['label'] ?></div>
                    <div class="week-day-num"><?= $day['num'] ?></div>
                    <?php if ($day['is_today']): ?><div class="week-day-dot"></div><?php endif; ?>
                </div>
                <?php if (!empty($dayTasks)): ?>
                <div class="week-tasks">
                    <?php foreach (array_slice($dayTasks, 0, 3) as $t):
                        $chipCls = $typeMap[$t['task_type'] ?? 'lesson'] ?? 'chip-lesson';
                        $tdone   = !empty($t['is_complete']);
                    ?>
                    <div class="week-task-chip <?= $chipCls ?>"
                         style="<?= $tdone ? 'opacity:.45;text-decoration:line-through' : '' ?>"
                         title="<?= htmlspecialchars($t['title'] ?? '') ?>">
                        <?= htmlspecialchars(mb_strimwidth($t['title'] ?? 'Task', 0, 16, '…')) ?>
                    </div>
                    <?php endforeach; ?>
                    <?php if ($dayTotal > 3): ?>
                    <div class="week-task-more">+<?= $dayTotal - 3 ?> more</div>
                    <?php endif; ?>
                </div>
                <?php elseif (!$day['is_past']): ?>
                <div style="font-size:.5625rem;color:var(--bd);text-align:center;margin-top:.375rem">—</div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── 2-col layout ── -->
    <div class="layout-2col">

        <!-- Today's Plan -->
        <div class="sr d3">
            <div class="today-card">
                <div class="today-card-header">
                    <div class="today-card-title">Today's Plan</div>
                    <div class="today-date-badge">
                        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <?= date('D, M j') ?>
                    </div>
                </div>

                <?php if ($tasksTotal > 0): ?>
                <div class="today-progress">
                    <div class="progress-row">
                        <span class="progress-label"><?= $tasksDone ?> of <?= $tasksTotal ?> tasks complete</span>
                        <span class="progress-pct" id="progressPct"><?= $planPct ?>%</span>
                    </div>
                    <div class="progress-rail">
                        <div class="progress-fill" id="progressFill" data-width="<?= $planPct ?>"></div>
                    </div>
                    <div class="progress-meta">
                        <span><?= $totalMinutes ?> min total</span>
                        <span>~<?= $studyHours ?> hrs/day goal</span>
                        <?php if ($planPct === 100): ?>
                        <span style="color:var(--ac2);font-weight:700">All done! <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="timeline" id="taskTimeline">
                    <?php
                    $currentFound = false;
                    $typeMap   = ['lesson' => 'badge-lesson', 'quiz' => 'badge-quiz', 'test' => 'badge-test', 'review' => 'badge-review', 'practice_test' => 'badge-test', 'ai_tutor' => 'badge-tutor', 'spaced_review' => 'badge-review'];
                    $typeLabel = ['lesson' => 'Lesson', 'quiz' => 'Quiz', 'test' => 'Test', 'review' => 'Review', 'practice_test' => 'Practice Test', 'ai_tutor' => 'AI Tutor', 'spaced_review' => 'Review'];
                    foreach ($todayPlan as $idx => $task):
                        $isDone   = !empty($task['is_complete']);
                        $isActive = !$isDone && !$currentFound;
                        if ($isActive) $currentFound = true;
                        $prevDone = $idx === 0 || !empty($todayPlan[$idx - 1]['is_complete']);
                        $typeCls  = $typeMap[$task['task_type']  ?? 'lesson'] ?? 'badge-lesson';
                        $typeLbl  = $typeLabel[$task['task_type'] ?? 'lesson'] ?? 'Task';
                        $link     = $task['link'] ?? '';
                        if (empty($link)) {
                            $type = $task['task_type'] ?? '';
                            if      ($type === 'lesson')                               $link = '/learn/math/';
                            elseif  ($type === 'quiz')                                 $link = '/quiz.php?quiz_id=' . ($task['reference_id'] ?? '');
                            elseif  ($type === 'practice_test')                        $link = '/practice-tests/';
                            elseif  ($type === 'spaced_review' || $type === 'review')  $link = '/schedule/spaced-review.php';
                            elseif  ($type === 'ai_tutor')                             $link = '/ai-tutor/';
                            else                                                       $link = '/schedule/';
                        }
                    ?>
                    <div class="timeline-item <?= $prevDone ? 'done-line' : '' ?>" id="task-row-<?= intval($task['id']) ?>">
                        <div class="timeline-left">
                            <div class="timeline-dot <?= $isDone ? 'done' : ($isActive ? 'active' : '') ?>">
                                <svg class="check" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                <svg class="ring"  viewBox="0 0 24 24"><circle cx="12" cy="12" r="4" fill="currentColor"/></svg>
                            </div>
                            <div class="timeline-line"></div>
                        </div>
                        <a href="<?= htmlspecialchars($link) ?>"
                           class="timeline-content <?= $isDone ? 'done-task' : ($isActive ? 'active-task' : '') ?>">
                            <div class="task-top">
                                <span class="task-type-badge <?= $typeCls ?>"><?= $typeLbl ?></span>
                                <?php if (($task['subject'] ?? '') === 'math'): ?>
                                <span style="font-size:.5625rem;color:var(--tx3);font-weight:700">Math</span>
                                <?php elseif (($task['subject'] ?? '') === 'reading_writing'): ?>
                                <span style="font-size:.5625rem;color:var(--tx3);font-weight:700">R&amp;W</span>
                                <?php endif; ?>
                            </div>
                            <div class="task-title"><?= htmlspecialchars($task['title'] ?? 'Study Session') ?></div>
                            <div class="task-meta">
                                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                <span><?= intval($task['duration_min'] ?? 20) ?> min</span>
                                <?php if (!empty($task['description'])): ?>
                                <span>·</span>
                                <span><?= htmlspecialchars(mb_strimwidth($task['description'], 0, 55, '…')) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="task-actions" onclick="event.stopPropagation();event.preventDefault();">
                                <?php if ($isDone): ?>
                                <span class="task-btn task-btn-done">
                                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                    Completed
                                </span>
                                <?php else: ?>
                                <a href="<?= htmlspecialchars($link) ?>" class="task-btn task-btn-primary">
                                    <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                                    Start
                                </a>
                                <button class="task-btn" onclick="markTaskDone(<?= intval($task['id']) ?>, this)" type="button">
                                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                    Mark done
                                </button>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php else: ?>
                <div class="empty-plan">
                    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="14" x2="16" y2="14"/><line x1="8" y1="18" x2="13" y2="18"/></svg>
                    <h3>No tasks for today</h3>
                    <p>Your AI study plan hasn't been generated yet, or there are no tasks scheduled for today.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column -->
        <div class="right-col sr d4">

            <!-- Test countdown -->
            <?php if ($daysUntilTest > 0 && $testDateLabel):
                $studyProgress = min(100, max(0, (int) round((1 - $daysUntilTest / 60) * 100)));
            ?>
            <div class="test-countdown">
                <div class="countdown-label"><span class="countdown-label-dot"></span>SAT Countdown</div>
                <div class="countdown-days"><?= $daysUntilTest ?> <span>days to go</span></div>
                <div class="countdown-date"><?= htmlspecialchars($testDateLabel) ?> · Target: <?= $targetScore ?></div>
                <div class="countdown-bar">
                    <div class="countdown-bar-fill" data-width="<?= $studyProgress ?>"></div>
                </div>
                <div class="countdown-progress-label">
                    <span>Study progress</span>
                    <span><?= $studyProgress ?>% through prep</span>
                </div>
            </div>
            <?php else: ?>
            <div class="side-card">
                <div class="side-card-hd">
                    <span class="side-card-title">Test Date</span>
                    <a href="/schedule/setup.php" class="side-card-link">Set date →</a>
                </div>
                <div class="side-card-body" style="text-align:center;padding:1.375rem;color:var(--tx3);font-size:.875rem">
                    No test date set.<br>
                    <a href="/schedule/setup.php" style="color:var(--ac2);font-weight:700;text-decoration:none">Add your SAT date →</a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Streak -->
            <div class="side-card">
                <div class="side-card-hd">
                    <span class="side-card-title">Study Streak</span>
                    <a href="/achievements/" class="side-card-link">All badges →</a>
                </div>
                <div class="side-card-body">
                    <div class="streak-visual">
                        <div class="streak-num"><?= $currentStreak ?></div>
                        <div>
                            <div class="streak-label">day streak</div>
                            <div class="streak-best">Best: <strong><?= $longestStreak ?> days</strong></div>
                        </div>
                    </div>
                    <div class="streak-dots">
                        <?php for ($d = 6; $d >= 0; $d--):
                            $dTs      = strtotime("-{$d} days");
                            $dKey     = date('Y-m-d', $dTs);
                            $isToday  = $d === 0;
                            $dTasks   = $weekDays[$dKey]['tasks'] ?? [];
                            $hasStudy = !empty($dTasks) && count(array_filter($dTasks, fn($t) => !empty($t['is_complete']))) > 0;
                            $cls      = $isToday ? 'today' : ($hasStudy ? 'active' : '');
                        ?>
                        <div class="streak-dot <?= $cls ?>" title="<?= date('D M j', $dTs) ?>"></div>
                        <?php endfor; ?>
                        <div class="streak-dots-label">Last 7 days</div>
                    </div>
                </div>
            </div>

            <!-- Spaced review -->
            <?php if ($spacedCount > 0): ?>
            <a href="/schedule/spaced-review.php" class="spaced-review-cta">
                <div class="spaced-review-ico">
                    <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
                </div>
                <div class="spaced-review-info">
                    <div class="spaced-review-title">Spaced Review Due</div>
                    <div class="spaced-review-sub">Questions ready for review today</div>
                </div>
                <div class="spaced-review-count"><?= $spacedCount ?></div>
            </a>
            <?php endif; ?>

            <!-- Group sessions -->
            <div class="side-card">
                <div class="side-card-hd">
                    <span class="side-card-title">Group Sessions</span>
                    <a href="/sessions/" class="side-card-link">All →</a>
                </div>
                <div class="side-card-body" style="padding:.375rem 1.125rem 1.125rem">
                    <?php if (!empty($upcomingSessions)): ?>
                    <?php foreach ($upcomingSessions as $sess):
                        $sessTs = strtotime($sess['scheduled_at'] ?? 'now');
                    ?>
                    <div class="session-item">
                        <div class="session-date">
                            <div class="session-day"><?= date('D', $sessTs) ?></div>
                            <div class="session-num"><?= date('j', $sessTs) ?></div>
                        </div>
                        <div class="session-info">
                            <div class="session-title"><?= htmlspecialchars($sess['title'] ?? 'Session') ?></div>
                            <div class="session-meta"><?= date('g:i A', $sessTs) ?> · <?= intval($sess['duration_mins'] ?? 60) ?>min</div>
                        </div>
                        <?php if (!empty($sess['enrolled'])): ?>
                        <span class="session-enroll enrolled-btn">Enrolled</span>
                        <?php else: ?>
                        <button class="session-enroll enroll-btn" type="button" onclick="enrollSession(<?= intval($sess['id']) ?>, this)">Enroll</button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div class="no-sessions">
                        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                        No upcoming sessions.<br>
                        <a href="/sessions/" style="color:var(--ac2);font-weight:600;text-decoration:none">Browse sessions →</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /right-col -->
    </div><!-- /layout-2col -->

</main>

<!-- Toast -->
<div class="toast" id="toast">
    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
    <span id="toastMsg">Task marked complete!</span>
</div>

<script>
(function () {
    'use strict';

    /* ── Scroll reveal ── */
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (n) {
            if (n.isIntersecting) { n.target.classList.add('v'); io.unobserve(n.target); }
        });
    }, { threshold: .04, rootMargin: '0px 0px -16px 0px' });
    document.querySelectorAll('.sr').forEach(function (el) { io.observe(el); });

    /* ── Animated bars ── */
    var bObs = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (!e.isIntersecting) return;
            var b = e.target;
            setTimeout(function () { b.style.width = (b.dataset.width || 0) + '%'; }, 180);
            bObs.unobserve(b);
        });
    }, { threshold: .1 });
    document.querySelectorAll('[data-width]').forEach(function (b) { bObs.observe(b); });

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

    /* ── Mark task done ── */
    window.markTaskDone = function (taskId, btn) {
        btn.disabled = true;
        btn.innerHTML = '<svg viewBox="0 0 24 24" style="width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:2.5;stroke-linecap:round"><polyline points="20 6 9 17 4 12"/></svg> Saving…';
        fetch('/api/mark-complete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ task_id: taskId, complete: true })
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success) {
                var row = document.getElementById('task-row-' + taskId);
                if (row) {
                    var dot = row.querySelector('.timeline-dot');
                    if (dot) {
                        dot.classList.remove('active');
                        dot.classList.add('done');
                        var chk = dot.querySelector('.check');
                        if (chk) chk.style.display = 'block';
                    }
                    var content = row.querySelector('.timeline-content');
                    if (content) { content.classList.remove('active-task'); content.classList.add('done-task'); }
                    var actions = row.querySelector('.task-actions');
                    if (actions) actions.innerHTML = '<span class="task-btn task-btn-done"><svg viewBox="0 0 24 24" style="width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:2.5;stroke-linecap:round"><polyline points="20 6 9 17 4 12"/></svg> Completed</span>';
                }
                if (res.tasks_done !== undefined && res.tasks_total !== undefined) {
                    var pct   = res.tasks_total > 0 ? Math.round(res.tasks_done / res.tasks_total * 100) : 0;
                    var fill  = document.getElementById('progressFill');
                    var pctEl = document.getElementById('progressPct');
                    if (fill)  fill.style.width  = pct + '%';
                    if (pctEl) pctEl.textContent = pct + '%';
                }
                showToast('Task marked complete!');
            }
        })
        .catch(function () { btn.disabled = false; btn.textContent = 'Retry'; });
    };

    /* ── Enroll session ── */
    window.enrollSession = function (sessId, btn) {
        btn.disabled = true;
        btn.textContent = '…';
        fetch('/api/enroll-session.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ session_id: sessId })
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success) { btn.textContent = 'Enrolled'; btn.className = 'session-enroll enrolled-btn'; btn.disabled = false; }
            else { btn.disabled = false; btn.textContent = 'Error'; }
        })
        .catch(function () { btn.disabled = false; btn.textContent = 'Retry'; });
    };

    /* ── Toast ── */
    function showToast(msg) {
        var t = document.getElementById('toast');
        document.getElementById('toastMsg').textContent = msg;
        t.classList.add('show');
        setTimeout(function () { t.classList.remove('show'); }, 3200);
    }

    /* ── Regenerate spinner ── */
    var regenForm = document.querySelector('form[action="/schedule/generate.php"]');
    if (regenForm) {
        regenForm.addEventListener('submit', function () {
            var btn = document.getElementById('regenBtn');
            if (btn) { btn.disabled = true; btn.innerHTML = '<svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:var(--ac);fill:none;stroke-width:2;stroke-linecap:round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg> Generating…'; }
        });
    }

}());
</script>
</body>
</html>