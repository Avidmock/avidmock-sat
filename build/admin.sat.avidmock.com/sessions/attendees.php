<?php
/**
 * sessions/attendees.php
 *
 * Full page showing attendees for a specific tutoring session.
 * - GET  ?id=X             → attendee list with session info card
 * - POST action=toggle_attendance  → AJAX toggle attended status
 * - GET  ?id=X&export=csv  → export attendees as CSV
 */

require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

$admin = currentAdmin();
$db    = Database::connect();

$sessionId = (int) ($_GET['id'] ?? $_POST['session_id'] ?? 0);

if ($sessionId <= 0) {
    header('Location: /sessions/index.php');
    exit;
}

// ── Load session ─────────────────────────────────────────────────────────────
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

// ── Schema detection for user name ───────────────────────────────────────────
$userCols = array_column(
    $db->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_ASSOC),
    'Field'
);
$nameExpr = in_array('first_name', $userCols)
    ? "CONCAT(u.first_name,' ',u.last_name)"
    : "u.name";

// ── Handle AJAX: toggle attendance ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_attendance') {
    header('Content-Type: application/json');

    $enrollmentId = (int) ($_POST['enrollment_id'] ?? 0);
    $attended     = (int) ($_POST['attended'] ?? 0);

    if ($enrollmentId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid enrollment.']);
        exit;
    }

    try {
        Database::execute(
            "UPDATE session_enrollments SET attended = ? WHERE id = ? AND session_id = ?",
            [$attended, $enrollmentId, $sessionId]
        );
        echo json_encode(['success' => true, 'attended' => $attended]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => 'Database error.']);
    }
    exit;
}

// ── Fetch enrolled students ──────────────────────────────────────────────────
$enrollees = Database::fetchAll(
    "SELECT se.id AS enrollment_id,
            se.user_id,
            se.attended,
            se.enrolled_at,
            {$nameExpr} AS student_name,
            u.email
     FROM session_enrollments se
     JOIN users u ON u.id = se.user_id
     WHERE se.session_id = ?
     ORDER BY se.enrolled_at ASC",
    [$sessionId]
);

// ── CSV Export ───────────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'attendees-session-' . $sessionId . '-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    // BOM for Excel UTF-8
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, ['Name', 'Email', 'Enrolled Date', 'Attended']);

    foreach ($enrollees as $e) {
        fputcsv($out, [
            $e['student_name'] ?? 'Unknown',
            $e['email'] ?? '',
            $e['enrolled_at'] ? date('Y-m-d H:i', strtotime($e['enrolled_at'])) : '',
            $e['attended'] ? 'Yes' : 'No',
        ]);
    }

    fclose($out);
    exit;
}

// ── Attendance stats ─────────────────────────────────────────────────────────
$totalEnrolled = count($enrollees);
$totalAttended = 0;
foreach ($enrollees as $e) {
    if ($e['attended']) $totalAttended++;
}

// ── Head setup ───────────────────────────────────────────────────────────────
$activePage = 'sessions';
$pageTitle  = 'Attendees: ' . $session['title'] . ' — Avidmock Admin';

$extraHead = <<<'CSS'
<style>
/* ── Layout ──────────────────────────────────────────────────────────── */
.main {
    margin-left: var(--sb-w);
    margin-top: var(--top-h);
    padding: 28px;
    min-height: calc(100vh - var(--top-h));
}

/* ── Breadcrumb ───────────────────────────────────────────────────────── */
.topbar-breadcrumb {
    display: flex; align-items: center; gap: 6px;
    font-family: var(--fh); font-size: 1rem; font-weight: 900;
    overflow: hidden;
}
.topbar-breadcrumb a {
    color: var(--tx3); text-decoration: none; font-style: italic; font-weight: 300;
    transition: color .16s; white-space: nowrap;
}
.topbar-breadcrumb a:hover { color: var(--ac); }
.topbar-breadcrumb svg {
    width: 12px; height: 12px; stroke: var(--tx3); fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0;
}
.topbar-title-text {
    color: var(--tx); white-space: nowrap;
}
.topbar-session-title {
    color: var(--tx2); font-weight: 300; font-style: italic;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px;
}

