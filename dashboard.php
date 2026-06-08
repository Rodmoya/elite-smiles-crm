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
$pageTitle = 'Home';
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> | Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="robots" content="noindex,nofollow">
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <?php require __DIR__ . '/app/partials/crm_sidebar.php'; ?>

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

                    <div class="flex flex-wrap gap-3">
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
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">Add Twilio reply inbox and unread badges.</div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">Turn notes into an activity timeline.</div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">Add follow-up due dates and no-touch filters.</div>
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
