<?php
/**
 * /schedule/generate.php — AI Schedule Generation
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Schedule.php';

Auth::requireStudent();
$userId    = (int)$_SESSION['user_id'];
$user      = User::findById($userId);
$firstName = explode(' ', $user['name'] ?? 'Student')[0];

/* ── Handle generation POST ── */
$generated = false;
$error     = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Security token mismatch. Please refresh and try again.';
    } else {
        $opts = [];
        if (!empty($_POST['test_date']))   $opts['test_date']   = $_POST['test_date'];
        if (!empty($_POST['study_hours'])) $opts['study_hours'] = (float)$_POST['study_hours'];
        if (!empty($_POST['focus_area']))  $opts['focus_area']  = $_POST['focus_area'];
        try {
            Schedule::generate($userId, $opts);
            $generated = true;
        } catch (Throwable $e) {
            error_log('generate.php: ' . $e->getMessage());
            $error = 'Generation failed. Please try again.';
        }
    }
}

/* ── Preferences ── */
$prefs       = Schedule::getPreferences($userId);
$testDate    = $user['test_date'] ?? $prefs['test_date'] ?? '';
$studyHours  = $user['study_hours'] ?? ($_SESSION['study_hours'] ?? 2);
$targetScore = (int)($_SESSION['target_score'] ?? 1200);
$focusArea   = $_SESSION['focus_area'] ?? 'balanced';

$daysUntilTest = null;
if ($testDate) {
    $ts = strtotime($testDate);
    if ($ts && $ts > time()) {
        $daysUntilTest = (int)ceil(($ts - time()) / 86400);
    }
}

$weekStats  = Schedule::getWeeklyStats($userId);
$weakTopics = Schedule::getWeakTopics($userId, 3);
$todayTasks = Schedule::getToday($userId);
$hasPlan    = !empty($todayTasks);

$satDates = array_filter([
    '2025-05-03' => 'May 3, 2025',
    '2025-06-07' => 'June 7, 2025',
    '2025-08-23' => 'August 23, 2025',
    '2025-10-04' => 'October 4, 2025',
    '2025-11-01' => 'November 1, 2025',
    '2025-12-06' => 'December 6, 2025',
    '2026-03-14' => 'March 14, 2026',
    '2026-05-02' => 'May 2, 2026',
    '2026-06-06' => 'June 6, 2026',
], fn($d, $k) => strtotime($k) > time(), ARRAY_FILTER_USE_BOTH);

$activePage  = 'schedule';
$topbarTitle = 'Generate Study Plan';
$topbarSub   = $hasPlan ? 'Regenerate your AI study plan' : 'Build your personalised AI study plan';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Generate Study Plan — Avidmock SAT</title>
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
.page-layout {
    display: grid;
    grid-template-columns: 1fr 310px;
    gap: 20px;
    align-items: start;
}
.col-left  { display: flex; flex-direction: column; gap: 16px; }

/* ─────────────────────────────────────────────
   HERO
───────────────────────────────────────────── */
.hero {
    background: var(--dk);
    border-radius: 20px;
    padding: 2.25rem 2.5rem;
    position: relative;
    overflow: hidden;
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
.hero-bg-glow2 {
    position: absolute;
    bottom: -60px; left: 8%;
    width: 200px; height: 200px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(31,226,144,.04) 0%, transparent 65%);
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
    animation: eyePulse 1.8s ease-in-out infinite;
}
@keyframes eyePulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50%       { transform: scale(1.5); opacity: .5; }
}

.hero-title {
    font-size: clamp(1.25rem, 3vw, 1.75rem);
    font-weight: 800;
    color: #fff;
    letter-spacing: -.035em;
    line-height: 1.2;
    margin-bottom: .625rem;
}
.hero-title em { font-style: normal; color: var(--ac); }
.hero-sub {
    font-size: .9375rem;
    color: rgba(255,255,255,.42);
    line-height: 1.7;
    max-width: 460px;
    margin-bottom: 1.375rem;
}

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
.hero-stat-val em { font-style: normal; font-size: .75rem; font-weight: 700; color: rgba(255,255,255,.35); margin-left: 2px; }
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
   ALERTS
