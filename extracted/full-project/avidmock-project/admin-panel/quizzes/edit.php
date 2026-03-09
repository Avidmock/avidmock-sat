<?php
/**
 * quizzes/edit.php
 * Edit an existing quiz — with full rich explanation editor + image upload.
 */
require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

$admin  = currentAdmin();
$db     = Database::connect();
$quizId = (int)($_GET['id'] ?? 0);
if (!$quizId) { header('Location: /quizzes/index.php'); exit; }

// ── Load quiz ─────────────────────────────────────────────────────────────
$quizStmt = $db->prepare("SELECT * FROM sat_quizzes WHERE id = :id");
$quizStmt->execute([':id' => $quizId]);
$quiz = $quizStmt->fetch(PDO::FETCH_ASSOC);
if (!$quiz) { header('Location: /quizzes/index.php'); exit; }

// ── Schema detection ──────────────────────────────────────────────────────
$allTables    = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$questionCols = $db->query("SHOW COLUMNS FROM sat_quiz_questions")->fetchAll(PDO::FETCH_COLUMN);
$hasExpHtml   = in_array('explanation_html', $questionCols);

// ── Questions ─────────────────────────────────────────────────────────────
$qStmt = $db->prepare("SELECT * FROM sat_quiz_questions WHERE quiz_id = :qid ORDER BY position ASC");
$qStmt->execute([':qid' => $quizId]);
$questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Lessons dropdown ──────────────────────────────────────────────────────
$lessons = [];
if (in_array('lessons', $allTables)) {
    try { $lessons = $db->query("SELECT slug, title FROM lessons WHERE status='published' ORDER BY title")->fetchAll(PDO::FETCH_ASSOC); }
    catch (Throwable) {}
} elseif (in_array('sat_quizzes', $allTables)) {
    try { $lessons = $db->query("SELECT DISTINCT lesson_slug AS slug, lesson_slug AS title FROM sat_quizzes WHERE lesson_slug IS NOT NULL AND lesson_slug != '' ORDER BY lesson_slug")->fetchAll(PDO::FETCH_ASSOC); }
    catch (Throwable) {}
}
$slugInDropdown = !empty($lessons) && (bool)array_filter($lessons, fn($l) => $l['slug'] === $quiz['lesson_slug']);

// ── Micro-topics ──────────────────────────────────────────────────────────
$topics = [];
if (in_array('micro_topics', $allTables)) {
    try { $topics = $db->query("SELECT id, name, domain, subject FROM micro_topics ORDER BY subject, domain, name")->fetchAll(PDO::FETCH_ASSOC); }
    catch (Throwable) {}
}

// ── Attempt count ─────────────────────────────────────────────────────────
$attemptCount = 0;
if (in_array('sat_quiz_attempts', $allTables)) {
    try {
        $s = $db->prepare("SELECT COUNT(*) FROM sat_quiz_attempts WHERE quiz_id = :id");
        $s->execute([':id' => $quizId]);
        $attemptCount = (int)$s->fetchColumn();
    } catch (Throwable) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Edit: <?= htmlspecialchars($quiz['title']) ?> — Avidmock Admin</title>
<meta name="robots" content="noindex,nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700;9..40,800&family=Fraunces:ital,opsz,wght@0,9..144,900;1,9..144,300&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css">
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
.sb-logo-mark{width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,var(--ac),var(--ac2));display:flex;align-items:center;justify-content:center}
.sb-logo-mark svg{width:17px;height:17px;fill:var(--dk)}
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

/* ── Topbar ────────────────────────────────────────────────────────────── */
.topbar{position:fixed;top:0;left:var(--sb-w);right:0;height:var(--top-h);background:rgba(12,31,29,.94);backdrop-filter:blur(16px);border-bottom:1px solid var(--bd);display:flex;align-items:center;padding:0 28px;gap:10px;z-index:200}
.topbar-ham{display:none;width:34px;height:34px;border-radius:8px;border:1px solid var(--bd);background:var(--sf);align-items:center;justify-content:center;cursor:pointer;flex-direction:column;gap:4px;padding:9px}
.topbar-ham span{display:block;height:1.5px;background:var(--tx2);border-radius:1px;width:100%}
.topbar-breadcrumb{display:flex;align-items:center;gap:7px;font-family:var(--fh);font-size:.9375rem;font-weight:900;color:var(--tx);letter-spacing:-.025em;min-width:0;overflow:hidden}
.topbar-breadcrumb a{color:var(--tx3);font-style:italic;font-weight:300;text-decoration:none;transition:color .15s;white-space:nowrap}
.topbar-breadcrumb a:hover{color:var(--ac)}
.topbar-breadcrumb svg{width:12px;height:12px;stroke:var(--tx3);fill:none;stroke-width:2;stroke-linecap:round;flex-shrink:0}
.topbar-title-text{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.status-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:50px;font-size:.5rem;font-weight:800;text-transform:uppercase;letter-spacing:.5px;flex-shrink:0}
.status-pill.published{background:var(--ac3);color:var(--ac)}
.status-pill.draft{background:var(--warn2);color:var(--warn)}
.status-pill.archived{background:var(--sf3);color:var(--tx3)}
.topbar-spacer{flex:1;min-width:8px}
.autosave{display:flex;align-items:center;gap:6px;font-family:var(--fm);font-size:.625rem;color:var(--tx3);white-space:nowrap}
.autosave-dot{width:6px;height:6px;border-radius:50%;background:var(--tx3);transition:background .3s}
.autosave-dot.saving{background:var(--warn);animation:blink .6s ease-in-out infinite alternate}
.autosave-dot.saved{background:var(--ac)}
@keyframes blink{to{opacity:.25}}

/* ── Buttons ───────────────────────────────────────────────────────────── */
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:9px;font-family:var(--ff);font-size:.8125rem;font-weight:700;text-decoration:none;border:1.5px solid transparent;cursor:pointer;transition:all .18s;white-space:nowrap;flex-shrink:0}
.btn svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
.btn-sm{padding:6px 13px;font-size:.75rem}
.btn-ghost{background:var(--sf);border-color:var(--bd);color:var(--tx2)}
.btn-ghost:hover{background:var(--sf2);border-color:var(--bd2);color:var(--tx)}
.btn-primary{background:var(--ac);color:var(--dk)}
.btn-primary:hover{background:var(--ac2);transform:translateY(-1px);box-shadow:0 4px 16px rgba(31,226,144,.25)}
.btn-publish{background:linear-gradient(135deg,var(--ac),var(--ac2));color:var(--dk);border:none}
.btn-publish:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(31,226,144,.3)}
.btn-danger{background:var(--err2);border-color:rgba(239,68,68,.2);color:var(--err)}
.btn-danger:hover{background:rgba(239,68,68,.22)}

