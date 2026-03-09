<?php
/**
 * sessions/past.php — Past Sessions History
 * my.sat.avidmock.com/sessions/past/
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Session.php';

Auth::requireStudent();
$userId    = $_SESSION['user_id'];
$user      = User::findById($userId);
$firstName = explode(' ', $user['name'] ?? 'Student')[0];

$subject = $_GET['subject'] ?? '';
$page    = max(1, intval($_GET['page'] ?? 1));
$perPage = 8;

$allPast  = Session::getPast($userId, null, $subject);
$total    = count($allPast);
$totalPgs = max(1, ceil($total / $perPage));
$pastPage = array_slice($allPast, ($page - 1) * $perPage, $perPage);

$totalAttended = $total;
$totalMinutes  = array_sum(array_column($allPast, 'duration_min'));
$totalHours    = round($totalMinutes / 60, 1);
$avgScoreBoost = $total > 0 ? round(array_sum(array_column($allPast, 'score_improvement')) / $total) : 0;
$uniqueTutors  = count(array_unique(array_column($allPast, 'tutor_name')));
$mathSessions  = count(array_filter($allPast, fn($s) => ($s['subject'] ?? '') === 'math'));
$rwSessions    = count(array_filter($allPast, fn($s) => ($s['subject'] ?? '') === 'reading_writing'));

$activePage  = 'sessions';
$topbarTitle = 'Past Sessions';
$topbarSub   = 'Your complete group sessions history';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Past Sessions — Avidmock SAT</title>
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
   TOKENS  (unified with entire platform)
───────────────────────────────────────────── */
:root {
    --dk: #143230;   --dk2: #1a3f3c;
    --ac: #1fe290;   --ac2: #17c87a;
    --tx: #1a1a2e;   --tx2: #4a4a5a;  --tx3: #8a8a9a;
    --bg: #f7faf9;   --bg2: #ffffff;  --bd: #e2ebe9;  --bd2: #d1dcd9;
    --warn: #f59e0b; --err: #ef4444;  --ok: #10b981;
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
.hero-inner { position: relative; z-index: 1; }

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

.hero-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 24px;
    flex-wrap: wrap;
    margin-bottom: 20px;
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
}

/* Browse button in hero */
.hero-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 10px 20px;
    background: rgba(255,255,255,.07);
    color: rgba(255,255,255,.7);
    font-family: var(--ff);
    font-size: .8125rem;
    font-weight: 700;
    border-radius: 10px;
    border: 1px solid rgba(255,255,255,.1);
    text-decoration: none;
    transition: background .2s, color .2s;
    white-space: nowrap;
    flex-shrink: 0;
    min-height: 40px;
}
.hero-btn:hover { background: rgba(255,255,255,.12); color: #fff; }
.hero-btn svg {
    width: 14px; height: 14px;
    stroke: var(--ac); fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
    flex-shrink: 0;
}

/* Hero stats row */
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

/* ─────────────────────────────────────────────
   FILTER BAR
───────────────────────────────────────────── */
.filter-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.subject-chips {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
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
    border-color: rgba(20,50,48,.2);
    color: var(--dk);
    font-weight: 800;
}
.subject-chip.active-math {
    background: rgba(20,50,48,.08);
    border-color: rgba(20,50,48,.22);
    color: var(--dk);
    font-weight: 800;
}
.subject-chip.active-rw {
    background: rgba(31,226,144,.07);
    border-color: rgba(31,226,144,.25);
    color: #0d7a4a;
    font-weight: 800;
}

.filter-count {
    font-size: .8125rem;
    font-weight: 600;
    color: var(--tx3);
    white-space: nowrap;
}

/* ─────────────────────────────────────────────
   PAST SESSION CARDS
───────────────────────────────────────────── */
.sessions-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 24px;
}

