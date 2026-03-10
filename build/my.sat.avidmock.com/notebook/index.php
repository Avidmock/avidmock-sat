<?php
/**
 * Smart Notebook — Students photograph homework, save problems, get SAT-correlated practice.
 * The bridge between school math and SAT prep.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/SmartNotebook.php';

Auth::requireStudent();
$userId    = (int) $_SESSION['user_id'];
$userTier  = current_tier();

$entries      = SmartNotebook::getEntries($userId, ['limit' => 20]);
$folders      = SmartNotebook::getFolders($userId);
$domainStats  = SmartNotebook::getDomainStats($userId);
$recs         = SmartNotebook::getRecommendations($userId);
$totalEntries = $entries['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Smart Notebook — Avidmock SAT</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700;9..40,800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css">
<style>
:root{--dk:#143230;--ac:#1fe290;--ac2:#17c87a;--tx:#1a1a2e;--bg:#f7faf9;--card:#fff;--border:rgba(20,50,48,.08);--muted:rgba(26,26,46,.5)}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--tx);min-height:100vh}

/* Layout */
.main-content{margin-left:260px;margin-top:56px;padding:32px 32px 80px;min-height:calc(100vh - 56px)}
@media(max-width:768px){.main-content{margin-left:0;padding:20px 16px 72px}}

/* Sidebar */
.nb-logo{font:800 1.1rem 'DM Sans';color:var(--ac);text-decoration:none;display:flex;align-items:center;gap:.5rem}
.nb-nav a{display:flex;align-items:center;gap:.6rem;padding:.6rem .8rem;border-radius:8px;color:rgba(255,255,255,.6);text-decoration:none;font:500 .88rem 'DM Sans';transition:all .15s}
.nb-nav a:hover,.nb-nav a.active{background:rgba(31,226,144,.1);color:var(--ac)}
.nb-nav .nb-badge{margin-left:auto;font:500 .7rem 'DM Mono';background:rgba(31,226,144,.15);color:var(--ac);padding:.15rem .45rem;border-radius:20px}
.nb-divider{height:1px;background:rgba(255,255,255,.08);margin:.5rem 0}
.nb-section-label{font:.6rem 'DM Sans';color:rgba(255,255,255,.3);text-transform:uppercase;letter-spacing:1px;margin-bottom:.5rem}

/* Header */
.nb-header{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;margin-bottom:2rem;flex-wrap:wrap}
.nb-title{font:800 1.75rem/1.2 'DM Sans';color:var(--dk)}
.nb-subtitle{color:var(--muted);font-size:.9rem;margin-top:.25rem}
.nb-actions{display:flex;gap:.75rem;flex-wrap:wrap}

.btn{display:inline-flex;align-items:center;gap:.4rem;padding:.6rem 1.2rem;border-radius:10px;font:600 .85rem 'DM Sans';cursor:pointer;border:none;transition:all .15s}
.btn-primary{background:var(--ac);color:#071512}
.btn-primary:hover{background:var(--ac2);transform:translateY(-1px);box-shadow:0 4px 12px rgba(31,226,144,.25)}
.btn-secondary{background:var(--card);color:var(--dk);border:1px solid var(--border)}
.btn-secondary:hover{border-color:var(--ac)}

/* Stats row */
.nb-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:1rem;margin-bottom:2rem}
.nb-stat{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:1rem 1.25rem;text-align:center}
.nb-stat-num{font:700 1.4rem 'DM Mono';color:var(--dk)}
.nb-stat-label{font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-top:.15rem}
.nb-stat.accent .nb-stat-num{color:var(--ac2)}

/* Entry cards */
.nb-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1rem;margin-bottom:2rem}

