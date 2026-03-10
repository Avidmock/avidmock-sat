<?php
/**
 * revenue/subscriptions.php — Subscription Management
 *
 * Filterable table of all subscribers with search, filtering, bulk actions,
 * and individual subscriber management.
 */

require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

$admin = currentAdmin();
$db    = Database::connect();

// ── Helpers ──────────────────────────────────────────────────────────────────
function sq(PDO $db, string $sql, array $params = []): mixed {
    try { $stmt = $db->prepare($sql); $stmt->execute($params); return $stmt->fetchColumn(); }
    catch (Throwable) { return 0; }
}
function sqAll(PDO $db, string $sql, array $params = []): array {
    try { $stmt = $db->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC); }
    catch (Throwable) { return []; }
}
function fmtMoney(float $v): string {
    if ($v >= 1000) return '$' . number_format($v / 1000, 1) . 'K';
    return '$' . number_format($v, 2);
}

$planPrices = ['free' => 0, 'pro' => 14.99, 'family' => 24.99];

// ── Schema detection ─────────────────────────────────────────────────────────
$allTables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$hasUsers  = in_array('users', $allTables);
$hasSubEvents = in_array('subscription_events', $allTables);

$hasSubPlan = false;
$hasSubStatus = false;
$hasSubStarted = false;
$nameExpr = "'Unknown'";

if ($hasUsers) {
    $userCols = array_column($db->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_ASSOC), 'Field');
    $hasSubPlan    = in_array('subscription_plan', $userCols);
    $hasSubStatus  = in_array('subscription_status', $userCols);
    $hasSubStarted = in_array('subscription_started_at', $userCols);

    if (in_array('first_name', $userCols) && in_array('last_name', $userCols)) {
        $nameExpr = "CONCAT(u.first_name,' ',u.last_name)";
    } elseif (in_array('name', $userCols)) {
        $nameExpr = "u.name";
    } else {
        $nameExpr = "u.email";
    }
}

