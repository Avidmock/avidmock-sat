<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/auth-guard.php';

$pageTitle = 'Create Session';
$activePage = 'sessions';

$errors = [];
$old = [
    'title'            => '',
    'subject'          => '',
    'description'      => '',
    'starts_at_date'   => '',
    'starts_at_time'   => '',
    'duration_minutes' => '60',
    'max_capacity'     => '20',
    'zoom_link'        => '',
    'tutor_name'       => '',
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    }

    // Sanitize inputs
    $old['title']            = trim($_POST['title'] ?? '');
    $old['subject']          = trim($_POST['subject'] ?? '');
    $old['description']      = trim($_POST['description'] ?? '');
    $old['starts_at_date']   = trim($_POST['starts_at_date'] ?? '');
    $old['starts_at_time']   = trim($_POST['starts_at_time'] ?? '');
    $old['duration_minutes'] = trim($_POST['duration_minutes'] ?? '60');
    $old['max_capacity']     = trim($_POST['max_capacity'] ?? '20');
    $old['zoom_link']        = trim($_POST['zoom_link'] ?? '');
    $old['tutor_name']       = trim($_POST['tutor_name'] ?? '');

    // Validation
    if ($old['title'] === '') {
        $errors[] = 'Title is required.';
    } elseif (mb_strlen($old['title']) > 200) {
        $errors[] = 'Title must be 200 characters or fewer.';
    }

    $validSubjects = ['math', 'reading_writing', 'general'];
    if (!in_array($old['subject'], $validSubjects, true)) {
        $errors[] = 'Please select a valid subject.';
    }

    if ($old['starts_at_date'] === '' || $old['starts_at_time'] === '') {
        $errors[] = 'Start date and time are required.';
    } else {
        $startsAt = $old['starts_at_date'] . ' ' . $old['starts_at_time'] . ':00';
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $startsAt);
        if (!$dt) {
            $errors[] = 'Invalid date/time format.';
        }
    }

    $validDurations = [30, 45, 60, 90];
    if (!in_array((int)$old['duration_minutes'], $validDurations, true)) {
        $errors[] = 'Please select a valid duration.';
    }

    $maxCap = (int)$old['max_capacity'];
    if ($maxCap < 1 || $maxCap > 500) {
        $errors[] = 'Capacity must be between 1 and 500.';
    }

    if ($old['zoom_link'] !== '' && !filter_var($old['zoom_link'], FILTER_VALIDATE_URL)) {
        $errors[] = 'Zoom link must be a valid URL.';
    }

    if ($old['tutor_name'] === '') {
        $errors[] = 'Tutor name is required.';
    }

    // Insert if no errors
    if (empty($errors)) {
        Database::insert('tutoring_sessions', [
            'title'            => $old['title'],
            'subject'          => $old['subject'],
            'description'      => $old['description'],
            'starts_at'        => $startsAt,
            'duration_minutes' => (int)$old['duration_minutes'],
            'max_capacity'     => $maxCap,
            'zoom_link'        => $old['zoom_link'] ?: null,
            'tutor_name'       => $old['tutor_name'],
            'status'           => 'upcoming',
            'created_by'       => $_SESSION['admin_id'],
            'created_at'       => date('Y-m-d H:i:s'),
        ]);

        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Session created successfully.'];
        header('Location: /sessions/');
        exit;
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/head.php';
?>
<div class="admin-layout">
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/sidebar.php'; ?>
<main class="admin-main">
    <div class="topbar">
        <h1>Create Session</h1>
        <a href="/sessions/" class="btn btn--ghost">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Back to Sessions
        </a>
    </div>

    <div class="content">
        <?php if (!empty($errors)): ?>
            <div class="alert alert--danger" role="alert">
                <strong>Please fix the following errors:</strong>
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="/sessions/create.php" class="form-card" data-watch-changes>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div class="form-grid form-grid--2col">
                <!-- Title -->
                <div class="form-group form-group--span2">
                    <label for="title" class="form-label">Session Title <span class="required">*</span></label>
                    <input type="text" name="title" id="title" class="input"
                           value="<?= htmlspecialchars($old['title']) ?>"
                           placeholder="e.g., SAT Math: Algebra Fundamentals"
                           maxlength="200" required>
                </div>

                <!-- Subject -->
                <div class="form-group">
                    <label for="subject" class="form-label">Subject <span class="required">*</span></label>
                    <select name="subject" id="subject" class="input" required>
                        <option value="" disabled <?= $old['subject'] === '' ? 'selected' : '' ?>>Select subject</option>
                        <option value="math" <?= $old['subject'] === 'math' ? 'selected' : '' ?>>Math</option>
                        <option value="reading_writing" <?= $old['subject'] === 'reading_writing' ? 'selected' : '' ?>>Reading & Writing</option>
                        <option value="general" <?= $old['subject'] === 'general' ? 'selected' : '' ?>>General</option>
                    </select>
                </div>

                <!-- Tutor Name -->
                <div class="form-group">
                    <label for="tutor_name" class="form-label">Tutor Name <span class="required">*</span></label>
                    <input type="text" name="tutor_name" id="tutor_name" class="input"
                           value="<?= htmlspecialchars($old['tutor_name']) ?>"
                           placeholder="Full name" required>
                </div>

                <!-- Date -->
                <div class="form-group">
                    <label for="starts_at_date" class="form-label">Date <span class="required">*</span></label>
                    <input type="date" name="starts_at_date" id="starts_at_date" class="input"
                           value="<?= htmlspecialchars($old['starts_at_date']) ?>"
                           min="<?= date('Y-m-d') ?>" required>
                </div>

                <!-- Time -->
                <div class="form-group">
                    <label for="starts_at_time" class="form-label">Time <span class="required">*</span></label>
                    <input type="time" name="starts_at_time" id="starts_at_time" class="input"
                           value="<?= htmlspecialchars($old['starts_at_time']) ?>" required>
                </div>

                <!-- Duration -->
                <div class="form-group">
                    <label for="duration_minutes" class="form-label">Duration <span class="required">*</span></label>
                    <select name="duration_minutes" id="duration_minutes" class="input" required>
                        <option value="30"  <?= $old['duration_minutes'] === '30'  ? 'selected' : '' ?>>30 minutes</option>
                        <option value="45"  <?= $old['duration_minutes'] === '45'  ? 'selected' : '' ?>>45 minutes</option>
                        <option value="60"  <?= $old['duration_minutes'] === '60'  ? 'selected' : '' ?>>60 minutes</option>
                        <option value="90"  <?= $old['duration_minutes'] === '90'  ? 'selected' : '' ?>>90 minutes</option>
                    </select>
                </div>

                <!-- Max Capacity -->
                <div class="form-group">
                    <label for="max_capacity" class="form-label">Max Capacity <span class="required">*</span></label>
                    <input type="number" name="max_capacity" id="max_capacity" class="input"
                           value="<?= htmlspecialchars($old['max_capacity']) ?>"
                           min="1" max="500" required>
                </div>

                <!-- Zoom Link -->
                <div class="form-group form-group--span2">
                    <label for="zoom_link" class="form-label">Zoom Link</label>
                    <input type="url" name="zoom_link" id="zoom_link" class="input"
                           value="<?= htmlspecialchars($old['zoom_link']) ?>"
                           placeholder="https://zoom.us/j/...">
                    <small class="form-hint">Leave blank if meeting link will be added later.</small>
                </div>

                <!-- Description -->
                <div class="form-group form-group--span2">
                    <label for="description" class="form-label">Description</label>
                    <textarea name="description" id="description" class="input input--textarea"
                              rows="5" placeholder="Session overview, topics to be covered, prerequisites..."
                    ><?= htmlspecialchars($old['description']) ?></textarea>
                </div>
            </div>

            <!-- Submit -->
            <div class="form-actions">
                <a href="/sessions/" class="btn btn--ghost">Cancel</a>
                <button type="submit" class="btn btn--primary">Create Session</button>
            </div>
        </form>
    </div>
</main>
</div>

<script src="/assets/js/admin-core.js"></script>
</body>
</html>
