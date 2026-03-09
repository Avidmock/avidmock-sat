<?php
/**
 * /admin/practice-tests/index.php
 * Practice Test management — list, filter, bulk actions, quick stats.
 */
require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

$admin = currentAdmin();
$db    = Database::connect();

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrfToken = $_SESSION['csrf_token'];

/* ── Flash ────────────────────────────────────────────────────────────── */
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

/* ── Bulk delete ──────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = 'error:Invalid security token.';
    } else {
        $ids = array_filter(array_map('intval', $_POST['ids'] ?? []));
        if (!empty($ids)) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            try {
                $db->prepare("DELETE FROM practice_tests WHERE id IN ({$ph})")->execute($ids);
                $_SESSION['flash'] = 'ok:Deleted ' . count($ids) . ' practice test' . (count($ids) !== 1 ? 's' : '') . '.';
            } catch (Throwable $e) {
                $_SESSION['flash'] = 'error:Delete failed: ' . $e->getMessage();
            }
        }
    }
    header('Location: /admin/practice-tests/'); exit;
}

/* ── Toggle publish ───────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_publish'])) {
    if (hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        $tid = (int)($_POST['test_id'] ?? 0);
        $pub = (int)($_POST['publish'] ?? 0);
        try {
            $db->prepare("UPDATE practice_tests SET is_published = ?, updated_at = NOW() WHERE id = ?")
               ->execute([$pub, $tid]);
            $_SESSION['flash'] = 'ok:Test ' . ($pub ? 'published' : 'unpublished') . ' successfully.';
        } catch (Throwable $e) {
            $_SESSION['flash'] = 'error:Update failed: ' . $e->getMessage();
        }
    }
    header('Location: /admin/practice-tests/'); exit;
}

/* ── Filters & pagination ─────────────────────────────────────────────── */
$search  = trim($_GET['q']      ?? '');
$fType   = $_GET['type']        ?? '';
$fSec    = $_GET['section']     ?? '';
$fStatus = $_GET['status']      ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

/* ── Build query ──────────────────────────────────────────────────────── */
$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $like = '%' . str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $search) . '%';
    $where[]  = '(t.title LIKE :search OR t.description LIKE :search2)';
    $params[':search']  = $like;
    $params[':search2'] = $like;
}
if ($fType !== '') {
    $where[]         = 't.type = :type';
    $params[':type'] = $fType;
}
if ($fSec !== '') {
    $where[]            = 't.section = :section';
    $params[':section'] = $fSec;
}
if ($fStatus === 'published') {
    $where[] = 't.is_published = 1';
} elseif ($fStatus === 'draft') {
    $where[] = 't.is_published = 0';
}

$whereSQL = implode(' AND ', $where);

