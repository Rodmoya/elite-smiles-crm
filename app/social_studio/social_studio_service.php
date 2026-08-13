<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/openai.php';
require_once dirname(__DIR__) . '/core/google_gemini.php';
require_once __DIR__ . '/elite_smiles_master_cmo.php';
require_once __DIR__ . '/social_studio_creative_brief.php';

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
            clean_image_key VARCHAR(255) NULL,
            source_caption MEDIUMTEXT NULL,
            source_hashtags TEXT NULL,
            analysis_json LONGTEXT NULL,
            base_prompt TEXT NULL,
            overlay_spec TEXT NULL,
            overlay_template_json LONGTEXT NULL,
            analysis_version TINYINT UNSIGNED NOT NULL DEFAULT 1,
            analysis_status VARCHAR(24) NOT NULL DEFAULT 'pending',
            analysis_error TEXT NULL,
            analyzed_at DATETIME NULL,
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
            generation_status VARCHAR(24) NOT NULL DEFAULT 'pending',
            generation_error TEXT NULL,
            scheduled_at DATETIME NULL,
            approved_at DATETIME NULL,
            approved_by INT UNSIGNED NULL,
            published_at DATETIME NULL,
            meta_post_id VARCHAR(120) NULL,
            meta_instagram_post_id VARCHAR(120) NULL,
            meta_facebook_post_id VARCHAR(120) NULL,
            publish_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            publish_error TEXT NULL,
            publish_started_at DATETIME NULL,
            last_publish_attempt_at DATETIME NULL,
            notes TEXT NULL,
            creation_mode VARCHAR(24) NOT NULL DEFAULT 'remix',
            creative_brief_json LONGTEXT NULL,
            reference_reason TEXT NULL,
            guardrail_json LONGTEXT NULL,
            parent_draft_id INT UNSIGNED NULL,
            version_number SMALLINT UNSIGNED NOT NULL DEFAULT 1,
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
            'source_caption' => "ALTER TABLE social_studio_base_creatives ADD COLUMN source_caption MEDIUMTEXT NULL AFTER local_image_key",
            'source_hashtags' => "ALTER TABLE social_studio_base_creatives ADD COLUMN source_hashtags TEXT NULL AFTER source_caption",
            'analysis_status' => "ALTER TABLE social_studio_base_creatives ADD COLUMN analysis_status VARCHAR(24) NOT NULL DEFAULT 'pending' AFTER analysis_version",
            'analysis_error' => "ALTER TABLE social_studio_base_creatives ADD COLUMN analysis_error TEXT NULL AFTER analysis_status",
            'analyzed_at' => "ALTER TABLE social_studio_base_creatives ADD COLUMN analyzed_at DATETIME NULL AFTER analysis_error",
            'clean_image_key' => "ALTER TABLE social_studio_base_creatives ADD COLUMN clean_image_key VARCHAR(255) NULL AFTER local_image_key",
        ] as $column => $sql) {
            $quotedColumn = db()->quote($column);
            if (!db_one("SHOW COLUMNS FROM social_studio_base_creatives LIKE {$quotedColumn}")) {
                db_query($sql);
            }
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
            'copy_mode' => "ALTER TABLE social_studio_drafts ADD COLUMN copy_mode VARCHAR(32) NOT NULL DEFAULT 'preserve' AFTER overlay_template_json",
            'text_position' => "ALTER TABLE social_studio_drafts ADD COLUMN text_position VARCHAR(16) NOT NULL DEFAULT 'source' AFTER copy_mode",
            'generation_status' => "ALTER TABLE social_studio_drafts ADD COLUMN generation_status VARCHAR(24) NOT NULL DEFAULT 'pending' AFTER image_generated_at",
            'generation_error' => "ALTER TABLE social_studio_drafts ADD COLUMN generation_error TEXT NULL AFTER generation_status",
            'meta_instagram_post_id' => "ALTER TABLE social_studio_drafts ADD COLUMN meta_instagram_post_id VARCHAR(120) NULL AFTER meta_post_id",
            'meta_facebook_post_id' => "ALTER TABLE social_studio_drafts ADD COLUMN meta_facebook_post_id VARCHAR(120) NULL AFTER meta_instagram_post_id",
            'publish_attempts' => "ALTER TABLE social_studio_drafts ADD COLUMN publish_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER meta_facebook_post_id",
            'publish_error' => "ALTER TABLE social_studio_drafts ADD COLUMN publish_error TEXT NULL AFTER publish_attempts",
            'publish_started_at' => "ALTER TABLE social_studio_drafts ADD COLUMN publish_started_at DATETIME NULL AFTER publish_error",
            'last_publish_attempt_at' => "ALTER TABLE social_studio_drafts ADD COLUMN last_publish_attempt_at DATETIME NULL AFTER publish_started_at",
            'creation_mode' => "ALTER TABLE social_studio_drafts ADD COLUMN creation_mode VARCHAR(24) NOT NULL DEFAULT 'remix' AFTER notes",
            'creative_brief_json' => "ALTER TABLE social_studio_drafts ADD COLUMN creative_brief_json LONGTEXT NULL AFTER creation_mode",
            'reference_reason' => "ALTER TABLE social_studio_drafts ADD COLUMN reference_reason TEXT NULL AFTER creative_brief_json",
            'guardrail_json' => "ALTER TABLE social_studio_drafts ADD COLUMN guardrail_json LONGTEXT NULL AFTER reference_reason",
            'parent_draft_id' => "ALTER TABLE social_studio_drafts ADD COLUMN parent_draft_id INT UNSIGNED NULL AFTER guardrail_json",
            'version_number' => "ALTER TABLE social_studio_drafts ADD COLUMN version_number SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER parent_draft_id",
        ] as $column => $sql) {
            // MariaDB does not accept bound parameters in SHOW COLUMNS LIKE clauses.
            // Quote the value through PDO, then keep the DDL itself fixed and controlled.
            $quotedColumn = db()->quote($column);
            if (!db_one("SHOW COLUMNS FROM social_studio_drafts LIKE {$quotedColumn}")) {
                db_query($sql);
            }
        }
        db_execute('UPDATE social_studio_base_creatives SET analysis_status="ready", analyzed_at=COALESCE(analyzed_at, updated_at) WHERE analysis_version >= 4 AND analysis_status="pending"');
        db_execute('UPDATE social_studio_drafts SET generation_status="ready", generation_error=NULL WHERE image_storage_key IS NOT NULL AND image_storage_key <> "" AND generation_status="pending"');
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
            'publishing' => 'Publishing',
            'publish_failed' => 'Publish failed',
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
            'dental_education' => ['#EliteSmilesUtah', '#DentalEducation', '#CosmeticDentistry', '#DraperUtah', '#SmileDesign', '#OralHealth', '#DentalTechnology', '#DraperDentist'],
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
            'dental_education' => 'Dental Education',
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
    function social_studio_sync_bundled_creatives(): int
    {
        $directory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'social-studio' . DIRECTORY_SEPARATOR . 'instagram';
        if (!is_dir($directory)) return 0;
        $existing = [];
        foreach (db_all('SELECT source_post_id FROM social_studio_base_creatives WHERE source_type="instagram"') as $row) {
            $existing[(string)$row['source_post_id']] = true;
        }
        $created = 0;
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*.jpg') ?: [] as $path) {
            $postId = pathinfo($path, PATHINFO_FILENAME);
            if ($postId === '' || isset($existing[$postId])) continue;
            db_insert('INSERT INTO social_studio_base_creatives (source_type, source_url, source_post_id, title, group_name, analysis_version, analysis_status, status) VALUES ("instagram", :source_url, :source_post_id, :title, "Pending analysis", 0, "pending", "active")', [
                'source_url' => 'https://www.instagram.com/p/' . rawurlencode($postId) . '/',
                'source_post_id' => $postId,
                'title' => 'Instagram post ' . $postId,
            ]);
            $existing[$postId] = true;
            $created++;
        }
        return $created;
    }

    function social_studio_base_analysis_progress(): array
    {
        social_studio_ensure_schema();
        social_studio_sync_bundled_creatives();
        $total = 0;
        $ready = 0;
        foreach (db_all('SELECT source_url, source_post_id, local_image_key, analysis_version, analysis_status, overlay_template_json, base_prompt FROM social_studio_base_creatives WHERE status = "active" AND source_type = "instagram" AND (published_at IS NULL OR published_at >= "2026-03-16")') as $base) {
            if (social_studio_base_source_path($base) === '') {
                continue;
            }
            $total++;
            $template = json_decode((string)($base['overlay_template_json'] ?? ''), true);
            if ((int)($base['analysis_version'] ?? 0) >= 4
                && is_array($template) && ($template['elements'] ?? []) !== []
                && trim((string)($base['base_prompt'] ?? '')) !== '') {
                $ready++;
            }
        }
        return ['total' => $total, 'ready' => $ready, 'remaining' => max(0, $total - $ready)];
    }
}

