<?php
/**
 * sessions/cancel.php
 *
 * Cancel a tutoring session.
 * - GET  ?id=X  → confirmation page with session details
 * - POST        → sets status='cancelled', redirects to index
 */

require_once __DIR__ . '/../auth/auth-guard.php';

// ── Handle POST — perform the cancellation ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sessionId = (int) ($_POST['session_id'] ?? 0);
    if ($sessionId <= 0) {
        $_SESSION['toast']      = 'Invalid session.';
        $_SESSION['toast_type'] = 'error';
        header('Location: /sessions/index.php');
        exit;
    }

    // Verify session exists and is not already cancelled
    $session = Database::fetch(
        "SELECT id, status FROM tutoring_sessions WHERE id = ?",
        [$sessionId]
    );

    if (!$session) {
        $_SESSION['toast']      = 'Session not found.';
        $_SESSION['toast_type'] = 'error';
        header('Location: /sessions/index.php');
        exit;
    }

    if ($session['status'] === 'cancelled') {
        $_SESSION['toast']      = 'Session is already cancelled.';
        $_SESSION['toast_type'] = 'error';
        header('Location: /sessions/index.php');
        exit;
    }

    Database::execute(
        "UPDATE tutoring_sessions SET status = 'cancelled', updated_at = NOW() WHERE id = ?",
        [$sessionId]
    );

    $_SESSION['toast']      = 'Session cancelled successfully.';
    $_SESSION['toast_type'] = 'success';
    header('Location: /sessions/index.php');
    exit;
}

// ── Handle GET — show confirmation page ─────────────────────────────────────
$sessionId = (int) ($_GET['id'] ?? 0);
if ($sessionId <= 0) {
    header('Location: /sessions/index.php');
    exit;
}

$session = Database::fetch(
    "SELECT ts.*,
            (SELECT COUNT(*) FROM session_enrollments WHERE session_id = ts.id) AS enrolled_count
     FROM tutoring_sessions ts
     WHERE ts.id = ?",
    [$sessionId]
);

if (!$session) {
    $_SESSION['toast']      = 'Session not found.';
    $_SESSION['toast_type'] = 'error';
    header('Location: /sessions/index.php');
    exit;
}

$admin      = currentAdmin();
$activePage = 'sessions';
$pageTitle  = 'Cancel Session — Avidmock Admin';

$extraHead = <<<'CSS'
<style>
.cancel-wrap {
    margin-left: var(--sb-w); margin-top: var(--top-h); padding: 28px;
    display: flex; justify-content: center;
}
.cancel-card {
    max-width: 520px; width: 100%;
    background: var(--sf); border: 1px solid var(--bd); border-radius: 14px;
    padding: 32px; animation: rev .4s cubic-bezier(.16,1,.3,1) forwards;
    opacity: 0; transform: translateY(12px);
}
.cancel-icon {
    width: 52px; height: 52px; border-radius: 14px;
    background: var(--err2); color: var(--err);
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 20px;
}
.cancel-icon svg { width: 24px; height: 24px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.cancel-h { font-family: var(--fh); font-size: 1.25rem; font-weight: 900; color: var(--tx); letter-spacing: -.025em; margin-bottom: 8px; }
.cancel-desc { font-size: .8125rem; color: var(--tx2); line-height: 1.6; margin-bottom: 24px; }
.cancel-details {
    background: var(--sf); border: 1px solid var(--bd); border-radius: 10px;
    padding: 16px; margin-bottom: 24px;
}
.cancel-detail-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 6px 0; font-size: .8125rem;
}
.cancel-detail-row + .cancel-detail-row { border-top: 1px solid var(--bd); }
.cancel-detail-label { color: var(--tx3); font-weight: 500; }
.cancel-detail-value { color: var(--tx); font-weight: 600; text-align: right; }
.cancel-warning {
    display: flex; align-items: flex-start; gap: 10px;
    background: var(--warn2); border: 1px solid rgba(245,158,11,.2); border-radius: 10px;
    padding: 12px 14px; margin-bottom: 24px; font-size: .75rem; color: var(--warn); line-height: 1.5;
}
.cancel-warning svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; flex-shrink: 0; margin-top: 1px; }
.cancel-actions { display: flex; gap: 10px; }
.cancel-actions .btn { flex: 1; justify-content: center; }
.status-tag {
    display: inline-block; padding: 3px 9px; border-radius: 50px;
    font-size: .5625rem; font-weight: 800; text-transform: uppercase; letter-spacing: .5px;
}
.status-tag.scheduled { background: var(--ac3); color: var(--ac); }
.status-tag.completed { background: var(--blue2); color: var(--blue); }
.status-tag.cancelled { background: var(--err2); color: var(--err); }
.status-tag.draft     { background: var(--warn2); color: var(--warn); }

