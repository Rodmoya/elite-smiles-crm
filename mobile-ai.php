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
$notifications = elite_ai_notification_rows(18);
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
        .result-card {
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #f9fafb;
            padding: 10px 12px;
        }
        .result-card-title {
            margin: 0 0 6px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0;
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
        .notification.priority {
            border-color: #fecaca;
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
                    <article class="notification <?= ($item['priority'] ?? 'normal') === 'high' ? 'priority' : '' ?>">
                        <h2><?= e((string) ($item['title'] ?? 'CRM alert')) ?></h2>
                        <?php if (trim((string) ($item['message'] ?? '')) !== ''): ?>
                            <p><?= e((string) ($item['message'] ?? '')) ?></p>
                        <?php endif; ?>
                        <p class="meta">
                            <?= e(format_datetime((string) ($item['created_at'] ?? ''), 'M j, Y g:i A')) ?>
                            <?php if (!empty($item['lead_name'])): ?>
                                - <?= e((string) $item['lead_name']) ?>
                            <?php endif; ?>
                        </p>
                        <?php if ((int) ($item['lead_id'] ?? 0) > 0): ?>
                            <a class="open-link" href="<?= e(base_url('leads.php?lead_id=' . (int) ($item['lead_id'] ?? 0))) ?>">Open lead</a>
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
            if (!thread || !form || !input) {
                return;
            }

            var endpoint = '<?= e(base_url('assistant-api.php')) ?>';
            var baseContext = {
                page: 'mobile-ai',
                page_title: 'Elite AI Mobile Portal',
                current_url: window.location.href,
                lead_id: 0,
                tab: 'assistant'
            };

            function createMessage(role, text, cards, isLoading) {
                var article = document.createElement('article');
                article.className = 'message ' + role + (isLoading ? ' loading' : '');

                var paragraph = document.createElement('p');
                paragraph.textContent = text;
                article.appendChild(paragraph);

                if (cards && cards.length) {
                    var cardsWrap = document.createElement('div');
                    cardsWrap.className = 'cards';
                    cards.forEach(function (card) {
                        var cardEl = document.createElement('div');
                        cardEl.className = 'result-card';

                        var title = document.createElement('p');
                        title.className = 'result-card-title';
                        title.textContent = card.title || 'Summary';
                        cardEl.appendChild(title);

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

                thread.appendChild(article);
                article.scrollIntoView({ behavior: 'smooth', block: 'end' });
                return article;
            }

            function setBusy(isBusy) {
                input.disabled = isBusy;
                form.querySelectorAll('button').forEach(function (button) {
                    button.disabled = isBusy;
                });
            }

            async function sendPrompt(prompt) {
                var cleanPrompt = (prompt || '').trim();
                if (!cleanPrompt) {
                    return;
                }

                createMessage('user', cleanPrompt);
                var loading = createMessage('assistant', 'Thinking...', [], true);
                setBusy(true);

                try {
                    var response = await fetch(endpoint, {
                        method: 'POST',
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            surface: 'mobile',
                            prompt: cleanPrompt,
                            quick_action: '',
                            context: baseContext
                        })
                    });

                    var data = await response.json();
                    loading.remove();

                    if (!response.ok || !data.ok) {
                        createMessage('assistant', data.message || 'I could not complete that request right now.');
                        return;
                    }

                    createMessage('assistant', data.answer || 'Ready.', data.cards || []);
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

            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    form.requestSubmit();
                }
            });

            if (mic) {
                mic.addEventListener('click', function () {
                    createMessage('assistant', 'Voice is not connected yet. Type the request for now.');
                });
            }
        }());
    </script>
</body>
</html>
