<?php
/**
 * /ai-tutor/index.php — AI Tutor Full Chat Interface
 * my.sat.avidmock.com/ai-tutor/
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/StudyStreak.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/AITutorHistory.php';

Auth::requireStudent();
$userId    = $_SESSION['user_id'];
$user      = User::findById($userId);
$firstName = trim($user['first_name'] ?? 'Student');
$lastName  = trim($user['last_name']  ?? '');
$fullName  = trim($firstName . ' ' . $lastName) ?: 'Student';

$streakData    = StudyStreak::get($userId) ?? [];
$currentStreak = intval($streakData['current_streak'] ?? 0);

$prefillQuestion = htmlspecialchars(trim($_GET['q'] ?? ''), ENT_QUOTES);
$prefillSubject  = in_array($_GET['subject'] ?? '', ['math','reading_writing','general'])
    ? $_GET['subject'] : 'general';

$recentConversations = AITutorHistory::getRecent($userId, 10);

$weakMathTopics = [];
$weakRWTopics   = [];
if (class_exists('CategoryPerformance')) {
    try {
        $weakMathTopics = CategoryPerformance::getWeakTopics($userId, 'math', 3)           ?? [];
        $weakRWTopics   = CategoryPerformance::getWeakTopics($userId, 'reading_writing', 3) ?? [];
    } catch (Throwable $e) {
        error_log('[ai-tutor/index.php] CategoryPerformance error: ' . $e->getMessage());
    }
}

/* ── Variables consumed by shared includes ── */
$activePage  = 'ai_tutor';
$topbarTitle = 'AI Tutor';
$topbarSub   = 'Ready · SAT-focused';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI Tutor — Avidmock SAT</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<link rel="icon" type="image/png" sizes="16x16"  href="/favicon-16x16.png">
<link rel="icon" type="image/png" sizes="32x32"  href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="192x192" href="/android-chrome-192x192.png">
<link rel="icon" type="image/png" sizes="512x512" href="/android-chrome-512x512.png">
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/KaTeX/0.16.9/katex.min.css">
<style>
/* ─────────────────────────────────────────────
   TOKENS  (match dashboard / schedule)
───────────────────────────────────────────── */
:root {
    --dk:#143230; --dk2:#1a3f3c; --dk3:#0e2422;
    --ac:#1fe290; --ac2:#17c87a;
    --tx:#1a1a2e; --tx2:#4a4a5a; --tx3:#8a8a9a;
    --bg:#f7faf9; --bg2:#ffffff; --bd:#e2ebe9;
    --err:#e74c3c; --warn:#f39c12; --ok:#10b981;
    --ff:'DM Sans',-apple-system,BlinkMacSystemFont,sans-serif;
    --fm:'DM Mono',monospace;
    --sidebar-w:260px; --topbar-h:64px; --r:14px; --r-sm:10px;
    --hist-w:280px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{font-size:16px}
body{font-family:var(--ff);-webkit-font-smoothing:antialiased;background:var(--bg);color:var(--tx);height:100vh;overflow:hidden}
a{text-decoration:none;color:inherit}
button{font-family:var(--ff);cursor:pointer}

/* ─────────────────────────────────────────────
   LAYOUT SHELL
   The shared topbar/sidebar already claim
   var(--sidebar-w) left and var(--topbar-h) top.
   We build the chat area inside the remaining space.
───────────────────────────────────────────── */
.ai-shell {
    position: fixed;
    top: var(--topbar-h);
    left: var(--sidebar-w);
    right: 0;
    bottom: 0;
    display: flex;
    overflow: hidden;
}

/* ─────────────────────────────────────────────
   HISTORY SIDEBAR
───────────────────────────────────────────── */
.hist-sidebar {
    width: var(--hist-w);
    flex-shrink: 0;
    background: var(--bg2);
    border-right: 1px solid var(--bd);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transition: transform .32s cubic-bezier(.16,1,.3,1);
}
.hist-header {
    padding: 0 20px;
    height: 56px;
    border-bottom: 1px solid var(--bd);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}
.hist-title { font-size: .9375rem; font-weight: 700; color: var(--tx); letter-spacing: -.015em; }
.hist-new-btn {
    display: flex; align-items: center; gap: 5px;
    padding: 6px 12px; border-radius: 8px;
    background: var(--ac); color: var(--dk);
    font-size: .75rem; font-weight: 800; border: none;
    transition: background .18s;
}
.hist-new-btn:hover { background: var(--ac2); }
.hist-new-btn svg { width:12px;height:12px;stroke:var(--dk);fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round; }
.hist-search { padding: 12px 16px; border-bottom: 1px solid var(--bd); flex-shrink: 0; }
.hist-search-inp {
    width: 100%; padding: 8px 12px;
    background: var(--bg); border: 1.5px solid var(--bd); border-radius: 9px;
    font-family: var(--ff); font-size: .8125rem; color: var(--tx); outline: none;
    transition: border-color .18s;
}
.hist-search-inp:focus { border-color: var(--ac); background: var(--bg2); }
.hist-search-inp::placeholder { color: var(--tx3); }
.hist-list { flex: 1; overflow-y: auto; padding: 8px; }
.hist-list::-webkit-scrollbar { width: 4px; }
.hist-list::-webkit-scrollbar-thumb { background: var(--bd); border-radius: 2px; }
.hist-group-label {
    font-size: .625rem; font-weight: 700; color: var(--tx3);
    text-transform: uppercase; letter-spacing: .5px; padding: 8px 8px 4px;
}
.hist-item {
    padding: 10px 12px; border-radius: 10px;
    cursor: pointer; transition: background .18s;
    margin-bottom: 2px;
}
.hist-item:hover { background: var(--bg); }
.hist-item.active { background: rgba(31,226,144,.07); border: 1px solid rgba(31,226,144,.15); }
.hist-item-title {
    font-size: .8125rem; font-weight: 600; color: var(--tx);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 2px;
}
.hist-item-meta { font-size: .6875rem; color: var(--tx3); display: flex; align-items: center; gap: 6px; }
.hist-item-subj {
    padding: 1px 6px; border-radius: 4px;
    font-size: .5625rem; font-weight: 700; text-transform: uppercase; letter-spacing: .3px;
}
.subj-math    { background: rgba(20,50,48,.07);   color: var(--dk); }
.subj-rw      { background: rgba(31,226,144,.1);  color: #0a6640; }
.subj-general { background: rgba(156,163,175,.12); color: var(--tx3); }
.hist-empty { text-align: center; padding: 3rem 1rem; color: var(--tx3); font-size: .875rem; }
.hist-empty svg { width:36px;height:36px;stroke:var(--bd);fill:none;stroke-width:1.5;margin:0 auto 10px;display:block; }

/* ─────────────────────────────────────────────
   MAIN CHAT AREA
───────────────────────────────────────────── */
.chat-area {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: var(--bg);
}

/* ── Chat inner topbar (subject switcher + actions) ── */
.chat-inner-bar {
    height: 52px;
    background: rgba(247,250,249,.96);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid var(--bd);
    display: flex;
    align-items: center;
    padding: 0 24px;
    gap: 12px;
    flex-shrink: 0;
    z-index: 10;
}
.chat-inner-bar-left { display: flex; align-items: center; gap: 8px; flex: 1; }
.ai-pulse { width:8px;height:8px;border-radius:50%;background:var(--ac);animation:aiPulse 2s ease-in-out infinite; }
@keyframes aiPulse { 0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.7)} }
.chat-inner-bar-name { font-size: .9375rem; font-weight: 700; color: var(--tx); letter-spacing: -.015em; }
.chat-inner-bar-sub  { font-size: .75rem; color: var(--tx3); }
.subject-select-wrap { display: flex; align-items: center; gap: 6px; }
.subject-label { font-size: .75rem; font-weight: 600; color: var(--tx3); }
.subject-select {
    padding: 6px 12px; border-radius: 8px;
    border: 1.5px solid var(--bd); background: var(--bg2);
    font-family: var(--ff); font-size: .8125rem; font-weight: 600; color: var(--tx);
    outline: none; cursor: pointer; transition: border-color .18s;
}
.subject-select:focus { border-color: var(--ac); }
.topbar-icon-btn {
    width: 36px; height: 36px; border-radius: 9px;
    border: 1.5px solid var(--bd); background: var(--bg2);
    display: flex; align-items: center; justify-content: center;
    transition: all .18s; cursor: pointer;
}
.topbar-icon-btn:hover { border-color: var(--ac); background: rgba(31,226,144,.04); }
.topbar-icon-btn svg { width:16px;height:16px;stroke:var(--tx2);fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round; }

