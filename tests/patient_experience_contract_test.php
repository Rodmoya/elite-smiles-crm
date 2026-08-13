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
    contract_expect(str_contains($sidebarMarkup, "'key' => 'patient_experience', 'label' => 'Patient Experience', 'href' => base_url('patient-experience.php?tab=contracts')"), 'Patient Experience navigation does not open Contracts first.');
    contract_expect($contractsTabPosition !== false && $patientsTabPosition !== false && $contractsTabPosition < $patientsTabPosition, 'Contracts is not the first Patient Experience tab.');
    contract_expect(str_contains($creatorMarkup, 'grid-cols-1 gap-2 sm:grid-cols-2'), 'Included treatment controls are not using the adaptive two-column layout.');
    contract_expect(!str_contains($creatorMarkup, '>3. Treatment area<'), 'The obsolete standalone treatment-area section is still visible.');
    contract_expect(str_contains($creatorMarkup, 'id="contract-area-modal"'), 'The per-procedure tooth selector modal is missing.');
    contract_expect(str_contains($creatorMarkup, 'line_item_teeth['), 'Per-procedure tooth selections are not submitted with the contract.');
    contract_expect(str_contains($creatorMarkup, 'id="preview-line-items" class="contract-treatment-list'), 'Included treatment is not arranged in two columns in the contract preview.');
    contract_expect(!str_contains($creatorMarkup, 'contract-copy-grid'), 'Contract preview incorrectly splits the full agreement into two columns.');
    contract_expect(str_contains($creatorMarkup, 'height:11in !important'), 'Contract preview print output is not constrained to one Letter page.');
    contract_expect(!str_contains($creatorMarkup, '>Financial summary<'), 'The contract preview still contains the non-original financial summary box.');
    contract_expect(str_contains($creatorMarkup, 'contract-option flex h-24'), 'Service controls do not use one consistent height.');
    contract_expect(str_contains($creatorMarkup, 'Add to this contract') && str_contains($creatorMarkup, 'Add to treatment library'), 'Custom service one-time and library actions are missing.');
    contract_expect(str_contains($creatorMarkup, 'Defaults to 25%'), 'The automatic deposit behavior is not explained in the form.');
    contract_expect(str_contains($creatorMarkup, 'contract-payment-notice') && str_contains($creatorMarkup, 'white-space:nowrap'), 'The highlighted one-line payment notice is missing from the preview.');
    $publicContractMarkup = (string)file_get_contents(dirname(__DIR__) . '/patient-experience/contract/index.php');
    contract_expect(str_contains($publicContractMarkup, 'class="agreement-treatment-list'), 'Included treatment is not arranged in two columns in the signing contract.');
    contract_expect(!str_contains($publicContractMarkup, 'agreement-copy-grid'), 'Signing contract incorrectly splits the full agreement into two columns.');
    contract_expect(str_contains($publicContractMarkup, 'height:11in'), 'Signing contract print output is not constrained to one Letter page.');
    contract_expect(!str_contains($publicContractMarkup, '>Financial summary<'), 'The signing contract still contains the non-original financial summary box.');
    contract_expect(str_contains($publicContractMarkup, 'agreement-payment-notice') && str_contains($publicContractMarkup, 'white-space:nowrap'), 'The highlighted one-line payment notice is missing from the signing document.');
    foreach (['cashier_check', 'credit_card', 'treatment_changes', 'insurance_responsibility', 'sedation', 'discount_acceptance', 'original_cancellation'] as $termKey) {
        contract_expect(trim((string)(patient_experience_contract_original_terms()[$termKey] ?? '')) !== '', 'Original contract language is missing: ' . $termKey);
    }
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
    foreach (['extractions', 'crowns', 'bridges'] as $toothProcedure) {
        contract_expect(($definitions['complex_restorative']['option_area_modes'][$toothProcedure] ?? '') === 'teeth', ucfirst($toothProcedure) . ' does not retain its own tooth selection.');
    }
    $email = 'contract-test-' . bin2hex(random_bytes(5)) . '@example.invalid';
    $leadId = db_insert("INSERT INTO leads (full_name,email,phone,status,created_at,updated_at) VALUES ('Contract Test Patient',:email,'8015550100','contacted',NOW(),NOW())", ['email' => $email]);

    $normalized = patient_experience_contract_input([
        'lead_id' => $leadId,
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
