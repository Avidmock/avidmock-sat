<?php
/**
 * /schedule/edit.php — Edit Study Schedule
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Schedule.php';

Auth::requireStudent();
$userId    = $_SESSION['user_id'];
$user      = User::findById($userId);
$firstName = explode(' ', $user['name'] ?? 'Student')[0];

/* ── Requested day ── */
$requestedDay = $_GET['day'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDay)) {
    $requestedDay = date('Y-m-d');
}
$dayTs    = strtotime($requestedDay);
$dayLabel = date('l, F j', $dayTs);
$isToday  = $requestedDay === date('Y-m-d');

/* ── Week days for day-picker ── */
$weekStart = strtotime('monday this week');
$weekDays  = [];
for ($d = 0; $d < 7; $d++) {
    $ts  = $weekStart + ($d * 86400);
    $key = date('Y-m-d', $ts);
    $weekDays[$key] = [
        'label'    => date('D', $ts),
        'num'      => date('j', $ts),
        'is_today' => $key === date('Y-m-d'),
        'is_past'  => $ts < strtotime('today'),
    ];
}

/* ── Tasks for selected day ── */
$tasks = Schedule::getDay($userId, $requestedDay) ?? [];

/* ── Handle AJAX ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';

    if ($action === 'reorder') {
        $order = array_map('intval', $body['order'] ?? []);
        echo json_encode(['success' => Schedule::reorderTasks($userId, $requestedDay, $order)]);
        exit;
    }
    if ($action === 'update_task') {
        $taskId  = intval($body['task_id'] ?? 0);
        $updates = [];
        if (isset($body['duration_min'])) $updates['duration_min'] = (int)$body['duration_min'];
        if (isset($body['title']))        $updates['title']        = trim(substr($body['title'], 0, 255));
        if (isset($body['task_type']))    $updates['task_type']    = $body['task_type'];
        echo json_encode(['success' => Schedule::updateTask($userId, $taskId, $updates)]);
        exit;
    }
    if ($action === 'delete_task') {
        echo json_encode(['success' => Schedule::deleteTask($userId, intval($body['task_id'] ?? 0))]);
        exit;
    }
    if ($action === 'add_task') {
        $taskId = Schedule::addTask($userId, $requestedDay, [
            'title'        => trim(substr($body['title'] ?? 'Custom Task', 0, 255)),
            'task_type'    => $body['task_type']    ?? 'lesson',
            'duration_min' => (int)($body['duration_min'] ?? 25),
        ]);
        echo json_encode(['success' => (bool)$taskId, 'task_id' => $taskId]);
        exit;
    }
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

/* ── Flash ── */
$success = $_SESSION['edit_success'] ?? null;
$error   = $_SESSION['edit_error']   ?? null;
unset($_SESSION['edit_success'], $_SESSION['edit_error']);

$tasksDone  = count(array_filter($tasks, fn($t) => $t['is_complete'] ?? false));
$tasksTotal = count($tasks);
$totalMin   = array_sum(array_column($tasks, 'duration_min'));
$donePct    = $tasksTotal > 0 ? round(($tasksDone / $tasksTotal) * 100) : 0;

$typeOptions = [
    'lesson'        => 'Lesson',
    'quiz'          => 'Quiz',
    'review'        => 'Review',
    'practice_test' => 'Practice Test',
    'spaced_review' => 'Spaced Review',
    'rest'          => 'Rest',
];

$activePage  = 'schedule';
$topbarTitle = 'Edit Schedule';
$topbarSub   = htmlspecialchars($dayLabel) . ' · Drag to reorder · Click to edit';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Schedule — Avidmock SAT</title>
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

    /* task type palette */
    --t-lesson-bg:  rgba(20,50,48,.07);    --t-lesson-fg:  #143230;
    --t-quiz-bg:    rgba(31,226,144,.1);   --t-quiz-fg:    #0d7a4a;
    --t-review-bg:  rgba(88,101,242,.09);  --t-review-fg:  #5865f2;
    --t-pt-bg:      rgba(245,158,11,.1);   --t-pt-fg:      #92400e;
    --t-rest-bg:    rgba(20,50,48,.05);    --t-rest-fg:    #4a4a5a;
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

/* ─────────────────────────────────────────────
   PAGE HEADER
───────────────────────────────────────────── */
.page-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.page-title {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--tx);
    letter-spacing: -.03em;
    line-height: 1.2;
}
.page-title em { font-style: normal; color: var(--ac); }
.page-sub { font-size: .875rem; color: var(--tx3); margin-top: 3px; }

.header-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.hdr-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 16px;
    border-radius: 10px;
    font-family: var(--ff);
    font-size: .8125rem;
    font-weight: 700;
    border: 1.5px solid var(--bd);
    background: var(--bg2);
    color: var(--tx2);
    transition: border-color .18s, color .18s, background .18s, transform .18s, box-shadow .18s;
    min-height: 38px;
}
.hdr-btn:hover { border-color: var(--ac); color: var(--dk); }
.hdr-btn svg {
    width: 14px; height: 14px;
    stroke: currentColor; fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
    flex-shrink: 0;
}
.hdr-btn-primary {
    background: var(--dk);
    color: #fff;
    border-color: transparent;
}
.hdr-btn-primary svg { stroke: var(--ac); }
.hdr-btn-primary:hover {
    background: var(--dk2);
    color: #fff;
    border-color: transparent;
    box-shadow: 0 4px 16px rgba(20,50,48,.18);
    transform: translateY(-1px);
}

