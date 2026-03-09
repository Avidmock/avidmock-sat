<?php
/**
 * quizzes/publish.php
 * Toggle quiz status: draft ↔ published ↔ archived
 *
 * GET  ?id=&action=publish|unpublish|archive  → confirmation page
 * POST ?id=&action=...                         → execute transition → redirect
 *
 * The $back parameter is validated against an allowlist of safe internal paths.
 */
require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

$admin  = currentAdmin();
$db     = Database::connect();

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// ── Input ─────────────────────────────────────────────────────────────────
$quizId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$allowedActions = ['publish', 'unpublish', 'archive'];
$action = in_array($_GET['action'] ?? $_POST['action'] ?? '', $allowedActions)
    ? ($_GET['action'] ?? $_POST['action'])
    : null;

// Validate $back against an allowlist of safe internal paths only
$rawBack = $_GET['back'] ?? $_POST['back'] ?? '';
$allowedBacks = [
    '/quizzes/index.php',
    '/quizzes/edit.php',
    '/index.php',
];
$back = '/quizzes/index.php'; // safe default
foreach ($allowedBacks as $allowed) {
    if (str_starts_with($rawBack, $allowed)) {
        $back = $rawBack;
        break;
    }
}

if (!$quizId || !$action) {
    header('Location: /quizzes/index.php');
    exit;
}

// ── Status map ────────────────────────────────────────────────────────────
$statusMap = [
    'publish'   => 'published',
    'unpublish' => 'draft',
    'archive'   => 'archived',
];
$newStatus = $statusMap[$action];

// ── Load quiz ─────────────────────────────────────────────────────────────
$quizStmt = $db->prepare("SELECT * FROM sat_quizzes WHERE id = :id");
$quizStmt->execute([':id' => $quizId]);
$quiz = $quizStmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    $_SESSION['flash_error'] = 'Quiz not found.';
    header('Location: /quizzes/index.php');
    exit;
}

// ── Question count (needed for publish guard) ─────────────────────────────
$qcStmt = $db->prepare("SELECT COUNT(*) FROM sat_quiz_questions WHERE quiz_id = :id");
$qcStmt->execute([':id' => $quizId]);
$qCount = (int)$qcStmt->fetchColumn();

// ── Handle POST — execute transition ─────────────────────────────────────
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check
    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Invalid request token. Please try again.';
        header('Location: ' . $back);
        exit;
    }

    // Guard: can't publish a quiz with no questions
    if ($newStatus === 'published' && $qCount === 0) {
        $error = 'You cannot publish a quiz with no questions. Add at least one question first.';
    }

    // Guard: no-op if already in target state
    elseif ($quiz['status'] === $newStatus) {
        $_SESSION['flash_success'] = 'Quiz is already ' . $newStatus . '.';
        header('Location: ' . $back);
        exit;
    }

    else {
        try {
            $stmt = $db->prepare(
                "UPDATE sat_quizzes SET status = :s, updated_at = NOW() WHERE id = :id"
            );
            $stmt->execute([':s' => $newStatus, ':id' => $quizId]);

            if ($stmt->rowCount() === 0) {
                $error = 'No changes were made. The quiz may have been modified by someone else.';
            } else {
                $labels = [
                    'published' => 'published and live',
                    'draft'     => 'moved back to draft',
                    'archived'  => 'archived',
                ];
                $_SESSION['flash_success'] = 'Quiz ' . ($labels[$newStatus] ?? $newStatus) . '.';
                header('Location: ' . $back);
                exit;
            }
        } catch (Throwable $e) {
            error_log('Publish quiz error: ' . $e->getMessage());
            $error = 'Something went wrong. Please try again.';
        }
    }
}

// ── GET — show confirmation page ──────────────────────────────────────────
$actionLabels = [
    'publish'   => ['label' => 'Publish',        'verb' => 'publish',          'color' => 'green'],
    'unpublish' => ['label' => 'Move to Draft',  'verb' => 'move to draft',    'color' => 'warn'],
    'archive'   => ['label' => 'Archive',         'verb' => 'archive',          'color' => 'warn'],
];
$actionMeta = $actionLabels[$action];

