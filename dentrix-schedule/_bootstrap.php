<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';
require_once ROOT_PATH . '/app/core/helpers.php';
require_once ROOT_PATH . '/app/core/db.php';
require_once ROOT_PATH . '/app/core/auth.php';
require_once ROOT_PATH . '/app/dentrix_schedule/dentrix_schedule_service.php';
require_once ROOT_PATH . '/app/dentrix_schedule/dentrix_schedule_parser.php';
require_once ROOT_PATH . '/app/dentrix_schedule/dentrix_schedule_consult_engine.php';

dentrix_schedule_ensure_schema();

// Role-gate helpers (dentrix_schedule_allowed_staff_roles/is_staff_request/
// staff_gate) live in dentrix_schedule_service.php, not here, so JSON API
// endpoints can reuse the exact same role check without loading this
// page-rendering bootstrap.

function dentrix_schedule_internal_boot(string $pageTitle = 'Dentrix Schedule Bridge'): array
{
    require_auth();
    dentrix_schedule_staff_gate();

    $user = auth_user() ?: [];

    $GLOBALS['currentPage'] = 'dentrix_schedule';
    $GLOBALS['pageTitle'] = $pageTitle;
    $GLOBALS['firstName'] = $user['first_name'] ?? 'User';
    $GLOBALS['logoUrl'] = base_url('assets/img/ES-Logo-Stack-500-x-150-px.png');
    $GLOBALS['logoutAction'] = $_SERVER['REQUEST_URI'] ?? base_url('dentrix-schedule');

    return $user;
}

function dentrix_schedule_render_shell_start(string $title): void
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
                    <?= e((string) $message) ?>
                </div>
            <?php endif; ?>

            <?php if (($message = flash_get('error'))): ?>
                <div class="mb-5 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <?= e((string) $message) ?>
                </div>
            <?php endif; ?>

            <?php if (($message = flash_get('info'))): ?>
                <div class="mb-5 rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    <?= e((string) $message) ?>
                </div>
            <?php endif; ?>

            <section class="mb-6 rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm lg:p-8">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Dentrix Schedule Bridge</p>
                        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950"><?= e($title) ?></h1>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                            Import the Dentrix Appointment Book Day View PDFs the office prints and uploads, so the CRM can
                            mirror the schedule and detect open consult slots. Phase 1: manual upload only.
                        </p>
                    </div>
                    <div class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-xs text-slate-600">
                        Status: <span class="font-semibold text-slate-900">Phase 1 (manual upload)</span>
                    </div>
                </div>
            </section>
<?php
}

function dentrix_schedule_render_shell_end(): void
{
    ?>
        </main>
    </body>
    </html>
<?php
}
