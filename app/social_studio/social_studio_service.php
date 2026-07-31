<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/openai.php';
require_once dirname(__DIR__) . '/core/google_gemini.php';

if (!function_exists('social_studio_ensure_schema')) {
    function social_studio_ensure_schema(): void
    {
        db_query("CREATE TABLE IF NOT EXISTS social_studio_drafts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(180) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'draft',
            platform VARCHAR(80) NOT NULL DEFAULT 'facebook_instagram',
            content_focus VARCHAR(120) NOT NULL DEFAULT 'veneers',
            post_type VARCHAR(120) NOT NULL DEFAULT 'education',
            caption TEXT NOT NULL,
            cta VARCHAR(255) NOT NULL DEFAULT '',
            hashtags TEXT NULL,
            image_prompt TEXT NULL,
            image_url VARCHAR(500) NULL,
            image_storage_key VARCHAR(255) NULL,
            branded_image_storage_key VARCHAR(255) NULL,
            image_generated_at DATETIME NULL,
            scheduled_at DATETIME NULL,
            approved_at DATETIME NULL,
            approved_by INT UNSIGNED NULL,
            published_at DATETIME NULL,
            meta_post_id VARCHAR(120) NULL,
            notes TEXT NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_social_status (status),
            INDEX idx_social_scheduled_at (scheduled_at),
            INDEX idx_social_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        foreach ([
            'image_storage_key' => "ALTER TABLE social_studio_drafts ADD COLUMN image_storage_key VARCHAR(255) NULL AFTER image_url",
            'branded_image_storage_key' => "ALTER TABLE social_studio_drafts ADD COLUMN branded_image_storage_key VARCHAR(255) NULL AFTER image_storage_key",
            'image_generated_at' => "ALTER TABLE social_studio_drafts ADD COLUMN image_generated_at DATETIME NULL AFTER branded_image_storage_key",
        ] as $column => $sql) {
            // MariaDB does not accept bound parameters in SHOW COLUMNS LIKE clauses.
            // Quote the value through PDO, then keep the DDL itself fixed and controlled.
            $quotedColumn = db()->quote($column);
            if (!db_one("SHOW COLUMNS FROM social_studio_drafts LIKE {$quotedColumn}")) {
                db_query($sql);
            }
        }
    }
}

if (!function_exists('social_studio_status_labels')) {
    function social_studio_status_labels(): array
    {
        return [
            'draft' => 'Draft',
            'review' => 'Review',
            'approved' => 'Approved',
            'scheduled' => 'Scheduled',
            'published' => 'Published',
            'rejected' => 'Rejected',
        ];
    }
}

if (!function_exists('social_studio_default_hashtags')) {
    function social_studio_default_hashtags(string $focus): array
    {
        return match ($focus) {
            'implants' => ['#DentalImplants', '#EliteSmilesUtah', '#DraperUtah', '#ImplantDentistry', '#ToothReplacement', '#SmileRestoration', '#UtahDentist', '#DraperDentist', '#AllOnX', '#FixedTeeth', '#SmileWithConfidence'],
            'smile_makeover' => ['#EliteSmilesUtah', '#SmileMakeover', '#CosmeticDentistry', '#DraperUtah', '#SmileDesign', '#NaturalLookingSmile', '#SmileConfidence', '#LoveYourSmile', '#UtahSmiles', '#DraperDentist'],
            'lip_repositioning' => ['#LipRepositioning', '#GummySmile', '#SmileMakeover', '#EliteSmilesUtah', '#DraperUtah', '#CosmeticDentistry', '#SmileConfidence', '#GummySmileCorrection', '#BeautifulSmile'],
            default => ['#EliteSmilesUtah', '#Veneers', '#PorcelainVeneers', '#SmileMakeover', '#CosmeticDentistry', '#DraperUtah', '#UtahVeneers', '#DraperDentist', '#SmileDesign', '#NaturalLookingVeneers', '#SmileConfidence', '#LoveYourSmile'],
        };
    }
}

if (!function_exists('social_studio_editorial_context')) {
    function social_studio_editorial_context(): string
    {
        return 'Elite Smiles by Walter Meden DDS is a premium cosmetic dentistry practice in Draper, Utah. Editorial line: warm, sincere, premium, confidence-led, and consultation-focused. Lead with how a natural-looking smile affects photos, conversations, weddings, work, and self-confidence. Use short paragraphs, simple benefit bullets, a clear complimentary-consultation CTA, and Draper, Utah location context. Mention flexible or 0% financing only when useful and always for qualified patients. Avoid guaranteed outcomes, fake patient claims, heavy jargon, and aggressive price-first copy.';
    }
}

if (!function_exists('social_studio_focus_label')) {
    function social_studio_focus_label(string $focus): string
    {
        return match ($focus) {
            'implants' => 'Implants',
            'smile_makeover' => 'Smile Makeover',
            'lip_repositioning' => 'Lip Repositioning',
            default => 'Veneers',
        };
    }
}

if (!function_exists('social_studio_seed_drafts')) {
    function social_studio_seed_drafts(string $focus, int $count, int $createdBy = 0, string $instruction = ''): int
    {
        social_studio_ensure_schema();

        $focus = social_studio_normalize_focus($focus);
        $count = max(1, min(14, $count));
        $hashtags = social_studio_default_hashtags($focus);
        $topics = social_studio_generate_topics($focus, $count, $instruction);
        $created = 0;

        foreach ($topics as $index => $topic) {
            $scheduledAt = social_studio_next_slot($index);
            $caption = trim((string)($topic['caption'] ?? ''));
            if ($caption === '') {
                $caption = social_studio_fallback_caption($focus, (int)$index);
            }
            $title = trim((string)($topic['title'] ?? ''));
            if ($title === '') {
                $title = social_studio_fallback_title($focus, (int)$index);
            }
            $imagePrompt = trim((string)($topic['image_prompt'] ?? ''));
            if ($imagePrompt === '') {
                $imagePrompt = social_studio_fallback_image_prompt($focus, $title);
            }

            db_insert(
                "INSERT INTO social_studio_drafts
                    (title, status, platform, content_focus, post_type, caption, cta, hashtags, image_prompt, scheduled_at, created_by)
                 VALUES
                    (:title, 'review', :platform, :content_focus, :post_type, :caption, :cta, :hashtags, :image_prompt, :scheduled_at, :created_by)",
                [
                    'title' => $title,
                    'platform' => 'facebook_instagram',
                    'content_focus' => $focus,
                    'post_type' => trim((string)($topic['post_type'] ?? 'education')) ?: 'education',
                    'caption' => $caption,
                    'cta' => trim((string)($topic['cta'] ?? 'Request a veneer quote today.')),
                    'hashtags' => implode(' ', $hashtags),
                    'image_prompt' => $imagePrompt,
                    'scheduled_at' => $scheduledAt,
                    'created_by' => $createdBy > 0 ? $createdBy : null,
                ]
            );
            $created++;
        }

        return $created;
    }
}

if (!function_exists('social_studio_generate_topics')) {
    function social_studio_generate_topics(string $focus, int $count, string $instruction = ''): array
    {
        if (!elite_openai_is_configured()) {
            return social_studio_fallback_topics($focus, $count);
        }

        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'drafts' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => 14,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'post_type' => ['type' => 'string'],
                            'caption' => ['type' => 'string'],
                            'cta' => ['type' => 'string'],
                            'image_prompt' => ['type' => 'string'],
                        ],
                        'required' => ['title', 'post_type', 'caption', 'cta', 'image_prompt'],
                    ],
                ],
            ],
            'required' => ['drafts'],
        ];

        $system = 'You are the social media strategist for Elite Smiles by Walter Meden DDS. Write concise, premium, compliant dental marketing posts. Avoid guaranteed outcomes, hype, fake patient claims, and medical overpromising. ' . social_studio_editorial_context();
        $user = "Create {$count} draft social posts for {$focus}. Each draft needs title, post_type, caption, CTA, and Nano Banana image prompt. The image prompt must request a clean visual with no text, no logo, no watermark, and room for CRM branding. Instruction: " . ($instruction !== '' ? $instruction : 'Generate conversion-focused consult posts.');
        $response = elite_openai_json_response($system, $user, $schema, 'social_studio_drafts');
        if (empty($response['ok']) || !is_array($response['data']['drafts'] ?? null)) {
            return social_studio_fallback_topics($focus, $count);
        }

        return array_slice(array_values($response['data']['drafts']), 0, $count);
    }
}

