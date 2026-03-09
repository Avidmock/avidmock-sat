<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK SAT PLATFORM · Core Configuration
 *  Version 4.5 — DB credentials restored, site back online
 * ═══════════════════════════════════════════════════════════════════
 */

// ─── ENVIRONMENT DETECTION ─────────────────────────────────────────
define('AVIDMOCK_ENV',   getenv('AVIDMOCK_ENV') ?: 'production');
define('AVIDMOCK_DEBUG', AVIDMOCK_ENV === 'development');

// ─── BASE URLS ─────────────────────────────────────────────────────
define('STUDENT_URL', rtrim(getenv('STUDENT_URL') ?: 'https://my.sat.avidmock.com', '/'));
define('ADMIN_URL',   rtrim(getenv('ADMIN_URL')   ?: 'https://admin.sat.avidmock.com', '/'));
define('PUBLIC_URL',  rtrim(getenv('PUBLIC_URL')  ?: 'https://sat.avidmock.com', '/'));
define('ASSETS_URL',  STUDENT_URL . '/assets');

// ─── DATABASE ──────────────────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_PORT',    '3306');
define('DB_NAME',    'dbw37mqkzieajt');
define('DB_USER',    'uofkcmd0rmvn2');
define('DB_PASS',    '4(%l1,521)l1');
define('DB_CHARSET', 'utf8mb4');

// ─── ANTHROPIC AI ──────────────────────────────────────────────────
define('ANTHROPIC_API_KEY',      getenv('ANTHROPIC_API_KEY')  ?: '');
define('ANTHROPIC_MODEL',        'claude-sonnet-4-5-20250929');
define('AI_TUTOR_MODEL',         'claude-sonnet-4-5-20250929');
define('AI_MAX_TOKENS',          2048);
define('AI_TEMPERATURE',         0.7);
define('AI_TUTOR_FREE_LIMIT',    5);
define('AI_TUTOR_PRO_LIMIT',     999);
define('AI_QUESTION_GEN_LIMIT',  50);

// ─── GOOGLE OAUTH ──────────────────────────────────────────────────
define('GOOGLE_CLIENT_ID',     getenv('GOOGLE_CLIENT_ID')     ?: '');
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '');
define('GOOGLE_REDIRECT_URI',  STUDENT_URL . '/auth/google-callback.php');

// ─── EMAIL (SMTP) ──────────────────────────────────────────────────
define('SMTP_HOST',      getenv('SMTP_HOST') ?: 'smtp.mailgun.org');
define('SMTP_PORT',      getenv('SMTP_PORT') ?: 587);
define('SMTP_USER',      getenv('SMTP_USER') ?: '');
define('SMTP_PASS',      getenv('SMTP_PASS') ?: '');
define('SMTP_FROM',      getenv('SMTP_FROM') ?: 'hello@avidmock.com');
define('SMTP_FROM_NAME', 'Avidmock SAT');

// ─── STRIPE ────────────────────────────────────────────────────────
define('STRIPE_PUBLIC_KEY',     getenv('STRIPE_PUBLIC_KEY')     ?: '');
define('STRIPE_SECRET_KEY',     getenv('STRIPE_SECRET_KEY')     ?: '');
define('STRIPE_WEBHOOK_SECRET', getenv('STRIPE_WEBHOOK_SECRET') ?: '');
define('PLAN_PRO_MONTHLY',    1499);
define('PLAN_PRO_YEARLY',     11988);
define('PLAN_FAMILY_MONTHLY', 2499);
define('PLAN_FAMILY_YEARLY',  19988);

// ─── WEB PUSH ──────────────────────────────────────────────────────
define('VAPID_PUBLIC_KEY',  getenv('VAPID_PUBLIC_KEY')  ?: '');
define('VAPID_PRIVATE_KEY', getenv('VAPID_PRIVATE_KEY') ?: '');
define('VAPID_SUBJECT',     'mailto:push@avidmock.com');

// ─── FILE STORAGE ──────────────────────────────────────────────────
define('UPLOAD_DIR',     __DIR__ . '/../uploads');
define('VIDEO_DIR',      UPLOAD_DIR . '/videos');
define('IMAGE_DIR',      UPLOAD_DIR . '/images');
define('EBOOK_DIR',      UPLOAD_DIR . '/ebooks');
define('MAX_VIDEO_SIZE', 500 * 1024 * 1024);
define('MAX_IMAGE_SIZE', 5   * 1024 * 1024);

// ─── SESSION ───────────────────────────────────────────────────────
define('SESSION_LIFETIME', 86400 * 30);
define('SESSION_NAME',     'avidmock_session');
define('CSRF_TOKEN_NAME',  'avidmock_csrf');

// ─── DESIGN TOKENS ─────────────────────────────────────────────────
define('COLOR_PRIMARY', '#1FE290');
define('COLOR_DARK',    '#143230');
define('COLOR_AMBER',   '#FFB347');
define('COLOR_CORAL',   '#FF6B6B');
define('COLOR_BG',      '#F0FAF4');

// ─── GAMIFICATION ──────────────────────────────────────────────────
define('XP_QUIZ_COMPLETE',    50);
define('XP_PERFECT_SCORE',    100);
define('XP_STREAK_DAY',       25);
define('XP_AI_TUTOR_SESSION', 15);
define('XP_MICRO_LESSON',     20);
define('XP_PRACTICE_TEST',    200);
define('XP_WRITING_SUBMIT',   30);
define('LEAGUE_BRONZE_MIN',   0);
define('LEAGUE_SILVER_MIN',   300);
define('LEAGUE_GOLD_MIN',     750);
define('LEAGUE_DIAMOND_MIN',  1500);

