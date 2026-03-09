<?php
/**
 * students/student.php — Individual student profile & stats
 * Uses shared includes/head.php, includes/sidebar.php
 */
require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

$admin = currentAdmin();
$db    = Database::connect();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /students/index.php'); exit; }

// ── Schema ────────────────────────────────────────────────────────────────
try {
    $allTables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $userCols  = $db->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    die("DB error: " . htmlspecialchars($e->getMessage()));
}

$nameExpr  = in_array('first_name', $userCols) ? "CONCAT(first_name,' ',last_name)" : "name";
$hasXp     = in_array('user_xp',          $allTables);
$hasAtt    = in_array('sat_quiz_attempts', $allTables);
$hasStatus = in_array('status', $userCols);

// ── Load student ──────────────────────────────────────────────────────────
$sStmt = $db->prepare(
    "SELECT *, {$nameExpr} AS display_name FROM users WHERE id = :id AND role = 'student'"
);
$sStmt->execute([':id' => $id]);
$student = $sStmt->fetch(PDO::FETCH_ASSOC);
if (!$student) { header('Location: /students/index.php'); exit; }

// ── XP & level ────────────────────────────────────────────────────────────
$xp = ['xp' => 0, 'level' => 1, 'level_name' => 'Beginner'];
if ($hasXp) {
    try {
        $xpCols = $db->query("SHOW COLUMNS FROM user_xp")->fetchAll(PDO::FETCH_COLUMN);
        $r = $db->prepare("SELECT * FROM user_xp WHERE user_id = :id");
        $r->execute([':id' => $id]);
        $row = $r->fetch(PDO::FETCH_ASSOC);
        if ($row) $xp = array_merge($xp, $row);
        // Normalise: some installs use 'xp', some 'total_xp'
        if (!isset($xp['xp']) && isset($xp['total_xp'])) $xp['xp'] = $xp['total_xp'];
    } catch (Throwable) {}
}

