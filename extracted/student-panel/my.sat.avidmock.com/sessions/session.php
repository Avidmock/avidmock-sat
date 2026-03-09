<?php
/**
 * sessions/session.php — Individual Session Detail Page
 * my.sat.avidmock.com/sessions/[id]/
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Session.php';

Auth::requireStudent();
$userId    = $_SESSION['user_id'];
$user      = User::findById($userId);
$firstName = explode(' ', $user['name'] ?? 'Student')[0];

$sessionId = intval($_GET['id'] ?? 0);
if (!$sessionId) { header('Location: /sessions/'); exit; }

$sess = Session::getById($sessionId);
if (!$sess || !$sess['is_published']) { header('Location: /sessions/'); exit; }

$isEnrolled  = Session::isEnrolled($userId, $sessionId);
$isPast      = Session::isPast($sess);
$maxStudents = intval($sess['max_students'] ?? 20);
$enrolledCnt = intval($sess['enrolled_count'] ?? 0);
$spotsLeft   = max(0, $maxStudents - $enrolledCnt);
$isFull      = $spotsLeft <= 0;

$ts     = strtotime($sess['starts_at'] ?? 'now');
$endTs  = $ts + ($sess['duration_min'] ?? 60) * 60;
$isLive = time() >= $ts && time() <= $endTs;

$agenda   = Session::getAgenda($sessionId);
$students = Session::getEnrolledStudents($sessionId, 8);
$related  = Session::getRelated($sessionId, $sess['subject'] ?? 'math', 3);

$spotsBarPct   = $maxStudents > 0 ? round(($enrolledCnt / $maxStudents) * 100) : 0;
$spotsBarClass = $spotsBarPct >= 100 ? 'full' : ($spotsBarPct >= 80 ? 'warn' : '');

$subjectLabel = match($sess['subject'] ?? 'math') {
    'reading_writing' => 'Reading &amp; Writing',
    'mixed'           => 'Math + R&amp;W',
    default           => 'Math',
};
$subjectBadgeCls = match($sess['subject'] ?? 'math') {
    'reading_writing' => 'sbadge-rw',
    'mixed'           => 'sbadge-mixed',
    default           => 'sbadge-math',
};

$activePage  = 'sessions';
$topbarTitle = $sess['title'] ?? 'Session';
$topbarSub   = 'Session Details';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($sess['title'] ?? 'Session') ?> — Avidmock SAT</title>
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
   TOKENS  (unified platform)
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
    position: fixed; inset: 0;
    background: rgba(0,0,0,.45);
    z-index: 250;
    opacity: 0;
    transition: opacity .28s;
    pointer-events: none;
}
.sidebar-overlay.show { opacity: 1; pointer-events: all; }

/* ─────────────────────────────────────────────
   BACK LINK
───────────────────────────────────────────── */
.back-link {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: .8125rem;
    font-weight: 700;
    color: var(--tx3);
    transition: color .18s;
    margin-bottom: 20px;
}
.back-link:hover { color: var(--dk); }
.back-link svg {
    width: 15px; height: 15px;
    stroke: currentColor; fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
}

/* ─────────────────────────────────────────────
   SESSION HERO
───────────────────────────────────────────── */
.session-hero {
    background: var(--dk);
    border-radius: 20px;
    padding: 2.25rem 2.5rem;
    position: relative;
    overflow: hidden;
    margin-bottom: 20px;
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 32px;
    align-items: start;
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

/* Badges */
.hero-badges {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}
.sbadge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 12px;
    border-radius: 50px;
    font-size: .5625rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .5px;
}
.sbadge-math  { background: rgba(255,255,255,.1);   color: rgba(255,255,255,.7); }
.sbadge-rw    { background: rgba(31,226,144,.1);    color: var(--ac); }
.sbadge-mixed { background: rgba(245,158,11,.1);    color: #fbbf24; }

.live-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 12px;
    border-radius: 50px;
    background: rgba(239,68,68,.15);
    border: 1px solid rgba(239,68,68,.3);
    font-size: .5625rem;
    font-weight: 800;
    color: #ff6b6b;
    text-transform: uppercase;
    letter-spacing: .5px;
    animation: livePulse 1.5s ease-in-out infinite;
}
.live-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    background: #ff6b6b;
}
@keyframes livePulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: .6; }
}

.enrolled-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 12px;
    border-radius: 50px;
    background: rgba(31,226,144,.1);
    border: 1px solid rgba(31,226,144,.2);
    font-size: .5625rem;
    font-weight: 800;
    color: var(--ac);
}
.enrolled-badge svg {
    width: 10px; height: 10px;
    stroke: var(--ac); fill: none;
    stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round;
}

