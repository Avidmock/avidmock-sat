<?php
/**
 * analytics/retention.php
 * Uses shared includes/head.php, includes/sidebar.php
 */
require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

$admin = currentAdmin();
$db    = Database::connect();

$allTables   = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$hasAttempts = in_array('sat_quiz_attempts', $allTables);
$usersTable  = in_array('users', $allTables) ? 'users' : (in_array('students', $allTables) ? 'students' : null);

$userNameExpr = "'Unknown'";
if ($usersTable) {
    $uc = $db->query("SHOW COLUMNS FROM `{$usersTable}`")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('first_name',$uc) && in_array('last_name',$uc)) $userNameExpr = "CONCAT(u.first_name,' ',u.last_name)";
    elseif (in_array('name',      $uc)) $userNameExpr = "u.name";
    elseif (in_array('full_name', $uc)) $userNameExpr = "u.full_name";
    elseif (in_array('email',     $uc)) $userNameExpr = "u.email";
}

$range = $_GET['range'] ?? '90';
$days  = in_array($range, ['30','60','90','180']) ? (int)$range : 90;
$since = date('Y-m-d', strtotime("-{$days} days"));

// ── Overall retention KPIs ────────────────────────────────────────────────
$totalStudents = $returningStudents = $newStudents = $churnedStudents = 0;
$avgGapDays    = null;

if ($hasAttempts && $usersTable) {
    try {
        $totalStudents     = (int)$db->query("SELECT COUNT(DISTINCT user_id) FROM sat_quiz_attempts")->fetchColumn();
        $newStudents       = (int)$db->query("SELECT COUNT(*) FROM (SELECT user_id FROM sat_quiz_attempts GROUP BY user_id HAVING MIN(created_at)>='$since') x")->fetchColumn();
        $returningStudents = (int)$db->query("SELECT COUNT(*) FROM (SELECT user_id FROM sat_quiz_attempts GROUP BY user_id HAVING MIN(created_at)<'$since' AND MAX(created_at)>='$since') x")->fetchColumn();
        $churnedStudents   = (int)$db->query("SELECT COUNT(*) FROM (SELECT user_id FROM sat_quiz_attempts GROUP BY user_id HAVING MAX(created_at)<'$since') x")->fetchColumn();
        $avgGapRow = $db->query(
            "SELECT ROUND(AVG(gap),1) AS avg_gap FROM (
                SELECT user_id, DATEDIFF(created_at, LAG(created_at) OVER (PARTITION BY user_id ORDER BY created_at)) AS gap
                FROM sat_quiz_attempts) t WHERE gap IS NOT NULL"
        )->fetch(PDO::FETCH_ASSOC);
        $avgGapDays = $avgGapRow['avg_gap'] ?? null;
    } catch (Throwable) {}
}

// ── Cohort retention table (weekly cohorts, up to 8 weeks) ────────────────
$cohortData = [];
if ($hasAttempts) {
    try {
        $cohorts = $db->query(
            "SELECT user_id, DATE_FORMAT(MIN(created_at),'%Y-%u') AS cohort_week,
                    MIN(DATE(created_at)) AS cohort_start
             FROM sat_quiz_attempts GROUP BY user_id"
        )->fetchAll(PDO::FETCH_ASSOC);

        $cohortMap = [];
        foreach ($cohorts as $c) $cohortMap[$c['cohort_start']][] = $c['user_id'];

        krsort($cohortMap);
        $latestCohorts = array_slice($cohortMap, 0, 8, true);
        ksort($latestCohorts);

        foreach ($latestCohorts as $cohortStart => $userIds) {
            $retained = [count($userIds)];
            for ($w = 1; $w <= 7; $w++) {
                $wStart = date('Y-m-d', strtotime($cohortStart . " + {$w} weeks"));
                $wEnd   = date('Y-m-d', strtotime($cohortStart . " + ".($w+1)." weeks"));
                if ($wEnd > date('Y-m-d')) { $retained[] = null; continue; }
                $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) FROM sat_quiz_attempts WHERE user_id IN ({$placeholders}) AND created_at>=? AND created_at<?");
                $stmt->execute(array_merge($userIds, [$wStart, $wEnd]));
                $retained[] = (int)$stmt->fetchColumn();
            }
            $cohortData[] = ['start'=>$cohortStart, 'size'=>count($userIds), 'retained'=>$retained];
        }
    } catch (Throwable) {}
}

