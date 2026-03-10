<?php
/**
 * Onboarding — 4-step new student setup flow.
 * Steps: welcome -> setup (test date, target score, study hours) -> diagnostic -> plan_preview
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Onboarding.php';

$user = Auth::requireStudent();
$userId    = (int)$_SESSION['user_id'];
$firstName = trim($user['first_name'] ?? 'Student');

// If already complete, go to dashboard
if (Onboarding::isComplete($userId)) {
    header('Location: /index.php');
    exit;
}

$obData    = Onboarding::getData($userId);
$step      = Onboarding::getCurrentStep($userId);
$progress  = Onboarding::getProgressPercent($userId);
$csrfToken = csrf_token();

// Handle POST submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals(csrf_token(), $_POST['_csrf'] ?? '')) {
    $action = $_POST['step'] ?? '';

    switch ($action) {
        case 'welcome':
            Onboarding::saveStep($userId, 'welcome', []);
            break;

        case 'setup':
            Onboarding::saveStep($userId, 'setup', [
                'test_date'    => $_POST['test_date'] ?? null,
                'target_score' => (int)($_POST['target_score'] ?? 1200),
                'study_hours'  => (float)($_POST['study_hours'] ?? 10),
            ]);
            break;

        case 'diagnostic':
            // Process diagnostic answers
            $answers = json_decode($_POST['answers'] ?? '[]', true);
            if (is_array($answers) && count($answers) > 0) {
                Onboarding::processDiagnostic($userId, $answers);
            } else {
                // Skip diagnostic
                Onboarding::saveStep($userId, 'diagnostic', []);
            }
            break;

        case 'plan_preview':
            Onboarding::saveStep($userId, 'plan_preview', []);
            header('Location: /index.php');
            exit;
    }

    header('Location: /onboarding/');
    exit;
}

// Diagnostic questions (mini-quiz)
$diagQuestions = [
    ['id' => 1, 'subject' => 'math', 'stem' => 'If 3x + 7 = 22, what is the value of x?', 'options' => ['3', '5', '7', '15'], 'correct' => 1],
    ['id' => 2, 'subject' => 'math', 'stem' => 'What is the slope of the line y = -2x + 5?', 'options' => ['5', '2', '-2', '-5'], 'correct' => 2],
    ['id' => 3, 'subject' => 'math', 'stem' => 'If f(x) = x&sup2; - 4, what is f(3)?', 'options' => ['5', '-1', '9', '13'], 'correct' => 0],
    ['id' => 4, 'subject' => 'math', 'stem' => 'A circle has radius 6. What is its area?', 'options' => ['12&pi;', '36&pi;', '6&pi;', '18&pi;'], 'correct' => 1],
    ['id' => 5, 'subject' => 'math', 'stem' => 'Simplify: (2x&sup3;)(3x&sup2;)', 'options' => ['6x&sup5;', '5x&sup5;', '6x&sup6;', '5x&sup6;'], 'correct' => 0],
    ['id' => 6, 'subject' => 'math', 'stem' => 'What is 15% of 240?', 'options' => ['24', '30', '36', '42'], 'correct' => 2],
    ['id' => 7, 'subject' => 'math', 'stem' => 'If a triangle has sides 3, 4, and 5, what type is it?', 'options' => ['Equilateral', 'Isosceles', 'Right', 'Obtuse'], 'correct' => 2],
    ['id' => 8, 'subject' => 'math', 'stem' => 'Solve: |2x - 6| = 10', 'options' => ['x = 8 or x = -2', 'x = 8 or x = 2', 'x = -8 or x = 2', 'x = 8'], 'correct' => 0],
    ['id' => 9, 'subject' => 'math', 'stem' => 'What is the median of {3, 7, 1, 9, 5}?', 'options' => ['3', '5', '7', '9'], 'correct' => 1],
    ['id' => 10, 'subject' => 'math', 'stem' => 'Factor: x&sup2; - 9', 'options' => ['(x-3)(x+3)', '(x-9)(x+1)', '(x-3)&sup2;', '(x+9)(x-1)'], 'correct' => 0],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Get Started — AvidMock SAT</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:ital,opsz,wght@0,9..40,300..800;1,9..40,300..800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --dk:#143230;--ac:#1fe290;--ac2:#17c87a;--tx:#1a1a2e;--bg:#f7faf9;
  --card:#ffffff;--card-border:rgba(20,50,48,.06);
  --shadow-sm:0 1px 3px rgba(20,50,48,.06);
  --shadow-md:0 4px 16px rgba(20,50,48,.08);
  --shadow-lg:0 8px 32px rgba(20,50,48,.10);
  --shadow-glow:0 0 24px rgba(31,226,144,.18);
  --radius:16px;--radius-sm:10px;--radius-xs:6px;
  --font:'DM Sans',system-ui,sans-serif;
  --mono:'DM Mono','Fira Code',monospace;
  --transition:cubic-bezier(.4,0,.2,1);
}
html{font-size:16px;-webkit-font-smoothing:antialiased}
body{font-family:var(--font);color:var(--tx);background:var(--bg);min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px}

/* Progress bar */
.progress-bar{position:fixed;top:0;left:0;width:100%;height:4px;background:rgba(20,50,48,.06);z-index:100}
.progress-fill{height:100%;background:var(--ac);transition:width .5s var(--transition);border-radius:0 2px 2px 0}

