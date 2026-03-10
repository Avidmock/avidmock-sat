<?php
/**
 * lessons/edit.php
 * Edit lesson content, video, and metadata.
 */
require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

$admin   = currentAdmin();
$db      = Database::connect();
$id      = (int)($_GET['id'] ?? 0);
$error   = '';
$success = '';

if (!$id) { header('Location: /lessons/index.php'); exit; }

if (isset($_GET['saved'])) $success = 'Lesson created successfully!';

// ── Fetch lesson ──────────────────────────────────────────────────────────
try {
    $stmt = $db->prepare("SELECT * FROM lessons WHERE id = ?");
    $stmt->execute([$id]);
    $lesson = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $lesson = null; }

if (!$lesson) { header('Location: /lessons/index.php'); exit; }

// ── Handle POST ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = trim($_POST['title'] ?? '');
    $slug     = trim($_POST['slug'] ?? '');
    $section  = $_POST['section'] ?? 'math';
    $module   = trim($_POST['module'] ?? '');
    $topic    = trim($_POST['topic'] ?? '');
    $content  = $_POST['content_html'] ?? '';
    $videoUrl = trim($_POST['video_url'] ?? '');
    $status   = $_POST['status'] ?? 'draft';

    if ($title === '') {
        $error = 'Title is required.';
    } else {
        try {
            $db->prepare(
                "UPDATE lessons SET title=:t, slug=:s, section=:sec, module=:m, topic=:top, content_html=:c, video_url=:v, status=:st WHERE id=:id"
            )->execute([
                ':t'   => $title, ':s' => $slug, ':sec' => $section, ':m' => $module,
                ':top' => $topic, ':c' => $content, ':v' => $videoUrl, ':st' => $status, ':id' => $id,
            ]);
            $success = 'Lesson updated!';
            $stmt = $db->prepare("SELECT * FROM lessons WHERE id = ?");
            $stmt->execute([$id]);
            $lesson = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { $error = 'Error: ' . $e->getMessage(); }
    }
}

// ── Quiz count for this lesson ────────────────────────────────────────────
try {
    $quizCount = (int) $db->prepare("SELECT COUNT(*) FROM sat_quizzes WHERE lesson_slug = ?")->execute([$lesson['slug']]) ?
        (int) $db->query("SELECT FOUND_ROWS()")->fetchColumn() : 0;
    $qStmt = $db->prepare("SELECT COUNT(*) FROM sat_quizzes WHERE lesson_slug = ?");
    $qStmt->execute([$lesson['slug']]);
    $quizCount = (int) $qStmt->fetchColumn();
} catch (Throwable $e) { $quizCount = 0; }

try { $draftCount = (int) $db->query("SELECT COUNT(*) FROM sat_quizzes WHERE status='draft'")->fetchColumn(); } catch (Throwable $e) { $draftCount = 0; }

$pageTitle  = 'Edit Lesson — Avidmock Admin';
$activePage = 'lessons';
$extraHead  = <<<'CSS'
<style>
.main { margin-left: var(--sb-w); margin-top: var(--top-h); padding: 32px 28px; min-height: calc(100vh - var(--top-h)); }
.ph { margin-bottom: 24px; }
.ph-eyebrow { display: flex; align-items: center; gap: 6px; font-size: .5rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 5px; }
.ph-dot { width: 4px; height: 4px; border-radius: 50%; background: var(--ac); }
.ph-title { font-family: var(--fh); font-size: 1.875rem; font-weight: 900; color: var(--tx); letter-spacing: -.035em; }

.layout { display: grid; grid-template-columns: 1fr 260px; gap: 20px; max-width: 1000px; }
.form-card { background: var(--sf); border: 1px solid var(--bd); border-radius: 14px; padding: 28px; }
.side-card { background: var(--sf); border: 1px solid var(--bd); border-radius: 14px; padding: 20px; height: fit-content; }

.form-group { margin-bottom: 20px; }
.form-label { display: block; font-size: .75rem; font-weight: 700; color: var(--tx2); margin-bottom: 6px; text-transform: uppercase; letter-spacing: .4px; }
.form-input, .form-textarea, .form-select { width: 100%; padding: 10px 14px; background: var(--ink); border: 1.5px solid var(--bd); border-radius: 9px; font-family: var(--ff); font-size: .875rem; color: var(--tx); outline: none; }
.form-textarea { min-height: 250px; resize: vertical; font-family: var(--fm); font-size: .8125rem; }
.form-input:focus, .form-textarea:focus, .form-select:focus { border-color: var(--ac); box-shadow: 0 0 0 3px rgba(31,226,144,.1); }
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