/* Title + description */
.hero-title {
    font-size: clamp(1.25rem, 2.8vw, 1.75rem);
    font-weight: 800;
    color: #fff;
    letter-spacing: -.03em;
    line-height: 1.2;
    margin-bottom: .625rem;
}
.hero-desc {
    font-size: .9375rem;
    color: rgba(255,255,255,.42);
    line-height: 1.7;
    margin-bottom: 1.375rem;
    max-width: 560px;
}

/* Meta row */
.hero-meta-row {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 1.5rem;
}
.hero-meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
}
.hero-meta-item svg {
    width: 14px; height: 14px;
    stroke: rgba(255,255,255,.28); fill: none;
    stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round;
    flex-shrink: 0;
}
.hero-meta-label {
    font-size: .5625rem;
    font-weight: 700;
    color: rgba(255,255,255,.28);
    text-transform: uppercase;
    letter-spacing: .4px;
    margin-bottom: 1px;
}
.hero-meta-val {
    font-size: .875rem;
    font-weight: 700;
    color: rgba(255,255,255,.8);
    line-height: 1;
}

/* Hero action buttons */
.hero-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}

.btn-enroll {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 12px 26px;
    background: var(--ac);
    color: var(--dk);
    font-family: var(--ff);
    font-size: .9375rem;
    font-weight: 800;
    border-radius: 11px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    transition: background .2s, transform .2s, box-shadow .2s;
    min-height: 46px;
}
.btn-enroll:hover {
    background: var(--ac2);
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(31,226,144,.3);
}
.btn-enroll svg {
    width: 15px; height: 15px;
    stroke: var(--dk); fill: none;
    stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round;
    flex-shrink: 0;
}

.btn-join {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 12px 26px;
    background: #ff6b6b;
    color: #fff;
    font-family: var(--ff);
    font-size: .9375rem;
    font-weight: 800;
    border-radius: 11px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    animation: joinPulse 2s ease-in-out infinite;
    transition: transform .2s, box-shadow .2s;
    min-height: 46px;
}
.btn-join:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(239,68,68,.3);
}
@keyframes joinPulse {
    0%, 100% { box-shadow: 0 0 0 0   rgba(255,107,107,.3); }
    50%       { box-shadow: 0 0 0 8px rgba(255,107,107,0); }
}
.btn-join svg {
    width: 15px; height: 15px;
    stroke: #fff; fill: none;
    stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round;
    flex-shrink: 0;
}

.btn-ghost {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 12px 22px;
    background: rgba(255,255,255,.06);
    color: rgba(255,255,255,.65);
    font-family: var(--ff);
    font-size: .9375rem;
    font-weight: 700;
    border-radius: 11px;
    border: 1px solid rgba(255,255,255,.1);
    cursor: pointer;
    text-decoration: none;
    transition: background .2s, color .2s;
    min-height: 46px;
}
.btn-ghost:hover { background: rgba(255,255,255,.11); color: #fff; }
.btn-ghost svg {
    width: 14px; height: 14px;
    stroke: currentColor; fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
    flex-shrink: 0;
}

/* Date block (hero right) */
.hero-date-block {
    position: relative;
    z-index: 1;
    text-align: center;
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 16px;
    padding: 24px 32px;
    min-width: 134px;
    flex-shrink: 0;
}
.hero-date-month {
    font-size: .5625rem;
    font-weight: 800;
    color: var(--ac);
    text-transform: uppercase;
    letter-spacing: .9px;
    margin-bottom: 2px;
}
.hero-date-day {
    font-size: 3rem;
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
    font-size: .875rem;
    font-weight: 800;
    color: var(--ac);
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid rgba(255,255,255,.07);
}
.hero-date-dur {
    font-size: .625rem;
    font-weight: 600;
    color: rgba(255,255,255,.28);
    margin-top: 3px;
}

/* ─────────────────────────────────────────────
   PAST NOTICE BANNER
───────────────────────────────────────────── */
.past-notice {
    background: rgba(20,50,48,.04);
    border: 1px solid var(--bd);
    border-radius: var(--r);
    padding: 13px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    font-size: .875rem;
    color: var(--tx2);
    font-weight: 500;
}
.past-notice svg {
    width: 17px; height: 17px;
    stroke: var(--tx3); fill: none;
    stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round;
    flex-shrink: 0;
}
.past-notice a { color: var(--ac); font-weight: 700; }

/* ─────────────────────────────────────────────
   TWO-COLUMN GRID
───────────────────────────────────────────── */
.session-grid {
    display: grid;
    grid-template-columns: 1fr 310px;
    gap: 16px;
    align-items: start;
}
.col-left  { display: flex; flex-direction: column; gap: 16px; }
.col-right { display: flex; flex-direction: column; gap: 14px; }

/* ─────────────────────────────────────────────
   CONTENT CARDS (light)
───────────────────────────────────────────── */
.card {
    background: var(--bg2);
    border: 1px solid var(--bd);
    border-radius: var(--r-lg);
    overflow: hidden;
    transition: border-color .18s;
}
.card:hover { border-color: var(--bd2); }

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--bd);
}
.card-header-title {
    font-size: .8125rem;
    font-weight: 800;
    color: var(--tx);
    letter-spacing: -.01em;
}
.card-header-meta {
    font-size: .75rem;
    font-weight: 600;
    color: var(--tx3);
}
.card-header-link {
    font-size: .75rem;
    font-weight: 600;
    color: var(--ac);
    transition: opacity .15s;
}
.card-header-link:hover { opacity: .7; }
.card-body { padding: 1.125rem 1.25rem; }

