<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: crm/sitemap.php
 *
 * Dynamic XML sitemap for active landing pages.
 * Supports both DB-backed pages and file/config-backed landings from landing-map.php.
 */

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/core/db.php';

$baseClean = 'https://hi.elitesmilesutah.com/crm/l/';
$baseQuery = 'https://hi.elitesmilesutah.com/crm/landing.php?slug=';

/*
 * Use clean URLs because the live master you shared is already under /crm/l/{slug}
 */
$useCleanUrls = true;

/**
 * Keep a single ordered collection by slug.
 */
$urls = [];

/**
 * Priority map by procedure.
 */
$priorityMap = [
    'veneers'           => '0.8',
    'implants'          => '0.8',
    'all_on_x'          => '0.7',
    'smile_makeover'    => '0.7',
    'lip_repositioning' => '0.6',
];

/**
 * Add / update one sitemap entry.
 */
$addUrl = static function (string $slug, string $procedure = '', ?string $updatedAt = null) use (&$urls, $priorityMap): void {
    $slug = trim($slug);
    if ($slug === '') {
        return;
    }

    $lastmod = date('Y-m-d');
    if ($updatedAt !== null && trim($updatedAt) !== '') {
        $ts = strtotime($updatedAt);
        if ($ts !== false) {
            $lastmod = date('Y-m-d', $ts);
        }
    }

    $urls[$slug] = [
        'slug'      => $slug,
        'procedure' => $procedure,
        'lastmod'   => $lastmod,
        'priority'  => $priorityMap[$procedure] ?? '0.6',
    ];
};

/*
 * 1) Try DB-backed landing pages first.
 */
try {
    $pages = db_all(
        'SELECT slug, procedure_type, updated_at
         FROM landing_pages
         WHERE is_active = 1
         ORDER BY procedure_type, slug'
    );

    if (is_array($pages)) {
        foreach ($pages as $page) {
            $addUrl(
                (string) ($page['slug'] ?? ''),
                (string) ($page['procedure_type'] ?? ''),
                (string) ($page['updated_at'] ?? '')
            );
        }
    }
} catch (Throwable $e) {
    // Continue gracefully to file/config fallback.
}

/*
 * 2) Add active config-backed pages from landing-map.php.
 * This helps when slugs exist in the file system/config but are not yet persisted in the DB.
 */
$landingMapFile = __DIR__ . '/app/landing_pages/configs/landing-map.php';
if (is_file($landingMapFile)) {
    $map = require $landingMapFile;
    if (is_array($map)) {
        foreach ($map as $slug => $row) {
            if (!is_array($row)) {
                continue;
            }

            $isActive  = (bool) ($row['is_active'] ?? false);
            $procedure = (string) ($row['procedure'] ?? $row['procedure_type'] ?? '');

            if ($isActive) {
                $addUrl((string) $slug, $procedure, null);
            }
        }
    }
}

/*
 * Stable output order.
 */
ksort($urls, SORT_NATURAL | SORT_FLAG_CASE);

header('Content-Type: application/xml; charset=UTF-8');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($urls as $entry) {
    $slug     = (string) $entry['slug'];
    $lastmod  = (string) $entry['lastmod'];
    $priority = (string) $entry['priority'];

    $loc = $useCleanUrls
        ? $baseClean . rawurlencode($slug)
        : $baseQuery . rawurlencode($slug);

    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . "</loc>\n";
    echo '    <lastmod>' . htmlspecialchars($lastmod, ENT_XML1, 'UTF-8') . "</lastmod>\n";
    echo "    <changefreq>monthly</changefreq>\n";
    echo '    <priority>' . htmlspecialchars($priority, ENT_XML1, 'UTF-8') . "</priority>\n";
    echo "  </url>\n";
}

echo '</urlset>';