// ── Attempt stats ─────────────────────────────────────────────────────────
$attStats = ['total' => 0, 'avg_score' => null, 'best_score' => null, 'worst_score' => null, 'total_time' => 0];
$recentAttempts = [];
if ($hasAtt) {
    try {
        $r = $db->prepare(
            "SELECT COUNT(*) AS total,
                    ROUND(AVG(score), 1) AS avg_score,
                    MAX(score)           AS best_score,
                    MIN(score)           AS worst_score,
                    SUM(time_taken)      AS total_time
             FROM sat_quiz_attempts
             WHERE user_id = :id AND status = 'completed'"
        );
        $r->execute([':id' => $id]);
        $row = $r->fetch(PDO::FETCH_ASSOC);
        if ($row) $attStats = array_merge($attStats, array_map(fn($v) => is_numeric($v) ? (float)$v : $v, $row));

        $r2 = $db->prepare(
            "SELECT a.id, q.title AS quiz_title, q.id AS quiz_id,
                    a.score, a.completed_at, a.time_taken
             FROM sat_quiz_attempts a
             JOIN sat_quizzes q ON q.id = a.quiz_id
             WHERE a.user_id = :id AND a.status = 'completed'
             ORDER BY a.completed_at DESC
             LIMIT 10"
        );
        $r2->execute([':id' => $id]);
        $recentAttempts = $r2->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {}
}

// ── Score history for chart ───────────────────────────────────────────────
$scoreHistory = [];
if ($hasAtt) {
    try {
        $r = $db->prepare(
            "SELECT DATE(completed_at) AS day, ROUND(AVG(score), 1) AS avg
             FROM sat_quiz_attempts
             WHERE user_id = :id AND status = 'completed'
               AND completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(completed_at)
             ORDER BY day ASC"
        );
        $r->execute([':id' => $id]);
        $scoreHistory = $r->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {}
}

// ── Avatar colour ─────────────────────────────────────────────────────────
function avatarColor(string $name): array {
    $colors = [
        ['bg' => 'rgba(31,226,144,.18)', 'fg' => '#1fe290'],
        ['bg' => 'rgba(59,130,246,.18)', 'fg' => '#60a5fa'],
        ['bg' => 'rgba(245,158,11,.18)', 'fg' => '#fbbf24'],
        ['bg' => 'rgba(239,68,68,.18)',  'fg' => '#f87171'],
        ['bg' => 'rgba(168,85,247,.18)', 'fg' => '#c084fc'],
        ['bg' => 'rgba(20,184,166,.18)', 'fg' => '#2dd4bf'],
    ];
    return $colors[abs(crc32($name)) % count($colors)];
}
$ava = avatarColor($student['display_name'] ?? '');

// ── XP bar calc ───────────────────────────────────────────────────────────
$lvl    = max(1, (int)($xp['level'] ?? 1));
$xpCur  = (int)($xp['xp'] ?? 0);
$xpPrev = ($lvl - 1) * ($lvl - 1) * 100;
$xpNeed = $lvl * $lvl * 100;
$xpPct  = $xpNeed > $xpPrev ? min(100, round(($xpCur - $xpPrev) / ($xpNeed - $xpPrev) * 100)) : 100;

// ── Head setup ────────────────────────────────────────────────────────────
$pageTitle  = htmlspecialchars($student['display_name']) . ' — Avidmock Admin';
$activePage = 'students';
$extraHead  = <<<'CSS'
<style>
/* ── Layout ────────────────────────────────────────────────────────── */
.main {
    margin-left: var(--sb-w);
    margin-top: var(--top-h);
    padding: 32px 28px;
    min-height: calc(100vh - var(--top-h));
}
.page-grid {
    display: grid;
    grid-template-columns: 288px 1fr;
    gap: 22px;
    align-items: start;
}

/* ── Profile sidebar card ──────────────────────────────────────────── */
.profile-card {
    background: var(--sf);
    border: 1px solid var(--bd);
    border-radius: 18px;
    overflow: hidden;
    position: sticky;
    top: calc(var(--top-h) + 24px);
}

/* Hero */
.profile-hero {
    padding: 28px 22px 22px;
    text-align: center;
    position: relative;
    border-bottom: 1px solid var(--bd);
}
.profile-hero::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 80px;
    background: linear-gradient(180deg, rgba(31,226,144,.05) 0%, transparent 100%);
    pointer-events: none;
}
.profile-ava {
    width: 76px; height: 76px; border-radius: 20px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.875rem; font-weight: 800;
    margin: 0 auto 14px; position: relative; z-index: 1;
    box-shadow: 0 8px 28px rgba(0,0,0,.3);
}
.profile-name {
    font-family: var(--fh); font-size: 1.1875rem; font-weight: 900;
    color: var(--tx); letter-spacing: -.025em; margin-bottom: 3px;
}
.profile-email {
    font-size: .6875rem; color: var(--tx3);
    font-family: var(--fm); margin-bottom: 12px;
}
.status-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 50px;
    font-size: .4375rem; font-weight: 800; text-transform: uppercase; letter-spacing: .6px;
}
.status-pill .sdot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; flex-shrink: 0; }
.status-pill.active    { background: var(--ac3);  color: var(--ac);  border: 1px solid rgba(31,226,144,.2); }
.status-pill.suspended { background: var(--err2); color: var(--err); border: 1px solid rgba(239,68,68,.2); }
.status-pill.inactive  { background: var(--sf3);  color: var(--tx3); border: 1px solid var(--bd); }

/* XP bar */
.xp-section {
    padding: 16px 20px;
    border-bottom: 1px solid var(--bd);
}
.xp-top {
    display: flex; justify-content: space-between; align-items: baseline;
    margin-bottom: 8px;
}
.xp-level { font-size: .8125rem; font-weight: 800; color: var(--tx); }
.xp-pts   { font-size: .6875rem; font-family: var(--fm); color: var(--ac); font-weight: 700; }
.xp-bar   { height: 5px; background: rgba(255,255,255,.07); border-radius: 3px; overflow: hidden; }
.xp-fill  {
    height: 100%;
    background: linear-gradient(90deg, var(--ac), var(--ac2));
    border-radius: 3px;
    transition: width .8s cubic-bezier(.16,1,.3,1);
    box-shadow: 0 0 8px rgba(31,226,144,.4);
}
.xp-next { font-size: .5625rem; color: var(--tx3); margin-top: 5px; }

