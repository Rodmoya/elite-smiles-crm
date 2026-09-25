<?php
declare(strict_types=1);

/**
 * A delivery failure caused by OUR authentication must never suppress the
 * recipient. Between 2026-09-12 and 2026-09-15 a MailChannels SPF rejection
 * was recorded as a recipient bounce and set email_opt_status='bounced' on
 * nine valid leads (ids 276-284), silently cutting off reachable patients.
 *
 * The bounce bodies quoted below are the real wording from those messages.
 */

require_once dirname(__DIR__) . '/app/leads/lead_email.php';

function sender_side_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$daemon = 'mailer-daemon@single-2020.banahosting.com';
$subject = 'Mail delivery failed: returning message to sender';

// --- sender-side: our fault, recipient is fine, must NOT suppress ---
$senderSide = [
    'smtp; 550 Message rejected: 550 [SPF_INVALID] SPF record validation failed: Domain hi.elitesmilesutah.com',
    'smtp; 550-5.7.26 Your email has been blocked because the sender is unauthenticated.',
    'smtp; 550 5.7.26 Unauthenticated sender not permitted',
    'Message rejected because it fails DMARC policy for the domain',
    'the DKIM signature did not verify for this message',
];
foreach ($senderSide as $body) {
    sender_side_assert(
        lead_email_is_sender_side_rejection($daemon, $subject, $body),
        'Must be classified sender-side so no recipient is suppressed: ' . mb_substr($body, 0, 40)
    );
}

// --- recipient-side: genuinely unreachable, suppression is correct ---
$recipientSide = [
    'smtp; 550-5.1.1 The email account that you tried to reach does not exist.',
    'smtp; 552 5.2.2 The email account that you tried to reach is over quota.',
    'smtp; 550 5.4.4 Unrouteable address',
    'smtp; 550 5.7.1 [RB] This recipient email address has been blocked.',
];
foreach ($recipientSide as $body) {
    sender_side_assert(
        !lead_email_is_sender_side_rejection($daemon, $subject, $body),
        'A recipient-side failure must stay suppressible: ' . mb_substr($body, 0, 40)
    );
}

// An ordinary patient reply must never be read as a sender-side rejection.
sender_side_assert(
    !lead_email_is_sender_side_rejection('patient@gmail.com', 'Re: my consultation', 'Sounds good, see you then.'),
    'A patient reply is not a sender-side rejection.'
);

// --- the guard must run before any recipient lookup, inside the cron ---
$cron = file_get_contents(dirname(__DIR__) . '/app/api/email_inbound_cron.php') ?: '';
$guardAt = strpos($cron, 'lead_email_is_sender_side_rejection(');
$loopAt  = strpos($cron, 'foreach (elite_email_bounce_recipients(');
sender_side_assert($guardAt !== false, 'The cron must consult the sender-side classifier.');
sender_side_assert($loopAt !== false, 'The recipient suppression loop must still exist.');
sender_side_assert($guardAt < $loopAt, 'The guard must short-circuit before any recipient is suppressed.');
sender_side_assert(
    str_contains($cron, "return ['handled' => true, 'matched' => 0, 'unmatched' => 0, 'sender_side' => true];"),
    'A sender-side rejection must return matched=0 so nothing is suppressed.'
);

// --- wiring: both poll paths must honour the flag ---
$cron = file_get_contents(dirname(__DIR__) . '/app/api/email_inbound_cron.php') ?: '';
sender_side_assert(
    substr_count($cron, "if (!empty(\$bounce['sender_side'])) {") === 2,
    'Both poll paths must branch on the sender_side flag.'
);
sender_side_assert(
    substr_count($cron, '$senderBlocked++;') === 2 && substr_count($cron, '$senderBlocked = 0;') === 2,
    'Both poll paths must count sender-side blocks separately.'
);

echo "lead_email_sender_side_rejection_test: OK\n";
