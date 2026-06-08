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

require_auth();
require_role('admin');

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

function users_page_roles(): array
{
    return [
        'admin' => 'Admin',
        'marketing_manager' => 'Marketing Manager',
        'staff' => 'Staff',
        'viewer' => 'Viewer',
    ];
}

function users_page_fetch_all(): array
{
    return db_all("
        SELECT
            id,
            first_name,
            last_name,
            email,
            role,
            is_active,
            invite_token,
            invite_expires_at,
            must_change_password,
            last_login_at,
            created_at,
            updated_at
        FROM users
        ORDER BY created_at DESC, id DESC
    ");
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
    $newRole = trim((string) post('role', 'viewer'));
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

            db_execute("
                INSERT INTO users (
                    first_name,
                    last_name,
                    email,
                    password_hash,
                    invite_token,
                    invite_expires_at,
                    password_reset_token,
                    password_reset_expires_at,
                    must_change_password,
                    role,
                    is_active,
                    created_at,
                    updated_at
                ) VALUES (
                    :first_name,
                    :last_name,
                    :email,
                    :password_hash,
                    :invite_token,
                    :invite_expires_at,
                    NULL,
                    NULL,
                    1,
                    :role,
                    :is_active,
                    NOW(),
                    NOW()
                )
            ", [
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email,
                'password_hash' => $temporaryHash,
                'invite_token' => $inviteToken,
                'invite_expires_at' => $inviteExpiresAt,
                'role' => $newRole,
                'is_active' => $isActive,
            ]);

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

$users = users_page_fetch_all();
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
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Role</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Status</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Last Login</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Invite</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200 bg-white">
                                        <?php if (empty($users)): ?>
                                            <tr>
                                                <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">
                                                    No users yet.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($users as $row): ?>
                                                <?php
                                                    $fullName = trim(((string) ($row['first_name'] ?? '')) . ' ' . ((string) ($row['last_name'] ?? '')));
                                                    $inviteReady = !empty($row['invite_token']) && !empty($row['invite_expires_at']);
                                                ?>
                                                <tr>
                                                    <td class="px-4 py-4 align-top">
                                                        <div class="text-sm font-semibold text-slate-900"><?= e($fullName !== '' ? $fullName : 'Unnamed User') ?></div>
                                                        <div class="mt-1 text-sm text-slate-500"><?= e((string) ($row['email'] ?? '')) ?></div>
                                                    </td>

                                                    <td class="px-4 py-4 align-top">
                                                        <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-700">
                                                            <?= e(users_page_roles()[(string) ($row['role'] ?? 'viewer')] ?? (string) ($row['role'] ?? 'viewer')) ?>
                                                        </span>
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
</body>
</html>