/* Meta rows */
.profile-meta { padding: 8px 0; border-bottom: 1px solid var(--bd); }
.meta-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 8px 20px; transition: background .12s;
}
.meta-row:hover { background: rgba(255,255,255,.02); }
.meta-label { font-size: .75rem; color: var(--tx3); }
.meta-val   { font-size: .8125rem; font-weight: 700; color: var(--tx); font-family: var(--fm); }
.meta-val.good { color: var(--ac); }

/* Actions */
.profile-actions { padding: 16px 18px; display: flex; flex-direction: column; gap: 8px; }

/* ── Content area ──────────────────────────────────────────────────── */
.ph { margin-bottom: 20px; }
.ph-eyebrow { display: flex; align-items: center; gap: 6px; font-size: .5rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 5px; }
.ph-dot { width: 4px; height: 4px; border-radius: 50%; background: var(--ac); }
.ph-title { font-family: var(--fh); font-size: 1.625rem; font-weight: 900; color: var(--tx); letter-spacing: -.035em; line-height: 1.1; margin-bottom: 3px; }
.ph-sub { font-size: .875rem; color: var(--tx2); }

/* ── Cards ─────────────────────────────────────────────────────────── */
.card {
    background: var(--sf);
    border: 1px solid var(--bd);
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 18px;
}
.card-head {
    padding: 13px 18px;
    border-bottom: 1px solid var(--bd);
    display: flex; align-items: center; gap: 10px;
    background: rgba(255,255,255,.015);
}
.card-head-ico {
    width: 28px; height: 28px; border-radius: 7px;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.card-head-ico svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.card-head-ico.green { background: var(--ac3);   color: var(--ac); }
.card-head-ico.blue  { background: rgba(59,130,246,.1); color: #60a5fa; }
.card-head-ico.amber { background: rgba(245,158,11,.1); color: var(--warn); }
.card-head-title { font-size: .875rem; font-weight: 700; color: var(--tx); flex: 1; }
.card-body { padding: 18px; }

/* ── Stat mini-cards ───────────────────────────────────────────────── */
.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
}
.mini-stat {
    background: var(--ink2);
    border: 1px solid var(--bd);
    border-radius: 11px; padding: 14px 12px;
    text-align: center; position: relative; overflow: hidden;
    transition: transform .18s, box-shadow .18s;
}
.mini-stat:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.2); }
.mini-stat::after {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,.06), transparent);
}
.ms-label { font-size: .4375rem; font-weight: 800; color: var(--tx3); text-transform: uppercase; letter-spacing: .9px; margin-bottom: 8px; }
.ms-val   { font-family: var(--fh); font-size: 1.625rem; font-weight: 900; letter-spacing: -.04em; line-height: 1; color: var(--tx); }
.ms-val.green { color: var(--ac); }
.ms-val.blue  { color: #60a5fa; }
.ms-val.amber { color: var(--warn); }
.ms-sub { font-size: .5625rem; color: var(--tx3); margin-top: 4px; }

/* ── Sparkline chart ────────────────────────────────────────────────── */
.spark-wrap {
    position: relative; padding: 4px 0 8px;
}
.spark-canvas { width: 100%; height: 100px; display: block; }
.spark-empty  { text-align: center; padding: 32px; color: var(--tx3); font-size: .875rem; }

/* ── Attempts table ─────────────────────────────────────────────────── */
.att-table { width: 100%; border-collapse: collapse; }
.att-table thead th {
    padding: 9px 16px;
    font-size: .5625rem; font-weight: 700; color: var(--tx3);
    text-transform: uppercase; letter-spacing: .8px;
    text-align: left; border-bottom: 1px solid var(--bd);
    background: rgba(255,255,255,.015); white-space: nowrap;
}
.att-table tbody td {
    padding: 12px 16px; font-size: .8125rem;
    border-bottom: 1px solid var(--bd); vertical-align: middle;
}
.att-table tbody tr:last-child td { border-bottom: none; }
.att-table tbody tr { transition: background .12s; }
.att-table tbody tr:hover { background: rgba(255,255,255,.025); }
.quiz-name { font-weight: 700; color: var(--tx); }
.score-chip {
    display: inline-flex; align-items: center; padding: 3px 9px;
    border-radius: 50px; font-size: .6875rem; font-weight: 800; font-family: var(--fm);
}
.score-chip.good { background: var(--ac3);  color: var(--ac); }
.score-chip.mid  { background: rgba(245,158,11,.1); color: var(--warn); }
.score-chip.bad  { background: rgba(239,68,68,.1);  color: var(--err); }
.dim { color: var(--tx3); font-size: .75rem; font-family: var(--fm); }
.empty-att { text-align: center; padding: 48px 20px; color: var(--tx3); font-size: .875rem; }
.empty-att-ico { font-size: 2rem; margin-bottom: 8px; opacity: .2; }

/* ── Icon btn (topbar) ─────────────────────────────────────────────── */
.icon-btn-sm {
    width: 32px; height: 32px; border-radius: 8px;
    background: var(--sf); border: 1px solid var(--bd);
    display: flex; align-items: center; justify-content: center;
    color: var(--tx3); text-decoration: none; cursor: pointer;
    transition: all .15s;
}
.icon-btn-sm:hover { background: var(--sf2); color: var(--tx); }
.icon-btn-sm svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 1.9; stroke-linecap: round; }

