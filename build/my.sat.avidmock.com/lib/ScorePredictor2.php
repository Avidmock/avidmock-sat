<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · ScorePredictor2
 *  v2 Predictive Engine — uses adaptive IRT ability estimates +
 *  historical performance for accurate SAT score prediction.
 *
 *  Combines data from:
 *    - adaptive_student_state (IRT ability per domain)
 *    - adaptive_response_log (answer-by-answer history)
 *    - practice_test_attempts (full test scores)
 *    - student_profiles (target_score)
 *    - category_performance (domain breakdown)
 *
 *  Public API:
 *    ScorePredictor2::predict(int $userId)
 *    ScorePredictor2::getScoreHistory(int $userId, int $days)
 *    ScorePredictor2::compareToPercentile(int $score)
 * ═══════════════════════════════════════════════════════════════════
 */

class ScorePredictor2
{
    /* ── SAT Score distribution (approximate, from College Board data) ── */
    private const PERCENTILE_TABLE = [
        1600 => 99, 1570 => 99, 1550 => 99, 1530 => 98, 1510 => 98,
        1500 => 97, 1490 => 97, 1470 => 96, 1450 => 95, 1430 => 94,
        1410 => 93, 1400 => 92, 1390 => 91, 1370 => 90, 1350 => 89,
        1330 => 87, 1310 => 85, 1300 => 84, 1290 => 83, 1270 => 81,
        1250 => 79, 1230 => 77, 1210 => 74, 1200 => 73, 1190 => 71,
        1170 => 69, 1150 => 66, 1130 => 63, 1110 => 60, 1100 => 58,
        1090 => 56, 1070 => 53, 1050 => 49, 1030 => 46, 1010 => 42,
        1000 => 40, 990 => 38, 970 => 35, 950 => 31, 930 => 28,
        910 => 25, 900 => 23, 890 => 21, 870 => 19, 850 => 16,
        830 => 14, 810 => 12, 800 => 10, 790 => 9, 770 => 8,
        750 => 6, 730 => 5, 710 => 4, 690 => 3, 670 => 2,
        650 => 2, 600 => 1, 500 => 1, 400 => 1,
    ];

    /* ── Domain to SAT section mapping ── */
    private const DOMAIN_LABELS = [
        'algebra'         => 'Algebra',
        'advanced_math'   => 'Advanced Math',
        'problem_solving' => 'Problem Solving & Data Analysis',
        'geometry'        => 'Geometry & Trigonometry',
    ];

    /* ── Domain weights for overall math score ── */
    private const DOMAIN_WEIGHTS = [
        'algebra'         => 0.35,
        'advanced_math'   => 0.35,
        'problem_solving' => 0.15,
        'geometry'        => 0.15,
    ];

