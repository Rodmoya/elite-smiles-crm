<?php
declare(strict_types=1);

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/core/db.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/leads/lead_meta.php';
require_once __DIR__ . '/app/leads/lead_service.php';

require_auth();

if (is_post() && post('action') === 'logout') {
    require_csrf();
    auth_logout();
    flash_set('success', 'You have been logged out.');
    redirect(base_url('login.php'));
}

$user = auth_user() ?: [];
$logoUrl = base_url('assets/img/ES-Logo-Stack-500-x-150-px.png');
$currentPage = 'marketing';
$pageTitle = 'Marketing';
$logoutAction = base_url('marketing.php');
$stats = lead_dashboard_stats();

function marketing_table_exists(string $table): bool
{
    try {
        return (bool) db_value("SHOW TABLES LIKE :table_name", ['table_name' => $table]);
    } catch (Throwable) {
        return false;
    }
}

function marketing_money(float|int|string|null $amount): string
{
    return '$' . number_format((float)($amount ?? 0), 0);
}

function marketing_int(float|int|string|null $amount): string
{
    return number_format((float)($amount ?? 0), 0);
}

function marketing_lead_value_expr(): string
{
    $default = number_format((float) lead_default_opportunity_value(), 2, '.', '');
    if (!leads_has_column('lead_value')) {
        return $default;
    }

    return "CASE WHEN lead_value IS NULL OR lead_value = 0 OR lead_value = 10000 THEN {$default} ELSE lead_value END";
}

function marketing_select_expr(string $column, string $fallback = "''"): string
{
    return leads_has_column($column) ? "COALESCE(NULLIF(TRIM({$column}), ''), {$fallback})" : $fallback;
}

$sourceRows = [];
$landingRows = [];
$recentMarketingLeads = [];
$leadValueExpr = leads_table_exists() ? marketing_lead_value_expr() : '0';
$dateWindowSql = leads_has_column('created_at') ? "created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)" : '1 = 1';
$visibilityAnd = leads_table_exists() ? lead_pipeline_visibility_sql('AND') : '';

