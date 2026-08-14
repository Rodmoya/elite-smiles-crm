<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/meta/meta_config.php';
require_once __DIR__ . '/social_studio_service.php';

if (!function_exists('social_studio_media_signature')) {
    function social_studio_media_signature(int $draftId, int $expires, string $key = ''): string
    {
        $key = $key !== '' ? $key : trim((string)(defined('APP_KEY') ? APP_KEY : ''));
        return $key === '' ? '' : hash_hmac('sha256', $draftId . '|' . $expires, $key);
    }
}

if (!function_exists('social_studio_public_media_url')) {
    function social_studio_public_media_url(int $draftId, int $ttlSeconds = 3600): string
    {
        $expires = time() + max(300, min(86400, $ttlSeconds));
        $signature = social_studio_media_signature($draftId, $expires);
        if ($signature === '') {
            throw new RuntimeException('APP_KEY is required to create a secure Meta media URL.');
        }

        return base_url('app/api/social_studio_media.php') . '?' . http_build_query([
            'draft_id' => $draftId,
            'expires' => $expires,
            'signature' => $signature,
        ]);
    }
}

if (!function_exists('social_studio_meta_prepare_image')) {
    /**
     * Return a Meta-compatible JPEG path. Exact-overlay drafts are stored as SVG
     * for browser fidelity, but Instagram's publishing API only accepts raster media.
     */
    function social_studio_meta_prepare_image(string $sourcePath): string
    {
        if ($sourcePath === '' || !is_file($sourcePath)) {
            throw new RuntimeException('The approved branded image is missing.');
        }

        $mime = function_exists('mime_content_type') ? (string)(@mime_content_type($sourcePath) ?: '') : '';
        if ($mime === 'image/jpeg') {
            return $sourcePath;
        }
        if (!in_array($mime, ['image/png', 'image/webp', 'image/svg+xml', 'text/plain'], true)) {
            throw new RuntimeException('The finished post is not a supported image format for Meta.');
        }

        $targetPath = $sourcePath . '.meta.jpg';
        if (is_file($targetPath) && filemtime($targetPath) >= filemtime($sourcePath) && filesize($targetPath) > 0) {
            return $targetPath;
        }

        if (in_array($mime, ['image/svg+xml', 'text/plain'], true)) {
            if (!class_exists('Imagick')) {
                throw new RuntimeException('The server image renderer is unavailable for this finished post.');
            }
            try {
                $image = new Imagick();
                $image->setResolution(144, 144);
                $image->readImage($sourcePath);
                $image->setIteratorIndex(0);
                $image->setImageBackgroundColor('white');
                $flattened = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
                $flattened->setImageFormat('jpeg');
                $flattened->setImageCompression(Imagick::COMPRESSION_JPEG);
                $flattened->setImageCompressionQuality(90);
                $flattened->stripImage();
                $ok = $flattened->writeImage($targetPath);
                $flattened->clear();
                $image->clear();
                if (!$ok) {
                    throw new RuntimeException('JPEG output could not be saved.');
                }
            } catch (Throwable $e) {
                @unlink($targetPath);
                throw new RuntimeException('The finished overlay could not be converted for Meta: ' . $e->getMessage());
            }
        } else {
            if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
                throw new RuntimeException('The server image renderer is unavailable for this finished post.');
            }
            $bytes = @file_get_contents($sourcePath);
            $source = is_string($bytes) ? @imagecreatefromstring($bytes) : false;
            if (!$source) {
                throw new RuntimeException('The finished post image could not be decoded for Meta.');
            }
            $width = imagesx($source);
            $height = imagesy($source);
            $canvas = imagecreatetruecolor($width, $height);
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
            imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
            $ok = imagejpeg($canvas, $targetPath, 90);
            imagedestroy($canvas);
            imagedestroy($source);
            if (!$ok) {
                @unlink($targetPath);
                throw new RuntimeException('The finished post could not be converted to JPEG for Meta.');
            }
        }

        if (!is_file($targetPath) || filesize($targetPath) <= 0) {
            throw new RuntimeException('The Meta-ready JPEG was not created.');
        }
        return $targetPath;
    }
}

if (!function_exists('social_studio_meta_caption')) {
    function social_studio_meta_caption(array $draft): string
    {
        $parts = array_values(array_filter([
            trim((string)($draft['caption'] ?? '')),
            trim((string)($draft['hashtags'] ?? '')),
        ], static fn(string $value): bool => $value !== ''));
        return implode("\n\n", $parts);
    }
}

