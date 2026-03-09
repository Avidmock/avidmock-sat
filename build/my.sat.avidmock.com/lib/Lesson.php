<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · Lesson
 *  Lesson CRUD, module navigation, progress tracking.
 * ═══════════════════════════════════════════════════════════════════
 */

class Lesson
{
    public static function getById(int $id): ?array
    {
        return Database::fetch("SELECT * FROM lessons WHERE id = ?", [$id]);
    }

    public static function getBySlug(string $slug): ?array
    {
        return Database::fetch("SELECT * FROM lessons WHERE slug = ? AND is_published = 1", [$slug]);
    }

    public static function getByModule(string $moduleSlug): array
    {
        return Database::fetchAll(
            "SELECT l.*, vp_agg.watched_count, vp_agg.total_users
             FROM lessons l
             LEFT JOIN (
                 SELECT lesson_id, COUNT(*) AS watched_count, COUNT(DISTINCT user_id) AS total_users
                 FROM video_progress WHERE is_complete = 1 GROUP BY lesson_id
             ) vp_agg ON vp_agg.lesson_id = l.id
             WHERE l.module_slug = ? AND l.is_published = 1
             ORDER BY l.order_index ASC",
            [$moduleSlug]
        );
    }

    /**
     * Get lesson with user's watch progress and quiz info.
     */
    public static function getForStudent(string $slug, int $userId): ?array
    {
        $lesson = self::getBySlug($slug);
        if (!$lesson) return null;

        // Watch progress
        $lesson['watch_progress'] = VideoProgress::get($userId, $lesson['id']);

        // Associated quiz
        $lesson['quiz'] = Quiz::getForStudent($slug, $userId);

        // Next/prev lessons in module
        $moduleLessons = self::getByModule($lesson['module_slug']);
        $currentIndex = null;
        foreach ($moduleLessons as $i => $ml) {
            if ($ml['id'] === $lesson['id']) { $currentIndex = $i; break; }
        }
        $lesson['prev_lesson'] = $currentIndex > 0 ? $moduleLessons[$currentIndex - 1] : null;
        $lesson['next_lesson'] = isset($moduleLessons[$currentIndex + 1]) ? $moduleLessons[$currentIndex + 1] : null;

        // Increment view count
        Database::query("UPDATE lessons SET view_count = view_count + 1 WHERE id = ?", [$lesson['id']]);

        return $lesson;
    }

    public static function create(array $data): int
    {
        return Database::insert('lessons', [
            'slug'         => self::generateSlug($data['title']),
            'title'        => trim($data['title']),
            'description'  => trim($data['description'] ?? ''),
            'module_slug'  => $data['module_slug'],
            'video_path'   => $data['video_path'] ?? null,
            'pdf_path'     => $data['pdf_path'] ?? null,
            'duration_secs'=> (int) ($data['duration_secs'] ?? 0),
            'is_published' => 0,
            'view_count'   => 0,
            'order_index'  => (int) ($data['order_index'] ?? 999),
            'meta_title'   => $data['meta_title'] ?? null,
            'meta_desc'    => $data['meta_desc'] ?? null,
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    public static function update(int $id, array $data): bool
    {
        $allowed = ['title', 'description', 'module_slug', 'video_path', 'pdf_path',
                     'duration_secs', 'order_index', 'meta_title', 'meta_desc'];
        $filtered = array_intersect_key($data, array_flip($allowed));
        $filtered['updated_at'] = date('Y-m-d H:i:s');
        return Database::update('lessons', $filtered, ['id' => $id]) > 0;
    }

    public static function togglePublish(int $id): bool
    {
        $lesson = self::getById($id);
        if (!$lesson) return false;
        return Database::update('lessons', ['is_published' => $lesson['is_published'] ? 0 : 1], ['id' => $id]) > 0;
    }

    private static function generateSlug(string $title): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
    }

    public static function adminList(array $filters = [], int $page = 1): array
    {
        $sql = "SELECT l.*, COUNT(DISTINCT vp.user_id) AS unique_viewers
                FROM lessons l
                LEFT JOIN video_progress vp ON vp.lesson_id = l.id AND vp.is_complete = 1";
        $params = [];
        $where = [];

        if (!empty($filters['module'])) {
            $where[] = "l.module_slug = ?";
            $params[] = $filters['module'];
        }
        if (!empty($filters['search'])) {
            $where[] = "l.title LIKE ?";
            $params[] = "%{$filters['search']}%";
        }

        if ($where) $sql .= " WHERE " . implode(' AND ', $where);
        $sql .= " GROUP BY l.id ORDER BY l.module_slug, l.order_index ASC";

        return Database::paginate($sql, $params, $page, 25);
    }
}