// Warnings to surface on the confirmation page
$warnings = [];
if ($newStatus === 'published' && $qCount === 0) {
    $warnings[] = 'This quiz has no questions. You must add at least one before publishing.';
}
if ($newStatus === 'published' && ($quiz['time_limit'] ?? 0) == 0) {
    $warnings[] = 'This quiz has no time limit set. Students can take as long as they like.';
}
if ($newStatus === 'archived' || $newStatus === 'draft') {
    // Check for active attempts
    $allTables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('sat_quiz_attempts', $allTables)) {
        try {
            $attStmt = $db->prepare(
                "SELECT COUNT(*) FROM sat_quiz_attempts WHERE quiz_id = :id AND status = 'in_progress'"
            );
            $attStmt->execute([':id' => $quizId]);
            $inProgress = (int)$attStmt->fetchColumn();
            if ($inProgress > 0) {
                $warnings[] = $inProgress . ' student' . ($inProgress !== 1 ? 's are' : ' is') .
                              ' currently taking this quiz. Their attempt will be interrupted.';
            }
        } catch (Throwable) {}
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($actionMeta['label']) ?>: <?= htmlspecialchars($quiz['title']) ?> — Avidmock Admin</title>
<meta name="robots" content="noindex,nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,600;9..40,700;9..40,800&family=Fraunces:ital,opsz,wght@0,9..144,900;1,9..144,300&family=DM+Mono:wght@400&display=swap" rel="stylesheet">
<style>
:root{
    --ink:#0c1f1d;--ink2:#0e2522;--dk:#143230;
    --ac:#1fe290;--ac2:#13c474;--ac3:rgba(31,226,144,.08);--ac4:rgba(31,226,144,.15);
    --tx:#e8f3f1;--tx2:#9dbfba;--tx3:#5a8580;
    --bd:rgba(255,255,255,.07);--bd2:rgba(255,255,255,.13);
    --sf:rgba(255,255,255,.04);--sf2:rgba(255,255,255,.07);--sf3:rgba(255,255,255,.10);
    --warn:#f59e0b;--warn2:rgba(245,158,11,.12);
    --err:#ef4444;--err2:rgba(239,68,68,.12);
    --ff:'DM Sans',sans-serif;--fh:'Fraunces',Georgia,serif;--fm:'DM Mono',monospace;
    --sb-w:240px;--top-h:60px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:var(--ff);background:var(--ink);color:var(--tx);-webkit-font-smoothing:antialiased}

/* SIDEBAR */
.sb{position:fixed;top:0;left:0;width:var(--sb-w);height:100vh;background:var(--ink2);border-right:1px solid var(--bd);display:flex;flex-direction:column;overflow-y:auto;z-index:300;transition:transform .32s cubic-bezier(.16,1,.3,1)}
.sb::-webkit-scrollbar{width:3px}.sb::-webkit-scrollbar-thumb{background:rgba(255,255,255,.06);border-radius:2px}
.sb-logo{display:flex;align-items:center;gap:10px;padding:0 18px;height:var(--top-h);border-bottom:1px solid var(--bd);text-decoration:none;flex-shrink:0}
.sb-logo-mark{width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,var(--ac),var(--ac2));display:flex;align-items:center;justify-content:center}
.sb-logo-mark svg{width:17px;height:17px;fill:var(--dk)}
.sb-logo-name{font-size:.875rem;font-weight:800;color:var(--tx);letter-spacing:-.02em}
.sb-logo-sub{font-size:.5625rem;color:var(--tx3);font-weight:600;text-transform:uppercase;letter-spacing:.6px}
.sb-nav{flex:1;padding:10px 0 16px}
.sb-group-label{padding:16px 18px 5px;font-size:.5rem;font-weight:700;color:var(--tx3);text-transform:uppercase;letter-spacing:1.2px}
.sb-link{display:flex;align-items:center;gap:9px;padding:8px 10px;margin:1px 8px;border-radius:9px;text-decoration:none;font-size:.8125rem;font-weight:600;color:var(--tx2);transition:all .16s;position:relative}
.sb-link:hover{background:var(--sf2);color:var(--tx)}
.sb-link.active{background:var(--ac3);color:var(--ac)}
.sb-link.active::before{content:'';position:absolute;left:-10px;top:50%;transform:translateY(-50%);width:3px;height:55%;background:var(--ac);border-radius:0 2px 2px 0}
.sb-ico{width:15px;height:15px;flex-shrink:0;stroke:currentColor;fill:none;stroke-width:1.85;stroke-linecap:round;stroke-linejoin:round}
.sb-foot{margin:8px;padding:10px 12px;background:var(--sf);border:1px solid var(--bd);border-radius:10px;display:flex;align-items:center;gap:9px}
.sb-foot-ava{width:30px;height:30px;border-radius:8px;background:linear-gradient(135deg,var(--ac),var(--ac2));display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.6875rem;font-weight:800;color:var(--dk)}
.sb-foot-name{font-size:.75rem;font-weight:700;color:var(--tx)}
.sb-foot-role{font-size:.5625rem;color:var(--tx3);text-transform:capitalize}
.sb-foot-out{margin-left:auto;padding:5px;background:none;border:none;cursor:pointer;color:var(--tx3);line-height:0;border-radius:6px;transition:all .16s}
.sb-foot-out:hover{background:var(--err2);color:var(--err)}
.sb-foot-out svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:250;opacity:0;transition:opacity .28s;pointer-events:none}
.sb-overlay.show{opacity:1;pointer-events:all}

