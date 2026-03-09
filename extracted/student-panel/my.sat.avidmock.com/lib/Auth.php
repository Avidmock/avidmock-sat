<?php
/**
 * AVIDMOCK Auth v5.3
 *
 * ┌─────────────────────────────────────────────────────────────────┐
 *  WHICH METHOD TO USE — QUICK REFERENCE
 * ├──────────────────────────┬──────────────────────────────────────┤
 *  Page type                 │ Method
 * ├──────────────────────────┼──────────────────────────────────────┤
 *  Lesson pages (SEO public) │ Auth::optionalStudent()              │
 *  Quiz / practice pages     │ Auth::requireStudentOrRedirect()     │
 *  Dashboard & all protected │ Auth::requireStudent()               │
 *  Onboarding flow           │ Auth::requireStudentForOnboarding()  │
 *  Admin panel               │ Auth::requireAdmin()                 │
 *  Parent dashboard          │ Auth::requireParent()                │
 * └──────────────────────────┴──────────────────────────────────────┘
 *
 *  WHY THIS MATTERS FOR SEO:
 *  requireStudent() redirects Googlebot to the login page.
 *  The login page has noindex.  Result → Google cannot index your
 *  lesson pages and they disappear from search results.
 *
 *  optionalStudent() lets Googlebot (and any guest) through.
 *  Logged-in users still get their personalised experience.
 *  Use it on every publicly-visible lesson / content page.
 *
 * Changes from v5.2:
 *  - Added clear routing table above
 *  - optionalStudent() returns guest-safe $firstName fallback
 *  - No behaviour changes — pure documentation + safety improvements
 */
class Auth
{
    private static function pdo(): PDO
    {
        global $pdo;
        if (!$pdo instanceof PDO) throw new RuntimeException('Auth: $pdo not available.');
        return $pdo;
    }

    // ──────────────────────────────────────────────────────────────
    //  LOGIN / LOGOUT
    // ──────────────────────────────────────────────────────────────

    public static function attempt(string $email, string $password): ?array
    {
        $stmt = self::pdo()->prepare(
            "SELECT id, first_name, last_name, name, email, password_hash,
                    role, email_verified, is_active, is_suspended,
                    onboarding_complete, test_date, target_score
             FROM users WHERE email = ? AND is_suspended = 0 LIMIT 1"
        );
        $stmt->execute([strtolower(trim($email))]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !password_verify($password, $user['password_hash'])) return null;
        if (!$user['email_verified']) return null;
        self::createSession($user);
        return $user;
    }

