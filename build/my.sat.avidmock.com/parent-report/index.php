<?php
/**
 * parent-report/index.php — View-Only Parent Dashboard
 *
 * Accessed via share token (no login required).
 * Shows the student's weekly progress in a beautiful read-only view.
 * Must work on mobile — parents check on their phones.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/ParentReport.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/StudyStreak.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/CategoryPerformance.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/ScorePredictor.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Achievement.php';

/* ── Validate token ── */
$token  = trim($_GET['token'] ?? '');
$userId = null;
$error  = null;

if ($token === '') {
    $error = 'No access token provided. Please use the link from your email.';
} else {
    try {
        $userId = ParentReport::validateToken($token);
        if (!$userId) {
            $error = 'This link has expired or is invalid. Ask your child to send a new report from their Avidmock dashboard.';
        }
    } catch (\Throwable $e) {
        error_log('[parent-report/index.php] token validate: ' . $e->getMessage());
        $error = 'Something went wrong. Please try again later.';
    }
}

/* ── Load report data ── */
$data = [];
if ($userId && !$error) {
    try {
        $data = ParentReport::generateWeeklyReport($userId);
    } catch (\Throwable $e) {
        error_log('[parent-report/index.php] generate: ' . $e->getMessage());
        $error = 'Could not load report data. Please try again later.';
    }
}

