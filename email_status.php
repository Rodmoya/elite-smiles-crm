<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * Lightweight email account status view.
 */

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/core/db.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/core/smtp.php';
require_once __DIR__ . '/app/leads/lead_communications.php';
require_once __DIR__ . '/app/leads/lead_email.php';

require_auth();
lead_comm_ensure_schema();
lead_email_ensure_schema();

if (is_post() && post('action') === 'logout') {
    require_csrf();
    auth_logout();
    flash_set('success', 'You have been logged out.');
    redirect(base_url('login.php'));
}

$currentPage = 'email_status';
$pageTitle = 'Email Status';
$logoutAction = base_url('email_status.php');
$logoUrl = base_url('assets/img/ES-Logo-Stack-500-x-150-px.png');

$direction = strtolower(trim((string)($_GET['direction'] ?? 'all')));
if (!in_array($direction, ['all', 'inbound', 'outbound', 'failed', 'opened'], true)) {
    $direction = 'all';
}

$range = strtolower(trim((string)($_GET['range'] ?? '30')));
if (!in_array($range, ['1', '7', '30', '90'], true)) {
    $range = '30';
}
$rangeDays = (int)$range;

$where = ['e.created_at >= DATE_SUB(NOW(), INTERVAL ' . $rangeDays . ' DAY)'];
$params = [];

if ($direction === 'inbound') {
    $where[] = "e.direction = 'inbound'";
} elseif ($direction === 'outbound') {
    $where[] = "e.direction = 'outbound'";
} elseif ($direction === 'failed') {
    $where[] = "e.status = 'failed'";
} elseif ($direction === 'opened') {
    $where[] = 'e.opened_at IS NOT NULL';
}

$whereSql = implode(' AND ', $where);

$summary = db_one(
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN direction = 'inbound' THEN 1 ELSE 0 END) AS inbound_total,
        SUM(CASE WHEN direction = 'outbound' AND status = 'sent' THEN 1 ELSE 0 END) AS sent_total,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed_total,
        SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) AS opened_total,
        SUM(CASE WHEN direction = 'inbound' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) AS inbound_24h,
        MAX(CASE WHEN direction = 'inbound' THEN created_at ELSE NULL END) AS last_inbound_at,
        MAX(CASE WHEN direction = 'outbound' THEN created_at ELSE NULL END) AS last_outbound_at
     FROM lead_emails
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$rangeDays} DAY)",
    []
) ?? [];

$recentEmails = db_all(
    "SELECT
        e.id,
        e.lead_id,
        e.direction,
        e.from_email,
        e.to_email,
        e.subject,
        e.body,
        e.status,
        e.created_by,
        e.created_at,
        e.opened_at,
        l.full_name,
        l.phone,
        l.status AS lead_status
     FROM lead_emails e
     LEFT JOIN leads l ON l.id = e.lead_id
     WHERE {$whereSql}
     ORDER BY e.created_at DESC, e.id DESC
     LIMIT 80",
    $params
);

$latestInbound = db_all(
    "SELECT
        e.id,
        e.lead_id,
        e.from_email,
        e.subject,
        e.body,
        e.created_at,
        l.full_name,
        l.phone
     FROM lead_emails e
     LEFT JOIN leads l ON l.id = e.lead_id
     WHERE e.direction = 'inbound'
     ORDER BY e.created_at DESC, e.id DESC
     LIMIT 8"
);

function email_status_short_datetime(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return 'None yet';
    }
    $ts = strtotime($value);
    return $ts ? date('M j, g:i A', $ts) : $value;
}

function email_status_badge_class(string $direction, string $status, ?string $openedAt): string
{
    if ($status === 'bounced') {
        return 'border-amber-200 bg-amber-50 text-amber-800';
    }
    if ($status === 'failed') {
        return 'border-rose-200 bg-rose-50 text-rose-700';
    }
    if ($direction === 'inbound') {
        return 'border-blue-200 bg-blue-50 text-blue-700';
    }
    if (trim((string)$openedAt) !== '') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    }
    return 'border-slate-200 bg-slate-50 text-slate-600';
}

