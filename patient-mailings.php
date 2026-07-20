<?php
declare(strict_types=1);

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/core/db.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/mailings/mailing_service.php';

require_auth();
mailing_ensure_schema();

if (is_post() && post('action') === 'logout') {
    require_csrf();
    auth_logout();
    flash_set('success', 'You have been logged out.');
    redirect(base_url('login.php'));
}

$user = auth_user() ?: [];
$logoUrl = base_url('assets/img/ES-Logo-Stack-500-x-150-px.png');
$currentPage = 'patient_mailings';
$pageTitle = 'Patient Mailings';
$logoutAction = base_url('patient-mailings.php');
$successMessage = flash_get('success') ?? '';
$errorMessage = flash_get('error') ?? '';
$data = mailing_dashboard_data();
$counts = $data['counts'];
$campaigns = $data['campaigns'];
$contacts = $data['contacts'];
$selected = $data['selected'];
$statusLabels = mailing_status_labels();

function mailing_badge_class(string $status): string
{
    return match ($status) {
        'approved', 'sent' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'review', 'scheduled', 'sending' => 'border-amber-200 bg-amber-50 text-amber-700',
        'paused' => 'border-rose-200 bg-rose-50 text-rose-700',
        default => 'border-slate-200 bg-slate-100 text-slate-600',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> | Patient Mailings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="robots" content="noindex,nofollow">
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <?php require __DIR__ . '/app/partials/crm_sidebar.php'; ?>

    <main class="px-4 py-6 sm:px-6 lg:pl-80 lg:pr-8 lg:py-8">
        <?php if ($successMessage !== ''): ?>
            <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e((string)$successMessage) ?></div>
        <?php endif; ?>
        <?php if ($errorMessage !== ''): ?>
            <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= e((string)$errorMessage) ?></div>
        <?php endif; ?>

        <section class="mb-6 rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm lg:p-8">
            <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Marketing</p>
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight lg:text-4xl">Patient Mailings</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-600 sm:text-base">
                        Create compliant patient newsletters, keep Dentrix-imported patients engaged, and route interested patients back into CRM lead forms.
                    </p>
                </div>
                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-800">
                    Production recommendation: use a domain-authenticated sender like SendGrid for real volume. SMTP is available for small tests.
                </div>
            </div>
        </section>

        <section class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Subscribed</p>
                <p class="mt-2 text-3xl font-semibold"><?= e((string)$counts['contacts']) ?></p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Unsubscribed</p>
                <p class="mt-2 text-3xl font-semibold"><?= e((string)$counts['unsubscribed']) ?></p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Drafts</p>
                <p class="mt-2 text-3xl font-semibold"><?= e((string)$counts['drafts']) ?></p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Sent</p>
                <p class="mt-2 text-3xl font-semibold"><?= e((string)$counts['sent']) ?></p>
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-[1.1fr_0.9fr]">
            <div class="space-y-5">
                <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Create</p>
                        <h2 class="mt-2 text-xl font-semibold">New newsletter draft</h2>
                    </div>
                    <form class="grid gap-4 sm:grid-cols-2" method="POST" action="<?= e(base_url('app/actions/mailing_generate.php')) ?>">
                        <?= csrf_input() ?>
                        <label class="block text-sm font-semibold">Campaign goal
                            <select name="goal" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-3">
                                <option value="education">Educational newsletter</option>
                                <option value="financing">0% financing announcement</option>
                                <option value="veneers">Veneers consult push</option>
                                <option value="reactivation">Patient reactivation</option>
                                <option value="seasonal">Seasonal smile update</option>
                            </select>
                        </label>
                        <label class="block text-sm font-semibold">CTA destination
                            <input name="cta_hint" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-3" value="<?= e(base_url('l/veneers-draper-google-v2?utm_source=patient_mailings&utm_medium=email')) ?>">
                        </label>
                        <label class="block text-sm font-semibold sm:col-span-2">Direction for OpenAI
                            <textarea name="instruction" rows="4" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-3" placeholder="Example: announce 0% interest financing for qualified patients, premium but friendly, invite them to request a veneers consult."></textarea>
                        </label>
                        <div class="rounded-2xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm leading-6 text-blue-800 sm:col-span-2">
                            OpenAI creates the subject, preview text, newsletter copy, CTA, and Nano Banana image prompt. The CRM template adds logo, unsubscribe, tracking, and business address.
                        </div>
                        <div class="sm:col-span-2">
                            <button class="rounded-2xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white" type="submit">Generate Newsletter Draft</button>
                        </div>
                    </form>
                </section>

                <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Campaigns</p>
                            <h2 class="mt-2 text-xl font-semibold">Drafts and sent campaigns</h2>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <?php if ($campaigns === []): ?>
                            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">No mailing campaigns yet.</div>
                        <?php endif; ?>
                        <?php foreach ($campaigns as $campaign): ?>
                            <?php $status = (string)$campaign['status']; ?>
                            <?php $campaignImageUrl = mailing_campaign_image_url($campaign); ?>
                            <article class="rounded-2xl border border-slate-200 p-4">
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="flex min-w-0 gap-4">
                                        <?php if ($campaignImageUrl !== ''): ?>
                                            <img src="<?= e($campaignImageUrl) ?>" alt="" class="hidden h-20 w-20 shrink-0 rounded-2xl object-cover sm:block">
                                        <?php else: ?>
                                            <div class="hidden h-20 w-20 shrink-0 rounded-2xl bg-gradient-to-br from-slate-950 via-slate-700 to-amber-300 sm:block"></div>
                                        <?php endif; ?>
                                        <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="font-semibold"><?= e((string)$campaign['title']) ?></h3>
                                            <span class="<?= e(mailing_badge_class($status)) ?> rounded-full border px-2.5 py-1 text-[11px] font-semibold"><?= e($statusLabels[$status] ?? $status) ?></span>
                                        </div>
                                        <p class="mt-1 text-sm text-slate-500"><?= e((string)$campaign['subject']) ?></p>
                                        <p class="mt-2 text-sm leading-6 text-slate-600"><?= e(str_limit(strip_tags((string)$campaign['body_html']), 180)) ?></p>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-2 lg:justify-end">
                                        <form method="POST" action="<?= e(base_url('app/actions/mailing_generate_image.php')) ?>">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="campaign_id" value="<?= e((string)$campaign['id']) ?>">
                                            <button class="rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700" type="submit"><?= mailing_campaign_image_url($campaign) !== '' ? 'Regenerate Image' : 'Generate Image' ?></button>
                                        </form>
                                        <?php if (!in_array($status, ['approved', 'sent', 'sending'], true)): ?>
                                            <form method="POST" action="<?= e(base_url('app/actions/mailing_status.php')) ?>">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="campaign_id" value="<?= e((string)$campaign['id']) ?>">
                                                <input type="hidden" name="status" value="approved">
                                                <button class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700" type="submit">Approve</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" action="<?= e(base_url('app/actions/mailing_send_test.php')) ?>" class="flex gap-2">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="campaign_id" value="<?= e((string)$campaign['id']) ?>">
                                            <input name="to" class="w-48 rounded-xl border border-slate-300 px-3 py-2 text-xs" placeholder="test@email.com">
                                            <button class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold" type="submit">Test</button>
                                        </form>
                                        <?php if (in_array($status, ['approved', 'review'], true)): ?>
                                            <form method="POST" action="<?= e(base_url('app/actions/mailing_send.php')) ?>" onsubmit="return confirm('Send this mailing to subscribed contacts now?');">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="campaign_id" value="<?= e((string)$campaign['id']) ?>">
                                                <button class="rounded-xl bg-slate-950 px-3 py-2 text-xs font-semibold text-white" type="submit">Send</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>

            <aside class="space-y-5">
                <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Audience</p>
                        <h2 class="mt-2 text-xl font-semibold">Import contacts</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-500">Paste CSV lines from Dentrix export or a small test list. Format: name,email,phone.</p>
                    </div>
                    <form method="POST" action="<?= e(base_url('app/actions/mailing_import_contacts.php')) ?>" class="space-y-3">
                        <?= csrf_input() ?>
                        <select name="source" class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm">
                            <option value="dentrix_import">Dentrix import</option>
                            <option value="manual">Manual list</option>
                        </select>
                        <textarea name="contacts" rows="6" class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm" placeholder="Jane Patient,jane@email.com,8015551212"></textarea>
                        <button class="w-full rounded-2xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white" type="submit">Import / Update Contacts</button>
                    </form>
                </section>

                <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Recent contacts</p>
                    </div>
                    <div class="space-y-3">
                        <?php if ($contacts === []): ?>
                            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">No contacts imported yet.</div>
                        <?php endif; ?>
                        <?php foreach ($contacts as $contact): ?>
                            <div class="rounded-2xl border border-slate-200 px-4 py-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold"><?= e((string)($contact['full_name'] ?: $contact['email'])) ?></p>
                                        <p class="truncate text-xs text-slate-500"><?= e((string)$contact['email']) ?></p>
                                    </div>
                                    <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-semibold text-slate-600"><?= e((string)$contact['opt_status']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Conversion path</p>
                    <p class="mt-3 text-sm leading-6 text-slate-600">
                        Newsletter CTA clicks are tracked, then forwarded to the selected CRM landing page. If a current patient submits that form for veneers, implants, or another procedure, they enter the existing lead pipeline.
                    </p>
                </section>
            </aside>
        </section>
    </main>
</body>
</html>
