<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/social_studio/social_studio_service.php';

require_marketing_access();
require_csrf();
header('Content-Type: application/json; charset=utf-8');

try {
    $result = social_studio_reanalyze_base_creatives(1, (int)post('base_id', 0));
    $ok = (int)($result['updated'] ?? 0) === 1 || (int)($result['remaining'] ?? 0) === 0;
    http_response_code($ok ? 200 : 422);
    echo json_encode(['ok' => $ok] + $result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $exception->getMessage()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