$studentName = e($data['student_name'] ?? 'Student');
$weekStart   = !empty($data['week_start']) ? date('M j', strtotime($data['week_start'])) : date('M j');
$weekEnd     = date('M j');
$inc         = $data['includes'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $studentName ?>&rsquo;s Progress Report &mdash; Avidmock SAT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --dk: #143230;
            --ac: #1FE290;
            --ac2: #17c87a;
            --tx: #1a1a2e;
            --bg: #f7faf9;
            --muted: #6B7280;
            --border: #E8F3F1;
            --card: #FFFFFF;
            --amber: #FFB347;
            --coral: #FF6B6B;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--tx);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        .container { max-width: 640px; margin: 0 auto; padding: 0 16px; }

        /* Header */
        .header {
            background: var(--dk);
            padding: 28px 20px;
            text-align: center;
        }
        .header-logo {
            font-size: 22px; font-weight: 800; color: #fff; letter-spacing: -0.3px;
        }
        .header-logo .dot { color: var(--ac); }
        .header-sub { color: #9BBAB7; font-size: 13px; margin-top: 6px; }

        /* Cards */
        .card {
            background: var(--card);
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 16px;
            box-shadow: 0 1px 4px rgba(20,50,48,0.05);
        }
        .card-title {
            font-size: 15px; font-weight: 700; color: var(--dk); margin-bottom: 14px;
        }

        /* Stats grid */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 16px;
        }
        .stat-box {
            background: var(--bg);
            border-radius: 10px;
            padding: 16px 12px;
            text-align: center;
        }
        .stat-num {
            font-size: 26px; font-weight: 800; color: var(--dk);
        }
        .stat-label {
            font-size: 11px; color: var(--muted); margin-top: 2px;
        }

        /* Proud moment */
        .proud {
            background: linear-gradient(135deg, var(--dk), #1a4440);
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 16px;
        }
        .proud-tag {
            color: var(--ac); font-weight: 700; font-size: 11px;
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;
        }
        .proud-title { color: #fff; font-size: 18px; font-weight: 700; line-height: 1.3; }
        .proud-detail { color: #9BBAB7; font-size: 13px; margin-top: 6px; }

        /* Bar chart */
        .bar-row { display: flex; align-items: center; margin-bottom: 8px; }
        .bar-label { width: 52px; font-size: 11px; color: var(--muted); text-align: right; padding-right: 10px; flex-shrink: 0; }
        .bar-track { flex: 1; background: var(--border); border-radius: 6px; height: 22px; overflow: hidden; }
        .bar-fill  { height: 22px; border-radius: 6px; transition: width .3s ease; }
        .bar-value { width: 42px; font-size: 12px; font-weight: 700; color: var(--dk); padding-left: 8px; flex-shrink: 0; }

        /* 7-day heatmap */
        .heatmap { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; margin-top: 12px; }
        .heatmap-cell { text-align: center; }
        .heatmap-day { font-size: 10px; color: var(--muted); margin-bottom: 4px; }
        .heatmap-dot {
            width: 32px; height: 32px; border-radius: 8px; margin: 0 auto;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700; color: var(--dk);
        }
        .heatmap-dot.active { background: var(--ac); }
        .heatmap-dot.inactive { background: var(--border); color: var(--muted); }

        /* Skill bars */
        .skill-item { margin-bottom: 12px; }
        .skill-meta { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px; }
        .skill-name { color: var(--dk); }
        .skill-pct  { font-weight: 700; color: var(--dk); }
        .skill-track { background: var(--border); border-radius: 4px; height: 8px; }
        .skill-fill  { border-radius: 4px; height: 8px; }

        /* SAT prediction */
        .prediction {
            background: var(--dk);
            border-radius: 14px;
            padding: 24px;
            text-align: center;
            margin-bottom: 16px;
        }
        .prediction-label {
            color: var(--ac); font-size: 11px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;
        }
        .prediction-score { color: #fff; font-size: 34px; font-weight: 800; }
        .prediction-detail { color: #9BBAB7; font-size: 12px; margin-top: 4px; }

        /* Badges */
        .badge-chip {
            display: inline-block;
            border-radius: 8px;
            padding: 8px 14px;
            margin: 0 6px 6px 0;
            font-size: 13px; font-weight: 600; color: var(--dk);
        }
        .badge-xp { color: var(--muted); font-weight: 400; font-size: 11px; margin-left: 4px; }

        /* Footer */
        .footer {
            text-align: center;
            padding: 32px 16px 40px;
            color: var(--muted);
            font-size: 13px;
        }
        .footer a { color: var(--ac); text-decoration: none; font-weight: 600; }
        .footer-cta {
            display: inline-block; margin-top: 16px; padding: 12px 32px;
            background: var(--ac); color: var(--dk); font-weight: 700;
            border-radius: 8px; text-decoration: none; font-size: 14px;
        }

        /* Error state */
        .error-page {
            min-height: 60vh; display: flex; flex-direction: column;
            align-items: center; justify-content: center; text-align: center; padding: 40px 20px;
        }
        .error-page h2 { color: var(--dk); margin-bottom: 8px; }
        .error-page p { color: var(--muted); max-width: 380px; }

        @media (max-width: 480px) {
            .stats-row { grid-template-columns: 1fr 1fr; }
            .stat-num { font-size: 22px; }
            .container { padding: 0 12px; }
            .card { padding: 20px 16px; }
        }
    </style>
</head>
<body>

<!-- Header -->
<div class="header">
    <div class="header-logo"><span class="dot">&#9679;</span> avidmock</div>
    <?php if (!$error): ?>
    <div class="header-sub">
        <?= $studentName ?>&rsquo;s Weekly Progress &middot; <?= $weekStart ?> &ndash; <?= $weekEnd ?>
    </div>
    <?php endif; ?>
</div>

<div class="container" style="padding-top: 20px; padding-bottom: 20px;">

<?php if ($error): ?>
    <div class="error-page">
        <h2>Link Expired</h2>
        <p><?= e($error) ?></p>
    </div>
<?php else: ?>

    <?php /* ── Proud Parent Moment ── */ ?>
    <?php if (!empty($data['proud_moment'])): ?>
    <div class="proud">
        <div class="proud-tag"><?= $data['proud_moment']['emoji'] ?? '' ?> Proud Parent Moment</div>
        <div class="proud-title"><?= e($data['proud_moment']['title'] ?? '') ?></div>
        <div class="proud-detail"><?= e($data['proud_moment']['detail'] ?? '') ?></div>
    </div>
    <?php endif; ?>

    <?php /* ── This Week's Highlights ── */ ?>
    <div class="card">
        <div class="card-title">&#127775; This Week&rsquo;s Highlights</div>
        <?php
        $highlights = [];
        if (!empty($data['quizzes']['completed'])) {
            $highlights[] = 'Completed <strong>' . $data['quizzes']['completed'] . '</strong> quiz' . ($data['quizzes']['completed'] !== 1 ? 'zes' : '');
        }
        if (!empty($data['quizzes']['avg_score'])) {
            $highlights[] = 'Average score: <strong>' . $data['quizzes']['avg_score'] . '%</strong>';
        }
        if (isset($data['quizzes']['score_delta']) && $data['quizzes']['score_delta'] > 0) {
            $highlights[] = 'Score improved by <strong>+' . $data['quizzes']['score_delta'] . '%</strong> vs last week';
        }
        if (!empty($data['streak']['current']) && $data['streak']['current'] >= 2) {
            $highlights[] = '&#128293; <strong>' . $data['streak']['current'] . '-day</strong> study streak';
        }
        if (!empty($data['ai_tutor']['sessions'])) {
            $highlights[] = 'Used AI Tutor <strong>' . $data['ai_tutor']['sessions'] . '</strong> time' . ($data['ai_tutor']['sessions'] !== 1 ? 's' : '');
        }
        if (!empty($data['achievements'])) {
            $highlights[] = 'Earned <strong>' . count($data['achievements']) . '</strong> new badge' . (count($data['achievements']) !== 1 ? 's' : '');
        }
        if (empty($highlights)) {
            $highlights[] = 'Getting started on this week&rsquo;s study plan!';
        }
        ?>
        <ul style="list-style: none; padding: 0;">
            <?php foreach ($highlights as $h): ?>
            <li style="padding: 6px 0; font-size: 14px; color: var(--tx); border-bottom: 1px solid var(--border);">
                <?= $h ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <?php /* ── Score Overview ── */ ?>
    <?php if (!empty($inc['scores']) && !empty($data['quizzes'])): ?>
    <div class="card">
        <div class="card-title">&#128202; Score Overview</div>
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-num"><?= $data['quizzes']['completed'] ?></div>
                <div class="stat-label">Quizzes</div>
            </div>
            <div class="stat-box">
                <div class="stat-num"><?= $data['quizzes']['avg_score'] ?>%</div>
                <div class="stat-label">Avg Score</div>
            </div>
            <div class="stat-box">
                <?php
                    $delta = $data['quizzes']['score_delta'];
                    $dColor = $delta > 0 ? 'var(--ac)' : ($delta < 0 ? 'var(--coral)' : 'var(--muted)');
                    $dSign  = $delta > 0 ? '+' : '';
                    $dText  = $delta !== null ? $dSign . $delta . '%' : '--';
                ?>
                <div class="stat-num" style="color: <?= $dColor ?>;"><?= $dText ?></div>
                <div class="stat-label">vs Last Week</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php /* ── Score Trend Chart ── */ ?>
    <?php if (!empty($data['score_trend']) && count($data['score_trend']) >= 2): ?>
    <div class="card">
        <div class="card-title">&#128200; Score Trend</div>
        <?php foreach ($data['score_trend'] as $week):
            $pct   = min(100, max(0, round((float) $week['avg_score'])));
            $barW  = max(4, $pct);
            $color = $pct >= 80 ? 'var(--ac)' : ($pct >= 60 ? 'var(--ac2)' : 'var(--amber)');
        ?>
        <div class="bar-row">
            <div class="bar-label"><?= e($week['week_label']) ?></div>
            <div class="bar-track">
                <div class="bar-fill" style="width: <?= $barW ?>%; background: <?= $color ?>;"></div>
            </div>
            <div class="bar-value"><?= $pct ?>%</div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php /* ── Study Streak & Activity ── */ ?>
    <?php if (!empty($inc['streaks']) && !empty($data['streak'])): ?>
    <div class="card">
        <div class="card-title">&#128293; Study Activity</div>
        <div class="stats-row" style="grid-template-columns: 1fr 1fr;">
            <div class="stat-box">
                <div class="stat-num"><?= $data['streak']['current'] ?></div>
                <div class="stat-label">Day Streak</div>
            </div>
            <div class="stat-box">
                <?php
                    $mins = (int) ($data['streak']['total_study_time'] ?? 0);
                    $hrs  = floor($mins / 60);
                    $rm   = $mins % 60;
                    $timeStr = $hrs > 0 ? $hrs . 'h ' . $rm . 'm' : $mins . ' min';
                ?>
                <div class="stat-num"><?= $timeStr ?></div>
                <div class="stat-label">Study Time</div>
            </div>
        </div>

        <!-- 7-day heatmap -->
        <?php if (!empty($data['streak']['activity_7days'])): ?>
        <div class="heatmap">
            <?php
            $dayNames = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
            $actMap = [];
            foreach ($data['streak']['activity_7days'] as $a) {
                $actMap[$a['day']] = (int) $a['sessions'];
            }
            for ($i = 0; $i < 7; $i++):
                $d   = date('Y-m-d', strtotime("monday this week +{$i} days"));
                $cnt = $actMap[$d] ?? 0;
            ?>
            <div class="heatmap-cell">
                <div class="heatmap-day"><?= $dayNames[$i] ?></div>
                <div class="heatmap-dot <?= $cnt > 0 ? 'active' : 'inactive' ?>">
                    <?= $cnt > 0 ? $cnt : '&middot;' ?>
                </div>
            </div>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php /* ── Skills Breakdown ── */ ?>
    <?php if (!empty($inc['scores']) && (!empty($data['skills_strong']) || !empty($data['skills_weak']))): ?>
    <div class="card">
        <div class="card-title">&#128218; Skill Breakdown</div>

        <?php if (!empty($data['skills_strong'])): ?>
        <div style="font-size: 12px; font-weight: 600; color: var(--ac); margin-bottom: 8px;">&#9650; Strongest</div>
        <?php foreach ($data['skills_strong'] as $skill):
            $pct = round((float) ($skill['avg_score'] ?? 0));
        ?>
        <div class="skill-item">
            <div class="skill-meta">
                <span class="skill-name"><?= e(ucwords(str_replace('_', ' ', $skill['category'] ?? ''))) ?></span>
                <span class="skill-pct"><?= $pct ?>%</span>
            </div>
            <div class="skill-track">
                <div class="skill-fill" style="width: <?= max(2, $pct) ?>%; background: var(--ac);"></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($data['skills_weak'])): ?>
        <div style="font-size: 12px; font-weight: 600; color: var(--amber); margin: 16px 0 8px;">&#9660; Needs Attention</div>
        <?php foreach ($data['skills_weak'] as $skill):
            $pct = round((float) ($skill['avg_score'] ?? 0));
        ?>
        <div class="skill-item">
            <div class="skill-meta">
                <span class="skill-name"><?= e(ucwords(str_replace('_', ' ', $skill['category'] ?? ''))) ?></span>
                <span class="skill-pct"><?= $pct ?>%</span>
            </div>
            <div class="skill-track">
                <div class="skill-fill" style="width: <?= max(2, $pct) ?>%; background: var(--amber);"></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php /* ── SAT Score Prediction ── */ ?>
    <?php if (!empty($data['sat_prediction'])): ?>
    <div class="prediction">
        <div class="prediction-label">Predicted SAT Score</div>
        <div class="prediction-score">
            <?= $data['sat_prediction']['predicted_low'] ?>&ndash;<?= $data['sat_prediction']['predicted_high'] ?>
        </div>
        <?php if (!empty($data['sat_prediction']['confidence'])): ?>
        <div class="prediction-detail">
            <?= round($data['sat_prediction']['confidence'] * 100) ?>% confidence &middot;
            Based on <?= $data['sat_prediction']['based_on'] ?? 0 ?> practice test<?= ($data['sat_prediction']['based_on'] ?? 0) !== 1 ? 's' : '' ?>
        </div>
        <?php endif; ?>
        <?php if ($data['sat_prediction']['math_predicted'] && $data['sat_prediction']['rw_predicted']): ?>
        <div style="margin-top: 12px; display: flex; justify-content: center; gap: 24px;">
            <div>
                <div style="color: #9BBAB7; font-size: 11px;">Math</div>
                <div style="color: #fff; font-size: 20px; font-weight: 700;"><?= $data['sat_prediction']['math_predicted'] ?></div>
            </div>
            <div>
                <div style="color: #9BBAB7; font-size: 11px;">R&amp;W</div>
                <div style="color: #fff; font-size: 20px; font-weight: 700;"><?= $data['sat_prediction']['rw_predicted'] ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php /* ── AI Tutor ── */ ?>
    <?php if (!empty($inc['ai_usage']) && !empty($data['ai_tutor']) && $data['ai_tutor']['sessions'] > 0): ?>
    <div class="card">
        <div class="card-title">&#129302; AI Tutor Activity</div>
        <p style="font-size: 14px; color: var(--muted); line-height: 1.6;">
            <?= $data['ai_tutor']['sessions'] ?> session<?= $data['ai_tutor']['sessions'] !== 1 ? 's' : '' ?>
            covering <?= $data['ai_tutor']['topics'] ?> topic<?= $data['ai_tutor']['topics'] !== 1 ? 's' : '' ?> this week.
            <?php if (!empty($data['ai_tutor']['top_topics'])): ?>
            <br><strong>Top topics:</strong>
            <?= e(implode(', ', array_map(fn($t) => ucwords(str_replace('_', ' ', $t['topic'] ?? '')), $data['ai_tutor']['top_topics']))) ?>
            <?php endif; ?>
        </p>
    </div>
    <?php endif; ?>

    <?php /* ── Achievements ── */ ?>
    <?php if (!empty($inc['achievements']) && !empty($data['achievements'])): ?>
    <div class="card">
        <div class="card-title">&#127942; Achievements Unlocked</div>
        <div>
            <?php foreach ($data['achievements'] as $badge): ?>
            <span class="badge-chip" style="background: <?= e($badge['badge_color'] ?? '#1FE290') ?>22; border: 1px solid <?= e($badge['badge_color'] ?? '#1FE290') ?>44;">
                <?= e($badge['name'] ?? '') ?>
                <span class="badge-xp">+<?= (int) ($badge['xp_reward'] ?? 0) ?> XP</span>
            </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

<?php endif; /* end !$error */ ?>

</div>

<!-- Footer -->
<div class="footer">
    <div>Powered by <a href="https://sat.avidmock.com">Avidmock SAT</a></div>
    <div style="margin-top: 4px; font-size: 12px; color: #9CA3AF;">
        The smartest way to ace the SAT
    </div>
    <a href="https://sat.avidmock.com" class="footer-cta">
        Start your child&rsquo;s journey &rarr;
    </a>
</div>

</body>
</html>
