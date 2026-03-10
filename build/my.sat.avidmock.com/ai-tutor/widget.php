<?php
/**
 * /ai-tutor/widget.php — AI Tutor Mini Widget
 *
 * Embeddable quick-ask panel included on lesson pages, quiz results, etc.
 * Self-contained: uses scoped CSS with unique ID prefix to avoid collisions.
 *
 * USAGE (PHP include on a lesson page):
 *   <?php
 *     $widgetContext = 'Help me with quadratic equations';  // optional
 *     $widgetSubject = 'math';                               // optional
 *     $widgetLesson  = 'quadratic-equations';               // optional
 *     require_once ROOT . '/ai-tutor/widget.php';
 *   ?>
 *
 * STANDALONE (iframe embed):
 *   /ai-tutor/widget.php?embed=1&subject=math&context=linear+equations
 */

$isStandalone = (basename($_SERVER['PHP_SELF']) === 'widget.php');

if ($isStandalone) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
    Auth::requireStudent();
    $wUser     = User::findById($_SESSION['user_id']);
    $wInitial  = strtoupper(substr(explode(' ', $wUser['name'] ?? 'S')[0], 0, 1));
} else {
    global $user;
    $wUser    = $user ?? [];
    $wInitial = strtoupper(substr(explode(' ', $wUser['name'] ?? 'S')[0], 0, 1));
}

$widgetContext = $widgetContext ?? trim($_GET['context'] ?? '');
$widgetSubject = $widgetSubject ?? (in_array($_GET['subject'] ?? '', ['math','reading_writing','general']) ? $_GET['subject'] : 'general');
$widgetLesson  = $widgetLesson  ?? trim($_GET['lesson'] ?? '');

/* Unique widget ID prevents CSS/JS conflicts when multiple widgets on page */
$wid = 'aitw' . substr(md5(uniqid('w', true)), 0, 7);

$isEmbed = $isStandalone && isset($_GET['embed']);
if ($isEmbed) {
    header('Content-Type: text/html; charset=utf-8');
    header('X-Frame-Options: SAMEORIGIN');
    echo '<!DOCTYPE html><html lang="en"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>AI Tutor</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/KaTeX/0.16.9/katex.min.css">
    <style>*,*::before,*::after{box-sizing:border-box}html,body{height:100%;margin:0;padding:12px;background:transparent;overflow:hidden}</style>
    </head><body>';
}
?>

<div class="<?= $wid ?>-widget ai-tutor-widget" id="<?= $wid ?>"
     data-subject="<?= htmlspecialchars($widgetSubject) ?>"
     style="font-family:'DM Sans',-apple-system,BlinkMacSystemFont,sans-serif">

<style>
/* ═══════════════════════════════════════════════════════
   WIDGET SCOPED STYLES — all prefixed #<?= $wid ?>
   Safe to embed multiple times on the same page.
═══════════════════════════════════════════════════════ */
#<?= $wid ?> {
    --c-dk:  #143230;
    --c-ac:  #1fe290;
    --c-ac2: #15c87a;
    --c-tx:  #111827;
    --c-tx2: #374151;
    --c-tx3: #9ca3af;
    --c-bg:  #f4f7f6;
    --c-bd:  #e2eae8;
    --c-wh:  #ffffff;
    --c-err: #ef4444;
    --c-ok:  #10b981;
    --c-ff:  'DM Sans',-apple-system,BlinkMacSystemFont,sans-serif;
    display: flex;
    flex-direction: column;
    background: var(--c-wh);
    border: 1.5px solid var(--c-bd);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(20,50,48,.07);
    max-height: 500px;
    transition: box-shadow .25s, border-color .2s;
    -webkit-font-smoothing: antialiased;
}
#<?= $wid ?>:focus-within {
    box-shadow: 0 4px 28px rgba(20,50,48,.1), 0 0 0 3px rgba(31,226,144,.1);
    border-color: rgba(31,226,144,.35);
}
#<?= $wid ?>.is-collapsed .w-body,
#<?= $wid ?>.is-collapsed .w-footer { display: none !important; }

