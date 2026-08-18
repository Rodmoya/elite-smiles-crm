<?php
declare(strict_types=1);

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/core/db.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/leads/lead_service.php';
require_once __DIR__ . '/app/leads/lead_communications.php';
require_once __DIR__ . '/app/leads/lead_agent.php';

require_auth();
lead_comm_ensure_schema();
lead_agent_ensure_schema();
try {
    lead_agent_backfill_touchpoints(5000);
} catch (Throwable $e) {
    esm_log('lead_agent', 'Historical touchpoint backfill failed.', ['error' => $e->getMessage()]);
}
$user = auth_user();

if (is_post() && post('action') === 'logout') {
    require_csrf();
    auth_logout();
    redirect(base_url('login.php'));
}
if (is_post() && in_array((string) post('action'), ['pause_agent', 'resume_agent'], true)) {
    require_csrf();
    if (!function_exists('auth_can_manage_leads') || !auth_can_manage_leads()) {
        http_response_code(403);
        exit('Forbidden');
    }
    $pause = post('action') === 'pause_agent';
    lead_agent_set_global_pause($pause, (int) ($user['user_id'] ?? 0));
    lead_agent_event(0, 'global-' . ($pause ? 'pause-' : 'resume-') . time(), $pause ? 'paused' : 'resumed', '', 'recorded', $pause ? 'operator_global_pause' : 'operator_global_resume');
    redirect(base_url('lead-agent-operations.php?notice=' . ($pause ? 'paused' : 'resumed')));
}
if (is_post() && post('action') === 'send_availability_options') {
    require_csrf();
    if (!function_exists('auth_can_manage_leads') || !auth_can_manage_leads()) {
        http_response_code(403);
        exit('Forbidden');
    }
    $result = lead_agent_offer_availability(
        (int) post('lead_id'),
        trim((string) post('availability_option_1')),
        trim((string) post('availability_option_2')),
        (int) ($user['user_id'] ?? 0)
    );
    $noticeKey = !empty($result['ok']) ? 'availability_sent' : 'availability_error';
    redirect(base_url('lead-agent-operations.php?notice=' . $noticeKey . '&message=' . rawurlencode((string) ($result['message'] ?? 'Availability could not be sent.'))));
}

$tz = new DateTimeZone(APP_TIMEZONE);
$today = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
$yesterday = (new DateTimeImmutable('yesterday', $tz))->format('Y-m-d');
$selectedDate = trim((string) get('date', $today));
$notice = trim((string) get('notice', ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate) || $selectedDate > $today) {
    $selectedDate = $today;
}

lead_agent_refresh_daily_report($today, false);
lead_agent_refresh_daily_report($yesterday, true);
if ($selectedDate !== $today && $selectedDate !== $yesterday) {
    $existing = db_one('SELECT id FROM lead_agent_daily_reports WHERE report_date = :report_date LIMIT 1', ['report_date' => $selectedDate]);
    if (!$existing) {
        lead_agent_refresh_daily_report($selectedDate, true);
    }
}

$reports = lead_agent_daily_reports(60);
$selectedReport = null;
$morningReport = null;
foreach ($reports as $report) {
    if ((string) $report['report_date'] === $selectedDate) {
        $selectedReport = $report;
    }
    if ((string) $report['report_date'] === $yesterday) {
        $morningReport = $report;
    }
}
if (!$selectedReport) {
    $generated = lead_agent_refresh_daily_report($selectedDate, $selectedDate !== $today);
    $selectedReport = [
        'report_date' => $selectedDate,
        'report_status' => $generated['status'],
        'executive_summary' => $generated['executive_summary'],
        'morning_review' => $generated['morning_review'],
        'metrics' => $generated['metrics'],
        'updated_at' => now(),
    ];
}