if (!function_exists('social_studio_fallback_topics')) {
    function social_studio_fallback_topics(string $focus, int $count): array
    {
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = [
                'title' => social_studio_fallback_title($focus, $i),
                'post_type' => ['education', 'trust', 'consult_cta', 'faq'][$i % 4],
                'caption' => social_studio_fallback_caption($focus, $i),
                'cta' => $focus === 'veneers' ? 'Request a veneer quote today.' : 'Schedule a consult with Elite Smiles.',
                'image_prompt' => social_studio_fallback_image_prompt($focus, social_studio_fallback_title($focus, $i)),
            ];
        }
        return $items;
    }
}

if (!function_exists('social_studio_fallback_title')) {
    function social_studio_fallback_title(string $focus, int $index): string
    {
        $titles = match ($focus) {
            'implants' => ['Implant consult made simple', 'What dental implants can restore', 'Why planning matters first', 'A confident smile starts here'],
            'smile_makeover' => ['Your smile makeover plan', 'Design before treatment', 'Small details, big confidence', 'A consult built around you'],
            'lip_repositioning' => ['Understanding gummy smile options', 'A balanced smile line', 'Lip repositioning consult', 'Subtle change, confident smile'],
            default => ['Design your smile before treatment begins', 'Choosing the right veneer shade', 'What veneers can improve', 'A natural white smile starts with planning'],
        };
        return $titles[$index % count($titles)];
    }
}

