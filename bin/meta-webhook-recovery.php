<?php
declare(strict_types=1);

define('META_PROCESSOR_LIBRARY_ONLY', true);
require dirname(__DIR__) . '/app/api/meta_webhook_process.php';

$lockPath = storage_path('meta-webhooks/recovery.lock');
ensure_directory(dirname($lockPath));
$lock = fopen($lockPath, 'c+');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDOUT, json_encode(['ok' => true, 'skipped' => true, 'message' => 'Recovery worker is already running.']) . PHP_EOL);
    exit(0);
}

try {
    $result = meta_processor_run(50, '', false);
    fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(!empty($result['ok']) ? 0 : 1);
} catch (Throwable $e) {
    esm_log('meta_webhook_recovery', 'Server recovery worker crashed.', ['message' => $e->getMessage()]);
    fwrite(STDERR, json_encode(['ok' => false, 'message' => 'Recovery worker crashed.']) . PHP_EOL);
    exit(1);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
