<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · Onboarding
 *  /lib/Onboarding.php
 * ═══════════════════════════════════════════════════════════════════
 */

class Onboarding
{
    private static array $steps = ['welcome', 'setup', 'diagnostic', 'plan_preview'];

    private static array $stepNumbers = [
        'welcome'      => 1,
        'setup'        => 2,
        'diagnostic'   => 3,
        'plan_preview' => 4,
    ];

    // ─────────────────────────────────────────────────────────────────
    //  PROFILE HELPERS
    // ─────────────────────────────────────────────────────────────────

    private static function getProfile(int $userId): array
    {
        $profile = Database::fetch(
            'SELECT * FROM student_profiles WHERE user_id = ?',
            [$userId]
        );

        if (!$profile) {
            Database::insert('student_profiles', [
                'user_id'             => $userId,
                'target_score'        => 1200,
                'weekly_study_hours'  => 10,
                'onboarding_step'     => 0,
                'onboarding_complete' => 0,
            ]);
            $profile = Database::fetch(
                'SELECT * FROM student_profiles WHERE user_id = ?',
                [$userId]
            );
        }

        return $profile ?? [];
    }

    // ─────────────────────────────────────────────────────────────────
    //  PUBLIC: STATUS
    // ─────────────────────────────────────────────────────────────────

    public static function isComplete(int $userId): bool
    {
        // ① Fastest check: session (already in memory)
        if (!empty($_SESSION['onboarding_complete'])) {
            return true;
        }

        // ② Primary source: student_profiles
        $profile = self::getProfile($userId);
        if (!empty($profile['onboarding_complete'])) {
            $_SESSION['onboarding_complete'] = true; // sync session
            return true;
        }

        // ③ Fallback: users table
        $user = Database::fetch(
            'SELECT onboarding_complete FROM users WHERE id = ?',
            [$userId]
        );
        if (!empty($user['onboarding_complete'])) {
            $_SESSION['onboarding_complete'] = true; // sync session
            return true;
        }

        return false;
    }

    public static function getCurrentStep(int $userId): string
    {
        $profile = self::getProfile($userId);
        $stepNum = (int) ($profile['onboarding_step'] ?? 0);

        foreach (self::$stepNumbers as $name => $num) {
            if ($num > $stepNum) {
                return $name;
            }
        }

        return 'complete';
    }

    public static function getProgressPercent(int $userId): int
    {
        $profile   = self::getProfile($userId);
        $stepNum   = (int) ($profile['onboarding_step'] ?? 0);
        $total     = count(self::$steps);
        $completed = min($stepNum, $total);
        return (int) round(($completed / $total) * 100);
    }

    // ─────────────────────────────────────────────────────────────────
    //  PUBLIC: getData
    // ─────────────────────────────────────────────────────────────────

