<?php
declare(strict_types=1);

/**
 * Patient Experience treatment-contract service.
 *
 * Drafts are editable. Delivery creates an immutable, hashed version; the
 * patient's signature is always attached to that exact version.
 */

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/mailer.php';
require_once __DIR__ . '/../core/twilio.php';

if (!function_exists('patient_experience_contract_definitions')) {
    function patient_experience_contract_definitions(): array
    {
        return [
            'veneers' => [
                'label' => 'Veneers',
                'tooth_mode' => 'teeth',
                'options' => [
                    'veneers' => 'Veneers',
                    'internal_restorations' => 'Internal restorations',
                    'digital_analog_smile_design' => 'Digital and analog smile design',
                    'gingivectomy' => 'Gingivectomies for cosmetic reasons (laser procedure)',
                    'full_mouth_debridement' => 'Full-mouth debridement with deep scaling',
                    'custom_shade' => 'Custom shade',
                    'orthotic' => 'Orthotic device',
                    'occlusal_guard' => 'Occlusal guard',
                    'ct_scan' => 'CT scan',
                    'ppe' => 'Surgical PPE',
                    'rinses' => 'Rinses',
                ],
            ],
            'all_on_x' => [
                'label' => 'All-on-X',
                'tooth_mode' => 'arch',
                'options' => [
                    'digital_3d_design' => 'Digital 3D design',
                    'diagnostic_wax_up' => 'Diagnostic wax-up',
                    'extractions' => 'Surgical extractions',
                    'alveoloplasty' => 'Alveoloplasty',
                    'bone_membrane_grafts' => 'Bone and membrane grafts',
                    'prp_prf' => 'PRP/PRF',
                    'implants_abutments' => 'Implants and custom abutments',
                    'immediate_prosthesis' => 'Immediate prosthesis',
                    'transitional_prosthesis' => 'Transitional prosthesis',
                    'permanent_prosthesis' => 'Permanent prosthesis',
                    'zirconia' => 'Zirconia prosthesis',
                    'occlusal_guard' => 'Occlusal guard',
                    'ct_scan' => 'CT scan',
                    'ppe' => 'Surgical PPE',
                    'rinses' => 'Rinses',
                ],
            ],
            'lip_repositioning' => [
                'label' => 'Lip Repositioning',
                'tooth_mode' => 'none',
                'options' => [
                    'lip_repositioning' => 'Lip repositioning',
                    'frenectomy' => 'Frenectomy',
                    'botox' => 'Botox as needed for the procedure',
                    'ppe' => 'Surgical PPE',
                    'rinses' => 'Rinses',
                ],
            ],
            'complex_restorative' => [
                'label' => 'Complex Restorative',
                'tooth_mode' => 'teeth_optional',
                'options' => [
                    'digital_scan' => 'Digital scan and design',
                    'root_canal' => 'Root canal therapy',
                    'therapeutic_parenteral_medication' => 'Therapeutic parenteral drug administration',
                    'post_core' => 'Post and core',
                    'core_buildup' => 'Core build-up',
                    'crowns' => 'Crowns',
                    'bridges' => 'Bridges',
                    'veneers' => 'Veneers',
                    'internal_restorations' => 'Internal restorations',
                    'extractions' => 'Surgical extractions',
                    'bone_membrane_grafts' => 'Bone and membrane grafts',
                    'implants_abutments' => 'Implants and custom abutments',
                    'implant_abutment_crown' => 'Implant, custom abutment, and crown',
                    'crown_lengthening' => 'Crown lengthening',
                    'gingivectomy' => 'Gingivectomy for cosmetic reasons',
                    'custom_shade' => 'Custom shade',
                    'full_mouth_debridement' => 'Full-mouth debridement with deep scaling',
                    'prp_prf' => 'PRP/PRF',
                    'pedicle_graft' => 'Pedicle graft',
                    'orthotic' => 'Orthotic device',
                    'high_end_temporaries' => 'High-end temporaries to increase vertical dimension',
                    'occlusal_guard' => 'Occlusal guard',
                    'ct_scan' => 'CT scan',
                    'panoramic_xray' => 'Panoramic X-ray',
                    'dexamethasone' => 'Anti-inflammatory dexamethasone',
                    'ppe' => 'Surgical PPE',
                    'rinses' => 'Rinses',
                ],
            ],
            'custom' => [
                'label' => 'Custom Treatment',
                'tooth_mode' => 'teeth_optional',
                'options' => [],
            ],
        ];
    }
}

