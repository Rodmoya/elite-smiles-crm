<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/social_studio/social_studio_service.php';

require_auth();
require_csrf();

$deleted = social_studio_delete_all_drafts();
flash_set('success', $deleted > 0 ? "Cleared {$deleted} social drafts." : 'The social review queue was already empty.');
redirect(base_url('social-studio.php'));