/* TOPBAR */
.topbar{position:fixed;top:0;left:var(--sb-w);right:0;height:var(--top-h);background:rgba(12,31,29,.92);backdrop-filter:blur(16px);border-bottom:1px solid var(--bd);display:flex;align-items:center;padding:0 28px;gap:12px;z-index:200}
.topbar-ham{display:none;width:34px;height:34px;border-radius:8px;border:1px solid var(--bd);background:var(--sf);align-items:center;justify-content:center;cursor:pointer;flex-direction:column;gap:4px;padding:9px}
.topbar-ham span{display:block;height:1.5px;background:var(--tx2);border-radius:1px;width:100%}
.topbar-title{font-family:var(--fh);font-size:1rem;font-weight:900;color:var(--tx);letter-spacing:-.025em}
.topbar-title a{color:var(--tx3);text-decoration:none;font-style:italic;font-weight:300}
.topbar-title a:hover{color:var(--ac)}
.topbar-spacer{flex:1}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:9px;font-family:var(--ff);font-size:.8125rem;font-weight:700;text-decoration:none;border:1.5px solid transparent;cursor:pointer;transition:all .18s}
.btn svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
.btn-ghost{background:var(--sf);border-color:var(--bd);color:var(--tx2)}
.btn-ghost:hover{background:var(--sf2);color:var(--tx);border-color:var(--bd2)}

/* MAIN */
.main{margin-left:var(--sb-w);margin-top:var(--top-h);display:flex;align-items:center;justify-content:center;min-height:calc(100vh - var(--top-h));padding:40px 24px}

/* CARD */
.card{background:var(--ink2);border:1px solid var(--bd2);border-radius:20px;padding:40px;max-width:520px;width:100%;text-align:center}

/* Action icon */
.action-ico{width:68px;height:68px;border-radius:18px;display:flex;align-items:center;justify-content:center;margin:0 auto 22px;border:1px solid transparent}
.action-ico.green{background:var(--ac3);border-color:rgba(31,226,144,.15)}
.action-ico.green svg{stroke:var(--ac)}
.action-ico.warn{background:var(--warn2);border-color:rgba(245,158,11,.2)}
.action-ico.warn svg{stroke:var(--warn)}
.action-ico svg{width:30px;height:30px;fill:none;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}

.card-title{font-family:var(--fh);font-size:1.5rem;font-weight:900;color:var(--tx);letter-spacing:-.03em;margin-bottom:6px}
.quiz-name{font-size:1rem;font-weight:700;color:var(--tx2);margin-bottom:14px;line-height:1.4}

/* Status flow */
.status-flow{display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:22px;flex-wrap:wrap}
.status-pill{padding:5px 13px;border-radius:50px;font-size:.6875rem;font-weight:700;border:1px solid transparent}
.status-pill.draft    {background:var(--sf2);color:var(--tx3);border-color:var(--bd)}
.status-pill.published{background:var(--ac3);color:var(--ac);border-color:rgba(31,226,144,.2)}
.status-pill.archived {background:var(--warn2);color:var(--warn);border-color:rgba(245,158,11,.2)}
.flow-arrow{color:var(--tx3);font-size:.75rem;display:flex;align-items:center}
.flow-arrow svg{width:14px;height:14px;stroke:var(--ac);fill:none;stroke-width:2;stroke-linecap:round}

/* Meta pills */
.meta-row{display:flex;justify-content:center;gap:10px;margin-bottom:22px;flex-wrap:wrap}
.meta-pill{padding:4px 12px;border-radius:50px;font-size:.6875rem;font-weight:700;background:var(--sf2);border:1px solid var(--bd);color:var(--tx3);font-family:var(--fm)}

