<?php
/**
 * quizzes/index.php
 * All quizzes — filterable, searchable, with bulk actions.
 * Uses shared includes/head.php, includes/sidebar.php, includes/admin.css
 */
require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

$admin = currentAdmin();
$db    = Database::connect();

// ── Detect name column ────────────────────────────────────────────────────
$userCols = $db->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
$nameExpr = in_array('first_name', $userCols)
    ? "CONCAT(u.first_name,' ',u.last_name)"
    : "u.name";

// ── Params ────────────────────────────────────────────────────────────────
$status  = $_GET['status'] ?? 'all';
$search  = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = [];
$params = [];
if ($status !== 'all') {
    $where[]           = 'q.status = :status';
    $params[':status'] = $status;
}
if ($search !== '') {
    $where[]       = '(q.title LIKE :s OR q.lesson_slug LIKE :s2)';
    $params[':s']  = "%{$search}%";
    $params[':s2'] = "%{$search}%";
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ── Count ─────────────────────────────────────────────────────────────────
$countStmt = $db->prepare("SELECT COUNT(*) FROM sat_quizzes q {$whereSQL}");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));

// ── Quizzes ───────────────────────────────────────────────────────────────
$stmt = $db->prepare(
    "SELECT q.id, q.title, q.lesson_slug, q.section, q.status,
            q.time_limit, q.passing_score, q.created_at, q.updated_at,
            (SELECT COUNT(*) FROM sat_quiz_questions WHERE quiz_id = q.id)                            AS q_count,
            (SELECT COUNT(*) FROM sat_quiz_attempts  WHERE quiz_id = q.id AND status = 'completed')   AS attempts,
            (SELECT ROUND(AVG(score),1) FROM sat_quiz_attempts WHERE quiz_id = q.id AND status = 'completed') AS avg_score,
            {$nameExpr} AS created_by_name
     FROM sat_quizzes q
     LEFT JOIN users u ON u.id = q.created_by
     {$whereSQL}
     ORDER BY q.updated_at DESC
     LIMIT :limit OFFSET :offset"
);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$stmt->execute();
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Tab counts ────────────────────────────────────────────────────────────
$rawCounts = $db->query(
    "SELECT status, COUNT(*) AS n FROM sat_quizzes GROUP BY status"
)->fetchAll(PDO::FETCH_ASSOC);
$counts = ['all' => 0];
foreach ($rawCounts as $row) {
    $counts[$row['status']] = (int) $row['n'];
    $counts['all']         += (int) $row['n'];
}

// ── For sidebar badge ─────────────────────────────────────────────────────
$draftCount = (int) ($counts['draft'] ?? 0);

// ── Head setup ────────────────────────────────────────────────────────────
$pageTitle  = 'Quizzes — Avidmock Admin';
$activePage = 'quizzes';
$extraHead  = <<<'CSS'
<style>
/* ── Page layout ───────────────────────────────────────── */
.main {
    margin-left: var(--sb-w);
    margin-top: var(--top-h);
    padding: 32px 28px;
    min-height: calc(100vh - var(--top-h));
}

/* ── Page header ─────────────────────────────────────── */
.ph { margin-bottom: 24px; }
.ph-eyebrow { display: flex; align-items: center; gap: 6px; font-size: .5rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 5px; }
.ph-dot { width: 4px; height: 4px; border-radius: 50%; background: var(--ac); }
.ph-title { font-family: var(--fh); font-size: 1.875rem; font-weight: 900; color: var(--tx); letter-spacing: -.035em; line-height: 1.1; }
.ph-sub { font-size: .875rem; color: var(--tx2); margin-top: 4px; }

/* ── Filter bar ──────────────────────────────────────── */
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

