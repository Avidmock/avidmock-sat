<?php
/**
 * Referrals — Invite friends, earn rewards. Referral program hub.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/SocialShare.php';

$user = Auth::requireStudent();
$userId    = (int)$_SESSION['user_id'];
$firstName = trim($user['first_name'] ?? 'Student');

$stats      = SocialShare::getReferralStats($userId);
$topRefs    = SocialShare::getTopReferrers(10);
$tiers      = SocialShare::getRewardTiers();
$csrfToken  = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Refer Friends — AvidMock SAT</title>
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
}
html{font-size:16px;scroll-behavior:smooth;-webkit-font-smoothing:antialiased}
body{font-family:var(--font);color:var(--tx);background:var(--bg);min-height:100vh;line-height:1.55}
a{color:var(--ac2);text-decoration:none;transition:color .2s var(--transition)}
a:hover{color:var(--dk)}

.main-content{margin-left:260px;margin-top:56px;padding:32px 32px 80px;min-height:calc(100vh - 56px)}
.wrap{max-width:800px;margin:0 auto;padding:0}
.back{display:inline-flex;align-items:center;gap:6px;font-size:.85rem;color:var(--dk);opacity:.6;margin-bottom:24px;transition:opacity .2s}
.back:hover{opacity:1}

/* Hero */
.ref-hero{text-align:center;margin-bottom:32px;padding:40px 24px;background:linear-gradient(135deg,var(--dk),#1a4a46);border-radius:var(--radius);color:#fff}
.ref-hero h1{font-size:1.8rem;font-weight:700;margin-bottom:6px}
.ref-hero p{font-size:.95rem;opacity:.7;max-width:420px;margin:0 auto}

/* Code box */
.code-box{background:rgba(255,255,255,.1);border:2px dashed rgba(255,255,255,.25);border-radius:var(--radius-sm);padding:16px 20px;margin:20px auto 0;max-width:380px;display:flex;align-items:center;gap:12px}
.code-box .code{flex:1;font-family:var(--mono);font-size:1.4rem;font-weight:700;letter-spacing:.15em;text-align:center}
.code-box .copy-btn{padding:8px 16px;background:var(--ac);color:var(--dk);border:none;border-radius:var(--radius-xs);font-family:var(--font);font-size:.8rem;font-weight:600;cursor:pointer;transition:all .2s var(--transition);white-space:nowrap}
.code-box .copy-btn:hover{background:#fff;transform:scale(1.03)}
.code-box .copy-btn.copied{background:#fff}

/* Share URL */
.share-url{margin-top:12px;text-align:center}
.share-url input{width:100%;max-width:380px;padding:10px 14px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:var(--radius-xs);color:#fff;font-family:var(--mono);font-size:.75rem;text-align:center}

/* Stats row */
.stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:32px}
.stat-card{background:var(--card);border:1px solid var(--card-border);border-radius:var(--radius-sm);padding:20px;text-align:center}
.stat-card .stat-num{font-family:var(--mono);font-size:2rem;font-weight:700;color:var(--dk)}
.stat-card .stat-label{font-size:.8rem;color:var(--tx);opacity:.5;margin-top:4px}

/* Reward tiers */
.tiers-section h2{font-size:1.1rem;font-weight:600;color:var(--dk);margin-bottom:16px}
.tier-list{display:flex;flex-direction:column;gap:8px;margin-bottom:32px}
.tier-row{display:grid;grid-template-columns:50px 1fr auto;align-items:center;gap:14px;padding:14px 18px;background:var(--card);border:1px solid var(--card-border);border-radius:var(--radius-sm);transition:all .2s var(--transition)}
.tier-row.earned{border-color:var(--ac);background:rgba(31,226,144,.03)}
.tier-count{font-family:var(--mono);font-size:.85rem;font-weight:600;color:var(--dk);text-align:center}
.tier-reward{font-size:.9rem;font-weight:500}
.tier-reward .tier-desc{font-size:.75rem;color:var(--tx);opacity:.45;display:block;margin-top:2px}
.tier-badge{padding:4px 10px;border-radius:var(--radius-xs);font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em}
.tier-badge.earned{background:rgba(31,226,144,.12);color:var(--ac2)}
.tier-badge.locked{background:rgba(20,50,48,.04);color:var(--tx);opacity:.35}

/* Share buttons */
.share-btns{display:flex;gap:8px;justify-content:center;margin-top:16px}
.share-btn{padding:10px 20px;border:none;border-radius:var(--radius-xs);font-family:var(--font);font-size:.8rem;font-weight:600;cursor:pointer;transition:all .2s var(--transition);text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.share-btn:hover{transform:translateY(-1px);box-shadow:var(--shadow-md)}
.share-btn.whatsapp{background:#25d366;color:#fff}
.share-btn.twitter{background:#1da1f2;color:#fff}
.share-btn.link{background:var(--dk);color:#fff}

/* Recent referrals */
.recent-section{margin-top:32px}
.recent-section h2{font-size:1.1rem;font-weight:600;color:var(--dk);margin-bottom:16px}
.recent-list{display:flex;flex-direction:column;gap:6px}
.recent-row{display:flex;align-items:center;gap:12px;padding:12px 16px;background:var(--card);border:1px solid var(--card-border);border-radius:var(--radius-sm)}
.recent-avatar{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--ac),var(--ac2));display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:600;color:var(--dk)}
.recent-name{flex:1;font-size:.85rem;font-weight:500}
.recent-date{font-size:.75rem;color:var(--tx);opacity:.4;font-family:var(--mono)}

/* Top referrers */
.top-section{margin-top:32px}
.top-section h2{font-size:1.1rem;font-weight:600;color:var(--dk);margin-bottom:16px}

.empty-state{text-align:center;padding:32px;color:var(--tx);opacity:.4;font-size:.9rem}

@media(max-width:768px){
  .main-content{margin-left:0;padding:20px 16px 72px}
}
@media(max-width:600px){
  .wrap{}
  .stats-row{grid-template-columns:1fr}
  .ref-hero{padding:28px 16px}
  .ref-hero h1{font-size:1.5rem}
  .share-btns{flex-direction:column}
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
  <a href="/index.php" class="back">&larr; Dashboard</a>

  <!-- Hero with referral code -->
  <div class="ref-hero">
    <h1>Invite Friends, Earn Rewards</h1>
    <p>Share your code and both you and your friend get bonus XP. Hit milestones for Pro rewards!</p>
    <div class="code-box">
      <span class="code" id="refCode"><?= htmlspecialchars($stats['code']) ?></span>
      <button class="copy-btn" onclick="copyCode()">Copy Code</button>
    </div>
    <div class="share-url">
      <input type="text" readonly value="<?= htmlspecialchars($stats['share_url']) ?>" id="refUrl" onclick="this.select()">
    </div>
    <div class="share-btns">
      <a href="https://wa.me/?text=<?= urlencode('Join me on AvidMock SAT Prep! Use my code ' . $stats['code'] . ' for bonus XP: ' . $stats['share_url']) ?>" target="_blank" rel="noopener" class="share-btn whatsapp">WhatsApp</a>
      <a href="https://twitter.com/intent/tweet?text=<?= urlencode('Leveling up my SAT prep with @AvidMock! Use my code ' . $stats['code'] . ' for bonus XP') ?>&url=<?= urlencode($stats['share_url']) ?>" target="_blank" rel="noopener" class="share-btn twitter">Twitter</a>
      <button class="share-btn link" onclick="copyUrl()">Copy Link</button>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-num"><?= $stats['total_referred'] ?></div>
      <div class="stat-label">Friends Referred</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= $stats['active_referred'] ?></div>
      <div class="stat-label">Active This Week</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= $stats['next_reward'] ? $stats['next_reward']['remaining'] : '0' ?></div>
      <div class="stat-label"><?= $stats['next_reward'] ? 'Until ' . htmlspecialchars($stats['next_reward']['label']) : 'All Rewards Earned!' ?></div>
    </div>
  </div>

  <!-- Reward tiers -->
  <div class="tiers-section">
    <h2>Reward Milestones</h2>
    <div class="tier-list">
      <?php foreach ($stats['rewards'] as $r): ?>
      <div class="tier-row<?= $r['earned'] ? ' earned' : '' ?>">
        <div class="tier-count"><?= $r['threshold'] ?></div>
        <div class="tier-reward">
          <?= htmlspecialchars($r['label']) ?>
          <span class="tier-desc">Refer <?= $r['threshold'] ?> friend<?= $r['threshold'] > 1 ? 's' : '' ?></span>
        </div>
        <span class="tier-badge <?= $r['earned'] ? 'earned' : 'locked' ?>"><?= $r['earned'] ? 'Earned' : 'Locked' ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Recent referrals -->
  <?php if (!empty($stats['recent_referrals'])): ?>
  <div class="recent-section">
    <h2>Recent Referrals</h2>
    <div class="recent-list">
      <?php foreach ($stats['recent_referrals'] as $ref): ?>
      <div class="recent-row">
        <div class="recent-avatar"><?= strtoupper(substr($ref['first_name'] ?? '?', 0, 1)) ?></div>
        <div class="recent-name"><?= htmlspecialchars($ref['first_name'] ?? 'Student') ?></div>
        <div class="recent-date"><?= date('M j', strtotime($ref['created_at'])) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Top referrers -->
  <?php if (!empty($topRefs)): ?>
  <div class="top-section">
    <h2>Top Referrers</h2>
    <div class="lb-list" style="display:flex;flex-direction:column;gap:6px">
      <?php foreach ($topRefs as $i => $r): ?>
      <div class="recent-row">
        <div style="font-family:var(--mono);font-size:.85rem;font-weight:600;color:var(--dk);width:28px;text-align:center"><?= $i + 1 ?></div>
        <div class="recent-avatar"><?= strtoupper(substr($r['first_name'] ?? '?', 0, 1)) ?></div>
        <div class="recent-name"><?= htmlspecialchars($r['first_name'] ?? 'Student') ?></div>
        <div class="recent-date" style="font-weight:600;color:var(--ac2)"><?= (int)$r['uses'] ?> refs</div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
</main>

<script>
function copyCode() {
  navigator.clipboard.writeText(document.getElementById('refCode').textContent.trim());
  const btn = document.querySelector('.copy-btn');
  btn.textContent = 'Copied!';
  btn.classList.add('copied');
  setTimeout(() => { btn.textContent = 'Copy Code'; btn.classList.remove('copied'); }, 2000);
}
function copyUrl() {
  navigator.clipboard.writeText(document.getElementById('refUrl').value);
  const btn = document.querySelector('.share-btn.link');
  btn.textContent = 'Copied!';
  setTimeout(() => { btn.textContent = 'Copy Link'; }, 2000);
}
</script>
</body>
</html>
