<?php
/**
 * quizzes/builder.php
 * Full-screen question builder — image-based explanation upload,
 * LaTeX stem preview, drag-and-drop reorder, live student preview.
 */
require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

$admin  = currentAdmin();
$db     = Database::connect();
$quizId = (int)($_GET['id'] ?? 0);
if (!$quizId) { header('Location: /quizzes/index.php'); exit; }

$quizStmt = $db->prepare("SELECT * FROM sat_quizzes WHERE id=:id");
$quizStmt->execute([':id' => $quizId]);
$quiz = $quizStmt->fetch(PDO::FETCH_ASSOC);
if (!$quiz) { header('Location: /quizzes/index.php'); exit; }

$questionCols  = $db->query("SHOW COLUMNS FROM sat_quiz_questions")->fetchAll(PDO::FETCH_COLUMN);
$hasExpHtml    = in_array('explanation_html',  $questionCols);
$hasExpImage   = in_array('explanation_image', $questionCols);

$questionsStmt = $db->prepare("SELECT * FROM sat_quiz_questions WHERE quiz_id=:id ORDER BY position");
$questionsStmt->execute([':id' => $quizId]);
$questions = $questionsStmt->fetchAll(PDO::FETCH_ASSOC);

$allTables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$topics    = [];
if (in_array('micro_topics', $allTables)) {
    try {
        $topics = $db->query("SELECT id, name, domain, subject FROM micro_topics ORDER BY subject, name")
                     ->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {}
}

$scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
$saveUrl   = $scheme . '://' . $host . '/api/save-quiz.php';
$uploadUrl = $scheme . '://' . $host . '/api/upload-explanation-image.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Builder: <?= htmlspecialchars($quiz['title']) ?> — Avidmock</title>
<meta name="robots" content="noindex,nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700;9..40,800&family=Fraunces:ital,opsz,wght@0,9..144,900;1,9..144,300&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css">
<style>
:root {
    --ink:  #0a1a18;
    --ink2: #0d2220;
    --ink3: #071512;
    --dk:   #143230;
    --ac:   #1fe290;
    --ac2:  #13c474;
    --ac3:  rgba(31,226,144,.08);
    --ac4:  rgba(31,226,144,.15);

    --tx:   #e8f3f1;
    --tx2:  #9dbfba;
    --tx3:  #5a8580;

    --bd:   rgba(255,255,255,.07);
    --bd2:  rgba(255,255,255,.12);
    --sf:   rgba(255,255,255,.04);
    --sf2:  rgba(255,255,255,.07);
    --sf3:  rgba(255,255,255,.11);

    --warn: #f59e0b; --warn2: rgba(245,158,11,.1);
    --err:  #ef4444; --err2:  rgba(239,68,68,.1);
    --blue: #3b82f6; --blue2: rgba(59,130,246,.1);

    --ff: 'DM Sans', sans-serif;
    --fh: 'Fraunces', Georgia, serif;
    --fm: 'DM Mono', monospace;
    --bar-h: 56px;

    /* Preview panel uses white/light theme */
    --pv-bg:   #f5f7f6;
    --pv-card: #ffffff;
    --pv-bd:   #e5eae8;
    --pv-tx:   #0d1f1c;
    --pv-tx2:  #374151;
    --pv-tx3:  #6b7280;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; font-family: var(--ff); background: var(--ink); color: var(--tx); -webkit-font-smoothing: antialiased; overflow: hidden; }

/* ══════════════════════════════════════════════════
   TOPBAR
══════════════════════════════════════════════════ */
.topbar {
    position: fixed; top: 0; left: 0; right: 0; height: var(--bar-h);
    background: rgba(7,21,18,.97); backdrop-filter: blur(20px);
    border-bottom: 1px solid var(--bd);
    display: flex; align-items: center; padding: 0 16px; gap: 10px; z-index: 300;
}
.tb-back {
    display: flex; align-items: center; gap: 7px; padding: 6px 12px; border-radius: 8px;
    background: var(--sf); border: 1px solid var(--bd); color: var(--tx2);
    text-decoration: none; font-size: .75rem; font-weight: 700; transition: all .16s; flex-shrink: 0;
}
.tb-back:hover { background: var(--sf2); border-color: var(--bd2); color: var(--tx); }
.tb-back svg { width: 12px; height: 12px; stroke: currentColor; fill: none; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
.tb-divider { width: 1px; height: 22px; background: var(--bd); flex-shrink: 0; }
.tb-quiz-name {
    display: flex; align-items: center; gap: 8px;
    font-family: var(--fh); font-size: .9375rem; font-weight: 900;
    color: var(--tx); letter-spacing: -.025em; min-width: 0; overflow: hidden;
}
.tb-quiz-name span { font-style: italic; font-weight: 300; color: var(--tx3); font-size: .8125rem; white-space: nowrap; }
.tb-quiz-name strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tb-status {
    display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 50px;
    font-size: .4375rem; font-weight: 800; text-transform: uppercase; letter-spacing: .6px; flex-shrink: 0;
}
.tb-status.published { background: var(--ac3); color: var(--ac); }
.tb-status.draft     { background: var(--warn2); color: var(--warn); }
.tb-spacer { flex: 1; min-width: 0; }
.autosave { display: flex; align-items: center; gap: 6px; font-family: var(--fm); font-size: .5625rem; color: var(--tx3); white-space: nowrap; }
.as-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--tx3); transition: background .3s; }
.as-dot.saving { background: var(--warn); animation: blink .7s ease-in-out infinite alternate; }
.as-dot.saved  { background: var(--ac); }
@keyframes blink { to { opacity: .2; } }

.btn {
    display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px;
    border-radius: 8px; font-family: var(--ff); font-size: .75rem; font-weight: 700;
    border: 1.5px solid transparent; cursor: pointer; transition: all .18s;
    text-decoration: none; white-space: nowrap; flex-shrink: 0;
}
.btn svg { width: 12px; height: 12px; stroke: currentColor; fill: none; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
.btn-sm { padding: 5px 11px; font-size: .6875rem; }
.btn-ghost   { background: var(--sf); border-color: var(--bd); color: var(--tx2); }
.btn-ghost:hover { background: var(--sf2); border-color: var(--bd2); color: var(--tx); }
.btn-primary { background: var(--ac); color: var(--dk); }
.btn-primary:hover { background: var(--ac2); box-shadow: 0 4px 16px rgba(31,226,144,.3); transform: translateY(-1px); }
.btn-publish { background: linear-gradient(135deg, var(--ac), var(--ac2)); color: var(--dk); border: none; }
.btn-publish:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(31,226,144,.35); }
.btn-danger  { background: var(--err2); color: var(--err); border-color: rgba(239,68,68,.18); }
.btn-danger:hover { background: rgba(239,68,68,.18); }

/* ══════════════════════════════════════════════════
   3-COLUMN LAYOUT
══════════════════════════════════════════════════ */
.layout { display: grid; grid-template-columns: 244px 1fr 296px; height: 100vh; padding-top: var(--bar-h); }
.col { height: calc(100vh - var(--bar-h)); display: flex; flex-direction: column; overflow: hidden; }

/* ══════════════════════════════════════════════════
   LEFT — question list
══════════════════════════════════════════════════ */
.col-list { background: var(--ink2); border-right: 1px solid var(--bd); }

.cl-head { padding: 14px 14px 10px; border-bottom: 1px solid var(--bd); flex-shrink: 0; display: flex; align-items: center; justify-content: space-between; }
.cl-head-title { font-size: .8125rem; font-weight: 700; color: var(--tx); }
.cl-count { font-size: .4375rem; font-weight: 800; background: var(--ac3); color: var(--ac); padding: 2px 8px; border-radius: 50px; }

.cl-search { margin: 8px 10px 4px; position: relative; flex-shrink: 0; }
.cl-search-input {
    width: 100%; padding: 7px 10px 7px 30px; background: var(--sf2); border: 1px solid var(--bd);
    border-radius: 7px; font-family: var(--ff); font-size: .75rem; color: var(--tx); outline: none; transition: border-color .16s;
}
.cl-search-input::placeholder { color: var(--tx3); }
.cl-search-input:focus { border-color: rgba(31,226,144,.3); }
.cl-search-ico { position: absolute; left: 9px; top: 50%; transform: translateY(-50%); width: 12px; height: 12px; stroke: var(--tx3); fill: none; stroke-width: 2; stroke-linecap: round; pointer-events: none; }

.q-list { flex: 1; overflow-y: auto; padding: 6px 8px 4px; }
.q-list::-webkit-scrollbar { width: 3px; }
.q-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,.06); border-radius: 2px; }

