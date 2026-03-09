<?php
/**
 * /admin/practice-tests/create.php
 * Create a new practice test shell — title, type, description, publish status.
 * After creating, admin is redirected to edit.php to add sections + questions.
 */
require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

$admin = currentAdmin();
$db    = Database::connect();

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrfToken = $_SESSION['csrf_token'];

/* ── Handle POST ─────────────────────────────────────────────────────────── */
$errors = [];
$vals   = [
    'title'        => '',
    'description'  => '',
    'instructions' => '',
    'type'         => 'full_length',
    'section'      => 'full',
    'total_time'   => 8040,
    'is_published' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $vals['title']        = trim($_POST['title']        ?? '');
        $vals['description']  = trim($_POST['description']  ?? '');
        $vals['instructions'] = trim($_POST['instructions'] ?? '');
        $vals['type']         = $_POST['type']    ?? 'full_length';
        $vals['section']      = $_POST['section'] ?? 'full';
        $vals['is_published'] = isset($_POST['is_published']) ? 1 : 0;

        /* Auto-set total_time based on type */
        $typeTimings = [
            'full_length' => 8040,  // 134 min = 2x32 + 2x35
            'mini'        => 2400,  // 40 min
            'topic'       => 1800,  // 30 min
            'timed'       => 3600,  // 60 min
        ];
        $vals['total_time'] = (int)($_POST['total_time'] ?? $typeTimings[$vals['type']] ?? 8040);

        /* Validation */
        if ($vals['title'] === '') $errors[] = 'Test title is required.';
        if (mb_strlen($vals['title']) > 255) $errors[] = 'Title is too long (max 255 characters).';
        if (!in_array($vals['type'], ['full_length','mini','topic','timed'])) $errors[] = 'Invalid test type.';
        if (!in_array($vals['section'], ['full','math','reading_writing'])) $errors[] = 'Invalid section.';

        if (empty($errors)) {
            try {
                $db->prepare(
                    "INSERT INTO practice_tests
                     (title, description, instructions, type, section, total_time, is_published, created_at, updated_at)
                     VALUES (?,?,?,?,?,?,?,NOW(),NOW())"
                )->execute([
                    $vals['title'],
                    $vals['description'],
                    $vals['instructions'],
                    $vals['type'],
                    $vals['section'],
                    $vals['total_time'],
                    $vals['is_published'],
                ]);
                $newId = (int)$db->lastInsertId();

                /* Auto-create 4 standard sections for full-length tests */
                if ($vals['type'] === 'full_length' && $vals['section'] === 'full') {
                    $sections = [
                        ['Reading & Writing — Module 1', 'reading_writing', 1, 1920, 1],
                        ['Reading & Writing — Module 2', 'reading_writing', 2, 1920, 2],
                        ['Math — Module 1',              'math',            1, 2100, 3],
                        ['Math — Module 2',              'math',            2, 2100, 4],
                    ];
                    $sStmt = $db->prepare(
                        "INSERT INTO practice_test_sections (test_id, title, subject, module, time_limit, sort_order)
                         VALUES (?,?,?,?,?,?)"
                    );
                    foreach ($sections as $s) {
                        $sStmt->execute([$newId, $s[0], $s[1], $s[2], $s[3], $s[4]]);
                    }
                }

                $_SESSION['flash'] = 'ok:Test "' . htmlspecialchars($vals['title']) . '" created. Now add questions to each section.';
                header('Location: /admin/practice-tests/edit.php?id=' . $newId);
                exit;

            } catch (Throwable $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>New Practice Test — Avidmock Admin</title>
<meta name="robots" content="noindex,nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,600;9..40,700;9..40,800&family=Fraunces:ital,opsz,wght@0,9..144,900;1,9..144,300&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ─────────────────────────────────────────────
   TOKENS
───────────────────────────────────────────── */
:root {
    --ink:#0c1f1d; --ink2:#0e2522; --ink3:#112623;
    --dk:#143230;
    --ac:#1fe290; --ac2:#13c474;
    --ac3:rgba(31,226,144,.08); --ac4:rgba(31,226,144,.15);
    --tx:#e8f3f1; --tx2:#9dbfba; --tx3:#5a8580;
    --bd:rgba(255,255,255,.07); --bd2:rgba(255,255,255,.13);
    --sf:rgba(255,255,255,.04); --sf2:rgba(255,255,255,.07);
    --warn:#f59e0b; --warn2:rgba(245,158,11,.12);
    --err:#ef4444;  --err2:rgba(239,68,68,.12);
    --blue:#3b82f6; --blue2:rgba(59,130,246,.12);
    --purple:#8b5cf6; --purple2:rgba(139,92,246,.12);
    --ff:'DM Sans',sans-serif;
    --fh:'Fraunces',Georgia,serif;
    --fm:'DM Mono',monospace;
    --sb-w:240px; --top-h:60px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:var(--ff);background:var(--ink);color:var(--tx);-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit}
button{font-family:var(--ff);cursor:pointer}

/* ─────────────────────────────────────────────
   SIDEBAR
───────────────────────────────────────────── */
.sb{position:fixed;top:0;left:0;width:var(--sb-w);height:100vh;background:var(--ink2);border-right:1px solid var(--bd);display:flex;flex-direction:column;overflow-y:auto;z-index:300;transition:transform .32s cubic-bezier(.16,1,.3,1)}
.sb::-webkit-scrollbar{width:3px}.sb::-webkit-scrollbar-thumb{background:rgba(255,255,255,.06)}
.sb-logo{display:flex;align-items:center;gap:10px;padding:0 18px;height:var(--top-h);border-bottom:1px solid var(--bd);text-decoration:none;flex-shrink:0}
.sb-logo-mark{width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,var(--ac),var(--ac2));display:flex;align-items:center;justify-content:center}
.sb-logo-mark svg{width:17px;height:17px;fill:var(--dk)}
.sb-logo-name{font-size:.875rem;font-weight:800;color:var(--tx);letter-spacing:-.02em}
.sb-logo-sub{font-size:.5625rem;color:var(--tx3);font-weight:600;text-transform:uppercase;letter-spacing:.6px}
.sb-nav{flex:1;padding:10px 0 16px}
.sb-group{padding:16px 18px 5px;font-size:.5rem;font-weight:700;color:var(--tx3);text-transform:uppercase;letter-spacing:1.2px}
.sb-link{display:flex;align-items:center;gap:9px;padding:8px 10px;margin:1px 8px;border-radius:9px;font-size:.8125rem;font-weight:600;color:var(--tx2);transition:all .16s;position:relative}
.sb-link:hover{background:var(--sf2);color:var(--tx)}
.sb-link.active{background:var(--ac3);color:var(--ac)}
.sb-link.active::before{content:'';position:absolute;left:-10px;top:50%;transform:translateY(-50%);width:3px;height:55%;background:var(--ac);border-radius:0 2px 2px 0}
.sb-ico{width:15px;height:15px;flex-shrink:0;stroke:currentColor;fill:none;stroke-width:1.85;stroke-linecap:round;stroke-linejoin:round}
.sb-foot{margin:8px;padding:10px 12px;background:var(--sf);border:1px solid var(--bd);border-radius:10px;display:flex;align-items:center;gap:9px}
.sb-ava{width:30px;height:30px;border-radius:8px;background:linear-gradient(135deg,var(--ac),var(--ac2));display:flex;align-items:center;justify-content:center;font-size:.6875rem;font-weight:800;color:var(--dk);flex-shrink:0}
.sb-foot-name{font-size:.75rem;font-weight:700;color:var(--tx)}.sb-foot-role{font-size:.5625rem;color:var(--tx3);text-transform:capitalize}
.sb-out{margin-left:auto;padding:5px;background:none;border:none;cursor:pointer;color:var(--tx3);line-height:0;border-radius:6px;transition:all .16s}
.sb-out:hover{background:var(--err2);color:var(--err)}
.sb-out svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:250;opacity:0;transition:opacity .28s;pointer-events:none}
.sb-overlay.show{opacity:1;pointer-events:all}

/* ─────────────────────────────────────────────
   TOPBAR
───────────────────────────────────────────── */
.topbar{position:fixed;top:0;left:var(--sb-w);right:0;height:var(--top-h);background:rgba(12,31,29,.92);backdrop-filter:blur(16px);border-bottom:1px solid var(--bd);display:flex;align-items:center;padding:0 28px;gap:10px;z-index:200}
.topbar-ham{display:none;width:34px;height:34px;border-radius:8px;border:1px solid var(--bd);background:var(--sf);align-items:center;justify-content:center;cursor:pointer;flex-direction:column;gap:4px;padding:9px}
.topbar-ham span{display:block;height:1.5px;background:var(--tx2);border-radius:1px;width:100%}
.tb-breadcrumb{display:flex;align-items:center;gap:6px;font-size:.8125rem;font-weight:600;color:var(--tx3)}
.tb-breadcrumb a{color:var(--tx3);transition:color .15s;font-family:var(--fh);font-style:italic;font-weight:300}
.tb-breadcrumb a:hover{color:var(--ac)}
.tb-breadcrumb-sep{opacity:.3;font-size:.75rem}
.tb-breadcrumb-cur{color:var(--tx)}
.topbar-spacer{flex:1}

/* ─────────────────────────────────────────────
   BUTTONS
───────────────────────────────────────────── */
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:9px;font-family:var(--ff);font-size:.8125rem;font-weight:700;border:1.5px solid transparent;cursor:pointer;transition:all .18s;white-space:nowrap}
.btn svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
.btn-primary{background:var(--ac);color:var(--dk);border-color:var(--ac)}
.btn-primary:hover{background:var(--ac2);transform:translateY(-1px);box-shadow:0 4px 14px rgba(31,226,144,.25)}
.btn-ghost{background:var(--sf);border-color:var(--bd);color:var(--tx2)}
.btn-ghost:hover{background:var(--sf2);color:var(--tx);border-color:var(--bd2)}

