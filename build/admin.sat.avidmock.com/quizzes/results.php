<?php
/**
 * quizzes/results.php
 * Aggregated analytics for one quiz.
 */
require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

$admin  = currentAdmin();
$db     = Database::connect();
$quizId = (int)($_GET['id'] ?? 0);

if (!$quizId) {
    header('Location: /quizzes/index.php');
    exit;
}

// ── Load quiz ─────────────────────────────────────────────────────────────
$quizStmt = $db->prepare("SELECT * FROM sat_quizzes WHERE id = :id");
$quizStmt->execute([':id' => $quizId]);
$quiz = $quizStmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    header('Location: /quizzes/index.php');
    exit;
}

// ── Detect which optional tables / columns exist ──────────────────────────
$allTables    = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

$hasAttempts  = in_array('sat_quiz_attempts',  $allTables);
$hasAnswers   = in_array('sat_quiz_answers',   $allTables);

// Detect user table name (support both 'users' and 'admins')
$usersTable   = in_array('users', $allTables) ? 'users' : (in_array('admins', $allTables) ? 'admins' : null);

// Detect optional answer columns
$answerCols   = $hasAnswers
    ? $db->query("SHOW COLUMNS FROM sat_quiz_answers")->fetchAll(PDO::FETCH_COLUMN)
    : [];
$hasIsCorrect = in_array('is_correct',  $answerCols);
$hasHintUsed  = in_array('hint_used',   $answerCols);
$hasTimeSpent = in_array('time_spent',  $answerCols);

// Detect optional attempt columns
$attemptCols     = $hasAttempts
    ? $db->query("SHOW COLUMNS FROM sat_quiz_attempts")->fetchAll(PDO::FETCH_COLUMN)
    : [];
$hasAttemptTime  = in_array('time_spent',      $attemptCols);
$hasAttemptNum   = in_array('attempt_number',  $attemptCols);
$hasCorrectCol   = in_array('correct',         $attemptCols);
$hasTotalCol     = in_array('total',           $attemptCols);

// Detect user name column
$userNameExpr = 'NULL';
if ($usersTable) {
    $userCols = $db->query("SHOW COLUMNS FROM `{$usersTable}`")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('first_name', $userCols) && in_array('last_name', $userCols)) {
        $userNameExpr = "CONCAT(u.first_name, ' ', u.last_name)";
    } elseif (in_array('name', $userCols)) {
        $userNameExpr = "u.name";
    } elseif (in_array('full_name', $userCols)) {
        $userNameExpr = "u.full_name";
    } elseif (in_array('email', $userCols)) {
        $userNameExpr = "u.email";
    }
}

// ── Aggregate stats ───────────────────────────────────────────────────────
$emptyStats = [
    'total_attempts' => 0, 'completed' => 0, 'avg_score' => null,
    'min_score' => null,   'max_score' => null, 'passed' => 0, 'avg_time' => null,
];
$stats = $emptyStats;

if ($hasAttempts) {
    try {
        $timeExpr = $hasAttemptTime
            ? "ROUND(AVG(CASE WHEN status='completed' THEN time_spent END)) AS avg_time"
            : "NULL AS avg_time";

        $statsStmt = $db->prepare(
            "SELECT
                COUNT(*)                                                              AS total_attempts,
                SUM(status = 'completed')                                             AS completed,
                ROUND(AVG(CASE WHEN status = 'completed' THEN score END), 1)         AS avg_score,
                ROUND(MIN(CASE WHEN status = 'completed' THEN score END), 1)         AS min_score,
                ROUND(MAX(CASE WHEN status = 'completed' THEN score END), 1)         AS max_score,
                SUM(CASE WHEN status = 'completed' AND score >= :pass THEN 1 ELSE 0 END) AS passed,
                {$timeExpr}
             FROM sat_quiz_attempts
             WHERE quiz_id = :id"
        );
        $statsStmt->execute([':id' => $quizId, ':pass' => $quiz['passing_score']]);
        $stats = array_merge($emptyStats, $statsStmt->fetch(PDO::FETCH_ASSOC) ?: []);
    } catch (Throwable) { /* leave as empty */ }
}

