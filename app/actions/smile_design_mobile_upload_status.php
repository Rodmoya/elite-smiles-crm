<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../smile_design/smile_design_service.php';

require_auth();
smile_design_ensure_schema();

$token = trim((string)get('token', ''));
if ($token === '' || !smile_design_verify_token($token, 'mobile_upload')) {
    json_response(['ok' => false, 'message' => 'Mobile upload link is not valid.'], 404);
}

$uploads = smile_design_mobile_uploads_for_token($token, true);
$slots = [];
foreach (['front' => 'Front', 'left_45' => 'Left 45', 'right_45' => 'Right 45'] as $photoType => $label) {
    $upload = $uploads[$photoType] ?? null;
    $slots[$photoType] = [
        'photo_type' => $photoType,
        'label' => $label,
        'ready' => is_array($upload),
        'original_name' => is_array($upload) ? (string)($upload['original_name'] ?? '') : '',
        'created_at' => is_array($upload) ? (string)($upload['created_at'] ?? '') : '',
    ];
}

json_response([
    'ok' => true,
    'slots' => $slots,
    'ready_count' => count(array_filter($slots, static fn(array $slot): bool => !empty($slot['ready']))),
]);
