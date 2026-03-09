<?php
/**
 * /ai-tutor/strategy-advisor.php — Pre-Test Strategy Advisor
 *
 * Provides personalised time management, section strategies, and test-day
 * advice based on the student's performance profile and upcoming test date.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/StudyStreak.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/CategoryPerformance.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/QuizAttempt.php';

Auth::requireStudent();
$userId    = $_SESSION['user_id'];
$user      = User::findById($userId);
$firstName = explode(' ', $user['name'] ?? 'Student')[0];
$currentStreak = StudyStreak::getCurrent($userId);

/* Profile data */
$targetScore = (int)($user['target_score'] ?? 1200);
$testDate    = $user['test_date'] ?? null;
$daysUntilTest = $testDate && strtotime($testDate) > time()
    ? (int)ceil((strtotime($testDate) - time()) / 86400) : null;

$mathPerf  = CategoryPerformance::get($userId, 'math') ?? [];
$rwPerf    = CategoryPerformance::get($userId, 'reading_writing') ?? [];
$mathAcc   = (int)($mathPerf['avg_score'] ?? 0);
$rwAcc     = (int)($rwPerf['avg_score'] ?? 0);

$weakMathTopics = CategoryPerformance::getWeakTopics($userId, 'math', 4) ?? [];
$weakRWTopics   = CategoryPerformance::getWeakTopics($userId, 'reading_writing', 4) ?? [];

$recentScores = QuizAttempt::getScoreHistory($userId, 5) ?? [];
$bestScore    = max(array_column($recentScores, 'score') ?: [0]);
$avgScore     = count($recentScores) > 0
    ? round(array_sum(array_column($recentScores, 'score')) / count($recentScores)) : 0;

/* Score tier for tailored advice */
if ($avgScore >= 1400) $tier = 'advanced';
elseif ($avgScore >= 1100) $tier = 'intermediate';
else $tier = 'foundational';

/* Predefined strategy topics */
$strategyTopics = [
    [
        'id'    => 'time-management',
        'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        'title' => 'Time Management',
        'sub'   => 'Per-question pacing for both sections',
        'color' => 'warn',
    ],
    [
        'id'    => 'math-strategy',
        'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19l8-14 8 14H4z"/></svg>',
        'title' => 'Math Section Strategy',
        'sub'   => 'Module 1 vs Module 2, calculator use, skipping',
        'color' => 'dk',
    ],
    [
        'id'    => 'reading-strategy',
        'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
        'title' => 'R&amp;W Section Strategy',
        'sub'   => 'Reading approach, question order, elimination',
        'color' => 'ac',
    ],
    [
        'id'    => 'test-day',
        'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
        'title' => 'Test Day Preparation',
        'sub'   => 'Night before, morning of, what to bring',
        'color' => 'ok',
    ],
    [
        'id'    => 'process-of-elimination',
        'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>',
        'title' => 'Process of Elimination',
        'sub'   => 'Eliminating wrong answers with confidence',
        'color' => 'err',
    ],
    [
        'id'    => 'mental-preparation',
        'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 110 20A10 10 0 0112 2z"/><path d="M12 6v6l4 2"/></svg>',
        'title' => 'Mental Preparation',
        'sub'   => 'Managing anxiety, staying focused, mindset',
        'color' => 'warn',
    ],
];

