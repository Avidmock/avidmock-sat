<?php
/**
 * /ai-tutor/history.php — AI Tutor Conversation History
 * Searchable list of all past AI conversations, grouped by date.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/StudyStreak.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/AITutorHistory.php';

Auth::requireStudent();
$userId        = $_SESSION['user_id'];
$user          = User::findById($userId);
$firstName     = explode(' ', $user['name'] ?? 'Student')[0];
$currentStreak = StudyStreak::getCurrent($userId);

/* Paginate */
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$search  = trim($_GET['q'] ?? '');
$subject = in_array($_GET['subject'] ?? '', ['math','reading_writing','general']) ? $_GET['subject'] : '';

$conversations = AITutorHistory::getAll($userId, $perPage, $offset, $search, $subject);
$total         = AITutorHistory::countAll($userId, $search, $subject);
$totalPages    = max(1, (int)ceil($total / $perPage));

/* Stats */
$stats = AITutorHistory::getStats($userId);
$totalConvs    = (int)($stats['total_conversations'] ?? 0);
$totalMessages = (int)($stats['total_messages'] ?? 0);
$topSubject    = $stats['top_subject'] ?? 'general';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Conversation History — AI Tutor — Avidmock SAT</title>
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
<style>
:root{
    --dk:#143230;--dk2:#1b403d;--ac:#1fe290;--ac2:#15c87a;
    --tx:#111827;--tx2:#374151;--tx3:#9ca3af;
    --bg:#f4f7f6;--bg2:#edf2f0;--bd:#e2eae8;--white:#ffffff;
    --err:#ef4444;--warn:#f59e0b;
    --ff:'DM Sans',-apple-system,BlinkMacSystemFont,sans-serif;
    --sidebar-w:260px;--topbar-h:64px;--r:14px;--r-sm:10px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{font-size:16px;scroll-behavior:smooth}
body{font-family:var(--ff);-webkit-font-smoothing:antialiased;background:var(--bg);color:var(--tx);min-height:100vh}
a{text-decoration:none;color:inherit}
button{font-family:var(--ff);cursor:pointer}
.sr{opacity:0;transform:translateY(20px);transition:opacity .5s cubic-bezier(.16,1,.3,1),transform .5s cubic-bezier(.16,1,.3,1)}
.sr.visible{opacity:1;transform:none}
.d1{transition-delay:.06s}.d2{transition-delay:.12s}.d3{transition-delay:.18s}.d4{transition-delay:.24s}

/* SIDEBAR */
.sidebar{position:fixed;top:0;left:0;width:var(--sidebar-w);height:100vh;background:var(--dk);display:flex;flex-direction:column;overflow-y:auto;overflow-x:hidden;z-index:200;transition:transform .32s cubic-bezier(.16,1,.3,1)}
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
.topbar-greeting{flex:1;min-width:0}
.topbar-greeting h1{font-size:.9375rem;font-weight:700;color:var(--tx);letter-spacing:-.015em}
.topbar-greeting p{font-size:.75rem;color:var(--tx3);margin-top:1px}
.topbar-actions{display:flex;align-items:center;gap:8px}
.topbar-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:var(--r-sm);font-size:.8125rem;font-weight:700;border:none;cursor:pointer;transition:all .2s;text-decoration:none}
.btn-mint{background:var(--ac);color:var(--dk)}
.btn-mint:hover{background:var(--ac2);transform:translateY(-1px);box-shadow:0 4px 14px rgba(31,226,144,.28)}
.btn-mint svg{stroke:var(--dk);fill:none;width:14px;height:14px;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
.ham-btn{display:none;width:38px;height:38px;border-radius:var(--r-sm);border:1.5px solid var(--bd);background:var(--white);flex-direction:column;align-items:center;justify-content:center;gap:4px;padding:10px}
.ham-btn span{display:block;height:2px;width:100%;background:var(--tx);border-radius:1px}
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:190;opacity:0;transition:opacity .28s;pointer-events:none}
.sidebar-overlay.show{opacity:1;pointer-events:all}

/* MAIN */
.main{margin-left:var(--sidebar-w);margin-top:var(--topbar-h);padding:32px 28px 80px;min-height:calc(100vh - var(--topbar-h))}

