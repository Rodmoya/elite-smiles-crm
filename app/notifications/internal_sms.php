<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/settings/crm_settings.php';

if (!function_exists('internal_sms_ensure_schema')) {
    function internal_sms_ensure_schema(): void
    {
        db_query("CREATE TABLE IF NOT EXISTS internal_sms_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            recipient_key VARCHAR(80) NULL,
            recipient_name VARCHAR(160) NOT NULL,
            to_number VARCHAR(32) NOT NULL,
            body TEXT NOT NULL,
            twilio_sid VARCHAR(80) NULL,
            twilio_status VARCHAR(40) NULL,
            status_code INT NULL,
            error_message TEXT NULL,
            sent_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_internal_sms_recipient (recipient_key),
            INDEX idx_internal_sms_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}
if (!function_exists('internal_sms_default_recipients')) {
    function internal_sms_default_recipients(): array
    {
        return [
            ['key' => 'dr_meden', 'name' => 'Dr. Walter Meden', 'phone' => '8016887200', 'enabled' => true],
            ['key' => 'rod_moya', 'name' => 'Rod Moya', 'phone' => '8016037011', 'enabled' => true],
        ];
    }
}

if (!function_exists('internal_sms_normalize_phone')) {
    function internal_sms_normalize_phone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        if (strlen($digits) === 10) {
            return '+1' . $digits;
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return '+' . $digits;
        }
        return '';
    }
}

if (!function_exists('internal_sms_sanitize_recipients')) {
    function internal_sms_sanitize_recipients(array $rows): array
    {
        $recipients = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = trim((string)($row['name'] ?? ''));
            $phone = internal_sms_normalize_phone((string)($row['phone'] ?? ''));
            if ($name === '' || $phone === '') {
                continue;
            }
            $key = strtolower(trim((string)($row['key'] ?? '')));
            if ($key === '') {
                $key = preg_replace('/[^a-z0-9]+/', '_', strtolower($name)) ?: 'recipient';
            }
            $recipients[] = [
                'key' => substr($key, 0, 80),
                'name' => substr($name, 0, 160),
                'phone' => $phone,
                'enabled' => !array_key_exists('enabled', $row) || filter_var($row['enabled'], FILTER_VALIDATE_BOOLEAN),
            ];
        }
        return $recipients;
    }
}

if (!function_exists('internal_sms_recipients')) {
    function internal_sms_upgrade_legacy_recipients(array $rows): array
    {
        $changed = false;
        foreach ($rows as &$row) {
            if (
                (string) ($row['key'] ?? '') === 'rod_moya'
                && internal_sms_normalize_phone((string) ($row['phone'] ?? '')) === '+18014994831'
            ) {
                $row['phone'] = '+18016037011';
                $changed = true;
            }
        }
        unset($row);
        return ['recipients' => $rows, 'changed' => $changed];
    }

    function internal_sms_recipients(): array
    {
        $saved = crm_settings_get_json('internal_sms_recipients', null);
        $rows = is_array($saved) ? internal_sms_sanitize_recipients($saved) : [];
        if ($rows === []) {
            return internal_sms_default_recipients();
        }
        $upgrade = internal_sms_upgrade_legacy_recipients($rows);
        $rows = (array) ($upgrade['recipients'] ?? []);
        if (!empty($upgrade['changed'])) {
            crm_settings_set_json('internal_sms_recipients', $rows, 0);
        }
        return $rows;
    }
}

if (!function_exists('internal_sms_save_recipients')) {
    function internal_sms_save_recipients(array $rows, int $updatedBy = 0): array
    {
        $recipients = internal_sms_sanitize_recipients($rows);
        crm_settings_set_json('internal_sms_recipients', $recipients, $updatedBy);
        return $recipients;
    }
}

if (!function_exists('internal_sms_find_recipient')) {
    function internal_sms_find_recipient(string $key): ?array
    {
        $key = trim($key);
        foreach (internal_sms_recipients() as $recipient) {
            if ((string)$recipient['key'] === $key) {
                return $recipient;
            }
        }
        return null;
    }
}

