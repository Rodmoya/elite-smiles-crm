<?php
declare(strict_types=1);

/**
 * Elite Smiles Marketing CRM
 * File: accept-invite.php
 *
 * Invite acceptance / first-time password setup page.
 */

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/core/db.php';
require_once __DIR__ . '/app/core/auth.php';

if (auth_check()) {
    redirect(base_url('dashboard.php'));
}

$logoUrl = base_url('assets/img/ES-Logo-Stack-500-x-150-px.png');
$errorMessage = flash_get('error') ?? '';
$successMessage = flash_get('success') ?? '';

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$inviteUser = null;

function accept_invite_find_user(string $token): ?array
{
    if ($token === '') {
        return null;
    }

    $sql = "
        SELECT
            id,
            first_name,
            last_name,
            email,
            role,
            is_active,
            invite_token,
            invite_expires_at,
            must_change_password
        FROM users
        WHERE invite_token = :invite_token
        LIMIT 1
    ";

    $user = db_one($sql, ['invite_token' => $token]);

    return $user ?: null;
}

function accept_invite_is_valid(array $user): bool
{
    $inviteToken = trim((string) ($user['invite_token'] ?? ''));
    $expiresAt = trim((string) ($user['invite_expires_at'] ?? ''));

    if ($inviteToken === '' || $expiresAt === '') {
        return false;
    }

    return strtotime($expiresAt) !== false && strtotime($expiresAt) >= time();
}

if ($token !== '') {
    $inviteUser = accept_invite_find_user($token);

    if (!$inviteUser) {
        $errorMessage = 'This invitation link is invalid.';
    } elseif (!accept_invite_is_valid($inviteUser)) {
        $errorMessage = 'This invitation link has expired. Please request a new invitation.';
    }
}

if (is_post()) {
    require_csrf();

    $password = (string) post('password', '');
    $passwordConfirm = (string) post('password_confirm', '');

    if ($token === '') {
        $errorMessage = 'Missing invitation token.';
    } else {
        $inviteUser = accept_invite_find_user($token);

        if (!$inviteUser) {
            $errorMessage = 'This invitation link is invalid.';
        } elseif (!accept_invite_is_valid($inviteUser)) {
            $errorMessage = 'This invitation link has expired. Please request a new invitation.';
        } elseif ($password === '' || $passwordConfirm === '') {
            $errorMessage = 'Please complete both password fields.';
        } elseif (strlen($password) < 8) {
            $errorMessage = 'Password must be at least 8 characters.';
        } elseif ($password !== $passwordConfirm) {
            $errorMessage = 'Passwords do not match.';
        } else {
            $newHash = auth_create_password_hash($password);

            db_execute("
                UPDATE users
                SET
                    password_hash = :password_hash,
                    invite_token = NULL,
                    invite_expires_at = NULL,
                    password_reset_token = NULL,
                    password_reset_expires_at = NULL,
                    must_change_password = 0,
                    is_active = 1,
                    updated_at = NOW()
                WHERE id = :id
                LIMIT 1
            ", [
                'password_hash' => $newHash,
                'id' => (int) ($inviteUser['id'] ?? 0),
            ]);

            esm_log('auth', 'Invite accepted and password set', [
                'user_id' => (int) ($inviteUser['id'] ?? 0),
                'email' => (string) ($inviteUser['email'] ?? ''),
                'ip' => client_ip(),
            ]);

            flash_set('success', 'Your password has been set. You can now sign in.');
            redirect(base_url('login.php'));
        }
    }
}

$inviteName = '';
$inviteEmail = '';
$inviteRole = '';

