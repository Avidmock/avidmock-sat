<?php
/**
 * settings/index.php
 * Platform settings — API keys, feature flags, site config.
 */
require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

$admin = currentAdmin();
$db    = Database::connect();

// ── Ensure settings table ─────────────────────────────────────────────────
try {
    $db->query("SELECT 1 FROM platform_settings LIMIT 1");
} catch (Throwable $e) {
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS platform_settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e2) {}
}

$success = '';
$error   = '';

// ── Load current settings ─────────────────────────────────────────────────
function getSetting(PDO $db, string $key, string $default = ''): string {
    try {
        $stmt = $db->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        return $stmt->fetchColumn() ?: $default;
    } catch (Throwable $e) { return $default; }
}

function setSetting(PDO $db, string $key, string $value): void {
    $db->prepare("INSERT INTO platform_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
       ->execute([$key, $value]);
}

// ── Handle POST ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        setSetting($db, 'site_name', trim($_POST['site_name'] ?? 'Avidmock SAT'));
        setSetting($db, 'support_email', trim($_POST['support_email'] ?? ''));
        setSetting($db, 'free_daily_questions', trim($_POST['free_daily_questions'] ?? '30'));
        setSetting($db, 'free_ai_messages', trim($_POST['free_ai_messages'] ?? '5'));
        setSetting($db, 'pro_price_monthly', trim($_POST['pro_price_monthly'] ?? '1999'));
        setSetting($db, 'family_price_monthly', trim($_POST['family_price_monthly'] ?? '2999'));
        setSetting($db, 'maintenance_mode', isset($_POST['maintenance_mode']) ? '1' : '0');
        setSetting($db, 'registration_open', isset($_POST['registration_open']) ? '1' : '0');
        setSetting($db, 'leaderboard_enabled', isset($_POST['leaderboard_enabled']) ? '1' : '0');
        setSetting($db, 'study_rooms_enabled', isset($_POST['study_rooms_enabled']) ? '1' : '0');
        setSetting($db, 'ai_tutor_enabled', isset($_POST['ai_tutor_enabled']) ? '1' : '0');
        $success = 'Settings saved!';
    } catch (Throwable $e) {
        $error = 'Error saving: ' . $e->getMessage();
    }
}

$settings = [
    'site_name'            => getSetting($db, 'site_name', 'Avidmock SAT'),
    'support_email'        => getSetting($db, 'support_email', ''),
    'free_daily_questions' => getSetting($db, 'free_daily_questions', '30'),
    'free_ai_messages'     => getSetting($db, 'free_ai_messages', '5'),
    'pro_price_monthly'    => getSetting($db, 'pro_price_monthly', '1999'),
    'family_price_monthly' => getSetting($db, 'family_price_monthly', '2999'),
    'maintenance_mode'     => getSetting($db, 'maintenance_mode', '0'),
    'registration_open'    => getSetting($db, 'registration_open', '1'),
    'leaderboard_enabled'  => getSetting($db, 'leaderboard_enabled', '1'),
    'study_rooms_enabled'  => getSetting($db, 'study_rooms_enabled', '1'),
    'ai_tutor_enabled'     => getSetting($db, 'ai_tutor_enabled', '1'),
];

try { $draftCount = (int) $db->query("SELECT COUNT(*) FROM sat_quizzes WHERE status='draft'")->fetchColumn(); } catch (Throwable $e) { $draftCount = 0; }

$pageTitle  = 'Settings — Avidmock Admin';
$activePage = 'settings';
$extraHead  = <<<'CSS'
<style>
.main { margin-left: var(--sb-w); margin-top: var(--top-h); padding: 32px 28px; min-height: calc(100vh - var(--top-h)); }
.ph { margin-bottom: 24px; }
.ph-eyebrow { display: flex; align-items: center; gap: 6px; font-size: .5rem; font-weight: 700; color: var(--tx3); text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 5px; }
.ph-dot { width: 4px; height: 4px; border-radius: 50%; background: var(--ac); }
.ph-title { font-family: var(--fh); font-size: 1.875rem; font-weight: 900; color: var(--tx); letter-spacing: -.035em; }

.settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; max-width: 900px; }
.setting-section { background: var(--sf); border: 1px solid var(--bd); border-radius: 14px; padding: 24px; }
.section-title { font-family: var(--fh); font-size: 1.05rem; font-weight: 900; color: var(--tx); margin-bottom: 16px; letter-spacing: -.02em; }

.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: .75rem; font-weight: 700; color: var(--tx2); margin-bottom: 6px; text-transform: uppercase; letter-spacing: .4px; }
.form-input, .form-select { width: 100%; padding: 10px 14px; background: var(--ink); border: 1.5px solid var(--bd); border-radius: 9px; font-family: var(--ff); font-size: .875rem; color: var(--tx); outline: none; }
.form-input:focus, .form-select:focus { border-color: var(--ac); box-shadow: 0 0 0 3px rgba(31,226,144,.1); }
.form-hint { font-size: .625rem; color: var(--tx3); margin-top: 3px; }

