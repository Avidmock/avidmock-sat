<?php
/**
 * quizzes/create.php
 * Create a new quiz — settings + questions with rich explanation editor.
 */
require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

$admin     = currentAdmin();
$db        = Database::connect();
$allTables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

// Detect rich explanation column
$questionCols = $db->query("SHOW COLUMNS FROM sat_quiz_questions")->fetchAll(PDO::FETCH_COLUMN);
$hasExpHtml   = in_array('explanation_html', $questionCols);

// Lesson options for the slug dropdown
$lessons = [];
if (in_array('lessons', $allTables)) {
    try {
        $lessons = $db->query(
            "SELECT slug, title FROM lessons WHERE status='published' ORDER BY title"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) { $lessons = []; }
} elseif (in_array('sat_quizzes', $allTables)) {
    try {
        $lessons = $db->query(
            "SELECT DISTINCT lesson_slug AS slug, lesson_slug AS title FROM sat_quizzes
             WHERE lesson_slug IS NOT NULL AND lesson_slug != ''
             ORDER BY lesson_slug"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) { $lessons = []; }
}

// Micro-topic options
$topics = [];
if (in_array('micro_topics', $allTables)) {
    try {
        $topics = $db->query(
            "SELECT id, name, domain, subject FROM micro_topics ORDER BY subject, domain, name"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) { $topics = []; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>New Quiz — Avidmock Admin</title>
<meta name="robots" content="noindex,nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700;9..40,800&family=Fraunces:ital,opsz,wght@0,9..144,900;1,9..144,300&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css">
<style>
/* ── Design tokens ────────────────────────────────────────────────────── */
:root {
    --ink:  #0c1f1d; --ink2: #0e2522; --dk:  #143230;
    --ac:   #1fe290; --ac2:  #13c474; --ac3:  rgba(31,226,144,.08); --ac4: rgba(31,226,144,.15);
    --tx:   #e8f3f1; --tx2:  #9dbfba; --tx3:  #5a8580;
    --bd:   rgba(255,255,255,.07);  --bd2: rgba(255,255,255,.13);
    --sf:   rgba(255,255,255,.04);  --sf2: rgba(255,255,255,.07); --sf3: rgba(255,255,255,.10);
    --warn: #f59e0b; --warn2: rgba(245,158,11,.12);
    --err:  #ef4444; --err2:  rgba(239,68,68,.12);
    --blue: #3b82f6; --blue2: rgba(59,130,246,.12);
    --ff: 'DM Sans', sans-serif;
    --fh: 'Fraunces', Georgia, serif;
    --fm: 'DM Mono', monospace;
    --sb-w:   240px;
    --top-h:  60px;
}
*,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body {
    height: 100%;
    font-family: var(--ff);
    background: var(--ink);
    color: var(--tx);
    -webkit-font-smoothing: antialiased;
}

/* ── Sidebar ──────────────────────────────────────────────────────────── */
.sb {
    position: fixed; top: 0; left: 0;
    width: var(--sb-w); height: 100vh;
    background: var(--ink2); border-right: 1px solid var(--bd);
    display: flex; flex-direction: column; overflow-y: auto;
    z-index: 300; transition: transform .3s cubic-bezier(.16,1,.3,1);
}
.sb::-webkit-scrollbar { width: 3px; }
.sb::-webkit-scrollbar-thumb { background: rgba(255,255,255,.06); border-radius: 2px; }
.sb-logo {
    display: flex; align-items: center; gap: 10px;
    padding: 0 18px; height: var(--top-h);
    border-bottom: 1px solid var(--bd);
    text-decoration: none; flex-shrink: 0;
}
.sb-logo-mark {
    width: 32px; height: 32px; border-radius: 8px;
    background: linear-gradient(135deg, var(--ac), var(--ac2));
    display: flex; align-items: center; justify-content: center;
}
.sb-logo-mark svg { width: 17px; height: 17px; fill: var(--dk); }
.sb-logo-name { font-size: .875rem; font-weight: 800; color: var(--tx); letter-spacing: -.02em; }
.sb-logo-sub  { font-size: .5625rem; color: var(--tx3); font-weight: 600; text-transform: uppercase; letter-spacing: .6px; }
.sb-nav { flex: 1; padding: 10px 0 16px; }
.sb-group { padding: 16px 18px 5px; font-size: .5rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: 1.2px; }
.sb-link {
    display: flex; align-items: center; gap: 9px;
    padding: 8px 10px; margin: 1px 8px; border-radius: 9px;
    text-decoration: none; font-size: .8125rem; font-weight: 600;
    color: var(--tx2); transition: all .15s; position: relative;
}
.sb-link:hover { background: var(--sf2); color: var(--tx); }
.sb-link.active { background: var(--ac3); color: var(--ac); }
.sb-link.active::before {
    content: ''; position: absolute; left: -10px; top: 50%; transform: translateY(-50%);
    width: 3px; height: 55%; background: var(--ac); border-radius: 0 2px 2px 0;
}
.sb-ico { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 1.85; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }
.sb-foot {
    margin: 8px; padding: 10px 12px;
    background: var(--sf); border: 1px solid var(--bd); border-radius: 10px;
    display: flex; align-items: center; gap: 9px;
}
.sb-ava {
    width: 30px; height: 30px; border-radius: 8px;
    background: linear-gradient(135deg, var(--ac), var(--ac2));
    display: flex; align-items: center; justify-content: center;
    font-size: .6875rem; font-weight: 800; color: var(--dk); flex-shrink: 0;
}
.sb-name { font-size: .75rem; font-weight: 700; color: var(--tx); }
.sb-role { font-size: .5625rem; color: var(--tx3); text-transform: capitalize; }
.sb-out {
    margin-left: auto; padding: 5px; background: none; border: none;
    cursor: pointer; color: var(--tx3); line-height: 0;
    transition: all .15s; border-radius: 6px;
}
.sb-out:hover { background: var(--err2); color: var(--err); }
.sb-out svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.sb-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.6); z-index: 250;
    opacity: 0; transition: opacity .25s; pointer-events: none;
}
.sb-overlay.show { display: block; opacity: 1; pointer-events: all; }

/* ── Topbar ───────────────────────────────────────────────────────────── */
.topbar {
    position: fixed; top: 0; left: var(--sb-w); right: 0; height: var(--top-h);
    background: rgba(12,31,29,.93); backdrop-filter: blur(16px);
    border-bottom: 1px solid var(--bd);
    display: flex; align-items: center; padding: 0 28px; gap: 12px; z-index: 200;
}
.topbar-ham {
    display: none; width: 34px; height: 34px; border-radius: 8px;
    border: 1px solid var(--bd); background: var(--sf);
    align-items: center; justify-content: center;
    cursor: pointer; flex-direction: column; gap: 4px; padding: 9px;
}
.topbar-ham span { display: block; height: 1.5px; background: var(--tx2); border-radius: 1px; width: 100%; }
.topbar-breadcrumb {
    display: flex; align-items: center; gap: 6px;
    font-family: var(--fh); font-size: .9375rem; font-weight: 900;
    color: var(--tx); letter-spacing: -.025em;
}
.topbar-breadcrumb a {
    color: var(--tx3); font-style: italic; font-weight: 300;
    text-decoration: none; transition: color .15s;
}
.topbar-breadcrumb a:hover { color: var(--ac); }
.topbar-breadcrumb svg { width: 12px; height: 12px; stroke: var(--tx3); fill: none; stroke-width: 2; stroke-linecap: round; }
.topbar-spacer { flex: 1; }
.autosave {
    display: flex; align-items: center; gap: 6px;
    font-family: var(--fm); font-size: .625rem; color: var(--tx3);
}
.autosave-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--tx3); transition: background .3s; }
.autosave-dot.saving { background: var(--warn); animation: blink .6s ease-in-out infinite alternate; }
.autosave-dot.saved  { background: var(--ac); }
@keyframes blink { to { opacity: .25; } }

/* ── Buttons ──────────────────────────────────────────────────────────── */
.btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border-radius: 9px;
    font-family: var(--ff); font-size: .8125rem; font-weight: 700;
    text-decoration: none; border: 1.5px solid transparent;
    cursor: pointer; transition: all .18s; white-space: nowrap;
}
.btn svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
.btn-sm  { padding: 6px 13px; font-size: .75rem; }
.btn-ghost   { background: var(--sf);  border-color: var(--bd);  color: var(--tx2); }
.btn-ghost:hover { background: var(--sf2); border-color: var(--bd2); color: var(--tx); }
.btn-primary { background: var(--ac);  color: var(--dk); }
.btn-primary:hover { background: var(--ac2); transform: translateY(-1px); box-shadow: 0 4px 16px rgba(31,226,144,.25); }
.btn-publish { background: linear-gradient(135deg, var(--ac), var(--ac2)); color: var(--dk); border: none; }
.btn-publish:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(31,226,144,.3); }
.btn-danger  { background: var(--err2); border-color: rgba(239,68,68,.2); color: var(--err); }
.btn-danger:hover { background: rgba(239,68,68,.22); }

