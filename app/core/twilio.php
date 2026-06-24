<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: app/core/twilio.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

if (!function_exists('elite_twilio_normalize_us_number')) {
    function elite_twilio_normalize_us_number(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) === 10) {
            return '+1' . $digits;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return '+' . $digits;
        }

        if (str_starts_with(trim($phone), '+') && strlen($digits) >= 10) {
            return '+' . $digits;
        }

        return '';
    }
}

if (!function_exists('elite_twilio_is_configured')) {
    function elite_twilio_is_configured(): bool
    {
        $hasSender = TWILIO_FROM_NUMBER !== '' || TWILIO_MESSAGING_SERVICE_SID !== '';

        return TWILIO_ACCOUNT_SID !== '' && TWILIO_AUTH_TOKEN !== '' && $hasSender;
    }
}

if (!function_exists('elite_twilio_request_url')) {
    function elite_twilio_request_url(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? parse_url(APP_URL, PHP_URL_HOST) ?? '');
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');

        if ($host === '' || $uri === '') {
            return '';
        }

        return $scheme . '://' . $host . $uri;
    }
}

if (!function_exists('elite_twilio_validate_request')) {
    function elite_twilio_validate_request(array $params, ?string $url = null): bool
    {
        $signature = (string)($_SERVER['HTTP_X_TWILIO_SIGNATURE'] ?? '');
        if ($signature === '' || TWILIO_AUTH_TOKEN === '') {
            return false;
        }

        $url = $url !== null && $url !== '' ? $url : elite_twilio_request_url();
        if ($url === '') {
            return false;
        }

        ksort($params);
        $base = $url;
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                continue;
            }
            $base .= (string)$key . (string)$value;
        }

        $expected = base64_encode(hash_hmac('sha1', $base, TWILIO_AUTH_TOKEN, true));
        if (hash_equals($expected, $signature)) {
            return true;
        }

        if (str_starts_with($url, 'http://')) {
            $secureUrl = 'https://' . substr($url, 7);
            $base = $secureUrl;
            foreach ($params as $key => $value) {
                if (is_array($value)) {
                    continue;
                }
                $base .= (string)$key . (string)$value;
            }
            $expectedSecure = base64_encode(hash_hmac('sha1', $base, TWILIO_AUTH_TOKEN, true));
            return hash_equals($expectedSecure, $signature);
        }

        return false;
    }
}

if (!function_exists('elite_twilio_send_sms')) {
    function elite_twilio_opt_out_notice(): string
    {
        return 'Reply STOP to opt out.';
    }
}

if (!function_exists('elite_twilio_message_has_opt_out_language')) {
    function elite_twilio_message_has_opt_out_language(string $body): bool
    {
        return (bool) preg_match('/\b(reply\s+stop\s+to\s+opt\s+out|stop\s+to\s+opt\s+out|reply\s+stop|opt\s+out)\b/i', $body);
    }
}

if (!function_exists('elite_twilio_strip_phone_numbers_from_sms_body')) {
    function elite_twilio_strip_phone_numbers_from_sms_body(string $body): string
    {
        $body = trim($body);
        if ($body === '') {
            return '';
        }

        $phonePattern = '/(?<![A-Za-z0-9])(?:\+?1[\s.\-()]*)?(?:\(?\d{3}\)?[\s.\-]*)\d{3}[\s.\-]*\d{4}(?![A-Za-z0-9])/';
        $body = (string) preg_replace($phonePattern, '', $body);
        $body = (string) preg_replace('/\s+([,.;:!?])/', '$1', $body);
        $body = (string) preg_replace('/(?:\s*,\s*){2,}/', ', ', $body);
        $body = (string) preg_replace('/\s{2,}/', ' ', $body);
        $body = (string) preg_replace('/\b(?:at|call|text|phone)\s*([.?!])/', '$1', $body);
        $body = (string) preg_replace('/\s+([.?!])/', '$1', $body);

        return trim($body);
    }
}

