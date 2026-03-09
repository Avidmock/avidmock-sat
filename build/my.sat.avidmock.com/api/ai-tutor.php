<?php
/**
 * /api/ai-tutor.php — AI Tutor API Proxy
 *
 * This endpoint exists for backward compatibility.
 * The main AI Tutor API is at /ai-tutor/api.php
 *
 * Routes:
 *   POST → Forwards to /ai-tutor/api.php (SSE streaming)
 *   GET  → Forwards to /ai-tutor/api.php (conversation history)
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

/* Forward to the main AI tutor API */
$apiPath = $_SERVER['DOCUMENT_ROOT'] . '/ai-tutor/api.php';

if (!file_exists($apiPath)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'AI Tutor API not found']);
    exit;
}

require $apiPath;
