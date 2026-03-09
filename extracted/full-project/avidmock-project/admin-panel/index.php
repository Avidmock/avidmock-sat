<?php
/**
 * index.php — Admin Dashboard
 * Uses shared includes/head.php, includes/sidebar.php
 */
require_once __DIR__ . '/auth/auth-guard.php';
require_once __DIR__ . '/lib/Database.php';

$admin = currentAdmin();
$db    = Database::connect();

// ── helpers ───────────────────────────────────────────────────────────────
function safeQuery(PDO $db, string $sql): int|float|string|null {
    try { return $db->query($sql)->fetchColumn() ?? 0; }
    catch (Throwable) { return 0; }
}
function safeQueryAll(PDO $db, string $sql): array {
    try { return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC); }
    catch (Throwable) { return []; }
}

// ── STAT CARDS ────────────────────────────────────────────────────────────
$totalStudents = (int) safeQuery($db,
    "SELECT COUNT(*) FROM users WHERE role='student' AND status='active'");

$activeToday = (int) safeQuery($db,
    "SELECT COUNT(DISTINCT user_id) FROM sat_quiz_attempts
     WHERE DATE(started_at) = CURDATE()");

$publishedQuizzes = (int) safeQuery($db,
    "SELECT COUNT(*) FROM sat_quizzes WHERE status='published'");

$totalAttempts = (int) safeQuery($db,
    "SELECT COUNT(*) FROM sat_quiz_attempts WHERE status='completed'");

$avgScore = (float) safeQuery($db,
    "SELECT COALESCE(ROUND(AVG(score),1),0) FROM sat_quiz_attempts WHERE status='completed'");

$questionCount = (int) safeQuery($db,
    "SELECT COUNT(*) FROM sat_quiz_questions");

$draftCount = (int) safeQuery($db,
    "SELECT COUNT(*) FROM sat_quizzes WHERE status='draft'");

$newThisWeek = (int) safeQuery($db,
    "SELECT COUNT(*) FROM users WHERE role='student'
     AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");

// ── detect users name column ──────────────────────────────────────────────
$userCols = array_column(
    $db->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_ASSOC),
    'Field'
);
$nameExpr = in_array('first_name', $userCols)
    ? "CONCAT(u.first_name,' ',u.last_name)"
    : "u.name";

// ── RECENT QUIZZES ────────────────────────────────────────────────────────
$recentQuizzes = safeQueryAll($db,
    "SELECT q.id, q.title, q.lesson_slug, q.status, q.updated_at,
            (SELECT COUNT(*) FROM sat_quiz_questions WHERE quiz_id=q.id)                            AS q_count,
            (SELECT COUNT(*) FROM sat_quiz_attempts   WHERE quiz_id=q.id AND status='completed')    AS attempts,
            (SELECT ROUND(AVG(score),1) FROM sat_quiz_attempts WHERE quiz_id=q.id AND status='completed') AS avg
     FROM sat_quizzes q
     ORDER BY q.updated_at DESC LIMIT 6");

// ── TOP STUDENTS ──────────────────────────────────────────────────────────
$tableList = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

if (in_array('user_xp', $tableList)) {
    // NOTE: user_xp uses column 'xp', not 'total_xp'
    $xpCols = $db->query("SHOW COLUMNS FROM user_xp")->fetchAll(PDO::FETCH_COLUMN);
    $xpCol  = in_array('xp', $xpCols) ? 'xp' : 'total_xp';
    $topStudents = safeQueryAll($db,
        "SELECT u.id, {$nameExpr} AS name, u.email, ux.{$xpCol} AS total_xp,
                (SELECT COUNT(*) FROM sat_quiz_attempts WHERE user_id=u.id AND status='completed') AS attempts,
                (SELECT ROUND(AVG(score),1) FROM sat_quiz_attempts WHERE user_id=u.id AND status='completed') AS avg_score
         FROM users u
         JOIN user_xp ux ON ux.user_id=u.id
         WHERE u.role='student' AND u.status='active'
         ORDER BY ux.{$xpCol} DESC LIMIT 5");
} else {
    $topStudents = safeQueryAll($db,
        "SELECT u.id, {$nameExpr} AS name, u.email,
                COUNT(a.id) AS attempts,
                ROUND(AVG(a.score),1) AS avg_score,
                0 AS total_xp
         FROM users u
         JOIN sat_quiz_attempts a ON a.user_id=u.id AND a.status='completed'
         WHERE u.role='student' AND u.status='active'
         GROUP BY u.id
         ORDER BY attempts DESC LIMIT 5");
}

// ── SPARKLINE — last 14 days ──────────────────────────────────────────────
$sparkData = safeQueryAll($db,
    "SELECT DATE(started_at) AS day, COUNT(*) AS n
     FROM sat_quiz_attempts
     WHERE started_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
     GROUP BY DATE(started_at) ORDER BY day ASC");

$sparkMap = [];
foreach ($sparkData as $row) { $sparkMap[$row['day']] = (int)$row['n']; }
$sparkPoints = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $sparkPoints[] = ['day' => date('M j', strtotime($d)), 'n' => $sparkMap[$d] ?? 0];
}
$sparkMax = max(array_column($sparkPoints, 'n')) ?: 1;

// ── ACTIVITY FEED ─────────────────────────────────────────────────────────
$activityFeed = safeQueryAll($db,
    "SELECT {$nameExpr} AS actor,
            CONCAT('Completed: ', q.title, ' — ', ROUND(a.score), '%') AS detail,
            a.completed_at AS ts
     FROM sat_quiz_attempts a
     JOIN users u ON u.id=a.user_id
     JOIN sat_quizzes q ON q.id=a.quiz_id
     WHERE a.status='completed'
     ORDER BY a.completed_at DESC LIMIT 8");

// ── helpers ───────────────────────────────────────────────────────────────
function timeAgo(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60)    return 'just now';
    if ($diff < 3600)  return round($diff/60).'m ago';
    if ($diff < 86400) return round($diff/3600).'h ago';
    return round($diff/86400).'d ago';
}
function scoreClass(float $s): string {
    if ($s >= 80) return 'score-hi';
    if ($s >= 60) return 'score-md';
    return 'score-lo';
}

// ── Head setup ────────────────────────────────────────────────────────────
$pageTitle  = 'Dashboard — Avidmock Admin';
$activePage = 'dashboard';
$extraHead  = <<<'CSS'
<style>
/* ── Layout ─────────────────────────────────────────────────────── */
.main {
    margin-left: var(--sb-w);
    margin-top: var(--top-h);
    padding: 28px;
    min-height: calc(100vh - var(--top-h));
}

/* ── Page header ─────────────────────────────────────────────────── */
.ph { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 24px; }
.ph-eyebrow { display: inline-flex; align-items: center; gap: 6px; font-size: .5rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 6px; }
.ph-eyebrow-dot { width: 4px; height: 4px; border-radius: 50%; background: var(--ac); }
.ph-title { font-family: var(--fh); font-size: clamp(1.5rem,2.5vw,2rem); font-weight: 900; color: var(--tx); letter-spacing: -.03em; line-height: 1.1; }
.ph-sub { font-size: .875rem; color: var(--tx2); margin-top: 4px; }
.ph-right { display: flex; gap: 8px; flex-shrink: 0; }

/* ── Topbar status badge ─────────────────────────────────────────── */
.topbar-status { display: flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 8px; background: rgba(31,226,144,.06); border: 1px solid rgba(31,226,144,.15); font-size: .625rem; font-weight: 700; color: var(--ac); text-transform: uppercase; letter-spacing: .8px; }
.topbar-status-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--ac); animation: pulse 2.2s ease-in-out infinite; }
@keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.35;transform:scale(.6)} }
.topbar-greeting { flex: 1; }
.topbar-greeting-hi { font-family: var(--fh); font-size: 1.0625rem; font-weight: 700; font-style: italic; color: var(--tx); letter-spacing: -.02em; line-height: 1.2; }
.topbar-greeting-date { font-size: .6875rem; color: var(--tx3); font-weight: 500; margin-top: 1px; }

