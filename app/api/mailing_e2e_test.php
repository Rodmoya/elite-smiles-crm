<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/mailings/mailing_service.php';

header('Content-Type: application/json; charset=utf-8');
$provided = trim((string)($_SERVER['HTTP_X_ELITE_CRON_SECRET'] ?? ''));
$expected = trim((string)ELITE_MAILING_CRON_SECRET);
if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Forbidden.']);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

try {
    mailing_ensure_schema();
    $testAddress = trim((string)SMTP_FROM_EMAIL);
    if (!filter_var($testAddress, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('The configured sender is not a valid test recipient.');
    }

    $contactId = mailing_upsert_contact([
        'email' => $testAddress,
        'full_name' => 'Elite Smiles Campaign Test',
        'source' => 'system_test',
        'opt_source' => 'system_test',
        'tags' => 'system_test',
    ]);
    if ($contactId <= 0) {
        throw new RuntimeException('Could not create the controlled test contact.');
    }
    db_query(
        "UPDATE mailing_contacts SET source = 'system_test', tags = 'system_test', opt_status = 'subscribed', opted_out_at = NULL WHERE id = :id LIMIT 1",
        ['id' => $contactId]
    );

    $stamp = date('M j, Y g:i A');
    $campaignId = mailing_generate_campaign(
        'education',
        "Controlled production validation created {$stamp}. Write a short educational veneers email about natural-looking planning. This is an internal system test. Do not use urgency, guarantees, prices, or patient claims.",
        0,
        'system_test'
    );
    if ($campaignId <= 0) {
        throw new RuntimeException('OpenAI campaign creation did not return a campaign.');
    }
    db_query("UPDATE mailing_campaigns SET title = CONCAT('[SYSTEM TEST] ', title) WHERE id = :id LIMIT 1", ['id' => $campaignId]);

    $image = mailing_generate_image_for_campaign($campaignId);
    if (empty($image['ok'])) {
        mailing_update_status($campaignId, 'paused');
        throw new RuntimeException('Nano Banana image generation failed: ' . (string)($image['message'] ?? 'Unknown error.'));
    }
    mailing_update_status($campaignId, 'approved');
    $delivery = mailing_send_campaign($campaignId, 1);
    if (empty($delivery['ok']) || (int)($delivery['sent'] ?? 0) !== 1) {
        throw new RuntimeException('Controlled delivery failed: ' . (string)($delivery['message'] ?? 'Unknown error.'));
    }
    mailing_log_event($campaignId, $contactId, null, 'e2e_test_completed', ['image_generated' => true, 'sent' => 1]);

    echo json_encode([
        'ok' => true,
        'campaign_id' => $campaignId,
        'image_generated' => true,
        'sent' => 1,
        'recipient' => 'configured practice mailbox',
        'message' => 'Controlled OpenAI, Nano Banana, assembly, tracking, and SMTP delivery completed.',
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    esm_log('mailings', 'Mailing end-to-end test failed.', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