    /* ================================================================
     *  PREDICT — main prediction method
     *
     *  Returns comprehensive prediction with:
     *    - predicted_score (400-1600)
     *    - confidence_low / confidence_high (80% CI)
     *    - probability_above_target
     *    - score_by_domain
     *    - trend
     *    - days_to_target
     *    - recommendations
     * ================================================================ */
    public static function predict(int $userId): array
    {
        // Get adaptive ability data
        $states = AdaptiveEngine::getAllStates($userId);
        $overall = $states['overall'] ?? ['ability_estimate' => 0.0, 'questions_answered' => 0];

        // Get practice test data for blending
        $testScores = Database::fetchAll(
            "SELECT total_score, math_score, rw_score, submitted_at
             FROM practice_test_attempts
             WHERE user_id = ? AND status = 'submitted' AND total_score IS NOT NULL
             ORDER BY submitted_at DESC LIMIT 5",
            [$userId]
        );

        // Get target score
        $profile = Database::fetch(
            "SELECT target_score FROM student_profiles WHERE user_id = ? LIMIT 1",
            [$userId]
        );
        $targetScore = (int) ($profile['target_score'] ?? $_SESSION['target_score'] ?? 1200);

        // ─── Calculate predicted math score from IRT ability ───
        $mathFromIRT = self::abilityToMathScore($states);

        // ─── Blend with practice test data if available ───
        $predictedMath = $mathFromIRT;
        $testWeight = 0;
        if (!empty($testScores)) {
            $recentMathScores = array_filter(array_column($testScores, 'math_score'));
            if (!empty($recentMathScores)) {
                $testMathAvg = self::weightedAverage($recentMathScores);
                // Blend: more weight to test scores if recent, more to IRT if many adaptive questions
                $adaptiveN = (int) ($overall['questions_answered'] ?? 0);
                $testWeight = min(0.6, count($recentMathScores) * 0.15);
                $irtWeight = min(0.7, $adaptiveN * 0.02);
                $total = $testWeight + $irtWeight;
                if ($total > 0) {
                    $predictedMath = (int) round(($testMathAvg * $testWeight + $mathFromIRT * $irtWeight) / $total);
                }
            }
        }
        $predictedMath = max(200, min(800, (int) (round($predictedMath / 10) * 10)));

        // ─── Estimate RW score ───
        $predictedRW = 500; // default
        if (!empty($testScores)) {
            $rwScores = array_filter(array_column($testScores, 'rw_score'));
            if (!empty($rwScores)) {
                $predictedRW = (int) (round(self::weightedAverage($rwScores) / 10) * 10);
            }
        }
        $predictedRW = max(200, min(800, $predictedRW));

        // ─── Total predicted score ───
        $predictedTotal = $predictedMath + $predictedRW;
        $predictedTotal = max(400, min(1600, (int) (round($predictedTotal / 10) * 10)));

        // ─── Confidence interval ───
        $n = (int) ($overall['questions_answered'] ?? 0);
        $testN = count($testScores);
        $effectiveN = $n + ($testN * 10); // Each test worth ~10 adaptive questions
        $baseSE = 150;
        $se = $effectiveN > 0 ? $baseSE / sqrt(max(1, $effectiveN / 5)) : $baseSE;
        $margin = (int) round($se * 1.28); // 80% CI
        $margin = max(20, min(200, $margin));

        $confidenceLow = max(400, $predictedTotal - $margin);
        $confidenceHigh = min(1600, $predictedTotal + $margin);

        // ─── Probability above target ───
        $pAboveTarget = self::probAboveTarget($predictedTotal, $margin, $targetScore);

        // ─── Per-domain scores ───
        $domainScores = self::getDomainScores($states);

        // ─── Trend analysis ───
        $trend = self::analyzeTrend($userId);

        // ─── Days to target ───
        $daysToTarget = self::estimateDaysToTarget($userId, $predictedTotal, $targetScore);

        // ─── Recommendations ───
        $recommendations = self::generateRecommendations($userId, $states, $domainScores, $predictedTotal, $targetScore);

        return [
            'predicted_score'         => $predictedTotal,
            'predicted_math'          => $predictedMath,
            'predicted_rw'            => $predictedRW,
            'confidence_low'          => $confidenceLow,
            'confidence_high'         => $confidenceHigh,
            'confidence_margin'       => $margin,
            'target_score'            => $targetScore,
            'probability_above_target' => round($pAboveTarget, 2),
            'score_by_domain'         => $domainScores,
            'trend'                   => $trend,
            'days_to_target'          => $daysToTarget,
            'recommendations'         => $recommendations,
            'percentile'              => self::compareToPercentile($predictedTotal),
            'data_quality'            => [
                'adaptive_questions' => $n,
                'practice_tests'     => $testN,
                'confidence_level'   => $effectiveN >= 50 ? 'high' : ($effectiveN >= 15 ? 'moderate' : 'low'),
            ],
        ];
    }

    /* ================================================================
     *  GET SCORE HISTORY — weekly score estimates over time
     * ================================================================ */
    public static function getScoreHistory(int $userId, int $days = 90): array
    {
        $history = [];

        // Get weekly ability snapshots from adaptive log
        $rows = Database::fetchAll(
            "SELECT
                YEARWEEK(answered_at, 1) AS yw,
                MIN(DATE(answered_at)) AS week_start,
                AVG(ability_after) AS avg_ability,
                COUNT(*) AS questions,
                SUM(is_correct) AS correct
             FROM adaptive_response_log
             WHERE user_id = ? AND answered_at > DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY YEARWEEK(answered_at, 1)
             ORDER BY yw ASC",
            [$userId, $days]
        );

        foreach ($rows as $row) {
            $ability = (float) $row['avg_ability'];
            $score = self::singleAbilityToScore($ability);
            $accuracy = (int) $row['questions'] > 0
                ? round((int) $row['correct'] / (int) $row['questions'] * 100, 1)
                : 0;

            $history[] = [
                'week'       => $row['week_start'],
                'week_label' => date('M j', strtotime($row['week_start'])),
                'score'      => $score,
                'ability'    => round($ability, 3),
                'questions'  => (int) $row['questions'],
                'accuracy'   => $accuracy,
            ];
        }

        // Also include practice test data points
        $tests = Database::fetchAll(
            "SELECT total_score, submitted_at
             FROM practice_test_attempts
             WHERE user_id = ? AND status = 'submitted' AND total_score IS NOT NULL
               AND submitted_at > DATE_SUB(NOW(), INTERVAL ? DAY)
             ORDER BY submitted_at ASC",
            [$userId, $days]
        );

        $testPoints = [];
        foreach ($tests as $t) {
            $testPoints[] = [
                'date'  => date('M j', strtotime($t['submitted_at'])),
                'score' => (int) $t['total_score'],
                'type'  => 'practice_test',
            ];
        }

        return [
            'weekly_estimates' => $history,
            'test_scores'      => $testPoints,
            'period_days'      => $days,
        ];
    }

