<?php
/**
 * /learn/quiz.php — Lesson Quiz Page
 * my.sat.avidmock.com/learn/quiz.php?lesson=function-notation-and-interpretation
 * OR: /learn/quiz.php?quiz_id=5
 *
 * Full-page quiz experience with:
 * - Progress bar with question count
 * - Multiple choice with instant feedback
 * - Timer per question
 * - Score tracking via /api/check-answer.php
 * - Redirect to /learn/results.php on completion
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Quiz.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Question.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/StudyStreak.php';

Auth::requireStudentOrRedirect();
$userId    = (int) $_SESSION['user_id'];
$user      = User::findById($userId);
$firstName = explode(' ', $user['name'] ?? 'Student')[0];

/* ── Resolve quiz ── */
$quiz      = null;
$questions = [];
$quizId    = 0;

if (!empty($_GET['quiz_id'])) {
    $quizId = (int) $_GET['quiz_id'];
    $quiz   = Quiz::getById_static($quizId);
} elseif (!empty($_GET['lesson'])) {
    $lessonSlug = preg_replace('/[^a-z0-9\-]/', '', strtolower($_GET['lesson']));
    $quizObj    = new Quiz($pdo);
    $quiz       = $quizObj->getByLesson($lessonSlug);
    if ($quiz) $quizId = $quiz['id'];
}

if (!$quiz) {
    header('Location: /learn/');
    exit;
}

$questions = Question::getByQuiz($quizId);
$totalQ    = count($questions);

if ($totalQ === 0) {
    header('Location: /learn/?error=no_questions');
    exit;
}

/* ── Create quiz attempt ── */
$attemptId = 0;
try {
    $stmt = $pdo->prepare(
        "INSERT INTO sat_quiz_attempts (user_id, quiz_id, status, started_at) VALUES (?, ?, 'in_progress', NOW())"
    );
    $stmt->execute([$userId, $quizId]);
    $attemptId = (int) $pdo->lastInsertId();
} catch (Throwable $e) {
    error_log('[learn/quiz.php] attempt insert: ' . $e->getMessage());
}

/* ── Update streak ── */
try { StudyStreak::update($userId); } catch (Throwable) {}

$quizTitle  = htmlspecialchars($quiz['title'] ?? 'Quiz', ENT_QUOTES);
$timeLimit  = (int) ($quiz['time_limit'] ?? ($totalQ * 90)); // default 90s per question

/* ── Prepare questions JSON (strip correct answers) ── */
$clientQuestions = [];
foreach ($questions as $i => $q) {
    $clientQuestions[] = [
        'id'       => $q['id'],
        'position' => $i + 1,
        'stem'     => $q['stem'],
        'type'     => $q['type'] ?? 'mcq',
        'options'  => [
            'A' => $q['option_a'] ?? '',
            'B' => $q['option_b'] ?? '',
            'C' => $q['option_c'] ?? '',
            'D' => $q['option_d'] ?? '',
        ],
    ];
}

