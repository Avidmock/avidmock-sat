<?php
/**
 * /schedule/setup.php — Schedule Configuration
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Schedule.php';

Auth::requireStudent();
$userId    = $_SESSION['user_id'];
$user      = User::findById($userId);
$firstName = explode(' ', $user['name'] ?? 'Student')[0];

$success = false;
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Security token mismatch. Please refresh and try again.';
    } else {
        $testDate    = trim($_POST['test_date']    ?? '');
        $targetScore = (int)($_POST['target_score'] ?? 1200);
        $studyHours  = (float)($_POST['study_hours'] ?? 2);
        $studyDays   = array_map('trim', (array)($_POST['study_days'] ?? ['Mon','Tue','Wed','Thu','Fri']));
        $focusArea   = trim($_POST['focus_area'] ?? 'balanced');

        if ($testDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $testDate)) {
            $errors[] = 'Invalid test date format.';
        }
        if ($testDate && strtotime($testDate) < strtotime('today')) {
            $errors[] = 'Test date cannot be in the past.';
        }
        if ($targetScore < 400 || $targetScore > 1600) {
            $errors[] = 'Target score must be between 400 and 1600.';
        }
        if ($studyHours < 0.5 || $studyHours > 8) {
            $errors[] = 'Study hours must be between 0.5 and 8.';
        }
        if (empty($studyDays)) {
            $errors[] = 'Please select at least one study day.';
        }

        if (empty($errors)) {
            User::update($userId, [
                'test_date'    => $testDate ?: null,
                'study_hours'  => $studyHours,
                'target_score' => $targetScore,
            ]);
            Schedule::savePreferences($userId, [
                'study_days'  => implode(',', $studyDays),
                'focus_area'  => $focusArea,
                'study_hours' => $studyHours,
            ]);

            $success = true;
            $user    = User::findById($userId);

            if (!empty($_POST['regenerate'])) {
                header('Location: /schedule/generate.php');
                exit;
            }
        }
    }
}

/* ── Current values ── */
$testDate    = $user['test_date']    ?? '';
$targetScore = (int)($_SESSION['target_score'] ?? $user['target_score'] ?? 1200);
$studyHours  = (float)($user['study_hours'] ?? $_SESSION['study_hours'] ?? 2);

$prefs        = Schedule::getPreferences($userId);
$studyDaysArr = isset($_SESSION['study_days'])
    ? explode(',', $_SESSION['study_days'])
    : (isset($prefs['study_days']) ? explode(',', $prefs['study_days']) : ['Mon','Tue','Wed','Thu','Fri']);
$focusArea    = $_SESSION['focus_area'] ?? $prefs['focus_area'] ?? 'balanced';

$daysUntilTest = null;
if ($testDate) {
    $ts = strtotime($testDate);
    if ($ts && $ts > time()) {
        $daysUntilTest = (int)ceil(($ts - time()) / 86400);
    }
}

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

$allDays = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];

$activePage  = 'schedule';
$topbarTitle = 'Schedule Setup';
$topbarSub   = 'Configure your test date, study hours, and preferences';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Schedule Setup — Avidmock SAT</title>
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
.d4 { transition-delay: .24s; }

/* ─────────────────────────────────────────────
   LAYOUT
───────────────────────────────────────────── */
.main-content {
    margin-left: var(--sidebar-w); margin-top: var(--topbar-h);
    padding: 32px 28px 80px;
    min-height: calc(100vh - var(--topbar-h));
}
.sidebar-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.45); z-index: 250;
    opacity: 0; transition: opacity .28s; pointer-events: none;
}
.sidebar-overlay.show { opacity: 1; pointer-events: all; display: block; }

