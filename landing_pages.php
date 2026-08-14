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

require_auth();

if (!auth_has_role('admin')) {
    http_response_code(403);
    exit('Access denied.');
}

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
            db_execute("UPDATE landing_pages SET is_active = 0, updated_at = NOW() WHERE angle <> '' AND angle IS NOT NULL");
            db_execute(
                "UPDATE landing_pages SET is_active = 1, updated_at = NOW()
                 WHERE (angle = '' OR angle IS NULL)
                   AND procedure_type IN ('veneers','implants','all_on_x','smile_makeover','lip_repositioning')
                   AND city IN ('draper','lehi','south-jordan','highland','alpine','park-city','farmington','cedar-hills')"
            );
            echo json_encode(['ok' => true, 'message' => 'Published 40 canonical organic pages and retired historical angle aliases.']);
            exit;
        }

        // Bulk activate/deactivate by procedure
        if ($action === 'bulk_procedure') {
            $proc   = (string) post('procedure');
            $status = (int) post('status');
            db_execute("UPDATE landing_pages SET is_active = :s, updated_at = NOW() WHERE procedure_type = :p AND (angle = '' OR angle IS NULL)", ['s' => $status, 'p' => $proc]);
            echo json_encode(['ok' => true]);
            exit;
        }

        // Bulk activate/deactivate by city
        if ($action === 'bulk_city') {
            $city   = (string) post('city');
            $status = (int) post('status');
            db_execute("UPDATE landing_pages SET is_active = :s, updated_at = NOW() WHERE city = :c AND (angle = '' OR angle IS NULL)", ['s' => $status, 'c' => $city]);
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
        echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// ── Load all pages into a matrix ──────────────────────────────────────────────
$allPages = db_all("SELECT id, slug, procedure_type, city, angle, is_active, hero_title, hero_image, updated_at FROM landing_pages WHERE angle = '' OR angle IS NULL ORDER BY procedure_type, city");

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
.cell-active   { background: #d1fae5; border-color: #6ee7b7; }
.cell-inactive { background: #f3f4f6; border-color: #e5e7eb; }
.dot-active    { background: #10b981; }
.dot-inactive  { background: #d1d5db; }
.panel-open    { transform: translateX(0); }
.panel-closed  { transform: translateX(100%); }
</style>
</head>
<body class="bg-gray-50 text-eliteInk antialiased">
<?php require __DIR__ . '/app/partials/crm_sidebar.php'; ?>
<div class="lg:pl-72">

<!-- ── HEADER ── -->
<header class="sticky top-0 z-40 border-b border-eliteBorder bg-white shadow-sm">
    <div class="mx-auto flex max-w-screen-xl items-center justify-between px-4 py-3">
        <div class="flex items-center gap-4">
            <img src="<?= e($logoUrl) ?>" alt="Elite Smiles" class="h-8 w-auto">
            <div>
                <div class="text-sm font-semibold text-gray-700">Organic Landing Pages</div>
                <div class="text-xs text-gray-500">One authoritative treatment page per city</div>
            </div>
        </div>
        <div class="flex items-center gap-6">
            <!-- Stats -->
            <div class="hidden items-center gap-6 sm:flex">
                <div class="text-center">
                    <div class="text-lg font-bold text-eliteInk"><?= $stats['active'] ?></div>
                    <div class="text-xs text-gray-500">Live pages</div>
                </div>
                <div class="text-center">
                    <div class="text-lg font-bold text-eliteInk"><?= $stats['total'] ?></div>
                    <div class="text-xs text-gray-500">Total pages</div>
                </div>
                <div class="text-center">
                    <div class="text-lg font-bold text-eliteRose"><?= $leadsThisMonth ?></div>
                    <div class="text-xs text-gray-500">Leads this month</div>
                </div>
                <div class="text-center">
                    <div class="text-lg font-bold text-blue-700"><?= $views30d ?></div>
                    <div class="text-xs text-gray-500">Views (30 days)</div>
                </div>
            </div>
            <a href="<?= e(base_url('dashboard.php')) ?>"
                class="rounded-full border border-eliteBorder bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                ← Dashboard
            </a>
        </div>
    </div>
</header>

<!-- ── PROCEDURE TABS ── -->
<div class="border-b border-eliteBorder bg-white">
    <div class="mx-auto max-w-screen-xl px-4">
        <div class="flex gap-1 overflow-x-auto">
            <?php foreach ($procedures as $procKey => $procLabel): ?>
            <?php
                $procPages  = $matrix[$procKey] ?? [];
                $procActive = 0;
                $procTotal  = 0;
                foreach ($procPages as $cityPages) {
                    foreach ($cityPages as $p) {
                        $procTotal++;
                        if ((int)($p['is_active'] ?? 0) === 1) $procActive++;
                    }
                }
            ?>
            <a href="?proc=<?= e($procKey) ?>"
                class="flex items-center gap-2 whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium transition
                    <?= $activeProcedure === $procKey
                        ? 'border-eliteRose text-eliteRose'
                        : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                <?= e($procLabel) ?>
                <span class="rounded-full px-2 py-0.5 text-xs font-semibold
                    <?= $activeProcedure === $procKey ? 'bg-eliteRose text-white' : 'bg-gray-100 text-gray-600' ?>">
                    <?= $procActive ?>/<?= $procTotal ?>
                </span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ── BULK ACTIONS BAR ── -->
<div class="border-b border-eliteBorder bg-eliteStone px-4 py-2">
    <div class="mx-auto flex max-w-screen-xl flex-wrap items-center gap-3">
        <button onclick="publishOrganicSet(this)"
            class="min-h-[44px] rounded-full bg-eliteRose px-4 py-2 text-xs font-semibold text-white hover:bg-eliteRoseDark">
            Publish organic page set
        </button>
        <span class="text-gray-300">|</span>
        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Bulk actions for <?= e($procedures[$activeProcedure]) ?>:</span>
        <button onclick="bulkProcedure('<?= e($activeProcedure) ?>', 1)"
            class="rounded-full bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
            Activate all
        </button>
        <button onclick="bulkProcedure('<?= e($activeProcedure) ?>', 0)"
            class="rounded-full border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50">
            Deactivate all
        </button>
        <span class="text-gray-300">|</span>
        <?php foreach ($cities as $cityKey => $cityLabel): ?>
        <button onclick="bulkCity('<?= e($cityKey) ?>', 1, this)"
            class="rounded-full bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-100 border border-blue-200">
            + <?= e($cityLabel) ?>
        </button>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── MATRIX GRID ── -->
<div class="mx-auto max-w-screen-xl px-4 py-6">
    <div class="mb-5 rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm leading-6 text-blue-950">
        <strong>Organic publishing model:</strong> education, candidacy, process, financing, and transformation content are consolidated into each city page. Historical angle URLs redirect here so they do not compete in Google Search.
    </div>
    <div class="overflow-x-auto rounded-2xl border border-eliteBorder bg-white shadow-sm">
        <table class="w-full min-w-[560px] border-collapse text-sm">
            <!-- Column headers (angles) -->
            <thead>
                <tr class="border-b border-eliteBorder bg-eliteStone">
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 w-32">City</th>
                    <?php foreach ($angles as $angleKey => $angleLabel): ?>
                    <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <div><?= e($angleLabel) ?></div>
                        <div class="mt-1 flex justify-center gap-1">
                            <button onclick="bulkAngle('<?= e($activeProcedure) ?>', '<?= e($angleKey) ?>', 1)"
                                class="rounded px-1.5 py-0.5 text-[10px] font-medium bg-emerald-50 text-emerald-700 hover:bg-emerald-100">on</button>
                            <button onclick="bulkAngle('<?= e($activeProcedure) ?>', '<?= e($angleKey) ?>', 0)"
                                class="rounded px-1.5 py-0.5 text-[10px] font-medium bg-gray-100 text-gray-500 hover:bg-gray-200">off</button>
                        </div>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cities as $cityKey => $cityLabel): ?>
                <tr class="border-b border-eliteBorder hover:bg-gray-50/50">
                    <!-- City label + bulk toggle -->
                    <td class="px-4 py-3">
                        <div class="font-medium text-eliteInk"><?= e($cityLabel) ?></div>
                        <div class="mt-1 flex gap-1">
                            <button onclick="bulkCity('<?= e($cityKey) ?>', 1, this)"
                                class="rounded px-1.5 py-0.5 text-[10px] font-medium bg-emerald-50 text-emerald-700 hover:bg-emerald-100">all on</button>
                            <button onclick="bulkCity('<?= e($cityKey) ?>', 0, this)"
                                class="rounded px-1.5 py-0.5 text-[10px] font-medium bg-gray-100 text-gray-500 hover:bg-gray-200">all off</button>
                        </div>
                    </td>

                    <!-- Angle cells -->
                    <?php foreach ($angles as $angleKey => $angleLabel): ?>
                    <?php
                        $page     = $matrix[$activeProcedure][$cityKey][$angleKey] ?? null;
                        $isActive = $page ? (int)($page['is_active'] ?? 0) === 1 : false;
                        $pageId   = $page ? (int)$page['id'] : 0;
                        $pageSlug = $page ? (string) ($page['slug'] ?? '') : '';
                        $pageMetrics = $performance[$pageSlug] ?? [];
                        $pageViews = (int) ($pageMetrics['views'] ?? 0);
                        $pageActions = (int) ($pageMetrics['actions'] ?? 0);
                        $pageLeads = (int) ($pageMetrics['leads'] ?? 0);
                        $pageBooked = (int) ($pageMetrics['booked'] ?? 0);
                        $pageConversion = $pageViews > 0 ? round(($pageLeads / $pageViews) * 100, 1) : 0.0;
                    ?>
                    <td class="px-3 py-3 text-center">
                        <?php if ($page): ?>
                        <div class="group relative inline-flex flex-col items-center gap-1.5 rounded-xl border p-2 transition cursor-pointer
                            <?= $isActive ? 'cell-active' : 'cell-inactive' ?>"
                            style="min-width: 120px;"
                            id="cell-<?= $pageId ?>">

                            <!-- Status dot + toggle -->
                            <button onclick="togglePage(<?= $pageId ?>, this)"
                                class="flex min-h-[44px] items-center gap-1.5 px-2 text-xs font-medium"
                                title="Click to toggle">
                                <span class="h-2.5 w-2.5 rounded-full dot flex-shrink-0 <?= $isActive ? 'dot-active' : 'dot-inactive' ?>"></span>
                                <span class="status-label"><?= $isActive ? 'Live' : 'Off' ?></span>
                            </button>

                            <!-- Edit button -->
                            <button onclick="openPanel(<?= $pageId ?>)"
                                class="min-h-[44px] rounded-lg bg-white/80 px-3 py-2 text-[11px] font-medium text-gray-600 hover:bg-white border border-gray-200 transition">
                                Edit
                            </button>

                            <!-- Preview link -->
                            <a href="<?= e(rtrim((string) APP_URL, '/') . '/l/' . rawurlencode((string)($page['slug'] ?? ''))) ?>"
                                target="_blank"
                                class="text-[10px] text-blue-600 hover:underline truncate max-w-[110px]"
                                title="<?= e((string)($page['slug'] ?? '')) ?>">
                                Preview ↗
                            </a>
                            <div class="mt-1 grid w-full grid-cols-5 gap-1 border-t border-black/10 pt-2 text-[10px] text-gray-600" aria-label="Last 30 days performance">
                                <span title="Page views"><strong class="block text-gray-900"><?= $pageViews ?></strong>views</span>
                                <span title="CTA actions"><strong class="block text-gray-900"><?= $pageActions ?></strong>CTA</span>
                                <span title="Leads"><strong class="block text-gray-900"><?= $pageLeads ?></strong>leads</span>
                                <span title="Consultations booked"><strong class="block text-gray-900"><?= $pageBooked ?></strong>booked</span>
                                <span title="Visitor-to-lead conversion"><strong class="block text-gray-900"><?= number_format($pageConversion, 1) ?>%</strong>CVR</span>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="inline-flex items-center justify-center rounded-xl border border-dashed border-gray-200 p-3 text-xs text-gray-300" style="min-width:120px; min-height: 72px;">
                            No page
                        </div>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Legend -->
    <div class="mt-4 flex items-center gap-6 text-xs text-gray-500">
        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full dot-active inline-block"></span> Live — visible to visitors</span>
        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full dot-inactive inline-block"></span> Off — not visible</span>
        <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded border border-dashed border-gray-300"></span> No page record</span>
    </div>
</div>

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
    if (!(await window.crmConfirm('Publish all 40 canonical organic pages and deactivate the historical angle versions?'))) return;
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