/* ── HEADER ── */
#<?= $wid ?> .w-header {
    display: flex; align-items: center; gap: 10px;
    padding: 11px 14px;
    background: var(--c-dk);
    flex-shrink: 0; cursor: pointer;
    user-select: none; -webkit-user-select: none;
    transition: background .18s;
}
#<?= $wid ?> .w-header:hover { background: #1a3e3b; }
#<?= $wid ?> .w-hico {
    width: 30px; height: 30px; border-radius: 8px;
    background: rgba(31,226,144,.12);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
#<?= $wid ?> .w-hico svg { width: 14px; height: 14px; stroke: var(--c-ac); fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
#<?= $wid ?> .w-htext { flex: 1; min-width: 0; }
#<?= $wid ?> .w-htitle { font-size: .875rem; font-weight: 700; color: #fff; letter-spacing: -.01em; }
#<?= $wid ?> .w-hsub   { font-size: .5625rem; color: rgba(255,255,255,.32); text-transform: uppercase; letter-spacing: .5px; margin-top: 1px; }
#<?= $wid ?> .w-pulse {
    width: 7px; height: 7px; border-radius: 50%;
    background: var(--c-ac); flex-shrink: 0;
    animation: <?= $wid ?>_pulse 2.2s ease-in-out infinite;
}
#<?= $wid ?> .w-pulse.busy { background: #f59e0b; animation-duration: .65s; }
@keyframes <?= $wid ?>_pulse {
    0%,100% { opacity:1; transform:scale(1); }
    50%      { opacity:.3; transform:scale(.55); }
}
#<?= $wid ?> .w-chevron {
    flex-shrink: 0; background: none; border: none; cursor: pointer;
    padding: 4px; color: rgba(255,255,255,.35); transition: color .18s;
}
#<?= $wid ?> .w-chevron:hover { color: #fff; }
#<?= $wid ?> .w-chevron svg {
    width: 15px; height: 15px; stroke: currentColor; fill: none;
    stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round;
    display: block; transition: transform .3s cubic-bezier(.16,1,.3,1);
}
#<?= $wid ?>.is-collapsed .w-chevron svg { transform: rotate(180deg); }

/* ── MESSAGES AREA ── */
#<?= $wid ?> .w-body {
    flex: 1; overflow-y: auto; overflow-x: hidden;
    padding: 14px 14px 6px;
    display: flex; flex-direction: column; gap: 10px;
    min-height: 130px;
}
#<?= $wid ?> .w-body::-webkit-scrollbar { width: 4px; }
#<?= $wid ?> .w-body::-webkit-scrollbar-thumb { background: var(--c-bd); border-radius: 2px; }

/* Welcome text */
#<?= $wid ?> .w-welcome {
    font-size: .8125rem; color: var(--c-tx3);
    text-align: center; padding: 10px 4px; line-height: 1.6;
}

/* Suggestion buttons */
#<?= $wid ?> .w-sugs { display: flex; flex-direction: column; gap: 5px; }
#<?= $wid ?> .w-sug {
    display: flex; align-items: center; gap: 7px;
    padding: 8px 10px; border-radius: 8px;
    border: 1.5px solid var(--c-bd); background: var(--c-bg);
    font-size: .75rem; font-weight: 600; color: var(--c-tx2);
    cursor: pointer; text-align: left; font-family: var(--c-ff);
    transition: border-color .18s, background .18s, color .18s;
}
#<?= $wid ?> .w-sug:hover { border-color: var(--c-ac); color: var(--c-dk); background: rgba(31,226,144,.03); }
#<?= $wid ?> .w-sug svg { width: 12px; height: 12px; stroke: var(--c-ac); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }

