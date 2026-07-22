<?php

declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: /app/partials/dashboard_action_queue.php
 *
 * Expected variables:
 * - $actionQueueRows (array)
 * - $actionQueueSummary (array)
 */

$actionQueueRows = $actionQueueRows ?? (function_exists('lead_action_queue_rows') ? lead_action_queue_rows(12) : []);
$actionQueueSummary = $actionQueueSummary ?? (function_exists('lead_action_queue_summary') ? lead_action_queue_summary($actionQueueRows) : ['total' => count($actionQueueRows)]);
$actionQueueCompact = !empty($actionQueueCompact);
$actionQueueAiEnabled = !empty($actionQueueAiEnabled);
$actionQueueDisplayLimit = isset($actionQueueDisplayLimit) ? max(1, (int)$actionQueueDisplayLimit) : ($actionQueueCompact ? 12 : 9);
$actionQueueDisplayRows = array_slice($actionQueueRows, 0, $actionQueueDisplayLimit);
$actionQueueTitle = $actionQueueTitle ?? 'Needs Attention Today';
$actionQueueSubtitle = $actionQueueSubtitle ?? 'One clean worklist. Open a lead to review the issue, then let AI propose the next move for approval.';

$queueMetricItems = [
    ['label' => 'Reply', 'value' => (int)($actionQueueSummary['reply_needed'] ?? 0), 'class' => 'border-blue-200 bg-blue-50 text-blue-700'],
    ['label' => 'First touch', 'value' => (int)($actionQueueSummary['first_touch'] ?? 0), 'class' => 'border-sky-200 bg-sky-50 text-sky-700'],
    ['label' => 'Follow-up', 'value' => (int)($actionQueueSummary['follow_up'] ?? 0), 'class' => 'border-amber-200 bg-amber-50 text-amber-800'],
    ['label' => 'Schedule', 'value' => (int)($actionQueueSummary['schedule'] ?? 0), 'class' => 'border-teal-200 bg-teal-50 text-teal-700'],
    ['label' => 'Cleanup', 'value' => (int)($actionQueueSummary['cleanup'] ?? 0), 'class' => 'border-rose-200 bg-rose-50 text-rose-700'],
];

if (!function_exists('lead_action_queue_contact_line')) {
    function lead_action_queue_contact_line(array $lead): string
    {
        $phone = trim((string)($lead['phone'] ?? ''));
        $email = trim((string)($lead['email'] ?? ''));
        if ($phone !== '' && $email !== '') {
            return $phone . ' / ' . $email;
        }
        return $phone !== '' ? $phone : $email;
    }
}

if (!function_exists('lead_action_queue_link')) {
    function lead_action_queue_link(int $leadId): string
    {
        return base_url('leads.php') . ($leadId > 0 ? '?lead_id=' . rawurlencode((string)$leadId) : '');
    }
}

?>

