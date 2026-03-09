<?php
/**
 * sessions/edit.php
 * Edit an existing tutoring session.
 */
require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

$admin = currentAdmin();
$db    = Database::connect();

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrfToken = $_SESSION['csrf_token'];

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /sessions/index.php'); exit; }

// ── Load session ─────────────────────────────────────────────────────────
$session = Database::fetch("SELECT * FROM tutoring_sessions WHERE id = ?", [$id]);
if (!$session) { header('Location: /sessions/index.php'); exit; }

// ── Flash message ────────────────────────────────────────────────────────
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// ── Enrollment count ─────────────────────────────────────────────────────
$enrolledCount = (int) Database::fetchColumn("SELECT COUNT(*) FROM session_enrollments WHERE session_id = ?", [$id]);

// ── Creator name ─────────────────────────────────────────────────────────
$creatorName = null;
if (!empty($session['created_by'])) {
    try {
        $creatorName = Database::fetchColumn("SELECT name FROM admins WHERE id = ?", [$session['created_by']]);
    } catch (Throwable) {
        $creatorName = 'Admin #' . $session['created_by'];
    }
}

// ── Handle POST ──────────────────────────────────────────────────────────
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $action = $_POST['_action'] ?? 'update';

        // ── DELETE SESSION ────────────────────────────────────────────
        if ($action === 'delete') {
            try {
                Database::delete('session_enrollments', ['session_id' => $id]);
                Database::delete('tutoring_sessions', ['id' => $id]);
                $_SESSION['flash'] = 'ok:Session deleted.';
                header('Location: /sessions/index.php');
                exit;
            } catch (Throwable $e) {
                $errors[] = 'Delete failed: ' . $e->getMessage();
            }
        }

        // ── UPDATE SESSION ────────────────────────────────────────────
        if ($action === 'update') {
            $title           = trim($_POST['title'] ?? '');
            $description     = trim($_POST['description'] ?? '');
            $session_type    = $_POST['session_type'] ?? $session['session_type'];
            $session_date    = trim($_POST['session_date'] ?? '');
            $duration_minutes = max(0, (int)($_POST['duration_minutes'] ?? 60));
            $max_students    = max(0, (int)($_POST['max_students'] ?? 0));
            $meeting_url     = trim($_POST['meeting_url'] ?? '');
            $status          = $_POST['status'] ?? $session['status'];

            // Validate
            if ($title === '') $errors[] = 'Title is required.';
            if ($session_date === '') $errors[] = 'Session date is required.';
            if (!in_array($session_type, ['group', 'one_on_one', 'workshop'])) $errors[] = 'Invalid session type.';
            if (!in_array($status, ['scheduled', 'live', 'completed', 'cancelled'])) $errors[] = 'Invalid status.';
            if ($duration_minutes < 1) $errors[] = 'Duration must be at least 1 minute.';

            if (empty($errors)) {
                try {
                    Database::update('tutoring_sessions', [
                        'title'            => $title,
                        'description'      => $description,
                        'session_type'     => $session_type,
                        'session_date'     => $session_date,
                        'duration_minutes' => $duration_minutes,
                        'max_students'     => $max_students,
                        'meeting_url'      => $meeting_url,
                        'status'           => $status,
                        'updated_at'       => date('Y-m-d H:i:s'),
                    ], ['id' => $id]);

                    $_SESSION['flash'] = 'ok:Session updated successfully.';
                    header('Location: /sessions/edit.php?id=' . $id);
                    exit;
                } catch (Throwable $e) {
                    $errors[] = 'Update failed: ' . $e->getMessage();
                }
            }

            // Re-populate from POST on error
            $session['title']            = $title;
            $session['description']      = $description;
            $session['session_type']     = $session_type;
            $session['session_date']     = $session_date;
            $session['duration_minutes'] = $duration_minutes;
            $session['max_students']     = $max_students;
            $session['meeting_url']      = $meeting_url;
            $session['status']           = $status;
        }
    }
}

$pageTitle  = 'Edit Session — Avidmock Admin';
$activePage = 'sessions';

