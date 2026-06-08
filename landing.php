<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: landing.php
 *
 * ROUTER ONLY — this file does nothing except:
 *   1. Bootstrap dependencies
 *   2. Resolve the slug
 *   3. Normalize to the clean canonical URL on GET requests
 *   4. Load the page record
 *   5. Hand off to the landing engine
 */

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/core/db.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/core/mailer.php';
require_once __DIR__ . '/app/leads/lead_meta.php';
require_once __DIR__ . '/app/leads/lead_service.php';
require_once __DIR__ . '/app/landing_pages/bootstrap.php';
require_once __DIR__ . '/app/landing_pages/engine/context.php';
require_once __DIR__ . '/app/landing_pages/engine/lead-handler.php';
require_once __DIR__ . '/app/landing_pages/engine/tracking.php';
require_once __DIR__ . '/app/landing_pages/engine/renderer.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '') {
    http_response_code(404);
    exit('Page not found.');
}

if (request_method() === 'GET') {
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $isLegacyQueryUrl = str_contains($requestUri, '/landing.php?') || str_ends_with($requestUri, '/landing.php');

    if ($isLegacyQueryUrl) {
        $params = $_GET;
        unset($params['slug']);

        $target = rtrim(APP_URL, '/') . '/l/' . rawurlencode($slug);
        if (!empty($params)) {
            $target .= '?' . http_build_query($params);
        }

        header('Location: ' . $target, true, 301);
        exit;
    }
}

$pageRow = db_one(
    'SELECT * FROM landing_pages WHERE slug = :slug AND is_active = 1 LIMIT 1',
    ['slug' => $slug]
);

$registry = landing_pages_registry();
$mapEntry = $registry['map'][$slug] ?? null;

if (!$pageRow && !$mapEntry) {
    http_response_code(404);
    exit('Landing page not found.');
}

$ctx = lp_build_full_context($slug, $pageRow ?? [], $registry);
$formResult = lp_handle_post($ctx);
lp_render($ctx, $formResult);
