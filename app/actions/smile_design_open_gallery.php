<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/../app/config/config.php';
require_once ROOT_PATH . '/app/core/helpers.php';
require_once ROOT_PATH . '/app/core/db.php';
require_once ROOT_PATH . '/app/core/auth.php';
require_once ROOT_PATH . '/app/smile_design/smile_design_service.php';

require_auth();
smile_design_ensure_schema();

$result = smile_design_issue_or_reuse_gallery_link(auth_user_id(), 90);
$galleryUrl = is_array($result['link'] ?? null) ? (smile_design_gallery_link_url($result['link'])) : null;

if (!empty($galleryUrl)) {
    redirect($galleryUrl);
}

flash_set('error', 'Consult Room link not available right now. Please try again.');
redirect(base_url('smile-design'));