if (!function_exists('social_studio_fallback_caption')) {
    function social_studio_fallback_caption(string $focus, int $index): string
    {
        if ($focus === 'veneers') {
            $captions = [
                'Thinking about veneers but unsure what your final smile could look like? At Elite Smiles, smile design planning helps you understand shape, shade, and style before treatment starts.',
                'A beautiful veneer result is not only about making teeth whiter. Shape, proportion, gum display, and natural facial balance all matter.',
                'If chips, worn edges, spacing, or old veneers are holding back your smile, a cosmetic consult can help you understand your best options.',
                'Your smile should look bright, clean, and natural for you. Our veneer consults are designed to make the process clear before you decide.',
            ];
            return $captions[$index % count($captions)];
        }
        return 'A confident smile starts with a clear plan. Elite Smiles helps you understand your options before treatment begins.';
    }
}

if (!function_exists('social_studio_fallback_image_prompt')) {
    function social_studio_fallback_image_prompt(string $focus, string $title): string
    {
        return 'Premium dental social media image for "' . $title . '". Realistic cosmetic dentistry or lifestyle setting, bright natural smile, elegant black white and warm gold brand feel, clean negative space for CRM logo placement. No readable text, no logo, no watermark, no typography, no distorted teeth.';
    }
}

if (!function_exists('social_studio_normalize_focus')) {
    function social_studio_normalize_focus(string $focus): string
    {
        $focus = trim($focus);
        return in_array($focus, ['veneers', 'implants', 'smile_makeover', 'lip_repositioning'], true) ? $focus : 'veneers';
    }
}

if (!function_exists('social_studio_next_slot')) {
    function social_studio_next_slot(int $offset): string
    {
        $date = new DateTime('tomorrow 10:30', new DateTimeZone(APP_TIMEZONE));
        $date->modify('+' . max(0, $offset) . ' day');
        return $date->format('Y-m-d H:i:s');
    }
}

