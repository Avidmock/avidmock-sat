<?php
/**
 * practice-tests/results.php
 * Detailed results for a single practice test — score distribution, per-student
 * breakdown, question accuracy, time analysis, pass/fail funnel.
 */
require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

$admin = currentAdmin();
$db    = Database::connect();

$id = (int)($_GET['id'] ?? 0);
if ($id < 1) { header('Location: /practice-tests/index.php'); exit; }

$range = $_GET['range'] ?? '30';
$days  = in_array($range, ['7','30','90','365']) ? (int)$range : 30;
$since = date('Y-m-d', strtotime("-{$days} days"));

// ── Schema detection ──────────────────────────────────────────────────────
$allTables   = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$ptTable     = in_array('practice_tests',$allTables) ? 'practice_tests'
             : (in_array('sat_practice_tests',$allTables) ? 'sat_practice_tests' : null);
$hasAttempts = in_array('sat_quiz_attempts',  $allTables);
$hasAnswers  = in_array('sat_quiz_answers',   $allTables);
$hasQuestions= in_array('sat_quiz_questions', $allTables);
$usersTable  = in_array('users',$allTables)?'users':(in_array('students',$allTables)?'students':null);

if (!$ptTable) { header('Location: /practice-tests/index.php'); exit; }

// ── Load the test ─────────────────────────────────────────────────────────
$test = null;
try {
    $s = $db->prepare("SELECT * FROM `{$ptTable}` WHERE id=:id");
    $s->execute([':id'=>$id]);
    $test = $s->fetch(PDO::FETCH_ASSOC);
} catch (Throwable) {}
if (!$test) { header('Location: /practice-tests/index.php'); exit; }

$ptCols      = $db->query("SHOW COLUMNS FROM `{$ptTable}`")->fetchAll(PDO::FETCH_COLUMN);
$hasPassCol  = in_array('passing_score', $ptCols);
$hasSectionC = in_array('section',       $ptCols);
$hasTimeC    = in_array('time_limit',    $ptCols);

// ── User name expression ──────────────────────────────────────────────────
$nameExpr = "'Unknown'";
if ($usersTable) {
    try {
        $uc = $db->query("SHOW COLUMNS FROM `{$usersTable}`")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('first_name',$uc)&&in_array('last_name',$uc))
            $nameExpr = "CONCAT(u.first_name,' ',u.last_name)";
        elseif (in_array('name',$uc))   $nameExpr = "u.name";
        elseif (in_array('full_name',$uc)) $nameExpr = "u.full_name";
        elseif (in_array('email',$uc))  $nameExpr = "u.email";
    } catch (Throwable) {}
}

// ── Attempt cols detection ────────────────────────────────────────────────
$attCols    = $hasAttempts ? $db->query("SHOW COLUMNS FROM sat_quiz_attempts")->fetchAll(PDO::FETCH_COLUMN) : [];
$hasTimeSpent = in_array('time_spent', $attCols);
$hasScoreCol  = in_array('score',      $attCols);
$hasStatus    = in_array('status',     $attCols);

// ── KPIs ──────────────────────────────────────────────────────────────────
$kpi = ['total'=>0,'completed'=>0,'avg_score'=>null,'pass_rate'=>null,'avg_time'=>null,'unique_students'=>0];