/* ─────────────────────────────────────────────
   ALERTS
───────────────────────────────────────────── */
.alert {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    border-radius: 12px;
    margin-bottom: 16px;
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
}
.alert-ok  { background: rgba(16,185,129,.07); border: 1px solid rgba(16,185,129,.2); color: #065f46; }
.alert-err { background: rgba(239,68,68,.06);  border: 1px solid rgba(239,68,68,.2);  color: var(--err); }

/* ─────────────────────────────────────────────
   DAY PICKER
───────────────────────────────────────────── */
.day-picker {
    display: flex;
    background: var(--bg2);
    border: 1px solid var(--bd);
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 20px;
    overflow-x: auto;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
}
.day-picker::-webkit-scrollbar { display: none; }

.day-tab {
    flex: 1;
    min-width: 48px;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 12px 6px;
    text-decoration: none;
    border-right: 1px solid var(--bd);
    position: relative;
    transition: background .18s;
    gap: 2px;
}
.day-tab:last-child { border-right: none; }
.day-tab:hover:not(.active) { background: rgba(31,226,144,.03); }
.day-tab.active {
    background: var(--dk);
}
.day-tab.past { opacity: .55; }

.day-tab-dow {
    font-size: .5rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: var(--tx3);
    line-height: 1;
}
.day-tab-num {
    font-size: 1.125rem;
    font-weight: 800;
    color: var(--tx);
    line-height: 1.1;
    letter-spacing: -.03em;
}
.day-tab-dot {
    width: 4px; height: 4px;
    border-radius: 50%;
    background: var(--ac);
}
.day-tab.today:not(.active) .day-tab-num { color: var(--ac); }
.day-tab.active .day-tab-dow  { color: rgba(255,255,255,.4); }
.day-tab.active .day-tab-num  { color: #fff; }
.day-tab.active .day-tab-dot  { background: var(--ac); }

/* ─────────────────────────────────────────────
   EDIT LAYOUT (2-col)
───────────────────────────────────────────── */
.edit-layout {
    display: grid;
    grid-template-columns: 1fr 296px;
    gap: 18px;
    align-items: start;
}

/* ─────────────────────────────────────────────
   TASK LIST CARD
───────────────────────────────────────────── */
.task-card {
    background: var(--bg2);
    border: 1px solid var(--bd);
    border-radius: var(--r-lg);
    overflow: hidden;
}
.task-card-header {
    padding: 1rem 1.375rem;
    border-bottom: 1px solid var(--bd);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.task-card-title { font-size: 1rem; font-weight: 800; color: var(--tx); letter-spacing: -.02em; }
.task-card-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: .75rem;
    color: var(--tx3);
    font-weight: 600;
}
.task-card-meta svg {
    width: 12px; height: 12px;
    stroke: var(--tx3); fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
}

/* Task rows */
.task-list { padding: .75rem; display: flex; flex-direction: column; gap: 7px; }

.task-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: .875rem .9375rem;
    background: var(--bg2);
    border: 1.5px solid var(--bd);
    border-radius: 12px;
    cursor: grab;
    position: relative;
    transition: border-color .2s, box-shadow .2s, transform .2s, opacity .2s;
    overflow: hidden;
}
.task-row::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 3px;
    border-radius: 0 2px 2px 0;
    background: transparent;
    transition: background .18s;
}
.task-row:hover { border-color: rgba(31,226,144,.35); box-shadow: 0 3px 12px rgba(20,50,48,.06); }
.task-row:hover::before { background: var(--ac); }
.task-row.dragging { opacity: .4; cursor: grabbing; transform: scale(1.015); box-shadow: 0 10px 28px rgba(20,50,48,.14); border-color: var(--ac); }
.task-row.drag-over { border-color: var(--ac); background: rgba(31,226,144,.03); }
.task-row.done { opacity: .55; }
.task-row.done .task-row-title { text-decoration: line-through; color: var(--tx3); }

/* Drag handle */
.drag-handle {
    display: flex;
    flex-direction: column;
    gap: 3px;
    flex-shrink: 0;
    padding: 3px 2px;
    cursor: grab;
    opacity: .4;
    transition: opacity .15s;
}
.drag-handle span {
    display: block;
    height: 2px;
    width: 14px;
    background: var(--bd2);
    border-radius: 1px;
    transition: background .15s;
}
.task-row:hover .drag-handle { opacity: 1; }
.task-row:hover .drag-handle span { background: var(--ac); }