───────────────────────────────────────────── */
.alert {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 13px 16px;
    border-radius: 13px;
    font-size: .875rem;
    font-weight: 600;
    animation: alertIn .4s cubic-bezier(.16,1,.3,1);
}
@keyframes alertIn {
    from { opacity: 0; transform: translateY(-6px); }
    to   { opacity: 1; transform: none; }
}
.alert svg {
    width: 16px; height: 16px;
    stroke: currentColor; fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
    flex-shrink: 0;
    margin-top: 1px;
}
.alert a { color: inherit; text-decoration: underline; }
.alert-ok   { background: rgba(16,185,129,.07);  border: 1px solid rgba(16,185,129,.2); color: #065f46; }
.alert-err  { background: rgba(239,68,68,.06);   border: 1px solid rgba(239,68,68,.2);  color: var(--err); }
.alert-info { background: rgba(20,50,48,.04);    border: 1px solid rgba(20,50,48,.1);   color: var(--dk); }

/* ─────────────────────────────────────────────
   SUCCESS BANNER
───────────────────────────────────────────── */
.success-banner {
    background: rgba(16,185,129,.07);
    border: 1px solid rgba(16,185,129,.2);
    border-radius: var(--r-lg);
    padding: 2rem;
    text-align: center;
}
.success-banner-ico {
    width: 52px; height: 52px;
    stroke: var(--ok); fill: none;
    stroke-width: 1.4; stroke-linecap: round; stroke-linejoin: round;
    margin: 0 auto .875rem; display: block;
}
.success-banner h3 {
    font-size: 1.0625rem;
    font-weight: 800;
    color: var(--dk);
    letter-spacing: -.02em;
    margin-bottom: .375rem;
}
.success-banner p {
    font-size: .875rem;
    color: var(--tx2);
    line-height: 1.65;
    margin-bottom: 1.25rem;
    max-width: 420px;
    margin-left: auto;
    margin-right: auto;
}
.success-actions {
    display: flex;
    gap: 8px;
    justify-content: center;
    flex-wrap: wrap;
}
.btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 11px 22px;
    background: var(--dk);
    color: #fff;
    font-family: var(--ff);
    font-size: .875rem;
    font-weight: 800;
    border-radius: 11px;
    border: none;
    cursor: pointer;
    transition: background .2s, box-shadow .2s, transform .2s;
    min-height: 44px;
}
.btn-primary:hover { background: var(--dk2); box-shadow: 0 6px 20px rgba(20,50,48,.15); transform: translateY(-1px); }
.btn-primary svg { stroke: var(--ac); }
.btn-ghost {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 11px 20px;
    background: transparent;
    color: var(--tx2);
    font-family: var(--ff);
    font-size: .875rem;
    font-weight: 700;
    border-radius: 11px;
    border: 1.5px solid var(--bd);
    cursor: pointer;
    transition: border-color .18s, color .18s;
    min-height: 44px;
}
.btn-ghost:hover { border-color: var(--ac); color: var(--dk); }
.btn-primary svg, .btn-ghost svg {
    width: 14px; height: 14px;
    fill: none; stroke-width: 2;
    stroke-linecap: round; stroke-linejoin: round;
    flex-shrink: 0;
}
.btn-ghost svg { stroke: currentColor; }

/* ─────────────────────────────────────────────
   CARDS (light)
───────────────────────────────────────────── */
.card {
    background: var(--bg2);
    border: 1px solid var(--bd);
    border-radius: var(--r-lg);
    overflow: hidden;
}
.card-header {
    padding: 1rem 1.375rem;
    border-bottom: 1px solid var(--bd);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.card-header-title {
    font-size: .9375rem;
    font-weight: 800;
    color: var(--tx);
    letter-spacing: -.02em;
    display: flex;
    align-items: center;
    gap: 8px;
}
.card-header-title svg {
    width: 15px; height: 15px;
    stroke: var(--ac2); fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
    flex-shrink: 0;
}
.card-header-link {
    font-size: .75rem;
    font-weight: 700;
    color: var(--ac2);
    transition: color .15s;
}
.card-header-link:hover { color: var(--dk); }
.card-body { padding: 1.25rem 1.375rem; }

/* Config rows */
.config-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: .5625rem 0;
    border-bottom: 1px solid var(--bd);
}
.config-row:first-child { padding-top: 0; }
.config-row:last-child  { border-bottom: none; padding-bottom: 0; }
.config-key { font-size: .8125rem; color: var(--tx3); font-weight: 600; }
.config-val { font-size: .9rem; font-weight: 800; color: var(--tx); }
.config-val.ac      { color: var(--ac2); }
.config-val.missing { color: var(--warn); font-size: .8125rem; }
.config-val.missing a { color: inherit; text-decoration: underline; }

