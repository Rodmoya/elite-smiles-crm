<?php
declare(strict_types=1);

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/core/db.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/core/mobile_ai_auth.php';
require_once __DIR__ . '/app/leads/lead_communications.php';
require_once __DIR__ . '/app/ai/elite_ai_service.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('Referrer-Policy: strict-origin-when-cross-origin');

lead_comm_ensure_schema();
mobile_ai_ensure_schema();
elite_ai_ensure_schema();

if (is_post() && post('action') === 'logout_mobile_ai') {
    mobile_ai_logout_current_session();
    redirect(base_url('login.php'));
}

$mobileUser = mobile_ai_require_user_session();
if (is_post()) {
    $request = json_decode((string) file_get_contents('php://input'), true);
    if (is_array($request) && ($request['action'] ?? '') === 'save_push_subscription') {
        $csrfToken = trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($request['_csrf_token'] ?? '')));
        if (!csrf_validate($csrfToken)) {
            http_response_code(419);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'message' => 'Invalid session token.',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }

        $subscription = (array) ($request['subscription'] ?? []);
        $saved = mobile_ai_save_push_subscription(
            (int) ($mobileUser['id'] ?? 0),
            $subscription,
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 150),
            'Elite AI Home Screen'
        );
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => $saved,
            'message' => $saved ? 'Notifications connected.' : 'Could not save the notification connection.',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (get('notification_feed') === '1') {
    $feedNotifications = elite_ai_notification_rows(20);
    $feedVersion = hash('sha256', json_encode($feedNotifications, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode([
        'ok' => true,
        'version' => $feedVersion,
        'server_time' => now(),
        'poll_after_ms' => 2000,
        'notifications' => $feedNotifications,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$tab = strtolower(trim((string) get('tab', 'assistant')));
if (!in_array($tab, ['assistant', 'notifications'], true)) {
    $tab = 'assistant';
}
$showWelcome = get('welcome') === '1';
$notificationFeedSeed = elite_ai_notification_rows(20);
$notifications = array_slice($notificationFeedSeed, 0, 5);
$notificationVersion = hash('sha256', json_encode($notificationFeedSeed, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
$mobileUnreadCount = count(array_filter($notificationFeedSeed, static fn (array $item): bool => !empty($item['is_new'])));
$fullName = trim(($mobileUser['first_name'] ?? '') . ' ' . ($mobileUser['last_name'] ?? ''));
$firstName = trim((string) ($mobileUser['first_name'] ?? ''));
$displayName = $firstName !== '' ? $firstName : ($fullName !== '' ? $fullName : 'there');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Elite AI</title>
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#ffffff">
    <link rel="manifest" href="<?= e(base_url('mobile-ai/manifest.webmanifest')) ?>">
    <style>
        :root {
            --bg: #f8fafc;
            --panel: #ffffff;
            --ink: #111827;
            --muted: #6b7280;
            --line: #e5e7eb;
            --soft: #f3f4f6;
            --accent: #111827;
            --danger: #b91c1c;
            --shadow: 0 16px 38px rgba(17, 24, 39, 0.08);
            --app-height: 100dvh;
            --app-top: 0px;
        }
        * { box-sizing: border-box; }
        html, body {
            width: 100%;
            height: 100%;
            margin: 0;
            overflow: hidden;
            overscroll-behavior: none;
        }
        body {
            background: var(--bg);
            color: var(--ink);
            font-family: Arial, Helvetica, sans-serif;
        }
        .app {
            position: fixed;
            inset-inline: 0;
            top: var(--app-top);
            width: min(100%, 760px);
            height: var(--app-height);
            min-height: 0;
            margin: 0 auto;
            display: grid;
            grid-template-rows: auto minmax(0, 1fr) auto auto;
            overflow: hidden;
            padding: env(safe-area-inset-top) 14px 0;
        }
        .topbar {
            position: sticky;
            top: 0;
            z-index: 5;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 58px;
            padding: 10px 2px;
            background: rgba(248, 250, 252, 0.92);
            backdrop-filter: blur(14px);
        }
        .title {
            margin: 0;
            font-size: 18px;
            line-height: 1;
            font-weight: 700;
            letter-spacing: 0;
        }
        .icon-btn {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: var(--panel);
            color: var(--ink);
            text-decoration: none;
            box-shadow: 0 6px 18px rgba(17, 24, 39, 0.04);
        }
        .icon-btn.active {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
        }
        .icon-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            display: inline-flex;
            min-width: 20px;
            height: 20px;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--bg);
            border-radius: 999px;
            padding: 0 5px;
            background: #dc2626;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            line-height: 1;
        }
        .thread {
            display: flex;
            flex-direction: column;
            gap: 12px;
            min-height: 0;
            padding: 12px 0 18px;
            overflow-x: hidden;
            overflow-y: auto;
            overscroll-behavior: contain;
            scroll-padding-bottom: 12px;
        }
        .hidden {
            display: none !important;
        }
        .message {
            max-width: 88%;
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 12px 14px;
            background: var(--panel);
            box-shadow: 0 8px 24px rgba(17, 24, 39, 0.04);
        }
        .message.user {
            align-self: flex-end;
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
        }
        .message.assistant {
            align-self: flex-start;
        }
        .message.loading {
            color: var(--muted);
        }
        .message p {
            margin: 0;
            font-size: 15px;
            line-height: 1.5;
            white-space: pre-line;
        }
        .welcome {
            max-width: 100%;
            border-color: #cbd5e1;
            background: #fff;
        }
        .cards {
            display: grid;
            gap: 8px;
            margin-top: 10px;
        }
        .quick-actions {
            display: none;
            flex-wrap: wrap;
            gap: 8px;
            margin: 8px 0 10px;
        }
        .quick-actions.open {
            display: flex;
        }
        .quick-actions-toggle {
            appearance: none;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.92);
            border-radius: 999px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            padding: 7px 11px;
            line-height: 1;
        }
        .quick-actions-shell {
            margin-bottom: 8px;
        }
        .pending-drafts {
            display: none;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: #fff;
            padding: 8px 10px 10px;
            margin-top: 8px;
        }
        .notification-enable {
            display: none;
            border: 1px solid #bfdbfe;
            border-radius: 14px;
            background: #eff6ff;
            color: #1e3a8a;
            padding: 10px 12px;
            margin: 0 0 8px;
            font-size: 13px;
            line-height: 1.4;
        }
        .notification-enable.open {
            display: block;
        }
        .notification-enable button {
            appearance: none;
            border: 0;
            border-radius: 999px;
            background: #1d4ed8;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            padding: 8px 11px;
            margin-top: 8px;
        }
        .pending-drafts-title {
            margin: 0 2px 8px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .pending-drafts-list {
            display: grid;
            gap: 8px;
        }
        .draft-status {
            margin: 6px 0 0;
            border: 1px solid #a7f3d0;
            border-radius: 12px;
            padding: 6px 10px;
            font-size: 11px;
            color: #065f46;
            background: #ecfdf5;
        }
        .draft-card {
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #f9fafb;
            padding: 10px;
            margin-top: 8px;
        }
        .draft-card-title {
            margin: 0 0 6px;
            color: var(--ink);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .draft-card-body {
            margin: 0;
            color: var(--ink);
            font-size: 13px;
            line-height: 1.45;
            white-space: pre-line;
        }
        .draft-card-meta {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.35;
        }
        .draft-card-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }
        .draft-action-btn {
            appearance: none;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: #fff;
            color: var(--ink);
            padding: 6px 10px;
            font-size: 11px;
            font-weight: 600;
            line-height: 1;
        }
        .quick-action-btn {
            appearance: none;
            border: 1px solid var(--line);
            background: #ffffff;
            border-radius: 999px;
            color: var(--ink);
            font-size: 12px;
            font-weight: 600;
            padding: 8px 12px;
            line-height: 1;
        }
        .action-btn {
            appearance: none;
            border: 1px solid var(--line);
            background: #f9fafb;
            color: var(--ink);
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
            padding: 7px 10px;
            margin-right: 6px;
            margin-top: 6px;
        }
        .result-card {
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #f9fafb;
            padding: 10px 12px;
        }
        .result-card summary {
            cursor: pointer;
            list-style: none;
        }
        .result-card summary::-webkit-details-marker {
            display: none;
        }
        .result-card-title {
            margin: 0;
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0;
        }
        .result-card-title::after {
            content: " tap";
            color: #94a3b8;
            font-weight: 600;
            text-transform: none;
        }
        .result-card[open] .result-card-title {
            margin-bottom: 6px;
        }
        .result-card[open] .result-card-title::after {
            content: "";
        }
        .result-card ul {
            margin: 0;
            padding-left: 18px;
            font-size: 13px;
            line-height: 1.45;
        }
        .composer-wrap {
            position: relative;
            z-index: 4;
            padding: 10px 0 calc(env(safe-area-inset-bottom) + 10px);
            background: linear-gradient(180deg, rgba(248,250,252,0), rgba(248,250,252,0.96) 24%, #f8fafc);
        }
        .composer {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 8px;
            align-items: center;
            border: 1px solid var(--line);
            border-radius: 18px;
            background: var(--panel);
            padding: 8px;
            box-shadow: var(--shadow);
        }
        .composer input {
            min-width: 0;
            height: 42px;
            border: 0;
            outline: 0;
            background: transparent;
            color: var(--ink);
            font-size: 16px;
            padding: 0 8px;
        }
        .composer button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: #fff;
            color: var(--ink);
            cursor: pointer;
        }
        .composer button[type="submit"] {
            border-color: var(--accent);
            background: var(--accent);
            color: #fff;
        }
        body.keyboard-open .quick-actions-shell {
            display: none;
        }
        body.keyboard-open .composer-wrap {
            padding-top: 6px;
            padding-bottom: 6px;
            background: var(--bg);
        }
        body.keyboard-open .thread {
            padding-top: 8px;
            padding-bottom: 8px;
        }
        .notifications {
            display: grid;
            gap: 10px;
            padding: 12px 0 22px;
        }
        .notification {
            display: block;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: var(--panel);
            padding: 12px 14px;
            color: inherit;
            text-decoration: none;
        }
        .notification.unread {
            border-color: #cbd5e1;
            background: #ffffff;
            color: var(--ink);
        }
        .notification.read {
            border-color: #e5e7eb;
            background: #f8fafc;
            color: #94a3b8;
        }
        .notification.read h2,
        .notification.read p,
        .notification.read .meta,
        .notification.read .open-link {
            color: #94a3b8;
        }
        .notification h2 {
            margin: 0;
            font-size: 14px;
            line-height: 1.35;
        }
        .notification p {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.45;
        }
        .notification .meta {
            font-size: 12px;
        }
        .notification-state {
            display: inline-flex;
            margin-left: 6px;
            border: 1px solid currentColor;
            border-radius: 999px;
            padding: 2px 7px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            vertical-align: middle;
        }
        .open-link {
            display: inline-flex;
            margin-top: 10px;
            color: var(--ink);
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }
        .notification .open-link {
            pointer-events: none;
        }
        .empty {
            color: var(--muted);
            font-size: 14px;
            padding: 24px 4px;
        }
    </style>
</head>
<body>
    <main class="app">
        <header class="topbar">
            <h1 class="title">Elite AI</h1>
            <a id="mobile-notifications-link" class="icon-btn <?= $tab === 'notifications' ? 'active' : '' ?>" href="<?= e(base_url($tab === 'notifications' ? 'mobile-ai/?tab=assistant' : 'mobile-ai/?tab=notifications')) ?>" aria-label="<?= $tab === 'notifications' ? 'Back to assistant' : 'Open notifications' ?>">
                <?php if ($tab === 'notifications'): ?>
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"></path></svg>
                <?php else: ?>
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path><path d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path></svg>
                    <span id="mobile-notifications-count" class="icon-badge <?= $mobileUnreadCount > 0 ? '' : 'hidden' ?>"><?= e((string) min(99, $mobileUnreadCount)) ?></span>
                <?php endif; ?>
            </a>
        </header>

        <?php if ($tab === 'assistant'): ?>
        <section class="thread" id="assistant-thread" aria-live="polite">
                <article class="notification-enable" id="notification-enable-card">
                    <div id="notification-enable-text">Enable iPhone notifications for Elite AI.</div>
                    <button type="button" id="notification-enable-button">Enable Notifications</button>
                </article>
                <?php if ($showWelcome): ?>
                    <article class="message assistant welcome">
                        <p>Setup complete. Add this page to your Home Screen for daily use.</p>
                    </article>
                <?php endif; ?>
                <article class="message assistant">
                    <p>Good morning, <?= e($displayName) ?>. What do you want to do?</p>
                </article>
            </section>

            <section class="composer-wrap" aria-label="Assistant composer">
                <div class="quick-actions-shell">
                    <button class="quick-actions-toggle" id="assistant-quick-actions-toggle" type="button" aria-expanded="false" aria-controls="assistant-quick-actions">Shortcuts</button>
                    <div class="quick-actions" id="assistant-quick-actions" aria-label="Quick actions"></div>
                </div>
                <form class="composer" id="assistant-composer">
                    <input
                        id="assistant-input"
                        type="text"
                        placeholder="Ask Elite AI what to do..."
                        autocomplete="off"
                        autocorrect="off"
                        autocapitalize="sentences"
                        spellcheck="false"
                        inputmode="text"
                        enterkeyhint="send"
                        aria-label="Ask Elite AI"
                    >
                    <button id="assistant-mic" type="button" aria-label="Microphone placeholder">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3z"></path><path d="M19 10v2a7 7 0 0 1-14 0v-2"></path><path d="M12 19v3"></path></svg>
                    </button>
                    <button type="submit" aria-label="Send">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2 11 13"></path><path d="m22 2-7 20-4-9-9-4 20-7z"></path></svg>
                    </button>
                </form>
            </section>
        <?php else: ?>
            <section class="notifications" id="mobile-notifications-list" aria-label="Notifications">
                <?php if (!$notifications): ?>
                    <p class="empty">No notifications right now.</p>
                <?php endif; ?>

                <?php foreach ($notifications as $item): ?>
                    <?php $isUnread = !empty($item['is_new']); ?>
                    <?php $notificationAssistantUrl = ''; ?>
                    <?php if ((int) ($item['lead_id'] ?? 0) > 0): ?>
                        <?php
                            $notificationAssistantUrl = base_url('mobile-ai/?tab=assistant'
                                . '&notification_id=' . rawurlencode((string) ($item['id'] ?? ''))
                                . '&lead_id=' . (int) ($item['lead_id'] ?? 0));
                        ?>
                    <?php endif; ?>
                    <<?= $notificationAssistantUrl !== '' ? 'a' : 'article' ?>
                        class="notification <?= $isUnread ? 'unread' : 'read' ?>"
                        <?php if ($notificationAssistantUrl !== ''): ?>
                            href="<?= e($notificationAssistantUrl) ?>"
                            aria-label="Open notification in Assistant"
                        <?php endif; ?>
                    >
                        <h2>
                            <?= e((string) ($item['title'] ?? 'CRM alert')) ?>
                            <span class="notification-state"><?= $isUnread ? 'Unread' : 'Read' ?></span>
                        </h2>
                        <?php if (trim((string) ($item['assistant_summary'] ?? '')) !== ''): ?>
                            <p><?= e((string) ($item['assistant_summary'] ?? '')) ?></p>
                        <?php endif; ?>
                        <?php if (trim((string) ($item['assistant_prompt'] ?? '')) !== ''): ?>
                            <p class="meta"><?= e((string) ($item['assistant_prompt'] ?? '')) ?></p>
                        <?php endif; ?>
                        <?php if (trim((string) ($item['message'] ?? '')) !== ''): ?>
                            <p><?= e((string) ($item['message'] ?? '')) ?></p>
                        <?php endif; ?>
                        <p class="meta">
                            <?= e(format_datetime((string) ($item['created_at'] ?? ''), 'M j, Y g:i A')) ?>
                            <?php if (!empty($item['lead_name'])): ?>
                                - <?= e((string) $item['lead_name']) ?>
                            <?php endif; ?>
                        </p>
                        <?php if ($notificationAssistantUrl !== ''): ?>
                            <span class="open-link">Open in Assistant</span>
                        <?php endif; ?>
                    </<?= $notificationAssistantUrl !== '' ? 'a' : 'article' ?>>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('<?= e(base_url('mobile-ai/sw.js')) ?>', { updateViaCache: 'none' })
                    .then(function (registration) { return registration.update(); })
                    .catch(function () {});
            });
        }

        (function () {
            var thread = document.getElementById('assistant-thread');
            var form = document.getElementById('assistant-composer');
            var input = document.getElementById('assistant-input');
            var mic = document.getElementById('assistant-mic');
            var quickActions = document.getElementById('assistant-quick-actions');
            var quickActionsToggle = document.getElementById('assistant-quick-actions-toggle');
            var pendingDraftsSection = document.getElementById('assistant-pending-drafts');
            var pendingDraftsTitle = document.getElementById('assistant-pending-drafts-title');
            var pendingDraftsList = document.getElementById('assistant-pending-drafts-list');
            var notificationEnableCard = document.getElementById('notification-enable-card');
            var notificationEnableButton = document.getElementById('notification-enable-button');
            var notificationEnableText = document.getElementById('notification-enable-text');
            var notificationCountBadge = document.getElementById('mobile-notifications-count');
            var visualViewport = window.visualViewport || null;
            var largestViewportHeight = visualViewport ? visualViewport.height : window.innerHeight;
            var viewportSyncFrame = 0;
            var endpoint = '<?= e((string) (parse_url(base_url('assistant-api-live.php'), PHP_URL_PATH) ?: '/crm/assistant-api-live.php')) ?>';
            var mobileEndpoint = '<?= e(base_url('mobile-ai/')) ?>';
            var notificationFeedEndpoint = '<?= e(base_url('mobile-ai/?notification_feed=1')) ?>';
            var assistantCsrfToken = <?= json_encode(csrf_token(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
            var webPushPublicKey = '<?= e((string) ELITE_WEB_PUSH_PUBLIC_KEY) ?>';
            var notificationSeed = <?= json_encode($notificationFeedSeed, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
            var notificationVersion = <?= json_encode($notificationVersion, JSON_UNESCAPED_SLASHES) ?>;
            var urlParams = new URLSearchParams(window.location.search || '');
            var activeNotificationId = String(urlParams.get('notification_id') || '');
            var activeLeadId = Number(urlParams.get('lead_id') || 0);
            var activeNotification = Array.isArray(notificationSeed) ? notificationSeed.find(function (item) {
                return String(item && item.id ? item.id : '') === activeNotificationId;
            }) : null;
            if (!activeNotification && activeLeadId > 0 && Array.isArray(notificationSeed)) {
                activeNotification = notificationSeed.find(function (item) {
                    return Number(item && item.lead_id ? item.lead_id : 0) === activeLeadId;
                }) || null;
            }
            var baseContext = {
                page: 'mobile-ai',
                page_title: 'Elite AI Mobile Portal',
                current_url: window.location.href,
                lead_id: activeLeadId > 0 ? activeLeadId : 0,
                tab: 'assistant'
            };

            var assistantSpeech = null;
            var isListening = false;
            var assistantThreadState = [];
            var assistantRestoringThread = false;
            var assistantThreadStorageKey = 'elite-ai-mobile-thread-v1';
            var assistantThreadMaxAgeMs = 12 * 60 * 60 * 1000;
            var notificationAudioUnlocked = false;
            var notificationAudioKey = 'elite_ai_seen_notifications_v1';
            var notificationPollTimer = null;

            function syncAssistantViewport() {
                viewportSyncFrame = 0;
                var viewportHeight = visualViewport ? visualViewport.height : window.innerHeight;
                var viewportTop = visualViewport ? visualViewport.offsetTop : 0;
                var inputFocused = document.activeElement === input;

                if (!inputFocused || viewportHeight > largestViewportHeight) {
                    largestViewportHeight = Math.max(largestViewportHeight, viewportHeight);
                }

                var keyboardOpen = inputFocused && (
                    (largestViewportHeight - viewportHeight) > 120
                    || viewportHeight < window.innerHeight * 0.82
                );

                document.documentElement.style.setProperty('--app-height', Math.max(320, viewportHeight) + 'px');
                document.documentElement.style.setProperty('--app-top', Math.max(0, viewportTop) + 'px');
                document.body.classList.toggle('keyboard-open', keyboardOpen);

                if (keyboardOpen && thread) {
                    thread.scrollTop = thread.scrollHeight;
                }
            }

            function scheduleAssistantViewportSync() {
                if (viewportSyncFrame) {
                    window.cancelAnimationFrame(viewportSyncFrame);
                }
                viewportSyncFrame = window.requestAnimationFrame(syncAssistantViewport);
            }

            function loadMobileAssistantThread() {
                try {
                    var raw = window.localStorage.getItem(assistantThreadStorageKey);
                    var stored = raw ? JSON.parse(raw) : null;
                    var savedAt = Number(stored && stored.saved_at ? stored.saved_at : 0);
                    if (!stored || !Array.isArray(stored.messages) || Date.now() - savedAt > assistantThreadMaxAgeMs) {
                        window.localStorage.removeItem(assistantThreadStorageKey);
                        assistantThreadState = [];
                        return;
                    }
                    assistantThreadState = stored.messages.slice(-30);
                } catch (error) {
                    assistantThreadState = [];
                }
            }

            function saveMobileAssistantThread() {
                try {
                    window.localStorage.setItem(assistantThreadStorageKey, JSON.stringify({
                        saved_at: Date.now(),
                        messages: assistantThreadState.slice(-30)
                    }));
                } catch (error) {
                    // Keep the live chat usable when browser storage is unavailable.
                }
            }

            function restoreMobileAssistantThread() {
                if (!thread || assistantThreadState.length === 0) {
                    return;
                }
                var savedMessages = assistantThreadState.slice();
                assistantRestoringThread = true;
                thread.innerHTML = '';
                if (notificationEnableCard) {
                    thread.appendChild(notificationEnableCard);
                }
                savedMessages.forEach(function (item) {
                    createMessage(
                        item && item.role === 'user' ? 'user' : 'assistant',
                        String(item && item.text ? item.text : ''),
                        Array.isArray(item && item.cards) ? item.cards : [],
                        Array.isArray(item && item.actions) ? item.actions : [],
                        false
                    );
                });
                assistantRestoringThread = false;
                thread.scrollTop = thread.scrollHeight;
            }

            function notificationIdentity(item) {
                return [
                    String(item && item.id ? item.id : ''),
                    String(item && item.lead_id ? item.lead_id : ''),
                    String(item && item.created_at ? item.created_at : ''),
                    String(item && item.message ? item.message : '').slice(0, 80)
                ].join('|');
            }

            function seenNotificationIds() {
                try {
                    var raw = window.localStorage.getItem(notificationAudioKey);
                    var parsed = raw ? JSON.parse(raw) : [];
                    return Array.isArray(parsed) ? parsed : [];
                } catch (error) {
                    return [];
                }
            }

            function saveSeenNotificationIds(ids) {
                try {
                    window.localStorage.setItem(notificationAudioKey, JSON.stringify(ids.slice(-80)));
                } catch (error) {
                    // Ignore storage limits. Sound is helpful, not critical.
                }
            }

            function unreadNotificationCount() {
                if (!Array.isArray(notificationSeed)) {
                    return 0;
                }
                return notificationSeed.filter(function (item) {
                    return Boolean(item && item.is_new);
                }).length;
            }

            function markNotificationsReadLocally(leadId) {
                var normalizedLeadId = Number(leadId || 0);
                if (normalizedLeadId <= 0 || !Array.isArray(notificationSeed)) {
                    return;
                }
                notificationSeed.forEach(function (item) {
                    if (Number(item && item.lead_id ? item.lead_id : 0) === normalizedLeadId) {
                        item.is_new = false;
                    }
                });
                if (activeNotification && Number(activeNotification.lead_id || 0) === normalizedLeadId) {
                    activeNotification.is_new = false;
                }
                syncAppBadge(unreadNotificationCount());
            }

            async function syncAppBadge(count) {
                var badgeCount = Math.max(0, Number(count || 0));
                if (notificationCountBadge) {
                    notificationCountBadge.textContent = badgeCount > 99 ? '99+' : String(badgeCount);
                    notificationCountBadge.classList.toggle('hidden', badgeCount === 0);
                }
                try {
                    if (badgeCount > 0 && 'setAppBadge' in navigator) {
                        await navigator.setAppBadge(badgeCount);
                        return;
                    }
                    if (badgeCount === 0 && 'clearAppBadge' in navigator) {
                        await navigator.clearAppBadge();
                    }
                } catch (error) {
                    // Badging is platform-dependent; the assistant still works without it.
                }
            }

            function unlockNotificationAudio() {
                notificationAudioUnlocked = true;
            }

            ['pointerdown', 'touchstart', 'keydown'].forEach(function (eventName) {
                window.addEventListener(eventName, unlockNotificationAudio, { once: true, passive: true });
            });

            function playNotificationSound() {
                try {
                    if (!notificationAudioUnlocked && document.visibilityState !== 'visible') {
                        return;
                    }
                    var AudioContext = window.AudioContext || window.webkitAudioContext;
                    if (!AudioContext) {
                        return;
                    }
                    var ctx = window.__eliteAINotificationAudio || new AudioContext();
                    window.__eliteAINotificationAudio = ctx;
                    if (ctx.state === 'suspended') {
                        ctx.resume().catch(function () {});
                    }
                    var gain = ctx.createGain();
                    var oscillator = ctx.createOscillator();
                    var now = ctx.currentTime;
                    oscillator.type = 'sine';
                    oscillator.frequency.setValueAtTime(880, now);
                    oscillator.frequency.setValueAtTime(660, now + 0.12);
                    gain.gain.setValueAtTime(0.0001, now);
                    gain.gain.exponentialRampToValueAtTime(0.08, now + 0.02);
                    gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.32);
                    oscillator.connect(gain);
                    gain.connect(ctx.destination);
                    oscillator.start(now);
                    oscillator.stop(now + 0.35);
                } catch (error) {
                    // Audio can be blocked by the browser until the next user gesture.
                }
            }

            function announceNewNotifications() {
                if (!Array.isArray(notificationSeed) || notificationSeed.length === 0) {
                    syncAppBadge(0);
                    return [];
                }
                var unread = notificationSeed.filter(function (item) {
                    return Boolean(item && item.is_new);
                });
                syncAppBadge(unread.length);
                if (unread.length === 0) {
                    return [];
                }
                var seen = seenNotificationIds();
                var seenSet = new Set(seen);
                var fresh = unread.filter(function (item) {
                    return !seenSet.has(notificationIdentity(item));
                });
                if (fresh.length === 0) {
                    return [];
                }
                fresh.forEach(function (item) {
                    seenSet.add(notificationIdentity(item));
                });
                saveSeenNotificationIds(Array.from(seenSet));
                playNotificationSound();
                return fresh;
            }

            var initialFreshNotifications = announceNewNotifications();

            if (!thread || !form || !input) {
                return;
            }
            loadMobileAssistantThread();
            restoreMobileAssistantThread();

            function isStandaloneApp() {
                return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
            }

            function urlBase64ToUint8Array(value) {
                var padding = '='.repeat((4 - value.length % 4) % 4);
                var base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
                var raw = window.atob(base64);
                return Uint8Array.from(Array.prototype.map.call(raw, function (character) {
                    return character.charCodeAt(0);
                }));
            }

            async function savePushSubscription(subscription) {
                var response = await fetch(mobileEndpoint, {
                    method: 'POST',
                    credentials: 'include',
                    cache: 'no-store',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': assistantCsrfToken
                    },
                    body: JSON.stringify({
                        action: 'save_push_subscription',
                        subscription: subscription.toJSON()
                    })
                });
                var data = await response.json();
                if (!response.ok || !data.ok) {
                    throw new Error(data.message || 'Could not save notification subscription.');
                }
            }

            async function ensurePushSubscription(showTest) {
                if (
                    !('serviceWorker' in navigator)
                    || !('PushManager' in window)
                    || webPushPublicKey === ''
                    || Notification.permission !== 'granted'
                ) {
                    return false;
                }

                var registration = await navigator.serviceWorker.ready;
                var subscription = await registration.pushManager.getSubscription();
                if (!subscription) {
                    subscription = await registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(webPushPublicKey)
                    });
                }
                await savePushSubscription(subscription);

                if (showTest) {
                    await registration.showNotification('Elite AI connected', {
                        body: 'Rod, real CRM notifications are connected.',
                        icon: '/crm/assets/img/ES-Logo-Stack-500-x-150-px.png',
                        badge: '/crm/assets/img/ES-Logo-Stack-500-x-150-px.png',
                        tag: 'elite-ai-test',
                        renotify: true,
                        data: { url: '/crm/mobile-ai?tab=assistant' }
                    });
                }
                return true;
            }

            function refreshNotificationPrompt() {
                if (!notificationEnableCard || !notificationEnableButton || !notificationEnableText) {
                    return;
                }
                if (!('Notification' in window)) {
                    notificationEnableCard.classList.add('open');
                    notificationEnableText.textContent = 'iPhone notifications need the Home Screen Elite AI app.';
                    notificationEnableButton.style.display = 'none';
                    return;
                }
                if (Notification.permission === 'granted') {
                    notificationEnableCard.classList.remove('open');
                    return;
                }
                notificationEnableCard.classList.add('open');
                notificationEnableButton.style.display = Notification.permission === 'denied' ? 'none' : 'inline-flex';
                notificationEnableButton.textContent = 'Enable Notifications';
                notificationEnableText.textContent = Notification.permission === 'denied'
                    ? 'Notifications are blocked. Open iPhone Settings > Notifications > Elite AI and allow them.'
                    : (isStandaloneApp()
                        ? 'Tap to allow iPhone notifications for Elite AI.'
                        : 'Open Elite AI from the Home Screen, then tap Enable Notifications.');
            }

            if (notificationEnableButton) {
                notificationEnableButton.addEventListener('click', async function () {
                    if (!('Notification' in window)) {
                        refreshNotificationPrompt();
                        return;
                    }
                    try {
                        var permission = Notification.permission === 'granted'
                            ? 'granted'
                            : await Notification.requestPermission();
                        if (permission === 'granted') {
                            await ensurePushSubscription(true);
                        }
                    } catch (error) {
                        if (notificationEnableText) {
                            notificationEnableText.textContent = 'Could not open the iPhone notification prompt. Open Elite AI from the Home Screen and try again.';
                        }
                    }
                    refreshNotificationPrompt();
                });
            }
            refreshNotificationPrompt();
            if ('Notification' in window && Notification.permission === 'granted') {
                ensurePushSubscription(false).catch(function () {
                    notificationEnableCard.classList.add('open');
                    notificationEnableButton.style.display = 'inline-flex';
                    notificationEnableButton.textContent = 'Reconnect Notifications';
                    notificationEnableText.textContent = 'Tap to reconnect real CRM notifications.';
                });
            }

            function assistantConversationContext() {
                return assistantThreadState.slice(-8).map(function (item) {
                    return {
                        role: item && item.role === 'user' ? 'user' : 'assistant',
                        text: String(item && item.text ? item.text : '').slice(0, 700)
                    };
                }).filter(function (item) {
                    return item.text.trim() !== '';
                });
            }

            function assistantContext() {
                var context = {};
                Object.keys(baseContext).forEach(function (key) {
                    context[key] = baseContext[key];
                });
                if (activeNotification) {
                    context.notification = {
                        id: String(activeNotification.id || ''),
                        type: String(activeNotification.type || ''),
                        title: String(activeNotification.title || ''),
                        message: String(activeNotification.message || ''),
                        created_at: String(activeNotification.created_at || ''),
                        lead_id: Number(activeNotification.lead_id || activeLeadId || 0),
                        lead_name: String(activeNotification.lead_name || ''),
                        status: String(activeNotification.status || ''),
                        suggested_action: String(activeNotification.suggested_action || ''),
                        is_new: Boolean(activeNotification.is_new),
                        assistant_summary: String(activeNotification.assistant_summary || ''),
                        assistant_prompt: String(activeNotification.assistant_prompt || ''),
                        primary_action: String(activeNotification.primary_action || ''),
                        badge_count: Number(activeNotification.badge_count || unreadNotificationCount())
                    };
                    context.lead_id = Number(activeNotification.lead_id || activeLeadId || 0);
                }
                context.assistant_thread = assistantConversationContext();
                return context;
            }

            var quickActionItems = [
                { label: 'Control Center', quick_action: 'control-center' },
                { label: 'Morning Sweep', quick_action: 'morning-sweep' },
                { label: 'New Leads', quick_action: 'new-leads' },
                { label: 'Replies', quick_action: 'replies' },
                { label: 'Follow-ups', quick_action: 'follow-ups' },
                { label: 'No Answer Review', quick_action: 'no-answer-review' },
                { label: 'Notifications', quick_action: 'notifications' },
            ];

            function createMessage(role, text, cards, actions, isLoading) {
                var article = document.createElement('article');
                article.className = 'message ' + role + (isLoading ? ' loading' : '');

                var paragraph = document.createElement('p');
                paragraph.textContent = text;
                article.appendChild(paragraph);

                if (cards && cards.length) {
                    var cardsWrap = document.createElement('div');
                    cardsWrap.className = 'cards';
                    cards.forEach(function (card) {
                        var cardEl = document.createElement('details');
                        cardEl.className = 'result-card';

                        var summary = document.createElement('summary');
                        var title = document.createElement('p');
                        title.className = 'result-card-title';
                        var itemCount = Array.isArray(card.items) ? card.items.length : 0;
                        title.textContent = (card.title || 'Details') + (itemCount ? ' (' + itemCount + ')' : '');
                        summary.appendChild(title);
                        cardEl.appendChild(summary);

                        var list = document.createElement('ul');
                        (card.items || []).forEach(function (item) {
                            var li = document.createElement('li');
                            li.textContent = item;
                            list.appendChild(li);
                        });
                        cardEl.appendChild(list);
                        cardsWrap.appendChild(cardEl);
                    });
                    article.appendChild(cardsWrap);
                }
                var assistantActions = normalizeAssistantActions(actions || [], baseContext.lead_id || activeLeadId || 0);
                if (assistantActions.length) {
                    var actionWrap = document.createElement('div');
                    assistantActions.forEach(function (action) {
                        var actionButton = document.createElement('button');
                        actionButton.type = 'button';
                        actionButton.className = 'action-btn mobile-ai-action-button';
                        actionButton.textContent = action.label || ('Action: ' + String(action.type || ''));
                        actionButton.dataset.actionType = String(action.type || '');
                        actionButton.dataset.leadId = String(Number(action.lead_id || action.leadId || 0));
                        actionButton.dataset.actionId = String(Number(action.action_id || action.actionId || 0));
                        actionButton.dataset.actionLabel = String(action.label || '');
                        actionButton.dataset.actionHelp = String(action.help || '');
                        actionButton.dataset.targetStatus = String(action.target_status || action.targetStatus || '');
                        actionButton.dataset.consultationDate = String(action.consultation_date || action.appointment_at || '');
                        actionButton.dataset.note = String(action.note || '');
                        actionButton.addEventListener('click', function (event) {
                            event.preventDefault();
                            event.stopPropagation();
                            runAssistantAction(buttonAssistantAction(actionButton, action));
                        });
                        actionWrap.appendChild(actionButton);
                    });
                    article.appendChild(actionWrap);
                }

                thread.appendChild(article);
                if (!assistantRestoringThread) {
                    article.scrollIntoView({ behavior: 'smooth', block: 'end' });
                }
                if (!isLoading && !assistantRestoringThread) {
                    assistantThreadState.push({
                        role: role === 'user' ? 'user' : 'assistant',
                        text: String(text || ''),
                        cards: Array.isArray(cards) ? cards.slice(0, 5).map(function (card) {
                            return {
                                title: String(card && card.title ? card.title : 'Details'),
                                items: Array.isArray(card && card.items) ? card.items.slice(0, 20).map(String) : []
                            };
                        }) : [],
                        actions: assistantActions.slice(0, 6).map(function (action) {
                            return {
                                type: String(action && action.type ? action.type : ''),
                                label: String(action && action.label ? action.label : ''),
                                help: String(action && action.help ? action.help : ''),
                                lead_id: Number(action && (action.lead_id || action.leadId) ? (action.lead_id || action.leadId) : 0),
                                action_id: Number(action && (action.action_id || action.actionId) ? (action.action_id || action.actionId) : 0),
                                target_status: String(action && (action.target_status || action.targetStatus) ? (action.target_status || action.targetStatus) : ''),
                                consultation_date: String(action && (action.consultation_date || action.appointment_at) ? (action.consultation_date || action.appointment_at) : ''),
                                note: String(action && action.note ? action.note : '')
                            };
                        }),
                        created_at: Date.now()
                    });
                    assistantThreadState = assistantThreadState.slice(-30);
                    saveMobileAssistantThread();
                }
                return article;
            }

            function setBusy(isBusy) {
                input.disabled = isBusy;
                form.querySelectorAll('button').forEach(function (button) {
                    button.disabled = isBusy;
                });
                if (quickActions) {
                    quickActions.querySelectorAll('button').forEach(function (button) {
                        button.disabled = isBusy;
                    });
                }
            }

            function getSpeechRecognition() {
                if (window.__eliteAIRecognition) {
                    return window.__eliteAIRecognition;
                }
                var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition || null;
                if (!SpeechRecognition) {
                    return null;
                }
                var recognition = new SpeechRecognition();
                recognition.continuous = false;
                recognition.interimResults = true;
                recognition.maxAlternatives = 1;
                recognition.lang = 'en-US';
                window.__eliteAIRecognition = recognition;
                return recognition;
            }

            function setDraftStatus(text) {
                var statusBar = input.parentElement && input.parentElement.querySelector('.draft-status');
                if (!statusBar) {
                    return;
                }
                if (!text) {
                    statusBar.classList.add('hidden');
                    statusBar.textContent = '';
                    return;
                }
                statusBar.textContent = text;
                statusBar.classList.remove('hidden');
            }

            function stopAssistantListening() {
                isListening = false;
                if (assistantSpeech && assistantSpeech.stop) {
                    try {
                        assistantSpeech.stop();
                    } catch (error) {
                        // no-op.
                    }
                }
                if (mic) {
                    mic.classList.remove('bg-slate-900', 'text-white');
                }
            }

            function startAssistantListening() {
                var recognition = getSpeechRecognition();
                if (!recognition) {
                    createMessage('assistant', 'Voice input is not supported on this browser.');
                    return;
                }

                assistantSpeech = recognition;
                isListening = true;
                assistantSpeech.onstart = function () {
                    mic.classList.add('bg-slate-900', 'text-white');
                    setDraftStatus('Listening...');
                };
                assistantSpeech.onresult = function (event) {
                    var finalText = '';
                    for (var i = 0; i < event.results.length; i += 1) {
                        if (event.results[i].isFinal) {
                            finalText += event.results[i][0].transcript + ' ';
                        }
                    }
                    if (finalText.trim() === '') {
                        return;
                    }
                    input.value = (input.value + ' ' + finalText).trim();
                    setDraftStatus('Ready to send transcript.');
                };
                assistantSpeech.onerror = function () {
                    setDraftStatus('Voice input failed. You can still type your request.');
                    stopAssistantListening();
                };
                assistantSpeech.onend = function () {
                    stopAssistantListening();
                    if (input.value.trim() !== '') {
                        setDraftStatus('You can edit transcript and press Send.');
                    }
                };
                try {
                    assistantSpeech.start();
                } catch (error) {
                    stopAssistantListening();
                    setDraftStatus('Could not start voice input. You can still type.');
                }
            }

            function ensureStatusNode() {
                var statusNode = input.parentElement && input.parentElement.querySelector('.draft-status');
                if (statusNode) {
                    return statusNode;
                }
                var node = document.createElement('p');
                node.className = 'draft-status hidden';
                input.parentElement.appendChild(node);
                return node;
            }

            function normalizeAssistantActions(actions, fallbackLeadId) {
                var leadId = Number(fallbackLeadId || 0);
                return (Array.isArray(actions) ? actions : []).map(function (action) {
                    var normalized = Object.assign({}, action || {});
                    normalized.type = String(normalized.type || '');
                    normalized.label = String(normalized.label || '');
                    normalized.help = String(normalized.help || '');
                    normalized.lead_id = Number(normalized.lead_id || normalized.leadId || leadId || 0);
                    normalized.action_id = Number(normalized.action_id || normalized.actionId || 0);
                    normalized.target_status = String(normalized.target_status || normalized.targetStatus || '');
                    normalized.consultation_date = String(normalized.consultation_date || normalized.appointment_at || '');
                    normalized.note = String(normalized.note || '');
                    return normalized;
                }).filter(function (action) {
                    return action.type && (
                        action.lead_id > 0
                        || action.action_id > 0
                        || ['clear_stale_drafts'].indexOf(action.type) !== -1
                    );
                });
            }

            function buttonAssistantAction(button, fallbackAction) {
                var fallback = fallbackAction || {};
                return {
                    type: String(button.dataset.actionType || fallback.type || ''),
                    label: String(button.dataset.actionLabel || fallback.label || ''),
                    help: String(button.dataset.actionHelp || fallback.help || ''),
                    lead_id: Number(button.dataset.leadId || fallback.lead_id || fallback.leadId || 0),
                    action_id: Number(button.dataset.actionId || fallback.action_id || fallback.actionId || 0),
                    target_status: String(button.dataset.targetStatus || fallback.target_status || fallback.targetStatus || ''),
                    consultation_date: String(button.dataset.consultationDate || fallback.consultation_date || fallback.appointment_at || ''),
                    note: String(button.dataset.note || fallback.note || '')
                };
            }

            function resolveDraftActionType(data, actionType) {
                var mappedActionType = String(actionType || '');
                if (mappedActionType === 'draft_sms' || mappedActionType === 'draft_email') {
                    return mappedActionType;
                }
                if (mappedActionType === 'use_draft' || mappedActionType === 'edit_draft' || mappedActionType === 'cancel_draft') {
                    if (data && String(data.channel || '') === 'SMS') {
                        return 'draft_sms';
                    }
                    if (data && String(data.channel || '') === 'Email') {
                        return 'draft_email';
                    }
                }
                if (data && String(data.action_type || '') === 'draft_sms') {
                    return 'draft_sms';
                }
                if (data && String(data.action_type || '') === 'draft_email') {
                    return 'draft_email';
                }
                if (data && data.channel === 'SMS') {
                    return 'draft_sms';
                }
                if (data && data.channel === 'Email') {
                    return 'draft_email';
                }
                return data && data.type ? String(data.type) : '';
            }

            function normalizeDraftText(draft, actionType) {
                if (!draft || typeof draft !== 'object') {
                    return '';
                }
                if (actionType === 'draft_email') {
                    if (typeof draft.body === 'string' && draft.body.trim() !== '') {
                        return draft.body.trim();
                    }
                    if (typeof draft.message === 'string' && draft.message.trim() !== '') {
                        return draft.message.trim();
                    }
                    if (typeof draft.text === 'string' && draft.text.trim() !== '') {
                        return draft.text.trim();
                    }
                }
                if (typeof draft.reply === 'string' && draft.reply.trim() !== '') {
                    return draft.reply.trim();
                }
                if (typeof draft.message === 'string' && draft.message.trim() !== '') {
                    return draft.message.trim();
                }
                if (typeof draft.text === 'string' && draft.text.trim() !== '') {
                    return draft.text.trim();
                }
                if (typeof draft.body === 'string' && draft.body.trim() !== '') {
                    return draft.body.trim();
                }
                return '';
            }

            function setDraftModeInComposer(draft, actionType, leadId, actionId, note) {
                var draftText = normalizeDraftText(draft || {}, actionType);
                if (draftText) {
                    input.value = draftText;
                }
                input.focus();
                ensureStatusNode();
                var channel = actionType === 'draft_sms' ? 'SMS' : 'Email';
                var noteText = note || 'Reviewing draft';
                var actionText = actionId > 0 ? (' | Queue #' + String(actionId)) : '';
                setDraftStatus(noteText + ' | ' + channel + ' | Draft only - not sent' + (leadId ? ' | Lead #' + String(leadId) : '') + actionText);
            }

            function appendDraftActionButtons(wrapper, action) {
                if (!wrapper || !action) {
                    return;
                }
                var actions = Array.isArray(action.actions) ? action.actions : [];
                if (!actions.length) {
                    return;
                }
                actions.forEach(function (entry) {
                    var actionType = String(entry.type || '');
                    var actionId = Number(entry.action_id || 0);
                    if (!actionType || !actionId) {
                        return;
                    }
                    var actionButton = document.createElement('button');
                    actionButton.type = 'button';
                    actionButton.className = 'draft-action-btn';
                    actionButton.textContent = String(entry.label || actionType);
                    actionButton.dataset.actionType = actionType;
                    actionButton.dataset.leadId = String(Number(action.lead_id || action.leadId || 0));
                    actionButton.dataset.actionId = String(Number(actionId));
                    actionButton.addEventListener('click', function (event) {
                        event.preventDefault();
                        event.stopPropagation();
                        runAssistantAction(buttonAssistantAction(actionButton, entry));
                    });
                    wrapper.appendChild(actionButton);
                });
            }

            function renderDraftCard(messageArticle, draftPayload, actionType, actionId, leadId, data, action) {
                if (!messageArticle) {
                    return;
                }
                var card = document.createElement('div');
                var cardTitle = document.createElement('p');
                var cardText = document.createElement('p');
                var badge = document.createElement('p');
                var actionWrap = document.createElement('div');
                var label = actionType === 'draft_sms' ? 'SMS Draft' : (actionType === 'draft_email' ? 'Email Draft' : 'Draft');
                var channel = actionType === 'draft_sms' ? 'SMS' : (actionType === 'draft_email' ? 'Email' : 'Draft');
                var status = (data && typeof data.draft_badge === 'string') ? String(data.draft_badge) : 'Draft only - not sent';
                var preview = formatDraftPreview(draftPayload || {}, actionType);
                var subject = '';
                if (actionType === 'draft_email' && draftPayload && String(draftPayload.subject || '').trim() !== '') {
                    subject = String(draftPayload.subject || '').trim();
                }

                card.className = 'draft-card';
                cardTitle.className = 'draft-card-title';
                cardTitle.textContent = label;
                cardText.className = 'draft-card-body';
                cardText.textContent = preview || 'Draft prepared for review. Draft only - not sent.';
                badge.className = 'draft-card-meta';
                badge.textContent = status + ' | ' + channel + ' | Lead #' + String(leadId || 0) + (actionId ? (' | Queue #' + String(actionId)) : '');
                if (subject !== '') {
                    var subjectRow = document.createElement('p');
                    subjectRow.className = 'draft-card-meta draft-card-subject';
                    subjectRow.textContent = 'Subject: ' + subject;
                    card.appendChild(subjectRow);
                }
                actionWrap.className = 'draft-card-actions';
                card.appendChild(cardTitle);
                card.appendChild(cardText);
                card.appendChild(badge);
                appendDraftActionButtons(actionWrap, { lead_id: leadId, actions: resolveDraftActions(data) });
                card.appendChild(actionWrap);
                messageArticle.appendChild(card);
                return card;
            }

            function resolveDraftActions(data) {
                if (!data) {
                    return [];
                }
                if (Array.isArray(data.draft_actions) && data.draft_actions.length) {
                    return data.draft_actions;
                }
                if (Array.isArray(data.actions) && data.actions.length) {
                    return data.actions;
                }
                return [];
            }

            function renderPendingDraft(item) {
                if (!item || !pendingDraftsList) {
                    return;
                }
                var actionId = Number(item.action_id || 0);
                var leadId = Number(item.lead_id || 0);
                var card = document.createElement('div');
                card.className = 'draft-card';

                var title = document.createElement('p');
                title.className = 'draft-card-title';
                title.textContent = String(item.channel || 'Draft') + ' Queue #' + String(actionId);
                card.appendChild(title);

                var body = document.createElement('p');
                body.className = 'draft-card-body';
                body.textContent = String(item.draft_preview || '').trim() || 'Draft prepared for review. Draft only - not sent.';
                card.appendChild(body);

                var meta = document.createElement('p');
                meta.className = 'draft-card-meta';
                meta.textContent = 'Lead #' + String(leadId) + (item.lead_name ? ' - ' + String(item.lead_name) : '');
                card.appendChild(meta);

                var actionWrap = document.createElement('div');
                actionWrap.className = 'draft-card-actions';
                appendDraftActionButtons(actionWrap, { lead_id: leadId, actions: item.actions || [] });
                card.appendChild(actionWrap);
                pendingDraftsList.appendChild(card);
            }

            function applyPendingDrafts(pendingDrafts) {
                if (!pendingDraftsList) {
                    return;
                }
                pendingDraftsList.innerHTML = '';
                if (!Array.isArray(pendingDrafts) || !pendingDrafts.length) {
                    pendingDraftsSection.style.display = 'none';
                    return;
                }
                pendingDraftsSection.style.display = 'block';
                if (pendingDraftsTitle) {
                    pendingDraftsTitle.classList.remove('hidden');
                }
                pendingDrafts.forEach(function (item) {
                    renderPendingDraft(item);
                });
            }

            async function refreshPendingDrafts() {
                if (!pendingDraftsSection || !pendingDraftsList) {
                    return;
                }
                try {
                    var response = await fetch(endpoint, {
                        method: 'POST',
                        credentials: 'include',
                        cache: 'no-store',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-Token': assistantCsrfToken
                        },
                        body: JSON.stringify({
                            surface: 'mobile',
                            prompt: '',
                            quick_action: '',
                            context: baseContext
                        })
                    });
                    if (!response.ok) {
                        return;
                    }
                    var data = await response.json();
                    applyPendingDrafts(Array.isArray(data.pending_drafts) ? data.pending_drafts : []);
                } catch (error) {
                    // keep silent.
                }
            }

            function parseDraftCandidate(candidate) {
                if (!candidate) {
                    return {};
                }

                if (typeof candidate === 'string') {
                    try {
                        var decoded = JSON.parse(candidate);
                        return decoded && typeof decoded === 'object' ? decoded : {};
                    } catch (error) {
                        return candidate.trim() !== '' ? { __preview: candidate } : {};
                    }
                }

                return typeof candidate === 'object' ? candidate : {};
            }

            function formatDraftPreview(draft, actionType) {
                if (!draft || typeof draft !== 'object') {
                    return '';
                }
                if (typeof draft.__preview === 'string' && draft.__preview.trim() !== '') {
                    return draft.__preview.trim();
                }
                var nestedSms = draft.sms && typeof draft.sms === 'object' ? draft.sms : {};
                var nestedEmail = draft.email && typeof draft.email === 'object' ? draft.email : {};
                var smsText = String(draft.reply || draft.message || draft.text || draft.draft_text || draft.body || nestedSms.reply || nestedSms.message || nestedSms.text || nestedSms.body || '').trim();
                if (actionType === 'draft_sms') {
                    return smsText ? 'Suggested SMS draft:\n\n' + smsText : '';
                }
                if (actionType === 'draft_email') {
                    var subject = String(draft.subject || nestedEmail.subject || '').trim();
                    var body = String(draft.body || draft.message || draft.text || nestedEmail.body || nestedEmail.message || nestedEmail.text || '').trim();
                    return 'Suggested Email draft:\n\nSubject: ' + (subject || '(no subject)') + '\n\n' + (body || '(no body)');
                }
                return '';
            }

            function resolveDraftPayload(data) {
                if (!data) {
                    return {};
                }

                var candidates = [
                    data.draft,
                    data.draft_payload,
                    data.draft_payload_json,
                    data.payload,
                    data.item && data.item.draft_payload_json,
                    data.queue_item && data.queue_item.draft_payload_json,
                    data.action_item && data.action_item.draft_payload_json,
                    data.data && data.data.draft,
                    data.data && data.data.payload,
                    data.draft_preview
                ];

                for (var i = 0; i < candidates.length; i += 1) {
                    var resolved = parseDraftCandidate(candidates[i]);
                    if (resolved && typeof resolved === 'object' && Object.keys(resolved).length > 0) {
                        return resolved;
                    }
                }

                return {};
            }

            function buildQuickActions() {
                if (!quickActions) {
                    return;
                }
                quickActions.innerHTML = '';
                quickActionItems.forEach(function (entry) {
                    var button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'quick-action-btn';
                    button.textContent = entry.label;
                    button.addEventListener('click', function () {
                        sendPrompt('', entry.quick_action);
                    });
                    quickActions.appendChild(button);
                });
            }

            if (quickActionsToggle && quickActions) {
                quickActionsToggle.addEventListener('click', function () {
                    var isOpen = quickActions.classList.toggle('open');
                    quickActionsToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                    quickActionsToggle.textContent = isOpen ? 'Hide shortcuts' : 'Shortcuts';
                });
            }

            async function runAssistantAction(action) {
                if (!action || !action.type) {
                    return;
                }

                var actionType = String(action.type || '');
                var leadId = Number(action.lead_id || 0);
                if (!leadId && actionType !== 'clear_stale_drafts') {
                    return;
                }
                var actionLabel = String(action.label || actionType);
                var userVerb = actionLabel;
                if (actionType === 'use_draft') {
                    userVerb = 'Use draft';
                } else if (actionType === 'edit_draft') {
                    userVerb = 'Edit draft';
                } else if (actionType === 'cancel_draft') {
                    userVerb = 'Cancel draft';
                }
                createMessage('user', leadId > 0 ? userVerb + ' for lead #' + leadId : userVerb);
                var loadingText = 'Preparing draft for approval...';
                if (actionType === 'mark_reviewed') {
                    loadingText = 'Clearing notification...';
                } else if (actionType === 'clear_stale_drafts') {
                    loadingText = 'Cleaning old approval drafts...';
                }
                var loading = createMessage('assistant', loadingText, [], null, true);
                setBusy(true);

                try {
                    var response = await fetch(endpoint, {
                        method: 'POST',
                        credentials: 'include',
                        cache: 'no-store',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-Token': assistantCsrfToken
                        },
                        body: JSON.stringify({
                            surface: 'mobile',
                            assistant_action: action.type,
                            lead_id: leadId,
                            action_id: Number(action.action_id || 0),
                            target_status: String(action.target_status || ''),
                            consultation_date: String(action.consultation_date || action.appointment_at || ''),
                            note: String(action.note || ''),
                            send_approved: action.type === 'send_draft',
                            stage_approved: action.type === 'move_stage',
                            schedule_approved: action.type === 'schedule_consultation',
                            prompt: action.help || '',
                            instruction: action.help || '',
                            quick_action: '',
                            context: {
                                page: baseContext.page,
                                page_title: baseContext.page_title,
                                current_url: baseContext.current_url,
                                lead_id: leadId,
                                notification: activeNotification ? {
                                    id: String(activeNotification.id || ''),
                                    type: String(activeNotification.type || ''),
                                    title: String(activeNotification.title || ''),
                                    message: String(activeNotification.message || ''),
                                    created_at: String(activeNotification.created_at || ''),
                                    lead_id: Number(activeNotification.lead_id || activeLeadId || 0),
                                    lead_name: String(activeNotification.lead_name || ''),
                                    status: String(activeNotification.status || ''),
                                    suggested_action: String(activeNotification.suggested_action || ''),
                                    is_new: Boolean(activeNotification.is_new)
                                } : null,
                                assistant_thread: assistantConversationContext()
                            }
                        })
                    });
                    var data = await response.json();
                    loading.remove();

                    if (!response.ok || !data.ok) {
                        createMessage('assistant', data.message || 'Assistant action failed.');
                        return;
                    }

                    if (data.current_subject && Number(data.current_subject.lead_id || 0) > 0) {
                        baseContext.lead_id = Number(data.current_subject.lead_id || 0);
                    } else if (Number(data.lead_id || 0) > 0) {
                        baseContext.lead_id = Number(data.lead_id || 0);
                    }

                    if (actionType === 'mark_reviewed') {
                        markNotificationsReadLocally(leadId);
                        createMessage('assistant', data.answer || data.message || 'Notification reviewed.', data.cards || [], normalizeAssistantActions(data.actions || [], data.lead_id || leadId));
                        refreshPendingDrafts();
                        return;
                    }

                    if (actionType === 'move_stage' || actionType === 'add_note' || actionType === 'schedule_consultation') {
                        createMessage('assistant', data.answer || data.message || 'Action completed.', data.cards || [], normalizeAssistantActions(data.actions || [], data.lead_id || leadId));
                        refreshPendingDrafts();
                        return;
                    }

                    if (actionType === 'cancel_draft') {
                        setDraftStatus('');
                        refreshPendingDrafts();
                        createMessage('assistant', data.message || 'Draft cancelled.');
                        return;
                    }

                    if (actionType === 'clear_stale_drafts') {
                        setDraftStatus('');
                        applyPendingDrafts(Array.isArray(data.pending_drafts) ? data.pending_drafts : []);
                        createMessage('assistant', data.answer || data.message || 'Stale drafts cleared.', data.cards || [], normalizeAssistantActions(data.actions || [], 0));
                        return;
                    }

                    if (actionType === 'send_draft') {
                        setDraftStatus('');
                        applyPendingDrafts(Array.isArray(data.pending_drafts) ? data.pending_drafts : []);
                        createMessage('assistant', data.answer || data.message || 'Draft sent.', data.cards || [], normalizeAssistantActions(data.actions || [], data.lead_id || leadId));
                        refreshPendingDrafts();
                        return;
                    }

                    var draftPayload = resolveDraftPayload(data);
                    var resolvedActionType = resolveDraftActionType(data, actionType);
                    var actionId = Number(data.action_id || action.action_id || 0);
                    if (actionType === 'use_draft' || actionType === 'edit_draft') {
                        if (actionId > 0) {
                            setDraftModeInComposer(
                                draftPayload,
                                resolvedActionType,
                                leadId,
                                actionId,
                                actionType === 'edit_draft' ? 'Editing draft' : 'Reviewing draft'
                            );
                        }
                        var loadedMessage = createMessage('assistant', data.message || 'Draft loaded into composer.');
                        renderDraftCard(loadedMessage, draftPayload, resolvedActionType, actionId, leadId, data, action);
                        if (typeof data.warning === 'string' && data.warning.trim() !== '') {
                            createMessage('assistant', 'Note: ' + String(data.warning));
                        }
                        refreshPendingDrafts();
                        return;
                    }

                    if (draftPayload) {
                        var draftedMessage = createMessage('assistant', data.message || 'Draft prepared for review.');
                        renderDraftCard(draftedMessage, draftPayload, resolvedActionType || actionType, actionId, leadId, data, action);
                        if (typeof data.warning === 'string' && data.warning.trim() !== '') {
                            createMessage('assistant', 'Note: ' + String(data.warning));
                        }
                        setDraftStatus('Reviewing draft | ' + (resolvedActionType === 'draft_sms' ? 'SMS' : 'Email') + ' | Draft only - not sent');
                        refreshPendingDrafts();
                        return;
                    }
                    setDraftStatus('');
                    createMessage('assistant', data.answer || data.message || 'Action completed.', data.cards || [], normalizeAssistantActions(data.actions || [], data.lead_id || leadId));
                } catch (error) {
                    loading.remove();
                    createMessage('assistant', 'I could not reach Elite AI right now. Please try again.');
                } finally {
                    setBusy(false);
                    input.focus();
                }
            }

            async function sendPrompt(prompt, quickAction, options) {
                var settings = options || {};
                var cleanPrompt = (prompt || '').trim();
                if (!cleanPrompt && !quickAction) {
                    return;
                }

                if (!settings.silentUser) {
                    createMessage('user', cleanPrompt || String(quickAction || ''));
                }
                var loading = createMessage('assistant', settings.loadingText || 'Thinking...', [], null, true);
                setBusy(true);

                try {
                    var response = await fetch(endpoint, {
                        method: 'POST',
                        credentials: 'include',
                        cache: 'no-store',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-Token': assistantCsrfToken
                        },
                        body: JSON.stringify({
                            surface: 'mobile',
                            prompt: cleanPrompt,
                            quick_action: quickAction || '',
                            context: assistantContext()
                        })
                    });

                    var data = await response.json();
                    loading.remove();

                    if (!response.ok || !data.ok) {
                        createMessage('assistant', data.message || 'I could not complete that request right now.');
                        return;
                    }

                    if (data.current_subject && Number(data.current_subject.lead_id || 0) > 0) {
                        baseContext.lead_id = Number(data.current_subject.lead_id || 0);
                    } else if (Number(data.lead_id || 0) > 0) {
                        baseContext.lead_id = Number(data.lead_id || 0);
                    }
                    var responseActionType = String(data.action || data.type || '');
                    if (responseActionType === 'mark_reviewed') {
                        markNotificationsReadLocally(Number(data.lead_id || activeLeadId || 0));
                    }
                    if (responseActionType === 'cancel_draft') {
                        setDraftStatus('');
                        applyPendingDrafts(Array.isArray(data.pending_drafts) ? data.pending_drafts : []);
                        createMessage('assistant', data.answer || data.message || 'Draft cancelled.');
                        return;
                    }
                    if (responseActionType === 'use_draft' || responseActionType === 'edit_draft') {
                        var typedDraftPayload = resolveDraftPayload(data);
                        var typedDraftActionType = resolveDraftActionType(data, responseActionType);
                        var typedDraftActionId = Number(data.action_id || 0);
                        var typedDraftLeadId = Number(data.lead_id || 0);
                        if (typedDraftActionId > 0) {
                            setDraftModeInComposer(
                                typedDraftPayload,
                                typedDraftActionType,
                                typedDraftLeadId,
                                typedDraftActionId,
                                responseActionType === 'edit_draft' ? 'Editing draft' : 'Reviewing draft'
                            );
                        }
                        var typedDraftMessage = createMessage('assistant', data.answer || data.message || 'Draft loaded into composer.');
                        renderDraftCard(typedDraftMessage, typedDraftPayload, typedDraftActionType, typedDraftActionId, typedDraftLeadId, data, {
                            lead_id: typedDraftLeadId,
                            actions: resolveDraftActions(data)
                        });
                        applyPendingDrafts(Array.isArray(data.pending_drafts) ? data.pending_drafts : []);
                        if (typeof data.warning === 'string' && data.warning.trim() !== '') {
                            createMessage('assistant', 'Note: ' + String(data.warning));
                        }
                        return;
                    }
                    var assistantActions = normalizeAssistantActions(data.actions || [], data.lead_id || 0);
                    var assistantMessage = createMessage('assistant', data.answer || 'Ready.', data.cards || [], assistantActions);
                    applyPendingDrafts(Array.isArray(data.pending_drafts) ? data.pending_drafts : []);
                    if (typeof data.warning === 'string' && data.warning.trim() !== '') {
                        createMessage('assistant', 'Note: ' + String(data.warning));
                    } else {
                        setDraftStatus('');
                    }
                } catch (error) {
                    loading.remove();
                    createMessage('assistant', 'I could not reach Elite AI right now. Please try again.');
                } finally {
                    setBusy(false);
                    if (settings.refocus !== false) {
                        input.focus();
                    }
                }
            }

            function activateNotification(item) {
                if (!item || !item.id) {
                    return;
                }
                var nextNotificationId = String(item.id || '');
                var nextLeadId = Number(item.lead_id || 0);
                if (activeNotificationId
                    && nextNotificationId !== activeNotificationId
                    && activeLeadId > 0
                    && nextLeadId > 0
                    && nextLeadId !== activeLeadId) {
                    assistantThreadState = [];
                    saveMobileAssistantThread();
                }
                activeNotification = item;
                activeNotificationId = nextNotificationId;
                activeLeadId = nextLeadId;
                baseContext.lead_id = activeLeadId;
                input.placeholder = 'Tell me what to do with this notification...';
            }

            async function openNotificationConversation(item) {
                activateNotification(item);
                markNotificationsReadLocally(Number(item && item.lead_id ? item.lead_id : 0));
                await sendPrompt('review this', '', {
                    silentUser: true,
                    loadingText: 'Opening notification...',
                    refocus: false
                });
            }

            function canAutoOpenNotificationArrival() {
                if (!input || input.disabled) {
                    return false;
                }
                if (document.activeElement === input) {
                    return false;
                }
                if (String(input.value || '').trim() !== '') {
                    return false;
                }
                return true;
            }

            function arrivalAssistantPrompt(item) {
                var leadName = String(item && item.lead_name ? item.lead_name : '').trim();
                var prompt = String(item && item.assistant_prompt ? item.assistant_prompt : '').trim();
                if (prompt !== '') {
                    return prompt;
                }
                if (leadName !== '') {
                    return 'Rod, we have a new notification from ' + leadName + '. What do you want me to do?';
                }
                return 'Rod, we have a new CRM notification. What do you want me to do?';
            }

            var knownNotificationIds = new Set((Array.isArray(notificationSeed) ? notificationSeed : []).map(notificationIdentity));
            var notificationPollRunning = false;

            function scheduleNotificationPoll(delay) {
                if (notificationPollTimer) {
                    window.clearTimeout(notificationPollTimer);
                }
                var fallbackDelay = document.hidden ? 12000 : 2000;
                notificationPollTimer = window.setTimeout(pollNotifications, Number(delay || fallbackDelay));
            }

            async function pollNotifications() {
                if (notificationPollRunning) {
                    scheduleNotificationPoll();
                    return;
                }
                notificationPollRunning = true;
                var nextPollMs = document.hidden ? 12000 : 2000;
                try {
                    var response = await fetch(notificationFeedEndpoint, {
                        credentials: 'include',
                        cache: 'no-store',
                        headers: { 'Accept': 'application/json' }
                    });
                    var data = await response.json();
                    if (!response.ok || !data.ok || !Array.isArray(data.notifications)) {
                        return;
                    }
                    nextPollMs = document.hidden ? 12000 : (Number(data.poll_after_ms || 0) || 2000);
                    notificationVersion = String(data.version || notificationVersion || '');

                    var arrivals = data.notifications.filter(function (item) {
                        return Boolean(item && item.is_new) && !knownNotificationIds.has(notificationIdentity(item));
                    });
                    notificationSeed = data.notifications;
                    notificationSeed.forEach(function (item) {
                        knownNotificationIds.add(notificationIdentity(item));
                    });
                    if (activeNotificationId) {
                        activeNotification = notificationSeed.find(function (item) {
                            return String(item && item.id ? item.id : '') === activeNotificationId;
                        }) || activeNotification;
                    }
                    syncAppBadge(unreadNotificationCount());

                    if (arrivals.length > 0) {
                        var seen = seenNotificationIds();
                        var seenSet = new Set(seen);
                        arrivals.forEach(function (item) {
                            seenSet.add(notificationIdentity(item));
                        });
                        saveSeenNotificationIds(Array.from(seenSet));
                        playNotificationSound();

                        if (canAutoOpenNotificationArrival()) {
                            await openNotificationConversation(arrivals[0]);
                        } else if (document.visibilityState === 'visible') {
                            createMessage('assistant', arrivalAssistantPrompt(arrivals[0]));
                        }
                    }
                } catch (error) {
                    // The next poll retries without interrupting the conversation.
                } finally {
                    notificationPollRunning = false;
                    scheduleNotificationPoll(nextPollMs);
                }
            }

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                var prompt = input.value;
                input.value = '';
                sendPrompt(prompt);
            });

            thread.addEventListener('click', function (event) {
                var target = event.target;
                var button = target && target.closest ? target.closest('.mobile-ai-action-button') : null;
                if (!button || !thread.contains(button)) {
                    return;
                }
                event.preventDefault();
                runAssistantAction(buttonAssistantAction(button, {}));
            });

            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
                    event.preventDefault();
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
                    }
                }
            });
            input.addEventListener('focus', function () {
                scheduleAssistantViewportSync();
                window.setTimeout(scheduleAssistantViewportSync, 120);
                window.setTimeout(scheduleAssistantViewportSync, 320);
            });
            input.addEventListener('blur', function () {
                window.setTimeout(scheduleAssistantViewportSync, 80);
            });
            window.addEventListener('resize', scheduleAssistantViewportSync);
            window.addEventListener('orientationchange', scheduleAssistantViewportSync);
            if (visualViewport) {
                visualViewport.addEventListener('resize', scheduleAssistantViewportSync);
                visualViewport.addEventListener('scroll', scheduleAssistantViewportSync);
            }

            if (mic) {
                mic.addEventListener('click', function () {
                    if (isListening) {
                        stopAssistantListening();
                        setDraftStatus('Voice stopped.');
                        return;
                    }
                    startAssistantListening();
                });
            }

            buildQuickActions();
            ensureStatusNode();
            syncAssistantViewport();
            if (activeNotification) {
                openNotificationConversation(activeNotification);
            } else if (initialFreshNotifications.length > 0) {
                openNotificationConversation(initialFreshNotifications[0]);
            }
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) {
                    pollNotifications();
                } else {
                    scheduleNotificationPoll(12000);
                }
            });
            window.addEventListener('focus', pollNotifications);
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.addEventListener('message', function (event) {
                    if (event.data && event.data.type === 'elite-ai-notification') {
                        pollNotifications();
                    } else if (event.data && event.data.type === 'elite-ai-notification-opened' && event.data.url) {
                        window.location.assign(String(event.data.url));
                    }
                });
            }
            scheduleNotificationPoll(2000);
        }());
    </script>
    <?php if ($tab === 'notifications'): ?>
    <script>
        (function () {
            var currentVersion = <?= json_encode($notificationVersion, JSON_UNESCAPED_SLASHES) ?>;
            var feedUrl = <?= json_encode(base_url('mobile-ai/?notification_feed=1'), JSON_UNESCAPED_SLASHES) ?>;
            var assistantUrl = <?= json_encode(base_url('mobile-ai/?tab=assistant'), JSON_UNESCAPED_SLASHES) ?>;
            var list = document.getElementById('mobile-notifications-list');
            var timer = null;
            var running = false;

            function notificationTime(value) {
                var date = new Date(String(value || '').replace(' ', 'T'));
                if (!Number.isFinite(date.getTime())) return String(value || '');
                return date.toLocaleString([], {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit'
                });
            }

            function appendNotificationText(parent, text, className) {
                var clean = String(text || '').trim();
                if (!clean) return;
                var paragraph = document.createElement('p');
                if (className) paragraph.className = className;
                paragraph.textContent = clean;
                parent.appendChild(paragraph);
            }

            function renderNotifications(notifications) {
                if (!list) return;
                var items = Array.isArray(notifications) ? notifications.slice(0, 5) : [];
                list.innerHTML = '';
                if (items.length === 0) {
                    var empty = document.createElement('p');
                    empty.className = 'empty';
                    empty.textContent = 'No notifications right now.';
                    list.appendChild(empty);
                    return;
                }

                items.forEach(function (item) {
                    var leadId = Number(item && item.lead_id ? item.lead_id : 0);
                    var notificationId = String(item && item.id ? item.id : '');
                    var isUnread = Boolean(item && item.is_new);
                    var article = document.createElement(leadId > 0 ? 'a' : 'article');
                    article.className = 'notification ' + (isUnread ? 'unread' : 'read');
                    if (leadId > 0) {
                        var target = new URL(assistantUrl, window.location.origin);
                        target.searchParams.set('notification_id', notificationId);
                        target.searchParams.set('lead_id', String(leadId));
                        article.href = target.toString();
                        article.setAttribute('aria-label', 'Open notification in Assistant');
                    }

                    var heading = document.createElement('h2');
                    heading.appendChild(document.createTextNode(String(item && item.title ? item.title : 'CRM alert') + ' '));
                    var state = document.createElement('span');
                    state.className = 'notification-state';
                    state.textContent = isUnread ? 'Unread' : 'Read';
                    heading.appendChild(state);
                    article.appendChild(heading);

                    appendNotificationText(article, item && item.assistant_summary ? item.assistant_summary : '');
                    appendNotificationText(article, item && item.assistant_prompt ? item.assistant_prompt : '', 'meta');
                    appendNotificationText(article, item && item.message ? item.message : '');
                    appendNotificationText(
                        article,
                        notificationTime(item && item.created_at ? item.created_at : '')
                            + (item && item.lead_name ? ' - ' + String(item.lead_name) : ''),
                        'meta'
                    );

                    if (leadId > 0) {
                        var open = document.createElement('span');
                        open.className = 'open-link';
                        open.textContent = 'Open in Assistant';
                        article.appendChild(open);
                    }
                    list.appendChild(article);
                });
            }

            async function syncBadges(notifications) {
                var unread = (Array.isArray(notifications) ? notifications : []).filter(function (item) {
                    return Boolean(item && item.is_new);
                }).length;
                try {
                    if (unread > 0 && 'setAppBadge' in navigator) {
                        await navigator.setAppBadge(unread);
                    } else if (unread === 0 && 'clearAppBadge' in navigator) {
                        await navigator.clearAppBadge();
                    }
                } catch (error) {
                    // The live notification list remains authoritative when badging is unavailable.
                }
            }

            function schedule(delay) {
                if (timer) window.clearTimeout(timer);
                timer = window.setTimeout(checkForChanges, Number(delay || (document.hidden ? 12000 : 2000)));
            }

            async function checkForChanges() {
                if (running) return;
                running = true;
                var nextDelay = document.hidden ? 12000 : 2000;
                try {
                    var response = await fetch(feedUrl, {
                        credentials: 'include',
                        cache: 'no-store',
                        headers: { 'Accept': 'application/json' }
                    });
                    var data = await response.json();
                    if (!response.ok || !data.ok || !data.version) return;
                    nextDelay = document.hidden ? 12000 : (Number(data.poll_after_ms || 0) || 2000);
                    if (String(data.version) !== currentVersion) {
                        currentVersion = String(data.version);
                        renderNotifications(data.notifications);
                        syncBadges(data.notifications);
                    }
                } catch (error) {
                    // Keep the current list visible and retry quietly.
                } finally {
                    running = false;
                    schedule(nextDelay);
                }
            }

            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) checkForChanges();
            });
            window.addEventListener('focus', checkForChanges);
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.addEventListener('message', function (event) {
                    if (event.data && event.data.type === 'elite-ai-notification') {
                        checkForChanges();
                    } else if (event.data && event.data.type === 'elite-ai-notification-opened' && event.data.url) {
                        window.location.assign(String(event.data.url));
                    }
                });
            }
            schedule(2000);
        }());
    </script>
    <?php endif; ?>
</body>
</html>