/* Type icon */
.task-type-ico {
    width: 30px; height: 30px;
    border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.task-type-ico svg {
    width: 14px; height: 14px;
    fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
}

.t-lesson .task-type-ico        { background: var(--t-lesson-bg); }
.t-lesson .task-type-ico svg    { stroke: var(--t-lesson-fg); }
.t-quiz .task-type-ico          { background: var(--t-quiz-bg); }
.t-quiz .task-type-ico svg      { stroke: var(--t-quiz-fg); }
.t-review .task-type-ico,
.t-spaced_review .task-type-ico { background: var(--t-review-bg); }
.t-review .task-type-ico svg,
.t-spaced_review .task-type-ico svg { stroke: var(--t-review-fg); }
.t-practice_test .task-type-ico { background: var(--t-pt-bg); }
.t-practice_test .task-type-ico svg { stroke: var(--t-pt-fg); }
.t-rest .task-type-ico          { background: var(--t-rest-bg); }
.t-rest .task-type-ico svg      { stroke: var(--t-rest-fg); }

/* Task info */
.task-row-info { flex: 1; min-width: 0; }
.task-row-title {
    font-size: .9375rem;
    font-weight: 700;
    color: var(--tx);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.3;
    margin-bottom: 3px;
}
.task-row-chips { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; }
.type-chip {
    display: inline-flex;
    align-items: center;
    padding: 1px 7px;
    border-radius: 5px;
    font-size: .5625rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .3px;
}
.tc-lesson        { background: var(--t-lesson-bg);  color: var(--t-lesson-fg); }
.tc-quiz          { background: var(--t-quiz-bg);    color: var(--t-quiz-fg); }
.tc-review,
.tc-spaced_review { background: var(--t-review-bg);  color: var(--t-review-fg); }
.tc-practice_test { background: var(--t-pt-bg);      color: var(--t-pt-fg); }
.tc-rest          { background: var(--t-rest-bg);    color: var(--t-rest-fg); }

.done-chip {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 1px 7px;
    border-radius: 5px;
    font-size: .5625rem;
    font-weight: 800;
    background: rgba(16,185,129,.08);
    color: #065f46;
}
.done-chip svg {
    width: 8px; height: 8px;
    stroke: currentColor; fill: none;
    stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round;
}

/* Duration stepper */
.dur-stepper {
    display: flex;
    align-items: center;
    gap: 5px;
    flex-shrink: 0;
}
.dur-btn {
    width: 24px; height: 24px;
    border-radius: 7px;
    border: 1.5px solid var(--bd);
    background: var(--bg2);
    display: flex; align-items: center; justify-content: center;
    font-size: .875rem;
    font-weight: 800;
    color: var(--tx2);
    line-height: 1;
    transition: border-color .15s, color .15s, background .15s;
    padding-bottom: 1px;
}
.dur-btn:hover { border-color: var(--ac); color: var(--dk); background: rgba(31,226,144,.04); }
.dur-val {
    font-size: .8125rem;
    font-weight: 800;
    color: var(--tx);
    min-width: 40px;
    text-align: center;
    letter-spacing: -.02em;
}

/* Row action buttons */
.row-actions { display: flex; gap: 4px; flex-shrink: 0; }
.row-act-btn {
    width: 30px; height: 30px;
    border-radius: 9px;
    border: 1.5px solid var(--bd);
    background: var(--bg2);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    transition: border-color .15s, background .15s;
}
.row-act-btn svg {
    width: 13px; height: 13px;
    stroke: var(--tx3); fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
    transition: stroke .15s;
}
.row-act-btn:hover { border-color: var(--ac); }
.row-act-btn:hover svg { stroke: var(--dk); }
.row-act-btn.del:hover { border-color: var(--err); background: rgba(239,68,68,.04); }
.row-act-btn.del:hover svg { stroke: var(--err); }

/* Empty state */
.task-empty {
    text-align: center;
    padding: 3rem 1.5rem;
}
.task-empty svg {
    width: 44px; height: 44px;
    stroke: var(--bd2); fill: none;
    stroke-width: 1.4; stroke-linecap: round; stroke-linejoin: round;
    margin: 0 auto 1.125rem; display: block;
}
.task-empty h3 {
    font-size: .9375rem;
    font-weight: 800;
    color: var(--tx);
    letter-spacing: -.02em;
    margin-bottom: .375rem;
}
.task-empty p { font-size: .875rem; color: var(--tx3); line-height: 1.65; }
.task-empty a { color: var(--ac); font-weight: 700; }

/* Add task footer */
.task-card-footer {
    padding: .875rem 1.125rem;
    border-top: 1px solid var(--bd);
    background: var(--bg);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}
.add-task-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 9px;
    background: var(--dk);
    color: #fff;
    font-family: var(--ff);
    font-size: .8125rem;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: background .2s, box-shadow .2s, transform .2s;
    min-height: 36px;
}
.add-task-btn:hover { background: var(--dk2); box-shadow: 0 4px 14px rgba(20,50,48,.14); transform: translateY(-1px); }
.add-task-btn svg {
    width: 13px; height: 13px;
    stroke: var(--ac); fill: none;
    stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round;
}
.footer-hint { font-size: .6875rem; color: var(--tx3); font-weight: 600; }

/* ─────────────────────────────────────────────
   RIGHT PANEL
───────────────────────────────────────────── */
.right-panel { display: flex; flex-direction: column; gap: 14px; }

/* ── Stats card ── */
.stats-card {
    background: var(--bg2);
    border: 1px solid var(--bd);
    border-radius: var(--r-lg);
    overflow: hidden;
}
.stats-card-hd {
    padding: .875rem 1.125rem;
    border-bottom: 1px solid var(--bd);
    font-size: .9rem;
    font-weight: 800;
    color: var(--tx);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.stats-card-hd-sub { font-size: .6875rem; font-weight: 600; color: var(--tx3); }
.stats-card-body { padding: 1rem 1.125rem; display: flex; flex-direction: column; gap: 10px; }
.stat-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.stat-row-label {
    display: flex; align-items: center; gap: 5px;
    font-size: .75rem; color: var(--tx3); font-weight: 600;
}
.stat-row-label svg {
    width: 12px; height: 12px;
    stroke: var(--tx3); fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
}
.stat-row-val { font-size: .9rem; font-weight: 800; color: var(--tx); letter-spacing: -.02em; }
.stat-row-val.ac { color: var(--ac2); }

.progress-bar-track {
    height: 6px;
    background: var(--bd);
    border-radius: 3px;
    overflow: hidden;
    margin-top: 4px;
}
.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--ac2), var(--ac));
    border-radius: 3px;
    transition: width 1.2s cubic-bezier(.16,1,.3,1);
}
.progress-bar-label {
    font-size: .625rem;
    color: var(--tx3);
    font-weight: 600;
    text-align: right;
    margin-top: 4px;
}

