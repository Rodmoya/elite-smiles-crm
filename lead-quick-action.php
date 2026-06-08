<?php
declare(strict_types=1);

/**
 * Elite Smiles Marketing CRM
 * File: /crm/lead-quick-action.php
 *
 * Lightweight mobile-first lead action page.
 * Opened from secure signed links in email / Pushover.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/db.php';
require_once __DIR__ . '/app/core/mailer.php';

header('Content-Type: text/html; charset=UTF-8');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

if (!function_exists('elite_qa_render_page')) {
    function elite_qa_render_page(string $title, string $bodyHtml, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        :root {
            --card: rgba(255,255,255,.94);
            --card-strong: rgba(255,255,255,.985);
            --line: #e8e0d4;
            --text: #2c241d;
            --muted: #7a6f63;
            --black: #141414;
            --gold: #b68c54;
            --cream: #fbf8f3;
            --ivory: #f4eee5;
            --shadow: 0 22px 70px rgba(44, 36, 29, .10);
            --radius: 26px;
            --disabled: #d7d1c8;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            background:
                radial-gradient(circle at top left, rgba(182,140,84,.11), transparent 28%),
                radial-gradient(circle at bottom right, rgba(182,140,84,.07), transparent 32%),
                linear-gradient(180deg, #fbf8f3 0%, #f3eee6 100%);
            color: var(--text);
            font-family: Inter, Arial, Helvetica, sans-serif;
        }

        body {
            min-height: 100vh;
            padding: 20px;
        }

        .shell {
            width: 100%;
            max-width: 760px;
            margin: 0 auto;
        }

        .hero {
            background: linear-gradient(180deg, rgba(20,20,20,.96) 0%, rgba(35,30,26,.96) 100%);
            color: #ffffff;
            border-radius: var(--radius);
            padding: 26px 24px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255,255,255,.05);
            overflow: hidden;
            position: relative;
        }

        .hero:before {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top right, rgba(182,140,84,.26), transparent 30%);
            pointer-events: none;
        }

        .eyebrow {
            position: relative;
            z-index: 1;
            font-size: 12px;
            letter-spacing: .18em;
            text-transform: uppercase;
            color: rgba(255,255,255,.74);
            font-weight: 700;
        }

        .hero h1 {
            position: relative;
            z-index: 1;
            margin: 10px 0 8px;
            font-size: 34px;
            line-height: 1.06;
            letter-spacing: -.03em;
        }

        .hero p {
            position: relative;
            z-index: 1;
            margin: 0;
            color: rgba(255,255,255,.82);
            font-size: 15px;
            line-height: 1.65;
            max-width: 560px;
        }

        .grid {
            display: grid;
            gap: 18px;
            margin-top: 18px;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .card-head {
            padding: 18px 20px 0;
        }

        .card-title {
            margin: 0;
            font-size: 19px;
            line-height: 1.2;
            letter-spacing: -.02em;
        }

        .card-subtitle {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.55;
        }

        .card-body {
            padding: 18px 20px 20px;
        }

        .actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 58px;
            width: 100%;
            padding: 14px 16px;
            border-radius: 16px;
            text-decoration: none;
            font-weight: 800;
            font-size: 15px;
            letter-spacing: -.01em;
            transition: transform .12s ease, opacity .12s ease;
            border: 1px solid transparent;
            text-align: center;
            cursor: pointer;
            font-family: inherit;
        }

        .action:active {
            transform: scale(.985);
        }

        .action-dark {
            background: var(--black);
            color: #ffffff;
        }

        .action-gold {
            background: var(--gold);
            color: #1f1812;
        }

        .action-disabled {
            background: var(--disabled);
            color: #7d756d;
            cursor: not-allowed;
            pointer-events: none;
        }

        .helper {
            margin-top: 12px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.6;
        }

        .pill-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 14px;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            padding: 10px 12px;
            border-radius: 999px;
            background: var(--ivory);
            border: 1px solid var(--line);
            color: var(--text);
            font-size: 13px;
            font-weight: 700;
            line-height: 1;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .meta-row {
            display: grid;
            gap: 4px;
            padding: 14px 16px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: var(--card-strong);
        }

        .meta-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .14em;
            font-weight: 700;
            color: var(--muted);
        }

        .meta-value {
            font-size: 16px;
            line-height: 1.45;
            color: var(--text);
            word-break: break-word;
        }

        .footer-note {
            margin-top: 14px;
            text-align: center;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.55;
        }

        @media (max-width: 640px) {
            body { padding: 14px; }
            .hero { padding: 22px 18px; border-radius: 22px; }
            .hero h1 { font-size: 29px; }
            .card { border-radius: 22px; }
            .card-head { padding: 16px 16px 0; }
            .card-body { padding: 16px; }
            .actions { grid-template-columns: 1fr; }
            .action { min-height: 54px; font-size: 15px; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <?php echo $bodyHtml; ?>
    </div>
</body>
</html>
        <?php
        exit;
    }
}

if (!function_exists('elite_qa_string')) {
    function elite_qa_string($value): string
    {
        return trim((string) ($value ?? ''));
    }
}

if (!function_exists('elite_qa_escape')) {
    function elite_qa_escape($value): string
    {
        return htmlspecialchars(trim((string) ($value ?? '')), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('elite_qa_app_url')) {
    function elite_qa_app_url(): string
    {
        return defined('APP_URL') ? rtrim((string) APP_URL, '/') : '';
    }
}

if (!function_exists('elite_qa_quick_action_secret')) {
    function elite_qa_quick_action_secret(): string
    {
        return defined('ELITE_QUICK_ACTION_SECRET') ? elite_qa_string((string) ELITE_QUICK_ACTION_SECRET) : '';
    }
}

if (!function_exists('elite_qa_build_signature')) {
    function elite_qa_build_signature(int $leadId, int $expiresAt): string
    {
        $secret = elite_qa_quick_action_secret();

        if ($leadId <= 0 || $expiresAt <= 0 || $secret === '') {
            return '';
        }

        return hash_hmac('sha256', $leadId . '|' . $expiresAt, $secret);
    }
}

if (!function_exists('elite_qa_is_secret_configured')) {
    function elite_qa_is_secret_configured(): bool
    {
        $secret = elite_qa_quick_action_secret();

        return $secret !== ''
            && $secret !== 'REPLACE_WITH_A_LONG_RANDOM_PRIVATE_STRING';
    }
}

if (!function_exists('elite_qa_verify_request')) {
    function elite_qa_verify_request(int $leadId, int $expiresAt, string $sig): bool
    {
        if ($leadId <= 0 || $expiresAt <= 0 || $sig === '') {
            return false;
        }

        if (!elite_qa_is_secret_configured()) {
            return false;
        }

        if (time() > $expiresAt) {
            return false;
        }

        $expected = elite_qa_build_signature($leadId, $expiresAt);
        if ($expected === '') {
            return false;
        }

        return hash_equals($expected, $sig);
    }
}

if (!function_exists('elite_qa_label_preferred_contact')) {
    function elite_qa_label_preferred_contact(string $value): string
    {
        $value = strtolower(trim($value));

        return match ($value) {
            'sms', 'text' => 'Text',
            'phone', 'call' => 'Call',
            'email' => 'Email',
            '' => 'Not specified',
            default => ucwords(str_replace(['_', '-'], ' ', $value)),
        };
    }
}

if (!function_exists('elite_qa_first_name_from_full_name')) {
    function elite_qa_first_name_from_full_name(string $fullName): string
    {
        $fullName = trim($fullName);

        if ($fullName === '') {
            return '';
        }

        $parts = preg_split('/\s+/', $fullName);
        if (!is_array($parts) || empty($parts[0])) {
            return '';
        }

        return trim((string) $parts[0]);
    }
}

if (!function_exists('elite_qa_fetch_lead')) {
    function elite_qa_fetch_lead(int $leadId): ?array
    {
        if ($leadId <= 0) {
            return null;
        }

        $sql = "
            SELECT
                id,
                full_name,
                phone,
                email,
                preferred_contact,
                procedure_interest,
                source,
                source_medium,
                source_type,
                landing_page,
                campaign,
                source_campaign,
                trigger_keyword,
                instagram_username,
                assigned_to,
                status,
                created_at
            FROM leads
            WHERE id = ?
            LIMIT 1
        ";

        if (function_exists('db_one')) {
            $row = db_one($sql, [$leadId]);
            return is_array($row) ? $row : null;
        }

        if (function_exists('db')) {
            $pdo = db();

            if ($pdo instanceof PDO) {
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$leadId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                return is_array($row) ? $row : null;
            }
        }

        throw new RuntimeException('No supported database helper was found.');
    }
}

if (!function_exists('elite_qa_sms_link')) {
    function elite_qa_sms_link(string $phone, string $message): string
    {
        $phone = trim($phone);
        $message = trim($message);

        if ($phone === '') {
            return '';
        }

        if (function_exists('elite_sms_link_for_phone')) {
            $link = (string) elite_sms_link_for_phone($phone, $message);
            if ($link !== '') {
                return $link;
            }
        }

        $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
        return 'sms:' . rawurlencode((string) $cleanPhone) . '&body=' . rawurlencode($message);
    }
}

register_shutdown_function(function (): void {
    $error = error_get_last();

    if (!$error) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    error_log('Elite quick action fatal error: ' . print_r($error, true));

    $message = '<section class="hero">'
        . '<div class="eyebrow">Elite Smiles</div>'
        . '<h1>Quick Actions Error</h1>'
        . '<p>The page hit a server-side PHP error before rendering. The error was logged so we can fix it.</p>'
        . '</section>'
        . '<div class="grid">'
        . '<section class="card"><div class="card-head"><h2 class="card-title">What happened</h2></div><div class="card-body">'
        . '<div class="meta-row"><div class="meta-label">PHP Error</div><div class="meta-value">'
        . htmlspecialchars(($error['message'] ?? 'Unknown error') . ' in ' . ($error['file'] ?? 'unknown file') . ' on line ' . ($error['line'] ?? 0), ENT_QUOTES, 'UTF-8')
        . '</div></div>'
        . '</div></section></div>';

    elite_qa_render_page('Quick Actions Error', $message, 500);
});

try {
    $leadId = isset($_GET['lead']) ? (int) $_GET['lead'] : 0;
    $expiresAt = isset($_GET['exp']) ? (int) $_GET['exp'] : 0;
    $sig = elite_qa_string($_GET['sig'] ?? '');

    $isValid = elite_qa_verify_request($leadId, $expiresAt, $sig);

    if (!$isValid) {
        $body = '<section class="hero">'
            . '<div class="eyebrow">Elite Smiles</div>'
            . '<h1>Lead link unavailable</h1>'
            . '<p>This quick action link is invalid or expired. Open the newest notification and try again.</p>'
            . '</section>';

        elite_qa_render_page('Lead Link Invalid', $body, 403);
    }

    $lead = elite_qa_fetch_lead($leadId);

    if (!$lead) {
        $body = '<section class="hero">'
            . '<div class="eyebrow">Elite Smiles</div>'
            . '<h1>Lead not found</h1>'
            . '<p>This lead could not be loaded. It may have been removed or the record no longer exists.</p>'
            . '</section>';

        elite_qa_render_page('Lead Not Found', $body, 404);
    }

    $fullName = elite_qa_string($lead['full_name'] ?? '');
    $phone = elite_qa_string($lead['phone'] ?? '');
    $email = elite_qa_string($lead['email'] ?? '');
    $preferredContact = elite_qa_string($lead['preferred_contact'] ?? '');
    $procedureInterest = elite_qa_string($lead['procedure_interest'] ?? '');
    $source = elite_qa_string($lead['source'] ?? '');
    $sourceMedium = elite_qa_string($lead['source_medium'] ?? '');
    $sourceType = elite_qa_string($lead['source_type'] ?? '');
    $landingPage = elite_qa_string($lead['landing_page'] ?? '');
    $campaign = elite_qa_string($lead['campaign'] ?? ($lead['source_campaign'] ?? ''));
    $triggerKeyword = elite_qa_string($lead['trigger_keyword'] ?? '');
    $instagramUsername = elite_qa_string($lead['instagram_username'] ?? '');
    $assignedTo = elite_qa_string($lead['assigned_to'] ?? '');
    $status = elite_qa_string($lead['status'] ?? '');
    $createdAt = elite_qa_string($lead['created_at'] ?? '');

    if ($fullName === '') {
        $fullName = 'Unknown Lead';
    }

    $firstName = elite_qa_first_name_from_full_name($fullName);
    $nameForMessage = $firstName !== '' ? $firstName : $fullName;

    $textForText = 'Hi ' . $nameForMessage . ', this is Elite Smiles. Thank you for reaching out. We would love to help you achieve the smile you deserve and get your consultation scheduled. What day or time works best for you?';
    $textForCall = 'Hi ' . $nameForMessage . ', this is Elite Smiles. Thank you for reaching out. We would love to help you achieve the smile you deserve and get your consultation scheduled. Is this a good moment for a quick call, or what time works best for you?';

    $smsTextLink = elite_qa_sms_link($phone, $textForText);
    $smsCallLink = elite_qa_sms_link($phone, $textForCall);

    $displayPhone = 'Not provided';
    if ($phone !== '') {
        $displayPhone = function_exists('elite_format_phone_for_reading')
            ? elite_format_phone_for_reading($phone)
            : $phone;
    }

    $displayPreferred = elite_qa_label_preferred_contact($preferredContact);
    $displayAssigned = $assignedTo !== '' ? $assignedTo : 'Unassigned';
    $displayStatus = $status !== '' ? ucwords(str_replace(['_', '-'], ' ', $status)) : 'New';
    $displayProcedure = $procedureInterest !== '' ? $procedureInterest : 'Not specified';

    ob_start();
    ?>
    <section class="hero">
        <div class="eyebrow">Elite Smiles</div>
        <h1><?php echo elite_qa_escape($fullName); ?></h1>
        <p>Choose the kind of message you want to send and your phone will open the text ready to go.</p>
    </section>

    <div class="grid">
        <section class="card">
            <div class="card-head">
                <h2 class="card-title">Quick Actions</h2>
                <p class="card-subtitle">Two ready-to-send message options for faster follow-up.</p>
            </div>
            <div class="card-body">
                <div class="actions">
                    <?php if ($smsTextLink !== ''): ?>
                        <a class="action action-dark" href="<?php echo elite_qa_escape($smsTextLink); ?>">Text for Text</a>
                    <?php else: ?>
                        <span class="action action-disabled">Text for Text</span>
                    <?php endif; ?>

                    <?php if ($smsCallLink !== ''): ?>
                        <a class="action action-gold" href="<?php echo elite_qa_escape($smsCallLink); ?>">Text for Call</a>
                    <?php else: ?>
                        <span class="action action-disabled">Text for Call</span>
                    <?php endif; ?>
                </div>

                <?php if ($phone === ''): ?>
                    <div class="helper">This lead does not have a phone number, so text actions are unavailable.</div>
                <?php else: ?>
                    <div class="helper">Both buttons open your phone's text message app with the message already filled in.</div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card-head">
                <h2 class="card-title">Lead Info</h2>
                <p class="card-subtitle">Just the essentials so you know who you are talking to.</p>
            </div>
            <div class="card-body">
                <div class="pill-row">
                    <span class="pill">Lead ID: <?php echo elite_qa_escape((string) ($lead['id'] ?? '')); ?></span>
                    <span class="pill">Status: <?php echo elite_qa_escape($displayStatus); ?></span>
                    <span class="pill">Assigned: <?php echo elite_qa_escape($displayAssigned); ?></span>
                </div>

                <div class="meta-grid">
                    <div class="meta-row">
                        <div class="meta-label">Full Name</div>
                        <div class="meta-value"><?php echo elite_qa_escape($fullName); ?></div>
                    </div>
                    <div class="meta-row">
                        <div class="meta-label">Phone</div>
                        <div class="meta-value"><?php echo elite_qa_escape($displayPhone); ?></div>
                    </div>
                    <div class="meta-row">
                        <div class="meta-label">Email</div>
                        <div class="meta-value"><?php echo elite_qa_escape($email !== '' ? $email : 'Not provided'); ?></div>
                    </div>
                    <div class="meta-row">
                        <div class="meta-label">Preferred Contact</div>
                        <div class="meta-value"><?php echo elite_qa_escape($displayPreferred); ?></div>
                    </div>
                    <div class="meta-row">
                        <div class="meta-label">Procedure Interest</div>
                        <div class="meta-value"><?php echo elite_qa_escape($displayProcedure); ?></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-head">
                <h2 class="card-title">Source</h2>
                <p class="card-subtitle">Quick attribution so you know where the lead came from.</p>
            </div>
            <div class="card-body">
                <div class="meta-grid">
                    <div class="meta-row">
                        <div class="meta-label">Source</div>
                        <div class="meta-value"><?php echo elite_qa_escape($source !== '' ? $source : 'Not specified'); ?></div>
                    </div>
                    <div class="meta-row">
                        <div class="meta-label">Source Medium</div>
                        <div class="meta-value"><?php echo elite_qa_escape($sourceMedium !== '' ? $sourceMedium : 'Not specified'); ?></div>
                    </div>
                    <div class="meta-row">
                        <div class="meta-label">Source Type</div>
                        <div class="meta-value"><?php echo elite_qa_escape($sourceType !== '' ? $sourceType : 'Not specified'); ?></div>
                    </div>
                    <div class="meta-row">
                        <div class="meta-label">Landing Page</div>
                        <div class="meta-value"><?php echo elite_qa_escape($landingPage !== '' ? $landingPage : 'Not specified'); ?></div>
                    </div>
                    <div class="meta-row">
                        <div class="meta-label">Campaign</div>
                        <div class="meta-value"><?php echo elite_qa_escape($campaign !== '' ? $campaign : 'Not specified'); ?></div>
                    </div>
                    <div class="meta-row">
                        <div class="meta-label">Trigger Keyword</div>
                        <div class="meta-value"><?php echo elite_qa_escape($triggerKeyword !== '' ? $triggerKeyword : 'Not specified'); ?></div>
                    </div>
                    <div class="meta-row">
                        <div class="meta-label">Instagram Username</div>
                        <div class="meta-value"><?php echo elite_qa_escape($instagramUsername !== '' ? $instagramUsername : 'Not specified'); ?></div>
                    </div>
                    <div class="meta-row">
                        <div class="meta-label">Received At</div>
                        <div class="meta-value"><?php echo elite_qa_escape($createdAt !== '' ? $createdAt : 'Not available'); ?></div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="footer-note">Elite Smiles Quick Actions</div>
    <?php

    $body = (string) ob_get_clean();
    elite_qa_render_page('Elite Smiles Lead Actions', $body, 200);
} catch (Throwable $e) {
    error_log('Elite quick action exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    $body = '<section class="hero">'
        . '<div class="eyebrow">Elite Smiles</div>'
        . '<h1>Quick Actions Error</h1>'
        . '<p>The page could not load because of a server-side exception. Details are shown below so we can fix it fast.</p>'
        . '</section>'
        . '<div class="grid">'
        . '<section class="card"><div class="card-head"><h2 class="card-title">Exception Details</h2></div><div class="card-body">'
        . '<div class="meta-row"><div class="meta-label">Message</div><div class="meta-value">' . elite_qa_escape($e->getMessage()) . '</div></div>'
        . '<div class="meta-row"><div class="meta-label">File</div><div class="meta-value">' . elite_qa_escape($e->getFile()) . '</div></div>'
        . '<div class="meta-row"><div class="meta-label">Line</div><div class="meta-value">' . elite_qa_escape((string) $e->getLine()) . '</div></div>'
        . '</div></section></div>';

    elite_qa_render_page('Quick Actions Error', $body, 500);
}