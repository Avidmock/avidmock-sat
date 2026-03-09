<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · Session
 *  Group session management, enrollment, Zoom link integration.
 *
 *  DB table: tutoring_sessions
 *  Columns : id, title, description, zoom_link, scheduled_at,
 *            duration_mins, max_students, subject, created_at
 * ═══════════════════════════════════════════════════════════════════
 */

class Session
{
    // ─────────────────────────────────────────────
    //  Internal helpers
    // ─────────────────────────────────────────────

    private static function tablesExist(): bool
    {
        try {
            Database::fetchAll("SELECT 1 FROM tutoring_sessions LIMIT 1", []);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Build a safe WHERE clause + bindings for subject filtering.
     * Returns [ string $extraWhere, array $extraParams ]
     * $extraWhere is either '' or " AND subject = ?"
     */
    private static function subjectClause(string $subject): array
    {
        if ($subject === '') {
            return ['', []];
        }
        return [' AND subject = ?', [$subject]];
    }


    // ─────────────────────────────────────────────
    //  Public read methods
    // ─────────────────────────────────────────────

    /**
     * Upcoming sessions (future), with enrolled count and whether
     * the given user is enrolled.  Optionally filtered by subject.
     *
     * Called in index.php:
     *   Session::getUpcoming($userId, $subject)
     */
    public static function getUpcoming(int $userId, string $subject = '', int $limit = 20): array
    {
        if (!self::tablesExist()) return [];

        [$subjectWhere, $subjectParams] = self::subjectClause($subject);

        try {
            return Database::fetchAll(
                "SELECT s.*,
                        (SELECT COUNT(*) FROM session_enrollments
                         WHERE session_id = s.id) AS enrolled_count,
                        (SELECT COUNT(*) FROM session_enrollments
                         WHERE session_id = s.id AND user_id = ?) AS is_enrolled
                 FROM tutoring_sessions s
                 WHERE s.scheduled_at > NOW()
                 {$subjectWhere}
                 ORDER BY s.scheduled_at ASC
                 LIMIT ?",
                array_merge([$userId], $subjectParams, [$limit])
            );
        } catch (PDOException $e) {
            error_log('[Session::getUpcoming] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * All sessions (past + future), with enrolled count.
     * Optionally filtered by subject.
     *
     * Called in index.php:
     *   Session::getAll($userId, $subject)
     */
    public static function getAll(int $userId, string $subject = '', int $limit = 50): array
    {
        if (!self::tablesExist()) return [];

        [$subjectWhere, $subjectParams] = self::subjectClause($subject);

        try {
            return Database::fetchAll(
                "SELECT s.*,
                        (SELECT COUNT(*) FROM session_enrollments
                         WHERE session_id = s.id) AS enrolled_count,
                        (SELECT COUNT(*) FROM session_enrollments
                         WHERE session_id = s.id AND user_id = ?) AS is_enrolled
                 FROM tutoring_sessions s
                 WHERE 1=1
                 {$subjectWhere}
                 ORDER BY s.scheduled_at ASC
                 LIMIT ?",
                array_merge([$userId], $subjectParams, [$limit])
            );
        } catch (PDOException $e) {
            error_log('[Session::getAll] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Sessions the user is enrolled in (future only).
     *
     * Called in index.php:
     *   Session::getEnrolled($userId)
     */
    public static function getEnrolled(int $userId): array
    {
        if (!self::tablesExist()) return [];

        try {
            return Database::fetchAll(
                "SELECT s.*,
                        (SELECT COUNT(*) FROM session_enrollments
                         WHERE session_id = s.id) AS enrolled_count,
                        1 AS is_enrolled
                 FROM tutoring_sessions s
                 JOIN session_enrollments se ON se.session_id = s.id
                 WHERE se.user_id = ?
                   AND s.scheduled_at > NOW()
                 ORDER BY s.scheduled_at ASC",
                [$userId]
            );
        } catch (PDOException $e) {
            error_log('[Session::getEnrolled] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Past sessions the user attended (or was enrolled in).
     *
     * Called in index.php:
     *   Session::getPast($userId, 3)
     */
    public static function getPast(int $userId, int $limit = 10): array
    {
        if (!self::tablesExist()) return [];

        try {
            return Database::fetchAll(
                "SELECT s.*, se.attended
                 FROM tutoring_sessions s
                 JOIN session_enrollments se ON se.session_id = s.id
                 WHERE se.user_id = ?
                   AND s.scheduled_at <= NOW()
                 ORDER BY s.scheduled_at DESC
                 LIMIT ?",
                [$userId, $limit]
            );
        } catch (PDOException $e) {
            error_log('[Session::getPast] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Single session by ID, enriched with enrolled_count and is_full flag.
     */
    public static function getById(int $id): ?array
    {
        if (!self::tablesExist()) return null;

        try {
            $session = Database::fetch(
                "SELECT * FROM tutoring_sessions WHERE id = ?",
                [$id]
            );
            if (!$session) return null;

            $session['enrolled_count'] = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM session_enrollments WHERE session_id = ?",
                [$id]
            );
            $session['is_full'] = $session['enrolled_count'] >= (int) $session['max_students'];

            return $session;
        } catch (PDOException $e) {
            error_log('[Session::getById] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if a user is enrolled in a specific session.
     */
    public static function isEnrolled(int $userId, int $sessionId): bool
    {
        try {
            return Database::exists('session_enrollments', [
                'user_id'    => $userId,
                'session_id' => $sessionId,
            ]);
        } catch (PDOException $e) {
            error_log('[Session::isEnrolled] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * List of all attendees (enrolled users) for a session.
     */
    public static function getAttendees(int $sessionId): array
    {
        if (!self::tablesExist()) return [];

        try {
            return Database::fetchAll(
                "SELECT u.id, u.name, u.email, u.avatar_url, se.attended
                 FROM session_enrollments se
                 JOIN users u ON u.id = se.user_id
                 WHERE se.session_id = ?
                 ORDER BY u.name ASC",
                [$sessionId]
            );
        } catch (PDOException $e) {
            error_log('[Session::getAttendees] ' . $e->getMessage());
            return [];
        }
    }


    // ─────────────────────────────────────────────
    //  Enrollment mutations
    // ─────────────────────────────────────────────

    /**
     * Enroll a user in a session.
     * Returns ['success' => bool, 'error' => string, 'zoom_link' => string]
     */
    public static function enroll(int $userId, int $sessionId): array
    {
        try {
            $session = self::getById($sessionId);

            if (!$session) {
                return ['success' => false, 'error' => 'Session not found'];
            }
            if ($session['is_full']) {
                return ['success' => false, 'error' => 'Session is full'];
            }
            if (self::isEnrolled($userId, $sessionId)) {
                return ['success' => false, 'error' => 'Already enrolled'];
            }

            Database::insert('session_enrollments', [
                'user_id'    => $userId,
                'session_id' => $sessionId,
                'attended'   => 0,
            ]);

            return [
                'success'   => true,
                'zoom_link' => $session['zoom_link'] ?? '',
            ];
        } catch (PDOException $e) {
            error_log('[Session::enroll] ' . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }

    /**
     * Unenroll a user from a session.
     */
    public static function unenroll(int $userId, int $sessionId): bool
    {
        try {
            return Database::delete('session_enrollments', [
                'user_id'    => $userId,
                'session_id' => $sessionId,
            ]) > 0;
        } catch (PDOException $e) {
            error_log('[Session::unenroll] ' . $e->getMessage());
            return false;
        }
    }


    // ─────────────────────────────────────────────
    //  Admin / creation
    // ─────────────────────────────────────────────

    /**
     * Create a new session. Returns the new session's ID, or 0 on failure.
     */
    public static function create(array $data): int
    {
        try {
            return Database::insert('tutoring_sessions', [
                'title'         => trim($data['title']),
                'description'   => trim($data['description']   ?? ''),
                'zoom_link'     => trim($data['zoom_link']     ?? ''),
                'scheduled_at'  => $data['scheduled_at'],
                'duration_mins' => (int) ($data['duration_mins'] ?? 60),
                'max_students'  => (int) ($data['max_students']  ?? 30),
                'subject'       => $data['subject']             ?? 'general',
            ]);
        } catch (PDOException $e) {
            error_log('[Session::create] ' . $e->getMessage());
            return 0;
        }
    }
}