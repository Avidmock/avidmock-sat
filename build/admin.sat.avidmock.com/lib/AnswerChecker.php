<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · AnswerChecker
 *  Real-time answer validation: MCQ + grid-in normalization.
 * ═══════════════════════════════════════════════════════════════════
 */

class AnswerChecker
{
    /**
     * Check a student's answer against the correct answer.
     * Returns full result with explanation.
     */
    public static function check(int $questionId, string $userAnswer): array
    {
        $question = Database::fetch(
            "SELECT id, type, correct_answer, explanation, hint, choice_a, choice_b, choice_c, choice_d
             FROM questions WHERE id = ? LIMIT 1",
            [$questionId]
        );

        if (!$question) {
            return ['error' => 'Question not found'];
        }

        $isCorrect = match ($question['type']) {
            'multiple_choice', 'evidence_pair', 'passage' => self::checkMCQ($userAnswer, $question['correct_answer']),
            'grid_in' => self::checkGridIn($userAnswer, $question['correct_answer']),
            default => false,
        };

        return [
            'is_correct'      => $isCorrect,
            'correct_answer'  => $question['correct_answer'],
            'user_answer'     => $userAnswer,
            'explanation'     => $question['explanation'],
            'question_type'   => $question['type'],
        ];
    }

    /**
     * Check multiple choice: simple letter comparison.
     */
    private static function checkMCQ(string $userAnswer, string $correctAnswer): bool
    {
        return strtoupper(trim($userAnswer)) === strtoupper(trim($correctAnswer));
    }

    /**
     * Check grid-in: normalize numeric input and compare.
     * Handles fractions, decimals, leading zeros, signs, equivalent forms.
     */
    private static function checkGridIn(string $userAnswer, string $correctAnswer): bool
    {
        $userVal = self::normalizeGridIn($userAnswer);
        $correctVal = self::normalizeGridIn($correctAnswer);

        if ($userVal === null || $correctVal === null) return false;

        // Compare with floating point tolerance
        return abs($userVal - $correctVal) < 0.0001;
    }

    /**
     * Normalize a grid-in answer to a float.
     * Accepts: "3", "3.5", "7/2", "3.50", ".5", "-4", "2/3"
     */
    private static function normalizeGridIn(string $input): ?float
    {
        $input = trim($input);
        if ($input === '') return null;

        // Remove spaces
        $input = str_replace(' ', '', $input);

        // Handle fractions: "7/2", "1/3", etc.
        if (preg_match('/^(-?\d+)\s*\/\s*(-?\d+)$/', $input, $m)) {
            $denominator = (float) $m[2];
            if ($denominator == 0) return null;
            return (float) $m[1] / $denominator;
        }

        // Handle mixed numbers: "3 1/2" → 3.5
        if (preg_match('/^(-?\d+)\s+(\d+)\s*\/\s*(\d+)$/', $input, $m)) {
            $denominator = (float) $m[3];
            if ($denominator == 0) return null;
            $whole = (float) $m[1];
            $frac = (float) $m[2] / $denominator;
            return $whole >= 0 ? $whole + $frac : $whole - $frac;
        }

        // Handle regular numbers and decimals
        if (is_numeric($input)) {
            return (float) $input;
        }

        return null;
    }

    /**
     * Batch check: validate multiple answers at once (for quiz submission).
     * $answers = [question_id => user_answer, ...]
     */
    public static function checkBatch(array $answers): array
    {
        $results = [];
        $correctCount = 0;

        foreach ($answers as $questionId => $userAnswer) {
            $result = self::check((int) $questionId, (string) $userAnswer);
            $results[$questionId] = $result;
            if ($result['is_correct'] ?? false) $correctCount++;
        }

        return [
            'results'        => $results,
            'total'          => count($answers),
            'correct'        => $correctCount,
            'score_percent'  => count($answers) > 0
                ? round(($correctCount / count($answers)) * 100, 1)
                : 0,
        ];
    }

    /**
     * Check answer and record it in the database (during quiz).
     */
    public static function checkAndRecord(
        int $attemptId,
        int $userId,
        int $questionId,
        string $userAnswer,
        int $timeSpent,
        bool $hintUsed = false
    ): array {
        $result = self::check($questionId, $userAnswer);

        // Get question for additional data
        $question = Question::getById($questionId);

        // Record the answer
        Database::insert('question_answers', [
            'attempt_id'       => $attemptId,
            'user_id'          => $userId,
            'question_id'      => $questionId,
            'user_answer'      => $userAnswer,
            'correct_answer'   => $result['correct_answer'],
            'is_correct'       => (int) $result['is_correct'],
            'score'            => $result['is_correct'] ? 1 : 0,
            'time_spent'       => $timeSpent,
            'hint_used'        => (int) $hintUsed,
            'created_at'       => date('Y-m-d H:i:s'),
        ]);

        // Update question stats
        QuestionStats::updateAfterAnswer($questionId, $question['quiz_id'] ?? 0, $result['is_correct'], $timeSpent, $hintUsed);

        return $result;
    }
}