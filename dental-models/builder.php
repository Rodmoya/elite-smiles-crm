<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$GLOBALS['dentalModelsFullscreenShell'] = true;
/** @var array<int, mixed>|null $user */
dental_models_internal_boot('Dental Model Studio');

$modelId = (int) get('id', 0);
if ($modelId <= 0) {
    flash_set('error', 'No dental model selected.');
    redirect(base_url('dental-models'));
}

$model = dental_models_find($modelId);
if (!$model) {
    flash_set('error', 'That dental model could not be found.');
    redirect(base_url('dental-models'));
}

$patientName = trim((string) ($model['patient_name'] ?? ''));
$caseId = (int) ($model['smile_design_case_id'] ?? 0);
$status = trim((string) ($model['processing_status'] ?? 'original'));
$fileSize = (int) ($model['file_size'] ?? 0);
$uploadedAt = trim((string) ($model['created_at'] ?? ''));

$action = (string) post('action', '');
if (is_post()) {
    require_csrf();

    if ($action === 'mark_missing' && $modelId > 0) {
        if (dental_models_update_processing_status($modelId, 'missing_file')) {
            flash_set('success', 'Model marked as missing file.');
        } else {
            flash_set('error', 'Could not update this model status.');
        }
        redirect(base_url('dental-models/' . $modelId . '/builder'));
    }

    if ($action === 'archive_record' && $modelId > 0) {
        if (dental_models_update_processing_status($modelId, 'archived')) {
            flash_set('success', 'Model archived for cleanup.');
        } else {
            flash_set('error', 'Could not archive this model record.');
        }
        redirect(base_url('dental-models/' . $modelId . '/builder'));
    }
}

$modelFilePath = dental_models_resolve_model_file($model);
$missingFromStorage = ($modelFilePath === null || !is_file($modelFilePath));
if ($missingFromStorage && $status !== 'missing_file' && $status !== 'archived') {
    dental_models_update_processing_status($modelId, 'missing_file');
    $status = 'missing_file';
}

$statusLabel = $missingFromStorage
    ? 'Missing File'
    : (($status === 'preview_ready' || $status === 'original') ? dental_models_status_label($status) : dental_models_status_label('preview_ready'));

$downloadUrl = base_url('dental-models/' . (int) $model['id'] . '/download-original');
$viewerUrl = base_url('app/actions/dental_model_file.php?id=' . (int) $model['id'] . '&download=0');
$recentModels = dental_models_list(10);

dental_models_render_shell_start('Dental Model Studio');
?>