/* ── Table ───────────────────────────────────────────── */
.table-card { background: var(--sf); border: 1px solid var(--bd); border-radius: 14px; overflow: hidden; margin-top: 20px; }
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
thead th { padding: 10px 16px; font-size: .5625rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: .8px; text-align: left; border-bottom: 1px solid var(--bd); background: rgba(255,255,255,.015); white-space: nowrap; }
tbody td { padding: 13px 16px; font-size: .8125rem; border-bottom: 1px solid var(--bd); vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }
tbody tr { transition: background .14s; }
tbody tr:hover { background: rgba(255,255,255,.025); }
.cb-col { width: 40px; }
.cb { width: 14px; height: 14px; accent-color: var(--ac); cursor: pointer; }

/* ── Cell types ──────────────────────────────────────── */
.quiz-title { font-weight: 700; color: var(--tx); letter-spacing: -.01em; }
.quiz-slug  { display: block; font-size: .625rem; color: var(--tx3); font-family: var(--fm); margin-top: 2px; }
.num   { font-family: var(--fm); font-size: .8125rem; font-weight: 500; color: var(--tx2); }
.score { font-weight: 700; font-family: var(--fm); }
.score.hi { color: var(--ac); }   .score.md { color: var(--warn); }
.score.lo { color: var(--err); }  .score.na { color: var(--tx3); }
.section-tag { display: inline-block; padding: 2px 8px; border-radius: 5px; font-size: .5625rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; }
.section-tag.math { background: var(--blue2);   color: var(--blue); }
.section-tag.rw   { background: var(--purple2); color: var(--purple); }
.section-tag.none { background: var(--sf3);     color: var(--tx3); }
.row-actions { display: flex; align-items: center; gap: 4px; opacity: 0; transition: opacity .14s; }
tbody tr:hover .row-actions { opacity: 1; }

/* ── Bulk bar ────────────────────────────────────────── */
.bulk-bar { display: none; align-items: center; gap: 10px; padding: 10px 16px; background: var(--ac3); border-bottom: 1px solid rgba(31,226,144,.15); }
.bulk-bar.show { display: flex; }
.bulk-count { font-size: .8125rem; font-weight: 700; color: var(--ac); }

/* ── Pagination ──────────────────────────────────────── */
.pagination { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-top: 1px solid var(--bd); flex-wrap: wrap; gap: 10px; }
.pag-info { font-size: .75rem; color: var(--tx3); }
.pag-btns { display: flex; gap: 4px; }
.pag-btn { width: 30px; height: 30px; border-radius: 7px; display: flex; align-items: center; justify-content: center; font-size: .75rem; font-weight: 700; text-decoration: none; transition: all .16s; color: var(--tx3); background: var(--sf); border: 1px solid var(--bd); }
.pag-btn:hover { background: var(--sf2); color: var(--tx); }
.pag-btn.active { background: var(--ac3); color: var(--ac); border-color: rgba(31,226,144,.2); }
.pag-btn.disabled { opacity: .35; pointer-events: none; }

/* ── Empty state ─────────────────────────────────────── */
.empty { text-align: center; padding: 80px 20px; }
.empty-ico { font-size: 2.5rem; margin-bottom: 16px; opacity: .2; }
.empty-title { font-family: var(--fh); font-size: 1.25rem; font-weight: 900; color: var(--tx); letter-spacing: -.025em; margin-bottom: 8px; }
.empty-sub { font-size: .875rem; color: var(--tx3); margin-bottom: 24px; }

/* ── Delete modal ────────────────────────────────────── */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.65); backdrop-filter: blur(4px); z-index: 500; display: flex; align-items: center; justify-content: center; opacity: 0; pointer-events: none; transition: opacity .22s; }
.modal-overlay.show { opacity: 1; pointer-events: all; }
.modal { background: var(--ink2); border: 1px solid var(--bd2); border-radius: 16px; padding: 28px; width: 100%; max-width: 420px; transform: translateY(16px) scale(.97); transition: transform .22s cubic-bezier(.16,1,.3,1); }
.modal-overlay.show .modal { transform: none; }
.modal-title { font-family: var(--fh); font-size: 1.125rem; font-weight: 900; color: var(--tx); margin-bottom: 8px; letter-spacing: -.025em; }
.modal-body  { font-size: .875rem; color: var(--tx2); line-height: 1.65; margin-bottom: 20px; }
.modal-body strong { color: var(--err); }
.modal-foot  { display: flex; gap: 8px; justify-content: flex-end; }

