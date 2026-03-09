<?php
/**
 * /sessions/create.php
 * Create a new tutoring session — group, one-on-one, or workshop.
 */
require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

$admin = currentAdmin();
$db    = Database::connect();

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrfToken = $_SESSION['csrf_token'];

/* ── Handle POST ─────────────────────────────────────────────────────────── */
$errors = [];
$vals   = [
    'title'            => '',
    'description'      => '',
    'session_type'     => 'group',
    'session_date'     => '',
    'duration_minutes' => 60,
    'max_students'     => 25,
    'meeting_url'      => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        $vals['title']            = trim($_POST['title']        ?? '');
        $vals['description']      = trim($_POST['description']  ?? '');
        $vals['session_type']     = $_POST['session_type']      ?? 'group';
        $vals['session_date']     = trim($_POST['session_date'] ?? '');
        $vals['duration_minutes'] = (int)($_POST['duration_minutes'] ?? 60);
        $vals['max_students']     = (int)($_POST['max_students']     ?? 25);
        $vals['meeting_url']      = trim($_POST['meeting_url']  ?? '');

        /* Validation */
        if ($vals['title'] === '') {
            $errors['title'] = 'Session title is required.';
        } elseif (mb_strlen($vals['title']) > 255) {
            $errors['title'] = 'Title is too long (max 255 characters).';
        }

        if ($vals['session_date'] === '') {
            $errors['session_date'] = 'Session date and time are required.';
        } else {
            $dt = DateTime::createFromFormat('Y-m-d\TH:i', $vals['session_date']);
            if (!$dt) {
                $errors['session_date'] = 'Invalid date/time format.';
            } elseif ($dt < new DateTime()) {
                $errors['session_date'] = 'Session date must be in the future.';
            }
        }

        $validTypes = ['group', 'one_on_one', 'workshop'];
        if (!in_array($vals['session_type'], $validTypes, true)) {
            $errors['session_type'] = 'Invalid session type.';
        }

        $validDurations = [30, 45, 60, 90, 120];
        if (!in_array($vals['duration_minutes'], $validDurations, true)) {
            $errors['duration_minutes'] = 'Please select a valid duration.';
        }

        if ($vals['max_students'] < 1 || $vals['max_students'] > 500) {
            $errors['max_students'] = 'Max students must be between 1 and 500.';
        }

        if ($vals['meeting_url'] !== '' && !filter_var($vals['meeting_url'], FILTER_VALIDATE_URL)) {
            $errors['meeting_url'] = 'Please enter a valid URL.';
        }

        if (empty($errors)) {
            try {
                $sessionDate = (DateTime::createFromFormat('Y-m-d\TH:i', $vals['session_date']))->format('Y-m-d H:i:s');

                Database::insert('tutoring_sessions', [
                    'title'            => $vals['title'],
                    'description'      => $vals['description'],
                    'session_type'     => $vals['session_type'],
                    'session_date'     => $sessionDate,
                    'duration_minutes' => $vals['duration_minutes'],
                    'max_students'     => $vals['max_students'],
                    'meeting_url'      => $vals['meeting_url'] ?: null,
                    'status'           => 'scheduled',
                    'created_by'       => $admin['id'],
                    'created_at'       => date('Y-m-d H:i:s'),
                    'updated_at'       => date('Y-m-d H:i:s'),
                ]);

                $_SESSION['flash'] = 'ok:Session "' . htmlspecialchars($vals['title']) . '" has been created successfully.';
                header('Location: /sessions/index.php');
                exit;
            } catch (Throwable $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

/* ── Page meta ───────────────────────────────────────────────────────────── */
$pageTitle  = 'Create Session — Avidmock Admin';
$activePage = 'sessions';
$extraHead  = <<<'CSS'
<style>
/* ─────────────────────────────────────────────
   PAGE-SPECIFIC TOKENS
───────────────────────────────────────────── */
.cs-layout{display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;max-width:1140px}
.cs-main{display:flex;flex-direction:column;gap:18px}
.cs-aside{display:flex;flex-direction:column;gap:16px;position:sticky;top:calc(var(--top-h) + 28px)}

/* ── Page header ───────────────────────────── */
.cs-header{margin-bottom:6px}
.cs-header h1{font-family:var(--fh);font-size:1.55rem;font-weight:900;color:var(--tx);letter-spacing:-.03em;margin-bottom:4px}
.cs-header p{font-size:.8125rem;color:var(--tx3);line-height:1.5}

/* ── Card ──────────────────────────────────── */
.cs-card{background:var(--ink2,#0e2522);border:1px solid var(--bd);border-radius:16px;overflow:hidden}
.cs-card-head{padding:16px 22px;border-bottom:1px solid var(--bd);display:flex;align-items:center;gap:11px}
.cs-card-ico{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.cs-card-ico svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.cs-card-title{font-size:.875rem;font-weight:700;color:var(--tx)}
.cs-card-sub{font-size:.625rem;color:var(--tx3);margin-top:1px}
.cs-card-body{padding:22px;display:flex;flex-direction:column;gap:18px}

/* ── Fields ────────────────────────────────── */
.cs-field{display:flex;flex-direction:column;gap:5px}
.cs-field-row{display:grid;gap:14px}
.cs-fr2{grid-template-columns:1fr 1fr}
.cs-label{font-size:.5625rem;font-weight:700;color:var(--tx3);text-transform:uppercase;letter-spacing:.8px}
.cs-label .req{color:var(--ac)}
.cs-hint{font-size:.5625rem;color:var(--tx3);margin-top:2px;line-height:1.45}
.cs-input,.cs-select,.cs-textarea{
    background:rgba(255,255,255,.05);border:1.5px solid rgba(255,255,255,.1);
    border-radius:9px;color:#fff;font-family:var(--ff);font-size:.8125rem;
    padding:10px 13px;outline:none;transition:border-color .18s,background .18s,box-shadow .18s;width:100%
}
.cs-input:focus,.cs-select:focus,.cs-textarea:focus{
    border-color:var(--ac);background:rgba(31,226,144,.03);
    box-shadow:0 0 0 3px rgba(31,226,144,.08)
}
.cs-input.has-error,.cs-select.has-error,.cs-textarea.has-error{border-color:var(--err)}
.cs-input::placeholder,.cs-textarea::placeholder{color:var(--tx3);opacity:.7}
.cs-select option{background:#0e2522;color:var(--tx)}
.cs-textarea{resize:vertical;min-height:90px;line-height:1.6}
.cs-char{font-family:var(--fm);font-size:.5rem;color:var(--tx3);text-align:right;margin-top:2px}
.cs-char.warn{color:var(--warn)}.cs-char.over{color:var(--err)}
.cs-error-msg{font-size:.6875rem;color:var(--err);display:flex;align-items:center;gap:4px;margin-top:2px}
.cs-error-msg svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0}

/* ── Radio cards (session type) ────────────── */
.cs-type-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px}
.cs-type-card{position:relative}
.cs-type-card input{position:absolute;opacity:0;width:0;height:0}
.cs-type-card label{
    display:flex;flex-direction:column;gap:8px;padding:16px;
    border-radius:12px;border:1.5px solid var(--bd);cursor:pointer;
    transition:all .2s;background:var(--sf);min-height:130px
}
.cs-type-card label:hover{border-color:rgba(255,255,255,.15);background:var(--sf2,rgba(255,255,255,.07))}
.cs-type-card input:checked + label{
    border-color:var(--ac);background:rgba(31,226,144,.06);
    box-shadow:0 0 0 1px var(--ac),0 4px 20px rgba(31,226,144,.08)
}
.cs-type-ico{
    width:36px;height:36px;border-radius:10px;
    display:flex;align-items:center;justify-content:center;
    transition:transform .2s
}
.cs-type-card label:hover .cs-type-ico{transform:scale(1.05)}
.cs-type-ico svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.cs-type-name{font-size:.8125rem;font-weight:700;color:var(--tx);transition:color .2s}
.cs-type-desc{font-size:.625rem;color:var(--tx3);line-height:1.45}
.cs-type-card input:checked + label .cs-type-name{color:var(--ac)}
.cs-type-check{
    position:absolute;top:12px;right:12px;width:20px;height:20px;
    border-radius:50%;background:var(--ac);display:flex;align-items:center;
    justify-content:center;opacity:0;transform:scale(.5);transition:all .2s
}
.cs-type-check svg{width:11px;height:11px;stroke:#0c1f1d;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}
.cs-type-card input:checked ~ .cs-type-check{opacity:1;transform:scale(1)}

/* ── Errors box ────────────────────────────── */
.cs-errors{background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:12px;padding:14px 18px;display:flex;flex-direction:column;gap:5px}
.cs-errors li{font-size:.8125rem;color:var(--err);list-style:none;display:flex;align-items:center;gap:6px}
.cs-errors li::before{content:'';width:5px;height:5px;border-radius:50%;background:var(--err);flex-shrink:0}

/* ── Button row ────────────────────────────── */
.cs-actions{display:flex;align-items:center;gap:10px;padding-top:4px}

/* ── Info sidebar ──────────────────────────── */
.cs-info{background:var(--sf);border-radius:14px;padding:18px;display:flex;flex-direction:column;gap:14px}
.cs-info-title{font-size:.75rem;font-weight:700;color:var(--tx);display:flex;align-items:center;gap:7px}
.cs-info-title svg{width:14px;height:14px;stroke:var(--ac);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.cs-tip{display:flex;gap:10px;padding:10px 0;border-bottom:1px solid var(--bd)}
.cs-tip:last-child{border-bottom:none;padding-bottom:0}
.cs-tip-num{
    width:22px;height:22px;border-radius:7px;background:rgba(31,226,144,.1);
    color:var(--ac);font-size:.625rem;font-weight:800;font-family:var(--fm);
    display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px
}
.cs-tip-text{font-size:.6875rem;color:var(--tx2);line-height:1.55}
.cs-tip-text strong{color:var(--tx);font-weight:600}

/* ── Summary card ──────────────────────────── */
.cs-sum-row{display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--bd)}
.cs-sum-row:last-child{border-bottom:none;padding-bottom:0}
.cs-sum-key{font-size:.6875rem;color:var(--tx3);font-weight:600}
.cs-sum-val{font-size:.6875rem;font-weight:700;color:var(--tx);font-family:var(--fm)}

/* ── Reveal animation ──────────────────────── */
.reveal{opacity:0;transform:translateY(14px);animation:csRev .45s cubic-bezier(.16,1,.3,1) forwards}
@keyframes csRev{to{opacity:1;transform:none}}
.d1{animation-delay:.04s}.d2{animation-delay:.08s}.d3{animation-delay:.12s}.d4{animation-delay:.16s}.d5{animation-delay:.2s}

/* ── Responsive ────────────────────────────── */
@media(max-width:1060px){
    .cs-layout{grid-template-columns:1fr}
    .cs-aside{position:static}
}
@media(max-width:768px){
    .cs-type-grid{grid-template-columns:1fr}
    .cs-fr2{grid-template-columns:1fr}
}
</style>
CSS;

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<!-- ── TOPBAR ───────────────────────────────────────────────────────── -->
<header class="topbar">
    <button class="topbar-ham" onclick="openSidebar()"><span></span><span></span><span></span></button>
    <div class="tb-breadcrumb">
        <a href="/sessions/index.php">Sessions</a>
        <span class="tb-breadcrumb-sep">&rsaquo;</span>
        <span class="tb-breadcrumb-cur">Create New</span>
    </div>
    <div class="topbar-spacer"></div>
</header>

<!-- ── MAIN ─────────────────────────────────────────────────────────── -->
<main class="main">

    <div class="cs-header reveal d1">
        <h1>Create New Session</h1>
        <p>Schedule a tutoring session for your students. Fill in the details below and publish when ready.</p>
    </div>

    <?php if (!empty($errors)): ?>
    <ul class="cs-errors reveal d1">
        <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars(is_string($e) ? $e : '') ?></li>
        <?php endforeach ?>
    </ul>
    <?php endif ?>

    <form method="POST" id="sessionForm" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

    <div class="cs-layout">

        <!-- ══════════════ LEFT COLUMN ══════════════ -->
        <div class="cs-main">

            <!-- Session Details -->
            <div class="cs-card reveal d2">
                <div class="cs-card-head">
                    <div class="cs-card-ico" style="background:rgba(31,226,144,.1);color:var(--ac)">
                        <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </div>
                    <div>
                        <div class="cs-card-title">Session Details</div>
                        <div class="cs-card-sub">Core information about your tutoring session</div>
                    </div>
                </div>
                <div class="cs-card-body">
                    <!-- Title -->
                    <div class="cs-field">
                        <label class="cs-label" for="title">Title <span class="req">*</span></label>
                        <input type="text" id="title" name="title"
                               class="cs-input <?= isset($errors['title']) ? 'has-error' : '' ?>"
                               value="<?= htmlspecialchars($vals['title']) ?>"
                               placeholder="e.g. SAT Math — Algebra Fundamentals"
                               maxlength="255" required
                               oninput="updateChar(this,255,'titleCt');clearError(this)">
                        <div class="cs-char" id="titleCt"><?= mb_strlen($vals['title']) ?> / 255</div>
                        <?php if (isset($errors['title'])): ?>
                            <div class="cs-error-msg"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><?= htmlspecialchars($errors['title']) ?></div>
                        <?php endif ?>
                    </div>

                    <!-- Description -->
                    <div class="cs-field">
                        <label class="cs-label" for="description">Description</label>
                        <textarea id="description" name="description"
                                  class="cs-textarea"
                                  placeholder="Describe what students will learn in this session..."
                                  maxlength="2000"
                                  oninput="updateChar(this,2000,'descCt')"><?= htmlspecialchars($vals['description']) ?></textarea>
                        <div class="cs-char" id="descCt"><?= mb_strlen($vals['description']) ?> / 2000</div>
                    </div>
                </div>
            </div>

            <!-- Session Type -->
            <div class="cs-card reveal d3">
                <div class="cs-card-head">
                    <div class="cs-card-ico" style="background:rgba(59,130,246,.1);color:#3b82f6">
                        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                    </div>
                    <div>
                        <div class="cs-card-title">Session Type</div>
                        <div class="cs-card-sub">Choose the format that best fits your goals</div>
                    </div>
                </div>
                <div class="cs-card-body">
                    <div class="cs-type-grid">
                        <!-- Group -->
                        <div class="cs-type-card">
                            <input type="radio" name="session_type" id="type_group" value="group"
                                   <?= $vals['session_type'] === 'group' ? 'checked' : '' ?>
                                   onchange="updateSummary()">
                            <label for="type_group">
                                <div class="cs-type-ico" style="background:rgba(31,226,144,.1);color:var(--ac)">
                                    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                                </div>
                                <div class="cs-type-name">Group Session</div>
                                <div class="cs-type-desc">Multiple students join a shared session with collaborative learning</div>
                            </label>
                            <div class="cs-type-check"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
                        </div>

                        <!-- One-on-One -->
                        <div class="cs-type-card">
                            <input type="radio" name="session_type" id="type_one_on_one" value="one_on_one"
                                   <?= $vals['session_type'] === 'one_on_one' ? 'checked' : '' ?>
                                   onchange="updateSummary()">
                            <label for="type_one_on_one">
                                <div class="cs-type-ico" style="background:rgba(139,92,246,.1);color:#8b5cf6">
                                    <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                </div>
                                <div class="cs-type-name">One-on-One</div>
                                <div class="cs-type-desc">Private tutoring tailored to the individual student's needs</div>
                            </label>
                            <div class="cs-type-check"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
                        </div>

                        <!-- Workshop -->
                        <div class="cs-type-card">
                            <input type="radio" name="session_type" id="type_workshop" value="workshop"
                                   <?= $vals['session_type'] === 'workshop' ? 'checked' : '' ?>
                                   onchange="updateSummary()">
                            <label for="type_workshop">
                                <div class="cs-type-ico" style="background:rgba(245,158,11,.1);color:#f59e0b">
                                    <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
                                </div>
                                <div class="cs-type-name">Workshop</div>
                                <div class="cs-type-desc">Structured class covering a specific topic in depth with exercises</div>
                            </label>
                            <div class="cs-type-check"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
                        </div>
                    </div>
                    <?php if (isset($errors['session_type'])): ?>
                        <div class="cs-error-msg"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><?= htmlspecialchars($errors['session_type']) ?></div>
                    <?php endif ?>
                </div>
            </div>

            <!-- Scheduling -->
            <div class="cs-card reveal d4">
                <div class="cs-card-head">
                    <div class="cs-card-ico" style="background:rgba(139,92,246,.1);color:#8b5cf6">
                        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </div>
                    <div>
                        <div class="cs-card-title">Scheduling</div>
                        <div class="cs-card-sub">Set the date, time, and duration</div>
                    </div>
                </div>
                <div class="cs-card-body">
                    <div class="cs-field-row cs-fr2">
                        <!-- Date & Time -->
                        <div class="cs-field">
                            <label class="cs-label" for="session_date">Date &amp; Time <span class="req">*</span></label>
                            <input type="datetime-local" id="session_date" name="session_date"
                                   class="cs-input <?= isset($errors['session_date']) ? 'has-error' : '' ?>"
                                   value="<?= htmlspecialchars($vals['session_date']) ?>"
                                   min="<?= date('Y-m-d\TH:i') ?>"
                                   required onchange="updateSummary();clearError(this)">
                            <?php if (isset($errors['session_date'])): ?>
                                <div class="cs-error-msg"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><?= htmlspecialchars($errors['session_date']) ?></div>
                            <?php endif ?>
                        </div>

                        <!-- Duration -->
                        <div class="cs-field">
                            <label class="cs-label" for="duration_minutes">Duration <span class="req">*</span></label>
                            <select id="duration_minutes" name="duration_minutes"
                                    class="cs-select <?= isset($errors['duration_minutes']) ? 'has-error' : '' ?>"
                                    onchange="updateSummary()">
                                <option value="30"  <?= $vals['duration_minutes'] === 30  ? 'selected' : '' ?>>30 minutes</option>
                                <option value="45"  <?= $vals['duration_minutes'] === 45  ? 'selected' : '' ?>>45 minutes</option>
                                <option value="60"  <?= $vals['duration_minutes'] === 60  ? 'selected' : '' ?>>60 minutes</option>
                                <option value="90"  <?= $vals['duration_minutes'] === 90  ? 'selected' : '' ?>>90 minutes</option>
                                <option value="120" <?= $vals['duration_minutes'] === 120 ? 'selected' : '' ?>>120 minutes</option>
                            </select>
                            <?php if (isset($errors['duration_minutes'])): ?>
                                <div class="cs-error-msg"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><?= htmlspecialchars($errors['duration_minutes']) ?></div>
                            <?php endif ?>
                        </div>
                    </div>

                    <div class="cs-field-row cs-fr2">
                        <!-- Max Students -->
                        <div class="cs-field">
                            <label class="cs-label" for="max_students">Max Students <span class="req">*</span></label>
                            <input type="number" id="max_students" name="max_students"
                                   class="cs-input <?= isset($errors['max_students']) ? 'has-error' : '' ?>"
                                   value="<?= (int)$vals['max_students'] ?>"
                                   min="1" max="500" required
                                   oninput="updateSummary();clearError(this)">
                            <div class="cs-hint">Set to 1 for one-on-one sessions</div>
                            <?php if (isset($errors['max_students'])): ?>
                                <div class="cs-error-msg"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><?= htmlspecialchars($errors['max_students']) ?></div>
                            <?php endif ?>
                        </div>

                        <!-- Meeting URL -->
                        <div class="cs-field">
                            <label class="cs-label" for="meeting_url">Meeting URL <span style="color:var(--tx3);font-weight:500;text-transform:none;letter-spacing:0">(optional)</span></label>
                            <input type="url" id="meeting_url" name="meeting_url"
                                   class="cs-input <?= isset($errors['meeting_url']) ? 'has-error' : '' ?>"
                                   value="<?= htmlspecialchars($vals['meeting_url']) ?>"
                                   placeholder="https://zoom.us/j/..."
                                   oninput="clearError(this)">
                            <div class="cs-hint">Zoom, Google Meet, or any video call link</div>
                            <?php if (isset($errors['meeting_url'])): ?>
                                <div class="cs-error-msg"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><?= htmlspecialchars($errors['meeting_url']) ?></div>
                            <?php endif ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="cs-actions reveal d5">
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                    Create Session
                </button>
                <a href="/sessions/index.php" class="btn btn-ghost">
                    Cancel
                </a>
            </div>

        </div><!-- end .cs-main -->

        <!-- ══════════════ RIGHT COLUMN ══════════════ -->
        <div class="cs-aside">

            <!-- Live Summary -->
            <div class="cs-card reveal d2">
                <div class="cs-card-head">
                    <div class="cs-card-ico" style="background:rgba(31,226,144,.1);color:var(--ac)">
                        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </div>
                    <div class="cs-card-title">Session Preview</div>
                </div>
                <div class="cs-card-body" style="padding:16px 20px">
                    <div class="cs-sum-row">
                        <span class="cs-sum-key">Type</span>
                        <span class="cs-sum-val" id="sumType">Group</span>
                    </div>
                    <div class="cs-sum-row">
                        <span class="cs-sum-key">Duration</span>
                        <span class="cs-sum-val" id="sumDuration">60 min</span>
                    </div>
                    <div class="cs-sum-row">
                        <span class="cs-sum-key">Max Students</span>
                        <span class="cs-sum-val" id="sumStudents">25</span>
                    </div>
                    <div class="cs-sum-row">
                        <span class="cs-sum-key">Date</span>
                        <span class="cs-sum-val" id="sumDate" style="color:var(--tx3)">Not set</span>
                    </div>
                    <div class="cs-sum-row">
                        <span class="cs-sum-key">Status</span>
                        <span class="cs-sum-val" id="sumStatus" style="color:var(--ac)">Scheduled</span>
                    </div>
                </div>
            </div>

            <!-- Tips -->
            <div class="cs-card reveal d3" style="background:var(--sf);border-color:var(--bd)">
                <div class="cs-card-body" style="padding:18px 20px">
                    <div class="cs-info-title">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        Session Tips
                    </div>
                    <div class="cs-tip">
                        <div class="cs-tip-num">1</div>
                        <div class="cs-tip-text"><strong>Choose the right type.</strong> Group sessions work best for review topics. Use one-on-one for targeted help with specific weaknesses.</div>
                    </div>
                    <div class="cs-tip">
                        <div class="cs-tip-num">2</div>
                        <div class="cs-tip-text"><strong>Set realistic capacity.</strong> For group sessions, 8-15 students keeps engagement high. Workshops can handle larger audiences.</div>
                    </div>
                    <div class="cs-tip">
                        <div class="cs-tip-num">3</div>
                        <div class="cs-tip-text"><strong>Add the meeting URL.</strong> Students receive this link when they register. You can always update it later from the session editor.</div>
                    </div>
                    <div class="cs-tip">
                        <div class="cs-tip-num">4</div>
                        <div class="cs-tip-text"><strong>Schedule ahead.</strong> Sessions scheduled at least 48 hours in advance get significantly higher student enrollment rates.</div>
                    </div>
                </div>
            </div>

            <!-- Type Breakdown -->
            <div class="cs-card reveal d4" style="background:var(--sf);border-color:var(--bd)">
                <div class="cs-card-body" style="padding:18px 20px">
                    <div class="cs-info-title">
                        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        Session Types Explained
                    </div>
                    <div style="margin-top:10px;display:flex;flex-direction:column;gap:10px">
                        <div style="display:flex;gap:10px;align-items:flex-start">
                            <div style="width:8px;height:8px;border-radius:50%;background:var(--ac);margin-top:4px;flex-shrink:0"></div>
                            <div>
                                <div style="font-size:.6875rem;font-weight:700;color:var(--tx)">Group</div>
                                <div style="font-size:.5625rem;color:var(--tx3);line-height:1.5">Collaborative learning for multiple students. Best for topic reviews and practice problem walkthroughs.</div>
                            </div>
                        </div>
                        <div style="display:flex;gap:10px;align-items:flex-start">
                            <div style="width:8px;height:8px;border-radius:50%;background:#8b5cf6;margin-top:4px;flex-shrink:0"></div>
                            <div>
                                <div style="font-size:.6875rem;font-weight:700;color:var(--tx)">One-on-One</div>
                                <div style="font-size:.5625rem;color:var(--tx3);line-height:1.5">Personalized tutoring for individual students. Ideal for addressing specific weaknesses or test anxiety.</div>
                            </div>
                        </div>
                        <div style="display:flex;gap:10px;align-items:flex-start">
                            <div style="width:8px;height:8px;border-radius:50%;background:#f59e0b;margin-top:4px;flex-shrink:0"></div>
                            <div>
                                <div style="font-size:.6875rem;font-weight:700;color:var(--tx)">Workshop</div>
                                <div style="font-size:.5625rem;color:var(--tx3);line-height:1.5">Structured deep-dive into a subject area. Combines teaching with hands-on practice exercises.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- end .cs-aside -->

    </div><!-- end .cs-layout -->
    </form>

</main>

<script>
/* ── Character counter ────────────────────────── */
function updateChar(el, max, id) {
    var n   = el.value.length,
        out = document.getElementById(id);
    if (!out) return;
    out.textContent = n + ' / ' + max;
    out.className   = 'cs-char' + (n > max * .9 ? ' warn' : '') + (n >= max ? ' over' : '');
}

/* ── Clear field error on input ───────────────── */
function clearError(el) {
    el.classList.remove('has-error');
    var msg = el.parentNode.querySelector('.cs-error-msg');
    if (msg) msg.style.display = 'none';
}

/* ── Type label map ───────────────────────────── */
var typeLabels = { group: 'Group', one_on_one: 'One-on-One', workshop: 'Workshop' };

/* ── Update summary sidebar ───────────────────── */
function updateSummary() {
    /* Type */
    var typeEl  = document.querySelector('input[name="session_type"]:checked');
    var type    = typeEl ? typeEl.value : 'group';
    var sumType = document.getElementById('sumType');
    if (sumType) sumType.textContent = typeLabels[type] || type;

    /* Duration */
    var dur    = document.getElementById('duration_minutes');
    var sumDur = document.getElementById('sumDuration');
    if (dur && sumDur) {
        var m = parseInt(dur.value);
        sumDur.textContent = m >= 60 ? Math.floor(m/60) + 'h' + (m%60 ? ' ' + (m%60) + 'm' : '') : m + ' min';
    }

    /* Max students */
    var ms     = document.getElementById('max_students');
    var sumStu = document.getElementById('sumStudents');
    if (ms && sumStu) sumStu.textContent = ms.value || '—';

    /* Date */
    var sd      = document.getElementById('session_date');
    var sumDate = document.getElementById('sumDate');
    if (sd && sumDate) {
        if (sd.value) {
            var d = new Date(sd.value);
            sumDate.textContent = d.toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' }) +
                                  ' ' + d.toLocaleTimeString('en-US', { hour:'numeric', minute:'2-digit' });
            sumDate.style.color = 'var(--tx)';
        } else {
            sumDate.textContent = 'Not set';
            sumDate.style.color = 'var(--tx3)';
        }
    }

    /* Auto-set max students for one-on-one */
    if (type === 'one_on_one' && ms) {
        ms.value = 1;
        if (sumStu) sumStu.textContent = '1';
    }
}

/* ── Client-side validation ───────────────────── */
document.getElementById('sessionForm').addEventListener('submit', function(e) {
    var valid = true;

    /* Title */
    var title = document.getElementById('title');
    if (title && !title.value.trim()) {
        title.classList.add('has-error');
        title.focus();
        title.scrollIntoView({ behavior: 'smooth', block: 'center' });
        showFieldError(title, 'Session title is required.');
        valid = false;
    }

    /* Date */
    var sd = document.getElementById('session_date');
    if (sd && !sd.value) {
        sd.classList.add('has-error');
        showFieldError(sd, 'Please select a date and time.');
        if (valid) { sd.focus(); sd.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
        valid = false;
    } else if (sd && sd.value) {
        var chosen = new Date(sd.value);
        if (chosen <= new Date()) {
            sd.classList.add('has-error');
            showFieldError(sd, 'Session date must be in the future.');
            if (valid) { sd.focus(); sd.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
            valid = false;
        }
    }

    /* Meeting URL */
    var url = document.getElementById('meeting_url');
    if (url && url.value.trim() && !isValidUrl(url.value.trim())) {
        url.classList.add('has-error');
        showFieldError(url, 'Please enter a valid URL.');
        if (valid) { url.focus(); url.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
        valid = false;
    }

    if (!valid) e.preventDefault();
});

function showFieldError(el, msg) {
    /* Remove existing error first */
    var existing = el.parentNode.querySelector('.cs-error-msg');
    if (existing) existing.remove();
    var div = document.createElement('div');
    div.className = 'cs-error-msg';
    div.innerHTML = '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>' + escHtml(msg);
    el.parentNode.appendChild(div);
}

function escHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

function isValidUrl(s) {
    try { var u = new URL(s); return u.protocol === 'http:' || u.protocol === 'https:'; }
    catch(_) { return false; }
}

/* ── Init ─────────────────────────────────────── */
updateSummary();
</script>

</body>
</html>
