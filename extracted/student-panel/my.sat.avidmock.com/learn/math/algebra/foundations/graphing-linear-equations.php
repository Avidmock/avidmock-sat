<?php
/**
 * /learn/math/algebra/graphing-linear-equations/index.php
 * Lesson page: Graphing Linear Equations
 * Full-width, no sidebar, SEO-optimized
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Quiz.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/CategoryPerformance.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/StudyStreak.php';

$user      = Auth::optionalStudent();
$userId    = $user ? (int)($user['id'] ?? 0) : 0;
$firstName = $user ? explode(' ', $user['name'] ?? 'Student')[0] : 'Student';

/* ── Lesson meta ── */
$lesson = [
    'title'       => 'Graphing Linear Equations',
    'slug'        => 'graphing-linear-equations',
    'subject'     => 'Math',
    'domain'      => 'Algebra',
    'difficulty'  => 'Foundations',
    'duration'    => '13 min read',
    'description' => 'Master graphing linear equations on the coordinate plane — from plotting points using slope and intercept to identifying lines from their equations. A core SAT Math skill tested in every exam.',
    'seo_desc'    => 'Learn to graph linear equations using slope-intercept form for the SAT. Covers plotting points, rise over run, x- and y-intercepts, and special lines. Free SAT Math Algebra lesson.',
    'prev'        => ['title' => 'Comparing Linear Relationships',  'link' => '/learn/math/algebra/comparing-linear-relationships/'],
    'next'        => ['title' => 'Understanding Slope',             'link' => '/learn/math/algebra/slope/'],
];

$questionCount = 5;
$quizUrl       = '/learn/quiz/' . urlencode($lesson['slug']);