/* ── Page layout ──────────────────────────────────────────────────────── */
.main {
    margin-left: var(--sb-w);
    margin-top: var(--top-h);
    display: grid;
    grid-template-columns: 1fr 288px;
    min-height: calc(100vh - var(--top-h));
    align-items: start;
}
.main-col {
    padding: 32px 28px;
    max-width: 860px;
}
.aside-col {
    padding: 24px 20px;
    background: var(--ink2);
    border-left: 1px solid var(--bd);
    position: sticky;
    top: var(--top-h);
    height: calc(100vh - var(--top-h));
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 0;
}
.aside-col::-webkit-scrollbar { width: 3px; }
.aside-col::-webkit-scrollbar-thumb { background: rgba(255,255,255,.06); border-radius: 2px; }

/* ── Page header ──────────────────────────────────────────────────────── */
.page-eyebrow {
    display: flex; align-items: center; gap: 6px;
    font-size: .5rem; font-weight: 700; color: var(--tx3);
    text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 5px;
}
.page-eyebrow-dot { width: 4px; height: 4px; border-radius: 50%; background: var(--ac); }
.page-title {
    font-family: var(--fh); font-size: 1.75rem; font-weight: 900;
    color: var(--tx); letter-spacing: -.04em; line-height: 1.1;
    margin-bottom: 28px;
}