/* ─────────────────────────────────────────────
   BREADCRUMB
───────────────────────────────────────────── */
.breadcrumb { display: flex; align-items: center; gap: 6px; font-size: .8125rem; color: var(--tx3); margin-bottom: 20px; }
.breadcrumb a { color: var(--ac2); font-weight: 600; transition: color .15s; }
.breadcrumb a:hover { color: var(--ac); }
.breadcrumb svg { width: 12px; height: 12px; stroke: var(--tx3); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.breadcrumb strong { font-weight: 700; color: var(--tx); }

/* ─────────────────────────────────────────────
   PAGE LAYOUT
───────────────────────────────────────────── */
.setup-layout { display: grid; grid-template-columns: 1fr 340px; gap: 24px; align-items: start; }

/* ─────────────────────────────────────────────
   ALERTS
───────────────────────────────────────────── */
.alert {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 12px 16px; border-radius: var(--r-sm);
    margin-bottom: 16px; font-size: .875rem; font-weight: 600;
}
.alert svg { width: 16px; height: 16px; flex-shrink: 0; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; margin-top: 1px; }
.alert-success { background: rgba(16,185,129,.08); border: 1px solid rgba(16,185,129,.2); color: #065f46; }
.alert-error   { background: rgba(239,68,68,.07);  border: 1px solid rgba(239,68,68,.2);  color: var(--err); }

/* ─────────────────────────────────────────────
   FORM CARDS
───────────────────────────────────────────── */
.form-card { background: var(--bg2); border: 1px solid var(--bd); border-radius: var(--r-lg); overflow: hidden; margin-bottom: 20px; }
.form-card-hd { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--bd); display: flex; align-items: center; gap: 10px; }
.form-card-ico { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.form-card-ico svg { width: 18px; height: 18px; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.ico-ac   { background: rgba(31,226,144,.08); } .ico-ac   svg { stroke: var(--ac2); }
.ico-dk   { background: rgba(20,50,48,.06);   } .ico-dk   svg { stroke: var(--dk); }
.ico-warn { background: rgba(245,158,11,.08); } .ico-warn svg { stroke: var(--warn); }
.form-card-title { font-size: 1rem; font-weight: 800; color: var(--tx); letter-spacing: -.02em; }
.form-card-sub   { font-size: .75rem; color: var(--tx3); margin-top: 1px; }
.form-card-body  { padding: 1.5rem; }
.form-group { margin-bottom: 1.25rem; }
.form-group:last-child { margin-bottom: 0; }
.form-label { display: block; font-size: .8125rem; font-weight: 700; color: var(--tx2); margin-bottom: .5rem; }
.form-input, .form-select {
    width: 100%; padding: 10px 14px;
    border: 2px solid var(--bd); border-radius: 10px;
    font-family: var(--ff); font-size: .9rem; color: var(--tx);
    background: var(--bg2); outline: none;
    transition: border-color .18s; appearance: none;
}
.form-input:focus, .form-select:focus { border-color: var(--ac); box-shadow: 0 0 0 3px rgba(31,226,144,.1); }
.form-hint { font-size: .75rem; color: var(--tx3); margin-top: 5px; line-height: 1.5; }

/* ─────────────────────────────────────────────
   SAT DATE GRID
───────────────────────────────────────────── */
.sat-dates-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
.sat-date-option { display: none; }
.sat-date-label {
    display: flex; flex-direction: column;
    padding: 10px 12px; border: 2px solid var(--bd);
    border-radius: 10px; cursor: pointer; transition: all .18s; text-align: center;
}
.sat-date-label:hover { border-color: var(--ac); }
.sat-date-month { font-size: .6875rem; font-weight: 800; color: var(--tx3); text-transform: uppercase; letter-spacing: .4px; }
.sat-date-full  { font-family: var(--fm); font-size: .8125rem; font-weight: 700; color: var(--tx); margin-top: 1px; }
.sat-date-option:checked + .sat-date-label { border-color: var(--ac); background: rgba(31,226,144,.06); }
.sat-date-option:checked + .sat-date-label .sat-date-month { color: var(--ac); }
.sat-date-option:checked + .sat-date-label .sat-date-full  { color: var(--dk); }
.manual-date-row { margin-top: 10px; display: flex; align-items: center; gap: 8px; }
.manual-date-row label { font-size: .75rem; font-weight: 600; color: var(--tx3); flex-shrink: 0; }

/* ─────────────────────────────────────────────
   SLIDERS
───────────────────────────────────────────── */
.slider-wrap { padding: .5rem 0; }
.score-slider, .hours-slider {
    width: 100%; -webkit-appearance: none; appearance: none;
    height: 6px; border-radius: 3px; background: var(--bd); outline: none; cursor: pointer;
}
.score-slider::-webkit-slider-thumb, .hours-slider::-webkit-slider-thumb {
    -webkit-appearance: none; width: 22px; height: 22px; border-radius: 50%;
    background: var(--dk); cursor: pointer;
    border: 3px solid var(--ac); box-shadow: 0 2px 8px rgba(20,50,48,.2);
}
.score-display { display: flex; justify-content: space-between; align-items: center; margin-top: 8px; }
.score-current { font-family: var(--fm); font-size: 1.75rem; font-weight: 700; color: var(--dk); letter-spacing: -.04em; }
.percentile-val { font-family: var(--fm); font-size: 1rem; font-weight: 700; color: var(--tx); }
.score-range-labels { display: flex; justify-content: space-between; font-size: .6875rem; color: var(--tx3); font-weight: 600; margin-top: 4px; font-family: var(--fm); }
.hours-display { font-family: var(--fm); font-size: 1.375rem; font-weight: 700; color: var(--dk); margin-top: 6px; }

/* ─────────────────────────────────────────────
   DAY TOGGLES
───────────────────────────────────────────── */
.day-toggles { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
.day-toggle { display: none; }
.day-toggle-label {
    display: flex; flex-direction: column; align-items: center; gap: 3px;
    padding: 10px 4px; border: 2px solid var(--bd); border-radius: 10px;
    cursor: pointer; transition: all .18s; user-select: none;
}
.day-toggle-label:hover { border-color: var(--ac); }
.day-toggle-short { font-size: .5625rem; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; color: var(--tx3); }
.day-toggle-indicator { width: 8px; height: 8px; border-radius: 50%; background: var(--bd); transition: background .18s; }
.day-toggle:checked + .day-toggle-label { border-color: var(--ac); background: rgba(31,226,144,.06); }
.day-toggle:checked + .day-toggle-label .day-toggle-short { color: var(--ac); }
.day-toggle:checked + .day-toggle-label .day-toggle-indicator { background: var(--ac); }

/* ─────────────────────────────────────────────
   FOCUS OPTIONS
───────────────────────────────────────────── */
.focus-options { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.focus-radio { display: none; }
.focus-label {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 14px; border: 2px solid var(--bd);
    border-radius: 12px; cursor: pointer; transition: all .18s;
}
.focus-label:hover { border-color: var(--ac); }
.focus-ico {
    width: 32px; height: 32px; border-radius: 8px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    background: rgba(31,226,144,.06);
}
.focus-ico svg { width: 16px; height: 16px; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; stroke: var(--ac2); }
.focus-radio:checked + .focus-label { border-color: var(--ac); background: rgba(31,226,144,.04); }
.focus-radio:checked + .focus-label .focus-ico { background: rgba(31,226,144,.15); }
.focus-text-title { font-size: .8125rem; font-weight: 700; color: var(--tx); }
.focus-text-sub   { font-size: .6875rem; color: var(--tx3); margin-top: 1px; }

/* ─────────────────────────────────────────────
   FORM ACTIONS
───────────────────────────────────────────── */
.form-actions {
    display: flex; gap: 10px; flex-wrap: wrap;
    padding: 1.25rem 1.5rem; border-top: 1px solid var(--bd); background: var(--bg);
}
.btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 10px 20px; border-radius: 10px;
    font-family: var(--ff); font-size: .875rem; font-weight: 700;
    border: none; cursor: pointer; text-decoration: none; transition: all .2s;
}
.btn svg { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.btn-primary { background: var(--dk); color: #fff; }
.btn-primary:hover { background: var(--dk2); box-shadow: 0 6px 20px rgba(20,50,48,.2); transform: translateY(-1px); }
.btn-primary svg { stroke: var(--ac); }
.btn-regen { background: var(--ac); color: var(--dk); }
.btn-regen:hover { background: var(--ac2); box-shadow: 0 6px 20px rgba(31,226,144,.25); transform: translateY(-1px); }
.btn-ghost { background: transparent; color: var(--tx2); border: 1.5px solid var(--bd); }
.btn-ghost:hover { border-color: var(--ac); color: var(--dk); }

/* ─────────────────────────────────────────────
   RIGHT PANEL — PREVIEW CARD
───────────────────────────────────────────── */
.preview-card {
    background: var(--dk); border-radius: var(--r-lg);
    padding: 1.5rem; position: relative; overflow: hidden; margin-bottom: 16px;
}
.preview-card::before {
    content: ''; position: absolute; top: -60px; right: -60px;
    width: 200px; height: 200px; border-radius: 50%; pointer-events: none;
    background: radial-gradient(circle, rgba(31,226,144,.08) 0%, transparent 60%);
}
.preview-label {
    font-size: .5625rem; font-weight: 800; color: var(--ac);
    text-transform: uppercase; letter-spacing: .1em;
    margin-bottom: .75rem; display: flex; align-items: center; gap: 5px;
}
.preview-label-dot { width: 5px; height: 5px; border-radius: 50%; background: var(--ac); animation: pulse 2s ease-in-out infinite; }
@keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: .3; } }
.preview-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: .5rem 0; border-bottom: 1px solid rgba(255,255,255,.06);
}
.preview-row:last-child { border-bottom: none; padding-bottom: 0; }
.preview-key { font-size: .75rem; color: rgba(255,255,255,.4); font-weight: 600; }
.preview-val { font-family: var(--fm); font-size: .875rem; color: #fff; font-weight: 700; }
.preview-val.highlight { color: var(--ac); }

/* ─────────────────────────────────────────────
   TIPS CARD
───────────────────────────────────────────── */
.tips-card { background: var(--bg2); border: 1px solid var(--bd); border-radius: var(--r); padding: 1.25rem; }
.tips-card-title { font-size: .8125rem; font-weight: 800; color: var(--tx); margin-bottom: .75rem; letter-spacing: -.01em; }
.tips-list { list-style: none; display: flex; flex-direction: column; gap: 8px; }
.tips-list li {
    display: flex; align-items: flex-start; gap: 8px;
    font-size: .8rem; color: var(--tx3); line-height: 1.55;
}
.tip-icon {
    width: 16px; height: 16px; border-radius: 50%; flex-shrink: 0; margin-top: 1px;
    background: rgba(31,226,144,.1);
    display: flex; align-items: center; justify-content: center;
}
.tip-icon svg { width: 8px; height: 8px; stroke: var(--ac2); fill: none; stroke-width: 3; stroke-linecap: round; stroke-linejoin: round; }

/* ─────────────────────────────────────────────
   RESPONSIVE
───────────────────────────────────────────── */
@media (max-width: 1024px) { .setup-layout { grid-template-columns: 1fr; } }
@media (max-width: 900px)  { .main-content { margin-left: 0; padding: 24px 16px 80px; } }
@media (max-width: 600px)  {
    .day-toggles     { grid-template-columns: repeat(4, 1fr); }
    .focus-options   { grid-template-columns: 1fr; }
    .sat-dates-grid  { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/sidebar.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/topbar.php';
?>

<main class="main-content">

    <!-- ── Breadcrumb ── -->
    <div class="breadcrumb sr">
        <a href="/schedule/">Schedule</a>
        <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        <strong>Setup</strong>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success sr">
        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        <div>Settings saved! <a href="/schedule/" style="color:inherit;text-decoration:underline">Back to schedule →</a></div>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-error sr">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <div><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
    </div>
    <?php endif; ?>

    <form method="POST" action="/schedule/setup.php" id="setupForm">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

        <div class="setup-layout">

            <!-- ── LEFT COLUMN ── -->
            <div>

                <!-- Test Date -->
                <div class="form-card sr d1">
                    <div class="form-card-hd">
                        <div class="form-card-ico ico-warn">
                            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        </div>
                        <div>
                            <div class="form-card-title">SAT Test Date</div>
                            <div class="form-card-sub">Select your upcoming SAT exam date</div>
                        </div>
                    </div>
                    <div class="form-card-body">
                        <div class="form-group">
                            <label class="form-label">Choose a College Board test date</label>
                            <div class="sat-dates-grid">
                                <?php foreach ($satDates as $dateVal => $dateLabel):
                                    $isSelected = $testDate === $dateVal;
                                    $idSlug     = str_replace('-', '', $dateVal);
                                ?>
                                <input type="radio" name="sat_date_preset" value="<?= $dateVal ?>"
                                       id="sat-<?= $idSlug ?>" class="sat-date-option"
                                       <?= $isSelected ? 'checked' : '' ?>
                                       onchange="setTestDate('<?= $dateVal ?>')">
                                <label for="sat-<?= $idSlug ?>" class="sat-date-label">
                                    <span class="sat-date-month"><?= date('M Y', strtotime($dateVal)) ?></span>
                                    <span class="sat-date-full"><?= $dateLabel ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="manual-date-row">
                                <label for="test_date">Or enter custom date:</label>
                                <input type="date" name="test_date" id="test_date" class="form-input" style="flex:1"
                                       value="<?= htmlspecialchars($testDate) ?>"
                                       min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                            </div>
                            <?php if ($daysUntilTest): ?>
                            <div class="form-hint" style="color:var(--ac2);font-weight:700">
                                <svg style="width:12px;height:12px;stroke:var(--ac2);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;vertical-align:middle;margin-right:3px" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                <?= $daysUntilTest ?> days until your test!
                            </div>
                            <?php else: ?>
                            <div class="form-hint">Your test date determines how your study plan is structured and paced.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Target Score -->
                <div class="form-card sr d2">
                    <div class="form-card-hd">
                        <div class="form-card-ico ico-ac">
                            <svg viewBox="0 0 24 24"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
                        </div>
                        <div>
                            <div class="form-card-title">Target Score</div>
                            <div class="form-card-sub">Set your SAT score goal (400–1600)</div>
                        </div>
                    </div>
                    <div class="form-card-body">
                        <div class="form-group">
                            <label class="form-label">Drag the slider to set your target</label>
                            <div class="slider-wrap">
                                <input type="range" name="target_score" id="targetScoreSlider" class="score-slider"
                                       min="400" max="1600" step="10" value="<?= $targetScore ?>"
                                       oninput="updateScoreDisplay(this.value)">
                                <div class="score-display">
                                    <div>
                                        <div style="font-size:.5625rem;font-weight:700;color:var(--tx3);text-transform:uppercase;letter-spacing:.08em;margin-bottom:2px">Target Score</div>
                                        <div class="score-current" id="scoreDisplayVal"><?= $targetScore ?></div>
                                    </div>
                                    <div style="text-align:right">
                                        <div style="font-size:.5625rem;font-weight:700;color:var(--tx3);text-transform:uppercase;letter-spacing:.08em;margin-bottom:2px">Percentile</div>
                                        <div class="percentile-val" id="percentileDisplay">—</div>
                                    </div>
                                </div>
                                <div class="score-range-labels"><span>400</span><span>800</span><span>1200</span><span>1600</span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Study Preferences -->
                <div class="form-card sr d3">
                    <div class="form-card-hd">
                        <div class="form-card-ico ico-dk">
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </div>
                        <div>
                            <div class="form-card-title">Study Preferences</div>
                            <div class="form-card-sub">Hours per day and days of the week</div>
                        </div>
                    </div>
                    <div class="form-card-body">
                        <div class="form-group">
                            <label class="form-label">Daily study hours</label>
                            <input type="range" name="study_hours" id="studyHoursSlider" class="hours-slider"
                                   min="0.5" max="6" step="0.5" value="<?= $studyHours ?>"
                                   oninput="updateHoursDisplay(this.value)">
                            <div class="hours-display" id="hoursDisplay"><?= $studyHours ?> hrs/day</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Study days</label>
                            <div class="day-toggles">
                                <?php foreach ($allDays as $day): ?>
                                <div>
                                    <input type="checkbox" name="study_days[]" value="<?= $day ?>"
                                           id="day-<?= $day ?>" class="day-toggle"
                                           <?= in_array($day, $studyDaysArr) ? 'checked' : '' ?>>
                                    <label for="day-<?= $day ?>" class="day-toggle-label">
                                        <span class="day-toggle-short"><?= $day ?></span>
                                        <span class="day-toggle-indicator"></span>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Focus Area -->
                <div class="form-card sr d3">
                    <div class="form-card-hd">
                        <div class="form-card-ico ico-ac">
                            <svg viewBox="0 0 24 24"><path d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                        <div>
                            <div class="form-card-title">Focus Area</div>
                            <div class="form-card-sub">Where should the AI prioritise your study time?</div>
                        </div>
                    </div>
                    <div class="form-card-body">
                        <div class="focus-options">
                            <?php
                            $focusOpts = [
                                'balanced'   => ['title' => 'Balanced',       'sub' => 'Equal time on Math &amp; R&amp;W',  'ico' => '<path d="M3 6h18M3 12h18M3 18h18"/>'],
                                'math'       => ['title' => 'Math Focus',     'sub' => 'More time on Math',                 'ico' => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>'],
                                'reading'    => ['title' => 'R&amp;W Focus',  'sub' => 'More time on Reading &amp; Writing','ico' => '<path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/>'],
                                'weaknesses' => ['title' => 'Fix Weaknesses', 'sub' => 'AI targets your weak areas',       'ico' => '<path d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" stroke-linecap="round" stroke-linejoin="round"/>'],
                            ];
                            foreach ($focusOpts as $val => $opt):
                            ?>
                            <input type="radio" name="focus_area" value="<?= $val ?>"
                                   id="focus-<?= $val ?>" class="focus-radio"
                                   <?= $focusArea === $val ? 'checked' : '' ?>>
                            <label for="focus-<?= $val ?>" class="focus-label">
                                <div class="focus-ico">
                                    <svg viewBox="0 0 24 24"><?= $opt['ico'] ?></svg>
                                </div>
                                <div>
                                    <div class="focus-text-title"><?= $opt['title'] ?></div>
                                    <div class="focus-text-sub"><?= $opt['sub'] ?></div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Save actions -->
                <div class="form-card sr d4" style="overflow:hidden">
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            Save Settings
                        </button>
                        <button type="submit" name="regenerate" value="1" class="btn btn-regen">
                            <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
                            Save &amp; Regenerate Plan
                        </button>
                        <a href="/schedule/" class="btn btn-ghost">
                            <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                            Cancel
                        </a>
                    </div>
                </div>

            </div><!-- /left col -->

            <!-- ── RIGHT COLUMN ── -->
            <div class="sr d2">

                <!-- Live preview -->
                <div class="preview-card">
                    <div class="preview-label">
                        <span class="preview-label-dot"></span>Current Settings
                    </div>
                    <div class="preview-row">
                        <span class="preview-key">Test Date</span>
                        <span class="preview-val highlight" id="previewDate"><?= $testDate ? date('M j, Y', strtotime($testDate)) : 'Not set' ?></span>
                    </div>
                    <div class="preview-row">
                        <span class="preview-key">Days Remaining</span>
                        <span class="preview-val" id="previewDays"><?= $daysUntilTest ?? '—' ?></span>
                    </div>
                    <div class="preview-row">
                        <span class="preview-key">Target Score</span>
                        <span class="preview-val highlight" id="previewScore"><?= $targetScore ?></span>
                    </div>
                    <div class="preview-row">
                        <span class="preview-key">Study Hours/Day</span>
                        <span class="preview-val" id="previewHours"><?= $studyHours ?> hrs</span>
                    </div>
                    <div class="preview-row">
                        <span class="preview-key">Focus Area</span>
                        <span class="preview-val" id="previewFocus"><?= ucwords(str_replace('_', ' ', $focusArea)) ?></span>
                    </div>
                    <div class="preview-row">
                        <span class="preview-key">Tasks/Week (est.)</span>
                        <span class="preview-val highlight" id="previewTasks">—</span>
                    </div>
                </div>

                <!-- Tips -->
                <div class="tips-card">
                    <div class="tips-card-title">Study Plan Tips</div>
                    <ul class="tips-list">
                        <?php foreach ([
                            'More days = more flexibility in your plan.',
                            '2–3 hours/day is optimal for most students.',
                            'The AI includes spaced repetition automatically.',
                            'Practice tests scheduled near your test date.',
                            'Your weakest topics always get extra focus.',
                        ] as $tip): ?>
                        <li>
                            <span class="tip-icon">
                                <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                            </span>
                            <?= htmlspecialchars($tip) ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

            </div><!-- /right col -->
        </div><!-- /setup-layout -->
    </form>

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

    /* ── Score percentile lookup ── */
    var pcts = {400:1,500:10,600:24,700:43,800:60,900:73,1000:84,1100:91,1200:96,1300:98,1400:99,1500:99,1600:99};
    function getPct(s) {
        var keys = Object.keys(pcts).map(Number).sort(function (a, b) { return a - b; });
        for (var i = keys.length - 1; i >= 0; i--) { if (s >= keys[i]) return pcts[keys[i]]; }
        return 1;
    }

    /* ── Score slider ── */
    window.updateScoreDisplay = function (val) {
        document.getElementById('scoreDisplayVal').textContent   = val;
        document.getElementById('previewScore').textContent      = val;
        document.getElementById('percentileDisplay').textContent = getPct(parseInt(val)) + 'th %ile';
        updateEstimatedTasks();
    };

    /* ── Hours slider ── */
    window.updateHoursDisplay = function (val) {
        document.getElementById('hoursDisplay').textContent  = val + ' hrs/day';
        document.getElementById('previewHours').textContent  = val + ' hrs';
        updateEstimatedTasks();
    };

    /* ── Tasks/week estimate ── */
    function updateEstimatedTasks() {
        var hrs  = parseFloat(document.getElementById('studyHoursSlider').value || 2);
        var days = document.querySelectorAll('.day-toggle:checked').length || 5;
        document.getElementById('previewTasks').textContent = Math.round((hrs * days * 60) / 25);
    }

    /* ── Preset date selection ── */
    window.setTestDate = function (dateVal) {
        document.getElementById('test_date').value = dateVal;
        var d    = new Date(dateVal);
        var now  = new Date();
        var diff = Math.ceil((d - now) / (1000 * 60 * 60 * 24));
        document.getElementById('previewDate').textContent = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        document.getElementById('previewDays').textContent = diff > 0 ? diff : '—';
    };

    /* ── Day toggles → update estimate ── */
    document.querySelectorAll('.day-toggle').forEach(function (cb) {
        cb.addEventListener('change', updateEstimatedTasks);
    });

    /* ── Focus radios → update preview ── */
    document.querySelectorAll('.focus-radio').forEach(function (r) {
        r.addEventListener('change', function () {
            document.getElementById('previewFocus').textContent = this.value
                .replace(/_/g, ' ')
                .replace(/\b\w/g, function (c) { return c.toUpperCase(); });
        });
    });

    /* ── Init ── */
    updateScoreDisplay(<?= $targetScore ?>);
    updateEstimatedTasks();

}());
</script>
</body>
</html>