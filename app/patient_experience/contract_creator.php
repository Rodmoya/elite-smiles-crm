<?php
declare(strict_types=1);

$contract = is_array($selectedContract ?? null) ? $selectedContract : [];
$definitions = $contractDefinitions ?? patient_experience_contract_definitions();
$patients = $contractPatients ?? [];
$contracts = $contracts ?? [];
$selectedTeeth = array_map('intval', (array)($contract['selected_teeth'] ?? []));
$selectedItemKeys = array_map(static fn(array $item): string => (string)($item['key'] ?? ''), (array)($contract['line_items'] ?? []));
$treatmentKey = (string)($contract['treatment_key'] ?? 'veneers');
$status = (string)($contract['status'] ?? 'draft');
$isEditable = !$contract || $status === 'draft';
$shareUrl = (string)($contractShareUrl ?? '');
$money = static fn(mixed $amount): string => number_format((float)$amount, 2, '.', '');
?>

<style>
    .contract-page { aspect-ratio: 8.5 / 11; min-height: 900px; }
    .contract-page.preprinted .contract-digital-letterhead,
    .contract-page.preprinted .contract-digital-footer { display: none; }
    .contract-page.preprinted .contract-paper-body { padding-top: 1.65in; }
    .contract-tooth input:checked + span { background:#0f172a; border-color:#0f172a; color:#fff; box-shadow:0 0 0 3px rgba(15,23,42,.12); }
    .contract-option input:checked + span { background:#eff6ff; border-color:#2563eb; color:#1e3a8a; }
    @media print {
        @page { size: letter; margin: 0; }
        body * { visibility:hidden !important; }
        #contract-preview, #contract-preview * { visibility:visible !important; }
        #contract-preview { position:absolute !important; inset:0 !important; width:8.5in !important; min-height:11in !important; margin:0 !important; box-shadow:none !important; border:0 !important; transform:none !important; }
        .contract-preview-tools { display:none !important; }
    }
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
                        <div><label for="contract-phone" class="block text-sm font-medium text-slate-700">Mobile phone</label><input id="contract-phone" name="patient_phone" value="<?= e((string)($contract['patient_phone'] ?? '')) ?>" class="mt-1.5 min-h-12 w-full rounded-xl border border-slate-300 px-3 text-base" autocomplete="tel"></div>
                        <div><label for="contract-email" class="block text-sm font-medium text-slate-700">Email</label><input id="contract-email" type="email" name="patient_email" value="<?= e((string)($contract['patient_email'] ?? '')) ?>" class="mt-1.5 min-h-12 w-full rounded-xl border border-slate-300 px-3 text-base" autocomplete="email"></div>
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

                <div id="contract-arch-section" class="rounded-2xl border border-slate-200 p-4">
                    <fieldset>
                        <legend class="text-sm font-semibold text-slate-900">3. Treatment area</legend>
                        <div id="contract-arch-controls" class="mt-3 grid grid-cols-3 gap-2">
                            <?php foreach (['upper' => 'Upper', 'lower' => 'Lower', 'both' => 'Both'] as $value => $label): ?>
                                <label class="cursor-pointer"><input class="peer sr-only" type="radio" name="arch_scope" value="<?= e($value) ?>" <?= (string)($contract['arch_scope'] ?? '') === $value ? 'checked' : '' ?>><span class="flex min-h-11 items-center justify-center rounded-xl border border-slate-300 text-sm font-semibold peer-checked:border-blue-600 peer-checked:bg-blue-50 peer-checked:text-blue-800 peer-focus-visible:ring-4 peer-focus-visible:ring-blue-100"><?= e($label) ?></span></label>
                            <?php endforeach; ?>
                        </div>
                        <div id="contract-teeth-controls" class="mt-3">
                            <div class="mb-3 flex flex-wrap gap-2">
                                <button type="button" data-select-teeth="upper" class="min-h-10 rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-100">Select upper</button>
                                <button type="button" data-select-teeth="lower" class="min-h-10 rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-100">Select lower</button>
                                <button type="button" data-select-teeth="all" class="min-h-10 rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-100">Full mouth</button>
                                <button type="button" data-select-teeth="clear" class="min-h-10 rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-100">Clear</button>
                            </div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Upper</p>
                            <div class="grid grid-cols-8 gap-1.5">
                                <?php foreach (range(1, 16) as $tooth): ?><label class="contract-tooth cursor-pointer"><input class="sr-only" type="checkbox" name="selected_teeth[]" value="<?= $tooth ?>" <?= in_array($tooth, $selectedTeeth, true) ? 'checked' : '' ?>><span class="flex min-h-10 items-center justify-center rounded-lg border border-slate-300 text-xs font-semibold transition"><?= $tooth ?></span></label><?php endforeach; ?>
                            </div>
                            <p class="mb-2 mt-4 text-xs font-semibold uppercase tracking-wider text-slate-500">Lower</p>
                            <div class="grid grid-cols-8 gap-1.5">
                                <?php foreach (range(32, 17) as $tooth): ?><label class="contract-tooth cursor-pointer"><input class="sr-only" type="checkbox" name="selected_teeth[]" value="<?= $tooth ?>" <?= in_array($tooth, $selectedTeeth, true) ? 'checked' : '' ?>><span class="flex min-h-10 items-center justify-center rounded-lg border border-slate-300 text-xs font-semibold transition"><?= $tooth ?></span></label><?php endforeach; ?>
                            </div>
                        </div>
                        <p id="contract-no-teeth" class="mt-3 hidden rounded-xl bg-slate-50 p-3 text-sm text-slate-600">This treatment does not require tooth selection.</p>
                    </fieldset>
                </div>

                <div class="rounded-2xl border border-slate-200 p-4">
                    <fieldset>
                        <legend class="text-sm font-semibold text-slate-900">4. Included services</legend>
                        <div id="contract-options" class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                            <?php foreach ($definitions as $definitionKey => $definition): ?>
                                <?php foreach ((array)$definition['options'] as $optionKey => $optionLabel): ?>
                                    <label class="contract-option cursor-pointer" data-treatment-option="<?= e($definitionKey) ?>">
                                        <input class="peer sr-only" type="checkbox" name="line_items[]" value="<?= e($optionKey) ?>" <?= $treatmentKey === $definitionKey && in_array($optionKey, $selectedItemKeys, true) ? 'checked' : '' ?>>
                                        <span class="flex min-h-11 items-center rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700 transition peer-focus-visible:ring-4 peer-focus-visible:ring-blue-100"><?= e((string)$optionLabel) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </div>
                        <label for="contract-custom-items" class="mt-3 block text-sm font-medium text-slate-700">Additional custom items</label>
                        <textarea id="contract-custom-items" name="custom_item_text" rows="3" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2 text-base" placeholder="One treatment item per line"><?= e((string)($contract['custom_item_text'] ?? '')) ?></textarea>
                    </fieldset>
                </div>

                <div class="rounded-2xl border border-slate-200 p-4">
                    <fieldset>
                        <legend class="text-sm font-semibold text-slate-900">5. Financials</legend>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                            <div><label for="contract-original-price" class="block text-sm font-medium text-slate-700">Original price <span class="text-slate-400">optional</span></label><input id="contract-original-price" name="original_price" inputmode="decimal" value="<?= e($money($contract['original_price'] ?? 0)) ?>" class="mt-1.5 min-h-12 w-full rounded-xl border border-slate-300 px-3 text-base tabular-nums"></div>
                            <div><label for="contract-discount" class="block text-sm font-medium text-slate-700">Professional discount</label><input id="contract-discount" name="discount_amount" inputmode="decimal" value="<?= e($money($contract['discount_amount'] ?? 0)) ?>" class="mt-1.5 min-h-12 w-full rounded-xl border border-slate-300 px-3 text-base tabular-nums"></div>
                            <div class="sm:col-span-2 xl:col-span-1 2xl:col-span-2"><label for="contract-final-price" class="block text-sm font-semibold text-slate-900">Final approved price after discount <span class="text-red-600">*</span></label><input id="contract-final-price" name="final_price" required inputmode="decimal" value="<?= e($money($contract['final_price'] ?? 0)) ?>" class="mt-1.5 min-h-12 w-full rounded-xl border border-slate-400 px-3 text-lg font-semibold tabular-nums focus:border-slate-700 focus:outline-none focus:ring-4 focus:ring-slate-200"></div>
                            <div><label for="contract-insurance" class="block text-sm font-medium text-slate-700">Estimated insurance</label><input id="contract-insurance" name="insurance_estimate" inputmode="decimal" value="<?= e($money($contract['insurance_estimate'] ?? 0)) ?>" class="mt-1.5 min-h-12 w-full rounded-xl border border-slate-300 px-3 text-base tabular-nums"></div>
                            <div><div class="flex items-center justify-between"><label for="contract-deposit" class="block text-sm font-medium text-slate-700">Deposit</label><button type="button" data-deposit-quarter class="text-xs font-semibold text-blue-700 hover:underline">Use 25%</button></div><input id="contract-deposit" name="deposit_amount" inputmode="decimal" value="<?= e($money($contract['deposit_amount'] ?? 0)) ?>" class="mt-1.5 min-h-12 w-full rounded-xl border border-slate-300 px-3 text-base tabular-nums"></div>
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
                <header class="contract-digital-letterhead border-b border-slate-200 px-[7%] py-7 text-center">
                    <img src="<?= e(base_url('assets/img/ES-Logo-Stack-500-x-150-px.png')) ?>" alt="Elite Smiles" class="mx-auto h-auto w-[210px] max-w-full">
                    <p class="mt-2 text-xs font-medium uppercase tracking-[0.16em] text-slate-500">Dental Treatment Agreement</p>
                </header>
                <div class="contract-paper-body px-[8%] py-[6%] text-[13px] leading-[1.5] text-slate-800">
                    <div class="flex items-start justify-between gap-6 border-b border-slate-200 pb-4">
                        <div><p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Patient</p><h3 id="preview-patient-name" class="mt-1 text-xl font-semibold text-slate-950">Patient name</h3></div>
                        <div class="text-right"><p id="preview-date" class="font-medium text-slate-900"><?= e(date('F j, Y')) ?></p><p class="mt-1 text-xs text-slate-500"><?= e((string)($contract['contract_number'] ?? 'Draft agreement')) ?></p></div>
                    </div>
                    <h1 id="preview-treatment-title" class="mt-5 text-lg font-semibold text-slate-950">Dental Treatment for Veneers</h1>
                    <p id="preview-opening" class="mt-3">Your final approved treatment price after professional discount is <strong>$0.00</strong>.</p>
                    <div class="mt-5">
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Included treatment</h2>
                        <ul id="preview-line-items" class="mt-2 space-y-1.5 pl-5"></ul>
                    </div>
                    <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Financial summary</h2>
                        <dl class="mt-2 space-y-1.5">
                            <div class="flex justify-between gap-4"><dt>Final approved price</dt><dd id="preview-final-price" class="font-semibold tabular-nums">$0.00</dd></div>
                            <div class="flex justify-between gap-4"><dt>Estimated insurance</dt><dd id="preview-insurance" class="tabular-nums">$0.00</dd></div>
                            <div class="flex justify-between gap-4"><dt>Estimated patient responsibility</dt><dd id="preview-responsibility" class="tabular-nums">$0.00</dd></div>
                            <div class="flex justify-between gap-4"><dt>Deposit</dt><dd id="preview-deposit" class="tabular-nums">$0.00</dd></div>
                            <div class="flex justify-between gap-4 border-t border-slate-300 pt-1.5 font-semibold"><dt>Remaining balance</dt><dd id="preview-balance" class="tabular-nums">$0.00</dd></div>
                        </dl>
                    </div>
                    <div class="mt-5 space-y-3 text-[12px] leading-[1.55]">
                        <p>Insurance benefits are estimates and are not guaranteed. The patient is responsible for any amount not paid by insurance.</p>
                        <p>I understand that dental treatment may require clinically necessary changes during care and that approved additional treatment may result in additional fees.</p>
                        <p>All cosmetic, prosthetic, fixed or removable, and restorative treatment must be paid in full before seating or delivery. A 3% processing fee applies to credit-card payments.</p>
                        <p>Optional IV sedation may be available for a separate hourly fee determined by and payable directly to the anesthesiology provider.</p>
                        <div class="rounded-lg border border-amber-300 bg-amber-50 p-3"><strong>Treatment Plan Cancellation.</strong> <?= e(patient_experience_contract_cancellation_text()) ?></div>
                    </div>
                    <div class="mt-7 grid grid-cols-[1fr_150px] gap-8 border-t border-slate-300 pt-6">
                        <div><div class="h-8 border-b border-slate-500"></div><p class="mt-1 text-[10px] uppercase tracking-wider text-slate-500">Patient or responsible-party signature</p></div>
                        <div><div class="h-8 border-b border-slate-500"></div><p class="mt-1 text-[10px] uppercase tracking-wider text-slate-500">Date</p></div>
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

<script>
(function () {
    const form = document.getElementById('contract-form');
    if (!form) return;
    const definitions = <?= json_encode($definitions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const money = value => Number(String(value || '').replace(/[^0-9.-]/g, '')) || 0;
    const formatMoney = value => new Intl.NumberFormat('en-US', {style:'currency', currency:'USD'}).format(Math.max(0, value));
    const selectedTreatment = () => form.querySelector('input[name="treatment_key"]:checked')?.value || 'veneers';
    const q = id => document.getElementById(id);
    const escapeText = value => String(value || '');

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
        const mode = definitions[key]?.tooth_mode || 'none';
        q('contract-arch-controls').classList.toggle('hidden', mode !== 'arch');
        q('contract-teeth-controls').classList.toggle('hidden', !['teeth','teeth_optional'].includes(mode));
        q('contract-no-teeth').classList.toggle('hidden', mode !== 'none');
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
        const finalPrice = money(q('contract-final-price').value);
        const insurance = money(q('contract-insurance').value);
        const responsibility = Math.max(0, finalPrice - insurance);
        const deposit = money(q('contract-deposit').value);
        const balance = Math.max(0, responsibility - deposit);
        q('preview-patient-name').textContent = patient;
        q('preview-treatment-title').textContent = 'Dental Treatment for ' + definition.label;
        q('preview-opening').innerHTML = 'Your final approved treatment price after professional discount is <strong>' + formatMoney(finalPrice) + '</strong>.';
        q('preview-final-price').textContent = formatMoney(finalPrice);
        q('preview-insurance').textContent = '−' + formatMoney(insurance);
        q('preview-responsibility').textContent = formatMoney(responsibility);
        q('preview-deposit').textContent = '−' + formatMoney(deposit);
        q('preview-balance').textContent = formatMoney(balance);
        q('financial-responsibility').textContent = formatMoney(responsibility);
        q('financial-balance').textContent = formatMoney(balance);

        const teeth = Array.from(form.querySelectorAll('input[name="selected_teeth[]"]:checked')).map(input => input.value);
        const arch = form.querySelector('input[name="arch_scope"]:checked')?.value || '';
        const area = definition.tooth_mode === 'arch' && arch ? ' — ' + (arch === 'both' ? 'Upper and lower arches' : arch.charAt(0).toUpperCase() + arch.slice(1) + ' arch') : (teeth.length ? ' — Teeth ' + teeth.join(', ') : '');
        const items = Array.from(form.querySelectorAll('input[name="line_items[]"]:checked:not(:disabled)')).map(input => input.nextElementSibling?.textContent?.trim() || input.value);
        q('contract-custom-items').value.split(/\r?\n/).map(line => line.trim()).filter(Boolean).forEach(line => items.push(line));
        const list = q('preview-line-items');
        list.replaceChildren();
        (items.length ? items : ['Select included treatment items']).forEach((item, index) => {
            const li = document.createElement('li');
            li.textContent = item + (index === 0 ? area : '');
            li.className = 'list-disc';
            list.appendChild(li);
        });
    }

    form.addEventListener('input', syncPreview);
    form.addEventListener('change', event => {
        if (event.target.id === 'contract-lead') syncPatient();
        if (event.target.name === 'treatment_key') syncTreatmentControls();
        syncPreview();
    });
    document.querySelectorAll('[data-select-teeth]').forEach(button => button.addEventListener('click', () => {
        const mode = button.dataset.selectTeeth;
        form.querySelectorAll('input[name="selected_teeth[]"]').forEach(input => {
            const tooth = Number(input.value);
            input.checked = mode === 'all' || (mode === 'upper' && tooth <= 16) || (mode === 'lower' && tooth >= 17);
            if (mode === 'clear') input.checked = false;
        });
        syncPreview();
    }));
    document.querySelector('[data-deposit-quarter]')?.addEventListener('click', () => {
        const responsibility = Math.max(0, money(q('contract-final-price').value) - money(q('contract-insurance').value));
        q('contract-deposit').value = (responsibility * .25).toFixed(2);
        syncPreview();
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
    syncPreview();
})();
</script>