/* Bubbles */
#<?= $wid ?> .w-row { display: flex; gap: 7px; animation: <?= $wid ?>_in .3s cubic-bezier(.16,1,.3,1); }
@keyframes <?= $wid ?>_in { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:none; } }
#<?= $wid ?> .w-row-user { flex-direction: row-reverse; }
#<?= $wid ?> .w-av {
    width: 26px; height: 26px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: .625rem; font-weight: 800; flex-shrink: 0; margin-top: 2px;
}
#<?= $wid ?> .w-av-ai   { background: var(--c-dk); }
#<?= $wid ?> .w-av-ai svg { width: 12px; height: 12px; stroke: var(--c-ac); fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
#<?= $wid ?> .w-av-user { background: linear-gradient(135deg,var(--c-ac),#0da367); color: var(--c-dk); font-weight: 800; }
#<?= $wid ?> .w-bub {
    padding: 9px 11px; border-radius: 12px;
    font-size: .8125rem; line-height: 1.65;
    max-width: calc(100% - 60px);
    word-break: break-word;
}
#<?= $wid ?> .w-row-user .w-bub {
    background: var(--c-dk); color: rgba(255,255,255,.88);
    border-bottom-right-radius: 3px; margin-left: auto;
}
#<?= $wid ?> .w-row-ai .w-bub {
    background: var(--c-bg); border: 1px solid var(--c-bd); color: var(--c-tx);
    border-bottom-left-radius: 3px;
}
/* Markdown inside AI bubble */
#<?= $wid ?> .w-row-ai .w-bub p        { margin-bottom: 6px; }
#<?= $wid ?> .w-row-ai .w-bub p:last-child { margin-bottom: 0; }
#<?= $wid ?> .w-row-ai .w-bub strong   { font-weight: 700; color: var(--c-tx); }
#<?= $wid ?> .w-row-ai .w-bub em       { font-style: italic; color: var(--c-tx2); }
#<?= $wid ?> .w-row-ai .w-bub code     { font-size: .8em; background: rgba(20,50,48,.07); padding: 1px 4px; border-radius: 3px; }
#<?= $wid ?> .w-row-ai .w-bub ul       { padding-left: 16px; margin: 4px 0; }
#<?= $wid ?> .w-row-ai .w-bub li       { margin-bottom: 3px; }
#<?= $wid ?> .w-row-ai .w-bub h3,
#<?= $wid ?> .w-row-ai .w-bub h4       { font-size: .875rem; font-weight: 700; margin: 8px 0 4px; }

/* Typing dots */
#<?= $wid ?> .w-typing { display: flex; gap: 4px; align-items: center; padding: 3px 2px; }
#<?= $wid ?> .w-typing span {
    width: 6px; height: 6px; border-radius: 50%; background: var(--c-tx3);
    animation: <?= $wid ?>_td 1.4s ease-in-out infinite;
}
#<?= $wid ?> .w-typing span:nth-child(2) { animation-delay:.2s; }
#<?= $wid ?> .w-typing span:nth-child(3) { animation-delay:.4s; }
@keyframes <?= $wid ?>_td { 0%,60%,100%{transform:translateY(0);opacity:.4}30%{transform:translateY(-5px);opacity:1} }