/* ── Responsive ──────────────────────────────────────── */
@media (max-width: 768px) {
    .main { margin-left: 0; padding: 16px; }
}
</style>
CSS;

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';

// ── Helper ────────────────────────────────────────────────────────────────
function scoreClass(float $s): string {
    if ($s >= 80) return 'hi';
    if ($s >= 60) return 'md';
    return 'lo';
}
?>

<!-- Topbar -->
<header class="topbar">
    <button class="topbar-ham" onclick="openSidebar()" aria-label="Menu">
        <span></span><span></span><span></span>
    </button>
    <div class="topbar-title">Quizzes <span>/ All</span></div>
    <div class="topbar-spacer"></div>
    <a href="/quizzes/create.php" class="btn btn-primary">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Quiz
    </a>
</header>

<!-- Main -->
<main class="main">

    <!-- Page header -->
    <div class="ph reveal d1">
        <div class="ph-eyebrow"><span class="ph-dot"></span>Content</div>
        <h1 class="ph-title">Quizzes</h1>
        <p class="ph-sub">
            <?= number_format($total) ?> quiz<?= $total !== 1 ? 'zes' : '' ?> found<?= $search ? ' for "<em>' . htmlspecialchars($search) . '</em>"' : '' ?>
        </p>
        <!-- Filter bar -->
        <div class="filter-bar">
            <div class="tabs">
                <?php foreach (['all' => 'All', 'published' => 'Published', 'draft' => 'Draft', 'archived' => 'Archived'] as $k => $label): ?>
                <a href="?status=<?= $k ?><?= $search ? '&q=' . urlencode($search) : '' ?>"
                   class="tab <?= $status === $k ? 'active' : '' ?>">
                    <?= $label ?>
                    <span class="tab-count"><?= number_format($counts[$k] ?? 0) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <form method="GET" style="display:contents">
                <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                <div class="search-wrap">
                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input class="search-input" type="search" name="q"
                           value="<?= htmlspecialchars($search) ?>"
                           placeholder="Search quizzes…"
                           autocomplete="off"
                           onkeydown="if(event.key==='Enter')this.closest('form').submit()">
                </div>
            </form>
        </div>
    </div>

    <!-- Table card -->
    <div class="table-card reveal d2">

        <!-- Bulk action bar (shown when rows selected) -->
        <div class="bulk-bar" id="bulkBar">
            <span class="bulk-count" id="bulkCount">0 selected</span>
            <button class="btn btn-sm btn-primary" onclick="bulkPublish()">
                <svg viewBox="0 0 24 24"><path d="M12 2l3 7h7l-5.5 4.5 2 7L12 17l-6.5 3.5 2-7L2 9h7z" fill="currentColor" stroke="none"/></svg>
                Publish selected
            </button>
            <button class="btn btn-sm btn-danger" onclick="bulkDelete()">
                <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
                Delete selected
            </button>
            <button class="btn btn-sm btn-ghost" onclick="clearSelection()">Clear</button>
        </div>

        <?php if (empty($quizzes)): ?>
        <!-- Empty state -->
        <div class="empty">
            <div class="empty-ico">📋</div>
            <div class="empty-title">
                <?= $status !== 'all' ? "No {$status} quizzes" : 'No quizzes yet' ?>
            </div>
            <div class="empty-sub">
                <?= $search ? 'Try a different search term.' : 'Create your first quiz to get started.' ?>
            </div>
            <a href="/quizzes/create.php" class="btn btn-primary">
                <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Create Quiz
            </a>
        </div>

        <?php else: ?>
        <!-- Table -->
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th class="cb-col">
                            <input type="checkbox" class="cb" id="selectAll" onchange="toggleAll(this)">
                        </th>
                        <th>Quiz</th>
                        <th>Section</th>
                        <th>Status</th>
                        <th>Questions</th>
                        <th>Attempts</th>
                        <th>Avg Score</th>
                        <th>Pass %</th>
                        <th>Updated</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($quizzes as $q): ?>
                <?php
                    $sec    = $q['section'] ?? '';
                    $secCls = $sec === 'math' ? 'math' : ($sec === 'reading-writing' ? 'rw' : 'none');
                    $secLbl = $sec === 'math' ? 'Math' : ($sec === 'reading-writing' ? 'R&amp;W' : '—');
                ?>
                <tr>
                    <td class="cb-col">
                        <input type="checkbox" class="cb row-cb" value="<?= $q['id'] ?>" onchange="updateBulk()">
                    </td>
                    <td>
                        <div class="quiz-title"><?= htmlspecialchars($q['title']) ?></div>
                        <code class="quiz-slug"><?= htmlspecialchars($q['lesson_slug'] ?? '') ?></code>
                    </td>
                    <td>
                        <span class="section-tag <?= $secCls ?>"><?= $secLbl ?></span>
                    </td>
                    <td>
                        <span class="status-pill <?= htmlspecialchars($q['status']) ?>">
                            <?php if ($q['status'] === 'published'): ?>
                            <span class="status-dot"></span>
                            <?php endif; ?>
                            <?= ucfirst($q['status']) ?>
                        </span>
                    </td>
                    <td><span class="num"><?= (int)$q['q_count'] ?></span></td>
                    <td><span class="num"><?= number_format((int)$q['attempts']) ?></span></td>
                    <td>
                        <?php if ($q['avg_score'] !== null): ?>
                        <span class="score <?= scoreClass((float)$q['avg_score']) ?>"><?= $q['avg_score'] ?>%</span>
                        <?php else: ?>
                        <span class="score na">—</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="num"><?= htmlspecialchars($q['passing_score'] ?? '—') ?>%</span></td>
                    <td style="color:var(--tx3);font-size:.625rem;font-family:var(--fm);white-space:nowrap">
                        <?= date('M j, Y', strtotime($q['updated_at'])) ?>
                    </td>
                    <td>
                        <div class="row-actions">
                            <a href="/quizzes/edit.php?id=<?= $q['id'] ?>"
                               class="icon-btn edit" title="Edit">
                                <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </a>
                            <a href="/quizzes/builder.php?id=<?= $q['id'] ?>"
                               class="icon-btn" title="Open Builder">
                                <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                            </a>
                            <a href="/quizzes/preview.php?id=<?= $q['id'] ?>"
                               class="icon-btn" title="Preview" target="_blank">
                                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </a>
                            <a href="/quizzes/results.php?id=<?= $q['id'] ?>"
                               class="icon-btn" title="Results">
                                <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                            </a>
                            <button class="icon-btn del" title="Delete"
                                    onclick="confirmDelete(<?= $q['id'] ?>,'<?= htmlspecialchars(addslashes($q['title'])) ?>')">
                                <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages > 1): ?>
        <div class="pagination">
            <div class="pag-info">
                Showing <?= $offset + 1 ?>–<?= min($offset + $perPage, $total) ?> of <?= number_format($total) ?>
            </div>
            <div class="pag-btns">
                <a href="?status=<?= $status ?>&q=<?= urlencode($search) ?>&page=<?= max(1, $page - 1) ?>"
                   class="pag-btn <?= $page <= 1 ? 'disabled' : '' ?>">‹</a>
                <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
                <a href="?status=<?= $status ?>&q=<?= urlencode($search) ?>&page=<?= $i ?>"
                   class="pag-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <a href="?status=<?= $status ?>&q=<?= urlencode($search) ?>&page=<?= min($pages, $page + 1) ?>"
                   class="pag-btn <?= $page >= $pages ? 'disabled' : '' ?>">›</a>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div><!-- /table-card -->

