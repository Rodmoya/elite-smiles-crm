<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/mailings/mailing_service.php';

$token = trim((string)input('t'));
$contactId = $token !== '' ? mailing_verify_contact_token($token, 'unsubscribe') : 0;
$ok = false;
if ($contactId > 0) {
    mailing_ensure_schema();
    $ok = db_execute(
        "UPDATE mailing_contacts SET opt_status = 'unsubscribed', opted_out_at = NOW(), updated_at = NOW() WHERE id = :id LIMIT 1",
        ['id' => $contactId]
    );
    db_execute("UPDATE mailing_recipients SET unsubscribed_at = NOW(), status = IF(status = 'sent', 'unsubscribed', status) WHERE contact_id = :id", ['id' => $contactId]);
    mailing_log_event(null, $contactId, null, 'unsubscribed', ['source' => 'one_click']);
}

http_response_code($ok ? 200 : 400);
header('Content-Type: text/html; charset=utf-8');
$title = $ok ? 'You are unsubscribed' : 'Unable to unsubscribe';
$message = $ok
    ? 'You will no longer receive Elite Smiles newsletters or offers. If this was a mistake, please call us at (801) 572-6262.'
    : 'This unsubscribe link is invalid. Please call Elite Smiles at (801) 572-6262 and we will take care of it.';
echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($title) . '</title><style>body{margin:0;background:#f6f2eb;color:#111827;font-family:Arial,sans-serif}.wrap{min-height:100vh;display:grid;place-items:center;padding:28px}.card{max-width:560px;background:#fff;border:1px solid #e8dfd1;border-radius:18px;padding:34px;box-shadow:0 18px 45px rgba(15,23,42,.08)}h1{margin:0 0 12px;font-size:28px}p{margin:0;color:#4b5563;font-size:16px;line-height:1.6}</style></head><body><main class="wrap"><section class="card"><h1>' . e($title) . '</h1><p>' . e($message) . '</p></section></main></body></html>';
