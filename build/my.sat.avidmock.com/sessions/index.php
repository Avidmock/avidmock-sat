<?php
/**
 * sessions/index.php — Group Sessions Hub
 * my.sat.avidmock.com/sessions/
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Session.php';

Auth::requireStudent();
$userId    = $_SESSION['user_id'];
$user      = User::findById($userId);
$firstName = explode(' ', $user['name'] ?? 'Student')[0];

$filter  = $_GET['filter']  ?? 'upcoming';
$subject = $_GET['subject'] ?? '';

$upcomingSessions = Session::getUpcoming($userId, $subject);
$enrolledSessions = Session::getEnrolled($userId);
$pastSessions     = Session::getPast($userId, 3);
$enrolledIds      = array_column($enrolledSessions, 'id');

$tabs = [
    'upcoming' => 'Upcoming',
    'enrolled' => 'My Sessions',
    'all'      => 'All Sessions',
];

$displaySessions = match($filter) {
    'enrolled' => $enrolledSessions,
    'all'      => Session::getAll($userId, $subject),
    default    => $upcomingSessions,
};

$activePage  = 'sessions';
$topbarTitle = 'Group Sessions';
$topbarSub   = 'Live &amp; upcoming study sessions with expert tutors';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Group Sessions — Avidmock SAT</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<link rel="icon" type="image/png" sizes="16x16"   href="/favicon-16x16.png">
<link rel="icon" type="image/png" sizes="32x32"   href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="192x192" href="/android-chrome-192x192.png">
<link rel="icon" type="image/png" sizes="512x512" href="/android-chrome-512x512.png">
<link rel="apple-touch-icon" sizes="180x180"      href="/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&display=swap" rel="stylesheet">
<style>
/* ─────────────────────────────────────────────
   TOKENS  (unified with achievements + leaderboard)
───────────────────────────────────────────── */
:root {
    --dk: #143230;   --dk2: #1a3f3c;
    --ac: #1fe290;   --ac2: #17c87a;
    --tx: #1a1a2e;   --tx2: #4a4a5a;  --tx3: #8a8a9a;
    --bg: #f7faf9;   --bg2: #ffffff;  --bd: #e2ebe9;  --bd2: #d1dcd9;
    --warn: #f59e0b; --err: #ef4444;
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
    background: var(--bg);
    color: var(--tx);
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    overflow-x: hidden;
    min-height: 100vh;
}
a { text-decoration: none; color: inherit; }
button { font-family: var(--ff); cursor: pointer; border: none; background: none; }

/* ─────────────────────────────────────────────
   SCROLL REVEAL
───────────────────────────────────────────── */
.sr {
    opacity: 0;
    transform: translateY(18px);
    transition: opacity .5s cubic-bezier(.16,1,.3,1), transform .5s cubic-bezier(.16,1,.3,1);
}
.sr.v { opacity: 1; transform: none; }
.d1 { transition-delay: .06s; }
.d2 { transition-delay: .12s; }
.d3 { transition-delay: .18s; }
.d4 { transition-delay: .24s; }
.d5 { transition-delay: .30s; }
.d6 { transition-delay: .36s; }

/* ─────────────────────────────────────────────
   LAYOUT SHELL
───────────────────────────────────────────── */
.main-content {
    margin-left: var(--sidebar-w);
    margin-top: var(--topbar-h);
    padding: 32px var(--pad) 80px;
    min-height: calc(100vh - var(--topbar-h));
    max-width: calc(1280px + var(--sidebar-w));
}
.sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.45);
    z-index: 250;
    opacity: 0;
    transition: opacity .28s;
    pointer-events: none;
}
.sidebar-overlay.show { opacity: 1; pointer-events: all; }

/* ─────────────────────────────────────────────
   HERO
───────────────────────────────────────────── */
.hero {
    background: var(--dk);
    border-radius: 20px;
    padding: 2.25rem 2.5rem;
    position: relative;
    overflow: hidden;
    margin-bottom: 20px;
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 24px;
    align-items: center;
}
.hero-bg-grid {
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(31,226,144,.028) 1px, transparent 1px),
        linear-gradient(90deg, rgba(31,226,144,.028) 1px, transparent 1px);
    background-size: 40px 40px;
    pointer-events: none;
}
.hero-bg-glow {
    position: absolute;
    top: -100px; right: -60px;
    width: 420px; height: 420px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(31,226,144,.07) 0%, transparent 65%);
    pointer-events: none;
}
.hero-left { position: relative; z-index: 1; }

.hero-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 50px;
    background: rgba(31,226,144,.1);
    border: 1px solid rgba(31,226,144,.18);
    font-size: .5625rem;
    font-weight: 800;
    color: var(--ac);
    text-transform: uppercase;
    letter-spacing: .6px;
    margin-bottom: 14px;
}
.hero-eyebrow-dot {
    width: 5px; height: 5px;
    border-radius: 50%;
    background: var(--ac);
    animation: eyeDot 1.4s ease-in-out infinite;
}
@keyframes eyeDot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50%       { opacity: .3; transform: scale(.5); }
}

.hero-title {
    font-size: clamp(1.25rem, 3vw, 1.75rem);
    font-weight: 800;
    color: #fff;
    letter-spacing: -.03em;
    line-height: 1.15;
    margin-bottom: 5px;
}
.hero-title em { font-style: normal; color: var(--ac); }
.hero-sub {
    font-size: .9375rem;
    color: rgba(255,255,255,.38);
    font-weight: 500;
    margin-bottom: 20px;
}

