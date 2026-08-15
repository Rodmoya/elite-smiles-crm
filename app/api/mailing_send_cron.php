<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/mailings/mailing_service.php';

header('Content-Type: application/json; charset=utf-8');
$provided = trim((string)($_SERVER['HTTP_X_ELITE_CRON_SECRET'] ?? ''));
$expected = trim((string)ELITE_MAILING_CRON_SECRET);
if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Forbidden.']);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

try {
    $result = mailing_send_due(3, 100);
    http_response_code(200);
    echo json_encode($result, JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    esm_log('mailings', 'Scheduled mailing worker failed.', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Scheduled mailing worker failed.']);
}
