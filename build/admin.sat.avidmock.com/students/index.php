<?php
/**
 * students/index.php — All students list
 * Searchable, filterable, sortable, paginated, bulk-actionable.
 * Uses shared includes/head.php, includes/sidebar.php, includes/admin.css
 */
require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

$admin = currentAdmin();
$db    = Database::connect();

// ── Schema detection ──────────────────────────────────────────────────────
try {
    $allTables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $userCols  = $db->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    die("DB connection error: " . htmlspecialchars($e->getMessage()));
}

$nameExpr  = in_array('first_name', $userCols) ? "CONCAT(u.first_name,' ',u.last_name)" : "u.name";
$hasXp     = in_array('user_xp',           $allTables);
$hasAtt    = in_array('sat_quiz_attempts',  $allTables);
$hasAvatar = in_array('avatar',  $userCols);
$hasStatus = in_array('status',  $userCols);

$xpJoin   = $hasXp ? "LEFT JOIN user_xp ux ON ux.user_id = u.id" : "";
$xpFields = $hasXp
    ? "COALESCE(ux.xp, 0) AS total_xp, COALESCE(ux.level, 1) AS level,"
    : "0 AS total_xp, 1 AS level,";

$attFields = $hasAtt
    ? "(SELECT COUNT(*) FROM sat_quiz_attempts WHERE user_id = u.id AND status = 'completed') AS attempts,
       (SELECT ROUND(AVG(score), 1) FROM sat_quiz_attempts WHERE user_id = u.id AND status = 'completed') AS avg_score,
       (SELECT MAX(completed_at) FROM sat_quiz_attempts WHERE user_id = u.id) AS last_active,"
    : "0 AS attempts, NULL AS avg_score, NULL AS last_active,";

$avatarField = $hasAvatar ? "u.avatar," : "NULL AS avatar,";
$statusField = $hasStatus ? "u.status," : "'active' AS status,";

// ── Filters ───────────────────────────────────────────────────────────────
$search  = trim($_GET['q']     ?? '');
$status  = $_GET['status']     ?? '';
$sort    = $_GET['sort']       ?? 'created_at';
$dir     = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

$allowed = ['name','email','created_at','total_xp','attempts','avg_score','status'];
if (!in_array($sort, $allowed)) $sort = 'created_at';

// ── WHERE ─────────────────────────────────────────────────────────────────
$where  = "WHERE u.role = 'student'";
$params = [];

if ($search !== '') {
    $where .= " AND ({$nameExpr} LIKE :s OR u.email LIKE :s2)";
    $params[':s']  = "%{$search}%";
    $params[':s2'] = "%{$search}%";
}
if ($status !== '' && $hasStatus) {
    $where .= " AND u.status = :st";
    $params[':st'] = $status;
}

// ── ORDER BY ──────────────────────────────────────────────────────────────
$sortCol = match($sort) {
    'name'      => $nameExpr,
    'email'     => 'u.email',
    'status'    => $hasStatus ? 'u.status' : 'u.created_at',
    'total_xp'  => $hasXp  ? 'COALESCE(ux.xp, 0)' : 'u.created_at',
    'attempts'  => $hasAtt ? "(SELECT COUNT(*) FROM sat_quiz_attempts WHERE user_id = u.id AND status = 'completed')" : 'u.created_at',
    'avg_score' => $hasAtt ? "(SELECT ROUND(AVG(score), 1) FROM sat_quiz_attempts WHERE user_id = u.id AND status = 'completed')" : 'u.created_at',
    default     => 'u.created_at',
};

// ── Count ─────────────────────────────────────────────────────────────────
try {
    $cntStmt = $db->prepare("SELECT COUNT(*) FROM users u {$xpJoin} {$where}");
    $cntStmt->execute($params);
    $total = (int)$cntStmt->fetchColumn();
} catch (Throwable $e) {
    die("Count query error: " . htmlspecialchars($e->getMessage()));
}

$pages  = max(1, ceil($total / $perPage));
$page   = min($page, $pages);
$offset = ($page - 1) * $perPage;

// ── Main query ────────────────────────────────────────────────────────────
try {
    $stmt = $db->prepare(
        "SELECT u.id, {$nameExpr} AS name, u.email, {$statusField} u.created_at,
                {$xpFields}
                {$attFields}
                {$avatarField}
                u.id AS uid
         FROM users u {$xpJoin}
         {$where}
         ORDER BY {$sortCol} {$dir}
         LIMIT :lim OFFSET :off"
    );
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    die("Main query error: " . htmlspecialchars($e->getMessage()));
}