if (!function_exists('patient_experience_contract_cancellation_text')) {
    function patient_experience_contract_cancellation_text(): string
    {
        return 'By signing this Treatment Agreement, I understand that Elite Smiles may reserve clinical time and begin treatment planning, laboratory coordination, ordering, and other preparation for my care. If I cancel or discontinue the accepted treatment plan after signing, I may incur a treatment-plan cancellation fee of up to $1,500, based on the clinical time reserved and costs incurred at the time of cancellation. Any applicable cancellation fee may be deducted from amounts already paid.';
    }
}

if (!function_exists('patient_experience_contract_original_terms')) {
    function patient_experience_contract_original_terms(): array
    {
        return [
            'cashier_check' => 'Cashier’s check must be payable to Walter Meden D.D.S.',
            'credit_card' => 'If paying by credit card, a 3% credit card processing fee will apply.',
            'treatment_changes' => 'I am aware that cosmetic/dental treatment can/may change in the process of performing it, and that those changes are what the doctor considers best for my dental health. I am aware that if such changes occur that I am financially responsible for said treatment. All cosmetic, prosthetic fixed or removable and any restoration treatment must be paid before the seating or delivery date.',
            'insurance_responsibility' => 'I am aware that I am responsible for any balances my insurance does not cover.',
            'insurance_estimate' => 'Insurance benefits are estimated based on information provided by your insurer and are not guaranteed. Any portion not paid by insurance remains the patient’s responsibility.',
            'sedation' => 'Optional- I.V. Sedation is available for an hourly fee determined by the anesthesiologist. This charge is payable separately to him/her on the day of your procedure.',
            'discount_acceptance' => 'The above price is a discounted price if the treatment plan is accepted today.',
            'original_cancellation' => 'Note: There will be a cancellation fee of $1,500. on cases $4000. or above. Cases below $4,000. the total deposit will not be refunded.',
        ];
    }
}

if (!function_exists('patient_experience_contract_ensure_schema')) {
    function patient_experience_contract_ensure_schema(): void
    {
        static $done = false;
        if ($done) return;
        $done = true;
        $charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        db_query("CREATE TABLE IF NOT EXISTS patient_experience_contracts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            contract_number VARCHAR(40) NOT NULL DEFAULT '',
            lead_id INT UNSIGNED NULL,
            patient_name VARCHAR(190) NOT NULL DEFAULT '',
            patient_phone VARCHAR(80) NOT NULL DEFAULT '',
            patient_email VARCHAR(190) NOT NULL DEFAULT '',
            treatment_key VARCHAR(80) NOT NULL DEFAULT 'veneers',
            treatment_label VARCHAR(190) NOT NULL DEFAULT 'Veneers',
            arch_scope VARCHAR(20) NOT NULL DEFAULT '',
            selected_teeth_json TEXT NULL,
            line_items_json LONGTEXT NULL,
            custom_item_text TEXT NULL,
            original_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            final_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            insurance_estimate DECIMAL(12,2) NOT NULL DEFAULT 0,
            patient_responsibility DECIMAL(12,2) NOT NULL DEFAULT 0,
            deposit_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            remaining_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
            card_fee_percent DECIMAL(5,2) NOT NULL DEFAULT 3.00,
            cancellation_fee_max DECIMAL(12,2) NOT NULL DEFAULT 1500.00,
            cancellation_text TEXT NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'draft',
            current_version_id INT UNSIGNED NULL,
            delivery_token_hash CHAR(64) NULL,
            expires_at DATETIME NULL,
            sent_at DATETIME NULL,
            viewed_at DATETIME NULL,
            signed_at DATETIME NULL,
            voided_at DATETIME NULL,
            created_by_user_id INT UNSIGNED NULL,
            updated_by_user_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_patient_exp_contract_number (contract_number),
            UNIQUE KEY uniq_patient_exp_contract_token (delivery_token_hash),
            KEY idx_patient_exp_contract_lead (lead_id),
            KEY idx_patient_exp_contract_status (status),
            KEY idx_patient_exp_contract_created (created_at)
        ) {$charset}");

        db_query("CREATE TABLE IF NOT EXISTS patient_experience_contract_versions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            contract_id INT UNSIGNED NOT NULL,
            version_number INT UNSIGNED NOT NULL,
            snapshot_json LONGTEXT NOT NULL,
            snapshot_hash CHAR(64) NOT NULL,
            created_by_user_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_patient_exp_contract_version (contract_id, version_number),
            KEY idx_patient_exp_contract_version_hash (snapshot_hash)
        ) {$charset}");

        db_query("CREATE TABLE IF NOT EXISTS patient_experience_contract_signatures (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            contract_id INT UNSIGNED NOT NULL,
            contract_version_id INT UNSIGNED NOT NULL,
            signer_name VARCHAR(190) NOT NULL,
            signer_relationship VARCHAR(120) NOT NULL DEFAULT 'self',
            signature_data MEDIUMTEXT NOT NULL,
            signature_hash CHAR(64) NOT NULL,
            acknowledged TINYINT(1) NOT NULL DEFAULT 1,
            signed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(80) NOT NULL DEFAULT '',
            user_agent VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_patient_exp_contract_signature (contract_id, contract_version_id),
            KEY idx_patient_exp_contract_signature_signed (signed_at)
        ) {$charset}");

        db_query("CREATE TABLE IF NOT EXISTS patient_experience_contract_deliveries (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            contract_id INT UNSIGNED NOT NULL,
            contract_version_id INT UNSIGNED NOT NULL,
            channel VARCHAR(20) NOT NULL,
            recipient VARCHAR(255) NOT NULL DEFAULT '',
            status VARCHAR(40) NOT NULL DEFAULT 'created',
            provider_reference VARCHAR(190) NOT NULL DEFAULT '',
            error_message TEXT NULL,
            created_by_user_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_patient_exp_contract_delivery_contract (contract_id),
            KEY idx_patient_exp_contract_delivery_status (status)
        ) {$charset}");
    }
}

