<?php
declare(strict_types=1);

function smile_before_after_viewer(?string $beforeUrl, ?string $afterUrl, array $options = []): void
{
    static $assetsPrinted = false;
    $title = (string)($options['title'] ?? 'Smile Preview');
    $requestedMode = strtolower((string)($options['mode'] ?? 'ba'));
    $modeMap = [
        'slider' => 'ba',
        'side' => 'compare',
        'zoom' => 'zoom',
        'before' => 'input',
        'after' => 'result',
        'input' => 'input',
        'result' => 'result',
        'compare' => 'compare',
        'ba' => 'ba',
        'opacity' => 'opacity',
        'video' => 'video',
    ];
    $defaultMode = $modeMap[$requestedMode] ?? 'ba';
    $logoUrl = (string)($options['logo_url'] ?? SMILE_DESIGN_LOGO_URL);
    $showWatermark = array_key_exists('watermark', $options) ? (bool)$options['watermark'] : false;
    $alignment = function_exists('smile_design_normalize_alignment')
        ? smile_design_normalize_alignment((array)($options['alignment'] ?? []))
        : (array)($options['alignment'] ?? []);
    $alignmentEdit = (array)($options['alignment_edit'] ?? []);
    $canEditAlignment = !empty($alignmentEdit);
    $inputGallery = array_values(array_filter((array)($options['input_gallery'] ?? []), static function ($item): bool {
        return is_array($item) && trim((string)($item['url'] ?? '')) !== '';
    }));
    $beforeUrl = $beforeUrl ?: '';
    $afterUrl = $afterUrl ?: '';
    $videoUrl = trim((string)($options['video_url'] ?? ''));
    $videoGenerate = is_array($options['video_generate'] ?? null) ? (array)$options['video_generate'] : [];
    $videoDelete = is_array($options['video_delete'] ?? null) ? (array)$options['video_delete'] : [];
    $hasVideo = $videoUrl !== '';
    $canGenerateVideo = !empty($videoGenerate);
    $canDeleteVideo = !empty($videoDelete);
    $hasAfter = $afterUrl !== '';
    if ($beforeUrl === '' && !empty($inputGallery[0]['url'])) {
        $beforeUrl = (string)$inputGallery[0]['url'];
    }
    $defaultInputLabel = (string)($options['before_label'] ?? 'Original photo');
    if ($inputGallery !== [] && $defaultInputLabel === 'Original photo') {
        $defaultInputLabel = (string)($inputGallery[0]['label'] ?? 'Original photo');
    }
    $defaultPhotoType = trim((string)($options['photo_type'] ?? ($inputGallery[0]['photo_type'] ?? 'front')));
    $zoomPresetForPhotoType = static function (string $photoType): array {
        $normalized = strtolower(trim($photoType));
        return match ($normalized) {
            'front' => ['x' => 50, 'y' => 88, 'scale' => 1.45, 'pan_x' => 0, 'pan_y' => 0],
            'left_45', 'left45' => ['x' => 50, 'y' => 82, 'scale' => 1.38, 'pan_x' => 0, 'pan_y' => 0],
            'right_45', 'right45' => ['x' => 50, 'y' => 82, 'scale' => 1.38, 'pan_x' => 0, 'pan_y' => 0],
            'smile_close_up', 'close_up_smile', 'closeup', 'close_up' => ['x' => 50, 'y' => 70, 'scale' => 1.18, 'pan_x' => 0, 'pan_y' => 0],
            default => ['x' => 50, 'y' => 86, 'scale' => 1.42, 'pan_x' => 0, 'pan_y' => 0],
        };
    };
    $zoomPreset = $zoomPresetForPhotoType($defaultPhotoType);

    if (!$assetsPrinted) {
        $assetsPrinted = true;
        ?>
        <style>
            .sd-viewer-wrap { --sd-before-zoom: 1; --sd-before-x: 0%; --sd-before-y: 0%; --sd-before-rotate: 0deg; --sd-after-zoom: 1; --sd-after-x: 0%; --sd-after-y: 0%; --sd-after-rotate: 0deg; --sd-frame-aspect: 4 / 3; --sd-zoom-x: 50%; --sd-zoom-y: 88%; --sd-zoom-scale: 1.45; --sd-zoom-pan-x: 0%; --sd-zoom-pan-y: 0%; }
            .sd-viewer-shell { display: grid; gap: 12px; }
            .sd-viewer-shell.has-gallery { grid-template-columns: minmax(148px, 180px) minmax(0, 1fr); align-items: start; }
            .sd-viewer { overflow: hidden; border-radius: 8px; background: #050505; color: #fff; }
            .sd-input-gallery { border: 1px solid rgba(255,255,255,.1); border-radius: 8px; background: #050505; padding: 12px; color: #fff; }
            .sd-input-gallery-title { font-size: 11px; font-weight: 800; letter-spacing: .18em; text-transform: uppercase; color: rgba(255,255,255,.6); }
            .sd-input-gallery-list { margin-top: 10px; display: grid; gap: 10px; }
            .sd-input-option { display: grid; gap: 8px; width: 100%; text-align: left; }
            .sd-input-option img { width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; object-fit: cover; border: 1px solid rgba(255,255,255,.14); background: #000; }
            .sd-input-option span { font-size: 12px; font-weight: 700; color: rgba(255,255,255,.82); }
            .sd-input-option[aria-pressed="true"] img { border-color: rgba(255,255,255,.8); box-shadow: 0 0 0 1px rgba(255,255,255,.28); }
            .sd-input-option[aria-pressed="true"] span { color: #fff; }
            .sd-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; border-bottom: 1px solid rgba(255,255,255,.1); background: #050505; padding: 14px 16px; }
            .sd-mode-group { display: flex; flex-wrap: wrap; gap: 8px; }
            .sd-mode-btn { border: 1px solid rgba(255,255,255,.25); border-radius: 6px; padding: 9px 12px; font-size: 12px; font-weight: 700; color: #fff; }
            .sd-mode-btn[aria-pressed="true"] { background: #fff; color: #050505; }
            .sd-frame { position: relative; aspect-ratio: var(--sd-frame-aspect); min-height: 280px; overflow: hidden; background: #000; touch-action: none; }
            .sd-frame img { display: block; width: 100%; height: 100%; object-fit: contain; object-position: center; user-select: none; }
            .sd-focus-mask { position: absolute; inset: 0; z-index: 7; pointer-events: none; background:
                radial-gradient(ellipse 54% 62% at 50% 48%, rgba(255,255,255,0) 0%, rgba(255,255,255,0) 56%, rgba(5,5,5,.16) 72%, rgba(5,5,5,.34) 88%, rgba(5,5,5,.58) 100%),
                linear-gradient(to bottom, rgba(5,5,5,.18), rgba(5,5,5,0) 16%, rgba(5,5,5,0) 82%, rgba(5,5,5,.22)),
                linear-gradient(to right, rgba(5,5,5,.18), rgba(5,5,5,0) 14%, rgba(5,5,5,0) 86%, rgba(5,5,5,.18));
                box-shadow: inset 0 0 0 1px rgba(255,255,255,.04); }
            .sd-align-before { transform: translate(var(--sd-before-x), var(--sd-before-y)) scale(calc(var(--sd-before-zoom) * var(--sd-before-mode-zoom, 1))) rotate(var(--sd-before-rotate)); transform-origin: center; }
            .sd-align-after { transform: translate(var(--sd-after-x), var(--sd-after-y)) scale(calc(var(--sd-after-zoom) * var(--sd-after-mode-zoom, 1))) rotate(var(--sd-after-rotate)); transform-origin: center; }
            .sd-base, .sd-after-layer { position: absolute; inset: 0; overflow: hidden; }
            .sd-after-layer { clip-path: inset(0 50% 0 0); }
            .sd-handle { position: absolute; inset-block: 0; left: 50%; width: 2px; background: #fff; transform: translateX(-1px); z-index: 8; }
            .sd-handle::after { content: ""; position: absolute; left: 50%; top: 50%; width: 34px; height: 34px; border-radius: 999px; border: 2px solid #fff; background: rgba(0,0,0,.55); transform: translate(-50%, -50%); }
            .sd-label-row { display: flex; justify-content: space-between; gap: 12px; padding: 10px 16px; background: #050505; font-size: 12px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: rgba(255,255,255,.72); }
            .sd-watermark { position: absolute; right: 18px; bottom: 18px; z-index: 9; width: auto !important; height: auto !important; min-width: 0; max-width: 140px; max-height: 52px; border-radius: 6px; background: rgba(255,255,255,.82); padding: 7px; opacity: .9; object-fit: contain; }
            .sd-side { display: grid; grid-template-columns: 1fr 1fr; gap: 1px; height: 100%; }
            .sd-side-panel { position: relative; background: #000; overflow: hidden; }
            .sd-side-panel img { width: 100%; height: 100%; object-fit: contain; object-position: center; }
            .sd-zoom-stack { display: grid; gap: 1px; background: rgba(255,255,255,.08); }
            .sd-zoom-panel { background: #000; }
            .sd-zoom-panel-header { display: flex; justify-content: space-between; gap: 12px; padding: 10px 16px; background: #050505; font-size: 12px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: rgba(255,255,255,.72); }
            .sd-zoom-frame { position: relative; aspect-ratio: 16 / 5.2; min-height: 198px; overflow: hidden; background: #000; }
            .sd-zoom-frame img { display: block; width: 100%; height: 100%; object-fit: cover; object-position: var(--sd-zoom-x, 50%) var(--sd-zoom-y, 60%); user-select: none; }
            .sd-zoom-frame .sd-align-before,
            .sd-zoom-frame .sd-align-after { transform-origin: center; }
            .sd-zoom-frame .sd-align-before { transform: translate(calc(var(--sd-before-x) + var(--sd-zoom-pan-x, 0%)), calc(var(--sd-before-y) + var(--sd-zoom-pan-y, 0%))) scale(calc(var(--sd-before-zoom) * var(--sd-zoom-scale, 1))) rotate(var(--sd-before-rotate)); }
            .sd-zoom-frame .sd-align-after { transform: translate(calc(var(--sd-after-x) + var(--sd-zoom-pan-x, 0%)), calc(var(--sd-after-y) + var(--sd-zoom-pan-y, 0%))) scale(calc(var(--sd-after-zoom) * var(--sd-zoom-scale, 1))) rotate(var(--sd-after-rotate)); }
            .sd-opacity-shell { position: relative; height: 100%; background: #000; }
            .sd-opacity-shell img { position: absolute; inset: 0; }
            .sd-opacity-base { z-index: 1; }
            .sd-opacity-overlay { z-index: 2; opacity: var(--sd-opacity, .55); }
            .sd-video-panel { display: grid; min-height: 420px; place-items: center; background: #050505; padding: 18px; }
            .sd-video-player { width: 100%; max-height: min(68vh, 720px); aspect-ratio: 16 / 9; border-radius: 8px; background: #000; object-fit: contain; }
            .sd-video-empty { display: grid; max-width: 620px; gap: 16px; justify-items: center; text-align: center; color: rgba(255,255,255,.72); }
            .sd-video-empty strong { color: #fff; font-size: 18px; }
            .sd-video-empty button { border-radius: 6px; background: #fff; color: #050505; padding: 10px 16px; font-size: 13px; font-weight: 800; }
            .sd-video-ready { display: grid; width: 100%; gap: 12px; justify-items: center; }
            .sd-video-actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; }
            .sd-video-actions form { margin: 0; }
            .sd-video-actions button { border-radius: 6px; border: 1px solid rgba(255,255,255,.22); background: rgba(255,255,255,.08); color: #fff; padding: 9px 13px; font-size: 12px; font-weight: 800; }
            .sd-video-actions button.sd-danger { border-color: rgba(248,113,113,.42); color: #fecaca; }
            .sd-placeholder { display: flex; height: 100%; min-height: 280px; align-items: center; justify-content: center; border: 1px dashed rgba(255,255,255,.35); color: rgba(255,255,255,.75); text-align: center; padding: 24px; }
            .sd-hidden { display: none !important; }
            .sd-align-tools { margin-top: 12px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; color: #0f172a; padding: 14px; }
            .sd-align-tools label { display: grid; gap: 6px; font-size: 12px; font-weight: 700; color: #334155; }
            .sd-align-tools input[type="range"] { width: 100%; }
            .sd-inline-range { display: flex; align-items: center; gap: 12px; font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: rgba(255,255,255,.78); }
            .sd-inline-range input[type="range"] { width: 180px; }
            @media (max-width: 640px) {
                .sd-viewer-shell.has-gallery { grid-template-columns: 1fr; }
                .sd-input-gallery-list { grid-template-columns: repeat(4, minmax(78px, 1fr)); }
                .sd-input-option span { font-size: 11px; }
                .sd-toolbar { align-items: flex-start; }
                .sd-mode-group { width: 100%; flex-wrap: nowrap; overflow-x: auto; overflow-y: hidden; padding-bottom: 2px; -ms-overflow-style: none; scrollbar-width: none; }
                .sd-mode-group::-webkit-scrollbar { display: none; }
                .sd-mode-btn { flex: 0 0 auto; white-space: nowrap; }
                .sd-mode-btn[data-sd-mode="compare"] { display: none; }
                .sd-frame { aspect-ratio: 9 / 16; min-height: 0; touch-action: pan-y; }
                .sd-side { grid-template-columns: 1fr; }
                .sd-zoom-frame { aspect-ratio: 4 / 2.6; min-height: 0; }
                .sd-watermark { right: 12px; bottom: 12px; max-width: 110px; max-height: 44px; }
                .sd-inline-range { width: 100%; flex-wrap: wrap; }
                .sd-inline-range input[type="range"] { width: 100%; }
            }
        </style>
        <script>
        (function () {
            function aspectToCss(value) {
                const raw = String(value || '4:3').trim();
                const parts = raw.split(':');
                if (parts.length !== 2) return '4 / 3';
                const width = parseFloat(parts[0]);
                const height = parseFloat(parts[1]);
                if (!Number.isFinite(width) || !Number.isFinite(height) || width <= 0 || height <= 0) {
                    return '4 / 3';
                }
                return width + ' / ' + height;
            }
            function zoomPresetForPhotoType(value) {
                const photoType = String(value || '').trim().toLowerCase();
                if (photoType === 'front') return { x: 50, y: 88, scale: 1.45, panX: 0, panY: 0 };
                if (photoType === 'left_45' || photoType === 'left45') {
                    return { x: 50, y: 82, scale: 1.38, panX: 0, panY: 0 };
                }
                if (photoType === 'right_45' || photoType === 'right45') {
                    return { x: 50, y: 82, scale: 1.38, panX: 0, panY: 0 };
                }
                if (photoType === 'smile_close_up' || photoType === 'close_up_smile' || photoType === 'closeup' || photoType === 'close_up') {
                    return { x: 50, y: 70, scale: 1.18, panX: 0, panY: 0 };
                }
                return { x: 50, y: 86, scale: 1.42, panX: 0, panY: 0 };
            }
            function applyZoomPreset(wrap, photoType) {
                if (!wrap) return;
                const preset = zoomPresetForPhotoType(photoType);
                wrap.style.setProperty('--sd-zoom-x', preset.x + '%');
                wrap.style.setProperty('--sd-zoom-y', preset.y + '%');
                wrap.style.setProperty('--sd-zoom-scale', String(preset.scale));
                wrap.style.setProperty('--sd-zoom-pan-x', (preset.panX || 0) + '%');
                wrap.style.setProperty('--sd-zoom-pan-y', (preset.panY || 0) + '%');
            }
            function setSlider(viewer, percent) {
                percent = Math.max(0, Math.min(100, percent));
                const after = viewer.querySelector('[data-sd-after-layer]');
                const handle = viewer.querySelector('[data-sd-handle]');
                if (after) after.style.clipPath = 'inset(0 ' + (100 - percent) + '% 0 0)';
                if (handle) handle.style.left = percent + '%';
            }
            function setMode(viewer, mode) {
                viewer.querySelectorAll('[data-sd-mode-panel]').forEach(function (panel) {
                    panel.classList.toggle('sd-hidden', panel.dataset.sdModePanel !== mode);
                });
                viewer.querySelectorAll('[data-sd-mode]').forEach(function (button) {
                    button.setAttribute('aria-pressed', button.dataset.sdMode === mode ? 'true' : 'false');
                });
            }
            document.addEventListener('click', function (event) {
                const alignToggle = event.target.closest('[data-sd-align-toggle]');
                if (alignToggle) {
                    const wrap = alignToggle.closest('[data-sd-viewer-wrap]');
                    const panel = wrap ? wrap.querySelector('[data-sd-align-panel]') : null;
                    if (panel) panel.classList.toggle('sd-hidden');
                    return;
                }
                const button = event.target.closest('[data-sd-mode]');
                if (!button) return;
                const viewer = button.closest('[data-sd-viewer]');
                if (viewer) setMode(viewer, button.dataset.sdMode);
            });
            document.addEventListener('click', function (event) {
                const option = event.target.closest('[data-sd-before-option]');
                if (!option) return;
                const wrap = option.closest('[data-sd-viewer-wrap]');
                if (!wrap) return;
                const url = option.getAttribute('data-url') || '';
                const label = option.getAttribute('data-label') || 'Original photo';
                const afterUrl = option.getAttribute('data-after-url') || '';
                const afterLabel = option.getAttribute('data-after-label') || 'After';
                wrap.querySelectorAll('[data-sd-before-option]').forEach(function (item) {
                    item.setAttribute('aria-pressed', item === option ? 'true' : 'false');
                });
                wrap.querySelectorAll('[data-sd-before-image]').forEach(function (img) {
                    img.setAttribute('src', url);
                });
                wrap.querySelectorAll('[data-sd-before-label]').forEach(function (node) {
                    node.textContent = label;
                });
                applyZoomPreset(wrap, option.getAttribute('data-photo-type') || '');
                wrap.querySelectorAll('[data-sd-after-image]').forEach(function (img) {
                    if (afterUrl) {
                        img.setAttribute('src', afterUrl);
                        img.classList.remove('sd-hidden');
                    } else {
                        img.removeAttribute('src');
                        img.classList.add('sd-hidden');
                    }
                });
                wrap.querySelectorAll('[data-sd-after-placeholder]').forEach(function (node) {
                    node.classList.toggle('sd-hidden', !!afterUrl);
                });
                wrap.querySelectorAll('[data-sd-after-layer], [data-sd-handle]').forEach(function (node) {
                    node.classList.toggle('sd-hidden', !afterUrl);
                });
                wrap.querySelectorAll('[data-sd-after-label]').forEach(function (node) {
                    node.textContent = afterUrl ? afterLabel : 'After pending';
                });
                const form = wrap.querySelector('[data-sd-align-panel]');
                if (form) {
                    ['before_photo_id', 'after_version_id', 'photo_type'].forEach(function (name) {
                        const input = form.querySelector('[name="' + name + '"]');
                        const attrName = 'data-' + name.replace(/_/g, '-');
                        if (input && option.hasAttribute(attrName)) input.value = option.getAttribute(attrName) || '';
                    });
                }
                let nextAlignment = null;
                const alignmentJson = option.getAttribute('data-alignment') || '';
                if (alignmentJson) {
                    try { nextAlignment = JSON.parse(alignmentJson); } catch (error) { nextAlignment = null; }
                }
                if (nextAlignment) {
                    const map = {
                        before_x: ['--sd-before-x', '%'],
                        before_y: ['--sd-before-y', '%'],
                        before_zoom: ['--sd-before-zoom', ''],
                        after_x: ['--sd-after-x', '%'],
                        after_y: ['--sd-after-y', '%'],
                        after_zoom: ['--sd-after-zoom', ''],
                        rotation: ['--sd-before-rotate', 'deg']
                    };
                    Object.keys(map).forEach(function (name) {
                        if (typeof nextAlignment[name] === 'undefined') return;
                        const value = parseFloat(nextAlignment[name]);
                        if (!Number.isFinite(value)) return;
                        const cssVar = map[name][0];
                        const unit = map[name][1];
                        wrap.style.setProperty(cssVar, value + unit);
                        if (name === 'rotation') wrap.style.setProperty('--sd-after-rotate', value + unit);
                        if (form) {
                            const input = form.querySelector('[name="' + name + '"]');
                            const output = form.querySelector('[data-sd-align-output="' + name + '"]');
                            if (input) input.value = value;
                            if (output) output.textContent = String(value);
                        }
                    });
                    if (typeof nextAlignment.crop_aspect_ratio !== 'undefined' && form) {
                        const select = form.querySelector('[name="crop_aspect_ratio"]');
                        if (select) select.value = String(nextAlignment.crop_aspect_ratio || '4:3');
                    }
                    wrap.style.setProperty('--sd-frame-aspect', aspectToCss(nextAlignment.crop_aspect_ratio || '4:3'));
                }
            });
            document.addEventListener('pointerdown', function (event) {
                const frame = event.target.closest('[data-sd-slider-frame]');
                if (!frame) return;
                const viewer = frame.closest('[data-sd-viewer]');
                function update(e) {
                    const rect = frame.getBoundingClientRect();
                    setSlider(viewer, ((e.clientX - rect.left) / rect.width) * 100);
                }
                update(event);
                frame.setPointerCapture(event.pointerId);
                frame.addEventListener('pointermove', update);
                frame.addEventListener('pointerup', function stop() {
                    frame.removeEventListener('pointermove', update);
                }, { once: true });
            });
            document.addEventListener('click', function (event) {
                const button = event.target.closest('[data-sd-zoom]');
                if (!button) return;
                const viewer = button.closest('[data-sd-viewer]');
                const img = viewer ? viewer.querySelector('[data-sd-zoom-img]') : null;
                if (!img) return;
                let current = parseFloat(img.style.getPropertyValue('--sd-zoom') || '1');
                if (button.dataset.sdZoom === 'in') current += .15;
                if (button.dataset.sdZoom === 'out') current -= .15;
                if (button.dataset.sdZoom === 'reset') current = 1;
                img.style.setProperty('--sd-zoom', Math.max(1, Math.min(2.2, current)).toString());
            });
            document.addEventListener('input', function (event) {
                const input = event.target.closest('[data-sd-align-input]');
                if (!input) return;
                const wrap = input.closest('[data-sd-viewer-wrap]');
                if (!wrap) return;
                const value = parseFloat(input.value || '0');
                const unit = input.dataset.sdAlignUnit || '';
                wrap.style.setProperty(input.dataset.sdAlignInput, value + unit);
                const output = wrap.querySelector('[data-sd-align-output="' + input.name + '"]');
                if (output) output.textContent = input.value;
            });
            document.addEventListener('change', function (event) {
                const select = event.target.closest('[name="crop_aspect_ratio"]');
                if (!select) return;
                const wrap = select.closest('[data-sd-viewer-wrap]');
                if (!wrap) return;
                wrap.style.setProperty('--sd-frame-aspect', aspectToCss(select.value || '4:3'));
            });
            document.addEventListener('input', function (event) {
                const input = event.target.closest('[data-sd-opacity-input]');
                if (!input) return;
                const viewer = input.closest('[data-sd-viewer]');
                if (!viewer) return;
                viewer.style.setProperty('--sd-opacity', (Math.max(0, Math.min(100, parseFloat(input.value || '55'))) / 100).toString());
                const output = viewer.querySelector('[data-sd-opacity-output]');
                if (output) output.textContent = input.value + '%';
            });
            document.addEventListener('submit', function (event) {
                const form = event.target.closest('[data-loading-label]');
                if (!form) return;
                const button = form.querySelector('button[type="submit"]');
                if (!button) return;
                button.disabled = true;
                button.textContent = form.getAttribute('data-loading-label') || 'Working...';
            });
        })();
        </script>
        <?php
    }
    $beforeZoom = (float)($alignment['before_zoom'] ?? 1);
    $beforeX = (float)($alignment['before_x'] ?? 0);
    $beforeY = (float)($alignment['before_y'] ?? 0);
    $afterZoom = (float)($alignment['after_zoom'] ?? 1);
    $afterX = (float)($alignment['after_x'] ?? 0);
    $afterY = (float)($alignment['after_y'] ?? 0);
    $rotation = (float)($alignment['rotation'] ?? 0);
    $aspectRatio = trim((string)($alignment['crop_aspect_ratio'] ?? '4:3'));
    $aspectParts = explode(':', $aspectRatio, 2);
    $aspectCss = '4 / 3';
    if (count($aspectParts) === 2) {
        $aspectWidth = max(1, (float)$aspectParts[0]);
        $aspectHeight = max(1, (float)$aspectParts[1]);
        $aspectCss = rtrim(rtrim(number_format($aspectWidth, 4, '.', ''), '0'), '.') . ' / ' . rtrim(rtrim(number_format($aspectHeight, 4, '.', ''), '0'), '.');
    }
    $style = sprintf(
        '--sd-before-zoom:%s;--sd-before-x:%s%%;--sd-before-y:%s%%;--sd-before-rotate:%sdeg;--sd-after-zoom:%s;--sd-after-x:%s%%;--sd-after-y:%s%%;--sd-after-rotate:%sdeg;--sd-frame-aspect:%s;--sd-zoom-x:%s%%;--sd-zoom-y:%s%%;--sd-zoom-scale:%s;--sd-zoom-pan-x:%s%%;--sd-zoom-pan-y:%s%%;',
        e((string)$beforeZoom),
        e((string)$beforeX),
        e((string)$beforeY),
        e((string)$rotation),
        e((string)$afterZoom),
        e((string)$afterX),
        e((string)$afterY),
        e((string)$rotation),
        e($aspectCss),
        e((string)$zoomPreset['x']),
        e((string)$zoomPreset['y']),
        e((string)$zoomPreset['scale']),
        e((string)($zoomPreset['pan_x'] ?? 0)),
        e((string)($zoomPreset['pan_y'] ?? 0))
    );
    ?>
    <div class="sd-viewer-wrap" data-sd-viewer-wrap style="<?= $style ?>">
    <div class="sd-viewer-shell <?= $inputGallery !== [] ? 'has-gallery' : '' ?>">
    <?php if ($inputGallery !== []): ?>
        <aside class="sd-input-gallery">
            <p class="sd-input-gallery-title">Input</p>
            <div class="sd-input-gallery-list">
                <?php foreach ($inputGallery as $index => $item): ?>
                    <?php
                    $galleryAlignment = (array)($item['alignment'] ?? []);
                    if ($galleryAlignment && function_exists('smile_design_normalize_alignment')) {
                        $galleryAlignment = smile_design_normalize_alignment($galleryAlignment);
                    }
                    $galleryAlignmentJson = $galleryAlignment ? (string)json_encode($galleryAlignment, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
                    ?>
                    <button type="button" class="sd-input-option" data-sd-before-option data-url="<?= e((string)$item['url']) ?>" data-label="<?= e((string)($item['label'] ?? 'Original photo')) ?>" data-after-url="<?= e((string)($item['after_url'] ?? '')) ?>" data-after-label="<?= e((string)($item['after_label'] ?? 'After')) ?>" data-before-photo-id="<?= e((string)($item['before_photo_id'] ?? '')) ?>" data-after-version-id="<?= e((string)($item['after_version_id'] ?? '')) ?>" data-photo-type="<?= e((string)($item['photo_type'] ?? '')) ?>" data-alignment="<?= e($galleryAlignmentJson) ?>" aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>">
                        <img src="<?= e((string)$item['url']) ?>" alt="<?= e((string)($item['label'] ?? 'Input')) ?>">
                        <span><?= e((string)($item['label'] ?? 'Input')) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </aside>
    <?php endif; ?>
    <div class="sd-viewer border border-white/10 shadow-sm" data-sd-viewer style="--sd-opacity:.55">
        <div class="sd-toolbar">
            <div>
                <p class="text-xs uppercase tracking-[0.22em] text-white/55">Smile Case Viewer</p>
                <h3 class="text-base font-semibold text-white"><?= e($title) ?></h3>
            </div>
            <div class="sd-mode-group">
                <button type="button" class="sd-mode-btn" data-sd-mode="input" aria-pressed="<?= $defaultMode === 'input' ? 'true' : 'false' ?>">Input</button>
                <button type="button" class="sd-mode-btn" data-sd-mode="result" aria-pressed="<?= $defaultMode === 'result' ? 'true' : 'false' ?>">Result</button>
                <button type="button" class="sd-mode-btn" data-sd-mode="compare" aria-pressed="<?= $defaultMode === 'compare' ? 'true' : 'false' ?>">Compare</button>
                <button type="button" class="sd-mode-btn" data-sd-mode="ba" aria-pressed="<?= $defaultMode === 'ba' ? 'true' : 'false' ?>">B/A</button>
                <button type="button" class="sd-mode-btn" data-sd-mode="opacity" aria-pressed="<?= $defaultMode === 'opacity' ? 'true' : 'false' ?>">Opacity</button>
                <button type="button" class="sd-mode-btn" data-sd-mode="zoom" aria-pressed="<?= $defaultMode === 'zoom' ? 'true' : 'false' ?>">Zoom</button>
                <?php if ($hasVideo || $canGenerateVideo): ?><button type="button" class="sd-mode-btn" data-sd-mode="video" aria-pressed="<?= $defaultMode === 'video' ? 'true' : 'false' ?>">Video</button><?php endif; ?>
            </div>
        </div>
        <div data-sd-mode-panel="input" class="<?= $defaultMode === 'input' ? '' : 'sd-hidden' ?>">
            <div class="sd-label-row"><span>Input</span><span data-sd-before-label><?= e($defaultInputLabel) ?></span></div>
            <div class="sd-frame">
                <?php if ($beforeUrl !== ''): ?><img class="sd-base sd-align-before" data-sd-before-image src="<?= e($beforeUrl) ?>" alt="Before photo"><?php else: ?><div class="sd-placeholder">Before photo will appear here.</div><?php endif; ?>
                <div class="sd-focus-mask" aria-hidden="true"></div>
                <?php if ($showWatermark): ?><img class="sd-watermark" src="<?= e($logoUrl) ?>" alt="Elite Smiles"><?php endif; ?>
            </div>
        </div>
        <div data-sd-mode-panel="result" class="<?= $defaultMode === 'result' ? '' : 'sd-hidden' ?>">
            <div class="sd-label-row"><span>Result</span><span data-sd-after-label><?= $hasAfter ? 'Selected version' : 'After pending' ?></span></div>
            <div class="sd-frame">
                <img class="sd-align-after <?= $hasAfter ? '' : 'sd-hidden' ?>" data-sd-after-image src="<?= e($hasAfter ? $afterUrl : '') ?>" alt="Result image">
                <div class="sd-placeholder <?= $hasAfter ? 'sd-hidden' : '' ?>" data-sd-after-placeholder>Result image will appear here.</div>
                <div class="sd-focus-mask" aria-hidden="true"></div>
                <?php if ($showWatermark): ?><img class="sd-watermark" src="<?= e($logoUrl) ?>" alt="Elite Smiles"><?php endif; ?>
            </div>
        </div>
        <div data-sd-mode-panel="ba" class="<?= $defaultMode === 'ba' ? '' : 'sd-hidden' ?>">
            <div class="sd-label-row"><span data-sd-before-label><?= e($defaultInputLabel) ?></span><span data-sd-after-label><?= $hasAfter ? 'After' : 'After pending' ?></span></div>
            <div class="sd-frame" data-sd-slider-frame>
                <?php if ($beforeUrl !== ''): ?><img class="sd-base sd-align-before" data-sd-before-image src="<?= e($beforeUrl) ?>" alt="Before photo"><?php else: ?><div class="sd-placeholder">Before photo will appear here.</div><?php endif; ?>
                <div class="sd-after-layer <?= $hasAfter ? '' : 'sd-hidden' ?>" data-sd-after-layer><img class="sd-align-after" data-sd-after-image src="<?= e($hasAfter ? $afterUrl : '') ?>" alt="After preview"></div><div class="sd-handle <?= $hasAfter ? '' : 'sd-hidden' ?>" data-sd-handle></div>
                <div class="sd-placeholder <?= $hasAfter ? 'sd-hidden' : '' ?>" data-sd-after-placeholder>After image pending.</div>
                <div class="sd-focus-mask" aria-hidden="true"></div>
                <?php if ($showWatermark): ?><img class="sd-watermark" src="<?= e($logoUrl) ?>" alt="Elite Smiles"><?php endif; ?>
            </div>
        </div>
        <div data-sd-mode-panel="compare" class="<?= $defaultMode === 'compare' ? '' : 'sd-hidden' ?>">
            <div class="sd-label-row"><span data-sd-before-label><?= e($defaultInputLabel) ?></span><span data-sd-after-label><?= $hasAfter ? 'Result' : 'Result pending' ?></span></div>
            <div class="sd-frame">
                <div class="sd-side">
                    <div class="sd-side-panel"><?= $beforeUrl !== '' ? '<img class="sd-align-before" data-sd-before-image src="' . e($beforeUrl) . '" alt="Before photo">' : '<div class="sd-placeholder">Before photo will appear here.</div>' ?></div>
                    <div class="sd-side-panel"><img class="sd-align-after <?= $hasAfter ? '' : 'sd-hidden' ?>" data-sd-after-image src="<?= e($hasAfter ? $afterUrl : '') ?>" alt="After preview"><div class="sd-placeholder <?= $hasAfter ? 'sd-hidden' : '' ?>" data-sd-after-placeholder>After image pending.</div></div>
                </div>
                <div class="sd-focus-mask" aria-hidden="true"></div>
                <?php if ($showWatermark): ?><img class="sd-watermark" src="<?= e($logoUrl) ?>" alt="Elite Smiles"><?php endif; ?>
            </div>
        </div>
        <div data-sd-mode-panel="opacity" class="<?= $defaultMode === 'opacity' ? '' : 'sd-hidden' ?>">
            <div class="sd-label-row">
                <span>Opacity Overlay</span>
                <span class="sd-inline-range">
                    <span data-sd-opacity-output>55%</span>
                    <input type="range" min="0" max="100" step="1" value="55" data-sd-opacity-input>
                </span>
            </div>
            <div class="sd-frame">
                <?php if ($beforeUrl !== ''): ?>
                    <div class="sd-opacity-shell">
                        <img class="sd-opacity-base sd-align-before" data-sd-before-image src="<?= e($beforeUrl) ?>" alt="Before photo">
                        <img class="sd-opacity-overlay sd-align-after <?= $hasAfter ? '' : 'sd-hidden' ?>" data-sd-after-image src="<?= e($hasAfter ? $afterUrl : '') ?>" alt="After overlay">
                        <div class="sd-placeholder <?= $hasAfter ? 'sd-hidden' : '' ?>" data-sd-after-placeholder>After image pending.</div>
                    </div>
                <?php else: ?>
                    <div class="sd-placeholder">Before photo will appear here.</div>
                <?php endif; ?>
                <div class="sd-focus-mask" aria-hidden="true"></div>
                <?php if ($showWatermark): ?><img class="sd-watermark" src="<?= e($logoUrl) ?>" alt="Elite Smiles"><?php endif; ?>
            </div>
        </div>
        <div data-sd-mode-panel="zoom" class="<?= $defaultMode === 'zoom' ? '' : 'sd-hidden' ?>">
            <div class="sd-zoom-stack">
                <div class="sd-zoom-panel">
                    <div class="sd-zoom-panel-header"><span>Before Zoom</span><span data-sd-before-label><?= e($defaultInputLabel) ?></span></div>
                    <div class="sd-zoom-frame">
                        <?php if ($beforeUrl !== ''): ?><img class="sd-align-before" data-sd-before-image src="<?= e($beforeUrl) ?>" alt="Before zoom"><?php else: ?><div class="sd-placeholder">Before photo will appear here.</div><?php endif; ?>
                    </div>
                </div>
                <div class="sd-zoom-panel">
                    <div class="sd-zoom-panel-header"><span>After Zoom</span><span data-sd-after-label><?= $hasAfter ? 'After' : 'After pending' ?></span></div>
                    <div class="sd-zoom-frame">
                        <img class="sd-align-after <?= $hasAfter ? '' : 'sd-hidden' ?>" data-sd-after-image src="<?= e($hasAfter ? $afterUrl : '') ?>" alt="After zoom">
                        <div class="sd-placeholder <?= $hasAfter ? 'sd-hidden' : '' ?>" data-sd-after-placeholder>After image pending.</div>
                        <?php if ($showWatermark): ?><img class="sd-watermark" src="<?= e($logoUrl) ?>" alt="Elite Smiles"><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php if ($hasVideo || $canGenerateVideo): ?>
            <div data-sd-mode-panel="video" class="<?= $defaultMode === 'video' ? '' : 'sd-hidden' ?>">
                <div class="sd-label-row"><span>Smile Reveal Video</span><span><?= $hasVideo ? 'Ready' : 'Generate from selected afters' ?></span></div>
                <div class="sd-video-panel">
                    <?php if ($hasVideo): ?>
                        <div class="sd-video-ready">
                            <video class="sd-video-player" src="<?= e($videoUrl) ?>" controls playsinline preload="metadata"></video>
                            <?php if ($canGenerateVideo || $canDeleteVideo): ?>
                                <div class="sd-video-actions">
                                    <?php if ($canGenerateVideo): ?>
                                        <form method="POST" action="<?= e((string)($videoGenerate['action'] ?? '')) ?>" data-loading-label="<?= e((string)($videoGenerate['loading_label'] ?? 'Re-generating reveal video...')) ?>">
                                            <?= csrf_input() ?>
                                            <?php foreach ((array)($videoGenerate['hidden'] ?? []) as $name => $value): ?>
                                                <input type="hidden" name="<?= e((string)$name) ?>" value="<?= e((string)$value) ?>">
                                            <?php endforeach; ?>
                                            <button type="submit">Re-generate Video</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($canDeleteVideo): ?>
                                        <form method="POST" action="<?= e((string)($videoDelete['action'] ?? '')) ?>" onsubmit="return confirm('Delete this reveal video?');">
                                            <?= csrf_input() ?>
                                            <?php foreach ((array)($videoDelete['hidden'] ?? []) as $name => $value): ?>
                                                <input type="hidden" name="<?= e((string)$name) ?>" value="<?= e((string)$value) ?>">
                                            <?php endforeach; ?>
                                            <button class="sd-danger" type="submit">Delete Video</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($canGenerateVideo): ?>
                        <form class="sd-video-empty" method="POST" action="<?= e((string)($videoGenerate['action'] ?? '')) ?>" data-loading-label="<?= e((string)($videoGenerate['loading_label'] ?? 'Generating reveal video...')) ?>">
                            <?= csrf_input() ?>
                            <?php foreach ((array)($videoGenerate['hidden'] ?? []) as $name => $value): ?>
                                <input type="hidden" name="<?= e((string)$name) ?>" value="<?= e((string)$value) ?>">
                            <?php endforeach; ?>
                            <strong>Generate patient reveal video</strong>
                            <span>Uses the selected Front, Left 45, and Right 45 after images to create the doctor presentation video.</span>
                            <button type="submit">Generate Video</button>
                        </form>
                    <?php else: ?>
                        <div class="sd-video-empty"><strong>Video pending</strong><span>Generate all three selected after angles first.</span></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($canEditAlignment): ?>
        <div class="mt-3">
            <button type="button" data-sd-align-toggle class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800">Adjust Alignment</button>
            <form data-sd-align-panel class="sd-align-tools sd-hidden" method="POST" action="<?= e(base_url('app/actions/smile_design_alignment_update.php')) ?>">
                <?= csrf_input() ?>
                <?php foreach (['pair_type', 'case_id', 'before_photo_id', 'after_version_id', 'real_pair_id', 'photo_type', 'return_url'] as $field): ?>
                    <?php if (array_key_exists($field, $alignmentEdit)): ?><input type="hidden" name="<?= e($field) ?>" value="<?= e((string)$alignmentEdit[$field]) ?>"><?php endif; ?>
                <?php endforeach; ?>
                <div class="grid gap-4 md:grid-cols-2">
                    <?php foreach ([
                        ['before_x', 'Before left/right', $beforeX, -50, 50, 1, '--sd-before-x', '%'],
                        ['before_y', 'Before up/down', $beforeY, -50, 50, 1, '--sd-before-y', '%'],
                        ['before_zoom', 'Before zoom', $beforeZoom, 0.5, 2.5, 0.05, '--sd-before-zoom', ''],
                        ['after_x', 'After left/right', $afterX, -50, 50, 1, '--sd-after-x', '%'],
                        ['after_y', 'After up/down', $afterY, -50, 50, 1, '--sd-after-y', '%'],
                        ['after_zoom', 'After zoom', $afterZoom, 0.5, 2.5, 0.05, '--sd-after-zoom', ''],
                    ] as [$name, $label, $value, $min, $max, $step, $cssVar, $unit]): ?>
                        <label><?= e($label) ?> <span data-sd-align-output="<?= e($name) ?>"><?= e((string)$value) ?></span>
                            <input data-sd-align-input="<?= e($cssVar) ?>" data-sd-align-unit="<?= e($unit) ?>" name="<?= e($name) ?>" type="range" min="<?= e((string)$min) ?>" max="<?= e((string)$max) ?>" step="<?= e((string)$step) ?>" value="<?= e((string)$value) ?>">
                        </label>
                    <?php endforeach; ?>
                </div>
                <label class="mt-4">Crop aspect ratio
                    <select name="crop_aspect_ratio" class="rounded-md border border-slate-300 px-3 py-2">
                        <?php foreach (['4:3', '1:1', '3:4', '16:9'] as $aspect): ?><option value="<?= e($aspect) ?>" <?= (string)($alignment['crop_aspect_ratio'] ?? '4:3') === $aspect ? 'selected' : '' ?>><?= e($aspect) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-semibold text-white" name="alignment_action" value="save" type="submit">Save Alignment</button>
                    <button class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold" name="alignment_action" value="reset" type="submit">Reset</button>
                    <button class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold" data-sd-align-toggle type="button">Cancel</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
    </div>
    </div>
    <?php
}
