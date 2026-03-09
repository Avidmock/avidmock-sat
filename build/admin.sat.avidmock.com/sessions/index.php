<?php
/**
 * sessions/index.php — Sessions Listing
 * Lists all tutoring sessions with stats, filters, and management actions.
 */
require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

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
function timeAgo(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 0)     return 'in ' . ltrim(timeUntil($dt), 'in ');
    if ($diff < 60)    return 'just now';
    if ($diff < 3600)  return round($diff/60).'m ago';
    if ($diff < 86400) return round($diff/3600).'h ago';
    return round($diff/86400).'d ago';
}
function timeUntil(string $dt): string {
    $diff = strtotime($dt) - time();
    if ($diff <= 0)     return 'now';
    if ($diff < 3600)   return 'in ' . round($diff/60) . 'm';
    if ($diff < 86400)  return 'in ' . round($diff/3600) . 'h';
    if ($diff < 604800) return 'in ' . round($diff/86400) . 'd';
    return date('M j', strtotime($dt));
}

// ── filters ──────────────────────────────────────────────────────────────
$filterStatus = $_GET['status'] ?? '';
$filterType   = $_GET['type']   ?? '';

$statusWhitelist = ['scheduled','live','completed','cancelled'];
$typeWhitelist   = ['group','one_on_one','workshop'];
if ($filterStatus && !in_array($filterStatus, $statusWhitelist)) $filterStatus = '';
if ($filterType   && !in_array($filterType, $typeWhitelist))     $filterType   = '';

// ── STAT CARDS ───────────────────────────────────────────────────────────
$totalSessions = (int) safeQuery($db,
    "SELECT COUNT(*) FROM tutoring_sessions");