.past-card {
    background: var(--bg2);
    border: 1.5px solid var(--bd);
    border-radius: var(--r-lg);
    display: grid;
    grid-template-columns: 72px 1fr auto;
    overflow: hidden;
    text-decoration: none;
    transition: transform .28s cubic-bezier(.16,1,.3,1), box-shadow .28s, border-color .2s;
    position: relative;
}
.past-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; bottom: 0;
    width: 2px;
    background: transparent;
    transition: background .2s;
    z-index: 1;
}
.past-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(20,50,48,.09);
    border-color: rgba(31,226,144,.3);
}
.past-card:hover::before {
    background: var(--ac);
}

/* Date column */
.past-card-date {
    background: var(--dk);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 16px 0;
    flex-shrink: 0;
    gap: 1px;
}
.past-card-date-month {
    font-size: .4375rem;
    font-weight: 800;
    color: var(--ac);
    text-transform: uppercase;
    letter-spacing: .5px;
}
.past-card-date-day {
    font-size: 1.625rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: -.04em;
    line-height: 1;
}
.past-card-date-year {
    font-size: .4375rem;
    font-weight: 600;
    color: rgba(255,255,255,.28);
    margin-top: 1px;
}

/* Body */
.past-card-body {
    padding: 14px 18px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 6px;
    min-width: 0;
}
.past-card-badges {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.subj-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 50px;
    font-size: .5625rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .4px;
}
.badge-math  { background: rgba(20,50,48,.07);  color: var(--dk); }
.badge-rw    { background: rgba(31,226,144,.08); color: #0d7a4a; }
.badge-mixed { background: rgba(245,158,11,.08); color: #92400e; }

.attended-pill {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 2px 8px;
    border-radius: 50px;
    font-size: .5625rem;
    font-weight: 800;
    background: rgba(16,185,129,.07);
    color: var(--ok);
}
.attended-pill svg {
    width: 9px; height: 9px;
    stroke: var(--ok); fill: none;
    stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round;
}

.past-card-title {
    font-size: .9375rem;
    font-weight: 700;
    color: var(--tx);
    letter-spacing: -.015em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.3;
}
.past-card-meta {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}
.past-card-meta-item {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: .75rem;
    color: var(--tx3);
}
.past-card-meta-item svg {
    width: 11px; height: 11px;
    stroke: var(--tx3); fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
    flex-shrink: 0;
}

/* Actions column */
.past-card-actions {
    padding: 14px 16px;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    justify-content: center;
    gap: 8px;
    flex-shrink: 0;
}

/* Score boost pill */
.score-boost {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 8px 13px;
    border-radius: 10px;
    min-width: 64px;
}
.score-boost.pos { background: rgba(31,226,144,.06); border: 1px solid rgba(31,226,144,.18); }
.score-boost.neg { background: rgba(239,68,68,.04);  border: 1px solid rgba(239,68,68,.14); }
.score-boost.nil { background: var(--bg);            border: 1px solid var(--bd); }

.score-boost-val {
    font-size: 1.125rem;
    font-weight: 800;
    letter-spacing: -.03em;
    line-height: 1;
}
.score-boost.pos .score-boost-val { color: var(--ac); }
.score-boost.neg .score-boost-val { color: var(--err); }
.score-boost.nil .score-boost-val { color: var(--tx3); }
.score-boost-key {
    font-size: .5rem;
    font-weight: 700;
    color: var(--tx3);
    text-transform: uppercase;
    letter-spacing: .4px;
    margin-top: 2px;
    white-space: nowrap;
}

/* Material / View button */
.action-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 8px;
    font-family: var(--ff);
    font-size: .6875rem;
    font-weight: 700;
    background: var(--bg);
    border: 1px solid var(--bd);
    color: var(--tx2);
    text-decoration: none;
    transition: border-color .15s, color .15s, background .15s;
    white-space: nowrap;
    min-height: 30px;
}
.action-btn:hover { border-color: var(--ac); color: var(--dk); background: rgba(31,226,144,.03); }
.action-btn svg {
    width: 11px; height: 11px;
    stroke: currentColor; fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
    flex-shrink: 0;
}

/* ─────────────────────────────────────────────
   EMPTY STATE
───────────────────────────────────────────── */
.empty-state {
    background: var(--bg2);
    border: 1px solid var(--bd);
    border-radius: var(--r-lg);
    padding: 4rem 2rem;
    text-align: center;
}
.empty-state svg {
    width: 48px; height: 48px;
    stroke: var(--bd2); fill: none;
    stroke-width: 1.4; stroke-linecap: round; stroke-linejoin: round;
    margin: 0 auto 1.125rem; display: block;
}
.empty-state-title {
    font-size: .9375rem;
    font-weight: 800;
    color: var(--tx);
    letter-spacing: -.02em;
    margin-bottom: .5rem;
}
.empty-state-sub {
    font-size: .875rem;
    color: var(--tx3);
    line-height: 1.65;
    max-width: 360px;
    margin: 0 auto 1.5rem;
}
.btn-ac {
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
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: background .2s, transform .2s, box-shadow .2s;
    min-height: 44px;
}
.btn-ac:hover {
    background: var(--ac2);
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(31,226,144,.28);
}
.btn-ac svg {
    width: 15px; height: 15px;
    stroke: var(--dk); fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
    flex-shrink: 0;
}

/* ─────────────────────────────────────────────
   PAGINATION
───────────────────────────────────────────── */
.pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}
.page-btn {
    width: 38px; height: 38px;
    border-radius: 10px;
    border: 1.5px solid var(--bd);
    background: var(--bg2);
    display: flex; align-items: center; justify-content: center;
    font-family: var(--ff);
    font-size: .875rem;
    font-weight: 700;
    color: var(--tx2);
    text-decoration: none;
    transition: border-color .15s, color .15s, background .15s;
    flex-shrink: 0;
}
.page-btn:hover   { border-color: var(--ac); color: var(--dk); }
.page-btn.active  { background: var(--dk); border-color: var(--dk); color: var(--ac); }
.page-btn.disabled { opacity: .35; cursor: not-allowed; pointer-events: none; }
.page-btn svg {
    width: 14px; height: 14px;
    stroke: currentColor; fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
}

