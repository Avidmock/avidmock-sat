<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · QuizAttempt
 * ═══════════════════════════════════════════════════════════════════
 */
class QuizAttempt
{
    public static function start(int $userId, int $quizId): ?array
    {
        global $pdo;

        $numStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM sat_quiz_attempts WHERE user_id = :uid AND quiz_id = :qid"
        );
        $numStmt->execute([':uid' => $userId, ':qid' => $quizId]);
        $attemptNum = (int)$numStmt->fetchColumn() + 1;

        static $cols = null;
        if ($cols === null) {
            $cols = $pdo->query("SHOW COLUMNS FROM sat_quiz_attempts")->fetchAll(PDO::FETCH_COLUMN);
        }

        $extraCols = ''; $extraVals = ''; $extraBind = [];

        if (in_array('created_at', $cols)) { $extraCols .= ', created_at'; $extraVals .= ', NOW()'; }
        if (in_array('started_at', $cols)) { $extraCols .= ', started_at'; $extraVals .= ', NOW()'; }
        if (in_array('total', $cols)) {
            $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM sat_quiz_questions WHERE quiz_id = :qid");
            $cntStmt->execute([':qid' => $quizId]);
            $extraCols .= ', total'; $extraVals .= ', :total';
            $extraBind[':total'] = (int)$cntStmt->fetchColumn();
        }

        $ins = $pdo->prepare("
            INSERT INTO sat_quiz_attempts (user_id, quiz_id, status, attempt_number {$extraCols})
            VALUES (:uid, :qid, 'in_progress', :num {$extraVals})
        ");
        $ins->execute(array_merge([':uid' => $userId, ':qid' => $quizId, ':num' => $attemptNum], $extraBind));

        return self::getById((int)$pdo->lastInsertId());
    }

    public static function getById(int $attemptId): ?array
    {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM sat_quiz_attempts WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $attemptId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function getResult(int $attemptId): ?array
    {
        return self::getById($attemptId);
    }

    public static function getAnswers(int $attemptId): array
    {
        global $pdo;
        $stmt = $pdo->prepare(
            "SELECT * FROM quiz_attempt_answers WHERE attempt_id = :id ORDER BY question_id ASC"
        );
        $stmt->execute([':id' => $attemptId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getHistory(int $userId, int $quizId, int $limit = 8): array
    {
        global $pdo;
        static $attemptCols = null;
        if ($attemptCols === null) {
            $attemptCols = $pdo->query("SHOW COLUMNS FROM sat_quiz_attempts")->fetchAll(PDO::FETCH_COLUMN);
        }
        $statusFilter = in_array('status', $attemptCols) ? "AND status = 'completed'" : '';
        $stmt = $pdo->prepare("
            SELECT score, completed_at FROM sat_quiz_attempts
            WHERE user_id = :uid AND quiz_id = :qid {$statusFilter}
            ORDER BY completed_at DESC LIMIT :lim
        ");
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':qid', $quizId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getScoreHistory(int $userId, ?int $quizId = null, int $limit = 6): array
    {
        global $pdo;
        static $attemptCols2 = null;
        if ($attemptCols2 === null) {
            $attemptCols2 = $pdo->query("SHOW COLUMNS FROM sat_quiz_attempts")->fetchAll(PDO::FETCH_COLUMN);
        }
        $statusFilter = in_array('status', $attemptCols2) ? "AND status = 'completed'" : '';
        $quizFilter   = $quizId !== null ? "AND quiz_id = :qid" : '';
        $stmt = $pdo->prepare("
            SELECT score, completed_at, DATE_FORMAT(completed_at, '%b %e') AS week
            FROM (
                SELECT score, completed_at FROM sat_quiz_attempts
                WHERE user_id = :uid AND score IS NOT NULL {$statusFilter} {$quizFilter}
                ORDER BY completed_at DESC LIMIT :lim
            ) sub ORDER BY completed_at ASC
        ");
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit,  PDO::PARAM_INT);
        if ($quizId !== null) $stmt->bindValue(':qid', $quizId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Call this when a quiz is submitted/completed.
     * Marks the lesson as complete in user_lesson_progress.
     */
    public static function markLessonCompleteFromQuiz(int $userId, int $quizId): void
    {
        global $pdo;

        // Get the lesson_slug from sat_quizzes
        $stmt = $pdo->prepare("SELECT lesson_slug FROM sat_quizzes WHERE id = :qid LIMIT 1");
        $stmt->execute([':qid' => $quizId]);
        $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$quiz || empty($quiz['lesson_slug'])) return;

        $lessonLink = '/math/' . $quiz['lesson_slug'];

        CategoryPerformance::markLessonComplete($userId, $lessonLink);
    }
}