if (!function_exists('social_studio_dashboard_data')) {
    function social_studio_dashboard_data(): array
    {
        social_studio_ensure_schema();
        $counts = [
            'review' => 0,
            'approved' => 0,
            'scheduled' => 0,
            'published' => 0,
        ];
        foreach (db_all('SELECT status, COUNT(*) AS total FROM social_studio_drafts GROUP BY status') as $row) {
            $counts[(string)$row['status']] = (int)$row['total'];
        }

        return [
            'counts' => $counts,
            'drafts' => db_all('SELECT * FROM social_studio_drafts ORDER BY FIELD(status, "review", "draft", "approved", "scheduled", "published", "rejected"), COALESCE(scheduled_at, created_at) ASC, id DESC LIMIT 12'),
            'selected' => db_one('SELECT * FROM social_studio_drafts WHERE status IN ("review", "draft", "approved") ORDER BY FIELD(status, "review", "draft", "approved"), id DESC LIMIT 1'),
            'schedule' => db_all('SELECT * FROM social_studio_drafts WHERE scheduled_at IS NOT NULL AND status IN ("review", "approved", "scheduled") ORDER BY scheduled_at ASC LIMIT 8'),
        ];
    }
}

if (!function_exists('social_studio_private_root')) {
    function social_studio_private_root(): string
    {
        return storage_path('social-studio');
    }
}

if (!function_exists('social_studio_safe_storage_path')) {
    function social_studio_safe_storage_path(string $storageKey): ?string
    {
        $storageKey = ltrim(str_replace('\\', '/', $storageKey), '/');
        if ($storageKey === '' || str_contains($storageKey, '..')) {
            return null;
        }
        $root = realpath(social_studio_private_root());
        if (!$root) {
            ensure_directory(social_studio_private_root());
            $root = realpath(social_studio_private_root());
        }
        if (!$root) {
            return null;
        }
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storageKey);
        $dir = dirname($path);
        ensure_directory($dir);
        $realDir = realpath($dir);
        if (!$realDir || !str_starts_with($realDir, $root)) {
            return null;
        }
        return $path;
    }
}

if (!function_exists('social_studio_image_url')) {
    function social_studio_image_url(array $draft, bool $branded = true): string
    {
        $key = $branded ? (string)($draft['branded_image_storage_key'] ?? '') : '';
        if ($key === '') {
            $key = (string)($draft['image_storage_key'] ?? '');
        }
        if ($key === '') {
            return '';
        }
        return base_url('app/actions/social_studio_image.php?draft_id=' . rawurlencode((string)$draft['id']) . ($branded ? '&variant=branded' : '&variant=raw'));
    }
}

if (!function_exists('social_studio_generate_image_for_draft')) {
    function social_studio_generate_image_for_draft(int $draftId): array
    {
        social_studio_ensure_schema();
        $draft = db_one('SELECT * FROM social_studio_drafts WHERE id = :id LIMIT 1', ['id' => $draftId]);
        if (!$draft) {
            return ['ok' => false, 'message' => 'Social draft not found.'];
        }

        $prompt = social_studio_refine_image_prompt($draft);
        $generated = social_studio_generate_image_binary($prompt);
        if (empty($generated['ok']) || !is_string($generated['bytes'] ?? null) || $generated['bytes'] === '') {
            return ['ok' => false, 'message' => (string)($generated['message'] ?? 'Could not generate image.')];
        }

        $storagePrefix = 'drafts/' . $draftId;
        $rawExt = (string)($generated['mime_type'] ?? '') === 'image/svg+xml' ? 'svg' : 'png';
        $rawKey = $storagePrefix . '/generated-' . date('Ymd-His') . '.' . $rawExt;
        $rawPath = social_studio_safe_storage_path($rawKey);
        if (!$rawPath || @file_put_contents($rawPath, $generated['bytes']) === false) {
            return ['ok' => false, 'message' => 'Could not save generated image.'];
        }

        $brandedExt = social_studio_can_raster_brand_images() ? 'png' : 'svg';
        $brandedKey = $storagePrefix . '/branded-' . date('Ymd-His') . '.' . $brandedExt;
        $brandedPath = social_studio_safe_storage_path($brandedKey);
        if (!$brandedPath || !social_studio_create_branded_image($rawPath, $brandedPath)) {
            $brandedKey = $rawKey;
        }

        db_execute(
            'UPDATE social_studio_drafts
             SET image_prompt = :image_prompt, image_storage_key = :image_storage_key, branded_image_storage_key = :branded_image_storage_key, image_generated_at = NOW()
             WHERE id = :id LIMIT 1',
            [
                'id' => $draftId,
                'image_prompt' => $prompt,
                'image_storage_key' => $rawKey,
                'branded_image_storage_key' => $brandedKey,
            ]
        );

        return ['ok' => true, 'message' => 'Image generated.', 'image_storage_key' => $rawKey, 'branded_image_storage_key' => $brandedKey];
    }
}