/* STAT CARDS */
.stat-row{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:28px}
.stat-card{background:var(--white);border:1px solid var(--bd);border-radius:var(--r);padding:20px;display:flex;align-items:center;gap:14px;transition:box-shadow .25s}
.stat-card:hover{box-shadow:0 4px 20px rgba(20,50,48,.06)}
.stat-ico{width:44px;height:44px;border-radius:11px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.stat-ico svg{width:20px;height:20px;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.ico-ac{background:rgba(31,226,144,.1)}.ico-ac svg{stroke:var(--ac2)}
.ico-dk{background:rgba(20,50,48,.07)}.ico-dk svg{stroke:var(--dk)}
.ico-warn{background:rgba(245,158,11,.1)}.ico-warn svg{stroke:var(--warn)}
.stat-label{font-size:.6875rem;font-weight:700;color:var(--tx3);text-transform:uppercase;letter-spacing:.4px;margin-bottom:3px}
.stat-value{font-size:1.5rem;font-weight:800;color:var(--tx);letter-spacing:-.04em;line-height:1}
.stat-sub{font-size:.6875rem;color:var(--tx3);margin-top:3px}

/* FILTERS */
.filters-bar{display:flex;align-items:center;gap:10px;margin-bottom:24px;flex-wrap:wrap}
.search-wrap{flex:1;min-width:200px;max-width:360px;position:relative}
.search-ico{position:absolute;left:12px;top:50%;transform:translateY(-50%);width:15px;height:15px;stroke:var(--tx3);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.search-inp{width:100%;padding:9px 12px 9px 36px;border:1.5px solid var(--bd);border-radius:var(--r-sm);font-family:var(--ff);font-size:.875rem;color:var(--tx);outline:none;transition:border-color .18s;background:var(--white)}
.search-inp:focus{border-color:var(--ac)}
.filter-tabs{display:flex;gap:6px;flex-wrap:wrap}
.filter-tab{padding:7px 14px;border-radius:50px;border:1.5px solid var(--bd);background:var(--white);font-size:.8125rem;font-weight:600;color:var(--tx3);cursor:pointer;transition:all .18s;text-decoration:none}
.filter-tab:hover{border-color:var(--ac);color:var(--tx)}
.filter-tab.active{background:var(--dk);border-color:var(--dk);color:var(--ac)}
.filter-count{margin-left:auto;font-size:.75rem;font-weight:600;color:var(--tx3);padding:4px 0}

/* CONVERSATION LIST */
.conv-list{display:flex;flex-direction:column;gap:10px;margin-bottom:32px}
.conv-card{
    background:var(--white);border:1px solid var(--bd);border-radius:var(--r);
    padding:18px 20px;
    display:flex;align-items:flex-start;gap:14px;
    transition:all .25s cubic-bezier(.16,1,.3,1);
    cursor:pointer;
}
.conv-card:hover{border-color:var(--ac);transform:translateX(3px);box-shadow:0 4px 20px rgba(20,50,48,.06)}
.conv-icon{
    width:44px;height:44px;border-radius:11px;flex-shrink:0;
    display:flex;align-items:center;justify-content:center;
}
.conv-icon svg{width:20px;height:20px;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.icon-math{background:rgba(20,50,48,.07)}.icon-math svg{stroke:var(--dk)}
.icon-rw{background:rgba(31,226,144,.1)}.icon-rw svg{stroke:var(--ac2)}
.icon-general{background:rgba(156,163,175,.1)}.icon-general svg{stroke:var(--tx3)}
.conv-body{flex:1;min-width:0}
.conv-title{font-size:.9375rem;font-weight:700;color:var(--tx);letter-spacing:-.015em;margin-bottom:5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.conv-preview{font-size:.8125rem;color:var(--tx3);line-height:1.5;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.conv-meta{display:flex;align-items:center;gap:8px;margin-top:8px;flex-wrap:wrap}
.conv-subj{padding:2px 8px;border-radius:4px;font-size:.625rem;font-weight:700;text-transform:uppercase;letter-spacing:.3px}
.subj-math{background:rgba(20,50,48,.07);color:var(--dk)}
.subj-rw{background:rgba(31,226,144,.1);color:#0a6640}
.subj-general{background:rgba(156,163,175,.12);color:var(--tx3)}
.conv-msg-count{font-size:.6875rem;color:var(--tx3);font-weight:600}
.conv-time{font-size:.6875rem;color:var(--tx3);margin-left:auto}
.conv-open-btn{
    flex-shrink:0;
    width:34px;height:34px;border-radius:9px;
    border:1.5px solid var(--bd);background:var(--bg);
    display:flex;align-items:center;justify-content:center;
    transition:all .18s;
    align-self:center;
}
.conv-open-btn:hover{border-color:var(--ac);background:rgba(31,226,144,.04)}
.conv-open-btn svg{width:15px;height:15px;stroke:var(--tx3);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.conv-card:hover .conv-open-btn svg{stroke:var(--ac)}

/* PAGINATION */
.pagination{display:flex;align-items:center;justify-content:center;gap:6px;margin-top:12px}
.page-btn{
    min-width:36px;height:36px;padding:0 10px;border-radius:9px;
    border:1.5px solid var(--bd);background:var(--white);
    font-size:.8125rem;font-weight:600;color:var(--tx3);
    display:flex;align-items:center;justify-content:center;
    transition:all .18s;cursor:pointer;text-decoration:none;
}
.page-btn:hover{border-color:var(--ac);color:var(--tx)}
.page-btn.active{background:var(--dk);border-color:var(--dk);color:var(--ac)}
.page-btn:disabled,.page-btn.disabled{opacity:.4;pointer-events:none}

/* EMPTY */
.empty-state{text-align:center;padding:60px 20px}
.empty-state-icon{width:64px;height:64px;border-radius:18px;background:rgba(31,226,144,.07);border:1px solid rgba(31,226,144,.15);display:flex;align-items:center;justify-content:center;margin:0 auto 16px}
.empty-state-icon svg{width:28px;height:28px;stroke:var(--ac);fill:none;stroke-width:1.5;stroke-linecap:round;stroke-linejoin:round}
.empty-state h3{font-size:1.125rem;font-weight:800;color:var(--tx);margin-bottom:8px}
.empty-state p{font-size:.9rem;color:var(--tx3);max-width:320px;margin:0 auto 20px;line-height:1.6}

/* RESPONSIVE */
@media(max-width:1024px){.stat-row{grid-template-columns:1fr 1fr}}
@media(max-width:900px){
    .sidebar{position:fixed;transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}
    .sidebar-overlay{display:block}.main{margin-left:0;padding:24px 16px 80px}
    .topbar{left:0;padding:0 16px}.ham-btn{display:flex}
}
@media(max-width:640px){
    .stat-row{grid-template-columns:1fr}.filters-bar{flex-direction:column;align-items:stretch}
    .search-wrap{max-width:none}.conv-time{display:none}
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
        <a href="/schedule/" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>My Schedule</a>
        <div class="sb-section">Learn</div>
        <a href="/math/" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><path d="M4 19l8-14 8 14H4z"/></svg>Math</a>
        <a href="/reading/" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>Reading &amp; Writing</a>
        <a href="/practice-tests/" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>Practice Tests</a>
        <div class="sb-section">Tools</div>
        <a href="/ai-tutor/" class="sb-link active"><svg class="sb-ico" viewBox="0 0 24 24"><path d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>AI Tutor</a>
        <a href="/writing-lab/" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>Writing Lab</a>
        <a href="/sessions/" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>Group Sessions</a>
        <div class="sb-section">Progress</div>
        <a href="/leaderboard/" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>Leaderboard</a>
        <a href="/achievements/" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>Achievements</a>
        <a href="/profile/" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Profile</a>
    </nav>
    <a href="/profile/" class="sb-user">
        <div class="sb-avatar"><?= strtoupper(substr($firstName,0,1)) ?></div>
        <div style="min-width:0">
            <div class="sb-user-name"><?= htmlspecialchars($user['name'] ?? 'Student') ?></div>
            <div class="sb-user-meta"><?= $currentStreak ?> day streak</div>
        </div>
    </a>
</aside>

<!-- TOPBAR -->
<header class="topbar">
    <button class="ham-btn" onclick="toggleSidebar()"><span></span><span></span><span></span></button>
    <div class="topbar-greeting">
        <h1>Conversation History</h1>
        <p><?= $totalConvs ?> conversation<?= $totalConvs !== 1 ? 's' : '' ?> · <?= $totalMessages ?> messages total</p>
    </div>
    <div class="topbar-actions">
        <a href="/ai-tutor/" class="topbar-btn btn-mint">
            <svg viewBox="0 0 24 24"><path d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" stroke-linecap="round" stroke-linejoin="round"/></svg>
            New Chat
        </a>
    </div>
</header>

<main class="main">

    <!-- Breadcrumb -->
    <nav style="font-size:.8125rem;color:var(--tx3);margin-bottom:20px" class="sr">
        <a href="/" style="color:var(--tx3)">Dashboard</a> <span style="margin:0 6px">›</span>
        <a href="/ai-tutor/" style="color:var(--tx3)">AI Tutor</a> <span style="margin:0 6px">›</span>
        <span style="color:var(--tx);font-weight:600">History</span>
    </nav>

    <!-- Stats -->
    <div class="stat-row sr">
        <div class="stat-card">
            <div class="stat-ico ico-ac"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg></div>
            <div>
                <div class="stat-label">Conversations</div>
                <div class="stat-value"><?= number_format($totalConvs) ?></div>
                <div class="stat-sub">all time</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-ico ico-dk"><svg viewBox="0 0 24 24"><path d="M8 9h8M8 13h6"/><path d="M20 4H4a2 2 0 00-2 2v14l4-4h14a2 2 0 002-2V6a2 2 0 00-2-2z"/></svg></div>
            <div>
                <div class="stat-label">Messages Exchanged</div>
                <div class="stat-value"><?= number_format($totalMessages) ?></div>
                <div class="stat-sub">questions &amp; answers</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-ico ico-warn"><svg viewBox="0 0 24 24"><path d="M4 19l8-14 8 14H4z"/></svg></div>
            <div>
                <div class="stat-label">Top Subject</div>
                <div class="stat-value" style="font-size:1.125rem;letter-spacing:-.02em">
                    <?= ['math'=>'Math','reading_writing'=>'R&W','general'=>'General'][$topSubject] ?? 'General' ?>
                </div>
                <div class="stat-sub">most asked about</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <form method="get" class="filters-bar sr d1" id="filterForm">
        <div class="search-wrap">
            <svg class="search-ico" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" name="q" class="search-inp" placeholder="Search conversations…"
                   value="<?= htmlspecialchars($search) ?>" id="searchInp">
        </div>
        <div class="filter-tabs">
            <a href="?" class="filter-tab <?= !$subject ? 'active' : '' ?>">All</a>
            <a href="?<?= http_build_query(array_merge($_GET,['subject'=>'math','page'=>1])) ?>" class="filter-tab <?= $subject==='math'?'active':'' ?>">Math</a>
            <a href="?<?= http_build_query(array_merge($_GET,['subject'=>'reading_writing','page'=>1])) ?>" class="filter-tab <?= $subject==='reading_writing'?'active':'' ?>">Reading &amp; Writing</a>
            <a href="?<?= http_build_query(array_merge($_GET,['subject'=>'general','page'=>1])) ?>" class="filter-tab <?= $subject==='general'?'active':'' ?>">General</a>
        </div>
        <div class="filter-count"><?= number_format($total) ?> result<?= $total !== 1 ? 's' : '' ?></div>
    </form>

    <!-- Conversation List -->
    <?php if (empty($conversations)): ?>
    <div class="empty-state sr d2">
        <div class="empty-state-icon"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg></div>
        <?php if ($search || $subject): ?>
            <h3>No matching conversations</h3>
            <p>Try a different search term or subject filter.</p>
            <a href="/ai-tutor/history.php" class="topbar-btn btn-mint" style="display:inline-flex">Clear filters</a>
        <?php else: ?>
            <h3>No conversations yet</h3>
            <p>Start a conversation with your AI tutor to see history here.</p>
            <a href="/ai-tutor/" class="topbar-btn btn-mint" style="display:inline-flex">Start Chatting</a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="conv-list sr d2">
        <?php
        $lastDate = null;
        foreach ($conversations as $conv):
            $ts = strtotime($conv['last_message_at'] ?? $conv['created_at'] ?? 'now');
            $convDate = date('Y-m-d', $ts);
            $today    = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            if ($convDate === $today) $dateLabel = 'Today';
            elseif ($convDate === $yesterday) $dateLabel = 'Yesterday';
            else $dateLabel = date('F j, Y', $ts);

            if ($dateLabel !== $lastDate):
                $lastDate = $dateLabel;
        ?>
        <div style="font-size:.6875rem;font-weight:700;color:var(--tx3);text-transform:uppercase;letter-spacing:.5px;padding:<?= $lastDate!=='Today'?'12px':'4px' ?> 4px 6px"><?= $dateLabel ?></div>
        <?php endif;
            $subjCls  = ['math'=>'subj-math','reading_writing'=>'subj-rw'][$conv['subject']??''] ?? 'subj-general';
            $subjLbl  = ['math'=>'Math','reading_writing'=>'R&W'][$conv['subject']??''] ?? 'General';
            $iconCls  = ['math'=>'icon-math','reading_writing'=>'icon-rw'][$conv['subject']??''] ?? 'icon-general';
            $msgCount = (int)($conv['message_count'] ?? 0);
        ?>
        <a href="/ai-tutor/?load=<?= (int)$conv['id'] ?>" class="conv-card">
            <div class="conv-icon <?= $iconCls ?>">
                <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
            </div>
            <div class="conv-body">
                <div class="conv-title"><?= htmlspecialchars($conv['title'] ?? 'Untitled conversation') ?></div>
                <div class="conv-preview"><?= htmlspecialchars(mb_substr($conv['last_message_preview'] ?? '', 0, 120)) ?></div>
                <div class="conv-meta">
                    <span class="conv-subj <?= $subjCls ?>"><?= $subjLbl ?></span>
                    <span class="conv-msg-count"><?= $msgCount ?> message<?= $msgCount !== 1 ? 's' : '' ?></span>
                    <span class="conv-time"><?= date('g:i A', $ts) ?></span>
                </div>
            </div>
            <div class="conv-open-btn">
                <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav class="pagination sr d3">
        <?php
        $prevPage = max(1, $page - 1);
        $nextPage = min($totalPages, $page + 1);
        $qBase = http_build_query(array_filter(['q'=>$search,'subject'=>$subject]));
        ?>
        <?php if ($page > 1): ?>
        <a href="?<?= $qBase ?>&page=<?= $prevPage ?>" class="page-btn">
            <svg style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
        </a>
        <?php endif; ?>

        <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
        <a href="?<?= $qBase ?>&page=<?= $p ?>" class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
        <a href="?<?= $qBase ?>&page=<?= $nextPage ?>" class="page-btn">
            <svg style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
    <?php endif; ?>

</main>

<script>
(function(){
    var io = new IntersectionObserver(function(e){e.forEach(function(n){if(n.isIntersecting){n.target.classList.add('visible');io.unobserve(n.target)}})},{threshold:.04,rootMargin:'0px 0px -16px 0px'});
    document.querySelectorAll('.sr').forEach(function(el){io.observe(el)});

    window.toggleSidebar = function(){
        var sb=document.getElementById('sidebar'),ov=document.getElementById('sidebarOverlay');
        var open=sb.classList.toggle('open');
        ov.classList.toggle('show',open);
        document.body.style.overflow=open?'hidden':'';
    };
    document.getElementById('sidebarOverlay').addEventListener('click',function(){
        document.getElementById('sidebar').classList.remove('open');
        this.classList.remove('show');
        document.body.style.overflow='';
    });
    window.addEventListener('resize',function(){ if(window.innerWidth>900){ document.getElementById('sidebar').classList.remove('open'); document.getElementById('sidebarOverlay').classList.remove('show'); document.body.style.overflow=''; } });

    /* Live search with debounce */
    var searchInp = document.getElementById('searchInp');
    var timer;
    searchInp.addEventListener('input', function(){
        clearTimeout(timer);
        timer = setTimeout(function(){
            document.getElementById('filterForm').submit();
        }, 600);
    });
}());
</script>
</body>
</html>