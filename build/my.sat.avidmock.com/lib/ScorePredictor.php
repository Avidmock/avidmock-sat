<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · ScorePredictor
 *  Predicts SAT score range based on practice test attempts.
 *
 *  Uses practice_test_attempts table (verified columns):
 *  id, user_id, test_id, status, total_score, math_score,
 *  rw_score, percentile, predicted_sat, time_taken,
 *  started_at, submitted_at
 * ═══════════════════════════════════════════════════════════════════
 */

class ScorePredictor
{
    /**
     * Get the latest prediction for a user.
     * Returns array with predicted_low, predicted_high, math_predicted,
     * rw_predicted, confidence — or empty array if no data yet.
     */
    public static function getLatest(int $userId): array
    {
        // Get last 5 submitted attempts, most recent first
        $attempts = Database::fetchAll(
            "SELECT total_score, math_score, rw_score, submitted_at
             FROM practice_test_attempts
             WHERE user_id = ? AND status = 'submitted'
               AND total_score IS NOT NULL
             ORDER BY submitted_at DESC
             LIMIT 5",
            [$userId]
        );

        if (empty($attempts)) {
            return [];
        }

        // Extract scores
        $totalScores = array_filter(array_column($attempts, 'total_score'));
        $mathScores  = array_filter(array_column($attempts, 'math_score'));
        $rwScores    = array_filter(array_column($attempts, 'rw_score'));

        if (empty($totalScores)) {
            return [];
        }

        // Weight recent attempts more heavily
        $weightedTotal = self::weightedAverage($totalScores);
        $weightedMath  = !empty($mathScores) ? self::weightedAverage($mathScores) : null;
        $weightedRW    = !empty($rwScores)   ? self::weightedAverage($rwScores)   : null;

        // Round to nearest 10 (SAT scoring increments)
        $predicted     = (int) (round($weightedTotal / 10) * 10);
        $predictedMath = $weightedMath !== null ? (int) (round($weightedMath / 10) * 10) : null;
        $predictedRW   = $weightedRW   !== null ? (int) (round($weightedRW   / 10) * 10) : null;

        // Margin of error shrinks as more attempts are available
        $count  = count($totalScores);
        $margin = $count >= 4 ? 30 : ($count >= 2 ? 50 : 70);

        // Confidence grows with more attempts (max ~90%)
        $confidence = min(0.90, 0.40 + ($count * 0.10));

        // Clamp to valid SAT range 400–1600
        $low  = max(400,  $predicted - $margin);
        $high = min(1600, $predicted + $margin);

        // Keep math/rw in 200–800 range
        if ($predictedMath !== null) {
            $predictedMath = max(200, min(800, $predictedMath));
        }
        if ($predictedRW !== null) {
            $predictedRW = max(200, min(800, $predictedRW));
        }

        return [
            'predicted_low'  => $low,
            'predicted_high' => $high,
            'predicted_mid'  => $predicted,
            'math_predicted' => $predictedMath,
            'rw_predicted'   => $predictedRW,
            'confidence'     => round($confidence, 2),
            'based_on'       => $count,
        ];
    }

    /**
     * Get full prediction history for the score predictor page.
     * Returns one prediction row per submitted attempt in chronological order.
     */
    public static function getHistory(int $userId): array
    {
        $attempts = Database::fetchAll(
            "SELECT total_score, math_score, rw_score, submitted_at
             FROM practice_test_attempts
             WHERE user_id = ? AND status = 'submitted'
               AND total_score IS NOT NULL
             ORDER BY submitted_at ASC",
            [$userId]
        );

        if (empty($attempts)) {
            return [];
        }

        $history = [];
        foreach ($attempts as $i => $attempt) {
            // Use attempts up to and including this one for the prediction
            $slice       = array_slice($attempts, 0, $i + 1);
            $totals      = array_column($slice, 'total_score');
            $weighted    = self::weightedAverage($totals);
            $predicted   = (int) (round($weighted / 10) * 10);
            $count       = count($slice);
            $margin      = $count >= 4 ? 30 : ($count >= 2 ? 50 : 70);
            $confidence  = min(0.90, 0.40 + ($count * 0.10));

            $history[] = [
                'date'           => date('M j', strtotime($attempt['submitted_at'])),
                'actual_score'   => (int) $attempt['total_score'],
                'predicted_low'  => max(400,  $predicted - $margin),
                'predicted_high' => min(1600, $predicted + $margin),
                'predicted_mid'  => $predicted,
                'confidence'     => round($confidence, 2),
            ];
        }

        return $history;
    }

    /**
     * Get score trend direction for a user.
     * Returns 'improving', 'declining', 'steady', or 'insufficient_data'.
     */
    public static function getTrend(int $userId): string
    {
        $attempts = Database::fetchAll(
            "SELECT total_score
             FROM practice_test_attempts
             WHERE user_id = ? AND status = 'submitted'
               AND total_score IS NOT NULL
             ORDER BY submitted_at DESC
             LIMIT 3",
            [$userId]
        );

        if (count($attempts) < 2) {
            return 'insufficient_data';
        }

        $scores = array_column($attempts, 'total_score');

        // Most recent is first — compare first vs last
        $diff = (int) $scores[0] - (int) $scores[count($scores) - 1];

        if ($diff >= 30)       return 'improving';
        if ($diff <= -30)      return 'declining';
        return 'steady';
    }

    /**
     * Calculate a weighted average — more recent scores count more.
     * Most recent score (first in array) gets highest weight.
     */
    private static function weightedAverage(array $scores): float
    {
        $scores  = array_values($scores);
        $count   = count($scores);
        $total   = 0;
        $weights = 0;

        foreach ($scores as $i => $score) {
            // Weight: most recent (index 0) gets weight = count, oldest gets weight = 1
            $weight   = $count - $i;
            $total   += (float) $score * $weight;
            $weights += $weight;
        }

        return $weights > 0 ? $total / $weights : 0;
    }
}