</main>

<!-- Delete confirmation modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal">
        <div class="modal-title">Delete quiz?</div>
        <div class="modal-body">
            This will permanently delete <strong id="deleteQuizName"></strong> and all its questions.
            Student attempt records are kept. <strong>This cannot be undone.</strong>
        </div>
        <div class="modal-foot">
            <button class="btn btn-ghost" onclick="closeModal()">Cancel</button>
            <button class="btn btn-danger" id="deleteConfirmBtn">Delete forever</button>
        </div>
    </div>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<script>
// ── Delete ────────────────────────────────────────────────────────────────
let deleteId = null;

function confirmDelete(id, name) {
    deleteId = id;
    document.getElementById('deleteQuizName').textContent = '"' + name + '"';
    document.getElementById('deleteModal').classList.add('show');
}
function closeModal() {
    document.getElementById('deleteModal').classList.remove('show');
    deleteId = null;
}
document.getElementById('deleteModal').addEventListener('click', e => {
    if (e.target === e.currentTarget) closeModal();
});
document.getElementById('deleteConfirmBtn').addEventListener('click', async () => {
    if (!deleteId) return;
    try {
        const res  = await fetch('/api/delete-quiz.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: deleteId, force: 0 })
        });
        const data = await res.json();
        closeModal();
        if (data.success) {
            toast('Quiz deleted.', 'success');
            setTimeout(() => location.reload(), 900);
        } else if (data.has_attempts) {
            if (confirm(`This quiz has ${data.attempt_count} student attempt(s). Delete anyway?`)) {
                const r2   = await fetch('/api/delete-quiz.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: deleteId, force: 1 }) });
                const data2 = await r2.json();
                if (data2.success) { toast('Quiz deleted.', 'success'); setTimeout(() => location.reload(), 900); }
                else toast(data2.error || 'Error deleting quiz.', 'error');
            }
        } else {
            toast(data.error || 'Error deleting quiz.', 'error');
        }
    } catch (e) { toast('Network error.', 'error'); }
});