/* ── Quick add card ── */
.quick-add-card {
    background: var(--bg2);
    border: 1px solid var(--bd);
    border-radius: var(--r-lg);
    overflow: hidden;
}
.qa-hd {
    padding: .875rem 1.125rem;
    border-bottom: 1px solid var(--bd);
    font-size: .9rem;
    font-weight: 800;
    color: var(--tx);
}
.qa-body { padding: 1rem 1.125rem; }
.qa-label {
    display: block;
    font-size: .6875rem;
    font-weight: 700;
    color: var(--tx2);
    margin-bottom: .35rem;
}
.qa-input, .qa-select {
    width: 100%;
    padding: 8px 11px;
    border: 1.5px solid var(--bd);
    border-radius: 9px;
    font-family: var(--ff);
    font-size: .8125rem;
    color: var(--tx);
    background: var(--bg2);
    outline: none;
    transition: border-color .2s, box-shadow .2s;
    -webkit-appearance: none;
    margin-bottom: 9px;
}
.qa-input:focus, .qa-select:focus {
    border-color: var(--ac);
    box-shadow: 0 0 0 3px rgba(31,226,144,.1);
}
.qa-row { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.qa-submit {
    width: 100%;
    padding: 9px;
    background: var(--ac);
    color: var(--dk);
    font-family: var(--ff);
    font-size: .8125rem;
    font-weight: 800;
    border: none;
    border-radius: 9px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin-top: 4px;
    transition: background .2s, box-shadow .2s;
    min-height: 36px;
}
.qa-submit:hover { background: var(--ac2); box-shadow: 0 4px 12px rgba(31,226,144,.25); }
.qa-submit svg {
    width: 13px; height: 13px;
    stroke: var(--dk); fill: none;
    stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round;
}

/* ── Save card ── */
.save-card {
    background: var(--dk);
    border-radius: var(--r-lg);
    padding: 1.25rem;
    position: relative;
    overflow: hidden;
}
.save-card-bg-glow {
    position: absolute;
    top: -50px; right: -50px;
    width: 160px; height: 160px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(31,226,144,.08) 0%, transparent 65%);
    pointer-events: none;
}
.save-card-bg-grid {
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(31,226,144,.025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(31,226,144,.025) 1px, transparent 1px);
    background-size: 28px 28px;
    pointer-events: none;
}
.save-card-inner { position: relative; z-index: 1; }
.save-card-title {
    font-size: .9rem;
    font-weight: 800;
    color: #fff;
    margin-bottom: 3px;
    letter-spacing: -.01em;
}
.save-card-sub {
    font-size: .75rem;
    color: rgba(255,255,255,.38);
    line-height: 1.5;
    margin-bottom: 1rem;
}
.save-card-btn {
    width: 100%;
    padding: 10px;
    background: var(--ac);
    color: var(--dk);
    font-family: var(--ff);
    font-size: .875rem;
    font-weight: 800;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: background .2s, box-shadow .2s;
    min-height: 40px;
}
.save-card-btn:hover { background: var(--ac2); box-shadow: 0 6px 18px rgba(31,226,144,.28); }
.save-card-btn svg {
    width: 14px; height: 14px;
    stroke: var(--dk); fill: none;
    stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round;
}
.save-card-nav {
    display: flex;
    justify-content: space-between;
    margin-top: .75rem;
    flex-wrap: wrap;
    gap: 4px;
}
.save-card-nav a {
    font-size: .6875rem;
    font-weight: 600;
    color: rgba(255,255,255,.35);
    transition: color .18s;
}
.save-card-nav a:hover { color: var(--ac); }

/* ─────────────────────────────────────────────
   MODALS
───────────────────────────────────────────── */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(10,20,18,.6);
    backdrop-filter: blur(5px);
    z-index: 900;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    opacity: 0;
    pointer-events: none;
    transition: opacity .28s;
}
.modal-overlay.open { opacity: 1; pointer-events: all; }
.modal {
    background: var(--bg2);
    border-radius: 20px;
    width: 100%;
    max-width: 420px;
    box-shadow: 0 24px 64px rgba(0,0,0,.18);
    overflow: hidden;
    transform: translateY(14px) scale(.97);
    transition: transform .32s cubic-bezier(.16,1,.3,1);
}
.modal-overlay.open .modal { transform: none; }

.modal-header {
    padding: 1.125rem 1.375rem;
    border-bottom: 1px solid var(--bd);
    display: flex;
    align-items: center;
    gap: 10px;
}
.modal-header-title { font-size: .9375rem; font-weight: 800; color: var(--tx); flex: 1; }
.modal-close-btn {
    width: 28px; height: 28px;
    border-radius: 8px;
    border: 1.5px solid var(--bd);
    background: var(--bg2);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    transition: border-color .15s;
}
.modal-close-btn:hover { border-color: var(--err); }
.modal-close-btn svg {
    width: 12px; height: 12px;
    stroke: var(--tx3); fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
}

.modal-body { padding: 1.375rem; }
.modal-label {
    display: block;
    font-size: .8125rem;
    font-weight: 700;
    color: var(--tx2);
    margin-bottom: .375rem;
}
.modal-input, .modal-select {
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
    margin-bottom: 12px;
}
.modal-input:focus, .modal-select:focus {
    border-color: var(--ac);
    box-shadow: 0 0 0 3px rgba(31,226,144,.1);
}
.modal-input:last-child, .modal-select:last-child { margin-bottom: 0; }
.modal-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