/* ── Section cards ────────────────────────────────────────────────────── */
.card {
    background: var(--sf); border: 1px solid var(--bd);
    border-radius: 14px; overflow: hidden; margin-bottom: 20px;
}
.card-head {
    padding: 14px 20px; border-bottom: 1px solid var(--bd);
    display: flex; align-items: center; gap: 10px;
}
.card-head-icon {
    width: 28px; height: 28px; border-radius: 7px;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.card-head-icon svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.card-head-icon.green  { background: var(--ac3);   color: var(--ac); }
.card-head-icon.blue   { background: var(--blue2); color: var(--blue); }
.card-head-title { font-size: .875rem; font-weight: 700; color: var(--tx); }
.card-head-count {
    font-size: .5625rem; font-weight: 800;
    background: var(--ac3); color: var(--ac);
    padding: 2px 8px; border-radius: 50px;
}
.card-body { padding: 20px; }

/* ── Form fields ──────────────────────────────────────────────────────── */
.field       { margin-bottom: 18px; }
.field:last-child { margin-bottom: 0; }
.field-row   { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 18px; }
.field-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; margin-bottom: 18px; }
.field-label { display: block; font-size: .75rem; font-weight: 700; color: var(--tx2); margin-bottom: 6px; }
.field-label .opt { color: var(--tx3); font-weight: 400; }
.field-hint  { font-size: .5625rem; color: var(--tx3); margin-top: 5px; line-height: 1.55; }
.field-input {
    width: 100%; padding: 10px 13px;
    background: var(--sf); border: 1.5px solid var(--bd); border-radius: 9px;
    font-family: var(--ff); font-size: .875rem; color: var(--tx);
    outline: none; transition: border-color .18s, box-shadow .18s; line-height: 1.6;
}
.field-input::placeholder { color: var(--tx3); }
.field-input:focus { border-color: var(--ac); box-shadow: 0 0 0 3px rgba(31,226,144,.09); }
textarea.field-input { resize: vertical; min-height: 80px; }
select.field-input {
    cursor: pointer; appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%235a8580' stroke-width='2' stroke-linecap='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 12px center;
}
.slug-toggle { font-size: .625rem; color: var(--tx3); text-decoration: underline; cursor: pointer; margin-left: 6px; font-weight: 400; transition: color .14s; }
.slug-toggle:hover { color: var(--ac); }

/* ── Question cards ───────────────────────────────────────────────────── */
.q-list { display: flex; flex-direction: column; gap: 12px; }
.q-card {
    background: var(--ink2); border: 1.5px solid var(--bd);
    border-radius: 12px; overflow: hidden; transition: border-color .16s;
}
.q-card.open { border-color: rgba(31,226,144,.18); }
.q-card.dragging { border-color: var(--ac); opacity: .65; transform: rotate(.4deg); }
.q-card-head {
    display: flex; align-items: center; gap: 10px;
    padding: 13px 16px; cursor: pointer; user-select: none;
}
.q-drag { cursor: grab; color: var(--tx3); line-height: 0; flex-shrink: 0; }
.q-drag svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 1.6; }
.q-num {
    font-family: var(--fm); font-size: .5625rem; font-weight: 500;
    color: var(--tx3); background: var(--sf3); padding: 2px 8px; border-radius: 4px; flex-shrink: 0;
}
.q-stem-preview {
    flex: 1; font-size: .8125rem; color: var(--tx);
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.q-diff {
    font-size: .5625rem; font-weight: 700; padding: 2px 8px; border-radius: 50px; flex-shrink: 0;
}
.q-diff.easy   { background: var(--ac3);   color: var(--ac); }
.q-diff.medium { background: var(--warn2); color: var(--warn); }
.q-diff.hard   { background: var(--err2);  color: var(--err); }
.q-chevron { margin-left: 4px; color: var(--tx3); line-height: 0; transition: transform .2s; }
.q-chevron svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.q-card.open .q-chevron { transform: rotate(180deg); }
.q-body { display: none; padding: 0 16px 16px; border-top: 1px solid var(--bd); }
.q-card.open .q-body { display: block; }
.q-body-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin: 16px 0 14px; }
.opt-row { display: flex; align-items: center; gap: 9px; margin-bottom: 8px; }
.opt-radio { width: 15px; height: 15px; accent-color: var(--ac); flex-shrink: 0; cursor: pointer; }
.opt-letter {
    width: 23px; height: 23px; border-radius: 5px; background: var(--sf3);
    display: flex; align-items: center; justify-content: center;
    font-size: .5625rem; font-weight: 800; color: var(--tx3); flex-shrink: 0;
}
.opts-label { font-size: .75rem; font-weight: 700; color: var(--tx2); margin: 14px 0 8px; }
.stem-preview {
    margin-top: 7px; padding: 9px 12px;
    background: var(--sf); border: 1px solid var(--bd); border-radius: 8px;
    min-height: 34px; font-size: .875rem; color: var(--tx); line-height: 1.65;
}
.q-del-btn {
    display: flex; align-items: center; justify-content: center; gap: 6px;
    width: 100%; margin-top: 14px; padding: 9px;
    border-radius: 8px; border: 1px dashed rgba(239,68,68,.2);
    background: none; color: var(--err); font-size: .75rem; font-weight: 600;
    cursor: pointer; transition: all .15s; font-family: var(--ff);
}
.q-del-btn:hover { background: var(--err2); border-style: solid; }
.q-del-btn svg { width: 12px; height: 12px; stroke: currentColor; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

/* Explanation sub-section */
.exp-section { margin-top: 16px; border-top: 1px solid var(--bd); padding-top: 14px; }
.exp-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.exp-label { font-size: .75rem; font-weight: 700; color: var(--tx2); }
.exp-tabs { display: flex; gap: 3px; background: var(--sf2); border-radius: 6px; padding: 2px; }
.exp-tab {
    padding: 4px 10px; border-radius: 4px; background: none; border: none;
    font-size: .5625rem; font-weight: 700; color: var(--tx3);
    cursor: pointer; font-family: var(--ff); transition: all .13s;
}
.exp-tab.active { background: var(--ac3); color: var(--ac); }
.exp-note { font-size: .5625rem; color: var(--tx3); margin-bottom: 8px; line-height: 1.6; }

/* Add question button */
.add-q-btn {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    width: 100%; padding: 15px;
    border: 1.5px dashed rgba(31,226,144,.22); border-radius: 12px;
    background: var(--ac3); color: var(--ac);
    font-size: .8125rem; font-weight: 700; font-family: var(--ff);
    cursor: pointer; transition: all .2s; margin-top: 12px;
}
.add-q-btn:hover { border-color: var(--ac2); background: var(--ac4); }
.add-q-btn svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2.2; stroke-linecap: round; }

