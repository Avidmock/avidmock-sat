<?php
/**
 * api/get-revenue.php — Revenue REST API
 *
 * Endpoints:
 *   GET ?metric=mrr      — Monthly recurring revenue data
 *   GET ?metric=cohort   — Cohort retention data
 *   GET ?metric=funnel   — Conversion funnel data
 *   GET ?metric=churn    — Churn analysis data
 *   GET ?metric=growth   — Growth metrics (signups, activation, viral, DAU/WAU/MAU)
 *
 * Optional:
 *   &period=7d|30d|90d|12m  (default: 30d)
 *
 * All responses return JSON.
 */

require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

$db = Database::connect();

// ── Helpers ──────────────────────────────────────────────────────────────────
function sq(PDO $db, string $sql, array $params = []): mixed {
    try { $stmt = $db->prepare($sql); $stmt->execute($params); return $stmt->fetchColumn(); }
    catch (Throwable) { return 0; }
}
function sqAll(PDO $db, string $sql, array $params = []): array {
    try { $stmt = $db->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC); }
    catch (Throwable) { return []; }
}
function jsonOut(array $data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Params ───────────────────────────────────────────────────────────────────
$metric = $_GET['metric'] ?? '';
$period = $_GET['period'] ?? '30d';

$periodMap = [
    '7d'  => 7,
    '30d' => 30,
    '90d' => 90,
    '12m' => 365,
];
$days = $periodMap[$period] ?? 30;
$since = date('Y-m-d', strtotime("-{$days} days"));

if (!in_array($metric, ['mrr', 'cohort', 'funnel', 'churn', 'growth'])) {
    jsonOut([
        'error' => 'Invalid or missing metric parameter',
        'valid_metrics' => ['mrr', 'cohort', 'funnel', 'churn', 'growth'],
        'valid_periods' => ['7d', '30d', '90d', '12m'],
    ], 400);
}

// ── Schema detection ─────────────────────────────────────────────────────────
$allTables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$hasUsers      = in_array('users', $allTables);
$hasSubEvents  = in_array('subscription_events', $allTables);
$hasAttempts   = in_array('sat_quiz_attempts', $allTables);
$hasReferrals  = in_array('referrals', $allTables);
$hasAttribution = in_array('signup_attribution', $allTables);

$hasSubPlan = false;
$hasSubStatus = false;
if ($hasUsers) {
    $userCols = array_column($db->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_ASSOC), 'Field');
    $hasSubPlan   = in_array('subscription_plan', $userCols);
    $hasSubStatus = in_array('subscription_status', $userCols);
    $hasCreatedAt = in_array('created_at', $userCols);
}

$planPrices = ['free' => 0, 'pro' => 14.99, 'family' => 24.99];

// ══════════════════════════════════════════════════════════════════════════════
//  METRIC: MRR
// ══════════════════════════════════════════════════════════════════════════════
if ($metric === 'mrr') {
    $data = [
        'metric' => 'mrr',
        'period' => $period,
        'current_mrr' => 0,
        'arr' => 0,
        'active_subscribers' => 0,
        'plan_breakdown' => [],
        'trend' => [],
    ];

    if ($hasUsers && $hasSubPlan) {
        $statusWhere = $hasSubStatus ? "AND (subscription_status = 'active' OR subscription_status IS NULL)" : "";

        $proCount    = (int) sq($db, "SELECT COUNT(*) FROM users WHERE subscription_plan = 'pro' {$statusWhere}");
        $familyCount = (int) sq($db, "SELECT COUNT(*) FROM users WHERE subscription_plan = 'family' {$statusWhere}");
        $freeCount   = (int) sq($db, "SELECT COUNT(*) FROM users WHERE subscription_plan = 'free' {$statusWhere}");

        $mrr = ($proCount * $planPrices['pro']) + ($familyCount * $planPrices['family']);

        $data['current_mrr'] = round($mrr, 2);
        $data['arr'] = round($mrr * 12, 2);
        $data['active_subscribers'] = $proCount + $familyCount;
        $data['plan_breakdown'] = [
            'free'   => ['count' => $freeCount,   'mrr' => 0],
            'pro'    => ['count' => $proCount,    'mrr' => round($proCount * $planPrices['pro'], 2)],
            'family' => ['count' => $familyCount, 'mrr' => round($familyCount * $planPrices['family'], 2)],
        ];
    }

    // MRR trend (monthly)
    $months = $period === '12m' ? 12 : min((int) ceil($days / 30), 12);
    if ($hasSubEvents) {
        for ($i = $months - 1; $i >= 0; $i--) {
            $mo = date('Y-m', strtotime("-{$i} months"));
            $moLabel = date('M Y', strtotime("-{$i} months"));
            $moStart = $mo . '-01';
            $moEnd   = date('Y-m-t', strtotime($moStart));

            $proMo    = (int) sq($db, "SELECT COUNT(DISTINCT user_id) FROM subscription_events WHERE plan='pro' AND event_type IN ('new','renewal','reactivate') AND created_at BETWEEN ? AND ?", [$moStart, $moEnd . ' 23:59:59']);
            $familyMo = (int) sq($db, "SELECT COUNT(DISTINCT user_id) FROM subscription_events WHERE plan='family' AND event_type IN ('new','renewal','reactivate') AND created_at BETWEEN ? AND ?", [$moStart, $moEnd . ' 23:59:59']);

            $data['trend'][] = [
                'month' => $moLabel,
                'mrr' => round(($proMo * $planPrices['pro']) + ($familyMo * $planPrices['family']), 2),
                'pro_count' => $proMo,
                'family_count' => $familyMo,
            ];
        }
    }

    jsonOut($data);
}

