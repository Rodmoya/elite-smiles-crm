<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/mailings/mailing_service.php';

require_marketing_access();
require_csrf();

$campaignId = (int)post('campaign_id', 0);
$result = mailing_generate_image_for_campaign($campaignId);
flash_set(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Image generation complete.'));
redirect(base_url('patient-mailings.php'));
