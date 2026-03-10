<?php
/**
 * score-predictor/index.php
 * AI-powered SAT score prediction with confidence intervals and trends.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/ScorePredictor.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/CategoryPerformance.php';

Auth::requireStudent();

$userId      = (int) $_SESSION['user_id'];
$activePage  = 'score-predictor';
$topbarTitle = 'Score Predictor';

// ── Fetch prediction data ─────────────────────────────────────────────────
try { $prediction = ScorePredictor::getLatest($userId); } catch (Throwable $e) { $prediction = null; }
try { $history = ScorePredictor::getHistory($userId); } catch (Throwable $e) { $history = []; }
try { $trend = ScorePredictor::getTrend($userId); } catch (Throwable $e) { $trend = 'steady'; }

$predicted  = $prediction['predicted_score'] ?? null;
$mathScore  = $prediction['math_score'] ?? null;
$rwScore    = $prediction['rw_score'] ?? null;
$confLow    = $prediction['confidence_low'] ?? null;
$confHigh   = $prediction['confidence_high'] ?? null;
$confidence = $prediction['probability_target'] ?? 0;

// ── Domain scores ─────────────────────────────────────────────────────────
try { $domains = CategoryPerformance::getDomainScores($userId); } catch (Throwable $e) { $domains = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Score Predictor — AvidMock SAT</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:wght@700;900&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{--dk:#143230;--ac:#1FE290;--tx:#1a1a2e;--tx2:#64748b;--tx3:#94a3b8;--bg:#f7faf9;--r:16px;--r-sm:10px;--shadow-sm:0 1px 2px rgba(0,0,0,.05);--shadow-md:0 4px 12px rgba(0,0,0,.08);--shadow-glow:0 0 20px rgba(31,226,144,.15)}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--tx);min-height:100vh}
.main-content{margin-left:260px;padding:32px;min-height:100vh}

.page-header{margin-bottom:28px}
.page-header h1{font-family:'Fraunces',serif;font-size:1.875rem;font-weight:900;letter-spacing:-.03em;color:var(--dk)}
.page-header p{color:var(--tx2);font-size:.875rem;margin-top:4px}

.score-hero{background:linear-gradient(135deg,#143230,#1a4a46);border-radius:20px;padding:40px;color:#fff;text-align:center;margin-bottom:28px;position:relative;overflow:hidden}
.score-hero::before{content:'';position:absolute;top:-50%;right:-30%;width:300px;height:300px;border-radius:50%;background:rgba(31,226,144,.08)}
.score-label{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;opacity:.7;margin-bottom:8px}
.score-big{font-family:'Fraunces',serif;font-size:4rem;font-weight:900;line-height:1;margin-bottom:4px}
.score-range{font-size:.875rem;opacity:.6;margin-bottom:16px}
.score-sections{display:flex;justify-content:center;gap:40px;margin-top:20px}
.score-section{text-align:center}
.score-section-label{font-size:.625rem;font-weight:700;text-transform:uppercase;letter-spacing:.8px;opacity:.5;margin-bottom:4px}
.score-section-value{font-family:'DM Mono',monospace;font-size:1.5rem;font-weight:700}

.trend-badge{display:inline-flex;align-items:center;gap:4px;padding:4px 12px;border-radius:20px;font-size:.75rem;font-weight:700;margin-top:12px}
.trend-badge.improving{background:rgba(31,226,144,.2);color:#1fe290}
.trend-badge.declining{background:rgba(239,68,68,.2);color:#ef4444}
.trend-badge.steady{background:rgba(255,255,255,.15);color:rgba(255,255,255,.7)}

.confidence-bar{margin-top:20px;max-width:300px;margin-left:auto;margin-right:auto}
.confidence-label{display:flex;justify-content:space-between;font-size:.6875rem;opacity:.6;margin-bottom:4px}
.confidence-track{height:6px;background:rgba(255,255,255,.1);border-radius:3px;overflow:hidden}
.confidence-fill{height:100%;background:var(--ac);border-radius:3px;transition:width .6s ease}

.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:28px}
.card{background:#fff;border:1px solid rgba(20,50,48,.06);border-radius:var(--r);padding:24px;box-shadow:var(--shadow-sm)}
.card-title{font-family:'Fraunces',serif;font-size:1.05rem;font-weight:900;color:var(--dk);margin-bottom:16px}

.domain-row{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid rgba(20,50,48,.04)}
.domain-row:last-child{border-bottom:none}
.domain-name{flex:1;font-size:.8125rem;font-weight:600;color:var(--tx)}
.domain-bar{width:120px;height:6px;background:rgba(20,50,48,.06);border-radius:3px;overflow:hidden}
.domain-fill{height:100%;border-radius:3px}
.domain-fill.hi{background:var(--ac)}
.domain-fill.md{background:#f59e0b}
.domain-fill.lo{background:#ef4444}
.domain-pct{font-family:'DM Mono',monospace;font-size:.8125rem;font-weight:600;min-width:40px;text-align:right}

.history-list{list-style:none}
.history-item{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(20,50,48,.04)}
.history-item:last-child{border-bottom:none}
.history-date{font-size:.75rem;color:var(--tx3)}
.history-score{font-family:'DM Mono',monospace;font-size:.875rem;font-weight:700;color:var(--dk)}
.history-change{font-size:.75rem;font-weight:600;padding:2px 8px;border-radius:4px}
.history-change.up{background:rgba(31,226,144,.1);color:var(--ac)}
.history-change.down{background:rgba(239,68,68,.1);color:#ef4444}

.empty-state{text-align:center;padding:60px 20px}
.empty-state h3{font-family:'Fraunces',serif;font-size:1.25rem;font-weight:900;color:var(--dk);margin-bottom:8px}
.empty-state p{color:var(--tx2);font-size:.875rem;margin-bottom:20px}
.btn-primary{display:inline-flex;align-items:center;gap:6px;padding:10px 20px;background:var(--ac);color:var(--dk);font-weight:700;font-size:.8125rem;border:none;border-radius:var(--r-sm);cursor:pointer;text-decoration:none;transition:all .18s}
.btn-primary:hover{transform:translateY(-1px);box-shadow:var(--shadow-glow)}

@media(max-width:768px){
    .main-content{margin-left:0;padding:16px}
    .grid-2{grid-template-columns:1fr}
    .score-big{font-size:3rem}
    .score-sections{flex-direction:column;gap:16px}
}
</style>
</head>
<body>
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/sidebar.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/topbar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1>Score Predictor</h1>
        <p>AI-powered SAT score estimate based on your practice performance</p>
    </div>

    <?php if ($predicted): ?>
    <div class="score-hero">
        <div class="score-label">Predicted SAT Score</div>
        <div class="score-big"><?= $predicted ?></div>
        <?php if ($confLow && $confHigh): ?>
        <div class="score-range"><?= $confLow ?> – <?= $confHigh ?> range</div>
        <?php endif; ?>

        <span class="trend-badge <?= e($trend) ?>">
            <?php if ($trend === 'improving'): ?>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/></svg>
                Improving
            <?php elseif ($trend === 'declining'): ?>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/></svg>
                Declining
            <?php else: ?>
                Steady
            <?php endif; ?>
        </span>

        <div class="score-sections">
            <div class="score-section">
                <div class="score-section-label">Math</div>
                <div class="score-section-value"><?= $mathScore ?? '—' ?></div>
            </div>
            <div class="score-section">
                <div class="score-section-label">Reading & Writing</div>
                <div class="score-section-value"><?= $rwScore ?? '—' ?></div>
            </div>
        </div>

        <?php if ($confidence > 0): ?>
        <div class="confidence-bar">
            <div class="confidence-label">
                <span>Confidence</span>
                <span><?= round($confidence * 100) ?>%</span>
            </div>
            <div class="confidence-track">
                <div class="confidence-fill" style="width:<?= round($confidence * 100) ?>%"></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="grid-2">
        <!-- Domain Breakdown -->
        <div class="card">
            <div class="card-title">Domain Performance</div>
            <?php if (!empty($domains)): ?>
                <?php foreach ($domains as $d):
                    $pct = round(($d['accuracy'] ?? 0) * 100);
                    $cls = $pct >= 70 ? 'hi' : ($pct >= 50 ? 'md' : 'lo');
                ?>
                <div class="domain-row">
                    <div class="domain-name"><?= e(ucwords(str_replace('_', ' ', $d['domain'] ?? ''))) ?></div>
                    <div class="domain-bar"><div class="domain-fill <?= $cls ?>" style="width:<?= $pct ?>%"></div></div>
                    <div class="domain-pct"><?= $pct ?>%</div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color:var(--tx3);font-size:.8125rem">Complete more quizzes to see domain breakdown.</p>
            <?php endif; ?>
        </div>

        <!-- Prediction History -->
        <div class="card">
            <div class="card-title">Prediction History</div>
            <?php if (!empty($history)): ?>
            <ul class="history-list">
                <?php
                $prev = null;
                foreach (array_slice($history, 0, 8) as $h):
                    $score = (int) $h['predicted_score'];
                    $change = $prev !== null ? $score - $prev : null;
                    $prev = $score;
                ?>
                <li class="history-item">
                    <span class="history-date"><?= date('M j', strtotime($h['created_at'])) ?></span>
                    <span class="history-score"><?= $score ?></span>
                    <?php if ($change !== null && $change !== 0): ?>
                    <span class="history-change <?= $change > 0 ? 'up' : 'down' ?>"><?= $change > 0 ? '+' : '' ?><?= $change ?></span>
                    <?php else: ?>
                    <span style="min-width:50px"></span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
                <p style="color:var(--tx3);font-size:.8125rem">Predictions appear after your first practice test.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php else: ?>
    <div class="card">
        <div class="empty-state">
            <h3>No predictions yet</h3>
            <p>Complete at least one practice test to get your AI-powered score prediction.</p>
            <a href="/practice-tests/" class="btn-primary">Take a Practice Test</a>
        </div>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
