<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: /leads.php
 *
 * Dedicated lead pipeline board.
 */

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/core/db.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/leads/lead_meta.php';
require_once __DIR__ . '/app/leads/lead_service.php';
require_once __DIR__ . '/app/leads/lead_communications.php';

require_auth();
lead_comm_ensure_schema();

if (is_post() && post('action') === 'logout') {
    require_csrf();
    auth_logout();
    flash_set('success', 'You have been logged out.');
    redirect(base_url('login.php'));
}

$user = auth_user();
$logoUrl = base_url('assets/img/ES-Logo-Stack-500-x-150-px.png');
$currentPage = 'leads';
$pageTitle = 'Leads';
$logoutAction = base_url('leads.php');

$successMessage = flash_get('success') ?? '';
$errorMessage = '';
$stats = lead_dashboard_stats();
$stageMap = function_exists('lead_pipeline_display_stage_map') ? lead_pipeline_display_stage_map() : lead_stage_map_ordered();
$pipelineCounts = lead_pipeline_counts();
$pipelineValues = lead_pipeline_stage_values();
$pipelineRows = lead_pipeline_rows(250);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> | Leads</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="robots" content="noindex,nofollow">
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <?php require __DIR__ . '/app/partials/crm_sidebar.php'; ?>

    <main class="px-4 py-6 sm:px-6 lg:pl-80 lg:pr-8 lg:py-8">
        <?php if ($successMessage !== ''): ?>
            <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                <?= e($successMessage) ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <?= e($errorMessage) ?>
            </div>
        <?php endif; ?>

        <section class="mb-4">
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(220px,360px)_minmax(0,1fr)] xl:items-end">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Lead Flow</p>
                    <h1 class="mt-1 text-3xl font-semibold tracking-tight text-slate-900 lg:text-4xl">Pipeline Board</h1>
                </div>

                <?php
                    $statsVariant = 'compact';
                    $statsInline = true;
                    require __DIR__ . '/app/partials/dashboard_stats.php';
                    unset($statsVariant, $statsInline);
                ?>
            </div>
        </section>

        <?php require __DIR__ . '/app/partials/dashboard_pipeline.php'; ?>
    </main>
    <script>
    (() => {
        const refreshMs = 60000;
        const quietMs = 15000;
        let lastInteractionAt = Date.now();

        const markInteraction = () => {
            lastInteractionAt = Date.now();
        };

        ['pointerdown', 'keydown', 'input', 'dragstart', 'drop', 'scroll'].forEach((eventName) => {
            window.addEventListener(eventName, markInteraction, { passive: true, capture: true });
        });

        const isOpen = (selector) => {
            const element = document.querySelector(selector);
            return element && !element.classList.contains('hidden');
        };

        window.setInterval(() => {
            const activeElement = document.activeElement;
            const isEditing = activeElement && ['INPUT', 'TEXTAREA', 'SELECT'].includes(activeElement.tagName);

            if (document.hidden || isEditing) return;
            if (isOpen('#lead-detail-modal') || isOpen('#new-lead-modal')) return;
            if (Date.now() - lastInteractionAt < quietMs) return;

            window.location.reload();
        }, refreshMs);
    })();
    </script>
</body>
</html>
