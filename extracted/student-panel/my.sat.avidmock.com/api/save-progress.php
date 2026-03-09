<?php
ob_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/VideoProgress.php';
require_once __DIR__ . '/../lib/Lesson.php';
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

$userId = (int)($_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 0);
if (!$userId) { http_response_code(401); echo json_encode(['success'=>false,'error'=>'Not logged in']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'error'=>'POST required']); exit; }

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Invalid JSON']); exit; }

$lessonId       = (int)($body['lesson_id']       ?? 0);
$secondsWatched = (int)($body['seconds_watched'] ?? 0);
$forceComplete  = (bool)($body['is_complete']    ?? false);

if ($lessonId <= 0) { http_response_code(422); echo json_encode(['success'=>false,'error'=>'lesson_id required']); exit; }
if ($secondsWatched < 0 || $secondsWatched > 86400) { http_response_code(422); echo json_encode(['success'=>false,'error'=>'seconds_watched out of range']); exit; }

$lesson = Lesson::getById($lessonId);
if (!$lesson) { http_response_code(404); echo json_encode(['success'=>false,'error'=>'Lesson not found']); exit; }

$duration     = max(1,(int)($lesson['duration_secs'] ?? 1));
$completedPct = $secondsWatched / $duration;
$isComplete   = $forceComplete || ($completedPct >= 0.85);

try {
    VideoProgress::save($userId, $lessonId, $secondsWatched, $isComplete);
} catch (Throwable $e) {
    error_log('save-progress: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Failed to save progress']);
    exit;
}

echo json_encode(['success'=>true,'is_complete'=>$isComplete,'seconds_watched'=>$secondsWatched,'completion_pct'=>round($completedPct*100,1)]);