if (!function_exists('elite_twilio_outbound_sms_count')) {
    function elite_twilio_outbound_sms_count(int $leadId): int
    {
        if ($leadId <= 0) {
            return 0;
        }

        try {
            $row = db_one(
                "SELECT COUNT(*)
                 FROM lead_messages
                 WHERE lead_id = :lead_id
                   AND direction = 'outbound'
                   AND channel = 'sms'",
                ['lead_id' => $leadId]
            );
            return (int) array_values($row ?: [0])[0];
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('elite_twilio_should_append_opt_out_notice')) {
    function elite_twilio_should_append_opt_out_notice(array $context = []): bool
    {
        if (array_key_exists('append_opt_out_notice', $context)) {
            return (bool) $context['append_opt_out_notice'];
        }

        $leadId = (int) ($context['lead_id'] ?? 0);
        if ($leadId > 0) {
            return elite_twilio_outbound_sms_count($leadId) === 0;
        }

        return false;
    }
}

if (!function_exists('elite_twilio_prepare_sms_body')) {
    function elite_twilio_prepare_sms_body(string $body, array $context = []): string
    {
        $body = trim($body);
        if ($body === '') {
            return '';
        }

        $body = elite_twilio_strip_phone_numbers_from_sms_body($body);
        if ($body === '') {
            return '';
        }

        if (!elite_twilio_should_append_opt_out_notice($context) || elite_twilio_message_has_opt_out_language($body)) {
            return $body;
        }

        return rtrim($body, " \t\n\r\0\x0B.") . '. ' . elite_twilio_opt_out_notice();
    }
}

if (!function_exists('elite_twilio_send_failure_fallback')) {
    function elite_twilio_send_failure_fallback(array $context, array $failure): bool
    {
        $lead = $context['lead'] ?? null;
        if (!is_array($lead) || !function_exists('elite_send_operator_follow_up_pushover')) {
            return false;
        }

        $summary = trim((string) ($context['fallback_summary'] ?? 'Twilio SMS failed before the message could be queued. Open lead actions to retry manually.'));
        $note = trim((string) ($failure['body'] ?? ''));
        if ($note === '') {
            $note = trim((string) ($context['original_body'] ?? ''));
        }

        return elite_send_operator_follow_up_pushover($lead, [
            'event' => 'sms_delivery_issue',
            'channel' => 'sms',
            'delivery_status' => (string) ($failure['twilio_status'] ?? 'send_failed'),
            'error_code' => (string) ($failure['twilio_code'] ?? ''),
            'error_message' => (string) ($failure['message'] ?? ''),
            'summary' => $summary,
            'note' => $note,
            'quick_action_mode' => 'communication',
        ]);
    }
}

if (!function_exists('elite_twilio_send_sms')) {
    function elite_twilio_send_sms(string $to, string $body, array $context = []): array
    {
        $to = elite_twilio_normalize_us_number($to);
        $body = elite_twilio_prepare_sms_body($body, $context);

        if (!elite_twilio_is_configured()) {
            return [
                'ok' => false,
                'message' => 'Twilio is not configured yet. Add the account SID, auth token, and sender number to .env.',
                'status_code' => 0,
                'body' => $body,
            ];
        }

        if ($to === '') {
            return [
                'ok' => false,
                'message' => 'Lead phone number is not a valid US mobile number.',
                'status_code' => 0,
                'body' => $body,
            ];
        }

        if ($body === '') {
            return [
                'ok' => false,
                'message' => 'Message cannot be empty.',
                'status_code' => 0,
                'body' => $body,
            ];
        }

        if (mb_strlen($body) > 1600) {
            return [
                'ok' => false,
                'message' => 'Message is too long for SMS.',
                'status_code' => 0,
                'body' => $body,
            ];
        }

        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode(TWILIO_ACCOUNT_SID) . '/Messages.json';
        $payload = [
            'To' => $to,
            'Body' => $body,
        ];

        $statusCallback = rtrim(APP_URL, '/') . '/app/api/twilio_sms_status.php';
        if ($statusCallback !== '') {
            $payload['StatusCallback'] = $statusCallback;
        }

        if (TWILIO_MESSAGING_SERVICE_SID !== '') {
            $payload['MessagingServiceSid'] = TWILIO_MESSAGING_SERVICE_SID;
        } else {
            $payload['From'] = TWILIO_FROM_NUMBER;
        }

        $rawResponse = false;
        $curlError = '';
        $statusCode = 0;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return [
                    'ok' => false,
                    'message' => 'Could not initialize Twilio request.',
                    'status_code' => 0,
                    'body' => $body,
                ];
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
            $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => [
                        'Content-Type: application/x-www-form-urlencoded',
                        'Authorization: Basic ' . base64_encode(TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN),
                    ],
                    'content' => http_build_query($payload),
                    'ignore_errors' => true,
                    'timeout' => 20,
                ],
            ]);

            $rawResponse = @file_get_contents($url, false, $context);
            $headers = $http_response_header ?? [];
            foreach ($headers as $header) {
                if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
                    $statusCode = (int) $matches[1];
                    break;
                }
            }
        }

        $decoded = [];
        if (is_string($rawResponse) && $rawResponse !== '') {
            $json = json_decode($rawResponse, true);
            if (is_array($json)) {
                $decoded = $json;
            }
        }

        if ($rawResponse === false || $statusCode < 200 || $statusCode >= 300) {
            esm_log('twilio_sms', 'Twilio SMS failed', [
                'to' => $to,
                'status_code' => $statusCode,
                'twilio_code' => $decoded['code'] ?? null,
                'twilio_message' => $decoded['message'] ?? null,
                'curl_error' => $curlError,
            ]);

            $result = [
                'ok' => false,
                'message' => $decoded['message'] ?? ($curlError !== '' ? $curlError : 'Twilio rejected the SMS request.'),
                'status_code' => $statusCode,
                'twilio_code' => $decoded['code'] ?? null,
                'twilio_status' => $decoded['status'] ?? '',
                'body' => $body,
            ];

            if (!empty($context['send_pushover_fallback'])) {
                $result['operator_fallback_sent'] = elite_twilio_send_failure_fallback($context, $result);
            }

            return $result;
        }

        esm_log('twilio_sms', 'Twilio SMS sent', [
            'to' => $to,
            'from' => TWILIO_MESSAGING_SERVICE_SID !== '' ? TWILIO_MESSAGING_SERVICE_SID : TWILIO_FROM_NUMBER,
            'sid' => $decoded['sid'] ?? null,
            'status' => $decoded['status'] ?? null,
        ]);

        return [
            'ok' => true,
            'message' => 'SMS sent.',
            'status_code' => $statusCode,
            'to' => $to,
            'from' => TWILIO_MESSAGING_SERVICE_SID !== '' ? TWILIO_MESSAGING_SERVICE_SID : TWILIO_FROM_NUMBER,
            'twilio_sid' => $decoded['sid'] ?? '',
            'twilio_status' => $decoded['status'] ?? '',
            'body' => $body,
        ];
    }
}