/* ── Sidebar settings ─────────────────────────────────────────────────── */
.aside-section { margin-bottom: 24px; }
.aside-section:last-child { margin-bottom: 0; }
.aside-label {
    font-size: .5rem; font-weight: 700; color: var(--tx3);
    text-transform: uppercase; letter-spacing: 1.1px; margin-bottom: 10px;
    display: flex; align-items: center; gap: 8px;
}
.aside-label::after { content: ''; flex: 1; height: 1px; background: var(--bd); }
.aside-field { margin-bottom: 12px; }
.aside-field-label { display: block; font-size: .75rem; font-weight: 700; color: var(--tx2); margin-bottom: 5px; }
.aside-input {
    width: 100%; padding: 8px 11px;
    background: var(--sf); border: 1.5px solid var(--bd); border-radius: 8px;
    font-family: var(--ff); font-size: .8125rem; color: var(--tx);
    outline: none; transition: border-color .18s;
}
.aside-input:focus { border-color: var(--ac); }
.aside-input::placeholder { color: var(--tx3); }
select.aside-input {
    cursor: pointer; appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%235a8580' stroke-width='2' stroke-linecap='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 10px center;
}
.aside-hint { font-size: .5rem; color: var(--tx3); margin-top: 4px; line-height: 1.55; }
.toggle-row { display: flex; align-items: center; justify-content: space-between; padding: 9px 0; border-bottom: 1px solid var(--bd); }
.toggle-row:last-child { border-bottom: none; padding-bottom: 0; }
.toggle-row:first-child { padding-top: 0; }
.toggle-info { flex: 1; }
.toggle-label { font-size: .8125rem; font-weight: 600; color: var(--tx); }
.toggle-sub   { font-size: .5625rem; color: var(--tx3); margin-top: 2px; }
.toggle { position: relative; width: 36px; height: 20px; flex-shrink: 0; }
.toggle input { opacity: 0; width: 0; height: 0; }
.toggle-slider {
    position: absolute; inset: 0;
    background: var(--sf3); border: 1px solid var(--bd2);
    border-radius: 50px; cursor: pointer; transition: background .2s;
}
.toggle-slider::before {
    content: ''; position: absolute;
    width: 14px; height: 14px; left: 2px; top: 2px;
    background: #fff; border-radius: 50%; transition: transform .2s;
}
.toggle input:checked + .toggle-slider { background: var(--ac); }
.toggle input:checked + .toggle-slider::before { transform: translateX(16px); }

/* ── Unsaved bar ──────────────────────────────────────────────────────── */
.unsaved-bar {
    display: none;
    background: var(--warn2); border-bottom: 1px solid rgba(245,158,11,.2);
    padding: 8px 28px; font-size: .75rem; color: var(--warn); font-weight: 600;
    align-items: center; gap: 8px;
    position: fixed; top: var(--top-h); left: var(--sb-w); right: 0; z-index: 190;
}
.unsaved-bar.show { display: flex; }
.unsaved-bar svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; }

/* ── Toast ────────────────────────────────────────────────────────────── */
.toast-wrap { position: fixed; bottom: 24px; right: 24px; z-index: 999; display: flex; flex-direction: column; gap: 8px; }
.toast {
    padding: 12px 18px; border-radius: 10px; font-size: .8125rem; font-weight: 600;
    border: 1px solid var(--bd2); box-shadow: 0 8px 32px rgba(0,0,0,.4);
    transform: translateX(120%); transition: transform .3s cubic-bezier(.16,1,.3,1); max-width: 300px;
}
.toast.show { transform: none; }
.toast.success { background: rgba(31,226,144,.12); color: var(--ac); }
.toast.error   { background: rgba(239,68,68,.12);  color: var(--err); }
.toast.info    { background: rgba(59,130,246,.12);  color: var(--blue); }