$activePage  = 'learn';
$topbarTitle = $quizTitle;
$topbarSub   = "{$totalQ} questions";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $quizTitle ?> — Avidmock SAT</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/KaTeX/0.16.9/katex.min.css">
<style>
:root {
    --dk:#143230; --dk2:#1a3f3c;
    --ac:#1fe290; --ac2:#17c87a;
    --tx:#1a1a2e; --tx2:#4a4a5a; --tx3:#8a8a9a;
    --bg:#f7faf9; --bg2:#ffffff; --bd:#e2ebe9;
    --err:#e74c3c; --warn:#f39c12; --ok:#10b981;
    --pr:#5b28a4;
    --ff:'DM Sans',-apple-system,BlinkMacSystemFont,sans-serif;
    --fm:'DM Mono',monospace;
    --r:14px; --r-sm:10px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{font-size:16px}
body{font-family:var(--ff);-webkit-font-smoothing:antialiased;background:var(--bg);color:var(--tx);min-height:100vh}

/* ── Quiz Shell ── */
.quiz-shell{max-width:740px;margin:0 auto;padding:24px 20px 100px}

/* ── Top Bar ── */
.quiz-top{display:flex;align-items:center;gap:16px;margin-bottom:32px;padding:16px 20px;background:var(--bg2);border-radius:var(--r);border:1px solid var(--bd)}
.quiz-top .back-btn{display:flex;align-items:center;gap:6px;color:var(--tx3);font-size:.875rem;text-decoration:none;transition:color .2s}
.quiz-top .back-btn:hover{color:var(--tx)}
.quiz-top .back-btn svg{width:18px;height:18px}
.quiz-title{flex:1;font-size:1.1rem;font-weight:600;color:var(--tx)}
.quiz-timer{font-family:var(--fm);font-size:.9rem;font-weight:500;color:var(--dk);background:rgba(31,226,144,.12);padding:6px 12px;border-radius:8px}
.quiz-timer.warning{color:var(--warn);background:rgba(243,156,18,.12)}
.quiz-timer.danger{color:var(--err);background:rgba(231,76,60,.12)}

/* ── Progress ── */
.progress-bar{height:6px;background:var(--bd);border-radius:3px;margin-bottom:32px;overflow:hidden}
.progress-fill{height:100%;background:linear-gradient(90deg,var(--ac),var(--ac2));border-radius:3px;transition:width .4s ease}

.progress-text{display:flex;justify-content:space-between;margin-bottom:8px;font-size:.8rem;color:var(--tx3);font-weight:500}

/* ── Question Card ── */
.question-card{background:var(--bg2);border-radius:var(--r);padding:32px 28px;border:1px solid var(--bd);margin-bottom:24px}
.q-number{font-size:.75rem;font-weight:600;color:var(--ac2);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px}
.q-stem{font-size:1.05rem;line-height:1.6;color:var(--tx);margin-bottom:24px}
.q-stem code{font-family:var(--fm);background:rgba(91,40,164,.08);padding:2px 6px;border-radius:4px;font-size:.9em}

/* ── Options ── */
.options-grid{display:flex;flex-direction:column;gap:10px}
.option-btn{display:flex;align-items:flex-start;gap:14px;padding:14px 18px;background:var(--bg);border:2px solid var(--bd);border-radius:var(--r-sm);cursor:pointer;transition:all .2s;text-align:left;font-family:var(--ff);font-size:.95rem;color:var(--tx);line-height:1.5}
.option-btn:hover{border-color:var(--pr);background:rgba(91,40,164,.04)}
.option-btn.selected{border-color:var(--pr);background:rgba(91,40,164,.08)}
.option-btn.correct{border-color:var(--ok);background:rgba(16,185,129,.08)}
.option-btn.incorrect{border-color:var(--err);background:rgba(231,76,60,.08)}
.option-btn.disabled{pointer-events:none;opacity:.7}
.opt-letter{width:28px;height:28px;display:flex;align-items:center;justify-content:center;background:var(--bg2);border:2px solid var(--bd);border-radius:8px;font-weight:700;font-size:.8rem;color:var(--tx3);flex-shrink:0;transition:all .2s}
.option-btn.selected .opt-letter{background:var(--pr);color:#fff;border-color:var(--pr)}
.option-btn.correct .opt-letter{background:var(--ok);color:#fff;border-color:var(--ok)}
.option-btn.incorrect .opt-letter{background:var(--err);color:#fff;border-color:var(--err)}
.opt-text{flex:1;padding-top:2px}

/* ── Explanation ── */
.explanation{display:none;margin-top:20px;padding:18px;background:rgba(91,40,164,.04);border:1px solid rgba(91,40,164,.15);border-radius:var(--r-sm);font-size:.9rem;line-height:1.6;color:var(--tx2)}
.explanation.show{display:block;animation:fadeIn .3s ease}
.explanation strong{color:var(--tx);font-weight:600}

/* ── Navigation ── */
.quiz-nav{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:24px}
.nav-btn{padding:12px 28px;border-radius:var(--r-sm);font-weight:600;font-size:.9rem;border:none;cursor:pointer;transition:all .2s;font-family:var(--ff)}
.nav-btn.secondary{background:var(--bg);border:2px solid var(--bd);color:var(--tx2)}
.nav-btn.secondary:hover{border-color:var(--tx3)}
.nav-btn.primary{background:var(--ac);color:var(--dk);border:none}
.nav-btn.primary:hover{background:var(--ac2)}
.nav-btn:disabled{opacity:.4;cursor:not-allowed}

/* ── Score Summary (shown after last question) ── */
.score-summary{display:none;text-align:center;padding:48px 24px;background:var(--bg2);border-radius:var(--r);border:1px solid var(--bd)}
.score-summary.show{display:block;animation:fadeIn .4s ease}
.score-ring{width:140px;height:140px;margin:0 auto 24px;position:relative}
.score-ring svg{width:100%;height:100%;transform:rotate(-90deg)}
.score-ring .bg{stroke:var(--bd);stroke-width:8;fill:none}
.score-ring .fill{stroke:var(--ac);stroke-width:8;fill:none;stroke-linecap:round;stroke-dasharray:377;stroke-dashoffset:377;transition:stroke-dashoffset 1.2s ease}
.score-value{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center}
.score-value .pct{font-size:2.2rem;font-weight:800;color:var(--tx)}
.score-value .label{font-size:.8rem;color:var(--tx3);font-weight:500}
.score-msg{font-size:1.1rem;font-weight:600;color:var(--tx);margin-bottom:8px}
.score-detail{font-size:.9rem;color:var(--tx2);margin-bottom:24px}

@keyframes fadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}

/* ── AI Help Button ── */
.ai-help-btn{position:fixed;bottom:24px;right:24px;padding:12px 20px;background:var(--pr);color:#fff;border:none;border-radius:40px;font-family:var(--ff);font-weight:600;font-size:.85rem;cursor:pointer;box-shadow:0 4px 20px rgba(91,40,164,.3);transition:all .2s;z-index:100;display:flex;align-items:center;gap:8px}
.ai-help-btn:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(91,40,164,.4)}
.ai-help-btn svg{width:18px;height:18px}

@media(max-width:600px){
    .quiz-shell{padding:16px 14px 90px}
    .question-card{padding:22px 18px}
    .quiz-top{flex-wrap:wrap;gap:10px}
}
</style>
</head>
<body>

<div class="quiz-shell">
    <!-- Top Bar -->
    <div class="quiz-top">
        <a href="javascript:history.back()" class="back-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Back
        </a>
        <div class="quiz-title"><?= $quizTitle ?></div>
        <div class="quiz-timer" id="timer">00:00</div>
    </div>

    <!-- Progress -->
    <div class="progress-text">
        <span id="progress-label">Question 1 of <?= $totalQ ?></span>
        <span id="progress-score">0 correct</span>
    </div>
    <div class="progress-bar">
        <div class="progress-fill" id="progress-fill" style="width:<?= round(100 / $totalQ, 1) ?>%"></div>
    </div>

    <!-- Question Container -->
    <div id="question-container">
        <!-- Rendered by JS -->
    </div>

    <!-- Score Summary -->
    <div class="score-summary" id="score-summary">
        <div class="score-ring">
            <svg viewBox="0 0 128 128">
                <circle class="bg" cx="64" cy="64" r="60"/>
                <circle class="fill" id="score-ring-fill" cx="64" cy="64" r="60"/>
            </svg>
            <div class="score-value">
                <div class="pct" id="score-pct">0%</div>
                <div class="label">Score</div>
            </div>
        </div>
        <div class="score-msg" id="score-msg">Well done!</div>
        <div class="score-detail" id="score-detail">0 of <?= $totalQ ?> correct</div>
        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
            <a href="/learn/results.php?attempt=<?= $attemptId ?>" class="nav-btn primary">View Detailed Results</a>
            <a href="/learn/review.php?attempt=<?= $attemptId ?>" class="nav-btn secondary">Review Answers</a>
        </div>
    </div>

    <!-- Navigation -->
    <div class="quiz-nav" id="quiz-nav">
        <button class="nav-btn secondary" id="btn-prev" disabled onclick="prevQuestion()">Previous</button>
        <button class="nav-btn primary" id="btn-next" onclick="nextQuestion()">Check Answer</button>
    </div>
</div>

<!-- AI Help Button -->
<a href="/ai-tutor/?subject=math" class="ai-help-btn" target="_blank">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a7 7 0 0 1 7 7c0 2.38-1.19 4.47-3 5.74V17a2 2 0 0 1-2 2H10a2 2 0 0 1-2-2v-2.26C6.19 13.47 5 11.38 5 9a7 7 0 0 1 7-7z"/><path d="M10 22h4"/></svg>
    Ask AI Tutor
</a>

<script src="https://cdnjs.cloudflare.com/ajax/libs/KaTeX/0.16.9/katex.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/KaTeX/0.16.9/contrib/auto-render.min.js"></script>
<script>
const QUESTIONS   = <?= json_encode($clientQuestions, JSON_UNESCAPED_UNICODE) ?>;
const ATTEMPT_ID  = <?= $attemptId ?>;
const QUIZ_ID     = <?= $quizId ?>;
const TOTAL_Q     = <?= $totalQ ?>;
const TIME_LIMIT  = <?= $timeLimit ?>;
const CSRF_TOKEN  = '<?= csrf_token() ?>';

let currentQ     = 0;
let correctCount = 0;
let answers      = {};  // { questionId: { answer, correct, explanation } }
let answered     = {};  // { questionId: true }
let startTime    = Date.now();
let qStartTime   = Date.now();
let timerInterval;

/* ── Timer ── */
function startTimer() {
    const timerEl = document.getElementById('timer');
    timerInterval = setInterval(() => {
        const elapsed = Math.floor((Date.now() - startTime) / 1000);
        const mins = String(Math.floor(elapsed / 60)).padStart(2, '0');
        const secs = String(elapsed % 60).padStart(2, '0');
        timerEl.textContent = mins + ':' + secs;

        if (TIME_LIMIT > 0) {
            const remaining = TIME_LIMIT - elapsed;
            if (remaining <= 60) timerEl.className = 'quiz-timer danger';
            else if (remaining <= 180) timerEl.className = 'quiz-timer warning';
            if (remaining <= 0) { clearInterval(timerInterval); autoSubmit(); }
        }
    }, 1000);
}

/* ── Render Question ── */
function renderQuestion(idx) {
    const q = QUESTIONS[idx];
    const container = document.getElementById('question-container');
    const isAnswered = !!answered[q.id];
    const savedAnswer = answers[q.id];

    let html = `<div class="question-card">
        <div class="q-number">Question ${q.position} of ${TOTAL_Q}</div>
        <div class="q-stem">${q.stem}</div>
        <div class="options-grid">`;

    ['A','B','C','D'].forEach(letter => {
        if (!q.options[letter]) return;
        let cls = 'option-btn';
        if (isAnswered) {
            cls += ' disabled';
            if (savedAnswer && savedAnswer.answer === letter) {
                cls += savedAnswer.correct ? ' correct' : ' incorrect';
            }
            if (savedAnswer && savedAnswer.correctAnswer === letter) {
                cls += ' correct';
            }
        } else if (answers[q.id]?.selected === letter) {
            cls += ' selected';
        }
        html += `<button class="${cls}" data-letter="${letter}" onclick="selectOption(${q.id},'${letter}',this)">
            <span class="opt-letter">${letter}</span>
            <span class="opt-text">${q.options[letter]}</span>
        </button>`;
    });

    html += `</div>`;

    if (isAnswered && savedAnswer?.explanation) {
        html += `<div class="explanation show"><strong>${savedAnswer.correct ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:3px"><polyline points="20 6 9 17 4 12"/></svg>Correct!' : '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:3px"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>Incorrect.'}</strong> ${savedAnswer.explanation}</div>`;
    }

    html += `</div>`;
    container.innerHTML = html;

    // Render math with KaTeX
    if (typeof renderMathInElement === 'function') {
        renderMathInElement(container, {
            delimiters: [
                {left: '$$', right: '$$', display: true},
                {left: '$', right: '$', display: false},
                {left: '\\(', right: '\\)', display: false},
                {left: '\\[', right: '\\]', display: true},
            ],
            throwOnError: false,
        });
    }

    // Update progress
    document.getElementById('progress-label').textContent = `Question ${idx + 1} of ${TOTAL_Q}`;
    document.getElementById('progress-fill').style.width = `${((idx + 1) / TOTAL_Q) * 100}%`;
    document.getElementById('progress-score').textContent = `${correctCount} correct`;

    // Update nav buttons
    document.getElementById('btn-prev').disabled = (idx === 0);
    const nextBtn = document.getElementById('btn-next');
    if (isAnswered) {
        nextBtn.textContent = idx === TOTAL_Q - 1 ? 'See Results' : 'Next →';
    } else {
        nextBtn.textContent = 'Check Answer';
        nextBtn.disabled = !answers[q.id]?.selected;
    }

    qStartTime = Date.now();
}

/* ── Select Option ── */
function selectOption(questionId, letter, btn) {
    if (answered[questionId]) return;

    // Clear previous selection
    btn.closest('.options-grid').querySelectorAll('.option-btn').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');

    answers[questionId] = answers[questionId] || {};
    answers[questionId].selected = letter;

    document.getElementById('btn-next').disabled = false;
}

/* ── Check Answer via API ── */
async function checkAnswer(questionId, userAnswer) {
    const timeSpent = Math.floor((Date.now() - qStartTime) / 1000);

    try {
        const resp = await fetch('/api/check-answer.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF_TOKEN,
            },
            body: JSON.stringify({
                attempt_id:  ATTEMPT_ID,
                question_id: questionId,
                user_answer: userAnswer,
                time_spent:  timeSpent,
            }),
        });

        const data = await resp.json();

        answers[questionId] = {
            ...answers[questionId],
            answer:        userAnswer,
            correct:       !!data.correct,
            correctAnswer: data.correct_answer || '',
            explanation:   data.explanation || '',
        };

        answered[questionId] = true;
        if (data.correct) correctCount++;

        renderQuestion(currentQ);

    } catch (err) {
        console.error('Check answer failed:', err);
    }
}

/* ── Next / Check ── */
function nextQuestion() {
    const q = QUESTIONS[currentQ];

    if (!answered[q.id]) {
        // Check the answer
        if (answers[q.id]?.selected) {
            checkAnswer(q.id, answers[q.id].selected);
        }
        return;
    }

    // Move to next or show results
    if (currentQ < TOTAL_Q - 1) {
        currentQ++;
        renderQuestion(currentQ);
    } else {
        showResults();
    }
}

function prevQuestion() {
    if (currentQ > 0) {
        currentQ--;
        renderQuestion(currentQ);
    }
}

/* ── Auto-submit on timeout ── */
async function autoSubmit() {
    try {
        await fetch('/api/submit-quiz.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
            body: JSON.stringify({
                attempt_id: ATTEMPT_ID,
                quiz_id:    QUIZ_ID,
                time_spent: TIME_LIMIT,
                auto_submit: true,
            }),
        });
    } catch (e) { console.error(e); }
    showResults();
}