/* Focus area options grid */
.focus-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-bottom: 1.125rem;
}
.opt-radio { display: none; }
.opt-label {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 11px 13px;
    border: 2px solid var(--bd);
    border-radius: 11px;
    cursor: pointer;
    transition: border-color .18s, background .18s;
}
.opt-label:hover { border-color: rgba(31,226,144,.4); }
.opt-radio:checked + .opt-label {
    border-color: var(--ac);
    background: rgba(31,226,144,.05);
}
.opt-label-title {
    font-size: .8125rem;
    font-weight: 700;
    color: var(--tx);
    line-height: 1.2;
}
.opt-label-sub { font-size: .625rem; color: var(--tx3); margin-top: 1px; }
.opt-radio:checked + .opt-label .opt-label-title { color: var(--dk); }

.form-label {
    display: block;
    font-size: .75rem;
    font-weight: 700;
    color: var(--tx2);
    margin-bottom: .375rem;
}
.form-select {
    width: 100%;
    padding: 9px 13px;
    border: 1.5px solid var(--bd);
    border-radius: 10px;
    font-family: var(--ff);
    font-size: .875rem;
    color: var(--tx);
    background: var(--bg2);
    outline: none;
    transition: border-color .2s, box-shadow .2s;
    -webkit-appearance: none;
    appearance: none;
    margin-bottom: 12px;
    cursor: pointer;
}
.form-select:focus {
    border-color: var(--ac);
    box-shadow: 0 0 0 3px rgba(31,226,144,.1);
}
.form-select:last-child { margin-bottom: 0; }

/* Warning note */
.warn-note {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: .75rem;
    font-weight: 600;
    color: var(--warn);
    margin-bottom: 10px;
}
.warn-note svg {
    width: 14px; height: 14px;
    stroke: var(--warn); fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
    flex-shrink: 0;
}

/* Generate button */
.gen-btn-wrap {
    padding: 1.125rem 1.375rem;
    border-top: 1px solid var(--bd);
    background: var(--bg);
}
.gen-btn {
    width: 100%;
    padding: 14px;
    background: var(--dk);
    color: #fff;
    font-family: var(--ff);
    font-size: 1rem;
    font-weight: 800;
    border: none;
    border-radius: 13px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    letter-spacing: -.02em;
    transition: background .25s, box-shadow .25s, transform .25s;
    min-height: 52px;
}
.gen-btn:hover:not(:disabled) {
    background: var(--dk2);
    box-shadow: 0 8px 28px rgba(20,50,48,.2);
    transform: translateY(-2px);
}
.gen-btn:disabled { opacity: .65; cursor: not-allowed; transform: none; }
.gen-btn .gen-icon svg {
    width: 18px; height: 18px;
    stroke: var(--ac); fill: none;
    stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round;
}
.gen-btn .spinner {
    width: 18px; height: 18px;
    border: 2.5px solid rgba(255,255,255,.2);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin .7s linear infinite;
    display: none;
    flex-shrink: 0;
}
@keyframes spin { to { transform: rotate(360deg); } }
.gen-btn.loading .spinner   { display: block; }
.gen-btn.loading .gen-icon  { display: none; }
.gen-btn.loading .gen-label { opacity: .7; }

/* ─────────────────────────────────────────────
   LOADING OVERLAY
───────────────────────────────────────────── */
.loading-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(10,20,18,.75);
    backdrop-filter: blur(8px);
    z-index: 999;
    align-items: center;
    justify-content: center;
    padding: 16px;
}
.loading-overlay.show { display: flex; }

.loading-card {
    background: var(--bg2);
    border-radius: 22px;
    padding: 2.25rem 2rem;
    max-width: 380px;
    width: 100%;
    text-align: center;
    box-shadow: 0 24px 64px rgba(0,0,0,.2);
}
.loading-orbit {
    width: 56px; height: 56px;
    border-radius: 50%;
    border: 3px solid var(--bd);
    border-top-color: var(--ac);
    animation: spin .85s linear infinite;
    margin: 0 auto 1.125rem;
}
.loading-title {
    font-size: 1.0625rem;
    font-weight: 800;
    color: var(--tx);
    letter-spacing: -.025em;
    margin-bottom: .375rem;
}
.loading-sub {
    font-size: .8125rem;
    color: var(--tx3);
    line-height: 1.65;
}