    /* ================================================================
     *  COMPARE TO PERCENTILE — maps a score to SAT percentile
     * ================================================================ */
    public static function compareToPercentile(int $score): int
    {
        $score = max(400, min(1600, $score));

        // Find nearest score in table
        $closest = 400;
        $closestDiff = PHP_INT_MAX;
        foreach (self::PERCENTILE_TABLE as $tableScore => $pct) {
            $diff = abs($score - $tableScore);
            if ($diff < $closestDiff) {
                $closestDiff = $diff;
                $closest = $tableScore;
            }
        }

        // Interpolate between neighbors
        $keys = array_keys(self::PERCENTILE_TABLE);
        rsort($keys);
        $above = $below = null;
        foreach ($keys as $k) {
            if ($k >= $score && ($above === null || $k < $above)) $above = $k;
            if ($k <= $score && ($below === null || $k > $below)) $below = $k;
        }

        if ($above !== null && $below !== null && $above !== $below) {
            $pctAbove = self::PERCENTILE_TABLE[$above];
            $pctBelow = self::PERCENTILE_TABLE[$below];
            $ratio = ($score - $below) / ($above - $below);
            return max(1, min(99, (int) round($pctBelow + $ratio * ($pctAbove - $pctBelow))));
        }

        return self::PERCENTILE_TABLE[$closest] ?? 50;
    }

    /* ================================================================
     *  PRIVATE HELPERS
     * ================================================================ */

    /* ── Convert domain abilities to per-domain math sub-scores ── */
    private static function abilityToMathScore(array $states): int
    {
        $total = 0;
        $weightSum = 0;

        foreach (self::DOMAIN_WEIGHTS as $domain => $weight) {
            $s = $states[$domain] ?? ['ability_estimate' => 0.0, 'questions_answered' => 0];
            $ability = (float) ($s['ability_estimate'] ?? 0.0);

            // Only include domains with data
            if ((int) ($s['questions_answered'] ?? 0) > 0) {
                // Map ability [-3, 3] to domain score contribution
                // Each domain contributes proportionally to 200–800 math score
                $domainScore = 500 + ($ability * 100); // center at 500, scale 100/unit
                $total += $domainScore * $weight;
                $weightSum += $weight;
            }
        }

        if ($weightSum > 0) {
            $mathScore = (int) round($total / $weightSum);
        } else {
            // Fall back to overall
            $overall = $states['overall'] ?? ['ability_estimate' => 0.0];
            $mathScore = 500 + ((float) ($overall['ability_estimate'] ?? 0.0) * 100);
        }

        return max(200, min(800, (int) (round($mathScore / 10) * 10)));
    }

    /* ── Single ability to total score ── */
    private static function singleAbilityToScore(float $ability): int
    {
        // Rough mapping: ability 0 ≈ 1000 total
        $raw = 1000 + ($ability * 200);
        return max(400, min(1600, (int) (round($raw / 10) * 10)));
    }