/* ── Messages ── */
.messages-wrap { flex: 1; overflow-y: auto; padding: 28px 24px; }
.messages-wrap::-webkit-scrollbar { width: 5px; }
.messages-wrap::-webkit-scrollbar-thumb { background: var(--bd); border-radius: 3px; }

/* Welcome screen */
.welcome-screen { max-width: 680px; margin: 0 auto; padding: 20px 0; }
.welcome-logo {
    width: 56px; height: 56px; border-radius: 16px;
    background: linear-gradient(135deg,rgba(31,226,144,.15),rgba(31,226,144,.05));
    border: 1px solid rgba(31,226,144,.2);
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 20px;
}
.welcome-logo svg { width:26px;height:26px;stroke:var(--ac);fill:none;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round; }
.welcome-title { font-size: 1.625rem; font-weight: 800; color: var(--tx); letter-spacing: -.03em; margin-bottom: 8px; }
.welcome-title em { font-style: italic; color: var(--ac); }
.welcome-sub { font-size: .9375rem; color: var(--tx3); line-height: 1.65; margin-bottom: 32px; max-width: 520px; }
.suggestion-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 32px; }
.suggestion-card {
    padding: 14px 16px;
    background: var(--bg2); border: 1.5px solid var(--bd); border-radius: 12px;
    cursor: pointer; text-align: left;
    transition: all .2s cubic-bezier(.16,1,.3,1);
    display: flex; flex-direction: column; gap: 4px;
}
.suggestion-card:hover { border-color: var(--ac); transform: translateY(-2px); box-shadow: 0 6px 20px rgba(20,50,48,.07); }
.sug-label { font-size: .625rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
.sug-text  { font-size: .875rem; font-weight: 600; color: var(--tx); line-height: 1.4; }
.sug-math     .sug-label { color: var(--dk); }
.sug-rw       .sug-label { color: var(--ac2); }
.sug-strategy .sug-label { color: var(--warn); }
.sug-review   .sug-label { color: var(--err); }

/* Weak-topics row */
.weak-row { margin-bottom: 24px; }
.weak-row-label { font-size: .6875rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: .4px; margin-bottom: 8px; }
.weak-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.weak-chip {
    padding: 5px 12px; border-radius: 50px;
    font-size: .75rem; font-weight: 700; cursor: pointer;
    border: 1.5px solid var(--bd); background: var(--bg2); color: var(--tx2);
    transition: all .18s;
}
.weak-chip:hover { border-color: var(--ac); color: var(--dk); background: rgba(31,226,144,.05); }

/* Chat messages */
.messages-list { max-width: 740px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px; }
.msg { display: flex; gap: 12px; }
.msg-user { flex-direction: row-reverse; }
.msg-avatar {
    width: 34px; height: 34px; border-radius: 50%;
    flex-shrink: 0; display: flex; align-items: center; justify-content: center;
    font-size: .8125rem; font-weight: 800; margin-top: 2px;
}
.msg-avatar-ai   { background: linear-gradient(135deg,var(--dk),var(--dk2)); border: 1px solid rgba(31,226,144,.2); color: var(--ac); }
.msg-avatar-ai   svg { width:16px;height:16px;stroke:var(--ac);fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round; }
.msg-avatar-user { background: linear-gradient(135deg,var(--ac),#0da367); }
.msg-avatar-user svg { width:16px;height:16px;stroke:var(--dk);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round; }
.msg-content { flex: 1; min-width: 0; }
.msg-bubble {
    padding: 14px 18px; border-radius: 16px;
    font-size: .9375rem; line-height: 1.7; max-width: 600px;
}
.msg-user .msg-bubble { background: var(--dk); color: rgba(255,255,255,.9); border-bottom-right-radius: 4px; margin-left: auto; }
.msg-ai  .msg-bubble  { background: var(--bg2); border: 1px solid var(--bd); color: var(--tx); border-bottom-left-radius: 4px; }
.msg-time { font-size: .6875rem; color: var(--tx3); margin-top: 5px; padding: 0 4px; }
.msg-user .msg-time { text-align: right; }
.typing-indicator { display: flex; gap: 5px; padding: 14px 18px; align-items: center; }
.typing-dot { width:7px;height:7px;border-radius:50%;background:var(--tx3);animation:typingDot 1.4s ease-in-out infinite; }
.typing-dot:nth-child(2){animation-delay:.2s} .typing-dot:nth-child(3){animation-delay:.4s}
@keyframes typingDot{0%,60%,100%{transform:translateY(0);opacity:.4}30%{transform:translateY(-6px);opacity:1}}

/* ── Input bar ── */
.input-area {
    padding: 14px 24px 18px;
    background: rgba(247,250,249,.96);
    backdrop-filter: blur(10px);
    border-top: 1px solid var(--bd);
    flex-shrink: 0;
}
.input-wrap {
    max-width: 740px; margin: 0 auto;
    background: var(--bg2); border: 2px solid var(--bd); border-radius: 16px;
    transition: border-color .2s, box-shadow .2s; overflow: hidden;
}
.input-wrap:focus-within { border-color: var(--ac); box-shadow: 0 0 0 4px rgba(31,226,144,.08); }
.input-row { display: flex; align-items: flex-end; }
.chat-textarea {
    flex: 1; padding: 14px 16px;
    background: none; border: none; outline: none; resize: none;
    font-family: var(--ff); font-size: .9375rem; color: var(--tx);
    line-height: 1.6; min-height: 52px; max-height: 180px;
}
.chat-textarea::placeholder { color: var(--tx3); }
.input-send {
    flex-shrink: 0; margin: 8px 10px 8px 0;
    width: 36px; height: 36px; border-radius: 10px;
    background: var(--dk); border: none;
    display: flex; align-items: center; justify-content: center;
    transition: all .2s;
}
.input-send:hover:not(:disabled) { background: var(--ac); transform: scale(1.05); }
.input-send:disabled { background: var(--bd); cursor: not-allowed; }
.input-send svg { width:16px;height:16px;fill:none;stroke:var(--ac);stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round; }
.input-send:hover:not(:disabled) svg { stroke: var(--dk); }
.input-send:disabled svg { stroke: var(--tx3); }
.input-footer {
    display: flex; align-items: center; justify-content: space-between;
    padding: 8px 14px; border-top: 1px solid var(--bd);
    background: rgba(247,250,249,.5);
}
.input-hint { font-size: .6875rem; color: var(--tx3); display: flex; align-items: center; gap: 4px; }
.input-hint kbd {
    padding: 2px 5px; background: var(--bg); border: 1px solid var(--bd);
    border-radius: 4px; font-size: .5625rem; font-family: var(--fm); color: var(--tx3);
}

/* ─────────────────────────────────────────────
   SIDEBAR OVERLAY  (shared pattern)
───────────────────────────────────────────── */
.sidebar-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.45); z-index: 250;
    opacity: 0; transition: opacity .28s; pointer-events: none;
}
.sidebar-overlay.show { opacity: 1; pointer-events: all; display: block; }

/* ─────────────────────────────────────────────
   RESPONSIVE
───────────────────────────────────────────── */
@media(max-width:1280px) {
    .hist-sidebar { display:none; }
    .hist-sidebar.open {
        display:flex; position:fixed;
        top:var(--topbar-h); left:var(--sidebar-w);
        bottom:0; z-index:300;
        box-shadow:4px 0 24px rgba(0,0,0,.1);
    }
}
@media(max-width:900px) {
    .ai-shell { left:0; }
    .subject-label { display:none; }
}
@media(max-width:640px) {
    .chat-inner-bar { padding:0 14px; gap:8px; }
    .messages-wrap  { padding:20px 14px; }
    .input-area     { padding:12px 14px 16px; }
    .suggestion-grid{ grid-template-columns:1fr; }
    .chat-inner-bar-sub { display:none; }
}
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/sidebar.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/topbar.php';
?>

<!-- ══════════════════════════════════════════
     AI SHELL — sits below topbar, right of sidebar
════════════════════════════════════════════ -->
<div class="ai-shell">

    <!-- ── HISTORY SIDEBAR ─────────────────── -->
    <aside class="hist-sidebar" id="histSidebar">
        <div class="hist-header">
            <span class="hist-title">Conversations</span>
            <button class="hist-new-btn" type="button" onclick="newConversation()">
                <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New
            </button>
        </div>
        <div class="hist-search">
            <input type="text" class="hist-search-inp" placeholder="Search conversations…" id="histSearch">
        </div>
        <div class="hist-list" id="histList">
            <?php if (!empty($recentConversations)): ?>
            <div class="hist-group-label">Recent</div>
            <?php foreach ($recentConversations as $convo): ?>
            <div class="hist-item" data-id="<?= intval($convo['id']) ?>" onclick="loadConversation(<?= intval($convo['id']) ?>)">
                <div class="hist-item-title"><?= htmlspecialchars($convo['title'] ?? 'Untitled conversation') ?></div>
                <div class="hist-item-meta">
                    <?php
                    $subj = $convo['subject'] ?? 'general';
                    $subjCls = $subj === 'math' ? 'subj-math' : ($subj === 'reading_writing' ? 'subj-rw' : 'subj-general');
                    $subjLbl = $subj === 'math' ? 'Math' : ($subj === 'reading_writing' ? 'R&W' : 'General');
                    ?>
                    <span class="hist-item-subj <?= $subjCls ?>"><?= $subjLbl ?></span>
                    <span><?= date('g:i A', strtotime($convo['created_at'] ?? 'now')) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="hist-empty">
                <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                No conversations yet.<br>Start your first session below.
            </div>
            <?php endif; ?>
        </div>
    </aside>

    <!-- ── MAIN CHAT AREA ──────────────────── -->
    <div class="chat-area" id="chatArea">

        <!-- Inner topbar: subject selector + actions -->
        <div class="chat-inner-bar">
            <div class="chat-inner-bar-left">
                <div class="ai-pulse"></div>
                <span class="chat-inner-bar-name">AI Tutor</span>
                <span class="chat-inner-bar-sub">Ready · SAT-focused</span>
            </div>
            <div class="subject-select-wrap">
                <span class="subject-label">Focus:</span>
                <select class="subject-select" id="subjectSelect" onchange="updateSubject(this.value)">
                    <option value="general"         <?= $prefillSubject === 'general'          ? 'selected' : '' ?>>General SAT</option>
                    <option value="math"            <?= $prefillSubject === 'math'             ? 'selected' : '' ?>>Math</option>
                    <option value="reading_writing" <?= $prefillSubject === 'reading_writing'  ? 'selected' : '' ?>>Reading &amp; Writing</option>
                </select>
            </div>
            <button class="topbar-icon-btn" type="button" onclick="toggleHistSidebar()" title="Conversation history">
                <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
            </button>
            <button class="topbar-icon-btn" type="button" onclick="clearChat()" title="New conversation">
                <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            </button>
        </div>

        <!-- Messages -->
        <div class="messages-wrap" id="messagesWrap">

            <!-- Welcome screen (shown when no messages) -->
            <div class="welcome-screen" id="welcomeScreen">
                <div class="welcome-logo">
                    <svg viewBox="0 0 24 24"><path d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <h1 class="welcome-title">Your <em>AI Tutor</em> is ready</h1>
                <p class="welcome-sub">Ask anything about the SAT — concepts, strategies, practice problems, or test-day tips. I'll give you step-by-step explanations tailored to your level.</p>

                <?php if (!empty($weakMathTopics) || !empty($weakRWTopics)): ?>
                <div class="weak-row">
                    <div class="weak-row-label">Suggested for you — based on your weak spots</div>
                    <div class="weak-chips">
                        <?php foreach ($weakMathTopics as $topic): ?>
                        <button class="weak-chip" type="button"
                            onclick="sendSuggestion('Help me understand <?= htmlspecialchars($topic['name'] ?? '', ENT_QUOTES) ?> step by step')">
                            📐 <?= htmlspecialchars($topic['name'] ?? '') ?>
                        </button>
                        <?php endforeach; ?>
                        <?php foreach ($weakRWTopics as $topic): ?>
                        <button class="weak-chip" type="button"
                            onclick="sendSuggestion('Explain the strategy for <?= htmlspecialchars($topic['name'] ?? '', ENT_QUOTES) ?> questions')">
                            📖 <?= htmlspecialchars($topic['name'] ?? '') ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="suggestion-grid">
                    <button class="suggestion-card sug-math" type="button" onclick="sendSuggestion('Explain systems of equations step by step')">
                        <span class="sug-label">Math</span>
                        <span class="sug-text">Explain systems of equations step by step</span>
                    </button>
                    <button class="suggestion-card sug-rw" type="button" onclick="sendSuggestion('What is the best strategy for inference questions?')">
                        <span class="sug-label">Reading &amp; Writing</span>
                        <span class="sug-text">Strategy for inference questions</span>
                    </button>
                    <button class="suggestion-card sug-strategy" type="button" onclick="sendSuggestion('What are the best SAT time management strategies?')">
                        <span class="sug-label">Strategy</span>
                        <span class="sug-text">Best SAT time management strategies</span>
                    </button>
                    <button class="suggestion-card sug-review" type="button" onclick="sendSuggestion('What are the most common SAT mistakes and how do I avoid them?')">
                        <span class="sug-label">Common Mistakes</span>
                        <span class="sug-text">Common mistakes and how to avoid them</span>
                    </button>
                </div>
            </div>

            <!-- Live messages rendered here by JS -->
            <div class="messages-list" id="messagesList" style="display:none"></div>

        </div><!-- /messages-wrap -->

        <!-- Input bar -->
        <div class="input-area">
            <div class="input-wrap">
                <div class="input-row">
                    <textarea class="chat-textarea" id="chatInput"
                        placeholder="Ask anything about the SAT…"
                        rows="1"
                        aria-label="Chat input"><?= $prefillQuestion ?></textarea>
                    <button class="input-send" id="sendBtn" type="button" onclick="sendMessage()" disabled>
                        <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    </button>
                </div>
                <div class="input-footer">
                    <span class="input-hint">
                        <kbd>Enter</kbd> to send &nbsp;·&nbsp; <kbd>Shift+Enter</kbd> for new line
                    </span>
                    <span class="input-hint" id="charCount" style="color:var(--tx3)"></span>
                </div>
            </div>
        </div>

    </div><!-- /chat-area -->

</div><!-- /ai-shell -->

<script>
(function(){
'use strict';

/* ── Sidebar overlay (shared with dashboard/schedule pattern) ── */
var ov = document.getElementById('sidebarOverlay');
if (ov) {
    ov.addEventListener('click', function(){
        var sb = document.getElementById('sidebar');
        if (sb) sb.classList.remove('open');
        ov.classList.remove('show');
        document.body.style.overflow = '';
    });
}
window.addEventListener('resize', function(){
    if (window.innerWidth > 900) {
        var sb = document.getElementById('sidebar');
        if (sb) sb.classList.remove('open');
        if (ov) ov.classList.remove('show');
        document.body.style.overflow = '';
    }
});

/* ── History sidebar toggle ── */
window.toggleHistSidebar = function(){
    var hs = document.getElementById('histSidebar');
    if (!hs) return;
    hs.classList.toggle('open');
};

/* ── Textarea auto-resize ── */
var textarea  = document.getElementById('chatInput');
var sendBtn   = document.getElementById('sendBtn');
var charCount = document.getElementById('charCount');

function resizeTA(){
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 180) + 'px';
}
function updateSendBtn(){
    var val = textarea.value.trim();
    sendBtn.disabled = val.length === 0;
    charCount.textContent = val.length > 0 ? val.length + ' chars' : '';
}
textarea.addEventListener('input', function(){ resizeTA(); updateSendBtn(); });
textarea.addEventListener('keydown', function(e){
    if (e.key === 'Enter' && !e.shiftKey){
        e.preventDefault();
        if (!sendBtn.disabled) sendMessage();
    }
});

/* ── Conversation state ── */
var messages      = [];
var isTyping      = false;
var currentSubject = '<?= $prefillSubject ?>';

/* ── Subject ── */
window.updateSubject = function(val){ currentSubject = val; };

/* ── Send ── */
window.sendMessage = function(){
    var text = textarea.value.trim();
    if (!text || isTyping) return;
    doSend(text);
};

window.sendSuggestion = function(text){
    doSend(text);
};

function doSend(text){
    hideWelcome();
    appendMsg('user', text);
    textarea.value = '';
    resizeTA();
    updateSendBtn();
    showTyping();
    callAPI(text);
}

/* ── UI helpers ── */
function hideWelcome(){
    var ws = document.getElementById('welcomeScreen');
    var ml = document.getElementById('messagesList');
    if (ws) ws.style.display = 'none';
    if (ml) ml.style.display = 'flex';
}

function appendMsg(role, text){
    var ml = document.getElementById('messagesList');
    var isUser = role === 'user';
    var time   = new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});

    var div = document.createElement('div');
    div.className = 'msg msg-' + (isUser ? 'user' : 'ai');

    var avatarHtml = isUser
        ? '<div class="msg-avatar msg-avatar-user"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>'
        : '<div class="msg-avatar msg-avatar-ai"><svg viewBox="0 0 24 24"><path d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" stroke-linecap="round" stroke-linejoin="round"/></svg></div>';

    div.innerHTML = avatarHtml +
        '<div class="msg-content">' +
            '<div class="msg-bubble">' + escHtml(text) + '</div>' +
            '<div class="msg-time">' + time + '</div>' +
        '</div>';

    ml.appendChild(div);
    scrollBottom();
    messages.push({role: isUser ? 'user' : 'assistant', content: text});
}

