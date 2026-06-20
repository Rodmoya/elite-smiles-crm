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
$fullName = $fullName !== '' ? $fullName : (string) ($mobileUser['email'] ?? 'User');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Elite AI</title>
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#0f172a">
    <link rel="manifest" href="<?= e(base_url('mobile-ai/manifest.webmanifest')) ?>">
    <style>
        :root {
            --bg: #f8fafc;
            --panel: rgba(255,255,255,0.94);
            --panel-strong: rgba(255,255,255,0.98);
            --ink: #0f172a;
            --muted: #64748b;
            --line: #dbe3ee;
            --accent: #0f172a;
            --accent-soft: #eef2ff;
            --danger: #b91c1c;
            --shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
            --radius-xl: 28px;
            --radius-lg: 22px;
            --radius-md: 16px;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(148, 163, 184, 0.18), transparent 26%),
                radial-gradient(circle at bottom right, rgba(15, 23, 42, 0.05), transparent 26%),
                linear-gradient(180deg, #ffffff 0%, var(--bg) 100%);
            color: var(--ink);
            font-family: Arial, Helvetica, sans-serif;
        }
        .shell {
            width: min(100%, 780px);
            margin: 0 auto;
            padding: calc(env(safe-area-inset-top) + 18px) 16px calc(env(safe-area-inset-bottom) + 26px);
        }
        .hero {
            overflow: hidden;
            border-radius: var(--radius-xl);
            padding: 22px 20px;
            color: #fff;
            background:
                radial-gradient(circle at top right, rgba(148, 163, 184, 0.25), transparent 28%),
                linear-gradient(160deg, #0f172a 0%, #1e293b 100%);
            box-shadow: var(--shadow);
        }
        .hero .eyebrow {
            display: inline-flex;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.12);
            font-size: 11px;
            letter-spacing: .18em;
            text-transform: uppercase;
            font-weight: 700;
        }
        .hero h1 {
            margin: 14px 0 8px;
            font: 700 clamp(30px, 9vw, 42px)/1 Georgia, "Times New Roman", serif;
            letter-spacing: -.04em;
        }
        .hero p {
            margin: 0;
            max-width: 36rem;
            color: rgba(255,255,255,0.84);
            font-size: 15px;
            line-height: 1.6;
        }
        .hero-meta {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 16px;
        }
        .pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 10px 12px;
            background: rgba(255,255,255,0.12);
            color: #fff;
            font-size: 12px;
            font-weight: 700;
        }
        .tabs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 16px;
        }
        .tab {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            min-height: 52px;
            border-radius: 18px;
            border: 1px solid var(--line);
            background: var(--panel);
            color: var(--ink);
            text-decoration: none;
            font-weight: 700;
            backdrop-filter: blur(12px);
        }
        .tab.active {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
        }
        .section {
            margin-top: 16px;
            border-radius: var(--radius-xl);
            border: 1px solid var(--line);
            background: var(--panel);
            box-shadow: var(--shadow);
            overflow: hidden;
            backdrop-filter: blur(12px);
        }
        .section-head {
            padding: 18px 18px 0;
        }
        .section-body {
            padding: 18px;
        }
        .section h2 {
            margin: 0;
            font: 700 24px/1.1 Georgia, "Times New Roman", serif;
            letter-spacing: -.03em;
        }
        .section-copy {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
        }
        .assistant-layout {
            display: grid;
            gap: 16px;
            min-height: 58vh;
        }
        .welcome-card,
        .setting,
        .notification,
        .bubble,
        .ai-card {
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.88);
        }
        .welcome-card {
            display: grid;
            gap: 10px;
            padding: 18px;
            border-radius: 22px;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
        }
        .welcome-card strong {
            font-size: 18px;
        }
        .assistant-thread {
            display: grid;
            gap: 12px;
            align-content: start;
        }
        .bubble {
            max-width: 100%;
            padding: 16px;
            border-radius: 22px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
        }
        .bubble.user {
            margin-left: auto;
            background: #0f172a;
            border-color: #0f172a;
            color: #fff;
        }
        .bubble.assistant {
            background: linear-gradient(180deg, #ffffff, #f8fafc);
        }
        .bubble.loading {
            color: var(--muted);
        }
        .bubble-label {
            display: block;
            margin-bottom: 8px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--muted);
        }
        .bubble.user .bubble-label {
            color: rgba(255,255,255,0.72);
        }
        .bubble p {
            margin: 0;
            font-size: 14px;
            line-height: 1.65;
            color: inherit;
            white-space: pre-line;
        }
        .assistant-cards {
            display: grid;
            gap: 10px;
            margin-top: 12px;
        }
        .ai-card {
            border-radius: 18px;
            padding: 12px 14px;
            background: #f8fafc;
        }
        .ai-card-title {
            margin: 0 0 8px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--muted);
        }
        .ai-card ul {
            margin: 0;
            padding-left: 18px;
            color: var(--ink);
            font-size: 13px;
            line-height: 1.55;
        }
        .assistant-dock {
            position: sticky;
            bottom: 0;
            display: grid;
            gap: 12px;
            margin-top: auto;
            padding: 10px 0 calc(env(safe-area-inset-bottom) + 4px);
            background: linear-gradient(180deg, rgba(248,250,252,0) 0%, rgba(248,250,252,0.94) 18%, rgba(248,250,252,1) 100%);
        }
        .composer {
            display: grid;
            gap: 10px;
            padding: 12px;
            border-radius: 24px;
            background: var(--panel-strong);
            border: 1px solid var(--line);
            backdrop-filter: blur(14px);
        }
        .composer-row {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 10px;
            align-items: center;
        }
        .composer input {
            width: 100%;
            min-height: 44px;
            border: 1px solid var(--line);
            outline: 0;
            background: #fff;
            color: var(--ink);
            font-size: 15px;
            padding: 0 14px;
            border-radius: 16px;
        }
        .btn, .quick {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid transparent;
            border-radius: 16px;
            text-decoration: none;
            cursor: pointer;
            font-weight: 700;
        }
        .btn {
            min-height: 44px;
            padding: 0 14px;
            background: var(--accent);
            color: #fff;
        }
        .btn.secondary,
        .btn.ghost {
            background: #fff;
            border-color: var(--line);
            color: var(--ink);
        }
        .btn.disabled {
            background: #e2e8f0;
            color: #64748b;
            cursor: not-allowed;
        }
        .grid {
            display: grid;
            gap: 12px;
        }
        .quick-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .quick {
            min-height: 44px;
            padding: 12px 14px;
            border-color: var(--line);
            background: #fff;
            color: var(--ink);
            text-align: center;
            line-height: 1.3;
            font-size: 13px;
        }
        .notification {
            border-radius: 20px;
            padding: 16px;
        }
        .notification.new {
            border-color: #cbd5e1;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
        }
        .notification-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 8px;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .badge.high {
            background: #fee2e2;
            color: var(--danger);
        }
        .badge.normal {
            background: #e2e8f0;
            color: #334155;
        }
        .notification-title {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
        }
        .notification-copy, .meta-copy {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
        }
        .action-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }
        .settings {
            display: grid;
            gap: 10px;
            margin-top: 16px;
        }
        .setting {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 18px;
        }
        .toggle {
            width: 48px;
            height: 28px;
            border-radius: 999px;
            background: #cbd5e1;
            position: relative;
        }
        .toggle:after {
            content: "";
            position: absolute;
            top: 3px;
            left: 3px;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .toggle.on {
            background: #0f172a;
        }
        .toggle.on:after {
            left: 23px;
        }
        .logout {
            margin-top: 16px;
            display: flex;
            justify-content: flex-end;
        }
        @media (max-width: 640px) {
            .hero h1 {
                font-size: 34px;
            }
            .quick-grid {
                gap: 8px;
            }
            .quick {
                flex: 1 1 calc(50% - 8px);
            }
            .composer-row {
                grid-template-columns: 1fr auto;
            }
            .composer-row .btn {
                min-width: 52px;
                padding: 0 12px;
            }
        }
    </style>
</head>
<body>
    <main class="shell">
        <section class="hero">
            <div class="eyebrow">Elite AI</div>
            <h1>Lead ops on your phone</h1>
            <p>Read-only Elite AI is now connected to real CRM data, shared rules, notifications, and lead context so you can review what matters without risking writes.</p>
            <div class="hero-meta">
                <span class="pill"><?= e($fullName) ?></span>
                <span class="pill"><?= e((string) ($mobileUser['role'] ?? 'viewer')) ?></span>
                <span class="pill">Session active</span>
            </div>
        </section>

        <nav class="tabs" aria-label="Mobile AI tabs">
            <a class="tab <?= $tab === 'assistant' ? 'active' : '' ?>" href="<?= e(base_url('mobile-ai/?tab=assistant')) ?>">Assistant</a>
            <a class="tab <?= $tab === 'notifications' ? 'active' : '' ?>" href="<?= e(base_url('mobile-ai/?tab=notifications')) ?>">Notifications</a>
        </nav>

        <?php if ($tab === 'assistant'): ?>
            <section class="section">
                <div class="section-head">
                    <h2>Assistant</h2>
                    <p class="section-copy">Ask Elite AI about leads, replies, follow-ups, No Answer review, notifications, or the next best manual step. This phase stays read-only.</p>
                </div>
                <div class="section-body">
                    <div class="assistant-layout">
                        <?php if ($showWelcome): ?>
                            <div class="welcome-card">
                                <strong>Setup complete</strong>
                                <p class="meta-copy">You are now in the permanent Elite AI portal. Add this page to your Home Screen for daily use so you are not relying on the one-time setup link.</p>
                                <p class="meta-copy">Use the Share button in Safari, then choose <em>Add to Home Screen</em>.</p>
                            </div>
                        <?php endif; ?>

                        <div class="assistant-thread" id="assistant-thread" aria-live="polite">
                            <article class="bubble assistant">
                                <span class="bubble-label">Elite AI</span>
                                <p>Elite AI is ready in read-only mode. Ask me to run a morning sweep, summarize a lead, surface replies, review notifications, or suggest the next manual step.</p>
                            </article>
                        </div>

                        <div class="assistant-dock">
                            <div class="quick-grid" aria-label="Assistant quick actions">
                                <button class="quick" type="button" data-action="morning-sweep" data-prompt="Run morning sweep">Morning Sweep</button>
                                <button class="quick" type="button" data-action="new-leads" data-prompt="Show new leads that need first contact">New Leads</button>
                                <button class="quick" type="button" data-action="replies" data-prompt="Who replied today?">Replies</button>
                                <button class="quick" type="button" data-action="follow-ups" data-prompt="Which contacted leads need follow-up?">Follow-ups</button>
                                <button class="quick" type="button" data-action="no-answer-review" data-prompt="Review No Answer candidates">No Answer Review</button>
                                <button class="quick" type="button" data-action="notifications" data-prompt="What notifications need attention?">Notifications</button>
                            </div>

                            <div class="settings">
                                <div class="setting">
                                    <div>
                                        <strong>Read-only Elite AI</strong>
                                        <p class="meta-copy">This assistant reads CRM data, locked workflow rules, and notifications. It does not send messages or move stages in this phase.</p>
                                    </div>
                                    <span class="badge normal">Safe Mode</span>
                                </div>
                            </div>

                            <form class="composer" id="assistant-composer" aria-label="Assistant command shell">
                                <div class="composer-row">
                                    <input id="assistant-input" type="text" placeholder="Ask Elite AI what to do..." autocomplete="off">
                                    <button class="btn ghost" id="assistant-mic" type="button" aria-label="Microphone placeholder">Mic</button>
                                    <button class="btn" type="submit">Send</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </section>
        <?php else: ?>
            <section class="section">
                <div class="section-head">
                    <h2>Notifications</h2>
                    <p class="section-copy">This feed is built from the current CRM communication and activity tables so the mobile portal can show live replies, new leads, and follow-up alerts without auto-actions.</p>
                </div>
                <div class="section-body">
                    <div class="grid">
                        <?php if (!$notifications): ?>
                            <div class="notification">
                                <p class="notification-title">No notifications yet</p>
                                <p class="notification-copy">Once new replies, lead events, or follow-up alerts arrive, they will appear here.</p>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($notifications as $item): ?>
                            <article class="notification <?= !empty($item['priority']) && $item['priority'] === 'high' ? 'new' : '' ?>">
                                <div class="notification-head">
                                    <p class="notification-title"><?= e((string) ($item['title'] ?? 'CRM alert')) ?></p>
                                    <span class="badge <?= e((string) ($item['priority'] ?? 'normal')) ?>">
                                        <?= ($item['priority'] ?? 'normal') === 'high' ? 'Priority' : 'Review' ?>
                                    </span>
                                </div>
                                <p class="notification-copy"><?= e((string) ($item['message'] ?? '')) ?></p>
                                <p class="meta-copy" style="margin-top:8px;">Suggested next action: <?= e((string) ($item['suggested_action'] ?? 'Open the lead and review context.')) ?></p>
                                <p class="meta-copy" style="margin-top:8px;">
                                    <?= e(format_datetime((string) ($item['created_at'] ?? ''), 'M j, Y g:i A')) ?>
                                    <?php if (!empty($item['lead_name'])): ?>
                                        - <?= e((string) $item['lead_name']) ?>
                                    <?php endif; ?>
                                </p>
                                <div class="action-row">
                                    <a class="btn secondary" href="<?= e(base_url('leads.php?lead_id=' . (int) ($item['lead_id'] ?? 0))) ?>">Open Lead</a>
                                    <button class="btn disabled" type="button" disabled>Ask AI</button>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="settings">
                        <div class="setting">
                            <div>
                                <strong>Push notifications</strong>
                                <p class="meta-copy">Subscription storage is ready. Delivery logic can plug in later without changing the mobile shell.</p>
                            </div>
                            <div class="toggle on" aria-hidden="true"></div>
                        </div>
                        <div class="setting">
                            <div>
                                <strong>Quiet hours</strong>
                                <p class="meta-copy">Preference model remains reserved for future configuration.</p>
                            </div>
                            <span class="badge normal">Placeholder</span>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <div class="logout">
            <form method="POST" action="<?= e(base_url('mobile-ai/')) ?>">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="logout_mobile_ai">
                <button class="btn secondary" type="submit">Logout Mobile</button>
            </form>
        </div>
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

            function createBubble(role, label, text, cards, isLoading) {
                var article = document.createElement('article');
                article.className = 'bubble ' + role + (isLoading ? ' loading' : '');

                var badge = document.createElement('span');
                badge.className = 'bubble-label';
                badge.textContent = label;
                article.appendChild(badge);

                var paragraph = document.createElement('p');
                paragraph.textContent = text;
                article.appendChild(paragraph);

                if (cards && cards.length) {
                    var cardsWrap = document.createElement('div');
                    cardsWrap.className = 'assistant-cards';
                    cards.forEach(function (card) {
                        var cardEl = document.createElement('div');
                        cardEl.className = 'ai-card';

                        var title = document.createElement('p');
                        title.className = 'ai-card-title';
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

            async function sendPrompt(prompt, quickAction) {
                var cleanPrompt = (prompt || '').trim();
                if (!cleanPrompt && !quickAction) {
                    return;
                }

                createBubble('user', 'You', cleanPrompt || 'Run quick action');
                var loading = createBubble('assistant', 'Elite AI', 'Thinking through the CRM context...', [], true);
                setBusy(true);

                try {
                    var response = await fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            surface: 'mobile',
                            prompt: cleanPrompt,
                            quick_action: quickAction || '',
                            context: baseContext
                        })
                    });

                    var data = await response.json();
                    loading.remove();

                    if (!response.ok || !data.ok) {
                        createBubble('assistant', 'Elite AI', data.message || 'I could not complete that request right now.');
                        return;
                    }

                    createBubble('assistant', 'Elite AI', data.answer || 'Read-only response ready.', data.cards || []);
                } catch (error) {
                    loading.remove();
                    createBubble('assistant', 'Elite AI', 'I hit an assistant error while loading CRM context. Please try again.');
                } finally {
                    setBusy(false);
                    input.focus();
                }
            }

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                var prompt = input.value;
                input.value = '';
                sendPrompt(prompt, '');
            });

            document.querySelectorAll('[data-action]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var prompt = button.getAttribute('data-prompt') || '';
                    var action = button.getAttribute('data-action') || '';
                    input.value = '';
                    sendPrompt(prompt, action);
                });
            });

            if (mic) {
                mic.addEventListener('click', function () {
                    createBubble('assistant', 'Elite AI', 'Voice capture is reserved for a later phase. For now, type a command and I will keep the flow read-only.');
                });
            }
        }());
    </script>
</body>
</html>
