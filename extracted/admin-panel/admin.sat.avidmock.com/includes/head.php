<?php
/**
 * includes/head.php
 *
 * Outputs the full <head> block. Must be called after PHP variables are set:
 *
 *   $pageTitle   = 'Quizzes';          // required — shown in <title>
 *   $extraHead   = '';                  // optional — inject <style> or <link> tags
 *   $withKatex   = false;              // optional — set true to load KaTeX
 *
 * Example usage:
 *   $pageTitle = 'Quizzes — Avidmock Admin';
 *   require_once __DIR__ . '/../includes/head.php';
 */

if (!isset($pageTitle))  $pageTitle  = 'Avidmock Admin';
if (!isset($extraHead))  $extraHead  = '';
if (!isset($withKatex))  $withKatex  = false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($pageTitle) ?></title>
<meta name="robots" content="noindex,nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700;9..40,800&family=Fraunces:ital,opsz,wght@0,9..144,900;1,9..144,300&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/includes/admin.css">
<?php if ($withKatex): ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css">
<?php endif; ?>
<?= $extraHead ?>
</head>
<body>