<div class="flex h-full min-h-0 flex-1 flex-col gap-3 p-3 md:p-4 lg:p-5">
    <header class="shrink-0 rounded-[1.75rem] border border-slate-800/80 bg-slate-900/95 px-4 py-4 shadow-2xl shadow-slate-950/40 backdrop-blur">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">
                    <span>3D Design Studio</span>
                    <span class="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-2.5 py-1 text-[10px] tracking-[0.18em] text-emerald-200">Fullscreen workspace</span>
                </div>
                <h1 class="mt-2 truncate text-2xl font-semibold tracking-tight text-white md:text-3xl">
                    <?= e($patientName !== '' ? $patientName : 'Unspecified patient') ?>
                </h1>
                <p class="mt-2 max-w-4xl text-sm leading-6 text-slate-300">
                    Open scans, inspect models, create patient files, and prep print-ready edits from a single editor-style workspace.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <details class="group relative">
                    <summary class="list-none cursor-pointer rounded-2xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm font-semibold text-slate-100 shadow-sm transition hover:bg-slate-700">
                        File
                    </summary>
                    <div class="absolute right-0 z-20 mt-2 w-72 overflow-hidden rounded-3xl border border-slate-700 bg-slate-950 p-2 shadow-2xl shadow-slate-950/40">
                        <a class="block rounded-2xl px-4 py-3 text-sm text-slate-100 transition hover:bg-slate-800" href="<?= e(base_url('dental-models')) ?>">Open Library</a>
                        <a class="block rounded-2xl px-4 py-3 text-sm text-slate-100 transition hover:bg-slate-800" href="<?= e(base_url('dental-models/new')) ?>">Import STL</a>
                        <a class="block rounded-2xl px-4 py-3 text-sm text-slate-100 transition hover:bg-slate-800" href="<?= e(base_url('smile-design/staff-intake')) ?>">Create Patient File</a>
                        <?php if (!$missingFromStorage): ?>
                            <a class="block rounded-2xl px-4 py-3 text-sm text-slate-100 transition hover:bg-slate-800" href="<?= e($downloadUrl) ?>">Download Original STL</a>
                        <?php endif; ?>
                        <button type="button" class="w-full cursor-not-allowed rounded-2xl px-4 py-3 text-left text-sm text-slate-400" disabled>Export STL / 3MF</button>
                    </div>
                </details>

                <details class="group relative">
                    <summary class="list-none cursor-pointer rounded-2xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm font-semibold text-slate-100 shadow-sm transition hover:bg-slate-700">
                        View
                    </summary>
                    <div class="absolute right-0 z-20 mt-2 w-72 overflow-hidden rounded-3xl border border-slate-700 bg-slate-950 p-2 shadow-2xl shadow-slate-950/40">
                        <button type="button" data-view-preset="fit" class="view-preset block w-full rounded-2xl px-4 py-3 text-left text-sm text-slate-100 transition hover:bg-slate-800">Fit to screen</button>
                        <button type="button" data-view-preset="top" class="view-preset block w-full rounded-2xl px-4 py-3 text-left text-sm text-slate-100 transition hover:bg-slate-800">Top view</button>
                        <button type="button" data-view-preset="front" class="view-preset block w-full rounded-2xl px-4 py-3 text-left text-sm text-slate-100 transition hover:bg-slate-800">Front view</button>
                        <button type="button" data-view-preset="right" class="view-preset block w-full rounded-2xl px-4 py-3 text-left text-sm text-slate-100 transition hover:bg-slate-800">Right view</button>
                        <button type="button" id="wireframe-toggle" class="block w-full rounded-2xl px-4 py-3 text-left text-sm text-slate-100 transition hover:bg-slate-800">Toggle wireframe</button>
                    </div>
                </details>

                <details class="group relative">
                    <summary class="list-none cursor-pointer rounded-2xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm font-semibold text-slate-100 shadow-sm transition hover:bg-slate-700">
                        Patient
                    </summary>
                    <div class="absolute right-0 z-20 mt-2 w-80 overflow-hidden rounded-3xl border border-slate-700 bg-slate-950 p-2 shadow-2xl shadow-slate-950/40">
                        <a class="block rounded-2xl px-4 py-3 text-sm text-slate-100 transition hover:bg-slate-800" href="<?= e(base_url('smile-design/staff-intake')) ?>">New Patient Case</a>
                        <a class="block rounded-2xl px-4 py-3 text-sm text-slate-100 transition hover:bg-slate-800" href="<?= e(base_url('patient-experience')) ?>">Patient Experience</a>
                        <a class="block rounded-2xl px-4 py-3 text-sm text-slate-100 transition hover:bg-slate-800" href="<?= e(base_url('leads.php')) ?>">Open Lead Record</a>
                    </div>
                </details>
            </div>
        </div>
    </header>

    <div class="grid min-h-0 flex-1 gap-3 xl:grid-cols-[280px_minmax(0,1fr)_340px]">
        <aside class="min-h-0 overflow-hidden rounded-[1.75rem] border border-slate-800 bg-slate-900/90 shadow-2xl shadow-slate-950/30">
            <div class="border-b border-slate-800 px-4 py-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">File Browser</p>
                        <h2 class="mt-1 text-lg font-semibold text-white">Recent scans</h2>
                    </div>
                    <a class="rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-100 transition hover:bg-slate-700" href="<?= e(base_url('dental-models/new')) ?>">
                        Import
                    </a>
                </div>
            </div>

            <div class="max-h-[38vh] overflow-y-auto p-2 xl:h-full xl:max-h-none">
                <?php foreach ($recentModels as $recent): ?>
                    <?php
                        $recentId = (int) ($recent['id'] ?? 0);
                        $recentPatient = trim((string) ($recent['patient_name'] ?? ''));
                        $recentStatus = strtolower(trim((string) ($recent['processing_status'] ?? 'original')));
                        $recentLabel = dental_models_status_label($recentStatus === '' ? 'original' : $recentStatus);
                        $isActive = $recentId === (int) $model['id'];
                    ?>
                    <a
                        href="<?= e(base_url('dental-models/' . $recentId . '/builder')) ?>"
                        class="<?= $isActive ? 'block rounded-3xl border border-emerald-400/30 bg-emerald-400/10 p-4 ring-1 ring-emerald-300/30' : 'block rounded-3xl border border-slate-800 bg-slate-950/70 p-4 transition hover:border-slate-700 hover:bg-slate-900' ?>"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-white"><?= e($recentPatient !== '' ? $recentPatient : 'Unspecified patient') ?></p>
                                <p class="mt-1 text-xs text-slate-400">Record #<?= e((string) $recentId) ?></p>
                            </div>
                            <span class="shrink-0 rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] <?= e(dental_models_status_badge_class($recentStatus)) ?>">
                                <?= e($recentLabel) ?>
                            </span>
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-3 text-xs text-slate-400">
                            <span><?= e(dental_models_format_bytes((int) ($recent['file_size'] ?? 0))) ?></span>
                            <span><?= e(substr((string) ($recent['created_at'] ?? ''), 0, 16)) ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </aside>

        <section class="min-h-0 overflow-hidden rounded-[1.75rem] border border-slate-800 bg-slate-900/90 shadow-2xl shadow-slate-950/30">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800 px-4 py-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Workspace</p>
                    <h2 class="mt-1 text-lg font-semibold text-white">Model viewport</h2>
                </div>
                <div class="flex flex-wrap gap-2 text-xs font-semibold">
                    <button type="button" class="view-preset rounded-2xl border border-slate-700 bg-slate-800 px-3 py-2 text-slate-100 transition hover:bg-slate-700" data-view-preset="fit">Reset</button>
                    <button type="button" class="view-preset rounded-2xl border border-slate-700 bg-slate-800 px-3 py-2 text-slate-100 transition hover:bg-slate-700" data-view-preset="top">Top</button>
                    <button type="button" class="view-preset rounded-2xl border border-slate-700 bg-slate-800 px-3 py-2 text-slate-100 transition hover:bg-slate-700" data-view-preset="front">Front</button>
                    <button type="button" class="view-preset rounded-2xl border border-slate-700 bg-slate-800 px-3 py-2 text-slate-100 transition hover:bg-slate-700" data-view-preset="right">Right</button>
                </div>
            </div>

            <?php if ($missingFromStorage): ?>
                <div class="flex h-full min-h-[420px] items-center justify-center p-6">
                    <div class="max-w-xl rounded-[1.75rem] border border-amber-500/20 bg-amber-500/10 p-6 text-amber-100">
                        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-amber-200">Storage alert</p>
                        <h3 class="mt-2 text-2xl font-semibold text-white">The original STL is missing.</h3>
                        <p class="mt-3 text-sm leading-6 text-amber-100/90">
                            This record cannot be previewed until the STL is re-uploaded. You can return to the library, upload a new scan, or mark the record for cleanup.
                        </p>
                        <div class="mt-5 flex flex-wrap gap-2">
                            <a class="rounded-2xl bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-900" href="<?= e(base_url('dental-models')) ?>">Back to library</a>
                            <a class="rounded-2xl border border-slate-700 bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white" href="<?= e(base_url('dental-models/new')) ?>">Upload new STL</a>
                            <form method="post" action="<?= e(base_url('dental-models/' . $modelId . '/builder')) ?>">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="mark_missing">
                                <button class="rounded-2xl border border-slate-700 bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white" type="submit">Mark missing</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div id="dental-viewer-canvas-wrap" class="relative h-[52vh] min-h-[420px] overflow-hidden bg-[#0c1220]">
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(96,165,250,0.18),transparent_36%),linear-gradient(180deg,rgba(15,23,42,0.9),rgba(2,6,23,0.98))]"></div>
                    <div id="dental-viewer-canvas" class="relative z-10 h-full w-full"></div>
                    <div class="pointer-events-none absolute inset-x-4 top-4 z-20 flex flex-wrap gap-2">
                        <span class="rounded-full border border-white/10 bg-black/30 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-200">3D scan workspace</span>
                        <span class="rounded-full border border-white/10 bg-black/30 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-200"><?= e($statusLabel) ?></span>
                        <span class="rounded-full border border-white/10 bg-black/30 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-200"><?= e(dental_models_format_bytes($fileSize)) ?></span>
                    </div>
                    <div id="dental-viewer-status" class="absolute inset-0 z-20 hidden items-center justify-center bg-slate-950/80 text-sm text-white">
                        Loading model viewer...
                    </div>
                </div>
            <?php endif; ?>

            <div class="grid gap-3 border-t border-slate-800 p-4 md:grid-cols-3">
                <div class="rounded-3xl border border-slate-800 bg-slate-950/70 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Model Info</p>
                    <div class="mt-3 space-y-2 text-sm text-slate-200">
                        <p><span class="font-semibold text-white">Patient:</span> <?= e($patientName !== '' ? $patientName : 'Unspecified patient') ?></p>
                        <p><span class="font-semibold text-white">Case:</span> <?= $caseId > 0 ? '#' . e((string) $caseId) : 'Unlinked' ?></p>
                        <p><span class="font-semibold text-white">Status:</span> <?= e($statusLabel) ?></p>
                        <p><span class="font-semibold text-white">Uploaded:</span> <?= e($uploadedAt) ?></p>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-800 bg-slate-950/70 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Selection Tools</p>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        <button type="button" class="rounded-2xl border border-slate-700 bg-slate-900 px-3 py-2.5 text-sm font-semibold text-slate-200 transition hover:bg-slate-800">Select</button>
                        <button type="button" class="rounded-2xl border border-slate-700 bg-slate-900 px-3 py-2.5 text-sm font-semibold text-slate-200 transition hover:bg-slate-800">Move</button>
                        <button type="button" class="rounded-2xl border border-slate-700 bg-slate-900 px-3 py-2.5 text-sm font-semibold text-slate-200 transition hover:bg-slate-800">Rotate</button>
                        <button type="button" class="rounded-2xl border border-slate-700 bg-slate-900 px-3 py-2.5 text-sm font-semibold text-slate-200 transition hover:bg-slate-800">Measure</button>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-800 bg-slate-950/70 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Patient Actions</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a class="rounded-2xl bg-emerald-500 px-4 py-2.5 text-sm font-semibold text-slate-950" href="<?= e(base_url('smile-design/staff-intake')) ?>">New Patient Case</a>
                        <a class="rounded-2xl border border-slate-700 bg-slate-900 px-4 py-2.5 text-sm font-semibold text-slate-100" href="<?= e(base_url('dental-models/new')) ?>">Import STL</a>
                    </div>
                    <p class="mt-3 text-xs leading-6 text-slate-400">
                        Connect scans to a patient file before editing so print prep and case tracking stay aligned.
                    </p>
                </div>
            </div>
        </section>

        <aside class="min-h-0 space-y-3">
            <section class="rounded-[1.75rem] border border-slate-800 bg-slate-900/90 p-4 shadow-2xl shadow-slate-950/30">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Inspector</p>
                <h2 class="mt-1 text-lg font-semibold text-white">Current file</h2>
                <div class="mt-3 space-y-2 text-sm text-slate-200">
                    <p><span class="font-semibold text-white">File:</span> <?= e((string) ($model['original_filename'] ?? 'model.stl')) ?></p>
                    <p><span class="font-semibold text-white">Record:</span> #<?= e((string) $modelId) ?></p>
                    <p><span class="font-semibold text-white">Size:</span> <?= e(dental_models_format_bytes($fileSize)) ?></p>
                </div>
            </section>

            <section class="rounded-[1.75rem] border border-slate-800 bg-slate-900/90 p-4 shadow-2xl shadow-slate-950/30">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Print Prep</p>
                <div class="mt-4 space-y-4">
                    <label class="block text-sm text-slate-200">
                        <span class="font-semibold text-white">Cut plane height</span>
                        <div class="mt-2 flex items-center gap-3">
                            <input id="cut-plane-slider" type="range" min="0" max="100" value="50" class="w-full accent-sky-400">
                            <span id="cut-plane-height-label" class="w-16 rounded-lg border border-slate-700 bg-slate-950 px-2 py-1 text-center text-xs font-semibold text-slate-100">50%</span>
                        </div>
                    </label>
                    <label class="block text-sm text-slate-200">
                        <span class="font-semibold text-white">Wall thickness</span>
                        <input type="range" disabled class="mt-2 w-full accent-sky-400" min="0" max="10" value="3">
                        <p class="mt-1 text-xs text-slate-400">Coming in V2</p>
                    </label>
                </div>
            </section>

            <section class="rounded-[1.75rem] border border-slate-800 bg-slate-900/90 p-4 shadow-2xl shadow-slate-950/30">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Action Queue</p>
                <div class="mt-3 space-y-2 text-sm text-slate-200">
                    <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-3 py-2">Open scan</div>
                    <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-3 py-2">Trim model</div>
                    <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-3 py-2">Send to printer prep</div>
                </div>
            </section>

            <section class="rounded-[1.75rem] border border-amber-500/20 bg-amber-500/10 p-4 text-sm text-amber-100">
                <p class="font-semibold text-white">V2 direction</p>
                <p class="mt-1 leading-6">
                    This workspace is now laid out like editor software. The next step is real tooth-level mesh tools, object selection, and export transforms.
                </p>
            </section>
        </aside>
    </div>
