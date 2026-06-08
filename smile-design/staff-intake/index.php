<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

$user = smile_design_internal_boot('Staff Intake');
smile_design_render_shell_start('Staff Intake');
smile_design_page_header('Staff Intake', 'Create a smile case fast with one strong front before photo, then refine details inside the case workspace.');
?>
<form class="grid gap-5 lg:grid-cols-[1fr_0.85fr]" method="POST" enctype="multipart/form-data" action="<?= e(base_url('app/actions/smile_design_staff_intake_submit.php')) ?>" data-sd-staff-intake data-loading-label="Creating case and analyzing photo...">
    <?= csrf_input() ?>
    <div class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm font-semibold">First name<input required name="first_name" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-3"></label>
            <label class="block text-sm font-semibold">Last name<input name="last_name" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-3"></label>
            <label class="block text-sm font-semibold">Phone<input required name="phone" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-3"></label>
            <label class="block text-sm font-semibold">Procedure<select name="procedure_interest" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-3" data-sd-procedure-select><?php foreach (smile_design_procedure_options() as $key => $label): ?><option value="<?= e($label) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
            <label class="block text-sm font-semibold sm:col-span-2">Email <span class="font-normal text-slate-500">(optional)</span><input name="email" type="email" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-3"></label>
            <label class="block text-sm font-semibold" data-sd-lvi-style-field>LVI style <span class="font-normal text-slate-500">(optional)</span><select name="selected_style" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-3"><?php foreach (smile_design_style_options() as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
        </div>

        <details class="mt-4 rounded-md bg-slate-50 p-4">
            <summary class="cursor-pointer text-sm font-semibold text-slate-900">Optional case details</summary>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <label class="block text-sm font-semibold">Consent status<select name="consent_status" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-3"><option value="not_recorded">Not recorded</option><option value="verbal">Verbal consent</option><option value="written">Written consent on file</option></select></label>
            </div>
            <label class="mt-4 block text-sm font-semibold">Optional notes<textarea name="notes" rows="5" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-3"></textarea></label>
        </details>

        <button class="mt-5 w-full rounded-md bg-slate-950 px-5 py-4 text-base font-semibold text-white sm:w-auto" type="submit">Create Smile Design Case</button>
        <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100"><div class="hidden h-full bg-emerald-500" data-sd-upload-progress></div></div>
    </div>

    <div class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-semibold">Staff Photo Upload</p>
        <div data-sd-photo-field>
            <label class="mt-3 flex min-h-44 cursor-pointer flex-col items-center justify-center rounded-md border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center">
                <span class="text-base font-semibold text-slate-900">Front BEFORE photo</span>
                <span class="mt-2 text-sm text-slate-500">JPG, PNG, WebP, HEIC, or HEIF. HEIC files are converted to JPG at full resolution.</span>
                <input required name="before_photo_front" type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif" capture="environment" class="sr-only" data-sd-photo-input data-sd-photo-label="Front">
            </label>
            <img class="mt-4 hidden max-h-[420px] w-full rounded-md object-contain ring-1 ring-slate-200" alt="Selected front photo preview" data-sd-photo-preview>
            <p class="mt-3 hidden rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm leading-6 text-amber-800" data-sd-photo-status></p>
            <button class="mt-3 hidden rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold" type="button" data-sd-replace-photo>Replace photo</button>
        </div>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div data-sd-photo-field>
                <label class="block text-sm font-semibold">Left 45 photo <span class="font-normal text-slate-500">(optional)</span><input name="before_photo_left_45" type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif" capture="environment" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2" data-sd-photo-input data-sd-photo-label="Left 45"></label>
                <img class="mt-3 hidden max-h-56 w-full rounded-md object-contain ring-1 ring-slate-200" alt="Selected left 45 photo preview" data-sd-photo-preview>
                <p class="mt-2 hidden rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-800" data-sd-photo-status></p>
            </div>
            <div data-sd-photo-field>
                <label class="block text-sm font-semibold">Right 45 photo <span class="font-normal text-slate-500">(optional)</span><input name="before_photo_right_45" type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif" capture="environment" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2" data-sd-photo-input data-sd-photo-label="Right 45"></label>
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
    let heicConverterPromise = null;
    let preparingCount = 0;
    function isLipRepositionOnly(value) {
        const text = String(value || '').toLowerCase();
        return (text.includes('lip reposition') || text.includes('gummy smile')) && !text.includes('veneer');
    }
    function syncLviStyleVisibility() {
        const hideStyle = procedureSelect && isLipRepositionOnly(procedureSelect.value);
        if (lviStyleField) lviStyleField.classList.toggle('hidden', !!hideStyle);
        if (lviStyleSelect) {
            lviStyleSelect.disabled = !!hideStyle;
            if (hideStyle) lviStyleSelect.value = 'natural';
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
