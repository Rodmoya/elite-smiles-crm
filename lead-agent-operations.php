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

if (is_post() && post('action') === 'logout') {
    require_csrf();
    auth_logout();
    redirect(base_url('login.php'));
}

$tz = new DateTimeZone(APP_TIMEZONE);
$today = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
$yesterday = (new DateTimeImmutable('yesterday', $tz))->format('Y-m-d');
$selectedDate = trim((string) get('date', $today));
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
$readyRows = db_all("SELECT l.id, l.full_name, l.source, s.pause_reason, s.updated_at
    FROM lead_agent_states s INNER JOIN leads l ON l.id = s.lead_id
    WHERE s.status = 'ready_to_schedule' ORDER BY s.updated_at DESC LIMIT 8");
$attentionRows = lead_agent_exception_rows(8);

$user = auth_user();
$logoUrl = base_url('assets/img/ES-Logo-Stack-500-x-150-px.png');
$currentPage = 'lead_agent_operations';
$pageTitle = 'Lead Agent';
$logoutAction = base_url('lead-agent-operations.php');
$mode = lead_agent_mode();
$modeClasses = $mode === 'active' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($mode === 'shadow' ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-slate-200 bg-slate-100 text-slate-600');
$selectedLabel = (new DateTimeImmutable($selectedDate, $tz))->format('l, F j, Y');
$eventLabels = [
    'enrolled' => 'Agent enrolled a lead',
    'inbound_classified' => 'Inbound conversation handled',
    'automatic_reply' => 'Automatic reply sent',
    'handoff' => 'Agent paused and handed off',
    'shadow_reply' => 'Reply prepared in shadow mode',
    'shadow_cadence' => 'Follow-up evaluated in shadow mode',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> | Lead Agent Operations</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="robots" content="noindex,nofollow">
    <style>
        :focus-visible { outline: 3px solid #2563eb; outline-offset: 3px; }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { scroll-behavior: auto !important; transition-duration: .01ms !important; } }
    </style>
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
                            <span class="h-2 w-2 rounded-full bg-current" aria-hidden="true"></span><?= e(ucfirst($mode)) ?> mode
                        </span>
                    </div>
                    <h1 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 lg:text-4xl">Lead Agent executive summary</h1>
                    <p class="mt-3 max-w-2xl text-base leading-7 text-slate-600">A daily record of what the agent completed, what moved forward, and the few decisions that genuinely need you.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <a href="<?= e(base_url('lead-agent-operations.php')) ?>" class="inline-flex min-h-11 items-center justify-center rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">Today</a>
                    <a href="<?= e(base_url('lead-agent-operations.php?date=' . rawurlencode($yesterday))) ?>" class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">Morning review</a>
                </div>
            </div>
        </header>

        <section aria-labelledby="summary-heading" class="grid gap-6 xl:grid-cols-[minmax(0,1.65fr)_minmax(18rem,.85fr)]">
            <article class="rounded-[2rem] border border-slate-200 bg-slate-950 p-6 text-white shadow-sm lg:p-8">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400"><?= e($selectedLabel) ?></p>
                    <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold text-slate-200"><?= e((string) ($selectedReport['report_status'] ?? 'live') === 'final' ? 'Saved day' : 'Live today') ?></span>
                </div>
                <h2 id="summary-heading" class="mt-6 text-2xl font-semibold tracking-tight">Executive summary</h2>
                <p class="mt-4 max-w-3xl text-lg leading-8 text-slate-200"><?= e((string) ($selectedReport['executive_summary'] ?? 'No summary is available.')) ?></p>
                <p class="mt-8 text-xs text-slate-400">Updated <?= e((string) ($selectedReport['updated_at'] ?? now())) ?> · saved automatically</p>
            </article>

            <article class="rounded-[2rem] border border-blue-200 bg-blue-50 p-6 shadow-sm lg:p-8">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-700">Morning review</p>
                <h2 class="mt-3 text-xl font-semibold text-slate-950">What happened yesterday</h2>
                <p class="mt-4 text-sm leading-7 text-slate-700"><?= e((string) ($morningReport['morning_review'] ?? 'The prior-day review will appear after the agent records activity.')) ?></p>
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

        <section class="grid gap-6 xl:grid-cols-2">
            <article class="rounded-[2rem] border border-emerald-200 bg-white p-6 shadow-sm lg:p-7">
                <div class="flex items-start justify-between gap-4">
                    <div><p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Ready for you</p><h2 class="mt-2 text-xl font-semibold">Scheduling handoffs</h2></div>
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-sm font-bold tabular-nums text-emerald-800"><?= e((string) count($readyRows)) ?></span>
                </div>
                <div class="mt-5 space-y-3">
                    <?php if ($readyRows === []): ?><p class="rounded-2xl bg-slate-50 px-4 py-5 text-sm text-slate-600">No lead is waiting for appointment times right now.</p><?php endif; ?>
                    <?php foreach ($readyRows as $row): ?>
                        <a href="<?= e(base_url('leads.php?id=' . (int) $row['id'])) ?>" class="flex min-h-14 items-center justify-between gap-4 rounded-2xl border border-slate-200 px-4 py-3 transition hover:border-emerald-300 hover:bg-emerald-50/50">
                            <span><span class="block font-semibold text-slate-900"><?= e((string) ($row['full_name'] ?? 'Lead')) ?></span><span class="mt-1 block text-xs text-slate-500"><?= e((string) ($row['source'] ?? '')) ?></span></span>
                            <span class="text-xs font-semibold text-emerald-800">Schedule</span>
                        </a>
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
                        <a href="<?= e(base_url('leads.php?id=' . (int) $row['id'])) ?>" class="block min-h-14 rounded-2xl border border-slate-200 px-4 py-3 transition hover:border-rose-300 hover:bg-rose-50/50">
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
                            <div><p class="text-sm font-semibold text-slate-900"><?= e($eventLabels[(string) $event['event_type']] ?? ucwords(str_replace('_', ' ', (string) $event['event_type']))) ?></p><p class="mt-1 text-xs leading-5 text-slate-500"><?= e((string) ($event['full_name'] ?? 'Lead')) ?><?= trim((string) ($event['reason'] ?? '')) !== '' ? ' · ' . e((string) $event['reason']) : '' ?></p></div>
                            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500"><?= e((string) ($event['channel'] ?? '')) ?></span>
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
