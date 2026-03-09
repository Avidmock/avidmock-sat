<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · CategoryPerformance
 *  Per-subject/domain mastery tracking for SAT Math & Reading/Writing.
 *
 *  Actual category_performance columns (verified via DESCRIBE):
 *  id, user_id, category, total_quizzes, correct_answers,
 *  avg_score, mastery_level, created_at, updated_at
 * ═══════════════════════════════════════════════════════════════════
 */

class CategoryPerformance
{
    /**
     * Get a single category row for a user.
     * Used by the dashboard: CategoryPerformance::get($userId, 'math')
     * Returns array with avg_score, mastery_level, total_quizzes or empty array.
     */
    public static function get(int $userId, string $category): array
    {
        $row = Database::fetch(
            "SELECT * FROM category_performance WHERE user_id = ? AND category = ?",
            [$userId, $category]
        );

        if (!$row) {
            return [
                'avg_score'     => 0,
                'mastery_level' => 0,
                'total_quizzes' => 0,
            ];
        }

        return $row;
    }

    /**
     * Update category performance after a quiz is submitted.
     */
    public static function updateAfterQuiz(int $userId, string $category, float $score): void
    {
        $parts = explode('/', trim($category, '/'));
        $cat   = count($parts) >= 2 ? $parts[1] : $parts[0];

        $existing = Database::fetch(
            "SELECT * FROM category_performance WHERE user_id = ? AND category = ?",
            [$userId, $cat]
        );

        if ($existing) {
            $total    = $existing['total_quizzes'] + 1;
            $avgScore = (($existing['avg_score'] * $existing['total_quizzes']) + $score) / $total;
            $mastery  = self::calculateMastery($avgScore);

            Database::update('category_performance', [
                'total_quizzes' => $total,
                'avg_score'     => round($avgScore, 1),
                'mastery_level' => $mastery,
            ], ['id' => $existing['id']]);
        } else {
            Database::insert('category_performance', [
                'user_id'         => $userId,
                'category'        => $cat,
                'total_quizzes'   => 1,
                'correct_answers' => 0,
                'avg_score'       => $score,
                'mastery_level'   => self::calculateMastery($score),
            ]);
        }
    }

    /**
     * Calculate mastery level (0-100) from an average score percentage.
     */
    public static function calculateMastery(float $avgScore): int
    {
        if ($avgScore >= 90) return 100;
        if ($avgScore >= 80) return 80;
        if ($avgScore >= 70) return 60;
        if ($avgScore >= 60) return 40;
        if ($avgScore >= 50) return 20;
        return 0;
    }

    /**
     * Get all category rows for a student.
     */
    public static function getForStudent(int $userId): array
    {
        return Database::fetchAll(
            "SELECT * FROM category_performance WHERE user_id = ? ORDER BY category",
            [$userId]
        );
    }

    /**
     * Get weakest categories for a student.
     */
    public static function getWeakest(int $userId, int $limit = 3): array
    {
        return Database::fetchAll(
            "SELECT * FROM category_performance
             WHERE user_id = ? AND total_quizzes >= 1
             ORDER BY avg_score ASC LIMIT ?",
            [$userId, $limit]
        );
    }

    /**
     * Get strongest categories for a student.
     */
    public static function getStrongest(int $userId, int $limit = 3): array
    {
        return Database::fetchAll(
            "SELECT * FROM category_performance
             WHERE user_id = ? AND total_quizzes >= 1
             ORDER BY avg_score DESC LIMIT ?",
            [$userId, $limit]
        );
    }

