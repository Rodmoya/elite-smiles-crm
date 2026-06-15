<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

/** @var array<int, mixed>|null $user */
dental_models_internal_boot('Dental Model Builder');

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

$patientName = trim((string)($model['patient_name'] ?? ''));
$caseId = (int)($model['smile_design_case_id'] ?? 0);
$status = trim((string)($model['processing_status'] ?? 'original'));
$fileSize = (int)($model['file_size'] ?? 0);
$uploadedAt = trim((string)($model['created_at'] ?? ''));
$modelFilePath = dental_models_resolve_model_file($model);
if ($modelFilePath === null || !is_file($modelFilePath)) {
    flash_set('error', 'That STL file is missing from secure storage. Re-upload this case after cleanup.');
    redirect(base_url('dental-models'));
}
$downloadUrl = base_url('dental-models/' . (int)$model['id'] . '/download-original');
$viewerUrl = base_url('app/actions/dental_model_file.php?id=' . (int)$model['id'] . '&download=0');

dental_models_render_shell_start('Dental Model Builder');
?>

<section class="grid gap-6 xl:grid-cols-[1fr_360px]">
    <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm lg:p-6">
        <div class="mb-4 flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Interactive 3D View</p>
                <h2 class="mt-2 text-xl font-semibold text-slate-900">Model Preview</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    Staff-only preview workspace for .stl inspection and cut-plane planning. No mesh editing is performed in V1.
                </p>
            </div>
            <a
                href="<?= e($downloadUrl) ?>"
                class="shrink-0 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white"
            >
                Download Original STL
            </a>
        </div>
        <div id="dental-viewer-canvas-wrap" class="relative overflow-hidden rounded-2xl border border-slate-200 bg-slate-900">
            <div id="dental-viewer-canvas" class="h-[55vh] w-full min-h-[420px]"></div>
            <div id="dental-viewer-status" class="absolute inset-0 hidden items-center justify-center bg-slate-900/80 text-sm text-white">
                Loading model viewer...
            </div>
        </div>
        <p class="mt-3 text-xs text-slate-500">
            V1 preview mode: model rendering and movement only. Permanent processing actions are coming in V2.
        </p>
    </div>

    <aside class="space-y-5">
        <section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Model info</p>
            <div class="mt-3 space-y-2 text-sm">
                <p><span class="font-semibold text-slate-900">Patient:</span> <?= e($patientName !== '' ? $patientName : 'Unspecified patient') ?></p>
                <p><span class="font-semibold text-slate-900">Case:</span> <?= $caseId > 0 ? '#' . e((string)$caseId) : 'Unlinked' ?></p>
                <p><span class="font-semibold text-slate-900">Status:</span> <?= e($status) ?></p>
                <p><span class="font-semibold text-slate-900">File size:</span> <?= e(dental_models_format_bytes($fileSize)) ?></p>
                <p><span class="font-semibold text-slate-900">Uploaded:</span> <?= e($uploadedAt) ?></p>
            </div>
        </section>

        <section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Modification Tools</p>
            <div class="mt-4 space-y-4">
                <button
                    type="button"
                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
                    disabled
                    title="Coming in V2"
                >
                    Execute Base Extrude - Coming in V2.
                </button>

                <button
                    type="button"
                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
                    disabled
                    title="Coming in V2"
                >
                    Add Drain Holes - Coming in V2.
                </button>
            </div>
        </section>

        <section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Print Prep Controls</p>
            <div class="mt-4 space-y-4">
                <label class="block text-sm text-slate-700">
                    <span class="font-semibold">Cut plane height</span>
                    <div class="mt-2 flex items-center gap-3">
                        <input id="cut-plane-slider" type="range" min="0" max="100" value="50" class="w-full">
                        <span id="cut-plane-height-label" class="w-16 rounded-lg bg-slate-100 px-2 py-1 text-center text-xs font-semibold text-slate-700">50%</span>
                    </div>
                </label>

                <label class="block text-sm text-slate-700">
                    <span class="font-semibold">Wall thickness</span>
                    <input type="range" disabled class="mt-2 w-full" min="0" max="10" value="3">
                    <p class="mt-1 text-xs text-slate-500">Coming in V2</p>
                </label>
            </div>
        </section>
    </aside>
</section>

