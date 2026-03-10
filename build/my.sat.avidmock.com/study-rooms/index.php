<?php
/**
 * Study Rooms Hub — Create, join, and browse multiplayer quiz battles.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/StudyRoom.php';

$user = Auth::requireStudent();
$userId    = (int)$_SESSION['user_id'];
$firstName = trim($user['first_name'] ?? 'Student');

$topics    = StudyRoom::getTopics();
$stats     = StudyRoom::getUserStats($userId);
$history   = StudyRoom::getUserHistory($userId, 10);
$publicRooms = StudyRoom::getPublicRooms(10);
$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Study Rooms — AvidMock SAT</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:ital,opsz,wght@0,9..40,300..800;1,9..40,300..800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --dk:#143230;--ac:#1fe290;--ac2:#17c87a;--tx:#1a1a2e;--bg:#f7faf9;
  --card:#ffffff;--card-border:rgba(20,50,48,.06);
  --glass:rgba(255,255,255,.72);--glass-border:rgba(255,255,255,.35);
  --shadow-sm:0 1px 3px rgba(20,50,48,.06);
  --shadow-md:0 4px 16px rgba(20,50,48,.08);
  --shadow-lg:0 8px 32px rgba(20,50,48,.10);
  --shadow-glow:0 0 24px rgba(31,226,144,.18);
  --radius:16px;--radius-sm:10px;--radius-xs:6px;
  --font:'DM Sans',system-ui,sans-serif;
  --mono:'DM Mono','Fira Code',monospace;
  --transition:cubic-bezier(.4,0,.2,1);
}
html{font-size:16px;scroll-behavior:smooth;-webkit-font-smoothing:antialiased}
body{font-family:var(--font);color:var(--tx);background:var(--bg);min-height:100vh;line-height:1.55}
a{color:var(--ac2);text-decoration:none;transition:color .2s var(--transition)}
a:hover{color:var(--dk)}

.main-content{margin-left:260px;margin-top:56px;padding:32px 32px 80px;min-height:calc(100vh - 56px)}
.wrap{max-width:1200px;margin:0 auto;padding:0}

/* ── Hero ── */
.hero{
  position:relative;overflow:hidden;
  background:linear-gradient(135deg,var(--dk) 0%,#1a4a46 50%,#1b5c52 100%);
  border-radius:var(--radius);padding:48px 40px;margin-bottom:32px;color:#fff;
}
.hero::before{
  content:'';position:absolute;top:-60%;right:-15%;width:500px;height:500px;border-radius:50%;
  background:radial-gradient(circle,rgba(31,226,144,.15) 0%,transparent 70%);pointer-events:none;
}
.hero-content{position:relative;z-index:1}
.hero-badge{
  display:inline-flex;align-items:center;gap:6px;
  background:rgba(31,226,144,.15);border:1px solid rgba(31,226,144,.3);
  border-radius:20px;padding:6px 14px;font-size:.8rem;font-weight:600;
  color:var(--ac);margin-bottom:16px;letter-spacing:.03em;text-transform:uppercase;
}
.hero h1{font-size:clamp(1.6rem,4vw,2.4rem);font-weight:700;margin-bottom:8px;letter-spacing:-.02em}
.hero h1 span{color:var(--ac)}
.hero p{font-size:1.05rem;color:rgba(255,255,255,.7);max-width:540px;margin-bottom:0}

/* ── Stats Strip ── */
.stats-strip{
  display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:16px;margin-bottom:32px;
}
.stat-card{
  background:var(--card);border:1px solid var(--card-border);border-radius:var(--radius-sm);
  padding:20px;text-align:center;transition:transform .2s var(--transition),box-shadow .2s var(--transition);
}
.stat-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-md)}
.stat-num{font-size:1.8rem;font-weight:700;color:var(--dk);font-family:var(--mono)}
.stat-label{font-size:.82rem;color:#666;margin-top:4px}

/* ── Action Cards Grid ── */
.action-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:40px}
@media(max-width:640px){.action-grid{grid-template-columns:1fr}}

