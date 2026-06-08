<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: /app/api/meta_leads_webhook.php
 *
 * Phase 1 Meta leads intake endpoint:
 * - accepts POST only
 * - optional secret protection
 * - accepts JSON payload
 * - normalizes Meta lead fields
 * - creates lead through shared lead service
 *
 * Current goal:
 * create a stable direct intake endpoint for Meta lead flows
 * without depending on extra external fetch logic yet.
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/leads/lead_meta.php';
require_once dirname(__DIR__) . '/leads/lead_service.php';

header('Content-Type: application/json; charset=UTF-8');

function meta_leads_json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function meta_leads_config_secret(): string
{
    $secret = (string) config_value('meta.webhook_secret', '');
    return trim($secret);
}

function meta_leads_request_secret(): string
{
    $headerSecret = '';
    if (isset($_SERVER['HTTP_X_WEBHOOK_SECRET'])) {
        $headerSecret = trim((string) $_SERVER['HTTP_X_WEBHOOK_SECRET']);
    }

    if ($headerSecret !== '') {
        return $headerSecret;
    }

    return trim((string) ($_GET['secret'] ?? $_POST['secret'] ?? ''));
}

function meta_leads_read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function meta_leads_value(array $payload, array $keys, string $default = ''): string
{
    foreach ($keys as $key) {
        if (!array_key_exists($key, $payload)) {
            continue;
        }

        $value = $payload[$key];

        if (is_array($value)) {
            if (isset($value['value']) && !is_array($value['value'])) {
                $candidate = trim((string) $value['value']);
                if ($candidate !== '') {
                    return $candidate;
                }
            }

            if (isset($value[0]) && !is_array($value[0])) {
                $candidate = trim((string) $value[0]);
                if ($candidate !== '') {
                    return $candidate;
                }
            }

            continue;
        }

        $candidate = trim((string) $value);
        if ($candidate !== '') {
            return $candidate;
        }
    }

    return $default;
}

function meta_leads_split_name(string $fullName): array
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

    return [trim((string) $first), trim((string) $last)];
}

function meta_leads_procedure_from_payload(array $payload): string
{
    $procedure = meta_leads_value($payload, [
        'procedure_interest',
        'service_needed',
        'service',
        'treatment',
        'procedure',
        'desired_procedure',
        'interest',
    ]);

    if ($procedure !== '') {
        return $procedure;
    }

    $campaign = strtolower(meta_leads_value($payload, [
        'campaign',
        'campaign_name',
        'source_campaign',
    ]));

    if (str_contains($campaign, 'veneer')) {
        return 'Veneers';
    }
    if (str_contains($campaign, 'implant')) {
        return 'Implants';
    }
    if (str_contains($campaign, 'all-on-x') || str_contains($campaign, 'all on x')) {
        return 'All on X';
    }
    if (str_contains($campaign, 'invisalign')) {
        return 'Invisalign';
    }
    if (str_contains($campaign, 'smile')) {
        return 'Smile Makeover';
    }

    return '';
}

function meta_leads_build_notes(array $payload): string
{
    $notes = [];

    $campaign = meta_leads_value($payload, ['campaign', 'campaign_name', 'source_campaign']);
    $adSet = meta_leads_value($payload, ['ad_set', 'adset_name', 'source_ad_set']);
    $adName = meta_leads_value($payload, ['ad_name', 'source_ad_name']);
    $formName = meta_leads_value($payload, ['form_name', 'lead_form_name']);
    $pageName = meta_leads_value($payload, ['page_name']);
    $platform = meta_leads_value($payload, ['platform'], 'meta');

    $notes[] = 'Meta lead intake received.';

    if ($platform !== '') {
        $notes[] = 'Platform: ' . $platform;
    }
    if ($pageName !== '') {
        $notes[] = 'Page: ' . $pageName;
    }
    if ($campaign !== '') {
        $notes[] = 'Campaign: ' . $campaign;
    }
    if ($adSet !== '') {
        $notes[] = 'Ad Set: ' . $adSet;
    }
    if ($adName !== '') {
        $notes[] = 'Ad Name: ' . $adName;
    }
    if ($formName !== '') {
        $notes[] = 'Form: ' . $formName;
    }

    $freeTextFields = [
        'message',
        'comments',
        'notes',
        'how_can_we_help',
        'main_goal',
        'timeline',
    ];

    foreach ($freeTextFields as $field) {
        $value = meta_leads_value($payload, [$field]);
        if ($value !== '') {
            $label = ucwords(str_replace('_', ' ', $field));
            $notes[] = $label . ': ' . $value;
        }
    }

    return implode("\n", $notes);
}

if (request_method() !== 'POST') {
    meta_leads_json_response([
        'ok' => false,
        'message' => 'Invalid request method.',
    ], 405);
}

$secret = meta_leads_config_secret();
$requestSecret = meta_leads_request_secret();

if ($secret !== '' && $requestSecret !== $secret) {
    meta_leads_json_response([
        'ok' => false,
        'message' => 'Unauthorized webhook request.',
    ], 401);
}

$payload = meta_leads_read_json_body();

if ($payload === []) {
    if (!empty($_POST) && is_array($_POST)) {
        $payload = $_POST;
    }
}

