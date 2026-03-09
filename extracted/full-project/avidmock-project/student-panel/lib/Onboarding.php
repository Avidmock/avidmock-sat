<?php
/**
 * AVIDMOCK Onboarding v5.2
 * KEY FIX: markComplete() uses raw SQL fallback to guarantee
 * users.onboarding_complete is always written.
 */
class Onboarding
{
    private static array $steps = ['welcome', 'setup', 'diagnostic', 'plan_preview'];
    private static array $stepNumbers = ['welcome'=>1,'setup'=>2,'diagnostic'=>3,'plan_preview'=>4];

    private static function getProfile(int $userId): array
    {
        $profile = Database::fetch('SELECT * FROM student_profiles WHERE user_id = ?', [$userId]);
        if (!$profile) {
            try {
                Database::insert('student_profiles', [
                    'user_id' => $userId, 'target_score' => 1200,
                    'weekly_study_hours' => 10, 'onboarding_step' => 0, 'onboarding_complete' => 0,
                ]);
            } catch (Throwable $e) { error_log('getProfile insert: ' . $e->getMessage()); }
            $profile = Database::fetch('SELECT * FROM student_profiles WHERE user_id = ?', [$userId]);
        }
        return $profile ?? [];
    }

    public static function isComplete(int $userId): bool
    {
        if (!empty($_SESSION['onboarding_complete'])) return true;

        $user = Database::fetch('SELECT onboarding_complete FROM users WHERE id = ? LIMIT 1', [$userId]);
        if (!empty($user['onboarding_complete'])) {
            $_SESSION['onboarding_complete'] = true;
            return true;
        }

        $profile = self::getProfile($userId);
        if (!empty($profile['onboarding_complete'])) {
            try {
                Database::execute('UPDATE users SET onboarding_complete = 1, updated_at = NOW() WHERE id = ?', [$userId]);
            } catch (Throwable $e) { error_log('isComplete sync: ' . $e->getMessage()); }
            $_SESSION['onboarding_complete'] = true;
            return true;
        }

        return false;
    }

    public static function getCurrentStep(int $userId): string
    {
        $profile = self::getProfile($userId);
        $stepNum = (int) ($profile['onboarding_step'] ?? 0);
        foreach (self::$stepNumbers as $name => $num) {
            if ($num > $stepNum) return $name;
        }
        return 'complete';
    }

    public static function getProgressPercent(int $userId): int
    {
        $profile = self::getProfile($userId);
        $stepNum = (int) ($profile['onboarding_step'] ?? 0);
        $total = count(self::$steps);
        return (int) round((min($stepNum, $total) / $total) * 100);
    }

    public static function getData(int $userId): array
    {
        $profile    = self::getProfile($userId);
        $diagnostic = Database::fetch('SELECT * FROM diagnostic_attempts WHERE user_id = ? LIMIT 1', [$userId]);
        $stepNum    = (int) ($profile['onboarding_step'] ?? 0);
        return [
            'welcome_completed'      => $stepNum >= 1,
            'setup_completed'        => $stepNum >= 2,
            'diagnostic_completed'   => $stepNum >= 3,
            'plan_preview_completed' => $stepNum >= 4,
            'setup' => [
                'test_date'    => $profile['test_date'] ?? null,
                'target_score' => $profile['target_score'] ?? 1200,
                'study_hours'  => $profile['weekly_study_hours'] ?? 10,
            ],
            'diagnostic' => $diagnostic ? [
                'math_theta' => $diagnostic['math_theta'] ?? null,
                'rw_theta'   => $diagnostic['rw_theta'] ?? null,
                'answers'    => !empty($diagnostic['answers_json']) ? json_decode($diagnostic['answers_json'], true) : [],
            ] : null,
            'onboarding_step'     => $stepNum,
            'onboarding_complete' => (bool) ($profile['onboarding_complete'] ?? false),
        ];
    }

    public static function saveStep(int $userId, string $step, array $data): void
    {
        self::getProfile($userId);
        $stepNum = self::$stepNumbers[$step] ?? 0;
        $currentStep = (int) (Database::fetchColumn('SELECT onboarding_step FROM student_profiles WHERE user_id = ?', [$userId]) ?? 0);
        $profileUpdate = [];
        if ($stepNum > $currentStep) $profileUpdate['onboarding_step'] = $stepNum;

        switch ($step) {
            case 'welcome': break;
            case 'setup':
                if (!empty($data['test_date']))      $profileUpdate['test_date'] = $data['test_date'];
                if (isset($data['target_score']))    $profileUpdate['target_score'] = (int) $data['target_score'];
                if (isset($data['study_hours']))     $profileUpdate['weekly_study_hours'] = (float) $data['study_hours'];
                self::syncSetupToUsers($userId, $data);
                break;
            case 'diagnostic':
                if (isset($data['math_theta'])) $profileUpdate['math_theta'] = (float) $data['math_theta'];
                if (isset($data['rw_theta']))   $profileUpdate['rw_theta']   = (float) $data['rw_theta'];
                break;
            case 'plan_preview':
                self::markComplete($userId);
                return;
        }

        if (!empty($profileUpdate)) {
            Database::update('student_profiles', $profileUpdate, ['user_id' => $userId]);
        }
    }

