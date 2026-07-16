<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/social_studio/social_studio_service.php';

require_auth();
require_csrf();

$focus = (string)post('focus', 'veneers');
$count = (int)post('count', 7);
$instruction = (string)post('instruction', '');
$created = social_studio_seed_drafts($focus, $count, (int)(auth_user_id() ?: 0), $instruction);

flash_set('success', 'Created ' . $created . ' social draft' . ($created === 1 ? '' : 's') . ' for review.');
redirect(base_url('social-studio.php'));