/* ─────────────────────────────────────────────
   MAIN LAYOUT
───────────────────────────────────────────── */
.main{margin-left:var(--sb-w);margin-top:var(--top-h);padding:28px}
.editor-layout{display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;max-width:1100px}
.editor-main{display:flex;flex-direction:column;gap:16px}
.editor-side{display:flex;flex-direction:column;gap:14px;position:sticky;top:calc(var(--top-h) + 20px)}

/* ─────────────────────────────────────────────
   CARDS
───────────────────────────────────────────── */
.card{background:var(--ink2);border:1px solid var(--bd);border-radius:16px;overflow:hidden}
.card-head{padding:16px 20px;border-bottom:1px solid var(--bd);display:flex;align-items:center;gap:10px}
.card-head-ico{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.card-head-ico svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.card-title{font-size:.875rem;font-weight:700;color:var(--tx)}
.card-sub{font-size:.625rem;color:var(--tx3);margin-top:1px}
.card-body{padding:20px;display:flex;flex-direction:column;gap:16px}

/* ─────────────────────────────────────────────
   FORM FIELDS
───────────────────────────────────────────── */
.field{display:flex;flex-direction:column;gap:5px}
.field-row{display:grid;gap:12px}
.fr-2{grid-template-columns:1fr 1fr}
.fr-3{grid-template-columns:1fr 1fr 1fr}
label.fl{font-size:.5625rem;font-weight:700;color:var(--tx3);text-transform:uppercase;letter-spacing:.8px}
.hint{font-size:.5625rem;color:var(--tx3);margin-top:2px}
.fi,.fs,.fta{background:var(--sf);border:1.5px solid var(--bd);border-radius:9px;color:var(--tx);font-family:var(--ff);font-size:.8125rem;padding:9px 12px;outline:none;transition:border-color .16s,background .16s;width:100%}
.fi:focus,.fs:focus,.fta:focus{border-color:var(--ac);background:rgba(31,226,144,.03)}
.fi.err,.fs.err{border-color:var(--err)}
.fs option{background:var(--ink2)}
.fta{resize:vertical;min-height:80px;line-height:1.55}
.char-count{font-family:var(--fm);font-size:.5rem;color:var(--tx3);text-align:right;margin-top:2px}
.char-count.warn{color:var(--warn)}.char-count.over{color:var(--err)}

/* ─────────────────────────────────────────────
   TYPE PICKER (visual card grid)
───────────────────────────────────────────── */
.type-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.type-opt input{position:absolute;opacity:0;width:0;height:0}
.type-opt label{display:flex;flex-direction:column;gap:6px;padding:12px 14px;border-radius:10px;border:1.5px solid var(--bd);cursor:pointer;transition:all .18s;background:var(--sf)}
.type-opt label:hover{border-color:var(--bd2);background:var(--sf2)}
.type-opt input:checked + label{border-color:var(--ac);background:var(--ac3)}
.type-opt-icon{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center}
.type-opt-icon svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.type-opt-name{font-size:.75rem;font-weight:700;color:var(--tx)}
.type-opt-desc{font-size:.5625rem;color:var(--tx3)}
.type-opt input:checked + label .type-opt-name{color:var(--ac)}

/* ─────────────────────────────────────────────
   PUBLISH TOGGLE
───────────────────────────────────────────── */
.pub-toggle{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border-radius:10px;border:1.5px solid var(--bd);background:var(--sf);cursor:pointer;transition:all .18s}
.pub-toggle:has(input:checked){border-color:var(--ac);background:var(--ac3)}
.pub-toggle input{position:absolute;opacity:0;width:0;height:0}
.pub-toggle-left{display:flex;align-items:center;gap:10px}
.pub-toggle-dot{width:8px;height:8px;border-radius:50%;background:var(--tx3);transition:background .2s}
.pub-toggle:has(input:checked) .pub-toggle-dot{background:var(--ac)}
.pub-toggle-text{font-size:.8125rem;font-weight:700;color:var(--tx)}
.pub-toggle-sub{font-size:.5625rem;color:var(--tx3);margin-top:1px}
.pub-switch{width:34px;height:18px;border-radius:9px;background:var(--sf2);border:1.5px solid var(--bd);position:relative;transition:all .2s;flex-shrink:0}
.pub-switch::after{content:'';position:absolute;left:2px;top:50%;transform:translateY(-50%);width:10px;height:10px;border-radius:50%;background:var(--tx3);transition:all .2s}
.pub-toggle:has(input:checked) .pub-switch{background:rgba(31,226,144,.2);border-color:var(--ac)}
.pub-toggle:has(input:checked) .pub-switch::after{left:calc(100% - 12px);background:var(--ac)}

/* ─────────────────────────────────────────────
   TIME PRESETS
───────────────────────────────────────────── */
.time-presets{display:flex;gap:5px;flex-wrap:wrap;margin-top:4px}
.preset-btn{padding:3px 9px;border-radius:6px;border:1px solid var(--bd);background:var(--sf);color:var(--tx3);font-family:var(--fm);font-size:.5625rem;cursor:pointer;transition:all .15s}
.preset-btn:hover{border-color:var(--bd2);color:var(--tx)}
.preset-btn.active{border-color:var(--ac);background:var(--ac3);color:var(--ac)}

/* ─────────────────────────────────────────────
   SAT FORMAT GUIDE
───────────────────────────────────────────── */
.sat-guide{background:var(--sf);border-radius:12px;padding:14px}
.sat-guide-title{font-size:.6875rem;font-weight:700;color:var(--tx);margin-bottom:10px}
.sat-module{display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid var(--bd)}
.sat-module:last-child{border-bottom:none;padding-bottom:0}
.sat-mod-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0}
.sat-mod-label{font-size:.6875rem;font-weight:600;color:var(--tx2);flex:1}
.sat-mod-time{font-family:var(--fm);font-size:.5625rem;color:var(--tx3)}
.sat-mod-q{font-family:var(--fm);font-size:.5625rem;color:var(--tx3);min-width:36px;text-align:right}
.sat-break{display:flex;align-items:center;gap:8px;padding:5px 0;opacity:.5}
.sat-break-line{flex:1;height:1px;background:var(--bd)}
.sat-break-lbl{font-size:.5625rem;color:var(--tx3);white-space:nowrap}

