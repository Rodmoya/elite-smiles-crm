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

smile_design_update_case_preferences($caseId, [
    'procedure_interest' => post('procedure_interest', (string)($case['procedure_interest'] ?? '')),
    'selected_style' => post('selected_style', (string)($case['selected_style'] ?? 'natural')),
    'shade_goal' => post('shade_goal', (string)($case['shade_goal'] ?? '110')),
    'treatment_scope' => post('treatment_scope', (string)($case['treatment_scope'] ?? 'upper')),
    'smile_width_goal' => post('smile_width_goal', (string)($case['smile_width_goal'] ?? 'keep_current')),
], auth_user_id());

flash_set('success', 'Case details updated.');
redirect(base_url('smile-design/cases/' . $caseId . '#source'));