/* Hero stats pill row */
.hero-stats {
    display: flex;
    gap: 0;
    background: rgba(255,255,255,.05);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 13px;
    overflow: hidden;
    width: fit-content;
    max-width: 100%;
    overflow-x: auto;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
}
.hero-stats::-webkit-scrollbar { display: none; }

.hero-stat {
    padding: 11px 20px;
    border-right: 1px solid rgba(255,255,255,.08);
    text-align: center;
    flex-shrink: 0;
}
.hero-stat:last-child { border-right: none; }
.hero-stat-val {
    font-size: 1.25rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: -.04em;
    line-height: 1;
}
.hero-stat-val.ac { color: var(--ac); }
.hero-stat-key {
    font-size: .5rem;
    font-weight: 700;
    color: rgba(255,255,255,.3);
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-top: 3px;
}

/* Hero right date block */
.hero-date-block {
    position: relative;
    z-index: 1;
    text-align: center;
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 16px;
    padding: 22px 32px;
    min-width: 130px;
    flex-shrink: 0;
    transition: background .2s;
}
.hero-date-block:hover { background: rgba(255,255,255,.07); }
.hero-date-month {
    font-size: .5625rem;
    font-weight: 800;
    color: var(--ac);
    text-transform: uppercase;
    letter-spacing: .9px;
    margin-bottom: 2px;
}
.hero-date-day {
    font-size: 2.75rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: -.05em;
    line-height: 1;
}
.hero-date-dow {
    font-size: .6875rem;
    font-weight: 600;
    color: rgba(255,255,255,.28);
    margin-top: 3px;
}
.hero-date-time {
    font-size: .8125rem;
    font-weight: 800;
    color: var(--ac);
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid rgba(255,255,255,.07);
}

/* ─────────────────────────────────────────────
   OVERVIEW CARDS
───────────────────────────────────────────── */
.overview-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}
.ov-card {
    background: var(--bg2);
    border: 1px solid var(--bd);
    border-radius: var(--r-lg);
    padding: 1rem 1.125rem;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    transition: transform .25s cubic-bezier(.16,1,.3,1), box-shadow .25s, border-color .2s;
}
.ov-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(20,50,48,.07);
    border-color: rgba(31,226,144,.28);
}
.ov-ico {
    width: 38px; height: 38px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.ov-ico svg {
    width: 17px; height: 17px;
    fill: none; stroke-width: 1.8;
    stroke-linecap: round; stroke-linejoin: round;
    stroke: currentColor;
}
.ic-green  { background: rgba(31,226,144,.08);  color: var(--ac); }
.ic-warn   { background: rgba(245,158,11,.08);  color: var(--warn); }
.ic-purple { background: rgba(168,85,247,.08);  color: #a855f7; }
.ic-dk     { background: rgba(20,50,48,.07);    color: var(--dk); }

.ov-label { font-size: .6875rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: .4px; margin-bottom: 3px; }
.ov-val   { font-size: 1.375rem; font-weight: 800; color: var(--tx); letter-spacing: -.04em; line-height: 1; }
.ov-val span { font-size: .9375rem; color: var(--ac); }
.ov-sub   { font-size: .6875rem; color: var(--tx3); margin-top: 4px; }

/* ─────────────────────────────────────────────
   FEATURED / NEXT SESSION CARD
───────────────────────────────────────────── */
.featured-card {
    background: var(--dk);
    border-radius: 20px;
    padding: 2.25rem 2.5rem;
    position: relative;
    overflow: hidden;
    margin-bottom: 20px;
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 28px;
    align-items: center;
}
.featured-bg-grid {
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(31,226,144,.025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(31,226,144,.025) 1px, transparent 1px);
    background-size: 40px 40px;
    pointer-events: none;
}
.featured-bg-glow {
    position: absolute;
    top: -80px; left: -80px;
    width: 360px; height: 360px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(31,226,144,.06) 0%, transparent 65%);
    pointer-events: none;
}
.featured-inner { position: relative; z-index: 1; }

.featured-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 50px;
    background: rgba(31,226,144,.1);
    border: 1px solid rgba(31,226,144,.18);
    font-size: .5625rem;
    font-weight: 800;
    color: var(--ac);
    text-transform: uppercase;
    letter-spacing: .6px;
    margin-bottom: 13px;
}
.featured-eyebrow-dot {
    width: 5px; height: 5px;
    border-radius: 50%;
    background: var(--ac);
    animation: eyeDot 1.4s ease-in-out infinite;
}
.featured-title {
    font-size: clamp(1.125rem, 2.5vw, 1.5rem);
    font-weight: 800;
    color: #fff;
    letter-spacing: -.03em;
    line-height: 1.25;
    margin-bottom: 14px;
}
.featured-meta-row {
    display: flex;
    flex-wrap: wrap;
    gap: 18px;
    margin-bottom: 20px;
}
.featured-meta-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: .8125rem;
    color: rgba(255,255,255,.45);
    font-weight: 500;
}
.featured-meta-item svg {
    width: 13px; height: 13px;
    stroke: rgba(255,255,255,.3);
    fill: none; stroke-width: 2;
    stroke-linecap: round; stroke-linejoin: round;
    flex-shrink: 0;
}
.featured-meta-item strong { color: rgba(255,255,255,.8); font-weight: 700; }

