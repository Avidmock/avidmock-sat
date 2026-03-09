<?php
/**
 * analytics/scores.php
 * Uses shared includes/head.php, includes/sidebar.php
 */
require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

$admin = currentAdmin();
$db    = Database::connect();

$allTables   = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$hasAttempts = in_array('sat_quiz_attempts', $allTables);
$hasQuizzes  = in_array('sat_quizzes',       $allTables);
$usersTable  = in_array('users', $allTables) ? 'users' : (in_array('students', $allTables) ? 'students' : null);

$userNameExpr = "'Unknown'";
if ($usersTable) {
    $uc = $db->query("SHOW COLUMNS FROM `{$usersTable}`")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('first_name',$uc) && in_array('last_name',$uc)) $userNameExpr = "CONCAT(u.first_name,' ',u.last_name)";
    elseif (in_array('name',      $uc)) $userNameExpr = "u.name";
    elseif (in_array('full_name', $uc)) $userNameExpr = "u.full_name";
    elseif (in_array('email',     $uc)) $userNameExpr = "u.email";
}

$range      = $_GET['range'] ?? '30';
$days       = in_array($range, ['7','30','90','365']) ? (int)$range : 30;
$since      = date('Y-m-d', strtotime("-{$days} days"));
$quizFilter = (int)($_GET['quiz_id'] ?? 0);

$attemptCols = $hasAttempts ? $db->query("SHOW COLUMNS FROM sat_quiz_attempts")->fetchAll(PDO::FETCH_COLUMN) : [];
$hasTimeCol  = in_array('time_spent',    $attemptCols);
$hasPassCol  = in_array('passing_score', $attemptCols);

// ── Overall stats ─────────────────────────────────────────────────────────
$overall = ['total'=>0,'completed'=>0,'avg_score'=>null,'pass_rate'=>null,'avg_time'=>null,'passed'=>null];
if ($hasAttempts) {
    try {
        $filterSQL = $quizFilter ? "AND a.quiz_id=:qf" : "";
        $params    = [':since' => $since];
        if ($quizFilter) $params[':qf'] = $quizFilter;
        $passExpr  = $hasPassCol
            ? "SUM(CASE WHEN a.status='completed' AND a.score>=a.passing_score THEN 1 ELSE 0 END)"
            : ($hasQuizzes ? "SUM(CASE WHEN a.status='completed' AND q.passing_score IS NOT NULL AND a.score>=q.passing_score THEN 1 ELSE 0 END)" : "NULL");
        $qJoin    = (!$hasPassCol && $hasQuizzes) ? "LEFT JOIN sat_quizzes q ON q.id=a.quiz_id" : "";
        $timeExpr = $hasTimeCol ? "ROUND(AVG(CASE WHEN a.status='completed' THEN a.time_spent END))" : "NULL";
        $r = $db->prepare(
            "SELECT COUNT(*) AS total,
                    SUM(a.status='completed') AS completed,
                    ROUND(AVG(CASE WHEN a.status='completed' THEN a.score END),1) AS avg_score,
                    {$passExpr} AS passed,
                    {$timeExpr} AS avg_time
             FROM sat_quiz_attempts a {$qJoin}
             WHERE a.created_at >= :since {$filterSQL}"
        );
        $r->execute($params);
        $row = $r->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $overall = array_merge($overall, $row);
            $overall['pass_rate'] = ($overall['completed'] > 0 && $overall['passed'] !== null)
                ? round($overall['passed'] / $overall['completed'] * 100, 1) : null;
        }
    } catch (Throwable) {}
}

// ── Score distribution ────────────────────────────────────────────────────
$distBuckets = array_fill_keys(range(0, 90, 10), 0);
if ($hasAttempts) {
    try {
        $filterSQL = $quizFilter ? "AND quiz_id=:qf" : "";
        $params    = [':since' => $since];
        if ($quizFilter) $params[':qf'] = $quizFilter;
        $sd = $db->prepare(
            "SELECT FLOOR(score/10)*10 AS b, COUNT(*) AS n
             FROM sat_quiz_attempts
             WHERE status='completed' AND created_at >= :since {$filterSQL}
             GROUP BY b ORDER BY b"
        );
        $sd->execute($params);
        foreach ($sd->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $b = (int)$r['b'];
            if (isset($distBuckets[$b])) $distBuckets[$b] = (int)$r['n'];
        }
    } catch (Throwable) {}
}
$distMax   = max(array_values($distBuckets)) ?: 1;
$distTotal = array_sum($distBuckets) ?: 1;

