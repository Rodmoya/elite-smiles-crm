<?php
declare(strict_types=1);

$quizSteps = $modal['steps'] ?? [];
$formAction = (string) ($modal['action'] ?? '');
$csrfToken = (string) ($modal['csrf_token'] ?? '');
$formState = $modal['form_state'] ?? [];
if (!is_array($formState)) {
    $formState = [];
}
?>
<form id="quizForm" method="post" action="<?= e($formAction) ?>" class="space-y-6">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

    <?php foreach ($quizSteps as $index => $step): ?>
        <div class="quiz-step<?= $index === 0 ? '' : ' hidden' ?>">
            <div class="text-[10px] font-semibold uppercase tracking-[0.22em] text-slate-500">Quick Question</div>
            <h3 class="mt-3 font-site-serif text-3xl font-semibold leading-tight text-eliteInk"><?= e((string) ($step['title'] ?? '')) ?></h3>
            <?php if (!empty($step['text'])): ?><p class="mt-3 text-base leading-7 text-eliteBody"><?= e((string) $step['text']) ?></p><?php endif; ?>
            <input type="hidden" name="<?= e((string) ($step['field'] ?? '')) ?>" id="<?= e((string) ($step['field'] ?? '')) ?>" value="<?= e((string) ($formState[$step['field']] ?? '')) ?>">
            <div class="mt-6 grid gap-3">
                <?php foreach (($step['options'] ?? []) as $value => $label): ?>
                    <?php
                    $optionValue = is_string($value) ? $value : (string) $label;
                    $optionLabel = is_string($value) ? (string) $label : (string) $label;
                    ?>
                    <button type="button" class="quiz-choice rounded-[1.25rem] border border-eliteBorder bg-white px-4 py-4 text-left text-sm font-medium leading-6 text-eliteInk transition hover:border-[#bc3f60] hover:text-[#bc3f60]" data-field="<?= e((string) ($step['field'] ?? '')) ?>" data-value="<?= e($optionValue) ?>">
                        <?= e($optionLabel) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="quiz-step hidden">
        <div class="text-[10px] font-semibold uppercase tracking-[0.22em] text-slate-500">Final Step</div>
        <h3 class="mt-3 font-site-serif text-3xl font-semibold leading-tight text-eliteInk">Tell us where to reach you</h3>
        <p class="mt-3 text-base leading-7 text-eliteBody">Preferred contact stays here in the final step so our team can follow up the way you want.</p>

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <label class="block text-sm font-medium text-slate-700"><span class="mb-2 block">Full name</span><input class="w-full rounded-[1rem] border border-slate-200 px-4 py-3" type="text" name="full_name" value="<?= e((string) ($formState['full_name'] ?? '')) ?>" required></label>
            <label class="block text-sm font-medium text-slate-700"><span class="mb-2 block">Phone</span><input class="w-full rounded-[1rem] border border-slate-200 px-4 py-3" type="tel" name="phone" value="<?= e((string) ($formState['phone'] ?? '')) ?>" required></label>
            <label class="block text-sm font-medium text-slate-700 sm:col-span-2"><span class="mb-2 block">Email</span><input class="w-full rounded-[1rem] border border-slate-200 px-4 py-3" type="email" name="email" value="<?= e((string) ($formState['email'] ?? '')) ?>" required></label>
            <label class="block text-sm font-medium text-slate-700 sm:col-span-2"><span class="mb-2 block">Preferred method of contact</span>
                <select class="w-full rounded-[1rem] border border-slate-200 px-4 py-3" name="preferred_contact">
                    <option value=""<?= (($formState['preferred_contact'] ?? '') === '') ? ' selected' : '' ?>>Select one</option>
                    <?php foreach (['Call', 'Text', 'Email'] as $option): ?>
                        <option value="<?= e($option) ?>"<?= (($formState['preferred_contact'] ?? '') === $option) ? ' selected' : '' ?>><?= e($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <label class="mt-4 flex items-start gap-3 rounded-[1rem] border border-slate-200 bg-white px-4 py-3 text-sm leading-6 text-slate-700">
            <input
                type="checkbox"
                name="sms_consent"
                value="yes"
                <?= (($formState['sms_consent'] ?? '') === 'yes') ? 'checked' : '' ?>
                class="mt-1 h-4 w-4 shrink-0 rounded border-slate-300 text-eliteRose focus:ring-eliteRose"
            >
            <span>
                I agree to receive text messages from Elite Smiles about my consultation request, scheduling, reminders, and responses to my questions. Consent is optional and is not required to submit this form. Message frequency may vary. Message and data rates may apply. Reply STOP to opt out, HELP for help. See our <a href="https://hi.elitesmilesutah.com/sms-terms/" class="font-medium text-eliteRose underline">SMS Terms</a>, <a href="https://hi.elitesmilesutah.com/sms-privacy/" class="font-medium text-eliteRose underline">SMS Privacy</a>, <a href="https://elitesmilesutah.com/terms/" class="font-medium text-eliteRose underline">Terms</a>, and <a href="https://elitesmilesutah.com/privacy/" class="font-medium text-eliteRose underline">Privacy Policy</a>.
            </span>
        </label>

        <div class="mt-6 flex items-center justify-between gap-3">
            <button type="button" id="quizPrevBtn" class="inline-flex items-center justify-center rounded-full border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Back</button>
            <button type="submit" class="cta-pill inline-flex items-center justify-center rounded-full bg-eliteRose px-6 py-3 text-sm font-semibold uppercase tracking-[0.06em] text-white transition hover:bg-eliteRoseDark">Submit</button>
        </div>
        <p class="mt-3 text-[11px] leading-5 text-slate-500">
            You can submit this form without agreeing to SMS. If the checkbox stays unchecked, our team will follow up by call or email instead of text.
        </p>
    </div>
</form>
