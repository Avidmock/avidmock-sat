<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · Leaderboard
 *  Fixed: user_xp table uses `xp` and `level` (not total_xp/current_level)
 *         xp_events table uses `xp_earned` (not xp_amount)
 * ═══════════════════════════════════════════════════════════════════
 */

class Leaderboard
{
    private static function tablesExist(): bool
    {
        try {
            Database::fetchAll("SELECT 1 FROM user_xp LIMIT 1", []);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  All-time top users
    //  Returns: user_id, total_xp, current_level, name, avatar_url, current_streak
    // ─────────────────────────────────────────────────────────────────
    public static function getTop(int $limit = 50): array
    {
        if (!self::tablesExist()) return [];

        try {
            return Database::fetchAll(
                "SELECT ux.user_id,
                        ux.xp                                  AS total_xp,
                        ux.level                               AS current_level,
                        TRIM(CONCAT(u.first_name,' ',u.last_name)) AS name,
                        COALESCE(u.avatar_url,'')              AS avatar_url,
                        COALESCE(ss.current_streak,0)          AS current_streak
                 FROM user_xp ux
                 JOIN users u ON u.id = ux.user_id
                 LEFT JOIN study_streaks ss ON ss.user_id = ux.user_id
                 ORDER BY ux.xp DESC
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
    //  Returns: user_id, weekly_xp, name, avatar_url, current_level
    // ─────────────────────────────────────────────────────────────────
    public static function getWeeklyTop(int $limit = 50): array
    {
        if (!self::tablesExist()) return [];

        try {
            Database::fetchAll("SELECT 1 FROM xp_events LIMIT 1", []);
        } catch (PDOException $e) {
            error_log('[Leaderboard::getWeeklyTop] xp_events missing: ' . $e->getMessage());
            return [];
        }

        try {
            $weekStart = date('Y-m-d', strtotime('monday this week'));
            return Database::fetchAll(
                "SELECT xe.user_id,
                        SUM(xe.xp_earned)                          AS weekly_xp,
                        TRIM(CONCAT(u.first_name,' ',u.last_name)) AS name,
                        COALESCE(u.avatar_url,'')                  AS avatar_url,
                        COALESCE(ux.level,1)                       AS current_level
                 FROM xp_events xe
                 JOIN users u ON u.id = xe.user_id
                 LEFT JOIN user_xp ux ON ux.user_id = xe.user_id
                 WHERE xe.created_at >= ?
                 GROUP BY xe.user_id, u.first_name, u.last_name, u.avatar_url, ux.level
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
    //  Returns: rank, total, xp, percentile
    // ─────────────────────────────────────────────────────────────────
    public static function getUserRank(int $userId): array
    {
        $default = ['rank' => 0, 'total' => 0, 'xp' => 0, 'percentile' => 0];

        if (!self::tablesExist()) return $default;

        try {
            $userXp = (int)(Database::fetchColumn(
                "SELECT COALESCE(xp, 0) FROM user_xp WHERE user_id = ?",
                [$userId]
            ) ?? 0);

            $rank = (int)(Database::fetchColumn(
                "SELECT COUNT(*) + 1 FROM user_xp WHERE xp > ?",
                [$userXp]
            ) ?? 1);

            $total = (int)(Database::fetchColumn(
                "SELECT COUNT(*) FROM user_xp",
                []
            ) ?? 0);

            return [
                'rank'       => $rank,
                'total'      => $total,
                'xp'         => $userXp,
                'percentile' => $total > 0 ? round((($total - $rank) / $total) * 100) : 0,
            ];
        } catch (PDOException $e) {
            error_log('[Leaderboard::getUserRank] ' . $e->getMessage());
            return $default;
        }
    }

    /**
     * Convenience — returns just the user's total XP as an int.
     */
    public static function getUserXP(int $userId): int
    {
        return self::getUserRank($userId)['xp'];
    }

    // ─────────────────────────────────────────────────────────────────
    //  Neighbors (above + user + below)
    //  Returns: ['above' => [], 'user' => [...], 'below' => []]
    // ─────────────────────────────────────────────────────────────────
    public static function getNeighbors(int $userId): array
    {
        $default = [
            'above' => [],
            'user'  => ['user_id' => $userId, 'total_xp' => 0, 'name' => ''],
            'below' => [],
        ];

        if (!self::tablesExist()) return $default;

        try {
            $userXp = (int)(Database::fetchColumn(
                "SELECT COALESCE(xp, 0) FROM user_xp WHERE user_id = ?",
                [$userId]
            ) ?? 0);

            $nameExpr = "TRIM(CONCAT(u.first_name,' ',u.last_name))";

            $above = Database::fetchAll(
                "SELECT ux.user_id,
                        ux.xp AS total_xp,
                        {$nameExpr} AS name,
                        COALESCE(u.avatar_url,'') AS avatar_url
                 FROM user_xp ux
                 JOIN users u ON u.id = ux.user_id
                 WHERE ux.xp > ?
                 ORDER BY ux.xp ASC LIMIT 3",
                [$userXp]
            );

            $below = Database::fetchAll(
                "SELECT ux.user_id,
                        ux.xp AS total_xp,
                        {$nameExpr} AS name,
                        COALESCE(u.avatar_url,'') AS avatar_url
                 FROM user_xp ux
                 JOIN users u ON u.id = ux.user_id
                 WHERE ux.xp < ?
                 ORDER BY ux.xp DESC LIMIT 3",
                [$userXp]
            );

            $userName = Database::fetchColumn(
                "SELECT TRIM(CONCAT(first_name,' ',last_name)) FROM users WHERE id = ?",
                [$userId]
            ) ?: '';

            return [
                'above' => array_reverse($above),
                'user'  => ['user_id' => $userId, 'total_xp' => $userXp, 'name' => $userName],
                'below' => $below,
            ];
        } catch (PDOException $e) {
            error_log('[Leaderboard::getNeighbors] ' . $e->getMessage());
            return $default;
        }
    }
}