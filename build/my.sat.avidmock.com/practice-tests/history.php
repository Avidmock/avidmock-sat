<?php
/**
 * /practice-tests/history.php — Test History
 * my.sat.avidmock.com/practice-tests/history/
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/StudyStreak.php';

Auth::requireStudent();
$userId    = $_SESSION['user_id'];
$user      = User::findById($userId);
$firstName = explode(' ', trim($user['name'] ?? $user['first_name'] ?? 'Student'))[0];

/* ── Streak ── */
$streakData    = StudyStreak::get($userId) ?? [];
$currentStreak = intval($streakData['current_streak'] ?? 0);

/* ── All submitted attempts with test info ── */
$attempts = Database::fetchAll(
    "SELECT a.*, t.title AS test_title, t.total_time, t.type AS test_type
     FROM practice_test_attempts a
     JOIN practice_tests t ON t.id = a.test_id
     WHERE a.user_id = ? AND a.status = 'submitted'
     ORDER BY a.submitted_at DESC",
    [$userId]
);

$totalAttempts = count($attempts);
$allScores     = array_filter(array_column($attempts, 'total_score'));
$bestScore     = $allScores ? max($allScores) : 0;
$avgScore      = $allScores ? round(array_sum($allScores) / count($allScores)) : 0;

/* ── Best section scores ── */
$bestRW   = max(array_filter(array_column($attempts, 'rw_score'))   ?: [0]);
$bestMath = max(array_filter(array_column($attempts, 'math_score')) ?: [0]);

/* ── Score trend (last 8, oldest first for chart) ── */
$chartSlice  = array_reverse(array_slice($attempts, 0, 8));
$chartScores = array_map(fn($a) => (int)($a['total_score'] ?? 0), $chartSlice);
$chartRW     = array_map(fn($a) => (int)($a['rw_score']    ?? 0), $chartSlice);
$chartMath   = array_map(fn($a) => (int)($a['math_score']  ?? 0), $chartSlice);
$chartLabels = array_map(fn($a) => date('M j', strtotime($a['submitted_at'])), $chartSlice);

/* ── Improvement (first vs latest) ── */
$improvement = null;
if ($totalAttempts >= 2) {
    $latest      = (int)($attempts[0]['total_score'] ?? 0);
    $first       = (int)($attempts[$totalAttempts - 1]['total_score'] ?? 0);
    $improvement = $latest - $first;
}

/* ── Shared includes vars ── */
$activePage = 'practice_tests';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Test History — Avidmock SAT</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<link rel="icon" type="image/png" sizes="16x16"   href="/favicon-16x16.png">
<link rel="icon" type="image/png" sizes="32x32"   href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="192x192" href="/android-chrome-192x192.png">
<link rel="icon" type="image/png" sizes="512x512" href="/android-chrome-512x512.png">
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
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
    --blue: #3b82f6;
    --purple: #8b5cf6;
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
body { font-family: var(--ff); -webkit-font-smoothing: antialiased; background: var(--bg); color: var(--tx); min-height: 100vh; overflow-x: hidden; }
a { text-decoration: none; color: inherit; }
button { font-family: var(--ff); cursor: pointer; }

/* ─────────────────────────────────────────────
   SCROLL REVEAL
───────────────────────────────────────────── */
.sr { opacity: 0; transform: translateY(20px); transition: opacity .55s cubic-bezier(.16,1,.3,1), transform .55s cubic-bezier(.16,1,.3,1); }
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
.page-wrap { max-width: 1000px; margin: 0 auto; }
.sidebar-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.45); z-index: 250;
    opacity: 0; transition: opacity .28s; pointer-events: none;
}
.sidebar-overlay.show { opacity: 1; pointer-events: all; display: block; }

/* ─────────────────────────────────────────────
   BREADCRUMB
───────────────────────────────────────────── */
.breadcrumb {
    display: flex; align-items: center; gap: 6px;
    font-size: .8125rem; color: var(--tx3); margin-bottom: 20px;
}
.breadcrumb a { color: var(--tx3); transition: color .15s; }
.breadcrumb a:hover { color: var(--ac); }
.breadcrumb-sep { opacity: .4; }
.breadcrumb-cur { color: var(--tx); font-weight: 600; }

/* ─────────────────────────────────────────────
   PAGE HEADER
───────────────────────────────────────────── */
.page-header {
    display: flex; align-items: flex-end; justify-content: space-between;
    gap: 16px; margin-bottom: 28px;
}
.page-title {
    font-family: var(--fh); font-size: 1.75rem; font-weight: 900;
    color: var(--tx); letter-spacing: -.03em; line-height: 1.1;
}
.page-title span { color: var(--ac); }
.page-subtitle { font-size: .875rem; color: var(--tx3); margin-top: 4px; }

