<?php declare(strict_types=1); ?>

<?php
$sectionData = $section ?? [];

$quizEntrySteps = [];
if (isset($modal['steps']) && is_array($modal['steps'])) {
    $quizEntrySteps = $modal['steps'];
} elseif (isset($quizSteps) && is_array($quizSteps)) {
    $quizEntrySteps = $quizSteps;
}

$quizFirstStep = $quizEntrySteps[0] ?? [];
if (!is_array($quizFirstStep)) {
    $quizFirstStep = [];
}

$quizFirstField = (string) ($quizFirstStep['field'] ?? '');
$quizFirstTitle = (string) ($quizFirstStep['title'] ?? 'What matters most to you about veneers?');
$quizFirstText = (string) ($quizFirstStep['text'] ?? 'Choose the concern that best matches what you want from your consultation.');
$quizFirstOptions = $quizFirstStep['options'] ?? [];
if (!is_array($quizFirstOptions)) {
    $quizFirstOptions = [];
}

$entryEyebrow = (string) ($sectionData['eyebrow'] ?? 'START HERE');
?>

<section class="bg-white" data-track-section="quiz_entry">
    <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
        <div class="rounded-[1.5rem] bg-[#fbfaf8] p-5 ring-1 ring-eliteBorder shadow-sm sm:rounded-[2rem] sm:p-7">
            <div class="grid gap-5 lg:grid-cols-[0.9fr_1.1fr] lg:items-center">
                <div class="min-w-0">
                    <div class="text-[10px] font-semibold uppercase tracking-[0.22em] text-slate-500 sm:text-[11px]">
                        <?= e($entryEyebrow) ?>
                    </div>
                    <h2 class="mt-3 font-site-serif text-2xl font-semibold leading-tight text-eliteInk sm:text-3xl">
                        <?= e($quizFirstTitle) ?>
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-eliteBody sm:text-base sm:leading-7">
                        <?= e($quizFirstText) ?>
                    </p>
                </div>

                <?php if ($quizFirstField !== '' && $quizFirstOptions !== []): ?>
                    <div class="grid min-w-0 gap-2">
                        <?php foreach ($quizFirstOptions as $optionKey => $optionLabel): ?>
                            <?php
                            $optionValue = is_string($optionKey) ? $optionKey : (string) $optionLabel;
                            $optionText = (string) $optionLabel;
                            if (trim($optionText) === '') {
                                continue;
                            }
                            ?>
                            <button
                                type="button"
                                data-open-quiz="1"
                                data-track="hero_intake_answer"
                                data-prefill-field="<?= e($quizFirstField) ?>"
                                data-prefill-value="<?= e($optionValue) ?>"
                                class="min-h-12 w-full rounded-[1.1rem] border border-eliteBorder bg-white px-4 py-3 text-left text-sm font-medium leading-5 text-eliteInk transition hover:border-[#bc3f60] hover:text-[#bc3f60] sm:text-base"
                            >
                                <?= e($optionText) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mt-5 rounded-[1.25rem] border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">
                Pricing depends on your smile, goals, and number of veneers. Dr. Meden reviews each case personally instead of using cookie-cutter pricing.
            </div>
        </div>
    </div>
</section>
