<?php
/**
 * Top bar component — user info, streak, notifications.
 */
$tbUser = $_SESSION['user_name'] ?? 'Student';
$tbStreak = 12;
$tbXp = 875;
?>
<style>
.topbar{position:fixed;top:0;right:0;left:260px;height:56px;background:rgba(247,250,249,.92);backdrop-filter:blur(12px);border-bottom:1px solid rgba(20,50,48,.06);display:flex;align-items:center;justify-content:flex-end;padding:0 24px;gap:16px;z-index:80}
.topbar .streak-pill{display:flex;align-items:center;gap:5px;padding:6px 12px;background:rgba(245,166,35,.08);border-radius:20px;font-size:.75rem;font-weight:600;color:#f5a623}
.topbar .xp-pill{display:flex;align-items:center;gap:5px;padding:6px 12px;background:rgba(31,226,144,.08);border-radius:20px;font-size:.75rem;font-weight:600;color:#17c87a}
.topbar .user-avatar{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#1fe290,#17c87a);display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:600;color:#143230;cursor:pointer}
@media(max-width:768px){.topbar{left:0;padding:0 16px 0 52px}}
</style>
<div class="topbar">
  <span class="streak-pill"><?= $tbStreak ?> day streak</span>
  <span class="xp-pill"><?= number_format($tbXp) ?> XP</span>
  <a href="/profile/" class="user-avatar"><?= strtoupper(substr($tbUser, 0, 1)) ?></a>
</div>
