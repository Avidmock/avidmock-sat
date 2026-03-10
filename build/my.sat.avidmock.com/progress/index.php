<?php
/**
 * progress/index.php
 * Progress timeline — visual history of student achievements, scores, and milestones.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Database.php';

Auth::requireStudent();

$userId      = (int) $_SESSION['user_id'];
$activePage  = 'progress';
$topbarTitle = 'My Progress';
$db          = Database::connect();

// ── Overall stats ─────────────────────────────────────────────────────────
try {
    $stmt = $db->prepare("SELECT COUNT(*) AS total, ROUND(AVG(score),1) AS avg_score, MAX(score) AS best_score FROM sat_quiz_attempts WHERE user_id = ? AND status = 'completed'");
    $stmt->execute([$userId]);
    $quizStats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $quizStats = ['total' => 0, 'avg_score' => 0, 'best_score' => 0]; }

try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM practice_test_attempts WHERE user_id = ? AND status = 'completed'");
    $stmt->execute([$userId]);
    $testCount = (int) $stmt->fetchColumn();
} catch (Throwable $e) { $testCount = 0; }

// ── XP & Level ────────────────────────────────────────────────────────────
try {
    $xpStmt = $db->prepare("SELECT total_xp FROM user_xp WHERE user_id = ?");
    $xpStmt->execute([$userId]);
    $totalXp = (int) $xpStmt->fetchColumn();
} catch (Throwable $e) {
    try {
        $xpStmt = $db->prepare("SELECT xp FROM user_xp WHERE user_id = ?");
        $xpStmt->execute([$userId]);
        $totalXp = (int) $xpStmt->fetchColumn();
    } catch (Throwable $e2) { $totalXp = 0; }
}

function calcLevel(int $xp): int { $l = 1; while (500 * $l * ($l + 1) / 2 <= $xp) $l++; return $l; }
$level = calcLevel($totalXp);

// ── Build timeline events ─────────────────────────────────────────────────
$timeline = [];

// Quiz completions
try {
    $stmt = $db->prepare(
        "SELECT a.score, a.created_at, q.title
         FROM sat_quiz_attempts a
         LEFT JOIN sat_quizzes q ON q.id = a.quiz_id
         WHERE a.user_id = ? AND a.status = 'completed'
         ORDER BY a.created_at DESC LIMIT 20"
    );
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $timeline[] = [
            'type' => 'quiz',
            'date' => $r['created_at'],
            'title' => 'Completed: ' . ($r['title'] ?? 'Quiz'),
            'detail' => 'Score: ' . round($r['score']) . '%',
            'score' => (float)$r['score'],
        ];
    }
} catch (Throwable $e) {}

// Practice tests
try {
    $stmt = $db->prepare(
        "SELECT total_score, math_score, rw_score, created_at
         FROM practice_test_attempts
         WHERE user_id = ? AND status = 'completed'
         ORDER BY created_at DESC LIMIT 10"
    );
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $timeline[] = [
            'type' => 'test',
            'date' => $r['created_at'],
            'title' => 'Practice Test Completed',
            'detail' => 'Score: ' . ($r['total_score'] ?? '—') . ' (M:' . ($r['math_score'] ?? '—') . ' R&W:' . ($r['rw_score'] ?? '—') . ')',
            'score' => (float)($r['total_score'] ?? 0),
        ];
    }
} catch (Throwable $e) {}

// Achievements
try {
    $stmt = $db->prepare(
        "SELECT ua.unlocked_at, a.name, a.description
         FROM user_achievements ua
         JOIN achievements a ON a.id = ua.achievement_id
         WHERE ua.user_id = ?
         ORDER BY ua.unlocked_at DESC LIMIT 15"
    );
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $timeline[] = [
            'type' => 'achievement',
            'date' => $r['unlocked_at'],
            'title' => 'Badge: ' . ($r['name'] ?? 'Achievement'),
            'detail' => $r['description'] ?? '',
            'score' => null,
        ];
    }
} catch (Throwable $e) {}

// Sort by date descending
usort($timeline, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));
$timeline = array_slice($timeline, 0, 30);

// ── Weekly score trend ────────────────────────────────────────────────────
$weeklyScores = [];
try {
    $stmt = $db->prepare(
        "SELECT DATE(created_at) AS d, ROUND(AVG(score),1) AS avg
         FROM sat_quiz_attempts WHERE user_id = ? AND status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY DATE(created_at) ORDER BY d ASC"
    );
    $stmt->execute([$userId]);
    $weeklyScores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Progress — AvidMock SAT</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:wght@700;900&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{--dk:#143230;--ac:#1FE290;--tx:#1a1a2e;--tx2:#64748b;--tx3:#94a3b8;--bg:#f7faf9;--r:16px;--r-sm:10px}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--tx);min-height:100vh}
.main-content{margin-left:260px;padding:32px;min-height:100vh}

.page-header{margin-bottom:28px}
.page-header h1{font-family:'Fraunces',serif;font-size:1.875rem;font-weight:900;letter-spacing:-.03em;color:var(--dk)}
.page-header p{color:var(--tx2);font-size:.875rem;margin-top:4px}

.stats-row{display:grid;grid-template-columns:repeat(auto-fit, minmax(140px, 1fr));gap:14px;margin-bottom:28px}
.stat-card{background:#fff;border:1px solid rgba(20,50,48,.06);border-radius:var(--r-sm);padding:16px 20px}
.stat-label{font-size:.625rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--tx3);margin-bottom:4px}
.stat-value{font-family:'DM Mono',monospace;font-size:1.5rem;font-weight:800;color:var(--dk)}
.stat-value.green{color:var(--ac)}

/* Score chart */
.chart-card{background:#fff;border:1px solid rgba(20,50,48,.06);border-radius:var(--r);padding:24px;margin-bottom:28px}
.chart-title{font-family:'Fraunces',serif;font-size:1.05rem;font-weight:900;color:var(--dk);margin-bottom:16px}
.chart-area{height:160px;display:flex;align-items:flex-end;gap:4px;padding-top:20px;position:relative}
.chart-bar{flex:1;border-radius:4px 4px 0 0;min-width:8px;transition:height .4s ease;position:relative}
.chart-bar:hover::after{content:attr(data-value);position:absolute;top:-20px;left:50%;transform:translateX(-50%);font-size:.5625rem;font-weight:700;color:var(--dk);background:#fff;padding:2px 6px;border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,.1);white-space:nowrap}
.chart-labels{display:flex;justify-content:space-between;font-size:.5rem;color:var(--tx3);margin-top:6px}

/* Timeline */
.timeline{position:relative;padding-left:32px}
.timeline::before{content:'';position:absolute;left:11px;top:8px;bottom:8px;width:2px;background:rgba(20,50,48,.06)}
.tl-item{position:relative;padding-bottom:24px}
.tl-item:last-child{padding-bottom:0}
.tl-dot{position:absolute;left:-32px;top:4px;width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:2px solid}
.tl-dot.quiz{background:rgba(31,226,144,.1);border-color:var(--ac)}
.tl-dot.quiz svg{stroke:var(--ac)}
.tl-dot.test{background:rgba(59,130,246,.1);border-color:#3b82f6}
.tl-dot.test svg{stroke:#3b82f6}
.tl-dot.achievement{background:rgba(245,158,11,.1);border-color:#f59e0b}
.tl-dot.achievement svg{stroke:#f59e0b}
.tl-dot svg{width:10px;height:10px;fill:none;stroke-width:2.5}
.tl-date{font-size:.625rem;color:var(--tx3);font-family:'DM Mono',monospace;margin-bottom:2px}
.tl-title{font-size:.875rem;font-weight:700;color:var(--tx)}
.tl-detail{font-size:.75rem;color:var(--tx2);margin-top:2px}

.empty-text{color:var(--tx3);font-size:.875rem;text-align:center;padding:40px}

.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.card{background:#fff;border:1px solid rgba(20,50,48,.06);border-radius:var(--r);padding:24px}
.card-title{font-family:'Fraunces',serif;font-size:1.05rem;font-weight:900;color:var(--dk);margin-bottom:16px}

@media(max-width:768px){.main-content{margin-left:0;padding:16px}.grid-2{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/sidebar.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/topbar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1>My Progress</h1>
        <p>Track your SAT prep journey</p>
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-label">Quizzes Done</div>
            <div class="stat-value"><?= (int)($quizStats['total'] ?? 0) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Avg Score</div>
            <div class="stat-value"><?= $quizStats['avg_score'] ?? '—' ?>%</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Best Score</div>
            <div class="stat-value green"><?= round($quizStats['best_score'] ?? 0) ?>%</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Practice Tests</div>
            <div class="stat-value"><?= $testCount ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total XP</div>
            <div class="stat-value"><?= number_format($totalXp) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Level</div>
            <div class="stat-value green"><?= $level ?></div>
        </div>
    </div>

    <?php if (!empty($weeklyScores)): ?>
    <div class="chart-card">
        <div class="chart-title">Score Trend (Last 30 Days)</div>
        <div class="chart-area">
            <?php foreach ($weeklyScores as $ws):
                $pct = max(5, (float)$ws['avg']);
                $color = $pct >= 70 ? 'var(--ac)' : ($pct >= 50 ? '#f59e0b' : '#ef4444');
            ?>
            <div class="chart-bar" style="height:<?= $pct ?>%;background:<?= $color ?>" data-value="<?= $ws['avg'] ?>% — <?= date('M j', strtotime($ws['d'])) ?>"></div>
            <?php endforeach; ?>
        </div>
        <div class="chart-labels">
            <span><?= !empty($weeklyScores) ? date('M j', strtotime($weeklyScores[0]['d'])) : '' ?></span>
            <span><?= !empty($weeklyScores) ? date('M j', strtotime(end($weeklyScores)['d'])) : '' ?></span>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-title">Activity Timeline</div>
        <?php if (empty($timeline)): ?>
        <div class="empty-text">Start taking quizzes and practice tests to build your timeline!</div>
        <?php else: ?>
        <div class="timeline">
            <?php foreach ($timeline as $event): ?>
            <div class="tl-item">
                <div class="tl-dot <?= $event['type'] ?>">
                    <?php if ($event['type'] === 'quiz'): ?>
                    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    <?php elseif ($event['type'] === 'test'): ?>
                    <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/></svg>
                    <?php else: ?>
                    <svg viewBox="0 0 24 24"><polygon points="12 2 15 8.5 22 9.3 17 14 18.2 21 12 17.8 5.8 21 7 14 2 9.3 9 8.5"/></svg>
                    <?php endif; ?>
                </div>
                <div class="tl-date"><?= date('M j, Y g:ia', strtotime($event['date'])) ?></div>
                <div class="tl-title"><?= htmlspecialchars($event['title']) ?></div>
                <?php if ($event['detail']): ?>
                <div class="tl-detail"><?= htmlspecialchars($event['detail']) ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
