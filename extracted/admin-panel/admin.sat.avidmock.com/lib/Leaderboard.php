<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · Leaderboard
 *  Weekly leaderboard, user rank pinning, league standings.
 *
 *  Fixed:
 *   - users table has first_name + last_name (not a single 'name' column)
 *   - getUserRank() always returns a consistent array shape
 *   - Added getUserXP() convenience method for callers that just need XP
 * ═══════════════════════════════════════════════════════════════════
 */

class Leaderboard
{
    /**
     * Check whether the core XP tables exist before querying them.
     */
    private static function tablesExist(): bool
    {
        try {
            Database::fetchAll("SELECT 1 FROM user_xp LIMIT 1", []);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Convenience: build a display name from first_name + last_name.
     * Used in every SELECT that pulls user identity.
     */
    private static function nameExpr(): string
    {
        return "TRIM(CONCAT(u.first_name, ' ', u.last_name))";
    }


    // ─────────────────────────────────────────────────────────────────
    //  All-time top users
    // ─────────────────────────────────────────────────────────────────

    /**
     * Returns up to $limit students ordered by total XP descending.
     * Each row: user_id, total_xp, current_level, league,
     *           name, avatar_url, current_streak
     */
    public static function getTop(int $limit = 50): array
    {
        if (!self::tablesExist()) {
            return [];
        }

        try {
            $nameExpr = self::nameExpr();
            return Database::fetchAll(
                "SELECT ux.user_id,
                        ux.total_xp,
                        ux.current_level,
                        COALESCE(ux.league, 'Bronze')          AS league,
                        {$nameExpr}                             AS name,
                        u.avatar_url,
                        COALESCE(ss.current_streak, 0)         AS current_streak
                 FROM user_xp ux
                 JOIN users u ON u.id = ux.user_id AND u.role = 'student' AND u.is_active = 1
                 LEFT JOIN study_streaks ss ON ss.user_id = ux.user_id
                 ORDER BY ux.total_xp DESC
                 LIMIT ?",
                [$limit]
            );
        } catch (PDOException $e) {
            error_log('[Leaderboard::getTop] ' . $e->getMessage());
            return [];
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Weekly top users
    // ─────────────────────────────────────────────────────────────────

    /**
     * Returns up to $limit students by XP earned since Monday this week.
     * Each row: user_id, weekly_xp, name, avatar_url, league, current_level
     */
    public static function getWeeklyTop(int $limit = 50): array
    {
        if (!self::tablesExist()) {
            return [];
        }

        // Check xp_events table exists too
        try {
            Database::fetchAll("SELECT 1 FROM xp_events LIMIT 1", []);
        } catch (PDOException $e) {
            error_log('[Leaderboard::getWeeklyTop] xp_events table missing: ' . $e->getMessage());
            return [];
        }

        try {
            $weekStart = date('Y-m-d', strtotime('monday this week'));
            $nameExpr  = self::nameExpr();

            return Database::fetchAll(
                "SELECT xe.user_id,
                        SUM(xe.xp_amount)                      AS weekly_xp,
                        {$nameExpr}                             AS name,
                        u.avatar_url,
                        COALESCE(ux.league, 'Bronze')          AS league,
                        COALESCE(ux.current_level, 1)          AS current_level
                 FROM xp_events xe
                 JOIN users u ON u.id = xe.user_id AND u.is_active = 1
                 LEFT JOIN user_xp ux ON ux.user_id = xe.user_id
                 WHERE xe.created_at >= ?
                 GROUP BY xe.user_id, u.first_name, u.last_name, u.avatar_url,
                          ux.league, ux.current_level
                 ORDER BY weekly_xp DESC
                 LIMIT ?",
                [$weekStart, $limit]
            );
        } catch (PDOException $e) {
            error_log('[Leaderboard::getWeeklyTop] ' . $e->getMessage());
            return [];
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Single user rank
    // ─────────────────────────────────────────────────────────────────

    /**
     * Returns an array:
     *   rank       — 1-based position (1 = top)
     *   total      — total number of ranked users
     *   xp         — user's total XP
     *   percentile — 0-100, higher = better
     *
     * Always returns this shape (never null, never an int).
     */
    public static function getUserRank(int $userId): array
    {
        $default = [
            'rank'       => 0,
            'total'      => 0,
            'xp'         => 0,
            'percentile' => 0,
        ];

        if (!self::tablesExist()) {
            return $default;
        }

        try {
            $totalXp = (int) Database::fetchColumn(
                "SELECT COALESCE(total_xp, 0) FROM user_xp WHERE user_id = ?",
                [$userId]
            );

            $rank = (int) Database::fetchColumn(
                "SELECT COUNT(*) + 1 FROM user_xp WHERE total_xp > ?",
                [$totalXp]
            );

            $totalUsers = (int) Database::count('user_xp');

            return [
                'rank'       => $rank,
                'total'      => $totalUsers,
                'xp'         => $totalXp,
                'percentile' => $totalUsers > 0
                    ? round((($totalUsers - $rank) / $totalUsers) * 100)
                    : 0,
            ];
        } catch (PDOException $e) {
            error_log('[Leaderboard::getUserRank] ' . $e->getMessage());
            return $default;
        }
    }

    /**
     * Convenience wrapper — returns just the user's total XP as an int.
     * Use when you only need the number and not the full rank array.
     */
    public static function getUserXP(int $userId): int
    {
        return self::getUserRank($userId)['xp'];
    }

    // ─────────────────────────────────────────────────────────────────
    //  Neighbors (5 above + user + 5 below)
    // ─────────────────────────────────────────────────────────────────

    /**
     * Returns ['above' => [], 'user' => [...], 'below' => []]
     * Useful for the pinned-rank widget on the leaderboard page.
     */
    public static function getNeighbors(int $userId): array
    {
        $default = [
            'above' => [],
            'user'  => ['user_id' => $userId, 'total_xp' => 0, 'name' => ''],
            'below' => [],
        ];

        if (!self::tablesExist()) {
            return $default;
        }

        try {
            $nameExpr = self::nameExpr();

            $userXp = (int) Database::fetchColumn(
                "SELECT COALESCE(total_xp, 0) FROM user_xp WHERE user_id = ?",
                [$userId]
            );

            $above = Database::fetchAll(
                "SELECT ux.user_id,
                        ux.total_xp,
                        COALESCE(ux.league, 'Bronze') AS league,
                        {$nameExpr}                   AS name,
                        u.avatar_url
                 FROM user_xp ux
                 JOIN users u ON u.id = ux.user_id
                 WHERE ux.total_xp > ?
                 ORDER BY ux.total_xp ASC
                 LIMIT 5",
                [$userXp]
            );

            $below = Database::fetchAll(
                "SELECT ux.user_id,
                        ux.total_xp,
                        COALESCE(ux.league, 'Bronze') AS league,
                        {$nameExpr}                   AS name,
                        u.avatar_url
                 FROM user_xp ux
                 JOIN users u ON u.id = ux.user_id
                 WHERE ux.total_xp < ?
                 ORDER BY ux.total_xp DESC
                 LIMIT 5",
                [$userXp]
            );

            // Get current user's name for the middle row
            $userName = Database::fetchColumn(
                "SELECT TRIM(CONCAT(first_name, ' ', last_name)) FROM users WHERE id = ?",
                [$userId]
            ) ?: '';

            return [
                'above' => array_reverse($above),
                'user'  => [
                    'user_id'  => $userId,
                    'total_xp' => $userXp,
                    'name'     => $userName,
                ],
                'below' => $below,
            ];
        } catch (PDOException $e) {
            error_log('[Leaderboard::getNeighbors] ' . $e->getMessage());
            return $default;
        }
    }
}