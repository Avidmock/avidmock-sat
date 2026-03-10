<?php
/**
 * reports/index.php
 * Reports & Data Export — CSV exports for students, quizzes, attempts, revenue.
 */
require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

$admin = currentAdmin();
$db    = Database::connect();

// ── Handle exports ────────────────────────────────────────────────────────
$export = $_GET['export'] ?? '';
if ($export) {
    try {
        switch ($export) {
            case 'students':
                $userCols = $db->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
                $nameExpr = in_array('first_name', $userCols) ? "CONCAT(first_name,' ',last_name)" : "name";
                $rows = $db->query("SELECT id, {$nameExpr} AS name, email, created_at FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
                $filename = 'students-' . date('Y-m-d') . '.csv';
                break;

            case 'quiz-attempts':
                $rows = $db->query(
                    "SELECT a.id, a.user_id, a.quiz_id, q.title AS quiz_title, a.score, a.status, a.time_spent, a.created_at
                     FROM sat_quiz_attempts a
                     LEFT JOIN sat_quizzes q ON q.id = a.quiz_id
                     ORDER BY a.created_at DESC
                     LIMIT 10000"
                )->fetchAll(PDO::FETCH_ASSOC);
                $filename = 'quiz-attempts-' . date('Y-m-d') . '.csv';
                break;

            case 'quizzes':
                $rows = $db->query(
                    "SELECT id, title, lesson_slug, section, status, time_limit, passing_score, created_at
                     FROM sat_quizzes ORDER BY id DESC"
                )->fetchAll(PDO::FETCH_ASSOC);
                $filename = 'quizzes-' . date('Y-m-d') . '.csv';
                break;

            case 'questions':
                $rows = $db->query(
                    "SELECT id, quiz_id, question_text, correct_answer, created_at
                     FROM sat_quiz_questions ORDER BY id DESC LIMIT 10000"
                )->fetchAll(PDO::FETCH_ASSOC);
                $filename = 'questions-' . date('Y-m-d') . '.csv';
                break;

            default:
                $rows = [];
                $filename = 'export.csv';
        }

        if (!empty($rows)) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            $out = fopen('php://output', 'w');
            fputcsv($out, array_keys($rows[0]));
            foreach ($rows as $row) fputcsv($out, $row);
            fclose($out);
            exit;
        }
    } catch (Throwable $e) {
        // Fall through to page
    }
}

// ── Dashboard stats ───────────────────────────────────────────────────────
try { $totalStudents = (int) $db->query("SELECT COUNT(*) FROM users")->fetchColumn(); } catch (Throwable $e) { $totalStudents = 0; }
try { $totalQuizzes  = (int) $db->query("SELECT COUNT(*) FROM sat_quizzes")->fetchColumn(); } catch (Throwable $e) { $totalQuizzes = 0; }
try { $totalAttempts = (int) $db->query("SELECT COUNT(*) FROM sat_quiz_attempts")->fetchColumn(); } catch (Throwable $e) { $totalAttempts = 0; }
try { $totalQuestions = (int) $db->query("SELECT COUNT(*) FROM sat_quiz_questions")->fetchColumn(); } catch (Throwable $e) { $totalQuestions = 0; }

try { $draftCount = (int) $db->query("SELECT COUNT(*) FROM sat_quizzes WHERE status='draft'")->fetchColumn(); } catch (Throwable $e) { $draftCount = 0; }

$pageTitle  = 'Reports — Avidmock Admin';
$activePage = 'reports';
$extraHead  = <<<'CSS'
<style>
.main { margin-left: var(--sb-w); margin-top: var(--top-h); padding: 32px 28px; min-height: calc(100vh - var(--top-h)); }
.ph { margin-bottom: 24px; }
.ph-eyebrow { display: flex; align-items: center; gap: 6px; font-size: .5rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 5px; }
.ph-dot { width: 4px; height: 4px; border-radius: 50%; background: var(--ac); }
.ph-title { font-family: var(--fh); font-size: 1.875rem; font-weight: 900; color: var(--tx); letter-spacing: -.035em; }
.ph-sub { font-size: .875rem; color: var(--tx2); margin-top: 4px; }