<section class="<?= $actionQueueCompact ? 'mb-4' : 'mb-6' ?>">
    <div class="rounded-[1.75rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Action Queue</p>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <h2 class="text-xl font-semibold text-slate-950"><?= e((string)$actionQueueTitle) ?></h2>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600">
                        <?= e((string)count($actionQueueDisplayRows)) ?> shown
                    </span>
                </div>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500"><?= e((string)$actionQueueSubtitle) ?></p>
            </div>

            <div class="grid grid-cols-2 gap-2 sm:grid-cols-5 xl:min-w-[34rem]">
                <?php foreach ($queueMetricItems as $metric): ?>
                    <div class="rounded-2xl border <?= e($metric['class']) ?> px-3 py-2">
                        <p class="text-[10px] font-bold uppercase tracking-[0.14em]"><?= e($metric['label']) ?></p>
                        <p class="mt-1 text-xl font-semibold leading-none"><?= e((string)$metric['value']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (empty($actionQueueDisplayRows)): ?>
            <div class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                Nothing urgent right now. The pipeline is clean for the moment.
            </div>
        <?php else: ?>
            <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200">
                <?php foreach ($actionQueueDisplayRows as $lead): ?>
                    <?php
                    $queue = (array)($lead['_action_queue'] ?? []);
                    $leadId = (int)($lead['id'] ?? 0);
                    $leadName = trim((string)($lead['full_name'] ?? ''));
                    $leadName = $leadName !== '' ? $leadName : 'Unnamed Lead';
                    $actionLabel = (string)($queue['action_label'] ?? 'Review next step');
                    $actionTone = (string)($queue['action_tone'] ?? 'slate');
                    $stageLabel = (string)($queue['stage_label'] ?? '');
                    $sourceLabel = (string)($queue['source_label'] ?? '');
                    $reason = (string)($queue['reason'] ?? 'Review next step.');
                    $tab = (string)($queue['tab'] ?? 'communications');
                    $contactLine = lead_action_queue_contact_line($lead);
                    $lastTouch = trim((string)($queue['last_touch_at'] ?? ''));
                    $link = lead_action_queue_link($leadId);
                    $urgencyLabel = (string)($queue['urgency_label'] ?? '');
                    $aiInstruction = implode(' ', array_filter([
                        'Analyze this lead because it is in Need Attention Today.',
                        'Lead: ' . $leadName . '.',
                        $actionLabel !== '' ? 'Needed action: ' . $actionLabel . '.' : '',
                        $reason !== '' ? 'Why it is late/flagged: ' . $reason : '',
                        $stageLabel !== '' ? 'Current workflow stage: ' . $stageLabel . '.' : '',
                        $urgencyLabel !== '' ? 'Urgency: ' . $urgencyLabel . '.' : '',
                        'Review the communication thread and CRM notes first.',
                        'Draft the next best patient-facing follow-up focused on scheduling or rescuing the consult.',
                        'Do not send anything. Put the draft in the composer for human approval.',
                    ]));
                    $badgeClass = function_exists('lead_conversion_badge_class')
                        ? lead_conversion_badge_class($actionTone)
                        : 'border-slate-200 bg-slate-50 text-slate-600';
                    ?>
                    <article class="border-b border-slate-200 bg-white px-4 py-3 last:border-b-0 hover:bg-slate-50">
                        <div class="grid grid-cols-1 gap-3 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1.4fr)_auto] lg:items-center">
                            <div class="min-w-0">
                                <a
                                    href="<?= e($link) ?>"
                                    class="block truncate text-sm font-semibold text-slate-950 hover:text-blue-700"
                                    <?= $actionQueueAiEnabled ? 'data-open-attention-review="1"' : 'data-open-action-lead="' . e((string)$leadId) . '"' ?>
                                    data-open-action-tab="<?= e($tab) ?>"
                                    data-attention-lead-id="<?= e((string)$leadId) ?>"
                                    data-attention-lead-name="<?= e($leadName) ?>"
                                    data-attention-action-label="<?= e($actionLabel) ?>"
                                    data-attention-stage-label="<?= e($stageLabel) ?>"
                                    data-attention-source-label="<?= e($sourceLabel) ?>"
                                    data-attention-reason="<?= e($reason) ?>"
                                    data-attention-last-touch="<?= e($lastTouch !== '' ? format_datetime($lastTouch, 'M j g:i A') : '') ?>"
                                    data-attention-ai-instruction="<?= e($aiInstruction) ?>"
                                >
                                    <?= e($leadName) ?>
                                </a>
                                <?php if ($contactLine !== ''): ?>
                                    <p class="mt-1 truncate text-xs text-slate-500"><?= e($contactLine) ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="shrink-0 rounded-md border <?= e($badgeClass) ?> px-2 py-1 text-[10px] font-bold">
                                        <?= e($actionLabel) ?>
                                    </span>
                                    <?php if ($stageLabel !== ''): ?>
                                        <span class="rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-slate-600"><?= e($stageLabel) ?></span>
                                    <?php endif; ?>
                                </div>
                                <p class="mt-2 line-clamp-2 text-xs leading-5 text-slate-600"><?= e($reason) ?></p>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-2 lg:justify-end">
                                <div class="min-w-0 flex flex-wrap items-center gap-1.5 text-[11px] text-slate-500 lg:max-w-[18rem] lg:justify-end">
                                    <?php if ($sourceLabel !== ''): ?>
                                        <span class="max-w-full truncate rounded-md border border-slate-200 bg-white px-2 py-1"><?= e($sourceLabel) ?></span>
                                    <?php endif; ?>
                                    <?php if ($lastTouch !== ''): ?>
                                        <span class="rounded-md border border-slate-200 bg-white px-2 py-1">Last <?= e(format_datetime($lastTouch, 'M j g:i A')) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex shrink-0 gap-2">
                                    <a
                                        href="<?= e($link) ?>"
                                        class="inline-flex h-10 items-center justify-center rounded-xl bg-slate-950 px-3 text-xs font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-900 focus:ring-offset-2"
                                        <?= $actionQueueAiEnabled ? 'data-open-attention-review="1"' : 'data-open-action-lead="' . e((string)$leadId) . '"' ?>
                                        data-open-action-tab="<?= e($tab) ?>"
                                        data-attention-lead-id="<?= e((string)$leadId) ?>"
                                        data-attention-lead-name="<?= e($leadName) ?>"
                                        data-attention-action-label="<?= e($actionLabel) ?>"
                                        data-attention-stage-label="<?= e($stageLabel) ?>"
                                        data-attention-source-label="<?= e($sourceLabel) ?>"
                                        data-attention-reason="<?= e($reason) ?>"
                                        data-attention-last-touch="<?= e($lastTouch !== '' ? format_datetime($lastTouch, 'M j g:i A') : '') ?>"
                                        data-attention-ai-instruction="<?= e($aiInstruction) ?>"
                                    >
                                        Open
                                    </a>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($actionQueueAiEnabled): ?>
    <div
        id="attention-review-modal"
        class="fixed inset-0 z-[80] hidden bg-slate-950/55 p-4 backdrop-blur-sm"
        aria-hidden="true"
    >
        <div class="mx-auto mt-8 flex max-h-[calc(100vh-4rem)] w-full max-w-2xl flex-col overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Attention Review</p>
                    <h3 id="attention-review-lead-name" class="mt-1 truncate text-xl font-semibold text-slate-950">Lead</h3>
                </div>
                <button
                    type="button"
                    id="attention-review-close"
                    class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50"
                    aria-label="Close attention review"
                >
                    &times;
                </button>
            </div>

            <div class="space-y-4 overflow-y-auto px-5 py-5">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-500">Current stage</p>
                        <p id="attention-review-stage" class="mt-2 text-sm font-semibold text-slate-900">-</p>
                    </div>
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-amber-700">Issue</p>
                        <p id="attention-review-issue" class="mt-2 text-sm font-semibold text-amber-950">Review next step</p>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-500">Why this needs attention</p>
                    <p id="attention-review-reason" class="mt-2 text-sm leading-6 text-slate-700">Review this lead before taking action.</p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-500">Source</p>
                        <p id="attention-review-source" class="mt-2 text-sm text-slate-700">-</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-500">Last touch</p>
                        <p id="attention-review-last-touch" class="mt-2 text-sm text-slate-700">-</p>
                    </div>
                </div>

                <div class="rounded-2xl border border-blue-100 bg-blue-50 p-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-blue-700">AI action</p>
                    <p class="mt-2 text-sm leading-6 text-blue-950">
                        AI will read the lead thread and notes, suggest the next action, and place the draft in the composer. You still approve before anything is sent.
                    </p>
                </div>

                <div id="attention-review-loader" class="hidden rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex items-center gap-3">
                        <span class="h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-slate-950"></span>
                        <p class="text-sm font-semibold text-slate-800">AI is reading the lead and drafting the next action...</p>
                    </div>
                </div>

                <div id="attention-review-result" class="hidden space-y-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-emerald-700">Suggested next action</p>
                        <p id="attention-review-suggestion" class="mt-2 text-sm font-semibold leading-6 text-emerald-950">Review draft.</p>
                    </div>
                    <div id="attention-review-sms-wrap" class="hidden rounded-xl border border-white/70 bg-white p-3">
                        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-500">SMS draft</p>
                        <p id="attention-review-sms-draft" class="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-800"></p>
                    </div>
                    <div id="attention-review-email-wrap" class="hidden rounded-xl border border-white/70 bg-white p-3">
                        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-500">Email draft</p>
                        <p id="attention-review-email-subject" class="mt-2 text-sm font-semibold text-slate-900"></p>
                        <p id="attention-review-email-body" class="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-800"></p>
                    </div>
                </div>

                <p id="attention-review-status" class="min-h-5 text-sm text-slate-500"></p>
            </div>

            <div class="flex flex-col gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-end">
                <button
                    type="button"
                    id="attention-review-open-lead"
                    class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                    data-open-action-lead=""
                    data-open-action-tab="communications"
                >
                    Open Full Lead
                </button>
                <button
                    type="button"
                    id="attention-review-ai-action"
                    class="inline-flex h-11 items-center justify-center rounded-xl bg-slate-950 px-4 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                    data-ai-action-lead=""
                    data-open-action-tab="communications"
                    data-ai-action-instruction=""
                >
                    AI Action
                </button>
                <button
                    type="button"
                    id="attention-review-approve-action"
                    class="hidden h-11 items-center justify-center rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    Approve & Open Composer
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>
