<?php declare(strict_types=1); ?>

<?php
$sectionData = $section ?? [];

$beforeAfterEyebrow = $beforeAfterEyebrow
    ?? ($sectionData['eyebrow'] ?? 'Real Transformation Direction');

$beforeAfterTitle = $beforeAfterTitle
    ?? ($sectionData['title'] ?? '');

$beforeAfterBody = $beforeAfterBody
    ?? ($sectionData['body'] ?? '');

$beforeAfterImage = $beforeAfterImage
    ?? ($sectionData['image_url'] ?? $sectionData['src'] ?? '');

$beforeAfterAlt = $beforeAfterAlt
    ?? ($sectionData['image_alt'] ?? $sectionData['alt'] ?? 'Before and after smile transformation');

$beforeAfterDisclaimer = (string) ($sectionData['disclaimer'] ?? 'Individual results may vary. A consultation is needed to determine the right treatment for your smile.');
$beforeAfterExtraImages = $sectionData['extra_images'] ?? [];
if (!is_array($beforeAfterExtraImages)) {
    $beforeAfterExtraImages = [];
}

if (trim((string) $beforeAfterImage) === '') {
    return;
}
?>

<section class="bg-white" data-track-section="before_after">
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
        <div class="rounded-[2rem] bg-[#faf8f5] px-6 py-7 ring-1 ring-eliteBorder shadow-sm sm:px-8 sm:py-9">
            <?php if (!empty($beforeAfterEyebrow)): ?>
                <div class="text-[10px] font-semibold uppercase tracking-[0.22em] text-slate-500 sm:text-[11px]">
                    <?= e((string) $beforeAfterEyebrow) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($beforeAfterTitle)): ?>
                <h2 class="mt-3 font-site-serif text-3xl font-semibold leading-tight text-eliteInk sm:text-4xl">
                    <?= e((string) $beforeAfterTitle) ?>
                </h2>
            <?php endif; ?>

            <?php if (!empty($beforeAfterBody)): ?>
                <p class="mt-4 text-base leading-7 text-eliteBody sm:text-lg sm:leading-8">
                    <?= e((string) $beforeAfterBody) ?>
                </p>
            <?php endif; ?>

            <div class="mt-6 overflow-hidden rounded-[1.75rem] bg-white ring-1 ring-eliteBorder">
                <img
                    src="<?= e((string) $beforeAfterImage) ?>"
                    alt="<?= e((string) $beforeAfterAlt) ?>"
                    class="w-full object-cover"
                    loading="lazy"
                >
            </div>

            <?php if ($beforeAfterExtraImages !== []): ?>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <?php foreach ($beforeAfterExtraImages as $image): ?>
                        <?php
                        if (!is_array($image)) {
                            continue;
                        }

                        $extraSrc = trim((string) ($image['src'] ?? $image['url'] ?? ''));
                        $extraAlt = trim((string) ($image['alt'] ?? 'Elite Smiles veneers before and after example'));
                        if ($extraSrc === '') {
                            continue;
                        }
                        ?>
                        <figure class="overflow-hidden rounded-[1.5rem] bg-white ring-1 ring-eliteBorder">
                            <img
                                src="<?= e($extraSrc) ?>"
                                alt="<?= e($extraAlt) ?>"
                                class="aspect-square w-full object-cover"
                                loading="lazy"
                            >
                        </figure>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <p class="mt-4 rounded-[1.1rem] border border-eliteBorder bg-white px-4 py-3 text-xs leading-5 text-slate-600 sm:text-sm sm:leading-6">
                <?= e($beforeAfterDisclaimer) ?>
            </p>

            <?php if (!empty($primaryCtaText)): ?>
                <div class="mt-6">
                    <button
                        type="button"
                        data-open-quiz="1"
                        data-track="cta_click"
                        class="cta-pill inline-flex w-full max-w-[420px] items-center justify-center bg-eliteRose px-4 py-3 text-center text-xs font-semibold uppercase leading-tight tracking-[0.04em] text-white transition hover:bg-eliteRoseDark sm:w-auto sm:px-6 sm:text-sm sm:tracking-[0.06em]"
                    >
                        <?= e((string) $primaryCtaText) ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