if (!function_exists('social_studio_reanalyze_base_creatives')) {
    function social_studio_reanalyze_base_creatives(int $limit = 1, int $baseId = 0): array
    {
        social_studio_ensure_schema();
        social_studio_sync_bundled_creatives();
        $limit = 1;
        $baseFilter = $baseId > 0 ? ' AND id = :base_id' : '';
        $bases = db_all('SELECT id, source_url, source_post_id, title, published_at, group_name, source_image_url, local_image_key FROM social_studio_base_creatives WHERE status = "active" AND source_type = "instagram" AND analysis_version < 4 AND (published_at IS NULL OR published_at >= "2026-03-16")' . $baseFilter . ' ORDER BY published_at DESC, id DESC LIMIT 100', $baseId > 0 ? ['base_id' => $baseId] : []);
        $bases = array_values(array_filter($bases, static fn(array $base): bool => social_studio_base_source_path($base) !== ''));
        $updated = 0;
        $failed = 0;
        $errors = [];

        foreach ($bases as $base) {
            if ($updated >= $limit) {
                break;
            }
            $path = social_studio_base_source_path($base);
            db_execute('UPDATE social_studio_base_creatives SET analysis_status="processing", analysis_error=NULL WHERE id=:id LIMIT 1', ['id' => (int)$base['id']]);
            if (!$path || !is_file($path)) {
                $failed++;
                $message = (string)$base['title'] . ' [' . (string)$base['source_post_id'] . '] ' . (string)$base['source_url'] . ': source image file not found';
                $errors[] = $message;
                db_execute('UPDATE social_studio_base_creatives SET analysis_status="failed", analysis_error=:error WHERE id=:id LIMIT 1', ['id' => (int)$base['id'], 'error' => $message]);
                continue;
            }
            $bytes = @file_get_contents($path);
            if (!is_string($bytes) || $bytes === '') {
                $failed++;
                $message = (string)$base['title'] . ': source image could not be read';
                $errors[] = $message;
                db_execute('UPDATE social_studio_base_creatives SET analysis_status="failed", analysis_error=:error WHERE id=:id LIMIT 1', ['id' => (int)$base['id'], 'error' => $message]);
                continue;
            }
            $mime = function_exists('mime_content_type') ? (string)(@mime_content_type($path) ?: 'image/jpeg') : 'image/jpeg';
            $post = $base;
            $post['image_url'] = 'data:' . $mime . ';base64,' . base64_encode($bytes);
            $analysis = social_studio_analyze_base_creative($post);
            if (empty($analysis['ok']) || !is_array($analysis['data'] ?? null)) {
                $failed++;
                $message = (string)$base['title'] . ': ' . (string)($analysis['message'] ?? 'OpenAI analysis failed');
                $errors[] = $message;
                db_execute('UPDATE social_studio_base_creatives SET analysis_status="failed", analysis_error=:error WHERE id=:id LIMIT 1', ['id' => (int)$base['id'], 'error' => $message]);
                continue;
            }
            $data = $analysis['data'];
            $analysisJson = is_array($data['analysis'] ?? null)
                ? json_encode($data['analysis'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : (string)($data['analysis'] ?? '');
            db_execute('UPDATE social_studio_base_creatives SET title=:title, group_name=:group_name, analysis_json=:analysis_json, base_prompt=:base_prompt, overlay_spec=:overlay_spec, overlay_template_json=:overlay_template_json, analysis_version=4, analysis_status="ready", analysis_error=NULL, analyzed_at=NOW() WHERE id=:id LIMIT 1', [
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
            'clean_image_key' => trim((string)($creative['clean_image_key'] ?? '')) ?: null,
            'analysis_json' => is_array($creative['analysis'] ?? null) ? json_encode($creative['analysis'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string)($creative['analysis_json'] ?? ''),
            'base_prompt' => (string)($creative['base_prompt'] ?? ''),
            'overlay_spec' => (string)($creative['overlay_spec'] ?? ''),
            'overlay_template_json' => social_studio_encode_overlay_template((array)($creative['overlay_template'] ?? [])),
            'source_caption' => trim((string)($creative['source_caption'] ?? '')) ?: null,
            'source_hashtags' => trim((string)($creative['source_hashtags'] ?? '')) ?: null,
            'analysis_version' => !empty($creative['overlay_template']) ? 4 : 0,
            'analysis_status' => !empty($creative['overlay_template']) ? 'ready' : 'pending',
            'analyzed_at' => !empty($creative['overlay_template']) ? date('Y-m-d H:i:s') : null,
        ];
        if ($existing) {
            $updateParams = array_intersect_key($params, array_flip(['source_url', 'title', 'published_at', 'group_name', 'source_image_url', 'local_image_key', 'clean_image_key', 'source_caption', 'source_hashtags', 'analysis_json', 'base_prompt', 'overlay_spec', 'overlay_template_json', 'analysis_version', 'analysis_status', 'analyzed_at']));
            db_execute('UPDATE social_studio_base_creatives SET source_url=:source_url, title=:title, published_at=:published_at, group_name=:group_name, source_image_url=:source_image_url, local_image_key=:local_image_key, clean_image_key=:clean_image_key, source_caption=:source_caption, source_hashtags=:source_hashtags, analysis_json=:analysis_json, base_prompt=:base_prompt, overlay_spec=:overlay_spec, overlay_template_json=:overlay_template_json, analysis_version=:analysis_version, analysis_status=:analysis_status, analysis_error=NULL, analyzed_at=:analyzed_at WHERE id=:id LIMIT 1', $updateParams + ['id' => (int)$existing['id']]);
            return (int)$existing['id'];
        }
        return (int)db_insert('INSERT INTO social_studio_base_creatives (source_type, source_url, source_post_id, title, published_at, group_name, source_image_url, local_image_key, clean_image_key, source_caption, source_hashtags, analysis_json, base_prompt, overlay_spec, overlay_template_json, analysis_version, analysis_status, analyzed_at) VALUES (:source_type,:source_url,:source_post_id,:title,:published_at,:group_name,:source_image_url,:local_image_key,:clean_image_key,:source_caption,:source_hashtags,:analysis_json,:base_prompt,:overlay_spec,:overlay_template_json,:analysis_version,:analysis_status,:analyzed_at)', $params);
    }
}

if (!function_exists('social_studio_base_is_ready')) {
    function social_studio_base_is_ready(array $base): bool
    {
        $template = json_decode((string)($base['overlay_template_json'] ?? ''), true);
        return (int)($base['analysis_version'] ?? 0) >= 4
            && is_array($template)
            && ($template['elements'] ?? []) !== []
            && trim((string)($base['base_prompt'] ?? '')) !== '';
    }
}

if (!function_exists('social_studio_visual_references')) {
    function social_studio_visual_references(): array
    {
        social_studio_ensure_schema();
        social_studio_sync_bundled_creatives();
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
        foreach (db_all('SELECT id, source_type, source_url, source_post_id, title, published_at, group_name, source_image_url, local_image_key, source_caption, source_hashtags, base_prompt, overlay_spec, overlay_template_json, analysis_version, analysis_status, analysis_error FROM social_studio_base_creatives WHERE status = "active" AND (source_type <> "instagram" OR published_at IS NULL OR published_at >= "2026-03-16") ORDER BY published_at DESC, id DESC LIMIT 300') as $base) {
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
                'source_caption' => (string)($base['source_caption'] ?? ''),
                'source_hashtags' => (string)($base['source_hashtags'] ?? ''),
                'ready' => social_studio_base_is_ready($base),
                'analysis_status' => social_studio_base_is_ready($base) ? 'ready' : (string)($base['analysis_status'] ?: 'pending'),
                'analysis_error' => (string)($base['analysis_error'] ?? ''),
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
                'ready' => true,
                'analysis_status' => 'ready',
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
                'font_family' => ['type' => 'string', 'enum' => ['bodoni', 'didot', 'playfair', 'garamond', 'georgia', 'montserrat', 'helvetica', 'arial', 'arial_narrow', 'script']],
                'font_style' => ['type' => 'string', 'enum' => ['normal', 'italic']],
                'font_size' => ['type' => 'number'], 'font_weight' => ['type' => 'integer'],
                'line_height' => ['type' => 'number'], 'letter_spacing' => ['type' => 'number'],
                'color' => ['type' => 'string'], 'background_color' => ['type' => 'string'],
                'border_color' => ['type' => 'string'], 'border_width' => ['type' => 'number'],
                'border_radius' => ['type' => 'number'],
                'align' => ['type' => 'string', 'enum' => ['left', 'center', 'right']],
                'uppercase' => ['type' => 'boolean'],
            ],
            'required' => ['type', 'text', 'x', 'y', 'width', 'height', 'font_role', 'font_family', 'font_style', 'font_size', 'font_weight', 'line_height', 'letter_spacing', 'color', 'background_color', 'border_color', 'border_width', 'border_radius', 'align', 'uppercase'],
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

    function social_studio_overlay_font_stack(array $element): string
    {
        return match ((string)($element['font_family'] ?? '')) {
            'didot' => "Didot,'Bodoni MT','Times New Roman',serif",
            'playfair' => "'Playfair Display',Georgia,'Times New Roman',serif",
            'garamond' => "Garamond,'EB Garamond',Georgia,serif",
            'georgia' => "Georgia,'Times New Roman',serif",
            'montserrat' => "Montserrat,'Avenir Next',Arial,sans-serif",
            'arial' => "Arial,Helvetica,sans-serif",
            'arial_narrow' => "'Arial Narrow','Aptos Narrow',Arial,sans-serif",
            'script' => "'Segoe Script','Brush Script MT',cursive",
            'bodoni' => "'Bodoni MT',Didot,'Times New Roman',serif",
            default => (string)($element['font_role'] ?? '') === 'serif'
                ? "'Bodoni MT',Didot,'Times New Roman',serif"
                : ((string)($element['font_role'] ?? '') === 'script' ? "'Segoe Script','Brush Script MT',cursive" : "Helvetica,Arial,sans-serif"),
        };
    }

    function social_studio_normalize_overlay_template(array $template): array
    {
        if (!is_array($template['elements'] ?? null)) return [];
        $elements = [];
        foreach (array_slice($template['elements'], 0, 30) as $element) {
            if (!is_array($element)) continue;
            $role = in_array((string)($element['font_role'] ?? ''), ['serif', 'sans', 'script'], true) ? (string)$element['font_role'] : 'sans';
            $defaultFamily = match ($role) { 'serif' => 'bodoni', 'script' => 'script', default => 'helvetica' };
            $family = in_array((string)($element['font_family'] ?? ''), ['bodoni', 'didot', 'playfair', 'garamond', 'georgia', 'montserrat', 'helvetica', 'arial', 'arial_narrow', 'script'], true) ? (string)$element['font_family'] : $defaultFamily;
            $elements[] = [
                'type' => in_array((string)($element['type'] ?? ''), ['text', 'line', 'box'], true) ? (string)$element['type'] : 'text',
                'text' => mb_substr((string)($element['text'] ?? ''), 0, 500),
                'x' => max(0, min(100, (float)($element['x'] ?? 0))), 'y' => max(0, min(100, (float)($element['y'] ?? 0))),
                'width' => max(.1, min(100, (float)($element['width'] ?? 10))), 'height' => max(.1, min(100, (float)($element['height'] ?? 5))),
                'font_role' => $role,
                'font_family' => $family,
                'font_style' => (string)($element['font_style'] ?? '') === 'italic' ? 'italic' : 'normal',
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

    function social_studio_overlay_template_headline(array $template, string $fallback = ''): string
    {
        $template = social_studio_normalize_overlay_template($template);
        $candidates = [];
        foreach (($template['elements'] ?? []) as $element) {
            if ((string)($element['type'] ?? '') !== 'text') continue;
            $text = trim((string)($element['text'] ?? ''));
            if ($text === '' || mb_strlen($text) < 3 || preg_match('/consult|schedule|book|call|financ|draper/i', $text)) continue;
            $candidates[] = ['text' => $text, 'size' => (float)($element['font_size'] ?? 0), 'y' => (float)($element['y'] ?? 100)];
        }
        usort($candidates, static fn(array $a, array $b): int => ($b['size'] <=> $a['size']) ?: ($a['y'] <=> $b['y']));
        return trim((string)($candidates[0]['text'] ?? $fallback));
    }

    function social_studio_overlay_template_cta(array $template, string $fallback = ''): string
    {
        $template = social_studio_normalize_overlay_template($template);
        $matches = [];
        foreach (($template['elements'] ?? []) as $element) {
            if ((string)($element['type'] ?? '') !== 'text') continue;
            $text = trim((string)($element['text'] ?? ''));
            if ($text === '' || !preg_match('/consult|schedule|book|call|discover|learn more|financ|start/i', $text)) continue;
            $matches[] = ['text' => $text, 'y' => (float)($element['y'] ?? 0), 'length' => mb_strlen($text)];
        }
        usort($matches, static fn(array $a, array $b): int => ($b['y'] <=> $a['y']) ?: ($a['length'] <=> $b['length']));
        return trim((string)($matches[0]['text'] ?? $fallback));
    }

    function social_studio_curated_overlay_template(string $sourcePostId): array
    {
        if ($sourcePostId !== 'DZME24slvGK') return [];
        $text = static function (string $value, float $x, float $y, float $width, float $height, string $role, float $size, int $weight, float $lineHeight, float $spacing, string $color, bool $uppercase = false): array {
            return ['type'=>'text','text'=>$value,'x'=>$x,'y'=>$y,'width'=>$width,'height'=>$height,'font_role'=>$role,'font_family'=>match($role){'serif'=>'bodoni','script'=>'script',default=>'helvetica'},'font_style'=>$role === 'script' ? 'italic' : 'normal','font_size'=>$size,'font_weight'=>$weight,'line_height'=>$lineHeight,'letter_spacing'=>$spacing,'color'=>$color,'background_color'=>'transparent','border_color'=>'transparent','border_width'=>0,'border_radius'=>0,'align'=>'left','uppercase'=>$uppercase];
        };
        $elements = [
            $text('YOUR', 7.8, 8.3, 35, 4.5, 'sans', 3.65, 400, 1, .18, '#20252d', true),
            $text("CONFIDENCE\nSTARTS", 7.8, 13.2, 43, 16, 'serif', 7.45, 400, .86, -.025, '#20252d', true),
            $text('with your smile', 7.8, 27.8, 39, 5.5, 'script', 4.7, 400, 1, 0, '#9b794e'),
            ['type'=>'line','text'=>'','x'=>7.8,'y'=>36.1,'width'=>9.3,'height'=>.12,'font_role'=>'sans','font_family'=>'helvetica','font_style'=>'normal','font_size'=>1,'font_weight'=>400,'line_height'=>1,'letter_spacing'=>0,'color'=>'transparent','background_color'=>'#9b794e','border_color'=>'transparent','border_width'=>0,'border_radius'=>0,'align'=>'left','uppercase'=>false],
            $text("Custom veneers designed\nto enhance your natural\nbeauty and help you feel\nconfident every day.", 7.8, 40.3, 34, 12, 'sans', 2.28, 500, 1.32, .01, '#20252d'),
            ['type'=>'box','text'=>'','x'=>7.8,'y'=>55.2,'width'=>5.2,'height'=>5.2,'font_role'=>'sans','font_size'=>1,'font_weight'=>400,'line_height'=>1,'letter_spacing'=>0,'color'=>'transparent','background_color'=>'transparent','border_color'=>'#a8895c','border_width'=>.12,'border_radius'=>50,'align'=>'left','uppercase'=>false],
            $text('◇', 9.05, 56.05, 2.7, 2.7, 'sans', 2.15, 400, 1, 0, '#9b794e'),
            $text("CUSTOM VENEERS\nNATURAL. BEAUTIFUL. YOU.", 14.7, 56.0, 27, 5, 'sans', 1.43, 500, 1.42, .10, '#20252d', true),
            ['type'=>'line','text'=>'','x'=>14.7,'y'=>61.8,'width'=>18.4,'height'=>.08,'font_role'=>'sans','font_size'=>1,'font_weight'=>400,'line_height'=>1,'letter_spacing'=>0,'color'=>'transparent','background_color'=>'#c9bba8','border_color'=>'transparent','border_width'=>0,'border_radius'=>0,'align'=>'left','uppercase'=>false],
            ['type'=>'box','text'=>'','x'=>7.8,'y'=>63.5,'width'=>5.2,'height'=>5.2,'font_role'=>'sans','font_size'=>1,'font_weight'=>400,'line_height'=>1,'letter_spacing'=>0,'color'=>'transparent','background_color'=>'transparent','border_color'=>'#a8895c','border_width'=>.12,'border_radius'=>50,'align'=>'left','uppercase'=>false],
            $text('⌣', 9.05, 64.25, 2.7, 2.7, 'sans', 2.15, 400, 1, 0, '#9b794e'),
            $text("COMPLIMENTARY\nCONSULTATION", 14.7, 64.3, 27, 5, 'sans', 1.43, 500, 1.42, .10, '#20252d', true),
            ['type'=>'line','text'=>'','x'=>14.7,'y'=>70.1,'width'=>18.4,'height'=>.08,'font_role'=>'sans','font_size'=>1,'font_weight'=>400,'line_height'=>1,'letter_spacing'=>0,'color'=>'transparent','background_color'=>'#c9bba8','border_color'=>'transparent','border_width'=>0,'border_radius'=>0,'align'=>'left','uppercase'=>false],
            ['type'=>'box','text'=>'','x'=>7.8,'y'=>71.8,'width'=>5.2,'height'=>5.2,'font_role'=>'sans','font_size'=>1,'font_weight'=>400,'line_height'=>1,'letter_spacing'=>0,'color'=>'transparent','background_color'=>'transparent','border_color'=>'#a8895c','border_width'=>.12,'border_radius'=>50,'align'=>'left','uppercase'=>false],
            $text('$', 9.35, 72.55, 2.2, 2.7, 'sans', 2.05, 400, 1, 0, '#9b794e'),
            $text("FLEXIBLE FINANCING\nOPTIONS", 14.7, 72.6, 27, 5, 'sans', 1.43, 500, 1.42, .10, '#20252d', true),
            $text("●  DRAPER, UTAH\n\nINVEST IN YOURSELF.\nLOVE YOUR SMILE.", 7.8, 88.5, 38, 11, 'sans', 1.38, 600, 1.35, .14, '#7f6749', true),
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
        $system = 'You are the Elite Smiles Master CMO and visual production director. Analyze the supplied Instagram creative as an IMMUTABLE approved production template. OCR every visible word exactly, preserving capitalization, punctuation, spelling, and manual line breaks. Measure each text block, divider line, and background box against the source pixels and encode it as a separate overlay_template element using percentages of the original canvas. font_size is percentage of canvas width. Select the closest available font_family by visual anatomy: bodoni, didot, playfair, garamond, georgia, montserrat, helvetica, arial, arial_narrow, or script. Record normal/italic style, weight, tracking, line height, alignment, colors, fills, borders, and geometry. Keep logos out of the template. base_prompt describes ONLY the clean photographic layer and explicitly requests no words, logo, watermark, icons, or graphic text. overlay_spec is a human-readable fidelity audit. This is forensic extraction, never redesign: do not improve, paraphrase, shorten, normalize, or invent any overlay copy.';
        $user = 'Analyze this existing Elite Smiles Instagram post pixel-by-pixel. Determine whether the source is 1:1 or 4:5. OCR every visible overlay word exactly and encode a deterministic overlay_template with precise x, y, width, height, font family, font style, scale, weight, tracking, color, alignment, and decoration. Treat text, icons, divider lines, and panels as independent reusable design elements; never rely on cropped source-image pixels. Preserve deliberate whitespace, manual line breaks, capitalization, punctuation, and the exact relationship between text and subject. Use transparent when a fill or border is absent. Exclude logos. The template must rebuild the source overlay cleanly on a newly generated photo without asking the image model to draw text. Source metadata: ' . json_encode(array_diff_key($post, ['image_url' => true]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return elite_openai_json_response($system, $user, $schema, 'elite_smiles_base_creative_analysis', (string)($post['image_url'] ?? ''));
    }

    function social_studio_get_or_create_overlay_template(array $base): array
    {
        $curated = social_studio_curated_overlay_template((string)($base['source_post_id'] ?? ''));
        if ($curated !== []) {
            if (!empty($base['id'])) {
                db_execute('UPDATE social_studio_base_creatives SET overlay_template_json=:overlay_template_json, analysis_version=4 WHERE id=:id LIMIT 1', ['id'=>(int)$base['id'], 'overlay_template_json'=>social_studio_encode_overlay_template($curated)]);
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
            db_execute('UPDATE social_studio_base_creatives SET analysis_json=:analysis_json, base_prompt=:base_prompt, overlay_spec=:overlay_spec, overlay_template_json=:overlay_template_json, analysis_version=4 WHERE id=:id LIMIT 1', [
                'id' => (int)$base['id'],
                'analysis_json' => is_array($data['analysis'] ?? null) ? json_encode($data['analysis'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string)($data['analysis'] ?? ''),
                'base_prompt' => trim((string)($data['base_prompt'] ?? ($base['base_prompt'] ?? ''))),
                'overlay_spec' => trim((string)($data['overlay_spec'] ?? ($base['overlay_spec'] ?? ''))),
                'overlay_template_json' => social_studio_encode_overlay_template($template),
            ]);
        }
        return $template;
    }

    function social_studio_position_overlay_template(array $template, string $position): array
    {
        $template = social_studio_normalize_overlay_template($template);
        if ($template === [] || !in_array($position, ['left', 'right'], true)) return $template;
        $elements = (array)($template['elements'] ?? []);
        if ($elements === []) return $template;
        $minX = 100.0; $maxX = 0.0;
        foreach ($elements as $element) {
            $minX = min($minX, (float)$element['x']);
            $maxX = max($maxX, (float)$element['x'] + (float)$element['width']);
        }
        $sourcePosition = (($minX + $maxX) / 2) < 50 ? 'left' : 'right';
        if ($sourcePosition === $position) return $template;
        foreach ($elements as &$element) {
            $element['x'] = max(0, min(100, 100 - (float)$element['x'] - (float)$element['width']));
            if ((string)$element['align'] === 'left') $element['align'] = 'right';
            elseif ((string)$element['align'] === 'right') $element['align'] = 'left';
        }
        unset($element);
        $template['elements'] = $elements;
        return social_studio_normalize_overlay_template($template);
    }

    function social_studio_overlay_text_fits(array $element, ?string $replacement = null): bool
    {
        if ((string)($element['type'] ?? '') !== 'text') return true;
        $text = $replacement ?? (string)($element['text'] ?? '');
        $lines = preg_split('/\R/u', $text) ?: [$text];
        $fontSize = max(0.1, (float)($element['font_size'] ?? 1));
        $boxWidth = max(0.1, (float)($element['width'] ?? 1));
        foreach ($lines as $line) {
            $units = 0.0;
            foreach (preg_split('//u', (string)$line, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
                if (preg_match('/\s/u', $character)) $units += .32;
                elseif (preg_match('/[MW@%&]/iu', $character)) $units += .88;
                elseif (preg_match('/[Iil1\.,:;!\'\|]/u', $character)) $units += .30;
                else $units += .58;
            }
            $tracking = max(-.08, (float)($element['letter_spacing'] ?? 0));
            $estimatedWidth = ($units + max(0, mb_strlen((string)$line) - 1) * $tracking) * $fontSize;
            if ($estimatedWidth > $boxWidth * .96) return false;
        }
        return true;
    }

    function social_studio_overlay_template_fits(array $template): bool
    {
        foreach ((array)($template['elements'] ?? []) as $element) {
            if (!social_studio_overlay_text_fits((array)$element)) return false;
        }
        return true;
    }

    function social_studio_fit_original_overlay_template(array $template, float $minimumScale = .72): array
    {
        $template = social_studio_normalize_overlay_template($template);
        if ($template === []) return [];
        foreach ((array)($template['elements'] ?? []) as $index => $element) {
            if ((string)($element['type'] ?? '') !== 'text' || social_studio_overlay_text_fits($element)) continue;
            $originalSize = max(.1, (float)($element['font_size'] ?? 1));
            $fitted = false;
            for ($scale = .98; $scale >= $minimumScale; $scale -= .02) {
                $template['elements'][$index]['font_size'] = round($originalSize * $scale, 4);
                if (social_studio_overlay_text_fits((array)$template['elements'][$index])) { $fitted = true; break; }
            }
            if (!$fitted) return [];
        }
        return social_studio_normalize_overlay_template($template);
    }

    function social_studio_rewrite_overlay_copy(array $template, string $focus, string $instruction = ''): array
    {
        $template = social_studio_normalize_overlay_template($template);
        if ($template === [] || !elite_openai_is_configured()) return ['ok'=>false, 'message'=>'OpenAI is not configured for overlay rewriting.'];
        $editable = [];
        foreach (($template['elements'] ?? []) as $index => $element) {
            if ((string)$element['type'] !== 'text') continue;
            $text = trim((string)$element['text']);
            if ($text === '' || mb_strlen($text) <= 2 || preg_match('/^[\p{S}\p{P}\$]+$/u', $text)) continue;
            $editable[] = ['index'=>$index, 'text'=>(string)$element['text']];
        }
        if ($editable === []) return ['ok'=>false, 'message'=>'The selected template has no editable copy blocks.'];
        $itemSchema = ['type'=>'object','additionalProperties'=>false,'properties'=>['index'=>['type'=>'integer'],'text'=>['type'=>'string']],'required'=>['index','text']];
        $schema = ['type'=>'object','additionalProperties'=>false,'properties'=>['replacements'=>['type'=>'array','minItems'=>count($editable),'maxItems'=>count($editable),'items'=>$itemSchema]],'required'=>['replacements']];
        $system = 'You are the Elite Smiles Master CMO. Rewrite only the supplied approved overlay wording. Keep the exact concept, treatment claims, CTA intent, location, financing qualifications, capitalization pattern, number of blocks, and manual line count. Each replacement must fit the same visual box: stay close to the original character count per line. Do not change typography, geometry, colors, icons, or layout. Return every supplied index exactly once.';
        $user = 'Focus: ' . social_studio_focus_label($focus) . "\nOptional direction: " . trim($instruction) . "\nApproved text blocks:\n" . json_encode($editable, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $response = elite_openai_json_response($system, $user, $schema, 'social_studio_overlay_rewrite');
        if (empty($response['ok']) || !is_array($response['data']['replacements'] ?? null)) return ['ok'=>false, 'message'=>(string)($response['message'] ?? 'Overlay rewrite failed.')];
        $replacements = [];
        foreach ($response['data']['replacements'] as $replacement) $replacements[(int)($replacement['index'] ?? -1)] = (string)($replacement['text'] ?? '');
        foreach ($editable as $source) {
            $index = (int)$source['index'];
            if (!isset($replacements[$index]) || trim($replacements[$index]) === '') return ['ok'=>false, 'message'=>'Overlay rewrite returned an incomplete template.'];
            $template['elements'][$index]['text'] = $replacements[$index];
        }
        return ['ok'=>true, 'template'=>social_studio_normalize_overlay_template($template)];
    }

    function social_studio_replace_overlay_text(array $template, string $find, string $replace): array
    {
        $template = social_studio_normalize_overlay_template($template);
        $find = trim(mb_substr($find, 0, 120));
        $replace = trim(mb_substr($replace, 0, 120));
        if ($template === [] || $find === '' || $replace === '') {
            return ['ok' => false, 'message' => 'Enter both the approved text and its replacement.'];
        }
        if (mb_strtolower($find) === mb_strtolower($replace)) {
            return ['ok' => false, 'message' => 'The replacement must be different from the approved text.'];
        }
        $count = 0;
        $pattern = '/' . preg_quote($find, '/') . '/iu';
        foreach (($template['elements'] ?? []) as $index => $element) {
            if ((string)($element['type'] ?? '') !== 'text') continue;
            $updated = preg_replace_callback($pattern, static fn(): string => $replace, (string)($element['text'] ?? ''), -1, $elementCount);
            if (is_string($updated) && $elementCount > 0) {
                $template['elements'][$index]['text'] = $updated;
                $count += $elementCount;
            }
        }
        if ($count === 0) {
            return ['ok' => false, 'message' => '“' . $find . '” was not found in the selected post overlay.'];
        }
        return ['ok' => true, 'template' => social_studio_normalize_overlay_template($template), 'count' => $count];
    }

    function social_studio_seed_drafts(string $focus, int $count, int $createdBy = 0, string $instruction = '', string $inspirationImageDataUrl = '', array $remixTemplate = [], ?array &$createdIds = null): int
    {
        social_studio_ensure_schema();

        $focus = social_studio_normalize_focus($focus);
        $count = max(1, min(7, $count));
        $sourceHashtags = trim((string)($remixTemplate['source_hashtags'] ?? ''));
        $hashtags = $sourceHashtags !== '' ? preg_split('/\s+/', $sourceHashtags) : social_studio_default_hashtags($focus);
        $hashtags = array_values(array_filter((array)$hashtags, static fn($tag): bool => is_string($tag) && str_starts_with(trim($tag), '#')));
        if ($hashtags === []) {
            $hashtags = social_studio_default_hashtags($focus);
        }
        $topics = social_studio_generate_topics($focus, $count, $instruction, $inspirationImageDataUrl);
        if ($topics === []) {
            throw new RuntimeException('OpenAI returned no usable social drafts. Nothing was added to the review queue.');
        }
        foreach ($topics as $topic) {
            if (trim((string)($topic['caption'] ?? '')) === ''
                || trim((string)($topic['title'] ?? '')) === ''
                || trim((string)($topic['image_prompt'] ?? '')) === '') {
                throw new RuntimeException('OpenAI returned an incomplete draft. Nothing was added to the review queue.');
            }
        }
        $created = 0;

        foreach ($topics as $index => $topic) {
            if ($remixTemplate !== []) {
                $topic['base_reference_key'] = (string)($remixTemplate['reference_key'] ?? '');
                $topic['base_post_prompt'] = (string)($remixTemplate['base_prompt'] ?? '');
                $topic['overlay_spec'] = social_studio_locked_overlay_spec($remixTemplate);
                $topic['overlay_template'] = social_studio_position_overlay_template((array)($remixTemplate['overlay_template'] ?? []), (string)($remixTemplate['text_position'] ?? 'source'));
                $topic['title'] = social_studio_overlay_template_headline((array)$topic['overlay_template'], (string)($topic['title'] ?? ''));
                $topic['cta'] = social_studio_overlay_template_cta((array)$topic['overlay_template'], (string)($topic['cta'] ?? ''));
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
            $caption = trim((string)($topic['caption'] ?? ''));
            if ($caption === '') {
                throw new RuntimeException('OpenAI returned a draft without a caption. Nothing was added to the review queue.');
            }
            $title = trim((string)($topic['title'] ?? ''));
            if ($title === '') {
                throw new RuntimeException('OpenAI returned a draft without a title. Nothing was added to the review queue.');
            }
            $imagePrompt = trim((string)($topic['image_prompt'] ?? ''));
            if ($imagePrompt === '') {
                throw new RuntimeException('OpenAI returned a draft without an image prompt. Nothing was added to the review queue.');
            }

            $createdId = (int)db_insert(
                "INSERT INTO social_studio_drafts
                    (title, status, platform, content_focus, post_type, caption, cta, hashtags, image_prompt, base_reference_key, base_post_prompt, overlay_spec, overlay_eyebrow, overlay_blocks_json, overlay_template_json, copy_mode, text_position, scheduled_at, created_by)
                 VALUES
                    (:title, 'review', :platform, :content_focus, :post_type, :caption, :cta, :hashtags, :image_prompt, :base_reference_key, :base_post_prompt, :overlay_spec, :overlay_eyebrow, :overlay_blocks_json, :overlay_template_json, :copy_mode, :text_position, :scheduled_at, :created_by)",
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
                    'copy_mode' => (string)($remixTemplate['copy_mode'] ?? 'preserve'),
                    'text_position' => (string)($remixTemplate['text_position'] ?? 'source'),
                    'scheduled_at' => null,
                    'created_by' => $createdBy > 0 ? $createdBy : null,
                ]
            );
            if (is_array($createdIds)) {
                $createdIds[] = $createdId;
            }
            $created++;
        }

        return $created;
    }
}

if (!function_exists('social_studio_generate_topics')) {
    function social_studio_generate_topics(string $focus, int $count, string $instruction = '', string $inspirationImageDataUrl = ''): array
    {
        if (!elite_openai_is_configured()) {
            throw new RuntimeException('OpenAI is not configured. No fallback drafts were created.');
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
        $isOriginal = str_contains($instruction, 'ORIGINAL CREATION BRIEF');
        $modeDirection = $isOriginal
            ? 'This is an ORIGINAL creation inside an approved Elite Smiles design system. Develop a genuinely new concept, hook, caption, and photographic direction from the structured brief. Reuse only the design grammar and saved overlay geometry; never copy source-photo pixels or old treatment claims.'
            : 'This is a REMIX. The selected base post is a locked template, not loose inspiration. Preserve its composition, crop, subject scale, palette, typography, hierarchy, CTA treatment, and approved overlay copy except for explicitly requested substitutions.';
        $user = "Create {$count} draft social posts for {$focus}. {$modeDirection} The deterministic overlay is handled separately by CRM; do not render or position on-image copy in these drafts. Create a fresh caption while directing only the clean photo through the supplied brief. Return overlay_eyebrow and overlay_blocks as empty placeholders because CRM supplies the overlay. Return base_reference_key, base_post_prompt, and overlay_spec for every draft. The Nano Banana image prompt must request a close, sharp subject with both eyes visible and brilliant bright-white cosmetically perfect teeth where a person is present; for clinical or 3D education, use complete credible anatomy and a clear focal model. The image remains unbranded with no text, logo, watermark, or typography. Instruction: " . ($instruction !== '' ? $instruction : 'Use the selected base post and requested controls.');
        $response = elite_openai_json_response($system, $user, $schema, 'social_studio_drafts', $inspirationImageDataUrl);
        if (empty($response['ok']) || !is_array($response['data']['drafts'] ?? null)) {
            throw new RuntimeException('OpenAI draft generation failed: ' . (string)($response['message'] ?? 'No structured drafts returned.'));
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
                'cta' => 'Schedule a complimentary consultation with Elite Smiles.',
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
        return in_array($focus, ['veneers', 'implants', 'smile_makeover', 'lip_repositioning', 'dental_education'], true) ? $focus : 'veneers';
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

if (!function_exists('social_studio_week_start')) {
    function social_studio_week_start(string $requested = ''): DateTimeImmutable
    {
        $timezone = new DateTimeZone(APP_TIMEZONE);
        try {
            $date = $requested !== '' ? new DateTimeImmutable($requested, $timezone) : new DateTimeImmutable('now', $timezone);
        } catch (Throwable) {
            $date = new DateTimeImmutable('now', $timezone);
        }
        return $date->modify('monday this week')->setTime(0, 0);
    }

    function social_studio_week_days(DateTimeImmutable $weekStart): array
    {
        $days = [];
        for ($offset = 0; $offset < 7; $offset++) {
            $days[] = $weekStart->modify('+' . $offset . ' days');
        }
        return $days;
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
                db_execute('UPDATE social_studio_base_creatives SET overlay_template_json=:template, analysis_version=4 WHERE id=:id LIMIT 1', ['id'=>(int)$curatedBase['id'], 'template'=>$curatedJson]);
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

        $requestedDraftId = function_exists('get') ? (int)get('draft', 0) : 0;
        $selected = $requestedDraftId > 0
            ? db_one('SELECT * FROM social_studio_drafts WHERE id=:id LIMIT 1', ['id' => $requestedDraftId])
            : null;
        $selected ??= db_one('SELECT * FROM social_studio_drafts WHERE status IN ("review", "draft", "approved", "scheduled", "publish_failed", "published") ORDER BY FIELD(status, "review", "draft", "approved", "publish_failed", "scheduled", "published"), id DESC LIMIT 1');

        $weekStart = social_studio_week_start(function_exists('get') ? trim((string)get('week', '')) : '');
        $weekEnd = $weekStart->modify('+7 days');

        return [
            'counts' => $counts,
            'drafts' => db_all('SELECT * FROM social_studio_drafts WHERE status IN ("review", "draft") ORDER BY FIELD(status, "review", "draft"), id DESC LIMIT 24'),
            'selected' => $selected,
            'schedule' => db_all('SELECT * FROM social_studio_drafts WHERE scheduled_at IS NOT NULL AND status IN ("approved", "scheduled", "publish_failed") ORDER BY scheduled_at ASC LIMIT 8'),
            'approved_unscheduled' => db_all('SELECT * FROM social_studio_drafts WHERE status="approved" AND scheduled_at IS NULL ORDER BY COALESCE(approved_at, created_at) ASC, id ASC LIMIT 50'),
            'calendar_items' => db_all('SELECT * FROM social_studio_drafts WHERE scheduled_at >= :week_start AND scheduled_at < :week_end AND status IN ("scheduled", "publishing", "published", "publish_failed") ORDER BY scheduled_at ASC, id ASC', [
                'week_start' => $weekStart->format('Y-m-d H:i:s'),
                'week_end' => $weekEnd->format('Y-m-d H:i:s'),
            ]),
            'published_drafts' => db_all('SELECT * FROM social_studio_drafts WHERE status="published" ORDER BY published_at DESC, id DESC LIMIT 100'),
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
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
        $version = substr(sha1($key), 0, 12);
        return base_url('app/actions/social_studio_image.php?draft_id=' . rawurlencode((string)$draft['id'])
            . ($branded ? '&variant=branded' : '&variant=raw')
            . '&v=' . rawurlencode($version));
    }
}

if (!function_exists('social_studio_locked_overlay_spec')) {
    function social_studio_locked_overlay_spec(array $template): string
    {
        $position = (string)($template['text_position'] ?? 'source');
        if (!in_array($position, ['source', 'left', 'right'], true)) {
            $position = 'source';
        }
        return "Text position: {$position}. Source preserves the approved coordinates; left or right mirrors only the overlay geometry. This is the only permitted layout substitution.\n"
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
            '{{TEXT_POSITION}}' => (string)($template['text_position'] ?? 'source'),
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

if (!function_exists('social_studio_should_send_reference_image')) {
    function social_studio_should_send_reference_image(array $overlayTemplate): bool
    {
        return true;
    }
}

if (!function_exists('social_studio_should_direct_edit_template')) {
    function social_studio_should_direct_edit_template(array $draft, string $referencePath): bool
    {
        return $referencePath !== ''
            && is_file($referencePath)
            && in_array((string)($draft['copy_mode'] ?? 'preserve'), ['preserve', 'replace'], true)
            && (string)($draft['text_position'] ?? 'source') === 'source'
            && preg_match('/^base_\d+$/', (string)($draft['base_reference_key'] ?? '')) === 1;
    }
}

if (!function_exists('social_studio_direct_template_edit_prompt')) {
    function social_studio_direct_template_edit_prompt(array $draft, array $overlayTemplate = []): string
    {
        $imagePrompt = trim((string)($draft['image_prompt'] ?? ''));
        $variation = '';
        if (preg_match('/Controlled substitutions only[^\n]*?(?:Focus:\s*([^;\n]+);\s*Purpose:\s*([^;\n]+);\s*Audience:\s*([^;\n]+);\s*Age range:\s*([^;\n]+))/iu', $imagePrompt, $match)) {
            $variation = 'Focus: ' . trim((string)$match[1])
                . '; Purpose: ' . trim((string)$match[2])
                . '; Audience: ' . trim((string)$match[3])
                . '; Age range: ' . trim((string)$match[4]) . '.';
        }
        if (preg_match('/Treatment-specific subject direction:\s*(.+?)(?:\n[A-Z][A-Z ]+:|$)/isu', $imagePrompt, $match)) {
            $variation .= ($variation !== '' ? "\n" : '') . 'Additional photo direction: ' . trim((string)$match[1]);
        }
        if ($variation === '') {
            $variation = 'Create a fresh photographic variation appropriate for ' . social_studio_focus_label((string)($draft['content_focus'] ?? 'veneers')) . '.';
        }

        $targetTemplate = social_studio_normalize_overlay_template((array)(json_decode((string)($draft['overlay_template_json'] ?? ''), true) ?: []));
        $requestedChanges = [];
        foreach ((array)($overlayTemplate['elements'] ?? []) as $index => $sourceElement) {
            if ((string)($sourceElement['type'] ?? '') !== 'text') continue;
            $sourceText = trim((string)($sourceElement['text'] ?? ''));
            $targetText = trim((string)($targetTemplate['elements'][$index]['text'] ?? $sourceText));
            if ($sourceText !== '' && $targetText !== '' && $sourceText !== $targetText) {
                $requestedChanges[] = 'Replace exactly “' . $sourceText . '” with “' . $targetText . '”.';
            }
        }

        $protectedCopy = [];
        foreach ((array)(($targetTemplate['elements'] ?? []) ?: ($overlayTemplate['elements'] ?? [])) as $element) {
            if ((string)($element['type'] ?? '') !== 'text') continue;
            $text = trim((string)($element['text'] ?? ''));
            if ($text !== '') $protectedCopy[] = $text;
        }

        return "Edit the supplied approved Elite Smiles advertisement directly.\n\n"
            . "CHANGE ONLY THE PHOTOGRAPHIC PERSON, PHOTOGRAPHIC DENTAL SUBJECT, AND PHOTOGRAPHIC BACKGROUND according to this request:\n{$variation}\n\n"
            . "Keep the replacement subject at the same camera distance, scale, pose area, and side of the composition as the original. When a person is present, keep the complete face, both eyes, and full bright natural-looking smile visible and tack-sharp.\n\n"
            . ($requestedChanges !== [] ? "PERMITTED TEXT SUBSTITUTION — make only the following exact wording change while preserving its font, size, weight, color, capitalization pattern, line structure, alignment, and position:\n" . implode("\n", $requestedChanges) . "\n\n" : '')
            . "PROTECTED DESIGN LOCK: Everything other than the requested photographic change and the explicitly permitted text substitution above is immutable. Preserve every other design element visually identical to the input: wording, spelling, punctuation, line breaks, fonts, font sizes, weights, positions, colors, icons, ornaments, underlines, circles, rules, panels, CTA, financing language, footer, brand treatment, spacing, margins, and canvas composition. Do not redraw, rewrite, move, resize, recolor, crop, blur, erase, or restyle protected design content. This is a localized replacement, not a redesign and not a new advertisement.\n\n"
            . ($protectedCopy !== [] ? "Protected wording that must remain perfectly legible and unchanged:\n" . implode("\n---\n", $protectedCopy) . "\n\n" : '')
            . "Return one image with the same aspect ratio and complete layout as the supplied advertisement. Never add new words, logos, badges, icons, or design elements.";
    }
}

if (!function_exists('social_studio_match_template_canvas')) {
    function social_studio_match_template_canvas(array $generated, string $templatePath): array
    {
        $bytes = is_string($generated['bytes'] ?? null) ? (string)$generated['bytes'] : '';
        if ($bytes === '' || !is_file($templatePath) || !function_exists('imagecreatefromstring')) return $generated;
        $templateSize = @getimagesize($templatePath);
        $sourceSize = @getimagesizefromstring($bytes);
        if (!is_array($templateSize) || !is_array($sourceSize) || empty($templateSize[0]) || empty($templateSize[1]) || empty($sourceSize[0]) || empty($sourceSize[1])) return $generated;
        $targetWidth = (int)$templateSize[0]; $targetHeight = (int)$templateSize[1];
        if ((int)$sourceSize[0] === $targetWidth && (int)$sourceSize[1] === $targetHeight) return $generated;
        $source = @imagecreatefromstring($bytes);
        if (!$source) return $generated;
        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        if (!$target) { imagedestroy($source); return $generated; }
        $sourceWidth = imagesx($source); $sourceHeight = imagesy($source);
        $sourceRatio = $sourceWidth / max(1, $sourceHeight); $targetRatio = $targetWidth / max(1, $targetHeight);
        if ($sourceRatio > $targetRatio) {
            $cropHeight = $sourceHeight; $cropWidth = (int)round($sourceHeight * $targetRatio);
            $cropX = (int)max(0, floor(($sourceWidth - $cropWidth) / 2)); $cropY = 0;
        } else {
            $cropWidth = $sourceWidth; $cropHeight = (int)round($sourceWidth / $targetRatio);
            $cropX = 0; $cropY = (int)max(0, floor(($sourceHeight - $cropHeight) / 2));
        }
        imagecopyresampled($target, $source, 0, 0, $cropX, $cropY, $targetWidth, $targetHeight, $cropWidth, $cropHeight);
        ob_start(); imagepng($target, null, 6); $normalized = (string)ob_get_clean();
        imagedestroy($target); imagedestroy($source);
        if ($normalized === '') return $generated;
        $generated['bytes'] = $normalized; $generated['mime_type'] = 'image/png';
        return $generated;
    }
}

if (!function_exists('social_studio_generate_image_for_draft')) {
    function social_studio_generate_image_for_draft(int $draftId, int $qualityAttempt = 1): array
    {
        social_studio_ensure_schema();
        $draft = db_one('SELECT * FROM social_studio_drafts WHERE id = :id LIMIT 1', ['id' => $draftId]);
        if (!$draft) {
            return ['ok' => false, 'message' => 'Social draft not found.'];
        }
        db_execute('UPDATE social_studio_drafts SET generation_status="generating", generation_error=NULL WHERE id=:id LIMIT 1', ['id' => $draftId]);

        $overlayTemplate = json_decode((string)($draft['overlay_template_json'] ?? ''), true);
        $overlayTemplate = is_array($overlayTemplate) ? social_studio_normalize_overlay_template($overlayTemplate) : [];
        $referenceImage = [];
        $referencePath = '';
        $sourceOverlayTemplate = [];
        if (preg_match('/^base_(\d+)$/', (string)($draft['base_reference_key'] ?? ''), $baseMatch)) {
            $base = db_one('SELECT source_url, source_post_id, COALESCE(NULLIF(clean_image_key, ""), local_image_key) AS local_image_key, overlay_template_json FROM social_studio_base_creatives WHERE id=:id AND status="active" LIMIT 1', ['id' => (int)$baseMatch[1]]);
            $referencePath = $base ? social_studio_base_source_path($base) : '';
            $sourceTemplateDecoded = $base ? json_decode((string)($base['overlay_template_json'] ?? ''), true) : null;
            $sourceOverlayTemplate = is_array($sourceTemplateDecoded) ? social_studio_normalize_overlay_template($sourceTemplateDecoded) : [];
            if (!social_studio_should_direct_edit_template($draft, $referencePath) && social_studio_should_send_reference_image($overlayTemplate) && $referencePath !== '' && is_file($referencePath)) {
                $referenceBytes = @file_get_contents($referencePath);
                if (is_string($referenceBytes) && $referenceBytes !== '') {
                    if (($sourceOverlayTemplate['elements'] ?? []) !== []) {
                        $cleanReference = social_studio_remove_reference_overlay($referenceBytes, $sourceOverlayTemplate);
                        if ($cleanReference !== '') {
                            $referenceBytes = $cleanReference;
                        }
                    }
                    $referenceMime = (string)(@mime_content_type($referencePath) ?: 'image/jpeg');
                    if (str_starts_with($referenceBytes, "\xFF\xD8")) {
                        $referenceMime = 'image/jpeg';
                    }
                    $referenceImage = ['bytes' => $referenceBytes, 'mime_type' => $referenceMime];
                }
            }
        }
        $directTemplateEdit = social_studio_should_direct_edit_template($draft, $referencePath);
        $prompt = $directTemplateEdit
            ? social_studio_direct_template_edit_prompt($draft, $sourceOverlayTemplate)
            : social_studio_refine_image_prompt($draft);
        $templateSquare = is_array($overlayTemplate) && (string)($overlayTemplate['aspect_ratio'] ?? '') === '1:1';
        if ($directTemplateEdit) {
            $edit = elite_gemini_generate_image_edit([$referencePath], $prompt);
            $editBytes = !empty($edit['ok']) ? base64_decode((string)($edit['image_base64'] ?? ''), true) : false;
            $generated = !empty($edit['ok']) && is_string($editBytes) && $editBytes !== ''
                ? ['ok'=>true, 'bytes'=>$editBytes, 'mime_type'=>(string)($edit['mime_type'] ?? 'image/png')]
                : ['ok'=>false, 'message'=>(string)($edit['message'] ?? 'Nano Banana could not edit the approved template.')];
            $generated = social_studio_match_template_canvas($generated, $referencePath);
        } else {
            $generated = social_studio_generate_image_binary($prompt, $referenceImage, $templateSquare ? [
                'aspect_ratio' => 'ASPECT_RATIO_ONE_BY_ONE',
                'image_size' => 'IMAGE_SIZE_TWO_K',
                'output_requirement' => 'Return one square 1:1 image matching the supplied Instagram source canvas.',
            ] : []);
        }
        if (empty($generated['ok']) || !is_string($generated['bytes'] ?? null) || $generated['bytes'] === '') {
            $message = (string)($generated['message'] ?? 'Could not generate image.');
            db_execute('UPDATE social_studio_drafts SET generation_status="failed", generation_error=:error WHERE id=:id LIMIT 1', ['id' => $draftId, 'error' => $message]);
            return ['ok' => false, 'message' => $message];
        }

        $storagePrefix = 'drafts/' . $draftId;
        $rawExt = match (strtolower((string)($generated['mime_type'] ?? 'image/png'))) {
            'image/svg+xml' => 'svg',
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'png',
        };
        $rawKey = $storagePrefix . '/generated-' . date('Ymd-His') . '.' . $rawExt;
        $rawPath = social_studio_safe_storage_path($rawKey);
        if (!$rawPath || @file_put_contents($rawPath, $generated['bytes']) === false) {
            db_execute('UPDATE social_studio_drafts SET generation_status="failed", generation_error="Could not save generated image." WHERE id=:id LIMIT 1', ['id' => $draftId]);
            return ['ok' => false, 'message' => 'Could not save generated image.'];
        }

        $hasOverlayTemplate = !$directTemplateEdit && is_array($overlayTemplate) && ($overlayTemplate['elements'] ?? []) !== [];
        $brandedExt = $hasOverlayTemplate ? 'svg' : (social_studio_can_raster_brand_images() ? 'png' : 'svg');
        $brandedKey = $storagePrefix . '/branded-' . date('Ymd-His') . '.' . $brandedExt;
        $brandedPath = social_studio_safe_storage_path($brandedKey);
        $pixelLockOverlay = $hasOverlayTemplate
            && (string)($draft['copy_mode'] ?? 'preserve') === 'preserve'
            && $referencePath !== ''
            && is_file($referencePath)
            && ($sourceOverlayTemplate['elements'] ?? []) !== [];
        if ($directTemplateEdit) {
            $brandedKey = $rawKey;
        } elseif (!$brandedPath || !social_studio_create_branded_image(
            $rawPath,
            $brandedPath,
            $hasOverlayTemplate ? $overlayTemplate : [],
            $pixelLockOverlay ? $referencePath : '',
            $pixelLockOverlay ? $sourceOverlayTemplate : []
        )) {
            $brandedKey = $rawKey;
        }

        db_execute(
            'UPDATE social_studio_drafts
             SET image_prompt = :image_prompt, image_storage_key = :image_storage_key, branded_image_storage_key = :branded_image_storage_key, image_generated_at = NOW(), generation_status="ready", generation_error=NULL
             WHERE id = :id LIMIT 1',
            [
                'id' => $draftId,
                'image_prompt' => $prompt,
                'image_storage_key' => $rawKey,
                'branded_image_storage_key' => $brandedKey,
            ]
        );

        $guardrails = [];
        if (function_exists('social_studio_review_generated_asset')) {
            try {
                $guardrails = social_studio_review_generated_asset($draftId, $rawPath);
            } catch (Throwable $reviewError) {
                esm_log('social_studio', 'Generated asset guardrail review failed.', ['draft_id' => $draftId, 'error' => $reviewError->getMessage()]);
            }
        }

        $visualGuardrailFailed = false;
        foreach ((array)($guardrails['checks'] ?? []) as $check) {
            if (in_array((string)($check['key'] ?? ''), ['image_text', 'focus', 'anatomy', 'framing'], true) && empty($check['pass'])) {
                $visualGuardrailFailed = true;
                break;
            }
        }
        if ((string)($draft['creation_mode'] ?? '') === 'original'
            && $visualGuardrailFailed
            && $qualityAttempt < 2) {
            if (is_file($rawPath)) @unlink($rawPath);
            if ($brandedPath && is_file($brandedPath)) @unlink($brandedPath);
            esm_log('social_studio', 'Retrying original image after guardrail review.', ['draft_id' => $draftId, 'attempt' => $qualityAttempt]);
            return social_studio_generate_image_for_draft($draftId, $qualityAttempt + 1);
        }

        return ['ok' => true, 'message' => 'Image generated.', 'image_storage_key' => $rawKey, 'branded_image_storage_key' => $brandedKey];
    }
}

if (!function_exists('social_studio_remove_reference_overlay')) {
    function social_studio_remove_reference_overlay(string $bytes, array $template): string
    {
        if (!function_exists('imagecreatefromstring') || ($template['elements'] ?? []) === []) return '';
        $image = @imagecreatefromstring($bytes);
        if (!$image) return '';
        $width = imagesx($image); $height = imagesy($image);
        $minX = 100.0; $minY = 100.0; $maxX = 0.0; $maxY = 0.0;
        foreach ($template['elements'] as $element) {
            $minX = min($minX, (float)$element['x']); $minY = min($minY, (float)$element['y']);
            $maxX = max($maxX, (float)$element['x'] + (float)$element['width']);
            $maxY = max($maxY, (float)$element['y'] + (float)$element['height']);
        }
        if ($maxX <= $minX || $maxY <= $minY) { imagedestroy($image); return ''; }
        $x1 = max(0, (int)floor(($minX - 4) * $width / 100));
        $y1 = max(0, (int)floor(($minY - 4) * $height / 100));
        $x2 = min($width, (int)ceil(($maxX + 3) * $width / 100));
        $y2 = min($height, (int)ceil(($maxY + 3) * $height / 100));
        if ($minX < 12) $x1 = 0;
        if ($minY < 12 && $maxY > 85) { $y1 = 0; $y2 = $height; }

        $zoneWidth = max(1, $x2 - $x1); $zoneHeight = max(1, $y2 - $y1);
        $zone = imagecrop($image, ['x'=>$x1, 'y'=>$y1, 'width'=>$zoneWidth, 'height'=>$zoneHeight]);
        if (!$zone) { imagedestroy($image); return ''; }
        for ($pass = 0; $pass < 22; $pass++) imagefilter($zone, IMG_FILTER_GAUSSIAN_BLUR);
        imagefilter($zone, IMG_FILTER_SMOOTH, 10);
        imagealphablending($zone, true);
        $wash = imagecolorallocatealpha($zone, 245, 241, 233, 48);
        imagefilledrectangle($zone, 0, 0, $zoneWidth, $zoneHeight, $wash);
        imagecopy($image, $zone, $x1, $y1, 0, 0, $zoneWidth, $zoneHeight);
        imagedestroy($zone);
        ob_start(); imagejpeg($image, null, 92); $clean = (string)ob_get_clean(); imagedestroy($image);
        return $clean;
    }
}

if (!function_exists('social_studio_overlay_subject_instruction')) {
    function social_studio_overlay_subject_instruction(array $template): string
    {
        $elements = (array)($template['elements'] ?? []);
        $left = 100.0;
        $right = 0.0;
        foreach ($elements as $element) {
            $text = trim((string)($element['text'] ?? ''));
            $y = (float)($element['y'] ?? 0);
            if ($y >= 58 && $text !== '' && preg_match('/consult|schedule|book|call|discover|financ|start/i', $text)) continue;
            $left = min($left, (float)($element['x'] ?? 0));
            $right = max($right, (float)($element['x'] ?? 0) + (float)($element['width'] ?? 0));
        }
        if ($right <= $left) return '';
        if (($left + $right) / 2 <= 50) {
            return 'The approved artwork occupies the left side. Place the complete head, both eyes, full face, and full smile entirely inside the right-side photo area; use a moderately sized head-and-shoulders portrait, never an extreme close-up.';
        }
        return 'The approved artwork occupies the right side. Place the complete head, both eyes, full face, and full smile entirely inside the left-side photo area; use a moderately sized head-and-shoulders portrait, never an extreme close-up.';
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
            $subjectInstruction = is_array($template) ? social_studio_overlay_subject_instruction($template) : '';
            return $basePrompt . "\n\nFinal output safeguards: {$ratio} Instagram composition. Create ONLY the clean photographic layer behind the saved CRM overlay. {$subjectInstruction} Both eyes must be completely visible and tack-sharp, with brilliant bright-white cosmetically perfect teeth and credible anatomy. Preserve the locked palette, lighting, negative space, and camera angle. Reconstruct the cleared text zone as a seamless softly detailed continuation of the surrounding background with natural light and depth; never create a hard-edged rectangle, flat color panel, card, or visible boundary. Do not render any words from the source. No text, logo, watermark, typography, icons, graphic lines, soft focus on the subject, haze, extreme close-up, or cut-off face.";
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
            return ['ok' => false, 'message' => 'Nano Banana image generation is not configured. No placeholder was created.'];
        }

        $model = defined('GOOGLE_GEMINI_IMAGE_MODEL') ? trim((string)GOOGLE_GEMINI_IMAGE_MODEL) : 'gemini-3.1-flash-image';
        $apiKey = defined('GOOGLE_GEMINI_API_KEY') ? trim((string)GOOGLE_GEMINI_API_KEY) : '';
        if ($apiKey === '') {
            return ['ok' => false, 'message' => 'Nano Banana API credentials are missing. No placeholder was created.'];
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
    function social_studio_create_branded_image(string $sourcePath, string $targetPath, array $overlayTemplate = [], string $templateSourcePath = '', array $sourceOverlayTemplate = []): bool
    {
        if ($overlayTemplate !== []) {
            return social_studio_create_branded_svg($sourcePath, $targetPath, $overlayTemplate, $templateSourcePath, $sourceOverlayTemplate);
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
    function social_studio_create_branded_svg(string $sourcePath, string $targetPath, array $overlayTemplate = [], string $templateSourcePath = '', array $sourceOverlayTemplate = []): bool
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
        $elements = (array)($template['elements'] ?? []);
        usort($elements, static function (array $left, array $right): int {
            $layer = static fn(array $element): int => (string)($element['type'] ?? '') === 'text' ? 1 : 0;
            return $layer($left) <=> $layer($right);
        });
        foreach ($elements as $element) {
            $x = (float)$element['x'] * $width / 100; $y = (float)$element['y'] * $height / 100;
            $w = (float)$element['width'] * $width / 100; $h = (float)$element['height'] * $height / 100;
            $fill = htmlspecialchars((string)$element['background_color'], ENT_QUOTES, 'UTF-8');
            $stroke = htmlspecialchars((string)$element['border_color'], ENT_QUOTES, 'UTF-8');
            $strokeWidth = (float)$element['border_width'] * $width / 100;
            if ($element['type'] === 'box' || $element['type'] === 'line') {
                $svg .= '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="' . $h . '" rx="' . ((float)$element['border_radius'] * $width / 100) . '" fill="' . $fill . '" stroke="' . $stroke . '" stroke-width="' . $strokeWidth . '"/>';
                continue;
            }
            $fontFamily = social_studio_overlay_font_stack($element);
            $fontSize = (float)$element['font_size'] * $width / 100;
            $anchor = match ((string)$element['align']) { 'center' => 'middle', 'right' => 'end', default => 'start' };
            $textX = $x + ((string)$element['align'] === 'center' ? $w / 2 : ((string)$element['align'] === 'right' ? $w : 0));
            $text = !empty($element['uppercase']) ? mb_strtoupper((string)$element['text']) : (string)$element['text'];
            $lines = preg_split('/\R/u', $text) ?: [$text];
            $svg .= '<text x="' . $textX . '" y="' . ($y + $fontSize) . '" fill="' . htmlspecialchars((string)$element['color'], ENT_QUOTES, 'UTF-8') . '" font-family="' . htmlspecialchars($fontFamily, ENT_QUOTES, 'UTF-8') . '" font-style="' . ((string)($element['font_style'] ?? 'normal') === 'italic' ? 'italic' : 'normal') . '" font-size="' . $fontSize . '" font-weight="' . (int)$element['font_weight'] . '" letter-spacing="' . ((float)$element['letter_spacing'] * $fontSize) . '" text-anchor="' . $anchor . '">';
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
        $storedBrief = trim((string)($draft['creative_brief_json'] ?? ''));
        $analysis = $storedBrief !== '' ? $storedBrief : json_encode([
            'source' => 'approved_social_studio_draft',
            'draft_id' => $draftId,
            'focus' => (string)($draft['content_focus'] ?? ''),
            'purpose' => (string)($draft['post_type'] ?? ''),
            'caption' => (string)($draft['caption'] ?? ''),
            'cta' => (string)($draft['cta'] ?? ''),
            'hashtags' => (string)($draft['hashtags'] ?? ''),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        social_studio_upsert_base_creative([
            'source_type' => (string)($draft['creation_mode'] ?? 'remix') === 'original' ? 'generated' : 'approved_draft',
            'source_url' => '',
            'source_post_id' => 'draft_' . $draftId,
            'title' => (string)($draft['title'] ?? ('Approved creative ' . $draftId)),
            'published_at' => date('Y-m-d'),
            'group_name' => (string)($draft['creation_mode'] ?? 'remix') === 'original' ? 'Approved original / ' . (string)($draft['post_type'] ?? 'creative') : (string)($draft['content_focus'] ?? 'Approved creative'),
            'source_image_url' => $sourceImageUrl,
            'local_image_key' => (string)($draft['branded_image_storage_key'] ?: ($draft['image_storage_key'] ?? '')),
            'clean_image_key' => (string)($draft['image_storage_key'] ?? ''),
            'source_caption' => (string)($draft['caption'] ?? ''),
            'source_hashtags' => (string)($draft['hashtags'] ?? ''),
            'analysis_json' => $analysis ?: '{}',
            'base_prompt' => (string)($draft['image_prompt'] ?: ($draft['base_post_prompt'] ?? '')),
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
        $draft = db_one('SELECT status, image_storage_key, branded_image_storage_key, generation_status FROM social_studio_drafts WHERE id=:id LIMIT 1', ['id' => $draftId]);
        if (!$draft) {
            return false;
        }
        if ($status === 'approved') {
            $brandedKey = trim((string)($draft['branded_image_storage_key'] ?? ''));
            $rawKey = trim((string)($draft['image_storage_key'] ?? ''));
            if ($brandedKey === '' && $rawKey === '') {
                return false;
            }
            if ($brandedKey === '' && $rawKey !== '') {
                db_execute('UPDATE social_studio_drafts SET branded_image_storage_key=:image_key, generation_status="ready", generation_error=NULL WHERE id=:id LIMIT 1', [
                    'id' => $draftId,
                    'image_key' => $rawKey,
                ]);
            }
        }
        if ($status === 'scheduled' && (string)($draft['status'] ?? '') !== 'approved') {
            return false;
        }
        if ($status === 'published') {
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
