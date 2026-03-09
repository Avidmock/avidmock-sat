<?php
/**
 * /admin/practice-tests/edit.php
 * Edit a practice test: title/details, sections, per-section question assignment, publish, delete.
 */
require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

$admin = currentAdmin();
$db    = Database::connect();

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrfToken = $_SESSION['csrf_token'];

$id = (int)($_GET['id'] ?? 0);
if ($id < 1) { header('Location: /admin/practice-tests/'); exit; }

/* ── Flash ─────────────────────────────────────────────────────────────── */
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

/* ── Load test ──────────────────────────────────────────────────────────── */
$test = null;
try {
    $s = $db->prepare("SELECT * FROM practice_tests WHERE id = ?");
    $s->execute([$id]);
    $test = $s->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
if (!$test) { header('Location: /admin/practice-tests/'); exit; }

/* ── Load sections with question counts ─────────────────────────────────── */
function loadSections(PDO $db, int $testId): array {
    return $db->prepare(
        "SELECT s.*,
                COUNT(sq.id) AS question_count
         FROM practice_test_sections s
         LEFT JOIN practice_test_section_questions sq ON sq.section_id = s.id
         WHERE s.test_id = ?
         GROUP BY s.id
         ORDER BY s.sort_order ASC, s.id ASC"
    )->execute([$testId]) ? $db->prepare(
        "SELECT s.*, COUNT(sq.id) AS question_count
         FROM practice_test_sections s
         LEFT JOIN practice_test_section_questions sq ON sq.section_id = s.id
         WHERE s.test_id = ?
         GROUP BY s.id ORDER BY s.sort_order ASC, s.id ASC"
    ) : null;
}
/* Proper way */
$secStmt = $db->prepare(
    "SELECT s.*, COUNT(sq.id) AS question_count
     FROM practice_test_sections s
     LEFT JOIN practice_test_section_questions sq ON sq.section_id = s.id
     WHERE s.test_id = ?
     GROUP BY s.id
     ORDER BY s.sort_order ASC, s.id ASC"
);
$secStmt->execute([$id]);
$sections = $secStmt->fetchAll(PDO::FETCH_ASSOC);

/* ── Attempt stats ───────────────────────────────────────────────────────── */
$stats = ['total'=>0,'submitted'=>0,'in_progress'=>0,'avg_score'=>null,'top_score'=>null];
try {
    $sr = $db->prepare(
        "SELECT
            COUNT(*) AS total,
            SUM(status='submitted')   AS submitted,
            SUM(status='in_progress') AS in_progress,
            ROUND(AVG(CASE WHEN status='submitted' THEN total_score END)) AS avg_score,
            MAX(CASE WHEN status='submitted' THEN total_score END)        AS top_score
         FROM practice_test_attempts WHERE test_id = ?"
    );
    $sr->execute([$id]);
    $row = $sr->fetch(PDO::FETCH_ASSOC);
    if ($row) $stats = $row;
} catch (Throwable) {}

/* ── Question bank — for the per-section picker ─────────────────────────── */
$questionBank = [];
try {
    $questionBank = $db->query(
        "SELECT id, subject, domain, skill, difficulty, question_type,
                LEFT(COALESCE(stem,''), 120) AS stem_short
         FROM questions
         ORDER BY subject ASC, difficulty ASC, id ASC
         LIMIT 2000"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable) {}

/* Index by id for fast lookup */
$questionById = [];
foreach ($questionBank as $q) $questionById[(int)$q['id']] = $q;

/* ── Questions already assigned per section ─────────────────────────────── */
$sectionQuestions = []; // section_id => [question_id, ...]
try {
    $sqRows = $db->prepare(
        "SELECT sq.section_id, sq.question_id
         FROM practice_test_section_questions sq
         JOIN practice_test_sections s ON s.id = sq.section_id
         WHERE s.test_id = ?
         ORDER BY sq.sort_order ASC, sq.id ASC"
    );
    $sqRows->execute([$id]);
    foreach ($sqRows->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sectionQuestions[(int)$row['section_id']][] = (int)$row['question_id'];
    }
} catch (Throwable) {}

/* ── Total question count across all sections ────────────────────────────── */
$totalQCount = 0;
foreach ($sections as $sec) $totalQCount += (int)($sec['question_count'] ?? 0);

/* ── Handle POST ─────────────────────────────────────────────────────────── */
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $action = $_POST['_action'] ?? 'update';

        /* ── DELETE TEST ─────────────────────────────────────────────── */
        if ($action === 'delete') {
            try {
                $db->prepare("DELETE FROM practice_tests WHERE id = ?")->execute([$id]);
                $_SESSION['flash'] = 'ok:Practice test deleted.';
                header('Location: /admin/practice-tests/'); exit;
            } catch (Throwable $e) {
                $errors[] = 'Delete failed: ' . $e->getMessage();
            }
        }

        /* ── UPDATE TEST DETAILS ─────────────────────────────────────── */
        if ($action === 'update') {
            $title        = trim($_POST['title']        ?? '');
            $description  = trim($_POST['description']  ?? '');
            $instructions = trim($_POST['instructions'] ?? '');
            $type         = $_POST['type']    ?? $test['type']    ?? 'full_length';
            $section      = $_POST['section'] ?? $test['section'] ?? 'full';
            $totalTime    = max(60, (int)($_POST['total_time'] ?? $test['total_time'] ?? 8040));
            $isPublished  = isset($_POST['is_published']) ? 1 : 0;

            if ($title === '') $errors[] = 'Title is required.';

            if (empty($errors)) {
                try {
                    $db->prepare(
                        "UPDATE practice_tests
                         SET title=?,description=?,instructions=?,type=?,section=?,total_time=?,is_published=?,updated_at=NOW()
                         WHERE id=?"
                    )->execute([$title,$description,$instructions,$type,$section,$totalTime,$isPublished,$id]);
                    $test['title']        = $title;
                    $test['description']  = $description;
                    $test['instructions'] = $instructions;
                    $test['type']         = $type;
                    $test['section']      = $section;
                    $test['total_time']   = $totalTime;
                    $test['is_published'] = $isPublished;
                    $flash = 'ok:Test details saved.';
                } catch (Throwable $e) {
                    $errors[] = 'Update failed: ' . $e->getMessage();
                }
            }
        }

        /* ── ADD SECTION ─────────────────────────────────────────────── */
        if ($action === 'add_section') {
            $secTitle   = trim($_POST['sec_title']   ?? '');
            $secSubject = $_POST['sec_subject'] ?? 'math';
            $secModule  = (int)($_POST['sec_module']  ?? 1);
            $secTime    = max(1, (int)($_POST['sec_time'] ?? 35)) * 60;
            $secOrder   = count($sections) + 1;

            if ($secTitle === '') $errors[] = 'Section title is required.';

            if (empty($errors)) {
                try {
                    $db->prepare(
                        "INSERT INTO practice_test_sections (test_id, title, subject, module, time_limit, sort_order)
                         VALUES (?,?,?,?,?,?)"
                    )->execute([$id, $secTitle, $secSubject, $secModule, $secTime, $secOrder]);
                    $flash = 'ok:Section "' . htmlspecialchars($secTitle) . '" added.';
                    header('Location: /admin/practice-tests/edit.php?id=' . $id . '&tab=sections'); exit;
                } catch (Throwable $e) {
                    $errors[] = 'Add section failed: ' . $e->getMessage();
                }
            }
        }

        /* ── DELETE SECTION ──────────────────────────────────────────── */
        if ($action === 'delete_section') {
            $secId = (int)($_POST['section_id'] ?? 0);
            try {
                $db->prepare("DELETE FROM practice_test_sections WHERE id = ? AND test_id = ?")->execute([$secId, $id]);
                $flash = 'ok:Section removed.';
                header('Location: /admin/practice-tests/edit.php?id=' . $id . '&tab=sections'); exit;
            } catch (Throwable $e) {
                $errors[] = 'Delete section failed: ' . $e->getMessage();
            }
        }

        /* ── SAVE SECTION QUESTIONS ──────────────────────────────────── */
        if ($action === 'save_section_questions') {
            $secId   = (int)($_POST['section_id'] ?? 0);
            $qIds    = array_filter(array_map('intval', $_POST['question_ids'] ?? []));

            /* Verify section belongs to this test */
            $chk = $db->prepare("SELECT id FROM practice_test_sections WHERE id = ? AND test_id = ?");
            $chk->execute([$secId, $id]);
            if (!$chk->fetch()) {
                $errors[] = 'Invalid section.';
            } else {
                try {
                    $db->prepare("DELETE FROM practice_test_section_questions WHERE section_id = ?")->execute([$secId]);
                    $ins = $db->prepare(
                        "INSERT INTO practice_test_section_questions (section_id, question_id, sort_order) VALUES (?,?,?)"
                    );
                    foreach (array_values($qIds) as $pos => $qid) {
                        $ins->execute([$secId, $qid, $pos + 1]);
                    }
                    $flash = 'ok:Questions saved for section.';
                    header('Location: /admin/practice-tests/edit.php?id=' . $id . '&tab=sections&open_section=' . $secId); exit;
                } catch (Throwable $e) {
                    $errors[] = 'Save questions failed: ' . $e->getMessage();
                }
            }
        }
    }
}

/* Active tab from URL */
$activeTab     = $_GET['tab']          ?? 'details';
$openSectionId = (int)($_GET['open_section'] ?? 0);

/* ── Helpers ─────────────────────────────────────────────────────────────── */
function fmtSecsEdit(int $s): string {
    if ($s <= 0) return '—';
    $h = floor($s / 3600); $m = floor(($s % 3600) / 60);
    return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
}
function diffColor(string $d): string {
    return ['easy'=>'var(--ac)','medium'=>'var(--warn)','hard'=>'var(--err)'][$d] ?? 'var(--tx3)';
}
function subjectLabel(string $s): string {
    return ['reading_writing'=>'R&W','math'=>'Math'][$s] ?? ucfirst($s);
}
$qTarget = ['full_length'=>98, 'mini'=>30, 'topic'=>20, 'timed'=>20];
$testTarget = $qTarget[$test['type'] ?? 'full_length'] ?? 98;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Edit: <?= htmlspecialchars(mb_substr($test['title'],0,50)) ?> — Avidmock Admin</title>
<meta name="robots" content="noindex,nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,600;9..40,700;9..40,800&family=Fraunces:ital,opsz,wght@0,9..144,900;1,9..144,300&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ─────────────────────────────────────────────
   TOKENS
───────────────────────────────────────────── */
:root {
    --ink:#0c1f1d; --ink2:#0e2522; --ink3:#112623;
    --dk:#143230;
    --ac:#1fe290; --ac2:#13c474;
    --ac3:rgba(31,226,144,.08); --ac4:rgba(31,226,144,.15);
    --tx:#e8f3f1; --tx2:#9dbfba; --tx3:#5a8580;
    --bd:rgba(255,255,255,.07); --bd2:rgba(255,255,255,.13);
    --sf:rgba(255,255,255,.04); --sf2:rgba(255,255,255,.07);
    --warn:#f59e0b; --warn2:rgba(245,158,11,.12);
    --err:#ef4444;  --err2:rgba(239,68,68,.12);
    --blue:#3b82f6; --blue2:rgba(59,130,246,.12);
    --purple:#8b5cf6; --purple2:rgba(139,92,246,.12);
    --ff:'DM Sans',sans-serif;
    --fh:'Fraunces',Georgia,serif;
    --fm:'DM Mono',monospace;
    --sb-w:240px; --top-h:60px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:var(--ff);background:var(--ink);color:var(--tx);-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit}
button{font-family:var(--ff);cursor:pointer}

/* ─────────────────────────────────────────────
   SIDEBAR
───────────────────────────────────────────── */
.sb{position:fixed;top:0;left:0;width:var(--sb-w);height:100vh;background:var(--ink2);border-right:1px solid var(--bd);display:flex;flex-direction:column;overflow-y:auto;z-index:300;transition:transform .32s cubic-bezier(.16,1,.3,1)}
.sb::-webkit-scrollbar{width:3px}.sb::-webkit-scrollbar-thumb{background:rgba(255,255,255,.06)}
.sb-logo{display:flex;align-items:center;gap:10px;padding:0 18px;height:var(--top-h);border-bottom:1px solid var(--bd);flex-shrink:0}
.sb-logo-mark{width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,var(--ac),var(--ac2));display:flex;align-items:center;justify-content:center}
.sb-logo-mark svg{width:17px;height:17px;fill:var(--dk)}
.sb-logo-name{font-size:.875rem;font-weight:800;color:var(--tx);letter-spacing:-.02em}
.sb-logo-sub{font-size:.5625rem;color:var(--tx3);font-weight:600;text-transform:uppercase;letter-spacing:.6px}
.sb-nav{flex:1;padding:10px 0 16px}
.sb-group{padding:16px 18px 5px;font-size:.5rem;font-weight:700;color:var(--tx3);text-transform:uppercase;letter-spacing:1.2px}
.sb-link{display:flex;align-items:center;gap:9px;padding:8px 10px;margin:1px 8px;border-radius:9px;font-size:.8125rem;font-weight:600;color:var(--tx2);transition:all .16s;position:relative}
.sb-link:hover{background:var(--sf2);color:var(--tx)}
.sb-link.active{background:var(--ac3);color:var(--ac)}
.sb-link.active::before{content:'';position:absolute;left:-10px;top:50%;transform:translateY(-50%);width:3px;height:55%;background:var(--ac);border-radius:0 2px 2px 0}
.sb-ico{width:15px;height:15px;flex-shrink:0;stroke:currentColor;fill:none;stroke-width:1.85;stroke-linecap:round;stroke-linejoin:round}
.sb-foot{margin:8px;padding:10px 12px;background:var(--sf);border:1px solid var(--bd);border-radius:10px;display:flex;align-items:center;gap:9px}
.sb-ava{width:30px;height:30px;border-radius:8px;background:linear-gradient(135deg,var(--ac),var(--ac2));display:flex;align-items:center;justify-content:center;font-size:.6875rem;font-weight:800;color:var(--dk);flex-shrink:0}
.sb-foot-name{font-size:.75rem;font-weight:700;color:var(--tx)}.sb-foot-role{font-size:.5625rem;color:var(--tx3);text-transform:capitalize}
.sb-out{margin-left:auto;padding:5px;background:none;border:none;cursor:pointer;color:var(--tx3);line-height:0;border-radius:6px;transition:all .16s}
.sb-out:hover{background:var(--err2);color:var(--err)}
.sb-out svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:250;opacity:0;transition:opacity .28s;pointer-events:none}
.sb-overlay.show{opacity:1;pointer-events:all}

