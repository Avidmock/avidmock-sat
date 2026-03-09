<?php
/**
 * AVIDMOCK · Schedule
 */
class Schedule
{
    public static function generate(int $userId, array $opts = []): void
    {
        try {
            $user = User::findById($userId);
            if (!$user) return;

            $testDate   = $opts['test_date']   ?? $user['test_date']   ?? null;
            $studyHours = (float)($opts['study_hours'] ?? $user['study_hours'] ?? 1);

            if (!$testDate) return;

            $daysLeft   = max(1, (int)(new DateTime())->diff(new DateTime($testDate))->days);
            $scheduleId = self::upsertSchedule($userId, $testDate, $studyHours);

            $mathLessons = [];
            $rwLessons   = [];

            try {
                $mathLessons = Database::fetchAll(
                    "SELECT l.*, COALESCE(vp.is_complete, 0) AS watched
                     FROM lessons l
                     LEFT JOIN video_progress vp ON vp.lesson_id = l.id AND vp.user_id = ?
                     WHERE l.module_slug LIKE 'math%' AND l.is_published = 1
                     ORDER BY l.order_index ASC",
                    [$userId]
                );
            } catch (Throwable $e) { error_log('Schedule::generate math: ' . $e->getMessage()); }

            try {
                $rwLessons = Database::fetchAll(
                    "SELECT l.*, COALESCE(vp.is_complete, 0) AS watched
                     FROM lessons l
                     LEFT JOIN video_progress vp ON vp.lesson_id = l.id AND vp.user_id = ?
                     WHERE l.module_slug LIKE 'reading%' AND l.is_published = 1
                     ORDER BY l.order_index ASC",
                    [$userId]
                );
            } catch (Throwable $e) { error_log('Schedule::generate rw: ' . $e->getMessage()); }

            try {
                Database::query(
                    "DELETE FROM schedule_tasks WHERE user_id = ? AND task_date >= CURDATE() AND is_completed = 0",
                    [$userId]
                );
            } catch (Throwable $e) { error_log('Schedule::generate delete: ' . $e->getMessage()); }

            $minutesPerDay = (int)($studyHours * 60);
            $currentDate   = new DateTime();

            for ($day = 0; $day < min($daysLeft, 90); $day++) {
                $date = clone $currentDate;
                $date->modify("+{$day} days");
                $dateStr     = $date->format('Y-m-d');
                $minutesLeft = $minutesPerDay;
                $isMathDay   = ($day % 2 === 0);
                $sortOrder   = 0;

                if ($minutesLeft >= 15) {
                    self::addTask($userId, $scheduleId, $dateStr, 'spaced_review', null, 'Spaced Review', 15, $sortOrder++);
                    $minutesLeft -= 15;
                }

                $primaryLessons = $isMathDay ? $mathLessons : $rwLessons;
                $unwatched      = array_values(array_filter($primaryLessons, fn($l) => !$l['watched']));
                if (!empty($unwatched) && $minutesLeft >= 25) {
                    $lesson = $unwatched[0];
                    self::addTask($userId, $scheduleId, $dateStr, 'lesson', $lesson['id'], 'Watch: ' . $lesson['title'], 15, $sortOrder++);
                    self::addTask($userId, $scheduleId, $dateStr, 'quiz',   $lesson['id'], 'Quiz: '  . $lesson['title'], 10, $sortOrder++);
                    $minutesLeft -= 25;
                }

                $secondaryLessons = $isMathDay ? $rwLessons : $mathLessons;
                $unwatched2       = array_values(array_filter($secondaryLessons, fn($l) => !$l['watched']));
                if (!empty($unwatched2) && $minutesLeft >= 20) {
                    $lesson2 = $unwatched2[0];
                    self::addTask($userId, $scheduleId, $dateStr, 'lesson', $lesson2['id'], 'Watch: ' . $lesson2['title'], 10, $sortOrder++);
                    self::addTask($userId, $scheduleId, $dateStr, 'quiz',   $lesson2['id'], 'Quiz: '  . $lesson2['title'], 10, $sortOrder++);
                    $minutesLeft -= 20;
                }

                if ($day > 0 && $day % 7 === 0 && $minutesLeft >= 30) {
                    self::addTask($userId, $scheduleId, $dateStr, 'practice_test', null, 'Take a Practice Test', 60, $sortOrder++);
                    $minutesLeft -= 30;
                }

                if ($minutesLeft >= 10) {
                    self::addTask($userId, $scheduleId, $dateStr, 'review', null, 'AI Tutor — Review mistakes', $minutesLeft, $sortOrder++);
                }
            }
        } catch (Throwable $e) {
            error_log('Schedule::generate failed: ' . $e->getMessage());
        }
    }