if ($hasAttempts) {
    try {
        $kr = $db->prepare("SELECT
            COUNT(*) AS total,
            SUM(".($hasStatus?"status='completed'":"1").") AS completed,
            " .($hasScoreCol?"ROUND(AVG(CASE WHEN ".($hasStatus?"status='completed'":"1=1")." THEN score END),1)":"NULL")." AS avg_score,
            " .($hasTimeSpent?"ROUND(AVG(CASE WHEN ".($hasStatus?"status='completed'":"1=1")." THEN time_spent END))":"NULL")." AS avg_time,
            COUNT(DISTINCT user_id) AS unique_students
            FROM sat_quiz_attempts
            WHERE quiz_id=:id AND created_at>=:since");
        $kr->execute([':id'=>$id, ':since'=>$since]);
        $row = $kr->fetch(PDO::FETCH_ASSOC);
        if ($row) $kpi = array_merge($kpi, $row);
    } catch (Throwable) {}

    // Pass rate
    if ($hasPassCol && $test['passing_score'] && $hasScoreCol) {
        try {
            $pr = $db->prepare("SELECT ROUND(AVG(CASE WHEN score>=:ps THEN 1 ELSE 0 END)*100,1)
                FROM sat_quiz_attempts WHERE quiz_id=:id AND status='completed' AND created_at>=:since");
            $pr->execute([':ps'=>$test['passing_score'],':id'=>$id,':since'=>$since]);
            $kpi['pass_rate'] = $pr->fetchColumn();
        } catch (Throwable) {}
    }
}

// ── Score distribution (10% buckets) ─────────────────────────────────────
$scoreDist = array_fill(0, 10, 0); // 0-9%,10-19%,…90-100%
if ($hasAttempts && $hasScoreCol) {
    try {
        $sd = $db->prepare("SELECT FLOOR(score/10) AS bucket, COUNT(*) AS n
            FROM sat_quiz_attempts WHERE quiz_id=:id AND ".($hasStatus?"status='completed'":"1=1")." AND created_at>=:since
            GROUP BY bucket");
        $sd->execute([':id'=>$id, ':since'=>$since]);
        foreach ($sd->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $b = min(9, max(0, (int)$row['bucket']));
            $scoreDist[$b] += (int)$row['n'];
        }
    } catch (Throwable) {}
}
$scoreDistMax = max(max($scoreDist), 1);

// ── Daily attempts trend ──────────────────────────────────────────────────
$dailyTrend = [];
if ($hasAttempts) {
    try {
        $dt = $db->prepare("SELECT DATE(created_at) AS d, COUNT(*) AS n
            FROM sat_quiz_attempts WHERE quiz_id=:id AND created_at>=:since
            GROUP BY d ORDER BY d ASC");
        $dt->execute([':id'=>$id, ':since'=>$since]);
        $raw = $dt->fetchAll(PDO::FETCH_KEY_PAIR);
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $dailyTrend[] = ['date'=>$date,'n'=>(int)($raw[$date]??0)];
        }
    } catch (Throwable) {}
}
$trendMax = max(array_column($dailyTrend,'n')?:[1]);

// ── Per-student results ───────────────────────────────────────────────────
$studentResults = [];
if ($hasAttempts) {
    try {
        $userJoin = $usersTable ? "LEFT JOIN `{$usersTable}` u ON u.id=a.user_id" : "";
        $nameSel  = $usersTable ? $nameExpr : "'Unknown'";
        $emailSel = ($usersTable && in_array('email', $db->query("SHOW COLUMNS FROM `{$usersTable}`")->fetchAll(PDO::FETCH_COLUMN))) ? "u.email" : "NULL";
        $sr = $db->prepare("SELECT a.user_id, {$nameSel} AS name, {$emailSel} AS email,
            COUNT(*) AS attempts,
            MAX(".($hasScoreCol?"a.score":"0").") AS best_score,
            ROUND(AVG(".($hasScoreCol?"a.score":"0")."),1) AS avg_score,
            " .($hasTimeSpent?"MIN(a.time_spent)":"NULL")." AS best_time,
            MAX(a.created_at) AS last_attempt,
            SUM(".($hasStatus?"a.status='completed'":"1").") AS completed
            FROM sat_quiz_attempts a {$userJoin}
            WHERE a.quiz_id=:id AND a.created_at>=:since
            GROUP BY a.user_id ORDER BY best_score DESC LIMIT 50");
        $sr->execute([':id'=>$id, ':since'=>$since]);
        $studentResults = $sr->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {}
}

// ── Question accuracy ─────────────────────────────────────────────────────
$questionAcc = [];
if ($hasAnswers && $hasQuestions) {
    try {
        $qa = $db->prepare("SELECT qq.id, qq.stem, qq.difficulty,
            COUNT(a.id) AS answers,
            ROUND(AVG(a.is_correct)*100,1) AS accuracy
            FROM sat_quiz_questions qq
            JOIN sat_quiz_answers a ON a.question_id=qq.id
            JOIN sat_quiz_attempts att ON att.id=a.attempt_id AND att.quiz_id=:id AND att.created_at>=:since
            GROUP BY qq.id, qq.stem, qq.difficulty
            HAVING answers>=3
            ORDER BY accuracy ASC LIMIT 20");
        $qa->execute([':id'=>$id, ':since'=>$since]);
        $questionAcc = $qa->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {}
}

// ── Time distribution ─────────────────────────────────────────────────────
$timeDist = [];
if ($hasAttempts && $hasTimeSpent && $hasTimeC && $test['time_limit']) {
    $timeLimit = (int)$test['time_limit'] * 60; // convert to seconds
    $buckets   = ['<25%','25-50%','50-75%','75-90%','>90%'];
    $timeDist  = array_fill(0, 5, 0);
    try {
        $td = $db->prepare("SELECT time_spent FROM sat_quiz_attempts WHERE quiz_id=:id AND status='completed' AND time_spent IS NOT NULL AND created_at>=:since");
        $td->execute([':id'=>$id,':since'=>$since]);
        foreach ($td->fetchAll(PDO::FETCH_COLUMN) as $ts) {
            $pct = $timeLimit > 0 ? $ts / $timeLimit : 0;
            if ($pct < .25)     $timeDist[0]++;
            elseif ($pct < .5)  $timeDist[1]++;
            elseif ($pct < .75) $timeDist[2]++;
            elseif ($pct < .9)  $timeDist[3]++;
            else                $timeDist[4]++;
        }
    } catch (Throwable) {}
}

function fmtSecs(?int $s): string {
    if ($s === null) return '—';
    $m = floor($s/60); $sec = $s%60;
    return $m.'m '.str_pad($sec,2,'0',STR_PAD_LEFT).'s';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Results: <?= htmlspecialchars($test['title']) ?> — Avidmock Admin</title>
<meta name="robots" content="noindex,nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,600;9..40,700;9..40,800&family=Fraunces:ital,opsz,wght@0,9..144,900;1,9..144,300&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{--ink:#0c1f1d;--ink2:#0e2522;--dk:#143230;--ac:#1fe290;--ac2:#13c474;--ac3:rgba(31,226,144,.08);--ac4:rgba(31,226,144,.15);--tx:#e8f3f1;--tx2:#9dbfba;--tx3:#5a8580;--bd:rgba(255,255,255,.07);--bd2:rgba(255,255,255,.13);--sf:rgba(255,255,255,.04);--sf2:rgba(255,255,255,.07);--warn:#f59e0b;--warn2:rgba(245,158,11,.12);--err:#ef4444;--err2:rgba(239,68,68,.12);--blue:#3b82f6;--blue2:rgba(59,130,246,.12);--ff:'DM Sans',sans-serif;--fh:'Fraunces',Georgia,serif;--fm:'DM Mono',monospace;--sb-w:240px;--top-h:60px}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:var(--ff);background:var(--ink);color:var(--tx);-webkit-font-smoothing:antialiased}
.sb{position:fixed;top:0;left:0;width:var(--sb-w);height:100vh;background:var(--ink2);border-right:1px solid var(--bd);display:flex;flex-direction:column;overflow-y:auto;z-index:300;transition:transform .32s cubic-bezier(.16,1,.3,1)}
.sb::-webkit-scrollbar{width:3px}.sb::-webkit-scrollbar-thumb{background:rgba(255,255,255,.06)}
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
.sb-foot-name{font-size:.75rem;font-weight:700;color:var(--tx)}.sb-foot-role{font-size:.5625rem;color:var(--tx3);text-transform:capitalize}
.sb-foot-out{margin-left:auto;padding:5px;background:none;border:none;cursor:pointer;color:var(--tx3);line-height:0;border-radius:6px;transition:all .16s}
.sb-foot-out:hover{background:var(--err2);color:var(--err)}
.sb-foot-out svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:250;opacity:0;transition:opacity .28s;pointer-events:none}
.sb-overlay.show{opacity:1;pointer-events:all}
.topbar{position:fixed;top:0;left:var(--sb-w);right:0;height:var(--top-h);background:rgba(12,31,29,.92);backdrop-filter:blur(16px);border-bottom:1px solid var(--bd);display:flex;align-items:center;padding:0 28px;gap:10px;z-index:200}
.topbar-ham{display:none;width:34px;height:34px;border-radius:8px;border:1px solid var(--bd);background:var(--sf);align-items:center;justify-content:center;cursor:pointer;flex-direction:column;gap:4px;padding:9px}
.topbar-ham span{display:block;height:1.5px;background:var(--tx2);border-radius:1px;width:100%}
.topbar-title{font-family:var(--fh);font-size:1rem;font-weight:900;color:var(--tx);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:400px}
.topbar-title a{color:var(--tx3);text-decoration:none;font-style:italic;font-weight:300}
.topbar-title a:hover{color:var(--ac)}
.topbar-spacer{flex:1}
.range-tabs{display:flex;gap:4px;background:var(--sf);border:1px solid var(--bd);border-radius:9px;padding:3px}
.range-tab{padding:5px 12px;border-radius:6px;font-size:.75rem;font-weight:700;color:var(--tx3);text-decoration:none;transition:all .16s}
.range-tab:hover{color:var(--tx)}.range-tab.active{background:var(--ac);color:var(--dk)}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:9px;font-family:var(--ff);font-size:.8125rem;font-weight:700;text-decoration:none;border:1.5px solid transparent;cursor:pointer;transition:all .18s}
.btn-ghost{background:var(--sf);border-color:var(--bd);color:var(--tx2)}.btn-ghost:hover{background:var(--sf2);color:var(--tx);border-color:var(--bd2)}
.btn-sm{padding:5px 11px;font-size:.75rem;border-radius:7px}
.main{margin-left:var(--sb-w);margin-top:var(--top-h);padding:28px}
/* TEST HEADER CARD */
.test-hero{background:var(--ink2);border:1px solid var(--bd);border-radius:16px;padding:22px 24px;margin-bottom:22px;display:flex;align-items:flex-start;gap:20px}
.test-hero-info{flex:1}
.test-hero-title{font-family:var(--fh);font-size:1.375rem;font-weight:900;color:var(--tx);letter-spacing:-.035em;margin-bottom:4px}
.test-hero-meta{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:8px}
.meta-chip{font-size:.5625rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;padding:3px 8px;border-radius:50px;background:var(--sf2);color:var(--tx3)}
.meta-chip.green{background:var(--ac3);color:var(--ac)}
.meta-chip.blue{background:var(--blue2);color:var(--blue)}
/* KPI ROW */
.kpi-row{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:22px}
.kpi{background:var(--ink2);border:1px solid var(--bd);border-radius:14px;padding:16px}
.kpi-label{font-size:.5rem;font-weight:700;color:var(--tx3);text-transform:uppercase;letter-spacing:.8px;margin-bottom:7px}
.kpi-val{font-family:var(--fh);font-size:1.875rem;font-weight:900;line-height:1;letter-spacing:-.04em;color:var(--tx);margin-bottom:3px}
.kpi-sub{font-size:.5625rem;color:var(--tx3)}
/* CHART GRID */
.chart-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px}
.card{background:var(--ink2);border:1px solid var(--bd);border-radius:16px;overflow:hidden}
.card-head{padding:14px 18px;border-bottom:1px solid var(--bd);display:flex;align-items:center;justify-content:space-between}
.card-title{font-size:.875rem;font-weight:700;color:var(--tx)}
.card-sub{font-size:.625rem;color:var(--tx3)}
/* SCORE DISTRIBUTION BARS */
.score-dist{padding:18px;display:flex;align-items:flex-end;gap:4px;height:130px}
.sd-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:3px;height:100%;cursor:default;position:relative}
.sd-wrap{flex:1;display:flex;flex-direction:column;justify-content:flex-end;width:100%}
.sd-bar{width:100%;border-radius:4px 4px 0 0;min-height:3px;transition:opacity .16s;position:relative}
.sd-bar:hover{opacity:.75}
.sd-bar::after{content:attr(data-tip);position:absolute;bottom:calc(100%+4px);left:50%;transform:translateX(-50%);background:var(--ink2);border:1px solid var(--bd2);padding:3px 7px;border-radius:5px;font-size:.5rem;color:var(--tx2);white-space:nowrap;pointer-events:none;opacity:0;transition:opacity .16s;z-index:10}
.sd-bar:hover::after{opacity:1}
.sd-label{font-size:.3125rem;color:var(--tx3);font-family:var(--fm);text-align:center}
/* SPARKLINE */
.sparkline{padding:14px 18px 10px;display:flex;align-items:flex-end;gap:3px;height:90px}
.sp-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:0;height:100%;position:relative}
.sp-bar{width:100%;border-radius:2px 2px 0 0;min-height:2px;background:var(--ac);opacity:.5;transition:opacity .12s}
.sp-bar:hover{opacity:.9}
.sp-bar::after{content:attr(data-tip);position:absolute;bottom:calc(100%+3px);left:50%;transform:translateX(-50%);background:var(--ink);border:1px solid var(--bd2);padding:2px 6px;border-radius:4px;font-size:.4375rem;color:var(--tx2);white-space:nowrap;pointer-events:none;opacity:0;transition:opacity .16s;z-index:10}
.sp-bar:hover::after{opacity:1}
/* FULL WIDTH TABLE CARD */
.full-card{background:var(--ink2);border:1px solid var(--bd);border-radius:16px;overflow:hidden;margin-bottom:18px}
/* DATA TABLE */
.data-table{width:100%;border-collapse:collapse}
.data-table th{padding:9px 14px;font-size:.5rem;font-weight:700;color:var(--tx3);text-transform:uppercase;letter-spacing:.8px;text-align:left;border-bottom:1px solid var(--bd);background:rgba(255,255,255,.015);white-space:nowrap}
.data-table td{padding:11px 14px;font-size:.8125rem;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:middle}
.data-table tbody tr:last-child td{border-bottom:none}
.data-table tbody tr:hover{background:rgba(255,255,255,.025)}
/* SCORE BAR */
.score-bar-wrap{display:flex;align-items:center;gap:8px}
.score-bar{width:70px;height:5px;background:var(--sf2);border-radius:3px;overflow:hidden;flex-shrink:0}
.score-fill{height:100%;border-radius:3px}
/* DIFF BADGE */
.diff-badge{font-size:.4375rem;font-weight:800;text-transform:uppercase;padding:1px 5px;border-radius:3px}
.diff-easy{background:rgba(31,226,144,.12);color:var(--ac2)}.diff-medium{background:var(--warn2);color:var(--warn)}.diff-hard{background:var(--err2);color:var(--err)}
/* PASS/FAIL funnel */
.funnel-row{display:flex;align-items:center;gap:12px;padding:10px 18px}
.funnel-label{font-size:.75rem;color:var(--tx2);width:80px;flex-shrink:0}
.funnel-bar-track{flex:1;height:20px;background:var(--sf);border-radius:4px;overflow:hidden}
.funnel-bar-fill{height:100%;border-radius:4px;transition:width .7s cubic-bezier(.16,1,.3,1)}
.funnel-n{font-family:var(--fm);font-size:.6875rem;color:var(--tx3);width:60px;text-align:right;flex-shrink:0}
/* TIME DIST */
.time-dist{padding:16px 18px;display:flex;flex-direction:column;gap:7px}
.td-row{display:flex;align-items:center;gap:10px}
.td-label{font-size:.625rem;color:var(--tx3);width:48px;flex-shrink:0;text-align:right}
.td-track{flex:1;height:8px;background:var(--sf2);border-radius:4px;overflow:hidden}
.td-fill{height:100%;border-radius:4px;background:var(--blue);transition:width .6s cubic-bezier(.16,1,.3,1)}
.td-n{font-family:var(--fm);font-size:.5625rem;color:var(--tx3);width:28px;text-align:right;flex-shrink:0}
/* empty state */
.empty-state{text-align:center;padding:40px;color:var(--tx3);font-size:.875rem}
/* reveal */
.reveal{opacity:0;transform:translateY(12px);animation:rev .4s cubic-bezier(.16,1,.3,1) forwards}
@keyframes rev{to{opacity:1;transform:none}}
.d1{animation-delay:.04s}.d2{animation-delay:.08s}.d3{animation-delay:.12s}.d4{animation-delay:.16s}
@media(max-width:1200px){.kpi-row{grid-template-columns:repeat(3,1fr)}}
@media(max-width:900px){.chart-grid{grid-template-columns:1fr}}
@media(max-width:768px){:root{--sb-w:0px}.sb{transform:translateX(-240px);--sb-w:240px}.sb.open{transform:translateX(0)}.topbar{left:0;padding:0 16px}.topbar-ham{display:flex}.main{margin-left:0;padding:16px}.range-tabs{display:none}.kpi-row{grid-template-columns:repeat(2,1fr)}}
</style>
</head>
<body>
<div class="sb-overlay" id="sbOverlay" onclick="closeSidebar()"></div>
<aside class="sb" id="sidebar">
    <a href="/index.php" class="sb-logo"><div class="sb-logo-mark"><svg viewBox="0 0 20 20"><path d="M10 2L13 8H19L14 12.5L16 18.5L10 15L4 18.5L6 12.5L1 8H7L10 2Z"/></svg></div><div><div class="sb-logo-name">Avidmock SAT</div><div class="sb-logo-sub">Admin Panel</div></div></a>
    <nav class="sb-nav">
        <div class="sb-group-label">Overview</div>
        <a href="/index.php" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>Dashboard</a>
        <a href="/analytics/index.php" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>Analytics</a>
        <div class="sb-group-label">Content</div>
        <a href="/quizzes/index.php" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><path d="M9 2H4a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9"/><path d="M13 2l5 5-8 8H5v-5l8-8z"/></svg>Quizzes</a>
        <a href="/questions/index.php" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3" stroke-linecap="round"/></svg>Question Bank</a>
        <a href="/practice-tests/index.php" class="sb-link active"><svg class="sb-ico" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>Practice Tests</a>
        <div class="sb-group-label">Students</div>
        <a href="/students/index.php" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>All Students</a>
    </nav>
    <div class="sb-foot">
        <div class="sb-foot-ava"><?= strtoupper(substr($admin['name'],0,1)) ?></div>
        <div><div class="sb-foot-name"><?= htmlspecialchars($admin['name']) ?></div><div class="sb-foot-role"><?= htmlspecialchars($admin['role']) ?></div></div>
        <a href="/auth/logout.php" class="sb-foot-out"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></a>
    </div>
</aside>

<header class="topbar">
    <button class="topbar-ham" onclick="openSidebar()"><span></span><span></span><span></span></button>
    <div class="topbar-title">
        <a href="/practice-tests/index.php">Practice Tests</a> / Results
    </div>
    <div class="topbar-spacer"></div>
    <div class="range-tabs">
        <?php foreach(['7'=>'7d','30'=>'30d','90'=>'90d','365'=>'1y'] as $v=>$l): ?>
        <a href="?id=<?=$id?>&range=<?=$v?>" class="range-tab <?=$range==$v?'active':''?>"><?=$l?></a>
        <?php endforeach ?>
    </div>
    <a href="/practice-tests/edit.php?id=<?=$id?>" class="btn btn-ghost btn-sm">Edit →</a>
</header>

<main class="main">
    <!-- Test hero -->
    <div class="test-hero reveal d1">
        <div class="test-hero-info">
            <div class="test-hero-title"><?= htmlspecialchars($test['title']) ?></div>
            <div class="test-hero-meta">
                <?php if($hasSectionC&&$test['section']): ?><span class="meta-chip blue"><?= htmlspecialchars(ucfirst(str_replace('_',' ',$test['section']))) ?></span><?php endif ?>
                <?php if($hasTimeC&&$test['time_limit']): ?><span class="meta-chip"><?=(int)$test['time_limit']?> min</span><?php endif ?>
                <?php if($hasPassCol&&$test['passing_score']): ?><span class="meta-chip green">Pass: <?=$test['passing_score']?>%</span><?php endif ?>
                <?php
                $sv = $test['status'] ?? 'published';
                $sc = $sv==='published'?'green':'';
                ?><span class="meta-chip <?=$sc?>"><?= ucfirst($sv) ?></span>
                <span class="meta-chip">ID #<?=$id?></span>
            </div>
        </div>
        <a href="/practice-tests/edit.php?id=<?=$id?>" class="btn btn-ghost btn-sm">Edit Test</a>
    </div>

    <!-- KPI row -->
    <div class="kpi-row reveal d2">
        <div class="kpi">
            <div class="kpi-label">Total Attempts</div>
            <div class="kpi-val"><?= number_format((int)$kpi['total']) ?></div>
            <div class="kpi-sub">last <?=$days?> days</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Completed</div>
            <div class="kpi-val" style="color:var(--ac)"><?= number_format((int)$kpi['completed']) ?></div>
            <div class="kpi-sub"><?= $kpi['total']>0?round($kpi['completed']/$kpi['total']*100).'% completion':'—' ?></div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Avg Score</div>
            <?php $sc=$kpi['avg_score']!==null?(float)$kpi['avg_score']:null; $cc=$sc!==null?($sc>=70?'var(--ac)':($sc>=50?'var(--warn)':'var(--err)')):'var(--tx3)'; ?>
            <div class="kpi-val" style="color:<?=$cc?>"><?= $sc!==null?$sc.'%':'—' ?></div>
            <div class="kpi-sub">completed attempts</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Pass Rate</div>
            <?php $pr=$kpi['pass_rate']!==null?(float)$kpi['pass_rate']:null; $prc=$pr!==null?($pr>=70?'var(--ac)':($pr>=50?'var(--warn)':'var(--err)')):'var(--tx3)'; ?>
            <div class="kpi-val" style="color:<?=$prc?>"><?= $pr!==null?$pr.'%':'—' ?></div>
            <div class="kpi-sub">threshold <?= $hasPassCol&&$test['passing_score']?$test['passing_score'].'%':'not set' ?></div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Unique Students</div>
            <div class="kpi-val"><?= number_format((int)$kpi['unique_students']) ?></div>
            <div class="kpi-sub"><?= $hasTimeSpent&&$kpi['avg_time']?fmtSecs((int)$kpi['avg_time']).' avg time':'—' ?></div>
        </div>
    </div>

    <!-- Charts row -->
    <div class="chart-grid reveal d3">
        <!-- Score distribution -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">Score Distribution</div>
                <div class="card-sub">completed attempts · last <?=$days?>d</div>
            </div>
            <div class="score-dist">
                <?php foreach ($scoreDist as $i => $n):
                    $low  = $i * 10;
                    $high = $low + ($i===9?10:9);
                    $h    = $scoreDistMax > 0 ? max(4, round($n/$scoreDistMax*100)) : 4;
                    $col  = $i>=7?'var(--ac)':($i>=5?'var(--warn)':'var(--err)');
                    $tip  = "{$low}–{$high}%: {$n} student".($n!==1?'s':'');
                ?>
                <div class="sd-col">
                    <div class="sd-wrap">
                        <div class="sd-bar" style="height:<?=$h?>px;background:<?=$col?>;opacity:<?=$n>0?.7:.15?>" data-tip="<?= htmlspecialchars($tip) ?>"></div>
                    </div>
                    <div class="sd-label"><?=$low?></div>
                </div>
                <?php endforeach ?>
            </div>
        </div>

        <!-- Daily trend sparkline -->
        <div class="card">
            <div class="card-head">
                <div class="card-title">Daily Attempts</div>
                <div class="card-sub">last <?=$days?> days</div>
            </div>
            <div class="sparkline">
                <?php foreach ($dailyTrend as $d):
                    $h  = $trendMax > 0 ? max(2, round($d['n']/$trendMax*68)) : 2;
                    $tip = date('M j', strtotime($d['date'])).': '.$d['n'];
                ?>
                <div class="sp-col">
                    <div style="flex:1;display:flex;align-items:flex-end">
                        <div class="sp-bar" style="height:<?=$h?>px;opacity:<?=$d['n']>0?.6:.1?>" data-tip="<?=htmlspecialchars($tip)?>"></div>
                    </div>
                </div>
                <?php endforeach ?>
            </div>
        </div>

        <!-- Pass / Fail funnel -->
        <?php if ($hasAttempts): $total=$kpi['total']>0?(int)$kpi['total']:1; ?>
        <div class="card">
            <div class="card-head">
                <div class="card-title">Completion Funnel</div>
                <div class="card-sub">attempt → complete → pass</div>
            </div>
            <div style="padding:16px 0">
                <?php
                $funnelRows = [
                    ['Attempted',  $kpi['total'],     'var(--blue)',  $total],
                    ['Completed',  $kpi['completed'],  'var(--warn)',  $total],
                ];
                if ($kpi['pass_rate']!==null) {
                    $passN = $kpi['completed']>0?round($kpi['completed']*$kpi['pass_rate']/100):0;
                    $funnelRows[]=['Passed', $passN, 'var(--ac)', $total];
                }
                foreach ($funnelRows as $fr):
                    $pct = $total>0?round($fr[1]/$total*100):0;
                ?>
                <div class="funnel-row">
                    <div class="funnel-label"><?=$fr[0]?></div>
                    <div class="funnel-bar-track">
                        <div class="funnel-bar-fill" style="width:<?=$pct?>%;background:<?=$fr[2]?>;opacity:.7"></div>
                    </div>
                    <div class="funnel-n"><?= number_format((int)$fr[1]) ?> · <?=$pct?>%</div>
                </div>
                <?php endforeach ?>
            </div>
        </div>
        <?php endif ?>

        <!-- Time distribution -->
        <?php if (!empty($timeDist) && array_sum($timeDist)>0):
            $tdMax = max($timeDist);
            $tdLabels = ['<25%','25-50%','50-75%','75-90%','>90%'];
            $tdCols   = ['var(--err)','var(--warn)','var(--blue)','var(--ac)','var(--ac)'];
        ?>
        <div class="card">
            <div class="card-head">
                <div class="card-title">Time Usage Distribution</div>
                <div class="card-sub">% of allotted time used · completed</div>
            </div>
            <div class="time-dist">
                <?php foreach($timeDist as $i=>$n):
                    $w = $tdMax>0?round($n/$tdMax*100):0;
                ?>
                <div class="td-row">
                    <div class="td-label"><?=$tdLabels[$i]?></div>
                    <div class="td-track"><div class="td-fill" style="width:<?=$w?>%;background:<?=$tdCols[$i]?>"></div></div>
                    <div class="td-n"><?=$n?></div>
                </div>
                <?php endforeach ?>
            </div>
        </div>
        <?php endif ?>
    </div>

    <!-- Per-student results table -->
    <div class="full-card reveal d3">
        <div class="card-head">
            <div class="card-title">Student Results</div>
            <div class="card-sub"><?= count($studentResults) ?> students · last <?=$days?>d · sorted by best score</div>
        </div>
        <?php if (empty($studentResults)): ?>
        <div class="empty-state">No attempts recorded in this period.</div>
        <?php else: ?>
        <div style="overflow-x:auto"><table class="data-table">
            <thead><tr>
                <th>Student</th>
                <th>Attempts</th>
                <th>Completed</th>
                <th>Best Score</th>
                <th>Avg Score</th>
                <?php if($hasPassCol&&$test['passing_score']):?><th>Status</th><?php endif?>
                <?php if($hasTimeSpent):?><th>Best Time</th><?php endif?>
                <th>Last Attempt</th>
                <?php if($usersTable):?><th></th><?php endif?>
            </tr></thead>
            <tbody>
            <?php foreach ($studentResults as $s):
                $bs = $s['best_score'] !== null ? (float)$s['best_score'] : null;
                $as = $s['avg_score']  !== null ? (float)$s['avg_score']  : null;
                $bsCol = $bs!==null?($bs>=70?'var(--ac)':($bs>=50?'var(--warn)':'var(--err)')):'var(--tx3)';
                $passed = $hasPassCol && $test['passing_score'] && $bs!==null && $bs>=$test['passing_score'];
            ?><tr>
                <td>
                    <div style="font-weight:600;color:var(--tx)"><?= htmlspecialchars($s['name']??'Unknown') ?></div>
                    <?php if($s['email']):?><div style="font-size:.625rem;color:var(--tx3)"><?= htmlspecialchars($s['email']) ?></div><?php endif?>
                </td>
                <td><span style="font-family:var(--fm);color:var(--tx2)"><?=(int)$s['attempts']?></span></td>
                <td><span style="font-family:var(--fm);color:var(--tx2)"><?=(int)$s['completed']?></span></td>
                <td>
                    <?php if ($bs!==null): ?>
                    <div class="score-bar-wrap">
                        <div class="score-bar"><div class="score-fill" style="width:<?=$bs?>%;background:<?=$bsCol?>"></div></div>
                        <span style="font-family:var(--fm);font-size:.75rem;font-weight:700;color:<?=$bsCol?>"><?=$bs?>%</span>
                    </div>
                    <?php else: ?>—<?php endif ?>
                </td>
                <td><span style="font-family:var(--fm);font-size:.75rem;color:var(--tx3)"><?= $as!==null?$as.'%':'—' ?></span></td>
                <?php if($hasPassCol&&$test['passing_score']):?>
                <td>
                    <?php if($bs===null):?><span style="color:var(--tx3)">—</span>
                    <?php elseif($passed):?><span style="font-size:.5rem;font-weight:800;padding:2px 7px;border-radius:50px;background:var(--ac3);color:var(--ac);text-transform:uppercase;letter-spacing:.5px">Passed</span>
                    <?php else:?><span style="font-size:.5rem;font-weight:800;padding:2px 7px;border-radius:50px;background:var(--err2);color:var(--err);text-transform:uppercase;letter-spacing:.5px">Failed</span>
                    <?php endif?>
                </td>
                <?php endif?>
                <?php if($hasTimeSpent):?><td><span style="font-family:var(--fm);font-size:.75rem;color:var(--tx3)"><?= fmtSecs($s['best_time']!==null?(int)$s['best_time']:null) ?></span></td><?php endif?>
                <td><span style="font-size:.6875rem;color:var(--tx3)"><?= date('M j, Y', strtotime($s['last_attempt'])) ?></span></td>
                <?php if($usersTable):?>
                <td><a href="/students/profile.php?id=<?=(int)$s['user_id']?>" style="font-size:.6875rem;color:var(--ac);text-decoration:none;font-weight:600">Profile →</a></td>
                <?php endif?>
            </tr><?php endforeach ?>
            </tbody>
        </table></div>
        <?php endif ?>
    </div>

    <!-- Question accuracy table -->
    <?php if (!empty($questionAcc)): ?>
    <div class="full-card reveal d4">
        <div class="card-head">
            <div class="card-title">Hardest Questions</div>
            <div class="card-sub">lowest accuracy · ≥3 answers · last <?=$days?>d</div>
        </div>
        <div style="overflow-x:auto"><table class="data-table">
            <thead><tr>
                <th>#</th>
                <th>Question</th>
                <th>Difficulty</th>
                <th>Answers</th>
                <th>Accuracy</th>
                <th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($questionAcc as $i => $q):
                $acc = (float)$q['accuracy'];
                $col = $acc>=70?'var(--ac)':($acc>=40?'var(--warn)':'var(--err)');
            ?><tr>
                <td style="font-family:var(--fm);font-size:.625rem;color:var(--tx3)"><?=$i+1?></td>
                <td style="max-width:280px"><div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--tx)"><?= htmlspecialchars(mb_substr(strip_tags($q['stem']??''),0,70)) ?></div></td>
                <td><span class="diff-badge diff-<?=strtolower($q['difficulty']??'medium')?>"><?= ucfirst($q['difficulty']??'—') ?></span></td>
                <td><span style="font-family:var(--fm);font-size:.75rem;color:var(--tx3)"><?=(int)$q['answers']?></span></td>
                <td>
                    <div class="score-bar-wrap">
                        <div class="score-bar"><div class="score-fill" style="width:<?=$acc?>%;background:<?=$col?>"></div></div>
                        <span style="font-family:var(--fm);font-size:.75rem;font-weight:700;color:<?=$col?>"><?=$acc?>%</span>
                    </div>
                </td>
                <td><a href="/questions/edit.php?id=<?=(int)$q['id']?>" style="font-size:.75rem;color:var(--ac);text-decoration:none;font-weight:600">Edit →</a></td>
            </tr><?php endforeach ?>
            </tbody>
        </table></div>
    </div>
    <?php endif ?>
</main>

<script>
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('sbOverlay').classList.add('show');document.body.style.overflow='hidden'}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sbOverlay').classList.remove('show');document.body.style.overflow=''}
window.addEventListener('resize',()=>{if(window.innerWidth>768)closeSidebar()})
</script>
</body>
</html>