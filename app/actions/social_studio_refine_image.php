<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/social_studio/social_studio_service.php';

require_auth();
require_csrf();

$draftId = (int)post('draft_id', 0);
$instruction = (string)post('instruction', '');
$result = social_studio_refine_image_for_draft($draftId, $instruction, (int)(auth_user_id() ?: 0));

flash_set(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? (!empty($result['ok']) ? 'Image refined.' : 'Image refinement failed.')));
redirect(base_url('social-studio.php?view=create&draft=' . $draftId));
