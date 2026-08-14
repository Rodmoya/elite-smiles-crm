<?php
declare(strict_types=1);

function mailing_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$service = file_get_contents($root . '/app/mailings/mailing_service.php') ?: '';
$page = file_get_contents($root . '/patient-mailings.php') ?: '';
$click = file_get_contents($root . '/app/api/mailing_click.php') ?: '';
$auth = file_get_contents($root . '/app/core/auth.php') ?: '';
$sidebar = file_get_contents($root . '/app/partials/crm_sidebar.php') ?: '';
$htaccess = file_get_contents($root . '/.htaccess') ?: '';

mailing_assert(
    str_contains($service, "SHOW COLUMNS FROM mailing_campaigns LIKE 'image_storage_key'"),
    'The MariaDB column check must use a quoted literal.'
);
mailing_assert(
    !str_contains($service, "SHOW COLUMNS FROM mailing_campaigns LIKE :column_name"),
    'Mailing schema setup must not bind a parameter in SHOW COLUMNS LIKE.'
);
mailing_assert(
    str_contains($service, "['approved', 'scheduled', 'sending']"),
    'Only approved or already-running campaigns may enter delivery.'
);
mailing_assert(
    str_contains($service, "'remaining' => \$remaining") && str_contains($service, "'finished' => \$finished"),
    'Batch delivery must report whether additional recipients remain.'
);
mailing_assert(
    !str_contains($click, "input('u')"),
    'Tracked clicks must not accept an arbitrary redirect destination.'
);
mailing_assert(
    str_contains($click, "\$campaign['cta_url']"),
    'Tracked clicks must resolve the approved campaign CTA.'
);
mailing_assert(
    str_contains($auth, 'function require_marketing_access') && str_contains($sidebar, 'auth_can_use_marketing()'),
    'Marketing visibility and server authorization must share one role policy.'
);
mailing_assert(
    str_contains($page, 'require_marketing_access();') && str_contains($page, 'data-mailing-busy'),
    'The mailing workspace must enforce marketing access and show blocking progress feedback.'
);
mailing_assert(
    substr_count($page, '<h1') === 1 && !str_contains($sidebar, '<h1 class="mt-2 text-xl'),
    'The mailing workspace and shared shell must expose one page-level heading.'
);
mailing_assert(
    str_contains($htaccess, 'landing-live-backup|landingtest|make-password|test_bootstrap|test_plain'),
    'Public development utilities must be denied by the web server.'
);

$protectedFiles = array_merge(
    glob($root . '/app/actions/mailing_*.php') ?: [],
    glob($root . '/app/actions/social_studio_*.php') ?: []
);
foreach ($protectedFiles as $file) {
    $source = file_get_contents($file) ?: '';
    mailing_assert(
        str_contains($source, 'require_marketing_access();'),
        basename($file) . ' must enforce the shared marketing role policy.'
    );
}

echo "Mailing Campaigns tests passed.\n";
