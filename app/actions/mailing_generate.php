<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/mailings/mailing_service.php';

require_auth();
require_csrf();

$goal = (string)post('goal', 'education');
$instruction = trim((string)post('instruction', ''));
$ctaHint = trim((string)post('cta_hint', ''));
if ($ctaHint !== '') {
    $instruction .= "\nUse this CTA destination unless there is a strong reason not to: " . $ctaHint;
}

$campaignId = mailing_generate_campaign($goal, $instruction, (int)(auth_user_id() ?: 0));
if ($campaignId > 0 && $ctaHint !== '' && preg_match('#^https?://#i', $ctaHint)) {
    db_execute('UPDATE mailing_campaigns SET cta_url = :cta_url WHERE id = :id LIMIT 1', [
        'id' => $campaignId,
        'cta_url' => $ctaHint,
    ]);
}
flash_set('success', 'Newsletter draft created for review.');
redirect(base_url('patient-mailings.php?campaign_id=' . $campaignId));
