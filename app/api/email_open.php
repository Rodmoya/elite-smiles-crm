<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * Open-tracking pixel for patient emails.
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/leads/lead_email.php';

$token = trim((string) input('t'));
if ($token !== '') {
    lead_email_mark_opened($token);
}

$gif = base64_decode('R0lGODlhAQABAPAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==');
header('Content-Type: image/gif');
header('Content-Length: ' . strlen((string)$gif));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
echo $gif;
