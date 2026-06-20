<?php
declare(strict_types=1);

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/core/db.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/core/mobile_ai_auth.php';
require_once __DIR__ . '/app/leads/lead_communications.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('Referrer-Policy: strict-origin-when-cross-origin');

lead_comm_ensure_schema();
mobile_ai_ensure_schema();

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

function mobile_ai_portal_notification_rows(int $limit = 20): array
{
    $limit = max(1, min(50, $limit));
    $items = [];

    try {
        $messages = db_all(
            "SELECT
                lm.id,
                lm.lead_id,
                lm.direction,
                lm.channel,
                lm.body,
                lm.is_read,
                lm.created_at,
                l.full_name,
                l.status
             FROM lead_messages lm
             INNER JOIN leads l ON l.id = lm.lead_id
             WHERE lm.direction = 'inbound'
             ORDER BY lm.created_at DESC, lm.id DESC
             LIMIT {$limit}"
        );

        foreach ($messages as $row) {
            $leadId = (int) ($row['lead_id'] ?? 0);
            $leadName = trim((string) ($row['full_name'] ?? ''));
            $items[] = [
                'id' => 'msg-' . (int) ($row['id'] ?? 0),
                'type' => 'reply',
                'title' => ($leadName !== '' ? $leadName : 'Lead reply') . ($leadId > 0 ? ' - Lead #' . $leadId : ''),
                'message' => trim((string) ($row['body'] ?? '')),
                'priority' => ((int) ($row['is_read'] ?? 0) === 0) ? 'high' : 'normal',
                'is_new' => (int) ($row['is_read'] ?? 0) === 0,
                'lead_id' => $leadId,
                'lead_name' => $leadName,
                'status' => trim((string) ($row['status'] ?? '')),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'suggested_action' => 'Review the reply and prepare a draft before sending.',
                'open_url' => base_url('leads.php?lead_id=' . $leadId),
            ];
        }
    } catch (Throwable $e) {
        esm_log('mobile_ai', 'Could not load inbound message notifications', ['error' => $e->getMessage()]);
    }

    try {
        $activities = db_all(
            "SELECT
                la.id,
                la.lead_id,
                la.type,
                la.body,
                la.created_at,
                l.full_name,
                l.status
             FROM lead_activities la
             INNER JOIN leads l ON l.id = la.lead_id
             WHERE la.type IN ('lead_created', 'stage_change', 'consultation_scheduled', 'follow_up_due', 'manual_sms_followup_prepared')
             ORDER BY la.created_at DESC, la.id DESC
             LIMIT {$limit}"
        );

        foreach ($activities as $row) {
            $type = trim((string) ($row['type'] ?? 'activity'));
            $leadId = (int) ($row['lead_id'] ?? 0);
            $leadName = trim((string) ($row['full_name'] ?? ''));
            $label = match ($type) {
                'lead_created' => 'New lead',
                'stage_change' => 'Pipeline update',
                'consultation_scheduled' => 'Consultation alert',
                'follow_up_due' => 'Follow-up alert',
                'manual_sms_followup_prepared' => 'Draft ready',
                default => 'CRM alert',
            };

            $items[] = [
                'id' => 'act-' . (int) ($row['id'] ?? 0),
                'type' => $type,
                'title' => $label . ': ' . ($leadName !== '' ? $leadName : 'Lead') . ($leadId > 0 ? ' - Lead #' . $leadId : ''),
                'message' => trim((string) ($row['body'] ?? '')),
                'priority' => in_array($type, ['lead_created', 'follow_up_due', 'consultation_scheduled'], true) ? 'high' : 'normal',
                'is_new' => false,
                'lead_id' => $leadId,
                'lead_name' => $leadName,
                'status' => trim((string) ($row['status'] ?? '')),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'suggested_action' => $type === 'lead_created'
                    ? 'Review lead details and confirm first-touch draft before sending.'
                    : 'Open the lead and review the next best step.',
                'open_url' => base_url('leads.php?lead_id=' . $leadId),
            ];
        }
    } catch (Throwable $e) {
        esm_log('mobile_ai', 'Could not load activity notifications', ['error' => $e->getMessage()]);
    }

    usort($items, static function (array $a, array $b): int {
        $aTime = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
        $bTime = strtotime((string) ($b['created_at'] ?? '')) ?: 0;
        return $bTime <=> $aTime;
    });

    return array_slice($items, 0, $limit);
}