$completed = (int)($stats['completed'] ?? 0);
$passed    = (int)($stats['passed']    ?? 0);
$passRate  = $completed > 0 ? round($passed / $completed * 100, 1) : 0;

// ── Per-question accuracy ─────────────────────────────────────────────────
$qStats = [];
if ($hasAnswers && $hasIsCorrect) {
    try {
        $correctExpr  = "SUM(a.is_correct)";
        $accuracyExpr = "ROUND(SUM(a.is_correct) / COUNT(a.id) * 100, 1)";
        $timeExpr     = $hasTimeSpent ? "ROUND(AVG(a.time_spent))" : "NULL";
        $hintExpr     = $hasHintUsed  ? "SUM(a.hint_used)"         : "NULL";

        $qStmt = $db->prepare(
            "SELECT
                q.id, q.stem, q.difficulty, q.position,
                COUNT(a.id)          AS attempts,
                {$correctExpr}       AS correct,
                {$accuracyExpr}      AS accuracy,
                {$timeExpr}          AS avg_time,
                {$hintExpr}          AS hints_used
             FROM sat_quiz_questions q
             LEFT JOIN sat_quiz_answers a ON a.question_id = q.id
             WHERE q.quiz_id = :id
             GROUP BY q.id, q.stem, q.difficulty, q.position
             ORDER BY q.position ASC"
        );
        $qStmt->execute([':id' => $quizId]);
        $qStats = $qStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) { $qStats = []; }
} else {
    // Fallback: just load questions without answer stats
    try {
        $qFallback = $db->prepare(
            "SELECT id, stem, difficulty, position,
                    0 AS attempts, 0 AS correct, NULL AS accuracy,
                    NULL AS avg_time, NULL AS hints_used
             FROM sat_quiz_questions WHERE quiz_id = :id ORDER BY position ASC"
        );
        $qFallback->execute([':id' => $quizId]);
        $qStats = $qFallback->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) { $qStats = []; }
}

// ── Score distribution (buckets of 10) ────────────────────────────────────
$distMap = array_fill_keys(range(0, 90, 10), 0);
if ($hasAttempts) {
    try {
        $distStmt = $db->prepare(
            "SELECT FLOOR(score / 10) * 10 AS bucket, COUNT(*) AS n
             FROM sat_quiz_attempts
             WHERE quiz_id = :id AND status = 'completed'
             GROUP BY FLOOR(score / 10) * 10
             ORDER BY bucket ASC"
        );
        $distStmt->execute([':id' => $quizId]);
        foreach ($distStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $b = (int)$row['bucket'];
            if (isset($distMap[$b])) $distMap[$b] = (int)$row['n'];
        }
    } catch (Throwable) {}
}
// Safe max — never divide by zero
$distMax = max(array_values($distMap)) ?: 1;

// ── Recent attempts ───────────────────────────────────────────────────────
$recent = [];
if ($hasAttempts && $usersTable) {
    try {
        $correctSel   = $hasCorrectCol   ? "a.correct"        : "NULL AS correct";
        $totalSel     = $hasTotalCol     ? "a.total"          : "NULL AS total";
        $timeSel      = $hasAttemptTime  ? "a.time_spent"     : "NULL AS time_spent";
        $attemptNumSel= $hasAttemptNum   ? "a.attempt_number" : "NULL AS attempt_number";

        $recentStmt = $db->prepare(
            "SELECT
                a.id,
                {$userNameExpr}  AS student_name,
                a.score,
                {$correctSel},
                {$totalSel},
                {$timeSel},
                {$attemptNumSel},
                a.completed_at
             FROM sat_quiz_attempts a
             JOIN `{$usersTable}` u ON u.id = a.user_id
             WHERE a.quiz_id = :id AND a.status = 'completed'
             ORDER BY a.completed_at DESC
             LIMIT 20"
        );
        $recentStmt->execute([':id' => $quizId]);
        $recent = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) { $recent = []; }
}

