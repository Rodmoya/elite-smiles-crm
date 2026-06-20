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
                'title' => ($leadName !== '' ? $leadName : 'Lead reply') . ($leadId > 0 ? ' · Lead #' . $leadId : ''),
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
                'title' => $label . ': ' . ($leadName !== '' ? $leadName : 'Lead') . ($leadId > 0 ? ' · Lead #' . $leadId : ''),
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
        .composer {
            position: sticky;
            bottom: 0;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
            margin-top: 18px;
            padding: 12px;
            border-radius: 24px;
            background: var(--panel-strong);
            border: 1px solid var(--line);
            backdrop-filter: blur(14px);
        }
        .composer input {
            width: 100%;
            border: 0;
            outline: 0;
            background: transparent;
            color: var(--ink);
            font-size: 15px;
            padding: 8px 4px;
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
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        .quick {
            min-height: 64px;
            padding: 14px;
            border-color: var(--line);
            background: linear-gradient(180deg, #fff, #f7f1e8);
            color: var(--ink);
            text-align: center;
            line-height: 1.3;
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
            .quick-grid { grid-template-columns: 1fr 1fr; }
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
            <a class="tab <?= $tab === 'assistant' ? 'active' : '' ?>" href="<?= e(base_url('mobile-ai?tab=assistant')) ?>">Assistant</a>
            <a class="tab <?= $tab === 'notifications' ? 'active' : '' ?>" href="<?= e(base_url('mobile-ai?tab=notifications')) ?>">Notifications</a>
        </nav>

        <?php if ($tab === 'assistant'): ?>
            <section class="section">
                <div class="section-head">
                    <h2>Assistant</h2>
                    <p class="section-copy">This shell is intentionally safe in Phase 1. Client-facing communication still requires draft review and approval before sending.</p>
                </div>
                <div class="section-body">
                    <div class="quick-grid">
                        <div class="quick">Morning Sweep</div>
                        <div class="quick">New Leads</div>
                        <div class="quick">Replies</div>
                        <div class="quick">Follow-ups</div>
                        <div class="quick">No Answer Review</div>
                        <div class="quick">Booked Consults</div>
                    </div>

                    <div class="settings">
                        <div class="setting">
                            <div>
                                <strong>AI actions</strong>
                                <p class="meta-copy">Command plumbing is ready. CRM-safe execution comes in the next phase.</p>
                            </div>
                            <span class="badge normal">Coming Soon</span>
                        </div>
                    </div>

                    <div class="composer" aria-label="Assistant command shell">
                        <input type="text" placeholder="Ask Elite AI..." disabled>
                        <button class="btn disabled" type="button" disabled>Mic Soon</button>
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
                                        · <?= e((string) $item['lead_name']) ?>
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
            <form method="POST" action="<?= e(base_url('mobile-ai')) ?>">
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
    </script>
</body>
</html>
