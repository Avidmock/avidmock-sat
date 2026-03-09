<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · Practice Tests — Exam Interface
 *  /practice-tests/exam.php
 *  Real SAT format: RW M1 → RW M2 → 10min break → Math M1 → Math M2
 * ═══════════════════════════════════════════════════════════════════
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
Auth::requireStudent();

$userId = $_SESSION['user_id'];
$user   = User::findById($userId);

$attemptId = (int)($_GET['attempt_id'] ?? 0);
if (!$attemptId) { header('Location: /practice-tests/'); exit; }

$attempt = Database::fetch(
    "SELECT a.*, t.title, t.total_time, t.id AS test_id
     FROM practice_test_attempts a
     JOIN practice_tests t ON t.id = a.test_id
     WHERE a.id = ? AND a.user_id = ?",
    [$attemptId, $userId]
);
if (!$attempt || $attempt['status'] !== 'in_progress') {
    header('Location: /practice-tests/'); exit;
}

// Load sections in order
$sections = Database::fetchAll(
    "SELECT * FROM practice_test_sections WHERE test_id = ? ORDER BY sort_order ASC",
    [$attempt['test_id']]
);

// Build sections with their questions
$sectionData = [];
foreach ($sections as $sec) {
    $qs = Database::fetchAll(
        "SELECT q.*, ptsq.sort_order AS q_order
         FROM practice_test_section_questions ptsq
         JOIN questions q ON q.id = ptsq.question_id
         WHERE ptsq.section_id = ?
         ORDER BY ptsq.sort_order ASC",
        [$sec['id']]
    );
    $sec['questions'] = $qs;
    $sectionData[]    = $sec;
}

// Saved answers
$savedAnswers = [];
$rows = Database::fetchAll(
    "SELECT question_id, user_answer FROM practice_test_answers WHERE attempt_id = ?",
    [$attemptId]
);
foreach ($rows as $r) $savedAnswers[$r['question_id']] = $r['user_answer'];

// Determine current section index from attempt
$currentSectionId  = $attempt['current_section_id'] ?? null;
$currentSectionIdx = 0;
if ($currentSectionId) {
    foreach ($sectionData as $idx => $sec) {
        if ($sec['id'] == $currentSectionId) { $currentSectionIdx = $idx; break; }
    }
}

// Handle test submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_test'])) {
    $timeTaken = (int)($_POST['time_elapsed'] ?? 0);

    $rawRW = $totalRW = $rawMath = $totalMath = 0;
    foreach ($sectionData as $sec) {
        foreach ($sec['questions'] as $q) {
            $given     = trim(strtolower($savedAnswers[$q['id']] ?? ''));
            $correct   = trim(strtolower($q['correct_answer'] ?? ''));
            $isCorrect = $given !== '' && $given === $correct;

            $existing = Database::fetch(
                "SELECT id FROM practice_test_answers WHERE attempt_id=? AND question_id=?",
                [$attemptId, $q['id']]
            );
            $data = ['user_answer' => $savedAnswers[$q['id']] ?? '', 'is_correct' => (int)$isCorrect];
            if ($existing) Database::update('practice_test_answers', $data, ['id' => $existing['id']]);
            else Database::insert('practice_test_answers', array_merge($data, [
                'attempt_id'  => $attemptId,
                'user_id'     => $userId,
                'question_id' => $q['id'],
            ]));

            if ($sec['subject'] === 'reading_writing') { $totalRW++;   if ($isCorrect) $rawRW++;   }
            else                                        { $totalMath++; if ($isCorrect) $rawMath++; }
        }
    }

    $rwPct     = $totalRW   > 0 ? $rawRW   / $totalRW   : 0;
    $mathPct   = $totalMath > 0 ? $rawMath / $totalMath : 0;
    $rwScore   = max(200, min(800, (int) round(round(200 + ($rwPct * 600))   / 10) * 10));
    $mathScore = max(200, min(800, (int) round(round(200 + ($mathPct * 600)) / 10) * 10));
    $totalScore = $rwScore + $mathScore;

    Database::update('practice_test_attempts', [
        'status'       => 'submitted',
        'rw_score'     => $rwScore,
        'math_score'   => $mathScore,
        'total_score'  => $totalScore,
        'time_taken'   => $timeTaken,
        'submitted_at' => date('Y-m-d H:i:s'),
    ], ['id' => $attemptId]);

    StudyStreak::update($userId);
    header("Location: /practice-tests/results.php?attempt_id={$attemptId}");
    exit;
}

$totalSections   = count($sectionData);
$BREAK_AFTER_IDX = 1;
$BREAK_DURATION  = 600;
$BREAK_CIRC      = round(2 * M_PI * 68);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($attempt['title']) ?> &mdash; Avidmock SAT Exam</title>
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700;9..40,800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ─────────────────────────────────────────────
   TOKENS
