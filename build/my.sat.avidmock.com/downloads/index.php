<?php
/**
 * Downloads — Study guides, formula sheets, and practice materials.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';

$user = Auth::requireStudent();
$userId    = (int)$_SESSION['user_id'];
$firstName = trim($user['first_name'] ?? 'Student');
$plan      = $user['subscription_plan'] ?? 'free';
$isPro     = in_array($plan, ['pro', 'family'], true);

// Downloadable resources
$resources = [
    [
        'category' => 'Formula Sheets',
        'items' => [
            ['title' => 'SAT Math Formula Sheet', 'desc' => 'All formulas you need for SAT Math in one page', 'file' => 'sat-math-formulas.pdf', 'size' => '420 KB', 'free' => true],
            ['title' => 'Algebra Cheat Sheet', 'desc' => 'Key algebra concepts, identities, and rules', 'file' => 'algebra-cheat-sheet.pdf', 'size' => '310 KB', 'free' => true],
            ['title' => 'Geometry Reference Guide', 'desc' => 'Shapes, theorems, and coordinate geometry', 'file' => 'geometry-reference.pdf', 'size' => '380 KB', 'free' => true],
            ['title' => 'Statistics & Probability', 'desc' => 'Mean, median, standard deviation, probability rules', 'file' => 'stats-probability.pdf', 'size' => '290 KB', 'free' => false],
        ]
    ],
    [
        'category' => 'Practice Worksheets',
        'items' => [
            ['title' => 'Linear Equations Practice', 'desc' => '50 problems with step-by-step solutions', 'file' => 'linear-equations-practice.pdf', 'size' => '1.2 MB', 'free' => true],
            ['title' => 'Quadratics Mastery Pack', 'desc' => '40 problems covering all quadratic types', 'file' => 'quadratics-mastery.pdf', 'size' => '980 KB', 'free' => false],
            ['title' => 'Word Problems Bootcamp', 'desc' => '60 SAT-style word problems with strategies', 'file' => 'word-problems-bootcamp.pdf', 'size' => '1.5 MB', 'free' => false],
            ['title' => 'Data Analysis & Graphs', 'desc' => '35 problems on charts, tables, and scatter plots', 'file' => 'data-analysis-practice.pdf', 'size' => '1.1 MB', 'free' => false],
        ]
    ],
    [
        'category' => 'Study Guides',
        'items' => [
            ['title' => 'SAT Math Study Plan (8 weeks)', 'desc' => 'Week-by-week study schedule for optimal prep', 'file' => 'sat-8week-plan.pdf', 'size' => '520 KB', 'free' => true],
            ['title' => 'Test Day Strategy Guide', 'desc' => 'Time management, guessing strategies, calculator tips', 'file' => 'test-day-strategy.pdf', 'size' => '350 KB', 'free' => true],
            ['title' => 'Desmos Calculator Guide', 'desc' => 'Master the built-in Desmos calculator for faster solving', 'file' => 'desmos-guide.pdf', 'size' => '680 KB', 'free' => false],
            ['title' => 'Score 1500+ Playbook', 'desc' => 'Advanced strategies for top-scoring students', 'file' => 'score-1500-playbook.pdf', 'size' => '890 KB', 'free' => false],
        ]
    ],
];

$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Downloads — AvidMock SAT</title>
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
  --radius:16px;--radius-sm:10px;--radius-xs:6px;
  --font:'DM Sans',system-ui,sans-serif;
  --mono:'DM Mono','Fira Code',monospace;
  --transition:cubic-bezier(.4,0,.2,1);
}
html{font-size:16px;scroll-behavior:smooth;-webkit-font-smoothing:antialiased}
body{font-family:var(--font);color:var(--tx);background:var(--bg);min-height:100vh;line-height:1.55}
a{color:var(--ac2);text-decoration:none;transition:color .2s var(--transition)}
a:hover{color:var(--dk)}

.wrap{max-width:860px;margin:0 auto;padding:32px 24px 80px}
.back{display:inline-flex;align-items:center;gap:6px;font-size:.85rem;color:var(--dk);opacity:.6;margin-bottom:24px;transition:opacity .2s}
.back:hover{opacity:1}

.page-header{margin-bottom:32px}
.page-header h1{font-size:1.8rem;font-weight:700;color:var(--dk);margin-bottom:4px}
.page-header p{font-size:.9rem;color:var(--tx);opacity:.55}

/* Pro badge */
.pro-banner{background:linear-gradient(135deg,var(--dk),#1a4a46);border-radius:var(--radius-sm);padding:16px 20px;margin-bottom:28px;display:flex;align-items:center;gap:16px;color:#fff}
.pro-banner .pro-icon{width:40px;height:40px;border-radius:var(--radius-xs);background:var(--ac);display:flex;align-items:center;justify-content:center;font-size:1.1rem;font-weight:700;color:var(--dk)}
.pro-banner .pro-text{flex:1}
.pro-banner .pro-text h3{font-size:.9rem;font-weight:600;margin-bottom:2px}
.pro-banner .pro-text p{font-size:.75rem;opacity:.6}
.pro-banner a{padding:8px 18px;background:var(--ac);color:var(--dk);border-radius:var(--radius-xs);font-size:.8rem;font-weight:600;white-space:nowrap}
.pro-banner a:hover{background:#fff;color:var(--dk)}

/* Category section */
.cat-section{margin-bottom:28px}
.cat-title{font-size:1rem;font-weight:600;color:var(--dk);margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid var(--card-border)}

/* Resource cards */
.res-list{display:flex;flex-direction:column;gap:8px}
.res-card{display:grid;grid-template-columns:48px 1fr auto;align-items:center;gap:14px;padding:16px 18px;background:var(--card);border:1px solid var(--card-border);border-radius:var(--radius-sm);transition:all .2s var(--transition)}
.res-card:hover{box-shadow:var(--shadow-sm);transform:translateY(-1px)}
.res-card.locked{opacity:.7}
.res-icon{width:44px;height:44px;border-radius:var(--radius-xs);display:flex;align-items:center;justify-content:center;font-size:1.1rem;font-weight:600}
.res-icon.pdf{background:rgba(220,53,69,.08);color:#dc3545}
.res-info h4{font-size:.9rem;font-weight:500;margin-bottom:2px}
.res-info p{font-size:.75rem;color:var(--tx);opacity:.45}
.res-info .res-size{font-family:var(--mono);font-size:.7rem;color:var(--tx);opacity:.35;margin-top:2px}
.res-action{display:flex;flex-direction:column;align-items:center;gap:4px}
.dl-btn{padding:8px 18px;border:none;border-radius:var(--radius-xs);font-family:var(--font);font-size:.8rem;font-weight:600;cursor:pointer;transition:all .2s var(--transition);text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.dl-btn.free{background:var(--ac);color:var(--dk)}
.dl-btn.free:hover{background:var(--ac2);transform:scale(1.03)}
.dl-btn.pro-only{background:rgba(20,50,48,.06);color:var(--tx);opacity:.6;cursor:default}
.dl-btn.pro-only:hover{transform:none}
.dl-btn.unlocked{background:var(--dk);color:#fff}
.dl-btn.unlocked:hover{background:var(--ac2);color:var(--dk);transform:scale(1.03)}
.pro-tag{font-size:.6rem;text-transform:uppercase;letter-spacing:.06em;color:var(--ac2);font-weight:600}

@media(max-width:600px){
  .wrap{padding:20px 16px 64px}
  .res-card{grid-template-columns:40px 1fr auto;gap:10px;padding:12px 14px}
  .pro-banner{flex-direction:column;text-align:center;gap:10px}
}
</style>
</head>
<body>
<div class="wrap">
  <a href="/index.php" class="back">&larr; Dashboard</a>

  <div class="page-header">
    <h1>Downloads</h1>
    <p>Free study guides, formula sheets, and practice worksheets for SAT Math</p>
  </div>

  <?php if (!$isPro): ?>
  <div class="pro-banner">
    <div class="pro-icon">P</div>
    <div class="pro-text">
      <h3>Unlock All Resources</h3>
      <p>Pro members get access to every download, plus exclusive strategy guides</p>
    </div>
    <a href="/profile/settings.php#billing">Upgrade to Pro</a>
  </div>
  <?php endif; ?>

  <?php foreach ($resources as $cat): ?>
  <div class="cat-section">
    <h2 class="cat-title"><?= htmlspecialchars($cat['category']) ?></h2>
    <div class="res-list">
      <?php foreach ($cat['items'] as $item):
        $canDownload = $item['free'] || $isPro;
        $lockedClass = $canDownload ? '' : ' locked';
      ?>
      <div class="res-card<?= $lockedClass ?>">
        <div class="res-icon pdf">PDF</div>
        <div class="res-info">
          <h4><?= htmlspecialchars($item['title']) ?></h4>
          <p><?= htmlspecialchars($item['desc']) ?></p>
          <div class="res-size"><?= $item['size'] ?></div>
        </div>
        <div class="res-action">
          <?php if ($canDownload): ?>
            <a href="/downloads/files/<?= htmlspecialchars($item['file']) ?>" class="dl-btn <?= $item['free'] ? 'free' : 'unlocked' ?>" download>Download</a>
          <?php else: ?>
            <span class="dl-btn pro-only">Pro Only</span>
            <span class="pro-tag">Upgrade</span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
</body>
</html>