.q-item {
    display: flex; align-items: center; gap: 7px;
    padding: 9px 9px 9px 7px; border-radius: 9px; cursor: pointer;
    transition: all .14s; border: 1.5px solid transparent; margin-bottom: 2px; position: relative;
}
.q-item:hover { background: var(--sf2); }
.q-item.selected { background: var(--ac3); border-color: rgba(31,226,144,.2); }
.q-item.dragging-over { border-color: var(--ac); background: var(--ac4); }
.q-drag { color: var(--tx3); cursor: grab; line-height: 0; flex-shrink: 0; opacity: .5; }
.q-drag svg { width: 11px; height: 11px; stroke: currentColor; fill: none; stroke-width: 1.8; }
.q-num { font-family: var(--fm); font-size: .5625rem; color: var(--tx3); width: 18px; text-align: center; flex-shrink: 0; }
.q-item.selected .q-num { color: var(--ac); }
.q-stem-txt { flex: 1; font-size: .75rem; font-weight: 600; color: var(--tx2); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.q-item.selected .q-stem-txt { color: var(--tx); }
.q-diff-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
.q-diff-dot.easy { background: var(--ac); } .q-diff-dot.medium { background: var(--warn); } .q-diff-dot.hard { background: var(--err); }
.q-has-img { width: 14px; height: 14px; flex-shrink: 0; opacity: .5; }
.q-has-img svg { width: 14px; height: 14px; stroke: var(--ac); fill: none; stroke-width: 1.8; stroke-linecap: round; }
.q-del {
    position: absolute; right: 7px; top: 50%; transform: translateY(-50%);
    opacity: 0; background: var(--err2); border: none; border-radius: 5px;
    padding: 3px 5px; cursor: pointer; color: var(--err); line-height: 0; transition: opacity .14s;
}
.q-item:hover .q-del { opacity: 1; }
.q-del svg { width: 9px; height: 9px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; }

.add-q-btn {
    display: flex; align-items: center; justify-content: center; gap: 7px;
    margin: 8px 8px 10px; padding: 12px;
    border: 1.5px dashed rgba(31,226,144,.22); border-radius: 10px;
    background: var(--ac3); color: var(--ac); font-size: .75rem; font-weight: 700;
    font-family: var(--ff); cursor: pointer; transition: all .2s; flex-shrink: 0;
}
.add-q-btn:hover { border-color: var(--ac2); background: var(--ac4); }
.add-q-btn svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 2.2; stroke-linecap: round; }

/* ══════════════════════════════════════════════════
   MIDDLE — editor
══════════════════════════════════════════════════ */
.col-editor { background: var(--ink); overflow-y: auto; }
.col-editor::-webkit-scrollbar { width: 4px; }
.col-editor::-webkit-scrollbar-thumb { background: rgba(255,255,255,.06); border-radius: 2px; }

.editor-empty {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    height: 100%; text-align: center; padding: 40px;
}
.editor-empty-icon {
    width: 72px; height: 72px; border-radius: 20px; background: var(--sf2); border: 1px solid var(--bd);
    display: flex; align-items: center; justify-content: center; margin-bottom: 20px;
}
.editor-empty-icon svg { width: 32px; height: 32px; stroke: var(--tx3); fill: none; stroke-width: 1.2; stroke-linecap: round; opacity: .5; }
.editor-empty h3 { font-family: var(--fh); font-size: 1.125rem; font-weight: 900; color: var(--tx2); letter-spacing: -.03em; margin-bottom: 6px; }
.editor-empty p  { font-size: .8125rem; color: var(--tx3); line-height: 1.6; }

.editor-form { padding: 24px 26px 44px; }

/* Section label */
.sec-label {
    font-size: .5625rem; font-weight: 700; color: var(--tx3); text-transform: uppercase;
    letter-spacing: 1px; margin-bottom: 8px; display: flex; align-items: center; gap: 8px;
}
.sec-label::after { content: ''; flex: 1; height: 1px; background: var(--bd); }
.sec-label .hint { text-transform: none; font-weight: 400; letter-spacing: 0; font-size: .5rem; }

/* Inputs */
.field { margin-bottom: 18px; }
.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 18px; }
.fi {
    width: 100%; padding: 10px 13px; background: var(--sf); border: 1.5px solid var(--bd);
    border-radius: 9px; font-family: var(--ff); font-size: .875rem; color: var(--tx);
    outline: none; transition: border-color .18s, box-shadow .18s; line-height: 1.6;
}
.fi::placeholder { color: var(--tx3); }
.fi:focus { border-color: var(--ac); box-shadow: 0 0 0 3px rgba(31,226,144,.09); }
textarea.fi { resize: vertical; min-height: 86px; }
select.fi {
    cursor: pointer; appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%235a8580' stroke-width='2' stroke-linecap='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 12px center;
}

/* Stem LaTeX preview */
.stem-preview {
    margin-top: 7px; padding: 9px 13px; background: var(--sf); border: 1px solid var(--bd);
    border-radius: 8px; min-height: 36px; font-size: .9rem; color: var(--tx); line-height: 1.65;
}

/* Answer options */
.opt-row { display: flex; align-items: center; gap: 8px; margin-bottom: 7px; }
.opt-radio { width: 15px; height: 15px; accent-color: var(--ac); flex-shrink: 0; cursor: pointer; }
.opt-key {
    width: 22px; height: 22px; border-radius: 5px; background: var(--sf3);
    display: flex; align-items: center; justify-content: center;
    font-size: .5625rem; font-weight: 800; color: var(--tx3); flex-shrink: 0;
}