// Status config
$statusColors = [
    'scheduled' => ['bg' => 'rgba(59,130,246,.12)',  'color' => '#3b82f6'],
    'live'      => ['bg' => 'rgba(31,226,144,.12)',  'color' => '#1fe290'],
    'completed' => ['bg' => 'rgba(255,255,255,.07)', 'color' => '#9dbfba'],
    'cancelled' => ['bg' => 'rgba(239,68,68,.12)',   'color' => '#ef4444'],
];
$typeLabels = [
    'group'    => 'Group',
    'one_on_one' => 'One-on-One',
    'workshop' => 'Workshop',
];
?>
<?php require_once __DIR__ . '/../includes/head.php'; ?>
<style>
/* ── Design tokens ─────────────────────────────────────────────────────── */
:root {
    --ink:#0c1f1d; --ink2:#0e2522; --dk:#143230;
    --ac:#1fe290; --ac2:#13c474; --ac3:rgba(31,226,144,.08); --ac4:rgba(31,226,144,.15);
    --tx:#e8f3f1; --tx2:#9dbfba; --tx3:#5a8580;
    --bd:rgba(255,255,255,.07); --bd2:rgba(255,255,255,.13);
    --sf:rgba(255,255,255,.04); --sf2:rgba(255,255,255,.07); --sf3:rgba(255,255,255,.10);
    --warn:#f59e0b; --warn2:rgba(245,158,11,.12);
    --err:#ef4444; --err2:rgba(239,68,68,.12);
    --blue:#3b82f6; --blue2:rgba(59,130,246,.12);
    --purple:#8b5cf6; --purple2:rgba(139,92,246,.12);
    --ff:'DM Sans',sans-serif; --fh:'Fraunces',Georgia,serif; --fm:'DM Mono',monospace;
    --sb-w:240px; --top-h:60px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:var(--ff);background:var(--ink);color:var(--tx);-webkit-font-smoothing:antialiased}

/* ── Sidebar ───────────────────────────────────────────────────────────── */
.sb{position:fixed;top:0;left:0;width:var(--sb-w);height:100vh;background:var(--ink2);border-right:1px solid var(--bd);display:flex;flex-direction:column;overflow-y:auto;z-index:300;transition:transform .3s cubic-bezier(.16,1,.3,1)}
.sb::-webkit-scrollbar{width:3px}.sb::-webkit-scrollbar-thumb{background:rgba(255,255,255,.06);border-radius:2px}
.sb-logo{display:flex;align-items:center;gap:10px;padding:0 18px;height:var(--top-h);border-bottom:1px solid var(--bd);text-decoration:none;flex-shrink:0}
.sb-logo-name{font-size:.875rem;font-weight:800;color:var(--tx);letter-spacing:-.02em}
.sb-logo-sub{font-size:.5625rem;color:var(--tx3);font-weight:600;text-transform:uppercase;letter-spacing:.6px}
.sb-nav{flex:1;padding:10px 0 16px}
.sb-group{padding:16px 18px 5px;font-size:.5rem;font-weight:700;color:var(--tx3);text-transform:uppercase;letter-spacing:1.2px}
.sb-link{display:flex;align-items:center;gap:9px;padding:8px 10px;margin:1px 8px;border-radius:9px;text-decoration:none;font-size:.8125rem;font-weight:600;color:var(--tx2);transition:all .15s;position:relative}
.sb-link:hover{background:var(--sf2);color:var(--tx)}
.sb-link.active{background:var(--ac3);color:var(--ac)}
.sb-link.active::before{content:'';position:absolute;left:-10px;top:50%;transform:translateY(-50%);width:3px;height:55%;background:var(--ac);border-radius:0 2px 2px 0}
.sb-ico{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:1.85;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0}
.sb-foot{margin:8px;padding:10px 12px;background:var(--sf);border:1px solid var(--bd);border-radius:10px;display:flex;align-items:center;gap:9px}
.sb-ava{width:30px;height:30px;border-radius:8px;background:linear-gradient(135deg,var(--ac),var(--ac2));display:flex;align-items:center;justify-content:center;font-size:.6875rem;font-weight:800;color:var(--dk);flex-shrink:0}
.sb-name{font-size:.75rem;font-weight:700;color:var(--tx)}
.sb-role{font-size:.5625rem;color:var(--tx3);text-transform:capitalize}
.sb-out{margin-left:auto;padding:5px;background:none;border:none;cursor:pointer;color:var(--tx3);line-height:0;transition:all .15s;border-radius:6px}
.sb-out:hover{background:var(--err2);color:var(--err)}
.sb-out svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:250;opacity:0;transition:opacity .25s;pointer-events:none}
.sb-overlay.show{display:block;opacity:1;pointer-events:all}
.sb-badge{font-size:.5625rem;font-weight:800;padding:1px 7px;border-radius:50px;background:var(--ac3);color:var(--ac);margin-left:auto}
.sb-badge.warn{background:var(--warn2);color:var(--warn)}

