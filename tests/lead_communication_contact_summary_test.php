<?php

declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../app/partials/dashboard_pipeline.php');

if (!is_string($source)) {
    fwrite(STDERR, "Could not read the lead communication contact summary source.\n");
    exit(1);
}

function lead_communication_contact_summary_expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$phonePosition = strpos($source, 'id="legacy-modal-sms-lead-phone"');
$emailPosition = strpos($source, 'id="legacy-modal-sms-lead-email"');

lead_communication_contact_summary_expect(
    $phonePosition !== false && $emailPosition !== false && $emailPosition > $phonePosition,
    'The Communication lead summary must show the email address beneath the phone number.'
);
lead_communication_contact_summary_expect(
    str_contains($source, "setText('legacy-modal-sms-lead-email', (card.dataset.leadEmail || '').trim() || 'No email selected', 'No email selected');"),
    'The Communication lead summary must populate the displayed email from the selected lead.'
);
lead_communication_contact_summary_expect(
    str_contains($source, 'id="legacy-modal-sms-lead-email" class="mt-1 break-all'),
    'Long email addresses must wrap instead of overflowing the Communication summary.'
);

echo "Lead communication contact summary tests passed.\n";
