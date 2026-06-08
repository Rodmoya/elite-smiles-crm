<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * Public website-form lead intake endpoint.
 *
 * WPForms keeps sending its normal emails. This endpoint receives a copy of
 * the inquiry and creates a CRM lead with source=website.
 */

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/leads/lead_meta.php';
require_once dirname(__DIR__) . '/leads/lead_service.php';

function website_lead_collect_buffer(): string
{
    $contents = '';
    while (ob_get_level() > 0) {
        $chunk = (string) ob_get_contents();
        if ($chunk !== '') {
            $contents .= $chunk;
        }
        ob_end_clean();
    }
    return trim($contents);
}

function website_lead_json_response(int $statusCode, array $payload): void
{
    $buffer = website_lead_collect_buffer();
    if ($buffer !== '') {
        $payload['buffer_output'] = $buffer;
    }

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function website_lead_header(string $name): string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string)($_SERVER[$key] ?? ''));
}

function website_lead_payload(): array
{
    $raw = (string) file_get_contents('php://input');
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));

    if (str_contains($contentType, 'application/json')) {
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    if (!empty($_POST)) {
        return $_POST;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function website_lead_value(array $payload, array $keys): string
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $payload)) {
            $value = $payload[$key];
            if (is_scalar($value)) {
                $value = trim((string) $value);
                if ($value !== '') {
                    return $value;
                }
            }
        }
    }

    $fields = $payload['fields'] ?? [];
    if (is_array($fields)) {
        foreach ($keys as $key) {
            foreach ($fields as $field) {
                if (!is_array($field)) {
                    continue;
                }
                $label = strtolower(trim((string)($field['label'] ?? $field['name'] ?? '')));
                if ($label === strtolower($key)) {
                    $value = trim((string)($field['value'] ?? ''));
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }
    }

    return '';
}

if (request_method() !== 'POST') {
    website_lead_json_response(405, ['ok' => false, 'message' => 'POST required.']);
}

$configuredSecret = defined('ELITE_WEBSITE_WEBHOOK_SECRET') ? trim((string) ELITE_WEBSITE_WEBHOOK_SECRET) : '';
if ($configuredSecret === '') {
    website_lead_json_response(503, ['ok' => false, 'message' => 'Website webhook is not configured.']);
}

$providedSecret = website_lead_header('X-Elite-Webhook-Secret');
if ($providedSecret === '' || !hash_equals($configuredSecret, $providedSecret)) {
    website_lead_json_response(401, ['ok' => false, 'message' => 'Unauthorized.']);
}

$payload = website_lead_payload();
if (empty($payload)) {
    website_lead_json_response(400, ['ok' => false, 'message' => 'Empty payload.']);
}

$fullName = website_lead_value($payload, ['full_name', 'name', 'your name', 'full name']);
$phone = website_lead_value($payload, ['phone', 'phone_number', 'phone number', 'mobile']);
$email = website_lead_value($payload, ['email', 'email_address', 'email address']);
$message = website_lead_value($payload, ['message', 'comments', 'comment', 'questions', 'how can we help']);
$formName = website_lead_value($payload, ['form_name', 'form_title', 'title']);
$pageUrl = website_lead_value($payload, ['page_url', 'url', 'referrer']);
$campaign = website_lead_value($payload, ['campaign', 'campaign_name', 'utm_campaign']);
$procedureInterest = website_lead_value($payload, ['procedure_interest', 'service', 'treatment', 'interest']);

if ($procedureInterest === '') {
    $procedureInterest = 'Website inquiry';
}
if ($campaign === '') {
    $campaign = $formName !== '' ? $formName : 'Website contact form';
}

$notes = [
    'Website form inquiry submitted.',
    'Source: website',
    'Campaign/Form: ' . $campaign,
];
if ($pageUrl !== '') {
    $notes[] = 'Page URL: ' . $pageUrl;
}
if ($message !== '') {
    $notes[] = 'Message: ' . $message;
}

$result = lead_create_minimal([
    'full_name' => $fullName,
    'phone' => $phone,
    'email' => $email,
    'procedure_interest' => $procedureInterest,
    'source' => 'website',
    'source_medium' => 'website',
    'source_type' => 'website_form',
    'landing_page' => $pageUrl,
    'campaign' => $campaign,
    'status' => 'new_lead',
    'financing_needed' => 'unsure',
    'financing_option' => 'none',
    'lead_value' => '10000',
    'notes' => implode("\n", $notes),
    'refresh_duplicate' => true,
], []);

if (!empty($result['duplicate_found'])) {
    website_lead_json_response(200, [
        'ok' => true,
        'duplicate_found' => true,
        'lead_id' => (int)($result['lead_id'] ?? 0),
        'message' => (string)($result['message'] ?? 'Duplicate lead found.'),
    ]);
}

if (empty($result['ok'])) {
    website_lead_json_response(422, [
        'ok' => false,
        'message' => (string)($result['message'] ?? 'Could not create lead.'),
    ]);
}

website_lead_json_response(201, [
    'ok' => true,
    'duplicate_found' => false,
    'lead_id' => (int)($result['lead_id'] ?? 0),
    'message' => 'Lead created.',
]);
