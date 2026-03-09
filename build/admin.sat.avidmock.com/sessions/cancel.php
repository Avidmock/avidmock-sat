<?php
/**
 * sessions/cancel.php
 *
 * Cancel a tutoring session.
 * - GET  ?id=X  → confirmation page with session details
 * - POST        → sets status='cancelled', redirects to index with success message
 */

require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

// ── Handle POST — perform the cancellation ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sessionId = (int) ($_POST['session_id'] ?? 0);
    if ($sessionId <= 0) {
        $_SESSION['toast']      = 'Invalid session.';
        $_SESSION['toast_type'] = 'error';
        header('Location: /sessions/index.php');
        exit;
    }

    // CSRF check
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $_SESSION['toast']      = 'Invalid request token. Please try again.';
        $_SESSION['toast_type'] = 'error';
        header('Location: /sessions/cancel.php?id=' . $sessionId);
        exit;
    }

    // Verify session exists and is not already cancelled
    $session = Database::fetch(
        "SELECT id, title, status FROM tutoring_sessions WHERE id = ?",
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

    $_SESSION['toast']      = 'Session "' . $session['title'] . '" cancelled successfully.';
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

// ── CSRF token ───────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$admin      = currentAdmin();
$activePage = 'sessions';
$pageTitle  = 'Cancel Session — Avidmock Admin';

$extraHead = <<<'CSS'
<style>
/* ── Layout ──────────────────────────────────────────────────────────── */
.cancel-wrap {
    margin-left: var(--sb-w); margin-top: var(--top-h); padding: 28px;
    display: flex; justify-content: center; align-items: flex-start;
    min-height: calc(100vh - var(--top-h));
}
.cancel-card {
    max-width: 520px; width: 100%;
    background: var(--sf); border: 1px solid var(--bd); border-radius: 14px;
    padding: 32px; margin-top: 40px;
    animation: rev .4s cubic-bezier(.16,1,.3,1) forwards;
    opacity: 0; transform: translateY(12px);
}
@keyframes rev { to { opacity: 1; transform: none; } }

/* ── Icon ─────────────────────────────────────────────────────────────── */
.cancel-icon {
    width: 52px; height: 52px; border-radius: 14px;
    background: rgba(239,68,68,.1); color: var(--err);
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 20px;
}
.cancel-icon svg {
    width: 24px; height: 24px; stroke: currentColor; fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
}

/* ── Text ─────────────────────────────────────────────────────────────── */
.cancel-h {
    font-family: var(--fh); font-size: 1.25rem; font-weight: 900;
    color: var(--tx); letter-spacing: -.025em; margin-bottom: 8px;
}
.cancel-desc {
    font-size: .8125rem; color: var(--tx2); line-height: 1.6; margin-bottom: 24px;
}
.cancel-desc strong { color: var(--tx); }

/* ── Details card ─────────────────────────────────────────────────────── */
.cancel-details {
    background: rgba(255,255,255,.025); border: 1px solid var(--bd);
    border-radius: 10px; padding: 16px; margin-bottom: 24px;
}
.cancel-detail-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 6px 0; font-size: .8125rem;
}
.cancel-detail-row + .cancel-detail-row { border-top: 1px solid var(--bd); }
.cancel-detail-label { color: var(--tx3); font-weight: 500; }
.cancel-detail-value { color: var(--tx); font-weight: 600; text-align: right; }