/* ─────────────────────────────────────────────
   BOTTOM CTA BANNER
───────────────────────────────────────────── */
.cta-banner {
    background: var(--dk);
    border-radius: var(--r-lg);
    padding: 1.5rem 1.75rem;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
}
.cta-banner-bg-grid {
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(31,226,144,.025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(31,226,144,.025) 1px, transparent 1px);
    background-size: 36px 36px;
    pointer-events: none;
}
.cta-banner-glow {
    position: absolute;
    top: -60px; right: -60px;
    width: 220px; height: 220px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(31,226,144,.07) 0%, transparent 65%);
    pointer-events: none;
}
.cta-banner-body { position: relative; z-index: 1; }
.cta-banner-title {
    font-size: 1rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: -.02em;
    margin-bottom: 3px;
}
.cta-banner-sub {
    font-size: .8125rem;
    color: rgba(255,255,255,.38);
}
.cta-banner-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 11px 22px;
    background: var(--ac);
    color: var(--dk);
    font-family: var(--ff);
    font-size: .875rem;
    font-weight: 800;
    border-radius: 11px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: background .2s, transform .2s, box-shadow .2s;
    position: relative;
    z-index: 1;
    white-space: nowrap;
    min-height: 44px;
    flex-shrink: 0;
}
.cta-banner-btn:hover {
    background: var(--ac2);
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(31,226,144,.28);
}
.cta-banner-btn svg {
    width: 14px; height: 14px;
    stroke: var(--dk); fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
    flex-shrink: 0;
}

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
    text-decoration: none;
    transition: color .2s;
    margin-bottom: 20px;
}
.back-link:hover { color: var(--dk); }
.back-link svg {
    width: 15px; height: 15px;
    stroke: currentColor; fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
}

