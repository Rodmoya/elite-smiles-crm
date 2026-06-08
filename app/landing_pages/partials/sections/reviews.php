<?php declare(strict_types=1); ?>

<?php
$sectionData = $section ?? [];

$reviewsEyebrow = $reviewsEyebrow
    ?? ($sectionData['eyebrow'] ?? 'Patient Reviews');

$reviewsTitle = $reviewsTitle
    ?? ($sectionData['title'] ?? 'What Patients Notice About The Experience');

$reviewsBody = $reviewsBody
    ?? ($sectionData['body'] ?? '');

$reviewItems = $reviewItems
    ?? ($sectionData['items'] ?? $sectionData['reviews'] ?? []);

if (!is_array($reviewItems) || $reviewItems === []) {
    return;
}
?>

<section class="bg-white" data-track-section="reviews">
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
        <div class="rounded-[2rem] bg-[#faf8f5] px-6 py-7 ring-1 ring-eliteBorder shadow-sm sm:px-8 sm:py-9">
            <?php if (!empty($reviewsEyebrow)): ?>
                <div class="text-[10px] font-semibold uppercase tracking-[0.22em] text-slate-500 sm:text-[11px]">
                    <?= e((string) $reviewsEyebrow) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($reviewsTitle)): ?>
                <h2 class="mt-3 font-site-serif text-3xl font-semibold leading-tight text-eliteInk sm:text-4xl">
                    <?= e((string) $reviewsTitle) ?>
                </h2>
            <?php endif; ?>

            <?php if (!empty($reviewsBody)): ?>
                <p class="mt-4 text-base leading-7 text-eliteBody sm:text-lg sm:leading-8">
                    <?= e((string) $reviewsBody) ?>
                </p>
            <?php endif; ?>

            <div class="mt-6 grid gap-4 md:grid-cols-3">
                <?php foreach ($reviewItems as $item): ?>
                    <?php
                    if (!is_array($item)) {
                        continue;
                    }

                    $author = trim((string) ($item['author'] ?? $item['name'] ?? 'Elite Smiles Patient'));
                    $quote  = trim((string) ($item['quote'] ?? $item['body'] ?? $item['text'] ?? ''));
                    $rating = (int) ($item['rating'] ?? 5);

                    if ($quote === '') {
                        continue;
                    }

                    $stars = str_repeat('★', max(1, min(5, $rating)));
                    ?>
                    <div class="rounded-[1.5rem] bg-white px-5 py-5 ring-1 ring-eliteBorder">
                        <div class="text-eliteRose text-sm tracking-[0.08em]"><?= e($stars) ?></div>
                        <div class="mt-3 text-sm font-semibold uppercase tracking-[0.08em] text-slate-500">
                            <?= e($author) ?>
                        </div>
                        <p class="mt-3 text-sm leading-7 text-eliteBody sm:text-base">
                            <?= e($quote) ?>
                        </p>
                    </div>
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
