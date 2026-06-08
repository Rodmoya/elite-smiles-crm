<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
$user = smile_design_internal_boot('Diagnostics');
if (is_post() && post('action') === 'run_schema_check') {
    require_csrf();
    if (function_exists('auth_has_role') && !auth_has_role('admin')) {
        http_response_code(403);
        exit('403 Forbidden');
    }
    smile_design_ensure_schema();
    flash_set('success', 'Schema check completed.');
    redirect(base_url('smile-design/diagnostics'));
}
$diagnosticError = '';
try {
    $health = smile_design_health();
} catch (Throwable $e) {
    $diagnosticError = 'One or more diagnostics failed. Check storage/logs for details.';
    if (function_exists('esm_log')) {
        esm_log('smile_design', 'Diagnostics failed', ['message' => $e->getMessage()]);
    }
    $health = [
        'database' => false,
        'tables' => array_fill_keys(smile_design_required_tables(), false),
        'storage_path' => smile_design_private_root(),
        'storage_exists' => is_dir(smile_design_private_root()),
        'storage_writable' => is_writable(smile_design_private_root()),
        'photo_endpoint' => base_url('app/actions/smile_design_photo.php'),
        'gd_available' => extension_loaded('gd'),
        'max_upload_size' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'allowed_types' => ['JPG', 'PNG', 'WebP'],
        'token_system' => defined('APP_KEY') && APP_KEY !== '',
        'manual_after_upload_enabled' => false,
        'alignment_schema_ok' => false,
        'cases' => 0,
        'after_versions' => 0,
        'real_result_cases' => 0,
        'real_result_pairs' => 0,
        'lvi_samples' => 0,
        'sample_cases' => 0,
        'preview_links' => 0,
        'saved_alignments' => 0,
        'approved_preview_cases' => 0,
        'recent_audit_events' => [],
    ];
}
smile_design_render_shell_start('Diagnostics');
smile_design_page_header('Diagnostics', 'Internal health check for Smile Design Engine.');
?>
<?php if ($diagnosticError !== ''): ?><div class="mb-5 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"><?= e($diagnosticError) ?></div><?php endif; ?>
<div class="grid gap-5 xl:grid-cols-[1fr_0.9fr]">
    <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">System</h2>
            <form method="POST"><?= csrf_input() ?><input type="hidden" name="action" value="run_schema_check"><button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-semibold text-white">Run Schema Check</button></form>
        </div>
        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
            <div class="rounded-md bg-slate-50 p-3"><dt class="text-slate-500">Database</dt><dd class="font-semibold"><?= $health['database'] ? 'Connected' : 'Unavailable' ?></dd></div>
            <div class="rounded-md bg-slate-50 p-3"><dt class="text-slate-500">Private storage exists</dt><dd class="font-semibold"><?= $health['storage_exists'] ? 'Yes' : 'No' ?></dd></div>
            <div class="rounded-md bg-slate-50 p-3"><dt class="text-slate-500">Private storage writable</dt><dd class="font-semibold"><?= $health['storage_writable'] ? 'Yes' : 'No' ?></dd></div>
            <div class="rounded-md bg-slate-50 p-3"><dt class="text-slate-500">GD available</dt><dd class="font-semibold"><?= $health['gd_available'] ? 'Yes' : 'No' ?></dd></div>
            <div class="rounded-md bg-slate-50 p-3"><dt class="text-slate-500">Max upload</dt><dd class="font-semibold"><?= e((string)$health['max_upload_size']) ?> / post <?= e((string)$health['post_max_size']) ?></dd></div>
            <div class="rounded-md bg-slate-50 p-3"><dt class="text-slate-500">Allowed types</dt><dd class="font-semibold"><?= e(implode(', ', $health['allowed_types'])) ?></dd></div>
            <div class="rounded-md bg-slate-50 p-3"><dt class="text-slate-500">Token system</dt><dd class="font-semibold"><?= $health['token_system'] ? 'Configured' : 'APP_KEY missing' ?></dd></div>
            <div class="rounded-md bg-slate-50 p-3"><dt class="text-slate-500">Photo endpoint</dt><dd class="truncate font-semibold"><?= e((string)$health['photo_endpoint']) ?></dd></div>
            <div class="rounded-md bg-slate-50 p-3"><dt class="text-slate-500">Manual after upload</dt><dd class="font-semibold"><?= !empty($health['manual_after_upload_enabled']) ? 'Enabled' : 'Disabled' ?></dd></div>
            <div class="rounded-md bg-slate-50 p-3"><dt class="text-slate-500">Alignment metadata</dt><dd class="font-semibold"><?= !empty($health['alignment_schema_ok']) ? 'Ready' : 'Missing' ?></dd></div>
            <div class="rounded-md bg-slate-50 p-3"><dt class="text-slate-500">Smile cases</dt><dd class="font-semibold"><?= e((string)($health['cases'] ?? 0)) ?></dd></div>
            <div class="rounded-md bg-slate-50 p-3"><dt class="text-slate-500">After versions</dt><dd class="font-semibold"><?= e((string)($health['after_versions'] ?? 0)) ?></dd></div>
            <div class="rounded-md bg-slate-50 p-3"><dt class="text-slate-500">Real result cases</dt><dd class="font-semibold"><?= e((string)($health['real_result_cases'] ?? 0)) ?></dd></div>
            <div class="rounded-md bg-slate-50 p-3"><dt class="text-slate-500">Real result photo pairs</dt><dd class="font-semibold"><?= e((string)($health['real_result_pairs'] ?? 0)) ?></dd></div>
            <div class="rounded-md bg-slate-50 p-3"><dt class="text-slate-500">LVI samples</dt><dd class="font-semibold"><?= e((string)($health['lvi_samples'] ?? 0)) ?></dd></div>
            <div class="rounded-md bg-slate-50 p-3"><dt class="text-slate-500">Sample cases</dt><dd class="font-semibold"><?= e((string)($health['sample_cases'] ?? 0)) ?></dd></div>
            <div class="rounded-md bg-slate-50 p-3"><dt class="text-slate-500">Preview links</dt><dd class="font-semibold"><?= e((string)($health['preview_links'] ?? 0)) ?></dd></div>
            <div class="rounded-md bg-slate-50 p-3"><dt class="text-slate-500">Saved alignments</dt><dd class="font-semibold"><?= e((string)($health['saved_alignments'] ?? 0)) ?></dd></div>
            <div class="rounded-md bg-slate-50 p-3"><dt class="text-slate-500">Approved preview cases</dt><dd class="font-semibold"><?= e((string)($health['approved_preview_cases'] ?? 0)) ?></dd></div>
        </dl>
    </section>
    <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold">Required Tables</h2>
        <div class="mt-4 grid gap-2 text-sm">
            <?php foreach ($health['tables'] as $table => $present): ?><div class="flex items-center justify-between rounded-md bg-slate-50 px-3 py-2"><span><?= e($table) ?></span><span class="font-semibold <?= $present ? 'text-emerald-700' : 'text-red-700' ?>"><?= $present ? 'Present' : 'Missing' ?></span></div><?php endforeach; ?>
        </div>
    </section>
