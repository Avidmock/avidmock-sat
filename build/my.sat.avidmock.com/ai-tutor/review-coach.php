<?php
/**
 * /ai-tutor/review-coach.php — Post-Quiz Review Coach
 *
 * Analyzes wrong answers from a quiz attempt, identifies error patterns,
 * and generates a personalised improvement plan via Claude API.
 *
 * Usage: /ai-tutor/review-coach.php?attempt_id=123
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/StudyStreak.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/QuizAttempt.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/AITutorHistory.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/RateLimit.php';

Auth::requireStudent();
$userId    = $_SESSION['user_id'];
$user      = User::findById($userId);
$firstName = explode(' ', $user['name'] ?? 'Student')[0];
$currentStreak = StudyStreak::getCurrent($userId);

/* ─── Validate attempt ─────────────────────────────────────────────── */
$attemptId = filter_input(INPUT_GET, 'attempt_id', FILTER_VALIDATE_INT);
if (!$attemptId) {
    header('Location: /ai-tutor/'); exit;
}

$attempt = QuizAttempt::getById($attemptId);
if (!$attempt || $attempt['user_id'] !== $userId || $attempt['status'] !== 'completed') {
    header('Location: /ai-tutor/'); exit;
}

/* ─── Load attempt data ─────────────────────────────────────────────── */
$quiz    = QuizAttempt::getQuizDetails($attemptId);
$answers = QuizAttempt::getAnswers($attemptId);

$totalQuestions = count($answers);
$wrongAnswers   = array_filter($answers, fn($a) => !$a['is_correct']);
$correctAnswers = array_filter($answers, fn($a) => $a['is_correct']);
$wrongCount     = count($wrongAnswers);
$score          = $attempt['score'] ?? 0;
$timeTaken      = (int)($attempt['time_spent'] ?? 0);

/* Error pattern analysis */
$errorsByTopic    = [];
$errorsByDifficulty = [];
foreach ($wrongAnswers as $a) {
    $topic = $a['topic'] ?? 'Unknown';
    $diff  = $a['difficulty'] ?? 'medium';
    $errorsByTopic[$topic]      = ($errorsByTopic[$topic] ?? 0) + 1;
    $errorsByDifficulty[$diff]  = ($errorsByDifficulty[$diff] ?? 0) + 1;
}
arsort($errorsByTopic);
$topErrorTopics = array_slice($errorsByTopic, 0, 5, true);

/* Time analysis */
$avgTimePerQ = $totalQuestions > 0 ? round($timeTaken / $totalQuestions) : 0;
$slowQuestions = array_filter($answers, fn($a) => ($a['time_spent'] ?? 0) > $avgTimePerQ * 1.8);

/* Build wrong-answer summary for Claude */
$wrongSummary = '';
foreach (array_slice($wrongAnswers, 0, 10) as $a) {
    $wrongSummary .= sprintf(
        "\n- Q: %s | Your answer: %s | Correct: %s | Topic: %s | Difficulty: %s",
        mb_substr($a['question_stem'] ?? 'N/A', 0, 100),
        $a['user_answer'] ?? '?',
        $a['correct_answer'] ?? '?',
        $a['topic'] ?? 'Unknown',
        $a['difficulty'] ?? 'medium'
    );
}

$topicsStr = implode(', ', array_keys($topErrorTopics));
$targetScore = (int)($user['target_score'] ?? 1200);
$quizTitle   = htmlspecialchars($quiz['title'] ?? 'Quiz');
$subject     = $quiz['subject'] ?? 'general';
$quizSubjectLabel = ['math'=>'Math','reading_writing'=>'Reading & Writing'][$subject] ?? 'General';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Review Coach — <?= $quizTitle ?> — Avidmock SAT</title>
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
.d1{transition-delay:.06s}.d2{transition-delay:.12s}.d3{transition-delay:.18s}.d4{transition-delay:.24s}.d5{transition-delay:.3s}