// ── Helpers ───────────────────────────────────────────────────────────────
function resultsFormatTime(int $s): string {
    if ($s <= 0) return '0s';
    return $s >= 60 ? floor($s / 60) . 'm ' . ($s % 60) . 's' : $s . 's';
}
function resultsScoreCls(float $s): string {
    return $s >= 80 ? 'hi' : ($s >= 60 ? 'md' : 'lo');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Results: <?= htmlspecialchars($quiz['title']) ?> — Avidmock Admin</title>
<meta name="robots" content="noindex,nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,600;9..40,700;9..40,800&family=Fraunces:ital,opsz,wght@0,9..144,900;1,9..144,300&family=DM+Mono:wght@400&display=swap" rel="stylesheet">
<style>
:root{
    --ink:#0c1f1d;--ink2:#0e2522;--dk:#143230;
    --ac:#1fe290;--ac2:#13c474;--ac3:rgba(31,226,144,.08);--ac4:rgba(31,226,144,.15);
    --tx:#e8f3f1;--tx2:#9dbfba;--tx3:#5a8580;
    --bd:rgba(255,255,255,.07);--bd2:rgba(255,255,255,.13);
    --sf:rgba(255,255,255,.04);--sf2:rgba(255,255,255,.07);--sf3:rgba(255,255,255,.10);
    --warn:#f59e0b;--warn2:rgba(245,158,11,.12);
    --err:#ef4444;--err2:rgba(239,68,68,.12);
    --blue:#3b82f6;--blue2:rgba(59,130,246,.12);
    --ff:'DM Sans',sans-serif;--fh:'Fraunces',Georgia,serif;--fm:'DM Mono',monospace;
    --sb-w:240px;--top-h:60px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{font-family:var(--ff);background:var(--ink);color:var(--tx);-webkit-font-smoothing:antialiased}

/* SIDEBAR */
.sb{position:fixed;top:0;left:0;width:var(--sb-w);height:100vh;background:var(--ink2);border-right:1px solid var(--bd);display:flex;flex-direction:column;overflow-y:auto;z-index:300;transition:transform .32s cubic-bezier(.16,1,.3,1)}
.sb::-webkit-scrollbar{width:3px}.sb::-webkit-scrollbar-thumb{background:rgba(255,255,255,.06);border-radius:2px}
.sb-logo{display:flex;align-items:center;gap:10px;padding:0 18px;height:var(--top-h);border-bottom:1px solid var(--bd);text-decoration:none;flex-shrink:0}
.sb-logo-mark{width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,var(--ac),var(--ac2));display:flex;align-items:center;justify-content:center}
.sb-logo-mark svg{width:17px;height:17px;fill:var(--dk)}
.sb-logo-name{font-size:.875rem;font-weight:800;color:var(--tx);letter-spacing:-.02em}
.sb-logo-sub{font-size:.5625rem;color:var(--tx3);font-weight:600;text-transform:uppercase;letter-spacing:.6px}
.sb-nav{flex:1;padding:10px 0 16px}
.sb-group-label{padding:16px 18px 5px;font-size:.5rem;font-weight:700;color:var(--tx3);text-transform:uppercase;letter-spacing:1.2px}
.sb-link{display:flex;align-items:center;gap:9px;padding:8px 10px;margin:1px 8px;border-radius:9px;text-decoration:none;font-size:.8125rem;font-weight:600;color:var(--tx2);transition:all .16s;position:relative}
.sb-link:hover{background:var(--sf2);color:var(--tx)}
.sb-link.active{background:var(--ac3);color:var(--ac)}
.sb-link.active::before{content:'';position:absolute;left:-10px;top:50%;transform:translateY(-50%);width:3px;height:55%;background:var(--ac);border-radius:0 2px 2px 0}
.sb-ico{width:15px;height:15px;flex-shrink:0;stroke:currentColor;fill:none;stroke-width:1.85;stroke-linecap:round;stroke-linejoin:round}
.sb-foot{margin:8px;padding:10px 12px;background:var(--sf);border:1px solid var(--bd);border-radius:10px;display:flex;align-items:center;gap:9px}
.sb-foot-ava{width:30px;height:30px;border-radius:8px;background:linear-gradient(135deg,var(--ac),var(--ac2));display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.6875rem;font-weight:800;color:var(--dk)}
.sb-foot-name{font-size:.75rem;font-weight:700;color:var(--tx)}
.sb-foot-role{font-size:.5625rem;color:var(--tx3);text-transform:capitalize}
.sb-foot-out{margin-left:auto;padding:5px;background:none;border:none;cursor:pointer;color:var(--tx3);line-height:0;border-radius:6px;transition:all .16s}
.sb-foot-out:hover{background:var(--err2);color:var(--err)}
.sb-foot-out svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:250;opacity:0;transition:opacity .28s;pointer-events:none}
.sb-overlay.show{opacity:1;pointer-events:all}

