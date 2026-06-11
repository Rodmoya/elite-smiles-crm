<?php
declare(strict_types=1);

/**
 * Meta Graph API client for lead detail lookups.
 */

require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once __DIR__ . '/meta_config.php';

if (!function_exists('meta_graph_endpoint_base')) {
    function meta_graph_endpoint_base(): string
    {
        $version = ltrim(meta_cfg_graph_version(), '/');
        return 'https://graph.facebook.com/' . $version . '/';
    }
}

if (!function_exists('meta_graph_request')) {
    function meta_graph_request(string $path, array $query = []): array
    {
        $accessToken = meta_cfg_access_token();
        if ($accessToken === '') {
            return [
                'ok' => false,
                'message' => 'Meta access token is not configured.',
            ];
        }

        $query['access_token'] = $accessToken;
        $path = ltrim($path, '/');

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $url = $path;
        } else {
            $url = meta_graph_endpoint_base() . $path;
        }

        $url .= '?' . http_build_query($query);

        $rawResponse = null;
        $curlError = '';
        $statusCode = 0;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return ['ok' => false, 'message' => 'Could not initialize Graph API request.'];
            }

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 12,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_HTTPGET => true,
                CURLOPT_USERAGENT => 'EliteSmilesMetaWebhook/1.0',
            ]);

            $rawResponse = curl_exec($ch);
            $curlError = curl_error($ch);
            $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 12,
                    'ignore_errors' => true,
                    'header' => [
                        'User-Agent: EliteSmilesMetaWebhook/1.0',
                        'Accept: application/json',
                    ],
                ],
            ]);

            $rawResponse = @file_get_contents($url, false, $context);
            foreach (($http_response_header ?? []) as $line) {
                if (preg_match('/^HTTP\/\S+\s+(\d+)/', (string)$line, $matches)) {
                    $statusCode = (int) $matches[1];
                    break;
                }
            }
        }

        if (!is_string($rawResponse) || $rawResponse === '') {
            return [
                'ok' => false,
                'message' => $curlError !== '' ? $curlError : 'Graph API returned empty response.',
                'status_code' => $statusCode,
            ];
        }

        $decoded = json_decode($rawResponse, true);
        if (!is_array($decoded)) {
            return [
                'ok' => false,
                'message' => 'Graph API response was not valid JSON.',
                'status_code' => $statusCode,
                'raw' => $rawResponse,
            ];
        }

        if ($statusCode >= 400 || isset($decoded['error'])) {
            return [
                'ok' => false,
                'message' => (string)($decoded['error']['message'] ?? 'Graph API request failed.'),
                'status_code' => $statusCode,
                'error' => $decoded['error'] ?? null,
                'raw' => $decoded,
            ];
        }

        return ['ok' => true, 'status_code' => $statusCode, 'data' => $decoded];
    }
}

if (!function_exists('meta_graph_fetch_lead')) {
    function meta_graph_fetch_lead(string $leadgenId): array
    {
        $leadgenId = trim($leadgenId);
        if ($leadgenId === '') {
            return ['ok' => false, 'message' => 'Missing leadgen id.'];
        }

        $response = meta_graph_request((string)$leadgenId, [
            'fields' => 'field_data,created_time,ad_id,form_id,adset_id,campaign_id,page_id,id,platform',
        ]);

        if (empty($response['ok'])) {
            return $response;
        }

        return ['ok' => true, 'lead' => $response['data'] ?? []];
    }
}

if (!function_exists('meta_graph_parse_field_value')) {
    function meta_graph_parse_field_value(mixed $field): string
    {
        if (!is_array($field)) {
            return trim((string)$field);
        }

        if (array_key_exists('value', $field) && !is_array($field['value'])) {
            return trim((string) $field['value']);
        }

        if (array_key_exists('values', $field) && is_array($field['values'])) {
            $first = $field['values'][0] ?? '';
            if (is_scalar($first)) {
                return trim((string) $first);
            }
        }

        return '';
    }
}

if (!function_exists('meta_graph_normalize_fields')) {
    function meta_graph_normalize_fields(array $fieldData): array
    {
        $fields = [];

        foreach ($fieldData as $field) {
            if (!is_array($field)) {
                continue;
            }

            $name = strtolower(trim((string)($field['name'] ?? '')));
            if ($name === '') {
                continue;
            }

            $fields[$name] = meta_graph_parse_field_value($field);
        }

        return $fields;
    }
}

if (!function_exists('meta_graph_normalize_payload')) {
    function meta_graph_normalize_payload(array $lead): array
    {
        if (!is_array($lead['field_data'] ?? null)) {
            return [];
        }

        $fields = meta_graph_normalize_fields($lead['field_data']);

        return [
            'full_name' => trim((string)($fields['full_name'] ?? '')),
            'first_name' => trim((string)($fields['first_name'] ?? '')),
            'last_name' => trim((string)($fields['last_name'] ?? '')),
            'email' => strtolower(trim((string)($fields['email'] ?? $fields['email_address'] ?? ''))),
            'phone_number' => trim((string)($fields['phone_number'] ?? $fields['phone'] ?? $fields['mobile_phone'] ?? '')),
            'how_soon' => trim((string)($fields['how_soon'] ?? $fields['timeline'] ?? $fields['timeframe'] ?? '')),
            'procedure_interest' => trim((string)($fields['procedure_interest'] ?? $fields['service_needed'] ?? $fields['service'] ?? $fields['procedure'] ?? '')),
            'form_id' => trim((string)($lead['form_id'] ?? '')),
            'leadgen_id' => trim((string)($lead['id'] ?? $lead['leadgen_id'] ?? '')),
            'ad_id' => trim((string)($lead['ad_id'] ?? '')),
            'adset_id' => trim((string)($lead['adset_id'] ?? '')),
            'campaign_id' => trim((string)($lead['campaign_id'] ?? '')),
            'page_id' => trim((string)($lead['page_id'] ?? '')),
            'platform' => trim((string)($lead['platform'] ?? 'meta')),
            'created_time' => trim((string)($lead['created_time'] ?? '')),
            'raw_fields' => $fields,
        ];
    }
}

