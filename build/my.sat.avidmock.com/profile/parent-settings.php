<?php
/**
 * profile/parent-settings.php — Student-facing settings to configure parent reports
 *
 * - Add parent email(s) (up to 2)
 * - Choose frequency: Weekly (default), Bi-weekly, Monthly
 * - Toggle what to include: scores, streaks, AI usage, achievements
 * - Preview button to see what the email looks like
 * - CSRF protection, form validation
 * - Uses the student panel design system
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/ParentReport.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Mailer.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/StudyStreak.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/CategoryPerformance.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/ScorePredictor.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Achievement.php';

Auth::requireStudent();
$userId = (int) $_SESSION['user_id'];
$user   = User::findById($userId);

$firstName = trim($user['first_name'] ?? 'Student');

$success = $_SESSION['parent_settings_success'] ?? null;
$error   = $_SESSION['parent_settings_error']   ?? null;
unset($_SESSION['parent_settings_success'], $_SESSION['parent_settings_error']);

/* ── Handle POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $_SESSION['parent_settings_error'] = 'Invalid form submission. Please try again.';
        header('Location: /profile/parent-settings.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $email1 = trim($_POST['parent_email_1'] ?? '');
        $email2 = trim($_POST['parent_email_2'] ?? '');

        // Validate
        if ($email1 !== '' && !filter_var($email1, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['parent_settings_error'] = 'Parent email 1 is not a valid email address.';
            header('Location: /profile/parent-settings.php');
            exit;
        }
        if ($email2 !== '' && !filter_var($email2, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['parent_settings_error'] = 'Parent email 2 is not a valid email address.';
            header('Location: /profile/parent-settings.php');
            exit;
        }

        // Block student's own email
        $studentEmail = strtolower(trim($user['email'] ?? ''));
        if (strtolower($email1) === $studentEmail || strtolower($email2) === $studentEmail) {
            $_SESSION['parent_settings_error'] = 'Parent email cannot be the same as your own email.';
            header('Location: /profile/parent-settings.php');
            exit;
        }

        $frequency = $_POST['frequency'] ?? 'weekly';
        if (!in_array($frequency, ['weekly', 'biweekly', 'monthly'], true)) {
            $frequency = 'weekly';
        }

        try {
            ParentReport::saveSettings($userId, [
                'parent_email_1'      => $email1 ?: null,
                'parent_email_2'      => $email2 ?: null,
                'frequency'           => $frequency,
                'include_scores'      => isset($_POST['include_scores']),
                'include_streaks'     => isset($_POST['include_streaks']),
                'include_ai_usage'    => isset($_POST['include_ai_usage']),
                'include_achievements' => isset($_POST['include_achievements']),
            ]);
            $_SESSION['parent_settings_success'] = 'Parent report settings saved successfully!';
        } catch (\Throwable $e) {
            error_log('[parent-settings.php] save: ' . $e->getMessage());
            $_SESSION['parent_settings_error'] = 'Could not save settings. Please try again.';
        }
        header('Location: /profile/parent-settings.php');
        exit;
    }

    if ($action === 'send_now') {
        try {
            $result = ParentReport::sendReport($userId);
            if ($result['success']) {
                $_SESSION['parent_settings_success'] = 'Report sent! Your parent(s) will receive it shortly.';
            } else {
                $_SESSION['parent_settings_error'] = $result['error'] ?? 'Could not send report.';
            }
        } catch (\Throwable $e) {
            error_log('[parent-settings.php] send: ' . $e->getMessage());
            $_SESSION['parent_settings_error'] = 'Something went wrong. Please try again.';
        }
        header('Location: /profile/parent-settings.php');
        exit;
    }
}

/* ── Load current settings ── */
$settings = null;
try {
    $settings = ParentReport::getSettings($userId);
} catch (\Throwable $e) {
    error_log('[parent-settings.php] load: ' . $e->getMessage());
}

$s = [
    'email1'     => $settings['parent_email_1']      ?? '',
    'email2'     => $settings['parent_email_2']      ?? '',
    'freq'       => $settings['frequency']            ?? 'weekly',
    'scores'     => (bool) ($settings['include_scores']       ?? 1),
    'streaks'    => (bool) ($settings['include_streaks']      ?? 1),
    'ai_usage'   => (bool) ($settings['include_ai_usage']     ?? 1),
    'achievements' => (bool) ($settings['include_achievements'] ?? 1),
    'last_sent'  => $settings['last_sent_at'] ?? null,
];

