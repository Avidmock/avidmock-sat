<?php
/**
 * lib/QuizProgress.php
 *
 * Reads and writes quiz_progress table so students can resume mid-quiz.
 */
class QuizProgress
{
    /* ── Get saved progress for user + quiz (most recent in-progress) ── */
    public static function get(int $userId, int $quizId): ?array
    {
        global $pdo;

        // Get the most recent in-progress attempt for this user+quiz
        $stmt = $pdo->prepare("
            SELECT qp.*
            FROM quiz_progress qp
            INNER JOIN sat_quiz_attempts qa ON qa.id = qp.attempt_id
            WHERE qp.user_id = :uid
              AND qp.quiz_id = :qid
              AND qa.status  = 'in_progress'
            ORDER BY qp.updated_at DESC
            LIMIT 1
        ");
        $stmt->execute([':uid' => $userId, ':qid' => $quizId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /* ── Save / upsert ─────────────────────────────────────────────── */
    public static function save(
        int    $userId,
        int    $quizId,
        int    $attemptId,
        int    $currentQuestion,
        array  $answeredQuestions = [],
        array  $flaggedQuestions  = [],
        array  $progressData      = []
    ): bool {
        global $pdo;

        $stmt = $pdo->prepare("
            INSERT INTO quiz_progress
                (user_id, quiz_id, attempt_id, current_question,
                 answered_questions, flagged_questions, progress_data)
            VALUES
                (:uid, :qid, :aid, :curr, :answered, :flagged, :progress)
            ON DUPLICATE KEY UPDATE
                current_question   = VALUES(current_question),
                answered_questions = VALUES(answered_questions),
                flagged_questions  = VALUES(flagged_questions),
                progress_data      = VALUES(progress_data),
                updated_at         = CURRENT_TIMESTAMP
        ");

        return $stmt->execute([
            ':uid'      => $userId,
            ':qid'      => $quizId,
            ':aid'      => $attemptId,
            ':curr'     => $currentQuestion,
            ':answered' => json_encode(array_values($answeredQuestions), JSON_HEX_TAG),
            ':flagged'  => json_encode(array_values($flaggedQuestions),  JSON_HEX_TAG),
            ':progress' => json_encode($progressData, JSON_HEX_TAG),
        ]);
    }

    /* ── Delete after quiz is submitted ───────────────────────────── */
    public static function delete(int $attemptId): void
    {
        global $pdo;
        $pdo->prepare("DELETE FROM quiz_progress WHERE attempt_id = :id")
            ->execute([':id' => $attemptId]);
    }
}