// ── Summary stats ─────────────────────────────────────────────────────────
$stats = ['total' => $total, 'active' => 0, 'suspended' => 0, 'new_week' => 0];
try {
    $statusSums = $hasStatus
        ? "SUM(status = 'active') AS active, SUM(status = 'suspended') AS suspended,"
        : "0 AS active, 0 AS suspended,";
    $r = $db->query(
        "SELECT {$statusSums}
                SUM(created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS new_week
         FROM users WHERE role = 'student'"
    )->fetch(PDO::FETCH_ASSOC);
    if ($r) {
        $stats['active']    = (int)($r['active']    ?? 0);
        $stats['suspended'] = (int)($r['suspended'] ?? 0);
        $stats['new_week']  = (int)($r['new_week']  ?? 0);
    }
} catch (Throwable) {}

// ── Sort URL helpers ──────────────────────────────────────────────────────
function sq(string $col, string $cur, string $curDir): string {
    $d = ($col === $cur && $curDir === 'ASC') ? 'desc' : 'asc';
    return '?' . http_build_query(array_merge($_GET, ['sort' => $col, 'dir' => $d, 'page' => 1]));
}
function si(string $col, string $cur, string $curDir): string {
    if ($col !== $cur) return '<svg width="8" height="10" viewBox="0 0 10 14" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 1v12M1 9l4 4 4-4"/></svg>';
    return $curDir === 'ASC'
        ? '<svg width="8" height="10" viewBox="0 0 10 14" fill="none" stroke="var(--ac)" stroke-width="2.5"><path d="M1 5l4-4 4 4"/></svg>'
        : '<svg width="8" height="10" viewBox="0 0 10 14" fill="none" stroke="var(--ac)" stroke-width="2.5"><path d="M1 9l4 4 4-4"/></svg>';
}

// ── Avatar color palette ──────────────────────────────────────────────────
function avatarColor(string $name): array {
    $colors = [
        ['bg' => 'rgba(31,226,144,.15)', 'fg' => '#1fe290'],
        ['bg' => 'rgba(59,130,246,.15)', 'fg' => '#60a5fa'],
        ['bg' => 'rgba(245,158,11,.15)', 'fg' => '#fbbf24'],
        ['bg' => 'rgba(239,68,68,.15)',  'fg' => '#f87171'],
        ['bg' => 'rgba(168,85,247,.15)', 'fg' => '#c084fc'],
        ['bg' => 'rgba(20,184,166,.15)', 'fg' => '#2dd4bf'],
    ];
    return $colors[abs(crc32($name)) % count($colors)];
}

// ── Head setup ────────────────────────────────────────────────────────────
$pageTitle  = 'Students — Avidmock Admin';
$activePage = 'students';
$extraHead  = <<<'CSS'
<style>
/* ── Page layout ─────────────────────────────────────────────────────── */
.main {
    margin-left: var(--sb-w);
    margin-top: var(--top-h);
    padding: 32px 28px;
    min-height: calc(100vh - var(--top-h));
}

/* ── Page header ─────────────────────────────────────────────────────── */
.ph { margin-bottom: 24px; }
.ph-eyebrow { display: flex; align-items: center; gap: 6px; font-size: .5rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 5px; }
.ph-dot { width: 4px; height: 4px; border-radius: 50%; background: var(--ac); }
.ph-title { font-family: var(--fh); font-size: 1.875rem; font-weight: 900; color: var(--tx); letter-spacing: -.035em; line-height: 1.1; }
.ph-sub { font-size: .875rem; color: var(--tx2); margin-top: 4px; }

/* ── Stats grid ─────────────────────────────────────────────────────── */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px; margin-bottom: 24px;
}
.stat-card {
    background: var(--sf); border: 1px solid var(--bd);
    border-radius: 14px; padding: 18px 20px;
    transition: all .2s; position: relative; overflow: hidden;
}
.stat-card::after {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,.06), transparent);
}
.stat-card:hover { background: var(--sf2); transform: translateY(-2px); box-shadow: 0 8px 28px rgba(0,0,0,.18); }
.sc-label { font-size: .4375rem; font-weight: 800; color: var(--tx3); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
.sc-val { font-family: var(--fh); font-size: 2rem; font-weight: 900; letter-spacing: -.05em; line-height: 1; margin-bottom: 3px; }
.sc-val.good    { color: var(--ac); }
.sc-val.neutral { color: var(--tx); }
.sc-val.warn    { color: var(--warn); }
.sc-sub { font-size: .5625rem; color: var(--tx3); font-weight: 600; }

/* ── Filter bar ─────────────────────────────────────────────────────── */
.filter-bar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 16px; }
.search-wrap { position: relative; flex: 1; min-width: 200px; max-width: 340px; }
.search-wrap svg { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); width: 14px; height: 14px; stroke: var(--tx3); fill: none; stroke-width: 1.8; stroke-linecap: round; pointer-events: none; }
.search-input { width: 100%; padding: 8px 12px 8px 34px; background: var(--sf); border: 1.5px solid var(--bd); border-radius: 9px; font-family: var(--ff); font-size: .8125rem; color: var(--tx); outline: none; transition: border-color .18s; }
.search-input::placeholder { color: var(--tx3); }
.search-input:focus { border-color: var(--ac); box-shadow: 0 0 0 3px rgba(31,226,144,.1); }
.filter-sel { padding: 8px 32px 8px 12px; background: var(--sf); border: 1.5px solid var(--bd); border-radius: 9px; font-family: var(--ff); font-size: .8125rem; color: var(--tx); outline: none; appearance: none; cursor: pointer; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%234f7a75' stroke-width='2' stroke-linecap='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 10px center; transition: border-color .18s; }
.filter-sel:focus { border-color: var(--ac); }

