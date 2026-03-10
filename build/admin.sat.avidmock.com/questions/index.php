<?php
/**
 * questions/index.php
 * Question Bank — browse, search, filter all questions across quizzes.
 */
require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

$admin = currentAdmin();
$db    = Database::connect();

// ── Params ────────────────────────────────────────────────────────────────
$search   = trim($_GET['q'] ?? '');
$domain   = $_GET['domain'] ?? 'all';
$diff     = $_GET['difficulty'] ?? 'all';
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 25;
$offset   = ($page - 1) * $perPage;

// ── Detect columns ────────────────────────────────────────────────────────
try {
    $qCols = $db->query("SHOW COLUMNS FROM sat_quiz_questions")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) { $qCols = []; }
$hasDomain     = in_array('domain', $qCols);
$hasDifficulty = in_array('difficulty', $qCols);
$hasSkill      = in_array('skill', $qCols);

// ── Build WHERE ───────────────────────────────────────────────────────────
$where  = [];
$params = [];
if ($search !== '') {
    $where[]       = '(qq.question_text LIKE :s)';
    $params[':s']  = "%{$search}%";
}
if ($domain !== 'all' && $hasDomain) {
    $where[]            = 'qq.domain = :domain';
    $params[':domain']  = $domain;
}
if ($diff !== 'all' && $hasDifficulty) {
    $where[]          = 'qq.difficulty = :diff';
    $params[':diff']  = $diff;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ── Count ─────────────────────────────────────────────────────────────────
try {
    $countStmt = $db->prepare("SELECT COUNT(*) FROM sat_quiz_questions qq {$whereSQL}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();
} catch (Throwable $e) { $total = 0; }
$pages = max(1, (int) ceil($total / $perPage));

// ── Fetch questions ───────────────────────────────────────────────────────
$domainCol = $hasDomain ? 'qq.domain,' : '';
$diffCol   = $hasDifficulty ? 'qq.difficulty,' : '';
$skillCol  = $hasSkill ? 'qq.skill,' : '';

try {
    $stmt = $db->prepare(
        "SELECT qq.id, qq.quiz_id, qq.question_text, qq.explanation_text,
                {$domainCol} {$diffCol} {$skillCol}
                qq.`order`, q.title AS quiz_title, q.status AS quiz_status,
                (SELECT COUNT(*) FROM sat_quiz_answers a WHERE a.question_id = qq.id) AS total_answers,
                (SELECT SUM(CASE WHEN a.is_correct = 1 THEN 1 ELSE 0 END) FROM sat_quiz_answers a WHERE a.question_id = qq.id) AS correct_answers
         FROM sat_quiz_questions qq
         LEFT JOIN sat_quizzes q ON q.id = qq.quiz_id
         {$whereSQL}
         ORDER BY qq.id DESC
         LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $stmt->execute();
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $questions = []; }

// ── Domain counts ─────────────────────────────────────────────────────────
$domainCounts = [];
if ($hasDomain) {
    try {
        $domainCounts = $db->query("SELECT domain, COUNT(*) AS n FROM sat_quiz_questions GROUP BY domain")->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Throwable $e) {}
}

// ── Difficulty counts ─────────────────────────────────────────────────────
$diffCounts = [];
if ($hasDifficulty) {
    try {
        $diffCounts = $db->query("SELECT difficulty, COUNT(*) AS n FROM sat_quiz_questions GROUP BY difficulty")->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Throwable $e) {}
}

// ── Draft count for sidebar ──────────────────────────────────────────────
try { $draftCount = (int) $db->query("SELECT COUNT(*) FROM sat_quizzes WHERE status='draft'")->fetchColumn(); } catch (Throwable $e) { $draftCount = 0; }

// ── Head ──────────────────────────────────────────────────────────────────
$pageTitle  = 'Question Bank — Avidmock Admin';
$activePage = 'questions';
$extraHead  = <<<'CSS'
<style>
.main { margin-left: var(--sb-w); margin-top: var(--top-h); padding: 32px 28px; min-height: calc(100vh - var(--top-h)); }
.ph { margin-bottom: 24px; }
.ph-eyebrow { display: flex; align-items: center; gap: 6px; font-size: .5rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 5px; }
.ph-dot { width: 4px; height: 4px; border-radius: 50%; background: var(--ac); }
.ph-title { font-family: var(--fh); font-size: 1.875rem; font-weight: 900; color: var(--tx); letter-spacing: -.035em; line-height: 1.1; }
.ph-sub { font-size: .875rem; color: var(--tx2); margin-top: 4px; }

.filter-bar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 16px; }
.tabs { display: flex; background: var(--sf); border: 1px solid var(--bd); border-radius: 10px; padding: 4px; gap: 2px; }
.tab { padding: 6px 14px; border-radius: 7px; font-size: .75rem; font-weight: 700; color: var(--tx3); text-decoration: none; transition: all .16s; display: flex; align-items: center; gap: 5px; white-space: nowrap; }
.tab:hover { color: var(--tx); background: var(--sf2); }
.tab.active { background: var(--ac3); color: var(--ac); }
.tab-count { font-size: .5625rem; background: var(--sf3); color: var(--tx3); padding: 1px 5px; border-radius: 50px; font-weight: 800; }
.tab.active .tab-count { background: rgba(31,226,144,.15); color: var(--ac); }
.search-wrap { position: relative; flex: 1; min-width: 200px; max-width: 320px; }
.search-wrap svg { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); width: 14px; height: 14px; stroke: var(--tx3); fill: none; stroke-width: 1.8; stroke-linecap: round; pointer-events: none; }
.search-input { width: 100%; padding: 8px 12px 8px 34px; background: var(--sf); border: 1.5px solid var(--bd); border-radius: 9px; font-family: var(--ff); font-size: .8125rem; color: var(--tx); outline: none; transition: border-color .18s; }
.search-input::placeholder { color: var(--tx3); }
.search-input:focus { border-color: var(--ac); box-shadow: 0 0 0 3px rgba(31,226,144,.1); }

.filter-select { padding: 7px 12px; background: var(--sf); border: 1.5px solid var(--bd); border-radius: 9px; font-family: var(--ff); font-size: .8125rem; color: var(--tx); outline: none; cursor: pointer; }
.filter-select:focus { border-color: var(--ac); }

.stat-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; margin-bottom: 20px; }
.stat-card { background: var(--sf); border: 1px solid var(--bd); border-radius: 12px; padding: 16px 18px; }
.stat-label { font-size: .625rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: .6px; margin-bottom: 4px; }
.stat-value { font-family: var(--fm); font-size: 1.5rem; font-weight: 800; color: var(--tx); }