/* ── Buttons in profile card ────────────────────────────────────────── */
.pf-btn {
    display: flex; align-items: center; justify-content: center; gap: 7px;
    padding: 9px 14px; border-radius: 9px;
    font-family: var(--ff); font-size: .8125rem; font-weight: 700;
    text-decoration: none; transition: all .18s;
    border: 1.5px solid transparent; cursor: pointer; width: 100%;
}
.pf-btn svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }
.pf-btn-primary { background: var(--ac);  color: var(--dk);  border-color: transparent; }
.pf-btn-primary:hover { background: var(--ac2); box-shadow: 0 4px 14px rgba(31,226,144,.25); }
.pf-btn-warn    { background: rgba(245,158,11,.1); color: var(--warn); border-color: rgba(245,158,11,.2); }
.pf-btn-warn:hover { background: rgba(245,158,11,.18); }
.pf-btn-danger  { background: rgba(239,68,68,.08); color: var(--err); border-color: rgba(239,68,68,.15); }
.pf-btn-danger:hover { background: rgba(239,68,68,.18); }

/* ── Toast ─────────────────────────────────────────────────────────── */
.toast-wrap { position: fixed; bottom: 24px; right: 24px; z-index: 999; display: flex; flex-direction: column; gap: 8px; pointer-events: none; }
.toast { padding: 12px 18px; border-radius: 11px; font-size: .8125rem; font-weight: 600; border: 1px solid var(--bd2); box-shadow: 0 8px 32px rgba(0,0,0,.4); transform: translateX(120%); transition: transform .3s cubic-bezier(.16,1,.3,1); max-width: 320px; pointer-events: all; }
.toast.show { transform: none; }
.toast.success { background: rgba(31,226,144,.12); color: var(--ac); }
.toast.error   { background: rgba(239,68,68,.12);  color: var(--err); }

/* ── Reveal ─────────────────────────────────────────────────────────── */
.reveal { opacity: 0; transform: translateY(12px); animation: rev .45s cubic-bezier(.16,1,.3,1) forwards; }
@keyframes rev { to { opacity: 1; transform: none; } }
.d1 { animation-delay: .04s; } .d2 { animation-delay: .1s; }
.d3 { animation-delay: .16s; } .d4 { animation-delay: .22s; }