/* ── Topbar ────────────────────────────────────────────────────────────── */
.topbar{position:fixed;top:0;left:var(--sb-w);right:0;height:var(--top-h);background:rgba(12,31,29,.94);backdrop-filter:blur(16px);border-bottom:1px solid var(--bd);display:flex;align-items:center;padding:0 28px;gap:10px;z-index:200}
.topbar-ham{display:none;width:34px;height:34px;border-radius:8px;border:1px solid var(--bd);background:var(--sf);align-items:center;justify-content:center;cursor:pointer;flex-direction:column;gap:4px;padding:9px}
.topbar-ham span{display:block;height:1.5px;background:var(--tx2);border-radius:1px;width:100%}
.topbar-breadcrumb{display:flex;align-items:center;gap:7px;font-family:var(--fh);font-size:.9375rem;font-weight:900;color:var(--tx);letter-spacing:-.025em;min-width:0;overflow:hidden}
.topbar-breadcrumb a{color:var(--tx3);font-style:italic;font-weight:300;text-decoration:none;transition:color .15s;white-space:nowrap}
.topbar-breadcrumb a:hover{color:var(--ac)}
.topbar-breadcrumb svg{width:12px;height:12px;stroke:var(--tx3);fill:none;stroke-width:2;stroke-linecap:round;flex-shrink:0}
.topbar-title-text{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.topbar-spacer{flex:1;min-width:8px}

/* ── Status pills ─────────────────────────────────────────────────────── */
.status-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:50px;font-size:.5rem;font-weight:800;text-transform:uppercase;letter-spacing:.5px;flex-shrink:0}
.status-pill.scheduled{background:var(--blue2);color:var(--blue)}
.status-pill.live{background:var(--ac3);color:var(--ac)}
.status-pill.completed{background:var(--sf3);color:var(--tx3)}
.status-pill.cancelled{background:var(--err2);color:var(--err)}

/* ── Buttons ───────────────────────────────────────────────────────────── */
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:9px;font-family:var(--ff);font-size:.8125rem;font-weight:700;text-decoration:none;border:1.5px solid transparent;cursor:pointer;transition:all .18s;white-space:nowrap;flex-shrink:0}
.btn svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
.btn-sm{padding:6px 13px;font-size:.75rem}
.btn-ghost{background:var(--sf);border-color:var(--bd);color:var(--tx2)}
.btn-ghost:hover{background:var(--sf2);border-color:var(--bd2);color:var(--tx)}
.btn-primary{background:var(--ac);color:var(--dk);border:none}
.btn-primary:hover{background:var(--ac2);transform:translateY(-1px);box-shadow:0 4px 16px rgba(31,226,144,.25)}
.btn-danger{background:var(--err2);border-color:rgba(239,68,68,.2);color:var(--err)}
.btn-danger:hover{background:rgba(239,68,68,.22)}

/* ── Layout ────────────────────────────────────────────────────────────── */
.main{margin-left:var(--sb-w);margin-top:var(--top-h);display:grid;grid-template-columns:1fr 288px;min-height:calc(100vh - var(--top-h));align-items:start}
.main-col{padding:32px 28px}
.aside-col{padding:24px 20px;background:var(--ink2);border-left:1px solid var(--bd);position:sticky;top:var(--top-h);height:calc(100vh - var(--top-h));overflow-y:auto;display:flex;flex-direction:column}
.aside-col::-webkit-scrollbar{width:3px}.aside-col::-webkit-scrollbar-thumb{background:rgba(255,255,255,.06);border-radius:2px}

