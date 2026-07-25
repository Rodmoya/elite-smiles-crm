<?php
declare(strict_types=1);

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/core/db.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/core/mobile_ai_auth.php';
require_once __DIR__ . '/app/notifications/internal_sms.php';

require_auth();
require_role('admin', 'marketing_manager');

if (is_post() && post('action') === 'logout') {
    require_csrf();
    auth_logout();
    flash_set('success', 'You have been logged out.');
    redirect(base_url('login.php'));
}

$user = auth_user();
$currentPage = 'settings';
$pageTitle = 'Settings';
$logoutAction = base_url('crm-settings.php');
$logoUrl = base_url('assets/img/ES-Logo-Stack-500-x-150-px.png');
$generatedMobileLink = '';
$generatedMobileQrUrl = '';
$generatedMobileUserLabel = '';

if (is_post() && post('action') === 'save_internal_sms') {
    require_csrf();
    $rows = [];
    $names = $_POST['recipient_name'] ?? [];
    $phones = $_POST['recipient_phone'] ?? [];
    $keys = $_POST['recipient_key'] ?? [];
    $enabled = $_POST['recipient_enabled'] ?? [];
    if (is_array($names) && is_array($phones)) {
        foreach ($names as $index => $name) {
            $rows[] = [
                'key' => is_array($keys) ? (string)($keys[$index] ?? '') : '',
                'name' => (string)$name,
                'phone' => is_array($phones) ? (string)($phones[$index] ?? '') : '',
                'enabled' => is_array($enabled) && array_key_exists((string)$index, $enabled),
            ];
        }
    }
    $saved = internal_sms_save_recipients($rows, (int)($user['id'] ?? 0));
    flash_set('success', 'Internal SMS recipients saved (' . count($saved) . ').');
    redirect(base_url('crm-settings.php'));
}