if (!function_exists('patient_experience_contract_money')) {
    function patient_experience_contract_money(mixed $value): float
    {
        if (is_string($value)) $value = preg_replace('/[^0-9.\-]/', '', $value);
        return round(max(0, (float)$value), 2);
    }
}

if (!function_exists('patient_experience_contract_normalize_teeth')) {
    function patient_experience_contract_normalize_teeth(mixed $value): array
    {
        $values = is_array($value) ? $value : explode(',', (string)$value);
        $teeth = [];
        foreach ($values as $item) {
            $tooth = (int)$item;
            if ($tooth >= 1 && $tooth <= 32) $teeth[$tooth] = $tooth;
        }
        ksort($teeth);
        return array_values($teeth);
    }
}

if (!function_exists('patient_experience_contract_input')) {
    function patient_experience_contract_input(array $input): array
    {
        $definitions = patient_experience_contract_definitions();
        $treatmentKey = strtolower(trim((string)($input['treatment_key'] ?? 'veneers')));
        if (!isset($definitions[$treatmentKey])) $treatmentKey = 'custom';
        $definition = $definitions[$treatmentKey];

        $selectedKeys = [];
        foreach ((array)($input['line_items'] ?? []) as $item) {
            $key = is_array($item) ? (string)($item['key'] ?? '') : (string)$item;
            if ($key !== '') $selectedKeys[$key] = $key;
        }
        $selectedKeys = array_values($selectedKeys);
        $lineItems = [];
        foreach ($selectedKeys as $key) {
            if (isset($definition['options'][$key])) {
                $lineItems[] = ['key' => $key, 'label' => (string)$definition['options'][$key]];
            }
        }
        $customLines = preg_split('/\r\n|\r|\n/', trim((string)($input['custom_item_text'] ?? ''))) ?: [];
        foreach ($customLines as $index => $line) {
            $line = trim($line);
            if ($line !== '') $lineItems[] = ['key' => 'custom_' . $index, 'label' => mb_substr($line, 0, 240)];
        }

        $originalPrice = patient_experience_contract_money($input['original_price'] ?? 0);
        $discountAmount = patient_experience_contract_money($input['discount_amount'] ?? 0);
        $finalPrice = patient_experience_contract_money($input['final_price'] ?? 0);
        $insurance = patient_experience_contract_money($input['insurance_estimate'] ?? 0);
        $responsibility = max(0, round($finalPrice - $insurance, 2));
        $deposit = patient_experience_contract_money($input['deposit_amount'] ?? 0);

        return [
            'lead_id' => max(0, (int)($input['lead_id'] ?? 0)),
            'patient_name' => mb_substr(trim((string)($input['patient_name'] ?? '')), 0, 190),
            'patient_phone' => mb_substr(trim((string)($input['patient_phone'] ?? '')), 0, 80),
            'patient_email' => mb_substr(strtolower(trim((string)($input['patient_email'] ?? ''))), 0, 190),
            'treatment_key' => $treatmentKey,
            'treatment_label' => (string)$definition['label'],
            'tooth_mode' => (string)$definition['tooth_mode'],
            'arch_scope' => in_array((string)($input['arch_scope'] ?? ''), ['upper', 'lower', 'both'], true) ? (string)$input['arch_scope'] : '',
            'selected_teeth' => patient_experience_contract_normalize_teeth($input['selected_teeth'] ?? []),
            'line_items' => $lineItems,
            'custom_item_text' => trim((string)($input['custom_item_text'] ?? '')),
            'original_price' => $originalPrice,
            'discount_amount' => $discountAmount,
            'final_price' => $finalPrice,
            'insurance_estimate' => $insurance,
            'patient_responsibility' => $responsibility,
            'deposit_amount' => $deposit,
            'remaining_balance' => max(0, round($responsibility - $deposit, 2)),
            'card_fee_percent' => 3.00,
            'cancellation_fee_max' => 1500.00,
            'cancellation_text' => patient_experience_contract_cancellation_text(),
        ];
    }
}

