<?php
/**
 * api/get-dashboard.php
 * GET: All dashboard statistics as JSON (used by dashboard JS refresh)
 *
 * Response JSON:
 *   {
 *     user: { name, target_score, test_date, days_until_test },
 *     stats: { best_score, avg_score, improvement, total_questions, total_tests },
 *     streak: { current, longest },
 *     math: { accuracy, mastery },
 *     rw: { accuracy, mastery },
 *     today_plan: [ { id, title, task_type, subject, duration_min, is_complete, link } ],
 *     leaderboard: { user_rank, user_points, top5: [] },
 *     upcoming_sessions: [],
 *     recent_achievements: []
 *   }
 */

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Schedule.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/StudyStreak.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Leaderboard.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Achievement.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/CategoryPerformance.php';

/* ── Headers ── */
header('Content-Type: application/json');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

/* ── Auth ── */
Auth::requireLogin();
$userId = (int) $_SESSION['user_id'];

/* ── Method guard ── */
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

/* ── AJAX guard ── */
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

try {

    /* ── User + goals ── */
    $user        = User::find($userId);
    $testDate    = $user['test_date'] ?? null;
    $daysUntilTest = null;
    if ($testDate) {
        $ts = strtotime($testDate);
        if ($ts && $ts > time()) {
            $daysUntilTest = (int) ceil(($ts - time()) / 86400);
        }
    }

    /* ── Core stats ── */
    $stats = User::getStats($userId);

    /* ── Streak ── */
    $streakData = StudyStreak::get($userId);

    /* ── Category performance ── */
    $mathPerf = CategoryPerformance::getSubject($userId, 'math');
    $rwPerf   = CategoryPerformance::getSubject($userId, 'reading_writing');

    /* ── Today's plan ── */
    $todayPlan = Schedule::getToday($userId);

    /* ── Leaderboard ── */
    $lb      = Leaderboard::getTop50($userId);
    $topFive = array_slice($lb['rows'] ?? [], 0, 5);

    /* ── Sessions ── */
    $sessions = Session::getUpcoming($userId, 3);

    /* ── Achievements ── */
    $recentBadges  = Achievement::getUnlocked($userId, 3);
    $unlockedCount = count(Achievement::getUnlocked($userId));
    $totalBadges   = count(Achievement::getAll($userId));

    /* ── Build response ── */
    echo json_encode([
        'success' => true,
        'generated_at' => date('c'),

        'user' => [
            'name'           => $user['name']         ?? '',
            'target_score'   => (int) ($user['target_score'] ?? 1200),
            'test_date'      => $testDate,
            'days_until_test'=> $daysUntilTest,
        ],

        'stats' => [
            'best_score'      => (int) ($stats['best_score']      ?? 0),
            'avg_score'       => (int) ($stats['avg_score']       ?? 0),
            'improvement'     => (int) ($stats['improvement']     ?? 0),
            'total_questions' => (int) ($stats['total_questions'] ?? 0),
            'total_tests'     => (int) ($stats['total_tests']     ?? 0),
            'last_test_date'  =>       ($stats['last_test_date']  ?? null),
        ],

        'streak' => [
            'current' => (int) ($streakData['current'] ?? 0),
            'longest' => (int) ($streakData['longest'] ?? 0),
        ],

        'math' => [
            'accuracy' => (int) ($mathPerf['accuracy'] ?? 0),
            'mastery'  => (int) ($mathPerf['mastery']  ?? 0),
        ],

        'reading_writing' => [
            'accuracy' => (int) ($rwPerf['accuracy'] ?? 0),
            'mastery'  => (int) ($rwPerf['mastery']  ?? 0),
        ],

        'today_plan' => array_map(function ($t) {
            return [
                'id'           => (int)  $t['id'],
                'title'        =>        $t['title']        ?? 'Study Session',
                'task_type'    =>        $t['task_type']    ?? 'lesson',
                'subject'      =>        $t['subject']      ?? '',
                'duration_min' => (int) ($t['duration_min'] ?? 20),
                'is_complete'  => (bool) $t['is_complete'],
                'link'         =>        $t['link']         ?? '#',
            ];
        }, $todayPlan),

        'leaderboard' => [
            'user_rank'   => (int) ($lb['user_rank']   ?? 0),
            'user_points' => (int) ($lb['user_points'] ?? 0),
            'top5'        => array_map(function ($r) use ($userId) {
                return [
                    'user_id' => (int)  $r['user_id'],
                    'name'    =>        $r['name']   ?? '',
                    'points'  => (int) ($r['points'] ?? 0),
                    'is_me'   => (int)  $r['user_id'] === $userId,
                ];
            }, $topFive),
        ],

        'upcoming_sessions' => array_map(function ($s) {
            return [
                'id'           => (int)  $s['id'],
                'title'        =>        $s['title']       ?? '',
                'subject'      =>        $s['subject']     ?? '',
                'starts_at'    =>        $s['starts_at']   ?? '',
                'duration_min' => (int) ($s['duration_min'] ?? 60),
                'tutor_name'   =>        $s['tutor_name']  ?? '',
                'enrolled'     => (bool) ($s['enrolled']   ?? false),
            ];
        }, $sessions),

        'achievements' => [
            'unlocked_count' => $unlockedCount,
            'total_count'    => $totalBadges,
            'recent'         => array_map(function ($b) {
                return [
                    'slug'        => $b['slug']       ?? '',
                    'label'       => $b['label']      ?? '',
                    'icon'        => $b['icon_svg']   ?? '',
                    'unlocked_at' => $b['unlocked_at'] ?? '',
                ];
            }, $recentBadges),
        ],
    ]);

} catch (Throwable $e) {
    error_log('get-dashboard.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Dashboard data unavailable']);
}