<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/StudyStreak.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Achievement.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Leaderboard.php';

Auth::requireStudent();
$userId = (int)$_SESSION['user_id'];

$user      = User::findById($userId);
$firstName = trim($user['first_name'] ?? 'Student');
$lastName  = trim($user['last_name']  ?? '');
$fullName  = trim($firstName . ' ' . $lastName) ?: 'Student';

/* ── Stats ── */
$stats          = User::getStats($userId) ?? [];
$bestScore      = intval($stats['best_score']      ?? 0);
$avgScore       = intval($stats['avg_score']       ?? 0);
$improvement    = intval($stats['improvement']     ?? 0);
$totalQuestions = intval($stats['total_questions'] ?? 0);
$totalTests     = intval($stats['total_tests']     ?? 0);

/* ── Streak ── */
$currentStreak  = StudyStreak::getCurrent($userId);
$longestStreak  = StudyStreak::getLongest($userId);
$streakData     = StudyStreak::get($userId);
$totalStudyDays = intval($streakData['total_study_days'] ?? 0);

/* ── Achievements ── */
$allAchievements      = Achievement::getAll($userId) ?? [];
$unlockedAchievements = array_values(array_filter($allAchievements, fn($a) => !empty($a['unlocked'])));
$lockedAchievements   = array_values(array_filter($allAchievements, fn($a) =>  empty($a['unlocked'])));
$totalAch             = count($allAchievements);
$unlockedCount        = count($unlockedAchievements);
$achProgressPct       = $totalAch > 0 ? round(($unlockedCount / $totalAch) * 100) : 0;

/* ── XP & Rank ── */
$rankData   = Leaderboard::getUserRank($userId);
$userRank   = intval($rankData['rank']       ?? 0);
$totalXP    = intval($rankData['xp']         ?? 0);
$percentile = intval($rankData['percentile'] ?? 0);

/* ── Level ── */
function xpThreshold(int $l): int { return $l <= 1 ? 0 : (int)(500 * ($l-1) * $l / 2); }
function levelFromXp(int $xp): int { $l = 1; while (xpThreshold($l+1) <= $xp) $l++; return $l; }
$myLevel    = levelFromXp($totalXP);
$xpForNext  = xpThreshold($myLevel + 1);
$xpForCur   = xpThreshold($myLevel);
$levelRange = $xpForNext - $xpForCur;
$levelPct   = $levelRange > 0 ? min(100, round(($totalXP - $xpForCur) / $levelRange * 100)) : 100;

/* ── Category accuracy ── */
$mathAccuracy = $rwAccuracy = 0;
if (class_exists('CategoryPerformance')) {
    try {
        $mp = CategoryPerformance::get($userId, 'math')            ?? [];
        $rp = CategoryPerformance::get($userId, 'reading_writing') ?? [];
        $mathAccuracy = intval($mp['avg_score'] ?? 0);
        $rwAccuracy   = intval($rp['avg_score'] ?? 0);
    } catch (Throwable) {}
}

$joinDate      = $user['created_at'] ?? null;
$joinFormatted = $joinDate ? date('M Y', strtotime($joinDate)) : 'Recently';
$improvSign    = $improvement > 0 ? '+' : '';

function tierColor(string $t): string {
    return match(strtolower($t)) { 'diamond'=>'#1fe290','gold'=>'#f5a623','silver'=>'#a8a8b8', default=>'#cd7f32' };
}
function tierLabel(string $t): string {
    return match(strtolower($t)) { 'diamond'=>'Diamond','gold'=>'Gold','silver'=>'Silver', default=>'Bronze' };
}

$activePage  = 'profile';
$topbarTitle = htmlspecialchars($fullName);
$topbarSub   = 'Level ' . $myLevel . ' · ' . number_format($totalXP) . ' XP';

$topbarExtra = '
<a href="/profile/settings/" style="
    display:inline-flex;align-items:center;gap:7px;
    padding:9px 18px;background:var(--dk);color:#fff;
    font-family:var(--ff);font-size:.8125rem;font-weight:700;
    border-radius:10px;text-decoration:none;border:none;cursor:pointer;
    transition:all .2s;
" onmouseover="this.style.background=\'#1a3f3c\';this.style.transform=\'translateY(-1px)\'"
   onmouseout="this.style.background=\'var(--dk)\';this.style.transform=\'\'">
    <svg viewBox="0 0 24 24" style="width:13px;height:13px;stroke:#1fe290;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round">
        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
        <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
    </svg>
    Edit Profile
</a>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($fullName) ?> — Profile · Avidmock SAT</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<link rel="icon" type="image/png" sizes="16x16"   href="/favicon-16x16.png">
<link rel="icon" type="image/png" sizes="32x32"   href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="192x192" href="/android-chrome-192x192.png">
<link rel="icon" type="image/png" sizes="512x512" href="/android-chrome-512x512.png">
<link rel="apple-touch-icon" sizes="180x180"      href="/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════
   DESIGN TOKENS
═══════════════════════════════════════════════ */
:root {
    --dk:  #143230;
    --dk2: #1a3f3c;
    --dk3: #0e2624;
    --ac:  #1fe290;
    --ac2: #17c87a;
    --ac3: rgba(31,226,144,.12);
    --tx:  #1a1a2e;
    --tx2: #4a4a5a;
    --tx3: #8a8a9a;
    --bg:  #f4f8f7;
    --bg2: #ffffff;
    --bd:  #e2ebe9;
    --bd2: #d0dbd8;
    --warn:  #f59e0b;
    --err:   #ef4444;
    --purp:  #a855f7;
    --ff: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    --sidebar-w: 260px;
    --topbar-h:  64px;
    --radius:    16px;
    --radius-sm: 10px;
    --radius-lg: 22px;
    --shadow-sm: 0 1px 4px rgba(20,50,48,.06), 0 4px 16px rgba(20,50,48,.05);
    --shadow-md: 0 4px 16px rgba(20,50,48,.09), 0 12px 40px rgba(20,50,48,.07);
    --shadow-lg: 0 12px 40px rgba(20,50,48,.14), 0 32px 80px rgba(20,50,48,.09);
    --pad:    30px;
    --pad-sm: 18px;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body {
    font-family: var(--ff);
    background: var(--bg);
    color: var(--tx);
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    overflow-x: hidden;
    min-height: 100vh;
}
a { text-decoration: none; color: inherit; }
button { font-family: var(--ff); cursor: pointer; border: none; background: none; }
img { display: block; }

/* ═══════════════════════════════════════════════
   SCROLL REVEAL
═══════════════════════════════════════════════ */
.sr {
    opacity: 0;
    transform: translateY(20px);
    transition: opacity .55s cubic-bezier(.16,1,.3,1), transform .55s cubic-bezier(.16,1,.3,1);
}
.sr.v { opacity: 1; transform: none; }
.d1 { transition-delay: .05s; }
.d2 { transition-delay: .11s; }
.d3 { transition-delay: .17s; }
.d4 { transition-delay: .23s; }
.d5 { transition-delay: .29s; }
.d6 { transition-delay: .35s; }

/* ═══════════════════════════════════════════════
   LAYOUT SHELL
═══════════════════════════════════════════════ */
.main-content {
    margin-left: var(--sidebar-w);
    margin-top: var(--topbar-h);
    min-height: calc(100vh - var(--topbar-h));
}
.sidebar-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,.45);
    z-index: 250;
    opacity: 0;
    transition: opacity .28s;
    pointer-events: none;
}
.sidebar-overlay.show { opacity: 1; pointer-events: all; }

/* ═══════════════════════════════════════════════
   HERO  —  full-width dark band
═══════════════════════════════════════════════ */
.hero {
    background: var(--dk);
    padding: 2.75rem var(--pad) 0;
    position: relative;
    overflow: hidden;
}

