<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * One-click unsubscribe endpoint for patient follow-up emails.
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/leads/lead_communications.php';
require_once dirname(__DIR__) . '/leads/lead_email.php';

$token = trim((string) input('t'));
$leadId = $token !== '' ? lead_email_verify_token($token, 'unsubscribe') : 0;
$ok = $leadId > 0 && lead_email_unsubscribe($leadId);

http_response_code($ok ? 200 : 400);
header('Content-Type: text/html; charset=utf-8');

$title = $ok ? 'You are unsubscribed' : 'Unable to unsubscribe';
$message = $ok
    ? 'You will no longer receive follow-up emails from Elite Smiles. If this was a mistake, please call us at (801) 572-6262.'
    : 'This unsubscribe link is invalid or expired. Please call Elite Smiles at (801) 572-6262 and we will take care of it.';

echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'
    . e($title)
    . '</title><style>body{margin:0;background:#f4f7fb;color:#0f172a;font-family:Arial,Helvetica,sans-serif}.wrap{min-height:100vh;display:grid;place-items:center;padding:28px}.card{max-width:560px;background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:34px;box-shadow:0 18px 45px rgba(15,23,42,.08)}h1{margin:0 0 12px;font-size:28px;line-height:1.2}p{margin:0;color:#475569;font-size:16px;line-height:1.6}</style></head><body><main class="wrap"><section class="card"><h1>'
    . e($title)
    . '</h1><p>'
    . e($message)
    . '</p></section></main></body></html>';