if (!function_exists('social_studio_meta_http')) {
    function social_studio_meta_http(string $method, string $path, array $params, string $accessToken): array
    {
        $accessToken = trim($accessToken);
        if ($accessToken === '') {
            return ['ok' => false, 'message' => 'Meta access token is not configured.'];
        }

        $method = strtoupper($method);
        $version = ltrim(meta_cfg_graph_version(), '/');
        $url = 'https://graph.facebook.com/' . $version . '/' . ltrim($path, '/');
        $params['access_token'] = $accessToken;

        if ($method === 'GET') {
            $url .= '?' . http_build_query($params);
        }

        if (!function_exists('curl_init')) {
            return ['ok' => false, 'message' => 'PHP cURL is required for Meta publishing.'];
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'message' => 'Could not initialize the Meta request.'];
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_USERAGENT => 'EliteSmilesSocialStudio/1.0',
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ];
        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($params);
        }
        curl_setopt_array($ch, $options);

        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if ($statusCode >= 400 || !is_array($decoded) || isset($decoded['error'])) {
            return [
                'ok' => false,
                'status_code' => $statusCode,
                'message' => trim((string)($decoded['error']['message'] ?? $curlError ?: 'Meta returned an invalid response.')),
                'error_code' => (string)($decoded['error']['code'] ?? ''),
                'error_subcode' => (string)($decoded['error']['error_subcode'] ?? ''),
            ];
        }

        return ['ok' => true, 'status_code' => $statusCode, 'data' => $decoded];
    }
}

if (!function_exists('social_studio_meta_resolve_accounts')) {
    function social_studio_meta_resolve_accounts(): array
    {
        $rootToken = meta_cfg_access_token();
        $configuredPageId = meta_cfg_page_id();
        $configuredInstagramId = meta_cfg_instagram_account_id();
        if ($rootToken === '') {
            return ['ok' => false, 'message' => 'META_ACCESS_TOKEN is not configured.'];
        }

        $candidates = [];
        if ($configuredPageId !== '') {
            $page = social_studio_meta_http('GET', $configuredPageId, [
                'fields' => 'id,name,access_token,instagram_business_account{id,username}',
            ], $rootToken);
            if (!empty($page['ok']) && is_array($page['data'] ?? null)) {
                $candidates[] = $page['data'];
            }
        } else {
            $accounts = social_studio_meta_http('GET', 'me/accounts', [
                'fields' => 'id,name,access_token,instagram_business_account{id,username}',
                'limit' => 100,
            ], $rootToken);
            if (!empty($accounts['ok']) && is_array($accounts['data']['data'] ?? null)) {
                $candidates = $accounts['data']['data'];
            }
        }

        if ($candidates === []) {
            $me = social_studio_meta_http('GET', 'me', [
                'fields' => 'id,name,access_token,instagram_business_account{id,username}',
            ], $rootToken);
            if (!empty($me['ok']) && is_array($me['data'] ?? null)) {
                $candidates[] = $me['data'];
            }
        }

        $selected = null;
        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            $candidateId = trim((string)($candidate['id'] ?? ''));
            $candidateName = trim((string)($candidate['name'] ?? ''));
            if (($configuredPageId !== '' && $candidateId === $configuredPageId)
                || ($configuredPageId === '' && stripos($candidateName, 'Elite Smiles') !== false)) {
                $selected = $candidate;
                break;
            }
        }
        $selected ??= is_array($candidates[0] ?? null) ? $candidates[0] : null;
        if (!$selected) {
            return ['ok' => false, 'message' => 'No Facebook Page is available to the configured Meta token.'];
        }

        $instagram = is_array($selected['instagram_business_account'] ?? null)
            ? $selected['instagram_business_account']
            : [];
        $pageId = trim((string)($selected['id'] ?? $configuredPageId));
        $instagramId = $configuredInstagramId !== ''
            ? $configuredInstagramId
            : trim((string)($instagram['id'] ?? ''));

        return [
            'ok' => $pageId !== '',
            'message' => $pageId !== '' ? '' : 'The Facebook Page ID could not be resolved.',
            'page_id' => $pageId,
            'page_name' => trim((string)($selected['name'] ?? '')),
            'page_token' => trim((string)($selected['access_token'] ?? '')) ?: $rootToken,
            'instagram_account_id' => $instagramId,
            'instagram_username' => trim((string)($instagram['username'] ?? '')),
        ];
    }
}