───────────────────────────────────────────── */
:root {
    --dk:  #0f2420;
    --dk2: #143230;
    --dk3: #1a3f3c;
    --ac:  #1fe290;
    --ac2: #17c87a;
    --math-c:  #6366f1;
    --math-bg: rgba(99,102,241,.08);
    --math-bd: rgba(99,102,241,.2);
    --rw-bg:   rgba(31,226,144,.08);
    --rw-bd:   rgba(31,226,144,.2);
    --tx:  #0f1a18;
    --tx2: #374847;
    --tx3: #7a8f8d;
    --bg:  #f3f7f6;
    --bg2: #ffffff;
    --bd:  #dde8e6;
    --bd2: #ccdbd8;
    --warn: #f59e0b;
    --ok:   #10b981;
    --bar-h: 60px;
    --nav-w: 240px;
    --ff: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    --fm: 'DM Mono', monospace;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { height: 100%; }
body {
    font-family: var(--ff);
    background: var(--bg);
    color: var(--tx);
    height: 100vh;
    overflow: hidden;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}
button { font-family: var(--ff); cursor: pointer; border: none; background: none; }

/* ─────────────────────────────────────────────
   TOP BAR
───────────────────────────────────────────── */
.exam-bar {
    position: fixed;
    top: 0; left: 0; right: 0;
    height: var(--bar-h);
    background: var(--dk2);
    border-bottom: 1px solid rgba(255,255,255,.06);
    display: flex;
    align-items: center;
    padding: 0 20px;
    gap: 12px;
    z-index: 300;
}
.exam-bar-logo {
    display: flex;
    align-items: center;
    gap: 7px;
    flex-shrink: 0;
}
.exam-bar-logo svg {
    width: 20px; height: 20px;
    stroke: var(--ac); fill: none;
    stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round;
    opacity: .75;
}
.exam-bar-logo-text {
    font-size: .625rem;
    font-weight: 800;
    color: rgba(255,255,255,.35);
    text-transform: uppercase;
    letter-spacing: .14em;
}
.exam-bar-sep {
    width: 1px; height: 22px;
    background: rgba(255,255,255,.08);
    flex-shrink: 0;
}
.exam-bar-section { flex: 1; display: flex; align-items: center; gap: 10px; min-width: 0; }

.section-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 8px;
    font-size: .6875rem;
    font-weight: 700;
    letter-spacing: .02em;
    white-space: nowrap;
    flex-shrink: 0;
}
.section-badge-rw   { background: var(--rw-bg);   color: var(--ac);  border: 1px solid var(--rw-bd); }
.section-badge-math { background: var(--math-bg); color: #a5b4fc;    border: 1px solid var(--math-bd); }
.section-badge-dot {
    width: 5px; height: 5px;
    border-radius: 50%;
    background: currentColor;
    flex-shrink: 0;
    animation: dotPulse 2s ease-in-out infinite;
}
@keyframes dotPulse { 0%,100%{opacity:1} 50%{opacity:.18} }

.exam-bar-title {
    font-size: .8125rem;
    font-weight: 500;
    color: rgba(255,255,255,.38);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 240px;
}

/* Timer */
.exam-timer {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 7px 15px;
    background: rgba(255,255,255,.05);
    border: 1px solid rgba(255,255,255,.09);
    border-radius: 10px;
    font-family: var(--fm);
    font-size: 1.0625rem;
    font-weight: 500;
    color: #fff;
    letter-spacing: .05em;
    flex-shrink: 0;
    transition: background .3s, border-color .3s, color .3s;
}
.exam-timer svg {
    width: 14px; height: 14px;
    stroke: rgba(255,255,255,.3); fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
    flex-shrink: 0;
    transition: stroke .3s;
}
.exam-timer.warn   { background: rgba(245,158,11,.1);  border-color: rgba(245,158,11,.25); color: #fcd34d; }
.exam-timer.warn svg { stroke: #fcd34d; }
.exam-timer.danger { background: rgba(239,68,68,.1);   border-color: rgba(239,68,68,.25);  color: #fca5a5; animation: timerPulse 1s ease-in-out infinite; }
.exam-timer.danger svg { stroke: #fca5a5; }
@keyframes timerPulse { 0%,100%{opacity:1} 50%{opacity:.38} }

/* Bar actions */
.bar-actions { display: flex; align-items: center; gap: 7px; flex-shrink: 0; }
.bar-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 13px;
    border-radius: 8px;
    font-size: .6875rem;
    font-weight: 700;
    font-family: var(--ff);
    cursor: pointer;
    border: 1px solid rgba(255,255,255,.09);
    background: rgba(255,255,255,.04);
    color: rgba(255,255,255,.5);
    transition: background .18s, color .18s;
    min-height: 34px;
}
.bar-btn:hover { background: rgba(255,255,255,.1); color: #fff; }
.bar-btn svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.bar-btn-submit { background: var(--ac); color: var(--dk); border-color: transparent; font-size: .75rem; padding: 7px 16px; }
.bar-btn-submit:hover { background: var(--ac2); color: var(--dk); }
.bar-btn-submit svg { stroke: var(--dk); }

/* ─────────────────────────────────────────────
   PROGRESS BAR
───────────────────────────────────────────── */
.exam-progress {
    position: fixed;
    top: var(--bar-h); left: 0; right: 0;
    height: 3px;
    background: rgba(255,255,255,.05);
    z-index: 299;
}
.exam-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--ac2), var(--ac));
    border-radius: 0 2px 2px 0;
    transition: width .55s cubic-bezier(.16,1,.3,1);
}

/* ─────────────────────────────────────────────
   LAYOUT
───────────────────────────────────────────── */
.exam-layout {
    margin-top: calc(var(--bar-h) + 3px);
    display: flex;
    height: calc(100vh - var(--bar-h) - 3px);
}

/* ─────────────────────────────────────────────
   QUESTION SIDEBAR
───────────────────────────────────────────── */
.q-sidebar {
    width: var(--nav-w);
    flex-shrink: 0;
    background: var(--dk2);
    border-right: 1px solid rgba(255,255,255,.06);
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}
.q-sidebar::-webkit-scrollbar { width: 4px; }
.q-sidebar::-webkit-scrollbar-track { background: transparent; }
.q-sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,.09); border-radius: 2px; }