$notifications = mobile_ai_portal_notification_rows(18);
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
    <meta name="theme-color" content="#111111">
    <link rel="manifest" href="<?= e(base_url('mobile-ai/manifest.webmanifest')) ?>">
    <style>
        :root {
            --bg: #f4efe7;
            --panel: rgba(255,255,255,0.88);
            --panel-strong: rgba(255,255,255,0.95);
            --ink: #17120f;
            --muted: #6f665d;
            --line: #e6ddd0;
            --gold: #bb8e58;
            --gold-soft: #f7efe2;
            --black: #111111;
            --danger: #9d3b30;
            --shadow: 0 24px 70px rgba(34, 26, 18, 0.12);
            --radius-xl: 28px;
            --radius-lg: 22px;
            --radius-md: 16px;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(187,142,88,0.22), transparent 26%),
                radial-gradient(circle at bottom right, rgba(17,17,17,0.06), transparent 26%),
                linear-gradient(180deg, #fbf8f3 0%, var(--bg) 100%);
            color: var(--ink);
            font-family: Arial, Helvetica, sans-serif;
        }
        .shell {
            width: min(100%, 780px);
            margin: 0 auto;
            padding: calc(env(safe-area-inset-top) + 18px) 16px calc(env(safe-area-inset-bottom) + 26px);
        }
        .hero {
            position: relative;
            overflow: hidden;
            border-radius: var(--radius-xl);
            padding: 22px 20px;
            color: #fff;
            background:
                radial-gradient(circle at top right, rgba(187,142,88,0.35), transparent 26%),
                linear-gradient(160deg, #111111 0%, #241a14 100%);
            box-shadow: var(--shadow);
        }
        .hero .eyebrow {
            display: inline-flex;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.1);
            font-size: 11px;
            letter-spacing: .18em;
            text-transform: uppercase;
            font-weight: 700;
        }
        .hero h1 {
            margin: 14px 0 8px;
            font: 700 clamp(30px, 9vw, 44px)/1 Georgia, "Times New Roman", serif;
            letter-spacing: -.04em;
        }
        .hero p {
            margin: 0;
            max-width: 36rem;
            color: rgba(255,255,255,0.82);
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
            background: var(--black);
            border-color: var(--black);
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
        .welcome-card {
            display: grid;
            gap: 10px;
            padding: 18px;
            border-radius: 22px;
            border: 1px solid rgba(187,142,88,0.32);
            background: linear-gradient(180deg, rgba(255,248,238,0.95), rgba(255,255,255,0.9));
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
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.78);
        }
        .bubble.user {
            margin-left: auto;
            background: #111111;
            border-color: #111111;
            color: #fff;
        }
        .bubble.system {
            background: linear-gradient(180deg, #fff, #f9f4ec);
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
            color: rgba(255,255,255,0.7);
        }
        .bubble p {
            margin: 0;
            font-size: 14px;
            line-height: 1.65;
            color: inherit;
        }
        .assistant-dock {
            position: sticky;
            bottom: 0;
            display: grid;
            gap: 12px;
            margin-top: auto;
            padding: 10px 0 calc(env(safe-area-inset-bottom) + 4px);
            background: linear-gradient(180deg, rgba(244,239,231,0) 0%, rgba(244,239,231,0.92) 18%, rgba(244,239,231,1) 100%);
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
            border: 0;
            outline: 0;
            background: rgba(255,255,255,0.92);
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
            background: var(--black);
            color: #fff;
        }
        .btn.secondary {
            background: #fff;
            border-color: var(--line);
            color: var(--ink);
        }
        .btn.ghost {
            background: rgba(255,255,255,0.92);
            border-color: var(--line);
            color: var(--ink);
        }
        .btn.disabled {
            background: #dad2c7;
            color: #7b7369;
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
            min-height: 48px;
            padding: 12px 14px;
            border-color: var(--line);
            background: linear-gradient(180deg, #fff, #f7f1e8);
            color: var(--ink);
            text-align: center;
            line-height: 1.3;
            font-size: 13px;
        }
        .notification {
            border: 1px solid var(--line);
            border-radius: 20px;
            background: rgba(255,255,255,0.7);
            padding: 16px;
        }
        .notification.new {
            border-color: rgba(187,142,88,0.55);
            background: linear-gradient(180deg, rgba(255,248,238,0.95), rgba(255,255,255,0.85));
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
            background: #f8e7e4;
            color: var(--danger);
        }
        .badge.normal {
            background: var(--gold-soft);
            color: #8b6638;
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
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.7);
        }
        .toggle {
            width: 48px;
            height: 28px;
            border-radius: 999px;
            background: #d9d1c6;
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
            background: #cfa469;
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
            <p>Phase 1 is live as a secure mobile shell: trusted QR access, read-only notifications, and the assistant command surface for the next phase.</p>
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
                    <p class="section-copy">Elite AI is ready to help with leads, replies, follow-ups, and notifications. Type an instruction below to begin.</p>
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
                            <article class="bubble system">
                                <span class="bubble-label">Elite AI</span>
                                <p>This assistant is currently read-only. I can help you think through leads, replies, follow-ups, and notifications without sending messages or changing pipeline stages yet.</p>
                            </article>
                        </div>

                        <div class="assistant-dock">
                            <div class="quick-grid" aria-label="Assistant quick actions">
                                <button class="quick" type="button" data-action="morning-sweep">Morning Sweep</button>
                                <button class="quick" type="button" data-action="new-leads">New Leads</button>
                                <button class="quick" type="button" data-action="replies">Replies</button>
                                <button class="quick" type="button" data-action="follow-ups">Follow-ups</button>
                                <button class="quick" type="button" data-action="no-answer-review">No Answer Review</button>
                                <button class="quick" type="button" data-action="notifications">Notifications</button>
                            </div>

                            <div class="settings">
                                <div class="setting">
                                    <div>
                                        <strong>AI actions</strong>
                                        <p class="meta-copy">The composer is live for read-only guidance. CRM-safe execution and drafts come in the next phase.</p>
                                    </div>
                                    <span class="badge normal">Read Only</span>
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
                    <p class="section-copy">This feed is built from the current CRM communication and activity tables so we can use real signals now without auto-actions.</p>
                </div>
                <div class="section-body">
                    <div class="grid">
                        <?php if (!$notifications): ?>
                            <div class="notification">
                                <p class="notification-title">No notifications yet</p>
                                <p class="notification-copy">The adapter is ready. Once new leads, replies, and follow-up events arrive, they will appear here.</p>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($notifications as $item): ?>
                            <article class="notification <?= !empty($item['is_new']) ? 'new' : '' ?>">
                                <div class="notification-head">
                                    <p class="notification-title"><?= e((string) ($item['title'] ?? 'CRM alert')) ?></p>
                                    <span class="badge <?= e((string) ($item['priority'] ?? 'normal')) ?>">
                                        <?= !empty($item['is_new']) ? 'Unread' : 'Review' ?>
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
                                    <a class="btn secondary" href="<?= e((string) ($item['open_url'] ?? base_url('leads.php'))) ?>">Open Lead</a>
                                    <button class="btn disabled" type="button" disabled>Ask AI</button>
                                    <button class="btn disabled" type="button" disabled>Mark Reviewed</button>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="settings">
                        <div class="setting">
                            <div>
                                <strong>Push notifications</strong>
                                <p class="meta-copy">Browser subscription storage is scaffolded. Sending logic can plug in next.</p>
                            </div>
                            <div class="toggle on" aria-hidden="true"></div>
                        </div>
                        <div class="setting">
                            <div>
                                <strong>Sound alerts</strong>
                                <p class="meta-copy">Enabled as a placeholder. Test playback will require a user tap in Phase 2.</p>
                            </div>
                            <div class="toggle on" aria-hidden="true"></div>
                        </div>
                        <div class="setting">
                            <div>
                                <strong>Quiet hours</strong>
                                <p class="meta-copy">Preference model reserved for future configuration.</p>
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
            var quickActions = {
                'morning-sweep': {
                    prompt: 'Run a morning sweep.',
                    response: 'Morning Sweep is in read-only mode right now. In the next phase, Elite AI will summarize overnight leads, unread replies, and follow-ups due first so you can start the day fast.'
                },
                'new-leads': {
                    prompt: 'Show me the new leads queue.',
                    response: 'New Leads is ready as a guidance action. Phase 2 will let me pull the latest lead list into this thread and surface who needs first touch next.'
                },
                'replies': {
                    prompt: 'Show me the latest replies.',
                    response: 'Replies is connected conceptually to the live notifications feed. For now, tap Notifications to review the newest inbound messages in read-only mode.'
                },
                'follow-ups': {
                    prompt: 'What follow-ups should I review?',
                    response: 'Follow-ups is staged for read-only coaching first. Phase 2 will let me summarize due follow-ups and suggest the next best manual action.'
                },
                'no-answer-review': {
                    prompt: 'Review no-answer leads.',
                    response: 'No Answer Review is intentionally held in safe mode. Once connected, this action will identify leads with repeated outreach and no response without moving any stages automatically.'
                },
                'notifications': {
                    prompt: 'Open notifications.',
                    response: 'Opening the live notifications feed now so you can review replies, new leads, and follow-up alerts with full CRM context.'
                }
            };

            var thread = document.getElementById('assistant-thread');
            var form = document.getElementById('assistant-composer');
            var input = document.getElementById('assistant-input');
            var mic = document.getElementById('assistant-mic');
            if (!thread || !form || !input) {
                return;
            }

            function appendBubble(label, text, role) {
                var article = document.createElement('article');
                article.className = 'bubble ' + role;

                var badge = document.createElement('span');
                badge.className = 'bubble-label';
                badge.textContent = label;

                var paragraph = document.createElement('p');
                paragraph.textContent = text;

                article.appendChild(badge);
                article.appendChild(paragraph);
                thread.appendChild(article);
                article.scrollIntoView({ behavior: 'smooth', block: 'end' });
            }

            function handlePrompt(prompt, actionKey) {
                var cleanPrompt = (prompt || '').trim();
                if (cleanPrompt === '') {
                    return;
                }

                appendBubble('You', cleanPrompt, 'user');

                if (actionKey === 'notifications') {
                    appendBubble('Elite AI', quickActions[actionKey].response, 'system');
                    window.setTimeout(function () {
                        window.location.href = '<?= e(base_url('mobile-ai/?tab=notifications')) ?>';
                    }, 350);
                    return;
                }

                if (actionKey && quickActions[actionKey]) {
                    appendBubble('Elite AI', quickActions[actionKey].response, 'system');
                    return;
                }

                appendBubble('Elite AI', 'I heard: "' + cleanPrompt + '". The composer is live in read-only mode, so I can confirm the request and guide the next step, but I am not sending messages or changing lead stages yet.', 'system');
            }

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                var prompt = input.value;
                input.value = '';
                handlePrompt(prompt, '');
            });

            document.querySelectorAll('[data-action]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var key = button.getAttribute('data-action') || '';
                    if (!quickActions[key]) {
                        return;
                    }
                    input.value = quickActions[key].prompt;
                    handlePrompt(quickActions[key].prompt, key);
                    input.value = '';
                });
            });

            if (mic) {
                mic.addEventListener('click', function () {
                    appendBubble('Elite AI', 'Voice capture is reserved for a later phase. For now, type a command and I will keep the flow read-only and review-safe.', 'system');
                });
            }
        }());
    </script>
</body>
</html>