    public static function markComplete(int $userId): void
    {
        // 1. student_profiles
        try {
            Database::update('student_profiles', ['onboarding_complete' => 1, 'onboarding_step' => 4], ['user_id' => $userId]);
        } catch (Throwable $e) { error_log('markComplete profiles: ' . $e->getMessage()); }

        // 2. users table via Database::update()
        $written = false;
        try {
            $affected = Database::update('users', ['onboarding_complete' => 1, 'updated_at' => date('Y-m-d H:i:s')], ['id' => $userId]);
            error_log('markComplete: Database::update affected=' . $affected . ' userId=' . $userId);
            if ($affected > 0) $written = true;
        } catch (Throwable $e) { error_log('markComplete update: ' . $e->getMessage()); }

        // 3. Raw SQL fallback if update() returned 0 rows
        if (!$written) {
            try {
                $rows = Database::execute('UPDATE users SET onboarding_complete = 1, updated_at = NOW() WHERE id = ?', [$userId]);
                error_log('markComplete: raw fallback affected=' . $rows . ' userId=' . $userId);
            } catch (Throwable $e) { error_log('markComplete raw fallback: ' . $e->getMessage()); }
        }

        // 4. Verify
        try {
            $check = Database::fetch('SELECT onboarding_complete FROM users WHERE id = ? LIMIT 1', [$userId]);
            if (empty($check['onboarding_complete'])) {
                error_log('markComplete CRITICAL: users.onboarding_complete still 0 for userId=' . $userId);
            } else {
                error_log('markComplete VERIFIED: onboarding_complete=1 for userId=' . $userId);
            }
        } catch (Throwable $e) { error_log('markComplete verify: ' . $e->getMessage()); }

        // 5. Session
        $_SESSION['onboarding_complete'] = true;

        // 6. Schedule
        try { if (class_exists('Schedule')) Schedule::generate($userId); } catch (Throwable $e) { error_log('markComplete schedule: ' . $e->getMessage()); }

        // 7. XP + achievements
        try {
            if (class_exists('Achievement')) Achievement::unlock($userId, 'onboarding_complete');
            if (class_exists('XPSystem'))    XPSystem::award($userId, 50, 'onboarding_complete');
        } catch (Throwable $e) { error_log('markComplete xp: ' . $e->getMessage()); }
    }

    public static function processDiagnostic(int $userId, array $results): array
    {
        $mathCorrect = 0; $mathTotal = 0; $rwCorrect = 0; $rwTotal = 0;
        foreach ($results as $r) {
            if (($r['subject'] ?? '') === 'math') { $mathTotal++; if (!empty($r['correct'])) $mathCorrect++; }
            else                                  { $rwTotal++;   if (!empty($r['correct'])) $rwCorrect++; }
        }
        $mathPct = $mathTotal > 0 ? round(($mathCorrect / $mathTotal) * 100) : 50;
        $rwPct   = $rwTotal   > 0 ? round(($rwCorrect   / $rwTotal)   * 100) : 50;
        $mathTheta = self::pctToTheta($mathPct);
        $rwTheta   = self::pctToTheta($rwPct);
        $mathLevel = $mathPct >= 80 ? 'advanced' : ($mathPct >= 50 ? 'intermediate' : 'foundational');
        $rwLevel   = $rwPct   >= 80 ? 'advanced' : ($rwPct   >= 50 ? 'intermediate' : 'foundational');

        $diagData = ['math_theta'=>$mathTheta,'rw_theta'=>$rwTheta,'answers_json'=>json_encode($results),'completed_at'=>date('Y-m-d H:i:s')];
        try {
            $existing = Database::fetch('SELECT id FROM diagnostic_attempts WHERE user_id = ? LIMIT 1', [$userId]);
            if ($existing) Database::update('diagnostic_attempts', $diagData, ['user_id' => $userId]);
            else           Database::insert('diagnostic_attempts', array_merge(['user_id' => $userId], $diagData));
        } catch (Throwable $e) { error_log('processDiagnostic attempts: ' . $e->getMessage()); }

        try {
            self::getProfile($userId);
            Database::update('student_profiles', ['math_theta'=>$mathTheta,'rw_theta'=>$rwTheta], ['user_id'=>$userId]);
        } catch (Throwable $e) { error_log('processDiagnostic theta: ' . $e->getMessage()); }

        self::saveStep($userId, 'diagnostic', ['math_theta'=>$mathTheta,'rw_theta'=>$rwTheta]);
        self::markComplete($userId);

        return ['math_score'=>$mathPct,'rw_score'=>$rwPct,'math_level'=>$mathLevel,'rw_level'=>$rwLevel,'math_theta'=>$mathTheta,'rw_theta'=>$rwTheta];
    }

    private static function pctToTheta(float $pct): float
    {
        return round((($pct - 50) / 50) * 3, 2);
    }

    private static function syncSetupToUsers(int $userId, array $data): void
    {
        $update = ['updated_at' => date('Y-m-d H:i:s')];
        if (!empty($data['test_date']))   $update['test_date']           = $data['test_date'];
        if (isset($data['target_score'])) $update['target_score']        = (int) $data['target_score'];
        if (isset($data['study_hours']))  $update['weekly_study_hours']  = (float) $data['study_hours'];
        if (count($update) > 1) {
            try { Database::update('users', $update, ['id' => $userId]); } catch (Throwable $e) { error_log('syncSetupToUsers: ' . $e->getMessage()); }
        }
    }
}