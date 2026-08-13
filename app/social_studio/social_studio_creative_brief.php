<?php
declare(strict_types=1);

if (!function_exists('social_studio_creative_brief_schema')) {
    function social_studio_creative_brief_schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'focus' => ['type' => 'string', 'enum' => ['veneers', 'implants', 'smile_makeover', 'lip_repositioning', 'dental_education']],
                'purpose' => ['type' => 'string', 'enum' => ['educational', 'social_ad']],
                'audience' => ['type' => 'string', 'enum' => ['any', 'woman', 'man']],
                'age_range' => ['type' => 'string', 'enum' => ['any', '25-34', '35-44', '45-54', '55+']],
                'text_position' => ['type' => 'string', 'enum' => ['source', 'left', 'right']],
                'visual_format' => ['type' => 'string', 'enum' => ['editorial_card', 'portrait', 'smile_closeup', 'clinical_3d', 'benefit_list', 'dark_luxury']],
                'editorial_angle' => ['type' => 'string'],
                'subject_direction' => ['type' => 'string'],
                'overlay_direction' => ['type' => 'string'],
                'cta' => ['type' => 'string'],
                'location' => ['type' => 'string'],
                'include_financing' => ['type' => 'boolean'],
            ],
            'required' => ['focus', 'purpose', 'audience', 'age_range', 'text_position', 'visual_format', 'editorial_angle', 'subject_direction', 'overlay_direction', 'cta', 'location', 'include_financing'],
        ];
    }

    function social_studio_interpret_creative_brief(string $instruction, array $controls = []): array
    {
        $instruction = trim($instruction);
        if ($instruction === '') {
            throw new RuntimeException('Describe the post you want to create.');
        }
        if (!elite_openai_is_configured()) {
            throw new RuntimeException('OpenAI is not configured for Original Creation.');
        }
        $system = 'You are the Elite Smiles Master CMO creative planner. Convert the request into one production-ready structured brief. Follow the brand operating system exactly. Never invent clinical outcomes, testimonials, urgency, availability, or prices. The image is a clean photographic or clinical layer with no words or logos; CRM renders typography. Use Draper, Utah and a complimentary consultation CTA. ' . social_studio_master_cmo_prompt();
        $user = "Creative request:\n{$instruction}\n\nExplicit controls (a value of auto means infer it from the request):\n" . json_encode($controls, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $response = elite_openai_json_response($system, $user, social_studio_creative_brief_schema(), 'social_studio_creative_brief');
        if (empty($response['ok']) || !is_array($response['data'] ?? null)) {
            throw new RuntimeException('OpenAI could not interpret the creative brief: ' . (string)($response['message'] ?? 'No structured brief returned.'));
        }
        $brief = $response['data'];
        $allowedControls = [
            'focus' => ['veneers', 'implants', 'smile_makeover', 'lip_repositioning', 'dental_education'],
            'purpose' => ['educational', 'social_ad'],
            'audience' => ['any', 'woman', 'man'],
            'age_range' => ['any', '25-34', '35-44', '45-54', '55+'],
            'text_position' => ['source', 'left', 'right'],
        ];
        foreach ($allowedControls as $field => $allowed) {
            $explicit = trim((string)($controls[$field] ?? 'auto'));
            if ($explicit !== '' && $explicit !== 'auto' && in_array($explicit, $allowed, true)) $brief[$field] = $explicit;
        }
        $brief['request'] = $instruction;
        $brief['cta'] = trim((string)($brief['cta'] ?? '')) ?: 'Schedule a complimentary consultation.';
        $brief['location'] = 'Draper, Utah';
        return $brief;
    }

    function social_studio_ready_brand_library(): array
    {
        social_studio_ensure_schema();
        $rows = db_all('SELECT id, source_type, source_post_id, title, published_at, group_name, source_caption, source_hashtags, analysis_json, base_prompt, overlay_spec, overlay_template_json, local_image_key FROM social_studio_base_creatives WHERE status="active" AND analysis_status="ready" AND analysis_version >= 4 AND overlay_template_json IS NOT NULL AND overlay_template_json <> "" ORDER BY COALESCE(published_at, DATE(created_at)) DESC, id DESC');
        return array_values(array_filter($rows, static function (array $row): bool {
            $decoded = json_decode((string)($row['overlay_template_json'] ?? ''), true);
            if (!is_array($decoded)) return false;
            $normalized = social_studio_normalize_overlay_template($decoded);
            return ($normalized['elements'] ?? []) !== [];
        }));
    }

    function social_studio_recommend_brand_reference(array $brief): array
    {
        $library = social_studio_ready_brand_library();
        if ($library === []) throw new RuntimeException('The Brand Library has no ready templates.');
        $candidates = [];
        foreach ($library as $item) {
            $candidates[] = [
                'id' => (int)$item['id'],
                'title' => (string)$item['title'],
                'angle' => (string)$item['group_name'],
                'analysis' => mb_substr(trim((string)$item['analysis_json']), 0, 420),
                'visual_recipe' => mb_substr(trim((string)$item['base_prompt']), 0, 420),
            ];
        }
        $selectedId = 0;
        $reason = '';
        if (elite_openai_is_configured()) {
            $schema = [
                'type' => 'object', 'additionalProperties' => false,
                'properties' => ['reference_id' => ['type' => 'integer'], 'reason' => ['type' => 'string']],
                'required' => ['reference_id', 'reason'],
            ];
            $system = 'Select the single strongest approved Elite Smiles visual system for the structured brief. Match purpose, format, emotional angle, negative-space needs, and audience. Choose only an ID supplied in the candidate list. The reference supplies design grammar and overlay geometry, never source-photo pixels.';
            $response = elite_openai_json_response($system, 'Brief: ' . json_encode($brief, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\nCandidates: " . json_encode($candidates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $schema, 'social_studio_reference_selection');
            if (!empty($response['ok'])) {
                $selectedId = (int)($response['data']['reference_id'] ?? 0);
                $reason = trim((string)($response['data']['reason'] ?? ''));
            }
        }
        $selected = null;
        foreach ($library as $item) if ((int)$item['id'] === $selectedId) { $selected = $item; break; }
        $selected ??= $library[0];
        return ['base' => $selected, 'reason' => $reason !== '' ? $reason : 'Best available approved Elite Smiles visual system for this brief.'];
    }

    function social_studio_create_original_overlay_copy(array $template, array $brief): array
    {
        $template = social_studio_normalize_overlay_template($template);
        if ($template === []) return ['ok' => false, 'message' => 'The visual system has no reusable overlay.'];
        $editable = [];
        foreach ((array)($template['elements'] ?? []) as $index => $element) {
            if ((string)($element['type'] ?? '') !== 'text') continue;
            $text = trim((string)($element['text'] ?? ''));
            if ($text === '' || mb_strlen($text) <= 2 || preg_match('/^[\p{S}\p{P}\$]+$/u', $text)) continue;
            $sourceLines = preg_split('/\R/u', $text) ?: [$text];
            $longestLine = max(array_map('mb_strlen', $sourceLines));
            $safeLineCapacity = $longestLine;
            $editable[] = [
                'index' => $index,
                'text' => $text,
                'max_characters' => max(8, (int)ceil(mb_strlen($text) * 1.05)),
                'line_count' => max(1, count($sourceLines)),
                'max_characters_per_line' => max(4, $safeLineCapacity),
            ];
        }
        if ($editable === []) return ['ok' => false, 'message' => 'The visual system has no editable text blocks.'];
        $itemSchema = ['type' => 'object', 'additionalProperties' => false, 'properties' => ['index' => ['type' => 'integer'], 'text' => ['type' => 'string']], 'required' => ['index', 'text']];
        $schema = ['type' => 'object', 'additionalProperties' => false, 'properties' => ['replacements' => ['type' => 'array', 'minItems' => count($editable), 'maxItems' => count($editable), 'items' => $itemSchema]], 'required' => ['replacements']];
        $system = 'You are the Elite Smiles Master CMO writing ORIGINAL on-image copy inside an approved design system. Replace the source message with the new brief; do not preserve an old treatment, claim, benefit, or headline when it conflicts with the brief. Preserve only the block count, capitalization pattern, CTA role, location role, financing qualification, and approximate character capacity. Obey both line_count and max_characters_per_line exactly; insert manual line breaks when line_count permits. Short, elegant copy is better than copy that risks overflow. Keep Draper, Utah. Use a concise complimentary-consultation CTA. Return every supplied index exactly once. Never include a price, guarantee, invented outcome, testimonial, urgency, or availability.';
        $failure = 'Original overlay generation failed.';
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $response = elite_openai_json_response($system, 'Structured brief: ' . json_encode($brief, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\nApproved layout capacities: " . json_encode($editable, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ($attempt > 1 ? "\nPrevious attempt did not fit. Make every replacement materially shorter." : ''), $schema, 'social_studio_original_overlay');
            if (empty($response['ok']) || !is_array($response['data']['replacements'] ?? null)) {
                $failure = (string)($response['message'] ?? $failure);
                continue;
            }
            $replacements = [];
            foreach ($response['data']['replacements'] as $replacement) $replacements[(int)($replacement['index'] ?? -1)] = trim((string)($replacement['text'] ?? ''));
            $candidate = $template;
            $valid = true;
            foreach ($editable as $source) {
                $index = (int)$source['index'];
                $replacement = $replacements[$index] ?? '';
                $lines = preg_split('/\R/u', $replacement) ?: [];
                if ($replacement === '' || mb_strlen($replacement) > (int)$source['max_characters'] || count($lines) > (int)$source['line_count']) { $valid = false; break; }
                foreach ($lines as $line) if (mb_strlen($line) > (int)$source['max_characters_per_line']) { $valid = false; break 2; }
                $candidate['elements'][$index]['text'] = $replacement;
            }
            $candidate = $valid ? social_studio_fit_original_overlay_template($candidate) : [];
            if ($candidate !== [] && social_studio_overlay_template_fits($candidate)) {
                return ['ok' => true, 'template' => $candidate];
            }
            $failure = 'Original overlay copy exceeded the approved typography capacity.';
        }
        return ['ok' => false, 'message' => $failure . ' Please try again with a shorter message.'];
    }

    function social_studio_create_original_drafts(string $instruction, array $controls, int $count, int $createdBy, string $inspirationImageDataUrl = '', ?array &$createdIds = null): int
    {
        $brief = social_studio_interpret_creative_brief($instruction, $controls);
        $recommendation = social_studio_recommend_brand_reference($brief);
        $base = $recommendation['base'];
        $template = social_studio_get_or_create_overlay_template($base);
        if ($template === []) throw new RuntimeException('The recommended Brand Library reference has no reusable overlay.');
        $rewritten = social_studio_create_original_overlay_copy($template, $brief);
        if (empty($rewritten['ok'])) throw new RuntimeException('The original overlay could not be created safely: ' . (string)($rewritten['message'] ?? 'Unknown error.'));
        $template = social_studio_position_overlay_template((array)$rewritten['template'], (string)$brief['text_position']);
        $productionInstruction = "ORIGINAL CREATION BRIEF (source of truth):\n" . json_encode($brief, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . "\nREFERENCE SELECTION: " . (string)$recommendation['reason']
            . "\nGenerate a genuinely new photograph or clinical visual. Do not copy or crop source pixels. Use only the approved design grammar and deterministic overlay geometry.";
        $remixTemplate = [
            'reference_key' => 'base_' . (int)$base['id'],
            'title' => (string)$base['title'],
            'source_post_id' => (string)$base['source_post_id'],
            'analysis_json' => (string)$base['analysis_json'],
            'base_prompt' => (string)$base['base_prompt'],
            'overlay_spec' => (string)$base['overlay_spec'],
            'overlay_template' => $template,
            'focus' => (string)$brief['focus'],
            'purpose' => (string)$brief['purpose'],
            'audience' => (string)$brief['audience'],
            'age_range' => (string)$brief['age_range'],
            'text_position' => (string)$brief['text_position'],
            'copy_mode' => 'original',
            'source_caption' => (string)($base['source_caption'] ?? ''),
            'source_hashtags' => '',
        ];
        $ids = [];
        $created = social_studio_seed_drafts((string)$brief['focus'], $count, $createdBy, $productionInstruction, $inspirationImageDataUrl, $remixTemplate, $ids);
        $briefJson = json_encode($brief, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        foreach ($ids as $id) {
            db_execute('UPDATE social_studio_drafts SET creation_mode="original", creative_brief_json=:brief, reference_reason=:reason, version_number=1 WHERE id=:id LIMIT 1', ['id' => (int)$id, 'brief' => $briefJson, 'reason' => (string)$recommendation['reason']]);
        }
        if (is_array($createdIds)) $createdIds = $ids;
        return $created;
    }

    function social_studio_draft_guardrails(array $draft, array $visual = []): array
    {
        $template = json_decode((string)($draft['overlay_template_json'] ?? ''), true);
        $elements = is_array($template) ? (array)($template['elements'] ?? []) : [];
        $overlayText = implode(' ', array_map(static fn(array $element): string => (string)($element['text'] ?? ''), $elements));
        $visualReviewed = $visual !== [];
        $checks = [
            ['key' => 'overlay', 'label' => 'Deterministic overlay present', 'pass' => $elements !== []],
            ['key' => 'overlay_fit', 'label' => 'Overlay copy fits every approved text box', 'pass' => $elements !== [] && social_studio_overlay_template_fits(is_array($template) ? $template : [])],
            ['key' => 'cta', 'label' => 'Consultation CTA present', 'pass' => (bool)preg_match('/consult|schedule|discover|book/i', (string)($draft['cta'] ?? '') . ' ' . $overlayText)],
            ['key' => 'claims', 'label' => 'No unsupported price or guarantee', 'pass' => !(bool)preg_match('/\$\s*\d|guaranteed|guarantee results|permanent results/i', (string)($draft['caption'] ?? '') . ' ' . $overlayText)],
            ['key' => 'image_text', 'label' => 'Clean image contains no text or logo', 'pass' => $visualReviewed && !empty($visual['no_text_or_logo'])],
            ['key' => 'focus', 'label' => 'Primary subject is sharp', 'pass' => $visualReviewed && !empty($visual['sharp_focus'])],
            ['key' => 'anatomy', 'label' => 'Face and dental anatomy are credible', 'pass' => $visualReviewed && !empty($visual['credible_anatomy'])],
            ['key' => 'framing', 'label' => 'Eyes and smile framing pass when applicable', 'pass' => $visualReviewed && !empty($visual['framing_pass'])],
        ];
        $passed = count(array_filter($checks, static fn(array $check): bool => !empty($check['pass'])));
        return ['status' => $passed === count($checks) ? 'pass' : 'review', 'passed' => $passed, 'total' => count($checks), 'checks' => $checks, 'visual_notes' => trim((string)($visual['notes'] ?? ''))];
    }

    function social_studio_review_generated_asset(int $draftId, string $rawPath): array
    {
        $draft = db_one('SELECT * FROM social_studio_drafts WHERE id=:id LIMIT 1', ['id' => $draftId]);
        if (!$draft) return [];
        $visual = [];
        $bytes = is_file($rawPath) ? @file_get_contents($rawPath) : false;
        if (elite_openai_is_configured() && is_string($bytes) && $bytes !== '') {
            $schema = [
                'type' => 'object', 'additionalProperties' => false,
                'properties' => [
                    'no_text_or_logo' => ['type' => 'boolean'],
                    'sharp_focus' => ['type' => 'boolean'],
                    'credible_anatomy' => ['type' => 'boolean'],
                    'framing_pass' => ['type' => 'boolean'],
                    'notes' => ['type' => 'string'],
                ],
                'required' => ['no_text_or_logo', 'sharp_focus', 'credible_anatomy', 'framing_pass', 'notes'],
            ];
            $mime = (string)(@mime_content_type($rawPath) ?: 'image/png');
            $response = elite_openai_json_response('You are a strict visual QA reviewer for Elite Smiles social imagery. Check the clean generated image only. framing_pass means both eyes and the full smile are visible for a human portrait; for a non-human clinical/3D visual, pass when the focal anatomy is complete and unobstructed. Fail any readable words, logos, watermarks, blur, cut-off face, distorted teeth, or implausible anatomy.', 'Review this generated asset against the Elite Smiles image guardrails.', $schema, 'social_studio_visual_guardrails', 'data:' . $mime . ';base64,' . base64_encode($bytes));
            if (!empty($response['ok']) && is_array($response['data'] ?? null)) $visual = $response['data'];
        }
        $guardrails = social_studio_draft_guardrails($draft, $visual);
        db_execute('UPDATE social_studio_drafts SET guardrail_json=:guardrails WHERE id=:id LIMIT 1', ['id' => $draftId, 'guardrails' => json_encode($guardrails, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        return $guardrails;
    }

}
