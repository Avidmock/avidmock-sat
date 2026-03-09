<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/auth-guard.php';

$pageTitle = 'Session Attendees';
$activePage = 'sessions';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Invalid session ID.'];
    header('Location: /sessions/');
    exit;
}

$session = Database::fetch(
    "SELECT * FROM tutoring_sessions WHERE id = ?",
    [$id]
);

if (!$session) {
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Session not found.'];
    header('Location: /sessions/');
    exit;
}

// Handle attendance update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Invalid security token.'];
        header('Location: /sessions/attendees.php?id=' . $id);
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_attendance') {
        $attendedIds = array_map('intval', $_POST['attended'] ?? []);

        // Reset all to not attended, then mark selected
        Database::update('session_enrollments', ['attended' => 0], ['session_id' => $id]);

        if (!empty($attendedIds)) {
            foreach ($attendedIds as $enrollmentId) {
                Database::update('session_enrollments', ['attended' => 1], [
                    'id'         => $enrollmentId,
                    'session_id' => $id,
                ]);
            }
        }

        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Attendance updated.'];
        header('Location: /sessions/attendees.php?id=' . $id);
        exit;
    }
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $attendees = Database::fetchAll(
        "SELECT u.name, u.email, se.enrolled_at, se.attended
         FROM session_enrollments se
         JOIN users u ON u.id = se.user_id
         WHERE se.session_id = ?
         ORDER BY u.name ASC",
        [$id]
    );

    $filename = 'attendees-session-' . $id . '-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Name', 'Email', 'Enrolled Date', 'Attended']);
    foreach ($attendees as $a) {
        fputcsv($out, [
            $a['name'],
            $a['email'],
            date('M j, Y g:i A', strtotime($a['enrolled_at'])),
            $a['attended'] ? 'Yes' : 'No',
        ]);
    }
    fclose($out);
    exit;
}

// Fetch attendees
$attendees = Database::fetchAll(
    "SELECT u.name, u.email, se.id AS enrollment_id, se.enrolled_at, se.attended
     FROM session_enrollments se
     JOIN users u ON u.id = se.user_id
     WHERE se.session_id = ?
     ORDER BY u.name ASC",
    [$id]
);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$startsAt = new DateTime($session['starts_at']);
$attendedCount = count(array_filter($attendees, fn($a) => (int)$a['attended'] === 1));

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/head.php';
?>
<div class="admin-layout">
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/sidebar.php'; ?>
<main class="admin-main">
    <div class="topbar">
        <h1>Attendees</h1>
        <div class="topbar__actions">
            <a href="/sessions/edit.php?id=<?= $id ?>" class="btn btn--ghost">Edit Session</a>
            <a href="/sessions/" class="btn btn--ghost">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Back
            </a>
        </div>
    </div>

    <div class="content">
        <!-- Session info -->
        <div class="info-bar">
            <div class="info-bar__item">
                <span class="info-bar__label">Session</span>
                <strong><?= htmlspecialchars($session['title']) ?></strong>
            </div>
            <div class="info-bar__item">
                <span class="info-bar__label">Date</span>
                <span><?= $startsAt->format('M j, Y \a\t g:i A') ?></span>
            </div>
            <div class="info-bar__item">
                <span class="info-bar__label">Enrolled</span>
                <strong><?= count($attendees) ?> / <?= (int)$session['max_capacity'] ?></strong>
            </div>
            <div class="info-bar__item">
                <span class="info-bar__label">Attended</span>
                <strong><?= $attendedCount ?> / <?= count($attendees) ?></strong>
            </div>
        </div>

        <?php if (empty($attendees)): ?>
            <div class="empty-state">
                <div class="empty-state__icon">
                    <svg width="48" height="48" viewBox="0 0 48 48" fill="none" aria-hidden="true">
                        <circle cx="24" cy="16" r="8" stroke="currentColor" stroke-width="2"/>
                        <path d="M8 40c0-8.8 7.2-16 16-16s16 7.2 16 16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>
                <h3>No students enrolled yet</h3>
                <p>Students will appear here once they enroll in this session.</p>
            </div>
        <?php else: ?>
            <!-- Actions bar -->
            <div class="table-actions">
                <a href="/sessions/attendees.php?id=<?= $id ?>&export=csv" class="btn btn--secondary btn--sm">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                        <path d="M7 2v8M4 7l3 3 3-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M2 11h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    Export CSV
                </a>
                <span class="text-muted text-sm"><?= count($attendees) ?> student<?= count($attendees) !== 1 ? 's' : '' ?></span>
            </div>

            <form method="POST" action="/sessions/attendees.php?id=<?= $id ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="update_attendance">

                <div class="table-wrap">
                    <table class="data-table" data-sortable>
                        <thead>
                            <tr>
                                <th class="cell--check">
                                    <label class="check-label" title="Toggle all">
                                        <input type="checkbox" id="toggleAll" class="checkbox">
                                        <span class="sr-only">Select all</span>
                                    </label>
                                </th>
                                <th data-sort="name">Student Name</th>
                                <th data-sort="email">Email</th>
                                <th data-sort="enrolled">Enrolled Date</th>
                                <th>Attended</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendees as $a): ?>
                                <tr>
                                    <td class="cell--check">
                                        <input type="checkbox" class="checkbox row-check"
                                               name="attended[]"
                                               value="<?= (int)$a['enrollment_id'] ?>"
                                               <?= (int)$a['attended'] ? 'checked' : '' ?>>
                                    </td>
                                    <td class="cell--title"><?= htmlspecialchars($a['name']) ?></td>
                                    <td>
                                        <a href="mailto:<?= htmlspecialchars($a['email']) ?>" class="link-subtle">
                                            <?= htmlspecialchars($a['email']) ?>
                                        </a>
                                    </td>
                                    <td class="cell--nowrap">
                                        <?= date('M j, Y', strtotime($a['enrolled_at'])) ?><br>
                                        <small class="text-muted"><?= date('g:i A', strtotime($a['enrolled_at'])) ?></small>
                                    </td>
                                    <td>
                                        <span class="pill <?= (int)$a['attended'] ? 'pill--accent' : 'pill--muted' ?>">
                                            <?= (int)$a['attended'] ? 'Present' : 'Absent' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn--primary">Save Attendance</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</main>
</div>

<script src="/assets/js/admin-core.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var toggleAll = document.getElementById('toggleAll');
    if (toggleAll) {
        toggleAll.addEventListener('change', function () {
            var boxes = document.querySelectorAll('.row-check');
            for (var i = 0; i < boxes.length; i++) {
                boxes[i].checked = toggleAll.checked;
            }
        });
    }

    AdminCore.initSortableTable(document.querySelector('[data-sortable]'));
});
</script>
</body>
</html>
