<?php
/**
 * analytics/index.php
 * Uses shared includes/head.php, includes/sidebar.php
 */
require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

$admin = currentAdmin();
$db    = Database::connect();

// ── Schema detection ──────────────────────────────────────────────────────
$allTables    = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$hasAttempts  = in_array('sat_quiz_attempts',  $allTables);
$hasAnswers   = in_array('sat_quiz_answers',   $allTables);
$hasQuizzes   = in_array('sat_quizzes',        $allTables);
$hasQuestions = in_array('sat_quiz_questions', $allTables);
$hasTopics    = in_array('micro_topics',       $allTables);
$usersTable   = in_array('users', $allTables) ? 'users' : (in_array('students', $allTables) ? 'students' : null);

$userNameExpr = "'Unknown'";
if ($usersTable) {
    $uc = $db->query("SHOW COLUMNS FROM `{$usersTable}`")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('first_name', $uc) && in_array('last_name', $uc)) $userNameExpr = "CONCAT(u.first_name,' ',u.last_name)";
    elseif (in_array('name',      $uc)) $userNameExpr = "u.name";
    elseif (in_array('full_name', $uc)) $userNameExpr = "u.full_name";
    elseif (in_array('email',     $uc)) $userNameExpr = "u.email";
}

// ── Date range ────────────────────────────────────────────────────────────
$range = $_GET['range'] ?? '30';
$days  = in_array($range, ['7','30','90','365']) ? (int)$range : 30;
$since = date('Y-m-d', strtotime("-{$days} days"));

// ── KPI cards ─────────────────────────────────────────────────────────────
$kpi = ['total_attempts'=>0,'completed'=>0,'pass_rate'=>null,'avg_score'=>null,
        'active_students'=>0,'total_quizzes'=>0,'total_questions'=>0,'avg_time'=>null];

if ($hasAttempts) {
    try {
        $attemptCols = $db->query("SHOW COLUMNS FROM sat_quiz_attempts")->fetchAll(PDO::FETCH_COLUMN);
        $hasScore    = in_array('score',         $attemptCols);
        $hasPassCol  = in_array('passing_score', $attemptCols);
        $hasTimeCol  = in_array('time_spent',    $attemptCols);

        $passExpr = $hasPassCol
            ? "SUM(CASE WHEN a.status='completed' AND a.score>=a.passing_score THEN 1 ELSE 0 END)"
            : ($hasQuizzes ? "SUM(CASE WHEN a.status='completed' AND q.passing_score IS NOT NULL AND a.score>=q.passing_score THEN 1 ELSE 0 END)" : "NULL");
        $quizJoin = (!$hasPassCol && $hasQuizzes) ? "LEFT JOIN sat_quizzes q ON q.id=a.quiz_id" : "";
        $timeExpr  = $hasTimeCol ? "ROUND(AVG(CASE WHEN a.status='completed' THEN a.time_spent END))" : "NULL";
        $scoreExpr = $hasScore   ? "ROUND(AVG(CASE WHEN a.status='completed' THEN a.score END),1)"    : "NULL";

        $r = $db->prepare(
            "SELECT COUNT(*) AS total_attempts,
                    SUM(a.status='completed') AS completed,
                    {$passExpr} AS passed,
                    {$scoreExpr} AS avg_score,
                    COUNT(DISTINCT a.user_id) AS active_students,
                    {$timeExpr} AS avg_time
             FROM sat_quiz_attempts a {$quizJoin}
             WHERE a.created_at >= :since"
        );
        $r->execute([':since' => $since]);
        $row = $r->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $kpi = array_merge($kpi, $row);
            $kpi['pass_rate'] = $kpi['completed'] > 0 && $kpi['passed'] !== null
                ? round($kpi['passed'] / $kpi['completed'] * 100, 1) : null;
        }
    } catch (Throwable) {}
}
if ($hasQuizzes) {
    try { $kpi['total_quizzes'] = (int)$db->query("SELECT COUNT(*) FROM sat_quizzes WHERE status='published'")->fetchColumn(); } catch (Throwable) {}
}
if ($hasQuestions) {
    try { $kpi['total_questions'] = (int)$db->query("SELECT COUNT(*) FROM sat_quiz_questions")->fetchColumn(); } catch (Throwable) {}
}

