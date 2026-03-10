<?php
/**
 * Sidebar navigation component — included on all dashboard pages.
 */
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$navItems = [
    ['label'=>'Dashboard',       'href'=>'/',               'icon'=>'grid'],
    ['label'=>'Learn',           'href'=>'/learn/',          'icon'=>'book'],
    ['label'=>'Practice Tests',  'href'=>'/practice-tests/', 'icon'=>'clipboard'],
    ['label'=>'AI Tutor',        'href'=>'/ai-tutor/',       'icon'=>'brain'],
    ['label'=>'Study Rooms',     'href'=>'/study-rooms/',    'icon'=>'users'],
    ['label'=>'Schedule',        'href'=>'/schedule/',       'icon'=>'calendar'],
    ['label'=>'Leaderboard',     'href'=>'/leaderboard/',    'icon'=>'trophy'],
    ['label'=>'Achievements',    'href'=>'/achievements/',   'icon'=>'star'],
    ['label'=>'Notebook',        'href'=>'/notebook/',       'icon'=>'edit'],
    ['label'=>'Downloads',       'href'=>'/downloads/',      'icon'=>'download'],
    ['label'=>'Referrals',       'href'=>'/referrals/',      'icon'=>'gift'],
];
$bottomItems = [
    ['label'=>'Profile',  'href'=>'/profile/',          'icon'=>'user'],
    ['label'=>'Settings', 'href'=>'/profile/settings.php', 'icon'=>'settings'],
];

$svgIcons = [
    'grid'      => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
    'book'      => '<path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/>',
    'clipboard' => '<path d="M16 4h2a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/>',
    'brain'     => '<path d="M12 2a4 4 0 014 4v1a3 3 0 012 5.2V14a4 4 0 01-4 4h-1v4h-2v-4H9a4 4 0 01-4-4v-1.8A3 3 0 017 7V6a4 4 0 014-4h1z"/>',
    'users'     => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>',
    'calendar'  => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
    'trophy'    => '<path d="M6 9H4a2 2 0 01-2-2V5h4"/><path d="M18 9h2a2 2 0 002-2V5h-4"/><path d="M4 5h16v4a6 6 0 01-6 6h-4a6 6 0 01-6-6V5z"/><path d="M12 15v4"/><path d="M8 19h8"/>',
    'star'      => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
    'edit'      => '<path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>',
    'download'  => '<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
    'gift'      => '<polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 110-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z"/>',
    'user'      => '<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    'settings'  => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>',
];
?>

<style>
.sidebar{position:fixed;left:0;top:0;width:260px;height:100vh;background:#fff;border-right:1px solid rgba(20,50,48,.06);display:flex;flex-direction:column;z-index:90;transition:transform .3s cubic-bezier(.4,0,.2,1)}
.sidebar-brand{padding:20px 24px;display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(20,50,48,.06)}
.sidebar-brand .logo{font-size:1.2rem;font-weight:700;color:#143230;letter-spacing:-.02em}
.sidebar-brand .logo span{color:#1fe290}
.sidebar-nav{flex:1;overflow-y:auto;padding:12px 12px}
.sidebar-nav a{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;font-size:.85rem;color:#1a1a2e;opacity:.6;transition:all .15s;text-decoration:none;margin-bottom:2px}
.sidebar-nav a:hover{opacity:1;background:rgba(20,50,48,.04)}
.sidebar-nav a.active{opacity:1;background:rgba(31,226,144,.1);color:#143230;font-weight:600}
.sidebar-nav a svg{width:18px;height:18px;flex-shrink:0}
.sidebar-bottom{padding:12px;border-top:1px solid rgba(20,50,48,.06)}
.sidebar-bottom a{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;font-size:.85rem;color:#1a1a2e;opacity:.5;transition:all .15s;text-decoration:none;margin-bottom:2px}
.sidebar-bottom a:hover{opacity:1;background:rgba(20,50,48,.04)}
.sidebar-bottom a.active{opacity:1;background:rgba(31,226,144,.1);color:#143230;font-weight:600}
.sidebar-bottom a svg{width:18px;height:18px;flex-shrink:0}

.sidebar-toggle{display:none;position:fixed;top:12px;left:12px;z-index:100;width:36px;height:36px;border-radius:8px;background:#fff;border:1px solid rgba(20,50,48,.1);cursor:pointer;align-items:center;justify-content:center}
.sidebar-overlay{display:none}

@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}
  .sidebar.open{transform:translateX(0)}
  .sidebar-toggle{display:flex}
  .sidebar-overlay{display:block;position:fixed;inset:0;background:rgba(0,0,0,.3);opacity:0;pointer-events:none;z-index:80;transition:opacity .3s}
  .sidebar-overlay.show{opacity:1;pointer-events:all}
  .main-content{margin-left:0!important}
}
</style>

<button class="sidebar-toggle" onclick="document.querySelector('.sidebar').classList.toggle('open');document.querySelector('.sidebar-overlay').classList.toggle('show')">
  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
</button>

<div class="sidebar-overlay" onclick="document.querySelector('.sidebar').classList.remove('open');this.classList.remove('show')"></div>

<nav class="sidebar">
  <div class="sidebar-brand">
    <span class="logo">Avid<span>Mock</span></span>
  </div>
  <div class="sidebar-nav">
    <?php foreach ($navItems as $item):
      $isActive = ($currentPath === $item['href'] || ($item['href'] !== '/' && str_starts_with($currentPath, $item['href'])));
      $iconSvg = $svgIcons[$item['icon']] ?? '';
    ?>
    <a href="<?= $item['href'] ?>"<?= $isActive ? ' class="active"' : '' ?>>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $iconSvg ?></svg>
      <?= $item['label'] ?>
    </a>
    <?php endforeach; ?>
  </div>
  <div class="sidebar-bottom">
    <?php foreach ($bottomItems as $item):
      $isActive = str_starts_with($currentPath, $item['href']);
      $iconSvg = $svgIcons[$item['icon']] ?? '';
    ?>
    <a href="<?= $item['href'] ?>"<?= $isActive ? ' class="active"' : '' ?>>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $iconSvg ?></svg>
      <?= $item['label'] ?>
    </a>
    <?php endforeach; ?>
  </div>
</nav>