    /**
     * Get Math performance — overall + per-domain breakdown.
     */
    public static function getMath(int $userId): array
    {
        $rows = Database::fetchAll(
            "SELECT category, avg_score, mastery_level, total_quizzes
             FROM category_performance
             WHERE user_id = ?",
            [$userId]
        );

        $byCategory = [];
        foreach ($rows as $row) {
            $byCategory[$row['category']] = $row;
        }

        $domain = static function (string $slug) use ($byCategory): array {
            $row = $byCategory[$slug] ?? null;
            return [
                'accuracy' => $row ? intval(round($row['avg_score'])) : 0,
                'mastered' => $row ? intval($row['mastery_level'])    : 0,
            ];
        };

        $mathDomains  = ['algebra', 'advanced_math', 'problem_solving', 'geometry'];
        $accuracyVals = [];
        $masteryVals  = [];
        foreach ($mathDomains as $slug) {
            $row = $byCategory[$slug] ?? null;
            if ($row && $row['total_quizzes'] > 0) {
                $accuracyVals[] = floatval($row['avg_score']);
                $masteryVals[]  = intval($row['mastery_level']);
            }
        }

        return [
            'overall' => [
                'accuracy'   => count($accuracyVals) ? intval(round(array_sum($accuracyVals) / count($accuracyVals))) : 0,
                'mastered'   => count($masteryVals)  ? intval(round(array_sum($masteryVals)  / count($masteryVals)))  : 0,
                'total'      => count($accuracyVals),
                'best_score' => 0,
            ],
            'algebra'         => $domain('algebra'),
            'advanced_math'   => $domain('advanced_math'),
            'problem_solving' => $domain('problem_solving'),
            'geometry'        => $domain('geometry'),
        ];
    }

    /**
     * Get Reading & Writing performance — overall + per-domain breakdown.
     */
    public static function getRW(int $userId): array
    {
        $rows = Database::fetchAll(
            "SELECT category, avg_score, mastery_level, total_quizzes
             FROM category_performance
             WHERE user_id = ?",
            [$userId]
        );

        $byCategory = [];
        foreach ($rows as $row) {
            $byCategory[$row['category']] = $row;
        }

        $domain = static function (string $slug) use ($byCategory): array {
            $row = $byCategory[$slug] ?? null;
            return [
                'accuracy' => $row ? intval(round($row['avg_score'])) : 0,
                'mastered' => $row ? intval($row['mastery_level'])    : 0,
            ];
        };

        $rwDomains    = ['information_ideas', 'craft_structure', 'expression_ideas', 'standard_english'];
        $accuracyVals = [];
        $masteryVals  = [];
        foreach ($rwDomains as $slug) {
            $row = $byCategory[$slug] ?? null;
            if ($row && $row['total_quizzes'] > 0) {
                $accuracyVals[] = floatval($row['avg_score']);
                $masteryVals[]  = intval($row['mastery_level']);
            }
        }

        return [
            'overall' => [
                'accuracy'   => count($accuracyVals) ? intval(round(array_sum($accuracyVals) / count($accuracyVals))) : 0,
                'mastered'   => count($masteryVals)  ? intval(round(array_sum($masteryVals)  / count($masteryVals)))  : 0,
                'total'      => count($accuracyVals),
                'best_score' => 0,
            ],
            'information_ideas' => $domain('information_ideas'),
            'craft_structure'   => $domain('craft_structure'),
            'expression_ideas'  => $domain('expression_ideas'),
            'standard_english'  => $domain('standard_english'),
        ];
    }

    /**
     * Get which individual lessons are complete for a student.
     * Returns [ '/learn/math/algebra/solving-linear-equations/' => true, ... ]
     */
    public static function getLessonProgress(int $userId): array
    {
        $rows = Database::fetchAll(
            "SELECT lesson_link
             FROM user_lesson_progress
             WHERE user_id = ? AND completed = 1",
            [$userId]
        );

        $map = [];
        foreach ($rows as $row) {
            $map[$row['lesson_link']] = true;
        }
        return $map;
    }

    /**
     * Mark a lesson as complete for a student.
     */
    public static function markLessonComplete(int $userId, string $lessonLink): void
    {
        $existing = Database::fetch(
            "SELECT id FROM user_lesson_progress WHERE user_id = ? AND lesson_link = ?",
            [$userId, $lessonLink]
        );

        if ($existing) {
            Database::update('user_lesson_progress', [
                'completed'    => 1,
                'completed_at' => date('Y-m-d H:i:s'),
            ], ['id' => $existing['id']]);
        } else {
            Database::insert('user_lesson_progress', [
                'user_id'      => $userId,
                'lesson_link'  => $lessonLink,
                'completed'    => 1,
                'completed_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}