/* ── Cards ─────────────────────────────────────────────────────────────── */
.card{background:var(--sf);border:1px solid var(--bd);border-radius:14px;overflow:hidden;margin-bottom:20px}
.card-head{padding:14px 20px;border-bottom:1px solid var(--bd);display:flex;align-items:center;gap:10px}
.card-head-icon{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.card-head-icon svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.card-head-icon.green{background:var(--ac3);color:var(--ac)}
.card-head-icon.blue{background:var(--blue2);color:var(--blue)}
.card-head-title{font-size:.875rem;font-weight:700;color:var(--tx)}
.card-body{padding:20px}

/* ── Form fields ───────────────────────────────────────────────────────── */
.field{margin-bottom:18px}
.field:last-child{margin-bottom:0}
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:18px}
.field-label{display:block;font-size:.75rem;font-weight:700;color:var(--tx2);margin-bottom:6px}
.field-label .opt{color:var(--tx3);font-weight:400}
.field-hint{font-size:.5625rem;color:var(--tx3);margin-top:5px;line-height:1.55}
.field-input{width:100%;padding:10px 13px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:9px;font-family:var(--ff);font-size:.875rem;color:#fff;outline:none;transition:border-color .18s,box-shadow .18s;line-height:1.6}
.field-input::placeholder{color:var(--tx3)}
.field-input:focus{border-color:var(--ac);box-shadow:0 0 0 3px rgba(31,226,144,.09)}
textarea.field-input{resize:vertical;min-height:80px}
select.field-input{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%235a8580' stroke-width='2' stroke-linecap='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:32px}

/* ── Aside sections ───────────────────────────────────────────────────── */
.aside-section{margin-bottom:24px}
.aside-section:last-child{margin-bottom:0}
.aside-label{font-size:.5rem;font-weight:700;color:var(--tx3);text-transform:uppercase;letter-spacing:1.1px;margin-bottom:10px;display:flex;align-items:center;gap:8px}
.aside-label::after{content:'';flex:1;height:1px;background:var(--bd)}

.meta-row{display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--bd)}
.meta-row:last-child{border-bottom:none;padding-bottom:0}
.meta-row:first-child{padding-top:0}
.meta-key{font-size:.75rem;font-weight:600;color:var(--tx3)}
.meta-val{font-size:.75rem;font-weight:700;color:var(--tx);text-align:right}
.meta-val.mono{font-family:var(--fm);font-size:.6875rem}

