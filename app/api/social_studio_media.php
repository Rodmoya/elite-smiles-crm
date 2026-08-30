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

$candidatePath = social_studio_safe_storage_path((string)($draft['branded_image_storage_key'] ?? ''));
$path = social_studio_verified_storage_file($candidatePath);
if ($path === null) {
    http_response_code(404);
    exit('Media not found.');
}
try {
    $servedPath = social_studio_meta_prepare_image($path);
} catch (Throwable $e) {
    http_response_code(415);
    exit('Meta-ready image could not be prepared.');
}
$servedPath = social_studio_verified_storage_file($servedPath);
if ($servedPath === null) {
    http_response_code(415);
    exit('Meta-ready image escaped private storage.');
}
$mime = social_studio_storage_file_mime($servedPath);
if ($mime !== 'image/jpeg') {
    http_response_code(415);
    exit('Meta publishing requires a JPEG image.');
}

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="elite-smiles-post-' . $draftId . '.jpg"');
header('Content-Length: ' . (string)social_studio_storage_file_size($servedPath));
header('Cache-Control: public, max-age=3600, immutable');
header('X-Content-Type-Options: nosniff');
if (!social_studio_stream_storage_file($servedPath)) {
    http_response_code(500);
}
