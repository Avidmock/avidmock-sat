<?php
/**
 * AvidMock SAT — Student Dashboard
 * my.sat.avidmock.com/index.php
 *
 * The main AI-powered student dashboard. Premium edtech experience.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/StudyStreak.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Achievement.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Leaderboard.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/ScorePredictor.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Schedule.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/CategoryPerformance.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Session.php';

Auth::requireStudent();

$userId    = (int) $_SESSION['user_id'];
$activePage   = 'dashboard';
$topbarTitle  = 'Dashboard';

/* ── helpers ──────────────────────────────────────────────────── */
function xpThreshold(int $l): int
{
    return $l <= 1 ? 0 : (int)(500 * ($l - 1) * $l / 2);
}

function levelFromXp(int $xp): int
{
    $l = 1;
    while (xpThreshold($l + 1) <= $xp) {
        $l++;
    }
    return $l;
}

/* ── data fetching (resilient) ────────────────────────────────── */
try { $user = User::findById($userId); } catch (\Throwable $e) { $user = []; }
$firstName = trim($user['first_name'] ?? 'Student');

try { $streakData = StudyStreak::get($userId); } catch (\Throwable $e) { $streakData = []; }
$currentStreak = intval($streakData['current_streak'] ?? 0);
$longestStreak = intval($streakData['longest_streak'] ?? 0);

try { $prediction = ScorePredictor::getLatest($userId); } catch (\Throwable $e) { $prediction = null; }
try { $scoreTrend = ScorePredictor::getHistory($userId); } catch (\Throwable $e) { $scoreTrend = []; }

try { $todayPlan = Schedule::getToday($userId); } catch (\Throwable $e) { $todayPlan = []; }

try { $mathPerf = CategoryPerformance::get($userId, 'math') ?? []; } catch (\Throwable $e) { $mathPerf = []; }
try { $rwPerf = CategoryPerformance::get($userId, 'reading_writing') ?? []; } catch (\Throwable $e) { $rwPerf = []; }
try { $weakMath = CategoryPerformance::getWeakTopics($userId, 'math', 3) ?? []; } catch (\Throwable $e) { $weakMath = []; }
try { $weakRW = CategoryPerformance::getWeakTopics($userId, 'reading_writing', 3) ?? []; } catch (\Throwable $e) { $weakRW = []; }

try { $rankData = Leaderboard::getUserRank($userId); } catch (\Throwable $e) { $rankData = []; }
$totalXP  = intval($rankData['xp'] ?? 0);
$userRank = intval($rankData['rank'] ?? 0);

try { $recentBadges = Achievement::getUnlocked($userId, 3); } catch (\Throwable $e) { $recentBadges = []; }
try { $upcomingSessions = Session::getUpcoming($userId, 2); } catch (\Throwable $e) { $upcomingSessions = []; }
try { $leaderboardTop = Leaderboard::getTop(5); } catch (\Throwable $e) { $leaderboardTop = []; }

/* ── derived values ───────────────────────────────────────────── */
$level        = levelFromXp($totalXP);
$currentThresh = xpThreshold($level);
$nextThresh    = xpThreshold($level + 1);
$xpInLevel     = $totalXP - $currentThresh;
$xpNeeded      = max($nextThresh - $currentThresh, 1);
$xpPercent     = min(100, round($xpInLevel / $xpNeeded * 100));

$predictedTotal = intval($prediction['total'] ?? 0);
$predictedMath  = intval($prediction['math'] ?? 0);
$predictedRW    = intval($prediction['reading_writing'] ?? 0);

$mathAccuracy = floatval($mathPerf['accuracy'] ?? 0);
$rwAccuracy   = floatval($rwPerf['accuracy'] ?? 0);
$mathMastery  = $mathPerf['mastery_label'] ?? 'Beginner';
$rwMastery    = $rwPerf['mastery_label'] ?? 'Beginner';

$questionsPracticed   = intval($user['questions_practiced'] ?? 0);
$practiceTestsTaken   = intval($user['practice_tests_taken'] ?? 0);
$aiTutorConversations = intval($user['ai_tutor_conversations'] ?? 0);
$studyHoursWeek       = round(floatval($user['study_hours_this_week'] ?? 0), 1);

$satDate   = $user['sat_date'] ?? null;
$daysUntil = $satDate ? max(0, (int)((strtotime($satDate) - time()) / 86400)) : null;

// Trend detection
$improving = false;
if (is_array($scoreTrend) && count($scoreTrend) >= 2) {
    $last = end($scoreTrend);
    $prev = prev($scoreTrend);
    $improving = intval($last['total'] ?? 0) > intval($prev['total'] ?? 0);
}

// Greeting logic
$hour = (int)date('H');
if ($hour < 12) { $timeGreeting = 'Good morning'; }
elseif ($hour < 17) { $timeGreeting = 'Good afternoon'; }
else { $timeGreeting = 'Good evening'; }

// Motivational line
$motiveLine = 'Ready to level up today?';
if ($currentStreak >= 7) {
    $motiveLine = $currentStreak . '-day streak — you\'re unstoppable!';
} elseif ($currentStreak >= 3) {
    $motiveLine = $currentStreak . '-day streak — you\'re on fire!';
} elseif ($daysUntil !== null && $daysUntil <= 120) {
    $motiveLine = $daysUntil . ' days until SAT — let\'s crush it!';
} elseif ($improving) {
    $motiveLine = 'Your score is trending up. Keep going!';
} elseif ($totalXP > 0) {
    $motiveLine = 'Level ' . $level . ' scholar — ' . number_format($totalXP) . ' XP earned so far.';
}

// League badge
$leagues = ['Bronze','Silver','Gold','Platinum','Diamond','Master'];
$leagueIndex = min(floor(($level - 1) / 5), count($leagues) - 1);
$leagueName  = $leagues[$leagueIndex];

