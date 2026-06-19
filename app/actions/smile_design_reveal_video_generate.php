<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/google_gemini.php';
require_once __DIR__ . '/../smile_design/smile_design_service.php';

require_auth();
require_csrf();
smile_design_ensure_schema();

$caseId = (int)post('case_id', 0);
if ($caseId <= 0 || !smile_design_case($caseId)) {
    flash_set('error', 'Smile case was not found.');
    redirect(base_url('smile-design/cases'));
}

$returnUrl = trim((string)post('return_url', ''));
if ($returnUrl === '') {
    $returnUrl = base_url('smile-design/cases/' . $caseId . '#compare');
}

try {
    $result = smile_design_generate_case_reveal_video($caseId, auth_user_id());
    if (!empty($result['ok'])) {
        $replaced = (int)($result['replaced_video_count'] ?? 0);
        flash_set('success', $replaced > 0 ? 'Smile reveal video re-generated and previous video deleted.' : 'Smile reveal video generated.');
    } else {
        flash_set('error', trim((string)($result['message'] ?? 'Smile reveal video could not be generated.')));
    }
} catch (Throwable $exception) {
    esm_log('smile_design_video', 'Reveal video action failed.', [
        'case_id' => $caseId,
        'message' => $exception->getMessage(),
    ]);
    flash_set('error', 'Smile reveal video could not be generated right now. The issue was logged.');
}

redirect($returnUrl);
