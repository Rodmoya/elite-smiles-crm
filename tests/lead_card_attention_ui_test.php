<?php

declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function format_datetime(string $value, string $format = 'M j, Y g:i A'): string
{
    $timestamp = strtotime($value);
    return $timestamp ? date($format, $timestamp) : $value;
}

$stageKey = 'new_lead';
$leadAttentionIds = [42 => true];
$lead = [
    'id' => 42,
    'full_name' => 'Attention Test Lead',
    'phone' => '8015551212',
    'email' => 'attention-test@example.invalid',
    'procedure_interest' => 'Veneers',
    'source' => 'manual',
    'preferred_contact' => 'text',
    'consultation_status' => 'requested',
    'status' => 'new_lead',
    'created_at' => '2026-08-26 14:35:00',
];

ob_start();
require dirname(__DIR__) . '/app/partials/lead_card.php';
$html = (string)ob_get_clean();

$expectations = [
    'lead-card-needs-attention' => 'Attention leads must receive the full-card halo class.',
    'data-lead-needs-attention="1"' => 'Attention state must be exposed to the rendered card.',
    'Received Aug 26, 2026 · 2:35 PM' => 'The original lead date and time must render at the card bottom.',
    'lead-card-created-at' => 'The received timestamp needs a stable card-footer selector.',
];

foreach ($expectations as $needle => $message) {
    if (!str_contains($html, $needle)) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

if (!lead_card_needs_attention(['id' => 42], [42 => true])) {
    fwrite(STDERR, "FAIL: A lead in the authoritative attention set must be highlighted.\n");
    exit(1);
}

if (lead_card_needs_attention(['id' => 7, 'follow_up_status' => 'needs_attention'], [])) {
    fwrite(STDERR, "FAIL: A stale lead-row flag must not override the authoritative attention set.\n");
    exit(1);
}

if (strpos($html, 'lead-card-created-at') < strpos($html, 'lead-card-bottom-row')) {
    fwrite(STDERR, "FAIL: The received timestamp must appear after the main card controls.\n");
    exit(1);
}

echo "Lead card attention UI tests passed.\n";
