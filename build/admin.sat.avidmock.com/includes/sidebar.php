<?php
/**
 * includes/sidebar.php
 *
 * Usage — at the top of every admin page, after auth + DB:
 *
 *   $admin       = currentAdmin();          // required — used for avatar/name
 *   $activePage  = 'quizzes';               // controls which link is highlighted
 *   $draftCount  = 3;                       // optional — shows badge on Quizzes link
 *
 *   require_once __DIR__ . '/../includes/sidebar.php';
 *
 * Recognised $activePage values:
 *   dashboard | analytics | quizzes | questions | practice-tests |
 *   students | sessions | notifications | settings |
 *   revenue | subscriptions | growth
 */

// Safe defaults
if (!isset($activePage))  $activePage  = '';
if (!isset($draftCount))  $draftCount  = 0;
if (!isset($admin))       $admin       = ['name' => 'Admin', 'role' => 'admin'];

function _sbLink(string $href, string $page, string $active, string $icon, string $label, int $badge = 0, string $badgeType = 'default'): string {
    $cls  = 'sb-link' . ($active === $page ? ' active' : '');
    $bdg  = '';
    if ($badge > 0) {
        $bCls = $badgeType === 'warn' ? 'sb-badge warn' : 'sb-badge';
        $bdg  = '<span class="' . $bCls . '">' . $badge . '</span>';
    }
    return '<a href="' . $href . '" class="' . $cls . '">' . $icon . htmlspecialchars($label) . $bdg . '</a>';
}

// ── SVG icon snippets ─────────────────────────────────────────────────────
$ico = [
    'dashboard' => '<svg class="sb-ico" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
    'analytics' => '<svg class="sb-ico" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>',
    'quizzes'   => '<svg class="sb-ico" viewBox="0 0 24 24"><path d="M9 2H4a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9"/><path d="M13 2l5 5-8 8H5v-5l8-8z"/></svg>',
    'questions' => '<svg class="sb-ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3" stroke-linecap="round"/><line x1="12" y1="17" x2="12.01" y2="17" stroke-width="2.5"/></svg>',
    'tests'     => '<svg class="sb-ico" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
    'students'  => '<svg class="sb-ico" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>',
    'sessions'  => '<svg class="sb-ico" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
    'notifs'    => '<svg class="sb-ico" viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>',
    'settings'  => '<svg class="sb-ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>',
    'ai-gen'    => '<svg class="sb-ico" viewBox="0 0 24 24"><path d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48l2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48l2.83-2.83"/></svg>',
    'revenue'   => '<svg class="sb-ico" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>',
    'growth'    => '<svg class="sb-ico" viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>',
    'subs'      => '<svg class="sb-ico" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
];
?>
<!-- Mobile overlay -->
<div class="sb-overlay" id="sbOverlay" onclick="closeSidebar()"></div>

<!-- Sidebar -->
<aside class="sb" id="sidebar">

    <a href="/index.php" class="sb-logo">

        <div>
            <div class="sb-logo-name">Avidmock SAT</div>
        </div>
    </a>

    <nav class="sb-nav">
        <div class="sb-group">Overview</div>
        <?= _sbLink('/index.php',            'dashboard',      $activePage, $ico['dashboard'], 'Dashboard') ?>
        <?= _sbLink('/analytics/index.php',  'analytics',      $activePage, $ico['analytics'], 'Analytics') ?>

        <div class="sb-group">Content</div>
        <?= _sbLink('/quizzes/index.php',        'quizzes',       $activePage, $ico['quizzes'],   'Quizzes',       $draftCount, 'warn') ?>
        <?= _sbLink('/quizzes/ai-generate.php',  'ai-generate',   $activePage, $ico['ai-gen'],    'AI Generator') ?>
        <?= _sbLink('/practice-tests/index.php', 'practice-tests',$activePage, $ico['tests'],     'Practice Tests') ?>

        <div class="sb-group">Revenue</div>
        <?= _sbLink('/revenue/index.php',         'revenue',       $activePage, $ico['revenue'],   'Revenue Dashboard') ?>
        <?= _sbLink('/revenue/subscriptions.php',  'subscriptions', $activePage, $ico['subs'],      'Subscriptions') ?>
        <?= _sbLink('/revenue/growth.php',         'growth',        $activePage, $ico['growth'],    'Growth') ?>

        <div class="sb-group">Students</div>
        <?= _sbLink('/students/index.php',       'students',      $activePage, $ico['students'],  'All Students') ?>

        <div class="sb-group">Platform</div>
        <?= _sbLink('/sessions/index.php',       'sessions',      $activePage, $ico['sessions'],  'Sessions') ?>
    </nav>

    <div class="sb-foot">
        <div class="sb-ava"><?= strtoupper(substr($admin['name'], 0, 1)) ?></div>
        <div>
            <div class="sb-name"><?= htmlspecialchars($admin['name']) ?></div>
            <div class="sb-role"><?= htmlspecialchars($admin['role']) ?></div>
        </div>
        <a href="/auth/logout.php" class="sb-out" title="Sign out">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </a>
    </div>

</aside>

<script>
/* Sidebar open/close — shared by all pages */
function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('sbOverlay').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sbOverlay').classList.remove('show');
    document.body.style.overflow = '';
}
window.addEventListener('resize', () => { if (window.innerWidth > 768) closeSidebar(); });
</script>