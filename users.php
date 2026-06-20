<?php
declare(strict_types=1);

/**
 * Elite Smiles Marketing CRM
 * File: /users.php
 *
 * Admin user management page:
 * - list users
 * - create user
 * - generate invite token
 * - send invite email
 * - show invite link
 *
 * Updated:
 * - shared top project navigation
 * - active nav state for current page
 * - mobile-safe nav layout
 */

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/core/db.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/core/mailer.php';
require_once __DIR__ . '/app/core/mobile_ai_auth.php';

require_auth();
require_role('admin');
mobile_ai_ensure_schema();

if (is_post() && post('action') === 'logout') {
    require_csrf();
    auth_logout();
    flash_set('success', 'You have been logged out.');
    redirect(base_url('login.php'));
}

$user = auth_user();
$firstName = $user['first_name'] ?? 'User';
$role = $user['role'] ?? 'admin';
$logoUrl = base_url('assets/img/ES-Logo-Stack-500-x-150-px.png');
$currentPage = 'users';
$pageTitle = 'Users';
$logoutAction = base_url('users.php');

$successMessage = flash_get('success') ?? '';
$errorMessage = flash_get('error') ?? '';
$generatedInviteLink = '';
$generatedInviteEmail = '';
$emailSendStatus = '';
$generatedMobileLink = '';
$generatedMobileQrUrl = '';
$generatedMobileUserLabel = '';

function users_page_roles(): array
{
    return [
        'admin' => 'Admin',
        'marketing_manager' => 'Marketing Manager',
        'staff' => 'Staff',
        'viewer' => 'Viewer',
    ];
}

function users_page_role_descriptions(): array
{
    return [
        'admin' => 'Full system access: users, leads, settings, and integrations.',
        'marketing_manager' => 'Manager of campaigns, communication, and growth workflows.',
        'staff' => 'Handles leads, notes, communication, and follow-up execution.',
        'viewer' => 'Read-only access for monitoring and reporting.',
    ];
}

function users_page_has_column(string $column): bool
{
    static $hasColumn = [];

    $column = trim($column);
    if ($column === '') {
        return false;
    }

    if (array_key_exists($column, $hasColumn)) {
        return $hasColumn[$column];
    }

    try {
        $exists = db_value(
            "SELECT 1
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = :schema
               AND TABLE_NAME = 'users'
               AND COLUMN_NAME = :column
             LIMIT 1",
            [
                'schema' => DB_NAME,
                'column' => $column,
            ]
        );
        $hasColumn[$column] = $exists !== null;
    } catch (Throwable $e) {
        $hasColumn[$column] = false;
    }

    return $hasColumn[$column];
}

