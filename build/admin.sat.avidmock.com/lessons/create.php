<?php
/**
 * lessons/create.php
 * Create a new lesson with content editor.
 */
require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

$admin   = currentAdmin();
$db      = Database::connect();
$error   = '';
$success = '';

// ── Detect/create lessons table ───────────────────────────────────────────
try {
    $db->query("SELECT 1 FROM lessons LIMIT 1");
} catch (Throwable $e) {
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS lessons (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            section ENUM('math','reading-writing') DEFAULT 'math',
            module VARCHAR(100) DEFAULT '',
            topic VARCHAR(100) DEFAULT '',
            content_html LONGTEXT,
            video_url VARCHAR(500) DEFAULT '',
            video_duration INT DEFAULT 0,
            status ENUM('draft','published','archived') DEFAULT 'draft',
            `order` INT DEFAULT 0,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e2) {}
}

// ── Handle POST ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title   = trim($_POST['title'] ?? '');
    $slug    = trim($_POST['slug'] ?? '');
    $section = $_POST['section'] ?? 'math';
    $module  = trim($_POST['module'] ?? '');
    $topic   = trim($_POST['topic'] ?? '');
    $content = $_POST['content_html'] ?? '';
    $videoUrl = trim($_POST['video_url'] ?? '');
    $status  = $_POST['status'] ?? 'draft';

    if ($title === '') {
        $error = 'Title is required.';
    } else {
        if ($slug === '') {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title));
            $slug = trim($slug, '-');
        }

        try {
            $stmt = $db->prepare(
                "INSERT INTO lessons (title, slug, section, module, topic, content_html, video_url, status, created_by)
                 VALUES (:title, :slug, :section, :module, :topic, :content, :video, :status, :admin)"
            );
            $stmt->execute([
                ':title'   => $title,
                ':slug'    => $slug,
                ':section' => $section,
                ':module'  => $module,
                ':topic'   => $topic,
                ':content' => $content,
                ':video'   => $videoUrl,
                ':status'  => $status,
                ':admin'   => $admin['id'] ?? null,
            ]);
            $newId = $db->lastInsertId();
            header("Location: /lessons/edit.php?id={$newId}&saved=1");
            exit;
        } catch (Throwable $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

try { $draftCount = (int) $db->query("SELECT COUNT(*) FROM sat_quizzes WHERE status='draft'")->fetchColumn(); } catch (Throwable $e) { $draftCount = 0; }

$pageTitle  = 'Create Lesson — Avidmock Admin';
$activePage = 'lessons';
$extraHead  = <<<'CSS'
<style>
.main { margin-left: var(--sb-w); margin-top: var(--top-h); padding: 32px 28px; min-height: calc(100vh - var(--top-h)); }
.ph { margin-bottom: 24px; }
.ph-eyebrow { display: flex; align-items: center; gap: 6px; font-size: .5rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 5px; }
.ph-dot { width: 4px; height: 4px; border-radius: 50%; background: var(--ac); }
.ph-title { font-family: var(--fh); font-size: 1.875rem; font-weight: 900; color: var(--tx); letter-spacing: -.035em; }

.form-card { background: var(--sf); border: 1px solid var(--bd); border-radius: 14px; padding: 28px; max-width: 780px; }
.form-group { margin-bottom: 20px; }
.form-label { display: block; font-size: .75rem; font-weight: 700; color: var(--tx2); margin-bottom: 6px; text-transform: uppercase; letter-spacing: .4px; }
.form-input, .form-textarea, .form-select { width: 100%; padding: 10px 14px; background: var(--ink); border: 1.5px solid var(--bd); border-radius: 9px; font-family: var(--ff); font-size: .875rem; color: var(--tx); outline: none; }
.form-textarea { min-height: 200px; resize: vertical; font-family: var(--fm); }
.form-input:focus, .form-textarea:focus, .form-select:focus { border-color: var(--ac); box-shadow: 0 0 0 3px rgba(31,226,144,.1); }
.form-hint { font-size: .6875rem; color: var(--tx3); margin-top: 4px; }

.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; }

.alert { padding: 12px 16px; border-radius: 9px; font-size: .8125rem; font-weight: 600; margin-bottom: 16px; }
.alert-error { background: rgba(239,68,68,.1); color: var(--err); border: 1px solid rgba(239,68,68,.2); }

.form-actions { display: flex; gap: 10px; margin-top: 24px; }

@media (max-width: 768px) { .main { margin-left: 0; padding: 16px; } .row-2, .row-3 { grid-template-columns: 1fr; } }
</style>
CSS;

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<header class="topbar">
    <button class="topbar-ham" onclick="openSidebar()" aria-label="Menu"><span></span><span></span><span></span></button>
    <div class="topbar-title">Lessons <span>/ Create</span></div>
    <div class="topbar-spacer"></div>
    <a href="/lessons/index.php" class="btn btn-ghost">Back to Lessons</a>
</header>

<main class="main">
    <div class="ph reveal d1">
        <div class="ph-eyebrow"><span class="ph-dot"></span>Content</div>
        <h1 class="ph-title">Create Lesson</h1>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <div class="form-card reveal d2">
        <form method="POST">
            <div class="row-2">
                <div class="form-group">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-input" required value="<?= e($_POST['title'] ?? '') ?>" placeholder="e.g. Solving Linear Equations">
                </div>
                <div class="form-group">
                    <label class="form-label">Slug</label>
                    <input type="text" name="slug" class="form-input" value="<?= e($_POST['slug'] ?? '') ?>" placeholder="auto-generated from title">
                    <div class="form-hint">Leave blank to auto-generate</div>
                </div>
            </div>

            <div class="row-3">
                <div class="form-group">
                    <label class="form-label">Section</label>
                    <select name="section" class="form-select">
                        <option value="math">Math</option>
                        <option value="reading-writing">Reading & Writing</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Module</label>
                    <input type="text" name="module" class="form-input" value="<?= e($_POST['module'] ?? '') ?>" placeholder="e.g. Algebra Foundations">
                </div>
                <div class="form-group">
                    <label class="form-label">Topic</label>
                    <input type="text" name="topic" class="form-input" value="<?= e($_POST['topic'] ?? '') ?>" placeholder="e.g. Linear Equations">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Video URL (optional)</label>
                <input type="url" name="video_url" class="form-input" value="<?= e($_POST['video_url'] ?? '') ?>" placeholder="https://youtube.com/watch?v=...">
            </div>

            <div class="form-group">
                <label class="form-label">Content (HTML)</label>
                <textarea name="content_html" class="form-textarea" rows="12" placeholder="<h2>Introduction</h2><p>...</p>"><?= e($_POST['content_html'] ?? '') ?></textarea>
                <div class="form-hint">Supports HTML. Use headings, paragraphs, lists, and LaTeX math notation.</div>
            </div>

            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-select" style="max-width:200px">
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create Lesson</button>
                <a href="/lessons/index.php" class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    </div>
</main>
</body>
</html>
