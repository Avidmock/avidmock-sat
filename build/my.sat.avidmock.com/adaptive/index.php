<?php
/**
 * /adaptive/index.php — AI-Powered Adaptive Practice
 * my.sat.avidmock.com/adaptive/
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/StudyStreak.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/AdaptiveEngine.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/ScorePredictor.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/ScorePredictor2.php';

Auth::requireStudent();
$userId    = (int) $_SESSION['user_id'];
$user      = User::findById($userId);
$firstName = explode(' ', trim($user['name'] ?? $user['first_name'] ?? 'Student'))[0];
$targetScore = (int) ($user['target_score'] ?? $_SESSION['target_score'] ?? 1200);

/* ── Streak ── */
$streakData    = StudyStreak::get($userId) ?? [];
$currentStreak = intval($streakData['current_streak'] ?? 0);

/* ── Adaptive profile ── */
$profile    = AdaptiveEngine::getStudentProfile($userId);
$domains    = AdaptiveEngine::getDomains();
$prediction = ScorePredictor2::predict($userId);
$history    = ScorePredictor2::getScoreHistory($userId, 56); // 8 weeks
$weeklyData = $history['weekly_estimates'] ?? [];

/* ── Page vars ── */
$activePage  = 'adaptive';
$topbarTitle = 'Adaptive Practice';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Adaptive Practice — Avidmock SAT</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<link rel="icon" type="image/png" sizes="16x16"   href="/favicon-16x16.png">
<link rel="icon" type="image/png" sizes="32x32"   href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="192x192" href="/android-chrome-192x192.png">
<link rel="icon" type="image/png" sizes="512x512" href="/android-chrome-512x512.png">
<link rel="apple-touch-icon" sizes="180x180"      href="/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800;1,9..40,500&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css">
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/contrib/auto-render.min.js"></script>
<style>
/* ─────────────────────────────────────────────
   TOKENS
───────────────────────────────────────────── */
:root {
    --dk: #143230;   --dk2: #1a3f3c;
    --ac: #1fe290;   --ac2: #17c87a;   --ac3: #12a562;
    --tx: #1a1a2e;   --tx2: #4a4a5a;   --tx3: #8a8a9a;
    --bg: #f7faf9;   --bg2: #ffffff;   --bd: #e2ebe9;  --bd2: #d1dcd9;
    --warn: #f59e0b; --err: #ef4444;   --ok: #10b981;
    --blue: #3b82f6; --purple: #8b5cf6; --pink: #ec4899;
    --ff: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    --mono: 'DM Mono', 'SF Mono', monospace;
    --sidebar-w: 260px;
    --topbar-h: 64px;
    --r: 14px;
    --r-sm: 10px;
    --r-lg: 18px;
    --r-xl: 22px;
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

/* ── Scroll Reveal ── */
.sr { opacity: 0; transform: translateY(18px); transition: opacity .5s cubic-bezier(.16,1,.3,1), transform .5s cubic-bezier(.16,1,.3,1); }
.sr.v { opacity: 1; transform: none; }
.d1 { transition-delay: .06s; }
.d2 { transition-delay: .12s; }
.d3 { transition-delay: .18s; }
.d4 { transition-delay: .24s; }
.d5 { transition-delay: .30s; }

/* ── Layout ── */
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

@media (max-width: 900px) {
    .main-content { margin-left: 0; padding: 24px var(--pad-sm) 80px; }
    .sidebar-overlay { display: block; }
}

/* ── Page Header ── */
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
.page-title em { font-style: normal; color: var(--ac2); }
.page-subtitle { font-size: .875rem; color: var(--tx3); margin-top: 3px; }

/* ─────────────────────────────────────────────
   HERO BANNER
───────────────────────────────────────────── */
.hero-banner {
    position: relative;
    background: linear-gradient(135deg, var(--dk) 0%, var(--dk2) 40%, #0d2826 100%);
    border-radius: var(--r-xl);
    padding: 36px 32px;
    color: #fff;
    overflow: hidden;
    margin-bottom: 28px;
}
.hero-banner::before {
    content: '';
    position: absolute;
    top: -40%;
    right: -10%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(31,226,144,.15) 0%, transparent 70%);
    pointer-events: none;
}
.hero-banner::after {
    content: '';
    position: absolute;
    bottom: -30%;
    left: 20%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(23,200,122,.08) 0%, transparent 70%);
    pointer-events: none;
}
.hero-inner { position: relative; z-index: 1; }
.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    background: rgba(31,226,144,.12);
    border: 1px solid rgba(31,226,144,.25);
    border-radius: 20px;
    font-size: .75rem;
    font-weight: 700;
    color: var(--ac);
    text-transform: uppercase;
    letter-spacing: .06em;
    margin-bottom: 14px;
}
.hero-badge svg { width: 14px; height: 14px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.hero-h1 {
    font-size: clamp(1.5rem, 4vw, 2rem);
    font-weight: 800;
    letter-spacing: -.03em;
    line-height: 1.2;
    margin-bottom: 8px;
}
.hero-h1 span { color: var(--ac); }
.hero-desc {
    font-size: .9375rem;
    color: rgba(255,255,255,.65);
    max-width: 540px;
    line-height: 1.6;
    margin-bottom: 20px;
}
.hero-actions { display: flex; gap: 12px; flex-wrap: wrap; }
.btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: var(--ac);
    color: var(--dk);
    font-size: .875rem;
    font-weight: 700;
    border-radius: var(--r);
    transition: background .2s, transform .15s;
    white-space: nowrap;
}
.btn-primary:hover { background: var(--ac2); transform: translateY(-1px); }
.btn-primary svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }
.btn-outline {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: rgba(255,255,255,.06);
    border: 1.5px solid rgba(255,255,255,.15);
    color: rgba(255,255,255,.85);
    font-size: .875rem;
    font-weight: 600;
    border-radius: var(--r);
    transition: border-color .2s, color .2s, background .2s;
    white-space: nowrap;
}
.btn-outline:hover { border-color: var(--ac); color: var(--ac); background: rgba(31,226,144,.06); }
.btn-outline svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

/* ─────────────────────────────────────────────
   GRID LAYOUT
───────────────────────────────────────────── */
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 28px; }
.grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 28px; }
.grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
.span-2 { grid-column: span 2; }

@media (max-width: 1100px) {
    .grid-4 { grid-template-columns: repeat(2, 1fr); }
    .grid-3 { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 700px) {
    .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; }
    .span-2 { grid-column: span 1; }
}