if ($payload === []) {
    meta_leads_json_response([
        'ok' => false,
        'message' => 'Empty payload.',
    ], 422);
}

$fullName = meta_leads_value($payload, [
    'full_name',
    'name',
    'customer_name',
]);

[$firstName, $lastName] = meta_leads_split_name($fullName);

if ($fullName === '') {
    $fullName = trim($firstName . ' ' . $lastName);
}

$email = strtolower(meta_leads_value($payload, [
    'email',
    'email_address',
]));

$phoneRaw = meta_leads_value($payload, [
    'phone',
    'phone_number',
    'mobile_phone',
]);

$phone = only_digits($phoneRaw);
if ($phoneRaw !== '' && $phone === '') {
    $phone = trim($phoneRaw);
}

$procedureInterest = meta_leads_procedure_from_payload($payload);

$campaign = meta_leads_value($payload, [
    'campaign',
    'campaign_name',
    'source_campaign',
]);

$adSet = meta_leads_value($payload, [
    'ad_set',
    'adset_name',
    'source_ad_set',
]);

$adName = meta_leads_value($payload, [
    'ad_name',
    'source_ad_name',
]);

$postId = meta_leads_value($payload, [
    'post_id',
    'source_post_id',
]);

$postLabel = meta_leads_value($payload, [
    'post_label',
    'source_post_label',
]);

$externalLeadId = meta_leads_value($payload, [
    'external_lead_id',
    'lead_id',
    'meta_lead_id',
]);

$landingPage = meta_leads_value($payload, [
    'landing_page',
    'landing_slug',
]);

$financingNeeded = strtolower(meta_leads_value($payload, [
    'financing_needed',
], 'unsure'));

if (!in_array($financingNeeded, ['yes', 'no', 'unsure'], true)) {
    $financingNeeded = 'unsure';
}

$consultationStatus = meta_leads_value($payload, [
    'consultation_status',
]);

$notes = meta_leads_build_notes($payload);

$input = [
    'full_name' => $fullName,
    'phone' => $phone,
    'email' => $email,
    'procedure_interest' => $procedureInterest,
    'source' => 'meta_lead_form',
    'source_medium' => 'paid',
    'source_type' => 'meta_instant_form',
    'landing_page' => $landingPage,
    'campaign' => $campaign,
    'external_lead_id' => $externalLeadId,
    'status' => 'new_lead',
    'financing_needed' => $financingNeeded,
    'financing_option' => 'none',
    'consultation_status' => $consultationStatus,
    'lead_value' => '10000',
    'notes' => $notes,
    'refresh_duplicate' => true,
];

$result = lead_create_minimal($input, []);

if (!empty($result['duplicate_found'])) {
    meta_leads_json_response([
        'ok' => true,
        'duplicate_found' => true,
        'message' => (string)($result['message'] ?? 'Duplicate Meta lead found.'),
        'lead_id' => (int)($result['lead_id'] ?? 0),
        'source' => 'meta_lead_form',
        'source_medium' => 'paid',
        'source_type' => 'meta_instant_form',
    ], 200);
}

if (empty($result['ok'])) {
    meta_leads_json_response([
        'ok' => false,
        'message' => (string) ($result['message'] ?? 'Failed to create Meta lead.'),
        'lead_id' => (int) ($result['lead_id'] ?? 0),
    ], 422);
}

$leadId = (int) ($result['lead_id'] ?? 0);

$updateFields = [];
$params = [
    'id' => $leadId,
];

if ($leadId > 0) {
    if ($campaign !== '' && leads_has_column('source_campaign')) {
        $updateFields[] = 'source_campaign = :source_campaign';
        $params['source_campaign'] = $campaign;
    }

    if ($adSet !== '' && leads_has_column('source_ad_set')) {
        $updateFields[] = 'source_ad_set = :source_ad_set';
        $params['source_ad_set'] = $adSet;
    }

    if ($adName !== '' && leads_has_column('source_ad_name')) {
        $updateFields[] = 'source_ad_name = :source_ad_name';
        $params['source_ad_name'] = $adName;
    }

    if ($postId !== '' && leads_has_column('source_post_id')) {
        $updateFields[] = 'source_post_id = :source_post_id';
        $params['source_post_id'] = $postId;
    }

    if ($postLabel !== '' && leads_has_column('source_post_label')) {
        $updateFields[] = 'source_post_label = :source_post_label';
        $params['source_post_label'] = $postLabel;
    }

    if (!empty($payload) && leads_has_column('source_context')) {
        $updateFields[] = 'source_context = :source_context';
        $params['source_context'] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    if (!empty($updateFields)) {
        $sql = 'UPDATE leads SET ' . implode(', ', $updateFields) . ', updated_at = :updated_at WHERE id = :id';
        $params['updated_at'] = now();
        db_execute($sql, $params);
    }
}

meta_leads_json_response([
    'ok' => true,
    'duplicate_found' => false,
    'message' => 'Meta lead created successfully.',
    'lead_id' => $leadId,
    'source' => 'meta_lead_form',
    'source_medium' => 'paid',
    'source_type' => 'meta_instant_form',
]);