.q-sidebar-section { padding: 16px 14px 8px; }
.q-sidebar-sec-hdr {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: .5625rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .12em;
    color: rgba(255,255,255,.22);
    margin-bottom: 8px;
}
.q-sidebar-sec-hdr svg { width: 10px; height: 10px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

.q-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 4px; margin-bottom: 6px; }
.q-dot {
    aspect-ratio: 1;
    border-radius: 6px;
    font-size: .6875rem;
    font-weight: 700;
    font-family: var(--fm);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all .14s;
    border: 1.5px solid transparent;
    color: rgba(255,255,255,.28);
    background: rgba(255,255,255,.04);
    padding: 0;
}
.q-dot:hover { background: rgba(255,255,255,.1); color: rgba(255,255,255,.8); }
.q-dot.answered      { background: rgba(31,226,144,.13);  color: var(--ac);  border-color: rgba(31,226,144,.22); }
.q-dot.answered-math { background: rgba(99,102,241,.13);  color: #a5b4fc;    border-color: rgba(99,102,241,.22); }
.q-dot.current       { border-color: rgba(255,255,255,.7); color: #fff;       background: rgba(255,255,255,.1); }
.q-dot.flagged       { border-color: var(--warn) !important; color: var(--warn) !important; background: rgba(245,158,11,.08); }

.q-sidebar-legend {
    padding: 12px 14px;
    border-top: 1px solid rgba(255,255,255,.06);
    margin-top: auto;
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.legend-row { display: flex; align-items: center; gap: 7px; font-size: .5625rem; font-weight: 600; color: rgba(255,255,255,.26); }
.legend-swatch { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }

/* ─────────────────────────────────────────────
   MAIN EXAM AREA
───────────────────────────────────────────── */
.exam-main { flex: 1; overflow-y: auto; display: flex; flex-direction: column; position: relative; }
.exam-main::-webkit-scrollbar { width: 6px; }
.exam-main::-webkit-scrollbar-track { background: var(--bg); }
.exam-main::-webkit-scrollbar-thumb { background: var(--bd); border-radius: 3px; }

/* ─────────────────────────────────────────────
   QUESTION BLOCK
───────────────────────────────────────────── */
.q-block { display: none; flex: 1; flex-direction: column; min-height: 100%; }
.q-block.active { display: flex; }

/* Split — passage left, question right */
.q-split { display: grid; grid-template-columns: 1fr 1fr; flex: 1; }

.q-passage-panel {
    overflow-y: auto;
    padding: 32px 28px;
    border-right: 1px solid var(--bd);
    background: var(--bg2);
}
.q-passage-panel::-webkit-scrollbar { width: 4px; }
.q-passage-panel::-webkit-scrollbar-thumb { background: var(--bd); border-radius: 2px; }

.passage-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: .5625rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .12em;
    color: var(--tx3);
    margin-bottom: 16px;
}
.passage-label svg { width: 11px; height: 11px; stroke: var(--tx3); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.passage-label::after { content: ''; flex: 1; height: 1px; background: var(--bd); }
.passage-text { font-size: .875rem; line-height: 1.9; color: var(--tx2); }
.passage-source { font-size: .75rem; color: var(--tx3); font-style: italic; margin-top: 12px; border-top: 1px solid var(--bd); padding-top: 10px; }

/* Full layout — Math / no stimulus */
.q-full-panel { flex: 1; overflow-y: auto; padding: 32px 44px; background: var(--bg); }
.q-question-panel { overflow-y: auto; padding: 32px 28px; background: var(--bg); }
.q-question-panel::-webkit-scrollbar { width: 4px; }
.q-question-panel::-webkit-scrollbar-thumb { background: var(--bd); border-radius: 2px; }

/* Meta row */
.q-meta { display: flex; align-items: center; gap: 7px; margin-bottom: 18px; flex-wrap: wrap; }
.q-counter { font-size: .8125rem; font-weight: 700; color: var(--tx3); }
.q-subject-pill { padding: 3px 9px; border-radius: 6px; font-size: .5625rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; }
.pill-rw   { background: var(--rw-bg);   color: var(--ac2);      border: 1px solid var(--rw-bd); }
.pill-math { background: var(--math-bg); color: var(--math-c);   border: 1px solid var(--math-bd); }
.q-module-pill { font-size: .5625rem; font-weight: 700; color: var(--tx3); padding: 3px 9px; background: var(--bg); border: 1px solid var(--bd); border-radius: 6px; }
.q-calc-pill {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: .5625rem; font-weight: 700; padding: 3px 9px;
    background: rgba(99,102,241,.07); color: #a5b4fc;
    border: 1px solid rgba(99,102,241,.18); border-radius: 6px;
}
.q-calc-pill svg { width: 9px; height: 9px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

/* Flag button */
.q-flag-btn {
    margin-left: auto;
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 11px; border-radius: 7px;
    border: 1.5px solid var(--bd); background: transparent;
    font-size: .6875rem; font-weight: 700; color: var(--tx3);
    cursor: pointer; font-family: var(--ff);
    transition: border-color .18s, color .18s, background .18s;
    min-height: 30px;
}
.q-flag-btn:hover { border-color: var(--warn); color: var(--warn); }
.q-flag-btn.flagged { border-color: var(--warn); color: var(--warn); background: rgba(245,158,11,.06); }
.q-flag-btn svg { width: 11px; height: 11px; stroke: currentColor; fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }

/* Stem box */
.q-stem-box {
    background: var(--bg2);
    border: 1px solid var(--bd);
    border-radius: 14px;
    padding: 20px 24px;
    font-size: .9375rem;
    line-height: 1.75;
    color: var(--tx);
    margin-bottom: 18px;
}
.q-stem-box.stimulus {
    background: var(--bg);
    font-size: .875rem;
    line-height: 1.9;
    color: var(--tx2);
    margin-bottom: 10px;
}

/* Answer choices */
.q-choices-list { display: flex; flex-direction: column; gap: 9px; }
.q-choice {
    display: flex; align-items: flex-start; gap: 13px;
    padding: 12px 16px; border-radius: 11px;
    border: 1.5px solid var(--bd); background: var(--bg2);
    cursor: pointer; transition: border-color .15s, background .15s, transform .15s;
    user-select: none; position: relative;
}
.q-choice:hover { border-color: rgba(31,226,144,.45); background: rgba(31,226,144,.012); transform: translateX(2px); }
.q-choice.selected      { border-color: var(--dk2);    background: rgba(20,50,48,.035); }
.q-choice.selected-math { border-color: var(--math-c); background: rgba(99,102,241,.04); }
.q-choice input { position: absolute; opacity: 0; pointer-events: none; }
.choice-ltr {
    width: 27px; height: 27px; border-radius: 50%;
    border: 1.5px solid var(--bd2);
    display: flex; align-items: center; justify-content: center;
    font-size: .6875rem; font-weight: 800; color: var(--tx3);
    flex-shrink: 0; transition: background .15s, border-color .15s, color .15s;
}
.q-choice.selected      .choice-ltr { background: var(--dk2);    border-color: var(--dk2);    color: #fff; }
.q-choice.selected-math .choice-ltr { background: var(--math-c); border-color: var(--math-c); color: #fff; }
.choice-body { font-size: .875rem; color: var(--tx2); line-height: 1.6; padding-top: 3px; }

/* SPR */
.q-spr-wrap { display: flex; flex-direction: column; gap: 7px; }
.q-spr-label { display: flex; align-items: center; gap: 6px; font-size: .8125rem; font-weight: 700; color: var(--tx3); }
.q-spr-label svg { width: 12px; height: 12px; stroke: var(--tx3); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.q-spr-input {
    width: 200px; padding: 11px 14px;
    border: 1.5px solid var(--bd); border-radius: 10px;
    font-size: .9375rem; font-family: var(--fm); color: var(--tx); background: var(--bg2);
    transition: border-color .2s, box-shadow .2s; outline: none;
}
.q-spr-input:focus { border-color: var(--ac); box-shadow: 0 0 0 3px rgba(31,226,144,.1); }

/* ─────────────────────────────────────────────
   FOOTER NAV
───────────────────────────────────────────── */
.q-footer {
    border-top: 1px solid var(--bd);
    padding: 13px 28px;
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    background: var(--bg2); flex-shrink: 0;
}
.q-footer-info { display: flex; align-items: center; gap: 6px; font-size: .75rem; color: var(--tx3); font-weight: 600; }
.q-footer-info svg { width: 12px; height: 12px; stroke: var(--tx3); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; opacity: .5; }
.q-footer-count { font-weight: 800; color: var(--tx); font-size: .875rem; }
.footer-nav { display: flex; gap: 8px; }

.nav-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 20px; border-radius: 10px;
    border: 1.5px solid var(--bd); background: var(--bg2);
    font-size: .8125rem; font-weight: 700; color: var(--tx2);
    cursor: pointer; transition: border-color .18s, background .18s, color .18s, box-shadow .18s;
    font-family: var(--ff); min-height: 40px;
}
.nav-btn:hover:not(:disabled) { border-color: var(--ac); color: var(--tx); background: rgba(31,226,144,.03); }
.nav-btn:disabled { opacity: .3; cursor: not-allowed; }
.nav-btn svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.nav-btn-primary { background: var(--dk2); color: #fff; border-color: transparent; }
.nav-btn-primary:hover:not(:disabled) { background: var(--dk3); border-color: transparent; box-shadow: 0 4px 14px rgba(20,50,48,.2); }
.nav-btn-primary svg { stroke: var(--ac); }
.nav-btn-math-primary { background: var(--math-c); color: #fff; border-color: transparent; }
.nav-btn-math-primary:hover:not(:disabled) { background: #4f46e5; border-color: transparent; }
.nav-btn-math-primary svg { stroke: #c7d2fe; }

/* ─────────────────────────────────────────────
   SECTION GATE
───────────────────────────────────────────── */
.section-gate {
    display: none; position: absolute; inset: 0;
    background: var(--dk2); z-index: 400;
    align-items: center; justify-content: center;
}
.section-gate.show { display: flex; }

.gate-inner {
    background: rgba(255,255,255,.03);
    border: 1px solid rgba(255,255,255,.07);
    border-radius: 24px; padding: 48px 56px;
    max-width: 520px; width: calc(100% - 32px);
    text-align: center;
    animation: gateIn .45s cubic-bezier(.16,1,.3,1);
}
@keyframes gateIn { from { opacity:0; transform: scale(.96) translateY(10px); } to { opacity:1; transform:none; } }

.gate-ico {
    width: 60px; height: 60px; border-radius: 17px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 20px; flex-shrink: 0;
}
.gate-ico svg { width: 28px; height: 28px; fill: none; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
.gate-ico-rw   { background: var(--rw-bg);   border: 1px solid var(--rw-bd); }
.gate-ico-rw   svg { stroke: var(--ac); }
.gate-ico-math { background: var(--math-bg); border: 1px solid var(--math-bd); }
.gate-ico-math svg { stroke: #a5b4fc; }

.gate-title { font-size: clamp(1.25rem, 3vw, 1.5rem); font-weight: 800; color: #fff; letter-spacing: -.03em; margin-bottom: 8px; line-height: 1.15; }
.gate-sub   { font-size: .9375rem; color: rgba(255,255,255,.38); line-height: 1.65; margin-bottom: 28px; }
.gate-stats { display: flex; gap: 10px; justify-content: center; margin-bottom: 32px; flex-wrap: wrap; }
.gate-stat  { padding: 13px 22px; border-radius: 12px; border: 1px solid rgba(255,255,255,.08); background: rgba(255,255,255,.04); text-align: center; min-width: 86px; }
.gate-stat-val { font-size: 1.375rem; font-weight: 800; color: #fff; letter-spacing: -.03em; line-height: 1; }
.gate-stat-lbl { font-size: .5rem; color: rgba(255,255,255,.3); margin-top: 4px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; }

.gate-btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 13px 32px; border-radius: 13px; font-size: .9375rem; font-weight: 800;
    border: none; cursor: pointer; font-family: var(--ff);
    transition: background .2s, transform .2s, box-shadow .2s;
    letter-spacing: -.01em; min-height: 50px;
}
.gate-btn svg { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }
.gate-btn-rw   { background: var(--ac);    color: var(--dk); }
.gate-btn-rw:hover   { background: var(--ac2);  transform: translateY(-2px); box-shadow: 0 8px 24px rgba(31,226,144,.25); }
.gate-btn-math { background: var(--math-c); color: #fff; }
.gate-btn-math:hover { background: #4f46e5; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(99,102,241,.3); }

/* ─────────────────────────────────────────────
   BREAK SCREEN
───────────────────────────────────────────── */
.break-screen {
    display: none; position: absolute; inset: 0;
    background: var(--dk); z-index: 400;
    align-items: center; justify-content: center;
}
.break-screen.show { display: flex; }

.break-inner { text-align: center; max-width: 480px; padding: 0 24px; animation: gateIn .5s cubic-bezier(.16,1,.3,1); }

.break-ico {
    width: 68px; height: 68px; border-radius: 20px;
    background: rgba(31,226,144,.1); border: 1px solid rgba(31,226,144,.18);
    display: flex; align-items: center; justify-content: center; margin: 0 auto 22px;
}
.break-ico svg { width: 32px; height: 32px; stroke: var(--ac); fill: none; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }

.break-title { font-size: clamp(1.625rem, 4vw, 2rem); font-weight: 800; color: #fff; letter-spacing: -.04em; margin-bottom: 10px; }
.break-sub   { font-size: 1rem; color: rgba(255,255,255,.4); line-height: 1.65; margin-bottom: 36px; max-width: 380px; margin-left: auto; margin-right: auto; }

.break-ring { position: relative; width: 160px; height: 160px; margin: 0 auto 28px; }
.break-ring svg { transform: rotate(-90deg); }
.break-ring-val { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; }
.break-ring-num { font-size: 2.25rem; font-weight: 800; color: #fff; letter-spacing: -.05em; font-family: var(--fm); line-height: 1; }
.break-ring-lbl { font-size: .5rem; color: rgba(255,255,255,.3); font-weight: 700; text-transform: uppercase; letter-spacing: .1em; }

.break-actions { display: flex; flex-direction: column; align-items: center; gap: 10px; }
.break-btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 13px 30px; border-radius: 13px;
    background: var(--ac); color: var(--dk); font-size: .9375rem; font-weight: 800;
    border: none; cursor: pointer; font-family: var(--ff);
    transition: background .18s, transform .18s, box-shadow .18s; min-height: 48px;
}
.break-btn:hover { background: var(--ac2); transform: translateY(-2px); box-shadow: 0 8px 22px rgba(31,226,144,.25); }
.break-btn svg { width: 14px; height: 14px; stroke: var(--dk); fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }
.break-skip-note { font-size: .75rem; color: rgba(255,255,255,.2); font-weight: 600; }

/* ─────────────────────────────────────────────
   SUBMIT MODAL
───────────────────────────────────────────── */
.modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.6); z-index: 600;
    align-items: center; justify-content: center; padding: 20px;
    backdrop-filter: blur(5px);
}
.modal-overlay.show { display: flex; }
.modal-box {
    background: var(--bg2); border-radius: 22px; padding: 36px;
    max-width: 460px; width: 100%;
    box-shadow: 0 32px 80px rgba(0,0,0,.22);
    animation: gateIn .3s cubic-bezier(.16,1,.3,1);
}
.modal-header { display: flex; align-items: flex-start; gap: 14px; margin-bottom: 20px; }
.modal-hdr-ico {
    width: 44px; height: 44px; border-radius: 12px;
    background: rgba(20,50,48,.07); border: 1px solid var(--bd);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.modal-hdr-ico svg { width: 20px; height: 20px; stroke: var(--dk2); fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.modal-title { font-size: 1.125rem; font-weight: 800; color: var(--tx); letter-spacing: -.025em; margin-bottom: 4px; line-height: 1.2; }
.modal-sub   { font-size: .8125rem; color: var(--tx3); line-height: 1.6; }
.modal-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 22px; }
.modal-stat  { background: var(--bg); border-radius: 11px; padding: 13px 10px; text-align: center; border: 1px solid var(--bd); }
.modal-stat-val { font-size: 1.5rem; font-weight: 800; color: var(--tx); letter-spacing: -.04em; line-height: 1; }
.modal-stat-val.ok   { color: var(--ok); }
.modal-stat-val.warn { color: var(--warn); }
.modal-stat-lbl { font-size: .5rem; color: var(--tx3); margin-top: 4px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; }
.modal-actions { display: flex; gap: 8px; }
.modal-btn { flex: 1; padding: 12px; border-radius: 11px; font-size: .875rem; font-weight: 700; cursor: pointer; border: none; font-family: var(--ff); transition: all .18s; min-height: 44px; }
.modal-btn-cancel { background: var(--bg); color: var(--tx2); border: 1.5px solid var(--bd); }
.modal-btn-cancel:hover { background: var(--bd); }
.modal-btn-submit { background: var(--dk2); color: #fff; }
.modal-btn-submit:hover { background: var(--dk3); }

/* ─────────────────────────────────────────────
   MOBILE SIDEBAR TOGGLE
───────────────────────────────────────────── */
.mob-q-toggle {
    position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
    background: var(--dk2); color: rgba(255,255,255,.7);
    border: 1px solid rgba(255,255,255,.1); border-radius: 40px;
    padding: 10px 22px; font-size: .8125rem; font-weight: 700; font-family: var(--ff);
    cursor: pointer; z-index: 200; box-shadow: 0 4px 18px rgba(0,0,0,.35);
    display: none; align-items: center; gap: 7px; min-height: 44px;
}
.mob-q-toggle svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

/* ─────────────────────────────────────────────
   RESPONSIVE
───────────────────────────────────────────── */
@media (max-width: 900px) {
    :root { --nav-w: 200px; }
    .q-sidebar {
        position: fixed; bottom: 0; left: 0; right: 0;
        width: 100%; height: auto; max-height: 55vh; top: auto;
        border-right: none; border-top: 1px solid rgba(255,255,255,.07);
        border-radius: 18px 18px 0 0;
        transform: translateY(100%); transition: transform .3s cubic-bezier(.16,1,.3,1);
        z-index: 250;
    }
    .q-sidebar.open { transform: translateY(0); }
    .exam-main { width: 100%; }
    .mob-q-toggle { display: flex; }
    .q-split { grid-template-columns: 1fr; }
    .q-passage-panel { max-height: 35vh; border-right: none; border-bottom: 1px solid var(--bd); }
}
@media (max-width: 600px) {
    .exam-bar { padding: 0 12px; gap: 8px; }
    .exam-bar-title { display: none; }
    .exam-bar-logo-text { display: none; }
    .q-full-panel, .q-question-panel { padding: 18px 14px; }
    .q-footer { padding: 10px 14px; }
    .gate-inner { padding: 32px 22px; }
    .gate-title { font-size: 1.25rem; }
    .break-title { font-size: 1.5rem; }
    .break-sub { font-size: .875rem; }
}
@supports (padding: max(0px)) {
    .q-full-panel, .q-question-panel {
        padding-bottom: max(32px, calc(32px + env(safe-area-inset-bottom)));
    }
}
</style>
</head>
<body>

<!-- ── TOP BAR ── -->
<div class="exam-bar" id="examBar">
    <div class="exam-bar-logo">
        <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
        <span class="exam-bar-logo-text">Avidmock SAT</span>
    </div>
    <div class="exam-bar-sep"></div>
    <div class="exam-bar-section" id="barSection"><!-- filled by JS --></div>
    <div class="exam-bar-title"><?= htmlspecialchars($attempt['title']) ?></div>
    <div class="exam-timer" id="examTimer">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <span id="timerDisplay">—:——</span>
    </div>
    <div class="bar-actions">
        <button class="bar-btn bar-btn-submit" onclick="showSubmitModal()" type="button">
            <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
            Submit Test
        </button>
    </div>
</div>

<!-- ── PROGRESS ── -->
<div class="exam-progress">
    <div class="exam-progress-fill" id="progressFill" style="width:0%"></div>
</div>

<div class="exam-layout">

    <!-- ── SIDEBAR ── -->
    <div class="q-sidebar" id="qSidebar">
        <?php
        $globalIdx = 0;
        foreach ($sectionData as $secIdx => $sec):
            $isMath = str_contains(strtolower($sec['subject'] ?? ''), 'math');
        ?>
        <div class="q-sidebar-section">
            <div class="q-sidebar-sec-hdr">
                <?php if ($isMath): ?>
                <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                <?php else: ?>
                <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
                <?php endif; ?>
                <?= htmlspecialchars($sec['title'] ?? 'Section') ?>
            </div>
            <div class="q-grid">
                <?php foreach ($sec['questions'] as $qi => $q):
                    $isAnswered    = isset($savedAnswers[$q['id']]) && $savedAnswers[$q['id']] !== '';
                    $answeredClass = $isAnswered ? ($isMath ? 'answered-math' : 'answered') : '';
                    $globalIdx++;
                ?>
                <button class="q-dot <?= $answeredClass ?>"
                        id="qdot-<?= $q['id'] ?>"
                        data-sec="<?= $secIdx ?>"
                        data-qi="<?= $qi ?>"
                        onclick="goToQuestion(<?= $secIdx ?>, <?= $qi ?>)"
                        type="button"><?= $globalIdx ?></button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="q-sidebar-legend">
            <div class="legend-row"><div class="legend-swatch" style="background:rgba(31,226,144,.15);border:1px solid rgba(31,226,144,.28)"></div>R&amp;W Answered</div>
            <div class="legend-row"><div class="legend-swatch" style="background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.28)"></div>Math Answered</div>
            <div class="legend-row"><div class="legend-swatch" style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.38)"></div>Flagged</div>
            <div class="legend-row"><div class="legend-swatch" style="background:rgba(255,255,255,.1);border:1.5px solid rgba(255,255,255,.65)"></div>Current</div>
        </div>
    </div>

    <!-- ── MAIN AREA ── -->
    <div class="exam-main" id="examMain">

        <form id="examForm" method="POST" style="display:contents">
            <input type="hidden" name="submit_test"  value="1">
            <input type="hidden" name="time_elapsed" id="timeElapsed" value="0">

            <?php foreach ($sectionData as $secIdx => $sec):
                $isMath     = str_contains(strtolower($sec['subject'] ?? ''), 'math');
                $module     = $sec['module'] ?? 1;
                $subjectLbl = $isMath ? 'Math' : 'R&amp;W';
                $pillClass  = $isMath ? 'pill-math' : 'pill-rw';
                $selClass   = $isMath ? 'selected-math' : 'selected';
                $navClass   = $isMath ? 'nav-btn-math-primary' : 'nav-btn-primary';
                $choices    = ['A','B','C','D'];
                $qCount     = count($sec['questions']);

                foreach ($sec['questions'] as $qi => $q):
                    $saved       = $savedAnswers[$q['id']] ?? '';
                    $hasStimulus = !empty($q['stimulus']) && $q['stimulus_type'] !== 'none';
                    $choiceTxts  = [
                        $q['choice_a'] ?? null,
                        $q['choice_b'] ?? null,
                        $q['choice_c'] ?? null,
                        $q['choice_d'] ?? null,
                    ];
                    $isSPR = $q['question_type'] === 'spr' || (empty($choiceTxts[0]) && !empty($q['stem']));
            ?>
            <div class="q-block" id="qblock-<?= $secIdx ?>-<?= $qi ?>"
                 data-sec="<?= $secIdx ?>" data-qi="<?= $qi ?>">

                <?php if ($hasStimulus && !$isMath): ?>
                <!-- ── SPLIT LAYOUT — R&W with passage ── -->
                <div class="q-split">
                    <div class="q-passage-panel">
                        <div class="passage-label">
                            <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
                            Passage
                        </div>
                        <div class="passage-text"><?= nl2br(htmlspecialchars($q['stimulus'])) ?></div>
                    </div>
                    <div class="q-question-panel">
                        <div class="q-meta">
                            <span class="q-counter">Q<?= $qi + 1 ?> of <?= $qCount ?></span>
                            <span class="q-subject-pill <?= $pillClass ?>"><?= $subjectLbl ?></span>
                            <span class="q-module-pill">Module <?= $module ?></span>
                            <button type="button" class="q-flag-btn" id="flagbtn-<?= $q['id'] ?>" onclick="toggleFlag(<?= $q['id'] ?>)">
                                <svg viewBox="0 0 24 24"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
                                Flag
                            </button>
                        </div>
                        <div class="q-stem-box"><?= nl2br(htmlspecialchars($q['stem'])) ?></div>
                        <?php if (!$isSPR): ?>
                        <div class="q-choices-list" id="choices-<?= $q['id'] ?>">
                            <?php foreach ($choices as $ci => $letter):
                                if (empty($choiceTxts[$ci])) continue;
                                $isSel = ($saved === $letter);
                            ?>
                            <label class="q-choice <?= $isSel ? $selClass : '' ?>"
                                   onclick="selectChoice(this,'<?= $letter ?>',<?= $q['id'] ?>,'<?= $selClass ?>')">
                                <input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $letter ?>" <?= $isSel ? 'checked' : '' ?>>
                                <div class="choice-ltr"><?= $letter ?></div>
                                <div class="choice-body"><?= htmlspecialchars($choiceTxts[$ci]) ?></div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="q-spr-wrap">
                            <div class="q-spr-label">
                                <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                Your Answer
                            </div>
                            <input class="q-spr-input" type="text"
                                   name="answers[<?= $q['id'] ?>]"
                                   value="<?= htmlspecialchars($saved) ?>"
                                   placeholder="Enter answer…"
                                   autocomplete="off"
                                   oninput="sprInput(<?= $q['id'] ?>, this.value)">
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php else: ?>
                <!-- ── FULL LAYOUT — Math / no stimulus ── -->
                <div class="q-full-panel">
                    <div class="q-meta">
                        <span class="q-counter">Q<?= $qi + 1 ?> of <?= $qCount ?></span>
                        <span class="q-subject-pill <?= $pillClass ?>"><?= $subjectLbl ?></span>
                        <span class="q-module-pill">Module <?= $module ?></span>
                        <?php if ($isMath && $module == 2): ?>
                        <span class="q-calc-pill">
                            <svg viewBox="0 0 24 24"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="12" x2="10" y2="12"/><line x1="12" y1="12" x2="14" y2="12"/><line x1="8" y1="16" x2="10" y2="16"/><line x1="12" y1="16" x2="14" y2="16"/><line x1="16" y1="16" x2="16" y2="18"/></svg>
                            Calculator OK
                        </span>
                        <?php endif; ?>
                        <button type="button" class="q-flag-btn" id="flagbtn-<?= $q['id'] ?>" onclick="toggleFlag(<?= $q['id'] ?>)">
                            <svg viewBox="0 0 24 24"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
                            Flag
                        </button>
                    </div>
                    <?php if ($hasStimulus): ?>
                    <div class="q-stem-box stimulus"><?= nl2br(htmlspecialchars($q['stimulus'])) ?></div>
                    <?php endif; ?>
                    <div class="q-stem-box"><?= nl2br(htmlspecialchars($q['stem'])) ?></div>
                    <?php if (!$isSPR): ?>
                    <div class="q-choices-list" id="choices-<?= $q['id'] ?>">
                        <?php foreach ($choices as $ci => $letter):
                            if (empty($choiceTxts[$ci])) continue;
                            $isSel = ($saved === $letter);
                        ?>
                        <label class="q-choice <?= $isSel ? $selClass : '' ?>"
                               onclick="selectChoice(this,'<?= $letter ?>',<?= $q['id'] ?>,'<?= $selClass ?>')">
                            <input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $letter ?>" <?= $isSel ? 'checked' : '' ?>>
                            <div class="choice-ltr"><?= $letter ?></div>
                            <div class="choice-body"><?= htmlspecialchars($choiceTxts[$ci]) ?></div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="q-spr-wrap">
                        <div class="q-spr-label">
                            <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            Your Answer
                        </div>
                        <input class="q-spr-input" type="text"
                               name="answers[<?= $q['id'] ?>]"
                               value="<?= htmlspecialchars($saved) ?>"
                               placeholder="Enter answer…"
                               autocomplete="off"
                               oninput="sprInput(<?= $q['id'] ?>, this.value)">
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- ── FOOTER NAV ── -->
                <div class="q-footer">
                    <div class="q-footer-info">
                        <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/></svg>
                        <span class="q-footer-count" id="footer-count-<?= $secIdx ?>">0/<?= $qCount ?></span>
                        answered in this section
                    </div>
                    <div class="footer-nav">
                        <button type="button" class="nav-btn"
                                onclick="prevQ(<?= $secIdx ?>, <?= $qi ?>)"
                                <?= $qi === 0 ? 'disabled' : '' ?>>
                            <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                            Back
                        </button>
                        <?php if ($qi < $qCount - 1): ?>
                        <button type="button" class="nav-btn <?= $navClass ?>"
                                onclick="nextQ(<?= $secIdx ?>, <?= $qi ?>)">
                            Next
                            <svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        </button>
                        <?php else: ?>
                        <button type="button" class="nav-btn <?= $navClass ?>"
                                onclick="finishSection(<?= $secIdx ?>)">
                            <?= $secIdx < $totalSections - 1 ? 'Finish Section' : 'Review &amp; Submit' ?>
                            <svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
            <?php endforeach; ?>
            <?php endforeach; ?>
        </form>

        <!-- ── SECTION GATE ── -->
        <div class="section-gate" id="sectionGate">
            <div class="gate-inner" id="gateContent"></div>
        </div>

        <!-- ── BREAK SCREEN ── -->
        <div class="break-screen" id="breakScreen">
            <div class="break-inner">
                <div class="break-ico">
                    <svg viewBox="0 0 24 24"><path d="M17 8h1a4 4 0 010 8h-1"/><path d="M3 8h14v9a4 4 0 01-4 4H7a4 4 0 01-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>
                </div>
                <div class="break-title">10-Minute Break</div>
                <div class="break-sub">You've completed Reading &amp; Writing. Rest your mind before the Math section begins.</div>
                <div class="break-ring">
                    <svg width="160" height="160" viewBox="0 0 160 160">
                        <circle cx="80" cy="80" r="68" fill="none" stroke="rgba(255,255,255,.07)" stroke-width="8"/>
                        <circle id="breakRingPath" cx="80" cy="80" r="68" fill="none"
                                stroke="url(#bkGrad)" stroke-width="8"
                                stroke-dasharray="<?= $BREAK_CIRC ?>"
                                stroke-dashoffset="0" stroke-linecap="round"
                                style="transform-origin:center;transform:rotate(-90deg)"/>
                        <defs>
                            <linearGradient id="bkGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" style="stop-color:#17c87a"/>
                                <stop offset="100%" style="stop-color:#1fe290"/>
                            </linearGradient>
                        </defs>
                    </svg>
                    <div class="break-ring-val">
                        <div class="break-ring-num" id="breakTimerNum">10:00</div>
                        <div class="break-ring-lbl">remaining</div>
                    </div>
                </div>
                <div class="break-actions">
                    <button class="break-btn" onclick="endBreak()" type="button">
                        Start Math Early
                        <svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </button>
                    <div class="break-skip-note">Break ends automatically in <span id="breakSkipTime">10:00</span></div>
                </div>
            </div>
        </div>

    </div><!-- /exam-main -->
</div><!-- /exam-layout -->

<!-- ── SUBMIT MODAL ── -->
<div class="modal-overlay" id="submitModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-hdr-ico">
                <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
            </div>
            <div>
                <div class="modal-title">Submit Test?</div>
                <div class="modal-sub">Once submitted you cannot change your answers. Review flagged questions before submitting.</div>
            </div>
        </div>
        <div class="modal-stats">
            <div class="modal-stat"><div class="modal-stat-val ok"   id="modAnswered">0</div><div class="modal-stat-lbl">Answered</div></div>
            <div class="modal-stat"><div class="modal-stat-val warn" id="modUnanswered">0</div><div class="modal-stat-lbl">Unanswered</div></div>
            <div class="modal-stat"><div class="modal-stat-val"      id="modFlagged">0</div><div class="modal-stat-lbl">Flagged</div></div>
        </div>
        <div class="modal-actions">
            <button class="modal-btn modal-btn-cancel" onclick="hideSubmitModal()" type="button">Keep Working</button>
            <button class="modal-btn modal-btn-submit" onclick="doSubmit()"        type="button">Submit Now</button>
        </div>
    </div>
</div>

<!-- ── MOBILE SIDEBAR TOGGLE ── -->
<button class="mob-q-toggle" id="mobQToggle" onclick="toggleSidebar()" type="button">
    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="4" x2="8" y2="10"/></svg>
    Questions
</button>

<script>
(function () {
'use strict';

/* ── DATA ──────────────────────────────────────── */
var SECTIONS = <?= json_encode(array_map(function ($s) {
    return [
        'id'        => $s['id'],
        'title'     => $s['title'],
        'subject'   => $s['subject'],
        'module'    => $s['module'] ?? 1,
        'timeLimit' => (int)($s['time_limit'] ?? 1920),
        'questions' => array_column($s['questions'], 'id'),
    ];
}, $sectionData)) ?>;

var BREAK_AFTER    = <?= $BREAK_AFTER_IDX ?>;
var BREAK_DUR      = <?= $BREAK_DURATION ?>;
var ATTEMPT_ID     = <?= $attemptId ?>;
var BREAK_CIRC     = <?= $BREAK_CIRC ?>;
var savedAnswers   = <?= json_encode($savedAnswers) ?>;
var flagged        = {};
var elapsed        = 0;
var curSec         = <?= $currentSectionIdx ?>;
var curQi          = 0;
var secTimer       = null;
var breakTimer     = null;
var secRemaining   = 0;
var breakRemaining = BREAK_DUR;

/* ── TIMER ─────────────────────────────────────── */
var timerEl   = document.getElementById('examTimer');
var timerDisp = document.getElementById('timerDisplay');
var timeInput = document.getElementById('timeElapsed');

function fmt(s) {
    var m = Math.floor(s / 60), ss = s % 60;
    return String(m).padStart(2, '0') + ':' + String(ss).padStart(2, '0');
}

function startSectionTimer(secs) {
    clearInterval(secTimer);
    secRemaining = secs;
    timerDisp.textContent = fmt(secRemaining);
    timerEl.className = 'exam-timer';
    secTimer = setInterval(function () {
        elapsed++;
        secRemaining--;
        timeInput.value = elapsed;
        timerDisp.textContent = fmt(secRemaining);
        if (secRemaining <= 300 && secRemaining > 60) timerEl.className = 'exam-timer warn';
        if (secRemaining <= 60)  timerEl.className = 'exam-timer danger';
        if (secRemaining <= 0)   { clearInterval(secTimer); autoAdvance(); }
    }, 1000);
}

function autoAdvance() {
    if (curSec < SECTIONS.length - 1) finishSection(curSec);
    else showSubmitModal();
}

/* ── NAVIGATION ────────────────────────────────── */
function showBlock(si, qi) {
    document.querySelectorAll('.q-block').forEach(function (b) { b.classList.remove('active'); });
    var block = document.getElementById('qblock-' + si + '-' + qi);
    if (block) block.classList.add('active');

    document.querySelectorAll('.q-dot').forEach(function (d) { d.classList.remove('current'); });
    var qIds = SECTIONS[si] ? SECTIONS[si].questions : [];
    if (qIds[qi]) {
        var dot = document.getElementById('qdot-' + qIds[qi]);
        if (dot) dot.classList.add('current');
    }
    updateBarSection(si);
    updateProgress();
    updateFooterCount(si);
}

window.nextQ = function (si, qi) {
    var sec = SECTIONS[si]; if (!sec) return;
    if (qi < sec.questions.length - 1) { curQi = qi + 1; showBlock(si, curQi); }
};
window.prevQ = function (si, qi) {
    if (qi > 0) { curQi = qi - 1; showBlock(si, curQi); }
};
window.goToQuestion = function (si, qi) {
    if (si !== curSec) return;
    curQi = qi;
    showBlock(si, qi);
    closeSidebar();
};

/* ── SECTION TRANSITIONS ───────────────────────── */
window.finishSection = function (si) {
    clearInterval(secTimer);
    curSec = si;
    var nextSi = si + 1;
    if (nextSi >= SECTIONS.length) { showSubmitModal(); return; }
    if (si === BREAK_AFTER)         { showBreak(nextSi); return; }
    showSectionGate(nextSi);
};

function isMathSec(sec) { return sec && sec.subject.indexOf('math') > -1; }

function showSectionGate(nextSi) {
    var sec  = SECTIONS[nextSi];
    var math = isMathSec(sec);
    var gc   = document.getElementById('gateContent');

    var icoHtml = math
        ? '<div class="gate-ico gate-ico-math"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div>'
        : '<div class="gate-ico gate-ico-rw"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg></div>';

    gc.innerHTML =
        icoHtml +
        '<div class="gate-title">' + sec.title + '</div>' +
        '<div class="gate-sub">Module ' + sec.module + ' &middot; ' + Math.round(sec.timeLimit / 60) + ' minutes &middot; ' + sec.questions.length + ' questions</div>' +
        '<div class="gate-stats">' +
            '<div class="gate-stat"><div class="gate-stat-val">' + sec.questions.length + '</div><div class="gate-stat-lbl">Questions</div></div>' +
            '<div class="gate-stat"><div class="gate-stat-val">' + Math.round(sec.timeLimit / 60) + 'm</div><div class="gate-stat-lbl">Time Limit</div></div>' +
            '<div class="gate-stat"><div class="gate-stat-val">M' + sec.module + '</div><div class="gate-stat-lbl">Module</div></div>' +
        '</div>' +
        '<button class="gate-btn ' + (math ? 'gate-btn-math' : 'gate-btn-rw') + '" onclick="startSection(' + nextSi + ')" type="button">' +
            'Begin Module ' + sec.module +
            '<svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>' +
        '</button>';

    document.getElementById('sectionGate').classList.add('show');
}

window.startSection = function (si) {
    document.getElementById('sectionGate').classList.remove('show');
    curSec = si; curQi = 0;
    showBlock(si, 0);
    startSectionTimer(SECTIONS[si].timeLimit);
    fetch('/api/save-practice-answer.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ attempt_id: ATTEMPT_ID, action: 'update_section', section_id: SECTIONS[si].id }),
    }).catch(function () {});
};

/* ── BREAK ─────────────────────────────────────── */
function showBreak(nextSi) {
    var bs = document.getElementById('breakScreen');
    bs.classList.add('show');
    bs.dataset.next = nextSi;
    breakRemaining = BREAK_DUR;
    updateBreakDisplay();
    breakTimer = setInterval(function () {
        breakRemaining--;
        updateBreakDisplay();
        if (breakRemaining <= 0) { clearInterval(breakTimer); endBreak(); }
    }, 1000);
}

function updateBreakDisplay() {
    var t = fmt(breakRemaining);
    document.getElementById('breakTimerNum').textContent = t;
    document.getElementById('breakSkipTime').textContent = t;
    var offset = BREAK_CIRC * (1 - breakRemaining / BREAK_DUR);
    document.getElementById('breakRingPath').style.strokeDashoffset = offset;
}

window.endBreak = function () {
    clearInterval(breakTimer);
    var bs = document.getElementById('breakScreen');
    bs.classList.remove('show');
    showSectionGate(parseInt(bs.dataset.next || '2', 10));
};

/* ── ANSWER HANDLING ───────────────────────────── */
window.selectChoice = function (label, letter, qId, selClass) {
    var list = label.closest('.q-choices-list');
    list.querySelectorAll('.q-choice').forEach(function (c) {
        c.classList.remove('selected', 'selected-math');
        c.querySelector('input').checked = false;
    });
    label.classList.add(selClass);
    label.querySelector('input').checked = true;
    savedAnswers[qId] = letter;
    updateDot(qId, true);
    updateProgress();
    updateFooterCount(curSec);
    autoSave(qId, letter);
};

window.sprInput = function (qId, val) {
    savedAnswers[qId] = val;
    updateDot(qId, val.trim() !== '');
    updateProgress();
    updateFooterCount(curSec);
    clearTimeout(window._sprT);
    window._sprT = setTimeout(function () { autoSave(qId, val); }, 700);
};

function updateDot(qId, answered) {
    var dot = document.getElementById('qdot-' + qId);
    if (!dot) return;
    var si   = parseInt(dot.dataset.sec, 10);
    var math = isMathSec(SECTIONS[si]);
    dot.classList.toggle('answered',      answered && !math);
    dot.classList.toggle('answered-math', answered &&  math);
}

/* ── FLAG ──────────────────────────────────────── */
window.toggleFlag = function (qId) {
    flagged[qId] = !flagged[qId];
    var btn = document.getElementById('flagbtn-' + qId);
    var dot = document.getElementById('qdot-'    + qId);
    if (btn) btn.classList.toggle('flagged', !!flagged[qId]);
    if (dot) dot.classList.toggle('flagged', !!flagged[qId]);
};

/* ── PROGRESS ──────────────────────────────────── */
function updateProgress() {
    var total = 0, answered = 0;
    SECTIONS.forEach(function (sec) {
        sec.questions.forEach(function (qId) {
            total++;
            if (savedAnswers[qId] && String(savedAnswers[qId]).trim()) answered++;
        });
    });
    var pct = total > 0 ? Math.round(answered / total * 100) : 0;
    document.getElementById('progressFill').style.width = pct + '%';
}

function updateFooterCount(si) {
    var el = document.getElementById('footer-count-' + si);
    if (!el || !SECTIONS[si]) return;
    var answered = SECTIONS[si].questions.filter(function (qId) {
        return savedAnswers[qId] && String(savedAnswers[qId]).trim();
    }).length;
    el.textContent = answered + '/' + SECTIONS[si].questions.length;
}

/* ── BAR BADGE ─────────────────────────────────── */
function updateBarSection(si) {
    var el = document.getElementById('barSection');
    if (!SECTIONS[si]) { el.innerHTML = ''; return; }
    var sec  = SECTIONS[si];
    var math = isMathSec(sec);
    var cls  = math ? 'section-badge-math' : 'section-badge-rw';
    el.innerHTML =
        '<div class="section-badge ' + cls + '">' +
            '<div class="section-badge-dot"></div>' +
            sec.title +
        '</div>';
}

/* ── AUTO-SAVE ─────────────────────────────────── */
function autoSave(qId, answer) {
    fetch('/api/save-practice-answer.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ attempt_id: ATTEMPT_ID, question_id: qId, answer: answer }),
    }).catch(function () {});
}

/* ── SUBMIT MODAL ──────────────────────────────── */
window.showSubmitModal = function () {
    var total = 0, answered = 0;
    var fl = Object.values(flagged).filter(Boolean).length;
    SECTIONS.forEach(function (sec) {
        sec.questions.forEach(function (qId) {
            total++;
            if (savedAnswers[qId] && String(savedAnswers[qId]).trim()) answered++;
        });
    });
    document.getElementById('modAnswered').textContent   = answered;
    document.getElementById('modUnanswered').textContent = total - answered;
    document.getElementById('modFlagged').textContent    = fl;
    document.getElementById('submitModal').classList.add('show');
};
window.hideSubmitModal = function () { document.getElementById('submitModal').classList.remove('show'); };
window.doSubmit = function () {
    clearInterval(secTimer);
    timeInput.value = elapsed;
    document.getElementById('examForm').submit();
};

/* ── SIDEBAR ───────────────────────────────────── */
window.toggleSidebar = function () { document.getElementById('qSidebar').classList.toggle('open'); };
window.closeSidebar  = function () { document.getElementById('qSidebar').classList.remove('open'); };

(function () {
    var mob = document.getElementById('mobQToggle');
    function check() { if (mob) mob.style.display = window.innerWidth <= 900 ? 'flex' : 'none'; }
    window.addEventListener('resize', check);
    check();
}());

/* ── KEYBOARD ──────────────────────────────────── */
document.addEventListener('keydown', function (e) {
    if (e.target.tagName === 'INPUT') return;
    if (e.key === 'ArrowRight') window.nextQ(curSec, curQi);
    if (e.key === 'ArrowLeft')  window.prevQ(curSec, curQi);
});

/* ── INIT ──────────────────────────────────────── */
(function init() {
    if (SECTIONS.length === 0) { showSubmitModal(); return; }
    var startSi    = <?= $currentSectionIdx ?>;
    var gateOnLoad = <?= ($currentSectionIdx === 0 && !$currentSectionId) ? 'true' : 'false' ?>;
    if (gateOnLoad && startSi === 0) showSectionGate(0);
    else startSection(startSi);
    updateProgress();
}());

}());
</script>
</body>
</html>