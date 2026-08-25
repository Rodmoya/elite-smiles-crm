<?php
declare(strict_types=1);

if (!function_exists('lead_agent_instruction_ensure_schema')) {
    function lead_agent_instruction_ensure_schema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        db_query("CREATE TABLE IF NOT EXISTS lead_agent_instructions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            scope VARCHAR(30) NOT NULL DEFAULT 'global',
            scope_value VARCHAR(120) NOT NULL DEFAULT '',
            instruction TEXT NOT NULL,
            priority VARCHAR(20) NOT NULL DEFAULT 'normal',
            channels VARCHAR(30) NOT NULL DEFAULT 'both',
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            starts_at DATETIME NULL,
            expires_at DATETIME NULL,
            created_by_user_id INT UNSIGNED NULL,
            created_by_label VARCHAR(120) NOT NULL DEFAULT 'Elite AI',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_lead_agent_instruction_active (status, starts_at, expires_at),
            KEY idx_lead_agent_instruction_scope (scope, scope_value)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

if (!function_exists('lead_agent_instruction_normalize')) {
    function lead_agent_instruction_normalize(array $input): array
    {
        $scope = strtolower(trim((string) ($input['scope'] ?? 'global')));
        $scope = in_array($scope, ['global', 'lead', 'stage', 'campaign'], true) ? $scope : 'global';
        $scopeValue = trim((string) ($input['scope_value'] ?? ''));
        if ($scope === 'lead') {
            $scopeValue = (string) max(0, (int) ($input['lead_id'] ?? $scopeValue));
        }
        if ($scope === 'global') {
            $scopeValue = '';
        }
        $priority = strtolower(trim((string) ($input['priority'] ?? 'normal')));
        $priority = in_array($priority, ['normal', 'important', 'mandatory'], true) ? $priority : 'normal';
        $channels = strtolower(trim((string) ($input['channels'] ?? $input['channel'] ?? 'both')));
        $channels = in_array($channels, ['sms', 'email', 'both'], true) ? $channels : 'both';
        $instruction = trim((string) ($input['instruction'] ?? $input['body'] ?? $input['prompt'] ?? ''));
        $instruction = mb_substr(preg_replace('/\s+/u', ' ', $instruction) ?? '', 0, 1200);

        return [
            'scope' => $scope,
            'scope_value' => mb_substr($scopeValue, 0, 120),
            'instruction' => $instruction,
            'priority' => $priority,
            'channels' => $channels,
            'starts_at' => lead_agent_instruction_datetime((string) ($input['starts_at'] ?? '')),
            'expires_at' => lead_agent_instruction_datetime((string) ($input['expires_at'] ?? '')),
        ];
    }
}

if (!function_exists('lead_agent_instruction_datetime')) {
    function lead_agent_instruction_datetime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        try {
            return (new DateTimeImmutable($value, new DateTimeZone(APP_TIMEZONE)))->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('lead_agent_instruction_create')) {
    function lead_agent_instruction_create(array $input, array $user = []): array
    {
        lead_agent_instruction_ensure_schema();
        $item = lead_agent_instruction_normalize($input);
        if ($item['instruction'] === '') {
            return ['ok' => false, 'message' => 'Instruction text is required.'];
        }
        if ($item['scope'] !== 'global' && $item['scope_value'] === '') {
            return ['ok' => false, 'message' => 'A scope value is required for this instruction.'];
        }
        $label = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
        $id = db_insert("INSERT INTO lead_agent_instructions
            (scope, scope_value, instruction, priority, channels, status, starts_at, expires_at, created_by_user_id, created_by_label, created_at, updated_at)
            VALUES (:scope, :scope_value, :instruction, :priority, :channels, 'active', :starts_at, :expires_at, :user_id, :label, NOW(), NOW())", [
            'scope' => $item['scope'], 'scope_value' => $item['scope_value'], 'instruction' => $item['instruction'],
            'priority' => $item['priority'], 'channels' => $item['channels'], 'starts_at' => $item['starts_at'],
            'expires_at' => $item['expires_at'], 'user_id' => (int) ($user['id'] ?? 0) ?: null,
            'label' => mb_substr($label !== '' ? $label : 'Elite AI', 0, 120),
        ]);
        return ['ok' => $id > 0, 'instruction_id' => $id, 'instruction' => $item, 'message' => 'Lead Agent instruction activated.'];
    }
}

if (!function_exists('lead_agent_instruction_set_status')) {
    function lead_agent_instruction_set_status(int $id, string $status): array
    {
        lead_agent_instruction_ensure_schema();
        $status = strtolower(trim($status));
        if ($id <= 0 || !in_array($status, ['active', 'paused', 'archived'], true)) {
            return ['ok' => false, 'message' => 'A valid instruction and status are required.'];
        }
        $changed = db_execute('UPDATE lead_agent_instructions SET status = :status, updated_at = NOW() WHERE id = :id', ['status' => $status, 'id' => $id]);
        return ['ok' => $changed > 0, 'instruction_id' => $id, 'status' => $status, 'message' => $changed > 0 ? 'Instruction status updated.' : 'Instruction not found.'];
    }
}

if (!function_exists('lead_agent_instructions_for_lead')) {
    function lead_agent_instructions_for_lead(array $lead, string $channel = 'both', int $limit = 12): array
    {
        lead_agent_instruction_ensure_schema();
        $leadId = (int) ($lead['id'] ?? 0);
        $stage = trim((string) ($lead['status'] ?? ''));
        $campaign = trim((string) ($lead['campaign'] ?? $lead['lead_source'] ?? ''));
        $rows = db_all("SELECT id, scope, scope_value, instruction, priority, channels, starts_at, expires_at
            FROM lead_agent_instructions
            WHERE status = 'active'
              AND (starts_at IS NULL OR starts_at <= NOW())
              AND (expires_at IS NULL OR expires_at > NOW())
              AND (channels = 'both' OR channels = :channel)
              AND (scope = 'global'
                OR (scope = 'lead' AND scope_value = :lead_id)
                OR (scope = 'stage' AND scope_value = :stage)
                OR (scope = 'campaign' AND scope_value = :campaign))
            ORDER BY FIELD(scope, 'lead', 'stage', 'campaign', 'global'), FIELD(priority, 'mandatory', 'important', 'normal'), id DESC
            LIMIT " . max(1, min(30, $limit)), [
            'channel' => $channel, 'lead_id' => (string) $leadId, 'stage' => $stage, 'campaign' => $campaign,
        ]);
        return array_values($rows);
    }
}

if (!function_exists('lead_agent_instruction_guidance')) {
    function lead_agent_instruction_guidance(array $lead, string $channel = 'both'): string
    {
        $rows = lead_agent_instructions_for_lead($lead, $channel);
        if ($rows === []) {
            return '';
        }
        $lines = array_map(static fn(array $row): string => '- [' . strtoupper((string) $row['priority']) . '] ' . trim((string) $row['instruction']), $rows);
        return "Operator-approved Lead Agent instructions. Follow them only when consistent with consent, opt-out, clinical, pricing, scheduling, contact-hour, and message policy safeguards; safeguards always win:\n" . implode("\n", $lines);
    }
}

if (!function_exists('lead_agent_instruction_preview')) {
    function lead_agent_instruction_preview(array $input): array
    {
        $item = lead_agent_instruction_normalize($input);
        $warnings = [];
        if (preg_match('/\b(ignore|bypass|override|disable)\b.{0,40}\b(policy|consent|opt.?out|safety|guardrail)\b/i', $item['instruction'])) {
            $warnings[] = 'This instruction cannot override Lead Agent safety policy.';
        }
        return ['ok' => $item['instruction'] !== '', 'instruction' => $item, 'warnings' => $warnings, 'requires_confirmation' => $item['scope'] !== 'lead'];
    }
}