if (!function_exists('social_studio_publish_instagram')) {
    function social_studio_publish_instagram(array $draft, array $accounts, string $mediaUrl, string $caption): string
    {
        $instagramId = trim((string)($accounts['instagram_account_id'] ?? ''));
        if ($instagramId === '') {
            throw new RuntimeException('No Instagram professional account is connected to the Meta Page.');
        }
        if (mb_strlen($caption) > 2200) {
            throw new RuntimeException('Instagram caption exceeds the 2,200-character publishing limit.');
        }

        $container = social_studio_meta_http('POST', $instagramId . '/media', [
            'image_url' => $mediaUrl,
            'caption' => $caption,
        ], (string)$accounts['page_token']);
        $creationId = trim((string)($container['data']['id'] ?? ''));
        if (empty($container['ok']) || $creationId === '') {
            throw new RuntimeException('Instagram container creation failed: ' . (string)($container['message'] ?? 'No container ID returned.'));
        }

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $status = social_studio_meta_http('GET', $creationId, ['fields' => 'status_code,status'], (string)$accounts['page_token']);
            $statusCode = strtoupper(trim((string)($status['data']['status_code'] ?? '')));
            if ($statusCode === 'FINISHED' || $statusCode === '') {
                break;
            }
            if (in_array($statusCode, ['ERROR', 'EXPIRED'], true)) {
                throw new RuntimeException('Instagram rejected the media container: ' . trim((string)($status['data']['status'] ?? $statusCode)));
            }
            usleep(750000);
        }

        $published = social_studio_meta_http('POST', $instagramId . '/media_publish', [
            'creation_id' => $creationId,
        ], (string)$accounts['page_token']);
        $postId = trim((string)($published['data']['id'] ?? ''));
        if (empty($published['ok']) || $postId === '') {
            throw new RuntimeException('Instagram publish failed: ' . (string)($published['message'] ?? 'No media ID returned.'));
        }
        return $postId;
    }
}

if (!function_exists('social_studio_publish_facebook')) {
    function social_studio_publish_facebook(array $accounts, string $mediaUrl, string $caption): string
    {
        $pageId = trim((string)($accounts['page_id'] ?? ''));
        if ($pageId === '') {
            throw new RuntimeException('No Facebook Page is configured for publishing.');
        }
        $published = social_studio_meta_http('POST', $pageId . '/photos', [
            'url' => $mediaUrl,
            'message' => $caption,
            'published' => 'true',
        ], (string)$accounts['page_token']);
        $postId = trim((string)($published['data']['post_id'] ?? $published['data']['id'] ?? ''));
        if (empty($published['ok']) || $postId === '') {
            throw new RuntimeException('Facebook publish failed: ' . (string)($published['message'] ?? 'No post ID returned.'));
        }
        return $postId;
    }
}