.nb-entry{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:1.25rem;transition:all .2s;position:relative;overflow:hidden}
.nb-entry:hover{border-color:rgba(31,226,144,.3);box-shadow:0 4px 20px rgba(20,50,48,.06)}
.nb-entry-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem}
.nb-entry-type{font:.65rem 'DM Mono';text-transform:uppercase;letter-spacing:.5px;padding:.2rem .5rem;border-radius:4px}
.nb-entry-type.scan{background:rgba(31,226,144,.08);color:var(--ac2)}
.nb-entry-type.text{background:rgba(91,40,164,.08);color:#5b28a4}
.nb-entry-date{font:.75rem 'DM Mono';color:var(--muted)}

.nb-entry-text{font:.9rem/1.6 'DM Sans';color:var(--tx);margin-bottom:.75rem;max-height:80px;overflow:hidden;position:relative}
.nb-entry-text::after{content:'';position:absolute;bottom:0;left:0;right:0;height:30px;background:linear-gradient(transparent,var(--card))}

.nb-entry-domain{display:inline-flex;align-items:center;gap:.3rem;font:.72rem 'DM Sans';color:var(--ac2);background:rgba(31,226,144,.06);padding:.25rem .6rem;border-radius:20px;margin-right:.4rem}
.nb-entry-diff{font:.7rem 'DM Mono';padding:.15rem .4rem;border-radius:4px}
.nb-entry-diff.easy{background:rgba(31,226,144,.1);color:var(--ac2)}
.nb-entry-diff.medium{background:rgba(255,179,71,.1);color:#d49300}
.nb-entry-diff.hard{background:rgba(255,107,107,.1);color:#e53e3e}

.nb-entry-footer{display:flex;justify-content:space-between;align-items:center;margin-top:.75rem;padding-top:.6rem;border-top:1px solid var(--border)}
.nb-star{cursor:pointer;color:rgba(26,26,46,.2);transition:color .15s}
.nb-star.active,.nb-star:hover{color:#fbbf24}
.nb-entry-actions{display:flex;gap:.4rem}
.nb-entry-btn{padding:.3rem .6rem;border-radius:6px;font:.72rem 'DM Sans';cursor:pointer;border:1px solid var(--border);background:transparent;color:var(--muted);transition:all .15s}
.nb-entry-btn:hover{border-color:var(--ac);color:var(--ac2)}
.nb-entry-btn.practice{background:var(--ac);color:#071512;border-color:var(--ac);font-weight:600}

/* Recommendations section */
.nb-recs{margin-bottom:2rem}
.nb-recs-title{font:700 1.1rem 'DM Sans';color:var(--dk);margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
.nb-recs-list{display:flex;gap:.75rem;overflow-x:auto;padding-bottom:.5rem}
.nb-rec-card{flex:0 0 240px;background:linear-gradient(135deg,var(--dk),#1a4a46);border-radius:12px;padding:1.1rem;color:#fff}
.nb-rec-domain{font:.65rem 'DM Mono';color:var(--ac);text-transform:uppercase;letter-spacing:.5px}
.nb-rec-title{font:600 .95rem 'DM Sans';margin:.4rem 0}
.nb-rec-reason{font:.78rem 'DM Sans';color:rgba(255,255,255,.5);margin-bottom:.75rem}
.nb-rec-btn{display:inline-block;padding:.4rem .9rem;background:var(--ac);color:#071512;border-radius:8px;font:600 .78rem 'DM Sans';text-decoration:none;transition:transform .15s}
.nb-rec-btn:hover{transform:translateY(-1px)}

/* Upload modal */
.nb-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;display:none;align-items:center;justify-content:center}
.nb-modal-overlay.show{display:flex}
.nb-modal{background:var(--card);border-radius:16px;padding:2rem;max-width:500px;width:90%;max-height:80vh;overflow-y:auto}
.nb-modal h2{font:700 1.2rem 'DM Sans';color:var(--dk);margin-bottom:1rem}
.nb-modal-close{position:absolute;top:1rem;right:1rem;background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--muted)}

.nb-upload-zone{border:2px dashed rgba(31,226,144,.3);border-radius:12px;padding:2.5rem;text-align:center;cursor:pointer;transition:all .2s}
.nb-upload-zone:hover,.nb-upload-zone.dragover{border-color:var(--ac);background:rgba(31,226,144,.03)}
.nb-upload-zone svg{width:48px;height:48px;color:var(--ac);margin-bottom:.75rem}
.nb-upload-zone p{color:var(--muted);font-size:.88rem}

.nb-text-input{width:100%;min-height:100px;padding:.75rem;border:1px solid var(--border);border-radius:8px;font:400 .9rem 'DM Sans';resize:vertical;margin-bottom:1rem}
.nb-text-input:focus{outline:none;border-color:var(--ac)}

/* Empty state */
.nb-empty{text-align:center;padding:4rem 2rem}
.nb-empty svg{width:80px;height:80px;color:rgba(31,226,144,.25);margin-bottom:1rem}
.nb-empty h3{font:700 1.2rem 'DM Sans';color:var(--dk);margin-bottom:.5rem}
.nb-empty p{color:var(--muted);max-width:400px;margin:0 auto .75rem}
</style>
</head>
<body>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/sidebar.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/topbar.php';
?>

<main class="main-content">

    <div class="nb-header">
        <div>
            <h1 class="nb-title">Smart Notebook</h1>
            <p class="nb-subtitle">Photograph any math problem. AI maps it to SAT curriculum and suggests practice.</p>
        </div>
        <div class="nb-actions">
            <button class="btn btn-primary" onclick="showModal('scan')">
                <svg viewBox="0 0 24 24" width="18" height="18"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="13" r="4" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                Scan Problem
            </button>
            <button class="btn btn-secondary" onclick="showModal('text')">
                <svg viewBox="0 0 24 24" width="18" height="18"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" fill="none" stroke="currentColor" stroke-width="2"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                Add Note
            </button>
        </div>
    </div>

    <!-- Stats -->
    <div class="nb-stats">
        <div class="nb-stat"><div class="nb-stat-num"><?= $totalEntries ?></div><div class="nb-stat-label">Total Entries</div></div>
        <div class="nb-stat accent"><div class="nb-stat-num"><?= count($domainStats) ?></div><div class="nb-stat-label">SAT Domains</div></div>
        <?php
        $totalScans = 0;
        foreach ($entries['entries'] ?? [] as $e) if ($e['type'] === 'scan') $totalScans++;
        ?>
        <div class="nb-stat"><div class="nb-stat-num"><?= $totalScans ?></div><div class="nb-stat-label">Problems Scanned</div></div>
        <div class="nb-stat"><div class="nb-stat-num"><?= count($recs) ?></div><div class="nb-stat-label">Practice Suggestions</div></div>
    </div>

    <!-- Recommendations -->
    <?php if (!empty($recs)): ?>
    <div class="nb-recs">
        <div class="nb-recs-title">
            <svg viewBox="0 0 24 24" width="20" height="20"><path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" fill="none" stroke="currentColor" stroke-width="2"/></svg>
            Recommended Practice
        </div>
        <div class="nb-recs-list">
            <?php foreach ($recs as $r): ?>
            <div class="nb-rec-card">
                <div class="nb-rec-domain"><?= e($r['domain']) ?></div>
                <div class="nb-rec-title"><?= e($r['title']) ?></div>
                <div class="nb-rec-reason"><?= e($r['reason']) ?></div>
                <a href="/learn/quiz.php?quiz_id=<?= $r['quiz_id'] ?>" class="nb-rec-btn">Practice Now</a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Entries -->
    <?php if (!empty($entries['entries'])): ?>
    <div class="nb-grid" id="entriesGrid">
        <?php foreach ($entries['entries'] as $entry): ?>
        <div class="nb-entry" data-id="<?= $entry['id'] ?>">
            <div class="nb-entry-header">
                <span class="nb-entry-type <?= $entry['type'] ?>"><?= $entry['type'] === 'scan' ? 'Scanned' : 'Note' ?></span>
                <span class="nb-entry-date"><?= date('M j', strtotime($entry['created_at'])) ?></span>
            </div>
            <div class="nb-entry-text"><?= e($entry['recognized_text'] ?: 'No text recognized') ?></div>
            <div style="margin-bottom:.5rem">
                <?php if ($entry['sat_domain']): ?>
                <span class="nb-entry-domain"><?= e(ucwords(str_replace('-', ' ', $entry['sat_domain']))) ?></span>
                <?php endif; ?>
                <?php if ($entry['difficulty']): ?>
                <span class="nb-entry-diff <?= $entry['difficulty'] ?>"><?= ucfirst($entry['difficulty']) ?></span>
                <?php endif; ?>
            </div>
            <div class="nb-entry-footer">
                <span class="nb-star <?= $entry['is_starred'] ? 'active' : '' ?>" onclick="toggleStar(<?= $entry['id'] ?>)">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="<?= $entry['is_starred'] ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                </span>
                <div class="nb-entry-actions">
                    <button class="nb-entry-btn" onclick="viewEntry(<?= $entry['id'] ?>)">View</button>
                    <?php if ($entry['sat_domain']): ?>
                    <button class="nb-entry-btn practice">Practice</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="nb-empty">
        <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 016.5 17H20" fill="none" stroke="currentColor" stroke-width="2"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z" fill="none" stroke="currentColor" stroke-width="2"/></svg>
        <h3>Your notebook is empty</h3>
        <p>Scan a math problem from your homework, textbook, or screen. AI will identify it, solve it, and map it to SAT topics so you can practice similar problems.</p>
        <button class="btn btn-primary" onclick="showModal('scan')">Scan Your First Problem</button>
    </div>
    <?php endif; ?>

</main>

<!-- Scan Modal -->
<div class="nb-modal-overlay" id="modalOverlay">
<div class="nb-modal" id="modal">
    <h2 id="modalTitle">Scan a Math Problem</h2>

    <!-- Scan mode -->
    <div id="scanMode">
        <div class="nb-upload-zone" id="dropZone">
            <svg viewBox="0 0 24 24"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="13" r="4" fill="none" stroke="currentColor" stroke-width="2"/></svg>
            <p><strong>Drop an image here</strong> or click to upload</p>
            <p style="font-size:.78rem;margin-top:.4rem">Supports: JPG, PNG, WEBP up to 5MB</p>
        </div>
        <input type="file" id="fileInput" accept="image/*" style="display:none">
        <div id="scanPreview" style="display:none;margin-top:1rem;text-align:center">
            <img id="previewImg" style="max-width:100%;border-radius:8px;margin-bottom:.75rem">
            <button class="btn btn-primary" onclick="analyzeScan()" id="analyzeBtn">Analyze with AI</button>
        </div>
        <div id="scanResult" style="display:none;margin-top:1rem"></div>
    </div>

    <!-- Text mode -->
    <div id="textMode" style="display:none">
        <textarea class="nb-text-input" id="noteText" placeholder="Type or paste a math problem..."></textarea>
        <button class="btn btn-primary" onclick="saveNote()">Save to Notebook</button>
    </div>

    <button style="margin-top:1rem" class="btn btn-secondary" onclick="closeModal()">Close</button>
</div>
</div>

<script>
const dropZone  = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
let currentImage = null;

function showModal(mode) {
    document.getElementById('modalOverlay').classList.add('show');
    document.getElementById('scanMode').style.display = mode === 'scan' ? 'block' : 'none';
    document.getElementById('textMode').style.display = mode === 'text' ? 'block' : 'none';
    document.getElementById('modalTitle').textContent = mode === 'scan' ? 'Scan a Math Problem' : 'Add a Note';
    document.getElementById('scanPreview').style.display = 'none';
    document.getElementById('scanResult').style.display = 'none';
}

function closeModal() {
    document.getElementById('modalOverlay').classList.remove('show');
}

dropZone.addEventListener('click', () => fileInput.click());
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragover'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('dragover');
    if (e.dataTransfer.files.length) handleFile(e.dataTransfer.files[0]);
});
fileInput.addEventListener('change', () => { if (fileInput.files.length) handleFile(fileInput.files[0]); });

function handleFile(file) {
    if (!file.type.startsWith('image/')) { alert('Please upload an image.'); return; }
    const reader = new FileReader();
    reader.onload = (e) => {
        currentImage = e.target.result;
        document.getElementById('previewImg').src = currentImage;
        document.getElementById('scanPreview').style.display = 'block';
    };
    reader.readAsDataURL(file);
}

async function analyzeScan() {
    if (!currentImage) return;
    const btn = document.getElementById('analyzeBtn');
    btn.disabled = true;
    btn.textContent = 'Analyzing...';

    try {
        // Strip data URL prefix to get pure base64
        const base64 = currentImage.split(',')[1] || currentImage;

        const resp = await fetch('/api/math-vision.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ image: base64, source: 'notebook' }),
        });
        const data = await resp.json();

        if (data.error) {
            document.getElementById('scanResult').innerHTML = `<p style="color:#e53e3e">${data.error}</p>`;
        } else {
            // Save to notebook
            await fetch('/api/notebook.php?action=add_scan', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ image: base64.substring(0, 200) + '...', ai_result: data }),
            });

            document.getElementById('scanResult').innerHTML = `
                <div style="background:rgba(31,226,144,.06);border:1px solid rgba(31,226,144,.2);border-radius:10px;padding:1rem">
                    <strong style="color:var(--ac2)">Problem Identified!</strong>
                    <p style="margin:.5rem 0;font-family:'DM Mono'">${escHtml(data.problem || '')}</p>
                    <p style="font-size:.85rem;color:var(--muted)">${data.sat_mapping?.domain || ''} &rsaquo; ${data.sat_mapping?.skill || ''}</p>
                    <p style="margin-top:.5rem"><strong>Answer:</strong> ${escHtml(data.solution?.answer || '')}</p>
                    <p style="color:var(--ac2);font-size:.88rem;margin-top:.5rem">Saved to Smart Notebook! +${data.xp_earned || 10} XP</p>
                </div>`;
        }
        document.getElementById('scanResult').style.display = 'block';
    } catch (err) {
        document.getElementById('scanResult').innerHTML = `<p style="color:#e53e3e">Error: ${err.message}</p>`;
        document.getElementById('scanResult').style.display = 'block';
    }

    btn.disabled = false;
    btn.textContent = 'Analyze with AI';
}

async function saveNote() {
    const text = document.getElementById('noteText').value.trim();
    if (!text) return;
    const form = new FormData();
    form.append('text', text);
    form.append('action', 'add_text');
    await fetch('/api/notebook.php?action=add_text', { method: 'POST', body: form });
    location.reload();
}

async function toggleStar(id) {
    const form = new FormData();
    form.append('entry_id', id);
    await fetch('/api/notebook.php?action=star', { method: 'POST', body: form });
    location.reload();
}

function viewEntry(id) {
    // Could open a detail modal — for now scroll to card
    const card = document.querySelector(`[data-id="${id}"]`);
    if (card) card.style.borderColor = 'var(--ac)';
}

function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
</script>

</body>
</html>
