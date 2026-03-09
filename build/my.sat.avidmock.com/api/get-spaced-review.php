<?php
/**
 * api/get-spaced-review.php  [NEW — v4]
 * GET: Today's spaced repetition items from the SM-2 queue
 *
 * Query params:
 *   ?limit=20  (default 20, max 50)
 *   ?subject=math|reading_writing|all  (default all)
 *
 * Response JSON:
 *   {
 *     success: bool,
 *     items: [
 *       {
 *         queue_id: int,
 *         question_id: int,
 *         type: string,
 *         stem: string,
 *         choices: { a,b,c,d }|null,
 *         hint: string|null,
 *         difficulty: string,
 *         subject: string,
 *         micro_topic: string,
 *         interval_days: int,
 *         repetitions: int,
 *         ease_factor: float
 *       }
 *     ],
 *     total_due: int,
 *     subject_breakdown: { math: int, reading_writing: int }
 *   }
 */

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/SpacedRepetition.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/FeatureGate.php';

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

/* ── Feature gate ── */
try {
    if (!FeatureGate::canAccess($userId, 'spaced_repetition')) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error'   => 'Spaced repetition requires Pro plan.',
            'upgrade' => true,
        ]);
        exit;
    }
} catch (Throwable $e) {
    // Allow through if gate not yet implemented
}

/* ── Params ── */
$limit   = min(50, max(1, (int) ($_GET['limit'] ?? 20)));
$subject = in_array($_GET['subject'] ?? '', ['math', 'reading_writing'])
             ? $_GET['subject']
             : 'all';

try {

    /* ── Fetch due items ── */
    $queue = SpacedRepetition::getReviewQueue($userId, $limit, $subject === 'all' ? null : $subject);

    /* ── Count total due (unfiltered) for the badge ── */
    $totalDue = SpacedRepetition::countDue($userId);

    /* ── Subject breakdown ── */
    $breakdown = SpacedRepetition::countBySubject($userId);

    /* ── Sanitize output (don't expose correct_answer) ── */
    $items = array_map(function ($item) {
        $choices = null;
        if (!empty($item['type']) && $item['type'] === 'mcq') {
            $choices = [
                'a' => $item['choice_a'] ?? '',
                'b' => $item['choice_b'] ?? '',
                'c' => $item['choice_c'] ?? '',
                'd' => $item['choice_d'] ?? '',
            ];
        }

        return [
            'queue_id'     => (int)   $item['queue_id'],
            'question_id'  => (int)   $item['question_id'],
            'type'         =>         $item['type']        ?? 'mcq',
            'stem'         =>         $item['stem']        ?? '',
            'choices'      =>         $choices,
            'hint'         =>         $item['hint']        ?? null,
            'difficulty'   =>         $item['difficulty']  ?? 'medium',
            'subject'      =>         $item['subject']     ?? '',
            'micro_topic'  =>         $item['micro_topic'] ?? '',
            'latex_enabled'=>  (bool) ($item['latex_enabled'] ?? false),
            'interval_days'=>  (int)  ($item['interval_days'] ?? 1),
            'repetitions'  =>  (int)  ($item['repetitions']   ?? 0),
            'ease_factor'  =>  round((float) ($item['ease_factor'] ?? 2.5), 2),
        ];
    }, $queue);

    echo json_encode([
        'success'          => true,
        'items'            => $items,
        'total_due'        => $totalDue,
        'subject_breakdown'=> [
            'math'             => (int) ($breakdown['math']             ?? 0),
            'reading_writing'  => (int) ($breakdown['reading_writing']  ?? 0),
        ],
        'generated_at'     => date('c'),
    ]);

} catch (Throwable $e) {
    error_log('get-spaced-review.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to load review queue']);
}