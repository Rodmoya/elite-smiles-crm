<?php
declare(strict_types=1);

$landingContext = $landingContext ?? ($ctx ?? []);
$pageRow = $pageRow ?? ($landingContext['pageRow'] ?? []);
$sections = $sections ?? ($landingContext['sections'] ?? []);
$sectionOrder = $sectionOrder ?? ($landingContext['section_order'] ?? []);
$head = $head ?? ($landingContext['head'] ?? []);
$logoUrl = $logoUrl ?? ($landingContext['logoUrl'] ?? base_url('assets/img/ES-Logo-Stack-500-x-150-px.png'));
$primaryCtaText = $primaryCtaText ?? ($landingContext['primaryCtaText'] ?? 'Take Advantage of the $750 Offer');
$pageSlug = $pageSlug ?? ($landingContext['slug'] ?? '');
$procedureKey = $procedureKey ?? ($landingContext['procedure_key'] ?? '');
$modal = $modal ?? ($landingContext['modal'] ?? []);

if (!is_array($sections)) {
    $sections = [];
}

if (!is_array($sectionOrder) || $sectionOrder === []) {
    $sectionOrder = [
        'hero',
        'offer',
        'authority',
        'gallery',
        'before_after',
        'reviews',
        'longform',
        'location_convenience',
        'faq',
        'final_cta',
    ];
}

$partialBase = dirname(__DIR__) . '/partials/sections';
$sectionMap = [
    'hero' => $partialBase . '/hero.php',
    'offer' => $partialBase . '/offer.php',
    'authority' => $partialBase . '/authority.php',
    'gallery' => $partialBase . '/gallery.php',
    'before_after' => $partialBase . '/before-after.php',
    'reviews' => $partialBase . '/reviews.php',
    'longform' => $partialBase . '/longform.php',
    'faq' => $partialBase . '/faq.php',
    'final_cta' => $partialBase . '/final-cta.php',
    'location_convenience' => $partialBase . '/location-convenience.php',
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
<body class="bg-white font-sansElite text-eliteInk antialiased">
    <div class="min-h-screen bg-white text-slate-900">
        <header class="sticky top-0 z-40 border-b border-eliteBorder bg-white/95 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
                <a href="https://elitesmilesutah.com/" class="shrink-0">
                    <img src="<?= e((string) $logoUrl) ?>" alt="Elite Smiles" class="h-auto w-[150px] max-w-full sm:w-[180px]">
                </a>
                <button
                    type="button"
                    data-open-quiz="1"
                    data-track="cta_click"
                    class="cta-pill inline-flex items-center justify-center bg-eliteRose px-4 py-2.5 text-xs font-semibold uppercase tracking-[0.08em] text-white transition hover:bg-eliteRoseDark sm:px-5 sm:text-sm"
                >
                    <?= e((string) $primaryCtaText) ?>
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
                $partial = $sectionMap[$sectionName] ?? null;
                $section = isset($sections[$sectionName]) && is_array($sections[$sectionName]) ? $sections[$sectionName] : [];

                if (!is_string($partial) || !is_file($partial)) {
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
