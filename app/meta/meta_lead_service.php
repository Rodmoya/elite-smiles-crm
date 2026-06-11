<?php
declare(strict_types=1);

/**
 * Meta integration lead intake service.
 *
 * Keeps webhook endpoint thin:
 * - parses payload
 * - validates signature
 * - fetches lead details from Graph when possible
 * - saves to leads as "new_lead" + Meta source
 * - records webhook/audit events
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/leads/lead_service.php';
require_once dirname(__DIR__) . '/leads/lead_communications.php';
require_once __DIR__ . '/meta_config.php';
require_once __DIR__ . '/meta_graph_client.php';
require_once __DIR__ . '/meta_notifications.php';

if (!function_exists('meta_lead_raw_body')) {
    function meta_lead_raw_body(): string
    {
        $raw = file_get_contents('php://input');
        return is_string($raw) ? $raw : '';
    }
}

if (!function_exists('meta_lead_request_signature')) {
    function meta_lead_request_signature(): string
    {
        $raw = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? $_SERVER['X_HUB_SIGNATURE_256'] ?? '';
        if (is_string($raw)) {
            return trim($raw);
        }
        return '';
    }
}

if (!function_exists('meta_lead_is_get_verification')) {
    function meta_lead_is_get_verification(array $get): bool
    {
        return ($get['hub_mode'] ?? '') === 'subscribe'
            || str_contains((string)($get['hub.mode'] ?? ''), 'subscribe');
    }
}

if (!function_exists('meta_lead_signature_valid')) {
    function meta_lead_signature_valid(string $rawBody, string $signature): bool
    {
        $appSecret = meta_cfg_app_secret();
        if ($appSecret === '' || $signature === '') {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);
        return hash_equals($expected, $signature);
    }
}

if (!function_exists('meta_lead_extract_entries')) {
    function meta_lead_extract_entries(array $payload): array
    {
        $entries = [];

        if (!is_array($payload['entry'] ?? null)) {
            return $entries;
        }

        foreach ($payload['entry'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $pageId = trim((string)($entry['id'] ?? ''));
            $changes = $entry['changes'] ?? [];
            if (!is_array($changes)) {
                continue;
            }

            foreach ($changes as $change) {
                if (!is_array($change)) {
                    continue;
                }

                $value = is_array($change['value'] ?? null) ? $change['value'] : [];
                $field = strtolower((string)($change['field'] ?? ''));
                if ($field !== 'leadgen' && $field !== 'leads') {
                    continue;
                }

                $leadgenId = trim((string)($value['leadgen_id'] ?? ''));
                if ($leadgenId === '') {
                    continue;
                }

                $entries[] = [
                    'leadgen_id' => $leadgenId,
                    'form_id' => trim((string)($value['form_id'] ?? '')),
                    'ad_id' => trim((string)($value['ad_id'] ?? '')),
                    'adset_id' => trim((string)($value['adset_id'] ?? '')),
                    'campaign_id' => trim((string)($value['campaign_id'] ?? '')),
                    'page_id' => trim((string)($value['page_id'] ?? $pageId)),
                    'created_time' => trim((string)($value['created_time'] ?? $payload['created_time'] ?? '')),
                    'raw_value' => $value,
                    'raw_entry' => $entry,
                ];
            }
        }

        return $entries;
    }
}

if (!function_exists('meta_lead_extract_direct_payload')) {
    function meta_lead_extract_direct_payload(array $payload): array
    {
        $leadgenId = trim((string)($payload['leadgen_id'] ?? $payload['id'] ?? ''));
        if ($leadgenId === '') {
            return [];
        }

        $metaLead = meta_graph_normalize_payload($payload);
        return [
            'leadgen_id' => $leadgenId,
            'form_id' => trim((string)($payload['form_id'] ?? $metaLead['form_id'] ?? '')),
            'ad_id' => trim((string)($payload['ad_id'] ?? '')),
            'adset_id' => trim((string)($payload['adset_id'] ?? '')),
            'campaign_id' => trim((string)($payload['campaign_id'] ?? '')),
            'page_id' => trim((string)($payload['page_id'] ?? '')),
            'created_time' => trim((string)($payload['created_time'] ?? '')),
            'raw_value' => $payload,
            'raw_entry' => $payload,
            'meta_field_data' => $metaLead['raw_fields'] ?? [],
        ];
    }
}

if (!function_exists('meta_lead_candidates')) {
    function meta_lead_candidates(array $payload): array
    {
        $fromEntries = meta_lead_extract_entries($payload);
        if ($fromEntries !== []) {
            return $fromEntries;
        }

        $direct = meta_lead_extract_direct_payload($payload);
        return $direct === [] ? [] : [$direct];
    }
}

if (!function_exists('meta_lead_value')) {
    function meta_lead_value(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];
            if (is_scalar($value) && trim((string)$value) !== '') {
                return trim((string)$value);
            }
        }

        return '';
    }
}

if (!function_exists('meta_lead_normalize_phone')) {
    function meta_lead_normalize_phone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string)$phone);
        $digits = is_string($digits) ? $digits : '';
        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }
        return $digits;
    }
}

if (!function_exists('meta_lead_split_name')) {
    function meta_lead_split_name(string $fullName): array
    {
        $fullName = trim($fullName);
        if ($fullName === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $fullName) ?: [];
        if (count($parts) <= 1) {
            return [$fullName, ''];
        }

        $first = array_shift($parts);
        $last = implode(' ', $parts);
        return [$first, $last];
    }
}

if (!function_exists('meta_lead_guess_procedure')) {
    function meta_lead_guess_procedure(string $inputCampaign = ''): string
    {
        $campaign = strtolower($inputCampaign);
        if ($campaign === '') {
            return '';
        }

        if (str_contains($campaign, 'veneer')) {
            return 'Veneers';
        }
        if (str_contains($campaign, 'implant')) {
            return 'Implants';
        }
        if (str_contains($campaign, 'smile')) {
            return 'Smile Makeover';
        }
        if (str_contains($campaign, 'all-on-x') || str_contains($campaign, 'all on x')) {
            return 'All on X';
        }

        return '';
    }
}

if (!function_exists('meta_lead_notes')) {
    function meta_lead_notes(array $normalized, array $event): string
    {
        $notes = ['Meta lead intake received.'];

        if (!empty($normalized['procedure_interest'])) {
            $notes[] = 'Procedure Interest: ' . $normalized['procedure_interest'];
        }
        if (!empty($normalized['how_soon'])) {
            $notes[] = 'How Soon: ' . $normalized['how_soon'];
        }
        if (!empty($event['ad_id'])) {
            $notes[] = 'Ad ID: ' . $event['ad_id'];
        }
        if (!empty($event['adset_id'])) {
            $notes[] = 'Ad Set ID: ' . $event['adset_id'];
        }
        if (!empty($event['campaign_id'])) {
            $notes[] = 'Campaign ID: ' . $event['campaign_id'];
        }
        if (!empty($event['form_id'])) {
            $notes[] = 'Form ID: ' . $event['form_id'];
        }
        if (!empty($event['page_id'])) {
            $notes[] = 'Page ID: ' . $event['page_id'];
        }

        if (($event['raw_entry'] ?? null) !== null && is_array($event['raw_entry'])) {
            $notes[] = 'Event page: ' . (string)($event['raw_entry']['id'] ?? '');
        }

        return implode("\n", array_values(array_filter($notes, static fn($value) => trim((string)$value) !== '')));
    }
}

if (!function_exists('meta_lead_merge_candidate_with_graph')) {
    function meta_lead_merge_candidate_with_graph(array $event): array
    {
        $leadId = (string)($event['leadgen_id'] ?? '');
        $graph = [];
        if ($leadId !== '') {
            $graphResponse = meta_graph_fetch_lead($leadId);
            if (!empty($graphResponse['ok']) && is_array($graphResponse['lead'] ?? null)) {
                $graph = meta_graph_normalize_payload($graphResponse['lead']);
                if ($graph['leadgen_id'] === '') {
                    $graph['leadgen_id'] = $leadId;
                }
            }
        }

        // Field-data from direct payload overrides graph data only when set.
        $directFields = [];
        if (!empty($event['meta_field_data']) && is_array($event['meta_field_data'])) {
            $directFields = $event['meta_field_data'];
        }

        $normalizeFromDirect = [];
        if (!empty($event['raw_value']) && is_array($event['raw_value']) && is_array($event['raw_value']['field_data'] ?? null)) {
            $normalizeFromDirect = meta_graph_normalize_payload($event['raw_value']);
        }

        $base = array_merge([
            'leadgen_id' => (string)($event['leadgen_id'] ?? ''),
            'form_id' => (string)($event['form_id'] ?? ''),
            'ad_id' => (string)($event['ad_id'] ?? ''),
            'adset_id' => (string)($event['adset_id'] ?? ''),
            'campaign_id' => (string)($event['campaign_id'] ?? ''),
            'page_id' => (string)($event['page_id'] ?? ''),
            'created_time' => (string)($event['created_time'] ?? ''),
            'platform' => 'meta',
            'raw_entry' => $event['raw_entry'] ?? [],
            'raw_value' => $event['raw_value'] ?? [],
        ], $graph, $normalizeFromDirect);

        if ($base['full_name'] === '' && !empty($directFields)) {
            $base['full_name'] = trim((string)($directFields['full_name'] ?? ''));
        }

        if ($base['email'] === '' && !empty($directFields['email'])) {
            $base['email'] = strtolower(trim((string)$directFields['email']));
        }

        if ($base['phone_number'] === '' && !empty($directFields['phone_number'])) {
            $base['phone_number'] = trim((string)$directFields['phone_number']);
        }

        if ($base['procedure_interest'] === '' && !empty($directFields['procedure_interest'])) {
            $base['procedure_interest'] = trim((string)$directFields['procedure_interest']);
        }

        $base['lead_source_columns'] = [
            'meta_lead_id' => (string)($base['leadgen_id'] ?? ''),
            'form_id' => (string)($base['form_id'] ?? ''),
            'form_name' => (string)($base['form_name'] ?? ''),
            'ad_id' => (string)($base['ad_id'] ?? ''),
            'adset_id' => (string)($base['adset_id'] ?? ''),
            'campaign_id' => (string)($base['campaign_id'] ?? ''),
            'page_id' => (string)($base['page_id'] ?? ''),
        ];

        return $base;
    }
}

if (!function_exists('meta_lead_prepare_input')) {
    function meta_lead_prepare_input(array $normalized, array $event): array
    {
        $fullName = trim((string)($normalized['full_name'] ?? ''));
        $firstName = trim((string)($normalized['first_name'] ?? ''));
        $lastName = trim((string)($normalized['last_name'] ?? ''));

        if ($fullName === '' && $firstName !== '') {
            $fullName = trim($firstName . ' ' . $lastName);
        }

        if ($fullName === '') {
            $fullName = trim(meta_lead_value($normalized, ['name', 'lead_name', 'customer_name']));
        }

        if ($fullName !== '') {
            [$guessFirst, $guessLast] = meta_lead_split_name($fullName);
            if ($firstName === '') {
                $firstName = $guessFirst;
            }
            if ($lastName === '') {
                $lastName = $guessLast;
            }
        }

        $phone = meta_lead_normalize_phone(meta_lead_value($normalized, ['phone_number', 'phone', 'mobile_phone']));
        $email = strtolower(trim((string)meta_lead_value($normalized, ['email', 'email_address'])));

        $howSoon = trim((string)meta_lead_value($normalized, ['how_soon', 'timeline', 'timeframe', 'preferred_timeline']));
        $procedure = trim((string)meta_lead_value($normalized, [
            'procedure_interest',
            'service_needed',
            'desired_procedure',
            'procedure',
            'service',
            'treatment',
        ]));
        if ($procedure === '') {
            $procedure = meta_lead_guess_procedure((string)meta_lead_value($normalized, ['campaign', 'campaign_name', 'source_campaign']));
        }

        $campaign = trim((string)meta_lead_value($normalized, ['campaign', 'campaign_name', 'source_campaign']));
        $notes = meta_lead_notes($normalized, $event);

        if ($howSoon !== '') {
            $notes .= "\nHow soon: " . $howSoon;
        }

        $leadSource = 'meta_lead_form';
        $leadInput = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => $fullName !== '' ? $fullName : ($firstName !== '' ? trim($firstName . ' ' . $lastName) : ''),
            'phone' => $phone,
            'email' => $email,
            'procedure_interest' => $procedure,
            'source' => $leadSource,
            'source_medium' => 'social',
            'source_type' => 'meta_instant_form',
            'landing_page' => trim((string)meta_lead_value($normalized, ['page_url', 'source_url', 'landing_page'])),
            'campaign' => $campaign,
            'external_lead_id' => trim((string)meta_lead_value($normalized, ['leadgen_id', 'lead_id', 'meta_lead_id', 'id'])),
            'status' => 'new_lead',
            'financing_needed' => 'unsure',
            'financing_option' => 'none',
            'consultation_status' => $howSoon !== '' ? 'requested' : '',
            'notes' => $notes,
            'lead_value' => (string) number_format((float)lead_default_opportunity_value(), 2, '.', ''),
            'refresh_duplicate' => true,
        ];

        $leadInput['lead_source_metadata'] = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'how_soon' => $howSoon,
            'leadgen_id' => trim((string)meta_lead_value($normalized, ['leadgen_id', 'lead_id', 'meta_lead_id', 'id'])),
            'form_id' => trim((string)($normalized['form_id'] ?? '')),
            'campaign_id' => trim((string)($normalized['campaign_id'] ?? '')),
            'ad_id' => trim((string)($normalized['ad_id'] ?? '')),
            'adset_id' => trim((string)($normalized['adset_id'] ?? '')),
            'page_id' => trim((string)($normalized['page_id'] ?? '')),
            'created_time' => trim((string)($normalized['created_time'] ?? '')),
        ];

        return $leadInput;
    }
}

if (!function_exists('meta_lead_update_tracking_columns')) {
    function meta_lead_update_tracking_columns(int $leadId, array $metadata): void
    {
        if ($leadId <= 0 || !leads_table_exists()) {
            return;
        }

        $meta = [];
        if (leads_has_column('source_form_id')) {
            $meta['source_form_id'] = $metadata['form_id'];
        }
        if (leads_has_column('source_form_name')) {
            $meta['source_form_name'] = $metadata['form_name'];
        }
        if (leads_has_column('source_ad_id')) {
            $meta['source_ad_id'] = $metadata['ad_id'];
        }
        if (leads_has_column('source_ad_set_id')) {
            $meta['source_ad_set_id'] = $metadata['adset_id'];
        }
        if (leads_has_column('source_campaign_id')) {
            $meta['source_campaign_id'] = $metadata['campaign_id'];
        }
        if (leads_has_column('source_page_id')) {
            $meta['source_page_id'] = $metadata['page_id'];
        }
        if (leads_has_column('how_soon')) {
            $meta['how_soon'] = $metadata['how_soon'];
        }
        if (leads_has_column('source_context')) {
            $meta['source_context'] = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if ($meta === []) {
            return;
        }

        $meta['updated_at'] = now();
        $set = [];
        foreach (array_keys($meta) as $key) {
            $set[] = "`{$key}` = :{$key}";
        }

        $params = ['id' => $leadId];
        foreach ($meta as $key => $value) {
            $params[$key] = $value;
        }

        try {
            db_execute('UPDATE leads SET ' . implode(', ', $set) . ' WHERE id = :id', $params);
        } catch (Throwable $e) {
            esm_log('meta_webhooks', 'Failed to update meta source columns on lead', [
                'lead_id' => $leadId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}

if (!function_exists('meta_lead_log_payload')) {
    function meta_lead_log_payload(string $event, array $payload): void
    {
        if (!function_exists('esm_log')) {
            return;
        }

        $safe = [];
        foreach ($payload as $key => $value) {
            if (is_string($value)) {
                $safe[$key] = $value;
            }
        }

        // Do not emit full raw payload into logs.
        if (isset($safe['raw_value'])) {
            $safe['raw_value'] = '[payload omitted]';
        }
        if (isset($safe['raw_entry'])) {
            $safe['raw_entry'] = '[payload omitted]';
        }

        esm_log('meta_webhooks', $event, $safe);
    }
}

if (!function_exists('meta_lead_process')) {
    function meta_lead_process(string $rawPayload, array $payload): array
    {
        $startAt = now();

        $candidates = meta_lead_candidates($payload);
        if ($candidates === []) {
            meta_lead_log_payload('meta_webhook_payload_unrecognized', [
                'raw_payload_keys' => array_keys($payload),
            ]);
            return [
                'ok' => true,
                'message' => 'No leadgen payload found.',
                'raw_fallback' => ['received_at' => $startAt],
                'results' => [],
            ];
        }

        $results = [];
        foreach ($candidates as $candidate) {
            $merged = meta_lead_merge_candidate_with_graph($candidate);
            $leadInput = meta_lead_prepare_input($merged, $candidate);
            $meta = $leadInput['lead_source_metadata'] ?? [];
            $leadSourceMeta = $meta;
            unset($leadInput['lead_source_metadata']);

            $createResult = lead_create_minimal($leadInput, []);
            $leadId = (int)($createResult['lead_id'] ?? 0);

            $resultItem = [
                'leadgen_id' => (string)($merged['leadgen_id'] ?? ''),
                'form_id' => (string)($merged['form_id'] ?? ''),
                'lead_id' => $leadId,
                'duplicate_found' => (bool)($createResult['duplicate_found'] ?? false),
                'message' => (string)($createResult['message'] ?? ''),
            ];

            if (!empty($createResult['ok'])) {
                meta_lead_update_tracking_columns($leadId, [
                    'form_id' => (string)($merged['form_id'] ?? ''),
                    'form_name' => (string)($leadInput['campaign'] ?? ''),
                    'ad_id' => (string)($merged['ad_id'] ?? ''),
                    'adset_id' => (string)($merged['adset_id'] ?? ''),
                    'campaign_id' => (string)($merged['campaign_id'] ?? ''),
                    'page_id' => (string)($merged['page_id'] ?? ''),
                    'first_name' => (string)($leadInput['first_name'] ?? ''),
                    'last_name' => (string)($leadInput['last_name'] ?? ''),
                    'how_soon' => (string)($leadSourceMeta['how_soon'] ?? ''),
                    'meta_source' => 'meta_lead_form',
                    'raw_payload' => $leadSourceMeta,
                ]);

                if (empty($createResult['duplicate_found'])) {
                    $leadForNotify = array_merge(
                        ['id' => $leadId],
                        ['full_name' => $leadInput['full_name'], 'phone' => $leadInput['phone'], 'email' => $leadInput['email']],
                        ['created_at' => $startAt, 'source' => $leadInput['source'], 'campaign' => $leadInput['campaign']]
                    );
                    $notify = meta_notify_lead($leadForNotify, [
                        'how_soon' => $leadInput['notes'] !== '' ? ($leadSourceMeta['how_soon'] ?? '') : '',
                        'timeline' => $leadSourceMeta['how_soon'] ?? '',
                        'notes' => $leadInput['notes'] ?? '',
                        'campaign' => $leadInput['campaign'] ?? '',
                        'created_at' => $startAt,
                    ]);

                    $resultItem['notification'] = $notify;
                }

                $resultItem['ok'] = true;
                if (empty($resultItem['message'])) {
                    $resultItem['message'] = $createResult['duplicate_found'] ? 'Duplicate lead found.' : 'Lead created.';
                }
            } else {
                $resultItem['ok'] = false;
                $resultItem['message'] = (string)($createResult['message'] ?? 'Could not create/update lead.');
                $resultItem['error'] = (string)($createResult['message'] ?? '');
            }

            if (function_exists('lead_comm_insert_activity') && $leadId > 0 && !empty($resultItem['ok'])) {
                try {
                    lead_comm_insert_activity($leadId, 'meta_webhook_processed', 'Meta webhook processed: ' . ($resultItem['message'] ?? ''), [
                        'leadgen_id' => $resultItem['leadgen_id'],
                        'form_id' => $resultItem['form_id'],
                    ], 'System');
                } catch (Throwable $e) {
                    // Activity is optional; do not fail webhook.
                }
            }

            $results[] = $resultItem;
        }

        return [
            'ok' => true,
            'message' => 'Meta webhook processed.',
            'results' => $results,
        ];
    }
}