/* ─────────────────────────────────────────────
   AGENDA
───────────────────────────────────────────── */
.agenda-list { display: flex; flex-direction: column; }
.agenda-item {
    display: flex;
    gap: 14px;
    padding: 13px 0;
    border-bottom: 1px solid var(--bd);
}
.agenda-item:first-child { padding-top: 0; }
.agenda-item:last-child  { border-bottom: none; padding-bottom: 0; }

.agenda-num {
    flex-shrink: 0;
    width: 28px; height: 28px;
    border-radius: 50%;
    background: rgba(20,50,48,.06);
    display: flex; align-items: center; justify-content: center;
    font-size: .75rem;
    font-weight: 800;
    color: var(--dk);
    margin-top: 1px;
}
.agenda-body { flex: 1; min-width: 0; }
.agenda-title { font-size: .9375rem; font-weight: 700; color: var(--tx); margin-bottom: 3px; }
.agenda-desc  { font-size: .8125rem; color: var(--tx3); line-height: 1.55; margin-bottom: 6px; }
.agenda-dur {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: .625rem;
    font-weight: 700;
    color: var(--tx3);
}
.agenda-dur svg {
    width: 10px; height: 10px;
    stroke: var(--tx3); fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
}

.agenda-empty {
    text-align: center;
    padding: 1.75rem;
    color: var(--tx3);
    font-size: .875rem;
}

/* ─────────────────────────────────────────────
   TOPICS
───────────────────────────────────────────── */
.topics-list {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
}
.topic-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 50px;
    background: var(--bg);
    border: 1px solid var(--bd);
    font-size: .75rem;
    font-weight: 600;
    color: var(--tx2);
    transition: border-color .15s, color .15s;
}
.topic-chip:hover { border-color: var(--ac); color: var(--dk); }
.topic-chip-dot {
    width: 5px; height: 5px;
    border-radius: 50%;
    background: var(--ac);
    flex-shrink: 0;
}

/* ─────────────────────────────────────────────
   RELATED SESSIONS
───────────────────────────────────────────── */
.related-list { display: flex; flex-direction: column; gap: 8px; }
.related-item {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 10px 12px;
    border-radius: var(--r-sm);
    border: 1px solid var(--bd);
    background: var(--bg);
    text-decoration: none;
    transition: border-color .18s, background .18s, transform .2s;
}
.related-item:hover {
    border-color: rgba(31,226,144,.3);
    background: rgba(31,226,144,.02);
    transform: translateX(3px);
}
.related-date-block {
    flex-shrink: 0;
    width: 38px;
    text-align: center;
    background: var(--dk);
    border-radius: 8px;
    padding: 5px 0;
}
.related-date-dow { font-size: .4375rem; font-weight: 800; color: var(--ac); text-transform: uppercase; }
.related-date-num { font-size: 1rem; font-weight: 800; color: #fff; line-height: 1.1; letter-spacing: -.03em; }

.related-body { flex: 1; min-width: 0; }
.related-title {
    font-size: .8125rem;
    font-weight: 700;
    color: var(--tx);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.3;
    margin-bottom: 2px;
}
.related-meta { font-size: .6875rem; color: var(--tx3); }

.related-enroll-btn {
    flex-shrink: 0;
    padding: 5px 11px;
    border-radius: 7px;
    font-family: var(--ff);
    font-size: .625rem;
    font-weight: 700;
    background: var(--ac);
    color: var(--dk);
    border: none;
    cursor: pointer;
    transition: background .15s;
    min-height: 28px;
}
.related-enroll-btn:hover { background: var(--ac2); }
.related-enrolled-check {
    font-size: .6875rem;
    font-weight: 800;
    color: var(--ac);
    flex-shrink: 0;
}

/* ─────────────────────────────────────────────
   AVAILABILITY CARD
───────────────────────────────────────────── */
.student-avatars {
    display: flex;
    margin-bottom: 14px;
    padding-left: 6px;
}
.student-avatar {
    width: 30px; height: 30px;
    border-radius: 50%;
    border: 2px solid var(--bg2);
    margin-left: -6px;
    display: flex; align-items: center; justify-content: center;
    font-size: .6875rem;
    font-weight: 800;
    color: #fff;
    flex-shrink: 0;
}
.student-avatar-more {
    background: var(--bg);
    color: var(--tx3);
    border-color: var(--bd);
    font-size: .5625rem;
}

.spots-track-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 7px;
}
.spots-track-label { font-size: .8125rem; font-weight: 600; color: var(--tx2); }
.spots-track-nums  { font-size: .8125rem; font-weight: 800; }
.spots-track-nums .used { color: var(--tx); }
.spots-track-nums .sep  { color: var(--bd2); margin: 0 2px; }
.spots-track-nums .max  { color: var(--tx3); }

