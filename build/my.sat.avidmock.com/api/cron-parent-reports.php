<?php
/**
 * api/cron-parent-reports.php — Cron Endpoint for Weekly Parent Reports
 *
 * Called weekly by SiteGround cron:
 *   wget -q -O /dev/null "https://my.sat.avidmock.com/api/cron-parent-reports.php?key=CRON_KEY"
 *
 * Supports:
 *   ?batch_offset=N    — Skip first N students (for processing large user bases)
 *   ?batch_size=50     — Students per batch (default 50, max 100)
 *   ?key=CRON_KEY      — Simple auth to prevent public access
 *
 * Iterates all students with parent_email set, generates and sends reports
 * in batches to avoid timeout. Logs results to parent_report_log table.
 */

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/ParentReport.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Mailer.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/StudyStreak.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/CategoryPerformance.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/ScorePredictor.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Achievement.php';

/* ── Headers ── */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/* ── Auth: require cron key or CLI execution ── */
$isCli   = php_sapi_name() === 'cli';
$cronKey = getenv('CRON_SECRET_KEY') ?: 'avidmock-cron-2024';
$reqKey  = $_GET['key'] ?? '';

if (!$isCli && !hash_equals($cronKey, $reqKey)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

/* ── Parameters ── */
$batchOffset = max(0, (int) ($_GET['batch_offset'] ?? 0));
$batchSize   = min(100, max(1, (int) ($_GET['batch_size'] ?? 50)));

/* ── Increase limits for cron ── */
set_time_limit(300);
ini_set('memory_limit', '256M');

$startTime = microtime(true);
$results   = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'errors' => []];

try {
    $students = ParentReport::getStudentsDueForReport($batchSize, $batchOffset);

    if (empty($students)) {
        echo json_encode([
            'success'      => true,
            'message'      => 'No students due for reports.',
            'batch_offset' => $batchOffset,
            'processed'    => 0,
        ]);
        exit;
    }

    foreach ($students as $student) {
        $userId = (int) $student['user_id'];

        try {
            $result = ParentReport::sendReport($userId);

            if ($result['success']) {
                $results['sent']++;
            } else {
                $results['failed']++;
                $results['errors'][] = [
                    'user_id' => $userId,
                    'error'   => $result['error'] ?? 'Unknown error',
                ];
            }
        } catch (\Throwable $e) {
            $results['failed']++;
            $results['errors'][] = [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ];
            error_log("[cron-parent-reports] user {$userId}: " . $e->getMessage());
        }

        // Safety valve: stop if we're approaching 4 minutes
        if ((microtime(true) - $startTime) > 240) {
            $results['skipped'] = count($students) - $results['sent'] - $results['failed'];
            break;
        }
    }

    $elapsed = round(microtime(true) - $startTime, 2);

    $response = [
        'success'       => true,
        'batch_offset'  => $batchOffset,
        'batch_size'    => $batchSize,
        'total_in_batch'=> count($students),
        'sent'          => $results['sent'],
        'failed'        => $results['failed'],
        'skipped'       => $results['skipped'],
        'elapsed_sec'   => $elapsed,
        'next_offset'   => $batchOffset + count($students),
    ];

    // Only include errors in dev
    if (AVIDMOCK_DEBUG) {
        $response['errors'] = $results['errors'];
    }

    // Log summary
    error_log(sprintf(
        '[cron-parent-reports] Batch offset=%d: sent=%d, failed=%d, skipped=%d, time=%.2fs',
        $batchOffset, $results['sent'], $results['failed'], $results['skipped'], $elapsed
    ));

    echo json_encode($response);

} catch (\Throwable $e) {
    error_log('[cron-parent-reports] Fatal: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => AVIDMOCK_DEBUG ? $e->getMessage() : 'Cron job failed.',
    ]);
}