</div>

<?php if (!$missingFromStorage): ?>
    <script src="https://cdn.jsdelivr.net/npm/three@0.161.0/build/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.161.0/examples/js/controls/OrbitControls.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.161.0/examples/js/loaders/STLLoader.js"></script>
    <script>
    (function () {
        'use strict';

        const viewer = document.getElementById('dental-viewer-canvas');
        const planeSlider = document.getElementById('cut-plane-slider');
        const planeLabel = document.getElementById('cut-plane-height-label');
        const status = document.getElementById('dental-viewer-status');
        const wireframeToggle = document.getElementById('wireframe-toggle');
        const viewButtons = Array.from(document.querySelectorAll('.view-preset'));

        if (!viewer || !window.THREE || !window.THREE.OrbitControls || !window.THREE.STLLoader) {
            if (status) {
                status.classList.remove('hidden');
                status.textContent = '3D viewer libraries are not available yet.';
            }
            return;
        }

        const modelUrl = <?= json_encode($viewerUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

        const scene = new THREE.Scene();
        scene.background = new THREE.Color(0x0b1120);

        const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: false });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        renderer.outputColorSpace = THREE.SRGBColorSpace;
        viewer.appendChild(renderer.domElement);

        const camera = new THREE.PerspectiveCamera(40, 1, 0.1, 5000);
        const controls = new window.THREE.OrbitControls(camera, renderer.domElement);
        controls.enableDamping = true;
        controls.dampingFactor = 0.08;
        controls.target.set(0, 0, 0);

        scene.add(new THREE.AmbientLight(0xffffff, 0.62));

        const keyLight = new THREE.DirectionalLight(0xffffff, 1.1);
        keyLight.position.set(8, 14, 8);
        scene.add(keyLight);

        const fillLight = new THREE.DirectionalLight(0xe8f0ff, 0.5);
        fillLight.position.set(-9, 6, -8);
        scene.add(fillLight);

        scene.add(new THREE.HemisphereLight(0xf7faff, 0x4b5563, 0.45));
        scene.add(new THREE.GridHelper(240, 24, 0x2f3b56, 0x1f2937));
        scene.add(new THREE.AxesHelper(60));

        const group = new THREE.Group();
        scene.add(group);

        const loader = new window.THREE.STLLoader();
        const cutPlaneGeometry = new THREE.PlaneGeometry(1, 1);
        const cutPlaneMaterial = new THREE.MeshBasicMaterial({
            color: 0x60a5fa,
            transparent: true,
            opacity: 0.26,
            side: THREE.DoubleSide,
            depthWrite: false,
        });
        const cutPlane = new THREE.Mesh(cutPlaneGeometry, cutPlaneMaterial);
        cutPlane.rotation.x = Math.PI / 2;
        cutPlane.visible = false;
        group.add(cutPlane);

        let minY = -10;
        let maxY = 10;
        let mesh = null;
        let meshMaterial = null;

        function fitViewport() {
            const width = Math.max(320, viewer.clientWidth);
            const height = Math.max(240, viewer.clientHeight);
            camera.aspect = width / height;
            camera.updateProjectionMatrix();
            renderer.setSize(width, height, false);
        }

        function renderFrame() {
            renderer.render(scene, camera);
        }

        function updatePlane(percent) {
            const value = Math.max(0, Math.min(100, Number(percent) || 0));
            const y = minY + ((maxY - minY) * (value / 100));
            cutPlane.position.y = y;
            if (planeLabel) {
                planeLabel.textContent = `${value}%`;
            }
            renderFrame();
        }

        function setWireframe(enabled) {
            if (!meshMaterial) {
                return;
            }
            meshMaterial.wireframe = enabled;
            meshMaterial.needsUpdate = true;
            renderFrame();
        }

        function fitToBounds(bounds) {
            const size = bounds.getSize(new window.THREE.Vector3());
            const center = bounds.getCenter(new window.THREE.Vector3());
            const radius = Math.max(size.x, size.y, size.z, 1);
            camera.position.set(center.x + radius * 1.15, center.y + radius * 0.95, center.z + radius * 1.35);
            controls.target.copy(center);
            controls.update();
            minY = center.y - Math.max(size.y * 0.55, 0.1);
            maxY = center.y + Math.max(size.y * 0.55, 0.1);
            cutPlane.geometry.dispose();
            cutPlane.geometry = new window.THREE.PlaneGeometry(Math.max(size.x, 1) * 1.2, Math.max(size.z, 1) * 1.2);
            cutPlane.visible = true;
            updatePlane(50);
        }

        function setViewPreset(mode) {
            if (!mesh) {
                return;
            }

            const bounds = new window.THREE.Box3().setFromObject(mesh);
            const center = bounds.getCenter(new window.THREE.Vector3());
            const size = bounds.getSize(new window.THREE.Vector3());
            const radius = Math.max(size.x, size.y, size.z, 1);

            if (mode === 'top') {
                camera.position.set(center.x, center.y + radius * 1.9, center.z + 0.01);
            } else if (mode === 'front') {
                camera.position.set(center.x, center.y + radius * 0.25, center.z + radius * 2.0);
            } else if (mode === 'right') {
                camera.position.set(center.x + radius * 2.0, center.y + radius * 0.25, center.z);
            } else {
                camera.position.set(center.x + radius * 1.15, center.y + radius * 0.95, center.z + radius * 1.35);
            }

            controls.target.copy(center);
            controls.update();
            renderFrame();
        }

        function animate() {
            requestAnimationFrame(animate);
            controls.update();
            renderer.render(scene, camera);
        }

        if (status) {
            status.classList.remove('hidden');
        }

        loader.load(
            modelUrl,
            function (geo) {
                if (status) {
                    status.classList.add('hidden');
                }

                geo.computeBoundingBox();
                if (!geo.boundingBox) {
                    return;
                }

                const bounds = geo.boundingBox.clone();
                const center = bounds.getCenter(new window.THREE.Vector3());

                meshMaterial = new window.THREE.MeshStandardMaterial({
                    color: 0xbfc8d4,
                    roughness: 0.42,
                    metalness: 0.08,
                });

                mesh = new window.THREE.Mesh(geo, meshMaterial);
                mesh.position.sub(center);
                group.add(mesh);

                const localBounds = new window.THREE.Box3().setFromObject(mesh);
                fitToBounds(localBounds);
                fitViewport();
                animate();
            },
            function () {
                if (status) {
                    status.classList.remove('hidden');
                    status.textContent = 'Loading STL model...';
                }
            },
            function () {
                if (status) {
                    status.classList.remove('hidden');
                    status.textContent = 'Could not load the STL model. Verify this is a valid STL.';
                }
            }
        );

        if (planeSlider) {
            planeSlider.addEventListener('input', function (event) {
                const target = event.target;
                if (!(target instanceof HTMLInputElement)) {
                    return;
                }
                updatePlane(target.value);
            });
        }

        if (wireframeToggle) {
            let wireframeOn = false;
            wireframeToggle.addEventListener('click', function () {
                wireframeOn = !wireframeOn;
                setWireframe(wireframeOn);
                wireframeToggle.textContent = wireframeOn ? 'Wireframe on' : 'Toggle wireframe';
            });
        }

        viewButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const preset = button.getAttribute('data-view-preset') || 'fit';
                setViewPreset(preset);
            });
        });

        window.addEventListener('resize', function () {
            fitViewport();
        });

        fitViewport();
    })();
    </script>
<?php endif; ?>

<?php
dental_models_render_shell_end();