// ══════════════════════════════════════════════════════════════════════════════
//  METRIC: COHORT
// ══════════════════════════════════════════════════════════════════════════════
if ($metric === 'cohort') {
    $data = [
        'metric' => 'cohort',
        'period' => $period,
        'cohorts' => [],
    ];

    $cohortMonths = min((int) ceil($days / 30), 12);

    if ($hasUsers && $hasAttempts && isset($hasCreatedAt) && $hasCreatedAt) {
        for ($i = $cohortMonths - 1; $i >= 0; $i--) {
            $cohortStart = date('Y-m-01', strtotime("-{$i} months"));
            $cohortEnd   = date('Y-m-t', strtotime("-{$i} months"));
            $label       = date('M Y', strtotime("-{$i} months"));

            $cohortSize = (int) sq($db,
                "SELECT COUNT(*) FROM users WHERE created_at BETWEEN ? AND ?",
                [$cohortStart, $cohortEnd . ' 23:59:59']
            );

            $retention = ['month_0' => $cohortSize];

            for ($m = 1; $m <= $i; $m++) {
                $checkStart = date('Y-m-01', strtotime("-" . ($i - $m) . " months"));
                $checkEnd   = date('Y-m-t', strtotime("-" . ($i - $m) . " months"));

                $retained = (int) sq($db,
                    "SELECT COUNT(DISTINCT a.user_id)
                     FROM sat_quiz_attempts a
                     JOIN users u ON u.id = a.user_id
                     WHERE u.created_at BETWEEN ? AND ?
                     AND a.started_at BETWEEN ? AND ?",
                    [$cohortStart, $cohortEnd . ' 23:59:59', $checkStart, $checkEnd . ' 23:59:59']
                );
                $retention["month_{$m}"] = $retained;
                $retention["month_{$m}_pct"] = $cohortSize > 0 ? round($retained / $cohortSize * 100, 1) : 0;
            }

            $data['cohorts'][] = [
                'label'     => $label,
                'size'      => $cohortSize,
                'retention' => $retention,
            ];
        }
    }

    jsonOut($data);
}

