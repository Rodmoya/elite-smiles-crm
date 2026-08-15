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
$generateAction = file_get_contents($root . '/app/actions/mailing_generate.php') ?: '';
$scheduleAction = file_get_contents($root . '/app/actions/mailing_schedule.php') ?: '';
$cronApi = file_get_contents($root . '/app/api/mailing_send_cron.php') ?: '';
$e2eApi = file_get_contents($root . '/app/api/mailing_e2e_test.php') ?: '';
$workflow = file_get_contents($root . '/.github/workflows/mailing-campaigns.yml') ?: '';
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
mailing_assert(str_contains($click, 'mailing_campaign_destination($campaign)'), 'Tracked clicks must resolve the approved attributed campaign CTA.');
mailing_assert(str_contains($service, 'function mailing_campaign_destination'), 'Mailing CTA destinations must add campaign attribution.');
mailing_assert(str_contains($service, "utm_campaign' => 'mailing_'"), 'Landing-page leads must be attributable to a mailing campaign.');
mailing_assert(str_contains($service, 'function mailing_audience_options'), 'The campaign builder must expose controlled audience segments.');
mailing_assert(str_contains($service, "attempt_count < 3"), 'Failed deliveries must retry with a finite attempt limit.');
mailing_assert(str_contains($service, 'function mailing_schedule_campaign') && str_contains($service, 'function mailing_send_due'), 'Approved campaigns must support scheduled background delivery.');
mailing_assert(str_contains($generateAction, 'mailing_generate_image_for_campaign'), 'One creation action must run OpenAI copy and Nano Banana image generation.');
mailing_assert(str_contains($scheduleAction, 'mailing_schedule_campaign'), 'The schedule action must use the validated scheduling service.');
mailing_assert(str_contains($cronApi, 'mailing_send_due(3, 100)'), 'The authenticated publisher must process due campaigns in bounded batches.');
mailing_assert(str_contains($workflow, '*/5 * * * *'), 'The backup campaign publisher must run every five minutes.');
mailing_assert(str_contains($e2eApi, "'system_test'") && str_contains($e2eApi, 'mailing_generate_image_for_campaign'), 'The controlled production test must be isolated from patient audiences and validate image generation.');
mailing_assert(
    str_contains($auth, 'function require_marketing_access') && str_contains($sidebar, 'auth_can_use_marketing()'),
    'Marketing visibility and server authorization must share one role policy.'
);
mailing_assert(
    str_contains($page, 'require_marketing_access();') && str_contains($page, 'data-mailing-busy'),
    'The mailing workspace must enforce marketing access and show blocking progress feedback.'
);
mailing_assert(str_contains($page, '>Create complete AI campaign</button>'), 'The primary creation action must communicate the complete AI workflow.');
mailing_assert(str_contains($page, 'id="mailing-body-editor"') && !str_contains($page, 'name="body_html"'), 'Operators must edit plain-language copy instead of raw HTML.');
mailing_assert(str_contains($page, 'app/actions/mailing_schedule.php'), 'The review workspace must expose scheduled delivery.');
mailing_assert(str_contains($page, "['Leads', \$selected['lead_count']"), 'Campaign reporting must show attributed leads.');
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
