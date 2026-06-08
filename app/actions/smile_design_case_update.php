<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../smile_design/smile_design_service.php';

require_auth();
require_csrf();
smile_design_ensure_schema();

$caseId = (int)post('case_id', 0);
$case = smile_design_case($caseId);
if (!$case) {
    flash_set('error', 'Smile case not found.');
    redirect(base_url('smile-design/cases'));
}

$email = strtolower(trim((string)post('email', '')));
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_set('error', 'Please enter a valid email address.');
    redirect(base_url('smile-design/cases/' . $caseId . '#source'));
}

smile_design_update_case_contact($caseId, [
    'first_name' => post('first_name', ''),
    'last_name' => post('last_name', ''),
    'patient_name' => post('patient_name', ''),
    'email' => $email,
    'phone' => post('phone', ''),
], auth_user_id());

flash_set('success', 'Patient and contact details updated.');
redirect(base_url('smile-design/cases/' . $caseId . '#source'));