if (!function_exists('social_studio_refine_image_prompt')) {
    function social_studio_refine_image_prompt(array $draft): string
    {
        $title = trim((string)($draft['title'] ?? 'Elite Smiles social post'));
        $caption = trim((string)($draft['caption'] ?? ''));
        $basePrompt = trim((string)($draft['image_prompt'] ?? ''));
        $focus = social_studio_focus_label((string)($draft['content_focus'] ?? 'veneers'));

        if (elite_openai_is_configured()) {
            $schema = [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'prompt' => ['type' => 'string'],
                ],
                'required' => ['prompt'],
            ];
            $system = 'You write image generation prompts for premium dental social media. The CRM will add the Elite Smiles logo later, so do not ask the image model to render logos, words, captions, text, watermarks, badges, or typography. ' . social_studio_editorial_context();
            $user = "Create one precise Nano Banana image prompt.\nTitle: {$title}\nFocus: {$focus}\nCaption: {$caption}\nExisting visual direction: {$basePrompt}\nRules: no text in image, no logo, no watermarks, premium cosmetic dentistry, realistic clean image, bright attractive smile where relevant, no distorted teeth or extra teeth, suitable for Facebook/Instagram feed.";
            $response = elite_openai_json_response($system, $user, $schema, 'social_image_prompt');
            if (!empty($response['ok']) && is_string($response['data']['prompt'] ?? null)) {
                $prompt = trim((string)$response['data']['prompt']);
                if ($prompt !== '') {
                    return $prompt;
                }
            }
        }

        return trim($basePrompt . "\n\nCreate a premium Elite Smiles social media image for: {$title}. Focus: {$focus}. No readable text, no logo, no watermark, no typography. Clean cosmetic dentistry or lifestyle look, realistic smile, elegant black/white/warm gold feel, suitable for Instagram and Facebook feed, with negative space for CRM logo placement.");
    }
}

