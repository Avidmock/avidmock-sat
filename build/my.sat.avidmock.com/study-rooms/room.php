<?php
/**
 * Study Room — The live game room page.
 * States: waiting (lobby) -> playing (questions) -> finished (results)
 * All state updates via polling /api/study-room.php?action=state every 2 seconds.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';

$user = Auth::requireStudent();
$userId    = (int)$_SESSION['user_id'];
$firstName = trim($user['first_name'] ?? 'Student');

$roomCode = strtoupper(trim($_GET['code'] ?? ''));
if (strlen($roomCode) !== 6) {
    header('Location: /study-rooms/');
    exit;
}

$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Room <?= e($roomCode) ?> — AvidMock Study Room</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:ital,opsz,wght@0,9..40,300..800;1,9..40,300..800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --dk:#143230;--ac:#1fe290;--ac2:#17c87a;--tx:#1a1a2e;--bg:#f7faf9;
  --card:#ffffff;--card-border:rgba(20,50,48,.06);
  --shadow-sm:0 1px 3px rgba(20,50,48,.06);
  --shadow-md:0 4px 16px rgba(20,50,48,.08);
  --shadow-lg:0 8px 32px rgba(20,50,48,.10);
  --shadow-glow:0 0 24px rgba(31,226,144,.18);
  --radius:16px;--radius-sm:10px;--radius-xs:6px;
  --font:'DM Sans',system-ui,sans-serif;
  --mono:'DM Mono','Fira Code',monospace;
  --transition:cubic-bezier(.4,0,.2,1);
  --correct:#1fe290;--wrong:#ff6b6b;
}
html{font-size:16px;scroll-behavior:smooth;-webkit-font-smoothing:antialiased}
body{font-family:var(--font);color:var(--tx);background:var(--bg);min-height:100vh;line-height:1.55}
a{color:var(--ac2);text-decoration:none}

.wrap{max-width:900px;margin:0 auto;padding:24px 20px 80px}

/* ── Back link ── */
.back-link{
  display:inline-flex;align-items:center;gap:6px;font-size:.85rem;color:#888;
  margin-bottom:20px;transition:color .2s;
}
.back-link:hover{color:var(--dk)}
.back-link svg{width:16px;height:16px}

/* ── State sections (show/hide) ── */
.state-section{display:none}
.state-section.active{display:block}

