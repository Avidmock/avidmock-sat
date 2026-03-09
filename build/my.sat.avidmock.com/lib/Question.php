<?php
/**
 * lib/Question.php
 *
 * Reads questions from sat_quiz_questions.
 *
 * Uses global $pdo directly — no Database:: helper class needed.
 *
 * Columns detected at runtime via SHOW COLUMNS so missing optional
 * columns never cause a fatal error.
 */
class Question
{
    /* ── Single question by ID ── */
    public static function getById(int $id): ?array
    {
        global $pdo;
        $stmt = $pdo->prepare(
            "SELECT * FROM sat_quiz_questions WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::normalise($row, $pdo) : null;
    }

    /* ── All questions for a quiz, ordered by position ── */
    public static function getByQuiz(int $quizId): array
    {
        global $pdo;
        $stmt = $pdo->prepare(
            "SELECT * FROM sat_quiz_questions
             WHERE quiz_id = :qid
             ORDER BY position ASC, id ASC"
        );
        $stmt->execute([':qid' => $quizId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(static function (array $row) use ($pdo) {
            return self::normalise($row, $pdo);
        }, $rows);
    }

    /* ── Normalise raw DB row ── */
    private static function normalise(array $row, PDO $pdo): array
    {
        static $cols = null;
        if ($cols === null) {
            $cols = $pdo->query("SHOW COLUMNS FROM sat_quiz_questions")
                        ->fetchAll(PDO::FETCH_COLUMN);
        }

        $has = static function (string $col) use ($cols): bool {
            return in_array($col, $cols, true);
        };

        $optA = $row['option_a'] ?? '';
        $optB = $row['option_b'] ?? '';
        $optC = $row['option_c'] ?? '';
        $optD = $row['option_d'] ?? '';

        /* ── Parse explanation_image: may be a JSON array or a single URL ── */
        $rawImg = $has('explanation_image') ? ($row['explanation_image'] ?? '') : '';
        if ($rawImg && $rawImg[0] === '[') {
            $explanationImages = json_decode($rawImg, true) ?? [];
        } elseif ($rawImg) {
            $explanationImages = [$rawImg];
        } else {
            $explanationImages = [];
        }

        return [
            /* ── Primary ── */
            'id'               => (int)$row['id'],
            'quiz_id'          => (int)$row['quiz_id'],
            'position'         => (int)($row['position'] ?? 1),

            /* ── Body ── */
            'type'             => $row['type'] ?? 'mcq',
            'stem'             => $row['stem'] ?? '',
            'difficulty'       => $has('difficulty') ? ($row['difficulty'] ?? 'medium') : 'medium',
            'points'           => $has('points')     ? (int)($row['points'] ?? 1) : 1,

            /* ── Raw option columns ── */
            'option_a'         => $optA,
            'option_b'         => $optB,
            'option_c'         => $optC,
            'option_d'         => $optD,

            /* ── Pre-built choices map ── */
            'choices'          => [
                'A' => $optA,
                'B' => $optB,
                'C' => $optC,
                'D' => $optD,
            ],

            /* ── correct_answer returned UPPERCASE ── */
            'correct_answer'   => strtoupper($row['correct_answer'] ?? 'A'),

            /* ── Explanation ── */
            'explanation'       => $has('explanation')       ? ($row['explanation']       ?? '') : '',
            'explanation_html'  => $has('explanation_html')  ? ($row['explanation_html']  ?? '') : '',
            'explanation_image' => $rawImg,          // raw string (for legacy code)
            'explanation_images'=> $explanationImages, // normalised array (for quiz.php)

            /* ── Hint ── */
            'hint'             => $has('hint') ? ($row['hint'] ?? '') : '',

            /* ── Metadata ── */
            'domain'           => $has('domain') ? ($row['domain'] ?? '') : '',
            'skill'            => $has('skill')  ? ($row['skill']  ?? '') : '',

            /* ── Legacy aliases ── */
            'choice_a'         => $optA,
            'choice_b'         => $optB,
            'choice_c'         => $optC,
            'choice_d'         => $optD,
            'order_index'      => (int)($row['position'] ?? 0),
            'latex_enabled'    => 0,
            'image_path'       => null,
        ];
    }
}