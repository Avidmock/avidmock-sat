<?php
/**
 * achievements/index.php
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Achievement.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/StudyStreak.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Leaderboard.php';

Auth::requireStudent();
$userId    = (int)$_SESSION['user_id'];
$user      = User::findById($userId);
$firstName = trim($user['first_name'] ?? 'Student');
$lastName  = trim($user['last_name']  ?? '');
$fullName  = trim($firstName . ' ' . $lastName) ?: 'Student';

/* ── Achievements ── */
$allAchievements = Achievement::getAll($userId);
$unlockedList    = array_values(array_filter($allAchievements, fn($a) => !empty($a['unlocked'])));
$lockedList      = array_values(array_filter($allAchievements, fn($a) =>  empty($a['unlocked'])));
$unlockedCount   = count($unlockedList);
$totalCount      = count($allAchievements);
$completionPct   = $totalCount > 0 ? round(($unlockedCount / $totalCount) * 100) : 0;

/* ── XP & Rank ── */
$rankData   = Leaderboard::getUserRank($userId);
$totalXp    = (int)($rankData['xp']        ?? 0);
$userRank   = (int)($rankData['rank']       ?? 0);
$percentile = (int)($rankData['percentile'] ?? 0);

/* ── Level helpers ── */
function xpThreshold(int $level): int {
    if ($level <= 1) return 0;
    return (int)(500 * ($level - 1) * $level / 2);
}
function levelFromXp(int $xp): int {
    $lvl = 1;
    while (xpThreshold($lvl + 1) <= $xp) $lvl++;
    return $lvl;
}
$currentLevel  = levelFromXp($totalXp);
$xpForCurrent  = xpThreshold($currentLevel);
$xpForNext     = xpThreshold($currentLevel + 1);
$levelRange    = $xpForNext - $xpForCurrent;
$levelProgress = $levelRange > 0 ? min(100, max(0, round(($totalXp - $xpForCurrent) / $levelRange * 100))) : 100;

/* ── Streak ── */
$currentStreak = StudyStreak::getCurrent($userId);
$longestStreak = StudyStreak::getLongest($userId);

/* ── Recent unlocks ── */
$recentUnlocks = array_slice(
    array_filter($unlockedList, fn($a) => !empty($a['unlocked_at'])),
    0, 5
);

/* ── Tier metadata ── */
$tierMeta = [
    'bronze'  => ['label' => 'Bronze',  'color' => '#cd7f32', 'bg' => 'rgba(205,127,50,.1)',  'bd' => 'rgba(205,127,50,.3)'],
    'silver'  => ['label' => 'Silver',  'color' => '#a8a8b8', 'bg' => 'rgba(168,168,184,.1)', 'bd' => 'rgba(168,168,184,.3)'],
    'gold'    => ['label' => 'Gold',    'color' => '#f5a623', 'bg' => 'rgba(245,166,35,.1)',  'bd' => 'rgba(245,166,35,.3)'],
    'diamond' => ['label' => 'Diamond', 'color' => '#1fe290', 'bg' => 'rgba(31,226,144,.1)',  'bd' => 'rgba(31,226,144,.3)'],
];

/* ── Category mapping ── */
$keyCategoryMap = [
    'onboarding_complete' => 'general',
    'first_quiz'          => 'general',
    'first_perfect'       => 'general',
    'streak_3'            => 'streak',
    'streak_7'            => 'streak',
    'streak_14'           => 'streak',
    'streak_30'           => 'streak',
    'streak_60'           => 'streak',
    'questions_50'        => 'practice',
    'questions_200'       => 'practice',
    'questions_500'       => 'practice',
    'questions_1000'      => 'practice',
    'math_mastery'        => 'mastery',
    'rw_mastery'          => 'mastery',
    'practice_test_1'     => 'tests',
    'practice_test_5'     => 'tests',
    'score_improve_50'    => 'score',
    'score_improve_100'   => 'score',
    'ai_tutor_10'         => 'special',
    'league_silver'       => 'league',
    'league_gold'         => 'league',
    'league_diamond'      => 'league',
];

$categories = [
    'general'  => 'Getting Started',
    'streak'   => 'Study Streaks',
    'practice' => 'Practice Questions',
    'tests'    => 'Practice Tests',
    'score'    => 'Score Progress',
    'mastery'  => 'Subject Mastery',
    'league'   => 'League Ranks',
    'special'  => 'Special Awards',
];

$byCategory = [];
foreach ($allAchievements as $a) {
    $cat = $keyCategoryMap[$a['key']] ?? 'general';
    $byCategory[$cat][] = $a;
}

/* ── Active filter ── */
$activeFilter = in_array($_GET['category'] ?? '', array_merge(['all','unlocked','locked'], array_keys($categories)))
    ? ($_GET['category'] ?? 'all')
    : 'all';
$activeTier = in_array($_GET['tier'] ?? '', array_keys($tierMeta)) ? $_GET['tier'] : '';

/* ── Rarest unlocked badge ── */
$tierOrder = ['diamond' => 4, 'gold' => 3, 'silver' => 2, 'bronze' => 1];
$rarest = null;
foreach ($unlockedList as $a) {
    if (!$rarest || ($tierOrder[$a['tier']] ?? 0) > ($tierOrder[$rarest['tier']] ?? 0)) {
        $rarest = $a;
    }
}

