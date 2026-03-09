<?php
/**
 * analytics/engagement.php
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

$range = $_GET['range'] ?? '30';
$days  = in_array($range, ['7','30','90','365']) ? (int)$range : 30;
$since = date('Y-m-d', strtotime("-{$days} days"));

$attemptCols = $hasAttempts ? $db->query("SHOW COLUMNS FROM sat_quiz_attempts")->fetchAll(PDO::FETCH_COLUMN) : [];
$hasTimeCol  = in_array('time_spent', $attemptCols);

// ── KPI stats ─────────────────────────────────────────────────────────────
$kpi = ['dau'=>0,'wau'=>0,'mau'=>0,'total_attempts'=>0,'completed'=>0];
if ($hasAttempts) {
    try {
        $kpi['mau'] = (int)$db->query("SELECT COUNT(DISTINCT user_id) FROM sat_quiz_attempts WHERE created_at >= '".date('Y-m-d',strtotime('-30 days'))."'")->fetchColumn();
        $kpi['wau'] = (int)$db->query("SELECT COUNT(DISTINCT user_id) FROM sat_quiz_attempts WHERE created_at >= '".date('Y-m-d',strtotime('-7 days'))."'")->fetchColumn();
        $kpi['dau'] = (int)$db->query("SELECT COUNT(DISTINCT user_id) FROM sat_quiz_attempts WHERE DATE(created_at) = CURDATE()")->fetchColumn();

        $r = $db->prepare("SELECT COUNT(*) AS total, SUM(status='completed') AS completed FROM sat_quiz_attempts WHERE created_at >= :s");
        $r->execute([':s' => $since]);
        $row = $r->fetch(PDO::FETCH_ASSOC);
        $kpi['total_attempts'] = (int)($row['total']    ?? 0);
        $kpi['completed']      = (int)($row['completed'] ?? 0);
    } catch (Throwable) {}
}

// ── Daily active users ────────────────────────────────────────────────────
$dailyActive = [];
if ($hasAttempts) {
    try {
        $da = $db->prepare(
            "SELECT DATE(created_at) AS d, COUNT(DISTINCT user_id) AS users, COUNT(*) AS attempts
             FROM sat_quiz_attempts WHERE created_at >= :s
             GROUP BY DATE(created_at) ORDER BY d ASC"
        );
        $da->execute([':s' => $since]);
        $dailyActive = $da->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {}
}
$dauMap = [];
foreach ($dailyActive as $r) $dauMap[$r['d']] = $r;
$filledDAU = [];
for ($i = $days - 1; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $filledDAU[] = ['d'=>$d, 'users'=>(int)($dauMap[$d]['users']??0), 'attempts'=>(int)($dauMap[$d]['attempts']??0)];
}
$dauMax = max(array_column($filledDAU, 'users')) ?: 1;

// ── Hour-of-day heatmap ───────────────────────────────────────────────────
$hourHeat = array_fill(0, 24, 0);
if ($hasAttempts) {
    try {
        $hh = $db->prepare("SELECT HOUR(created_at) AS h, COUNT(*) AS n FROM sat_quiz_attempts WHERE created_at >= :s GROUP BY h");
        $hh->execute([':s' => $since]);
        foreach ($hh->fetchAll(PDO::FETCH_ASSOC) as $r) $hourHeat[(int)$r['h']] = (int)$r['n'];
    } catch (Throwable) {}
}
$heatMax = max($hourHeat) ?: 1;

// ── Day-of-week activity ──────────────────────────────────────────────────
$dowHeat = array_fill(0, 7, 0);
if ($hasAttempts) {
    try {
        $dw = $db->prepare("SELECT DAYOFWEEK(created_at)-1 AS dow, COUNT(*) AS n FROM sat_quiz_attempts WHERE created_at >= :s GROUP BY dow");
        $dw->execute([':s' => $since]);
        foreach ($dw->fetchAll(PDO::FETCH_ASSOC) as $r) $dowHeat[(int)$r['dow']] = (int)$r['n'];
    } catch (Throwable) {}
}

// ── Most active students ──────────────────────────────────────────────────
$activeStudents = [];
if ($hasAttempts && $usersTable) {
    try {
        $as = $db->prepare(
            "SELECT {$userNameExpr} AS student, a.user_id,
                    COUNT(*) AS attempts, SUM(a.status='completed') AS completed,
                    MAX(a.created_at) AS last_seen
             FROM sat_quiz_attempts a JOIN `{$usersTable}` u ON u.id=a.user_id
             WHERE a.created_at >= :s
             GROUP BY a.user_id ORDER BY attempts DESC LIMIT 10"
        );
        $as->execute([':s' => $since]);
        $activeStudents = $as->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {}
}

// ── Session depth ─────────────────────────────────────────────────────────
$depthBuckets = ['1'=>0, '2-3'=>0, '4-5'=>0, '6+'=>0];
if ($hasAttempts) {
    try {
        $sd = $db->prepare("SELECT user_id, DATE(created_at) AS d, COUNT(*) AS n FROM sat_quiz_attempts WHERE created_at >= :s GROUP BY user_id, DATE(created_at)");
        $sd->execute([':s' => $since]);
        foreach ($sd->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $n = (int)$r['n'];
            if ($n === 1)      $depthBuckets['1']++;
            elseif ($n <= 3)   $depthBuckets['2-3']++;
            elseif ($n <= 5)   $depthBuckets['4-5']++;
            else               $depthBuckets['6+']++;
        }
    } catch (Throwable) {}
}
$depthTotal = array_sum($depthBuckets) ?: 1;

$dowLabels = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

function timeAgo(string $ts): string {
    $d = time() - strtotime($ts);
    if ($d < 60)    return $d.'s ago';
    if ($d < 3600)  return floor($d/60).'m ago';
    if ($d < 86400) return floor($d/3600).'h ago';
    return date('M j', strtotime($ts));
}

// ── Head setup ────────────────────────────────────────────────────────────
$pageTitle  = 'Engagement Analytics — Avidmock Admin';
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
.kpi-sub   { font-size: .625rem; color: var(--tx3); }

/* ── Dash grids ──────────────────────────────────────────────────────── */
.dash-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
.card      { background: var(--ink2); border: 1px solid var(--bd); border-radius: 16px; overflow: hidden; }
.card-head { padding: 16px 20px; border-bottom: 1px solid var(--bd); display: flex; align-items: center; justify-content: space-between; }
.card-title { font-size: .875rem; font-weight: 700; color: var(--tx); }
.card-sub   { font-size: .625rem; color: var(--tx3); }