/* ─────────────────────────────────────────────
   SUMMARY CARD
───────────────────────────────────────────── */
.summary-row{display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--bd)}
.summary-row:last-child{border-bottom:none;padding-bottom:0}
.summary-key{font-size:.6875rem;color:var(--tx3);font-weight:600}
.summary-val{font-size:.6875rem;font-weight:700;color:var(--tx);font-family:var(--fm)}

/* ─────────────────────────────────────────────
   ERRORS
───────────────────────────────────────────── */
.errors-box{background:var(--err2);border:1px solid rgba(239,68,68,.25);border-radius:10px;padding:14px 16px;margin-bottom:16px;display:flex;flex-direction:column;gap:4px}
.errors-box li{font-size:.8125rem;color:var(--err)}

/* ─────────────────────────────────────────────
   REVEAL
───────────────────────────────────────────── */
.reveal{opacity:0;transform:translateY(12px);animation:rev .4s cubic-bezier(.16,1,.3,1) forwards}
@keyframes rev{to{opacity:1;transform:none}}
.d1{animation-delay:.04s}.d2{animation-delay:.08s}.d3{animation-delay:.12s}.d4{animation-delay:.16s}

/* ─────────────────────────────────────────────
   RESPONSIVE
───────────────────────────────────────────── */
@media(max-width:1000px){
    .editor-layout{grid-template-columns:1fr}
    .editor-side{position:static}
}
@media(max-width:768px){
    :root{--sb-w:0px}
    .sb{transform:translateX(-240px);--sb-w:240px}
    .sb.open{transform:translateX(0)}
    .sb-overlay{display:block}
    .topbar{left:0;padding:0 16px}
    .topbar-ham{display:flex}
    .main{margin-left:0;padding:16px}
    .fr-2,.fr-3{grid-template-columns:1fr}
    .type-grid{grid-template-columns:1fr 1fr}
}
</style>
</head>
<body>