/* Layered background texture */
.hero-grid {
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(31,226,144,.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(31,226,144,.03) 1px, transparent 1px);
    background-size: 38px 38px;
    pointer-events: none;
}
.hero-radial-1 {
    position: absolute;
    top: -140px; right: -100px;
    width: 560px; height: 560px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(31,226,144,.08) 0%, transparent 65%);
    pointer-events: none;
}
.hero-radial-2 {
    position: absolute;
    bottom: -80px; left: 10%;
    width: 320px; height: 320px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(31,226,144,.035) 0%, transparent 65%);
    pointer-events: none;
}

.hero-inner {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: flex-end;
    gap: 28px;
    padding-bottom: 28px;
    flex-wrap: wrap;
}

/* Avatar */
.hero-avatar-wrap {
    flex-shrink: 0;
    position: relative;
}
.hero-avatar {
    width: 88px; height: 88px;
    border-radius: 22px;
    background: linear-gradient(135deg, var(--ac), #0da367);
    display: flex; align-items: center; justify-content: center;
    font-size: 2.25rem;
    font-weight: 800;
    color: var(--dk);
    border: 3px solid rgba(31,226,144,.3);
    box-shadow: 0 0 0 6px rgba(31,226,144,.08);
    overflow: hidden;
    flex-shrink: 0;
    letter-spacing: -.02em;
}
.hero-avatar img { width: 100%; height: 100%; object-fit: cover; }

.hero-avatar-badge {
    position: absolute;
    bottom: -6px; right: -6px;
    padding: 3px 9px;
    border-radius: 50px;
    background: var(--ac);
    color: var(--dk);
    font-size: .5rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .4px;
    border: 2px solid var(--dk);
    white-space: nowrap;
}

/* Name + meta block */
.hero-meta { flex: 1; min-width: 200px; }

.hero-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 11px;
    border-radius: 50px;
    background: rgba(31,226,144,.1);
    border: 1px solid rgba(31,226,144,.18);
    font-size: .5rem;
    font-weight: 800;
    color: var(--ac);
    text-transform: uppercase;
    letter-spacing: .7px;
    margin-bottom: 10px;
}
.hero-eyebrow-dot {
    width: 5px; height: 5px;
    border-radius: 50%;
    background: var(--ac);
    animation: eyeDot 1.4s ease-in-out infinite;
}
@keyframes eyeDot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.3;transform:scale(.5)} }

.hero-name {
    font-size: clamp(1.875rem, 4vw, 3rem);
    font-weight: 800;
    color: #fff;
    letter-spacing: -.04em;
    line-height: 1.05;
    margin-bottom: 12px;
}
.hero-name em { font-style: normal; color: var(--ac); }

.hero-tags {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.hero-tag {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 11px;
    border-radius: 50px;
    font-size: .6875rem;
    font-weight: 600;
    background: rgba(255,255,255,.07);
    border: 1px solid rgba(255,255,255,.1);
    color: rgba(255,255,255,.5);
}
.hero-tag svg {
    width: 10px; height: 10px;
    stroke: currentColor; fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
    flex-shrink: 0;
}
.hero-tag-ac   { background: rgba(31,226,144,.12); border-color: rgba(31,226,144,.22); color: rgba(31,226,144,.85); }
.hero-tag-warn { background: rgba(245,158,11,.1);  border-color: rgba(245,158,11,.2);  color: rgba(245,158,11,.85); }
.hero-tag-purp { background: rgba(168,85,247,.1);  border-color: rgba(168,85,247,.2);  color: rgba(168,85,247,.85); }

/* Edit button (right-aligned on hero row) */
.hero-actions { flex-shrink: 0; align-self: flex-start; padding-top: 4px; }
.btn-edit {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 10px 20px;
    background: rgba(255,255,255,.07);
    border: 1.5px solid rgba(255,255,255,.14);
    border-radius: 11px;
    font-family: var(--ff);
    font-size: .8125rem;
    font-weight: 700;
    color: rgba(255,255,255,.65);
    text-decoration: none;
    transition: background .2s, border-color .2s, color .2s, transform .2s;
    white-space: nowrap;
}
.btn-edit:hover {
    background: rgba(255,255,255,.12);
    border-color: rgba(255,255,255,.22);
    color: #fff;
    transform: translateY(-1px);
}
.btn-edit svg {
    width: 12px; height: 12px;
    stroke: var(--ac); fill: none;
    stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round;
    flex-shrink: 0;
}

/* ═══════════════════════════════════════════════
   STAT STRIP  — attached to bottom of hero
═══════════════════════════════════════════════ */
.hero-strip {
    position: relative; z-index: 1;
    display: flex;
    background: rgba(0,0,0,.15);
    border-top: 1px solid rgba(255,255,255,.05);
    overflow-x: auto;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
}
.hero-strip::-webkit-scrollbar { display: none; }

.hs-cell {
    flex: 1;
    min-width: 110px;
    padding: 18px 22px;
    border-right: 1px solid rgba(255,255,255,.05);
    transition: background .2s;
    flex-shrink: 0;
}
.hs-cell:last-child { border-right: none; }
.hs-cell:hover { background: rgba(255,255,255,.03); }

.hs-val {
    font-size: 1.625rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: -.04em;
    line-height: 1;
    margin-bottom: 4px;
}
.hs-val.ac { color: var(--ac); }
.hs-val sup {
    font-size: .9rem;
    font-weight: 600;
    color: rgba(255,255,255,.3);
    letter-spacing: 0;
}
.hs-key {
    font-size: .4875rem;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: rgba(255,255,255,.22);
}
.hs-sub {
    font-size: .6875rem;
    color: rgba(255,255,255,.3);
    margin-top: 3px;
    font-weight: 500;
}

/* ═══════════════════════════════════════════════
   TABS  —  underline style
═══════════════════════════════════════════════ */
.tabs-wrap {
    background: var(--bg2);
    border-bottom: 1px solid var(--bd);
    padding: 0 var(--pad);
    display: flex;
    overflow-x: auto;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
    position: sticky;
    top: var(--topbar-h);
    z-index: 100;
    box-shadow: 0 1px 0 var(--bd), 0 4px 12px rgba(20,50,48,.04);
}
.tabs-wrap::-webkit-scrollbar { display: none; }

.tab-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 16px 4px;
    margin-right: 28px;
    font-family: var(--ff);
    font-size: .875rem;
    font-weight: 600;
    color: var(--tx3);
    background: none;
    border: none;
    border-bottom: 2.5px solid transparent;
    cursor: pointer;
    white-space: nowrap;
    transition: color .2s, border-color .2s;
    margin-bottom: -1px;
}
.tab-btn:last-child { margin-right: 0; }
.tab-btn svg {
    width: 15px; height: 15px;
    stroke: currentColor; fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
    flex-shrink: 0;
    opacity: .6;
    transition: opacity .2s;
}
.tab-btn:hover { color: var(--tx2); }
.tab-btn:hover svg { opacity: 1; }
.tab-btn.active {
    color: var(--dk);
    border-bottom-color: var(--ac);
    font-weight: 800;
}
.tab-btn.active svg { opacity: 1; }

.tab-badge {
    display: inline-flex;
    align-items: center;
    padding: 1px 7px;
    border-radius: 50px;
    font-size: .5rem;
    font-weight: 800;
    background: var(--ac);
    color: var(--dk);
    letter-spacing: .2px;
}

/* ═══════════════════════════════════════════════
   CONTENT AREA
═══════════════════════════════════════════════ */
.content-area {
    padding: 28px var(--pad) 88px;
    max-width: 1180px;
}
.tab-panel { display: none; }
.tab-panel.active { display: block; }

