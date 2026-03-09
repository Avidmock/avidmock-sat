<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · QuestionStats
 *  Per-question analytics: accuracy, timing, hint usage, auto difficulty.
 * ═══════════════════════════════════════════════════════════════════
 */

class QuestionStats
{
    public static function updateAfterAnswer(int $questionId, int $quizId, bool $isCorrect, int $timeSpent, bool $hintUsed): void
    {
        $existing = Database::fetch(
            "SELECT * FROM question_stats WHERE question_id = ?",
            [$questionId]
        );

        if ($existing) {
            $total = $existing['total_attempts'] + 1;
            $correctCount = ($existing['accuracy_rate'] / 100 * $existing['total_attempts']) + ($isCorrect ? 1 : 0);
            $accuracy = round(($correctCount / $total) * 100, 1);
            $avgTime = round((($existing['avg_time'] * $existing['total_attempts']) + $timeSpent) / $total, 1);
            $hintCount = ($existing['hint_usage_rate'] / 100 * $existing['total_attempts']) + ($hintUsed ? 1 : 0);
            $hintRate = round(($hintCount / $total) * 100, 1);

            // Auto-calculate difficulty from accuracy
            $difficulty = self::autoDifficulty($accuracy);

            Database::update('question_stats', [
                'total_attempts'    => $total,
                'accuracy_rate'     => $accuracy,
                'avg_time'          => $avgTime,
                'hint_usage_rate'   => $hintRate,
                'difficulty_rating' => $difficulty,
            ], ['id' => $existing['id']]);
        } else {
            Database::insert('question_stats', [
                'question_id'       => $questionId,
                'quiz_id'           => $quizId ?: null,
                'total_attempts'    => 1,
                'accuracy_rate'     => $isCorrect ? 100 : 0,
                'avg_time'          => $timeSpent,
                'hint_usage_rate'   => $hintUsed ? 100 : 0,
                'difficulty_rating' => 'uncalibrated',
            ]);
        }
    }

    private static function autoDifficulty(float $accuracy): string
    {
        if ($accuracy >= 85) return 'easy';
        if ($accuracy >= 55) return 'medium';
        return 'hard';
    }

    public static function getHardestQuestions(int $limit = 20): array
    {
        return Database::fetchAll(
            "SELECT qs.*, q.stem, q.difficulty AS manual_difficulty
             FROM question_stats qs
             JOIN questions q ON q.id = qs.question_id
             WHERE qs.total_attempts >= 10
             ORDER BY qs.accuracy_rate ASC
             LIMIT ?",
            [$limit]
        );
    }

    public static function getFlaggedQuestions(): array
    {
        return Database::fetchAll(
            "SELECT qs.*, q.stem, q.difficulty
             FROM question_stats qs
             JOIN questions q ON q.id = qs.question_id
             WHERE qs.total_attempts >= 10
               AND (qs.accuracy_rate < 20 OR qs.accuracy_rate > 95)
             ORDER BY qs.accuracy_rate ASC"
        );
    }
}