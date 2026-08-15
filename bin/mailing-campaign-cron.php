<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/mailings/mailing_service.php';

$batchSize = 100;
foreach ($argv ?? [] as $argument) {
    if (preg_match('/^--limit=(\d+)$/', (string)$argument, $match)) {
        $batchSize = max(1, min(500, (int)$match[1]));
    }
}

try {
    $result = mailing_send_due(3, $batchSize);
    echo json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    esm_log('mailings', 'Mailing CLI worker failed.', ['error' => $e->getMessage()]);
    fwrite(STDERR, 'Mailing CLI worker failed.' . PHP_EOL);
    exit(1);
}
