<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · StudentPerformance
 *  Per-user per-quiz aggregated performance metrics.
 * ═══════════════════════════════════════════════════════════════════
 */

class StudentPerformance
{
    /**
     * Get performance record for a specific user + quiz.
     */
    public static function getByQuiz(int $userId, int $quizId): ?array
    {
        global $pdo;
        $stmt = $pdo->prepare(
            "SELECT * FROM student_performance WHERE user_id = ? AND quiz_id = ? LIMIT 1"
        );
        $stmt->execute([$userId, $quizId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Update after a quiz attempt is completed.
     */
    public static function updateAfterAttempt(int $userId, int $quizId, float $score): void
    {
        global $pdo;

        $stmt = $pdo->prepare(
            "SELECT * FROM student_performance WHERE user_id = ? AND quiz_id = ? LIMIT 1"
        );
        $stmt->execute([$userId, $quizId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $totalAttempts = $existing['total_attempts'] + 1;
            $bestScore     = max($existing['best_score'], $score);
            $avgScore      = (($existing['average_score'] * $existing['total_attempts']) + $score) / $totalAttempts;
            $improvement   = $totalAttempts > 1 ? $score - $existing['average_score'] : 0;
            $mastery       = self::calculateMastery($bestScore);

            $stmt = $pdo->prepare(
                "UPDATE student_performance SET
                    total_attempts    = ?,
                    best_score        = ?,
                    average_score     = ?,
                    improvement_rate  = ?,
                    mastery_level     = ?,
                    last_attempt_date = ?
                 WHERE id = ?"
            );
            $stmt->execute([
                $totalAttempts,
                $bestScore,
                round($avgScore, 1),
                round($improvement, 1),
                $mastery,
                date('Y-m-d H:i:s'),
                $existing['id'],
            ]);
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO student_performance
                    (user_id, quiz_id, total_attempts, best_score, average_score, improvement_rate, mastery_level, last_attempt_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $userId,
                $quizId,
                1,
                $score,
                $score,
                0,
                self::calculateMastery($score),
                date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Calculate mastery level from score.
     */
    public static function calculateMastery(float $score): string
    {
        if ($score >= 95) return 'expert';
        if ($score >= 80) return 'advanced';
        if ($score >= 65) return 'proficient';
        if ($score >= 45) return 'developing';
        return 'beginner';
    }

    /**
     * Get mastery display config (label + color).
     */
    public static function masteryDisplay(string $level): array
    {
        return match ($level) {
            'expert'     => ['label' => 'Expert',     'color' => '#8B5CF6', 'bg' => '#F3EEFF'],
            'advanced'   => ['label' => 'Advanced',   'color' => '#1FE290', 'bg' => '#E8FFF5'],
            'proficient' => ['label' => 'Proficient', 'color' => '#4A90D9', 'bg' => '#EBF4FF'],
            'developing' => ['label' => 'Developing', 'color' => '#FFB347', 'bg' => '#FFF3E0'],
            default      => ['label' => 'Beginner',   'color' => '#9CA3AF', 'bg' => '#F3F4F6'],
        };
    }

    /**
     * Get all performance records for a student.
     */
    public static function getForStudent(int $userId): array
    {
        global $pdo;
        $stmt = $pdo->prepare(
            "SELECT sp.*, q.title AS quiz_title, q.lesson_slug
             FROM student_performance sp
             JOIN sat_quizzes q ON q.id = sp.quiz_id
             WHERE sp.user_id = ?
             ORDER BY sp.last_attempt_date DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}