<section class="mt-6 rounded-[2rem] border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
    <p class="font-semibold">V1 note</p>
    <p class="mt-1 leading-6">
        This is a V1 viewer/preview workspace. Real mesh processing operations (cut, extrude, hole placement, STL export transforms)
        are intentionally deferred and will land in V2 after secure processing worker integration.
    </p>
</section>

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

    if (!viewer || !window.THREE || !window.THREE.OrbitControls || !window.THREE.STLLoader) {
        if (status) {
            status.classList.remove('hidden');
            status.textContent = '3D viewer libraries are not available yet.';
        }
        return;
    }

    const modelUrl = <?= json_encode($viewerUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

    const scene = new THREE.Scene();
    scene.background = new THREE.Color(0xeef2f7);

    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: false });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    viewer.appendChild(renderer.domElement);

    const camera = new THREE.PerspectiveCamera(45, 1, 0.1, 5000);
    const controls = new window.THREE.OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.08;

    scene.add(new THREE.AmbientLight(0xffffff, 0.65));

    const keyLight = new THREE.DirectionalLight(0xffffff, 1);
    keyLight.position.set(8, 14, 8);
    scene.add(keyLight);

    const fillLight = new THREE.DirectionalLight(0xe8f0ff, 0.45);
    fillLight.position.set(-9, 6, -8);
    scene.add(fillLight);

    scene.add(new THREE.HemisphereLight(0xf7faff, 0x4b5563, 0.4));

    const group = new THREE.Group();
    scene.add(group);

    const loader = new window.THREE.STLLoader();
    const geometry = new THREE.PlaneGeometry(1, 1);
    const planeMaterial = new THREE.MeshBasicMaterial({
        color: 0x60a5fa,
        transparent: true,
        opacity: 0.28,
        side: THREE.DoubleSide,
        depthWrite: false,
    });
    const cutPlane = new THREE.Mesh(geometry, planeMaterial);
    cutPlane.rotation.x = Math.PI / 2;
    cutPlane.visible = false;
    group.add(cutPlane);

    let minY = -10;
    let maxY = 10;

    function fitViewport() {
        const width = Math.max(320, viewer.clientWidth);
        const height = Math.max(240, viewer.clientHeight);
        camera.aspect = width / height;
        camera.updateProjectionMatrix();
        renderer.setSize(width, height);
    }

    function updatePlane(percent) {
        const value = Math.max(0, Math.min(100, Number(percent) || 0));
        const y = minY + ((maxY - minY) * (value / 100));
        cutPlane.position.y = y;
        if (planeLabel) {
            planeLabel.textContent = `${value}%`;
        }
        renderer.render(scene, camera);
    }

    function animate() {
        requestAnimationFrame(animate);
        controls.update();
        renderer.render(scene, camera);
    }

    function positionCamera(bounds) {
        const size = bounds.getSize(new window.THREE.Vector3());
        const radius = Math.max(size.x, size.y, size.z, 1);
        camera.position.set(radius * 1.2, radius * 1.0, radius * 1.45);
        controls.target.set(0, 0, 0);
        controls.update();
    }

    if (status) status.classList.remove('hidden');

    loader.load(
        modelUrl,
        function (geo) {
            if (status) status.classList.add('hidden');
            geo.computeBoundingBox();
            if (!geo.boundingBox) {
                return;
            }

            const bbox = new window.THREE.Box3().setFromBufferAttribute(geo.attributes.position);
            const modelSize = bbox.getSize(new window.THREE.Vector3());
            const center = bbox.getCenter(new window.THREE.Vector3());

            const normalMat = new window.THREE.MeshStandardMaterial({
                color: 0x6b7280,
                roughness: 0.5,
                metalness: 0.12,
            });

            const mesh = new window.THREE.Mesh(geo, normalMat);
            mesh.position.sub(center);
            group.add(mesh);

            const width = Math.max(modelSize.x, 1);
            const depth = Math.max(modelSize.z, 1);
            cutPlane.geometry.dispose();
            cutPlane.geometry = new window.THREE.PlaneGeometry(width * 1.2, depth * 1.2);
            cutPlane.visible = true;

            minY = -Math.max(modelSize.y * 0.55, 0.1);
            maxY = Math.max(modelSize.y * 0.55, 0.1);
            const boundsLocal = new window.THREE.Box3().setFromObject(mesh);
            positionCamera(boundsLocal);
            fitViewport();
            updatePlane(50);
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

    window.addEventListener('resize', function () {
        fitViewport();
    });

    fitViewport();
})();
</script>

<?php
dental_models_render_shell_end();
