<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · VideoProgress
 *  Track video watch progress per user per lesson.
 * ═══════════════════════════════════════════════════════════════════
 */

class VideoProgress
{
    public static function save(int $userId, int $lessonId, int $secondsWatched): void
    {
        $lesson = Database::fetch("SELECT duration_secs FROM lessons WHERE id = ?", [$lessonId]);
        $duration = (int) ($lesson['duration_secs'] ?? 0);
        $isComplete = $duration > 0 && $secondsWatched >= ($duration * 0.85);

        $existing = Database::fetch(
            "SELECT id, seconds_watched FROM video_progress WHERE user_id = ? AND lesson_id = ?",
            [$userId, $lessonId]
        );

        if ($existing) {
            $maxWatched = max($existing['seconds_watched'], $secondsWatched);
            Database::update('video_progress', [
                'seconds_watched' => $maxWatched,
                'is_complete'     => (int) ($isComplete || ($duration > 0 && $maxWatched >= $duration * 0.85)),
                'updated_at'      => date('Y-m-d H:i:s'),
            ], ['id' => $existing['id']]);
        } else {
            Database::insert('video_progress', [
                'user_id'         => $userId,
                'lesson_id'       => $lessonId,
                'seconds_watched' => $secondsWatched,
                'is_complete'     => (int) $isComplete,
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public static function get(int $userId, int $lessonId): array
    {
        $row = Database::fetch(
            "SELECT seconds_watched, is_complete, updated_at FROM video_progress WHERE user_id = ? AND lesson_id = ?",
            [$userId, $lessonId]
        );
        return $row ?: ['seconds_watched' => 0, 'is_complete' => false, 'updated_at' => null];
    }

    public static function getCompletedCount(int $userId): int
    {
        return (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM video_progress WHERE user_id = ? AND is_complete = 1",
            [$userId]
        );
    }

    public static function getModuleProgress(int $userId, string $moduleSlug): array
    {
        $total = (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM lessons WHERE module_slug = ? AND is_published = 1",
            [$moduleSlug]
        );
        $completed = (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM video_progress vp
             JOIN lessons l ON l.id = vp.lesson_id
             WHERE vp.user_id = ? AND l.module_slug = ? AND vp.is_complete = 1",
            [$userId, $moduleSlug]
        );
        return [
            'total'     => $total,
            'completed' => $completed,
            'percent'   => $total > 0 ? round(($completed / $total) * 100) : 0,
        ];
    }
}