/* ── Responsive ─────────────────────────────────────────────────────── */
@media (max-width: 960px) {
    .page-grid { grid-template-columns: 1fr; }
    .profile-card { position: static; }
    .stats-row { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 768px) {
    .main { margin-left: 0; padding: 16px; }
    .stats-row { grid-template-columns: 1fr 1fr; }
}
</style>
CSS;

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';

$stStatus = $student['status'] ?? 'active';
?>

<!-- Topbar -->
<header class="topbar">
    <button class="topbar-ham" onclick="openSidebar()" aria-label="Menu">
        <span></span><span></span><span></span>
    </button>
    <div class="topbar-title">
        Students <span>/ <?= htmlspecialchars($student['display_name']) ?></span>
    </div>
    <div class="topbar-spacer"></div>
    <a href="/students/index.php" class="btn btn-ghost btn-sm">
        <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
        All Students
    </a>
</header>

<!-- Main -->
<main class="main">
<div class="page-grid">

    <!-- ── LEFT: Profile card ───────────────────────────────────────── -->
    <div class="reveal d1">
        <div class="profile-card">

            <!-- Hero -->
            <div class="profile-hero">
                <div class="profile-ava" style="background:<?= $ava['bg'] ?>;color:<?= $ava['fg'] ?>">
                    <?= strtoupper(substr($student['display_name'], 0, 1)) ?>
                </div>
                <div class="profile-name"><?= htmlspecialchars($student['display_name']) ?></div>
                <div class="profile-email"><?= htmlspecialchars($student['email']) ?></div>
                <span class="status-pill <?= htmlspecialchars($stStatus) ?>">
                    <span class="sdot"></span>
                    <?= ucfirst(htmlspecialchars($stStatus)) ?>
                </span>
            </div>

            <!-- XP bar -->
            <div class="xp-section">
                <div class="xp-top">
                    <span class="xp-level">Level <?= $lvl ?> &mdash; <?= htmlspecialchars($xp['level_name'] ?? 'Beginner') ?></span>
                    <span class="xp-pts"><?= number_format($xpCur) ?> XP</span>
                </div>
                <div class="xp-bar">
                    <div class="xp-fill" style="width:<?= $xpPct ?>%"></div>
                </div>
                <div class="xp-next"><?= number_format(max(0, $xpNeed - $xpCur)) ?> XP to Level <?= $lvl + 1 ?></div>
            </div>

            <!-- Meta -->
            <div class="profile-meta">
                <div class="meta-row">
                    <span class="meta-label">Joined</span>
                    <span class="meta-val"><?= date('M j, Y', strtotime($student['created_at'])) ?></span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Quizzes Done</span>
                    <span class="meta-val"><?= (int)$attStats['total'] ?></span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Avg Score</span>
                    <span class="meta-val"><?= $attStats['avg_score'] !== null ? number_format((float)$attStats['avg_score'], 1) . '%' : '—' ?></span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Best Score</span>
                    <span class="meta-val good"><?= $attStats['best_score'] !== null ? number_format((float)$attStats['best_score'], 1) . '%' : '—' ?></span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Worst Score</span>
                    <span class="meta-val"><?= $attStats['worst_score'] !== null ? number_format((float)$attStats['worst_score'], 1) . '%' : '—' ?></span>
                </div>
            </div>

        </div>
    </div>

    <!-- ── RIGHT: Stats + history ──────────────────────────────────── -->
    <div>
        <!-- Page header -->
        <div class="ph reveal d1">
            <div class="ph-eyebrow"><span class="ph-dot"></span>Student Profile</div>
            <h1 class="ph-title"><?= htmlspecialchars($student['display_name']) ?></h1>
            <p class="ph-sub">Performance overview and recent quiz history</p>
        </div>

        <!-- Performance summary -->
        <div class="card reveal d2">
            <div class="card-head">
                <div class="card-head-ico green">
                    <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                </div>
                <div class="card-head-title">Performance Summary</div>
            </div>
            <div class="card-body">
                <div class="stats-row">
                    <div class="mini-stat">
                        <div class="ms-label">Quizzes</div>
                        <div class="ms-val"><?= (int)$attStats['total'] ?></div>
                        <div class="ms-sub">completed</div>
                    </div>
                    <div class="mini-stat">
                        <div class="ms-label">Avg Score</div>
                        <div class="ms-val <?= ((float)($attStats['avg_score'] ?? 0)) >= 70 ? 'green' : 'amber' ?>">
                            <?= $attStats['avg_score'] !== null ? number_format((float)$attStats['avg_score'], 1) . '%' : '—' ?>
                        </div>
                        <div class="ms-sub">average</div>
                    </div>
                    <div class="mini-stat">
                        <div class="ms-label">Best Score</div>
                        <div class="ms-val green">
                            <?= $attStats['best_score'] !== null ? number_format((float)$attStats['best_score'], 1) . '%' : '—' ?>
                        </div>
                        <div class="ms-sub">personal best</div>
                    </div>
                    <div class="mini-stat">
                        <div class="ms-label">Total XP</div>
                        <div class="ms-val blue"><?= number_format($xpCur) ?></div>
                        <div class="ms-sub">earned</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Score trend -->
        <div class="card reveal d3">
            <div class="card-head">
                <div class="card-head-ico blue">
                    <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                </div>
                <div class="card-head-title">Score Trend — Last 30 Days</div>
            </div>
            <div class="card-body">
                <?php if (count($scoreHistory) > 1): ?>
                <div class="spark-wrap">
                    <canvas class="spark-canvas" id="sparkCanvas"></canvas>
                </div>
                <?php else: ?>
                <div class="spark-empty">
                    <div class="empty-att-ico"></div>
                    Not enough data yet — needs at least 2 quiz attempts in the last 30 days.
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent attempts -->
        <div class="card reveal d4">
            <div class="card-head">
                <div class="card-head-ico amber">
                    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                </div>
                <div class="card-head-title">Recent Quiz Attempts</div>
            </div>
            <?php if (empty($recentAttempts)): ?>
            <div class="empty-att">
                <div class="empty-att-ico"></div>
                No quiz attempts yet.
            </div>
            <?php else: ?>
            <table class="att-table">
                <thead>
                    <tr>
                        <th>Quiz</th>
                        <th>Score</th>
                        <th>Time</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentAttempts as $a):
                    $sc  = (float)$a['score'];
                    $cls = $sc >= 80 ? 'good' : ($sc >= 60 ? 'mid' : 'bad');
                ?>
                <tr>
                    <td><span class="quiz-name"><?= htmlspecialchars($a['quiz_title']) ?></span></td>
                    <td><span class="score-chip <?= $cls ?>"><?= number_format($sc, 1) ?>%</span></td>
                    <td><span class="dim"><?= $a['time_taken'] ? gmdate('i:s', (int)$a['time_taken']) : '—' ?></span></td>
                    <td><span class="dim"><?= $a['completed_at'] ? date('M j, Y', strtotime($a['completed_at'])) : '—' ?></span></td>
                    <td style="text-align:right">
                        <a href="/quizzes/results.php?id=<?= (int)$a['quiz_id'] ?>" class="btn btn-ghost btn-sm">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div><!-- /right -->

