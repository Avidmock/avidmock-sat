<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/auth-guard.php';

$pageTitle = 'Cancel Session';
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

if (in_array($session['status'], ['cancelled', 'completed'], true)) {
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'This session cannot be cancelled.'];
    header('Location: /sessions/');
    exit;
}

$enrolledResult = Database::fetch(
    "SELECT COUNT(*) AS cnt FROM session_enrollments WHERE session_id = ?",
    [$id]
);
$enrolledCount = (int)($enrolledResult['cnt'] ?? 0);

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Invalid security token.'];
        header('Location: /sessions/cancel.php?id=' . $id);
        exit;
    }

    Database::update('tutoring_sessions', [
        'status'       => 'cancelled',
        'cancelled_at' => date('Y-m-d H:i:s'),
        'cancelled_by' => $_SESSION['admin_id'],
    ], ['id' => $id]);

    $_SESSION['toast'] = ['type' => 'success', 'message' => 'Session has been cancelled.'];
    header('Location: /sessions/');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$startsAt = new DateTime($session['starts_at']);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/head.php';
?>
<div class="admin-layout">
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/sidebar.php'; ?>
<main class="admin-main">
    <div class="topbar">
        <h1>Cancel Session</h1>
        <a href="/sessions/" class="btn btn--ghost">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Back to Sessions
        </a>
    </div>

    <div class="content">
        <div class="confirm-card confirm-card--danger">
            <div class="confirm-card__icon">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none" aria-hidden="true">
                    <circle cx="20" cy="20" r="18" stroke="currentColor" stroke-width="2"/>
                    <path d="M20 12v10M20 26v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </div>
            <h2 class="confirm-card__title">Are you sure you want to cancel this session?</h2>
            <p class="confirm-card__desc">This action cannot be undone. All enrolled students will be notified.</p>

            <div class="confirm-card__details">
                <dl class="detail-grid">
                    <dt>Title</dt>
                    <dd><?= htmlspecialchars($session['title']) ?></dd>

                    <dt>Tutor</dt>
                    <dd><?= htmlspecialchars($session['tutor_name']) ?></dd>

                    <dt>Date & Time</dt>
                    <dd><?= $startsAt->format('M j, Y \a\t g:i A') ?></dd>

                    <dt>Duration</dt>
                    <dd><?= (int)$session['duration_minutes'] ?> minutes</dd>

                    <dt>Enrolled</dt>
                    <dd>
                        <?= $enrolledCount ?> student<?= $enrolledCount !== 1 ? 's' : '' ?>
                        <?php if ($enrolledCount > 0): ?>
                            <span class="text-warning">(will be notified)</span>
                        <?php endif; ?>
                    </dd>
                </dl>
            </div>

            <form method="POST" action="/sessions/cancel.php?id=<?= $id ?>" class="confirm-card__actions">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <a href="/sessions/" class="btn btn--ghost">Keep Session</a>
                <button type="submit" class="btn btn--danger">Cancel Session</button>
            </form>
        </div>
    </div>
</main>
</div>

<script src="/assets/js/admin-core.js"></script>
</body>
</html>