$upcomingSessions = (int) safeQuery($db,
    "SELECT COUNT(*) FROM tutoring_sessions
     WHERE status='scheduled' AND session_date > NOW()");

$liveSessions = (int) safeQuery($db,
    "SELECT COUNT(*) FROM tutoring_sessions WHERE status='live'");

$totalEnrollments = (int) safeQuery($db,
    "SELECT COUNT(*) FROM session_enrollments");

// ── SESSION LIST ─────────────────────────────────────────────────────────
$where = [];
if ($filterStatus) $where[] = "s.status='" . $filterStatus . "'";
if ($filterType)   $where[] = "s.session_type='" . $filterType . "'";
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sessions = safeQueryAll($db,
    "SELECT s.*,
            (SELECT COUNT(*) FROM session_enrollments WHERE session_id=s.id) AS enrolled_count
     FROM tutoring_sessions s
     {$whereSQL}
     ORDER BY
        FIELD(s.status,'live','scheduled','completed','cancelled'),
        s.session_date DESC
     LIMIT 100");

// ── Head setup ───────────────────────────────────────────────────────────
$pageTitle  = 'Sessions — Avidmock Admin';
$activePage = 'sessions';
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

/* ── Filters bar ─────────────────────────────────────────────────── */
.filters-bar { display: flex; align-items: center; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
.filter-chip { display: inline-flex; align-items: center; gap: 5px; padding: 6px 14px; border-radius: 50px; font-size: .6875rem; font-weight: 700; color: var(--tx3); background: var(--sf); border: 1px solid var(--bd); text-decoration: none; transition: all .18s cubic-bezier(.16,1,.3,1); cursor: pointer; white-space: nowrap; }
.filter-chip:hover { background: var(--sf2); border-color: var(--bd2); color: var(--tx2); }
.filter-chip.active { background: var(--ac3); border-color: rgba(31,226,144,.25); color: var(--ac); }
.filter-chip .chip-count { font-family: var(--fm); font-size: .5625rem; opacity: .7; }
.filters-sep { width: 1px; height: 20px; background: var(--bd); margin: 0 4px; }

/* ── Session table card ──────────────────────────────────────────── */
.card { background: var(--sf); border: 1px solid var(--bd); border-radius: var(--r2,16px); overflow: hidden; }
.card-head { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px 0; }
.card-title { font-size: .875rem; font-weight: 700; color: var(--tx); letter-spacing: -.015em; }
.card-count { font-size: .6875rem; font-weight: 600; color: var(--tx3); font-family: var(--fm); }

/* ── Sessions table ──────────────────────────────────────────────── */
.s-table { width: 100%; border-collapse: collapse; }
.s-table th { padding: 10px 14px; font-size: .5625rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: .8px; text-align: left; border-bottom: 1px solid var(--bd); background: rgba(255,255,255,.015); }
.s-table td { padding: 12px 14px; font-size: .8125rem; border-bottom: 1px solid var(--bd); vertical-align: middle; }
.s-table tr:last-child td { border-bottom: none; }
.s-table tbody tr { transition: background .14s; }
.s-table tbody tr:hover { background: var(--sf2); }

/* cell content */
.st-title { font-weight: 700; color: var(--tx); letter-spacing: -.01em; }
.st-desc  { display: block; font-size: .625rem; color: var(--tx3); margin-top: 2px; max-width: 260px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* type badge */
.st-type { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: 50px; font-size: .5625rem; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }
.st-type.group     { background: var(--blue2);   color: var(--blue); }
.st-type.one_on_one { background: var(--purple2); color: var(--purple); }
.st-type.workshop  { background: var(--warn2);   color: var(--warn); }

/* status badge */
.st-status { display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 50px; font-size: .5rem; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; }
.st-status.scheduled { background: var(--blue2);   color: var(--blue); }
.st-status.live      { background: rgba(31,226,144,.1); color: var(--ac); }
.st-status.completed { background: var(--sf3);     color: var(--tx3); }
.st-status.cancelled { background: rgba(239,68,68,.1); color: var(--err); }
.st-status-dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.st-status.live .st-status-dot { animation: livePulse 2s ease-in-out infinite; }
@keyframes livePulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.3;transform:scale(.55)} }

/* enrollment meter */
.enroll-bar { display: flex; align-items: center; gap: 8px; }
.enroll-meter { width: 48px; height: 4px; border-radius: 2px; background: var(--bd); overflow: hidden; flex-shrink: 0; }
.enroll-fill { height: 100%; border-radius: 2px; background: var(--ac); transition: width .3s ease; }
.enroll-fill.warn { background: var(--warn); }
.enroll-fill.full { background: var(--err); }
.enroll-text { font-family: var(--fm); font-size: .6875rem; font-weight: 600; color: var(--tx2); white-space: nowrap; }

/* datetime */
.st-date { font-weight: 600; color: var(--tx); font-size: .8125rem; }
.st-time { display: block; font-size: .625rem; color: var(--tx3); font-family: var(--fm); margin-top: 2px; }

/* duration */
.st-duration { font-family: var(--fm); font-size: .75rem; color: var(--tx2); }

/* actions */
.st-actions { display: flex; gap: 4px; }
.st-act { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 7px; background: var(--sf2); border: 1px solid var(--bd); text-decoration: none; transition: all .16s; color: var(--tx2); cursor: pointer; }
.st-act svg { width: 12px; height: 12px; stroke: currentColor; fill: none; stroke-width: 1.8; stroke-linecap: round; }
.st-act:hover { background: var(--ac3); border-color: rgba(31,226,144,.2); color: var(--ac); }
.st-act.danger:hover { background: rgba(239,68,68,.1); border-color: rgba(239,68,68,.2); color: var(--err); }
.st-act.attendees:hover { background: var(--blue2); border-color: rgba(var(--blue-rgb,96,165,250),.25); color: var(--blue); }

/* ── Empty state ─────────────────────────────────────────────────── */
.empty-state { padding: 48px 20px; text-align: center; }
.empty-state-ico { font-size: 2.5rem; margin-bottom: 12px; opacity: .25; }
.empty-state-text { font-size: .875rem; color: var(--tx3); margin-bottom: 4px; }
.empty-state-sub  { font-size: .75rem; color: var(--tx3); opacity: .6; }

/* ── Reveals ─────────────────────────────────────────────────────── */
.reveal { opacity: 0; transform: translateY(16px); animation: revealUp .5s cubic-bezier(.16,1,.3,1) forwards; }
@keyframes revealUp { to { opacity: 1; transform: none; } }
.d1{animation-delay:.04s} .d2{animation-delay:.08s} .d3{animation-delay:.12s}
.d4{animation-delay:.16s} .d5{animation-delay:.20s} .d6{animation-delay:.24s}
.d7{animation-delay:.28s} .d8{animation-delay:.32s}

/* ── Table scroll wrapper ────────────────────────────────────────── */
.table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }

/* ── Responsive ──────────────────────────────────────────────────── */
@media (max-width: 1100px) {
    .stats-grid { grid-template-columns: repeat(2,1fr); }
}
@media (max-width: 768px) {
    .main { margin-left: 0; padding: 16px; }
    .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
    .ph { flex-wrap: wrap; }
    .filters-bar { gap: 6px; }
}
@media (max-width: 480px) {
    .stats-grid { grid-template-columns: 1fr; }
    .ph-right { display: none; }
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
    <div class="topbar-greeting">
        <div class="topbar-greeting-hi">Sessions</div>
        <div class="topbar-greeting-date"><?= date('l, F j, Y') ?></div>
    </div>
    <div class="topbar-spacer"></div>
    <?php if ($liveSessions > 0): ?>
    <div class="topbar-status">
        <span class="topbar-status-dot"></span>
        <?= $liveSessions ?> live now
    </div>
    <?php endif; ?>
    <a href="/sessions/create.php" class="btn btn-primary btn-sm">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Create Session
    </a>
</header>

<!-- Main -->
<main class="main">

    <!-- Page header -->
    <div class="ph reveal d1">
        <div class="ph-left">
            <div class="ph-eyebrow"><span class="ph-eyebrow-dot"></span>Tutoring Sessions</div>
            <h1 class="ph-title">Session Management</h1>
            <p class="ph-sub">Schedule, track, and manage all tutoring sessions.</p>
        </div>
        <div class="ph-right">
            <a href="/sessions/create.php" class="btn btn-ghost btn-sm">
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Schedule New
            </a>
        </div>
    </div>

    <!-- STAT CARDS -->
    <div class="stats-grid">
        <div class="stat-card ac reveal d2">
            <div class="sc-top">
                <div class="sc-label">Total Sessions</div>
                <div class="sc-ico ac"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
            </div>
            <div class="sc-val"><?= number_format($totalSessions) ?></div>
            <div class="sc-change neutral">all time</div>
        </div>
        <div class="stat-card blue reveal d3">
            <div class="sc-top">
                <div class="sc-label">Upcoming</div>
                <div class="sc-ico blue"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14" stroke-linecap="round"/></svg></div>
            </div>
            <div class="sc-val"><?= number_format($upcomingSessions) ?></div>
            <div class="sc-change <?= $upcomingSessions > 0 ? 'up' : 'neutral' ?>">
                <?= $upcomingSessions > 0 ? 'scheduled' : 'none scheduled' ?>
            </div>
        </div>
        <div class="stat-card warn reveal d4">
            <div class="sc-top">
                <div class="sc-label">Live Now</div>
                <div class="sc-ico warn"><svg viewBox="0 0 24 24"><path d="M5.636 18.364a9 9 0 010-12.728"/><path d="M18.364 5.636a9 9 0 010 12.728"/><path d="M8.464 15.536a5 5 0 010-7.072"/><path d="M15.536 8.464a5 5 0 010 7.072"/><circle cx="12" cy="12" r="1"/></svg></div>
            </div>
            <div class="sc-val"><?= number_format($liveSessions) ?></div>
            <div class="sc-change <?= $liveSessions > 0 ? 'up' : 'neutral' ?>">
                <?php if ($liveSessions > 0): ?>
                    <svg viewBox="0 0 24 24"><polyline points="18 15 12 9 6 15"/></svg>
                    in progress
                <?php else: ?>
                    no active sessions
                <?php endif; ?>
            </div>
        </div>
        <div class="stat-card pur reveal d5">
            <div class="sc-top">
                <div class="sc-label">Total Enrollments</div>
                <div class="sc-ico pur"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></div>
            </div>
            <div class="sc-val"><?= number_format($totalEnrollments) ?></div>
            <div class="sc-change neutral">across all sessions</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-bar reveal d5">
        <a href="/sessions/index.php" class="filter-chip <?= !$filterStatus && !$filterType ? 'active' : '' ?>">All</a>

        <div class="filters-sep"></div>

        <a href="?status=scheduled" class="filter-chip <?= $filterStatus === 'scheduled' ? 'active' : '' ?>">Scheduled</a>
        <a href="?status=live"      class="filter-chip <?= $filterStatus === 'live'      ? 'active' : '' ?>">Live</a>
        <a href="?status=completed" class="filter-chip <?= $filterStatus === 'completed' ? 'active' : '' ?>">Completed</a>
        <a href="?status=cancelled" class="filter-chip <?= $filterStatus === 'cancelled' ? 'active' : '' ?>">Cancelled</a>

        <div class="filters-sep"></div>

        <a href="?type=group"      class="filter-chip <?= $filterType === 'group'      ? 'active' : '' ?>">Group</a>
        <a href="?type=one_on_one" class="filter-chip <?= $filterType === 'one_on_one' ? 'active' : '' ?>">1-on-1</a>
        <a href="?type=workshop"   class="filter-chip <?= $filterType === 'workshop'   ? 'active' : '' ?>">Workshop</a>
    </div>

    <!-- Sessions table -->
    <div class="card reveal d6">
        <div class="card-head" style="padding-bottom:12px">
            <div class="card-title">
                <?php if ($filterStatus): ?>
                    <?= ucfirst($filterStatus) ?> Sessions
                <?php elseif ($filterType): ?>
                    <?= $filterType === 'one_on_one' ? '1-on-1' : ucfirst($filterType) ?> Sessions
                <?php else: ?>
                    All Sessions
                <?php endif; ?>
            </div>
            <div class="card-count"><?= count($sessions) ?> result<?= count($sessions) !== 1 ? 's' : '' ?></div>
        </div>

        <?php if (empty($sessions)): ?>
        <div class="empty-state">
            <div class="empty-state-ico">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--tx3)"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <div class="empty-state-text">
                <?php if ($filterStatus || $filterType): ?>
                    No sessions match the current filter.
                <?php else: ?>
                    No sessions created yet.
                <?php endif; ?>
            </div>
            <div class="empty-state-sub">
                <?php if ($filterStatus || $filterType): ?>
                    Try a different filter or <a href="/sessions/index.php" style="color:var(--ac);text-decoration:none">view all sessions</a>.
                <?php else: ?>
                    <a href="/sessions/create.php" style="color:var(--ac);text-decoration:none">Create your first session</a> to get started.
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="table-scroll">
            <table class="s-table">
                <thead>
                    <tr>
                        <th>Session</th>
                        <th>Type</th>
                        <th>Date / Time</th>
                        <th>Duration</th>
                        <th>Enrolled</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($sessions as $s):
                    $enrolled  = (int)$s['enrolled_count'];
                    $max       = (int)$s['max_students'];
                    $pct       = $max > 0 ? round(($enrolled / $max) * 100) : 0;
                    $fillClass = $pct >= 100 ? 'full' : ($pct >= 80 ? 'warn' : '');

                    $typeLabel = match($s['session_type']) {
                        'one_on_one' => '1-on-1',
                        'workshop'   => 'Workshop',
                        default      => 'Group',
                    };
                ?>
                <tr>
                    <!-- Title -->
                    <td>
                        <div class="st-title"><?= htmlspecialchars($s['title']) ?></div>
                        <?php if (!empty($s['description'])): ?>
                        <span class="st-desc"><?= htmlspecialchars($s['description']) ?></span>
                        <?php endif; ?>
                    </td>

                    <!-- Type -->
                    <td>
                        <span class="st-type <?= htmlspecialchars($s['session_type']) ?>">
                            <?= $typeLabel ?>
                        </span>
                    </td>

                    <!-- Date/Time -->
                    <td>
                        <div class="st-date"><?= date('M j, Y', strtotime($s['session_date'])) ?></div>
                        <span class="st-time">
                            <?= date('g:i A', strtotime($s['session_date'])) ?>
                            <?php if ($s['status'] === 'scheduled'): ?>
                                &middot; <?= timeUntil($s['session_date']) ?>
                            <?php endif; ?>
                        </span>
                    </td>

                    <!-- Duration -->
                    <td>
                        <span class="st-duration"><?= (int)$s['duration_minutes'] ?>min</span>
                    </td>

                    <!-- Enrolled -->
                    <td>
                        <div class="enroll-bar">
                            <div class="enroll-meter">
                                <div class="enroll-fill <?= $fillClass ?>" style="width:<?= min($pct, 100) ?>%"></div>
                            </div>
                            <span class="enroll-text"><?= $enrolled ?>/<?= $max ?></span>
                        </div>
                    </td>

                    <!-- Status -->
                    <td>
                        <span class="st-status <?= htmlspecialchars($s['status']) ?>">
                            <span class="st-status-dot"></span>
                            <?= ucfirst($s['status']) ?>
                        </span>
                    </td>

                    <!-- Actions -->
                    <td>
                        <div class="st-actions">
                            <a href="/sessions/edit.php?id=<?= (int)$s['id'] ?>" class="st-act" title="Edit session">
                                <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </a>
                            <a href="/sessions/attendees.php?id=<?= (int)$s['id'] ?>" class="st-act attendees" title="View attendees">
                                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                            </a>
                            <?php if ($s['status'] === 'scheduled' || $s['status'] === 'live'): ?>
                            <a href="/sessions/cancel.php?id=<?= (int)$s['id'] ?>" class="st-act danger" title="Cancel session" onclick="return confirm('Cancel this session?')">
                                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</main>

</body>
</html>
