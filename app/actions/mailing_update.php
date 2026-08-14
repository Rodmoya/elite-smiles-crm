<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/mailings/mailing_service.php';

require_marketing_access();
require_csrf();

$campaignId = (int)post('campaign_id', 0);
$result = mailing_update_campaign($campaignId, [
    'title' => post('title', ''),
    'subject' => post('subject', ''),
    'preview_text' => post('preview_text', ''),
    'hero_title' => post('hero_title', ''),
    'body_html' => post('body_html', ''),
    'body_text' => post('body_text', ''),
    'cta_label' => post('cta_label', ''),
    'cta_url' => post('cta_url', ''),
]);

flash_set(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Campaign update complete.'));
redirect(base_url('patient-mailings.php?campaign_id=' . $campaignId . '#review'));
