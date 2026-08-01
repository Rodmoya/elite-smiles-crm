<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/social_studio/social_studio_service.php';

require_auth();
social_studio_ensure_schema();
$baseId = (int)get('base_id', 0);
$base = $baseId > 0 ? db_one('SELECT local_image_key FROM social_studio_base_creatives WHERE id = :id AND status = "active" LIMIT 1', ['id' => $baseId]) : null;
$path = $base ? social_studio_safe_storage_path((string)($base['local_image_key'] ?? '')) : null;
if (!$path || !is_file($path)) {
    http_response_code(404);
    exit('Image not found.');
}
$mime = function_exists('mime_content_type') ? (string)(@mime_content_type($path) ?: 'image/jpeg') : 'image/jpeg';
header('Content-Type: ' . $mime);
header('Cache-Control: private, max-age=3600');
header('Content-Length: ' . (string)filesize($path));
readfile($path);
