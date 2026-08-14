<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/mailings/mailing_service.php';

require_marketing_access();
require_csrf();

$source = (string)post('source', 'manual');
$contacts = (string)post('contacts', '');
$result = mailing_import_contacts_from_text($contacts, $source);

flash_set('success', 'Imported/updated ' . $result['imported'] . ' contact(s). Skipped ' . $result['skipped'] . '.');
redirect(base_url('patient-mailings.php'));