/* ── Show Results ── */
function showResults() {
    clearInterval(timerInterval);
    document.getElementById('question-container').style.display = 'none';
    document.getElementById('quiz-nav').style.display = 'none';

    const pct = Math.round((correctCount / TOTAL_Q) * 100);
    const summary = document.getElementById('score-summary');
    summary.classList.add('show');

    document.getElementById('score-pct').textContent = pct + '%';
    document.getElementById('score-detail').textContent = `${correctCount} of ${TOTAL_Q} correct`;

    // Animated ring
    const circumference = 2 * Math.PI * 60; // r=60
    const offset = circumference - (pct / 100) * circumference;
    const ring = document.getElementById('score-ring-fill');
    ring.style.strokeDasharray = circumference;
    setTimeout(() => { ring.style.strokeDashoffset = offset; }, 100);

    // Change ring color based on score
    if (pct >= 80) { ring.style.stroke = '#10b981'; }
    else if (pct >= 60) { ring.style.stroke = '#1fe290'; }
    else if (pct >= 40) { ring.style.stroke = '#f39c12'; }
    else { ring.style.stroke = '#e74c3c'; }

    // Message
    const msgs = pct >= 90 ? 'Outstanding!' : pct >= 70 ? 'Great work!' : pct >= 50 ? 'Good effort! Keep practicing.' : 'Keep going — review the explanations!';
    document.getElementById('score-msg').textContent = msgs;

    // Submit the quiz
    fetch('/api/submit-quiz.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
        body: JSON.stringify({
            attempt_id: ATTEMPT_ID,
            quiz_id:    QUIZ_ID,
            time_spent: Math.floor((Date.now() - startTime) / 1000),
        }),
    }).catch(e => console.error(e));
}

/* ── Keyboard shortcuts ── */
document.addEventListener('keydown', (e) => {
    const q = QUESTIONS[currentQ];
    if (!answered[q?.id]) {
        if (['a','b','c','d'].includes(e.key.toLowerCase())) {
            const letter = e.key.toUpperCase();
            const btn = document.querySelector(`[data-letter="${letter}"]`);
            if (btn) selectOption(q.id, letter, btn);
        }
    }
    if (e.key === 'Enter') nextQuestion();
});

/* ── Init ── */
startTimer();
renderQuestion(0);
</script>
</body>
</html>
