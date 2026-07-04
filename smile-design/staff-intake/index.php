<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

$user = smile_design_internal_boot('Staff Intake');
$sourceLeadId = (int)get('lead_id', 0);
$sourceLead = null;
if ($sourceLeadId > 0) {
    $sourceLead = db_one('SELECT * FROM leads WHERE id = :id LIMIT 1', ['id' => $sourceLeadId]);
    if (!$sourceLead) {
        $sourceLeadId = 0;
        flash_set('error', 'Lead not found. Staff Intake opened without lead details.');
    }
}

$prefillFullName = trim((string)($sourceLead['full_name'] ?? ''));
$prefillFirstName = trim((string)($sourceLead['first_name'] ?? ''));
$prefillLastName = trim((string)($sourceLead['last_name'] ?? ''));
if ($prefillFirstName === '' && $prefillFullName !== '') {
    $nameParts = preg_split('/\s+/', $prefillFullName, 2);
    $prefillFirstName = trim((string)($nameParts[0] ?? ''));
    $prefillLastName = trim((string)($nameParts[1] ?? $prefillLastName));
}
$prefillPhone = trim((string)($sourceLead['phone'] ?? ''));
$prefillEmail = trim((string)($sourceLead['email'] ?? ''));
$prefillProcedure = trim((string)($sourceLead['procedure_interest'] ?? ''));
$prefillNotes = trim((string)($sourceLead['notes'] ?? ''));
if ($sourceLeadId > 0) {
    $leadContext = 'Source lead #' . $sourceLeadId;
    if (trim((string)($sourceLead['campaign'] ?? '')) !== '') {
        $leadContext .= "\nCampaign: " . trim((string)$sourceLead['campaign']);
    }
    if (trim((string)($sourceLead['landing_page'] ?? '')) !== '') {
        $leadContext .= "\nLanding page: " . trim((string)$sourceLead['landing_page']);
    }
    $prefillNotes = trim($leadContext . ($prefillNotes !== '' ? "\n\nLead notes:\n" . $prefillNotes : ''));
}
$cancelUrl = $sourceLeadId > 0
    ? base_url('leads.php?lead_id=' . $sourceLeadId)
    : base_url('smile-design/cases');
$requestedMobileUploadToken = trim((string)get('mobile_upload_token', ''));
$mobileUploadToken = $requestedMobileUploadToken !== '' && smile_design_verify_token($requestedMobileUploadToken, 'mobile_upload')
    ? $requestedMobileUploadToken
    : smile_design_issue_mobile_upload_token(auth_user_id(), 24);
