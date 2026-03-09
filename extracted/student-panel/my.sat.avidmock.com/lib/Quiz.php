<?php
/**
 * lib/Quiz.php
 *
 * Loads quiz rows from sat_quizzes.
 * Detects every optional column via SHOW COLUMNS — never crashes on missing ones.
 */
class Quiz
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ── Instance methods (quiz.php uses `new Quiz($pdo)`) ── */
    public function getById(int $quizId): ?array
    {
        return self::findById($quizId, $this->pdo);
    }

    public function getByLesson(string $slug): ?array
    {
        return self::findByLesson($slug, $this->pdo);
    }

    /* ── Static helper (results.php / review.php) ── */
    public static function getById_static(int $quizId): ?array
    {
        global $pdo;
        return self::findById($quizId, $pdo);
    }

    /* ── Core finders ── */
    private static function findById(int $quizId, PDO $pdo): ?array
    {
        $stmt = $pdo->prepare("SELECT * FROM sat_quizzes WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $quizId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::normalise($row, $pdo) : null;
    }

    private static function findByLesson(string $slug, PDO $pdo): ?array
    {
        static $cols = null;
        if ($cols === null) {
            $cols = $pdo->query("SHOW COLUMNS FROM sat_quizzes")
                        ->fetchAll(PDO::FETCH_COLUMN);
        }

        $statusFilter = in_array('status', $cols)     ? "AND status = 'published'" : '';
        $orderBy      = in_array('created_at', $cols) ? 'ORDER BY created_at DESC' : 'ORDER BY id DESC';

        $stmt = $pdo->prepare(
            "SELECT * FROM sat_quizzes
             WHERE lesson_slug = :slug {$statusFilter}
             {$orderBy} LIMIT 1"
        );
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::normalise($row, $pdo) : null;
    }

    /* ── Normalise raw DB row ── */
    private static function normalise(array $row, PDO $pdo): array
    {
        static $cols = null;
        if ($cols === null) {
            $cols = $pdo->query("SHOW COLUMNS FROM sat_quizzes")
                        ->fetchAll(PDO::FETCH_COLUMN);
        }

        $has = static function (string $col) use ($cols): bool {
            return in_array($col, $cols, true);
        };

        return [
            'id'             => (int)$row['id'],
            'title'          => $row['title']       ?? 'Untitled Quiz',
            'lesson_slug'    => $row['lesson_slug'] ?? '',
            'section'        => $has('section')       ? ($row['section']       ?? '') : '',
            'instructions'   => $has('instructions')  ? ($row['instructions']  ?? '') : '',
            'status'         => $has('status')        ? ($row['status']        ?? 'published') : 'published',
            'time_limit'     => $has('time_limit')    ? (int)($row['time_limit']    ?? 0)   : 0,
            'timer_type'     => $has('timer_type')    ? ($row['timer_type']    ?? 'total')  : 'total',
            'pass_threshold' => $has('pass_threshold') ? (int)($row['pass_threshold'] ?? 60) : 60,
            'show_hints'     => $has('show_hints')    ? (bool)$row['show_hints']    : true,
            'shuffle_q'      => $has('shuffle_q')     ? (bool)$row['shuffle_q']     : false,
            'shuffle_opts'   => $has('shuffle_opts')  ? (bool)$row['shuffle_opts']  : false,
            'xp_reward'      => $has('xp_reward')     ? (int)$row['xp_reward']      : 50,
            'created_at'     => $has('created_at')    ? ($row['created_at']    ?? null) : null,
            'updated_at'     => $has('updated_at')    ? ($row['updated_at']    ?? null) : null,
        ];
    }
}