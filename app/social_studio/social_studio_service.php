<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/openai.php';
require_once dirname(__DIR__) . '/core/google_gemini.php';
require_once __DIR__ . '/elite_smiles_master_cmo.php';

if (!function_exists('social_studio_ensure_schema')) {
    function social_studio_ensure_schema(): void
    {
        db_query("CREATE TABLE IF NOT EXISTS social_studio_base_creatives (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            source_type VARCHAR(32) NOT NULL DEFAULT 'instagram',
            source_url VARCHAR(500) NULL,
            source_post_id VARCHAR(180) NULL,
            title VARCHAR(180) NOT NULL,
            published_at DATE NULL,
            group_name VARCHAR(120) NOT NULL DEFAULT 'Other',
            source_image_url VARCHAR(500) NULL,
            local_image_key VARCHAR(255) NULL,
            analysis_json LONGTEXT NULL,
            base_prompt TEXT NULL,
            overlay_spec TEXT NULL,
            overlay_template_json LONGTEXT NULL,
            analysis_version TINYINT UNSIGNED NOT NULL DEFAULT 1,
            status VARCHAR(32) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_social_source (source_type, source_post_id),
            INDEX idx_social_base_status (status),
            INDEX idx_social_base_date (published_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
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
            base_reference_key VARCHAR(180) NULL,
            base_post_prompt TEXT NULL,
            overlay_spec TEXT NULL,
            overlay_eyebrow VARCHAR(180) NULL,
            overlay_blocks_json TEXT NULL,
            overlay_template_json LONGTEXT NULL,
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
        try {
            db_query("ALTER TABLE social_studio_base_creatives MODIFY COLUMN source_image_url TEXT NULL");
        } catch (Throwable $e) {
            // The column may already be migrated or the hosting DB may not allow DDL here.
        }
        if (!db_one("SHOW COLUMNS FROM social_studio_base_creatives LIKE 'analysis_version'")) {
            db_query("ALTER TABLE social_studio_base_creatives ADD COLUMN analysis_version TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER overlay_spec");
        }
        if (!db_one("SHOW COLUMNS FROM social_studio_base_creatives LIKE 'overlay_template_json'")) {
            db_query("ALTER TABLE social_studio_base_creatives ADD COLUMN overlay_template_json LONGTEXT NULL AFTER overlay_spec");
        }

        foreach ([
            'image_storage_key' => "ALTER TABLE social_studio_drafts ADD COLUMN image_storage_key VARCHAR(255) NULL AFTER image_url",
            'branded_image_storage_key' => "ALTER TABLE social_studio_drafts ADD COLUMN branded_image_storage_key VARCHAR(255) NULL AFTER image_storage_key",
            'image_generated_at' => "ALTER TABLE social_studio_drafts ADD COLUMN image_generated_at DATETIME NULL AFTER branded_image_storage_key",
            'base_reference_key' => "ALTER TABLE social_studio_drafts ADD COLUMN base_reference_key VARCHAR(180) NULL AFTER image_prompt",
            'base_post_prompt' => "ALTER TABLE social_studio_drafts ADD COLUMN base_post_prompt TEXT NULL AFTER base_reference_key",
            'overlay_spec' => "ALTER TABLE social_studio_drafts ADD COLUMN overlay_spec TEXT NULL AFTER base_post_prompt",
            'overlay_eyebrow' => "ALTER TABLE social_studio_drafts ADD COLUMN overlay_eyebrow VARCHAR(180) NULL AFTER overlay_spec",
            'overlay_blocks_json' => "ALTER TABLE social_studio_drafts ADD COLUMN overlay_blocks_json TEXT NULL AFTER overlay_eyebrow",
            'overlay_template_json' => "ALTER TABLE social_studio_drafts ADD COLUMN overlay_template_json LONGTEXT NULL AFTER overlay_blocks_json",
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
        return social_studio_master_cmo_prompt();
        return 'Elite Smiles by Walter Meden DDS is a premium cosmetic dentistry practice in Draper, Utah. Editorial line: warm, sincere, premium, confidence-led, and consultation-focused. Lead with how a natural-looking smile affects photos, conversations, weddings, work, and self-confidence. Use short paragraphs, simple benefit bullets, a clear complimentary-consultation CTA, and Draper, Utah location context. Mention flexible or 0% financing only when useful and always for qualified patients. Avoid guaranteed outcomes, fake patient claims, heavy jargon, and aggressive price-first copy. Visual editorial line learned from the current Instagram feed: 4:5 magazine-style compositions; creamy ivory, warm white, charcoal, black, and restrained champagne-gold; elegant serif display headlines paired with clean sans-serif support text; soft daylight and polished portrait photography; confident women and men, close-up smiles, natural facial expressions, lifestyle confidence, and carefully framed before/after cases. Rotate among editorial education cards, benefit checklists, premium portraits, close-up smile details, before/after transformations, and dark luxury panels. Use generous whitespace, clear hierarchy, one primary visual idea, and readable high-contrast type zones. For portrait or smile imagery, use a deliberate editorial crop with the face and smile large enough to read immediately, tack-sharp eyes and teeth, realistic skin texture, accurate anatomy, and the subject placed inside the frame—not cut off or pushed to the edge. Use one clear focal subject, professional camera focus, crisp detail, and balanced negative space for the separate CRM text layer. Nano Banana must not render logos, brand marks, watermarks, or typography; leave intentional clean space for the CRM’s separate text layout layer. Avoid neon colors, loud gradients, generic dental stock imagery, distorted teeth, exaggerated whitening, cluttered props, soft focus, motion blur, distant subjects, awkward cropping, and overly artificial faces.';
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

if (!function_exists('social_studio_compact_overlay_cta')) {
    function social_studio_compact_overlay_cta(string $cta, string $focus = 'veneers'): string
    {
        $cta = trim(preg_replace('/\s+/', ' ', $cta) ?? $cta);
        if ($cta !== '' && str_word_count($cta) <= 7) {
            return $cta;
        }
        return match ($focus) {
            'implants' => 'Book Your Implant Consultation',
            'lip_repositioning' => 'Explore Lip Repositioning',
            'smile_makeover' => 'Design Your New Smile',
            default => 'Book Your Complimentary Consultation',
        };
    }
}

if (!function_exists('social_studio_base_source_path')) {
    function social_studio_base_source_path(array $base): string
    {
        $storedPath = social_studio_safe_storage_path((string)($base['local_image_key'] ?? ''));
        if ($storedPath && is_file($storedPath)) {
            return $storedPath;
        }
        $candidateIds = [(string)($base['source_post_id'] ?? '')];
        if (preg_match('#/(?:p|reel)/([^/?]+)#', (string)($base['source_url'] ?? ''), $sourceMatch)) {
            $candidateIds[] = (string)$sourceMatch[1];
        }
        foreach (array_unique($candidateIds) as $candidateId) {
            $safePostId = preg_replace('/[^A-Za-z0-9_-]/', '_', $candidateId) ?: '';
            $bundledPath = $safePostId !== ''
                ? dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'social-studio' . DIRECTORY_SEPARATOR . 'instagram' . DIRECTORY_SEPARATOR . $safePostId . '.jpg'
                : '';
            if ($bundledPath !== '' && is_file($bundledPath)) {
                return $bundledPath;
            }
        }
        return '';
    }
}

if (!function_exists('social_studio_base_analysis_progress')) {
    function social_studio_base_analysis_progress(): array
    {
        social_studio_ensure_schema();
        $total = 0;
        $ready = 0;
        foreach (db_all('SELECT source_url, source_post_id, local_image_key, analysis_version FROM social_studio_base_creatives WHERE status = "active" AND source_type = "instagram"') as $base) {
            if (social_studio_base_source_path($base) === '') {
                continue;
            }
            $total++;
            if ((int)($base['analysis_version'] ?? 1) >= 3) {
                $ready++;
            }
        }
        return ['total' => $total, 'ready' => $ready, 'remaining' => max(0, $total - $ready)];
    }
}

if (!function_exists('social_studio_reanalyze_base_creatives')) {
    function social_studio_reanalyze_base_creatives(int $limit = 1): array
    {
        social_studio_ensure_schema();
        $limit = max(1, min(3, $limit));
        $bases = db_all('SELECT id, source_url, source_post_id, title, published_at, group_name, source_image_url, local_image_key FROM social_studio_base_creatives WHERE status = "active" AND source_type = "instagram" AND analysis_version < 3 ORDER BY published_at DESC, id DESC LIMIT 100');
        $bases = array_values(array_filter($bases, static fn(array $base): bool => social_studio_base_source_path($base) !== ''));
        $updated = 0;
        $failed = 0;
        $errors = [];

        foreach ($bases as $base) {
            if ($updated >= $limit) {
                break;
            }
            $path = social_studio_base_source_path($base);
            if (!$path || !is_file($path)) {
                $failed++;
                $errors[] = (string)$base['title'] . ' [' . (string)$base['source_post_id'] . '] ' . (string)$base['source_url'] . ': source image file not found';
                continue;
            }
            $bytes = @file_get_contents($path);
            if (!is_string($bytes) || $bytes === '') {
                $failed++;
                $errors[] = (string)$base['title'] . ': source image could not be read';
                continue;
            }
            $mime = function_exists('mime_content_type') ? (string)(@mime_content_type($path) ?: 'image/jpeg') : 'image/jpeg';
            $post = $base;
            $post['image_url'] = 'data:' . $mime . ';base64,' . base64_encode($bytes);
            $analysis = social_studio_analyze_base_creative($post);
            if (empty($analysis['ok']) || !is_array($analysis['data'] ?? null)) {
                $failed++;
                $errors[] = (string)$base['title'] . ': ' . (string)($analysis['message'] ?? 'OpenAI analysis failed');
                continue;
            }
            $data = $analysis['data'];
            $analysisJson = is_array($data['analysis'] ?? null)
                ? json_encode($data['analysis'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : (string)($data['analysis'] ?? '');
            db_execute('UPDATE social_studio_base_creatives SET title=:title, group_name=:group_name, analysis_json=:analysis_json, base_prompt=:base_prompt, overlay_spec=:overlay_spec, overlay_template_json=:overlay_template_json, analysis_version=3 WHERE id=:id LIMIT 1', [
                'id' => (int)$base['id'],
                'title' => trim((string)($data['title'] ?? '')) ?: (string)$base['title'],
                'group_name' => trim((string)($data['group_name'] ?? '')) ?: (string)$base['group_name'],
                'analysis_json' => $analysisJson,
                'base_prompt' => trim((string)($data['base_prompt'] ?? '')),
                'overlay_spec' => trim((string)($data['overlay_spec'] ?? '')),
                'overlay_template_json' => social_studio_encode_overlay_template((array)($data['overlay_template'] ?? [])),
            ]);
            $updated++;
        }

        return ['updated' => $updated, 'failed' => $failed, 'errors' => array_slice($errors, 0, 4)] + social_studio_base_analysis_progress();
    }
}

if (!function_exists('social_studio_upsert_base_creative')) {
    function social_studio_upsert_base_creative(array $creative): int
    {
        social_studio_ensure_schema();
        $existing = db_one('SELECT id FROM social_studio_base_creatives WHERE source_type = :source_type AND source_post_id = :source_post_id LIMIT 1', [
            'source_type' => (string)($creative['source_type'] ?? 'instagram'),
            'source_post_id' => (string)($creative['source_post_id'] ?? ''),
        ]);
        $params = [
            'source_type' => (string)($creative['source_type'] ?? 'instagram'),
            'source_url' => (string)($creative['source_url'] ?? ''),
            'source_post_id' => (string)($creative['source_post_id'] ?? ''),
            'title' => (string)($creative['title'] ?? 'Untitled base creative'),
            'published_at' => !empty($creative['published_at']) ? $creative['published_at'] : null,
            'group_name' => (string)($creative['group_name'] ?? 'Other'),
            'source_image_url' => (string)($creative['source_image_url'] ?? ''),
            'local_image_key' => (string)($creative['local_image_key'] ?? ''),
            'analysis_json' => is_array($creative['analysis'] ?? null) ? json_encode($creative['analysis'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string)($creative['analysis_json'] ?? ''),
            'base_prompt' => (string)($creative['base_prompt'] ?? ''),
            'overlay_spec' => (string)($creative['overlay_spec'] ?? ''),
            'overlay_template_json' => social_studio_encode_overlay_template((array)($creative['overlay_template'] ?? [])),
        ];
        if ($existing) {
            $updateParams = array_intersect_key($params, array_flip(['source_url', 'title', 'published_at', 'group_name', 'source_image_url', 'local_image_key', 'analysis_json', 'base_prompt', 'overlay_spec', 'overlay_template_json']));
            db_execute('UPDATE social_studio_base_creatives SET source_url=:source_url, title=:title, published_at=:published_at, group_name=:group_name, source_image_url=:source_image_url, local_image_key=:local_image_key, analysis_json=:analysis_json, base_prompt=:base_prompt, overlay_spec=:overlay_spec, overlay_template_json=:overlay_template_json WHERE id=:id LIMIT 1', $updateParams + ['id' => (int)$existing['id']]);
            return (int)$existing['id'];
        }
        return (int)db_insert('INSERT INTO social_studio_base_creatives (source_type, source_url, source_post_id, title, published_at, group_name, source_image_url, local_image_key, analysis_json, base_prompt, overlay_spec, overlay_template_json) VALUES (:source_type,:source_url,:source_post_id,:title,:published_at,:group_name,:source_image_url,:local_image_key,:analysis_json,:base_prompt,:overlay_spec,:overlay_template_json)', $params);
    }
}

if (!function_exists('social_studio_visual_references')) {
    function social_studio_visual_references(): array
    {
        social_studio_ensure_schema();
        $references = [];
        /* The library is intentionally database-backed. Static placeholders made
         * it possible to remix four examples without importing the full window. */
        /* legacy references removed */
        $references = [
            'instagram_2026_veneers_confidence' => [
                'label' => 'Instagram 2026 — Veneers confidence',
                'group' => 'Confidence / life experience',
                'date' => '2026-06-04',
                'description' => 'Current Elite Smiles creative: warm patient portrait, editorial magazine composition, serif headline, short emotional promise, complimentary consultation CTA.',
                'image' => 'assets/img/social-studio/instagram-2026/veneers-confidence.jpg',
            ],
            'instagram_2026_veneers_benefits' => [
                'label' => 'Instagram 2026 — Veneers benefits',
                'group' => 'Educational / benefits',
                'date' => '2026-05-26',
                'description' => 'Current Elite Smiles creative: ivory education card, large serif title, compact benefit list, restrained gold accents, consultation and qualified-financing footer.',
                'image' => 'assets/img/social-studio/instagram-2026/veneers-benefits.jpg',
            ],
            'instagram_2026_lip_repositioning' => [
                'label' => 'Instagram 2026 — Lip repositioning',
                'group' => 'Treatment education',
                'date' => '2026-06-08',
                'description' => 'Current Elite Smiles creative: close lifestyle portrait, treatment name clearly framed, before/after education, natural-looking results, Draper location and concise CTA.',
                'image' => 'assets/img/social-studio/instagram-2026/lip-repositioning.jpg',
            ],
            'instagram_2026_all_on_x' => [
                'label' => 'Instagram 2026 — All-on-X',
                'group' => 'Treatment education',
                'date' => '2026-06-12',
                'description' => 'Current Elite Smiles creative: dark premium clinical panel, benefit-led hierarchy, qualified-patient financing note, confidence/lifestyle close, Draper consultation CTA.',
                'image' => 'assets/img/social-studio/instagram-2026/all-on-x.jpg',
            ],
            'none' => [
                'label' => 'Let the Master CMO choose',
                'group' => 'Master CMO recommendation',
                'date' => '',
                'description' => 'Use the strongest existing Elite Smiles pattern for the selected topic and audience.',
                'image' => '',
            ],
        ];
        foreach (db_all('SELECT id, source_type, source_url, source_post_id, title, published_at, group_name, source_image_url, local_image_key, base_prompt, overlay_spec FROM social_studio_base_creatives WHERE status = "active" ORDER BY published_at DESC, id DESC LIMIT 300') as $base) {
            $key = 'base_' . (int)$base['id'];
            $safePostId = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)($base['source_post_id'] ?? '')) ?: '';
            $bundledImage = $safePostId !== '' ? 'assets/social-studio/instagram/' . $safePostId . '.jpg' : '';
            $bundledPath = $bundledImage !== '' ? dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $bundledImage) : '';
            $storedPath = social_studio_safe_storage_path((string)($base['local_image_key'] ?? ''));
            if ((string)($base['source_type'] ?? '') === 'instagram' && social_studio_base_source_path($base) === '') {
                continue;
            }
            if (str_starts_with((string)($base['source_post_id'] ?? ''), 'draft_') && (!$storedPath || !is_file($storedPath))) {
                continue;
            }
            $references[$key] = [
                'label' => (string)$base['title'],
                'group' => (string)($base['group_name'] ?: 'Instagram base creatives'),
                'date' => (string)($base['published_at'] ?? ''),
                'description' => 'Analyzed base creative. ' . trim((string)($base['overlay_spec'] ?? '')),
                'image_url' => $bundledPath !== '' && is_file($bundledPath)
                    ? base_url($bundledImage)
                    : base_url('app/actions/social_studio_base_image.php?base_id=' . (int)$base['id']),
                'base_prompt' => (string)($base['base_prompt'] ?? ''),
                'source_url' => (string)($base['source_url'] ?? ''),
                'source_image_url' => (string)($base['source_image_url'] ?? ''),
            ];
        }
        foreach (db_all('SELECT id, title, image_storage_key, branded_image_storage_key FROM social_studio_drafts WHERE status IN ("approved", "published") AND (image_storage_key IS NOT NULL OR branded_image_storage_key IS NOT NULL) ORDER BY id DESC LIMIT 20') as $draft) {
            $draftId = (int)$draft['id'];
            $draftStorageKey = (string)($draft['branded_image_storage_key'] ?: $draft['image_storage_key']);
            $draftPath = social_studio_safe_storage_path($draftStorageKey);
            if (!$draftPath || !is_file($draftPath)) {
                continue;
            }
            $references['approved_' . $draftId] = [
                'label' => 'Approved ad — ' . (string)$draft['title'],
                'group' => 'Approved generated ads',
                'date' => '',
                'description' => 'Proven Elite Smiles approved creative. Preserve its strongest angle, hierarchy, CTA pattern, and visual language while creating a new original version.',
                'image_url' => base_url('app/actions/social_studio_image.php?draft_id=' . $draftId . '&variant=branded'),
            ];
        }
        foreach (['instagram_2026_veneers_confidence', 'instagram_2026_veneers_benefits', 'instagram_2026_lip_repositioning', 'instagram_2026_all_on_x', 'none'] as $legacyKey) {
            unset($references[$legacyKey]);
        }
        return $references;
    }
}

if (!function_exists('social_studio_seed_drafts')) {
    function social_studio_overlay_template_schema(): array
    {
        $element = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['text', 'line', 'box']],
                'text' => ['type' => 'string'],
                'x' => ['type' => 'number'], 'y' => ['type' => 'number'],
                'width' => ['type' => 'number'], 'height' => ['type' => 'number'],
                'font_role' => ['type' => 'string', 'enum' => ['serif', 'sans', 'script']],
                'font_size' => ['type' => 'number'], 'font_weight' => ['type' => 'integer'],
                'line_height' => ['type' => 'number'], 'letter_spacing' => ['type' => 'number'],
                'color' => ['type' => 'string'], 'background_color' => ['type' => 'string'],
                'border_color' => ['type' => 'string'], 'border_width' => ['type' => 'number'],
                'border_radius' => ['type' => 'number'],
                'align' => ['type' => 'string', 'enum' => ['left', 'center', 'right']],
                'uppercase' => ['type' => 'boolean'],
            ],
            'required' => ['type', 'text', 'x', 'y', 'width', 'height', 'font_role', 'font_size', 'font_weight', 'line_height', 'letter_spacing', 'color', 'background_color', 'border_color', 'border_width', 'border_radius', 'align', 'uppercase'],
        ];
        return [
            'type' => 'object', 'additionalProperties' => false,
            'properties' => [
                'version' => ['type' => 'integer'],
                'aspect_ratio' => ['type' => 'string', 'enum' => ['1:1', '4:5']],
                'canvas_background' => ['type' => 'string'],
                'image_fit' => ['type' => 'string', 'enum' => ['cover', 'contain']],
                'elements' => ['type' => 'array', 'items' => $element],
            ],
            'required' => ['version', 'aspect_ratio', 'canvas_background', 'image_fit', 'elements'],
        ];
    }

    function social_studio_safe_css_color(string $color, string $fallback = 'transparent'): string
    {
        $color = trim(strtolower($color));
        return $color === 'transparent' || preg_match('/^#[0-9a-f]{3,8}$/', $color) || preg_match('/^rgba?\([0-9.,%\s]+\)$/', $color) ? $color : $fallback;
    }

    function social_studio_normalize_overlay_template(array $template): array
    {
        if (!is_array($template['elements'] ?? null)) return [];
        $elements = [];
        foreach (array_slice($template['elements'], 0, 30) as $element) {
            if (!is_array($element)) continue;
            $elements[] = [
                'type' => in_array((string)($element['type'] ?? ''), ['text', 'line', 'box'], true) ? (string)$element['type'] : 'text',
                'text' => mb_substr((string)($element['text'] ?? ''), 0, 500),
                'x' => max(0, min(100, (float)($element['x'] ?? 0))), 'y' => max(0, min(100, (float)($element['y'] ?? 0))),
                'width' => max(.1, min(100, (float)($element['width'] ?? 10))), 'height' => max(.1, min(100, (float)($element['height'] ?? 5))),
                'font_role' => in_array((string)($element['font_role'] ?? ''), ['serif', 'sans', 'script'], true) ? (string)$element['font_role'] : 'sans',
                'font_size' => max(.5, min(20, (float)($element['font_size'] ?? 3))), 'font_weight' => max(100, min(900, (int)($element['font_weight'] ?? 400))),
                'line_height' => max(.7, min(2.5, (float)($element['line_height'] ?? 1.1))), 'letter_spacing' => max(-.1, min(1, (float)($element['letter_spacing'] ?? 0))),
                'color' => social_studio_safe_css_color((string)($element['color'] ?? '#17202a'), '#17202a'),
                'background_color' => social_studio_safe_css_color((string)($element['background_color'] ?? 'transparent')),
                'border_color' => social_studio_safe_css_color((string)($element['border_color'] ?? 'transparent')),
                'border_width' => max(0, min(2, (float)($element['border_width'] ?? 0))), 'border_radius' => max(0, min(50, (float)($element['border_radius'] ?? 0))),
                'align' => in_array((string)($element['align'] ?? ''), ['left', 'center', 'right'], true) ? (string)$element['align'] : 'left',
                'uppercase' => !empty($element['uppercase']),
            ];
        }
        return ['version' => 1, 'aspect_ratio' => (string)($template['aspect_ratio'] ?? '') === '1:1' ? '1:1' : '4:5', 'canvas_background' => social_studio_safe_css_color((string)($template['canvas_background'] ?? 'transparent')), 'image_fit' => (string)($template['image_fit'] ?? '') === 'contain' ? 'contain' : 'cover', 'elements' => $elements];
    }

    function social_studio_encode_overlay_template(array $template): ?string
    {
        $template = social_studio_normalize_overlay_template($template);
        return $template === [] ? null : (json_encode($template, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null);
    }

    function social_studio_curated_overlay_template(string $sourcePostId): array
    {
        if ($sourcePostId !== 'DZME24slvGK') return [];
        $text = static function (string $value, float $x, float $y, float $width, float $height, string $role, float $size, int $weight, float $lineHeight, float $spacing, string $color, bool $uppercase = false): array {
            return ['type'=>'text','text'=>$value,'x'=>$x,'y'=>$y,'width'=>$width,'height'=>$height,'font_role'=>$role,'font_size'=>$size,'font_weight'=>$weight,'line_height'=>$lineHeight,'letter_spacing'=>$spacing,'color'=>$color,'background_color'=>'transparent','border_color'=>'transparent','border_width'=>0,'border_radius'=>0,'align'=>'left','uppercase'=>$uppercase];
        };
        $elements = [
            $text('YOUR', 7.8, 7.2, 35, 4, 'sans', 2.2, 400, 1, .16, '#20252d', true),
            $text("CONFIDENCE\nSTARTS", 7.8, 12.6, 38, 13, 'serif', 5.4, 400, .9, -.02, '#20252d', true),
            $text('with your smile', 12.2, 25.5, 35, 5, 'script', 4.2, 400, 1, 0, '#9b794e'),
            ['type'=>'line','text'=>'','x'=>7.8,'y'=>32.2,'width'=>12,'height'=>.18,'font_role'=>'sans','font_size'=>1,'font_weight'=>400,'line_height'=>1,'letter_spacing'=>0,'color'=>'transparent','background_color'=>'#9b794e','border_color'=>'transparent','border_width'=>0,'border_radius'=>0,'align'=>'left','uppercase'=>false],
            $text("Custom veneers designed to\nenhance your natural beauty and\nhelp you feel confident every day.", 7.8, 36.5, 37, 10, 'sans', 1.75, 500, 1.35, .01, '#20252d'),
            $text("◇   CUSTOM VENEERS\n     NATURAL. BEAUTIFUL. YOU.", 7.8, 52, 39, 7, 'sans', 1.35, 500, 1.35, .10, '#20252d', true),
            $text("□   COMPLIMENTARY\n     CONSULTATION", 7.8, 62, 39, 7, 'sans', 1.35, 500, 1.35, .10, '#20252d', true),
            $text("$   FLEXIBLE FINANCING\n     OPTIONS", 7.8, 72, 39, 7, 'sans', 1.35, 500, 1.35, .10, '#20252d', true),
            $text("●  DRAPER, UTAH\n\nINVEST IN YOURSELF.\nLOVE YOUR SMILE.", 7.8, 84, 38, 13, 'sans', 1.25, 600, 1.35, .12, '#7f6749', true),
        ];
        return social_studio_normalize_overlay_template(['version'=>1,'aspect_ratio'=>'1:1','canvas_background'=>'transparent','image_fit'=>'cover','elements'=>$elements]);
    }

    function social_studio_analyze_base_creative(array $post): array
    {
        require_once dirname(__DIR__) . '/core/openai.php';
        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'title' => ['type' => 'string'],
                'group_name' => ['type' => 'string'],
                'analysis' => ['type' => 'string'],
                'base_prompt' => ['type' => 'string'],
                'overlay_spec' => ['type' => 'string'],
                'overlay_template' => social_studio_overlay_template_schema(),
            ],
            'required' => ['title', 'group_name', 'analysis', 'base_prompt', 'overlay_spec', 'overlay_template'],
        ];
        $system = 'You are the Elite Smiles Master CMO and visual production director. Analyze the supplied Instagram creative as a LOCKED reusable production template. OCR every visible word exactly, preserving capitalization, punctuation, and line breaks. Represent each text block, divider line, and background box as a separate overlay_template element using percentages of the original canvas. font_size is a percentage of canvas width. Keep logos out of the template. base_prompt must describe ONLY the clean photographic layer and explicitly request no words, logo, watermark, icons, or graphic text. overlay_spec is a human-readable audit. This is extraction, not redesign: never improve, paraphrase, shorten, or invent overlay copy.';
        $user = 'Analyze this existing Elite Smiles Instagram post pixel-by-pixel. Determine whether the source is 1:1 or 4:5. OCR the exact overlay copy and encode a deterministic overlay_template with precise x, y, width, height, typography role, scale, color, and decoration. Use transparent when a fill or border is absent. The template must rebuild the source overlay on a newly generated clean photo without asking the image model to draw text. Source metadata: ' . json_encode(array_diff_key($post, ['image_url' => true]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return elite_openai_json_response($system, $user, $schema, 'elite_smiles_base_creative_analysis', (string)($post['image_url'] ?? ''));
    }

    function social_studio_get_or_create_overlay_template(array $base): array
    {
        $curated = social_studio_curated_overlay_template((string)($base['source_post_id'] ?? ''));
        if ($curated !== []) {
            if (!empty($base['id'])) {
                db_execute('UPDATE social_studio_base_creatives SET overlay_template_json=:overlay_template_json, analysis_version=3 WHERE id=:id LIMIT 1', ['id'=>(int)$base['id'], 'overlay_template_json'=>social_studio_encode_overlay_template($curated)]);
            }
            return $curated;
        }
        $stored = json_decode((string)($base['overlay_template_json'] ?? ''), true);
        $stored = is_array($stored) ? social_studio_normalize_overlay_template($stored) : [];
        if ($stored !== [] && ($stored['elements'] ?? []) !== []) {
            return $stored;
        }

        $path = social_studio_base_source_path($base);
        $bytes = $path !== '' ? @file_get_contents($path) : false;
        if (!is_string($bytes) || $bytes === '') {
            return [];
        }
        $post = $base;
        $mime = function_exists('mime_content_type') ? (string)(@mime_content_type($path) ?: 'image/jpeg') : 'image/jpeg';
        $post['image_url'] = 'data:' . $mime . ';base64,' . base64_encode($bytes);
        $analysis = social_studio_analyze_base_creative($post);
        $data = is_array($analysis['data'] ?? null) ? $analysis['data'] : [];
        $template = social_studio_normalize_overlay_template((array)($data['overlay_template'] ?? []));
        if ($template === [] || ($template['elements'] ?? []) === []) {
            return [];
        }
        if (!empty($base['id'])) {
            db_execute('UPDATE social_studio_base_creatives SET analysis_json=:analysis_json, base_prompt=:base_prompt, overlay_spec=:overlay_spec, overlay_template_json=:overlay_template_json, analysis_version=3 WHERE id=:id LIMIT 1', [
                'id' => (int)$base['id'],
                'analysis_json' => is_array($data['analysis'] ?? null) ? json_encode($data['analysis'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string)($data['analysis'] ?? ''),
                'base_prompt' => trim((string)($data['base_prompt'] ?? ($base['base_prompt'] ?? ''))),
                'overlay_spec' => trim((string)($data['overlay_spec'] ?? ($base['overlay_spec'] ?? ''))),
                'overlay_template_json' => social_studio_encode_overlay_template($template),
            ]);
        }
        return $template;
    }

    function social_studio_seed_drafts(string $focus, int $count, int $createdBy = 0, string $instruction = '', string $inspirationImageDataUrl = '', array $remixTemplate = []): int
    {
        social_studio_ensure_schema();

        $focus = social_studio_normalize_focus($focus);
        $count = max(1, min(7, $count));
        $hashtags = social_studio_default_hashtags($focus);
        $topics = social_studio_generate_topics($focus, $count, $instruction, $inspirationImageDataUrl);
        $created = 0;

        foreach ($topics as $index => $topic) {
            if ($remixTemplate !== []) {
                $topic['base_reference_key'] = (string)($remixTemplate['reference_key'] ?? '');
                $topic['base_post_prompt'] = (string)($remixTemplate['base_prompt'] ?? '');
                $topic['overlay_spec'] = social_studio_locked_overlay_spec($remixTemplate);
                $topic['overlay_template'] = (array)($remixTemplate['overlay_template'] ?? []);
                $topic['image_prompt'] = social_studio_locked_remix_image_prompt($remixTemplate, $topic, $focus);
                $topic['post_type'] = (string)($remixTemplate['purpose'] ?? 'educational') === 'social_ad' ? 'social_ad' : 'education';
                if (!empty($remixTemplate['replica_mode']) && (string)($remixTemplate['source_post_id'] ?? '') === 'DZME24slvGK') {
                    $topic['title'] = 'Your Confidence Starts With Your Smile';
                    $topic['caption'] = 'Custom veneers designed to enhance your natural beauty and help you feel confident every day. Custom veneers. Natural. Beautiful. You. Complimentary consultation and flexible financing options in Draper, Utah.';
                    $topic['cta'] = 'Complimentary Consultation';
                    $topic['overlay_eyebrow'] = 'YOUR';
                    $topic['overlay_blocks'] = ['Custom veneers — Natural. Beautiful. You.', 'Complimentary consultation', 'Flexible financing options'];
                    $topic['overlay_spec'] = "REPLICA_TEMPLATE: confidence_starts\n" . $topic['overlay_spec'];
                    $topic['image_prompt'] .= "\n\n1:1 CONTROL TEST: use the supplied source creative as the exact visual reference. Preserve the same woman, head tilt, expression, hair, wardrobe, indoor background, camera crop, lighting, and subject placement. Remove all existing words, icons, dividers, and graphic marks from the left side and reconstruct that area as clean softly blurred ivory background so the CRM can rebuild the typography separately. Do not add any text or symbols.";
                }
            }
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
                    (title, status, platform, content_focus, post_type, caption, cta, hashtags, image_prompt, base_reference_key, base_post_prompt, overlay_spec, overlay_eyebrow, overlay_blocks_json, overlay_template_json, scheduled_at, created_by)
                 VALUES
                    (:title, 'review', :platform, :content_focus, :post_type, :caption, :cta, :hashtags, :image_prompt, :base_reference_key, :base_post_prompt, :overlay_spec, :overlay_eyebrow, :overlay_blocks_json, :overlay_template_json, :scheduled_at, :created_by)",
                [
                    'title' => $title,
                    'platform' => 'facebook_instagram',
                    'content_focus' => $focus,
                    'post_type' => trim((string)($topic['post_type'] ?? 'education')) ?: 'education',
                    'caption' => $caption,
                    'cta' => social_studio_compact_overlay_cta((string)($topic['cta'] ?? ''), $focus),
                    'hashtags' => implode(' ', $hashtags),
                    'image_prompt' => $imagePrompt,
                    'base_reference_key' => trim((string)($topic['base_reference_key'] ?? '')) ?: null,
                    'base_post_prompt' => trim((string)($topic['base_post_prompt'] ?? '')) ?: null,
                    'overlay_spec' => trim((string)($topic['overlay_spec'] ?? '')) ?: null,
                    'overlay_eyebrow' => trim((string)($topic['overlay_eyebrow'] ?? '')) ?: null,
                    'overlay_blocks_json' => json_encode(array_values(array_filter((array)($topic['overlay_blocks'] ?? []), static fn($item): bool => is_string($item) && trim($item) !== '')), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null,
                    'overlay_template_json' => social_studio_encode_overlay_template((array)($topic['overlay_template'] ?? [])),
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
    function social_studio_generate_topics(string $focus, int $count, string $instruction = '', string $inspirationImageDataUrl = ''): array
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
                    'maxItems' => 7,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'post_type' => ['type' => 'string'],
                            'caption' => ['type' => 'string'],
                            'cta' => ['type' => 'string'],
                            'image_prompt' => ['type' => 'string'],
                            'base_reference_key' => ['type' => 'string'],
                            'base_post_prompt' => ['type' => 'string'],
                            'overlay_spec' => ['type' => 'string'],
                            'overlay_eyebrow' => ['type' => 'string'],
                            'overlay_blocks' => ['type' => 'array', 'maxItems' => 5, 'items' => ['type' => 'string']],
                        ],
                        'required' => ['title', 'post_type', 'caption', 'cta', 'image_prompt', 'base_reference_key', 'base_post_prompt', 'overlay_spec', 'overlay_eyebrow', 'overlay_blocks'],
                    ],
                ],
            ],
            'required' => ['drafts'],
        ];

        $system = 'You are the Elite Smiles Master CMO. Write concise, premium, compliant dental marketing posts using the complete brand operating system below. ' . social_studio_editorial_context();
        $user = "Create {$count} draft social posts for {$focus}. In remix mode, the selected base post is a LOCKED template, not loose inspiration. Preserve its composition, crop, subject scale, palette, typography families, relative font sizes, capitalization pattern, line-break rhythm, exact content-block count, hierarchy, benefit format, CTA treatment, and overlay structure. Change only Focus, Purpose, Audience, Age range, and Text position. Treatment-specific wording may be substituted inside the same content structure; do not add or remove sections. Return overlay_eyebrow as the short overline/kicker used by the base (empty string if the base has none). Return overlay_blocks with exactly the same number and role of supporting text blocks/bullets as the base (empty array if it has none); these are concise on-image words, not caption paragraphs. The CTA must match the base CTA's approximate word count and may never exceed seven words; longer action language belongs in the caption. Return base_reference_key, base_post_prompt, and overlay_spec for every draft. The Nano Banana image prompt must preserve the base visual recipe and request a close, sharp subject with both eyes visible and brilliant bright-white cosmetically perfect teeth where a person is present. The image itself remains unbranded with no text/logo/watermark/typography. Instruction: " . ($instruction !== '' ? $instruction : 'Use the selected base post and requested controls.');
        $response = elite_openai_json_response($system, $user, $schema, 'social_studio_drafts', $inspirationImageDataUrl);
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
                'overlay_eyebrow' => '',
                'overlay_blocks' => [],
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
        return 'Premium dental social media image for "' . $title . '". Realistic cosmetic dentistry or lifestyle setting, bright natural smile, elegant black white and warm gold visual feel. No readable text, no logo, no watermark, no typography, no added branding, no distorted teeth.';
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
        foreach (db_all('SELECT id, source_post_id, overlay_template_json FROM social_studio_base_creatives WHERE status="active" AND source_post_id="DZME24slvGK"') as $curatedBase) {
            $curatedTemplate = social_studio_curated_overlay_template((string)$curatedBase['source_post_id']);
            $curatedJson = social_studio_encode_overlay_template($curatedTemplate);
            if ($curatedJson !== null && $curatedJson !== (string)($curatedBase['overlay_template_json'] ?? '')) {
                db_execute('UPDATE social_studio_base_creatives SET overlay_template_json=:template, analysis_version=3 WHERE id=:id LIMIT 1', ['id'=>(int)$curatedBase['id'], 'template'=>$curatedJson]);
                db_execute('UPDATE social_studio_drafts SET overlay_template_json=:template WHERE base_reference_key=:reference_key', ['template'=>$curatedJson, 'reference_key'=>'base_' . (int)$curatedBase['id']]);
            }
        }
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

if (!function_exists('social_studio_locked_overlay_spec')) {
    function social_studio_locked_overlay_spec(array $template): string
    {
        $position = (string)($template['text_position'] ?? 'left');
        if (!in_array($position, ['left', 'right', 'top', 'bottom'], true)) {
            $position = 'left';
        }
        return "Text position: {$position}. This is the only permitted layout substitution.\n"
            . "LOCKED BASE OVERLAY — preserve typography families, relative font sizes, capitalization, line breaks, block count, spacing, hierarchy, palette, and CTA treatment exactly:\n"
            . trim((string)($template['overlay_spec'] ?? ''));
    }
}

if (!function_exists('social_studio_locked_remix_image_prompt')) {
    function social_studio_locked_remix_image_prompt(array $template, array $topic, string $focus): string
    {
        $variables = [
            '{{FOCUS}}' => social_studio_focus_label($focus),
            '{{PURPOSE}}' => (string)($template['purpose'] ?? 'educational'),
            '{{AUDIENCE}}' => (string)($template['audience'] ?? 'any adult'),
            '{{AGE_RANGE}}' => (string)($template['age_range'] ?? 'any adult'),
            '{{TEXT_POSITION}}' => (string)($template['text_position'] ?? 'left'),
        ];
        $basePrompt = strtr(trim((string)($template['base_prompt'] ?? '')), $variables);
        $topicDirection = trim((string)($topic['image_prompt'] ?? ''));

        return $basePrompt
            . "\n\nLOCKED REMIX CONTRACT: preserve the selected ad's composition, crop, subject scale, camera angle, lighting, background style, negative-space ratio, palette, and visual rhythm. Do not reinterpret the template."
            . "\nControlled substitutions only — Focus: {$variables['{{FOCUS}}']}; Purpose: {$variables['{{PURPOSE}}']}; Audience: {$variables['{{AUDIENCE}}']}; Age range: {$variables['{{AGE_RANGE}}']}; Text position: {$variables['{{TEXT_POSITION}}']}."
            . ($topicDirection !== '' ? "\nTreatment-specific subject direction: {$topicDirection}" : '')
            . "\nPORTRAIT QUALITY: use a close, intentional head-and-shoulders crop when a person is present. The face and smile must be a dominant focal point, both eyes fully visible and tack-sharp, and the teeth brilliant bright white, even, polished, and cosmetically perfect while retaining credible human anatomy. No soft focus, haze, motion blur, distant subject, cut-off face, gray teeth, yellow cast, text, logo, watermark, or typography.";
    }
}

if (!function_exists('social_studio_store_imported_image')) {
    function social_studio_store_imported_image(string $postId, string $bytes, string $mime = ''): string
    {
        if ($bytes === '') {
            return '';
        }
        $size = function_exists('getimagesizefromstring') ? @getimagesizefromstring($bytes) : false;
        if (!is_array($size)) {
            return '';
        }
        $mime = (string)($size['mime'] ?? $mime);
        $extension = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };
        $safeId = preg_replace('/[^A-Za-z0-9_-]/', '_', $postId) ?: 'post';
        $key = 'instagram/' . $safeId . '.' . $extension;
        $path = social_studio_safe_storage_path($key);
        if (!$path || @file_put_contents($path, $bytes) === false) {
            return '';
        }
        return $key;
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
        $referenceImage = [];
        if (preg_match('/^base_(\d+)$/', (string)($draft['base_reference_key'] ?? ''), $baseMatch)) {
            $base = db_one('SELECT source_url, source_post_id, local_image_key FROM social_studio_base_creatives WHERE id=:id AND status="active" LIMIT 1', ['id' => (int)$baseMatch[1]]);
            $referencePath = $base ? social_studio_base_source_path($base) : '';
            if ($referencePath !== '' && is_file($referencePath)) {
                $referenceBytes = @file_get_contents($referencePath);
                if (is_string($referenceBytes) && $referenceBytes !== '') {
                    $referenceImage = ['bytes' => $referenceBytes, 'mime_type' => (string)(@mime_content_type($referencePath) ?: 'image/jpeg')];
                }
            }
        }
        $overlayTemplate = json_decode((string)($draft['overlay_template_json'] ?? ''), true);
        $templateSquare = is_array($overlayTemplate) && (string)($overlayTemplate['aspect_ratio'] ?? '') === '1:1';
        $generated = social_studio_generate_image_binary($prompt, $referenceImage, $templateSquare ? [
            'aspect_ratio' => 'ASPECT_RATIO_ONE_BY_ONE',
            'image_size' => 'IMAGE_SIZE_TWO_K',
            'output_requirement' => 'Return one square 1:1 image matching the supplied Instagram source canvas.',
        ] : []);
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

        $hasOverlayTemplate = is_array($overlayTemplate) && ($overlayTemplate['elements'] ?? []) !== [];
        $brandedExt = $hasOverlayTemplate ? 'svg' : (social_studio_can_raster_brand_images() ? 'png' : 'svg');
        $brandedKey = $storagePrefix . '/branded-' . date('Ymd-His') . '.' . $brandedExt;
        $brandedPath = social_studio_safe_storage_path($brandedKey);
        if (!$brandedPath || !social_studio_create_branded_image($rawPath, $brandedPath, $hasOverlayTemplate ? $overlayTemplate : [])) {
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

        if (str_starts_with((string)($draft['base_reference_key'] ?? ''), 'base_') && trim((string)($draft['base_post_prompt'] ?? '')) !== '') {
            $template = json_decode((string)($draft['overlay_template_json'] ?? ''), true);
            $ratio = is_array($template) && (string)($template['aspect_ratio'] ?? '') === '1:1' ? 'square 1:1' : 'vertical 4:5';
            return $basePrompt . "\n\nFinal output safeguards: {$ratio} Instagram composition. Create ONLY the clean photographic layer behind the saved CRM overlay. If a person is present, use a close head-and-shoulders crop with the face and smile dominant, both eyes completely visible and tack-sharp, and brilliant bright-white cosmetically perfect teeth with credible anatomy. Preserve the locked base composition, subject scale, palette, lighting, negative space, and camera angle. Do not render any words from the source. No text, logo, watermark, typography, icons, graphic lines, soft focus, haze, or cut-off face.";
        }

        if (elite_openai_is_configured()) {
            $schema = [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'prompt' => ['type' => 'string'],
                ],
                'required' => ['prompt'],
            ];
            $system = 'You write image generation prompts for premium dental social media. Images must remain unbranded; do not ask the image model or CRM to render logos, words, captions, text, watermarks, badges, or typography. ' . social_studio_editorial_context();
            $user = "Create one precise Nano Banana image prompt.\nTitle: {$title}\nFocus: {$focus}\nCaption: {$caption}\nExisting visual direction: {$basePrompt}\nRules: no text in image, no logo, no watermarks, premium cosmetic dentistry, realistic clean image, bright attractive smile where relevant, no distorted teeth or extra teeth, suitable for Facebook/Instagram feed. Specify a sharp primary focal subject, crisp eyes and teeth, close intentional framing, professional portrait-camera focus, and no soft focus or distant/cut-off subject. Keep clean negative space only where the CRM editorial layer will sit.";
            $response = elite_openai_json_response($system, $user, $schema, 'social_image_prompt');
            if (!empty($response['ok']) && is_string($response['data']['prompt'] ?? null)) {
                $prompt = trim((string)$response['data']['prompt']);
                if ($prompt !== '') {
                    return $prompt;
                }
            }
        }

        return trim($basePrompt . "\n\nCreate a premium dental social media image for: {$title}. Focus: {$focus}. Match the Elite Smiles editorial line: clean bright natural-looking smile, real human moment, soft daylight, warm neutral whites, restrained charcoal and champagne-gold accents, one clear subject, close human framing, generous breathing room, realistic dental anatomy, premium but approachable. No readable text, no logo, no watermark, no typography, no added branding, loud gradients, neon colors, exaggerated whitening, split-screen collage, or clutter.");
    }
}

if (!function_exists('social_studio_generate_image_binary')) {
    function social_studio_generate_image_binary(string $prompt, array $referenceImage = [], array $format = []): array
    {
        if (!elite_gemini_is_configured()) {
            return social_studio_placeholder_image_binary($prompt);
        }

        $model = defined('GOOGLE_GEMINI_IMAGE_MODEL') ? trim((string)GOOGLE_GEMINI_IMAGE_MODEL) : 'gemini-3.1-flash-image';
        $apiKey = defined('GOOGLE_GEMINI_API_KEY') ? trim((string)GOOGLE_GEMINI_API_KEY) : '';
        if ($apiKey === '') {
            return social_studio_placeholder_image_binary($prompt);
        }

        $imageFormat = [
            'aspectRatio' => (string)($format['aspect_ratio'] ?? 'ASPECT_RATIO_FOUR_BY_FIVE'),
        ];
        if (str_contains(strtolower($model), 'gemini-3')) {
            $imageFormat['imageSize'] = (string)($format['image_size'] ?? 'IMAGE_SIZE_TWO_K');
        }

        $parts = [[
            'text' => $prompt . "\n\nOutput requirement: " . (string)($format['output_requirement'] ?? 'Return one vertical 4:5 portrait image composed for an Instagram feed post.'),
        ]];
        if (is_string($referenceImage['bytes'] ?? null) && $referenceImage['bytes'] !== '') {
            $parts[] = [
                'inlineData' => [
                    'mimeType' => (string)($referenceImage['mime_type'] ?? 'image/jpeg'),
                    'data' => base64_encode($referenceImage['bytes']),
                ],
            ];
        }

        $payload = [
            'contents' => [[
                'role' => 'user',
                'parts' => $parts,
            ]],
            'generationConfig' => [
                'responseModalities' => ['IMAGE'],
                'responseFormat' => [
                    'image' => $imageFormat,
                ],
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
    function social_studio_create_branded_image(string $sourcePath, string $targetPath, array $overlayTemplate = []): bool
    {
        if ($overlayTemplate !== []) {
            return social_studio_create_branded_svg($sourcePath, $targetPath, $overlayTemplate);
        }
        if (@copy($sourcePath, $targetPath)) {
            return true;
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

        $ok = imagepng($source, $targetPath, 6);
        imagedestroy($source);
        return $ok;
    }
}

if (!function_exists('social_studio_create_branded_svg')) {
    function social_studio_create_branded_svg(string $sourcePath, string $targetPath, array $overlayTemplate = []): bool
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
        $sourceData = 'data:' . $sourceMime . ';base64,' . base64_encode($sourceBytes);
        $template = social_studio_normalize_overlay_template($overlayTemplate);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">'
            . '<rect width="100%" height="100%" fill="' . htmlspecialchars((string)($template['canvas_background'] ?? 'transparent'), ENT_QUOTES, 'UTF-8') . '"/>'
            . '<image href="' . $sourceData . '" x="0" y="0" width="' . $width . '" height="' . $height . '" preserveAspectRatio="xMidYMid slice"/>';
        foreach (($template['elements'] ?? []) as $element) {
            $x = (float)$element['x'] * $width / 100; $y = (float)$element['y'] * $height / 100;
            $w = (float)$element['width'] * $width / 100; $h = (float)$element['height'] * $height / 100;
            $fill = htmlspecialchars((string)$element['background_color'], ENT_QUOTES, 'UTF-8');
            $stroke = htmlspecialchars((string)$element['border_color'], ENT_QUOTES, 'UTF-8');
            $strokeWidth = (float)$element['border_width'] * $width / 100;
            if ($element['type'] === 'box' || $element['type'] === 'line') {
                $svg .= '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="' . $h . '" rx="' . ((float)$element['border_radius'] * $width / 100) . '" fill="' . $fill . '" stroke="' . $stroke . '" stroke-width="' . $strokeWidth . '"/>';
                continue;
            }
            $fontFamily = match ((string)$element['font_role']) { 'serif' => 'Georgia,Times New Roman,serif', 'script' => 'Brush Script MT,Segoe Script,cursive', default => 'Arial,Helvetica,sans-serif' };
            $fontSize = (float)$element['font_size'] * $width / 100;
            $anchor = match ((string)$element['align']) { 'center' => 'middle', 'right' => 'end', default => 'start' };
            $textX = $x + ((string)$element['align'] === 'center' ? $w / 2 : ((string)$element['align'] === 'right' ? $w : 0));
            $text = !empty($element['uppercase']) ? mb_strtoupper((string)$element['text']) : (string)$element['text'];
            $lines = preg_split('/\R/u', $text) ?: [$text];
            $svg .= '<text x="' . $textX . '" y="' . ($y + $fontSize) . '" fill="' . htmlspecialchars((string)$element['color'], ENT_QUOTES, 'UTF-8') . '" font-family="' . htmlspecialchars($fontFamily, ENT_QUOTES, 'UTF-8') . '" font-size="' . $fontSize . '" font-weight="' . (int)$element['font_weight'] . '" letter-spacing="' . ((float)$element['letter_spacing'] * $fontSize) . '" text-anchor="' . $anchor . '">';
            foreach ($lines as $lineIndex => $line) {
                $svg .= '<tspan x="' . $textX . '" dy="' . ($lineIndex === 0 ? 0 : ((float)$element['line_height'] * $fontSize)) . '">' . htmlspecialchars((string)$line, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</tspan>';
            }
            $svg .= '</text>';
        }
        $svg .= '</svg>';
        return @file_put_contents($targetPath, $svg) !== false;
    }
}

if (!function_exists('social_studio_update_status')) {
    function social_studio_delete_draft(int $draftId): bool
    {
        social_studio_ensure_schema();
        if ($draftId <= 0) {
            return false;
        }
        $draft = db_one('SELECT image_storage_key, branded_image_storage_key FROM social_studio_drafts WHERE id = :id LIMIT 1', ['id' => $draftId]);
        if (!$draft) {
            return false;
        }
        foreach (['image_storage_key', 'branded_image_storage_key'] as $key) {
            $path = social_studio_safe_storage_path((string)($draft[$key] ?? ''));
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
        }
        return db_query('DELETE FROM social_studio_drafts WHERE id = :id', ['id' => $draftId])->rowCount() > 0;
    }

    function social_studio_delete_all_drafts(): int
    {
        social_studio_ensure_schema();
        $drafts = db_all('SELECT image_storage_key, branded_image_storage_key FROM social_studio_drafts');
        foreach ($drafts as $draft) {
            foreach (['image_storage_key', 'branded_image_storage_key'] as $key) {
                $path = social_studio_safe_storage_path((string)($draft[$key] ?? ''));
                if ($path !== '' && is_file($path)) {
                    @unlink($path);
                }
            }
        }
        return (int)db_query('DELETE FROM social_studio_drafts')->rowCount();
    }

    function social_studio_promote_approved_draft(int $draftId): bool
    {
        social_studio_ensure_schema();
        $draft = db_one('SELECT * FROM social_studio_drafts WHERE id = :id LIMIT 1', ['id' => $draftId]);
        if (!$draft) {
            return false;
        }
        $sourceImageUrl = base_url('app/actions/social_studio_image.php?draft_id=' . $draftId . '&variant=branded');
        $analysis = json_encode([
            'source' => 'approved_social_studio_draft',
            'draft_id' => $draftId,
            'focus' => (string)($draft['content_focus'] ?? ''),
            'purpose' => (string)($draft['post_type'] ?? ''),
            'caption' => (string)($draft['caption'] ?? ''),
            'cta' => (string)($draft['cta'] ?? ''),
            'hashtags' => (string)($draft['hashtags'] ?? ''),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        social_studio_upsert_base_creative([
            'source_type' => 'approved_draft',
            'source_url' => '',
            'source_post_id' => 'draft_' . $draftId,
            'title' => (string)($draft['title'] ?? ('Approved creative ' . $draftId)),
            'published_at' => date('Y-m-d'),
            'group_name' => (string)($draft['content_focus'] ?? 'Approved creative'),
            'source_image_url' => $sourceImageUrl,
            'local_image_key' => (string)($draft['branded_image_storage_key'] ?: ($draft['image_storage_key'] ?? '')),
            'analysis_json' => $analysis ?: '{}',
            'base_prompt' => (string)($draft['base_post_prompt'] ?: ($draft['image_prompt'] ?? '')),
            'overlay_spec' => (string)($draft['overlay_spec'] ?? ''),
            'overlay_template' => (array)(json_decode((string)($draft['overlay_template_json'] ?? ''), true) ?: []),
        ]);
        return true;
    }

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

        $ok = db_execute('UPDATE social_studio_drafts SET ' . implode(', ', $sets) . ' WHERE id = :id LIMIT 1', $params);
        if ($ok && $status === 'approved') {
            social_studio_promote_approved_draft($draftId);
        }
        return $ok;
    }
}
