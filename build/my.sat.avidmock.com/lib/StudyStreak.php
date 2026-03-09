<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · StudyStreak
 *  Fixed: column is `last_active` (not last_activity_date)
 * ═══════════════════════════════════════════════════════════════════
 */

class StudyStreak
{
    // ─────────────────────────────────────────────────────────────────
    //  Update streak after any study activity
    // ─────────────────────────────────────────────────────────────────
    public static function update(int $userId): array
    {
        try {
            $streak = Database::fetch(
                "SELECT * FROM study_streaks WHERE user_id = ?",
                [$userId]
            );

            if (!$streak) {
                Database::insert('study_streaks', [
                    'user_id'        => $userId,
                    'current_streak' => 1,
                    'longest_streak' => 1,
                    'last_active'    => date('Y-m-d'),
                ]);
                return ['streak' => 1, 'increased' => true, 'freeze_used' => false];
            }

            $today         = new DateTime(date('Y-m-d'));
            $lastActive    = new DateTime($streak['last_active'] ?? '2000-01-01');
            $daysSinceLast = (int) $today->diff($lastActive)->days;

            if ($daysSinceLast === 0) {
                return ['streak' => (int)$streak['current_streak'], 'increased' => false, 'freeze_used' => false];
            }

            $freezeUsed = false;

            if ($daysSinceLast === 1) {
                $newStreak = (int)$streak['current_streak'] + 1;
            } elseif ($daysSinceLast === 2) {
                try {
                    $freeze = Database::fetch(
                        "SELECT id FROM streak_freezes WHERE user_id = ? AND is_used = 0 LIMIT 1",
                        [$userId]
                    );
                } catch (Throwable $e) {
                    $freeze = null;
                }
                if ($freeze) {
                    Database::update('streak_freezes', [
                        'is_used' => 1,
                        'used_at' => date('Y-m-d H:i:s'),
                    ], ['id' => $freeze['id']]);
                    $newStreak  = (int)$streak['current_streak'] + 1;
                    $freezeUsed = true;
                } else {
                    $newStreak = 1;
                }
            } else {
                $newStreak = 1;
            }

            $longestStreak = max((int)$streak['longest_streak'], $newStreak);

            Database::update('study_streaks', [
                'current_streak' => $newStreak,
                'longest_streak' => $longestStreak,
                'last_active'    => date('Y-m-d'),
            ], ['user_id' => $userId]);

            return [
                'streak'      => $newStreak,
                'longest'     => $longestStreak,
                'increased'   => true,
                'freeze_used' => $freezeUsed,
            ];
        } catch (Throwable $e) {
            error_log('[StudyStreak::update] ' . $e->getMessage());
            return ['streak' => 0, 'increased' => false, 'freeze_used' => false];
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Get current streak count — returns int
    // ─────────────────────────────────────────────────────────────────
    public static function getCurrent(int $userId): int
    {
        try {
            $row = Database::fetch(
                "SELECT current_streak FROM study_streaks WHERE user_id = ?",
                [$userId]
            );
            return (int)($row['current_streak'] ?? 0);
        } catch (Throwable $e) {
            error_log('[StudyStreak::getCurrent] ' . $e->getMessage());
            return 0;
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Get longest streak — returns int
    // ─────────────────────────────────────────────────────────────────
    public static function getLongest(int $userId): int
    {
        try {
            $row = Database::fetch(
                "SELECT longest_streak FROM study_streaks WHERE user_id = ?",
                [$userId]
            );
            return (int)($row['longest_streak'] ?? 0);
        } catch (Throwable $e) {
            error_log('[StudyStreak::getLongest] ' . $e->getMessage());
            return 0;
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Get full streak info — returns array
    // ─────────────────────────────────────────────────────────────────
    public static function get(int $userId): array
    {
        $default = [
            'current_streak'    => 0,
            'longest_streak'    => 0,
            'is_active_today'   => false,
            'freezes_available' => 0,
        ];

        try {
            $streak = Database::fetch(
                "SELECT * FROM study_streaks WHERE user_id = ?",
                [$userId]
            );

            if (!$streak) return $default;

            $streak['is_active_today'] = ($streak['last_active'] === date('Y-m-d'));

            try {
                $streak['freezes_available'] = (int)Database::fetchColumn(
                    "SELECT COUNT(*) FROM streak_freezes WHERE user_id = ? AND is_used = 0",
                    [$userId]
                );
            } catch (Throwable $e) {
                $streak['freezes_available'] = 0;
            }

            return $streak;
        } catch (Throwable $e) {
            error_log('[StudyStreak::get] ' . $e->getMessage());
            return $default;
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Last 15 days activity grid
    // ─────────────────────────────────────────────────────────────────
    public static function getLast15Days(int $userId): array
    {
        return self::getActivityGrid($userId, 15);
    }

    public static function getActivityGrid(int $userId, int $days = 15): array
    {
        try {
            $rows = Database::fetchAll(
                "SELECT DATE(completed_at) AS day, COUNT(*) AS sessions
                 FROM sat_quiz_attempts
                 WHERE user_id = ?
                   AND status = 'completed'
                   AND completed_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                 GROUP BY DATE(completed_at)",
                [$userId, $days]
            );

            $map = [];
            foreach ($rows as $row) {
                $map[$row['day']] = (int)$row['sessions'];
            }

            $grid = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $date   = date('Y-m-d', strtotime("-{$i} days"));
                $grid[] = $map[$date] ?? 0;
            }
            return $grid;
        } catch (Throwable $e) {
            error_log('[StudyStreak::getActivityGrid] ' . $e->getMessage());
            return array_fill(0, $days, 0);
        }
    }
}