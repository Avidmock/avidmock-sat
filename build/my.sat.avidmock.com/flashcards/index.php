<?php
/**
 * flashcards/index.php
 * Spaced-repetition flashcard study system. Cards generated from missed questions.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Database.php';

Auth::requireStudent();

$userId      = (int) $_SESSION['user_id'];
$activePage  = 'flashcards';
$topbarTitle = 'Flashcards';
$db          = Database::connect();

// ── Ensure flashcards table ───────────────────────────────────────────────
try { $db->query("SELECT 1 FROM flashcards LIMIT 1"); } catch (Throwable $e) {
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS flashcards (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            front TEXT NOT NULL,
            back TEXT NOT NULL,
            domain VARCHAR(50) DEFAULT '',
            skill VARCHAR(80) DEFAULT '',
            difficulty TINYINT DEFAULT 0,
            ease_factor FLOAT DEFAULT 2.5,
            interval_days INT DEFAULT 0,
            repetitions INT DEFAULT 0,
            next_review DATE DEFAULT (CURRENT_DATE),
            source_question_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_review (user_id, next_review),
            INDEX idx_user_domain (user_id, domain)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e2) {}
}

// ── Auto-generate cards from missed questions ─────────────────────────────
try {
    $missed = $db->prepare(
        "SELECT qq.id, qq.question_text, qq.explanation_text, qq.correct_answer,
                qq.option_a, qq.option_b, qq.option_c, qq.option_d
         FROM sat_quiz_answers a
         JOIN sat_quiz_questions qq ON qq.id = a.question_id
         WHERE a.user_id = :uid AND a.is_correct = 0
         AND qq.id NOT IN (SELECT source_question_id FROM flashcards WHERE user_id = :uid2 AND source_question_id IS NOT NULL)
         ORDER BY a.created_at DESC
         LIMIT 10"
    );
    $missed->execute([':uid' => $userId, ':uid2' => $userId]);
    $newCards = $missed->fetchAll(PDO::FETCH_ASSOC);

    foreach ($newCards as $card) {
        $correctLetter = $card['correct_answer'];
        $correctText = $card['option_' . strtolower($correctLetter)] ?? $correctLetter;
        $back = "Answer: {$correctLetter}) {$correctText}";
        if (!empty($card['explanation_text'])) {
            $back .= "\n\n" . $card['explanation_text'];
        }
        $db->prepare(
            "INSERT INTO flashcards (user_id, front, back, source_question_id, next_review) VALUES (?, ?, ?, ?, CURRENT_DATE)"
        )->execute([$userId, $card['question_text'], $back, $card['id']]);
    }
} catch (Throwable $e) { /* graceful */ }

// ── Fetch cards due today ─────────────────────────────────────────────────
try {
    $stmt = $db->prepare("SELECT * FROM flashcards WHERE user_id = ? AND next_review <= CURRENT_DATE ORDER BY next_review ASC LIMIT 20");
    $stmt->execute([$userId]);
    $dueCards = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $dueCards = []; }

// ── Stats ─────────────────────────────────────────────────────────────────
try { $totalCards = (int) $db->prepare("SELECT COUNT(*) FROM flashcards WHERE user_id = ?")->execute([$userId]) ? 0 : 0;
    $s = $db->prepare("SELECT COUNT(*) FROM flashcards WHERE user_id = ?"); $s->execute([$userId]); $totalCards = (int) $s->fetchColumn();
} catch (Throwable $e) { $totalCards = 0; }

try {
    $s = $db->prepare("SELECT COUNT(*) FROM flashcards WHERE user_id = ? AND next_review <= CURRENT_DATE");
    $s->execute([$userId]); $dueCount = (int) $s->fetchColumn();
} catch (Throwable $e) { $dueCount = count($dueCards); }