if (!function_exists('social_studio_generate_image_binary')) {
    function social_studio_generate_image_binary(string $prompt): array
    {
        if (!elite_gemini_is_configured()) {
            return social_studio_placeholder_image_binary($prompt);
        }

        $model = defined('GOOGLE_GEMINI_IMAGE_MODEL') ? trim((string)GOOGLE_GEMINI_IMAGE_MODEL) : 'gemini-3.1-flash-image';
        $apiKey = defined('GOOGLE_GEMINI_API_KEY') ? trim((string)GOOGLE_GEMINI_API_KEY) : '';
        if ($apiKey === '') {
            return social_studio_placeholder_image_binary($prompt);
        }

        $payload = [
            'contents' => [[
                'role' => 'user',
                'parts' => [[
                    'text' => $prompt,
                ]],
            ]],
            'generationConfig' => [
                'responseModalities' => ['TEXT', 'IMAGE'],
            ],
        ];
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'message' => 'Could not initialize image generation.'];
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);
        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($decoded) || $statusCode < 200 || $statusCode >= 300) {
            esm_log('social_studio', 'Gemini social image generation failed.', [
                'status_code' => $statusCode,
                'error' => $decoded['error']['message'] ?? $curlError,
            ]);
            return ['ok' => false, 'message' => (string)($decoded['error']['message'] ?? 'Image generation failed.')];
        }

        foreach (($decoded['candidates'] ?? []) as $candidate) {
            foreach (($candidate['content']['parts'] ?? []) as $part) {
                $inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
                if (is_array($inline) && is_string($inline['data'] ?? null) && $inline['data'] !== '') {
                    $bytes = base64_decode((string)$inline['data'], true);
                    if (is_string($bytes) && $bytes !== '') {
                        return ['ok' => true, 'bytes' => $bytes, 'mime_type' => (string)($inline['mimeType'] ?? $inline['mime_type'] ?? 'image/png')];
                    }
                }
            }
        }

        return ['ok' => false, 'message' => 'Image response did not include image data.'];
    }
}

if (!function_exists('social_studio_placeholder_image_binary')) {
    function social_studio_placeholder_image_binary(string $prompt): array
    {
        if (!function_exists('imagecreatetruecolor')) {
            $safePrompt = htmlspecialchars(substr($prompt, 0, 150), ENT_QUOTES, 'UTF-8');
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1080" height="1080" viewBox="0 0 1080 1080">'
                . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#020617"/><stop offset=".55" stop-color="#334155"/><stop offset="1" stop-color="#d7b76d"/></linearGradient></defs>'
                . '<rect width="1080" height="1080" fill="url(#g)"/>'
                . '<ellipse cx="735" cy="320" rx="190" ry="82" fill="#fff" opacity=".92"/>'
                . '<ellipse cx="735" cy="320" rx="145" ry="48" fill="#d7b76d" opacity=".78"/>'
                . '<text x="70" y="870" fill="#fff" font-family="Arial, sans-serif" font-size="38" font-weight="700">Elite Smiles social image placeholder</text>'
                . '<text x="70" y="925" fill="#fff" opacity=".82" font-family="Arial, sans-serif" font-size="24">' . $safePrompt . '</text>'
                . '</svg>';
            return ['ok' => true, 'bytes' => $svg, 'mime_type' => 'image/svg+xml'];
        }
        $width = 1080;
        $height = 1080;
        $image = imagecreatetruecolor($width, $height);
        $dark = imagecolorallocate($image, 2, 6, 23);
        $slate = imagecolorallocate($image, 51, 65, 85);
        $gold = imagecolorallocate($image, 215, 183, 109);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, $width, $height, $dark);
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            $color = imagecolorallocate(
                $image,
                (int)(2 + (215 - 2) * $ratio),
                (int)(6 + (183 - 6) * $ratio),
                (int)(23 + (109 - 23) * $ratio)
            );
            imageline($image, 0, $y, $width, $y, $color);
        }
        imagefilledellipse($image, 740, 320, 360, 160, $white);
        imagefilledellipse($image, 740, 320, 300, 100, $gold);
        imagestring($image, 5, 70, 860, 'Elite Smiles social image placeholder', $white);
        imagestring($image, 3, 70, 900, substr($prompt, 0, 95), $white);
        ob_start();
        imagepng($image, null, 6);
        $bytes = (string)ob_get_clean();
        imagedestroy($image);
        return ['ok' => true, 'bytes' => $bytes, 'mime_type' => 'image/png'];
    }
}

if (!function_exists('social_studio_can_raster_brand_images')) {
    function social_studio_can_raster_brand_images(): bool
    {
        return class_exists('Imagick') || function_exists('imagecreatefromstring');
    }
}