/* ── DAU chart ───────────────────────────────────────────────────────── */
.dau-chart { padding: 20px; display: flex; align-items: flex-end; gap: 3px; height: 140px; }
.dau-col   { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 2px; cursor: default; position: relative; }
.dau-bar   { width: 100%; border-radius: 3px 3px 0 0; background: var(--blue); opacity: .5; min-height: 2px; transition: opacity .16s; }
.dau-bar:hover { opacity: .9; }
.dau-bar::after {
    content: attr(data-tip); position: absolute; bottom: calc(100% + 5px); left: 50%; transform: translateX(-50%);
    background: var(--ink2); border: 1px solid var(--bd2); padding: 3px 7px; border-radius: 5px;
    font-size: .5rem; color: var(--tx2); white-space: nowrap; pointer-events: none;
    opacity: 0; transition: opacity .16s; z-index: 10;
}
.dau-bar:hover::after { opacity: 1; }
.dau-label { font-size: .375rem; color: var(--tx3); font-family: var(--fm); }

/* ── Heatmap ─────────────────────────────────────────────────────────── */
.heatmap    { padding: 16px 20px; }
.hm-row     { display: flex; gap: 3px; margin-bottom: 3px; align-items: center; }
.hm-label   { font-size: .5rem; color: var(--tx3); font-family: var(--fm); width: 28px; flex-shrink: 0; text-align: right; }
.hm-cell    { flex: 1; height: 22px; border-radius: 4px; cursor: default; position: relative; transition: opacity .16s; }
.hm-cell:hover { opacity: .75; }
.hm-cell::after {
    content: attr(data-tip); position: absolute; bottom: calc(100% + 4px); left: 50%; transform: translateX(-50%);
    background: var(--ink2); border: 1px solid var(--bd2); padding: 3px 7px; border-radius: 5px;
    font-size: .5rem; color: var(--tx2); white-space: nowrap; pointer-events: none;
    opacity: 0; transition: opacity .16s; z-index: 10;
}
.hm-cell:hover::after { opacity: 1; }
.hm-hours   { display: flex; gap: 3px; margin-top: 4px; padding-left: 32px; }
.hm-h-label { flex: 1; font-size: .375rem; color: var(--tx3); font-family: var(--fm); text-align: center; }