/* ── Page header ──────────────────────────────────────────────────────── */
.ph { margin-bottom: 22px; }
.ph-eyebrow {
    display: flex; align-items: center; gap: 6px;
    font-size: .5rem; font-weight: 700; color: var(--tx3);
    text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 5px;
}
.ph-dot { width: 4px; height: 4px; border-radius: 50%; background: var(--ac); }
.ph-title {
    font-family: var(--fh); font-size: 1.5rem; font-weight: 900;
    color: var(--tx); letter-spacing: -.035em; line-height: 1.1;
}
.ph-sub { font-size: .875rem; color: var(--tx2); margin-top: 4px; }

/* ── Session info card ────────────────────────────────────────────────── */
.session-card {
    background: var(--sf); border: 1px solid var(--bd); border-radius: 14px;
    padding: 20px 24px; margin-bottom: 22px;
    display: flex; align-items: flex-start; gap: 20px; flex-wrap: wrap;
}
.session-card-main { flex: 1; min-width: 220px; }
.session-card-title {
    font-family: var(--fh); font-size: 1.125rem; font-weight: 900;
    color: var(--tx); letter-spacing: -.025em; margin-bottom: 6px;
}
.session-card-meta {
    display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-top: 8px;
}
.meta-chip {
    font-size: .5625rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .5px; padding: 3px 9px; border-radius: 50px;
    background: rgba(255,255,255,.07); color: var(--tx3);
}
.meta-chip.green { background: rgba(31,226,144,.08); color: var(--ac); }
.meta-chip.blue  { background: rgba(59,130,246,.1); color: #60a5fa; }
.meta-chip.warn  { background: rgba(245,158,11,.1); color: var(--warn); }
.meta-chip.err   { background: rgba(239,68,68,.1); color: var(--err); }

.session-card-stats {
    display: flex; gap: 16px; align-items: center;
}
.stat-block { text-align: center; }
.stat-block-val {
    font-family: var(--fh); font-size: 1.5rem; font-weight: 900;
    color: var(--ac); letter-spacing: -.04em; line-height: 1;
}
.stat-block-val.neutral { color: var(--tx); }
.stat-block-label {
    font-size: .5rem; font-weight: 700; color: var(--tx3);
    text-transform: uppercase; letter-spacing: .8px; margin-top: 4px;
}
.stat-divider {
    width: 1px; height: 36px; background: var(--bd);
}

/* ── Buttons ──────────────────────────────────────────────────────────── */
.btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border-radius: 9px;
    font-family: var(--ff); font-size: .8125rem; font-weight: 700;
    text-decoration: none; border: 1.5px solid transparent;
    cursor: pointer; transition: all .18s;
}
.btn svg {
    width: 13px; height: 13px; stroke: currentColor; fill: none;
    stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round;
}
.btn-ghost { background: var(--sf); border-color: var(--bd); color: var(--tx2); }
.btn-ghost:hover { background: rgba(255,255,255,.07); color: var(--tx); border-color: rgba(255,255,255,.13); }
.btn-sm { padding: 5px 11px; font-size: .75rem; border-radius: 7px; }
.btn-primary { background: var(--ac); color: #0c1f1d; border-color: var(--ac); }
.btn-primary:hover { background: #13c474; }

/* ── Table card ───────────────────────────────────────────────────────── */
.table-card {
    background: var(--sf); border: 1px solid var(--bd);
    border-radius: 14px; overflow: hidden;
}
.table-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 18px; border-bottom: 1px solid var(--bd); flex-wrap: wrap; gap: 10px;
}
.table-title { font-size: .875rem; font-weight: 700; color: var(--tx); }
.table-sub { font-size: .625rem; color: var(--tx3); }
.table-actions { display: flex; gap: 8px; align-items: center; }
.table-wrap { overflow-x: auto; }

table { width: 100%; border-collapse: collapse; }
thead th {
    padding: 10px 16px; font-size: .5625rem; font-weight: 700;
    color: var(--tx3); text-transform: uppercase; letter-spacing: .8px;
    text-align: left; border-bottom: 1px solid var(--bd);
    background: rgba(255,255,255,.015); white-space: nowrap;
}
tbody td {
    padding: 13px 16px; font-size: .8125rem;
    border-bottom: 1px solid rgba(255,255,255,.04); vertical-align: middle;
}
tbody tr:last-child td { border-bottom: none; }
tbody tr { transition: background .14s; }
tbody tr:hover { background: rgba(255,255,255,.025); }

/* ── Cell types ───────────────────────────────────────────────────────── */
.ava {
    width: 32px; height: 32px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: .6875rem; font-weight: 800; flex-shrink: 0;
}
.s-name { font-weight: 700; color: var(--tx); letter-spacing: -.01em; }
.s-name a { color: inherit; text-decoration: none; }
.s-name a:hover { color: var(--ac); }
.s-email {
    display: block; font-size: .625rem; color: var(--tx3);
    font-family: var(--fm); margin-top: 2px;
}
.date-cell {
    font-size: .75rem; color: var(--tx3); font-family: var(--fm); white-space: nowrap;
}
.row-num {
    font-family: var(--fm); font-size: .75rem; color: var(--tx3);
}

/* ── Toggle switch ────────────────────────────────────────────────────── */
.toggle {
    position: relative; display: inline-block;
    width: 38px; height: 20px; cursor: pointer;
}
.toggle input { opacity: 0; width: 0; height: 0; }
.toggle-slider {
    position: absolute; inset: 0;
    background: rgba(255,255,255,.1); border: 1px solid var(--bd);
    border-radius: 20px; transition: all .2s;
}
.toggle-slider::before {
    content: ''; position: absolute;
    width: 14px; height: 14px; border-radius: 50%;
    left: 2px; bottom: 2px;
    background: var(--tx3); transition: all .2s;
}
.toggle input:checked + .toggle-slider {
    background: rgba(31,226,144,.2); border-color: rgba(31,226,144,.3);
}
.toggle input:checked + .toggle-slider::before {
    transform: translateX(18px); background: var(--ac);
}
.attendance-label {
    font-size: .625rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .5px; margin-left: 8px; vertical-align: middle;
}
.attendance-label.yes { color: var(--ac); }
.attendance-label.no  { color: var(--tx3); }

/* ── Empty state ──────────────────────────────────────────────────────── */
.empty { text-align: center; padding: 60px 20px; }
.empty-ico {
    width: 56px; height: 56px; border-radius: 16px;
    background: rgba(255,255,255,.04); border: 1px solid var(--bd);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px;
}
.empty-ico svg {
    width: 24px; height: 24px; stroke: var(--tx3); fill: none;
    stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round;
}
.empty-title {
    font-family: var(--fh); font-size: 1.125rem; font-weight: 900;
    color: var(--tx); letter-spacing: -.025em; margin-bottom: 6px;
}
.empty-sub { font-size: .8125rem; color: var(--tx3); line-height: 1.5; }

/* ── Toast ────────────────────────────────────────────────────────────── */
.toast-wrap {
    position: fixed; bottom: 24px; right: 24px; z-index: 500;
    display: flex; flex-direction: column; gap: 8px;
}
.toast {
    padding: 10px 18px; border-radius: 10px;
    font-size: .8125rem; font-weight: 600;
    background: var(--sf); border: 1px solid var(--bd); color: var(--tx2);
    transform: translateY(10px); opacity: 0; transition: all .3s;
    max-width: 340px;
}
.toast.show { transform: none; opacity: 1; }
.toast.success { background: rgba(31,226,144,.1); border-color: rgba(31,226,144,.2); color: var(--ac); }
.toast.error   { background: rgba(239,68,68,.1); border-color: rgba(239,68,68,.2); color: var(--err); }

/* ── Reveal animation ─────────────────────────────────────────────────── */
.reveal {
    opacity: 0; transform: translateY(12px);
    animation: rev .4s cubic-bezier(.16,1,.3,1) forwards;
}
@keyframes rev { to { opacity: 1; transform: none; } }
.d1 { animation-delay: .04s; }
.d2 { animation-delay: .08s; }
.d3 { animation-delay: .12s; }

/* ── Responsive ───────────────────────────────────────────────────────── */
@media (max-width: 768px) {
    .main { margin-left: 0; padding: 16px; }
    .session-card { flex-direction: column; }
    .session-card-stats { margin-top: 4px; }
    .table-head { flex-direction: column; align-items: flex-start; }
}
</style>
CSS;

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';

// ── Avatar color helper ──────────────────────────────────────────────────────
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

$sessionDate = !empty($session['session_date'])
    ? date('M j, Y \a\t g:i A', strtotime($session['session_date']))
    : 'Not scheduled';

$statusClass = match ($session['status'] ?? 'scheduled') {
    'active', 'scheduled' => 'green',
    'completed'           => 'blue',
    'cancelled'           => 'err',
    'draft'               => 'warn',
    default               => '',
};
?>

<!-- Topbar -->
<header class="topbar">
    <button class="topbar-ham" onclick="openSidebar()" aria-label="Menu">
        <span></span><span></span><span></span>
    </button>
    <div class="topbar-breadcrumb">
        <a href="/sessions/index.php">Sessions</a>
        <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        <a href="/sessions/edit.php?id=<?= $sessionId ?>" class="topbar-session-title"><?= htmlspecialchars($session['title']) ?></a>
        <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        <span class="topbar-title-text">Attendees</span>
    </div>
    <div style="flex:1"></div>
    <?php if (!empty($enrollees)): ?>
    <a href="/sessions/attendees.php?id=<?= $sessionId ?>&export=csv" class="btn btn-ghost btn-sm">
        <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export CSV
    </a>
    <?php endif; ?>
    <a href="/sessions/index.php" class="btn btn-ghost btn-sm">
        <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
        Back
    </a>
</header>

<!-- Main -->
<main class="main">

    <!-- Page header -->
    <div class="ph reveal d1">
        <div class="ph-eyebrow"><span class="ph-dot"></span>Session Attendees</div>
        <h1 class="ph-title">Attendees</h1>
        <p class="ph-sub">
            <?= $totalEnrolled ?> student<?= $totalEnrolled !== 1 ? 's' : '' ?> enrolled
            &middot; <?= $totalAttended ?> attended
        </p>
    </div>

    <!-- Session info card -->
    <div class="session-card reveal d1">
        <div class="session-card-main">
            <div class="session-card-title"><?= htmlspecialchars($session['title']) ?></div>
            <?php if (!empty($session['description'])): ?>
            <p style="font-size:.8125rem;color:var(--tx2);line-height:1.5;margin-bottom:6px">
                <?= htmlspecialchars(mb_substr($session['description'], 0, 120)) ?><?= mb_strlen($session['description'] ?? '') > 120 ? '...' : '' ?>
            </p>
            <?php endif; ?>
            <div class="session-card-meta">
                <span class="meta-chip <?= $statusClass ?>"><?= ucfirst(htmlspecialchars($session['status'] ?? 'scheduled')) ?></span>
                <span class="meta-chip"><?= htmlspecialchars(ucfirst($session['session_type'] ?? 'general')) ?></span>
                <span class="meta-chip"><?= htmlspecialchars($sessionDate) ?></span>
                <span class="meta-chip"><?= (int)($session['duration_minutes'] ?? 0) ?> min</span>
            </div>
        </div>
        <div class="session-card-stats">
            <div class="stat-block">
                <div class="stat-block-val"><?= $totalEnrolled ?></div>
                <div class="stat-block-label">Enrolled</div>
            </div>
            <div class="stat-divider"></div>
            <div class="stat-block">
                <div class="stat-block-val neutral"><?= (int)($session['max_students'] ?? 0) ?></div>
                <div class="stat-block-label">Max</div>
            </div>
            <div class="stat-divider"></div>
            <div class="stat-block">
                <div class="stat-block-val" style="color:<?= $totalAttended > 0 ? 'var(--ac)' : 'var(--tx3)' ?>"><?= $totalAttended ?></div>
                <div class="stat-block-label">Attended</div>
            </div>
        </div>
    </div>

    <!-- Attendees table -->
    <div class="table-card reveal d2">
        <div class="table-head">
            <div>
                <div class="table-title">Enrolled Students</div>
                <div class="table-sub"><?= $totalEnrolled ?> student<?= $totalEnrolled !== 1 ? 's' : '' ?> &middot; toggle attendance with the switch</div>
            </div>
            <div class="table-actions">
                <?php if (!empty($enrollees)): ?>
                <a href="/sessions/attendees.php?id=<?= $sessionId ?>&export=csv" class="btn btn-ghost btn-sm">
                    <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Export CSV
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($enrollees)): ?>
        <!-- Empty state -->
        <div class="empty">
            <div class="empty-ico">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
            </div>
            <div class="empty-title">No enrollees yet</div>
            <div class="empty-sub">
                No students have enrolled in this session.<br>
                Students can enroll through the main platform.
            </div>
        </div>

        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Student</th>
                        <th>Email</th>
                        <th>Enrolled Date</th>
                        <th>Attended</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($enrollees as $i => $e):
                    $name = $e['student_name'] ?? 'Unknown';
                    $ac   = avatarColor($name);
                    $att  = (int) ($e['attended'] ?? 0);
                ?>
                <tr data-enrollment-id="<?= (int)$e['enrollment_id'] ?>">
                    <td><span class="row-num"><?= $i + 1 ?></span></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div class="ava" style="background:<?= $ac['bg'] ?>;color:<?= $ac['fg'] ?>">
                                <?= strtoupper(mb_substr($name, 0, 1)) ?>
                            </div>
                            <div>
                                <div class="s-name">
                                    <a href="/students/student.php?id=<?= (int)$e['user_id'] ?>">
                                        <?= htmlspecialchars($name) ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <code class="s-email" style="display:inline"><?= htmlspecialchars($e['email'] ?? '') ?></code>
                    </td>
                    <td>
                        <span class="date-cell">
                            <?= $e['enrolled_at'] ? date('M j, Y g:i A', strtotime($e['enrolled_at'])) : '—' ?>
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center">
                            <label class="toggle">
                                <input type="checkbox"
                                       <?= $att ? 'checked' : '' ?>
                                       onchange="toggleAttendance(<?= (int)$e['enrollment_id'] ?>, this.checked ? 1 : 0, this)">
                                <span class="toggle-slider"></span>
                            </label>
                            <span class="attendance-label <?= $att ? 'yes' : 'no' ?>"
                                  id="att-label-<?= (int)$e['enrollment_id'] ?>">
                                <?= $att ? 'Yes' : 'No' ?>
                            </span>
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

