<?php
declare(strict_types=1);

/**
 * An inbound reply that matches no lead used to be marked \Seen and dropped.
 * Because the poller only ever searches UNSEEN, that lost the reply for good.
 * These checks pin the rule: never mark a message seen unless it was stored.
 */

function unmatched_retention_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$cron = file_get_contents($root . '/app/api/email_inbound_cron.php') ?: '';
$email = file_get_contents($root . '/app/leads/lead_email.php') ?: '';

unmatched_retention_assert($cron !== '' && $email !== '', 'Both inbound sources must be readable.');

// The poller has two implementations (ext/imap and the raw socket fallback that
// production actually runs). Both must retain, so fix one and you must fix both.
unmatched_retention_assert(
    substr_count($cron, '$retained = true;') === 2,
    'Both poll paths must reset the retention flag for every message.'
);
unmatched_retention_assert(
    substr_count($cron, 'if ($retained) {') === 2,
    'Both poll paths must guard the seen-marking behind the retention flag.'
);
// Per poll path: an unmatched reply, an unmatched bounce, and a delivery
// blocked by our own sender authentication. Three kinds, two paths.
unmatched_retention_assert(
    substr_count($cron, 'lead_email_record_unmatched(') === 6,
    'Each poll path must retain unmatched replies, unmatched bounces, and sender-side blocks.'
);

// Every statement that marks a message read must sit directly inside the guard.
$lines = preg_split('/\R/', $cron) ?: [];
$seenMarkers = 0;
foreach ($lines as $index => $line) {
    $isSeenMarker = str_contains($line, 'imap_setflag_full(')
        || (str_contains($line, '+FLAGS.SILENT') && str_contains($line, 'Seen'));
    if (!$isSeenMarker) {
        continue;
    }
    $seenMarkers++;

    $previous = '';
    for ($back = $index - 1; $back >= 0; $back--) {
        if (trim((string)$lines[$back]) !== '') {
            $previous = trim((string)$lines[$back]);
            break;
        }
    }
    unmatched_retention_assert(
        $previous === 'if ($retained) {',
        'A message is marked seen outside the retention guard on line ' . ($index + 1) . '.'
    );
}
unmatched_retention_assert($seenMarkers === 2, 'Expected exactly one seen-marking statement per poll path.');

// A message left unread must surface, or a silent retention failure looks like a clean run.
unmatched_retention_assert(
    substr_count($cron, "unread: could not retain unmatched email.") === 2,
    'A skipped seen-marking must be reported as an error on both paths.'
);

// Retention storage contract.
unmatched_retention_assert(
    str_contains($email, 'function lead_email_record_unmatched('),
    'lead_email.php must expose the unmatched retention helper.'
);
unmatched_retention_assert(
    str_contains($email, 'CREATE TABLE IF NOT EXISTS lead_email_unmatched'),
    'Unmatched inbound mail must have a durable table.'
);
unmatched_retention_assert(
    str_contains($email, 'UNIQUE KEY uniq_source_id (source_id)'),
    'Re-polling the same message must not create duplicate rows.'
);
unmatched_retention_assert(
    str_contains($email, "return ['ok' => false, 'message' => \$e->getMessage()];"),
    'A storage failure must report ok=false so the caller leaves the message unread.'
);

// The schema latch must only close after the table really exists, so a failed
// CREATE is retried on the next poll instead of silently never running again.
$schemaStart = strpos($email, 'function lead_email_ensure_unmatched_schema(');
unmatched_retention_assert($schemaStart !== false, 'The unmatched schema helper must exist.');
$schemaBody = substr($email, $schemaStart, 2000);
$createAt = strpos($schemaBody, 'CREATE TABLE IF NOT EXISTS lead_email_unmatched');
$latchAt = strpos($schemaBody, '$done = true;', $createAt === false ? 0 : $createAt);
unmatched_retention_assert(
    $createAt !== false && $latchAt !== false && $latchAt > $createAt,
    'The schema helper must latch only after the CREATE TABLE succeeds.'
);

echo "lead_email_unmatched_retention_test: OK\n";