// Task type icons (SVG data)
$taskIcons = [
    'lesson'        => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>',
    'quiz'          => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    'review'        => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>',
    'practice-test' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard — AvidMock SAT</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:ital,opsz,wght@0,9..40,300..800;1,9..40,300..800&display=swap" rel="stylesheet">
<style>
/* ── Reset & Tokens ─────────────────────────────────────────── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --dk:#143230;--ac:#1fe290;--ac2:#17c87a;--tx:#1a1a2e;--bg:#f7faf9;
  --card:#ffffff;--card-border:rgba(20,50,48,.06);
  --glass:rgba(255,255,255,.72);--glass-border:rgba(255,255,255,.35);
  --shadow-sm:0 1px 3px rgba(20,50,48,.06);
  --shadow-md:0 4px 16px rgba(20,50,48,.08);
  --shadow-lg:0 8px 32px rgba(20,50,48,.10);
  --shadow-glow:0 0 24px rgba(31,226,144,.18);
  --radius:16px;--radius-sm:10px;--radius-xs:6px;
  --font:'DM Sans',system-ui,sans-serif;
  --mono:'DM Mono','Fira Code',monospace;
  --transition:cubic-bezier(.4,0,.2,1);
  --sidebar-w:260px;--topbar-h:56px;
}
html{font-size:16px;scroll-behavior:smooth;-webkit-font-smoothing:antialiased}
body{
  font-family:var(--font);color:var(--tx);background:var(--bg);
  min-height:100vh;line-height:1.55;
}
a{color:var(--ac2);text-decoration:none;transition:color .2s var(--transition)}
a:hover{color:var(--dk)}

/* ── Layout Shell ───────────────────────────────────────────── */
.main-content{
  margin-left:var(--sidebar-w);margin-top:var(--topbar-h);padding:32px 32px 80px;min-height:calc(100vh - var(--topbar-h));
}
@media(max-width:768px){ .main-content{margin-left:0;padding:20px 16px 72px} }

/* ── Scroll Reveal ──────────────────────────────────────────── */
.reveal{opacity:0;transform:translateY(24px);transition:opacity .6s var(--transition),transform .6s var(--transition)}
.reveal.visible{opacity:1;transform:translateY(0)}

/* ── 1. Hero Greeting ───────────────────────────────────────── */
.hero{
  position:relative;overflow:hidden;
  background:linear-gradient(135deg,var(--dk) 0%,#1a4a46 50%,#1b5c52 100%);
  border-radius:var(--radius);padding:44px 40px 40px;margin-bottom:28px;
  color:#fff;
}
.hero::before{
  content:'';position:absolute;top:-60%;right:-20%;
  width:480px;height:480px;border-radius:50%;
  background:radial-gradient(circle,rgba(31,226,144,.18) 0%,transparent 70%);
  pointer-events:none;
}
.hero::after{
  content:'';position:absolute;bottom:-40%;left:-10%;
  width:320px;height:320px;border-radius:50%;
  background:radial-gradient(circle,rgba(31,226,144,.10) 0%,transparent 70%);
  pointer-events:none;
}
.hero-content{position:relative;z-index:1}
.hero-greeting{font-size:1.1rem;font-weight:500;color:rgba(255,255,255,.7);margin-bottom:4px}
.hero-name{font-size:clamp(1.6rem,4vw,2.2rem);font-weight:700;margin-bottom:6px;letter-spacing:-.02em}
.hero-name span{color:var(--ac)}
.hero-motive{
  display:inline-flex;align-items:center;gap:8px;
  font-size:.95rem;color:rgba(255,255,255,.75);margin-bottom:28px;
  background:rgba(255,255,255,.08);border-radius:20px;padding:6px 16px;
  backdrop-filter:blur(8px);
}
.hero-motive svg{width:16px;height:16px;flex-shrink:0}
.hero-actions{display:flex;flex-wrap:wrap;gap:12px}
.hero-btn{
  display:inline-flex;align-items:center;gap:8px;
  padding:12px 24px;border-radius:10px;font-size:.9rem;font-weight:600;
  border:none;cursor:pointer;transition:all .25s var(--transition);
  font-family:var(--font);letter-spacing:-.01em;
}
.hero-btn svg{width:18px;height:18px}
.hero-btn--primary{
  background:var(--ac);color:var(--dk);
  box-shadow:0 4px 20px rgba(31,226,144,.35);
}
.hero-btn--primary:hover{
  background:#2dffa0;transform:translateY(-2px);
  box-shadow:0 6px 28px rgba(31,226,144,.45);
}
.hero-btn--secondary{
  background:rgba(255,255,255,.1);color:#fff;
  border:1px solid rgba(255,255,255,.15);
  backdrop-filter:blur(8px);
}
.hero-btn--secondary:hover{
  background:rgba(255,255,255,.18);transform:translateY(-2px);
}

/* ── Glass Card Base ────────────────────────────────────────── */
.g-card{
  background:var(--glass);
  border:1px solid var(--glass-border);
  border-radius:var(--radius);
  backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);
  box-shadow:var(--shadow-md);
  padding:28px;
  transition:transform .3s var(--transition),box-shadow .3s var(--transition);
}
.g-card:hover{
  transform:translateY(-3px);box-shadow:var(--shadow-lg);
}
.card-header{
  display:flex;justify-content:space-between;align-items:center;
  margin-bottom:20px;
}
.card-title{font-size:1.05rem;font-weight:700;color:var(--dk);letter-spacing:-.01em}
.card-link{
  font-size:.82rem;font-weight:600;color:var(--ac2);
  display:inline-flex;align-items:center;gap:4px;
}
.card-link svg{width:14px;height:14px;transition:transform .2s}
.card-link:hover svg{transform:translateX(3px)}

/* ── Main Grid ──────────────────────────────────────────────── */
.dash-grid{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:24px;
}
.dash-grid .full{grid-column:1/-1}

/* ── 2. Score Prediction ────────────────────────────────────── */
.score-card{position:relative;overflow:hidden}
.score-card .score-glow{
  position:absolute;top:-30px;right:-30px;width:160px;height:160px;
  background:radial-gradient(circle,rgba(31,226,144,.12) 0%,transparent 70%);
  border-radius:50%;pointer-events:none;
}
.score-main{display:flex;align-items:center;gap:28px;margin-bottom:20px}
.score-badge-wrap{flex-shrink:0}
.score-details{flex:1}
.score-label{font-size:.78rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:rgba(20,50,48,.5);margin-bottom:4px}
.score-total{font-size:2.4rem;font-weight:800;color:var(--dk);letter-spacing:-.03em;line-height:1}
.score-total .trend{
  display:inline-flex;align-items:center;gap:3px;
  font-size:.85rem;font-weight:600;margin-left:10px;
  padding:3px 10px;border-radius:20px;vertical-align:middle;
}
.score-total .trend.up{background:rgba(31,226,144,.12);color:#0a9956}
.score-total .trend.down{background:rgba(239,68,68,.1);color:#dc2626}
.score-total .trend svg{width:14px;height:14px}
.score-breakdown{display:flex;gap:24px;margin-top:14px}
.score-sub{display:flex;flex-direction:column;gap:2px}
.score-sub-label{font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:rgba(20,50,48,.45)}
.score-sub-val{font-size:1.35rem;font-weight:700;color:var(--dk);font-family:var(--mono)}
.score-range{
  display:flex;align-items:center;gap:8px;
  padding:12px 16px;background:rgba(20,50,48,.03);border-radius:var(--radius-sm);
  font-size:.82rem;color:rgba(20,50,48,.6);
}
.score-range svg{width:16px;height:16px;color:var(--ac2);flex-shrink:0}
/* Score ring SVG */
.score-ring-wrap{position:relative;width:110px;height:110px}
.score-ring-wrap svg{width:110px;height:110px;transform:rotate(-90deg)}
.score-ring-bg{fill:none;stroke:rgba(20,50,48,.07);stroke-width:8}
.score-ring-fill{
  fill:none;stroke:url(#scoreGrad);stroke-width:8;
  stroke-linecap:round;
  stroke-dasharray:282.74;
  stroke-dashoffset:282.74;
  transition:stroke-dashoffset 1.5s var(--transition);
}
.score-ring-center{
  position:absolute;inset:0;display:flex;flex-direction:column;
  align-items:center;justify-content:center;
}
.score-ring-num{font-size:1.6rem;font-weight:800;color:var(--dk);line-height:1;font-family:var(--mono)}
.score-ring-of{font-size:.65rem;color:rgba(20,50,48,.45);font-weight:500}

/* ── 3. Today's Plan ────────────────────────────────────────── */
.plan-list{display:flex;flex-direction:column;gap:8px}
.plan-item{
  display:flex;align-items:center;gap:14px;
  padding:14px 16px;background:rgba(20,50,48,.02);
  border-radius:var(--radius-sm);
  border:1px solid rgba(20,50,48,.04);
  transition:all .2s var(--transition);
  cursor:pointer;
}
.plan-item:hover{background:rgba(31,226,144,.04);border-color:rgba(31,226,144,.15)}
.plan-item.completed{opacity:.55}
.plan-item.completed .plan-task-name{text-decoration:line-through}
.plan-check{
  width:22px;height:22px;border-radius:6px;flex-shrink:0;
  border:2px solid rgba(20,50,48,.2);display:flex;align-items:center;justify-content:center;
  transition:all .2s var(--transition);cursor:pointer;background:transparent;
}
.plan-check.done{
  background:var(--ac);border-color:var(--ac);
}
.plan-check.done svg{opacity:1}
.plan-check svg{width:14px;height:14px;color:#fff;opacity:0;transition:opacity .15s}
.plan-icon{
  width:34px;height:34px;border-radius:8px;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
  background:rgba(31,226,144,.1);color:var(--ac2);
}
.plan-icon svg{width:18px;height:18px}
.plan-info{flex:1;min-width:0}
.plan-task-name{font-size:.88rem;font-weight:600;color:var(--dk);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.plan-meta{font-size:.75rem;color:rgba(20,50,48,.45);display:flex;gap:12px;margin-top:2px}
.plan-empty{
  text-align:center;padding:36px 20px;
}
.plan-empty-icon{font-size:2.4rem;margin-bottom:10px;opacity:.4}
.plan-empty-text{font-size:.9rem;color:rgba(20,50,48,.5);margin-bottom:16px}
.plan-empty-btn{
  display:inline-flex;align-items:center;gap:6px;
  padding:10px 22px;border-radius:8px;font-size:.85rem;font-weight:600;
  background:var(--ac);color:var(--dk);border:none;cursor:pointer;
  font-family:var(--font);transition:all .2s var(--transition);
}
.plan-empty-btn:hover{background:var(--ac2);transform:translateY(-1px)}
.plan-empty-btn svg{width:16px;height:16px}

/* ── 4. Performance Radar ───────────────────────────────────── */
.perf-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px}
.perf-card-inner{text-align:center}
.perf-section-label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:rgba(20,50,48,.4);margin-bottom:16px}
.perf-accuracy-ring{position:relative;width:90px;height:90px;margin:0 auto 12px}
.perf-accuracy-ring svg{width:90px;height:90px;transform:rotate(-90deg)}
.perf-ring-bg{fill:none;stroke:rgba(20,50,48,.06);stroke-width:7}
.perf-ring-fill{
  fill:none;stroke:var(--ac);stroke-width:7;stroke-linecap:round;
  stroke-dasharray:226.2;stroke-dashoffset:226.2;
  transition:stroke-dashoffset 1.2s var(--transition);
}
.perf-ring-center{
  position:absolute;inset:0;display:flex;flex-direction:column;
  align-items:center;justify-content:center;
}
.perf-ring-pct{font-size:1.3rem;font-weight:800;color:var(--dk);font-family:var(--mono);line-height:1}
.perf-mastery{
  display:inline-block;font-size:.7rem;font-weight:700;text-transform:uppercase;
  letter-spacing:.05em;padding:3px 10px;border-radius:12px;
  background:rgba(31,226,144,.1);color:var(--ac2);margin-bottom:16px;
}
.perf-weak-title{font-size:.72rem;font-weight:600;color:rgba(20,50,48,.45);margin-bottom:8px;text-align:left}
.perf-weak-list{display:flex;flex-direction:column;gap:6px}
.perf-weak-item{
  display:flex;align-items:center;justify-content:space-between;gap:8px;
  font-size:.8rem;padding:8px 10px;background:rgba(20,50,48,.02);
  border-radius:var(--radius-xs);
}
.perf-weak-item .topic-name{font-weight:500;color:var(--dk);flex:1;text-align:left}
.perf-weak-item .topic-pct{font-family:var(--mono);font-size:.75rem;color:rgba(20,50,48,.5);flex-shrink:0}
.perf-weak-item .ai-link{
  font-size:.7rem;font-weight:600;color:var(--ac2);white-space:nowrap;flex-shrink:0;
}

/* ── 5. Streak & XP Row ─────────────────────────────────────── */
.streak-row{
  display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:16px;
}
.streak-item{
  display:flex;flex-direction:column;align-items:center;
  padding:20px 12px;background:rgba(20,50,48,.02);
  border-radius:var(--radius-sm);text-align:center;
  border:1px solid rgba(20,50,48,.04);
}
.streak-item-icon{
  width:40px;height:40px;border-radius:10px;
  display:flex;align-items:center;justify-content:center;
  margin-bottom:10px;font-size:1.3rem;
}
.streak-item-icon svg{width:22px;height:22px}
.streak-item-icon.fire{background:rgba(255,140,0,.1);color:#ff8c00}
.streak-item-icon.star{background:rgba(31,226,144,.1);color:var(--ac2)}
.streak-item-icon.bolt{background:rgba(99,102,241,.1);color:#6366f1}
.streak-item-icon.shield{background:rgba(236,72,153,.1);color:#ec4899}
.streak-item-icon.trophy{background:rgba(245,158,11,.1);color:#f59e0b}
.streak-item-icon.medal{background:rgba(20,50,48,.08);color:var(--dk)}
.streak-val{font-size:1.5rem;font-weight:800;color:var(--dk);font-family:var(--mono);line-height:1;margin-bottom:4px}
.streak-label{font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:rgba(20,50,48,.4)}
.xp-progress-wrap{grid-column:1/-1;padding:0 4px}
.xp-bar-outer{
  width:100%;height:10px;background:rgba(20,50,48,.06);
  border-radius:5px;overflow:hidden;margin-top:4px;
}
.xp-bar-inner{
  height:100%;border-radius:5px;
  background:linear-gradient(90deg,var(--ac2),var(--ac));
  width:0;transition:width 1.4s var(--transition);
}
.xp-bar-labels{display:flex;justify-content:space-between;margin-top:6px}
.xp-bar-labels span{font-size:.7rem;color:rgba(20,50,48,.45);font-family:var(--mono)}

/* ── 6. Quick Insights Grid ─────────────────────────────────── */
.insights-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.insight-card{
  text-align:center;padding:24px 14px;
  background:var(--glass);border:1px solid var(--glass-border);
  border-radius:var(--radius);backdrop-filter:blur(12px);
  box-shadow:var(--shadow-sm);
  transition:transform .25s var(--transition),box-shadow .25s var(--transition);
}
.insight-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-md)}
.insight-icon{
  width:44px;height:44px;border-radius:12px;margin:0 auto 12px;
  display:flex;align-items:center;justify-content:center;font-size:1.2rem;
}
.insight-icon svg{width:22px;height:22px}
.insight-icon.purple{background:rgba(99,102,241,.1);color:#6366f1}
.insight-icon.blue{background:rgba(59,130,246,.1);color:#3b82f6}
.insight-icon.amber{background:rgba(245,158,11,.1);color:#f59e0b}
.insight-icon.rose{background:rgba(236,72,153,.1);color:#ec4899}
.insight-val{font-size:1.8rem;font-weight:800;color:var(--dk);font-family:var(--mono);line-height:1;margin-bottom:4px}
.insight-label{font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:rgba(20,50,48,.4)}

/* ── 7. Leaderboard Preview ─────────────────────────────────── */
.lb-list{display:flex;flex-direction:column;gap:6px}
.lb-row{
  display:flex;align-items:center;gap:14px;
  padding:12px 16px;border-radius:var(--radius-sm);
  transition:background .2s;
}
.lb-row:hover{background:rgba(20,50,48,.02)}
.lb-row.me{background:rgba(31,226,144,.06);border:1px solid rgba(31,226,144,.15)}
.lb-rank{
  width:28px;height:28px;border-radius:8px;
  display:flex;align-items:center;justify-content:center;
  font-size:.78rem;font-weight:700;color:var(--dk);
  background:rgba(20,50,48,.05);flex-shrink:0;font-family:var(--mono);
}
.lb-row:nth-child(1) .lb-rank{background:rgba(245,158,11,.15);color:#b45309}
.lb-row:nth-child(2) .lb-rank{background:rgba(156,163,175,.15);color:#6b7280}
.lb-row:nth-child(3) .lb-rank{background:rgba(180,83,9,.12);color:#92400e}
.lb-name{flex:1;font-size:.88rem;font-weight:600;color:var(--dk)}
.lb-xp{font-size:.82rem;font-weight:600;color:rgba(20,50,48,.5);font-family:var(--mono)}

/* ── 8. Recent Achievements ─────────────────────────────────── */
.badges-grid{display:flex;gap:16px;flex-wrap:wrap}
.badge-card{
  flex:1;min-width:120px;text-align:center;
  padding:24px 16px;background:rgba(20,50,48,.02);
  border-radius:var(--radius-sm);border:1px solid rgba(20,50,48,.04);
  transition:transform .3s var(--transition);
}
.badge-card:hover{transform:translateY(-3px) scale(1.02)}
.badge-icon{
  width:56px;height:56px;border-radius:50%;margin:0 auto 12px;
  display:flex;align-items:center;justify-content:center;
  font-size:1.6rem;
  background:linear-gradient(135deg,rgba(31,226,144,.15),rgba(23,200,122,.08));
  animation:badgePulse 2s ease-in-out infinite;
}
@keyframes badgePulse{
  0%,100%{box-shadow:0 0 0 0 rgba(31,226,144,.2)}
  50%{box-shadow:0 0 0 10px rgba(31,226,144,0)}
}
.badge-name{font-size:.82rem;font-weight:700;color:var(--dk);margin-bottom:2px}
.badge-date{font-size:.7rem;color:rgba(20,50,48,.4)}
.no-badges{text-align:center;padding:24px;font-size:.88rem;color:rgba(20,50,48,.4)}

/* ── 9. Upcoming Sessions ───────────────────────────────────── */
.session-list{display:flex;flex-direction:column;gap:12px}
.session-card{
  display:flex;align-items:center;gap:16px;
  padding:16px 18px;background:rgba(20,50,48,.02);
  border-radius:var(--radius-sm);border:1px solid rgba(20,50,48,.04);
}
.session-date-box{
  width:52px;text-align:center;flex-shrink:0;
  padding:8px;background:linear-gradient(135deg,var(--dk),#1a4a46);
  border-radius:10px;color:#fff;
}
.session-date-box .sdb-month{font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;opacity:.7}
.session-date-box .sdb-day{font-size:1.3rem;font-weight:800;line-height:1.1}
.session-info{flex:1;min-width:0}
.session-info .si-title{font-size:.88rem;font-weight:700;color:var(--dk);margin-bottom:2px}
.session-info .si-time{font-size:.78rem;color:rgba(20,50,48,.5)}
.session-enroll{
  padding:8px 18px;border-radius:8px;font-size:.8rem;font-weight:600;
  background:var(--ac);color:var(--dk);border:none;cursor:pointer;
  font-family:var(--font);transition:all .2s var(--transition);flex-shrink:0;
}
.session-enroll:hover{background:var(--ac2);transform:translateY(-1px)}
.no-sessions{text-align:center;padding:24px;font-size:.88rem;color:rgba(20,50,48,.4)}

/* ── Section Spacing ────────────────────────────────────────── */
.section-title{
  font-size:.72rem;font-weight:700;text-transform:uppercase;
  letter-spacing:.08em;color:rgba(20,50,48,.35);margin-bottom:16px;
}

/* ── Responsive ─────────────────────────────────────────────── */
@media(max-width:900px){
  .dash-grid{grid-template-columns:1fr}
  .perf-grid{grid-template-columns:1fr}
  .insights-grid{grid-template-columns:repeat(2,1fr)}
  .hero{padding:32px 24px 28px}
}
@media(max-width:540px){
  .main-content{padding:16px 14px 60px}
  .hero{padding:24px 18px 22px}
  .hero-name{font-size:1.4rem}
  .hero-actions{gap:8px}
  .hero-btn{padding:10px 16px;font-size:.82rem}
  .g-card{padding:20px 16px}
  .score-main{flex-direction:column;text-align:center}
  .score-breakdown{justify-content:center}
  .streak-row{grid-template-columns:repeat(3,1fr)}
  .insights-grid{grid-template-columns:1fr 1fr}
  .badges-grid{flex-direction:column}
}
</style>
</head>
<body>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/sidebar.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/topbar.php';
?>

<?php $scoreBadgeSize = 'large'; $scoreBadgeStyle = 'card'; ?>

<main class="main-content">

<!-- ═══════════════════════════════════════════════════════════════
     1. AI GREETING HERO
     ═══════════════════════════════════════════════════════════ -->
<section class="hero reveal">
  <div class="hero-content">
    <p class="hero-greeting"><?= htmlspecialchars($timeGreeting) ?></p>
    <h1 class="hero-name"><?= htmlspecialchars($firstName) ?>, <span>let's study.</span></h1>
    <div class="hero-motive">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
      <?= htmlspecialchars($motiveLine) ?>
    </div>
    <div class="hero-actions">
      <a href="/study/plan" class="hero-btn hero-btn--primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
        Start Today's Plan
      </a>
      <a href="/ai-tutor" class="hero-btn hero-btn--secondary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 2a7 7 0 017 7c0 3-2 5.5-4 7l-1 3H10l-1-3c-2-1.5-4-4-4-7a7 7 0 017-7z"/><line x1="10" y1="22" x2="14" y2="22"/></svg>
        AI Tutor
      </a>
      <a href="/practice-test" class="hero-btn hero-btn--secondary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 12l2 2 4-4"/></svg>
        Practice Test
      </a>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════
     MAIN GRID
     ═══════════════════════════════════════════════════════════ -->
<div class="dash-grid">

<!-- ── 2. Score Prediction Card ──────────────────────────────── -->
<div class="g-card score-card reveal">
  <div class="score-glow"></div>
  <div class="card-header">
    <h2 class="card-title">Predicted Score</h2>
    <a href="/scores" class="card-link">
      History
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
  </div>

  <?php
    // Include score-badge component if available
    $scoreBadgePath = $_SERVER['DOCUMENT_ROOT'] . '/components/score-badge.php';
    $hasScoreBadge  = file_exists($scoreBadgePath);
  ?>

  <div class="score-main">
    <div class="score-badge-wrap">
      <?php if ($hasScoreBadge): ?>
        <?php include $scoreBadgePath; ?>
      <?php else: ?>
        <!-- Inline score ring -->
        <div class="score-ring-wrap">
          <svg viewBox="0 0 100 100">
            <defs>
              <linearGradient id="scoreGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="var(--ac2)"/>
                <stop offset="100%" stop-color="var(--ac)"/>
              </linearGradient>
            </defs>
            <circle class="score-ring-bg" cx="50" cy="50" r="45"/>
            <circle class="score-ring-fill" cx="50" cy="50" r="45"
                    data-pct="<?= $predictedTotal > 0 ? round($predictedTotal / 1600 * 100) : 0 ?>"/>
          </svg>
          <div class="score-ring-center">
            <span class="score-ring-num" data-count="<?= $predictedTotal ?>"><?= $predictedTotal ?></span>
            <span class="score-ring-of">/ 1600</span>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="score-details">
      <p class="score-label">Total Predicted</p>
      <p class="score-total">
        <span data-count="<?= $predictedTotal ?>"><?= $predictedTotal ?></span>
        <?php if ($improving): ?>
          <span class="trend up">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/></svg>
            Improving
          </span>
        <?php elseif (is_array($scoreTrend) && count($scoreTrend) >= 2 && !$improving): ?>
          <span class="trend down">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/></svg>
            Needs work
          </span>
        <?php endif; ?>
      </p>
      <div class="score-breakdown">
        <div class="score-sub">
          <span class="score-sub-label">Math</span>
          <span class="score-sub-val" data-count="<?= $predictedMath ?>"><?= $predictedMath ?></span>
        </div>
        <div class="score-sub">
          <span class="score-sub-label">Reading &amp; Writing</span>
          <span class="score-sub-val" data-count="<?= $predictedRW ?>"><?= $predictedRW ?></span>
        </div>
      </div>
    </div>
  </div>

  <div class="score-range">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
    This prediction updates as you practice. More data = higher accuracy.
  </div>
</div>

<!-- ── 3. Today's Study Plan ─────────────────────────────────── -->
<div class="g-card reveal">
  <div class="card-header">
    <h2 class="card-title">Today's Plan</h2>
    <a href="/schedule" class="card-link">
      View Full Schedule
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
  </div>

  <?php if (!empty($todayPlan)): ?>
    <div class="plan-list">
      <?php foreach ($todayPlan as $task):
        $type  = $task['type'] ?? 'lesson';
        $done  = !empty($task['completed']);
        $icon  = $taskIcons[$type] ?? $taskIcons['lesson'];
        $dur   = intval($task['duration_minutes'] ?? 0);
        $taskId = intval($task['id'] ?? 0);
      ?>
      <div class="plan-item<?= $done ? ' completed' : '' ?>" data-task-id="<?= $taskId ?>">
        <button class="plan-check<?= $done ? ' done' : '' ?>" aria-label="Mark complete" data-task="<?= $taskId ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
        </button>
        <div class="plan-icon"><?= $icon ?></div>
        <div class="plan-info">
          <div class="plan-task-name"><?= htmlspecialchars($task['title'] ?? 'Untitled Task') ?></div>
          <div class="plan-meta">
            <span><?= ucfirst(str_replace('-', ' ', $type)) ?></span>
            <?php if ($dur > 0): ?><span><?= $dur ?> min</span><?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="plan-empty">
      <div class="plan-empty-icon"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:.5"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg></div>
      <p class="plan-empty-text">No study plan for today yet.</p>
      <a href="/schedule/generate" class="plan-empty-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a7 7 0 017 7c0 3-2 5.5-4 7l-1 3H10l-1-3c-2-1.5-4-4-4-7a7 7 0 017-7z"/><line x1="10" y1="22" x2="14" y2="22"/></svg>
        Generate AI Study Plan
      </a>
    </div>
  <?php endif; ?>
</div>

<!-- ── 4. Performance Radar ──────────────────────────────────── -->
<div class="g-card full reveal">
  <div class="card-header">
    <h2 class="card-title">Performance Overview</h2>
    <a href="/performance" class="card-link">
      Details
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
  </div>

  <div class="perf-grid">
    <!-- Math -->
    <div class="g-card" style="padding:24px 20px;box-shadow:none;border:1px solid rgba(20,50,48,.06)">
      <div class="perf-card-inner">
        <p class="perf-section-label">Math</p>
        <div class="perf-accuracy-ring">
          <svg viewBox="0 0 80 80">
            <circle class="perf-ring-bg" cx="40" cy="40" r="36"/>
            <circle class="perf-ring-fill" cx="40" cy="40" r="36"
                    data-pct="<?= round($mathAccuracy) ?>"/>
          </svg>
          <div class="perf-ring-center">
            <span class="perf-ring-pct" data-count="<?= round($mathAccuracy) ?>"><?= round($mathAccuracy) ?>%</span>
          </div>
        </div>
        <div class="perf-mastery"><?= htmlspecialchars($mathMastery) ?></div>

        <?php if (!empty($weakMath)): ?>
          <p class="perf-weak-title">Areas to improve</p>
          <div class="perf-weak-list">
            <?php foreach ($weakMath as $wt): ?>
            <div class="perf-weak-item">
              <span class="topic-name"><?= htmlspecialchars($wt['topic'] ?? 'Unknown') ?></span>
              <span class="topic-pct"><?= intval($wt['accuracy'] ?? 0) ?>%</span>
              <a href="/ai-tutor?topic=<?= urlencode($wt['topic'] ?? '') ?>&cat=math" class="ai-link">Ask AI</a>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Reading & Writing -->
    <div class="g-card" style="padding:24px 20px;box-shadow:none;border:1px solid rgba(20,50,48,.06)">
      <div class="perf-card-inner">
        <p class="perf-section-label">Reading &amp; Writing</p>
        <div class="perf-accuracy-ring">
          <svg viewBox="0 0 80 80">
            <circle class="perf-ring-bg" cx="40" cy="40" r="36"/>
            <circle class="perf-ring-fill" cx="40" cy="40" r="36"
                    data-pct="<?= round($rwAccuracy) ?>"/>
          </svg>
          <div class="perf-ring-center">
            <span class="perf-ring-pct" data-count="<?= round($rwAccuracy) ?>"><?= round($rwAccuracy) ?>%</span>
          </div>
        </div>
        <div class="perf-mastery"><?= htmlspecialchars($rwMastery) ?></div>

        <?php if (!empty($weakRW)): ?>
          <p class="perf-weak-title">Areas to improve</p>
          <div class="perf-weak-list">
            <?php foreach ($weakRW as $wt): ?>
            <div class="perf-weak-item">
              <span class="topic-name"><?= htmlspecialchars($wt['topic'] ?? 'Unknown') ?></span>
              <span class="topic-pct"><?= intval($wt['accuracy'] ?? 0) ?>%</span>
              <a href="/ai-tutor?topic=<?= urlencode($wt['topic'] ?? '') ?>&cat=rw" class="ai-link">Ask AI</a>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ── 5. Streak & XP Row ────────────────────────────────────── -->
<div class="g-card full reveal">
  <div class="card-header">
    <h2 class="card-title">Progress &amp; Streaks</h2>
  </div>
  <div class="streak-row">
    <div class="streak-item">
      <div class="streak-item-icon fire"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 12c2-2.96 0-7-1-8 0 3.04-2.22 5.5-4 7.5S3 16 5.5 18.5C7.5 20.5 10 21 12 21s4.5-.5 6.5-2.5S22 14.54 22 12c-1.5 1-4 0-5-2.5-.5-1.5-1-3-1-5-1 1-3 5.04-4 7.5z"/></svg></div>
      <span class="streak-val" data-count="<?= $currentStreak ?>"><?= $currentStreak ?></span>
      <span class="streak-label">Day Streak</span>
    </div>
    <div class="streak-item">
      <div class="streak-item-icon star"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></div>
      <span class="streak-val" data-count="<?= $longestStreak ?>"><?= $longestStreak ?></span>
      <span class="streak-label">Best Streak</span>
    </div>
    <div class="streak-item">
      <div class="streak-item-icon bolt"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg></div>
      <span class="streak-val" data-count="<?= $totalXP ?>"><?= number_format($totalXP) ?></span>
      <span class="streak-label">Total XP</span>
    </div>
    <div class="streak-item">
      <div class="streak-item-icon shield"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
      <span class="streak-val">Lv <?= $level ?></span>
      <span class="streak-label">Current Level</span>
    </div>
    <div class="streak-item">
      <div class="streak-item-icon trophy"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 010-5H6"/><path d="M18 9h1.5a2.5 2.5 0 000-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0012 0V2z"/></svg></div>
      <span class="streak-val"><?= htmlspecialchars($leagueName) ?></span>
      <span class="streak-label">League</span>
    </div>
    <div class="streak-item">
      <div class="streak-item-icon medal"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg></div>
      <span class="streak-val">#<?= $userRank ?: '—' ?></span>
      <span class="streak-label">Rank</span>
    </div>
    <div class="xp-progress-wrap">
      <div class="xp-bar-outer">
        <div class="xp-bar-inner" data-width="<?= $xpPercent ?>"></div>
      </div>
      <div class="xp-bar-labels">
        <span><?= number_format($xpInLevel) ?> / <?= number_format($xpNeeded) ?> XP to Level <?= $level + 1 ?></span>
        <span><?= $xpPercent ?>%</span>
      </div>
    </div>
  </div>
</div>

<!-- ── 6. Quick Insights Grid ────────────────────────────────── -->
<div class="full reveal">
  <p class="section-title">Quick Insights</p>
  <div class="insights-grid">
    <div class="insight-card">
      <div class="insight-icon purple"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg></div>
      <p class="insight-val" data-count="<?= $questionsPracticed ?>"><?= number_format($questionsPracticed) ?></p>
      <p class="insight-label">Questions Practiced</p>
    </div>
    <div class="insight-card">
      <div class="insight-icon blue"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></div>
      <p class="insight-val" data-count="<?= $practiceTestsTaken ?>"><?= $practiceTestsTaken ?></p>
      <p class="insight-label">Practice Tests</p>
    </div>
    <div class="insight-card">
      <div class="insight-icon amber"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg></div>
      <p class="insight-val" data-count="<?= $aiTutorConversations ?>"><?= $aiTutorConversations ?></p>
      <p class="insight-label">AI Tutor Chats</p>
    </div>
    <div class="insight-card">
      <div class="insight-icon rose"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
      <p class="insight-val" data-count="<?= $studyHoursWeek ?>"><?= $studyHoursWeek ?></p>
      <p class="insight-label">Hours This Week</p>
    </div>
  </div>
</div>

<!-- ── 7. Leaderboard Preview ────────────────────────────────── -->
<div class="g-card reveal">
  <div class="card-header">
    <h2 class="card-title">Leaderboard</h2>
    <a href="/leaderboard" class="card-link">
      View Full
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
  </div>

  <?php if (!empty($leaderboardTop)): ?>
    <div class="lb-list">
      <?php foreach ($leaderboardTop as $i => $entry):
        $isMe = (intval($entry['user_id'] ?? 0) === $userId);
      ?>
      <div class="lb-row<?= $isMe ? ' me' : '' ?>">
        <span class="lb-rank"><?= $i + 1 ?></span>
        <span class="lb-name"><?= htmlspecialchars(($entry['first_name'] ?? '') . ' ' . substr($entry['last_name'] ?? '', 0, 1) . '.') ?><?= $isMe ? ' (You)' : '' ?></span>
        <span class="lb-xp"><?= number_format(intval($entry['xp'] ?? 0)) ?> XP</span>
      </div>
      <?php endforeach; ?>

      <?php // If user is not in top 5, show their row separately
        $userInTop = false;
        foreach ($leaderboardTop as $e) {
            if (intval($e['user_id'] ?? 0) === $userId) { $userInTop = true; break; }
        }
        if (!$userInTop && $userRank > 0):
      ?>
      <div style="text-align:center;padding:4px;font-size:.75rem;color:rgba(20,50,48,.3)">...</div>
      <div class="lb-row me">
        <span class="lb-rank"><?= $userRank ?></span>
        <span class="lb-name"><?= htmlspecialchars($firstName) ?> (You)</span>
        <span class="lb-xp"><?= number_format($totalXP) ?> XP</span>
      </div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <p class="no-sessions">Leaderboard data is loading. Practice more to climb the ranks!</p>
  <?php endif; ?>
</div>

<!-- ── 8. Recent Achievements ────────────────────────────────── -->
<div class="g-card reveal">
  <div class="card-header">
    <h2 class="card-title">Recent Achievements</h2>
    <a href="/achievements" class="card-link">
      View All
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
  </div>

  <?php if (!empty($recentBadges)): ?>
    <div class="badges-grid">
      <?php foreach ($recentBadges as $badge): ?>
      <div class="badge-card">
        <div class="badge-icon"><?= !empty($badge['icon_svg']) ? $badge['icon_svg'] : '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>' ?></div>
        <p class="badge-name"><?= htmlspecialchars($badge['name'] ?? 'Achievement') ?></p>
        <p class="badge-date"><?= !empty($badge['unlocked_at']) ? date('M j', strtotime($badge['unlocked_at'])) : '' ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="no-badges">Complete tasks and hit milestones to earn badges!</p>
  <?php endif; ?>
</div>

<!-- ── 9. Upcoming Sessions ──────────────────────────────────── -->
<div class="g-card full reveal">
  <div class="card-header">
    <h2 class="card-title">Upcoming Live Sessions</h2>
    <a href="/sessions" class="card-link">
      Browse All
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
  </div>

  <?php if (!empty($upcomingSessions)): ?>
    <div class="session-list">
      <?php foreach ($upcomingSessions as $sess):
        $sessDate = strtotime($sess['start_time'] ?? 'now');
      ?>
      <div class="session-card">
        <div class="session-date-box">
          <div class="sdb-month"><?= date('M', $sessDate) ?></div>
          <div class="sdb-day"><?= date('j', $sessDate) ?></div>
        </div>
        <div class="session-info">
          <p class="si-title"><?= htmlspecialchars($sess['title'] ?? 'Tutoring Session') ?></p>
          <p class="si-time"><?= date('g:i A', $sessDate) ?> &middot; <?= intval($sess['duration_minutes'] ?? 60) ?> min</p>
        </div>
        <?php if (empty($sess['enrolled'])): ?>
          <button class="session-enroll" data-session-id="<?= intval($sess['id'] ?? 0) ?>">Enroll</button>
        <?php else: ?>
          <span style="font-size:.8rem;font-weight:600;color:var(--ac2)">Enrolled &#10003;</span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="no-sessions">No upcoming sessions right now. Check back soon!</p>
  <?php endif; ?>
</div>

</div><!-- /dash-grid -->
</main><!-- /main-content -->

<script>
(function(){
  'use strict';

  /* ── Scroll Reveal (IntersectionObserver) ─────────────────── */
  const revealEls = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry, i) => {
        if (entry.isIntersecting) {
          setTimeout(() => entry.target.classList.add('visible'), i * 60);
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });
    revealEls.forEach(el => observer.observe(el));
  } else {
    revealEls.forEach(el => el.classList.add('visible'));
  }

  /* ── Animate Counting Numbers ─────────────────────────────── */
  function animateCount(el, target, duration) {
    if (el.dataset.animated) return;
    el.dataset.animated = '1';
    const start = 0;
    const startTime = performance.now();
    const isFloat = String(target).includes('.');
    const format = target >= 1000 && !isFloat;

    function step(now) {
      const elapsed = now - startTime;
      const progress = Math.min(elapsed / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3); // easeOutCubic
      const current = start + (target - start) * eased;

      if (isFloat) {
        el.textContent = current.toFixed(1);
      } else if (format) {
        el.textContent = Math.round(current).toLocaleString();
      } else {
        el.textContent = Math.round(current);
      }

      if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  const countEls = document.querySelectorAll('[data-count]');
  if ('IntersectionObserver' in window) {
    const countObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const val = parseFloat(entry.target.dataset.count);
          if (val > 0) animateCount(entry.target, val, 1200);
          countObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.3 });
    countEls.forEach(el => countObserver.observe(el));
  }

  /* ── SVG Ring Animations ──────────────────────────────────── */
  function animateRings() {
    // Score ring
    document.querySelectorAll('.score-ring-fill').forEach(ring => {
      const pct = parseFloat(ring.dataset.pct || 0);
      const circumference = 2 * Math.PI * parseFloat(ring.getAttribute('r'));
      ring.style.strokeDasharray = circumference;
      ring.style.strokeDashoffset = circumference;
      setTimeout(() => {
        ring.style.strokeDashoffset = circumference - (circumference * pct / 100);
      }, 400);
    });

    // Performance rings
    document.querySelectorAll('.perf-ring-fill').forEach(ring => {
      const pct = parseFloat(ring.dataset.pct || 0);
      const circumference = 2 * Math.PI * parseFloat(ring.getAttribute('r'));
      ring.style.strokeDasharray = circumference;
      ring.style.strokeDashoffset = circumference;
      setTimeout(() => {
        ring.style.strokeDashoffset = circumference - (circumference * pct / 100);
      }, 600);
    });
  }

  if ('IntersectionObserver' in window) {
    const ringObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          animateRings();
          ringObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.2 });

    const scoreCard = document.querySelector('.score-card');
    if (scoreCard) ringObserver.observe(scoreCard);

    document.querySelectorAll('.perf-accuracy-ring').forEach(el => ringObserver.observe(el));
  } else {
    animateRings();
  }

  /* ── XP Progress Bar ──────────────────────────────────────── */
  const xpBar = document.querySelector('.xp-bar-inner');
  if (xpBar) {
    const xpObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          setTimeout(() => {
            xpBar.style.width = xpBar.dataset.width + '%';
          }, 300);
          xpObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.3 });
    xpObserver.observe(xpBar);
  }

  /* ── Task Checkbox Toggle ─────────────────────────────────── */
  document.querySelectorAll('.plan-check').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.stopPropagation();
      const taskId = this.dataset.task;
      const item = this.closest('.plan-item');
      const isDone = this.classList.toggle('done');
      item.classList.toggle('completed', isDone);

      fetch('/api/mark-complete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ task_id: parseInt(taskId), completed: isDone })
      }).catch(err => console.warn('Failed to update task:', err));
    });
  });

  /* ── Session Enroll ───────────────────────────────────────── */
  document.querySelectorAll('.session-enroll').forEach(btn => {
    btn.addEventListener('click', function() {
      const sessionId = this.dataset.sessionId;
      const el = this;
      el.disabled = true;
      el.textContent = 'Enrolling...';

      fetch('/api/enroll-session.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ session_id: parseInt(sessionId) })
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          const span = document.createElement('span');
          span.style.cssText = 'font-size:.8rem;font-weight:600;color:var(--ac2)';
          span.textContent = 'Enrolled \u2713';
          el.replaceWith(span);
        } else {
          el.textContent = 'Retry';
          el.disabled = false;
        }
      })
      .catch(() => {
        el.textContent = 'Retry';
        el.disabled = false;
      });
    });
  });

  /* ── Optional: Periodic Dashboard Refresh ─────────────────── */
  let refreshInterval = 5 * 60 * 1000; // 5 minutes
  setInterval(() => {
    fetch('/api/get-dashboard.php', {
      headers: { 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
      // Update streak if changed
      if (data.current_streak !== undefined) {
        const streakEl = document.querySelector('.streak-val[data-count]');
        if (streakEl) streakEl.textContent = data.current_streak;
      }
      // Update XP if changed
      if (data.xp !== undefined) {
        const xpBarInner = document.querySelector('.xp-bar-inner');
        if (xpBarInner && data.xp_percent !== undefined) {
          xpBarInner.style.width = data.xp_percent + '%';
        }
      }
    })
    .catch(() => {}); // Silently fail
  }, refreshInterval);

})();
</script>
</body>
</html>