/* ── Table card ─────────────────────────────────────────────────────── */
.table-card { background: var(--sf); border: 1px solid var(--bd); border-radius: 14px; overflow: hidden; margin-top: 20px; }
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
thead th { padding: 10px 16px; font-size: .5625rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: .8px; text-align: left; border-bottom: 1px solid var(--bd); background: rgba(255,255,255,.015); white-space: nowrap; }
thead th a { color: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
thead th a:hover { color: var(--tx2); }
tbody td { padding: 13px 16px; font-size: .8125rem; border-bottom: 1px solid var(--bd); vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }
tbody tr { transition: background .14s; }
tbody tr:hover { background: rgba(255,255,255,.025); }
tbody tr.sel { background: rgba(31,226,144,.04); }
.cb-col { width: 42px; }
.cb { width: 14px; height: 14px; accent-color: var(--ac); cursor: pointer; }

/* ── Cell types ─────────────────────────────────────────────────────── */
.ava { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: .6875rem; font-weight: 800; flex-shrink: 0; }
.s-name  { font-weight: 700; color: var(--tx); letter-spacing: -.01em; }
.s-email { display: block; font-size: .625rem; color: var(--tx3); font-family: var(--fm); margin-top: 2px; }
.s-name a { color: inherit; text-decoration: none; }
.s-name a:hover { color: var(--ac); }

.status-pill { display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 50px; font-size: .5rem; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; }
.status-pill.active    { background: var(--ac3); color: var(--ac); border: 1px solid rgba(31,226,144,.18); }
.status-pill.suspended { background: rgba(239,68,68,.1); color: var(--err); border: 1px solid rgba(239,68,68,.2); }
.status-pill.inactive  { background: var(--sf3); color: var(--tx3); border: 1px solid var(--bd); }
.status-dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; flex-shrink: 0; }

.num { font-family: var(--fm); font-size: .8125rem; font-weight: 500; color: var(--tx2); }
.xp  { font-family: var(--fm); font-size: .8125rem; font-weight: 700; color: var(--ac); }
.score { font-family: var(--fm); font-size: .8125rem; font-weight: 700; color: var(--tx2); }
.score.hi { color: var(--ac); } .score.md { color: var(--warn); } .score.lo { color: var(--err); }
.dim { color: var(--tx3); font-size: .75rem; }

/* ── Row action — View only ─────────────────────────────────────────── */
.row-actions { display: flex; align-items: center; gap: 4px; opacity: 0; transition: opacity .14s; }
tbody tr:hover .row-actions { opacity: 1; }
.icon-btn {
    width: 28px; height: 28px; border-radius: 7px;
    display: flex; align-items: center; justify-content: center;
    background: var(--sf2); border: 1px solid var(--bd);
    color: var(--tx3); cursor: pointer; text-decoration: none;
    transition: all .15s;
}
.icon-btn:hover { background: var(--ac3); color: var(--ac); border-color: rgba(31,226,144,.2); }
.icon-btn svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }
.view-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 12px; border-radius: 7px;
    border: 1px solid var(--bd); background: var(--sf2);
    color: var(--tx2); font-size: .75rem; font-weight: 700;
    text-decoration: none; transition: all .16s; font-family: var(--ff);
}
.view-btn:hover { background: var(--ac); color: var(--dk); border-color: var(--ac); }
.view-btn svg { width: 11px; height: 11px; stroke: currentColor; fill: none; stroke-width: 2.2; stroke-linecap: round; }

