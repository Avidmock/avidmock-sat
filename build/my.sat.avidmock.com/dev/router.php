<?php
/**
 * Development router for PHP built-in server.
 * Intercepts all requests, loads mock-bootstrap.php instead of config.php,
 * then includes the actual page file.
 *
 * Usage: cd build/my.sat.avidmock.com && php -S localhost:8080 dev/router.php
 */

$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$root = __DIR__ . '/..';

// Serve static files directly
$staticExts = ['css','js','png','jpg','jpeg','gif','svg','ico','woff','woff2','ttf','eot','pdf','map'];
$ext = strtolower(pathinfo($uri, PATHINFO_EXTENSION));
if (in_array($ext, $staticExts)) {
    $file = $root . $uri;
    if (is_file($file)) {
        $mimeTypes = [
            'css'=>'text/css','js'=>'application/javascript','png'=>'image/png',
            'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif',
            'svg'=>'image/svg+xml','ico'=>'image/x-icon','pdf'=>'application/pdf',
            'woff'=>'font/woff','woff2'=>'font/woff2','ttf'=>'font/ttf',
        ];
        header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
        readfile($file);
        return true;
    }
}

// Load mock bootstrap (this replaces config.php and all lib classes)
require_once __DIR__ . '/mock-bootstrap.php';

// Override require_once / include for config.php and lib files
// by wrapping them in a custom stream wrapper
set_include_path($root);

// Resolve the PHP file to serve
if ($uri === '/' || $uri === '') {
    $file = $root . '/index.php';
} else {
    // Try exact path
    $file = $root . $uri;

    // Try index.php in directory
    if (is_dir($file)) {
        $file = rtrim($file, '/') . '/index.php';
    }

    // Try adding .php
    if (!is_file($file) && is_file($file . '.php')) {
        $file = $file . '.php';
    }
}

if (!is_file($file)) {
    http_response_code(404);
    echo '<h1>404 — Page Not Found</h1><p>No file at: ' . htmlspecialchars($uri) . '</p>';
    echo '<p><a href="/">Go to Dashboard</a></p>';
    return;
}

// Intercept config.php and lib includes — they're already loaded via mock-bootstrap
$interceptPaths = ['/config/config.php', '/lib/'];

// Use output buffering to capture and serve the page
ob_start();

// Override DOCUMENT_ROOT for the request
$_SERVER['DOCUMENT_ROOT'] = $root;

// Create a wrapper that prevents re-loading config.php and lib classes
$originalRequireOnce = 'require_once';

// The trick: we re-define require_once behavior by wrapping the file
// We'll process the PHP file and replace require_once calls to config/lib
$code = file_get_contents($file);

// Replace require_once for config.php (already loaded)
$code = preg_replace(
    "/require_once\s+.*?config\.php.*?;/",
    "/* mock: config already loaded */",
    $code
);

// Replace require_once for lib/ files (already mocked)
$code = preg_replace(
    "/require_once\s+.*?\/lib\/\w+\.php.*?;/",
    "/* mock: lib already loaded */",
    $code
);

// Replace require_once for Auth.php etc
$code = preg_replace(
    "/require_once\s+.*?Auth\.php.*?;/",
    "/* mock: Auth already loaded */",
    $code
);

// Write to temp file and include
$tmp = tempnam(sys_get_temp_dir(), 'avm_');
file_put_contents($tmp, $code);

try {
    include $tmp;
} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    echo '<h1>Error</h1>';
    echo '<pre>' . htmlspecialchars($e->getMessage()) . "\n" . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    echo '<p><a href="/">Go to Dashboard</a></p>';
} finally {
    @unlink($tmp);
}

$output = ob_get_clean();
echo $output;
