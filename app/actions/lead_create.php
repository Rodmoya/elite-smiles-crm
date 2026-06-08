<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: /app/actions/lead_create.php
 *
 * AJAX endpoint:
 * - create new lead from dashboard modal
 * - auth protected
 * - CSRF protected
 * - returns JSON
 * - prepares intake for landing pages, website, Google, Meta, and future sources
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../leads/lead_meta.php';
require_once __DIR__ . '/../leads/lead_service.php';

header('Content-Type: application/json; charset=UTF-8');

function lead_create_json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function lead_create_normalize_source(string $source): string
{
    $value = strtolower(trim($source));

    return match ($value) {
        'manual',
        'website',
        'landing_page',
        'google',
        'google_ads',
        'facebook',
        'instagram',
        'meta',
        'meta_lead_form',
        'ringcentral',
        'referral',
        'walk_in' => $value,
        '' => 'manual',
        default => $value,
    };
}

function lead_create_normalize_source_medium(string $sourceMedium, string $source): string
{
    $value = strtolower(trim($sourceMedium));
    if ($value !== '') {
        return $value;
    }

    return match ($source) {
        'website', 'landing_page' => 'website',
        'google', 'google_ads' => 'search',
        'facebook', 'instagram', 'meta', 'meta_lead_form' => 'social',
        'ringcentral' => 'phone',
        'referral' => 'referral',
        'walk_in' => 'offline',
        default => 'manual',
    };
}

function lead_create_normalize_source_type(string $sourceType, string $source): string
{
    $value = strtolower(trim($sourceType));
    if ($value !== '') {
        return $value;
    }

    return match ($source) {
        'website' => 'website_form',
        'landing_page' => 'quiz_form',
        'google', 'google_ads' => 'landing_visit',
        'facebook', 'instagram', 'meta' => 'social_lead',
        'meta_lead_form' => 'meta_instant_form',
        'ringcentral' => 'phone_call',
        'referral' => 'manual_entry',
        'walk_in' => 'manual_entry',
        default => 'manual_entry',
    };
}

if (!is_post()) {
    lead_create_json_response([
        'ok' => false,
        'message' => 'Invalid request method.',
    ], 405);
}

if (!is_logged_in()) {
    lead_create_json_response([
        'ok' => false,
        'message' => 'Unauthorized.',
    ], 401);
}

try {
    require_csrf();
} catch (Throwable $e) {
    lead_create_json_response([
        'ok' => false,
        'message' => 'Invalid security token.',
    ], 419);
}

$user = auth_user();

$source = lead_create_normalize_source((string) post('source', 'manual'));
$sourceMedium = lead_create_normalize_source_medium((string) post('source_medium', ''), $source);
$sourceType = lead_create_normalize_source_type((string) post('source_type', ''), $source);

$input = [
    'full_name'          => trim((string) post('full_name')),
    'phone'              => trim((string) post('phone')),
    'email'              => trim((string) post('email')),
    'procedure_interest' => trim((string) post('procedure_interest')),
    'preferred_contact'  => trim((string) post('preferred_contact')),
    'source'             => $source,
    'source_medium'      => $sourceMedium,
    'source_type'        => $sourceType,
    'landing_page'       => trim((string) post('landing_page')),
    'campaign'           => trim((string) post('campaign')),
    'external_lead_id'   => trim((string) post('external_lead_id')),
    'status'             => trim((string) post('status', 'new_lead')),
    'financing_needed'   => trim((string) post('financing_needed', 'unsure')),
    'financing_option'   => trim((string) post('financing_option', 'none')),
    'consultation_status'=> trim((string) post('consultation_status')),
    'consultation_date'  => trim((string) post('consultation_date')),
    'lead_value'         => trim((string) post('lead_value', '10000')),
    'notes'              => trim((string) post('notes')),
];

$result = lead_create_minimal($input, is_array($user) ? $user : []);

if (!empty($result['duplicate_found'])) {
    lead_create_json_response([
        'ok' => false,
        'duplicate_found' => true,
        'duplicate_match_type' => (string)($result['duplicate_match_type'] ?? ''),
        'duplicate_lead_id' => (int)($result['lead_id'] ?? 0),
        'message' => (string)($result['message'] ?? 'Possible duplicate lead found.'),
    ], 409);
}

if (empty($result['ok'])) {
    lead_create_json_response([
        'ok' => false,
        'message' => (string)($result['message'] ?? 'Failed to create lead.'),
        'lead_id' => (int)($result['lead_id'] ?? 0),
    ], 422);
}

lead_create_json_response([
    'ok' => true,
    'message' => (string)($result['message'] ?? 'Lead created successfully.'),
    'lead_id' => (int)($result['lead_id'] ?? 0),
    'source' => $source,
    'source_medium' => $sourceMedium,
    'source_type' => $sourceType,
]);
