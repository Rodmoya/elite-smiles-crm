<?php declare(strict_types=1); ?>

<?php
$sectionData = $section ?? [];

$heroEyebrow = $heroEyebrow
    ?? ($sectionData['eyebrow'] ?? $sectionData['hero_eyebrow'] ?? '');

$heroTitle = $heroTitle
    ?? ($sectionData['title'] ?? $sectionData['hero_title'] ?? '');

$heroMobileTitle = (string) ($sectionData['mobile_title'] ?? 'Natural-Looking Veneers in Draper');
$heroMobileBody = (string) ($sectionData['mobile_body'] ?? 'Designed to look refined, healthy, and believable - not fake.');
$heroSupportLine = (string) ($sectionData['support_line'] ?? 'Free private consultation with Dr. Walter Meden DDS. 0% financing may be available for qualified patients.');

$heroBody = $heroBody
    ?? ($sectionData['body'] ?? $sectionData['hero_body'] ?? '');

$heroImageUrl = $heroImageUrl
    ?? ($sectionData['image_url'] ?? '');

$heroImageAlt = $heroImageAlt
    ?? ($sectionData['image_alt'] ?? $heroTitle ?? 'Elite Smiles');

$headerCtaText = $headerCtaText
    ?? ($sectionData['button_text'] ?? $primaryCtaText ?? 'Start My Free Veneers Consultation');

$quickFormAction = (string) ($formAction ?? ($landingContext['formAction'] ?? ''));
$quickStandardForm = $standardForm ?? ($landingContext['standardForm'] ?? []);
if (!is_array($quickStandardForm)) {
    $quickStandardForm = [];
}
$quickAttribution = $attribution ?? ($landingContext['attribution'] ?? []);
if (!is_array($quickAttribution)) {
    $quickAttribution = [];
}
$quickQuizSteps = $quizSteps ?? ($landingContext['quizSteps'] ?? []);
if (!is_array($quickQuizSteps)) {
    $quickQuizSteps = [];
}
$quickProcedureLabel = (string) ($procedureLabel ?? ($landingContext['procedureLabel'] ?? 'Porcelain Veneers'));
$submittedDetailsView = (bool) ($landingContext['submittedDetailsView'] ?? false);
$miniLandingGate = (bool) ($landingContext['miniLandingGate'] ?? false);

?>