if (!function_exists('patient_experience_contract_validate')) {
    function patient_experience_contract_validate(array $data): array
    {
        $errors = [];
        if (($data['patient_name'] ?? '') === '') $errors['patient_name'] = 'Select or enter the patient’s legal name.';
        if (($data['patient_email'] ?? '') !== '' && !filter_var((string)$data['patient_email'], FILTER_VALIDATE_EMAIL)) $errors['patient_email'] = 'Enter a valid email address.';
        if (($data['tooth_mode'] ?? '') === 'teeth' && empty($data['selected_teeth'])) $errors['selected_teeth'] = 'Select at least one tooth for this treatment.';
        if (($data['tooth_mode'] ?? '') === 'arch' && ($data['arch_scope'] ?? '') === '') $errors['arch_scope'] = 'Select the upper arch, lower arch, or both arches.';
        if (empty($data['line_items'])) $errors['line_items'] = 'Select at least one included treatment item.';
        if ((float)($data['final_price'] ?? 0) <= 0) $errors['final_price'] = 'Enter the final approved treatment price.';
        if ((float)($data['original_price'] ?? 0) > 0 && (float)$data['final_price'] > (float)$data['original_price']) $errors['final_price'] = 'Final price cannot exceed the original price.';
        if ((float)($data['insurance_estimate'] ?? 0) > (float)($data['final_price'] ?? 0)) $errors['insurance_estimate'] = 'Insurance estimate cannot exceed the final price.';
        if ((float)($data['deposit_amount'] ?? 0) > (float)($data['patient_responsibility'] ?? 0)) $errors['deposit_amount'] = 'Deposit cannot exceed the patient responsibility.';
        return $errors;
    }
}