/* ── Layout ────────────────────────────────────────────────────────────── */
.main{margin-left:var(--sb-w);margin-top:var(--top-h);display:grid;grid-template-columns:1fr 288px;min-height:calc(100vh - var(--top-h));align-items:start}
.main-col{padding:32px 28px}
.aside-col{padding:24px 20px;background:var(--ink2);border-left:1px solid var(--bd);position:sticky;top:var(--top-h);height:calc(100vh - var(--top-h));overflow-y:auto;display:flex;flex-direction:column}
.aside-col::-webkit-scrollbar{width:3px}.aside-col::-webkit-scrollbar-thumb{background:rgba(255,255,255,.06);border-radius:2px}

/* ── Page header ───────────────────────────────────────────────────────── */
.page-eyebrow{display:flex;align-items:center;gap:6px;font-size:.5rem;font-weight:700;color:var(--tx3);text-transform:uppercase;letter-spacing:1.2px;margin-bottom:5px}
.page-eyebrow-dot{width:4px;height:4px;border-radius:50%;background:var(--ac)}
.page-title{font-family:var(--fh);font-size:1.625rem;font-weight:900;color:var(--tx);letter-spacing:-.04em;line-height:1.15;margin-bottom:4px}
.page-meta{font-size:.8125rem;color:var(--tx3);margin-bottom:22px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.page-meta-sep{opacity:.35}

/* ── Alerts ────────────────────────────────────────────────────────────── */
.alert{padding:12px 16px;border-radius:10px;font-size:.8125rem;margin-bottom:20px;display:flex;align-items:flex-start;gap:10px;line-height:1.55}
.alert svg{flex-shrink:0;margin-top:1px;width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round}
.alert-warn{background:var(--warn2);border:1px solid rgba(245,158,11,.2);color:var(--warn)}

/* ── Cards ─────────────────────────────────────────────────────────────── */
.card{background:var(--sf);border:1px solid var(--bd);border-radius:14px;overflow:hidden;margin-bottom:20px}
.card-head{padding:14px 20px;border-bottom:1px solid var(--bd);display:flex;align-items:center;gap:10px}
.card-head-icon{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.card-head-icon svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.card-head-icon.green{background:var(--ac3);color:var(--ac)}
.card-head-icon.blue{background:var(--blue2);color:var(--blue)}
.card-head-title{font-size:.875rem;font-weight:700;color:var(--tx)}
.card-head-count{font-size:.5625rem;font-weight:800;background:var(--ac3);color:var(--ac);padding:2px 8px;border-radius:50px}
.card-body{padding:20px}

/* ── Form fields ───────────────────────────────────────────────────────── */
.field{margin-bottom:18px}
.field:last-child{margin-bottom:0}
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:18px}
.field-label{display:block;font-size:.75rem;font-weight:700;color:var(--tx2);margin-bottom:6px}
.field-label .opt{color:var(--tx3);font-weight:400}
.field-hint{font-size:.5625rem;color:var(--tx3);margin-top:5px;line-height:1.55}
.field-input{width:100%;padding:10px 13px;background:var(--sf);border:1.5px solid var(--bd);border-radius:9px;font-family:var(--ff);font-size:.875rem;color:var(--tx);outline:none;transition:border-color .18s,box-shadow .18s;line-height:1.6}
.field-input::placeholder{color:var(--tx3)}
.field-input:focus{border-color:var(--ac);box-shadow:0 0 0 3px rgba(31,226,144,.09)}
textarea.field-input{resize:vertical;min-height:80px}
select.field-input{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%235a8580' stroke-width='2' stroke-linecap='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center}
.slug-toggle{font-size:.625rem;color:var(--tx3);text-decoration:underline;cursor:pointer;margin-left:6px;font-weight:400;transition:color .14s}
.slug-toggle:hover{color:var(--ac)}

/* ── Question cards ────────────────────────────────────────────────────── */
.q-list{display:flex;flex-direction:column;gap:12px}
.q-card{background:var(--ink2);border:1.5px solid var(--bd);border-radius:12px;overflow:hidden;transition:border-color .16s}
.q-card.open{border-color:rgba(31,226,144,.2)}
.q-card.dragging{border-color:var(--ac);opacity:.6;transform:rotate(.4deg)}
.q-card-head{display:flex;align-items:center;gap:10px;padding:13px 16px;cursor:pointer;user-select:none}
.q-drag{cursor:grab;color:var(--tx3);line-height:0;flex-shrink:0}
.q-drag svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:1.6}
.q-num{font-family:var(--fm);font-size:.5625rem;font-weight:500;color:var(--tx3);background:var(--sf3);padding:2px 8px;border-radius:4px;flex-shrink:0}
.q-stem-preview{flex:1;font-size:.8125rem;color:var(--tx);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.q-diff{font-size:.5625rem;font-weight:700;padding:2px 8px;border-radius:50px;flex-shrink:0}
.q-diff.easy{background:var(--ac3);color:var(--ac)}
.q-diff.medium{background:var(--warn2);color:var(--warn)}
.q-diff.hard{background:var(--err2);color:var(--err)}
.q-chevron{margin-left:4px;color:var(--tx3);line-height:0;transition:transform .2s}
.q-chevron svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.q-card.open .q-chevron{transform:rotate(180deg)}
.q-body{display:none;padding:0 16px 16px;border-top:1px solid var(--bd)}
.q-card.open .q-body{display:block}
.q-body-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:16px 0 14px}
.opts-label{font-size:.75rem;font-weight:700;color:var(--tx2);margin:14px 0 8px}
.opt-row{display:flex;align-items:center;gap:9px;margin-bottom:8px}
.opt-radio{width:15px;height:15px;accent-color:var(--ac);flex-shrink:0;cursor:pointer}
.opt-letter{width:23px;height:23px;border-radius:5px;background:var(--sf3);display:flex;align-items:center;justify-content:center;font-size:.5625rem;font-weight:800;color:var(--tx3);flex-shrink:0}
.stem-preview{margin-top:7px;padding:9px 12px;background:var(--sf);border:1px solid var(--bd);border-radius:8px;min-height:34px;font-size:.875rem;color:var(--tx);line-height:1.65}
.q-del-btn{display:flex;align-items:center;justify-content:center;gap:6px;width:100%;margin-top:14px;padding:9px;border-radius:8px;border:1px dashed rgba(239,68,68,.2);background:none;color:var(--err);font-size:.75rem;font-weight:600;cursor:pointer;transition:all .15s;font-family:var(--ff)}
.q-del-btn:hover{background:var(--err2);border-style:solid}
.q-del-btn svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}

