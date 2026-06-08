<?php declare(strict_types=1); ?>
<div
    id="quizModal"
    class="fixed inset-0 z-50 hidden items-end justify-center overflow-x-hidden bg-black/45 px-2 py-2 backdrop-blur-sm sm:items-center sm:px-4 sm:py-6"
    aria-hidden="true"
>
    <div class="relative max-h-[96vh] w-full max-w-[calc(100vw-1rem)] overflow-x-hidden overflow-y-auto rounded-t-[1.5rem] bg-white p-4 shadow-2xl sm:max-w-2xl sm:rounded-[2rem] sm:p-7">

        <button type="button" id="closeQuizBtn"
            class="absolute right-4 top-4 inline-flex h-11 w-11 items-center justify-center rounded-full border border-eliteBorder bg-white text-xl text-slate-500 transition hover:bg-slate-50 hover:text-slate-700"
            aria-label="Close">
            &times;
        </button>

        <div class="mb-5 pr-10">
            <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Elite Smiles</div>
            <h2 class="mt-2 font-site-serif text-2xl font-semibold tracking-tight text-eliteInk sm:text-4xl">
                Reserve Your Free Veneers Consultation
            </h2>
            <p class="mt-2 text-sm leading-6 text-eliteBody">
                Answer a few quick questions so our team can help match you with the right next step.
            </p>
        </div>

        <?php if (!empty($errorMessage)): ?>
        <div class="mb-5 rounded-[1.25rem] border border-red-200 bg-red-50 px-4 py-4 text-sm text-red-700">
            <?= e($errorMessage) ?>
        </div>
        <?php endif; ?>

        <!-- Progress bar -->
        <div class="mb-5">
            <div class="h-2 w-full overflow-hidden rounded-full bg-[#ebe9e4]">
                <div id="quizProgressBar"
                    class="h-full rounded-full bg-eliteRose transition-all duration-300"
                    style="width: <?= e((string) round((1 / max($totalSteps, 1)) * 100)) ?>%;"></div>
            </div>
            <div class="mt-2 text-right text-[11px] font-medium uppercase tracking-[0.12em] text-slate-500 sm:text-xs sm:tracking-[0.18em]">
                Step <span id="quizStepNumber">1</span> of <?= e((string) $totalSteps) ?>
            </div>
        </div>

        <form id="quizForm" method="POST" action="<?= e($formAction) ?>" class="space-y-6" data-track-form="quiz_form">
            <?= csrf_input() ?>
            <?php
            $attributionFields = is_array($modal['attribution'] ?? null) ? $modal['attribution'] : [];
            foreach ($attributionFields as $attrName => $attrValue):
                if (!is_scalar($attrValue)) continue;
            ?>
            <input type="hidden" name="<?= e((string) $attrName) ?>" value="<?= e((string) $attrValue) ?>">
            <?php endforeach; ?>
            <input type="hidden" name="fbp" value="">
            <input type="hidden" name="fbc" value="">
            <input type="hidden" name="fbclid" value="">
            <input type="hidden" name="event_id" value="">

            <!-- Hidden fields for all quiz answers -->
            <?php foreach ($quizSteps as $step):
                $f = (string)($step['field'] ?? '');
                if ($f === '') continue;
            ?>
            <input type="hidden" name="<?= e($f) ?>" id="<?= e($f) ?>" value="<?= e($standardForm[$f] ?? '') ?>">
            <?php endforeach; ?>
            <input type="hidden" name="procedure_interest" id="procedure_interest" value="<?= e($standardForm['procedure_interest'] ?? $procedureLabel) ?>">

            <!-- Quiz option steps -->
            <?php foreach ($quizSteps as $stepIndex => $step):
                $field   = (string) ($step['field'] ?? '');
                $title   = (string) ($step['title'] ?? '');
                $text    = (string) ($step['text']  ?? '');
                $options = (array)  ($step['options'] ?? []);
                $saved   = trim((string) ($standardForm[$field] ?? ''));
            ?>
            <div class="quiz-step <?= $stepIndex === 0 ? '' : 'hidden' ?>">
                <div class="rounded-[1.35rem] border border-eliteBorder bg-[#fbfaf8] p-4 sm:rounded-[1.5rem] sm:p-6">
                    <h3 class="font-site-serif text-[1.45rem] font-semibold leading-tight text-eliteInk sm:text-3xl"><?= e($title) ?></h3>
                    <?php if ($text !== ''): ?>
                    <p class="mt-2 text-sm leading-6 text-eliteBody"><?= e($text) ?></p>
                    <?php endif; ?>
                    <div class="mt-5 grid gap-3">
                        <?php foreach ($options as $ok => $ov):
                            $val      = is_string($ok) ? $ok : (string)$ov;
                            $label    = (string)$ov;
                            $selected = $saved !== '' && $saved === $val;
                        ?>
                        <button type="button"
                            data-field="<?= e($field) ?>"
                            data-value="<?= e($val) ?>"
                            class="quiz-choice min-h-12 w-full rounded-[1.1rem] border px-4 py-3 text-left text-sm font-medium leading-5 transition sm:rounded-[1.2rem] sm:py-4
                                <?= $selected ? 'border-[#bc3f60] bg-[#bc3f60] text-white' : 'border-eliteBorder bg-white text-eliteInk hover:border-[#d8a7b4]' ?>">
                            <?= e($label) ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Contact info step (always last) -->
            <div class="quiz-step hidden">
                <div class="rounded-[1.35rem] border border-eliteBorder bg-[#fbfaf8] p-4 sm:rounded-[1.5rem] sm:p-6">
                    <h3 class="font-site-serif text-[1.45rem] font-semibold leading-tight text-eliteInk sm:text-3xl">
                        Last step: where should we send openings?
                    </h3>
                    <p class="mt-2 text-sm leading-6 text-eliteBody">
                        We will text or call to help schedule your free private consultation with Dr. Meden.
                    </p>
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="first_name" class="mb-2 block text-sm font-medium text-slate-700">First Name</label>
                            <input id="first_name" name="first_name" type="text" maxlength="190" autocomplete="given-name"
                                value="<?= e($standardForm['first_name']) ?>" required
                                class="w-full min-w-0 rounded-[1.1rem] border border-eliteBorder bg-white px-4 py-3 text-base text-eliteInk outline-none transition focus:border-[#d2c8cc] sm:text-sm">
                        </div>
                        <div>
                            <label for="last_name" class="mb-2 block text-sm font-medium text-slate-700">Last Name <span class="font-normal text-slate-400">(optional)</span></label>
                            <input id="last_name" name="last_name" type="text" maxlength="190" autocomplete="family-name"
                                value="<?= e($standardForm['last_name']) ?>"
                                class="w-full min-w-0 rounded-[1.1rem] border border-eliteBorder bg-white px-4 py-3 text-base text-eliteInk outline-none transition focus:border-[#d2c8cc] sm:text-sm">
                        </div>
                        <div>
                            <label for="email" class="mb-2 block text-sm font-medium text-slate-700">Email</label>
                            <input id="email" name="email" type="email" maxlength="190" autocomplete="email" inputmode="email"
                                value="<?= e($standardForm['email']) ?>" required
                                class="w-full min-w-0 rounded-[1.1rem] border border-eliteBorder bg-white px-4 py-3 text-base text-eliteInk outline-none transition focus:border-[#d2c8cc] sm:text-sm">
                        </div>
                        <div>
                            <label for="phone" class="mb-2 block text-sm font-medium text-slate-700">Phone</label>
                            <input id="phone" name="phone" type="tel" maxlength="50" autocomplete="tel" inputmode="tel"
                                value="<?= e($standardForm['phone']) ?>" required
                                class="w-full min-w-0 rounded-[1.1rem] border border-eliteBorder bg-white px-4 py-3 text-base text-eliteInk outline-none transition focus:border-[#d2c8cc] sm:text-sm">
                        </div>
                    </div>
                    <div class="mt-4">
                        <label for="preferred_contact" class="mb-2 block text-sm font-medium text-slate-700">Preferred method of contact</label>
                        <select id="preferred_contact" name="preferred_contact"
                            class="w-full min-w-0 rounded-[1.1rem] border border-eliteBorder bg-white px-4 py-3 text-base text-eliteInk outline-none transition focus:border-[#d2c8cc] sm:text-sm">
                            <option value="" <?= $standardForm['preferred_contact'] === '' ? 'selected' : '' ?>>Select one</option>
                            <option value="Call"  <?= $standardForm['preferred_contact'] === 'Call'  ? 'selected' : '' ?>>Call</option>
                            <option value="Text"  <?= $standardForm['preferred_contact'] === 'Text'  ? 'selected' : '' ?>>Text</option>
                            <option value="Email" <?= $standardForm['preferred_contact'] === 'Email' ? 'selected' : '' ?>>Email</option>
                        </select>
                        <p class="mt-2 text-[11px] leading-5 text-slate-500">Choosing text here does not enroll you in SMS. Use the checkbox below if you want text follow-up.</p>
                    </div>
                    <label class="mt-4 flex items-start gap-3 rounded-[1rem] border border-eliteBorder bg-white px-3.5 py-3 text-sm leading-6 text-slate-700">
                        <input
                            type="checkbox"
                            name="sms_consent"
                            value="yes"
                            <?= ($standardForm['sms_consent'] ?? '') === 'yes' ? 'checked' : '' ?>
                            class="mt-1 h-4 w-4 shrink-0 rounded border-slate-300 text-eliteRose focus:ring-eliteRose"
                        >
                        <span>
                            I agree to receive text messages from Elite Smiles about my consultation request, scheduling, reminders, and responses to my questions. Consent is optional and is not required to submit this form. Message frequency may vary. Message and data rates may apply. Reply STOP to opt out, HELP for help. See our <a href="https://hi.elitesmilesutah.com/sms-terms/" class="font-medium text-eliteRose underline">SMS Terms</a>, <a href="https://hi.elitesmilesutah.com/sms-privacy/" class="font-medium text-eliteRose underline">SMS Privacy</a>, <a href="https://elitesmilesutah.com/terms/" class="font-medium text-eliteRose underline">Terms</a>, and <a href="https://elitesmilesutah.com/privacy/" class="font-medium text-eliteRose underline">Privacy Policy</a>.
                        </span>
                    </label>
                    <button type="submit" data-track="form_submit_click"
                        class="cta-pill mt-5 inline-flex w-full items-center justify-center bg-eliteRose px-4 py-3 text-sm font-semibold uppercase tracking-[0.06em] text-white transition hover:bg-eliteRoseDark">
                        Request My Free Consultation
                    </button>
                    <p class="mt-3 text-sm font-medium leading-6 text-eliteInk">
                        No obligation. Our team will follow up with available consultation times.
                    </p>
                    <p class="mt-3 text-[11px] leading-5 text-slate-500">
                        You can submit this form without agreeing to SMS. If the checkbox stays unchecked, our team will follow up by call or email instead of text.
                    </p>
                </div>
            </div>

            <!-- Back button -->
            <div class="flex justify-start border-t border-eliteBorder pt-2">
                <button type="button" id="prevStepBtn"
                    class="inline-flex items-center justify-center rounded-full border border-eliteBorder bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    Back
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    <?php lp_tracking_js_fn(); ?>

    const modal    = document.getElementById('quizModal');
    const closeBtn = document.getElementById('closeQuizBtn');
    const prevBtn  = document.getElementById('prevStepBtn');
    const progBar  = document.getElementById('quizProgressBar');
    const stepNum  = document.getElementById('quizStepNumber');
    const steps    = Array.from(document.querySelectorAll('.quiz-step'));
    const openBtns = Array.from(document.querySelectorAll('[data-open-quiz="1"]'));
    const total    = steps.length;
    let cur = 1;

    const slug = '<?= e($pageSlug) ?>';
    const proc = '<?= e($procedureKey) ?>';
    const attribution = <?= json_encode($attribution ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}' ?>;
    const googleAdsConversionEvent = '<?= e((string) ($googleAdsConversionEvent ?? '')) ?>';
    const googleAdsConversionSendTo = '<?= e((string) ($googleAdsConversionSendTo ?? '')) ?>';
    const submittedLeadId = '<?= e((string) ($form_submittedLeadId ?? '')) ?>';
    const escapeCss = window.CSS && typeof CSS.escape === 'function'
        ? CSS.escape
        : value => String(value).replace(/["\\]/g, '\\$&');

    function readCookie(name) {
        return document.cookie
            .split(';')
            .map(part => part.trim())
            .find(part => part.indexOf(name + '=') === 0)
            ?.substring(name.length + 1) || '';
    }

    function queryParam(name) {
        return new URLSearchParams(window.location.search).get(name) || '';
    }

    function trackedForms() {
        return Array.from(document.querySelectorAll('form[data-track-form]'));
    }

    function setFormValue(name, value) {
        if (!name || !value) return;
        trackedForms().forEach(activeForm => {
            activeForm.querySelectorAll('[name="' + escapeCss(name) + '"]').forEach(input => {
                input.value = value;
            });
        });
    }

    function buildEventId() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        return 'lead_' + Date.now() + '_' + Math.random().toString(16).slice(2);
    }

    function refreshAttributionFields() {
        const fbclid = queryParam('fbclid') || attribution.fbclid || '';
        const fbp = readCookie('_fbp') || attribution.fbp || '';
        const fbc = readCookie('_fbc') || attribution.fbc || (fbclid ? 'fb.1.' + Math.floor(Date.now() / 1000) + '.' + fbclid : '');
        if (!attribution.event_id) attribution.event_id = buildEventId();

        attribution.fbclid = fbclid;
        attribution.fbp = fbp;
        attribution.fbc = fbc;

        setFormValue('fbclid', fbclid);
        setFormValue('fbp', fbp);
        setFormValue('fbc', fbc);
        setFormValue('event_id', attribution.event_id);
    }

    function updateStep() {
        steps.forEach((s, i) => s.classList.toggle('hidden', i + 1 !== cur));
        if (progBar) progBar.style.width = ((cur / total) * 100) + '%';
        if (stepNum) stepNum.textContent = String(cur);
        if (prevBtn) prevBtn.style.visibility = cur === 1 ? 'hidden' : 'visible';
    }

    function openModal() {
        if (!modal) return;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
        document.documentElement.classList.add('overflow-hidden');
        updateStep();
        trackEvent('wizard_start', { landing_page: slug, procedure_type: proc });
    }

    function applyPrefill(field, value) {
        if (!field || !value) return;

        const hidden = document.getElementById(field);
        if (hidden) hidden.value = value;

        const matchingChoice = document.querySelector('.quiz-choice[data-field="' + escapeCss(field) + '"][data-value="' + escapeCss(value) + '"]');
        if (matchingChoice) {
            const step = matchingChoice.closest('.quiz-step');
            if (step) {
                step.querySelectorAll('.quiz-choice').forEach(b => {
                    b.classList.remove('border-[#bc3f60]', 'bg-[#bc3f60]', 'text-white');
                    b.classList.add('border-eliteBorder', 'bg-white', 'text-eliteInk');
                });
            }

            matchingChoice.classList.remove('border-eliteBorder', 'bg-white', 'text-eliteInk');
            matchingChoice.classList.add('border-[#bc3f60]', 'bg-[#bc3f60]', 'text-white');
        }

        trackEvent('hero_intake_answer', { field, value, landing_page: slug, procedure_type: proc });

        if (cur === 1 && total > 1) {
            cur = 2;
        }
    }

    function closeModal() {
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
        document.documentElement.classList.remove('overflow-hidden');
    }

    openBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            const track = this.getAttribute('data-track');
            const prefillField = this.getAttribute('data-prefill-field');
            const prefillValue = this.getAttribute('data-prefill-value');
            if (track && !prefillField) trackEvent(track, { landing_page: slug, procedure_type: proc });
            applyPrefill(prefillField, prefillValue);
            openModal();
        });
    });

    document.querySelectorAll('[data-scroll-intake="1"]').forEach(btn => {
        btn.addEventListener('click', function () {
            trackEvent(this.getAttribute('data-track') || 'intake_cta_click', { landing_page: slug, procedure_type: proc });
            const quickForm = document.getElementById('quickLeadForm');
            if (!quickForm) return;
            quickForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
            const firstField = quickForm.querySelector('input[name="first_name"], input[name="phone"], input[name="email"]');
            if (firstField) {
                window.setTimeout(() => firstField.focus({ preventScroll: true }), 450);
            }
        });
    });

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (modal)    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
    if (prevBtn)  prevBtn.addEventListener('click', () => { if (cur > 1) { cur--; updateStep(); } });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && modal && modal.classList.contains('flex')) closeModal();
    });

    document.querySelectorAll('.quiz-choice').forEach(btn => {
        btn.addEventListener('click', function () {
            const field = this.getAttribute('data-field');
            const value = this.getAttribute('data-value');
            if (!field) return;

            const hidden = document.getElementById(field);
            if (hidden) hidden.value = value;

            this.closest('.quiz-step').querySelectorAll('.quiz-choice').forEach(b => {
                b.classList.remove('border-[#bc3f60]', 'bg-[#bc3f60]', 'text-white');
                b.classList.add('border-eliteBorder', 'bg-white', 'text-eliteInk');
            });
            this.classList.remove('border-eliteBorder', 'bg-white', 'text-eliteInk');
            this.classList.add('border-[#bc3f60]', 'bg-[#bc3f60]', 'text-white');

            const evtName = field === 'procedure_interest' ? 'procedure_selected'
                : field === 'financing_needed' ? 'financing_selected'
                : 'quiz_step_answered';
            trackEvent(evtName, { field, value, landing_page: slug });

            setTimeout(() => { if (cur < total) { cur++; updateStep(); } }, 120);
        });
    });

    trackedForms().forEach(activeForm => {
        activeForm.addEventListener('submit', () => {
            refreshAttributionFields();
            const conversionPayload = Object.assign({}, attribution, {
                landing_page: slug,
                procedure_type: proc,
                form_type: activeForm.getAttribute('data-track-form') || 'lead_form'
            });
            trackEvent('form_submitted', conversionPayload);
            trackEvent(googleAdsConversionEvent || 'submit_lead_form', conversionPayload);
        });
    });

    <?php lp_tracking_page_view($ctx ?? []); ?>

    refreshAttributionFields();

    if (submittedLeadId) {
        const successPayload = Object.assign({}, attribution, {
            landing_page: slug,
            procedure_type: proc,
            lead_id: submittedLeadId
        });
        trackEvent('lead_success', successPayload);
        if (googleAdsConversionSendTo && typeof gtag === 'function') {
            gtag('event', 'conversion', Object.assign({}, successPayload, { send_to: googleAdsConversionSendTo }));
        }
    }

    updateStep();
})();
</script>