.toggle-row { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--bd); }
.toggle-row:last-child { border-bottom: none; }
.toggle-label { font-size: .8125rem; font-weight: 600; color: var(--tx); }
.toggle-sub { font-size: .6875rem; color: var(--tx3); }
.toggle { position: relative; width: 44px; height: 24px; flex-shrink: 0; }
.toggle input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; inset: 0; background: var(--sf3); border-radius: 12px; cursor: pointer; transition: .2s; }
.toggle-slider:before { content: ''; position: absolute; width: 18px; height: 18px; left: 3px; top: 3px; background: #fff; border-radius: 50%; transition: .2s; }
.toggle input:checked + .toggle-slider { background: var(--ac); }
.toggle input:checked + .toggle-slider:before { transform: translateX(20px); }

.alert { padding: 12px 16px; border-radius: 9px; font-size: .8125rem; font-weight: 600; margin-bottom: 16px; }
.alert-error { background: rgba(239,68,68,.1); color: var(--err); }
.alert-success { background: rgba(31,226,144,.1); color: var(--ac); }

.save-bar { position: sticky; bottom: 0; background: var(--ink); border-top: 1px solid var(--bd); padding: 16px 28px; margin: 24px -28px -32px; display: flex; justify-content: flex-end; gap: 10px; }

@media (max-width: 768px) { .main { margin-left: 0; padding: 16px; } .settings-grid { grid-template-columns: 1fr; } }
</style>
CSS;

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<header class="topbar">
    <button class="topbar-ham" onclick="openSidebar()" aria-label="Menu"><span></span><span></span><span></span></button>
    <div class="topbar-title">Settings</div>
    <div class="topbar-spacer"></div>
</header>

<main class="main">
    <div class="ph reveal d1">
        <div class="ph-eyebrow"><span class="ph-dot"></span>Platform</div>
        <h1 class="ph-title">Settings</h1>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

    <form method="POST">
        <div class="settings-grid reveal d2">
            <!-- General -->
            <div class="setting-section">
                <div class="section-title">General</div>
                <div class="form-group">
                    <label class="form-label">Site Name</label>
                    <input type="text" name="site_name" class="form-input" value="<?= e($settings['site_name']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Support Email</label>
                    <input type="email" name="support_email" class="form-input" value="<?= e($settings['support_email']) ?>" placeholder="support@avidmock.com">
                </div>
            </div>

            <!-- Pricing -->
            <div class="setting-section">
                <div class="section-title">Pricing & Limits</div>
                <div class="form-group">
                    <label class="form-label">Free Daily Questions</label>
                    <input type="number" name="free_daily_questions" class="form-input" value="<?= e($settings['free_daily_questions']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Free AI Messages/Day</label>
                    <input type="number" name="free_ai_messages" class="form-input" value="<?= e($settings['free_ai_messages']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Pro Price (cents/month)</label>
                    <input type="number" name="pro_price_monthly" class="form-input" value="<?= e($settings['pro_price_monthly']) ?>">
                    <div class="form-hint">$<?= number_format((int)$settings['pro_price_monthly'] / 100, 2) ?>/month</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Family Price (cents/month)</label>
                    <input type="number" name="family_price_monthly" class="form-input" value="<?= e($settings['family_price_monthly']) ?>">
                    <div class="form-hint">$<?= number_format((int)$settings['family_price_monthly'] / 100, 2) ?>/month</div>
                </div>
            </div>

            <!-- Feature Toggles -->
            <div class="setting-section" style="grid-column: 1 / -1">
                <div class="section-title">Feature Flags</div>
                <div class="toggle-row">
                    <div>
                        <div class="toggle-label">Registration Open</div>
                        <div class="toggle-sub">Allow new users to sign up</div>
                    </div>
                    <label class="toggle"><input type="checkbox" name="registration_open" <?= $settings['registration_open'] === '1' ? 'checked' : '' ?>><span class="toggle-slider"></span></label>
                </div>
                <div class="toggle-row">
                    <div>
                        <div class="toggle-label">AI Tutor</div>
                        <div class="toggle-sub">Enable Claude-powered AI tutoring</div>
                    </div>
                    <label class="toggle"><input type="checkbox" name="ai_tutor_enabled" <?= $settings['ai_tutor_enabled'] === '1' ? 'checked' : '' ?>><span class="toggle-slider"></span></label>
                </div>
                <div class="toggle-row">
                    <div>
                        <div class="toggle-label">Leaderboard</div>
                        <div class="toggle-sub">Show global and weekly leaderboards</div>
                    </div>
                    <label class="toggle"><input type="checkbox" name="leaderboard_enabled" <?= $settings['leaderboard_enabled'] === '1' ? 'checked' : '' ?>><span class="toggle-slider"></span></label>
                </div>
                <div class="toggle-row">
                    <div>
                        <div class="toggle-label">Study Rooms</div>
                        <div class="toggle-sub">Multiplayer quiz battles</div>
                    </div>
                    <label class="toggle"><input type="checkbox" name="study_rooms_enabled" <?= $settings['study_rooms_enabled'] === '1' ? 'checked' : '' ?>><span class="toggle-slider"></span></label>
                </div>
                <div class="toggle-row">
                    <div>
                        <div class="toggle-label" style="color:var(--err)">Maintenance Mode</div>
                        <div class="toggle-sub">Show maintenance page to all students</div>
                    </div>
                    <label class="toggle"><input type="checkbox" name="maintenance_mode" <?= $settings['maintenance_mode'] === '1' ? 'checked' : '' ?>><span class="toggle-slider"></span></label>
                </div>
            </div>
        </div>

        <div class="save-bar">
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </div>
    </form>
</main>
</body>
</html>