/* ── Enrollment stat ──────────────────────────────────────────────────── */
.enroll-stat{display:flex;align-items:center;gap:12px;padding:14px 16px;background:var(--sf);border:1px solid var(--bd);border-radius:10px;margin-bottom:14px}
.enroll-stat-icon{width:36px;height:36px;border-radius:9px;background:var(--ac3);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.enroll-stat-icon svg{width:16px;height:16px;stroke:var(--ac);fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.enroll-stat-num{font-family:var(--fm);font-size:1.25rem;font-weight:700;color:var(--tx);line-height:1}
.enroll-stat-label{font-size:.5625rem;color:var(--tx3);margin-top:2px}

/* ── Alerts ────────────────────────────────────────────────────────────── */
.alert{padding:12px 16px;border-radius:10px;font-size:.8125rem;margin-bottom:20px;display:flex;align-items:flex-start;gap:10px;line-height:1.55}
.alert svg{flex-shrink:0;margin-top:1px;width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round}
.alert-ok{background:var(--ac3);border:1px solid rgba(31,226,144,.2);color:var(--ac)}
.alert-err{background:var(--err2);border:1px solid rgba(239,68,68,.2);color:var(--err)}

/* ── Toast ─────────────────────────────────────────────────────────────── */
.toast{position:fixed;bottom:28px;right:28px;padding:12px 20px;border-radius:10px;font-size:.8125rem;font-weight:600;color:var(--dk);background:var(--ac);box-shadow:0 8px 32px rgba(0,0,0,.35);z-index:9999;transform:translateY(20px);opacity:0;transition:all .35s cubic-bezier(.16,1,.3,1);pointer-events:none}
.toast.show{transform:translateY(0);opacity:1;pointer-events:auto}
.toast.error{background:var(--err);color:#fff}

/* ── Status select coloring ───────────────────────────────────────────── */
.status-select{font-weight:700}
.status-select.scheduled{color:var(--blue)}
.status-select.live{color:var(--ac)}
.status-select.completed{color:var(--tx3)}
.status-select.cancelled{color:var(--err)}

/* ── Form actions bar ─────────────────────────────────────────────────── */
.form-actions{display:flex;align-items:center;gap:10px;padding-top:20px;border-top:1px solid var(--bd);margin-top:24px}
.form-actions-spacer{flex:1}

/* ── Danger zone ──────────────────────────────────────────────────────── */
.danger-zone{margin-top:auto;padding-top:16px;border-top:1px solid var(--bd)}
.danger-zone-title{font-size:.5rem;font-weight:700;color:var(--err);text-transform:uppercase;letter-spacing:1.1px;margin-bottom:10px;display:flex;align-items:center;gap:8px}
.danger-zone-title::after{content:'';flex:1;height:1px;background:rgba(239,68,68,.15)}

/* ── Responsive ───────────────────────────────────────────────────────── */
@media(max-width:1024px){
    .main{grid-template-columns:1fr}
    .aside-col{position:static;height:auto;border-left:none;border-top:1px solid var(--bd)}
}
@media(max-width:768px){
    :root{--sb-w:0px}
    .sb{transform:translateX(-100%)}
    .sb.open{transform:translateX(0)}
    .topbar-ham{display:flex}
    .main-col{padding:24px 16px}
    .aside-col{padding:20px 16px}
    .field-row{grid-template-columns:1fr}
}
</style>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<!-- ── Topbar ──────────────────────────────────────────────────────────── -->
<header class="topbar">
    <button class="topbar-ham" onclick="openSidebar()"><span></span><span></span><span></span></button>
    <div class="topbar-breadcrumb">
        <a href="/sessions/index.php">Sessions</a>
        <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        <span class="topbar-title-text">Edit: <?= htmlspecialchars($session['title']) ?></span>
    </div>
    <span class="status-pill <?= htmlspecialchars($session['status']) ?>"><?= htmlspecialchars(ucfirst($session['status'])) ?></span>
    <div class="topbar-spacer"></div>
    <a href="/sessions/index.php" class="btn btn-ghost btn-sm">
        <svg viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Back
    </a>
</header>

<!-- ── Main ────────────────────────────────────────────────────────────── -->
<div class="main">
    <div class="main-col">

        <?php if ($flash): ?>
            <?php
                $fParts = explode(':', $flash, 2);
                $fType  = $fParts[0] === 'ok' ? 'ok' : 'err';
                $fMsg   = $fParts[1] ?? $flash;
            ?>
            <div class="alert alert-<?= $fType ?>">
                <?php if ($fType === 'ok'): ?>
                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                <?php else: ?>
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                <?php endif; ?>
                <?= htmlspecialchars($fMsg) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-err">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                <div>
                    <?php foreach ($errors as $err): ?>
                        <div><?= htmlspecialchars($err) ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" id="editForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="_action" value="update">

            <!-- Session Details -->
            <div class="card">
                <div class="card-head">
                    <div class="card-head-icon green">
                        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </div>
                    <span class="card-head-title">Session Details</span>
                </div>
                <div class="card-body">
                    <div class="field">
                        <label class="field-label" for="title">Title</label>
                        <input type="text" id="title" name="title" class="field-input" placeholder="e.g. SAT Math Bootcamp" value="<?= htmlspecialchars($session['title']) ?>" required>
                    </div>

                    <div class="field">
                        <label class="field-label" for="description">Description <span class="opt">(optional)</span></label>
                        <textarea id="description" name="description" class="field-input" rows="3" placeholder="Brief description of what this session covers..."><?= htmlspecialchars($session['description'] ?? '') ?></textarea>
                    </div>

                    <div class="field-row">
                        <div class="field">
                            <label class="field-label" for="session_type">Session Type</label>
                            <select id="session_type" name="session_type" class="field-input">
                                <option value="group"    <?= $session['session_type'] === 'group'    ? 'selected' : '' ?>>Group</option>
                                <option value="one_on_one" <?= $session['session_type'] === 'one_on_one' ? 'selected' : '' ?>>One-on-One</option>
                                <option value="workshop" <?= $session['session_type'] === 'workshop' ? 'selected' : '' ?>>Workshop</option>
                            </select>
                        </div>
                        <div class="field">
                            <label class="field-label" for="status">Status</label>
                            <select id="status" name="status" class="field-input status-select <?= htmlspecialchars($session['status']) ?>" onchange="this.className='field-input status-select '+this.value">
                                <option value="scheduled" <?= $session['status'] === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                                <option value="live"      <?= $session['status'] === 'live'      ? 'selected' : '' ?>>Live</option>
                                <option value="completed" <?= $session['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="cancelled" <?= $session['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                    </div>

                    <div class="field-row">
                        <div class="field">
                            <label class="field-label" for="session_date">Session Date &amp; Time</label>
                            <input type="datetime-local" id="session_date" name="session_date" class="field-input" value="<?= htmlspecialchars(date('Y-m-d\TH:i', strtotime($session['session_date']))) ?>" required>
                        </div>
                        <div class="field">
                            <label class="field-label" for="duration_minutes">Duration (minutes)</label>
                            <input type="number" id="duration_minutes" name="duration_minutes" class="field-input" min="1" max="600" value="<?= (int)$session['duration_minutes'] ?>">
                        </div>
                    </div>

                    <div class="field-row">
                        <div class="field">
                            <label class="field-label" for="max_students">Max Students</label>
                            <input type="number" id="max_students" name="max_students" class="field-input" min="0" value="<?= (int)$session['max_students'] ?>">
                            <div class="field-hint">Set to 0 for unlimited.</div>
                        </div>
                        <div class="field">
                            <label class="field-label" for="meeting_url">Meeting URL <span class="opt">(optional)</span></label>
                            <input type="url" id="meeting_url" name="meeting_url" class="field-input" placeholder="https://zoom.us/j/..." value="<?= htmlspecialchars($session['meeting_url'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    Save Changes
                </button>
                <a href="/sessions/index.php" class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    </div>

    <!-- ── Aside ──────────────────────────────────────────────────────── -->
    <div class="aside-col">

        <!-- Enrollment stat -->
        <div class="aside-section">
            <div class="aside-label">Enrollment</div>
            <div class="enroll-stat">
                <div class="enroll-stat-icon">
                    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                </div>
                <div>
                    <div class="enroll-stat-num"><?= $enrolledCount ?><?php if ((int)$session['max_students'] > 0): ?><span style="font-size:.6875rem;color:var(--tx3);font-weight:400"> / <?= (int)$session['max_students'] ?></span><?php endif; ?></div>
                    <div class="enroll-stat-label">Enrolled students</div>
                </div>
            </div>
            <?php if ($enrolledCount > 0): ?>
                <a href="/sessions/attendees.php?id=<?= $id ?>" class="btn btn-ghost btn-sm" style="width:100%;justify-content:center">
                    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    View Attendees
                </a>
            <?php endif; ?>
        </div>

        <!-- Session meta -->
        <div class="aside-section">
            <div class="aside-label">Session Info</div>
            <div class="meta-row">
                <span class="meta-key">ID</span>
                <span class="meta-val mono">#<?= $id ?></span>
            </div>
            <div class="meta-row">
                <span class="meta-key">Type</span>
                <span class="meta-val"><?= htmlspecialchars($typeLabels[$session['session_type']] ?? $session['session_type']) ?></span>
            </div>
            <div class="meta-row">
                <span class="meta-key">Status</span>
                <span class="meta-val"><span class="status-pill <?= htmlspecialchars($session['status']) ?>"><?= htmlspecialchars(ucfirst($session['status'])) ?></span></span>
            </div>
            <div class="meta-row">
                <span class="meta-key">Created</span>
                <span class="meta-val mono"><?= $session['created_at'] ? date('M j, Y g:ia', strtotime($session['created_at'])) : '—' ?></span>
            </div>
            <div class="meta-row">
                <span class="meta-key">Updated</span>
                <span class="meta-val mono"><?= $session['updated_at'] ? date('M j, Y g:ia', strtotime($session['updated_at'])) : '—' ?></span>
            </div>
            <?php if ($creatorName): ?>
            <div class="meta-row">
                <span class="meta-key">Created by</span>
                <span class="meta-val"><?= htmlspecialchars($creatorName) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Danger Zone -->
        <div class="danger-zone">
            <div class="danger-zone-title">Danger Zone</div>
            <form method="POST" id="deleteForm" onsubmit="return confirmDelete()">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="_action" value="delete">
                <button type="submit" class="btn btn-danger btn-sm" style="width:100%;justify-content:center">
                    <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                    Delete Session
                </button>
            </form>
            <div class="field-hint" style="margin-top:8px;text-align:center">This will also remove all enrollment records.</div>
        </div>
    </div>
</div>

<!-- ── Toast (for flash) ──────────────────────────────────────────────── -->
<div class="toast" id="toast"></div>

<script>
/* ── Delete confirmation ────────────────────────────────────────────────── */
function confirmDelete() {
    return confirm('Are you sure you want to delete this session?\n\nThis will permanently remove the session and all enrollment records. This action cannot be undone.');
}

/* ── Flash → toast ──────────────────────────────────────────────────────── */
(function() {
    const flash = <?= json_encode($flash) ?>;
    if (!flash) return;
    const [type, ...rest] = flash.split(':');
    const msg = rest.join(':');
    if (!msg) return;
    const toast = document.getElementById('toast');
    toast.textContent = msg;
    if (type !== 'ok') toast.classList.add('error');
    requestAnimationFrame(() => {
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3500);
    });
})();
</script>
</body>
</html>
