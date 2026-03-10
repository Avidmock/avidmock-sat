<?php
/**
 * R&W Practice — AI-powered Reading & Writing section practice with instant scoring.
 * Covers all 4 R&W domains: Standard English Conventions, Expression of Ideas,
 * Information & Ideas, Craft and Structure.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/AIEssayScorer.php';

Auth::requireStudent();
$userId   = (int) $_SESSION['user_id'];
$patterns = AIEssayScorer::getPatternAnalysis($userId);
$types    = AIEssayScorer::QUESTION_TYPES;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>R&W Practice — Avidmock SAT</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700;9..40,800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{--dk:#143230;--ac:#1fe290;--ac2:#17c87a;--tx:#1a1a2e;--bg:#f7faf9;--card:#fff;--border:rgba(20,50,48,.08);--muted:rgba(26,26,46,.5);--coral:#FF6B6B;--amber:#FFB347;--purple:#8B5CF6}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--tx);min-height:100vh}

.rw-wrap{max-width:960px;margin:0 auto;padding:2rem 1.5rem}

/* Header */
.rw-header{text-align:center;margin-bottom:2.5rem}
.rw-title{font:800 2rem/1.2 'DM Sans';color:var(--dk)}
.rw-subtitle{color:var(--muted);margin-top:.35rem;max-width:550px;margin-left:auto;margin-right:auto}
.rw-back{display:inline-flex;align-items:center;gap:.3rem;color:var(--ac2);text-decoration:none;font:500 .85rem 'DM Sans';margin-bottom:1rem}

/* Stats bar */
.rw-stats{display:flex;justify-content:center;gap:2rem;margin-bottom:2.5rem;flex-wrap:wrap}
.rw-stat{text-align:center}
.rw-stat-val{font:700 1.5rem 'DM Mono';color:var(--dk)}
.rw-stat-label{font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}

/* Domain cards */
.rw-domains{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:2.5rem}
.rw-domain{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:1.25rem;cursor:pointer;transition:all .2s;position:relative;overflow:hidden}
.rw-domain:hover{border-color:var(--ac);transform:translateY(-2px);box-shadow:0 4px 20px rgba(31,226,144,.08)}
.rw-domain.active{border-color:var(--ac);background:rgba(31,226,144,.03)}
.rw-domain::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--ac);transform:scaleX(0);transition:transform .2s}
.rw-domain.active::before{transform:scaleX(1)}
.rw-domain-icon{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:.75rem;font-size:1.2rem}
.rw-domain-icon.grammar{background:rgba(31,226,144,.1);color:var(--ac2)}
.rw-domain-icon.rhetoric{background:rgba(139,92,246,.1);color:var(--purple)}
.rw-domain-icon.comprehension{background:rgba(255,179,71,.1);color:var(--amber)}
.rw-domain-icon.vocabulary{background:rgba(255,107,107,.1);color:var(--coral)}
.rw-domain h3{font:600 .9rem 'DM Sans';color:var(--dk);margin-bottom:.2rem}
.rw-domain p{font-size:.78rem;color:var(--muted)}

/* Practice area */
.rw-practice{display:none}
.rw-practice.active{display:block}

.rw-question-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:2rem;margin-bottom:1.5rem}
.rw-passage{background:rgba(20,50,48,.03);border-left:3px solid var(--dk);padding:1.25rem;border-radius:0 10px 10px 0;margin-bottom:1.5rem;font-size:.92rem;line-height:1.8;color:var(--tx)}
.rw-question-text{font:600 1rem 'DM Sans';color:var(--dk);margin-bottom:1.25rem;line-height:1.6}

/* Options */
.rw-options{display:flex;flex-direction:column;gap:.5rem;margin-bottom:1.5rem}
.rw-option{display:flex;align-items:flex-start;gap:.75rem;padding:.75rem 1rem;border:1px solid var(--border);border-radius:10px;cursor:pointer;transition:all .15s;font-size:.92rem}
.rw-option:hover{border-color:var(--ac);background:rgba(31,226,144,.02)}
.rw-option.selected{border-color:var(--ac);background:rgba(31,226,144,.06)}
.rw-option.correct{border-color:var(--ac);background:rgba(31,226,144,.08)}
.rw-option.wrong{border-color:var(--coral);background:rgba(255,107,107,.06)}
.rw-option-letter{font:700 .85rem 'DM Mono';color:var(--ac2);min-width:1.5rem}

.rw-submit-area{display:flex;gap:.75rem;align-items:center;flex-wrap:wrap}
.btn{display:inline-flex;align-items:center;gap:.4rem;padding:.65rem 1.5rem;border:none;border-radius:10px;font:600 .88rem 'DM Sans';cursor:pointer;transition:all .15s}
.btn-primary{background:var(--ac);color:#071512}
.btn-primary:hover{background:var(--ac2);transform:translateY(-1px)}
.btn-primary:disabled{opacity:.5;cursor:not-allowed;transform:none}
.btn-outline{background:transparent;border:1px solid var(--border);color:var(--dk)}
.btn-outline:hover{border-color:var(--ac)}

/* Feedback panel */
.rw-feedback{display:none;background:var(--card);border:1px solid var(--border);border-radius:16px;padding:2rem;margin-top:1.5rem}
.rw-feedback.show{display:block;animation:slideUp .3s ease}
@keyframes slideUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}