if (leads_table_exists()) {
    $sourceExpr = marketing_select_expr('source', "'Unknown'");
    if (leads_has_column('source_campaign')) {
        $campaignExpr = marketing_select_expr('source_campaign', "''");
    } elseif (leads_has_column('campaign')) {
        $campaignExpr = marketing_select_expr('campaign', "''");
    } else {
        $campaignExpr = "''";
    }
    $landingExpr = marketing_select_expr('landing_page', "''");
    $consultExpr = leads_has_column('consultation_status') ? " OR consultation_status IN ('scheduled', 'completed')" : '';
    $lastLeadExpr = leads_has_column('created_at') ? 'MAX(created_at)' : 'NULL';

    $sourceRows = db_all("
        SELECT
            {$sourceExpr} AS source_name,
            {$campaignExpr} AS campaign_name,
            COUNT(*) AS leads_count,
            SUM(CASE WHEN status IN ('consultation_booked', 'consult_completed', 'treatment_accepted'){$consultExpr} THEN 1 ELSE 0 END) AS booked_count,
            SUM(CASE WHEN status = 'treatment_accepted' THEN 1 ELSE 0 END) AS won_count,
            SUM({$leadValueExpr}) AS estimated_value
        FROM leads
        WHERE {$dateWindowSql}{$visibilityAnd}
        GROUP BY source_name, campaign_name
        ORDER BY leads_count DESC, estimated_value DESC
        LIMIT 12
    ");

    $landingRows = db_all("
        SELECT
            {$landingExpr} AS landing_page,
            COUNT(*) AS leads_count,
            SUM(CASE WHEN status IN ('consultation_booked', 'consult_completed', 'treatment_accepted'){$consultExpr} THEN 1 ELSE 0 END) AS booked_count,
            {$lastLeadExpr} AS last_lead_at
        FROM leads
        WHERE {$dateWindowSql}{$visibilityAnd}
            AND {$landingExpr} <> ''
        GROUP BY landing_page
        ORDER BY leads_count DESC, last_lead_at DESC
        LIMIT 8
    ");

    $recentFields = ['id', 'full_name', "{$sourceExpr} AS source_name", "{$campaignExpr} AS campaign_name", "{$landingExpr} AS landing_page"];
    if (leads_has_column('created_at')) {
        $recentFields[] = 'created_at';
    }
    if (leads_has_column('status')) {
        $recentFields[] = 'status';
    }

    $recentMarketingLeads = db_all("
        SELECT " . implode(', ', $recentFields) . "
        FROM leads
        WHERE {$dateWindowSql}{$visibilityAnd}
        ORDER BY " . (leads_has_column('created_at') ? 'created_at DESC,' : '') . " id DESC
        LIMIT 8
    ");
}

$landingPageTotals = marketing_table_exists('landing_pages')
    ? db_one('SELECT COUNT(*) AS total_pages, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_pages FROM landing_pages')
    : ['total_pages' => 0, 'active_pages' => 0];

$mailingCounts = [
    'campaigns' => marketing_table_exists('mailing_campaigns') ? (int) db_value('SELECT COUNT(*) FROM mailing_campaigns') : 0,
    'review' => marketing_table_exists('mailing_campaigns') ? (int) db_value("SELECT COUNT(*) FROM mailing_campaigns WHERE status IN ('draft', 'review')") : 0,
    'contacts' => marketing_table_exists('mailing_contacts') ? (int) db_value("SELECT COUNT(*) FROM mailing_contacts WHERE opt_status = 'subscribed'") : 0,
];

$socialCounts = [
    'drafts' => marketing_table_exists('social_studio_drafts') ? (int) db_value("SELECT COUNT(*) FROM social_studio_drafts WHERE status IN ('draft', 'review')") : 0,
    'scheduled' => marketing_table_exists('social_studio_drafts') ? (int) db_value("SELECT COUNT(*) FROM social_studio_drafts WHERE status = 'scheduled'") : 0,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> | Marketing</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="robots" content="noindex,nofollow">
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <?php require __DIR__ . '/app/partials/crm_sidebar.php'; ?>

    <main class="px-4 py-6 sm:px-6 lg:pl-80 lg:pr-8 lg:py-8">
        <section class="mb-6 rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm lg:p-8">
            <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Marketing</p>
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight lg:text-4xl">Marketing Performance</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-600 sm:text-base">
                        The place for ads, landing pages, mailing campaigns, and social content. This first pass uses CRM lead attribution; ad spend sync is marked separately until Meta and Google insights are connected.
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="<?= e(base_url('landing_pages.php')) ?>" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-5 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-100">Landing Pages</a>
                    <?php if (is_file(__DIR__ . '/patient-mailings.php')): ?>
                        <a href="<?= e(base_url('patient-mailings.php')) ?>" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-5 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-100">Mailing Campaigns</a>
                    <?php endif; ?>
                    <a href="<?= e(base_url('social-studio.php')) ?>" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">Social Studio</a>
                </div>
            </div>
        </section>

        <section class="mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">30-Day Leads</p>
                <p class="mt-3 text-3xl font-semibold"><?= e(marketing_int(array_sum(array_map(static fn(array $row): int => (int)($row['leads_count'] ?? 0), $sourceRows)))) ?></p>
                <p class="mt-2 text-sm text-slate-500">Attributed in CRM</p>
            </div>
            <div class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Estimated Pipeline</p>
                <p class="mt-3 text-3xl font-semibold"><?= e(marketing_money($stats['pipeline_value_total'] ?? 0)) ?></p>
                <p class="mt-2 text-sm text-slate-500">Open CRM opportunity</p>
            </div>
            <div class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Landing Pages</p>
                <p class="mt-3 text-3xl font-semibold"><?= e((string)(int)($landingPageTotals['active_pages'] ?? 0)) ?></p>
                <p class="mt-2 text-sm text-slate-500"><?= e((string)(int)($landingPageTotals['total_pages'] ?? 0)) ?> total pages</p>
            </div>
            <div class="rounded-[1.5rem] border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-amber-700">Ad Spend Sync</p>
                <p class="mt-3 text-lg font-semibold text-amber-950">Not connected yet</p>
                <p class="mt-2 text-sm leading-6 text-amber-800">Meta/Google spend and CPL need the next integration pass.</p>
            </div>
        </section>

        <section class="mb-6 grid gap-5 xl:grid-cols-[1.25fr_0.75fr]">
            <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Ads and Campaigns</p>
                        <h2 class="mt-2 text-xl font-semibold">CRM lead results by source</h2>
                    </div>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">Last 30 days</span>
                </div>

                <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-[11px] uppercase tracking-[0.16em] text-slate-500">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Source / Campaign</th>
                                <th class="px-4 py-3 text-right font-semibold">Leads</th>
                                <th class="px-4 py-3 text-right font-semibold">Booked</th>
                                <th class="px-4 py-3 text-right font-semibold">Won</th>
                                <th class="px-4 py-3 text-right font-semibold">Value</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <?php if (empty($sourceRows)): ?>
                                <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No attributed leads found for the last 30 days.</td></tr>
                            <?php else: ?>
                                <?php foreach ($sourceRows as $row): ?>
                                    <tr>
                                        <td class="px-4 py-3">
                                            <p class="font-semibold text-slate-900"><?= e((string)($row['source_name'] ?? 'Unknown')) ?></p>
                                            <p class="mt-1 text-xs text-slate-500"><?= e((string)($row['campaign_name'] ?? '')) ?></p>
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold"><?= e((string)(int)($row['leads_count'] ?? 0)) ?></td>
                                        <td class="px-4 py-3 text-right"><?= e((string)(int)($row['booked_count'] ?? 0)) ?></td>
                                        <td class="px-4 py-3 text-right"><?= e((string)(int)($row['won_count'] ?? 0)) ?></td>
                                        <td class="px-4 py-3 text-right font-semibold"><?= e(marketing_money($row['estimated_value'] ?? 0)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid gap-5">
                <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Marketing Tools</p>
                    <div class="mt-4 grid gap-3">
                        <a href="<?= e(base_url('landing_pages.php')) ?>" class="rounded-2xl border border-slate-200 p-4 transition hover:bg-slate-50">
                            <p class="font-semibold">Landing Pages</p>
                            <p class="mt-1 text-sm text-slate-500"><?= e((string)(int)($landingPageTotals['active_pages'] ?? 0)) ?> active pages</p>
                        </a>
                        <?php if (is_file(__DIR__ . '/patient-mailings.php')): ?>
                            <a href="<?= e(base_url('patient-mailings.php')) ?>" class="rounded-2xl border border-slate-200 p-4 transition hover:bg-slate-50">
                                <p class="font-semibold">Mailing Campaigns</p>
                                <p class="mt-1 text-sm text-slate-500"><?= e((string)$mailingCounts['review']) ?> drafts/review, <?= e((string)$mailingCounts['contacts']) ?> subscribed contacts</p>
                            </a>
                        <?php endif; ?>
                        <a href="<?= e(base_url('social-studio.php')) ?>" class="rounded-2xl border border-slate-200 p-4 transition hover:bg-slate-50">
                            <p class="font-semibold">Social Studio</p>
                            <p class="mt-1 text-sm text-slate-500"><?= e((string)$socialCounts['drafts']) ?> drafts/review, <?= e((string)$socialCounts['scheduled']) ?> scheduled</p>
                        </a>
                    </div>
                </div>

                <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Live Ads Status</p>
                    <div class="mt-4 space-y-3">
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                            <p class="font-semibold text-amber-950">Meta Ads</p>
                            <p class="mt-1 text-sm leading-6 text-amber-800">Lead webhook works. Spend, status, and placement insights still need Ads API sync.</p>
                        </div>
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                            <p class="font-semibold text-amber-950">Google Ads</p>
                            <p class="mt-1 text-sm leading-6 text-amber-800">Landing-page conversion events exist. Google spend/click data still needs Ads or GA4 connection.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-2">
            <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Landing Pages</p>
                        <h2 class="mt-2 text-xl font-semibold">Lead flow by page</h2>
                    </div>
                    <a href="<?= e(base_url('landing_pages.php')) ?>" class="text-sm font-semibold text-slate-700 hover:text-slate-950">Manage</a>
                </div>
                <div class="mt-5 divide-y divide-slate-100">
                    <?php if (empty($landingRows)): ?>
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">No landing-page-attributed leads in the last 30 days.</div>
                    <?php else: ?>
                        <?php foreach ($landingRows as $row): ?>
                            <div class="flex items-center justify-between gap-4 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold"><?= e((string)$row['landing_page']) ?></p>
                                    <p class="mt-1 text-xs text-slate-500">Last lead: <?= e(!empty($row['last_lead_at']) ? format_datetime((string)$row['last_lead_at'], 'M j g:i A') : 'Unknown') ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold"><?= e((string)(int)$row['leads_count']) ?></p>
                                    <p class="text-xs text-slate-500"><?= e((string)(int)$row['booked_count']) ?> booked</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Recent Marketing Leads</p>
                        <h2 class="mt-2 text-xl font-semibold">Newest attributed leads</h2>
                    </div>
                    <a href="<?= e(base_url('leads.php')) ?>" class="text-sm font-semibold text-slate-700 hover:text-slate-950">Open Leads</a>
                </div>
                <div class="mt-5 divide-y divide-slate-100">
                    <?php if (empty($recentMarketingLeads)): ?>
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">No recent leads to show.</div>
                    <?php else: ?>
                        <?php foreach ($recentMarketingLeads as $lead): ?>
                            <a href="<?= e(base_url('leads.php?lead_id=' . (int)$lead['id'])) ?>" class="block py-3 transition hover:bg-slate-50">
                                <div class="flex items-center justify-between gap-4">
                                    <div class="min-w-0 px-2">
                                        <p class="truncate text-sm font-semibold"><?= e(trim((string)($lead['full_name'] ?? '')) !== '' ? (string)$lead['full_name'] : 'Unnamed Lead') ?></p>
                                        <p class="mt-1 truncate text-xs text-slate-500"><?= e((string)($lead['source_name'] ?? 'Unknown')) ?><?= !empty($lead['campaign_name']) ? ' - ' . e((string)$lead['campaign_name']) : '' ?></p>
                                    </div>
                                    <p class="shrink-0 text-xs text-slate-500"><?= e(!empty($lead['created_at']) ? format_datetime((string)$lead['created_at'], 'M j') : '') ?></p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