/* ── Status tags ──────────────────────────────────────────────────────── */
.status-tag {
    display: inline-block; padding: 3px 9px; border-radius: 50px;
    font-size: .5625rem; font-weight: 800; text-transform: uppercase; letter-spacing: .5px;
}
.status-tag.scheduled { background: rgba(31,226,144,.08); color: var(--ac); }
.status-tag.active    { background: rgba(31,226,144,.08); color: var(--ac); }
.status-tag.completed { background: rgba(59,130,246,.1); color: #60a5fa; }
.status-tag.cancelled { background: rgba(239,68,68,.1); color: var(--err); }
.status-tag.draft     { background: rgba(245,158,11,.1); color: var(--warn); }

/* ── Warning box ──────────────────────────────────────────────────────── */
.cancel-warning {
    display: flex; align-items: flex-start; gap: 10px;
    background: rgba(245,158,11,.08); border: 1px solid rgba(245,158,11,.2);
    border-radius: 10px; padding: 12px 14px; margin-bottom: 24px;
    font-size: .75rem; color: var(--warn); line-height: 1.5;
}
.cancel-warning svg {
    width: 16px; height: 16px; stroke: currentColor; fill: none;
    stroke-width: 2; stroke-linecap: round; flex-shrink: 0; margin-top: 1px;
}
.cancel-warning strong { color: var(--warn); }

/* ── Info warning (already cancelled) ─────────────────────────────────── */
.cancel-info {
    display: flex; align-items: flex-start; gap: 10px;
    background: rgba(255,255,255,.03); border: 1px solid var(--bd);
    border-radius: 10px; padding: 12px 14px; margin-bottom: 24px;
    font-size: .75rem; color: var(--tx3); line-height: 1.5;
}
.cancel-info svg {
    width: 16px; height: 16px; stroke: currentColor; fill: none;
    stroke-width: 2; stroke-linecap: round; flex-shrink: 0; margin-top: 1px;
}

/* ── Buttons ──────────────────────────────────────────────────────────── */
.cancel-actions { display: flex; gap: 10px; }
.cancel-actions .btn { flex: 1; justify-content: center; }

.btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 10px 20px; border-radius: 9px;
    font-family: var(--ff); font-size: .8125rem; font-weight: 700;
    text-decoration: none; border: 1.5px solid transparent;
    cursor: pointer; transition: all .18s;
}
.btn svg {
    width: 13px; height: 13px; stroke: currentColor; fill: none;
    stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round;
}
.btn-ghost {
    background: var(--sf); border-color: var(--bd); color: var(--tx2);
}
.btn-ghost:hover {
    background: rgba(255,255,255,.07); color: var(--tx); border-color: rgba(255,255,255,.13);
}
.btn-danger {
    background: rgba(239,68,68,.12); color: var(--err); border-color: rgba(239,68,68,.2);
}
.btn-danger:hover {
    background: rgba(239,68,68,.2);
}
.btn-sm { padding: 5px 11px; font-size: .75rem; border-radius: 7px; }

/* ── Topbar ───────────────────────────────────────────────────────────── */
.topbar-breadcrumb {
    display: flex; align-items: center; gap: 6px;
    font-family: var(--fh); font-size: 1rem; font-weight: 900;
}
.topbar-breadcrumb a {
    color: var(--tx3); text-decoration: none; font-style: italic; font-weight: 300;
    transition: color .16s;
}
.topbar-breadcrumb a:hover { color: var(--ac); }
.topbar-breadcrumb svg {
    width: 12px; height: 12px; stroke: var(--tx3); fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
}
.topbar-title-text { color: var(--tx); }

/* ── Responsive ───────────────────────────────────────────────────────── */
@media (max-width: 768px) {
    .cancel-wrap { margin-left: 0; padding: 20px 16px; }
    .cancel-card { margin-top: 16px; padding: 24px 20px; }
    .cancel-actions { flex-direction: column; }
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
    <button class="topbar-ham" onclick="openSidebar()" aria-label="Menu">
        <span></span><span></span><span></span>
    </button>
    <div class="topbar-breadcrumb">
        <a href="/sessions/index.php">Sessions</a>
        <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        <span class="topbar-title-text">Cancel Session</span>
    </div>
    <div style="flex:1"></div>
    <a href="/sessions/index.php" class="btn btn-ghost btn-sm">
        <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
        Back
    </a>
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
            This will mark the session as cancelled and it will no longer appear as available to students.
        </p>

        <!-- Session details -->
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
                <span class="cancel-detail-value">
                    <span class="status-tag <?= $statusClass ?>"><?= htmlspecialchars(ucfirst($session['status'] ?? 'scheduled')) ?></span>
                </span>
            </div>
            <?php if (!empty($session['meeting_url'])): ?>
            <div class="cancel-detail-row">
                <span class="cancel-detail-label">Meeting URL</span>
                <span class="cancel-detail-value" style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($session['meeting_url']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Warning if students are enrolled -->
        <?php if ((int)$session['enrolled_count'] > 0): ?>
        <div class="cancel-warning">
            <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <span>
                This session has <strong><?= (int)$session['enrolled_count'] ?> enrolled student<?= (int)$session['enrolled_count'] !== 1 ? 's' : '' ?></strong>.
                Cancelling will affect all enrolled participants.
            </span>
        </div>
        <?php endif; ?>

        <?php if ($session['status'] === 'cancelled'): ?>
            <!-- Already cancelled -->
            <div class="cancel-info">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span>This session is already cancelled. No further action is needed.</span>
            </div>
            <div class="cancel-actions">
                <a href="/sessions/index.php" class="btn btn-ghost">
                    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                    Back to Sessions
                </a>
            </div>
        <?php else: ?>
            <!-- Confirm cancel form -->
            <form method="POST" action="/sessions/cancel.php">
                <input type="hidden" name="session_id" value="<?= (int)$session['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <div class="cancel-actions">
                    <a href="/sessions/index.php" class="btn btn-ghost">
                        <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                        Go Back
                    </a>
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
