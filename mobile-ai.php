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
$tab = strtolower(trim((string) get('tab', 'assistant')));
if (!in_array($tab, ['assistant', 'notifications'], true)) {
    $tab = 'assistant';
}
$showWelcome = get('welcome') === '1';
$notifications = elite_ai_notification_rows(5);
$fullName = trim(($mobileUser['first_name'] ?? '') . ' ' . ($mobileUser['last_name'] ?? ''));
$firstName = trim((string) ($mobileUser['first_name'] ?? ''));
$displayName = $firstName !== '' ? $firstName : ($fullName !== '' ? $fullName : 'Rodrigo');
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
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body {
            min-height: 100vh;
            background: var(--bg);
            color: var(--ink);
            font-family: Arial, Helvetica, sans-serif;
        }
        .app {
            width: min(100%, 760px);
            min-height: 100vh;
            margin: 0 auto;
            display: grid;
            grid-template-rows: auto 1fr auto;
            padding: env(safe-area-inset-top) 14px env(safe-area-inset-bottom);
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
        .thread {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding: 12px 0 18px;
            overflow: visible;
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
            position: sticky;
            bottom: 0;
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
            font-size: 15px;
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
        .notifications {
            display: grid;
            gap: 10px;
            padding: 12px 0 22px;
        }
        .notification {
            border: 1px solid var(--line);
            border-radius: 14px;
            background: var(--panel);
            padding: 12px 14px;
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
            <a class="icon-btn <?= $tab === 'notifications' ? 'active' : '' ?>" href="<?= e(base_url($tab === 'notifications' ? 'mobile-ai/?tab=assistant' : 'mobile-ai/?tab=notifications')) ?>" aria-label="<?= $tab === 'notifications' ? 'Back to assistant' : 'Open notifications' ?>">
                <?php if ($tab === 'notifications'): ?>
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"></path></svg>
                <?php else: ?>
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path><path d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path></svg>
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

            <section class="pending-drafts" id="assistant-pending-drafts" aria-label="Pending Elite AI drafts">
                <p class="pending-drafts-title" id="assistant-pending-drafts-title">Pending drafts</p>
                <div class="pending-drafts-list" id="assistant-pending-drafts-list"></div>
            </section>

            <section class="composer-wrap" aria-label="Assistant composer">
                <div class="quick-actions-shell">
                    <button class="quick-actions-toggle" id="assistant-quick-actions-toggle" type="button" aria-expanded="false" aria-controls="assistant-quick-actions">Shortcuts</button>
                    <div class="quick-actions" id="assistant-quick-actions" aria-label="Quick actions"></div>
                </div>
                <form class="composer" id="assistant-composer">
                    <input id="assistant-input" type="text" placeholder="Ask Elite AI what to do..." autocomplete="off" enterkeyhint="send">
                    <button id="assistant-mic" type="button" aria-label="Microphone placeholder">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3z"></path><path d="M19 10v2a7 7 0 0 1-14 0v-2"></path><path d="M12 19v3"></path></svg>
                    </button>
                    <button type="submit" aria-label="Send">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2 11 13"></path><path d="m22 2-7 20-4-9-9-4 20-7z"></path></svg>
                    </button>
                </form>
            </section>
        <?php else: ?>
            <section class="notifications" aria-label="Notifications">
                <?php if (!$notifications): ?>
                    <p class="empty">No notifications right now.</p>
                <?php endif; ?>

                <?php foreach ($notifications as $item): ?>
                    <?php $isUnread = !empty($item['is_new']); ?>
                    <article class="notification <?= $isUnread ? 'unread' : 'read' ?>">
                        <h2>
                            <?= e((string) ($item['title'] ?? 'CRM alert')) ?>
                            <span class="notification-state"><?= $isUnread ? 'Unread' : 'Read' ?></span>
                        </h2>
                        <?php if (trim((string) ($item['message'] ?? '')) !== ''): ?>
                            <p><?= e((string) ($item['message'] ?? '')) ?></p>
                        <?php endif; ?>
                        <p class="meta">
                            <?= e(format_datetime((string) ($item['created_at'] ?? ''), 'M j, Y g:i A')) ?>
                            <?php if (!empty($item['lead_name'])): ?>
                                - <?= e((string) $item['lead_name']) ?>
                            <?php endif; ?>
                        </p>
                        <?php $assistantCard = is_array($item['assistant_card'] ?? null) ? $item['assistant_card'] : []; ?>
                        <?php if ((int) ($item['lead_id'] ?? 0) > 0): ?>
                            <?php
                                $notificationAssistantUrl = base_url('mobile-ai/?tab=assistant'
                                    . '&notification_id=' . rawurlencode((string) ($item['id'] ?? ''))
                                    . '&lead_id=' . (int) ($item['lead_id'] ?? 0));
                            ?>
                            <a class="open-link" href="<?= e($notificationAssistantUrl) ?>">Ask Elite AI</a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('<?= e(base_url('mobile-ai/sw.js')) ?>').catch(function () {});
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
            if (!thread || !form || !input) {
                return;
            }

            var endpoint = '<?= e((string) (parse_url(base_url('assistant-api-live.php'), PHP_URL_PATH) ?: '/crm/assistant-api-live.php')) ?>';
            var notificationSeed = <?= json_encode($notifications, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
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

            function isStandaloneApp() {
                return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
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
                        var permission = await Notification.requestPermission();
                        if (permission === 'granted' && 'serviceWorker' in navigator) {
                            await navigator.serviceWorker.ready;
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
                        is_new: Boolean(activeNotification.is_new)
                    };
                    context.lead_id = Number(activeNotification.lead_id || activeLeadId || 0);
                }
                context.assistant_thread = assistantConversationContext();
                return context;
            }

            var quickActionItems = [
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
                if (actions && actions.length) {
                    var actionWrap = document.createElement('div');
                    actions.forEach(function (action) {
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
                article.scrollIntoView({ behavior: 'smooth', block: 'end' });
                if (!isLoading) {
                    assistantThreadState.push({
                        role: role === 'user' ? 'user' : 'assistant',
                        text: String(text || ''),
                        created_at: Date.now()
                    });
                    assistantThreadState = assistantThreadState.slice(-30);
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
                    return normalized;
                }).filter(function (action) {
                    return action.type && action.lead_id;
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
                            'Accept': 'application/json'
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
                if (!action || !action.type || !action.lead_id) {
                    return;
                }

                var actionType = String(action.type || '');
                var leadId = Number(action.lead_id || 0);
                var actionLabel = String(action.label || actionType);
                var userVerb = actionLabel;
                if (actionType === 'use_draft') {
                    userVerb = 'Use draft';
                } else if (actionType === 'edit_draft') {
                    userVerb = 'Edit draft';
                } else if (actionType === 'cancel_draft') {
                    userVerb = 'Cancel draft';
                }
                createMessage('user', userVerb + ' for lead #' + Number(action.lead_id));
                var loading = createMessage('assistant', actionType === 'mark_reviewed' ? 'Clearing notification...' : 'Preparing draft for approval...', [], null, true);
                setBusy(true);

                try {
                    var response = await fetch(endpoint, {
                        method: 'POST',
                        credentials: 'include',
                        cache: 'no-store',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            surface: 'mobile',
                            assistant_action: action.type,
                            lead_id: Number(action.lead_id || 0),
                            action_id: Number(action.action_id || 0),
                            target_status: String(action.target_status || ''),
                            note: String(action.note || ''),
                            prompt: action.help || '',
                            instruction: action.help || '',
                            quick_action: '',
                            context: {
                                page: baseContext.page,
                                page_title: baseContext.page_title,
                                current_url: baseContext.current_url,
                                lead_id: Number(action.lead_id || 0),
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
                        createMessage('assistant', data.answer || data.message || 'Notification reviewed.', data.cards || [], normalizeAssistantActions(data.actions || [], data.lead_id || leadId));
                        refreshPendingDrafts();
                        return;
                    }

                    if (actionType === 'move_stage' || actionType === 'add_note') {
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
                    createMessage('assistant', 'The draft action completed, but no usable preview came back. Queue item: ' + String(actionId));
                } catch (error) {
                    loading.remove();
                    createMessage('assistant', 'I could not reach Elite AI right now. Please try again.');
                } finally {
                    setBusy(false);
                    input.focus();
                }
            }

            async function sendPrompt(prompt, quickAction) {
                var cleanPrompt = (prompt || '').trim();
                if (!cleanPrompt && !quickAction) {
                    return;
                }

                createMessage('user', cleanPrompt || String(quickAction || ''));
                var loading = createMessage('assistant', 'Thinking...', [], null, true);
                setBusy(true);

                try {
                    var response = await fetch(endpoint, {
                        method: 'POST',
                        credentials: 'include',
                        cache: 'no-store',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
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
                    input.focus();
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
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    form.requestSubmit();
                }
            });

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
            if (activeNotification) {
                var notificationLeadId = Number(activeNotification.lead_id || activeLeadId || 0);
                var notificationText = String(activeNotification.message || '').trim();
                var notificationTitle = String(activeNotification.title || 'this notification').trim();
                var assistantIntro = 'I am looking at ' + notificationTitle + (notificationText ? ':\n\n"' + notificationText + '"' : '') + '\n\nWhat do you want to do? I can draft a reply for approval, mark it reviewed, or help update the lead.';
                var notificationActions = [];
                if (notificationLeadId > 0) {
                    notificationActions.push({
                        type: 'draft_sms',
                        label: 'Draft SMS reply',
                        lead_id: notificationLeadId,
                        help: 'Review this notification in context and prepare a warm SMS reply draft for approval. Do not send.'
                    });
                    notificationActions.push({
                        type: 'draft_email',
                        label: 'Draft Email reply',
                        lead_id: notificationLeadId,
                        help: 'Review this notification in context and prepare an email reply draft for approval. Do not send.'
                    });
                    notificationActions.push({
                        type: 'mark_reviewed',
                        label: 'Mark reviewed',
                        lead_id: notificationLeadId,
                        help: 'Mark this notification reviewed. Do not send any patient-facing message.'
                    });
                }
                createMessage('assistant', assistantIntro, [], notificationActions);
                input.placeholder = 'Tell me what to do with this notification...';
            }
            refreshPendingDrafts();
        }());
    </script>
</body>
</html>
