<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/social_studio/social_studio_service.php';

require_auth();
social_studio_ensure_schema();

$draftId = (int)get('draft_id', 0);
$variant = (string)get('variant', 'branded');
$draft = $draftId > 0 ? db_one('SELECT * FROM social_studio_drafts WHERE id = :id LIMIT 1', ['id' => $draftId]) : null;
if (!$draft) {
    http_response_code(404);
    exit('Image not found.');
}

$storageKey = $variant === 'raw'
    ? (string)($draft['image_storage_key'] ?? '')
    : (string)($draft['branded_image_storage_key'] ?? '');
if ($storageKey === '') {
    $storageKey = (string)($draft['image_storage_key'] ?? '');
}

$path = social_studio_safe_storage_path($storageKey);
if (!$path || !is_file($path)) {
    http_response_code(404);
    exit('Image not found.');
}

$mime = function_exists('mime_content_type') ? (string)(@mime_content_type($path) ?: '') : '';
if ($mime === '') {
    $mime = 'image/png';
}
if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'svg') {
    $mime = 'image/svg+xml';
}

header('Content-Type: ' . $mime);
header('Cache-Control: private, no-cache, must-revalidate');
header('Content-Length: ' . (string)filesize($path));
readfile($path);
