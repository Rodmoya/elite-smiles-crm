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

$result = smile_design_issue_or_reuse_gallery_link(auth_user_id(), 90);
$link = is_array($result['link'] ?? null) ? $result['link'] : null;
$galleryUrl = $link ? smile_design_gallery_link_url($link) : null;

smile_design_audit(null, !empty($result['created']) ? 'gallery_link_created' : 'gallery_link_reused', ['expires_days' => 90], auth_user_id());
flash_set('success', 'Consult room gallery link is ready' . ($galleryUrl ? ': ' . $galleryUrl : '.'));

redirect(base_url('smile-design'));
