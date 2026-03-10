<?php
/**
 * /weakness-analyzer/index.php — AI Weakness Analyzer Dashboard
 * my.sat.avidmock.com/weakness-analyzer/
 *
 * Premium "health dashboard for SAT prep" — radar chart, skill cards,
 * AI coaching, action plan, predicted score impact.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/WeaknessAnalyzer.php';

Auth::requireStudent();

$userId    = (int) $_SESSION['user_id'];
$activePage   = 'weakness_analyzer';
$topbarTitle  = 'Skill Map';

/* ── Fetch user info ─────────────────────────────────────────── */
try { $user = User::findById($userId); } catch (\Throwable $e) { $user = []; }
$firstName   = trim($user['first_name'] ?? 'Student');
$targetScore = (int) ($user['target_score'] ?? 1200);

/* ── Run analysis (uses cache if fresh) ──────────────────────── */
try {
    $analysis = WeaknessAnalyzer::analyse($userId);
} catch (\Throwable $e) {
    error_log('weakness-analyzer page: ' . $e->getMessage());
    $analysis = [
        'weak_skills'            => [],
        'strong_skills'          => [],
        'ai_analysis'            => ['coaching_paragraph' => 'Take a few quizzes first so we can map your skills!', 'action_plan' => [], 'skill_tips' => []],
        'recommended_quizzes'    => [],
        'predicted_score_impact' => ['points' => 0, 'description' => ''],
        'domain_scores'          => [],
        'readiness_score'        => 0,
        'total_answers'          => 0,
        'generated_at'           => date('Y-m-d H:i:s'),
    ];
}

$readiness      = (int) ($analysis['readiness_score'] ?? 0);
$weakSkills     = $analysis['weak_skills'] ?? [];
$strongSkills   = $analysis['strong_skills'] ?? [];
$aiAnalysis     = $analysis['ai_analysis'] ?? [];
$actionPlan     = $aiAnalysis['action_plan'] ?? [];
$coachingText   = $aiAnalysis['coaching_paragraph'] ?? '';
$recQuizzes     = $analysis['recommended_quizzes'] ?? [];
$scoreImpact    = $analysis['predicted_score_impact'] ?? ['points' => 0, 'description' => ''];
$domainScores   = $analysis['domain_scores'] ?? [];
$totalAnswers   = (int) ($analysis['total_answers'] ?? 0);
$generatedAt    = $analysis['generated_at'] ?? '';
$isCached       = !empty($analysis['cached']);
$hasData        = $totalAnswers >= 4;