/* ─────────────────────────────────────────────
   TOPBAR
───────────────────────────────────────────── */
.topbar{position:fixed;top:0;left:var(--sb-w);right:0;height:var(--top-h);background:rgba(12,31,29,.92);backdrop-filter:blur(16px);border-bottom:1px solid var(--bd);display:flex;align-items:center;padding:0 28px;gap:10px;z-index:200}
.topbar-ham{display:none;width:34px;height:34px;border-radius:8px;border:1px solid var(--bd);background:var(--sf);align-items:center;justify-content:center;cursor:pointer;flex-direction:column;gap:4px;padding:9px}
.topbar-ham span{display:block;height:1.5px;background:var(--tx2);border-radius:1px;width:100%}
.tb-bc{display:flex;align-items:center;gap:6px;font-size:.8125rem;font-weight:600;color:var(--tx3);overflow:hidden}
.tb-bc a{color:var(--tx3);font-family:var(--fh);font-style:italic;font-weight:300;transition:color .15s;white-space:nowrap}
.tb-bc a:hover{color:var(--ac)}
.tb-bc-sep{opacity:.3;flex-shrink:0}
.tb-bc-cur{color:var(--tx);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.topbar-spacer{flex:1}

/* ─────────────────────────────────────────────
   BUTTONS
───────────────────────────────────────────── */
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:9px;font-family:var(--ff);font-size:.8125rem;font-weight:700;border:1.5px solid transparent;cursor:pointer;transition:all .18s;white-space:nowrap}
.btn svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
.btn-primary{background:var(--ac);color:var(--dk);border-color:var(--ac)}
.btn-primary:hover{background:var(--ac2);transform:translateY(-1px);box-shadow:0 4px 14px rgba(31,226,144,.2)}
.btn-ghost{background:var(--sf);border-color:var(--bd);color:var(--tx2)}
.btn-ghost:hover{background:var(--sf2);color:var(--tx);border-color:var(--bd2)}
.btn-sm{padding:5px 10px;font-size:.6875rem;border-radius:7px}
.btn-danger{background:var(--err);color:#fff;border-color:var(--err)}
.btn-danger:hover{background:#dc2626}

/* ─────────────────────────────────────────────
   LAYOUT
───────────────────────────────────────────── */
.main{margin-left:var(--sb-w);margin-top:var(--top-h);padding:28px}
.editor-layout{display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start}
.editor-main{display:flex;flex-direction:column;gap:16px}
.editor-side{display:flex;flex-direction:column;gap:14px;position:sticky;top:calc(var(--top-h)+20px)}

/* ─────────────────────────────────────────────
   STATS STRIP
───────────────────────────────────────────── */
.stats-strip{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:24px}
.stat-box{background:var(--ink2);border:1px solid var(--bd);border-radius:12px;padding:14px 16px}
.stat-lbl{font-size:.5rem;font-weight:700;color:var(--tx3);text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px}
.stat-val{font-family:var(--fh);font-size:1.5rem;font-weight:900;color:var(--tx);line-height:1;letter-spacing:-.04em;margin-bottom:2px}
.stat-sub{font-size:.5625rem;color:var(--tx3)}

/* ─────────────────────────────────────────────
   TABS
───────────────────────────────────────────── */
.tab-bar{display:flex;gap:2px;background:var(--sf);border-radius:10px;padding:3px;margin-bottom:0}
.tab-btn{flex:1;padding:8px 12px;border-radius:8px;font-size:.75rem;font-weight:700;color:var(--tx3);cursor:pointer;transition:all .16s;text-align:center;border:none;background:none;font-family:var(--ff);display:flex;align-items:center;justify-content:center;gap:5px}
.tab-btn:hover{color:var(--tx2)}
.tab-btn.active{background:var(--ink2);color:var(--tx);box-shadow:0 1px 4px rgba(0,0,0,.3)}
.tab-badge{font-family:var(--fm);font-size:.5625rem;background:var(--ac3);color:var(--ac);padding:1px 6px;border-radius:50px}
.tab-panel{display:none;flex-direction:column;gap:16px}
.tab-panel.active{display:flex}

/* ─────────────────────────────────────────────
   CARDS
───────────────────────────────────────────── */
.card{background:var(--ink2);border:1px solid var(--bd);border-radius:16px;overflow:hidden}
.card-head{padding:16px 20px;border-bottom:1px solid var(--bd);display:flex;align-items:center;gap:10px}
.card-head-ico{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.card-head-ico svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.card-title{font-size:.875rem;font-weight:700;color:var(--tx)}
.card-sub{font-size:.625rem;color:var(--tx3);margin-top:1px}
.card-body{padding:20px;display:flex;flex-direction:column;gap:16px}

/* ─────────────────────────────────────────────
   FORM FIELDS
───────────────────────────────────────────── */
.field{display:flex;flex-direction:column;gap:5px}
.field-row{display:grid;gap:12px}
.fr-2{grid-template-columns:1fr 1fr}
.fr-3{grid-template-columns:1fr 1fr 1fr}
.fr-4{grid-template-columns:1fr 1fr 1fr 1fr}
label.fl{font-size:.5625rem;font-weight:700;color:var(--tx3);text-transform:uppercase;letter-spacing:.8px}
.hint{font-size:.5625rem;color:var(--tx3);margin-top:2px}
.fi,.fs,.fta{background:var(--sf);border:1.5px solid var(--bd);border-radius:9px;color:var(--tx);font-family:var(--ff);font-size:.8125rem;padding:9px 12px;outline:none;transition:border-color .16s,background .16s;width:100%}
.fi:focus,.fs:focus,.fta:focus{border-color:var(--ac);background:rgba(31,226,144,.03)}
.fi.err{border-color:var(--err)}
.fs option{background:var(--ink2)}
.fta{resize:vertical;min-height:80px;line-height:1.55}
.char-count{font-family:var(--fm);font-size:.5rem;color:var(--tx3);text-align:right;margin-top:2px}
.char-count.warn{color:var(--warn)}.char-count.over{color:var(--err)}

/* ─────────────────────────────────────────────
   PUBLISH TOGGLE
───────────────────────────────────────────── */
.pub-toggle{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border-radius:10px;border:1.5px solid var(--bd);background:var(--sf);cursor:pointer;transition:all .18s}
.pub-toggle:has(input:checked){border-color:var(--ac);background:var(--ac3)}
.pub-toggle input{position:absolute;opacity:0;width:0;height:0}
.pub-dot{width:8px;height:8px;border-radius:50%;background:var(--tx3);transition:background .2s;flex-shrink:0}
.pub-toggle:has(input:checked) .pub-dot{background:var(--ac)}
.pub-text{font-size:.8125rem;font-weight:700;color:var(--tx)}
.pub-sub{font-size:.5625rem;color:var(--tx3);margin-top:1px}
.pub-switch{width:34px;height:18px;border-radius:9px;background:var(--sf2);border:1.5px solid var(--bd);position:relative;transition:all .2s;flex-shrink:0}
.pub-switch::after{content:'';position:absolute;left:2px;top:50%;transform:translateY(-50%);width:10px;height:10px;border-radius:50%;background:var(--tx3);transition:all .2s}
.pub-toggle:has(input:checked) .pub-switch{background:rgba(31,226,144,.2);border-color:var(--ac)}
.pub-toggle:has(input:checked) .pub-switch::after{left:calc(100% - 12px);background:var(--ac)}

/* ─────────────────────────────────────────────
   SECTIONS LIST
───────────────────────────────────────────── */
.section-list{display:flex;flex-direction:column;gap:12px}
.section-item{background:var(--ink3);border:1px solid var(--bd);border-radius:12px;overflow:hidden;transition:border-color .2s}
.section-item.open{border-color:var(--bd2)}
.section-hd{display:flex;align-items:center;gap:12px;padding:14px 16px;cursor:pointer;user-select:none}
.section-hd:hover{background:rgba(255,255,255,.02)}
.section-drag{color:var(--tx3);font-size:.875rem;flex-shrink:0;opacity:.4}
.section-subject-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.section-info{flex:1;min-width:0}
.section-name{font-size:.875rem;font-weight:700;color:var(--tx);margin-bottom:2px}
.section-meta{font-size:.5625rem;color:var(--tx3);display:flex;align-items:center;gap:8px}
.q-pill{font-size:.5625rem;font-weight:700;padding:2px 8px;border-radius:5px;white-space:nowrap}
.q-pill-ok{background:var(--ac3);color:var(--ac)}
.q-pill-warn{background:var(--warn2);color:var(--warn)}
.q-pill-empty{background:var(--sf2);color:var(--tx3)}
.section-chevron{width:14px;height:14px;stroke:var(--tx3);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;transition:transform .2s;flex-shrink:0}
.section-item.open .section-chevron{transform:rotate(90deg)}
.section-body{display:none;border-top:1px solid var(--bd)}
.section-item.open .section-body{display:block}

/* ─────────────────────────────────────────────
   QUESTION PICKER (per section)
───────────────────────────────────────────── */
.q-picker-header{padding:12px 16px;background:var(--sf);border-bottom:1px solid var(--bd);display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.q-filter-input{flex:1;min-width:140px;background:rgba(255,255,255,.04);border:1px solid var(--bd);border-radius:7px;color:var(--tx);font-family:var(--ff);font-size:.75rem;padding:6px 10px;outline:none}
.q-filter-input:focus{border-color:var(--ac)}
.q-filter-input::placeholder{color:var(--tx3)}
.q-filter-sel{background:rgba(255,255,255,.04);border:1px solid var(--bd);border-radius:7px;color:var(--tx2);font-family:var(--ff);font-size:.6875rem;padding:5px 8px;outline:none}
.q-filter-sel option{background:var(--ink2)}
.q-sel-count{font-size:.6875rem;color:var(--ac);font-weight:700;margin-left:auto;white-space:nowrap}

.q-list{max-height:340px;overflow-y:auto;padding:8px}
.q-list::-webkit-scrollbar{width:4px}.q-list::-webkit-scrollbar-thumb{background:var(--bd2);border-radius:2px}
.q-row{display:flex;align-items:flex-start;gap:8px;padding:8px 10px;border-radius:8px;cursor:pointer;border:1px solid transparent;transition:all .12s;margin-bottom:2px}
.q-row:hover{background:var(--sf2)}
.q-row.selected{background:var(--ac3);border-color:var(--ac4)}
.q-row input{accent-color:var(--ac);width:13px;height:13px;flex-shrink:0;cursor:pointer;margin-top:2px}
.q-row-content{flex:1;min-width:0}
.q-row-stem{font-size:.6875rem;color:var(--tx2);line-height:1.45;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
.q-row.selected .q-row-stem{color:var(--tx)}
.q-row-tags{display:flex;align-items:center;gap:4px;margin-top:4px;flex-wrap:wrap}
.q-tag{font-size:.375rem;font-weight:800;text-transform:uppercase;padding:1px 5px;border-radius:3px;letter-spacing:.3px}
.qt-easy{background:rgba(31,226,144,.12);color:var(--ac2)}
.qt-medium{background:var(--warn2);color:var(--warn)}
.qt-hard{background:var(--err2);color:var(--err)}
.qt-rw{background:var(--blue2);color:var(--blue)}
.qt-math{background:var(--purple2);color:var(--purple)}
.qt-type{background:var(--sf2);color:var(--tx3)}

.q-picker-footer{padding:10px 16px;border-top:1px solid var(--bd);display:flex;align-items:center;gap:8px;background:var(--sf)}

/* ─────────────────────────────────────────────
   ADD SECTION FORM
───────────────────────────────────────────── */
.add-section-form{background:var(--sf);border:1px solid var(--bd);border-radius:12px;padding:16px;display:flex;flex-direction:column;gap:12px}
.add-section-title{font-size:.75rem;font-weight:700;color:var(--tx);margin-bottom:4px}

/* ─────────────────────────────────────────────
   DANGER ZONE
───────────────────────────────────────────── */
.danger-zone{background:rgba(239,68,68,.04);border:1px solid rgba(239,68,68,.15);border-radius:14px;padding:18px}
.danger-title{font-size:.75rem;font-weight:700;color:var(--err);margin-bottom:6px}
.danger-body{font-size:.6875rem;color:var(--tx3);margin-bottom:14px;line-height:1.5}

/* ─────────────────────────────────────────────
   FLASH / ERRORS
───────────────────────────────────────────── */
.flash{padding:11px 18px;border-radius:10px;font-size:.8125rem;font-weight:600;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.flash-ok{background:var(--ac3);border:1px solid var(--ac4);color:var(--ac)}
.flash-error{background:var(--err2);border:1px solid rgba(239,68,68,.25);color:var(--err)}
.flash svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0}
.errors-box{background:var(--err2);border:1px solid rgba(239,68,68,.25);border-radius:10px;padding:14px 16px;margin-bottom:16px;display:flex;flex-direction:column;gap:4px}
.errors-box li{font-size:.8125rem;color:var(--err)}

/* ─────────────────────────────────────────────
   MODAL
───────────────────────────────────────────── */
.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);z-index:500;display:none;align-items:center;justify-content:center;padding:20px}
.modal-backdrop.open{display:flex}
.modal{background:var(--ink2);border:1px solid var(--bd2);border-radius:18px;padding:28px;width:100%;max-width:420px;box-shadow:0 32px 80px rgba(0,0,0,.6)}
.modal-ico{width:44px;height:44px;border-radius:12px;background:var(--err2);display:flex;align-items:center;justify-content:center;margin-bottom:14px}
.modal-ico svg{width:20px;height:20px;stroke:var(--err);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.modal-title{font-family:var(--fh);font-size:1.125rem;font-weight:900;color:var(--tx);margin-bottom:8px}
.modal-body{font-size:.8125rem;color:var(--tx2);margin-bottom:22px;line-height:1.6}
.modal-actions{display:flex;gap:8px;justify-content:flex-end}

/* ─────────────────────────────────────────────
   REVEAL
───────────────────────────────────────────── */
.reveal{opacity:0;transform:translateY(12px);animation:rev .4s cubic-bezier(.16,1,.3,1) forwards}
@keyframes rev{to{opacity:1;transform:none}}
.d1{animation-delay:.04s}.d2{animation-delay:.08s}.d3{animation-delay:.12s}.d4{animation-delay:.16s}

/* ─────────────────────────────────────────────
   RESPONSIVE
───────────────────────────────────────────── */
@media(max-width:1100px){
    .editor-layout{grid-template-columns:1fr}
    .editor-side{position:static}
    .stats-strip{grid-template-columns:repeat(3,1fr)}
}
@media(max-width:768px){
    :root{--sb-w:0px}
    .sb{transform:translateX(-240px);--sb-w:240px}
    .sb.open{transform:translateX(0)}
    .sb-overlay{display:block}
    .topbar{left:0;padding:0 16px}
    .topbar-ham{display:flex}
    .main{margin-left:0;padding:16px}
    .stats-strip{grid-template-columns:1fr 1fr}
    .fr-2,.fr-3,.fr-4{grid-template-columns:1fr}
}
@media(max-width:500px){.stats-strip{grid-template-columns:1fr}}
</style>
</head>
<body>

<div class="sb-overlay" id="sbOverlay"></div>

<!-- ── SIDEBAR ──────────────────────────────────────────────────────── -->
<aside class="sb" id="sidebar">
    <a href="/admin/" class="sb-logo">
        <div class="sb-logo-mark"><svg viewBox="0 0 20 20"><path d="M10 2L13 8H19L14 12.5L16 18.5L10 15L4 18.5L6 12.5L1 8H7L10 2Z"/></svg></div>
        <div><div class="sb-logo-name">Avidmock SAT</div><div class="sb-logo-sub">Admin Panel</div></div>
    </a>
    <nav class="sb-nav">
        <div class="sb-group">Overview</div>
        <a href="/admin/" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>Dashboard</a>
        <a href="/admin/analytics/" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>Analytics</a>
        <div class="sb-group">Content</div>
        <a href="/admin/questions/" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>Question Bank</a>
        <a href="/admin/practice-tests/" class="sb-link active"><svg class="sb-ico" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>Practice Tests</a>
        <a href="/admin/quizzes/" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><path d="M9 2H4a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9"/><path d="M13 2l5 5-8 8H5v-5l8-8z"/></svg>Quizzes</a>
        <div class="sb-group">Students</div>
        <a href="/admin/students/" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>All Students</a>
    </nav>
    <div class="sb-foot">
        <div class="sb-ava"><?= strtoupper(substr($admin['name'] ?? 'A', 0, 1)) ?></div>
        <div><div class="sb-foot-name"><?= htmlspecialchars($admin['name'] ?? 'Admin') ?></div><div class="sb-foot-role"><?= htmlspecialchars($admin['role'] ?? 'admin') ?></div></div>
        <a href="/admin/auth/logout.php" class="sb-out"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></a>
    </div>
</aside>

<!-- ── TOPBAR ───────────────────────────────────────────────────────── -->
<header class="topbar">
    <button class="topbar-ham" onclick="openSidebar()"><span></span><span></span><span></span></button>
    <div class="tb-bc">
        <a href="/admin/practice-tests/">Practice Tests</a>
        <span class="tb-bc-sep">›</span>
        <span class="tb-bc-cur"><?= htmlspecialchars(mb_substr($test['title'], 0, 45)) ?></span>
    </div>
    <div class="topbar-spacer"></div>
    <?php if ((int)$stats['submitted'] > 0): ?>
    <a href="/admin/practice-tests/results.php?test_id=<?= $id ?>" class="btn btn-ghost btn-sm">
        <svg viewBox="0 0 24 24"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>
        Results
    </a>
    <?php endif ?>
    <a href="/admin/practice-tests/" class="btn btn-ghost btn-sm">
        <svg viewBox="0 0 24 24"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
        Back
    </a>
</header>

<!-- ── MAIN ─────────────────────────────────────────────────────────── -->
<main class="main">

    <?php if ($flash): [$ft, $fm] = explode(':', $flash, 2); ?>
    <div class="flash flash-<?= $ft === 'ok' ? 'ok' : 'error' ?> reveal">
        <?php if ($ft === 'ok'): ?><svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        <?php else: ?><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg><?php endif ?>
        <?= htmlspecialchars($fm) ?>
    </div>
    <?php endif ?>

    <?php if (!empty($errors)): ?>
    <ul class="errors-box reveal">
        <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach ?>
    </ul>
    <?php endif ?>

    <!-- ── STATS STRIP ────────────────────────────────── -->
    <div class="stats-strip reveal d1">
        <div class="stat-box">
            <div class="stat-lbl">Sections</div>
            <div class="stat-val"><?= count($sections) ?></div>
            <div class="stat-sub">configured</div>
        </div>
        <div class="stat-box">
            <div class="stat-lbl">Questions</div>
            <div class="stat-val" style="color:<?= $totalQCount >= $testTarget ? 'var(--ac)' : 'var(--warn)' ?>">
                <?= $totalQCount ?>
            </div>
            <div class="stat-sub">of <?= $testTarget ?> target</div>
        </div>
        <div class="stat-box">
            <div class="stat-lbl">Attempts</div>
            <div class="stat-val"><?= number_format((int)$stats['total']) ?></div>
            <div class="stat-sub"><?= (int)$stats['submitted'] ?> submitted</div>
        </div>
        <div class="stat-box">
            <div class="stat-lbl">Avg Score</div>
            <div class="stat-val" style="color:<?= $stats['avg_score'] ? ($stats['avg_score'] >= 1200 ? 'var(--ac)' : ($stats['avg_score'] >= 900 ? 'var(--warn)' : 'var(--err)')) : 'var(--tx3)' ?>">
                <?= $stats['avg_score'] ? number_format((float)$stats['avg_score']) : '—' ?>
            </div>
            <div class="stat-sub">out of 1600</div>
        </div>
        <div class="stat-box">
            <div class="stat-lbl">Status</div>
            <div class="stat-val" style="font-size:1rem;color:<?= $test['is_published'] ? 'var(--ac)' : 'var(--tx3)' ?>;padding-top:4px">
                <?= $test['is_published'] ? '● Live' : '○ Draft' ?>
            </div>
            <div class="stat-sub"><?= $test['is_published'] ? 'visible to students' : 'hidden from students' ?></div>
        </div>
    </div>

    <!-- ── EDITOR LAYOUT ──────────────────────────────── -->
    <div class="editor-layout">

        <!-- ── LEFT ─────────────────────────────────── -->
        <div class="editor-main">

            <!-- Tab Bar -->
            <div class="tab-bar reveal d2" id="tabBar">
                <button type="button" class="tab-btn <?= $activeTab==='details'?'active':'' ?>" onclick="switchTab('details',this)">
                    <svg style="width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/></svg>
                    Details
                </button>
                <button type="button" class="tab-btn <?= $activeTab==='sections'?'active':'' ?>" onclick="switchTab('sections',this)">
                    <svg style="width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round" viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                    Sections
                    <span class="tab-badge" id="secBadge"><?= count($sections) ?></span>
                </button>
                <button type="button" class="tab-btn" onclick="switchTab('settings',this)" <?= $activeTab==='settings'?'class="tab-btn active"':'' ?>>
                    <svg style="width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                    Settings
                </button>
            </div>

            <!-- ═══ TAB: DETAILS ══════════════════════ -->
            <div class="tab-panel <?= $activeTab==='details'?'active':'' ?>" id="tab-details">
                <form method="POST" id="detailsForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="_action"    value="update">

                    <div class="card reveal d3">
                        <div class="card-head">
                            <div class="card-head-ico" style="background:var(--ac3);color:var(--ac)">
                                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            </div>
                            <div>
                                <div class="card-title">Test Details</div>
                                <div class="card-sub">Title and description shown to students</div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="field">
                                <label class="fl" for="title">Title *</label>
                                <input type="text" id="title" name="title" class="fi"
                                       value="<?= htmlspecialchars($test['title']) ?>"
                                       maxlength="255" required
                                       oninput="charCount(this,255,'titleCC')">
                                <div class="char-count" id="titleCC"><?= mb_strlen($test['title']) ?> / 255</div>
                            </div>

                            <div class="field">
                                <label class="fl" for="description">Description</label>
                                <textarea id="description" name="description" class="fta"
                                          maxlength="1000"
                                          placeholder="Brief overview shown in the test library…"
                                          oninput="charCount(this,1000,'descCC')"><?= htmlspecialchars($test['description'] ?? '') ?></textarea>
                                <div class="char-count" id="descCC"><?= mb_strlen($test['description'] ?? '') ?> / 1000</div>
                            </div>

                            <div class="field">
                                <label class="fl" for="instructions">Instructions (optional)</label>
                                <textarea id="instructions" name="instructions" class="fta" style="min-height:60px"
                                          placeholder="Special notes shown to students before the test begins…"><?= htmlspecialchars($test['instructions'] ?? '') ?></textarea>
                            </div>

                            <div class="field-row fr-2">
                                <div class="field">
                                    <label class="fl" for="type">Test Type</label>
                                    <select id="type" name="type" class="fs">
                                        <option value="full_length" <?= ($test['type']??'')==='full_length'?'selected':'' ?>>Full Length</option>
                                        <option value="mini"        <?= ($test['type']??'')==='mini'?'selected':'' ?>>Mini Test</option>
                                        <option value="topic"       <?= ($test['type']??'')==='topic'?'selected':'' ?>>Topic Drill</option>
                                        <option value="timed"       <?= ($test['type']??'')==='timed'?'selected':'' ?>>Timed Challenge</option>
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="fl" for="section">Section Focus</label>
                                    <select id="section" name="section" class="fs">
                                        <option value="full"            <?= ($test['section']??'')==='full'?'selected':'' ?>>Full SAT</option>
                                        <option value="math"            <?= ($test['section']??'')==='math'?'selected':'' ?>>Math Only</option>
                                        <option value="reading_writing" <?= ($test['section']??'')==='reading_writing'?'selected':'' ?>>R&amp;W Only</option>
                                    </select>
                                </div>
                            </div>

                            <div class="field">
                                <label class="fl" for="total_time">Total Time (minutes)</label>
                                <input type="number" id="total_time" name="total_time" class="fi" style="max-width:140px"
                                       value="<?= round(($test['total_time'] ?? 8040) / 60) ?>"
                                       min="1" max="600">
                                <div class="hint">Full SAT = 134 min (2×32 R&W + 2×35 Math)</div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary reveal d4"
                            style="align-self:flex-start"
                            onclick="document.getElementById('total_time').value = document.getElementById('total_time').value * 60 || document.getElementById('total_time').value">
                        <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        Save Details
                    </button>
                </form>
            </div>

            <!-- ═══ TAB: SECTIONS ════════════════════ -->
            <div class="tab-panel <?= $activeTab==='sections'?'active':'' ?>" id="tab-sections">

                <!-- Sections list -->
                <?php if (!empty($sections)): ?>
                <div class="section-list reveal d3" id="sectionList">
                <?php foreach ($sections as $sec):
                    $secId     = (int)$sec['id'];
                    $secQs     = $sectionQuestions[$secId] ?? [];
                    $secQCount = count($secQs);
                    $subjColor = $sec['subject'] === 'math' ? 'var(--purple)' : 'var(--blue)';
                    $isOpen    = ($openSectionId === $secId);
                    // Expected question count per section for standard SAT
                    $expectedQ = ($sec['subject'] === 'math') ? 22 : 27;
                    $qPillCls  = $secQCount === 0 ? 'q-pill-empty' : ($secQCount >= $expectedQ ? 'q-pill-ok' : 'q-pill-warn');
                ?>
                <div class="section-item <?= $isOpen ? 'open' : '' ?>" id="section-<?= $secId ?>">

                    <!-- Header -->
                    <div class="section-hd" onclick="toggleSection(<?= $secId ?>)">
                        <span class="section-drag">⠿</span>
                        <div class="section-subject-dot" style="background:<?= $subjColor ?>"></div>
                        <div class="section-info">
                            <div class="section-name"><?= htmlspecialchars($sec['title']) ?></div>
                            <div class="section-meta">
                                <span><?= htmlspecialchars(subjectLabel($sec['subject'])) ?></span>
                                <span>·</span>
                                <span>Module <?= (int)($sec['module'] ?? 1) ?></span>
                                <span>·</span>
                                <span><?= fmtSecsEdit((int)$sec['time_limit']) ?></span>
                            </div>
                        </div>
                        <span class="q-pill <?= $qPillCls ?>"><?= $secQCount ?> Q<?php if ($secQCount < $expectedQ): ?> / <?= $expectedQ ?><?php endif ?></span>
                        <svg class="section-chevron" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                    </div>

                    <!-- Question picker body -->
                    <div class="section-body">
                        <form method="POST" id="secForm-<?= $secId ?>">
                            <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="_action"     value="save_section_questions">
                            <input type="hidden" name="section_id"  value="<?= $secId ?>">

                            <!-- Filter bar -->
                            <div class="q-picker-header">
                                <input type="text" class="q-filter-input"
                                       placeholder="Search questions…"
                                       oninput="filterQs(<?= $secId ?>, this.value)">
                                <select class="q-filter-sel" onchange="filterQsDiff(<?= $secId ?>, this.value)">
                                    <option value="">All difficulties</option>
                                    <option value="easy">Easy</option>
                                    <option value="medium">Medium</option>
                                    <option value="hard">Hard</option>
                                </select>
                                <select class="q-filter-sel" onchange="filterQsSubj(<?= $secId ?>, this.value)">
                                    <option value="">All subjects</option>
                                    <option value="reading_writing" <?= $sec['subject']==='reading_writing'?'selected':'' ?>>R&amp;W</option>
                                    <option value="math" <?= $sec['subject']==='math'?'selected':'' ?>>Math</option>
                                </select>
                                <button type="button" class="btn btn-sm btn-ghost" onclick="selectAllQsInSection(<?= $secId ?>)">All</button>
                                <button type="button" class="btn btn-sm btn-ghost" onclick="clearAllQsInSection(<?= $secId ?>)">Clear</button>
                                <span class="q-sel-count" id="secQCount-<?= $secId ?>"><?= $secQCount ?> selected</span>
                            </div>

                            <!-- Question list -->
                            <div class="q-list" id="qlist-<?= $secId ?>">
                                <?php foreach ($questionBank as $q):
                                    $qid      = (int)$q['id'];
                                    $checked  = in_array($qid, $secQs);
                                    $diff     = $q['difficulty'] ?? 'medium';
                                    $subj     = $q['subject'] ?? 'math';
                                    $domain   = htmlspecialchars($q['domain'] ?? '');
                                    $skill    = htmlspecialchars(mb_substr($q['skill'] ?? '', 0, 30));
                                    $stemShrt = htmlspecialchars(mb_substr(strip_tags($q['stem_short'] ?? ''), 0, 110));
                                ?>
                                <label class="q-row <?= $checked ? 'selected' : '' ?>"
                                       data-stem="<?= strtolower($stemShrt) ?>"
                                       data-diff="<?= $diff ?>"
                                       data-subj="<?= $subj ?>">
                                    <input type="checkbox" name="question_ids[]"
                                           value="<?= $qid ?>"
                                           <?= $checked ? 'checked' : '' ?>
                                           onchange="updateSecCount(<?= $secId ?>)">
                                    <div class="q-row-content">
                                        <div class="q-row-stem"><?= $stemShrt ?: '(no stem preview)' ?></div>
                                        <div class="q-row-tags">
                                            <span class="q-tag <?= $subj==='math' ? 'qt-math' : 'qt-rw' ?>"><?= subjectLabel($subj) ?></span>
                                            <span class="q-tag qt-<?= $diff ?>"><?= ucfirst($diff) ?></span>
                                            <?php if ($q['question_type'] ?? ''): ?>
                                            <span class="q-tag qt-type"><?= strtoupper($q['question_type']) ?></span>
                                            <?php endif ?>
                                            <?php if ($domain): ?>
                                            <span class="q-tag qt-type"><?= $domain ?></span>
                                            <?php endif ?>
                                        </div>
                                    </div>
                                </label>
                                <?php endforeach ?>
                                <?php if (empty($questionBank)): ?>
                                <div style="text-align:center;padding:32px;color:var(--tx3);font-size:.8125rem">
                                    No questions in the question bank yet.<br>
                                    <a href="/admin/questions/create.php" style="color:var(--ac);margin-top:6px;display:inline-block">Add questions →</a>
                                </div>
                                <?php endif ?>
                            </div>

                            <!-- Footer -->
                            <div class="q-picker-footer">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
                                    Save Questions
                                </button>
                                <button type="button" class="btn btn-ghost btn-sm"
                                        onclick="confirmDeleteSection(<?= $secId ?>, '<?= htmlspecialchars(addslashes($sec['title']), ENT_QUOTES) ?>')">
                                    <svg viewBox="0 0 24 24" style="stroke:var(--err)"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
                                    Remove Section
                                </button>
                                <span style="font-size:.5625rem;color:var(--tx3);margin-left:auto">
                                    #<?= $secId ?> · sort <?= (int)($sec['sort_order'] ?? 0) ?>
                                </span>
                            </div>
                        </form>
                    </div>

                </div>
                <?php endforeach ?>
                </div>
                <?php else: ?>
                <div style="text-align:center;padding:48px 20px;color:var(--tx3);background:var(--ink2);border:1px solid var(--bd);border-radius:16px">
                    <div style="font-size:2rem;margin-bottom:12px">📋</div>
                    <div style="font-size:.9375rem;font-weight:700;color:var(--tx);margin-bottom:6px">No sections yet</div>
                    <div style="font-size:.8125rem">Add sections below to start assigning questions.</div>
                </div>
                <?php endif ?>

                <!-- Add Section Form -->
                <div class="add-section-form reveal d4">
                    <div class="add-section-title">+ Add New Section</div>
                    <form method="POST" id="addSecForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="_action"    value="add_section">
                        <div style="display:flex;flex-direction:column;gap:10px">
                            <div class="field-row fr-2">
                                <div class="field">
                                    <label class="fl" for="sec_title">Section Title *</label>
                                    <input type="text" id="sec_title" name="sec_title" class="fi"
                                           placeholder="e.g. Reading & Writing — Module 1" required>
                                </div>
                                <div class="field">
                                    <label class="fl" for="sec_subject">Subject</label>
                                    <select id="sec_subject" name="sec_subject" class="fs">
                                        <option value="reading_writing">Reading &amp; Writing</option>
                                        <option value="math">Math</option>
                                    </select>
                                </div>
                            </div>
                            <div class="field-row fr-3">
                                <div class="field">
                                    <label class="fl" for="sec_module">Module</label>
                                    <select id="sec_module" name="sec_module" class="fs">
                                        <option value="1">Module 1</option>
                                        <option value="2">Module 2</option>
                                    </select>
                                </div>
                                <div class="field">
                                    <label class="fl" for="sec_time">Time (minutes)</label>
                                    <input type="number" id="sec_time" name="sec_time" class="fi" value="35" min="1" max="120">
                                </div>
                                <div class="field" style="justify-content:flex-end">
                                    <label class="fl">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end">
                                        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                        Add Section
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

            </div><!-- end tab-sections -->

            <!-- ═══ TAB: SETTINGS ════════════════════ -->
            <div class="tab-panel <?= $activeTab==='settings'?'active':'' ?>" id="tab-settings">
                <form method="POST" id="settingsForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="_action"    value="update">
                    <input type="hidden" name="title"        value="<?= htmlspecialchars($test['title']) ?>">
                    <input type="hidden" name="description"  value="<?= htmlspecialchars($test['description'] ?? '') ?>">
                    <input type="hidden" name="instructions" value="<?= htmlspecialchars($test['instructions'] ?? '') ?>">
                    <input type="hidden" name="type"         value="<?= htmlspecialchars($test['type'] ?? 'full_length') ?>">
                    <input type="hidden" name="section"      value="<?= htmlspecialchars($test['section'] ?? 'full') ?>">
                    <input type="hidden" name="total_time"   value="<?= (int)($test['total_time'] ?? 8040) ?>">

                    <div class="card reveal d3">
                        <div class="card-head">
                            <div class="card-head-ico" style="background:var(--ac3);color:var(--ac)">
                                <svg viewBox="0 0 24 24"><path d="M22 2L11 13M22 2L15 22 11 13 2 9l20-7z"/></svg>
                            </div>
                            <div>
                                <div class="card-title">Publish Settings</div>
                                <div class="card-sub">Control student access to this test</div>
                            </div>
                        </div>
                        <div class="card-body">
                            <label class="pub-toggle" for="is_published">
                                <input type="checkbox" id="is_published" name="is_published" value="1"
                                       <?= $test['is_published'] ? 'checked' : '' ?>>
                                <div style="display:flex;align-items:center;gap:10px">
                                    <div class="pub-dot"></div>
                                    <div>
                                        <div class="pub-text">Published</div>
                                        <div class="pub-sub">Students can see and take this test</div>
                                    </div>
                                </div>
                                <div class="pub-switch"></div>
                            </label>
                            <?php if ($totalQCount < $testTarget): ?>
                            <div style="padding:10px 12px;background:var(--warn2);border:1px solid rgba(245,158,11,.2);border-radius:8px;font-size:.6875rem;color:var(--warn);display:flex;gap:8px">
                                <svg style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0;margin-top:1px" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                <span>Only <?= $totalQCount ?>/<?= $testTarget ?> questions assigned. Consider completing sections before publishing.</span>
                            </div>
                            <?php endif ?>
                            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
                                <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/></svg>
                                Save Settings
                            </button>
                        </div>
                    </div>

                </form>

                <!-- Metadata -->
                <div class="card reveal d4" style="background:var(--sf)">
                    <div class="card-head"><div class="card-title">Metadata</div></div>
                    <div class="card-body" style="padding:14px 16px;gap:8px">
                        <?php foreach ([
                            ['ID',      '#' . $id,                                  'var(--fm)'],
                            ['Created', date('M j, Y', strtotime($test['created_at'])), null],
                            ['Updated', date('M j, Y g:ia', strtotime($test['updated_at'] ?? $test['created_at'])), null],
                            ['Type',    ucfirst(str_replace('_',' ',$test['type']??'full_length')), null],
                            ['Duration', fmtSecsEdit((int)($test['total_time']??0)), 'var(--fm)'],
                        ] as [$k,$v,$fam]): ?>
                        <div style="display:flex;justify-content:space-between;font-size:.6875rem;padding:5px 0;border-bottom:1px solid var(--bd)">
                            <span style="color:var(--tx3)"><?= $k ?></span>
                            <span style="<?= $fam ? "font-family:{$fam};" : '' ?>color:var(--tx2)"><?= htmlspecialchars($v) ?></span>
                        </div>
                        <?php endforeach ?>
                    </div>
                </div>

            </div><!-- end tab-settings -->

        </div><!-- end editor-main -->

        <!-- ── RIGHT SIDEBAR ────────────────────────── -->
        <div class="editor-side">

            <!-- Quick save -->
            <div class="card reveal d2">
                <div class="card-head">
                    <div class="card-title">Quick Actions</div>
                </div>
                <div class="card-body">
                    <!-- Publish toggle form (standalone) -->
                    <form method="POST" id="quickPubForm">
                        <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="_action"     value="update">
                        <input type="hidden" name="title"       value="<?= htmlspecialchars($test['title']) ?>">
                        <input type="hidden" name="description" value="<?= htmlspecialchars($test['description'] ?? '') ?>">
                        <input type="hidden" name="instructions" value="<?= htmlspecialchars($test['instructions'] ?? '') ?>">
                        <input type="hidden" name="type"        value="<?= htmlspecialchars($test['type'] ?? '') ?>">
                        <input type="hidden" name="section"     value="<?= htmlspecialchars($test['section'] ?? '') ?>">
                        <input type="hidden" name="total_time"  value="<?= (int)($test['total_time'] ?? 8040) ?>">
                        <label class="pub-toggle" for="quickPub">
                            <input type="checkbox" id="quickPub" name="is_published" value="1"
                                   <?= $test['is_published'] ? 'checked' : '' ?>
                                   onchange="this.form.submit()">
                            <div style="display:flex;align-items:center;gap:8px">
                                <div class="pub-dot"></div>
                                <div>
                                    <div class="pub-text" id="quickPubText"><?= $test['is_published'] ? 'Published' : 'Draft' ?></div>
                                    <div class="pub-sub"><?= $test['is_published'] ? 'Click to unpublish' : 'Click to publish' ?></div>
                                </div>
                            </div>
                            <div class="pub-switch"></div>
                        </label>
                    </form>

                    <div style="border-top:1px solid var(--bd);margin:4px 0"></div>

                    <!-- Progress to completion -->
                    <div>
                        <div style="display:flex;justify-content:space-between;font-size:.6875rem;color:var(--tx3);margin-bottom:6px">
                            <span>Questions filled</span>
                            <span style="font-family:var(--fm);color:<?= $totalQCount >= $testTarget ? 'var(--ac)' : 'var(--warn)' ?>"><?= $totalQCount ?>/<?= $testTarget ?></span>
                        </div>
                        <div style="height:5px;background:var(--sf2);border-radius:3px;overflow:hidden">
                            <div style="height:100%;width:<?= min(100, round($totalQCount/$testTarget*100)) ?>%;background:<?= $totalQCount >= $testTarget ? 'var(--ac)' : 'var(--warn)' ?>;border-radius:3px;transition:width .3s"></div>
                        </div>
                        <?php foreach ($sections as $sec):
                            $secId = (int)$sec['id'];
                            $sc    = count($sectionQuestions[$secId] ?? []);
                            $exp   = $sec['subject'] === 'math' ? 22 : 27;
                        ?>
                        <div style="display:flex;justify-content:space-between;font-size:.5625rem;color:var(--tx3);margin-top:6px">
                            <span><?= htmlspecialchars(mb_substr($sec['title'],0,28)) ?></span>
                            <span style="font-family:var(--fm);color:<?= $sc >= $exp ? 'var(--ac)' : 'var(--tx3)' ?>"><?= $sc ?>/<?= $exp ?></span>
                        </div>
                        <?php endforeach ?>
                    </div>

                    <a href="/admin/practice-tests/create.php" class="btn btn-ghost btn-sm" style="width:100%;justify-content:center;margin-top:4px">
                        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Duplicate Test
                    </a>
                </div>
            </div>

            <!-- Danger zone -->
            <div class="danger-zone reveal d3">
                <div class="danger-title">Danger Zone</div>
                <div class="danger-body">
                    Permanently delete this test, all <?= count($sections) ?> section<?= count($sections)!==1?'s':'' ?>,
                    and all <?= (int)$stats['total'] ?> student attempt<?= $stats['total']!=1?'s':'' ?>.
                    This cannot be undone.
                </div>
                <button type="button" class="btn btn-sm"
                        style="background:var(--err2);color:var(--err);border:1px solid rgba(239,68,68,.2);font-weight:700"
                        onclick="document.getElementById('deleteModal').classList.add('open')">
                    Delete Test
                </button>
            </div>

        </div><!-- end editor-side -->

    </div><!-- end editor-layout -->

</main>

<!-- ── DELETE TEST MODAL ───────────────────────── -->
<div class="modal-backdrop" id="deleteModal">
    <div class="modal">
        <div class="modal-ico"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M9 6V4h6v2"/></svg></div>
        <div class="modal-title">Delete Practice Test?</div>
        <div class="modal-body">
            You are about to permanently delete <strong>"<?= htmlspecialchars($test['title']) ?>"</strong>,
            all <?= count($sections) ?> section<?= count($sections)!==1?'s':'' ?>,
            and <?= (int)$stats['total'] ?> student attempt<?= $stats['total']!=1?'s':'' ?>.
            This action cannot be undone.
        </div>
        <div class="modal-actions">
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('deleteModal').classList.remove('open')">Cancel</button>
            <form method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="_action"    value="delete">
                <button type="submit" class="btn btn-danger">Delete Forever</button>
            </form>
        </div>
    </div>
</div>

<!-- ── DELETE SECTION MODAL ───────────────────── -->
<div class="modal-backdrop" id="delSecModal">
    <div class="modal">
        <div class="modal-ico"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M9 6V4h6v2"/></svg></div>
        <div class="modal-title">Remove Section?</div>
        <div class="modal-body" id="delSecBody">Remove this section and all its question assignments?</div>
        <div class="modal-actions">
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('delSecModal').classList.remove('open')">Cancel</button>
            <form method="POST" id="delSecForm" style="display:inline">
                <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="_action"     value="delete_section">
                <input type="hidden" name="section_id"  id="delSecId">
                <button type="submit" class="btn btn-danger">Remove</button>
            </form>
        </div>
    </div>
</div>

<script>
/* ── Sidebar ──────────────────────────────────── */
function openSidebar(){
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('sbOverlay').classList.add('show');
    document.body.style.overflow='hidden';
}
function closeSidebar(){
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sbOverlay').classList.remove('show');
    document.body.style.overflow='';
}
document.getElementById('sbOverlay').addEventListener('click', closeSidebar);
window.addEventListener('resize', function(){ if(window.innerWidth>768) closeSidebar(); });

/* ── Char counter ─────────────────────────────── */
function charCount(el, max, id){
    var n = el.value.length;
    var c = document.getElementById(id);
    if (!c) return;
    c.textContent = n + ' / ' + max;
    c.className = 'char-count' + (n > max*.9 ? ' warn' : '') + (n >= max ? ' over' : '');
}

/* ── Total time: convert minutes → seconds on submit ─── */
document.getElementById('detailsForm').addEventListener('submit', function(){
    var ti = document.getElementById('total_time');
    var mins = parseInt(ti.value) || 134;
    ti.value = mins * 60;
});

/* ── Tabs ─────────────────────────────────────── */
function switchTab(name, btn){
    document.querySelectorAll('.tab-panel').forEach(function(p){ p.classList.remove('active'); });
    document.querySelectorAll('.tab-btn').forEach(function(b){ b.classList.remove('active'); });
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
    // Update URL without reload
    var url = new URL(window.location);
    url.searchParams.set('tab', name);
    url.searchParams.delete('open_section');
    history.replaceState(null, '', url.toString());
}

/* ── Section accordion ────────────────────────── */
function toggleSection(secId){
    var el = document.getElementById('section-' + secId);
    if (!el) return;
    el.classList.toggle('open');
    if (el.classList.contains('open')){
        var url = new URL(window.location);
        url.searchParams.set('open_section', secId);
        history.replaceState(null, '', url.toString());
    }
}

/* ── Question filter (per section) ───────────── */
function filterQs(secId, query){
    var lq = query.toLowerCase();
    document.querySelectorAll('#qlist-' + secId + ' .q-row').forEach(function(row){
        var match = !lq || (row.dataset.stem||'').includes(lq);
        row.style.display = match ? '' : 'none';
    });
}
function filterQsDiff(secId, diff){
    document.querySelectorAll('#qlist-' + secId + ' .q-row').forEach(function(row){
        var match = !diff || row.dataset.diff === diff;
        row.style.display = match ? '' : 'none';
    });
}
function filterQsSubj(secId, subj){
    document.querySelectorAll('#qlist-' + secId + ' .q-row').forEach(function(row){
        var match = !subj || row.dataset.subj === subj;
        row.style.display = match ? '' : 'none';
    });
}

/* ── Select/clear all questions in section ──── */
function selectAllQsInSection(secId){
    document.querySelectorAll('#qlist-' + secId + ' input[type=checkbox]').forEach(function(cb){
        if (cb.closest('.q-row').style.display !== 'none') cb.checked = true;
    });
    updateSecCount(secId);
}
function clearAllQsInSection(secId){
    document.querySelectorAll('#qlist-' + secId + ' input[type=checkbox]').forEach(function(cb){ cb.checked = false; });
    updateSecCount(secId);
}

/* ── Update section question count badge ──────── */
function updateSecCount(secId){
    var n = document.querySelectorAll('#qlist-' + secId + ' input:checked').length;
    var el = document.getElementById('secQCount-' + secId);
    if (el) el.textContent = n + ' selected';
    // Update selected classes
    document.querySelectorAll('#qlist-' + secId + ' .q-row').forEach(function(row){
        row.classList.toggle('selected', row.querySelector('input').checked);
    });
}

/* ── Delete section modal ─────────────────────── */
function confirmDeleteSection(secId, title){
    document.getElementById('delSecBody').textContent = 'Remove "' + title + '" and all its question assignments? Cannot be undone.';
    document.getElementById('delSecId').value = secId;
    document.getElementById('delSecModal').classList.add('open');
}

/* ── Modal close on backdrop click / ESC ──────── */
document.querySelectorAll('.modal-backdrop').forEach(function(bd){
    bd.addEventListener('click', function(e){
        if (e.target === bd) bd.classList.remove('open');
    });
});
document.addEventListener('keydown', function(e){
    if (e.key === 'Escape'){
        document.querySelectorAll('.modal-backdrop.open').forEach(function(m){ m.classList.remove('open'); });
    }
});

/* ── Form validation (details tab) ──────────── */
document.getElementById('detailsForm').addEventListener('submit', function(e){
    var t = document.getElementById('title');
    if (t && !t.value.trim()){
        e.preventDefault();
        t.classList.add('err');
        t.focus();
    }
});

/* ── Open correct tab & section from URL on load ─ */
(function(){
    var tab = new URLSearchParams(window.location.search).get('tab');
    if (tab) {
        var btn = null;
        document.querySelectorAll('.tab-btn').forEach(function(b){
            if (b.getAttribute('onclick') && b.getAttribute('onclick').includes("'" + tab + "'")) btn = b;
        });
        if (btn) {
            document.querySelectorAll('.tab-panel').forEach(function(p){ p.classList.remove('active'); });
            document.querySelectorAll('.tab-btn').forEach(function(b){ b.classList.remove('active'); });
            var panel = document.getElementById('tab-' + tab);
            if (panel){ panel.classList.add('active'); if(btn) btn.classList.add('active'); }
        }
    }

    // Init all section q counts
    document.querySelectorAll('[id^="qlist-"]').forEach(function(qlist){
        var secId = qlist.id.replace('qlist-', '');
        updateSecCount(parseInt(secId));
    });
})();
</script>
</body>
</html>