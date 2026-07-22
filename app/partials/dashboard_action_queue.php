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
$actionQueueSubtitle = $actionQueueSubtitle ?? 'One clean worklist. Use AI Action to review why the lead is late, draft the next move, then approve before sending.';

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
                                    data-open-action-lead="<?= e((string)$leadId) ?>"
                                    data-open-action-tab="<?= e($tab) ?>"
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
                                    <?php if ($actionQueueAiEnabled): ?>
                                        <button
                                            type="button"
                                            class="inline-flex h-10 items-center justify-center rounded-xl bg-slate-950 px-3 text-xs font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-900 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                                            data-ai-action-lead="<?= e((string)$leadId) ?>"
                                            data-open-action-tab="<?= e($tab) ?>"
                                            data-ai-action-instruction="<?= e($aiInstruction) ?>"
                                        >
                                            AI Action
                                        </button>
                                    <?php endif; ?>
                                    <a
                                        href="<?= e($link) ?>"
                                        class="inline-flex h-10 items-center justify-center rounded-xl <?= $actionQueueAiEnabled ? 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-100 focus:ring-slate-400' : 'bg-slate-950 text-white hover:bg-slate-800 focus:ring-slate-900' ?> px-3 text-xs font-semibold transition focus:outline-none focus:ring-2 focus:ring-offset-2"
                                        data-open-action-lead="<?= e((string)$leadId) ?>"
                                        data-open-action-tab="<?= e($tab) ?>"
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
