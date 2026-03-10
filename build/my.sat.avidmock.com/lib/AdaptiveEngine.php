<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · AdaptiveEngine
 *  Core adaptive learning engine using simplified Item Response Theory.
 *
 *  IRT Model:
 *    P(correct) = 1 / (1 + exp(-1.7 * a * (theta_student - theta_question)))
 *    theta_new  = theta_old + K * (outcome - P(correct))
 *    K = 0.4 (first 10 Qs), 0.2 (11–50 Qs), 0.1 (50+ Qs)
 *
 *  Required tables (create if not exist):
 *
 *  CREATE TABLE IF NOT EXISTS adaptive_student_state (
 *    id INT AUTO_INCREMENT PRIMARY KEY,
 *    user_id INT NOT NULL,
 *    domain VARCHAR(30) NOT NULL DEFAULT 'overall',
 *    ability_estimate FLOAT NOT NULL DEFAULT 0.0,
 *    questions_answered INT NOT NULL DEFAULT 0,
 *    correct_count INT NOT NULL DEFAULT 0,
 *    total_time_spent INT NOT NULL DEFAULT 0,
 *    streak_correct INT NOT NULL DEFAULT 0,
 *    streak_incorrect INT NOT NULL DEFAULT 0,
 *    last_updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 *    UNIQUE KEY uq_user_domain (user_id, domain)
 *  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 *
 *  CREATE TABLE IF NOT EXISTS adaptive_question_params (
 *    question_id INT PRIMARY KEY,
 *    difficulty_estimate FLOAT NOT NULL DEFAULT 0.0,
 *    discrimination FLOAT NOT NULL DEFAULT 1.0,
 *    guessing_param FLOAT NOT NULL DEFAULT 0.25,
 *    times_shown INT NOT NULL DEFAULT 0,
 *    times_correct INT NOT NULL DEFAULT 0,
 *    last_calibrated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
 *  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 *
 *  CREATE TABLE IF NOT EXISTS adaptive_response_log (
 *    id INT AUTO_INCREMENT PRIMARY KEY,
 *    user_id INT NOT NULL,
 *    question_id INT NOT NULL,
 *    domain VARCHAR(30) DEFAULT NULL,
 *    is_correct TINYINT(1) NOT NULL DEFAULT 0,
 *    time_spent INT NOT NULL DEFAULT 0,
 *    ability_before FLOAT NOT NULL DEFAULT 0.0,
 *    ability_after FLOAT NOT NULL DEFAULT 0.0,
 *    p_correct FLOAT NOT NULL DEFAULT 0.5,
 *    flag VARCHAR(20) DEFAULT NULL,
 *    answered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *    INDEX idx_user_time (user_id, answered_at),
 *    INDEX idx_question (question_id)
 *  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 *
 * ═══════════════════════════════════════════════════════════════════
 */

class AdaptiveEngine
{
    /* ── SAT Math domains ─────────────────────────────────────── */
    private const DOMAINS = [
        'algebra'         => 'Algebra',
        'advanced_math'   => 'Advanced Math',
        'problem_solving' => 'Problem Solving & Data Analysis',
        'geometry'        => 'Geometry & Trigonometry',
    ];

    /* ── Difficulty mapping from text labels ───────────────────── */
    private const DIFFICULTY_MAP = [
        'easy'   => -1.0,
        'medium' =>  0.0,
        'hard'   =>  1.0,
    ];

    /* ── Zone of Proximal Development bounds ───────────────────── */
    private const ZPD_LOW  = 0.55;
    private const ZPD_HIGH = 0.80;
    private const ZPD_IDEAL = 0.70;

    /* ── Quiz difficulty mix ───────────────────────────────────── */
    private const MIX_EASY    = 0.20;
    private const MIX_ATLEVEL = 0.60;
    private const MIX_STRETCH = 0.20;

    private static bool $tablesChecked = false;

    /* ================================================================
     *  ENSURE TABLES
     * ================================================================ */
    private static function ensureTables(): void
    {
        if (self::$tablesChecked) return;

        $pdo = Database::connect();

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS adaptive_student_state (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                domain VARCHAR(30) NOT NULL DEFAULT 'overall',
                ability_estimate FLOAT NOT NULL DEFAULT 0.0,
                questions_answered INT NOT NULL DEFAULT 0,
                correct_count INT NOT NULL DEFAULT 0,
                total_time_spent INT NOT NULL DEFAULT 0,
                streak_correct INT NOT NULL DEFAULT 0,
                streak_incorrect INT NOT NULL DEFAULT 0,
                last_updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_user_domain (user_id, domain)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS adaptive_question_params (
                question_id INT PRIMARY KEY,
                difficulty_estimate FLOAT NOT NULL DEFAULT 0.0,
                discrimination FLOAT NOT NULL DEFAULT 1.0,
                guessing_param FLOAT NOT NULL DEFAULT 0.25,
                times_shown INT NOT NULL DEFAULT 0,
                times_correct INT NOT NULL DEFAULT 0,
                last_calibrated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS adaptive_response_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                question_id INT NOT NULL,
                domain VARCHAR(30) DEFAULT NULL,
                is_correct TINYINT(1) NOT NULL DEFAULT 0,
                time_spent INT NOT NULL DEFAULT 0,
                ability_before FLOAT NOT NULL DEFAULT 0.0,
                ability_after FLOAT NOT NULL DEFAULT 0.0,
                p_correct FLOAT NOT NULL DEFAULT 0.5,
                flag VARCHAR(20) DEFAULT NULL,
                answered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_time (user_id, answered_at),
                INDEX idx_question (question_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        self::$tablesChecked = true;
    }

    /* ================================================================
     *  IRT CORE — Probability of correct answer
     *  P(correct) = c + (1-c) / (1 + exp(-1.7 * a * (θs - θq)))
     *  Simplified: c=0 for most uses, a=discrimination
     * ================================================================ */
    public static function pCorrect(float $abilityStudent, float $difficultyQuestion, float $discrimination = 1.0): float
    {
        $exponent = -1.7 * $discrimination * ($abilityStudent - $difficultyQuestion);
        // Clamp to prevent overflow
        $exponent = max(-10.0, min(10.0, $exponent));
        return 1.0 / (1.0 + exp($exponent));
    }

    /* ================================================================
     *  LEARNING RATE — decreases as student answers more
     * ================================================================ */
    private static function learningRate(int $questionsAnswered): float
    {
        if ($questionsAnswered < 10) return 0.4;
        if ($questionsAnswered < 50) return 0.2;
        return 0.1;
    }

    /* ================================================================
     *  GET STUDENT STATE (per domain or overall)
     * ================================================================ */
    public static function getState(int $userId, string $domain = 'overall'): array
    {
        self::ensureTables();

        $row = Database::fetch(
            "SELECT * FROM adaptive_student_state WHERE user_id = ? AND domain = ? LIMIT 1",
            [$userId, $domain]
        );

        if ($row) {
            return [
                'user_id'            => (int) $row['user_id'],
                'domain'             => $row['domain'],
                'ability_estimate'   => (float) $row['ability_estimate'],
                'questions_answered' => (int) $row['questions_answered'],
                'correct_count'      => (int) $row['correct_count'],
                'total_time_spent'   => (int) $row['total_time_spent'],
                'streak_correct'     => (int) $row['streak_correct'],
                'streak_incorrect'   => (int) $row['streak_incorrect'],
                'last_updated'       => $row['last_updated'],
            ];
        }

        // Initialize default state
        return [
            'user_id'            => $userId,
            'domain'             => $domain,
            'ability_estimate'   => 0.0,
            'questions_answered' => 0,
            'correct_count'      => 0,
            'total_time_spent'   => 0,
            'streak_correct'     => 0,
            'streak_incorrect'   => 0,
            'last_updated'       => null,
        ];
    }

    /* ================================================================
     *  GET ALL DOMAIN STATES for a user
     * ================================================================ */
    public static function getAllStates(int $userId): array
    {
        self::ensureTables();

        $rows = Database::fetchAll(
            "SELECT * FROM adaptive_student_state WHERE user_id = ? ORDER BY domain",
            [$userId]
        );

        $states = [];
        foreach ($rows as $row) {
            $states[$row['domain']] = [
                'ability_estimate'   => (float) $row['ability_estimate'],
                'questions_answered' => (int) $row['questions_answered'],
                'correct_count'      => (int) $row['correct_count'],
                'total_time_spent'   => (int) $row['total_time_spent'],
                'streak_correct'     => (int) $row['streak_correct'],
                'streak_incorrect'   => (int) $row['streak_incorrect'],
                'last_updated'       => $row['last_updated'],
            ];
        }

        // Fill in missing domains with defaults
        foreach (array_merge(self::DOMAINS, ['overall' => 'Overall']) as $slug => $label) {
            if (!isset($states[$slug])) {
                $states[$slug] = [
                    'ability_estimate'   => 0.0,
                    'questions_answered' => 0,
                    'correct_count'      => 0,
                    'total_time_spent'   => 0,
                    'streak_correct'     => 0,
                    'streak_incorrect'   => 0,
                    'last_updated'       => null,
                ];
            }
        }

        return $states;
    }

    /* ================================================================
     *  GET QUESTION PARAMS — ensures IRT params exist for a question
     * ================================================================ */
    private static function getQuestionParams(int $questionId): array
    {
        $row = Database::fetch(
            "SELECT * FROM adaptive_question_params WHERE question_id = ? LIMIT 1",
            [$questionId]
        );

        if ($row) {
            return [
                'question_id'        => (int) $row['question_id'],
                'difficulty_estimate' => (float) $row['difficulty_estimate'],
                'discrimination'     => (float) $row['discrimination'],
                'guessing_param'     => (float) $row['guessing_param'],
                'times_shown'        => (int) $row['times_shown'],
                'times_correct'      => (int) $row['times_correct'],
            ];
        }

        // Bootstrap from the question's difficulty label
        $q = Database::fetch(
            "SELECT id, difficulty, domain FROM sat_quiz_questions WHERE id = ? LIMIT 1",
            [$questionId]
        );

        $diffLabel = strtolower($q['difficulty'] ?? 'medium');
        $diffEstimate = self::DIFFICULTY_MAP[$diffLabel] ?? 0.0;

        // Insert default params
        Database::query(
            "INSERT IGNORE INTO adaptive_question_params (question_id, difficulty_estimate, discrimination, guessing_param, times_shown, times_correct)
             VALUES (?, ?, 1.0, 0.25, 0, 0)",
            [$questionId, $diffEstimate]
        );

        return [
            'question_id'         => $questionId,
            'difficulty_estimate' => $diffEstimate,
            'discrimination'      => 1.0,
            'guessing_param'      => 0.25,
            'times_shown'         => 0,
            'times_correct'       => 0,
        ];
    }

    /* ================================================================
     *  GET NEXT QUESTION — selects optimal next question via IRT
     *
     *  Strategy:
     *    1. Determine target domain (weakest if none specified)
     *    2. Get student ability for that domain
     *    3. Find questions where P(correct) is in ZPD (0.55–0.80)
     *    4. Exclude recently seen (last 24h)
     *    5. Rank by information gain (closest to ideal P = 0.70)
     *    6. Add slight randomness to prevent repetition
     * ================================================================ */
    public static function getNextQuestion(int $userId, ?string $domain = null): ?array
    {
        self::ensureTables();

        // Determine target domain
        if ($domain === null) {
            $domain = self::getWeakestDomain($userId);
        }

        $state = self::getState($userId, $domain ?: 'overall');
        $ability = $state['ability_estimate'];
        $overallState = self::getState($userId, 'overall');
        $overallAbility = $overallState['ability_estimate'];

        // Use domain ability if available, otherwise overall
        $effectiveAbility = $state['questions_answered'] >= 3 ? $ability : $overallAbility;

        // Calculate ideal difficulty for this student (where P ≈ 0.70)
        // P = 1/(1+exp(-1.7*(θs - θq))) = 0.70
        // θq = θs - ln(0.70/0.30) / 1.7 ≈ θs - 0.50
        $idealDifficulty = $effectiveAbility - 0.50;

        // Get recently seen question IDs (last 24 hours)
        $recentIds = Database::fetchAll(
            "SELECT question_id FROM adaptive_response_log
             WHERE user_id = ? AND answered_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            [$userId]
        );
        $recentIdList = array_column($recentIds, 'question_id');
        $excludePlaceholder = '';
        $excludeParams = [$userId];

        if (!empty($recentIdList)) {
            $excludePlaceholder = 'AND q.id NOT IN (' . implode(',', array_fill(0, count($recentIdList), '?')) . ')';
            $excludeParams = array_merge($excludeParams, $recentIdList);
        }

        // Build query with domain filter
        $domainFilter = '';
        if ($domain && $domain !== 'overall' && isset(self::DOMAINS[$domain])) {
            $domainFilter = 'AND q.domain = ?';
            $excludeParams[] = $domain;
        }

        // Find candidate questions
        $sql = "
            SELECT q.id, q.stem, q.difficulty, q.domain, q.skill, q.type,
                   q.option_a, q.option_b, q.option_c, q.option_d,
                   COALESCE(ap.difficulty_estimate, 0.0) AS diff_estimate,
                   COALESCE(ap.discrimination, 1.0) AS discrimination
            FROM sat_quiz_questions q
            LEFT JOIN adaptive_question_params ap ON ap.question_id = q.id
            LEFT JOIN adaptive_response_log arl ON arl.question_id = q.id AND arl.user_id = ?
            WHERE 1=1
            {$excludePlaceholder}
            {$domainFilter}
            GROUP BY q.id
            ORDER BY ABS(COALESCE(ap.difficulty_estimate, 0.0) - ?) ASC, RAND()
            LIMIT 20
        ";
        $excludeParams[] = $idealDifficulty;

        $candidates = Database::fetchAll($sql, $excludeParams);

        if (empty($candidates)) {
            // Fallback: get any question from the domain, even if recently seen
            $fallbackParams = [];
            $fallbackDomain = '';
            if ($domain && $domain !== 'overall') {
                $fallbackDomain = 'WHERE q.domain = ?';
                $fallbackParams[] = $domain;
            }
            $fallbackParams[] = $idealDifficulty;

            $candidates = Database::fetchAll("
                SELECT q.id, q.stem, q.difficulty, q.domain, q.skill, q.type,
                       q.option_a, q.option_b, q.option_c, q.option_d,
                       COALESCE(ap.difficulty_estimate, 0.0) AS diff_estimate,
                       COALESCE(ap.discrimination, 1.0) AS discrimination
                FROM sat_quiz_questions q
                LEFT JOIN adaptive_question_params ap ON ap.question_id = q.id
                {$fallbackDomain}
                ORDER BY ABS(COALESCE(ap.difficulty_estimate, 0.0) - ?) ASC, RAND()
                LIMIT 10
            ", $fallbackParams);
        }

        if (empty($candidates)) return null;

        // Score each candidate by information gain (proximity to ZPD ideal)
        $scored = [];
        foreach ($candidates as $c) {
            $p = self::pCorrect($effectiveAbility, (float) $c['diff_estimate'], (float) $c['discrimination']);
            // Fisher information: a^2 * p * (1-p) — max at p=0.5
            $information = pow((float) $c['discrimination'], 2) * $p * (1 - $p);
            // Bonus for being in ZPD
            $zpdBonus = ($p >= self::ZPD_LOW && $p <= self::ZPD_HIGH) ? 2.0 : 0.0;
            // Penalty for being too far from ideal
            $idealPenalty = -abs($p - self::ZPD_IDEAL);

            $score = $information + $zpdBonus + $idealPenalty + (mt_rand(0, 100) / 500); // small random jitter
            $c['_p_correct'] = $p;
            $c['_score'] = $score;
            $scored[] = $c;
        }

        // Sort by score descending
        usort($scored, fn($a, $b) => $b['_score'] <=> $a['_score']);
        $selected = $scored[0];

        // Record that we showed this question
        Database::query(
            "INSERT INTO adaptive_question_params (question_id, difficulty_estimate, discrimination, guessing_param, times_shown, times_correct)
             VALUES (?, ?, ?, 0.25, 1, 0)
             ON DUPLICATE KEY UPDATE times_shown = times_shown + 1",
            [$selected['id'], (float) $selected['diff_estimate'], (float) $selected['discrimination']]
        );

        return [
            'question_id'   => (int) $selected['id'],
            'stem'          => $selected['stem'],
            'type'          => $selected['type'] ?? 'mcq',
            'domain'        => $selected['domain'],
            'skill'         => $selected['skill'] ?? '',
            'difficulty'    => $selected['difficulty'],
            'choices'       => [
                'A' => $selected['option_a'],
                'B' => $selected['option_b'],
                'C' => $selected['option_c'],
                'D' => $selected['option_d'],
            ],
            'p_correct'     => round($selected['_p_correct'], 3),
            'ability'       => round($effectiveAbility, 3),
            'target_domain' => $domain,
        ];
    }

    /* ================================================================
     *  UPDATE ABILITY — after a student answers a question
     *
     *  Returns updated state + flags (guessing, careless_error)
     * ================================================================ */
    public static function updateAbility(int $userId, int $questionId, bool $correct, int $timeSpent): array
    {
        self::ensureTables();

        // Get question info
        $question = Database::fetch(
            "SELECT id, domain, difficulty, correct_answer, explanation, explanation_html
             FROM sat_quiz_questions WHERE id = ? LIMIT 1",
            [$questionId]
        );
        if (!$question) {
            throw new RuntimeException("Question {$questionId} not found");
        }

        $domain = $question['domain'] ?: 'overall';
        $qParams = self::getQuestionParams($questionId);

        // Get current states
        $domainState = self::getState($userId, $domain);
        $overallState = self::getState($userId, 'overall');

        $domainAbility = $domainState['ability_estimate'];
        $overallAbility = $overallState['ability_estimate'];

        // Calculate P(correct)
        $effectiveAbility = $domainState['questions_answered'] >= 3 ? $domainAbility : $overallAbility;
        $p = self::pCorrect($effectiveAbility, $qParams['difficulty_estimate'], $qParams['discrimination']);

        // Detect anomalies
        $flag = null;
        $outcome = $correct ? 1.0 : 0.0;

        // Guessing detection: correct answer but extremely fast (< 8 seconds) and high difficulty
        if ($correct && $timeSpent < 8 && $qParams['difficulty_estimate'] > $effectiveAbility + 0.5) {
            $flag = 'suspected_guess';
            // Reduce the update magnitude for suspected guesses
            $outcome = 0.6; // Partial credit
        }

        // Careless error detection: wrong but ability much higher than question difficulty
        if (!$correct && $effectiveAbility > $qParams['difficulty_estimate'] + 1.0 && $timeSpent < 15) {
            $flag = 'careless_error';
            $outcome = 0.3; // Partial penalty
        }

        // Slow correct: took very long but got it right — slight extra credit
        if ($correct && $timeSpent > 120) {
            $flag = $flag ?: 'slow_correct';
        }

        // Calculate ability update
        $K_domain = self::learningRate($domainState['questions_answered']);
        $K_overall = self::learningRate($overallState['questions_answered']);

        $newDomainAbility = $domainAbility + $K_domain * ($outcome - $p);
        $newOverallAbility = $overallAbility + $K_overall * ($outcome - $p);

        // Clamp abilities to reasonable range [-3, 3]
        $newDomainAbility = max(-3.0, min(3.0, $newDomainAbility));
        $newOverallAbility = max(-3.0, min(3.0, $newOverallAbility));

        // Update streaks
        $newDomainStreakCorrect = $correct ? $domainState['streak_correct'] + 1 : 0;
        $newDomainStreakIncorrect = $correct ? 0 : $domainState['streak_incorrect'] + 1;
        $newOverallStreakCorrect = $correct ? $overallState['streak_correct'] + 1 : 0;
        $newOverallStreakIncorrect = $correct ? 0 : $overallState['streak_incorrect'] + 1;

        // Persist domain state
        Database::query(
            "INSERT INTO adaptive_student_state (user_id, domain, ability_estimate, questions_answered, correct_count, total_time_spent, streak_correct, streak_incorrect)
             VALUES (?, ?, ?, 1, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                ability_estimate = ?,
                questions_answered = questions_answered + 1,
                correct_count = correct_count + ?,
                total_time_spent = total_time_spent + ?,
                streak_correct = ?,
                streak_incorrect = ?,
                last_updated = NOW()",
            [
                $userId, $domain, $newDomainAbility,
                $correct ? 1 : 0, $timeSpent,
                $newDomainStreakCorrect, $newDomainStreakIncorrect,
                // ON DUPLICATE KEY
                $newDomainAbility,
                $correct ? 1 : 0, $timeSpent,
                $newDomainStreakCorrect, $newDomainStreakIncorrect,
            ]
        );

        // Persist overall state
        Database::query(
            "INSERT INTO adaptive_student_state (user_id, domain, ability_estimate, questions_answered, correct_count, total_time_spent, streak_correct, streak_incorrect)
             VALUES (?, 'overall', ?, 1, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                ability_estimate = ?,
                questions_answered = questions_answered + 1,
                correct_count = correct_count + ?,
                total_time_spent = total_time_spent + ?,
                streak_correct = ?,
                streak_incorrect = ?,
                last_updated = NOW()",
            [
                $userId, $newOverallAbility,
                $correct ? 1 : 0, $timeSpent,
                $newOverallStreakCorrect, $newOverallStreakIncorrect,
                // ON DUPLICATE KEY
                $newOverallAbility,
                $correct ? 1 : 0, $timeSpent,
                $newOverallStreakCorrect, $newOverallStreakIncorrect,
            ]
        );

        // Log the response
        Database::query(
            "INSERT INTO adaptive_response_log (user_id, question_id, domain, is_correct, time_spent, ability_before, ability_after, p_correct, flag)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $userId, $questionId, $domain,
                $correct ? 1 : 0, $timeSpent,
                round($domainAbility, 4), round($newDomainAbility, 4),
                round($p, 4), $flag,
            ]
        );

        // Update question params (calibration) based on observed outcomes
        self::calibrateQuestion($questionId, $correct);

        return [
            'correct'         => $correct,
            'correct_answer'  => $question['correct_answer'],
            'explanation'     => $question['explanation_html'] ?: $question['explanation'],
            'flag'            => $flag,
            'ability_before'  => round($domainAbility, 3),
            'ability_after'   => round($newDomainAbility, 3),
            'ability_change'  => round($newDomainAbility - $domainAbility, 3),
            'overall_ability' => round($newOverallAbility, 3),
            'p_correct'       => round($p, 3),
            'domain'          => $domain,
            'domain_label'    => self::DOMAINS[$domain] ?? ucfirst($domain),
            'questions_answered' => $domainState['questions_answered'] + 1,
            'domain_accuracy' => $domainState['questions_answered'] > 0
                ? round(($domainState['correct_count'] + ($correct ? 1 : 0)) / ($domainState['questions_answered'] + 1) * 100, 1)
                : ($correct ? 100.0 : 0.0),
            'streak'          => $correct ? $newDomainStreakCorrect : -$newDomainStreakIncorrect,
        ];
    }

    /* ================================================================
     *  CALIBRATE QUESTION — adjust difficulty based on observed data
     * ================================================================ */
    private static function calibrateQuestion(int $questionId, bool $correct): void
    {
        Database::query(
            "UPDATE adaptive_question_params
             SET times_correct = times_correct + ?,
                 last_calibrated = NOW()
             WHERE question_id = ?",
            [$correct ? 1 : 0, $questionId]
        );

        // Re-estimate difficulty every 20 responses
        $params = Database::fetch(
            "SELECT times_shown, times_correct, difficulty_estimate FROM adaptive_question_params WHERE question_id = ?",
            [$questionId]
        );

        if ($params && (int) $params['times_shown'] >= 20 && (int) $params['times_shown'] % 10 === 0) {
            $observed_p = (int) $params['times_correct'] / (int) $params['times_shown'];
            // Inverse IRT: θq ≈ -ln(p/(1-p)) / 1.7
            $observed_p = max(0.05, min(0.95, $observed_p));
            $newDifficulty = -log($observed_p / (1 - $observed_p)) / 1.7;
            $newDifficulty = max(-3.0, min(3.0, $newDifficulty));

            // Smooth update: blend old and new
            $oldDifficulty = (float) $params['difficulty_estimate'];
            $blended = $oldDifficulty * 0.6 + $newDifficulty * 0.4;

            Database::query(
                "UPDATE adaptive_question_params SET difficulty_estimate = ? WHERE question_id = ?",
                [round($blended, 4), $questionId]
            );
        }
    }

    /* ================================================================
     *  GET WEAKEST DOMAIN — returns domain slug student needs most help
     * ================================================================ */
    private static function getWeakestDomain(int $userId): string
    {
        $states = self::getAllStates($userId);
        $weakest = null;
        $lowestAbility = PHP_FLOAT_MAX;

        foreach (self::DOMAINS as $slug => $label) {
            $s = $states[$slug] ?? null;
            if (!$s || $s['questions_answered'] === 0) {
                // Never tested domain — highest priority
                return $slug;
            }
            if ($s['ability_estimate'] < $lowestAbility) {
                $lowestAbility = $s['ability_estimate'];
                $weakest = $slug;
            }
        }

        return $weakest ?? 'algebra';
    }

    /* ================================================================
     *  GET STUDENT PROFILE — full adaptive profile for dashboard
     * ================================================================ */
    public static function getStudentProfile(int $userId): array
    {
        self::ensureTables();

        $states = self::getAllStates($userId);
        $overall = $states['overall'] ?? ['ability_estimate' => 0.0, 'questions_answered' => 0, 'correct_count' => 0, 'total_time_spent' => 0];

        // Per-domain profiles
        $domainProfiles = [];
        foreach (self::DOMAINS as $slug => $label) {
            $s = $states[$slug] ?? ['ability_estimate' => 0.0, 'questions_answered' => 0, 'correct_count' => 0, 'total_time_spent' => 0];
            $accuracy = $s['questions_answered'] > 0 ? round($s['correct_count'] / $s['questions_answered'] * 100, 1) : 0;
            $avgTime = $s['questions_answered'] > 0 ? round($s['total_time_spent'] / $s['questions_answered'], 1) : 0;

            $domainProfiles[$slug] = [
                'label'              => $label,
                'ability'            => round($s['ability_estimate'], 3),
                'ability_pct'        => self::abilityToPercentile($s['ability_estimate']),
                'questions_answered' => $s['questions_answered'],
                'accuracy'           => $accuracy,
                'avg_time_seconds'   => $avgTime,
                'level'              => self::abilityToLevel($s['ability_estimate']),
                'level_label'        => self::abilityToLevelLabel($s['ability_estimate']),
            ];
        }

        // Learning velocity: measure improvement over last 7 days vs prior 7 days
        $velocity = self::calculateVelocity($userId);

        // Optimal study time
        $optimalStudy = self::getOptimalStudyRecommendation($userId, $states);

        // Predicted score + confidence interval
        $prediction = self::predictFromAbility($userId, $overall['ability_estimate']);

        // Weakest domain
        $weakest = self::getWeakestDomain($userId);

        return [
            'overall' => [
                'ability'            => round($overall['ability_estimate'], 3),
                'ability_pct'        => self::abilityToPercentile($overall['ability_estimate']),
                'questions_answered' => $overall['questions_answered'],
                'accuracy'           => $overall['questions_answered'] > 0
                    ? round($overall['correct_count'] / $overall['questions_answered'] * 100, 1) : 0,
                'total_time_minutes' => round($overall['total_time_spent'] / 60, 1),
                'level'              => self::abilityToLevel($overall['ability_estimate']),
                'level_label'        => self::abilityToLevelLabel($overall['ability_estimate']),
            ],
            'domains'            => $domainProfiles,
            'weakest_domain'     => $weakest,
            'weakest_label'      => self::DOMAINS[$weakest] ?? $weakest,
            'velocity'           => $velocity,
            'optimal_study'      => $optimalStudy,
            'predicted_score'    => $prediction['predicted_score'],
            'confidence_low'     => $prediction['confidence_low'],
            'confidence_high'    => $prediction['confidence_high'],
            'ready'              => $overall['questions_answered'] >= 10,
        ];
    }

    /* ================================================================
     *  GENERATE ADAPTIVE QUIZ — personalized question selection
     *
     *  Mix: 20% confidence builders (easy), 60% at-level, 20% stretch
     * ================================================================ */
    public static function generateAdaptiveQuiz(int $userId, int $questionCount = 10, ?string $domain = null): array
    {
        self::ensureTables();

        $state = $domain && $domain !== 'all'
            ? self::getState($userId, $domain)
            : self::getState($userId, 'overall');
        $ability = $state['ability_estimate'];

        // Calculate counts for each category
        $easyCount = max(1, (int) round($questionCount * self::MIX_EASY));
        $stretchCount = max(1, (int) round($questionCount * self::MIX_STRETCH));
        $atLevelCount = $questionCount - $easyCount - $stretchCount;

        // Difficulty targets
        $easyTarget = $ability - 1.0;     // ~85-90% chance correct
        $atLevelTarget = $ability - 0.5;  // ~70% chance correct
        $stretchTarget = $ability + 0.5;  // ~45-55% chance correct

        // Build domain filter
        $domainFilter = '';
        $domainParams = [];
        if ($domain && $domain !== 'all' && $domain !== 'overall' && isset(self::DOMAINS[$domain])) {
            $domainFilter = 'AND q.domain = ?';
            $domainParams = [$domain];
        }

        // Get recently seen questions to avoid
        $recentIds = Database::fetchAll(
            "SELECT question_id FROM adaptive_response_log
             WHERE user_id = ? AND answered_at > DATE_SUB(NOW(), INTERVAL 48 HOUR)",
            [$userId]
        );
        $excludeIds = array_column($recentIds, 'question_id');
        $excludeClause = '';
        $excludeParams = [];
        if (!empty($excludeIds)) {
            $excludeClause = 'AND q.id NOT IN (' . implode(',', array_fill(0, count($excludeIds), '?')) . ')';
            $excludeParams = $excludeIds;
        }

        $questions = [];
        $usedIds = [];
        $rationale = [];

        // Helper to fetch questions near a difficulty target
        $fetchNear = function (float $target, int $count, string $label) use (
            &$questions, &$usedIds, &$rationale, $domainFilter, $domainParams,
            $excludeClause, $excludeParams, $ability
        ) {
            if ($count <= 0) return;

            $usedExclude = '';
            $usedParams = [];
            if (!empty($usedIds)) {
                $usedExclude = 'AND q.id NOT IN (' . implode(',', array_fill(0, count($usedIds), '?')) . ')';
                $usedParams = $usedIds;
            }

            $params = array_merge($domainParams, $excludeParams, $usedParams, [$target]);

            $rows = Database::fetchAll("
                SELECT q.id, q.stem, q.difficulty, q.domain, q.skill, q.type,
                       q.option_a, q.option_b, q.option_c, q.option_d,
                       COALESCE(ap.difficulty_estimate, 0.0) AS diff_estimate,
                       COALESCE(ap.discrimination, 1.0) AS discrimination
                FROM sat_quiz_questions q
                LEFT JOIN adaptive_question_params ap ON ap.question_id = q.id
                WHERE 1=1
                {$domainFilter}
                {$excludeClause}
                {$usedExclude}
                ORDER BY ABS(COALESCE(ap.difficulty_estimate, 0.0) - ?) ASC, RAND()
                LIMIT ?
            ", array_merge($params, [$count * 2]));

            // Score and pick best
            $picked = 0;
            foreach ($rows as $row) {
                if ($picked >= $count) break;
                if (in_array((int) $row['id'], $usedIds, true)) continue;

                $p = self::pCorrect($ability, (float) $row['diff_estimate'], (float) $row['discrimination']);
                $questions[] = [
                    'question_id' => (int) $row['id'],
                    'stem'        => $row['stem'],
                    'type'        => $row['type'] ?? 'mcq',
                    'domain'      => $row['domain'],
                    'skill'       => $row['skill'] ?? '',
                    'difficulty'  => $row['difficulty'],
                    'choices'     => [
                        'A' => $row['option_a'],
                        'B' => $row['option_b'],
                        'C' => $row['option_c'],
                        'D' => $row['option_d'],
                    ],
                    'p_correct'   => round($p, 3),
                    'category'    => $label,
                ];
                $usedIds[] = (int) $row['id'];
                $rationale[] = [
                    'question_id' => (int) $row['id'],
                    'category'    => $label,
                    'p_correct'   => round($p, 3),
                    'reason'      => $label === 'confidence'
                        ? 'Confidence builder — high chance of success to build momentum'
                        : ($label === 'stretch'
                            ? 'Stretch question — pushes beyond current level for growth'
                            : 'At-level — targets zone of proximal development for optimal learning'),
                ];
                $picked++;
            }
        };

        // Fetch in order: easy first (warm up), then at-level, then stretch
        $fetchNear($easyTarget, $easyCount, 'confidence');
        $fetchNear($atLevelTarget, $atLevelCount, 'at_level');
        $fetchNear($stretchTarget, $stretchCount, 'stretch');

        // If we didn't get enough, pad with random questions
        if (count($questions) < $questionCount) {
            $deficit = $questionCount - count($questions);
            $usedExclude = '';
            $usedParams = [];
            if (!empty($usedIds)) {
                $usedExclude = 'AND q.id NOT IN (' . implode(',', array_fill(0, count($usedIds), '?')) . ')';
                $usedParams = $usedIds;
            }
            $params = array_merge($domainParams, $usedParams);
            $extra = Database::fetchAll("
                SELECT q.id, q.stem, q.difficulty, q.domain, q.skill, q.type,
                       q.option_a, q.option_b, q.option_c, q.option_d
                FROM sat_quiz_questions q
                WHERE 1=1 {$domainFilter} {$usedExclude}
                ORDER BY RAND()
                LIMIT ?
            ", array_merge($params, [$deficit]));

            foreach ($extra as $row) {
                $questions[] = [
                    'question_id' => (int) $row['id'],
                    'stem'        => $row['stem'],
                    'type'        => $row['type'] ?? 'mcq',
                    'domain'      => $row['domain'],
                    'skill'       => $row['skill'] ?? '',
                    'difficulty'  => $row['difficulty'],
                    'choices'     => [
                        'A' => $row['option_a'],
                        'B' => $row['option_b'],
                        'C' => $row['option_c'],
                        'D' => $row['option_d'],
                    ],
                    'p_correct'   => 0.5,
                    'category'    => 'filler',
                ];
            }
        }

        // Interleave: easy → at-level → easy → at-level → stretch pattern
        // Rather than pure shuffle, order for pedagogical flow
        $ordered = self::pedagogicalOrder($questions);

        return [
            'quiz_id'        => 'adaptive_' . $userId . '_' . time(),
            'student_ability' => round($ability, 3),
            'question_count'  => count($ordered),
            'domain'          => $domain ?: 'all',
            'questions'       => $ordered,
            'rationale'       => $rationale,
            'mix'             => [
                'confidence' => $easyCount,
                'at_level'   => $atLevelCount,
                'stretch'    => $stretchCount,
            ],
        ];
    }

    /* ── Pedagogical ordering: warm up → build → challenge ────── */
    private static function pedagogicalOrder(array $questions): array
    {
        $easy = $atLevel = $stretch = $filler = [];
        foreach ($questions as $q) {
            match ($q['category'] ?? 'filler') {
                'confidence' => $easy[] = $q,
                'at_level'   => $atLevel[] = $q,
                'stretch'    => $stretch[] = $q,
                default      => $filler[] = $q,
            };
        }

        // Interleave: start with 1 easy, then alternate at-level with occasional stretch
        $result = [];
        $easyIdx = $atIdx = $stretchIdx = $fillIdx = 0;

        // First: one easy warm-up
        if (isset($easy[$easyIdx])) $result[] = $easy[$easyIdx++];

        // Then interleave
        $total = count($questions) - count($result);
        for ($i = 0; $i < $total; $i++) {
            // Pattern: at-level, at-level, easy/stretch alternating
            if ($i % 5 < 3 && isset($atLevel[$atIdx])) {
                $result[] = $atLevel[$atIdx++];
            } elseif ($i % 5 === 3 && isset($stretch[$stretchIdx])) {
                $result[] = $stretch[$stretchIdx++];
            } elseif (isset($easy[$easyIdx])) {
                $result[] = $easy[$easyIdx++];
            } elseif (isset($atLevel[$atIdx])) {
                $result[] = $atLevel[$atIdx++];
            } elseif (isset($stretch[$stretchIdx])) {
                $result[] = $stretch[$stretchIdx++];
            } elseif (isset($filler[$fillIdx])) {
                $result[] = $filler[$fillIdx++];
            }
        }

        // Number them
        foreach ($result as $i => &$q) {
            $q['position'] = $i + 1;
        }

        return $result;
    }

    /* ================================================================
     *  HELPER — ability to percentile (approximate normal CDF)
     * ================================================================ */
    private static function abilityToPercentile(float $ability): int
    {
        // Map ability [-3, 3] to percentile [1, 99] using normal CDF approximation
        // Standard normal: mean=0, sd=1
        $z = $ability; // ability IS the z-score in our model
        // Approximation of Phi(z) using logistic function
        $p = 1.0 / (1.0 + exp(-1.7 * $z));
        return max(1, min(99, (int) round($p * 100)));
    }

    /* ── Ability to skill level ───────────────────────────────── */
    private static function abilityToLevel(float $ability): int
    {
        // 1-5 scale
        if ($ability < -1.5) return 1;
        if ($ability < -0.5) return 2;
        if ($ability < 0.5)  return 3;
        if ($ability < 1.5)  return 4;
        return 5;
    }

    private static function abilityToLevelLabel(float $ability): string
    {
        return match (self::abilityToLevel($ability)) {
            1 => 'Beginner',
            2 => 'Developing',
            3 => 'Proficient',
            4 => 'Advanced',
            5 => 'Expert',
        };
    }

    /* ================================================================
     *  LEARNING VELOCITY — rate of improvement
     * ================================================================ */
    private static function calculateVelocity(int $userId): array
    {
        // Get ability changes over recent periods
        $recent = Database::fetchAll(
            "SELECT ability_after, answered_at
             FROM adaptive_response_log
             WHERE user_id = ? AND answered_at > DATE_SUB(NOW(), INTERVAL 14 DAY)
             ORDER BY answered_at ASC",
            [$userId]
        );

        if (count($recent) < 5) {
            return [
                'trend'         => 'insufficient_data',
                'points_per_day' => 0,
                'description'   => 'Answer more questions to see your learning velocity',
            ];
        }

        // Split into first half and second half
        $mid = (int) (count($recent) / 2);
        $firstHalf = array_slice($recent, 0, $mid);
        $secondHalf = array_slice($recent, $mid);

        $firstAvg = array_sum(array_column($firstHalf, 'ability_after')) / count($firstHalf);
        $secondAvg = array_sum(array_column($secondHalf, 'ability_after')) / count($secondHalf);
        $change = $secondAvg - $firstAvg;

        // Calculate days spanned
        $firstDate = new DateTime($recent[0]['answered_at']);
        $lastDate = new DateTime(end($recent)['answered_at']);
        $days = max(1, $firstDate->diff($lastDate)->days);

        $pointsPerDay = $change / $days;
        // Convert ability change to approximate SAT point change
        $satPointsPerDay = $pointsPerDay * 100; // rough: 1 ability unit ≈ 100 SAT points

        if ($change > 0.05) {
            $trend = 'improving';
            $desc = sprintf('Gaining ~%.0f SAT points/week', abs($satPointsPerDay * 7));
        } elseif ($change < -0.05) {
            $trend = 'declining';
            $desc = 'Performance has dipped recently — focus on fundamentals';
        } else {
            $trend = 'plateau';
            $desc = 'Score is stable — try harder questions or new domains to break through';
        }

        return [
            'trend'           => $trend,
            'points_per_day'  => round($satPointsPerDay, 1),
            'ability_change'  => round($change, 4),
            'days_measured'   => $days,
            'description'     => $desc,
        ];
    }

    /* ================================================================
     *  OPTIMAL STUDY RECOMMENDATIONS
     * ================================================================ */
    private static function getOptimalStudyRecommendation(int $userId, array $states): array
    {
        // Analyze time-of-day performance
        $timePerf = Database::fetchAll(
            "SELECT HOUR(answered_at) AS hr, AVG(is_correct) AS acc, COUNT(*) AS cnt
             FROM adaptive_response_log
             WHERE user_id = ? AND answered_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY HOUR(answered_at)
             HAVING cnt >= 5
             ORDER BY acc DESC",
            [$userId]
        );

        $bestHour = null;
        if (!empty($timePerf)) {
            $bestHour = (int) $timePerf[0]['hr'];
        }

        // Recommend minutes per domain based on weakness
        $recommendations = [];
        $totalMinutes = 30; // Default session

        foreach (self::DOMAINS as $slug => $label) {
            $s = $states[$slug] ?? ['ability_estimate' => 0.0, 'questions_answered' => 0];
            // Lower ability = more time needed
            $weight = max(0.1, 2.0 - ($s['ability_estimate'] + 1.5));
            $recommendations[$slug] = [
                'domain' => $label,
                'weight' => round($weight, 2),
            ];
        }

        // Normalize weights to total minutes
        $totalWeight = array_sum(array_column($recommendations, 'weight'));
        foreach ($recommendations as $slug => &$r) {
            $r['minutes'] = $totalWeight > 0
                ? max(5, (int) round($r['weight'] / $totalWeight * $totalMinutes))
                : 8;
        }

        return [
            'session_minutes'  => $totalMinutes,
            'best_hour'        => $bestHour,
            'best_time_label'  => $bestHour !== null ? self::formatHour($bestHour) : null,
            'domain_breakdown' => $recommendations,
            'tip'              => $bestHour !== null
                ? sprintf('You perform best around %s — try to practice then!', self::formatHour($bestHour))
                : 'Practice at the same time daily to build a strong study habit.',
        ];
    }

    private static function formatHour(int $hour): string
    {
        if ($hour === 0) return '12 AM';
        if ($hour < 12) return $hour . ' AM';
        if ($hour === 12) return '12 PM';
        return ($hour - 12) . ' PM';
    }

    /* ================================================================
     *  PREDICT FROM ABILITY — converts IRT ability to SAT score
     * ================================================================ */
    public static function predictFromAbility(int $userId, float $ability): array
    {
        // Map ability [-3, 3] to SAT score [400, 1600]
        // Linear mapping: ability 0 ≈ 1000 (median), each unit ≈ 200 points
        $midScore = 1000;
        $scale = 200;
        $raw = $midScore + ($ability * $scale);
        $predicted = max(400, min(1600, (int) (round($raw / 10) * 10)));

        // Confidence interval depends on questions answered
        $overallState = self::getState($userId, 'overall');
        $n = $overallState['questions_answered'];
        // SE decreases with sqrt(n)
        $se = $n > 0 ? $scale / sqrt(max(1, $n / 3)) : $scale;
        $margin = (int) round($se * 1.28); // 80% CI → z=1.28
        $margin = max(20, min(200, $margin));

        return [
            'predicted_score' => $predicted,
            'confidence_low'  => max(400, $predicted - $margin),
            'confidence_high' => min(1600, $predicted + $margin),
            'margin'          => $margin,
        ];
    }

    /* ================================================================
     *  GET DOMAIN LIST — for external use
     * ================================================================ */
    public static function getDomains(): array
    {
        return self::DOMAINS;
    }

    /* ================================================================
     *  GET RESPONSE HISTORY — for charts
     * ================================================================ */
    public static function getResponseHistory(int $userId, int $limit = 50): array
    {
        self::ensureTables();

        return Database::fetchAll(
            "SELECT question_id, domain, is_correct, time_spent, ability_before, ability_after, p_correct, flag, answered_at
             FROM adaptive_response_log
             WHERE user_id = ?
             ORDER BY answered_at DESC
             LIMIT ?",
            [$userId, $limit]
        );
    }

    /* ================================================================
     *  SESSION STATS — running stats for an active session
     * ================================================================ */
    public static function getSessionStats(int $userId, string $since = null): array
    {
        self::ensureTables();

        $since = $since ?: date('Y-m-d H:i:s', time() - 3600); // default last hour

        $rows = Database::fetchAll(
            "SELECT is_correct, time_spent, ability_after, domain
             FROM adaptive_response_log
             WHERE user_id = ? AND answered_at >= ?
             ORDER BY answered_at ASC",
            [$userId, $since]
        );

        if (empty($rows)) {
            return [
                'questions_answered' => 0,
                'correct'            => 0,
                'accuracy'           => 0,
                'avg_time'           => 0,
                'ability_trend'      => [],
                'domains_covered'    => [],
            ];
        }

        $correct = array_sum(array_column($rows, 'is_correct'));
        $total = count($rows);
        $abilities = array_column($rows, 'ability_after');
        $domains = array_unique(array_column($rows, 'domain'));

        return [
            'questions_answered' => $total,
            'correct'            => $correct,
            'accuracy'           => round($correct / $total * 100, 1),
            'avg_time'           => round(array_sum(array_column($rows, 'time_spent')) / $total, 1),
            'ability_trend'      => array_map('floatval', $abilities),
            'ability_start'      => round((float) $abilities[0], 3),
            'ability_current'    => round((float) end($abilities), 3),
            'domains_covered'    => array_values($domains),
        ];
    }
}
