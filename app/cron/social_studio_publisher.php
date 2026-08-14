<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/social_studio/social_studio_publisher.php';

try {
    $result = social_studio_publish_due(25);
    fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(!empty($result['ok']) ? 0 : 1);
} catch (Throwable $e) {
    esm_log('social_studio', 'Server-side Social Studio publisher failed.', ['error' => $e->getMessage()]);
    fwrite(STDERR, json_encode(['ok' => false, 'message' => 'Server-side Social Studio publisher failed.']) . PHP_EOL);
    exit(1);
}
