<?php
/**
 * quizzes/ai-generate.php — AI Question Generator
 * The content creation superpower. Uses Claude to generate
 * SAT-quality questions from topic + difficulty specs.
 */
require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/AIQuestionGenerator.php';

$admin      = currentAdmin();
$db         = Database::connect();
$activePage = 'ai-generate';

// Get existing quizzes for "Save to Quiz" dropdown
$quizzes = [];
try {
    $quizzes = $db->query(
        "SELECT id, title, lesson_slug, status FROM sat_quizzes ORDER BY updated_at DESC LIMIT 50"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable) {}

$domains = AIQuestionGenerator::DOMAINS;

$pageTitle = 'AI Question Generator — Avidmock Admin';
$withKatex = true;
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">

<div class="ai-gen-header">
    <div class="ai-gen-header-text">
        <h1 class="page-title">
            <svg class="title-ico" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z" fill="none" stroke="currentColor" stroke-width="2"/><path d="M2 17l10 5 10-5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M2 12l10 5 10-5" fill="none" stroke="currentColor" stroke-width="2"/></svg>
            AI Question Generator
        </h1>
        <p class="page-sub">Generate SAT-quality questions instantly with Claude AI. Select a domain, skill, and difficulty — get exam-ready questions in seconds.</p>
    </div>
    <div class="ai-gen-stats" id="genStats">
        <div class="gen-stat"><span class="gen-stat-num" id="statGenerated">—</span><span class="gen-stat-label">Generated Today</span></div>
        <div class="gen-stat"><span class="gen-stat-num" id="statTotal">—</span><span class="gen-stat-label">Total Questions</span></div>
    </div>
</div>

<!-- Generator Form -->
<div class="ai-gen-form-wrap">
    <form id="genForm" class="ai-gen-form">

        <div class="form-row">
            <div class="form-group">
                <label>SAT Math Domain</label>
                <select id="domain" name="domain" required>
                    <option value="">Select domain…</option>
                    <?php foreach ($domains as $key => $d): ?>
                    <option value="<?= $key ?>"><?= htmlspecialchars($d['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Specific Skill</label>
                <select id="skill" name="skill" required disabled>
                    <option value="">Select domain first…</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Difficulty</label>
                <div class="diff-pills">
                    <label class="diff-pill"><input type="radio" name="difficulty" value="easy"><span>Easy</span></label>
                    <label class="diff-pill"><input type="radio" name="difficulty" value="medium" checked><span>Medium</span></label>
                    <label class="diff-pill"><input type="radio" name="difficulty" value="hard"><span>Hard</span></label>
                </div>
            </div>

            <div class="form-group">
                <label>Number of Questions</label>
                <div class="count-control">
                    <button type="button" class="count-btn" onclick="adjustCount(-1)">−</button>
                    <input type="number" id="count" name="count" value="5" min="1" max="10" class="count-input">
                    <button type="button" class="count-btn" onclick="adjustCount(1)">+</button>
                </div>
            </div>

            <div class="form-group">
                <label>Save to Quiz (optional)</label>
                <select id="quizId" name="quiz_id">
                    <option value="">Don't save — preview only</option>
                    <?php foreach ($quizzes as $q): ?>
                    <option value="<?= $q['id'] ?>"><?= htmlspecialchars($q['title']) ?> (<?= $q['status'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-generate" id="btnGenerate">
                <svg viewBox="0 0 24 24" width="20" height="20"><path d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48l2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48l2.83-2.83" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/></svg>
                Generate with AI
            </button>
            <span class="gen-hint">Powered by Claude · ~10 seconds per batch</span>
        </div>
    </form>
</div>

<!-- Loading State -->
<div id="loadingState" class="ai-gen-loading" style="display:none">
    <div class="loading-spinner">
        <div class="spinner-ring"></div>
        <div class="spinner-ring"></div>
        <div class="spinner-ring"></div>
    </div>
    <p class="loading-text">Generating <span id="loadingCount">5</span> questions…</p>
    <p class="loading-sub">Claude is crafting SAT-quality questions with step-by-step explanations</p>
</div>

<!-- Results -->
<div id="resultsArea" class="ai-gen-results" style="display:none">
    <div class="results-header">
        <h2 class="results-title">Generated Questions</h2>
        <div class="results-meta" id="resultsMeta"></div>
        <div class="results-actions">
            <button class="btn-secondary" onclick="saveAllToQuiz()">Save All to Quiz</button>
            <button class="btn-secondary" onclick="regenerate()">Regenerate</button>
        </div>
    </div>
    <div id="questionCards" class="question-cards"></div>
</div>

</main>

<style>
/* ── AI Generator Page Styles ───────────────────────────────────── */
.main-content { margin-left: 260px; padding: 2rem 2.5rem; min-height: 100vh; }
@media (max-width: 768px) { .main-content { margin-left: 0; padding: 1rem; } }

.ai-gen-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 2rem; margin-bottom: 2rem; }
.page-title { font: 800 1.75rem/1.2 'DM Sans', sans-serif; color: var(--tx, #e8f3f1); display: flex; align-items: center; gap: .5rem; }
.title-ico { width: 28px; height: 28px; color: var(--ac); }
.page-sub { color: rgba(232,243,241,.55); font-size: .9rem; margin-top: .35rem; max-width: 500px; }

.ai-gen-stats { display: flex; gap: 1.5rem; }
.gen-stat { text-align: center; background: rgba(31,226,144,.06); border: 1px solid rgba(31,226,144,.12); border-radius: 12px; padding: .75rem 1.25rem; }
.gen-stat-num { display: block; font: 700 1.5rem 'DM Mono', monospace; color: var(--ac); }
.gen-stat-label { font-size: .7rem; color: rgba(232,243,241,.45); text-transform: uppercase; letter-spacing: .5px; }

/* ── Form ──────────────────────────────────────────────────────── */
.ai-gen-form-wrap { background: var(--ink2, #0d2220); border: 1px solid rgba(31,226,144,.1); border-radius: 16px; padding: 2rem; margin-bottom: 2rem; }
.form-row { display: flex; gap: 1.5rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
.form-group { flex: 1; min-width: 200px; }
.form-group label { display: block; font: 600 .75rem 'DM Sans'; color: rgba(232,243,241,.6); text-transform: uppercase; letter-spacing: .5px; margin-bottom: .5rem; }

select, .count-input {
    width: 100%; padding: .65rem .9rem; background: var(--ink3, #071512); border: 1px solid rgba(31,226,144,.15);
    border-radius: 8px; color: var(--tx, #e8f3f1); font: 400 .9rem 'DM Sans'; outline: none; transition: border .2s;
}
select:focus, .count-input:focus { border-color: var(--ac); }

.diff-pills { display: flex; gap: .5rem; }
.diff-pill { cursor: pointer; }
.diff-pill input { display: none; }
.diff-pill span {
    display: block; padding: .55rem 1.2rem; border-radius: 8px; font: 500 .85rem 'DM Sans';
    border: 1px solid rgba(31,226,144,.15); color: rgba(232,243,241,.6); transition: all .2s;
}
.diff-pill input:checked + span { background: var(--ac); color: #071512; border-color: var(--ac); font-weight: 700; }
.diff-pill:hover span { border-color: var(--ac); }

.count-control { display: flex; align-items: center; gap: 0; }
.count-btn {
    width: 40px; height: 40px; border: 1px solid rgba(31,226,144,.15); background: var(--ink3, #071512);
    color: var(--ac); font-size: 1.2rem; cursor: pointer; transition: all .15s;
}
.count-btn:first-child { border-radius: 8px 0 0 8px; }
.count-btn:last-child { border-radius: 0 8px 8px 0; }
.count-btn:hover { background: rgba(31,226,144,.1); }
.count-input { border-radius: 0; text-align: center; width: 60px; -moz-appearance: textfield; }
.count-input::-webkit-inner-spin-button { display: none; }

.form-actions { display: flex; align-items: center; gap: 1rem; padding-top: .5rem; }
.btn-generate {
    display: flex; align-items: center; gap: .5rem; padding: .75rem 2rem; background: var(--ac);
    color: #071512; border: none; border-radius: 10px; font: 700 .95rem 'DM Sans'; cursor: pointer;
    transition: all .2s; box-shadow: 0 0 20px rgba(31,226,144,.2);
}
.btn-generate:hover { transform: translateY(-1px); box-shadow: 0 4px 25px rgba(31,226,144,.35); }
.btn-generate:disabled { opacity: .5; cursor: not-allowed; transform: none; }
.gen-hint { font-size: .78rem; color: rgba(232,243,241,.35); }

/* ── Loading ───────────────────────────────────────────────────── */
.ai-gen-loading { text-align: center; padding: 3rem; }
.loading-spinner { display: flex; justify-content: center; gap: .3rem; margin-bottom: 1.5rem; }
.spinner-ring {
    width: 12px; height: 12px; border-radius: 50%; background: var(--ac);
    animation: pulse 1.2s ease-in-out infinite;
}
.spinner-ring:nth-child(2) { animation-delay: .2s; }
.spinner-ring:nth-child(3) { animation-delay: .4s; }
@keyframes pulse { 0%, 100% { transform: scale(.6); opacity: .3; } 50% { transform: scale(1); opacity: 1; } }
.loading-text { font: 600 1.1rem 'DM Sans'; color: var(--tx); }
.loading-sub { font-size: .85rem; color: rgba(232,243,241,.4); margin-top: .3rem; }

/* ── Results ───────────────────────────────────────────────────── */
.results-header { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
.results-title { font: 700 1.3rem 'DM Sans'; color: var(--tx); }
.results-meta { font: 400 .85rem 'DM Mono'; color: var(--ac); flex: 1; }
.results-actions { display: flex; gap: .5rem; }
.btn-secondary {
    padding: .5rem 1rem; background: transparent; border: 1px solid rgba(31,226,144,.2);
    color: var(--ac); border-radius: 8px; font: 500 .8rem 'DM Sans'; cursor: pointer; transition: all .15s;
}
.btn-secondary:hover { background: rgba(31,226,144,.08); border-color: var(--ac); }

.question-cards { display: flex; flex-direction: column; gap: 1.25rem; }

.q-card {
    background: var(--ink2, #0d2220); border: 1px solid rgba(31,226,144,.1); border-radius: 14px;
    padding: 1.5rem; transition: border-color .2s;
}
.q-card:hover { border-color: rgba(31,226,144,.25); }
.q-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.q-num { font: 700 .75rem 'DM Mono'; color: var(--ac); text-transform: uppercase; letter-spacing: 1px; }
.q-diff { font: 500 .7rem 'DM Sans'; padding: .2rem .6rem; border-radius: 20px; }
.q-diff.easy { background: rgba(31,226,144,.12); color: #1fe290; }
.q-diff.medium { background: rgba(255,179,71,.12); color: #FFB347; }
.q-diff.hard { background: rgba(255,107,107,.12); color: #FF6B6B; }

.q-stem { font: 400 1rem 'DM Sans'; color: var(--tx); line-height: 1.65; margin-bottom: 1.25rem; }

.q-options { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; margin-bottom: 1.25rem; }
.q-opt {
    display: flex; align-items: flex-start; gap: .5rem; padding: .6rem .8rem;
    border-radius: 8px; font-size: .88rem; color: rgba(232,243,241,.75); background: var(--ink3, #071512);
    border: 1px solid transparent;
}
.q-opt.correct { border-color: var(--ac); background: rgba(31,226,144,.06); color: var(--ac); }
.q-opt-letter { font: 700 .8rem 'DM Mono'; color: var(--ac); min-width: 1.2rem; }

.q-explanation { background: rgba(31,226,144,.04); border-left: 3px solid var(--ac); border-radius: 0 8px 8px 0; padding: 1rem 1.25rem; margin-top: .5rem; }
.q-explanation-title { font: 700 .75rem 'DM Mono'; color: var(--ac); text-transform: uppercase; letter-spacing: .5px; margin-bottom: .5rem; }
.q-explanation-text { font-size: .88rem; color: rgba(232,243,241,.7); line-height: 1.7; }

.q-card-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; padding-top: .75rem; border-top: 1px solid rgba(31,226,144,.06); }
.q-strategy { font-size: .8rem; color: rgba(232,243,241,.4); font-style: italic; max-width: 70%; }
.q-actions { display: flex; gap: .5rem; }
.q-btn {
    padding: .35rem .75rem; border-radius: 6px; font: 500 .75rem 'DM Sans'; cursor: pointer;
    border: 1px solid rgba(31,226,144,.15); background: transparent; color: rgba(232,243,241,.6); transition: all .15s;
}
.q-btn:hover { border-color: var(--ac); color: var(--ac); }
.q-btn.save { background: var(--ac); color: #071512; border-color: var(--ac); }

@media (max-width: 600px) {
    .ai-gen-header { flex-direction: column; }
    .form-row { flex-direction: column; }
    .q-options { grid-template-columns: 1fr; }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>

<script>
// ── Domain → Skills mapping ──────────────────────────────────────
const domains = <?= json_encode($domains, JSON_UNESCAPED_UNICODE) ?>;

const domainSel = document.getElementById('domain');
const skillSel  = document.getElementById('skill');

domainSel.addEventListener('change', () => {
    const d = domains[domainSel.value];
    skillSel.innerHTML = '<option value="">Select skill…</option>';
    if (d) {
        Object.entries(d.skills).forEach(([k, v]) => {
            skillSel.innerHTML += `<option value="${k}">${v}</option>`;
        });
        skillSel.disabled = false;
    } else {
        skillSel.disabled = true;
    }
});

function adjustCount(delta) {
    const inp = document.getElementById('count');
    inp.value = Math.max(1, Math.min(10, parseInt(inp.value || 5) + delta));
}

// ── Generate ─────────────────────────────────────────────────────
const form       = document.getElementById('genForm');
const loadingEl  = document.getElementById('loadingState');
const resultsEl  = document.getElementById('resultsArea');
const cardsEl    = document.getElementById('questionCards');
let lastGenerated = [];

form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const domain     = domainSel.value;
    const skill      = skillSel.value;
    const difficulty = document.querySelector('input[name="difficulty"]:checked')?.value || 'medium';
    const count      = parseInt(document.getElementById('count').value) || 5;
    const quizId     = parseInt(document.getElementById('quizId').value) || 0;

    if (!domain || !skill) { alert('Please select a domain and skill.'); return; }

    // Show loading
    form.querySelector('.btn-generate').disabled = true;
    loadingEl.style.display = 'block';
    resultsEl.style.display = 'none';
    document.getElementById('loadingCount').textContent = count;

    try {
        const resp = await fetch('/api/generate-questions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                domain, skill, difficulty, count,
                quiz_id: quizId,
                save: quizId > 0,
            }),
        });

        const data = await resp.json();
        loadingEl.style.display = 'none';

        if (data.questions && data.questions.length > 0) {
            lastGenerated = data.questions;
            renderQuestions(data.questions, data.meta);
            resultsEl.style.display = 'block';
        } else {
            alert('Generation failed. ' + (data.meta?.error || 'Try again.'));
        }
    } catch (err) {
        loadingEl.style.display = 'none';
        alert('Network error: ' + err.message);
    }

    form.querySelector('.btn-generate').disabled = false;
});

function renderQuestions(questions, meta) {
    document.getElementById('resultsMeta').textContent =
        `${meta.validated}/${meta.requested} validated · ${meta.domain} > ${meta.skill} · ${meta.difficulty}`;

    cardsEl.innerHTML = questions.map((q, i) => `
        <div class="q-card" data-idx="${i}">
            <div class="q-card-header">
                <span class="q-num">Question ${i + 1}</span>
                <span class="q-diff ${q.difficulty}">${q.difficulty}</span>
            </div>
            <div class="q-stem">${escHtml(q.stem)}</div>
            <div class="q-options">
                ${['A','B','C','D'].map(letter => `
                    <div class="q-opt ${q.correct_answer?.toUpperCase() === letter ? 'correct' : ''}">
                        <span class="q-opt-letter">${letter}</span>
                        <span>${escHtml(q.options?.[letter] || '')}</span>
                    </div>
                `).join('')}
            </div>
            <div class="q-explanation">
                <div class="q-explanation-title">Explanation</div>
                <div class="q-explanation-text">${escHtml(q.explanation || '')}</div>
            </div>
            ${q.sat_strategy_tip ? `
            <div class="q-card-footer">
                <span class="q-strategy">${escHtml(q.sat_strategy_tip)}</span>
                <div class="q-actions">
                    <button class="q-btn" onclick="editQuestion(${i})">Edit</button>
                    <button class="q-btn" onclick="removeQuestion(${i})">Remove</button>
                </div>
            </div>` : ''}
        </div>
    `).join('');

    // Render LaTeX
    cardsEl.querySelectorAll('.q-stem, .q-opt span, .q-explanation-text').forEach(el => {
        renderLatex(el);
    });
}

function renderLatex(el) {
    let html = el.innerHTML;
    // Display math \[ ... \]
    html = html.replace(/\\\[(.*?)\\\]/gs, (_, tex) => {
        try { return katex.renderToString(tex.trim(), { displayMode: true, throwOnError: false }); }
        catch { return _; }
    });
    // Inline math \( ... \)
    html = html.replace(/\\\((.*?)\\\)/g, (_, tex) => {
        try { return katex.renderToString(tex.trim(), { displayMode: false, throwOnError: false }); }
        catch { return _; }
    });
    el.innerHTML = html;
}

function escHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function regenerate() {
    form.dispatchEvent(new Event('submit'));
}

function removeQuestion(idx) {
    lastGenerated.splice(idx, 1);
    renderQuestions(lastGenerated, { validated: lastGenerated.length, requested: lastGenerated.length, domain: '—', skill: '—', difficulty: '—' });
}

function editQuestion(idx) {
    // Open inline editor (simplified — could be a modal)
    const card = cardsEl.querySelector(`[data-idx="${idx}"]`);
    const q = lastGenerated[idx];
    const stem = prompt('Edit question stem:', q.stem);
    if (stem !== null) {
        q.stem = stem;
        renderQuestions(lastGenerated, { validated: lastGenerated.length, requested: lastGenerated.length, domain: '—', skill: '—', difficulty: '—' });
    }
}

async function saveAllToQuiz() {
    const quizId = parseInt(document.getElementById('quizId').value);
    if (!quizId) { alert('Select a quiz from the dropdown first.'); return; }
    if (!lastGenerated.length) return;

    try {
        const resp = await fetch('/api/generate-questions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                quiz_id: quizId,
                save: true,
                questions: lastGenerated,
                domain: domainSel.value,
                skill: skillSel.value,
                difficulty: document.querySelector('input[name="difficulty"]:checked')?.value || 'medium',
                count: 0, // skip generation, just save
            }),
        });
        const data = await resp.json();
        alert(`Saved ${data.saved || lastGenerated.length} questions to quiz!`);
    } catch (err) {
        alert('Error saving: ' + err.message);
    }
}
</script>

</body>
</html>