/* ═══════════════════════════════════════════════
   XP PROGRESS CARD
═══════════════════════════════════════════════ */
.xp-card {
    background: var(--bg2);
    border: 1px solid var(--bd);
    border-radius: var(--radius-lg);
    padding: 1.25rem 1.5rem;
    margin-bottom: 18px;
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    transition: box-shadow .25s;
}
.xp-card:hover { box-shadow: var(--shadow-md); }
.xp-card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 11px;
    gap: 10px;
    flex-wrap: wrap;
}
.xp-level-label {
    font-size: 1rem;
    font-weight: 800;
    color: var(--tx);
    letter-spacing: -.02em;
}
.xp-level-label span {
    font-size: .875rem;
    font-weight: 500;
    color: var(--tx3);
}
.xp-nums {
    font-size: .8125rem;
    font-weight: 700;
    color: var(--ac2);
}
.xp-track {
    height: 8px;
    background: var(--bd);
    border-radius: 4px;
    overflow: hidden;
    position: relative;
}
.xp-fill {
    height: 100%;
    border-radius: 4px;
    background: linear-gradient(90deg, var(--ac2), var(--ac), #5effc4);
    transition: width 1.7s cubic-bezier(.16,1,.3,1);
    position: relative;
    overflow: hidden;
}
.xp-fill::after {
    content: '';
    position: absolute; inset: 0;
    background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,.35) 50%, transparent 100%);
    animation: shimmer 2.5s ease-in-out infinite;
}
@keyframes shimmer { 0%{transform:translateX(-100%)} 100%{transform:translateX(250%)} }

.xp-foot {
    display: flex;
    justify-content: space-between;
    font-size: .6875rem;
    color: var(--tx3);
    font-weight: 600;
    margin-top: 8px;
}

/* ═══════════════════════════════════════════════
   STAT CARDS GRID
═══════════════════════════════════════════════ */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 20px;
}
.stat-card {
    background: var(--bg2);
    border: 1px solid var(--bd);
    border-radius: var(--radius-lg);
    padding: 1.25rem 1.25rem 1.125rem;
    display: flex;
    flex-direction: column;
    gap: 0;
    text-decoration: none;
    color: inherit;
    transition: transform .28s cubic-bezier(.16,1,.3,1), box-shadow .28s, border-color .22s;
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}
.stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 2.5px;
    background: transparent;
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
    transition: background .3s;
}
.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-md);
    border-color: rgba(31,226,144,.25);
}
.stat-card:hover::before { background: linear-gradient(90deg, var(--ac), transparent); }

.sc-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
}
.sc-ico {
    width: 40px; height: 40px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.sc-ico svg {
    width: 18px; height: 18px;
    fill: none; stroke-width: 1.9;
    stroke-linecap: round; stroke-linejoin: round;
    stroke: currentColor;
}
.ic-green  { background: rgba(31,226,144,.1);  color: var(--ac2); }
.ic-fire   { background: rgba(245,158,11,.09); color: var(--warn); }
.ic-star   { background: rgba(168,85,247,.09); color: var(--purp); }
.ic-dk     { background: rgba(20,50,48,.07);   color: var(--dk); }

.sc-label {
    font-size: .5625rem;
    font-weight: 800;
    color: var(--tx3);
    text-transform: uppercase;
    letter-spacing: .6px;
    margin-bottom: 5px;
}
.sc-val {
    font-size: 2rem;
    font-weight: 800;
    color: var(--tx);
    letter-spacing: -.05em;
    line-height: 1;
    margin-bottom: 6px;
}
.sc-val.ac { color: var(--ac2); }
.sc-val small {
    font-size: 1.1rem;
    color: var(--tx3);
    font-weight: 600;
    letter-spacing: -.02em;
}
.sc-chip {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 3px 9px;
    border-radius: 50px;
    font-size: .625rem;
    font-weight: 700;
    margin-top: 2px;
}
.chip-up   { background: rgba(31,226,144,.09);  color: #0a6e42; }
.chip-down { background: rgba(239,68,68,.07);   color: var(--err); }
.chip-flat { background: var(--bg);             color: var(--tx3); }
.chip-warn { background: rgba(245,158,11,.09);  color: #92600a; }

/* ═══════════════════════════════════════════════
   TWO-COLUMN LAYOUT
═══════════════════════════════════════════════ */
.two-col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

/* ═══════════════════════════════════════════════
   GENERIC CARD COMPONENT
═══════════════════════════════════════════════ */
.card {
    background: var(--bg2);
    border: 1px solid var(--bd);
    border-radius: var(--radius-lg);
    overflow: hidden;
    margin-bottom: 16px;
    box-shadow: var(--shadow-sm);
    transition: box-shadow .25s, border-color .2s;
}
.card:last-child { margin-bottom: 0; }
.card:hover { box-shadow: var(--shadow-md); border-color: var(--bd2); }

.card-hd {
    padding: 1rem 1.375rem;
    border-bottom: 1px solid var(--bd);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}
.card-hd-left {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}
.card-hd-ico {
    width: 32px; height: 32px;
    border-radius: 9px;
    background: rgba(20,50,48,.06);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.card-hd-ico svg {
    width: 14px; height: 14px;
    stroke: var(--dk); fill: none;
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
}
.card-hd-title { font-size: .9375rem; font-weight: 800; color: var(--tx); letter-spacing: -.01em; }
.card-hd-sub   { font-size: .6875rem; color: var(--tx3); margin-top: 1px; }
.card-link {
    font-size: .75rem;
    font-weight: 700;
    color: var(--ac2);
    white-space: nowrap;
    transition: opacity .2s;
    flex-shrink: 0;
}
.card-link:hover { opacity: .65; }

.card-body { padding: 1.25rem 1.375rem; }

/* ═══════════════════════════════════════════════
   SCORE PANEL — dark
═══════════════════════════════════════════════ */
.score-panel {
    background: var(--dk);
    border-radius: var(--radius-lg);
    padding: 1.75rem;
    margin-bottom: 16px;
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow-md);
}
.score-panel::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(31,226,144,.025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(31,226,144,.025) 1px, transparent 1px);
    background-size: 30px 30px;
    pointer-events: none;
}
.score-inner {
    position: relative; z-index: 1;
    display: flex;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
}

/* SVG ring */
.ring-wrap { position: relative; flex-shrink: 0; }
.ring-center {
    position: absolute; inset: 0;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
}
.ring-num {
    font-size: 1.375rem;
    font-weight: 800;
    color: var(--ac);
    letter-spacing: -.04em;
    line-height: 1;
}
.ring-lbl {
    font-size: .4375rem;
    font-weight: 700;
    letter-spacing: .7px;
    text-transform: uppercase;
    color: rgba(255,255,255,.22);
    margin-top: 3px;
}

.score-info { flex: 1; min-width: 160px; }
.score-title {
    font-size: 1.0625rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: -.025em;
    margin-bottom: 4px;
}
.score-avg {
    font-size: .8125rem;
    color: rgba(255,255,255,.35);
    margin-bottom: 16px;
    font-weight: 500;
}
.score-avg strong { color: var(--ac); font-weight: 700; }

.sbars { display: flex; flex-direction: column; gap: 10px; }
.sbar-row { display: flex; align-items: center; gap: 10px; }
.sbar-lbl {
    font-size: .6875rem;
    font-weight: 700;
    color: rgba(255,255,255,.3);
    width: 40px;
    flex-shrink: 0;
}
.sbar-track {
    flex: 1;
    height: 5px;
    background: rgba(255,255,255,.07);
    border-radius: 3px;
    overflow: hidden;
}
.sbar-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 1.5s cubic-bezier(.16,1,.3,1);
}
.sf-math { background: linear-gradient(90deg, var(--ac2), var(--ac)); }
.sf-rw   { background: rgba(31,226,144,.55); }
.sbar-pct {
    font-size: .6875rem;
    font-weight: 800;
    color: rgba(255,255,255,.3);
    width: 28px;
    text-align: right;
    flex-shrink: 0;
}

/* ═══════════════════════════════════════════════
   SKILL BARS
═══════════════════════════════════════════════ */
.skill-list { display: flex; flex-direction: column; gap: 16px; }
.skill-item-top {
    display: flex;
    justify-content: space-between;
    margin-bottom: 6px;
}
.skill-lbl { font-size: .8125rem; font-weight: 700; color: var(--tx2); }
.skill-pct { font-size: .75rem; font-weight: 800; color: var(--tx3); }
.skill-pct.hi { color: var(--ac2); }
.skill-track {
    height: 5px;
    background: var(--bd);
    border-radius: 3px;
    overflow: hidden;
}
.skill-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 1.45s cubic-bezier(.16,1,.3,1);
}