// ══════════════════════════════════════════════════════════════════════════════
//  METRIC: FUNNEL
// ══════════════════════════════════════════════════════════════════════════════
if ($metric === 'funnel') {
    $data = [
        'metric' => 'funnel',
        'period' => $period,
        'steps' => [],
    ];

    $signups = 0;
    $onboarded = 0;
    $firstQuiz = 0;
    $subscribed = 0;

    if ($hasUsers) {
        $signups = (int) sq($db, "SELECT COUNT(*) FROM users WHERE created_at >= ?", [$since]);
    }
    if ($hasUsers && $hasAttempts) {
        $onboarded = (int) sq($db,
            "SELECT COUNT(DISTINCT u.id) FROM users u
             JOIN sat_quiz_attempts a ON a.user_id = u.id
             WHERE u.created_at >= ?
             AND a.started_at <= DATE_ADD(u.created_at, INTERVAL 24 HOUR)",
            [$since]
        );
        $firstQuiz = (int) sq($db,
            "SELECT COUNT(DISTINCT a.user_id) FROM sat_quiz_attempts a
             JOIN users u ON u.id = a.user_id
             WHERE u.created_at >= ? AND a.status = 'completed'",
            [$since]
        );
    }
    if ($hasUsers && $hasSubPlan) {
        $subscribed = (int) sq($db,
            "SELECT COUNT(*) FROM users
             WHERE subscription_plan IN ('pro','family') AND created_at >= ?",
            [$since]
        );
    }

    $data['steps'] = [
        ['label' => 'Signups',             'count' => $signups,    'pct' => 100],
        ['label' => 'Onboarding Complete',  'count' => $onboarded,  'pct' => $signups > 0 ? round($onboarded / $signups * 100, 1) : 0],
        ['label' => 'First Quiz Complete',  'count' => $firstQuiz,  'pct' => $signups > 0 ? round($firstQuiz / $signups * 100, 1) : 0],
        ['label' => 'Subscribed',           'count' => $subscribed, 'pct' => $signups > 0 ? round($subscribed / $signups * 100, 1) : 0],
    ];

    // Add drop-off rates
    for ($i = 1; $i < count($data['steps']); $i++) {
        $prev = $data['steps'][$i - 1]['count'];
        $curr = $data['steps'][$i]['count'];
        $data['steps'][$i]['dropoff_pct'] = $prev > 0 ? round((1 - $curr / $prev) * 100, 1) : 0;
    }

    jsonOut($data);
}

// ══════════════════════════════════════════════════════════════════════════════
//  METRIC: CHURN
// ══════════════════════════════════════════════════════════════════════════════
if ($metric === 'churn') {
    $data = [
        'metric' => 'churn',
        'period' => $period,
        'churn_rate' => 0,
        'avg_days_before_churn' => 0,
        'cancelled_count' => 0,
        'reasons' => [],
        'at_risk_count' => 0,
    ];

    if ($hasSubEvents) {
        $cancelledCount = (int) sq($db,
            "SELECT COUNT(DISTINCT user_id) FROM subscription_events
             WHERE event_type='cancel' AND created_at >= ?",
            [$since]
        );
        $activeStart = (int) sq($db,
            "SELECT COUNT(DISTINCT user_id) FROM subscription_events
             WHERE event_type IN ('new','reactivate','renewal') AND created_at < ?",
            [$since]
        );

        $data['cancelled_count'] = $cancelledCount;
        $data['churn_rate'] = $activeStart > 0 ? round($cancelledCount / $activeStart * 100, 1) : 0;

        $data['avg_days_before_churn'] = (float) sq($db,
            "SELECT AVG(DATEDIFF(se.created_at, u.created_at))
             FROM subscription_events se
             JOIN users u ON u.id = se.user_id
             WHERE se.event_type = 'cancel' AND se.created_at >= ?",
            [$since]
        );

        if ($hasAttempts) {
            $data['reasons'] = sqAll($db,
                "SELECT
                    CASE
                        WHEN last_attempt IS NULL THEN 'Never took a quiz'
                        WHEN DATEDIFF(cancel_date, last_attempt) > 14 THEN 'Inactive 2+ weeks before cancel'
                        WHEN avg_score < 40 THEN 'Low scores (frustration)'
                        WHEN attempt_count < 3 THEN 'Low engagement (< 3 quizzes)'
                        ELSE 'Other / voluntary'
                    END AS reason,
                    COUNT(*) AS count
                 FROM (
                    SELECT se.user_id, se.created_at AS cancel_date,
                           MAX(a.started_at) AS last_attempt,
                           AVG(a.score) AS avg_score,
                           COUNT(a.id) AS attempt_count
                    FROM subscription_events se
                    LEFT JOIN sat_quiz_attempts a ON a.user_id = se.user_id AND a.started_at < se.created_at
                    WHERE se.event_type = 'cancel' AND se.created_at >= ?
                    GROUP BY se.user_id, se.created_at
                 ) AS churn_analysis
                 GROUP BY reason ORDER BY count DESC",
                [$since]
            );
        }
    }

    // At-risk count
    if ($hasUsers && $hasSubPlan && $hasAttempts) {
        $statusWhere = $hasSubStatus ? "AND (u.subscription_status = 'active' OR u.subscription_status IS NULL)" : "";
        $data['at_risk_count'] = (int) sq($db,
            "SELECT COUNT(DISTINCT u.id)
             FROM users u
             LEFT JOIN sat_quiz_attempts a ON a.user_id = u.id
             WHERE u.subscription_plan IN ('pro','family') {$statusWhere}
             GROUP BY u.id
             HAVING MAX(a.started_at) < DATE_SUB(NOW(), INTERVAL 7 DAY) OR MAX(a.started_at) IS NULL"
        );
    }

    jsonOut($data);
}