.modal-footer {
    padding: 1rem 1.375rem;
    border-top: 1px solid var(--bd);
    display: flex;
    gap: 8px;
}
.modal-btn {
    flex: 1;
    padding: 10px;
    border-radius: 10px;
    font-family: var(--ff);
    font-size: .875rem;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: all .2s;
    min-height: 40px;
}
.modal-btn-primary { background: var(--dk); color: #fff; }
.modal-btn-primary:hover { background: var(--dk2); }
.modal-btn-ghost {
    background: var(--bg);
    color: var(--tx2);
    border: 1.5px solid var(--bd);
}
.modal-btn-ghost:hover { border-color: var(--ac); color: var(--dk); }

/* ─────────────────────────────────────────────
   TOAST
───────────────────────────────────────────── */
.toast {
    position: fixed;
    bottom: 24px; right: 24px;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 11px 16px;
    background: var(--dk);
    color: #fff;
    border-radius: 12px;
    font-size: .875rem;
    font-weight: 700;
    z-index: 1100;
    box-shadow: 0 8px 24px rgba(0,0,0,.15);
    transform: translateY(72px);
    opacity: 0;
    transition: transform .32s cubic-bezier(.16,1,.3,1), opacity .32s;
    pointer-events: none;
    max-width: calc(100vw - 32px);
}
.toast.show { transform: none; opacity: 1; }
.toast svg {
    width: 15px; height: 15px;
    stroke: var(--ac); fill: none;
    stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round;
    flex-shrink: 0;
}
.toast.err svg { stroke: var(--err); }

/* ─────────────────────────────────────────────
   RESPONSIVE
───────────────────────────────────────────── */
@media (max-width: 1024px) {
    .edit-layout { grid-template-columns: 1fr; }
    .right-panel { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px; }
}

@media (max-width: 900px) {
    .main-content { margin-left: 0; padding: 20px var(--pad-sm) 72px; }
}

@media (max-width: 600px) {
    .page-header { flex-direction: column; align-items: flex-start; }
    .header-actions { width: 100%; }
    .hdr-btn { flex: 1; justify-content: center; }
    .qa-row { grid-template-columns: 1fr; }
    .modal-row { grid-template-columns: 1fr; }
    .right-panel { grid-template-columns: 1fr; }
    .task-row { flex-wrap: wrap; }
    .dur-stepper { order: 3; }
}

@media (max-width: 480px) {
    .task-card-header { padding: .875rem 1rem; }
    .task-row { padding: .75rem .875rem; gap: 8px; }
    .dur-stepper { display: none; }
    .toast { bottom: 16px; right: 16px; left: 16px; max-width: unset; }
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
    .toast { bottom: max(24px, env(safe-area-inset-bottom)); }
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
            <h1 class="page-title">Edit Plan — <em><?= date('l', $dayTs) ?></em></h1>
            <p class="page-sub"><?= htmlspecialchars($dayLabel) ?> · Drag to reorder · Click to edit</p>
        </div>
        <div class="header-actions">
            <a href="/schedule/generate.php" class="hdr-btn">
                <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
                Regenerate
            </a>
            <a href="/schedule/setup.php" class="hdr-btn hdr-btn-primary">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                Setup
            </a>
        </div>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-ok sr">
        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-err sr">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <!-- ── Day Picker ── -->
    <div class="day-picker sr d1">
        <?php foreach ($weekDays as $dayKey => $day): ?>
        <a href="/schedule/edit.php?day=<?= $dayKey ?>"
           class="day-tab <?= $dayKey === $requestedDay ? 'active' : '' ?> <?= $day['is_today'] ? 'today' : '' ?> <?= $day['is_past'] ? 'past' : '' ?>"
           <?= $dayKey === $requestedDay ? 'aria-current="page"' : '' ?>>
            <span class="day-tab-dow"><?= $day['label'] ?></span>
            <span class="day-tab-num"><?= $day['num'] ?></span>
            <?php if ($day['is_today']): ?><span class="day-tab-dot"></span><?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ── Two-col layout ── -->
    <div class="edit-layout">

        <!-- ══ TASK LIST ══════════════════════ -->
        <div class="task-card sr d2">
            <div class="task-card-header">
                <div class="task-card-title"><?= $tasksTotal ?> <?= $tasksTotal === 1 ? 'Task' : 'Tasks' ?></div>
                <div class="task-card-meta">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <?= $totalMin ?> min total
                    <span style="color:var(--bd2)">·</span>
                    <?= $tasksDone ?>/<?= $tasksTotal ?> done
                </div>
            </div>

            <?php if (empty($tasks)): ?>
            <div class="task-empty">
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="14" x2="16" y2="14"/><line x1="8" y1="18" x2="13" y2="18"/></svg>
                <h3>No tasks for <?= date('l', $dayTs) ?></h3>
                <p>Add tasks below, or <a href="/schedule/generate.php">regenerate your plan</a>.</p>
            </div>
            <?php else: ?>
            <div class="task-list" id="taskList">
                <?php
                $typeIcons = [
                    'lesson'        => '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>',
                    'quiz'          => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>',
                    'review'        => '<polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/>',
                    'spaced_review' => '<polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/>',
                    'practice_test' => '<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/>',
                    'rest'          => '<path d="M17 7l-1.41 1.41L18.17 10H2v2h16.17l-1.59 1.58L18 15l4-4z"/>',
                ];
                $typeLabels = [
                    'lesson'        => 'Lesson',
                    'quiz'          => 'Quiz',
                    'review'        => 'Review',
                    'spaced_review' => 'Spaced Review',
                    'practice_test' => 'Practice Test',
                    'rest'          => 'Rest',
                ];
                foreach ($tasks as $task):
                    $isDone = (bool)($task['is_complete'] ?? false);
                    $type   = $task['task_type'] ?? 'lesson';
                    $icon   = $typeIcons[$type]  ?? $typeIcons['lesson'];
                    $label  = $typeLabels[$type]  ?? 'Task';
                    $dur    = intval($task['duration_min'] ?? 20);
                    $safeTitle = htmlspecialchars(addslashes($task['title'] ?? ''), ENT_QUOTES);
                ?>
                <div class="task-row t-<?= $type ?> <?= $isDone ? 'done' : '' ?>"
                     data-id="<?= intval($task['id']) ?>"
                     data-dur="<?= $dur ?>"
                     draggable="true">
                    <div class="drag-handle"><span></span><span></span><span></span></div>
                    <div class="task-type-ico">
                        <svg viewBox="0 0 24 24"><?= $icon ?></svg>
                    </div>
                    <div class="task-row-info">
                        <div class="task-row-title"><?= htmlspecialchars($task['title'] ?? 'Task') ?></div>
                        <div class="task-row-chips">
                            <span class="type-chip tc-<?= $type ?>"><?= $label ?></span>
                            <?php if ($isDone): ?>
                            <span class="done-chip">
                                <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                Done
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="dur-stepper">
                        <button class="dur-btn" onclick="changeDur(this, -5)" type="button" aria-label="Decrease duration">−</button>
                        <span class="dur-val"><?= $dur ?>m</span>
                        <button class="dur-btn" onclick="changeDur(this, +5)" type="button" aria-label="Increase duration">+</button>
                    </div>
                    <div class="row-actions">
                        <button class="row-act-btn"
                                onclick="editTask(<?= intval($task['id']) ?>, '<?= $safeTitle ?>', '<?= $type ?>', <?= $dur ?>)"
                                title="Edit task" type="button">
                            <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <button class="row-act-btn del"
                                onclick="deleteTask(<?= intval($task['id']) ?>, this)"
                                title="Delete task" type="button">
                            <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="task-card-footer">
                <button class="add-task-btn" onclick="openModal('addModal')" type="button">
                    <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Task
                </button>
                <span class="footer-hint">Drag rows to reorder</span>
            </div>
        </div>

        <!-- ══ RIGHT PANEL ════════════════════ -->
        <div class="right-panel sr d3">

            <!-- Day summary -->
            <div class="stats-card">
                <div class="stats-card-hd">
                    Day Summary
                    <span class="stats-card-hd-sub"><?= date('D, M j', $dayTs) ?></span>
                </div>
                <div class="stats-card-body">
                    <div class="stat-row">
                        <span class="stat-row-label">
                            <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/></svg>
                            Tasks
                        </span>
                        <span class="stat-row-val" id="statTasks"><?= $tasksDone ?>/<?= $tasksTotal ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-row-label">
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            Study Time
                        </span>
                        <span class="stat-row-val ac" id="statTime"><?= $totalMin ?> min</span>
                    </div>
                    <div class="progress-bar-track">
                        <div class="progress-bar-fill" id="progressFill" style="width:<?= $donePct ?>%"></div>
                    </div>
                    <div class="progress-bar-label" id="progressLabel"><?= $donePct ?>% complete</div>
                </div>
            </div>

            <!-- Quick add -->
            <div class="quick-add-card">
                <div class="qa-hd">Quick Add</div>
                <div class="qa-body">
                    <label class="qa-label" for="qaTitle">Title</label>
                    <input type="text" class="qa-input" id="qaTitle"
                           placeholder="e.g. Linear equations review"
                           maxlength="255" autocomplete="off">
                    <div class="qa-row">
                        <div>
                            <label class="qa-label" for="qaType">Type</label>
                            <select class="qa-select" id="qaType">
                                <?php foreach ($typeOptions as $v => $l): ?>
                                <option value="<?= $v ?>"><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="qa-label" for="qaDuration">Duration</label>
                            <select class="qa-select" id="qaDuration">
                                <?php foreach ([10,15,20,25,30,40,45,60,90] as $m): ?>
                                <option value="<?= $m ?>" <?= $m === 25 ? 'selected' : '' ?>><?= $m ?> min</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button class="qa-submit" onclick="quickAdd()" type="button">
                        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add to Plan
                    </button>
                </div>
            </div>

            <!-- Save card -->
            <div class="save-card">
                <div class="save-card-bg-grid"></div>
                <div class="save-card-bg-glow"></div>
                <div class="save-card-inner">
                    <div class="save-card-title">Save Your Changes</div>
                    <div class="save-card-sub">Fine-tune your study day by reordering tasks or adjusting durations.</div>
                    <button class="save-card-btn" onclick="saveOrder()" type="button">
                        <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        Save Order
                    </button>
                    <div class="save-card-nav">
                        <a href="/schedule/">&larr; Back to schedule</a>
                        <a href="/schedule/generate.php">Regenerate &rarr;</a>
                    </div>
                </div>
            </div>

        </div><!-- /right-panel -->
    </div><!-- /edit-layout -->

</main>

<!-- ══ EDIT TASK MODAL ════════════════════════ -->
<div class="modal-overlay" id="editModal" role="dialog" aria-modal="true" aria-labelledby="editModalTitle">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-header-title" id="editModalTitle">Edit Task</span>
            <button class="modal-close-btn" onclick="closeModal('editModal')" type="button" aria-label="Close">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="editTaskId">
            <label class="modal-label" for="editTitle">Title</label>
            <input type="text" class="modal-input" id="editTitle" placeholder="Task title" maxlength="255">
            <div class="modal-row">
                <div>
                    <label class="modal-label" for="editType">Type</label>
                    <select class="modal-select" id="editType">
                        <?php foreach ($typeOptions as $v => $l): ?>
                        <option value="<?= $v ?>"><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="modal-label" for="editDuration">Duration (min)</label>
                    <input type="number" class="modal-input" id="editDuration" min="5" max="180" step="5" value="25" style="margin-bottom:0">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="modal-btn modal-btn-ghost" onclick="closeModal('editModal')" type="button">Cancel</button>
            <button class="modal-btn modal-btn-primary" onclick="saveEdit()" type="button">Save Changes</button>
        </div>
    </div>
</div>

<!-- ══ ADD TASK MODAL ═════════════════════════ -->
<div class="modal-overlay" id="addModal" role="dialog" aria-modal="true" aria-labelledby="addModalTitle">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-header-title" id="addModalTitle">Add Custom Task</span>
            <button class="modal-close-btn" onclick="closeModal('addModal')" type="button" aria-label="Close">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <label class="modal-label" for="addTitle">Title</label>
            <input type="text" class="modal-input" id="addTitle" placeholder="e.g. Quadratics deep dive" maxlength="255">
            <div class="modal-row">
                <div>
                    <label class="modal-label" for="addType">Type</label>
                    <select class="modal-select" id="addType">
                        <?php foreach ($typeOptions as $v => $l): ?>
                        <option value="<?= $v ?>"><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="modal-label" for="addDuration">Duration</label>
                    <select class="modal-select" id="addDuration" style="margin-bottom:0">
                        <?php foreach ([10,15,20,25,30,40,45,60,90] as $m): ?>
                        <option value="<?= $m ?>" <?= $m === 25 ? 'selected' : '' ?>><?= $m ?> min</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="modal-btn modal-btn-ghost" onclick="closeModal('addModal')" type="button">Cancel</button>
            <button class="modal-btn modal-btn-primary" onclick="confirmAdd()" type="button">Add Task</button>
        </div>
    </div>
</div>

<!-- ══ TOAST ══════════════════════════════════ -->
<div class="toast" id="toast" role="status" aria-live="polite">
    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
    <span id="toastMsg">Saved!</span>
</div>

<script>
(function () {
    'use strict';

    var DAY = '<?= $requestedDay ?>';

    /* ── Scroll reveal ── */
    var srObs = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (e.isIntersecting) { e.target.classList.add('v'); srObs.unobserve(e.target); }
        });
    }, { threshold: .04, rootMargin: '0px 0px -16px 0px' });
    document.querySelectorAll('.sr').forEach(function (el) { srObs.observe(el); });

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

    /* ── Toast ── */
    var toastTimer;
    function toast(msg, isErr) {
        clearTimeout(toastTimer);
        var el = document.getElementById('toast');
        document.getElementById('toastMsg').textContent = msg;
        el.classList.toggle('err', !!isErr);
        el.classList.add('show');
        toastTimer = setTimeout(function () { el.classList.remove('show'); }, 3000);
    }

    /* ── Modal helpers ── */
    window.openModal = function (id) {
        document.getElementById(id).classList.add('open');
        document.body.style.overflow = 'hidden';
        // Focus first input
        var first = document.querySelector('#' + id + ' input, #' + id + ' select');
        if (first) setTimeout(function () { first.focus(); }, 100);
    };
    window.closeModal = function (id) {
        document.getElementById(id).classList.remove('open');
        document.body.style.overflow = '';
    };
    document.querySelectorAll('.modal-overlay').forEach(function (ov) {
        ov.addEventListener('click', function (e) { if (e.target === this) closeModal(this.id); });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.open').forEach(function (m) { closeModal(m.id); });
        }
    });

    /* ── API ── */
    function api(body, cb) {
        fetch('/schedule/edit.php?day=' + DAY, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body),
        })
        .then(function (r) { return r.json(); })
        .then(cb)
        .catch(function () { toast('Network error. Try again.', true); });
    }

    /* ── Sync stats panel ── */
    function syncStats() {
        var rows   = document.querySelectorAll('.task-row');
        var done   = document.querySelectorAll('.task-row.done');
        var total  = 0;
        rows.forEach(function (r) { total += (parseInt(r.dataset.dur) || 0); });

        var el = document.getElementById('statTime');
        if (el) el.textContent = total + ' min';

        var tasksEl = document.getElementById('statTasks');
        if (tasksEl) tasksEl.textContent = done.length + '/' + rows.length;

        var pct   = rows.length > 0 ? Math.round((done.length / rows.length) * 100) : 0;
        var fill  = document.getElementById('progressFill');
        var label = document.getElementById('progressLabel');
        if (fill)  fill.style.width       = pct + '%';
        if (label) label.textContent       = pct + '% complete';
    }

    /* ── Duration stepper ── */
    window.changeDur = function (btn, delta) {
        var row    = btn.closest('.task-row');
        var valEl  = row.querySelector('.dur-val');
        var taskId = parseInt(row.dataset.id);
        var cur    = parseInt(row.dataset.dur) || 20;
        var next   = Math.max(5, Math.min(180, cur + delta));
        row.dataset.dur   = next;
        valEl.textContent = next + 'm';
        api({ action: 'update_task', task_id: taskId, duration_min: next }, function (res) {
            if (res.success) { toast('Duration updated.'); syncStats(); }
            else toast('Failed to update.', true);
        });
    };

    /* ── Edit task ── */
    window.editTask = function (id, title, type, dur) {
        document.getElementById('editTaskId').value    = id;
        document.getElementById('editTitle').value     = title;
        document.getElementById('editType').value      = type;
        document.getElementById('editDuration').value  = dur;
        openModal('editModal');
    };
    window.saveEdit = function () {
        var id    = parseInt(document.getElementById('editTaskId').value);
        var title = document.getElementById('editTitle').value.trim();
        var type  = document.getElementById('editType').value;
        var dur   = parseInt(document.getElementById('editDuration').value) || 25;
        if (!title) { document.getElementById('editTitle').focus(); return; }
        api({ action: 'update_task', task_id: id, title: title, task_type: type, duration_min: dur }, function (res) {
            if (res.success) {
                var row = document.querySelector('[data-id="' + id + '"]');
                if (row) {
                    row.querySelector('.task-row-title').textContent = title;
                    row.querySelector('.dur-val').textContent        = dur + 'm';
                    row.dataset.dur = dur;
                }
                closeModal('editModal');
                toast('Task updated!');
                syncStats();
            } else {
                toast('Failed to save.', true);
            }
        });
    };

    /* ── Delete task ── */
    window.deleteTask = function (id) {
        if (!confirm('Delete this task?')) return;
        api({ action: 'delete_task', task_id: id }, function (res) {
            if (res.success) {
                var row = document.querySelector('[data-id="' + id + '"]');
                if (row) {
                    row.style.transition = 'opacity .28s, transform .28s';
                    row.style.opacity    = '0';
                    row.style.transform  = 'translateX(16px)';
                    setTimeout(function () { row.remove(); syncStats(); }, 300);
                }
                toast('Task deleted.');
            } else {
                toast('Failed to delete.', true);
            }
        });
    };

    /* ── Save order ── */
    window.saveOrder = function () {
        var order = Array.from(document.querySelectorAll('.task-row')).map(function (r) {
            return parseInt(r.dataset.id);
        });
        api({ action: 'reorder', order: order }, function (res) {
            if (res.success) toast('Order saved!');
            else toast('Failed to save order.', true);
        });
    };

    /* ── Shared add helper ── */
    var TYPE_ICONS = {
        lesson:        '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>',
        quiz:          '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>',
        review:        '<polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/>',
        spaced_review: '<polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/>',
        practice_test: '<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/>',
        rest:          '<path d="M17 7l-1.41 1.41L18.17 10H2v2h16.17l-1.59 1.58L18 15l4-4z"/>',
    };
    var TYPE_LABELS = {
        lesson:'Lesson', quiz:'Quiz', review:'Review',
        spaced_review:'Spaced Review', practice_test:'Practice Test', rest:'Rest'
    };

    function addTaskToList(title, type, dur, cb) {
        api({ action: 'add_task', title: title, task_type: type, duration_min: dur }, function (res) {
            if (!res.success || !res.task_id) { toast('Failed to add task.', true); return; }
            var safe  = title.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            var safeQ = title.replace(/'/g, "\\'");
            var icon  = TYPE_ICONS[type] || TYPE_ICONS.lesson;
            var lbl   = TYPE_LABELS[type] || type;
            var html  = '<div class="task-row t-' + type + '" data-id="' + res.task_id + '" data-dur="' + dur + '" draggable="true" style="opacity:0;transform:translateX(-10px)">'
                + '<div class="drag-handle"><span></span><span></span><span></span></div>'
                + '<div class="task-type-ico"><svg viewBox="0 0 24 24">' + icon + '</svg></div>'
                + '<div class="task-row-info">'
                +   '<div class="task-row-title">' + safe + '</div>'
                +   '<div class="task-row-chips"><span class="type-chip tc-' + type + '">' + lbl + '</span></div>'
                + '</div>'
                + '<div class="dur-stepper">'
                +   '<button class="dur-btn" onclick="changeDur(this,-5)" type="button">−</button>'
                +   '<span class="dur-val">' + dur + 'm</span>'
                +   '<button class="dur-btn" onclick="changeDur(this,+5)" type="button">+</button>'
                + '</div>'
                + '<div class="row-actions">'
                +   '<button class="row-act-btn" onclick="editTask(' + res.task_id + ',\'' + safeQ + '\',\'' + type + '\',' + dur + ')" title="Edit" type="button"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>'
                +   '<button class="row-act-btn del" onclick="deleteTask(' + res.task_id + ')" title="Delete" type="button"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg></button>'
                + '</div></div>';

            var list = document.getElementById('taskList');
            if (!list) { location.reload(); return; }
            list.insertAdjacentHTML('beforeend', html);
            var newRow = list.lastElementChild;
            requestAnimationFrame(function () {
                newRow.style.transition = 'opacity .38s cubic-bezier(.16,1,.3,1), transform .38s cubic-bezier(.16,1,.3,1)';
                newRow.style.opacity    = '1';
                newRow.style.transform  = 'none';
            });
            toast('Task added!');
            syncStats();
            if (cb) cb();
        });
    }

    /* ── Quick add ── */
    window.quickAdd = function () {
        var title = document.getElementById('qaTitle').value.trim();
        if (!title) { document.getElementById('qaTitle').focus(); return; }
        addTaskToList(
            title,
            document.getElementById('qaType').value,
            parseInt(document.getElementById('qaDuration').value) || 25,
            function () { document.getElementById('qaTitle').value = ''; }
        );
    };
    document.getElementById('qaTitle').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); quickAdd(); }
    });

    /* ── Add via modal ── */
    window.confirmAdd = function () {
        var title = document.getElementById('addTitle').value.trim();
        if (!title) { document.getElementById('addTitle').focus(); return; }
        addTaskToList(
            title,
            document.getElementById('addType').value,
            parseInt(document.getElementById('addDuration').value) || 25,
            function () { closeModal('addModal'); document.getElementById('addTitle').value = ''; }
        );
    };
    document.getElementById('addTitle').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); confirmAdd(); }
    });

    /* ── Drag & drop ── */
    var dragSrc  = null;
    var dragOver = null;
    var list     = document.getElementById('taskList');

    if (list) {
        list.addEventListener('dragstart', function (e) {
            var row = e.target.closest('.task-row');
            if (!row) return;
            dragSrc = row;
            row.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', '');
        });
        list.addEventListener('dragover', function (e) {
            e.preventDefault();
            var row = e.target.closest('.task-row');
            if (!row || row === dragSrc) return;
            if (dragOver && dragOver !== row) dragOver.classList.remove('drag-over');
            dragOver = row;
            row.classList.add('drag-over');
            var rect = row.getBoundingClientRect();
            var mid  = rect.top + rect.height / 2;
            if (e.clientY < mid) list.insertBefore(dragSrc, row);
            else row.nextSibling ? list.insertBefore(dragSrc, row.nextSibling) : list.appendChild(dragSrc);
        });
        list.addEventListener('dragleave', function (e) {
            var row = e.target.closest('.task-row');
            if (row) row.classList.remove('drag-over');
        });
        list.addEventListener('dragend', function () {
            if (dragSrc) dragSrc.classList.remove('dragging');
            if (dragOver) dragOver.classList.remove('drag-over');
            dragSrc = null; dragOver = null;
            /* Auto-save order after drop */
            var order = Array.from(list.querySelectorAll('.task-row')).map(function (r) {
                return parseInt(r.dataset.id);
            });
            api({ action: 'reorder', order: order }, function (res) {
                if (res.success) toast('Order saved!');
            });
        });
    }

}());
</script>
</body>
</html>