</div><!-- /page-grid -->
</main>

<div class="toast-wrap" id="toastWrap"></div>

<script>
const STUDENT_ID = <?= $id ?>;

/* ── Status toggle ────────────────────────────────────────────────────── */
async function toggleStatus(action) {
    if (action === 'suspend' && !confirm('Suspend this student? They will lose access.')) return;
    try {
        const res  = await fetch('/students/bulk-action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, ids: [STUDENT_ID] }),
        });
        const data = await res.json();
        if (data.success) { toast('Done.', 'success'); setTimeout(() => location.reload(), 900); }
        else toast(data.error || 'Something went wrong.', 'error');
    } catch { toast('Network error.', 'error'); }
}

/* ── Delete ───────────────────────────────────────────────────────────── */
async function deleteStudent() {
    if (!confirm('Permanently delete this student and ALL their data?\nThis cannot be undone.')) return;
    try {
        const res  = await fetch('/students/bulk-action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', ids: [STUDENT_ID] }),
        });
        const data = await res.json();
        if (data.success) { toast('Student deleted.', 'success'); setTimeout(() => window.location = '/students/index.php', 900); }
        else toast(data.error || 'Something went wrong.', 'error');
    } catch { toast('Network error.', 'error'); }
}

/* ── Toast ────────────────────────────────────────────────────────────── */
function toast(msg, type = 'info') {
    const wrap = document.getElementById('toastWrap');
    const el   = document.createElement('div');
    el.className   = `toast ${type}`;
    el.textContent = msg;
    wrap.appendChild(el);
    requestAnimationFrame(() => requestAnimationFrame(() => el.classList.add('show')));
    setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 400); }, 3200);
}

