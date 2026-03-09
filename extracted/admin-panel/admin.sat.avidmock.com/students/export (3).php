<?php
/**
 * students/export.php — Export students as CSV
 * Supports ?q= search and ?status= filter from index page.
 */
require_once __DIR__ . '/../auth/auth-guard.php';
require_once __DIR__ . '/../lib/Database.php';

$admin = currentAdmin();
$db    = Database::connect();

// ── Schema detection ──────────────────────────────────────────────────────
try {
    $userCols  = $db->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    $allTables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    http_response_code(500);
    exit("DB error: " . htmlspecialchars($e->getMessage()));
}

$nameExpr = in_array('first_name', $userCols)
    ? "CONCAT(u.first_name,' ',u.last_name)"
    : "u.name";

$hasXp    = in_array('user_xp',          $allTables);
$hasAtt   = in_array('sat_quiz_attempts', $allTables);
$hasLevel = $hasXp && in_array('level', $db->query("SHOW COLUMNS FROM user_xp")->fetchAll(PDO::FETCH_COLUMN));

// ── Column expressions ────────────────────────────────────────────────────
// NOTE: user_xp table uses column 'xp', not 'total_xp'
$xpJoin   = $hasXp ? "LEFT JOIN user_xp ux ON ux.user_id = u.id" : "";
$xpFields = $hasXp
    ? "COALESCE(ux.xp, 0) AS total_xp, " . ($hasLevel ? "COALESCE(ux.level, 1)" : "1") . " AS level,"
    : "0 AS total_xp, 1 AS level,";

$attFields = $hasAtt
    ? "(SELECT COUNT(*) FROM sat_quiz_attempts WHERE user_id = u.id AND status = 'completed') AS attempts,
       (SELECT ROUND(AVG(score), 1) FROM sat_quiz_attempts WHERE user_id = u.id AND status = 'completed') AS avg_score,
       (SELECT MAX(completed_at)    FROM sat_quiz_attempts WHERE user_id = u.id AND status = 'completed') AS last_active,"
    : "0 AS attempts, NULL AS avg_score, NULL AS last_active,";

$hasStatus = in_array('status', $userCols);
$statusField = $hasStatus ? "u.status," : "'active' AS status,";

// ── Filters ───────────────────────────────────────────────────────────────
$search = trim($_GET['q']     ?? '');
$status = $_GET['status']     ?? '';

$where  = "WHERE u.role = 'student'";
$params = [];

if ($search !== '') {
    $where .= " AND ({$nameExpr} LIKE :s OR u.email LIKE :s2)";
    $params[':s']  = "%{$search}%";
    $params[':s2'] = "%{$search}%";
}
if ($status !== '' && $hasStatus) {
    $where .= " AND u.status = :st";
    $params[':st'] = $status;
}

// ── Query ─────────────────────────────────────────────────────────────────
try {
    $stmt = $db->prepare(
        "SELECT {$nameExpr} AS name, u.email, {$statusField} u.created_at,
                {$xpFields}
                {$attFields}
                u.id AS uid
         FROM users u {$xpJoin}
         {$where}
         ORDER BY u.created_at DESC"
    );
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    http_response_code(500);
    exit("Query error: " . htmlspecialchars($e->getMessage()));
}

// ── Stream CSV ────────────────────────────────────────────────────────────
$filename = 'students-' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

$out = fopen('php://output', 'w');

// UTF-8 BOM so Excel opens it correctly
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Column headers
fputcsv($out, [
    'Name',
    'Email',
    'Status',
    'Level',
    'Total XP',
    'Quizzes Completed',
    'Avg Score (%)',
    'Last Active',
    'Joined',
]);

// Rows
foreach ($rows as $r) {
    fputcsv($out, [
        $r['name']       ?? '',
        $r['email']      ?? '',
        $r['status']     ?? 'active',
        (int)($r['level']    ?? 1),
        (int)($r['total_xp'] ?? 0),
        (int)($r['attempts'] ?? 0),
        isset($r['avg_score']) && $r['avg_score'] !== null
            ? number_format((float)$r['avg_score'], 1)
            : '',
        !empty($r['last_active'])
            ? date('Y-m-d', strtotime($r['last_active']))
            : '',
        !empty($r['created_at'])
            ? date('Y-m-d', strtotime($r['created_at']))
            : '',
    ]);
}

fclose($out);
exit;