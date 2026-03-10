<?php
/**
 * =====================================================================
 *  AVIDMOCK SAT -- SocialShare
 *  Shareable score cards, referral codes, and viral sharing engine.
 *
 *  DATABASE TABLES:
 *
 *  CREATE TABLE share_tokens (
 *      id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *      user_id     INT UNSIGNED NOT NULL,
 *      type        VARCHAR(30) NOT NULL DEFAULT 'scorecard',
 *      token       VARCHAR(64) NOT NULL UNIQUE,
 *      data_json   MEDIUMTEXT DEFAULT NULL,
 *      views       INT UNSIGNED DEFAULT 0,
 *      created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
 *      expires_at  DATETIME DEFAULT NULL,
 *      INDEX idx_token (token),
 *      INDEX idx_user_type (user_id, type)
 *  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 *
 *  CREATE TABLE referral_codes (
 *      id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *      user_id     INT UNSIGNED NOT NULL UNIQUE,
 *      code        VARCHAR(10) NOT NULL UNIQUE,
 *      uses        INT UNSIGNED DEFAULT 0,
 *      created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
 *      INDEX idx_code (code)
 *  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 *
 *  CREATE TABLE referral_events (
 *      id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *      referrer_id     INT UNSIGNED NOT NULL,
 *      referred_id     INT UNSIGNED NOT NULL,
 *      reward_type     VARCHAR(50) DEFAULT NULL,
 *      reward_given    TINYINT(1) DEFAULT 0,
 *      created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
 *      UNIQUE KEY uq_referred (referred_id),
 *      INDEX idx_referrer (referrer_id)
 *  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 * =====================================================================
 */

class SocialShare
{
    // ── Referral reward tiers ───────────────────────────────────
    private const REWARD_TIERS = [
        1  => ['type' => 'xp_bonus',       'label' => '+100 XP',              'xp' => 100],
        3  => ['type' => 'pro_week',        'label' => '1 Week Free Pro',      'xp' => 200],
        5  => ['type' => 'pro_month',       'label' => '1 Month Free Pro',     'xp' => 500],
        10 => ['type' => 'pro_lifetime',    'label' => 'Free Pro for Life',    'xp' => 1000],
    ];

    // ── League thresholds & labels ──────────────────────────────
    private const LEAGUES = [
        'bronze'  => ['min' => 0,    'label' => 'Bronze',  'color' => '#cd7f32', 'emoji' => "\xF0\x9F\xA5\x89"],
        'silver'  => ['min' => 300,  'label' => 'Silver',  'color' => '#a8a8b8', 'emoji' => "\xF0\x9F\xA5\x88"],
        'gold'    => ['min' => 750,  'label' => 'Gold',    'color' => '#f5a623', 'emoji' => "\xF0\x9F\xA5\x87"],
        'diamond' => ['min' => 1500, 'label' => 'Diamond', 'color' => '#1fe290', 'emoji' => "\xF0\x9F\x92\x8E"],
    ];

    // ── Generate score card data ────────────────────────────────
    public static function generateScoreCard(int $userId): array
    {
        $user = Database::fetch(
            "SELECT id, first_name, last_name, avatar_url, created_at
             FROM users WHERE id = ?",
            [$userId]
        );
        if (!$user) throw new RuntimeException('User not found');

        $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'Student';
        $firstName = $user['first_name'] ?? 'Student';

        // Get XP and level
        $xpData = Database::fetch(
            "SELECT COALESCE(xp, 0) AS xp, COALESCE(level, 1) AS level FROM user_xp WHERE user_id = ?",
            [$userId]
        );
        $totalXp = (int)($xpData['xp'] ?? 0);

        // Get streak
        $streak = 0;
        try {
            $streakRow = Database::fetch(
                "SELECT COALESCE(current_streak, 0) AS current_streak FROM study_streaks WHERE user_id = ?",
                [$userId]
            );
            $streak = (int)($streakRow['current_streak'] ?? 0);
        } catch (\Throwable $e) {}

        // Get predicted SAT score
        $predictedScore = 0;
        try {
            $attempt = Database::fetch(
                "SELECT total_score FROM practice_test_attempts
                 WHERE user_id = ? AND status = 'submitted' AND total_score IS NOT NULL
                 ORDER BY submitted_at DESC LIMIT 1",
                [$userId]
            );
            $predictedScore = (int)($attempt['total_score'] ?? 0);
        } catch (\Throwable $e) {}

        // Determine league
        $league = 'bronze';
        foreach (array_reverse(self::LEAGUES, true) as $key => $l) {
            if ($totalXp >= $l['min']) {
                $league = $key;
                break;
            }
        }
        $leagueData = self::LEAGUES[$league];

        // Weekly improvement
        $weeklyImprovement = 0;
        try {
            $weekAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
            $weekXp = (int)Database::fetchColumn(
                "SELECT COALESCE(SUM(xp_earned), 0) FROM xp_events WHERE user_id = ? AND created_at >= ?",
                [$userId, $weekAgo]
            );
            $weeklyImprovement = $weekXp;
        } catch (\Throwable $e) {}

        // Questions practiced
        $questionsPracticed = 0;
        try {
            $questionsPracticed = (int)Database::fetchColumn(
                "SELECT COUNT(*) FROM quiz_answers WHERE user_id = ?",
                [$userId]
            ) ?: 0;
        } catch (\Throwable $e) {}

        return [
            'user_id'             => $userId,
            'name'                => $name,
            'first_name'          => $firstName,
            'avatar_url'          => $user['avatar_url'] ?? '',
            'predicted_score'     => $predictedScore,
            'total_xp'            => $totalXp,
            'current_streak'      => $streak,
            'league'              => $league,
            'league_label'        => $leagueData['label'],
            'league_color'        => $leagueData['color'],
            'league_emoji'        => $leagueData['emoji'],
            'weekly_xp'           => $weeklyImprovement,
            'questions_practiced' => $questionsPracticed,
            'member_since'        => $user['created_at'],
        ];
    }

