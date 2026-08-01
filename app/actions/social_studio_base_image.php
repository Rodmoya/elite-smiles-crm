<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/social_studio/social_studio_service.php';

require_auth();
social_studio_ensure_schema();
$baseId = (int)get('base_id', 0);
$base = $baseId > 0 ? db_one('SELECT id, source_image_url, local_image_key FROM social_studio_base_creatives WHERE id = :id AND status = "active" LIMIT 1', ['id' => $baseId]) : null;
$path = $base ? social_studio_safe_storage_path((string)($base['local_image_key'] ?? '')) : null;
if ((!$path || !is_file($path)) && $base && trim((string)($base['source_image_url'] ?? '')) !== '') {
    $url = (string)$base['source_image_url'];
    $bytes = false;
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 20, CURLOPT_USERAGENT => 'Mozilla/5.0']);
        $bytes = curl_exec($curl);
        curl_close($curl);
    } else {
        $bytes = @file_get_contents($url);
    }
    if (is_string($bytes) && $bytes !== '' && function_exists('getimagesizefromstring') && @getimagesizefromstring($bytes) !== false) {
        $key = social_studio_store_imported_image('base_' . $baseId, $bytes);
        if ($key !== '') {
            db_execute('UPDATE social_studio_base_creatives SET local_image_key = :local_image_key WHERE id = :id LIMIT 1', ['local_image_key' => $key, 'id' => $baseId]);
            $path = social_studio_safe_storage_path($key);
        }
    }
}
if (!$path || !is_file($path)) {
    http_response_code(404);
    exit('Image not found.');
}
$mime = function_exists('mime_content_type') ? (string)(@mime_content_type($path) ?: 'image/jpeg') : 'image/jpeg';
header('Content-Type: ' . $mime);
header('Cache-Control: private, max-age=3600');
header('Content-Length: ' . (string)filesize($path));
readfile($path);
