<?php
declare(strict_types=1);

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/core/db.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/mailings/mailing_service.php';

require_marketing_access();

if (is_post() && post('action') === 'logout') {
    require_csrf();
    auth_logout();
    flash_set('success', 'You have been logged out.');
    redirect(base_url('login.php'));
}

$user = auth_user() ?: [];
$logoUrl = base_url('assets/img/ES-Logo-Stack-500-x-150-px.png');
$currentPage = 'patient_mailings';
$pageTitle = 'Mailing Campaigns';
$logoutAction = base_url('patient-mailings.php');
$successMessage = flash_get('success') ?? '';
$errorMessage = flash_get('error') ?? '';
$selectedCampaignId = isset($_GET['campaign_id']) ? max(0, (int)$_GET['campaign_id']) : 0;
$mailingAvailable = true;

try {
    mailing_ensure_schema();
    $data = mailing_dashboard_data($selectedCampaignId);
} catch (Throwable $e) {
    $mailingAvailable = false;
    $data = [
        'counts' => ['contacts' => 0, 'unsubscribed' => 0, 'drafts' => 0, 'sent' => 0],
        'campaigns' => [],
        'contacts' => [],
        'selected' => null,
    ];
    esm_log('mailings', 'Patient mailings page unavailable.', [
        'error_class' => get_class($e),
        'error' => $e->getMessage(),
    ]);
}

$counts = $data['counts'];
$campaigns = $data['campaigns'];
$contacts = $data['contacts'];
$selected = $data['selected'];
$statusLabels = mailing_status_labels();
$audienceOptions = mailing_audience_options();
$health = mailing_system_health($mailingAvailable);

function mailing_badge_class(string $status): string
{
    return match ($status) {
        'approved', 'sent' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'review', 'scheduled', 'sending' => 'border-amber-200 bg-amber-50 text-amber-900',
        'paused' => 'border-rose-200 bg-rose-50 text-rose-800',
        default => 'border-slate-200 bg-slate-100 text-slate-700',
    };
}

function mailing_health_label(bool $ready): string
{
    return $ready ? 'Ready' : 'Needs setup';
}

$selectedStatus = (string)($selected['status'] ?? '');
$selectedAudience = mailing_normalize_audience((string)($selected['audience_filter'] ?? 'all_subscribed'));
$selectedAudienceCount = $mailingAvailable && $selected ? mailing_audience_count($selectedAudience) : 0;
$selectedLocked = in_array($selectedStatus, ['sending', 'sent'], true);
$canSendSelected = $selected !== null
    && in_array($selectedStatus, ['approved', 'scheduled', 'sending'], true)
    && $selectedAudienceCount > 0
    && !empty($health['sender']);