/* ── Stat cards ─────────────────────────────────────────────────── */
.stats-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 12px; margin-bottom: 24px; }
.stat-card { background: var(--sf); border: 1px solid var(--bd); border-radius: var(--r2,16px); padding: 20px; position: relative; overflow: hidden; transition: all .22s cubic-bezier(.16,1,.3,1); cursor: default; }
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: var(--ac-gradient,linear-gradient(90deg,var(--ac),transparent)); opacity: 0; transition: opacity .22s; }
.stat-card:hover { background: var(--sf2); border-color: var(--bd2); transform: translateY(-2px); box-shadow: 0 8px 32px rgba(0,0,0,.35); }
.stat-card:hover::before { opacity: 1; }
.stat-card.ac   { --ac-gradient: linear-gradient(90deg,var(--ac),transparent); }
.stat-card.warn { --ac-gradient: linear-gradient(90deg,var(--warn),transparent); }
.stat-card.blue { --ac-gradient: linear-gradient(90deg,var(--blue),transparent); }
.stat-card.pur  { --ac-gradient: linear-gradient(90deg,var(--purple),transparent); }
.sc-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.sc-label { font-size: .6875rem; font-weight: 600; color: var(--tx3); }
.sc-ico { width: 30px; height: 30px; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
.sc-ico svg { width: 14px; height: 14px; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; stroke: currentColor; }
.sc-ico.ac   { background: var(--ac3);      color: var(--ac); }
.sc-ico.warn { background: var(--warn2);    color: var(--warn); }
.sc-ico.blue { background: var(--blue2);    color: var(--blue); }
.sc-ico.pur  { background: var(--purple2);  color: var(--purple); }
.sc-val { font-family: var(--fh); font-size: 2rem; font-weight: 900; color: var(--tx); letter-spacing: -.04em; line-height: 1; margin-bottom: 4px; }
.sc-change { font-size: .6875rem; font-weight: 600; display: inline-flex; align-items: center; gap: 3px; }
.sc-change.up { color: var(--ac); } .sc-change.neutral { color: var(--tx3); }
.sc-change svg { width: 10px; height: 10px; stroke: currentColor; fill: none; stroke-width: 2.5; stroke-linecap: round; }

/* ── Dash grid ───────────────────────────────────────────────────── */
.dash-grid { display: grid; grid-template-columns: 1fr 1fr 340px; gap: 14px; }
.card { background: var(--sf); border: 1px solid var(--bd); border-radius: var(--r2,16px); overflow: hidden; }
.card-head { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px 0; }
.card-title { font-size: .875rem; font-weight: 700; color: var(--tx); letter-spacing: -.015em; }
.card-link { font-size: .6875rem; font-weight: 700; color: var(--tx3); text-decoration: none; transition: color .16s; }
.card-link:hover { color: var(--ac); }
.card-divider { border: none; border-top: 1px solid var(--bd); margin: 0; }

/* ── Sparkline chart ─────────────────────────────────────────────── */
.chart-card { grid-column: 1/3; }
.chart-meta { display: flex; align-items: flex-end; gap: 16px; padding: 12px 20px 16px; }
.chart-total { font-family: var(--fh); font-size: 2.25rem; font-weight: 900; color: var(--tx); letter-spacing: -.04em; }
.chart-total-label { font-size: .6875rem; color: var(--tx3); font-weight: 500; margin-bottom: 4px; }
.chart-period { font-size: .625rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: .8px; margin-bottom: 6px; }
.chart-area { overflow: hidden; }
svg.sparkline { width: 100%; display: block; overflow: visible; }
.spark-bar { fill: var(--ac); opacity: .18; transition: opacity .18s; cursor: default; }
.spark-bar:hover { opacity: .45; }
.spark-bar.today { fill: var(--ac); opacity: .55; }

/* ── Quick actions ───────────────────────────────────────────────── */
.qa-card { grid-column: 3; grid-row: 1/3; display: flex; flex-direction: column; }
.qa-list { padding: 14px; display: flex; flex-direction: column; gap: 7px; }
.qa-btn { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-radius: 10px; background: var(--sf2); border: 1px solid var(--bd); text-decoration: none; transition: all .2s cubic-bezier(.16,1,.3,1); cursor: pointer; }
.qa-btn:hover { background: var(--sf3); border-color: var(--bd2); transform: translateX(3px); }
.qa-btn.primary { background: var(--ac3); border-color: rgba(31,226,144,.2); }
.qa-btn.primary:hover { background: var(--ac4); border-color: rgba(31,226,144,.35); }
.qa-ico { width: 32px; height: 32px; border-radius: 8px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
.qa-ico svg { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 1.85; stroke-linecap: round; stroke-linejoin: round; }
.qa-ico.ac     { background: var(--ac3);     color: var(--ac); }
.qa-ico.purple { background: var(--purple2); color: var(--purple); }
.qa-label { font-size: .8125rem; font-weight: 700; color: var(--tx); }
.qa-sub   { font-size: .5625rem; color: var(--tx3); margin-top: 1px; }
.qa-arr { margin-left: auto; color: var(--tx3); }
.qa-arr svg { width: 12px; height: 12px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; }

/* ── Top students ────────────────────────────────────────────────── */
.students-list { padding: 10px 14px 14px; display: flex; flex-direction: column; gap: 1px; }
.student-row { display: flex; align-items: center; gap: 10px; padding: 9px 8px; border-radius: 8px; transition: background .14s; }
.student-row:hover { background: var(--sf2); }
.student-rank { font-family: var(--fm); font-size: .625rem; font-weight: 500; color: var(--tx3); width: 16px; text-align: center; flex-shrink: 0; }
.student-rank.gold { color: #f59e0b; font-weight: 700; }
.student-ava { width: 28px; height: 28px; border-radius: 7px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: .625rem; font-weight: 800; color: var(--dk); background: linear-gradient(135deg,var(--ac),var(--ac2)); }
.student-name { font-size: .8125rem; font-weight: 700; color: var(--tx); flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.student-xp { font-family: var(--fm); font-size: .625rem; font-weight: 500; color: var(--ac); background: var(--ac3); padding: 2px 7px; border-radius: 50px; flex-shrink: 0; }

/* ── Quiz table ──────────────────────────────────────────────────── */
.quiz-table-card { grid-column: 1/3; }
.quiz-table { width: 100%; border-collapse: collapse; }
.quiz-table th { padding: 8px 14px; font-size: .5625rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: .8px; text-align: left; border-bottom: 1px solid var(--bd); background: rgba(255,255,255,.015); }
.quiz-table td { padding: 12px 14px; font-size: .8125rem; border-bottom: 1px solid var(--bd); vertical-align: middle; }
.quiz-table tr:last-child td { border-bottom: none; }
.quiz-table tbody tr { transition: background .14s; }
.quiz-table tbody tr:hover { background: var(--sf2); }
.qt-title { font-weight: 700; color: var(--tx); letter-spacing: -.01em; }
.qt-slug  { display: block; font-size: .625rem; color: var(--tx3); font-family: var(--fm); margin-top: 2px; }
.qt-status { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 50px; font-size: .5rem; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; }
.qt-status.published { background: var(--ac3);   color: var(--ac); }
.qt-status.draft     { background: var(--warn2);  color: var(--warn); }
.qt-status.archived  { background: var(--sf3);    color: var(--tx3); }
.qt-status-dot { width: 4px; height: 4px; border-radius: 50%; background: currentColor; }
.qt-q-count { font-weight: 700; color: var(--tx); }
.qt-q-count.zero { color: var(--tx3); }
.qt-score { font-weight: 700; }
.qt-score.score-hi { color: var(--ac); } .qt-score.score-md { color: var(--warn); } .qt-score.score-lo { color: var(--err); } .qt-score.na { color: var(--tx3); }
.qt-edit { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 7px; background: var(--sf2); border: 1px solid var(--bd); text-decoration: none; transition: all .16s; color: var(--tx2); }
.qt-edit svg { width: 12px; height: 12px; stroke: currentColor; fill: none; stroke-width: 1.8; stroke-linecap: round; }
.qt-edit:hover { background: var(--ac3); border-color: rgba(31,226,144,.2); color: var(--ac); }

/* ── Activity feed ───────────────────────────────────────────────── */
.activity-list { padding: 10px 14px 14px; display: flex; flex-direction: column; gap: 1px; }
.activity-row { display: flex; align-items: flex-start; gap: 10px; padding: 8px; border-radius: 8px; transition: background .14s; }
.activity-row:hover { background: var(--sf2); }
.activity-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--ac); margin-top: 5px; flex-shrink: 0; }
.activity-body { flex: 1; min-width: 0; }
.activity-actor  { font-size: .75rem; font-weight: 700; color: var(--tx); }
.activity-detail { font-size: .6875rem; color: var(--tx3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 1px; }
.activity-ts { font-size: .5625rem; color: var(--tx3); flex-shrink: 0; margin-top: 3px; font-family: var(--fm); }

/* ── Empty state ─────────────────────────────────────────────────── */
.empty-state { padding: 32px 20px; text-align: center; }
.empty-state-ico  { font-size: 2rem; margin-bottom: 8px; opacity: .3; }
.empty-state-text { font-size: .8125rem; color: var(--tx3); }

/* ── Reveals ─────────────────────────────────────────────────────── */
.reveal { opacity: 0; transform: translateY(16px); animation: revealUp .5s cubic-bezier(.16,1,.3,1) forwards; }
@keyframes revealUp { to { opacity: 1; transform: none; } }
.d1{animation-delay:.04s} .d2{animation-delay:.08s} .d3{animation-delay:.12s}
.d4{animation-delay:.16s} .d5{animation-delay:.20s} .d6{animation-delay:.24s}
.d7{animation-delay:.28s} .d8{animation-delay:.32s}

/* ── Responsive ──────────────────────────────────────────────────── */
@media (max-width: 1100px) {
    .dash-grid { grid-template-columns: 1fr 1fr; }
    .qa-card { grid-column: 1/3; grid-row: auto; }
    .chart-card, .quiz-table-card { grid-column: 1/3; }
    .stats-grid { grid-template-columns: repeat(2,1fr); }
}
@media (max-width: 768px) {
    .main { margin-left: 0; padding: 16px; }
    .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
    .dash-grid { grid-template-columns: 1fr; }
    .chart-card, .quiz-table-card, .qa-card { grid-column: 1; grid-row: auto; }
}
@media (max-width: 480px) {
    .ph { flex-wrap: wrap; } .ph-right { display: none; }
}
</style>
CSS;

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Topbar -->
<header class="topbar">
    <button class="topbar-ham" onclick="openSidebar()" aria-label="Menu">
        <span></span><span></span><span></span>
    </button>
    <div class="topbar-greeting">
        <div class="topbar-greeting-hi">
            Good <?= date('G') < 12 ? 'morning' : (date('G') < 18 ? 'afternoon' : 'evening') ?>,
            <?= htmlspecialchars(explode(' ', $admin['name'])[0]) ?>.
        </div>
        <div class="topbar-greeting-date"><?= date('l, F j, Y') ?></div>
    </div>
    <div class="topbar-spacer"></div>
    <a href="/quizzes/create.php" class="btn btn-primary btn-sm">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Quiz
    </a>
</header>

<!-- Main -->
<main class="main">

    <!-- Page header -->
    <div class="ph reveal d1">
        <div class="ph-left">
            <div class="ph-eyebrow"><span class="ph-eyebrow-dot"></span>Admin Dashboard</div>
            <h1 class="ph-title">Platform Overview</h1>
            <p class="ph-sub">Everything happening across Avidmock SAT, right now.</p>
        </div>
        <div class="ph-right">
            <a href="/analytics/index.php" class="btn btn-ghost btn-sm">
                <svg viewBox="0 0 24 24"><path d="M3 14l4-5 4 3 4-6" stroke-linecap="round"/><path d="M3 18h14" stroke-linecap="round"/></svg>
                View Analytics
            </a>
        </div>
    </div>

    <!-- STAT CARDS -->
    <div class="stats-grid">
        <div class="stat-card ac reveal d2">
            <div class="sc-top">
                <div class="sc-label">Active Students</div>
                <div class="sc-ico ac"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></div>
            </div>
            <div class="sc-val"><?= number_format($totalStudents) ?></div>
            <div class="sc-change up">
                <svg viewBox="0 0 24 24"><polyline points="18 15 12 9 6 15"/></svg>
                +<?= $newThisWeek ?> this week
            </div>
        </div>
        <div class="stat-card warn reveal d3">
            <div class="sc-top">
                <div class="sc-label">Active Today</div>
                <div class="sc-ico warn"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14" stroke-linecap="round"/></svg></div>
            </div>
            <div class="sc-val"><?= number_format($activeToday) ?></div>
            <div class="sc-change neutral">quiz attempts today</div>
        </div>
        <div class="stat-card blue reveal d4">
            <div class="sc-top">
                <div class="sc-label">Published Quizzes</div>
                <div class="sc-ico blue"><svg viewBox="0 0 24 24"><path d="M9 2H4a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9"/><path d="M13 2l5 5-8 8H5v-5l8-8z"/></svg></div>
            </div>
            <div class="sc-val"><?= number_format($publishedQuizzes) ?></div>
            <div class="sc-change <?= $draftCount > 0 ? 'neutral' : 'up' ?>">
                <?= $draftCount > 0 ? "{$draftCount} draft" . ($draftCount !== 1 ? 's' : '') . ' waiting' : 'all published' ?>
            </div>
        </div>
        <div class="stat-card pur reveal d5">
            <div class="sc-top">
                <div class="sc-label">Avg Score</div>
                <div class="sc-ico pur"><svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
            </div>
            <div class="sc-val"><?= $avgScore > 0 ? $avgScore . '%' : '—' ?></div>
            <div class="sc-change neutral"><?= number_format($totalAttempts) ?> total attempts</div>
        </div>
    </div>

    <!-- MAIN GRID -->
    <div class="dash-grid">

        <!-- SPARKLINE -->
        <div class="card chart-card reveal d4">
            <div class="card-head">
                <div class="card-title">Quiz Attempts — Last 14 Days</div>
                <a href="/analytics/engagement.php" class="card-link">Full report →</a>
            </div>
            <div class="chart-meta">
                <div>
                    <div class="chart-total-label">Total attempts this period</div>
                    <div class="chart-total"><?= number_format(array_sum(array_column($sparkPoints, 'n'))) ?></div>
                </div>
                <div>
                    <div class="chart-period">Peak day</div>
                    <div style="font-size:.8125rem;font-weight:700;color:var(--ac)"><?= $sparkMax ?> attempts</div>
                </div>
            </div>
            <div class="chart-area">
                <?php
                $svgH  = 80; $svgW = 100;
                $n     = count($sparkPoints);
                $barW  = ($svgW / $n) * 0.7;
                $gap   = ($svgW / $n) * 0.3;
                $today = date('M j');
                ?>
                <svg class="sparkline" viewBox="0 0 <?= $svgW ?> <?= $svgH ?>" preserveAspectRatio="none">
                    <?php foreach ($sparkPoints as $i => $pt):
                        $barH    = $sparkMax > 0 ? round(($pt['n'] / $sparkMax) * ($svgH - 8)) : 2;
                        $barH    = max($barH, 2);
                        $x       = ($i / $n) * $svgW + $gap / 2;
                        $y       = $svgH - $barH;
                        $isToday = $pt['day'] === $today;
                    ?>
                    <rect class="spark-bar<?= $isToday ? ' today' : '' ?>"
                          x="<?= round($x,2) ?>" y="<?= round($y,2) ?>"
                          width="<?= round($barW,2) ?>" height="<?= round($barH,2) ?>"
                          rx="1.5">
                        <title><?= htmlspecialchars($pt['day']) ?>: <?= $pt['n'] ?> attempts</title>
                    </rect>
                    <?php endforeach; ?>
                </svg>
            </div>
        </div>

        <!-- QUICK ACTIONS + TOP STUDENTS -->
        <div class="card qa-card reveal d5">
            <div class="card-head" style="padding-bottom:10px">
                <div class="card-title">Quick Actions</div>
            </div>
            <div class="qa-list">
                <a href="/quizzes/create.php" class="qa-btn primary">
                    <div class="qa-ico ac">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16" stroke-linecap="round"/><line x1="8" y1="12" x2="16" y2="12" stroke-linecap="round"/></svg>
                    </div>
                    <div>
                        <div class="qa-label">Create New Quiz</div>
                        <div class="qa-sub">Build + publish instantly</div>
                    </div>
                    <div class="qa-arr"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></div>
                </a>
                <a href="/sessions/create.php" class="qa-btn">
                    <div class="qa-ico purple">
                        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </div>
                    <div>
                        <div class="qa-label">Schedule Session</div>
                        <div class="qa-sub">Group study session</div>
                    </div>
                    <div class="qa-arr"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></div>
                </a>
            </div>

            <hr class="card-divider">

            <div class="card-head" style="padding-top:14px;padding-bottom:6px">
                <div class="card-title">Top Students</div>
                <a href="/students/index.php" class="card-link">View all →</a>
            </div>
            <?php if (empty($topStudents)): ?>
            <div class="empty-state">
                <div class="empty-state-ico"></div>
                <div class="empty-state-text">No student data yet.</div>
            </div>
            <?php else: ?>
            <div class="students-list">
                <?php foreach ($topStudents as $i => $s): ?>
                <div class="student-row">
                    <div class="student-rank <?= $i === 0 ? 'gold' : '' ?>"><?= $i === 0 ? '★' : '#'.($i+1) ?></div>
                    <div class="student-ava"><?= strtoupper(substr($s['name'], 0, 1)) ?></div>
                    <div class="student-name"><?= htmlspecialchars($s['name']) ?></div>
                    <div class="student-xp"><?= number_format((int)$s['total_xp']) ?> XP</div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- RECENT QUIZZES -->
        <div class="card quiz-table-card reveal d6">
            <div class="card-head" style="padding-bottom:12px">
                <div class="card-title">Recent Quizzes</div>
                <a href="/quizzes/index.php" class="card-link">View all →</a>
            </div>
            <?php if (empty($recentQuizzes)): ?>
            <div class="empty-state">
                <div class="empty-state-ico">📋</div>
                <div class="empty-state-text">No quizzes created yet.</div>
            </div>
            <?php else: ?>
            <table class="quiz-table">
                <thead>
                    <tr>
                        <th>Quiz</th><th>Status</th><th>Questions</th>
                        <th>Attempts</th><th>Avg Score</th><th>Updated</th><th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentQuizzes as $q): ?>
                <tr>
                    <td>
                        <div class="qt-title"><?= htmlspecialchars($q['title']) ?></div>
                        <code class="qt-slug"><?= htmlspecialchars($q['lesson_slug'] ?? '') ?></code>
                    </td>
                    <td>
                        <span class="qt-status <?= htmlspecialchars($q['status']) ?>">
                            <?php if ($q['status'] === 'published'): ?><span class="qt-status-dot"></span><?php endif; ?>
                            <?= ucfirst($q['status']) ?>
                        </span>
                    </td>
                    <td><span class="qt-q-count <?= (int)$q['q_count'] === 0 ? 'zero' : '' ?>"><?= (int)$q['q_count'] ?></span></td>
                    <td style="color:var(--tx2);font-weight:600"><?= number_format((int)$q['attempts']) ?></td>
                    <td>
                        <?php if ($q['avg'] !== null): ?>
                        <span class="qt-score <?= scoreClass((float)$q['avg']) ?>"><?= $q['avg'] ?>%</span>
                        <?php else: ?>
                        <span class="qt-score na">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="color:var(--tx3);font-size:.6875rem;font-family:var(--fm)"><?= date('M j', strtotime($q['updated_at'])) ?></td>
                    <td>
                        <a href="/quizzes/edit.php?id=<?= (int)$q['id'] ?>" class="qt-edit" title="Edit">
                            <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- ACTIVITY FEED -->
        <div class="card reveal d7" style="grid-column:1/-1">
            <div class="card-head" style="padding-bottom:6px">
                <div class="card-title">Recent Activity</div>
                <span style="font-size:.6875rem;color:var(--tx3);font-family:var(--fm)">live</span>
            </div>
            <?php if (empty($activityFeed)): ?>
            <div class="empty-state">
                <div class="empty-state-ico"></div>
                <div class="empty-state-text">No activity yet — students haven't taken any quizzes.</div>
            </div>
            <?php else: ?>
            <div class="activity-list">
                <?php foreach ($activityFeed as $ev): ?>
                <div class="activity-row">
                    <div class="activity-dot"></div>
                    <div class="activity-body">
                        <div class="activity-actor"><?= htmlspecialchars($ev['actor']) ?></div>
                        <div class="activity-detail"><?= htmlspecialchars($ev['detail']) ?></div>
                    </div>
                    <div class="activity-ts"><?= $ev['ts'] ? timeAgo($ev['ts']) : '' ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /dash-grid -->

</main>

<script>
// Auto-refresh every 60 s (only when tab is visible)
let refreshTimer = setInterval(() => { if (!document.hidden) location.reload(); }, 60000);
document.addEventListener('visibilitychange', () => {
    if (document.hidden) { clearInterval(refreshTimer); }
    else { refreshTimer = setInterval(() => { if (!document.hidden) location.reload(); }, 60000); }
});
</script>
</body>
</html>