try {
    $countStmt = $db->prepare("SELECT COUNT(*) FROM practice_tests t WHERE {$whereSQL}");
    $countStmt->execute($params);
    $totalRows = (int)$countStmt->fetchColumn();

    $dataStmt = $db->prepare(
        "SELECT t.*,
                (SELECT COUNT(*) FROM practice_test_sections WHERE test_id = t.id) AS section_count,
                (SELECT COUNT(*) FROM practice_test_section_questions sq
                 JOIN practice_test_sections s ON s.id = sq.section_id
                 WHERE s.test_id = t.id) AS question_count,
                (SELECT COUNT(*) FROM practice_test_attempts WHERE test_id = t.id AND status = 'submitted') AS attempt_count,
                (SELECT ROUND(AVG(total_score)) FROM practice_test_attempts WHERE test_id = t.id AND status = 'submitted') AS avg_score
         FROM practice_tests t
         WHERE {$whereSQL}
         ORDER BY t.created_at DESC
         LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $k => $v) $dataStmt->bindValue($k, $v);
    $dataStmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $dataStmt->execute();
    $tests = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

    $statsRow = $db->query(
        "SELECT
            COUNT(*) AS total,
            SUM(is_published = 1) AS published,
            SUM(is_published = 0) AS draft,
            (SELECT COUNT(*) FROM practice_test_attempts WHERE status = 'submitted') AS total_attempts,
            (SELECT ROUND(AVG(total_score)) FROM practice_test_attempts WHERE status = 'submitted') AS global_avg
         FROM practice_tests"
    )->fetch(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    $tests     = [];
    $totalRows = 0;
    $statsRow  = ['total'=>0,'published'=>0,'draft'=>0,'total_attempts'=>0,'global_avg'=>null];
    $flash = 'error:Database error: ' . $e->getMessage();
}

$totalPages = $totalRows > 0 ? (int)ceil($totalRows / $perPage) : 1;
$qBase = http_build_query(array_filter(['q'=>$search,'type'=>$fType,'section'=>$fSec,'status'=>$fStatus]));

/* ── Helpers ────────────────────────────────────────────────────────── */
function fmtSecs(int $s): string {
    if ($s <= 0) return '—';
    $h = floor($s / 3600); $m = floor(($s % 3600) / 60);
    return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
}
function typeChip(string $t): string {
    $map = [
        'full_length' => ['Full Length', '#1fe290', 'rgba(31,226,144,.1)'],
        'mini'        => ['Mini',        '#f59e0b', 'rgba(245,158,11,.1)'],
        'topic'       => ['Topic Drill', '#8b5cf6', 'rgba(139,92,246,.1)'],
        'timed'       => ['Timed',       '#ef4444', 'rgba(239,68,68,.1)'],
    ];
    [$lbl, $c, $bg] = $map[$t] ?? [ucfirst($t), '#9dbfba', 'rgba(255,255,255,.06)'];
    return "<span class=\"type-chip\" style=\"color:{$c};background:{$bg}\">{$lbl}</span>";
}
function secLabel(string $s): string {
    return ['full'=>'Full','math'=>'Math','reading_writing'=>'R&W'][$s] ?? ucfirst($s);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Practice Tests — Avidmock Admin</title>
<meta name="robots" content="noindex,nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,600;9..40,700;9..40,800&family=Fraunces:ital,opsz,wght@0,9..144,900;1,9..144,300&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{--ink:#0c1f1d;--ink2:#0e2522;--dk:#143230;--ac:#1fe290;--ac2:#13c474;--ac3:rgba(31,226,144,.08);--ac4:rgba(31,226,144,.15);--tx:#e8f3f1;--tx2:#9dbfba;--tx3:#5a8580;--bd:rgba(255,255,255,.07);--bd2:rgba(255,255,255,.13);--sf:rgba(255,255,255,.04);--sf2:rgba(255,255,255,.07);--warn:#f59e0b;--warn2:rgba(245,158,11,.12);--err:#ef4444;--err2:rgba(239,68,68,.12);--blue:#3b82f6;--purple:#8b5cf6;--ff:'DM Sans',sans-serif;--fh:'Fraunces',Georgia,serif;--fm:'DM Mono',monospace;--sb-w:240px;--top-h:60px}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:var(--ff);background:var(--ink);color:var(--tx);-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit}
button{font-family:var(--ff);cursor:pointer}
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
.topbar{position:fixed;top:0;left:var(--sb-w);right:0;height:var(--top-h);background:rgba(12,31,29,.92);backdrop-filter:blur(16px);border-bottom:1px solid var(--bd);display:flex;align-items:center;padding:0 28px;gap:10px;z-index:200}
.topbar-ham{display:none;width:34px;height:34px;border-radius:8px;border:1px solid var(--bd);background:var(--sf);align-items:center;justify-content:center;cursor:pointer;flex-direction:column;gap:4px;padding:9px}
.topbar-ham span{display:block;height:1.5px;background:var(--tx2);border-radius:1px;width:100%}
.topbar-title{font-family:var(--fh);font-size:1rem;font-weight:900;color:var(--tx);letter-spacing:-.025em}
.topbar-spacer{flex:1}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:9px;font-family:var(--ff);font-size:.8125rem;font-weight:700;border:1.5px solid transparent;cursor:pointer;transition:all .18s;white-space:nowrap}
.btn svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
.btn-primary{background:var(--ac);color:var(--dk);border-color:var(--ac)}.btn-primary:hover{background:var(--ac2);transform:translateY(-1px);box-shadow:0 4px 14px rgba(31,226,144,.2)}
.btn-ghost{background:var(--sf);border-color:var(--bd);color:var(--tx2)}.btn-ghost:hover{background:var(--sf2);color:var(--tx);border-color:var(--bd2)}
.btn-sm{padding:5px 10px;font-size:.6875rem;border-radius:7px}
.main{margin-left:var(--sb-w);margin-top:var(--top-h);padding:28px}
.page-eyebrow{font-size:.5rem;font-weight:700;color:var(--tx3);text-transform:uppercase;letter-spacing:1.2px;display:flex;align-items:center;gap:6px;margin-bottom:6px}
.page-dot{width:4px;height:4px;border-radius:50%;background:var(--ac)}
.page-title{font-family:var(--fh);font-size:1.75rem;font-weight:900;color:var(--tx);letter-spacing:-.035em;margin-bottom:4px}
.page-sub{font-size:.875rem;color:var(--tx2);margin-bottom:24px}
/* stat grid */
.stat-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:24px}
.stat-card{background:var(--ink2);border:1px solid var(--bd);border-radius:14px;padding:18px;transition:all .2s}
.stat-card:hover{border-color:var(--bd2);transform:translateY(-2px)}
.stat-lbl{font-size:.5625rem;font-weight:700;color:var(--tx3);text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px}
.stat-val{font-family:var(--fh);font-size:2rem;font-weight:900;line-height:1;letter-spacing:-.04em;color:var(--tx);margin-bottom:3px}
.stat-sub{font-size:.625rem;color:var(--tx3)}
/* toolbar */
.toolbar{display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap}
.search-wrap{position:relative;flex:1;min-width:200px;max-width:360px}
.search-wrap svg{position:absolute;left:10px;top:50%;transform:translateY(-50%);width:14px;height:14px;stroke:var(--tx3);fill:none;stroke-width:2;pointer-events:none}
.search-input{width:100%;background:var(--sf);border:1px solid var(--bd);border-radius:9px;color:var(--tx);font-family:var(--ff);font-size:.8125rem;padding:8px 12px 8px 34px;outline:none;transition:border-color .16s}
.search-input:focus{border-color:var(--ac)}.search-input::placeholder{color:var(--tx3)}
.filter-sel{background:var(--sf);border:1px solid var(--bd);border-radius:8px;color:var(--tx2);font-family:var(--ff);font-size:.75rem;padding:7px 10px;outline:none;cursor:pointer;transition:all .16s}
.filter-sel:focus{border-color:var(--ac);color:var(--tx)}.filter-sel option{background:var(--ink2)}
/* table */
.table-wrap{background:var(--ink2);border:1px solid var(--bd);border-radius:16px;overflow:hidden;margin-bottom:20px}
.table-topbar{padding:11px 18px;border-bottom:1px solid var(--bd);display:flex;align-items:center;gap:10px;min-height:44px}
.table-count{font-size:.6875rem;color:var(--tx3);margin-left:auto}
.bulk-bar{display:none;align-items:center;gap:8px}
.bulk-bar.show{display:flex}
.data-table{width:100%;border-collapse:collapse}
.data-table th{padding:9px 14px;font-size:.5rem;font-weight:700;color:var(--tx3);text-transform:uppercase;letter-spacing:.8px;text-align:left;border-bottom:1px solid var(--bd);background:rgba(255,255,255,.015);white-space:nowrap}
.data-table td{padding:12px 14px;font-size:.8125rem;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:middle;color:var(--tx2)}
.data-table tbody tr:last-child td{border-bottom:none}
.data-table tbody tr:hover td{background:rgba(255,255,255,.02)}
.data-table tbody tr.sel td{background:var(--ac3)}
.cb{width:14px;height:14px;accent-color:var(--ac);cursor:pointer}
/* test cell */
.test-name{font-weight:700;color:var(--tx);font-size:.875rem;margin-bottom:2px}
.test-meta{font-size:.5625rem;color:var(--tx3);display:flex;align-items:center;gap:5px;margin-top:3px}
.test-meta svg{width:10px;height:10px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;opacity:.6}
/* chips */
.type-chip{display:inline-flex;align-items:center;padding:2px 8px;border-radius:5px;font-size:.5rem;font-weight:800;text-transform:uppercase;letter-spacing:.4px}
/* q progress */
.q-prog{display:flex;align-items:center;gap:6px}
.q-bar{flex:1;height:4px;background:var(--sf2);border-radius:2px;overflow:hidden;min-width:48px}
.q-fill{height:100%;border-radius:2px}
.q-num{font-family:var(--fm);font-size:.5625rem;color:var(--tx3);white-space:nowrap}
/* publish toggle */
.pub-btn{display:flex;align-items:center;gap:5px;background:none;border:none;padding:4px 8px;border-radius:6px;cursor:pointer;transition:background .15s}
.pub-btn:hover{background:var(--sf2)}
.pub-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}
.pub-lbl{font-size:.6875rem;font-weight:700}
/* score */
.score-n{font-family:var(--fm);font-size:.75rem;font-weight:700}
.sc-hi{color:var(--ac)}.sc-md{color:var(--warn)}.sc-lo{color:var(--err)}
/* actions */
.act-row{display:flex;gap:4px}
/* pagination */
.pagination{display:flex;align-items:center;gap:5px;justify-content:center;flex-wrap:wrap;margin-top:4px}
.pg-btn{padding:6px 12px;background:var(--sf);border:1px solid var(--bd);border-radius:7px;color:var(--tx2);font-size:.75rem;font-weight:600;transition:all .16s}
.pg-btn:hover{background:var(--sf2);color:var(--tx);border-color:var(--bd2)}
.pg-btn.active{background:var(--ac);color:var(--dk);border-color:var(--ac)}
.pg-btn.disabled{opacity:.3;pointer-events:none}
/* flash */
.flash{padding:11px 18px;border-radius:10px;font-size:.8125rem;font-weight:600;margin-bottom:18px;display:flex;align-items:center;gap:8px}
.flash-ok{background:var(--ac3);border:1px solid var(--ac4);color:var(--ac)}
.flash-error{background:var(--err2);border:1px solid rgba(239,68,68,.25);color:var(--err)}
.flash svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0}
/* empty */
.empty-state{text-align:center;padding:64px 20px}
.empty-ico{font-size:2.5rem;margin-bottom:12px}
.empty-title{font-size:.9375rem;font-weight:700;color:var(--tx);margin-bottom:6px}
.empty-sub{font-size:.8125rem;color:var(--tx3);margin-bottom:20px}
/* modals */
.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);z-index:500;display:none;align-items:center;justify-content:center;padding:20px}
.modal-backdrop.open{display:flex}
.modal{background:var(--ink2);border:1px solid var(--bd2);border-radius:18px;padding:28px;width:100%;max-width:420px;box-shadow:0 32px 80px rgba(0,0,0,.6)}
.modal-ico{width:44px;height:44px;border-radius:12px;background:var(--err2);display:flex;align-items:center;justify-content:center;margin-bottom:14px}
.modal-ico svg{width:20px;height:20px;stroke:var(--err);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.modal-title{font-family:var(--fh);font-size:1.125rem;font-weight:900;color:var(--tx);margin-bottom:8px}
.modal-body{font-size:.8125rem;color:var(--tx2);margin-bottom:22px;line-height:1.6}
.modal-actions{display:flex;gap:8px;justify-content:flex-end}
.btn-danger{background:var(--err);color:#fff;border:none;border-radius:9px;font-family:var(--ff);font-size:.8125rem;font-weight:700;padding:9px 20px;cursor:pointer;transition:all .18s}
.btn-danger:hover{background:#dc2626;transform:translateY(-1px)}
/* reveal */
.reveal{opacity:0;transform:translateY(12px);animation:rev .4s cubic-bezier(.16,1,.3,1) forwards}
@keyframes rev{to{opacity:1;transform:none}}
.d1{animation-delay:.04s}.d2{animation-delay:.08s}.d3{animation-delay:.12s}.d4{animation-delay:.16s}
/* responsive */
@media(max-width:1200px){.stat-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:900px){.stat-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:768px){
    :root{--sb-w:0px}.sb{transform:translateX(-240px);--sb-w:240px}.sb.open{transform:translateX(0)}
    .sb-overlay{display:block}.topbar{left:0;padding:0 16px}.topbar-ham{display:flex}
    .main{margin-left:0;padding:16px}.stat-grid{grid-template-columns:1fr 1fr}
    .data-table th:nth-child(5),.data-table td:nth-child(5),
    .data-table th:nth-child(6),.data-table td:nth-child(6){display:none}
}
@media(max-width:500px){.stat-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="sb-overlay" id="sbOverlay"></div>

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
        <div class="sb-group">Platform</div>
        <a href="/admin/settings/" class="sb-link"><svg class="sb-ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>Settings</a>
    </nav>
    <div class="sb-foot">
        <div class="sb-ava"><?= strtoupper(substr($admin['name'] ?? 'A', 0, 1)) ?></div>
        <div><div class="sb-foot-name"><?= htmlspecialchars($admin['name'] ?? 'Admin') ?></div><div class="sb-foot-role"><?= htmlspecialchars($admin['role'] ?? 'admin') ?></div></div>
        <a href="/admin/auth/logout.php" class="sb-out"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></a>
    </div>
</aside>

<header class="topbar">
    <button class="topbar-ham" onclick="openSidebar()"><span></span><span></span><span></span></button>
    <div class="topbar-title">Practice Tests</div>
    <div class="topbar-spacer"></div>
    <a href="/admin/practice-tests/create.php" class="btn btn-primary">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Practice Test
    </a>
</header>

<main class="main">

    <div class="page-eyebrow reveal"><span class="page-dot"></span>Content Management</div>
    <h1 class="page-title reveal d1">Practice Tests</h1>
    <p class="page-sub reveal d1">Create and manage full-length SAT practice tests for students.</p>

    <?php if ($flash): [$ft, $fm] = explode(':', $flash, 2); ?>
    <div class="flash flash-<?= $ft === 'ok' ? 'ok' : 'error' ?>">
        <?php if ($ft === 'ok'): ?><svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        <?php else: ?><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg><?php endif ?>
        <?= htmlspecialchars($fm) ?>
    </div>
    <?php endif ?>

    <!-- STATS -->
    <div class="stat-grid reveal d2">
        <div class="stat-card">
            <div class="stat-lbl">Total Tests</div>
            <div class="stat-val"><?= number_format((int)$statsRow['total']) ?></div>
            <div class="stat-sub">in library</div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Published</div>
            <div class="stat-val" style="color:var(--ac)"><?= number_format((int)$statsRow['published']) ?></div>
            <div class="stat-sub">live to students</div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Drafts</div>
            <div class="stat-val" style="color:var(--tx3)"><?= number_format((int)$statsRow['draft']) ?></div>
            <div class="stat-sub">not yet live</div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Total Attempts</div>
            <div class="stat-val"><?= number_format((int)$statsRow['total_attempts']) ?></div>
            <div class="stat-sub">submitted</div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Avg Score</div>
            <div class="stat-val <?= $statsRow['global_avg'] ? ($statsRow['global_avg'] >= 1200 ? 'sc-hi' : ($statsRow['global_avg'] >= 900 ? 'sc-md' : '')) : '' ?>">
                <?= $statsRow['global_avg'] ? number_format((float)$statsRow['global_avg']) : '—' ?>
            </div>
            <div class="stat-sub">out of 1600</div>
        </div>
    </div>

    <!-- TOOLBAR -->
    <form method="GET" id="filterForm">
    <div class="toolbar reveal d3">
        <div class="search-wrap">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" name="q" class="search-input" placeholder="Search practice tests…"
                   value="<?= htmlspecialchars($search) ?>" oninput="debounceSubmit()">
        </div>
        <select name="type" class="filter-sel" onchange="this.form.submit()">
            <option value="">All types</option>
            <option value="full_length" <?= $fType==='full_length'?'selected':'' ?>>Full Length</option>
            <option value="mini"        <?= $fType==='mini'?'selected':'' ?>>Mini Test</option>
            <option value="topic"       <?= $fType==='topic'?'selected':'' ?>>Topic Drill</option>
            <option value="timed"       <?= $fType==='timed'?'selected':'' ?>>Timed</option>
        </select>
        <select name="section" class="filter-sel" onchange="this.form.submit()">
            <option value="">All sections</option>
            <option value="full"            <?= $fSec==='full'?'selected':'' ?>>Full SAT</option>
            <option value="math"            <?= $fSec==='math'?'selected':'' ?>>Math</option>
            <option value="reading_writing" <?= $fSec==='reading_writing'?'selected':'' ?>>R&amp;W</option>
        </select>
        <select name="status" class="filter-sel" onchange="this.form.submit()">
            <option value="">All statuses</option>
            <option value="published" <?= $fStatus==='published'?'selected':'' ?>>Published</option>
            <option value="draft"     <?= $fStatus==='draft'?'selected':'' ?>>Draft</option>
        </select>
        <?php if ($search || $fType || $fSec || $fStatus): ?>
        <a href="/admin/practice-tests/" class="btn btn-ghost btn-sm">
            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>Clear
        </a>
        <?php endif ?>
        <span style="font-size:.6875rem;color:var(--tx3);margin-left:auto"><?= number_format($totalRows) ?> test<?= $totalRows !== 1 ? 's' : '' ?></span>
    </div>
    </form>

    <!-- TABLE -->
    <div class="table-wrap reveal d4">
        <div class="table-topbar">
            <input type="checkbox" class="cb" id="selectAll">
            <div class="bulk-bar" id="bulkBar">
                <span style="font-size:.75rem;color:var(--tx2);font-weight:600"><span id="bulkCount">0</span> selected</span>
                <button type="button" class="btn btn-sm" style="background:var(--err2);color:var(--err);border:1px solid rgba(239,68,68,.2)" onclick="confirmBulkDelete()">
                    <svg viewBox="0 0 24 24" style="width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M9 6V4h6v2"/></svg>
                    Delete selected
                </button>
            </div>
            <span class="table-count"><?= number_format($totalRows) ?> result<?= $totalRows !== 1 ? 's' : '' ?></span>
        </div>

        <?php if (empty($tests)): ?>
        <div class="empty-state">
            <div class="empty-ico">📋</div>
            <div class="empty-title"><?= ($search||$fType||$fSec||$fStatus) ? 'No tests match your filters' : 'No practice tests yet' ?></div>
            <div class="empty-sub"><?= ($search||$fType||$fSec||$fStatus) ? 'Try adjusting your search or filters.' : 'Create your first practice test to get started.' ?></div>
            <?php if (!$search && !$fType && !$fSec && !$fStatus): ?>
            <a href="/admin/practice-tests/create.php" class="btn btn-primary">
                <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Create First Test
            </a>
            <?php endif ?>
        </div>

        <?php else: ?>
        <div style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:36px"></th>
                    <th>Test</th>
                    <th>Type</th>
                    <th>Questions</th>
                    <th>Duration</th>
                    <th>Attempts</th>
                    <th>Avg Score</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th style="width:120px"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tests as $t):
                $qCount  = (int)($t['question_count'] ?? 0);
                $qTarget = match($t['type'] ?? '') { 'full_length' => 98, 'mini' => 30, default => 20 };
                $qPct    = $qTarget > 0 ? min(100, round($qCount / $qTarget * 100)) : 0;
                $atts    = (int)($t['attempt_count'] ?? 0);
                $avgSc   = $t['avg_score'] ? (int)$t['avg_score'] : null;
                $avgCls  = $avgSc ? ($avgSc >= 1200 ? 'sc-hi' : ($avgSc >= 900 ? 'sc-md' : 'sc-lo')) : '';
                $pub     = (int)($t['is_published'] ?? 0);
            ?>
            <tr data-id="<?= (int)$t['id'] ?>">
                <td><input type="checkbox" class="cb row-cb" value="<?= (int)$t['id'] ?>"></td>

                <td style="min-width:220px">
                    <div class="test-name"><?= htmlspecialchars($t['title']) ?></div>
                    <div class="test-meta">
                        <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/></svg>
                        <?= (int)($t['section_count'] ?? 0) ?> section<?= ($t['section_count'] ?? 0) != 1 ? 's' : '' ?>
                        <?php if ($t['section'] ?? ''): ?> · <?= secLabel($t['section']) ?><?php endif ?>
                    </div>
                </td>

                <td><?= typeChip($t['type'] ?? 'full_length') ?></td>

                <td style="min-width:120px">
                    <div class="q-prog">
                        <div class="q-bar">
                            <div class="q-fill" style="width:<?= $qPct ?>%;background:<?= $qPct >= 100 ? 'var(--ac)' : 'var(--warn)' ?>"></div>
                        </div>
                        <span class="q-num"><?= $qCount ?>/<?= $qTarget ?></span>
                    </div>
                </td>

                <td><span style="font-family:var(--fm);font-size:.75rem;color:var(--tx3)"><?= fmtSecs((int)($t['total_time'] ?? 0)) ?></span></td>

                <td><span style="font-family:var(--fm);font-size:.75rem;color:<?= $atts > 0 ? 'var(--tx2)' : 'var(--tx3)' ?>"><?= number_format($atts) ?></span></td>

                <td>
                    <?php if ($avgSc): ?><span class="score-n <?= $avgCls ?>"><?= $avgSc ?></span>
                    <?php else: ?><span style="color:var(--tx3);font-size:.75rem">—</span><?php endif ?>
                </td>

                <td>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="csrf_token"     value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="toggle_publish" value="1">
                        <input type="hidden" name="test_id"        value="<?= (int)$t['id'] ?>">
                        <input type="hidden" name="publish"        value="<?= $pub ? 0 : 1 ?>">
                        <button type="submit" class="pub-btn" title="<?= $pub ? 'Click to unpublish' : 'Click to publish' ?>">
                            <div class="pub-dot" style="background:<?= $pub ? 'var(--ac)' : 'var(--tx3)' ?>"></div>
                            <span class="pub-lbl" style="color:<?= $pub ? 'var(--ac)' : 'var(--tx3)' ?>"><?= $pub ? 'Live' : 'Draft' ?></span>
                        </button>
                    </form>
                </td>

                <td><span style="font-size:.6875rem;color:var(--tx3)"><?= date('M j, Y', strtotime($t['created_at'])) ?></span></td>

                <td>
                    <div class="act-row">
                        <?php if ($atts > 0): ?>
                        <a href="/admin/practice-tests/results.php?test_id=<?= (int)$t['id'] ?>" class="btn btn-ghost btn-sm" title="Results">
                            <svg viewBox="0 0 24 24"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>
                        </a>
                        <?php endif ?>
                        <a href="/admin/practice-tests/edit.php?id=<?= (int)$t['id'] ?>" class="btn btn-ghost btn-sm" title="Edit">
                            <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </a>
                        <button type="button" class="btn btn-ghost btn-sm" style="color:var(--err)" title="Delete"
                                onclick="confirmDelete(<?= (int)$t['id'] ?>, '<?= htmlspecialchars(addslashes($t['title']), ENT_QUOTES) ?>')">
                            <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M9 6V4h6v2"/></svg>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach ?>
            </tbody>
        </table>
        </div>
        <?php endif ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination reveal d4">
        <a href="?<?= $qBase ?>&page=<?= $page-1 ?>" class="pg-btn <?= $page<=1?'disabled':'' ?>">← Prev</a>
        <?php
        $start = max(1, $page-2); $end = min($totalPages, $page+2);
        if ($start > 1) echo '<span class="pg-btn disabled">…</span>';
        for ($i = $start; $i <= $end; $i++) echo "<a href=\"?{$qBase}&page={$i}\" class=\"pg-btn ".($i===$page?'active':'')."\">{$i}</a>";
        if ($end < $totalPages) echo '<span class="pg-btn disabled">…</span>';
        ?>
        <a href="?<?= $qBase ?>&page=<?= $page+1 ?>" class="pg-btn <?= $page>=$totalPages?'disabled':'' ?>">Next →</a>
    </div>
    <?php endif ?>

</main>

<!-- DELETE MODAL -->
<div class="modal-backdrop" id="deleteModal">
    <div class="modal">
        <div class="modal-ico"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M9 6V4h6v2"/></svg></div>
        <div class="modal-title">Delete Practice Test</div>
        <div class="modal-body" id="deleteModalBody">This will permanently delete the test, all its sections, and all student attempts. Cannot be undone.</div>
        <div class="modal-actions">
            <button type="button" class="btn btn-ghost" onclick="closeModal('deleteModal')">Cancel</button>
            <form method="POST" style="display:inline">
                <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="bulk_delete" value="1">
                <input type="hidden" name="ids[]"       id="deleteId">
                <button type="submit" class="btn-danger">Delete Test</button>
            </form>
        </div>
    </div>
</div>

<!-- BULK DELETE MODAL -->
<div class="modal-backdrop" id="bulkDeleteModal">
    <div class="modal">
        <div class="modal-ico"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M9 6V4h6v2"/></svg></div>
        <div class="modal-title">Delete Selected Tests</div>
        <div class="modal-body" id="bulkDeleteBody">This will permanently delete all selected tests, their sections, and all student attempts.</div>
        <div class="modal-actions">
            <button type="button" class="btn btn-ghost" onclick="closeModal('bulkDeleteModal')">Cancel</button>
            <form method="POST" style="display:inline">
                <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="bulk_delete" value="1">
                <div id="bulkDeleteIds"></div>
                <button type="submit" class="btn-danger">Delete All</button>
            </form>
        </div>
    </div>
</div>

<script>
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('sbOverlay').classList.add('show');document.body.style.overflow='hidden'}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sbOverlay').classList.remove('show');document.body.style.overflow=''}
document.getElementById('sbOverlay').addEventListener('click',closeSidebar);
window.addEventListener('resize',function(){if(window.innerWidth>768)closeSidebar()});

var _st;
function debounceSubmit(){clearTimeout(_st);_st=setTimeout(function(){document.getElementById('filterForm').submit()},420)}

var selectAll=document.getElementById('selectAll');
var bulkBar=document.getElementById('bulkBar');
var bulkCount=document.getElementById('bulkCount');
function updateBulk(){
    var cbs=document.querySelectorAll('.row-cb');
    var checked=document.querySelectorAll('.row-cb:checked');
    var n=checked.length;
    bulkCount.textContent=n;
    n>0?bulkBar.classList.add('show'):bulkBar.classList.remove('show');
    selectAll.indeterminate=n>0&&n<cbs.length;
    selectAll.checked=n>0&&n===cbs.length;
    cbs.forEach(function(cb){cb.closest('tr').classList.toggle('sel',cb.checked)});
}
document.querySelectorAll('.row-cb').forEach(function(cb){cb.addEventListener('change',updateBulk)});
selectAll.addEventListener('change',function(e){document.querySelectorAll('.row-cb').forEach(function(cb){cb.checked=e.target.checked});updateBulk()});

function closeModal(id){document.getElementById(id).classList.remove('open')}
function confirmDelete(id,title){
    document.getElementById('deleteModalBody').textContent='Delete "'+title+'"? All sections, questions and student attempts will be removed. Cannot be undone.';
    document.getElementById('deleteId').value=id;
    document.getElementById('deleteModal').classList.add('open');
}
function confirmBulkDelete(){
    var ids=[...document.querySelectorAll('.row-cb:checked')].map(function(cb){return cb.value});
    if(!ids.length)return;
    document.getElementById('bulkDeleteBody').textContent='Delete '+ids.length+' selected test(s)? All related data will be permanently removed.';
    document.getElementById('bulkDeleteIds').innerHTML=ids.map(function(id){return'<input type="hidden" name="ids[]" value="'+id+'">'}).join('');
    document.getElementById('bulkDeleteModal').classList.add('open');
}
document.querySelectorAll('.modal-backdrop').forEach(function(bd){bd.addEventListener('click',function(e){if(e.target===bd)bd.classList.remove('open')})});
document.addEventListener('keydown',function(e){if(e.key==='Escape')document.querySelectorAll('.modal-backdrop.open').forEach(function(m){m.classList.remove('open')})});
</script>
</body>
</html>