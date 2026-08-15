<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/mailings/mailing_service.php';

require_marketing_access();
require_csrf();

$goal = (string)post('goal', 'education');
$instruction = trim((string)post('instruction', ''));
$ctaHint = trim((string)post('cta_hint', ''));
$audience = (string)post('audience_filter', 'all_subscribed');
if ($ctaHint !== '') {
    $instruction .= "\nUse this CTA destination unless there is a strong reason not to: " . $ctaHint;
}

$campaignId = mailing_generate_campaign($goal, $instruction, (int)(auth_user_id() ?: 0), $audience);
if ($campaignId > 0 && $ctaHint !== '' && preg_match('#^https?://#i', $ctaHint)) {
    db_execute('UPDATE mailing_campaigns SET cta_url = :cta_url WHERE id = :id LIMIT 1', [
        'id' => $campaignId,
        'cta_url' => $ctaHint,
    ]);
}
$imageResult = $campaignId > 0 ? mailing_generate_image_for_campaign($campaignId) : ['ok' => false, 'message' => 'Draft creation failed.'];
if (!empty($imageResult['ok'])) {
    flash_set('success', 'Complete AI campaign created with OpenAI copy and a Nano Banana image. Review it before approval.');
} else {
    flash_set('error', 'The OpenAI draft was created, but the image needs attention: ' . (string)($imageResult['message'] ?? 'Image generation failed.'));
}
redirect(base_url('patient-mailings.php?campaign_id=' . $campaignId));