/* ─────────────────────────────────────────────
   CARDS
───────────────────────────────────────────── */
.card {
    background: var(--bg2);
    border: 1px solid var(--bd);
    border-radius: var(--r-lg);
    padding: 24px;
    transition: border-color .2s, box-shadow .2s;
}
.card:hover { border-color: var(--bd2); box-shadow: 0 2px 12px rgba(20,50,48,.04); }
.card-label {
    font-size: .6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--tx3);
    margin-bottom: 14px;
}
.section-title {
    font-size: 1.125rem;
    font-weight: 800;
    color: var(--tx);
    letter-spacing: -.02em;
    margin-bottom: 16px;
}
.section-title em { font-style: normal; color: var(--ac2); }

/* ─────────────────────────────────────────────
   DOMAIN GAUGES
───────────────────────────────────────────── */
.gauge-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 24px 16px;
}
.gauge-wrap {
    position: relative;
    width: 110px;
    height: 110px;
    margin-bottom: 14px;
}
.gauge-wrap svg { width: 110px; height: 110px; transform: rotate(-90deg); }
.gauge-track { fill: none; stroke: var(--bd); stroke-width: 8; }
.gauge-fill {
    fill: none;
    stroke-width: 8;
    stroke-linecap: round;
    transition: stroke-dashoffset 1.2s cubic-bezier(.16,1,.3,1), stroke .4s;
}
.gauge-center {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
}
.gauge-value {
    font-family: var(--mono);
    font-size: 1.375rem;
    font-weight: 700;
    color: var(--tx);
    line-height: 1;
}
.gauge-unit {
    font-size: .625rem;
    font-weight: 600;
    color: var(--tx3);
    text-transform: uppercase;
    letter-spacing: .06em;
    margin-top: 2px;
}
.gauge-label {
    font-size: .8125rem;
    font-weight: 700;
    color: var(--tx);
    margin-bottom: 4px;
}
.gauge-sub {
    font-size: .75rem;
    color: var(--tx3);
}
.gauge-level {
    display: inline-flex;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: .6875rem;
    font-weight: 700;
    margin-top: 8px;
}
.level-beginner   { background: #fef3c7; color: #92400e; }
.level-developing { background: #fed7aa; color: #9a3412; }
.level-proficient { background: #d1fae5; color: #065f46; }
.level-advanced   { background: #c7d2fe; color: #3730a3; }
.level-expert     { background: #e0e7ff; color: #312e81; }

/* ─────────────────────────────────────────────
   PREDICTION CARD
───────────────────────────────────────────── */
.pred-card {
    background: linear-gradient(135deg, var(--dk) 0%, var(--dk2) 100%);
    border: none;
    color: #fff;
    position: relative;
    overflow: hidden;
}
.pred-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 250px;
    height: 250px;
    background: radial-gradient(circle, rgba(31,226,144,.1) 0%, transparent 70%);
    pointer-events: none;
}
.pred-inner { position: relative; z-index: 1; }
.pred-score {
    font-family: var(--mono);
    font-size: 3rem;
    font-weight: 700;
    line-height: 1;
    color: var(--ac);
    margin-bottom: 4px;
}
.pred-range {
    font-size: .8125rem;
    color: rgba(255,255,255,.5);
    margin-bottom: 16px;
    font-family: var(--mono);
}
.pred-target {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    background: rgba(255,255,255,.06);
    border-radius: var(--r-sm);
    margin-bottom: 14px;
}
.pred-target-pct {
    font-family: var(--mono);
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--ac);
}
.pred-target-label {
    font-size: .8125rem;
    color: rgba(255,255,255,.65);
    line-height: 1.3;
}
.pred-days {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    background: rgba(255,255,255,.04);
    border-radius: var(--r-sm);
    margin-bottom: 14px;
}
.pred-days-num {
    font-family: var(--mono);
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--warn);
}
.pred-days-label {
    font-size: .8125rem;
    color: rgba(255,255,255,.55);
}
.pred-percentile {
    font-size: .8125rem;
    color: rgba(255,255,255,.5);
    margin-top: 10px;
}
.pred-percentile strong { color: var(--ac); }

/* ── Trend Mini Chart ── */
.trend-chart {
    display: flex;
    align-items: flex-end;
    gap: 6px;
    height: 60px;
    margin: 16px 0 8px;
}
.trend-bar {
    flex: 1;
    min-width: 0;
    border-radius: 4px 4px 0 0;
    background: rgba(31,226,144,.3);
    transition: height .6s cubic-bezier(.16,1,.3,1), background .3s;
    position: relative;
}
.trend-bar:last-child { background: var(--ac); }
.trend-bar:hover { background: var(--ac); }
.trend-labels {
    display: flex;
    justify-content: space-between;
    font-size: .625rem;
    color: rgba(255,255,255,.35);
    font-family: var(--mono);
}

/* ─────────────────────────────────────────────
   GENERATE QUIZ SECTION
───────────────────────────────────────────── */
.gen-card { padding: 28px; }
.gen-form { display: flex; gap: 14px; align-items: flex-end; flex-wrap: wrap; }
.gen-field { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 140px; }
.gen-label {
    font-size: .75rem;
    font-weight: 700;
    color: var(--tx2);
    text-transform: uppercase;
    letter-spacing: .06em;
}
.gen-select {
    appearance: none;
    padding: 10px 36px 10px 14px;
    border: 1.5px solid var(--bd);
    border-radius: var(--r-sm);
    background: var(--bg2) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%238a8a9a' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E") no-repeat right 12px center;
    font-family: var(--ff);
    font-size: .875rem;
    font-weight: 500;
    color: var(--tx);
    cursor: pointer;
    transition: border-color .2s;
}
.gen-select:hover { border-color: var(--ac); }
.gen-select:focus { outline: none; border-color: var(--ac); box-shadow: 0 0 0 3px rgba(31,226,144,.12); }
.gen-btn {
    padding: 10px 22px;
    background: var(--ac);
    color: var(--dk);
    font-weight: 700;
    font-size: .875rem;
    border-radius: var(--r-sm);
    transition: background .2s;
    white-space: nowrap;
    min-height: 42px;
}
.gen-btn:hover { background: var(--ac2); }

/* ─────────────────────────────────────────────
   SESSION OVERLAY (practice modal)
───────────────────────────────────────────── */
.session-overlay {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 1000;
    background: var(--bg);
    overflow-y: auto;
}
.session-overlay.active { display: block; }

.session-layout {
    display: grid;
    grid-template-columns: 1fr 300px;
    max-width: 1100px;
    margin: 0 auto;
    min-height: 100vh;
    gap: 0;
}
@media (max-width: 900px) {
    .session-layout { grid-template-columns: 1fr; }
    .session-sidebar { display: none; }
    .session-sidebar.mobile-show { display: block; position: fixed; right: 0; top: 0; bottom: 0; z-index: 10; width: 300px; box-shadow: -4px 0 24px rgba(0,0,0,.1); }
}

/* ── Session Header ── */
.session-header {
    position: sticky;
    top: 0;
    z-index: 5;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 24px;
    background: var(--bg2);
    border-bottom: 1px solid var(--bd);
}
.session-back {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    background: transparent;
    border: 1.5px solid var(--bd);
    border-radius: var(--r-sm);
    font-size: .8125rem;
    font-weight: 600;
    color: var(--tx2);
    transition: border-color .2s, color .2s;
}
.session-back:hover { border-color: var(--err); color: var(--err); }
.session-back svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.session-meta { display: flex; align-items: center; gap: 20px; }
.session-counter {
    font-family: var(--mono);
    font-size: .8125rem;
    font-weight: 600;
    color: var(--tx2);
}
.session-timer {
    font-family: var(--mono);
    font-size: .875rem;
    font-weight: 600;
    color: var(--tx3);
    display: flex;
    align-items: center;
    gap: 6px;
}
.session-timer svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.session-stats-toggle {
    display: none;
    padding: 8px;
    border: 1.5px solid var(--bd);
    border-radius: var(--r-sm);
    color: var(--tx2);
}
.session-stats-toggle svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
@media (max-width: 900px) { .session-stats-toggle { display: flex; } }

/* ── Question Card ── */
.session-main { padding: 32px 24px 80px; }
.question-card {
    background: var(--bg2);
    border: 1.5px solid var(--bd);
    border-radius: var(--r-lg);
    padding: 32px;
    max-width: 700px;
    margin: 0 auto;
    transition: border-color .3s;
}
.question-card.correct-flash { border-color: var(--ok); box-shadow: 0 0 0 3px rgba(16,185,129,.12); }
.question-card.wrong-flash   { border-color: var(--err); box-shadow: 0 0 0 3px rgba(239,68,68,.12); }

.q-domain-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: .6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    margin-bottom: 16px;
    background: rgba(31,226,144,.08);
    color: var(--ac3);
}
.q-difficulty {
    display: inline-flex;
    padding: 3px 8px;
    border-radius: 8px;
    font-size: .625rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    margin-left: 8px;
}
.diff-easy   { background: #d1fae5; color: #065f46; }
.diff-medium { background: #fef3c7; color: #92400e; }
.diff-hard   { background: #fecaca; color: #991b1b; }

.q-stem {
    font-size: 1.0625rem;
    line-height: 1.7;
    color: var(--tx);
    margin-bottom: 24px;
}
.q-stem .katex { font-size: 1em; }

/* ── Choices ── */
.choices { display: flex; flex-direction: column; gap: 10px; }
.choice-btn {
    display: flex;
    align-items: center;
    gap: 14px;
    width: 100%;
    padding: 14px 18px;
    background: var(--bg);
    border: 1.5px solid var(--bd);
    border-radius: var(--r);
    font-size: .9375rem;
    font-weight: 500;
    color: var(--tx);
    text-align: left;
    transition: border-color .18s, background .18s, transform .12s;
}
.choice-btn:hover:not(.disabled) {
    border-color: var(--ac);
    background: rgba(31,226,144,.03);
    transform: translateX(3px);
}
.choice-btn.selected {
    border-color: var(--ac);
    background: rgba(31,226,144,.06);
}
.choice-btn.correct {
    border-color: var(--ok) !important;
    background: rgba(16,185,129,.08) !important;
}
.choice-btn.incorrect {
    border-color: var(--err) !important;
    background: rgba(239,68,68,.06) !important;
}
.choice-btn.disabled { pointer-events: none; opacity: .85; }
.choice-letter {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: var(--bg2);
    border: 1.5px solid var(--bd);
    font-family: var(--mono);
    font-size: .8125rem;
    font-weight: 700;
    color: var(--tx2);
    flex-shrink: 0;
    transition: background .18s, border-color .18s, color .18s;
}
.choice-btn.selected .choice-letter { background: var(--ac); border-color: var(--ac); color: var(--dk); }
.choice-btn.correct .choice-letter   { background: var(--ok); border-color: var(--ok); color: #fff; }
.choice-btn.incorrect .choice-letter { background: var(--err); border-color: var(--err); color: #fff; }
.choice-text { flex: 1; line-height: 1.5; }

/* ── Feedback ── */
.feedback-area {
    display: none;
    margin-top: 20px;
    padding: 20px;
    border-radius: var(--r);
    animation: fadeSlideUp .35s cubic-bezier(.16,1,.3,1);
}
@keyframes fadeSlideUp {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: none; }
}
.feedback-area.show { display: block; }
.feedback-correct { background: rgba(16,185,129,.06); border: 1px solid rgba(16,185,129,.2); }
.feedback-wrong   { background: rgba(239,68,68,.05);  border: 1px solid rgba(239,68,68,.15); }
.feedback-header {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: .9375rem;
    font-weight: 700;
    margin-bottom: 10px;
}
.feedback-correct .feedback-header { color: var(--ok); }
.feedback-wrong   .feedback-header { color: var(--err); }
.feedback-header svg { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }
.feedback-body {
    font-size: .875rem;
    line-height: 1.7;
    color: var(--tx2);
}
.feedback-flag {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-top: 10px;
    padding: 5px 10px;
    border-radius: 8px;
    font-size: .75rem;
    font-weight: 600;
    background: rgba(245,158,11,.08);
    color: var(--warn);
}

/* ── Ability Update Animation ── */
.ability-update {
    display: none;
    margin-top: 16px;
    padding: 14px 18px;
    background: var(--bg);
    border-radius: var(--r);
    animation: fadeSlideUp .4s cubic-bezier(.16,1,.3,1) .15s both;
}
.ability-update.show { display: block; }
.ability-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.ability-label {
    font-size: .75rem;
    font-weight: 600;
    color: var(--tx3);
    text-transform: uppercase;
    letter-spacing: .06em;
}
.ability-bar-wrap {
    flex: 1;
    height: 8px;
    background: var(--bd);
    border-radius: 4px;
    overflow: hidden;
    position: relative;
}
.ability-bar {
    height: 100%;
    border-radius: 4px;
    background: var(--ac);
    transition: width 1s cubic-bezier(.16,1,.3,1);
}
.ability-delta {
    font-family: var(--mono);
    font-size: .8125rem;
    font-weight: 700;
    min-width: 50px;
    text-align: right;
}
.ability-delta.positive { color: var(--ok); }
.ability-delta.negative { color: var(--err); }

/* ── Next Button ── */
.next-btn-wrap {
    display: none;
    margin-top: 20px;
    text-align: center;
}
.next-btn-wrap.show { display: block; }
.next-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 13px 28px;
    background: var(--ac);
    color: var(--dk);
    font-size: .9375rem;
    font-weight: 700;
    border-radius: var(--r);
    transition: background .2s, transform .15s;
}
.next-btn:hover { background: var(--ac2); transform: translateY(-1px); }
.next-btn svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }

/* ── Session Sidebar ── */
.session-sidebar {
    border-left: 1px solid var(--bd);
    background: var(--bg2);
    padding: 24px 20px;
    overflow-y: auto;
    height: 100vh;
    position: sticky;
    top: 0;
}
.ss-section { margin-bottom: 24px; }
.ss-label {
    font-size: .6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--tx3);
    margin-bottom: 10px;
}
.ss-stat {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid var(--bd);
    font-size: .8125rem;
}
.ss-stat:last-child { border-bottom: none; }
.ss-stat-label { color: var(--tx2); font-weight: 500; }
.ss-stat-value { font-family: var(--mono); font-weight: 700; color: var(--tx); }
.ss-stat-value.green { color: var(--ok); }
.ss-stat-value.red { color: var(--err); }

.ss-mini-chart {
    display: flex;
    align-items: flex-end;
    gap: 3px;
    height: 40px;
    margin-top: 8px;
}
.ss-mini-bar {
    flex: 1;
    border-radius: 2px;
    min-height: 3px;
    transition: height .4s cubic-bezier(.16,1,.3,1);
}
.ss-mini-bar.green { background: var(--ok); }
.ss-mini-bar.red { background: var(--err); }

/* ── Session Close Mobile ── */
.ss-close-mobile {
    display: none;
    position: absolute;
    top: 12px;
    right: 12px;
    width: 32px;
    height: 32px;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    border: 1px solid var(--bd);
    background: var(--bg2);
    color: var(--tx2);
}
.ss-close-mobile svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
@media (max-width: 900px) {
    .ss-close-mobile { display: flex; }
    .session-sidebar.mobile-show { position: relative; }
}

/* ─────────────────────────────────────────────
   RECOMMENDATION CARDS
───────────────────────────────────────────── */
.rec-list { display: flex; flex-direction: column; gap: 10px; }
.rec-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 16px;
    background: var(--bg);
    border-radius: var(--r);
    transition: background .2s;
}
.rec-item:hover { background: rgba(31,226,144,.03); }
.rec-priority {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    flex-shrink: 0;
    font-size: .75rem;
    font-weight: 800;
}
.rec-priority.high   { background: rgba(239,68,68,.08); color: var(--err); }
.rec-priority.medium { background: rgba(245,158,11,.08); color: var(--warn); }
.rec-priority.low    { background: rgba(59,130,246,.08); color: var(--blue); }
.rec-body { flex: 1; min-width: 0; }
.rec-action { font-size: .875rem; font-weight: 700; color: var(--tx); margin-bottom: 3px; }
.rec-desc { font-size: .8125rem; color: var(--tx3); line-height: 1.5; }
.rec-impact {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-top: 6px;
    padding: 3px 8px;
    border-radius: 6px;
    background: rgba(31,226,144,.06);
    font-size: .6875rem;
    font-weight: 700;
    color: var(--ac3);
}

/* ─────────────────────────────────────────────
   LOADING / SKELETON
───────────────────────────────────────────── */
.skeleton {
    position: relative;
    overflow: hidden;
    background: var(--bd) !important;
    color: transparent !important;
    border-radius: 6px;
}
.skeleton::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,.5) 50%, transparent 100%);
    animation: shimmer 1.5s infinite;
}
@keyframes shimmer { from { transform: translateX(-100%); } to { transform: translateX(100%); } }