/* ═══ LOBBY ═══ */
.lobby-card{
  background:var(--card);border:1px solid var(--card-border);border-radius:var(--radius);
  padding:40px;text-align:center;
}
.lobby-card h1{font-size:1.6rem;font-weight:700;color:var(--dk);margin-bottom:8px}
.lobby-card .subtitle{font-size:.95rem;color:#888;margin-bottom:28px}

.room-code-display{
  display:inline-block;font-family:var(--mono);font-size:3rem;font-weight:700;
  letter-spacing:.4em;padding:20px 40px;background:rgba(31,226,144,.06);
  border:2px dashed rgba(31,226,144,.3);border-radius:var(--radius);color:var(--dk);
  margin-bottom:8px;cursor:pointer;position:relative;user-select:all;
}
.room-code-display:hover{background:rgba(31,226,144,.1);border-color:var(--ac)}
.copy-hint{font-size:.78rem;color:#aaa;margin-bottom:32px}

.lobby-info{
  display:flex;justify-content:center;gap:32px;margin-bottom:32px;flex-wrap:wrap;
}
.lobby-info-item{text-align:center}
.lobby-info-item .val{font-size:1.1rem;font-weight:700;color:var(--dk)}
.lobby-info-item .lbl{font-size:.78rem;color:#888;margin-top:2px}

.players-grid{
  display:flex;flex-wrap:wrap;justify-content:center;gap:16px;margin-bottom:32px;
}
.player-bubble{
  display:flex;flex-direction:column;align-items:center;gap:6px;
  animation:playerJoin .5s var(--transition);
}
@keyframes playerJoin{
  from{opacity:0;transform:scale(.8) translateY(10px)}
  to{opacity:1;transform:scale(1) translateY(0)}
}
.player-avatar{
  width:52px;height:52px;border-radius:50%;background:var(--ac);color:var(--dk);
  display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.1rem;
  border:3px solid #fff;box-shadow:var(--shadow-md);
}
.player-avatar.host{border-color:var(--ac);box-shadow:0 0 0 3px rgba(31,226,144,.2)}
.player-name{font-size:.78rem;font-weight:600;color:#555;max-width:80px;text-align:center;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

.lobby-actions{display:flex;justify-content:center;gap:12px;flex-wrap:wrap}
.btn{
  display:inline-flex;align-items:center;justify-content:center;gap:8px;
  padding:14px 28px;border-radius:var(--radius-xs);font-size:.95rem;font-weight:600;
  border:none;cursor:pointer;font-family:var(--font);transition:all .25s var(--transition);
}
.btn-primary{background:var(--ac);color:var(--dk);box-shadow:0 4px 16px rgba(31,226,144,.3)}
.btn-primary:hover{background:#2dffa0;transform:translateY(-2px);box-shadow:0 6px 24px rgba(31,226,144,.4)}
.btn-primary:disabled{opacity:.5;cursor:not-allowed;transform:none}
.btn-ghost{background:transparent;color:#888;border:1.5px solid rgba(20,50,48,.1)}
.btn-ghost:hover{border-color:var(--dk);color:var(--dk)}

.share-row{
  display:flex;justify-content:center;gap:8px;margin-top:20px;flex-wrap:wrap;
}
.share-btn{
  padding:8px 14px;border-radius:var(--radius-xs);font-size:.8rem;font-weight:600;
  border:1.5px solid rgba(20,50,48,.08);background:#fff;color:#555;cursor:pointer;
  font-family:var(--font);transition:all .2s;display:flex;align-items:center;gap:5px;
}
.share-btn:hover{border-color:var(--ac);color:var(--ac2);background:rgba(31,226,144,.04)}

/* ═══ PLAYING ═══ */
.game-header{
  display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;
  flex-wrap:wrap;gap:12px;
}
.q-counter{font-size:.85rem;font-weight:600;color:#888}
.q-counter span{color:var(--dk)}

.timer-ring{
  position:relative;width:64px;height:64px;
}
.timer-ring svg{transform:rotate(-90deg)}
.timer-ring circle{fill:none;stroke-width:4;stroke-linecap:round}
.timer-ring .bg{stroke:rgba(20,50,48,.06)}
.timer-ring .fg{stroke:var(--ac);transition:stroke-dashoffset .3s linear,stroke .3s}
.timer-ring .fg.warn{stroke:#ffb347}
.timer-ring .fg.danger{stroke:#ff6b6b}
.timer-text{
  position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
  font-family:var(--mono);font-weight:700;font-size:1.1rem;color:var(--dk);
}

.question-card{
  background:var(--card);border:1px solid var(--card-border);border-radius:var(--radius);
  padding:36px 32px;margin-bottom:20px;
}
.question-text{font-size:1.15rem;font-weight:600;line-height:1.6;color:var(--dk);margin-bottom:0}

.options-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:24px}
@media(max-width:560px){.options-grid{grid-template-columns:1fr}}

.option-card{
  display:flex;align-items:flex-start;gap:14px;
  background:var(--card);border:2px solid rgba(20,50,48,.08);border-radius:var(--radius-sm);
  padding:18px 20px;cursor:pointer;transition:all .2s var(--transition);
  user-select:none;position:relative;overflow:hidden;
}
.option-card:hover{border-color:rgba(31,226,144,.4);background:rgba(31,226,144,.02);transform:translateY(-1px)}
.option-card.selected{border-color:var(--ac);background:rgba(31,226,144,.06);box-shadow:var(--shadow-glow)}
.option-card.correct{border-color:var(--correct);background:rgba(31,226,144,.08)}
.option-card.wrong{border-color:var(--wrong);background:rgba(255,107,107,.06)}
.option-card.disabled{pointer-events:none;opacity:.7}
.option-card.correct .option-letter,.option-card.wrong .option-letter{color:#fff}
.option-card.correct .option-letter{background:var(--correct)}
.option-card.wrong .option-letter{background:var(--wrong)}

.option-letter{
  flex-shrink:0;width:32px;height:32px;border-radius:8px;
  background:rgba(20,50,48,.06);color:var(--dk);font-weight:700;font-size:.85rem;
  display:flex;align-items:center;justify-content:center;transition:all .2s;
}
.option-card.selected .option-letter{background:var(--ac);color:var(--dk)}
.option-text{font-size:.92rem;line-height:1.5;padding-top:4px;flex:1}

/* Feedback flash */
@keyframes correctFlash{0%{box-shadow:0 0 0 0 rgba(31,226,144,.4)}100%{box-shadow:0 0 0 20px rgba(31,226,144,0)}}
@keyframes wrongShake{0%,100%{transform:translateX(0)}20%,60%{transform:translateX(-6px)}40%,80%{transform:translateX(6px)}}
.option-card.correct{animation:correctFlash .6s ease-out}
.option-card.wrong{animation:wrongShake .4s ease-out}

/* Player avatars bar */
.players-bar{
  display:flex;align-items:center;gap:8px;margin-bottom:20px;flex-wrap:wrap;
}
.player-chip{
  display:flex;align-items:center;gap:6px;
  background:var(--card);border:1.5px solid var(--card-border);
  border-radius:20px;padding:4px 12px 4px 4px;font-size:.78rem;font-weight:600;
  transition:all .3s;
}
.player-chip.answered{border-color:var(--ac);background:rgba(31,226,144,.04)}
.player-chip .mini-avatar{
  width:24px;height:24px;border-radius:50%;background:var(--ac);color:var(--dk);
  display:flex;align-items:center;justify-content:center;font-size:.6rem;font-weight:700;
}
.player-chip .check{color:var(--ac);font-size:.85rem;margin-left:2px}

/* Score bar */
.score-bar{
  display:flex;align-items:center;gap:12px;padding:14px 20px;
  background:var(--card);border:1px solid var(--card-border);border-radius:var(--radius-sm);
  overflow-x:auto;
}
.score-entry{
  display:flex;align-items:center;gap:8px;white-space:nowrap;
  font-size:.82rem;font-weight:600;color:#555;
}
.score-entry .pos{
  font-family:var(--mono);font-size:.75rem;color:#bbb;min-width:18px;
}
.score-entry .pts{color:var(--dk);font-family:var(--mono)}
.score-entry.me{color:var(--ac2)}
.score-entry.me .pts{color:var(--ac2)}

/* ═══ BETWEEN QUESTIONS ═══ */
.between-card{
  background:var(--card);border:1px solid var(--card-border);border-radius:var(--radius);
  padding:32px;text-align:center;margin-bottom:20px;
}
.between-card h3{font-size:1.1rem;color:var(--dk);margin-bottom:8px}
.correct-answer-display{
  font-size:1.2rem;font-weight:700;color:var(--correct);margin:12px 0;
}
.explanation{
  font-size:.9rem;color:#666;line-height:1.6;max-width:600px;margin:0 auto 16px;
  background:rgba(31,226,144,.04);border-radius:var(--radius-xs);padding:12px 16px;
}

/* ═══ FINISHED ═══ */
.finished-card{
  background:var(--card);border:1px solid var(--card-border);border-radius:var(--radius);
  padding:48px 32px;text-align:center;position:relative;overflow:hidden;
}
.winner-crown{font-size:3rem;margin-bottom:8px;animation:crownBounce 1s ease-out}
@keyframes crownBounce{
  0%{opacity:0;transform:scale(0) rotate(-20deg)}
  60%{transform:scale(1.3) rotate(5deg)}
  100%{opacity:1;transform:scale(1) rotate(0)}
}
.finished-card h1{font-size:1.8rem;font-weight:700;color:var(--dk);margin-bottom:4px}
.finished-card .subtitle{font-size:1rem;color:#888;margin-bottom:32px}

.final-leaderboard{
  max-width:500px;margin:0 auto 32px;text-align:left;
}
.final-rank-item{
  display:flex;align-items:center;gap:14px;
  padding:14px 16px;border-radius:var(--radius-sm);margin-bottom:8px;
  transition:all .3s var(--transition);
  animation:rankSlide .5s var(--transition) both;
}
.final-rank-item:nth-child(1){animation-delay:.1s}
.final-rank-item:nth-child(2){animation-delay:.2s}
.final-rank-item:nth-child(3){animation-delay:.3s}
@keyframes rankSlide{from{opacity:0;transform:translateX(-20px)}to{opacity:1;transform:translateX(0)}}
.final-rank-item.gold{background:linear-gradient(135deg,rgba(245,166,35,.08),rgba(245,166,35,.02))}
.final-rank-item.silver{background:rgba(168,168,184,.06)}
.final-rank-item.bronze{background:rgba(205,127,50,.04)}
.final-rank-item.me{border:2px solid var(--ac);background:rgba(31,226,144,.04)}

.rank-badge{
  width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;
  font-weight:700;font-size:.9rem;flex-shrink:0;
}
.rank-badge.r1{background:rgba(245,166,35,.15);color:#f5a623}
.rank-badge.r2{background:rgba(168,168,184,.15);color:#8888a0}
.rank-badge.r3{background:rgba(205,127,50,.15);color:#cd7f32}
.rank-badge.rn{background:rgba(20,50,48,.06);color:#888}

.rank-info{flex:1}
.rank-info .name{font-weight:600;font-size:.92rem;color:var(--dk)}
.rank-info .detail{font-size:.78rem;color:#888}
.rank-score{font-family:var(--mono);font-weight:700;font-size:1rem;color:var(--dk)}

.xp-earned{
  display:inline-flex;align-items:center;gap:8px;
  background:rgba(31,226,144,.08);border:1px solid rgba(31,226,144,.2);
  border-radius:var(--radius-sm);padding:14px 24px;margin-bottom:24px;
  font-weight:700;color:var(--ac2);font-size:1.1rem;
}

.finished-actions{display:flex;justify-content:center;gap:12px;flex-wrap:wrap}

/* ═══ LOADING ═══ */
.loading-state{
  text-align:center;padding:80px 20px;
}
.loading-spinner{
  width:40px;height:40px;border:3px solid rgba(20,50,48,.1);border-top-color:var(--ac);
  border-radius:50%;animation:spin .8s linear infinite;margin:0 auto 16px;
}
@keyframes spin{to{transform:rotate(360deg)}}

/* ── Toast ── */
.toast{
  position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(100px);
  background:var(--dk);color:#fff;padding:14px 24px;border-radius:var(--radius-sm);
  font-size:.88rem;font-weight:500;box-shadow:var(--shadow-lg);z-index:999;
  transition:transform .4s var(--transition);display:flex;align-items:center;gap:10px;
}
.toast.show{transform:translateX(-50%) translateY(0)}
.toast.success{background:var(--ac2)}
.toast.error{background:#ff4757}

/* ── Confetti ── */
.confetti-piece{
  position:fixed;top:-20px;width:10px;height:10px;border-radius:2px;
  animation:confettiFall 3s ease-out forwards;z-index:998;pointer-events:none;
}
@keyframes confettiFall{
  0%{opacity:1;transform:translateY(0) rotate(0deg)}
  100%{opacity:0;transform:translateY(100vh) rotate(720deg)}
}

/* ── Main content ── */
.main-content{margin-left:260px;margin-top:56px;padding:32px 32px 80px;min-height:calc(100vh - 56px)}
@media(max-width:768px){.main-content{margin-left:0;padding:20px 16px 72px}}

/* ── Responsive ── */
@media(max-width:640px){
  .wrap{padding:16px 14px 60px}
  .room-code-display{font-size:2rem;padding:14px 24px;letter-spacing:.3em}
  .lobby-card{padding:28px 20px}
  .question-card{padding:24px 20px}
  .question-text{font-size:1rem}
  .option-card{padding:14px 16px}
  .finished-card{padding:32px 20px}
  .lobby-info{gap:20px}
}
</style>
</head>
<body>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/sidebar.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/topbar.php';
?>

<main class="main-content">
<div class="wrap">
  <a href="/study-rooms/" class="back-link">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
    Back to Study Rooms
  </a>

  <!-- Loading state -->
  <div class="state-section active" id="loadingState">
    <div class="loading-state">
      <div class="loading-spinner"></div>
      <p style="color:#888">Loading room...</p>
    </div>
  </div>

  <!-- LOBBY STATE -->
  <div class="state-section" id="lobbyState">
    <div class="lobby-card">
      <h1>Waiting for Players</h1>
      <p class="subtitle" id="lobbyTopic"></p>

      <div class="room-code-display" id="roomCodeDisplay" onclick="copyCode()" title="Click to copy"><?= e($roomCode) ?></div>
      <p class="copy-hint">Click code to copy &middot; Share with friends to join</p>

      <div class="lobby-info">
        <div class="lobby-info-item"><div class="val" id="lobbyPlayerCount">0</div><div class="lbl">Players</div></div>
        <div class="lobby-info-item"><div class="val" id="lobbyQuestionCount">0</div><div class="lbl">Questions</div></div>
        <div class="lobby-info-item"><div class="val" id="lobbyTimer">0s</div><div class="lbl">Per Question</div></div>
      </div>

      <div class="players-grid" id="lobbyPlayers"></div>

      <div class="lobby-actions">
        <button class="btn btn-primary" id="startBtn" onclick="startGame()" style="display:none">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>
          Start Battle
        </button>
        <span id="waitingMsg" style="font-size:.9rem;color:#888;padding:14px">Waiting for host to start...</span>
      </div>

      <div class="share-row">
        <button class="share-btn" onclick="copyLink()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
          Copy Link
        </button>
        <button class="share-btn" onclick="shareWhatsApp()">WhatsApp</button>
        <button class="share-btn" onclick="shareTwitter()">Twitter/X</button>
      </div>
    </div>
  </div>

  <!-- PLAYING STATE -->
  <div class="state-section" id="playingState">
    <div class="game-header">
      <div class="q-counter">Question <span id="qCurrent">1</span> of <span id="qTotal">10</span></div>
      <div class="timer-ring" id="timerRing">
        <svg width="64" height="64" viewBox="0 0 64 64">
          <circle class="bg" cx="32" cy="32" r="28"/>
          <circle class="fg" id="timerCircle" cx="32" cy="32" r="28"
                  stroke-dasharray="175.93" stroke-dashoffset="0"/>
        </svg>
        <div class="timer-text" id="timerText">30</div>
      </div>
    </div>

    <div class="players-bar" id="playersBar"></div>

    <div class="question-card">
      <p class="question-text" id="questionText">Loading question...</p>
    </div>

    <div class="options-grid" id="optionsGrid"></div>

    <div class="score-bar" id="scoreBar"></div>
  </div>

  <!-- BETWEEN QUESTIONS STATE (shows briefly after each answer) -->
  <div class="state-section" id="betweenState">
    <div class="between-card">
      <h3 id="betweenTitle">Correct!</h3>
      <div class="correct-answer-display" id="betweenAnswer"></div>
      <div class="explanation" id="betweenExplanation"></div>
      <p style="font-size:.85rem;color:#888" id="betweenNext">Next question in 3s...</p>
    </div>
    <div class="score-bar" id="betweenScoreBar"></div>
  </div>

  <!-- FINISHED STATE -->
  <div class="state-section" id="finishedState">
    <div class="finished-card">
      <div class="winner-crown" id="finishedIcon"></div>
      <h1 id="finishedTitle">Battle Complete!</h1>
      <p class="subtitle" id="finishedSubtitle"></p>

      <div class="xp-earned" id="xpEarned" style="display:none">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
        <span id="xpAmount"></span>
      </div>

      <div class="final-leaderboard" id="finalLeaderboard"></div>

      <div class="finished-actions">
        <button class="btn btn-primary" onclick="window.location.href='/study-rooms/'">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
          New Battle
        </button>
        <button class="btn btn-ghost" onclick="shareResults()">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
          Share Results
        </button>
      </div>
    </div>
  </div>

  <!-- ERROR STATE -->
  <div class="state-section" id="errorState">
    <div class="lobby-card">
      <h1 style="color:var(--wrong)">Room Not Found</h1>
      <p class="subtitle" id="errorMsg">This room doesn't exist or has expired.</p>
      <div style="margin-top:24px">
        <a href="/study-rooms/" class="btn btn-primary" style="display:inline-flex;width:auto">Back to Study Rooms</a>
      </div>
    </div>
  </div>
</div>
</main>

<div class="toast" id="toast"></div>

<script>
const ROOM_CODE = '<?= e($roomCode) ?>';
const USER_ID = <?= $userId ?>;
const CSRF = '<?= e($csrfToken) ?>';
const POLL_INTERVAL = 2000;
const CIRCUMFERENCE = 2 * Math.PI * 28; // timer ring circumference

let pollTimer = null;
let localTimer = null;
let currentState = null;
let myAnswer = null;
let betweenTimeout = null;
let lastQuestionIndex = -1;
let showingBetween = false;

// ── State management ────────────────────────────────────────
function showState(id) {
  document.querySelectorAll('.state-section').forEach(s => s.classList.remove('active'));
  document.getElementById(id)?.classList.add('active');
}

// ── Polling ─────────────────────────────────────────────────
async function pollState() {
  try {
    const res = await fetch(`/api/study-room.php?action=state&code=${ROOM_CODE}`);
    const data = await res.json();
    if (!data.success) {
      showState('errorState');
      document.getElementById('errorMsg').textContent = data.error || 'Room not found';
      stopPolling();
      return;
    }
    handleState(data);
  } catch (err) {
    console.error('Poll error:', err);
  }
}

function startPolling() {
  pollState();
  pollTimer = setInterval(pollState, POLL_INTERVAL);
}

function stopPolling() {
  if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
}

// ── Handle state update ─────────────────────────────────────
function handleState(data) {
  currentState = data;

  if (data.status === 'waiting') {
    renderLobby(data);
  } else if (data.status === 'playing') {
    renderPlaying(data);
  } else if (data.status === 'finished') {
    renderFinished(data);
    stopPolling();
  } else if (data.status === 'expired') {
    showState('errorState');
    document.getElementById('errorMsg').textContent = 'This room has expired.';
    stopPolling();
  }
}

// ── LOBBY RENDERER ──────────────────────────────────────────
function renderLobby(data) {
  if (showingBetween) return;
  showState('lobbyState');

  document.getElementById('lobbyTopic').textContent = data.topic_label + ' \u00b7 ' + data.question_count + ' questions';
  document.getElementById('lobbyPlayerCount').textContent = data.player_count + '/' + data.max_players;
  document.getElementById('lobbyQuestionCount').textContent = data.question_count;
  document.getElementById('lobbyTimer').textContent = data.time_per_question + 's';

  // Players
  const grid = document.getElementById('lobbyPlayers');
  grid.innerHTML = data.players.map(p => {
    const initial = (p.first_name || 'S').charAt(0).toUpperCase();
    const isHost = p.user_id === data.host_id;
    return `<div class="player-bubble">
      <div class="player-avatar ${isHost ? 'host' : ''}">${initial}</div>
      <div class="player-name">${esc(p.first_name)}${isHost ? ' \u2605' : ''}</div>
    </div>`;
  }).join('');

  // Start button (host only)
  const startBtn = document.getElementById('startBtn');
  const waitMsg = document.getElementById('waitingMsg');
  if (data.host_id === USER_ID) {
    startBtn.style.display = '';
    waitMsg.style.display = 'none';
    startBtn.disabled = data.player_count < 1;
  } else {
    startBtn.style.display = 'none';
    waitMsg.style.display = '';
  }
}

// ── PLAYING RENDERER ────────────────────────────────────────
function renderPlaying(data) {
  if (!data.current_question) return;

  const qIdx = data.current_question_index;

  // New question arrived
  if (qIdx !== lastQuestionIndex) {
    // If showing between-questions, keep it briefly
    if (showingBetween && qIdx === lastQuestionIndex + 1) {
      // Let the between state show for a moment, then switch
    }
    lastQuestionIndex = qIdx;
    myAnswer = null;
    showingBetween = false;

    showState('playingState');
    renderQuestion(data);
    startLocalTimer(data.time_per_question, data.time_remaining);
  }

  // Update players bar
  renderPlayersBar(data);
  renderScoreBar(data.players, 'scoreBar');
}

function renderQuestion(data) {
  const q = data.current_question;
  document.getElementById('qCurrent').textContent = q.index + 1;
  document.getElementById('qTotal').textContent = data.question_count;
  document.getElementById('questionText').textContent = q.question_text;

  const grid = document.getElementById('optionsGrid');
  grid.innerHTML = '';
  const letters = ['A', 'B', 'C', 'D'];
  letters.forEach(letter => {
    if (!q.options[letter]) return;
    const card = document.createElement('div');
    card.className = 'option-card';
    card.dataset.letter = letter;
    card.innerHTML = `<div class="option-letter">${letter}</div><div class="option-text">${esc(q.options[letter])}</div>`;
    card.addEventListener('click', () => selectAnswer(letter));
    grid.appendChild(card);
  });
}

function renderPlayersBar(data) {
  const bar = document.getElementById('playersBar');
  bar.innerHTML = data.players.map(p => {
    const initial = (p.first_name || 'S').charAt(0).toUpperCase();
    const answered = data.answered_users.includes(p.user_id);
    return `<div class="player-chip ${answered ? 'answered' : ''}">
      <div class="mini-avatar">${initial}</div>
      ${esc(p.first_name)}
      ${answered ? '<span class="check">\u2713</span>' : ''}
    </div>`;
  }).join('');
}

function renderScoreBar(players, containerId) {
  const bar = document.getElementById(containerId);
  if (!bar) return;
  const sorted = [...players].sort((a, b) => b.score - a.score);
  bar.innerHTML = sorted.map((p, i) => {
    const isMe = p.user_id === USER_ID;
    return `<div class="score-entry ${isMe ? 'me' : ''}">
      <span class="pos">#${i + 1}</span>
      ${esc(p.first_name)}
      <span class="pts">${p.score.toLocaleString()}</span>
    </div>`;
  }).join('');
}

// ── Local countdown timer ───────────────────────────────────
function startLocalTimer(totalSecs, remaining) {
  if (localTimer) clearInterval(localTimer);
  let timeLeft = remaining ?? totalSecs;
  const circle = document.getElementById('timerCircle');
  const text = document.getElementById('timerText');

  function update() {
    text.textContent = Math.max(0, Math.ceil(timeLeft));
    const fraction = 1 - (timeLeft / totalSecs);
    circle.style.strokeDashoffset = (fraction * CIRCUMFERENCE).toFixed(2);
    circle.classList.remove('warn', 'danger');
    if (timeLeft <= 5) circle.classList.add('danger');
    else if (timeLeft <= 10) circle.classList.add('warn');
  }
  update();

  localTimer = setInterval(() => {
    timeLeft -= 0.1;
    if (timeLeft <= 0) {
      timeLeft = 0;
      clearInterval(localTimer);
      localTimer = null;
      // Auto-submit if not answered
      if (!myAnswer) {
        disableOptions();
      }
    }
    update();
  }, 100);
}

// ── Answer selection ────────────────────────────────────────
async function selectAnswer(letter) {
  if (myAnswer) return;
  myAnswer = letter;

  // Visual feedback immediately (optimistic)
  document.querySelectorAll('.option-card').forEach(c => {
    c.classList.add('disabled');
    if (c.dataset.letter === letter) c.classList.add('selected');
  });

  // Calculate time taken
  const timerText = document.getElementById('timerText');
  const timeLeft = parseFloat(timerText.textContent) || 0;
  const totalTime = currentState?.time_per_question || 30;
  const timeMs = Math.round((totalTime - timeLeft) * 1000);

  try {
    const fd = new FormData();
    fd.append('_csrf', CSRF);
    fd.append('code', ROOM_CODE);
    fd.append('question_index', currentState.current_question_index);
    fd.append('answer', letter);
    fd.append('time_ms', timeMs);

    const res = await fetch('/api/study-room.php?action=answer', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success) {
      showAnswerFeedback(letter, data.is_correct, data.correct_answer, data.explanation, data.points);
    }
  } catch (err) {
    showToast('Failed to submit answer', true);
  }
}

function showAnswerFeedback(selected, isCorrect, correctAnswer, explanation, points) {
  document.querySelectorAll('.option-card').forEach(c => {
    const l = c.dataset.letter;
    if (l === correctAnswer) c.classList.add('correct');
    if (l === selected && !isCorrect) c.classList.add('wrong');
  });

  if (isCorrect) {
    showToast('+' + points + ' points!', false, 'success');
  } else {
    showToast('Incorrect \u2014 answer was ' + correctAnswer, false, 'error');
  }

  // Show between state briefly
  setTimeout(() => {
    showBetween(isCorrect, correctAnswer, explanation);
  }, 1500);
}

function showBetween(isCorrect, correctAnswer, explanation) {
  showingBetween = true;
  showState('betweenState');

  document.getElementById('betweenTitle').textContent = isCorrect ? 'Correct!' : 'Incorrect';
  document.getElementById('betweenTitle').style.color = isCorrect ? 'var(--correct)' : 'var(--wrong)';
  document.getElementById('betweenAnswer').textContent = 'Answer: ' + correctAnswer;
  document.getElementById('betweenExplanation').textContent = explanation || '';
  document.getElementById('betweenExplanation').style.display = explanation ? '' : 'none';

  if (currentState?.players) renderScoreBar(currentState.players, 'betweenScoreBar');

  // Auto-advance handled by next poll bringing new question
  betweenTimeout = setTimeout(() => {
    showingBetween = false;
    if (currentState?.status === 'playing') {
      showState('playingState');
    }
  }, 4000);
}

function disableOptions() {
  document.querySelectorAll('.option-card').forEach(c => c.classList.add('disabled'));
}

// ── FINISHED RENDERER ───────────────────────────────────────
function renderFinished(data) {
  showState('finishedState');
  if (localTimer) { clearInterval(localTimer); localTimer = null; }
  if (betweenTimeout) { clearTimeout(betweenTimeout); }

  const sorted = [...data.players].sort((a, b) => b.score - a.score);
  const myPlayer = sorted.find(p => p.user_id === USER_ID);
  const myRank = sorted.findIndex(p => p.user_id === USER_ID) + 1;
  const isWinner = myRank === 1 && sorted.length > 1;

  // Icon & title
  const icon = document.getElementById('finishedIcon');
  const title = document.getElementById('finishedTitle');
  const sub = document.getElementById('finishedSubtitle');

  if (isWinner) {
    icon.textContent = '\uD83C\uDFC6';
    title.textContent = 'Victory!';
    sub.textContent = 'You crushed it! #1 in ' + data.topic_label;
    launchConfetti();
  } else if (myRank <= 3) {
    icon.textContent = '\uD83C\uDF1F';
    title.textContent = 'Great Job!';
    sub.textContent = 'You placed #' + myRank + ' out of ' + sorted.length + ' players';
  } else {
    icon.textContent = '\uD83D\uDCAA';
    title.textContent = 'Battle Complete!';
    sub.textContent = 'You placed #' + myRank + ' out of ' + sorted.length + ' players';
  }

  // XP
  if (myPlayer) {
    const xpEarned = 15 + (myPlayer.correct || 0) * 10 + (isWinner ? 50 : 0);
    document.getElementById('xpEarned').style.display = '';
    document.getElementById('xpAmount').textContent = '+' + xpEarned + ' XP earned';
  }

  // Leaderboard
  const lb = document.getElementById('finalLeaderboard');
  lb.innerHTML = sorted.map((p, i) => {
    const rank = i + 1;
    const isMe = p.user_id === USER_ID;
    const rankClass = rank === 1 ? 'gold' : rank === 2 ? 'silver' : rank === 3 ? 'bronze' : '';
    const badgeClass = rank <= 3 ? 'r' + rank : 'rn';
    const medals = ['\uD83E\uDD47', '\uD83E\uDD48', '\uD83E\uDD49'];
    const accuracy = data.question_count > 0 ? Math.round((p.correct / data.question_count) * 100) : 0;
    return `<div class="final-rank-item ${rankClass} ${isMe ? 'me' : ''}">
      <div class="rank-badge ${badgeClass}">${rank <= 3 ? medals[rank - 1] : '#' + rank}</div>
      <div class="rank-info">
        <div class="name">${esc(p.name)}${isMe ? ' (You)' : ''}</div>
        <div class="detail">${p.correct}/${data.question_count} correct \u00b7 ${accuracy}% accuracy</div>
      </div>
      <div class="rank-score">${p.score.toLocaleString()}</div>
    </div>`;
  }).join('');
}

// ── Confetti ────────────────────────────────────────────────
function launchConfetti() {
  const colors = ['#1fe290', '#17c87a', '#f5a623', '#6366f1', '#ff6b6b', '#ffb347'];
  for (let i = 0; i < 60; i++) {
    const piece = document.createElement('div');
    piece.className = 'confetti-piece';
    piece.style.left = Math.random() * 100 + 'vw';
    piece.style.background = colors[Math.floor(Math.random() * colors.length)];
    piece.style.animationDuration = (2 + Math.random() * 2) + 's';
    piece.style.animationDelay = (Math.random() * 1) + 's';
    piece.style.width = (6 + Math.random() * 8) + 'px';
    piece.style.height = (6 + Math.random() * 8) + 'px';
    document.body.appendChild(piece);
    setTimeout(() => piece.remove(), 5000);
  }
}

// ── Actions ─────────────────────────────────────────────────
async function startGame() {
  const btn = document.getElementById('startBtn');
  btn.disabled = true;
  btn.textContent = 'Starting...';
  try {
    const fd = new FormData();
    fd.append('_csrf', CSRF);
    fd.append('code', ROOM_CODE);
    const res = await fetch('/api/study-room.php?action=start', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.success) {
      showToast(data.error || 'Failed to start', true);
      btn.disabled = false;
      btn.innerHTML = 'Start Battle';
    }
    // Next poll will show playing state
  } catch (err) {
    showToast('Network error', true);
    btn.disabled = false;
    btn.innerHTML = 'Start Battle';
  }
}

function copyCode() {
  navigator.clipboard?.writeText(ROOM_CODE);
  showToast('Room code copied!');
}

function copyLink() {
  const url = window.location.origin + '/study-rooms/room.php?code=' + ROOM_CODE;
  navigator.clipboard?.writeText(url);
  showToast('Link copied!');
}

function shareWhatsApp() {
  const url = window.location.origin + '/study-rooms/room.php?code=' + ROOM_CODE;
  window.open('https://wa.me/?text=' + encodeURIComponent('Join my SAT study battle! Room code: ' + ROOM_CODE + '\n' + url), '_blank');
}

function shareTwitter() {
  const url = window.location.origin + '/study-rooms/room.php?code=' + ROOM_CODE;
  window.open('https://twitter.com/intent/tweet?text=' + encodeURIComponent('Join my SAT study battle on @AvidMock! Room: ' + ROOM_CODE) + '&url=' + encodeURIComponent(url), '_blank');
}

function shareResults() {
  const url = window.location.origin + '/share/battle-results.php?code=' + ROOM_CODE;
  if (navigator.share) {
    navigator.share({ title: 'SAT Battle Results', text: 'Check out my SAT study battle results on AvidMock!', url });
  } else {
    navigator.clipboard?.writeText(url);
    showToast('Results link copied!');
  }
}

// ── Toast ───────────────────────────────────────────────────
function showToast(msg, isError = false, type = '') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'toast show';
  if (isError || type === 'error') t.classList.add('error');
  else if (type === 'success') t.classList.add('success');
  setTimeout(() => t.classList.remove('show'), 3000);
}

// ── Helpers ─────────────────────────────────────────────────
function esc(str) {
  const d = document.createElement('div');
  d.textContent = str || '';
  return d.innerHTML;
}

// ── Start ───────────────────────────────────────────────────
startPolling();

// Cleanup on leave
window.addEventListener('beforeunload', stopPolling);
</script>
</body>
</html>
