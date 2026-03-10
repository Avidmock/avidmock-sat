<?php
/**
 * questions/edit.php
 * Edit a single question.
 */
require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

$admin = currentAdmin();
$db    = Database::connect();

$id    = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

if (!$id) { header('Location: /questions/index.php'); exit; }

// ── Detect columns ────────────────────────────────────────────────────────
try {
    $qCols = $db->query("SHOW COLUMNS FROM sat_quiz_questions")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) { $qCols = []; }
$hasDomain     = in_array('domain', $qCols);
$hasDifficulty = in_array('difficulty', $qCols);
$hasSkill      = in_array('skill', $qCols);

// ── Fetch question ────────────────────────────────────────────────────────
try {
    $question = $db->prepare("SELECT * FROM sat_quiz_questions WHERE id = ?")->execute([$id]) ? null : null;
    $stmt = $db->prepare("SELECT * FROM sat_quiz_questions WHERE id = ?");
    $stmt->execute([$id]);
    $question = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $question = null; }

if (!$question) { header('Location: /questions/index.php'); exit; }

// ── Fetch quizzes ─────────────────────────────────────────────────────────
try {
    $quizzes = $db->query("SELECT id, title FROM sat_quizzes ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $quizzes = []; }

// ── Handle POST ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $questionText = trim($_POST['question_text'] ?? '');
    $optionA      = trim($_POST['option_a'] ?? '');
    $optionB      = trim($_POST['option_b'] ?? '');
    $optionC      = trim($_POST['option_c'] ?? '');
    $optionD      = trim($_POST['option_d'] ?? '');
    $correct      = $_POST['correct_answer'] ?? '';
    $explanation   = trim($_POST['explanation_text'] ?? '');
    $quizId       = (int)($_POST['quiz_id'] ?? 0);

    if ($questionText === '') {
        $error = 'Question text is required.';
    } elseif (!in_array($correct, ['A','B','C','D'])) {
        $error = 'Select a correct answer.';
    } else {
        try {
            $sets = [
                'question_text = :qtext',
                'option_a = :oa', 'option_b = :ob', 'option_c = :oc', 'option_d = :od',
                'correct_answer = :correct',
                'explanation_text = :expl',
                'quiz_id = :quiz_id',
            ];
            $bind = [
                ':qtext'   => $questionText,
                ':oa'      => $optionA,
                ':ob'      => $optionB,
                ':oc'      => $optionC,
                ':od'      => $optionD,
                ':correct' => $correct,
                ':expl'    => $explanation,
                ':quiz_id' => $quizId ?: null,
                ':id'      => $id,
            ];

            if ($hasDomain) {
                $sets[] = 'domain = :domain';
                $bind[':domain'] = $_POST['domain'] ?? '';
            }
            if ($hasDifficulty) {
                $sets[] = 'difficulty = :difficulty';
                $bind[':difficulty'] = $_POST['difficulty'] ?? '';
            }
            if ($hasSkill) {
                $sets[] = 'skill = :skill';
                $bind[':skill'] = trim($_POST['skill'] ?? '');
            }

            $sql = "UPDATE sat_quiz_questions SET " . implode(', ', $sets) . " WHERE id = :id";
            $db->prepare($sql)->execute($bind);

            $success = 'Question updated!';
            // Re-fetch
            $stmt = $db->prepare("SELECT * FROM sat_quiz_questions WHERE id = ?");
            $stmt->execute([$id]);
            $question = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// ── Question stats ────────────────────────────────────────────────────────
try {
    $statsStmt = $db->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN is_correct=1 THEN 1 ELSE 0 END) AS correct FROM sat_quiz_answers WHERE question_id = ?");
    $statsStmt->execute([$id]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $stats = ['total' => 0, 'correct' => 0]; }
$totalAttempts = (int)($stats['total'] ?? 0);
$correctCount  = (int)($stats['correct'] ?? 0);
$accuracyPct   = $totalAttempts > 0 ? round($correctCount / $totalAttempts * 100, 1) : null;

try { $draftCount = (int) $db->query("SELECT COUNT(*) FROM sat_quizzes WHERE status='draft'")->fetchColumn(); } catch (Throwable $e) { $draftCount = 0; }

$pageTitle  = 'Edit Question — Avidmock Admin';
$activePage = 'questions';
$extraHead  = <<<'CSS'
<style>
.main { margin-left: var(--sb-w); margin-top: var(--top-h); padding: 32px 28px; min-height: calc(100vh - var(--top-h)); }
.ph { margin-bottom: 24px; }
.ph-eyebrow { display: flex; align-items: center; gap: 6px; font-size: .5rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 5px; }
.ph-dot { width: 4px; height: 4px; border-radius: 50%; background: var(--ac); }
.ph-title { font-family: var(--fh); font-size: 1.875rem; font-weight: 900; color: var(--tx); letter-spacing: -.035em; line-height: 1.1; }

.layout { display: grid; grid-template-columns: 1fr 280px; gap: 20px; max-width: 960px; }
.form-card { background: var(--sf); border: 1px solid var(--bd); border-radius: 14px; padding: 28px; }
.side-card { background: var(--sf); border: 1px solid var(--bd); border-radius: 14px; padding: 20px; height: fit-content; }

.form-group { margin-bottom: 20px; }
.form-label { display: block; font-size: .75rem; font-weight: 700; color: var(--tx2); margin-bottom: 6px; text-transform: uppercase; letter-spacing: .4px; }
.form-input, .form-textarea, .form-select { width: 100%; padding: 10px 14px; background: var(--ink); border: 1.5px solid var(--bd); border-radius: 9px; font-family: var(--ff); font-size: .875rem; color: var(--tx); outline: none; }
.form-textarea { min-height: 100px; resize: vertical; }
.form-input:focus, .form-textarea:focus, .form-select:focus { border-color: var(--ac); box-shadow: 0 0 0 3px rgba(31,226,144,.1); }

.options-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.option-item { display: flex; align-items: center; gap: 10px; }
.option-item label { font-weight: 700; color: var(--tx2); font-size: .875rem; min-width: 20px; }
.option-item input[type="text"] { flex: 1; padding: 10px 14px; background: var(--ink); border: 1.5px solid var(--bd); border-radius: 9px; font-family: var(--ff); font-size: .875rem; color: var(--tx); outline: none; }
.correct-group { display: flex; gap: 12px; margin-top: 12px; }
.correct-radio { display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: .875rem; font-weight: 600; color: var(--tx2); }
.correct-radio input[type="radio"] { accent-color: var(--ac); }
.meta-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }

.alert { padding: 12px 16px; border-radius: 9px; font-size: .8125rem; font-weight: 600; margin-bottom: 16px; }
.alert-error { background: rgba(239,68,68,.1); color: var(--err); border: 1px solid rgba(239,68,68,.2); }
.alert-success { background: rgba(31,226,144,.1); color: var(--ac); border: 1px solid rgba(31,226,144,.2); }

.side-stat { margin-bottom: 16px; }
.side-stat-label { font-size: .625rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 3px; }
.side-stat-value { font-family: var(--fm); font-size: 1.25rem; font-weight: 800; color: var(--tx); }
.side-stat-value.green { color: var(--ac); }
.side-stat-value.red { color: var(--err); }

.form-actions { display: flex; gap: 10px; margin-top: 24px; }

@media (max-width: 768px) { .main { margin-left: 0; padding: 16px; } .layout { grid-template-columns: 1fr; } .options-grid, .meta-grid { grid-template-columns: 1fr; } }
</style>
CSS;

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<header class="topbar">
    <button class="topbar-ham" onclick="openSidebar()" aria-label="Menu"><span></span><span></span><span></span></button>
    <div class="topbar-title">Questions <span>/ Edit #<?= $id ?></span></div>
    <div class="topbar-spacer"></div>
    <a href="/questions/index.php" class="btn btn-ghost">Back to Bank</a>
</header>

<main class="main">
    <div class="ph reveal d1">
        <div class="ph-eyebrow"><span class="ph-dot"></span>Content</div>
        <h1 class="ph-title">Edit Question</h1>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

    <div class="layout reveal d2">
        <div class="form-card">
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Assign to Quiz</label>
                    <select name="quiz_id" class="form-select">
                        <option value="0">— Standalone —</option>
                        <?php foreach ($quizzes as $qz): ?>
                        <option value="<?= $qz['id'] ?>" <?= ($question['quiz_id'] ?? 0) == $qz['id'] ? 'selected' : '' ?>><?= e($qz['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Question Text</label>
                    <textarea name="question_text" class="form-textarea" rows="4" required><?= e($question['question_text'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Options</label>
                    <div class="options-grid">
                        <div class="option-item"><label>A</label><input type="text" name="option_a" value="<?= e($question['option_a'] ?? '') ?>" required></div>
                        <div class="option-item"><label>B</label><input type="text" name="option_b" value="<?= e($question['option_b'] ?? '') ?>" required></div>
                        <div class="option-item"><label>C</label><input type="text" name="option_c" value="<?= e($question['option_c'] ?? '') ?>" required></div>
                        <div class="option-item"><label>D</label><input type="text" name="option_d" value="<?= e($question['option_d'] ?? '') ?>" required></div>
                    </div>
                    <div class="correct-group">
                        <span style="font-size:.75rem;font-weight:700;color:var(--tx3);margin-right:6px">Correct:</span>
                        <?php foreach (['A','B','C','D'] as $opt): ?>
                        <label class="correct-radio"><input type="radio" name="correct_answer" value="<?= $opt ?>" <?= ($question['correct_answer'] ?? '') === $opt ? 'checked' : '' ?>> <?= $opt ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Explanation</label>
                    <textarea name="explanation_text" class="form-textarea" rows="3"><?= e($question['explanation_text'] ?? '') ?></textarea>
                </div>

                <div class="meta-grid">
                    <?php if ($hasDomain): ?>
                    <div class="form-group">
                        <label class="form-label">Domain</label>
                        <select name="domain" class="form-select">
                            <option value="">— Select —</option>
                            <?php foreach (['algebra','advanced_math','problem_solving','geometry_trig','reading_comprehension','writing_language'] as $d): ?>
                            <option value="<?= $d ?>" <?= ($question['domain'] ?? '') === $d ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $d)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <?php if ($hasDifficulty): ?>
                    <div class="form-group">
                        <label class="form-label">Difficulty</label>
                        <select name="difficulty" class="form-select">
                            <option value="">— Select —</option>
                            <?php foreach (['easy','medium','hard'] as $d): ?>
                            <option value="<?= $d ?>" <?= ($question['difficulty'] ?? '') === $d ? 'selected' : '' ?>><?= ucfirst($d) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <?php if ($hasSkill): ?>
                    <div class="form-group">
                        <label class="form-label">Skill</label>
                        <input type="text" name="skill" class="form-input" value="<?= e($question['skill'] ?? '') ?>">
                    </div>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="/questions/index.php" class="btn btn-ghost">Cancel</a>
                </div>
            </form>
        </div>

        <div class="side-card">
            <h3 style="font-family:var(--fh);font-size:1rem;font-weight:900;color:var(--tx);margin-bottom:16px">Stats</h3>
            <div class="side-stat">
                <div class="side-stat-label">Total Attempts</div>
                <div class="side-stat-value"><?= number_format($totalAttempts) ?></div>
            </div>
            <div class="side-stat">
                <div class="side-stat-label">Correct Answers</div>
                <div class="side-stat-value green"><?= number_format($correctCount) ?></div>
            </div>
            <div class="side-stat">
                <div class="side-stat-label">Accuracy Rate</div>
                <div class="side-stat-value <?= $accuracyPct !== null ? ($accuracyPct >= 60 ? 'green' : 'red') : '' ?>">
                    <?= $accuracyPct !== null ? $accuracyPct . '%' : '—' ?>
                </div>
            </div>
            <div class="side-stat">
                <div class="side-stat-label">Question ID</div>
                <div class="side-stat-value">#<?= $id ?></div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