/* Step container */
.step-wrap{max-width:520px;width:100%;animation:fadeIn .4s var(--transition)}
@keyframes fadeIn{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}

/* Step indicator */
.step-dots{display:flex;justify-content:center;gap:8px;margin-bottom:32px}
.dot{width:10px;height:10px;border-radius:50%;background:rgba(20,50,48,.1);transition:all .3s var(--transition)}
.dot.done{background:var(--ac)}
.dot.current{background:var(--dk);transform:scale(1.2)}

/* Card */
.ob-card{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow-lg);padding:40px 36px;text-align:center}
.ob-card h1{font-size:1.6rem;font-weight:700;color:var(--dk);margin-bottom:8px}
.ob-card .subtitle{font-size:.9rem;color:var(--tx);opacity:.55;margin-bottom:28px;line-height:1.5}

/* Form elements */
.field{text-align:left;margin-bottom:20px}
.field label{display:block;font-size:.8rem;font-weight:600;color:var(--dk);margin-bottom:6px}
.field input,.field select{width:100%;padding:12px 16px;border:1px solid var(--card-border);border-radius:var(--radius-xs);font-family:var(--font);font-size:.9rem;color:var(--tx);transition:border-color .2s}
.field input:focus,.field select:focus{outline:none;border-color:var(--ac)}
.field .hint{font-size:.7rem;color:var(--tx);opacity:.4;margin-top:4px}

/* Target score slider */
.score-display{font-family:var(--mono);font-size:2rem;font-weight:700;color:var(--dk);margin:8px 0}
input[type="range"]{width:100%;accent-color:var(--ac)}