.loading-spinner {
    display: inline-block;
    width: 24px;
    height: 24px;
    border: 3px solid var(--bd);
    border-top-color: var(--ac);
    border-radius: 50%;
    animation: spin .7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ─────────────────────────────────────────────
   MISC
───────────────────────────────────────────── */
.katex-display { margin: 12px 0; }
.hidden { display: none !important; }
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/sidebar.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/topbar.php';
?>

<main class="main-content" id="mainContent">

    <!-- ══════════════════════════════════════════
         PAGE HEADER
    ══════════════════════════════════════════ -->
    <div class="page-header sr">
        <div>
            <h1 class="page-title">Adaptive <em>Practice</em></h1>
            <p class="page-subtitle">AI-powered questions that adapt to your level in real time</p>
        </div>
    </div>

    <!-- ══════════════════════════════════════════
         HERO BANNER
    ══════════════════════════════════════════ -->
    <div class="hero-banner sr d1">
        <div class="hero-inner">
            <div class="hero-badge">
                <svg viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                AI-Powered Engine
            </div>
            <h2 class="hero-h1">Questions <span>Adapt to You</span></h2>
            <p class="hero-desc">Our IRT-based engine selects the perfect next question for your level — not too easy, not too hard. Every answer makes the algorithm smarter about where you need to grow.</p>
            <div class="hero-actions">
                <button class="btn-primary" id="startSessionBtn" onclick="startAdaptiveSession()">
                    <svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    Start Adaptive Session
                </button>
                <a href="#generateSection" class="btn-outline">
                    <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    Generate Custom Quiz
                </a>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════
         DOMAIN ABILITY GAUGES
    ══════════════════════════════════════════ -->
    <h3 class="section-title sr d2">Your <em>Ability Level</em> by Domain</h3>
    <div class="grid-4 sr d2">
        <?php
        $gaugeColors = ['#1fe290', '#3b82f6', '#f59e0b', '#8b5cf6'];
        $i = 0;
        foreach ($domains as $slug => $label):
            $dp = $profile['domains'][$slug] ?? ['ability' => 0, 'questions_answered' => 0, 'accuracy' => 0, 'level_label' => 'Beginner', 'ability_pct' => 50];
            $pct = max(0, min(100, (int) $dp['ability_pct']));
            $circumference = 2 * M_PI * 44;
            $offset = $circumference * (1 - $pct / 100);
            $color = $gaugeColors[$i % 4];
            $levelClass = 'level-' . strtolower($dp['level_label']);
        ?>
        <div class="card gauge-card">
            <div class="gauge-wrap">
                <svg viewBox="0 0 110 110">
                    <circle class="gauge-track" cx="55" cy="55" r="44"/>
                    <circle class="gauge-fill" cx="55" cy="55" r="44"
                            stroke="<?= $color ?>"
                            stroke-dasharray="<?= round($circumference, 2) ?>"
                            stroke-dashoffset="<?= round($circumference, 2) ?>"
                            data-target="<?= round($offset, 2) ?>"/>
                </svg>
                <div class="gauge-center">
                    <div class="gauge-value" data-target="<?= $pct ?>">0</div>
                    <div class="gauge-unit">pctl</div>
                </div>
            </div>
            <div class="gauge-label"><?= e($label) ?></div>
            <div class="gauge-sub"><?= $dp['questions_answered'] ?> Qs &middot; <?= $dp['accuracy'] ?>% acc</div>
            <span class="gauge-level <?= $levelClass ?>"><?= e($dp['level_label']) ?></span>
        </div>
        <?php $i++; endforeach; ?>
    </div>

    <!-- ══════════════════════════════════════════
         PREDICTION + RECOMMENDATIONS ROW
    ══════════════════════════════════════════ -->
    <div class="grid-2 sr d3">
        <!-- Score Prediction Card -->
        <div class="card pred-card">
            <div class="pred-inner">
                <div class="card-label" style="color:rgba(255,255,255,.4)">Predicted SAT Score</div>
                <div class="pred-score" id="predScore"><?= $prediction['predicted_score'] ?></div>
                <div class="pred-range"><?= $prediction['confidence_low'] ?> &ndash; <?= $prediction['confidence_high'] ?> (80% CI)</div>

                <?php if ($targetScore > 0): ?>
                <div class="pred-target">
                    <div class="pred-target-pct"><?= round($prediction['probability_above_target'] * 100) ?>%</div>
                    <div class="pred-target-label">chance of hitting your target<br><strong style="color:#fff"><?= $targetScore ?></strong></div>
                </div>
                <?php endif; ?>

                <?php if ($prediction['days_to_target'] !== null && $prediction['days_to_target'] > 0): ?>
                <div class="pred-days">
                    <div class="pred-days-num"><?= $prediction['days_to_target'] ?></div>
                    <div class="pred-days-label">estimated days to reach your target</div>
                </div>
                <?php endif; ?>

                <!-- Trend mini chart -->
                <?php if (!empty($weeklyData)): ?>
                <div class="trend-chart" id="trendChart">
                    <?php
                    $scores = array_column($weeklyData, 'score');
                    $maxS = max($scores ?: [1000]);
                    $minS = min($scores ?: [800]);
                    $range = max(1, $maxS - $minS);
                    $last8 = array_slice($weeklyData, -8);
                    foreach ($last8 as $w):
                        $h = max(8, (($w['score'] - $minS) / $range) * 52 + 8);
                    ?>
                    <div class="trend-bar" style="height: <?= round($h) ?>px" title="<?= $w['week_label'] ?>: <?= $w['score'] ?>"></div>
                    <?php endforeach; ?>
                </div>
                <div class="trend-labels">
                    <span><?= $last8[0]['week_label'] ?? '' ?></span>
                    <span><?= end($last8)['week_label'] ?? 'Now' ?></span>
                </div>
                <?php endif; ?>

                <div class="pred-percentile">
                    Higher than <strong><?= $prediction['percentile'] ?>%</strong> of SAT takers
                </div>
            </div>
        </div>

        <!-- Recommendations Card -->
        <div class="card">
            <div class="card-label">Top Recommendations</div>
            <div class="rec-list">
                <?php
                $recs = $prediction['recommendations'] ?? [];
                if (empty($recs)):
                ?>
                <div style="text-align:center; padding:20px; color:var(--tx3); font-size:.875rem;">
                    Answer more questions to get personalized recommendations.
                </div>
                <?php else: foreach ($recs as $rec): ?>
                <div class="rec-item">
                    <div class="rec-priority <?= $rec['priority'] ?>">
                        <?= $rec['priority'] === 'high' ? '!' : ($rec['priority'] === 'medium' ? '~' : '-') ?>
                    </div>
                    <div class="rec-body">
                        <div class="rec-action"><?= e($rec['action']) ?></div>
                        <div class="rec-desc"><?= e($rec['description']) ?></div>
                        <?php if ($rec['impact_points'] > 0): ?>
                        <span class="rec-impact">+<?= $rec['impact_points'] ?> pts potential</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════
         GENERATE CUSTOM QUIZ
    ══════════════════════════════════════════ -->
    <div class="card gen-card sr d4" id="generateSection">
        <h3 class="section-title">Generate <em>Custom Quiz</em></h3>
        <p style="font-size:.875rem; color:var(--tx3); margin-bottom:18px;">
            Difficulty automatically adapts to your ability level. Choose a domain and length.
        </p>
        <div class="gen-form">
            <div class="gen-field">
                <label class="gen-label">Domain</label>
                <select class="gen-select" id="genDomain">
                    <option value="all">All Domains</option>
                    <?php foreach ($domains as $slug => $label): ?>
                    <option value="<?= $slug ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="gen-field">
                <label class="gen-label">Length</label>
                <select class="gen-select" id="genCount">
                    <option value="5">5 Questions</option>
                    <option value="10" selected>10 Questions</option>
                    <option value="15">15 Questions</option>
                    <option value="20">20 Questions</option>
                </select>
            </div>
            <button class="gen-btn" id="genBtn" onclick="generateQuiz()">
                Generate Quiz
            </button>
        </div>
    </div>

</main>

<!-- ══════════════════════════════════════════════
     SESSION OVERLAY (full-screen practice mode)
══════════════════════════════════════════════ -->
<div class="session-overlay" id="sessionOverlay">
    <div class="session-header">
        <button class="session-back" onclick="endSession()">
            <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
            End Session
        </button>
        <div class="session-meta">
            <span class="session-counter" id="sessionCounter">Q 0</span>
            <span class="session-timer" id="sessionTimer">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span id="timerDisplay">0:00</span>
            </span>
            <button class="session-stats-toggle" id="statsToggle" onclick="toggleMobileSidebar()">
                <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            </button>
        </div>
    </div>

    <div class="session-layout">
        <div class="session-main" id="sessionMain">
            <!-- Question Card (populated by JS) -->
            <div class="question-card" id="questionCard">
                <div style="text-align:center; padding:40px;">
                    <div class="loading-spinner"></div>
                    <p style="margin-top:14px; color:var(--tx3); font-size:.875rem;">Loading your first question...</p>
                </div>
            </div>
        </div>

        <div class="session-sidebar" id="sessionSidebar">
            <button class="ss-close-mobile" onclick="toggleMobileSidebar()">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>

            <div class="ss-section">
                <div class="ss-label">Session Stats</div>
                <div class="ss-stat">
                    <span class="ss-stat-label">Answered</span>
                    <span class="ss-stat-value" id="ssAnswered">0</span>
                </div>
                <div class="ss-stat">
                    <span class="ss-stat-label">Correct</span>
                    <span class="ss-stat-value green" id="ssCorrect">0</span>
                </div>
                <div class="ss-stat">
                    <span class="ss-stat-label">Accuracy</span>
                    <span class="ss-stat-value" id="ssAccuracy">—</span>
                </div>
                <div class="ss-stat">
                    <span class="ss-stat-label">Avg Time</span>
                    <span class="ss-stat-value" id="ssAvgTime">—</span>
                </div>
                <div class="ss-stat">
                    <span class="ss-stat-label">Streak</span>
                    <span class="ss-stat-value" id="ssStreak">0</span>
                </div>
            </div>

            <div class="ss-section">
                <div class="ss-label">Ability Trend</div>
                <div class="ss-mini-chart" id="ssMiniChart"></div>
            </div>

            <div class="ss-section">
                <div class="ss-label">Current Domain</div>
                <div id="ssDomain" style="font-size:.875rem; font-weight:600; color:var(--tx);">—</div>
            </div>

            <div class="ss-section">
                <div class="ss-label">Session Difficulty</div>
                <div id="ssDifficulty" style="font-size:.875rem; color:var(--tx2);">Calibrating...</div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
'use strict';

/* ══════════════════════════════════════════════
   STATE
══════════════════════════════════════════════ */
var session = {
    active: false,
    domain: null,
    currentQuestion: null,
    answered: 0,
    correct: 0,
    totalTime: 0,
    streak: 0,
    startedAt: null,
    questionStartTime: null,
    timerInterval: null,
    abilityTrend: [],
    quizMode: false,
    quizQuestions: [],
    quizIndex: 0,
};

var API_BASE = '/api/adaptive-quiz.php';
var PREDICT_API = '/api/predict-score.php';

/* ══════════════════════════════════════════════
   SCROLL REVEAL
══════════════════════════════════════════════ */
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

/* ── Ctrl/Cmd+K ── */
document.addEventListener('keydown', function (e) {
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        if (window.tbOpenSearch) tbOpenSearch();
    }
});