/* ── Page vars ── */
$activePage  = 'achievements';
$topbarTitle = 'Achievements';
$topbarSub   = $unlockedCount . ' of ' . $totalCount . ' badges unlocked';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Achievements — Avidmock SAT</title>
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
/* ─────────────────────────────────────────────
   TOKENS
───────────────────────────────────────────── */
:root {
    --dk: #143230;   --dk2: #1a3f3c;
    --ac: #1fe290;   --ac2: #17c87a;
    --tx: #1a1a2e;   --tx2: #4a4a5a;  --tx3: #8a8a9a;
    --bg: #f7faf9;   --bg2: #ffffff;  --bd: #e2ebe9;  --bd2: #d1dcd9;
    --warn: #f59e0b; --err: #ef4444;
    --ff: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    --sidebar-w: 260px;
    --topbar-h: 64px;
    --r: 14px;
    --r-sm: 10px;
    --r-lg: 18px;
    --pad: 28px;
    --pad-sm: 16px;
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

/* ─────────────────────────────────────────────
   SCROLL REVEAL
───────────────────────────────────────────── */
.sr {
    opacity: 0;
    transform: translateY(18px);
    transition: opacity .5s cubic-bezier(.16,1,.3,1), transform .5s cubic-bezier(.16,1,.3,1);
}
.sr.v { opacity: 1; transform: none; }
.d1 { transition-delay: .06s; }
.d2 { transition-delay: .12s; }
.d3 { transition-delay: .18s; }
.d4 { transition-delay: .24s; }
.d5 { transition-delay: .30s; }
.d6 { transition-delay: .36s; }

/* ─────────────────────────────────────────────
   LAYOUT SHELL
───────────────────────────────────────────── */
.main-content {
    margin-left: var(--sidebar-w);
    margin-top: var(--topbar-h);
    padding: 32px var(--pad) 80px;
    min-height: calc(100vh - var(--topbar-h));
    max-width: calc(1280px + var(--sidebar-w));
}
.sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.45);
    z-index: 250;
    opacity: 0;
    transition: opacity .28s;
    pointer-events: none;
}
.sidebar-overlay.show { opacity: 1; pointer-events: all; }

/* ─────────────────────────────────────────────
   HERO
───────────────────────────────────────────── */
.hero {
    background: var(--dk);
    border-radius: 20px;
    padding: 2.25rem 2.5rem;
    position: relative;
    overflow: hidden;
    margin-bottom: 20px;
}
.hero-bg-grid {
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(31,226,144,.028) 1px, transparent 1px),
        linear-gradient(90deg, rgba(31,226,144,.028) 1px, transparent 1px);
    background-size: 40px 40px;
    pointer-events: none;
}
.hero-bg-glow {
    position: absolute;
    top: -100px; right: -60px;
    width: 420px; height: 420px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(31,226,144,.07) 0%, transparent 65%);
    pointer-events: none;
}
.hero-inner { position: relative; z-index: 1; }

.hero-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 50px;
    background: rgba(31,226,144,.1);
    border: 1px solid rgba(31,226,144,.18);
    font-size: .5625rem;
    font-weight: 800;
    color: var(--ac);
    text-transform: uppercase;
    letter-spacing: .6px;
    margin-bottom: 14px;
}
.hero-eyebrow-dot {
    width: 5px; height: 5px;
    border-radius: 50%;
    background: var(--ac);
    animation: eyeDot 1.4s ease-in-out infinite;
}
@keyframes eyeDot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50%       { opacity: .3; transform: scale(.5); }
}

.hero-title {
    font-size: clamp(1.25rem, 3vw, 1.75rem);
    font-weight: 800;
    color: #fff;
    letter-spacing: -.03em;
    line-height: 1.15;
    margin-bottom: 5px;
}
.hero-title em { font-style: normal; color: var(--ac); }
.hero-sub {
    font-size: .9375rem;
    color: rgba(255,255,255,.38);
    font-weight: 500;
    margin-bottom: 20px;
}

/* Hero stats pill row — scrollable on tiny screens */
.hero-stats {
    display: flex;
    gap: 0;
    background: rgba(255,255,255,.05);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 13px;
    overflow: hidden;
    width: fit-content;
    max-width: 100%;
    overflow-x: auto;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
}
.hero-stats::-webkit-scrollbar { display: none; }

.hero-stat {
    padding: 11px 20px;
    border-right: 1px solid rgba(255,255,255,.08);
    text-align: center;
    flex-shrink: 0;
}
.hero-stat:last-child { border-right: none; }
.hero-stat-val {
    font-size: 1.25rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: -.04em;
    line-height: 1;
}
.hero-stat-val.ac { color: var(--ac); }
.hero-stat-key {
    font-size: .5rem;
    font-weight: 700;
    color: rgba(255,255,255,.3);
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-top: 3px;
}

