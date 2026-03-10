<?php
/**
 * lessons/index.php
 * Manage lesson content — video lessons, reading material, practice sets.
 */
require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

$admin = currentAdmin();
$db    = Database::connect();

// ── Check if lessons table exists ─────────────────────────────────────────
$hasLessons = false;
try {
    $db->query("SELECT 1 FROM lessons LIMIT 1");
    $hasLessons = true;
} catch (Throwable $e) {}

// ── Params ────────────────────────────────────────────────────────────────
$search  = trim($_GET['q'] ?? '');
$section = $_GET['section'] ?? 'all';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$lessons = [];
$total   = 0;

if ($hasLessons) {
    // Detect columns
    $lCols = $db->query("SHOW COLUMNS FROM lessons")->fetchAll(PDO::FETCH_COLUMN);
    $hasSection  = in_array('section', $lCols);
    $hasStatus   = in_array('status', $lCols);
    $hasModule   = in_array('module', $lCols);
    $hasVideoUrl = in_array('video_url', $lCols);

    $where  = [];
    $params = [];
    if ($search !== '') {
        $where[]      = '(title LIKE :s OR slug LIKE :s2)';
        $params[':s'] = "%{$search}%";
        $params[':s2'] = "%{$search}%";
    }
    if ($section !== 'all' && $hasSection) {
        $where[]              = 'section = :section';
        $params[':section']   = $section;
    }
    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    try {
        $countStmt = $db->prepare("SELECT COUNT(*) FROM lessons {$whereSQL}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
    } catch (Throwable $e) { $total = 0; }

    $pages = max(1, (int) ceil($total / $perPage));

    $sectionCol = $hasSection ? 'section,' : '';
    $statusCol  = $hasStatus  ? 'status,'  : '';
    $moduleCol  = $hasModule  ? 'module,'  : '';

    try {
        $stmt = $db->prepare(
            "SELECT id, title, slug, {$sectionCol} {$statusCol} {$moduleCol} created_at, updated_at
             FROM lessons {$whereSQL}
             ORDER BY updated_at DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { $lessons = []; }
} else {
    $pages = 1;
}

try { $draftCount = (int) $db->query("SELECT COUNT(*) FROM sat_quizzes WHERE status='draft'")->fetchColumn(); } catch (Throwable $e) { $draftCount = 0; }

$pageTitle  = 'Lessons — Avidmock Admin';
$activePage = 'lessons';
$extraHead  = <<<'CSS'
<style>
.main { margin-left: var(--sb-w); margin-top: var(--top-h); padding: 32px 28px; min-height: calc(100vh - var(--top-h)); }
.ph { margin-bottom: 24px; }
.ph-eyebrow { display: flex; align-items: center; gap: 6px; font-size: .5rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 5px; }
.ph-dot { width: 4px; height: 4px; border-radius: 50%; background: var(--ac); }
.ph-title { font-family: var(--fh); font-size: 1.875rem; font-weight: 900; color: var(--tx); letter-spacing: -.035em; line-height: 1.1; }
.ph-sub { font-size: .875rem; color: var(--tx2); margin-top: 4px; }

.filter-bar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 16px; }
.search-wrap { position: relative; flex: 1; min-width: 200px; max-width: 320px; }
.search-wrap svg { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); width: 14px; height: 14px; stroke: var(--tx3); fill: none; stroke-width: 1.8; stroke-linecap: round; pointer-events: none; }
.search-input { width: 100%; padding: 8px 12px 8px 34px; background: var(--sf); border: 1.5px solid var(--bd); border-radius: 9px; font-family: var(--ff); font-size: .8125rem; color: var(--tx); outline: none; }
.search-input:focus { border-color: var(--ac); box-shadow: 0 0 0 3px rgba(31,226,144,.1); }
.filter-select { padding: 7px 12px; background: var(--sf); border: 1.5px solid var(--bd); border-radius: 9px; font-family: var(--ff); font-size: .8125rem; color: var(--tx); outline: none; cursor: pointer; }

.lessons-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; margin-top: 20px; }
.lesson-card { background: var(--sf); border: 1px solid var(--bd); border-radius: 14px; padding: 20px; transition: all .18s; }
.lesson-card:hover { border-color: var(--ac); transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.08); }
.lesson-section { display: inline-block; padding: 2px 8px; border-radius: 5px; font-size: .5625rem; font-weight: 700; text-transform: uppercase; margin-bottom: 8px; }
.lesson-section.math { background: var(--blue2); color: var(--blue); }
.lesson-section.rw { background: var(--purple2); color: var(--purple); }
.lesson-title { font-family: var(--fh); font-size: 1.05rem; font-weight: 900; color: var(--tx); letter-spacing: -.02em; margin-bottom: 4px; }
.lesson-slug { font-size: .625rem; font-family: var(--fm); color: var(--tx3); margin-bottom: 12px; }
.lesson-meta { display: flex; gap: 16px; font-size: .75rem; color: var(--tx3); }
.lesson-actions { display: flex; gap: 8px; margin-top: 14px; }
.status-dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; margin-right: 4px; }
.status-dot.published { background: var(--ac); }
.status-dot.draft { background: var(--warn); }

