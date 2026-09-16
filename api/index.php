<?php

// Prepare writable /tmp directories for serverless environment
$dirs = [
    '/tmp/storage/app/public',
    '/tmp/storage/framework/cache/data',
    '/tmp/storage/framework/sessions',
    '/tmp/storage/framework/views',
    '/tmp/storage/logs',
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

putenv('VERCEL=1');
$_ENV['VERCEL'] = '1';
$_SERVER['VERCEL'] = '1';

putenv('BROADCAST_CONNECTION=null');
$_ENV['BROADCAST_CONNECTION'] = 'null';
$_SERVER['BROADCAST_CONNECTION'] = 'null';

putenv('LOG_CHANNEL=errorlog');
$_ENV['LOG_CHANNEL'] = 'errorlog';
$_SERVER['LOG_CHANNEL'] = 'errorlog';

putenv('APP_STORAGE_PATH=/tmp/storage');
$_ENV['APP_STORAGE_PATH'] = '/tmp/storage';
$_SERVER['APP_STORAGE_PATH'] = '/tmp/storage';

try {
    require __DIR__ . '/../public/index.php';
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'serverless_error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 10),
    ], JSON_PRETTY_PRINT);
    exit;
}