/* ═══════════════════════════════════════════════
   STREAK CARD
═══════════════════════════════════════════════ */
.streak-nums {
    display: flex;
    gap: 0;
    border: 1px solid var(--bd);
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 16px;
}
.streak-num-cell {
    flex: 1;
    padding: 14px 8px;
    text-align: center;
    border-right: 1px solid var(--bd);
    transition: background .2s;
}
.streak-num-cell:last-child { border-right: none; }
.streak-num-cell:hover { background: var(--bg); }

.streak-big {
    font-size: 2rem;
    font-weight: 800;
    letter-spacing: -.05em;
    line-height: 1;
    margin-bottom: 4px;
}
.streak-big.warn { color: var(--warn); }
.streak-big.mute { color: var(--tx); }

.streak-key {
    font-size: .4875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .8px;
    color: var(--tx3);
}
.streak-msg {
    padding: 11px 14px;
    background: rgba(245,158,11,.06);
    border: 1px solid rgba(245,158,11,.14);
    border-radius: 11px;
    font-size: .8125rem;
    color: var(--tx2);
    font-weight: 500;
    line-height: 1.55;
}

/* ═══════════════════════════════════════════════
   ACHIEVEMENTS TAB
═══════════════════════════════════════════════ */
.ach-header-card {
    background: var(--dk);
    border-radius: var(--radius-lg);
    padding: 1.5rem 1.75rem;
    margin-bottom: 20px;
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow-md);
}
.ach-header-card::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(31,226,144,.025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(31,226,144,.025) 1px, transparent 1px);
    background-size: 34px 34px;
    pointer-events: none;
}
.ach-header-inner {
    position: relative; z-index: 1;
    display: flex;
    align-items: center;
    gap: 0;
    flex-wrap: wrap;
}
.ach-stat-cell {
    padding: 6px 24px 6px 0;
    margin-right: 24px;
    border-right: 1px solid rgba(255,255,255,.07);
}
.ach-stat-cell:last-of-type { border-right: none; }
.ach-stat-num {
    font-size: 1.625rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: -.04em;
    line-height: 1;
}
.ach-stat-num.ac { color: var(--ac); }
.ach-stat-key {
    font-size: .4875rem;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: rgba(255,255,255,.22);
    margin-top: 3px;
}
.ach-prog-wrap {
    flex: 1;
    min-width: 180px;
    margin-top: 0;
}
.ach-prog-top {
    display: flex;
    justify-content: space-between;
    font-size: .6875rem;
    font-weight: 600;
    color: rgba(255,255,255,.3);
    margin-bottom: 8px;
}
.ach-prog-top span:last-child { color: var(--ac); font-weight: 800; }
.ach-prog-track { height: 7px; background: rgba(255,255,255,.07); border-radius: 4px; overflow: hidden; }
.ach-prog-fill  {
    height: 100%;
    border-radius: 4px;
    background: linear-gradient(90deg, var(--ac2), var(--ac));
    transition: width 1.55s cubic-bezier(.16,1,.3,1);
}

.section-divider {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: .5625rem;
    font-weight: 800;
    color: var(--tx3);
    text-transform: uppercase;
    letter-spacing: .9px;
    margin: 24px 0 14px;
}
.section-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--bd);
}

.ach-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(185px, 1fr));
    gap: 12px;
}

.ach-card {
    background: var(--bg2);
    border: 1.5px solid var(--bd);
    border-radius: var(--radius-lg);
    padding: 1.25rem 1rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 8px;
    position: relative;
    overflow: hidden;
    transition: transform .28s cubic-bezier(.16,1,.3,1), box-shadow .28s, border-color .2s;
    box-shadow: var(--shadow-sm);
}
.ach-card.unlocked:hover {
    transform: translateY(-4px) scale(1.015);
    box-shadow: var(--shadow-md);
    border-color: transparent;
}
.ach-card.locked {
    opacity: .5;
    filter: grayscale(.35);
}

.ach-ico-wrap {
    width: 56px; height: 56px;
    border-radius: 15px;
    display: flex; align-items: center; justify-content: center;
    position: relative;
    transition: transform .3s cubic-bezier(.16,1,.3,1);
    flex-shrink: 0;
}
.ach-card.unlocked:hover .ach-ico-wrap { transform: scale(1.1) rotate(-4deg); }
.ach-ico-wrap svg {
    width: 26px; height: 26px;
    fill: none; stroke-width: 1.8;
    stroke-linecap: round; stroke-linejoin: round;
}

.ach-check-badge {
    position: absolute;
    bottom: -4px; right: -4px;
    width: 19px; height: 19px;
    border-radius: 50%;
    background: var(--ac);
    border: 2px solid var(--bg2);
    display: flex; align-items: center; justify-content: center;
}
.ach-check-badge svg {
    width: 8px; height: 8px;
    stroke: var(--dk); fill: none;
    stroke-width: 3; stroke-linecap: round; stroke-linejoin: round;
}

.ach-name {
    font-size: .8125rem;
    font-weight: 800;
    color: var(--tx);
    line-height: 1.25;
    letter-spacing: -.01em;
}
.ach-card.locked .ach-name { color: var(--tx3); }
.ach-desc {
    font-size: .6875rem;
    color: var(--tx3);
    line-height: 1.55;
}
.ach-tier-chip {
    display: inline-flex;
    padding: 2px 9px;
    border-radius: 50px;
    font-size: .4875rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .5px;
    border: 1px solid transparent;
}
.ach-xp {
    font-size: .5875rem;
    font-weight: 700;
    color: var(--tx3);
}

/* ═══════════════════════════════════════════════
   ACTIVITY TIMELINE
═══════════════════════════════════════════════ */
.timeline { display: flex; flex-direction: column; position: relative; }
.timeline::before {
    content: '';
    position: absolute;
    left: 16px;
    top: 6px; bottom: 6px;
    width: 1.5px;
    background: linear-gradient(180deg, var(--ac3), var(--bd) 80%, transparent);
}

.tl-item {
    display: flex;
    gap: 14px;
    padding: 10px 0;
    align-items: flex-start;
}
.tl-dot {
    width: 32px; height: 32px;
    border-radius: 50%;
    background: var(--bg2);
    border: 1.5px solid var(--bd);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    z-index: 1;
    transition: transform .2s, box-shadow .2s;
}
.tl-item:hover .tl-dot { transform: scale(1.1); box-shadow: 0 0 0 4px rgba(31,226,144,.1); }
.tl-dot.type-quiz {
    border-color: rgba(31,226,144,.4);
    background: rgba(31,226,144,.06);
}
.tl-dot.type-test {
    border-color: rgba(245,158,11,.35);
    background: rgba(245,158,11,.06);
}
.tl-dot svg {
    width: 13px; height: 13px;
    fill: none; stroke-width: 2.2;
    stroke-linecap: round; stroke-linejoin: round;
}
.tl-dot.type-quiz svg { stroke: var(--ac2); }
.tl-dot.type-test svg { stroke: var(--warn); }