/* SIDEBAR (shared) */
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
.tb-title{flex:1;min-width:0}
.tb-title h1{font-size:.9375rem;font-weight:700;color:var(--tx);letter-spacing:-.015em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.tb-title p{font-size:.75rem;color:var(--tx3)}
.tb-actions{display:flex;gap:8px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:var(--r-sm);font-size:.8125rem;font-weight:700;border:none;cursor:pointer;transition:all .2s;text-decoration:none}
.btn svg{width:14px;height:14px;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0}
.btn-dark{background:var(--dk);color:#fff}.btn-dark svg{stroke:var(--ac)}.btn-dark:hover{background:var(--dk2);transform:translateY(-1px);box-shadow:0 4px 14px rgba(20,50,48,.18)}
.btn-mint{background:var(--ac);color:var(--dk)}.btn-mint svg{stroke:var(--dk)}.btn-mint:hover{background:var(--ac2);transform:translateY(-1px);box-shadow:0 4px 14px rgba(31,226,144,.28)}
.ham-btn{display:none;width:38px;height:38px;border-radius:var(--r-sm);border:1.5px solid var(--bd);background:var(--white);flex-direction:column;align-items:center;justify-content:center;gap:4px;padding:10px}
.ham-btn span{display:block;height:2px;width:100%;background:var(--tx);border-radius:1px}
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:190;opacity:0;transition:opacity .28s;pointer-events:none}
.sidebar-overlay.show{opacity:1;pointer-events:all}

/* MAIN */
.main-content{margin-left:260px;margin-top:56px;padding:32px 32px 80px;min-height:calc(100vh - 56px)}
@media(max-width:768px){.main-content{margin-left:0;padding:20px 16px 72px}}

/* SCORE BANNER */
.score-banner{
    background:linear-gradient(135deg,var(--dk),var(--dk2));
    border-radius:var(--r);padding:28px 32px;margin-bottom:24px;
    display:flex;align-items:center;gap:24px;flex-wrap:wrap;
    position:relative;overflow:hidden;
}
.score-banner::before{
    content:'';position:absolute;top:-40px;right:-40px;
    width:200px;height:200px;border-radius:50%;
    background:radial-gradient(circle,rgba(31,226,144,.08),transparent 65%);
    pointer-events:none;
}
.score-num{
    font-size:3rem;font-weight:800;letter-spacing:-.05em;line-height:1;
    background:linear-gradient(135deg,var(--ac),#00d47e);
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
}
.score-meta{flex:1}
.score-title{font-size:1.125rem;font-weight:800;color:#fff;letter-spacing:-.02em;margin-bottom:4px}
.score-sub{font-size:.875rem;color:rgba(255,255,255,.45);line-height:1.5}
.score-pills{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.score-pill{
    padding:5px 12px;border-radius:50px;font-size:.75rem;font-weight:700;
    background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);color:rgba(255,255,255,.6);
}
.score-pill.good{background:rgba(31,226,144,.1);border-color:rgba(31,226,144,.2);color:var(--ac)}
.score-pill.bad{background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.2);color:#f87171}

/* GRID */
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px}
.grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:20px}

/* CARD */
.card{background:var(--white);border:1px solid var(--bd);border-radius:var(--r);padding:22px;transition:box-shadow .25s}
.card:hover{box-shadow:0 4px 20px rgba(20,50,48,.06)}
.card-label{font-size:.6875rem;font-weight:700;color:var(--tx3);text-transform:uppercase;letter-spacing:.6px;margin-bottom:14px}

/* STAT MINI */
.stat-mini{display:flex;align-items:center;gap:12px}
.stat-mini-ico{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.stat-mini-ico svg{width:18px;height:18px;fill:none;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
.ico-ok{background:rgba(16,185,129,.1)}.ico-ok svg{stroke:var(--ok)}
.ico-err{background:rgba(239,68,68,.08)}.ico-err svg{stroke:var(--err)}
.ico-warn{background:rgba(245,158,11,.1)}.ico-warn svg{stroke:var(--warn)}
.ico-dk{background:rgba(20,50,48,.07)}.ico-dk svg{stroke:var(--dk)}
.stat-val{font-size:1.5rem;font-weight:800;color:var(--tx);letter-spacing:-.04em;line-height:1}
.stat-key{font-size:.6875rem;color:var(--tx3);font-weight:600;text-transform:uppercase;letter-spacing:.3px;margin-top:2px}

/* ERROR PATTERN */
.pattern-row{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--bd)}
.pattern-row:last-child{border-bottom:none}
.pattern-topic{flex:1;font-size:.875rem;font-weight:600;color:var(--tx);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pattern-bar-wrap{width:120px;height:6px;background:var(--bg2);border-radius:3px;overflow:hidden;flex-shrink:0}
.pattern-bar{height:100%;border-radius:3px;background:var(--err);transition:width 1.2s cubic-bezier(.16,1,.3,1)}
.pattern-count{font-size:.75rem;font-weight:700;color:var(--err);width:28px;text-align:right;flex-shrink:0}

/* WRONG QUESTIONS */
.wrong-list{display:flex;flex-direction:column;gap:10px}
.wrong-item{
    padding:14px 16px;border-radius:var(--r-sm);
    border:1.5px solid rgba(239,68,68,.15);
    background:rgba(239,68,68,.02);
}
.wrong-stem{font-size:.875rem;color:var(--tx);line-height:1.6;margin-bottom:10px;font-weight:500}
.wrong-answers{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px}
.answer-pill{padding:4px 10px;border-radius:6px;font-size:.75rem;font-weight:700}
.answer-yours{background:rgba(239,68,68,.1);color:var(--err)}
.answer-correct{background:rgba(16,185,129,.1);color:var(--ok)}
.wrong-meta{display:flex;gap:8px;align-items:center}
.wrong-topic-tag{font-size:.625rem;font-weight:700;padding:2px 7px;border-radius:4px;text-transform:uppercase;letter-spacing:.3px;background:rgba(20,50,48,.07);color:var(--dk)}
.wrong-diff{font-size:.625rem;font-weight:700;padding:2px 7px;border-radius:4px;text-transform:uppercase;letter-spacing:.3px}
.diff-easy{background:rgba(16,185,129,.1);color:var(--ok)}
.diff-medium{background:rgba(245,158,11,.1);color:var(--warn)}
.diff-hard{background:rgba(239,68,68,.1);color:var(--err)}
.wrong-ask-btn{
    margin-left:auto;padding:4px 10px;border-radius:6px;
    background:rgba(20,50,48,.06);border:1px solid rgba(20,50,48,.1);
    font-size:.6875rem;font-weight:700;color:var(--dk);
    transition:all .18s;
}
.wrong-ask-btn:hover{background:rgba(31,226,144,.1);border-color:rgba(31,226,144,.2);color:var(--ac2)}

/* AI ANALYSIS PANEL */
.ai-panel{
    background:var(--dk);border-radius:var(--r);padding:0;
    overflow:hidden;margin-bottom:20px;
}
.ai-panel-header{
    padding:18px 24px;border-bottom:1px solid rgba(255,255,255,.07);
    display:flex;align-items:center;gap:12px;
}
.ai-panel-ico{
    width:38px;height:38px;border-radius:10px;
    background:rgba(31,226,144,.1);
    display:flex;align-items:center;justify-content:center;
    flex-shrink:0;
}
.ai-panel-ico svg{width:18px;height:18px;stroke:var(--ac);fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.ai-panel-title{font-size:1rem;font-weight:700;color:#fff}
.ai-panel-sub{font-size:.75rem;color:rgba(255,255,255,.35);margin-top:1px}
.ai-panel-body{padding:24px}
.ai-loading{
    display:flex;flex-direction:column;align-items:center;gap:14px;padding:40px 20px;
}
.ai-loading-dots{display:flex;gap:6px}
.ai-loading-dot{width:8px;height:8px;border-radius:50%;background:rgba(31,226,144,.4);animation:ldot 1.4s ease-in-out infinite}
.ai-loading-dot:nth-child(2){animation-delay:.2s}
.ai-loading-dot:nth-child(3){animation-delay:.4s}
@keyframes ldot{0%,60%,100%{transform:scale(.6);opacity:.4}30%{transform:scale(1);opacity:1}}
.ai-loading-text{font-size:.875rem;color:rgba(255,255,255,.4);text-align:center}
.ai-content{font-size:.9375rem;color:rgba(255,255,255,.78);line-height:1.75}
.ai-content h3{font-size:1rem;font-weight:700;color:#fff;margin:18px 0 8px}
.ai-content h3:first-child{margin-top:0}
.ai-content p{margin-bottom:10px}
.ai-content ul{padding-left:20px;margin-bottom:12px}
.ai-content li{margin-bottom:5px}
.ai-content strong{color:#fff;font-weight:700}
.ai-content em{font-style:italic;color:var(--ac)}
.ai-content code{font-size:.85em;background:rgba(255,255,255,.08);padding:2px 6px;border-radius:4px}
.ai-content blockquote{
    border-left:3px solid var(--ac);padding:8px 14px;margin:10px 0;
    background:rgba(31,226,144,.04);border-radius:0 8px 8px 0;font-style:italic;
    color:rgba(255,255,255,.6);
}
.ai-error{
    padding:16px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);
    border-radius:var(--r-sm);color:#f87171;font-size:.875rem;
}
.ai-actions{
    padding:16px 24px;border-top:1px solid rgba(255,255,255,.06);
    display:flex;gap:8px;flex-wrap:wrap;
}
.ai-action-btn{
    display:inline-flex;align-items:center;gap:6px;
    padding:8px 16px;border-radius:var(--r-sm);
    background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);
    font-size:.8125rem;font-weight:700;color:rgba(255,255,255,.7);
    transition:all .18s;
}
.ai-action-btn:hover{background:rgba(31,226,144,.1);border-color:rgba(31,226,144,.2);color:var(--ac)}
.ai-action-btn svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}

/* NEXT STEPS */
.next-steps{display:flex;flex-direction:column;gap:8px}
.next-step-item{
    display:flex;align-items:center;gap:12px;padding:12px 14px;
    border-radius:var(--r-sm);border:1px solid var(--bd);background:var(--bg);
    transition:all .2s;
}
.next-step-item:hover{border-color:var(--ac);background:rgba(31,226,144,.02)}
.step-num{
    width:26px;height:26px;border-radius:50%;
    background:var(--dk);color:var(--ac);
    display:flex;align-items:center;justify-content:center;
    font-size:.6875rem;font-weight:800;flex-shrink:0;
}
.step-text{flex:1;font-size:.875rem;font-weight:600;color:var(--tx)}
.step-link{
    padding:4px 10px;border-radius:6px;
    background:rgba(31,226,144,.08);border:1px solid rgba(31,226,144,.15);
    font-size:.6875rem;font-weight:700;color:var(--ac2);
    transition:all .18s;flex-shrink:0;
}
.step-link:hover{background:rgba(31,226,144,.15)}

/* RESPONSIVE */
@media(max-width:900px){
    .sidebar{position:fixed;transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}
    .sidebar-overlay{display:block}.main{margin-left:0;padding:24px 16px 80px}
    .topbar{left:0;padding:0 16px}.ham-btn{display:flex}
}
@media(max-width:768px){.grid-2,.grid-3{grid-template-columns:1fr}}
@media(max-width:480px){.score-banner{padding:20px}.score-num{font-size:2.25rem}}
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
        <a href="/achievements/" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>Achievements</a>
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
        <h1>Review Coach — <?= $quizTitle ?></h1>
        <p>AI-powered analysis of your <?= $wrongCount ?> incorrect answer<?= $wrongCount !== 1 ? 's' : '' ?></p>
    </div>
    <div class="tb-actions">
        <a href="/quiz.php?quiz_id=<?= (int)($quiz['id'] ?? 0) ?>" class="btn btn-dark">
            <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
            Retake Quiz
        </a>
        <a href="/ai-tutor/" class="btn btn-mint">
            <svg viewBox="0 0 24 24"><path d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Open AI Tutor
        </a>
    </div>
</header>

<main class="main">

    <!-- Score Banner -->
    <div class="score-banner sr">
        <div style="text-align:center;min-width:80px">
            <div class="score-num"><?= $score ?>%</div>
        </div>
        <div class="score-meta">
            <div class="score-title"><?= $quizTitle ?> · <?= $quizSubjectLabel ?></div>
            <div class="score-sub">Completed <?= date('M j, Y', strtotime($attempt['completed_at'] ?? 'now')) ?> · <?= gmdate('i:s', $timeTaken) ?> time taken</div>
            <div class="score-pills">
                <span class="score-pill good"><?= count($correctAnswers) ?> correct</span>
                <span class="score-pill bad"><?= $wrongCount ?> incorrect</span>
                <span class="score-pill"><?= $totalQuestions ?> total</span>
                <?php if ($avgTimePerQ): ?>
                <span class="score-pill"><?= $avgTimePerQ ?>s avg/question</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="grid-3 sr d1">
        <div class="card">
            <div class="stat-mini">
                <div class="stat-mini-ico ico-ok"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
                <div><div class="stat-val"><?= count($correctAnswers) ?></div><div class="stat-key">Correct Answers</div></div>
            </div>
        </div>
        <div class="card">
            <div class="stat-mini">
                <div class="stat-mini-ico ico-err"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></div>
                <div><div class="stat-val"><?= $wrongCount ?></div><div class="stat-key">Need Review</div></div>
            </div>
        </div>
        <div class="card">
            <div class="stat-mini">
                <div class="stat-mini-ico ico-warn"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
                <div><div class="stat-val"><?= count($slowQuestions) ?></div><div class="stat-key">Slow Questions</div></div>
            </div>
        </div>
    </div>

    <!-- Error Patterns + Wrong Questions -->
    <div class="grid-2 sr d2">
        <!-- Error by Topic -->
        <div class="card">
            <div class="card-label">Error Patterns by Topic</div>
            <?php if (empty($topErrorTopics)): ?>
            <p style="color:var(--tx3);font-size:.875rem">No errors! Perfect score.</p>
            <?php else:
                $maxErrors = max($topErrorTopics);
            ?>
            <div>
                <?php foreach ($topErrorTopics as $topic => $count): ?>
                <div class="pattern-row">
                    <div class="pattern-topic"><?= htmlspecialchars($topic) ?></div>
                    <div class="pattern-bar-wrap"><div class="pattern-bar" data-target="<?= round(($count/$maxErrors)*100) ?>%" style="width:0"></div></div>
                    <div class="pattern-count"><?= $count ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Error by Difficulty -->
        <div class="card">
            <div class="card-label">Errors by Difficulty</div>
            <?php
            $diffs = ['easy'=>'Easy','medium'=>'Medium','hard'=>'Hard'];
            $diffColors = ['easy'=>'var(--ok)','medium'=>'var(--warn)','hard'=>'var(--err)'];
            foreach ($diffs as $dk => $dl):
                $cnt = $errorsByDifficulty[$dk] ?? 0;
                $pct = $totalQuestions > 0 ? round(($cnt/$totalQuestions)*100) : 0;
            ?>
            <div style="margin-bottom:14px">
                <div style="display:flex;justify-content:space-between;font-size:.8125rem;font-weight:600;color:var(--tx2);margin-bottom:5px">
                    <span><?= $dl ?></span>
                    <span style="color:<?= $diffColors[$dk] ?>"><?= $cnt ?> error<?= $cnt !== 1 ? 's' : '' ?></span>
                </div>
                <div style="height:6px;background:var(--bg2);border-radius:3px;overflow:hidden">
                    <div data-target="<?= $pct ?>%" style="height:100%;width:0;background:<?= $diffColors[$dk] ?>;border-radius:3px;transition:width 1.2s cubic-bezier(.16,1,.3,1)"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- AI Analysis -->
    <div class="ai-panel sr d3">
        <div class="ai-panel-header">
            <div class="ai-panel-ico">
                <svg viewBox="0 0 24 24"><path d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <div>
                <div class="ai-panel-title">AI Analysis &amp; Next Steps</div>
                <div class="ai-panel-sub">Personalised breakdown of your mistakes and what to do next</div>
            </div>
        </div>
        <div class="ai-panel-body" id="aiBody">
            <div class="ai-loading">
                <div class="ai-loading-dots">
                    <div class="ai-loading-dot"></div>
                    <div class="ai-loading-dot"></div>
                    <div class="ai-loading-dot"></div>
                </div>
                <div class="ai-loading-text">Analysing your performance and generating personalised recommendations…</div>
            </div>
        </div>
        <div class="ai-actions" id="aiActions" style="display:none">
            <a href="/ai-tutor/?q=Help+me+understand+<?= urlencode($topicsStr) ?>&subject=<?= $subject ?>" class="ai-action-btn">
                <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                Discuss with AI Tutor
            </a>
            <a href="/<?= $subject === 'math' ? 'math' : 'reading' ?>/" class="ai-action-btn">
                <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
                Study Related Lessons
            </a>
            <a href="/quiz.php?quiz_id=<?= (int)($quiz['id'] ?? 0) ?>" class="ai-action-btn">
                <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
                Retake This Quiz
            </a>
        </div>
    </div>

    <!-- Wrong Questions Detail -->
    <?php if (!empty($wrongAnswers)): ?>
    <div class="card sr d4">
        <div class="card-label">Questions to Review (<?= $wrongCount ?>)</div>
        <div class="wrong-list">
            <?php foreach ($wrongAnswers as $a):
                $diffCls = 'diff-' . ($a['difficulty'] ?? 'medium');
            ?>
            <div class="wrong-item">
                <div class="wrong-stem"><?= htmlspecialchars(mb_substr($a['question_stem'] ?? '', 0, 200)) ?></div>
                <div class="wrong-answers">
                    <span class="answer-pill answer-yours">Your: <?= htmlspecialchars($a['user_answer'] ?? '?') ?></span>
                    <span class="answer-pill answer-correct">Correct: <?= htmlspecialchars($a['correct_answer'] ?? '?') ?></span>
                </div>
                <div class="wrong-meta">
                    <span class="wrong-topic-tag"><?= htmlspecialchars($a['topic'] ?? 'Unknown') ?></span>
                    <span class="wrong-diff <?= $diffCls ?>"><?= ucfirst($a['difficulty'] ?? 'medium') ?></span>
                    <button class="wrong-ask-btn" onclick="askAboutQuestion(<?= (int)$a['question_id'] ?>, '<?= addslashes(mb_substr($a['question_stem'] ?? '', 0, 100)) ?>')">
                        Ask AI →
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/KaTeX/0.16.9/katex.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/KaTeX/0.16.9/contrib/auto-render.min.js"></script>
<script>
(function(){
'use strict';

/* Scroll reveal */
var io = new IntersectionObserver(function(e){e.forEach(function(n){if(n.isIntersecting){n.target.classList.add('visible');io.unobserve(n.target)}})},{threshold:.04,rootMargin:'0px 0px -16px 0px'});
document.querySelectorAll('.sr').forEach(function(el){io.observe(el)});

/* Sidebar */
window.toggleSidebar = function(){
    var sb=document.getElementById('sidebar'),ov=document.getElementById('sidebarOverlay');
    var open=sb.classList.toggle('open');ov.classList.toggle('show',open);
    document.body.style.overflow=open?'hidden':'';
};
document.getElementById('sidebarOverlay').addEventListener('click',function(){
    document.getElementById('sidebar').classList.remove('open');
    this.classList.remove('show');document.body.style.overflow='';
});

/* Animate bars */
var barObs = new IntersectionObserver(function(entries){
    entries.forEach(function(e){
        if(!e.isIntersecting) return;
        var el=e.target;
        setTimeout(function(){ el.style.width=el.dataset.target||'0%'; },200);
        barObs.unobserve(el);
    });
},{threshold:.1});
document.querySelectorAll('[data-target]').forEach(function(b){ barObs.observe(b); });

/* Ask about question */
window.askAboutQuestion = function(qId, stem){
    var url = '/ai-tutor/?q=' + encodeURIComponent('Can you explain this question to me: ' + stem + '...') + '&subject=<?= $subject ?>';
    window.location.href = url;
};

/* ── Load AI Analysis via SSE ── */
(function loadAnalysis(){
    var aiBody    = document.getElementById('aiBody');
    var aiActions = document.getElementById('aiActions');

    var payload = {
        mode: 'review_coach',
        attempt_id:    <?= (int)$attemptId ?>,
        quiz_title:    <?= json_encode($quiz['title'] ?? 'Quiz') ?>,
        subject:       <?= json_encode($subject) ?>,
        score:         <?= (int)$score ?>,
        wrong_count:   <?= $wrongCount ?>,
        total:         <?= $totalQuestions ?>,
        top_topics:    <?= json_encode(array_keys($topErrorTopics)) ?>,
        wrong_summary: <?= json_encode($wrongSummary) ?>,
        target_score:  <?= $targetScore ?>,
        avg_time:      <?= $avgTimePerQ ?>,
    };

    fetch('/ai-tutor/api.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
        body: JSON.stringify({
            message: buildPrompt(payload),
            subject: payload.subject,
            history: [],
        })
    })
    .then(function(response){
        var reader  = response.body.getReader();
        var decoder = new TextDecoder();
        var fullText = '';

        aiBody.innerHTML = '<div class="ai-content" id="aiContent"></div>';
        var contentEl = document.getElementById('aiContent');

        function read(){
            return reader.read().then(function(result){
                if(result.done){
                    aiActions.style.display = 'flex';
                    try{ renderMathInElement(contentEl,{delimiters:[{left:'$$',right:'$$',display:true},{left:'$',right:'$',display:false}],throwOnError:false}); }catch(e){}
                    return;
                }
                var chunk = decoder.decode(result.value,{stream:true});
                chunk.split('\n').forEach(function(line){
                    if(!line.startsWith('data: ')) return;
                    var d=line.slice(6).trim();
                    if(d==='[DONE]'||d.includes('conversation_id')) return;
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
        aiBody.innerHTML = '<div class="ai-error">Could not load analysis. Please try refreshing.</div>';
    });

    function buildPrompt(d){
        return 'You are reviewing a student\'s quiz performance. Here is what happened:\n\n' +
            'Quiz: ' + d.quiz_title + ' (' + d.subject + ')\n' +
            'Score: ' + d.score + '% (' + (d.total - d.wrong_count) + '/' + d.total + ' correct)\n' +
            'Target SAT score: ' + d.target_score + '\n' +
            'Average time per question: ' + d.avg_time + 's\n' +
            'Top error topics: ' + d.top_topics.join(', ') + '\n\n' +
            'Wrong answer details:' + d.wrong_summary + '\n\n' +
            'Please provide:\n' +
            '1. **What went wrong** — identify the key patterns in the mistakes (conceptual gaps, rushing, tricky question types?)\n' +
            '2. **Most critical topics to fix** — specific concepts the student needs to review, ranked by impact\n' +
            '3. **Concrete next steps** — 3-4 specific actions to take before the next attempt\n' +
            '4. **One encouraging note** — acknowledge what they did right and motivate them\n\n' +
            'Keep it specific, actionable, and under 400 words. Use markdown headers.';
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
}());

}());
</script>
</body>
</html>