.rw-score-badge{display:inline-flex;align-items:center;gap:.4rem;font:700 1.2rem 'DM Mono';padding:.4rem 1rem;border-radius:10px;margin-bottom:1rem}
.rw-score-badge.high{background:rgba(31,226,144,.1);color:var(--ac2)}
.rw-score-badge.mid{background:rgba(255,179,71,.1);color:#d49300}
.rw-score-badge.low{background:rgba(255,107,107,.1);color:var(--coral)}

.rw-feedback h3{font:700 .95rem 'DM Sans';color:var(--dk);margin:1rem 0 .5rem}
.rw-feedback-text{font-size:.9rem;line-height:1.7;color:var(--tx)}
.rw-feedback-list{list-style:none;padding:0}
.rw-feedback-list li{padding:.4rem 0;font-size:.88rem;display:flex;align-items:flex-start;gap:.4rem}
.rw-feedback-list li::before{content:'';width:6px;height:6px;border-radius:50%;margin-top:.45rem;flex-shrink:0}
.rw-strengths li::before{background:var(--ac2)}
.rw-weaknesses li::before{background:var(--coral)}

.rw-grammar-table{width:100%;border-collapse:collapse;margin-top:.75rem;font-size:.85rem}
.rw-grammar-table th{text-align:left;font:600 .72rem 'DM Mono';color:var(--muted);text-transform:uppercase;padding:.5rem .75rem;border-bottom:1px solid var(--border)}
.rw-grammar-table td{padding:.5rem .75rem;border-bottom:1px solid var(--border)}
.rw-grammar-table .original{color:var(--coral);text-decoration:line-through}
.rw-grammar-table .corrected{color:var(--ac2)}

.rw-tip{background:rgba(31,226,144,.04);border:1px solid rgba(31,226,144,.15);border-radius:10px;padding:1rem 1.25rem;margin-top:1rem;font-size:.88rem}
.rw-tip strong{color:var(--ac2)}

/* Loading */
.rw-loading{text-align:center;padding:2rem;display:none}
.rw-loading.show{display:block}
.rw-loading-dots{display:flex;justify-content:center;gap:.3rem;margin-bottom:1rem}
.rw-loading-dots span{width:8px;height:8px;border-radius:50%;background:var(--ac);animation:bounce .8s ease infinite}
.rw-loading-dots span:nth-child(2){animation-delay:.15s}
.rw-loading-dots span:nth-child(3){animation-delay:.3s}
@keyframes bounce{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}

@media(max-width:600px){
    .rw-wrap{padding:1rem}
    .rw-question-card{padding:1.25rem}
    .rw-stats{gap:1rem}
}
</style>
</head>
<body>

<div class="rw-wrap">

    <a href="/index.php" class="rw-back">
        <svg viewBox="0 0 24 24" width="16" height="16"><path d="M19 12H5M12 19l-7-7 7-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        Back to Dashboard
    </a>

    <div class="rw-header">
        <h1 class="rw-title">Reading & Writing Practice</h1>
        <p class="rw-subtitle">AI-powered practice for the SAT R&W section. Get instant scoring, feedback, and targeted improvement tips.</p>
    </div>

    <!-- Stats -->
    <div class="rw-stats">
        <div class="rw-stat">
            <div class="rw-stat-val"><?= $patterns['submissions'] ?? 0 ?></div>
            <div class="rw-stat-label">Responses Scored</div>
        </div>
        <div class="rw-stat">
            <div class="rw-stat-val"><?= $patterns['avg_score'] ?? '—' ?>/4</div>
            <div class="rw-stat-label">Average Score</div>
        </div>
        <div class="rw-stat">
            <div class="rw-stat-val"><?= ucfirst($patterns['score_trend'] ?? '—') ?></div>
            <div class="rw-stat-label">Trend</div>
        </div>
    </div>

    <!-- Domain Selection -->
    <h2 style="font:700 1.1rem 'DM Sans';color:var(--dk);margin-bottom:1rem">Choose a Domain</h2>
    <div class="rw-domains">
        <?php foreach ($types as $key => $type): ?>
        <div class="rw-domain" data-type="<?= $key ?>" onclick="selectDomain('<?= $key ?>')">
            <div class="rw-domain-icon <?= $key ?>">
                <?php if ($key === 'grammar'): ?>ABC
                <?php elseif ($key === 'rhetoric'): ?>
                <?php elseif ($key === 'comprehension'): ?>
                <?php else: ?>Aa<?php endif; ?>
            </div>
            <h3><?= htmlspecialchars($type['label']) ?></h3>
            <p><?= count($type['skills']) ?> skills · <?= ucfirst($key) ?></p>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Practice Area -->
    <div class="rw-practice" id="practiceArea">
        <div class="rw-loading" id="questionLoading">
            <div class="rw-loading-dots"><span></span><span></span><span></span></div>
            <p style="color:var(--muted);font-size:.88rem">Generating a practice question with AI…</p>
        </div>

        <div id="questionArea" style="display:none">
            <div class="rw-question-card">
                <div class="rw-passage" id="passageText"></div>
                <div class="rw-question-text" id="questionText"></div>
                <div class="rw-options" id="optionsArea"></div>
                <div class="rw-submit-area">
                    <button class="btn btn-primary" id="submitBtn" onclick="submitAnswer()" disabled>Check Answer</button>
                    <button class="btn btn-outline" onclick="loadNewQuestion()">Skip — New Question</button>
                </div>
            </div>
        </div>

        <div class="rw-feedback" id="feedbackPanel">
            <div id="feedbackContent"></div>
        </div>

        <div style="text-align:center;margin-top:1.5rem" id="nextArea" style="display:none">
            <button class="btn btn-primary" onclick="loadNewQuestion()">Next Question</button>
        </div>
    </div>

</div>

<script>
const types = <?= json_encode($types) ?>;
let currentType = '';
let currentQuestion = null;
let selectedAnswer = '';

function selectDomain(type) {
    currentType = type;
    document.querySelectorAll('.rw-domain').forEach(el => el.classList.toggle('active', el.dataset.type === type));
    document.getElementById('practiceArea').classList.add('active');
    loadNewQuestion();
}

async function loadNewQuestion() {
    const loading = document.getElementById('questionLoading');
    const qArea   = document.getElementById('questionArea');
    const feedback = document.getElementById('feedbackPanel');

    loading.classList.add('show');
    qArea.style.display = 'none';
    feedback.classList.remove('show');
    document.getElementById('nextArea').style.display = 'none';
    selectedAnswer = '';
    document.getElementById('submitBtn').disabled = true;

    // Pick a random skill from the type
    const skills = types[currentType]?.skills || [];
    const skill  = skills[Math.floor(Math.random() * skills.length)];

    try {
        const resp = await fetch(`/api/essay-scorer.php?action=prompt&type=${currentType}&skill=${skill}&difficulty=medium`);
        const data = await resp.json();

        if (data.error) {
            loading.classList.remove('show');
            qArea.innerHTML = `<p style="color:var(--coral);text-align:center;padding:2rem">${data.error}</p>`;
            qArea.style.display = 'block';
            return;
        }

        currentQuestion = data;
        document.getElementById('passageText').textContent = data.passage || '';
        document.getElementById('questionText').textContent = data.question || '';

        const optionsHtml = ['A','B','C','D'].map(letter => `
            <div class="rw-option" data-letter="${letter}" onclick="selectOption('${letter}')">
                <span class="rw-option-letter">${letter}</span>
                <span>${escHtml(data.options?.[letter] || '')}</span>
            </div>
        `).join('');
        document.getElementById('optionsArea').innerHTML = optionsHtml;

        loading.classList.remove('show');
        qArea.style.display = 'block';
    } catch (err) {
        loading.classList.remove('show');
        alert('Failed to load question: ' + err.message);
    }
}

function selectOption(letter) {
    selectedAnswer = letter;
    document.querySelectorAll('.rw-option').forEach(el => {
        el.classList.toggle('selected', el.dataset.letter === letter);
    });
    document.getElementById('submitBtn').disabled = false;
}

async function submitAnswer() {
    if (!selectedAnswer || !currentQuestion) return;
    document.getElementById('submitBtn').disabled = true;

    // Show correct/wrong
    const correct = currentQuestion.correct_answer?.toUpperCase();
    document.querySelectorAll('.rw-option').forEach(el => {
        const letter = el.dataset.letter;
        if (letter === correct) el.classList.add('correct');
        if (letter === selectedAnswer && letter !== correct) el.classList.add('wrong');
    });

    // Show feedback
    const isCorrect = selectedAnswer === correct;
    const feedbackPanel = document.getElementById('feedbackPanel');

    const scoreClass = isCorrect ? 'high' : 'low';
    const scoreText  = isCorrect ? 'Correct!' : 'Incorrect';

    let html = `
        <div class="rw-score-badge ${scoreClass}">${scoreText}</div>
    `;

    if (currentQuestion.explanation) {
        html += `
            <h3>Explanation</h3>
            <div class="rw-feedback-text">${escHtml(currentQuestion.explanation)}</div>
        `;
    }

    // Get AI feedback by scoring the response
    try {
        const resp = await fetch('/api/essay-scorer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                passage: currentQuestion.passage || '',
                question: currentQuestion.question || '',
                response: selectedAnswer,
                type: currentType,
                action: 'score',
            }),
        });
        const aiData = await resp.json();

        if (aiData.tip) {
            html += `<div class="rw-tip"><strong>Pro Tip:</strong> ${escHtml(aiData.tip)}</div>`;
        }
    } catch {}

    document.getElementById('feedbackContent').innerHTML = html;
    feedbackPanel.classList.add('show');
    document.getElementById('nextArea').style.display = 'block';
}

function escHtml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
</script>

</body>
</html>
