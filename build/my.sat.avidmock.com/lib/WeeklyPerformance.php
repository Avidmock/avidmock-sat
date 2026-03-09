<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · WeeklyPerformance
 *  Weekly metrics for streaks, leaderboard, and analytics.
 * ═══════════════════════════════════════════════════════════════════
 */

class WeeklyPerformance
{
    public static function updateAfterQuiz(int $userId, int $correctAnswers, float $score): void
    {
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $weekEnd   = date('Y-m-d', strtotime('sunday this week'));

        $existing = Database::fetch(
            "SELECT * FROM weekly_performance WHERE user_id = ? AND week_start = ?",
            [$userId, $weekStart]
        );

        if ($existing) {
            $quizzesTaken = $existing['quizzes_taken'] + 1;
            $totalCorrect = $existing['correct_answers'] + $correctAnswers;
            $avgScore = (($existing['average_score'] * $existing['quizzes_taken']) + $score) / $quizzesTaken;

            Database::update('weekly_performance', [
                'quizzes_taken'   => $quizzesTaken,
                'correct_answers' => $totalCorrect,
                'average_score'   => round($avgScore, 1),
            ], ['id' => $existing['id']]);
        } else {
            Database::insert('weekly_performance', [
                'user_id'         => $userId,
                'week_start'      => $weekStart,
                'week_end'        => $weekEnd,
                'quizzes_taken'   => 1,
                'correct_answers' => $correctAnswers,
                'average_score'   => $score,
                'study_streak'    => 0,
            ]);
        }
    }

    public static function getForUser(int $userId, int $weeks = 12): array
    {
        return Database::fetchAll(
            "SELECT * FROM weekly_performance
             WHERE user_id = ? ORDER BY week_start DESC LIMIT ?",
            [$userId, $weeks]
        );
    }

    public static function getLeaderboard(int $limit = 50): array
    {
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        return Database::fetchAll(
            "SELECT wp.*, u.name, u.avatar_url, ux.league
             FROM weekly_performance wp
             JOIN users u ON u.id = wp.user_id
             LEFT JOIN user_xp ux ON ux.user_id = wp.user_id
             WHERE wp.week_start = ?
             ORDER BY wp.correct_answers DESC, wp.average_score DESC
             LIMIT ?",
            [$weekStart, $limit]
        );
    }
}