.alert { padding: 12px 16px; border-radius: 9px; font-size: .8125rem; font-weight: 600; margin-bottom: 16px; }
.alert-error { background: rgba(239,68,68,.1); color: var(--err); }
.alert-success { background: rgba(31,226,144,.1); color: var(--ac); }

.side-stat { margin-bottom: 16px; }
.side-stat-label { font-size: .625rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 3px; }
.side-stat-value { font-family: var(--fm); font-size: 1.125rem; font-weight: 800; color: var(--tx); }
.side-actions { display: flex; flex-direction: column; gap: 8px; margin-top: 20px; }

.form-actions { display: flex; gap: 10px; margin-top: 24px; }

@media (max-width: 768px) { .main { margin-left: 0; padding: 16px; } .layout, .row-2 { grid-template-columns: 1fr; } }
</style>
CSS;

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<header class="topbar">
    <button class="topbar-ham" onclick="openSidebar()" aria-label="Menu"><span></span><span></span><span></span></button>
    <div class="topbar-title">Lessons <span>/ Edit</span></div>
    <div class="topbar-spacer"></div>
    <a href="/lessons/index.php" class="btn btn-ghost">Back to Lessons</a>
</header>

<main class="main">
    <div class="ph reveal d1">
        <div class="ph-eyebrow"><span class="ph-dot"></span>Content</div>
        <h1 class="ph-title"><?= e($lesson['title']) ?></h1>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

    <div class="layout reveal d2">
        <div class="form-card">
            <form method="POST">
                <div class="row-2">
                    <div class="form-group">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-input" required value="<?= e($lesson['title']) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Slug</label>
                        <input type="text" name="slug" class="form-input" value="<?= e($lesson['slug']) ?>">
                    </div>
                </div>
                <div class="row-2">
                    <div class="form-group">
                        <label class="form-label">Section</label>
                        <select name="section" class="form-select">
                            <option value="math" <?= ($lesson['section'] ?? '') === 'math' ? 'selected' : '' ?>>Math</option>
                            <option value="reading-writing" <?= ($lesson['section'] ?? '') === 'reading-writing' ? 'selected' : '' ?>>Reading & Writing</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="draft" <?= ($lesson['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="published" <?= ($lesson['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                            <option value="archived" <?= ($lesson['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archived</option>
                        </select>
                    </div>
                </div>
                <div class="row-2">
                    <div class="form-group">
                        <label class="form-label">Module</label>
                        <input type="text" name="module" class="form-input" value="<?= e($lesson['module'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Topic</label>
                        <input type="text" name="topic" class="form-input" value="<?= e($lesson['topic'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Video URL</label>
                    <input type="url" name="video_url" class="form-input" value="<?= e($lesson['video_url'] ?? '') ?>" placeholder="https://youtube.com/watch?v=...">
                </div>
                <div class="form-group">
                    <label class="form-label">Content (HTML)</label>
                    <textarea name="content_html" class="form-textarea" rows="16"><?= e($lesson['content_html'] ?? '') ?></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="/lessons/index.php" class="btn btn-ghost">Cancel</a>
                </div>
            </form>
        </div>

        <div class="side-card">
            <h3 style="font-family:var(--fh);font-size:1rem;font-weight:900;color:var(--tx);margin-bottom:16px">Details</h3>
            <div class="side-stat">
                <div class="side-stat-label">Lesson ID</div>
                <div class="side-stat-value">#<?= $id ?></div>
            </div>
            <div class="side-stat">
                <div class="side-stat-label">Linked Quizzes</div>
                <div class="side-stat-value"><?= $quizCount ?></div>
            </div>
            <div class="side-stat">
                <div class="side-stat-label">Created</div>
                <div class="side-stat-value" style="font-size:.8125rem"><?= date('M j, Y', strtotime($lesson['created_at'])) ?></div>
            </div>
            <div class="side-stat">
                <div class="side-stat-label">Updated</div>
                <div class="side-stat-value" style="font-size:.8125rem"><?= date('M j, Y', strtotime($lesson['updated_at'])) ?></div>
            </div>
            <div class="side-actions">
                <a href="/quizzes/create.php?lesson=<?= urlencode($lesson['slug']) ?>" class="btn btn-sm btn-primary" style="width:100%;text-align:center">Attach Quiz</a>
            </div>
        </div>
    </div>
</main>
</body>
</html>
