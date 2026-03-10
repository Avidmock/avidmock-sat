<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · Achievement
 *  Badge system: definitions, unlock logic, celebrations.
 * ═══════════════════════════════════════════════════════════════════
 */

class Achievement
{
    private static array $definitions = [
        'onboarding_complete' => ['name' => 'Welcome Aboard',     'tier' => 'bronze',  'xp_reward' => 50,  'description' => 'Complete the onboarding process',          'badge_color' => '#cd7f32'],
        'first_quiz'          => ['name' => 'Quiz Starter',       'tier' => 'bronze',  'xp_reward' => 25,  'description' => 'Complete your first quiz',                  'badge_color' => '#cd7f32'],
        'first_perfect'       => ['name' => 'Perfectionist',      'tier' => 'gold',    'xp_reward' => 200, 'description' => 'Score 100% on any quiz',                    'badge_color' => '#f5a623'],
        'streak_3'            => ['name' => 'Getting Warm',        'tier' => 'bronze',  'xp_reward' => 30,  'description' => '3-day study streak',                        'badge_color' => '#cd7f32'],
        'streak_7'            => ['name' => 'Week Warrior',        'tier' => 'silver',  'xp_reward' => 75,  'description' => '7-day study streak',                        'badge_color' => '#a8a8b8'],
        'streak_14'           => ['name' => 'Fortnight Force',     'tier' => 'silver',  'xp_reward' => 150, 'description' => '14-day study streak',                       'badge_color' => '#a8a8b8'],
        'streak_30'           => ['name' => 'Monthly Master',      'tier' => 'gold',    'xp_reward' => 300, 'description' => '30-day study streak',                       'badge_color' => '#f5a623'],
        'streak_60'           => ['name' => 'Unstoppable',         'tier' => 'diamond', 'xp_reward' => 500, 'description' => '60-day study streak',                       'badge_color' => '#1fe290'],
        'questions_50'        => ['name' => 'Half Century',        'tier' => 'bronze',  'xp_reward' => 50,  'description' => 'Answer 50 questions',                       'badge_color' => '#cd7f32'],
        'questions_200'       => ['name' => 'Question Crusher',    'tier' => 'silver',  'xp_reward' => 100, 'description' => 'Answer 200 questions',                      'badge_color' => '#a8a8b8'],
        'questions_500'       => ['name' => 'Quiz Machine',        'tier' => 'gold',    'xp_reward' => 200, 'description' => 'Answer 500 questions',                      'badge_color' => '#f5a623'],
        'questions_1000'      => ['name' => 'Thousand Strong',     'tier' => 'diamond', 'xp_reward' => 500, 'description' => 'Answer 1,000 questions',                    'badge_color' => '#1fe290'],
        'math_mastery'        => ['name' => 'Math Master',         'tier' => 'gold',    'xp_reward' => 250, 'description' => 'Reach 80%+ accuracy in Math',               'badge_color' => '#f5a623'],
        'rw_mastery'          => ['name' => 'Word Wizard',         'tier' => 'gold',    'xp_reward' => 250, 'description' => 'Reach 80%+ accuracy in R&W',                'badge_color' => '#f5a623'],
        'practice_test_1'     => ['name' => 'Test Taker',          'tier' => 'bronze',  'xp_reward' => 100, 'description' => 'Complete your first practice test',         'badge_color' => '#cd7f32'],
        'practice_test_5'     => ['name' => 'Test Veteran',        'tier' => 'silver',  'xp_reward' => 200, 'description' => 'Complete 5 practice tests',                 'badge_color' => '#a8a8b8'],
        'score_improve_50'    => ['name' => 'Rising Star',         'tier' => 'silver',  'xp_reward' => 150, 'description' => 'Improve your score by 50+ points',          'badge_color' => '#a8a8b8'],
        'score_improve_100'   => ['name' => 'Breakout Player',     'tier' => 'gold',    'xp_reward' => 300, 'description' => 'Improve your score by 100+ points',         'badge_color' => '#f5a623'],
        'ai_tutor_10'         => ['name' => 'Curious Mind',        'tier' => 'bronze',  'xp_reward' => 30,  'description' => 'Have 10 AI tutor conversations',            'badge_color' => '#cd7f32'],
        'league_silver'       => ['name' => 'Silver Promotion',    'tier' => 'silver',  'xp_reward' => 100, 'description' => 'Reach Silver League',                       'badge_color' => '#a8a8b8'],
        'league_gold'         => ['name' => 'Golden Achievement',  'tier' => 'gold',    'xp_reward' => 200, 'description' => 'Reach Gold League',                         'badge_color' => '#f5a623'],
        'league_diamond'      => ['name' => 'Diamond Elite',       'tier' => 'diamond', 'xp_reward' => 500, 'description' => 'Reach Diamond League',                      'badge_color' => '#1fe290'],
    ];

