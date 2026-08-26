<?php

declare(strict_types=1);

$leadsSource = file_get_contents(__DIR__ . '/../leads.php');
$pipelineSource = file_get_contents(__DIR__ . '/../app/partials/dashboard_pipeline.php');
$leadServiceSource = file_get_contents(__DIR__ . '/../app/leads/lead_service.php');
$leadEmailSource = file_get_contents(__DIR__ . '/../app/leads/lead_email.php');

if ($leadsSource === false || $pipelineSource === false || $leadServiceSource === false || $leadEmailSource === false) {
    fwrite(STDERR, "Unable to read pipeline sources.\n");
    exit(1);
}

$requiredAttentionTokens = [
    'lead_operator_has_stale_sms_delivery',
    "['accepted', 'queued', 'sending', 'scheduled']",
    'lead_attention_rows',
    'array_merge($dueRows, $exceptionRows)',
];

foreach ($requiredAttentionTokens as $token) {
    if (!str_contains($leadServiceSource, $token)) {
        fwrite(STDERR, "Missing unified attention behavior: {$token}\n");
        exit(1);
    }
}

if (!str_contains($leadEmailSource, "next_follow_up_at = :next_follow_up_at")) {
    fwrite(STDERR, "A bounced email must immediately synchronize the visible follow-up schedule.\n");
    exit(1);
}

$requiredLeadRefreshTokens = [
    'refreshPipelineSilently',
    "headers: { 'Accept': 'text/html' }",
    "snapshot.getElementById('lead-pipeline-board')",
    'window.elitePipelineApplySnapshot(incomingBoard)',
    "FROM lead_agent_states",
    "WHERE status = 'needs_attention'",
    "'attention_latest_update'",
    "'attention_due_total'",
    "'attention_delivery_pending_total'",
    'lead_attention_rows(100)',
];

foreach ($requiredLeadRefreshTokens as $token) {
    if (!str_contains($leadsSource, $token)) {
        fwrite(STDERR, "Missing silent-refresh behavior: {$token}\n");
        exit(1);
    }
}

$requiredViewPreservationTokens = [
    'viewportScrollLeft',
    'pipelineDropzoneViewState',
    'anchorLeadId',
    'restorePipelineDropzoneView',
    'window.requestAnimationFrame(restoreView)',
    'bindPipelineCard',
];

foreach ($requiredViewPreservationTokens as $token) {
    if (!str_contains($pipelineSource, $token)) {
        fwrite(STDERR, "Missing pipeline view preservation behavior: {$token}\n");
        exit(1);
    }
}

if (str_contains($leadsSource, 'const reloadPipeline = () =>')) {
    fwrite(STDERR, "Dedicated pipeline refresh must not use a full page reload.\n");
    exit(1);
}

echo "Pipeline silent refresh tests passed.\n";
