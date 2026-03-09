<?php
/**
 * api/get-leaderboard.php
 * GET: Top 10 leaderboard + current user rank (live, no cache)
 *
 * Query params:
 *   ?limit=10   (max 50)
 *   ?period=weekly|alltime  (default: weekly)
 *
 * Response JSON:
 *   {
 *     success: bool,
 *     period: string,
 *     rows: [ { rank, user_id, name, initials, points, is_me } ],
 *     user_rank: int,
 *     user_points: int
 *   }
 */

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Leaderboard.php';

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

/* ── Params ── */
$limit  = min(50, max(1, (int) ($_GET['limit'] ?? 10)));
$period = in_array($_GET['period'] ?? '', ['alltime']) ? 'alltime' : 'weekly';

try {

    $data       = Leaderboard::getTop50($userId, $period);
    $rows       = array_slice($data['rows'] ?? [], 0, $limit);
    $userRank   = (int) ($data['user_rank']   ?? 0);
    $userPoints = (int) ($data['user_points'] ?? 0);

    /* ── Enrich rows ── */
    $enriched = [];
    foreach ($rows as $i => $row) {
        $name     = $row['name'] ?? 'Student';
        $initials = strtoupper(implode('', array_map(
            fn($w) => substr($w, 0, 1),
            array_slice(explode(' ', $name), 0, 2)
        )));

        $enriched[] = [
            'rank'    => $i + 1,
            'user_id' => (int)  $row['user_id'],
            'name'    =>        $name,
            'initials'=>        $initials,
            'points'  => (int) ($row['points'] ?? 0),
            'is_me'   => (int)  $row['user_id'] === $userId,
        ];
    }

    /* ── If user not in top N, append their row ── */
    $userInTop = array_filter($enriched, fn($r) => $r['is_me']);
    if (empty($userInTop) && $userRank > 0) {
        $enriched[] = [
            'rank'    => $userRank,
            'user_id' => $userId,
            'name'    => $_SESSION['user_name'] ?? 'You',
            'initials'=> strtoupper(substr($_SESSION['user_name'] ?? 'Y', 0, 1)),
            'points'  => $userPoints,
            'is_me'   => true,
            'pinned'  => true, // flag for UI to style differently
        ];
    }

    echo json_encode([
        'success'     => true,
        'period'      => $period,
        'generated_at'=> date('c'),
        'rows'        => $enriched,
        'user_rank'   => $userRank,
        'user_points' => $userPoints,
    ]);

} catch (Throwable $e) {
    error_log('get-leaderboard.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Leaderboard unavailable']);
}