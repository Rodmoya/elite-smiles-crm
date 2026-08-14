<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/mailings/mailing_service.php';

$token = trim((string)input('t'));
$target = '';
if ($token !== '') {
    mailing_ensure_schema();
    $recipient = db_one('SELECT * FROM mailing_recipients WHERE tracking_token = :token LIMIT 1', ['token' => $token]);
    if ($recipient) {
        $campaign = mailing_campaign((int)$recipient['campaign_id']);
        $target = trim((string)($campaign['cta_url'] ?? ''));
        db_execute('UPDATE mailing_recipients SET clicked_at = COALESCE(clicked_at, NOW()) WHERE id = :id LIMIT 1', ['id' => (int)$recipient['id']]);
        db_execute('UPDATE mailing_contacts SET last_engaged_at = NOW() WHERE id = :id LIMIT 1', ['id' => (int)$recipient['contact_id']]);
        mailing_log_event((int)$recipient['campaign_id'], (int)$recipient['contact_id'], (int)$recipient['id'], 'clicked', ['target' => $target]);
    }
}

if ($target === '' || !preg_match('#^https://#i', $target)) {
    $target = base_url('l/veneers-draper-google-v2?utm_source=patient_mailings&utm_medium=email');
}
header('Location: ' . $target);
exit;