if (!function_exists('social_studio_publish_draft')) {
    function social_studio_publish_draft(int $draftId): array
    {
        social_studio_ensure_schema();
        $draft = db_one('SELECT * FROM social_studio_drafts WHERE id=:id LIMIT 1', ['id' => $draftId]);
        if (!$draft) {
            return ['ok' => false, 'message' => 'Social draft not found.'];
        }
        $status = (string)($draft['status'] ?? '');
        $stalePublishing = $status === 'publishing'
            && strtotime((string)($draft['publish_started_at'] ?? '')) < time() - 600;
        if (!in_array($status, ['approved', 'scheduled', 'publish_failed'], true) && !$stalePublishing) {
            return ['ok' => false, 'message' => 'Only an approved or scheduled draft can be published.'];
        }
        $storageKey = trim((string)($draft['branded_image_storage_key'] ?? ''));
        $path = social_studio_safe_storage_path($storageKey);
        if ($path === '' || !is_file($path)) {
            return ['ok' => false, 'message' => 'The approved branded image is missing.'];
        }
        try {
            social_studio_meta_prepare_image($path);
        } catch (Throwable $e) {
            $message = mb_substr(trim($e->getMessage()), 0, 1000);
            db_execute('UPDATE social_studio_drafts SET status="publish_failed", publish_error=:publish_error, last_publish_attempt_at=NOW(), publish_attempts=publish_attempts+1 WHERE id=:id LIMIT 1', [
                'id' => $draftId,
                'publish_error' => $message,
            ]);
            return ['ok' => false, 'message' => $message];
        }

        $claimed = db_query(
            'UPDATE social_studio_drafts SET status="publishing", publish_started_at=NOW(), last_publish_attempt_at=NOW(), publish_attempts=publish_attempts+1, publish_error=NULL WHERE id=:id AND (status IN ("approved","scheduled","publish_failed") OR (status="publishing" AND publish_started_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)))',
            ['id' => $draftId]
        )->rowCount() > 0;
        if (!$claimed) {
            return ['ok' => false, 'message' => 'This post is already being published.'];
        }

        try {
            $accounts = social_studio_meta_resolve_accounts();
            if (empty($accounts['ok'])) {
                throw new RuntimeException((string)($accounts['message'] ?? 'Meta account discovery failed.'));
            }
            $caption = social_studio_meta_caption($draft);
            $mediaUrl = social_studio_public_media_url($draftId);
            $platform = strtolower((string)($draft['platform'] ?? 'facebook_instagram'));
            $publishInstagram = str_contains($platform, 'instagram');
            $publishFacebook = str_contains($platform, 'facebook');
            if (!$publishInstagram && !$publishFacebook) {
                throw new RuntimeException('No supported Meta publishing destination is selected.');
            }

            $instagramPostId = trim((string)($draft['meta_instagram_post_id'] ?? ''));
            $facebookPostId = trim((string)($draft['meta_facebook_post_id'] ?? ''));
            if ($publishInstagram && $instagramPostId === '') {
                $instagramPostId = social_studio_publish_instagram($draft, $accounts, $mediaUrl, $caption);
                db_execute('UPDATE social_studio_drafts SET meta_instagram_post_id=:post_id WHERE id=:id LIMIT 1', ['id' => $draftId, 'post_id' => $instagramPostId]);
            }
            if ($publishFacebook && $facebookPostId === '') {
                $facebookPostId = social_studio_publish_facebook($accounts, $mediaUrl, $caption);
                db_execute('UPDATE social_studio_drafts SET meta_facebook_post_id=:post_id WHERE id=:id LIMIT 1', ['id' => $draftId, 'post_id' => $facebookPostId]);
            }

            $primaryPostId = $instagramPostId !== '' ? $instagramPostId : $facebookPostId;
            db_execute('UPDATE social_studio_drafts SET status="published", published_at=NOW(), meta_post_id=:meta_post_id, publish_error=NULL WHERE id=:id LIMIT 1', [
                'id' => $draftId,
                'meta_post_id' => $primaryPostId,
            ]);
            esm_log('social_studio', 'Social post published through Meta.', [
                'draft_id' => $draftId,
                'instagram_post_id' => $instagramPostId,
                'facebook_post_id' => $facebookPostId,
            ]);
            return [
                'ok' => true,
                'message' => 'Published to Meta successfully.',
                'instagram_post_id' => $instagramPostId,
                'facebook_post_id' => $facebookPostId,
            ];
        } catch (Throwable $e) {
            $message = mb_substr(trim($e->getMessage()), 0, 1000);
            db_execute('UPDATE social_studio_drafts SET status="publish_failed", publish_error=:publish_error WHERE id=:id LIMIT 1', [
                'id' => $draftId,
                'publish_error' => $message,
            ]);
            esm_log('social_studio', 'Meta social publishing failed.', ['draft_id' => $draftId, 'error' => $message]);
            return ['ok' => false, 'message' => $message];
        }
    }
}

if (!function_exists('social_studio_schedule_draft')) {
    function social_studio_schedule_draft(int $draftId, string $scheduledAt): array
    {
        social_studio_ensure_schema();
        $timestamp = strtotime($scheduledAt);
        if ($timestamp === false || $timestamp < time() + 60) {
            return ['ok' => false, 'message' => 'Choose a publishing time at least one minute from now.'];
        }
        if ($timestamp > strtotime('+6 months')) {
            return ['ok' => false, 'message' => 'Scheduled posts must be within the next six months.'];
        }
        $formatted = date('Y-m-d H:i:s', $timestamp);
        $updated = db_query(
            'UPDATE social_studio_drafts SET status="scheduled", scheduled_at=:scheduled_at, publish_error=NULL WHERE id=:id AND status IN ("approved","scheduled","publish_failed")',
            ['id' => $draftId, 'scheduled_at' => $formatted]
        )->rowCount() > 0;
        return $updated
            ? ['ok' => true, 'message' => 'Post scheduled for ' . date('M j, Y g:i A T', $timestamp) . '.', 'scheduled_at' => $formatted]
            : ['ok' => false, 'message' => 'Approve the post before scheduling it.'];
    }
}

if (!function_exists('social_studio_publish_due')) {
    function social_studio_publish_due(int $limit = 10): array
    {
        social_studio_ensure_schema();
        $limit = max(1, min(25, $limit));
        $drafts = db_all('SELECT id FROM social_studio_drafts WHERE (status="scheduled" AND scheduled_at IS NOT NULL AND scheduled_at <= NOW()) OR (status="publishing" AND publish_started_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)) ORDER BY scheduled_at ASC, id ASC LIMIT ' . $limit);
        $results = [];
        foreach ($drafts as $draft) {
            $draftId = (int)($draft['id'] ?? 0);
            $results[] = ['draft_id' => $draftId] + social_studio_publish_draft($draftId);
        }
        return [
            'ok' => !array_filter($results, static fn(array $result): bool => empty($result['ok'])),
            'processed' => count($results),
            'results' => $results,
        ];
    }
}