function appendAIMsg(text){
    var ml   = document.getElementById('messagesList');
    var time = new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
    var div  = document.createElement('div');
    div.className = 'msg msg-ai';
    div.innerHTML =
        '<div class="msg-avatar msg-avatar-ai"><svg viewBox="0 0 24 24"><path d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" stroke-linecap="round" stroke-linejoin="round"/></svg></div>' +
        '<div class="msg-content">' +
            '<div class="msg-bubble">' + simpleMarkdown(text) + '</div>' +
            '<div class="msg-time">' + time + '</div>' +
        '</div>';
    ml.appendChild(div);
    scrollBottom();
    messages.push({role: 'assistant', content: text});
}

function showTyping(){
    isTyping = true;
    sendBtn.disabled = true;
    var ml  = document.getElementById('messagesList');
    var div = document.createElement('div');
    div.className = 'msg msg-ai';
    div.id = 'typingMsg';
    div.innerHTML =
        '<div class="msg-avatar msg-avatar-ai"><svg viewBox="0 0 24 24"><path d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" stroke-linecap="round" stroke-linejoin="round"/></svg></div>' +
        '<div class="msg-content"><div class="msg-bubble"><div class="typing-indicator"><div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div></div></div></div>';
    ml.appendChild(div);
    scrollBottom();
}