/* Spots progress */
.featured-spots {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
}
.featured-spots-track {
    flex: 1;
    max-width: 180px;
    height: 5px;
    background: rgba(255,255,255,.08);
    border-radius: 3px;
    overflow: hidden;
}
.featured-spots-fill {
    height: 100%;
    border-radius: 3px;
    background: linear-gradient(90deg, var(--ac2), var(--ac));
    transition: width 1.2s cubic-bezier(.16,1,.3,1);
    width: 0%;
}
.featured-spots-text {
    font-size: .6875rem;
    font-weight: 700;
    color: rgba(255,255,255,.35);
    white-space: nowrap;
}
.featured-spots-text em { font-style: normal; color: var(--ac); }

/* Action buttons */
.featured-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.btn-enroll {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 11px 24px;
    background: var(--ac);
    color: var(--dk);
    font-family: var(--ff);
    font-size: .875rem;
    font-weight: 800;
    border-radius: 11px;
    border: none;
    cursor: pointer;
    transition: background .2s, transform .2s, box-shadow .2s;
    min-height: 44px;
}
.btn-enroll:hover {
    background: var(--ac2);
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(31,226,144,.28);
}
.btn-enroll.enrolled {
    background: rgba(31,226,144,.1);
    color: var(--ac);
    border: 1.5px solid rgba(31,226,144,.25);
    cursor: default;
    box-shadow: none;
    transform: none;
}
.btn-ghost {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 11px 20px;
    background: rgba(255,255,255,.06);
    color: rgba(255,255,255,.65);
    font-family: var(--ff);
    font-size: .875rem;
    font-weight: 700;
    border-radius: 11px;
    border: 1px solid rgba(255,255,255,.1);
    cursor: pointer;
    text-decoration: none;
    transition: background .2s, color .2s;
    min-height: 44px;
}
.btn-ghost:hover { background: rgba(255,255,255,.11); color: #fff; }
.btn-ghost svg, .btn-enroll svg {
    width: 14px; height: 14px;
    stroke: currentColor; fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
    flex-shrink: 0;
}

/* Featured date block (right side) */
.featured-date-block {
    position: relative;
    z-index: 1;
    text-align: center;
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 16px;
    padding: 22px 32px;
    min-width: 130px;
    flex-shrink: 0;
}
.featured-date-month {
    font-size: .5625rem;
    font-weight: 800;
    color: var(--ac);
    text-transform: uppercase;
    letter-spacing: .9px;
    margin-bottom: 2px;
}
.featured-date-day {
    font-size: 2.75rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: -.05em;
    line-height: 1;
}
.featured-date-dow {
    font-size: .6875rem;
    font-weight: 600;
    color: rgba(255,255,255,.28);
    margin-top: 3px;
}
.featured-date-time {
    font-size: .8125rem;
    font-weight: 800;
    color: var(--ac);
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid rgba(255,255,255,.07);
}

/* ─────────────────────────────────────────────
   FILTER BAR
───────────────────────────────────────────── */
.filter-bar {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.filter-tabs {
    display: flex;
    gap: 3px;
    background: var(--bg2);
    border: 1px solid var(--bd);
    border-radius: 12px;
    padding: 4px;
    overflow-x: auto;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
    flex: 1;
    min-width: 0;
}
.filter-tabs::-webkit-scrollbar { display: none; }

.filter-tab {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 7px 14px;
    border-radius: 9px;
    font-size: .8125rem;
    font-weight: 700;
    color: var(--tx3);
    text-decoration: none;
    transition: background .15s, color .15s;
    white-space: nowrap;
    flex-shrink: 0;
    min-height: 36px;
}
.filter-tab:hover  { color: var(--tx); background: var(--bg); }
.filter-tab.active { background: var(--dk); color: var(--ac); }

.filter-tab-badge {
    font-size: .5rem;
    font-weight: 800;
    background: rgba(31,226,144,.15);
    color: var(--ac);
    padding: 1px 6px;
    border-radius: 50px;
}
.filter-tab:not(.active) .filter-tab-badge {
    background: rgba(0,0,0,.06);
    color: var(--tx3);
}

.subject-chips {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
    align-items: center;
    padding-top: 4px;
}
.subject-chip {
    padding: 6px 13px;
    border-radius: 50px;
    font-size: .6875rem;
    font-weight: 700;
    border: 1.5px solid var(--bd);
    background: var(--bg2);
    color: var(--tx3);
    white-space: nowrap;
    text-decoration: none;
    transition: border-color .15s, color .15s, background .15s;
    min-height: 32px;
    display: inline-flex;
    align-items: center;
}
.subject-chip:hover { border-color: var(--ac); color: var(--dk); }
.subject-chip.active {
    background: rgba(20,50,48,.06);
    border-color: rgba(20,50,48,.18);
    color: var(--dk);
    font-weight: 800;
}

/* ─────────────────────────────────────────────
   SESSIONS GRID
───────────────────────────────────────────── */
.sessions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 12px;
    margin-bottom: 24px;
}

/* ─────────────────────────────────────────────
   SESSION CARD
───────────────────────────────────────────── */
.session-card {
    background: var(--bg2);
    border: 1.5px solid var(--bd);
    border-radius: var(--r-lg);
    padding: 1.1875rem 1.125rem;
    display: flex;
    flex-direction: column;
    gap: 12px;
    position: relative;
    overflow: hidden;
    transition: transform .28s cubic-bezier(.16,1,.3,1), box-shadow .28s, border-color .2s;
}
.session-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(20,50,48,.09);
    border-color: rgba(31,226,144,.3);
}

/* Thin top accent line */
.session-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent, rgba(31,226,144,.3), transparent);
    opacity: 0;
    transition: opacity .25s;
}
.session-card:hover::before { opacity: 1; }