// ── Daily attempts ────────────────────────────────────────────────────────
$dailyAttempts = [];
if ($hasAttempts) {
    try {
        $ds = $db->prepare(
            "SELECT DATE(created_at) AS d, COUNT(*) AS n, SUM(status='completed') AS c
             FROM sat_quiz_attempts WHERE created_at >= :since
             GROUP BY DATE(created_at) ORDER BY d ASC"
        );
        $ds->execute([':since' => $since]);
        $dailyAttempts = $ds->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {}
}

$dailyMap = [];
foreach ($dailyAttempts as $r) $dailyMap[$r['d']] = $r;
$filledDays = [];
for ($i = $days - 1; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $filledDays[] = ['d'=>$d, 'n'=>(int)($dailyMap[$d]['n']??0), 'c'=>(int)($dailyMap[$d]['c']??0)];
}
$sparkMax = max(array_column($filledDays, 'n') ?: [1]) ?: 1;

// ── Top quizzes ───────────────────────────────────────────────────────────
$topQuizzes = [];
if ($hasAttempts && $hasQuizzes) {
    try {
        $tq = $db->prepare(
            "SELECT q.id, q.title,
                    COUNT(a.id) AS attempts,
                    SUM(a.status='completed') AS completed,
                    ROUND(AVG(CASE WHEN a.status='completed' THEN a.score END),1) AS avg_score
             FROM sat_quizzes q
             LEFT JOIN sat_quiz_attempts a ON a.quiz_id=q.id AND a.created_at>=:since
             WHERE q.status='published'
             GROUP BY q.id, q.title
             ORDER BY attempts DESC LIMIT 6"
        );
        $tq->execute([':since' => $since]);
        $topQuizzes = $tq->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {}
}

// ── Recent activity ───────────────────────────────────────────────────────
$recentActivity = [];
if ($hasAttempts && $usersTable) {
    try {
        $ra = $db->prepare(
            "SELECT {$userNameExpr} AS student, a.score, a.status,
                    q.title AS quiz_title, a.created_at
             FROM sat_quiz_attempts a
             JOIN `{$usersTable}` u ON u.id=a.user_id
             LEFT JOIN sat_quizzes q ON q.id=a.quiz_id
             ORDER BY a.created_at DESC LIMIT 8"
        );
        $ra->execute();
        $recentActivity = $ra->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {}
}

// ── Score distribution ────────────────────────────────────────────────────
$scoreBuckets = array_fill_keys(range(0, 90, 10), 0);
if ($hasAttempts) {
    try {
        $sd = $db->prepare(
            "SELECT FLOOR(score/10)*10 AS b, COUNT(*) AS n
             FROM sat_quiz_attempts WHERE status='completed' AND created_at>=:since
             GROUP BY b ORDER BY b"
        );
        $sd->execute([':since' => $since]);
        foreach ($sd->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $b = (int)$r['b'];
            if (isset($scoreBuckets[$b])) $scoreBuckets[$b] = (int)$r['n'];
        }
    } catch (Throwable) {}
}
$scoreMax = max(array_values($scoreBuckets)) ?: 1;

// ── Difficulty breakdown ──────────────────────────────────────────────────
$diffBreak = ['easy'=>0,'medium'=>0,'hard'=>0];
if ($hasQuestions) {
    try {
        foreach ($db->query("SELECT difficulty, COUNT(*) AS n FROM sat_quiz_questions GROUP BY difficulty")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (isset($diffBreak[$r['difficulty']])) $diffBreak[$r['difficulty']] = (int)$r['n'];
        }
    } catch (Throwable) {}
}
$diffTotal = array_sum($diffBreak) ?: 1;

// ── Topics ────────────────────────────────────────────────────────────────
$topTopics = [];
if ($hasTopics && $hasQuestions) {
    try {
        $topTopics = $db->query(
            "SELECT mt.name, COUNT(qq.id) AS n
             FROM micro_topics mt
             LEFT JOIN sat_quiz_questions qq ON qq.micro_topic_id=mt.id
             GROUP BY mt.id, mt.name ORDER BY n DESC LIMIT 8"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {}
}
$topicMax = max(array_column($topTopics,'n') ?: [1]) ?: 1;

// ── Helpers ───────────────────────────────────────────────────────────────
function fmtSecs(int $s): string {
    return $s >= 3600
        ? floor($s/3600).'h '.floor(($s%3600)/60).'m'
        : ($s >= 60 ? floor($s/60).'m '.($s%60).'s' : $s.'s');
}
function scCls(float $s): string { return $s>=80?'hi':($s>=60?'md':'lo'); }

// ── Head setup ────────────────────────────────────────────────────────────
$pageTitle  = 'Analytics — Avidmock Admin';
$activePage = 'analytics';
$extraHead  = <<<'CSS'
<style>
/* ── Layout ─────────────────────────────────────────────────────────── */
.main {
    margin-left: var(--sb-w);
    margin-top: var(--top-h);
    padding: 28px;
}

/* ── Page header ─────────────────────────────────────────────────────── */
.ph-eyebrow { font-size: .5rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: 1.2px; display: flex; align-items: center; gap: 6px; margin-bottom: 6px; }
.ph-dot     { width: 4px; height: 4px; border-radius: 50%; background: var(--ac); }
.ph-title   { font-family: var(--fh); font-size: 1.75rem; font-weight: 900; color: var(--tx); letter-spacing: -.035em; margin-bottom: 4px; }
.ph-sub     { font-size: .875rem; color: var(--tx2); margin-bottom: 28px; }
.period-badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 50px; background: var(--ac3); border: 1px solid var(--ac4); font-size: .6875rem; font-weight: 700; color: var(--ac); margin-left: 10px; font-family: var(--ff); }

/* ── Range tabs ──────────────────────────────────────────────────────── */
.range-tabs { display: flex; gap: 4px; background: var(--sf); border: 1px solid var(--bd); border-radius: 9px; padding: 3px; }
.range-tab  { padding: 5px 12px; border-radius: 6px; font-size: .75rem; font-weight: 700; color: var(--tx3); text-decoration: none; transition: all .16s; }
.range-tab:hover  { color: var(--tx); }
.range-tab.active { background: var(--ac); color: var(--dk); }

/* ── KPI grid ────────────────────────────────────────────────────────── */
.kpi-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 12px; margin-bottom: 24px; }
.kpi-card { background: var(--ink2); border: 1px solid var(--bd); border-radius: 16px; padding: 20px; position: relative; overflow: hidden; transition: all .2s; }
.kpi-card:hover { border-color: var(--bd2); transform: translateY(-2px); }
.kpi-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; border-radius: 2px 2px 0 0; }
.kpi-card.green::before  { background: linear-gradient(90deg,var(--ac),var(--ac2)); }
.kpi-card.blue::before   { background: linear-gradient(90deg,var(--blue),#6366f1); }
.kpi-card.warn::before   { background: linear-gradient(90deg,var(--warn),#f97316); }
.kpi-card.purple::before { background: linear-gradient(90deg,var(--purple),#ec4899); }
.kpi-label { font-size: .5625rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: .8px; margin-bottom: 10px; }
.kpi-val   { font-family: var(--fh); font-size: 2.25rem; font-weight: 900; line-height: 1; letter-spacing: -.04em; color: var(--tx); margin-bottom: 4px; }
.kpi-val.hi { color: var(--ac); } .kpi-val.md { color: var(--warn); } .kpi-val.lo { color: var(--err); }
.kpi-sub   { font-size: .6875rem; color: var(--tx3); }
.kpi-icon  { position: absolute; top: 18px; right: 18px; width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
.kpi-icon svg { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.kpi-card.green  .kpi-icon { background: var(--ac3);      color: var(--ac); }
.kpi-card.blue   .kpi-icon { background: var(--blue2);    color: var(--blue); }
.kpi-card.warn   .kpi-icon { background: var(--warn2);    color: var(--warn); }
.kpi-card.purple .kpi-icon { background: var(--purple2);  color: var(--purple); }

/* ── Sparkline chart ─────────────────────────────────────────────────── */
.sparkline-card { background: var(--ink2); border: 1px solid var(--bd); border-radius: 16px; padding: 20px; margin-bottom: 24px; }
.spark-head     { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.spark-title    { font-size: .875rem; font-weight: 700; color: var(--tx); }
.spark-legend   { display: flex; gap: 16px; }
.spark-leg      { display: flex; align-items: center; gap: 5px; font-size: .6875rem; color: var(--tx3); }
.spark-dot      { width: 8px; height: 8px; border-radius: 2px; }
.spark-bars     { display: flex; align-items: flex-end; gap: 3px; height: 100px; padding-bottom: 4px; }
.spark-col      { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 2px; height: 100%; }
.spark-bar-wrap { flex: 1; width: 100%; display: flex; align-items: flex-end; }
.spark-bar      { width: 100%; border-radius: 3px 3px 0 0; min-height: 2px; transition: opacity .16s; cursor: default; position: relative; }
.spark-bar:hover { opacity: .85; }
.spark-bar::after {
    content: attr(data-tip); position: absolute; bottom: calc(100% + 5px); left: 50%; transform: translateX(-50%);
    background: var(--ink2); border: 1px solid var(--bd2); padding: 4px 8px; border-radius: 6px;
    font-size: .5625rem; color: var(--tx2); white-space: nowrap; pointer-events: none;
    opacity: 0; transition: opacity .16s; z-index: 10;
}
.spark-bar:hover::after { opacity: 1; }
.spark-date { font-size: .4375rem; color: var(--tx3); font-family: var(--fm); text-align: center; width: 100%; overflow: hidden; white-space: nowrap; }

/* ── Nav shortcuts ───────────────────────────────────────────────────── */
.nav-shortcuts { display: grid; grid-template-columns: repeat(4,1fr); gap: 12px; margin-bottom: 24px; }
.nav-shortcut  { background: var(--ink2); border: 1px solid var(--bd); border-radius: 14px; padding: 18px; text-decoration: none; display: flex; flex-direction: column; gap: 10px; transition: all .2s; }
.nav-shortcut:hover { border-color: var(--bd2); transform: translateY(-2px); background: var(--sf); }
.ns-icon  { width: 36px; height: 36px; border-radius: 9px; display: flex; align-items: center; justify-content: center; }
.ns-icon svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.ns-label { font-size: .8125rem; font-weight: 700; color: var(--tx); }
.ns-sub   { font-size: .6875rem; color: var(--tx3); }

/* ── Dash grid cards ─────────────────────────────────────────────────── */
.dash-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; }
.card      { background: var(--ink2); border: 1px solid var(--bd); border-radius: 16px; overflow: hidden; }
.card-head { padding: 16px 20px; border-bottom: 1px solid var(--bd); display: flex; align-items: center; justify-content: space-between; }
.card-title { font-size: .875rem; font-weight: 700; color: var(--tx); }
.card-link  { font-size: .6875rem; color: var(--ac); text-decoration: none; display: flex; align-items: center; gap: 4px; font-weight: 600; }
.card-link:hover { color: var(--ac2); }
.card-link svg { width: 11px; height: 11px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; }

/* ── Score distribution ──────────────────────────────────────────────── */
.score-dist { padding: 20px; display: flex; flex-direction: column; gap: 8px; }
.sd-row     { display: flex; align-items: center; gap: 10px; }
.sd-label   { font-family: var(--fm); font-size: .5625rem; color: var(--tx3); width: 36px; flex-shrink: 0; text-align: right; }
.sd-track   { flex: 1; height: 8px; background: var(--sf2); border-radius: 4px; overflow: hidden; }
.sd-bar     { height: 100%; border-radius: 4px; transition: width .6s cubic-bezier(.16,1,.3,1); }
.sd-count   { font-family: var(--fm); font-size: .5625rem; color: var(--tx3); width: 28px; text-align: right; }

/* ── Top quizzes ─────────────────────────────────────────────────────── */
.quiz-rows { padding: 0; }
.quiz-row  { display: flex; align-items: center; gap: 12px; padding: 12px 20px; border-bottom: 1px solid var(--bd); transition: background .16s; text-decoration: none; }
.quiz-row:last-child { border-bottom: none; }
.quiz-row:hover  { background: var(--sf); }
.quiz-rank  { width: 20px; font-family: var(--fm); font-size: .6875rem; color: var(--tx3); flex-shrink: 0; text-align: center; }
.quiz-info  { flex: 1; min-width: 0; }
.quiz-name  { font-size: .8125rem; font-weight: 600; color: var(--tx); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.quiz-attempts { font-size: .6875rem; color: var(--tx3); margin-top: 2px; }
.quiz-score { font-family: var(--fm); font-size: .75rem; font-weight: 600; }
.quiz-score.hi { color: var(--ac); } .quiz-score.md { color: var(--warn); } .quiz-score.lo { color: var(--err); }

/* ── Difficulty breakdown ────────────────────────────────────────────── */
.diff-section { padding: 20px; display: flex; flex-direction: column; gap: 10px; }
.diff-row   { display: flex; align-items: center; gap: 10px; }
.diff-lbl   { font-size: .6875rem; font-weight: 700; width: 52px; flex-shrink: 0; text-transform: capitalize; }
.diff-lbl.easy   { color: var(--ac); }
.diff-lbl.medium { color: var(--warn); }
.diff-lbl.hard   { color: var(--err); }
.diff-track { flex: 1; height: 10px; background: var(--sf2); border-radius: 5px; overflow: hidden; }
.diff-fill  { height: 100%; border-radius: 5px; transition: width .7s cubic-bezier(.16,1,.3,1); }
.diff-fill.easy   { background: var(--ac); }
.diff-fill.medium { background: var(--warn); }
.diff-fill.hard   { background: var(--err); }
.diff-n { font-family: var(--fm); font-size: .6875rem; color: var(--tx3); width: 36px; text-align: right; }

/* ── Recent activity ─────────────────────────────────────────────────── */
.activity-full { background: var(--ink2); border: 1px solid var(--bd); border-radius: 16px; overflow: hidden; margin-bottom: 24px; }
.act-row  { display: flex; align-items: center; gap: 12px; padding: 11px 20px; border-bottom: 1px solid rgba(255,255,255,.04); transition: background .16s; }
.act-row:last-child { border-bottom: none; }
.act-row:hover { background: var(--sf); }
.act-ava  { width: 28px; height: 28px; border-radius: 7px; background: linear-gradient(135deg,var(--ac3),var(--blue2)); border: 1px solid var(--bd); display: flex; align-items: center; justify-content: center; font-size: .5625rem; font-weight: 800; color: var(--tx2); flex-shrink: 0; }
.act-name  { font-size: .8125rem; font-weight: 700; color: var(--tx); min-width: 0; }
.act-quiz  { font-size: .6875rem; color: var(--tx3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; min-width: 0; }
.act-score { font-family: var(--fm); font-size: .75rem; font-weight: 700; flex-shrink: 0; }
.act-score.hi { color: var(--ac); } .act-score.md { color: var(--warn); } .act-score.lo { color: var(--err); }
.act-status { font-size: .5rem; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; padding: 2px 8px; border-radius: 50px; flex-shrink: 0; }
.act-status.completed   { background: var(--ac3);   color: var(--ac); }
.act-status.in_progress { background: var(--blue2); color: var(--blue); }
.act-status.abandoned   { background: var(--sf2);   color: var(--tx3); }
.act-time { font-size: .5625rem; color: var(--tx3); font-family: var(--fm); flex-shrink: 0; white-space: nowrap; }

/* ── Reveal ──────────────────────────────────────────────────────────── */
.reveal { opacity: 0; transform: translateY(14px); animation: rev .42s cubic-bezier(.16,1,.3,1) forwards; }
@keyframes rev { to { opacity: 1; transform: none; } }
.d1{animation-delay:.04s} .d2{animation-delay:.08s} .d3{animation-delay:.12s}
.d4{animation-delay:.16s} .d5{animation-delay:.20s} .d6{animation-delay:.24s}

/* ── Empty state ─────────────────────────────────────────────────────── */
.empty-state { text-align: center; padding: 40px 20px; color: var(--tx3); font-size: .8125rem; }

/* ── Responsive ──────────────────────────────────────────────────────── */
@media (max-width: 1200px) { .kpi-grid { grid-template-columns: repeat(2,1fr); } }
@media (max-width: 1000px) { .dash-grid { grid-template-columns: 1fr; } .nav-shortcuts { grid-template-columns: repeat(2,1fr); } }
@media (max-width: 768px) {
    .main { margin-left: 0; padding: 16px; }
    .kpi-grid { grid-template-columns: repeat(2,1fr); }
    .range-tabs { display: none; }
    .nav-shortcuts { grid-template-columns: repeat(2,1fr); }
}
</style>
CSS;

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<!-- Topbar -->
<header class="topbar">
    <button class="topbar-ham" onclick="openSidebar()" aria-label="Menu">
        <span></span><span></span><span></span>
    </button>
    <div class="topbar-title">Analytics</div>
    <div class="topbar-spacer"></div>
    <div class="range-tabs">
        <?php foreach (['7'=>'7d','30'=>'30d','90'=>'90d','365'=>'1y'] as $v => $l): ?>
        <a href="?range=<?= $v ?>" class="range-tab <?= $range == $v ? 'active' : '' ?>"><?= $l ?></a>
        <?php endforeach ?>
    </div>
</header>

<!-- Main -->
<main class="main">

    <div class="ph-eyebrow"><span class="ph-dot"></span>Platform Overview</div>
    <h1 class="ph-title reveal d1">Analytics Dashboard
        <span class="period-badge">Last <?= $days ?> days</span>
    </h1>
    <p class="ph-sub reveal d1">All student activity, quiz performance and content health at a glance.</p>

    <!-- KPI Cards -->
    <div class="kpi-grid reveal d2">
        <div class="kpi-card green">
            <div class="kpi-icon"><svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
            <div class="kpi-label">Quiz Attempts</div>
            <div class="kpi-val"><?= number_format((int)$kpi['total_attempts']) ?></div>
            <div class="kpi-sub"><?= number_format((int)$kpi['completed']) ?> completed</div>
        </div>
        <div class="kpi-card blue">
            <div class="kpi-icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></div>
            <div class="kpi-label">Active Students</div>
            <div class="kpi-val"><?= number_format((int)$kpi['active_students']) ?></div>
            <div class="kpi-sub">unique this period</div>
        </div>
        <div class="kpi-card warn">
            <div class="kpi-icon"><svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
            <div class="kpi-label">Pass Rate</div>
            <?php if ($kpi['pass_rate'] !== null): ?>
            <div class="kpi-val <?= scCls((float)$kpi['pass_rate']) ?>"><?= $kpi['pass_rate'] ?>%</div>
            <?php else: ?><div class="kpi-val" style="color:var(--tx3)">—</div><?php endif ?>
            <div class="kpi-sub">avg score: <?= $kpi['avg_score'] ?? '—' ?><?= $kpi['avg_score'] !== null ? '%' : '' ?></div>
        </div>
        <div class="kpi-card purple">
            <div class="kpi-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14" stroke-linecap="round"/></svg></div>
            <div class="kpi-label">Avg Completion Time</div>
            <?php if ($kpi['avg_time']): ?>
            <div class="kpi-val" style="font-size:1.5rem"><?= fmtSecs((int)$kpi['avg_time']) ?></div>
            <?php else: ?><div class="kpi-val" style="color:var(--tx3)">—</div><?php endif ?>
            <div class="kpi-sub"><?= number_format((int)$kpi['total_quizzes']) ?> published quizzes</div>
        </div>
    </div>

    <!-- Sparkline chart -->
    <div class="sparkline-card reveal d3">
        <div class="spark-head">
            <div class="spark-title">Daily Activity — last <?= $days ?> days</div>
            <div class="spark-legend">
                <div class="spark-leg"><div class="spark-dot" style="background:var(--ac)"></div>Attempts</div>
                <div class="spark-leg"><div class="spark-dot" style="background:var(--blue)"></div>Completed</div>
            </div>
        </div>
        <?php if (empty(array_filter(array_column($filledDays, 'n')))): ?>
        <div class="empty-state">No attempt data in this period.</div>
        <?php else: ?>
        <div class="spark-bars">
            <?php foreach ($filledDays as $day):
                $hA = $day['n'] > 0 ? max(4, round($day['n'] / $sparkMax * 96)) : 2;
                $hC = $day['n'] > 0 && $day['c'] > 0 ? max(2, round($day['c'] / $sparkMax * 96)) : 0;
                $dateLabel = date('M j', strtotime($day['d']));
            ?>
            <div class="spark-col">
                <div class="spark-bar-wrap">
                    <div style="width:100%;position:relative">
                        <div class="spark-bar"
                             style="height:<?= $hA ?>px;background:var(--ac);opacity:.3"
                             data-tip="<?= $dateLabel ?>: <?= $day['n'] ?> attempts"></div>
                        <?php if ($hC > 0): ?>
                        <div style="position:absolute;bottom:0;left:0;right:0;height:<?= $hC ?>px;background:var(--blue);border-radius:3px 3px 0 0;opacity:.7"></div>
                        <?php endif ?>
                    </div>
                </div>
                <?php if (count($filledDays) <= 14 || date('D', strtotime($day['d'])) === 'Mon'): ?>
                <div class="spark-date"><?= date('M j', strtotime($day['d'])) ?></div>
                <?php else: ?><div class="spark-date"></div><?php endif ?>
            </div>
            <?php endforeach ?>
        </div>
        <?php endif ?>
    </div>

    <!-- Nav shortcuts -->
    <div class="nav-shortcuts reveal d3">
        <a href="/analytics/scores.php?range=<?= $range ?>" class="nav-shortcut">
            <div class="ns-icon" style="background:var(--ac3);color:var(--ac)"><svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
            <div class="ns-label">Scores</div>
            <div class="ns-sub">Score distributions &amp; pass rates</div>
        </a>
        <a href="/analytics/engagement.php?range=<?= $range ?>" class="nav-shortcut">
            <div class="ns-icon" style="background:var(--blue2);color:var(--blue)"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
            <div class="ns-label">Engagement</div>
            <div class="ns-sub">Active students &amp; session depth</div>
        </a>
        <a href="/analytics/retention.php?range=<?= $range ?>" class="nav-shortcut">
            <div class="ns-icon" style="background:var(--warn2);color:var(--warn)"><svg viewBox="0 0 24 24"><path d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.66 0 3-4.03 3-9s-1.34-9-3-9m0 18c-1.66 0-3-4.03-3-9s1.34-9 3-9"/></svg></div>
            <div class="ns-label">Retention</div>
            <div class="ns-sub">Return rates &amp; cohort analysis</div>
        </a>
        <a href="/analytics/funnel.php?range=<?= $range ?>" class="nav-shortcut">
            <div class="ns-icon" style="background:var(--purple2);color:var(--purple)"><svg viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg></div>
            <div class="ns-label">Funnel</div>
            <div class="ns-sub">Enrol → attempt → complete</div>
        </a>
    </div>

    <!-- Charts row 1 -->
    <div class="dash-grid reveal d4">
        <!-- Score distribution -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">Score Distribution</div>
                <a href="/analytics/scores.php?range=<?= $range ?>" class="card-link">View full <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></a>
            </div>
            <?php if (!$hasAttempts || !array_sum($scoreBuckets)): ?>
            <div class="empty-state">No completed attempts yet.</div>
            <?php else: ?>
            <div class="score-dist">
                <?php foreach ($scoreBuckets as $b => $n):
                    $pct = round($n / $scoreMax * 100);
                    $col = $b >= 80 ? 'var(--ac)' : ($b >= 60 ? 'var(--warn)' : 'var(--err)');
                ?>
                <div class="sd-row">
                    <div class="sd-label"><?= $b ?>–<?= $b+9 ?>%</div>
                    <div class="sd-track"><div class="sd-bar" style="width:<?= $pct ?>%;background:<?= $col ?>"></div></div>
                    <div class="sd-count"><?= $n ?></div>
                </div>
                <?php endforeach ?>
            </div>
            <?php endif ?>
        </div>

        <!-- Top quizzes -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">Top Quizzes by Attempts</div>
                <a href="/quizzes/index.php" class="card-link">All quizzes <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></a>
            </div>
            <?php if (empty($topQuizzes)): ?>
            <div class="empty-state">No quiz data yet.</div>
            <?php else: ?>
            <div class="quiz-rows">
                <?php foreach ($topQuizzes as $i => $qz):
                    $sc = $qz['avg_score'] !== null ? (float)$qz['avg_score'] : null;
                ?>
                <a href="/quizzes/results.php?id=<?= $qz['id'] ?>" class="quiz-row">
                    <div class="quiz-rank"><?= $i+1 ?></div>
                    <div class="quiz-info">
                        <div class="quiz-name"><?= htmlspecialchars(mb_substr($qz['title'],0,40)) ?></div>
                        <div class="quiz-attempts"><?= number_format((int)$qz['attempts']) ?> attempts · <?= number_format((int)$qz['completed']) ?> completed</div>
                    </div>
                    <?php if ($sc !== null): ?>
                    <div class="quiz-score <?= scCls($sc) ?>"><?= $sc ?>%</div>
                    <?php endif ?>
                </a>
                <?php endforeach ?>
            </div>
            <?php endif ?>
        </div>
    </div>

    <!-- Charts row 2 -->
    <div class="dash-grid reveal d5">
        <!-- Difficulty breakdown -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">Question Difficulty Mix</div>
                <a href="/questions/index.php" class="card-link"><?= number_format($kpi['total_questions']) ?> total <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></a>
            </div>
            <?php if (!$hasQuestions || !array_sum($diffBreak)): ?>
            <div class="empty-state">No questions found.</div>
            <?php else: ?>
            <div class="diff-section">
                <?php foreach ($diffBreak as $d => $n):
                    $pct = round($n / $diffTotal * 100);
                ?>
                <div class="diff-row">
                    <div class="diff-lbl <?= $d ?>"><?= $d ?></div>
                    <div class="diff-track"><div class="diff-fill <?= $d ?>" style="width:<?= $pct ?>%"></div></div>
                    <div class="diff-n"><?= number_format($n) ?></div>
                </div>
                <?php endforeach ?>
                <div style="font-size:.6875rem;color:var(--tx3);margin-top:4px;text-align:right">
                    <?= number_format(array_sum($diffBreak)) ?> total questions
                </div>
            </div>
            <?php endif ?>
        </div>

        <!-- Top topics -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">Top Topics by Question Count</div>
                <a href="/analytics/topics.php?range=<?= $range ?>" class="card-link">Full report <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></a>
            </div>
            <?php if (empty($topTopics)): ?>
            <div class="empty-state">No topic data yet.</div>
            <?php else: ?>
            <div class="diff-section">
                <?php foreach ($topTopics as $t):
                    $pct = round($t['n'] / $topicMax * 100);
                ?>
                <div class="diff-row">
                    <div style="font-size:.6875rem;color:var(--tx2);flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($t['name']) ?></div>
                    <div class="diff-track" style="max-width:120px"><div class="diff-fill easy" style="width:<?= $pct ?>%;background:var(--blue)"></div></div>
                    <div class="diff-n"><?= number_format((int)$t['n']) ?></div>
                </div>
                <?php endforeach ?>
            </div>
            <?php endif ?>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="activity-full reveal d6">
        <div class="card-head">
            <div class="card-title">Recent Activity</div>
            <a href="/analytics/engagement.php" class="card-link">View engagement <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></a>
        </div>
        <?php if (empty($recentActivity)): ?>
        <div class="empty-state">No recent activity found.</div>
        <?php else: ?>
        <?php foreach ($recentActivity as $act):
            $initial = strtoupper(substr($act['student']??'?', 0, 1));
            $sc      = $act['score'] !== null ? (float)$act['score'] : null;
            $timeAgo = '';
            if ($act['created_at']) {
                $diff    = time() - strtotime($act['created_at']);
                $timeAgo = $diff < 3600 ? floor($diff/60).'m ago'
                    : ($diff < 86400 ? floor($diff/3600).'h ago'
                    : date('M j', strtotime($act['created_at'])));
            }
        ?>
        <div class="act-row">
            <div class="act-ava"><?= $initial ?></div>
            <div style="min-width:0;flex:0 0 140px">
                <div class="act-name"><?= htmlspecialchars(mb_substr($act['student']??'Unknown', 0, 20)) ?></div>
            </div>
            <div class="act-quiz"><?= htmlspecialchars(mb_substr($act['quiz_title']??'—', 0, 40)) ?></div>
            <div class="act-status <?= htmlspecialchars($act['status']??'') ?>"><?= htmlspecialchars($act['status']??'') ?></div>
            <?php if ($sc !== null): ?>
            <div class="act-score <?= scCls($sc) ?>"><?= round($sc) ?>%</div>
            <?php endif ?>
            <div class="act-time"><?= $timeAgo ?></div>
        </div>
        <?php endforeach ?>
        <?php endif ?>
    </div>

</main>
</body>
</html>