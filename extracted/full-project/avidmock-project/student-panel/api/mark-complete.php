<?php
/**
 * /api/mark-complete.php
 * Called by schedule/index.php when student clicks "Mark done"
 *
 * POST JSON: { task_id: int, complete: bool }
 * Returns:   { success: bool, tasks_done: int, tasks_total: int }
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Schedule.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/StudyStreak.php';

header('Content-Type: application/json; charset=utf-8');

// Must be logged-in student
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthenticated']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Only accept POST + XHR
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$taskId   = (int)($body['task_id'] ?? 0);
$complete = (bool)($body['complete'] ?? true);

if ($taskId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid task_id']);
    exit;
}

// Only mark complete (not un-complete) for now
if ($complete) {
    $ok = Schedule::markComplete($taskId, $userId);
} else {
    // Allow un-completing: reset the row
    try {
        $ok = Database::update('schedule_tasks', [
            'is_completed' => 0,
            'completed_at' => null,
        ], ['id' => $taskId, 'user_id' => $userId]) >= 0;
    } catch (Throwable $e) {
        $ok = false;
    }
}

// Count today's progress to send back to the UI
$todayTasks = Schedule::getToday($userId);
$tasksDone  = count(array_filter($todayTasks, fn($t) => !empty($t['is_complete'])));
$tasksTotal = count($todayTasks);

// Update streak whenever a task is marked complete
if ($ok && $complete) {
    try {
        StudyStreak::update($userId);
    } catch (Throwable $e) {
        error_log('mark-complete streak update: ' . $e->getMessage());
    }
}

echo json_encode([
    'success'     => $ok,
    'tasks_done'  => $tasksDone,
    'tasks_total' => $tasksTotal,
]);