<div class="sb-overlay" id="sbOverlay"></div>

<!-- ── SIDEBAR ──────────────────────────────────────────────────────── -->
<aside class="sb" id="sidebar">
    <a href="/admin/" class="sb-logo">
        <div class="sb-logo-mark">
            <svg viewBox="0 0 20 20"><path d="M10 2L13 8H19L14 12.5L16 18.5L10 15L4 18.5L6 12.5L1 8H7L10 2Z"/></svg>
        </div>
        <div>
            <div class="sb-logo-name">Avidmock SAT</div>
            <div class="sb-logo-sub">Admin Panel</div>
        </div>
    </a>
    <nav class="sb-nav">
        <div class="sb-group">Overview</div>
        <a href="/admin/" class="sb-link">
            <svg class="sb-ico" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            Dashboard
        </a>
        <a href="/admin/analytics/" class="sb-link">
            <svg class="sb-ico" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            Analytics
        </a>
        <div class="sb-group">Content</div>
        <a href="/admin/questions/" class="sb-link">
            <svg class="sb-ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            Question Bank
        </a>
        <a href="/admin/practice-tests/" class="sb-link active">
            <svg class="sb-ico" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            Practice Tests
        </a>
        <a href="/admin/quizzes/" class="sb-link">
            <svg class="sb-ico" viewBox="0 0 24 24"><path d="M9 2H4a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9"/><path d="M13 2l5 5-8 8H5v-5l8-8z"/></svg>
            Quizzes
        </a>
        <div class="sb-group">Students</div>
        <a href="/admin/students/" class="sb-link">
            <svg class="sb-ico" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            All Students
        </a>
    </nav>
    <div class="sb-foot">
        <div class="sb-ava"><?= strtoupper(substr($admin['name'] ?? 'A', 0, 1)) ?></div>
        <div>
            <div class="sb-foot-name"><?= htmlspecialchars($admin['name'] ?? 'Admin') ?></div>
            <div class="sb-foot-role"><?= htmlspecialchars($admin['role'] ?? 'admin') ?></div>
        </div>
        <a href="/admin/auth/logout.php" class="sb-out">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </a>
    </div>