$activePage  = 'profile';
$topbarTitle = 'Parent Reports';
$topbarSub   = 'Profile &rsaquo; Parent Reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Parent Reports — Avidmock SAT</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root {
    --dk:  #143230;
    --dk2: #1a3f3c;
    --dk3: #0e2624;
    --ac:  #1fe290;
    --ac2: #17c87a;
    --tx:  #1a1a2e;
    --tx2: #4a4a5a;
    --tx3: #8a8a9a;
    --bg:  #f4f8f7;
    --bg2: #ffffff;
    --bd:  #e2ebe9;
    --bd2: #d0dbd8;
    --err: #ef4444;
    --warn: #f59e0b;
    --ff: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    --sidebar-w: 260px;
    --topbar-h:  64px;
    --radius:    14px;
    --shadow-sm: 0 1px 4px rgba(20,50,48,.06), 0 4px 16px rgba(20,50,48,.04);
    --pad:    36px;
    --pad-sm: 18px;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: var(--ff);
    background: var(--bg);
    color: var(--tx);
    -webkit-font-smoothing: antialiased;
    overflow-x: hidden;
    min-height: 100vh;
}
a { text-decoration: none; color: inherit; }
button { font-family: var(--ff); cursor: pointer; border: none; background: none; }

/* ── Layout ── */
.main-content {
    margin-left: var(--sidebar-w);
    margin-top: var(--topbar-h);
    padding: 36px var(--pad) 96px;
    max-width: calc(680px + var(--sidebar-w));
}

.page-title {
    font-size: 22px; font-weight: 800; color: var(--dk); margin-bottom: 4px;
}
.page-sub {
    font-size: 14px; color: var(--tx3); margin-bottom: 28px;
}

/* ── Cards ── */
.card {
    background: var(--bg2);
    border-radius: var(--radius);
    padding: 28px;
    margin-bottom: 20px;
    box-shadow: var(--shadow-sm);
}
.card-title {
    font-size: 16px; font-weight: 700; color: var(--dk); margin-bottom: 4px;
}
.card-desc {
    font-size: 13px; color: var(--tx3); margin-bottom: 20px;
}

/* ── Form elements ── */
.form-group { margin-bottom: 18px; }
.form-label {
    display: block; font-size: 13px; font-weight: 600; color: var(--tx2); margin-bottom: 6px;
}
.form-input {
    width: 100%; padding: 11px 14px; font-size: 14px; font-family: var(--ff);
    border: 1.5px solid var(--bd); border-radius: 10px; background: var(--bg);
    color: var(--tx); transition: border-color .2s;
}
.form-input:focus { outline: none; border-color: var(--ac); }
.form-input::placeholder { color: var(--tx3); }
.form-hint { font-size: 11px; color: var(--tx3); margin-top: 4px; }

/* ── Select ── */
.form-select {
    width: 100%; padding: 11px 14px; font-size: 14px; font-family: var(--ff);
    border: 1.5px solid var(--bd); border-radius: 10px; background: var(--bg);
    color: var(--tx); appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg width='12' height='8' viewBox='0 0 12 8' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1.5L6 6.5L11 1.5' stroke='%238a8a9a' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
    cursor: pointer;
}
.form-select:focus { outline: none; border-color: var(--ac); }

/* ── Toggles ── */
.toggle-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 0; border-bottom: 1px solid var(--bd);
}
.toggle-row:last-child { border-bottom: none; }
.toggle-label { font-size: 14px; color: var(--tx); }
.toggle-desc  { font-size: 12px; color: var(--tx3); margin-top: 2px; }

/* Custom toggle switch */
.switch { position: relative; display: inline-block; width: 44px; height: 24px; }
.switch input { opacity: 0; width: 0; height: 0; }
.switch .slider {
    position: absolute; inset: 0; background: var(--bd);
    border-radius: 24px; transition: .2s; cursor: pointer;
}
.switch .slider::before {
    content: ''; position: absolute; left: 3px; bottom: 3px;
    width: 18px; height: 18px; border-radius: 50%; background: white;
    transition: .2s; box-shadow: 0 1px 3px rgba(0,0,0,.12);
}
.switch input:checked + .slider { background: var(--ac); }
.switch input:checked + .slider::before { transform: translateX(20px); }