/* Warning boxes */
.warn-list{display:flex;flex-direction:column;gap:8px;margin-bottom:22px;text-align:left}
.warn-box{background:var(--warn2);border:1px solid rgba(245,158,11,.2);border-radius:10px;padding:11px 14px;font-size:.8125rem;color:var(--warn);line-height:1.55;display:flex;gap:9px;align-items:flex-start}
.warn-box.err{background:var(--err2);border-color:rgba(239,68,68,.2);color:var(--err)}
.warn-box svg{flex-shrink:0;margin-top:1px;width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round}

/* Error box */
.err-box{background:var(--err2);border:1px solid rgba(239,68,68,.2);border-radius:9px;padding:10px 14px;font-size:.8125rem;color:var(--err);margin-bottom:18px;text-align:left}

/* Actions */
.actions{display:flex;flex-direction:column;gap:10px}
.btn-confirm{width:100%;justify-content:center;padding:11px}
.btn-confirm.green{background:var(--ac);color:var(--dk);border-color:var(--ac)}
.btn-confirm.green:hover{background:var(--ac2);transform:translateY(-1px);box-shadow:0 4px 16px rgba(31,226,144,.25)}
.btn-confirm.green:disabled{opacity:.4;pointer-events:none;transform:none;box-shadow:none}
.btn-confirm.warn{background:var(--warn2);color:var(--warn);border-color:rgba(245,158,11,.25)}
.btn-confirm.warn:hover{background:rgba(245,158,11,.2)}
.cancel-link{font-size:.8125rem;color:var(--tx3);text-decoration:none;transition:color .16s}
.cancel-link:hover{color:var(--tx)}

@media(max-width:768px){
    :root{--sb-w:0px}
    .sb{transform:translateX(-240px);--sb-w:240px}
    .sb.open{transform:translateX(0)}
    .topbar{left:0;padding:0 16px}
    .topbar-ham{display:flex}
    .main{margin-left:0;padding:20px 16px}
    .card{padding:28px 20px}
}
</style>
</head>
<body>

<div class="sb-overlay" id="sbOverlay" onclick="closeSidebar()"></div>

<aside class="sb" id="sidebar">
    <a href="/index.php" class="sb-logo">
        <div class="sb-logo-mark"><svg viewBox="0 0 20 20"><path d="M10 2L13 8H19L14 12.5L16 18.5L10 15L4 18.5L6 12.5L1 8H7L10 2Z"/></svg></div>
        <div><div class="sb-logo-name">Avidmock SAT</div><div class="sb-logo-sub">Admin Panel</div></div>
    </a>
    <nav class="sb-nav">
        <div class="sb-group-label">Overview</div>
        <a href="/index.php" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>Dashboard</a>
        <a href="/analytics/index.php" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>Analytics</a>
        <div class="sb-group-label">Content</div>
        <a href="/quizzes/index.php" class="sb-link active">
            <svg class="sb-ico" viewBox="0 0 24 24"><path d="M9 2H4a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9"/><path d="M13 2l5 5-8 8H5v-5l8-8z"/></svg>
            Quizzes
        </a>
        <a href="/questions/index.php" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3" stroke-linecap="round"/><line x1="12" y1="17" x2="12.01" y2="17" stroke-width="2.5"/></svg>Question Bank</a>
        <a href="/lessons/index.php" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>Lessons</a>
        <a href="/tests/index.php" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>Practice Tests</a>
        <a href="/ebooks/index.php" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>Ebooks</a>
        <div class="sb-group-label">Students</div>
        <a href="/students/index.php" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>All Students</a>
        <a href="/students/at-risk.php" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17" stroke-width="2.5"/></svg>At-Risk</a>
        <div class="sb-group-label">Platform</div>
        <a href="/sessions/index.php" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>Sessions</a>
        <a href="/notifications/index.php" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>Notifications</a>
        <a href="/settings/general.php" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>Settings</a>
    </nav>
    <div class="sb-foot">
        <div class="sb-foot-ava"><?= strtoupper(substr($admin['name'], 0, 1)) ?></div>
        <div>
            <div class="sb-foot-name"><?= htmlspecialchars($admin['name']) ?></div>
            <div class="sb-foot-role"><?= htmlspecialchars($admin['role']) ?></div>
        </div>
        <a href="/auth/logout.php" class="sb-foot-out" title="Sign out">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </a>
    </div>
</aside>

