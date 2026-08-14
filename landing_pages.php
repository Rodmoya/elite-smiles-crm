<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: landing_pages.php
 *
 * Organic local SEO landing pages admin panel.
 * Matrix view: one authoritative page per procedure and city.
 * Slide-out detail panel for editing individual pages.
 * Bulk activate/deactivate by procedure or city.
 */

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/core/db.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/landing_pages/bootstrap.php';

require_marketing_access();

// ── Config ────────────────────────────────────────────────────────────────────
$procedures = [
    'veneers'           => 'Veneers',
    'implants'          => 'Implants',
    'all_on_x'          => 'All-on-X',
    'smile_makeover'    => 'Smile Makeover',
    'lip_repositioning' => 'Lip Repositioning',
];

$cities = [
    'draper'       => 'Draper',
    'lehi'         => 'Lehi',
    'south-jordan' => 'South Jordan',
    'highland'     => 'Highland',
    'alpine'       => 'Alpine',
    'park-city'    => 'Park City',
    'farmington'   => 'Farmington',
    'cedar-hills'  => 'Cedar Hills',
];

$angles = ['' => 'Organic page'];

$landingRegistry = landing_pages_registry();
$canonicalPageDefinitions = [];
foreach (($landingRegistry['map'] ?? []) as $canonicalSlug => $definition) {
    if (!is_array($definition) || empty($definition['is_active']) || !empty($definition['angle'])) {
        continue;
    }
    $canonicalPageDefinitions[(string) $canonicalSlug] = $definition;
}

