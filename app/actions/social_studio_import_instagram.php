<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/social_studio/social_studio_service.php';

require_auth();
require_csrf();
social_studio_ensure_schema();

$posts = json_decode((string)post('posts_json', '[]'), true);
if (!is_array($posts)) {
    flash_set('error', 'Instagram import payload is invalid.');
    redirect(base_url('social-studio.php'));
}

$imported = 0;
$failed = 0;
foreach ($posts as $post) {
    if (!is_array($post) || trim((string)($post['post_id'] ?? '')) === '' || trim((string)($post['image_url'] ?? '')) === '') {
        $failed++;
        continue;
    }
    $analysis = social_studio_analyze_base_creative($post);
    if (empty($analysis['ok']) || !is_array($analysis['data'] ?? null)) {
        $failed++;
        continue;
    }
    $data = $analysis['data'];
    social_studio_upsert_base_creative([
        'source_type' => 'instagram',
        'source_url' => (string)($post['source_url'] ?? ''),
        'source_post_id' => (string)$post['post_id'],
        'title' => (string)$data['title'],
        'published_at' => (string)($post['published_at'] ?? ''),
        'group_name' => (string)$data['group_name'],
        'source_image_url' => (string)$post['image_url'],
        'local_image_key' => (string)($post['local_image_key'] ?? ''),
        'analysis_json' => json_encode($data['analysis'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'base_prompt' => (string)$data['base_prompt'],
        'overlay_spec' => (string)$data['overlay_spec'],
    ]);
    $imported++;
}

flash_set('success', 'Imported and analyzed ' . $imported . ' Instagram base posts' . ($failed ? '; ' . $failed . ' failed.' : '.'));
redirect(base_url('social-studio.php'));