/* TOPBAR */
.topbar{position:fixed;top:0;left:var(--sb-w);right:0;height:var(--top-h);background:rgba(12,31,29,.92);backdrop-filter:blur(16px);border-bottom:1px solid var(--bd);display:flex;align-items:center;padding:0 28px;gap:12px;z-index:200}
.topbar-ham{display:none;width:34px;height:34px;border-radius:8px;border:1px solid var(--bd);background:var(--sf);align-items:center;justify-content:center;cursor:pointer;flex-direction:column;gap:4px;padding:9px}
.topbar-ham span{display:block;height:1.5px;background:var(--tx2);border-radius:1px;width:100%}
.topbar-title{font-family:var(--fh);font-size:1rem;font-weight:900;color:var(--tx);letter-spacing:-.025em;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.topbar-title a{color:var(--tx3);text-decoration:none;font-style:italic;font-weight:300}
.topbar-title a:hover{color:var(--ac)}
.topbar-spacer{flex:1}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:9px;font-family:var(--ff);font-size:.8125rem;font-weight:700;text-decoration:none;transition:all .18s;border:1.5px solid transparent;cursor:pointer}
.btn svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
.btn-ghost{background:var(--sf);border-color:var(--bd);color:var(--tx2)}
.btn-ghost:hover{background:var(--sf2);color:var(--tx);border-color:var(--bd2)}

/* MAIN */
.main{margin-left:var(--sb-w);margin-top:var(--top-h);padding:28px}
.ph-eyebrow{font-size:.5rem;font-weight:700;color:var(--tx3);text-transform:uppercase;letter-spacing:1.2px;display:flex;align-items:center;gap:6px;margin-bottom:6px}
.ph-dot{width:4px;height:4px;border-radius:50%;background:var(--ac)}
.ph-title{font-family:var(--fh);font-size:1.75rem;font-weight:900;color:var(--tx);letter-spacing:-.035em;margin-bottom:4px}
.ph-sub{font-size:.875rem;color:var(--tx2);margin-bottom:24px}

/* No-data notice */
.no-data-notice{background:var(--sf);border:1px solid var(--bd);border-radius:12px;padding:18px 20px;font-size:.875rem;color:var(--tx3);margin-bottom:24px;display:flex;align-items:center;gap:10px}
.no-data-notice svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;flex-shrink:0}

/* STAT CARDS */
.stats-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:24px}
.stat-card{background:var(--sf);border:1px solid var(--bd);border-radius:14px;padding:18px 16px;transition:all .2s}
.stat-card:hover{background:var(--sf2);transform:translateY(-2px)}
.sc-label{font-size:.625rem;font-weight:700;color:var(--tx3);text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px}
.sc-val{font-family:var(--fh);font-size:2rem;font-weight:900;color:var(--tx);letter-spacing:-.04em;line-height:1;margin-bottom:4px}
.sc-val.hi{color:var(--ac)}.sc-val.md{color:var(--warn)}.sc-val.lo{color:var(--err)}
.sc-sub{font-size:.625rem;color:var(--tx3);line-height:1.4}

/* DASH GRID */
.dash-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:24px}
.card{background:var(--sf);border:1px solid var(--bd);border-radius:14px;overflow:hidden}
.card-head{padding:16px 20px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--bd)}
.card-title{font-size:.875rem;font-weight:700;color:var(--tx)}
.card-sub{font-size:.625rem;color:var(--tx3)}

