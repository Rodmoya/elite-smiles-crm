<?php
declare(strict_types=1);

$testRoot = sys_get_temp_dir() . '/elite-meta-test-' . bin2hex(random_bytes(6));
function meta_queue_root_path(): string
{
    global $testRoot;
    return $testRoot;
}
function meta_test_remove_tree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $child = $path . DIRECTORY_SEPARATOR . $item;
        is_dir($child) ? meta_test_remove_tree($child) : @unlink($child);
    }
    @rmdir($path);
}
function meta_test_expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
register_shutdown_function(static fn() => meta_test_remove_tree($GLOBALS['testRoot']));

define('META_PROCESSOR_LIBRARY_ONLY', true);
require dirname(__DIR__) . '/app/meta/meta_queue.php';
require dirname(__DIR__) . '/app/meta/meta_webhook_security.php';
require dirname(__DIR__) . '/app/api/meta_webhook_process.php';

$secret = 'synthetic-app-secret';
$raw = '{"leadgen_id":"synthetic-1001"}';
$signature = 'sha256=' . hash_hmac('sha256', $raw, $secret);
meta_test_expect(meta_webhook_signature_matches($raw, $signature, $secret), 'Valid webhook signature was rejected.');
meta_test_expect(!meta_webhook_signature_matches($raw, 'sha256=invalid', $secret), 'Invalid webhook signature was accepted.');
meta_test_expect(!meta_webhook_payload_valid(['object' => 'page']), 'Payload without a lead event should be rejected.');

$payload = ['leadgen_id' => 'synthetic-1001', 'form_id' => 'form-test'];
$first = meta_queue_enqueue($payload);
$duplicate = meta_queue_enqueue($payload);
meta_test_expect(!empty($first['ok']) && empty($first['duplicate']), 'First webhook was not queued.');
meta_test_expect(!empty($duplicate['duplicate']), 'Meta retry did not resolve to the existing queue event.');
meta_test_expect($first['event_id'] === $duplicate['event_id'], 'Duplicate webhook received a different event id.');

$created = 0;
$successProcessor = static function (string $rawPayload, array $queuedPayload) use (&$created): array {
    $created++;
    return ['ok' => true, 'message' => 'Synthetic lead created.', 'results' => [[
        'ok' => true, 'lead_id' => 900001, 'duplicate_found' => false, 'message' => 'Lead created.',
    ]]];
};
$options = [
    'access_token_available' => true,
    'db_ready' => static fn(): array => ['ok' => true],
    'process' => $successProcessor,
];
$immediate = meta_processor_run(1, (string) $first['event_id'], false, $options);
meta_test_expect((int) $immediate['claimed'] === 1, 'Immediate processor did not claim the webhook event.');
meta_test_expect($created === 1, 'Synthetic lead was not created exactly once.');
meta_test_expect(is_file(meta_queue_record_path('done', (string) $first['event_id'])), 'Processed event was not finalized.');

$afterDoneRetry = meta_queue_enqueue($payload);
meta_processor_run(1, (string) $afterDoneRetry['event_id'], false, $options);
meta_test_expect($created === 1, 'A Meta retry created a duplicate lead.');

$failurePayload = ['leadgen_id' => 'synthetic-1002', 'form_id' => 'form-test'];
$failureEvent = meta_queue_enqueue($failurePayload);
$failureOptions = $options;
$failureOptions['process'] = static fn(): array => ['ok' => true, 'results' => [[
    'ok' => false, 'message' => 'Database temporarily unavailable.',
]]];
$failedAttempt = meta_processor_run(1, (string) $failureEvent['event_id'], false, $failureOptions);
meta_test_expect((string) ($failedAttempt['results'][0]['status'] ?? '') === 'retrying', 'Retryable failure was not left queued.');
meta_test_expect(is_file(meta_queue_record_path('pending', (string) $failureEvent['event_id'])), 'Retryable event was lost from the pending queue.');

$recovered = meta_processor_run(1, (string) $failureEvent['event_id'], false, $options);
meta_test_expect((string) ($recovered['results'][0]['status'] ?? '') === 'processed', 'Server recovery did not process the queued failure.');

$_GET['secret'] = 'must-not-be-read-from-url';
unset($_SERVER['HTTP_X_ELITE_CRON_SECRET'], $_SERVER['HTTP_X_META_PROCESSOR_SECRET']);
meta_test_expect(meta_processor_secret_input() === '', 'Processor accepted a secret from the URL.');

echo "Meta webhook processing tests passed.\n";