/* ══════════════════════════════════════════════
   GAUGE ANIMATIONS (on page load)
══════════════════════════════════════════════ */
setTimeout(function () {
    document.querySelectorAll('.gauge-fill').forEach(function (el) {
        var target = parseFloat(el.getAttribute('data-target'));
        el.style.strokeDashoffset = target;
    });
    document.querySelectorAll('.gauge-value').forEach(function (el) {
        var target = parseInt(el.getAttribute('data-target'), 10);
        animateNumber(el, 0, target, 1000);
    });
}, 400);

function animateNumber(el, from, to, duration) {
    var start = performance.now();
    function tick(now) {
        var t = Math.min(1, (now - start) / duration);
        t = 1 - Math.pow(1 - t, 3); // ease out cubic
        el.textContent = Math.round(from + (to - from) * t);
        if (t < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
}

/* ══════════════════════════════════════════════
   ADAPTIVE SESSION — infinite practice
══════════════════════════════════════════════ */
window.startAdaptiveSession = function (domain) {
    session.active = true;
    session.domain = domain || null;
    session.answered = 0;
    session.correct = 0;
    session.totalTime = 0;
    session.streak = 0;
    session.startedAt = Date.now();
    session.abilityTrend = [];
    session.quizMode = false;

    document.getElementById('sessionOverlay').classList.add('active');
    document.body.style.overflow = 'hidden';

    updateSessionStats();
    loadNextQuestion();
    startTimer();
};

window.endSession = function () {
    if (session.answered > 0 && !confirm('End your adaptive session? Your progress has been saved.')) return;
    closeSession();
};

function closeSession() {
    session.active = false;
    document.getElementById('sessionOverlay').classList.remove('active');
    document.body.style.overflow = '';
    if (session.timerInterval) clearInterval(session.timerInterval);

    // Reload page to refresh gauges with updated data
    if (session.answered > 0) {
        window.location.reload();
    }
}

/* ── Load Next Question ── */
function loadNextQuestion() {
    var card = document.getElementById('questionCard');
    card.className = 'question-card';
    card.innerHTML = '<div style="text-align:center;padding:40px;"><div class="loading-spinner"></div><p style="margin-top:14px;color:var(--tx3);font-size:.875rem;">Finding your next question...</p></div>';

    if (session.quizMode && session.quizIndex < session.quizQuestions.length) {
        var q = session.quizQuestions[session.quizIndex];
        session.currentQuestion = q;
        session.questionStartTime = Date.now();
        renderQuestion(q);
        session.quizIndex++;
        return;
    }

    var url = API_BASE + '?action=next_question';
    if (session.domain) url += '&domain=' + encodeURIComponent(session.domain);

    fetch(url, { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success || !data.data) {
                card.innerHTML = '<div style="text-align:center;padding:40px;color:var(--tx3);"><p>No more questions available. Great job!</p><button class="next-btn" onclick="endSession()" style="margin-top:16px;">Finish Session</button></div>';
                return;
            }
            session.currentQuestion = data.data;
            session.questionStartTime = Date.now();
            renderQuestion(data.data);
        })
        .catch(function (err) {
            card.innerHTML = '<div style="text-align:center;padding:40px;color:var(--err);"><p>Failed to load question. <button onclick="loadNextQuestion()" style="color:var(--ac);font-weight:700;text-decoration:underline;background:none;border:none;cursor:pointer;">Try again</button></p></div>';
        });
}

/* ── Render Question ── */
function renderQuestion(q) {
    var card = document.getElementById('questionCard');
    var diffClass = (q.difficulty || 'medium').toLowerCase();
    diffClass = diffClass === 'easy' ? 'diff-easy' : (diffClass === 'hard' ? 'diff-hard' : 'diff-medium');

    var domainLabel = {
        'algebra': 'Algebra',
        'advanced_math': 'Advanced Math',
        'problem_solving': 'Problem Solving',
        'geometry': 'Geometry & Trig'
    }[q.domain] || q.domain || 'Math';

    var html = '';
    html += '<div class="q-domain-badge">' + escHtml(domainLabel);
    html += '<span class="q-difficulty ' + diffClass + '">' + escHtml(q.difficulty || 'Medium') + '</span>';
    html += '</div>';
    html += '<div class="q-stem" id="qStem">' + q.stem + '</div>';
    html += '<div class="choices" id="choicesWrap">';

    var letters = ['A', 'B', 'C', 'D'];
    letters.forEach(function (letter) {
        var text = q.choices ? q.choices[letter] : '';
        if (!text) return;
        html += '<button class="choice-btn" data-letter="' + letter + '" onclick="selectChoice(this, \'' + letter + '\')">';
        html += '<span class="choice-letter">' + letter + '</span>';
        html += '<span class="choice-text">' + text + '</span>';
        html += '</button>';
    });
    html += '</div>';
    html += '<div class="feedback-area" id="feedbackArea"></div>';
    html += '<div class="ability-update" id="abilityUpdate"></div>';
    html += '<div class="next-btn-wrap" id="nextBtnWrap"><button class="next-btn" onclick="loadNextQuestion()"><span>Next Question</span><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></button></div>';

    card.innerHTML = html;

    // Update counter
    var label = session.quizMode
        ? 'Q ' + session.quizIndex + '/' + session.quizQuestions.length
        : 'Q ' + (session.answered + 1);
    document.getElementById('sessionCounter').textContent = label;

    // Update domain sidebar
    document.getElementById('ssDomain').textContent = domainLabel;

    // Render KaTeX
    renderMath();

    // Reset timer display for this question
    session.questionStartTime = Date.now();
}

/* ── Select Choice (submit answer) ── */
window.selectChoice = function (btn, letter) {
    if (btn.classList.contains('disabled')) return;

    // Disable all choices
    document.querySelectorAll('.choice-btn').forEach(function (b) { b.classList.add('disabled'); });
    btn.classList.add('selected');

    var timeSpent = Math.round((Date.now() - session.questionStartTime) / 1000);
    var q = session.currentQuestion;

    fetch(API_BASE + '?action=answer', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            question_id: q.question_id,
            answer: letter,
            time_spent: timeSpent,
        }),
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (!data.success) {
            alert('Error submitting answer. Please try again.');
            return;
        }

        var result = data.data.result;
        var isCorrect = result.correct;

        // Update session state
        session.answered++;
        if (isCorrect) { session.correct++; session.streak++; }
        else { session.streak = 0; }
        session.totalTime += timeSpent;
        session.abilityTrend.push(result.ability_after);

        // Highlight correct/incorrect
        var card = document.getElementById('questionCard');
        card.classList.add(isCorrect ? 'correct-flash' : 'wrong-flash');

        var correctLetter = result.correct_answer;
        document.querySelectorAll('.choice-btn').forEach(function (b) {
            if (b.dataset.letter === correctLetter) b.classList.add('correct');
            if (b.dataset.letter === letter && !isCorrect) b.classList.add('incorrect');
        });

        // Show feedback
        showFeedback(isCorrect, result);

        // Show ability update
        showAbilityUpdate(result);

        // Show next button
        document.getElementById('nextBtnWrap').classList.add('show');

        // Update sidebar stats
        updateSessionStats();
        updateMiniChart();

        // If quiz mode and done
        if (session.quizMode && session.quizIndex >= session.quizQuestions.length) {
            var nextWrap = document.getElementById('nextBtnWrap');
            nextWrap.innerHTML = '<button class="next-btn" onclick="closeSession()" style="background:var(--dk);color:#fff;">Finish Quiz &mdash; ' + session.correct + '/' + session.answered + ' correct</button>';
            nextWrap.classList.add('show');
        }
    })
    .catch(function (err) {
        console.error('Answer submission error:', err);
        document.querySelectorAll('.choice-btn').forEach(function (b) { b.classList.remove('disabled'); });
        btn.classList.remove('selected');
    });
};