$colorMap = [
    'warn' => ['bg'=>'rgba(245,158,11,.1)','border'=>'rgba(245,158,11,.2)','text'=>'#d97706','stroke'=>'var(--warn)'],
    'dk'   => ['bg'=>'rgba(20,50,48,.07)','border'=>'rgba(20,50,48,.15)','text'=>'var(--dk)','stroke'=>'var(--dk)'],
    'ac'   => ['bg'=>'rgba(31,226,144,.1)','border'=>'rgba(31,226,144,.2)','text'=>'var(--ac2)','stroke'=>'var(--ac)'],
    'ok'   => ['bg'=>'rgba(16,185,129,.1)','border'=>'rgba(16,185,129,.2)','text'=>'#059669','stroke'=>'var(--ok)'],
    'err'  => ['bg'=>'rgba(239,68,68,.08)','border'=>'rgba(239,68,68,.15)','text'=>'var(--err)','stroke'=>'var(--err)'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Strategy Advisor — AI Tutor — Avidmock SAT</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="192x192" href="/android-chrome-192x192.png">
<link rel="icon" type="image/png" sizes="512x512" href="/android-chrome-512x512.png">
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800;1,9..40,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/KaTeX/0.16.9/katex.min.css">
<style>
:root{
    --dk:#143230;--dk2:#1b403d;--ac:#1fe290;--ac2:#15c87a;
    --tx:#111827;--tx2:#374151;--tx3:#9ca3af;
    --bg:#f4f7f6;--bg2:#edf2f0;--bd:#e2eae8;--white:#ffffff;
    --err:#ef4444;--ok:#10b981;--warn:#f59e0b;
    --ff:'DM Sans',-apple-system,BlinkMacSystemFont,sans-serif;
    --sidebar-w:260px;--topbar-h:64px;--r:14px;--r-sm:10px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{font-size:16px;scroll-behavior:smooth}
body{font-family:var(--ff);-webkit-font-smoothing:antialiased;background:var(--bg);color:var(--tx);min-height:100vh}
a{text-decoration:none;color:inherit}
button{font-family:var(--ff);cursor:pointer}
.sr{opacity:0;transform:translateY(18px);transition:opacity .5s cubic-bezier(.16,1,.3,1),transform .5s cubic-bezier(.16,1,.3,1)}
.sr.visible{opacity:1;transform:none}
.d1{transition-delay:.06s}.d2{transition-delay:.12s}.d3{transition-delay:.18s}.d4{transition-delay:.24s}

/* SIDEBAR */
.sidebar{position:fixed;top:0;left:0;width:var(--sidebar-w);height:100vh;background:var(--dk);display:flex;flex-direction:column;overflow-y:auto;z-index:200;transition:transform .32s cubic-bezier(.16,1,.3,1)}
.sidebar::-webkit-scrollbar{width:3px}
.sidebar::-webkit-scrollbar-thumb{background:rgba(255,255,255,.08)}
.sb-brand{display:flex;align-items:center;gap:10px;padding:0 20px;height:var(--topbar-h);border-bottom:1px solid rgba(255,255,255,.06);flex-shrink:0}
.sb-brand-name{font-size:.9375rem;font-weight:800;color:#fff;letter-spacing:-.02em}
.sb-brand-dot{margin-left:auto;width:7px;height:7px;border-radius:50%;background:var(--ac);animation:sbDot 2.5s ease-in-out infinite}
@keyframes sbDot{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.3;transform:scale(.5)}}
.sb-section{padding:18px 20px 6px;font-size:.625rem;font-weight:700;color:rgba(255,255,255,.2);text-transform:uppercase;letter-spacing:.8px}
.sb-nav{flex:1;padding-bottom:8px}
.sb-link{display:flex;align-items:center;gap:10px;margin:1px 8px;padding:9px 12px;border-radius:9px;font-size:.875rem;font-weight:600;color:rgba(255,255,255,.45);transition:all .18s;position:relative}
.sb-link:hover{background:rgba(255,255,255,.06);color:rgba(255,255,255,.8)}
.sb-link.active{background:rgba(31,226,144,.1);color:var(--ac)}
.sb-link.active::before{content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);width:3px;height:55%;background:var(--ac);border-radius:0 2px 2px 0}
.sb-ico{width:17px;height:17px;flex-shrink:0;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
.sb-user{margin:8px;padding:11px 13px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);border-radius:11px;display:flex;align-items:center;gap:10px;transition:background .18s}
.sb-user:hover{background:rgba(255,255,255,.07)}
.sb-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--ac),#0da367);display:flex;align-items:center;justify-content:center;font-size:.8125rem;font-weight:800;color:var(--dk);flex-shrink:0}
.sb-user-name{font-size:.8125rem;font-weight:700;color:#fff}
.sb-user-meta{font-size:.6875rem;color:rgba(255,255,255,.28)}

/* TOPBAR */
.topbar{position:fixed;top:0;left:var(--sidebar-w);right:0;height:var(--topbar-h);background:rgba(244,247,246,.95);backdrop-filter:blur(10px);border-bottom:1px solid var(--bd);display:flex;align-items:center;padding:0 28px;gap:14px;z-index:100}
.tb-title{flex:1}
.tb-title h1{font-size:.9375rem;font-weight:700;color:var(--tx);letter-spacing:-.015em}
.tb-title p{font-size:.75rem;color:var(--tx3)}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:var(--r-sm);font-size:.8125rem;font-weight:700;border:none;cursor:pointer;transition:all .2s;text-decoration:none}
.btn svg{width:14px;height:14px;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0}
.btn-dark{background:var(--dk);color:#fff}.btn-dark svg{stroke:var(--ac)}.btn-dark:hover{background:var(--dk2);transform:translateY(-1px)}
.btn-mint{background:var(--ac);color:var(--dk)}.btn-mint svg{stroke:var(--dk)}.btn-mint:hover{background:var(--ac2);transform:translateY(-1px);box-shadow:0 4px 14px rgba(31,226,144,.28)}
.ham-btn{display:none;width:38px;height:38px;border-radius:var(--r-sm);border:1.5px solid var(--bd);background:var(--white);flex-direction:column;align-items:center;justify-content:center;gap:4px;padding:10px}
.ham-btn span{display:block;height:2px;width:100%;background:var(--tx);border-radius:1px}
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:190;opacity:0;transition:opacity .28s;pointer-events:none}
.sidebar-overlay.show{opacity:1;pointer-events:all}

/* MAIN */
.main{margin-left:var(--sidebar-w);margin-top:var(--topbar-h);padding:32px 28px 80px}

/* PAGE HEADER CARD */
.page-hero{
    background:linear-gradient(135deg,var(--dk),#1c4a47);
    border-radius:var(--r);padding:28px 32px;margin-bottom:24px;
    display:flex;align-items:center;gap:24px;flex-wrap:wrap;
    position:relative;overflow:hidden;
}
.page-hero::before{content:'';position:absolute;top:-40px;right:-40px;width:250px;height:250px;border-radius:50%;background:radial-gradient(circle,rgba(31,226,144,.07),transparent 65%);pointer-events:none}
.hero-ico{width:56px;height:56px;border-radius:15px;background:rgba(31,226,144,.1);border:1px solid rgba(31,226,144,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.hero-ico svg{width:24px;height:24px;stroke:var(--ac);fill:none;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}
.hero-info{flex:1}
.hero-title{font-size:1.375rem;font-weight:800;color:#fff;letter-spacing:-.025em;margin-bottom:5px}
.hero-sub{font-size:.875rem;color:rgba(255,255,255,.45);line-height:1.55}
.hero-stats{display:flex;gap:12px;margin-top:14px;flex-wrap:wrap}
.hero-stat{padding:7px 14px;border-radius:8px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);font-size:.75rem;font-weight:700;color:rgba(255,255,255,.6)}
.hero-stat strong{color:#fff}
.hero-stat.ac-stat{background:rgba(31,226,144,.1);border-color:rgba(31,226,144,.2);color:var(--ac)}

<?php if ($daysUntilTest): ?>
.countdown{
    flex-shrink:0;text-align:center;
    padding:16px 20px;background:rgba(31,226,144,.08);border:1px solid rgba(31,226,144,.18);border-radius:12px;
}
.countdown-num{font-size:2.5rem;font-weight:800;color:var(--ac);letter-spacing:-.05em;line-height:1}
.countdown-label{font-size:.6875rem;font-weight:700;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.5px;margin-top:2px}
<?php endif; ?>

/* PROFILE SECTION */
.profile-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px}
.card{background:var(--white);border:1px solid var(--bd);border-radius:var(--r);padding:20px;transition:box-shadow .25s}
.card:hover{box-shadow:0 4px 20px rgba(20,50,48,.06)}
.card-label{font-size:.6875rem;font-weight:700;color:var(--tx3);text-transform:uppercase;letter-spacing:.6px;margin-bottom:14px}
.acc-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
.acc-name{font-size:.875rem;font-weight:600;color:var(--tx2)}
.acc-pct{font-size:.875rem;font-weight:800;color:var(--tx)}
.acc-bar{height:5px;background:var(--bg2);border-radius:3px;overflow:hidden;margin-bottom:4px}
.acc-bar-fill{height:100%;border-radius:3px;transition:width 1.2s cubic-bezier(.16,1,.3,1)}

/* STRATEGY TOPICS GRID */
.strategy-section-title{
    font-size:1.125rem;font-weight:800;color:var(--tx);letter-spacing:-.02em;
    margin-bottom:16px;margin-top:8px;
}
.strategy-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:28px}
.strategy-card{
    background:var(--white);border:1.5px solid var(--bd);border-radius:var(--r);
    padding:18px;cursor:pointer;
    transition:all .25s cubic-bezier(.16,1,.3,1);
    text-align:left;
}
.strategy-card:hover{transform:translateY(-3px);box-shadow:0 8px 28px rgba(20,50,48,.08)}
.strategy-card.active{border-color:var(--ac);background:rgba(31,226,144,.02)}
.sc-ico{
    width:40px;height:40px;border-radius:10px;
    display:flex;align-items:center;justify-content:center;margin-bottom:12px;
}
.sc-ico svg{width:18px;height:18px}
.sc-title{font-size:.9375rem;font-weight:700;color:var(--tx);letter-spacing:-.015em;margin-bottom:4px}
.sc-sub{font-size:.75rem;color:var(--tx3);line-height:1.5}

/* RESPONSE PANEL */
.response-panel{
    background:var(--dk);border-radius:var(--r);
    overflow:hidden;margin-bottom:20px;
}
.rp-header{
    padding:16px 24px;border-bottom:1px solid rgba(255,255,255,.07);
    display:flex;align-items:center;justify-content:space-between;gap:12px;
}
.rp-topic{font-size:.9375rem;font-weight:700;color:#fff}
.rp-close{
    width:28px;height:28px;border-radius:7px;
    background:rgba(255,255,255,.07);border:none;
    display:flex;align-items:center;justify-content:center;
    color:rgba(255,255,255,.4);cursor:pointer;transition:all .18s;
}
.rp-close:hover{background:rgba(255,255,255,.12);color:#fff}
.rp-close svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.rp-body{padding:24px;min-height:160px}
.rp-content{font-size:.9375rem;color:rgba(255,255,255,.78);line-height:1.75}
.rp-content h3{font-size:1rem;font-weight:700;color:#fff;margin:16px 0 8px}
.rp-content h3:first-child{margin-top:0}
.rp-content p{margin-bottom:10px}
.rp-content ul,.rp-content ol{padding-left:20px;margin-bottom:10px}
.rp-content li{margin-bottom:5px}
.rp-content strong{color:#fff;font-weight:700}
.rp-content em{font-style:italic;color:var(--ac)}
.rp-content blockquote{
    border-left:3px solid var(--ac);padding:8px 14px;margin:10px 0;
    background:rgba(31,226,144,.05);border-radius:0 8px 8px 0;color:rgba(255,255,255,.6);font-style:italic;
}
.rp-content .tip-box{
    background:rgba(31,226,144,.08);border:1px solid rgba(31,226,144,.15);border-radius:10px;padding:12px 16px;margin:12px 0;
}
.rp-footer{
    padding:14px 24px;border-top:1px solid rgba(255,255,255,.06);
    display:flex;gap:8px;
}
.rp-btn{
    display:inline-flex;align-items:center;gap:5px;
    padding:7px 14px;border-radius:8px;
    background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);
    font-size:.75rem;font-weight:700;color:rgba(255,255,255,.6);
    transition:all .18s;
}
.rp-btn:hover{background:rgba(31,226,144,.1);border-color:rgba(31,226,144,.2);color:var(--ac)}
.rp-btn svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}

/* LOADING */
.rp-loading{display:flex;flex-direction:column;align-items:center;gap:12px;padding:32px 20px}
.rp-loading-dots{display:flex;gap:5px}
.rp-ld{width:7px;height:7px;border-radius:50%;background:rgba(31,226,144,.4);animation:ld 1.4s ease-in-out infinite}
.rp-ld:nth-child(2){animation-delay:.2s}.rp-ld:nth-child(3){animation-delay:.4s}
@keyframes ld{0%,60%,100%{transform:scale(.6);opacity:.4}30%{transform:scale(1);opacity:1}}
.rp-loading-text{font-size:.8125rem;color:rgba(255,255,255,.35)}

/* QUICK CHAT */
.quick-chat{
    background:var(--white);border:1px solid var(--bd);border-radius:var(--r);padding:20px;margin-bottom:20px;
}
.qc-label{font-size:.6875rem;font-weight:700;color:var(--tx3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px}
.qc-input-wrap{display:flex;gap:10px}
.qc-textarea{
    flex:1;padding:11px 14px;border:1.5px solid var(--bd);border-radius:var(--r-sm);
    font-family:var(--ff);font-size:.875rem;color:var(--tx);resize:none;outline:none;
    transition:border-color .18s;line-height:1.5;
}
.qc-textarea:focus{border-color:var(--ac)}
.qc-send{
    align-self:flex-end;padding:11px 18px;border-radius:var(--r-sm);
    background:var(--dk);color:var(--ac);font-size:.8125rem;font-weight:700;border:none;
    transition:all .2s;white-space:nowrap;
}
.qc-send:hover{background:var(--ac);color:var(--dk)}

/* RESPONSIVE */
@media(max-width:900px){
    .sidebar{position:fixed;transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}
    .sidebar-overlay{display:block}.main{margin-left:0;padding:24px 16px 80px}
    .topbar{left:0;padding:0 16px}.ham-btn{display:flex}
}
@media(max-width:768px){
    .strategy-grid{grid-template-columns:1fr 1fr}.profile-grid{grid-template-columns:1fr}
}
@media(max-width:480px){
    .strategy-grid{grid-template-columns:1fr}.page-hero{padding:20px}
    .countdown{display:none}
}
</style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <a href="/" class="sb-brand">
        <img src="/assets/images/logos/avidmock-logo-white.svg" alt="Avidmock" onerror="this.style.display='none'">
        <span class="sb-brand-name">Avidmock SAT</span>
        <span class="sb-brand-dot"></span>
    </a>
    <nav class="sb-nav">
        <div class="sb-section">Main</div>
        <a href="/" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>Dashboard</a>
        <div class="sb-section">Tools</div>
        <a href="/ai-tutor/" class="sb-link active"><svg class="sb-ico" viewBox="0 0 24 24"><path d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>AI Tutor</a>
        <a href="/math/" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><path d="M4 19l8-14 8 14H4z"/></svg>Math</a>
        <a href="/reading/" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>Reading &amp; Writing</a>
        <a href="/practice-tests/" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>Practice Tests</a>
    </nav>
    <a href="/profile/" class="sb-user">
        <div class="sb-avatar"><?= strtoupper(substr($firstName,0,1)) ?></div>
        <div><div class="sb-user-name"><?= htmlspecialchars($user['name'] ?? 'Student') ?></div><div class="sb-user-meta"><?= $currentStreak ?> day streak</div></div>
    </a>
</aside>

<!-- TOPBAR -->
<header class="topbar">
    <button class="ham-btn" onclick="toggleSidebar()"><span></span><span></span><span></span></button>
    <div class="tb-title">
        <h1>Strategy Advisor</h1>
        <p>Personalised SAT strategies for your level</p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="/practice-tests/" class="btn btn-dark">
            <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
            Take a Test
        </a>
        <a href="/ai-tutor/" class="btn btn-mint">
            <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
            Full AI Chat
        </a>
    </div>
</header>

<main class="main">

    <!-- Hero -->
    <div class="page-hero sr">
        <div class="hero-ico">
            <svg viewBox="0 0 24 24"><path d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div class="hero-info">
            <div class="hero-title">Your SAT Strategy Advisor</div>
            <div class="hero-sub">Personalised strategies based on your <?= ucfirst($tier) ?> level (avg score: <?= $avgScore ?>) — target: <?= $targetScore ?>. Click any topic below to get tailored advice.</div>
            <div class="hero-stats">
                <span class="hero-stat">Math accuracy: <strong><?= $mathAcc ?>%</strong></span>
                <span class="hero-stat">R&W accuracy: <strong><?= $rwAcc ?>%</strong></span>
                <?php if ($bestScore): ?><span class="hero-stat ac-stat">Best score: <strong><?= $bestScore ?></strong></span><?php endif; ?>
            </div>
        </div>
        <?php if ($daysUntilTest): ?>
        <div class="countdown">
            <div class="countdown-num"><?= $daysUntilTest ?></div>
            <div class="countdown-label">Days to Test</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Profile -->
    <div class="profile-grid sr d1">
        <div class="card">
            <div class="card-label">Your Accuracy by Subject</div>
            <div>
                <?php
                $subjects = [
                    'Math' => $mathAcc,
                    'Reading &amp; Writing' => $rwAcc,
                ];
                foreach ($subjects as $name => $pct):
                    $color = $pct >= 70 ? 'var(--ac)' : ($pct >= 50 ? 'var(--warn)' : 'var(--err)');
                ?>
                <div class="acc-row"><span class="acc-name"><?= $name ?></span><span class="acc-pct"><?= $pct ?>%</span></div>
                <div class="acc-bar"><div class="acc-bar-fill" data-target="<?= $pct ?>%" style="width:0;background:<?= $color ?>"></div></div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-label">Priority Topics to Improve</div>
            <?php
            $allWeak = array_merge(
                array_map(fn($t) => ['name'=>$t['topic_name'],'subj'=>'Math'], $weakMathTopics),
                array_map(fn($t) => ['name'=>$t['topic_name'],'subj'=>'R&W'], $weakRWTopics)
            );
            if (empty($allWeak)):
            ?>
            <p style="font-size:.875rem;color:var(--tx3)">No weak areas detected yet. Keep practising!</p>
            <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:6px">
                <?php foreach (array_slice($allWeak, 0, 5) as $w): ?>
                <div style="display:flex;align-items:center;gap:8px;padding:7px 10px;border-radius:8px;background:var(--bg);border:1px solid var(--bd)">
                    <div style="width:6px;height:6px;border-radius:50%;background:var(--err);flex-shrink:0"></div>
                    <span style="font-size:.8125rem;font-weight:600;color:var(--tx);flex:1"><?= htmlspecialchars($w['name']) ?></span>
                    <span style="font-size:.625rem;font-weight:700;padding:1px 6px;border-radius:4px;background:rgba(20,50,48,.07);color:var(--dk)"><?= $w['subj'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Strategy Topics -->
    <div class="strategy-section-title sr d2">Choose a strategy topic for personalised advice:</div>
    <div class="strategy-grid sr d3" id="strategyGrid">
        <?php foreach ($strategyTopics as $topic):
            $c = $colorMap[$topic['color']] ?? $colorMap['dk'];
        ?>
        <button class="strategy-card"
                data-topic-id="<?= $topic['id'] ?>"
                data-topic-title="<?= htmlspecialchars($topic['title']) ?>"
                onclick="loadStrategy('<?= $topic['id'] ?>', '<?= htmlspecialchars(addslashes($topic['title'])) ?>')">
            <div class="sc-ico" style="background:<?= $c['bg'] ?>;border:1px solid <?= $c['border'] ?>">
                <div style="color:<?= $c['stroke'] ?>"><?= str_replace('stroke="currentColor"', 'stroke="'.$c['stroke'].'"', $topic['icon']) ?></div>
            </div>
            <div class="sc-title"><?= $topic['title'] ?></div>
            <div class="sc-sub"><?= $topic['sub'] ?></div>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- Response Panel (hidden until topic clicked) -->
    <div class="response-panel sr d4" id="responsePanel" style="display:none">
        <div class="rp-header">
            <span class="rp-topic" id="rpTopic">Strategy</span>
            <button class="rp-close" onclick="closePanel()" title="Close">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="rp-body" id="rpBody"></div>
        <div class="rp-footer" id="rpFooter" style="display:none">
            <a href="/ai-tutor/" class="rp-btn" id="rpDeepLink">
                <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                Go deeper in AI Chat
            </a>
            <button class="rp-btn" onclick="refreshStrategy()" id="rpRefreshBtn">
                <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
                Refresh
            </button>
        </div>
    </div>

    <!-- Quick Ask Box -->
    <div class="quick-chat sr d4">
        <div class="qc-label">Ask a specific strategy question</div>
        <div class="qc-input-wrap">
            <textarea class="qc-textarea" id="qcTextarea" rows="2" placeholder="e.g. Should I skip hard questions and come back, or work through them in order?"
                      onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendQuickAsk()}"></textarea>
            <button class="qc-send" onclick="sendQuickAsk()">Ask AI Tutor</button>
        </div>
    </div>

</main>

<script>
(function(){
'use strict';

var io = new IntersectionObserver(function(e){e.forEach(function(n){if(n.isIntersecting){n.target.classList.add('visible');io.unobserve(n.target)}})},{threshold:.04,rootMargin:'0px 0px -16px 0px'});
document.querySelectorAll('.sr').forEach(function(el){io.observe(el)});

window.toggleSidebar = function(){
    var sb=document.getElementById('sidebar'),ov=document.getElementById('sidebarOverlay');
    var open=sb.classList.toggle('open');ov.classList.toggle('show',open);
    document.body.style.overflow=open?'hidden':'';
};
document.getElementById('sidebarOverlay').addEventListener('click',function(){
    document.getElementById('sidebar').classList.remove('open');this.classList.remove('show');document.body.style.overflow='';
});

/* Animate bars */
var barObs = new IntersectionObserver(function(entries){
    entries.forEach(function(e){
        if(!e.isIntersecting)return;
        setTimeout(function(){e.target.style.width=e.target.dataset.target;},200);
        barObs.unobserve(e.target);
    });
},{threshold:.1});
document.querySelectorAll('[data-target]').forEach(function(b){barObs.observe(b)});

var currentTopicId    = null;
var currentTopicTitle = null;

/* Student profile for prompts */
var profile = {
    tier:        '<?= $tier ?>',
    mathAcc:     <?= $mathAcc ?>,
    rwAcc:       <?= $rwAcc ?>,
    targetScore: <?= $targetScore ?>,
    avgScore:    <?= $avgScore ?>,
    daysLeft:    <?= $daysUntilTest ?? 'null' ?>,
    weakMath:    <?= json_encode(array_column($weakMathTopics,'topic_name')) ?>,
    weakRW:      <?= json_encode(array_column($weakRWTopics,'topic_name')) ?>,
};

var topicPrompts = {
    'time-management': 'Give me personalised time management advice for the SAT. I am at the ' + profile.tier + ' level (avg ' + profile.avgScore + '/1600, target ' + profile.targetScore + ').' + (profile.daysLeft?' I have '+profile.daysLeft+' days until my test.':'') + ' Cover: (1) exact pacing per question for Math and R&W modules, (2) when to skip and return, (3) managing the clock in the last 5 minutes, (4) specific traps that slow down ' + profile.tier + '-level students. Be very specific with numbers.',
    'math-strategy': 'Give me a complete Math section strategy for the SAT. I am at the ' + profile.tier + ' level (Math accuracy: ' + profile.mathAcc + '%). My weak topics are: ' + (profile.weakMath.join(', ')||'None identified')+'. Cover: (1) Module 1 vs Module 2 approach differences, (2) calculator strategy, (3) which question types to tackle first vs skip, (4) common traps to avoid, (5) specific tips for my weak topics.',
    'reading-strategy': 'Give me a complete Reading & Writing strategy for SAT. I am at the ' + profile.tier + ' level (R&W accuracy: ' + profile.rwAcc + '%). My weak topics are: ' + (profile.weakRW.join(', ')||'None identified')+'. Cover: (1) how to approach each question type in R&W, (2) passage reading strategy (skim vs read carefully), (3) elimination technique, (4) how to handle the vocabulary-in-context questions, (5) the most common mistakes for my level.',
    'test-day': 'Give me a complete test-day preparation plan for the SAT. I have ' + (profile.daysLeft || 'some time') + ' until my test. Target score: ' + profile.targetScore + '. Cover: (1) the night before (what to prepare, what to review, what to NOT do), (2) morning of (routine, breakfast, what to bring), (3) during the test (handling nerves, breaks strategy, emergency plan if you blank), (4) common test-day mistakes and how to avoid them.',
    'process-of-elimination': 'Teach me process of elimination for SAT at the ' + profile.tier + ' level. Cover: (1) the general POE framework step-by-step, (2) POE for Math — how to use it without working backwards, (3) POE for R&W — specific signals that make an answer wrong, (4) how to confidently guess when eliminating 2 choices, (5) the most common "trap" answer patterns on the SAT. Give concrete examples.',
    'mental-preparation': 'Give me mental preparation strategies for the SAT. Target: ' + profile.targetScore + ', current avg: ' + profile.avgScore + '. Cover: (1) managing test anxiety before and during the exam, (2) what to do when you blank on a question, (3) how to recover from a bad section without letting it affect the next one, (4) mindset techniques used by high scorers, (5) building confidence in the weeks leading up to the test.',
};

window.loadStrategy = function(topicId, topicTitle){
    currentTopicId    = topicId;
    currentTopicTitle = topicTitle;

    /* Mark active card */
    document.querySelectorAll('.strategy-card').forEach(function(c){
        c.classList.toggle('active', c.dataset.topicId === topicId);
    });

    var panel = document.getElementById('responsePanel');
    var rpBody = document.getElementById('rpBody');
    var rpFooter = document.getElementById('rpFooter');
    var rpTopic  = document.getElementById('rpTopic');
    var rpDeepLink = document.getElementById('rpDeepLink');

    panel.style.display = 'block';
    rpTopic.textContent = topicTitle;
    rpFooter.style.display = 'none';
    rpDeepLink.href = '/ai-tutor/?q=' + encodeURIComponent('Tell me more about: ' + topicTitle + ' for the SAT') + '&subject=general';

    rpBody.innerHTML = '<div class="rp-loading"><div class="rp-loading-dots"><div class="rp-ld"></div><div class="rp-ld"></div><div class="rp-ld"></div></div><div class="rp-loading-text">Generating personalised strategy…</div></div>';

    /* Scroll to panel */
    setTimeout(function(){ panel.scrollIntoView({behavior:'smooth',block:'start'}); }, 100);

    var prompt = topicPrompts[topicId] || 'Give me SAT strategy advice for: ' + topicTitle;
    streamResponse(prompt, rpBody, rpFooter);
};

window.refreshStrategy = function(){
    if (currentTopicId) loadStrategy(currentTopicId, currentTopicTitle);
};

window.closePanel = function(){
    document.getElementById('responsePanel').style.display = 'none';
    document.querySelectorAll('.strategy-card').forEach(function(c){ c.classList.remove('active'); });
};

window.sendQuickAsk = function(){
    var ta  = document.getElementById('qcTextarea');
    var q   = ta.value.trim();
    if (!q) return;
    window.location.href = '/ai-tutor/?q=' + encodeURIComponent(q) + '&subject=general';
};

function streamResponse(prompt, bodyEl, footerEl){
    fetch('/ai-tutor/api.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
        body: JSON.stringify({ message: prompt, subject: 'general', history: [] })
    })
    .then(function(response){
        var reader  = response.body.getReader();
        var decoder = new TextDecoder();
        var fullText = '';

        bodyEl.innerHTML = '<div class="rp-content" id="rpContent"></div>';
        var contentEl = document.getElementById('rpContent');

        function read(){
            return reader.read().then(function(result){
                if(result.done){
                    footerEl.style.display = 'flex';
                    return;
                }
                var chunk = decoder.decode(result.value,{stream:true});
                chunk.split('\n').forEach(function(line){
                    if(!line.startsWith('data: '))return;
                    var d=line.slice(6).trim();
                    if(d==='[DONE]'||d.includes('conversation_id'))return;
                    try{
                        var p=JSON.parse(d);
                        if(p.delta){ fullText+=p.delta; contentEl.innerHTML=formatMd(fullText); }
                    }catch(e){}
                });
                return read();
            });
        }
        return read();
    })
    .catch(function(){
        bodyEl.innerHTML = '<div style="color:rgba(255,255,255,.4);padding:20px;text-align:center">Could not load strategy. Please try again.</div>';
    });
}

function formatMd(t){
    return t
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>')
        .replace(/\*(.+?)\*/g,'<em>$1</em>')
        .replace(/^### (.+)$/gm,'<h3>$1</h3>')
        .replace(/^## (.+)$/gm,'<h3>$1</h3>')
        .replace(/^\* (.+)$/gm,'<li>$1</li>')
        .replace(/^- (.+)$/gm,'<li>$1</li>')
        .replace(/^> (.+)$/gm,'<blockquote>$1</blockquote>')
        .replace(/\n\n/g,'</p><p>')
        .replace(/\n/g,'<br>');
}

/* Auto-load from URL param */
var params = new URLSearchParams(window.location.search);
var autoTopic = params.get('topic');
if (autoTopic) {
    var titles = {
        'time-management':'Time Management','math-strategy':'Math Section Strategy',
        'reading-strategy':'R&W Section Strategy','test-day':'Test Day Preparation',
        'process-of-elimination':'Process of Elimination','mental-preparation':'Mental Preparation'
    };
    if (titles[autoTopic]) setTimeout(function(){ loadStrategy(autoTopic, titles[autoTopic]); }, 300);
}

}());
</script>
</body>
</html>