.session-card-top {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

/* Date block */
.session-date-block {
    flex-shrink: 0;
    width: 48px;
    text-align: center;
    background: var(--dk);
    border-radius: 11px;
    padding: 7px 0 6px;
}
.session-date-dow {
    font-size: .5rem;
    font-weight: 800;
    color: var(--ac);
    text-transform: uppercase;
    letter-spacing: .4px;
}
.session-date-num {
    font-size: 1.375rem;
    font-weight: 800;
    color: #fff;
    line-height: 1.1;
    letter-spacing: -.03em;
}

/* Info */
.session-info { flex: 1; min-width: 0; }

.session-subject-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 9px;
    border-radius: 50px;
    margin-bottom: 5px;
    font-size: .5625rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .4px;
}
.badge-math { background: rgba(20,50,48,.07); color: var(--dk); }
.badge-rw   { background: rgba(31,226,144,.08); color: #0d7a4a; }

.session-title {
    font-size: .9375rem;
    font-weight: 700;
    color: var(--tx);
    line-height: 1.3;
    margin-bottom: 3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.session-tutor { font-size: .75rem; color: var(--tx3); }

/* Meta row */
.session-meta {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}
.session-meta-item {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: .75rem;
    color: var(--tx3);
    font-weight: 500;
}
.session-meta-item svg {
    width: 11px; height: 11px;
    stroke: var(--tx3); fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
    flex-shrink: 0;
}

/* Footer */
.session-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 11px;
    border-top: 1px solid var(--bd);
    gap: 8px;
}
.spots-wrap {
    display: flex;
    align-items: center;
    gap: 7px;
    flex: 1;
    min-width: 0;
}
.spots-track {
    width: 56px;
    height: 4px;
    background: var(--bd);
    border-radius: 2px;
    overflow: hidden;
    flex-shrink: 0;
}
.spots-fill {
    height: 100%;
    border-radius: 2px;
    background: linear-gradient(90deg, var(--ac2), var(--ac));
    transition: width .8s cubic-bezier(.16,1,.3,1);
    width: 0%;
}
.spots-fill.warn { background: linear-gradient(90deg, #f97316, var(--warn)); }
.spots-fill.full { background: linear-gradient(90deg, #dc2626, var(--err)); }
.spots-text {
    font-size: .6875rem;
    font-weight: 700;
    color: var(--tx3);
    white-space: nowrap;
}

/* Session action buttons */
.btn-session-enroll {
    padding: 7px 16px;
    border-radius: 9px;
    font-family: var(--ff);
    font-size: .75rem;
    font-weight: 700;
    border: none;
    background: var(--ac);
    color: var(--dk);
    cursor: pointer;
    transition: background .18s, transform .18s;
    white-space: nowrap;
    flex-shrink: 0;
    min-height: 34px;
}
.btn-session-enroll:hover { background: var(--ac2); transform: translateY(-1px); }
.btn-session-enroll.enrolled {
    background: rgba(31,226,144,.08);
    color: var(--ac);
    border: 1px solid rgba(31,226,144,.2);
    cursor: default;
}
.btn-session-enroll.enrolled:hover { transform: none; background: rgba(31,226,144,.08); }
.btn-session-enroll.full {
    background: var(--bg);
    color: var(--tx3);
    border: 1px solid var(--bd);
    cursor: not-allowed;
}
.btn-session-enroll.full:hover { transform: none; }

/* ─────────────────────────────────────────────
   BOTTOM GRID — Past sessions + How it works
───────────────────────────────────────────── */
.bottom-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

/* Past sessions card */
.card-light {
    background: var(--bg2);
    border: 1px solid var(--bd);
    border-radius: var(--r-lg);
    overflow: hidden;
}
.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--bd);
}
.card-header-title {
    font-size: .8125rem;
    font-weight: 800;
    color: var(--tx);
    letter-spacing: -.01em;
}
.card-header-link {
    font-size: .75rem;
    font-weight: 600;
    color: var(--ac);
    text-decoration: none;
    transition: opacity .15s;
}
.card-header-link:hover { opacity: .7; }

.past-list { display: flex; flex-direction: column; }
.past-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 1.25rem;
    border-bottom: 1px solid var(--bd);
    text-decoration: none;
    transition: background .15s;
}
.past-item:last-child { border-bottom: none; }
.past-item:hover { background: var(--bg); }

.past-date-block {
    flex-shrink: 0;
    width: 38px;
    text-align: center;
    background: var(--bg2);
    border: 1px solid var(--bd);
    border-radius: 9px;
    padding: 4px 0;
}
.past-date-block-month {
    font-size: .4375rem;
    font-weight: 800;
    color: var(--tx3);
    text-transform: uppercase;
    letter-spacing: .3px;
}
.past-date-block-day {
    font-size: 1rem;
    font-weight: 800;
    color: var(--tx);
    line-height: 1.1;
    letter-spacing: -.03em;
}

.past-item-body { flex: 1; min-width: 0; }
.past-item-title {
    font-size: .8125rem;
    font-weight: 700;
    color: var(--tx);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.3;
    margin-bottom: 2px;
}
.past-item-meta { font-size: .6875rem; color: var(--tx3); }

.past-item-arrow {
    flex-shrink: 0;
    width: 24px; height: 24px;
    border-radius: 6px;
    background: var(--bg);
    border: 1px solid var(--bd);
    display: flex; align-items: center; justify-content: center;
    transition: background .15s, border-color .15s;
}
.past-item-arrow svg {
    width: 10px; height: 10px;
    stroke: var(--tx3); fill: none;
    stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round;
}
.past-item:hover .past-item-arrow { background: var(--ac); border-color: var(--ac); }
.past-item:hover .past-item-arrow svg { stroke: var(--dk); }

.past-empty {
    padding: 2.5rem;
    text-align: center;
    color: var(--tx3);
}
.past-empty svg {
    width: 32px; height: 32px;
    stroke: var(--bd); fill: none;
    stroke-width: 1.5; margin: 0 auto .75rem; display: block;
}

/* How it works card */
.card-dk {
    background: var(--dk);
    border-radius: var(--r-lg);
    position: relative;
    overflow: hidden;
}
.card-dk-bg-grid {
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(31,226,144,.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(31,226,144,.03) 1px, transparent 1px);
    background-size: 36px 36px;
    pointer-events: none;
}
.card-dk-bg-glow {
    position: absolute;
    top: -80px; right: -80px;
    width: 280px; height: 280px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(31,226,144,.07) 0%, transparent 65%);
    pointer-events: none;
}
.card-dk-inner {
    position: relative;
    z-index: 1;
    padding: 1.25rem;
}
.card-dk-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 1.25rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(255,255,255,.07);
}
.card-dk-title {
    font-size: .8125rem;
    font-weight: 800;
    color: rgba(255,255,255,.5);
    text-transform: uppercase;
    letter-spacing: .5px;
}

