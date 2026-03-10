<?php
/**
 * questions/create.php
 * Create a standalone question (optionally assign to a quiz).
 */
require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

$admin = currentAdmin();
$db    = Database::connect();

$error   = '';
$success = '';

// ── Fetch quizzes for dropdown ────────────────────────────────────────────
try {
    $quizzes = $db->query("SELECT id, title FROM sat_quizzes ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $quizzes = []; }

// ── Detect columns ────────────────────────────────────────────────────────
try {
    $qCols = $db->query("SHOW COLUMNS FROM sat_quiz_questions")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) { $qCols = []; }
$hasDomain     = in_array('domain', $qCols);
$hasDifficulty = in_array('difficulty', $qCols);
$hasSkill      = in_array('skill', $qCols);
$hasExplHtml   = in_array('explanation_html', $qCols);

// ── Handle POST ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quizId       = (int)($_POST['quiz_id'] ?? 0);
    $questionText = trim($_POST['question_text'] ?? '');
    $optionA      = trim($_POST['option_a'] ?? '');
    $optionB      = trim($_POST['option_b'] ?? '');
    $optionC      = trim($_POST['option_c'] ?? '');
    $optionD      = trim($_POST['option_d'] ?? '');
    $correct      = $_POST['correct_answer'] ?? '';
    $explanation   = trim($_POST['explanation_text'] ?? '');
    $domainVal    = $_POST['domain'] ?? '';
    $diffVal      = $_POST['difficulty'] ?? '';
    $skillVal     = trim($_POST['skill'] ?? '');

    if ($questionText === '') {
        $error = 'Question text is required.';
    } elseif (!in_array($correct, ['A','B','C','D'])) {
        $error = 'Please select a correct answer.';
    } else {
        try {
            // Get next order
            $nextOrder = 1;
            if ($quizId > 0) {
                $nextOrder = (int) $db->prepare("SELECT COALESCE(MAX(`order`),0)+1 FROM sat_quiz_questions WHERE quiz_id = ?")->execute([$quizId]) ?
                    (int) $db->query("SELECT COALESCE(MAX(`order`),0)+1 FROM sat_quiz_questions WHERE quiz_id = {$quizId}")->fetchColumn() : 1;
            }

            $cols = ['quiz_id', 'question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer', 'explanation_text', '`order`'];
            $vals = [':quiz_id', ':qtext', ':oa', ':ob', ':oc', ':od', ':correct', ':expl', ':ord'];
            $bind = [
                ':quiz_id' => $quizId ?: null,
                ':qtext'   => $questionText,
                ':oa'      => $optionA,
                ':ob'      => $optionB,
                ':oc'      => $optionC,
                ':od'      => $optionD,
                ':correct' => $correct,
                ':expl'    => $explanation,
                ':ord'     => $nextOrder,
            ];

            if ($hasDomain && $domainVal) {
                $cols[] = 'domain'; $vals[] = ':domain'; $bind[':domain'] = $domainVal;
            }
            if ($hasDifficulty && $diffVal) {
                $cols[] = 'difficulty'; $vals[] = ':difficulty'; $bind[':difficulty'] = $diffVal;
            }
            if ($hasSkill && $skillVal) {
                $cols[] = 'skill'; $vals[] = ':skill'; $bind[':skill'] = $skillVal;
            }

            $sql = "INSERT INTO sat_quiz_questions (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
            $ins = $db->prepare($sql);
            $ins->execute($bind);

            $success = 'Question created successfully!';
        } catch (Throwable $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// ── Draft count for sidebar ──────────────────────────────────────────────
try { $draftCount = (int) $db->query("SELECT COUNT(*) FROM sat_quizzes WHERE status='draft'")->fetchColumn(); } catch (Throwable $e) { $draftCount = 0; }

$pageTitle  = 'Create Question — Avidmock Admin';
$activePage = 'questions';
$extraHead  = <<<'CSS'
<style>
.main { margin-left: var(--sb-w); margin-top: var(--top-h); padding: 32px 28px; min-height: calc(100vh - var(--top-h)); }
.ph { margin-bottom: 24px; }
.ph-eyebrow { display: flex; align-items: center; gap: 6px; font-size: .5rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 5px; }
.ph-dot { width: 4px; height: 4px; border-radius: 50%; background: var(--ac); }
.ph-title { font-family: var(--fh); font-size: 1.875rem; font-weight: 900; color: var(--tx); letter-spacing: -.035em; line-height: 1.1; }

.form-card { background: var(--sf); border: 1px solid var(--bd); border-radius: 14px; padding: 28px; max-width: 720px; }
.form-group { margin-bottom: 20px; }
.form-label { display: block; font-size: .75rem; font-weight: 700; color: var(--tx2); margin-bottom: 6px; text-transform: uppercase; letter-spacing: .4px; }
.form-input, .form-textarea, .form-select { width: 100%; padding: 10px 14px; background: var(--ink); border: 1.5px solid var(--bd); border-radius: 9px; font-family: var(--ff); font-size: .875rem; color: var(--tx); outline: none; transition: border-color .18s; }
.form-textarea { min-height: 100px; resize: vertical; }
.form-input:focus, .form-textarea:focus, .form-select:focus { border-color: var(--ac); box-shadow: 0 0 0 3px rgba(31,226,144,.1); }

.options-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.option-item { display: flex; align-items: center; gap: 10px; }
.option-item label { font-weight: 700; color: var(--tx2); font-size: .875rem; min-width: 20px; }
.option-item input[type="text"] { flex: 1; padding: 10px 14px; background: var(--ink); border: 1.5px solid var(--bd); border-radius: 9px; font-family: var(--ff); font-size: .875rem; color: var(--tx); outline: none; }
.option-item input[type="text"]:focus { border-color: var(--ac); }

.correct-group { display: flex; gap: 12px; margin-top: 12px; }
.correct-radio { display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: .875rem; font-weight: 600; color: var(--tx2); }
.correct-radio input[type="radio"] { accent-color: var(--ac); }

.meta-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }

.alert { padding: 12px 16px; border-radius: 9px; font-size: .8125rem; font-weight: 600; margin-bottom: 16px; }
.alert-error { background: rgba(239,68,68,.1); color: var(--err); border: 1px solid rgba(239,68,68,.2); }
.alert-success { background: rgba(31,226,144,.1); color: var(--ac); border: 1px solid rgba(31,226,144,.2); }

.form-actions { display: flex; gap: 10px; margin-top: 24px; }

@media (max-width: 768px) { .main { margin-left: 0; padding: 16px; } .options-grid, .meta-grid { grid-template-columns: 1fr; } }
</style>
CSS;

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<header class="topbar">
    <button class="topbar-ham" onclick="openSidebar()" aria-label="Menu"><span></span><span></span><span></span></button>
    <div class="topbar-title">Questions <span>/ Create</span></div>
    <div class="topbar-spacer"></div>
    <a href="/questions/index.php" class="btn btn-ghost">Back to Bank</a>
</header>

<main class="main">
    <div class="ph reveal d1">
        <div class="ph-eyebrow"><span class="ph-dot"></span>Content</div>
        <h1 class="ph-title">Create Question</h1>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

    <div class="form-card reveal d2">
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Assign to Quiz (optional)</label>
                <select name="quiz_id" class="form-select">
                    <option value="0">— Standalone Question —</option>
                    <?php foreach ($quizzes as $qz): ?>
                    <option value="<?= $qz['id'] ?>"><?= e($qz['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Question Text</label>
                <textarea name="question_text" class="form-textarea" rows="4" required placeholder="Enter the question..."><?= e($_POST['question_text'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Answer Options</label>
                <div class="options-grid">
                    <div class="option-item"><label>A</label><input type="text" name="option_a" value="<?= e($_POST['option_a'] ?? '') ?>" required></div>
                    <div class="option-item"><label>B</label><input type="text" name="option_b" value="<?= e($_POST['option_b'] ?? '') ?>" required></div>
                    <div class="option-item"><label>C</label><input type="text" name="option_c" value="<?= e($_POST['option_c'] ?? '') ?>" required></div>
                    <div class="option-item"><label>D</label><input type="text" name="option_d" value="<?= e($_POST['option_d'] ?? '') ?>" required></div>
                </div>
                <div class="correct-group">
                    <span style="font-size:.75rem;font-weight:700;color:var(--tx3);margin-right:6px">Correct:</span>
                    <?php foreach (['A','B','C','D'] as $opt): ?>
                    <label class="correct-radio"><input type="radio" name="correct_answer" value="<?= $opt ?>" <?= ($_POST['correct_answer'] ?? '') === $opt ? 'checked' : '' ?>> <?= $opt ?></label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Explanation</label>
                <textarea name="explanation_text" class="form-textarea" rows="3" placeholder="Explain the correct answer..."><?= e($_POST['explanation_text'] ?? '') ?></textarea>
            </div>

            <div class="meta-grid">
                <?php if ($hasDomain): ?>
                <div class="form-group">
                    <label class="form-label">Domain</label>
                    <select name="domain" class="form-select">
                        <option value="">— Select —</option>
                        <option value="algebra">Algebra</option>
                        <option value="advanced_math">Advanced Math</option>
                        <option value="problem_solving">Problem Solving</option>
                        <option value="geometry_trig">Geometry & Trig</option>
                        <option value="reading_comprehension">Reading Comprehension</option>
                        <option value="writing_language">Writing & Language</option>
                    </select>
                </div>
                <?php endif; ?>
                <?php if ($hasDifficulty): ?>
                <div class="form-group">
                    <label class="form-label">Difficulty</label>
                    <select name="difficulty" class="form-select">
                        <option value="">— Select —</option>
                        <option value="easy">Easy</option>
                        <option value="medium">Medium</option>
                        <option value="hard">Hard</option>
                    </select>
                </div>
                <?php endif; ?>
                <?php if ($hasSkill): ?>
                <div class="form-group">
                    <label class="form-label">Skill</label>
                    <input type="text" name="skill" class="form-input" placeholder="e.g. linear-equations" value="<?= e($_POST['skill'] ?? '') ?>">
                </div>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create Question</button>
                <a href="/questions/index.php" class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    </div>
</main>
</body>
</html>
