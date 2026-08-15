<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/patient_experience/patient_experience_service.php';

function contract_expect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$leadId = 0;
$contractId = 0;
$libraryLabel = 'Contract QA ' . bin2hex(random_bytes(5));
try {
    patient_experience_ensure_schema();
    $creatorMarkup = (string)file_get_contents(dirname(__DIR__) . '/app/patient_experience/contract_creator.php');
    $patientExperienceMarkup = (string)file_get_contents(dirname(__DIR__) . '/patient-experience.php');
    $kioskMarkup = (string)file_get_contents(dirname(__DIR__) . '/patient-experience/kiosk.php');
    $kioskApiMarkup = (string)file_get_contents(dirname(__DIR__) . '/app/api/patient_experience_kiosk.php');
    $legacySidebarMarkup = (string)file_get_contents(dirname(__DIR__) . '/app/partials/crm_sidebar.php');
    $sidebarMarkup = (string)file_get_contents(dirname(__DIR__) . '/app/partials/crm_sidebar_live.php');
    $patientExperienceMarkup = (string)file_get_contents(dirname(__DIR__) . '/patient-experience.php');
    $tabsStart = strpos($patientExperienceMarkup, 'grid grid-cols-3 gap-1.5');
    $tabsMarkup = $tabsStart === false ? '' : substr($patientExperienceMarkup, $tabsStart, 2200);
    $contractsTabPosition = strpos($tabsMarkup, '>Contracts</a>');
    $patientsTabPosition = strpos($tabsMarkup, '>Intake & Patients</a>');
    contract_expect(str_contains($creatorMarkup, '@page { size: letter; margin: 0; }'), 'Contract print layout is not locked to borderless letter size.');
    contract_expect(!str_contains($creatorMarkup, '<section class="space-y-6 no-print">'), 'The printable contract is hidden by its parent wrapper.');
    contract_expect(str_contains($creatorMarkup, 'padding-top: 1.65in'), 'Preprinted letterhead spacing is missing.');
    contract_expect(str_contains($sidebarMarkup, "'label' => 'Contract Creator'"), 'Contract Creator is missing from the CRM navigation.');
    contract_expect(str_contains($sidebarMarkup, "patient-experience.php?tab=contracts"), 'Contract Creator navigation does not deep-link to the contracts tab.');
    contract_expect(str_contains($sidebarMarkup, "'key' => 'patient_experience', 'label' => 'Patient Experience', 'href' => base_url('patient-experience.php?tab=patients')"), 'Patient Experience navigation does not open Intake first.');
    contract_expect($contractsTabPosition !== false && $patientsTabPosition !== false && $patientsTabPosition < $contractsTabPosition, 'Intake is not the first Patient Experience tab.');
    contract_expect(str_contains($patientExperienceMarkup, "get('tab', 'patients')"), 'Patient Experience does not open Intake by default.');
    contract_expect(str_contains($patientExperienceMarkup, "\$activeTab = 'patients';"), 'Invalid Patient Experience tabs do not fall back to Intake.');
    contract_expect(str_contains($legacySidebarMarkup, "patient-experience.php?tab=patients"), 'Legacy Patient Experience navigation does not open Intake first.');
    contract_expect(str_contains($patientExperienceMarkup, 'walk_in=1') && str_contains($patientExperienceMarkup, 'walkInIntakeQrUrl'), 'Permanent walk-in intake QR is missing.');
    contract_expect(str_contains($patientExperienceMarkup, 'patient_experience_contract_qr_data_url($walkInIntakeUrl)'), 'Walk-in intake QR is not generated locally.');
    contract_expect(str_contains($kioskMarkup, 'kioskToken ? beginSession : beginDirectSession'), 'Walk-in QR does not start a new intake automatically.');
    contract_expect(str_contains($kioskMarkup, 'grid-template-columns: repeat(3, minmax(0, 1fr))'), 'Patient intake does not use the compact three-column desktop form layout.');
    contract_expect(str_contains($kioskMarkup, "'radio', 'yes_no'") && str_contains($kioskMarkup, 'form-choice-grid') && str_contains($kioskMarkup, '--choice-columns:'), 'Radio and yes/no choices must span the form and use responsive option columns.');
    contract_expect(str_contains($kioskMarkup, "const isConsent = category === 'consent'"), 'Patient forms do not switch into a dedicated consent-document mode.');
    contract_expect(str_contains($kioskMarkup, 'consent-letterhead') && str_contains($kioskMarkup, 'Elite Smiles by Walter Meden, DDS'), 'Consent documents are missing the branded legal letterhead.');
    contract_expect(str_contains($kioskMarkup, "'Step ' + phaseNumber + ' of 3"), 'Patient forms do not communicate the Intake, Consents, and Review phases.');
    contract_expect(str_contains($kioskMarkup, "input.closest('.form-signature-panel')"), 'Captured signatures are not connected to their visible consent preview.');
    $packetDefinition = patient_experience_packet_definition();
    contract_expect((int)($packetDefinition['version'] ?? 0) === 4, 'The protected-SSN consent packet was not versioned.');
    $patientInformation = null;
    foreach ((array)($packetDefinition['sections'] ?? []) as $packetSection) {
        if ((string)($packetSection['section_key'] ?? '') === 'patient_information') $patientInformation = $packetSection;
    }
    $ssnField = null;
    foreach ((array)($patientInformation['fields'] ?? []) as $field) {
        if ((string)($field['key'] ?? '') === 'patient_ssn') $ssnField = $field;
    }
    contract_expect(is_array($ssnField) && (string)($ssnField['type'] ?? '') === 'ssn' && !empty($ssnField['sensitive']), 'Patient information is missing the protected Social Security number field.');
    $protectedSsn = patient_experience_encrypt_sensitive_value('123-45-6789');
    contract_expect(!str_contains(json_encode($protectedSsn) ?: '', '123-45-6789'), 'Sensitive intake values must not be stored as plaintext.');
    contract_expect(patient_experience_decrypt_sensitive_value($protectedSsn) === '123-45-6789', 'Sensitive intake encryption does not round-trip safely.');
    contract_expect(patient_experience_sensitive_answer_label($ssnField, '123-45-6789') === '•••-••-6789', 'SSN review and print output must reveal only the last four digits.');
    contract_expect(str_contains($kioskMarkup, 'data-ssn-input="1"') && str_contains($kioskMarkup, 'type="password" inputmode="numeric"'), 'The SSN control must be masked and use a numeric keyboard.');
    $consentKeys = [];
    foreach ((array)($packetDefinition['sections'] ?? []) as $packetSection) {
        if ((string)($packetSection['category'] ?? '') === 'consent') $consentKeys[] = (string)($packetSection['section_key'] ?? '');
    }
    contract_expect($consentKeys === ['information_authorization', 'consent_to_proceed', 'hipaa_acknowledgement', 'office_insurance_policy', 'photo_image_consent'], 'The digital consent packet must match the five consent documents in the supplied source packet.');
    contract_expect(!in_array('no_recording_policy', $consentKeys, true), 'The source packet does not include a separate no-recording consent.');
    $insuranceChildren = patient_experience_field_children(['key' => 'primary_insurance', 'type' => 'insurance', 'label' => 'Primary insurance']);
    $subscriberSsn = array_values(array_filter($insuranceChildren, static fn(array $field): bool => (string)($field['key'] ?? '') === 'primary_insurance_subscriber_ssn'));
    contract_expect(count($subscriberSsn) === 1 && !empty($subscriberSsn[0]['sensitive']), 'Insurance subscriber SSNs must use the protected field path.');
    foreach ((array)($packetDefinition['sections'] ?? []) as $packetSection) {
        if ((string)($packetSection['category'] ?? '') !== 'consent') continue;
        $fieldTypes = array_map(static fn(array $field): string => (string)($field['type'] ?? ''), (array)($packetSection['fields'] ?? []));
        contract_expect(in_array('digital_initials', $fieldTypes, true), 'Consent is missing required initials: ' . (string)($packetSection['section_key'] ?? 'unknown'));
        contract_expect(in_array('digital_signature', $fieldTypes, true), 'Consent is missing its individual signature: ' . (string)($packetSection['section_key'] ?? 'unknown'));
    }
    contract_expect(str_contains($kioskMarkup, "patient_name: 'Walk-in Patient'") && !str_contains($kioskMarkup, "patient_name: 'Test Patient'"), 'Walk-in kiosk still uses a test patient identity.');
    contract_expect(str_contains($kioskApiMarkup, "\$patientName = 'Walk-in Patient';") && !str_contains($kioskApiMarkup, "\$patientName = 'Test Patient';"), 'Walk-in API still creates test patients.');
    contract_expect(str_contains($patientExperienceMarkup, 'auto_begin=1'), 'Patient-specific intake QR does not open forms immediately.');
    contract_expect(str_contains($creatorMarkup, 'grid-cols-1 gap-2 sm:grid-cols-2'), 'Included treatment controls are not using the adaptive two-column layout.');
    contract_expect(!str_contains($creatorMarkup, '>3. Treatment area<'), 'The obsolete standalone treatment-area section is still visible.');
    contract_expect(str_contains($creatorMarkup, 'id="contract-area-modal"'), 'The per-procedure tooth selector modal is missing.');
    contract_expect(str_contains($creatorMarkup, 'line_item_teeth['), 'Per-procedure tooth selections are not submitted with the contract.');
    contract_expect(str_contains($creatorMarkup, 'id="preview-line-items" class="contract-treatment-list'), 'Included treatment list is missing from the contract preview.');
    contract_expect(!str_contains($creatorMarkup, 'contract-copy-grid'), 'Contract preview incorrectly splits the full agreement into two columns.');
    contract_expect(!str_contains($creatorMarkup, '.contract-treatment-list { display:grid'), 'Contract preview does not use the original single-column treatment list.');
    contract_expect(str_contains($creatorMarkup, 'font-family:Calibri, Arial, sans-serif'), 'Contract preview does not use the original document typography.');
    contract_expect(str_contains($creatorMarkup, 'contract-original-copy') && str_contains($creatorMarkup, 'contract-signature-original'), 'Contract preview is missing the original document structure.');
    contract_expect(str_contains($creatorMarkup, 'id="contract-date" type="date" name="agreement_date" required'), 'Agreement date is not an editable required date field.');
    contract_expect(str_contains($creatorMarkup, 'id="preview-signature-patient"'), 'Patient name is missing beneath the preview signature line.');
    contract_expect(str_contains($creatorMarkup, 'align-items:start; margin:8pt 0 20px;'), 'Preview signature and date are not aligned or moved ten more pixels above the note.');
    contract_expect(str_contains($creatorMarkup, '.contract-closing-block { margin-top:auto; margin-bottom:5px; }'), 'Contract preview signature and cancellation language are not anchored together at the bottom.');
    contract_expect(str_contains($creatorMarkup, 'class="text-[9pt] leading-[1.15]"><strong>Treatment Plan Cancellation.'), 'Contract preview cancellation language was not reduced by one point.');
    contract_expect(!str_contains($creatorMarkup, '>Included treatment<'), 'Contract preview still contains a modern section heading that is absent from the originals.');
    contract_expect(str_contains($creatorMarkup, 'height:11in !important'), 'Contract preview print output is not constrained to one Letter page.');
    contract_expect(!str_contains($creatorMarkup, '>Financial summary<'), 'The contract preview still contains the non-original financial summary box.');
    contract_expect(str_contains($creatorMarkup, 'contract-option flex h-[72px]'), 'Service controls do not use the compact, consistent 72px height.');
    contract_expect(str_contains($creatorMarkup, 'text-[13px] leading-4') && str_contains($creatorMarkup, 'text-[11px] font-medium leading-[14px]'), 'Compact service labels do not use the fitted typography scale.');
    contract_expect(str_contains($creatorMarkup, 'Add to this contract') && str_contains($creatorMarkup, 'Add to treatment library'), 'Custom service one-time and library actions are missing.');
    contract_expect(str_contains($creatorMarkup, 'Defaults to 25%'), 'The automatic deposit behavior is not explained in the form.');
    contract_expect(str_contains($creatorMarkup, 'w-[147px]') && str_contains($creatorMarkup, 'text-[10px]'), 'The digital branded preview header was not reduced by about 30%.');
    contract_expect(str_contains($creatorMarkup, 'contract-payment-notice') && str_contains($creatorMarkup, 'white-space:nowrap'), 'The highlighted one-line payment notice is missing from the preview.');
    contract_expect(str_contains($creatorMarkup, '.contract-payment-notice { margin-bottom:16pt;'), 'Preview needs more space between the payment notice and procedures.');
    contract_expect(str_contains($creatorMarkup, '.contract-treatment-list { margin:0 0 16pt;'), 'Preview needs more space between procedures and legal language.');
    contract_expect(str_contains($creatorMarkup, '.contract-treatment-list li { margin:0 0 3pt;'), 'Preview procedures do not have the requested subtle row spacing.');
    contract_expect(str_contains($creatorMarkup, 'class="contract-sedation"') && str_contains($creatorMarkup, '.contract-sedation { color:#b91c1c; }'), 'Preview sedation language is not red.');
    contract_expect(str_contains($creatorMarkup, '<strong>Optional</strong>'), 'Preview sedation language does not bold Optional.');
    contract_expect(str_contains($creatorMarkup, "<strong><?= e((string)\$originalTerms['discount_acceptance']) ?></strong>"), 'Preview discounted-price language is not bold.');
    $creatorScriptPosition = strpos($creatorMarkup, '<script>');
    contract_expect($creatorScriptPosition !== false && !str_contains(substr($creatorMarkup, $creatorScriptPosition), '@media'), 'A CSS media rule was rendered inside the Contract Creator JavaScript.');
    $publicContractMarkup = (string)file_get_contents(dirname(__DIR__) . '/patient-experience/contract/index.php');
    contract_expect(str_contains($publicContractMarkup, 'class="agreement-treatment-list'), 'Included treatment list is missing from the signing contract.');
    contract_expect(!str_contains($publicContractMarkup, 'agreement-copy-grid'), 'Signing contract incorrectly splits the full agreement into two columns.');
    contract_expect(!str_contains($publicContractMarkup, '.agreement-treatment-list { display:grid'), 'Signing contract does not use the original single-column treatment list.');
    contract_expect(str_contains($publicContractMarkup, 'font-family:Calibri, Arial, sans-serif'), 'Signing contract does not use the original document typography.');
    contract_expect(str_contains($publicContractMarkup, 'agreement-original-copy') && str_contains($publicContractMarkup, 'agreement-signature-original'), 'Signing contract is missing the original document structure.');
    contract_expect(str_contains($publicContractMarkup, 'agreement-signature-patient') && str_contains($publicContractMarkup, "\$agreement['patient_name']"), 'Patient name is missing beneath the public signature line.');
    contract_expect(str_contains($publicContractMarkup, 'align-items:start; margin:8pt 0 20px;'), 'Public signature and date are not aligned or moved ten more pixels above the note.');
    contract_expect(str_contains($publicContractMarkup, '.agreement-closing-block { margin-top:auto; margin-bottom:5px; }'), 'Signing-document signature and cancellation language are not anchored together at the bottom.');
    contract_expect(str_contains($publicContractMarkup, 'class="text-[9pt] leading-[1.15]"><strong>Treatment Plan Cancellation.'), 'Signing contract cancellation language was not reduced by one point.');
    contract_expect(!str_contains($publicContractMarkup, '>Included treatment<'), 'Signing contract still contains a modern section heading that is absent from the originals.');
    contract_expect(str_contains($publicContractMarkup, 'height:11in'), 'Signing contract print output is not constrained to one Letter page.');
    contract_expect(str_contains($publicContractMarkup, 'w-[147px]') && str_contains($publicContractMarkup, 'text-[10px]'), 'The digital branded signing header was not reduced by about 30%.');
    contract_expect(!str_contains($publicContractMarkup, '>Financial summary<'), 'The signing contract still contains the non-original financial summary box.');
    contract_expect(str_contains($publicContractMarkup, 'agreement-payment-notice') && str_contains($publicContractMarkup, 'white-space:nowrap'), 'The highlighted one-line payment notice is missing from the signing document.');
    contract_expect(str_contains($publicContractMarkup, '.agreement-payment-notice { margin-bottom:16pt;'), 'Signing document needs more space between the payment notice and procedures.');
    contract_expect(str_contains($publicContractMarkup, '.agreement-treatment-list { margin:0 0 16pt;'), 'Signing document needs more space between procedures and legal language.');
    contract_expect(str_contains($publicContractMarkup, '.agreement-treatment-list li { margin:0 0 3pt;'), 'Signing-document procedures do not have the requested subtle row spacing.');
    contract_expect(str_contains($publicContractMarkup, 'class="agreement-sedation"') && str_contains($publicContractMarkup, '.agreement-sedation { color:#b91c1c; }'), 'Signing-document sedation language is not red.');
    contract_expect(str_contains($publicContractMarkup, '<strong>Optional</strong>'), 'Signing-document sedation language does not bold Optional.');
    contract_expect(str_contains($publicContractMarkup, "<strong><?= e((string)\$terms['discount_acceptance']) ?></strong>"), 'Signing-document discounted-price language is not bold.');
    foreach (['cashier_check', 'credit_card', 'treatment_changes', 'insurance_responsibility', 'sedation', 'discount_acceptance', 'original_cancellation'] as $termKey) {
        contract_expect(trim((string)(patient_experience_contract_original_terms()[$termKey] ?? '')) !== '', 'Original contract language is missing: ' . $termKey);
    }
    contract_expect(str_contains((string)patient_experience_contract_original_terms()['sedation'], "two weeks' notice"), 'Sedation cancellation notice is missing from the approved terms.');
    $historicalOptions = [
        'diagnostic_wax_up', 'full_mouth_debridement', 'therapeutic_parenteral_medication',
        'implant_abutment_crown', 'pedicle_graft', 'high_end_temporaries', 'dexamethasone',
    ];
    $definedOptions = [];
    foreach (patient_experience_contract_definitions() as $definition) {
        $definedOptions = array_merge($definedOptions, array_keys((array)($definition['options'] ?? [])));
    }
    foreach ($historicalOptions as $optionKey) {
        contract_expect(in_array($optionKey, $definedOptions, true), 'Historical treatment option is missing: ' . $optionKey);
    }
    $definitions = patient_experience_contract_definitions();
    contract_expect(($definitions['veneers']['option_area_modes']['veneers'] ?? '') === 'teeth', 'Veneers does not open the tooth selector.');
    contract_expect(($definitions['veneers']['option_area_modes']['internal_restorations'] ?? '') === 'teeth', 'Internal restorations does not open the tooth selector.');
    contract_expect(in_array('Digital smile design', (array)($definitions['veneers']['options'] ?? []), true), 'Veneers treatment options do not identify smile design as digital only.');
    contract_expect(!in_array('Digital and analog smile design', (array)($definitions['veneers']['options'] ?? []), true), 'Legacy analog smile design wording is still patient-facing.');
    contract_expect(patient_experience_contract_format_teeth([5, 6, 7]) === '#5,6,7', 'Short tooth selections do not retain the required #5,6,7 format.');
    contract_expect(patient_experience_contract_format_teeth([2, 3, 4, 5, 6, 7, 8]) === '#2-8', 'Consecutive tooth selections are not collapsed to #2-8.');
    contract_expect(patient_experience_contract_format_teeth([2, 3, 4, 5, 6, 7, 8, 10]) === '#2-8,10', 'Mixed tooth ranges are not formatted correctly.');
    foreach (['extractions', 'crowns', 'bridges'] as $toothProcedure) {
        contract_expect(($definitions['complex_restorative']['option_area_modes'][$toothProcedure] ?? '') === 'teeth', ucfirst($toothProcedure) . ' does not retain its own tooth selection.');
    }
    $email = 'contract-test-' . bin2hex(random_bytes(5)) . '@example.invalid';
    $leadId = db_insert("INSERT INTO leads (full_name,email,phone,status,created_at,updated_at) VALUES ('Contract Test Patient',:email,'8015550100','contacted',NOW(),NOW())", ['email' => $email]);

    $normalized = patient_experience_contract_input([
        'lead_id' => $leadId,
        'agreement_date' => '2026-09-15',
        'patient_name' => 'Contract Test Patient',
        'patient_phone' => '',
        'patient_email' => '',
        'treatment_key' => 'veneers',
        'line_items' => ['veneers', 'gingivectomy'],
        'line_item_teeth' => [
            'veneers' => ['4', '5', '5', '13', '99'],
            'gingivectomy' => ['6', '7'],
        ],
        'original_price' => '20000',
        'discount_amount' => '4000',
        'final_price' => '16000',
        'insurance_estimate' => '1000',
        'deposit_amount' => '3750',
    ]);
    contract_expect(($normalized['line_items'][0]['teeth'] ?? []) === [4, 5, 13], 'Per-procedure tooth normalization failed.');
    contract_expect(($normalized['line_items'][1]['teeth'] ?? []) === [6, 7], 'A second procedure did not retain its independent tooth selection.');
    contract_expect((float)$normalized['patient_responsibility'] === 15000.0, 'Patient responsibility calculation failed.');
    contract_expect((float)$normalized['remaining_balance'] === 11250.0, 'Remaining balance calculation failed.');
    contract_expect($normalized['agreement_date'] === '2026-09-15', 'Editable agreement date was not normalized correctly.');
    contract_expect(patient_experience_contract_validate($normalized) === [], 'Valid contract was rejected.');

    $automaticDeposit = patient_experience_contract_input([
        'patient_name' => 'Automatic Deposit Patient',
        'treatment_key' => 'veneers',
        'line_items' => ['veneers'],
        'line_item_teeth' => ['veneers' => [7, 8, 9, 10]],
        'final_price' => 16000,
        'insurance_estimate' => 1000,
        'deposit_amount' => '',
    ]);
    contract_expect((float)$automaticDeposit['deposit_amount'] === 3750.0, 'Blank deposit did not default to 25% of patient responsibility.');
    contract_expect((float)$automaticDeposit['remaining_balance'] === 11250.0, 'Automatic deposit did not update the remaining balance.');

    $archNormalized = patient_experience_contract_input([
        'patient_name' => 'Arch Test Patient',
        'treatment_key' => 'all_on_x',
        'line_items' => ['permanent_prosthesis'],
        'line_item_arch' => ['permanent_prosthesis' => 'upper'],
        'final_price' => 25000,
    ]);
    contract_expect(($archNormalized['line_items'][0]['arch_scope'] ?? '') === 'upper', 'An arch-based service did not retain its independent arch selection.');
    contract_expect(patient_experience_contract_validate($archNormalized) === [], 'A valid arch-based procedure was rejected.');

    $legacyNormalized = patient_experience_contract_input([
        'patient_name' => 'Legacy Contract Patient',
        'treatment_key' => 'veneers',
        'line_items' => ['veneers'],
        'selected_teeth' => [7, 8, 9, 10],
        'final_price' => 12000,
    ]);
    contract_expect(($legacyNormalized['line_items'][0]['teeth'] ?? []) === [7, 8, 9, 10], 'Legacy global tooth selections are not migrated into the selected service.');

    $invalid = $normalized;
    $invalid['line_items'][0]['teeth'] = [];
    contract_expect(isset(patient_experience_contract_validate($invalid)['line_item_area']), 'A tooth-based procedure did not require its own tooth selection.');

    $saved = patient_experience_contract_save([
        'lead_id' => $leadId,
        'agreement_date' => '2026-09-15',
        'patient_name' => 'Contract Test Patient',
        'treatment_key' => 'veneers',
        'line_items' => ['veneers', 'gingivectomy'],
        'line_item_teeth' => ['veneers' => [4, 5, 13], 'gingivectomy' => [6, 7]],
        'original_price' => 20000,
        'discount_amount' => 4000,
        'final_price' => 16000,
        'insurance_estimate' => 1000,
        'deposit_amount' => 3750,
        'custom_item_text' => $libraryLabel,
        'custom_library_items_json' => json_encode([['label' => $libraryLabel, 'area_mode' => 'none', 'treatment_key' => 'veneers']]),
    ], null);
    contract_expect(!empty($saved['ok']), 'Contract draft was not saved.');
    $contractId = (int)$saved['contract_id'];
    $contract = patient_experience_contract_by_id($contractId);
    contract_expect((bool)$contract, 'Saved contract was not found.');
    contract_expect(str_starts_with((string)$contract['contract_number'], 'ES-'), 'Contract number was not generated.');
    contract_expect((string)$contract['agreement_date'] === '2026-09-15', 'Saved contract lost the edited agreement date.');
    contract_expect(($contract['line_items'][0]['teeth'] ?? []) === [4, 5, 13], 'Saved contract lost the Veneers tooth assignment.');
    contract_expect(($contract['line_items'][1]['teeth'] ?? []) === [6, 7], 'Saved contract lost the Gingivectomy tooth assignment.');
    contract_expect(in_array($libraryLabel, array_column((array)$contract['line_items'], 'label'), true), 'Saved contract lost the custom service.');
    $libraryRow = db_one('SELECT treatment_key,label FROM patient_experience_contract_library_items WHERE label=:label LIMIT 1', ['label' => $libraryLabel]);
    contract_expect((string)($libraryRow['treatment_key'] ?? '') === 'veneers', 'Custom service was not saved to the selected treatment library.');
    $libraryDefinitions = patient_experience_contract_definitions();
    contract_expect(in_array($libraryLabel, (array)($libraryDefinitions['veneers']['options'] ?? []), true), 'Saved library service is not available on the next contract.');

    $delivery = patient_experience_contract_prepare_delivery($contractId, [], null);
    contract_expect(!empty($delivery['ok']), 'Immutable delivery version was not created.');
    contract_expect((int)db_value('SELECT COUNT(*) FROM patient_experience_contract_versions WHERE contract_id=:id', ['id' => $contractId]) === 1, 'Contract version count is incorrect.');
    parse_str((string)parse_url((string)$delivery['url'], PHP_URL_QUERY), $query);
    $token = (string)($query['t'] ?? '');
    $resolved = patient_experience_contract_from_token($token, false);
    contract_expect((bool)$resolved, 'Secure contract token did not resolve.');
    contract_expect(hash('sha256', (string)$resolved['snapshot_json']) === (string)$resolved['snapshot_hash'], 'Immutable snapshot hash does not match.');
    contract_expect((string)($resolved['snapshot']['contract']['date'] ?? '') === 'September 15, 2026', 'Immutable snapshot did not preserve the edited agreement date.');

    $_SERVER['REMOTE_ADDR'] = '203.0.113.25';
    $_SERVER['HTTP_USER_AGENT'] = 'Elite Smiles Contract Test';
    $signatureData = 'data:image/png;base64,' . base64_encode(str_repeat('signed-contract-test', 20));
    $signed = patient_experience_contract_sign($token, [
        'signer_name' => 'Contract Test Patient',
        'signer_relationship' => 'self',
        'cancellation_acknowledged' => '1',
        'signature_data' => $signatureData,
    ]);
    contract_expect(!empty($signed['ok']), 'Contract signature was not stored.');
    $signature = db_one('SELECT * FROM patient_experience_contract_signatures WHERE contract_id=:id LIMIT 1', ['id' => $contractId]);
    contract_expect((string)($signature['ip_address'] ?? '') === '203.0.113.25', 'Signature IP address was not stored.');
    contract_expect((int)($signature['acknowledged'] ?? 0) === 1, 'Cancellation acknowledgment was not stored.');
    contract_expect((string)db_value('SELECT status FROM patient_experience_contracts WHERE id=:id', ['id' => $contractId]) === 'signed', 'Contract did not become immutable after signing.');

    $editAttempt = patient_experience_contract_save(array_merge($normalized, ['contract_id' => $contractId]), null);
    contract_expect(empty($editAttempt['ok']), 'Signed contract was incorrectly editable.');
} finally {
    db_query('DELETE FROM patient_experience_contract_library_items WHERE label=:label', ['label' => $libraryLabel]);
    if ($contractId > 0) {
        db_query('DELETE FROM patient_experience_contract_deliveries WHERE contract_id=:id', ['id' => $contractId]);
        db_query('DELETE FROM patient_experience_contract_signatures WHERE contract_id=:id', ['id' => $contractId]);
        db_query('DELETE FROM patient_experience_contract_versions WHERE contract_id=:id', ['id' => $contractId]);
        db_query('DELETE FROM patient_experience_contracts WHERE id=:id', ['id' => $contractId]);
    }
    if ($leadId > 0) {
        db_query("DELETE FROM patient_experience_audit_events WHERE lead_id=:lead_id AND event_key IN ('contract_draft_saved','contract_sent','contract_signed')", ['lead_id' => $leadId]);
        db_query('DELETE FROM leads WHERE id=:id LIMIT 1', ['id' => $leadId]);
    }
}

echo "Patient Experience contract tests passed.\n";
