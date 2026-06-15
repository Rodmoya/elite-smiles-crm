<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../dental_models/dental_models_service.php';

dental_models_ensure_schema();
require_auth();
dental_models_staff_gate();

function dental_model_file_model_id(): int
{
    return (int) get('id', 0);
}

function dental_model_file_not_found(): never
{
    http_response_code(404);
    exit('Model file not found.');
}

$modelId = dental_model_file_model_id();
if ($modelId <= 0) {
    dental_model_file_not_found();
}

$model = dental_models_find($modelId);
if (!$model) {
    dental_model_file_not_found();
}

$storedPath = (string)($model['stored_path'] ?? '');
$filePath = dental_models_resolve_stored_path($storedPath);
if ($filePath === null || !is_file($filePath)) {
    dental_model_file_not_found();
}

$originalFilename = trim((string)($model['original_filename'] ?? ''));
if ($originalFilename === '') {
    $originalFilename = 'dental-model-' . $modelId . '.stl';
}
if (!str_ends_with(strtolower($originalFilename), '.stl')) {
    $originalFilename .= '.stl';
}
$filenameForHeader = str_replace(['"', "'"], '', $originalFilename);

$download = (string)get('download', '1') === '1';
$isAttachment = $download || str_contains((string)$_SERVER['REQUEST_URI'], '/download-original');
$contentType = trim((string)($model['mime_type'] ?? ''));
if ($contentType === '') {
    $contentType = 'model/stl';
}
$safeContentType = dental_models_normalize_mime_type($contentType);

header('Content-Type: ' . $safeContentType);
header('Content-Length: ' . (int)filesize($filePath));
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');

if ($isAttachment) {
    header('Content-Disposition: attachment; filename="' . $filenameForHeader . '"');
} else {
    header('Content-Disposition: inline; filename="' . $filenameForHeader . '"');
}

readfile($filePath);
exit;
