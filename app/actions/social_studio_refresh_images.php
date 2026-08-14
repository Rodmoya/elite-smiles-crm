<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/social_studio/social_studio_service.php';
require_marketing_access();
require_csrf();
social_studio_ensure_schema();
$posts = json_decode((string)post('posts_json', '[]'), true);
$updated = 0;
foreach (is_array($posts) ? $posts : [] as $post) {
    $id = trim((string)($post['post_id'] ?? ''));
    $url = trim((string)($post['image_url'] ?? ''));
    if ($id === '' || $url === '') continue;
    $bytes = false;
    if (function_exists('curl_init')) {
        $c = curl_init($url);
        curl_setopt_array($c, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_TIMEOUT=>30, CURLOPT_USERAGENT=>'Mozilla/5.0']);
        $bytes = curl_exec($c); curl_close($c);
    } else $bytes = @file_get_contents($url);
    if (!is_string($bytes) || !@getimagesizefromstring($bytes)) continue;
    $key = social_studio_store_imported_image($id, $bytes);
    if ($key !== '') {
        db_execute('UPDATE social_studio_base_creatives SET source_image_url=:url, local_image_key=:key WHERE source_type="instagram" AND source_post_id=:id LIMIT 1', ['url'=>$url,'key'=>$key,'id'=>$id]);
        $updated++;
    }
}
flash_set('success', 'Refreshed ' . $updated . ' Instagram carousel images.');
redirect(base_url('social-studio.php'));