.action-card{
  background:var(--card);border:1px solid var(--card-border);border-radius:var(--radius);
  padding:32px;transition:transform .2s var(--transition),box-shadow .2s var(--transition);
}
.action-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-lg)}
.action-card h2{font-size:1.2rem;font-weight:700;margin-bottom:4px;color:var(--dk)}
.action-card p{font-size:.9rem;color:#666;margin-bottom:20px}
.action-icon{
  width:56px;height:56px;border-radius:14px;display:flex;align-items:center;justify-content:center;
  margin-bottom:16px;font-size:1.5rem;
}
.action-icon.create{background:rgba(31,226,144,.1);color:var(--ac2)}
.action-icon.join{background:rgba(99,102,241,.1);color:#6366f1}

/* ── Form elements ── */
.form-group{margin-bottom:16px}
.form-group label{display:block;font-size:.82rem;font-weight:600;color:var(--dk);margin-bottom:6px}
.form-group select,.form-group input{
  width:100%;padding:10px 14px;border:1.5px solid rgba(20,50,48,.12);border-radius:var(--radius-xs);
  font-family:var(--font);font-size:.9rem;background:#fff;transition:border-color .2s;
  color:var(--tx);
}
.form-group select:focus,.form-group input:focus{
  outline:none;border-color:var(--ac);box-shadow:0 0 0 3px rgba(31,226,144,.12);
}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}

.btn{
  display:inline-flex;align-items:center;justify-content:center;gap:8px;
  padding:12px 24px;border-radius:var(--radius-xs);font-size:.9rem;font-weight:600;
  border:none;cursor:pointer;font-family:var(--font);transition:all .25s var(--transition);
  width:100%;
}
.btn-primary{
  background:var(--ac);color:var(--dk);box-shadow:0 4px 16px rgba(31,226,144,.3);
}
.btn-primary:hover{background:#2dffa0;transform:translateY(-1px);box-shadow:0 6px 24px rgba(31,226,144,.4)}
.btn-secondary{
  background:rgba(99,102,241,.08);color:#6366f1;border:1.5px solid rgba(99,102,241,.2);
}
.btn-secondary:hover{background:rgba(99,102,241,.14);transform:translateY(-1px)}

.join-input{
  text-align:center;font-size:1.6rem;font-family:var(--mono);font-weight:700;
  letter-spacing:.3em;text-transform:uppercase;padding:14px !important;
}
.join-input::placeholder{letter-spacing:.1em;font-size:1rem}

/* ── Checkbox ── */
.check-row{display:flex;align-items:center;gap:8px;margin-bottom:16px}
.check-row input{width:18px;height:18px;accent-color:var(--ac)}
.check-row label{font-size:.85rem;color:#444}

/* ── Section headers ── */
.section-header{
  display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;
}
.section-header h2{font-size:1.2rem;font-weight:700;color:var(--dk)}
.section-header .count{
  font-size:.78rem;background:rgba(31,226,144,.1);color:var(--ac2);
  padding:4px 10px;border-radius:12px;font-weight:600;
}

/* ── Active Rooms List ── */
.room-list{display:flex;flex-direction:column;gap:12px;margin-bottom:40px}
.room-item{
  display:flex;align-items:center;gap:16px;
  background:var(--card);border:1px solid var(--card-border);border-radius:var(--radius-sm);
  padding:16px 20px;transition:all .2s var(--transition);cursor:pointer;
}
.room-item:hover{border-color:var(--ac);box-shadow:var(--shadow-md);transform:translateY(-1px)}
.room-topic{
  font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;
  color:var(--ac2);background:rgba(31,226,144,.08);padding:3px 8px;border-radius:4px;
}
.room-meta{flex:1}
.room-meta h3{font-size:.95rem;font-weight:600;color:var(--dk);margin-bottom:2px}
.room-meta p{font-size:.8rem;color:#888}
.room-players{
  display:flex;align-items:center;gap:6px;font-size:.82rem;color:#555;font-weight:500;
}
.room-players svg{width:16px;height:16px;opacity:.6}
.room-join-btn{
  padding:8px 18px;background:var(--ac);color:var(--dk);border:none;border-radius:var(--radius-xs);
  font-weight:600;font-size:.82rem;cursor:pointer;font-family:var(--font);
  transition:all .2s var(--transition);white-space:nowrap;
}
.room-join-btn:hover{background:#2dffa0;transform:scale(1.03)}

/* ── History Table ── */
.history-table{width:100%;border-collapse:collapse;margin-bottom:40px}
.history-table th{
  text-align:left;font-size:.75rem;font-weight:600;color:#888;text-transform:uppercase;
  letter-spacing:.04em;padding:8px 12px;border-bottom:1.5px solid rgba(20,50,48,.08);
}
.history-table td{
  padding:12px;font-size:.88rem;border-bottom:1px solid rgba(20,50,48,.04);
}
.history-table tr:hover td{background:rgba(31,226,144,.02)}
.badge-win{
  display:inline-flex;align-items:center;gap:4px;
  background:rgba(31,226,144,.1);color:var(--ac2);font-size:.75rem;font-weight:600;
  padding:3px 8px;border-radius:4px;
}
.badge-loss{
  display:inline-flex;align-items:center;gap:4px;
  background:rgba(255,107,107,.08);color:#ff6b6b;font-size:.75rem;font-weight:600;
  padding:3px 8px;border-radius:4px;
}
.rank-1{color:#f5a623;font-weight:700}

/* ── Empty state ── */
.empty-state{
  text-align:center;padding:40px 20px;color:#999;
}
.empty-state svg{width:48px;height:48px;opacity:.4;margin-bottom:12px}
.empty-state p{font-size:.9rem}

/* ── Reveal animation ── */
.reveal{opacity:0;transform:translateY(20px);transition:opacity .5s var(--transition),transform .5s var(--transition)}
.reveal.visible{opacity:1;transform:translateY(0)}

@media(max-width:768px){
  .main-content{margin-left:0;padding:20px 16px 72px}
}
/* ── Responsive ── */
@media(max-width:640px){
  .wrap{}
  .hero{padding:32px 24px}
  .hero h1{font-size:1.4rem}
  .form-row{grid-template-columns:1fr}
  .stats-strip{grid-template-columns:repeat(2,1fr)}
  .room-item{flex-wrap:wrap}
  .history-table{font-size:.8rem}
  .history-table th,.history-table td{padding:8px 6px}
}

/* ── Toast ── */
.toast{
  position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(100px);
  background:var(--dk);color:#fff;padding:14px 24px;border-radius:var(--radius-sm);
  font-size:.88rem;font-weight:500;box-shadow:var(--shadow-lg);z-index:999;
  transition:transform .4s var(--transition);display:flex;align-items:center;gap:10px;
}
.toast.show{transform:translateX(-50%) translateY(0)}
.toast.error{background:#ff4757}
</style>
</head>
<body>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/sidebar.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/topbar.php';
?>

<main class="main-content">
<div class="wrap">

  <!-- Hero -->
  <div class="hero reveal">
    <div class="hero-content">
      <div class="hero-badge">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
        Multiplayer
      </div>
      <h1>Study Rooms <span>&#8212;</span> Learn Together, <span>Score Higher</span></h1>
      <p>Challenge your friends to real-time SAT quiz battles. Compete, learn, and level up together.</p>
    </div>
  </div>

  <!-- Stats Strip -->
  <div class="stats-strip reveal">
    <div class="stat-card">
      <div class="stat-num"><?= $stats['total_battles'] ?></div>
      <div class="stat-label">Total Battles</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= $stats['win_rate'] ?>%</div>
      <div class="stat-label">Win Rate</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= $stats['wins'] ?></div>
      <div class="stat-label">Victories</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= $stats['best_streak'] ?></div>
      <div class="stat-label">Best Streak</div>
    </div>
  </div>

  <!-- Create / Join Grid -->
  <div class="action-grid reveal">

    <!-- Create Room -->
    <div class="action-card">
      <div class="action-icon create">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
      </div>
      <h2>Create Room</h2>
      <p>Set up a new battle room and invite friends</p>

      <form id="createForm">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

        <div class="form-group">
          <label>Topic</label>
          <select name="topic">
            <?php foreach ($topics as $key => $label): ?>
            <option value="<?= e($key) ?>"><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Questions</label>
            <select name="question_count">
              <option value="5">5 Questions</option>
              <option value="10" selected>10 Questions</option>
              <option value="15">15 Questions</option>
              <option value="20">20 Questions</option>
            </select>
          </div>
          <div class="form-group">
            <label>Time per Q</label>
            <select name="time_per_question">
              <option value="15">15 seconds</option>
              <option value="30" selected>30 seconds</option>
              <option value="45">45 seconds</option>
              <option value="60">60 seconds</option>
              <option value="90">90 seconds</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label>Max Players</label>
          <select name="max_players">
            <option value="2">2 Players</option>
            <option value="4">4 Players</option>
            <option value="6">6 Players</option>
            <option value="8">8 Players</option>
            <option value="10" selected>10 Players</option>
          </select>
        </div>

        <div class="check-row">
          <input type="checkbox" name="is_public" id="is_public">
          <label for="is_public">Make room public (anyone can join)</label>
        </div>

        <button type="submit" class="btn btn-primary">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
          Create Battle Room
        </button>
      </form>
    </div>

    <!-- Join Room -->
    <div class="action-card">
      <div class="action-icon join">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
      </div>
      <h2>Join Room</h2>
      <p>Enter the 6-character code to join a battle</p>

      <form id="joinForm" style="margin-top:auto">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

        <div class="form-group" style="margin-bottom:20px;margin-top:24px">
          <label>Room Code</label>
          <input type="text" name="code" class="join-input" maxlength="6" placeholder="ABCDEF" autocomplete="off" spellcheck="false">
        </div>

        <button type="submit" class="btn btn-secondary" id="joinBtn" disabled>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
          Join Battle
        </button>
      </form>

      <div style="text-align:center;margin-top:24px;padding-top:20px;border-top:1px solid rgba(20,50,48,.06)">
        <p style="font-size:.82rem;color:#888;margin-bottom:8px">Or share this link to invite friends:</p>
        <div style="font-size:.78rem;color:var(--ac2);font-family:var(--mono)">my.sat.avidmock.com/study-rooms/room.php?code=...</div>
      </div>
    </div>
  </div>

  <!-- Active Public Rooms -->
  <?php if (!empty($publicRooms)): ?>
  <div class="reveal">
    <div class="section-header">
      <h2>Active Rooms</h2>
      <span class="count"><?= count($publicRooms) ?> open</span>
    </div>
    <div class="room-list">
      <?php foreach ($publicRooms as $room): ?>
      <div class="room-item" onclick="quickJoin('<?= e($room['code']) ?>')">
        <div>
          <span class="room-topic"><?= e($room['topic_label']) ?></span>
        </div>
        <div class="room-meta">
          <h3>Room <?= e($room['code']) ?> &mdash; by <?= e($room['host_name']) ?></h3>
          <p><?= $room['question_count'] ?> questions &middot; <?= $room['time_per_question'] ?>s timer</p>
        </div>
        <div class="room-players">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
          <?= $room['player_count'] ?>/<?= $room['max_players'] ?>
        </div>
        <button class="room-join-btn" onclick="event.stopPropagation();quickJoin('<?= e($room['code']) ?>')">Join</button>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Past Battles -->
  <div class="reveal">
    <div class="section-header">
      <h2>Your Past Battles</h2>
      <?php if (!empty($history)): ?>
      <span class="count"><?= $stats['total_battles'] ?> total</span>
      <?php endif; ?>
    </div>

    <?php if (empty($history)): ?>
    <div class="empty-state">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
      <p>No battles yet. Create or join a room to get started!</p>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto">
    <table class="history-table">
      <thead>
        <tr>
          <th>Room</th>
          <th>Topic</th>
          <th>Result</th>
          <th>Score</th>
          <th>Accuracy</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($history as $h): ?>
        <tr>
          <td>
            <a href="room.php?code=<?= e($h['code']) ?>" style="font-family:var(--mono);font-weight:600;font-size:.85rem"><?= e($h['code']) ?></a>
          </td>
          <td style="font-size:.82rem"><?= e($h['topic_label']) ?></td>
          <td>
            <?php if ($h['status'] === 'finished'): ?>
              <?php if ($h['won']): ?>
                <span class="badge-win"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:2px"><path d="M6 9H4.5a2.5 2.5 0 010-5H6"/><path d="M18 9h1.5a2.5 2.5 0 000-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0012 0V2z"/></svg> 1st</span>
              <?php elseif ($h['rank'] > 0): ?>
                <span class="badge-loss">#<?= $h['rank'] ?> of <?= $h['total_players'] ?></span>
              <?php else: ?>
                <span class="badge-loss">--</span>
              <?php endif; ?>
            <?php elseif ($h['status'] === 'playing'): ?>
              <span style="color:#f5a623;font-size:.82rem;font-weight:600">In Progress</span>
            <?php else: ?>
              <span style="color:#999;font-size:.82rem"><?= ucfirst($h['status']) ?></span>
            <?php endif; ?>
          </td>
          <td style="font-family:var(--mono);font-weight:600"><?= number_format($h['score']) ?></td>
          <td>
            <?php $acc = $h['question_count'] > 0 ? round(($h['correct'] / $h['question_count']) * 100) : 0; ?>
            <?= $acc ?>%
          </td>
          <td style="font-size:.82rem;color:#888"><?= $h['created_at'] ? date('M j', strtotime($h['created_at'])) : '--' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>
  </div>

</div>
</main>

<!-- Toast notification -->
<div class="toast" id="toast"></div>

<script>
const CSRF = '<?= e($csrfToken) ?>';

// Reveal animations
const revealEls = document.querySelectorAll('.reveal');
const revealObserver = new IntersectionObserver((entries) => {
  entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); revealObserver.unobserve(e.target); }});
}, { threshold: 0.1 });
revealEls.forEach(el => revealObserver.observe(el));

// Toast
function showToast(msg, isError = false) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'toast show' + (isError ? ' error' : '');
  setTimeout(() => t.classList.remove('show'), 3500);
}

// Join input validation
const joinInput = document.querySelector('.join-input');
const joinBtn = document.getElementById('joinBtn');
joinInput.addEventListener('input', function() {
  this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
  joinBtn.disabled = this.value.length !== 6;
});

// Create room
document.getElementById('createForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = this.querySelector('button');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Creating...';

  try {
    const fd = new FormData(this);
    fd.set('is_public', this.querySelector('[name=is_public]').checked ? '1' : '');
    const res = await fetch('/api/study-room.php?action=create', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      window.location.href = 'room.php?code=' + data.code;
    } else {
      showToast(data.error || 'Failed to create room', true);
      btn.disabled = false;
      btn.innerHTML = 'Create Battle Room';
    }
  } catch (err) {
    showToast('Network error', true);
    btn.disabled = false;
    btn.innerHTML = 'Create Battle Room';
  }
});

// Join room
document.getElementById('joinForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const code = this.querySelector('[name=code]').value.trim();
  if (code.length !== 6) return;

  const btn = this.querySelector('button');
  btn.disabled = true;
  btn.textContent = 'Joining...';

  try {
    const fd = new FormData();
    fd.append('_csrf', CSRF);
    fd.append('code', code);
    const res = await fetch('/api/study-room.php?action=join', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      window.location.href = 'room.php?code=' + data.code;
    } else {
      showToast(data.error || 'Failed to join room', true);
      btn.disabled = false;
      btn.textContent = 'Join Battle';
    }
  } catch (err) {
    showToast('Network error', true);
    btn.disabled = false;
    btn.textContent = 'Join Battle';
  }
});

// Quick join from public rooms
async function quickJoin(code) {
  try {
    const fd = new FormData();
    fd.append('_csrf', CSRF);
    fd.append('code', code);
    const res = await fetch('/api/study-room.php?action=join', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      window.location.href = 'room.php?code=' + code;
    } else {
      showToast(data.error || 'Failed to join', true);
    }
  } catch (err) {
    showToast('Network error', true);
  }
}
</script>
</body>
</html>