function hideTyping(){
    isTyping = false;
    var t = document.getElementById('typingMsg');
    if (t) t.remove();
    updateSendBtn();
}

function scrollBottom(){
    var mw = document.getElementById('messagesWrap');
    mw.scrollTop = mw.scrollHeight;
}

/* ── API call ── */
function callAPI(userText){
    fetch('/api/ai-tutor.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
        body: JSON.stringify({
            message: userText,
            subject: currentSubject,
            history: messages.slice(-20)
        })
    })
    .then(function(r){ return r.json(); })
    .then(function(res){
        hideTyping();
        if (res.success && res.reply) {
            appendAIMsg(res.reply);
        } else {
            appendAIMsg("I'm sorry, I couldn't process that. Please try again.");
        }
    })
    .catch(function(){
        hideTyping();
        appendAIMsg("Something went wrong. Please check your connection and try again.");
    });
}

/* ── New conversation ── */
window.newConversation = function(){
    messages = [];
    var ml = document.getElementById('messagesList');
    var ws = document.getElementById('welcomeScreen');
    if (ml) { ml.innerHTML = ''; ml.style.display = 'none'; }
    if (ws) ws.style.display = 'block';
    textarea.value = '';
    resizeTA();
    updateSendBtn();
    document.querySelectorAll('.hist-item').forEach(function(i){ i.classList.remove('active'); });
};
window.clearChat = newConversation;

