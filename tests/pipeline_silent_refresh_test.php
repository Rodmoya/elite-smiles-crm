<?php

declare(strict_types=1);

$leadsSource = file_get_contents(__DIR__ . '/../leads.php');
$pipelineSource = file_get_contents(__DIR__ . '/../app/partials/dashboard_pipeline.php');

if ($leadsSource === false || $pipelineSource === false) {
    fwrite(STDERR, "Unable to read pipeline sources.\n");
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