// ── At-risk students (14–60 days inactive) ────────────────────────────────
$atRisk = [];
if ($hasAttempts && $usersTable) {
    try {
        $atRisk = $db->query(
            "SELECT {$userNameExpr} AS student, a.user_id,
                    COUNT(*) AS total_attempts,
                    MAX(a.created_at) AS last_attempt,
                    DATEDIFF(NOW(), MAX(a.created_at)) AS days_inactive
             FROM sat_quiz_attempts a JOIN `{$usersTable}` u ON u.id=a.user_id
             GROUP BY a.user_id
             HAVING days_inactive BETWEEN 14 AND 60
             ORDER BY days_inactive DESC LIMIT 15"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {}
}

// ── D1 / D7 / D30 retention ───────────────────────────────────────────────
$dayRetention = ['D1'=>null, 'D7'=>null, 'D30'=>null];
if ($hasAttempts) {
    try {
        foreach ([1=>'D1', 7=>'D7', 30=>'D30'] as $d => $key) {
            $cutoff   = date('Y-m-d', strtotime("-{$d} days"));
            $eligible = (int)$db->query("SELECT COUNT(DISTINCT user_id) FROM sat_quiz_attempts WHERE DATE(created_at)='$cutoff'")->fetchColumn();
            if ($eligible > 0) {
                $returned = (int)$db->query(
                    "SELECT COUNT(DISTINCT a1.user_id) FROM sat_quiz_attempts a1
                     WHERE DATE(a1.created_at)='$cutoff'
                     AND EXISTS (SELECT 1 FROM sat_quiz_attempts a2 WHERE a2.user_id=a1.user_id AND a2.created_at>='$cutoff' AND DATE(a2.created_at)>'$cutoff')"
                )->fetchColumn();
                $dayRetention[$key] = round($returned / $eligible * 100, 1);
            }
        }
    } catch (Throwable) {}
}

// ── Head setup ────────────────────────────────────────────────────────────
$pageTitle  = 'Retention Analytics — Avidmock Admin';
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
.kpi-row   { display: grid; grid-template-columns: repeat(4,1fr); gap: 12px; margin-bottom: 24px; }
.kpi       { background: var(--ink2); border: 1px solid var(--bd); border-radius: 14px; padding: 18px 16px; }
.kpi-label { font-size: .5625rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: .8px; margin-bottom: 8px; }
.kpi-val   { font-family: var(--fh); font-size: 2rem; font-weight: 900; line-height: 1; letter-spacing: -.04em; color: var(--tx); margin-bottom: 3px; }
.kpi-sub   { font-size: .625rem; color: var(--tx3); }

/* ── Dash grid ───────────────────────────────────────────────────────── */
.dash-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
.card      { background: var(--ink2); border: 1px solid var(--bd); border-radius: 16px; overflow: hidden; }
.card-head { padding: 16px 20px; border-bottom: 1px solid var(--bd); display: flex; align-items: center; justify-content: space-between; }
.card-title { font-size: .875rem; font-weight: 700; color: var(--tx); }
.card-sub   { font-size: .625rem; color: var(--tx3); }

/* ── Day retention bars ──────────────────────────────────────────────── */
.ret-metrics { padding: 20px; display: flex; flex-direction: column; gap: 14px; }
.ret-row     { display: flex; align-items: center; gap: 12px; }
.ret-label   { font-size: .8125rem; font-weight: 700; color: var(--tx2); width: 36px; flex-shrink: 0; }
.ret-track   { flex: 1; height: 12px; background: var(--sf2); border-radius: 6px; overflow: hidden; }
.ret-fill    { height: 100%; border-radius: 6px; transition: width .7s cubic-bezier(.16,1,.3,1); }
.ret-val     { font-family: var(--fm); font-size: .75rem; font-weight: 700; width: 40px; text-align: right; }

/* ── Student composition ─────────────────────────────────────────────── */
.breakdown { padding: 20px; display: flex; flex-direction: column; gap: 12px; }
.bk-row    { display: flex; align-items: center; gap: 10px; }
.bk-dot    { width: 8px; height: 8px; border-radius: 2px; flex-shrink: 0; }
.bk-label  { font-size: .75rem; color: var(--tx2); flex: 1; }
.bk-track  { width: 80px; height: 8px; background: var(--sf2); border-radius: 4px; overflow: hidden; }
.bk-fill   { height: 100%; border-radius: 4px; transition: width .6s; }
.bk-val    { font-family: var(--fm); font-size: .6875rem; color: var(--tx3); width: 36px; text-align: right; }

/* ── Cohort table ────────────────────────────────────────────────────── */
.full-card    { background: var(--ink2); border: 1px solid var(--bd); border-radius: 16px; overflow: hidden; margin-bottom: 20px; }
.cohort-table { width: 100%; border-collapse: collapse; }
.cohort-table th { padding: 8px 12px; font-size: .5rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: .8px; text-align: center; border-bottom: 1px solid var(--bd); background: rgba(255,255,255,.015); }
.cohort-table th:first-child { text-align: left; }
.cohort-table td { padding: 9px 12px; font-size: .75rem; border-bottom: 1px solid rgba(255,255,255,.04); text-align: center; font-family: var(--fm); }
.cohort-table td:first-child { text-align: left; font-family: var(--ff); font-weight: 600; color: var(--tx2); }
.cohort-cell { display: inline-block; padding: 3px 8px; border-radius: 5px; font-size: .6875rem; font-weight: 700; min-width: 36px; }

/* ── At-risk table ───────────────────────────────────────────────────── */
.data-table   { width: 100%; border-collapse: collapse; }
.data-table th { padding: 9px 16px; font-size: .5rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: .8px; text-align: left; border-bottom: 1px solid var(--bd); background: rgba(255,255,255,.015); }
.data-table td { padding: 11px 16px; font-size: .8125rem; border-bottom: 1px solid rgba(255,255,255,.04); vertical-align: middle; }
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover { background: rgba(255,255,255,.025); }
.risk-badge  { font-size: .5rem; font-weight: 800; padding: 2px 8px; border-radius: 50px; text-transform: uppercase; }
.risk-hi  { background: var(--err2);  color: var(--err); }
.risk-md  { background: var(--warn2); color: var(--warn); }
.risk-lo  { background: var(--sf2);   color: var(--tx3); }

/* ── Utilities ───────────────────────────────────────────────────────── */
.empty-state { text-align: center; padding: 40px; color: var(--tx3); font-size: .875rem; }
.reveal { opacity: 0; transform: translateY(12px); animation: rev .4s cubic-bezier(.16,1,.3,1) forwards; }
@keyframes rev { to { opacity: 1; transform: none; } }
.d1{animation-delay:.04s} .d2{animation-delay:.08s} .d3{animation-delay:.12s} .d4{animation-delay:.16s}

/* ── Responsive ──────────────────────────────────────────────────────── */
@media (max-width: 1000px) { .dash-grid { grid-template-columns: 1fr; } }
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
        <span> / Retention</span>
    </div>
    <div class="topbar-spacer"></div>
    <div class="range-tabs">
        <?php foreach (['30'=>'30d','60'=>'60d','90'=>'90d','180'=>'180d'] as $v => $l): ?>
        <a href="?range=<?= $v ?>" class="range-tab <?= $range == $v ? 'active' : '' ?>"><?= $l ?></a>
        <?php endforeach ?>
    </div>
</header>

<!-- Main -->
<main class="main">

    <div class="ph-eyebrow"><span class="ph-dot"></span>Retention Analytics</div>
    <h1 class="ph-title reveal d1">Student Retention</h1>
    <p class="ph-sub reveal d1">Return rates, cohort analysis and churn indicators · last <?= $days ?> days</p>

    <!-- KPI row -->
    <div class="kpi-row reveal d2">
        <div class="kpi">
            <div class="kpi-label">Total Students Ever</div>
            <div class="kpi-val"><?= number_format($totalStudents) ?></div>
            <div class="kpi-sub">have attempted a quiz</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Returning</div>
            <div class="kpi-val" style="color:var(--ac)"><?= number_format($returningStudents) ?></div>
            <div class="kpi-sub">returned this period</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">New</div>
            <div class="kpi-val" style="color:var(--blue)"><?= number_format($newStudents) ?></div>
            <div class="kpi-sub">first attempt this period</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Avg Days Between Sessions</div>
            <div class="kpi-val" style="font-size:1.25rem"><?= $avgGapDays !== null ? $avgGapDays.'d' : '—' ?></div>
            <div class="kpi-sub">shorter = more engaged</div>
        </div>
    </div>

    <!-- D1/D7/D30 + composition -->
    <div class="dash-grid reveal d3">

        <!-- Day retention rates -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">Day Retention Rates</div>
                <div class="card-sub">returned after N days</div>
            </div>
            <div class="ret-metrics">
                <?php foreach ($dayRetention as $lbl => $val):
                    $col = $val === null ? 'var(--sf2)' : ($val >= 60 ? 'var(--ac)' : ($val >= 30 ? 'var(--warn)' : 'var(--err)'));
                    $w   = $val ?? 0;
                ?>
                <div class="ret-row">
                    <div class="ret-label"><?= $lbl ?></div>
                    <div class="ret-track"><div class="ret-fill" style="width:<?= $w ?>%;background:<?= $col ?>"></div></div>
                    <div class="ret-val" style="color:<?= $col ?>"><?= $val !== null ? $val.'%' : '—' ?></div>
                </div>
                <?php endforeach ?>
                <div style="font-size:.6875rem;color:var(--tx3);margin-top:4px">Based on students who started exactly N days ago</div>
            </div>
        </div>

        <!-- Student composition -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">Student Composition</div>
                <div class="card-sub">this period</div>
            </div>
            <?php
            $bkTotal   = ($newStudents + $returningStudents + $churnedStudents) ?: 1;
            $breakdown = [
                ['New',       $newStudents,       'var(--blue)', 'var(--blue2)'],
                ['Returning', $returningStudents, 'var(--ac)',   'var(--ac3)'],
                ['Churned',   $churnedStudents,   'var(--err)',  'var(--err2)'],
            ];
            ?>
            <div class="breakdown">
                <?php foreach ($breakdown as [$lbl, $n, $col, $bg]):
                    $pct = round($n / $bkTotal * 100);
                ?>
                <div class="bk-row">
                    <div class="bk-dot" style="background:<?= $col ?>"></div>
                    <div class="bk-label"><?= $lbl ?></div>
                    <div class="bk-track"><div class="bk-fill" style="width:<?= $pct ?>%;background:<?= $col ?>"></div></div>
                    <div class="bk-val"><?= number_format($n) ?></div>
                </div>
                <?php endforeach ?>
                <div style="font-size:.6875rem;color:var(--tx3);margin-top:6px">Churned = last activity before this period</div>
            </div>
        </div>
    </div>

    <!-- Cohort retention table -->
    <div class="full-card reveal d3">
        <div class="card-head">
            <div class="card-title">Weekly Cohort Retention</div>
            <div class="card-sub">% of cohort returning each week</div>
        </div>
        <?php if (empty($cohortData)): ?>
        <div class="empty-state">Not enough data for cohort analysis yet.</div>
        <?php else: ?>
        <div style="overflow-x:auto">
            <table class="cohort-table">
                <thead>
                    <tr>
                        <th>Cohort</th>
                        <th>Size</th>
                        <?php for ($w = 0; $w < 8; $w++): ?><th>Wk <?= $w ?></th><?php endfor ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($cohortData as $cohort):
                    $cohortSize = $cohort['size'] ?: 1;
                ?>
                <tr>
                    <td><?= date('M j', strtotime($cohort['start'])) ?></td>
                    <td><span style="font-family:var(--fm);color:var(--tx3)"><?= number_format($cohort['size']) ?></span></td>
                    <?php foreach ($cohort['retained'] as $wk => $ret):
                        if ($ret === null) { echo '<td><span style="color:var(--tx3)">—</span></td>'; continue; }
                        $pct   = round($ret / $cohortSize * 100);
                        $alpha = max(.07, $pct / 100 * .75);
                        $col   = $pct >= 80 ? 'var(--ac)' : ($pct >= 50 ? 'var(--warn)' : 'var(--err)');
                    ?>
                    <td><span class="cohort-cell" style="background:rgba(31,226,144,<?= $alpha ?>);color:<?= $col ?>"><?= $pct ?>%</span></td>
                    <?php endforeach ?>
                </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <?php endif ?>
    </div>

    <!-- At-risk students -->
    <div class="full-card reveal d4">
        <div class="card-head">
            <div class="card-title">At-Risk Students</div>
            <div class="card-sub">14–60 days inactive</div>
        </div>
        <?php if (empty($atRisk)): ?>
        <div class="empty-state">No at-risk students found.</div>
        <?php else: ?>
        <div style="overflow-x:auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Last Active</th>
                        <th>Days Inactive</th>
                        <th>Total Attempts</th>
                        <th>Risk</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($atRisk as $s):
                    $d         = (int)$s['days_inactive'];
                    $risk      = $d >= 30 ? 'risk-hi' : ($d >= 20 ? 'risk-md' : 'risk-lo');
                    $riskLabel = $d >= 30 ? 'High'    : ($d >= 20 ? 'Medium'  : 'Low');
                ?>
                <tr>
                    <td style="font-weight:600;color:var(--tx)"><?= htmlspecialchars($s['student']) ?></td>
                    <td style="font-size:.75rem;color:var(--tx3);font-family:var(--fm)"><?= date('M j, Y', strtotime($s['last_attempt'])) ?></td>
                    <td><span style="font-family:var(--fm);font-size:.8125rem;color:var(--warn)"><?= $d ?> days</span></td>
                    <td style="font-family:var(--fm);font-size:.75rem;color:var(--tx2)"><?= number_format((int)$s['total_attempts']) ?></td>
                    <td><span class="risk-badge <?= $risk ?>"><?= $riskLabel ?></span></td>
                    <td><a href="/students/student.php?id=<?= (int)$s['user_id'] ?>" style="font-size:.75rem;color:var(--ac);text-decoration:none;font-weight:600">View →</a></td>
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