if (!function_exists('patient_experience_contract_db_params')) {
    function patient_experience_contract_db_params(array $data): array
    {
        $keys = [
            'lead_id', 'patient_name', 'patient_phone', 'patient_email', 'treatment_key', 'treatment_label',
            'arch_scope', 'custom_item_text', 'original_price', 'discount_amount', 'final_price',
            'insurance_estimate', 'patient_responsibility', 'deposit_amount', 'remaining_balance',
            'card_fee_percent', 'cancellation_fee_max', 'cancellation_text',
        ];
        $params = [];
        foreach ($keys as $key) $params[$key] = $data[$key] ?? null;
        $params['selected_teeth_json'] = json_encode((array)($data['selected_teeth'] ?? []));
        $params['line_items_json'] = json_encode((array)($data['line_items'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $params;
    }
}

if (!function_exists('patient_experience_contract_save')) {
    function patient_experience_contract_save(array $input, ?int $userId = null): array
    {
        patient_experience_contract_ensure_schema();
        $data = patient_experience_contract_input($input);
        $errors = patient_experience_contract_validate($data);
        if ($errors) return ['ok' => false, 'errors' => $errors, 'data' => $data];

        $contractId = max(0, (int)($input['contract_id'] ?? 0));
        if ($contractId > 0) {
            $existing = patient_experience_contract_by_id($contractId);
            if (!$existing) return ['ok' => false, 'errors' => ['contract' => 'Contract not found.'], 'data' => $data];
            if (!in_array((string)$existing['status'], ['draft'], true)) return ['ok' => false, 'errors' => ['contract' => 'Sent or signed contracts cannot be edited. Create a new contract instead.'], 'data' => $data];
            db_execute('UPDATE patient_experience_contracts SET
                lead_id=:lead_id, patient_name=:patient_name, patient_phone=:patient_phone, patient_email=:patient_email,
                treatment_key=:treatment_key, treatment_label=:treatment_label, arch_scope=:arch_scope,
                selected_teeth_json=:selected_teeth_json, line_items_json=:line_items_json, custom_item_text=:custom_item_text,
                original_price=:original_price, discount_amount=:discount_amount, final_price=:final_price,
                insurance_estimate=:insurance_estimate, patient_responsibility=:patient_responsibility,
                deposit_amount=:deposit_amount, remaining_balance=:remaining_balance, card_fee_percent=:card_fee_percent,
                cancellation_fee_max=:cancellation_fee_max, cancellation_text=:cancellation_text,
                updated_by_user_id=:user_id, updated_at=NOW() WHERE id=:id LIMIT 1', array_merge(patient_experience_contract_db_params($data), [
                    'user_id' => $userId,
                    'id' => $contractId,
                ]));
        } else {
            db_execute('INSERT INTO patient_experience_contracts
                (contract_number, lead_id, patient_name, patient_phone, patient_email, treatment_key, treatment_label, arch_scope,
                 selected_teeth_json, line_items_json, custom_item_text, original_price, discount_amount, final_price,
                 insurance_estimate, patient_responsibility, deposit_amount, remaining_balance, card_fee_percent,
                 cancellation_fee_max, cancellation_text, status, created_by_user_id, updated_by_user_id, created_at)
                VALUES
                (:contract_number, :lead_id, :patient_name, :patient_phone, :patient_email, :treatment_key, :treatment_label, :arch_scope,
                 :selected_teeth_json, :line_items_json, :custom_item_text, :original_price, :discount_amount, :final_price,
                 :insurance_estimate, :patient_responsibility, :deposit_amount, :remaining_balance, :card_fee_percent,
                 :cancellation_fee_max, :cancellation_text, \'draft\', :created_by_user_id, :updated_by_user_id, NOW())', array_merge(patient_experience_contract_db_params($data), [
                    'contract_number' => 'PENDING-' . bin2hex(random_bytes(5)),
                    'created_by_user_id' => $userId,
                    'updated_by_user_id' => $userId,
                ]));
            $contractId = (int)db()->lastInsertId();
            $number = 'ES-' . date('Ymd') . '-' . str_pad((string)$contractId, 5, '0', STR_PAD_LEFT);
            db_execute('UPDATE patient_experience_contracts SET contract_number=:number WHERE id=:id', ['number' => $number, 'id' => $contractId]);
        }

        if (function_exists('patient_experience_audit')) {
            patient_experience_audit('contract_draft_saved', ['contract_id' => $contractId], null, $data['lead_id'] ?: null, $userId);
        }
        return ['ok' => true, 'contract_id' => $contractId, 'contract' => patient_experience_contract_by_id($contractId)];
    }
}

if (!function_exists('patient_experience_contract_hydrate')) {
    function patient_experience_contract_hydrate(array $row): array
    {
        $row['selected_teeth'] = json_decode((string)($row['selected_teeth_json'] ?? '[]'), true) ?: [];
        $row['line_items'] = json_decode((string)($row['line_items_json'] ?? '[]'), true) ?: [];
        return $row;
    }
}

if (!function_exists('patient_experience_contract_by_id')) {
    function patient_experience_contract_by_id(int $contractId): ?array
    {
        patient_experience_contract_ensure_schema();
        $row = db_one('SELECT * FROM patient_experience_contracts WHERE id=:id LIMIT 1', ['id' => $contractId]);
        return $row ? patient_experience_contract_hydrate($row) : null;
    }
}

if (!function_exists('patient_experience_contract_list')) {
    function patient_experience_contract_list(int $limit = 100): array
    {
        patient_experience_contract_ensure_schema();
        $limit = max(1, min(300, $limit));
        return array_map('patient_experience_contract_hydrate', db_all("SELECT * FROM patient_experience_contracts ORDER BY created_at DESC, id DESC LIMIT {$limit}"));
    }
}

if (!function_exists('patient_experience_contract_patient_options')) {
    function patient_experience_contract_patient_options(int $limit = 250): array
    {
        $limit = max(1, min(500, $limit));
        try {
            return db_all("SELECT id, full_name, phone, email FROM leads WHERE COALESCE(full_name, '') <> '' ORDER BY updated_at DESC, id DESC LIMIT {$limit}");
        } catch (Throwable) {
            return db_all("SELECT id, full_name, phone, email FROM leads WHERE COALESCE(full_name, '') <> '' ORDER BY id DESC LIMIT {$limit}");
        }
    }
}

if (!function_exists('patient_experience_contract_snapshot')) {
    function patient_experience_contract_snapshot(array $contract): array
    {
        return [
            'schema_version' => 2,
            'terms_version' => 2,
            'practice' => [
                'name' => 'Elite Smiles',
                'provider' => 'Walter Meden, D.D.S.',
                'address' => '11762 South State, Suite 300, Draper, UT 84020',
            ],
            'contract' => [
                'id' => (int)$contract['id'],
                'number' => (string)$contract['contract_number'],
                'date' => date('F j, Y'),
                'patient_name' => (string)$contract['patient_name'],
                'patient_phone' => (string)$contract['patient_phone'],
                'patient_email' => (string)$contract['patient_email'],
                'treatment_key' => (string)$contract['treatment_key'],
                'treatment_label' => (string)$contract['treatment_label'],
                'arch_scope' => (string)$contract['arch_scope'],
                'selected_teeth' => (array)$contract['selected_teeth'],
                'line_items' => (array)$contract['line_items'],
            ],
            'financials' => [
                'original_price' => (float)$contract['original_price'],
                'discount_amount' => (float)$contract['discount_amount'],
                'final_price' => (float)$contract['final_price'],
                'insurance_estimate' => (float)$contract['insurance_estimate'],
                'patient_responsibility' => (float)$contract['patient_responsibility'],
                'deposit_amount' => (float)$contract['deposit_amount'],
                'remaining_balance' => (float)$contract['remaining_balance'],
                'card_fee_percent' => (float)$contract['card_fee_percent'],
            ],
            'terms' => array_merge(patient_experience_contract_original_terms(), [
                'cancellation_fee_max' => (float)$contract['cancellation_fee_max'],
                'cancellation_text' => (string)$contract['cancellation_text'],
            ]),
            'created_at' => date('c'),
        ];
    }
}

if (!function_exists('patient_experience_contract_prepare_delivery')) {
    function patient_experience_contract_prepare_delivery(int $contractId, array $channels, ?int $userId = null): array
    {
        $contract = patient_experience_contract_by_id($contractId);
        if (!$contract) return ['ok' => false, 'message' => 'Contract not found.'];
        if ((string)$contract['status'] === 'signed') return ['ok' => false, 'message' => 'Signed contracts cannot be resent or changed.'];

        $definitions = patient_experience_contract_definitions();
        $data = $contract;
        $data['tooth_mode'] = (string)($definitions[(string)$contract['treatment_key']]['tooth_mode'] ?? 'teeth_optional');
        $errors = patient_experience_contract_validate($data);
        if ($errors) return ['ok' => false, 'message' => reset($errors), 'errors' => $errors];

        $snapshot = patient_experience_contract_snapshot($contract);
        $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($snapshotJson)) return ['ok' => false, 'message' => 'Could not create the immutable contract snapshot.'];
        $version = (int)db_value('SELECT COALESCE(MAX(version_number),0)+1 FROM patient_experience_contract_versions WHERE contract_id=:id', ['id' => $contractId]);
        $hash = hash('sha256', $snapshotJson);
        db_execute('INSERT INTO patient_experience_contract_versions (contract_id, version_number, snapshot_json, snapshot_hash, created_by_user_id, created_at) VALUES (:contract_id,:version,:snapshot,:hash,:user_id,NOW())', [
            'contract_id' => $contractId, 'version' => $version, 'snapshot' => $snapshotJson, 'hash' => $hash, 'user_id' => $userId,
        ]);
        $versionId = (int)db()->lastInsertId();
        $token = bin2hex(random_bytes(32));
        db_execute("UPDATE patient_experience_contracts SET current_version_id=:version_id, delivery_token_hash=:token_hash, status='sent', sent_at=NOW(), expires_at=DATE_ADD(NOW(), INTERVAL 30 DAY), updated_by_user_id=:user_id WHERE id=:id", [
            'version_id' => $versionId, 'token_hash' => hash('sha256', $token), 'user_id' => $userId, 'id' => $contractId,
        ]);
        $url = base_url('patient-experience/contract/?t=' . rawurlencode($token));
        $sent = [];
        $issues = [];
        foreach (array_unique($channels) as $channel) {
            if ($channel === 'sms' && trim((string)$contract['patient_phone']) !== '') {
                $body = 'Elite Smiles treatment agreement for ' . $contract['patient_name'] . ': ' . $url;
                $result = elite_twilio_send_sms((string)$contract['patient_phone'], $body, ['source' => 'patient_experience_contract']);
                $ok = !empty($result['ok']);
                db_execute('INSERT INTO patient_experience_contract_deliveries (contract_id,contract_version_id,channel,recipient,status,provider_reference,error_message,created_by_user_id,created_at) VALUES (:contract_id,:version_id,\'sms\',:recipient,:status,:reference,:error,:user_id,NOW())', [
                    'contract_id' => $contractId, 'version_id' => $versionId, 'recipient' => (string)$contract['patient_phone'], 'status' => $ok ? 'sent' : 'failed', 'reference' => (string)($result['sid'] ?? ''), 'error' => $ok ? null : (string)($result['message'] ?? 'SMS failed'), 'user_id' => $userId,
                ]);
                $ok ? $sent[] = 'text' : $issues[] = (string)($result['message'] ?? 'SMS failed');
            }
            if ($channel === 'email' && filter_var((string)$contract['patient_email'], FILTER_VALIDATE_EMAIL)) {
                $subject = 'Your Elite Smiles Treatment Agreement';
                $plain = "Hi {$contract['patient_name']},\n\nPlease review and sign your secure Elite Smiles treatment agreement:\n{$url}\n\nElite Smiles";
                $html = '<div style="font-family:Arial,sans-serif;color:#0f172a;max-width:620px;margin:auto"><div style="text-align:center;padding:24px"><img src="' . e(base_url('assets/img/ES-Logo-Stack-500-x-150-px.png')) . '" width="210" alt="Elite Smiles"></div><div style="border:1px solid #e2e8f0;border-radius:18px;padding:28px"><h1 style="font-size:22px;margin:0 0 16px">Your treatment agreement is ready</h1><p>Hi ' . e((string)$contract['patient_name']) . ',</p><p>Please review and sign your secure Elite Smiles treatment agreement.</p><p style="margin:24px 0"><a href="' . e($url) . '" style="background:#0f172a;color:#fff;padding:13px 20px;border-radius:10px;text-decoration:none;font-weight:700">Review and sign</a></p><p style="color:#64748b;font-size:13px">Elite Smiles by Dr. Walter Meden<br>11762 South State, Suite 300, Draper, UT 84020</p></div></div>';
                $ok = elite_send_mail_multipart((string)$contract['patient_email'], $subject, $plain, $html);
                db_execute('INSERT INTO patient_experience_contract_deliveries (contract_id,contract_version_id,channel,recipient,status,error_message,created_by_user_id,created_at) VALUES (:contract_id,:version_id,\'email\',:recipient,:status,:error,:user_id,NOW())', [
                    'contract_id' => $contractId, 'version_id' => $versionId, 'recipient' => (string)$contract['patient_email'], 'status' => $ok ? 'sent' : 'failed', 'error' => $ok ? null : 'Email failed', 'user_id' => $userId,
                ]);
                $ok ? $sent[] = 'email' : $issues[] = 'Email failed.';
            }
        }
        if (function_exists('patient_experience_audit')) patient_experience_audit('contract_sent', ['contract_id' => $contractId, 'version_id' => $versionId, 'channels' => $sent, 'issues' => $issues], null, (int)$contract['lead_id'] ?: null, $userId);
        return ['ok' => true, 'url' => $url, 'sent' => $sent, 'issues' => $issues, 'version_id' => $versionId];
    }
}

if (!function_exists('patient_experience_contract_from_token')) {
    function patient_experience_contract_from_token(string $token, bool $markViewed = true): ?array
    {
        patient_experience_contract_ensure_schema();
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
        $row = db_one("SELECT c.*, v.snapshot_json, v.snapshot_hash, v.version_number, v.id AS version_id
            FROM patient_experience_contracts c INNER JOIN patient_experience_contract_versions v ON v.id=c.current_version_id
            WHERE c.delivery_token_hash=:token_hash AND c.voided_at IS NULL AND (c.expires_at IS NULL OR c.expires_at>NOW()) LIMIT 1", ['token_hash' => hash('sha256', $token)]);
        if (!$row) return null;
        if ($markViewed && empty($row['viewed_at'])) db_execute("UPDATE patient_experience_contracts SET viewed_at=NOW(), status=IF(status='sent','viewed',status) WHERE id=:id", ['id' => (int)$row['id']]);
        $row = patient_experience_contract_hydrate($row);
        $row['snapshot'] = json_decode((string)$row['snapshot_json'], true) ?: [];
        $row['signature'] = db_one('SELECT * FROM patient_experience_contract_signatures WHERE contract_id=:contract_id AND contract_version_id=:version_id LIMIT 1', ['contract_id' => (int)$row['id'], 'version_id' => (int)$row['version_id']]);
        return $row;
    }
}

if (!function_exists('patient_experience_contract_sign')) {
    function patient_experience_contract_sign(string $token, array $input): array
    {
        $contract = patient_experience_contract_from_token($token, true);
        if (!$contract) return ['ok' => false, 'message' => 'This secure contract link is invalid or expired.'];
        if (!empty($contract['signature']) || (string)$contract['status'] === 'signed') return ['ok' => false, 'message' => 'This contract has already been signed.'];
        $signer = mb_substr(trim((string)($input['signer_name'] ?? '')), 0, 190);
        $relationship = mb_substr(trim((string)($input['signer_relationship'] ?? 'self')), 0, 120);
        $signature = trim((string)($input['signature_data'] ?? ''));
        if ($signer === '') return ['ok' => false, 'message' => 'Enter the signer’s full legal name.'];
        if (empty($input['cancellation_acknowledged'])) return ['ok' => false, 'message' => 'Acknowledge the treatment-plan cancellation policy before signing.'];
        if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $signature, $match)) return ['ok' => false, 'message' => 'Please draw your signature in the signature box.'];
        $binary = base64_decode($match[1], true);
        if (!is_string($binary) || strlen($binary) < 100 || strlen($binary) > 500000) return ['ok' => false, 'message' => 'The signature image is invalid. Please clear it and sign again.'];

        try {
            db_begin();
            db_execute('INSERT INTO patient_experience_contract_signatures (contract_id,contract_version_id,signer_name,signer_relationship,signature_data,signature_hash,acknowledged,signed_at,ip_address,user_agent,created_at) VALUES (:contract_id,:version_id,:signer,:relationship,:signature,:hash,1,NOW(),:ip,:user_agent,NOW())', [
                'contract_id' => (int)$contract['id'], 'version_id' => (int)$contract['version_id'], 'signer' => $signer, 'relationship' => $relationship, 'signature' => $signature, 'hash' => hash('sha256', $binary), 'ip' => function_exists('client_ip') ? client_ip() : substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 80), 'user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);
            db_execute("UPDATE patient_experience_contracts SET status='signed', signed_at=NOW() WHERE id=:id AND signed_at IS NULL", ['id' => (int)$contract['id']]);
            db_commit();
        } catch (Throwable $e) {
            db_rollback();
            return ['ok' => false, 'message' => 'The signature could not be saved. Please refresh and try again.'];
        }
        if (function_exists('patient_experience_audit')) patient_experience_audit('contract_signed', ['contract_id' => (int)$contract['id'], 'version_id' => (int)$contract['version_id'], 'snapshot_hash' => (string)$contract['snapshot_hash']], null, (int)$contract['lead_id'] ?: null);
        return ['ok' => true, 'message' => 'Your treatment agreement has been signed successfully.'];
    }
}