/* SCORE DISTRIBUTION */
.dist-chart{padding:20px;display:flex;align-items:flex-end;gap:4px;height:160px;overflow:hidden}
.dist-bar-wrap{flex:1;display:flex;flex-direction:column;align-items:center;gap:5px;height:100%}
.dist-bar-track{flex:1;display:flex;align-items:flex-end;width:100%}
.dist-bar{
    width:100%;border-radius:4px 4px 0 0;
    min-height:3px;transition:opacity .18s;
    position:relative;
}
.dist-bar.empty{opacity:.15}
.dist-bar.has-data{opacity:.55}
.dist-bar:hover{opacity:1}
.dist-bar::after{
    content:attr(data-tip);
    position:absolute;bottom:calc(100% + 6px);left:50%;transform:translateX(-50%);
    background:var(--ink2);border:1px solid var(--bd2);
    padding:4px 8px;border-radius:5px;font-size:.5rem;color:var(--tx2);
    white-space:nowrap;pointer-events:none;opacity:0;transition:opacity .16s;
}
.dist-bar:hover::after{opacity:1}
.dist-label{font-size:.5rem;color:var(--tx3);font-family:var(--fm)}

/* Q TABLE */
.q-table{width:100%;border-collapse:collapse}
.q-table th{
    padding:9px 14px;font-size:.5625rem;font-weight:700;
    color:var(--tx3);text-transform:uppercase;letter-spacing:.8px;
    text-align:left;border-bottom:1px solid var(--bd);background:rgba(255,255,255,.015);
}
.q-table td{padding:11px 14px;font-size:.8125rem;border-bottom:1px solid var(--bd);vertical-align:middle}
.q-table tbody tr:last-child td{border-bottom:none}
.q-table tbody tr:hover{background:rgba(255,255,255,.02)}
.q-stem-cell{font-weight:600;color:var(--tx);max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.acc-bar-wrap{width:72px;height:5px;background:var(--sf3);border-radius:3px;overflow:hidden;display:inline-block;vertical-align:middle}
.acc-bar{height:100%;border-radius:3px;background:var(--ac);transition:width .6s cubic-bezier(.16,1,.3,1)}
.acc-bar.md{background:var(--warn)}.acc-bar.lo{background:var(--err)}
.diff-pill{font-size:.5rem;font-weight:800;padding:2px 7px;border-radius:50px;text-transform:capitalize}
.diff-pill.easy  {background:var(--ac3);color:var(--ac)}
.diff-pill.medium{background:var(--warn2);color:var(--warn)}
.diff-pill.hard  {background:var(--err2);color:var(--err)}

/* RECENT TABLE */
.full-card{background:var(--sf);border:1px solid var(--bd);border-radius:14px;overflow:hidden;margin-bottom:24px}
.r-table{width:100%;border-collapse:collapse}
.r-table th{
    padding:9px 16px;font-size:.5625rem;font-weight:700;
    color:var(--tx3);text-transform:uppercase;letter-spacing:.8px;
    text-align:left;border-bottom:1px solid var(--bd);background:rgba(255,255,255,.015);
}
.r-table td{padding:11px 16px;font-size:.8125rem;border-bottom:1px solid var(--bd);vertical-align:middle}
.r-table tbody tr:last-child td{border-bottom:none}
.r-table tbody tr:hover{background:rgba(255,255,255,.025)}
.score-val{font-weight:700;font-family:var(--fm)}
.score-val.hi{color:var(--ac)}.score-val.md{color:var(--warn)}.score-val.lo{color:var(--err)}
.empty-center{text-align:center;padding:48px 20px;color:var(--tx3);font-size:.875rem}

.reveal{opacity:0;transform:translateY(12px);animation:rev .4s cubic-bezier(.16,1,.3,1) forwards}
@keyframes rev{to{opacity:1;transform:none}}
.d1{animation-delay:.04s}.d2{animation-delay:.08s}.d3{animation-delay:.12s}.d4{animation-delay:.16s}

@media(max-width:1200px){.stats-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:1000px){.dash-grid{grid-template-columns:1fr}}
@media(max-width:768px){
    :root{--sb-w:0px}
    .sb{transform:translateX(-240px);--sb-w:240px}
    .sb.open{transform:translateX(0)}
    .topbar{left:0;padding:0 16px}
    .topbar-ham{display:flex}
    .main{margin-left:0;padding:16px}
    .stats-grid{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:480px){
    .stats-grid{grid-template-columns:1fr 1fr}
    .sc-val{font-size:1.5rem}
}
</style>
</head>
<body>

<div class="sb-overlay" id="sbOverlay" onclick="closeSidebar()"></div>

<aside class="sb" id="sidebar">
    <a href="/index.php" class="sb-logo">
        <div class="sb-logo-mark"><svg viewBox="0 0 20 20"><path d="M10 2L13 8H19L14 12.5L16 18.5L10 15L4 18.5L6 12.5L1 8H7L10 2Z"/></svg></div>
        <div><div class="sb-logo-name">Avidmock SAT</div><div class="sb-logo-sub">Admin Panel</div></div>
    </a>
    <nav class="sb-nav">
        <div class="sb-group-label">Overview</div>
        <a href="/index.php" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>Dashboard</a>
        <a href="/analytics/index.php" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>Analytics</a>
        <div class="sb-group-label">Content</div>
        <a href="/quizzes/index.php" class="sb-link active">
            <svg class="sb-ico" viewBox="0 0 24 24"><path d="M9 2H4a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9"/><path d="M13 2l5 5-8 8H5v-5l8-8z"/></svg>
            Quizzes
        </a>
        <a href="/questions/index.php" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3" stroke-linecap="round"/><line x1="12" y1="17" x2="12.01" y2="17" stroke-width="2.5"/></svg>Question Bank</a>
        <a href="/tests/index.php" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>Practice Tests</a>
        <div class="sb-group-label">Students</div>
        <a href="/students/index.php" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>All Students</a>
        <div class="sb-group-label">Platform</div>
        <a href="/sessions/index.php" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>Sessions</a>
        <a href="/notifications/index.php" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>Notifications</a>
        <a href="/settings/general.php" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>Settings</a>
    </nav>
    <div class="sb-foot">
        <div class="sb-foot-ava"><?= strtoupper(substr($admin['name'], 0, 1)) ?></div>
        <div>
            <div class="sb-foot-name"><?= htmlspecialchars($admin['name']) ?></div>
            <div class="sb-foot-role"><?= htmlspecialchars($admin['role']) ?></div>
        </div>
        <a href="/auth/logout.php" class="sb-foot-out" title="Sign out">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </a>
    </div>
</aside>

<header class="topbar">
    <button class="topbar-ham" onclick="openSidebar()" aria-label="Menu">
        <span></span><span></span><span></span>
    </button>
    <div class="topbar-title">
        <a href="/quizzes/index.php">Quizzes</a> /
        <a href="/quizzes/edit.php?id=<?= $quizId ?>"><?= htmlspecialchars($quiz['title']) ?></a> /
        Results
    </div>
    <div class="topbar-spacer"></div>
    <a href="/quizzes/preview.php?id=<?= $quizId ?>" class="btn btn-ghost" target="_blank">
        <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        Preview
    </a>
    <a href="/quizzes/edit.php?id=<?= $quizId ?>" class="btn btn-ghost">
        <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        Edit Quiz
    </a>
    <a href="/quizzes/export.php?id=<?= $quizId ?>" class="btn btn-ghost">
        <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export
    </a>
</header>

<main class="main">

    <div class="ph-eyebrow"><span class="ph-dot"></span>Quiz Analytics</div>
    <h1 class="ph-title reveal d1"><?= htmlspecialchars($quiz['title']) ?></h1>
    <p class="ph-sub reveal d1">Aggregated results from all student attempts</p>

    <?php if (!$hasAttempts): ?>
    <div class="no-data-notice reveal d1">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16" stroke-width="2.5"/></svg>
        Attempt tracking tables haven't been set up yet. Stats will appear here once students start taking this quiz.
    </div>
    <?php endif; ?>

    <!-- ── Stat Cards ─────────────────────────────────────────────────── -->
    <div class="stats-grid reveal d2">
        <div class="stat-card">
            <div class="sc-label">Total Attempts</div>
            <div class="sc-val"><?= number_format((int)$stats['total_attempts']) ?></div>
            <div class="sc-sub"><?= number_format($completed) ?> completed</div>
        </div>
        <div class="stat-card">
            <div class="sc-label">Average Score</div>
            <?php if ($stats['avg_score'] !== null): ?>
            <div class="sc-val <?= resultsScoreCls((float)$stats['avg_score']) ?>"><?= $stats['avg_score'] ?>%</div>
            <?php else: ?>
            <div class="sc-val" style="color:var(--tx3)">—</div>
            <?php endif; ?>
            <div class="sc-sub">Pass threshold: <?= $quiz['passing_score'] ?>%</div>
        </div>
        <div class="stat-card">
            <div class="sc-label">Pass Rate</div>
            <div class="sc-val <?= $completed > 0 ? resultsScoreCls($passRate) : '' ?>"><?= $completed > 0 ? $passRate . '%' : '—' ?></div>
            <div class="sc-sub"><?= number_format($passed) ?> / <?= number_format($completed) ?> passed</div>
        </div>
        <div class="stat-card">
            <div class="sc-label">Score Range</div>
            <?php if ($stats['min_score'] !== null && $stats['max_score'] !== null): ?>
            <div class="sc-val" style="font-size:1.25rem"><?= $stats['min_score'] ?>% – <?= $stats['max_score'] ?>%</div>
            <?php else: ?>
            <div class="sc-val" style="color:var(--tx3)">—</div>
            <?php endif; ?>
            <div class="sc-sub">min – max</div>
        </div>
        <div class="stat-card">
            <div class="sc-label">Avg Time</div>
            <?php if ($stats['avg_time'] !== null): ?>
            <div class="sc-val" style="font-size:1.25rem"><?= resultsFormatTime((int)$stats['avg_time']) ?></div>
            <?php else: ?>
            <div class="sc-val" style="color:var(--tx3)">—</div>
            <?php endif; ?>
            <div class="sc-sub">per attempt</div>
        </div>
    </div>

    <!-- ── Charts row ─────────────────────────────────────────────────── -->
    <div class="dash-grid reveal d3">

        <!-- Score Distribution -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">Score Distribution</div>
                <div class="card-sub"><?= $completed ?> completed attempt<?= $completed !== 1 ? 's' : '' ?></div>
            </div>
            <div class="dist-chart">
                <?php foreach ($distMap as $bucket => $n):
                    $barH  = $n > 0 ? max(8, (int)round($n / $distMax * 120)) : 4;
                    $color = $bucket >= 80 ? 'var(--ac)' : ($bucket >= 60 ? 'var(--warn)' : 'var(--err)');
                    $tip   = $bucket . '–' . ($bucket + 9) . '%: ' . $n . ' student' . ($n !== 1 ? 's' : '');
                ?>
                <div class="dist-bar-wrap">
                    <div class="dist-bar-track">
                        <div class="dist-bar <?= $n > 0 ? 'has-data' : 'empty' ?>"
                             style="height:<?= $barH ?>px;background:<?= $color ?>"
                             data-tip="<?= htmlspecialchars($tip) ?>"></div>
                    </div>
                    <div class="dist-label"><?= $bucket ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Per-Question Accuracy -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">Question Accuracy</div>
                <div class="card-sub"><?= count($qStats) ?> question<?= count($qStats) !== 1 ? 's' : '' ?></div>
            </div>
            <?php if (empty($qStats)): ?>
            <div class="empty-center">No questions found.</div>
            <?php else: ?>
            <div style="overflow-x:auto;max-height:320px;overflow-y:auto">
                <table class="q-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Question</th>
                            <th>Diff</th>
                            <th>Accuracy</th>
                            <th>Attempts</th>
                            <?php if ($hasAnswers && $hasTimeSpent): ?><th>Avg Time</th><?php endif; ?>
                            <?php if ($hasHintUsed): ?><th>Hints</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($qStats as $qRow):
                        $acc = (float)($qRow['accuracy'] ?? 0);
                        $cls = resultsScoreCls($acc);
                        $hasAcc = $qRow['attempts'] > 0;
                    ?>
                    <tr>
                        <td style="color:var(--tx3);font-family:var(--fm);font-size:.625rem"><?= (int)$qRow['position'] ?></td>
                        <td>
                            <span class="q-stem-cell" title="<?= htmlspecialchars($qRow['stem']) ?>">
                                <?= htmlspecialchars(mb_substr($qRow['stem'], 0, 55)) ?><?= mb_strlen($qRow['stem']) > 55 ? '…' : '' ?>
                            </span>
                        </td>
                        <td><span class="diff-pill <?= htmlspecialchars($qRow['difficulty'] ?? 'medium') ?>"><?= htmlspecialchars($qRow['difficulty'] ?? 'medium') ?></span></td>
                        <td>
                            <?php if ($hasAcc): ?>
                            <div style="display:flex;align-items:center;gap:8px">
                                <div class="acc-bar-wrap">
                                    <div class="acc-bar <?= $cls ?>" style="width:<?= $acc ?>%"></div>
                                </div>
                                <span class="score-val <?= $cls ?>" style="font-size:.75rem"><?= $acc ?>%</span>
                            </div>
                            <?php else: ?>
                            <span style="color:var(--tx3);font-size:.75rem">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="color:var(--tx2);font-family:var(--fm);font-size:.75rem"><?= number_format((int)$qRow['attempts']) ?></td>
                        <?php if ($hasAnswers && $hasTimeSpent): ?>
                        <td style="color:var(--tx3);font-family:var(--fm);font-size:.75rem"><?= $qRow['avg_time'] ? resultsFormatTime((int)$qRow['avg_time']) : '—' ?></td>
                        <?php endif; ?>
                        <?php if ($hasHintUsed): ?>
                        <td style="color:var(--tx3);font-family:var(--fm);font-size:.75rem"><?= $qRow['hints_used'] !== null ? number_format((int)$qRow['hints_used']) : '—' ?></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- ── Recent Attempts ────────────────────────────────────────────── -->
    <div class="full-card reveal d4">
        <div class="card-head">
            <div class="card-title">Recent Student Attempts</div>
            <div class="card-sub">Last 20 completed</div>
        </div>
        <?php if (empty($recent)): ?>
        <div class="empty-center">
            <?php if (!$usersTable): ?>
            User table not found. Check your database configuration.
            <?php else: ?>
            No completed attempts yet.
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto">
            <table class="r-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Score</th>
                        <?php if ($hasCorrectCol && $hasTotalCol): ?><th>Correct</th><?php endif; ?>
                        <?php if ($hasAttemptTime): ?><th>Time</th><?php endif; ?>
                        <?php if ($hasAttemptNum): ?><th>Attempt #</th><?php endif; ?>
                        <th>Completed</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recent as $r):
                    $scoreCls = resultsScoreCls((float)$r['score']);
                ?>
                <tr>
                    <td style="font-weight:700;color:var(--tx)"><?= htmlspecialchars($r['student_name'] ?? 'Unknown') ?></td>
                    <td><span class="score-val <?= $scoreCls ?>"><?= round((float)$r['score']) ?>%</span></td>
                    <?php if ($hasCorrectCol && $hasTotalCol): ?>
                    <td style="color:var(--tx2);font-family:var(--fm);font-size:.75rem"><?= (int)$r['correct'] ?>/<?= (int)$r['total'] ?></td>
                    <?php endif; ?>
                    <?php if ($hasAttemptTime): ?>
                    <td style="color:var(--tx3);font-family:var(--fm);font-size:.75rem"><?= $r['time_spent'] ? resultsFormatTime((int)$r['time_spent']) : '—' ?></td>
                    <?php endif; ?>
                    <?php if ($hasAttemptNum): ?>
                    <td style="color:var(--tx3);font-size:.75rem">#<?= (int)$r['attempt_number'] ?></td>
                    <?php endif; ?>
                    <td style="color:var(--tx3);font-size:.625rem;font-family:var(--fm)"><?= htmlspecialchars(date('M j, Y H:i', strtotime($r['completed_at']))) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</main>

<script>
function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('sbOverlay').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sbOverlay').classList.remove('show');
    document.body.style.overflow = '';
}
window.addEventListener('resize', () => { if (window.innerWidth > 768) closeSidebar(); });
</script>
</body>
</html>