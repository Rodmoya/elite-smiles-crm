<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/social_studio/social_studio_publisher.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$provided = trim((string)($_SERVER['HTTP_X_ELITE_CRON_SECRET'] ?? $_GET['secret'] ?? ''));
$configured = trim((string)(defined('ELITE_SOCIAL_STUDIO_CRON_SECRET') ? ELITE_SOCIAL_STUDIO_CRON_SECRET : ''));
if ($configured === '' || !hash_equals($configured, $provided)) {
    json_response(['ok' => false, 'message' => 'Unauthorized.'], 401);
}

try {
    $limit = max(1, min(25, (int)($_GET['limit'] ?? 10)));
    $result = social_studio_publish_due($limit);
    json_response($result, !empty($result['ok']) ? 200 : 500);
} catch (Throwable $e) {
    esm_log('social_studio', 'Social Studio publishing cron failed.', ['error' => $e->getMessage()]);
    json_response(['ok' => false, 'message' => 'Social Studio publishing run failed.'], 500);
}
