<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/mailings/mailing_service.php';

require_marketing_access();
require_csrf();

$campaignId = (int)post('campaign_id', 0);
$status = (string)post('status', '');
$ok = mailing_update_status($campaignId, $status);
flash_set($ok ? 'success' : 'error', $ok ? 'Mailing campaign updated.' : 'Could not update mailing campaign.');
redirect(base_url('patient-mailings.php'));