/* ── Responsive ───────────────────────────────────────────────────────── */
@media (max-width: 1024px) {
    .main { grid-template-columns: 1fr; }
    .aside-col { position: static; height: auto; border-left: none; border-top: 1px solid var(--bd); }
    .field-row-3 { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 768px) {
    :root { --sb-w: 0px; }
    .sb { transform: translateX(-240px); --sb-w: 240px; }
    .sb.open { transform: translateX(0); }
    .topbar { left: 0; padding: 0 16px; }
    .topbar-ham { display: flex; }
    .main { margin-left: 0; }
    .unsaved-bar { left: 0; }
    .field-row, .field-row-3, .q-body-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<!-- Sidebar overlay (mobile) -->
<div class="sb-overlay" id="sbOverlay" onclick="closeSidebar()"></div>

<!-- Sidebar -->
<aside class="sb" id="sidebar">
    <a href="/index.php" class="sb-logo">
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
            <svg class="sb-ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3" stroke-linecap="round"/></svg>Question Bank
        </a>
        <a href="/practice-tests/index.php" class="sb-link">
            <svg class="sb-ico" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>Practice Tests
        </a>
        <div class="sb-group">Students</div>
        <a href="/students/index.php" class="sb-link">
            <svg class="sb-ico" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>All Students
        </a>
    </nav>
    <div class="sb-foot">
        <div class="sb-ava"><?= strtoupper(substr($admin['name'], 0, 1)) ?></div>
        <div>
            <div class="sb-name"><?= htmlspecialchars($admin['name']) ?></div>
            <div class="sb-role"><?= htmlspecialchars($admin['role']) ?></div>
        </div>
        <a href="/auth/logout.php" class="sb-out" title="Log out">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </a>
    </div>
</aside>

<!-- Topbar -->
<header class="topbar">
    <button class="topbar-ham" onclick="openSidebar()">
        <span></span><span></span><span></span>
    </button>
    <div class="topbar-breadcrumb">
        <a href="/quizzes/index.php">Quizzes</a>
        <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        New Quiz
    </div>
    <div class="topbar-spacer"></div>
    <div class="autosave">
        <span class="autosave-dot" id="autosaveDot"></span>
        <span id="autosaveLabel">Draft</span>
    </div>
    <a href="/quizzes/index.php" class="btn btn-ghost btn-sm">Cancel</a>
    <button class="btn btn-ghost btn-sm" onclick="saveQuiz('draft')">
        <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        Save Draft
    </button>
    <button class="btn btn-publish btn-sm" onclick="saveQuiz('published')">
        <svg viewBox="0 0 24 24"><path d="M12 2l3 7h7l-5.5 4.5 2 7L12 17l-6.5 3.5 2-7L2 9h7z" fill="var(--dk)" stroke="none"/></svg>
        Publish
    </button>
</header>

<!-- Unsaved changes bar -->
<div class="unsaved-bar" id="unsavedBar">
    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16" stroke-width="2.5"/></svg>
    You have unsaved changes — Ctrl+S to save
</div>

<!-- Main -->
<div class="main">

    <!-- Left: content -->
    <div class="main-col">
        <div class="page-eyebrow"><span class="page-eyebrow-dot"></span>Quizzes</div>
        <h1 class="page-title">Create New Quiz</h1>

        <!-- Quiz details card -->
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
                           placeholder="e.g. Solving Linear Equations — Practice Quiz"
                           oninput="markUnsaved()" autocomplete="off">
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
                            <option value="">— Select lesson —</option>
                            <?php foreach ($lessons as $l): ?>
                            <option value="<?= htmlspecialchars($l['slug']) ?>">
                                <?= htmlspecialchars($l['title'] ?? $l['slug']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" id="lessonSlugText" class="field-input"
                               placeholder="e.g. solving-linear-equations"
                               style="display:none" oninput="markUnsaved()">
                        <?php else: ?>
                        <input type="text" id="lessonSlugText" class="field-input"
                               placeholder="e.g. solving-linear-equations"
                               oninput="markUnsaved()">
                        <?php endif; ?>
                        <div class="field-hint">Used to link this quiz to its lesson page.</div>
                    </div>
                    <div>
                        <label class="field-label" for="quizSection">Section</label>
                        <select id="quizSection" class="field-input" onchange="markUnsaved()">
                            <option value="">— Any section —</option>
                            <option value="math">Math</option>
                            <option value="reading-writing">Reading &amp; Writing</option>
                        </select>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="quizInstructions">Instructions <span class="opt">(optional)</span></label>
                    <textarea id="quizInstructions" class="field-input" rows="2"
                              placeholder="Instructions shown to students before starting the quiz…"
                              oninput="markUnsaved()"></textarea>
                </div>

            </div>
        </div>

        <!-- Questions card -->
        <div class="card">
            <div class="card-head">
                <div class="card-head-icon blue">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3" stroke-linecap="round"/><line x1="12" y1="17" x2="12.01" y2="17" stroke-width="2.5"/></svg>
                </div>
                <div class="card-head-title">Questions</div>
                <span class="card-head-count" id="qCountBadge">0 questions</span>
                <div style="margin-left:auto; display:flex; gap:6px">
                    <button class="btn btn-ghost btn-sm" onclick="exportJSON()" title="Export questions as JSON">
                        <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>Export
                    </button>
                    <button class="btn btn-ghost btn-sm" onclick="triggerImport()" title="Import questions from JSON">
                        <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>Import
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

    <!-- Right: settings sidebar -->
    <aside class="aside-col">

        <div class="aside-section">
            <div class="aside-label">Timing</div>
            <div class="aside-field">
                <label class="aside-field-label" for="timeLimit">Time Limit (seconds)</label>
                <input type="number" id="timeLimit" class="aside-input"
                       value="0" min="0" step="60" placeholder="0 = unlimited"
                       oninput="markUnsaved()">
                <div class="aside-hint">0 = no limit · 600 = 10 min · 1800 = 30 min</div>
            </div>
        </div>

        <div class="aside-section">
            <div class="aside-label">Scoring</div>
            <div class="aside-field">
                <label class="aside-field-label" for="passingScore">Passing Score (%)</label>
                <input type="number" id="passingScore" class="aside-input"
                       value="70" min="1" max="100" oninput="markUnsaved()">
            </div>
            <div class="aside-field">
                <label class="aside-field-label" for="xpReward">XP Reward on Pass</label>
                <input type="number" id="xpReward" class="aside-input"
                       value="50" min="0" step="5" oninput="markUnsaved()">
            </div>
        </div>

        <div class="aside-section">
            <div class="aside-label">Options</div>
            <div class="toggle-row">
                <div class="toggle-info">
                    <div class="toggle-label">Show Hints</div>
                    <div class="toggle-sub">Students can reveal hints</div>
                </div>
                <label class="toggle">
                    <input type="checkbox" id="showHints" checked onchange="markUnsaved()">
                    <span class="toggle-slider"></span>
                </label>
            </div>
            <div class="toggle-row">
                <div class="toggle-info">
                    <div class="toggle-label">Shuffle Questions</div>
                    <div class="toggle-sub">Randomise question order</div>
                </div>
                <label class="toggle">
                    <input type="checkbox" id="shuffleQ" onchange="markUnsaved()">
                    <span class="toggle-slider"></span>
                </label>
            </div>
            <div class="toggle-row">
                <div class="toggle-info">
                    <div class="toggle-label">Shuffle Options</div>
                    <div class="toggle-sub">Randomise A/B/C/D order</div>
                </div>
                <label class="toggle">
                    <input type="checkbox" id="shuffleOpts" onchange="markUnsaved()">
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </div>

        <div class="aside-section">
            <div class="aside-label">After Saving</div>
            <a href="#" id="previewLink" class="btn btn-ghost" style="width:100%;justify-content:center;margin-bottom:8px" onclick="return false">
                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                Preview Quiz
            </a>
            <a href="#" id="builderLink" class="btn btn-ghost" style="width:100%;justify-content:center" onclick="return false">
                <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                Open Builder
            </a>
        </div>

    </aside>

</div><!-- /main -->

<div class="toast-wrap" id="toastWrap"></div>
<input type="file" id="jsonImport" accept=".json" style="display:none" onchange="handleImport(this)">

<!-- ── Scripts ──────────────────────────────────────────────────────────── -->
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
<script>
// ── Embed shared RichEditor component ─────────────────────────────────────
<?php readfile(__DIR__ . '/_rich_editor.js'); ?>

// ── Config ────────────────────────────────────────────────────────────────
const HAS_LESSONS  = <?= !empty($lessons)  ? 'true' : 'false' ?>;
const HAS_EXP_HTML = <?= $hasExpHtml       ? 'true' : 'false' ?>;
const TOPICS       = <?= json_encode($topics, JSON_HEX_TAG) ?>;

// ── State ─────────────────────────────────────────────────────────────────
let questions    = [];   // array of question objects
let savedQuizId  = null; // set after first save
let unsaved      = false;
let autosaveTimer= null;
const richEditors= {};   // map from question.id → RichEditor instance
let slugMode     = 'select'; // 'select' | 'text'

// ── Slug mode toggle ──────────────────────────────────────────────────────
function toggleSlugMode() {
    const sel    = document.getElementById('lessonSlugSelect');
    const txt    = document.getElementById('lessonSlugText');
    const toggle = document.getElementById('slugToggle');
    if (!sel || !txt) return;
    if (slugMode === 'select') {
        slugMode = 'text';
        sel.style.display = 'none';
        txt.style.display = '';
        txt.focus();
        if (toggle) toggle.textContent = 'use dropdown';
    } else {
        slugMode = 'select';
        txt.style.display = 'none';
        sel.style.display = '';
        if (toggle) toggle.textContent = 'type manually';
    }
}
function getSlug() {
    if (!HAS_LESSONS || slugMode === 'text') {
        return document.getElementById('lessonSlugText')?.value.trim() || '';
    }
    return document.getElementById('lessonSlugSelect')?.value || '';
}

// ── Add / remove questions ────────────────────────────────────────────────
function addQuestion(data = null) {
    const q = data || {
        id: 'new_' + Date.now(),
        stem: '', type: 'mcq',
        option_a: '', option_b: '', option_c: '', option_d: '',
        correct_answer: 'a',
        explanation: '', explanation_html: '',
        hint: '', difficulty: 'medium',
        micro_topic_id: '', points: 1,
    };
    if (!q.id) q.id = 'new_' + Date.now();
    questions.push(q);
    renderQuestions();
    markUnsaved();
    // Auto-open the new card
    setTimeout(() => {
        const card = document.querySelector(`[data-qid="${q.id}"]`);
        if (card) {
            card.classList.add('open');
            card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }, 40);
}

function removeQuestion(id) {
    if (richEditors[id]) delete richEditors[id];
    questions = questions.filter(q => q.id !== id);
    renderQuestions();
    markUnsaved();
}

// ── Render all questions ──────────────────────────────────────────────────
function renderQuestions() {
    const list = document.getElementById('qList');
    list.innerHTML = '';

    questions.forEach((q, i) => {
        const card = document.createElement('div');
        card.className    = 'q-card';
        card.dataset.qid  = q.id;
        card.innerHTML    = buildCardHTML(q, i);
        list.appendChild(card);

        // ── Stem input ──────────────────────────────────────────────────
        const stemInput = card.querySelector('.stem-input');
        if (stemInput) {
            stemInput.addEventListener('input', () => {
                q.stem = stemInput.value;
                // Update list preview
                card.querySelector('.q-stem-preview').innerHTML =
                    q.stem ? esc(q.stem.substring(0, 80)) : `<em style="color:var(--tx3)">Click to expand…</em>`;
                // LaTeX preview
                renderStemPreview(card, q.stem);
                markUnsaved();
            });
            renderStemPreview(card, q.stem || '');
        }

        // ── Options ─────────────────────────────────────────────────────
        ['a','b','c','d'].forEach(opt => {
            const inp = card.querySelector(`.opt-input-${opt}`);
            if (inp) inp.addEventListener('input', () => { q[`option_${opt}`] = inp.value; markUnsaved(); });
        });
        card.querySelectorAll('.correct-radio').forEach(r =>
            r.addEventListener('change', () => { q.correct_answer = r.value; markUnsaved(); })
        );

        // ── Metadata fields ─────────────────────────────────────────────
        ['difficulty', 'micro_topic_id', 'points'].forEach(field => {
            const el = card.querySelector(`[data-f="${field}"]`);
            if (el) el.addEventListener('change', () => { q[field] = el.value; markUnsaved(); });
        });
        const hintEl = card.querySelector('.hint-input');
        if (hintEl) hintEl.addEventListener('input', () => { q.hint = hintEl.value; markUnsaved(); });

        // ── Plain explanation ────────────────────────────────────────────
        const plainExp = card.querySelector('.plain-exp');
        if (plainExp) {
            plainExp.addEventListener('input', () => {
                q.explanation = plainExp.value;
                if (HAS_EXP_HTML) q.explanation_html = plainExp.value ? `<p>${esc(plainExp.value)}</p>` : '';
                markUnsaved();
            });
        }

        // ── Rich editor (only when column exists) ────────────────────────
        if (HAS_EXP_HTML) {
            const richWrap = card.querySelector('.rich-exp-wrap');
            if (richWrap) {
                richEditors[q.id] = new RichEditor(richWrap, {
                    initialHtml: q.explanation_html || (q.explanation ? `<p>${esc(q.explanation)}</p>` : ''),
                    uploadUrl:   '/api/upload-explanation-image.php',
                    onchange:    html => { q.explanation_html = html; markUnsaved(); }
                });
            }
            // Explanation mode tabs
            card.querySelectorAll('.exp-tab').forEach(tab => {
                tab.addEventListener('click', () => {
                    card.querySelectorAll('.exp-tab').forEach(t => t.classList.toggle('active', t === tab));
                    card.querySelector('.rich-exp-wrap').style.display  = tab.dataset.mode === 'rich'  ? '' : 'none';
                    card.querySelector('.plain-exp-wrap').style.display = tab.dataset.mode === 'plain' ? '' : 'none';
                });
            });
        }

        // ── Expand / collapse ────────────────────────────────────────────
        card.querySelector('.q-card-head').addEventListener('click', e => {
            if (e.target.closest('button, input, select, textarea, .re-wrap')) return;
            card.classList.toggle('open');
        });
    });

    document.getElementById('qCountBadge').textContent =
        questions.length + ' question' + (questions.length !== 1 ? 's' : '');

    initDragDrop();
}

// ── Build question card HTML ──────────────────────────────────────────────
function buildCardHTML(q, i) {
    const topicOptions = TOPICS.map(t =>
        `<option value="${t.id}" ${q.micro_topic_id == t.id ? 'selected' : ''}>${esc(t.name)}</option>`
    ).join('');

    const expSection = HAS_EXP_HTML ? `
        <div class="exp-section">
            <div class="exp-head">
                <div class="exp-label">Explanation</div>
                <div class="exp-tabs">
                    <button type="button" class="exp-tab active" data-mode="rich">Rich Editor</button>
                    <button type="button" class="exp-tab"        data-mode="plain">Plain Text</button>
                </div>
            </div>
            <p class="exp-note">Supports <strong style="color:var(--tx)">bold</strong>, <em>italic</em>, headings, lists, $LaTeX$ formulas, and images.</p>
            <div class="rich-exp-wrap"></div>
            <div class="plain-exp-wrap" style="display:none">
                <textarea class="field-input plain-exp" rows="3" placeholder="Plain-text fallback…">${esc(q.explanation || '')}</textarea>
            </div>
        </div>` : `
        <div class="exp-section">
            <div class="exp-head"><div class="exp-label">Explanation <span style="font-weight:400;color:var(--tx3)">(optional)</span></div></div>
            <textarea class="field-input plain-exp" rows="3" placeholder="Why this answer is correct…">${esc(q.explanation || '')}</textarea>
        </div>`;

    return `
    <div class="q-card-head">
        <span class="q-drag">
            <svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="8" y1="18" x2="16" y2="18"/></svg>
        </span>
        <span class="q-num">Q${i + 1}</span>
        <span class="q-stem-preview">
            ${q.stem ? esc(q.stem.substring(0, 80)) : '<em style="color:var(--tx3)">Click to expand…</em>'}
        </span>
        <span class="q-diff ${q.difficulty || 'medium'}">${q.difficulty || 'medium'}</span>
        <span class="q-chevron">
            <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
        </span>
    </div>

    <div class="q-body">
        <div style="margin-top:16px">
            <label class="field-label">
                Question Stem
                <span style="font-weight:400;color:var(--tx3)">— $LaTeX$ supported</span>
            </label>
            <textarea class="field-input stem-input" rows="3"
                      placeholder="Write your question here…">${esc(q.stem || '')}</textarea>
            <div class="stem-preview">
                <span style="color:var(--tx3);font-size:.75rem">LaTeX preview appears here…</span>
            </div>
        </div>

        <div class="q-body-grid">
            <div>
                <label class="field-label">Difficulty</label>
                <select class="field-input" data-f="difficulty">
                    <option value="easy"   ${q.difficulty === 'easy'                     ? 'selected' : ''}>Easy</option>
                    <option value="medium" ${!q.difficulty || q.difficulty === 'medium'  ? 'selected' : ''}>Medium</option>
                    <option value="hard"   ${q.difficulty === 'hard'                     ? 'selected' : ''}>Hard</option>
                </select>
            </div>
            <div>
                <label class="field-label">Points</label>
                <input type="number" class="field-input" data-f="points"
                       value="${q.points || 1}" min="1" max="5">
            </div>
        </div>

        ${TOPICS.length > 0 ? `
        <div style="margin-bottom:14px">
            <label class="field-label">Micro-Topic</label>
            <select class="field-input" data-f="micro_topic_id">
                <option value="">— Not tagged —</option>
                ${topicOptions}
            </select>
        </div>` : ''}

        <div class="opts-label">Answer Options — select the correct one</div>
        ${['a','b','c','d'].map(opt => `
        <div class="opt-row">
            <input type="radio" class="opt-radio correct-radio"
                   name="correct_${esc(q.id)}" value="${opt}"
                   ${(q.correct_answer || 'a') === opt ? 'checked' : ''}>
            <span class="opt-letter">${opt.toUpperCase()}</span>
            <input type="text" class="field-input opt-input-${opt}"
                   value="${esc(q['option_' + opt] || '')}"
                   placeholder="Option ${opt.toUpperCase()}" style="margin:0">
        </div>`).join('')}

        <div style="margin-top:14px">
            <label class="field-label">Hint <span style="font-weight:400;color:var(--tx3)">(optional)</span></label>
            <textarea class="field-input hint-input" rows="2"
                      placeholder="A helpful nudge toward the answer…">${esc(q.hint || '')}</textarea>
        </div>

        ${expSection}

        <button type="button" class="q-del-btn" onclick="removeQuestion('${esc(q.id)}')">
            <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
            Remove this question
        </button>
    </div>`;
}

// ── Stem LaTeX live preview ───────────────────────────────────────────────
function renderStemPreview(card, text) {
    const el = card.querySelector('.stem-preview');
    if (!el || typeof katex === 'undefined') return;
    if (!text.trim()) {
        el.innerHTML = '<span style="color:var(--tx3);font-size:.75rem">LaTeX preview appears here…</span>';
        return;
    }
    el.innerHTML = text.replace(/\$([^$\n]+)\$/g, (_, m) => {
        try { return katex.renderToString(m, { throwOnError: false }); }
        catch { return `<span style="color:var(--err)">[invalid LaTeX]</span>`; }
    });
}

// ── Drag-and-drop reordering ──────────────────────────────────────────────
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
            e.preventDefault();
            if (!dragged || dragged === card) return;
            const r = card.getBoundingClientRect();
            card.parentNode.insertBefore(dragged, e.clientY > r.top + r.height / 2 ? card.nextSibling : card);
        });
    });
}