// ── Filters ──────────────────────────────────────────────────────────────────
$search     = trim($_GET['q'] ?? '');
$planFilter = $_GET['plan'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 25;

// ── Build query ──────────────────────────────────────────────────────────────
$subscribers = [];
$totalCount  = 0;
$totalPages  = 1;

// Stats
$totalActive    = 0;
$newThisMonth   = 0;
$cancelledThisMonth = 0;
$netChange      = 0;

if ($hasUsers && $hasSubPlan) {
    $where  = ["1=1"];
    $params = [];

    if ($planFilter !== 'all' && in_array($planFilter, ['free', 'pro', 'family'])) {
        $where[] = "u.subscription_plan = ?";
        $params[] = $planFilter;
    }

    if ($hasSubStatus && $statusFilter !== 'all' && in_array($statusFilter, ['active', 'cancelled', 'past_due'])) {
        $where[] = "u.subscription_status = ?";
        $params[] = $statusFilter;
    }

    if ($search !== '') {
        $searchLike = "%{$search}%";
        if (str_contains($nameExpr, 'CONCAT')) {
            $where[] = "({$nameExpr} LIKE ? OR u.email LIKE ?)";
        } else {
            $where[] = "({$nameExpr} LIKE ? OR u.email LIKE ?)";
        }
        $params[] = $searchLike;
        $params[] = $searchLike;
    }

    // Special filter: at-risk
    $atRiskFilter = ($_GET['filter'] ?? '') === 'at_risk';
    $atRiskJoin = '';
    if ($atRiskFilter) {
        $where[] = "u.subscription_plan IN ('pro','family')";
        $atRiskJoin = "LEFT JOIN (SELECT user_id, MAX(started_at) AS last_active FROM sat_quiz_attempts GROUP BY user_id) la ON la.user_id = u.id";
        $where[] = "(la.last_active IS NULL OR la.last_active < DATE_SUB(NOW(), INTERVAL 7 DAY))";
    }

    $whereStr = implode(' AND ', $where);

    // Count
    $totalCount = (int) sq($db,
        "SELECT COUNT(*) FROM users u {$atRiskJoin} WHERE {$whereStr}", $params
    );
    $totalPages = max(1, (int) ceil($totalCount / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    // Fetch subscribers
    $statusCol    = $hasSubStatus ? "u.subscription_status" : "'active'";
    $startedCol   = $hasSubStarted ? "u.subscription_started_at" : "u.created_at";

    $subscribers = sqAll($db,
        "SELECT u.id, {$nameExpr} AS name, u.email,
                u.subscription_plan AS plan,
                {$statusCol} AS sub_status,
                {$startedCol} AS started_at,
                u.created_at
         FROM users u
         {$atRiskJoin}
         WHERE {$whereStr}
         ORDER BY u.created_at DESC
         LIMIT {$perPage} OFFSET {$offset}",
        $params
    );

    // Stats summary
    $statusActive = $hasSubStatus ? "AND (subscription_status = 'active' OR subscription_status IS NULL)" : "";
    $totalActive = (int) sq($db,
        "SELECT COUNT(*) FROM users WHERE subscription_plan IN ('pro','family') {$statusActive}"
    );
    $newThisMonth = (int) sq($db,
        "SELECT COUNT(*) FROM users
         WHERE subscription_plan IN ('pro','family')
         AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"
    );
    if ($hasSubStatus) {
        $cancelledThisMonth = (int) sq($db,
            "SELECT COUNT(*) FROM users
             WHERE subscription_status = 'cancelled'
             AND updated_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"
        );
    } elseif ($hasSubEvents) {
        $cancelledThisMonth = (int) sq($db,
            "SELECT COUNT(DISTINCT user_id) FROM subscription_events
             WHERE event_type='cancel'
             AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"
        );
    }
    $netChange = $newThisMonth - $cancelledThisMonth;
}

// ── CSV Export ───────────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="subscribers_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Name', 'Email', 'Plan', 'Status', 'Started', 'MRR Contribution']);

    if ($hasUsers && $hasSubPlan) {
        $statusCol  = $hasSubStatus ? "u.subscription_status" : "'active'";
        $startedCol = $hasSubStarted ? "u.subscription_started_at" : "u.created_at";
        $allSubs = sqAll($db,
            "SELECT u.id, {$nameExpr} AS name, u.email,
                    u.subscription_plan AS plan,
                    {$statusCol} AS sub_status,
                    {$startedCol} AS started_at
             FROM users u
             WHERE u.subscription_plan IN ('pro','family')
             ORDER BY u.created_at DESC"
        );
        foreach ($allSubs as $s) {
            fputcsv($out, [
                $s['id'], $s['name'], $s['email'], ucfirst($s['plan']),
                ucfirst($s['sub_status'] ?? 'active'),
                $s['started_at'] ? date('Y-m-d', strtotime($s['started_at'])) : '',
                '$' . number_format($planPrices[$s['plan']] ?? 0, 2)
            ]);
        }
    }
    fclose($out);
    exit;
}

// ── Head setup ───────────────────────────────────────────────────────────────
$pageTitle  = 'Subscriptions — Avidmock Admin';
$activePage = 'subscriptions';
$extraHead  = <<<'CSS'
<style>
.main { margin-left: var(--sb-w); margin-top: var(--top-h); padding: 28px; min-height: calc(100vh - var(--top-h)); }

.ph { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 24px; }
.ph-eyebrow { display: inline-flex; align-items: center; gap: 6px; font-size: .5rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 6px; }
.ph-eyebrow-dot { width: 4px; height: 4px; border-radius: 50%; background: var(--ac); }
.ph-title { font-family: var(--fh); font-size: clamp(1.5rem,2.5vw,2rem); font-weight: 900; color: var(--tx); letter-spacing: -.03em; line-height: 1.1; }
.ph-sub { font-size: .875rem; color: var(--tx2); margin-top: 4px; }
.ph-right { display: flex; gap: 8px; flex-shrink: 0; flex-wrap: wrap; }

/* Stats summary row */
.sub-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 20px; }
.sub-stat {
    background: var(--sf); border: 1px solid var(--bd); border-radius: 12px;
    padding: 16px; text-align: center; transition: all .2s;
}
.sub-stat:hover { background: var(--sf2); border-color: var(--bd2); transform: translateY(-1px); }
.sub-stat-val { font-family: var(--fh); font-size: 1.75rem; font-weight: 900; color: var(--tx); letter-spacing: -.03em; line-height: 1; margin-bottom: 4px; }
.sub-stat-label { font-size: .625rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: .6px; }
.sub-stat-val.green { color: var(--ac); }
.sub-stat-val.red { color: var(--err); }
.sub-stat-val.blue { color: var(--blue); }

/* Filters bar */
.filters {
    display: flex; align-items: center; gap: 10px; margin-bottom: 16px; flex-wrap: wrap;
    background: var(--sf); border: 1px solid var(--bd); border-radius: 12px;
    padding: 12px 16px;
}
.filter-search {
    flex: 1; min-width: 200px; padding: 8px 14px; border-radius: 8px;
    background: var(--sf2); border: 1px solid var(--bd);
    font-family: var(--ff); font-size: .8125rem; color: var(--tx);
    outline: none; transition: border-color .2s;
}
.filter-search:focus { border-color: var(--ac); }
.filter-search::placeholder { color: var(--tx3); }
.filter-select {
    padding: 8px 12px; border-radius: 8px;
    background: var(--sf2); border: 1px solid var(--bd);
    font-family: var(--ff); font-size: .75rem; font-weight: 600; color: var(--tx2);
    cursor: pointer; outline: none; appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%239dbfba' stroke-width='2.5' stroke-linecap='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 10px center; padding-right: 30px;
}
.filter-select:focus { border-color: var(--ac); }
.filter-select option { background: var(--ink); color: var(--tx); }
.filter-spacer { flex: 1; }

/* Card */
.card { background: var(--sf); border: 1px solid var(--bd); border-radius: 16px; overflow: hidden; }
.card-head { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px 0; }
.card-title { font-size: .9375rem; font-weight: 700; color: var(--tx); }

/* Subscribers table */
.sub-table { width: 100%; border-collapse: collapse; }
.sub-table th {
    padding: 10px 16px; font-size: .5625rem; font-weight: 700; color: var(--tx3);
    text-transform: uppercase; letter-spacing: .6px; text-align: left;
    border-bottom: 1px solid var(--bd); background: rgba(255,255,255,.015);
    white-space: nowrap;
}
.sub-table td {
    padding: 12px 16px; font-size: .8125rem; border-bottom: 1px solid var(--bd);
    vertical-align: middle;
}
.sub-table tbody tr:last-child td { border-bottom: none; }
.sub-table tbody tr { transition: background .14s; }
.sub-table tbody tr:hover { background: var(--sf2); }
.sub-name { font-weight: 700; color: var(--tx); }
.sub-email { font-size: .625rem; color: var(--tx3); margin-top: 1px; }
.plan-pill {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px; border-radius: 50px; font-size: .5625rem; font-weight: 800;
    text-transform: uppercase; letter-spacing: .4px;
}
.plan-pill.free   { background: var(--sf3); color: var(--tx3); }
.plan-pill.pro    { background: var(--ac3); color: var(--ac); }
.plan-pill.family { background: var(--purple2); color: var(--purple); }
.status-pill-sm {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 8px; border-radius: 50px; font-size: .5rem; font-weight: 800;
    text-transform: uppercase; letter-spacing: .4px;
}
.status-pill-sm.active   { background: var(--ac3); color: var(--ac); }
.status-pill-sm.cancelled { background: var(--err2); color: var(--err); }
.status-pill-sm.past_due  { background: var(--warn2); color: var(--warn); }
.status-dot-sm { width: 4px; height: 4px; border-radius: 50%; background: currentColor; }
.mrr-val { font-family: var(--fm); font-weight: 700; color: var(--ac); }
.mrr-val.zero { color: var(--tx3); }
.date-val { font-family: var(--fm); font-size: .6875rem; color: var(--tx2); }
.actions-cell { display: flex; gap: 4px; }

/* Pagination */
.pagination {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 20px; border-top: 1px solid var(--bd);
}
.pagination-info { font-size: .75rem; color: var(--tx3); }
.pagination-pages { display: flex; gap: 4px; }
.page-btn {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 32px; height: 32px; padding: 0 8px; border-radius: 8px;
    background: var(--sf2); border: 1px solid var(--bd);
    font-size: .75rem; font-weight: 600; color: var(--tx2);
    text-decoration: none; transition: all .16s; cursor: pointer;
}
.page-btn:hover { background: var(--sf3); color: var(--tx); border-color: var(--bd2); }
.page-btn.active { background: var(--ac3); color: var(--ac); border-color: rgba(31,226,144,.2); }
.page-btn.disabled { opacity: .3; pointer-events: none; }

/* Empty state */
.empty-state { padding: 48px 20px; text-align: center; }
.empty-state-text { font-size: .875rem; color: var(--tx3); }
.empty-state-sub { font-size: .75rem; color: var(--tx3); margin-top: 4px; opacity: .6; }

/* Animations */
.reveal { opacity: 0; transform: translateY(16px); animation: revealUp .5s cubic-bezier(.16,1,.3,1) forwards; }
@keyframes revealUp { to { opacity: 1; transform: none; } }
.d1{animation-delay:.04s} .d2{animation-delay:.08s} .d3{animation-delay:.12s}
.d4{animation-delay:.16s} .d5{animation-delay:.20s}

/* Responsive */
@media (max-width: 1100px) { .sub-stats { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 768px) {
    .main { margin-left: 0; padding: 16px; }
    .sub-stats { grid-template-columns: 1fr 1fr; gap: 8px; }
    .ph { flex-wrap: wrap; }
    .ph-right { width: 100%; }
    .sub-table { font-size: .75rem; }
    .sub-table th, .sub-table td { padding: 8px 10px; }
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
    <div class="topbar-breadcrumb">
        <a href="/revenue/index.php">Revenue</a>
        <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        <span class="topbar-title-text">Subscriptions</span>
    </div>
    <div class="topbar-spacer"></div>
    <a href="/revenue/index.php" class="btn btn-ghost btn-sm">
        <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        Revenue
    </a>
</header>

<!-- Main -->
<main class="main">

    <!-- Page header -->
    <div class="ph reveal d1">
        <div>
            <div class="ph-eyebrow"><span class="ph-eyebrow-dot"></span>Subscription Management</div>
            <h1 class="ph-title">Subscriptions</h1>
            <p class="ph-sub">Manage, filter, and take action on all subscriber accounts.</p>
        </div>
        <div class="ph-right">
            <?php
            $exportUrl = '?' . http_build_query(array_merge($_GET, ['export' => 'csv']));
            ?>
            <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn btn-ghost btn-sm">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Export CSV
            </a>
        </div>
    </div>

    <!-- Stats summary -->
    <div class="sub-stats reveal d2">
        <div class="sub-stat">
            <div class="sub-stat-val green"><?= number_format($totalActive) ?></div>
            <div class="sub-stat-label">Active Subscribers</div>
        </div>
        <div class="sub-stat">
            <div class="sub-stat-val blue">+<?= number_format($newThisMonth) ?></div>
            <div class="sub-stat-label">New This Month</div>
        </div>
        <div class="sub-stat">
            <div class="sub-stat-val red">-<?= number_format($cancelledThisMonth) ?></div>
            <div class="sub-stat-label">Cancelled This Month</div>
        </div>
        <div class="sub-stat">
            <div class="sub-stat-val <?= $netChange >= 0 ? 'green' : 'red' ?>"><?= $netChange >= 0 ? '+' : '' ?><?= number_format($netChange) ?></div>
            <div class="sub-stat-label">Net Change</div>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" class="filters reveal d3">
        <input type="text" name="q" class="filter-search" placeholder="Search by name or email..." value="<?= htmlspecialchars($search) ?>">
        <select name="plan" class="filter-select" onchange="this.form.submit()">
            <option value="all" <?= $planFilter === 'all' ? 'selected' : '' ?>>All Plans</option>
            <option value="free" <?= $planFilter === 'free' ? 'selected' : '' ?>>Free</option>
            <option value="pro" <?= $planFilter === 'pro' ? 'selected' : '' ?>>Pro ($14.99)</option>
            <option value="family" <?= $planFilter === 'family' ? 'selected' : '' ?>>Family ($24.99)</option>
        </select>
        <?php if ($hasSubStatus): ?>
        <select name="status" class="filter-select" onchange="this.form.submit()">
            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            <option value="past_due" <?= $statusFilter === 'past_due' ? 'selected' : '' ?>>Past Due</option>
        </select>
        <?php endif; ?>
        <button type="submit" class="btn btn-ghost btn-sm">
            <svg viewBox="0 0 24 24" style="width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            Search
        </button>
        <?php if ($search || $planFilter !== 'all' || $statusFilter !== 'all' || $atRiskFilter): ?>
        <a href="/revenue/subscriptions.php" class="btn btn-ghost btn-xs" style="color:var(--err)">Clear</a>
        <?php endif; ?>
    </form>

    <!-- Subscribers table -->
    <div class="card reveal d4">
        <?php if (empty($subscribers)): ?>
        <div class="empty-state">
            <div class="empty-state-text">No subscribers found.</div>
            <div class="empty-state-sub">Try adjusting your search or filter criteria.</div>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto">
            <table class="sub-table">
                <thead>
                    <tr>
                        <th>Subscriber</th>
                        <th>Plan</th>
                        <th>Status</th>
                        <th>Started</th>
                        <th>MRR</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subscribers as $sub): ?>
                    <tr>
                        <td>
                            <div class="sub-name"><?= htmlspecialchars($sub['name']) ?></div>
                            <div class="sub-email"><?= htmlspecialchars($sub['email']) ?></div>
                        </td>
                        <td>
                            <span class="plan-pill <?= htmlspecialchars($sub['plan']) ?>">
                                <?= ucfirst($sub['plan']) ?>
                            </span>
                        </td>
                        <td>
                            <?php $st = $sub['sub_status'] ?? 'active'; ?>
                            <span class="status-pill-sm <?= htmlspecialchars($st) ?>">
                                <span class="status-dot-sm"></span>
                                <?= ucfirst(str_replace('_', ' ', $st)) ?>
                            </span>
                        </td>
                        <td>
                            <span class="date-val">
                                <?= $sub['started_at'] ? date('M j, Y', strtotime($sub['started_at'])) : '—' ?>
                            </span>
                        </td>
                        <td>
                            <?php $mrrContrib = $planPrices[$sub['plan']] ?? 0; ?>
                            <span class="mrr-val <?= $mrrContrib == 0 ? 'zero' : '' ?>">
                                <?= $mrrContrib > 0 ? '$' . number_format($mrrContrib, 2) : '$0' ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <a href="/students/view.php?id=<?= (int)$sub['id'] ?>" class="icon-btn edit" title="View Profile">
                                    <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </a>
                                <?php if ($sub['plan'] !== 'family'): ?>
                                <a href="/revenue/subscriptions.php?action=upgrade&id=<?= (int)$sub['id'] ?>" class="icon-btn edit" title="Upgrade">
                                    <svg viewBox="0 0 24 24"><polyline points="18 15 12 9 6 15"/></svg>
                                </a>
                                <?php endif; ?>
                                <?php if ($sub['plan'] !== 'free'): ?>
                                <a href="/revenue/subscriptions.php?action=cancel&id=<?= (int)$sub['id'] ?>" class="icon-btn del" title="Cancel" onclick="return confirm('Cancel subscription for <?= htmlspecialchars(addslashes($sub['name'])) ?>?')">
                                    <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <div class="pagination-info">
                Showing <?= (($page - 1) * $perPage) + 1 ?>-<?= min($page * $perPage, $totalCount) ?> of <?= number_format($totalCount) ?> subscribers
            </div>
            <div class="pagination-pages">
                <?php
                $baseQ = $_GET;
                unset($baseQ['page']);
                $baseUrl = '?' . http_build_query($baseQ);
                ?>
                <a href="<?= $baseUrl ?>&page=<?= max(1, $page - 1) ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                    <svg viewBox="0 0 24 24" style="width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round"><polyline points="15 18 9 12 15 6"/></svg>
                </a>
                <?php
                $start = max(1, $page - 2);
                $end   = min($totalPages, $page + 2);
                for ($p = $start; $p <= $end; $p++):
                ?>
                <a href="<?= $baseUrl ?>&page=<?= $p ?>" class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <a href="<?= $baseUrl ?>&page=<?= min($totalPages, $page + 1) ?>" class="page-btn <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <svg viewBox="0 0 24 24" style="width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

</main>

</body>
</html>
