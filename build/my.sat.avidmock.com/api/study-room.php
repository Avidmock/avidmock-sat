<?php
/**
 * =====================================================================
 *  AVIDMOCK SAT -- Study Room API
 *  Handles all study room actions via ?action= parameter.
 *
 *  POST ?action=create   — Create a new room
 *  POST ?action=join     — Join a room with code
 *  GET  ?action=state    — Get room state (polled every 2s)
 *  POST ?action=start    — Host starts the game
 *  POST ?action=answer   — Submit answer
 *  GET  ?action=leaderboard — Get room leaderboard
 *  POST ?action=end      — End the game
 * =====================================================================
 */

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/StudyRoom.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ── Auth check (all actions require login) ──────────────────
if (!is_logged_in()) {
    json_response(['success' => false, 'error' => 'Login required'], 401);
}
$userId = current_user_id();

// ── CSRF check for POST actions ─────────────────────────────
if ($method === 'POST') {
    $csrfToken = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrf_token(), $csrfToken)) {
        json_response(['success' => false, 'error' => 'Invalid CSRF token'], 403);
    }
}

try {
    switch ($action) {

        // ── CREATE ROOM ─────────────────────────────────────
        case 'create':
            if ($method !== 'POST') json_response(['success' => false, 'error' => 'POST required'], 405);

            $result = StudyRoom::create($userId, [
                'topic'             => $_POST['topic'] ?? 'mixed',
                'question_count'    => (int)($_POST['question_count'] ?? 10),
                'time_per_question' => (int)($_POST['time_per_question'] ?? 30),
                'max_players'       => (int)($_POST['max_players'] ?? 10),
                'is_public'         => !empty($_POST['is_public']),
            ]);

            json_response(['success' => true, ...$result]);

        // ── JOIN ROOM ───────────────────────────────────────
        case 'join':
            if ($method !== 'POST') json_response(['success' => false, 'error' => 'POST required'], 405);

            $code = trim($_POST['code'] ?? '');
            if (strlen($code) !== 6) {
                json_response(['success' => false, 'error' => 'Room code must be 6 characters'], 400);
            }

            $result = StudyRoom::join($userId, $code);
            json_response(['success' => true, ...$result]);

        // ── GET STATE ───────────────────────────────────────
        case 'state':
            if ($method !== 'GET') json_response(['success' => false, 'error' => 'GET required'], 405);

            $code = trim($_GET['code'] ?? '');
            if (!$code) json_response(['success' => false, 'error' => 'Code required'], 400);

            $state = StudyRoom::getState($code);
            if (!$state) json_response(['success' => false, 'error' => 'Room not found'], 404);

            json_response(['success' => true, 'user_id' => $userId, ...$state]);

        // ── START GAME ──────────────────────────────────────
        case 'start':
            if ($method !== 'POST') json_response(['success' => false, 'error' => 'POST required'], 405);

            $code = trim($_POST['code'] ?? '');
            StudyRoom::startGame($code, $userId);
            json_response(['success' => true]);

        // ── SUBMIT ANSWER ───────────────────────────────────
        case 'answer':
            if ($method !== 'POST') json_response(['success' => false, 'error' => 'POST required'], 405);

            $code          = trim($_POST['code'] ?? '');
            $questionIndex = (int)($_POST['question_index'] ?? -1);
            $answer        = trim($_POST['answer'] ?? '');
            $timeMs        = (int)($_POST['time_ms'] ?? 0);

            if (!$code || $questionIndex < 0 || !$answer) {
                json_response(['success' => false, 'error' => 'Missing parameters'], 400);
            }

            $result = StudyRoom::submitAnswer($code, $userId, $questionIndex, $answer, $timeMs);
            json_response(['success' => true, ...$result]);

        // ── LEADERBOARD ─────────────────────────────────────
        case 'leaderboard':
            if ($method !== 'GET') json_response(['success' => false, 'error' => 'GET required'], 405);

            $code = trim($_GET['code'] ?? '');
            if (!$code) json_response(['success' => false, 'error' => 'Code required'], 400);

            $lb = StudyRoom::getLeaderboard($code);
            json_response(['success' => true, 'leaderboard' => $lb]);

        // ── END GAME ────────────────────────────────────────
        case 'end':
            if ($method !== 'POST') json_response(['success' => false, 'error' => 'POST required'], 405);

            $code = trim($_POST['code'] ?? '');
            StudyRoom::endGame($code, $userId);
            json_response(['success' => true]);

        // ── PUBLIC ROOMS LIST ───────────────────────────────
        case 'public':
            $rooms = StudyRoom::getPublicRooms(20);
            json_response(['success' => true, 'rooms' => $rooms]);

        // ── USER HISTORY ────────────────────────────────────
        case 'history':
            $history = StudyRoom::getUserHistory($userId, 20);
            json_response(['success' => true, 'history' => $history]);

        // ── USER STATS ──────────────────────────────────────
        case 'stats':
            $stats = StudyRoom::getUserStats($userId);
            json_response(['success' => true, ...$stats]);

        default:
            json_response(['success' => false, 'error' => 'Unknown action'], 400);
    }

} catch (RuntimeException $e) {
    json_response(['success' => false, 'error' => $e->getMessage()], 400);
} catch (\Throwable $e) {
    error_log('[study-room.php] ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Server error'], 500);
}