</div>
<section class="mt-5 rounded-md border border-slate-200 bg-white p-5 shadow-sm">
    <h2 class="text-lg font-semibold">Readiness Checklist</h2>
    <div class="mt-4 grid gap-2 text-sm md:grid-cols-2">
        <?php foreach ([
            'Manual after upload enabled' => !empty($health['manual_after_upload_enabled']),
            'AFTER version schema OK' => !empty($health['tables']['smile_after_versions']),
            'Alignment metadata/table OK' => !empty($health['alignment_schema_ok']),
            'LVI sample upload path OK' => !empty($health['tables']['lvi_sample_images']) && !empty($health['storage_writable']),
            'Real Results upload path OK' => !empty($health['tables']['real_result_photo_pairs']) && !empty($health['storage_writable']),
            'Preview link readiness' => !empty($health['tables']['smile_preview_links']) && !empty($health['token_system']),
            'Browser upload QA checklist visible' => true,
            'OpenAI image provider configured' => defined('OPENAI_API_KEY') && trim((string) OPENAI_API_KEY) !== '',
        ] as $label => $ok): ?>
            <div class="flex items-center justify-between rounded-md bg-slate-50 px-3 py-2"><span><?= e($label) ?></span><span class="font-semibold <?= $ok ? 'text-emerald-700' : 'text-red-700' ?>"><?= $ok ? 'Ready' : 'Needs attention' ?></span></div>
        <?php endforeach; ?>
    </div>
</section>
<section class="mt-5 rounded-md border border-slate-200 bg-white p-5 shadow-sm">
    <h2 class="text-lg font-semibold">Recent Audit Events</h2>
    <div class="mt-4 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="text-left text-slate-500"><th class="py-2">Event</th><th class="py-2">Case</th><th class="py-2">Time</th></tr></thead><tbody><?php foreach ($health['recent_audit_events'] as $event): ?><tr class="border-t border-slate-100"><td class="py-2"><?= e((string)$event['event_key']) ?></td><td class="py-2"><?= e((string)$event['case_id']) ?></td><td class="py-2"><?= e(format_datetime((string)$event['created_at'])) ?></td></tr><?php endforeach; ?></tbody></table></div>
</section>
<?php smile_design_render_shell_end(); ?>