$mobileUploadUrl = smile_design_mobile_upload_url($mobileUploadToken);
$mobileUploadStatusUrl = base_url('app/actions/smile_design_mobile_upload_status.php?token=' . rawurlencode($mobileUploadToken));
$mobileUploadQrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=12&data=' . rawurlencode($mobileUploadUrl);
smile_design_render_shell_start('Staff Intake');
smile_design_page_header('Staff Intake', 'Create a smile case fast with one strong front before photo, then refine details inside the case workspace.');
?>
<form class="grid gap-5 lg:grid-cols-[1fr_0.85fr]" method="POST" enctype="multipart/form-data" action="<?= e(base_url('app/actions/smile_design_staff_intake_submit.php')) ?>" data-sd-staff-intake data-loading-label="Creating case and analyzing photo...">
    <?= csrf_input() ?>
    <input type="hidden" name="lead_id" value="<?= e((string)$sourceLeadId) ?>">
    <input type="hidden" name="mobile_upload_token" value="<?= e($mobileUploadToken) ?>">
    <div class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
        <?php if ($sourceLeadId > 0): ?>
            <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm leading-6 text-emerald-900">
                Creating this Smile Design case from lead #<?= e((string)$sourceLeadId) ?><?= $prefillFullName !== '' ? ': ' . e($prefillFullName) : '' ?>.
            </div>
        <?php endif; ?>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm font-semibold">First name<input required name="first_name" value="<?= e($prefillFirstName) ?>" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-3"></label>
            <label class="block text-sm font-semibold">Last name<input name="last_name" value="<?= e($prefillLastName) ?>" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-3"></label>
            <label class="block text-sm font-semibold">Phone<input required name="phone" value="<?= e($prefillPhone) ?>" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-3"></label>
            <label class="block text-sm font-semibold">Procedure<select name="procedure_interest" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-3" data-sd-procedure-select><?php $procedureOptions = smile_design_procedure_options(); if ($prefillProcedure !== '' && !in_array($prefillProcedure, $procedureOptions, true)): ?><option value="<?= e($prefillProcedure) ?>" selected><?= e($prefillProcedure) ?></option><?php endif; ?><?php foreach ($procedureOptions as $key => $label): ?><option value="<?= e($label) ?>" <?= $prefillProcedure !== '' && $prefillProcedure === $label ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
            <label class="block text-sm font-semibold sm:col-span-2">Email <span class="font-normal text-slate-500">(optional)</span><input name="email" type="email" value="<?= e($prefillEmail) ?>" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-3"></label>
            <label class="block text-sm font-semibold" data-sd-lvi-style-field>LVI style <span class="font-normal text-slate-500">(optional)</span><select name="selected_style" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-3"><?php foreach (smile_design_style_options() as $key => $label): ?><option value="<?= e($key) ?>" <?= $key === 'natural' ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
            <label class="block text-sm font-semibold" data-sd-shade-field>Veneer shade <span class="font-normal text-slate-500">(default)</span><select name="shade_goal" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-3"><?php foreach (smile_design_shade_options() as $key => $label): ?><option value="<?= e($key) ?>" <?= $key === '110' ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
            <label class="block text-sm font-semibold">Treatment scope<select name="treatment_scope" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-3"><?php foreach (smile_design_treatment_scope_options() as $key => $label): ?><option value="<?= e($key) ?>" <?= $key === 'upper' ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
            <label class="block text-sm font-semibold">Smile width<select name="smile_width_goal" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-3"><?php foreach (smile_design_smile_width_options() as $key => $label): ?><option value="<?= e($key) ?>" <?= $key === 'keep_current' ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        </div>

        <details class="mt-4 rounded-md bg-slate-50 p-4" <?= $sourceLeadId > 0 ? 'open' : '' ?>>
            <summary class="cursor-pointer text-sm font-semibold text-slate-900">Optional case details</summary>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <label class="block text-sm font-semibold">Consent status<select name="consent_status" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-3"><option value="not_recorded">Not recorded</option><option value="verbal">Verbal consent</option><option value="written">Written consent on file</option></select></label>
            </div>
            <label class="mt-4 block text-sm font-semibold">Optional notes<textarea name="notes" rows="5" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-3"><?= e($prefillNotes) ?></textarea></label>
        </details>

        <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center">
            <button class="w-full rounded-md bg-slate-950 px-5 py-4 text-base font-semibold text-white sm:w-auto" type="submit">Create Smile Design Case</button>
            <a class="inline-flex w-full items-center justify-center rounded-md border border-slate-300 bg-white px-5 py-4 text-base font-semibold text-slate-700 transition hover:bg-slate-100 sm:w-auto" href="<?= e($cancelUrl) ?>">
                <?= $sourceLeadId > 0 ? 'Cancel and Return to Lead' : 'Cancel' ?>
            </a>
        </div>
        <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100"><div class="hidden h-full bg-emerald-500" data-sd-upload-progress></div></div>
    </div>

    <div class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-semibold">Staff Photo Upload</p>
        <div class="mt-3 grid gap-4 rounded-md border border-slate-200 bg-slate-50 p-4 sm:grid-cols-[150px_1fr]">
            <div class="rounded-md bg-white p-2 shadow-sm">
                <img class="h-auto w-full" src="<?= e($mobileUploadQrUrl) ?>" alt="Mobile upload QR code">
            </div>
            <div>
                <p class="text-sm font-semibold text-slate-950">Upload from phone</p>
                <p class="mt-2 text-sm leading-6 text-slate-600">Scan this QR code to upload Front, Left 45, and Right 45 photos from your phone. This link is upload-only and expires in 24 hours.</p>
                <a class="mt-3 inline-flex rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700" href="<?= e($mobileUploadUrl) ?>" target="_blank" rel="noreferrer">Open mobile upload link</a>
            </div>
        </div>
        <div class="mt-3 rounded-md border border-slate-200 bg-white p-3" data-sd-mobile-status data-status-url="<?= e($mobileUploadStatusUrl) ?>">
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-semibold text-slate-900">Phone upload status</p>
                <button class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700" type="button" data-sd-refresh-mobile-uploads>Refresh</button>
            </div>
            <div class="mt-3 grid gap-2 text-xs sm:grid-cols-3">
                <?php foreach (['front' => 'Front', 'left_45' => 'Left 45', 'right_45' => 'Right 45'] as $slotKey => $slotLabel): ?>
                    <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2" data-sd-mobile-slot="<?= e($slotKey) ?>">
                        <p class="font-semibold text-slate-800"><?= e($slotLabel) ?></p>
                        <p class="mt-1 text-slate-500" data-sd-mobile-slot-status>Waiting for phone upload.</p>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="mt-3 text-xs leading-5 text-slate-500" data-sd-mobile-status-summary>Uploads from the phone will appear here automatically.</p>
        </div>
        <div data-sd-photo-field>
            <label class="mt-3 flex min-h-44 cursor-pointer flex-col items-center justify-center rounded-md border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center">
                <span class="text-base font-semibold text-slate-900">Front BEFORE photo</span>
                <span class="mt-2 text-sm text-slate-500">Required. Upload here or use the phone QR above. JPG, PNG, WebP, HEIC, or HEIF.</span>
                <input name="before_photo_front" type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif" class="sr-only" data-sd-photo-input data-sd-photo-label="Front">
            </label>
            <img class="mt-4 hidden max-h-[420px] w-full rounded-md object-contain ring-1 ring-slate-200" alt="Selected front photo preview" data-sd-photo-preview>
            <p class="mt-3 hidden rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm leading-6 text-amber-800" data-sd-photo-status></p>
            <button class="mt-3 hidden rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold" type="button" data-sd-replace-photo>Replace photo</button>
        </div>
        <div class="mt-3 rounded-md border border-sky-200 bg-sky-50 px-3 py-2 text-sm leading-6 text-sky-900">
            Create Smile Design Case only after the Front photo shows ready here, or after the phone upload page shows Front as ready.
        </div>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div data-sd-photo-field>
                <label class="block text-sm font-semibold">Left 45 photo <span class="font-normal text-slate-500">(optional)</span><input name="before_photo_left_45" type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2" data-sd-photo-input data-sd-photo-label="Left 45"></label>
                <img class="mt-3 hidden max-h-56 w-full rounded-md object-contain ring-1 ring-slate-200" alt="Selected left 45 photo preview" data-sd-photo-preview>
                <p class="mt-2 hidden rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-800" data-sd-photo-status></p>
            </div>
            <div data-sd-photo-field>
                <label class="block text-sm font-semibold">Right 45 photo <span class="font-normal text-slate-500">(optional)</span><input name="before_photo_right_45" type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2" data-sd-photo-input data-sd-photo-label="Right 45"></label>
                <img class="mt-3 hidden max-h-56 w-full rounded-md object-contain ring-1 ring-slate-200" alt="Selected right 45 photo preview" data-sd-photo-preview>
                <p class="mt-2 hidden rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-800" data-sd-photo-status></p>
            </div>
        </div>
    </div>
