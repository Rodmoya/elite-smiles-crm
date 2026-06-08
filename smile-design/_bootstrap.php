<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/config.php';
require_once ROOT_PATH . '/app/core/helpers.php';
require_once ROOT_PATH . '/app/core/db.php';
require_once ROOT_PATH . '/app/core/auth.php';
require_once ROOT_PATH . '/app/smile_design/smile_design_service.php';
require_once ROOT_PATH . '/app/partials/smile_before_after_viewer.php';

smile_design_ensure_schema();

function smile_design_internal_boot(string $pageTitle = 'Smile Design'): array
{
    require_auth();
    if (is_post() && post('action') === 'logout') {
        require_csrf();
        auth_logout();
        redirect(base_url('login.php'));
    }
    $GLOBALS['currentPage'] = 'smile_design';
    $GLOBALS['pageTitle'] = $pageTitle;
    $GLOBALS['logoUrl'] = base_url('assets/img/ES-Logo-Stack-500-x-150-px.png');
    $GLOBALS['logoutAction'] = $_SERVER['REQUEST_URI'] ?? base_url('smile-design');
    return auth_user() ?: [];
}

function smile_design_page_header(string $title, string $subtitle = ''): void
{
    ?>
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Smile Design Engine</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950"><?= e($title) ?></h1>
            <?php if ($subtitle !== ''): ?>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600"><?= e($subtitle) ?></p>
            <?php endif; ?>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= e(base_url('smile-design/staff-intake')) ?>" class="rounded-md bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">New Case</a>
            <a href="<?= e(base_url('smile-design/gallery')) ?>" class="rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700">Gallery</a>
        </div>
    </div>
    <nav class="mb-6 flex gap-2 overflow-x-auto pb-1 text-sm" aria-label="Smile Design navigation">
        <?php foreach ([
            ['Dashboard', 'smile-design'],
            ['Staff Intake', 'smile-design/staff-intake'],
            ['Consult Tool', 'smile-design/consult'],
            ['Cases', 'smile-design/cases'],
            ['Gallery', 'smile-design/gallery'],
            ['LVI Library', 'smile-design/lvi-library'],
            ['Diagnostics', 'smile-design/diagnostics'],
        ] as [$label, $path]): ?>
            <a class="shrink-0 rounded-md border border-slate-300 bg-white px-3 py-2 font-semibold text-slate-700" href="<?= e(base_url($path)) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <?php
}

function smile_design_render_shell_start(string $title): void
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
    <body class="min-h-screen bg-slate-50 text-slate-950 antialiased">
        <?php require ROOT_PATH . '/app/partials/crm_sidebar.php'; ?>
        <main class="px-4 py-6 sm:px-6 lg:pl-80 lg:pr-8 lg:py-8">
            <?php if (($message = flash_get('success'))): ?>
                <div class="mb-5 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= e((string)$message) ?></div>
            <?php endif; ?>
            <?php if (($message = flash_get('error'))): ?>
                <div class="mb-5 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= e((string)$message) ?></div>
            <?php endif; ?>
    <?php
}