</aside>

<!-- ── TOPBAR ───────────────────────────────────────────────────────── -->
<header class="topbar">
    <button class="topbar-ham" onclick="openSidebar()"><span></span><span></span><span></span></button>
    <div class="tb-breadcrumb">
        <a href="/admin/practice-tests/">Practice Tests</a>
        <span class="tb-breadcrumb-sep">›</span>
        <span class="tb-breadcrumb-cur">New Test</span>
    </div>
    <div class="topbar-spacer"></div>
    <a href="/admin/practice-tests/" class="btn btn-ghost">
        <svg viewBox="0 0 24 24"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
        Cancel
    </a>
</header>

<!-- ── MAIN ─────────────────────────────────────────────────────────── -->
<main class="main">

    <div class="reveal d1" style="margin-bottom:24px">
        <h1 style="font-family:var(--fh);font-size:1.5rem;font-weight:900;color:var(--tx);letter-spacing:-.03em;margin-bottom:4px">
            New Practice Test
        </h1>
        <p style="font-size:.8125rem;color:var(--tx3)">
            Create the test shell — you'll add sections and questions in the next step.
        </p>
    </div>

    <?php if (!empty($errors)): ?>
    <ul class="errors-box reveal d1">
        <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach ?>
    </ul>
    <?php endif ?>

    <form method="POST" id="mainForm" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="hidden" name="total_time" id="totalTimeInput" value="<?= (int)$vals['total_time'] ?>">

    <div class="editor-layout">

        <!-- ── LEFT ─────────────────────────────────────────── -->
        <div class="editor-main">

            <!-- Core Details -->
            <div class="card reveal d2">
                <div class="card-head">
                    <div class="card-head-ico" style="background:var(--ac3);color:var(--ac)">
                        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </div>
                    <div>
                        <div class="card-title">Test Details</div>
                        <div class="card-sub">Title, description, and instructions for students</div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="field">
                        <label class="fl" for="title">Title *</label>
                        <input type="text" id="title" name="title" class="fi <?= in_array('Test title is required.', $errors) ? 'err' : '' ?>"
                               value="<?= htmlspecialchars($vals['title']) ?>"
                               placeholder="e.g. SAT Practice Test 1 — Full Length"
                               maxlength="255" required
                               oninput="updateCharCount(this, 255, 'titleCount')">
                        <div class="char-count" id="titleCount"><?= mb_strlen($vals['title']) ?> / 255</div>
                    </div>

                    <div class="field">
                        <label class="fl" for="description">Description</label>
                        <textarea id="description" name="description" class="fta"
                                  placeholder="Brief overview shown to students on the test library page…"
                                  maxlength="1000"
                                  oninput="updateCharCount(this, 1000, 'descCount')"><?= htmlspecialchars($vals['description']) ?></textarea>
                        <div class="char-count" id="descCount"><?= mb_strlen($vals['description']) ?> / 1000</div>
                    </div>

                    <div class="field">
                        <label class="fl" for="instructions">Instructions (optional)</label>
                        <textarea id="instructions" name="instructions" class="fta" style="min-height:64px"
                                  placeholder="Special notes shown to students before the test starts…"><?= htmlspecialchars($vals['instructions']) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Test Type -->
            <div class="card reveal d3">
                <div class="card-head">
                    <div class="card-head-ico" style="background:var(--blue2);color:var(--blue)">
                        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </div>
                    <div>
                        <div class="card-title">Test Type &amp; Format</div>
                        <div class="card-sub">Choose the format — this controls the sections auto-created</div>
                    </div>
                </div>
                <div class="card-body">

                    <!-- Type picker -->
                    <div class="field">
                        <label class="fl">Test Type</label>
                        <div class="type-grid" id="typeGrid">
                            <div class="type-opt">
                                <input type="radio" name="type" id="t_full" value="full_length" <?= $vals['type']==='full_length'?'checked':'' ?> onchange="onTypeChange('full_length')">
                                <label for="t_full">
                                    <div class="type-opt-icon" style="background:var(--ac3);color:var(--ac)">
                                        <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                                    </div>
                                    <div class="type-opt-name">Full Length</div>
                                    <div class="type-opt-desc">4 modules · 98 questions · 2h 14m</div>
                                </label>
                            </div>
                            <div class="type-opt">
                                <input type="radio" name="type" id="t_mini" value="mini" <?= $vals['type']==='mini'?'checked':'' ?> onchange="onTypeChange('mini')">
                                <label for="t_mini">
                                    <div class="type-opt-icon" style="background:var(--warn2);color:var(--warn)">
                                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    </div>
                                    <div class="type-opt-name">Mini Test</div>
                                    <div class="type-opt-desc">Shorter format · ~40 min</div>
                                </label>
                            </div>
                            <div class="type-opt">
                                <input type="radio" name="type" id="t_topic" value="topic" <?= $vals['type']==='topic'?'checked':'' ?> onchange="onTypeChange('topic')">
                                <label for="t_topic">
                                    <div class="type-opt-icon" style="background:var(--purple2);color:var(--purple)">
                                        <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
                                    </div>
                                    <div class="type-opt-name">Topic Drill</div>
                                    <div class="type-opt-desc">Focus on one skill area · ~30 min</div>
                                </label>
                            </div>
                            <div class="type-opt">
                                <input type="radio" name="type" id="t_timed" value="timed" <?= $vals['type']==='timed'?'checked':'' ?> onchange="onTypeChange('timed')">
                                <label for="t_timed">
                                    <div class="type-opt-icon" style="background:var(--err2);color:var(--err)">
                                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    </div>
                                    <div class="type-opt-name">Timed Challenge</div>
                                    <div class="type-opt-desc">Speed + accuracy practice · ~60 min</div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Section (only shown for non-full-length) -->
                    <div class="field" id="sectionField" style="<?= $vals['type']==='full_length' ? 'display:none' : '' ?>">
                        <label class="fl" for="section">Section Focus</label>
                        <select id="section" name="section" class="fs">
                            <option value="full"            <?= $vals['section']==='full'?'selected':'' ?>>Both (Math + R&W)</option>
                            <option value="math"            <?= $vals['section']==='math'?'selected':'' ?>>Math Only</option>
                            <option value="reading_writing" <?= $vals['section']==='reading_writing'?'selected':'' ?>>Reading &amp; Writing Only</option>
                        </select>
                    </div>

                    <!-- Custom time -->
                    <div class="field">
                        <label class="fl" for="customTime">Total Time Limit (minutes)</label>
                        <input type="number" id="customTime" class="fi" style="max-width:140px"
                               value="<?= round($vals['total_time'] / 60) ?>"
                               min="1" max="600"
                               oninput="document.getElementById('totalTimeInput').value = this.value * 60; updateSummary()">
                        <div class="time-presets" id="timePresets">
                            <button type="button" class="preset-btn" onclick="setTime(134, this)">134m · Full SAT</button>
                            <button type="button" class="preset-btn" onclick="setTime(70, this)">70m · Math</button>
                            <button type="button" class="preset-btn" onclick="setTime(64, this)">64m · R&W</button>
                            <button type="button" class="preset-btn" onclick="setTime(40, this)">40m · Mini</button>
                            <button type="button" class="preset-btn" onclick="setTime(30, this)">30m · Topic</button>
                        </div>
                        <div class="hint">For full SAT = 134 min (2×32 R&W + 2×35 Math)</div>
                    </div>
                </div>
            </div>

        </div><!-- end .editor-main -->

        <!-- ── RIGHT SIDEBAR ────────────────────────────── -->
        <div class="editor-side">

            <!-- Publish -->
            <div class="card reveal d2">
                <div class="card-head">
                    <div class="card-head-ico" style="background:var(--ac3);color:var(--ac)">
                        <svg viewBox="0 0 24 24"><path d="M22 2L11 13M22 2L15 22 11 13 2 9l20-7z"/></svg>
                    </div>
                    <div class="card-title">Publish</div>
                </div>
                <div class="card-body">
                    <label class="pub-toggle" for="is_published">
                        <input type="checkbox" id="is_published" name="is_published" value="1" <?= $vals['is_published'] ? 'checked' : '' ?> onchange="updateSummary()">
                        <div class="pub-toggle-left">
                            <div class="pub-toggle-dot"></div>
                            <div>
                                <div class="pub-toggle-text">Publish immediately</div>
                                <div class="pub-toggle-sub">Students can see this test</div>
                            </div>
                        </div>
                        <div class="pub-switch"></div>
                    </label>
                    <p style="font-size:.5625rem;color:var(--tx3)">
                        Leave unpublished to build sections first, then publish when ready.
                    </p>

                    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:11px">
                        <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        Create Test
                    </button>
                    <p style="font-size:.5625rem;color:var(--tx3);text-align:center">
                        You'll add questions in the next step →
                    </p>
                </div>
            </div>

            <!-- Summary -->
            <div class="card reveal d3">
                <div class="card-head">
                    <div class="card-title">Summary</div>
                </div>
                <div class="card-body" style="padding:16px">
                    <div class="summary-row">
                        <span class="summary-key">Type</span>
                        <span class="summary-val" id="sumType">Full Length</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-key">Duration</span>
                        <span class="summary-val" id="sumTime">134 min</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-key">Status</span>
                        <span class="summary-val" id="sumStatus" style="color:var(--tx3)">Draft</span>
                    </div>
                    <div class="summary-row" id="sumSectionsRow">
                        <span class="summary-key">Sections</span>
                        <span class="summary-val" style="color:var(--ac)" id="sumSections">4 auto-created</span>
                    </div>
                </div>
            </div>

            <!-- Real SAT format reference -->
            <div class="card reveal d4" style="background:var(--sf)">
                <div class="card-head">
                    <div class="card-title" style="font-size:.8125rem">Real SAT Format</div>
                </div>
                <div class="card-body" style="padding:14px 16px">
                    <div class="sat-guide">
                        <div class="sat-module">
                            <div class="sat-mod-dot" style="background:var(--blue)"></div>
                            <span class="sat-mod-label">R&amp;W Module 1</span>
                            <span class="sat-mod-time">32 min</span>
                            <span class="sat-mod-q">27 Q</span>
                        </div>
                        <div class="sat-module">
                            <div class="sat-mod-dot" style="background:var(--blue)"></div>
                            <span class="sat-mod-label">R&amp;W Module 2</span>
                            <span class="sat-mod-time">32 min</span>
                            <span class="sat-mod-q">27 Q</span>
                        </div>
                        <div class="sat-break">
                            <div class="sat-break-line"></div>
                            <span class="sat-break-lbl">☕ 10 min break</span>
                            <div class="sat-break-line"></div>
                        </div>
                        <div class="sat-module">
                            <div class="sat-mod-dot" style="background:var(--purple)"></div>
                            <span class="sat-mod-label">Math Module 1</span>
                            <span class="sat-mod-time">35 min</span>
                            <span class="sat-mod-q">22 Q</span>
                        </div>
                        <div class="sat-module">
                            <div class="sat-mod-dot" style="background:var(--purple)"></div>
                            <span class="sat-mod-label">Math Module 2</span>
                            <span class="sat-mod-time">35 min</span>
                            <span class="sat-mod-q">22 Q</span>
                        </div>
                    </div>
                    <p style="font-size:.5625rem;color:var(--tx3);margin-top:10px;line-height:1.5">
                        For full-length tests, sections are auto-created with correct timing. You assign questions per section in the editor.
                    </p>
                </div>
            </div>

        </div><!-- end .editor-side -->

    </div><!-- end .editor-layout -->
    </form>