/* ── Explanation section ────────────────────────────────────────────────── */
.exp-section{margin-top:18px;border-top:1px solid var(--bd);padding-top:16px}

.exp-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.exp-header-left{display:flex;align-items:center;gap:10px}
.exp-icon{width:26px;height:26px;border-radius:7px;background:linear-gradient(135deg,var(--ac3),rgba(31,226,144,.14));border:1px solid rgba(31,226,144,.18);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.exp-icon svg{width:12px;height:12px;stroke:var(--ac);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.exp-label{font-size:.875rem;font-weight:700;color:var(--tx)}
.exp-sublabel{font-size:.5625rem;color:var(--tx3);margin-top:2px}

/* Feature capability pills */
.exp-caps{display:flex;align-items:center;gap:5px;flex-wrap:wrap;margin-bottom:12px}
.exp-cap{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:50px;font-size:.5rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;border:1px solid var(--bd)}
.exp-cap svg{width:8px;height:8px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;flex-shrink:0}
.exp-cap.img{background:var(--blue2);border-color:rgba(59,130,246,.2);color:var(--blue)}
.exp-cap.tex{background:var(--ac3);border-color:rgba(31,226,144,.2);color:var(--ac)}
.exp-cap.fmt{background:var(--sf3);border-color:var(--bd2);color:var(--tx2)}

/* Image upload zone — the primary CTAs */
.exp-upload-zone{
    display:flex;align-items:stretch;gap:8px;
    padding:12px 14px;margin-bottom:10px;
    background:rgba(59,130,246,.05);
    border:1.5px dashed rgba(59,130,246,.3);
    border-radius:10px;
    cursor:pointer;
    transition:all .2s;
    position:relative;
}
.exp-upload-zone:hover{background:var(--blue2);border-color:rgba(59,130,246,.5)}
.exp-upload-zone:hover .euz-arrow{transform:translateX(3px)}
.euz-thumb{
    width:42px;height:42px;border-radius:8px;
    background:var(--blue2);border:1px solid rgba(59,130,246,.25);
    display:flex;align-items:center;justify-content:center;flex-shrink:0;
    transition:all .2s;
}
.euz-thumb svg{width:18px;height:18px;stroke:var(--blue);fill:none;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}
.euz-body{flex:1}
.euz-title{font-size:.8125rem;font-weight:700;color:var(--tx);margin-bottom:3px}
.euz-sub{font-size:.5625rem;color:var(--tx3);line-height:1.55}
.euz-badges{display:flex;gap:4px;margin-top:5px;flex-wrap:wrap}
.euz-badge{font-family:var(--fm);font-size:.4375rem;font-weight:700;padding:2px 6px;border-radius:4px;background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.2);color:var(--blue);text-transform:uppercase;letter-spacing:.4px}
.euz-arrow{margin-left:auto;align-self:center;color:var(--tx3);line-height:0;transition:transform .2s;flex-shrink:0}
.euz-arrow svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}

/* Drag-over state for the rich editor when a file is dragged over the whole exp section */
.exp-section.drag-over .re-wrap{border-color:var(--blue)!important;box-shadow:0 0 0 3px rgba(59,130,246,.15)!important}

/* Add question button */
.add-q-btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:15px;border:1.5px dashed rgba(31,226,144,.22);border-radius:12px;background:var(--ac3);color:var(--ac);font-size:.8125rem;font-weight:700;font-family:var(--ff);cursor:pointer;transition:all .2s;margin-top:12px}
.add-q-btn:hover{border-color:var(--ac2);background:var(--ac4)}
.add-q-btn svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round}

/* ── Aside ─────────────────────────────────────────────────────────────── */
.aside-section{margin-bottom:24px}
.aside-section:last-child{margin-bottom:0}
.aside-label{font-size:.5rem;font-weight:700;color:var(--tx3);text-transform:uppercase;letter-spacing:1.1px;margin-bottom:10px;display:flex;align-items:center;gap:8px}
.aside-label::after{content:'';flex:1;height:1px;background:var(--bd)}
.aside-field{margin-bottom:12px}
.aside-field:last-child{margin-bottom:0}
.aside-field-label{display:block;font-size:.75rem;font-weight:700;color:var(--tx2);margin-bottom:5px}
.aside-input{width:100%;padding:8px 11px;background:var(--sf);border:1.5px solid var(--bd);border-radius:8px;font-family:var(--ff);font-size:.8125rem;color:var(--tx);outline:none;transition:border-color .18s}
.aside-input:focus{border-color:var(--ac)}
.aside-input::placeholder{color:var(--tx3)}
select.aside-input{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%235a8580' stroke-width='2' stroke-linecap='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center}
.aside-hint{font-size:.5rem;color:var(--tx3);margin-top:4px;line-height:1.55}
.toggle-row{display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--bd)}
.toggle-row:last-child{border-bottom:none;padding-bottom:0}
.toggle-row:first-child{padding-top:0}
.toggle-info{flex:1}
.toggle-label{font-size:.8125rem;font-weight:600;color:var(--tx)}
.toggle-sub{font-size:.5625rem;color:var(--tx3);margin-top:2px}
.toggle{position:relative;width:36px;height:20px;flex-shrink:0}
.toggle input{opacity:0;width:0;height:0}
.toggle-slider{position:absolute;inset:0;background:var(--sf3);border:1px solid var(--bd2);border-radius:50px;cursor:pointer;transition:background .2s}
.toggle-slider::before{content:'';position:absolute;width:14px;height:14px;left:2px;top:2px;background:#fff;border-radius:50%;transition:transform .2s}
.toggle input:checked+.toggle-slider{background:var(--ac)}
.toggle input:checked+.toggle-slider::before{transform:translateX(16px)}

/* ── Unsaved bar ───────────────────────────────────────────────────────── */
.unsaved-bar{display:none;background:var(--warn2);border-bottom:1px solid rgba(245,158,11,.2);padding:8px 28px;font-size:.75rem;color:var(--warn);font-weight:600;align-items:center;gap:8px;position:fixed;top:var(--top-h);left:var(--sb-w);right:0;z-index:190}
.unsaved-bar.show{display:flex}
.unsaved-bar svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round}

/* ── Toast ─────────────────────────────────────────────────────────────── */
.toast-wrap{position:fixed;bottom:24px;right:24px;z-index:999;display:flex;flex-direction:column;gap:8px}
.toast{padding:12px 18px;border-radius:10px;font-size:.8125rem;font-weight:600;border:1px solid var(--bd2);box-shadow:0 8px 32px rgba(0,0,0,.4);transform:translateX(120%);transition:transform .3s cubic-bezier(.16,1,.3,1);max-width:300px}
.toast.show{transform:none}
.toast.success{background:rgba(31,226,144,.12);color:var(--ac);border-color:rgba(31,226,144,.25)}
.toast.error{background:rgba(239,68,68,.12);color:var(--err);border-color:rgba(239,68,68,.2)}
.toast.info{background:rgba(59,130,246,.12);color:var(--blue);border-color:rgba(59,130,246,.2)}