function smile_design_render_shell_end(): void
{
    ?>
        </main>
        <div id="smile-action-loader" class="fixed inset-0 z-[70] hidden items-center justify-center bg-slate-950/55 px-4" role="status" aria-live="polite" aria-label="Working">
            <div class="flex flex-col items-center gap-4 rounded-xl bg-white px-8 py-7 text-center shadow-2xl">
                <div class="h-12 w-12 animate-spin rounded-full border-4 border-slate-200 border-t-slate-950"></div>
                <p id="smile-action-loader-label" class="text-sm font-semibold text-slate-800">Working...</p>
            </div>
        </div>
        <?php if (empty($GLOBALS['smile_design_skip_global_confirm_modal'])): ?>
            <div id="global-confirm-modal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-slate-950/55 px-4">
                <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl">
                    <h3 class="text-lg font-semibold text-slate-950">Please confirm</h3>
                    <p id="global-confirm-modal-message" class="mt-3 text-sm leading-6 text-slate-600">Are you sure?</p>
                    <div class="mt-6 flex justify-end gap-3">
                        <button id="global-confirm-modal-cancel" type="button" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                        <button id="global-confirm-modal-continue" type="button" class="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white">Delete</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <script>
        (function () {
            function initSmileDesignActions() {
            const loader = document.getElementById('smile-action-loader');
            const loaderLabel = document.getElementById('smile-action-loader-label');
            let pendingConfirmForm = null;
            document.documentElement.setAttribute('data-smile-loader-ready', '1');

            function actionLabel(form) {
                const explicit = form.getAttribute('data-loading-label');
                if (explicit) return explicit;
                const submitter = form.querySelector('button[type="submit"], input[type="submit"]');
                const raw = submitter ? (submitter.textContent || submitter.value || '') : '';
                const text = raw.trim().replace(/\s+/g, ' ');
                if (/generate/i.test(text)) return 'Generating...';
                if (/create/i.test(text)) return 'Creating...';
                if (/upload/i.test(text)) return 'Uploading...';
                if (/save/i.test(text)) return 'Saving...';
                if (/delete|remove/i.test(text)) return 'Deleting...';
                return 'Working...';
            }

            function showActionLoader(label) {
                document.documentElement.setAttribute('data-smile-loader-active', '1');
                if (loaderLabel) loaderLabel.textContent = label || 'Working...';
                if (loader) {
                    loader.classList.remove('hidden');
                    loader.classList.add('flex');
                }
                document.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (button) {
                    button.disabled = true;
                    button.classList.add('cursor-wait', 'opacity-80');
                });
            }

            function hideActionLoader() {
                document.documentElement.removeAttribute('data-smile-loader-active');
                if (loader) {
                    loader.classList.add('hidden');
                    loader.classList.remove('flex');
                }
                document.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (button) {
                    button.disabled = false;
                    button.classList.remove('cursor-wait', 'opacity-80');
                });
            }

            window.smileDesignShowActionLoader = showActionLoader;
            window.smileDesignHideActionLoader = hideActionLoader;

            const confirmModal = document.getElementById('global-confirm-modal');
            const confirmMessage = document.getElementById('global-confirm-modal-message');
            const confirmCancel = document.getElementById('global-confirm-modal-cancel');
            const confirmContinue = document.getElementById('global-confirm-modal-continue');

            if (confirmModal) {
                document.querySelectorAll('form[data-confirm]').forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (event.defaultPrevented) return;
                        event.preventDefault();
                        pendingConfirmForm = form;
                        if (confirmMessage) {
                            confirmMessage.textContent = form.getAttribute('data-confirm') || 'Are you sure?';
                        }
                        confirmModal.classList.remove('hidden');
                        confirmModal.classList.add('flex');
                    });
                });

                if (confirmCancel) {
                    confirmCancel.addEventListener('click', function () {
                        pendingConfirmForm = null;
                        confirmModal.classList.add('hidden');
                        confirmModal.classList.remove('flex');
                    });
                }
                if (confirmContinue) {
                    confirmContinue.addEventListener('click', function () {
                        const form = pendingConfirmForm;
                        pendingConfirmForm = null;
                        confirmModal.classList.add('hidden');
                        confirmModal.classList.remove('flex');
                        if (form) {
                            showActionLoader(actionLabel(form));
                            form.submit();
                        }
                    });
                }
                confirmModal.addEventListener('click', function (event) {
                    if (event.target === confirmModal) {
                        pendingConfirmForm = null;
                        confirmModal.classList.add('hidden');
                        confirmModal.classList.remove('flex');
                    }
                });
            }

            document.querySelectorAll('form[method]:not([data-confirm])').forEach(function (form) {
                if (String(form.getAttribute('method') || '').toUpperCase() !== 'POST') return;
                form.addEventListener('submit', function (event) {
                    if (event.defaultPrevented) return;
                    showActionLoader(actionLabel(form));
                });
            });
            }

            initSmileDesignActions();
        })();
        </script>
    </body>
    </html>
    <?php
}
