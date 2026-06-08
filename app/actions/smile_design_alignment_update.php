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

$data = [
    'pair_type' => (string)post('pair_type'),
    'case_id' => (int)post('case_id', 0),
    'before_photo_id' => (int)post('before_photo_id', 0),
    'after_version_id' => (int)post('after_version_id', 0),
    'real_pair_id' => (int)post('real_pair_id', 0),
    'photo_type' => (string)post('photo_type'),
    'before_zoom' => (float)post('before_zoom', 1),
    'before_x' => (float)post('before_x', 0),
    'before_y' => (float)post('before_y', 0),
    'after_zoom' => (float)post('after_zoom', 1),
    'after_x' => (float)post('after_x', 0),
    'after_y' => (float)post('after_y', 0),
    'crop_aspect_ratio' => (string)post('crop_aspect_ratio', '4:3'),
];

$action = (string)post('alignment_action', 'save');
$ok = $action === 'reset'
    ? smile_design_reset_alignment($data, auth_user_id())
    : smile_design_save_alignment($data, auth_user_id());

flash_set($ok ? 'success' : 'error', $ok ? 'Alignment updated.' : 'Could not update alignment.');

$returnUrl = trim((string)post('return_url'));
if ($returnUrl !== '' && (str_starts_with($returnUrl, '/') || str_starts_with($returnUrl, base_url('')))) {
    redirect($returnUrl);
}

if (!empty($data['case_id'])) {
    redirect(base_url('smile-design/cases/' . (int)$data['case_id']));
}
redirect(base_url('smile-design'));