function email_status_label(string $direction, string $status, ?string $openedAt): string
{
    if ($status === 'bounced') {
        return 'Bounced';
    }
    if ($status === 'failed') {
        return 'Failed';
    }
    if ($direction === 'inbound') {
        return 'Inbound';
    }
    if (trim((string)$openedAt) !== '') {
        return 'Opened';
    }
    return 'Sent';
}

function email_status_preview(string $body, int $limit = 220): string
{
    $body = trim(preg_replace('/\s+/', ' ', $body) ?? '');
    if ($body === '') {
        return 'No body preview available.';
    }
    return mb_strlen($body) > $limit ? mb_substr($body, 0, $limit - 3) . '...' : $body;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> | Email Status</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="robots" content="noindex,nofollow">
    <meta http-equiv="refresh" content="60">
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <?php require __DIR__ . '/app/partials/crm_sidebar.php'; ?>

    <main class="px-4 py-6 sm:px-6 lg:pl-80 lg:pr-8 lg:py-8">
        <section class="mb-6">
            <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm lg:p-8">
                <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <div class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-600">
                            Email Monitor
                        </div>
                        <h1 class="mt-4 text-3xl font-semibold tracking-tight text-slate-900 lg:text-4xl">Email Status</h1>
                        <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-600">
                            Quick visibility for patient email follow-up. Use this to confirm outbound emails, inbound replies, opens, failures, and lead matching without turning the CRM into a full email inbox.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <a href="<?= e(base_url('email_status.php')) ?>" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100">
                            Refresh
                        </a>
                        <a href="<?= e(base_url('leads.php')) ?>" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800">
                            Open Leads
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Inbound 24h</p>
                <p class="mt-3 text-3xl font-semibold text-slate-900"><?= e((string)(int)($summary['inbound_24h'] ?? 0)) ?></p>
                <p class="mt-2 text-xs text-slate-500">Last: <?= e(email_status_short_datetime($summary['last_inbound_at'] ?? null)) ?></p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Sent</p>
                <p class="mt-3 text-3xl font-semibold text-slate-900"><?= e((string)(int)($summary['sent_total'] ?? 0)) ?></p>
                <p class="mt-2 text-xs text-slate-500">Last: <?= e(email_status_short_datetime($summary['last_outbound_at'] ?? null)) ?></p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">Opened</p>
                <p class="mt-3 text-3xl font-semibold text-slate-900"><?= e((string)(int)($summary['opened_total'] ?? 0)) ?></p>
                <p class="mt-2 text-xs text-slate-500"><?= e((string)$rangeDays) ?> day view</p>
            </div>
            <div class="rounded-2xl border <?= elite_smtp_is_configured() ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' ?> p-5 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] <?= elite_smtp_is_configured() ? 'text-emerald-700' : 'text-amber-700' ?>">Account</p>
                <p class="mt-3 text-lg font-semibold <?= elite_smtp_is_configured() ? 'text-emerald-900' : 'text-amber-900' ?>">
                    <?= elite_smtp_is_configured() ? 'SMTP Ready' : 'SMTP Needs Setup' ?>
                </p>
                <p class="mt-2 truncate text-xs <?= elite_smtp_is_configured() ? 'text-emerald-700' : 'text-amber-700' ?>">
                    <?= e((string)SMTP_FROM_EMAIL) ?>
                </p>
            </div>
        </section>

        <section class="mb-6 grid grid-cols-1 gap-5 xl:grid-cols-[0.95fr_1.05fr]">
            <div class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Inbound Replies</p>
                        <h2 class="mt-2 text-lg font-semibold text-slate-900">Latest Received</h2>
                    </div>
                    <span class="rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700"><?= e((string)(int)($summary['inbound_total'] ?? 0)) ?> total</span>
                </div>
                <div class="mt-4 max-h-[520px] space-y-3 overflow-y-auto pr-2">
                    <?php if (empty($latestInbound)): ?>
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm text-slate-500">
                            No inbound replies have been logged yet.
                        </div>
                    <?php else: ?>
                        <?php foreach ($latestInbound as $email): ?>
                            <?php $leadName = trim((string)($email['full_name'] ?? '')) ?: 'Unmatched lead'; ?>
                            <article class="rounded-2xl border border-blue-100 bg-blue-50/60 px-4 py-4">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-slate-900"><?= e($leadName) ?></p>
                                    <p class="text-xs font-medium text-blue-700"><?= e(email_status_short_datetime($email['created_at'] ?? null)) ?></p>
                                </div>
                                <p class="mt-1 truncate text-xs text-slate-500"><?= e((string)($email['from_email'] ?? '')) ?></p>
                                <p class="mt-3 text-sm font-semibold text-slate-900"><?= e((string)($email['subject'] ?? '(no subject)')) ?></p>
                                <p class="mt-2 text-sm leading-6 text-slate-700"><?= e(email_status_preview((string)($email['body'] ?? ''), 260)) ?></p>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                <form method="GET" class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Email Log</p>
                        <h2 class="mt-2 text-lg font-semibold text-slate-900">Recent Activity</h2>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <select name="direction" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none">
                            <?php foreach (['all' => 'All', 'inbound' => 'Inbound', 'outbound' => 'Outbound', 'opened' => 'Opened', 'failed' => 'Failed'] as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $direction === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="range" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none">
                            <?php foreach (['1' => '24 hours', '7' => '7 days', '30' => '30 days', '90' => '90 days'] as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $range === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Apply</button>
                    </div>
                </form>

                <div class="mt-4 max-h-[620px] space-y-3 overflow-y-auto pr-2">
                    <?php if (empty($recentEmails)): ?>
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm text-slate-500">
                            No email records match this filter.
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentEmails as $email): ?>
                            <?php
                                $leadName = trim((string)($email['full_name'] ?? '')) ?: 'Unmatched lead';
                                $badgeClass = email_status_badge_class((string)$email['direction'], (string)$email['status'], $email['opened_at'] ?? null);
                                $badgeLabel = email_status_label((string)$email['direction'], (string)$email['status'], $email['opened_at'] ?? null);
                            ?>
                            <article class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-900"><?= e($leadName) ?></p>
                                        <p class="mt-1 truncate text-xs text-slate-500">
                                            <?= e((string)$email['from_email']) ?> to <?= e((string)$email['to_email']) ?>
                                        </p>
                                    </div>
                                    <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold <?= e($badgeClass) ?>"><?= e($badgeLabel) ?></span>
                                </div>
                                <p class="mt-3 text-sm font-semibold text-slate-900"><?= e((string)($email['subject'] ?? '(no subject)')) ?></p>
                                <p class="mt-2 text-sm leading-6 text-slate-600"><?= e(email_status_preview((string)($email['body'] ?? ''))) ?></p>
                                <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-slate-400">
                                    <span><?= e(email_status_short_datetime($email['created_at'] ?? null)) ?></span>
                                    <?php if (!empty($email['opened_at'])): ?>
                                        <span>Opened <?= e(email_status_short_datetime($email['opened_at'])) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($email['lead_id'])): ?>
                                        <a href="<?= e(base_url('leads.php')) ?>" class="font-semibold text-blue-700 hover:text-blue-800">View lead #<?= e((string)$email['lead_id']) ?></a>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Configuration</p>
            <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                    <p class="text-[11px] uppercase tracking-[0.18em] text-slate-500">SMTP Host</p>
                    <p class="mt-2 truncate text-sm font-semibold text-slate-900"><?= e(SMTP_HOST !== '' ? (string)SMTP_HOST : 'Not configured') ?></p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                    <p class="text-[11px] uppercase tracking-[0.18em] text-slate-500">IMAP Host</p>
                    <p class="mt-2 truncate text-sm font-semibold text-slate-900"><?= e(IMAP_HOST !== '' ? (string)IMAP_HOST : 'Not configured') ?></p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                    <p class="text-[11px] uppercase tracking-[0.18em] text-slate-500">Auto Refresh</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900">Every 60 seconds</p>
                </div>
            </div>
        </section>
    </main>
</body>
</html>