/* ── Show Feedback ── */
function showFeedback(isCorrect, result) {
    var area = document.getElementById('feedbackArea');
    var cls = isCorrect ? 'feedback-correct' : 'feedback-wrong';
    var icon = isCorrect
        ? '<svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
        : '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
    var label = isCorrect ? 'Correct!' : 'Incorrect';

    var html = '<div class="feedback-header">' + icon + ' ' + label + '</div>';
    if (result.explanation) {
        html += '<div class="feedback-body">' + result.explanation + '</div>';
    }
    if (result.flag) {
        var flagLabels = {
            'suspected_guess': 'Possible guess detected — ability update reduced',
            'careless_error': 'Careless error detected — take your time on similar questions',
            'slow_correct': 'Correct but slow — practice for speed',
        };
        html += '<div class="feedback-flag">' + (flagLabels[result.flag] || result.flag) + '</div>';
    }

    area.className = 'feedback-area show ' + cls;
    area.innerHTML = html;

    // Render math in explanation
    renderMath();
}

/* ── Show Ability Update ── */
function showAbilityUpdate(result) {
    var area = document.getElementById('abilityUpdate');
    var change = result.ability_change;
    var after = result.ability_after;
    var pct = Math.max(0, Math.min(100, Math.round(50 + after * 16.67)));
    var deltaClass = change >= 0 ? 'positive' : 'negative';
    var deltaSign = change >= 0 ? '+' : '';

    var html = '<div class="ability-row">';
    html += '<span class="ability-label">' + escHtml(result.domain_label || 'Ability') + '</span>';
    html += '<div class="ability-bar-wrap"><div class="ability-bar" id="abilityBar" style="width:0%"></div></div>';
    html += '<span class="ability-delta ' + deltaClass + '">' + deltaSign + change.toFixed(3) + '</span>';
    html += '</div>';

    area.className = 'ability-update show';
    area.innerHTML = html;

    // Animate bar
    setTimeout(function () {
        var bar = document.getElementById('abilityBar');
        if (bar) bar.style.width = pct + '%';
    }, 50);
}