    public static function createSession(array $user): void
    {
        session_regenerate_id(true);
        $name = $user['name'] ?? trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $_SESSION['user_id']             = (int) $user['id'];
        $_SESSION['user_name']           = trim($name);
        $_SESSION['user_email']          = $user['email'];
        $_SESSION['user_role']           = $user['role'];
        $_SESSION['user_tier']           = TIER_FREE;
        $_SESSION['onboarding_complete'] = (bool) ($user['onboarding_complete'] ?? false);
        $_SESSION['test_date']           = $user['test_date'] ?? null;
        $_SESSION['target_score']        = (int) ($user['target_score'] ?? 1200);
        $_SESSION['logged_in_at']        = time();
        try {
            self::pdo()->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);
        } catch (Throwable) {}
    }

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

    // ──────────────────────────────────────────────────────────────
    //  USER RETRIEVAL
    // ──────────────────────────────────────────────────────────────

    public static function user(): ?array
    {
        static $cached = null;
        if (!is_logged_in()) return null;
        if ($cached === null) {
            $stmt = self::pdo()->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([current_user_id()]);
            $cached = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($cached) {
                if (empty($cached['name']) && !empty($cached['first_name'])) {
                    $cached['name'] = trim($cached['first_name'] . ' ' . ($cached['last_name'] ?? ''));
                }
                $cached['subscription_plan'] = $_SESSION['user_tier'] ?? TIER_FREE;
                $cached['current_streak']    = (int) ($cached['current_streak'] ?? 0);
                $cached['longest_streak']    = (int) ($cached['longest_streak'] ?? 0);
                $cached['total_xp']          = (int) ($cached['total_xp'] ?? 0);
                $cached['current_level']     = (int) ($cached['current_level'] ?? 1);
                $cached['league']            = $cached['league'] ?? 'bronze';
                if (!empty($cached['onboarding_complete'])) {
                    $_SESSION['onboarding_complete'] = true;
                }
            }
        }
        return $cached;
    }

    public static function isLoggedIn(): bool { return is_logged_in(); }

    public static function isStudent(): bool
    {
        if (!is_logged_in()) return false;
        $role = $_SESSION['user_role'] ?? null;
        if ($role !== null) return $role === 'student';
        $user = self::user();
        return $user !== null && ($user['role'] ?? '') === 'student';
    }

    public static function isAdmin(): bool { return is_admin(); }

    // ──────────────────────────────────────────────────────────────
    //  ★ LESSON PAGES — PUBLIC / SEO-FRIENDLY
    //
    //  Use this on every lesson, article, or content page that
    //  should appear in Google search results.
    //
    //  • Googlebot (not logged in) → gets the full page, can index it
    //  • Guest visitors            → see the full lesson content
    //  • Logged-in students        → get their personalised experience
    //
    //  Returns the user array if logged in, or null for guests.
    //  Your lesson page should handle both cases gracefully, e.g.:
    //
    //    $user      = Auth::optionalStudent();
    //    $firstName = $user ? explode(' ', $user['name'])[0] : 'Student';
    // ──────────────────────────────────────────────────────────────
    public static function optionalStudent(): ?array
    {
        if (!is_logged_in()) return null;
        return self::user();
    }

    // ──────────────────────────────────────────────────────────────
    //  ★ QUIZ / PRACTICE PAGES — LOGIN REQUIRED, RETURN URL PRESERVED
    //
    //  Redirects guests to the login page with a ?redirect= param
    //  so the student lands back on the quiz after logging in.
    //  Use this on /learn/quiz/*, /practice/*, etc.
    // ──────────────────────────────────────────────────────────────
    public static function requireStudentOrRedirect(): array
    {
        if (!is_logged_in()) {
            $returnTo = urlencode(STUDENT_URL . ($_SERVER['REQUEST_URI'] ?? '/'));
            redirect(PUBLIC_URL . '/pages/auth/login.php?redirect=' . $returnTo);
        }
        $user = self::user();
        if (!$user || ($user['role'] ?? '') !== 'student') {
            redirect(PUBLIC_URL . '/pages/auth/login.php');
        }
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        foreach (['/welcome', '/setup', '/diagnostic', '/plan', '/onboarding/'] as $path) {
            if (str_starts_with($uri, $path)) return $user;
        }
        if (!self::checkOnboardingComplete((int) $user['id'])) {
            redirect(STUDENT_URL . '/welcome');
        }
        return $user;
    }

    // ──────────────────────────────────────────────────────────────
    //  ★ DASHBOARD & ALL OTHER PROTECTED PAGES
    //
    //  Hard gate — guests are redirected to login immediately.
    //  Use this on /dashboard, /profile, /settings, etc.
    //  Do NOT use this on lesson pages (breaks SEO — see above).
    // ──────────────────────────────────────────────────────────────
    public static function requireStudent(): array
    {
        if (!is_logged_in()) {
            redirect(PUBLIC_URL . '/pages/auth/login.php?redirect=' . urlencode(STUDENT_URL . ($_SERVER['REQUEST_URI'] ?? '/')));
        }
        $user = self::user();
        if (!$user || ($user['role'] ?? '') !== 'student') {
            redirect(PUBLIC_URL . '/pages/auth/login.php');
        }
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        foreach (['/welcome', '/setup', '/diagnostic', '/plan', '/onboarding/'] as $path) {
            if (str_starts_with($uri, $path)) return $user;
        }
        if (!self::checkOnboardingComplete((int) $user['id'])) {
            redirect(STUDENT_URL . '/welcome');
        }
        return $user;
    }

    // ──────────────────────────────────────────────────────────────
    //  ONBOARDING FLOW
    // ──────────────────────────────────────────────────────────────

    public static function requireStudentForOnboarding(): array
    {
        if (!is_logged_in()) {
            redirect(PUBLIC_URL . '/pages/auth/login.php?redirect=' . urlencode(STUDENT_URL . ($_SERVER['REQUEST_URI'] ?? '/')));
        }
        $user = self::user();
        if (!$user || ($user['role'] ?? '') !== 'student') {
            redirect(PUBLIC_URL . '/pages/auth/login.php');
        }
        return $user;
    }

    /**
     * Checks onboarding completion.
     * Uses Database::fetch() not self::pdo() because global $pdo can be
     * NULL after certain async API calls fire during onboarding.
     */
    public static function checkOnboardingComplete(int $userId): bool
    {
        if (!empty($_SESSION['onboarding_complete'])) return true;

        try {
            $row = Database::fetch('SELECT onboarding_complete FROM users WHERE id = ? LIMIT 1', [$userId]);
            if (!empty($row['onboarding_complete'])) {
                $_SESSION['onboarding_complete'] = true;
                return true;
            }
        } catch (Throwable $e) {
            error_log('checkOnboardingComplete users: ' . $e->getMessage());
        }

        try {
            $row = Database::fetch('SELECT onboarding_complete FROM student_profiles WHERE user_id = ? LIMIT 1', [$userId]);
            if (!empty($row['onboarding_complete'])) {
                try {
                    Database::execute('UPDATE users SET onboarding_complete = 1, updated_at = NOW() WHERE id = ?', [$userId]);
                } catch (Throwable $e) {
                    error_log('checkOnboardingComplete sync: ' . $e->getMessage());
                }
                $_SESSION['onboarding_complete'] = true;
                return true;
            }
        } catch (Throwable $e) {
            error_log('checkOnboardingComplete profiles: ' . $e->getMessage());
        }

        return false;
    }

    // ──────────────────────────────────────────────────────────────
    //  ADMIN / PARENT
    // ──────────────────────────────────────────────────────────────

    public static function requireAdmin(): array
    {
        if (!is_logged_in() || !is_admin()) redirect(ADMIN_URL . '/auth/login.php');
        return self::user();
    }

    public static function requireParent(): array
    {
        if (!is_logged_in()) redirect(PARENT_URL . '/auth/login.php');
        $user = self::user();
        if (!$user || ($user['role'] ?? '') !== 'parent') redirect(PARENT_URL . '/auth/login.php');
        return $user;
    }

    // ──────────────────────────────────────────────────────────────
    //  MISC HELPERS
    // ──────────────────────────────────────────────────────────────

    public static function requireOnboarding(): void
    {
        if (!self::checkOnboardingComplete((int) current_user_id())) {
            redirect(STUDENT_URL . '/welcome');
        }
    }

    public static function canAccess(string $feature): bool
    {
        return can_access($feature, current_tier());
    }

    public static function daysUntilTest(): ?int
    {
        $d = $_SESSION['test_date'] ?? null;
        if (!$d) return null;
        $diff = (new DateTime())->diff(new DateTime($d));
        return $diff->invert ? 0 : $diff->days;
    }
}