if (!function_exists('internal_sms_twilio_ready')) {
    function internal_sms_twilio_ready(): bool
    {
        return defined('TWILIO_ACCOUNT_SID') && TWILIO_ACCOUNT_SID !== ''
            && defined('TWILIO_AUTH_TOKEN') && TWILIO_AUTH_TOKEN !== ''
            && ((defined('TWILIO_MESSAGING_SERVICE_SID') && TWILIO_MESSAGING_SERVICE_SID !== '')
                || (defined('TWILIO_FROM_NUMBER') && TWILIO_FROM_NUMBER !== ''));
    }
}

if (!function_exists('internal_sms_send')) {
    function internal_sms_send(array $recipient, string $body, int $sentBy = 0): array
    {
        internal_sms_ensure_schema();
        $name = trim((string)($recipient['name'] ?? ''));
        $key = trim((string)($recipient['key'] ?? ''));
        $to = internal_sms_normalize_phone((string)($recipient['phone'] ?? ''));
        $body = trim($body);

        if ($name === '' || $to === '') {
            return ['ok' => false, 'message' => 'Internal SMS recipient is invalid.'];
        }
        if ($body === '') {
            return ['ok' => false, 'message' => 'Internal SMS body is required.'];
        }
        if (!internal_sms_twilio_ready()) {
            return ['ok' => false, 'message' => 'Twilio is not configured.'];
        }

        $payload = [
            'To' => $to,
            'Body' => $body,
            // Internal alerts need the same delivery evidence as patient SMS.
            // The callback updates internal_sms_logs and can trigger the quiet
            // Pushover fallback when Twilio later reports a delivery failure.
            'StatusCallback' => rtrim(APP_URL, '/') . '/app/api/twilio_sms_status.php',
        ];
        if (defined('TWILIO_MESSAGING_SERVICE_SID') && TWILIO_MESSAGING_SERVICE_SID !== '') {
            $payload['MessagingServiceSid'] = TWILIO_MESSAGING_SERVICE_SID;
        } else {
            $payload['From'] = TWILIO_FROM_NUMBER;
        }

        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode(TWILIO_ACCOUNT_SID) . '/Messages.json';
        $rawResponse = false;
        $statusCode = 0;
        $curlError = '';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return ['ok' => false, 'message' => 'Could not initialize Twilio request.'];
            }
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_USERPWD => TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            ]);
            $rawResponse = curl_exec($ch);
            $curlError = curl_error($ch);
            $statusCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
        }

        $decoded = [];
        if (is_string($rawResponse) && $rawResponse !== '') {
            $json = json_decode($rawResponse, true);
            $decoded = is_array($json) ? $json : [];
        }

        $ok = $rawResponse !== false && $statusCode >= 200 && $statusCode < 300;
        $message = $ok ? 'Internal SMS sent.' : (string)($decoded['message'] ?? ($curlError !== '' ? $curlError : 'Twilio rejected the SMS request.'));

        db_query(
            'INSERT INTO internal_sms_logs (recipient_key, recipient_name, to_number, body, twilio_sid, twilio_status, status_code, error_message, sent_by, created_at)
             VALUES (:recipient_key, :recipient_name, :to_number, :body, :twilio_sid, :twilio_status, :status_code, :error_message, :sent_by, NOW())',
            [
                'recipient_key' => $key !== '' ? $key : null,
                'recipient_name' => $name,
                'to_number' => $to,
                'body' => $body,
                'twilio_sid' => (string)($decoded['sid'] ?? ''),
                'twilio_status' => (string)($decoded['status'] ?? ''),
                'status_code' => $statusCode > 0 ? $statusCode : null,
                'error_message' => $ok ? null : $message,
                'sent_by' => $sentBy > 0 ? $sentBy : null,
            ]
        );

        return [
            'ok' => $ok,
            'message' => $message,
            'to' => $to,
            'twilio_sid' => (string)($decoded['sid'] ?? ''),
            'twilio_status' => (string)($decoded['status'] ?? ''),
            'status_code' => $statusCode,
        ];
    }
}
