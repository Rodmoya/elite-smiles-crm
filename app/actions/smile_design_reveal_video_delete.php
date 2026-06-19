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

$caseId = (int)post('case_id', 0);
$videoId = (int)post('video_id', 0);
if ($caseId <= 0 || $videoId <= 0) {
    flash_set('error', 'Video was not found.');
    redirect(base_url('smile-design/cases'));
}

$returnUrl = trim((string)post('return_url', ''));
if ($returnUrl === '') {
    $returnUrl = base_url('smile-design/cases/' . $caseId . '#compare');
}

$video = db_one('SELECT * FROM smile_case_videos WHERE id = :id AND case_id = :case_id LIMIT 1', [
    'id' => $videoId,
    'case_id' => $caseId,
]);
if (!$video) {
    flash_set('error', 'Video was not found.');
    redirect($returnUrl);
}

try {
    $result = smile_design_delete_case_video($videoId, auth_user_id());
    flash_set(!empty($result['ok']) ? 'success' : 'error', !empty($result['ok']) ? 'Smile reveal video deleted.' : (string)($result['message'] ?? 'Video could not be deleted.'));
} catch (Throwable $exception) {
    esm_log('smile_design_video', 'Reveal video delete failed.', [
        'case_id' => $caseId,
        'video_id' => $videoId,
        'message' => $exception->getMessage(),
    ]);
    flash_set('error', 'Smile reveal video could not be deleted right now. The issue was logged.');
}

redirect($returnUrl);
