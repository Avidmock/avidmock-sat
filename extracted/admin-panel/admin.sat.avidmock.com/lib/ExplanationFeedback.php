<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · ExplanationFeedback
 *  Student feedback on quiz explanations (thumbs up/down).
 * ═══════════════════════════════════════════════════════════════════
 */

class ExplanationFeedback
{
    public static function save(int $userId, int $questionId, string $type, ?string $comment = null): void
    {
        $existing = Database::fetch(
            "SELECT id FROM explanation_feedback WHERE user_id = ? AND question_id = ?",
            [$userId, $questionId]
        );

        $data = [
            'user_id'          => $userId,
            'question_id'      => $questionId,
            'feedback_type'    => $type, // 'helpful' or 'not_helpful'
            'feedback_comment' => $comment,
            'created_at'       => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            Database::update('explanation_feedback', $data, ['id' => $existing['id']]);
        } else {
            Database::insert('explanation_feedback', $data);
        }
    }

    public static function getForQuestion(int $questionId): array
    {
        $stats = Database::fetch(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN feedback_type = 'helpful' THEN 1 ELSE 0 END) AS helpful,
                SUM(CASE WHEN feedback_type = 'not_helpful' THEN 1 ELSE 0 END) AS not_helpful
             FROM explanation_feedback WHERE question_id = ?",
            [$questionId]
        );

        $comments = Database::fetchAll(
            "SELECT feedback_comment, feedback_type, created_at
             FROM explanation_feedback
             WHERE question_id = ? AND feedback_comment IS NOT NULL
             ORDER BY created_at DESC",
            [$questionId]
        );

        return ['stats' => $stats, 'comments' => $comments];
    }

    /**
     * Get worst-rated explanations (admin: what needs rewriting).
     */
    public static function getWorstRated(int $limit = 20): array
    {
        return Database::fetchAll(
            "SELECT q.id, q.stem, q.explanation,
                    COUNT(ef.id) AS total_feedback,
                    SUM(CASE WHEN ef.feedback_type = 'not_helpful' THEN 1 ELSE 0 END) AS negative,
                    ROUND(SUM(CASE WHEN ef.feedback_type = 'not_helpful' THEN 1 ELSE 0 END) / COUNT(ef.id) * 100, 1) AS negative_pct
             FROM explanation_feedback ef
             JOIN questions q ON q.id = ef.question_id
             GROUP BY q.id
             HAVING total_feedback >= 5 AND negative_pct >= 30
             ORDER BY negative_pct DESC
             LIMIT ?",
            [$limit]
        );
    }
}