.spots-bar-big {
    height: 7px;
    background: var(--bd);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 6px;
}
.spots-bar-fill {
    height: 100%;
    border-radius: 4px;
    background: linear-gradient(90deg, var(--ac2), var(--ac));
    transition: width 1.2s cubic-bezier(.16,1,.3,1);
    width: 0%;
}
.spots-bar-fill.warn { background: linear-gradient(90deg, #f97316, var(--warn)); }
.spots-bar-fill.full { background: linear-gradient(90deg, #dc2626, var(--err)); }

.spots-remaining {
    font-size: .75rem;
    font-weight: 700;
    color: var(--tx3);
    margin-bottom: 14px;
}
.spots-remaining.few  { color: var(--warn); }
.spots-remaining.none { color: var(--err); }

.enroll-full-btn {
    width: 100%;
    padding: 11px;
    border-radius: 10px;
    font-family: var(--ff);
    font-size: .9375rem;
    font-weight: 800;
    border: none;
    cursor: pointer;
    background: var(--ac);
    color: var(--dk);
    transition: background .2s, transform .2s, box-shadow .2s;
    min-height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
}
.enroll-full-btn:hover {
    background: var(--ac2);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(31,226,144,.28);
}
.enroll-full-btn svg {
    width: 14px; height: 14px;
    stroke: var(--dk); fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
}

.enrolled-confirm {
    text-align: center;
    padding: .875rem;
    background: rgba(31,226,144,.06);
    border: 1px solid rgba(31,226,144,.18);
    border-radius: 10px;
    font-size: .875rem;
    font-weight: 700;
    color: var(--ac);
}

/* ─────────────────────────────────────────────
   TUTOR CARD
───────────────────────────────────────────── */
.tutor-profile { display: flex; align-items: flex-start; gap: 13px; }
.tutor-avatar {
    width: 50px; height: 50px;
    border-radius: 13px;
    background: var(--dk);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--ac);
    flex-shrink: 0;
}
.tutor-name  { font-size: .9375rem; font-weight: 800; color: var(--tx); letter-spacing: -.015em; margin-bottom: 2px; }
.tutor-title { font-size: .75rem; color: var(--tx3); margin-bottom: 9px; }
.tutor-stats { display: flex; gap: 14px; flex-wrap: wrap; }
.tutor-stat-val { font-size: .875rem; font-weight: 800; color: var(--tx); line-height: 1; }
.tutor-stat-val.ac { color: var(--ac); }
.tutor-stat-key { font-size: .5625rem; color: var(--tx3); font-weight: 600; margin-top: 2px; }
.tutor-bio {
    font-size: .8125rem;
    color: var(--tx3);
    line-height: 1.65;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--bd);
}

/* ─────────────────────────────────────────────
   WHAT TO PREPARE CARD
───────────────────────────────────────────── */
.prep-list { display: flex; flex-direction: column; gap: 10px; }
.prep-item { display: flex; align-items: center; gap: 11px; }
.prep-ico {
    width: 30px; height: 30px;
    border-radius: 9px;
    background: rgba(31,226,144,.08);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.prep-ico svg {
    width: 14px; height: 14px;
    stroke: var(--ac); fill: none;
    stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round;
}
.prep-text { font-size: .8125rem; color: var(--tx2); font-weight: 500; }

/* ─────────────────────────────────────────────
   RESPONSIVE
   ─────────────────────────────────────────────
   ≥ 1100   2-col hero + 2-col session grid
   ≤ 1100   hero collapses date block; grid stacks
   ≤ 900    sidebar off, full width
   ≤ 600    smaller hero, tighter body
   ≤ 480    tightest phones
───────────────────────────────────────────── */

@media (max-width: 1100px) {
    .session-hero { grid-template-columns: 1fr; }
    .hero-date-block { display: none; }
    .session-grid { grid-template-columns: 1fr; }
    .col-right {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 14px;
    }
}

@media (max-width: 900px) {
    .main-content { margin-left: 0; padding: 20px var(--pad-sm) 72px; }
}

@media (max-width: 600px) {
    .session-hero { padding: 1.375rem 1.125rem; border-radius: 16px; }
    .hero-title   { font-size: 1.25rem; }
    .hero-desc    { font-size: .875rem; }
    .hero-meta-row { gap: 14px; }
    .hero-meta-val { font-size: .8125rem; }
    .hero-actions { gap: 8px; }
    .btn-enroll, .btn-join, .btn-ghost { padding: 10px 18px; font-size: .875rem; min-height: 42px; }

    .col-right { grid-template-columns: 1fr; }

    .card-header  { padding: .875rem 1rem; }
    .card-body    { padding: 1rem; }
    .agenda-title { font-size: .875rem; }
}

@media (max-width: 480px) {
    .session-hero { padding: 1.125rem 1rem; border-radius: 14px; }
    .hero-title   { font-size: 1.125rem; }
    .hero-meta-row { gap: 10px; }
    .hero-badges  { gap: 6px; }
    .sbadge, .live-badge, .enrolled-badge { padding: 3px 9px; font-size: .5rem; }
    .btn-enroll, .btn-join { width: 100%; justify-content: center; }
    .hero-actions { flex-direction: column; }
    .btn-ghost { width: 100%; justify-content: center; }
    .tutor-avatar { width: 42px; height: 42px; font-size: 1.0625rem; border-radius: 11px; }
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

    <!-- Back link -->
    <a href="/sessions/" class="back-link sr">
        <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Sessions
    </a>

    <!-- ══════════════════════════════════════
         SESSION HERO
    ═══════════════════════════════════════ -->
    <div class="session-hero sr d1">
        <div class="hero-bg-grid"></div>
        <div class="hero-bg-glow"></div>

        <div class="hero-left">
            <!-- Badges -->
            <div class="hero-badges">
                <span class="sbadge <?= $subjectBadgeCls ?>"><?= $subjectLabel ?></span>
                <?php if ($isLive): ?>
                <span class="live-badge">
                    <span class="live-dot"></span>
                    Live Now
                </span>
                <?php elseif ($isEnrolled): ?>
                <span class="enrolled-badge">
                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    Enrolled
                </span>
                <?php endif; ?>
                <?php if ($isPast): ?>
                <span class="sbadge" style="background:rgba(255,255,255,.07);color:rgba(255,255,255,.4)">Past Session</span>
                <?php endif; ?>
            </div>

            <h1 class="hero-title"><?= htmlspecialchars($sess['title'] ?? 'Group Session') ?></h1>

            <?php if (!empty($sess['description'])): ?>
            <p class="hero-desc"><?= nl2br(htmlspecialchars($sess['description'])) ?></p>
            <?php endif; ?>

            <!-- Meta -->
            <div class="hero-meta-row">
                <div class="hero-meta-item">
                    <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <div>
                        <div class="hero-meta-label">Tutor</div>
                        <div class="hero-meta-val"><?= htmlspecialchars($sess['tutor_name'] ?? 'Expert Tutor') ?></div>
                    </div>
                </div>
                <div class="hero-meta-item">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <div>
                        <div class="hero-meta-label">Duration</div>
                        <div class="hero-meta-val"><?= intval($sess['duration_min'] ?? 60) ?> min</div>
                    </div>
                </div>
                <div class="hero-meta-item">
                    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                    <div>
                        <div class="hero-meta-label">Students</div>
                        <div class="hero-meta-val"><?= $enrolledCnt ?> / <?= $maxStudents ?></div>
                    </div>
                </div>
                <div class="hero-meta-item">
                    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <div>
                        <div class="hero-meta-label">Date</div>
                        <div class="hero-meta-val"><?= date('M j, Y', $ts) ?></div>
                    </div>
                </div>
            </div>

            <!-- Actions — context-aware -->
            <div class="hero-actions">
                <?php if ($isPast): ?>
                    <?php if ($isEnrolled): ?>
                    <a href="/sessions/past/" class="btn-ghost" style="background:rgba(31,226,144,.08);border-color:rgba(31,226,144,.22);color:var(--ac)">
                        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        View Recap
                    </a>
                    <?php endif; ?>
                    <a href="/sessions/" class="btn-ghost">
                        <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        Browse Sessions
                    </a>

                <?php elseif ($isLive && $isEnrolled): ?>
                    <a href="<?= htmlspecialchars($sess['join_url'] ?? '#') ?>" class="btn-join" target="_blank" rel="noopener">
                        <svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                        Join Session Now
                    </a>
                    <a href="#agenda" class="btn-ghost">
                        <svg viewBox="0 0 24 24"><path d="M9 12h6M9 16h4M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/></svg>
                        View Agenda
                    </a>

                <?php elseif ($isEnrolled): ?>
                    <button class="btn-enroll" style="background:rgba(31,226,144,.14);color:var(--ac);border:1px solid rgba(31,226,144,.25);cursor:default" disabled>
                        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                        Enrolled — See you there!
                    </button>
                    <a href="#agenda" class="btn-ghost">
                        <svg viewBox="0 0 24 24"><path d="M9 12h6M9 16h4M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/></svg>
                        View Agenda
                    </a>

                <?php elseif ($isFull): ?>
                    <button class="btn-enroll" style="background:rgba(239,68,68,.08);color:var(--err);border:1px solid rgba(239,68,68,.18);cursor:not-allowed" disabled>
                        Session Full
                    </button>
                    <a href="/sessions/" class="btn-ghost">
                        <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        Find Another
                    </a>

                <?php else: ?>
                    <button class="btn-enroll" id="enrollBtn" onclick="doEnroll(<?= $sessionId ?>, this)">
                        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Enroll Now — Free
                    </button>
                    <a href="#agenda" class="btn-ghost">
                        <svg viewBox="0 0 24 24"><path d="M9 12h6M9 16h4M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/></svg>
                        View Agenda
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Date block (right, hidden ≤1100px) -->
        <div class="hero-date-block">
            <div class="hero-date-month"><?= date('M', $ts) ?></div>
            <div class="hero-date-day"><?= date('j', $ts) ?></div>
            <div class="hero-date-dow"><?= date('l', $ts) ?></div>
            <div class="hero-date-time"><?= date('g:i A', $ts) ?></div>
            <div class="hero-date-dur"><?= intval($sess['duration_min'] ?? 60) ?> min</div>
        </div>
    </div>

    <!-- Past notice -->
    <?php if ($isPast): ?>
    <div class="past-notice sr d2">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        This session has already taken place.
        <?php if ($isEnrolled): ?>
        The recording and materials are in your <a href="/sessions/past/">past sessions</a>.
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ══════════════════════════════════════
         TWO-COLUMN GRID
    ═══════════════════════════════════════ -->
    <div class="session-grid">

        <!-- ── LEFT COLUMN ── -->
        <div class="col-left">

            <!-- Agenda -->
            <div class="card sr d2" id="agenda">
                <div class="card-header">
                    <div class="card-header-title">Session Agenda</div>
                    <?php if (!empty($agenda)): ?>
                    <div class="card-header-meta"><?= array_sum(array_column($agenda, 'duration_min')) ?> min total</div>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (!empty($agenda)): ?>
                    <div class="agenda-list">
                        <?php foreach ($agenda as $i => $item): ?>
                        <div class="agenda-item">
                            <div class="agenda-num"><?= $i + 1 ?></div>
                            <div class="agenda-body">
                                <div class="agenda-title"><?= htmlspecialchars($item['title'] ?? '') ?></div>
                                <?php if (!empty($item['description'])): ?>
                                <div class="agenda-desc"><?= htmlspecialchars($item['description']) ?></div>
                                <?php endif; ?>
                                <div class="agenda-dur">
                                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    <?= intval($item['duration_min'] ?? 10) ?> min
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="agenda-empty">Agenda will be posted closer to the session date.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Topics -->
            <?php if (!empty($sess['topics'])): ?>
            <div class="card sr d3">
                <div class="card-header">
                    <div class="card-header-title">Topics Covered</div>
                </div>
                <div class="card-body">
                    <div class="topics-list">
                        <?php foreach (json_decode($sess['topics'], true) ?? [] as $topic): ?>
                        <span class="topic-chip">
                            <span class="topic-chip-dot"></span>
                            <?= htmlspecialchars($topic) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Related sessions -->
            <?php if (!empty($related)): ?>
            <div class="card sr d4">
                <div class="card-header">
                    <div class="card-header-title">More Sessions</div>
                    <a href="/sessions/" class="card-header-link">View all &rarr;</a>
                </div>
                <div class="card-body">
                    <div class="related-list">
                        <?php foreach ($related as $rel):
                            $rTs = strtotime($rel['starts_at'] ?? 'now');
                            $relEnrolled = Session::isEnrolled($userId, intval($rel['id']));
                        ?>
                        <a href="/sessions/<?= intval($rel['id']) ?>/" class="related-item">
                            <div class="related-date-block">
                                <div class="related-date-dow"><?= date('D', $rTs) ?></div>
                                <div class="related-date-num"><?= date('j', $rTs) ?></div>
                            </div>
                            <div class="related-body">
                                <div class="related-title"><?= htmlspecialchars($rel['title'] ?? 'Session') ?></div>
                                <div class="related-meta"><?= date('M j · g:i A', $rTs) ?> · <?= htmlspecialchars($rel['tutor_name'] ?? '') ?></div>
                            </div>
                            <?php if ($relEnrolled): ?>
                            <span class="related-enrolled-check">✓</span>
                            <?php else: ?>
                            <button class="related-enroll-btn"
                                    onclick="event.preventDefault(); doEnroll(<?= intval($rel['id']) ?>, this)">
                                Enroll
                            </button>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /col-left -->

        <!-- ── RIGHT COLUMN ── -->
        <div class="col-right">

            <!-- Availability -->
            <div class="card sr d2">
                <div class="card-header">
                    <div class="card-header-title">Availability</div>
                </div>
                <div class="card-body">
                    <!-- Student avatar stack -->
                    <?php if (!empty($students)): ?>
                    <div class="student-avatars">
                        <?php
                        $avatarColors = ['#143230','#1a3f3c','#0d7a4a','#1e6b5e','#2d4a3e','#1a5045','#0a3d2e'];
                        foreach ($students as $i => $s):
                            $bg = $avatarColors[$i % count($avatarColors)];
                        ?>
                        <div class="student-avatar"
                             style="background:<?= $bg ?>;z-index:<?= 20 - $i ?>">
                            <?= strtoupper(substr($s['name'] ?? '?', 0, 1)) ?>
                        </div>
                        <?php endforeach; ?>
                        <?php if ($enrolledCnt > count($students)): ?>
                        <div class="student-avatar student-avatar-more">
                            +<?= $enrolledCnt - count($students) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Spots bar -->
                    <div class="spots-track-top">
                        <span class="spots-track-label">Students enrolled</span>
                        <span class="spots-track-nums">
                            <span class="used"><?= $enrolledCnt ?></span>
                            <span class="sep">/</span>
                            <span class="max"><?= $maxStudents ?></span>
                        </span>
                    </div>
                    <div class="spots-bar-big">
                        <div class="spots-bar-fill <?= $spotsBarClass ?>"
                             data-width="<?= $spotsBarPct ?>"
                             style="width:0%"></div>
                    </div>
                    <div class="spots-remaining <?= $spotsLeft === 0 ? 'none' : ($spotsLeft <= 3 ? 'few' : '') ?>">
                        <?php if ($spotsLeft === 0): ?>
                            Session is full
                        <?php elseif ($spotsLeft <= 3): ?>
                            Only <?= $spotsLeft ?> spot<?= $spotsLeft !== 1 ? 's' : '' ?> left!
                        <?php else: ?>
                            <?= $spotsLeft ?> spots available
                        <?php endif; ?>
                    </div>

                    <?php if (!$isPast && !$isEnrolled && !$isFull): ?>
                    <button class="enroll-full-btn" id="enrollBtnSide" onclick="doEnroll(<?= $sessionId ?>, this)">
                        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Enroll Now
                    </button>
                    <?php elseif ($isEnrolled && !$isPast): ?>
                    <div class="enrolled-confirm">✓ You're enrolled</div>
                    <?php elseif ($isLive && $isEnrolled): ?>
                    <a href="<?= htmlspecialchars($sess['join_url'] ?? '#') ?>"
                       class="enroll-full-btn"
                       style="background:#ff6b6b;color:#fff;text-decoration:none"
                       target="_blank" rel="noopener">
                        <svg viewBox="0 0 24 24" style="stroke:#fff"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                        Join Now
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tutor -->
            <div class="card sr d3">
                <div class="card-header">
                    <div class="card-header-title">Your Tutor</div>
                </div>
                <div class="card-body">
                    <div class="tutor-profile">
                        <div class="tutor-avatar">
                            <?= strtoupper(substr($sess['tutor_name'] ?? 'T', 0, 1)) ?>
                        </div>
                        <div style="flex:1;min-width:0">
                            <div class="tutor-name"><?= htmlspecialchars($sess['tutor_name'] ?? 'Expert Tutor') ?></div>
                            <div class="tutor-title"><?= htmlspecialchars($sess['tutor_title'] ?? 'SAT Expert') ?></div>
                            <div class="tutor-stats">
                                <div>
                                    <div class="tutor-stat-val"><?= intval($sess['tutor_sessions'] ?? 0) ?>+</div>
                                    <div class="tutor-stat-key">Sessions</div>
                                </div>
                                <div>
                                    <div class="tutor-stat-val"><?= intval($sess['tutor_students'] ?? 0) ?>+</div>
                                    <div class="tutor-stat-key">Students</div>
                                </div>
                                <div>
                                    <div class="tutor-stat-val ac">★ <?= number_format(floatval($sess['tutor_rating'] ?? 4.9), 1) ?></div>
                                    <div class="tutor-stat-key">Rating</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($sess['tutor_bio'])): ?>
                    <div class="tutor-bio"><?= htmlspecialchars($sess['tutor_bio']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- What to prepare -->
            <div class="card sr d4">
                <div class="card-header">
                    <div class="card-header-title">What to Prepare</div>
                </div>
                <div class="card-body">
                    <div class="prep-list">
                        <?php
                        $prepItems = [
                            ['M9 12h6M9 16h4M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z',           'Paper &amp; pencil for scratch work'],
                            ['M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z', 'Questions ready for your tutor'],
                            ['M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3zM19 10v2a7 7 0 01-14 0v-2',     'Working microphone &amp; camera'],
                            ['M13 10V3L4 14h7v7l9-11h-7z',                                                     'Strong internet connection'],
                        ];
                        foreach ($prepItems as [$icon, $label]):
                        ?>
                        <div class="prep-item">
                            <div class="prep-ico">
                                <svg viewBox="0 0 24 24"><path d="<?= $icon ?>"/></svg>
                            </div>
                            <span class="prep-text"><?= $label ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div><!-- /col-right -->

    </div><!-- /session-grid -->

</main>

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

    /* ── Animated spots bar ── */
    var bObs = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (!e.isIntersecting) return;
            var b = e.target;
            setTimeout(function () { b.style.width = (b.dataset.width || 0) + '%'; }, 200);
            bObs.unobserve(b);
        });
    }, { threshold: .05 });
    document.querySelectorAll('[data-width]').forEach(function (b) { bObs.observe(b); });

    /* ── Staggered agenda entrance ── */
    var agObs = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (!e.isIntersecting) return;
            var items = e.target.querySelectorAll('.agenda-item');
            items.forEach(function (item, i) {
                item.style.opacity   = '0';
                item.style.transform = 'translateX(-8px)';
                setTimeout(function () {
                    item.style.transition = 'opacity .35s cubic-bezier(.16,1,.3,1), transform .35s cubic-bezier(.16,1,.3,1)';
                    item.style.opacity    = '1';
                    item.style.transform  = 'none';
                }, i * 50);
            });
            agObs.unobserve(e.target);
        });
    }, { threshold: .04 });
    document.querySelectorAll('.agenda-list').forEach(function (g) { agObs.observe(g); });

    /* ── Enroll ── */
    window.doEnroll = function (sessId, btn) {
        btn.disabled = true;
        var orig = btn.innerHTML;
        btn.textContent = '…';

        fetch('/api/enroll-session.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body:    JSON.stringify({ session_id: sessId }),
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success) {
                /* Hero enroll button */
                var heroBtn = document.getElementById('enrollBtn');
                if (heroBtn) {
                    heroBtn.innerHTML = '<svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:var(--ac);fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round"><polyline points="20 6 9 17 4 12"/></svg> Enrolled — See you there!';
                    heroBtn.style.background   = 'rgba(31,226,144,.14)';
                    heroBtn.style.color        = 'var(--ac)';
                    heroBtn.style.border       = '1px solid rgba(31,226,144,.25)';
                    heroBtn.style.cursor       = 'default';
                    heroBtn.style.boxShadow    = 'none';
                    heroBtn.style.transform    = 'none';
                    heroBtn.disabled = true;
                }
                /* Side enroll button → enrolled confirm */
                var sideBtn = document.getElementById('enrollBtnSide');
                if (sideBtn) {
                    sideBtn.outerHTML = '<div class="enrolled-confirm">✓ You\'re enrolled</div>';
                }
                /* Add enrolled badge to hero if not present */
                var existingBadge = document.querySelector('.enrolled-badge');
                if (!existingBadge) {
                    var badges = document.querySelector('.hero-badges');
                    if (badges) {
                        var badge = document.createElement('span');
                        badge.className = 'enrolled-badge';
                        badge.innerHTML = '<svg viewBox="0 0 24 24" style="width:10px;height:10px;stroke:var(--ac);fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round"><polyline points="20 6 9 17 4 12"/></svg> Enrolled';
                        badges.appendChild(badge);
                    }
                }
                /* Update related enroll button if it triggered this */
                if (btn !== heroBtn && btn !== document.getElementById('enrollBtnSide')) {
                    btn.outerHTML = '<span class="related-enrolled-check">✓</span>';
                }
            } else {
                btn.innerHTML = orig;
                btn.disabled  = false;
                alert(res.message || 'Enrollment failed — please try again.');
            }
        })
        .catch(function () {
            btn.innerHTML = orig;
            btn.disabled  = false;
        });
    };

}());
</script>
</body>
</html>