/* ── Load conversation ── */
window.loadConversation = function(id){
    document.querySelectorAll('.hist-item').forEach(function(i){
        i.classList.toggle('active', parseInt(i.dataset.id) === id);
    });
    // Actual load via AJAX would go here — placeholder for now
    fetch('/api/ai-tutor-history.php?id=' + id, {headers:{'X-Requested-With':'XMLHttpRequest'}})
    .then(function(r){ return r.json(); })
    .then(function(res){
        if (!res.success || !res.messages) return;
        messages = [];
        var ml = document.getElementById('messagesList');
        var ws = document.getElementById('welcomeScreen');
        if (ml) { ml.innerHTML = ''; ml.style.display = 'flex'; }
        if (ws) ws.style.display = 'none';
        (res.messages || []).forEach(function(m){
            appendMsg(m.role === 'user' ? 'user' : 'ai', m.content);
        });
    })
    .catch(function(){});
};

/* ── History search filter ── */
document.getElementById('histSearch').addEventListener('input', function(){
    var q = this.value.toLowerCase();
    document.querySelectorAll('.hist-item').forEach(function(item){
        var title = (item.querySelector('.hist-item-title') || {}).textContent || '';
        item.style.display = title.toLowerCase().includes(q) ? '' : 'none';
    });
});

/* ── Simple markdown renderer ── */
function simpleMarkdown(text){
    return escHtml(text)
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        .replace(/`(.+?)`/g, '<code style="font-family:var(--fm);background:var(--bg);padding:1px 5px;border-radius:4px;font-size:.875em">$1</code>')
        .replace(/\n/g, '<br>');
}

function escHtml(s){
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ── Prefill question ── */
<?php if ($prefillQuestion): ?>
setTimeout(function(){
    textarea.value = <?= json_encode(html_entity_decode($prefillQuestion, ENT_QUOTES)) ?>;
    resizeTA();
    updateSendBtn();
    textarea.focus();
}, 300);
<?php else: ?>
textarea.focus();
<?php endif; ?>

}());
</script>
</body>
</html>