// ── Per-quiz breakdown ────────────────────────────────────────────────────
$perQuiz = [];
if ($hasAttempts && $hasQuizzes) {
    try {
        $passExpr = $hasPassCol
            ? "SUM(CASE WHEN a.status='completed' AND a.score>=a.passing_score THEN 1 ELSE 0 END)"
            : "SUM(CASE WHEN a.status='completed' AND q.passing_score IS NOT NULL AND a.score>=q.passing_score THEN 1 ELSE 0 END)";
        $timeExpr = $hasTimeCol ? "ROUND(AVG(CASE WHEN a.status='completed' THEN a.time_spent END))" : "NULL";
        $pq = $db->prepare(
            "SELECT q.id, q.title, q.passing_score,
                    COUNT(a.id) AS attempts,
                    SUM(a.status='completed') AS completed,
                    ROUND(AVG(CASE WHEN a.status='completed' THEN a.score END),1) AS avg_score,
                    ROUND(MIN(CASE WHEN a.status='completed' THEN a.score END),0) AS min_score,
                    ROUND(MAX(CASE WHEN a.status='completed' THEN a.score END),0) AS max_score,
                    {$passExpr} AS passed,
                    {$timeExpr} AS avg_time
             FROM sat_quizzes q
             LEFT JOIN sat_quiz_attempts a ON a.quiz_id=q.id AND a.created_at >= :since
             WHERE q.status='published'
             GROUP BY q.id, q.title, q.passing_score
             ORDER BY attempts DESC"
        );
        $pq->execute([':since' => $since]);
        $perQuiz = $pq->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {}
}

// ── Top & bottom performers ───────────────────────────────────────────────
$topStudents = $bottomStudents = [];
if ($hasAttempts && $usersTable) {
    try {
        $filterSQL = $quizFilter ? "AND a.quiz_id=:qf" : "";
        $params    = [':since' => $since];
        if ($quizFilter) $params[':qf'] = $quizFilter;

        $ts = $db->prepare(
            "SELECT {$userNameExpr} AS student, COUNT(a.id) AS attempts,
                    ROUND(AVG(CASE WHEN a.status='completed' THEN a.score END),1) AS avg_score,
                    SUM(a.status='completed') AS completed
             FROM sat_quiz_attempts a JOIN `{$usersTable}` u ON u.id=a.user_id
             WHERE a.created_at >= :since AND a.status='completed' {$filterSQL}
             GROUP BY a.user_id HAVING completed >= 2
             ORDER BY avg_score DESC LIMIT 8"
        );
        $ts->execute($params);
        $topStudents = $ts->fetchAll(PDO::FETCH_ASSOC);

        $bs = $db->prepare(
            "SELECT {$userNameExpr} AS student, COUNT(a.id) AS attempts,
                    ROUND(AVG(CASE WHEN a.status='completed' THEN a.score END),1) AS avg_score,
                    SUM(a.status='completed') AS completed
             FROM sat_quiz_attempts a JOIN `{$usersTable}` u ON u.id=a.user_id
             WHERE a.created_at >= :since AND a.status='completed' {$filterSQL}
             GROUP BY a.user_id HAVING completed >= 2
             ORDER BY avg_score ASC LIMIT 8"
        );
        $bs->execute($params);
        $bottomStudents = $bs->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {}
}

// ── Daily avg score trend ─────────────────────────────────────────────────
$dailyTrend = [];
if ($hasAttempts) {
    try {
        $filterSQL = $quizFilter ? "AND quiz_id=:qf" : "";
        $params    = [':since' => $since];
        if ($quizFilter) $params[':qf'] = $quizFilter;
        $dt = $db->prepare(
            "SELECT DATE(created_at) AS d,
                    ROUND(AVG(CASE WHEN status='completed' THEN score END),1) AS avg_score,
                    COUNT(*) AS n
             FROM sat_quiz_attempts
             WHERE created_at >= :since AND status='completed' {$filterSQL}
             GROUP BY DATE(created_at)
             ORDER BY d ASC"
        );
        $dt->execute($params);
        $dailyTrend = $dt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {}
}

// ── Quiz filter list ──────────────────────────────────────────────────────
$quizzesList = [];
if ($hasQuizzes) {
    try { $quizzesList = $db->query("SELECT id, title FROM sat_quizzes WHERE status='published' ORDER BY title")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable) {}
}

// ── Helpers ───────────────────────────────────────────────────────────────
function scoreColor(float $s): string { return $s>=80?'var(--ac)':($s>=60?'var(--warn)':'var(--err)'); }
function scoreCls(float $s): string   { return $s>=80?'hi':($s>=60?'md':'lo'); }
function fmtSecs(int $s): string      { return $s>=60?floor($s/60).'m '.($s%60).'s':$s.'s'; }

// ── Head setup ────────────────────────────────────────────────────────────
$pageTitle  = 'Score Analytics — Avidmock Admin';
$activePage = 'analytics';
$extraHead  = <<<'CSS'
<style>
/* ── Layout ─────────────────────────────────────────────────────────── */
.main { margin-left: var(--sb-w); margin-top: var(--top-h); padding: 28px; }

/* ── Topbar extras ───────────────────────────────────────────────────── */
.topbar-title span { color: var(--tx3); font-style: italic; font-weight: 300; }
.range-tabs  { display: flex; gap: 4px; background: var(--sf); border: 1px solid var(--bd); border-radius: 9px; padding: 3px; }
.range-tab   { padding: 5px 12px; border-radius: 6px; font-size: .75rem; font-weight: 700; color: var(--tx3); text-decoration: none; transition: all .16s; }
.range-tab:hover  { color: var(--tx); }
.range-tab.active { background: var(--ac); color: var(--dk); }
.filter-select { background: var(--sf); border: 1px solid var(--bd); border-radius: 8px; color: var(--tx); font-family: var(--ff); font-size: .75rem; padding: 6px 10px; outline: none; cursor: pointer; }
.filter-select option { background: var(--ink2); }

/* ── Page header ─────────────────────────────────────────────────────── */
.ph-eyebrow { font-size: .5rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: 1.2px; display: flex; align-items: center; gap: 6px; margin-bottom: 6px; }
.ph-dot     { width: 4px; height: 4px; border-radius: 50%; background: var(--ac); }
.ph-title   { font-family: var(--fh); font-size: 1.75rem; font-weight: 900; color: var(--tx); letter-spacing: -.035em; margin-bottom: 4px; }
.ph-sub     { font-size: .875rem; color: var(--tx2); margin-bottom: 28px; }

/* ── KPI row ─────────────────────────────────────────────────────────── */
.kpi-row   { display: grid; grid-template-columns: repeat(5,1fr); gap: 12px; margin-bottom: 24px; }
.kpi       { background: var(--ink2); border: 1px solid var(--bd); border-radius: 14px; padding: 18px 16px; }
.kpi-label { font-size: .5625rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: .8px; margin-bottom: 8px; }
.kpi-val   { font-family: var(--fh); font-size: 2rem; font-weight: 900; line-height: 1; letter-spacing: -.04em; color: var(--tx); margin-bottom: 3px; }
.kpi-val.hi { color: var(--ac); } .kpi-val.md { color: var(--warn); } .kpi-val.lo { color: var(--err); }
.kpi-sub   { font-size: .625rem; color: var(--tx3); }

/* ── Main grid ───────────────────────────────────────────────────────── */
.main-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
.card      { background: var(--ink2); border: 1px solid var(--bd); border-radius: 16px; overflow: hidden; }
.card-head { padding: 16px 20px; border-bottom: 1px solid var(--bd); display: flex; align-items: center; justify-content: space-between; }
.card-title { font-size: .875rem; font-weight: 700; color: var(--tx); }
.card-sub   { font-size: .625rem; color: var(--tx3); }

/* ── Distribution chart ──────────────────────────────────────────────── */
.dist-chart { padding: 20px; display: flex; align-items: flex-end; gap: 6px; height: 180px; }
.dc-col  { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; height: 100%; cursor: default; }
.dc-wrap { flex: 1; display: flex; flex-direction: column; justify-content: flex-end; width: 100%; position: relative; }
.dc-bar  { width: 100%; border-radius: 5px 5px 0 0; min-height: 3px; transition: opacity .2s; position: relative; }
.dc-bar:hover { opacity: .75; }
.dc-bar::after {
    content: attr(data-tip); position: absolute; bottom: calc(100% + 6px); left: 50%; transform: translateX(-50%);
    background: var(--ink2); border: 1px solid var(--bd2); padding: 4px 8px; border-radius: 6px;
    font-size: .5rem; color: var(--tx2); white-space: nowrap; pointer-events: none;
    opacity: 0; transition: opacity .16s; z-index: 10;
}
.dc-bar:hover::after { opacity: 1; }
.dc-label { font-size: .4375rem; color: var(--tx3); font-family: var(--fm); text-align: center; }
.dc-pct   { font-size: .4375rem; color: var(--tx3); font-family: var(--fm); }

/* ── Trend chart ─────────────────────────────────────────────────────── */
.trend-chart { padding: 20px; }
.tc-bars { display: flex; align-items: flex-end; gap: 3px; height: 120px; }
.tc-col  { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 2px; cursor: default; position: relative; }
.tc-bar  { width: 100%; border-radius: 3px 3px 0 0; opacity: .55; min-height: 2px; transition: opacity .16s; }
.tc-bar:hover { opacity: .9; }
.tc-bar::after {
    content: attr(data-tip); position: absolute; bottom: calc(100% + 5px); left: 50%; transform: translateX(-50%);
    background: var(--ink2); border: 1px solid var(--bd2); padding: 3px 7px; border-radius: 5px;
    font-size: .5rem; color: var(--tx2); white-space: nowrap; pointer-events: none;
    opacity: 0; transition: opacity .16s; z-index: 10;
}
.tc-bar:hover::after { opacity: 1; }
.tc-label { font-size: .4375rem; color: var(--tx3); font-family: var(--fm); }

/* ── Per-quiz table ──────────────────────────────────────────────────── */
.full-card  { background: var(--ink2); border: 1px solid var(--bd); border-radius: 16px; overflow: hidden; margin-bottom: 20px; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { padding: 9px 16px; font-size: .5rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: .8px; text-align: left; border-bottom: 1px solid var(--bd); background: rgba(255,255,255,.015); white-space: nowrap; }
.data-table td { padding: 11px 16px; font-size: .8125rem; border-bottom: 1px solid rgba(255,255,255,.04); vertical-align: middle; }
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover { background: rgba(255,255,255,.025); }
.score-badge { font-family: var(--fm); font-size: .75rem; font-weight: 700; padding: 3px 9px; border-radius: 50px; }
.score-badge.hi { background: var(--ac3);   color: var(--ac); }
.score-badge.md { background: var(--warn2); color: var(--warn); }
.score-badge.lo { background: var(--err2);  color: var(--err); }
.pass-bar      { height: 5px; border-radius: 3px; overflow: hidden; background: var(--sf2); width: 70px; display: inline-block; vertical-align: middle; margin-right: 6px; }
.pass-bar-fill { height: 100%; border-radius: 3px; }

/* ── Performers grid ─────────────────────────────────────────────────── */
.perf-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
.perf-card-head { padding: 14px 18px; border-bottom: 1px solid var(--bd); display: flex; align-items: center; gap: 10px; }
.perf-card-ico  { width: 28px; height: 28px; border-radius: 7px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.perf-card-ico svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.perf-card-ico.green { background: var(--ac3);   color: var(--ac); }
.perf-card-ico.amber { background: var(--warn2); color: var(--warn); }
.perf-card-title { font-size: .875rem; font-weight: 700; color: var(--tx); flex: 1; }
.perf-card-sub   { font-size: .625rem; color: var(--tx3); }
.perf-row  { display: flex; align-items: center; gap: 10px; padding: 10px 18px; border-bottom: 1px solid rgba(255,255,255,.04); transition: background .16s; }
.perf-row:last-child { border-bottom: none; }
.perf-row:hover { background: var(--sf); }
.perf-rank { font-family: var(--fm); font-size: .5625rem; color: var(--tx3); width: 16px; text-align: center; flex-shrink: 0; }
.perf-name { font-size: .8125rem; font-weight: 600; color: var(--tx); flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.perf-meta { font-size: .6875rem; color: var(--tx3); }

/* ── Empty / reveals ─────────────────────────────────────────────────── */
.empty-state { text-align: center; padding: 40px; color: var(--tx3); font-size: .875rem; }
.reveal { opacity: 0; transform: translateY(12px); animation: rev .4s cubic-bezier(.16,1,.3,1) forwards; }
@keyframes rev { to { opacity: 1; transform: none; } }
.d1{animation-delay:.04s} .d2{animation-delay:.08s} .d3{animation-delay:.12s} .d4{animation-delay:.16s}

/* ── Responsive ──────────────────────────────────────────────────────── */
@media (max-width: 1100px) { .kpi-row { grid-template-columns: repeat(3,1fr); } .main-grid { grid-template-columns: 1fr; } .perf-grid { grid-template-columns: 1fr; } }
@media (max-width: 768px)  { .main { margin-left: 0; padding: 16px; } .range-tabs { display: none; } .kpi-row { grid-template-columns: repeat(2,1fr); } }
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
    <div class="topbar-title">
        <a href="/analytics/index.php">Analytics</a>
        <span> / Scores</span>
    </div>
    <div class="topbar-spacer"></div>
    <?php if (!empty($quizzesList)): ?>
    <form method="GET" style="display:flex;gap:8px;align-items:center">
        <input type="hidden" name="range" value="<?= htmlspecialchars($range) ?>">
        <select name="quiz_id" class="filter-select" onchange="this.form.submit()">
            <option value="0">All quizzes</option>
            <?php foreach ($quizzesList as $qz): ?>
            <option value="<?= (int)$qz['id'] ?>" <?= $quizFilter === (int)$qz['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars(mb_substr($qz['title'], 0, 32)) ?>
            </option>
            <?php endforeach ?>
        </select>
    </form>
    <?php endif ?>
    <div class="range-tabs">
        <?php foreach (['7'=>'7d','30'=>'30d','90'=>'90d','365'=>'1y'] as $v => $l): ?>
        <a href="?range=<?= $v ?><?= $quizFilter ? "&quiz_id={$quizFilter}" : '' ?>"
           class="range-tab <?= $range == $v ? 'active' : '' ?>"><?= $l ?></a>
        <?php endforeach ?>
    </div>
</header>

<!-- Main -->
<main class="main">

    <div class="ph-eyebrow"><span class="ph-dot"></span>Score Analytics</div>
    <h1 class="ph-title reveal d1">Score Performance</h1>
    <p class="ph-sub reveal d1">Distribution, pass rates and per-quiz breakdown · last <?= $days ?> days</p>

    <!-- KPI row -->
    <div class="kpi-row reveal d2">
        <div class="kpi">
            <div class="kpi-label">Completed Attempts</div>
            <div class="kpi-val"><?= number_format((int)$overall['completed']) ?></div>
            <div class="kpi-sub">of <?= number_format((int)$overall['total']) ?> total</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Average Score</div>
            <?php if ($overall['avg_score'] !== null): ?>
            <div class="kpi-val <?= scoreCls((float)$overall['avg_score']) ?>"><?= $overall['avg_score'] ?>%</div>
            <?php else: ?><div class="kpi-val" style="color:var(--tx3)">—</div><?php endif ?>
            <div class="kpi-sub">across all quizzes</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Pass Rate</div>
            <?php if ($overall['pass_rate'] !== null): ?>
            <div class="kpi-val <?= scoreCls((float)$overall['pass_rate']) ?>"><?= $overall['pass_rate'] ?>%</div>
            <?php else: ?><div class="kpi-val" style="color:var(--tx3)">—</div><?php endif ?>
            <div class="kpi-sub">of completed attempts</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Avg Time</div>
            <?php if ($overall['avg_time']): ?>
            <div class="kpi-val" style="font-size:1.25rem"><?= fmtSecs((int)$overall['avg_time']) ?></div>
            <?php else: ?><div class="kpi-val" style="color:var(--tx3)">—</div><?php endif ?>
            <div class="kpi-sub">per completion</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Completion Rate</div>
            <?php $cr = $overall['total'] > 0 ? round($overall['completed'] / $overall['total'] * 100, 1) : null; ?>
            <?php if ($cr !== null): ?>
            <div class="kpi-val <?= scoreCls($cr) ?>"><?= $cr ?>%</div>
            <?php else: ?><div class="kpi-val" style="color:var(--tx3)">—</div><?php endif ?>
            <div class="kpi-sub">started → finished</div>
        </div>
    </div>

    <!-- Charts row -->
    <div class="main-grid reveal d3">

        <!-- Score distribution -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">Score Distribution</div>
                <div class="card-sub"><?= array_sum($distBuckets) ?> attempts</div>
            </div>
            <?php if (!array_sum($distBuckets)): ?>
            <div class="empty-state">No score data yet.</div>
            <?php else: ?>
            <div class="dist-chart">
                <?php foreach ($distBuckets as $b => $n):
                    $h   = $n > 0 ? max(6, round($n / $distMax * 160)) : 4;
                    $col = $b >= 80 ? 'var(--ac)' : ($b >= 60 ? 'var(--warn)' : 'var(--err)');
                    $pct = round($n / $distTotal * 100, 1);
                    $tip = "{$b}–".($b+9)."% : {$n} ({$pct}%)";
                ?>
                <div class="dc-col">
                    <div class="dc-wrap">
                        <div class="dc-bar"
                             style="height:<?= $h ?>px;background:<?= $col ?>;opacity:.6"
                             data-tip="<?= htmlspecialchars($tip) ?>"></div>
                    </div>
                    <div class="dc-label"><?= $b ?></div>
                    <div class="dc-pct"><?= $n ?></div>
                </div>
                <?php endforeach ?>
            </div>
            <?php endif ?>
        </div>

        <!-- Daily avg score trend -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">Daily Avg Score Trend</div>
                <div class="card-sub">last <?= $days ?> days</div>
            </div>
            <?php if (empty($dailyTrend)): ?>
            <div class="empty-state">No trend data yet.</div>
            <?php else:
                $tMax = max(array_column($dailyTrend,'avg_score')) ?: 100;
            ?>
            <div class="trend-chart">
                <div class="tc-bars">
                    <?php foreach ($dailyTrend as $td):
                        $h   = max(4, round((float)$td['avg_score'] / $tMax * 110));
                        $col = scoreColor((float)$td['avg_score']);
                        $tip = date('M j', strtotime($td['d'])).': '.($td['avg_score']??'—').'% ('.$td['n'].' attempts)';
                    ?>
                    <div class="tc-col">
                        <div class="tc-bar"
                             style="height:<?= $h ?>px;background:<?= $col ?>"
                             data-tip="<?= htmlspecialchars($tip) ?>"></div>
                        <div class="tc-label"><?= date('j', strtotime($td['d'])) ?></div>
                    </div>
                    <?php endforeach ?>
                </div>
            </div>
            <?php endif ?>
        </div>
    </div>

    <!-- Per-quiz breakdown -->
    <div class="full-card reveal d4">
        <div class="card-head">
            <div class="card-title">Per-Quiz Score Breakdown</div>
            <div class="card-sub"><?= count($perQuiz) ?> published quizzes</div>
        </div>
        <?php if (empty($perQuiz)): ?>
        <div class="empty-state">No quiz data yet.</div>
        <?php else: ?>
        <div style="overflow-x:auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Quiz</th>
                        <th>Attempts</th>
                        <th>Completed</th>
                        <th>Avg Score</th>
                        <th>Range</th>
                        <th>Pass Rate</th>
                        <?php if ($hasTimeCol): ?><th>Avg Time</th><?php endif ?>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($perQuiz as $pq):
                    $sc = $pq['avg_score'] !== null ? (float)$pq['avg_score'] : null;
                    $pr = ($pq['completed'] > 0 && $pq['passed'] !== null)
                        ? round($pq['passed'] / $pq['completed'] * 100, 1) : null;
                    $cr = $pq['attempts'] > 0 ? round($pq['completed'] / $pq['attempts'] * 100, 0) : 0;
                ?>
                <tr>
                    <td>
                        <div style="font-weight:600;color:var(--tx);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                            <?= htmlspecialchars($pq['title']) ?>
                        </div>
                        <?php if ($pq['passing_score']): ?>
                        <div style="font-size:.625rem;color:var(--tx3)">Pass: <?= $pq['passing_score'] ?>%</div>
                        <?php endif ?>
                    </td>
                    <td><span style="font-family:var(--fm);font-size:.75rem;color:var(--tx2)"><?= number_format((int)$pq['attempts']) ?></span></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:6px">
                            <div class="pass-bar"><div class="pass-bar-fill" style="width:<?= $cr ?>%;background:var(--blue)"></div></div>
                            <span style="font-family:var(--fm);font-size:.75rem;color:var(--tx2)"><?= number_format((int)$pq['completed']) ?></span>
                        </div>
                    </td>
                    <td>
                        <?php if ($sc !== null): ?>
                        <span class="score-badge <?= scoreCls($sc) ?>"><?= $sc ?>%</span>
                        <?php else: ?><span style="color:var(--tx3)">—</span><?php endif ?>
                    </td>
                    <td>
                        <?php if ($pq['min_score'] !== null): ?>
                        <span style="font-family:var(--fm);font-size:.75rem;color:var(--tx3)"><?= $pq['min_score'] ?>–<?= $pq['max_score'] ?>%</span>
                        <?php else: ?><span style="color:var(--tx3)">—</span><?php endif ?>
                    </td>
                    <td>
                        <?php if ($pr !== null): ?>
                        <div style="display:flex;align-items:center;gap:6px">
                            <div class="pass-bar"><div class="pass-bar-fill" style="width:<?= $pr ?>%;background:<?= scoreColor($pr) ?>"></div></div>
                            <span style="font-family:var(--fm);font-size:.75rem;color:<?= scoreColor($pr) ?>"><?= $pr ?>%</span>
                        </div>
                        <?php else: ?><span style="color:var(--tx3)">—</span><?php endif ?>
                    </td>
                    <?php if ($hasTimeCol): ?>
                    <td><span style="font-family:var(--fm);font-size:.75rem;color:var(--tx3)"><?= $pq['avg_time'] ? fmtSecs((int)$pq['avg_time']) : '—' ?></span></td>
                    <?php endif ?>
                    <td><a href="/quizzes/results.php?id=<?= (int)$pq['id'] ?>" style="font-size:.75rem;color:var(--ac);text-decoration:none;font-weight:600">Details →</a></td>
                </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <?php endif ?>
    </div>

    <!-- Top & bottom performers -->
    <div class="perf-grid reveal d4">

        <!-- Top performers -->
        <div class="card">
            <div class="perf-card-head">
                <div class="perf-card-ico green">
                    <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                </div>
                <div class="perf-card-title">Top Performers</div>
                <div class="perf-card-sub">≥ 2 completed attempts</div>
            </div>
            <?php if (empty($topStudents)): ?>
            <div class="empty-state">Not enough data yet.</div>
            <?php else: ?>
            <?php foreach ($topStudents as $i => $s): ?>
            <div class="perf-row">
                <div class="perf-rank"><?= $i+1 ?></div>
                <div class="perf-name"><?= htmlspecialchars($s['student']) ?></div>
                <div class="perf-meta"><?= $s['attempts'] ?> attempts</div>
                <span class="score-badge <?= scoreCls((float)$s['avg_score']) ?>" style="margin-left:auto"><?= $s['avg_score'] ?>%</span>
            </div>
            <?php endforeach ?>
            <?php endif ?>
        </div>

        <!-- Needs support -->
        <div class="card">
            <div class="perf-card-head">
                <div class="perf-card-ico amber">
                    <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17" stroke-width="2.5"/></svg>
                </div>
                <div class="perf-card-title">Needs Support</div>
                <div class="perf-card-sub">lowest avg · ≥ 2 attempts</div>
            </div>
            <?php if (empty($bottomStudents)): ?>
            <div class="empty-state">Not enough data yet.</div>
            <?php else: ?>
            <?php foreach ($bottomStudents as $i => $s): ?>
            <div class="perf-row">
                <div class="perf-rank"><?= $i+1 ?></div>
                <div class="perf-name"><?= htmlspecialchars($s['student']) ?></div>
                <div class="perf-meta"><?= $s['attempts'] ?> attempts</div>
                <span class="score-badge <?= scoreCls((float)$s['avg_score']) ?>" style="margin-left:auto"><?= $s['avg_score'] ?>%</span>
            </div>
            <?php endforeach ?>
            <?php endif ?>
        </div>

    </div>

</main>
</body>
</html>