<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/social_studio/social_studio_publisher.php';

$draftId = max(0, (int)($_GET['draft_id'] ?? 0));
$expires = (int)($_GET['expires'] ?? 0);
$providedSignature = trim((string)($_GET['signature'] ?? ''));
$expectedSignature = social_studio_media_signature($draftId, $expires);

if ($draftId <= 0 || $expires < time() || $expires > time() + 86400 || $expectedSignature === '' || !hash_equals($expectedSignature, $providedSignature)) {
    http_response_code(403);
    exit('Media link is invalid or expired.');
}

social_studio_ensure_schema();
$draft = db_one('SELECT status, branded_image_storage_key FROM social_studio_drafts WHERE id=:id LIMIT 1', ['id' => $draftId]);
if (!$draft || !in_array((string)$draft['status'], ['approved', 'scheduled', 'publishing', 'publish_failed', 'published'], true)) {
    http_response_code(404);
    exit('Media not found.');
}

$path = social_studio_safe_storage_path((string)($draft['branded_image_storage_key'] ?? ''));
// The signed draft ID selects a DB-owned storage key; safe_storage_path constrains it to STORAGE_PATH.
if ($path === '' || !is_file($path)) { // nosemgrep: php.lang.security.injection.tainted-filename.tainted-filename
    http_response_code(404);
    exit('Media not found.');
}
$mime = function_exists('mime_content_type') ? (string)(mime_content_type($path) ?: '') : ''; // nosemgrep: php.lang.security.injection.tainted-filename.tainted-filename
if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
    http_response_code(415);
    exit('Meta publishing requires a JPEG or PNG image.');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($path)); // nosemgrep: php.lang.security.injection.tainted-filename.tainted-filename
header('Cache-Control: public, max-age=3600, immutable');
header('X-Content-Type-Options: nosniff');
readfile($path); // nosemgrep: php.lang.security.injection.tainted-filename.tainted-filename
