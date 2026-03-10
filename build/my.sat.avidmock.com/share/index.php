<?php
/**
 * Share — Public score card / achievement share page.
 * Accessible without login (for shared links).
 * URL: /share/?t=<token>
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/SocialShare.php';

$token = trim($_GET['t'] ?? '');
$shareData = null;
$scoreCard = null;
$error = null;

if (strlen($token) < 16) {
    $error = 'Invalid or expired share link.';
} else {
    $shareData = SocialShare::getShareData($token);
    if (!$shareData) {
        $error = 'This share link has expired or is no longer available.';
    } else {
        try {
            $scoreCard = SocialShare::generateScoreCard((int)$shareData['user_id']);
        } catch (\Throwable $e) {
            $error = 'Unable to load score card.';
        }
    }
}

// League colors
$leagueColors = [
    'bronze'  => ['bg' => '#cd7f32', 'gradient' => 'linear-gradient(135deg,#cd7f32,#a0622a)'],
    'silver'  => ['bg' => '#a8a8b8', 'gradient' => 'linear-gradient(135deg,#c0c0d0,#8888a0)'],
    'gold'    => ['bg' => '#f5a623', 'gradient' => 'linear-gradient(135deg,#f5a623,#d4891a)'],
    'diamond' => ['bg' => '#1fe290', 'gradient' => 'linear-gradient(135deg,#1fe290,#17c87a)'],
];
$league = $scoreCard['league'] ?? 'bronze';
$lc = $leagueColors[$league] ?? $leagueColors['bronze'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $scoreCard ? htmlspecialchars($scoreCard['first_name']) . "'s SAT Score Card" : 'Share' ?> — AvidMock SAT</title>
<?php if ($scoreCard): ?>
<meta property="og:title" content="<?= htmlspecialchars($scoreCard['first_name']) ?>'s SAT Prep Journey — AvidMock">
<meta property="og:description" content="<?= htmlspecialchars($scoreCard['first_name']) ?> is preparing for the SAT with AvidMock! <?= $scoreCard['predicted_score'] ? 'Predicted score: ' . $scoreCard['predicted_score'] : '' ?>">
<meta property="og:type" content="website">
<meta property="og:url" content="<?= htmlspecialchars(STUDENT_URL . '/share/?t=' . $token) ?>">
<meta name="twitter:card" content="summary">
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:ital,opsz,wght@0,9..40,300..800;1,9..40,300..800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --dk:#143230;--ac:#1fe290;--ac2:#17c87a;--tx:#1a1a2e;--bg:#f7faf9;
  --card:#ffffff;--card-border:rgba(20,50,48,.06);
  --shadow-lg:0 8px 32px rgba(20,50,48,.10);
  --radius:16px;--radius-sm:10px;--radius-xs:6px;
  --font:'DM Sans',system-ui,sans-serif;
  --mono:'DM Mono','Fira Code',monospace;
  --transition:cubic-bezier(.4,0,.2,1);
}
html{font-size:16px;-webkit-font-smoothing:antialiased}
body{font-family:var(--font);color:var(--tx);background:var(--bg);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}

.share-wrap{max-width:440px;width:100%}

/* Score card */
.score-card{background:var(--card);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow-lg)}
.sc-header{padding:32px 28px 20px;text-align:center;color:#fff}
.sc-avatar{width:64px;height:64px;border-radius:50%;margin:0 auto 12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:700;background:rgba(255,255,255,.2);border:3px solid rgba(255,255,255,.3)}
.sc-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.sc-name{font-size:1.2rem;font-weight:600;margin-bottom:2px}
.sc-league{font-size:.75rem;text-transform:uppercase;letter-spacing:.08em;opacity:.7}

.sc-score-ring{width:140px;height:140px;margin:20px auto;position:relative}
.sc-score-ring svg{width:100%;height:100%;transform:rotate(-90deg)}
.sc-score-ring circle{fill:none;stroke-width:8}
.sc-score-ring .ring-bg{stroke:rgba(255,255,255,.15)}
.sc-score-ring .ring-fill{stroke:#fff;stroke-linecap:round;transition:stroke-dashoffset 1s var(--transition)}
.sc-score-num{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center}
.sc-score-num .num{font-family:var(--mono);font-size:2.2rem;font-weight:700;color:#fff;line-height:1}
.sc-score-num .label{font-size:.65rem;text-transform:uppercase;letter-spacing:.08em;opacity:.6;color:#fff;margin-top:4px}

/* Stats grid */
.sc-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:1px;background:var(--card-border)}
.sc-stat{padding:16px 12px;text-align:center;background:var(--card)}
.sc-stat .val{font-family:var(--mono);font-size:1.2rem;font-weight:700;color:var(--dk)}
.sc-stat .lbl{font-size:.7rem;color:var(--tx);opacity:.45;margin-top:2px}

/* Footer */
.sc-footer{padding:16px 20px;text-align:center;background:var(--card);border-top:1px solid var(--card-border)}
.sc-footer .brand{font-size:.75rem;color:var(--tx);opacity:.4}
.sc-footer .brand strong{color:var(--ac2);opacity:1}

/* CTA */
.cta{text-align:center;margin-top:20px}
.cta a{display:inline-block;padding:12px 32px;background:var(--dk);color:#fff;border-radius:var(--radius-sm);font-weight:600;font-size:.9rem;transition:all .2s var(--transition)}
.cta a:hover{background:var(--ac2);color:var(--dk);transform:translateY(-1px)}

/* Error */
.error-card{background:var(--card);border-radius:var(--radius);padding:48px 32px;text-align:center;box-shadow:var(--shadow-lg)}
.error-card h2{font-size:1.2rem;color:var(--dk);margin-bottom:8px}
.error-card p{font-size:.9rem;color:var(--tx);opacity:.5}

@media(max-width:480px){
  body{padding:16px}
  .sc-header{padding:24px 20px 16px}
}
</style>
</head>
<body>
<div class="share-wrap">
  <?php if ($error): ?>
    <div class="error-card">
      <h2>Oops!</h2>
      <p><?= htmlspecialchars($error) ?></p>
      <div class="cta" style="margin-top:24px">
        <a href="<?= htmlspecialchars(PUBLIC_URL) ?>">Start your SAT Prep</a>
      </div>
    </div>
  <?php elseif ($scoreCard): ?>
    <?php
      $predicted = $scoreCard['predicted_score'];
      $maxScore = 1600;
      $pct = $predicted > 0 ? min(100, round(($predicted / $maxScore) * 100)) : 0;
      $circumference = 2 * M_PI * 58; // radius 58
      $dashOffset = $circumference - ($circumference * $pct / 100);
    ?>
    <div class="score-card">
      <div class="sc-header" style="background:<?= $lc['gradient'] ?>">
        <div class="sc-avatar">
          <?php if (!empty($scoreCard['avatar_url'])): ?>
            <img src="<?= htmlspecialchars($scoreCard['avatar_url']) ?>" alt="">
          <?php else: ?>
            <?= strtoupper(substr($scoreCard['first_name'], 0, 1)) ?>
          <?php endif; ?>
        </div>
        <div class="sc-name"><?= htmlspecialchars($scoreCard['name']) ?></div>
        <div class="sc-league"><?= htmlspecialchars($scoreCard['league_label']) ?> League</div>

        <?php if ($predicted > 0): ?>
        <div class="sc-score-ring">
          <svg viewBox="0 0 128 128">
            <circle class="ring-bg" cx="64" cy="64" r="58"/>
            <circle class="ring-fill" cx="64" cy="64" r="58"
              stroke-dasharray="<?= round($circumference) ?>"
              stroke-dashoffset="<?= round($dashOffset) ?>"/>
          </svg>
          <div class="sc-score-num">
            <span class="num"><?= $predicted ?></span>
            <span class="label">Predicted SAT</span>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <div class="sc-stats">
        <div class="sc-stat">
          <div class="val"><?= number_format($scoreCard['total_xp']) ?></div>
          <div class="lbl">Total XP</div>
        </div>
        <div class="sc-stat">
          <div class="val"><?= $scoreCard['current_streak'] ?></div>
          <div class="lbl">Day Streak</div>
        </div>
        <div class="sc-stat">
          <div class="val"><?= number_format($scoreCard['questions_practiced']) ?></div>
          <div class="lbl">Questions</div>
        </div>
      </div>

      <div class="sc-footer">
        <span class="brand">Powered by <strong>AvidMock SAT</strong></span>
      </div>
    </div>

    <div class="cta">
      <a href="<?= htmlspecialchars(PUBLIC_URL) ?>">Start your SAT Prep — Free</a>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