/* ── Buttons ── */
.btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 12px 24px; font-size: 14px; font-weight: 700;
    border-radius: 10px; cursor: pointer; transition: all .2s;
}
.btn-primary { background: var(--ac); color: var(--dk); }
.btn-primary:hover { background: var(--ac2); }
.btn-outline {
    background: transparent; color: var(--dk);
    border: 1.5px solid var(--bd);
}
.btn-outline:hover { border-color: var(--ac); color: var(--ac); }
.btn-sm { padding: 8px 16px; font-size: 13px; }

.btn-row { display: flex; gap: 12px; margin-top: 24px; flex-wrap: wrap; }

/* ── Alerts ── */
.alert {
    padding: 14px 18px; border-radius: 10px; font-size: 13px; font-weight: 500;
    margin-bottom: 20px;
}
.alert-success { background: #e8f9ef; color: #0a7c42; border: 1px solid #b5eaca; }
.alert-error   { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

/* ── Preview modal ── */
.modal-overlay {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5);
    z-index: 9999; align-items: center; justify-content: center;
}
.modal-overlay.open { display: flex; }
.modal-box {
    background: var(--bg2); border-radius: 16px; width: 96%; max-width: 620px;
    max-height: 85vh; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,.2);
    display: flex; flex-direction: column;
}
.modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 24px; border-bottom: 1px solid var(--bd);
}
.modal-header h3 { font-size: 16px; font-weight: 700; color: var(--dk); }
.modal-close {
    width: 32px; height: 32px; border-radius: 8px; display: flex;
    align-items: center; justify-content: center; font-size: 18px;
    color: var(--tx3); cursor: pointer;
}
.modal-close:hover { background: var(--bg); }
.modal-body { flex: 1; overflow-y: auto; }
.modal-body iframe {
    width: 100%; min-height: 600px; border: none;
}

/* ── Responsive ── */
@media (max-width: 768px) {
    .main-content { margin-left: 0; padding: 16px 16px 80px; margin-top: var(--topbar-h); }
    .card { padding: 20px 16px; }
    .btn-row { flex-direction: column; }
    .btn { width: 100%; justify-content: center; }
}
</style>
</head>
<body>

