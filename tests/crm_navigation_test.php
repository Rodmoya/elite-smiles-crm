<?php
declare(strict_types=1);

function base_url(string $path): string { return '/' . $path; }
function auth_has_role(string ...$roles): bool { return in_array($GLOBALS['testRole'], $roles, true); }
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$root = dirname(__DIR__);
foreach (['admin', 'staff', 'viewer'] as $testRole) {
    $crmCanUseMarketing = $testRole === 'admin';
    $crmHasPatientMailings = true;
    require $root . '/app/partials/crm_nav_items.php';
    $keys = array_column($crmNavItems, 'key');
    check(array_slice($keys, 0, 3) === ['dashboard', 'leads', 'smile_design'], 'Primary order');
    check(!in_array('dental_models', $keys, true), '3D Design hidden');
    check(count($keys) === count(array_unique($keys)), 'No duplicate routes');
    check(in_array('patient_experience', $keys, true) === ($testRole !== 'viewer'), 'Patient permissions');
    check(in_array('users', $keys, true) === ($testRole === 'admin'), 'Admin permissions');
    if ($testRole !== 'viewer') {
        check($keys[3] === 'patient_experience' && $keys[4] === 'patient_contracts', 'Patient and contract order');
    }
}
foreach (['crm_sidebar.php', 'crm_sidebar_live.php'] as $file) {
    $source = file_get_contents($root . '/app/partials/' . $file);
    check(str_contains($source, "require __DIR__ . '/crm_nav_items.php'"), 'Shared menu: ' . $file);
    check(str_contains($source, "require __DIR__ . '/crm_navigation_behavior.php'"), 'Shared scrolling: ' . $file);
}
echo "CRM navigation tests passed.\n";