/* ── FOOTER / INPUT ── */
#<?= $wid ?> .w-footer {
    flex-shrink: 0;
    padding: 9px 12px 11px;
    border-top: 1px solid var(--c-bd);
    background: rgba(244,247,246,.55);
    display: flex; flex-direction: column; gap: 6px;
}
#<?= $wid ?> .w-irow { display: flex; gap: 6px; align-items: flex-end; }
#<?= $wid ?> .w-ta {
    flex: 1; padding: 8px 11px;
    background: var(--c-wh); border: 1.5px solid var(--c-bd);
    border-radius: 10px; font-family: var(--c-ff);
    font-size: .8125rem; color: var(--c-tx); resize: none;
    outline: none; line-height: 1.5; min-height: 38px; max-height: 96px;
    transition: border-color .18s; overflow-y: auto;
}
#<?= $wid ?> .w-ta:focus     { border-color: var(--c-ac); }
#<?= $wid ?> .w-ta::placeholder { color: var(--c-tx3); }
#<?= $wid ?> .w-send {
    width: 34px; height: 34px; border-radius: 9px;
    background: var(--c-dk); border: none; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: background .18s, transform .18s;
}
#<?= $wid ?> .w-send:hover:not([disabled]) { background: var(--c-ac); transform: scale(1.06); }
#<?= $wid ?> .w-send[disabled]  { background: var(--c-bd); cursor: not-allowed; }
#<?= $wid ?> .w-send svg { width: 14px; height: 14px; stroke: var(--c-ac); fill: none; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
#<?= $wid ?> .w-send:hover:not([disabled]) svg { stroke: var(--c-dk); }
#<?= $wid ?> .w-send[disabled] svg { stroke: var(--c-tx3); }
#<?= $wid ?> .w-meta { display: flex; justify-content: space-between; align-items: center; }
#<?= $wid ?> .w-hint { font-size: .5625rem; color: var(--c-tx3); letter-spacing: .1px; }
#<?= $wid ?> .w-fulllink {
    font-size: .6875rem; font-weight: 700; color: var(--c-ac);
    text-decoration: none; display: flex; align-items: center; gap: 3px;
    transition: opacity .18s;
}
#<?= $wid ?> .w-fulllink:hover { opacity: .7; }
#<?= $wid ?> .w-fulllink svg { width: 10px; height: 10px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
</style>

<!-- HEADER -->
<div class="w-header" onclick="<?= $wid ?>_toggle(event)">
    <div class="w-hico">
        <svg viewBox="0 0 24 24"><path d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </div>
    <div class="w-htext">
        <div class="w-htitle">AI Tutor</div>
        <div class="w-hsub" id="<?= $wid ?>-sub">Ask anything · SAT focused</div>
    </div>
    <div class="w-pulse" id="<?= $wid ?>-pulse"></div>
    <button class="w-chevron" type="button" onclick="<?= $wid ?>_toggle(event)" aria-label="Toggle widget">
        <svg viewBox="0 0 24 24"><polyline points="18 15 12 9 6 15"/></svg>
    </button>
</div>

<!-- MESSAGES -->
<div class="w-body" id="<?= $wid ?>-body">
    <div class="w-welcome" id="<?= $wid ?>-welcome">
        Ask me anything about <?= $widgetLesson ? htmlspecialchars($widgetLesson) : 'this topic' ?> — I'll explain step by step.
    </div>

    <?php
    $suggestions = [];
    if ($widgetSubject === 'math') {
        $suggestions = [
            ['icon'=>'△', 'text'=>'Explain this concept with an example'],
            ['icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>', 'text'=>'Give me a practice problem like this'],
            ['icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>', 'text'=>'What mistakes should I avoid here?'],
        ];
    } elseif ($widgetSubject === 'reading_writing') {
        $suggestions = [
            ['icon'=>'', 'text'=>'How do I approach this question type?'],
            ['icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>',  'text'=>'Show me an elimination strategy'],
            ['icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',  'text'=>'What are the common traps here?'],
        ];
    } else {
        $suggestions = [
            ['icon'=>'', 'text'=>'Explain this step by step'],
            ['icon'=>'', 'text'=>'Give me a practice problem'],
            ['icon'=>'', 'text'=>'What are common mistakes here?'],
        ];
    }
    ?>
    <div class="w-sugs" id="<?= $wid ?>-sugs">
        <?php foreach ($suggestions as $s): ?>
        <button class="w-sug" type="button" onclick="<?= $wid ?>_send(<?= json_encode($s['text']) ?>)">
            <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            <?= htmlspecialchars($s['text']) ?>
        </button>
        <?php endforeach; ?>
    </div>

    <div id="<?= $wid ?>-msgs"></div>
</div>

