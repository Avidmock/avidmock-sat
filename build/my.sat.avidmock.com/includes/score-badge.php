<?php
/**
 * /includes/score-badge.php — Predictive Score Badge Component
 *
 * Usage:
 *   <?php
 *   $scoreBadgeUserId = $userId;
 *   $scoreBadgeSize   = 'large'; // 'small', 'medium', 'large'
 *   $scoreBadgeStyle  = 'card';  // 'card', 'inline', 'pill'
 *   include $_SERVER['DOCUMENT_ROOT'] . '/includes/score-badge.php';
 *   ?>
 *
 * Requires: ScorePredictor class loaded
 */

// Defaults
$sbUserId = $scoreBadgeUserId ?? (current_user_id() ?: 0);
$sbSize   = $scoreBadgeSize ?? 'medium';
$sbStyle  = $scoreBadgeStyle ?? 'card';

if (!$sbUserId) return;

// Load prediction
if (!class_exists('ScorePredictor')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/ScorePredictor.php';
}
$sbPrediction = ScorePredictor::getLatest($sbUserId);

// No data yet — show empty state
$sbHasData = !empty($sbPrediction) && !empty($sbPrediction['predicted_mid']);

$sbMid        = $sbPrediction['predicted_mid']  ?? 0;
$sbLow        = $sbPrediction['predicted_low']  ?? 0;
$sbHigh       = $sbPrediction['predicted_high'] ?? 0;
$sbConfidence = round(($sbPrediction['confidence'] ?? 0) * 100);
$sbMathPred   = $sbPrediction['math_predicted'] ?? null;
$sbRwPred     = $sbPrediction['rw_predicted']   ?? null;

// Score percentage for the ring (400–1600 scale → 0–100%)
$sbPct = $sbHasData ? round((($sbMid - 400) / 1200) * 100) : 0;

// Unique ID for multiple badges on same page
$sbId = 'sb-' . uniqid();

// Size config
$sbRingSize = match($sbSize) { 'small' => 60, 'large' => 120, default => 80 };
$sbFontSize = match($sbSize) { 'small' => '1.1rem', 'large' => '2.2rem', default => '1.5rem' };
$sbLabelSize = match($sbSize) { 'small' => '.6rem', 'large' => '.75rem', default => '.65rem' };
?>

<?php if ($sbStyle === 'pill'): ?>
<!-- ═══ PILL STYLE ═══ -->
<div class="score-pill <?= $sbHasData ? '' : 'empty' ?>" id="<?= $sbId ?>">
    <?php if ($sbHasData): ?>
    <span class="sp-score"><?= $sbMid ?></span>
    <span class="sp-range"><?= $sbLow ?>–<?= $sbHigh ?></span>
    <?php else: ?>
    <span class="sp-empty">Take a practice test to get your predicted score</span>
    <?php endif; ?>