// ── Bulk selection ────────────────────────────────────────────────────────
function updateBulk() {
    const cbs = document.querySelectorAll('.row-cb:checked');
    document.getElementById('bulkCount').textContent = cbs.length + ' selected';
    document.getElementById('bulkBar').classList.toggle('show', cbs.length > 0);
}
function toggleAll(master) {
    document.querySelectorAll('.row-cb').forEach(cb => cb.checked = master.checked);
    updateBulk();
}
function clearSelection() {
    document.querySelectorAll('.row-cb, #selectAll').forEach(cb => cb.checked = false);
    document.getElementById('bulkBar').classList.remove('show');
}
function getSelected() {
    return [...document.querySelectorAll('.row-cb:checked')].map(c => c.value);
}

async function bulkDelete() {
    const ids = getSelected();
    if (!ids.length || !confirm(`Permanently delete ${ids.length} quiz(zes)?`)) return;
    let ok = 0;
    for (const id of ids) {
        try {
            const r = await fetch('/api/delete-quiz.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id, force: 1 }) });
            const d = await r.json();
            if (d.success) ok++;
        } catch (e) {}
    }
    toast(`${ok} quiz${ok !== 1 ? 'zes' : ''} deleted.`, 'success');
    setTimeout(() => location.reload(), 900);
}
async function bulkPublish() {
    const ids = getSelected();
    if (!ids.length) return;
    let ok = 0;
    for (const id of ids) {
        try {
            const r = await fetch('/api/publish-quiz.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id, status: 'published' }) });
            const d = await r.json();
            if (d.success) ok++;
        } catch (e) {}
    }
    toast(`${ok} quiz${ok !== 1 ? 'zes' : ''} published.`, 'success');
    setTimeout(() => location.reload(), 700);
}

// ── Toast ─────────────────────────────────────────────────────────────────
function toast(msg, type = 'info') {
    const wrap = document.getElementById('toastWrap');
    const el   = document.createElement('div');
    el.className   = `toast ${type}`;
    el.textContent = msg;
    wrap.appendChild(el);
    requestAnimationFrame(() => requestAnimationFrame(() => el.classList.add('show')));
    setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 400); }, 3200);
}
</script>
</body>
</html>