<?php
declare(strict_types=1);

$contract = is_array($selectedContract ?? null) ? $selectedContract : [];
$definitions = $contractDefinitions ?? patient_experience_contract_definitions();
$patients = $contractPatients ?? [];
$contracts = $contracts ?? [];
$selectedTeeth = array_map('intval', (array)($contract['selected_teeth'] ?? []));
$selectedItemKeys = array_map(static fn(array $item): string => (string)($item['key'] ?? ''), (array)($contract['line_items'] ?? []));
$selectedItemsByKey = [];
foreach ((array)($contract['line_items'] ?? []) as $selectedItem) {
    $selectedItemsByKey[(string)($selectedItem['key'] ?? '')] = $selectedItem;
}
$treatmentKey = (string)($contract['treatment_key'] ?? 'veneers');
$status = (string)($contract['status'] ?? 'draft');
$isEditable = !$contract || $status === 'draft';
$shareUrl = (string)($contractShareUrl ?? '');
$money = static fn(mixed $amount): string => number_format((float)$amount, 2, '.', '');
$originalTerms = patient_experience_contract_original_terms();
$agreementDate = trim((string)($contract['agreement_date'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $agreementDate)) $agreementDate = date('Y-m-d');
?>

<style>
    .contract-page { display:flex; aspect-ratio:8.5 / 11; min-height:900px; flex-direction:column; font-family:Calibri, Arial, sans-serif; color:#111827; }
    .contract-paper-body { display:flex; min-height:0; flex:1; flex-direction:column; }
    .contract-treatment-list { margin:0 0 16pt; padding-left:.42in; }
    .contract-treatment-list li { margin:0; padding-left:.04in; line-height:1.08; }
    .contract-original-copy > p, .contract-legal-copy > p { margin:0 0 8pt; }
    .contract-closing-block { margin-top:auto; margin-bottom:5px; }
    .contract-signature-original { display:grid; grid-template-columns:minmax(0,1fr) 1.65in; gap:.25in; align-items:start; margin:8pt 0 10px; }
    .contract-signature-primary { display:grid; grid-template-columns:auto minmax(0,1fr); column-gap:.08in; align-items:end; }
    .contract-signature-patient { grid-column:2; margin-top:2px; font-size:9pt; line-height:1.1; }
    .contract-signature-rule { min-height:.3in; border-bottom:1px solid #111827; }
    .contract-cancellation-bottom { margin:0; }
    .contract-page.preprinted .contract-digital-letterhead,
    .contract-page.preprinted .contract-digital-footer { display: none; }
    .contract-page.preprinted .contract-paper-body { padding-top: 1.65in; }
    .contract-tooth input:checked + span { background:#0f172a; border-color:#0f172a; color:#fff; box-shadow:0 0 0 3px rgba(15,23,42,.12); }
    .contract-option input:checked + span { background:#eff6ff; border-color:#2563eb; color:#1e3a8a; }
    .contract-payment-notice { margin-bottom:16pt; background:#fef3c7; border:1px solid #fcd34d; white-space:nowrap; font-size:10.5px; line-height:1.25; }
    .contract-sedation { color:#b91c1c; }
    @media print {
        @page { size: letter; margin: 0; }
        body * { visibility:hidden !important; }
        #contract-preview, #contract-preview * { visibility:visible !important; }
        #contract-preview { position:absolute !important; inset:0 !important; box-sizing:border-box !important; width:8.5in !important; height:11in !important; min-height:11in !important; margin:0 !important; overflow:hidden !important; box-shadow:none !important; border:0 !important; transform:none !important; }
        #contract-preview .contract-digital-letterhead { padding-top:0.13in !important; padding-bottom:0.10in !important; }
        #contract-preview .contract-digital-letterhead img { width:1.20in !important; }
        #contract-preview .contract-paper-body { padding-right:1in !important; padding-bottom:0.58in !important; padding-left:1in !important; font-size:11pt !important; line-height:1.08 !important; }
        #contract-preview:not(.preprinted) .contract-paper-body { padding-top:0.35in !important; }
        #contract-preview.preprinted .contract-paper-body { padding-top:1.65in !important; }
        #contract-preview .contract-treatment-list li, #contract-preview .contract-signature { break-inside:avoid; page-break-inside:avoid; }
        #contract-preview .contract-legal-copy { font-size:11pt !important; line-height:1.08 !important; }
        #contract-preview .contract-payment-notice { white-space:nowrap !important; font-size:10.5px !important; line-height:1.25 !important; }
        .contract-preview-tools { display:none !important; }
    }
    @media (max-width: 700px) { .contract-payment-notice { white-space:normal; } }
    @media (prefers-reduced-motion: reduce) { .contract-transition { transition:none !important; } }
</style>

<section class="space-y-6">
    <div class="flex flex-col gap-4 rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Contract Creator</p>
            <h2 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900"><?= $contract ? e((string)$contract['contract_number']) : 'New treatment agreement' ?></h2>
            <p class="mt-2 text-sm text-slate-600">Build the agreement on the left. The official document updates live on the right.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <?php if ($contract): ?>
                <span class="rounded-full px-3 py-1.5 text-xs font-semibold <?= $status === 'signed' ? 'bg-emerald-100 text-emerald-800' : ($status === 'draft' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800') ?>"><?= e(ucwords($status)) ?></span>
            <?php endif; ?>
            <a href="<?= e($tabUrl('contracts')) ?>" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-4 focus:ring-slate-200">New contract</a>
        </div>
    </div>

    <?php if ($shareUrl !== ''): ?>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4" role="status">
            <p class="font-semibold text-emerald-900">Secure signing link ready</p>
            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                <input id="contract-share-url" readonly value="<?= e($shareUrl) ?>" class="min-h-11 min-w-0 flex-1 rounded-xl border border-emerald-300 bg-white px-3 text-sm text-emerald-900">
                <button type="button" data-copy-contract-link class="min-h-11 rounded-xl bg-emerald-700 px-4 text-sm font-semibold text-white hover:bg-emerald-800 focus:outline-none focus:ring-4 focus:ring-emerald-200">Copy link</button>
                <a href="<?= e($shareUrl) ?>" target="_blank" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-emerald-300 bg-white px-4 text-sm font-semibold text-emerald-800 hover:bg-emerald-100">Open</a>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid items-start gap-6 xl:grid-cols-[minmax(340px,0.72fr)_minmax(620px,1.28fr)]">
        <form id="contract-form" method="POST" action="<?= e(base_url('patient-experience.php')) ?>" class="space-y-4 rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm xl:sticky xl:top-6" novalidate>
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="save_contract">
            <input type="hidden" name="contract_id" value="<?= e((string)($contract['id'] ?? 0)) ?>">

            <?php if (!$isEditable): ?>
                <div class="rounded-xl border border-blue-200 bg-blue-50 p-3 text-sm leading-6 text-blue-800">This version is locked because it was sent or signed. Create a new contract to make changes.</div>
            <?php endif; ?>

            <fieldset <?= $isEditable ? '' : 'disabled' ?> class="space-y-4 disabled:opacity-70">
                <div class="rounded-2xl border border-slate-200 p-4">
                    <legend class="px-1 text-sm font-semibold text-slate-900">1. Patient</legend>
                    <label for="contract-lead" class="mt-2 block text-sm font-medium text-slate-700">Select CRM patient</label>
                    <select id="contract-lead" name="lead_id" class="mt-1.5 min-h-12 w-full rounded-xl border border-slate-300 bg-white px-3 text-base focus:border-slate-600 focus:outline-none focus:ring-4 focus:ring-slate-200">
                        <option value="0">New or unlinked patient</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?= e((string)$patient['id']) ?>" data-name="<?= e((string)$patient['full_name']) ?>" data-phone="<?= e((string)$patient['phone']) ?>" data-email="<?= e((string)$patient['email']) ?>" <?= (int)($contract['lead_id'] ?? 0) === (int)$patient['id'] ? 'selected' : '' ?>><?= e((string)$patient['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="contract-patient-name" class="mt-3 block text-sm font-medium text-slate-700">Patient legal name <span class="text-red-600">*</span></label>
                    <input id="contract-patient-name" name="patient_name" required value="<?= e((string)($contract['patient_name'] ?? '')) ?>" class="mt-1.5 min-h-12 w-full rounded-xl border border-slate-300 px-3 text-base focus:border-slate-600 focus:outline-none focus:ring-4 focus:ring-slate-200" autocomplete="name">
                    <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                        <div><label for="contract-date" class="block text-sm font-medium text-slate-700">Agreement date <span class="text-red-600">*</span></label><input id="contract-date" type="date" name="agreement_date" required value="<?= e($agreementDate) ?>" class="mt-1.5 min-h-12 w-full rounded-xl border border-slate-300 bg-white px-3 text-base"></div>
                        <div><label for="contract-phone" class="block text-sm font-medium text-slate-700">Mobile phone</label><input id="contract-phone" name="patient_phone" value="<?= e((string)($contract['patient_phone'] ?? '')) ?>" class="mt-1.5 min-h-12 w-full rounded-xl border border-slate-300 px-3 text-base" autocomplete="tel"></div>
                        <div class="sm:col-span-2 xl:col-span-1 2xl:col-span-2"><label for="contract-email" class="block text-sm font-medium text-slate-700">Email</label><input id="contract-email" type="email" name="patient_email" value="<?= e((string)($contract['patient_email'] ?? '')) ?>" class="mt-1.5 min-h-12 w-full rounded-xl border border-slate-300 px-3 text-base" autocomplete="email"></div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 p-4">
                    <legend class="px-1 text-sm font-semibold text-slate-900">2. Treatment</legend>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <?php foreach ($definitions as $key => $definition): ?>
                            <label class="cursor-pointer">
                                <input type="radio" class="peer sr-only" name="treatment_key" value="<?= e($key) ?>" <?= $treatmentKey === $key ? 'checked' : '' ?>>
                                <span class="flex min-h-12 items-center justify-center rounded-xl border border-slate-300 px-3 py-2 text-center text-sm font-semibold text-slate-700 transition peer-checked:border-slate-900 peer-checked:bg-slate-900 peer-checked:text-white peer-focus-visible:ring-4 peer-focus-visible:ring-slate-200"><?= e((string)$definition['label']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 p-4">
                    <fieldset>
                        <legend class="text-sm font-semibold text-slate-900">3. Included services</legend>
                        <p class="mt-2 text-xs leading-5 text-slate-500">Procedures involving specific teeth will ask you to select them when the procedure is chosen.</p>
                        <div id="contract-options" class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <?php foreach ($definitions as $definitionKey => $definition): ?>
                                <?php foreach ((array)$definition['options'] as $optionKey => $optionLabel): ?>
                                    <?php
                                    $areaMode = (string)(($definition['option_area_modes'] ?? [])[$optionKey] ?? 'none');
                                    $existingArea = (array)($selectedItemsByKey[$optionKey] ?? []);
                                    $existingAreaTeeth = array_map('intval', (array)($existingArea['teeth'] ?? []));
                                    $existingAreaArch = (string)($existingArea['arch_scope'] ?? '');
                                    ?>
                                    <div class="contract-option flex h-[72px] items-stretch overflow-hidden rounded-xl border border-slate-300" data-treatment-option="<?= e($definitionKey) ?>" data-option-key="<?= e($optionKey) ?>" data-option-label="<?= e((string)$optionLabel) ?>" data-area-mode="<?= e($areaMode) ?>">
                                        <label class="flex min-w-0 flex-1 cursor-pointer items-center">
                                            <input class="peer sr-only" type="checkbox" name="line_items[]" value="<?= e($optionKey) ?>" <?= $treatmentKey === $definitionKey && in_array($optionKey, $selectedItemKeys, true) ? 'checked' : '' ?>>
                                            <span class="flex h-full w-full flex-col justify-center rounded-l-xl px-3 py-1 text-[13px] leading-4 text-slate-700 transition peer-checked:bg-blue-50 peer-checked:text-blue-900 peer-focus-visible:ring-4 peer-focus-visible:ring-blue-100">
                                                <span><?= e((string)$optionLabel) ?></span>
                                                <?php if ($areaMode !== 'none'): ?><span class="mt-0.5 text-[11px] font-medium leading-[14px] text-slate-500" data-area-summary><?= $existingAreaTeeth ? 'Teeth ' . e(implode(', ', $existingAreaTeeth)) : ($existingAreaArch !== '' ? e(ucfirst($existingAreaArch) . ($existingAreaArch === 'both' ? ' arches' : ' arch')) : ($areaMode === 'teeth' ? 'Select teeth' : 'Select arch')) ?></span><?php endif; ?>
                                            </span>
                                        </label>
                                        <?php if ($areaMode !== 'none'): ?><button type="button" data-edit-item-area class="flex w-12 shrink-0 items-center justify-center border-l border-slate-300 text-blue-700 hover:bg-blue-50 focus:outline-none focus:ring-4 focus:ring-inset focus:ring-blue-100" aria-label="<?= e(($areaMode === 'teeth' ? 'Choose teeth for ' : 'Choose arch for ') . (string)$optionLabel) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/></svg></button><?php endif; ?>
                                        <div data-item-area-inputs>
                                            <?php foreach ($existingAreaTeeth as $tooth): ?><input type="hidden" name="line_item_teeth[<?= e($optionKey) ?>][]" value="<?= $tooth ?>"><?php endforeach; ?>
                                            <?php if ($existingAreaArch !== ''): ?><input type="hidden" name="line_item_arch[<?= e($optionKey) ?>]" value="<?= e($existingAreaArch) ?>"><?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-3">
                            <label for="contract-custom-item-entry" class="block text-sm font-semibold text-slate-800">Additional custom service</label>
                            <p class="mt-1 text-xs leading-5 text-slate-500">Add it only to this agreement, or save it to the selected treatment library for future contracts.</p>
                            <input id="contract-custom-item-entry" class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 bg-white px-3 text-base" maxlength="190" placeholder="Enter a service or procedure">
                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                <button type="button" id="contract-add-custom-once" class="min-h-12 rounded-xl border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 hover:bg-slate-100 focus:outline-none focus:ring-4 focus:ring-slate-200">Add to this contract</button>
                                <button type="button" id="contract-add-custom-library" class="min-h-12 rounded-xl bg-blue-700 px-3 text-sm font-semibold text-white hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-200">Add to treatment library</button>
                            </div>
                            <p id="contract-custom-item-feedback" class="mt-2 hidden text-xs font-medium" role="status"></p>
                            <div id="contract-custom-item-list" class="mt-3 space-y-2"></div>
                            <textarea id="contract-custom-items" name="custom_item_text" class="hidden" aria-hidden="true"><?= e((string)($contract['custom_item_text'] ?? '')) ?></textarea>
                            <input id="contract-custom-library-items" type="hidden" name="custom_library_items_json" value="[]">
                        </div>
                    </fieldset>
                </div>

                <div class="rounded-2xl border border-slate-200 p-4">
                    <fieldset>
                        <legend class="text-sm font-semibold text-slate-900">4. Financials</legend>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                            <div><label for="contract-original-price" class="block text-sm font-medium text-slate-700">Original price <span class="text-slate-400">optional</span></label><input id="contract-original-price" name="original_price" inputmode="decimal" value="<?= e($money($contract['original_price'] ?? 0)) ?>" class="mt-1.5 min-h-12 w-full rounded-xl border border-slate-300 px-3 text-base tabular-nums"></div>
                            <div><label for="contract-discount" class="block text-sm font-medium text-slate-700">Professional discount</label><input id="contract-discount" name="discount_amount" inputmode="decimal" value="<?= e($money($contract['discount_amount'] ?? 0)) ?>" class="mt-1.5 min-h-12 w-full rounded-xl border border-slate-300 px-3 text-base tabular-nums"></div>
                            <div class="sm:col-span-2 xl:col-span-1 2xl:col-span-2"><label for="contract-final-price" class="block text-sm font-semibold text-slate-900">Final approved price after discount <span class="text-red-600">*</span></label><input id="contract-final-price" name="final_price" required inputmode="decimal" value="<?= e($money($contract['final_price'] ?? 0)) ?>" class="mt-1.5 min-h-12 w-full rounded-xl border border-slate-400 px-3 text-lg font-semibold tabular-nums focus:border-slate-700 focus:outline-none focus:ring-4 focus:ring-slate-200"></div>
                            <div><label for="contract-insurance" class="block text-sm font-medium text-slate-700">Estimated insurance</label><input id="contract-insurance" name="insurance_estimate" inputmode="decimal" value="<?= e($money($contract['insurance_estimate'] ?? 0)) ?>" class="mt-1.5 min-h-12 w-full rounded-xl border border-slate-300 px-3 text-base tabular-nums"></div>
                            <div><div class="flex items-center justify-between"><label for="contract-deposit" class="block text-sm font-medium text-slate-700">Deposit</label><span class="text-xs font-medium text-slate-500">Defaults to 25%</span></div><input id="contract-deposit" name="deposit_amount" inputmode="decimal" value="<?= $contract ? e($money($contract['deposit_amount'] ?? 0)) : '' ?>" class="mt-1.5 min-h-12 w-full rounded-xl border border-slate-300 px-3 text-base tabular-nums" placeholder="Automatically 25%"></div>
                        </div>
                        <div class="mt-4 rounded-xl bg-slate-50 p-3 text-sm">
                            <div class="flex justify-between gap-4"><span class="text-slate-600">Patient responsibility</span><strong id="financial-responsibility" class="tabular-nums text-slate-900">$0.00</strong></div>
                            <div class="mt-2 flex justify-between gap-4 border-t border-slate-200 pt-2"><span class="font-medium text-slate-700">Remaining balance</span><strong id="financial-balance" class="tabular-nums text-slate-900">$0.00</strong></div>
                        </div>
                    </fieldset>
                </div>

                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                    <p class="text-sm font-semibold text-amber-950">Cancellation acknowledgment</p>
                    <p class="mt-2 text-sm leading-6 text-amber-900"><?= e(patient_experience_contract_cancellation_text()) ?></p>
                    <p class="mt-2 text-xs font-medium text-amber-800">This approved paragraph is locked and will require a separate patient acknowledgment before signing.</p>
                </div>
            </fieldset>

            <?php if ($isEditable): ?>
                <button id="save-contract-button" type="submit" class="min-h-12 w-full rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-300 disabled:cursor-not-allowed disabled:opacity-50">Save contract draft</button>
            <?php endif; ?>
        </form>

        <div class="min-w-0 space-y-4">
            <div class="contract-preview-tools flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                <div class="inline-flex rounded-xl bg-slate-100 p-1" role="group" aria-label="Preview format">
                    <button type="button" data-preview-mode="digital" class="min-h-10 rounded-lg bg-white px-3 text-sm font-semibold text-slate-900 shadow-sm">Digital branded</button>
                    <button type="button" data-preview-mode="preprinted" class="min-h-10 rounded-lg px-3 text-sm font-semibold text-slate-600">Preprinted paper</button>
                </div>
                <button type="button" data-print-contract class="min-h-11 rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-100 focus:outline-none focus:ring-4 focus:ring-slate-200">Print preview</button>
            </div>

            <article id="contract-preview" class="contract-page contract-transition relative mx-auto w-full max-w-[850px] overflow-hidden border border-slate-300 bg-white shadow-xl">
                <header class="contract-digital-letterhead border-b border-slate-200 px-[7%] py-5 text-center">
                    <img src="<?= e(base_url('assets/img/ES-Logo-Stack-500-x-150-px.png')) ?>" alt="Elite Smiles" class="mx-auto h-auto w-[147px] max-w-full">
                    <p class="mt-1.5 text-[10px] font-medium uppercase tracking-[0.16em] text-slate-500">Dental Treatment Agreement</p>
                </header>
                <div class="contract-paper-body px-[1in] pb-[0.65in] pt-[0.42in] text-[11pt] leading-[1.08]">
                    <div class="contract-original-copy">
                        <p id="preview-date"><?= e(date('F j, Y', strtotime($agreementDate))) ?></p>
                        <p id="preview-treatment-title">Dental Treatment for Patient name:</p>
                        <p id="preview-opening"></p>
                    </div>
                    <div class="contract-payment-notice rounded px-2 py-1.5 text-center font-semibold text-slate-950"><span><?= e((string)$originalTerms['cashier_check']) ?></span><span class="mx-1.5 text-amber-700" aria-hidden="true">&bull;</span><span><?= e((string)$originalTerms['credit_card']) ?></span></div>
                    <ul id="preview-line-items" class="contract-treatment-list"></ul>
                    <div class="contract-legal-copy text-[11pt] leading-[1.08]">
                        <p><?= e((string)$originalTerms['treatment_changes']) ?></p>
                        <p class="font-semibold"><?= e((string)$originalTerms['insurance_responsibility']) ?></p>
                        <p id="preview-insurance-language" class="hidden"><?= e((string)$originalTerms['insurance_estimate']) ?></p>
                        <p class="contract-sedation"><?= e((string)$originalTerms['sedation']) ?></p>
                        <p><strong><?= e((string)$originalTerms['discount_acceptance']) ?></strong></p>
                    </div>
                    <div class="contract-closing-block">
                        <div class="contract-signature contract-signature-original">
                            <div class="contract-signature-primary"><span class="whitespace-nowrap">Patient Signature/Responsible Party:</span><span class="contract-signature-rule min-w-0"></span><span id="preview-signature-patient" class="contract-signature-patient">Patient name</span></div>
                            <div class="flex items-end gap-2"><span>Date:</span><span class="contract-signature-rule min-w-0 flex-1"></span></div>
                        </div>
                        <div class="contract-cancellation-bottom">
                            <p class="mb-[8pt] font-semibold"><?= e((string)$originalTerms['original_cancellation']) ?></p>
                            <p class="text-[9pt] leading-[1.15]"><strong>Treatment Plan Cancellation.</strong> <?= e(patient_experience_contract_cancellation_text()) ?></p>
                        </div>
                    </div>
                </div>
                <footer class="contract-digital-footer absolute inset-x-0 bottom-0 border-t border-slate-200 bg-white px-[7%] py-3 text-center text-[10px] leading-4 text-slate-500">Elite Smiles by Dr. Walter Meden · 11762 South State, Suite 300, Draper, UT 84020<br>Confidential Patient Document · <span><?= e((string)($contract['contract_number'] ?? 'Draft')) ?></span></footer>
            </article>

            <?php if ($contract && $status === 'draft'): ?>
                <form method="POST" action="<?= e(base_url('patient-experience.php')) ?>" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <?= csrf_input() ?><input type="hidden" name="action" value="send_contract"><input type="hidden" name="contract_id" value="<?= e((string)$contract['id']) ?>">
                    <h3 class="font-semibold text-slate-900">Send secure signing link</h3>
                    <p class="mt-1 text-sm text-slate-600">Sending creates an immutable version of this agreement.</p>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border border-slate-300 px-3 text-sm"><input type="checkbox" name="channels[]" value="sms" <?= trim((string)$contract['patient_phone']) !== '' ? 'checked' : 'disabled' ?>> Text</label>
                        <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border border-slate-300 px-3 text-sm"><input type="checkbox" name="channels[]" value="email" <?= trim((string)$contract['patient_email']) !== '' ? 'checked' : 'disabled' ?>> Email</label>
                    </div>
                    <button type="submit" class="mt-4 min-h-12 w-full rounded-xl bg-blue-700 px-5 text-sm font-semibold text-white hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-200">Create and send signing link</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between"><div><p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Contract history</p><h3 class="mt-2 text-xl font-semibold text-slate-900">Recent agreements</h3></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600"><?= count($contracts) ?> records</span></div>
        <div class="mt-4 grid gap-3 md:grid-cols-2">
            <?php foreach ($contracts as $row): ?>
                <a href="<?= e($tabUrl('contracts', ['contract_id' => (int)$row['id']])) ?>" class="rounded-2xl border border-slate-200 p-4 transition hover:border-slate-400 hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-slate-200">
                    <div class="flex items-start justify-between gap-3"><div><p class="font-semibold text-slate-900"><?= e((string)$row['patient_name']) ?></p><p class="mt-1 text-sm text-slate-500"><?= e((string)$row['contract_number']) ?> · <?= e((string)$row['treatment_label']) ?></p></div><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600"><?= e(ucwords((string)$row['status'])) ?></span></div>
                    <p class="mt-3 text-sm font-semibold tabular-nums text-slate-800">$<?= e(number_format((float)$row['final_price'], 2)) ?></p>
                </a>
            <?php endforeach; ?>
            <?php if (!$contracts): ?><p class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500 md:col-span-2">No treatment agreements yet.</p><?php endif; ?>
        </div>
    </div>
</section>

<div id="contract-area-modal" class="fixed inset-0 z-[90] hidden items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm" aria-hidden="true">
    <div class="w-full max-w-xl rounded-[2rem] bg-white p-5 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="contract-area-modal-title">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Procedure area</p>
                <h2 id="contract-area-modal-title" class="mt-1 text-xl font-semibold text-slate-950">Select teeth</h2>
                <p id="contract-area-modal-help" class="mt-1 text-sm text-slate-600">Choose every tooth included in this procedure.</p>
            </div>
            <button type="button" data-area-cancel class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-slate-300 text-xl text-slate-600 hover:bg-slate-100" aria-label="Close tooth selector">&times;</button>
        </div>
        <p id="contract-area-modal-error" class="mt-3 hidden rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-medium text-red-800" role="alert"></p>
        <div id="contract-modal-teeth" class="mt-5">
            <div class="mb-3 flex flex-wrap gap-2">
                <button type="button" data-modal-select="upper" class="min-h-11 rounded-xl border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-100">Select upper</button>
                <button type="button" data-modal-select="lower" class="min-h-11 rounded-xl border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-100">Select lower</button>
                <button type="button" data-modal-select="all" class="min-h-11 rounded-xl border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-100">Full mouth</button>
                <button type="button" data-modal-select="clear" class="min-h-11 rounded-xl border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-100">Clear</button>
            </div>
            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Upper</p>
            <div class="grid grid-cols-8 gap-1.5">
                <?php foreach (range(1, 16) as $tooth): ?><label class="contract-tooth cursor-pointer"><input class="sr-only" type="checkbox" data-modal-tooth value="<?= $tooth ?>"><span class="flex min-h-11 items-center justify-center rounded-lg border border-slate-300 text-xs font-semibold transition"><?= $tooth ?></span></label><?php endforeach; ?>
            </div>
            <p class="mb-2 mt-4 text-xs font-semibold uppercase tracking-wider text-slate-500">Lower</p>
            <div class="grid grid-cols-8 gap-1.5">
                <?php foreach (range(32, 17) as $tooth): ?><label class="contract-tooth cursor-pointer"><input class="sr-only" type="checkbox" data-modal-tooth value="<?= $tooth ?>"><span class="flex min-h-11 items-center justify-center rounded-lg border border-slate-300 text-xs font-semibold transition"><?= $tooth ?></span></label><?php endforeach; ?>
            </div>
        </div>
        <div id="contract-modal-arch" class="mt-5 hidden grid grid-cols-3 gap-2">
            <?php foreach (['upper' => 'Upper', 'lower' => 'Lower', 'both' => 'Both'] as $value => $label): ?>
                <label class="cursor-pointer"><input class="peer sr-only" type="radio" data-modal-arch name="contract_modal_arch" value="<?= e($value) ?>"><span class="flex min-h-12 items-center justify-center rounded-xl border border-slate-300 text-sm font-semibold peer-checked:border-blue-600 peer-checked:bg-blue-50 peer-checked:text-blue-800 peer-focus-visible:ring-4 peer-focus-visible:ring-blue-100"><?= e($label) ?></span></label>
            <?php endforeach; ?>
        </div>
        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" data-area-cancel class="min-h-12 rounded-xl border border-slate-300 px-5 text-sm font-semibold text-slate-700 hover:bg-slate-100">Cancel</button>
            <button type="button" id="contract-area-apply" class="min-h-12 rounded-xl bg-slate-900 px-6 text-sm font-semibold text-white hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-300">Apply selection</button>
        </div>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('contract-form');
    if (!form) return;
    const definitions = <?= json_encode($definitions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const money = value => Number(String(value || '').replace(/[^0-9.-]/g, '')) || 0;
    const formatMoney = value => new Intl.NumberFormat('en-US', {style:'currency', currency:'USD'}).format(Math.max(0, value));
    const selectedTreatment = () => form.querySelector('input[name="treatment_key"]:checked')?.value || 'veneers';
    const q = id => document.getElementById(id);
    const areaModal = q('contract-area-modal');
    const areaModalTitle = q('contract-area-modal-title');
    const areaModalHelp = q('contract-area-modal-help');
    const areaModalError = q('contract-area-modal-error');
    const modalTeeth = Array.from(areaModal.querySelectorAll('[data-modal-tooth]'));
    const modalArches = Array.from(areaModal.querySelectorAll('[data-modal-arch]'));
    const customEntry = q('contract-custom-item-entry');
    const customTextarea = q('contract-custom-items');
    const customLibraryInput = q('contract-custom-library-items');
    const customList = q('contract-custom-item-list');
    const customFeedback = q('contract-custom-item-feedback');
    let customItems = customTextarea.value.split(/\r?\n/).map(label => label.trim()).filter(Boolean).map(label => ({label, library:false, treatment_key:selectedTreatment()}));
    let activeAreaCard = null;
    let uncheckOnCancel = false;

    function syncPatient() {
        const select = q('contract-lead');
        const option = select?.selectedOptions?.[0];
        if (!option || option.value === '0') return;
        q('contract-patient-name').value = option.dataset.name || '';
        q('contract-phone').value = option.dataset.phone || '';
        q('contract-email').value = option.dataset.email || '';
    }

    function syncTreatmentControls() {
        const key = selectedTreatment();
        document.querySelectorAll('[data-treatment-option]').forEach(label => {
            const visible = label.dataset.treatmentOption === key;
            label.classList.toggle('hidden', !visible);
            const input = label.querySelector('input');
            input.disabled = !visible;
            if (!visible) input.checked = false;
        });
    }

    function syncPreview() {
        const key = selectedTreatment();
        const definition = definitions[key] || definitions.custom;
        const patient = q('contract-patient-name').value.trim() || 'Patient name';
        const dateParts = q('contract-date').value.split('-').map(Number);
        const agreementDate = dateParts.length === 3 && dateParts.every(Number.isFinite)
            ? new Intl.DateTimeFormat('en-US', {month:'long', day:'numeric', year:'numeric'}).format(new Date(dateParts[0], dateParts[1] - 1, dateParts[2]))
            : '';
        const finalPrice = money(q('contract-final-price').value);
        const insurance = money(q('contract-insurance').value);
        const responsibility = Math.max(0, finalPrice - insurance);
        const depositInput = q('contract-deposit').value.trim();
        const deposit = depositInput === '' ? Math.round(responsibility * 25) / 100 : money(depositInput);
        const balance = Math.max(0, responsibility - deposit);
        q('preview-treatment-title').textContent = 'Dental Treatment for ' + patient + ':';
        q('preview-date').textContent = agreementDate;
        q('preview-signature-patient').textContent = patient;
        let financialLanguage = patient + ', Your estimated out of pocket portion of your Dental Treatment cost will be ' + formatMoney(finalPrice) + ' after a professional discount is applied. A deposit of ' + formatMoney(deposit) + ' will be made prior to your appointment. ';
        if (insurance > 0) financialLanguage += 'Your insurance estimated payment is ' + formatMoney(insurance) + '. ';
        financialLanguage += 'Your remaining balance of ' + formatMoney(balance) + ' is due the day of your procedure. The Payment would be in a form of a cashier’s check made to Walter Meden DDS.';
        q('preview-opening').textContent = financialLanguage;
        q('preview-insurance-language').classList.toggle('hidden', insurance <= 0);
        q('financial-responsibility').textContent = formatMoney(responsibility);
        q('financial-balance').textContent = formatMoney(balance);

        const items = Array.from(form.querySelectorAll('[data-treatment-option]')).filter(card => card.querySelector('input[name="line_items[]"]:checked:not(:disabled)')).map(card => {
            const summary = card.querySelector('[data-area-summary]')?.textContent?.trim() || '';
            return card.dataset.optionLabel + (summary && !summary.startsWith('Select ') ? ' — ' + summary : '');
        });
        q('contract-custom-items').value.split(/\r?\n/).map(line => line.trim()).filter(Boolean).forEach(line => items.push(line));
        const list = q('preview-line-items');
        list.replaceChildren();
        (items.length ? items : ['Select included treatment items']).forEach(item => {
            const li = document.createElement('li');
            li.textContent = item;
            li.className = 'list-disc';
            list.appendChild(li);
        });
    }
    function syncCustomItems() {
        const seen = new Set();
        customItems = customItems.filter(item => {
            const key = item.label.toLocaleLowerCase();
            if (seen.has(key)) return false;
            seen.add(key);
            return true;
        });
        customTextarea.value = customItems.map(item => item.label).join('\n');
        customLibraryInput.value = JSON.stringify(customItems.filter(item => item.library).map(item => ({
            label: item.label,
            area_mode: 'none',
            treatment_key: item.treatment_key,
        })));
        customList.replaceChildren();
        customItems.forEach((item, index) => {
            const row = document.createElement('div');
            row.className = 'flex min-h-12 items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2';
            const copy = document.createElement('div');
            copy.className = 'min-w-0';
            const label = document.createElement('p');
            label.className = 'truncate text-sm font-semibold text-slate-800';
            label.textContent = item.label;
            const badge = document.createElement('p');
            badge.className = 'mt-0.5 text-xs text-slate-500';
            badge.textContent = item.library ? ((definitions[item.treatment_key]?.label || 'Treatment') + ' library') : 'This contract only';
            copy.append(label, badge);
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'min-h-11 shrink-0 rounded-lg px-3 text-sm font-semibold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-4 focus:ring-red-100';
            remove.textContent = 'Remove';
            remove.setAttribute('aria-label', 'Remove ' + item.label);
            remove.addEventListener('click', () => { customItems.splice(index, 1); syncCustomItems(); syncPreview(); });
            row.append(copy, remove);
            customList.appendChild(row);
        });
    }

    function addCustomItem(toLibrary) {
        const label = customEntry.value.trim().slice(0, 190);
        if (!label) {
            customFeedback.textContent = 'Enter a service name first.';
            customFeedback.className = 'mt-2 text-xs font-medium text-red-700';
            customEntry.focus();
            return;
        }
        const existing = customItems.find(item => item.label.toLocaleLowerCase() === label.toLocaleLowerCase());
        if (existing) {
            if (toLibrary) {
                existing.library = true;
                existing.treatment_key = selectedTreatment();
                customFeedback.textContent = 'Added to the ' + (definitions[existing.treatment_key]?.label || 'treatment') + ' library.';
            } else {
                customFeedback.textContent = 'That service is already included.';
            }
        } else {
            customItems.push({label, library:toLibrary, treatment_key:selectedTreatment()});
            customFeedback.textContent = toLibrary ? 'Added to this contract and its treatment library.' : 'Added to this contract.';
        }
        customFeedback.className = 'mt-2 text-xs font-medium text-emerald-700';
        customEntry.value = '';
        syncCustomItems();
        syncPreview();
        customEntry.focus();
    }

    form.addEventListener('input', syncPreview);
    form.addEventListener('change', event => {
        if (event.target.id === 'contract-lead') syncPatient();
        if (event.target.name === 'treatment_key') syncTreatmentControls();
        syncPreview();
    });
    function areaInputs(card) { return card.querySelector('[data-item-area-inputs]'); }
    function hasArea(card) {
        if (card.dataset.areaMode === 'teeth') return areaInputs(card).querySelectorAll('input[name^="line_item_teeth"]').length > 0;
        if (card.dataset.areaMode === 'arch') return Boolean(areaInputs(card).querySelector('input[name^="line_item_arch"]')?.value);
        return true;
    }
    function updateAreaSummary(card) {
        const summary = card.querySelector('[data-area-summary]');
        if (!summary) return;
        const teeth = Array.from(areaInputs(card).querySelectorAll('input[name^="line_item_teeth"]')).map(input => Number(input.value)).sort((a, b) => a - b);
        const arch = areaInputs(card).querySelector('input[name^="line_item_arch"]')?.value || '';
        summary.textContent = teeth.length ? 'Teeth ' + teeth.join(', ') : (arch ? (arch === 'both' ? 'Both arches' : arch.charAt(0).toUpperCase() + arch.slice(1) + ' arch') : (card.dataset.areaMode === 'teeth' ? 'Select teeth' : 'Select arch'));
    }
    function openAreaModal(card, shouldUncheck = false) {
        activeAreaCard = card;
        uncheckOnCancel = shouldUncheck;
        const teethMode = card.dataset.areaMode === 'teeth';
        areaModalTitle.textContent = (teethMode ? 'Select teeth for ' : 'Select arch for ') + card.dataset.optionLabel;
        areaModalHelp.textContent = teethMode ? 'Choose every tooth included in this procedure.' : 'Choose the arch or arches included in this procedure.';
        q('contract-modal-teeth').classList.toggle('hidden', !teethMode);
        q('contract-modal-arch').classList.toggle('hidden', teethMode);
        areaModalError.classList.add('hidden');
        areaModalError.textContent = '';
        const selectedTeeth = new Set(Array.from(areaInputs(card).querySelectorAll('input[name^="line_item_teeth"]')).map(input => input.value));
        modalTeeth.forEach(input => { input.checked = selectedTeeth.has(input.value); });
        const selectedArch = areaInputs(card).querySelector('input[name^="line_item_arch"]')?.value || '';
        modalArches.forEach(input => { input.checked = input.value === selectedArch; });
        areaModal.classList.remove('hidden');
        areaModal.classList.add('flex');
        areaModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
        (teethMode ? modalTeeth[0] : modalArches[0])?.focus();
    }
    function closeAreaModal(cancelled = false) {
        if (cancelled && uncheckOnCancel && activeAreaCard) activeAreaCard.querySelector('input[name="line_items[]"]').checked = false;
        areaModal.classList.add('hidden');
        areaModal.classList.remove('flex');
        areaModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
        activeAreaCard?.querySelector('[data-edit-item-area]')?.focus();
        activeAreaCard = null;
        uncheckOnCancel = false;
        syncPreview();
    }
    document.querySelectorAll('[data-treatment-option]').forEach(card => {
        const checkbox = card.querySelector('input[name="line_items[]"]');
        checkbox.addEventListener('change', () => {
            if (card.dataset.areaMode !== 'none' && checkbox.checked && !hasArea(card)) openAreaModal(card, true);
            if (!checkbox.checked && card.dataset.areaMode !== 'none') {
                areaInputs(card).replaceChildren();
                updateAreaSummary(card);
            }
            syncPreview();
        });
        card.querySelector('[data-edit-item-area]')?.addEventListener('click', () => {
            const newlyChecked = !checkbox.checked;
            checkbox.checked = true;
            openAreaModal(card, newlyChecked);
        });
        updateAreaSummary(card);
    });
    areaModal.querySelectorAll('[data-area-cancel]').forEach(button => button.addEventListener('click', () => closeAreaModal(true)));
    areaModal.addEventListener('click', event => { if (event.target === areaModal) closeAreaModal(true); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && !areaModal.classList.contains('hidden')) closeAreaModal(true); });
    areaModal.querySelectorAll('[data-modal-select]').forEach(button => button.addEventListener('click', () => {
        const mode = button.dataset.modalSelect;
        modalTeeth.forEach(input => {
            const tooth = Number(input.value);
            input.checked = mode === 'all' || (mode === 'upper' && tooth <= 16) || (mode === 'lower' && tooth >= 17);
            if (mode === 'clear') input.checked = false;
        });
    }));
    q('contract-area-apply').addEventListener('click', () => {
        if (!activeAreaCard) return;
        const teeth = modalTeeth.filter(input => input.checked).map(input => input.value);
        const arch = modalArches.find(input => input.checked)?.value || '';
        if ((activeAreaCard.dataset.areaMode === 'teeth' && !teeth.length) || (activeAreaCard.dataset.areaMode === 'arch' && !arch)) {
            areaModalError.textContent = activeAreaCard.dataset.areaMode === 'teeth' ? 'Select at least one tooth.' : 'Select upper, lower, or both arches.';
            areaModalError.classList.remove('hidden');
            return;
        }
        const holder = areaInputs(activeAreaCard);
        holder.replaceChildren();
        if (activeAreaCard.dataset.areaMode === 'teeth') {
            teeth.forEach(tooth => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'line_item_teeth[' + activeAreaCard.dataset.optionKey + '][]';
                input.value = tooth;
                holder.appendChild(input);
            });
        } else {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'line_item_arch[' + activeAreaCard.dataset.optionKey + ']';
            input.value = arch;
            holder.appendChild(input);
        }
        updateAreaSummary(activeAreaCard);
        closeAreaModal(false);
    });
    form.addEventListener('submit', event => {
        const missing = Array.from(form.querySelectorAll('[data-treatment-option]')).find(card => card.querySelector('input[name="line_items[]"]:checked:not(:disabled)') && !hasArea(card));
        if (!missing) return;
        event.preventDefault();
        openAreaModal(missing, false);
        areaModalError.textContent = missing.dataset.areaMode === 'teeth' ? 'Select at least one tooth before saving.' : 'Select an arch before saving.';
        areaModalError.classList.remove('hidden');
    });
    q('contract-add-custom-once').addEventListener('click', () => addCustomItem(false));
    q('contract-add-custom-library').addEventListener('click', () => addCustomItem(true));
    customEntry.addEventListener('keydown', event => {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        addCustomItem(false);
    });
    document.querySelectorAll('[data-preview-mode]').forEach(button => button.addEventListener('click', () => {
        const preprinted = button.dataset.previewMode === 'preprinted';
        q('contract-preview').classList.toggle('preprinted', preprinted);
        document.querySelectorAll('[data-preview-mode]').forEach(other => {
            const active = other === button;
            other.classList.toggle('bg-white', active); other.classList.toggle('shadow-sm', active); other.classList.toggle('text-slate-900', active); other.classList.toggle('text-slate-600', !active);
        });
    }));
    document.querySelector('[data-print-contract]')?.addEventListener('click', () => window.print());
    document.querySelector('[data-copy-contract-link]')?.addEventListener('click', async event => {
        await navigator.clipboard.writeText(q('contract-share-url').value);
        event.currentTarget.textContent = 'Copied';
    });
    syncTreatmentControls();
    syncCustomItems();
    syncPreview();
})();
</script>
