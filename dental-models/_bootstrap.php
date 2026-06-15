<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';
require_once ROOT_PATH . '/app/core/helpers.php';
require_once ROOT_PATH . '/app/core/db.php';
require_once ROOT_PATH . '/app/core/auth.php';
require_once ROOT_PATH . '/app/dental_models/dental_models_service.php';

dental_models_ensure_schema();

function dental_models_internal_boot(string $pageTitle = 'Dental Models'): array
{
    require_auth();
    dental_models_staff_gate();

    if (is_post() && post('action') === 'logout') {
        require_csrf();
        auth_logout();
        flash_set('success', 'You have been logged out.');
        redirect(base_url('login.php'));
    }

    $user = auth_user() ?: [];

    $GLOBALS['currentPage'] = 'dental_models';
    $GLOBALS['pageTitle'] = $pageTitle;
    $GLOBALS['firstName'] = $user['first_name'] ?? 'User';
    $GLOBALS['logoUrl'] = base_url('assets/img/ES-Logo-Stack-500-x-150-px.png');
    $GLOBALS['logoutAction'] = $_SERVER['REQUEST_URI'] ?? base_url('dental-models');

    return $user;
}

function dental_models_render_shell_start(string $title): void
{
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e(APP_NAME) ?> | <?= e($title) ?></title>
        <script src="https://cdn.tailwindcss.com"></script>
        <meta name="robots" content="noindex,nofollow">
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
        <?php require ROOT_PATH . '/app/partials/crm_sidebar.php'; ?>
        <main class="px-4 py-6 sm:px-6 lg:pl-80 lg:pr-8 lg:py-8">
            <?php if (($message = flash_get('success'))): ?>
                <div class="mb-5 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    <?= e((string)$message) ?>
                </div>
            <?php endif; ?>

            <?php if (($message = flash_get('error'))): ?>
                <div class="mb-5 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <?= e((string)$message) ?>
                </div>
            <?php endif; ?>
            <?php if (($message = flash_get('info'))): ?>
                <div class="mb-5 rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    <?= e((string)$message) ?>
                </div>
            <?php endif; ?>
            <section class="mb-6 rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm lg:p-8">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Dental Model Builder</p>
                        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950"><?= e($title) ?></h1>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                            Internal preview workspace for .stl model review and print-prep planning. No mesh edits in V1.
                        </p>
                    </div>
                    <div class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-xs text-slate-600">
                        Status: <span class="font-semibold text-slate-900">V1 Preview Mode</span>
                    </div>
                </div>
            <div class="mt-5 flex flex-wrap gap-2">
                <a class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white" href="<?= e(base_url('dental-models/new')) ?>">
                    New Upload
                </a>
                <a class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700" href="<?= e(base_url('dental-models')) ?>">
                    All Models
                </a>
            </div>
        </section>
<?php
}

function dental_models_render_shell_end(): void
{
    ?>
        </main>
    </body>
    </html>
<?php
}