/* ── Timer ── */
function startTimer() {
    if (session.timerInterval) clearInterval(session.timerInterval);
    session.timerInterval = setInterval(function () {
        if (!session.questionStartTime) return;
        var elapsed = Math.round((Date.now() - session.questionStartTime) / 1000);
        var m = Math.floor(elapsed / 60);
        var s = elapsed % 60;
        document.getElementById('timerDisplay').textContent = m + ':' + (s < 10 ? '0' : '') + s;
    }, 1000);
}

/* ── Update Session Stats ── */
function updateSessionStats() {
    document.getElementById('ssAnswered').textContent = session.answered;
    document.getElementById('ssCorrect').textContent = session.correct;
    document.getElementById('ssAccuracy').textContent = session.answered > 0
        ? Math.round(session.correct / session.answered * 100) + '%'
        : '—';
    document.getElementById('ssAvgTime').textContent = session.answered > 0
        ? Math.round(session.totalTime / session.answered) + 's'
        : '—';

    var streakEl = document.getElementById('ssStreak');
    streakEl.textContent = session.streak;
    streakEl.className = 'ss-stat-value ' + (session.streak >= 3 ? 'green' : '');

    // Difficulty readout
    var diffEl = document.getElementById('ssDifficulty');
    if (session.abilityTrend.length > 0) {
        var lastAbility = session.abilityTrend[session.abilityTrend.length - 1];
        var level = lastAbility < -1 ? 'Below Average' : (lastAbility < 0 ? 'Approaching Average' : (lastAbility < 1 ? 'Above Average' : 'Advanced'));
        diffEl.textContent = level + ' (' + lastAbility.toFixed(2) + ')';
    }
}