.export-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
.export-card { background: var(--sf); border: 1px solid var(--bd); border-radius: 14px; padding: 24px; transition: all .18s; }
.export-card:hover { border-color: var(--ac); transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.08); }
.export-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: 14px; }
.export-icon svg { width: 20px; height: 20px; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; fill: none; }
.export-icon.blue { background: var(--blue2); } .export-icon.blue svg { stroke: var(--blue); }
.export-icon.green { background: rgba(31,226,144,.1); } .export-icon.green svg { stroke: var(--ac); }
.export-icon.purple { background: var(--purple2); } .export-icon.purple svg { stroke: var(--purple); }
.export-icon.amber { background: rgba(255,193,7,.1); } .export-icon.amber svg { stroke: var(--warn); }

.export-title { font-family: var(--fh); font-size: 1.05rem; font-weight: 900; color: var(--tx); margin-bottom: 4px; }
.export-desc { font-size: .8125rem; color: var(--tx3); line-height: 1.5; margin-bottom: 14px; }
.export-stat { font-family: var(--fm); font-size: .75rem; color: var(--tx2); margin-bottom: 14px; }
.export-stat strong { color: var(--tx); font-size: .875rem; }

@media (max-width: 768px) { .main { margin-left: 0; padding: 16px; } }
</style>
CSS;

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<header class="topbar">
    <button class="topbar-ham" onclick="openSidebar()" aria-label="Menu"><span></span><span></span><span></span></button>
    <div class="topbar-title">Reports</div>
    <div class="topbar-spacer"></div>
</header>

<main class="main">
    <div class="ph reveal d1">
        <div class="ph-eyebrow"><span class="ph-dot"></span>Data</div>
        <h1 class="ph-title">Reports & Export</h1>
        <p class="ph-sub">Download CSV exports of your platform data</p>
    </div>

    <div class="export-grid reveal d2">
        <div class="export-card">
            <div class="export-icon blue">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
            </div>
            <div class="export-title">Students</div>
            <div class="export-desc">All registered students with name, email, and signup date.</div>
            <div class="export-stat"><strong><?= number_format($totalStudents) ?></strong> records</div>
            <a href="?export=students" class="btn btn-sm btn-primary">
                <svg viewBox="0 0 24 24" style="width:14px;height:14px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Download CSV
            </a>
        </div>

        <div class="export-card">
            <div class="export-icon green">
                <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </div>
            <div class="export-title">Quiz Attempts</div>
            <div class="export-desc">All quiz attempts with scores, time spent, and completion status.</div>
            <div class="export-stat"><strong><?= number_format($totalAttempts) ?></strong> records</div>
            <a href="?export=quiz-attempts" class="btn btn-sm btn-primary">
                <svg viewBox="0 0 24 24" style="width:14px;height:14px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Download CSV
            </a>
        </div>

        <div class="export-card">
            <div class="export-icon purple">
                <svg viewBox="0 0 24 24"><path d="M9 2H4a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9"/><path d="M13 2l5 5-8 8H5v-5l8-8z"/></svg>
            </div>
            <div class="export-title">Quizzes</div>
            <div class="export-desc">All quizzes with title, section, status, and settings.</div>
            <div class="export-stat"><strong><?= number_format($totalQuizzes) ?></strong> records</div>
            <a href="?export=quizzes" class="btn btn-sm btn-primary">
                <svg viewBox="0 0 24 24" style="width:14px;height:14px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Download CSV
            </a>
        </div>

        <div class="export-card">
            <div class="export-icon amber">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div class="export-title">Questions</div>
            <div class="export-desc">All questions from the question bank with answers.</div>
            <div class="export-stat"><strong><?= number_format($totalQuestions) ?></strong> records</div>
            <a href="?export=questions" class="btn btn-sm btn-primary">
                <svg viewBox="0 0 24 24" style="width:14px;height:14px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Download CSV
            </a>
        </div>
    </div>
</main>
</body>
</html>