<div class="toast-wrap" id="toastWrap"></div>

<script>
// ── Toggle attendance via AJAX ────────────────────────────────────────────────
async function toggleAttendance(enrollmentId, attended, checkbox) {
    const label = document.getElementById('att-label-' + enrollmentId);
    try {
        const res = await fetch('/sessions/attendees.php?id=<?= $sessionId ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=toggle_attendance&session_id=<?= $sessionId ?>'
                + '&enrollment_id=' + enrollmentId
                + '&attended=' + attended,
        });
        const data = await res.json();
        if (data.success) {
            label.textContent = attended ? 'Yes' : 'No';
            label.className   = 'attendance-label ' + (attended ? 'yes' : 'no');
            toast(attended ? 'Marked as attended' : 'Marked as not attended', 'success');
        } else {
            // Revert checkbox
            checkbox.checked = !checkbox.checked;
            toast(data.error || 'Failed to update.', 'error');
        }
    } catch (e) {
        checkbox.checked = !checkbox.checked;
        toast('Network error. Please try again.', 'error');
    }
}

// ── Toast ─────────────────────────────────────────────────────────────────────
function toast(msg, type) {
    type = type || 'info';
    var wrap = document.getElementById('toastWrap');
    var el   = document.createElement('div');
    el.className   = 'toast ' + type;
    el.textContent = msg;
    wrap.appendChild(el);
    requestAnimationFrame(function() {
        requestAnimationFrame(function() { el.classList.add('show'); });
    });
    setTimeout(function() {
        el.classList.remove('show');
        setTimeout(function() { el.remove(); }, 400);
    }, 2800);
}
</script>
</body>
</html>
