<?php
declare(strict_types=1);
// Exercise the real callback with in-memory persistence. No network or messages.
$fixture = ['id' => 2342, 'lead_id' => 309, 'twilio_status' => 'queued', 'delivered_at' => '2026-09-23 17:26:52'];
$touchpoint = 'queued';
$alerts = 0;
$transaction = false;
$failWrite = false;
function status_expect(bool $ok, string $why): void { if (!$ok) throw new RuntimeException($why); }
function elite_twilio_validate_request(array $params, ?string $url = null): bool { return true; }
function lead_comm_ensure_schema(): void {}
function lead_agent_observability_ensure_schema(): void {}
function db_begin(): bool { $GLOBALS['transaction'] = true; return true; }
function db_commit(): bool { $GLOBALS['transaction'] = false; return true; }
function db_rollBack(): bool { $GLOBALS['transaction'] = false; return true; }
function db_one(string $sql, array $params = []): ?array {
    status_expect($GLOBALS['transaction'] && str_contains($sql, 'FOR UPDATE'), 'Read must hold row lock');
    return str_contains($sql, 'internal_sms_logs') ? null : $GLOBALS['fixture'];
}
function db_query(string $sql, array $params = []): mixed {
    status_expect($GLOBALS['transaction'], 'Write inside transaction');
    $GLOBALS['fixture']['twilio_status'] = $params['status'];
    $GLOBALS['fixture']['delivered_at'] = $params['delivered_at'];
    return null;
}
function lead_agent_update_touchpoint_delivery(string $channel, int $recordId, string $status, string $providerId = ''): void {
    status_expect($GLOBALS['transaction'], 'Touchpoint must share message lock');
    $GLOBALS['touchpoint'] = $status;
}
function lead_agent_mark_sms_delivery_attention(int $id, string $status, string $code = '', string $message = '', array $context = []): array {
    status_expect(!$GLOBALS['transaction'], 'Notification after commit');
    $GLOBALS['alerts']++;
    return [];
}
function callback(string $status): void {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['MessageSid' => 'SMtest', 'MessageStatus' => $status];
    ob_start();
    require dirname(__DIR__) . '/app/api/twilio_sms_status.php';
    status_expect(ob_get_clean() === 'ok', 'Callback acknowledged');
}
callback('delivered');
status_expect($fixture['twilio_status'] === 'delivered' && $touchpoint === 'delivered', 'Reconcile reported inconsistent row');
status_expect($fixture['delivered_at'] === '2026-09-23 17:26:52', 'Preserve original delivery receipt time');
foreach (['queued', 'sent', 'sending', 'accepted', 'failed', 'undelivered', 'delivered', '', 'invalid'] as $status) {
    callback($status);
    status_expect($fixture['twilio_status'] === 'delivered' && $touchpoint === 'delivered', 'No regression: ' . $status);
}
status_expect($alerts === 0, 'Late failure must not raise false attention');
$fixture['twilio_status'] = 'queued'; $fixture['delivered_at'] = null;
callback('sent'); callback('undelivered'); callback('queued'); callback('undelivered');
status_expect($fixture['twilio_status'] === 'undelivered' && $alerts === 1, 'Real failure recorded once');
$ordered = ['accepted', 'scheduled', 'queued', 'sending', 'sent', 'delivered', 'read'];
foreach ($ordered as $i => $current) {
    foreach ($ordered as $j => $incoming) {
        status_expect(elite_twilio_status_should_advance($current, $incoming) === ($j > $i), "$current -> $incoming");
    }
}
foreach (['failed', 'undelivered', 'canceled'] as $terminal) {
    foreach ($ordered as $incoming) status_expect(!elite_twilio_status_should_advance($terminal, $incoming), 'Preserve terminal failure');
}
echo "Twilio status ordering, real callback routing, receipt preservation, row locks and duplicate-alert tests passed.\n";
