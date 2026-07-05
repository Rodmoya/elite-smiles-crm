<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../smile_design/smile_design_service.php';

smile_design_ensure_schema();

$photoId = (int)get('photo_id', 0);
$afterId = (int)get('after_id', 0);
$videoId = (int)get('video_id', 0);
$lviSampleId = (int)get('lvi_sample_id', 0);
$realPairId = (int)get('real_pair_id', 0);
$realSide = (string)get('side', 'before');
$photo = null;
$caseId = 0;
$storageKey = '';
$mime = 'image/jpeg';

if ($videoId > 0) {
    $photo = db_one('SELECT * FROM smile_case_videos WHERE id = :id LIMIT 1', ['id' => $videoId]);
    if ($photo) {
        $caseId = (int)$photo['case_id'];
        $storageKey = (string)$photo['storage_key'];
        $mime = (string)$photo['mime_type'];
    }
} elseif ($afterId > 0) {
    $photo = db_one('SELECT * FROM smile_after_versions WHERE id = :id LIMIT 1', ['id' => $afterId]);
    if ($photo) {
        $caseId = (int)$photo['case_id'];
        $storageKey = (string)$photo['storage_key'];
        $mime = (string)$photo['mime_type'];
    }
} elseif ($lviSampleId > 0) {
    $photo = db_one('SELECT * FROM lvi_sample_images WHERE id = :id LIMIT 1', ['id' => $lviSampleId]);
    if ($photo) {
        $storageKey = (string)$photo['storage_key'];
        $mime = (string)$photo['mime_type'];
    }
} elseif ($realPairId > 0) {
    $photo = db_one('SELECT * FROM real_result_photo_pairs WHERE id = :id LIMIT 1', ['id' => $realPairId]);
    if ($photo) {
        $storageKey = $realSide === 'after' ? (string)$photo['after_storage_key'] : (string)$photo['before_storage_key'];
        $mime = $realSide === 'after' ? (string)($photo['after_mime_type'] ?? 'image/jpeg') : (string)($photo['before_mime_type'] ?? 'image/jpeg');
    }
} else {
    $photo = db_one('SELECT * FROM smile_case_photos WHERE id = :id LIMIT 1', ['id' => $photoId]);
    if ($photo) {
        $caseId = (int)$photo['case_id'];
        $storageKey = (string)$photo['storage_key'];
        $mime = (string)$photo['mime_type'];
    }
}

if (!$photo || $storageKey === '') {
    http_response_code(404);
    exit('Photo not found.');
}

$authorized = auth_check();
$authorizedByToken = false;
if (!$authorized && $caseId > 0) {
    $token = (string)get('token', '');
    $preview = smile_design_verify_token($token, 'preview');
    $intake = smile_design_verify_token($token, 'intake');
    $gallery = smile_design_verify_token($token, 'gallery');
    $link = $preview ?: $intake;
    $authorized = $link && (int)$link['case_id'] === $caseId;
    $authorizedByToken = (bool)$authorized;
    if (!$authorized && $gallery && (int)$gallery['case_id'] === 0) {
        $authorized = true;
        $authorizedByToken = true;
    }
}

if (!$authorized && $caseId === 0) {
    $token = (string)get('token', '');
    $gallery = smile_design_verify_token($token, 'gallery');
    if ($gallery && (int)$gallery['case_id'] === 0) {
        $authorized = true;
        $authorizedByToken = true;
    }
}

if (!$authorized) {
    http_response_code(403);
    exit('Forbidden.');
}

$path = smile_design_safe_storage_path($storageKey);
if (!$path || !is_file($path)) {
    http_response_code(404);
    exit('Photo file not found.');
}

smile_design_audit($caseId ?: null, $videoId > 0 ? 'video_viewed' : 'photo_viewed', ['photo_id' => $photoId, 'after_id' => $afterId, 'video_id' => $videoId, 'lvi_sample_id' => $lviSampleId, 'real_pair_id' => $realPairId], auth_user_id());
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: ' . ($authorizedByToken ? 'public, max-age=3600' : 'private, max-age=300'));
if ($videoId > 0) {
    header('Accept-Ranges: bytes');
}
readfile($path);
exit;