@media (max-width: 768px) {
    .cancel-wrap { padding: 20px 16px; }
}
</style>
CSS;

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';

$sessionDate = !empty($session['session_date'])
    ? date('M j, Y \a\t g:i A', strtotime($session['session_date']))
    : 'Not set';

$statusClass = strtolower($session['status'] ?? 'scheduled');
?>

<!-- Topbar -->
<header class="topbar">
    <button class="topbar-ham" onclick="openSidebar()">
        <span></span><span></span><span></span>
    </button>
    <div class="topbar-breadcrumb">
        <a href="/sessions/index.php">Sessions</a>
        <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        <span class="topbar-title-text">Cancel Session</span>
    </div>
</header>

<!-- Content -->
<main class="cancel-wrap">
    <div class="cancel-card">
        <div class="cancel-icon">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        </div>

        <h1 class="cancel-h">Cancel this session?</h1>
        <p class="cancel-desc">
            You are about to cancel <strong><?= htmlspecialchars($session['title']) ?></strong>.
            This action cannot be undone.
        </p>

        <div class="cancel-details">
            <div class="cancel-detail-row">
                <span class="cancel-detail-label">Title</span>
                <span class="cancel-detail-value"><?= htmlspecialchars($session['title']) ?></span>
            </div>
            <div class="cancel-detail-row">
                <span class="cancel-detail-label">Date</span>
                <span class="cancel-detail-value"><?= htmlspecialchars($sessionDate) ?></span>
            </div>
            <div class="cancel-detail-row">
                <span class="cancel-detail-label">Type</span>
                <span class="cancel-detail-value"><?= htmlspecialchars(ucfirst($session['session_type'] ?? 'N/A')) ?></span>
            </div>
            <div class="cancel-detail-row">
                <span class="cancel-detail-label">Duration</span>
                <span class="cancel-detail-value"><?= (int)($session['duration_minutes'] ?? 0) ?> min</span>
            </div>
            <div class="cancel-detail-row">
                <span class="cancel-detail-label">Enrolled</span>
                <span class="cancel-detail-value"><?= (int)$session['enrolled_count'] ?> / <?= (int)($session['max_students'] ?? 0) ?></span>
            </div>
            <div class="cancel-detail-row">
                <span class="cancel-detail-label">Status</span>
                <span class="cancel-detail-value"><span class="status-tag <?= $statusClass ?>"><?= htmlspecialchars(ucfirst($session['status'] ?? 'scheduled')) ?></span></span>
            </div>
        </div>

        <?php if ((int)$session['enrolled_count'] > 0): ?>
        <div class="cancel-warning">
            <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <span>
                This session has <strong><?= (int)$session['enrolled_count'] ?> enrolled student<?= (int)$session['enrolled_count'] !== 1 ? 's' : '' ?></strong>.
                Cancelling will affect all enrolled students.
            </span>
        </div>
        <?php endif; ?>

        <?php if ($session['status'] === 'cancelled'): ?>
            <div class="cancel-warning">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span>This session is already cancelled.</span>
            </div>
            <div class="cancel-actions">
                <a href="/sessions/index.php" class="btn btn-ghost">Back to Sessions</a>
            </div>
        <?php else: ?>
            <form method="POST" action="/sessions/cancel.php">
                <input type="hidden" name="session_id" value="<?= (int)$session['id'] ?>">
                <div class="cancel-actions">
                    <a href="/sessions/index.php" class="btn btn-ghost">Go Back</a>
                    <button type="submit" class="btn btn-danger">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        Confirm Cancel
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</main>

</body>
</html>
