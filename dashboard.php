<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: /dashboard.php
 *
 * Home dashboard: high-level command center.
 * The pipeline board lives on /leads.php.
 */

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/core/db.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/leads/lead_meta.php';
require_once __DIR__ . '/app/leads/lead_service.php';
require_once __DIR__ . '/app/leads/lead_communications.php';
require_once __DIR__ . '/app/ai/elite_ai_service.php';

require_auth();
lead_comm_ensure_schema();

if (is_post() && post('action') === 'logout') {
    require_csrf();
    auth_logout();
    flash_set('success', 'You have been logged out.');
    redirect(base_url('login.php'));
}

$user = auth_user();
$firstName = $user['first_name'] ?? 'User';
$logoUrl = base_url('assets/img/ES-Logo-Stack-500-x-150-px.png');
$currentPage = 'dashboard';
$pageTitle = 'Command Center';
$logoutAction = base_url('dashboard.php');

$successMessage = flash_get('success') ?? '';
$errorMessage = '';
$stats = lead_dashboard_stats();

$landingPageTotals = db_one(
    'SELECT
        COUNT(*) AS total_pages,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_pages
     FROM landing_pages'
);

$stats['active_pages'] = (int) ($landingPageTotals['active_pages'] ?? 0);
$totalLandingPages = (int) ($landingPageTotals['total_pages'] ?? 0);
$recentLeads = lead_recent_rows(8);
$dashboardNotifications = function_exists('elite_ai_notification_rows') ? elite_ai_notification_rows(8) : [];
$unreadNotificationCount = count(array_filter($dashboardNotifications, static fn(array $row): bool => !empty($row['is_new'])));
$actionQueueRows = function_exists('lead_action_queue_rows') ? lead_action_queue_rows(50) : [];
$actionQueueSummary = function_exists('lead_action_queue_summary') ? lead_action_queue_summary($actionQueueRows) : ['total' => count($actionQueueRows)];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> | Command Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="robots" content="noindex,nofollow">
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <?php require __DIR__ . '/app/partials/crm_sidebar_live.php'; ?>

    <main class="px-4 py-6 sm:px-6 lg:pl-80 lg:pr-8 lg:py-8">
        <?php if ($successMessage !== ''): ?>
            <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                <?= e($successMessage) ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <?= e($errorMessage) ?>
            </div>
        <?php endif; ?>

        <section class="mb-8">
            <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm lg:p-8">
                <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                    <div class="max-w-3xl">
                        <div class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-600">
                            Elite Smiles CRM
                        </div>
                        <h1 class="mt-4 text-3xl font-semibold tracking-tight text-slate-900 lg:text-4xl">
                            Welcome back, <?= e((string) $firstName) ?>.
                        </h1>
                        <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-600 sm:text-base">
                            This is the home base for lead flow, landing page performance, and follow-up priorities. The pipeline board now has its own Leads page so daily outreach stays fast.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <div class="relative">
                            <button
                                type="button"
                                id="dashboard-notifications-button"
                                class="relative inline-flex h-12 w-12 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:border-blue-300 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                aria-haspopup="true"
                                aria-expanded="false"
                                aria-controls="dashboard-notifications-menu"
                                title="Dashboard notifications"
                            >
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M10.27 21a2 2 0 0 0 3.46 0"></path>
                                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 7-3 9h18c0-2-3-2-3-9"></path>
                                </svg>
                                <?php if ($unreadNotificationCount > 0): ?>
                                    <span class="absolute -right-2 -top-2 inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-blue-600 px-1.5 text-[11px] font-bold text-white shadow-sm"><?= e($unreadNotificationCount > 99 ? '99+' : (string)$unreadNotificationCount) ?></span>
                                <?php endif; ?>
                            </button>
                            <div
                                id="dashboard-notifications-menu"
                                class="hidden absolute right-0 top-14 z-50 w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/15"
                            >
                                <div class="border-b border-slate-100 px-4 py-3">
                                    <p class="text-sm font-semibold text-slate-900">Notifications</p>
                                    <p class="mt-1 text-xs text-slate-500">Replies, new leads, and follow-up alerts.</p>
                                </div>
                                <div class="max-h-96 overflow-y-auto p-2">
                                    <?php if (empty($dashboardNotifications)): ?>
                                        <div class="px-4 py-6 text-center text-sm text-slate-500">No notifications need review right now.</div>
                                    <?php else: ?>
                                        <?php foreach ($dashboardNotifications as $item): ?>
                                            <?php
                                            $leadId = (int)($item['lead_id'] ?? 0);
                                            $isNew = !empty($item['is_new']);
                                            $type = (string)($item['type'] ?? '');
                                            $leadName = trim((string)($item['lead_name'] ?? 'Lead'));
                                            $title = trim((string)($item['title'] ?? 'CRM notification'));
                                            $message = trim((string)($item['message'] ?? $item['suggested_action'] ?? 'Review next step.'));
                                            ?>
                                            <a
                                                href="<?= e(base_url('leads.php') . ($leadId > 0 ? '?lead_id=' . $leadId : '')) ?>"
                                                class="flex items-start gap-3 rounded-2xl px-3 py-3 text-left transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 <?= $isNew ? 'bg-white' : 'bg-slate-50 text-slate-400' ?>"
                                            >
                                                <span class="mt-1 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full <?= $isNew ? ($type === 'reply' ? 'bg-blue-50 text-blue-700' : 'bg-emerald-50 text-emerald-700') : 'bg-slate-100 text-slate-400' ?>">
                                                    <?= $type === 'reply' ? '!' : '+' ?>
                                                </span>
                                                <span class="min-w-0 flex-1">
                                                    <span class="block truncate text-sm font-semibold <?= $isNew ? 'text-slate-900' : 'text-slate-400' ?>"><?= e($leadName) ?></span>
                                                    <span class="mt-0.5 block truncate text-xs font-medium <?= $isNew ? 'text-slate-600' : 'text-slate-400' ?>"><?= e($title) ?></span>
                                                    <span class="mt-1 block line-clamp-2 text-xs <?= $isNew ? 'text-slate-500' : 'text-slate-300' ?>"><?= e($message) ?></span>
                                                    <span class="mt-1 block text-[10px] font-semibold uppercase tracking-[0.12em] <?= $isNew ? 'text-blue-700' : 'text-slate-400' ?>"><?= $isNew ? 'Unread' : 'Read' ?><?= !empty($item['created_at']) ? ' - ' . e(format_datetime((string)$item['created_at'], 'M j g:i A')) : '' ?></span>
                                                </span>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="border-t border-slate-100 p-3">
                                    <a href="<?= e(base_url('leads.php')) ?>" class="block rounded-2xl bg-slate-950 px-4 py-2.5 text-center text-sm font-semibold text-white transition hover:bg-slate-800">Open Leads Board</a>
                                </div>
                            </div>
                        </div>
                        <a
                            href="<?= e(base_url('leads.php')) ?>"
                            class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800"
                        >
                            Open Leads
                        </a>
                        <a
                            href="<?= e(base_url('landing_pages.php')) ?>"
                            class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-5 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
                        >
                            Landing Pages
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <?php require __DIR__ . '/app/partials/dashboard_stats.php'; ?>

        <?php require __DIR__ . '/app/partials/dashboard_action_queue.php'; ?>

        <section class="grid grid-cols-1 gap-5 xl:grid-cols-[1fr_0.9fr]">
            <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Lead Flow</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-900">Recent Leads</h2>
                    </div>
                    <a href="<?= e(base_url('leads.php')) ?>" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100">
                        View Board
                    </a>
                </div>

                <div class="mt-5 divide-y divide-slate-100">
                    <?php if (empty($recentLeads)): ?>
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                            No recent leads yet.
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentLeads as $lead): ?>
                            <?php
                                $leadName = trim((string)($lead['full_name'] ?? ''));
                                $leadName = $leadName !== '' ? $leadName : 'Unnamed Lead';
                                $leadStatus = trim((string)($lead['status'] ?? 'new_lead'));
                                $stageLabels = lead_stage_labels();
                                $stageLabel = $stageLabels[$leadStatus] ?? ucwords(str_replace('_', ' ', $leadStatus));
                            ?>
                            <div class="flex flex-col gap-2 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-900"><?= e($leadName) ?></p>
                                    <p class="mt-1 truncate text-xs text-slate-500">
                                        <?= e((string)($lead['procedure_interest'] ?? 'Service not set')) ?>
                                    </p>
                                </div>
                                <span class="inline-flex w-fit rounded-full border px-3 py-1 text-xs font-semibold <?= e(lead_stage_badge_class($leadStatus)) ?>">
                                    <?= e($stageLabel) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="space-y-5">
                <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Landing Workflow</p>
                    <h2 class="mt-2 text-xl font-semibold text-slate-900">Landing Pages</h2>
                    <p class="mt-3 text-sm leading-7 text-slate-600">
                        Keep Meta, Google, and website traffic pages organized from one place.
                    </p>
                    <div class="mt-5 grid grid-cols-2 gap-3">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-[11px] uppercase tracking-[0.18em] text-slate-500">Active</p>
                            <p class="mt-1 text-2xl font-semibold text-slate-900"><?= e((string) $stats['active_pages']) ?></p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-[11px] uppercase tracking-[0.18em] text-slate-500">Total</p>
                            <p class="mt-1 text-2xl font-semibold text-slate-900"><?= e((string) $totalLandingPages) ?></p>
                        </div>
                    </div>
                </div>

                <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Kaizen Queue</p>
                    <h2 class="mt-2 text-xl font-semibold text-slate-900">Next Improvements</h2>
                    <div class="mt-4 space-y-3 text-sm leading-6 text-slate-600">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">Add Meta Ads Health cards once the Ads MCP connection is live.</div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">Show lead flow: Meta received -> CRM created -> first touch sent.</div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">Add campaign spend and cost-per-lead alerts.</div>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <script>
    (function () {
        const button = document.getElementById('dashboard-notifications-button');
        const menu = document.getElementById('dashboard-notifications-menu');
        if (!button || !menu) return;

        button.addEventListener('click', function (event) {
            event.stopPropagation();
            const hidden = menu.classList.toggle('hidden');
            button.setAttribute('aria-expanded', hidden ? 'false' : 'true');
        });

        document.addEventListener('click', function (event) {
            if (menu.classList.contains('hidden')) return;
            if (menu.contains(event.target) || button.contains(event.target)) return;
            menu.classList.add('hidden');
            button.setAttribute('aria-expanded', 'false');
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            menu.classList.add('hidden');
            button.setAttribute('aria-expanded', 'false');
        });
    })();
    </script>
</body>
</html>