/* ── Bulk bar ───────────────────────────────────────────────────────── */
.bulk-bar { display: none; align-items: center; gap: 10px; padding: 10px 16px; background: var(--ac3); border-bottom: 1px solid rgba(31,226,144,.15); }
.bulk-bar.show { display: flex; }
.bulk-count { font-size: .8125rem; font-weight: 700; color: var(--ac); }

/* ── Pagination ─────────────────────────────────────────────────────── */
.pagination { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-top: 1px solid var(--bd); flex-wrap: wrap; gap: 10px; }
.pag-info { font-size: .75rem; color: var(--tx3); }
.pag-btns { display: flex; gap: 4px; }
.pag-btn { min-width: 30px; height: 30px; padding: 0 8px; border-radius: 7px; display: flex; align-items: center; justify-content: center; font-size: .75rem; font-weight: 700; text-decoration: none; transition: all .16s; color: var(--tx3); background: var(--sf); border: 1px solid var(--bd); }
.pag-btn:hover { background: var(--sf2); color: var(--tx); }
.pag-btn.active { background: var(--ac3); color: var(--ac); border-color: rgba(31,226,144,.2); }
.pag-btn.disabled { opacity: .35; pointer-events: none; }
.pag-btn.gap { background: none; border-color: transparent; cursor: default; }

/* ── Empty state ────────────────────────────────────────────────────── */
.empty { text-align: center; padding: 80px 20px; }
.empty-ico { font-size: 2.5rem; margin-bottom: 16px; opacity: .2; }
.empty-title { font-family: var(--fh); font-size: 1.25rem; font-weight: 900; color: var(--tx); letter-spacing: -.025em; margin-bottom: 8px; }
.empty-sub { font-size: .875rem; color: var(--tx3); }

/* ── Responsive ─────────────────────────────────────────────────────── */
@media (max-width: 1100px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 768px)  { .main { margin-left: 0; padding: 16px; } .stats-grid { grid-template-columns: 1fr 1fr; } }
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
    <div class="topbar-title">Students <span>/ All</span></div>
    <div class="topbar-spacer"></div>
    <a href="/students/export.php?<?= http_build_query(array_filter(['q' => $search, 'status' => $status])) ?>" class="btn btn-ghost btn-sm">
        <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export CSV
    </a>
    <a href="/students/add.php" class="btn btn-primary">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Student
    </a>
</header>

