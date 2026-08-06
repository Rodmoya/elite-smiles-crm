<?php
declare(strict_types=1);

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/core/db.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/core/mobile_ai_auth.php';
require_once __DIR__ . '/app/core/mobile_ai_push.php';
require_once __DIR__ . '/app/leads/lead_communications.php';
require_once __DIR__ . '/app/leads/lead_email.php';
require_once __DIR__ . '/app/ai/elite_ai_service.php';
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
    if ($token === '') {
        flash_set('error', mobile_ai_has_key()
            ? 'Could not generate the Mobile AI reconnect link right now.'
            : 'APP_KEY is required before Mobile AI reconnect links can be issued.');
    } else {
        $generatedMobileLink = mobile_ai_qr_setup_url($token);
        $generatedMobileQrUrl = mobile_ai_qr_image_url($token);
        $generatedMobileUserLabel = trim(((string)($targetUser['first_name'] ?? '')) . ' ' . ((string)($targetUser['last_name'] ?? '')));
        $generatedMobileUserLabel = $generatedMobileUserLabel !== '' ? $generatedMobileUserLabel : (string)($targetUser['email'] ?? 'Current user');
        flash_set('success', 'Mobile AI reconnect QR generated.');
    }
}

if (is_post() && post('action') === 'clear_stale_ai_drafts') {
    require_csrf();
    $cleared = elite_ai_cancel_stale_pending_drafts_for_user((array)$user, 7);
    flash_set('success', $cleared > 0
        ? 'Cleared ' . $cleared . ' stale Elite AI approval draft' . ($cleared === 1 ? '' : 's') . '.'
        : 'No stale Elite AI approval drafts needed cleanup.');
    redirect(base_url('crm-settings.php'));
}

if (is_post() && post('action') === 'send_mobile_push_test') {
    require_csrf();

    if (!mobile_ai_web_push_ready()) {
        flash_set('error', 'Web push is not fully configured yet.');
        redirect(base_url('crm-settings.php'));
    }

    $pushResult = mobile_ai_send_push_payload([
        'title' => 'Elite AI test',
        'push_body' => 'Rod, this is a live Elite AI push test from CRM Settings.',
        'url' => '/crm/mobile-ai?tab=assistant',
        'badge_count' => function_exists('mobile_ai_unread_badge_count') ? mobile_ai_unread_badge_count() : 0,
    ], (int) ($user['id'] ?? 0));

    if (($pushResult['sent'] ?? 0) > 0) {
        flash_set('success', 'Mobile push test sent to ' . (int) ($pushResult['sent'] ?? 0) . ' device' . ((int) ($pushResult['sent'] ?? 0) === 1 ? '' : 's') . '.');
    } else {
        flash_set('error', 'Mobile push test did not reach a device. Check active subscriptions in the control center.');
    }

    redirect(base_url('crm-settings.php'));
}

$successMessage = flash_get('success') ?? '';
$errorMessage = flash_get('error') ?? '';
$recipients = internal_sms_recipients();
while (count($recipients) < 4) {
    $recipients[] = ['key' => '', 'name' => '', 'phone' => '', 'enabled' => true];
}

lead_comm_ensure_schema();
lead_email_ensure_schema();
elite_ai_ensure_schema();
mobile_ai_ensure_schema();

