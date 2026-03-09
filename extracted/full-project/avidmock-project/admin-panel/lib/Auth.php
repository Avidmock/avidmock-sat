<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · Auth
 *  Session management, login, logout, guards.
 * ═══════════════════════════════════════════════════════════════════
 */

class Auth
{
    /**
     * Attempt login with email + password.
     */
    public static function attempt(string $email, string $password): ?array
    {
        $user = Database::fetch(
            "SELECT id, first_name, last_name, email, password_hash, role,
                    email_verified, is_active, is_suspended
             FROM users WHERE email = ? AND is_suspended = 0 LIMIT 1",
            [strtolower(trim($email))]
        );

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        if (!$user['email_verified']) {
            return null;
        }

        self::createSession($user);
        return $user;
    }

    /**
     * Create session from a user record.
     */
    public static function createSession(array $user): void
    {
        session_regenerate_id(true);

        // Support both 'name' (old) and 'first_name'+'last_name' (new schema)
        $name = $user['name']
            ?? (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

        $_SESSION['user_id']             = (int) $user['id'];
        $_SESSION['user_name']           = trim($name);
        $_SESSION['user_email']          = $user['email'];
        $_SESSION['user_role']           = $user['role'];
        $_SESSION['user_tier']           = TIER_FREE;
        $_SESSION['onboarding_complete'] = (bool) ($user['onboarding_complete'] ?? false);
        $_SESSION['test_date']           = $user['test_date'] ?? null;
        $_SESSION['target_score']        = (int) ($user['target_score'] ?? 1200);
        $_SESSION['logged_in_at']        = time();

        // Update last login (ignore errors if column missing)
        try {
            Database::execute(
                "UPDATE users SET last_login_at = NOW() WHERE id = ?",
                [$user['id']]
            );
        } catch (Exception $e) {
            // Column may not exist yet — safe to ignore
        }
    }

    /**
     * Logout: destroy session entirely.
     */
    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => '/',
                'domain'   => '.avidmock.com',
                'secure'   => true,
                'httponly' => true,
                'samesite' => 'None',
            ]);
        }

        session_destroy();
    }

    /**
     * Get the current authenticated user from DB.
     * Only queries the users table — no JOINs to optional tables.
     */
    public static function user(): ?array
    {
        static $cached = null;

        if (!is_logged_in()) return null;

        if ($cached === null) {
            $cached = Database::fetch(
                "SELECT * FROM users WHERE id = ? LIMIT 1",
                [current_user_id()]
            );

            if ($cached) {
                // Normalize name field
                if (empty($cached['name']) && !empty($cached['first_name'])) {
                    $cached['name'] = trim($cached['first_name'] . ' ' . $cached['last_name']);
                }
                // Set defaults for optional columns
                $cached['subscription_plan'] = $_SESSION['user_tier'] ?? TIER_FREE;
                $cached['current_streak']    = 0;
                $cached['longest_streak']    = 0;
                $cached['total_xp']          = 0;
                $cached['current_level']     = 1;
                $cached['league']            = 'bronze';
            }
        }

        return $cached;
    }

    /**
     * Guard: require authenticated student.
     */
    public static function requireStudent(): array
    {
        if (!is_logged_in()) {
            redirect('https://sat.avidmock.com/pages/auth/login?redirect=' .
                urlencode('https://my.sat.avidmock.com' . $_SERVER['REQUEST_URI']));
        }

        $user = self::user();

        if (!$user || $user['role'] !== 'student') {
            redirect('https://sat.avidmock.com/pages/auth/login');
        }

        return $user;
    }

    /**
     * Guard: require admin.
     */
    public static function requireAdmin(): array
    {
        if (!is_logged_in() || !is_admin()) {
            redirect(ADMIN_URL . '/auth/login.php');
        }
        return self::user();
    }

    /**
     * Guard: require parent.
     */
    public static function requireParent(): array
    {
        if (!is_logged_in()) {
            redirect(PARENT_URL . '/auth/login.php');
        }
        $user = self::user();
        if (!$user || $user['role'] !== 'parent') {
            redirect(PARENT_URL . '/auth/login.php');
        }
        return $user;
    }

    /**
     * Check if onboarding is complete.
     */
    public static function requireOnboarding(): void
    {
        if (!($_SESSION['onboarding_complete'] ?? false)) {
            redirect(STUDENT_URL . '/onboarding/welcome.php');
        }
    }

    /**
     * Check if user can access a feature.
     */
    public static function canAccess(string $feature): bool
    {
        return can_access($feature, current_tier());
    }

    /**
     * Days until SAT test date.
     */
    public static function daysUntilTest(): ?int
    {
        $testDate = $_SESSION['test_date'] ?? null;
        if (!$testDate) return null;
        $now  = new DateTime();
        $test = new DateTime($testDate);
        $diff = $now->diff($test);
        return $diff->invert ? 0 : $diff->days;
    }
}