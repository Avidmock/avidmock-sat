<?php
/**
 * quizzes/delete.php
 * Archive or permanently delete a quiz.
 * Supports both GET (confirmation page) and POST (execute deletion).
 * ?id= required.
 */
require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

$admin  = currentAdmin();
$db     = Database::connect();
$quizId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if (!$quizId) {
    header('Location: /quizzes/index.php');
    exit;
}

// ── Load quiz ─────────────────────────────────────────────────────────────
$quizStmt = $db->prepare("SELECT * FROM sat_quizzes WHERE id = :id");
$quizStmt->execute([':id' => $quizId]);
$quiz = $quizStmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    header('Location: /quizzes/index.php');
    exit;
}

// ── Counts ────────────────────────────────────────────────────────────────
$allTables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

$attCount = 0;
if (in_array('sat_quiz_attempts', $allTables)) {
    try {
        $aStmt = $db->prepare("SELECT COUNT(*) FROM sat_quiz_attempts WHERE quiz_id = :id");
        $aStmt->execute([':id' => $quizId]);
        $attCount = (int)$aStmt->fetchColumn();
    } catch (Throwable) {}
}

$qcStmt = $db->prepare("SELECT COUNT(*) FROM sat_quiz_questions WHERE quiz_id = :id");
$qcStmt->execute([':id' => $quizId]);
$qCount = (int)$qcStmt->fetchColumn();