// ══════════════════════════════════════════════════════════════════════════════
//  METRIC: GROWTH
// ══════════════════════════════════════════════════════════════════════════════
if ($metric === 'growth') {
    $data = [
        'metric' => 'growth',
        'period' => $period,
        'signups' => [
            'total' => 0,
            'period_total' => 0,
            'trend' => [],
        ],
        'activation_rate' => 0,
        'viral_coefficient' => 0,
        'referrals' => ['sent' => 0, 'accepted' => 0],
        'engagement' => [
            'dau' => 0, 'wau' => 0, 'mau' => 0,
            'dau_wau' => 0, 'dau_mau' => 0, 'wau_mau' => 0,
        ],
        'channels' => [],
        'growth_score' => 0,
    ];

    // Signups
    if ($hasUsers && isset($hasCreatedAt) && $hasCreatedAt) {
        $data['signups']['total'] = (int) sq($db, "SELECT COUNT(*) FROM users");
        $data['signups']['period_total'] = (int) sq($db,
            "SELECT COUNT(*) FROM users WHERE created_at >= ?", [$since]
        );
        $data['signups']['trend'] = sqAll($db,
            "SELECT DATE(created_at) AS date, COUNT(*) AS count
             FROM users WHERE created_at >= ?
             GROUP BY DATE(created_at) ORDER BY date",
            [$since]
        );
    }

    // Activation
    if ($hasUsers && $hasAttempts) {
        $signups30d = (int) sq($db, "SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $activated  = (int) sq($db,
            "SELECT COUNT(DISTINCT u.id) FROM users u
             JOIN sat_quiz_attempts a ON a.user_id = u.id
             WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             AND a.started_at <= DATE_ADD(u.created_at, INTERVAL 24 HOUR)"
        );
        $data['activation_rate'] = $signups30d > 0 ? round($activated / $signups30d * 100, 1) : 0;
    }

    // Viral
    if ($hasReferrals) {
        $data['referrals']['sent']     = (int) sq($db, "SELECT COUNT(*) FROM referrals WHERE created_at >= ?", [$since]);
        $data['referrals']['accepted'] = (int) sq($db, "SELECT COUNT(*) FROM referrals WHERE status='accepted' AND created_at >= ?", [$since]);
        $totalU = max((int) sq($db, "SELECT COUNT(*) FROM users WHERE created_at >= ?", [$since]), 1);
        $avgInv = $data['referrals']['sent'] / $totalU;
        $convR  = $data['referrals']['sent'] > 0 ? $data['referrals']['accepted'] / $data['referrals']['sent'] : 0;
        $data['viral_coefficient'] = round($avgInv * $convR, 2);
    }

    // Engagement
    if ($hasAttempts) {
        $dau = (int) sq($db, "SELECT COUNT(DISTINCT user_id) FROM sat_quiz_attempts WHERE started_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)");
        $wau = (int) sq($db, "SELECT COUNT(DISTINCT user_id) FROM sat_quiz_attempts WHERE started_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $mau = (int) sq($db, "SELECT COUNT(DISTINCT user_id) FROM sat_quiz_attempts WHERE started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $data['engagement'] = [
            'dau' => $dau, 'wau' => $wau, 'mau' => $mau,
            'dau_wau' => $wau > 0 ? round($dau / $wau * 100, 1) : 0,
            'dau_mau' => $mau > 0 ? round($dau / $mau * 100, 1) : 0,
            'wau_mau' => $mau > 0 ? round($wau / $mau * 100, 1) : 0,
        ];
    }

    // Channels
    if ($hasAttribution) {
        $data['channels'] = sqAll($db,
            "SELECT COALESCE(channel, 'direct') AS channel, COUNT(*) AS count
             FROM signup_attribution WHERE created_at >= ?
             GROUP BY channel ORDER BY count DESC",
            [$since]
        );
    }

    // Growth score (simplified)
    $data['growth_score'] = max(0, min(100,
        min(25, round(($data['signups']['period_total'] / max($days, 1)) * 2.5)) +
        min(25, round($data['activation_rate'] / 80 * 25)) +
        min(25, round(($data['engagement']['dau_mau'] ?? 0) / 40 * 25)) +
        min(25, round($data['viral_coefficient'] / 1.0 * 25))
    ));

    jsonOut($data);
}
