<?php
/**
 * daily-challenge/index.php
 * Daily SAT challenge — one timed question per day with XP bonus.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Database.php';

Auth::requireStudent();

$userId      = (int) $_SESSION['user_id'];
$activePage  = 'daily-challenge';
$topbarTitle = 'Daily Challenge';
$db          = Database::connect();

// ── Ensure daily_challenges table ─────────────────────────────────────────
try { $db->query("SELECT 1 FROM daily_challenges LIMIT 1"); } catch (Throwable $e) {
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS daily_challenges (
            id INT AUTO_INCREMENT PRIMARY KEY,
            challenge_date DATE NOT NULL UNIQUE,
            question_id INT NOT NULL,
            bonus_xp INT DEFAULT 50,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_date (challenge_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS daily_challenge_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            challenge_date DATE NOT NULL,
            answer VARCHAR(5),
            is_correct TINYINT DEFAULT 0,
            time_ms INT DEFAULT 0,
            xp_earned INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_user_date (user_id, challenge_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e2) {}
}

$today = date('Y-m-d');

// ── Check if already attempted today ──────────────────────────────────────
try {
    $stmt = $db->prepare("SELECT * FROM daily_challenge_attempts WHERE user_id = ? AND challenge_date = ?");
    $stmt->execute([$userId, $today]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $attempt = null; }

// ── Get today's challenge ─────────────────────────────────────────────────
try {
    $stmt = $db->prepare("SELECT dc.*, qq.* FROM daily_challenges dc JOIN sat_quiz_questions qq ON qq.id = dc.question_id WHERE dc.challenge_date = ?");
    $stmt->execute([$today]);
    $challenge = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $challenge = null; }

// Auto-generate today's challenge if missing
if (!$challenge) {
    try {
        $q = $db->query("SELECT id FROM sat_quiz_questions ORDER BY RAND() LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($q) {
            $db->prepare("INSERT IGNORE INTO daily_challenges (challenge_date, question_id, bonus_xp) VALUES (?, ?, 50)")->execute([$today, $q['id']]);
            $stmt = $db->prepare("SELECT dc.*, qq.* FROM daily_challenges dc JOIN sat_quiz_questions qq ON qq.id = dc.question_id WHERE dc.challenge_date = ?");
            $stmt->execute([$today]);
            $challenge = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {}
}

// ── Streak count ──────────────────────────────────────────────────────────
try {
    $streakStmt = $db->prepare(
        "SELECT COUNT(*) FROM daily_challenge_attempts WHERE user_id = ? AND is_correct = 1 AND challenge_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)"
    );
    $streakStmt->execute([$userId]);
    $challengeStreak = (int) $streakStmt->fetchColumn();
} catch (Throwable $e) { $challengeStreak = 0; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daily Challenge — AvidMock SAT</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:wght@700;900&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{--dk:#143230;--ac:#1FE290;--tx:#1a1a2e;--tx2:#64748b;--tx3:#94a3b8;--bg:#f7faf9;--r:16px;--r-sm:10px;--err:#ef4444}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--tx);min-height:100vh}
.main-content{margin-left:260px;padding:32px;min-height:100vh}

.page-header{margin-bottom:28px;display:flex;align-items:center;justify-content:space-between}
.page-header h1{font-family:'Fraunces',serif;font-size:1.875rem;font-weight:900;letter-spacing:-.03em;color:var(--dk)}
.streak-badge{display:flex;align-items:center;gap:6px;padding:8px 16px;background:rgba(31,226,144,.1);border-radius:20px;font-size:.8125rem;font-weight:700;color:var(--dk)}
.streak-badge svg{width:18px;height:18px;stroke:var(--ac);fill:none;stroke-width:2}

.challenge-card{background:#fff;border:1px solid rgba(20,50,48,.06);border-radius:20px;padding:40px;max-width:680px;margin:0 auto;box-shadow:0 4px 24px rgba(0,0,0,.06)}
.challenge-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px}
.challenge-date{font-size:.75rem;font-weight:700;color:var(--tx3);text-transform:uppercase;letter-spacing:.6px}
.challenge-xp{font-family:'DM Mono',monospace;font-size:.875rem;font-weight:700;color:var(--ac);background:rgba(31,226,144,.08);padding:4px 12px;border-radius:6px}

.question-text{font-size:1.125rem;line-height:1.7;color:var(--tx);margin-bottom:28px;font-weight:500}

.options{display:flex;flex-direction:column;gap:10px}
.option-btn{display:flex;align-items:center;gap:12px;padding:14px 18px;background:var(--bg);border:2px solid rgba(20,50,48,.08);border-radius:var(--r-sm);cursor:pointer;transition:all .18s;font-size:.9375rem}
.option-btn:hover{border-color:var(--ac);background:rgba(31,226,144,.03)}
.option-btn.selected{border-color:var(--ac);background:rgba(31,226,144,.06)}
.option-btn.correct{border-color:var(--ac);background:rgba(31,226,144,.1)}
.option-btn.wrong{border-color:var(--err);background:rgba(239,68,68,.06)}
.option-btn.disabled{pointer-events:none;opacity:.7}
.option-letter{width:28px;height:28px;border-radius:50%;background:rgba(20,50,48,.06);display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:800;color:var(--tx2);flex-shrink:0}
.option-btn.correct .option-letter{background:var(--ac);color:#fff}
.option-btn.wrong .option-letter{background:var(--err);color:#fff}

.submit-area{margin-top:24px;display:flex;justify-content:center}
.btn-submit{padding:12px 36px;background:var(--ac);color:var(--dk);font-weight:700;font-size:.9375rem;border:none;border-radius:var(--r-sm);cursor:pointer;transition:all .18s}
.btn-submit:hover{transform:translateY(-1px);box-shadow:0 4px 16px rgba(31,226,144,.3)}
.btn-submit:disabled{opacity:.4;cursor:not-allowed;transform:none;box-shadow:none}

.result-box{margin-top:24px;padding:20px;border-radius:var(--r-sm);text-align:center}
.result-box.correct-result{background:rgba(31,226,144,.08);border:1px solid rgba(31,226,144,.2)}
.result-box.wrong-result{background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.15)}
.result-title{font-family:'Fraunces',serif;font-size:1.25rem;font-weight:900;margin-bottom:6px}
.result-box.correct-result .result-title{color:var(--dk)}
.result-box.wrong-result .result-title{color:var(--err)}
.result-xp{font-family:'DM Mono',monospace;font-size:.875rem;color:var(--ac);margin-top:4px}
.explanation{margin-top:16px;padding:16px;background:rgba(20,50,48,.02);border-radius:var(--r-sm);font-size:.875rem;color:var(--tx2);line-height:1.65;text-align:left}

.completed-state{text-align:center;padding:40px}
.completed-state h2{font-family:'Fraunces',serif;font-size:1.5rem;font-weight:900;color:var(--dk);margin-bottom:8px}
.completed-state p{color:var(--tx2);margin-bottom:4px}

.empty-state{text-align:center;padding:60px}
.empty-state h3{font-family:'Fraunces',serif;font-size:1.25rem;font-weight:900;color:var(--dk);margin-bottom:8px}
.empty-state p{color:var(--tx2);font-size:.875rem}

@media(max-width:768px){.main-content{margin-left:0;padding:16px}.challenge-card{padding:24px}}
</style>
</head>
<body>
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/sidebar.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/topbar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1>Daily Challenge</h1>
        <div class="streak-badge">
            <svg viewBox="0 0 24 24"><path d="M12 2L15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26z"/></svg>
            <?= $challengeStreak ?> correct this month
        </div>
    </div>

    <?php if (!$challenge): ?>
    <div class="challenge-card">
        <div class="empty-state">
            <h3>No challenge available</h3>
            <p>Add questions to your quiz bank to enable daily challenges.</p>
        </div>
    </div>

    <?php elseif ($attempt): ?>
    <div class="challenge-card">
        <div class="completed-state">
            <h2><?= $attempt['is_correct'] ? 'Correct!' : 'Not quite' ?></h2>
            <p>You already completed today's challenge.</p>
            <?php if ((int)$attempt['xp_earned'] > 0): ?>
            <p style="font-family:'DM Mono',monospace;color:var(--ac);font-weight:700">+<?= $attempt['xp_earned'] ?> XP earned</p>
            <?php endif; ?>
            <p style="font-size:.75rem;color:var(--tx3);margin-top:12px">Come back tomorrow for a new challenge!</p>
        </div>
    </div>

    <?php else: ?>
    <div class="challenge-card">
        <div class="challenge-header">
            <span class="challenge-date"><?= date('l, F j') ?></span>
            <span class="challenge-xp">+<?= $challenge['bonus_xp'] ?? 50 ?> XP</span>
        </div>

        <div class="question-text"><?= htmlspecialchars($challenge['question_text'] ?? '') ?></div>

        <div class="options" id="options">
            <?php foreach (['A','B','C','D'] as $letter):
                $optKey = 'option_' . strtolower($letter);
                $optText = $challenge[$optKey] ?? '';
                if (!$optText) continue;
            ?>
            <div class="option-btn" data-answer="<?= $letter ?>" onclick="selectOption(this)">
                <span class="option-letter"><?= $letter ?></span>
                <span><?= htmlspecialchars($optText) ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="submit-area">
            <button class="btn-submit" id="submitBtn" disabled onclick="submitAnswer()">Submit Answer</button>
        </div>

        <div id="resultArea"></div>
    </div>

    <script>
    let selectedAnswer = null;
    const correctAnswer = <?= json_encode($challenge['correct_answer'] ?? '') ?>;
    const bonusXp = <?= (int)($challenge['bonus_xp'] ?? 50) ?>;
    const explanation = <?= json_encode($challenge['explanation_text'] ?? '') ?>;
    const startTime = Date.now();

    function selectOption(el) {
        document.querySelectorAll('.option-btn').forEach(b => b.classList.remove('selected'));
        el.classList.add('selected');
        selectedAnswer = el.dataset.answer;
        document.getElementById('submitBtn').disabled = false;
    }

    async function submitAnswer() {
        if (!selectedAnswer) return;
        const timeMs = Date.now() - startTime;
        const isCorrect = selectedAnswer === correctAnswer;

        // Disable all options
        document.querySelectorAll('.option-btn').forEach(b => {
            b.classList.add('disabled');
            if (b.dataset.answer === correctAnswer) b.classList.add('correct');
            if (b.dataset.answer === selectedAnswer && !isCorrect) b.classList.add('wrong');
        });
        document.getElementById('submitBtn').style.display = 'none';

        // Show result
        const xp = isCorrect ? bonusXp : 0;
        document.getElementById('resultArea').innerHTML = `
            <div class="result-box ${isCorrect ? 'correct-result' : 'wrong-result'}">
                <div class="result-title">${isCorrect ? 'Correct!' : 'Not quite'}</div>
                ${xp > 0 ? `<div class="result-xp">+${xp} XP</div>` : ''}
                ${explanation ? `<div class="explanation">${explanation}</div>` : ''}
            </div>`;

        // Submit to API
        try {
            await fetch('/api/daily-challenge-submit.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({answer: selectedAnswer, time_ms: timeMs})
            });
        } catch (e) {}
    }
    </script>
    <?php endif; ?>
</div>
</body>
</html>