    /* ── Get per-domain predicted scores ── */
    private static function getDomainScores(array $states): array
    {
        $scores = [];
        foreach (self::DOMAIN_LABELS as $slug => $label) {
            $s = $states[$slug] ?? ['ability_estimate' => 0.0, 'questions_answered' => 0, 'correct_count' => 0];
            $ability = (float) ($s['ability_estimate'] ?? 0.0);
            $n = (int) ($s['questions_answered'] ?? 0);
            $correct = (int) ($s['correct_count'] ?? 0);

            // Per-domain "score" as a percentage of mastery (0-100)
            $mastery = max(0, min(100, (int) round(50 + $ability * 16.67)));

            $scores[$slug] = [
                'label'      => $label,
                'ability'    => round($ability, 3),
                'mastery'    => $mastery,
                'questions'  => $n,
                'accuracy'   => $n > 0 ? round($correct / $n * 100, 1) : 0,
                'level'      => self::abilityLevel($ability),
            ];
        }
        return $scores;
    }

    private static function abilityLevel(float $ability): string
    {
        if ($ability < -1.5) return 'needs_work';
        if ($ability < -0.5) return 'developing';
        if ($ability < 0.5)  return 'proficient';
        if ($ability < 1.5)  return 'strong';
        return 'expert';
    }

    /* ── Probability of scoring above target ── */
    private static function probAboveTarget(int $predicted, int $margin, int $target): float
    {
        if ($predicted >= $target + $margin) return 0.95;
        if ($predicted <= $target - $margin) return 0.05;

        // Normal approximation
        // SE ≈ margin / 1.28 (since margin is 80% CI)
        $se = $margin / 1.28;
        if ($se <= 0) return $predicted >= $target ? 0.9 : 0.1;

        $z = ($predicted - $target) / $se;
        // Approximate Phi(z) with logistic
        $p = 1.0 / (1.0 + exp(-1.7 * $z));
        return max(0.01, min(0.99, $p));
    }

    /* ── Trend analysis ── */
    private static function analyzeTrend(int $userId): string
    {
        $recent = Database::fetchAll(
            "SELECT AVG(ability_after) AS avg_ability, YEARWEEK(answered_at, 1) AS yw
             FROM adaptive_response_log
             WHERE user_id = ? AND answered_at > DATE_SUB(NOW(), INTERVAL 28 DAY)
             GROUP BY YEARWEEK(answered_at, 1)
             ORDER BY yw ASC",
            [$userId]
        );

        if (count($recent) < 2) {
            // Fall back to practice test trend
            return ScorePredictor::getTrend($userId) === 'improving' ? 'improving' : 'insufficient_data';
        }

        $first = (float) $recent[0]['avg_ability'];
        $last = (float) end($recent)['avg_ability'];
        $change = $last - $first;

        if ($change > 0.1) return 'improving';
        if ($change < -0.1) return 'declining';
        return 'plateau';
    }

    /* ── Estimate days to reach target ── */
    private static function estimateDaysToTarget(int $userId, int $predicted, int $target): ?int
    {
        if ($predicted >= $target) return 0;

        $gap = $target - $predicted;

        // Get learning velocity
        $velocityData = Database::fetchAll(
            "SELECT ability_after, answered_at
             FROM adaptive_response_log
             WHERE user_id = ? AND answered_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
             ORDER BY answered_at ASC",
            [$userId]
        );

        if (count($velocityData) < 10) {
            // Use default rate: ~15 points per week with steady practice
            $pointsPerDay = 2.0;
        } else {
            $firstAbility = (float) $velocityData[0]['ability_after'];
            $lastAbility = (float) end($velocityData)['ability_after'];
            $abilityChange = $lastAbility - $firstAbility;

            $firstDate = new DateTime($velocityData[0]['answered_at']);
            $lastDate = new DateTime(end($velocityData)['answered_at']);
            $days = max(1, $firstDate->diff($lastDate)->days);

            // Convert ability change to score change
            $scoreChange = $abilityChange * 200; // 1 ability ≈ 200 SAT points
            $pointsPerDay = $days > 0 ? $scoreChange / $days : 0;
        }

        if ($pointsPerDay <= 0.5) {
            // Very slow or declining — use optimistic 2 pts/day
            $pointsPerDay = 2.0;
        }

        $daysNeeded = (int) ceil($gap / $pointsPerDay);
        return max(1, min(365, $daysNeeded));
    }

