<?php declare(strict_types=1); ?>

<?php
$sectionData = $section ?? [];

$offerValueLabel = $offerValueLabel
    ?? ($sectionData['value_label'] ?? 'FREE PRIVATE CONSULTATION');

$offerTitle = $offerTitle
    ?? ($sectionData['title'] ?? 'What Your Free Veneers Consultation Includes');

$offerDescription = $offerDescription
    ?? ($sectionData['body'] ?? 'Complete the short intake and our team will review your smile goals, timing, pricing questions, and whether veneers may be the right fit before recommending the best next step.');

$offerItems = $offerItems
    ?? ($sectionData['items'] ?? [
        'Private veneers consultation with Elite Smiles',
        'Personalized smile and cosmetic goals review',
        'Case-by-case pricing conversation',
        '0% financing review for qualified patients',
    ]);

$offerFooterNote = $offerFooterNote
    ?? ($sectionData['footer_note'] ?? '');

$primaryCtaText = $primaryCtaText
    ?? ($sectionData['button_text'] ?? 'Start My Free Veneers Consultation');

$procedureKey = $procedureKey ?? ($landingView['procedure_key'] ?? $landingContext['procedure_key'] ?? '');
$angleKey = $angleKey ?? ($landingView['angle_key'] ?? $landingContext['angle_key'] ?? '');

$financingPartners = $financingPartners ?? ($sectionData['financing_partners'] ?? null);

if (!is_array($financingPartners) || $financingPartners === []) {
    $isFinancingAngle = (($procedureKey ?? '') === 'veneers' && ($angleKey ?? '') === 'financing');

    if ($isFinancingAngle) {
        $financingPartners = [
            ['label' => 'Cherry', 'url' => 'https://withcherry.com/'],
            ['label' => 'Sunbit', 'url' => 'https://www.sunbit.com/'],
            ['label' => 'CareCredit', 'url' => 'https://www.carecredit.com/'],
            ['label' => 'Mountain America Credit Union', 'url' => 'https://www.macu.com/', 'note' => '0% financing available for qualified patients'],
        ];
    }
}

if (!is_array($offerItems)) {
    $offerItems = [];
}
?>

<section class="bg-eliteSageSoft" data-track-section="offer">
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
        <div class="rounded-[2rem] bg-white px-5 py-6 ring-1 ring-eliteBorder shadow-sm sm:px-8 sm:py-8">
            <div class="inline-flex items-center rounded-full bg-eliteStone px-3 py-1.5 text-[10px] font-semibold uppercase tracking-[0.22em] text-slate-600 sm:text-[11px]">
                <?= e((string) $offerValueLabel) ?>
            </div>

            <h2 class="mt-4 font-site-serif text-3xl font-semibold leading-tight text-eliteInk sm:text-4xl">
                <?= e((string) $offerTitle) ?>
            </h2>

            <p class="mt-4 text-base leading-7 text-eliteBody sm:text-lg sm:leading-8">
                <?= e((string) $offerDescription) ?>
            </p>

            <?php if (!empty($offerItems)): ?>
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <?php foreach ($offerItems as $item): ?>
                <?php if (trim((string) $item) === '') continue; ?>
                <div class="rounded-[1.25rem] bg-eliteStone px-4 py-4 text-sm leading-6 text-eliteBody ring-1 ring-eliteBorder">
                    <?= e((string) $item) ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($offerFooterNote)): ?>
            <p class="mt-5 text-sm leading-6 text-slate-700"><?= e((string) $offerFooterNote) ?></p>
            <?php endif; ?>

            <?php if (is_array($financingPartners) && !empty($financingPartners)): ?>
            <div class="mt-6 rounded-[1.5rem] border border-eliteBorder bg-[#faf8f5] px-4 py-4 sm:px-5">
                <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                    Financing partners
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <?php foreach ($financingPartners as $partner): ?>
                        <?php
                        $partnerLabel = trim((string) ($partner['label'] ?? ''));
                        $partnerUrl   = trim((string) ($partner['url'] ?? ''));
                        $partnerNote  = trim((string) ($partner['note'] ?? ''));

                        if ($partnerLabel === '' || $partnerUrl === '') {
                            continue;
                        }
                        ?>
                        <a
                            href="<?= e($partnerUrl) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center rounded-full border border-eliteBorder bg-white px-3 py-2 text-sm font-medium text-eliteInk transition hover:border-eliteRose hover:text-eliteRose"
                        >
                            <?= e($partnerLabel) ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <?php
                $macuNote = '';
                foreach ($financingPartners as $partner) {
                    if (!empty($partner['note'])) {
                        $macuNote = (string) $partner['note'];
                        break;
                    }
                }
                ?>
                <?php if ($macuNote !== ''): ?>
                <p class="mt-3 text-sm leading-6 text-slate-700">
                    <?= e($macuNote) ?>
                </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="mt-6">
                <button type="button" data-open-quiz="1" data-track="cta_click"
                    class="cta-pill inline-flex w-full max-w-[420px] items-center justify-center bg-eliteRose px-4 py-3 text-center text-xs font-semibold uppercase leading-tight tracking-[0.04em] text-white transition hover:bg-eliteRoseDark sm:w-auto sm:px-6 sm:text-sm sm:tracking-[0.06em]">
                    <?= e((string) $primaryCtaText) ?>
                </button>
            </div>
        </div>
    </div>
</section>