    // ── Generate a shareable URL token ──────────────────────────
    public static function generateShareUrl(int $userId, string $type = 'scorecard', array $extraData = []): string
    {
        // Check for existing valid token
        $existing = Database::fetch(
            "SELECT token FROM share_tokens
             WHERE user_id = ? AND type = ?
               AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY created_at DESC LIMIT 1",
            [$userId, $type]
        );

        if ($existing) {
            // Update the data
            Database::execute(
                "UPDATE share_tokens SET data_json = ? WHERE token = ?",
                [json_encode($extraData), $existing['token']]
            );
            return $existing['token'];
        }

        $token = bin2hex(random_bytes(16));

        Database::insert('share_tokens', [
            'user_id'    => $userId,
            'type'       => $type,
            'token'      => $token,
            'data_json'  => json_encode($extraData),
            'views'      => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);

        return $token;
    }

    // ── Get share token data ────────────────────────────────────
    public static function getShareData(string $token): ?array
    {
        $row = Database::fetch(
            "SELECT * FROM share_tokens WHERE token = ? AND (expires_at IS NULL OR expires_at > NOW())",
            [$token]
        );
        if (!$row) return null;

        // Increment view count
        Database::execute(
            "UPDATE share_tokens SET views = views + 1 WHERE id = ?",
            [$row['id']]
        );

        $row['data'] = json_decode($row['data_json'] ?? '{}', true);
        return $row;
    }

    // ── Get or create referral code ─────────────────────────────
    public static function getReferralCode(int $userId): string
    {
        $existing = Database::fetch(
            "SELECT code FROM referral_codes WHERE user_id = ?",
            [$userId]
        );

        if ($existing) return $existing['code'];

        // Generate unique referral code
        $user = Database::fetch("SELECT first_name FROM users WHERE id = ?", [$userId]);
        $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $user['first_name'] ?? 'AVM'), 0, 4));
        $code = $prefix . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        // Ensure unique
        $attempts = 0;
        while ($attempts < 20) {
            $exists = Database::fetchColumn(
                "SELECT COUNT(*) FROM referral_codes WHERE code = ?",
                [$code]
            );
            if (!$exists) break;
            $code = $prefix . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $attempts++;
        }

        Database::insert('referral_codes', [
            'user_id'    => $userId,
            'code'       => $code,
            'uses'       => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $code;
    }

    // ── Process a referral (called during signup) ───────────────
    public static function processReferral(string $code, int $newUserId): bool
    {
        $code = strtoupper(trim($code));
        $ref = Database::fetch(
            "SELECT * FROM referral_codes WHERE code = ?",
            [$code]
        );
        if (!$ref) return false;

        $referrerId = (int)$ref['user_id'];

        // Don't refer yourself
        if ($referrerId === $newUserId) return false;

        // Check if already referred
        $exists = Database::fetch(
            "SELECT id FROM referral_events WHERE referred_id = ?",
            [$newUserId]
        );
        if ($exists) return false;

        // Record the event
        Database::insert('referral_events', [
            'referrer_id' => $referrerId,
            'referred_id' => $newUserId,
            'reward_type' => null,
            'reward_given'=> 0,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        // Increment referral count
        Database::execute(
            "UPDATE referral_codes SET uses = uses + 1 WHERE id = ?",
            [$ref['id']]
        );

        // Check and award referrer rewards
        self::checkAndAwardRewards($referrerId);

        // Award XP to referred user too
        try {
            $existing = Database::fetch("SELECT id FROM user_xp WHERE user_id = ?", [$newUserId]);
            if ($existing) {
                Database::execute("UPDATE user_xp SET xp = xp + 50 WHERE user_id = ?", [$newUserId]);
            } else {
                Database::insert('user_xp', ['user_id' => $newUserId, 'xp' => 50, 'level' => 1]);
            }
        } catch (\Throwable $e) {}

        return true;
    }

    // ── Check and award referral rewards ────────────────────────
    private static function checkAndAwardRewards(int $userId): void
    {
        $totalReferrals = (int)Database::fetchColumn(
            "SELECT COUNT(*) FROM referral_events WHERE referrer_id = ?",
            [$userId]
        );

        foreach (self::REWARD_TIERS as $threshold => $reward) {
            if ($totalReferrals >= $threshold) {
                // Check if this reward was already given
                $alreadyGiven = Database::fetch(
                    "SELECT id FROM referral_events
                     WHERE referrer_id = ? AND reward_type = ? AND reward_given = 1
                     LIMIT 1",
                    [$userId, $reward['type']]
                );

                if (!$alreadyGiven) {
                    // Mark the milestone referral with the reward
                    Database::execute(
                        "UPDATE referral_events
                         SET reward_type = ?, reward_given = 1
                         WHERE referrer_id = ?
                         ORDER BY created_at ASC
                         LIMIT 1",
                        [$reward['type'], $userId]
                    );

                    // Award XP
                    try {
                        Database::execute(
                            "UPDATE user_xp SET xp = xp + ? WHERE user_id = ?",
                            [$reward['xp'], $userId]
                        );
                    } catch (\Throwable $e) {}
                }
            }
        }
    }

    // ── Get referral stats ──────────────────────────────────────
    public static function getReferralStats(int $userId): array
    {
        $code = self::getReferralCode($userId);

        $totalReferred = (int)Database::fetchColumn(
            "SELECT COUNT(*) FROM referral_events WHERE referrer_id = ?",
            [$userId]
        ) ?: 0;

        // Active = those who have logged in within 7 days
        $activeReferred = 0;
        try {
            $activeReferred = (int)Database::fetchColumn(
                "SELECT COUNT(*) FROM referral_events re
                 JOIN users u ON u.id = re.referred_id
                 WHERE re.referrer_id = ? AND u.last_login_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                [$userId]
            ) ?: 0;
        } catch (\Throwable $e) {}

        // What rewards have been earned
        $rewardsEarned = [];
        foreach (self::REWARD_TIERS as $threshold => $reward) {
            $rewardsEarned[] = [
                'threshold' => $threshold,
                'type'      => $reward['type'],
                'label'     => $reward['label'],
                'earned'    => $totalReferred >= $threshold,
            ];
        }

        // Next reward
        $nextReward = null;
        foreach (self::REWARD_TIERS as $threshold => $reward) {
            if ($totalReferred < $threshold) {
                $nextReward = [
                    'threshold'  => $threshold,
                    'label'      => $reward['label'],
                    'remaining'  => $threshold - $totalReferred,
                ];
                break;
            }
        }

        // Recent referrals
        $recentReferrals = [];
        try {
            $recentReferrals = Database::fetchAll(
                "SELECT u.first_name, re.created_at
                 FROM referral_events re
                 JOIN users u ON u.id = re.referred_id
                 WHERE re.referrer_id = ?
                 ORDER BY re.created_at DESC
                 LIMIT 10",
                [$userId]
            );
        } catch (\Throwable $e) {}

        return [
            'code'              => $code,
            'total_referred'    => $totalReferred,
            'active_referred'   => $activeReferred,
            'rewards'           => $rewardsEarned,
            'next_reward'       => $nextReward,
            'recent_referrals'  => $recentReferrals,
            'share_url'         => STUDENT_URL . '/referrals/?ref=' . $code,
        ];
    }

    // ── Top referrers leaderboard ───────────────────────────────
    public static function getTopReferrers(int $limit = 10): array
    {
        try {
            return Database::fetchAll(
                "SELECT rc.user_id, rc.uses, u.first_name, u.avatar_url
                 FROM referral_codes rc
                 JOIN users u ON u.id = rc.user_id
                 WHERE rc.uses > 0
                 ORDER BY rc.uses DESC
                 LIMIT ?",
                [$limit]
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ── Get reward tiers (for display) ──────────────────────────
    public static function getRewardTiers(): array
    {
        return self::REWARD_TIERS;
    }

    // ── Get league info ─────────────────────────────────────────
    public static function getLeagueInfo(string $league): array
    {
        return self::LEAGUES[$league] ?? self::LEAGUES['bronze'];
    }
}
