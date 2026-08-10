<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/patient_experience/patient_experience_service.php';

function contract_expect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$leadId = 0;
$contractId = 0;
try {
    patient_experience_ensure_schema();
    $creatorMarkup = (string)file_get_contents(dirname(__DIR__) . '/app/patient_experience/contract_creator.php');
    contract_expect(str_contains($creatorMarkup, '@page { size: letter; margin: 0; }'), 'Contract print layout is not locked to borderless letter size.');
    contract_expect(!str_contains($creatorMarkup, '<section class="space-y-6 no-print">'), 'The printable contract is hidden by its parent wrapper.');
    contract_expect(str_contains($creatorMarkup, 'padding-top: 1.65in'), 'Preprinted letterhead spacing is missing.');
    $email = 'contract-test-' . bin2hex(random_bytes(5)) . '@example.invalid';
    $leadId = db_insert("INSERT INTO leads (full_name,email,phone,status,created_at,updated_at) VALUES ('Contract Test Patient',:email,'8015550100','contacted',NOW(),NOW())", ['email' => $email]);

    $normalized = patient_experience_contract_input([
        'lead_id' => $leadId,
        'patient_name' => 'Contract Test Patient',
        'patient_phone' => '',
        'patient_email' => '',
        'treatment_key' => 'veneers',
        'selected_teeth' => ['4', '5', '5', '13', '99'],
        'line_items' => ['veneers', 'gingivectomy'],
        'original_price' => '20000',
        'discount_amount' => '4000',
        'final_price' => '16000',
        'insurance_estimate' => '1000',
        'deposit_amount' => '3750',
    ]);
    contract_expect($normalized['selected_teeth'] === [4, 5, 13], 'Tooth normalization failed.');
    contract_expect((float)$normalized['patient_responsibility'] === 15000.0, 'Patient responsibility calculation failed.');
    contract_expect((float)$normalized['remaining_balance'] === 11250.0, 'Remaining balance calculation failed.');
    contract_expect(patient_experience_contract_validate($normalized) === [], 'Valid contract was rejected.');

    $invalid = $normalized;
    $invalid['selected_teeth'] = [];
    contract_expect(isset(patient_experience_contract_validate($invalid)['selected_teeth']), 'Veneer contract did not require tooth selection.');

    $saved = patient_experience_contract_save([
        'lead_id' => $leadId,
        'patient_name' => 'Contract Test Patient',
        'treatment_key' => 'veneers',
        'selected_teeth' => [4, 5, 13],
        'line_items' => ['veneers', 'gingivectomy'],
        'original_price' => 20000,
        'discount_amount' => 4000,
        'final_price' => 16000,
        'insurance_estimate' => 1000,
        'deposit_amount' => 3750,
    ], null);
    contract_expect(!empty($saved['ok']), 'Contract draft was not saved.');
    $contractId = (int)$saved['contract_id'];
    $contract = patient_experience_contract_by_id($contractId);
    contract_expect((bool)$contract, 'Saved contract was not found.');
    contract_expect(str_starts_with((string)$contract['contract_number'], 'ES-'), 'Contract number was not generated.');

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