// ── AJAX / POST handlers ───────────────────────────────────────────────────────
if (is_post()) {
    $action = (string) post('action');
    header('Content-Type: application/json; charset=UTF-8');

    try {
        require_csrf();

        // Toggle single page active/inactive
        if ($action === 'toggle') {
            $id = (int) post('id');
            $row = db_one('SELECT id, is_active FROM landing_pages WHERE id = :id LIMIT 1', ['id' => $id]);
            if (!$row) throw new RuntimeException('Page not found.');
            $new = (int)$row['is_active'] === 1 ? 0 : 1;
            db_execute('UPDATE landing_pages SET is_active = :s, updated_at = NOW() WHERE id = :id', ['s' => $new, 'id' => $id]);
            echo json_encode(['ok' => true, 'is_active' => $new]);
            exit;
        }

        if ($action === 'publish_organic_set') {
            db_begin();
            db_query('UPDATE landing_pages SET is_active = 0, updated_at = NOW() WHERE is_active <> 0');
            foreach ($canonicalPageDefinitions as $canonicalSlug => $definition) {
                db_query(
                    "INSERT INTO landing_pages
                        (slug, procedure_type, city, angle, layout_variant, question_set, traffic_source_default, is_active)
                     VALUES
                        (:slug, :procedure_type, :city, '', 'organic', :question_set, 'organic', 1)
                     ON DUPLICATE KEY UPDATE
                        procedure_type = VALUES(procedure_type),
                        city = VALUES(city),
                        angle = '',
                        layout_variant = 'organic',
                        question_set = VALUES(question_set),
                        traffic_source_default = 'organic',
                        is_active = 1,
                        updated_at = NOW()",
                    [
                        'slug' => $canonicalSlug,
                        'procedure_type' => (string) ($definition['procedure'] ?? ''),
                        'city' => (string) ($definition['city'] ?? ''),
                        'question_set' => (string) ($definition['question_set'] ?? 'organic-consultation.php'),
                    ]
                );
            }
            db_commit();
            echo json_encode(['ok' => true, 'message' => 'Published exactly ' . count($canonicalPageDefinitions) . ' canonical organic pages and retired all legacy variants.']);
            exit;
        }

        // Bulk activate/deactivate by procedure
        if ($action === 'bulk_procedure') {
            $proc   = (string) post('procedure');
            $status = (int) post('status');
            foreach ($canonicalPageDefinitions as $canonicalSlug => $definition) {
                if ((string) ($definition['procedure'] ?? '') === $proc) {
                    db_execute('UPDATE landing_pages SET is_active = :s, updated_at = NOW() WHERE slug = :slug', ['s' => $status, 'slug' => $canonicalSlug]);
                }
            }
            echo json_encode(['ok' => true]);
            exit;
        }

        // Bulk activate/deactivate by city
        if ($action === 'bulk_city') {
            $city   = (string) post('city');
            $status = (int) post('status');
            foreach ($canonicalPageDefinitions as $canonicalSlug => $definition) {
                if ((string) ($definition['city'] ?? '') === $city) {
                    db_execute('UPDATE landing_pages SET is_active = :s, updated_at = NOW() WHERE slug = :slug', ['s' => $status, 'slug' => $canonicalSlug]);
                }
            }
            echo json_encode(['ok' => true]);
            exit;
        }

        // Bulk activate/deactivate by angle
        if ($action === 'bulk_angle') {
            $angle  = (string) post('angle');
            $proc   = (string) post('procedure');
            $status = (int) post('status');
            db_execute('UPDATE landing_pages SET is_active = :s, updated_at = NOW() WHERE procedure_type = :p AND angle = :a', ['s' => $status, 'p' => $proc, 'a' => $angle]);
            echo json_encode(['ok' => true]);
            exit;
        }

        // Save page detail (hero image, title, cta override)
        if ($action === 'save') {
            $id = (int) post('id');
            $row = db_one('SELECT id FROM landing_pages WHERE id = :id LIMIT 1', ['id' => $id]);
            if (!$row) throw new RuntimeException('Page not found.');

            db_execute(
                'UPDATE landing_pages SET
                    hero_title       = :hero_title,
                    hero_subtitle    = :hero_subtitle,
                    hero_image       = :hero_image,
                    primary_cta_text = :primary_cta_text,
                    offer_badge      = :offer_badge,
                    offer_title      = :offer_title,
                    offer_description= :offer_description,
                    is_active        = :is_active,
                    updated_at       = NOW()
                WHERE id = :id LIMIT 1',
                [
                    'id'               => $id,
                    'hero_title'       => trim((string) post('hero_title')),
                    'hero_subtitle'    => trim((string) post('hero_subtitle')),
                    'hero_image'       => trim((string) post('hero_image')),
                    'primary_cta_text' => trim((string) post('primary_cta_text')),
                    'offer_badge'      => trim((string) post('offer_badge')),
                    'offer_title'      => trim((string) post('offer_title')),
                    'offer_description'=> trim((string) post('offer_description')),
                    'is_active'        => (int) post('is_active'),
                ]
            );
            echo json_encode(['ok' => true, 'message' => 'Saved successfully.']);
            exit;
        }

        // Load single page data for slide-out panel
        if ($action === 'load') {
            $id  = (int) post('id');
            $row = db_one('SELECT * FROM landing_pages WHERE id = :id LIMIT 1', ['id' => $id]);
            if (!$row) throw new RuntimeException('Page not found.');
            echo json_encode(['ok' => true, 'page' => $row]);
            exit;
        }

        throw new RuntimeException('Unknown action.');

    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db_rollBack();
        }
        echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// ── Load all pages into a matrix ──────────────────────────────────────────────
$allPages = db_all("SELECT id, slug, procedure_type, city, angle, is_active, hero_title, hero_image, updated_at FROM landing_pages ORDER BY procedure_type, city");
$allPages = array_values(array_filter(
    $allPages,
    static fn(array $page): bool => isset($canonicalPageDefinitions[(string) ($page['slug'] ?? '')])
));

// Index: $matrix[procedure][city][angle] = page row
$matrix = [];
$stats  = ['total' => 0, 'active' => 0];

foreach ($allPages as $page) {
    $proc  = (string) ($page['procedure_type'] ?? 'general');
    $city  = (string) ($page['city']           ?? '');
    $angle = (string) ($page['angle']          ?? '');
    $matrix[$proc][$city][$angle] = $page;
    $stats['total']++;
    if ((int)$page['is_active'] === 1) $stats['active']++;
}

// Thirty-day organic funnel performance, keyed by canonical landing-page slug.
$performance = [];
$views30d = 0;
try {
    $eventRows = db_all(
        "SELECT landing_page,
                SUM(event_name = 'page_view') AS page_views,
                SUM(event_name IN ('header_cta_click','cta_click','directions_click','wizard_start','form_submit_click','form_submit_attempt')) AS cta_actions
         FROM landing_page_events
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY landing_page"
    );
    foreach ($eventRows as $eventRow) {
        $slug = (string) ($eventRow['landing_page'] ?? '');
        $performance[$slug]['views'] = (int) ($eventRow['page_views'] ?? 0);
        $performance[$slug]['actions'] = (int) ($eventRow['cta_actions'] ?? 0);
        $views30d += (int) ($eventRow['page_views'] ?? 0);
    }
} catch (Throwable $e) {
    // The event table is created lazily on the first tracked visit after deployment.
}

try {
    $leadRows = db_all(
        "SELECT landing_page,
                COUNT(*) AS leads,
                SUM(status IN ('consultation_booked','consult_completed','treatment_accepted','treatment_completed')
                    OR consultation_date IS NOT NULL) AS booked
         FROM leads
         WHERE landing_page <> ''
           AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY landing_page"
    );
    foreach ($leadRows as $leadRow) {
        $slug = (string) ($leadRow['landing_page'] ?? '');
        $performance[$slug]['leads'] = (int) ($leadRow['leads'] ?? 0);
        $performance[$slug]['booked'] = (int) ($leadRow['booked'] ?? 0);
    }
} catch (Throwable $e) {
    // Older installations may not yet have consultation_date; page management remains available.
}

// Count leads this month
$leadsThisMonth = (int) db_value(
    "SELECT COUNT(*) FROM leads WHERE landing_page <> '' AND created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')"
) ?: 0;

$user      = auth_user();
$firstName = $user['first_name'] ?? 'User';
$logoUrl   = base_url('assets/img/ES-Logo-Stack-500-x-150-px.png');
$csrfToken = csrf_token();
$activeProcedure = trim((string) ($_GET['proc'] ?? 'veneers'));
if (!isset($procedures[$activeProcedure])) $activeProcedure = 'veneers';
$currentPage = 'landing_pages';
$pageTitle = 'Organic Landing Pages';
$logoutAction = base_url('landing_pages.php');

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Organic Landing Pages — Elite Smiles CRM</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
    theme: {
        extend: {
            colors: {
                eliteRose: '#bc3f60',
                eliteRoseDark: '#a93654',
                eliteInk: '#171717',
                eliteBody: '#333333',
                eliteBorder: '#e7e7e2',
                eliteStone: '#f4f4f1',
            }
        }
    }
};
</script>
<style>
body { font-family: system-ui, -apple-system, sans-serif; }
.cell-active   { border-color: #d8e8df; }
.cell-inactive { border-color: #e5e7eb; background: #fafafa; }
.cell-active .status-toggle { background: #ecfdf5; color: #047857; }
.cell-inactive .status-toggle { background: #f3f4f6; color: #6b7280; }
.dot-active    { background: #10b981; }
.dot-inactive  { background: #d1d5db; }
.panel-open    { transform: translateX(0); }
.panel-closed  { transform: translateX(100%); }
</style>
</head>
<body class="bg-gray-50 text-eliteInk antialiased">
<?php require __DIR__ . '/app/partials/crm_sidebar.php'; ?>
<div class="lg:pl-72">

<header class="border-b border-eliteBorder bg-white">
    <div class="mx-auto flex max-w-screen-2xl flex-col gap-4 px-4 py-5 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-eliteRose">Local SEO workspace</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-eliteInk sm:text-3xl">Organic Landing Pages</h1>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">Manage one authoritative treatment page for each target city and follow its path from visit to consultation.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <button onclick="publishOrganicSet(this)"
                class="min-h-[44px] rounded-xl bg-eliteRose px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-eliteRoseDark">
                Publish canonical set
            </button>
            <a href="<?= e(base_url('dashboard.php')) ?>"
                class="inline-flex min-h-[44px] items-center rounded-xl border border-eliteBorder bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                Dashboard
            </a>
        </div>
    </div>
</header>

<main class="mx-auto max-w-screen-2xl px-4 py-6 sm:px-6 lg:px-8">
    <section aria-label="Landing page performance" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-2xl border border-eliteBorder bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Pages live</p>
            <div class="mt-2 flex items-end gap-2"><strong class="text-3xl text-eliteInk"><?= $stats['active'] ?></strong><span class="pb-1 text-sm text-gray-400">of <?= $stats['total'] ?></span></div>
        </div>
        <div class="rounded-2xl border border-eliteBorder bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Views</p>
            <div class="mt-2 flex items-end gap-2"><strong class="text-3xl text-eliteInk"><?= $views30d ?></strong><span class="pb-1 text-sm text-gray-400">last 30 days</span></div>
        </div>
        <div class="rounded-2xl border border-eliteBorder bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Leads</p>
            <div class="mt-2 flex items-end gap-2"><strong class="text-3xl text-eliteRose"><?= $leadsThisMonth ?></strong><span class="pb-1 text-sm text-gray-400">this month</span></div>
        </div>
        <div class="rounded-2xl border border-eliteBorder bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Publishing model</p>
            <p class="mt-2 text-sm font-semibold leading-5 text-eliteInk">One canonical page per treatment and city</p>
        </div>
    </section>

    <nav aria-label="Treatments" class="mt-6 overflow-x-auto rounded-2xl border border-eliteBorder bg-white p-1.5 shadow-sm">
        <div class="flex min-w-max gap-1">
            <?php foreach ($procedures as $procKey => $procLabel): ?>
            <?php
                $procPages = $matrix[$procKey] ?? [];
                $procActive = 0;
                $procTotal = 0;
                foreach ($procPages as $cityPages) {
                    foreach ($cityPages as $procedurePage) {
                        $procTotal++;
                        if ((int) ($procedurePage['is_active'] ?? 0) === 1) $procActive++;
                    }
                }
            ?>
            <a href="?proc=<?= e($procKey) ?>"
                class="flex min-h-[44px] items-center gap-2 whitespace-nowrap rounded-xl px-4 py-2 text-sm font-semibold transition
                    <?= $activeProcedure === $procKey ? 'bg-[#121b32] text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50 hover:text-eliteInk' ?>">
                <?= e($procLabel) ?>
                <span class="rounded-full px-2 py-0.5 text-xs <?= $activeProcedure === $procKey ? 'bg-white/15 text-white' : 'bg-gray-100 text-gray-500' ?>"><?= $procActive ?>/<?= $procTotal ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </nav>

    <section class="mt-6 rounded-2xl border border-eliteBorder bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">Selected treatment</p>
                <h2 class="mt-1 text-xl font-bold text-eliteInk"><?= e($procedures[$activeProcedure]) ?> by city</h2>
                <p class="mt-1 text-sm text-gray-500">Legacy campaign URLs redirect to these pages, preventing duplicate pages from competing in Google.</p>
            </div>
            <details class="group rounded-xl border border-eliteBorder bg-gray-50 px-4 py-3">
                <summary class="cursor-pointer list-none text-sm font-semibold text-gray-700">Bulk publishing controls</summary>
                <div class="mt-3 flex flex-wrap gap-2 border-t border-eliteBorder pt-3">
                    <button onclick="bulkProcedure('<?= e($activeProcedure) ?>', 1)" class="min-h-[40px] rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700">Activate all 8</button>
                    <button onclick="bulkProcedure('<?= e($activeProcedure) ?>', 0)" class="min-h-[40px] rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50">Deactivate all 8</button>
                </div>
            </details>
        </div>
    </section>

    <section aria-label="<?= e($procedures[$activeProcedure]) ?> city pages" class="mt-4 grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
        <?php foreach ($cities as $cityKey => $cityLabel): ?>
        <?php
            $page = $matrix[$activeProcedure][$cityKey][''] ?? null;
            $isActive = $page ? (int) ($page['is_active'] ?? 0) === 1 : false;
            $pageId = $page ? (int) $page['id'] : 0;
            $pageSlug = $page ? (string) ($page['slug'] ?? '') : '';
            $pageMetrics = $performance[$pageSlug] ?? [];
            $pageViews = (int) ($pageMetrics['views'] ?? 0);
            $pageActions = (int) ($pageMetrics['actions'] ?? 0);
            $pageLeads = (int) ($pageMetrics['leads'] ?? 0);
            $pageBooked = (int) ($pageMetrics['booked'] ?? 0);
            $pageConversion = $pageViews > 0 ? round(($pageLeads / $pageViews) * 100, 1) : 0.0;
        ?>
        <?php if ($page): ?>
        <article id="cell-<?= $pageId ?>" class="cell-<?= $isActive ? 'active' : 'inactive' ?> rounded-2xl border bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">Utah city page</p>
                    <h3 class="mt-1 text-xl font-bold text-eliteInk"><?= e($cityLabel) ?></h3>
                    <p class="mt-1 truncate font-mono text-[11px] text-gray-400" title="<?= e($pageSlug) ?>"><?= e($pageSlug) ?></p>
                </div>
                <button onclick="togglePage(<?= $pageId ?>, this)" class="status-toggle flex min-h-[40px] shrink-0 items-center gap-2 rounded-full px-3 py-2 text-xs font-semibold" title="Toggle page visibility">
                    <span class="dot h-2.5 w-2.5 rounded-full <?= $isActive ? 'dot-active' : 'dot-inactive' ?>"></span>
                    <span class="status-label"><?= $isActive ? 'Live' : 'Off' ?></span>
                </button>
            </div>

            <div class="mt-5 grid grid-cols-5 gap-2 rounded-xl bg-gray-50 p-3 text-center" aria-label="Last 30 days performance">
                <span class="text-[10px] text-gray-500"><strong class="block text-base text-eliteInk"><?= htmlentities((string) $pageViews, ENT_QUOTES, 'UTF-8') ?></strong>Views</span>
                <span class="text-[10px] text-gray-500"><strong class="block text-base text-eliteInk"><?= htmlentities((string) $pageActions, ENT_QUOTES, 'UTF-8') ?></strong>CTA</span>
                <span class="text-[10px] text-gray-500"><strong class="block text-base text-eliteInk"><?= htmlentities((string) $pageLeads, ENT_QUOTES, 'UTF-8') ?></strong>Leads</span>
                <span class="text-[10px] text-gray-500"><strong class="block text-base text-eliteInk"><?= htmlentities((string) $pageBooked, ENT_QUOTES, 'UTF-8') ?></strong>Booked</span>
                <span class="text-[10px] text-gray-500"><strong class="block text-base text-eliteInk"><?= htmlentities(number_format($pageConversion, 1), ENT_QUOTES, 'UTF-8') ?>%</strong>CVR</span>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-2">
                <button onclick="openPanel(<?= $pageId ?>)" class="min-h-[44px] rounded-xl border border-eliteBorder bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">Edit page</button>
                <a href="<?= e(rtrim((string) APP_URL, '/') . '/l/' . rawurlencode($pageSlug)) ?>" target="_blank" class="inline-flex min-h-[44px] items-center justify-center rounded-xl bg-[#121b32] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#1c2948]">Preview page ↗</a>
            </div>
        </article>
        <?php else: ?>
        <article class="flex min-h-56 items-center justify-center rounded-2xl border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-400">
            <?= e($cityLabel) ?> page record is missing. Use “Publish canonical set” to restore it.
        </article>
        <?php endif; ?>
        <?php endforeach; ?>
    </section>
</main>

</div>

<!-- ── SLIDE-OUT DETAIL PANEL ── -->
<div id="overlay" class="fixed inset-0 z-40 bg-black/30 hidden" onclick="closePanel()"></div>

<div id="panel" class="fixed right-0 top-0 z-50 h-full w-full max-w-lg bg-white shadow-2xl transition-transform duration-300 panel-closed overflow-y-auto">
    <div class="flex items-center justify-between border-b border-eliteBorder px-5 py-4">
        <h2 class="text-base font-semibold text-eliteInk" id="panelTitle">Page Details</h2>
        <button onclick="closePanel()" class="rounded-full p-2 hover:bg-gray-100 text-gray-500 text-xl leading-none">&times;</button>
    </div>

    <div id="panelLoading" class="flex items-center justify-center py-16 text-sm text-gray-400">Loading...</div>

    <div id="panelContent" class="hidden px-5 py-5 space-y-5">

        <!-- Status toggle -->
        <div class="flex items-center justify-between rounded-xl bg-eliteStone p-4">
            <div>
                <div class="font-medium text-eliteInk">Page Status</div>
                <div id="panelSlug" class="text-xs text-gray-500 mt-0.5 font-mono"></div>
            </div>
            <label class="relative inline-flex cursor-pointer items-center">
                <input type="checkbox" id="panelIsActive" class="sr-only peer">
                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-eliteRose
                    after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                    after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all
                    peer-checked:after:translate-x-5"></div>
                <span class="ml-2 text-sm font-medium text-gray-700" id="panelStatusLabel">Inactive</span>
            </label>
        </div>

        <input type="hidden" id="panelId">

        <!-- Hero image -->
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">Hero Image URL</label>
            <input type="text" id="panelHeroImage" placeholder="assets/img/landings/your-image.jpg"
                class="w-full rounded-xl border border-eliteBorder px-4 py-2.5 text-sm focus:outline-none focus:border-eliteRose">
            <p class="mt-1 text-xs text-gray-400">Relative path from /crm/ or full https:// URL</p>
        </div>

        <!-- Hero title -->
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">Hero Title Override</label>
            <input type="text" id="panelHeroTitle" placeholder="Leave empty to use procedure default"
                class="w-full rounded-xl border border-eliteBorder px-4 py-2.5 text-sm focus:outline-none focus:border-eliteRose">
        </div>

        <!-- Hero subtitle -->
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">Hero Subtitle Override</label>
            <textarea id="panelHeroSubtitle" rows="2" placeholder="Leave empty to use procedure default"
                class="w-full rounded-xl border border-eliteBorder px-4 py-2.5 text-sm focus:outline-none focus:border-eliteRose resize-none"></textarea>
        </div>

        <!-- CTA text -->
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">CTA Button Text Override</label>
            <input type="text" id="panelCtaText" placeholder="Take Advantage of the $750 Offer"
                class="w-full rounded-xl border border-eliteBorder px-4 py-2.5 text-sm focus:outline-none focus:border-eliteRose">
        </div>

        <!-- Offer badge -->
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">Offer Badge</label>
            <input type="text" id="panelOfferBadge" placeholder="$750 VALUE"
                class="w-full rounded-xl border border-eliteBorder px-4 py-2.5 text-sm focus:outline-none focus:border-eliteRose">
        </div>

        <!-- Offer title -->
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">Offer Title</label>
            <input type="text" id="panelOfferTitle" placeholder="What the $750 Offer May Include"
                class="w-full rounded-xl border border-eliteBorder px-4 py-2.5 text-sm focus:outline-none focus:border-eliteRose">
        </div>

        <!-- Offer description -->
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">Offer Description</label>
            <textarea id="panelOfferDesc" rows="3" placeholder="Leave empty to use procedure default"
                class="w-full rounded-xl border border-eliteBorder px-4 py-2.5 text-sm focus:outline-none focus:border-eliteRose resize-none"></textarea>
        </div>

        <!-- Save + preview buttons -->
        <div class="flex gap-3 pt-2">
            <button onclick="savePage()"
                class="flex-1 rounded-full bg-eliteRose py-2.5 text-sm font-semibold text-white hover:bg-eliteRoseDark transition">
                Save Changes
            </button>
            <a id="panelPreviewLink" href="#" target="_blank"
                class="flex-1 rounded-full border border-eliteBorder bg-white py-2.5 text-center text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                Preview ↗
            </a>
        </div>

        <div id="panelMessage" class="hidden rounded-xl px-4 py-3 text-sm font-medium"></div>
    </div>
</div>

<!-- ── JAVASCRIPT ── -->
<div id="pageToast" role="status" aria-live="polite" class="pointer-events-none fixed left-1/2 top-1/2 z-[100] hidden max-w-sm -translate-x-1/2 -translate-y-1/2 rounded-2xl border border-eliteBorder bg-white px-5 py-4 text-center text-sm font-semibold text-eliteInk shadow-2xl"></div>
<script>
const CSRF = '<?= e($csrfToken) ?>';
const BASE = '<?= e(base_url('landing_pages.php')) ?>';
const LANDING_BASE = '<?= e(rtrim((string) APP_URL, '/') . '/l/') ?>';

async function api(payload) {
    try {
        const fd = new FormData();
        fd.append('_csrf_token', CSRF);
        for (const [k, v] of Object.entries(payload)) fd.append(k, v);
        const res = await fetch(BASE, { method: 'POST', body: fd, headers: { Accept: 'application/json' } });
        const data = await res.json().catch(() => ({ ok: false, message: 'The server returned an unreadable response.' }));
        if (!res.ok && data.ok !== false) return { ok: false, message: 'The request failed. Please try again.' };
        return data;
    } catch (error) {
        return { ok: false, message: 'Unable to reach the server. Check your connection and try again.' };
    }
}

function pageToast(message, isError = false) {
    const toast = document.getElementById('pageToast');
    toast.textContent = message || (isError ? 'The request failed.' : 'Done.');
    toast.classList.remove('hidden', 'border-red-200', 'text-red-800', 'border-emerald-200', 'text-emerald-800');
    toast.classList.add(isError ? 'border-red-200' : 'border-emerald-200', isError ? 'text-red-800' : 'text-emerald-800');
    window.clearTimeout(pageToast.timer);
    pageToast.timer = window.setTimeout(() => toast.classList.add('hidden'), 4200);
}

// Toggle single page
async function togglePage(id, btn) {
    btn.disabled = true;
    const data = await api({ action: 'toggle', id });
    if (!data.ok) { pageToast(data.message, true); btn.disabled = false; return; }

    const cell   = document.getElementById('cell-' + id);
    const dot    = btn.querySelector('.dot');
    const label  = btn.querySelector('.status-label');
    const active = data.is_active === 1;

    cell.className  = cell.className.replace(/cell-(active|inactive)/g, active ? 'cell-active' : 'cell-inactive');
    dot.className   = dot.className.replace(/dot-(active|inactive)/g, active ? 'dot-active' : 'dot-inactive');
    label.textContent = active ? 'Live' : 'Off';
    btn.disabled    = false;

    // Update panel if open
    if (document.getElementById('panelId').value == id) {
        document.getElementById('panelIsActive').checked = active;
        document.getElementById('panelStatusLabel').textContent = active ? 'Live' : 'Inactive';
    }
}

// Bulk procedure
async function bulkProcedure(proc, status) {
    if (!(await window.crmConfirm((status ? 'Activate' : 'Deactivate') + ' all pages for this procedure?'))) return;
    const data = await api({ action: 'bulk_procedure', procedure: proc, status });
    if (data.ok) location.reload();
    else pageToast(data.message, true);
}

async function publishOrganicSet(button) {
    if (!(await window.crmConfirm(
        'Publish all 40 canonical organic pages and deactivate the historical angle versions?',
        { title: 'Publish organic page set?', confirmLabel: 'Publish pages' }
    ))) return;
    button.disabled = true;
    const data = await api({ action: 'publish_organic_set' });
    if (data.ok) {
        pageToast(data.message || 'Organic page set published.');
        window.setTimeout(() => location.reload(), 900);
        return;
    }
    button.disabled = false;
    pageToast(data.message, true);
}

// Bulk city
async function bulkCity(city, status) {
    if (!(await window.crmConfirm((status ? 'Activate' : 'Deactivate') + ' all pages for this city?'))) return;
    const data = await api({ action: 'bulk_city', city, status });
    if (data.ok) location.reload();
    else pageToast(data.message, true);
}

// Bulk angle
async function bulkAngle(proc, angle, status) {
    if (!(await window.crmConfirm((status ? 'Activate' : 'Deactivate') + ' all ' + (angle || 'base') + ' pages?'))) return;
    const data = await api({ action: 'bulk_angle', procedure: proc, angle, status });
    if (data.ok) location.reload();
    else pageToast(data.message, true);
}

// Open slide-out panel
async function openPanel(id) {
    document.getElementById('overlay').classList.remove('hidden');
    document.getElementById('panel').classList.remove('panel-closed');
    document.getElementById('panel').classList.add('panel-open');
    document.getElementById('panelLoading').classList.remove('hidden');
    document.getElementById('panelContent').classList.add('hidden');
    document.getElementById('panelMessage').classList.add('hidden');

    const data = await api({ action: 'load', id });
    if (!data.ok) { pageToast(data.message, true); closePanel(); return; }

    const p = data.page;
    document.getElementById('panelId').value             = p.id;
    document.getElementById('panelTitle').textContent    = p.slug;
    document.getElementById('panelSlug').textContent     = p.slug;
    document.getElementById('panelIsActive').checked     = p.is_active == 1;
    document.getElementById('panelStatusLabel').textContent = p.is_active == 1 ? 'Live' : 'Inactive';
    document.getElementById('panelHeroImage').value      = p.hero_image    || '';
    document.getElementById('panelHeroTitle').value      = p.hero_title    || '';
    document.getElementById('panelHeroSubtitle').value   = p.hero_subtitle || '';
    document.getElementById('panelCtaText').value        = p.primary_cta_text  || '';
    document.getElementById('panelOfferBadge').value     = p.offer_badge   || '';
    document.getElementById('panelOfferTitle').value     = p.offer_title   || '';
    document.getElementById('panelOfferDesc').value      = p.offer_description || '';
    document.getElementById('panelPreviewLink').href     = LANDING_BASE + encodeURIComponent(p.slug);

    document.getElementById('panelLoading').classList.add('hidden');
    document.getElementById('panelContent').classList.remove('hidden');

    // Sync toggle change
    document.getElementById('panelIsActive').onchange = function() {
        document.getElementById('panelStatusLabel').textContent = this.checked ? 'Live' : 'Inactive';
    };
}

function closePanel() {
    document.getElementById('overlay').classList.add('hidden');
    document.getElementById('panel').classList.add('panel-closed');
    document.getElementById('panel').classList.remove('panel-open');
}

async function savePage() {
    const id = document.getElementById('panelId').value;
    const msg = document.getElementById('panelMessage');
    msg.classList.add('hidden');

    const data = await api({
        action:            'save',
        id:                id,
        hero_image:        document.getElementById('panelHeroImage').value,
        hero_title:        document.getElementById('panelHeroTitle').value,
        hero_subtitle:     document.getElementById('panelHeroSubtitle').value,
        primary_cta_text:  document.getElementById('panelCtaText').value,
        offer_badge:       document.getElementById('panelOfferBadge').value,
        offer_title:       document.getElementById('panelOfferTitle').value,
        offer_description: document.getElementById('panelOfferDesc').value,
        is_active:         document.getElementById('panelIsActive').checked ? 1 : 0,
    });

    msg.classList.remove('hidden', 'bg-green-50', 'text-green-700', 'bg-red-50', 'text-red-700');
    if (data.ok) {
        msg.classList.add('bg-green-50', 'text-green-700');
        msg.textContent = data.message || 'Saved.';
        // Update cell status on grid
        const isActive = document.getElementById('panelIsActive').checked;
        const cell = document.getElementById('cell-' + id);
        if (cell) {
            cell.className = cell.className.replace(/cell-(active|inactive)/g, isActive ? 'cell-active' : 'cell-inactive');
            const dot   = cell.querySelector('.dot');
            const label = cell.querySelector('.status-label');
            if (dot)   dot.className   = dot.className.replace(/dot-(active|inactive)/g, isActive ? 'dot-active' : 'dot-inactive');
            if (label) label.textContent = isActive ? 'Live' : 'Off';
        }
    } else {
        msg.classList.add('bg-red-50', 'text-red-700');
        msg.textContent = data.message || 'Error saving.';
    }
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closePanel(); });
</script>
<script src="<?= e(base_url('assets/js/crm-confirm-dialog.js?v=' . (string)(@filemtime(__DIR__ . '/assets/js/crm-confirm-dialog.js') ?: '1'))) ?>" defer></script>

</body>
</html>