.table-card { background: var(--sf); border: 1px solid var(--bd); border-radius: 14px; overflow: hidden; }
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
thead th { padding: 10px 16px; font-size: .5625rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: .8px; text-align: left; border-bottom: 1px solid var(--bd); white-space: nowrap; }
tbody td { padding: 13px 16px; font-size: .8125rem; border-bottom: 1px solid var(--bd); vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: rgba(255,255,255,.025); }

.q-text { max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--tx); font-weight: 600; }
.q-quiz { font-size: .625rem; color: var(--tx3); margin-top: 2px; }
.diff-badge { display: inline-block; padding: 2px 8px; border-radius: 5px; font-size: .5625rem; font-weight: 700; text-transform: uppercase; }
.diff-badge.easy { background: rgba(31,226,144,.1); color: var(--ac); }
.diff-badge.medium { background: rgba(255,193,7,.12); color: var(--warn); }
.diff-badge.hard { background: rgba(239,68,68,.1); color: var(--err); }
.domain-tag { display: inline-block; padding: 2px 8px; border-radius: 5px; font-size: .5625rem; font-weight: 700; background: var(--blue2); color: var(--blue); }
.accuracy { font-family: var(--fm); font-weight: 700; }
.accuracy.hi { color: var(--ac); } .accuracy.md { color: var(--warn); } .accuracy.lo { color: var(--err); } .accuracy.na { color: var(--tx3); }
.num { font-family: var(--fm); font-size: .8125rem; color: var(--tx2); }