<!-- Main -->
<main class="main">

    <!-- Page header -->
    <div class="ph reveal d1">
        <div class="ph-eyebrow"><span class="ph-dot"></span>Students</div>
        <h1 class="ph-title">All Students</h1>
        <p class="ph-sub">
            <?= number_format($total) ?> student<?= $total !== 1 ? 's' : '' ?> registered<?= $search ? ' matching "<em>' . htmlspecialchars($search) . '</em>"' : '' ?>
        </p>

        <!-- Filter bar -->
        <div class="filter-bar">
            <form method="get" style="display:contents">
                <?php if ($hasStatus): ?>
                <select name="status" class="filter-sel" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="active"    <?= $status === 'active'    ? 'selected' : '' ?>>Active</option>
                    <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                    <option value="inactive"  <?= $status === 'inactive'  ? 'selected' : '' ?>>Inactive</option>
                </select>
                <?php endif; ?>
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                <input type="hidden" name="dir"  value="<?= strtolower($dir) ?>">
                <div class="search-wrap">
                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="search" name="q" class="search-input"
                           placeholder="Search name or email…"
                           value="<?= htmlspecialchars($search) ?>"
                           autocomplete="off"
                           onkeydown="if(event.key==='Enter')this.closest('form').submit()">
                </div>
                <?php if ($search !== '' || $status !== ''): ?>
                <a href="/students/index.php" class="btn btn-ghost btn-sm">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid reveal d2">
        <div class="stat-card">
            <div class="sc-label">Total Students</div>
            <div class="sc-val neutral"><?= number_format($stats['total']) ?></div>
            <div class="sc-sub">all time</div>
        </div>
        <div class="stat-card">
            <div class="sc-label">Active</div>
            <div class="sc-val good"><?= number_format($stats['active']) ?></div>
            <div class="sc-sub">in good standing</div>
        </div>
        <div class="stat-card">
            <div class="sc-label">Suspended</div>
            <div class="sc-val warn"><?= number_format($stats['suspended']) ?></div>
            <div class="sc-sub">access restricted</div>
        </div>
        <div class="stat-card">
            <div class="sc-label">New This Week</div>
            <div class="sc-val good"><?= number_format($stats['new_week']) ?></div>
            <div class="sc-sub">last 7 days</div>
        </div>
    </div>

    <!-- Table card -->
    <div class="table-card reveal d3">

        <!-- Bulk action bar -->
        <div class="bulk-bar" id="bulkBar">
            <span class="bulk-count" id="bulkCount">0 selected</span>
            <button class="btn btn-sm btn-ghost" onclick="bulkAction('activate')">Activate</button>
            <button class="btn btn-sm btn-ghost" onclick="bulkAction('suspend')">Suspend</button>
            <button class="btn btn-sm btn-danger" onclick="bulkAction('delete')">Delete</button>
            <button class="btn btn-sm btn-ghost" onclick="clearSelection()">Clear</button>
        </div>

        <?php if (empty($students)): ?>
        <div class="empty">
            <div class="empty-ico">👥</div>
            <div class="empty-title">
                <?= $status !== '' ? "No {$status} students" : 'No students found' ?>
            </div>
            <div class="empty-sub">
                <?= $search ? 'Try a different search term.' : 'Add your first student to get started.' ?>
            </div>
        </div>

        <?php else: ?>
        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th class="cb-col">
                        <input type="checkbox" class="cb" id="selAll" onchange="toggleAll(this)">
                    </th>
                    <th><a href="<?= sq('name', $sort, $dir) ?>">Student <?= si('name', $sort, $dir) ?></a></th>
                    <?php if ($hasStatus): ?>
                    <th><a href="<?= sq('status', $sort, $dir) ?>">Status <?= si('status', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if ($hasXp): ?>
                    <th><a href="<?= sq('total_xp', $sort, $dir) ?>">XP <?= si('total_xp', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <?php if ($hasAtt): ?>
                    <th><a href="<?= sq('attempts', $sort, $dir) ?>">Attempts <?= si('attempts', $sort, $dir) ?></a></th>
                    <th><a href="<?= sq('avg_score', $sort, $dir) ?>">Avg Score <?= si('avg_score', $sort, $dir) ?></a></th>
                    <?php endif; ?>
                    <th><a href="<?= sq('created_at', $sort, $dir) ?>">Joined <?= si('created_at', $sort, $dir) ?></a></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($students as $s):
                $ac = avatarColor($s['name'] ?? '');
                $st = $s['status'] ?? 'active';
            ?>
            <tr data-id="<?= (int)$s['id'] ?>">
                <td class="cb-col">
                    <input type="checkbox" class="cb row-cb" value="<?= (int)$s['id'] ?>" onchange="updateBulk()">
                </td>
                <td>
                    <div style="display:flex;align-items:center;gap:10px">
                        <div class="ava" style="background:<?= $ac['bg'] ?>;color:<?= $ac['fg'] ?>">
                            <?= strtoupper(substr($s['name'] ?? '?', 0, 1)) ?>
                        </div>
                        <div>
                            <div class="s-name">
                                <a href="/students/student.php?id=<?= (int)$s['id'] ?>">
                                    <?= htmlspecialchars($s['name'] ?? '') ?>
                                </a>
                            </div>
                            <code class="s-email"><?= htmlspecialchars($s['email'] ?? '') ?></code>
                        </div>
                    </div>
                </td>
                <?php if ($hasStatus): ?>
                <td>
                    <span class="status-pill <?= htmlspecialchars($st) ?>">
                        <?php if ($st === 'active'): ?><span class="status-dot"></span><?php endif; ?>
                        <?= ucfirst(htmlspecialchars($st)) ?>
                    </span>
                </td>
                <?php endif; ?>
                <?php if ($hasXp): ?>
                <td><span class="xp"><?= number_format((int)($s['total_xp'] ?? 0)) ?></span></td>
                <?php endif; ?>
                <?php if ($hasAtt): ?>
                <td><span class="num"><?= number_format((int)($s['attempts'] ?? 0)) ?></span></td>
                <td>
                    <?php if (isset($s['avg_score']) && $s['avg_score'] !== null):
                        $sv = (float)$s['avg_score'];
                        $sc = $sv >= 80 ? 'hi' : ($sv >= 60 ? 'md' : 'lo');
                    ?>
                    <span class="score <?= $sc ?>"><?= number_format($sv, 1) ?>%</span>
                    <?php else: ?>
                    <span class="dim">—</span>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
                <td style="color:var(--tx3);font-size:.625rem;font-family:var(--fm);white-space:nowrap">
                    <?= isset($s['created_at']) ? date('M j, Y', strtotime($s['created_at'])) : '—' ?>
                </td>
                <td>
                    <div class="row-actions">
                        <a href="/students/student.php?id=<?= (int)$s['id'] ?>" class="view-btn">
                            <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            View
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <?php if ($pages > 1): ?>
        <div class="pagination">
            <div class="pag-info">
                Showing <?= number_format($offset + 1) ?>–<?= number_format(min($offset + $perPage, $total)) ?> of <?= number_format($total) ?>
            </div>
            <div class="pag-btns">
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])) ?>"
                   class="pag-btn <?= $page <= 1 ? 'disabled' : '' ?>">‹</a>
                <?php
                $startP = max(1, $page - 2);
                $endP   = min($pages, $page + 2);
                if ($startP > 1) echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '" class="pag-btn">1</a>';
                if ($startP > 2) echo '<span class="pag-btn gap">…</span>';
                for ($p = $startP; $p <= $endP; $p++):
                ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"
                   class="pag-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php
                if ($endP < $pages - 1) echo '<span class="pag-btn gap">…</span>';
                if ($endP < $pages)     echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $pages])) . '" class="pag-btn">' . $pages . '</a>';
                ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => min($pages, $page + 1)])) ?>"
                   class="pag-btn <?= $page >= $pages ? 'disabled' : '' ?>">›</a>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

    </div><!-- /table-card -->