/* ── SEO JSON-LD ── */
$jsonLd = json_encode([
    '@context'         => 'https://schema.org',
    '@type'            => 'LearningResource',
    'name'             => 'Graphing Linear Equations — SAT Math',
    'description'      => $lesson['seo_desc'],
    'url'              => 'https://my.sat.avidmock.com/learn/math/algebra/graphing-linear-equations/',
    'provider'         => ['@type' => 'Organization', 'name' => 'Avidmock SAT', 'url' => 'https://avidmock.com'],
    'educationalLevel' => 'High School',
    'teaches'          => 'Graphing Linear Equations for SAT Math',
    'timeRequired'     => 'PT13M',
    'inLanguage'       => 'en',
    'isPartOf'         => ['@type' => 'Course', 'name' => 'SAT Math — Algebra', 'url' => 'https://my.sat.avidmock.com/learn/math/'],
    'hasPart'          => [
        ['@type' => 'Quiz', 'name' => 'Graphing Linear Equations Practice Quiz', 'numberOfQuestions' => $questionCount],
    ],
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

$faqJsonLd = json_encode([
    '@context'   => 'https://schema.org',
    '@type'      => 'FAQPage',
    'mainEntity' => [
        ['@type' => 'Question', 'name' => 'How do you graph a linear equation in slope-intercept form?',
         'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'To graph y = mx + b, start by plotting the y-intercept (0, b) on the y-axis. Then use the slope m = rise/run to find a second point: move up (or down) by the rise, then right by the run. Draw a line through both points.']],
        ['@type' => 'Question', 'name' => 'How does the SAT test graphing linear equations?',
         'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'The SAT shows a graph and asks you to identify the equation, or gives you an equation and asks which graph matches. Key skills are reading slope and intercept from a graph, identifying positive/negative slopes, and recognizing horizontal and vertical lines.']],
        ['@type' => 'Question', 'name' => 'What are x-intercept and y-intercept?',
         'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'The y-intercept is where the line crosses the y-axis — set x = 0 and solve for y. The x-intercept is where the line crosses the x-axis — set y = 0 and solve for x. Both intercepts are useful anchor points when sketching a line.']],
    ],
], JSON_UNESCAPED_SLASHES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Graphing Linear Equations — SAT Math Algebra | Avidmock</title>
    <meta name="description" content="<?= htmlspecialchars($lesson['seo_desc']) ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://my.sat.avidmock.com/learn/math/algebra/graphing-linear-equations/">

    <meta property="og:type"        content="article">
    <meta property="og:title"       content="Graphing Linear Equations — SAT Math | Avidmock">
    <meta property="og:description" content="<?= htmlspecialchars($lesson['seo_desc']) ?>">
    <meta property="og:url"         content="https://my.sat.avidmock.com/learn/math/algebra/graphing-linear-equations/">
    <meta property="og:site_name"   content="Avidmock SAT">

    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="Graphing Linear Equations — SAT Math">
    <meta name="twitter:description" content="<?= htmlspecialchars($lesson['seo_desc']) ?>">

    <script type="application/ld+json"><?= $jsonLd ?></script>
    <script type="application/ld+json"><?= $faqJsonLd ?></script>

    <link rel="icon" type="image/x-icon" href="/favicon.ico">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="192x192" href="/android-chrome-192x192.png">
<link rel="icon" type="image/png" sizes="512x512" href="/android-chrome-512x512.png">
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800;1,9..40,400;1,9..40,500&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">

    <style>
:root {
    --dk:   #143230;
    --dk2:  #1a3f3c;
    --ac:   #1fe290;
    --ac2:  #17c87a;
    --ac3:  rgba(31,226,144,.08);
    --tx:   #0f1923;
    --tx2:  #374151;
    --tx3:  #6b7280;
    --bg:   #f7faf9;
    --bg2:  #ffffff;
    --bd:   #e2ebe9;
    --bd2:  #d1dcd9;
    --err:  #ef4444;
    --warn: #f59e0b;
    --ff:   'DM Sans', -apple-system, sans-serif;
    --ffs:  'DM Serif Display', Georgia, serif;
    --topbar-h: 60px;
    --content-w: 780px;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; font-size: 16px; }
body { font-family: var(--ff); background: var(--bg); color: var(--tx); -webkit-font-smoothing: antialiased; overflow-x: hidden; }

/* TOPBAR */
.topbar { position: fixed; top: 0; left: 0; right: 0; height: var(--topbar-h); background: rgba(247,250,249,.96); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border-bottom: 1px solid var(--bd); display: flex; align-items: center; padding: 0 24px; gap: 16px; z-index: 500; }
.topbar-logo { display: flex; align-items: center; gap: 8px; text-decoration: none; font-size: .875rem; font-weight: 800; color: var(--dk); letter-spacing: -.02em; flex-shrink: 0; }
.topbar-logo-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--ac); flex-shrink: 0; }
.topbar-breadcrumb { display: flex; align-items: center; gap: 6px; font-size: .8125rem; color: var(--tx3); min-width: 0; overflow: hidden; }
.topbar-breadcrumb a { color: var(--tx3); text-decoration: none; font-weight: 500; transition: color .18s; white-space: nowrap; }
.topbar-breadcrumb a:hover { color: var(--ac2); }
.topbar-breadcrumb-sep { width: 14px; height: 14px; stroke: var(--bd2); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }
.topbar-breadcrumb-current { font-weight: 700; color: var(--tx); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.topbar-right { margin-left: auto; display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
.topbar-btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; border-radius: 9px; font-family: var(--ff); font-size: .8125rem; font-weight: 700; text-decoration: none; cursor: pointer; border: none; transition: all .2s; letter-spacing: -.01em; background: none; }
.topbar-btn-outline { background: transparent; border: 1.5px solid var(--bd); color: var(--tx2); }
.topbar-btn-outline:hover { border-color: var(--ac); color: var(--dk); background: var(--ac3); }
.topbar-btn-outline svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.topbar-btn-ac { background: var(--ac); color: var(--dk); border: none; }
.topbar-btn-ac:hover { background: var(--ac2); box-shadow: 0 4px 16px rgba(31,226,144,.3); transform: translateY(-1px); }
.topbar-btn-ac svg { width: 14px; height: 14px; stroke: var(--dk); fill: none; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
.topbar-avatar { width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg, var(--ac), #0da367); display: flex; align-items: center; justify-content: center; font-size: .75rem; font-weight: 800; color: var(--dk); text-decoration: none; border: 2px solid rgba(31,226,144,.25); flex-shrink: 0; }

/* HERO */
.lesson-hero { background: var(--dk); padding: calc(var(--topbar-h) + 48px) 24px 52px; position: relative; overflow: hidden; }
.lesson-hero::before { content: ''; position: absolute; inset: 0; background-image: linear-gradient(rgba(31,226,144,.03) 1px, transparent 1px), linear-gradient(90deg, rgba(31,226,144,.03) 1px, transparent 1px); background-size: 40px 40px; pointer-events: none; }
.lesson-hero::after { content: ''; position: absolute; top: -120px; right: -80px; width: 500px; height: 500px; background: radial-gradient(circle, rgba(31,226,144,.06) 0%, transparent 60%); border-radius: 50%; pointer-events: none; }
.lesson-hero-inner { max-width: var(--content-w); margin: 0 auto; position: relative; z-index: 1; }
.lesson-hero-tags { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }
.lesson-tag { display: inline-flex; align-items: center; gap: 5px; padding: 4px 11px; border-radius: 50px; font-size: .6875rem; font-weight: 700; letter-spacing: .4px; text-transform: uppercase; }
.tag-domain   { background: rgba(31,226,144,.1);  color: var(--ac); border: 1px solid rgba(31,226,144,.2); }
.tag-level    { background: rgba(255,255,255,.06); color: rgba(255,255,255,.55); border: 1px solid rgba(255,255,255,.1); }
.tag-duration { background: rgba(255,255,255,.06); color: rgba(255,255,255,.45); border: 1px solid rgba(255,255,255,.08); }
.lesson-hero-title { font-family: var(--ffs); font-size: clamp(1.875rem, 5vw, 3rem); color: #fff; line-height: 1.12; letter-spacing: -.02em; margin-bottom: 16px; }
.lesson-hero-desc { font-size: 1.0625rem; color: rgba(255,255,255,.5); line-height: 1.7; max-width: 600px; margin-bottom: 28px; }
.lesson-hero-meta { display: flex; align-items: center; gap: 20px; flex-wrap: wrap; }
.hero-meta-item { display: flex; align-items: center; gap: 6px; font-size: .8125rem; color: rgba(255,255,255,.4); font-weight: 500; }
.hero-meta-item svg { width: 14px; height: 14px; stroke: rgba(255,255,255,.3); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }

/* LAYOUT */
.lesson-layout { display: grid; grid-template-columns: 1fr 300px; gap: 32px; max-width: 1160px; margin: 0 auto; padding: 40px 24px 80px; align-items: start; }
.lesson-main { min-width: 0; }
.lesson-aside { position: sticky; top: calc(var(--topbar-h) + 20px); }

/* VIDEO */
.video-section { background: #fff; border: 1px solid var(--bd); border-radius: 20px; overflow: hidden; margin-bottom: 28px; box-shadow: 0 2px 16px rgba(20,50,48,.04); }
.video-container { position: relative; padding-bottom: 56.25%; background: var(--dk); overflow: hidden; }
.video-coming-soon { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 16px; background: linear-gradient(145deg, #0f2422 0%, #143230 50%, #0a1e1c 100%); }
.video-cs-grid { position: absolute; inset: 0; background-image: linear-gradient(rgba(31,226,144,.03) 1px, transparent 1px), linear-gradient(90deg, rgba(31,226,144,.03) 1px, transparent 1px); background-size: 32px 32px; }
.video-cs-glow { position: absolute; width: 300px; height: 300px; border-radius: 50%; background: radial-gradient(circle, rgba(31,226,144,.1) 0%, transparent 70%); top: 50%; left: 50%; transform: translate(-50%,-50%); animation: glowPulse 3s ease-in-out infinite; }
@keyframes glowPulse { 0%,100%{transform:translate(-50%,-50%) scale(1);opacity:.6} 50%{transform:translate(-50%,-50%) scale(1.15);opacity:1} }
.video-cs-icon { position: relative; width: 72px; height: 72px; border-radius: 50%; background: rgba(31,226,144,.08); border: 1.5px solid rgba(31,226,144,.2); display: flex; align-items: center; justify-content: center; animation: iconFloat 4s ease-in-out infinite; }
@keyframes iconFloat { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-8px)} }
.video-cs-icon svg { width: 32px; height: 32px; stroke: var(--ac); fill: none; stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round; }
.video-cs-label { position: relative; font-family: var(--ffs); font-size: 1.5rem; color: #fff; letter-spacing: -.02em; }
.video-cs-sub { position: relative; font-size: .875rem; color: rgba(255,255,255,.35); text-align: center; max-width: 260px; line-height: 1.55; }
.video-cs-badge { position: relative; display: inline-flex; align-items: center; gap: 6px; padding: 6px 16px; background: rgba(31,226,144,.08); border: 1px solid rgba(31,226,144,.18); border-radius: 50px; font-size: .75rem; font-weight: 700; color: var(--ac); letter-spacing: .3px; }
.video-cs-badge-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--ac); animation: dotBlink 1.4s ease-in-out infinite; }
@keyframes dotBlink { 0%,100%{opacity:1} 50%{opacity:.2} }
.video-meta { padding: 16px 20px; border-top: 1px solid var(--bd); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
.video-meta-title { font-size: .9375rem; font-weight: 700; color: var(--tx); letter-spacing: -.01em; }
.video-notify-btn { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; background: var(--ac3); border: 1px solid rgba(31,226,144,.2); border-radius: 8px; font-family: var(--ff); font-size: .75rem; font-weight: 700; color: var(--ac2); cursor: pointer; transition: all .2s; }
.video-notify-btn:hover { background: rgba(31,226,144,.14); }
.video-notify-btn svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; }
.video-notify-btn:disabled { cursor: default; opacity: .8; }

/* LESSON CONTENT */
.lesson-content { background: #fff; border: 1px solid var(--bd); border-radius: 20px; padding: 2rem 2.25rem; margin-bottom: 28px; box-shadow: 0 2px 16px rgba(20,50,48,.04); }
.lesson-content h2 { font-family: var(--ffs); font-size: 1.5rem; font-weight: 400; color: var(--tx); letter-spacing: -.025em; line-height: 1.3; margin-bottom: 1rem; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--bd); }
.lesson-content h2:first-child { margin-top: 0; padding-top: 0; border-top: none; }
.lesson-content h3 { font-size: 1rem; font-weight: 800; color: var(--tx); letter-spacing: -.015em; margin-bottom: .625rem; margin-top: 1.5rem; }
.lesson-content p { font-size: 1rem; line-height: 1.78; color: var(--tx2); margin-bottom: 1rem; }
.lesson-content p:last-child { margin-bottom: 0; }
.lesson-content strong { color: var(--tx); font-weight: 700; }

.key-concept { background: linear-gradient(135deg, rgba(31,226,144,.06), rgba(31,226,144,.02)); border: 1px solid rgba(31,226,144,.18); border-left: 3px solid var(--ac); border-radius: 0 12px 12px 0; padding: 1rem 1.25rem; margin: 1.25rem 0; }
.key-concept-label { font-size: .625rem; font-weight: 800; color: var(--ac2); text-transform: uppercase; letter-spacing: .6px; margin-bottom: .375rem; }
.key-concept p { font-size: .9375rem; color: var(--tx); margin: 0; line-height: 1.65; }

.steps { display: flex; flex-direction: column; gap: 12px; margin: 1.25rem 0; }
.step { display: flex; gap: 14px; padding: 14px 16px; background: var(--bg); border: 1px solid var(--bd); border-radius: 12px; transition: border-color .2s; }
.step:hover { border-color: var(--bd2); }
.step-num { width: 28px; height: 28px; border-radius: 50%; background: var(--dk); color: var(--ac); font-size: .75rem; font-weight: 800; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 1px; }
.step-body { flex: 1; }
.step-title { font-size: .9375rem; font-weight: 700; color: var(--tx); margin-bottom: 3px; letter-spacing: -.01em; }
.step-desc { font-size: .875rem; color: var(--tx3); line-height: 1.6; }

.example { background: var(--dk); border-radius: 14px; padding: 1.25rem 1.5rem; margin: 1.25rem 0; position: relative; overflow: hidden; }
.example::before { content: ''; position: absolute; inset: 0; background-image: linear-gradient(rgba(31,226,144,.03) 1px, transparent 1px), linear-gradient(90deg, rgba(31,226,144,.03) 1px, transparent 1px); background-size: 28px 28px; pointer-events: none; }
.example-label { font-size: .625rem; font-weight: 800; color: var(--ac); text-transform: uppercase; letter-spacing: .8px; margin-bottom: .75rem; display: flex; align-items: center; gap: 6px; position: relative; }
.example-label::after { content: ''; flex: 1; height: 1px; background: rgba(31,226,144,.15); }
.example-equation { font-family: 'Courier New', monospace; font-size: 1.0625rem; color: #fff; line-height: 1.9; position: relative; letter-spacing: .02em; }
.example-equation .step-label { font-family: var(--ff); font-size: .6875rem; font-weight: 600; color: rgba(255,255,255,.3); display: inline-block; width: 140px; margin-right: 8px; }
.example-equation .highlight { color: var(--ac); font-weight: 700; }

/* Coordinate grid SVG card */
.graph-card { background: var(--bg); border: 1px solid var(--bd); border-radius: 14px; padding: 20px; margin: 1.25rem 0; display: flex; flex-direction: column; align-items: center; gap: 12px; }
.graph-card-label { font-size: .625rem; font-weight: 800; color: var(--tx3); text-transform: uppercase; letter-spacing: .5px; }
.graph-card svg { max-width: 320px; width: 100%; }

/* Slope cards */
.slope-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin: 1.25rem 0; }
.slope-card { background: var(--bg); border: 1px solid var(--bd); border-radius: 12px; padding: 14px 12px; text-align: center; transition: border-color .2s; }
.slope-card:hover { border-color: var(--bd2); }
.slope-card-symbol { font-family: var(--ffs); font-size: 1.75rem; line-height: 1; margin-bottom: 8px; }
.slope-card-label { font-size: .6875rem; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 4px; }
.slope-card-desc { font-size: .75rem; color: var(--tx3); line-height: 1.4; }
.sc-pos  .slope-card-symbol { color: var(--ac2); }
.sc-neg  .slope-card-symbol { color: var(--err); }
.sc-zero .slope-card-symbol { color: var(--warn); }
.sc-und  .slope-card-symbol { color: var(--tx3); }
.sc-pos  .slope-card-label { color: var(--ac2); }
.sc-neg  .slope-card-label { color: var(--err); }
.sc-zero .slope-card-label { color: var(--warn); }
.sc-und  .slope-card-label { color: var(--tx3); }

/* Intercept table */
.compare-table { width: 100%; border-collapse: collapse; margin: 1.25rem 0; font-size: .9rem; }
.compare-table th { background: var(--dk); color: var(--ac); font-weight: 700; font-size: .75rem; text-transform: uppercase; letter-spacing: .4px; padding: 10px 14px; text-align: left; }
.compare-table th:first-child { border-radius: 10px 0 0 0; }
.compare-table th:last-child  { border-radius: 0 10px 0 0; }
.compare-table td { padding: 10px 14px; border-bottom: 1px solid var(--bd); color: var(--tx2); font-size: .875rem; line-height: 1.55; }
.compare-table tr:last-child td { border-bottom: none; }
.compare-table tr:hover td { background: var(--bg); }
.compare-table td:first-child { font-weight: 700; color: var(--tx); }
.compare-table .mono { font-family: 'Courier New', monospace; color: var(--dk); font-weight: 700; }

.sat-tip { display: flex; gap: 12px; padding: 14px 16px; background: rgba(245,158,11,.06); border: 1px solid rgba(245,158,11,.18); border-radius: 12px; margin: 1.25rem 0; }
.sat-tip-ico { width: 32px; height: 32px; background: rgba(245,158,11,.1); border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.sat-tip-ico svg { width: 16px; height: 16px; stroke: var(--warn); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.sat-tip-label { font-size: .625rem; font-weight: 800; color: var(--warn); text-transform: uppercase; letter-spacing: .6px; margin-bottom: 3px; }
.sat-tip-text { font-size: .875rem; color: var(--tx2); line-height: 1.6; }

/* Quiz CTA */
.quiz-cta-banner { display: flex; align-items: center; gap: 16px; background: var(--dk); border-radius: 16px; padding: 1.5rem; margin: 2rem 0; position: relative; overflow: hidden; text-decoration: none; cursor: pointer; transition: transform .25s cubic-bezier(.16,1,.3,1), box-shadow .25s; border: none; font-family: var(--ff); width: 100%; text-align: left; }
.quiz-cta-banner:hover { transform: translateY(-2px); box-shadow: 0 12px 36px rgba(20,50,48,.18); }
.quiz-cta-banner::before { content: ''; position: absolute; inset: 0; background-image: linear-gradient(rgba(31,226,144,.025) 1px, transparent 1px), linear-gradient(90deg, rgba(31,226,144,.025) 1px, transparent 1px); background-size: 28px 28px; pointer-events: none; }
.quiz-cta-ico { width: 52px; height: 52px; border-radius: 14px; background: rgba(31,226,144,.1); border: 1px solid rgba(31,226,144,.2); display: flex; align-items: center; justify-content: center; flex-shrink: 0; position: relative; }
.quiz-cta-ico svg { width: 24px; height: 24px; stroke: var(--ac); fill: none; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
.quiz-cta-body { flex: 1; position: relative; }
.quiz-cta-label { font-size: .625rem; font-weight: 800; color: var(--ac); text-transform: uppercase; letter-spacing: .6px; margin-bottom: 4px; }
.quiz-cta-title { font-size: 1.0625rem; font-weight: 800; color: #fff; letter-spacing: -.02em; margin-bottom: 3px; }
.quiz-cta-sub { font-size: .8125rem; color: rgba(255,255,255,.4); }
.quiz-cta-btn { flex-shrink: 0; padding: 10px 22px; background: var(--ac); color: var(--dk); border: none; border-radius: 10px; font-family: var(--ff); font-size: .875rem; font-weight: 800; position: relative; display: flex; align-items: center; gap: 6px; transition: all .2s; pointer-events: none; }
.quiz-cta-banner:hover .quiz-cta-btn { background: var(--ac2); box-shadow: 0 4px 16px rgba(31,226,144,.35); }
.quiz-cta-btn svg { width: 14px; height: 14px; stroke: var(--dk); fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }

/* ASIDE */
.aside-card { background: #fff; border: 1px solid var(--bd); border-radius: 18px; overflow: hidden; margin-bottom: 16px; box-shadow: 0 2px 12px rgba(20,50,48,.04); }
.aside-card-hd { padding: .875rem 1.125rem; border-bottom: 1px solid var(--bd); font-size: .6875rem; font-weight: 800; color: var(--tx3); text-transform: uppercase; letter-spacing: .5px; display: flex; align-items: center; gap: 7px; }
.aside-card-hd svg { width: 13px; height: 13px; stroke: var(--tx3); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.aside-card-body { padding: 1rem 1.125rem; }
.lesson-nav { display: flex; flex-direction: column; gap: 6px; padding: 1rem 1.125rem; }
.lesson-nav-item { display: flex; align-items: center; gap: 10px; padding: 9px 10px; border-radius: 9px; text-decoration: none; font-size: .8125rem; font-weight: 600; color: var(--tx2); transition: all .18s; border: 1px solid transparent; }
.lesson-nav-item:hover { background: var(--bg); border-color: var(--bd); }
.lesson-nav-item.active { background: var(--ac3); border-color: rgba(31,226,144,.2); color: var(--dk); }
.lesson-nav-item-check { width: 18px; height: 18px; border-radius: 50%; border: 2px solid var(--bd); flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
.lesson-nav-item.done .lesson-nav-item-check { background: var(--ac); border-color: var(--ac); }
.lesson-nav-item-check svg { width: 9px; height: 9px; stroke: transparent; fill: none; stroke-width: 3; stroke-linecap: round; stroke-linejoin: round; }
.lesson-nav-item.done .lesson-nav-item-check svg { stroke: var(--dk); }
.ai-widget { background: var(--dk); border-radius: 14px; padding: 1.125rem; position: relative; overflow: hidden; }
.ai-widget::before { content: ''; position: absolute; inset: 0; background-image: linear-gradient(rgba(31,226,144,.03) 1px, transparent 1px), linear-gradient(90deg, rgba(31,226,144,.03) 1px, transparent 1px); background-size: 24px 24px; pointer-events: none; }
.ai-widget-top { display: flex; align-items: center; gap: 8px; margin-bottom: .75rem; position: relative; }
.ai-widget-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--ac); animation: dotBlink 1.4s ease-in-out infinite; }
.ai-widget-label { font-size: .75rem; font-weight: 700; color: rgba(255,255,255,.55); }
.ai-widget-question { font-size: .875rem; color: rgba(255,255,255,.35); font-style: italic; line-height: 1.55; margin-bottom: 1rem; position: relative; }
.ai-widget-btn { display: flex; align-items: center; justify-content: center; gap: 6px; width: 100%; padding: 9px; background: rgba(31,226,144,.1); border: 1px solid rgba(31,226,144,.2); border-radius: 9px; font-family: var(--ff); font-size: .8125rem; font-weight: 700; color: var(--ac); cursor: pointer; text-decoration: none; transition: all .2s; position: relative; }
.ai-widget-btn:hover { background: rgba(31,226,144,.16); }
.ai-widget-btn svg { width: 14px; height: 14px; stroke: var(--ac); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.aside-quiz-btn { display: flex; align-items: center; justify-content: center; gap: 7px; width: 100%; padding: 10px; background: var(--dk); color: #fff; border-radius: 10px; font-family: var(--ff); font-size: .875rem; font-weight: 700; text-decoration: none; transition: background .2s; border: none; cursor: pointer; }
.aside-quiz-btn:hover { background: var(--dk2); color: #fff; }
.aside-quiz-btn svg { width: 15px; height: 15px; stroke: var(--ac); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

/* Bottom nav */
.lesson-nav-bottom { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 28px; }
.lesson-nav-btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 22px; border-radius: 12px; text-decoration: none; font-size: .875rem; font-weight: 700; transition: all .22s; border: 1.5px solid var(--bd); background: #fff; color: var(--tx2); }
.lesson-nav-btn:hover { border-color: var(--ac); color: var(--dk); background: var(--ac3); }
.lesson-nav-btn svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.lesson-nav-btn-next { background: var(--dk); color: #fff; border-color: transparent; margin-left: auto; }
.lesson-nav-btn-next:hover { background: var(--dk2); color: #fff; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(20,50,48,.12); }
.lesson-nav-btn-next svg { stroke: var(--ac); }

/* Scroll reveal */
.sr   { opacity:0; transform:translateY(18px); transition:opacity .5s cubic-bezier(.16,1,.3,1), transform .5s cubic-bezier(.16,1,.3,1); }
.sr.v { opacity:1; transform:none; }
.d1{transition-delay:.05s}.d2{transition-delay:.1s}.d3{transition-delay:.15s}.d4{transition-delay:.2s}

/* Responsive */
@media (max-width: 1024px) {
    .lesson-layout { grid-template-columns: 1fr; }
    .lesson-aside { position: static; }
    .aside-card:last-child { display: none; }
    .slope-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
    .lesson-layout { padding: 24px 16px 64px; gap: 20px; }
    .lesson-hero { padding: calc(var(--topbar-h) + 32px) 16px 36px; }
    .lesson-hero-title { font-size: 1.75rem; }
    .lesson-content { padding: 1.375rem 1.125rem; }
    .topbar { padding: 0 14px; gap: 10px; }
    .topbar-breadcrumb { display: none; }
    .quiz-cta-banner { flex-direction: column; text-align: center; }
    .quiz-cta-btn { width: 100%; justify-content: center; }
    .lesson-nav-bottom { flex-direction: column; }
    .lesson-nav-btn-next { width: 100%; justify-content: center; }
}
@media (max-width: 480px) {
    .topbar-btn-outline { display: none; }
    .slope-grid { grid-template-columns: repeat(2, 1fr); }
}
    </style>
</head>
<body>

<!-- TOPBAR -->
<header class="topbar">
    <nav class="topbar-breadcrumb" aria-label="Breadcrumb">
        <a href="/learn/math/">Math</a>
        <svg class="topbar-breadcrumb-sep" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        <a href="/learn/math/#algebra">Algebra</a>
        <svg class="topbar-breadcrumb-sep" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        <span class="topbar-breadcrumb-current">Graphing Linear Equations</span>
    </nav>
    <div class="topbar-right">
        <a href="/learn/math/" class="topbar-btn topbar-btn-outline">
            <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
            All Lessons
        </a>
        <a href="<?= htmlspecialchars($quizUrl) ?>" class="topbar-btn topbar-btn-ac" aria-label="Take practice quiz">
            <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
            Take Quiz
        </a>
    </div>
</header>

<!-- HERO -->
<section class="lesson-hero" aria-label="Lesson overview">
    <div class="lesson-hero-inner">
        <div class="lesson-hero-tags">
            <span class="lesson-tag tag-domain"><?= htmlspecialchars($lesson['domain']) ?></span>
            <span class="lesson-tag tag-level"><?= htmlspecialchars($lesson['difficulty']) ?></span>
            <span class="lesson-tag tag-duration">
                <svg style="width:10px;height:10px;stroke:rgba(255,255,255,.4);fill:none;stroke-width:2;stroke-linecap:round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <?= htmlspecialchars($lesson['duration']) ?>
            </span>
        </div>
        <h1 class="lesson-hero-title"><?= htmlspecialchars($lesson['title']) ?></h1>
        <p class="lesson-hero-desc"><?= htmlspecialchars($lesson['description']) ?></p>
        <div class="lesson-hero-meta">
            <div class="hero-meta-item">
                <svg viewBox="0 0 24 24"><path d="M4 19l8-14 8 14H4z"/></svg>
                SAT Math · Algebra
            </div>
            <div class="hero-meta-item">
                <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                <?= $questionCount ?> practice questions
            </div>
            <div class="hero-meta-item">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>
                XP reward on completion
            </div>
        </div>
    </div>
</section>

<!-- MAIN LAYOUT -->
<div class="lesson-layout">
    <div class="lesson-main">

        <!-- VIDEO -->
        <div class="video-section sr d1" aria-label="Lesson video">
            <div class="video-container" role="img" aria-label="Video coming soon">
                <div class="video-coming-soon">
                    <div class="video-cs-grid"></div>
                    <div class="video-cs-glow"></div>
                    <div class="video-cs-label">Video Lesson</div>
                    <div class="video-cs-sub">Our instructors are recording this lesson. It will be available very soon.</div>
                    <div class="video-cs-badge">
                        <span class="video-cs-badge-dot"></span>
                        Coming Soon
                    </div>
                </div>
            </div>
            <div class="video-meta">
                <div class="video-meta-title">Graphing Linear Equations — Full Walkthrough</div>
                <div class="video-meta-right">
                    <button class="video-notify-btn" id="notifyBtn" onclick="notifyVideoReady()" aria-label="Notify me when video is ready">
                        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
                        Notify me
                    </button>
                </div>
            </div>
        </div>

        <!-- LESSON CONTENT -->
        <article class="lesson-content sr d2" itemscope itemtype="https://schema.org/LearningResource">
            <meta itemprop="name" content="Graphing Linear Equations">
            <meta itemprop="educationalLevel" content="High School">
            <meta itemprop="teaches" content="Graphing Linear Equations">

            <h2>The Coordinate Plane: A Quick Refresher</h2>
            <p>
                Every point on the coordinate plane is described by an ordered pair <strong>(x, y)</strong>.
                The x-axis runs horizontally; the y-axis runs vertically. Where they cross is the
                <strong>origin (0, 0)</strong>. Linear equations always produce straight lines — no curves,
                no bends — because the relationship between x and y is constant.
            </p>

            <div class="key-concept">
                <div class="key-concept-label">Core Formula</div>
                <p>Every linear equation can be written as <strong>y = mx + b</strong>, where m is the slope (steepness and direction) and b is the y-intercept (where the line crosses the y-axis). This is called slope-intercept form.</p>
            </div>

            <!-- Inline SVG coordinate plane -->
            <div class="graph-card">
                <div class="graph-card-label">Coordinate Plane — y = 2x + 1</div>
                <svg viewBox="0 0 320 280" fill="none" xmlns="http://www.w3.org/2000/svg" aria-label="Graph of y equals 2x plus 1">
                    <!-- Grid lines -->
                    <g stroke="#e2ebe9" stroke-width="1">
                        <line x1="40" y1="20" x2="40" y2="260"/>
                        <line x1="80" y1="20" x2="80" y2="260"/>
                        <line x1="120" y1="20" x2="120" y2="260"/>
                        <line x1="200" y1="20" x2="200" y2="260"/>
                        <line x1="240" y1="20" x2="240" y2="260"/>
                        <line x1="280" y1="20" x2="280" y2="260"/>
                        <line x1="20" y1="60" x2="300" y2="60"/>
                        <line x1="20" y1="100" x2="300" y2="100"/>
                        <line x1="20" y1="140" x2="300" y2="140"/>
                        <line x1="20" y1="220" x2="300" y2="220"/>
                        <line x1="20" y1="260" x2="300" y2="260"/>
                    </g>
                    <!-- Axes -->
                    <line x1="160" y1="20" x2="160" y2="265" stroke="#143230" stroke-width="2"/>
                    <line x1="15" y1="180" x2="305" y2="180" stroke="#143230" stroke-width="2"/>
                    <!-- Axis arrows -->
                    <polyline points="156,24 160,16 164,24" fill="#143230"/>
                    <polyline points="297,176 305,180 297,184" fill="#143230"/>
                    <!-- Axis labels -->
                    <text x="167" y="14" font-family="DM Sans,sans-serif" font-size="11" fill="#143230" font-weight="700">y</text>
                    <text x="295" y="196" font-family="DM Sans,sans-serif" font-size="11" fill="#143230" font-weight="700">x</text>
                    <!-- Tick labels x-axis -->
                    <text x="36" y="194" font-family="DM Sans,sans-serif" font-size="10" fill="#6b7280" text-anchor="middle">-3</text>
                    <text x="76" y="194" font-family="DM Sans,sans-serif" font-size="10" fill="#6b7280" text-anchor="middle">-2</text>
                    <text x="116" y="194" font-family="DM Sans,sans-serif" font-size="10" fill="#6b7280" text-anchor="middle">-1</text>
                    <text x="196" y="194" font-family="DM Sans,sans-serif" font-size="10" fill="#6b7280" text-anchor="middle">1</text>
                    <text x="236" y="194" font-family="DM Sans,sans-serif" font-size="10" fill="#6b7280" text-anchor="middle">2</text>
                    <text x="276" y="194" font-family="DM Sans,sans-serif" font-size="10" fill="#6b7280" text-anchor="middle">3</text>
                    <!-- Tick labels y-axis -->
                    <text x="150" y="64" font-family="DM Sans,sans-serif" font-size="10" fill="#6b7280" text-anchor="end">3</text>
                    <text x="150" y="104" font-family="DM Sans,sans-serif" font-size="10" fill="#6b7280" text-anchor="end">2</text>
                    <text x="150" y="144" font-family="DM Sans,sans-serif" font-size="10" fill="#6b7280" text-anchor="end">1</text>
                    <text x="150" y="224" font-family="DM Sans,sans-serif" font-size="10" fill="#6b7280" text-anchor="end">-1</text>
                    <!-- y = 2x + 1  →  at x=-2: y=-3  at x=2: y=5
                         pixel mapping: x_px = 160 + x*40, y_px = 180 - y*40 -->
                    <!-- Line: from (-2.8, -4.6) to (1.8, 4.6) approx -->
                    <line x1="48" y1="365" x2="232" y2="12" stroke="#1fe290" stroke-width="2.5" stroke-linecap="round"/>
                    <!-- Clip with rect for visible area -->
                    <clipPath id="cp"><rect x="20" y="20" width="280" height="245"/></clipPath>
                    <line x1="48" y1="365" x2="232" y2="12" stroke="#1fe290" stroke-width="2.5" stroke-linecap="round" clip-path="url(#cp)"/>
                    <!-- y-intercept point (0, 1): x_px=160, y_px=140 -->
                    <circle cx="160" cy="140" r="5" fill="#1fe290" stroke="#fff" stroke-width="2"/>
                    <text x="167" y="136" font-family="DM Sans,sans-serif" font-size="10" fill="#17c87a" font-weight="700">(0, 1) = y-intercept</text>
                    <!-- slope annotation -->
                    <g stroke="#f59e0b" stroke-width="1.5" stroke-dasharray="3 2">
                        <!-- rise arrow: from (1,3) to (2,3) then up to (2,5) -->
                        <!-- (1,3): x_px=200, y_px=60 -->
                        <!-- (2,3): x_px=240, y_px=60 -->
                        <!-- (2,5): x_px=240, y_px=-20 — clipped -->
                        <line x1="200" y1="60" x2="240" y2="60" clip-path="url(#cp)"/>
                        <text x="208" y="74" font-family="DM Sans,sans-serif" font-size="9" fill="#f59e0b" font-weight="700">run=1</text>
                    </g>
                    <!-- equation label -->
                    <rect x="22" y="22" width="88" height="20" rx="5" fill="#143230"/>
                    <text x="66" y="35" font-family="Courier New,monospace" font-size="11" fill="#1fe290" font-weight="700" text-anchor="middle">y = 2x + 1</text>
                </svg>
                <p style="font-size:.8125rem;color:var(--tx3);text-align:center;max-width:280px;line-height:1.5">The y-intercept <strong style="color:var(--ac2)">(0, 1)</strong> is where the line starts. The slope <strong style="color:var(--dk)">m = 2</strong> means go up 2 for every 1 step right.</p>
            </div>

            <h2>How to Graph Any Linear Equation</h2>

            <div class="steps">
                <div class="step">
                    <div class="step-num">1</div>
                    <div class="step-body">
                        <div class="step-title">Rewrite in Slope-Intercept Form</div>
                        <div class="step-desc">If the equation isn't already y = mx + b, isolate y. Every other form (standard, point-slope) can be converted by simple algebra.</div>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num">2</div>
                    <div class="step-body">
                        <div class="step-title">Plot the Y-Intercept</div>
                        <div class="step-desc">Find b and mark the point (0, b) on the y-axis. This is always your starting anchor — a guaranteed point the line passes through.</div>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num">3</div>
                    <div class="step-body">
                        <div class="step-title">Use Slope to Find a Second Point</div>
                        <div class="step-desc">Write slope as a fraction: m = rise/run. From the y-intercept, move up (rise) and right (run). Mark the new point. If slope is negative, move down instead of up.</div>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num">4</div>
                    <div class="step-body">
                        <div class="step-title">Draw the Line</div>
                        <div class="step-desc">Connect your two points with a straight line and extend it in both directions with arrows. Two points fully define any line.</div>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num">5</div>
                    <div class="step-body">
                        <div class="step-title">Verify with a Third Point</div>
                        <div class="step-desc">Pick any x value, calculate y, and check it falls on your line. If it doesn't, recheck your slope direction or intercept.</div>
                    </div>
                </div>
            </div>

            <h2>The Four Slope Types</h2>
            <p>Slope tells you both the <strong>direction</strong> and <strong>steepness</strong> of a line. Recognising slope type instantly from a graph or equation is a key SAT speed skill.</p>

            <div class="slope-grid">
                <div class="slope-card sc-pos">
                    <div class="slope-card-symbol">↗</div>
                    <div class="slope-card-label">Positive</div>
                    <div class="slope-card-desc">Line rises left to right. m &gt; 0</div>
                </div>
                <div class="slope-card sc-neg">
                    <div class="slope-card-symbol">↘</div>
                    <div class="slope-card-label">Negative</div>
                    <div class="slope-card-desc">Line falls left to right. m &lt; 0</div>
                </div>
                <div class="slope-card sc-zero">
                    <div class="slope-card-symbol">→</div>
                    <div class="slope-card-label">Zero</div>
                    <div class="slope-card-desc">Horizontal line. m = 0, y = b</div>
                </div>
                <div class="slope-card sc-und">
                    <div class="slope-card-symbol">↕</div>
                    <div class="slope-card-label">Undefined</div>
                    <div class="slope-card-desc">Vertical line. x = c, no slope</div>
                </div>
            </div>

            <h2>Worked Example: Graphing from Standard Form</h2>
            <p>The SAT sometimes gives equations in standard form <strong>Ax + By = C</strong>. The fastest approach is to find both intercepts, then draw the line.</p>

            <div class="example">
                <div class="example-label">SAT-Style Example</div>
                <div class="example-equation">
                    <div><span class="step-label">Equation:</span> 3x + 2y = 12</div>
                    <div style="margin-top:8px"><span class="step-label">Find x-intercept:</span> set y = 0 → 3x = 12 → <span class="highlight">x = 4 → (4, 0)</span></div>
                    <div><span class="step-label">Find y-intercept:</span> set x = 0 → 2y = 12 → <span class="highlight">y = 6 → (0, 6)</span></div>
                    <div style="margin-top:8px"><span class="step-label">Plot &amp; connect:</span> draw line through (4, 0) and (0, 6)</div>
                    <div><span class="step-label">Slope check:</span> m = (6−0)/(0−4) = <span class="highlight">−3/2</span> (negative, confirmed ↘)</div>
                </div>
            </div>

            <h2>Worked Example: Writing an Equation from a Graph</h2>
            <p>The SAT also asks the reverse — given a graph, write the equation. Read slope and intercept directly from the visual.</p>

            <div class="example">
                <div class="example-label">Read Graph → Write Equation</div>
                <div class="example-equation">
                    <div><span class="step-label">Step 1 — intercept:</span> line crosses y-axis at (0, −2) → <span class="highlight">b = −2</span></div>
                    <div><span class="step-label">Step 2 — pick point:</span> line also passes through (3, 4)</div>
                    <div><span class="step-label">Step 3 — slope:</span> m = (4 − (−2)) / (3 − 0) = 6/3 = <span class="highlight">2</span></div>
                    <div style="margin-top:8px"><span class="step-label">Equation:</span> <span class="highlight">y = 2x − 2</span></div>
                </div>
            </div>

            <div class="sat-tip">
                <div class="sat-tip-ico">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </div>
                <div>
                    <div class="sat-tip-label">SAT Strategy</div>
                    <div class="sat-tip-text">
                        On SAT graph questions, always check the <strong>y-intercept first</strong> — it eliminates answer choices instantly.
                        If two answer choices have the same intercept, then compare slopes. You almost never need to calculate
                        both from scratch; one glance at the graph usually settles it.
                    </div>
                </div>
            </div>

            <h2>Special Lines to Recognise Instantly</h2>

            <table class="compare-table" aria-label="Special linear equation types">
                <thead>
                    <tr>
                        <th>Line Type</th>
                        <th>Equation Form</th>
                        <th>What It Looks Like</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Horizontal line</td>
                        <td class="mono">y = 4</td>
                        <td>Flat line, crosses y-axis at 4, slope = 0</td>
                    </tr>
                    <tr>
                        <td>Vertical line</td>
                        <td class="mono">x = −3</td>
                        <td>Straight up/down, crosses x-axis at −3, undefined slope</td>
                    </tr>
                    <tr>
                        <td>Through the origin</td>
                        <td class="mono">y = 5x</td>
                        <td>Passes through (0, 0), b = 0</td>
                    </tr>
                    <tr>
                        <td>Parallel lines</td>
                        <td class="mono">same m, different b</td>
                        <td>Same steepness, never intersect</td>
                    </tr>
                    <tr>
                        <td>Perpendicular lines</td>
                        <td class="mono">m₁ × m₂ = −1</td>
                        <td>Slopes are negative reciprocals, meet at 90°</td>
                    </tr>
                </tbody>
            </table>

            <!-- QUIZ CTA -->
            <a href="<?= htmlspecialchars($quizUrl) ?>" class="quiz-cta-banner" aria-label="Start practice quiz">
                <div class="quiz-cta-ico">
                    <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                </div>
                <div class="quiz-cta-body">
                    <div class="quiz-cta-label">Practice Quiz</div>
                    <div class="quiz-cta-title">Test Your Understanding</div>
                    <div class="quiz-cta-sub"><?= $questionCount ?> questions · Instant feedback · Track your progress</div>
                </div>
                <div class="quiz-cta-btn">
                    <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    Start Quiz
                </div>
            </a>

            <h2>Frequently Asked Questions</h2>

            <div itemscope itemtype="https://schema.org/FAQPage">
                <h3 itemprop="name" itemscope itemtype="https://schema.org/Question">What is the fastest way to graph a line on the SAT?</h3>
                <p itemprop="acceptedAnswer" itemscope itemtype="https://schema.org/Answer">
                    <span itemprop="text">Put the equation in slope-intercept form, plot the y-intercept, then use rise/run to place one more point. Two points are all you need. On multiple-choice questions, you often don't need to draw at all — just identify which answer choice matches the slope sign and y-intercept value you read from the equation.</span>
                </p>

                <h3>How do I know if two lines are perpendicular?</h3>
                <p>Perpendicular lines have slopes that are <strong>negative reciprocals</strong> of each other. If one line has slope 2/3, the perpendicular slope is −3/2. Multiply them together and you always get −1. The SAT tests this frequently in geometry and coordinate plane questions.</p>

                <h3>Can a linear equation have no graph?</h3>
                <p>No — every linear equation in two variables produces a straight line on the coordinate plane. However, a linear <strong>system</strong> (two equations) can have no solution if the lines are parallel and never intersect, or infinitely many solutions if the lines are identical.</p>
            </div>

        </article>

        <!-- PREV/NEXT NAV -->
        <div class="lesson-nav-bottom sr d3">
            <?php if ($lesson['prev']): ?>
            <a href="<?= htmlspecialchars($lesson['prev']['link']) ?>" class="lesson-nav-btn">
                <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                <?= htmlspecialchars($lesson['prev']['title']) ?>
            </a>
            <?php endif; ?>
            <?php if ($lesson['next']): ?>
            <a href="<?= htmlspecialchars($lesson['next']['link']) ?>" class="lesson-nav-btn lesson-nav-btn-next">
                <?= htmlspecialchars($lesson['next']['title']) ?>
                <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>
            <?php endif; ?>
        </div>

    </div><!-- /lesson-main -->

    <!-- ASIDE -->
    <aside class="lesson-aside" aria-label="Lesson tools">

        <div class="aside-card sr d2">
            <div class="aside-card-hd">
                <svg viewBox="0 0 24 24"><path d="M3 13.5L9 7l4 4 8-8"/><path d="M3 21h18"/></svg>
                Your Progress
            </div>
            <div class="aside-card-body">
                <div style="font-size:.8125rem;color:var(--tx3);margin-bottom:.875rem;line-height:1.55">
                    Complete the quiz below to mark this lesson as done and earn XP.
                </div>
                <a href="<?= htmlspecialchars($quizUrl) ?>" class="aside-quiz-btn">
                    <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    Start Practice Quiz
                </a>
            </div>
        </div>

        <div class="aside-card sr d3">
            <div class="aside-card-hd">
                <svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h7"/></svg>
                Algebra Lessons
            </div>
            <div class="lesson-nav">
                <a href="/learn/math/algebra/solving-linear-equations/" class="lesson-nav-item done">
                    <div class="lesson-nav-item-check"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
                    Solving Linear Equations
                </a>
                <a href="/learn/math/algebra/comparing-linear-relationships/" class="lesson-nav-item done">
                    <div class="lesson-nav-item-check"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
                    Comparing Linear Relationships
                </a>
                <a href="/learn/math/algebra/graphing-linear-equations/" class="lesson-nav-item active">
                    <div class="lesson-nav-item-check"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
                    Graphing Linear Equations
                </a>
                <a href="/learn/math/algebra/slope/" class="lesson-nav-item">
                    <div class="lesson-nav-item-check"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
                    Understanding Slope
                </a>
                <a href="/learn/math/algebra/solving-linear-inequalities/" class="lesson-nav-item">
                    <div class="lesson-nav-item-check"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
                    Solving Linear Inequalities
                </a>
                <a href="/learn/math/algebra/function-notation/" class="lesson-nav-item">
                    <div class="lesson-nav-item-check"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
                    Function Notation
                </a>
                <a href="/learn/math/algebra/" style="display:block;padding:8px 10px;font-size:.75rem;font-weight:600;color:var(--tx3);text-decoration:none;text-align:center;margin-top:4px">
                    View all 10 lessons →
                </a>
            </div>
        </div>

        <div class="aside-card sr d4">
            <div class="aside-card-hd">
                <svg viewBox="0 0 24 24"><path d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                AI Tutor
            </div>
            <div class="aside-card-body" style="padding:0">
                <div class="ai-widget">
                    <div class="ai-widget-top">
                        <span class="ai-widget-dot"></span>
                        <span class="ai-widget-label">Ready to help</span>
                    </div>
                    <div class="ai-widget-question">"How do I find the slope of a line just by looking at its graph?"</div>
                    <a href="/ai-tutor/?q=How+do+I+graph+linear+equations+using+slope+and+intercept&subject=math" class="ai-widget-btn">
                        <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        Ask AI Tutor
                    </a>
                </div>
            </div>
        </div>

    </aside>

</div><!-- /lesson-layout -->

<script>
(function () {
    'use strict';

    /* Scroll reveal */
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (n) {
            if (n.isIntersecting) { n.target.classList.add('v'); io.unobserve(n.target); }
        });
    }, { threshold: .05, rootMargin: '0px 0px -24px 0px' });
    document.querySelectorAll('.sr').forEach(function (el) { io.observe(el); });

    /* Notify video ready */
    window.notifyVideoReady = function () {
        var btn = document.getElementById('notifyBtn');
        if (!btn || btn.disabled) return;
        btn.disabled = true;
        btn.innerHTML = '<svg viewBox="0 0 24 24" style="width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round"><polyline points="20 6 9 17 4 12"/></svg> You\'ll be notified';
        btn.style.color       = 'var(--ac2)';
        btn.style.background  = 'rgba(31,226,144,.12)';
        btn.style.borderColor = 'rgba(31,226,144,.25)';
    };

}());
</script>

</body>
</html>