.row-actions { display: flex; gap: 4px; opacity: 0; transition: opacity .14s; }
tbody tr:hover .row-actions { opacity: 1; }

.pagination { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-top: 1px solid var(--bd); flex-wrap: wrap; gap: 10px; }
.pag-info { font-size: .75rem; color: var(--tx3); }
.pag-btns { display: flex; gap: 4px; }
.pag-btn { width: 30px; height: 30px; border-radius: 7px; display: flex; align-items: center; justify-content: center; font-size: .75rem; font-weight: 700; text-decoration: none; color: var(--tx3); background: var(--sf); border: 1px solid var(--bd); }
.pag-btn:hover { background: var(--sf2); color: var(--tx); }
.pag-btn.active { background: var(--ac3); color: var(--ac); border-color: rgba(31,226,144,.2); }
.pag-btn.disabled { opacity: .35; pointer-events: none; }

.empty { text-align: center; padding: 80px 20px; }
.empty-title { font-family: var(--fh); font-size: 1.25rem; font-weight: 900; color: var(--tx); margin-bottom: 8px; }
.empty-sub { font-size: .875rem; color: var(--tx3); }

@media (max-width: 768px) { .main { margin-left: 0; padding: 16px; } }
</style>
CSS;

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<header class="topbar">
    <button class="topbar-ham" onclick="openSidebar()" aria-label="Menu"><span></span><span></span><span></span></button>
    <div class="topbar-title">Question Bank</div>
    <div class="topbar-spacer"></div>
    <a href="/questions/create.php" class="btn btn-primary">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Question
    </a>
</header>