try {
    $s = $db->prepare("SELECT COUNT(*) FROM flashcards WHERE user_id = ? AND repetitions > 0");
    $s->execute([$userId]); $masteredCount = (int) $s->fetchColumn();
} catch (Throwable $e) { $masteredCount = 0; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Flashcards — AvidMock SAT</title>
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
.stat-value.green{color:var(--ac)}

.flashcard-area{display:flex;justify-content:center;align-items:center;min-height:400px}
.flashcard{width:100%;max-width:560px;min-height:320px;perspective:1000px;cursor:pointer}
.flashcard-inner{position:relative;width:100%;min-height:320px;transition:transform .6s;transform-style:preserve-3d}
.flashcard.flipped .flashcard-inner{transform:rotateY(180deg)}
.flashcard-front,.flashcard-back{position:absolute;inset:0;backface-visibility:hidden;border-radius:20px;padding:40px;display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center}
.flashcard-front{background:linear-gradient(135deg,#143230,#1a4a46);color:#fff}
.flashcard-back{background:#fff;border:2px solid var(--ac);transform:rotateY(180deg)}
.card-side-label{font-size:.625rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;opacity:.5;margin-bottom:16px}
.card-text{font-size:1rem;line-height:1.65;max-height:220px;overflow-y:auto}
.card-text-back{font-size:.9375rem;color:var(--tx);line-height:1.65;white-space:pre-line}

.card-actions{display:flex;justify-content:center;gap:10px;margin-top:24px}
.rate-btn{padding:10px 24px;border-radius:var(--r-sm);font-size:.8125rem;font-weight:700;border:2px solid;cursor:pointer;transition:all .18s}
.rate-btn.again{border-color:#ef4444;color:#ef4444;background:rgba(239,68,68,.05)}
.rate-btn.again:hover{background:#ef4444;color:#fff}
.rate-btn.hard{border-color:#f59e0b;color:#f59e0b;background:rgba(245,158,11,.05)}
.rate-btn.hard:hover{background:#f59e0b;color:#fff}
.rate-btn.good{border-color:var(--ac);color:var(--dk);background:rgba(31,226,144,.05)}
.rate-btn.good:hover{background:var(--ac);color:var(--dk)}
.rate-btn.easy{border-color:var(--dk);color:var(--dk);background:rgba(20,50,48,.05)}
.rate-btn.easy:hover{background:var(--dk);color:#fff}

.card-progress{text-align:center;margin-top:16px;font-size:.75rem;color:var(--tx3)}
.card-hint{text-align:center;margin-top:8px;font-size:.6875rem;color:var(--tx3)}

.empty-state{text-align:center;padding:60px 20px;background:#fff;border-radius:var(--r);border:1px solid rgba(20,50,48,.06)}
.empty-state h3{font-family:'Fraunces',serif;font-size:1.25rem;font-weight:900;color:var(--dk);margin-bottom:8px}
.empty-state p{color:var(--tx2);font-size:.875rem;margin-bottom:20px}
.btn-primary{display:inline-flex;align-items:center;gap:6px;padding:10px 20px;background:var(--ac);color:var(--dk);font-weight:700;font-size:.8125rem;border:none;border-radius:var(--r-sm);cursor:pointer;text-decoration:none}

@media(max-width:768px){.main-content{margin-left:0;padding:16px}.stats-row{flex-direction:column}}
</style>
</head>
<body>
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/sidebar.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/topbar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1>Flashcards</h1>
        <p>Spaced-repetition study cards from your missed questions</p>
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-label">Due Today</div>
            <div class="stat-value green"><?= $dueCount ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Cards</div>
            <div class="stat-value"><?= $totalCards ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Reviewed</div>
            <div class="stat-value"><?= $masteredCount ?></div>
        </div>
    </div>

    <?php if (!empty($dueCards)): ?>
    <div class="flashcard-area">
        <div class="flashcard" id="flashcard" onclick="flipCard()">
            <div class="flashcard-inner">
                <div class="flashcard-front">
                    <div class="card-side-label">Question</div>
                    <div class="card-text" id="cardFront"></div>
                </div>
                <div class="flashcard-back">
                    <div class="card-side-label">Answer</div>
                    <div class="card-text-back" id="cardBack"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card-actions" id="rateButtons" style="display:none">
        <button class="rate-btn again" onclick="rateCard(0)">Again</button>
        <button class="rate-btn hard" onclick="rateCard(1)">Hard</button>
        <button class="rate-btn good" onclick="rateCard(2)">Good</button>
        <button class="rate-btn easy" onclick="rateCard(3)">Easy</button>
    </div>

    <div class="card-progress" id="cardProgress"></div>
    <div class="card-hint" id="cardHint">Click card to flip</div>

    <script>
    const cards = <?= json_encode(array_map(function($c) {
        return ['id' => $c['id'], 'front' => $c['front'], 'back' => $c['back']];
    }, $dueCards)) ?>;
    let idx = 0;

    function showCard() {
        if (idx >= cards.length) {
            document.querySelector('.flashcard-area').innerHTML = '<div class="empty-state"><h3>All done!</h3><p>You\'ve reviewed all cards due today. Come back tomorrow.</p></div>';
            document.getElementById('rateButtons').style.display = 'none';
            document.getElementById('cardProgress').textContent = '';
            document.getElementById('cardHint').textContent = '';
            return;
        }
        document.getElementById('flashcard').classList.remove('flipped');
        document.getElementById('cardFront').textContent = cards[idx].front;
        document.getElementById('cardBack').textContent = cards[idx].back;
        document.getElementById('rateButtons').style.display = 'none';
        document.getElementById('cardProgress').textContent = (idx + 1) + ' / ' + cards.length;
        document.getElementById('cardHint').textContent = 'Click card to flip';
    }

    function flipCard() {
        document.getElementById('flashcard').classList.toggle('flipped');
        if (document.getElementById('flashcard').classList.contains('flipped')) {
            document.getElementById('rateButtons').style.display = 'flex';
            document.getElementById('cardHint').textContent = 'Rate your recall below';
        }
    }

    async function rateCard(quality) {
        const cardId = cards[idx].id;
        try {
            await fetch('/api/flashcard-review.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({card_id: cardId, quality: quality})
            });
        } catch (e) {}
        idx++;
        showCard();
    }

    showCard();
    </script>

    <?php else: ?>
    <div class="empty-state">
        <h3>No flashcards due</h3>
        <p>Flashcards are auto-generated when you get questions wrong. Take some quizzes to build your deck!</p>
        <a href="/learn/" class="btn-primary">Start Practicing</a>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
