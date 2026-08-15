<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/mailings/mailing_service.php';

require_marketing_access();
require_csrf();

$campaignId = (int)post('campaign_id', 0);
$date = trim((string)post('schedule_date', ''));
$time = trim((string)post('schedule_time', ''));
$result = mailing_schedule_campaign($campaignId, trim($date . ' ' . $time));
flash_set(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Scheduling complete.'));
redirect(base_url('patient-mailings.php?campaign_id=' . $campaignId . '#review'));
