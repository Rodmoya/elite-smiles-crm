<?php declare(strict_types=1); ?>

<?php
$sectionData = $section ?? [];

$galleryEyebrow = $galleryEyebrow
    ?? ($sectionData['eyebrow'] ?? 'Visual Direction');

$galleryTitle = $galleryTitle
    ?? ($sectionData['title'] ?? '');

$galleryBody = $galleryBody
    ?? ($sectionData['body'] ?? '');

$galleryImages = $galleryImages
    ?? ($sectionData['items'] ?? $sectionData['images'] ?? []);

if (!is_array($galleryImages) || $galleryImages === []) {
    return;
}
?>

<section class="bg-white" data-track-section="gallery">
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
        <div class="rounded-[2rem] bg-[#faf8f5] px-6 py-7 ring-1 ring-eliteBorder shadow-sm sm:px-8 sm:py-9">
            <?php if (!empty($galleryEyebrow)): ?>
                <div class="text-[10px] font-semibold uppercase tracking-[0.22em] text-slate-500 sm:text-[11px]">
                    <?= e((string) $galleryEyebrow) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($galleryTitle)): ?>
                <h2 class="mt-3 font-site-serif text-3xl font-semibold leading-tight text-eliteInk sm:text-4xl">
                    <?= e((string) $galleryTitle) ?>
                </h2>
            <?php endif; ?>

            <?php if (!empty($galleryBody)): ?>
                <p class="mt-4 max-w-3xl text-base leading-7 text-eliteBody sm:text-lg sm:leading-8">
                    <?= e((string) $galleryBody) ?>
                </p>
            <?php endif; ?>

            <div class="mt-6 grid gap-4 md:grid-cols-3">
                <?php foreach ($galleryImages as $image): ?>
                    <?php
                    if (!is_array($image)) {
                        continue;
                    }

                    $src = trim((string) ($image['src'] ?? $image['url'] ?? ''));
                    $alt = trim((string) ($image['alt'] ?? $image['title'] ?? 'Elite Smiles'));
                    $caption = trim((string) ($image['caption'] ?? ''));

                    if ($src === '') {
                        continue;
                    }
                    ?>
                    <figure class="overflow-hidden rounded-[1.5rem] bg-white ring-1 ring-eliteBorder">
                        <img
                            src="<?= e($src) ?>"
                            alt="<?= e($alt) ?>"
                            class="aspect-[4/5] w-full object-cover"
                            loading="lazy"
                        >
                        <?php if ($caption !== ''): ?>
                            <figcaption class="px-4 py-3 text-sm leading-6 text-eliteBody">
                                <?= e($caption) ?>
                            </figcaption>
                        <?php endif; ?>
                    </figure>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($primaryCtaText)): ?>
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
    </div>
</section>