/* ── Shared bar rows (depth, funnel, dow) ────────────────────────────── */
.depth-chart { padding: 20px; display: flex; flex-direction: column; gap: 10px; }
.depth-row   { display: flex; align-items: center; gap: 10px; }
.depth-lbl   { font-size: .75rem; font-weight: 700; color: var(--tx2); width: 40px; flex-shrink: 0; }
.depth-track { flex: 1; height: 10px; background: var(--sf2); border-radius: 5px; overflow: hidden; }
.depth-fill  { height: 100%; border-radius: 5px; background: var(--blue); transition: width .6s cubic-bezier(.16,1,.3,1); }
.depth-n     { font-family: var(--fm); font-size: .625rem; color: var(--tx3); width: 60px; text-align: right; }

/* ── Active students table ───────────────────────────────────────────── */
.full-card    { background: var(--ink2); border: 1px solid var(--bd); border-radius: 16px; overflow: hidden; margin-bottom: 20px; }
.data-table   { width: 100%; border-collapse: collapse; }
.data-table th { padding: 9px 16px; font-size: .5rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: .8px; text-align: left; border-bottom: 1px solid var(--bd); background: rgba(255,255,255,.015); }
.data-table td { padding: 11px 16px; font-size: .8125rem; border-bottom: 1px solid rgba(255,255,255,.04); vertical-align: middle; }
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover { background: rgba(255,255,255,.025); }
.progress-bar      { height: 5px; border-radius: 3px; overflow: hidden; background: var(--sf2); width: 60px; display: inline-block; vertical-align: middle; margin-right: 6px; }
.progress-bar-fill { height: 100%; border-radius: 3px; background: var(--ac); }

/* ── Utilities ───────────────────────────────────────────────────────── */
.empty-state { text-align: center; padding: 40px; color: var(--tx3); font-size: .875rem; }
.reveal { opacity: 0; transform: translateY(12px); animation: rev .4s cubic-bezier(.16,1,.3,1) forwards; }
@keyframes rev { to { opacity: 1; transform: none; } }
.d1{animation-delay:.04s} .d2{animation-delay:.08s} .d3{animation-delay:.12s} .d4{animation-delay:.16s}

/* ── Responsive ──────────────────────────────────────────────────────── */
@media (max-width: 1100px) { .kpi-row { grid-template-columns: repeat(3,1fr); } .dash-grid { grid-template-columns: 1fr; } }
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
        <span> / Engagement</span>
    </div>
    <div class="topbar-spacer"></div>
    <div class="range-tabs">
        <?php foreach (['7'=>'7d','30'=>'30d','90'=>'90d','365'=>'1y'] as $v => $l): ?>
        <a href="?range=<?= $v ?>" class="range-tab <?= $range == $v ? 'active' : '' ?>"><?= $l ?></a>
        <?php endforeach ?>
    </div>
</header>