/* ── Responsive ────────────────────────────────────────────────────────── */
@media(max-width:1024px){
    .main{grid-template-columns:1fr}
    .aside-col{position:static;height:auto;border-left:none;border-top:1px solid var(--bd)}
}
@media(max-width:768px){
    :root{--sb-w:0px}
    .sb{transform:translateX(-240px);--sb-w:240px}
    .sb.open{transform:translateX(0)}
    .topbar{left:0;padding:0 16px}
    .topbar-ham{display:flex}
    .main{margin-left:0}
    .unsaved-bar{left:0}
    .field-row,.q-body-grid{grid-template-columns:1fr}
}
</style>
</head>
<body>

<div class="sb-overlay" id="sbOverlay" onclick="closeSidebar()"></div>

<!-- ── Sidebar ──────────────────────────────────────────────────────────── -->
<aside class="sb" id="sidebar">
    <a href="/index.php" class="sb-logo">
        <div class="sb-logo-mark"><svg viewBox="0 0 20 20"><path d="M10 2L13 8H19L14 12.5L16 18.5L10 15L4 18.5L6 12.5L1 8H7L10 2Z"/></svg></div>
        <div><div class="sb-logo-name">Avidmock SAT</div><div class="sb-logo-sub">Admin Panel</div></div>
    </a>
    <nav class="sb-nav">
        <div class="sb-group">Overview</div>
        <a href="/index.php" class="sb-link">
            <svg class="sb-ico" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>Dashboard
        </a>
        <a href="/analytics/index.php" class="sb-link">
            <svg class="sb-ico" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>Analytics
        </a>
        <div class="sb-group">Content</div>
        <a href="/quizzes/index.php" class="sb-link active">
            <svg class="sb-ico" viewBox="0 0 24 24"><path d="M9 2H4a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9"/><path d="M13 2l5 5-8 8H5v-5l8-8z"/></svg>Quizzes
        </a>
        <a href="/questions/index.php" class="sb-link">
            <svg class="sb-ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3" stroke-linecap="round"/><line x1="12" y1="17" x2="12.01" y2="17" stroke-width="2.5"/></svg>Question Bank
        </a>
        <a href="/practice-tests/index.php" class="sb-link">
            <svg class="sb-ico" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>Practice Tests
        </a>
        <div class="sb-group">Students</div>
        <a href="/students/index.php" class="sb-link">
            <svg class="sb-ico" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>All Students
        </a>
        <div class="sb-group">Platform</div>
        <a href="/settings/general.php" class="sb-link">
            <svg class="sb-ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>Settings
        </a>
    </nav>
    <div class="sb-foot">
        <div class="sb-ava"><?= strtoupper(substr($admin['name'],0,1)) ?></div>
        <div>
            <div class="sb-name"><?= htmlspecialchars($admin['name']) ?></div>
            <div class="sb-role"><?= htmlspecialchars($admin['role']) ?></div>
        </div>
        <a href="/auth/logout.php" class="sb-out" title="Sign out">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </a>
    </div>
</aside>

<!-- ── Topbar ────────────────────────────────────────────────────────────── -->
<header class="topbar">
    <button class="topbar-ham" onclick="openSidebar()" aria-label="Menu"><span></span><span></span><span></span></button>
    <div class="topbar-breadcrumb">
        <a href="/quizzes/index.php">Quizzes</a>
        <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        <span class="topbar-title-text"><?= htmlspecialchars($quiz['title']) ?></span>
        <span class="status-pill <?= htmlspecialchars($quiz['status']) ?>"><?= ucfirst(htmlspecialchars($quiz['status'])) ?></span>
    </div>
    <div class="topbar-spacer"></div>
    <div class="autosave">
        <span class="autosave-dot saved" id="autosaveDot"></span>
        <span id="autosaveLabel">Saved</span>
    </div>
    <a href="/quizzes/results.php?id=<?= $quizId ?>" class="btn btn-ghost btn-sm">
        <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>Results
    </a>
    <a href="/quizzes/preview.php?id=<?= $quizId ?>" class="btn btn-ghost btn-sm" target="_blank">
        <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>Preview
    </a>
    <button class="btn btn-ghost btn-sm" onclick="saveQuiz('<?= $quiz['status'] === 'published' ? 'published' : 'draft' ?>')">
        <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>Save
    </button>
    <?php if ($quiz['status'] !== 'published'): ?>
    <button class="btn btn-publish btn-sm" onclick="saveQuiz('published')">
        <svg viewBox="0 0 24 24"><path d="M12 2l3 7h7l-5.5 4.5 2 7L12 17l-6.5 3.5 2-7L2 9h7z" fill="var(--dk)" stroke="none"/></svg>Publish
    </button>
    <?php else: ?>
    <button class="btn btn-danger btn-sm" onclick="saveQuiz('draft')">Unpublish</button>
    <?php endif; ?>
</header>

<!-- Unsaved bar -->
<div class="unsaved-bar" id="unsavedBar">
    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16" stroke-width="2.5"/></svg>
    You have unsaved changes — Ctrl+S to save
</div>

