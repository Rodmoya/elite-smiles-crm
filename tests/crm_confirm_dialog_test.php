<?php
declare(strict_types=1);

function crm_confirm_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$dialogSource = file_get_contents($root . '/assets/js/crm-confirm-dialog.js') ?: '';
$sidebarSource = file_get_contents($root . '/app/partials/crm_sidebar.php') ?: '';
$liveSidebarSource = file_get_contents($root . '/app/partials/crm_sidebar_live.php') ?: '';

crm_confirm_assert(str_contains($dialogSource, 'window.crmConfirm ='), 'The CRM must expose one shared confirmation dialog API.');
crm_confirm_assert(str_contains($dialogSource, "document.addEventListener('submit'"), 'The shared dialog must intercept declarative confirmation forms.');
crm_confirm_assert(str_contains($dialogSource, "form.dataset.crmConfirmBypass = '1'"), 'Confirmed forms must resume exactly once.');
crm_confirm_assert(str_contains($dialogSource, "dialog.addEventListener('cancel'"), 'The shared dialog must support keyboard Escape cancellation.');
crm_confirm_assert(str_contains($dialogSource, "cancelButton.focus()"), 'The safer Cancel action must receive initial keyboard focus.');
crm_confirm_assert(str_contains($dialogSource, 'prefers-reduced-motion'), 'Dialog motion must respect reduced-motion preferences.');
crm_confirm_assert(str_contains($sidebarSource, 'assets/js/crm-confirm-dialog.js'), 'The primary CRM shell must load the shared confirmation dialog.');
crm_confirm_assert(str_contains($liveSidebarSource, 'assets/js/crm-confirm-dialog.js'), 'The live CRM shell must load the shared confirmation dialog.');

$confirmationFiles = [
    '/social-studio.php',
    '/patient-mailings.php',
    '/landing_pages.php',
    '/smile-design/cases/show.php',
    '/app/partials/smile_before_after_viewer.php',
    '/app/partials/dashboard_pipeline.php',
];
foreach ($confirmationFiles as $relativePath) {
    $source = file_get_contents($root . $relativePath) ?: '';
    crm_confirm_assert(!str_contains($source, 'window.confirm('), "{$relativePath} must not use a native browser confirmation dialog.");
    crm_confirm_assert(!preg_match('/(?<!crm)confirm\s*\(/i', $source), "{$relativePath} must route confirmation through the shared CRM dialog.");
}

echo "CRM confirmation dialog tests passed.\n";