/* Buttons */
.ob-btn{display:inline-block;padding:14px 36px;background:var(--dk);color:#fff;border:none;border-radius:var(--radius-sm);font-family:var(--font);font-size:.95rem;font-weight:600;cursor:pointer;transition:all .2s var(--transition);margin-top:8px}
.ob-btn:hover{background:var(--ac);color:var(--dk);transform:translateY(-1px);box-shadow:var(--shadow-glow)}
.ob-btn.secondary{background:transparent;color:var(--tx);opacity:.5;padding:10px 20px;font-size:.8rem}
.ob-btn.secondary:hover{opacity:1;background:transparent;box-shadow:none;transform:none}

/* Diagnostic quiz */
.diag-progress{font-family:var(--mono);font-size:.75rem;color:var(--tx);opacity:.4;margin-bottom:16px}
.diag-stem{font-size:1rem;font-weight:500;margin-bottom:20px;line-height:1.5;text-align:left}
.diag-options{display:flex;flex-direction:column;gap:8px;margin-bottom:20px}
.diag-opt{display:block;width:100%;padding:14px 18px;background:rgba(20,50,48,.03);border:2px solid transparent;border-radius:var(--radius-sm);font-family:var(--font);font-size:.9rem;text-align:left;cursor:pointer;transition:all .15s var(--transition)}
.diag-opt:hover{border-color:var(--ac);background:rgba(31,226,144,.04)}
.diag-opt.selected{border-color:var(--ac);background:rgba(31,226,144,.08);font-weight:500}

/* Score reveal */
.score-reveal{padding:40px 20px;text-align:center}
.score-reveal .label{font-size:.8rem;text-transform:uppercase;letter-spacing:.1em;color:var(--tx);opacity:.4;margin-bottom:8px}
.score-reveal .score{font-family:var(--mono);font-size:3.5rem;font-weight:700;color:var(--dk);line-height:1}
.score-reveal .range{font-size:.9rem;color:var(--tx);opacity:.55;margin-top:8px}
.score-reveal .msg{font-size:.85rem;color:var(--tx);opacity:.5;margin-top:16px;line-height:1.5}

/* Confetti (simple CSS version) */
.confetti-wrap{position:fixed;inset:0;pointer-events:none;z-index:200;overflow:hidden}
.confetti{position:absolute;width:10px;height:10px;border-radius:2px;animation:fall 3s linear forwards}
@keyframes fall{0%{transform:translateY(-10px) rotate(0);opacity:1}100%{transform:translateY(100vh) rotate(720deg);opacity:0}}

@media(max-width:600px){
  body{padding:16px}
  .ob-card{padding:28px 20px}
  .ob-card h1{font-size:1.3rem}
}
</style>
</head>
<body>

<div class="progress-bar">
  <div class="progress-fill" style="width:<?= $progress ?>%"></div>
</div>

<div class="step-wrap">
  <div class="step-dots">
    <?php
    $stepNames = ['welcome', 'setup', 'diagnostic', 'plan_preview'];
    foreach ($stepNames as $i => $s):
      $class = 'dot';
      if ($obData['onboarding_step'] > $i) $class .= ' done';
      elseif ($s === $step) $class .= ' current';
    ?>
    <div class="<?= $class ?>"></div>
    <?php endforeach; ?>
  </div>

  <?php if ($step === 'welcome'): ?>
  <!-- STEP 1: Welcome -->
  <div class="ob-card">
    <h1>Welcome, <?= htmlspecialchars($firstName) ?>!</h1>
    <p class="subtitle">You're about to start your SAT prep journey. We'll personalize everything based on your goals and current level.</p>
    <p style="font-size:.85rem;color:var(--tx);opacity:.45;margin-bottom:24px">This takes about 5 minutes</p>
    <form method="POST">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
      <input type="hidden" name="step" value="welcome">
      <button type="submit" class="ob-btn">Let's Get Started</button>
    </form>
  </div>

  <?php elseif ($step === 'setup'): ?>
  <!-- STEP 2: Setup -->
  <div class="ob-card">
    <h1>Set Your Goals</h1>
    <p class="subtitle">Tell us about your SAT plans so we can build the perfect study schedule for you.</p>
    <form method="POST">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
      <input type="hidden" name="step" value="setup">

      <div class="field">
        <label>When is your SAT test date?</label>
        <input type="date" name="test_date" value="<?= htmlspecialchars($obData['setup']['test_date'] ?? '') ?>" min="<?= date('Y-m-d') ?>">
        <div class="hint">Don't know yet? You can change this later.</div>
      </div>

      <div class="field">
        <label>Target Score</label>
        <div class="score-display" id="scoreDisplay"><?= (int)($obData['setup']['target_score'] ?? 1200) ?></div>
        <input type="range" name="target_score" min="800" max="1600" step="10" value="<?= (int)($obData['setup']['target_score'] ?? 1200) ?>" oninput="document.getElementById('scoreDisplay').textContent=this.value">
        <div style="display:flex;justify-content:space-between;font-size:.7rem;color:var(--tx);opacity:.35"><span>800</span><span>1200</span><span>1600</span></div>
      </div>

      <div class="field">
        <label>Weekly study hours</label>
        <select name="study_hours">
          <?php foreach ([3,5,7,10,15,20] as $h): ?>
          <option value="<?= $h ?>" <?= (int)($obData['setup']['study_hours'] ?? 10) === $h ? 'selected' : '' ?>><?= $h ?> hours/week</option>
          <?php endforeach; ?>
        </select>
      </div>

      <button type="submit" class="ob-btn">Continue</button>
    </form>
  </div>

  <?php elseif ($step === 'diagnostic'): ?>
  <!-- STEP 3: Diagnostic Mini-Quiz -->
  <div class="ob-card" id="diagCard">
    <h1>Quick Diagnostic</h1>
    <p class="subtitle">Answer 10 questions so we can assess your starting level. No pressure — this just helps us personalize your plan.</p>

    <div id="diagQuiz">
      <div class="diag-progress">Question <span id="qNum">1</span> of <?= count($diagQuestions) ?></div>
      <div class="diag-stem" id="qStem"></div>
      <div class="diag-options" id="qOptions"></div>
      <div style="display:flex;gap:8px;justify-content:center">
        <button class="ob-btn" id="nextBtn" onclick="nextQuestion()" disabled>Next</button>
      </div>
    </div>

    <div id="diagDone" style="display:none">
      <div class="score-reveal">
        <div class="label">Your Starting Level</div>
        <div class="score" id="diagScore">—</div>
        <div class="range" id="diagLevel"></div>
        <div class="msg">We'll use this to create your personalized study plan.</div>
      </div>
      <form method="POST" id="diagForm">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="step" value="diagnostic">
        <input type="hidden" name="answers" id="diagAnswers" value="[]">
        <button type="submit" class="ob-btn">See My Plan</button>
      </form>
    </div>

    <form method="POST" style="margin-top:16px">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
      <input type="hidden" name="step" value="diagnostic">
      <input type="hidden" name="answers" value="[]">
      <button type="submit" class="ob-btn secondary">Skip for now</button>
    </form>
  </div>

  <script>
  const questions = <?= json_encode($diagQuestions) ?>;
  let currentQ = 0;
  let answers = [];
  let selected = -1;

  function renderQuestion() {
    const q = questions[currentQ];
    document.getElementById('qNum').textContent = currentQ + 1;
    document.getElementById('qStem').innerHTML = q.stem;
    const opts = document.getElementById('qOptions');
    opts.innerHTML = '';
    selected = -1;
    document.getElementById('nextBtn').disabled = true;

    q.options.forEach((opt, i) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'diag-opt';
      btn.innerHTML = String.fromCharCode(65 + i) + '. ' + opt;
      btn.onclick = () => {
        document.querySelectorAll('.diag-opt').forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
        selected = i;
        document.getElementById('nextBtn').disabled = false;
      };
      opts.appendChild(btn);
    });
  }

  function nextQuestion() {
    if (selected < 0) return;
    const q = questions[currentQ];
    answers.push({
      id: q.id,
      subject: q.subject,
      selected: selected,
      correct: selected === q.correct
    });

    currentQ++;
    if (currentQ >= questions.length) {
      showResults();
    } else {
      renderQuestion();
      document.getElementById('nextBtn').textContent = currentQ === questions.length - 1 ? 'Finish' : 'Next';
    }
  }

  function showResults() {
    const correct = answers.filter(a => a.correct).length;
    const pct = Math.round((correct / answers.length) * 100);
    const estimated = Math.round(800 + (pct / 100) * 800);
    const level = pct >= 80 ? 'Advanced' : (pct >= 50 ? 'Intermediate' : 'Foundational');

    document.getElementById('diagQuiz').style.display = 'none';
    document.getElementById('diagDone').style.display = 'block';
    document.getElementById('diagScore').textContent = correct + '/' + answers.length;
    document.getElementById('diagLevel').textContent = level + ' Level — Est. ' + estimated + ' SAT';
    document.getElementById('diagAnswers').value = JSON.stringify(answers);

    // Simple confetti
    const wrap = document.createElement('div');
    wrap.className = 'confetti-wrap';
    document.body.appendChild(wrap);
    const colors = ['#1fe290','#17c87a','#f5a623','#1da1f2','#cd7f32'];
    for (let i = 0; i < 30; i++) {
      const c = document.createElement('div');
      c.className = 'confetti';
      c.style.left = Math.random() * 100 + '%';
      c.style.background = colors[Math.floor(Math.random() * colors.length)];
      c.style.animationDelay = Math.random() * 2 + 's';
      c.style.animationDuration = (2 + Math.random() * 2) + 's';
      wrap.appendChild(c);
    }
    setTimeout(() => wrap.remove(), 5000);
  }

  renderQuestion();
  </script>

  <?php elseif ($step === 'plan_preview'): ?>
  <!-- STEP 4: Plan Preview -->
  <div class="ob-card">
    <h1>Your Personalized Plan</h1>
    <p class="subtitle">Based on your goals, here's what we've built for you:</p>

    <div style="text-align:left;margin:20px 0;display:flex;flex-direction:column;gap:14px">
      <div style="display:flex;gap:14px;align-items:flex-start;padding:14px 16px;background:rgba(31,226,144,.05);border-radius:var(--radius-sm)">
        <div style="width:36px;height:36px;border-radius:var(--radius-xs);background:var(--ac);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--dk);flex-shrink:0">1</div>
        <div>
          <div style="font-weight:600;font-size:.9rem;margin-bottom:2px">Daily Study Schedule</div>
          <div style="font-size:.75rem;color:var(--tx);opacity:.5">Personalized tasks based on your target score and available hours</div>
        </div>
      </div>
      <div style="display:flex;gap:14px;align-items:flex-start;padding:14px 16px;background:rgba(31,226,144,.05);border-radius:var(--radius-sm)">
        <div style="width:36px;height:36px;border-radius:var(--radius-xs);background:var(--ac);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--dk);flex-shrink:0">2</div>
        <div>
          <div style="font-weight:600;font-size:.9rem;margin-bottom:2px">AI Tutor Access</div>
          <div style="font-size:.75rem;color:var(--tx);opacity:.5">Get instant help on any math concept with your personal AI tutor</div>
        </div>
      </div>
      <div style="display:flex;gap:14px;align-items:flex-start;padding:14px 16px;background:rgba(31,226,144,.05);border-radius:var(--radius-sm)">
        <div style="width:36px;height:36px;border-radius:var(--radius-xs);background:var(--ac);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--dk);flex-shrink:0">3</div>
        <div>
          <div style="font-weight:600;font-size:.9rem;margin-bottom:2px">Practice Tests & Quizzes</div>
          <div style="font-size:.75rem;color:var(--tx);opacity:.5">Full-length practice tests and topic quizzes with detailed explanations</div>
        </div>
      </div>
      <div style="display:flex;gap:14px;align-items:flex-start;padding:14px 16px;background:rgba(31,226,144,.05);border-radius:var(--radius-sm)">
        <div style="width:36px;height:36px;border-radius:var(--radius-xs);background:var(--ac);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--dk);flex-shrink:0">4</div>
        <div>
          <div style="font-weight:600;font-size:.9rem;margin-bottom:2px">Score Prediction</div>
          <div style="font-size:.75rem;color:var(--tx);opacity:.5">Track your progress with our AI-powered SAT score predictor</div>
        </div>
      </div>
    </div>

    <form method="POST">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
      <input type="hidden" name="step" value="plan_preview">
      <button type="submit" class="ob-btn">Start Learning</button>
    </form>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