/* ── Mini Chart ── */
function updateMiniChart() {
    var chart = document.getElementById('ssMiniChart');
    if (!chart) return;

    var trend = session.abilityTrend;
    var last20 = trend.slice(-20);
    if (last20.length === 0) return;

    var mn = Math.min.apply(null, last20);
    var mx = Math.max.apply(null, last20);
    var range = mx - mn || 1;

    var html = '';
    // We show answer correctness as colored bars
    var startIdx = Math.max(0, session.answered - 20);
    for (var i = 0; i < last20.length; i++) {
        var h = Math.max(4, ((last20[i] - mn) / range) * 36 + 4);
        // Determine color by whether this specific answer was correct
        // We use green for increases, red for decreases
        var color = i === 0 ? 'green' : (last20[i] >= last20[i - 1] ? 'green' : 'red');
        html += '<div class="ss-mini-bar ' + color + '" style="height:' + Math.round(h) + 'px"></div>';
    }
    chart.innerHTML = html;
}

/* ══════════════════════════════════════════════
   GENERATE QUIZ
══════════════════════════════════════════════ */
window.generateQuiz = function () {
    var domain = document.getElementById('genDomain').value;
    var count = parseInt(document.getElementById('genCount').value, 10);
    var btn = document.getElementById('genBtn');
    btn.textContent = 'Generating...';
    btn.disabled = true;

    var url = API_BASE + '?action=generate&count=' + count;
    if (domain && domain !== 'all') url += '&domain=' + encodeURIComponent(domain);

    fetch(url, { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            btn.textContent = 'Generate Quiz';
            btn.disabled = false;

            if (!data.success || !data.data || !data.data.questions || data.data.questions.length === 0) {
                alert('Could not generate quiz. Not enough questions available for the selected criteria.');
                return;
            }

            // Start a quiz-mode session
            session.active = true;
            session.domain = domain === 'all' ? null : domain;
            session.answered = 0;
            session.correct = 0;
            session.totalTime = 0;
            session.streak = 0;
            session.startedAt = Date.now();
            session.abilityTrend = [];
            session.quizMode = true;
            session.quizQuestions = data.data.questions;
            session.quizIndex = 0;

            document.getElementById('sessionOverlay').classList.add('active');
            document.body.style.overflow = 'hidden';

            updateSessionStats();
            loadNextQuestion();
            startTimer();
        })
        .catch(function (err) {
            btn.textContent = 'Generate Quiz';
            btn.disabled = false;
            alert('Failed to generate quiz. Please try again.');
        });
};

