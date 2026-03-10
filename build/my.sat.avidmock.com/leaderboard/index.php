<?php
/**
 * Leaderboard — Weekly + All-Time XP rankings with league tiers.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Leaderboard.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/StudyStreak.php';

$user = Auth::requireStudent();
$userId    = (int)$_SESSION['user_id'];
$firstName = trim($user['first_name'] ?? 'Student');

$rankData   = Leaderboard::getUserRank($userId);
$totalXp    = (int)($rankData['xp'] ?? 0);
$userRank   = (int)($rankData['rank'] ?? 0);
$percentile = (int)($rankData['percentile'] ?? 0);
$totalUsers = (int)($rankData['total'] ?? 0);

$weeklyTop  = Leaderboard::getWeeklyTop(20);
$allTimeTop = Leaderboard::getTop(20);
$neighbors  = Leaderboard::getNeighbors($userId);

$currentStreak = StudyStreak::getCurrent($userId);

// League helpers
$leagues = [
    'bronze'  => ['min' => 0,    'label' => 'Bronze',  'color' => '#cd7f32'],
    'silver'  => ['min' => 300,  'label' => 'Silver',  'color' => '#a8a8b8'],
    'gold'    => ['min' => 750,  'label' => 'Gold',    'color' => '#f5a623'],
    'diamond' => ['min' => 1500, 'label' => 'Diamond', 'color' => '#1fe290'],
];
function getUserLeague(int $xp, array $leagues): string {
    $league = 'bronze';
    foreach (array_reverse($leagues, true) as $key => $l) {
        if ($xp >= $l['min']) { $league = $key; break; }
    }
    return $league;
}
$myLeague = getUserLeague($totalXp, $leagues);
$leagueInfo = $leagues[$myLeague];

// Next league
$nextLeague = null;
$xpToNext = 0;
$found = false;
foreach ($leagues as $key => $l) {
    if ($found) { $nextLeague = $l; $xpToNext = $l['min'] - $totalXp; break; }
    if ($key === $myLeague) $found = true;
}

$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Leaderboard — AvidMock SAT</title>
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
.wrap{max-width:900px;margin:0 auto;padding:0}
.back{display:inline-flex;align-items:center;gap:6px;font-size:.85rem;color:var(--dk);opacity:.6;margin-bottom:24px;transition:opacity .2s}
.back:hover{opacity:1;color:var(--dk)}

/* Header */
.lb-header{text-align:center;margin-bottom:32px}
.lb-header h1{font-size:2rem;font-weight:700;color:var(--dk);margin-bottom:4px}
.lb-header p{font-size:.95rem;color:var(--tx);opacity:.6}