/* XP bar */
.xp-bar-wrap { max-width: 500px; margin-top: 18px; }
.xp-bar-top {
    display: flex;
    justify-content: space-between;
    font-size: .6875rem;
    font-weight: 700;
    color: rgba(255,255,255,.35);
    margin-bottom: 6px;
}
.xp-bar-top span { color: var(--ac); font-weight: 800; }
.xp-bar-track {
    height: 6px;
    background: rgba(255,255,255,.07);
    border-radius: 3px;
    overflow: hidden;
}
.xp-bar-fill {
    height: 100%;
    border-radius: 3px;
    background: linear-gradient(90deg, var(--ac2), var(--ac));
    transition: width 1.6s cubic-bezier(.16,1,.3,1);
    width: 0%;
}

/* ─────────────────────────────────────────────
   OVERVIEW CARDS
───────────────────────────────────────────── */
.overview-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}
.ov-card {
    background: var(--bg2);
    border: 1px solid var(--bd);
    border-radius: var(--r-lg);
    padding: 1rem 1.125rem;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    transition: transform .25s cubic-bezier(.16,1,.3,1), box-shadow .25s, border-color .2s;
}
.ov-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(20,50,48,.07);
    border-color: rgba(31,226,144,.28);
}
.ov-ico {
    width: 38px; height: 38px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.ov-ico svg {
    width: 17px; height: 17px;
    fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round;
    stroke: currentColor;
}
.ic-green { background: rgba(31,226,144,.08);  color: var(--ac); }
.ic-fire  { background: rgba(245,158,11,.08);  color: var(--warn); }
.ic-star  { background: rgba(168,85,247,.08);  color: #a855f7; }
.ic-dk    { background: rgba(20,50,48,.07);    color: var(--dk); }

.ov-label { font-size: .6875rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: .4px; margin-bottom: 3px; }
.ov-val   { font-size: 1.375rem; font-weight: 800; color: var(--tx); letter-spacing: -.04em; line-height: 1; }
.ov-val span { font-size: .9375rem; color: var(--ac); }
.ov-sub   { font-size: .6875rem; color: var(--tx3); margin-top: 4px; }

/* ─────────────────────────────────────────────
   STREAK CALLOUT
───────────────────────────────────────────── */
.streak-callout {
    background: var(--dk);
    border-radius: var(--r-lg);
    padding: 1.125rem 1.375rem;
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 20px;
    position: relative;
    overflow: hidden;
}
.streak-callout::before {
    content: '';
    position: absolute;
    right: -40px; top: -40px;
    width: 160px; height: 160px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(31,226,144,.07) 0%, transparent 70%);
    pointer-events: none;
}
.streak-ico {
    width: 44px; height: 44px;
    border-radius: 12px;
    background: rgba(245,158,11,.1);
    border: 1px solid rgba(245,158,11,.2);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.streak-ico svg {
    width: 22px; height: 22px;
    stroke: #f59e0b; fill: none;
    stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round;
}
.streak-body { flex: 1; position: relative; z-index: 1; min-width: 0; }
.streak-num  { font-size: 1.5rem; font-weight: 800; color: #fff; letter-spacing: -.04em; line-height: 1; }
.streak-num em { font-style: normal; color: var(--ac); }
.streak-sub  { font-size: .8125rem; color: rgba(255,255,255,.38); margin-top: 2px; }
.streak-best {
    text-align: right;
    flex-shrink: 0;
    position: relative;
    z-index: 1;
}
.streak-best-val { font-size: 1.125rem; font-weight: 800; color: rgba(255,255,255,.55); letter-spacing: -.02em; }
.streak-best-key { font-size: .5625rem; color: rgba(255,255,255,.22); text-transform: uppercase; letter-spacing: .4px; margin-top: 2px; }

/* ─────────────────────────────────────────────
   FILTER BAR
───────────────────────────────────────────── */
.filter-bar {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.filter-tabs {
    display: flex;
    gap: 3px;
    background: var(--bg2);
    border: 1px solid var(--bd);
    border-radius: 12px;
    padding: 4px;
    overflow-x: auto;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
    flex: 1;
    min-width: 0;
}
.filter-tabs::-webkit-scrollbar { display: none; }

.filter-tab {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 13px;
    border-radius: 9px;
    font-size: .8125rem;
    font-weight: 700;
    color: var(--tx3);
    text-decoration: none;
    transition: background .15s, color .15s;
    white-space: nowrap;
    flex-shrink: 0;
    min-height: 34px;
}
.filter-tab:hover  { color: var(--tx); background: var(--bg); }
.filter-tab.active { background: var(--dk); color: var(--ac); }

.filter-tab-count {
    font-size: .5rem;
    font-weight: 800;
    background: rgba(0,0,0,.06);
    padding: 1px 6px;
    border-radius: 50px;
    color: inherit;
}
.filter-tab.active .filter-tab-count { background: rgba(31,226,144,.2); color: var(--ac); }

.tier-chips {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
    align-items: center;
    padding-top: 4px;
}
.tier-chip {
    padding: 5px 12px;
    border-radius: 50px;
    font-size: .625rem;
    font-weight: 800;
    border: 1.5px solid var(--bd);
    background: var(--bg2);
    color: var(--tx3);
    text-decoration: none;
    transition: border-color .15s, background .15s;
    text-transform: uppercase;
    letter-spacing: .3px;
    white-space: nowrap;
    min-height: 30px;
    display: inline-flex;
    align-items: center;
}
.tier-chip:hover { border-color: currentColor; }

/* ─────────────────────────────────────────────
   ACHIEVEMENT SECTIONS
───────────────────────────────────────────── */
.achievements-section { margin-bottom: 28px; }
.section-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}
.section-title { font-size: .9375rem; font-weight: 800; color: var(--tx); letter-spacing: -.02em; }
.section-count { font-size: .75rem; font-weight: 700; color: var(--tx3); }
.section-count em { font-style: normal; color: var(--ac); font-weight: 800; }

.achievement-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
    gap: 10px;
}

/* ─────────────────────────────────────────────
   BADGE CARD
───────────────────────────────────────────── */
.badge-card {
    background: var(--bg2);
    border: 1.5px solid var(--bd);
    border-radius: var(--r-lg);
    padding: 1.125rem .9375rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 8px;
    position: relative;
    overflow: hidden;
    transition: transform .28s cubic-bezier(.16,1,.3,1), box-shadow .28s, border-color .2s;
}
.badge-card.unlocked { cursor: pointer; }
.badge-card.unlocked:hover {
    transform: translateY(-4px) scale(1.015);
    box-shadow: 0 12px 32px rgba(20,50,48,.1);
    border-color: transparent;
}
.badge-card.locked { opacity: .48; filter: grayscale(.35); }
.badge-card.locked:hover { opacity: .65; transform: translateY(-2px); }

.badge-card.unlocked.bronze  { border-color: rgba(205,127,50,.3); }
.badge-card.unlocked.silver  { border-color: rgba(168,168,184,.3); }
.badge-card.unlocked.gold    { border-color: rgba(245,166,35,.3); }
.badge-card.unlocked.diamond {
    border-color: rgba(31,226,144,.4);
    animation: diamondPulse 3s ease-in-out infinite;
}
@keyframes diamondPulse {
    0%, 100% { box-shadow: 0 0 0 0   rgba(31,226,144,.14); }
    50%       { box-shadow: 0 0 18px 4px rgba(31,226,144,.1); }
}

/* Tier tab */
.badge-tier-tab {
    position: absolute;
    top: 10px; right: 10px;
    padding: 2px 7px;
    border-radius: 50px;
    font-size: .4375rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .4px;
    border: 1px solid currentColor;
    opacity: .8;
}

/* Hover reveal date */
.badge-unlock-date {
    position: absolute;
    bottom: 0; left: 0; right: 0;
    background: rgba(20,50,48,.92);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    padding: 5px;
    text-align: center;
    font-size: .5rem;
    font-weight: 700;
    color: var(--ac);
    transform: translateY(100%);
    transition: transform .22s cubic-bezier(.16,1,.3,1);
    border-radius: 0 0 var(--r-lg) var(--r-lg);
}
.badge-card.unlocked:hover .badge-unlock-date { transform: none; }

/* Icon */
.badge-ico-wrap {
    width: 60px; height: 60px;
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    position: relative;
    flex-shrink: 0;
    transition: transform .3s cubic-bezier(.16,1,.3,1);
}
.badge-ico-wrap svg {
    width: 27px; height: 27px;
    fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round;
    stroke: currentColor;
}
.badge-card.unlocked:hover .badge-ico-wrap { transform: scale(1.1) rotate(-4deg); }

/* Overlay check / lock */
.badge-overlay {
    position: absolute;
    bottom: -4px; right: -4px;
    width: 20px; height: 20px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    border: 2px solid var(--bg2);
}
.badge-overlay svg {
    width: 9px; height: 9px;
    fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round;
}

.badge-label { font-size: .8125rem; font-weight: 800; color: var(--tx); letter-spacing: -.01em; line-height: 1.2; }
.badge-card.locked .badge-label { color: var(--tx3); }
.badge-desc  { font-size: .6875rem; color: var(--tx3); line-height: 1.5; }
.badge-xp {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 2px 8px;
    border-radius: 50px;
    font-size: .5rem;
    font-weight: 800;
    background: rgba(31,226,144,.08);
    color: #0d7a4a;
}
.badge-card.locked .badge-xp { background: var(--bg); color: var(--tx3); }

/* ─────────────────────────────────────────────
   MODAL
───────────────────────────────────────────── */
.modal-overlay {
    position: fixed; inset: 0;
    background: rgba(10,20,18,.75);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    z-index: 900;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    opacity: 0;
    pointer-events: none;
    transition: opacity .3s;
}
.modal-overlay.open { opacity: 1; pointer-events: all; }

.modal-box {
    background: var(--bg2);
    border-radius: 22px;
    width: 100%;
    max-width: 390px;
    max-height: calc(100dvh - 40px);
    overflow-y: auto;
    scrollbar-width: none;
    padding: 2rem;
    transform: translateY(18px) scale(.97);
    transition: transform .35s cubic-bezier(.16,1,.3,1);
    box-shadow: 0 32px 80px rgba(0,0,0,.28);
    position: relative;
    text-align: center;
}
.modal-box::-webkit-scrollbar { display: none; }
.modal-overlay.open .modal-box { transform: none; }

.modal-close {
    position: absolute;
    top: 14px; right: 14px;
    width: 30px; height: 30px;
    border-radius: 50%;
    border: 1.5px solid var(--bd);
    background: var(--bg);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    transition: border-color .15s, background .15s;
}
.modal-close:hover { border-color: var(--err); background: rgba(239,68,68,.06); }
.modal-close svg { width: 12px; height: 12px; stroke: var(--tx3); fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }

.modal-ico {
    width: 84px; height: 84px;
    border-radius: 22px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 14px;
}
.modal-ico svg { width: 40px; height: 40px; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; stroke: currentColor; }

.modal-title { font-size: 1.1875rem; font-weight: 800; color: var(--tx); letter-spacing: -.025em; margin-bottom: 6px; }
.modal-desc  { font-size: .9375rem; color: var(--tx3); line-height: 1.65; margin-bottom: 14px; }
.modal-xp {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 13px;
    border-radius: 50px;
    font-size: .8125rem;
    font-weight: 800;
    background: rgba(31,226,144,.08);
    color: #0d7a4a;
    margin-bottom: 14px;
}
.modal-date {
    font-size: .75rem;
    font-weight: 600;
    color: var(--ac);
    background: rgba(31,226,144,.07);
    border: 1px solid rgba(31,226,144,.15);
    border-radius: 8px;
    padding: 7px 13px;
    margin-bottom: 14px;
    display: block;
}
.modal-btn {
    width: 100%;
    padding: 11px;
    border-radius: 11px;
    font-family: var(--ff);
    font-size: .9375rem;
    font-weight: 800;
    border: none;
    cursor: pointer;
    background: var(--dk);
    color: var(--ac);
    transition: background .2s, transform .2s, box-shadow .2s;
    min-height: 44px;
}
.modal-btn:hover { background: var(--dk2); transform: translateY(-1px); box-shadow: 0 6px 18px rgba(20,50,48,.18); }

/* ─────────────────────────────────────────────
   EMPTY STATE
───────────────────────────────────────────── */
.empty-state {
    background: var(--bg2);
    border: 1px solid var(--bd);
    border-radius: var(--r-lg);
    padding: 3rem;
    text-align: center;
}
.empty-state svg { width: 44px; height: 44px; stroke: var(--bd); fill: none; stroke-width: 1.5; margin: 0 auto 1rem; display: block; }
.empty-state-title { font-size: .9375rem; font-weight: 800; color: var(--tx); margin-bottom: .375rem; }
.empty-state-sub   { color: var(--tx3); font-size: .875rem; }

/* ─────────────────────────────────────────────
   RESPONSIVE
   ─────────────────────────────────────────────
   ≥ 1100   4-col overview, full badge grid
   ≤ 1100   2-col overview
   ≤ 900    sidebar off, full width
   ≤ 768    filter bar stacks, badge grid adapts
   ≤ 600    smaller hero, 2-col badges, tighter cards
   ≤ 480    tightest — icons hidden, tiny type
───────────────────────────────────────────── */

@media (max-width: 1100px) {
    .overview-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 900px) {
    .main-content { margin-left: 0; padding: 20px var(--pad-sm) 72px; }
    .hero { padding: 1.75rem 1.5rem; }
}

@media (max-width: 768px) {
    .filter-bar  { flex-direction: column; align-items: stretch; gap: 8px; }
    .filter-tabs { flex: none; }
    .tier-chips  { padding-top: 0; }
    .achievement-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 8px; }
    .streak-callout { gap: 12px; }
}

@media (max-width: 600px) {
    .hero { padding: 1.375rem 1.125rem; border-radius: 16px; }
    .hero-title { font-size: 1.25rem; }
    .hero-sub   { font-size: .875rem; margin-bottom: 16px; }
    .hero-stat  { padding: 9px 14px; }
    .hero-stat-val { font-size: 1.0625rem; }
    .xp-bar-wrap { margin-top: 14px; }

    .overview-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
    .ov-card { padding: .875rem .9375rem; }
    .ov-val  { font-size: 1.125rem; }

    .streak-callout { padding: .875rem 1rem; }
    .streak-num { font-size: 1.25rem; }
    .streak-ico { width: 38px; height: 38px; border-radius: 10px; }
    .streak-ico svg { width: 18px; height: 18px; }

    .achievement-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
    .badge-card { padding: .9375rem .75rem; gap: 6px; border-radius: 14px; }
    .badge-ico-wrap { width: 52px; height: 52px; border-radius: 13px; }
    .badge-ico-wrap svg { width: 24px; height: 24px; }
    .badge-label { font-size: .75rem; }
    .badge-desc  { font-size: .625rem; }

    .filter-tab { padding: 5px 10px; font-size: .75rem; }
}

@media (max-width: 480px) {
    .overview-grid { gap: 6px; }
    .ov-ico { display: none; }

    .tier-chip { padding: 4px 9px; font-size: .5625rem; }
    .badge-tier-tab { display: none; }

    .modal-box   { padding: 1.5rem; border-radius: 18px; }
    .modal-ico   { width: 70px; height: 70px; border-radius: 18px; }
    .modal-ico svg { width: 32px; height: 32px; }
    .modal-title { font-size: 1.0625rem; }
    .modal-desc  { font-size: .875rem; }

    .empty-state { padding: 2rem 1.25rem; }
}

/* ── Safe area insets (notched phones) ── */
@supports (padding: max(0px)) {
    .main-content {
        padding-left:   max(var(--pad-sm), env(safe-area-inset-left));
        padding-right:  max(var(--pad-sm), env(safe-area-inset-right));
        padding-bottom: max(80px, calc(80px + env(safe-area-inset-bottom)));
    }
    @media (min-width: 901px) {
        .main-content {
            padding-left:  max(var(--pad), env(safe-area-inset-left));
            padding-right: max(var(--pad), env(safe-area-inset-right));
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

    <!-- ── Hero ── -->
    <div class="hero sr">
        <div class="hero-bg-grid"></div>
        <div class="hero-bg-glow"></div>
        <div class="hero-inner">
            <div class="hero-eyebrow"><span class="hero-eyebrow-dot"></span>Achievement Center</div>
            <div class="hero-title"><?= htmlspecialchars($firstName) ?>'s <em>Achievements</em></div>
            <div class="hero-sub"><?= $unlockedCount ?> of <?= $totalCount ?> badges unlocked · <?= number_format($totalXp) ?> XP earned</div>
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="hero-stat-val ac"><?= $unlockedCount ?></div>
                    <div class="hero-stat-key">Unlocked</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-val"><?= $totalCount - $unlockedCount ?></div>
                    <div class="hero-stat-key">Remaining</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-val ac"><?= $completionPct ?>%</div>
                    <div class="hero-stat-key">Complete</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-val"><?= $currentLevel ?></div>
                    <div class="hero-stat-key">Level</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-val ac"><?= $currentStreak ?>d</div>
                    <div class="hero-stat-key">Streak</div>
                </div>
            </div>
            <div class="xp-bar-wrap">
                <div class="xp-bar-top">
                    <span>Level <?= $currentLevel ?> → <?= $currentLevel + 1 ?></span>
                    <span><?= $levelProgress ?>%</span>
                </div>
                <div class="xp-bar-track">
                    <div class="xp-bar-fill" data-width="<?= $levelProgress ?>"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Overview cards ── -->
    <div class="overview-grid sr d1">
        <div class="ov-card">
            <div class="ov-ico ic-green">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>
            </div>
            <div>
                <div class="ov-label">Badges</div>
                <div class="ov-val"><?= $unlockedCount ?><span>/<?= $totalCount ?></span></div>
                <div class="ov-sub"><?= $completionPct ?>% complete</div>
            </div>
        </div>
        <div class="ov-card">
            <div class="ov-ico ic-star">
                <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </div>
            <div>
                <div class="ov-label">Total XP</div>
                <div class="ov-val"><?= number_format($totalXp) ?></div>
                <div class="ov-sub">Level <?= $currentLevel ?> · <?= $levelProgress ?>% to next</div>
            </div>
        </div>
        <div class="ov-card">
            <div class="ov-ico ic-fire">
                <svg viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9z"/></svg>
            </div>
            <div>
                <div class="ov-label">Streak</div>
                <div class="ov-val"><?= $currentStreak ?><span> days</span></div>
                <div class="ov-sub">Best: <?= $longestStreak ?> days</div>
            </div>
        </div>
        <div class="ov-card">
            <div class="ov-ico ic-dk">
                <svg viewBox="0 0 24 24"><path d="M12 15l-4 5 4-2 4 2-4-5zM12 3a6 6 0 016 6c0 3-1.5 5-3 6.5H9C7.5 14 6 12 6 9a6 6 0 016-6z"/></svg>
            </div>
            <div>
                <div class="ov-label">Rarest Badge</div>
                <?php if ($rarest): ?>
                <div class="ov-val" style="font-size:.9375rem;letter-spacing:-.01em"><?= htmlspecialchars($rarest['name']) ?></div>
                <div class="ov-sub" style="text-transform:capitalize"><?= $rarest['tier'] ?></div>
                <?php else: ?>
                <div class="ov-val" style="font-size:.9375rem;color:var(--tx3)">None yet</div>
                <div class="ov-sub">Complete quizzes to start</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Streak callout ── -->
    <?php if ($currentStreak > 0): ?>
    <div class="streak-callout sr d2">
        <div class="streak-ico">
            <svg viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9z"/></svg>
        </div>
        <div class="streak-body">
            <div class="streak-num"><em><?= $currentStreak ?></em>-day streak</div>
            <div class="streak-sub">
                <?php
                if ($currentStreak >= 30)     echo 'Absolutely on fire — you are unstoppable.';
                elseif ($currentStreak >= 14) echo 'Two weeks strong — incredible commitment!';
                elseif ($currentStreak >= 7)  echo 'One full week! You\'re building a great habit.';
                elseif ($currentStreak >= 3)  echo 'Great momentum! Keep studying daily.';
                else                          echo 'Good start! Study tomorrow to keep your streak.';
                ?>
            </div>
        </div>
        <div class="streak-best">
            <div class="streak-best-val"><?= $longestStreak ?>d</div>
            <div class="streak-best-key">Best ever</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Filter bar ── -->
    <div class="filter-bar sr d2">
        <div class="filter-tabs">
            <a href="?category=all<?= $activeTier ? '&tier='.$activeTier : '' ?>"
               class="filter-tab <?= $activeFilter === 'all' ? 'active' : '' ?>">
                All <span class="filter-tab-count"><?= $totalCount ?></span>
            </a>
            <a href="?category=unlocked<?= $activeTier ? '&tier='.$activeTier : '' ?>"
               class="filter-tab <?= $activeFilter === 'unlocked' ? 'active' : '' ?>">
                Unlocked <span class="filter-tab-count"><?= $unlockedCount ?></span>
            </a>
            <a href="?category=locked<?= $activeTier ? '&tier='.$activeTier : '' ?>"
               class="filter-tab <?= $activeFilter === 'locked' ? 'active' : '' ?>">
                Locked <span class="filter-tab-count"><?= $totalCount - $unlockedCount ?></span>
            </a>
            <?php foreach ($categories as $slug => $label):
                $cnt = count($byCategory[$slug] ?? []);
                if (!$cnt) continue;
            ?>
            <a href="?category=<?= $slug ?><?= $activeTier ? '&tier='.$activeTier : '' ?>"
               class="filter-tab <?= $activeFilter === $slug ? 'active' : '' ?>">
                <?= htmlspecialchars($label) ?> <span class="filter-tab-count"><?= $cnt ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="tier-chips">
            <?php foreach ($tierMeta as $ts => $tm): ?>
            <a href="?category=<?= $activeFilter ?><?= $activeTier === $ts ? '' : '&tier='.$ts ?>"
               class="tier-chip"
               style="color:<?= $tm['color'] ?>;<?= $activeTier === $ts ? 'background:'.$tm['bg'].';border-color:'.$tm['bd'].';' : '' ?>">
                <?= $tm['label'] ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── Badge sections ── -->
    <?php
    $displayList = [];
    if (in_array($activeFilter, ['all','unlocked','locked'])) {
        foreach ($categories as $slug => $label) {
            $items = $byCategory[$slug] ?? [];
            if ($activeFilter === 'unlocked') $items = array_filter($items, fn($a) => !empty($a['unlocked']));
            if ($activeFilter === 'locked')   $items = array_filter($items, fn($a) =>  empty($a['unlocked']));
            if ($activeTier) $items = array_filter($items, fn($a) => ($a['tier'] ?? 'bronze') === $activeTier);
            if (!empty($items)) $displayList[] = ['slug' => $slug, 'label' => $label, 'items' => array_values($items)];
        }
    } else {
        $items = $byCategory[$activeFilter] ?? [];
        if ($activeTier) $items = array_filter($items, fn($a) => ($a['tier'] ?? 'bronze') === $activeTier);
        if (!empty($items)) $displayList[] = ['slug' => $activeFilter, 'label' => $categories[$activeFilter] ?? '', 'items' => array_values($items)];
    }
    ?>

    <?php if (empty($displayList)): ?>
    <div class="empty-state sr d3">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>
        <div class="empty-state-title">No badges here</div>
        <div class="empty-state-sub">Complete quizzes and lessons to earn badges.</div>
    </div>

    <?php else: ?>
    <?php foreach ($displayList as $gi => $group):
        $catUnlocked = count(array_filter($group['items'], fn($a) => !empty($a['unlocked'])));
        $delay = 'd' . min($gi + 3, 6);
    ?>
    <div class="achievements-section sr <?= $delay ?>">
        <div class="section-header">
            <div class="section-title"><?= htmlspecialchars($group['label']) ?></div>
            <div class="section-count"><em><?= $catUnlocked ?></em> / <?= count($group['items']) ?> unlocked</div>
        </div>
        <div class="achievement-grid">
            <?php foreach ($group['items'] as $a):
                $tier     = $a['tier'] ?? 'bronze';
                $tm       = $tierMeta[$tier] ?? $tierMeta['bronze'];
                $unlocked = !empty($a['unlocked']);
                $json     = htmlspecialchars(json_encode($a, JSON_HEX_APOS | JSON_HEX_TAG), ENT_QUOTES);
                $cls      = 'badge-card ' . ($unlocked ? 'unlocked ' . $tier : 'locked');
            ?>
            <div class="<?= $cls ?>"
                 <?= $unlocked ? 'onclick="openModal('.$json.')" tabindex="0" role="button" aria-label="'.htmlspecialchars($a['name'] ?? '').'"' : '' ?>>

                <div class="badge-tier-tab" style="color:<?= $tm['color'] ?>"><?= $tm['label'] ?></div>

                <div class="badge-ico-wrap"
                     style="background:<?= $tm['bg'] ?>;border:1.5px solid <?= $tm['bd'] ?>;color:<?= $tm['color'] ?>">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>
                    <?php if (!$unlocked): ?>
                    <div class="badge-overlay" style="background:var(--bg);border-color:var(--bd)">
                        <svg viewBox="0 0 24 24" style="stroke:var(--tx3)"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                    </div>
                    <?php else: ?>
                    <div class="badge-overlay" style="background:<?= $tm['color'] ?>">
                        <svg viewBox="0 0 24 24" style="stroke:#fff"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="badge-label"><?= htmlspecialchars($a['name'] ?? '') ?></div>
                <div class="badge-desc"><?= htmlspecialchars($a['description'] ?? '') ?></div>
                <div class="badge-xp">+<?= intval($a['xp_reward'] ?? 0) ?> XP</div>

                <?php if ($unlocked && !empty($a['unlocked_at'])): ?>
                <div class="badge-unlock-date">Unlocked <?= date('M j, Y', strtotime($a['unlocked_at'])) ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

</main>

<!-- ── Achievement modal ── -->
<div class="modal-overlay" id="modal"
     onclick="if(event.target===this)closeModal()"
     role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal()" aria-label="Close">
            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
        <div id="modalContent"></div>
    </div>
</div>

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
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (n) {
            if (n.isIntersecting) { n.target.classList.add('v'); io.unobserve(n.target); }
        });
    }, { threshold: .04, rootMargin: '0px 0px -20px 0px' });
    document.querySelectorAll('.sr').forEach(function (el) { io.observe(el); });

    /* ── Animated XP bar ── */
    var bObs = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (!e.isIntersecting) return;
            var b = e.target;
            setTimeout(function () { b.style.width = (b.dataset.width || 0) + '%'; }, 200);
            bObs.unobserve(b);
        });
    }, { threshold: .05 });
    document.querySelectorAll('[data-width]').forEach(function (b) { bObs.observe(b); });

    /* ── Staggered badge card entrance ── */
    var cardObs = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (!e.isIntersecting) return;
            var cards = e.target.querySelectorAll('.badge-card');
            cards.forEach(function (c, i) {
                c.style.opacity   = '0';
                c.style.transform = 'translateY(14px)';
                setTimeout(function () {
                    c.style.transition = 'opacity .42s cubic-bezier(.16,1,.3,1), transform .42s cubic-bezier(.16,1,.3,1)';
                    c.style.opacity    = c.classList.contains('locked') ? '.48' : '1';
                    c.style.transform  = 'none';
                }, i * 35);
            });
            cardObs.unobserve(e.target);
        });
    }, { threshold: .04 });
    document.querySelectorAll('.achievement-grid').forEach(function (g) { cardObs.observe(g); });

    /* ── Keyboard for badge cards ── */
    document.querySelectorAll('.badge-card.unlocked').forEach(function (card) {
        card.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); card.click(); }
        });
    });

    /* ── Tier colours ── */
    var tierColors = {
        bronze:  { color: '#cd7f32', bg: 'rgba(205,127,50,.1)' },
        silver:  { color: '#a8a8b8', bg: 'rgba(168,168,184,.1)' },
        gold:    { color: '#f5a623', bg: 'rgba(245,166,35,.1)' },
        diamond: { color: '#1fe290', bg: 'rgba(31,226,144,.1)' },
    };

    /* ── Open modal ── */
    window.openModal = function (data) {
        if (typeof data === 'string') {
            try { data = JSON.parse(data); } catch (e) { return; }
        }
        var tier = data.tier || 'bronze';
        var tc   = tierColors[tier] || tierColors.bronze;

        var html = '<div class="modal-ico" style="background:' + tc.bg + ';border:2px solid ' + tc.color + '44;color:' + tc.color + '">'
                 + '<svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg></div>';
        html += '<div style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:50px;font-size:.5625rem;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:' + tc.color + ';background:' + tc.bg + ';margin-bottom:10px;border:1px solid ' + tc.color + '44">'
              + esc(tier.charAt(0).toUpperCase() + tier.slice(1)) + '</div>';
        html += '<div class="modal-title" id="modalTitle">' + esc(data.name || '') + '</div>';
        html += '<div class="modal-desc">' + esc(data.description || '') + '</div>';
        html += '<div class="modal-xp">+' + parseInt(data.xp_reward || 0) + ' XP</div>';
        if (data.unlocked && data.unlocked_at) {
            html += '<div class="modal-date">Unlocked ' + fmtDate(data.unlocked_at) + '</div>';
        }
        html += '<button class="modal-btn" onclick="closeModal()">Close</button>';

        document.getElementById('modalContent').innerHTML = html;
        document.getElementById('modal').classList.add('open');
        document.body.style.overflow = 'hidden';

        setTimeout(function () {
            var btn = document.querySelector('#modal .modal-btn');
            if (btn) btn.focus();
        }, 350);
    };

    window.closeModal = function () {
        document.getElementById('modal').classList.remove('open');
        document.body.style.overflow = '';
    };

    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });

    /* Focus trap */
    var modalEl = document.getElementById('modal');
    modalEl.addEventListener('keydown', function (e) {
        if (e.key !== 'Tab' || !modalEl.classList.contains('open')) return;
        var focusable = Array.from(modalEl.querySelectorAll('button, [tabindex]:not([tabindex="-1"])'));
        var first = focusable[0];
        var last  = focusable[focusable.length - 1];
        if (e.shiftKey  && document.activeElement === first) { e.preventDefault(); last.focus(); }
        if (!e.shiftKey && document.activeElement === last)  { e.preventDefault(); first.focus(); }
    });

    function esc(s) {
        return String(s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function fmtDate(d) {
        try { return new Date(d).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }); }
        catch (e) { return d; }
    }

}());
</script>
</body>
</html>