    // ─────────────────────────────────────────────────────────────────
    //  Table guard
    // ─────────────────────────────────────────────────────────────────
    private static function tablesExist(): bool
    {
        try {
            Database::fetchAll("SELECT 1 FROM achievements LIMIT 1", []);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Get achievement ID by slug, auto-insert if missing
    // ─────────────────────────────────────────────────────────────────
    private static function getOrCreateId(string $key): int
    {
        if (!isset(self::$definitions[$key])) return 0;
        $def = self::$definitions[$key];

        $id = Database::fetchColumn(
            "SELECT id FROM achievements WHERE slug = ?",
            [$key]
        );

        if (!$id) {
            $id = Database::insert('achievements', [
                'slug'         => $key,
                'name'         => $def['name'],
                'description'  => $def['description'],
                'icon_url'     => null,
                'badge_color'  => $def['badge_color'],
                'xp_reward'    => $def['xp_reward'],
                'criteria_json'=> json_encode(['key' => $key, 'tier' => $def['tier']]),
                'is_active'    => 1,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
        }

        return (int) $id;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Unlock
    // ─────────────────────────────────────────────────────────────────
    public static function unlock(int $userId, string $key): ?array
    {
        if (!self::tablesExist()) return null;
        if (!isset(self::$definitions[$key])) return null;
        if (self::hasAchievement($userId, $key)) return null;

        try {
            $def           = self::$definitions[$key];
            $achievementId = self::getOrCreateId($key);
            if (!$achievementId) return null;

            Database::insert('user_achievements', [
                'user_id'        => $userId,
                'achievement_id' => $achievementId,
                'unlocked_at'    => date('Y-m-d H:i:s'),
            ]);

            // Award XP if XPSystem exists
            if (class_exists('XPSystem')) {
                XPSystem::award($userId, $def['xp_reward'], 'achievement_' . $key);
            }

            return [
                'key'         => $key,
                'name'        => $def['name'],
                'tier'        => $def['tier'],
                'xp_reward'   => $def['xp_reward'],
                'description' => $def['description'],
                'badge_color' => $def['badge_color'],
            ];
        } catch (PDOException $e) {
            error_log('[Achievement::unlock] ' . $e->getMessage());
            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Check if user already has achievement
    // ─────────────────────────────────────────────────────────────────
    public static function hasAchievement(int $userId, string $key): bool
    {
        try {
            $achievementId = Database::fetchColumn(
                "SELECT id FROM achievements WHERE slug = ?",
                [$key]
            );
            if (!$achievementId) return false;

            return (bool) Database::fetchColumn(
                "SELECT COUNT(*) FROM user_achievements WHERE user_id = ? AND achievement_id = ?",
                [$userId, $achievementId]
            );
        } catch (PDOException $e) {
            error_log('[Achievement::hasAchievement] ' . $e->getMessage());
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Recently unlocked badges (for dashboard widget)
    // ─────────────────────────────────────────────────────────────────
    public static function getRecentUnlocked(int $userId, int $limit = 3): array
    {
        if (!self::tablesExist()) return [];

        try {
            $rows = Database::fetchAll(
                "SELECT a.slug, a.name, a.icon_url, a.badge_color, a.xp_reward,
                        a.criteria_json, ua.unlocked_at
                 FROM user_achievements ua
                 JOIN achievements a ON a.id = ua.achievement_id
                 WHERE ua.user_id = ? AND a.is_active = 1
                 ORDER BY ua.unlocked_at DESC
                 LIMIT ?",
                [$userId, $limit]
            );

            // Enrich with local definition data (tier, description)
            foreach ($rows as &$row) {
                $def = self::$definitions[$row['slug']] ?? [];
                $row['tier']        = $def['tier']        ?? 'bronze';
                $row['description'] = $def['description'] ?? '';
                // icon_svg placeholder so dashboard template doesn't break
                $row['icon_svg']    = $row['icon_url']
                    ? '<img src="' . htmlspecialchars($row['icon_url']) . '" style="width:18px;height:18px">'
                    : self::getTierEmoji($row['tier']);
            }
            unset($row);

            return $rows;
        } catch (PDOException $e) {
            error_log('[Achievement::getRecentUnlocked] ' . $e->getMessage());
            return [];
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Unlocked count
    // ─────────────────────────────────────────────────────────────────
    public static function getUnlockedCount(int $userId): int
    {
        if (!self::tablesExist()) return 0;

        try {
            return (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM user_achievements WHERE user_id = ?",
                [$userId]
            );
        } catch (PDOException $e) {
            error_log('[Achievement::getUnlockedCount] ' . $e->getMessage());
            return 0;
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  All achievements with user unlock status
    // ─────────────────────────────────────────────────────────────────
    public static function getAll(int $userId): array
    {
        if (!self::tablesExist()) return [];

        try {
            $unlocked = Database::fetchAll(
                "SELECT a.slug, ua.unlocked_at
                 FROM user_achievements ua
                 JOIN achievements a ON a.id = ua.achievement_id
                 WHERE ua.user_id = ?",
                [$userId]
            );

            $unlockedMap = [];
            foreach ($unlocked as $u) {
                $unlockedMap[$u['slug']] = $u['unlocked_at'];
            }

            $all = [];
            foreach (self::$definitions as $key => $def) {
                $all[] = array_merge($def, [
                    'key'         => $key,
                    'unlocked'    => isset($unlockedMap[$key]),
                    'unlocked_at' => $unlockedMap[$key] ?? null,
                ]);
            }

            usort($all, function ($a, $b) {
                if ($a['unlocked'] !== $b['unlocked']) return $b['unlocked'] - $a['unlocked'];
                $tierOrder = ['diamond' => 4, 'gold' => 3, 'silver' => 2, 'bronze' => 1];
                return ($tierOrder[$b['tier']] ?? 0) - ($tierOrder[$a['tier']] ?? 0);
            });

            return $all;
        } catch (PDOException $e) {
            error_log('[Achievement::getAll] ' . $e->getMessage());
            return [];
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Check for new achievements after a quiz
    // ─────────────────────────────────────────────────────────────────
    public static function checkAfterQuiz(int $userId, float $score, int $correct, int $total): ?array
    {
        if (!self::tablesExist()) return null;

        try {
            $quizCount = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM quiz_attempts WHERE user_id = ? AND status = 'completed'",
                [$userId]
            );
            if ($quizCount === 1) {
                $result = self::unlock($userId, 'first_quiz');
                if ($result) return $result;
            }

            if ($score >= 100) {
                $result = self::unlock($userId, 'first_perfect');
                if ($result) return $result;
            }

            $totalAnswered = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM question_answers WHERE user_id = ?",
                [$userId]
            );
            foreach ([50, 200, 500, 1000] as $milestone) {
                if ($totalAnswered >= $milestone) {
                    $result = self::unlock($userId, "questions_{$milestone}");
                    if ($result) return $result;
                }
            }

            if (class_exists('StudyStreak')) {
                $streak = StudyStreak::get($userId);
                foreach ([3, 7, 14, 30, 60] as $days) {
                    if (($streak['current_streak'] ?? 0) >= $days) {
                        $result = self::unlock($userId, "streak_{$days}");
                        if ($result) return $result;
                    }
                }
            }

            return null;
        } catch (PDOException $e) {
            error_log('[Achievement::checkAfterQuiz] ' . $e->getMessage());
            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Helper: tier emoji fallback when no icon_url set
    // ─────────────────────────────────────────────────────────────────
    private static function getTierEmoji(string $tier): string
    {
        return match($tier) {
            'diamond' => '',
            'gold'    => '',
            'silver'  => '',
            default   => '',
        };
    }
}