.loading-steps {
    display: flex;
    flex-direction: column;
    gap: 5px;
    margin-top: 1.125rem;
    text-align: left;
}
.loading-step {
    display: flex;
    align-items: center;
    gap: 9px;
    font-size: .8125rem;
    color: var(--tx3);
    padding: 8px 11px;
    border-radius: 9px;
    transition: background .35s, color .35s;
}
.loading-step svg {
    width: 15px; height: 15px;
    stroke: currentColor; fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
    flex-shrink: 0;
}
.loading-step.active {
    background: rgba(31,226,144,.07);
    color: var(--dk);
    font-weight: 700;
}
.loading-step.active svg { stroke: var(--ac2); }
.loading-step.done {
    color: var(--ok);
    font-weight: 600;
}

/* ─────────────────────────────────────────────
   RIGHT PANEL
───────────────────────────────────────────── */
.right-panel { display: flex; flex-direction: column; gap: 14px; }

/* Info card rows */
.info-card {
    background: var(--bg2);
    border: 1px solid var(--bd);
    border-radius: var(--r-lg);
    overflow: hidden;
}
.info-card-hd {
    padding: .875rem 1.125rem;
    border-bottom: 1px solid var(--bd);
    font-size: .9rem;
    font-weight: 800;
    color: var(--tx);
}
.info-card-body { padding: .875rem 1.125rem; }
.info-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: .45rem 0;
    border-bottom: 1px solid var(--bd);
}
.info-row:first-child { padding-top: 0; }
.info-row:last-child  { border-bottom: none; padding-bottom: 0; }
.info-key { font-size: .75rem; color: var(--tx3); font-weight: 600; }
.info-val { font-size: .875rem; font-weight: 800; color: var(--tx); }
.info-val.ac { color: var(--ac2); }