// ─── SPACED REPETITION ─────────────────────────────────────────────
define('SR_INITIAL_INTERVAL',  1);
define('SR_INITIAL_EASE',      2.5);
define('SR_MIN_EASE',          1.3);
define('SR_EASY_BONUS',        1.3);
define('SR_INTERVAL_MODIFIER', 1.0);

// ─── SUBSCRIPTION TIERS ────────────────────────────────────────────
define('TIER_FREE',   'free');
define('TIER_PRO',    'pro');
define('TIER_FAMILY', 'family');

$FEATURE_LIMITS = [
    TIER_FREE => [
        'daily_questions'     => 30,
        'ai_tutor_messages'   => 5,
        'practice_tests'      => 1,
        'writing_submissions' => 0,
        'adaptive_engine'     => 'basic',
        'spaced_repetition'   => false,
        'score_prediction'    => 'basic',
        'offline_mode'        => false,
        'parent_dashboard'    => false,
    ],
    TIER_PRO => [
        'daily_questions'     => 999,
        'ai_tutor_messages'   => 999,
        'practice_tests'      => 999,
        'writing_submissions' => 999,
        'adaptive_engine'     => 'full',
        'spaced_repetition'   => true,
        'score_prediction'    => 'detailed',
        'offline_mode'        => true,
        'parent_dashboard'    => false,
    ],
    TIER_FAMILY => [
        'daily_questions'     => 999,
        'ai_tutor_messages'   => 999,
        'practice_tests'      => 999,
        'writing_submissions' => 999,
        'adaptive_engine'     => 'full',
        'spaced_repetition'   => true,
        'score_prediction'    => 'detailed',
        'offline_mode'        => true,
        'parent_dashboard'    => true,
        'max_accounts'        => 3,
    ],
];

// ─── SAT CONSTANTS ─────────────────────────────────────────────────
define('SAT_MIN_SCORE',      400);
define('SAT_MAX_SCORE',      1600);
define('SAT_SECTION_MIN',    200);
define('SAT_SECTION_MAX',    800);
define('SAT_MATH_QUESTIONS', 44);
define('SAT_RW_QUESTIONS',   54);
define('SAT_MATH_TIME',      70);
define('SAT_RW_TIME',        64);

// ─── AUTOLOADER ────────────────────────────────────────────────────
spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/../lib/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// ─── ERROR HANDLING ────────────────────────────────────────────────
if (AVIDMOCK_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    ini_set('error_log', $logDir . '/error.log');
}

// ─── TIMEZONE ──────────────────────────────────────────────────────
date_default_timezone_set('UTC');

// ─── SESSION BOOTSTRAP ─────────────────────────────────────────────
function avidmock_session_start(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $cookieParams = [
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'domain'   => '.avidmock.com',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'None',
    ];

    session_name(SESSION_NAME);
    session_set_cookie_params($cookieParams);
    session_start();

    // Migrate data from legacy PHPSESSID if present and our session is empty
    if (empty($_SESSION['user_id']) && !empty($_COOKIE['PHPSESSID'])) {
        session_write_close();

        session_name('PHPSESSID');
        session_id($_COOKIE['PHPSESSID']);
        session_set_cookie_params($cookieParams);
        session_start();
        $migratedData = $_SESSION;
        session_write_close();

        session_name(SESSION_NAME);
        session_id($_COOKIE[SESSION_NAME] ?? '');
        session_set_cookie_params($cookieParams);
        session_start();

        if (!empty($migratedData['user_id'])) {
            foreach ($migratedData as $k => $v) {
                $_SESSION[$k] = $v;
            }
        }
    }
}

avidmock_session_start();

// ─── DATABASE CONNECTION ───────────────────────────────────────────
try {
    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        ),
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    if (AVIDMOCK_DEBUG) {
        die('Database connection failed: ' . $e->getMessage());
    }
    http_response_code(503);
    error_log('Avidmock DB connection failed: ' . $e->getMessage());
    if (
        isset($_SERVER['HTTP_X_REQUESTED_WITH']) ||
        (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
    ) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Service temporarily unavailable']);
    } else {
        echo 'Service temporarily unavailable.';
    }
    exit;
}

// ─── GLOBAL HELPERS ────────────────────────────────────────────────

function csrf_token(): string
{
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function csrf_verify(): bool
{
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals(csrf_token(), $token);
}

function e(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function json_response(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function get_feature_limits(string $tier = TIER_FREE): array
{
    global $FEATURE_LIMITS;
    return $FEATURE_LIMITS[$tier] ?? $FEATURE_LIMITS[TIER_FREE];
}

function can_access(string $feature, string $tier = TIER_FREE): bool
{
    $limits = get_feature_limits($tier);
    $value  = $limits[$feature] ?? false;
    if (is_bool($value))   return $value;
    if (is_int($value))    return $value > 0;
    if (in_array($value, ['full', 'detailed'], true)) return true;
    return (bool) $value;
}

function time_ago(string $datetime): string
{
    $diff = (new DateTime())->diff(new DateTime($datetime));
    if ($diff->y > 0) return $diff->y . 'y ago';
    if ($diff->m > 0) return $diff->m . 'mo ago';
    if ($diff->d > 0) return $diff->d . 'd ago';
    if ($diff->h > 0) return $diff->h . 'h ago';
    if ($diff->i > 0) return $diff->i . 'm ago';
    return 'just now';
}

function current_tier(): string
{
    return $_SESSION['user_tier'] ?? TIER_FREE;
}

function current_user_id(): ?int
{
    $id = $_SESSION['user_id'] ?? null;
    return $id !== null ? (int) $id : null;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function is_admin(): bool
{
    return ($_SESSION['user_role'] ?? '') === 'admin';
}