<!-- FOOTER / INPUT -->
<div class="w-footer">
    <div class="w-irow">
        <textarea
            class="w-ta"
            id="<?= $wid ?>-ta"
            placeholder="<?= $widgetContext ? 'Ask about: '.htmlspecialchars(mb_substr($widgetContext,0,40)).'…' : 'Ask anything about this topic…' ?>"
            rows="1"
            maxlength="2000"
            onkeydown="<?= $wid ?>_key(event)"
            oninput="<?= $wid ?>_resize(this)"
        ></textarea>
        <button class="w-send" id="<?= $wid ?>-send" type="button" disabled
                onclick="<?= $wid ?>_send()" aria-label="Send">
            <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        </button>
    </div>
    <div class="w-meta">
        <span class="w-hint">Enter to send &nbsp;·&nbsp; Shift+Enter new line</span>
        <?php
        $fullChatUrl = '/ai-tutor/';
        if ($widgetContext) {
            $fullChatUrl .= '?q=' . urlencode($widgetContext) . '&subject=' . urlencode($widgetSubject);
        }
        ?>
        <a class="w-fulllink" href="<?= htmlspecialchars($fullChatUrl) ?>">
            Full chat
            <svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
        </a>
    </div>
</div>

</div><!-- /widget -->

<script>
(function(){
'use strict';
var W = '<?= $wid ?>';
var $ = function(s){ return document.getElementById(W+'-'+s); };

var subject    = <?= json_encode($widgetSubject) ?>;
var ctxPrefill = <?= json_encode($widgetContext) ?>;
var lesson     = <?= json_encode($widgetLesson)  ?>;
var userInitial = '<?= $wInitial ?>';

var state = {
    collapsed:      false,
    streaming:      false,
    conversationId: null,
    history:        [],
    hasMessages:    false,
};

var bodyEl    = $('body');
var msgsEl    = $('msgs');
var welcomeEl = $('welcome');
var sugsEl    = $('sugs');
var taEl      = $('ta');
var sendEl    = $('send');
var pulseEl   = $('pulse');
var subEl     = $('sub');

/* ── Textarea auto-resize ── */
window[W+'_resize'] = function(el){
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 96) + 'px';
    sendEl.disabled = (el.value.trim().length === 0 || state.streaming);
};

/* ── Keydown ── */
window[W+'_key'] = function(e){
    if (e.key === 'Enter' && !e.shiftKey){
        e.preventDefault();
        if (!sendEl.disabled) window[W+'_send']();
    }
};

/* ── Toggle collapse ── */
window[W+'_toggle'] = function(e){
    if (e && e.target && e.target.tagName === 'BUTTON' && !e.target.classList.contains('w-chevron')) return;
    state.collapsed = !state.collapsed;
    document.getElementById(W).classList.toggle('is-collapsed', state.collapsed);
};

/* ── Show/hide welcome area ── */
function hideWelcome(){
    if (welcomeEl) welcomeEl.style.display = 'none';
    if (sugsEl)    sugsEl.style.display    = 'none';
}

/* ── Append bubble, returns the bubble content element ── */
function appendBubble(role, text){
    var isUser = role === 'user';
    var row = document.createElement('div');
    row.className = 'w-row w-row-' + (isUser ? 'user' : 'ai');

    var avHtml = isUser
        ? '<div class="w-av w-av-user">' + userInitial + '</div>'
        : '<div class="w-av w-av-ai"><svg viewBox="0 0 24 24"><path d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" stroke-linecap="round" stroke-linejoin="round"/></svg></div>';

    var bubHtml = isUser
        ? esc(text).replace(/\n/g, '<br>')
        : md(text);

    row.innerHTML = avHtml + '<div class="w-bub">' + bubHtml + '</div>';
    msgsEl.appendChild(row);
    scrollDown();
    return row.querySelector('.w-bub');
}

/* ── Typing indicator ── */
function showTyping(){
    var row = document.createElement('div');
    row.className = 'w-row w-row-ai';
    row.id = W + '-typing';
    row.innerHTML = '<div class="w-av w-av-ai"><svg viewBox="0 0 24 24"><path d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" stroke-linecap="round" stroke-linejoin="round"/></svg></div>' +
        '<div class="w-bub"><div class="w-typing"><span></span><span></span><span></span></div></div>';
    msgsEl.appendChild(row);
    scrollDown();
}
function removeTyping(){
    var t = document.getElementById(W+'-typing');
    if (t) t.remove();
}

/* ── Send message ── */
window[W+'_send'] = function(text){
    var msg = text || taEl.value.trim();
    if (!msg || state.streaming) return;

    /* If context set and first message, prepend it */
    var apiMsg = msg;
    if (ctxPrefill && !state.hasMessages) {
        apiMsg = 'I am studying: ' + ctxPrefill + (lesson ? ' (lesson: '+lesson+')' : '') + '\n\nMy question: ' + msg;
    }

    hideWelcome();
    appendBubble('user', msg);
    state.history.push({ role: 'user', content: apiMsg });
    state.hasMessages = true;

    taEl.value = '';
    taEl.style.height = 'auto';
    sendEl.disabled = true;
    state.streaming = true;
    pulseEl.classList.add('busy');
    subEl.textContent = 'Thinking…';
    scrollDown();
    showTyping();

    fetch('/ai-tutor/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({
            message:         apiMsg,
            subject:         subject,
            conversation_id: state.conversationId,
            history:         state.history.slice(-10, -1),
        })
    })
    .then(function(response){
        removeTyping();
        subEl.textContent = 'Responding…';

        var reader   = response.body.getReader();
        var decoder  = new TextDecoder();
        var fullText = '';
        var bubble   = null;

        function read(){
            return reader.read().then(function(res){
                if (res.done){
                    done(fullText);
                    return;
                }
                var chunk = decoder.decode(res.value, { stream: true });
                chunk.split('\n').forEach(function(line){
                    if (!line.startsWith('data: ')) return;
                    var d = line.slice(6).trim();
                    if (d === '[DONE]') return;
                    try {
                        var p = JSON.parse(d);
                        if (p.conversation_id) state.conversationId = p.conversation_id;
                        if (p.delta) {
                            if (!bubble) bubble = appendBubble('ai', '');
                            fullText += p.delta;
                            bubble.innerHTML = md(fullText);
                            scrollDown();
                        }
                    } catch(e){}
                });
                return read();
            });
        }
        return read();
    })
    .catch(function(){
        removeTyping();
        appendBubble('ai', 'Connection error — please try again.');
        done('');
    });

    function done(text){
        if (text) state.history.push({ role: 'assistant', content: text });
        state.streaming  = false;
        sendEl.disabled  = taEl.value.trim().length === 0;
        pulseEl.classList.remove('busy');
        subEl.textContent = 'Ask anything · SAT focused';
    }
};

