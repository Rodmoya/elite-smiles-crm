<?php
declare(strict_types=1);

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/core/db.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/core/mobile_ai_auth.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('Referrer-Policy: strict-origin-when-cross-origin');

$token = trim((string) ($_GET['token'] ?? ''));
$setup = $token !== '' ? mobile_ai_find_valid_setup_token($token) : null;
$user = $setup['user'] ?? null;
$errorMessage = '';

if ($token === '') {
    $errorMessage = 'Missing mobile setup token.';
} elseif (!$setup || !$user) {
    $errorMessage = 'This mobile setup link is invalid, expired, already used, or revoked.';
} else {
    mobile_ai_mark_setup_token_used((int) ($setup['id'] ?? 0));
    mobile_ai_create_session((int) ($user['id'] ?? 0), 'QR Mobile Session');
    esm_log('mobile_ai', 'Mobile AI session created from QR setup', [
        'user_id' => (int) ($user['id'] ?? 0),
        'email' => (string) ($user['email'] ?? ''),
    ]);
    redirect(base_url('mobile-ai/?welcome=1'));
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(APP_NAME) ?> | Mobile Setup</title>
    <meta name="robots" content="noindex,nofollow">
    <style>
        :root {
            --bg: #f4efe7;
            --panel: rgba(255,255,255,0.92);
            --ink: #1e1a17;
            --muted: #6f655b;
            --line: #e6ddd0;
            --accent: #b88b54;
            --shadow: 0 24px 70px rgba(35, 28, 22, 0.12);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background:
                radial-gradient(circle at top left, rgba(184,139,84,0.18), transparent 28%),
                linear-gradient(180deg, #fbf8f3 0%, var(--bg) 100%);
            color: var(--ink);
            font-family: Georgia, "Times New Roman", serif;
        }
        .card {
            width: min(100%, 560px);
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 28px;
            box-shadow: var(--shadow);
            padding: 28px;
            backdrop-filter: blur(12px);
        }
        .eyebrow {
            display: inline-flex;
            padding: 8px 12px;
            border-radius: 999px;
            background: #f7f0e6;
            color: #8d6b40;
            font: 700 11px/1 Arial, sans-serif;
            text-transform: uppercase;
            letter-spacing: .18em;
        }
        h1 {
            margin: 18px 0 10px;
            font-size: clamp(30px, 8vw, 42px);
            line-height: 1;
        }
        p {
            margin: 0 0 14px;
            color: var(--muted);
            font: 400 16px/1.7 Arial, sans-serif;
        }
        .error {
            margin-top: 18px;
            border: 1px solid #f0c7c2;
            background: #fff3f1;
            color: #8f3024;
            border-radius: 18px;
            padding: 14px 16px;
            font: 600 14px/1.5 Arial, sans-serif;
        }
        a {
            display: inline-flex;
            margin-top: 10px;
            color: var(--ink);
            text-decoration: none;
            border-bottom: 1px solid rgba(30,26,23,0.25);
            font: 600 14px/1.4 Arial, sans-serif;
        }
    </style>
</head>
<body>
    <main class="card">
        <div class="eyebrow">Elite AI</div>
        <h1>Mobile access unavailable</h1>
        <p>This secure QR setup link could not be used.</p>
        <div class="error"><?= e($errorMessage) ?></div>
        <a href="<?= e(base_url('login.php')) ?>">Back to CRM login</a>
    </main>
</body>
</html>