<!-- Main -->
<main class="main">

    <div class="ph-eyebrow"><span class="ph-dot"></span>Engagement Analytics</div>
    <h1 class="ph-title reveal d1">Student Engagement</h1>
    <p class="ph-sub reveal d1">Active users, session depth and activity patterns · last <?= $days ?> days</p>

    <!-- KPI row -->
    <div class="kpi-row reveal d2">
        <div class="kpi">
            <div class="kpi-label">DAU (today)</div>
            <div class="kpi-val"><?= number_format($kpi['dau']) ?></div>
            <div class="kpi-sub">daily active users</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">WAU (7 days)</div>
            <div class="kpi-val"><?= number_format($kpi['wau']) ?></div>
            <div class="kpi-sub">weekly active users</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">MAU (30 days)</div>
            <div class="kpi-val"><?= number_format($kpi['mau']) ?></div>
            <div class="kpi-sub">monthly active users</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">DAU/MAU Ratio</div>
            <div class="kpi-val"><?= $kpi['mau'] > 0 ? round($kpi['dau'] / $kpi['mau'] * 100, 1).'%' : '—' ?></div>
            <div class="kpi-sub">stickiness</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Total Attempts</div>
            <div class="kpi-val"><?= number_format($kpi['total_attempts']) ?></div>
            <div class="kpi-sub"><?= number_format($kpi['completed']) ?> completed</div>
        </div>
    </div>

    <!-- Row 1: DAU chart + Hour heatmap -->
    <div class="dash-grid reveal d3">

        <!-- Daily active users -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">Daily Active Users</div>
                <div class="card-sub">last <?= $days ?> days</div>
            </div>
            <?php if (!array_sum(array_column($filledDAU, 'users'))): ?>
            <div class="empty-state">No data yet.</div>
            <?php else: ?>
            <div class="dau-chart">
                <?php foreach ($filledDAU as $day):
                    $h   = $day['users'] > 0 ? max(3, round($day['users'] / $dauMax * 130)) : 2;
                    $tip = date('M j', strtotime($day['d'])).': '.$day['users'].' users, '.$day['attempts'].' attempts';
                ?>
                <div class="dau-col">
                    <div class="dau-bar" style="height:<?= $h ?>px" data-tip="<?= htmlspecialchars($tip) ?>"></div>
                    <?php if (count($filledDAU) <= 14 || date('D', strtotime($day['d'])) === 'Mon'): ?>
                    <div class="dau-label"><?= date('M j', strtotime($day['d'])) ?></div>
                    <?php else: ?><div class="dau-label"></div><?php endif ?>
                </div>
                <?php endforeach ?>
            </div>
            <?php endif ?>
        </div>

        <!-- Activity by hour + day of week -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">Activity by Hour</div>
                <div class="card-sub">attempts per hour of day</div>
            </div>
            <?php if (!array_sum($hourHeat)): ?>
            <div class="empty-state">No data yet.</div>
            <?php else: ?>
            <div class="heatmap">
                <!-- Hour heatmap row -->
                <div class="hm-row" style="margin-bottom:16px">
                    <div class="hm-label">Hrs</div>
                    <?php for ($h = 0; $h < 24; $h++):
                        $n     = $hourHeat[$h];
                        $alpha = max(.06, ($n / $heatMax) * .75);
                        $tip   = ($h < 12 ? $h.'am' : ($h === 12 ? '12pm' : ($h-12).'pm')).': '.$n.' attempts';
                    ?>
                    <div class="hm-cell"
                         style="background:rgba(31,226,144,<?= $alpha ?>)"
                         data-tip="<?= htmlspecialchars($tip) ?>"></div>
                    <?php endfor ?>
                </div>
                <div class="hm-hours">
                    <?php for ($h = 0; $h < 24; $h += 3): ?>
                    <div class="hm-h-label" style="flex:3"><?= $h > 0 ? ($h < 12 ? $h.'a' : ($h === 12 ? '12p' : ($h-12).'p')) : '12a' ?></div>
                    <?php endfor ?>
                </div>

                <!-- Day of week -->
                <div style="margin-top:18px;font-size:.5625rem;font-weight:700;color:var(--tx3);text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px">
                    By Day of Week
                </div>
                <?php
                $dowMax = max($dowHeat) ?: 1;
                foreach ($dowHeat as $d => $n):
                    $pct = round($n / $dowMax * 100);
                ?>
                <div class="depth-row">
                    <div class="depth-lbl"><?= $dowLabels[$d] ?></div>
                    <div class="depth-track"><div class="depth-fill" style="width:<?= $pct ?>%;background:var(--blue)"></div></div>
                    <div class="depth-n"><?= number_format($n) ?></div>
                </div>
                <?php endforeach ?>
            </div>
            <?php endif ?>
        </div>
    </div>

    <!-- Row 2: Session depth + Completion funnel -->
    <div class="dash-grid reveal d4">

        <!-- Session depth -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">Session Depth</div>
                <div class="card-sub">attempts per user per day</div>
            </div>
            <?php if (!array_sum($depthBuckets)): ?>
            <div class="empty-state">No data yet.</div>
            <?php else: ?>
            <div class="depth-chart">
                <?php foreach ($depthBuckets as $label => $n):
                    $pct = round($n / $depthTotal * 100);
                ?>
                <div class="depth-row">
                    <div class="depth-lbl"><?= $label ?></div>
                    <div class="depth-track"><div class="depth-fill" style="width:<?= $pct ?>%"></div></div>
                    <div class="depth-n"><?= number_format($n) ?> (<?= $pct ?>%)</div>
                </div>
                <?php endforeach ?>
                <div style="font-size:.6875rem;color:var(--tx3);margin-top:8px">
                    <?= number_format(array_sum($depthBuckets)) ?> total user-day sessions
                </div>
            </div>
            <?php endif ?>
        </div>

        <!-- Completion funnel -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">Completion Funnel</div>
                <div class="card-sub">this period</div>
            </div>
            <?php
            $fTotal    = $kpi['total_attempts'] ?: 1;
            $fComplete = $kpi['completed'];
            $dropRate  = $fTotal > 0 ? round((1 - $fComplete / $fTotal) * 100, 1) : 0;
            $funnel = ['Started' => $fTotal, 'Completed' => $fComplete];
            ?>
            <div class="depth-chart">
                <?php foreach ($funnel as $lbl => $n):
                    $pct = round($n / $fTotal * 100);
                    $col = $lbl === 'Completed' ? 'var(--ac)' : 'var(--blue)';
                ?>
                <div class="depth-row">
                    <div style="font-size:.75rem;font-weight:700;color:var(--tx2);width:80px;flex-shrink:0"><?= $lbl ?></div>
                    <div class="depth-track"><div class="depth-fill" style="width:<?= $pct ?>%;background:<?= $col ?>"></div></div>
                    <div class="depth-n"><?= number_format($n) ?></div>
                </div>
                <?php endforeach ?>
                <div style="font-size:.6875rem;color:var(--tx3);margin-top:10px">
                    Drop-off rate: <span style="color:var(--err);font-family:var(--fm)"><?= $dropRate ?>%</span>
                    &nbsp;·&nbsp;
                    Completion rate: <span style="color:var(--ac);font-family:var(--fm)"><?= 100 - $dropRate ?>%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Most active students -->
    <div class="full-card reveal d4">
        <div class="card-head">
            <div class="card-title">Most Active Students</div>
            <div class="card-sub">last <?= $days ?> days</div>
        </div>
        <?php if (empty($activeStudents)): ?>
        <div class="empty-state">No student data yet.</div>
        <?php else: ?>
        <div style="overflow-x:auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Attempts</th>
                        <th>Completed</th>
                        <th>Completion Rate</th>
                        <th>Last Seen</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($activeStudents as $i => $s):
                    $compRate = $s['attempts'] > 0 ? round($s['completed'] / $s['attempts'] * 100, 0) : 0;
                ?>
                <tr>
                    <td><span style="font-family:var(--fm);font-size:.625rem;color:var(--tx3)"><?= $i+1 ?></span></td>
                    <td><span style="font-weight:600;color:var(--tx)"><?= htmlspecialchars($s['student']) ?></span></td>
                    <td><span style="font-family:var(--fm);font-size:.75rem;color:var(--tx2)"><?= number_format((int)$s['attempts']) ?></span></td>
                    <td><span style="font-family:var(--fm);font-size:.75rem;color:var(--tx2)"><?= number_format((int)$s['completed']) ?></span></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:6px">
                            <div class="progress-bar"><div class="progress-bar-fill" style="width:<?= $compRate ?>%"></div></div>
                            <span style="font-family:var(--fm);font-size:.75rem;color:var(--ac)"><?= $compRate ?>%</span>
                        </div>
                    </td>
                    <td><span style="font-size:.625rem;color:var(--tx3);font-family:var(--fm)"><?= $s['last_seen'] ? timeAgo($s['last_seen']) : '—' ?></span></td>
                    <td><a href="/students/student.php?id=<?= (int)$s['user_id'] ?>" style="font-size:.75rem;color:var(--ac);text-decoration:none;font-weight:600">Profile →</a></td>
                </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <?php endif ?>
    </div>

</main>
</body>
</html>