/* Your rank card */
.rank-card{background:linear-gradient(135deg,var(--dk),#1a4a46);border-radius:var(--radius);padding:28px 32px;color:#fff;margin-bottom:32px;display:grid;grid-template-columns:1fr auto;gap:24px;align-items:center}
.rank-card .rank-main h2{font-size:.85rem;text-transform:uppercase;letter-spacing:.08em;opacity:.6;margin-bottom:4px}
.rank-card .rank-number{font-family:var(--mono);font-size:2.5rem;font-weight:700;line-height:1}
.rank-card .rank-meta{display:flex;gap:20px;margin-top:12px}
.rank-card .rank-meta span{font-size:.8rem;opacity:.7}
.rank-card .rank-meta strong{display:block;font-size:1rem;opacity:1}
.rank-card .league-badge{display:flex;flex-direction:column;align-items:center;gap:6px}
.league-icon{width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:700;border:3px solid rgba(255,255,255,.2)}
.league-label{font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;opacity:.7}

/* Progress to next league */
.league-progress{background:var(--card);border:1px solid var(--card-border);border-radius:var(--radius-sm);padding:16px 20px;margin-bottom:32px;display:flex;align-items:center;gap:16px}
.league-progress .bar-wrap{flex:1}
.league-progress .bar-label{font-size:.8rem;color:var(--tx);opacity:.6;margin-bottom:6px}
.league-progress .bar{height:8px;background:rgba(20,50,48,.06);border-radius:4px;overflow:hidden}
.league-progress .bar-fill{height:100%;border-radius:4px;transition:width .6s var(--transition)}
.league-progress .bar-xp{font-family:var(--mono);font-size:.75rem;color:var(--tx);opacity:.5;margin-top:4px}

/* Tabs */
.tabs{display:flex;gap:4px;margin-bottom:24px;background:rgba(20,50,48,.04);border-radius:var(--radius-sm);padding:4px}
.tab-btn{flex:1;padding:10px 16px;border:none;background:none;font-family:var(--font);font-size:.85rem;font-weight:500;color:var(--tx);opacity:.5;cursor:pointer;border-radius:var(--radius-xs);transition:all .2s var(--transition)}
.tab-btn.active{background:var(--card);opacity:1;box-shadow:var(--shadow-sm);color:var(--dk)}
.tab-panel{display:none}
.tab-panel.active{display:block}

/* Leaderboard list */
.lb-list{display:flex;flex-direction:column;gap:6px}
.lb-row{display:grid;grid-template-columns:44px 40px 1fr auto;align-items:center;gap:12px;padding:12px 16px;background:var(--card);border:1px solid var(--card-border);border-radius:var(--radius-sm);transition:all .2s var(--transition)}
.lb-row:hover{box-shadow:var(--shadow-sm);transform:translateY(-1px)}
.lb-row.is-you{border-color:var(--ac);background:rgba(31,226,144,.04)}
.lb-row.top-3{border-left:3px solid}
.lb-row.rank-1{border-left-color:#f5a623}
.lb-row.rank-2{border-left-color:#a8a8b8}
.lb-row.rank-3{border-left-color:#cd7f32}
.lb-rank{font-family:var(--mono);font-size:.9rem;font-weight:600;text-align:center;color:var(--dk)}
.lb-rank.gold{color:#f5a623;font-size:1.1rem}
.lb-rank.silver{color:#a8a8b8;font-size:1.05rem}
.lb-rank.bronze{color:#cd7f32;font-size:1rem}
.lb-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--ac),var(--ac2));display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:600;color:var(--dk);overflow:hidden}
.lb-avatar img{width:100%;height:100%;object-fit:cover}
.lb-name{font-size:.9rem;font-weight:500}
.lb-name .streak{font-size:.75rem;color:var(--tx);opacity:.45;margin-left:6px}
.lb-xp{font-family:var(--mono);font-size:.85rem;font-weight:600;color:var(--ac2)}

/* Neighbors section */
.neighbors{margin-top:32px}
.neighbors h3{font-size:1rem;font-weight:600;color:var(--dk);margin-bottom:12px}
.nb-divider{height:2px;background:linear-gradient(90deg,transparent,var(--ac),transparent);margin:4px 0;border-radius:1px}

/* Empty state */
.empty-state{text-align:center;padding:48px 24px;color:var(--tx);opacity:.5}
.empty-state svg{width:48px;height:48px;margin-bottom:12px;opacity:.3}
.empty-state p{font-size:.9rem}

@media(max-width:768px){
  .main-content{margin-left:0;padding:20px 16px 72px}
}
@media(max-width:600px){
  .wrap{}
  .rank-card{grid-template-columns:1fr;text-align:center;padding:24px 20px}
  .rank-card .rank-meta{justify-content:center}
  .rank-card .league-badge{flex-direction:row;gap:10px}
  .lb-row{grid-template-columns:36px 32px 1fr auto;gap:8px;padding:10px 12px}
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

  <div class="lb-header">
    <h1>Leaderboard</h1>
    <p>Compete with other students and climb the ranks</p>
  </div>

  <!-- Your rank card -->
  <div class="rank-card">
    <div class="rank-main">
      <h2>Your Rank</h2>
      <div class="rank-number">#<?= $userRank ?: '—' ?></div>
      <div class="rank-meta">
        <span>XP<strong><?= number_format($totalXp) ?></strong></span>
        <span>Top<strong><?= $percentile ?>%</strong></span>
        <span>Streak<strong><?= $currentStreak ?> days</strong></span>
      </div>
    </div>
    <div class="league-badge">
      <div class="league-icon" style="background:<?= $leagueInfo['color'] ?>20;border-color:<?= $leagueInfo['color'] ?>">
        <?= strtoupper(substr($leagueInfo['label'], 0, 1)) ?>
      </div>
      <span class="league-label" style="color:<?= $leagueInfo['color'] ?>"><?= $leagueInfo['label'] ?> League</span>
    </div>
  </div>

  <?php if ($nextLeague): ?>
  <div class="league-progress">
    <div class="bar-wrap">
      <?php
        $prevMin = $leagues[$myLeague]['min'];
        $range = $nextLeague['min'] - $prevMin;
        $progress = $range > 0 ? min(100, max(0, round(($totalXp - $prevMin) / $range * 100))) : 100;
      ?>
      <div class="bar-label"><?= $xpToNext ?> XP to <?= $nextLeague['label'] ?> League</div>
      <div class="bar"><div class="bar-fill" style="width:<?= $progress ?>%;background:<?= $nextLeague['color'] ?>"></div></div>
      <div class="bar-xp"><?= number_format($totalXp) ?> / <?= number_format($nextLeague['min']) ?> XP</div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Tabs -->
  <div class="tabs">
    <button class="tab-btn active" data-tab="weekly">This Week</button>
    <button class="tab-btn" data-tab="alltime">All Time</button>
    <button class="tab-btn" data-tab="nearby">Near You</button>
  </div>

  <!-- Weekly -->
  <div class="tab-panel active" id="tab-weekly">
    <?php if (empty($weeklyTop)): ?>
      <div class="empty-state"><p>No activity this week yet. Start studying to earn XP!</p></div>
    <?php else: ?>
      <div class="lb-list">
        <?php foreach ($weeklyTop as $i => $p):
          $rank = $i + 1;
          $isYou = (int)$p['user_id'] === $userId;
          $initials = strtoupper(substr($p['name'] ?? '?', 0, 1));
          $rankClass = $rank <= 3 ? ' top-3 rank-' . $rank : '';
          $youClass = $isYou ? ' is-you' : '';
          $rankColorClass = match($rank) { 1 => ' gold', 2 => ' silver', 3 => ' bronze', default => '' };
        ?>
        <div class="lb-row<?= $rankClass . $youClass ?>">
          <div class="lb-rank<?= $rankColorClass ?>"><?= $rank ?></div>
          <div class="lb-avatar">
            <?php if (!empty($p['avatar_url'])): ?>
              <img src="<?= htmlspecialchars($p['avatar_url']) ?>" alt="">
            <?php else: ?>
              <?= $initials ?>
            <?php endif; ?>
          </div>
          <div class="lb-name">
            <?= htmlspecialchars($p['name'] ?: 'Student') ?>
            <?php if ($isYou): ?><span class="streak">(You)</span><?php endif; ?>
          </div>
          <div class="lb-xp"><?= number_format((int)($p['weekly_xp'] ?? 0)) ?> XP</div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- All Time -->
  <div class="tab-panel" id="tab-alltime">
    <?php if (empty($allTimeTop)): ?>
      <div class="empty-state"><p>No students on the leaderboard yet.</p></div>
    <?php else: ?>
      <div class="lb-list">
        <?php foreach ($allTimeTop as $i => $p):
          $rank = $i + 1;
          $isYou = (int)$p['user_id'] === $userId;
          $initials = strtoupper(substr($p['name'] ?? '?', 0, 1));
          $rankClass = $rank <= 3 ? ' top-3 rank-' . $rank : '';
          $youClass = $isYou ? ' is-you' : '';
          $rankColorClass = match($rank) { 1 => ' gold', 2 => ' silver', 3 => ' bronze', default => '' };
        ?>
        <div class="lb-row<?= $rankClass . $youClass ?>">
          <div class="lb-rank<?= $rankColorClass ?>"><?= $rank ?></div>
          <div class="lb-avatar">
            <?php if (!empty($p['avatar_url'])): ?>
              <img src="<?= htmlspecialchars($p['avatar_url']) ?>" alt="">
            <?php else: ?>
              <?= $initials ?>
            <?php endif; ?>
          </div>
          <div class="lb-name">
            <?= htmlspecialchars($p['name'] ?: 'Student') ?>
            <?php if ($isYou): ?><span class="streak">(You)</span><?php endif; ?>
            <?php if (!empty($p['current_streak']) && (int)$p['current_streak'] > 0): ?>
              <span class="streak"><?= (int)$p['current_streak'] ?>d streak</span>
            <?php endif; ?>
          </div>
          <div class="lb-xp"><?= number_format((int)($p['total_xp'] ?? 0)) ?> XP</div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Near You -->
  <div class="tab-panel" id="tab-nearby">
    <div class="neighbors">
      <h3>Players near your rank</h3>
      <div class="lb-list">
        <?php foreach ($neighbors['above'] as $p):
          $initials = strtoupper(substr($p['name'] ?? '?', 0, 1));
        ?>
        <div class="lb-row">
          <div class="lb-rank">&uarr;</div>
          <div class="lb-avatar">
            <?php if (!empty($p['avatar_url'])): ?><img src="<?= htmlspecialchars($p['avatar_url']) ?>" alt=""><?php else: ?><?= $initials ?><?php endif; ?>
          </div>
          <div class="lb-name"><?= htmlspecialchars($p['name'] ?: 'Student') ?></div>
          <div class="lb-xp"><?= number_format((int)($p['total_xp'] ?? 0)) ?> XP</div>
        </div>
        <?php endforeach; ?>

        <div class="nb-divider"></div>

        <div class="lb-row is-you">
          <div class="lb-rank">#<?= $userRank ?></div>
          <div class="lb-avatar"><?= strtoupper(substr($firstName, 0, 1)) ?></div>
          <div class="lb-name"><?= htmlspecialchars($firstName) ?> <span class="streak">(You)</span></div>
          <div class="lb-xp"><?= number_format($totalXp) ?> XP</div>
        </div>

        <div class="nb-divider"></div>

        <?php foreach ($neighbors['below'] as $p):
          $initials = strtoupper(substr($p['name'] ?? '?', 0, 1));
        ?>
        <div class="lb-row">
          <div class="lb-rank">&darr;</div>
          <div class="lb-avatar">
            <?php if (!empty($p['avatar_url'])): ?><img src="<?= htmlspecialchars($p['avatar_url']) ?>" alt=""><?php else: ?><?= $initials ?><?php endif; ?>
          </div>
          <div class="lb-name"><?= htmlspecialchars($p['name'] ?: 'Student') ?></div>
          <div class="lb-xp"><?= number_format((int)($p['total_xp'] ?? 0)) ?> XP</div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
</main>

<script>
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
  });
});
</script>
</body>
</html>
