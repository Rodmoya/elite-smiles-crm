<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/social_studio/social_studio_publisher.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$provided = trim((string)($_SERVER['HTTP_X_ELITE_CRON_SECRET'] ?? $_GET['secret'] ?? ''));
$configured = trim((string)(defined('ELITE_SOCIAL_STUDIO_CRON_SECRET') ? ELITE_SOCIAL_STUDIO_CRON_SECRET : ''));
if ($configured === '' || !hash_equals($configured, $provided)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized.']);
    exit;
}

try {
    echo json_encode(social_studio_publish_due((int)($_GET['limit'] ?? 10)), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    esm_log('social_studio', 'Social Studio publishing cron failed.', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Social Studio publishing run failed.']);
}