.empty { text-align: center; padding: 80px 20px; }
.empty-title { font-family: var(--fh); font-size: 1.25rem; font-weight: 900; color: var(--tx); margin-bottom: 8px; }
.empty-sub { font-size: .875rem; color: var(--tx3); margin-bottom: 24px; }

.pagination { display: flex; align-items: center; justify-content: center; gap: 4px; margin-top: 24px; }
.pag-btn { width: 30px; height: 30px; border-radius: 7px; display: flex; align-items: center; justify-content: center; font-size: .75rem; font-weight: 700; text-decoration: none; color: var(--tx3); background: var(--sf); border: 1px solid var(--bd); }
.pag-btn:hover { background: var(--sf2); color: var(--tx); }
.pag-btn.active { background: var(--ac3); color: var(--ac); }
.pag-btn.disabled { opacity: .35; pointer-events: none; }

@media (max-width: 768px) { .main { margin-left: 0; padding: 16px; } .lessons-grid { grid-template-columns: 1fr; } }
</style>
CSS;

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<header class="topbar">
    <button class="topbar-ham" onclick="openSidebar()" aria-label="Menu"><span></span><span></span><span></span></button>
    <div class="topbar-title">Lessons</div>
    <div class="topbar-spacer"></div>
    <a href="/lessons/create.php" class="btn btn-primary">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Lesson
    </a>
</header>

<main class="main">
    <div class="ph reveal d1">
        <div class="ph-eyebrow"><span class="ph-dot"></span>Content</div>
        <h1 class="ph-title">Lessons</h1>
        <p class="ph-sub"><?= number_format($total) ?> lesson<?= $total !== 1 ? 's' : '' ?> in your library</p>

        <div class="filter-bar">
            <form method="GET" style="display:contents">
                <select name="section" class="filter-select" onchange="this.form.submit()">
                    <option value="all">All Sections</option>
                    <option value="math" <?= $section === 'math' ? 'selected' : '' ?>>Math</option>
                    <option value="reading-writing" <?= $section === 'reading-writing' ? 'selected' : '' ?>>Reading & Writing</option>
                </select>
                <div class="search-wrap">
                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input class="search-input" type="search" name="q" value="<?= e($search) ?>" placeholder="Search lessons…" onkeydown="if(event.key==='Enter')this.closest('form').submit()">
                </div>
            </form>
        </div>
    </div>

    <?php if (!$hasLessons): ?>
    <div class="empty reveal d2">
        <div class="empty-title">Lessons table not found</div>
        <div class="empty-sub">Run the database migration to create the lessons table, or create your first lesson.</div>
        <a href="/lessons/create.php" class="btn btn-primary">Create First Lesson</a>
    </div>
    <?php elseif (empty($lessons)): ?>
    <div class="empty reveal d2">
        <div class="empty-title">No lessons yet</div>
        <div class="empty-sub"><?= $search ? 'Try a different search.' : 'Start building your lesson library.' ?></div>
        <a href="/lessons/create.php" class="btn btn-primary">Create Lesson</a>
    </div>
    <?php else: ?>
    <div class="lessons-grid reveal d2">
        <?php foreach ($lessons as $l):
            $sec    = $l['section'] ?? '';
            $secCls = $sec === 'math' ? 'math' : 'rw';
            $secLbl = $sec === 'math' ? 'Math' : 'R&W';
            $st     = $l['status'] ?? 'draft';
        ?>
        <div class="lesson-card">
            <?php if (isset($l['section'])): ?>
            <span class="lesson-section <?= $secCls ?>"><?= $secLbl ?></span>
            <?php endif; ?>
            <div class="lesson-title"><?= e($l['title'] ?? '') ?></div>
            <div class="lesson-slug"><?= e($l['slug'] ?? '') ?></div>
            <div class="lesson-meta">
                <?php if (isset($l['status'])): ?>
                <span><span class="status-dot <?= $st ?>"></span><?= ucfirst($st) ?></span>
                <?php endif; ?>
                <?php if (isset($l['module'])): ?>
                <span><?= e($l['module']) ?></span>
                <?php endif; ?>
                <span><?= date('M j, Y', strtotime($l['updated_at'] ?? $l['created_at'])) ?></span>
            </div>
            <div class="lesson-actions">
                <a href="/lessons/edit.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                <a href="/lessons/edit.php?id=<?= $l['id'] ?>&tab=content" class="btn btn-sm btn-ghost">Content</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($pages > 1): ?>
    <div class="pagination">
        <a href="?section=<?= urlencode($section) ?>&q=<?= urlencode($search) ?>&page=<?= max(1, $page - 1) ?>" class="pag-btn <?= $page <= 1 ? 'disabled' : '' ?>">‹</a>
        <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
        <a href="?section=<?= urlencode($section) ?>&q=<?= urlencode($search) ?>&page=<?= $i ?>" class="pag-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="?section=<?= urlencode($section) ?>&q=<?= urlencode($search) ?>&page=<?= min($pages, $page + 1) ?>" class="pag-btn <?= $page >= $pages ? 'disabled' : '' ?>">›</a>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>