.hdr-actions { display: flex; gap: 8px; }
.hdr-btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 9px 18px; border-radius: var(--r-sm);
    font-family: var(--ff); font-size: .8125rem; font-weight: 700;
    border: 1.5px solid var(--bd); background: var(--bg2); color: var(--tx2);
    transition: border-color .18s, color .18s, background .18s;
}
.hdr-btn:hover { border-color: var(--ac); color: var(--dk); }
.hdr-btn svg { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.hdr-btn-primary { background: var(--dk); color: #fff; border-color: transparent; }
.hdr-btn-primary:hover { background: var(--dk2); color: #fff; }
.hdr-btn-primary svg { stroke: var(--ac); }

/* ─────────────────────────────────────────────
   STAT STRIP
───────────────────────────────────────────── */
.stat-strip { display: grid; grid-template-columns: repeat(5, 1fr); gap: 14px; margin-bottom: 24px; }
.stat-pill {
    background: var(--bg2); border: 1px solid var(--bd); border-radius: var(--r);
    padding: 18px 20px; display: flex; align-items: center; gap: 12px;
    transition: box-shadow .2s, transform .2s;
}
.stat-pill:hover { box-shadow: 0 4px 16px rgba(20,50,48,.06); transform: translateY(-2px); }
.stat-ico { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.stat-ico svg { width: 18px; height: 18px; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.ico-ac     { background: rgba(31,226,144,.08);  } .ico-ac     svg { stroke: var(--ac2); }
.ico-dk     { background: rgba(20,50,48,.06);    } .ico-dk     svg { stroke: var(--dk); }
.ico-warn   { background: rgba(245,158,11,.08);  } .ico-warn   svg { stroke: var(--warn); }
.ico-blue   { background: rgba(59,130,246,.08);  } .ico-blue   svg { stroke: var(--blue); }
.ico-purple { background: rgba(139,92,246,.08);  } .ico-purple svg { stroke: var(--purple); }
.ico-up     { background: rgba(16,185,129,.08);  } .ico-up     svg { stroke: var(--ok); }
.ico-dn     { background: rgba(239,68,68,.06);   } .ico-dn     svg { stroke: var(--err); }
.stat-label { font-size: .5625rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: .08em; margin-bottom: 2px; }
.stat-val   { font-family: var(--fm); font-size: 1.375rem; font-weight: 700; color: var(--tx); letter-spacing: -.04em; line-height: 1; }
.stat-sub   { font-size: .6875rem; color: var(--tx3); margin-top: 3px; }

/* ─────────────────────────────────────────────
   CHART CARD
───────────────────────────────────────────── */
.chart-card {
    background: var(--bg2); border: 1px solid var(--bd);
    border-radius: var(--r-lg); padding: 28px; margin-bottom: 24px;
}
.chart-card-hd { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.chart-card-title { font-size: 1.0625rem; font-weight: 800; color: var(--tx); letter-spacing: -.02em; }
.chart-legend { display: flex; align-items: center; gap: 16px; }
.legend-item { display: flex; align-items: center; gap: 6px; font-size: .75rem; color: var(--tx3); font-weight: 600; }
.legend-dot { width: 8px; height: 8px; border-radius: 50%; }
.chart-wrap { height: 200px; position: relative; margin-top: 16px; }

/* ─────────────────────────────────────────────
   HISTORY TABLE CARD
───────────────────────────────────────────── */
.history-card {
    background: var(--bg2); border: 1px solid var(--bd);
    border-radius: var(--r-lg); overflow: hidden;
}
.history-card-hd {
    padding: 18px 24px; border-bottom: 1px solid var(--bd);
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
}
.history-card-title { font-size: 1.0625rem; font-weight: 800; color: var(--tx); letter-spacing: -.02em; }
.history-count {
    font-family: var(--fm); font-size: .75rem; color: var(--tx3);
    background: var(--bg); border: 1px solid var(--bd);
    border-radius: 20px; padding: 3px 12px; font-weight: 600;
}

/* Table */
.history-table { width: 100%; border-collapse: collapse; }
.history-table th {
    font-size: .625rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .08em; color: var(--tx3);
    padding: 11px 20px; text-align: left;
    border-bottom: 1px solid var(--bd); white-space: nowrap;
}
.history-table td {
    padding: 14px 20px; border-bottom: 1px solid var(--bd);
    font-size: .875rem; color: var(--tx2); vertical-align: middle;
}
.history-table tbody tr:last-child td { border-bottom: none; }
.history-table tbody tr:hover td { background: rgba(247,250,249,.8); }

/* Rank cell */
.rank-cell {
    display: flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 9px;
    font-family: var(--fm); font-size: .6875rem; font-weight: 700;
    color: var(--tx3); background: var(--bg); border: 1.5px solid var(--bd);
}
.rank-cell.gold   { background: rgba(245,158,11,.1);  border-color: rgba(245,158,11,.3);  color: #d97706; }
.rank-cell.silver { background: rgba(148,163,184,.1); border-color: rgba(148,163,184,.3); color: #64748b; }
.rank-cell.bronze { background: rgba(180,83,9,.08);   border-color: rgba(180,83,9,.2);    color: #92400e; }

/* Rank icons (SVG trophy/medal stand) */
.rank-icon { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

/* Test name */
.test-name { font-weight: 700; color: var(--tx); font-size: .875rem; }
.test-type-chip {
    display: inline-block; font-size: .5625rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .08em;
    padding: 2px 7px; border-radius: 5px;
    background: var(--bg); color: var(--tx3); border: 1px solid var(--bd); margin-top: 3px;
}

/* Score badge */
.score-badge {
    display: inline-flex; align-items: center;
    padding: 5px 13px; border-radius: 9px;
    font-family: var(--fm); font-weight: 700; font-size: .9375rem; letter-spacing: -.02em;
}
.score-high { background: rgba(31,226,144,.1);  color: var(--ac2); }
.score-mid  { background: rgba(245,158,11,.1);  color: #a06a00; }
.score-low  { background: rgba(239,68,68,.08);  color: var(--err); }

/* Section score mini bars */
.sec-score-wrap { display: flex; flex-direction: column; gap: 3px; }
.sec-score-row  { display: flex; align-items: center; gap: 6px; font-size: .75rem; }
.sec-score-lbl  { width: 28px; color: var(--tx3); font-weight: 600; }
.sec-score-val  { font-family: var(--fm); font-weight: 700; color: var(--tx); min-width: 32px; font-size: .75rem; }
.sec-score-bar-wrap { flex: 1; height: 4px; background: var(--bd); border-radius: 2px; overflow: hidden; min-width: 48px; }
.sec-score-fill { height: 100%; border-radius: 2px; transition: width .8s cubic-bezier(.16,1,.3,1); }

/* Duration / date */
.td-duration { font-family: var(--fm); font-size: .8125rem; color: var(--tx3); white-space: nowrap; }
.td-date-main { font-weight: 600; color: var(--tx); font-size: .8125rem; }
.td-date-time { font-size: .6875rem; color: var(--tx3); margin-top: 2px; }

/* Action buttons */
.tbl-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 13px; border-radius: 8px;
    font-family: var(--ff); font-size: .75rem; font-weight: 700;
    border: 1.5px solid var(--bd); color: var(--tx2);
    transition: border-color .18s, color .18s; white-space: nowrap; margin-right: 4px;
}
.tbl-btn:hover { border-color: var(--ac); color: var(--dk); }
.tbl-btn-primary { background: var(--dk); color: #fff; border-color: transparent; }
.tbl-btn-primary:hover { background: var(--dk2); color: #fff; }
.tbl-btn svg { width: 11px; height: 11px; stroke: currentColor; fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }

/* ─────────────────────────────────────────────
   EMPTY STATE
───────────────────────────────────────────── */
.empty-state { text-align: center; padding: 80px 24px; }
.empty-icon {
    width: 72px; height: 72px; border-radius: 20px;
    background: rgba(20,50,48,.05); border: 1.5px solid var(--bd);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 20px;
}
.empty-icon svg { width: 32px; height: 32px; stroke: var(--tx3); fill: none; stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round; }
.empty-title { font-family: var(--fh); font-size: 1.375rem; font-weight: 900; color: var(--tx); letter-spacing: -.03em; margin-bottom: 8px; }
.empty-sub   { font-size: .9375rem; color: var(--tx3); line-height: 1.6; margin-bottom: 28px; max-width: 400px; margin-left: auto; margin-right: auto; }
.btn-mint {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 13px 26px; border-radius: 12px;
    background: var(--ac); color: var(--dk);
    font-family: var(--ff); font-size: .875rem; font-weight: 800;
    transition: background .18s, box-shadow .18s, transform .18s;
}
.btn-mint:hover { background: var(--ac2); box-shadow: 0 6px 20px rgba(31,226,144,.25); transform: translateY(-1px); }
.btn-mint svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }

/* ─────────────────────────────────────────────
   RESPONSIVE
───────────────────────────────────────────── */
@media (max-width: 900px)  { .main-content { margin-left: 0; padding: 24px 16px 80px; } }
@media (max-width: 1024px) { .stat-strip { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 768px)  {
    .stat-strip { grid-template-columns: repeat(2, 1fr); }
    .history-table th:nth-child(4), .history-table td:nth-child(4) { display: none; }
    .page-header { flex-direction: column; align-items: flex-start; }
}
@media (max-width: 600px)  {
    .stat-strip { grid-template-columns: 1fr 1fr; }
    .history-table th:nth-child(3), .history-table td:nth-child(3) { display: none; }
    .chart-legend { display: none; }
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
<div class="page-wrap">

    <!-- ── Breadcrumb ── -->
    <nav class="breadcrumb sr">
        <a href="/practice-tests/">Practice Tests</a>
        <span class="breadcrumb-sep">›</span>
        <span class="breadcrumb-cur">History</span>
    </nav>

    <!-- ── Page Header ── -->
    <div class="page-header sr">
        <div>
            <h1 class="page-title">Test <span>History</span></h1>
            <p class="page-subtitle">All your completed SAT practice tests</p>
        </div>
        <div class="hdr-actions">
            <a href="/practice-tests/" class="hdr-btn">
                <svg viewBox="0 0 24 24"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
                All Tests
            </a>
            <?php if ($totalAttempts > 0): ?>
            <a href="/practice-tests/" class="hdr-btn hdr-btn-primary">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg>
                New Test
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($totalAttempts > 0): ?>

    <!-- ── Stat Strip ── -->
    <div class="stat-strip sr d1">

        <div class="stat-pill">
            <div class="stat-ico ico-dk">
                <svg viewBox="0 0 24 24"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>
            </div>
            <div>
                <div class="stat-label">Tests Done</div>
                <div class="stat-val"><?= $totalAttempts ?></div>
                <div class="stat-sub">completed</div>
            </div>
        </div>

        <div class="stat-pill">
            <div class="stat-ico ico-ac">
                <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </div>
            <div>
                <div class="stat-label">Best Score</div>
                <div class="stat-val"><?= $bestScore ?: '—' ?></div>
                <div class="stat-sub">out of 1600</div>
            </div>
        </div>

        <div class="stat-pill">
            <div class="stat-ico ico-warn">
                <svg viewBox="0 0 24 24"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/></svg>
            </div>
            <div>
                <div class="stat-label">Average</div>
                <div class="stat-val"><?= $avgScore ?: '—' ?></div>
                <div class="stat-sub">all tests</div>
            </div>
        </div>

        <div class="stat-pill">
            <div class="stat-ico ico-blue">
                <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
            </div>
            <div>
                <div class="stat-label">Best R&amp;W</div>
                <div class="stat-val"><?= $bestRW ?: '—' ?></div>
                <div class="stat-sub">out of 800</div>
            </div>
        </div>

        <div class="stat-pill">
            <?php
            $impClass = ($improvement === null) ? 'ico-warn' :
                        ($improvement > 0 ? 'ico-up' : ($improvement < 0 ? 'ico-dn' : 'ico-warn'));
            $impVal   = $improvement === null ? '—' :
                        ($improvement > 0 ? '+' . $improvement : (string)$improvement);
            $impColor = $improvement > 0 ? 'color:var(--ac2)' : ($improvement < 0 ? 'color:var(--err)' : '');
            ?>
            <div class="stat-ico <?= $impClass ?>">
                <svg viewBox="0 0 24 24">
                    <?php if ($improvement > 0): ?>
                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>
                    <?php elseif ($improvement < 0): ?>
                    <polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/>
                    <?php else: ?>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                    <?php endif; ?>
                </svg>
            </div>
            <div>
                <div class="stat-label">Improvement</div>
                <div class="stat-val" style="<?= $impColor ?>"><?= $impVal ?></div>
                <div class="stat-sub">first → latest</div>
            </div>
        </div>

    </div><!-- /stat-strip -->

    <!-- ── Score Progression Chart ── -->
    <?php if (count($chartScores) >= 2): ?>
    <div class="chart-card sr d2">
        <div class="chart-card-hd">
            <div class="chart-card-title">Score Progression</div>
            <div class="chart-legend">
                <div class="legend-item">
                    <div class="legend-dot" style="background:#1fe290"></div>Total
                </div>
                <div class="legend-item">
                    <div class="legend-dot" style="background:#3b82f6"></div>R&amp;W
                </div>
                <div class="legend-item">
                    <div class="legend-dot" style="background:#8b5cf6"></div>Math
                </div>
            </div>
        </div>
        <div class="chart-wrap">
            <canvas id="scoreChart"></canvas>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── History Table ── -->
    <div class="history-card sr d3">
        <div class="history-card-hd">
            <div class="history-card-title">All Attempts</div>
            <span class="history-count"><?= $totalAttempts ?> attempt<?= $totalAttempts !== 1 ? 's' : '' ?></span>
        </div>
        <div style="overflow-x:auto">
            <table class="history-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Test</th>
                        <th>Total Score</th>
                        <th>R&amp;W / Math</th>
                        <th>Duration</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                /* Sort copy by score desc to determine ranks */
                $rankMap = [];
                $sorted  = $attempts;
                usort($sorted, fn($a, $b) => $b['total_score'] <=> $a['total_score']);
                foreach ($sorted as $r => $s) {
                    $rankMap[$s['id']] = $r + 1;
                }

                foreach ($attempts as $idx => $att):
                    $sc     = (int)($att['total_score'] ?? 0);
                    $rw     = (int)($att['rw_score']    ?? 0);
                    $math   = (int)($att['math_score']  ?? 0);
                    $cls    = $sc >= 1300 ? 'score-high' : ($sc >= 1100 ? 'score-mid' : 'score-low');
                    $rank   = $rankMap[$att['id']] ?? null;
                    $rankCellClass = $rank === 1 ? 'gold' : ($rank === 2 ? 'silver' : ($rank === 3 ? 'bronze' : ''));
                ?>
                <tr>
                    <!-- Rank -->
                    <td>
                        <div class="rank-cell <?= $rankCellClass ?>">
                            <?php if ($rank === 1): ?>
                            <svg class="rank-icon" viewBox="0 0 24 24">
                                <path d="M8 21h8M12 17v4M17 3l2 4h3l-2.5 3.5L21 14l-4-1.5L12 15l-5 1.5-1.5-3.5L3 7h3l2-4"/>
                            </svg>
                            <?php elseif ($rank === 2): ?>
                            <svg class="rank-icon" viewBox="0 0 24 24">
                                <circle cx="12" cy="8" r="6"/><path d="M8.21 13.89L7 23l5-3 5 3-1.21-9.12"/>
                            </svg>
                            <?php elseif ($rank === 3): ?>
                            <svg class="rank-icon" viewBox="0 0 24 24">
                                <circle cx="12" cy="8" r="6"/><path d="M8.21 13.89L7 23l5-3 5 3-1.21-9.12"/>
                            </svg>
                            <?php else: ?>
                            #<?= $rank ?>
                            <?php endif; ?>
                        </div>
                    </td>

                    <!-- Test name -->
                    <td>
                        <div class="test-name"><?= htmlspecialchars($att['test_title']) ?></div>
                        <div class="test-type-chip"><?= ucfirst(str_replace('_', ' ', $att['test_type'] ?? 'full length')) ?></div>
                    </td>

                    <!-- Total score -->
                    <td>
                        <span class="score-badge <?= $cls ?>"><?= $sc ?: '—' ?></span>
                    </td>

                    <!-- Section scores -->
                    <td>
                        <?php if ($rw || $math): ?>
                        <div class="sec-score-wrap">
                            <div class="sec-score-row">
                                <span class="sec-score-lbl">R&W</span>
                                <span class="sec-score-val" style="color:var(--blue)"><?= $rw ?: '—' ?></span>
                                <?php if ($rw): ?>
                                <div class="sec-score-bar-wrap">
                                    <div class="sec-score-fill" style="width:<?= round($rw / 800 * 100) ?>%;background:var(--blue)"></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="sec-score-row">
                                <span class="sec-score-lbl" style="color:var(--purple)">M</span>
                                <span class="sec-score-val" style="color:var(--purple)"><?= $math ?: '—' ?></span>
                                <?php if ($math): ?>
                                <div class="sec-score-bar-wrap">
                                    <div class="sec-score-fill" style="width:<?= round($math / 800 * 100) ?>%;background:var(--purple)"></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <span style="color:var(--tx4);font-size:.8125rem">—</span>
                        <?php endif; ?>
                    </td>

                    <!-- Duration -->
                    <td>
                        <span class="td-duration">
                            <?php if ($att['time_taken']): ?>
                                <?= floor($att['time_taken'] / 3600) > 0
                                    ? floor($att['time_taken'] / 3600) . 'h ' . sprintf('%02d', floor(($att['time_taken'] % 3600) / 60)) . 'm'
                                    : floor($att['time_taken'] / 60) . 'm ' . ($att['time_taken'] % 60) . 's' ?>
                            <?php else: ?>—<?php endif; ?>
                        </span>
                    </td>

                    <!-- Date -->
                    <td>
                        <div class="td-date-main"><?= date('M j, Y', strtotime($att['submitted_at'])) ?></div>
                        <div class="td-date-time"><?= date('g:i A', strtotime($att['submitted_at'])) ?></div>
                    </td>

                    <!-- Actions -->
                    <td style="white-space:nowrap">
                        <a href="/practice-tests/results/?attempt_id=<?= (int)$att['id'] ?>"
                           class="tbl-btn tbl-btn-primary">
                            <svg viewBox="0 0 24 24"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>
                            Results
                        </a>
                        <a href="/practice-tests/review/?attempt_id=<?= (int)$att['id'] ?>"
                           class="tbl-btn">
                            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            Review
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php else: ?>

    <!-- ── Empty State ── -->
    <div class="empty-state sr">
        <div class="empty-icon">
            <svg viewBox="0 0 24 24">
                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
                <polyline points="10 9 9 9 8 9"/>
            </svg>
        </div>
        <h3 class="empty-title">No completed tests yet</h3>
        <p class="empty-sub">Take your first full-length SAT practice test to start tracking your progress and score.</p>
        <a href="/practice-tests/" class="btn-mint">
            Browse Practice Tests
            <svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </a>
    </div>

    <?php endif; ?>

</div><!-- /page-wrap -->
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
(function () {
    'use strict';

    /* Scroll reveal */
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (e.isIntersecting) { e.target.classList.add('v'); io.unobserve(e.target); }
        });
    }, { threshold: .04, rootMargin: '0px 0px -16px 0px' });
    document.querySelectorAll('.sr').forEach(function (el) { io.observe(el); });

    /* Sidebar overlay */
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

    /* Chart.js — score progression */
    <?php if (count($chartScores) >= 2): ?>
    var ctx = document.getElementById('scoreChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [
                    {
                        label: 'Total',
                        data: <?= json_encode($chartScores) ?>,
                        borderColor: '#1fe290',
                        backgroundColor: 'rgba(31,226,144,.06)',
                        borderWidth: 2.5,
                        pointBackgroundColor: '#1fe290',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        tension: 0.4,
                        fill: true,
                    },
                    {
                        label: 'R&W',
                        data: <?= json_encode($chartRW) ?>,
                        borderColor: '#3b82f6',
                        backgroundColor: 'transparent',
                        borderWidth: 1.5,
                        borderDash: [4, 3],
                        pointBackgroundColor: '#3b82f6',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        tension: 0.4,
                    },
                    {
                        label: 'Math',
                        data: <?= json_encode($chartMath) ?>,
                        borderColor: '#8b5cf6',
                        backgroundColor: 'transparent',
                        borderWidth: 1.5,
                        borderDash: [4, 3],
                        pointBackgroundColor: '#8b5cf6',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        tension: 0.4,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#143230',
                        titleColor: '#1fe290',
                        bodyColor: 'rgba(255,255,255,.8)',
                        borderColor: 'rgba(31,226,144,.2)',
                        borderWidth: 1,
                        padding: 12,
                        cornerRadius: 10,
                        callbacks: {
                            label: function (ctx) {
                                return ' ' + ctx.dataset.label + ': ' + ctx.parsed.y;
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'DM Sans', size: 12 }, color: '#6b7280' },
                    },
                    y: {
                        min: 200, max: 1600,
                        grid: { color: 'rgba(0,0,0,.04)' },
                        ticks: {
                            font: { family: 'DM Sans', size: 12 },
                            color: '#6b7280',
                            stepSize: 200,
                            callback: function (v) { return v; },
                        },
                    },
                },
            },
        });
    }
    <?php endif; ?>

    /* Ctrl/Cmd+K search shortcut */
    document.addEventListener('keydown', function (e) {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            if (window.tbOpenSearch) tbOpenSearch();
        }
    });
}());
</script>
</body>
</html>