<?php
/**
 * essay/index.php
 * AI-powered essay/writing practice with scoring and feedback.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Database.php';

Auth::requireStudent();

$userId      = (int) $_SESSION['user_id'];
$activePage  = 'essay';
$topbarTitle = 'Essay Practice';
$db          = Database::connect();

// ── Fetch past submissions ────────────────────────────────────────────────
try {
    $stmt = $db->prepare(
        "SELECT id, overall_score, word_count, time_spent, created_at
         FROM rw_submissions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10"
    );
    $stmt->execute([$userId]);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $submissions = []; }

// ── Prompts ───────────────────────────────────────────────────────────────
$prompts = [
    ['id' => 1, 'title' => 'Technology in Education', 'text' => 'Some people argue that technology in the classroom distracts students, while others believe it enhances learning. Write an essay that takes a clear position on this issue, using evidence and reasoning to support your argument.', 'category' => 'Argumentative'],
    ['id' => 2, 'title' => 'Community Service', 'text' => 'Should community service be a requirement for high school graduation? Write an essay that argues for or against mandatory community service, supporting your position with specific examples and logical reasoning.', 'category' => 'Argumentative'],
    ['id' => 3, 'title' => 'Social Media Impact', 'text' => 'Analyze the impact of social media on teenage mental health. Consider both positive and negative effects, and propose a balanced approach to social media use among young people.', 'category' => 'Analytical'],
    ['id' => 4, 'title' => 'Climate Action', 'text' => 'Some argue that individual actions to combat climate change are insignificant compared to policy changes. Others believe personal responsibility is key. Evaluate both perspectives and present your own position.', 'category' => 'Evaluative'],
];

$avgScore = 0;
if (!empty($submissions)) {
    $scores = array_filter(array_column($submissions, 'overall_score'));
    $avgScore = count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Essay Practice — AvidMock SAT</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:wght@700;900&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{--dk:#143230;--ac:#1FE290;--tx:#1a1a2e;--tx2:#64748b;--tx3:#94a3b8;--bg:#f7faf9;--r:16px;--r-sm:10px}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--tx);min-height:100vh}
.main-content{margin-left:260px;padding:32px;min-height:100vh}

.page-header{margin-bottom:28px}
.page-header h1{font-family:'Fraunces',serif;font-size:1.875rem;font-weight:900;letter-spacing:-.03em;color:var(--dk)}
.page-header p{color:var(--tx2);font-size:.875rem;margin-top:4px}

.stats-row{display:flex;gap:14px;margin-bottom:28px}
.stat-card{background:#fff;border:1px solid rgba(20,50,48,.06);border-radius:var(--r-sm);padding:16px 20px;flex:1}
.stat-label{font-size:.625rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--tx3);margin-bottom:4px}
.stat-value{font-family:'DM Mono',monospace;font-size:1.5rem;font-weight:800;color:var(--dk)}

.section-title{font-family:'Fraunces',serif;font-size:1.125rem;font-weight:900;color:var(--dk);margin-bottom:16px}

.prompts-grid{display:grid;grid-template-columns:repeat(auto-fill, minmax(300px, 1fr));gap:16px;margin-bottom:32px}
.prompt-card{background:#fff;border:1px solid rgba(20,50,48,.06);border-radius:var(--r);padding:24px;transition:all .18s;cursor:pointer}
.prompt-card:hover{border-color:var(--ac);transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,.08)}
.prompt-category{font-size:.5625rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--ac);background:rgba(31,226,144,.08);padding:3px 8px;border-radius:4px;display:inline-block;margin-bottom:10px}
.prompt-title{font-family:'Fraunces',serif;font-size:1.05rem;font-weight:900;color:var(--dk);margin-bottom:8px}
.prompt-text{font-size:.8125rem;color:var(--tx2);line-height:1.55;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.prompt-action{margin-top:14px;display:flex;justify-content:flex-end}
.btn-start{padding:8px 18px;background:var(--ac);color:var(--dk);font-weight:700;font-size:.75rem;border:none;border-radius:8px;cursor:pointer}

/* Writing modal */
.write-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);z-index:200;justify-content:center;align-items:center}
.write-modal.show{display:flex}
.write-panel{background:#fff;border-radius:20px;width:95%;max-width:800px;max-height:90vh;overflow-y:auto;padding:32px}
.write-prompt{background:var(--bg);border-radius:var(--r-sm);padding:16px;margin-bottom:20px;font-size:.875rem;color:var(--tx2);line-height:1.6}
.write-area{width:100%;min-height:300px;padding:16px;border:2px solid rgba(20,50,48,.08);border-radius:var(--r-sm);font-family:'DM Sans',sans-serif;font-size:.9375rem;line-height:1.8;color:var(--tx);resize:vertical;outline:none}
.write-area:focus{border-color:var(--ac)}
.write-footer{display:flex;justify-content:space-between;align-items:center;margin-top:16px}
.word-count{font-family:'DM Mono',monospace;font-size:.75rem;color:var(--tx3)}
.write-actions{display:flex;gap:8px}
.btn-cancel{padding:10px 20px;background:none;border:1.5px solid rgba(20,50,48,.1);border-radius:var(--r-sm);font-weight:600;font-size:.8125rem;cursor:pointer;color:var(--tx2)}
.btn-submit{padding:10px 24px;background:var(--ac);color:var(--dk);font-weight:700;font-size:.8125rem;border:none;border-radius:var(--r-sm);cursor:pointer}
.btn-submit:disabled{opacity:.4;cursor:not-allowed}

/* Past submissions */
.submissions-list{background:#fff;border:1px solid rgba(20,50,48,.06);border-radius:var(--r);overflow:hidden}
.sub-item{display:flex;align-items:center;padding:14px 20px;border-bottom:1px solid rgba(20,50,48,.04);gap:16px}
.sub-item:last-child{border-bottom:none}
.sub-score{font-family:'DM Mono',monospace;font-size:1.125rem;font-weight:800;min-width:40px}
.sub-score.hi{color:var(--ac)}
.sub-score.md{color:#f59e0b}
.sub-score.lo{color:#ef4444}
.sub-info{flex:1}
.sub-date{font-size:.75rem;color:var(--tx3)}
.sub-meta{font-size:.6875rem;color:var(--tx3);margin-top:2px}

.empty-text{color:var(--tx3);font-size:.875rem;padding:20px;text-align:center}

@media(max-width:768px){.main-content{margin-left:0;padding:16px}.stats-row{flex-direction:column}.prompts-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/sidebar.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/topbar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1>Essay Practice</h1>
        <p>Improve your SAT writing with AI-powered feedback and scoring</p>
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-label">Essays Written</div>
            <div class="stat-value"><?= count($submissions) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Average Score</div>
            <div class="stat-value"><?= $avgScore ?: '—' ?>/10</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Words</div>
            <div class="stat-value"><?= number_format(array_sum(array_column($submissions, 'word_count'))) ?></div>
        </div>
    </div>

    <div class="section-title">Writing Prompts</div>
    <div class="prompts-grid">
        <?php foreach ($prompts as $p): ?>
        <div class="prompt-card" onclick="openWriteModal(<?= $p['id'] ?>)">
            <span class="prompt-category"><?= htmlspecialchars($p['category']) ?></span>
            <div class="prompt-title"><?= htmlspecialchars($p['title']) ?></div>
            <div class="prompt-text"><?= htmlspecialchars($p['text']) ?></div>
            <div class="prompt-action">
                <button class="btn-start">Start Writing</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($submissions)): ?>
    <div class="section-title">Past Submissions</div>
    <div class="submissions-list">
        <?php foreach ($submissions as $sub):
            $sc = (int)($sub['overall_score'] ?? 0);
            $cls = $sc >= 7 ? 'hi' : ($sc >= 5 ? 'md' : 'lo');
        ?>
        <div class="sub-item">
            <span class="sub-score <?= $cls ?>"><?= $sc ?></span>
            <div class="sub-info">
                <div class="sub-date"><?= date('M j, Y g:ia', strtotime($sub['created_at'])) ?></div>
                <div class="sub-meta"><?= (int)$sub['word_count'] ?> words</div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Write Modal -->
<div class="write-modal" id="writeModal">
    <div class="write-panel">
        <div class="write-prompt" id="writePrompt"></div>
        <textarea class="write-area" id="essayText" placeholder="Start writing your essay..." oninput="updateWordCount()"></textarea>
        <div class="write-footer">
            <span class="word-count"><span id="wc">0</span> words</span>
            <div class="write-actions">
                <button class="btn-cancel" onclick="closeWriteModal()">Cancel</button>
                <button class="btn-submit" id="submitEssay" disabled onclick="submitEssay()">Submit for Scoring</button>
            </div>
        </div>
    </div>
</div>

<script>
const prompts = <?= json_encode($prompts) ?>;
let activePromptId = null;
let startTime = null;

function openWriteModal(id) {
    const p = prompts.find(x => x.id === id);
    if (!p) return;
    activePromptId = id;
    startTime = Date.now();
    document.getElementById('writePrompt').textContent = p.text;
    document.getElementById('essayText').value = '';
    document.getElementById('wc').textContent = '0';
    document.getElementById('writeModal').classList.add('show');
}

function closeWriteModal() {
    document.getElementById('writeModal').classList.remove('show');
    activePromptId = null;
}

function updateWordCount() {
    const text = document.getElementById('essayText').value.trim();
    const count = text ? text.split(/\s+/).length : 0;
    document.getElementById('wc').textContent = count;
    document.getElementById('submitEssay').disabled = count < 50;
}

async function submitEssay() {
    const text = document.getElementById('essayText').value.trim();
    const timeSpent = Math.floor((Date.now() - startTime) / 1000);
    document.getElementById('submitEssay').disabled = true;
    document.getElementById('submitEssay').textContent = 'Scoring...';

    try {
        const res = await fetch('/api/essay-scorer.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({prompt_id: activePromptId, response_text: text, time_spent: timeSpent})
        });
        const data = await res.json();
        if (data.success) {
            closeWriteModal();
            location.reload();
        } else {
            alert(data.error || 'Failed to score essay');
            document.getElementById('submitEssay').disabled = false;
            document.getElementById('submitEssay').textContent = 'Submit for Scoring';
        }
    } catch (e) {
        alert('Network error');
        document.getElementById('submitEssay').disabled = false;
        document.getElementById('submitEssay').textContent = 'Submit for Scoring';
    }
}
</script>
</body>
</html>
