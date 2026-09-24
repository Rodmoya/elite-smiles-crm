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

$firstName = trim((string)post('first_name', ''));
$lastName = trim((string)post('last_name', ''));
$name = trim($firstName . ' ' . $lastName);
if ($firstName === '' || $name === '' || mb_strlen($name) > 190 || preg_match('/[\x00-\x1F\x7F]/u', $name)) {
    flash_set('error', 'Enter a valid patient name (190 characters maximum).');
    redirect(base_url('smile-design/cases/' . $caseId . '#source'));
}

try {
    smile_design_update_case_contact($caseId, [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'patient_name' => $name,
        'email' => (string)($case['email'] ?? ''),
        'phone' => (string)($case['phone'] ?? ''),
    ], auth_user_id());
    flash_set('success', 'Patient name corrected.');
} catch (Throwable $e) {
    esm_log('smile_design', 'Could not correct patient name.', ['case_id' => $caseId, 'error' => $e->getMessage()]);
    flash_set('error', 'Could not save the corrected name. Please try again.');
}

redirect(base_url('smile-design/cases/' . $caseId . '#source'));