</main>

<script>
/* ── Sidebar ──────────────────────────────────── */
function openSidebar(){
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('sbOverlay').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeSidebar(){
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sbOverlay').classList.remove('show');
    document.body.style.overflow = '';
}
document.getElementById('sbOverlay').addEventListener('click', closeSidebar);
window.addEventListener('resize', function(){ if(window.innerWidth > 768) closeSidebar(); });

/* ── Char counters ─────────────────────────────── */
function updateCharCount(el, max, id){
    var n   = el.value.length;
    var out = document.getElementById(id);
    if (!out) return;
    out.textContent = n + ' / ' + max;
    out.className   = 'char-count' + (n > max*0.9 ? ' warn' : '') + (n >= max ? ' over' : '');
}

/* ── Type change ────────────────────────────────── */
var typeTimings = { full_length:134, mini:40, topic:30, timed:60 };
var typeLabels  = { full_length:'Full Length', mini:'Mini Test', topic:'Topic Drill', timed:'Timed Challenge' };

function onTypeChange(type){
    // Show/hide section field
    var sf = document.getElementById('sectionField');
    if (sf) sf.style.display = type === 'full_length' ? 'none' : '';

    // Update time
    var mins = typeTimings[type] || 134;
    var ti   = document.getElementById('customTime');
    if (ti) ti.value = mins;
    document.getElementById('totalTimeInput').value = mins * 60;

    // Highlight preset button
    document.querySelectorAll('.preset-btn').forEach(function(btn){ btn.classList.remove('active'); });

    updateSummary();
}

function setTime(mins, btn){
    document.getElementById('customTime').value = mins;
    document.getElementById('totalTimeInput').value = mins * 60;
    document.querySelectorAll('.preset-btn').forEach(function(b){ b.classList.remove('active'); });
    btn.classList.add('active');
    updateSummary();
}

/* ── Summary ────────────────────────────────────── */
function updateSummary(){
    // Type label
    var typeEl = document.querySelector('input[name="type"]:checked');
    var type   = typeEl ? typeEl.value : 'full_length';
    var sumT   = document.getElementById('sumType');
    if (sumT) sumT.textContent = typeLabels[type] || type;

    // Time
    var mins  = parseInt(document.getElementById('customTime')?.value || 134);
    var h     = Math.floor(mins / 60);
    var m     = mins % 60;
    var timeStr = h > 0 ? h+'h '+m+'m' : m+'m';
    var sumTime = document.getElementById('sumTime');
    if (sumTime) sumTime.textContent = mins + ' min (' + timeStr + ')';

    // Status
    var published = document.getElementById('is_published')?.checked;
    var sumStatus = document.getElementById('sumStatus');
    if (sumStatus){
        sumStatus.textContent = published ? 'Published' : 'Draft';
        sumStatus.style.color = published ? 'var(--ac)' : 'var(--tx3)';
    }

    // Sections note
    var sectRow = document.getElementById('sumSectionsRow');
    var sectVal = document.getElementById('sumSections');
    if (sectRow && sectVal){
        if (type === 'full_length'){
            sectRow.style.display = '';
            sectVal.textContent = '4 auto-created';
        } else {
            sectRow.style.display = '';
            sectVal.textContent = 'Add manually in editor';
        }
    }
}

/* ── Form validation ───────────────────────────── */
document.getElementById('mainForm').addEventListener('submit', function(e){
    var t = document.getElementById('title');
    if (t && !t.value.trim()){
        e.preventDefault();
        t.classList.add('err');
        t.focus();
        t.scrollIntoView({ behavior:'smooth', block:'center' });
    }
});

/* ── Init ─────────────────────────────────────── */
updateSummary();
// Highlight correct preset button on load
(function(){
    var mins = parseInt(document.getElementById('customTime')?.value || 134);
    document.querySelectorAll('.preset-btn').forEach(function(btn){
        var m = parseInt(btn.getAttribute('onclick')?.match(/setTime\((\d+)/)?.[1]);
        if (m === mins) btn.classList.add('active');
    });
})();
</script>
</body>
</html>