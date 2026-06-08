<?php
declare(strict_types=1);

$modal = $modal ?? [];
$quizSteps = $modal['steps'] ?? [];
$formVariant = (string) ($modal['form_variant'] ?? 'quiz-standard.php');
$formPath = __DIR__ . '/../forms/' . basename($formVariant);
if (!is_file($formPath)) {
    return;
}
$totalSteps = count($quizSteps) + 1;
?>
<div id="quizModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 px-4 py-6">
    <div class="relative w-full max-w-3xl rounded-[2rem] bg-white shadow-2xl">
        <button type="button" id="closeQuizModal" class="absolute right-4 top-4 z-10 inline-flex h-11 w-11 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-700" aria-label="Close quiz">
            <span class="text-xl leading-none">&times;</span>
        </button>

        <div class="border-b border-slate-100 px-6 py-5 sm:px-8">
            <div class="text-[10px] font-semibold uppercase tracking-[0.22em] text-slate-500">Elite Smiles Consultation Quiz</div>
            <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                <div id="quizProgressBar" class="h-full rounded-full bg-[#bc3f60] transition-all duration-300" style="width: 0%"></div>
            </div>
            <div class="mt-3 text-sm text-slate-500">Step <span id="quizStepNumber">1</span> of <?= e((string) $totalSteps) ?></div>
        </div>

        <div class="px-6 py-6 sm:px-8">
            <?php require $formPath; ?>
        </div>
    </div>
</div>