/* ─────────────────────────────────────────────
   RESPONSIVE
   ─────────────────────────────────────────────
   ≥ 900   sidebar visible, full layout
   ≤ 900   sidebar off, full width
   ≤ 680   hide actions column on cards
   ≤ 600   smaller hero, tighter cards
   ≤ 480   tightest phones
───────────────────────────────────────────── */

@media (max-width: 900px) {
    .main-content { margin-left: 0; padding: 20px var(--pad-sm) 72px; }
}

@media (max-width: 680px) {
    .past-card { grid-template-columns: 60px 1fr; }
    .past-card-actions { display: none; }
    .past-card-date-day { font-size: 1.375rem; }
}

@media (max-width: 600px) {
    .hero         { padding: 1.375rem 1.125rem; border-radius: 16px; }
    .hero-title   { font-size: 1.25rem; }
    .hero-sub     { font-size: .875rem; }
    .hero-stat    { padding: 9px 14px; }
    .hero-stat-val { font-size: 1.0625rem; }

    .filter-bar   { flex-direction: column; align-items: flex-start; gap: 8px; }
    .subject-chip { padding: 5px 10px; font-size: .625rem; }

    .past-card-body  { padding: 12px 14px; }
    .past-card-title { font-size: .875rem; }
    .past-card-meta  { gap: 10px; }
    .past-card-meta-item { font-size: .6875rem; }

    .cta-banner { padding: 1.25rem; }
    .cta-banner-title { font-size: .9375rem; }

    .pagination .page-btn { width: 34px; height: 34px; font-size: .8125rem; }
}

