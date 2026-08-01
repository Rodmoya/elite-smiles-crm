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

        foreach ([
            'image_storage_key' => "ALTER TABLE social_studio_drafts ADD COLUMN image_storage_key VARCHAR(255) NULL AFTER image_url",
            'branded_image_storage_key' => "ALTER TABLE social_studio_drafts ADD COLUMN branded_image_storage_key VARCHAR(255) NULL AFTER image_storage_key",
            'image_generated_at' => "ALTER TABLE social_studio_drafts ADD COLUMN image_generated_at DATETIME NULL AFTER branded_image_storage_key",
            'base_reference_key' => "ALTER TABLE social_studio_drafts ADD COLUMN base_reference_key VARCHAR(180) NULL AFTER image_prompt",
            'base_post_prompt' => "ALTER TABLE social_studio_drafts ADD COLUMN base_post_prompt TEXT NULL AFTER base_reference_key",
            'overlay_spec' => "ALTER TABLE social_studio_drafts ADD COLUMN overlay_spec TEXT NULL AFTER base_post_prompt",
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
        ];
        if ($existing) {
            $updateParams = array_intersect_key($params, array_flip(['source_url', 'title', 'published_at', 'group_name', 'source_image_url', 'local_image_key', 'analysis_json', 'base_prompt', 'overlay_spec']));
            db_execute('UPDATE social_studio_base_creatives SET source_url=:source_url, title=:title, published_at=:published_at, group_name=:group_name, source_image_url=:source_image_url, local_image_key=:local_image_key, analysis_json=:analysis_json, base_prompt=:base_prompt, overlay_spec=:overlay_spec WHERE id=:id LIMIT 1', $updateParams + ['id' => (int)$existing['id']]);
            return (int)$existing['id'];
        }
        return (int)db_insert('INSERT INTO social_studio_base_creatives (source_type, source_url, source_post_id, title, published_at, group_name, source_image_url, local_image_key, analysis_json, base_prompt, overlay_spec) VALUES (:source_type,:source_url,:source_post_id,:title,:published_at,:group_name,:source_image_url,:local_image_key,:analysis_json,:base_prompt,:overlay_spec)', $params);
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
        foreach (db_all('SELECT id, source_url, source_post_id, title, published_at, group_name, source_image_url, local_image_key, base_prompt, overlay_spec FROM social_studio_base_creatives WHERE status = "active" ORDER BY published_at DESC, id DESC LIMIT 300') as $base) {
            $key = 'base_' . (int)$base['id'];
            $safePostId = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)($base['source_post_id'] ?? '')) ?: '';
            $bundledImage = $safePostId !== '' ? 'assets/social-studio/instagram/' . $safePostId . '.jpg' : '';
            $bundledPath = $bundledImage !== '' ? dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $bundledImage) : '';
            $storedPath = social_studio_safe_storage_path((string)($base['local_image_key'] ?? ''));
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
    function social_studio_analyze_base_creative(array $post): array
    {
        require_once dirname(__DIR__) . '/core/openai.php';
        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'title' => ['type' => 'string'],
                'group_name' => ['type' => 'string'],
                'analysis' => ['type' => 'object', 'additionalProperties' => true],
                'base_prompt' => ['type' => 'string'],
                'overlay_spec' => ['type' => 'string'],
            ],
            'required' => ['title', 'group_name', 'analysis', 'base_prompt', 'overlay_spec'],
        ];
        $system = 'You are the Elite Smiles Master CMO and visual editorial director. Analyze the supplied Instagram creative as a reusable design system. Identify composition, subject framing, lighting, palette, typography family and scale, text hierarchy, safe zones, CTA treatment, logo treatment, and clinical-ad compliance. Never ask for or reproduce a logo inside the generated image; the CRM overlay remains editable.';
        $user = 'Analyze this existing Elite Smiles Instagram post. Return a reusable base prompt for creating an original new version with Nano Banana and a precise editable overlay specification. Source metadata: ' . json_encode($post, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return elite_openai_json_response($system, $user, $schema, 'elite_smiles_base_creative_analysis', (string)($post['image_url'] ?? ''));
    }

    function social_studio_seed_drafts(string $focus, int $count, int $createdBy = 0, string $instruction = '', string $inspirationImageDataUrl = ''): int
    {
        social_studio_ensure_schema();

        $focus = social_studio_normalize_focus($focus);
        $count = max(1, min(7, $count));
        $hashtags = social_studio_default_hashtags($focus);
        $topics = social_studio_generate_topics($focus, $count, $instruction, $inspirationImageDataUrl);
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
                    (title, status, platform, content_focus, post_type, caption, cta, hashtags, image_prompt, base_reference_key, base_post_prompt, overlay_spec, scheduled_at, created_by)
                 VALUES
                    (:title, 'review', :platform, :content_focus, :post_type, :caption, :cta, :hashtags, :image_prompt, :base_reference_key, :base_post_prompt, :overlay_spec, :scheduled_at, :created_by)",
                [
                    'title' => $title,
                    'platform' => 'facebook_instagram',
                    'content_focus' => $focus,
                    'post_type' => trim((string)($topic['post_type'] ?? 'education')) ?: 'education',
                    'caption' => $caption,
                    'cta' => trim((string)($topic['cta'] ?? 'Request a veneer quote today.')),
                    'hashtags' => implode(' ', $hashtags),
                    'image_prompt' => $imagePrompt,
                    'base_reference_key' => trim((string)($topic['base_reference_key'] ?? '')) ?: null,
                    'base_post_prompt' => trim((string)($topic['base_post_prompt'] ?? '')) ?: null,
                    'overlay_spec' => trim((string)($topic['overlay_spec'] ?? '')) ?: null,
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
                        ],
                        'required' => ['title', 'post_type', 'caption', 'cta', 'image_prompt', 'base_reference_key', 'base_post_prompt', 'overlay_spec'],
                    ],
                ],
            ],
            'required' => ['drafts'],
        ];

        $system = 'You are the Elite Smiles Master CMO. Write concise, premium, compliant dental marketing posts using the complete brand operating system below. ' . social_studio_editorial_context();
        $user = "Create {$count} draft social posts for {$focus}. Each draft is a new version of a selected base post, not a blank concept. Preserve the base post's analyzed look, feel, content hierarchy, CTA pattern, and overlay structure; change only the requested variation inputs and create original wording and imagery. Return base_reference_key, base_post_prompt (the reusable analyzed template prompt), and overlay_spec (headline scale, text blocks, placement, CTA, and logo treatment) for every draft. Make the Nano Banana image prompt sharp, clean, original, unbranded, with no text/logo/watermark/typography and with space for the CRM overlay. Instruction: " . ($instruction !== '' ? $instruction : 'Use the selected base post and requested controls.');
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

        $imageFormat = [
            'aspectRatio' => 'ASPECT_RATIO_FOUR_BY_FIVE',
        ];
        if (str_contains(strtolower($model), 'gemini-3')) {
            $imageFormat['imageSize'] = 'IMAGE_SIZE_TWO_K';
        }

        $payload = [
            'contents' => [[
                'role' => 'user',
                'parts' => [[
                    'text' => $prompt . "\n\nOutput requirement: return one vertical 4:5 portrait image composed for an Instagram feed post.",
                ]],
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
    function social_studio_create_branded_image(string $sourcePath, string $targetPath): bool
    {
        // Preserve the generated image exactly; social creatives must remain unbranded.
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
        $sourceData = 'data:' . $sourceMime . ';base64,' . base64_encode($sourceBytes);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">'
            . '<image href="' . $sourceData . '" x="0" y="0" width="' . $width . '" height="' . $height . '" preserveAspectRatio="xMidYMid slice"/></svg>';
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
