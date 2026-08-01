<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/social_studio/social_studio_service.php';

require_auth();
require_csrf();
social_studio_ensure_schema();

$rawJson = (string)file_get_contents('php://input');
$postedJson = str_contains((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') ? $rawJson : (string)post('posts_json', '[]');
$posts = json_decode($postedJson, true);
if (!is_array($posts)) {
    flash_set('error', 'Instagram import payload is invalid.');
    redirect(base_url('social-studio.php'));
}

$imported = 0;
$failed = 0;
$batchIndex = max(0, (int)post('batch_index', 0));
$system = 'You are the Elite Smiles Master CMO and visual editorial director. Analyze each supplied Instagram creative as a reusable design system. Return one result per post_id. Identify composition, subject framing, lighting, palette, typography family and scale, text hierarchy, safe zones, CTA treatment, logo treatment, and clinical-ad compliance. The CRM overlay remains editable; generated images must not bake text or logos.';
$itemSchema = [
    'type' => 'object', 'additionalProperties' => false,
    'properties' => [
        'post_id' => ['type' => 'string'], 'title' => ['type' => 'string'], 'group_name' => ['type' => 'string'],
        'analysis' => ['type' => 'string'],
        'base_prompt' => ['type' => 'string'], 'overlay_spec' => ['type' => 'string'],
    ],
    'required' => ['post_id', 'title', 'group_name', 'analysis', 'base_prompt', 'overlay_spec'],
];
$batches = array_chunk($posts, 5);
$batch = $batches[$batchIndex] ?? [];
if (!$batch) {
    flash_set('error', 'Instagram import batch not found.');
    redirect(base_url('social-studio.php'));
}
{
    $valid = array_values(array_filter($batch, static fn($post) => is_array($post) && trim((string)($post['post_id'] ?? '')) !== '' && trim((string)($post['image_url'] ?? '')) !== ''));
    $failed += count($batch) - count($valid);
    if (!$valid) continue;
    $schema = ['type' => 'object', 'additionalProperties' => false, 'properties' => ['items' => ['type' => 'array', 'items' => $itemSchema]], 'required' => ['items']];
    $prompt = 'Analyze every post in this batch. Use the metadata to match each image to its post_id. Do not omit any item. Metadata: ' . json_encode($valid, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    try {
        $analysis = elite_openai_json_response($system, $prompt, $schema, 'elite_smiles_base_creative_batch', array_values(array_map(static fn($post) => (string)$post['image_url'], $valid)));
    } catch (Throwable $exception) {
        $failed += count($valid);
        continue;
    }
    $results = is_array($analysis['data']['items'] ?? null) ? $analysis['data']['items'] : [];
    $byId = [];
    foreach ($results as $result) $byId[(string)($result['post_id'] ?? '')] = $result;
    foreach ($valid as $post) {
        $data = $byId[(string)$post['post_id']] ?? null;
        if (!is_array($data)) { $failed++; continue; }
        social_studio_upsert_base_creative([
            'source_type' => 'instagram', 'source_url' => (string)($post['source_url'] ?? ''),
            'source_post_id' => (string)$post['post_id'], 'title' => (string)$data['title'],
            'published_at' => (string)($post['published_at'] ?? ''), 'group_name' => (string)$data['group_name'],
            'source_image_url' => (string)$post['image_url'], 'local_image_key' => (string)($post['local_image_key'] ?? ''),
            'analysis_json' => json_encode($data['analysis'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'base_prompt' => (string)$data['base_prompt'], 'overlay_spec' => (string)$data['overlay_spec'],
        ]);
        $imported++;
    }
}

flash_set('success', 'Imported and analyzed ' . $imported . ' Instagram base posts' . ($failed ? '; ' . $failed . ' failed.' : '.'));
redirect(base_url('social-studio.php'));
