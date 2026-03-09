<?php
/**
 * admin.sat.avidmock.com / api / upload-explanation-image.php
 *
 * Handles image uploads from the rich explanation editor in builder.php.
 *
 * POST multipart/form-data:
 *   image   file   (JPEG / PNG / GIF / WebP, max 5 MB)
 *
 * Response JSON:
 *   { success: true,  url: "/uploads/explanations/20260226_abc123.jpg" }
 *   { success: false, error: "..." }
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

/* ── MUST match auth-guard.php exactly before session_start() ───────────── */
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

/* ── Admin auth ──────────────────────────────────────────────────────────── */
$adminId   = $_SESSION['admin_id']   ?? null;
$adminRole = $_SESSION['admin_role'] ?? null;
$validRoles = ['admin', 'teacher'];

if (!$adminId || !in_array($adminRole, $validRoles, true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

/* ── Config ──────────────────────────────────────────────────────────────── */
$adminUploadDir  = $_SERVER['DOCUMENT_ROOT'] . '/uploads/explanations/';
$studentUploadDir = '/home/u2001-xj8791lx5lo5/www/my.sat.avidmock.com/public_html/uploads/explanations/';
$publicBase      = '/uploads/explanations/';
$maxBytes        = 5 * 1024 * 1024;
$allowedMime     = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

/* ── Validate file ───────────────────────────────────────────────────────── */
if (empty($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
    echo json_encode(['success' => false, 'error' => 'No file received']);
    exit;
}

$file = $_FILES['image'];

$uploadErrors = [
    UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit',
    UPLOAD_ERR_FORM_SIZE  => 'File too large',
    UPLOAD_ERR_PARTIAL    => 'Upload incomplete',
    UPLOAD_ERR_NO_TMP_DIR => 'No temp folder on server',
    UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk',
    UPLOAD_ERR_EXTENSION  => 'Upload blocked by server extension',
];
if ($file['error'] !== UPLOAD_ERR_OK) {
    $msg = $uploadErrors[$file['error']] ?? 'Upload error code ' . $file['error'];
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

if ($file['size'] > $maxBytes) {
    echo json_encode(['success' => false, 'error' => 'File exceeds 5 MB limit']);
    exit;
}

/* Detect real MIME — never trust the browser-supplied type */
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowedMime, true)) {
    echo json_encode(['success' => false, 'error' => "File type not allowed ({$mime}). Use JPEG, PNG, GIF or WebP."]);
    exit;
}

/* ── Create admin upload directory if needed ─────────────────────────────── */
if (!is_dir($adminUploadDir)) {
    if (!mkdir($adminUploadDir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => 'Could not create admin upload directory']);
        exit;
    }
}

/* ── Create student upload directory if needed ───────────────────────────── */
if (!is_dir($studentUploadDir)) {
    if (!mkdir($studentUploadDir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => 'Could not create student upload directory']);
        exit;
    }
}

/* ── Unique filename ─────────────────────────────────────────────────────── */
$ext = match ($mime) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
    default      => 'jpg',
};
$filename     = date('Ymd') . '_' . bin2hex(random_bytes(10)) . '.' . $ext;
$adminDest    = $adminUploadDir  . $filename;
$studentDest  = $studentUploadDir . $filename;

/* ── Save to admin site ──────────────────────────────────────────────────── */
if (!move_uploaded_file($file['tmp_name'], $adminDest)) {
    echo json_encode(['success' => false, 'error' => 'Failed to save file to admin directory']);
    exit;
}

/* ── Copy to student site ────────────────────────────────────────────────── */
if (!copy($adminDest, $studentDest)) {
    echo json_encode(['success' => false, 'error' => 'Saved to admin but failed to copy to student site: ' . $studentDest]);
    exit;
}

echo json_encode([
    'success' => true,
    'url'     => $publicBase . $filename,
    'name'    => basename($file['name']),
    'size'    => $file['size'],
]);