if (!function_exists('social_studio_create_branded_image')) {
    function social_studio_create_branded_image(string $sourcePath, string $targetPath): bool
    {
        if (class_exists('Imagick')) {
            try {
                $image = new Imagick($sourcePath);
                $image->setImageFormat('png');
                $width = $image->getImageWidth();
                $height = $image->getImageHeight();
                $logoPath = ROOT_PATH . '/assets/img/ES-Logo-Stack-500-x-150-px.png';
                if (is_file($logoPath)) {
                    $logo = new Imagick($logoPath);
                    $targetLogoWidth = max(180, (int)round($width * 0.24));
                    $logo->resizeImage($targetLogoWidth, 0, Imagick::FILTER_LANCZOS, 1);
                    $boxPaddingX = max(18, (int)round($width * 0.025));
                    $boxPaddingY = max(14, (int)round($width * 0.016));
                    $boxW = $logo->getImageWidth() + ($boxPaddingX * 2);
                    $boxH = $logo->getImageHeight() + ($boxPaddingY * 2);
                    $boxX = $width - $boxW - max(28, (int)round($width * 0.035));
                    $boxY = $height - $boxH - max(28, (int)round($width * 0.035));
                    $draw = new ImagickDraw();
                    $draw->setFillColor(new ImagickPixel('rgba(255,255,255,0.9)'));
                    $draw->rectangle($boxX, $boxY, $boxX + $boxW, $boxY + $boxH);
                    $image->drawImage($draw);
                    $image->compositeImage($logo, Imagick::COMPOSITE_OVER, $boxX + $boxPaddingX, $boxY + $boxPaddingY);
                    $logo->clear();
                }
                $ok = $image->writeImage($targetPath);
                $image->clear();
                return $ok;
            } catch (Throwable $e) {
                esm_log('social_studio', 'Imagick branding failed.', ['message' => $e->getMessage()]);
            }
        }

        if (!function_exists('imagecreatefromstring')) {
            return social_studio_create_branded_svg($sourcePath, $targetPath);
        }
        $sourceBytes = @file_get_contents($sourcePath);
        if (!is_string($sourceBytes) || $sourceBytes === '') {
            return false;
        }
        $source = @imagecreatefromstring($sourceBytes);
        if (!$source) {
            return false;
        }
        imagepalettetotruecolor($source);
        imagealphablending($source, true);
        imagesavealpha($source, true);
        $width = imagesx($source);
        $height = imagesy($source);

        $logoPath = ROOT_PATH . '/assets/img/ES-Logo-Stack-500-x-150-px.png';
        $logo = is_file($logoPath) ? @imagecreatefrompng($logoPath) : false;
        if (!$logo) {
            imagepng($source, $targetPath, 6);
            imagedestroy($source);
            return true;
        }
        imagepalettetotruecolor($logo);
        imagealphablending($logo, true);
        imagesavealpha($logo, true);

        $targetLogoWidth = max(180, (int)round($width * 0.24));
        $logoRatio = imagesy($logo) > 0 ? imagesx($logo) / imagesy($logo) : 3.33;
        $targetLogoHeight = max(54, (int)round($targetLogoWidth / $logoRatio));
        $padding = max(28, (int)round($width * 0.035));
        $boxPaddingX = (int)round($padding * 0.7);
        $boxPaddingY = (int)round($padding * 0.45);
        $boxW = $targetLogoWidth + ($boxPaddingX * 2);
        $boxH = $targetLogoHeight + ($boxPaddingY * 2);
        $boxX = $width - $boxW - $padding;
        $boxY = $height - $boxH - $padding;
        $white = imagecolorallocatealpha($source, 255, 255, 255, 14);
        imagefilledrectangle($source, $boxX, $boxY, $boxX + $boxW, $boxY + $boxH, $white);
        imagecopyresampled(
            $source,
            $logo,
            $boxX + $boxPaddingX,
            $boxY + $boxPaddingY,
            0,
            0,
            $targetLogoWidth,
            $targetLogoHeight,
            imagesx($logo),
            imagesy($logo)
        );

        $ok = imagepng($source, $targetPath, 6);
        imagedestroy($logo);
        imagedestroy($source);
        return $ok;
    }
}