/* ── Sparkline canvas ─────────────────────────────────────────────────── */
const sparkData = <?= json_encode($scoreHistory) ?>;

(function drawSparkline() {
    if (sparkData.length < 2) return;
    const canvas = document.getElementById('sparkCanvas');
    if (!canvas) return;

    const dpr = window.devicePixelRatio || 1;
    const W   = canvas.offsetWidth  * dpr;
    const H   = canvas.offsetHeight * dpr;
    canvas.width  = W;
    canvas.height = H;

    const ctx = canvas.getContext('2d');
    const pad = { t: 12, r: 12, b: 24, l: 40 };
    const iW  = W - pad.l - pad.r;
    const iH  = H - pad.t - pad.b;

    const vals = sparkData.map(d => parseFloat(d.avg));
    const mn   = Math.max(0,   Math.min(...vals) - 8);
    const mx   = Math.min(100, Math.max(...vals) + 8);
    const range = mx - mn || 1;

    const px = i => pad.l + (i / (vals.length - 1)) * iW;
    const py = v => pad.t + iH - ((v - mn) / range) * iH;

    /* Grid lines */
    ctx.strokeStyle = 'rgba(255,255,255,.04)';
    ctx.lineWidth   = 1;
    [0, 0.25, 0.5, 0.75, 1].forEach(t => {
        const yy = pad.t + t * iH;
        ctx.beginPath(); ctx.moveTo(pad.l, yy); ctx.lineTo(pad.l + iW, yy); ctx.stroke();
    });

    /* Y-axis labels */
    ctx.fillStyle = 'rgba(90,133,128,.8)';
    ctx.font      = `${9 * dpr}px 'DM Mono', monospace`;
    ctx.textAlign = 'right';
    [mn, (mn + mx) / 2, mx].forEach(v => {
        ctx.fillText(v.toFixed(0) + '%', pad.l - 5, py(v) + 3);
    });

    /* Smooth curve helper */
    function smooth(pts) {
        ctx.beginPath();
        ctx.moveTo(px(0), py(pts[0]));
        for (let i = 0; i < pts.length - 1; i++) {
            const xc = (px(i) + px(i + 1)) / 2;
            const yc = (py(pts[i]) + py(pts[i + 1])) / 2;
            ctx.quadraticCurveTo(px(i), py(pts[i]), xc, yc);
        }
        ctx.quadraticCurveTo(px(pts.length - 2), py(pts[pts.length - 2]), px(pts.length - 1), py(pts[pts.length - 1]));
    }

    /* Fill gradient */
    const grad = ctx.createLinearGradient(0, pad.t, 0, pad.t + iH);
    grad.addColorStop(0,   'rgba(31,226,144,.22)');
    grad.addColorStop(0.7, 'rgba(31,226,144,.05)');
    grad.addColorStop(1,   'rgba(31,226,144,0)');
    smooth(vals);
    ctx.lineTo(px(vals.length - 1), pad.t + iH);
    ctx.lineTo(px(0), pad.t + iH);
    ctx.closePath();
    ctx.fillStyle = grad;
    ctx.fill();

    /* Line */
    smooth(vals);
    ctx.strokeStyle = '#1fe290';
    ctx.lineWidth   = 2 * dpr;
    ctx.lineJoin    = 'round';
    ctx.stroke();

    /* Dots */
    ctx.fillStyle = '#1fe290';
    vals.forEach((v, i) => {
        ctx.beginPath();
        ctx.arc(px(i), py(v), 3 * dpr, 0, Math.PI * 2);
        ctx.fill();
    });

    /* X-axis date labels (first & last) */
    if (sparkData.length >= 2) {
        ctx.fillStyle = 'rgba(90,133,128,.7)';
        ctx.font      = `${8 * dpr}px 'DM Mono', monospace`;
        ctx.textAlign = 'left';
        ctx.fillText(sparkData[0].day.slice(5),  pad.l, H - 4);
        ctx.textAlign = 'right';
        ctx.fillText(sparkData[sparkData.length - 1].day.slice(5), pad.l + iW, H - 4);
    }
})();
</script>
</body>
</html>