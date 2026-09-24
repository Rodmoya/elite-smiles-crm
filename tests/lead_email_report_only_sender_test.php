<?php
declare(strict_types=1);

/**
 * DMARC aggregate reports were filling the unmatched-inbound table and burying
 * real replies. They are skipped now. Bounce notices are deliberately NOT
 * skipped: a dead patient address is signal worth reviewing.
 *
 * Every "must skip" address below was taken from the production
 * storage/logs/lead_email.log, not invented.
 */

$_ENV['IMAP_USER'] = 'hello@hi.elitesmilesutah.com';
$_ENV['SMTP_USER'] = 'hello@hi.elitesmilesutah.com';

require_once dirname(__DIR__) . '/app/leads/lead_email.php';

function report_only_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

// Observed in production: 156 of 190 unmatched messages were these reporters.
$mustSkip = [
    ['noreply-dmarc-support@google.com', 'Report domain: hi.elitesmilesutah.com Submitter: google.com'],
    ['noreply@dmarc.yahoo.com', 'Report Domain: hi.elitesmilesutah.com Submitter: yahoo.com'],
    ['dmarcreport@microsoft.com', 'Report Domain: hi.elitesmilesutah.com Submitter: protection.outlook.com'],
    ['dmarc-support@alerts.comcast.net', '[Preview] Report Domain: hi.elitesmilesutah.com'],
    ['noreply-dmarc@corp.mail.com', 'Report Domain: hi.elitesmilesutah.com'],
    ['hello@hi.elitesmilesutah.com', 'Crafting Your Perfect Smile with Veneers'],
    ['aggregate@some-new-reporter.example', 'Report Domain: hi.elitesmilesutah.com Submitter: x'],
];
foreach ($mustSkip as [$from, $subject]) {
    report_only_assert(
        lead_email_is_report_only_sender($from, $subject),
        'Report-only mail must be skipped: ' . $from
    );
}

// Bounce notices carry a dead address and must still be retained for review.
$mustRetain = [
    ['mailer-daemon@mailchannels.net', 'Undelivered Mail Returned to Sender'],
    ['mailer-daemon@single-2020.banahosting.com', 'Mail delivery failed: returning message to sender'],
    ['postmaster@example.com', 'Delivery Status Notification (Failure)'],
];
foreach ($mustRetain as [$from, $subject]) {
    report_only_assert(
        !lead_email_is_report_only_sender($from, $subject),
        'Bounce notices must stay reviewable, not be skipped: ' . $from
    );
}

// A real patient reply must never be discarded, however it is worded.
$mustNeverSkip = [
    ['patient@gmail.com', 'Re: Your consultation'],
    ['someone@yahoo.com', 'report domain names I liked for my smile'],
    ['person@hotmail.com', 'Fwd: Report Domain: something a patient pasted'],
    ['first.last@comcast.net', 'Question about veneers'],
    ['', ''],
    ['not-an-email', 'Re: hello'],
];
foreach ($mustNeverSkip as [$from, $subject]) {
    report_only_assert(
        !lead_email_is_report_only_sender($from, $subject),
        'A patient reply must never be treated as report-only: ' . ($from !== '' ? $from : '(empty)')
    );
}

// The subject rule is anchored, so a forwarded/quoted report line cannot trigger it.
report_only_assert(
    !lead_email_is_report_only_sender('patient@gmail.com', 'Re: Report Domain: hi.elitesmilesutah.com'),
    'A quoted report subject on a patient reply must not be skipped.'
);

// Wiring: both poll paths must consult the classifier and count skips apart
// from unmatched, or the ops signal is muddled.
$cron = file_get_contents(dirname(__DIR__) . '/app/api/email_inbound_cron.php') ?: '';
report_only_assert(
    substr_count($cron, 'lead_email_is_report_only_sender(') === 2,
    'Both poll paths must classify report-only senders.'
);
report_only_assert(
    substr_count($cron, '$skipped++;') === 2 && substr_count($cron, '$skipped = 0;') === 2,
    'Both poll paths must count skipped reports separately from unmatched replies.'
);
report_only_assert(
    substr_count($cron, "'skipped' => \$skipped") === 2,
    'Both poll paths must report the skipped count.'
);

echo "lead_email_report_only_sender_test: OK\n";
