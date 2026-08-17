<?php

declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../app/partials/dashboard_pipeline.php');

if (!is_string($source)) {
    fwrite(STDERR, "Could not read the lead communication timeline source.\n");
    exit(1);
}

function lead_timeline_expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

lead_timeline_expect(
    str_contains($source, "['sms' => 'SMS', 'email' => 'Email', 'notes' => 'Notes', 'all' => 'All']")
        && str_contains($source, 'data-timeline-filter="<?= e($timelineFilterKey) ?>"'),
    'The unified timeline must provide SMS, Email, Notes, and All filters.'
);

lead_timeline_expect(
    str_contains($source, 'let activeTimelineFilter = \'sms\';'),
    'SMS must be the default unified-timeline filter.'
);
lead_timeline_expect(
    str_contains($source, 'role="tablist"') && str_contains($source, "button.setAttribute('aria-selected'"),
    'Timeline filters must expose accessible selected state.'
);
lead_timeline_expect(
    str_contains($source, "if (item.channel === 'email' || item.channel === 'notes')")
        && str_contains($source, '<details class="group rounded-2xl border'),
    'Email and note cards must render collapsed by default.'
);
lead_timeline_expect(
    str_contains($source, "channel: 'sms'")
        && str_contains($source, "channel: 'email'")
        && str_contains($source, "channel: 'notes'"),
    'Every communication source must be assigned to a timeline channel.'
);
lead_timeline_expect(
    str_contains($source, 'return aTime - bTime;') && str_contains($source, 'scrollThreadPaneToBottom(unifiedTimeline);'),
    'The unified timeline must read like a conversation with the newest entry at the bottom.'
);
lead_timeline_expect(
    str_contains($source, "['ArrowLeft', 'ArrowRight', 'Home', 'End']"),
    'Timeline filters must support keyboard navigation.'
);

echo "Lead communication timeline tests passed.\n";