/* ══════════════════════════════════════════════
   MOBILE SIDEBAR TOGGLE
══════════════════════════════════════════════ */
window.toggleMobileSidebar = function () {
    var sb = document.getElementById('sessionSidebar');
    sb.classList.toggle('mobile-show');
};

/* ══════════════════════════════════════════════
   MATH RENDERING (KaTeX)
══════════════════════════════════════════════ */
function renderMath() {
    if (typeof renderMathInElement === 'function') {
        try {
            renderMathInElement(document.getElementById('questionCard'), {
                delimiters: [
                    { left: '$$', right: '$$', display: true },
                    { left: '$', right: '$', display: false },
                    { left: '\\(', right: '\\)', display: false },
                    { left: '\\[', right: '\\]', display: true },
                ],
                throwOnError: false,
            });
        } catch (e) { /* KaTeX rendering errors are non-fatal */ }
    }
}

/* ── Wait for KaTeX auto-render to load ── */
document.addEventListener('DOMContentLoaded', function () {
    // Initial render for any math on the page
    if (typeof renderMathInElement === 'function') {
        renderMathInElement(document.body, {
            delimiters: [
                { left: '$$', right: '$$', display: true },
                { left: '$', right: '$', display: false },
                { left: '\\(', right: '\\)', display: false },
                { left: '\\[', right: '\\]', display: true },
            ],
            throwOnError: false,
        });
    }
});

/* ══════════════════════════════════════════════
   KEYBOARD SHORTCUTS
══════════════════════════════════════════════ */
document.addEventListener('keydown', function (e) {
    if (!session.active) return;

    // A/B/C/D keys to select choice
    var key = e.key.toUpperCase();
    if (['A', 'B', 'C', 'D'].indexOf(key) !== -1 && !e.metaKey && !e.ctrlKey && !e.altKey) {
        var btn = document.querySelector('.choice-btn[data-letter="' + key + '"]:not(.disabled)');
        if (btn) {
            e.preventDefault();
            selectChoice(btn, key);
        }
    }

    // Enter or Space to go to next question
    if ((e.key === 'Enter' || e.key === ' ') && document.getElementById('nextBtnWrap').classList.contains('show')) {
        e.preventDefault();
        loadNextQuestion();
    }

    // Escape to end session
    if (e.key === 'Escape') {
        endSession();
    }
});

/* ══════════════════════════════════════════════
   HELPERS
══════════════════════════════════════════════ */
function escHtml(str) {
    var div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}

}());
</script>
</body>
</html>