<section class="relative overflow-hidden bg-white" data-track-section="hero">
    <div class="mx-auto grid max-w-7xl items-center gap-6 px-5 py-7 sm:px-6 sm:py-10 lg:grid-cols-12 lg:gap-8 lg:px-8 lg:py-12">
        <div class="min-w-0 lg:col-span-6">
            <?php if (!empty($heroEyebrow)): ?>
                <div class="inline-flex items-center rounded-full bg-eliteStone px-3 py-1.5 text-[10px] font-semibold uppercase tracking-[0.22em] text-slate-600 sm:text-[11px]">
                    <?= e((string) $heroEyebrow) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($heroTitle)): ?>
                <h1 class="mt-4 hidden font-site-serif text-5xl font-semibold leading-tight text-eliteInk sm:block lg:text-6xl">
                    <?= e((string) $heroTitle) ?>
                </h1>
                <h1 class="mt-4 max-w-[21rem] font-site-serif text-[clamp(1.85rem,7.2vw,2.25rem)] font-semibold leading-[1.08] text-eliteInk [overflow-wrap:anywhere] sm:hidden">
                    <?= e($heroMobileTitle) ?>
                </h1>
            <?php endif; ?>

            <?php if (!empty($heroBody)): ?>
                <p class="mt-5 hidden max-w-2xl text-lg leading-8 text-eliteBody sm:block">
                    <?= e((string) $heroBody) ?>
                </p>
                <p class="mt-4 max-w-[21rem] text-base leading-7 text-eliteBody sm:hidden">
                    <?= e($heroMobileBody) ?>
                </p>
            <?php endif; ?>

            <p class="mt-4 max-w-[21rem] text-sm font-medium leading-6 text-eliteInk sm:max-w-[34rem] sm:text-base sm:leading-7">
                <?= e($heroSupportLine) ?>
            </p>

            <?php if ($submittedDetailsView): ?>
                <div class="mt-6 max-w-xl rounded-[1.25rem] border border-emerald-200 bg-emerald-50 p-4 text-emerald-900 shadow-sm sm:p-5">
                    <div class="text-[10px] font-semibold uppercase tracking-[0.22em] text-emerald-700">Request Received</div>
                    <p class="mt-2 text-base font-semibold leading-7">Thank you. Rod with Elite Smiles will follow up shortly to help schedule your free consultation.</p>
                    <p class="mt-2 text-sm leading-6 text-emerald-800">If you opted in to SMS, we may text you about scheduling. You can review what the consultation includes below while our team receives your information.</p>
                </div>
            <?php else: ?>
                <form id="quickLeadForm" method="POST" action="<?= e($quickFormAction) ?>" class="mt-6 max-w-xl rounded-[1.25rem] border border-eliteBorder bg-[#fbfaf8] p-4 shadow-sm sm:p-5" data-track-form="quick_lead_form">
                    <?= csrf_input() ?>
                    <?php foreach ($quickAttribution as $attrName => $attrValue): ?>
                        <?php if (!is_scalar($attrValue)) continue; ?>
                        <input type="hidden" name="<?= e((string) $attrName) ?>" value="<?= e((string) $attrValue) ?>">
                    <?php endforeach; ?>
                    <input type="hidden" name="fbp" value="">
                    <input type="hidden" name="fbc" value="">
                    <input type="hidden" name="fbclid" value="">
                    <input type="hidden" name="event_id" value="">
                    <input type="hidden" name="procedure_interest" value="<?= e($quickProcedureLabel) ?>">
                    <?php foreach ($quickQuizSteps as $quickStep): ?>
                        <?php $quickField = (string) ($quickStep['field'] ?? ''); ?>
                        <?php if ($quickField === '' || $quickField === 'procedure_interest') continue; ?>
                        <input type="hidden" name="<?= e($quickField) ?>" value="<?= e((string) ($quickStandardForm[$quickField] ?? '')) ?>">
                    <?php endforeach; ?>

                    <div class="text-[10px] font-semibold uppercase tracking-[0.22em] text-slate-500">Free Veneers Consultation Request</div>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <label class="block text-sm font-medium text-slate-700">
                            <span class="mb-1.5 block">First name</span>
                            <input name="first_name" type="text" autocomplete="given-name" maxlength="190" required value="<?= e((string) ($quickStandardForm['first_name'] ?? '')) ?>" class="w-full min-w-0 rounded-[0.9rem] border border-eliteBorder bg-white px-3 py-2.5 text-base text-eliteInk outline-none transition focus:border-[#bc3f60] sm:text-sm">
                        </label>
                        <label class="block text-sm font-medium text-slate-700">
                            <span class="mb-1.5 block">Last name <span class="font-normal text-slate-400">(optional)</span></span>
                            <input name="last_name" type="text" autocomplete="family-name" maxlength="190" value="<?= e((string) ($quickStandardForm['last_name'] ?? '')) ?>" class="w-full min-w-0 rounded-[0.9rem] border border-eliteBorder bg-white px-3 py-2.5 text-base text-eliteInk outline-none transition focus:border-[#bc3f60] sm:text-sm">
                        </label>
                        <label class="block text-sm font-medium text-slate-700">
                            <span class="mb-1.5 block">Phone</span>
                            <input name="phone" type="tel" autocomplete="tel" inputmode="tel" maxlength="50" required value="<?= e((string) ($quickStandardForm['phone'] ?? '')) ?>" class="w-full min-w-0 rounded-[0.9rem] border border-eliteBorder bg-white px-3 py-2.5 text-base text-eliteInk outline-none transition focus:border-[#bc3f60] sm:text-sm">
                        </label>
                        <label class="block text-sm font-medium text-slate-700">
                            <span class="mb-1.5 block">Email</span>
                            <input name="email" type="email" autocomplete="email" inputmode="email" maxlength="190" required value="<?= e((string) ($quickStandardForm['email'] ?? '')) ?>" class="w-full min-w-0 rounded-[0.9rem] border border-eliteBorder bg-white px-3 py-2.5 text-base text-eliteInk outline-none transition focus:border-[#bc3f60] sm:text-sm">
                        </label>
                        <label class="block text-sm font-medium text-slate-700 sm:col-span-2">
                            <span class="mb-1.5 block">Preferred contact</span>
                            <select name="preferred_contact" class="w-full min-w-0 rounded-[0.9rem] border border-eliteBorder bg-white px-3 py-2.5 text-base text-eliteInk outline-none transition focus:border-[#bc3f60] sm:text-sm">
                                <option value="" <?= (($quickStandardForm['preferred_contact'] ?? '') === '') ? 'selected' : '' ?>>Select one</option>
                                <option value="Text" <?= (($quickStandardForm['preferred_contact'] ?? '') === 'Text') ? 'selected' : '' ?>>Text me</option>
                                <option value="Call" <?= (($quickStandardForm['preferred_contact'] ?? '') === 'Call') ? 'selected' : '' ?>>Call me</option>
                                <option value="Email" <?= (($quickStandardForm['preferred_contact'] ?? '') === 'Email') ? 'selected' : '' ?>>Email me</option>
                            </select>
                            <span class="mt-1.5 block text-[11px] font-normal leading-5 text-slate-500">Choosing text here does not enroll you in SMS. Use the checkbox below if you want text follow-up.</span>
                        </label>
                    </div>
                    <label class="mt-4 flex items-start gap-3 rounded-[1rem] border border-eliteBorder bg-white px-3.5 py-3 text-sm leading-6 text-slate-700">
                        <input
                            type="checkbox"
                            name="sms_consent"
                            value="yes"
                            <?= (($quickStandardForm['sms_consent'] ?? '') === 'yes') ? 'checked' : '' ?>
                            class="mt-1 h-4 w-4 shrink-0 rounded border-slate-300 text-eliteRose focus:ring-eliteRose"
                        >
                        <span>
                            I agree to receive text messages from Elite Smiles about my consultation request, scheduling, reminders, and responses to my questions. Consent is optional and is not required to submit this form. Message frequency may vary. Message and data rates may apply. Reply STOP to opt out, HELP for help. See our <a href="https://hi.elitesmilesutah.com/sms-terms/" class="font-medium text-eliteRose underline">SMS Terms</a>, <a href="https://hi.elitesmilesutah.com/sms-privacy/" class="font-medium text-eliteRose underline">SMS Privacy</a>, <a href="https://elitesmilesutah.com/terms/" class="font-medium text-eliteRose underline">Terms</a>, and <a href="https://elitesmilesutah.com/privacy/" class="font-medium text-eliteRose underline">Privacy Policy</a>.
                        </span>
                    </label>
                    <button type="submit" data-track="quick_lead_submit_click" class="cta-pill mt-4 inline-flex w-full items-center justify-center bg-eliteRose px-4 py-3 text-center text-xs font-semibold uppercase leading-tight tracking-[0.04em] text-white transition hover:bg-eliteRoseDark sm:text-sm">
                        Start My Free Consultation
                    </button>
                    <p class="mt-3 text-[11px] leading-5 text-slate-500">
                        Takes less than 30 seconds. After you submit, you can review the consultation details while our team follows up using your selected contact method. If you leave the SMS box unchecked, we will use call or email instead of text.
                    </p>
                </form>
            <?php endif; ?>

            <div class="mt-5 grid max-w-[21.5rem] gap-2 text-sm text-eliteBody sm:max-w-none sm:grid-cols-3">
                <div class="rounded-full border border-eliteBorder bg-[#fbfaf8] px-3 py-2 text-center font-medium">Free private consult</div>
                <div class="rounded-full border border-eliteBorder bg-[#fbfaf8] px-3 py-2 text-center font-medium">Dr. Meden review</div>
                <div class="rounded-full border border-eliteBorder bg-[#fbfaf8] px-3 py-2 text-center font-medium">0% options may be available</div>
            </div>

            <?php if (!$miniLandingGate && !$submittedDetailsView): ?>
            <div class="mt-7 max-w-[21.5rem] sm:max-w-none">
                <button
                    type="button"
                    data-open-quiz="1"
                    data-track="quiz_secondary_click"
                    class="inline-flex w-full max-w-[420px] items-center justify-center rounded-full border border-eliteBorder bg-white px-4 py-3 text-center text-xs font-semibold uppercase leading-tight tracking-[0.04em] text-eliteInk transition hover:border-[#bc3f60] hover:text-[#bc3f60] sm:w-auto sm:px-6 sm:text-sm sm:tracking-[0.06em]"
                >
                    Answer A Few Smile Questions Instead
                </button>
                <p class="mt-3 max-w-xl text-sm leading-6 text-slate-600">
                    Takes less than 60 seconds. We will text or call to help schedule your free consultation.
                </p>
            </div>
            <?php endif; ?>
        </div>

        <div class="min-w-0 lg:col-span-6">
            <div class="overflow-hidden rounded-[1.6rem] bg-[#fbfaf8] ring-1 ring-eliteBorder shadow-sm sm:rounded-[2rem]">
                <div class="relative aspect-[16/10] sm:aspect-[16/9] lg:aspect-[4/3]">
                    <?php if (!empty($heroImageUrl)): ?>
                        <img
                            src="<?= e((string) $heroImageUrl) ?>"
                            alt="<?= e((string) $heroImageAlt) ?>"
                            class="absolute inset-0 h-full w-full object-cover object-center"
                            loading="eager"
                            fetchpriority="high"
                        >
                    <?php else: ?>
                        <div class="absolute inset-0 bg-[#f4f0eb]"></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
