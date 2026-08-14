<?php
declare(strict_types=1);

function organic_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

if (!defined('APP_URL')) {
    define('APP_URL', 'https://hi.elitesmilesutah.com/crm');
}
if (!function_exists('base_url')) {
    function base_url(string $path = ''): string
    {
        return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
    }
}

$root = dirname(__DIR__);
require_once $root . '/app/landing_pages/bootstrap.php';

$registry = landing_pages_registry();
$map = $registry['map'] ?? [];
$procedures = ['veneers', 'implants', 'all_on_x', 'smile_makeover', 'lip_repositioning'];
$cities = ['draper', 'lehi', 'south-jordan', 'highland', 'alpine', 'park-city', 'farmington', 'cedar-hills'];

organic_assert(count($map) === 201, 'The registry must preserve 200 historical routes plus the Google alias.');
$active = array_filter($map, static fn(array $row): bool => !empty($row['is_active']));
organic_assert(count($active) === 40, 'Exactly 40 canonical treatment/city pages must be active in config.');

foreach ($procedures as $procedure) {
    foreach ($cities as $city) {
        $slug = lp_organic_base_slug($procedure, $city);
        organic_assert(isset($map[$slug]), "Missing canonical route {$slug}.");
        organic_assert(!empty($map[$slug]['is_active']), "Canonical route {$slug} must be active.");
        organic_assert(empty($map[$slug]['angle']), "Canonical route {$slug} must not have an angle.");

        $treatment = $registry['organic_treatments'][$procedure] ?? [];
        $organicCity = $registry['organic_cities'][$city] ?? [];
        $ctx = [
            'registry' => $registry,
            'procedure_key' => $procedure,
            'city_key' => $city,
            'procedureLabel' => (string) ($treatment['label'] ?? ''),
            'cityLabel' => (string) ($organicCity['label'] ?? ''),
            'slug' => $slug,
            'pageRow' => [],
        ];
        $view = landing_pages_build_organic_view($ctx);
        organic_assert($view !== [], "Organic view failed for {$slug}.");
        organic_assert(count($view['sections']['longform']['items'] ?? []) >= 4, "{$slug} needs at least four educational sections.");
        organic_assert(count($view['sections']['faq']['items'] ?? []) >= 5, "{$slug} needs at least five FAQs.");
        organic_assert(str_contains((string) ($view['head']['title'] ?? ''), (string) ($organicCity['label'] ?? '')), "{$slug} title must include its city.");
        organic_assert(($view['head']['canonical'] ?? '') === base_url('l/' . $slug), "{$slug} canonical must point to its base route.");
        organic_assert(count($view['head']['schema'] ?? []) >= 4, "{$slug} must include Dentist, Service, breadcrumb, and FAQ schema.");
    }
}

foreach ($map as $slug => $row) {
    if (!empty($row['angle'])) {
        organic_assert(empty($row['is_active']), "Historical alias {$slug} must not be independently active.");
        organic_assert(!empty($row['canonical_slug']), "Historical alias {$slug} must identify its canonical route.");
    }
}

$routerSource = file_get_contents($root . '/landing.php') ?: '';
organic_assert(str_contains($routerSource, "header('Location: ' . \$target, true, 301)"), 'Historical aliases must use permanent redirects.');
organic_assert(str_contains($routerSource, "(int) (\$pageRow['is_active'] ?? 0) !== 1"), 'Inactive DB pages must fail closed.');
$templateSource = file_get_contents($root . '/app/landing_pages/templates/organic-master-base.php') ?: '';
organic_assert(str_contains($templateSource, "trackEvent('lead_success'"), 'Organic pages must track confirmed lead creation.');
organic_assert(str_contains($templateSource, 'window.location.href'), 'Successful submissions must redirect to the deduplicated conversion view.');
organic_assert(str_contains($templateSource, 'window.eliteLandingTracking'), 'Organic pages must configure first-party event tracking.');

$eventSource = file_get_contents($root . '/app/api/landing_event.php') ?: '';
organic_assert(str_contains($eventSource, 'require_csrf()'), 'The first-party event endpoint must require CSRF protection.');
organic_assert(str_contains($eventSource, 'landing_page_events'), 'The first-party event endpoint must persist funnel events.');

$adminSource = file_get_contents($root . '/landing_pages.php') ?: '';
organic_assert(str_contains($adminSource, '$pageConversion'), 'The organic-page workspace must report visitor-to-lead conversion.');
organic_assert(str_contains($adminSource, 'Publish organic page set'), 'The workspace must expose the canonical publishing action.');

echo "Organic landing page tests passed.\n";