function reorderFromDOM() {
    const ids   = [...document.querySelectorAll('.q-card')].map(c => c.dataset.qid);
    questions   = ids.map(id => questions.find(q => q.id === id)).filter(Boolean);
    renderQuestions();
}

// ── Save to API ───────────────────────────────────────────────────────────
async function saveQuiz(status = 'draft') {
    const title = document.getElementById('quizTitle').value.trim();
    const slug  = getSlug();

    if (!title) {
        toast('Please enter a quiz title.', 'error');
        document.getElementById('quizTitle').focus();
        return;
    }
    if (!slug) {
        toast('Please select or enter a lesson slug.', 'error');
        return;
    }

    setAutosave('saving');

    const payload = {
        id:           savedQuizId,
        title,
        lesson_slug:  slug,
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
            stem:            q.stem             || '',
            type:            q.type             || 'mcq',
            option_a:        q.option_a         || '',
            option_b:        q.option_b         || '',
            option_c:        q.option_c         || '',
            option_d:        q.option_d         || '',
            correct_answer:  q.correct_answer   || 'a',
            explanation:     q.explanation      || '',
            explanation_html:q.explanation_html || '',
            hint:            q.hint             || '',
            difficulty:      q.difficulty       || 'medium',
            micro_topic_id:  q.micro_topic_id   || null,
            points:          parseInt(q.points) || 1,
        }))
    };

    try {
        const res  = await fetch('/api/save-quiz.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(payload),
        });
        const data = await res.json();

        if (data.success) {
            savedQuizId = data.quiz_id;

            // Update temp IDs with real ones from DB
            if (data.question_ids) {
                questions.forEach((q, i) => {
                    if (data.question_ids[i]) q.id = data.question_ids[i];
                });
            }

            markSaved();
            setAutosave('saved');

            // Enable post-save links
            const pl = document.getElementById('previewLink');
            const bl = document.getElementById('builderLink');
            if (pl) pl.href = `/quizzes/preview.php?id=${savedQuizId}`;
            if (bl) bl.href = `/quizzes/builder.php?id=${savedQuizId}`;

            if (status === 'published') {
                toast('🎉 Quiz published!', 'success');
                setTimeout(() => window.location.href = '/quizzes/index.php', 1400);
            } else {
                toast('Draft saved.', 'info');
            }
        } else {
            setAutosave('unsaved');
            toast(data.error || 'Error saving quiz.', 'error');
        }
    } catch (e) {
        setAutosave('unsaved');
        toast('Network error — please try again.', 'error');
    }
}