    public static function getData(int $userId): array
    {
        $profile = self::getProfile($userId);

        $diagnostic = Database::fetch(
            'SELECT * FROM diagnostic_attempts WHERE user_id = ?',
            [$userId]
        );

        $stepNum = (int) ($profile['onboarding_step'] ?? 0);

        return [
            'welcome_completed'      => $stepNum >= 1,
            'setup_completed'        => $stepNum >= 2,
            'diagnostic_completed'   => $stepNum >= 3,
            'plan_preview_completed' => $stepNum >= 4,

            'setup' => [
                'test_date'    => $profile['test_date']          ?? null,
                'target_score' => $profile['target_score']       ?? 1200,
                'study_hours'  => $profile['weekly_study_hours'] ?? 10,
            ],

            'diagnostic' => $diagnostic ? [
                'math_theta' => $diagnostic['math_theta'] ?? null,
                'rw_theta'   => $diagnostic['rw_theta']   ?? null,
                'answers'    => !empty($diagnostic['answers_json'])
                                    ? json_decode($diagnostic['answers_json'], true)
                                    : [],
            ] : null,

            'onboarding_step'     => $stepNum,
            'onboarding_complete' => (bool) ($profile['onboarding_complete'] ?? false),
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    //  PUBLIC: saveStep
    // ─────────────────────────────────────────────────────────────────

    public static function saveStep(int $userId, string $step, array $data): void
    {
        self::getProfile($userId);

        $stepNum = self::$stepNumbers[$step] ?? 0;

        $currentStep = (int) (Database::fetchColumn(
            'SELECT onboarding_step FROM student_profiles WHERE user_id = ?',
            [$userId]
        ) ?? 0);

        $update = [];

        if ($stepNum > $currentStep) {
            $update['onboarding_step'] = $stepNum;
        }

        switch ($step) {

            case 'welcome':
                break;

            case 'setup':
                if (!empty($data['test_date'])) {
                    $update['test_date'] = $data['test_date'];
                }
                if (isset($data['target_score'])) {
                    $update['target_score'] = (int) $data['target_score'];
                }
                if (isset($data['study_hours'])) {
                    $update['weekly_study_hours'] = (float) $data['study_hours'];
                }
                if (!empty($data['test_date']) || isset($data['target_score'])) {
                    $userUpdate = ['updated_at' => date('Y-m-d H:i:s')];
                    if (!empty($data['test_date']))   $userUpdate['test_date']    = $data['test_date'];
                    if (isset($data['target_score'])) $userUpdate['target_score'] = (int) $data['target_score'];
                    if (isset($data['study_hours']))  $userUpdate['weekly_study_hours'] = (float) $data['study_hours'];
                    Database::update('users', $userUpdate, ['id' => $userId]);
                }
                break;

            case 'diagnostic':
                if (isset($data['math_theta'])) {
                    $update['math_theta'] = (float) $data['math_theta'];
                }
                if (isset($data['rw_theta'])) {
                    $update['rw_theta'] = (float) $data['rw_theta'];
                }
                break;

            case 'plan_preview':
                // Delegate entirely to markComplete so session is also updated
                self::markComplete($userId);
                return;
        }

        if (!empty($update)) {
            Database::update('student_profiles', $update, ['user_id' => $userId]);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  PUBLIC: markComplete
    // ─────────────────────────────────────────────────────────────────

    public static function markComplete(int $userId): void
    {
        // ① student_profiles
        Database::update('student_profiles', [
            'onboarding_complete' => 1,
            'onboarding_step'     => 4,
        ], ['user_id' => $userId]);

        // ② users table
        try {
    $pdo  = Database::connect();
    $stmt = $pdo->prepare(
        'UPDATE users SET onboarding_complete = 1, updated_at = NOW() WHERE id = ?'
    );
    $stmt->execute([$userId]);
    error_log('markComplete: rows affected = ' . $stmt->rowCount() . ' for user ' . $userId);
} catch (Throwable $e) {
    error_log('markComplete users UPDATE failed: ' . $e->getMessage());
}

// Force session update immediately
$_SESSION['onboarding_complete'] = true;

        // ③ Session — critical: without this the redirect guard fails
        $_SESSION['onboarding_complete'] = true;

        // ④ Generate first AI study schedule
        try {
            Schedule::generate($userId);
        } catch (Throwable $e) {
            error_log('Schedule::generate failed after onboarding: ' . $e->getMessage());
        }

        // ⑤ Award XP + achievement
        try {
            if (class_exists('Achievement')) {
                Achievement::unlock($userId, 'onboarding_complete');
            }
            if (class_exists('XPSystem')) {
                XPSystem::award($userId, 50, 'onboarding_complete');
            }
        } catch (Throwable $e) {
            error_log('Onboarding XP/achievement award failed: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  PUBLIC: processDiagnostic
    // ─────────────────────────────────────────────────────────────────

    public static function processDiagnostic(int $userId, array $results): array
    {
        $mathCorrect = 0; $mathTotal = 0;
        $rwCorrect   = 0; $rwTotal   = 0;

        foreach ($results as $r) {
            if (($r['subject'] ?? '') === 'math') {
                $mathTotal++;
                if (!empty($r['correct'])) $mathCorrect++;
            } else {
                $rwTotal++;
                if (!empty($r['correct'])) $rwCorrect++;
            }
        }

        $mathPct = $mathTotal > 0 ? round(($mathCorrect / $mathTotal) * 100) : 50;
        $rwPct   = $rwTotal   > 0 ? round(($rwCorrect   / $rwTotal)   * 100) : 50;

        $mathTheta = self::pctToTheta($mathPct);
        $rwTheta   = self::pctToTheta($rwPct);

        $mathLevel = $mathPct >= 80 ? 'advanced' : ($mathPct >= 50 ? 'intermediate' : 'foundational');
        $rwLevel   = $rwPct   >= 80 ? 'advanced' : ($rwPct   >= 50 ? 'intermediate' : 'foundational');

        $existing = Database::fetch(
            'SELECT id FROM diagnostic_attempts WHERE user_id = ?',
            [$userId]
        );

        $diagData = [
            'math_theta'   => $mathTheta,
            'rw_theta'     => $rwTheta,
            'answers_json' => json_encode($results),
            'completed_at' => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            Database::update('diagnostic_attempts', $diagData, ['user_id' => $userId]);
        } else {
            Database::insert('diagnostic_attempts', array_merge(['user_id' => $userId], $diagData));
        }

        self::getProfile($userId); // ensures the row EXISTS before we update it
Database::update('student_profiles', [
    'onboarding_complete' => 1,
    'onboarding_step'     => 4,
], ['user_id' => $userId]);

        self::saveStep($userId, 'diagnostic', [
            'math_theta' => $mathTheta,
            'rw_theta'   => $rwTheta,
        ]);

        return [
            'math_score' => $mathPct,
            'rw_score'   => $rwPct,
            'math_level' => $mathLevel,
            'rw_level'   => $rwLevel,
            'math_theta' => $mathTheta,
            'rw_theta'   => $rwTheta,
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    //  PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────────

    private static function pctToTheta(float $pct): float
    {
        return round((($pct - 50) / 50) * 3, 2);
    }
}