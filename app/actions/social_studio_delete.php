<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/social_studio/social_studio_service.php';

require_marketing_access();
require_csrf();

$draftId = (int)post('draft_id', 0);
$ok = social_studio_delete_draft($draftId);
flash_set($ok ? 'success' : 'error', $ok ? 'Social draft deleted.' : 'Could not delete that social draft.');
redirect(base_url('social-studio.php'));