/* Weak topics */
.weak-item {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: .5rem 0;
    border-bottom: 1px solid var(--bd);
}
.weak-item:first-child { padding-top: 0; }
.weak-item:last-child  { border-bottom: none; padding-bottom: 0; }
.weak-name { font-size: .75rem; font-weight: 700; color: var(--tx); min-width: 80px; flex-shrink: 0; }
.weak-bar-track { flex: 1; height: 5px; background: var(--bd); border-radius: 3px; overflow: hidden; }
.weak-bar-fill  { height: 100%; background: linear-gradient(90deg, #dc2626, var(--err)); border-radius: 3px; }
.weak-score { font-size: .75rem; font-weight: 800; color: var(--err); min-width: 34px; text-align: right; }

/* Dark action card */
.action-card {
    background: var(--dk);
    border-radius: var(--r-lg);
    padding: 1.25rem;
    position: relative;
    overflow: hidden;
}
.action-card-bg-grid {
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(31,226,144,.025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(31,226,144,.025) 1px, transparent 1px);
    background-size: 30px 30px;
    pointer-events: none;
}
.action-card-bg-glow {
    position: absolute;
    top: -40px; right: -40px;
    width: 150px; height: 150px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(31,226,144,.08) 0%, transparent 65%);
    pointer-events: none;
}
.action-card-inner { position: relative; z-index: 1; }
.action-card-title { font-size: .875rem; font-weight: 800; color: #fff; margin-bottom: 3px; }
.action-card-sub   { font-size: .75rem; color: rgba(255,255,255,.38); line-height: 1.5; margin-bottom: 1rem; }
.action-links { display: flex; flex-direction: column; gap: 5px; }
.action-link {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 11px;
    border-radius: 9px;
    background: rgba(255,255,255,.05);
    color: rgba(255,255,255,.6);
    font-size: .8125rem;
    font-weight: 600;
    transition: background .18s, color .18s;
}
.action-link:hover { background: rgba(255,255,255,.1); color: #fff; }
.action-link svg {
    width: 13px; height: 13px;
    stroke: var(--ac); fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
    flex-shrink: 0;
}

/* ─────────────────────────────────────────────
   RESPONSIVE
───────────────────────────────────────────── */
@media (max-width: 1024px) {
    .page-layout { grid-template-columns: 1fr; }
    .right-panel { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px; }
}

@media (max-width: 900px) {
    .main-content { margin-left: 0; padding: 20px var(--pad-sm) 72px; }
}

@media (max-width: 600px) {
    .hero { padding: 1.375rem 1.125rem; border-radius: 16px; }
    .hero-title { font-size: 1.25rem; }
    .focus-grid { grid-template-columns: 1fr; }
    .right-panel { grid-template-columns: 1fr; }
    .success-actions { flex-direction: column; }
    .btn-primary, .btn-ghost { width: 100%; justify-content: center; }
}

@media (max-width: 480px) {
    .hero-stat { padding: 9px 12px; }
    .hero-stat-val { font-size: 1rem; }
    .gen-btn { font-size: .9375rem; padding: 12px; }
}

/* Safe area */
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

<!-- ══ LOADING OVERLAY ════════════════════════ -->
<div class="loading-overlay" id="loadingOverlay" role="dialog" aria-modal="true" aria-label="Generating your plan">
    <div class="loading-card">
        <div class="loading-orbit"></div>
        <div class="loading-title">Generating Your Plan</div>
        <div class="loading-sub">Our AI is building a personalised study schedule based on your goals and progress.</div>
        <div class="loading-steps">
            <div class="loading-step active" id="ls1">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Analysing your progress
            </div>
            <div class="loading-step" id="ls2">
                <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                Building lesson sequence
            </div>
            <div class="loading-step" id="ls3">
                <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                Scheduling reviews &amp; tests
            </div>
            <div class="loading-step" id="ls4">
                <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                Finalising your schedule
            </div>
        </div>
    </div>
</div>

<main class="main-content" id="mainContent">

    <!-- ── Success banner ── -->
    <?php if ($generated): ?>
    <div class="success-banner sr" id="successBanner">
        <svg class="success-banner-ico" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        <h3>Your Study Plan is Ready!</h3>
        <p>We've generated a personalised <?= $daysUntilTest ? $daysUntilTest . '-day' : '90-day' ?> study plan covering lessons, quizzes, practice tests, and spaced reviews.</p>
        <div class="success-actions">
            <a href="/schedule/" class="btn-primary">
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                View My Schedule
            </a>
            <a href="/schedule/edit.php" class="btn-ghost">Edit Today's Plan</a>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-err sr">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if (!$testDate && !$generated): ?>
    <div class="alert alert-info sr">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        No test date set. <a href="/schedule/setup.php">Set your test date in Setup</a> for a more accurate plan.
    </div>
    <?php endif; ?>

    <div class="page-layout">

        <!-- ══ LEFT COLUMN ═══════════════════════ -->
        <div class="col-left">

            <!-- Hero -->
            <div class="hero sr d1">
                <div class="hero-bg-grid"></div>
                <div class="hero-bg-glow"></div>
                <div class="hero-bg-glow2"></div>
                <div class="hero-inner">
                    <div class="hero-eyebrow">
                        <span class="hero-eyebrow-dot"></span>
                        AI-Powered Planning
                    </div>
                    <h1 class="hero-title">
                        <?php if ($hasPlan): ?>
                            Regenerate Your <em>Study Plan</em>
                        <?php else: ?>
                            Build Your <em>Personalised</em> Study Plan
                        <?php endif; ?>
                    </h1>
                    <p class="hero-sub">
                        <?php if ($hasPlan): ?>
                            Your plan will be rebuilt from today, keeping completed tasks and incorporating your latest quiz performance.
                        <?php else: ?>
                            We'll create a day-by-day study plan tailored to your test date, study hours, and current skill level.
                        <?php endif; ?>
                    </p>
                    <?php if ($daysUntilTest || $testDate): ?>
                    <div class="hero-stats">
                        <?php if ($daysUntilTest): ?>
                        <div class="hero-stat">
                            <div class="hero-stat-val ac"><?= $daysUntilTest ?><em>d</em></div>
                            <div class="hero-stat-key">Until Test</div>
                        </div>
                        <?php endif; ?>
                        <div class="hero-stat">
                            <div class="hero-stat-val"><?= $targetScore ?></div>
                            <div class="hero-stat-key">Target Score</div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-val"><?= $studyHours ?><em>h</em></div>
                            <div class="hero-stat-key">Per Day</div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-val"><?= $daysUntilTest ? min($daysUntilTest, 90) : 90 ?><em>d</em></div>
                            <div class="hero-stat-key">Plan Length</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Current settings -->
            <div class="card sr d2">
                <div class="card-header">
                    <div class="card-header-title">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                        Current Settings
                    </div>
                    <a href="/schedule/setup.php" class="card-header-link">Edit Settings &rarr;</a>
                </div>
                <div class="card-body">
                    <div class="config-row">
                        <span class="config-key">Test Date</span>
                        <?php if ($testDate): ?>
                        <span class="config-val ac"><?= date('M j, Y', strtotime($testDate)) ?></span>
                        <?php else: ?>
                        <span class="config-val missing">Not set — <a href="/schedule/setup.php">add one</a></span>
                        <?php endif; ?>
                    </div>
                    <div class="config-row">
                        <span class="config-key">Target Score</span>
                        <span class="config-val"><?= $targetScore ?> / 1600</span>
                    </div>
                    <div class="config-row">
                        <span class="config-key">Study Hours / Day</span>
                        <span class="config-val"><?= $studyHours ?>h (<?= (int)($studyHours * 60) ?> min)</span>
                    </div>
                    <div class="config-row">
                        <span class="config-key">Focus Area</span>
                        <span class="config-val"><?= ucwords(str_replace('_', ' ', $focusArea)) ?></span>
                    </div>
                    <div class="config-row">
                        <span class="config-key">Plan Length</span>
                        <span class="config-val"><?= $daysUntilTest ? min($daysUntilTest, 90) : 90 ?> days</span>
                    </div>
                </div>
            </div>

            <!-- Generation form -->
            <div class="card sr d3">
                <div class="card-header">
                    <div class="card-header-title">
                        <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
                        Generation Options
                    </div>
                </div>
                <form method="POST" id="generateForm">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <div class="card-body">

                        <label class="form-label">Focus area for this generation</label>
                        <div class="focus-grid">
                            <?php
                            $focusOptions = [
                                'balanced'   => ['Balanced',       'Equal Math & R&W'],
                                'math'       => ['Math Focus',      'Prioritise Math'],
                                'reading'    => ['R&W Focus',       'Prioritise Reading'],
                                'weaknesses' => ['Fix Weaknesses',  'Target weak areas'],
                            ];
                            foreach ($focusOptions as $val => [$title, $sub]):
                            ?>
                            <input type="radio" name="focus_area" value="<?= $val ?>"
                                   id="fo-<?= $val ?>" class="opt-radio"
                                   <?= $focusArea === $val ? 'checked' : '' ?>>
                            <label for="fo-<?= $val ?>" class="opt-label">
                                <span class="opt-label-title"><?= $title ?></span>
                                <span class="opt-label-sub"><?= $sub ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>

                        <?php if (!empty($satDates)): ?>
                        <label class="form-label" for="test_date_override">Override test date <span style="font-weight:500;color:var(--tx3)">(optional)</span></label>
                        <select name="test_date" id="test_date_override" class="form-select">
                            <option value="">— Use saved date (<?= $testDate ? date('M j, Y', strtotime($testDate)) : 'none' ?>) —</option>
                            <?php foreach ($satDates as $d => $lbl): ?>
                            <option value="<?= $d ?>"><?= $lbl ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>

                        <label class="form-label" for="study_hours_override">Override study hours <span style="font-weight:500;color:var(--tx3)">(optional)</span></label>
                        <select name="study_hours" id="study_hours_override" class="form-select">
                            <option value="">— Use saved (<?= $studyHours ?> hrs/day) —</option>
                            <?php foreach ([0.5, 1, 1.5, 2, 2.5, 3, 4, 5] as $h): ?>
                            <option value="<?= $h ?>"><?= $h ?> hrs/day (<?= (int)($h * 60) ?> min)</option>
                            <?php endforeach; ?>
                        </select>

                    </div>
                    <div class="gen-btn-wrap">
                        <?php if ($hasPlan): ?>
                        <div class="warn-note">
                            <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            This will replace future incomplete tasks in your plan.
                        </div>
                        <?php endif; ?>
                        <button type="submit" class="gen-btn" id="genBtn" onclick="startLoading()">
                            <span class="gen-icon">
                                <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
                            </span>
                            <div class="spinner"></div>
                            <span class="gen-label"><?= $hasPlan ? 'Regenerate Study Plan' : 'Generate My Study Plan' ?></span>
                        </button>
                    </div>
                </form>
            </div>

        </div><!-- /col-left -->

        <!-- ══ RIGHT PANEL ════════════════════════ -->
        <div class="right-panel sr d2">

            <!-- This week's progress -->
            <div class="info-card">
                <div class="info-card-hd">This Week's Progress</div>
                <div class="info-card-body">
                    <?php
                    $completedTasks = (int)($weekStats['completed_tasks']  ?? 0);
                    $totalTasks     = (int)($weekStats['total_tasks']      ?? 0);
                    $completedMins  = (int)($weekStats['completed_mins']   ?? 0);
                    $completionRate = (int)($weekStats['completion_rate']  ?? 0);
                    ?>
                    <div class="info-row">
                        <span class="info-key">Tasks done</span>
                        <span class="info-val ac"><?= $completedTasks ?>/<?= $totalTasks ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-key">Study time</span>
                        <span class="info-val"><?= $completedMins ?> min</span>
                    </div>
                    <div class="info-row">
                        <span class="info-key">Completion rate</span>
                        <span class="info-val"><?= $completionRate ?>%</span>
                    </div>
                </div>
            </div>

            <!-- Weak topics -->
            <?php if (!empty($weakTopics)): ?>
            <div class="info-card">
                <div class="info-card-hd">Weak Areas to Focus On</div>
                <div class="info-card-body">
                    <?php foreach ($weakTopics as $topic): ?>
                    <div class="weak-item">
                        <span class="weak-name"><?= htmlspecialchars($topic['name'] ?? 'Topic') ?></span>
                        <div class="weak-bar-track">
                            <div class="weak-bar-fill" style="width:<?= min(100, intval($topic['score'] ?? 0)) ?>%"></div>
                        </div>
                        <span class="weak-score"><?= intval($topic['score'] ?? 0) ?>%</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick links -->
            <div class="action-card">
                <div class="action-card-bg-grid"></div>
                <div class="action-card-bg-glow"></div>
                <div class="action-card-inner">
                    <div class="action-card-title">More Options</div>
                    <div class="action-card-sub">Customise your plan or review today's tasks.</div>
                    <div class="action-links">
                        <a href="/schedule/setup.php" class="action-link">
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                            Schedule Setup
                        </a>
                        <a href="/schedule/" class="action-link">
                            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            View Current Plan
                        </a>
                        <a href="/schedule/edit.php" class="action-link">
                            <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            Edit Today's Tasks
                        </a>
                        <a href="/schedule/spaced-review.php" class="action-link">
                            <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
                            Start Spaced Review
                        </a>
                    </div>
                </div>
            </div>

        </div><!-- /right-panel -->
    </div><!-- /page-layout -->

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

    /* ── Loading overlay with step animation ── */
    var STEPS       = ['ls1', 'ls2', 'ls3', 'ls4'];
    var currentStep = 0;
    var stepTimer;

    window.startLoading = function () {
        /* Brief delay so the browser registers the click before we block the UI */
        setTimeout(function () {
            document.getElementById('loadingOverlay').classList.add('show');
            document.body.style.overflow = 'hidden';
        }, 60);

        var btn = document.getElementById('genBtn');
        if (btn) { btn.disabled = true; btn.classList.add('loading'); }

        stepTimer = setInterval(function () {
            if (currentStep < STEPS.length) {
                if (currentStep > 0) {
                    var prev = document.getElementById(STEPS[currentStep - 1]);
                    if (prev) { prev.classList.remove('active'); prev.classList.add('done'); }
                }
                var cur = document.getElementById(STEPS[currentStep]);
                if (cur) cur.classList.add('active');
                currentStep++;
            } else {
                clearInterval(stepTimer);
            }
        }, 950);
    };

    <?php if ($generated): ?>
    /* Auto-scroll success banner into view */
    setTimeout(function () {
        var banner = document.getElementById('successBanner');
        if (banner) banner.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, 250);
    <?php endif; ?>

}());
</script>
</body>
</html>