/* ── EXPLANATION IMAGE SECTION ── */
.exp-section { margin-top: 24px; border-top: 1px solid var(--bd); padding-top: 22px; }
.exp-section-head { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; }
.exp-section-icon {
    width: 32px; height: 32px; border-radius: 8px; flex-shrink: 0;
    background: linear-gradient(135deg, rgba(31,226,144,.12), rgba(31,226,144,.05));
    border: 1px solid rgba(31,226,144,.2); display: flex; align-items: center; justify-content: center;
}
.exp-section-icon svg { width: 14px; height: 14px; stroke: var(--ac); fill: none; stroke-width: 1.8; stroke-linecap: round; }
.exp-section-title { font-size: .875rem; font-weight: 700; color: var(--tx); margin-bottom: 2px; }
.exp-section-sub   { font-size: .5625rem; color: var(--tx3); line-height: 1.5; }

/* Image upload drop zone */
.img-drop-zone {
    border: 2px dashed rgba(31,226,144,.22); border-radius: 14px;
    background: var(--ac3); padding: 32px 24px; text-align: center;
    cursor: pointer; transition: all .25s; position: relative; overflow: hidden;
}
.img-drop-zone:hover, .img-drop-zone.drag-over {
    border-color: var(--ac); background: var(--ac4);
    box-shadow: 0 0 0 4px rgba(31,226,144,.1);
}
.img-drop-zone.has-image { border-style: solid; border-color: rgba(31,226,144,.3); padding: 0; background: transparent; }
.img-drop-zone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
.idz-icon {
    width: 52px; height: 52px; border-radius: 14px; margin: 0 auto 14px;
    background: rgba(31,226,144,.1); border: 1px solid rgba(31,226,144,.2);
    display: flex; align-items: center; justify-content: center;
}
.idz-icon svg { width: 22px; height: 22px; stroke: var(--ac); fill: none; stroke-width: 1.6; stroke-linecap: round; }
.idz-title { font-size: .875rem; font-weight: 700; color: var(--tx); margin-bottom: 5px; }
.idz-sub   { font-size: .6875rem; color: var(--tx3); line-height: 1.6; margin-bottom: 10px; }
.idz-formats { display: flex; justify-content: center; gap: 5px; flex-wrap: wrap; }
.idz-fmt {
    font-family: var(--fm); font-size: .4rem; font-weight: 700; padding: 2px 7px;
    border-radius: 4px; background: rgba(31,226,144,.1); border: 1px solid rgba(31,226,144,.18);
    color: var(--ac); text-transform: uppercase; letter-spacing: .3px;
}