.how-list { display: flex; flex-direction: column; gap: 1rem; }
.how-item { display: flex; align-items: flex-start; gap: 12px; }
.how-num {
    flex-shrink: 0;
    width: 28px; height: 28px;
    border-radius: 50%;
    background: rgba(31,226,144,.1);
    border: 1px solid rgba(31,226,144,.18);
    display: flex; align-items: center; justify-content: center;
    font-size: .6875rem;
    font-weight: 800;
    color: var(--ac);
}
.how-body { flex: 1; }
.how-title { font-size: .875rem; font-weight: 700; color: #fff; margin-bottom: 2px; }
.how-desc  { font-size: .8125rem; color: rgba(255,255,255,.38); line-height: 1.55; }

/* ─────────────────────────────────────────────
   EMPTY STATE
───────────────────────────────────────────── */
.empty-state {
    background: var(--bg2);
    border: 1px solid var(--bd);
    border-radius: var(--r-lg);
    padding: 3.5rem 2rem;
    text-align: center;
    grid-column: 1 / -1;
}
.empty-state svg {
    width: 44px; height: 44px;
    stroke: var(--bd2); fill: none;
    stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round;
    margin: 0 auto 1rem; display: block;
}
.empty-state-title { font-size: .9375rem; font-weight: 800; color: var(--tx); margin-bottom: .375rem; }
.empty-state-sub   { font-size: .875rem; color: var(--tx3); line-height: 1.6; }

/* ─────────────────────────────────────────────
   TOAST
───────────────────────────────────────────── */
.toast {
    position: fixed;
    bottom: 24px; right: 24px;
    padding: 12px 20px;
    background: var(--dk);
    color: #fff;
    border-radius: 12px;
    font-size: .875rem;
    font-weight: 700;
    z-index: 1000;
    transform: translateY(80px);
    opacity: 0;
    transition: all .35s cubic-bezier(.16,1,.3,1);
    box-shadow: 0 8px 24px rgba(0,0,0,.18);
    display: flex;
    align-items: center;
    gap: 8px;
    pointer-events: none;
}
.toast.show { transform: translateY(0); opacity: 1; }
.toast svg {
    width: 15px; height: 15px;
    stroke: var(--ac); fill: none;
    stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round;
    flex-shrink: 0;
}

/* ─────────────────────────────────────────────
   RESPONSIVE
   ─────────────────────────────────────────────
   ≥ 1100   4-col overview, hero 2-col, bottom 2-col
   ≤ 1100   2-col overview, hero collapses date block
   ≤ 900    sidebar off, full width
   ≤ 768    filter stacks, session grid 1-col
   ≤ 600    smaller hero, tight cards
   ≤ 480    tightest phones
───────────────────────────────────────────── */

@media (max-width: 1100px) {
    .overview-grid { grid-template-columns: repeat(2, 1fr); }
    .hero { grid-template-columns: 1fr; }
    .featured-card { grid-template-columns: 1fr; }
    .featured-date-block { display: none; }
}

@media (max-width: 900px) {
    .main-content { margin-left: 0; padding: 20px var(--pad-sm) 72px; }
}

@media (max-width: 768px) {
    .filter-bar   { flex-direction: column; align-items: stretch; }
    .filter-tabs  { flex: none; }
    .subject-chips { padding-top: 0; }
    .sessions-grid { grid-template-columns: 1fr; gap: 10px; }
    .bottom-grid  { grid-template-columns: 1fr; }
    .hero { padding: 1.75rem 1.5rem; }
    .featured-card { padding: 1.75rem 1.5rem; }
}

@media (max-width: 600px) {
    .hero         { padding: 1.375rem 1.125rem; border-radius: 16px; }
    .hero-title   { font-size: 1.25rem; }
    .hero-sub     { font-size: .875rem; margin-bottom: 14px; }
    .hero-stat    { padding: 9px 14px; }
    .hero-stat-val { font-size: 1.0625rem; }

    .featured-card { padding: 1.375rem 1.125rem; border-radius: 16px; }
    .featured-title { font-size: 1.125rem; }

    .overview-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
    .ov-card { padding: .875rem .9375rem; }
    .ov-val  { font-size: 1.125rem; }

    .sessions-grid { gap: 8px; }
    .session-card  { padding: 1rem .9375rem; border-radius: var(--r); }
    .session-title { font-size: .875rem; }

    .toast { bottom: 16px; right: 16px; left: 16px; justify-content: center; }
}

@media (max-width: 480px) {
    .overview-grid { gap: 6px; }
    .ov-ico { display: none; }
    .filter-tab { padding: 6px 11px; font-size: .75rem; }
    .subject-chip { padding: 5px 10px; font-size: .625rem; }
    .session-date-block { width: 42px; }
    .session-date-num { font-size: 1.25rem; }
}

/* Safe area insets (notched phones) */
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
    .toast {
        bottom: max(24px, env(safe-area-inset-bottom));
    }
}
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/sidebar.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/topbar.php';

