<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/mailings/mailing_service.php';

$token = trim((string)($_GET['t'] ?? ''));
if ($token !== '') {
    mailing_ensure_schema();
    $recipient = db_one('SELECT * FROM mailing_recipients WHERE tracking_token = :token LIMIT 1', ['token' => $token]);
    if ($recipient) {
        db_execute('UPDATE mailing_recipients SET opened_at = COALESCE(opened_at, NOW()) WHERE id = :id LIMIT 1', ['id' => (int)$recipient['id']]);
        db_execute('UPDATE mailing_contacts SET last_engaged_at = NOW() WHERE id = :id LIMIT 1', ['id' => (int)$recipient['contact_id']]);
        mailing_log_event((int)$recipient['campaign_id'], (int)$recipient['contact_id'], (int)$recipient['id'], 'opened');
    }
}
$gif = base64_decode('R0lGODlhAQABAPAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==');
header('Content-Type: image/gif');
header('Content-Length: ' . strlen((string)$gif));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
echo $gif;