    /* ── Generate actionable recommendations ── */
    private static function generateRecommendations(int $userId, array $states, array $domainScores, int $predicted, int $target): array
    {
        $recs = [];

        // 1. Identify weakest domain
        $weakest = null;
        $lowestMastery = 101;
        foreach ($domainScores as $slug => $ds) {
            if ($ds['questions'] >= 3 && $ds['mastery'] < $lowestMastery) {
                $lowestMastery = $ds['mastery'];
                $weakest = $slug;
            }
        }

        if ($weakest) {
            $gap = $target - $predicted;
            $potentialGain = min((int) round($gap * 0.4), 80);
            $recs[] = [
                'action'        => "Focus on {$domainScores[$weakest]['label']}",
                'description'   => "Your weakest area at {$domainScores[$weakest]['mastery']}% mastery. Intensive practice here offers the biggest score gains.",
                'impact_points' => $potentialGain,
                'priority'      => 'high',
                'type'          => 'domain_focus',
                'domain'        => $weakest,
            ];
        }

        // 2. Check for untested domains
        foreach (self::DOMAIN_LABELS as $slug => $label) {
            $ds = $domainScores[$slug] ?? ['questions' => 0];
            if ((int) ($ds['questions'] ?? 0) < 5) {
                $recs[] = [
                    'action'        => "Take a diagnostic in {$label}",
                    'description'   => "Answer at least 10 questions to calibrate your ability level.",
                    'impact_points' => 20,
                    'priority'      => 'medium',
                    'type'          => 'diagnostic',
                    'domain'        => $slug,
                ];
            }
        }

        // 3. Practice test recommendation
        $lastTest = Database::fetch(
            "SELECT submitted_at FROM practice_test_attempts
             WHERE user_id = ? AND status = 'submitted' ORDER BY submitted_at DESC LIMIT 1",
            [$userId]
        );
        $daysSinceTest = $lastTest
            ? (new DateTime())->diff(new DateTime($lastTest['submitted_at']))->days
            : 999;

        if ($daysSinceTest > 14) {
            $recs[] = [
                'action'        => 'Take a full practice test',
                'description'   => $daysSinceTest > 30
                    ? "It's been over a month since your last practice test. Take one to benchmark your progress."
                    : "A practice test every 1-2 weeks helps track real progress under timed conditions.",
                'impact_points' => 30,
                'priority'      => $daysSinceTest > 30 ? 'high' : 'medium',
                'type'          => 'practice_test',
                'domain'        => null,
            ];
        }

        // 4. Consistency recommendation
        $studyDays = Database::fetchColumn(
            "SELECT COUNT(DISTINCT DATE(answered_at)) FROM adaptive_response_log
             WHERE user_id = ? AND answered_at > DATE_SUB(NOW(), INTERVAL 14 DAY)",
            [$userId]
        );

        if ((int) $studyDays < 5) {
            $recs[] = [
                'action'        => 'Build a daily practice habit',
                'description'   => "You've practiced {$studyDays} of the last 14 days. Aim for at least 5 days/week for steady improvement.",
                'impact_points' => 25,
                'priority'      => 'high',
                'type'          => 'habit',
                'domain'        => null,
            ];
        }

        // 5. Speed work if avg time is high
        $avgTime = Database::fetchColumn(
            "SELECT AVG(time_spent) FROM adaptive_response_log
             WHERE user_id = ? AND answered_at > DATE_SUB(NOW(), INTERVAL 14 DAY)",
            [$userId]
        );

        if ($avgTime && (float) $avgTime > 90) {
            $recs[] = [
                'action'        => 'Work on speed and efficiency',
                'description'   => sprintf(
                    "Your average answer time is %ds. SAT gives ~95s per math question. Practice timed drills.",
                    (int) $avgTime
                ),
                'impact_points' => 15,
                'priority'      => 'medium',
                'type'          => 'speed',
                'domain'        => null,
            ];
        }

        // Sort by priority then impact
        $priorityOrder = ['high' => 0, 'medium' => 1, 'low' => 2];
        usort($recs, function ($a, $b) use ($priorityOrder) {
            $pa = $priorityOrder[$a['priority']] ?? 2;
            $pb = $priorityOrder[$b['priority']] ?? 2;
            if ($pa !== $pb) return $pa - $pb;
            return $b['impact_points'] - $a['impact_points'];
        });

        return array_slice($recs, 0, 5);
    }

    /* ── Weighted average (recent items weighted higher) ── */
    private static function weightedAverage(array $values): float
    {
        $values = array_values($values);
        $count = count($values);
        $total = 0;
        $weights = 0;
        foreach ($values as $i => $v) {
            $w = $count - $i;
            $total += (float) $v * $w;
            $weights += $w;
        }
        return $weights > 0 ? $total / $weights : 0;
    }
}