if (!function_exists('social_studio_create_branded_svg')) {
    function social_studio_create_branded_svg(string $sourcePath, string $targetPath): bool
    {
        $sourceBytes = @file_get_contents($sourcePath);
        if (!is_string($sourceBytes) || $sourceBytes === '') {
            return false;
        }
        $sourceMime = function_exists('mime_content_type') ? (string)(@mime_content_type($sourcePath) ?: '') : '';
        if ($sourceMime === '' || $sourceMime === 'text/plain') {
            $sourceMime = str_starts_with(ltrim($sourceBytes), '<svg') ? 'image/svg+xml' : 'image/png';
        }
        $size = @getimagesize($sourcePath);
        $width = is_array($size) && !empty($size[0]) ? (int)$size[0] : 1080;
        $height = is_array($size) && !empty($size[1]) ? (int)$size[1] : 1080;
        $logoPath = ROOT_PATH . '/assets/img/ES-Logo-Stack-500-x-150-px.png';
        $logoBytes = is_file($logoPath) ? @file_get_contents($logoPath) : '';
        $logoData = is_string($logoBytes) && $logoBytes !== '' ? 'data:image/png;base64,' . base64_encode($logoBytes) : '';
        $logoW = max(180, (int)round($width * 0.24));
        $logoH = (int)round($logoW / 3.33);
        $pad = max(28, (int)round($width * 0.035));
        $boxPadX = max(18, (int)round($width * 0.025));
        $boxPadY = max(14, (int)round($width * 0.016));
        $boxW = $logoW + ($boxPadX * 2);
        $boxH = $logoH + ($boxPadY * 2);
        $boxX = $width - $boxW - $pad;
        $boxY = $height - $boxH - $pad;
        $sourceData = 'data:' . $sourceMime . ';base64,' . base64_encode($sourceBytes);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">'
            . '<image href="' . $sourceData . '" x="0" y="0" width="' . $width . '" height="' . $height . '" preserveAspectRatio="xMidYMid slice"/>'
            . '<rect x="' . $boxX . '" y="' . $boxY . '" width="' . $boxW . '" height="' . $boxH . '" rx="0" fill="rgba(255,255,255,.9)"/>';
        if ($logoData !== '') {
            $svg .= '<image href="' . $logoData . '" x="' . ($boxX + $boxPadX) . '" y="' . ($boxY + $boxPadY) . '" width="' . $logoW . '" height="' . $logoH . '" preserveAspectRatio="xMidYMid meet"/>';
        } else {
            $svg .= '<text x="' . ($boxX + $boxPadX) . '" y="' . ($boxY + $boxPadY + 34) . '" fill="#020617" font-family="Arial, sans-serif" font-size="28" font-weight="700">Elite Smiles</text>';
        }
        $svg .= '</svg>';
        return @file_put_contents($targetPath, $svg) !== false;
    }
}

if (!function_exists('social_studio_update_status')) {
    function social_studio_update_status(int $draftId, string $status, int $userId = 0): bool
    {
        social_studio_ensure_schema();
        $allowed = ['draft', 'review', 'approved', 'scheduled', 'published', 'rejected'];
        if ($draftId <= 0 || !in_array($status, $allowed, true)) {
            return false;
        }

        $sets = ['status = :status'];
        $params = ['id' => $draftId, 'status' => $status];
        if ($status === 'approved') {
            $sets[] = 'approved_at = NOW()';
            $sets[] = 'approved_by = :approved_by';
            $params['approved_by'] = $userId > 0 ? $userId : null;
        }
        if ($status === 'scheduled') {
            $sets[] = 'scheduled_at = COALESCE(scheduled_at, :scheduled_at)';
            $params['scheduled_at'] = social_studio_next_slot(0);
        }

        return db_execute('UPDATE social_studio_drafts SET ' . implode(', ', $sets) . ' WHERE id = :id LIMIT 1', $params);
    }
}
