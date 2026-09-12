<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../smile_design/smile_design_service.php';

require_auth();
require_csrf();
smile_design_ensure_schema();

$photoId = (int)post('photo_id', 0);
$token = trim((string)post('mobile_upload_token', ''));

$photo = db_one("SELECT * FROM smile_case_photos WHERE id = :id AND kind = 'before' LIMIT 1", ['id' => $photoId]);
if (!$photo) {
    flash_set('error', 'Before photo not found.');
    redirect(base_url('smile-design/cases'));
}

$caseId = (int)$photo['case_id'];
$result = smile_design_replace_before_photo_from_mobile_upload($photoId, $token, auth_user_id());
flash_set($result['ok'] ? 'success' : 'error', (string)($result['ok'] ? 'Before photo replaced from phone upload.' : ($result['message'] ?? 'Could not replace before photo from phone upload.')));
redirect(base_url('smile-design/cases/' . $caseId . '#source'));
