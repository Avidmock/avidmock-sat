<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/auth-guard.php';

$pageTitle = 'Tutoring Sessions';
$activePage = 'sessions';

// Filters
$statusFilter = $_GET['status'] ?? '';
$subjectFilter = $_GET['subject'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$validStatuses = ['upcoming', 'in-progress', 'completed', 'cancelled'];
$validSubjects = ['math', 'reading_writing', 'general'];

// Build query conditions
$where = [];
$params = [];

if ($statusFilter && in_array($statusFilter, $validStatuses, true)) {
    $where[] = 'ts.status = ?';
    $params[] = $statusFilter;
}

if ($subjectFilter && in_array($subjectFilter, $validSubjects, true)) {
    $where[] = 'ts.subject = ?';
    $params[] = $subjectFilter;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count total for pagination
$countResult = Database::fetch(
    "SELECT COUNT(*) AS total FROM tutoring_sessions ts {$whereClause}",
    $params
);
$totalSessions = (int)($countResult['total'] ?? 0);
$totalPages = max(1, (int)ceil($totalSessions / $perPage));

// Fetch sessions with enrolled count
$sessions = Database::fetchAll(
    "SELECT ts.*,
        (SELECT COUNT(*) FROM session_enrollments se WHERE se.session_id = ts.id) AS enrolled_count
     FROM tutoring_sessions ts
     {$whereClause}
     ORDER BY ts.starts_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);

function statusBadgeClass(string $status): string
{
    return match ($status) {
        'upcoming'    => 'pill--accent',
        'in-progress' => 'pill--warning',
        'completed'   => 'pill--muted',
        'cancelled'   => 'pill--danger',
        default       => 'pill--muted',
    };
}

function subjectLabel(string $subject): string
{
    return match ($subject) {
        'math'            => 'Math',
        'reading_writing' => 'Reading & Writing',
        'general'         => 'General',
        default           => ucfirst($subject),
    };
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/head.php';
?>
<div class="admin-layout">
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/sidebar.php'; ?>
<main class="admin-main">
    <div class="topbar">
        <h1>Tutoring Sessions</h1>
        <a href="/sessions/create.php" class="btn btn--primary">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            Create Session
        </a>
    </div>

    <div class="content">
        <!-- Filters -->
        <form method="GET" action="/sessions/" class="filters-bar">
            <div class="filter-group">
                <label for="filter-status">Status</label>
                <select name="status" id="filter-status" class="input input--sm">
                    <option value="">All Statuses</option>
                    <?php foreach ($validStatuses as $s): ?>
                        <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>>
                            <?= ucfirst(str_replace('-', ' ', $s)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="filter-subject">Subject</label>
                <select name="subject" id="filter-subject" class="input input--sm">
                    <option value="">All Subjects</option>
                    <?php foreach ($validSubjects as $sub): ?>
                        <option value="<?= $sub ?>" <?= $subjectFilter === $sub ? 'selected' : '' ?>>
                            <?= subjectLabel($sub) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn--secondary btn--sm">Apply</button>
            <?php if ($statusFilter || $subjectFilter): ?>
                <a href="/sessions/" class="btn btn--ghost btn--sm">Clear Filters</a>
            <?php endif; ?>
        </form>

        <!-- Count -->
        <div class="table-meta">
            <span class="table-meta__count">
                <?= number_format($totalSessions) ?> session<?= $totalSessions !== 1 ? 's' : '' ?>
            </span>
        </div>

        <!-- Table -->
        <div class="table-wrap">
            <table class="data-table" data-sortable>
                <thead>
                    <tr>
                        <th data-sort="title">Title</th>
                        <th data-sort="subject">Subject</th>
                        <th data-sort="tutor">Tutor</th>
                        <th data-sort="date">Date &amp; Time</th>
                        <th>Duration</th>
                        <th>Capacity</th>
                        <th data-sort="status">Status</th>
                        <th class="cell--actions-header">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($sessions)): ?>
                    <tr>
                        <td colspan="8" class="table-empty">
                            <div class="table-empty__inner">
                                <p>No sessions found.</p>
                                <a href="/sessions/create.php" class="btn btn--primary btn--sm">Create your first session</a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($sessions as $s): ?>
                    <tr>
                        <td class="cell--title">
                            <a href="/sessions/edit.php?id=<?= (int)$s['id'] ?>">
                                <?= htmlspecialchars($s['title']) ?>
                            </a>
                        </td>
                        <td><span class="pill pill--subtle"><?= subjectLabel($s['subject']) ?></span></td>
                        <td><?= htmlspecialchars($s['tutor_name']) ?></td>
                        <td class="cell--nowrap">
                            <?= date('M j, Y', strtotime($s['starts_at'])) ?><br>
                            <small class="text-muted"><?= date('g:i A', strtotime($s['starts_at'])) ?></small>
                        </td>
                        <td><?= (int)$s['duration_minutes'] ?> min</td>
                        <td>
                            <span class="capacity-badge <?= (int)$s['enrolled_count'] >= (int)$s['max_capacity'] ? 'capacity-badge--full' : '' ?>">
                                <?= (int)$s['enrolled_count'] ?> / <?= (int)$s['max_capacity'] ?>
                            </span>
                        </td>
                        <td>
                            <span class="pill <?= statusBadgeClass($s['status']) ?>">
                                <?= ucfirst(str_replace('-', ' ', $s['status'])) ?>
                            </span>
                        </td>
                        <td class="cell--actions">
                            <div class="action-group">
                                <a href="/sessions/edit.php?id=<?= (int)$s['id'] ?>"
                                   class="btn btn--ghost btn--xs" title="Edit session">Edit</a>
                                <a href="/sessions/attendees.php?id=<?= (int)$s['id'] ?>"
                                   class="btn btn--ghost btn--xs" title="View attendees">Attendees</a>
                                <?php if (!in_array($s['status'], ['cancelled', 'completed'], true)): ?>
                                    <a href="/sessions/cancel.php?id=<?= (int)$s['id'] ?>"
                                       class="btn btn--ghost btn--xs btn--danger" title="Cancel session">Cancel</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <?php
            $qp = [];
            if ($statusFilter)  $qp['status']  = $statusFilter;
            if ($subjectFilter) $qp['subject'] = $subjectFilter;
        ?>
        <nav class="pagination" aria-label="Session pagination">
            <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($qp, ['page' => $page - 1])) ?>"
                   class="pagination__link" aria-label="Previous page">Previous</a>
            <?php else: ?>
                <span class="pagination__link pagination__link--disabled" aria-disabled="true">Previous</span>
            <?php endif; ?>

            <?php
            $start = max(1, $page - 2);
            $end   = min($totalPages, $page + 2);
            ?>

            <?php if ($start > 1): ?>
                <a href="?<?= http_build_query(array_merge($qp, ['page' => 1])) ?>"
                   class="pagination__link">1</a>
                <?php if ($start > 2): ?>
                    <span class="pagination__ellipsis">&hellip;</span>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $start; $i <= $end; $i++): ?>
                <a href="?<?= http_build_query(array_merge($qp, ['page' => $i])) ?>"
                   class="pagination__link <?= $i === $page ? 'pagination__link--active' : '' ?>"
                   <?= $i === $page ? 'aria-current="page"' : '' ?>>
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($end < $totalPages): ?>
                <?php if ($end < $totalPages - 1): ?>
                    <span class="pagination__ellipsis">&hellip;</span>
                <?php endif; ?>
                <a href="?<?= http_build_query(array_merge($qp, ['page' => $totalPages])) ?>"
                   class="pagination__link"><?= $totalPages ?></a>
            <?php endif; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?<?= http_build_query(array_merge($qp, ['page' => $page + 1])) ?>"
                   class="pagination__link" aria-label="Next page">Next</a>
            <?php else: ?>
                <span class="pagination__link pagination__link--disabled" aria-disabled="true">Next</span>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    </div>
</main>
</div>

<script src="/assets/js/admin-core.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    AdminCore.initSortableTable(document.querySelector('[data-sortable]'));
});
</script>
</body>
</html>