<!-- ── Main grid ─────────────────────────────────────────────────────────── -->
<div class="main">

    <div class="main-col">
        <div class="page-eyebrow"><span class="page-eyebrow-dot"></span>Quizzes / Edit</div>
        <h1 class="page-title"><?= htmlspecialchars($quiz['title']) ?></h1>
        <div class="page-meta">
            <span>ID #<?= $quizId ?></span>
            <span class="page-meta-sep">·</span>
            <span><?= number_format($attemptCount) ?> attempt<?= $attemptCount !== 1 ? 's' : '' ?></span>
            <span class="page-meta-sep">·</span>
            <span>Updated <?= date('M j, Y', strtotime($quiz['updated_at'])) ?></span>
        </div>

        <?php if ($attemptCount > 0): ?>
        <div class="alert alert-warn">
            <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17" stroke-width="2.5"/></svg>
            <div><?= number_format($attemptCount) ?> student<?= $attemptCount !== 1 ? 's have' : ' has' ?> already attempted this quiz. Editing questions may affect result consistency.</div>
        </div>
        <?php endif; ?>

        <!-- Quiz Details card ──────────────────────────────────────────── -->
        <div class="card">
            <div class="card-head">
                <div class="card-head-icon green">
                    <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </div>
                <div class="card-head-title">Quiz Details</div>
            </div>
            <div class="card-body">
                <div class="field">
                    <label class="field-label" for="quizTitle">Title <span class="opt">*</span></label>
                    <input type="text" id="quizTitle" class="field-input"
                           value="<?= htmlspecialchars($quiz['title']) ?>" oninput="markUnsaved()">
                </div>
                <div class="field-row">
                    <div>
                        <label class="field-label">
                            Lesson Slug <span class="opt">*</span>
                            <?php if (!empty($lessons)): ?>
                            <span class="slug-toggle" id="slugToggle" onclick="toggleSlugMode()">type manually</span>
                            <?php endif; ?>
                        </label>
                        <?php if (!empty($lessons)): ?>
                        <select id="lessonSlugSelect" class="field-input" onchange="markUnsaved()">
                            <?php foreach ($lessons as $l): ?>
                            <option value="<?= htmlspecialchars($l['slug']) ?>" <?= $l['slug'] === $quiz['lesson_slug'] ? 'selected' : '' ?>><?= htmlspecialchars($l['title'] ?? $l['slug']) ?></option>
                            <?php endforeach; ?>
                            <?php if (!$slugInDropdown && $quiz['lesson_slug']): ?>
                            <option value="<?= htmlspecialchars($quiz['lesson_slug']) ?>" selected><?= htmlspecialchars($quiz['lesson_slug']) ?> (current)</option>
                            <?php endif; ?>
                        </select>
                        <input type="text" id="lessonSlugText" class="field-input"
                               value="<?= htmlspecialchars($quiz['lesson_slug']) ?>"
                               style="display:none" oninput="markUnsaved()">
                        <?php else: ?>
                        <input type="text" id="lessonSlugText" class="field-input"
                               value="<?= htmlspecialchars($quiz['lesson_slug']) ?>"
                               placeholder="e.g. solving-linear-equations" oninput="markUnsaved()">
                        <?php endif; ?>
                        <div class="field-hint">Links this quiz to its lesson page.</div>
                    </div>
                    <div>
                        <label class="field-label" for="quizSection">Section</label>
                        <select id="quizSection" class="field-input" onchange="markUnsaved()">
                            <option value="" <?= !$quiz['section'] ? 'selected' : '' ?>>— Any section —</option>
                            <option value="math" <?= $quiz['section'] === 'math' ? 'selected' : '' ?>>Math</option>
                            <option value="reading-writing" <?= $quiz['section'] === 'reading-writing' ? 'selected' : '' ?>>Reading &amp; Writing</option>
                        </select>
                    </div>
                </div>
                <div class="field">
                    <label class="field-label" for="quizInstructions">Instructions <span class="opt">(optional)</span></label>
                    <textarea id="quizInstructions" class="field-input" rows="3" oninput="markUnsaved()"><?= htmlspecialchars($quiz['instructions'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Questions card ─────────────────────────────────────────────── -->
        <div class="card">
            <div class="card-head">
                <div class="card-head-icon blue">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3" stroke-linecap="round"/><line x1="12" y1="17" x2="12.01" y2="17" stroke-width="2.5"/></svg>
                </div>
                <div class="card-head-title">Questions</div>
                <span class="card-head-count" id="qCountBadge">0 questions</span>
                <div style="margin-left:auto;display:flex;gap:6px">
                    <a href="/quizzes/builder.php?id=<?= $quizId ?>" class="btn btn-ghost btn-sm">
                        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>Full Builder
                    </a>
                    <button class="btn btn-ghost btn-sm" onclick="exportJSON()">
                        <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>Export
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="q-list" id="qList"></div>
                <button class="add-q-btn" onclick="addQuestion()">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                    Add Question
                </button>
            </div>
        </div>

    </div><!-- /main-col -->

    <!-- ── Settings aside ────────────────────────────────────────────────── -->
    <aside class="aside-col">
        <div class="aside-section">
            <div class="aside-label">Timing</div>
            <div class="aside-field">
                <label class="aside-field-label" for="timeLimit">Time Limit (seconds)</label>
                <input type="number" id="timeLimit" class="aside-input" value="<?= (int)$quiz['time_limit'] ?>" min="0" step="60" oninput="markUnsaved()">
                <div class="aside-hint">0 = no limit · 600 = 10 min · 1800 = 30 min</div>
            </div>
        </div>
        <div class="aside-section">
            <div class="aside-label">Scoring</div>
            <div class="aside-field">
                <label class="aside-field-label" for="passingScore">Passing Score (%)</label>
                <input type="number" id="passingScore" class="aside-input" value="<?= (int)$quiz['passing_score'] ?>" min="1" max="100" oninput="markUnsaved()">
            </div>
            <div class="aside-field">
                <label class="aside-field-label" for="xpReward">XP Reward on Pass</label>
                <input type="number" id="xpReward" class="aside-input" value="<?= (int)$quiz['xp_reward'] ?>" min="0" step="5" oninput="markUnsaved()">
            </div>
        </div>
        <div class="aside-section">
            <div class="aside-label">Options</div>
            <div class="toggle-row">
                <div class="toggle-info"><div class="toggle-label">Show Hints</div><div class="toggle-sub">Students can reveal hints</div></div>
                <label class="toggle"><input type="checkbox" id="showHints" <?= $quiz['show_hints'] ? 'checked' : '' ?> onchange="markUnsaved()"><span class="toggle-slider"></span></label>
            </div>
            <div class="toggle-row">
                <div class="toggle-info"><div class="toggle-label">Shuffle Questions</div><div class="toggle-sub">Randomise question order</div></div>
                <label class="toggle"><input type="checkbox" id="shuffleQ" <?= $quiz['shuffle_q'] ? 'checked' : '' ?> onchange="markUnsaved()"><span class="toggle-slider"></span></label>
            </div>
            <div class="toggle-row">
                <div class="toggle-info"><div class="toggle-label">Shuffle Options</div><div class="toggle-sub">Randomise A/B/C/D order</div></div>
                <label class="toggle"><input type="checkbox" id="shuffleOpts" <?= $quiz['shuffle_opts'] ? 'checked' : '' ?> onchange="markUnsaved()"><span class="toggle-slider"></span></label>
            </div>
        </div>
        <div class="aside-section">
            <div class="aside-label">Actions</div>
            <a href="/quizzes/duplicate.php?id=<?= $quizId ?>" class="btn btn-ghost" style="width:100%;justify-content:center;margin-bottom:8px">
                <svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>Duplicate Quiz
            </a>
            <a href="/quizzes/export.php?id=<?= $quizId ?>" class="btn btn-ghost" style="width:100%;justify-content:center;margin-bottom:8px">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>Export JSON
            </a>
            <a href="/quizzes/delete.php?id=<?= $quizId ?>" class="btn btn-danger" style="width:100%;justify-content:center"
               onclick="return confirm('Permanently delete this quiz? This cannot be undone.')">
                <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>Delete Quiz
            </a>
        </div>
    </aside>

</div><!-- /main -->

<div class="toast-wrap" id="toastWrap"></div>

<!-- ── Scripts ───────────────────────────────────────────────────────────── -->
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
<script>
/* ── Embed RichEditor ────────────────────────────────────────────────────── */
<?php readfile(__DIR__ . '/_rich_editor.js'); ?>

/* ── Config ──────────────────────────────────────────────────────────────── */
const QUIZ_ID      = <?= $quizId ?>;
const QUIZ_STATUS  = <?= json_encode($quiz['status']) ?>;
const HAS_LESSONS  = <?= !empty($lessons)  ? 'true' : 'false' ?>;
const HAS_EXP_HTML = <?= $hasExpHtml       ? 'true' : 'false' ?>;
const TOPICS       = <?= json_encode($topics, JSON_HEX_TAG) ?>;
const UPLOAD_URL   = '/api/upload-explanation-image.php';

/* ── State ───────────────────────────────────────────────────────────────── */
let questions     = <?= json_encode($questions, JSON_HEX_TAG) ?>;
let unsaved       = false;
let autosaveTimer = null;
const richEditors = {};
let slugMode      = 'select';

/* ── Init: backfill explanation_html on existing questions ───────────────── */
questions.forEach(q => {
    q.explanation_html = q.explanation_html
        || (q.explanation ? `<p>${esc(q.explanation)}</p>` : '');
});

/* ── Slug toggle ─────────────────────────────────────────────────────────── */
function toggleSlugMode() {
    const sel = document.getElementById('lessonSlugSelect');
    const txt = document.getElementById('lessonSlugText');
    const lbl = document.getElementById('slugToggle');
    if (!sel || !txt) return;
    if (slugMode === 'select') {
        slugMode = 'text';
        sel.style.display = 'none'; txt.style.display = ''; txt.focus();
        if (lbl) lbl.textContent = 'use dropdown';
    } else {
        slugMode = 'select';
        txt.style.display = 'none'; sel.style.display = '';
        if (lbl) lbl.textContent = 'type manually';
    }
}
function getSlug() {
    if (!HAS_LESSONS || slugMode === 'text')
        return document.getElementById('lessonSlugText')?.value.trim() || '';
    return document.getElementById('lessonSlugSelect')?.value || '';
}

/* ── Add / remove questions ──────────────────────────────────────────────── */
function addQuestion(data = null) {
    const q = data || {
        id: 'new_' + Date.now(), stem: '', type: 'mcq',
        option_a:'', option_b:'', option_c:'', option_d:'',
        correct_answer:'a', explanation:'', explanation_html:'',
        hint:'', difficulty:'medium', micro_topic_id:'', points:1,
    };
    if (!q.id) q.id = 'new_' + Date.now();
    q.explanation_html = q.explanation_html || (q.explanation ? `<p>${esc(q.explanation)}</p>` : '');
    questions.push(q);
    renderQuestions();
    markUnsaved();
    setTimeout(() => {
        const card = document.querySelector(`[data-qid="${q.id}"]`);
        if (card) { card.classList.add('open'); card.scrollIntoView({ behavior:'smooth', block:'nearest' }); }
    }, 40);
}

function removeQuestion(id) {
    if (richEditors[id]) delete richEditors[id];
    questions = questions.filter(q => String(q.id) !== String(id));
    renderQuestions();
    markUnsaved();
}

/* ── Render all question cards ───────────────────────────────────────────── */
function renderQuestions() {
    const list = document.getElementById('qList');
    list.innerHTML = '';

    questions.forEach((q, i) => {
        const card = document.createElement('div');
        card.className   = 'q-card';
        card.dataset.qid = q.id;
        card.innerHTML   = buildCardHTML(q, i);
        list.appendChild(card);

        /* stem */
        const stemEl = card.querySelector('.stem-input');
        if (stemEl) {
            stemEl.addEventListener('input', () => {
                q.stem = stemEl.value;
                card.querySelector('.q-stem-preview').innerHTML =
                    q.stem ? esc(q.stem.substring(0,80)) : '<em style="color:var(--tx3)">Click to expand…</em>';
                renderStemPreview(card, q.stem);
                markUnsaved();
            });
            renderStemPreview(card, q.stem || '');
        }

        /* options */
        ['a','b','c','d'].forEach(opt => {
            const inp = card.querySelector(`.opt-input-${opt}`);
            if (inp) inp.addEventListener('input', () => { q[`option_${opt}`] = inp.value; markUnsaved(); });
        });
        card.querySelectorAll('.correct-radio').forEach(r =>
            r.addEventListener('change', () => { q.correct_answer = r.value; markUnsaved(); })
        );

        /* meta fields */
        ['difficulty','micro_topic_id','points'].forEach(f => {
            const el = card.querySelector(`[data-f="${f}"]`);
            if (el) el.addEventListener('change', () => { q[f] = el.value; markUnsaved(); });
        });
        const hintEl = card.querySelector('.hint-input');
        if (hintEl) hintEl.addEventListener('input', () => { q.hint = hintEl.value; markUnsaved(); });

        /* ── Explanation ─────────────────────────────────────────────────── */
        if (HAS_EXP_HTML) {
            const richWrap = card.querySelector('.rich-exp-wrap');
            if (richWrap) {
                if (richEditors[q.id]) delete richEditors[q.id];

                richEditors[q.id] = new RichEditor(richWrap, {
                    initialHtml : q.explanation_html || '',
                    uploadUrl   : UPLOAD_URL,
                    onchange    : html => {
                        q.explanation_html = html;
                        /* sync stripped plain text */
                        q.explanation = richWrap.querySelector('.re-editor')?.innerText?.trim() || '';
                        markUnsaved();
                    }
                });

                /* upload-zone button triggers the editor's hidden file input */
                const uploadZone = card.querySelector('.exp-upload-zone');
                if (uploadZone) {
                    uploadZone.addEventListener('click', e => {
                        e.stopPropagation();
                        richEditors[q.id]?.fileInput?.click();
                    });
                }

                /* visual drag-over highlight on the exp-section */
                const expSection = card.querySelector('.exp-section');
                if (expSection) {
                    expSection.addEventListener('dragover', e => {
                        if (e.dataTransfer?.types?.includes('Files')) {
                            e.preventDefault();
                            expSection.classList.add('drag-over');
                        }
                    });
                    expSection.addEventListener('dragleave', () => expSection.classList.remove('drag-over'));
                    expSection.addEventListener('drop', e => {
                        expSection.classList.remove('drag-over');
                        const file = e.dataTransfer?.files[0];
                        if (file && file.type.startsWith('image/')) {
                            e.preventDefault();
                            e.stopPropagation();
                            richEditors[q.id]?._uploadImage(file);
                        }
                    });
                }
            }
        } else {
            /* plain text fallback */
            const plainExp = card.querySelector('.plain-exp');
            if (plainExp) plainExp.addEventListener('input', () => { q.explanation = plainExp.value; markUnsaved(); });
        }

        /* expand / collapse */
        card.querySelector('.q-card-head').addEventListener('click', e => {
            if (e.target.closest('button,input,select,textarea,.re-wrap,.exp-upload-zone')) return;
            card.classList.toggle('open');
        });
    });

    document.getElementById('qCountBadge').textContent =
        questions.length + ' question' + (questions.length !== 1 ? 's' : '');

    initDragDrop();
}

/* ── Build single question card HTML ─────────────────────────────────────── */
function buildCardHTML(q, i) {
    const topicOpts = TOPICS.map(t =>
        `<option value="${t.id}" ${q.micro_topic_id == t.id ? 'selected':''}>${esc(t.name)}</option>`
    ).join('');

    /* ── explanation section markup ───────────────────────────────────────── */
    const expSection = HAS_EXP_HTML ? `
    <div class="exp-section">
        <div class="exp-header">
            <div class="exp-header-left">
                <div class="exp-icon">
                    <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                </div>
                <div>
                    <div class="exp-label">Explanation</div>
                    <div class="exp-sublabel">Shown to students after they answer</div>
                </div>
            </div>
        </div>

        <div class="exp-caps">
            <span class="exp-cap img">
                <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                Images
            </span>
            <span class="exp-cap tex">∑ LaTeX formulas</span>
            <span class="exp-cap fmt">Bold · Italic · Lists · Headings</span>
        </div>

        <div class="exp-upload-zone">
            <div class="euz-thumb">
                <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            </div>
            <div class="euz-body">
                <div class="euz-title">Add an image to this explanation</div>
                <div class="euz-sub">Click here to browse, or drag &amp; drop / paste a screenshot directly into the editor</div>
                <div class="euz-badges">
                    <span class="euz-badge">PNG</span>
                    <span class="euz-badge">JPG</span>
                    <span class="euz-badge">WebP</span>
                    <span class="euz-badge">GIF</span>
                    <span class="euz-badge">max 5 MB</span>
                </div>
            </div>
            <span class="euz-arrow">
                <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
            </span>
        </div>

        <div class="rich-exp-wrap"></div>
    </div>` : `
    <div class="exp-section">
        <div class="exp-header">
            <div class="exp-header-left">
                <div class="exp-icon"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg></div>
                <div>
                    <div class="exp-label">Explanation <span style="font-weight:400;font-size:.625rem;color:var(--tx3);margin-left:4px">(optional)</span></div>
                </div>
            </div>
        </div>
        <textarea class="field-input plain-exp" rows="3"
                  placeholder="Why is this the correct answer?">${esc(q.explanation || '')}</textarea>
    </div>`;

    return `
    <div class="q-card-head">
        <span class="q-drag"><svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="8" y1="18" x2="16" y2="18"/></svg></span>
        <span class="q-num">Q${i+1}</span>
        <span class="q-stem-preview">${q.stem ? esc(q.stem.substring(0,80)) : '<em style="color:var(--tx3)">Click to expand…</em>'}</span>
        <span class="q-diff ${q.difficulty||'medium'}">${q.difficulty||'medium'}</span>
        <span class="q-chevron"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></span>
    </div>

    <div class="q-body">
        <div style="margin-top:16px">
            <label class="field-label">Question Stem
                <span style="font-weight:400;color:var(--tx3)">— $LaTeX$ supported</span>
            </label>
            <textarea class="field-input stem-input" rows="3"
                      placeholder="Write your question here…">${esc(q.stem||'')}</textarea>
            <div class="stem-preview"><span style="color:var(--tx3);font-size:.75rem">LaTeX preview appears here…</span></div>
        </div>

        <div class="q-body-grid">
            <div>
                <label class="field-label">Difficulty</label>
                <select class="field-input" data-f="difficulty">
                    <option value="easy"   ${q.difficulty==='easy'                  ?'selected':''}>Easy</option>
                    <option value="medium" ${!q.difficulty||q.difficulty==='medium' ?'selected':''}>Medium</option>
                    <option value="hard"   ${q.difficulty==='hard'                  ?'selected':''}>Hard</option>
                </select>
            </div>
            <div>
                <label class="field-label">Points</label>
                <input type="number" class="field-input" data-f="points"
                       value="${q.points||1}" min="1" max="5">
            </div>
        </div>

        ${TOPICS.length > 0 ? `
        <div style="margin-bottom:14px">
            <label class="field-label">Micro-Topic</label>
            <select class="field-input" data-f="micro_topic_id">
                <option value="">— Not tagged —</option>${topicOpts}
            </select>
        </div>` : ''}

        <div class="opts-label">Answer Options — select the correct one</div>
        ${['a','b','c','d'].map(opt => `
        <div class="opt-row">
            <input type="radio" class="opt-radio correct-radio"
                   name="correct_${esc(String(q.id))}" value="${opt}"
                   ${(q.correct_answer||'a')===opt?'checked':''}>
            <span class="opt-letter">${opt.toUpperCase()}</span>
            <input type="text" class="field-input opt-input-${opt}"
                   value="${esc(q['option_'+opt]||'')}"
                   placeholder="Option ${opt.toUpperCase()}" style="margin:0">
        </div>`).join('')}

        <div style="margin-top:14px">
            <label class="field-label">Hint <span style="font-weight:400;color:var(--tx3)">(optional)</span></label>
            <textarea class="field-input hint-input" rows="2"
                      placeholder="A helpful nudge toward the answer…">${esc(q.hint||'')}</textarea>
        </div>

        ${expSection}

        <button type="button" class="q-del-btn" onclick="removeQuestion('${esc(String(q.id))}')">
            <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
            Remove this question
        </button>
    </div>`;
}

/* ── Stem LaTeX live preview ─────────────────────────────────────────────── */
function renderStemPreview(card, text) {
    const el = card.querySelector('.stem-preview');
    if (!el || typeof katex === 'undefined') return;
    if (!text.trim()) {
        el.innerHTML = '<span style="color:var(--tx3);font-size:.75rem">LaTeX preview appears here…</span>';
        return;
    }
    el.innerHTML = text.replace(/\$([^$\n]+)\$/g, (_, m) => {
        try { return katex.renderToString(m, { throwOnError:false }); }
        catch { return `<span style="color:var(--err)">[invalid LaTeX]</span>`; }
    });
}

/* ── Drag-and-drop card reorder ──────────────────────────────────────────── */
function initDragDrop() {
    const list = document.getElementById('qList');
    let dragged = null;
    list.querySelectorAll('.q-card').forEach(card => {
        card.setAttribute('draggable', true);
        card.addEventListener('dragstart', () => {
            dragged = card;
            setTimeout(() => card.classList.add('dragging'), 0);
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('dragging');
            dragged = null;
            reorderFromDOM();
        });
        card.addEventListener('dragover', e => {
            /* only card-reorder drags — not file drops */
            if (e.dataTransfer?.types?.includes('Files')) return;
            e.preventDefault();
            if (!dragged || dragged === card) return;
            const r = card.getBoundingClientRect();
            card.parentNode.insertBefore(dragged, e.clientY > r.top + r.height / 2 ? card.nextSibling : card);
        });
    });
}
function reorderFromDOM() {
    const ids = [...document.querySelectorAll('.q-card')].map(c => c.dataset.qid);
    questions  = ids.map(id => questions.find(q => String(q.id) === String(id))).filter(Boolean);
    renderQuestions();
}

/* ── Save ────────────────────────────────────────────────────────────────── */
async function saveQuiz(status) {
    const title = document.getElementById('quizTitle').value.trim();
    const slug  = getSlug();
    if (!title) { toast('Please enter a quiz title.', 'error'); document.getElementById('quizTitle').focus(); return; }
    if (!slug)  { toast('Please select or enter a lesson slug.', 'error'); return; }

    setAutosave('saving');

    const payload = {
        id: QUIZ_ID, title, lesson_slug: slug,
        section:      document.getElementById('quizSection').value,
        instructions: document.getElementById('quizInstructions').value,
        time_limit:   parseInt(document.getElementById('timeLimit').value)    || 0,
        passing_score:parseInt(document.getElementById('passingScore').value) || 70,
        xp_reward:    parseInt(document.getElementById('xpReward').value)     || 50,
        show_hints:   document.getElementById('showHints').checked  ? 1 : 0,
        shuffle_q:    document.getElementById('shuffleQ').checked   ? 1 : 0,
        shuffle_opts: document.getElementById('shuffleOpts').checked? 1 : 0,
        status,
        questions: questions.map((q, i) => ({
            id:              (typeof q.id === 'string' && q.id.startsWith('new_')) ? null : q.id,
            position:        i + 1,
            stem:            q.stem            || '',
            type:            q.type            || 'mcq',
            option_a:        q.option_a        || '',
            option_b:        q.option_b        || '',
            option_c:        q.option_c        || '',
            option_d:        q.option_d        || '',
            correct_answer:  q.correct_answer  || 'a',
            explanation:     q.explanation     || '',
            explanation_html:q.explanation_html|| '',
            hint:            q.hint            || '',
            difficulty:      q.difficulty      || 'medium',
            micro_topic_id:  q.micro_topic_id  || null,
            points:          parseInt(q.points) || 1,
        }))
    };

    try {
        const res  = await fetch('/api/save-quiz.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body:JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            if (data.question_ids) questions.forEach((q,i) => { if (data.question_ids[i]) q.id = data.question_ids[i]; });
            markSaved();
            setAutosave('saved');
            toast(status === 'published' ? '🎉 Quiz published!' : 'Saved.', status === 'published' ? 'success' : 'info');
        } else {
            setAutosave('unsaved');
            toast(data.error || 'Error saving.', 'error');
        }
    } catch (e) {
        setAutosave('unsaved');
        toast('Network error — please try again.', 'error');
    }
}

/* ── Dirty state ─────────────────────────────────────────────────────────── */
function markUnsaved() {
    unsaved = true;
    document.getElementById('unsavedBar').classList.add('show');
    setAutosave('unsaved');
    clearTimeout(autosaveTimer);
    autosaveTimer = setTimeout(() => saveQuiz(QUIZ_STATUS), 90_000);
}
function markSaved() {
    unsaved = false;
    document.getElementById('unsavedBar').classList.remove('show');
}
function setAutosave(s) {
    const dot   = document.getElementById('autosaveDot');
    const label = document.getElementById('autosaveLabel');
    if (!dot || !label) return;
    dot.className     = 'autosave-dot' + (s==='saving'?' saving':s==='saved'?' saved':'');
    label.textContent = s==='saving'?'Saving…':s==='saved'?'Saved':'Unsaved';
}

/* ── Export ──────────────────────────────────────────────────────────────── */
function exportJSON() {
    if (!questions.length) { toast('No questions to export.', 'error'); return; }
    const blob = new Blob([JSON.stringify({ title: document.getElementById('quizTitle').value, questions }, null, 2)], { type:'application/json' });
    const a = Object.assign(document.createElement('a'), { href: URL.createObjectURL(blob), download:`quiz-${QUIZ_ID}.json` });
    a.click(); URL.revokeObjectURL(a.href);
}

/* ── Mobile sidebar ──────────────────────────────────────────────────────── */
function openSidebar()  { document.getElementById('sidebar').classList.add('open'); document.getElementById('sbOverlay').classList.add('show'); document.body.style.overflow='hidden'; }
function closeSidebar() { document.getElementById('sidebar').classList.remove('open'); document.getElementById('sbOverlay').classList.remove('show'); document.body.style.overflow=''; }
window.addEventListener('resize', () => { if(window.innerWidth>768) closeSidebar(); });

/* ── Utility ─────────────────────────────────────────────────────────────── */
function esc(s) {
    return String(s||'')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
function toast(msg, type='info') {
    const wrap = document.getElementById('toastWrap');
    const el   = document.createElement('div');
    el.className = `toast ${type}`; el.textContent = msg;
    wrap.appendChild(el);
    requestAnimationFrame(()=>requestAnimationFrame(()=>el.classList.add('show')));
    setTimeout(()=>{ el.classList.remove('show'); setTimeout(()=>el.remove(),400); },3500);
}

/* ── Boot ────────────────────────────────────────────────────────────────── */
renderQuestions();

document.addEventListener('keydown', e => {
    if ((e.ctrlKey||e.metaKey) && e.key==='s') { e.preventDefault(); saveQuiz(QUIZ_STATUS); }
});
window.addEventListener('beforeunload', e => {
    if (unsaved) { e.preventDefault(); e.returnValue=''; }
});
</script>
</body>
</html>