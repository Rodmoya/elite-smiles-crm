<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/social_studio/social_studio_service.php';

require_marketing_access();
require_csrf();
header('Content-Type: application/json; charset=utf-8');

$respond = static function (bool $ok, string $message, int $status = 200): never {
    http_response_code($status);
    echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
};

$draftId = max(0, (int)post('draft_id', 0));
$upload = $_FILES['image'] ?? null;
if ($draftId <= 0 || !is_array($upload) || (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $respond(false, 'The finished JPEG was not received.', 422);
}

$draft = db_one('SELECT branded_image_storage_key FROM social_studio_drafts WHERE id=:id LIMIT 1', ['id' => $draftId]);
$sourcePath = $draft ? social_studio_safe_storage_path((string)($draft['branded_image_storage_key'] ?? '')) : null;
$temporaryPath = trim((string)($upload['tmp_name'] ?? ''));
if (!$draft || !$sourcePath || !is_file($sourcePath) || $temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
    $respond(false, 'The approved post image could not be matched.', 404);
}

$size = @getimagesize($temporaryPath);
if (!is_array($size) || ($size['mime'] ?? '') !== 'image/jpeg' || (int)($size[0] ?? 0) < 600 || (int)($size[1] ?? 0) < 600) {
    $respond(false, 'The prepared post must be a JPEG at least 600 pixels wide and tall.', 422);
}
if ((int)($upload['size'] ?? 0) <= 0 || (int)($upload['size'] ?? 0) > 12 * 1024 * 1024) {
    $respond(false, 'The prepared JPEG is empty or larger than 12 MB.', 422);
}

$targetPath = $sourcePath . '.meta.jpg';
$stagingPath = $targetPath . '.upload-' . bin2hex(random_bytes(6));
if (!move_uploaded_file($temporaryPath, $stagingPath)) {
    $respond(false, 'The prepared JPEG could not be saved.', 500);
}
@chmod($stagingPath, 0640);
if (!@rename($stagingPath, $targetPath)) {
    @unlink($stagingPath);
    $respond(false, 'The prepared JPEG could not be activated.', 500);
}

$respond(true, 'Meta-ready JPEG saved.');
