<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: app/core/twilio.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

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
    function elite_twilio_send_sms(string $to, string $body): array
    {
        $to = elite_twilio_normalize_us_number($to);
        $body = trim($body);

        if (!elite_twilio_is_configured()) {
            return [
                'ok' => false,
                'message' => 'Twilio is not configured yet. Add the account SID, auth token, and sender number to .env.',
                'status_code' => 0,
            ];
        }

        if ($to === '') {
            return [
                'ok' => false,
                'message' => 'Lead phone number is not a valid US mobile number.',
                'status_code' => 0,
            ];
        }

        if ($body === '') {
            return [
                'ok' => false,
                'message' => 'Message cannot be empty.',
                'status_code' => 0,
            ];
        }

        if (mb_strlen($body) > 1600) {
            return [
                'ok' => false,
                'message' => 'Message is too long for SMS.',
                'status_code' => 0,
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

            return [
                'ok' => false,
                'message' => $decoded['message'] ?? ($curlError !== '' ? $curlError : 'Twilio rejected the SMS request.'),
                'status_code' => $statusCode,
                'twilio_code' => $decoded['code'] ?? null,
            ];
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
        ];
    }
}
