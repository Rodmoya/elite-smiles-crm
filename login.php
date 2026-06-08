<?php
declare(strict_types=1);

/**
 * Elite Smiles Marketing CRM
 * File: login.php
 *
 * Centered light-theme login page with Elite Smiles logo.
 * Includes password visibility toggle.
 */

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/core/db.php';
require_once __DIR__ . '/app/core/auth.php';

if (auth_check()) {
    redirect(base_url('dashboard.php'));
}

$errorMessage = flash_get('error') ?? '';
$successMessage = flash_get('success') ?? '';

$logoUrl = base_url('assets/img/ES-Logo-Stack-500-x-150-px.png');

if (is_post()) {
    require_csrf();

    $email = strtolower(trim((string) post('email', '')));
    $password = (string) post('password', '');

    if ($email === '' || $password === '') {
        $errorMessage = 'Please enter your email and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Please enter a valid email address.';
    } else {
        if (auth_attempt($email, $password)) {
            flash_set('success', 'Welcome back.');
            redirect(base_url('dashboard.php'));
        }

        $errorMessage = 'Invalid credentials or inactive account.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> | Login</title>
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
                    Secure access to your dashboard
                </p>
            </div>

            <div class="rounded-[2rem] bg-white p-8 shadow-xl ring-1 ring-slate-200">
                <div class="mb-8 text-center">
                    <div class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-[11px] font-medium uppercase tracking-[0.22em] text-slate-600">
                        Sign In
                    </div>

                    <h2 class="mt-4 text-2xl font-semibold tracking-tight text-slate-900">
                        Welcome back
                    </h2>

                    <p class="mt-2 text-sm leading-6 text-slate-500">
                        Log in to continue to the Elite Smiles platform.
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

                <form method="POST" action="<?= e(base_url('login.php')) ?>" class="space-y-5">
                    <?= csrf_input() ?>

                    <div>
                        <label for="email" class="mb-2 block text-sm font-medium text-slate-700">
                            Email address
                        </label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?= e(old_value('email')) ?>"
                            autocomplete="email"
                            required
                            class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 placeholder-slate-400 outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            placeholder="you@example.com"
                        >
                    </div>

                    <div>
                        <label for="password" class="mb-2 block text-sm font-medium text-slate-700">
                            Password
                        </label>

                        <div class="relative">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                autocomplete="current-password"
                                required
                                class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 pr-14 text-slate-900 placeholder-slate-400 outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                                placeholder="Enter your password"
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

                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-300"
                    >
                        Sign in
                    </button>
                </form>

                <div class="mt-6 border-t border-slate-200 pt-5 text-center">
                    <p class="text-xs leading-6 text-slate-500">
                        Temporary environment:
                        <span class="font-medium text-slate-700"><?= e(APP_URL) ?></span>
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
        const passwordInput = document.getElementById('password');
        const toggleButton = document.getElementById('toggle-password');
        const eyeOpenIcon = document.getElementById('eye-open-icon');
        const eyeClosedIcon = document.getElementById('eye-closed-icon');

        if (!passwordInput || !toggleButton || !eyeOpenIcon || !eyeClosedIcon) return;

        toggleButton.addEventListener('click', function () {
            const isPassword = passwordInput.getAttribute('type') === 'password';

            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            toggleButton.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
            toggleButton.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');

            eyeOpenIcon.classList.toggle('hidden', isPassword);
            eyeClosedIcon.classList.toggle('hidden', !isPassword);
        });
    })();
    </script>
</body>
</html>