<div class="main-content">

    <h1 class="page-title">Parent Progress Reports</h1>
    <p class="page-sub">Keep your parents in the loop with automated weekly progress updates.</p>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/profile/parent-settings.php" id="settingsForm">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="save_settings">

        <!-- Parent Emails -->
        <div class="card">
            <div class="card-title">Parent / Guardian Emails</div>
            <div class="card-desc">Add up to 2 email addresses to receive progress reports.</div>

            <div class="form-group">
                <label class="form-label" for="parent_email_1">Parent Email 1</label>
                <input type="email"
                       id="parent_email_1"
                       name="parent_email_1"
                       class="form-input"
                       placeholder="parent@example.com"
                       value="<?= e($s['email1']) ?>"
                       maxlength="255">
            </div>

            <div class="form-group">
                <label class="form-label" for="parent_email_2">Parent Email 2 <span style="color: var(--tx3); font-weight: 400;">(optional)</span></label>
                <input type="email"
                       id="parent_email_2"
                       name="parent_email_2"
                       class="form-input"
                       placeholder="guardian@example.com"
                       value="<?= e($s['email2']) ?>"
                       maxlength="255">
            </div>
        </div>

        <!-- Frequency -->
        <div class="card">
            <div class="card-title">Report Frequency</div>
            <div class="card-desc">How often should we send reports?</div>

            <div class="form-group">
                <select name="frequency" class="form-select" id="frequency">
                    <option value="weekly"   <?= $s['freq'] === 'weekly'   ? 'selected' : '' ?>>Weekly (Every Monday)</option>
                    <option value="biweekly" <?= $s['freq'] === 'biweekly' ? 'selected' : '' ?>>Bi-weekly (Every 2 weeks)</option>
                    <option value="monthly"  <?= $s['freq'] === 'monthly'  ? 'selected' : '' ?>>Monthly</option>
                </select>
            </div>

            <?php if ($s['last_sent']): ?>
            <div class="form-hint">
                Last report sent: <?= date('M j, Y \a\t g:i A', strtotime($s['last_sent'])) ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Include Toggles -->
        <div class="card">
            <div class="card-title">Report Contents</div>
            <div class="card-desc">Choose what your parents see in each report.</div>

            <div class="toggle-row">
                <div>
                    <div class="toggle-label">Quiz Scores & Trends</div>
                    <div class="toggle-desc">Average scores, improvement, skill breakdown</div>
                </div>
                <label class="switch">
                    <input type="checkbox" name="include_scores" <?= $s['scores'] ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="toggle-row">
                <div>
                    <div class="toggle-label">Study Streaks & Activity</div>
                    <div class="toggle-desc">Current streak, study time, daily activity</div>
                </div>
                <label class="switch">
                    <input type="checkbox" name="include_streaks" <?= $s['streaks'] ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="toggle-row">
                <div>
                    <div class="toggle-label">AI Tutor Usage</div>
                    <div class="toggle-desc">Session count, topics discussed</div>
                </div>
                <label class="switch">
                    <input type="checkbox" name="include_ai_usage" <?= $s['ai_usage'] ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="toggle-row">
                <div>
                    <div class="toggle-label">Achievements</div>
                    <div class="toggle-desc">Badges earned this week</div>
                </div>
                <label class="switch">
                    <input type="checkbox" name="include_achievements" <?= $s['achievements'] ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>
        </div>

        <!-- Actions -->
        <div class="btn-row">
            <button type="submit" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                Save Settings
            </button>
            <button type="button" class="btn btn-outline" id="previewBtn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                Preview Report
            </button>
            <button type="button" class="btn btn-outline" id="sendNowBtn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                Send Now
            </button>
        </div>
    </form>
</div>

<!-- Preview Modal -->
<div class="modal-overlay" id="previewModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Email Preview</h3>
            <div class="modal-close" id="closeModal">&times;</div>
        </div>
        <div class="modal-body">
            <iframe id="previewFrame" title="Report Preview"></iframe>
        </div>
    </div>
</div>

<!-- Send Now Form (hidden) -->
<form method="POST" action="/profile/parent-settings.php" id="sendNowForm" style="display:none;">
    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="send_now">
</form>

<script>
(function() {
    // Preview button
    const previewBtn   = document.getElementById('previewBtn');
    const previewModal = document.getElementById('previewModal');
    const closeModal   = document.getElementById('closeModal');
    const previewFrame = document.getElementById('previewFrame');

    previewBtn.addEventListener('click', function() {
        previewFrame.src = '/api/parent-report.php?action=preview';
        previewModal.classList.add('open');
    });

    closeModal.addEventListener('click', function() {
        previewModal.classList.remove('open');
        previewFrame.src = '';
    });

    previewModal.addEventListener('click', function(e) {
        if (e.target === previewModal) {
            previewModal.classList.remove('open');
            previewFrame.src = '';
        }
    });

    // Send Now
    const sendNowBtn  = document.getElementById('sendNowBtn');
    const sendNowForm = document.getElementById('sendNowForm');

    sendNowBtn.addEventListener('click', function() {
        const email1 = document.getElementById('parent_email_1').value.trim();
        if (!email1) {
            alert('Please add at least one parent email and save settings first.');
            return;
        }
        if (confirm('Send the progress report to your parent(s) now?')) {
            sendNowForm.submit();
        }
    });

    // Client-side email validation
    document.getElementById('settingsForm').addEventListener('submit', function(e) {
        const email1 = document.getElementById('parent_email_1').value.trim();
        const email2 = document.getElementById('parent_email_2').value.trim();
        const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (email1 && !emailRe.test(email1)) {
            e.preventDefault();
            alert('Please enter a valid email for Parent Email 1.');
            return;
        }
        if (email2 && !emailRe.test(email2)) {
            e.preventDefault();
            alert('Please enter a valid email for Parent Email 2.');
            return;
        }
    });
})();
</script>

</body>
</html>