$mailingTimeOptions = [];
for ($hour = 0; $hour < 24; $hour++) {
    foreach ([0, 30] as $minute) {
        $value = sprintf('%02d:%02d', $hour, $minute);
        $mailingTimeOptions[$value] = date('g:i A', strtotime($value));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> | Mailing Campaigns</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="robots" content="noindex,nofollow">
    <style>
        :focus-visible { outline: 3px solid #94a3b8; outline-offset: 2px; }
        [data-mailing-busy][aria-hidden="false"] { opacity: 1; pointer-events: auto; }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { scroll-behavior: auto !important; transition-duration: 0.01ms !important; } }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-950 antialiased">
    <?php require __DIR__ . '/app/partials/crm_sidebar.php'; ?>

    <div data-mailing-busy aria-hidden="true" class="fixed inset-0 z-[120] grid place-items-center bg-slate-950/45 opacity-0 backdrop-blur-sm transition-opacity">
        <div class="flex min-w-64 items-center gap-4 rounded-2xl bg-white px-5 py-4 shadow-2xl" role="status" aria-live="polite">
            <span class="h-6 w-6 animate-spin rounded-full border-2 border-slate-300 border-t-slate-950" aria-hidden="true"></span>
            <div><p class="text-sm font-semibold">Working on your campaign</p><p class="mt-1 text-xs text-slate-500">Please keep this page open.</p></div>
        </div>
    </div>

    <?php if ($successMessage !== '' || $errorMessage !== ''): ?>
        <div class="fixed left-1/2 top-5 z-[110] w-[calc(100%-2rem)] max-w-xl -translate-x-1/2" role="status" aria-live="polite">
            <div class="rounded-2xl border px-5 py-4 shadow-xl <?= $errorMessage !== '' ? 'border-rose-200 bg-rose-50 text-rose-900' : 'border-emerald-200 bg-emerald-50 text-emerald-900' ?>">
                <p class="text-sm font-semibold"><?= e((string)($errorMessage !== '' ? $errorMessage : $successMessage)) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <main class="px-4 pb-28 pt-6 sm:px-6 lg:pb-8 lg:pl-80 lg:pr-8 lg:pt-8">
        <header class="mx-auto max-w-[1500px]">
            <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm lg:p-8">
                <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                    <div class="max-w-3xl">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Patient engagement</p>
                        <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950 lg:text-4xl">Mailing Campaigns</h1>
                        <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-600 sm:text-base">Create, review, test, and send polished patient emails without leaving the CRM.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4" aria-label="System readiness">
                        <?php foreach (['database' => 'Database', 'sender' => 'Email sender', 'copy_ai' => 'OpenAI', 'image_ai' => 'Images'] as $key => $label): ?>
                            <?php $ready = !empty($health[$key]); ?>
                            <div class="min-w-28 rounded-xl border px-3 py-2 <?= $ready ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' ?>">
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500"><?= e($label) ?></p>
                                <p class="mt-1 flex items-center gap-1.5 text-xs font-semibold <?= $ready ? 'text-emerald-800' : 'text-amber-900' ?>"><span class="h-2 w-2 rounded-full <?= $ready ? 'bg-emerald-500' : 'bg-amber-500' ?>" aria-hidden="true"></span><?= e(mailing_health_label($ready)) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </header>

        <div class="mx-auto mt-5 max-w-[1500px]">
            <?php if (!$mailingAvailable): ?>
                <section class="rounded-[2rem] border border-amber-200 bg-white p-6 shadow-sm lg:p-10" aria-labelledby="mailing-repair-title">
                    <div class="mx-auto max-w-2xl text-center">
                        <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-amber-100 text-amber-900" aria-hidden="true">
                            <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4m0 4h.01M10.3 3.6 2.4 17.3A2 2 0 0 0 4.1 20h15.8a2 2 0 0 0 1.7-2.7L13.7 3.6a2 2 0 0 0-3.4 0Z"/></svg>
                        </span>
                        <p class="mt-5 text-xs font-semibold uppercase tracking-[0.2em] text-amber-800">Setup interrupted</p>
                        <h2 id="mailing-repair-title" class="mt-2 text-2xl font-semibold tracking-tight">The mailing workspace could not connect to its tables</h2>
                        <p class="mt-3 text-sm leading-7 text-slate-600">No campaign was sent and no patient data was changed. Refresh after the database repair is deployed; the workspace will initialize itself automatically.</p>
                        <a href="<?= e(base_url('patient-mailings.php')) ?>" class="mt-6 inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-950 px-5 text-sm font-semibold text-white transition hover:bg-slate-800">Check again</a>
                    </div>
                </section>
            <?php else: ?>
                <nav class="mb-5 grid gap-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-sm sm:grid-cols-3" aria-label="Campaign workflow">
                    <a href="#create" class="flex min-h-14 items-center gap-3 rounded-xl px-4 transition hover:bg-slate-50"><span class="grid h-8 w-8 place-items-center rounded-full bg-slate-950 text-xs font-semibold text-white">1</span><span><span class="block text-sm font-semibold">Create</span><span class="block text-xs text-slate-500">Choose the message</span></span></a>
                    <a href="#review" class="flex min-h-14 items-center gap-3 rounded-xl px-4 transition hover:bg-slate-50"><span class="grid h-8 w-8 place-items-center rounded-full bg-slate-100 text-xs font-semibold text-slate-700">2</span><span><span class="block text-sm font-semibold">Review & test</span><span class="block text-xs text-slate-500">Approve every detail</span></span></a>
                    <a href="#audience" class="flex min-h-14 items-center gap-3 rounded-xl px-4 transition hover:bg-slate-50"><span class="grid h-8 w-8 place-items-center rounded-full bg-slate-100 text-xs font-semibold text-slate-700">3</span><span><span class="block text-sm font-semibold">Audience & send</span><span class="block text-xs text-slate-500"><?= e((string)$counts['contacts']) ?> subscribed contacts</span></span></a>
                </nav>

                <section class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Mailing totals">
                    <?php foreach ([['Subscribed', $counts['contacts'], 'Ready to receive'], ['Unsubscribed', $counts['unsubscribed'], 'Automatically excluded'], ['In review', $counts['drafts'], 'Awaiting approval'], ['Sent', $counts['sent'], 'Completed campaigns']] as [$label, $value, $help]): ?>
                        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500"><?= e((string)$label) ?></p><p class="mt-2 text-3xl font-semibold tabular-nums"><?= e((string)$value) ?></p><p class="mt-1 text-xs text-slate-500"><?= e((string)$help) ?></p></article>
                    <?php endforeach; ?>
                </section>

                <div class="grid gap-5 xl:grid-cols-[360px_minmax(0,1fr)]">
                    <aside class="space-y-5">
                        <section id="create" class="scroll-mt-5 rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm lg:p-6">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Step 1</p>
                            <h2 class="mt-2 text-xl font-semibold">Create a campaign</h2>
                            <p class="mt-2 text-sm leading-6 text-slate-600">Give OpenAI the goal and direction. Nothing sends automatically.</p>
                            <form class="mt-5 space-y-4" method="POST" action="<?= e(base_url('app/actions/mailing_generate.php')) ?>" data-mailing-form>
                                <?= csrf_input() ?>
                                <label class="block text-sm font-semibold" for="mailing-goal">Campaign goal</label>
                                <select id="mailing-goal" name="goal" class="min-h-12 w-full rounded-xl border border-slate-300 bg-white px-3 text-base sm:text-sm">
                                    <option value="education">Educational newsletter</option><option value="financing">Financing announcement</option><option value="veneers">Veneers consultation</option><option value="reactivation">Patient reactivation</option><option value="seasonal">Seasonal smile update</option>
                                </select>
                                <label class="block text-sm font-semibold" for="mailing-audience">Audience</label>
                                <select id="mailing-audience" name="audience_filter" class="min-h-12 w-full rounded-xl border border-slate-300 bg-white px-3 text-base sm:text-sm"><?php foreach ($audienceOptions as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select>
                                <label class="block text-sm font-semibold" for="mailing-direction">Direction <span class="font-normal text-slate-500">(optional)</span></label>
                                <textarea id="mailing-direction" name="instruction" rows="5" class="w-full rounded-xl border border-slate-300 px-3 py-3 text-base leading-6 sm:text-sm" placeholder="Example: A useful summer veneers education email. Premium, natural, and conversational."></textarea>
                                <label class="block text-sm font-semibold" for="mailing-cta-hint">Button destination</label>
                                <input id="mailing-cta-hint" name="cta_hint" type="url" inputmode="url" class="min-h-12 w-full rounded-xl border border-slate-300 px-3 text-base sm:text-sm" value="<?= e(base_url('l/veneers-draper-google-v2?utm_source=patient_mailings&utm_medium=email')) ?>">
                                <button class="min-h-12 w-full rounded-xl bg-slate-950 px-5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-wait disabled:opacity-50" type="submit">Create complete AI campaign</button>
                                <p class="text-xs leading-5 text-slate-500">OpenAI writes the campaign, Nano Banana creates the image, and the CRM assembles the branded email. Nothing sends automatically.</p>
                            </form>
                        </section>

                        <section id="audience" class="scroll-mt-5 rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm lg:p-6">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Step 3</p>
                            <h2 class="mt-2 text-xl font-semibold">Audience</h2>
                            <p class="mt-2 text-sm leading-6 text-slate-600">Paste CSV rows as <strong>name, email, phone</strong>. Existing unsubscribes remain excluded.</p>
                            <form method="POST" action="<?= e(base_url('app/actions/mailing_import_contacts.php')) ?>" class="mt-5 space-y-3" data-mailing-form>
                                <?= csrf_input() ?>
                                <label class="sr-only" for="mailing-source">Contact source</label>
                                <select id="mailing-source" name="source" class="min-h-12 w-full rounded-xl border border-slate-300 bg-white px-3 text-base sm:text-sm"><option value="dentrix_import">Dentrix export</option><option value="manual">Manual list</option></select>
                                <label class="sr-only" for="mailing-contacts">Contacts</label>
                                <textarea id="mailing-contacts" name="contacts" rows="6" class="w-full rounded-xl border border-slate-300 px-3 py-3 text-base leading-6 sm:text-sm" placeholder="Jane Patient,jane@email.com,8015551212"></textarea>
                                <button class="min-h-12 w-full rounded-xl border border-slate-300 bg-white px-5 text-sm font-semibold transition hover:bg-slate-50" type="submit">Import or update</button>
                            </form>
                            <?php if ($contacts !== []): ?><details class="mt-4"><summary class="min-h-11 cursor-pointer py-3 text-sm font-semibold">Recent contacts</summary><div class="space-y-2"><?php foreach ($contacts as $contact): ?><div class="rounded-xl bg-slate-50 px-3 py-2"><p class="truncate text-sm font-semibold"><?= e((string)($contact['full_name'] ?: $contact['email'])) ?></p><p class="truncate text-xs text-slate-500"><?= e((string)$contact['email']) ?></p></div><?php endforeach; ?></div></details><?php endif; ?>
                        </section>
                    </aside>

                    <div class="min-w-0 space-y-5">
                        <section id="review" class="scroll-mt-5 rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm lg:p-7">
                            <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-start sm:justify-between">
                                <div><p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Step 2</p><h2 class="mt-2 text-xl font-semibold">Review and test</h2><p class="mt-2 text-sm text-slate-600">Edit the exact email, send yourself a test, then approve it.</p></div>
                                <?php if ($selected): ?><span class="<?= e(mailing_badge_class($selectedStatus)) ?> inline-flex w-fit rounded-full border px-3 py-1.5 text-xs font-semibold"><?= e($statusLabels[$selectedStatus] ?? $selectedStatus) ?></span><?php endif; ?>
                            </div>

                            <?php if (!$selected): ?>
                                <div class="grid min-h-80 place-items-center py-12 text-center"><div><span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-slate-100 text-slate-600" aria-hidden="true"><svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5h16v14H4zM4 7l8 6 8-6"/></svg></span><h3 class="mt-4 font-semibold">No campaign selected</h3><p class="mt-2 text-sm text-slate-500">Generate your first draft to begin reviewing.</p></div></div>
                            <?php else: ?>
                                <div class="mt-6 grid gap-6 2xl:grid-cols-[minmax(0,1fr)_minmax(380px,0.9fr)]">
                                    <form method="POST" action="<?= e(base_url('app/actions/mailing_update.php')) ?>" class="space-y-4" data-mailing-form>
                                        <?= csrf_input() ?><input type="hidden" name="campaign_id" value="<?= e((string)$selected['id']) ?>">
                                        <div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-semibold">Internal title<input name="title" value="<?= e((string)$selected['title']) ?>" required <?= $selectedLocked ? 'readonly' : '' ?> class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-3 text-base read-only:bg-slate-100 sm:text-sm"></label><label class="text-sm font-semibold">Subject line<input name="subject" value="<?= e((string)$selected['subject']) ?>" required <?= $selectedLocked ? 'readonly' : '' ?> class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-3 text-base read-only:bg-slate-100 sm:text-sm"></label></div>
                                        <label class="block text-sm font-semibold">Inbox preview<input name="preview_text" value="<?= e((string)$selected['preview_text']) ?>" <?= $selectedLocked ? 'readonly' : '' ?> class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-3 text-base read-only:bg-slate-100 sm:text-sm"></label>
                                        <label class="block text-sm font-semibold">Email headline<input id="mailing-headline-editor" name="hero_title" value="<?= e((string)$selected['hero_title']) ?>" required <?= $selectedLocked ? 'readonly' : '' ?> class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-3 text-base read-only:bg-slate-100 sm:text-sm"></label>
                                        <label class="block text-sm font-semibold">Message<textarea id="mailing-body-editor" name="body_text" rows="9" required <?= $selectedLocked ? 'readonly' : '' ?> class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-3 text-base leading-7 read-only:bg-slate-100 sm:text-sm"><?= e((string)$selected['body_text']) ?></textarea><span class="mt-1 block text-xs font-normal text-slate-500">Write naturally. Blank lines create new paragraphs in the finished email.</span></label>
                                        <label class="block text-sm font-semibold">Audience<select name="audience_filter" <?= $selectedLocked ? 'disabled' : '' ?> class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 bg-white px-3 text-base disabled:bg-slate-100 sm:text-sm"><?php foreach ($audienceOptions as $value => $label): ?><option value="<?= e($value) ?>" <?= $value === $selectedAudience ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                                        <div class="grid gap-4 sm:grid-cols-[0.7fr_1.3fr]"><label class="text-sm font-semibold">Button label<input id="mailing-cta-editor" name="cta_label" value="<?= e((string)$selected['cta_label']) ?>" required <?= $selectedLocked ? 'readonly' : '' ?> class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-3 text-base read-only:bg-slate-100 sm:text-sm"></label><label class="text-sm font-semibold">Button destination<input name="cta_url" type="url" value="<?= e((string)$selected['cta_url']) ?>" required <?= $selectedLocked ? 'readonly' : '' ?> class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-3 text-base read-only:bg-slate-100 sm:text-sm"></label></div>
                                        <?php if (!$selectedLocked): ?><button class="min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold transition hover:bg-slate-50" type="submit">Save changes</button><?php else: ?><p class="rounded-xl bg-slate-100 px-4 py-3 text-xs leading-5 text-slate-600">This campaign is locked because delivery has started. Create a new version to change its content.</p><?php endif; ?>
                                    </form>

                                    <div>
                                        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-[#f6f2eb] shadow-sm">
                                            <div class="bg-slate-950 px-6 py-5 text-center"><img src="<?= e((string)$logoUrl) ?>" alt="Elite Smiles" class="mx-auto h-auto w-44 brightness-0 invert"></div>
                                            <?php $selectedImage = mailing_campaign_image_url($selected); if ($selectedImage !== ''): ?><img src="<?= e($selectedImage) ?>" alt="" class="aspect-[16/8] w-full object-cover" loading="lazy"><?php endif; ?>
                                            <div class="bg-white p-6"><p id="mailing-preview-headline" class="text-2xl font-semibold leading-tight"><?= e((string)$selected['hero_title']) ?></p><div id="mailing-preview-body" class="mt-4 text-sm leading-7 text-slate-600"><?= mailing_sanitize_body_html((string)$selected['body_html']) ?></div><span id="mailing-preview-cta" class="mt-5 inline-flex min-h-11 items-center rounded-xl bg-slate-950 px-5 text-sm font-semibold text-white"><?= e((string)$selected['cta_label']) ?></span></div>
                                            <div class="border-t border-[#e8dfd1] bg-white px-6 py-4 text-[11px] leading-5 text-slate-500">Elite Smiles by Walter Meden DDS · Draper, Utah<br>Every delivered email includes a secure unsubscribe link.</div>
                                        </div>
                                        <div class="mt-4 grid grid-cols-2 gap-2 text-center sm:grid-cols-5"><?php foreach ([['Audience', $selectedAudienceCount], ['Sent', $selected['delivered_count'] ?? 0], ['Opened', $selected['opened_count'] ?? 0], ['Clicked', $selected['clicked_count'] ?? 0], ['Leads', $selected['lead_count'] ?? 0]] as [$metric, $value]): ?><div class="rounded-xl bg-slate-50 px-2 py-3"><p class="text-lg font-semibold tabular-nums"><?= e((string)$value) ?></p><p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500"><?= e($metric) ?></p></div><?php endforeach; ?></div>
                                    </div>
                                </div>

                                <div class="mt-6 flex flex-col gap-3 border-t border-slate-200 pt-5 lg:flex-row lg:items-end lg:justify-between">
                                    <div class="flex flex-wrap gap-2">
                                        <form method="POST" action="<?= e(base_url('app/actions/mailing_generate_image.php')) ?>" data-mailing-form><?= csrf_input() ?><input type="hidden" name="campaign_id" value="<?= e((string)$selected['id']) ?>"><button class="min-h-11 rounded-xl border border-blue-200 bg-blue-50 px-4 text-sm font-semibold text-blue-800 transition hover:bg-blue-100" type="submit"><?= $selectedImage !== '' ? 'Regenerate image' : 'Generate image' ?></button></form>
                                        <?php if (!$selectedLocked && $selectedStatus !== 'approved'): ?><form method="POST" action="<?= e(base_url('app/actions/mailing_status.php')) ?>" data-mailing-form><?= csrf_input() ?><input type="hidden" name="campaign_id" value="<?= e((string)$selected['id']) ?>"><input type="hidden" name="status" value="approved"><button class="min-h-11 rounded-xl border border-emerald-200 bg-emerald-50 px-4 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-100" type="submit">Approve campaign</button></form><?php endif; ?>
                                    </div>
                                    <div class="flex flex-col gap-2 sm:flex-row">
                                        <form method="POST" action="<?= e(base_url('app/actions/mailing_send_test.php')) ?>" class="flex min-w-0 gap-2" data-mailing-form><?= csrf_input() ?><input type="hidden" name="campaign_id" value="<?= e((string)$selected['id']) ?>"><label class="sr-only" for="mailing-test-email">Test email address</label><input id="mailing-test-email" name="to" type="email" required class="min-h-11 min-w-0 flex-1 rounded-xl border border-slate-300 px-3 text-base sm:w-52 sm:text-sm" placeholder="Test email address"><button class="min-h-11 shrink-0 rounded-xl border border-slate-300 px-4 text-sm font-semibold" type="submit">Send test</button></form>
                                        <form method="POST" action="<?= e(base_url('app/actions/mailing_send.php')) ?>" data-mailing-form data-crm-confirm="Send this approved campaign to subscribed contacts now? Unsubscribed contacts are always excluded." data-crm-confirm-label="Send campaign"><?= csrf_input() ?><input type="hidden" name="campaign_id" value="<?= e((string)$selected['id']) ?>"><button <?= $canSendSelected ? '' : 'disabled' ?> title="<?= e($canSendSelected ? 'Send campaign' : 'Approve the campaign, configure the sender, and import subscribed contacts first.') ?>" class="min-h-11 w-full rounded-xl bg-slate-950 px-5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-300" type="submit"><?= $selectedStatus === 'sending' ? 'Continue sending' : 'Send campaign' ?></button></form>
                                    </div>
                                </div>
                                <?php if (in_array($selectedStatus, ['approved', 'scheduled'], true)): ?>
                                    <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                                            <div><p class="text-sm font-semibold">Schedule delivery</p><p class="mt-1 text-xs leading-5 text-slate-500">The publisher checks every five minutes and continues large campaigns in safe batches.</p><?php if ($selectedStatus === 'scheduled' && !empty($selected['scheduled_at'])): ?><p class="mt-2 text-xs font-semibold text-amber-800">Currently scheduled for <?= e(date('M j, Y \a\t g:i A', strtotime((string)$selected['scheduled_at']))) ?></p><?php endif; ?></div>
                                            <form method="POST" action="<?= e(base_url('app/actions/mailing_schedule.php')) ?>" class="grid gap-2 sm:grid-cols-[150px_150px_auto]" data-mailing-form><?= csrf_input() ?><input type="hidden" name="campaign_id" value="<?= e((string)$selected['id']) ?>"><label class="text-xs font-semibold">Date<input name="schedule_date" type="date" min="<?= e(date('Y-m-d')) ?>" required class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm"></label><label class="text-xs font-semibold">Time<select name="schedule_time" required class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm"><?php foreach ($mailingTimeOptions as $value => $label): ?><option value="<?= e($value) ?>" <?= $value === '10:00' ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label><button class="min-h-11 self-end rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold transition hover:bg-slate-100" type="submit"><?= $selectedStatus === 'scheduled' ? 'Reschedule' : 'Schedule' ?></button></form>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </section>

                        <section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm lg:p-7">
                            <div class="flex items-end justify-between gap-4"><div><p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Library</p><h2 class="mt-2 text-xl font-semibold">Campaign history</h2></div><p class="text-xs text-slate-500"><?= e((string)count($campaigns)) ?> recent</p></div>
                            <div class="mt-5 divide-y divide-slate-100">
                                <?php if ($campaigns === []): ?><div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">Your generated campaigns will appear here.</div><?php endif; ?>
                                <?php foreach ($campaigns as $campaign): ?><?php $status=(string)$campaign['status']; $isCurrent=$selected && (int)$selected['id']===(int)$campaign['id']; ?>
                                    <a href="<?= e(base_url('patient-mailings.php?campaign_id=' . (int)$campaign['id'] . '#review')) ?>" class="flex min-h-16 items-center gap-3 rounded-xl px-3 py-3 transition hover:bg-slate-50 <?= $isCurrent ? 'bg-slate-50 ring-1 ring-slate-200' : '' ?>">
                                        <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold"><?= e((string)$campaign['title']) ?></p><p class="mt-1 truncate text-xs text-slate-500"><?= e((string)$campaign['subject']) ?></p></div><span class="<?= e(mailing_badge_class($status)) ?> shrink-0 rounded-full border px-2.5 py-1 text-[11px] font-semibold"><?= e($statusLabels[$status] ?? $status) ?></span><svg viewBox="0 0 24 24" class="h-4 w-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
    (() => {
        const busy = document.querySelector('[data-mailing-busy]');
        document.querySelectorAll('[data-mailing-form]').forEach(form => form.addEventListener('submit', event => {
            if (event.defaultPrevented || !form.checkValidity()) return;
            if (form.dataset.crmConfirm && form.dataset.crmConfirmBypass !== '1') return;
            window.setTimeout(() => {
                busy?.setAttribute('aria-hidden', 'false');
                form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = true; });
            }, 0);
        }));
        const toast = document.querySelector('[role="status"].fixed');
        if (toast) window.setTimeout(() => toast.remove(), 5000);

        const headlineEditor = document.getElementById('mailing-headline-editor');
        const bodyEditor = document.getElementById('mailing-body-editor');
        const ctaEditor = document.getElementById('mailing-cta-editor');
        const previewHeadline = document.getElementById('mailing-preview-headline');
        const previewBody = document.getElementById('mailing-preview-body');
        const previewCta = document.getElementById('mailing-preview-cta');
        const renderBody = () => {
            if (!bodyEditor || !previewBody) return;
            previewBody.replaceChildren();
            const blocks = bodyEditor.value.trim().split(/\n\s*\n/).filter(Boolean);
            blocks.forEach(block => {
                const paragraph = document.createElement('p');
                paragraph.className = 'mt-3 first:mt-0';
                paragraph.textContent = block.replace(/\n+/g, ' ');
                previewBody.appendChild(paragraph);
            });
        };
        headlineEditor?.addEventListener('input', () => { if (previewHeadline) previewHeadline.textContent = headlineEditor.value; });
        ctaEditor?.addEventListener('input', () => { if (previewCta) previewCta.textContent = ctaEditor.value; });
        bodyEditor?.addEventListener('input', renderBody);
    })();
    </script>
</body>
</html>
