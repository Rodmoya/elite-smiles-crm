<?php declare(strict_types=1); ?>

<?php
$sectionData = $section ?? [];

$faqHeading = $faqHeading
    ?? ($sectionData['title'] ?? 'Frequently Asked Questions');

$faqIntro = $faqIntro
    ?? ($sectionData['body'] ?? '');

$faqItems = $faqItems
    ?? ($sectionData['items'] ?? $sectionData['faq'] ?? []);

if (!is_array($faqItems) || $faqItems === []) {
    return;
}
?>

<section class="bg-white" data-track-section="faq">
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
        <div class="rounded-[2rem] bg-eliteStone px-6 py-7 ring-1 ring-eliteBorder shadow-sm sm:px-8 sm:py-9">
            <?php if (!empty($faqHeading)): ?>
                <h2 class="font-site-serif text-3xl font-semibold leading-tight text-eliteInk sm:text-4xl">
                    <?= e((string) $faqHeading) ?>
                </h2>
            <?php endif; ?>

            <?php if (!empty($faqIntro)): ?>
                <p class="mt-4 text-base leading-7 text-eliteBody sm:text-lg sm:leading-8">
                    <?= e((string) $faqIntro) ?>
                </p>
            <?php endif; ?>

            <div class="mt-6 divide-y divide-eliteBorder overflow-hidden rounded-[1.5rem] border border-eliteBorder bg-white">
                <?php foreach ($faqItems as $item): ?>
                    <?php
                    if (!is_array($item)) {
                        continue;
                    }

                    $question = trim((string) ($item['question'] ?? ''));
                    $answer   = trim((string) ($item['answer'] ?? ''));

                    if ($question === '' || $answer === '') {
                        continue;
                    }
                    ?>
                    <details class="group px-5 py-4">
                        <summary class="cursor-pointer list-none pr-8 text-left text-base font-semibold leading-7 text-eliteInk marker:content-['']">
                            <span><?= e($question) ?></span>
                            <span class="float-right text-slate-400 transition group-open:rotate-45">+</span>
                        </summary>
                        <div class="mt-3 text-sm leading-7 text-eliteBody sm:text-base">
                            <?= e($answer) ?>
                        </div>
                    </details>
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