</div>
<style>
.score-pill{display:inline-flex;align-items:center;gap:8px;padding:6px 14px;border-radius:20px;background:linear-gradient(135deg,rgba(91,40,164,.08),rgba(91,40,164,.04));border:1px solid rgba(91,40,164,.15)}
.score-pill .sp-score{font-weight:800;font-size:.95rem;color:#5b28a4}
.score-pill .sp-range{font-size:.7rem;color:#908daa;font-family:var(--fm,'DM Mono',monospace)}
.score-pill.empty{background:var(--bg,#f7faf9);border:1px dashed var(--bd,#e2ebe9)}
.score-pill .sp-empty{font-size:.75rem;color:var(--tx3,#8a8a9a)}
</style>

<?php elseif ($sbStyle === 'inline'): ?>
<!-- ═══ INLINE STYLE ═══ -->
<span class="score-inline" id="<?= $sbId ?>">
    <?php if ($sbHasData): ?>
    <svg width="<?= $sbRingSize ?>" height="<?= $sbRingSize ?>" viewBox="0 0 48 48" class="si-ring">
        <circle cx="24" cy="24" r="20" stroke="rgba(91,40,164,.12)" stroke-width="4" fill="none"/>
        <circle cx="24" cy="24" r="20" stroke="#5b28a4" stroke-width="4" fill="none"
                stroke-linecap="round"
                stroke-dasharray="<?= round(2 * M_PI * 20, 1) ?>"
                stroke-dashoffset="<?= round(2 * M_PI * 20 * (1 - $sbPct / 100), 1) ?>"
                transform="rotate(-90 24 24)"
                class="si-fill"/>
    </svg>
    <span class="si-value"><?= $sbMid ?></span>
    <?php else: ?>
    <span class="si-empty">—</span>
    <?php endif; ?>
</span>
<style>
.score-inline{display:inline-flex;align-items:center;gap:6px;position:relative}
.score-inline .si-ring{flex-shrink:0}
.score-inline .si-fill{transition:stroke-dashoffset 1.2s cubic-bezier(.4,0,.2,1)}
.score-inline .si-value{font-weight:800;font-size:<?= $sbFontSize ?>;color:#5b28a4}
.score-inline .si-empty{font-size:<?= $sbFontSize ?>;color:#8a8a9a;font-weight:600}
</style>

<?php else: ?>
<!-- ═══ CARD STYLE (default) ═══ -->
<div class="score-badge-card <?= $sbHasData ? '' : 'empty' ?>" id="<?= $sbId ?>">
    <?php if ($sbHasData): ?>
    <div class="sbc-ring">
        <svg viewBox="0 0 128 128" width="<?= $sbRingSize ?>" height="<?= $sbRingSize ?>">
            <circle cx="64" cy="64" r="56" stroke="rgba(91,40,164,.1)" stroke-width="8" fill="none"/>
            <circle cx="64" cy="64" r="56" stroke="#5b28a4" stroke-width="8" fill="none"
                    stroke-linecap="round"
                    stroke-dasharray="<?= round(2 * M_PI * 56, 1) ?>"
                    stroke-dashoffset="<?= round(2 * M_PI * 56 * (1 - $sbPct / 100), 1) ?>"
                    transform="rotate(-90 64 64)"
                    class="sbc-fill"/>
        </svg>
        <div class="sbc-inner">
            <span class="sbc-score"><?= $sbMid ?></span>
            <span class="sbc-label">Predicted</span>
        </div>
    </div>
    <div class="sbc-details">
        <div class="sbc-title">SAT Score Prediction</div>
        <div class="sbc-range"><?= $sbLow ?> – <?= $sbHigh ?> range</div>
        <?php if ($sbMathPred || $sbRwPred): ?>
        <div class="sbc-sections">
            <?php if ($sbMathPred): ?><span class="sbc-sect">Math: <?= $sbMathPred ?></span><?php endif; ?>
            <?php if ($sbRwPred): ?><span class="sbc-sect">R&W: <?= $sbRwPred ?></span><?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="sbc-confidence"><?= $sbConfidence ?>% confidence · <?= $sbPrediction['attempts_used'] ?? '?' ?> tests analyzed</div>
    </div>
    <?php else: ?>
    <div class="sbc-empty-icon">
        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="rgba(91,40,164,.4)" stroke-width="1.5">
            <circle cx="12" cy="12" r="10"/><path d="M12 6v6l3 3"/>
        </svg>
    </div>
    <div class="sbc-details">
        <div class="sbc-title" style="color:#908daa">Score Prediction</div>
        <div class="sbc-range" style="color:#b0adca">Complete a practice test to unlock your predicted SAT score</div>
        <a href="/practice-tests/" class="sbc-cta">Take Practice Test →</a>
    </div>
    <?php endif; ?>
</div>
<style>
.score-badge-card{display:flex;align-items:center;gap:16px;padding:16px 20px;background:linear-gradient(135deg,rgba(91,40,164,.06),rgba(91,40,164,.02));border:1px solid rgba(91,40,164,.12);border-radius:14px;transition:all .2s}
.score-badge-card:hover{border-color:rgba(91,40,164,.25)}
.score-badge-card.empty{border-style:dashed;background:rgba(91,40,164,.02)}
.sbc-ring{position:relative;flex-shrink:0}
.sbc-fill{transition:stroke-dashoffset 1.5s cubic-bezier(.4,0,.2,1)}
.sbc-inner{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center}
.sbc-score{font-size:<?= $sbFontSize ?>;font-weight:800;color:#5b28a4;line-height:1}
.sbc-label{font-size:<?= $sbLabelSize ?>;color:#908daa;font-weight:500;text-transform:uppercase;letter-spacing:.04em}
.sbc-details{flex:1;min-width:0}
.sbc-title{font-size:.85rem;font-weight:700;color:#18182a;margin-bottom:2px}
.sbc-range{font-size:.75rem;color:#908daa;font-family:var(--fm,'DM Mono',monospace)}
.sbc-sections{display:flex;gap:10px;margin-top:4px}
.sbc-sect{font-size:.72rem;font-weight:600;color:#5b28a4;background:rgba(91,40,164,.08);padding:2px 8px;border-radius:4px}
.sbc-confidence{font-size:.68rem;color:#b0adca;margin-top:4px}
.sbc-empty-icon{flex-shrink:0}
.sbc-cta{display:inline-block;margin-top:8px;font-size:.78rem;font-weight:600;color:#5b28a4;text-decoration:none;transition:color .2s}
.sbc-cta:hover{color:#4a1f87}
</style>
<?php endif; ?>