.tl-body { flex: 1; padding-top: 4px; min-width: 0; }
.tl-title {
    font-size: .9375rem;
    font-weight: 700;
    color: var(--tx);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.tl-sub   { font-size: .75rem; color: var(--tx3); margin-top: 2px; }
.tl-score {
    display: inline-flex;
    padding: 2px 9px;
    border-radius: 50px;
    font-size: .625rem;
    font-weight: 700;
    margin-top: 5px;
    background: rgba(31,226,144,.08);
    color: #0a6b40;
}

.tl-date {
    font-size: .625rem;
    color: var(--tx3);
    font-weight: 600;
    white-space: nowrap;
    padding-top: 7px;
    flex-shrink: 0;
}

/* ═══════════════════════════════════════════════
   EMPTY STATE
═══════════════════════════════════════════════ */
.empty-state {
    text-align: center;
    padding: 3.5rem 1.5rem;
}
.empty-state svg {
    width: 44px; height: 44px;
    stroke: var(--bd2); fill: none;
    stroke-width: 1.4; stroke-linecap: round; stroke-linejoin: round;
    margin: 0 auto 1.125rem;
    display: block;
}
.empty-state-title { font-size: 1rem; font-weight: 800; color: var(--tx); margin-bottom: .375rem; }
.empty-state-sub   { font-size: .875rem; color: var(--tx3); line-height: 1.7; }

/* ═══════════════════════════════════════════════
   RESPONSIVE BREAKPOINTS
   ─────────────────────────────────────────────
   ≥ 1100  : Full layout, 4-col stats
   ≤ 1100  : 2-col stats, keep two-col
   ≤  900  : Remove sidebar margin, mobile nav
   ≤  768  : two-col becomes 1-col
   ≤  640  : Hero adapts, stat grid 2-col
   ≤  480  : Tightest — strip scrolls, minimal pads
═══════════════════════════════════════════════ */

@media (max-width: 1100px) {
    .stat-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 900px) {
    .main-content { margin-left: 0; }
    .hero { padding: 2.25rem var(--pad-sm) 0; }
    .hero-strip { }
    .tabs-wrap { padding: 0 var(--pad-sm); top: var(--topbar-h); }
    .content-area { padding: 22px var(--pad-sm) 80px; }
    .card-body { padding: 1.125rem; }
    .card-hd   { padding: .9375rem 1.125rem; }
    .score-panel { padding: 1.375rem; }
}

@media (max-width: 768px) {
    .two-col { grid-template-columns: 1fr; gap: 16px; }
    .hero-inner { gap: 18px; padding-bottom: 22px; }
}

@media (max-width: 640px) {
    .hero { padding: 1.75rem var(--pad-sm) 0; }
    .hero-name { font-size: 1.875rem; }
    .hero-avatar { width: 72px; height: 72px; font-size: 1.75rem; border-radius: 18px; }
    .hero-avatar-badge { font-size: .4375rem; padding: 2px 7px; }

    /* Strip: 2 per row instead of 5 */
    .hs-cell { flex: 0 0 50%; border-right: none !important; border-bottom: 1px solid rgba(255,255,255,.05); }
    .hs-cell:nth-child(odd) { border-right: 1px solid rgba(255,255,255,.05) !important; }

    .stat-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
    .sc-val { font-size: 1.625rem; }

    .ach-header-inner { gap: 8px 0; }
    .ach-stat-cell { padding: 4px 14px 4px 0; margin-right: 14px; }
    .ach-prog-wrap { padding-left: 0; margin-top: 10px; flex: 0 0 100%; }
    .ach-grid { grid-template-columns: 1fr 1fr; gap: 10px; }

    .score-inner { flex-direction: column; }
    .ring-wrap { align-self: center; }
    .score-info { width: 100%; }

    .xp-card-top { flex-direction: column; align-items: flex-start; gap: 4px; }
}

@media (max-width: 480px) {
    .hero { padding: 1.375rem var(--pad-sm) 0; }
    .hero-name { font-size: 1.625rem; }
    .hero-inner { gap: 14px; flex-wrap: nowrap; }
    .hero-tags { gap: 4px; }
    .hero-tag  { padding: 3px 9px; font-size: .625rem; }
    .hero-actions { display: none; } /* hide edit btn, accessible via profile menu */

    .hs-val  { font-size: 1.25rem; }
    .hs-cell { padding: 13px 14px; }

    .tab-btn { padding: 13px 2px; margin-right: 20px; font-size: .8125rem; }

    .stat-grid { gap: 8px; }
    .stat-card { padding: 1rem; }
    .sc-val { font-size: 1.5rem; }
    .sc-ico { width: 34px; height: 34px; border-radius: 10px; }
    .sc-ico svg { width: 15px; height: 15px; }

    .ach-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
    .ach-card { padding: 1rem .75rem; border-radius: 13px; }
    .ach-ico-wrap { width: 48px; height: 48px; border-radius: 13px; }
    .ach-ico-wrap svg { width: 22px; height: 22px; }
    .ach-name { font-size: .75rem; }
    .ach-desc { display: none; } /* too cramped at 480 */

    .streak-big { font-size: 1.5rem; }
    .streak-num-cell { padding: 11px 6px; }

    .tl-title { font-size: .875rem; }
}

/* ── Safe area insets (notched phones) ── */
@supports (padding: max(0px)) {
    .content-area {
        padding-left:   max(var(--pad-sm), env(safe-area-inset-left));
        padding-right:  max(var(--pad-sm), env(safe-area-inset-right));
        padding-bottom: max(88px, calc(88px + env(safe-area-inset-bottom)));
    }
    @media (min-width: 901px) {
        .content-area {
            padding-left:  max(var(--pad), env(safe-area-inset-left));
            padding-right: max(var(--pad), env(safe-area-inset-right));
        }
    }
    .hero         { padding-left:  max(var(--pad-sm), env(safe-area-inset-left)); padding-right: max(var(--pad-sm), env(safe-area-inset-right)); }
    .tabs-wrap    { padding-left:  max(var(--pad-sm), env(safe-area-inset-left)); padding-right: max(var(--pad-sm), env(safe-area-inset-right)); }
    @media (min-width: 901px) {
        .hero      { padding-left: max(var(--pad), env(safe-area-inset-left)); padding-right: max(var(--pad), env(safe-area-inset-right)); }
        .tabs-wrap { padding-left: max(var(--pad), env(safe-area-inset-left)); padding-right: max(var(--pad), env(safe-area-inset-right)); }
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

    <!-- ══════════════════════════════════════
         HERO
    ══════════════════════════════════════ -->
    <section class="hero">
        <div class="hero-grid"></div>
        <div class="hero-radial-1"></div>
        <div class="hero-radial-2"></div>

        <div class="hero-inner">
            <div class="hero-avatar-wrap">
                <div class="hero-avatar">
                    <?php if (!empty($user['avatar_url'])): ?>
                        <img src="<?= htmlspecialchars($user['avatar_url']) ?>" alt="<?= htmlspecialchars($fullName) ?>">
                    <?php else: ?>
                        <?= strtoupper(substr($firstName, 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div class="hero-avatar-badge">Lv <?= $myLevel ?></div>
            </div>

            <div class="hero-meta">
                <div class="hero-eyebrow"><span class="hero-eyebrow-dot"></span>Student Profile</div>
                <h1 class="hero-name">
                    <?= htmlspecialchars($firstName) ?>
                    <?php if ($lastName): ?> <em><?= htmlspecialchars($lastName) ?></em><?php endif; ?>
                </h1>
                <div class="hero-tags">
                    <span class="hero-tag">
                        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        Joined <?= $joinFormatted ?>
                    </span>
                    <span class="hero-tag">
                        <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/></svg>
                        <?= $totalTests ?> tests
                    </span>
                    <?php if ($userRank > 0): ?>
                    <span class="hero-tag hero-tag-purp">
                        <svg viewBox="0 0 24 24"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>
                        Rank #<?= $userRank ?>
                    </span>
                    <?php endif; ?>
                    <span class="hero-tag hero-tag-ac">Level <?= $myLevel ?></span>
                    <?php if ($currentStreak >= 3): ?>
                    <span class="hero-tag hero-tag-warn">
                        <svg viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9z"/></svg>
                        <?= $currentStreak ?>-day streak
                    </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="hero-actions">
                <a href="/profile/settings/" class="btn-edit">
                    <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Edit Profile
                </a>
            </div>
        </div>

        <!-- Stat strip -->
        <div class="hero-strip" id="heroStrip">
            <div class="hs-cell">
                <div class="hs-val ac"><?= $bestScore ?: '—' ?></div>
                <div class="hs-key">Best Score</div>
                <?php if ($improvement): ?>
                <div class="hs-sub"><?= $improvSign . abs($improvement) ?> pts improvement</div>
                <?php endif; ?>
            </div>
            <div class="hs-cell">
                <div class="hs-val"><?= number_format($totalXP) ?></div>
                <div class="hs-key">Total XP</div>
                <div class="hs-sub">Level <?= $myLevel ?> · <?= $levelPct ?>% to next</div>
            </div>
            <div class="hs-cell">
                <div class="hs-val ac"><?= $currentStreak ?></div>
                <div class="hs-key">Day Streak</div>
                <div class="hs-sub">Best <?= $longestStreak ?>d</div>
            </div>
            <div class="hs-cell">
                <div class="hs-val"><?= $unlockedCount ?><sup>/<?= $totalAch ?></sup></div>
                <div class="hs-key">Badges</div>
                <div class="hs-sub"><?= $achProgressPct ?>% collected</div>
            </div>
            <div class="hs-cell">
                <div class="hs-val"><?= $totalQuestions > 999 ? number_format($totalQuestions) : $totalQuestions ?></div>
                <div class="hs-key">Questions Done</div>
                <div class="hs-sub"><?= $totalTests ?> full tests</div>
            </div>
        </div>
    </section>

    <!-- ══════════════════════════════════════
         TABS
    ══════════════════════════════════════ -->
    <nav class="tabs-wrap" role="tablist" aria-label="Profile sections">
        <button class="tab-btn active" onclick="switchTab('overview',this)" role="tab" aria-selected="true" aria-controls="tab-overview">
            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            Overview
        </button>
        <button class="tab-btn" onclick="switchTab('achievements',this)" role="tab" aria-selected="false" aria-controls="tab-achievements">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>
            Achievements
            <?php if ($unlockedCount): ?>
            <span class="tab-badge"><?= $unlockedCount ?></span>
            <?php endif; ?>
        </button>
        <button class="tab-btn" onclick="switchTab('activity',this)" role="tab" aria-selected="false" aria-controls="tab-activity">
            <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            Activity
        </button>
    </nav>

    <!-- ══════════════════════════════════════
         CONTENT
    ══════════════════════════════════════ -->
    <div class="content-area">

        <!-- ─── OVERVIEW ─────────────────── -->
        <div id="tab-overview" class="tab-panel active" role="tabpanel">

            <!-- XP Progress -->
            <div class="xp-card sr">
                <div class="xp-card-top">
                    <span class="xp-level-label">
                        Level <?= $myLevel ?>
                        <span>→ Level <?= $myLevel + 1 ?></span>
                    </span>
                    <span class="xp-nums"><?= number_format($totalXP) ?> / <?= number_format($xpForNext) ?> XP</span>
                </div>
                <div class="xp-track">
                    <div class="xp-fill" data-width="<?= $levelPct ?>"></div>
                </div>
                <div class="xp-foot">
                    <span><?= $levelPct ?>% to next level</span>
                    <span><?= number_format(max(0, $xpForNext - $totalXP)) ?> XP remaining</span>
                </div>
            </div>

            <!-- Stats grid -->
            <div class="stat-grid sr d1">
                <a href="/practice-tests/history/" class="stat-card">
                    <div class="sc-header">
                        <div class="sc-label">Best Score</div>
                        <div class="sc-ico ic-green">
                            <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        </div>
                    </div>
                    <div class="sc-val ac"><?= $bestScore ?: '—' ?></div>
                    <?php $ic = $improvement > 0 ? 'chip-up' : ($improvement < 0 ? 'chip-down' : 'chip-flat'); ?>
                    <span class="sc-chip <?= $ic ?>">
                        <?= $improvement !== 0 ? $improvSign . abs($improvement) . ' pts' : 'No change yet' ?>
                    </span>
                </a>

                <div class="stat-card">
                    <div class="sc-header">
                        <div class="sc-label">Current Streak</div>
                        <div class="sc-ico ic-fire">
                            <svg viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9z"/></svg>
                        </div>
                    </div>
                    <div class="sc-val"><?= $currentStreak ?><small> days</small></div>
                    <span class="sc-chip chip-warn">Best: <?= $longestStreak ?>d</span>
                </div>

                <a href="/achievements/" class="stat-card">
                    <div class="sc-header">
                        <div class="sc-label">Badges Earned</div>
                        <div class="sc-ico ic-star">
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>
                        </div>
                    </div>
                    <div class="sc-val"><?= $unlockedCount ?><small>/<?= $totalAch ?></small></div>
                    <span class="sc-chip chip-flat"><?= $totalAch - $unlockedCount ?> remaining</span>
                </a>

                <div class="stat-card">
                    <div class="sc-header">
                        <div class="sc-label">Questions Done</div>
                        <div class="sc-ico ic-dk">
                            <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                        </div>
                    </div>
                    <div class="sc-val"><?= $totalQuestions > 999 ? number_format($totalQuestions) : ($totalQuestions ?: 0) ?></div>
                    <span class="sc-chip chip-flat"><?= $totalTests ?> full tests</span>
                </div>
            </div>

            <!-- Two-col: score + skills -->
            <div class="two-col sr d2">

                <!-- Left -->
                <div>
                    <!-- Score dark panel -->
                    <div class="score-panel">
                        <div class="score-inner">
                            <div class="ring-wrap">
                                <svg width="96" height="96" viewBox="0 0 100 100">
                                    <circle cx="50" cy="50" r="40" fill="none" stroke="rgba(255,255,255,.06)" stroke-width="7"/>
                                    <circle cx="50" cy="50" r="40" fill="none" stroke="#1fe290" stroke-width="7"
                                            stroke-linecap="round" transform="rotate(-90 50 50)"
                                            data-arc="<?= $bestScore > 0 ? min(100, round(($bestScore/1600)*100)) : 0 ?>"
                                            style="stroke-dasharray:251.3;stroke-dashoffset:251.3"/>
                                </svg>
                                <div class="ring-center">
                                    <div class="ring-num"><?= $bestScore ?: '—' ?></div>
                                    <div class="ring-lbl">Best</div>
                                </div>
                            </div>
                            <div class="score-info">
                                <div class="score-title">Score Progress</div>
                                <div class="score-avg">Average: <strong><?= $avgScore ?: '—' ?></strong></div>
                                <div class="sbars">
                                    <div class="sbar-row">
                                        <div class="sbar-lbl">Math</div>
                                        <div class="sbar-track">
                                            <div class="sbar-fill sf-math" data-width="<?= $mathAccuracy ?>"></div>
                                        </div>
                                        <div class="sbar-pct"><?= $mathAccuracy ?>%</div>
                                    </div>
                                    <div class="sbar-row">
                                        <div class="sbar-lbl">R&amp;W</div>
                                        <div class="sbar-track">
                                            <div class="sbar-fill sf-rw" data-width="<?= $rwAccuracy ?>"></div>
                                        </div>
                                        <div class="sbar-pct"><?= $rwAccuracy ?>%</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Math Skills -->
                    <div class="card sr d3">
                        <div class="card-hd">
                            <div class="card-hd-left">
                                <div class="card-hd-ico">
                                    <svg viewBox="0 0 24 24"><path d="M4 19l8-14 8 14H4z"/></svg>
                                </div>
                                <div>
                                    <div class="card-hd-title">Math Skills</div>
                                    <div class="card-hd-sub">Accuracy by domain</div>
                                </div>
                            </div>
                            <a href="/learn/math/" class="card-link">Practice →</a>
                        </div>
                        <div class="card-body">
                            <div class="skill-list">
                                <?php foreach ([
                                    ['Heart of Algebra',       '#1fe290'],
                                    ['Advanced Math',          '#14c474'],
                                    ['Problem Solving & Data', 'rgba(31,226,144,.65)'],
                                    ['Additional Topics',      'rgba(31,226,144,.42)'],
                                ] as [$lbl, $col]): ?>
                                <div>
                                    <div class="skill-item-top">
                                        <span class="skill-lbl"><?= $lbl ?></span>
                                        <span class="skill-pct <?= $mathAccuracy >= 70 ? 'hi' : '' ?>"><?= $mathAccuracy ?>%</span>
                                    </div>
                                    <div class="skill-track">
                                        <div class="skill-fill" data-width="<?= $mathAccuracy ?>" style="background:<?= $col ?>"></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right -->
                <div>
                    <!-- R&W Skills -->
                    <div class="card sr d2">
                        <div class="card-hd">
                            <div class="card-hd-left">
                                <div class="card-hd-ico">
                                    <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                </div>
                                <div>
                                    <div class="card-hd-title">Reading &amp; Writing Skills</div>
                                    <div class="card-hd-sub">Accuracy by domain</div>
                                </div>
                            </div>
                            <a href="/learn/reading-writing/" class="card-link">Practice →</a>
                        </div>
                        <div class="card-body">
                            <div class="skill-list">
                                <?php foreach ([
                                    ['Information & Ideas',          '#1fe290'],
                                    ['Craft & Structure',            '#14c474'],
                                    ['Expression of Ideas',          'rgba(31,226,144,.65)'],
                                    ['Standard English Conventions', 'rgba(31,226,144,.42)'],
                                ] as [$lbl, $col]): ?>
                                <div>
                                    <div class="skill-item-top">
                                        <span class="skill-lbl"><?= $lbl ?></span>
                                        <span class="skill-pct <?= $rwAccuracy >= 70 ? 'hi' : '' ?>"><?= $rwAccuracy ?>%</span>
                                    </div>
                                    <div class="skill-track">
                                        <div class="skill-fill" data-width="<?= $rwAccuracy ?>" style="background:<?= $col ?>"></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Streak Card -->
                    <div class="card sr d3">
                        <div class="card-hd">
                            <div class="card-hd-left">
                                <div class="card-hd-ico">
                                    <svg viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9z"/></svg>
                                </div>
                                <div>
                                    <div class="card-hd-title">Study Streak</div>
                                    <div class="card-hd-sub">Daily consistency</div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="streak-nums">
                                <div class="streak-num-cell">
                                    <div class="streak-big warn"><?= $currentStreak ?></div>
                                    <div class="streak-key">Current</div>
                                </div>
                                <div class="streak-num-cell">
                                    <div class="streak-big mute"><?= $longestStreak ?></div>
                                    <div class="streak-key">Best Ever</div>
                                </div>
                                <div class="streak-num-cell">
                                    <div class="streak-big mute"><?= $totalStudyDays ?></div>
                                    <div class="streak-key">Total Days</div>
                                </div>
                            </div>
                            <?php if ($currentStreak > 0): ?>
                            <div class="streak-msg">
                                <?php
                                if ($currentStreak >= 30)     echo 'Absolutely on fire — completely unstoppable!';
                                elseif ($currentStreak >= 14) echo 'Two weeks strong — incredible commitment!';
                                elseif ($currentStreak >= 7)  echo 'One full week! You are building a great habit.';
                                elseif ($currentStreak >= 3)  echo 'Great momentum! Keep studying every day.';
                                else                          echo 'Good start! Study tomorrow to keep your streak.';
                                ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /overview -->

        <!-- ─── ACHIEVEMENTS ─────────────── -->
        <div id="tab-achievements" class="tab-panel" role="tabpanel">

            <div class="ach-header-card sr">
                <div class="ach-header-inner">
                    <div class="ach-stat-cell">
                        <div class="ach-stat-num ac"><?= $unlockedCount ?></div>
                        <div class="ach-stat-key">Unlocked</div>
                    </div>
                    <div class="ach-stat-cell">
                        <div class="ach-stat-num"><?= $totalAch ?></div>
                        <div class="ach-stat-key">Total</div>
                    </div>
                    <div class="ach-stat-cell">
                        <div class="ach-stat-num"><?= $achProgressPct ?>%</div>
                        <div class="ach-stat-key">Complete</div>
                    </div>
                    <div class="ach-prog-wrap">
                        <div class="ach-prog-top">
                            <span>Collection progress</span>
                            <span><?= $achProgressPct ?>%</span>
                        </div>
                        <div class="ach-prog-track">
                            <div class="ach-prog-fill" data-width="<?= $achProgressPct ?>"></div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (empty($allAchievements)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>
                <div class="empty-state-title">No badges yet</div>
                <div class="empty-state-sub">Complete quizzes and lessons to start earning badges.</div>
            </div>

            <?php else: ?>
                <?php if (!empty($unlockedAchievements)): ?>
                <div class="section-divider sr">Unlocked Badges</div>
                <div class="ach-grid sr d1">
                    <?php foreach ($unlockedAchievements as $ach):
                        $tc = tierColor($ach['tier'] ?? 'bronze');
                        $tl = tierLabel($ach['tier'] ?? 'bronze');
                    ?>
                    <div class="ach-card unlocked" style="border-color:<?= $tc ?>2e;">
                        <div class="ach-ico-wrap"
                             style="background:<?= $tc ?>18;border:1.5px solid <?= $tc ?>2e;color:<?= $tc ?>">
                            <svg viewBox="0 0 24 24" style="stroke:<?= $tc ?>">
                                <circle cx="12" cy="8" r="6"/>
                                <path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/>
                            </svg>
                            <div class="ach-check-badge">
                                <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                            </div>
                        </div>
                        <div class="ach-name"><?= htmlspecialchars($ach['name'] ?? '') ?></div>
                        <div class="ach-desc"><?= htmlspecialchars($ach['description'] ?? '') ?></div>
                        <span class="ach-tier-chip"
                              style="color:<?= $tc ?>;background:<?= $tc ?>15;border-color:<?= $tc ?>2e">
                            <?= $tl ?>
                        </span>
                        <?php if (!empty($ach['xp_reward'])): ?>
                        <span class="ach-xp">+<?= intval($ach['xp_reward']) ?> XP</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($lockedAchievements)): ?>
                <div class="section-divider sr d2">In Progress</div>
                <div class="ach-grid sr d3">
                    <?php foreach ($lockedAchievements as $ach): ?>
                    <div class="ach-card locked">
                        <div class="ach-ico-wrap" style="background:var(--bd);filter:grayscale(1)">
                            <svg viewBox="0 0 24 24" style="stroke:var(--tx3)">
                                <circle cx="12" cy="8" r="6"/>
                                <path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/>
                            </svg>
                        </div>
                        <div class="ach-name"><?= htmlspecialchars($ach['name'] ?? '') ?></div>
                        <div class="ach-desc"><?= htmlspecialchars($ach['description'] ?? '') ?></div>
                        <span class="ach-tier-chip"
                              style="color:var(--tx3);background:var(--bg);border-color:var(--bd)">
                            <?= tierLabel($ach['tier'] ?? 'bronze') ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>

        </div><!-- /achievements -->

        <!-- ─── ACTIVITY ─────────────────── -->
        <div id="tab-activity" class="tab-panel" role="tabpanel">
            <div class="card sr">
                <div class="card-hd">
                    <div class="card-hd-left">
                        <div class="card-hd-ico">
                            <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        </div>
                        <div>
                            <div class="card-hd-title">Recent Activity</div>
                            <div class="card-hd-sub">Latest sessions &amp; results</div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php
                    $activities = [];
                    try {
                        $stmt = $pdo->prepare("
                            SELECT 'quiz' AS type,
                                   sq.lesson_slug AS title,
                                   sqa.score,
                                   sqa.completed_at AS created_at
                            FROM sat_quiz_attempts sqa
                            JOIN sat_quizzes sq ON sq.id = sqa.quiz_id
                            WHERE sqa.user_id = ? AND sqa.status = 'completed'
                            ORDER BY sqa.completed_at DESC
                            LIMIT 20
                        ");
                        $stmt->execute([$userId]);
                        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Throwable) {}
                    ?>
                    <?php if (empty($activities)): ?>
                    <div class="empty-state">
                        <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        <div class="empty-state-title">No activity yet</div>
                        <div class="empty-state-sub">Complete lessons and quizzes to see your history here.</div>
                    </div>
                    <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($activities as $act): ?>
                        <div class="tl-item">
                            <div class="tl-dot type-quiz">
                                <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/></svg>
                            </div>
                            <div class="tl-body">
                                <div class="tl-title"><?= htmlspecialchars(ucwords(str_replace('-', ' ', $act['title'] ?? ''))) ?></div>
                                <div class="tl-sub">Quiz completed</div>
                                <?php if (isset($act['score'])): ?>
                                <span class="tl-score">Score: <?= intval($act['score']) ?>%</span>
                                <?php endif; ?>
                            </div>
                            <div class="tl-date"><?= $act['created_at'] ? date('M j', strtotime($act['created_at'])) : '' ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div><!-- /activity -->

    </div><!-- /content-area -->
</main>

<script>
(function () {
    'use strict';

    /* ── Sidebar overlay ── */
    var ov = document.getElementById('sidebarOverlay');
    if (ov) {
        ov.addEventListener('click', function () {
            var sb = document.getElementById('sidebar');
            if (sb) sb.classList.remove('open');
            ov.classList.remove('show');
            document.body.style.overflow = '';
        });
    }
    window.addEventListener('resize', function () {
        if (window.innerWidth > 900) {
            var sb = document.getElementById('sidebar');
            if (sb) sb.classList.remove('open');
            if (ov) ov.classList.remove('show');
            document.body.style.overflow = '';
        }
    });

    /* ── Scroll reveal ── */
    var srObs = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (e.isIntersecting) { e.target.classList.add('v'); srObs.unobserve(e.target); }
        });
    }, { threshold: .04, rootMargin: '0px 0px -20px 0px' });
    document.querySelectorAll('.sr').forEach(function (el) { srObs.observe(el); });

    /* ── Animated width bars ── */
    var barObs = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (!e.isIntersecting) return;
            var el = e.target;
            setTimeout(function () { el.style.width = (el.dataset.width || 0) + '%'; }, 180);
            barObs.unobserve(el);
        });
    }, { threshold: .08 });

    function observeBars(ctx) {
        (ctx || document).querySelectorAll('[data-width]').forEach(function (b) {
            barObs.observe(b);
        });
    }
    observeBars();

    /* ── SVG ring animation ── */
    function animRings(ctx) {
        (ctx || document).querySelectorAll('[data-arc]').forEach(function (el) {
            var pct = parseFloat(el.dataset.arc) / 100;
            var r   = parseFloat(el.getAttribute('r') || 40);
            var c   = 2 * Math.PI * r;
            el.style.strokeDasharray  = c;
            el.style.strokeDashoffset = c;
            setTimeout(function () {
                el.style.transition       = 'stroke-dashoffset 1.6s cubic-bezier(.16,1,.3,1)';
                el.style.strokeDashoffset = c * (1 - pct);
            }, 450);
        });
    }
    animRings();

    /* ── Hero strip entrance stagger ── */
    var cells = document.querySelectorAll('.hs-cell');
    cells.forEach(function (c, i) {
        c.style.opacity   = '0';
        c.style.transform = 'translateY(14px)';
        setTimeout(function () {
            c.style.transition = 'opacity .55s cubic-bezier(.16,1,.3,1), transform .55s cubic-bezier(.16,1,.3,1)';
            c.style.opacity    = '1';
            c.style.transform  = 'none';
        }, 300 + i * 70);
    });

    /* ── Hero avatar subtle float ── */
    var avatar = document.querySelector('.hero-avatar');
    if (avatar) {
        avatar.style.animation = 'avatarFloat 5s ease-in-out infinite';
        var style = document.createElement('style');
        style.textContent = '@keyframes avatarFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-5px)}}';
        document.head.appendChild(style);
    }

    /* ── Stagger achievement cards ── */
    function staggerCards(grid) {
        var cards = grid.querySelectorAll('.ach-card');
        cards.forEach(function (c, i) {
            c.style.opacity   = '0';
            c.style.transform = 'translateY(12px) scale(.98)';
            setTimeout(function () {
                c.style.transition = 'opacity .4s cubic-bezier(.16,1,.3,1), transform .4s cubic-bezier(.16,1,.3,1)';
                c.style.opacity    = c.classList.contains('locked') ? '.5' : '1';
                c.style.transform  = 'none';
            }, i * 40);
        });
    }

    /* ── Tab switching ── */
    window.switchTab = function (id, btn) {
        /* Deactivate all */
        document.querySelectorAll('.tab-panel').forEach(function (p) { p.classList.remove('active'); });
        document.querySelectorAll('.tab-btn').forEach(function (b) {
            b.classList.remove('active');
            b.setAttribute('aria-selected', 'false');
        });

        /* Activate selected */
        var panel = document.getElementById('tab-' + id);
        panel.classList.add('active');
        btn.classList.add('active');
        btn.setAttribute('aria-selected', 'true');

        /* Re-trigger bars & rings inside new panel */
        panel.querySelectorAll('[data-width]').forEach(function (b) {
            b.style.width = '0%';
            setTimeout(function () { b.style.width = (b.dataset.width || 0) + '%'; }, 100);
        });
        animRings(panel);

        /* Stagger ach cards if switching to achievements */
        if (id === 'achievements') {
            panel.querySelectorAll('.ach-grid').forEach(staggerCards);
        }

        /* Scroll to top of content on mobile */
        if (window.innerWidth <= 900) {
            var contentEl = document.querySelector('.content-area');
            if (contentEl) {
                setTimeout(function () {
                    contentEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 80);
            }
        }
    };

    /* ── Timeline row entrance ── */
    var tlObs = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (!e.isIntersecting) return;
            var items = e.target.querySelectorAll('.tl-item');
            items.forEach(function (item, i) {
                item.style.opacity   = '0';
                item.style.transform = 'translateX(-10px)';
                setTimeout(function () {
                    item.style.transition = 'opacity .38s cubic-bezier(.16,1,.3,1), transform .38s cubic-bezier(.16,1,.3,1)';
                    item.style.opacity    = '1';
                    item.style.transform  = 'none';
                }, i * 45);
            });
            tlObs.unobserve(e.target);
        });
    }, { threshold: .04 });
    document.querySelectorAll('.timeline').forEach(function (t) { tlObs.observe(t); });

}());
</script>
</body>
</html>