if ($inviteUser) {
    $inviteName = trim(((string) ($inviteUser['first_name'] ?? '')) . ' ' . ((string) ($inviteUser['last_name'] ?? '')));
    $inviteEmail = (string) ($inviteUser['email'] ?? '');
    $inviteRole = (string) ($inviteUser['role'] ?? 'viewer');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> | Accept Invite</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="robots" content="noindex,nofollow">
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <div class="flex min-h-screen items-center justify-center px-6 py-10">
        <div class="w-full max-w-md">
            <div class="mb-6 text-center">
                <div class="mx-auto flex justify-center">
                    <img
                        src="<?= e($logoUrl) ?>"
                        alt="Elite Smiles"
                        class="h-auto w-full max-w-[260px]"
                    >
                </div>

                <h1 class="mt-4 text-2xl font-semibold tracking-tight text-slate-900">
                    Elite Smiles CRM
                </h1>

                <p class="mt-2 text-sm text-slate-500">
                    Complete your account setup
                </p>
            </div>

            <div class="rounded-[2rem] bg-white p-8 shadow-xl ring-1 ring-slate-200">
                <div class="mb-8 text-center">
                    <div class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-[11px] font-medium uppercase tracking-[0.22em] text-slate-600">
                        Invitation
                    </div>

                    <h2 class="mt-4 text-2xl font-semibold tracking-tight text-slate-900">
                        Set your password
                    </h2>

                    <p class="mt-2 text-sm leading-6 text-slate-500">
                        Use this secure page to activate your access.
                    </p>
                </div>

                <?php if ($errorMessage !== ''): ?>
                    <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        <?= e($errorMessage) ?>
                    </div>
                <?php endif; ?>

                <?php if ($successMessage !== ''): ?>
                    <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                        <?= e($successMessage) ?>
                    </div>
                <?php endif; ?>

                <?php if ($inviteUser && $errorMessage === ''): ?>
                    <div class="mb-6 rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Invited User</p>
                        <p class="mt-2 text-lg font-semibold text-slate-900"><?= e($inviteName !== '' ? $inviteName : 'Team Member') ?></p>
                        <p class="mt-1 text-sm text-slate-600"><?= e($inviteEmail) ?></p>
                        <p class="mt-2 text-xs uppercase tracking-[0.18em] text-slate-500">Role: <?= e($inviteRole) ?></p>
                    </div>

                    <form method="POST" action="<?= e(base_url('accept-invite.php?token=' . urlencode($token))) ?>" class="space-y-5">
                        <?= csrf_input() ?>
                        <input type="hidden" name="token" value="<?= e($token) ?>">

                        <div>
                            <label for="password" class="mb-2 block text-sm font-medium text-slate-700">
                                New Password
                            </label>

                            <div class="relative">
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    autocomplete="new-password"
                                    required
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 pr-14 text-slate-900 placeholder-slate-400 outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                                    placeholder="Create a password"
                                >

                                <button
                                    type="button"
                                    id="toggle-password"
                                    class="absolute inset-y-0 right-2 inline-flex items-center justify-center rounded-xl px-3 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                                    aria-label="Show password"
                                    aria-pressed="false"
                                >
                                    <svg id="eye-open-icon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.269 2.943 9.542 7-1.273 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>

                                    <svg id="eye-closed-icon" xmlns="http://www.w3.org/2000/svg" class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M3 3l18 18"></path>
                                        <path d="M10.584 10.587A2 2 0 0 0 12 14a2 2 0 0 0 1.414-.586"></path>
                                        <path d="M9.363 5.365A9.466 9.466 0 0 1 12 5c4.478 0 8.269 2.943 9.542 7a9.97 9.97 0 0 1-4.132 5.14"></path>
                                        <path d="M6.228 6.228A9.965 9.965 0 0 0 2.458 12c1.274 4.057 5.065 7 9.542 7a9.45 9.45 0 0 0 5.168-1.528"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label for="password_confirm" class="mb-2 block text-sm font-medium text-slate-700">
                                Confirm Password
                            </label>

                            <div class="relative">
                                <input
                                    type="password"
                                    id="password_confirm"
                                    name="password_confirm"
                                    autocomplete="new-password"
                                    required
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 pr-14 text-slate-900 placeholder-slate-400 outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                                    placeholder="Confirm your password"
                                >

                                <button
                                    type="button"
                                    id="toggle-password-confirm"
                                    class="absolute inset-y-0 right-2 inline-flex items-center justify-center rounded-xl px-3 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                                    aria-label="Show password confirmation"
                                    aria-pressed="false"
                                >
                                    <svg id="eye-open-icon-confirm" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.269 2.943 9.542 7-1.273 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>

                                    <svg id="eye-closed-icon-confirm" xmlns="http://www.w3.org/2000/svg" class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M3 3l18 18"></path>
                                        <path d="M10.584 10.587A2 2 0 0 0 12 14a2 2 0 0 0 1.414-.586"></path>
                                        <path d="M9.363 5.365A9.466 9.466 0 0 1 12 5c4.478 0 8.269 2.943 9.542 7a9.97 9.97 0 0 1-4.132 5.14"></path>
                                        <path d="M6.228 6.228A9.965 9.965 0 0 0 2.458 12c1.274 4.057 5.065 7 9.542 7a9.45 9.45 0 0 0 5.168-1.528"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <button
                            type="submit"
                            class="inline-flex w-full items-center justify-center rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-300"
                        >
                            Set Password & Activate Account
                        </button>
                    </form>
                <?php else: ?>
                    <div class="text-center">
                        <a
                            href="<?= e(base_url('login.php')) ?>"
                            class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-5 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
                        >
                            Back to Login
                        </a>
                    </div>
                <?php endif; ?>

                <div class="mt-6 border-t border-slate-200 pt-5 text-center">
                    <p class="text-xs leading-6 text-slate-500">
                        Secure onboarding link for Elite Smiles CRM
                    </p>
                </div>
            </div>

            <p class="mt-6 text-center text-xs text-slate-400">
                <?= e(APP_NAME) ?> — Draper, Utah
            </p>
        </div>
    </div>

    <script>
    (function () {
        function wireToggle(buttonId, inputId, openIconId, closedIconId, showLabel, hideLabel) {
            const button = document.getElementById(buttonId);
            const input = document.getElementById(inputId);
            const openIcon = document.getElementById(openIconId);
            const closedIcon = document.getElementById(closedIconId);

            if (!button || !input || !openIcon || !closedIcon) return;

            button.addEventListener('click', function () {
                const isPassword = input.getAttribute('type') === 'password';

                input.setAttribute('type', isPassword ? 'text' : 'password');
                button.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
                button.setAttribute('aria-label', isPassword ? hideLabel : showLabel);

                openIcon.classList.toggle('hidden', isPassword);
                closedIcon.classList.toggle('hidden', !isPassword);
            });
        }

        wireToggle(
            'toggle-password',
            'password',
            'eye-open-icon',
            'eye-closed-icon',
            'Show password',
            'Hide password'
        );

        wireToggle(
            'toggle-password-confirm',
            'password_confirm',
            'eye-open-icon-confirm',
            'eye-closed-icon-confirm',
            'Show password confirmation',
            'Hide password confirmation'
        );
    })();
    </script>
</body>
</html>