if (is_post() && post('action') === 'send_internal_sms_test') {
    require_csrf();
    $recipient = internal_sms_find_recipient((string)post('recipient_key'));
    if (!$recipient || empty($recipient['enabled'])) {
        flash_set('error', 'Choose an enabled internal SMS recipient.');
        redirect(base_url('crm-settings.php'));
    }
    $body = 'Elite Smiles CRM internal SMS test. This number is now configured for internal doctor/operator notifications.';
    $result = internal_sms_send($recipient, $body, (int)($user['id'] ?? 0));
    flash_set(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Test complete.'));
    redirect(base_url('crm-settings.php'));
}

if (is_post() && post('action') === 'generate_mobile_qr') {
    require_csrf();

    $targetUserId = (int)(auth_user_id() ?? 0);
    if ($targetUserId <= 0) {
        flash_set('error', 'Could not find the current CRM user.');
        redirect(base_url('crm-settings.php'));
    }

    $targetUser = auth_find_user_by_id($targetUserId) ?: (array)$user;
    $token = mobile_ai_issue_setup_token($targetUserId, $targetUserId);
    $generatedMobileLink = mobile_ai_qr_setup_url($token);
    $generatedMobileQrUrl = mobile_ai_qr_image_url($token);
    $generatedMobileUserLabel = trim(((string)($targetUser['first_name'] ?? '')) . ' ' . ((string)($targetUser['last_name'] ?? '')));
    $generatedMobileUserLabel = $generatedMobileUserLabel !== '' ? $generatedMobileUserLabel : (string)($targetUser['email'] ?? 'Current user');
    flash_set('success', 'Mobile AI reconnect QR generated.');
}

$successMessage = flash_get('success') ?? '';
$errorMessage = flash_get('error') ?? '';
$recipients = internal_sms_recipients();
while (count($recipients) < 4) {
    $recipients[] = ['key' => '', 'name' => '', 'phone' => '', 'enabled' => true];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> | Settings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="robots" content="noindex,nofollow">
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
<?php require __DIR__ . '/app/partials/crm_sidebar_live.php'; ?>

<main class="min-h-screen bg-slate-50 px-4 py-6 sm:px-6 lg:pl-80 lg:pr-8 lg:py-8">
    <div class="mx-auto max-w-5xl">
        <div class="mb-6">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">CRM Settings</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Internal Notifications</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Reusable Twilio recipients for doctor/operator notifications. These are internal-only messages and do not use patient opt-out or lead message rules.</p>
        </div>

        <?php if ($successMessage !== ''): ?>
            <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"><?= e($successMessage) ?></div>
        <?php endif; ?>
        <?php if ($errorMessage !== ''): ?>
            <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <section class="mb-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-200 pb-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">Reconnect Elite AI on iPhone</h2>
                    <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-500">Generate a fresh one-time QR code, scan it with the iPhone Camera, open it in Safari, then add Elite AI back to the Home Screen if needed.</p>
                </div>
                <form method="POST">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="generate_mobile_qr">
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">Generate Reconnect QR</button>
                </form>
            </div>

            <?php if ($generatedMobileLink !== ''): ?>
                <div class="mt-5 grid gap-5 lg:grid-cols-[260px_1fr] lg:items-start">
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <img src="<?= e($generatedMobileQrUrl) ?>" alt="Mobile AI reconnect QR code" class="mx-auto h-auto w-full max-w-[220px]">
                    </div>
                    <div class="space-y-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Reconnect Link</p>
                            <h3 class="mt-1 text-base font-semibold text-slate-950"><?= e($generatedMobileUserLabel) ?></h3>
                            <p class="mt-2 text-sm leading-6 text-slate-500">This link expires in 15 minutes and works one time. On iPhone, scan the QR, open in Safari, then open the Elite AI Home Screen app and allow notifications.</p>
                        </div>
                        <div id="mobile-ai-qr-link" class="break-all rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-700 ring-1 ring-slate-200"><?= e($generatedMobileLink) ?></div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" id="mobile-ai-copy-link" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 transition hover:bg-slate-100">Copy Link</button>
                            <a href="<?= e($generatedMobileLink) ?>" target="_blank" rel="noreferrer" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 transition hover:bg-slate-100">Open Setup Page</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">Internal SMS Recipients</h2>
                    <p class="mt-1 text-sm text-slate-500">Used for handoffs like “send Dr. Meden the follow-up list.”</p>
                </div>
                <span class="<?= internal_sms_twilio_ready() ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-amber-50 text-amber-700 ring-amber-200' ?> rounded-full px-3 py-1 text-xs font-semibold ring-1">
                    <?= internal_sms_twilio_ready() ? 'Twilio ready' : 'Twilio needs setup' ?>
                </span>
            </div>

            <form method="POST" class="mt-5 space-y-3">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="save_internal_sms">
                <?php foreach ($recipients as $index => $recipient): ?>
                    <div class="grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3 md:grid-cols-[1.1fr_1fr_auto] md:items-end">
                        <input type="hidden" name="recipient_key[]" value="<?= e((string)($recipient['key'] ?? '')) ?>">
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Name</span>
                            <input name="recipient_name[]" value="<?= e((string)($recipient['name'] ?? '')) ?>" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Mobile phone</span>
                            <input name="recipient_phone[]" value="<?= e(format_phone_us((string)($recipient['phone'] ?? ''))) ?>" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900">
                        </label>
                        <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700">
                            <input type="checkbox" name="recipient_enabled[<?= (int)$index ?>]" value="1" <?= !empty($recipient['enabled']) ? 'checked' : '' ?>>
                            Enabled
                        </label>
                    </div>
                <?php endforeach; ?>
                <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">Save Internal SMS Settings</button>
            </form>
        </section>

        <section class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-950">Send Test</h2>
            <p class="mt-1 text-sm text-slate-500">Sends a short internal-only Twilio test message to confirm the recipient works.</p>
            <form method="POST" class="mt-4 flex flex-col gap-3 sm:flex-row">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="send_internal_sms_test">
                <select name="recipient_key" class="min-w-0 flex-1 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                    <?php foreach (internal_sms_recipients() as $recipient): ?>
                        <?php if (!empty($recipient['enabled'])): ?>
                            <option value="<?= e((string)$recipient['key']) ?>"><?= e((string)$recipient['name']) ?> - <?= e(format_phone_us((string)$recipient['phone'])) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 transition hover:bg-slate-100">Send Test SMS</button>
            </form>
        </section>
    </div>
</main>
<script>
    (function () {
        var copy = document.getElementById('mobile-ai-copy-link');
        var link = document.getElementById('mobile-ai-qr-link');
        if (!copy || !link) {
            return;
        }
        copy.addEventListener('click', async function () {
            try {
                await navigator.clipboard.writeText((link.textContent || '').trim());
                copy.textContent = 'Copied';
            } catch (error) {
                copy.textContent = 'Copy Failed';
            }
            window.setTimeout(function () {
                copy.textContent = 'Copy Link';
            }, 1600);
        });
    }());
</script>
</body>
</html>
