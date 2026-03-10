<?php
/**
 * api/parent-report.php — Parent Report REST Endpoint
 *
 * GET  ?action=preview   → Returns HTML preview of current week's report
 * POST ?action=send      → Manually triggers report send to parent email(s)
 * POST ?action=settings  → Updates parent email, frequency preferences
 */

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Mailer.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/ParentReport.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/StudyStreak.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/CategoryPerformance.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/ScorePredictor.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Achievement.php';

/* ── Headers ── */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

/* ── Auth ── */
$user = Auth::requireStudent();
$userId = (int) $_SESSION['user_id'];

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {

    // ──────────────────────────────────────────────────────────────
    //  GET ?action=preview — HTML preview of the report
    // ──────────────────────────────────────────────────────────────
    if ($method === 'GET' && $action === 'preview') {
        $data  = ParentReport::generateWeeklyReport($userId);
        $token = ParentReport::generateShareToken($userId);
        $data['share_token'] = $token;
        $html  = ParentReport::renderEmailHtml($data);

        // Return HTML directly for preview rendering
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }

    // ──────────────────────────────────────────────────────────────
    //  POST ?action=send — Send report now
    // ──────────────────────────────────────────────────────────────
    if ($method === 'POST' && $action === 'send') {
        if (!csrf_verify()) {
            json_response(['success' => false, 'error' => 'Invalid CSRF token.'], 403);
        }

        $result = ParentReport::sendReport($userId);
        json_response($result, $result['success'] ? 200 : 400);
    }

    // ──────────────────────────────────────────────────────────────
    //  POST ?action=settings — Update parent report preferences
    // ──────────────────────────────────────────────────────────────
    if ($method === 'POST' && $action === 'settings') {
        if (!csrf_verify()) {
            json_response(['success' => false, 'error' => 'Invalid CSRF token.'], 403);
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        // Validate email(s)
        $email1 = trim($body['parent_email_1'] ?? '');
        $email2 = trim($body['parent_email_2'] ?? '');

        if ($email1 !== '' && !filter_var($email1, FILTER_VALIDATE_EMAIL)) {
            json_response(['success' => false, 'error' => 'Parent email 1 is not a valid email address.'], 422);
        }
        if ($email2 !== '' && !filter_var($email2, FILTER_VALIDATE_EMAIL)) {
            json_response(['success' => false, 'error' => 'Parent email 2 is not a valid email address.'], 422);
        }

        // Validate frequency
        $frequency = $body['frequency'] ?? 'weekly';
        if (!in_array($frequency, ['weekly', 'biweekly', 'monthly'], true)) {
            $frequency = 'weekly';
        }

        ParentReport::saveSettings($userId, [
            'parent_email_1'      => $email1 ?: null,
            'parent_email_2'      => $email2 ?: null,
            'frequency'           => $frequency,
            'include_scores'      => !empty($body['include_scores']),
            'include_streaks'     => !empty($body['include_streaks']),
            'include_ai_usage'    => !empty($body['include_ai_usage']),
            'include_achievements' => !empty($body['include_achievements']),
        ]);

        json_response(['success' => true, 'message' => 'Parent report settings saved.']);
    }

    // ──────────────────────────────────────────────────────────────
    //  GET (no action) — Return current settings
    // ──────────────────────────────────────────────────────────────
    if ($method === 'GET' && $action === '') {
        $settings = ParentReport::getSettings($userId);
        json_response([
            'success'  => true,
            'settings' => $settings ? [
                'parent_email_1'      => $settings['parent_email_1'] ?? '',
                'parent_email_2'      => $settings['parent_email_2'] ?? '',
                'frequency'           => $settings['frequency'] ?? 'weekly',
                'include_scores'      => (bool) ($settings['include_scores'] ?? 1),
                'include_streaks'     => (bool) ($settings['include_streaks'] ?? 1),
                'include_ai_usage'    => (bool) ($settings['include_ai_usage'] ?? 1),
                'include_achievements' => (bool) ($settings['include_achievements'] ?? 1),
                'last_sent_at'        => $settings['last_sent_at'] ?? null,
            ] : null,
        ]);
    }

    // ── Unknown action ──
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unknown action: ' . e($action)]);

} catch (\Throwable $e) {
    error_log('[api/parent-report.php] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An unexpected error occurred.']);
}