    private static function upsertSchedule(int $userId, string $testDate, float $studyHours): int
    {
        try {
            $existing = Database::fetch("SELECT id FROM study_schedules WHERE user_id = ?", [$userId]);
            if ($existing) {
                Database::update('study_schedules', [
                    'test_date'    => $testDate,
                    'weekly_hours' => (int)round($studyHours * 7),
                    'generated_at' => date('Y-m-d H:i:s'),
                    'is_active'    => 1,
                ], ['user_id' => $userId]);
                return (int)$existing['id'];
            }
            return (int)Database::insert('study_schedules', [
                'user_id'      => $userId,
                'test_date'    => $testDate,
                'weekly_hours' => (int)round($studyHours * 7),
                'generated_at' => date('Y-m-d H:i:s'),
                'is_active'    => 1,
            ]);
        } catch (Throwable $e) {
            error_log('Schedule::upsertSchedule failed: ' . $e->getMessage());
            return 0;
        }
    }

    private static function addTask(int $userId, int $scheduleId, string $date, string $type, ?int $lessonId, string $title, int $minutes, int $sortOrder = 0): void
    {
        try {
            $row = [
                'schedule_id'    => $scheduleId,
                'user_id'        => $userId,
                'task_type'      => $type,
                'title'          => $title,
                'estimated_mins' => $minutes,
                'task_date'      => $date,
                'sort_order'     => $sortOrder,
                'is_completed'   => 0,
            ];
            if ($lessonId !== null && in_array($type, ['lesson', 'quiz'], true)) {
                $row['lesson_id'] = $lessonId;
            }
            Database::insert('schedule_tasks', $row);
        } catch (Throwable $e) {
            error_log('Schedule::addTask failed: ' . $e->getMessage());
        }
    }

    public static function getToday(int $userId): array
    {
        try {
            return Database::fetchAll(
                "SELECT *, is_completed AS is_complete, estimated_mins AS duration_min
                 FROM schedule_tasks WHERE user_id = ? AND task_date = CURDATE()
                 ORDER BY is_completed ASC, sort_order ASC, id ASC",
                [$userId]
            );
        } catch (Throwable $e) { error_log('Schedule::getToday: ' . $e->getMessage()); return []; }
    }

    public static function getRange(int $userId, string $from, string $to): array
    {
        try {
            return Database::fetchAll(
                "SELECT *, is_completed AS is_complete, estimated_mins AS duration_min
                 FROM schedule_tasks WHERE user_id = ? AND task_date BETWEEN ? AND ?
                 ORDER BY task_date ASC, sort_order ASC, id ASC",
                [$userId, $from, $to]
            );
        } catch (Throwable $e) { error_log('Schedule::getRange: ' . $e->getMessage()); return []; }
    }

    public static function markComplete(int $taskId, int $userId): bool
    {
        try {
            return Database::update('schedule_tasks', [
                'is_completed' => 1,
                'completed_at' => date('Y-m-d H:i:s'),
            ], ['id' => $taskId, 'user_id' => $userId]) > 0;
        } catch (Throwable $e) { error_log('Schedule::markComplete: ' . $e->getMessage()); return false; }
    }

    public static function regenerate(int $userId): void { self::generate($userId); }

    public static function getPreferences(int $userId): array
    {
        try {
            return Database::fetch("SELECT * FROM study_schedules WHERE user_id = ? LIMIT 1", [$userId]) ?? [];
        } catch (Throwable $e) { error_log('Schedule::getPreferences: ' . $e->getMessage()); return []; }
    }

    public static function getWeakTopics(int $userId, int $limit = 5): array
    {
        try {
            return Database::fetchAll(
                "SELECT mt.name, ROUND(AVG(qaa.is_correct) * 100) AS score
                 FROM quiz_attempt_answers qaa
                 JOIN quiz_attempts qa ON qa.id = qaa.quiz_attempt_id
                 JOIN questions q ON q.id = qaa.question_id
                 LEFT JOIN micro_topics mt ON mt.id = q.micro_topic_id
                 WHERE qa.user_id = ? AND mt.name IS NOT NULL
                 GROUP BY mt.id, mt.name HAVING COUNT(*) >= 2
                 ORDER BY score ASC LIMIT ?",
                [$userId, $limit]
            );
        } catch (Throwable $e) { error_log('Schedule::getWeakTopics: ' . $e->getMessage()); return []; }
    }

    public static function getWeeklyStats(int $userId): array
    {
        try {
            $weekStart = date('Y-m-d', strtotime('monday this week'));
            $weekEnd   = date('Y-m-d', strtotime('sunday this week'));
            $stats = Database::fetch(
                "SELECT COUNT(*) AS total_tasks, SUM(is_completed) AS completed_tasks,
                        SUM(estimated_mins) AS total_mins,
                        SUM(CASE WHEN is_completed = 1 THEN estimated_mins ELSE 0 END) AS completed_mins
                 FROM schedule_tasks WHERE user_id = ? AND task_date BETWEEN ? AND ?",
                [$userId, $weekStart, $weekEnd]
            ) ?? [];
            $stats['completion_rate'] = ($stats['total_tasks'] ?? 0) > 0
                ? round(($stats['completed_tasks'] / $stats['total_tasks']) * 100) : 0;
            return $stats;
        } catch (Throwable $e) {
            error_log('Schedule::getWeeklyStats: ' . $e->getMessage());
            return ['total_tasks'=>0,'completed_tasks'=>0,'total_mins'=>0,'completed_mins'=>0,'completion_rate'=>0];
        }
    }
}