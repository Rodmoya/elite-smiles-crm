<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/mailings/mailing_service.php';

require_auth();
require_csrf();

$campaignId = (int)post('campaign_id', 0);
$result = mailing_send_campaign($campaignId, 50);
flash_set(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Send complete.'));
redirect(base_url('patient-mailings.php'));