$controlHealth = [
    'leads' => function_exists('lead_comm_table_exists') ? lead_comm_table_exists('leads') : false,
    'messages' => function_exists('lead_comm_table_exists') ? lead_comm_table_exists('lead_messages') : false,
    'activities' => function_exists('lead_comm_table_exists') ? lead_comm_table_exists('lead_activities') : false,
    'emails' => function_exists('lead_comm_table_exists') ? lead_comm_table_exists('lead_emails') : false,
    'twilio' => defined('TWILIO_ACCOUNT_SID') && TWILIO_ACCOUNT_SID !== '' && defined('TWILIO_AUTH_TOKEN') && TWILIO_AUTH_TOKEN !== '',
    'smtp' => function_exists('elite_smtp_is_configured') ? elite_smtp_is_configured() : false,
    'meta' => defined('META_ACCESS_TOKEN') && META_ACCESS_TOKEN !== '' && defined('META_FORM_IDS') && META_FORM_IDS !== '',
    'pushover' => defined('ELITE_PUSHOVER_APP_TOKEN') && ELITE_PUSHOVER_APP_TOKEN !== '' && defined('ELITE_PUSHOVER_USER_KEY') && ELITE_PUSHOVER_USER_KEY !== '',
];
$mobileRuntimeHealth = [
    'app_key' => function_exists('mobile_ai_has_key') ? mobile_ai_has_key() : false,
    'assistant_token' => function_exists('auth_assistant_token_ready') ? auth_assistant_token_ready() : false,
    'local_qr' => function_exists('mobile_ai_vendor_autoload') ? mobile_ai_vendor_autoload() : is_file(ROOT_PATH . '/vendor/autoload.php'),
    'web_push' => function_exists('mobile_ai_web_push_ready') ? mobile_ai_web_push_ready() : false,
];
$pendingDraftCount = 0;
$staleDraftCount = 0;
$unreadInboundCount = 0;
$deliveryIssueCount = 0;
$recentAiLog = null;
$mobileSessionMetrics = ['total' => 0, 'active' => 0, 'last_seen_at' => ''];
$mobilePushMetrics = ['total' => 0, 'active' => 0, 'last_seen_at' => ''];
try {
    $pendingDraftCount = (int) db_value("SELECT COUNT(*) FROM elite_ai_action_queue WHERE status = 'pending_review'");
    $staleDraftCount = count(elite_ai_stale_pending_drafts_for_user((array)$user, 7, 50));
    $unreadInboundCount = (int) db_value("SELECT COALESCE(SUM(unread_message_count),0) FROM leads");
    $deliveryIssueCount = (int) db_value("SELECT COUNT(DISTINCT lead_id) FROM lead_activities WHERE type = 'sms_delivery_issue' AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)");
    $recentAiLog = db_one("SELECT surface, response_summary, created_at FROM elite_ai_logs ORDER BY created_at DESC, id DESC LIMIT 1");
    $mobileSessionMetrics = db_one(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN revoked_at IS NULL AND (expires_at IS NULL OR expires_at >= NOW()) THEN 1 ELSE 0 END) AS active,
            MAX(last_seen_at) AS last_seen_at
         FROM user_mobile_sessions"
    ) ?: $mobileSessionMetrics;
    $mobilePushMetrics = db_one(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN enabled = 1 AND push_enabled = 1 AND revoked_at IS NULL THEN 1 ELSE 0 END) AS active,
            MAX(last_seen_at) AS last_seen_at
         FROM user_push_subscriptions"
    ) ?: $mobilePushMetrics;
} catch (Throwable $e) {
    esm_log('settings', 'Could not load CRM control center metrics.', ['error' => $e->getMessage()]);
}
$controlOnline = !in_array(false, $controlHealth, true);
$mobileRuntimeOnline = !in_array(false, $mobileRuntimeHealth, true);

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
            <div class="flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">CRM Control Center</h2>
                    <p class="mt-1 text-sm text-slate-500">Live operating status for Elite AI, lead messaging, and CRM automation.</p>
                </div>
                <span class="<?= $controlOnline ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-amber-50 text-amber-700 ring-amber-200' ?> rounded-full px-3 py-1 text-xs font-semibold ring-1">
                    <?= $controlOnline ? 'All core systems online' : 'Needs attention' ?>
                </span>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <?php foreach ([
                    'Tables' => $controlHealth['leads'] && $controlHealth['messages'] && $controlHealth['activities'] && $controlHealth['emails'],
                    'Twilio' => $controlHealth['twilio'],
                    'Email' => $controlHealth['smtp'],
                    'Meta' => $controlHealth['meta'],
                    'Pushover' => $controlHealth['pushover'],
                ] as $label => $ok): ?>
                    <div class="rounded-xl border <?= $ok ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' ?> p-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] <?= $ok ? 'text-emerald-700' : 'text-amber-700' ?>"><?= e($label) ?></p>
                        <p class="mt-1 text-sm font-semibold <?= $ok ? 'text-emerald-950' : 'text-amber-950' ?>"><?= $ok ? 'Working' : 'Check setup' ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Unread Inbound</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950"><?= (int)$unreadInboundCount ?></p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Pending Drafts</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950"><?= (int)$pendingDraftCount ?></p>
                    <?php if ($staleDraftCount > 0): ?>
                        <p class="mt-1 text-xs font-semibold text-amber-700"><?= (int)$staleDraftCount ?> stale</p>
                    <?php endif; ?>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Delivery Issues</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950"><?= (int)$deliveryIssueCount ?></p>
                </div>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <?php foreach ([
                    'APP_KEY' => $mobileRuntimeHealth['app_key'],
                    'Assistant Token' => $mobileRuntimeHealth['assistant_token'],
                    'Local QR' => $mobileRuntimeHealth['local_qr'],
                    'Web Push' => $mobileRuntimeHealth['web_push'],
                ] as $label => $ok): ?>
                    <div class="rounded-xl border <?= $ok ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' ?> p-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] <?= $ok ? 'text-emerald-700' : 'text-amber-700' ?>"><?= e($label) ?></p>
                        <p class="mt-1 text-sm font-semibold <?= $ok ? 'text-emerald-950' : 'text-amber-950' ?>"><?= $ok ? 'Ready' : 'Needs attention' ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-2">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Mobile Sessions</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-950"><?= (int)($mobileSessionMetrics['active'] ?? 0) ?></p>
                            <p class="mt-1 text-xs text-slate-500"><?= (int)($mobileSessionMetrics['total'] ?? 0) ?> total trusted device sessions</p>
                        </div>
                        <span class="<?= (int)($mobileSessionMetrics['active'] ?? 0) > 0 ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-amber-50 text-amber-700 ring-amber-200' ?> rounded-full px-3 py-1 text-xs font-semibold ring-1">
                            <?= (int)($mobileSessionMetrics['active'] ?? 0) > 0 ? 'Active' : 'No active device' ?>
                        </span>
                    </div>
                    <p class="mt-3 text-sm text-slate-600">Last seen: <?= e($mobileSessionMetrics['last_seen_at'] ? format_datetime((string)$mobileSessionMetrics['last_seen_at'], 'M j g:i A') : 'Never') ?></p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Push Devices</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-950"><?= (int)($mobilePushMetrics['active'] ?? 0) ?></p>
                            <p class="mt-1 text-xs text-slate-500"><?= (int)($mobilePushMetrics['total'] ?? 0) ?> saved browser/device subscriptions</p>
                        </div>
                        <span class="<?= (int)($mobilePushMetrics['active'] ?? 0) > 0 ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-amber-50 text-amber-700 ring-amber-200' ?> rounded-full px-3 py-1 text-xs font-semibold ring-1">
                            <?= (int)($mobilePushMetrics['active'] ?? 0) > 0 ? 'Subscribed' : 'No live subscription' ?>
                        </span>
                    </div>
                    <p class="mt-3 text-sm text-slate-600">Last seen: <?= e($mobilePushMetrics['last_seen_at'] ? format_datetime((string)$mobilePushMetrics['last_seen_at'], 'M j g:i A') : 'Never') ?></p>
                </div>
            </div>

            <div class="mt-5 flex flex-col gap-3 rounded-xl border <?= $mobileRuntimeOnline ? 'border-slate-200 bg-slate-50' : 'border-amber-200 bg-amber-50' ?> p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-slate-950">Mobile assistant controls</p>
                    <p class="mt-1 text-sm <?= $mobileRuntimeOnline ? 'text-slate-600' : 'text-amber-800' ?>">
                        <?= $mobileRuntimeOnline
                            ? 'Mobile access, local QR generation, and push delivery are wired up. Use a push test any time you want to confirm a live device is still connected.'
                            : 'One or more mobile assistant dependencies need attention before reconnect and notifications will be fully reliable.' ?>
                    </p>
                </div>
                <form method="POST">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="send_mobile_push_test">
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 transition hover:bg-slate-100">Send Push Test</button>
                </form>
            </div>

            <?php if ($staleDraftCount > 0): ?>
                <div class="mt-5 flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-amber-950">Elite AI has old approval drafts waiting.</p>
                        <p class="mt-1 text-sm text-amber-800">Clear drafts older than 7 days so the assistant chat stays focused on current leads.</p>
                    </div>
                    <form method="POST">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="clear_stale_ai_drafts">
                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-amber-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-amber-800">Clear Stale Drafts</button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($recentAiLog): ?>
                <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Last Elite AI Activity</p>
                    <p class="mt-1 text-sm font-semibold text-slate-950"><?= e(ucfirst((string)($recentAiLog['surface'] ?? 'assistant'))) ?> - <?= e(format_datetime((string)($recentAiLog['created_at'] ?? ''), 'M j g:i A')) ?></p>
                    <p class="mt-2 line-clamp-3 text-sm leading-6 text-slate-600"><?= e((string)($recentAiLog['response_summary'] ?? '')) ?></p>
                </div>
            <?php endif; ?>
        </section>

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