<header class="topbar">
    <button class="topbar-ham" onclick="openSidebar()" aria-label="Menu">
        <span></span><span></span><span></span>
    </button>
    <div class="topbar-title">
        <a href="/quizzes/index.php">Quizzes</a> / <?= htmlspecialchars($actionMeta['label']) ?>
    </div>
    <div class="topbar-spacer"></div>
    <a href="<?= htmlspecialchars($back) ?>" class="btn btn-ghost">
        <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
        Cancel
    </a>
</header>

<main class="main">
    <div class="card">

        <?php
        $color = $actionMeta['color'];
        $iconSvg = match($action) {
            'publish'   => '<path d="M22 2L11 13"/><path d="M22 2L15 22l-4-9-9-4 20-7z"/>',
            'unpublish' => '<path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>',
            'archive'   => '<path d="M21 8v13a2 2 0 01-2 2H5a2 2 0 01-2-2V8"/><rect x="1" y="3" width="22" height="5" rx="1"/><line x1="10" y1="12" x2="14" y2="12"/>',
            default     => '<circle cx="12" cy="12" r="10"/>',
        };
        ?>

        <div class="action-ico <?= $color ?>">
            <svg viewBox="0 0 24 24"><?= $iconSvg ?></svg>
        </div>

        <div class="card-title"><?= htmlspecialchars($actionMeta['label']) ?> quiz</div>
        <div class="quiz-name"><?= htmlspecialchars($quiz['title']) ?></div>

        <!-- Status transition indicator -->
        <div class="status-flow">
            <span class="status-pill <?= $quiz['status'] ?>"><?= ucfirst($quiz['status']) ?></span>
            <span class="flow-arrow">
                <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
            </span>
            <span class="status-pill <?= $newStatus ?>"><?= ucfirst($newStatus) ?></span>
        </div>

        <!-- Meta -->
        <div class="meta-row">
            <span class="meta-pill"><?= $qCount ?> question<?= $qCount !== 1 ? 's' : '' ?></span>
            <span class="meta-pill"><?= htmlspecialchars($quiz['lesson_slug'] ?? '—') ?></span>
            <?php if (!empty($quiz['time_limit'])): ?>
            <span class="meta-pill"><?= floor($quiz['time_limit'] / 60) ?>m <?= $quiz['time_limit'] % 60 ?>s</span>
            <?php endif; ?>
        </div>

        <?php if ($error): ?>
        <div class="err-box"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($warnings)): ?>
        <div class="warn-list">
            <?php foreach ($warnings as $w): ?>
            <div class="warn-box <?= $newStatus === 'published' && $qCount === 0 ? 'err' : '' ?>">
                <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17" stroke-width="2.5"/></svg>
                <span><?= htmlspecialchars($w) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" id="actionForm">
            <input type="hidden" name="id"         value="<?= $quizId ?>">
            <input type="hidden" name="action"     value="<?= htmlspecialchars($action) ?>">
            <input type="hidden" name="back"       value="<?= htmlspecialchars($back) ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <div class="actions">
                <button type="submit" id="confirmBtn"
                        class="btn btn-confirm <?= $color ?>"
                        <?= ($newStatus === 'published' && $qCount === 0) ? 'disabled' : '' ?>>
                    <?php if ($action === 'publish'): ?>
                        <svg viewBox="0 0 24 24"><path d="M22 2L11 13"/><path d="M22 2L15 22l-4-9-9-4 20-7z"/></svg>
                        Publish quiz
                    <?php elseif ($action === 'unpublish'): ?>
                        <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        Move to draft
                    <?php else: ?>
                        <svg viewBox="0 0 24 24"><path d="M21 8v13a2 2 0 01-2 2H5a2 2 0 01-2-2V8"/><rect x="1" y="3" width="22" height="5" rx="1"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
                        Archive quiz
                    <?php endif; ?>
                </button>
                <a href="<?= htmlspecialchars($back) ?>" class="cancel-link">← Cancel, go back</a>
            </div>
        </form>

    </div>
</main>

<script>
// Prevent double-submit
document.getElementById('actionForm').addEventListener('submit', function() {
    const btn = document.getElementById('confirmBtn');
    btn.disabled = true;
    btn.textContent = 'Working…';
});

function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('sbOverlay').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sbOverlay').classList.remove('show');
    document.body.style.overflow = '';
}
window.addEventListener('resize', () => { if (window.innerWidth > 768) closeSidebar(); });
</script>
</body>
</html>