/* ── Domain data for radar chart ─────────────────────────────── */
$radarDomains = [
    ['label' => 'Algebra',            'key' => 'algebra',         'accuracy' => (float)($domainScores['algebra']['accuracy'] ?? 0)],
    ['label' => 'Advanced Math',      'key' => 'advanced_math',   'accuracy' => (float)($domainScores['advanced_math']['accuracy'] ?? 0)],
    ['label' => 'Problem Solving',    'key' => 'problem_solving', 'accuracy' => (float)($domainScores['problem_solving']['accuracy'] ?? 0)],
    ['label' => 'Geometry & Trig',    'key' => 'geometry',        'accuracy' => (float)($domainScores['geometry']['accuracy'] ?? 0)],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Skill Map — Avidmock SAT</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300..800;1,9..40,300..800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ================================================================
   TOKENS
================================================================ */
:root {
    --dk:#143230; --dk2:#1a3f3c; --dk3:#0e2422;
    --ac:#1fe290; --ac2:#17c87a; --ac3:#14a864;
    --tx:#1a1a2e; --tx2:#4a4a5a; --tx3:#8a8a9a;
    --bg:#f7faf9; --bg2:#ffffff; --bd:#e2ebe9;
    --err:#e74c3c; --warn:#f39c12; --ok:#10b981;
    --coral:#ff6b6b; --amber:#ffb347; --purple:#8b5cf6;
    --ff:'DM Sans',-apple-system,BlinkMacSystemFont,sans-serif;
    --fm:'DM Mono',monospace;
    --sidebar-w:260px; --topbar-h:64px;
    --r:16px; --r-sm:12px; --r-xs:8px;
    --shadow-sm:0 1px 3px rgba(20,50,48,.06);
    --shadow-md:0 4px 16px rgba(20,50,48,.08);
    --shadow-lg:0 8px 32px rgba(20,50,48,.10);
    --shadow-glow:0 0 24px rgba(31,226,144,.18);
    --pad:32px; --pad-sm:16px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{font-size:16px;scroll-behavior:smooth;-webkit-font-smoothing:antialiased}
body{font-family:var(--ff);color:var(--tx);background:var(--bg);min-height:100vh;line-height:1.55}
a{color:var(--ac2);text-decoration:none;transition:color .2s}
a:hover{color:var(--dk)}
button{font-family:var(--ff);cursor:pointer}

/* ================================================================
   LAYOUT (sidebar + topbar aware)
================================================================ */
.main-content {
    margin-left: var(--sidebar-w);
    margin-top: var(--topbar-h);
    padding: var(--pad) var(--pad) 100px;
    max-width: 1100px;
}
@media (max-width:900px) {
    .main-content { margin-left:0; padding: var(--pad-sm) var(--pad-sm) 100px; }
}
.sidebar-overlay {
    display:none;position:fixed;inset:0;z-index:998;
    background:rgba(0,0,0,.4);opacity:0;transition:opacity .3s;
}
.sidebar-overlay.show { display:block;opacity:1; }

/* ================================================================
   SCROLL REVEAL
================================================================ */
.sr { opacity:0; transform:translateY(20px); transition:opacity .55s cubic-bezier(.4,0,.2,1),transform .55s cubic-bezier(.4,0,.2,1); }
.sr.vis { opacity:1; transform:translateY(0); }
.d1{transition-delay:.08s}.d2{transition-delay:.16s}.d3{transition-delay:.24s}.d4{transition-delay:.32s}.d5{transition-delay:.4s}

/* ================================================================
   HERO
================================================================ */
.hero {
    position:relative;overflow:hidden;
    background:linear-gradient(135deg,var(--dk) 0%,#1a4a46 50%,#1b5c52 100%);
    border-radius:var(--r);padding:40px 36px 44px;margin-bottom:28px;color:#fff;
}
.hero::before {
    content:'';position:absolute;top:-50%;right:-15%;width:440px;height:440px;
    border-radius:50%;background:radial-gradient(circle,rgba(31,226,144,.15) 0%,transparent 70%);
    pointer-events:none;
}
.hero-inner { position:relative;z-index:1;display:flex;align-items:center;gap:40px;flex-wrap:wrap }
.hero-text { flex:1;min-width:280px }
.hero-eyebrow {
    display:inline-flex;align-items:center;gap:8px;font-size:.82rem;font-weight:600;
    letter-spacing:.05em;text-transform:uppercase;color:var(--ac);margin-bottom:10px;
}
.hero-title { font-size:clamp(1.5rem,3.5vw,2rem);font-weight:700;letter-spacing:-.02em;margin-bottom:6px }
.hero-title em { font-style:normal;color:var(--ac) }
.hero-sub { font-size:.95rem;color:rgba(255,255,255,.65);margin-bottom:20px }
.hero-meta { display:flex;gap:20px;flex-wrap:wrap }
.hero-meta-item { display:flex;align-items:center;gap:6px;font-size:.82rem;color:rgba(255,255,255,.55) }
.hero-meta-item svg { width:14px;height:14px;stroke:var(--ac);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round }
.hero-actions { display:flex;gap:10px;margin-top:20px }
.btn-refresh {
    display:inline-flex;align-items:center;gap:8px;padding:10px 20px;
    border-radius:10px;font-size:.85rem;font-weight:600;border:none;
    background:rgba(255,255,255,.12);color:#fff;backdrop-filter:blur(8px);
    border:1px solid rgba(255,255,255,.15);transition:all .25s;
}
.btn-refresh:hover { background:rgba(255,255,255,.2);transform:translateY(-1px) }
.btn-refresh svg { width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round }
.btn-refresh.loading svg { animation:spin .8s linear infinite }
@keyframes spin { to { transform:rotate(360deg) } }

/* ── Gauge (circular) ───────────────────────────────────── */
.gauge-wrap { flex-shrink:0;width:160px;height:160px;position:relative }
.gauge-svg { width:100%;height:100%;transform:rotate(-90deg) }
.gauge-bg { fill:none;stroke:rgba(255,255,255,.1);stroke-width:10 }
.gauge-fill {
    fill:none;stroke:var(--ac);stroke-width:10;stroke-linecap:round;
    transition:stroke-dashoffset 1.2s cubic-bezier(.4,0,.2,1);
}
.gauge-label {
    position:absolute;inset:0;display:flex;flex-direction:column;
    align-items:center;justify-content:center;
}
.gauge-val { font-size:2.2rem;font-weight:800;color:#fff;line-height:1;font-family:var(--fm) }
.gauge-unit { font-size:.7rem;font-weight:600;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.08em;margin-top:2px }
@media (max-width:600px) { .gauge-wrap{width:120px;height:120px} .gauge-val{font-size:1.6rem} }

/* ================================================================
   SECTION HEADERS
================================================================ */
.sec-hdr { display:flex;align-items:center;gap:10px;margin-bottom:18px }
.sec-icon {
    width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;
    flex-shrink:0;
}
.sec-icon svg { width:18px;height:18px;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;fill:none }
.sec-icon.red   { background:rgba(231,76,60,.1);  } .sec-icon.red svg   { stroke:var(--err) }
.sec-icon.green { background:rgba(31,226,144,.1);  } .sec-icon.green svg { stroke:var(--ac) }
.sec-icon.blue  { background:rgba(74,144,217,.12); } .sec-icon.blue svg  { stroke:#4a90d9 }
.sec-icon.purple{ background:rgba(139,92,246,.1);  } .sec-icon.purple svg{ stroke:var(--purple) }
.sec-icon.amber { background:rgba(255,179,71,.1);  } .sec-icon.amber svg { stroke:var(--amber) }
.sec-title { font-size:1.15rem;font-weight:700;color:var(--tx);letter-spacing:-.01em }
.sec-badge { font-size:.72rem;font-weight:700;padding:3px 10px;border-radius:20px;letter-spacing:.02em }

/* ================================================================
   RADAR CHART
================================================================ */
.radar-section { margin-bottom:32px }
.radar-card {
    background:var(--bg2);border:1px solid var(--bd);border-radius:var(--r);
    padding:28px;box-shadow:var(--shadow-sm);
}
.radar-grid { display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:center }
@media (max-width:700px) { .radar-grid{grid-template-columns:1fr} }
.radar-svg-wrap { display:flex;align-items:center;justify-content:center }
.radar-legend { display:flex;flex-direction:column;gap:14px }
.legend-row {
    display:flex;align-items:center;gap:12px;padding:10px 14px;
    border-radius:var(--r-xs);background:var(--bg);transition:background .2s;
}
.legend-row:hover { background:rgba(31,226,144,.06) }
.legend-dot { width:10px;height:10px;border-radius:50%;flex-shrink:0 }
.legend-label { flex:1;font-size:.88rem;font-weight:600;color:var(--tx) }
.legend-val { font-family:var(--fm);font-size:.88rem;font-weight:600 }
.legend-bar-wrap { flex:1;max-width:120px;height:6px;border-radius:3px;background:var(--bd);overflow:hidden }
.legend-bar { height:100%;border-radius:3px;transition:width .8s cubic-bezier(.4,0,.2,1) }

/* ================================================================
   SKILL CARDS (weak + strong)
================================================================ */
.skills-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-bottom:32px }
.skill-card {
    background:var(--bg2);border:1px solid var(--bd);border-radius:var(--r-sm);
    padding:20px;box-shadow:var(--shadow-sm);transition:transform .2s,box-shadow .2s;
    position:relative;overflow:hidden;
}
.skill-card:hover { transform:translateY(-3px);box-shadow:var(--shadow-md) }
.skill-card::before {
    content:'';position:absolute;top:0;left:0;right:0;height:3px;
    border-radius:var(--r-sm) var(--r-sm) 0 0;
}
.skill-card.weak::before { background:linear-gradient(90deg,var(--coral),var(--amber)) }
.skill-card.strong::before { background:linear-gradient(90deg,var(--ac),var(--ac2)) }
.skill-card-top { display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:10px }
.skill-name { font-size:.92rem;font-weight:700;color:var(--tx);line-height:1.3 }
.skill-domain { font-size:.72rem;font-weight:600;color:var(--tx3);margin-top:2px }
.skill-accuracy {
    font-family:var(--fm);font-size:1.4rem;font-weight:700;line-height:1;
    flex-shrink:0;
}
.skill-accuracy.low  { color:var(--coral) }
.skill-accuracy.mid  { color:var(--amber) }
.skill-accuracy.high { color:var(--ac) }
.skill-meta { display:flex;align-items:center;gap:14px;margin-bottom:10px;font-size:.78rem;color:var(--tx3) }
.skill-meta svg { width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0 }
.skill-meta-item { display:inline-flex;align-items:center;gap:4px }
.trend-up   { color:var(--ac)!important }
.trend-down { color:var(--coral)!important }
.trend-flat { color:var(--tx3)!important }
.skill-bar-wrap { height:5px;border-radius:3px;background:var(--bd);overflow:hidden;margin-bottom:12px }
.skill-bar { height:100%;border-radius:3px;transition:width .8s cubic-bezier(.4,0,.2,1) }
.skill-bar.low  { background:linear-gradient(90deg,var(--coral),var(--amber)) }
.skill-bar.mid  { background:linear-gradient(90deg,var(--amber),#e6c73e) }
.skill-bar.high { background:linear-gradient(90deg,var(--ac2),var(--ac)) }
.skill-tip {
    font-size:.8rem;color:var(--tx2);line-height:1.5;padding:10px 12px;
    background:var(--bg);border-radius:var(--r-xs);border-left:3px solid var(--amber);
}
.skill-tip.strong-tip { border-left-color:var(--ac) }
.skill-actions { margin-top:12px }
.btn-practice {
    display:inline-flex;align-items:center;gap:6px;padding:8px 16px;
    border-radius:8px;font-size:.78rem;font-weight:700;border:none;
    background:linear-gradient(135deg,var(--ac),var(--ac2));color:var(--dk);
    transition:all .2s;box-shadow:0 2px 8px rgba(31,226,144,.2);
}
.btn-practice:hover { transform:translateY(-1px);box-shadow:0 4px 14px rgba(31,226,144,.3) }
.btn-practice svg { width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round }

/* ================================================================
   AI COACH SECTION
================================================================ */
.coach-card {
    background:var(--bg2);border:1px solid var(--bd);border-radius:var(--r);
    padding:28px;box-shadow:var(--shadow-sm);margin-bottom:32px;
    position:relative;overflow:hidden;
}
.coach-card::before {
    content:'';position:absolute;top:0;left:0;right:0;height:3px;
    background:linear-gradient(90deg,var(--ac),var(--purple),var(--ac2));
}
.coach-text {
    font-size:.95rem;line-height:1.7;color:var(--tx);max-width:720px;
}

/* ================================================================
   ACTION PLAN
================================================================ */
.plan-list { display:flex;flex-direction:column;gap:14px;margin-bottom:32px }
.plan-step {
    display:flex;gap:16px;align-items:flex-start;
    background:var(--bg2);border:1px solid var(--bd);border-radius:var(--r-sm);
    padding:20px;box-shadow:var(--shadow-sm);transition:transform .2s,box-shadow .2s;
}
.plan-step:hover { transform:translateY(-2px);box-shadow:var(--shadow-md) }
.plan-num {
    width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;
    font-family:var(--fm);font-size:.88rem;font-weight:700;flex-shrink:0;
    background:linear-gradient(135deg,var(--ac),var(--ac2));color:var(--dk);
}
.plan-body { flex:1;min-width:0 }
.plan-title { font-size:.95rem;font-weight:700;color:var(--tx);margin-bottom:4px }
.plan-desc { font-size:.85rem;color:var(--tx2);line-height:1.55 }
.plan-time {
    display:inline-flex;align-items:center;gap:4px;margin-top:6px;
    font-size:.75rem;font-weight:600;color:var(--ac2);
}
.plan-time svg { width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round }

/* ================================================================
   SCORE IMPACT
================================================================ */
.impact-card {
    background:linear-gradient(135deg,var(--dk) 0%,#1a4a46 100%);
    border-radius:var(--r);padding:32px;color:#fff;margin-bottom:32px;
    display:flex;align-items:center;gap:28px;flex-wrap:wrap;
    box-shadow:var(--shadow-lg);position:relative;overflow:hidden;
}
.impact-card::before {
    content:'';position:absolute;top:-30%;right:-10%;width:260px;height:260px;
    border-radius:50%;background:radial-gradient(circle,rgba(31,226,144,.12) 0%,transparent 70%);
    pointer-events:none;
}
.impact-points {
    font-family:var(--fm);font-size:3rem;font-weight:800;color:var(--ac);line-height:1;
    flex-shrink:0;position:relative;
}
.impact-points-label { font-size:.72rem;font-weight:600;color:rgba(255,255,255,.45);text-transform:uppercase;letter-spacing:.06em;margin-top:4px }
.impact-desc { font-size:.95rem;color:rgba(255,255,255,.75);line-height:1.6;max-width:540px;position:relative }

/* ================================================================
   EMPTY STATE
================================================================ */
.empty-state {
    text-align:center;padding:60px 24px;
    background:var(--bg2);border:2px dashed var(--bd);border-radius:var(--r);
    margin-bottom:32px;
}
.empty-state svg { width:64px;height:64px;stroke:var(--bd);fill:none;stroke-width:1.5;margin-bottom:16px }
.empty-state h3 { font-size:1.1rem;font-weight:700;color:var(--tx);margin-bottom:8px }
.empty-state p { font-size:.9rem;color:var(--tx3);max-width:400px;margin:0 auto 20px }
.btn-primary {
    display:inline-flex;align-items:center;gap:8px;padding:12px 24px;
    border-radius:10px;font-size:.9rem;font-weight:700;border:none;
    background:linear-gradient(135deg,var(--ac),var(--ac2));color:var(--dk);
    box-shadow:0 4px 16px rgba(31,226,144,.25);transition:all .25s;
}
.btn-primary:hover { transform:translateY(-2px);box-shadow:0 6px 24px rgba(31,226,144,.35) }
.btn-primary svg { width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round }

/* ================================================================
   ANIMATIONS
================================================================ */
@keyframes fadeInUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
@keyframes gaugeGrow {
    0% { stroke-dashoffset: var(--circumference); }
    100% { stroke-dashoffset: var(--target-offset); }
}
@keyframes shimmer {
    0% { background-position:-200% 0 }
    100% { background-position:200% 0 }
}
@keyframes pulse-glow {
    0%,100% { box-shadow:0 0 0 0 rgba(31,226,144,.3) }
    50%     { box-shadow:0 0 0 10px rgba(31,226,144,0) }
}

/* ================================================================
   RESPONSIVE
================================================================ */
@media (max-width:600px) {
    .hero-inner { gap:20px }
    .hero { padding:28px 20px 32px }
    .radar-grid { gap:16px }
    .skills-grid { grid-template-columns:1fr }
    .impact-card { padding:24px;gap:16px }
    .impact-points { font-size:2.2rem }
}

/* Safe area */
@supports (padding:max(0px)) {
    .main-content {
        padding-left:max(var(--pad-sm),env(safe-area-inset-left));
        padding-right:max(var(--pad-sm),env(safe-area-inset-right));
        padding-bottom:max(100px,calc(100px + env(safe-area-inset-bottom)));
    }
    @media (min-width:901px) {
        .main-content {
            padding-left:max(var(--pad),env(safe-area-inset-left));
            padding-right:max(var(--pad),env(safe-area-inset-right));
        }
    }
}
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/sidebar.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/topbar.php';
?>

<main class="main-content" id="mainContent">

<?php
/* ── Gauge calculations ────────────────────────────────────── */
$circumference = 2 * M_PI * 62; // radius=62
$gaugeOffset   = $circumference - ($circumference * $readiness / 100);
?>

<!-- ════════════════════════════════════════════════════════════
     HERO  — "Your Personalised Skill Map"
═════════════════════════════════════════════════════════════ -->
<section class="hero sr">
    <div class="hero-inner">
        <div class="hero-text">
            <div class="hero-eyebrow">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                AI-Powered Analysis
            </div>
            <h1 class="hero-title">Your Personalised <em>Skill Map</em></h1>
            <p class="hero-sub"><?php if ($hasData): ?>Based on <?= number_format($totalAnswers) ?> answers across all your quizzes<?php else: ?>Take a few quizzes to unlock your personalised skill analysis<?php endif; ?></p>
            <div class="hero-meta">
                <?php if ($generatedAt): ?>
                <div class="hero-meta-item">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    Updated <?= date('M j, g:i A', strtotime($generatedAt)) ?>
                </div>
                <?php endif; ?>
                <div class="hero-meta-item">
                    <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    Target: <?= number_format($targetScore) ?>
                </div>
            </div>
            <div class="hero-actions">
                <button class="btn-refresh" id="refreshBtn" onclick="refreshAnalysis()">
                    <svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/></svg>
                    Refresh Analysis
                </button>
            </div>
        </div>

        <!-- Readiness Gauge -->
        <div class="gauge-wrap">
            <svg class="gauge-svg" viewBox="0 0 140 140">
                <circle class="gauge-bg" cx="70" cy="70" r="62"/>
                <circle class="gauge-fill" cx="70" cy="70" r="62"
                    stroke-dasharray="<?= round($circumference, 2) ?>"
                    stroke-dashoffset="<?= round($gaugeOffset, 2) ?>"
                    style="--circumference:<?= round($circumference, 2) ?>;--target-offset:<?= round($gaugeOffset, 2) ?>"
                />
            </svg>
            <div class="gauge-label">
                <span class="gauge-val"><?= $readiness ?></span>
                <span class="gauge-unit">Readiness</span>
            </div>
        </div>
    </div>
</section>

<?php if (!$hasData): ?>
<!-- ═══ EMPTY STATE ════════════════════════════════════════ -->
<div class="empty-state sr d1">
    <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
        <polyline points="14 2 14 8 20 8"/>
        <line x1="16" y1="13" x2="8" y2="13"/>
        <line x1="16" y1="17" x2="8" y2="17"/>
        <polyline points="10 9 9 9 8 9"/>
    </svg>
    <h3>Not Enough Data Yet</h3>
    <p>Complete at least a couple of quizzes so we can analyse your strengths and weaknesses across all SAT Math domains.</p>
    <a href="/learn/" class="btn-primary">
        <svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
        Start Practicing
    </a>
</div>

<?php else: ?>

<!-- ════════════════════════════════════════════════════════════
     RADAR CHART  — 4 SAT Math Domains
═════════════════════════════════════════════════════════════ -->
<section class="radar-section sr d1">
    <div class="sec-hdr">
        <div class="sec-icon blue">
            <svg viewBox="0 0 24 24"><polygon points="12 2 22 8.5 22 15.5 12 22 2 15.5 2 8.5 12 2"/></svg>
        </div>
        <span class="sec-title">Domain Breakdown</span>
    </div>
    <div class="radar-card">
        <div class="radar-grid">
            <!-- SVG Radar -->
            <div class="radar-svg-wrap">
                <?php
                $cx = 150; $cy = 150; $maxR = 110;
                $n = count($radarDomains);
                $angles = [];
                for ($i = 0; $i < $n; $i++) {
                    $angles[] = ($i * 2 * M_PI / $n) - M_PI / 2; // start from top
                }

                /* Build the data polygon points */
                $dataPoints = [];
                foreach ($radarDomains as $i => $d) {
                    $r = ($d['accuracy'] / 100) * $maxR;
                    $x = $cx + $r * cos($angles[$i]);
                    $y = $cy + $r * sin($angles[$i]);
                    $dataPoints[] = round($x, 1) . ',' . round($y, 1);
                }
                $dataPolyStr = implode(' ', $dataPoints);
                ?>
                <svg viewBox="0 0 300 300" width="280" height="280" style="max-width:100%">
                    <!-- Grid rings -->
                    <?php foreach ([25, 50, 75, 100] as $pct): ?>
                    <?php
                    $rr = ($pct / 100) * $maxR;
                    $ringPts = [];
                    for ($i = 0; $i < $n; $i++) {
                        $rx = $cx + $rr * cos($angles[$i]);
                        $ry = $cy + $rr * sin($angles[$i]);
                        $ringPts[] = round($rx, 1) . ',' . round($ry, 1);
                    }
                    ?>
                    <polygon points="<?= implode(' ', $ringPts) ?>" fill="none" stroke="#e2ebe9" stroke-width="1" opacity=".6"/>
                    <?php endforeach; ?>

                    <!-- Axis lines -->
                    <?php for ($i = 0; $i < $n; $i++): ?>
                    <?php
                    $lx = $cx + $maxR * cos($angles[$i]);
                    $ly = $cy + $maxR * sin($angles[$i]);
                    ?>
                    <line x1="<?= $cx ?>" y1="<?= $cy ?>" x2="<?= round($lx, 1) ?>" y2="<?= round($ly, 1) ?>" stroke="#e2ebe9" stroke-width="1" opacity=".5"/>
                    <?php endfor; ?>

                    <!-- Data polygon -->
                    <polygon points="<?= $dataPolyStr ?>" fill="rgba(31,226,144,.15)" stroke="#1fe290" stroke-width="2.5" stroke-linejoin="round"/>

                    <!-- Data points + labels -->
                    <?php foreach ($radarDomains as $i => $d): ?>
                    <?php
                    $r = ($d['accuracy'] / 100) * $maxR;
                    $px = $cx + $r * cos($angles[$i]);
                    $py = $cy + $r * sin($angles[$i]);
                    $lbx = $cx + ($maxR + 24) * cos($angles[$i]);
                    $lby = $cy + ($maxR + 24) * sin($angles[$i]);
                    $anchor = 'middle';
                    if ($angles[$i] > 0.1 && $angles[$i] < M_PI - 0.1) $anchor = 'start';
                    elseif ($angles[$i] > M_PI + 0.1) $anchor = 'end';
                    ?>
                    <circle cx="<?= round($px, 1) ?>" cy="<?= round($py, 1) ?>" r="5" fill="#1fe290" stroke="#fff" stroke-width="2"/>
                    <text x="<?= round($lbx, 1) ?>" y="<?= round($lby, 1) ?>" text-anchor="<?= $anchor ?>" dominant-baseline="middle"
                          font-family="DM Sans,sans-serif" font-size="11" font-weight="600" fill="#4a4a5a"><?= e($d['label']) ?></text>
                    <?php endforeach; ?>

                    <!-- Center percentage labels on rings -->
                    <?php foreach ([25, 50, 75, 100] as $pct): ?>
                    <?php $rr = ($pct / 100) * $maxR; ?>
                    <text x="<?= $cx + 4 ?>" y="<?= $cy - $rr - 3 ?>" font-size="9" fill="#8a8a9a" font-family="DM Mono,monospace" text-anchor="start"><?= $pct ?>%</text>
                    <?php endforeach; ?>
                </svg>
            </div>

            <!-- Legend bars -->
            <div class="radar-legend">
                <?php foreach ($radarDomains as $d):
                    $acc = $d['accuracy'];
                    $color = $acc >= 75 ? 'var(--ac)' : ($acc >= 50 ? 'var(--amber)' : 'var(--coral)');
                ?>
                <div class="legend-row">
                    <div class="legend-dot" style="background:<?= $color ?>"></div>
                    <span class="legend-label"><?= e($d['label']) ?></span>
                    <div class="legend-bar-wrap">
                        <div class="legend-bar" style="width:<?= $acc ?>%;background:<?= $color ?>"></div>
                    </div>
                    <span class="legend-val" style="color:<?= $color ?>"><?= round($acc) ?>%</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- ════════════════════════════════════════════════════════════
     WEAK SPOTS
═════════════════════════════════════════════════════════════ -->
<?php if (!empty($weakSkills)): ?>
<section class="sr d2">
    <div class="sec-hdr">
        <div class="sec-icon red">
            <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <span class="sec-title">Weak Spots</span>
        <span class="sec-badge" style="background:rgba(231,76,60,.1);color:var(--err)"><?= count($weakSkills) ?> skill<?= count($weakSkills) !== 1 ? 's' : '' ?></span>
    </div>
    <div class="skills-grid">
        <?php foreach ($weakSkills as $idx => $skill):
            $acc = $skill['accuracy'];
            $accClass = $acc < 40 ? 'low' : ($acc < 60 ? 'mid' : 'mid');
            $barClass = $acc < 40 ? 'low' : ($acc < 60 ? 'mid' : 'mid');
            $trend = $skill['trend'];
            $trendClass = $trend === 'improving' ? 'trend-up' : ($trend === 'declining' ? 'trend-down' : 'trend-flat');
            $trendArrow = $trend === 'improving' ? '&#9650;' : ($trend === 'declining' ? '&#9660;' : '&#9644;');
            $trendLabel = ucfirst($trend);
        ?>
        <div class="skill-card weak" style="animation:fadeInUp .5s <?= $idx * 0.06 ?>s both">
            <div class="skill-card-top">
                <div>
                    <div class="skill-name"><?= e($skill['skill_label']) ?></div>
                    <div class="skill-domain"><?= e($skill['domain_label']) ?></div>
                </div>
                <div class="skill-accuracy <?= $accClass ?>"><?= round($acc) ?>%</div>
            </div>
            <div class="skill-meta">
                <span class="skill-meta-item <?= $trendClass ?>">
                    <span><?= $trendArrow ?></span> <?= $trendLabel ?>
                </span>
                <span class="skill-meta-item">
                    <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/></svg>
                    <?= $skill['total'] ?> attempts
                </span>
                <?php if ($skill['avg_time'] > 0): ?>
                <span class="skill-meta-item">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <?= $skill['avg_time'] ?>s avg
                </span>
                <?php endif; ?>
            </div>
            <div class="skill-bar-wrap">
                <div class="skill-bar <?= $barClass ?>" style="width:<?= $acc ?>%"></div>
            </div>
            <?php if (!empty($skill['tip'])): ?>
            <div class="skill-tip"><?= e($skill['tip']) ?></div>
            <?php endif; ?>
            <div class="skill-actions">
                <a href="/learn/?focus=<?= urlencode($skill['domain']) ?>" class="btn-practice">
                    <svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    Practice Now
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ════════════════════════════════════════════════════════════
     STRONG SKILLS
═════════════════════════════════════════════════════════════ -->
<?php if (!empty($strongSkills)): ?>
<section class="sr d3">
    <div class="sec-hdr">
        <div class="sec-icon green">
            <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <span class="sec-title">Strong Skills</span>
        <span class="sec-badge" style="background:rgba(31,226,144,.1);color:var(--ac2)"><?= count($strongSkills) ?> skill<?= count($strongSkills) !== 1 ? 's' : '' ?></span>
    </div>
    <div class="skills-grid">
        <?php foreach ($strongSkills as $idx => $skill):
            $acc = $skill['accuracy'];
        ?>
        <div class="skill-card strong" style="animation:fadeInUp .5s <?= $idx * 0.06 ?>s both">
            <div class="skill-card-top">
                <div>
                    <div class="skill-name"><?= e($skill['skill_label']) ?></div>
                    <div class="skill-domain"><?= e($skill['domain_label']) ?></div>
                </div>
                <div class="skill-accuracy high"><?= round($acc) ?>%</div>
            </div>
            <div class="skill-meta">
                <span class="skill-meta-item trend-up">
                    <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    Mastered
                </span>
                <span class="skill-meta-item">
                    <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/></svg>
                    <?= $skill['total'] ?> attempts
                </span>
            </div>
            <div class="skill-bar-wrap">
                <div class="skill-bar high" style="width:<?= $acc ?>%"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ════════════════════════════════════════════════════════════
     AI COACH ANALYSIS
═════════════════════════════════════════════════════════════ -->
<?php if (!empty($coachingText)): ?>
<section class="sr d3">
    <div class="sec-hdr">
        <div class="sec-icon purple">
            <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
        </div>
        <span class="sec-title">AI Coach Analysis</span>
    </div>
    <div class="coach-card">
        <div class="coach-text"><?= nl2br(e($coachingText)) ?></div>
    </div>
</section>
<?php endif; ?>

<!-- ════════════════════════════════════════════════════════════
     RECOMMENDED ACTION PLAN
═════════════════════════════════════════════════════════════ -->
<?php if (!empty($actionPlan)): ?>
<section class="sr d4">
    <div class="sec-hdr">
        <div class="sec-icon amber">
            <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
        </div>
        <span class="sec-title">Recommended Action Plan</span>
    </div>
    <div class="plan-list">
        <?php foreach ($actionPlan as $step): ?>
        <div class="plan-step">
            <div class="plan-num"><?= (int)($step['step'] ?? 0) ?></div>
            <div class="plan-body">
                <div class="plan-title"><?= e($step['title'] ?? '') ?></div>
                <div class="plan-desc"><?= e($step['description'] ?? '') ?></div>
                <?php if (!empty($step['time_estimate'])): ?>
                <div class="plan-time">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <?= e($step['time_estimate']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ════════════════════════════════════════════════════════════
     SCORE IMPACT
═════════════════════════════════════════════════════════════ -->
<?php if ($scoreImpact['points'] > 0): ?>
<section class="sr d5">
    <div class="sec-hdr">
        <div class="sec-icon green">
            <svg viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
        </div>
        <span class="sec-title">Score Impact Potential</span>
    </div>
    <div class="impact-card">
        <div>
            <div class="impact-points">+<?= $scoreImpact['points'] ?></div>
            <div class="impact-points-label">Potential Points</div>
        </div>
        <div class="impact-desc"><?= e($scoreImpact['description']) ?></div>
    </div>
</section>
<?php endif; ?>

<?php endif; /* end $hasData */ ?>

</main>

<!-- ══════════════════════════════════════════════════════════
     JAVASCRIPT
═══════════════════════════════════════════════════════════ -->
<script>
/* ── Scroll reveal ──────────────────────────────────────── */
(function(){
    const els = document.querySelectorAll('.sr');
    if (!els.length) return;
    const io = new IntersectionObserver((entries)=>{
        entries.forEach(e=>{ if(e.isIntersecting){ e.target.classList.add('vis'); io.unobserve(e.target); }});
    },{threshold:0.08});
    els.forEach(el=>io.observe(el));
})();

/* ── Refresh analysis ───────────────────────────────────── */
function refreshAnalysis(){
    const btn = document.getElementById('refreshBtn');
    if(!btn) return;
    btn.classList.add('loading');
    btn.disabled = true;

    fetch('/api/get-weakness-analysis.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'refresh'})
    })
    .then(r=>r.json())
    .then(d=>{
        if(d.success) {
            window.location.reload();
        } else {
            alert('Could not refresh: ' + (d.error||'Unknown error'));
            btn.classList.remove('loading');
            btn.disabled = false;
        }
    })
    .catch(()=>{
        alert('Network error. Please try again.');
        btn.classList.remove('loading');
        btn.disabled = false;
    });
}
</script>
</body>
</html>