/* ── Compute next session data for hero ── */
$nextSession = !empty($upcomingSessions) ? $upcomingSessions[0] : null;
$nextTs      = $nextSession ? strtotime($nextSession['scheduled_at'] ?? 'now') : null;
$nextEnrolled = $nextSession && in_array(intval($nextSession['id']), $enrolledIds);
$nextSpotsLeft = intval($nextSession['spots_left'] ?? 12);
$nextMaxSpots  = intval($nextSession['max_spots']  ?? 20);
$nextSpotsPct  = $nextMaxSpots > 0 ? round((($nextMaxSpots - $nextSpotsLeft) / $nextMaxSpots) * 100) : 0;
?>

<main class="main-content" id="mainContent">

    <!-- ══════════════════════════════════════
         HERO
    ═══════════════════════════════════════ -->
    <div class="hero sr">
        <div class="hero-bg-grid"></div>
        <div class="hero-bg-glow"></div>

        <div class="hero-left">
            <div class="hero-eyebrow">
                <span class="hero-eyebrow-dot"></span>
                Group Sessions
            </div>
            <div class="hero-title"><?= htmlspecialchars($firstName) ?>'s <em>Study Hub</em></div>
            <div class="hero-sub">Live tutor-led sessions · Small groups · Interactive</div>
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="hero-stat-val ac"><?= count($enrolledSessions) ?></div>
                    <div class="hero-stat-key">Enrolled</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-val"><?= count($upcomingSessions) ?></div>
                    <div class="hero-stat-key">Upcoming</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-val ac"><?= count($pastSessions) ?></div>
                    <div class="hero-stat-key">Attended</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-val">+34</div>
                    <div class="hero-stat-key">Avg. Score Boost</div>
                </div>
            </div>
        </div>

        <?php if ($nextSession && $nextTs): ?>
        <div class="hero-date-block">
            <div class="hero-date-month"><?= date('M', $nextTs) ?></div>
            <div class="hero-date-day"><?= date('j', $nextTs) ?></div>
            <div class="hero-date-dow"><?= date('l', $nextTs) ?></div>
            <div class="hero-date-time"><?= date('g:i A', $nextTs) ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ══════════════════════════════════════
         OVERVIEW CARDS
    ═══════════════════════════════════════ -->
    <div class="overview-grid sr d1">
        <div class="ov-card">
            <div class="ov-ico ic-green">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
            </div>
            <div>
                <div class="ov-label">Enrolled</div>
                <div class="ov-val"><?= count($enrolledSessions) ?><span> sessions</span></div>
                <div class="ov-sub">Active bookings</div>
            </div>
        </div>
        <div class="ov-card">
            <div class="ov-ico ic-dk">
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <div>
                <div class="ov-label">Upcoming</div>
                <div class="ov-val"><?= count($upcomingSessions) ?><span> this week</span></div>
                <div class="ov-sub">Scheduled ahead</div>
            </div>
        </div>
        <div class="ov-card">
            <div class="ov-ico ic-warn">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div>
                <div class="ov-label">Attended</div>
                <div class="ov-val"><?= count($pastSessions) ?><span> total</span></div>
                <div class="ov-sub">Sessions completed</div>
            </div>
        </div>
        <div class="ov-card">
            <div class="ov-ico ic-purple">
                <svg viewBox="0 0 24 24"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>
            </div>
            <div>
                <div class="ov-label">Avg. Score Boost</div>
                <div class="ov-val">+<span>34</span></div>
                <div class="ov-sub">Points per student</div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════
         FEATURED / NEXT SESSION
    ═══════════════════════════════════════ -->
    <?php if ($nextSession): ?>
    <div class="featured-card sr d2">
        <div class="featured-bg-grid"></div>
        <div class="featured-bg-glow"></div>

        <div class="featured-inner">
            <div class="featured-eyebrow">
                <span class="featured-eyebrow-dot"></span>
                Next Session
            </div>
            <div class="featured-title"><?= htmlspecialchars($nextSession['title'] ?? 'Study Session') ?></div>
            <div class="featured-meta-row">
                <div class="featured-meta-item">
                    <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <strong><?= htmlspecialchars($nextSession['tutor_name'] ?? 'Tutor') ?></strong>
                </div>
                <div class="featured-meta-item">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <strong><?= date('g:i A', $nextTs) ?></strong>&nbsp;·&nbsp;<?= intval($nextSession['duration_mins'] ?? 60) ?> min
                </div>
                <div class="featured-meta-item">
                    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <?= date('l, F j', $nextTs) ?>
                </div>
                <div class="featured-meta-item">
                    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                    <?= $nextMaxSpots - $nextSpotsLeft ?> / <?= $nextMaxSpots ?> students
                </div>
            </div>

            <div class="featured-spots">
                <div class="featured-spots-track">
                    <div class="featured-spots-fill" data-width="<?= $nextSpotsPct ?>"></div>
                </div>
                <span class="featured-spots-text">
                    <em><?= $nextSpotsLeft ?></em>&nbsp;spot<?= $nextSpotsLeft !== 1 ? 's' : '' ?> remaining
                </span>
            </div>

            <div class="featured-actions">
                <?php if ($nextEnrolled): ?>
                <button class="btn-enroll enrolled" type="button">
                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    Enrolled
                </button>
                <?php else: ?>
                <button class="btn-enroll" type="button" onclick="enrollSession(<?= intval($nextSession['id']) ?>, this)">
                    <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Enroll Now
                </button>
                <?php endif; ?>
                <a href="/sessions/<?= intval($nextSession['id']) ?>/" class="btn-ghost">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    View Details
                </a>
            </div>
        </div>

        <div class="featured-date-block">
            <div class="featured-date-month"><?= date('M', $nextTs) ?></div>
            <div class="featured-date-day"><?= date('j', $nextTs) ?></div>
            <div class="featured-date-dow"><?= date('l', $nextTs) ?></div>
            <div class="featured-date-time"><?= date('g:i A', $nextTs) ?></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ══════════════════════════════════════
         FILTER BAR
    ═══════════════════════════════════════ -->
    <div class="filter-bar sr d3">
        <div class="filter-tabs">
            <?php foreach ($tabs as $key => $label): ?>
            <a href="?filter=<?= $key ?><?= $subject ? '&subject='.urlencode($subject) : '' ?>"
               class="filter-tab <?= $filter === $key ? 'active' : '' ?>">
                <?= htmlspecialchars($label) ?>
                <?php if ($key === 'enrolled' && count($enrolledSessions) > 0): ?>
                <span class="filter-tab-badge"><?= count($enrolledSessions) ?></span>
                <?php elseif ($key === 'upcoming'): ?>
                <span class="filter-tab-badge"><?= count($upcomingSessions) ?></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="subject-chips">
            <a href="?filter=<?= $filter ?>"
               class="subject-chip <?= !$subject ? 'active' : '' ?>">All Subjects</a>
            <a href="?filter=<?= $filter ?>&subject=math"
               class="subject-chip <?= $subject === 'math' ? 'active' : '' ?>">Math</a>
            <a href="?filter=<?= $filter ?>&subject=reading_writing"
               class="subject-chip <?= $subject === 'reading_writing' ? 'active' : '' ?>">Reading &amp; Writing</a>
        </div>
    </div>

    <!-- ══════════════════════════════════════
         SESSIONS GRID
    ═══════════════════════════════════════ -->
    <div class="sessions-grid sr d4" id="sessionsGrid">
        <?php if (!empty($displaySessions)):
            foreach ($displaySessions as $sess):
                $sTs       = strtotime($sess['scheduled_at'] ?? 'now');
                $sLeft     = intval($sess['spots_left'] ?? 5);
                $sMax      = intval($sess['max_spots']  ?? 20);
                $sPct      = $sMax > 0 ? round((($sMax - $sLeft) / $sMax) * 100) : 0;
                $subj      = $sess['subject'] ?? 'math';
                $badgeCls  = $subj === 'reading_writing' ? 'badge-rw' : 'badge-math';
                $badgeLbl  = $subj === 'reading_writing' ? 'R&amp;W' : 'Math';
                $isEnrolled = in_array(intval($sess['id']), $enrolledIds);
                $fillCls   = $sPct >= 90 ? 'full' : ($sPct >= 65 ? 'warn' : '');
        ?>
        <div class="session-card">
            <div class="session-card-top">
                <div class="session-date-block">
                    <div class="session-date-dow"><?= date('D', $sTs) ?></div>
                    <div class="session-date-num"><?= date('j', $sTs) ?></div>
                </div>
                <div class="session-info">
                    <span class="session-subject-badge <?= $badgeCls ?>"><?= $badgeLbl ?></span>
                    <div class="session-title" title="<?= htmlspecialchars($sess['title'] ?? '') ?>">
                        <?= htmlspecialchars($sess['title'] ?? 'Session') ?>
                    </div>
                    <div class="session-tutor">with <?= htmlspecialchars($sess['tutor_name'] ?? 'Tutor') ?></div>
                </div>
            </div>

            <div class="session-meta">
                <div class="session-meta-item">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <?= date('g:i A', $sTs) ?>&nbsp;·&nbsp;<?= intval($sess['duration_mins'] ?? 60) ?>min
                </div>
                <?php if (!empty($sess['topic'])): ?>
                <div class="session-meta-item">
                    <svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h10M4 18h7"/></svg>
                    <?= htmlspecialchars(mb_strimwidth($sess['topic'], 0, 38, '…')) ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="session-footer">
                <div class="spots-wrap">
                    <div class="spots-track">
                        <div class="spots-fill <?= $fillCls ?>" data-width="<?= $sPct ?>"></div>
                    </div>
                    <span class="spots-text"><?= $sLeft ?> spot<?= $sLeft !== 1 ? 's' : '' ?> left</span>
                </div>
                <?php if ($isEnrolled): ?>
                <button class="btn-session-enroll enrolled" disabled type="button">Enrolled</button>
                <?php elseif ($sLeft > 0): ?>
                <button class="btn-session-enroll" type="button"
                        onclick="enrollSession(<?= intval($sess['id']) ?>, this)">Enroll</button>
                <?php else: ?>
                <button class="btn-session-enroll full" disabled type="button">Full</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach;
        else: ?>
        <div class="empty-state">
            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
            <div class="empty-state-title">No sessions found</div>
            <div class="empty-state-sub">
                There are no <?= $filter === 'enrolled' ? 'enrolled' : 'upcoming' ?> sessions<?= $subject ? ' for this subject' : '' ?> right now.<br>
                Check back soon or browse all sessions.
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ══════════════════════════════════════
         BOTTOM GRID
    ═══════════════════════════════════════ -->
    <div class="bottom-grid sr d5" id="past">

        <!-- Past Sessions -->
        <div class="card-light">
            <div class="card-header">
                <div class="card-header-title">Past Sessions</div>
                <a href="?filter=all" class="card-header-link">View all &rarr;</a>
            </div>
            <?php if (!empty($pastSessions)): ?>
            <div class="past-list">
                <?php foreach ($pastSessions as $ps):
                    $pTs = strtotime($ps['scheduled_at'] ?? 'now');
                ?>
                <a href="/sessions/<?= intval($ps['id']) ?>/" class="past-item">
                    <div class="past-date-block">
                        <div class="past-date-block-month"><?= date('M', $pTs) ?></div>
                        <div class="past-date-block-day"><?= date('j', $pTs) ?></div>
                    </div>
                    <div class="past-item-body">
                        <div class="past-item-title"><?= htmlspecialchars($ps['title'] ?? 'Session') ?></div>
                        <div class="past-item-meta">
                            <?= htmlspecialchars($ps['tutor_name'] ?? '') ?>&nbsp;·&nbsp;<?= intval($ps['duration_mins'] ?? 60) ?> min
                        </div>
                    </div>
                    <div class="past-item-arrow">
                        <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="past-empty">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                No past sessions yet.
            </div>
            <?php endif; ?>
        </div>

        <!-- How It Works -->
        <div class="card-dk">
            <div class="card-dk-bg-grid"></div>
            <div class="card-dk-bg-glow"></div>
            <div class="card-dk-inner">
                <div class="card-dk-header">
                    <div class="card-dk-title">How it works</div>
                </div>
                <div class="how-list">
                    <?php
                    $steps = [
                        ['Browse &amp; Enroll',  'Find a session that fits your schedule and click Enroll.'],
                        ['Join Live',            'No extra software needed — join straight from this page.'],
                        ['Learn &amp; Practice', 'Work through SAT questions with your tutor and peers.'],
                        ['Review &amp; Recap',   'Full recording and notes are saved to your dashboard.'],
                    ];
                    foreach ($steps as $i => $step):
                    ?>
                    <div class="how-item">
                        <div class="how-num"><?= $i + 1 ?></div>
                        <div class="how-body">
                            <div class="how-title"><?= $step[0] ?></div>
                            <div class="how-desc"><?= $step[1] ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div><!-- /bottom-grid -->