/* Image preview inside the drop zone */
.img-preview-wrap {
    position: relative; border-radius: 12px; overflow: hidden;
    background: #000; min-height: 80px;
}
.img-preview-wrap img {
    width: 100%; height: auto; display: block; max-height: 280px;
    object-fit: contain; border-radius: 12px;
    opacity: 0; transition: opacity .4s ease;
}
.img-preview-wrap img.loaded { opacity: 1; }
.img-preview-actions {
    position: absolute; top: 10px; right: 10px; display: flex; gap: 6px;
}
.img-action-btn {
    display: flex; align-items: center; gap: 5px; padding: 6px 11px;
    border-radius: 8px; font-family: var(--ff); font-size: .6875rem; font-weight: 700;
    cursor: pointer; border: none; backdrop-filter: blur(8px); transition: all .18s;
}
.img-action-btn svg { width: 11px; height: 11px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; }
.btn-replace { background: rgba(255,255,255,.9); color: #0a1a18; }
.btn-replace:hover { background: #fff; }
.btn-remove  { background: rgba(239,68,68,.85); color: #fff; }
.btn-remove:hover { background: var(--err); }
.img-preview-bar {
    padding: 8px 12px; border-top: 1px solid rgba(255,255,255,.08);
    display: flex; align-items: center; gap: 8px;
    background: rgba(0,0,0,.5); backdrop-filter: blur(4px);
}
.img-preview-name { font-family: var(--fm); font-size: .5625rem; color: rgba(255,255,255,.5); flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.img-preview-size { font-family: var(--fm); font-size: .5625rem; color: rgba(255,255,255,.3); flex-shrink: 0; }

/* Upload progress */
.upload-progress {
    position: absolute; inset: 0; display: flex; flex-direction: column;
    align-items: center; justify-content: center; gap: 12px;
    background: rgba(10,26,24,.92); backdrop-filter: blur(8px); border-radius: 12px;
}
.upload-progress.hidden { display: none; }
.upload-spinner {
    width: 36px; height: 36px; border-radius: 50%;
    border: 3px solid rgba(31,226,144,.2); border-top-color: var(--ac);
    animation: spin .8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.upload-progress-label { font-size: .75rem; font-weight: 600; color: var(--tx2); }

/* Plain text fallback for explanation */
.exp-text-section { margin-top: 16px; }
.exp-or-divider {
    display: flex; align-items: center; gap: 10px; margin: 16px 0;
    font-size: .5625rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: 1px;
}
.exp-or-divider::before, .exp-or-divider::after { content: ''; flex: 1; height: 1px; background: var(--bd); }

/* Editor actions */
.editor-actions { display: flex; gap: 8px; margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--bd); flex-wrap: wrap; }

/* ══════════════════════════════════════════════════
   RIGHT — live preview (WHITE THEME)
══════════════════════════════════════════════════ */
.col-preview { background: var(--pv-bg); border-left: 1px solid #dde3e1; overflow-y: auto; display: flex; flex-direction: column; }
.col-preview::-webkit-scrollbar { width: 3px; }
.col-preview::-webkit-scrollbar-thumb { background: #d1d9d6; border-radius: 2px; }

.pv-head { padding: 14px 16px; border-bottom: 1px solid #e5eae8; display: flex; align-items: center; gap: 8px; flex-shrink: 0; background: #fff; }
.pv-head-icon { width: 22px; height: 22px; border-radius: 6px; background: rgba(31,226,144,.1); display: flex; align-items: center; justify-content: center; }
.pv-head-icon svg { width: 11px; height: 11px; stroke: #13c474; fill: none; stroke-width: 2; stroke-linecap: round; }
.pv-head-title { font-size: .8125rem; font-weight: 700; color: var(--pv-tx); }
.pv-head-sub   { font-size: .5625rem; color: var(--pv-tx3); margin-left: auto; }

.stats-bar { display: grid; grid-template-columns: repeat(4,1fr); border-bottom: 1px solid #e5eae8; flex-shrink: 0; background: #fff; }
.stat { padding: 10px 0; text-align: center; border-right: 1px solid #e5eae8; }
.stat:last-child { border-right: none; }
.stat-val { font-family: var(--fm); font-size: 1rem; font-weight: 700; color: var(--pv-tx); line-height: 1; }
.stat-lbl { font-size: .3875rem; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: var(--pv-tx3); margin-top: 3px; }
.stat .easy { color: #10b981; } .stat .medium { color: #f59e0b; } .stat .hard { color: #ef4444; }

.pv-inner { padding: 16px; flex: 1; overflow-y: auto; }
.pv-empty { padding: 48px 20px; text-align: center; color: var(--pv-tx3); font-size: .8125rem; }
.pv-empty svg { width: 36px; height: 36px; stroke: #c8d5d2; fill: none; stroke-width: 1; margin-bottom: 12px; display: block; margin-left: auto; margin-right: auto; }
.pv-empty p { color: #9ca3af; font-size: .8125rem; }

/* Preview question card — mirrors quiz.php exactly */
.pv-card { background: #fff; border: 1.5px solid #e5eae8; border-radius: 14px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,.05); }
.pv-card-body { padding: 18px 18px 14px; }
.pv-q-num { font-family: var(--fm); font-size: .5rem; color: var(--pv-tx3); background: #f5f7f6; padding: 2px 8px; border-radius: 4px; display: inline-block; margin-bottom: 10px; }
.pv-difficulty { display: inline-flex; align-items: center; gap: 4px; font-size: .5625rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 10px; margin-left: 6px; }
.pv-diff-easy { color: #10b981; } .pv-diff-medium { color: #f59e0b; } .pv-diff-hard { color: #ef4444; }
.pv-stem { font-size: .9375rem; font-weight: 500; color: var(--pv-tx); line-height: 1.68; margin-bottom: 14px; }
.pv-choice {
    display: flex; align-items: center; gap: 10px; padding: 10px 12px;
    border-radius: 10px; border: 1.5px solid #e5eae8; margin-bottom: 6px;
    background: #fff; transition: border-color .15s;
}
.pv-choice.correct { border-color: rgba(16,185,129,.3); background: rgba(16,185,129,.04); }
.pv-choice-key {
    width: 26px; height: 26px; border-radius: 7px; background: #f5f7f6; border: 1.5px solid #e5eae8;
    display: flex; align-items: center; justify-content: center;
    font-family: var(--fm); font-size: .625rem; font-weight: 700; color: var(--pv-tx3); flex-shrink: 0;
}
.pv-choice.correct .pv-choice-key { background: #10b981; border-color: #10b981; color: #fff; }
.pv-choice-text { font-size: .8125rem; color: var(--pv-tx2); line-height: 1.45; }

/* Preview explanation — image card */
.pv-exp-section { border-top: 1px solid #e5eae8; }
.pv-exp-header { padding: 10px 16px 8px; display: flex; align-items: center; gap: 8px; }
.pv-exp-label { font-size: .5rem; font-weight: 800; text-transform: uppercase; letter-spacing: .8px; color: #9ca3af; }
.pv-exp-img { width: 100%; height: auto; display: block; cursor: zoom-in; }
.pv-exp-img-wrap { position: relative; overflow: hidden; background: #f9fbfa; }
.pv-exp-img-wrap img { width: 100%; height: auto; max-height: 220px; object-fit: contain; display: block; }
.pv-exp-text { padding: 12px 16px 14px; font-size: .8125rem; color: var(--pv-tx2); line-height: 1.7; }
.pv-exp-text p { margin: 0 0 .5em; } .pv-exp-text p:last-child { margin: 0; }
.pv-no-exp { padding: 16px; text-align: center; }
.pv-no-exp-inner { border: 1.5px dashed #dde3e1; border-radius: 10px; padding: 20px; }
.pv-no-exp-inner svg { width: 24px; height: 24px; stroke: #c8d5d2; fill: none; margin-bottom: 6px; display: block; margin-left: auto; margin-right: auto; }
.pv-no-exp-inner p { font-size: .6875rem; color: #9ca3af; }

.pv-nav { display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; border-top: 1px solid #e5eae8; flex-shrink: 0; background: #fff; }
.pv-nav-btn {
    display: flex; align-items: center; gap: 5px; padding: 5px 11px; border-radius: 7px;
    background: #f5f7f6; border: 1px solid #e5eae8; color: var(--pv-tx2);
    font-size: .6875rem; font-weight: 700; cursor: pointer; transition: all .14s; font-family: var(--ff);
}
.pv-nav-btn:hover { background: #edf0ef; color: var(--pv-tx); }
.pv-nav-btn:disabled { opacity: .35; cursor: not-allowed; }
.pv-nav-btn svg { width: 11px; height: 11px; stroke: currentColor; fill: none; stroke-width: 2.2; stroke-linecap: round; }

/* ══════════════════════════════════════════════════
   TOAST
══════════════════════════════════════════════════ */
.toast-wrap { position: fixed; bottom: 20px; right: 20px; z-index: 999; display: flex; flex-direction: column; gap: 7px; }
.toast {
    padding: 11px 16px; border-radius: 9px; font-size: .75rem; font-weight: 600;
    border: 1px solid var(--bd2); box-shadow: 0 8px 28px rgba(0,0,0,.45);
    transform: translateX(120%); transition: transform .28s cubic-bezier(.16,1,.3,1); max-width: 300px;
}
.toast.show { transform: none; }
.toast.success { background: rgba(31,226,144,.12); color: var(--ac); border-color: rgba(31,226,144,.2); }
.toast.error   { background: rgba(239,68,68,.12);  color: var(--err); border-color: rgba(239,68,68,.18); }
.toast.info    { background: rgba(59,130,246,.12);  color: var(--blue); border-color: rgba(59,130,246,.18); }

/* ══════════════════════════════════════════════════
   RESPONSIVE
══════════════════════════════════════════════════ */
@media (max-width: 1024px) { .layout { grid-template-columns: 230px 1fr; } .col-preview { display: none; } }
@media (max-width: 640px)  { .layout { grid-template-columns: 1fr; } .col-list { display: none; } }
</style>
</head>
<body>

<header class="topbar">
    <a href="/quizzes/edit.php?id=<?= $quizId ?>" class="tb-back">
        <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>Back
    </a>
    <div class="tb-divider"></div>
    <div class="tb-quiz-name">
        <strong><?= htmlspecialchars($quiz['title']) ?></strong>
        <span>/ Builder</span>
        <span class="tb-status <?= htmlspecialchars($quiz['status']) ?>"><?= ucfirst(htmlspecialchars($quiz['status'])) ?></span>
    </div>
    <div class="tb-spacer"></div>
    <div class="autosave">
        <span class="as-dot" id="asDot"></span>
        <span id="asLabel">All saved</span>
    </div>
    <button class="btn btn-ghost btn-sm" onclick="saveAll()">
        <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>Save
    </button>
    <a href="/quizzes/preview.php?id=<?= $quizId ?>" target="_blank" class="btn btn-ghost btn-sm">
        <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>Preview
    </a>
    <button class="btn btn-publish btn-sm" onclick="saveAll('published')">
        <svg viewBox="0 0 24 24"><path d="M12 2l3 7h7l-5.5 4.5 2 7L12 17l-6.5 3.5 2-7L2 9h7z" fill="var(--dk)" stroke="none"/></svg>Publish
    </button>
</header>

<div class="layout">

    <!-- LEFT: question list -->
    <div class="col col-list">
        <div class="cl-head">
            <div style="display:flex;align-items:center;gap:8px">
                <span class="cl-head-title">Questions</span>
                <span class="cl-count" id="qCount">0</span>
            </div>
        </div>
        <div class="cl-search">
            <svg class="cl-search-ico" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" class="cl-search-input" id="searchInput" placeholder="Filter questions…" oninput="filterList()">
        </div>
        <div class="q-list" id="qList"></div>
        <button class="add-q-btn" onclick="addQuestion()">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
            Add Question
        </button>
    </div>

    <!-- MIDDLE: editor -->
    <div class="col col-editor" id="colEditor">
        <div class="editor-empty" id="editorEmpty">
            <div class="editor-empty-icon">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3" stroke-linecap="round"/><line x1="12" y1="17" x2="12.01" y2="17" stroke-width="2.5"/></svg>
            </div>
            <h3>Select a question</h3>
            <p>Choose from the list or add a new one to start editing.</p>
        </div>
        <div class="editor-form" id="editorForm" style="display:none"></div>
    </div>

    <!-- RIGHT: live preview -->
    <div class="col col-preview">
        <div class="pv-head">
            <div class="pv-head-icon">
                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </div>
            <span class="pv-head-title">Student View</span>
            <span class="pv-head-sub" id="pvSub">No question selected</span>
        </div>
        <div class="stats-bar">
            <div class="stat"><div class="stat-val" id="stTotal">0</div><div class="stat-lbl">Total</div></div>
            <div class="stat"><div class="stat-val easy" id="stEasy">0</div><div class="stat-lbl">Easy</div></div>
            <div class="stat"><div class="stat-val medium" id="stMed">0</div><div class="stat-lbl">Med</div></div>
            <div class="stat"><div class="stat-val hard" id="stHard">0</div><div class="stat-lbl">Hard</div></div>
        </div>
        <div class="pv-inner" id="pvInner">
            <div class="pv-empty">
                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <p>Select a question to preview</p>
            </div>
        </div>
        <div class="pv-nav">
            <button class="pv-nav-btn" id="pvPrev" onclick="navigateQ(-1)" disabled>
                <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>Prev
            </button>
            <span style="font-family:var(--fm);font-size:.5625rem;color:var(--pv-tx3)" id="pvPosition">—</span>
            <button class="pv-nav-btn" id="pvNext" onclick="navigateQ(1)" disabled>
                Next <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </div>
    </div>

</div>

<div class="toast-wrap" id="toastWrap"></div>

<script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
<script>
/* ══════════════════════════════════════════════════════════════════
   CONSTANTS
══════════════════════════════════════════════════════════════════ */
const QUIZ_ID     = <?= (int)$quizId ?>;
const QUIZ_STATUS = <?= json_encode($quiz['status']) ?>;
const HAS_EXP_IMG = <?= $hasExpImage ? 'true' : 'false' ?>;
const TOPICS      = <?= json_encode($topics, JSON_HEX_TAG) ?>;
const SAVE_URL    = <?= json_encode($saveUrl) ?>;
const UPLOAD_URL  = <?= json_encode($uploadUrl) ?>;

/* ══════════════════════════════════════════════════════════════════
   STATE
══════════════════════════════════════════════════════════════════ */
let questions = <?= json_encode($questions, JSON_HEX_TAG) ?>;
let selIdx    = questions.length > 0 ? 0 : null;
let dirty     = false;
let saveTimer = null;

// Ensure explanation_image field exists on every question object
questions.forEach(q => { if (!q.explanation_image) q.explanation_image = ''; });

/* ══════════════════════════════════════════════════════════════════
   QUESTION LIST
══════════════════════════════════════════════════════════════════ */
function renderList(filterText = '') {
    const list   = document.getElementById('qList');
    list.innerHTML = '';
    const filter = filterText.toLowerCase();

    questions.forEach((q, i) => {
        if (filter && !(q.stem || '').toLowerCase().includes(filter)) return;

        const item = document.createElement('div');
        item.className   = 'q-item' + (selIdx === i ? ' selected' : '');
        item.dataset.idx = i;
        const hasImg     = !!(q.explanation_image || '').trim();
        item.innerHTML = `
            <span class="q-drag"><svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="8" y1="18" x2="16" y2="18"/></svg></span>
            <span class="q-num">${i + 1}</span>
            <span class="q-stem-txt">${esc((q.stem || 'New question…').substring(0, 52))}</span>
            ${hasImg ? `<span class="q-has-img" title="Has explanation image"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></span>` : ''}
            <span class="q-diff-dot ${q.difficulty || 'medium'}"></span>
            <button class="q-del" onclick="deleteQ(${i}, event)"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>`;

        item.addEventListener('click', e => { if (e.target.closest('.q-del')) return; selectQ(i); });
        list.appendChild(item);
    });

    document.getElementById('qCount').textContent  = questions.length;
    document.getElementById('stTotal').textContent = questions.length;
    document.getElementById('stEasy').textContent  = questions.filter(q => q.difficulty === 'easy').length;
    document.getElementById('stMed').textContent   = questions.filter(q => !q.difficulty || q.difficulty === 'medium').length;
    document.getElementById('stHard').textContent  = questions.filter(q => q.difficulty === 'hard').length;

    initDrag();
    updatePvNav();
}

function filterList() { renderList(document.getElementById('searchInput')?.value || ''); }

/* ══════════════════════════════════════════════════════════════════
   SELECTION
══════════════════════════════════════════════════════════════════ */
function selectQ(idx) {
    if (selIdx !== null && selIdx !== idx) syncQ();
    selIdx = idx;
    renderList(document.getElementById('searchInput')?.value || '');
    renderEditor(questions[idx]);
    renderPreview(questions[idx], idx);
}

function navigateQ(dir) {
    const next = (selIdx ?? 0) + dir;
    if (next >= 0 && next < questions.length) selectQ(next);
}

function updatePvNav() {
    const prev = document.getElementById('pvPrev');
    const next = document.getElementById('pvNext');
    const pos  = document.getElementById('pvPosition');
    const sub  = document.getElementById('pvSub');
    if (prev) prev.disabled = (selIdx === null || selIdx === 0);
    if (next) next.disabled = (selIdx === null || selIdx >= questions.length - 1);
    if (pos)  pos.textContent = selIdx !== null ? `${selIdx + 1} / ${questions.length}` : '—';
    if (sub)  sub.textContent = selIdx !== null ? `Q${selIdx + 1} of ${questions.length}` : 'No question selected';
}

/* ══════════════════════════════════════════════════════════════════
   EDITOR RENDERING
══════════════════════════════════════════════════════════════════ */
function renderEditor(q) {
    document.getElementById('editorEmpty').style.display = 'none';
    const form = document.getElementById('editorForm');
    form.style.display = 'block';

    const topicOpts = TOPICS.length > 0
        ? `<option value="">— Not tagged —</option>` +
          TOPICS.map(t => `<option value="${t.id}" ${q.micro_topic_id == t.id ? 'selected':''}>${esc(t.name)}</option>`).join('')
        : null;

    const imgUrl = (q.explanation_image || '').trim();

    /* ── Explanation image section ── */
    const expSection = `
    <div class="exp-section">
        <div class="exp-section-head">
            <div class="exp-section-icon">
                <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            </div>
            <div>
                <div class="exp-section-title">Explanation Image</div>
                <div class="exp-section-sub">Shown to students after they answer — upload a diagram, worked solution, or annotated image</div>
            </div>
        </div>

        <div class="img-drop-zone ${imgUrl ? 'has-image' : ''}" id="imgDropZone">
            ${imgUrl ? `
            <div class="img-preview-wrap">
                <div class="upload-progress hidden" id="uploadProgress">
                    <div class="upload-spinner"></div>
                    <div class="upload-progress-label">Uploading…</div>
                </div>
                <img src="${esc(imgUrl)}" id="previewImg" alt="Explanation" onload="this.classList.add('loaded')">
                <div class="img-preview-actions">
                    <button class="img-action-btn btn-replace" onclick="triggerReplace(event)">
                        <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        Replace
                    </button>
                    <button class="img-action-btn btn-remove" onclick="removeImage(event)">
                        <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
                        Remove
                    </button>
                </div>
                <div class="img-preview-bar">
                    <span class="img-preview-name" id="previewName">${esc(imgUrl.split('/').pop())}</span>
                    <span class="img-preview-size" id="previewSize">Saved</span>
                </div>
            </div>` : `
            <div class="upload-progress hidden" id="uploadProgress">
                <div class="upload-spinner"></div>
                <div class="upload-progress-label">Uploading…</div>
            </div>
            <div class="idz-icon">
                <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            </div>
            <div class="idz-title">Upload explanation image</div>
            <div class="idz-sub">Drag &amp; drop here, paste from clipboard, or click to browse</div>
            <div class="idz-formats">
                <span class="idz-fmt">PNG</span>
                <span class="idz-fmt">JPG</span>
                <span class="idz-fmt">WebP</span>
                <span class="idz-fmt">GIF</span>
                <span class="idz-fmt">Max 5 MB</span>
            </div>`}
            <input type="file" id="imgFileInput" accept="image/jpeg,image/png,image/gif,image/webp" onchange="handleFileInput(this)">
        </div>

        <div class="exp-or-divider">or add a text explanation</div>
        <textarea class="fi" id="fExp" rows="3"
                  placeholder="Optional text explanation shown below the image…">${esc(q.explanation || '')}</textarea>
    </div>`;

    form.innerHTML = `
        <div class="sec-label">Question Stem <span class="hint">— $LaTeX$ inline supported</span></div>
        <div class="field">
            <textarea class="fi" id="fStem" rows="4" placeholder="Write your question here…">${esc(q.stem || '')}</textarea>
            <div class="stem-preview" id="stemPrev"><span style="color:var(--tx3);font-size:.75rem">LaTeX preview appears here…</span></div>
        </div>

        <div class="field-row">
            <div>
                <div class="sec-label">Difficulty</div>
                <select class="fi" id="fDiff">
                    <option value="easy"   ${q.difficulty === 'easy'                     ? 'selected' : ''}>Easy</option>
                    <option value="medium" ${!q.difficulty || q.difficulty === 'medium'  ? 'selected' : ''}>Medium</option>
                    <option value="hard"   ${q.difficulty === 'hard'                     ? 'selected' : ''}>Hard</option>
                </select>
            </div>
            <div>
                <div class="sec-label">Points</div>
                <input type="number" class="fi" id="fPoints" value="${q.points || 1}" min="1" max="5">
            </div>
        </div>

        ${topicOpts !== null ? `<div class="field"><div class="sec-label">Micro-Topic</div><select class="fi" id="fTopic">${topicOpts}</select></div>` : ''}

        <div class="sec-label">Answer Options <span class="hint">— select the correct answer</span></div>
        ${['a','b','c','d'].map(opt => `
        <div class="opt-row">
            <input type="radio" class="opt-radio" name="correct" value="${opt}" id="r_${opt}" ${(q.correct_answer || 'a') === opt ? 'checked' : ''}>
            <label for="r_${opt}" class="opt-key">${opt.toUpperCase()}</label>
            <input type="text" class="fi" id="fOpt${opt.toUpperCase()}" value="${esc(q['option_' + opt] || '')}" placeholder="Option ${opt.toUpperCase()}" style="margin:0">
        </div>`).join('')}

        <div class="field" style="margin-top:16px">
            <div class="sec-label">Hint <span class="hint">(optional)</span></div>
            <textarea class="fi" id="fHint" rows="2" placeholder="A helpful nudge without giving away the answer…">${esc(q.hint || '')}</textarea>
        </div>

        ${expSection}

        <div class="editor-actions">
            <button class="btn btn-primary" onclick="saveQ()">
                <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>Save Question
            </button>
            <button class="btn btn-ghost" onclick="duplicateQ(${selIdx})">
                <svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>Duplicate
            </button>
            <button class="btn btn-danger" onclick="deleteQ(${selIdx}, null)">
                <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>Delete
            </button>
        </div>`;

    /* Wire input events */
    document.getElementById('fStem').addEventListener('input', () => { syncQ(); renderStemLatex(); });
    document.getElementById('fDiff').addEventListener('change', syncQ);
    document.getElementById('fPoints').addEventListener('input', syncQ);
    document.getElementById('fTopic')?.addEventListener('change', syncQ);
    document.querySelectorAll('input[name="correct"]').forEach(r => r.addEventListener('change', syncQ));
    ['A','B','C','D'].forEach(l => document.getElementById(`fOpt${l}`)?.addEventListener('input', syncQ));
    document.getElementById('fHint')?.addEventListener('input', syncQ);
    document.getElementById('fExp')?.addEventListener('input', syncQ);

    /* Wire drop zone drag events */
    const zone = document.getElementById('imgDropZone');
    if (zone) {
        zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
        zone.addEventListener('dragleave', e => { if (!zone.contains(e.relatedTarget)) zone.classList.remove('drag-over'); });
        zone.addEventListener('drop', e => {
            e.preventDefault();
            zone.classList.remove('drag-over');
            const file = e.dataTransfer?.files[0];
            if (file && file.type.startsWith('image/')) uploadImage(file);
        });
    }

    /* Wire paste anywhere on page */
    document.onpaste = e => {
        const items = e.clipboardData?.items || [];
        for (const item of items) {
            if (item.type.startsWith('image/')) {
                const file = item.getAsFile();
                if (file) { uploadImage(file); break; }
            }
        }
    };

    renderStemLatex();
    document.getElementById('colEditor').scrollTop = 0;
}

/* ══════════════════════════════════════════════════════════════════
   IMAGE UPLOAD
══════════════════════════════════════════════════════════════════ */
function handleFileInput(input) {
    const file = input.files[0];
    if (file) uploadImage(file);
    input.value = '';
}

function triggerReplace(e) {
    e.stopPropagation();
    document.getElementById('imgFileInput')?.click();
}

function removeImage(e) {
    e.stopPropagation();
    if (!confirm('Remove this explanation image?')) return;
    questions[selIdx].explanation_image = '';
    renderEditor(questions[selIdx]);
    renderPreview(questions[selIdx], selIdx);
    renderList(document.getElementById('searchInput')?.value || '');
    markDirty();
    toast('Image removed.', 'info');
}

async function uploadImage(file) {
    if (file.size > 5 * 1024 * 1024) { toast('Image exceeds 5 MB limit.', 'error'); return; }
    if (!file.type.startsWith('image/')) { toast('Please upload an image file.', 'error'); return; }

    const progress = document.getElementById('uploadProgress');
    if (progress) progress.classList.remove('hidden');

    const fd = new FormData();
    fd.append('image', file);

    try {
        const res  = await fetch(UPLOAD_URL, { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success && data.url) {
            questions[selIdx].explanation_image = data.url;
            renderEditor(questions[selIdx]);
            renderPreview(questions[selIdx], selIdx);
            renderList(document.getElementById('searchInput')?.value || '');
            markDirty();
            toast('Image uploaded successfully.', 'success');
        } else {
            if (progress) progress.classList.add('hidden');
            toast(data.error || 'Upload failed.', 'error');
        }
    } catch (err) {
        if (progress) progress.classList.add('hidden');
        toast('Upload error: ' + err.message, 'error');
    }
}

/* ══════════════════════════════════════════════════════════════════
   SYNC FIELDS → QUESTIONS ARRAY
══════════════════════════════════════════════════════════════════ */
function syncQ() {
    if (selIdx === null) return;
    const q          = questions[selIdx];
    q.stem           = document.getElementById('fStem')?.value     || '';
    q.difficulty     = document.getElementById('fDiff')?.value     || 'medium';
    q.points         = parseInt(document.getElementById('fPoints')?.value) || 1;
    q.micro_topic_id = document.getElementById('fTopic')?.value    || null;
    q.correct_answer = document.querySelector('input[name="correct"]:checked')?.value || 'a';
    q.option_a       = document.getElementById('fOptA')?.value     || '';
    q.option_b       = document.getElementById('fOptB')?.value     || '';
    q.option_c       = document.getElementById('fOptC')?.value     || '';
    q.option_d       = document.getElementById('fOptD')?.value     || '';
    q.hint           = document.getElementById('fHint')?.value     || '';
    q.explanation    = document.getElementById('fExp')?.value      || '';
    renderPreview(q, selIdx);
    markDirty();
}

/* ══════════════════════════════════════════════════════════════════
   STEM LATEX PREVIEW
══════════════════════════════════════════════════════════════════ */
function renderStemLatex() {
    const stem = document.getElementById('fStem')?.value || '';
    const el   = document.getElementById('stemPrev');
    if (!el || typeof katex === 'undefined') return;
    if (!stem.trim()) {
        el.innerHTML = '<span style="color:var(--tx3);font-size:.75rem">LaTeX preview appears here…</span>';
        return;
    }
    el.innerHTML = stem.replace(/\$([^$\n]+)\$/g, (_, m) => {
        try   { return katex.renderToString(m, { throwOnError: false }); }
        catch { return `<span style="color:var(--err)">[invalid LaTeX]</span>`; }
    });
}

/* ══════════════════════════════════════════════════════════════════
   LIVE PREVIEW PANEL — white theme, mirrors quiz.php
══════════════════════════════════════════════════════════════════ */
function renderPreview(q, idx) {
    const inner = document.getElementById('pvInner');
    if (!q) {
        inner.innerHTML = `<div class="pv-empty"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg><p>Select a question to preview</p></div>`;
        updatePvNav(); return;
    }

    const opts   = ['a','b','c','d'].filter(k => q['option_' + k]);
    const imgUrl = (q.explanation_image || '').trim();
    const diffCls = { easy:'pv-diff-easy', medium:'pv-diff-medium', hard:'pv-diff-hard' }[q.difficulty || 'medium'] || 'pv-diff-medium';
    const diffLbl = (q.difficulty || 'medium').charAt(0).toUpperCase() + (q.difficulty || 'medium').slice(1);

    let expHtml = '';
    if (imgUrl) {
        expHtml = `
        <div class="pv-exp-section">
            <div class="pv-exp-header">
                <span class="pv-exp-label">Explanation</span>
            </div>
            <div class="pv-exp-img-wrap">
                <img src="${esc(imgUrl)}" alt="Explanation" loading="lazy">
            </div>
            ${q.explanation ? `<div class="pv-exp-text"><p>${esc(q.explanation)}</p></div>` : ''}
        </div>`;
    } else if (q.explanation) {
        expHtml = `
        <div class="pv-exp-section">
            <div class="pv-exp-header"><span class="pv-exp-label">Explanation</span></div>
            <div class="pv-exp-text"><p>${esc(q.explanation)}</p></div>
        </div>`;
    } else {
        expHtml = `
        <div class="pv-no-exp">
            <div class="pv-no-exp-inner">
                <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                <p>No explanation image yet — upload one above</p>
            </div>
        </div>`;
    }

    inner.innerHTML = `
    <div class="pv-card">
        <div class="pv-card-body">
            <div>
                <span class="pv-q-num">Q${idx + 1}</span>
                <span class="pv-difficulty ${diffCls}">· ${diffLbl}</span>
            </div>
            <div class="pv-stem" id="pvStemEl">${esc(q.stem || 'No stem yet…')}</div>
            ${opts.map(k => `
            <div class="pv-choice ${k === q.correct_answer ? 'correct' : ''}">
                <div class="pv-choice-key">${k.toUpperCase()}</div>
                <div class="pv-choice-text">${esc(q['option_' + k] || '')}</div>
            </div>`).join('')}
        </div>
        ${expHtml}
    </div>`;

    /* KaTeX in stem */
    const stemEl = document.getElementById('pvStemEl');
    if (stemEl && typeof katex !== 'undefined') {
        stemEl.innerHTML = (q.stem || 'No stem yet…').replace(/\$([^$\n]+)\$/g, (_, m) => {
            try   { return katex.renderToString(m, { throwOnError: false }); }
            catch { return esc(m); }
        });
    }

    updatePvNav();
}

/* ══════════════════════════════════════════════════════════════════
   CRUD
══════════════════════════════════════════════════════════════════ */
function addQuestion() {
    const q = {
        id: null, stem: '', type: 'mcq',
        option_a: '', option_b: '', option_c: '', option_d: '',
        correct_answer: 'a', difficulty: 'medium',
        micro_topic_id: null, hint: '',
        explanation: '', explanation_html: '', explanation_image: '',
        points: 1, position: questions.length + 1,
    };
    questions.push(q);
    selIdx = questions.length - 1;
    renderList();
    renderEditor(q);
    renderPreview(q, selIdx);
    markDirty();
    setTimeout(() => document.getElementById('fStem')?.focus(), 60);
}

function saveQ() {
    syncQ();
    renderList(document.getElementById('searchInput')?.value || '');
    toast('Question saved.', 'success');
    scheduleAutoSave();
}

function deleteQ(idx, e) {
    if (e) e.stopPropagation();
    if (!confirm('Delete this question?')) return;
    questions.splice(idx, 1);
    questions.forEach((q, i) => q.position = i + 1);
    selIdx = questions.length > 0 ? Math.min(idx, questions.length - 1) : null;
    renderList();
    if (selIdx !== null) {
        renderEditor(questions[selIdx]);
        renderPreview(questions[selIdx], selIdx);
    } else {
        document.getElementById('editorEmpty').style.display = 'flex';
        document.getElementById('editorForm').style.display  = 'none';
        document.getElementById('pvInner').innerHTML =
            `<div class="pv-empty"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg><p>Select a question to preview</p></div>`;
        updatePvNav();
    }
    markDirty();
    saveAll();
}

function duplicateQ(idx) {
    const clone = { ...questions[idx], id: null, position: questions.length + 1 };
    questions.push(clone);
    selIdx = questions.length - 1;
    renderList();
    renderEditor(clone);
    renderPreview(clone, selIdx);
    markDirty();
    toast('Question duplicated.', 'info');
}

/* ══════════════════════════════════════════════════════════════════
   DRAG AND DROP REORDER
══════════════════════════════════════════════════════════════════ */
function initDrag() {
    const list = document.getElementById('qList');
    let dragged = null;

    list.querySelectorAll('.q-item').forEach(item => {
        item.setAttribute('draggable', true);
        item.addEventListener('dragstart', e => {
            if (e.dataTransfer?.types?.includes('Files')) { e.preventDefault(); return; }
            dragged = item;
            setTimeout(() => { item.style.opacity = '.4'; }, 0);
        });
        item.addEventListener('dragend', () => {
            item.style.opacity = '';
            list.querySelectorAll('.q-item').forEach(i => i.classList.remove('dragging-over'));
            dragged = null;
            reorderFromDOM();
        });
        item.addEventListener('dragover', e => {
            if (e.dataTransfer?.types?.includes('Files')) return;
            e.preventDefault();
            if (!dragged || dragged === item) return;
            list.querySelectorAll('.q-item').forEach(i => i.classList.remove('dragging-over'));
            item.classList.add('dragging-over');
            const r = item.getBoundingClientRect();
            list.insertBefore(dragged, e.clientY > r.top + r.height / 2 ? item.nextSibling : item);
        });
    });
}

function reorderFromDOM() {
    const idxs = [...document.querySelectorAll('.q-item')].map(el => parseInt(el.dataset.idx));
    questions  = idxs.map(i => questions[i]).filter(Boolean);
    questions.forEach((q, i) => q.position = i + 1);
    selIdx = questions.length > 0 ? 0 : null;
    renderList();
    if (selIdx !== null) { renderEditor(questions[selIdx]); renderPreview(questions[selIdx], selIdx); }
    markDirty();
    scheduleAutoSave();
}

/* ══════════════════════════════════════════════════════════════════
   SAVE
══════════════════════════════════════════════════════════════════ */
function markDirty() { dirty = true; setAutosave('unsaved'); }
function scheduleAutoSave() { clearTimeout(saveTimer); saveTimer = setTimeout(() => saveAll(), 10_000); }

function setAutosave(s) {
    const dot   = document.getElementById('asDot');
    const label = document.getElementById('asLabel');
    if (!dot || !label) return;
    dot.className     = 'as-dot' + (s === 'saving' ? ' saving' : s === 'saved' ? ' saved' : '');
    label.textContent = s === 'saving' ? 'Saving…' : s === 'saved' ? 'All saved' : 'Unsaved changes';
}

async function saveAll(status = null) {
    if (selIdx !== null) syncQ();
    setAutosave('saving');

    const payload = {
        id:        QUIZ_ID,
        status:    status || QUIZ_STATUS,
        questions: questions.map((q, i) => ({
            id:               (typeof q.id === 'number' && q.id > 0) ? q.id : null,
            position:         i + 1,
            stem:             q.stem              || '',
            type:             q.type              || 'mcq',
            option_a:         q.option_a          || '',
            option_b:         q.option_b          || '',
            option_c:         q.option_c          || '',
            option_d:         q.option_d          || '',
            correct_answer:   q.correct_answer    || 'a',
            explanation:      q.explanation       || '',
            explanation_html: q.explanation_html  || '',
            explanation_image:q.explanation_image || '',
            hint:             q.hint              || '',
            difficulty:       q.difficulty        || 'medium',
            micro_topic_id:   q.micro_topic_id    || null,
            points:           parseInt(q.points)  || 1,
        }))
    };

    try {
        const res = await fetch(SAVE_URL, {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload),
        });

        let data;
        const ct = res.headers.get('content-type') || '';
        if (ct.includes('application/json')) {
            data = await res.json();
        } else {
            const raw = await res.text();
            setAutosave('unsaved');
            toast('Server error (' + res.status + '): ' + raw.substring(0, 200), 'error');
            return;
        }

        if (data.success) {
            if (data.question_ids) {
                questions.forEach((q, i) => { if (data.question_ids[i]) q.id = data.question_ids[i]; });
            }
            dirty = false;
            setAutosave('saved');
            if (status === 'published') {
                toast('🎉 Quiz published!', 'success');
                setTimeout(() => window.location.href = '/quizzes/index.php', 1400);
            } else {
                toast('Saved.', 'info');
            }
        } else {
            setAutosave('unsaved');
            toast(data.error || 'Save failed.', 'error');
        }
    } catch (err) {
        setAutosave('unsaved');
        toast('Network error: ' + err.message, 'error');
    }
}

/* ══════════════════════════════════════════════════════════════════
   HELPERS
══════════════════════════════════════════════════════════════════ */
function esc(s) {
    return String(s || '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
function toast(msg, type = 'info') {
    const wrap = document.getElementById('toastWrap');
    const el   = document.createElement('div');
    el.className   = `toast ${type}`;
    el.textContent = msg;
    wrap.appendChild(el);
    requestAnimationFrame(() => requestAnimationFrame(() => el.classList.add('show')));
    setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 380); }, 3500);
}

/* ══════════════════════════════════════════════════════════════════
   BOOT
══════════════════════════════════════════════════════════════════ */
renderList();
if (questions.length > 0) selectQ(0);

document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key === 's')         { e.preventDefault(); saveAll(); }
    if ((e.ctrlKey || e.metaKey) && e.key === 'ArrowDown') { e.preventDefault(); navigateQ(1); }
    if ((e.ctrlKey || e.metaKey) && e.key === 'ArrowUp')   { e.preventDefault(); navigateQ(-1); }
});
window.addEventListener('beforeunload', e => {
    if (dirty) { e.preventDefault(); e.returnValue = ''; }
});
</script>
</body>
</html>