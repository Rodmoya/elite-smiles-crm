<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/leads/lead_service.php';
require_once dirname(__DIR__) . '/app/leads/lead_ai.php';

function ingestion_safety_expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$locks = lead_identity_lock_names([
    'external_lead_id' => 'meta-123',
    'email' => 'Patient@Example.com',
    'phone' => '(801) 555-1212',
]);
ingestion_safety_expect(count($locks) === 3, 'Every supplied lead identity must receive an advisory lock.');
ingestion_safety_expect($locks === array_values(array_unique($locks)), 'Lead identity lock names must be unique.');
$sortedLocks = $locks;
sort($sortedLocks);
ingestion_safety_expect($locks === $sortedLocks, 'Lead identity locks must be sorted to prevent deadlocks.');

$metaFormattedPhone = elite_phone_us_analysis('p:+1 (801) 555-1212');
ingestion_safety_expect(!empty($metaFormattedPhone['valid']), 'A valid Meta-formatted US number must remain SMS eligible.');
ingestion_safety_expect($metaFormattedPhone['national'] === '8015551212', 'US normalization must remove formatting and only the country code.');
ingestion_safety_expect($metaFormattedPhone['e164'] === '+18015551212', 'A valid US number must normalize to E.164 for Twilio.');

$incompletePhone = elite_phone_us_analysis('801555121');
ingestion_safety_expect(empty($incompletePhone['valid']) && $incompletePhone['status'] === 'invalid', 'A nine-digit number must be marked invalid.');
ingestion_safety_expect(elite_phone_storage_value('801555121') === '801555121', 'An incomplete number must remain visible for manual correction.');
ingestion_safety_expect(elite_twilio_normalize_us_number('801555121') === '', 'An incomplete number must never reach Twilio.');

$placeholderPhone = elite_phone_us_analysis('0000001001');
ingestion_safety_expect(empty($placeholderPhone['valid']), 'A placeholder with an impossible NANP prefix must be blocked.');

$resolvedReaction = [
    'last_inbound_resolved' => true,
    'last_inbound' => ['body' => '👍 to the previous message'],
];
ingestion_safety_expect(
    lead_ai_reuses_resolved_reaction('I noticed you gave us a thumbs-up.', $resolvedReaction),
    'An acknowledged reaction reused as fresh context must be blocked.'
);
ingestion_safety_expect(
    !lead_ai_reuses_resolved_reaction('We are here whenever you are ready.', $resolvedReaction),
    'A normal follow-up must not be blocked merely because an old reaction exists.'
);

$metaWebhook = (string) file_get_contents(dirname(__DIR__) . '/app/api/meta_webhook.php');
ingestion_safety_expect(
    str_contains($metaWebhook, "\$metaAppSecret === '' || !meta_webhook_signature_valid(\$rawBody)"),
    'Meta webhook authentication must fail closed when the app secret is unavailable.'
);
$legacyMetaWebhook = (string) file_get_contents(dirname(__DIR__) . '/app/api/meta_leads_webhook.php');
ingestion_safety_expect(
    str_contains($legacyMetaWebhook, "\$secret === '' || \$requestSecret === '' || !hash_equals(\$secret, \$requestSecret)"),
    'The direct Meta intake endpoint must also fail closed and compare its secret safely.'
);
ingestion_safety_expect(
    str_contains($legacyMetaWebhook, "'phone_raw' => \$phoneRaw"),
    'The direct Meta intake endpoint must preserve the unnormalized phone value.'
);
$metaLeadService = (string) file_get_contents(dirname(__DIR__) . '/app/meta/meta_lead_service.php');
ingestion_safety_expect(
    str_contains($metaLeadService, "'phone_raw' => \$phoneRaw")
        && str_contains($metaLeadService, "'phone_validation_status' => (string)\$phoneAnalysis['status']"),
    'The native Meta Graph intake path must preserve raw phone provenance and validation status.'
);

$twilioWebhook = (string) file_get_contents(dirname(__DIR__) . '/app/api/twilio_sms_webhook.php');
ingestion_safety_expect(
    str_contains($twilioWebhook, 'Ignored duplicate inbound SMS webhook')
        && str_contains($twilioWebhook, 'Concurrent duplicate inbound SMS webhook was safely ignored.')
        && str_contains($twilioWebhook, 'SELECT GET_LOCK(:lock_name, 5)'),
    'Inbound Twilio processing must serialize and suppress webhook retries.'
);

echo "Lead ingestion safety tests passed.\n";