</main>

<div class="toast-wrap" id="toastWrap"></div>

<script>
// ── Checkbox selection ────────────────────────────────────────────────────
function toggleAll(master) {
    document.querySelectorAll('.row-cb').forEach(cb => cb.checked = master.checked);
    updateBulk();
}
function updateBulk() {
    const cbs = document.querySelectorAll('.row-cb:checked');
    const all = document.querySelectorAll('.row-cb');
    document.getElementById('bulkCount').textContent = cbs.length + ' selected';
    document.getElementById('bulkBar').classList.toggle('show', cbs.length > 0);
    document.getElementById('selAll').indeterminate = cbs.length > 0 && cbs.length < all.length;
    document.querySelectorAll('tr[data-id]').forEach(r => {
        const c = r.querySelector('.row-cb');
        r.classList.toggle('sel', c && c.checked);
    });
}
function clearSelection() {
    document.querySelectorAll('.row-cb, #selAll').forEach(cb => cb.checked = false);
    document.getElementById('selAll').indeterminate = false;
    document.getElementById('bulkBar').classList.remove('show');
    document.querySelectorAll('tr[data-id]').forEach(r => r.classList.remove('sel'));
}
function getSelected() {
    return [...document.querySelectorAll('.row-cb:checked')].map(c => +c.value);
}

// ── Bulk actions ──────────────────────────────────────────────────────────
async function bulkAction(action) {
    const ids = getSelected();
    if (!ids.length) return;
    const label = action.charAt(0).toUpperCase() + action.slice(1);
    if ((action === 'delete' || action === 'suspend') &&
        !confirm(`${label} ${ids.length} student${ids.length !== 1 ? 's' : ''}?`)) return;
    try {
        const res  = await fetch('/students/bulk-action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, ids }),
        });
        const data = await res.json();
        if (data.success) { toast(data.message || 'Done.', 'success'); setTimeout(() => location.reload(), 900); }
        else toast(data.error || 'Something went wrong.', 'error');
    } catch { toast('Network error.', 'error'); }
}

// ── Toast ─────────────────────────────────────────────────────────────────
function toast(msg, type = 'info') {
    const wrap = document.getElementById('toastWrap');
    const el   = document.createElement('div');
    el.className   = `toast ${type}`;
    el.textContent = msg;
    wrap.appendChild(el);
    requestAnimationFrame(() => requestAnimationFrame(() => el.classList.add('show')));
    setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 400); }, 3200);
}
</script>
</body>
</html>