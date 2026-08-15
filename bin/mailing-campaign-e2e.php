<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/mailings/mailing_service.php';

try {
    echo json_encode(mailing_run_controlled_e2e_test(), JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    esm_log('mailings', 'Mailing CLI end-to-end test failed.', ['error' => $e->getMessage()]);
    fwrite(STDERR, 'Mailing CLI end-to-end test failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