// ── CSRF token ────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// ── Handle POST ───────────────────────────────────────────────────────────
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token. Please try again.';
    } else {
        $type = $_POST['type'] ?? 'soft';
        try {
            $db->beginTransaction();

            if ($type === 'hard') {
                // Delete in dependency order: answers → attempts → questions → quiz
                if (in_array('sat_quiz_answers', $allTables)) {
                    $db->prepare(
                        "DELETE ans FROM sat_quiz_answers ans
                         JOIN sat_quiz_questions q ON q.id = ans.question_id
                         WHERE q.quiz_id = :id"
                    )->execute([':id' => $quizId]);
                }
                if (in_array('sat_quiz_attempts', $allTables)) {
                    $db->prepare(
                        "DELETE FROM sat_quiz_attempts WHERE quiz_id = :id"
                    )->execute([':id' => $quizId]);
                }
                $db->prepare(
                    "DELETE FROM sat_quiz_questions WHERE quiz_id = :id"
                )->execute([':id' => $quizId]);
                $db->prepare(
                    "DELETE FROM sat_quizzes WHERE id = :id"
                )->execute([':id' => $quizId]);

            } else {
                // Soft delete — archive only
                $db->prepare(
                    "UPDATE sat_quizzes SET status = 'archived', updated_at = NOW() WHERE id = :id"
                )->execute([':id' => $quizId]);
            }

            $db->commit();
            $_SESSION['flash_success'] = $type === 'hard' ? 'Quiz permanently deleted.' : 'Quiz archived successfully.';
            header('Location: /quizzes/index.php');
            exit;

        } catch (Throwable $e) {
            $db->rollBack();
            $error = 'Something went wrong: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Delete: <?= htmlspecialchars($quiz['title']) ?> — Avidmock Admin</title>
<meta name="robots" content="noindex,nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,600;9..40,700;9..40,800&family=Fraunces:ital,opsz,wght@0,9..144,900;1,9..144,300&family=DM+Mono:wght@400&display=swap" rel="stylesheet">
<style>
:root{
    --ink:#0c1f1d;--ink2:#0e2522;--dk:#143230;
    --ac:#1fe290;--ac2:#13c474;--ac3:rgba(31,226,144,.08);
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
.card{background:var(--ink2);border:1px solid var(--bd2);border-radius:20px;padding:40px;max-width:540px;width:100%;text-align:center}
.del-ico{width:68px;height:68px;border-radius:18px;background:var(--err2);border:1px solid rgba(239,68,68,.2);display:flex;align-items:center;justify-content:center;margin:0 auto 22px}
.del-ico svg{width:30px;height:30px;stroke:var(--err);fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.card-title{font-family:var(--fh);font-size:1.5rem;font-weight:900;color:var(--tx);letter-spacing:-.03em;margin-bottom:6px}
.quiz-name{font-size:1rem;font-weight:700;color:var(--err);margin-bottom:14px;line-height:1.4}
.meta-row{display:flex;justify-content:center;gap:10px;margin-bottom:24px;flex-wrap:wrap}
.meta-pill{padding:4px 12px;border-radius:50px;font-size:.6875rem;font-weight:700;background:var(--sf2);border:1px solid var(--bd);color:var(--tx3);font-family:var(--fm)}

/* Warning box */
.warn-box{background:var(--err2);border:1px solid rgba(239,68,68,.2);border-radius:10px;padding:12px 16px;font-size:.8125rem;color:var(--err);margin-bottom:22px;line-height:1.6;text-align:left;display:flex;gap:10px;align-items:flex-start}
.warn-box svg{flex-shrink:0;margin-top:1px;width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round}

/* Error box */
.err-box{background:var(--err2);border:1px solid rgba(239,68,68,.2);border-radius:9px;padding:10px 14px;font-size:.8125rem;color:var(--err);margin-bottom:18px;text-align:left}

/* Option cards */
.options{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px}
.opt-card{padding:18px;border-radius:12px;border:1.5px solid var(--bd);background:var(--sf);text-align:left;width:100%;font-family:var(--ff);cursor:pointer;transition:all .18s;position:relative}
.opt-card:focus-visible{outline:2px solid var(--ac);outline-offset:2px}
.opt-card.soft:hover,.opt-card.soft:focus-visible{border-color:rgba(245,158,11,.35);background:var(--warn2)}
.opt-card.hard:hover,.opt-card.hard:focus-visible{border-color:rgba(239,68,68,.35);background:var(--err2)}
.opt-ico{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;margin-bottom:10px}
.opt-ico svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round}
.opt-ico.warn{background:var(--warn2);color:var(--warn)}
.opt-ico.err{background:var(--err2);color:var(--err)}
.opt-title{font-size:.875rem;font-weight:700;color:var(--tx);margin-bottom:4px}
.opt-desc{font-size:.5625rem;color:var(--tx3);line-height:1.55}

/* Cancel link */
.cancel-link{font-size:.8125rem;color:var(--tx3);text-decoration:none;transition:color .16s}
.cancel-link:hover{color:var(--tx)}

/* Confirm overlay */
.confirm-overlay{position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(6px);z-index:400;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .22s}
.confirm-overlay.show{opacity:1;pointer-events:all}
.confirm-modal{background:var(--ink2);border:1px solid var(--bd2);border-radius:16px;padding:28px;max-width:380px;width:100%;transform:translateY(14px) scale(.97);transition:transform .22s cubic-bezier(.16,1,.3,1)}
.confirm-overlay.show .confirm-modal{transform:none}
.confirm-title{font-family:var(--fh);font-size:1.125rem;font-weight:900;color:var(--tx);margin-bottom:8px;letter-spacing:-.02em}
.confirm-body{font-size:.875rem;color:var(--tx2);line-height:1.6;margin-bottom:22px}
.confirm-body strong{color:var(--err)}
.confirm-foot{display:flex;gap:8px;justify-content:flex-end}
.btn-danger{background:var(--err2);color:var(--err);border-color:rgba(239,68,68,.2)}
.btn-danger:hover{background:rgba(239,68,68,.2)}

/* Responsive */
@media(max-width:768px){
    :root{--sb-w:0px}
    .sb{transform:translateX(-240px);--sb-w:240px}
    .sb.open{transform:translateX(0)}
    .topbar{left:0;padding:0 16px}
    .topbar-ham{display:flex}
    .main{margin-left:0}
    .options{grid-template-columns:1fr}
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
        <a href="/quizzes/index.php">Quizzes</a> / Delete
    </div>
    <div class="topbar-spacer"></div>
    <a href="/quizzes/edit.php?id=<?= $quizId ?>" class="btn btn-ghost">
        <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
        Cancel
    </a>
</header>

<main class="main">
    <div class="card">

        <div class="del-ico">
            <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
        </div>

        <div class="card-title">Delete this quiz?</div>
        <div class="quiz-name"><?= htmlspecialchars($quiz['title']) ?></div>

        <?php if ($error): ?>
        <div class="err-box"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="meta-row">
            <span class="meta-pill"><?= $qCount ?> question<?= $qCount !== 1 ? 's' : '' ?></span>
            <span class="meta-pill"><?= number_format($attCount) ?> attempt<?= $attCount !== 1 ? 's' : '' ?></span>
            <span class="meta-pill">Status: <?= htmlspecialchars($quiz['status']) ?></span>
        </div>

        <?php if ($attCount > 0): ?>
        <div class="warn-box">
            <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17" stroke-width="2.5"/></svg>
            <span>
                <strong><?= number_format($attCount) ?> student<?= $attCount !== 1 ? 's have' : ' has' ?></strong>
                already attempted this quiz. Hard-deleting will permanently remove all their answer records.
                Consider archiving instead.
            </span>
        </div>
        <?php endif; ?>

        <div class="options">
            <!-- ARCHIVE -->
            <form method="POST">
                <input type="hidden" name="id" value="<?= $quizId ?>">
                <input type="hidden" name="type" value="soft">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <button type="submit" class="opt-card soft">
                    <div class="opt-ico warn">
                        <svg viewBox="0 0 24 24"><path d="M21 8v13a2 2 0 01-2 2H5a2 2 0 01-2-2V8"/><rect x="1" y="3" width="22" height="5" rx="1"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
                    </div>
                    <div class="opt-title">Archive</div>
                    <div class="opt-desc">Hidden from students but all data is preserved. Fully reversible at any time.</div>
                </button>
            </form>

            <!-- HARD DELETE -->
            <form method="POST" id="hardDeleteForm">
                <input type="hidden" name="id" value="<?= $quizId ?>">
                <input type="hidden" name="type" value="hard">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <button type="button" class="opt-card hard" onclick="showConfirm()">
                    <div class="opt-ico err">
                        <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
                    </div>
                    <div class="opt-title">Delete Forever</div>
                    <div class="opt-desc">
                        Permanently removes the quiz, all <?= $qCount ?> question<?= $qCount !== 1 ? 's' : '' ?><?= $attCount > 0 ? ', and ' . number_format($attCount) . ' attempt record' . ($attCount !== 1 ? 's' : '') : '' ?>.
                        Irreversible.
                    </div>
                </button>
            </form>
        </div>

        <a href="/quizzes/edit.php?id=<?= $quizId ?>" class="cancel-link">← Cancel, keep this quiz</a>
    </div>
</main>

<!-- Hard-delete confirmation modal -->
<div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-modal">
        <div class="confirm-title">Are you absolutely sure?</div>
        <div class="confirm-body">
            You are about to permanently delete
            <strong>"<?= htmlspecialchars($quiz['title']) ?>"</strong>
            <?php if ($attCount > 0): ?>
            along with <strong><?= number_format($attCount) ?> student attempt<?= $attCount !== 1 ? 's' : '' ?></strong>
            <?php endif; ?>
            and all <?= $qCount ?> question<?= $qCount !== 1 ? 's' : '' ?>.
            This action <strong>cannot be undone</strong>.
        </div>
        <div class="confirm-foot">
            <button class="btn btn-ghost" onclick="closeConfirm()">Cancel</button>
            <button class="btn btn-danger" onclick="submitHardDelete()">
                <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
                Yes, delete forever
            </button>
        </div>
    </div>
</div>

<script>
function showConfirm() {
    document.getElementById('confirmOverlay').classList.add('show');
}
function closeConfirm() {
    document.getElementById('confirmOverlay').classList.remove('show');
}
function submitHardDelete() {
    document.getElementById('hardDeleteForm').submit();
}

// Close modal on backdrop click
document.getElementById('confirmOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeConfirm();
});

// Close modal on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeConfirm();
});

// Sidebar
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
window.addEventListener('resize', () => {
    if (window.innerWidth > 768) closeSidebar();
});
</script>
</body>
</html>