<?php declare(strict_types=1); ?>

<?php
$sectionData = $section ?? [];

$longFormSections = $longFormSections
    ?? ($sectionData['items'] ?? $sectionData['sections'] ?? []);

if (!is_array($longFormSections) || $longFormSections === []) {
    return;
}

$sectionClasses = trim((string) ($sectionData['classes'] ?? 'bg-white'));
$sectionTrack   = trim((string) ($sectionData['track'] ?? 'longform'));
?>

<section class="<?= e($sectionClasses) ?>" data-track-section="<?= e($sectionTrack) ?>">
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
        <div class="space-y-5">
            <?php foreach ($longFormSections as $i => $item): ?>
                <?php if (!is_array($item)) continue; ?>
                <div class="rounded-[2rem] <?= $i % 2 === 0 ? 'bg-white' : 'bg-eliteStone' ?> px-6 py-7 ring-1 ring-eliteBorder shadow-sm sm:px-8 sm:py-9">
                    <?php if (!empty($item['eyebrow'])): ?>
                        <div class="text-[10px] font-semibold uppercase tracking-[0.22em] text-slate-500 sm:text-[11px]">
                            <?= e((string) $item['eyebrow']) ?>
                        </div>
                    <?php endif; ?>

                    <h2 class="mt-3 font-site-serif text-3xl font-semibold leading-tight text-eliteInk sm:text-4xl">
                        <?= e((string) ($item['title'] ?? '')) ?>
                    </h2>

                    <p class="mt-4 text-base leading-7 text-eliteBody sm:text-lg sm:leading-8">
                        <?= e((string) ($item['body'] ?? '')) ?>
                    </p>

                    <?php if (($i === 1 || $i === 3) && !empty($primaryCtaText)): ?>
                        <div class="mt-6">
                            <button
                                type="button"
                                data-open-quiz="1"
                                data-track="cta_click"
                                class="cta-pill inline-flex w-full max-w-[420px] items-center justify-center bg-eliteRose px-6 py-3 text-sm font-semibold uppercase tracking-[0.06em] text-white transition hover:bg-eliteRoseDark sm:w-auto"
                            >
                                <?= e((string) $primaryCtaText) ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