$metrics = (array) ($selectedReport['metrics'] ?? []);
$activity = lead_agent_recent_activity($selectedDate, 30);
$learning = lead_agent_learned_guidance('', 6);
$latestRun = lead_agent_latest_run();
$runHealth = lead_agent_run_health($latestRun);
$performance = lead_agent_performance_metrics(30);
$channelPerformance = lead_agent_performance_by_channel(30);
$globallyPaused = lead_agent_is_globally_paused();
$readyRows = db_all("SELECT l.id, l.full_name, l.source, l.scheduling_preferred_day, l.scheduling_preferred_time,
        s.pause_reason, s.scheduling_phase, s.scheduling_context, s.updated_at
    FROM lead_agent_states s INNER JOIN leads l ON l.id = s.lead_id
    WHERE s.status = 'ready_to_schedule' ORDER BY s.updated_at DESC LIMIT 8");
$agentReadyTotal = (int) db_value("SELECT COUNT(*) FROM lead_agent_states WHERE status = 'ready_to_schedule'");
$pipelineCounts = lead_pipeline_counts();
$pipelineSchedulingTotal = (int)($pipelineCounts['scheduling'] ?? 0);
$attentionRows = lead_agent_exception_rows(8);

$logoUrl = base_url('assets/img/ES-Logo-Stack-500-x-150-px.png');
$currentPage = 'lead_agent_operations';
$pageTitle = 'Lead Agent';
$logoutAction = base_url('lead-agent-operations.php');
$mode = lead_agent_mode();
$effectiveMode = $globallyPaused ? 'paused' : $mode;
$modeClasses = $effectiveMode === 'active' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($effectiveMode === 'shadow' ? 'border-amber-200 bg-amber-50 text-amber-700' : ($effectiveMode === 'paused' ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-slate-200 bg-slate-100 text-slate-600'));
$runToneClasses = match ((string)($runHealth['tone'] ?? 'slate')) {
    'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
    'amber' => 'border-amber-200 bg-amber-50 text-amber-800',
    'rose' => 'border-rose-200 bg-rose-50 text-rose-800',
    default => 'border-slate-200 bg-slate-100 text-slate-700',
};
$selectedLabel = (new DateTimeImmutable($selectedDate, $tz))->format('l, F j, Y');
$eventLabels = [
    'enrolled' => 'Agent enrolled a lead',
    'inbound_classified' => 'Inbound conversation handled',
    'automatic_reply' => 'Automatic reply sent',
    'availability_offered' => 'Two appointment options sent',
    'slot_selected' => 'Lead selected an appointment time',
    'cadence_reserved' => 'Approved follow-up sent',
    'cadence_sent' => 'Approved follow-up sent',
    'cadence_failed' => 'Follow-up delivery failed',
    'deferred' => 'Follow-up deferred safely',
    'paused' => 'Automation paused',
    'worker_error' => 'Worker error recorded',
    'handoff' => 'Agent paused and handed off',
    'shadow_reply' => 'Automatic reply prepared in shadow mode',
    'shadow_cadence' => 'Follow-up evaluated in shadow mode',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> | Lead Agent Operations</title>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/lead-agent.css')) ?>">
    <meta name="robots" content="noindex,nofollow">
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
<?php require __DIR__ . '/app/partials/crm_sidebar.php'; ?>

<main id="main-content" class="px-4 py-6 sm:px-6 lg:pl-80 lg:pr-8 lg:py-8">
    <div class="mx-auto max-w-7xl space-y-6">
        <header class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm lg:p-8">
            <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Daily operations</span>
                        <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold <?= e($modeClasses) ?>">
                            <span class="h-2 w-2 rounded-full bg-current" aria-hidden="true"></span><?= e(ucfirst($effectiveMode)) ?><?= $effectiveMode === 'paused' ? '' : ' mode' ?>
                        </span>
                    </div>
                    <h1 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 lg:text-4xl">Lead Agent executive summary</h1>
                    <p class="mt-3 max-w-2xl text-base leading-7 text-slate-600">A daily record of what the agent completed, what moved forward, and the few decisions that genuinely need you.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <a href="<?= e(base_url('lead-agent-operations.php')) ?>" class="inline-flex min-h-11 items-center justify-center rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">Today</a>
                    <a href="<?= e(base_url('lead-agent-operations.php?date=' . rawurlencode($yesterday))) ?>" class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">Morning review</a>
                    <?php if (function_exists('auth_can_manage_leads') && auth_can_manage_leads()): ?>
                        <form method="POST" action="<?= e(base_url('lead-agent-operations.php')) ?>">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="<?= $globallyPaused ? 'resume_agent' : 'pause_agent' ?>">
                            <button type="submit" class="inline-flex min-h-11 cursor-pointer items-center justify-center rounded-2xl border px-4 py-2.5 text-sm font-semibold transition focus-visible:ring-4 <?= $globallyPaused ? 'border-emerald-300 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 focus-visible:ring-emerald-200' : 'border-rose-300 bg-rose-50 text-rose-800 hover:bg-rose-100 focus-visible:ring-rose-200' ?>" aria-label="<?= $globallyPaused ? 'Resume all automated lead follow-up' : 'Pause all automated lead follow-up' ?>">
                                <?= $globallyPaused ? 'Resume agent' : 'Pause agent' ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <?php if (in_array($notice, ['paused', 'resumed'], true)): ?>
            <div class="rounded-2xl border px-4 py-3 text-sm font-semibold <?= $notice === 'paused' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' ?>" role="status" aria-live="polite">
                <?= $notice === 'paused' ? 'Automated lead follow-up is paused. New inbound messages remain saved in the CRM.' : 'Automated lead follow-up resumed successfully.' ?>
            </div>
        <?php endif; ?>
        <?php if (in_array($notice, ['availability_sent', 'availability_error'], true)): ?>
            <div class="rounded-2xl border px-4 py-3 text-sm font-semibold <?= $notice === 'availability_sent' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800' ?>" role="status" aria-live="polite">
                <?= e(trim((string) get('message', '')) ?: ($notice === 'availability_sent' ? 'Two appointment options were sent.' : 'Availability could not be sent.')) ?>
            </div>
        <?php endif; ?>

        <section aria-labelledby="summary-heading" class="grid gap-6 xl:grid-cols-[minmax(0,1.65fr)_minmax(18rem,.85fr)]">
            <article class="rounded-[2rem] border border-slate-200 bg-slate-950 p-6 text-white shadow-sm lg:p-8">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400"><?= e($selectedLabel) ?></p>
                    <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold text-slate-200"><?= e((string) ($selectedReport['report_status'] ?? 'live') === 'final' ? 'Saved day' : 'Live today') ?></span>
                </div>
                <h2 id="summary-heading" class="mt-6 text-2xl font-semibold tracking-tight">Executive summary</h2>
                <p class="mt-4 max-w-3xl text-lg leading-8 text-slate-200"><?= lead_agent_linked_report_text((string) ($selectedReport['executive_summary'] ?? 'No summary is available.'), $metrics, 'font-semibold text-white underline decoration-white/40 underline-offset-4 hover:decoration-white') ?></p>
                <p class="mt-8 text-xs text-slate-400">Updated <?= e((string) ($selectedReport['updated_at'] ?? now())) ?> · saved automatically</p>
            </article>

            <article class="rounded-[2rem] border border-blue-200 bg-blue-50 p-6 shadow-sm lg:p-8">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-700">Morning review</p>
                <h2 class="mt-3 text-xl font-semibold text-slate-950">What happened yesterday</h2>
                <p class="mt-4 text-sm leading-7 text-slate-700"><?= lead_agent_linked_report_text((string) ($morningReport['morning_review'] ?? 'The prior-day review will appear after the agent records activity.'), (array) ($morningReport['metrics'] ?? []), 'font-semibold text-blue-900 underline decoration-blue-300 underline-offset-4 hover:decoration-blue-700') ?></p>
                <a href="<?= e(base_url('lead-agent-operations.php?date=' . rawurlencode($yesterday))) ?>" class="mt-5 inline-flex min-h-11 items-center text-sm font-semibold text-blue-800 underline decoration-blue-300 underline-offset-4">Open yesterday’s saved record</a>
            </article>
        </section>

        <section aria-label="Daily metrics" class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            <?php foreach ([
                ['Actions', 'actions_completed', 'Communication decisions completed'],
                ['Texts', 'sms_sent', 'Approved SMS sent'],
                ['Emails', 'emails_sent', 'Approved emails sent'],
                ['Inbound', 'inbound_handled', 'Replies reviewed by agent'],
                ['Scheduling', 'ready_to_schedule_today', 'New handoffs to Rod'],
                ['Exceptions', 'needs_attention_today', 'Human judgment required'],
            ] as [$label, $key, $hint]): ?>
                <article class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" title="<?= e($hint) ?>">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500"><?= e($label) ?></p>
                    <p class="mt-3 text-3xl font-semibold tabular-nums text-slate-950"><?= e((string) ((int) ($metrics[$key] ?? 0))) ?></p>
                    <p class="mt-2 text-xs leading-5 text-slate-500"><?= e($hint) ?></p>
                </article>
            <?php endforeach; ?>
        </section>

        <section aria-labelledby="performance-heading" class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm lg:p-7">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Conversion learning</p>
                    <h2 id="performance-heading" class="mt-2 text-xl font-semibold text-slate-950">Last 30 days</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Attributed to the most recent automated touch. Tracking improves continuously as new messages are sent.</p>
                </div>
                <p class="text-sm font-semibold tabular-nums text-slate-700"><?= e((string) $performance['touches']) ?> tracked touches</p>
            </div>
            <dl class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6" aria-label="Lead Agent conversion performance">
                <?php foreach ([
                    ['Reply rate', 'reply_rate', '%', 'Leads who replied'],
                    ['Scheduling intent', 'scheduling_rate', '%', 'Leads ready to schedule'],
                    ['Consultations', 'booking_rate', '%', 'Attributed bookings'],
                    ['Delivery failures', 'failure_rate', '%', 'Failed or bounced'],
                    ['Opt-outs', 'opt_out_rate', '%', 'Attributed opt-outs'],
                    ['Email opens', 'opened', '', 'Tracked email opens'],
                ] as [$label, $key, $suffix, $hint]): ?>
                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500"><?= e($label) ?></dt>
                        <dd class="mt-3 text-2xl font-semibold tabular-nums text-slate-950"><?= e((string) ($performance[$key] ?? 0)) ?><?= e($suffix) ?></dd>
                        <p class="mt-2 text-xs leading-5 text-slate-500"><?= e($hint) ?></p>
                    </div>
                <?php endforeach; ?>
            </dl>
            <div class="mt-6 grid gap-3 md:grid-cols-2" aria-label="Performance by communication channel">
                <?php if ($channelPerformance === []): ?><p class="rounded-2xl bg-blue-50 px-4 py-4 text-sm text-blue-800">Channel comparison will appear after the next tracked SMS or email.</p><?php endif; ?>
                <?php foreach ($channelPerformance as $channelRow): $touches = max(1, (int) ($channelRow['touches'] ?? 0)); ?>
                    <article class="rounded-2xl border border-slate-200 px-4 py-4">
                        <div class="flex items-center justify-between gap-3"><h3 class="text-sm font-semibold uppercase tracking-wide text-slate-800"><?= e((string) ($channelRow['channel'] ?? 'channel')) ?></h3><span class="text-xs tabular-nums text-slate-500"><?= e((string) ((int) ($channelRow['touches'] ?? 0))) ?> touches</span></div>
                        <p class="mt-3 text-sm text-slate-600"><strong class="tabular-nums text-slate-950"><?= e((string) round(((int) ($channelRow['replies'] ?? 0) / $touches) * 100, 1)) ?>%</strong> replied &middot; <strong class="tabular-nums text-slate-950"><?= e((string) ((int) ($channelRow['bookings'] ?? 0))) ?></strong> booked &middot; <strong class="tabular-nums text-slate-950"><?= e((string) ((int) ($channelRow['failures'] ?? 0))) ?></strong> failed</p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section aria-label="Agent health and queue status" class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(22rem,.8fr)]">
            <article class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm lg:p-7">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Agent heartbeat</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-950">Latest automated run</h2>
                    </div>
                    <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-bold <?= e($runToneClasses) ?>">
                        <span class="h-2 w-2 rounded-full bg-current" aria-hidden="true"></span><?= e((string)($runHealth['label'] ?? 'Unknown')) ?>
                    </span>
                </div>
                <?php if (!$latestRun): ?>
                    <p class="mt-5 rounded-2xl bg-slate-50 px-4 py-5 text-sm text-slate-600">The first durable run record will appear after the next scheduled Lead Agent execution.</p>
                <?php else: ?>
                    <p class="mt-4 text-sm leading-6 text-slate-600">Finished <?= e((string)($latestRun['finished_at'] ?: $latestRun['started_at'])) ?> · <?= e((string)((int)($latestRun['duration_ms'] ?? 0))) ?> ms · <?= e(ucfirst((string)($latestRun['mode'] ?? 'active'))) ?> mode</p>
                    <dl class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-5">
                        <?php foreach ([
                            ['Due', 'due_count'], ['Processed', 'processed_count'], ['Sent', 'sent_count'], ['Deferred', 'skipped_count'], ['Errors', 'error_count'],
                        ] as [$label, $key]): ?>
                            <div class="rounded-2xl bg-slate-50 p-3"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500"><?= e($label) ?></dt><dd class="mt-2 text-2xl font-semibold tabular-nums text-slate-950"><?= e((string)((int)($latestRun[$key] ?? 0))) ?></dd></div>
                        <?php endforeach; ?>
                    </dl>
                    <?php if (trim((string)($latestRun['error_message'] ?? '')) !== ''): ?><p class="mt-4 rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-800"><?= e((string)$latestRun['error_message']) ?></p><?php endif; ?>
                <?php endif; ?>
            </article>

            <article class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm lg:p-7">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Current workload</p>
                <h2 class="mt-2 text-xl font-semibold text-slate-950">Queue health</h2>
                <dl class="mt-5 space-y-3">
                    <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3"><dt class="text-sm text-slate-600">Scheduling pipeline total</dt><dd class="text-lg font-bold tabular-nums text-slate-950"><?= e((string)$pipelineSchedulingTotal) ?></dd></div>
                    <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3"><dt class="text-sm text-slate-600">Agent handoffs waiting for Rod</dt><dd class="text-lg font-bold tabular-nums text-slate-950"><?= e((string)$agentReadyTotal) ?></dd></div>
                    <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3"><dt class="text-sm text-slate-600">New handoffs today</dt><dd class="text-lg font-bold tabular-nums text-slate-950"><?= e((string)((int)($metrics['ready_to_schedule_today'] ?? 0))) ?></dd></div>
                    <div class="flex items-center justify-between rounded-2xl <?= (int)($metrics['overdue_now'] ?? 0) > 0 ? 'bg-amber-50' : 'bg-emerald-50' ?> px-4 py-3"><dt class="text-sm <?= (int)($metrics['overdue_now'] ?? 0) > 0 ? 'text-amber-800' : 'text-emerald-800' ?>">Overdue automated follow-ups</dt><dd class="text-lg font-bold tabular-nums <?= (int)($metrics['overdue_now'] ?? 0) > 0 ? 'text-amber-900' : 'text-emerald-900' ?>"><?= e((string)((int)($metrics['overdue_now'] ?? 0))) ?></dd></div>
                    <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3"><dt class="text-sm text-slate-600">Oldest overdue</dt><dd class="text-sm font-bold tabular-nums text-slate-950"><?= e((string)((int)($metrics['oldest_overdue_minutes'] ?? 0))) ?> min</dd></div>
                </dl>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <article class="rounded-[2rem] border border-emerald-200 bg-white p-6 shadow-sm lg:p-7">
                <div class="flex items-start justify-between gap-4">
                    <div><p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Ready for you</p><h2 class="mt-2 text-xl font-semibold">Scheduling handoffs</h2></div>
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-sm font-bold tabular-nums text-emerald-800"><?= e((string)$agentReadyTotal) ?></span>
                </div>
                <div class="mt-5 space-y-3">
                    <?php if ($readyRows === []): ?><p class="rounded-2xl bg-slate-50 px-4 py-5 text-sm text-slate-600">No lead is waiting for appointment times right now.</p><?php endif; ?>
                    <?php foreach ($readyRows as $row):
                        $preference = trim((string) ($row['scheduling_context'] ?? ''));
                        if ($preference === '') {
                            $preference = trim((string) ($row['scheduling_preferred_day'] ?? '') . ' ' . (string) ($row['scheduling_preferred_time'] ?? ''));
                        }
                    ?>
                        <div class="rounded-2xl border border-slate-200 p-4">
                            <div class="flex items-start justify-between gap-4">
                                <span><a href="<?= e(base_url('leads.php?lead_id=' . (int) $row['id'])) ?>" class="font-semibold text-slate-900 underline decoration-slate-300 underline-offset-4 hover:text-emerald-800"><?= e((string) ($row['full_name'] ?? 'Lead')) ?></a><span class="mt-1 block text-xs text-slate-500"><?= e((string) ($row['source'] ?? '')) ?></span></span>
                                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800"><?= e($preference !== '' ? $preference : 'Preference not captured') ?></span>
                            </div>
                            <?php if (in_array((string) ($row['scheduling_phase'] ?? ''), ['', 'awaiting_availability'], true)): ?>
                                <form method="POST" action="<?= e(base_url('lead-agent-operations.php')) ?>" class="mt-4 grid gap-3 sm:grid-cols-2">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="send_availability_options">
                                    <input type="hidden" name="lead_id" value="<?= e((string) ((int) $row['id'])) ?>">
                                    <label class="text-xs font-semibold text-slate-600">First option<input required type="datetime-local" name="availability_option_1" class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-100"></label>
                                    <label class="text-xs font-semibold text-slate-600">Second option<input required type="datetime-local" name="availability_option_2" class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-100"></label>
                                    <button type="submit" class="min-h-11 rounded-xl bg-slate-950 px-4 text-sm font-semibold text-white transition hover:bg-slate-800 sm:col-span-2">Send these two options</button>
                                </form>
                            <?php else: ?>
                                <p class="mt-3 text-sm text-slate-600">The lead selected a time and is waiting for final confirmation.</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="rounded-[2rem] border border-rose-200 bg-white p-6 shadow-sm lg:p-7">
                <div class="flex items-start justify-between gap-4">
                    <div><p class="text-xs font-semibold uppercase tracking-[0.18em] text-rose-700">Needs attention</p><h2 class="mt-2 text-xl font-semibold">Agent exceptions only</h2></div>
                    <span class="rounded-full bg-rose-100 px-3 py-1 text-sm font-bold tabular-nums text-rose-800"><?= e((string) count($attentionRows)) ?></span>
                </div>
                <div class="mt-5 space-y-3">
                    <?php if ($attentionRows === []): ?><p class="rounded-2xl bg-emerald-50 px-4 py-5 text-sm text-emerald-800">Clear. The agent can proceed on every active conversation.</p><?php endif; ?>
                    <?php foreach ($attentionRows as $row): $queue = (array) ($row['_action_queue'] ?? []); ?>
                        <a href="<?= e(base_url('leads.php?lead_id=' . (int) $row['id'])) ?>" class="block min-h-14 rounded-2xl border border-slate-200 px-4 py-3 transition hover:border-rose-300 hover:bg-rose-50/50">
                            <span class="font-semibold text-slate-900"><?= e((string) ($row['full_name'] ?? 'Lead')) ?></span>
                            <span class="mt-1 block text-xs leading-5 text-slate-600"><?= e((string) ($queue['reason'] ?? 'Human review required.')) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.3fr)_minmax(20rem,.7fr)]">
            <article class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm lg:p-7">
                <div><p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Audit trail</p><h2 class="mt-2 text-xl font-semibold">Agent activity</h2></div>
                <div class="mt-5 divide-y divide-slate-100">
                    <?php if ($activity === []): ?><p class="py-8 text-center text-sm text-slate-500">No agent activity was recorded for this day.</p><?php endif; ?>
                    <?php foreach ($activity as $event): ?>
                        <div class="grid gap-2 py-4 sm:grid-cols-[7rem_minmax(0,1fr)_auto] sm:items-start">
                            <time class="text-xs font-medium tabular-nums text-slate-500"><?= e(date('g:i A', strtotime((string) $event['created_at']))) ?></time>
                            <div><p class="text-sm font-semibold text-slate-900"><?= e($eventLabels[(string) $event['event_type']] ?? ucwords(str_replace('_', ' ', (string) $event['event_type']))) ?></p><p class="mt-1 text-xs leading-5 text-slate-500"><?php if ((int) ($event['lead_id'] ?? 0) > 0): ?><a href="<?= e(base_url('leads.php?lead_id=' . (int) $event['lead_id'])) ?>" class="font-semibold text-slate-700 underline decoration-slate-300 underline-offset-2 hover:text-blue-800"><?= e((string) ($event['full_name'] ?? 'Lead')) ?></a><?php else: ?>System<?php endif; ?><?= trim((string) ($event['reason'] ?? '')) !== '' ? ' · ' . e((string) $event['reason']) : '' ?></p></div>
                            <span class="text-right text-xs font-semibold uppercase tracking-wide text-slate-500"><?= e(trim((string)($event['channel'] ?? '') . ' ' . (string)($event['status'] ?? ''))) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>

            <div class="space-y-6">
                <article class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Continuous learning</p>
                    <h2 class="mt-2 text-xl font-semibold">What the agent is retaining</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Generalized patterns only. Patient names and raw message text are not stored in learning memory.</p>
                    <div class="mt-5 space-y-4">
                        <?php if ($learning === []): ?><p class="text-sm text-slate-500">Learning begins with the next inbound communication.</p><?php endif; ?>
                        <?php foreach ($learning as $item): ?>
                            <div class="border-l-2 border-blue-200 pl-4"><div class="flex items-center justify-between gap-3"><p class="text-sm font-semibold text-slate-900"><?= e(ucwords(str_replace('_', ' ', (string) $item['intent']))) ?></p><span class="text-xs font-semibold tabular-nums text-blue-700"><?= e((string) ((int) $item['evidence_count'])) ?> signals</span></div><p class="mt-1 text-xs leading-5 text-slate-600"><?= e((string) $item['guidance']) ?></p></div>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Daily archive</p>
                    <h2 class="mt-2 text-xl font-semibold">Saved by day</h2>
                    <nav class="mt-4 max-h-80 space-y-2 overflow-y-auto pr-1" aria-label="Daily report archive">
                        <?php foreach ($reports as $report): $reportDate = (string) $report['report_date']; $reportMetrics = (array) ($report['metrics'] ?? []); ?>
                            <a href="<?= e(base_url('lead-agent-operations.php?date=' . rawurlencode($reportDate))) ?>" class="flex min-h-12 items-center justify-between rounded-2xl border px-4 py-3 transition <?= $reportDate === $selectedDate ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 text-slate-700 hover:bg-slate-50' ?>" <?= $reportDate === $selectedDate ? 'aria-current="page"' : '' ?>>
                                <span class="text-sm font-semibold"><?= e((new DateTimeImmutable($reportDate, $tz))->format('M j, Y')) ?></span>
                                <span class="text-xs tabular-nums <?= $reportDate === $selectedDate ? 'text-slate-300' : 'text-slate-500' ?>"><?= e((string) ((int) ($reportMetrics['actions_completed'] ?? 0))) ?> actions</span>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </article>
            </div>
        </section>
    </div>
</main>
</body>
</html>
