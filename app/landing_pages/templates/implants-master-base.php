<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

$runtime = [
    'page' => $page ?? [],
    'pageSlug' => $pageSlug ?? ((is_array($page ?? null) ? (string) ($page['slug'] ?? '') : '')),
    'heroImageUrl' => $heroImageUrl ?? '',
];

$landingContext = $landingContext ?? ($ctx ?? landing_pages_build_context(
    (string) ($runtime['pageSlug'] ?? ''),
    is_array($runtime['page']) ? $runtime['page'] : [],
    $runtime
));

$landingView = $landingView ?? landing_pages_build_implants_view($landingContext, $runtime);
$sections = $landingView['sections'] ?? [];
$sectionOrder = $landingView['section_order'] ?? [];
$head = $head ?? ($landingContext['head'] ?? []);
$logoUrl = $logoUrl ?? ($landingContext['logoUrl'] ?? base_url('assets/img/ES-Logo-Stack-500-x-150-px.png'));
$primaryCtaText = $primaryCtaText ?? ($landingContext['primaryCtaText'] ?? 'Reserve Your Implant Consultation');
$pageSlug = $pageSlug ?? ($landingContext['slug'] ?? '');
$modal = $modal ?? ($landingContext['modal'] ?? []);

$sectionMap = [
    'hero' => __DIR__ . '/../partials/sections/hero.php',
    'offer' => __DIR__ . '/../partials/sections/offer.php',
    'authority' => __DIR__ . '/../partials/sections/authority.php',
    'text_block' => __DIR__ . '/../partials/sections/text-block.php',
    'longform' => __DIR__ . '/../partials/sections/longform.php',
    'gallery' => __DIR__ . '/../partials/sections/gallery.php',
    'before_after' => __DIR__ . '/../partials/sections/before-after.php',
    'location_convenience' => __DIR__ . '/../partials/sections/location-convenience.php',
    'reviews' => __DIR__ . '/../partials/sections/reviews.php',
    'faq' => __DIR__ . '/../partials/sections/faq.php',
    'final_cta' => __DIR__ . '/../partials/sections/final-cta.php',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require dirname(__DIR__) . '/partials/head-standard.php'; ?>
    <?php lp_tracking_head(); ?>
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
                    eliteSageSoft: '#eef0ec'
                },
                fontFamily: {
                    sansElite: ['Montserrat', 'system-ui', 'sans-serif'],
                    siteSerif: ['Playfair Display', 'Georgia', 'serif']
                }
            }
        }
    };
    </script>
</head>
<body class="bg-eliteStone font-sansElite text-eliteInk antialiased">
<div class="min-h-screen bg-eliteStone text-eliteInk antialiased font-sansElite">
    <header class="sticky top-0 z-40 border-b border-eliteBorder bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
            <a href="https://elitesmilesutah.com/" class="shrink-0">
                <img
                    src="<?= e((string) $logoUrl) ?>"
                    alt="Elite Smiles"
                    class="h-auto w-[150px] max-w-full sm:w-[180px]"
                >
            </a>

            <button
                type="button"
                data-open-quiz="1"
                data-track="cta_click"
                class="cta-pill inline-flex items-center justify-center bg-eliteRose px-4 py-2.5 text-xs font-semibold uppercase tracking-[0.08em] text-white transition hover:bg-eliteRoseDark sm:px-5 sm:text-sm"
            >
                <?= e((string) ($landingView['header_cta_text'] ?? 'Reserve Your Implant Consultation')) ?>
            </button>
        </div>
    </header>

    <?php if ($successMessage !== ''): ?>
        <div class="border-b border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <div class="mx-auto max-w-5xl"><?= e((string) $successMessage) ?></div>
        </div>
    <?php endif; ?>

    <main>
        <?php foreach ($sectionOrder as $sectionName): ?>
            <?php
            $section = $sections[$sectionName] ?? null;
            $partial = $sectionMap[$sectionName] ?? null;

            if (!is_array($section) || !is_string($partial) || !is_file($partial)) {
                continue;
            }

            if (array_key_exists('enabled', $section) && $section['enabled'] === false) {
                continue;
            }

            require $partial;
            ?>
        <?php endforeach; ?>
    </main>
</div>

<?php if (!empty($modal['steps'])): ?>
    <?php $totalSteps = count($quizSteps ?? []) + 1; ?>
    <?php require dirname(__DIR__) . '/partials/quiz-modal.php'; ?>
<?php endif; ?>
</body>
</html>