</form>
<script>
(function () {
    const form = document.querySelector('[data-sd-staff-intake]');
    const progress = document.querySelector('[data-sd-upload-progress]');
    const photoInputs = Array.from(document.querySelectorAll('[data-sd-photo-input]'));
    const submitButton = form ? form.querySelector('button[type="submit"]') : null;
    const procedureSelect = document.querySelector('[data-sd-procedure-select]');
    const lviStyleField = document.querySelector('[data-sd-lvi-style-field]');
    const lviStyleSelect = lviStyleField ? lviStyleField.querySelector('select') : null;
    const shadeField = document.querySelector('[data-sd-shade-field]');
    const shadeSelect = shadeField ? shadeField.querySelector('select') : null;
    const mobileStatusPanel = document.querySelector('[data-sd-mobile-status]');
    const mobileRefreshButton = document.querySelector('[data-sd-refresh-mobile-uploads]');
    const mobileStatusSummary = document.querySelector('[data-sd-mobile-status-summary]');
    let heicConverterPromise = null;
    let preparingCount = 0;
    let mobilePollTimer = null;
    function isLipRepositionOnly(value) {
        const text = String(value || '').toLowerCase();
        return (text.includes('lip reposition') || text.includes('gummy smile')) && !text.includes('veneer');
    }
    function syncLviStyleVisibility() {
        const hideStyle = procedureSelect && isLipRepositionOnly(procedureSelect.value);
        if (lviStyleField) lviStyleField.classList.toggle('hidden', !!hideStyle);
        if (shadeField) shadeField.classList.toggle('hidden', !!hideStyle);
        if (lviStyleSelect) {
            lviStyleSelect.disabled = !!hideStyle;
            if (hideStyle) lviStyleSelect.value = 'natural';
        }
        if (shadeSelect) {
            shadeSelect.disabled = !!hideStyle;
            if (hideStyle) shadeSelect.value = '110';
        }
    }
    function isHeicFile(file) {
        if (!file) return false;
        const extension = (file.name.split('.').pop() || '').toLowerCase();
        return file.type === 'image/heic' || file.type === 'image/heif' || extension === 'heic' || extension === 'heif';
    }
    function jpgName(file) {
        return file.name.replace(/\.[^.]+$/, '') + '.jpg';
    }
    function setInputFile(inputElement, file) {
        const transfer = new DataTransfer();
        transfer.items.add(file);
        inputElement.files = transfer.files;
    }
    function loadHeicConverter() {
        if (window.heic2any) return Promise.resolve(window.heic2any);
        if (heicConverterPromise) return heicConverterPromise;
        heicConverterPromise = new Promise(function (resolve, reject) {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js';
            script.async = true;
            script.onload = function () {
                if (window.heic2any) {
                    resolve(window.heic2any);
                } else {
                    reject(new Error('HEIC converter did not load.'));
                }
            };
            script.onerror = function () {
                reject(new Error('HEIC converter could not be loaded.'));
            };
            document.head.appendChild(script);
        });
        return heicConverterPromise;
    }
    async function convertHeicInput(inputElement) {
        const file = inputElement.files && inputElement.files[0];
        if (!isHeicFile(file)) return false;
        const heic2any = await loadHeicConverter();
        const converted = await heic2any({
            blob: file,
            toType: 'image/jpeg',
            quality: 0.92
        });
        const blob = Array.isArray(converted) ? converted[0] : converted;
        const jpgFile = new File([blob], jpgName(file), {
            type: 'image/jpeg',
            lastModified: Date.now()
        });
        setInputFile(inputElement, jpgFile);
        return true;
    }
    function photoParts(inputElement) {
        const field = inputElement.closest('[data-sd-photo-field]');
        return {
            field: field,
            preview: field ? field.querySelector('[data-sd-photo-preview]') : null,
            status: field ? field.querySelector('[data-sd-photo-status]') : null,
            replace: field ? field.querySelector('[data-sd-replace-photo]') : null
        };
    }
    function showStatus(parts, message, tone) {
        const status = parts.status;
        if (!status) return;
        status.textContent = message;
        status.classList.toggle('border-emerald-200', tone === 'success');
        status.classList.toggle('bg-emerald-50', tone === 'success');
        status.classList.toggle('text-emerald-800', tone === 'success');
        status.classList.toggle('border-amber-200', tone !== 'success');
        status.classList.toggle('bg-amber-50', tone !== 'success');
        status.classList.toggle('text-amber-800', tone !== 'success');
        status.classList.remove('hidden');
    }
    function hideStatus(parts) {
        const status = parts.status;
        if (!status) return;
        status.textContent = '';
        status.classList.add('hidden');
    }
    function setMobileSlot(slotKey, slot) {
        const slotEl = document.querySelector('[data-sd-mobile-slot="' + slotKey + '"]');
        if (!slotEl) return;
        const statusEl = slotEl.querySelector('[data-sd-mobile-slot-status]');
        const ready = !!(slot && slot.ready);
        slotEl.classList.toggle('border-emerald-200', ready);
        slotEl.classList.toggle('bg-emerald-50', ready);
        slotEl.classList.toggle('border-slate-200', !ready);
        slotEl.classList.toggle('bg-slate-50', !ready);
        if (statusEl) {
            statusEl.textContent = ready
                ? 'Ready from phone' + (slot.original_name ? ': ' + slot.original_name : '.')
                : 'Waiting for phone upload.';
            statusEl.classList.toggle('text-emerald-700', ready);
            statusEl.classList.toggle('text-slate-500', !ready);
        }
    }
    async function refreshMobileUploads(manual) {
        if (!mobileStatusPanel || !mobileStatusPanel.dataset.statusUrl) return;
        if (mobileRefreshButton) mobileRefreshButton.disabled = true;
        try {
            const response = await fetch(mobileStatusPanel.dataset.statusUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await response.json();
            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'Could not check phone uploads.');
            }
            const slots = data.slots || {};
            ['front', 'left_45', 'right_45'].forEach(function (slotKey) {
                setMobileSlot(slotKey, slots[slotKey] || null);
            });
            if (mobileStatusSummary) {
                const count = Number(data.ready_count || 0);
                mobileStatusSummary.textContent = count > 0
                    ? count + ' phone upload' + (count === 1 ? ' is' : 's are') + ' ready. You can create the case now if Front is ready.'
                    : (manual ? 'No phone uploads found yet. Try again after the phone page shows Ready.' : 'Waiting for phone uploads...');
                mobileStatusSummary.classList.toggle('text-emerald-700', count > 0);
                mobileStatusSummary.classList.toggle('text-slate-500', count === 0);
            }
        } catch (error) {
            if (mobileStatusSummary) {
                mobileStatusSummary.textContent = error.message || 'Could not check phone uploads.';
                mobileStatusSummary.classList.remove('text-emerald-700');
                mobileStatusSummary.classList.add('text-rose-700');
            }
        } finally {
            if (mobileRefreshButton) mobileRefreshButton.disabled = false;
        }
    }
    function setSubmitReady() {
        if (!submitButton) return;
        const busy = preparingCount > 0;
        submitButton.disabled = busy;
        submitButton.classList.toggle('cursor-wait', busy);
        submitButton.classList.toggle('opacity-70', busy);
        submitButton.textContent = busy ? 'Preparing Photos...' : 'Create Smile Design Case';
    }
    function setPreview(inputElement) {
        const file = inputElement.files && inputElement.files[0];
        const parts = photoParts(inputElement);
        if (!file || !parts.preview) return;
        parts.preview.classList.add('hidden');
        parts.preview.removeAttribute('src');
        parts.preview.onload = function () {
            if (parts.preview.naturalWidth > 0 && parts.preview.naturalHeight > 0) {
                parts.preview.classList.remove('hidden');
            }
        };
        parts.preview.onerror = function () {
            parts.preview.classList.add('hidden');
            showStatus(parts, 'Preview is not available for this file, but it can still be uploaded if it is a supported photo type.');
        };
        parts.preview.src = URL.createObjectURL(file);
    }
    async function preparePhotoInput(inputElement) {
        const file = inputElement.files && inputElement.files[0];
        const parts = photoParts(inputElement);
        const label = inputElement.getAttribute('data-sd-photo-label') || 'Photo';
        if (!file) {
            if (parts.preview) {
                parts.preview.classList.add('hidden');
                parts.preview.removeAttribute('src');
            }
            hideStatus(parts);
            return;
        }

        hideStatus(parts);
        if (parts.preview) {
            parts.preview.classList.add('hidden');
            parts.preview.removeAttribute('src');
        }
        if (parts.replace) parts.replace.classList.remove('hidden');

        if (!isHeicFile(file)) {
            setPreview(inputElement);
            showStatus(parts, label + ' photo ready.', 'success');
            return;
        }

        preparingCount += 1;
        setSubmitReady();
        showStatus(parts, 'Converting ' + label + ' HEIC to full-resolution JPG...');
        if (window.smileDesignShowActionLoader) {
            window.smileDesignShowActionLoader('Converting ' + label + ' photo...');
        }
        try {
            await convertHeicInput(inputElement);
            setPreview(inputElement);
            showStatus(parts, label + ' converted to JPG and ready.', 'success');
        } catch (error) {
            if (parts.preview) parts.preview.classList.add('hidden');
            inputElement.value = '';
            showStatus(parts, label + ' HEIC conversion failed in this browser. Please upload JPG, PNG, or WebP for now.');
        } finally {
            preparingCount = Math.max(0, preparingCount - 1);
            if (preparingCount === 0 && window.smileDesignHideActionLoader) {
                window.smileDesignHideActionLoader();
            }
            setSubmitReady();
        }
    }
    if (procedureSelect) {
        procedureSelect.addEventListener('change', syncLviStyleVisibility);
        syncLviStyleVisibility();
    }
    photoInputs.forEach(function (photoInput) {
        photoInput.addEventListener('change', function () {
            preparePhotoInput(photoInput);
        });
        const parts = photoParts(photoInput);
        if (parts.replace) parts.replace.addEventListener('click', function () { photoInput.click(); });
    });
    if (mobileRefreshButton) {
        mobileRefreshButton.addEventListener('click', function () {
            refreshMobileUploads(true);
        });
    }
    if (mobileStatusPanel) {
        refreshMobileUploads(false);
        mobilePollTimer = window.setInterval(function () {
            if (!document.hidden) refreshMobileUploads(false);
        }, 5000);
    }
    if (form && progress) form.addEventListener('submit', async function (event) {
        if (preparingCount > 0) {
            event.preventDefault();
            if (window.smileDesignShowActionLoader) {
                window.smileDesignShowActionLoader('Preparing photos...');
            }
            return;
        }
        const hasUnconvertedHeic = photoInputs.some(function (fileInput) {
            const file = fileInput.files && fileInput.files[0];
            return isHeicFile(file);
        });
        if (hasUnconvertedHeic) {
            event.preventDefault();
            for (const fileInput of photoInputs) {
                await preparePhotoInput(fileInput);
            }
            return;
        }
        progress.classList.remove('hidden');
        progress.style.width = '65%';
        if (window.smileDesignShowActionLoader) {
            window.smileDesignShowActionLoader('Creating smile design case...');
        }
        window.setTimeout(function () {
            progress.style.width = '85%';
            if (window.smileDesignShowActionLoader) {
                window.smileDesignShowActionLoader('Analyzing case photo with AI...');
            }
        }, 900);
        window.setTimeout(function () {
            progress.style.width = '95%';
            if (window.smileDesignShowActionLoader) {
                window.smileDesignShowActionLoader('Saving case analysis...');
            }
        }, 6500);
        window.setTimeout(function () {
            progress.classList.add('hidden');
            progress.style.width = '0';
        }, 30000);
    });
})();
</script>
<?php smile_design_render_shell_end(); ?>
