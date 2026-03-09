<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · User
 *  Student, admin, and parent account management.
 * ═══════════════════════════════════════════════════════════════════
 */

class User
{
    /**
     * Create a new user account.
     */
    public static function create(array $data): int
    {
        return Database::insert('users', [
            'name'                => trim($data['name']),
            'email'               => strtolower(trim($data['email'])),
            'password_hash'       => password_hash($data['password'], PASSWORD_ARGON2ID),
            'role'                => $data['role'] ?? 'student',
            'email_verified'      => 0,
            'onboarding_complete' => 0,
            'verification_token'  => bin2hex(random_bytes(32)),
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Find user by email.
     */
    public static function findByEmail(string $email): ?array
    {
        return Database::fetch(
            "SELECT * FROM users WHERE email = ? LIMIT 1",
            [strtolower(trim($email))]
        );
    }

    /**
     * Find user by ID.
     */
    public static function findById(int $id): ?array
    {
        return Database::fetch("SELECT * FROM users WHERE id = ? LIMIT 1", [$id]);
    }

    /**
     * Find user by verification token.
     */
    public static function findByVerificationToken(string $token): ?array
    {
        return Database::fetch(
            "SELECT * FROM users WHERE verification_token = ? LIMIT 1",
            [$token]
        );
    }

    /**
     * Find user by password reset token (not expired).
     */
    public static function findByResetToken(string $token): ?array
    {
        return Database::fetch(
            "SELECT * FROM users
             WHERE reset_token = ? AND reset_expires_at > NOW()
             LIMIT 1",
            [$token]
        );
    }

    /**
     * Find or create user from Google OAuth.
     */
    public static function findOrCreateFromGoogle(array $googleUser): array
    {
        $existing = self::findByEmail($googleUser['email']);

        if ($existing) {
            // Update Google ID if not set
            if (empty($existing['google_id'])) {
                Database::update('users', [
                    'google_id'      => $googleUser['id'],
                    'avatar_url'     => $googleUser['picture'] ?? null,
                    'email_verified' => 1,
                ], ['id' => $existing['id']]);
            }
            return self::findById($existing['id']);
        }

        // Create new account from Google
        $id = Database::insert('users', [
            'name'                => $googleUser['name'],
            'email'               => strtolower($googleUser['email']),
            'password_hash'       => '', // no password for OAuth
            'google_id'           => $googleUser['id'],
            'avatar_url'          => $googleUser['picture'] ?? null,
            'role'                => 'student',
            'email_verified'      => 1,
            'onboarding_complete' => 0,
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);

        // Initialize supporting records
        self::initializeStudent($id);

        return self::findById($id);
    }

    /**
     * Verify a user's email.
     */
    public static function verifyEmail(string $token): bool
    {
        $user = self::findByVerificationToken($token);
        if (!$user) return false;

        Database::update('users', [
            'email_verified'     => 1,
            'verification_token' => null,
            'updated_at'         => date('Y-m-d H:i:s'),
        ], ['id' => $user['id']]);

        return true;
    }

    /**
     * Set password reset token.
     */
    public static function setResetToken(int $userId): string
    {
        $token = bin2hex(random_bytes(32));

        Database::update('users', [
            'reset_token'      => $token,
            'reset_expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
        ], ['id' => $userId]);

        return $token;
    }

    /**
     * Reset password using token.
     */
    public static function resetPassword(string $token, string $newPassword): bool
    {
        $user = self::findByResetToken($token);
        if (!$user) return false;

        Database::update('users', [
            'password_hash'    => password_hash($newPassword, PASSWORD_ARGON2ID),
            'reset_token'      => null,
            'reset_expires_at' => null,
            'updated_at'       => date('Y-m-d H:i:s'),
        ], ['id' => $user['id']]);

        return true;
    }

    /**
     * Update user profile fields.
     */
    public static function update(int $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return Database::update('users', $data, ['id' => $id]) > 0;
    }

    /**
     * Update onboarding data (test date, target score, study hours).
     */
    public static function updateOnboarding(int $id, array $data): void
    {
        $allowed = ['test_date', 'target_score', 'study_hours'];
        $filtered = array_intersect_key($data, array_flip($allowed));
        $filtered['updated_at'] = date('Y-m-d H:i:s');

        Database::update('users', $filtered, ['id' => $id]);
    }

    /**
     * Mark onboarding complete.
     */
    public static function completeOnboarding(int $id): void
    {
        Database::update('users', [
            'onboarding_complete' => 1,
            'updated_at'          => date('Y-m-d H:i:s'),
        ], ['id' => $id]);

        $_SESSION['onboarding_complete'] = true;
    }

    /**
     * Initialize all supporting tables for a new student.
     */
    public static function initializeStudent(int $userId): void
    {
        // Study streak
        Database::insert('study_streaks', [
            'user_id'            => $userId,
            'current_streak'     => 0,
            'longest_streak'     => 0,
            'last_activity_date' => null,
            'total_study_days'   => 0,
        ]);

        // XP
        Database::insert('user_xp', [
            'user_id'       => $userId,
            'total_xp'      => 0,
            'current_level'  => 1,
            'league'        => 'bronze',
            'league_rank'   => 0,
        ]);
    }

    /**
     * Suspend / unsuspend a user.
     */
    public static function toggleSuspend(int $id): bool
    {
        $user = self::findById($id);
        if (!$user) return false;

        $newStatus = $user['is_suspended'] ? 0 : 1;
        Database::update('users', ['is_suspended' => $newStatus], ['id' => $id]);
        return true;
    }

    /**
     * Get comprehensive student stats for dashboard / profile.
     */
    public static function getStats(int $userId): array
    {
        // Quiz performance
        try {
            $quizRow = Database::fetch(
                "SELECT COUNT(*) as total_tests,
                        COALESCE(AVG(score), 0) as avg_score,
                        COALESCE(MAX(score), 0) as best_score,
                        COALESCE(SUM(correct), 0) as total_correct,
                        COALESCE(SUM(total), 0) as total_questions,
                        MAX(completed_at) as last_test_date
                 FROM sat_quiz_attempts
                 WHERE user_id = ? AND status = 'completed'",
                [$userId]
            );
        } catch (\Throwable $e) {
            $quizRow = [
                'total_tests'     => 0,
                'avg_score'       => 0,
                'best_score'      => 0,
                'total_correct'   => 0,
                'total_questions' => 0,
                'last_test_date'  => null,
            ];
        }

        // Practice test stats
        try {
            $practiceRow = Database::fetch(
                "SELECT COUNT(*) as total_practice_tests,
                        COALESCE(MAX(total_score), 0) as best_practice_score,
                        COALESCE(AVG(total_score), 0) as avg_practice_score
                 FROM practice_test_attempts
                 WHERE user_id = ? AND status = 'completed'",
                [$userId]
            );
        } catch (\Throwable $e) {
            $practiceRow = [
                'total_practice_tests'  => 0,
                'best_practice_score'   => 0,
                'avg_practice_score'    => 0,
            ];
        }

        // Improvement: compare avg score of first 3 attempts vs last 3 attempts
        $improvement = 0;
        try {
            $early = Database::fetch(
                "SELECT COALESCE(AVG(score), 0) as avg_score
                 FROM (
                     SELECT score FROM sat_quiz_attempts
                     WHERE user_id = ? AND status = 'completed'
                     ORDER BY completed_at ASC LIMIT 3
                 ) AS early_attempts",
                [$userId]
            );
            $recent = Database::fetch(
                "SELECT COALESCE(AVG(score), 0) as avg_score
                 FROM (
                     SELECT score FROM sat_quiz_attempts
                     WHERE user_id = ? AND status = 'completed'
                     ORDER BY completed_at DESC LIMIT 3
                 ) AS recent_attempts",
                [$userId]
            );
            $improvement = round((float) $recent['avg_score'] - (float) $early['avg_score'], 2);
        } catch (\Throwable $e) {
            $improvement = 0;
        }

        // Build weighted average: combine quiz avg and practice avg weighted by attempt counts
        $quizCount     = (int) $quizRow['total_tests'];
        $practiceCount = (int) $practiceRow['total_practice_tests'];
        $totalTests    = $quizCount + $practiceCount;

        if ($totalTests > 0) {
            $weightedAvg = round(
                ($quizRow['avg_score'] * $quizCount + $practiceRow['avg_practice_score'] * $practiceCount)
                / $totalTests,
                2
            );
        } else {
            $weightedAvg = 0;
        }

        return [
            'best_score'      => max((float) $quizRow['best_score'], (float) $practiceRow['best_practice_score']),
            'avg_score'       => $weightedAvg,
            'improvement'     => $improvement,
            'total_questions' => (int) $quizRow['total_questions'],
            'total_tests'     => $totalTests,
            'last_test_date'  => $quizRow['last_test_date'],
        ];
    }

    /**
     * Get the student's score improvement over time.
     */
    public static function getScoreTrend(int $userId, int $days = 30): array
    {
        return Database::fetchAll(
            "SELECT DATE(time_completed) AS date, AVG(score) AS avg_score
             FROM quiz_attempts
             WHERE user_id = ? AND status = 'completed'
               AND time_completed >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(time_completed)
             ORDER BY date ASC",
            [$userId, $days]
        );
    }

    /**
     * Search students (admin use).
     */
    public static function search(string $query, int $page = 1, int $perPage = 20): array
    {
        $like = "%{$query}%";
        return Database::paginate(
            "SELECT u.id, u.name, u.email, u.created_at, u.last_login_at,
                    u.is_suspended, u.onboarding_complete,
                    COALESCE(ss.current_streak, 0) AS streak,
                    COALESCE(ux.total_xp, 0) AS xp
             FROM users u
             LEFT JOIN study_streaks ss ON ss.user_id = u.id
             LEFT JOIN user_xp ux ON ux.user_id = u.id
             WHERE u.role = 'student'
               AND (u.name LIKE ? OR u.email LIKE ?)
             ORDER BY u.last_login_at DESC",
            [$like, $like],
            $page,
            $perPage
        );
    }

    /**
     * Get total student count (admin dashboard).
     */
    public static function totalStudents(): int
    {
        return Database::count('users', ['role' => 'student']);
    }

    /**
     * Get active today count (admin dashboard).
     */
    public static function activeToday(): int
    {
        return (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM users
             WHERE role = 'student' AND DATE(last_login_at) = CURDATE()"
        );
    }

    /**
     * Export students as CSV-ready array (admin use).
     */
    public static function exportAll(): array
    {
        return Database::fetchAll(
            "SELECT u.id, u.name, u.email, u.created_at, u.last_login_at,
                    u.target_score, u.test_date,
                    COALESCE(ss.current_streak, 0) AS streak,
                    COALESCE(ux.total_xp, 0) AS xp,
                    COALESCE(sp.best_score, 0) AS best_quiz_score
             FROM users u
             LEFT JOIN study_streaks ss ON ss.user_id = u.id
             LEFT JOIN user_xp ux ON ux.user_id = u.id
             LEFT JOIN (
                 SELECT user_id, MAX(best_score) AS best_score
                 FROM student_performance GROUP BY user_id
             ) sp ON sp.user_id = u.id
             WHERE u.role = 'student'
             ORDER BY u.created_at DESC"
        );
    }
}