<main class="main">
    <div class="ph reveal d1">
        <div class="ph-eyebrow"><span class="ph-dot"></span>Content</div>
        <h1 class="ph-title">Question Bank</h1>
        <p class="ph-sub"><?= number_format($total) ?> question<?= $total !== 1 ? 's' : '' ?> across all quizzes</p>
    </div>

    <!-- Stats row -->
    <div class="stat-row reveal d2">
        <div class="stat-card">
            <div class="stat-label">Total Questions</div>
            <div class="stat-value"><?= number_format($total) ?></div>
        </div>
        <?php if ($hasDomain): ?>
        <div class="stat-card">
            <div class="stat-label">Domains</div>
            <div class="stat-value"><?= count($domainCounts) ?></div>
        </div>
        <?php endif; ?>
        <?php if ($hasDifficulty): ?>
        <div class="stat-card">
            <div class="stat-label">Easy</div>
            <div class="stat-value"><?= number_format($diffCounts['easy'] ?? 0) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Medium</div>
            <div class="stat-value"><?= number_format($diffCounts['medium'] ?? 0) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Hard</div>
            <div class="stat-value"><?= number_format($diffCounts['hard'] ?? 0) ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="filter-bar reveal d2">
        <form method="GET" style="display:contents">
            <?php if ($hasDomain): ?>
            <select name="domain" class="filter-select" onchange="this.form.submit()">
                <option value="all">All Domains</option>
                <?php foreach ($domainCounts as $d => $n): ?>
                <option value="<?= e($d) ?>" <?= $domain === $d ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $d))) ?> (<?= $n ?>)</option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <?php if ($hasDifficulty): ?>
            <select name="difficulty" class="filter-select" onchange="this.form.submit()">
                <option value="all">All Difficulties</option>
                <option value="easy" <?= $diff === 'easy' ? 'selected' : '' ?>>Easy</option>
                <option value="medium" <?= $diff === 'medium' ? 'selected' : '' ?>>Medium</option>
                <option value="hard" <?= $diff === 'hard' ? 'selected' : '' ?>>Hard</option>
            </select>
            <?php endif; ?>
            <div class="search-wrap">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input class="search-input" type="search" name="q" value="<?= e($search) ?>" placeholder="Search questions…" onkeydown="if(event.key==='Enter')this.closest('form').submit()">
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="table-card reveal d3" style="margin-top:20px">
        <?php if (empty($questions)): ?>
        <div class="empty">
            <div class="empty-title">No questions found</div>
            <div class="empty-sub"><?= $search ? 'Try a different search.' : 'Create quizzes with questions to populate the bank.' ?></div>
        </div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Question</th>
                        <?php if ($hasDomain): ?><th>Domain</th><?php endif; ?>
                        <?php if ($hasDifficulty): ?><th>Difficulty</th><?php endif; ?>
                        <th>Attempts</th>
                        <th>Accuracy</th>
                        <th>Quiz</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($questions as $q):
                    $totalAns = (int)($q['total_answers'] ?? 0);
                    $correctAns = (int)($q['correct_answers'] ?? 0);
                    $accuracy = $totalAns > 0 ? round($correctAns / $totalAns * 100, 1) : null;
                    $accClass = $accuracy === null ? 'na' : ($accuracy >= 70 ? 'hi' : ($accuracy >= 50 ? 'md' : 'lo'));
                ?>
                <tr>
                    <td>
                        <div class="q-text"><?= e(substr(strip_tags($q['question_text'] ?? ''), 0, 100)) ?></div>
                        <div class="q-quiz">ID: <?= $q['id'] ?></div>
                    </td>
                    <?php if ($hasDomain): ?>
                    <td><span class="domain-tag"><?= e(ucwords(str_replace('_', ' ', $q['domain'] ?? '—'))) ?></span></td>
                    <?php endif; ?>
                    <?php if ($hasDifficulty): ?>
                    <td><span class="diff-badge <?= e($q['difficulty'] ?? '') ?>"><?= e(ucfirst($q['difficulty'] ?? '—')) ?></span></td>
                    <?php endif; ?>
                    <td><span class="num"><?= number_format($totalAns) ?></span></td>
                    <td>
                        <?php if ($accuracy !== null): ?>
                        <span class="accuracy <?= $accClass ?>"><?= $accuracy ?>%</span>
                        <?php else: ?>
                        <span class="accuracy na">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($q['quiz_title']): ?>
                        <a href="/quizzes/edit.php?id=<?= $q['quiz_id'] ?>" style="font-size:.75rem;color:var(--ac)"><?= e($q['quiz_title']) ?></a>
                        <?php else: ?>
                        <span class="num">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="row-actions">
                            <a href="/questions/edit.php?id=<?= $q['id'] ?>" class="icon-btn edit" title="Edit">
                                <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages > 1): ?>
        <div class="pagination">
            <div class="pag-info">Showing <?= $offset + 1 ?>–<?= min($offset + $perPage, $total) ?> of <?= number_format($total) ?></div>
            <div class="pag-btns">
                <a href="?domain=<?= urlencode($domain) ?>&difficulty=<?= urlencode($diff) ?>&q=<?= urlencode($search) ?>&page=<?= max(1, $page - 1) ?>" class="pag-btn <?= $page <= 1 ? 'disabled' : '' ?>">‹</a>
                <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
                <a href="?domain=<?= urlencode($domain) ?>&difficulty=<?= urlencode($diff) ?>&q=<?= urlencode($search) ?>&page=<?= $i ?>" class="pag-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <a href="?domain=<?= urlencode($domain) ?>&difficulty=<?= urlencode($diff) ?>&q=<?= urlencode($search) ?>&page=<?= min($pages, $page + 1) ?>" class="pag-btn <?= $page >= $pages ? 'disabled' : '' ?>">›</a>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</main>
</body>
</html>