function users_page_fetch_all(): array
{
    $selectColumns = [
        'id',
        'first_name',
        'last_name',
        'email',
        'role',
        'is_active',
        'invite_token',
        'invite_expires_at',
        'must_change_password',
        'last_login_at',
        'created_at',
        'updated_at',
    ];

    if (users_page_has_column('phone')) {
        $selectColumns[] = 'phone';
    }

    if (users_page_has_column('role_description')) {
        $selectColumns[] = 'role_description';
    }

    $selectSql = implode(",\n            ", $selectColumns);

    return db_all("
        SELECT
            {$selectSql}
        FROM users
        ORDER BY created_at DESC, id DESC
    ");
}

function users_page_mobile_label(?array $session): string
{
    if (!$session) {
        return 'No device yet';
    }

    if (trim((string) ($session['revoked_at'] ?? '')) !== '') {
        return 'Revoked';
    }

    $expiresAt = trim((string) ($session['expires_at'] ?? ''));
    if ($expiresAt !== '' && strtotime($expiresAt) !== false && strtotime($expiresAt) < time()) {
        return 'Expired';
    }

    return trim((string) ($session['device_label'] ?? 'Trusted mobile')) ?: 'Trusted mobile';
}

function users_top_nav_items(): array
{
    return [
        [
            'key' => 'dashboard',
            'label' => 'Dashboard',
            'href' => base_url('dashboard.php'),
        ],
        [
            'key' => 'landing_pages',
            'label' => 'Landing Pages',
            'href' => base_url('landing_pages.php'),
        ],
        [
            'key' => 'users',
            'label' => 'Users',
            'href' => base_url('users.php'),
        ],
    ];
}

if (is_post() && post('action') === 'create_user') {
    require_csrf();

    $first = trim((string) post('first_name'));
    $last = trim((string) post('last_name'));
    $email = strtolower(trim((string) post('email')));
    $phone = trim((string) post('phone'));
    $newRole = trim((string) post('role', 'viewer'));
    $roleDescription = trim((string) post('role_description'));
    $isActive = (int) (post('is_active', '1') === '1' ? 1 : 0);

    $allowedRoles = array_keys(users_page_roles());

    if ($first === '' || $last === '' || $email === '') {
        $errorMessage = 'Please complete first name, last name, and email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Please enter a valid email address.';
    } elseif (!in_array($newRole, $allowedRoles, true)) {
        $errorMessage = 'Invalid role selected.';
    } else {
        $existing = auth_find_user_by_email($email);

        if ($existing) {
            $errorMessage = 'A user with that email already exists.';
        } else {
            $inviteToken = auth_generate_token(32);
            $inviteExpiresAt = auth_token_expires_at(72);
        $temporaryHash = auth_create_password_hash(bin2hex(random_bytes(16)));
        $insertColumns = [
            'first_name',
            'last_name',
            'email',
            'password_hash',
            'invite_token',
            'invite_expires_at',
            'password_reset_token',
            'password_reset_expires_at',
            'must_change_password',
            'role',
            'is_active',
            'created_at',
            'updated_at',
        ];
        $insertValues = [
            ':first_name',
            ':last_name',
            ':email',
            ':password_hash',
            ':invite_token',
            ':invite_expires_at',
            'NULL',
            'NULL',
            '1',
            ':role',
            ':is_active',
            'NOW()',
            'NOW()',
        ];
        $params = [
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
            'password_hash' => $temporaryHash,
            'invite_token' => $inviteToken,
            'invite_expires_at' => $inviteExpiresAt,
            'role' => $newRole,
            'is_active' => $isActive,
        ];

        if (users_page_has_column('phone')) {
            $insertColumns[] = 'phone';
            $insertValues[] = ':phone';
            $params['phone'] = $phone;
        }

        if (users_page_has_column('role_description')) {
            $insertColumns[] = 'role_description';
            $insertValues[] = ':role_description';
            $params['role_description'] = $roleDescription;
        }

            db_execute("
                INSERT INTO users (
                    " . implode(', ', $insertColumns) . "
                ) VALUES (
                    " . implode(', ', $insertValues) . "
                )
            ", $params);

            $generatedInviteEmail = $email;
            $generatedInviteLink = base_url('accept-invite.php?token=' . urlencode($inviteToken));

            $mailSent = elite_send_invite_email($email, $first, $generatedInviteLink);
            $emailSendStatus = $mailSent
                ? 'Invite email sent successfully.'
                : 'User created, but email sending failed. Use the invite link below manually.';

            $successMessage = 'User created. Invite link generated below.';

            esm_log('auth', 'Admin created user invite', [
                'created_by_user_id' => auth_user_id(),
                'email' => $email,
                'role' => $newRole,
                'ip' => client_ip(),
                'mail_sent' => $mailSent ? 1 : 0,
            ]);
        }
    }
}

if (is_post() && post('action') === 'update_user') {
    require_csrf();

    $targetUserId = (int) post('user_id');
    $first = trim((string) post('first_name'));
    $last = trim((string) post('last_name'));
    $email = strtolower(trim((string) post('email')));
    $phone = trim((string) post('phone'));
    $updatedRole = trim((string) post('role', 'viewer'));
    $roleDescription = trim((string) post('role_description'));
    $isActive = (int) (post('is_active', '1') === '1' ? 1 : 0);

    $allowedRoles = array_keys(users_page_roles());

    if ($targetUserId <= 0) {
        $errorMessage = 'Invalid user.';
    } elseif ($first === '' || $last === '' || $email === '') {
        $errorMessage = 'Please complete first name, last name, and email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Please enter a valid email address.';
    } elseif (!in_array($updatedRole, $allowedRoles, true)) {
        $errorMessage = 'Invalid role selected.';
    } else {
        $conflictUser = db_one("
            SELECT id
            FROM users
            WHERE LOWER(email) = :email
              AND id <> :id
            LIMIT 1
        ", [
            'email' => $email,
            'id' => $targetUserId,
        ]);

        if ($conflictUser) {
            $errorMessage = 'A user with that email already exists.';
        } else {
            $setParts = [
                'first_name = :first_name',
                'last_name = :last_name',
                'email = :email',
                'role = :role',
                'is_active = :is_active',
            ];
            $updateParams = [
                'id' => $targetUserId,
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email,
                'role' => $updatedRole,
                'is_active' => $isActive,
            ];

            if (users_page_has_column('phone')) {
                $setParts[] = 'phone = :phone';
                $updateParams['phone'] = $phone;
            }

            if (users_page_has_column('role_description')) {
                $setParts[] = 'role_description = :role_description';
                $updateParams['role_description'] = $roleDescription;
            }

            db_execute("
                UPDATE users
                SET " . implode(', ', $setParts) . ",
                    updated_at = NOW()
                WHERE id = :id
                LIMIT 1
            ", $updateParams);

            $successMessage = 'User updated successfully.';
        }
    }
}

if (is_post() && post('action') === 'regenerate_invite') {
    require_csrf();

    $targetUserId = (int) post('user_id');

    if ($targetUserId <= 0) {
        $errorMessage = 'Invalid user.';
    } else {
        $targetUser = auth_find_user_by_id($targetUserId);

        if (!$targetUser) {
            $errorMessage = 'User not found.';
        } else {
            $inviteToken = auth_generate_token(32);
            $inviteExpiresAt = auth_token_expires_at(72);

            db_execute("
                UPDATE users
                SET
                    invite_token = :invite_token,
                    invite_expires_at = :invite_expires_at,
                    must_change_password = 1,
                    updated_at = NOW()
                WHERE id = :id
                LIMIT 1
            ", [
                'invite_token' => $inviteToken,
                'invite_expires_at' => $inviteExpiresAt,
                'id' => $targetUserId,
            ]);

            $generatedInviteEmail = (string) ($targetUser['email'] ?? '');
            $generatedInviteLink = base_url('accept-invite.php?token=' . urlencode($inviteToken));

            $mailSent = elite_send_invite_email(
                (string) ($targetUser['email'] ?? ''),
                (string) ($targetUser['first_name'] ?? ''),
                $generatedInviteLink
            );

            $emailSendStatus = $mailSent
                ? 'Fresh invite email sent successfully.'
                : 'Fresh invite generated, but email sending failed. Use the invite link below manually.';

            $successMessage = 'A fresh invite link was generated.';
        }
    }
}

if (is_post() && post('action') === 'generate_mobile_qr') {
    require_csrf();

    $targetUserId = (int) post('user_id');
    $targetUser = $targetUserId > 0 ? auth_find_user_by_id($targetUserId) : null;

    if (!$targetUser) {
        $errorMessage = 'User not found.';
    } elseif ((int) ($targetUser['is_active'] ?? 0) !== 1) {
        $errorMessage = 'User must be active before mobile access can be issued.';
    } else {
        $token = mobile_ai_issue_setup_token($targetUserId, auth_user_id());
        $generatedMobileLink = mobile_ai_qr_setup_url($token);
        $generatedMobileQrUrl = mobile_ai_qr_image_url($token);
        $generatedMobileUserLabel = trim(((string) ($targetUser['first_name'] ?? '')) . ' ' . ((string) ($targetUser['last_name'] ?? '')));
        $generatedMobileUserLabel = $generatedMobileUserLabel !== '' ? $generatedMobileUserLabel : (string) ($targetUser['email'] ?? 'User');
        $successMessage = 'Mobile AI QR link generated.';
    }
}

if (is_post() && post('action') === 'revoke_mobile_access') {
    require_csrf();

    $targetUserId = (int) post('user_id');
    $targetUser = $targetUserId > 0 ? auth_find_user_by_id($targetUserId) : null;

    if (!$targetUser) {
        $errorMessage = 'User not found.';
    } else {
        mobile_ai_revoke_user_access($targetUserId);
        $successMessage = 'Mobile AI access revoked for this user.';
    }
}

$users = users_page_fetch_all();
$users = array_map(static function (array $row): array {
    $userId = (int) ($row['id'] ?? 0);
    $row['mobile_setup'] = $userId > 0 ? mobile_ai_latest_setup_token_for_user($userId) : null;
    $row['mobile_session'] = $userId > 0 ? mobile_ai_latest_session_for_user($userId) : null;
    return $row;
}, $users);
$topNavItems = users_top_nav_items();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> | Users</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="robots" content="noindex,nofollow">
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <?php require __DIR__ . '/app/partials/crm_sidebar.php'; ?>

    <div class="min-h-screen">
        <main class="px-4 py-6 sm:px-6 lg:pl-80 lg:pr-8 lg:py-8">
            <section class="mb-8">
                <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm lg:p-8">
                    <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                        <div class="max-w-3xl">
                            <div class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-600">
                                Access Control
                            </div>

                            <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-900 lg:text-4xl">
                                Team access and invitations
                            </h2>

                            <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-600 sm:text-base">
                                Create users, assign roles, and send secure invite links directly from the CRM.
                            </p>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            Current focus:
                            <span class="font-medium text-slate-900">user creation, invites, password onboarding</span>
                        </div>
                    </div>
                </div>
            </section>

            <?php if ($errorMessage !== ''): ?>
                <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <?= e($errorMessage) ?>
                </div>
            <?php endif; ?>

            <?php if ($successMessage !== ''): ?>
                <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    <?= e($successMessage) ?>
                </div>
            <?php endif; ?>

            <?php if ($emailSendStatus !== ''): ?>
                <div class="mb-6 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm">
                    <?= e($emailSendStatus) ?>
                </div>
            <?php endif; ?>

            <?php if ($generatedInviteLink !== ''): ?>
                <section class="mb-6">
                    <div class="rounded-[2rem] border border-emerald-200 bg-white p-6 shadow-sm">
                        <p class="text-xs uppercase tracking-[0.2em] text-emerald-600">Invite Ready</p>
                        <h3 class="mt-2 text-xl font-semibold text-slate-900"><?= e($generatedInviteEmail) ?></h3>

                        <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="mb-2 text-xs uppercase tracking-[0.18em] text-slate-500">Invite Link</p>
                            <div class="break-all rounded-xl bg-white px-4 py-3 text-sm text-slate-700 ring-1 ring-slate-200">
                                <?= e($generatedInviteLink) ?>
                            </div>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($generatedMobileLink !== ''): ?>
                <div id="mobile-ai-qr-modal" class="fixed inset-0 z-50 flex items-center justify-center px-4 py-8">
                    <div id="mobile-ai-qr-backdrop" class="absolute inset-0 bg-slate-950/55 backdrop-blur-sm"></div>
                    <div class="relative z-10 w-full max-w-3xl rounded-[2rem] border border-amber-200 bg-white p-6 shadow-2xl lg:p-8">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs uppercase tracking-[0.2em] text-amber-700">Mobile AI Access Ready</p>
                                <h3 class="mt-2 text-2xl font-semibold text-slate-900"><?= e($generatedMobileUserLabel) ?></h3>
                                <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-600">
                                    Scan this fresh QR from an iPhone to create a trusted mobile session. The setup link is temporary, one-time use, and contains no password, API key, or secret.
                                </p>
                            </div>
                            <button
                                type="button"
                                id="mobile-ai-qr-close"
                                class="rounded-2xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
                            >
                                Close
                            </button>
                        </div>

                        <div class="mt-6 grid gap-5 lg:grid-cols-[260px_1fr] lg:items-start">
                            <div class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-slate-50 p-4">
                                <img src="<?= e($generatedMobileQrUrl) ?>" alt="Mobile AI QR code" class="mx-auto h-auto w-full max-w-[220px]">
                            </div>

                            <div class="space-y-4">
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <p class="mb-2 text-xs uppercase tracking-[0.18em] text-slate-500">Mobile Setup Link</p>
                                    <div id="mobile-ai-qr-link" class="break-all rounded-xl bg-white px-4 py-3 text-sm text-slate-700 ring-1 ring-slate-200">
                                        <?= e($generatedMobileLink) ?>
                                    </div>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            id="mobile-ai-copy-link"
                                            class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800"
                                        >
                                            Copy Mobile Link
                                        </button>
                                        <a
                                            href="<?= e($generatedMobileLink) ?>"
                                            target="_blank"
                                            rel="noreferrer"
                                            class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
                                        >
                                            Open Setup Page
                                        </a>
                                    </div>
                                </div>

                                <div class="grid gap-3 sm:grid-cols-3">
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                                        <div class="text-xs uppercase tracking-[0.14em] text-slate-500">Token Window</div>
                                        <div class="mt-1 font-medium text-slate-900">About 15 minutes</div>
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                                        <div class="text-xs uppercase tracking-[0.14em] text-slate-500">Usage</div>
                                        <div class="mt-1 font-medium text-slate-900">One-time setup only</div>
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                                        <div class="text-xs uppercase tracking-[0.14em] text-slate-500">Safety</div>
                                        <div class="mt-1 font-medium text-slate-900">Revokable anytime</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-[0.95fr_1.35fr]">
                <section>
                    <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                        <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Create User</p>
                        <h3 class="mt-2 text-xl font-semibold text-slate-900">New team member</h3>

                        <form method="POST" action="<?= e(base_url('users.php')) ?>" class="mt-6 space-y-4">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="create_user">

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label for="first_name" class="mb-2 block text-sm font-medium text-slate-700">First Name</label>
                                    <input
                                        type="text"
                                        id="first_name"
                                        name="first_name"
                                        value="<?= e(old('first_name')) ?>"
                                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                                        placeholder="First name"
                                        required
                                    >
                                </div>

                                <div>
                                    <label for="last_name" class="mb-2 block text-sm font-medium text-slate-700">Last Name</label>
                                    <input
                                        type="text"
                                        id="last_name"
                                        name="last_name"
                                        value="<?= e(old('last_name')) ?>"
                                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                                        placeholder="Last name"
                                        required
                                    >
                                </div>
                            </div>

                            <div>
                                <label for="email" class="mb-2 block text-sm font-medium text-slate-700">Email</label>
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    value="<?= e(old('email')) ?>"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                                    placeholder="teammember@yourdomain.com"
                                    required
                                >
                            </div>

                            <div>
                                <label for="phone" class="mb-2 block text-sm font-medium text-slate-700">Phone</label>
                                <input
                                    type="text"
                                    id="phone"
                                    name="phone"
                                    value="<?= e(old('phone')) ?>"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                                    placeholder="(000) 000-0000"
                                >
                            </div>

                            <div>
                                <label for="role_description" class="mb-2 block text-sm font-medium text-slate-700">Role Description</label>
                                <textarea
                                    id="role_description"
                                    name="role_description"
                                    rows="3"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                                    placeholder="Optional role responsibilities / notes for this user"
                                ><?= e(old('role_description')) ?></textarea>
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label for="role" class="mb-2 block text-sm font-medium text-slate-700">Role</label>
                                    <select
                                        id="role"
                                        name="role"
                                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                                    >
                                        <?php foreach (users_page_roles() as $roleKey => $roleLabel): ?>
                                            <option value="<?= e($roleKey) ?>" <?= old('role', 'viewer') === $roleKey ? 'selected' : '' ?>>
                                                <?= e($roleLabel) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label for="is_active" class="mb-2 block text-sm font-medium text-slate-700">Status</label>
                                    <select
                                        id="is_active"
                                        name="is_active"
                                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                                    >
                                        <option value="1" <?= old('is_active', '1') === '1' ? 'selected' : '' ?>>Active</option>
                                        <option value="0" <?= old('is_active') === '0' ? 'selected' : '' ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>

                            <button
                                type="submit"
                                class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800"
                            >
                                Create User & Send Invite
                            </button>
                        </form>
                    </div>
                </section>

                <section>
                    <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Users</p>
                                <h3 class="mt-2 text-xl font-semibold text-slate-900">Current team</h3>
                            </div>

                            <div class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs text-slate-600">
                                <?= e((string) count($users)) ?> total
                            </div>
                        </div>

                        <div class="mt-6 overflow-hidden rounded-[1.5rem] border border-slate-200">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Name</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Email</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Phone</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Role</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Role Description</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Status</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Last Login</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Mobile AI Access</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Invite</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200 bg-white">
                                        <?php if (empty($users)): ?>
                                            <tr>
                                                <td colspan="10" class="px-4 py-10 text-center text-sm text-slate-500">
                                                    No users yet.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($users as $row): ?>
                                                <?php
                                                    $fullName = trim((string) ($row['first_name'] ?? '')) . ' ' . trim((string) ($row['last_name'] ?? ''));
                                                    $inviteReady = !empty($row['invite_token']) && !empty($row['invite_expires_at']);
                                                    $rowRole = (string) ($row['role'] ?? 'viewer');
                                                    $rowRoleDescription = trim((string) ($row['role_description'] ?? ''));
                                                    $mobileSetup = is_array($row['mobile_setup'] ?? null) ? $row['mobile_setup'] : null;
                                                    $mobileSession = is_array($row['mobile_session'] ?? null) ? $row['mobile_session'] : null;
                                                    if ($rowRoleDescription === '') {
                                                        $rowRoleDescription = users_page_role_descriptions()[$rowRole] ?? '';
                                                    }
                                                ?>
                                                <tr>
                                                    <td class="px-4 py-4 align-top">
                                                        <div class="text-sm font-semibold text-slate-900"><?= e($fullName !== '' ? $fullName : 'Unnamed User') ?></div>
                                                    </td>

                                                    <td class="px-4 py-4 align-top">
                                                        <div class="text-sm text-slate-900"><?= e((string) ($row['email'] ?? '')) ?></div>
                                                    </td>

                                                    <td class="px-4 py-4 align-top">
                                                        <div class="text-sm text-slate-900"><?= e((string) ($row['phone'] ?? 'N/A')) ?></div>
                                                    </td>

                                                    <td class="px-4 py-4 align-top">
                                                        <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-700">
                                                            <?= e(users_page_roles()[$rowRole] ?? $rowRole) ?>
                                                        </span>
                                                    </td>

                                                    <td class="px-4 py-4 align-top text-sm text-slate-500">
                                                        <?= e($rowRoleDescription !== '' ? $rowRoleDescription : 'No description yet') ?>
                                                    </td>

                                                    <td class="px-4 py-4 align-top">
                                                        <?php if ((int) ($row['is_active'] ?? 0) === 1): ?>
                                                            <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">
                                                                Active
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="inline-flex rounded-full border border-red-200 bg-red-50 px-3 py-1 text-xs font-medium text-red-700">
                                                                Inactive
                                                            </span>
                                                        <?php endif; ?>

                                                        <?php if ((int) ($row['must_change_password'] ?? 0) === 1): ?>
                                                            <div class="mt-2 text-xs text-amber-600">Password setup pending</div>
                                                        <?php endif; ?>
                                                    </td>

                                                    <td class="px-4 py-4 align-top text-sm text-slate-500">
                                                        <?= e(!empty($row['last_login_at']) ? (string) $row['last_login_at'] : 'Never') ?>
                                                    </td>

                                                    <td class="px-4 py-4 align-top">
                                                        <div class="space-y-2 rounded-2xl border border-slate-200 bg-slate-50 p-3">
                                                            <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Device</div>
                                                            <div class="text-sm font-medium text-slate-900"><?= e(users_page_mobile_label($mobileSession)) ?></div>
                                                            <div class="text-xs text-slate-500">
                                                                Last mobile login: <?= e(!empty($mobileSession['last_seen_at']) ? (string) $mobileSession['last_seen_at'] : 'Never') ?>
                                                            </div>
                                                            <div class="text-xs text-slate-500">
                                                                QR status:
                                                                <?php
                                                                    $setupStatus = 'Not generated';
                                                                    if ($mobileSetup) {
                                                                        $setupStatus = 'Ready';
                                                                        if (!empty($mobileSetup['used_at'])) {
                                                                            $setupStatus = 'Used';
                                                                        } elseif (!empty($mobileSetup['revoked_at'])) {
                                                                            $setupStatus = 'Revoked';
                                                                        } elseif (!empty($mobileSetup['expires_at']) && strtotime((string) $mobileSetup['expires_at']) !== false && strtotime((string) $mobileSetup['expires_at']) < time()) {
                                                                            $setupStatus = 'Expired';
                                                                        }
                                                                    }
                                                                ?>
                                                                <?= e($setupStatus) ?>
                                                            </div>
                                                            <div class="text-xs text-slate-500">
                                                                Generate a fresh QR anytime to preview or copy a new one-time setup link.
                                                            </div>
                                                            <div class="flex flex-wrap gap-2 pt-1">
                                                                <form method="POST" action="<?= e(base_url('users.php')) ?>">
                                                                    <?= csrf_input() ?>
                                                                    <input type="hidden" name="action" value="generate_mobile_qr">
                                                                    <input type="hidden" name="user_id" value="<?= e((string) ($row['id'] ?? 0)) ?>">
                                                                    <button type="submit" class="inline-flex items-center justify-center rounded-2xl border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800 transition hover:bg-amber-100">
                                                                        <?= $mobileSetup ? 'Regenerate QR' : 'Generate Mobile QR' ?>
                                                                    </button>
                                                                </form>
                                                                <form method="POST" action="<?= e(base_url('users.php')) ?>">
                                                                    <?= csrf_input() ?>
                                                                    <input type="hidden" name="action" value="revoke_mobile_access">
                                                                    <input type="hidden" name="user_id" value="<?= e((string) ($row['id'] ?? 0)) ?>">
                                                                    <button type="submit" class="inline-flex items-center justify-center rounded-2xl border border-red-300 bg-red-50 px-3 py-2 text-xs font-medium text-red-700 transition hover:bg-red-100">
                                                                        Revoke
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </td>

                                                    <td class="px-4 py-4 align-top">
                                                        <div class="space-y-2">
                                                            <?php if ($inviteReady): ?>
                                                                <div class="text-xs text-slate-500">
                                                                    Expires: <?= e((string) ($row['invite_expires_at'] ?? '')) ?>
                                                                </div>
                                                            <?php endif; ?>

                                                            <form method="POST" action="<?= e(base_url('users.php')) ?>">
                                                                <?= csrf_input() ?>
                                                                <input type="hidden" name="action" value="regenerate_invite">
                                                                <input type="hidden" name="user_id" value="<?= e((string) ($row['id'] ?? 0)) ?>">
                                                                <button
                                                                    type="submit"
                                                                    class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-100"
                                                                >
                                                                    <?= $inviteReady ? 'Refresh Invite & Send' : 'Generate Invite & Send' ?>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>

                                                    <td class="px-4 py-4 align-top">
                                                        <button
                                                            type="button"
                                                            class="open-user-edit inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-100"
                                                            data-user-id="<?= e((string) ($row['id'] ?? 0)) ?>"
                                                            data-first-name="<?= e((string) ($row['first_name'] ?? '') ) ?>"
                                                            data-last-name="<?= e((string) ($row['last_name'] ?? '') ) ?>"
                                                            data-email="<?= e((string) ($row['email'] ?? '') ) ?>"
                                                            data-phone="<?= e((string) ($row['phone'] ?? '') ) ?>"
                                                            data-role="<?= e((string) ($row['role'] ?? 'viewer')) ?>"
                                                            data-is-active="<?= e((string) ((int) ($row['is_active'] ?? 0) === 1 ? 1 : 0)) ?>"
                                                            data-role-description="<?= e((string) ($rowRoleDescription ?? '')) ?>"
                                                        >
                                                            <span class="sr-only">Edit user</span>
                                                            <svg
                                                                xmlns="http://www.w3.org/2000/svg"
                                                                viewBox="0 0 24 24"
                                                                fill="none"
                                                                stroke="currentColor"
                                                                stroke-width="2"
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                                class="h-4 w-4"
                                                                aria-hidden="true"
                                                            >
                                                                <path d="M12 20h9"></path>
                                                                <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
                                                                <path d="M14.5 6.5 17 9"></path>
                                                            </svg>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <div id="user-edit-modal" class="fixed inset-0 z-50 hidden">
        <div id="user-edit-backdrop" class="absolute inset-0 bg-black/40"></div>
        <div class="relative mx-auto mt-24 w-[min(90vw,680px)] rounded-[1.5rem] border border-slate-200 bg-white p-6 shadow-2xl">
            <div class="mb-4 flex items-start justify-between">
                <div>
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Edit User</p>
                    <h3 id="user-edit-title" class="mt-1 text-xl font-semibold text-slate-900">Update team member</h3>
                </div>
                <button
                    type="button"
                    id="user-edit-close"
                    class="rounded-xl border border-slate-200 px-3 py-1 text-sm text-slate-700 hover:bg-slate-50"
                >
                    Close
                </button>
            </div>

            <form id="user-edit-form" method="POST" action="<?= e(base_url('users.php')) ?>" class="space-y-4">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="user_id" id="edit-user-id" value="">

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="edit-user-first-name" class="mb-1 block text-xs font-medium text-slate-700">First Name</label>
                        <input
                            type="text"
                            id="edit-user-first-name"
                            name="first_name"
                            class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            required
                        >
                    </div>
                    <div>
                        <label for="edit-user-last-name" class="mb-1 block text-xs font-medium text-slate-700">Last Name</label>
                        <input
                            type="text"
                            id="edit-user-last-name"
                            name="last_name"
                            class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            required
                        >
                    </div>
                </div>

                <div>
                    <label for="edit-user-email" class="mb-1 block text-xs font-medium text-slate-700">Email</label>
                    <input
                        type="email"
                        id="edit-user-email"
                        name="email"
                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                        required
                    >
                </div>

                <div>
                    <label for="edit-user-phone" class="mb-1 block text-xs font-medium text-slate-700">Phone</label>
                    <input
                        type="text"
                        id="edit-user-phone"
                        name="phone"
                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                        placeholder="(000) 000-0000"
                    >
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="edit-user-role" class="mb-1 block text-xs font-medium text-slate-700">Role</label>
                        <select
                            id="edit-user-role"
                            name="role"
                            class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                        >
                            <?php foreach (users_page_roles() as $roleKey => $roleLabel): ?>
                                <option value="<?= e($roleKey) ?>"><?= e($roleLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="edit-user-status" class="mb-1 block text-xs font-medium text-slate-700">Status</label>
                        <select
                            id="edit-user-status"
                            name="is_active"
                            class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                        >
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="edit-user-role-description" class="mb-1 block text-xs font-medium text-slate-700">Role Description</label>
                    <textarea
                        id="edit-user-role-description"
                        name="role_description"
                        rows="3"
                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                        placeholder="Custom role responsibilities"
                    ></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button
                        type="button"
                        id="user-edit-cancel"
                        class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800"
                    >
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            var modal = document.getElementById('user-edit-modal');
            var closeBtn = document.getElementById('user-edit-close');
            var cancelBtn = document.getElementById('user-edit-cancel');
            var backdrop = document.getElementById('user-edit-backdrop');
            var title = document.getElementById('user-edit-title');
            var inputId = document.getElementById('edit-user-id');
            var inputFirst = document.getElementById('edit-user-first-name');
            var inputLast = document.getElementById('edit-user-last-name');
            var inputEmail = document.getElementById('edit-user-email');
            var inputPhone = document.getElementById('edit-user-phone');
            var inputRole = document.getElementById('edit-user-role');
            var inputStatus = document.getElementById('edit-user-status');
            var inputRoleDescription = document.getElementById('edit-user-role-description');

            function openModal() {
                if (!modal) {
                    return;
                }
                modal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }

            function closeModal() {
                if (!modal) {
                    return;
                }
                modal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }

            document.querySelectorAll('.open-user-edit').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    inputId.value = btn.getAttribute('data-user-id') || '';
                    inputFirst.value = btn.getAttribute('data-first-name') || '';
                    inputLast.value = btn.getAttribute('data-last-name') || '';
                    inputEmail.value = btn.getAttribute('data-email') || '';
                    inputPhone.value = btn.getAttribute('data-phone') || '';
                    inputRole.value = btn.getAttribute('data-role') || 'viewer';
                    inputStatus.value = btn.getAttribute('data-is-active') || '1';
                    inputRoleDescription.value = btn.getAttribute('data-role-description') || '';
                    title.textContent = 'Edit user';
                    openModal();
                });
            });

            if (closeBtn) {
                closeBtn.addEventListener('click', closeModal);
            }

            if (cancelBtn) {
                cancelBtn.addEventListener('click', function (event) {
                    event.preventDefault();
                    closeModal();
                });
            }

            if (backdrop) {
                backdrop.addEventListener('click', closeModal);
            }

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                    closeModal();
                }
            });

            var qrModal = document.getElementById('mobile-ai-qr-modal');
            var qrBackdrop = document.getElementById('mobile-ai-qr-backdrop');
            var qrClose = document.getElementById('mobile-ai-qr-close');
            var qrCopy = document.getElementById('mobile-ai-copy-link');
            var qrLink = document.getElementById('mobile-ai-qr-link');

            function closeQrModal() {
                if (!qrModal) {
                    return;
                }
                qrModal.classList.add('hidden');
            }

            if (qrClose) {
                qrClose.addEventListener('click', closeQrModal);
            }

            if (qrBackdrop) {
                qrBackdrop.addEventListener('click', closeQrModal);
            }

            if (qrCopy && qrLink) {
                qrCopy.addEventListener('click', async function () {
                    var text = qrLink.textContent || '';

                    try {
                        await navigator.clipboard.writeText(text.trim());
                        qrCopy.textContent = 'Link Copied';
                    } catch (error) {
                        qrCopy.textContent = 'Copy Failed';
                    }

                    window.setTimeout(function () {
                        qrCopy.textContent = 'Copy Mobile Link';
                    }, 1600);
                });
            }
        })();
    </script>
</body>
</html>