/* ── Scroll ── */
function scrollDown(){
    bodyEl.scrollTop = bodyEl.scrollHeight;
}

/* ── Helpers ── */
function esc(t){
    return t.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function md(t){
    return t
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/```[\s\S]*?```/g, function(m){
            return '<pre style="background:rgba(20,50,48,.06);padding:8px 10px;border-radius:7px;font-size:.78em;overflow-x:auto;margin:6px 0"><code>' + m.replace(/```\w*\n?/g,'').replace(/```/g,'').trim() + '</code></pre>';
        })
        .replace(/`([^`]+)`/g, '<code>$1</code>')
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        .replace(/^### (.+)$/gm, '<h4>$1</h4>')
        .replace(/^## (.+)$/gm,  '<h3>$1</h3>')
        .replace(/^\* (.+)$/gm,  '<li>$1</li>')
        .replace(/^- (.+)$/gm,   '<li>$1</li>')
        .replace(/(<li>[\s\S]+?<\/li>)/g, '<ul>$1</ul>')
        .replace(/^> (.+)$/gm, '<blockquote style="border-left:2px solid var(--c-ac);padding:4px 10px;margin:6px 0;color:var(--c-tx2);font-style:italic">$1</blockquote>')
        .replace(/\n\n/g, '</p><p>')
        .replace(/\n/g,   '<br>');
}

}());
</script>

<?php if ($isEmbed): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/KaTeX/0.16.9/katex.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/KaTeX/0.16.9/contrib/auto-render.min.js"></script>
</body>
</html>
<?php endif; ?>