// ── Dirty state ───────────────────────────────────────────────────────────
function markUnsaved() {
    unsaved = true;
    document.getElementById('unsavedBar').classList.add('show');
    setAutosave('unsaved');
    clearTimeout(autosaveTimer);
    autosaveTimer = setTimeout(() => saveQuiz('draft'), 90_000); // auto-save after 90s idle
}
function markSaved() {
    unsaved = false;
    document.getElementById('unsavedBar').classList.remove('show');
}
function setAutosave(s) {
    const dot   = document.getElementById('autosaveDot');
    const label = document.getElementById('autosaveLabel');
    if (!dot || !label) return;
    dot.className   = 'autosave-dot' + (s === 'saving' ? ' saving' : s === 'saved' ? ' saved' : '');
    label.textContent = s === 'saving' ? 'Saving…' : s === 'saved' ? 'Saved' : 'Unsaved';
}

// ── Import / Export ───────────────────────────────────────────────────────
function triggerImport() { document.getElementById('jsonImport').click(); }

function handleImport(input) {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        try {
            const parsed = JSON.parse(e.target.result);
            const qs     = parsed.questions || (Array.isArray(parsed) ? parsed : []);
            if (!qs.length) { toast('No questions found in that file.', 'error'); return; }
            qs.forEach(q => addQuestion({ ...q, id: 'new_' + Date.now() + Math.random() }));
            toast(`Imported ${qs.length} question${qs.length !== 1 ? 's' : ''}.`, 'success');
        } catch {
            toast('Invalid JSON file.', 'error');
        }
    };
    reader.readAsText(file);
    input.value = '';
}

function exportJSON() {
    if (!questions.length) { toast('No questions to export yet.', 'error'); return; }
    const data = {
        title:      document.getElementById('quizTitle').value || 'Untitled Quiz',
        questions,
    };
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const a    = Object.assign(document.createElement('a'), {
        href:     URL.createObjectURL(blob),
        download: 'quiz-export.json',
    });
    a.click();
    URL.revokeObjectURL(a.href);
}

// ── Mobile sidebar ────────────────────────────────────────────────────────
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

// ── Utility ───────────────────────────────────────────────────────────────
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
    setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 400); }, 3500);
}

// ── Global keyboard shortcuts ─────────────────────────────────────────────
document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        saveQuiz('draft');
    }
});
window.addEventListener('beforeunload', e => {
    if (unsaved) { e.preventDefault(); e.returnValue = ''; }
});
</script>
</body>
</html>