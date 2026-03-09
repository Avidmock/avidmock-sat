<?php
/**
 * admin.sat.avidmock.com / api / get-stats.php
 *
 * GET: Dashboard statistics for admin panel
 *
 * Response JSON:
 *   {
 *     total_students, active_today, active_week, total_quizzes,
 *     total_questions, avg_score, total_practice_tests,
 *     avg_streak, pro_subscribers, revenue_mtd
 *   }
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_name('avidmock_admin_session');
    session_set_cookie_params([
        'lifetime' => 86400 * 30,
        'path'     => '/',
        'domain'   => 'admin.sat.avidmock.com',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/* ── Config ── */
$configPaths = [
    __DIR__ . '/../config/config.php',
    __DIR__ . '/../config/db.php',
];
foreach ($configPaths as $p) {
    if (file_exists($p)) { require_once $p; break; }
}
if (!isset($pdo) && isset($db))   $pdo = $db;
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database not configured']);
    exit;
}

/* ── Auth ── */
$adminId   = $_SESSION['admin_id']   ?? null;
$adminRole = $_SESSION['admin_role'] ?? null;
if (!$adminId || !in_array($adminRole, ['admin', 'teacher'], true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'GET required']);
    exit;
}

try {
    /* Total students */
    $totalStudents = (int) $pdo->query(
        "SELECT COUNT(*) FROM users WHERE role = 'student' AND is_active = 1"
    )->fetchColumn();

    /* Active today */
    $activeToday = (int) $pdo->query(
        "SELECT COUNT(*) FROM users WHERE role = 'student' AND DATE(last_login_at) = CURDATE()"
    )->fetchColumn();

    /* Active this week */
    $activeWeek = (int) $pdo->query(
        "SELECT COUNT(*) FROM users WHERE role = 'student' AND last_login_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
    )->fetchColumn();

    /* Total quizzes */
    $totalQuizzes = 0;
    try {
        $totalQuizzes = (int) $pdo->query("SELECT COUNT(*) FROM sat_quizzes")->fetchColumn();
    } catch (Throwable) {
        try { $totalQuizzes = (int) $pdo->query("SELECT COUNT(*) FROM quizzes")->fetchColumn(); } catch (Throwable) {}
    }

    /* Total questions */
    $totalQuestions = 0;
    try {
        $totalQuestions = (int) $pdo->query("SELECT COUNT(*) FROM sat_quiz_questions")->fetchColumn();
    } catch (Throwable) {
        try { $totalQuestions = (int) $pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn(); } catch (Throwable) {}
    }

    /* Average quiz score */
    $avgScore = 0;
    try {
        $avgScore = (float) $pdo->query(
            "SELECT COALESCE(AVG(score), 0) FROM sat_quiz_attempts WHERE status = 'completed'"
        )->fetchColumn();
    } catch (Throwable) {}

    /* Total practice test completions */
    $totalPracticeTests = 0;
    try {
        $totalPracticeTests = (int) $pdo->query(
            "SELECT COUNT(*) FROM practice_test_attempts WHERE status = 'submitted'"
        )->fetchColumn();
    } catch (Throwable) {}

    /* Average streak */
    $avgStreak = 0;
    try {
        $avgStreak = (float) $pdo->query(
            "SELECT COALESCE(AVG(current_streak), 0) FROM study_streaks"
        )->fetchColumn();
    } catch (Throwable) {}

    /* Pro subscribers */
    $proSubscribers = 0;
    try {
        $proSubscribers = (int) $pdo->query(
            "SELECT COUNT(*) FROM users WHERE subscription_plan IN ('pro', 'family') AND is_active = 1"
        )->fetchColumn();
    } catch (Throwable) {}

    echo json_encode([
        'success'              => true,
        'total_students'       => $totalStudents,
        'active_today'         => $activeToday,
        'active_week'          => $activeWeek,
        'total_quizzes'        => $totalQuizzes,
        'total_questions'      => $totalQuestions,
        'avg_score'            => round($avgScore, 1),
        'total_practice_tests' => $totalPracticeTests,
        'avg_streak'           => round($avgStreak, 1),
        'pro_subscribers'      => $proSubscribers,
    ]);

} catch (Throwable $e) {
    error_log('[admin/api/get-stats] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch stats']);
}