</main>

<!-- ── Toast ── -->
<div class="toast" id="toast" role="status" aria-live="polite">
    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
    <span id="toastMsg">Enrolled successfully!</span>
</div>

<script>
(function () {
    'use strict';

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

    /* ── Scroll reveal ── */
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (n) {
            if (n.isIntersecting) { n.target.classList.add('v'); io.unobserve(n.target); }
        });
    }, { threshold: .04, rootMargin: '0px 0px -16px 0px' });
    document.querySelectorAll('.sr').forEach(function (el) { io.observe(el); });

    /* ── Animated progress bars ── */
    var bObs = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (!e.isIntersecting) return;
            var b = e.target;
            setTimeout(function () { b.style.width = (b.dataset.width || 0) + '%'; }, 180);
            bObs.unobserve(b);
        });
    }, { threshold: .05 });
    document.querySelectorAll('[data-width]').forEach(function (b) { bObs.observe(b); });

    /* ── Staggered card entrance ── */
    var gridObs = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (!e.isIntersecting) return;
            var cards = e.target.querySelectorAll('.session-card');
            cards.forEach(function (c, i) {
                c.style.opacity   = '0';
                c.style.transform = 'translateY(14px)';
                setTimeout(function () {
                    c.style.transition = 'opacity .4s cubic-bezier(.16,1,.3,1), transform .4s cubic-bezier(.16,1,.3,1), box-shadow .28s, border-color .2s';
                    c.style.opacity    = '1';
                    c.style.transform  = 'none';
                }, i * 45);
            });
            gridObs.unobserve(e.target);
        });
    }, { threshold: .04 });
    document.querySelectorAll('.sessions-grid').forEach(function (g) { gridObs.observe(g); });

    /* ── Enroll ── */
    window.enrollSession = function (sessId, btn) {
        btn.disabled    = true;
        btn.textContent = '…';
        fetch('/api/enroll-session.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ session_id: sessId })
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success) {
                btn.textContent = 'Enrolled';
                btn.classList.add('enrolled');
                btn.disabled = true;
                showToast('Enrolled successfully!');
            } else {
                btn.disabled    = false;
                btn.textContent = 'Retry';
            }
        })
        .catch(function () {
            btn.disabled    = false;
            btn.textContent = 'Retry';
        });
    };

    /* ── Toast ── */
    function showToast(msg) {
        var t = document.getElementById('toast');
        document.getElementById('toastMsg').textContent = msg;
        t.classList.add('show');
        setTimeout(function () { t.classList.remove('show'); }, 3200);
    }

}());
</script>
</body>
</html>