@media (max-width: 480px) {
    .hero-stats { border-radius: 10px; }
    .hero-stat  { padding: 9px 12px; }
    .hero-stat-val { font-size: .9375rem; }

    .past-card { grid-template-columns: 52px 1fr; border-radius: var(--r); }
    .past-card-date { width: 52px; }
    .past-card-date-day { font-size: 1.25rem; }
    .past-card-body { padding: 10px 12px; gap: 5px; }
    .past-card-title { font-size: .8125rem; }

    .cta-banner { flex-direction: column; align-items: flex-start; }
    .cta-banner-btn { width: 100%; justify-content: center; }
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
         HERO
    ═══════════════════════════════════════ -->
    <div class="hero sr d1">
        <div class="hero-bg-grid"></div>
        <div class="hero-bg-glow"></div>
        <div class="hero-inner">
            <div class="hero-eyebrow">
                <span class="hero-eyebrow-dot"></span>
                Session History
            </div>
            <div class="hero-top">
                <div>
                    <div class="hero-title"><?= htmlspecialchars($firstName) ?>'s <em>Past Sessions</em></div>
                    <div class="hero-sub">Your complete history of attended group sessions</div>
                </div>
                <a href="/sessions/" class="hero-btn">
                    <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    Browse Upcoming
                </a>
            </div>
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="hero-stat-val ac"><?= $totalAttended ?></div>
                    <div class="hero-stat-key">Attended</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-val"><?= $totalHours ?><span style="font-size:.75rem;font-weight:700">h</span></div>
                    <div class="hero-stat-key">Study Time</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-val ac">
                        <?php if ($avgScoreBoost > 0): ?>+<?= $avgScoreBoost ?>
                        <?php elseif ($avgScoreBoost < 0): ?><?= $avgScoreBoost ?>
                        <?php else: ?>—<?php endif; ?>
                    </div>
                    <div class="hero-stat-key">Avg Boost</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-val"><?= $uniqueTutors ?></div>
                    <div class="hero-stat-key">Tutors</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-val ac"><?= $mathSessions ?></div>
                    <div class="hero-stat-key">Math</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-val"><?= $rwSessions ?></div>
                    <div class="hero-stat-key">R&amp;W</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════
         FILTER BAR
    ═══════════════════════════════════════ -->
    <div class="filter-bar sr d2">
        <div class="subject-chips">
            <a href="?<?= $page > 1 ? 'page='.$page : '' ?>"
               class="subject-chip <?= !$subject ? 'active' : '' ?>">
                All Subjects
            </a>
            <a href="?subject=math<?= $page > 1 ? '&page='.$page : '' ?>"
               class="subject-chip <?= $subject === 'math' ? 'active-math' : '' ?>">
                Math
            </a>
            <a href="?subject=reading_writing<?= $page > 1 ? '&page='.$page : '' ?>"
               class="subject-chip <?= $subject === 'reading_writing' ? 'active-rw' : '' ?>">
                Reading &amp; Writing
            </a>
        </div>
        <?php if ($total > 0): ?>
        <span class="filter-count">
            <?= $total ?> session<?= $total !== 1 ? 's' : '' ?><?= $subject ? ' · '.($subject === 'math' ? 'Math' : 'R&amp;W') : '' ?>
        </span>
        <?php endif; ?>
    </div>

    <!-- ══════════════════════════════════════
         SESSIONS LIST
    ═══════════════════════════════════════ -->
    <?php if (!empty($pastPage)): ?>
    <div class="sessions-list sr d3" id="sessionsList">
        <?php foreach ($pastPage as $sess):
            $ts    = strtotime($sess['starts_at'] ?? 'now');
            $boost = intval($sess['score_improvement'] ?? 0);
            $bCls  = $boost > 0 ? 'pos' : ($boost < 0 ? 'neg' : 'nil');
            $bVal  = $boost > 0 ? '+' . $boost : ($boost < 0 ? (string)$boost : '—');
            $subj  = $sess['subject'] ?? 'math';
            $badgeCls = $subj === 'reading_writing' ? 'badge-rw' : ($subj === 'mixed' ? 'badge-mixed' : 'badge-math');
            $badgeLbl = $subj === 'reading_writing' ? 'R&amp;W' : ($subj === 'mixed' ? 'Mixed' : 'Math');
            $hasMat   = !empty($sess['materials_url']) || !empty($sess['recap_url']);
            $matUrl   = $sess['materials_url'] ?? $sess['recap_url'] ?? '/sessions/'.intval($sess['id']).'/';
        ?>
        <a href="/sessions/<?= intval($sess['id']) ?>/" class="past-card">

            <div class="past-card-date">
                <div class="past-card-date-month"><?= date('M', $ts) ?></div>
                <div class="past-card-date-day"><?= date('j', $ts) ?></div>
                <div class="past-card-date-year"><?= date('Y', $ts) ?></div>
            </div>

            <div class="past-card-body">
                <div class="past-card-badges">
                    <span class="subj-badge <?= $badgeCls ?>"><?= $badgeLbl ?></span>
                    <span class="attended-pill">
                        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                        Attended
                    </span>
                </div>
                <div class="past-card-title"><?= htmlspecialchars($sess['title'] ?? 'Group Session') ?></div>
                <div class="past-card-meta">
                    <div class="past-card-meta-item">
                        <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <?= htmlspecialchars($sess['tutor_name'] ?? 'Tutor') ?>
                    </div>
                    <div class="past-card-meta-item">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <?= intval($sess['duration_min'] ?? 60) ?> min
                    </div>
                    <div class="past-card-meta-item">
                        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                        <?= intval($sess['enrolled_count'] ?? 0) ?> students
                    </div>
                </div>
            </div>

            <div class="past-card-actions">
                <?php if ($boost !== 0): ?>
                <div class="score-boost <?= $bCls ?>">
                    <div class="score-boost-val"><?= $bVal ?></div>
                    <div class="score-boost-key">Score boost</div>
                </div>
                <?php endif; ?>
                <a href="<?= htmlspecialchars($matUrl) ?>"
                   class="action-btn"
                   onclick="event.stopPropagation()">
                    <?php if ($hasMat): ?>
                    <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    Materials
                    <?php else: ?>
                    <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    View
                    <?php endif; ?>
                </a>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ── Pagination ── -->
    <?php if ($totalPgs > 1): ?>
    <div class="pagination sr d4">
        <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?><?= $subject ? '&subject='.$subject : '' ?>" class="page-btn" aria-label="Previous page">
            <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
        </a>
        <?php else: ?>
        <span class="page-btn disabled" aria-disabled="true">
            <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
        </span>
        <?php endif; ?>

        <?php
        /* Smart page range: show first, last, and window around current */
        for ($p = 1; $p <= $totalPgs; $p++):
            $show = ($p === 1 || $p === $totalPgs || abs($p - $page) <= 1);
            $prevShow = ($p > 1) && ($p - 1 === 1 || $p - 1 === $totalPgs || abs(($p - 1) - $page) <= 1);
            if (!$show) {
                if ($prevShow) echo '<span style="color:var(--tx3);font-size:.875rem;padding:0 4px;align-self:center">…</span>';
                continue;
            }
        ?>
        <a href="?page=<?= $p ?><?= $subject ? '&subject='.$subject : '' ?>"
           class="page-btn <?= $p === $page ? 'active' : '' ?>"
           <?= $p === $page ? 'aria-current="page"' : '' ?>>
            <?= $p ?>
        </a>
        <?php endfor; ?>

        <?php if ($page < $totalPgs): ?>
        <a href="?page=<?= $page + 1 ?><?= $subject ? '&subject='.$subject : '' ?>" class="page-btn" aria-label="Next page">
            <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
        <?php else: ?>
        <span class="page-btn disabled" aria-disabled="true">
            <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <!-- ── Empty state ── -->
    <div class="empty-state sr d3">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <div class="empty-state-title">
            <?php if ($subject): ?>
                No past <?= $subject === 'math' ? 'Math' : 'R&amp;W' ?> sessions yet
            <?php else: ?>
                No past sessions yet
            <?php endif; ?>
        </div>
        <div class="empty-state-sub">
            <?php if ($subject): ?>
                You haven't attended any <?= $subject === 'math' ? 'Math' : 'Reading &amp; Writing' ?> sessions yet.
            <?php else: ?>
                Once you attend a group session it will appear here along with session materials and your score improvements.
            <?php endif; ?>
        </div>
        <a href="/sessions/" class="btn-ac">
            <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            Browse Sessions
        </a>
    </div>
    <?php endif; ?>

    <!-- ══════════════════════════════════════
         BOTTOM CTA BANNER
    ═══════════════════════════════════════ -->
    <?php if ($totalAttended > 0): ?>
    <div class="cta-banner sr d5">
        <div class="cta-banner-bg-grid"></div>
        <div class="cta-banner-glow"></div>
        <div class="cta-banner-body">
            <div class="cta-banner-title">Keep the momentum going!</div>
            <div class="cta-banner-sub">Enroll in more group sessions to continue boosting your score.</div>
        </div>
        <a href="/sessions/" class="cta-banner-btn">
            <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            Browse Sessions
        </a>
    </div>
    <?php endif; ?>

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

    /* ── Staggered card entrance ── */
    var listObs = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (!e.isIntersecting) return;
            var cards = e.target.querySelectorAll('.past-card');
            cards.forEach(function (c, i) {
                c.style.opacity   = '0';
                c.style.transform = 'translateX(-10px)';
                setTimeout(function () {
                    c.style.transition = 'opacity .38s cubic-bezier(.16,1,.3,1), transform .38s cubic-bezier(.16,1,.3,1), box-shadow .28s, border-color .2s';
                    c.style.opacity    = '1';
                    c.style.transform  = 'none';
                }, i * 40);
            });
            listObs.unobserve(e.target);
        });
    }, { threshold: .04 });
    document.querySelectorAll('.sessions-list').forEach(function (g) { listObs.observe(g); });

}());
</script>
</body>
</html>