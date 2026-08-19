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
            'model_profile' => ['type' => 'string', 'enum' => ['auto', 'woman', 'man', 'mixed', 'neutral']],
            'color_mood' => ['type' => 'string', 'enum' => ['auto', 'warm_ivory', 'neutral', 'dark_luxury', 'cool_minimal', 'studio']],
            'style_reference_mode' => ['type' => 'string', 'enum' => ['style_anchor', 'photo_reference']],
            'reference_caption' => ['type' => 'string'],
            'visual_format' => ['type' => 'string', 'enum' => ['editorial_card', 'portrait', 'smile_closeup', 'clinical_3d', 'benefit_list', 'dark_luxury']],
            'editorial_angle' => ['type' => 'string'],
            'subject_direction' => ['type' => 'string'],
            'overlay_direction' => ['type' => 'string'],
            'cta' => ['type' => 'string'],
                'location' => ['type' => 'string'],
                'include_financing' => ['type' => 'boolean'],
                'novelty_mode' => ['type' => 'string', 'enum' => ['conservative', 'balanced', 'fresh', 'experimental']],
                'novelty_avoid' => ['type' => 'string'],
            ],
            'required' => ['focus', 'purpose', 'audience', 'age_range', 'text_position', 'visual_format', 'editorial_angle', 'subject_direction', 'overlay_direction', 'cta', 'location', 'include_financing'],
        ];
    }

    function social_studio_normalize_novelty_mode(string $mode): string
    {
        $mode = trim((string)$mode);
        return in_array($mode, ['conservative', 'fresh', 'experimental'], true) ? $mode : 'balanced';
    }

    function social_studio_slugify_social_signal(string $value, int $maxLength = 80): string
    {
        $value = trim((string)preg_replace('/\s+/u', ' ', mb_strtolower($value, 'UTF-8')));
        $value = trim((string)preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $value));
        return mb_substr($value, 0, max(8, $maxLength));
    }

    function social_studio_signal_counters_to_top(array $counter, int $limit = 3): array
    {
        if ($counter === []) {
            return [];
        }
        arsort($counter, SORT_NUMERIC);
        $result = [];
        $i = 0;
        foreach ($counter as $signal => $count) {
            if ($signal === '' || $i >= $limit) break;
            $result[$signal] = (int)$count;
            $i++;
        }
        return $result;
    }

    function social_studio_recent_novelty_profile(string $focus, int $windowDays = 45, int $sampleLimit = 45): array
    {
        social_studio_ensure_schema();
        $focus = in_array($focus, ['veneers', 'implants', 'smile_makeover', 'lip_repositioning', 'dental_education'], true) ? $focus : 'veneers';
        $windowDays = max(14, min(120, $windowDays));
        $sampleLimit = max(10, min(120, $sampleLimit));
        $focus = str_replace('\'', '', $focus);
        $safeWindowDays = (int)$windowDays;
        $safeLimit = (int)$sampleLimit;
        $rows = db_all(
            'SELECT id, created_at, content_focus, creative_brief_json, title, caption FROM social_studio_drafts WHERE creation_mode = "original" AND content_focus = :focus AND created_at >= DATE_SUB(NOW(), INTERVAL ' . $safeWindowDays . ' DAY) ORDER BY created_at DESC LIMIT ' . $safeLimit,
            ['focus' => $focus]
        );

        $positionCounter = [];
        $angleCounter = [];
        $hookCounter = [];
        $voiceCounter = [];
        $examples = [];
        foreach ($rows as $row) {
            $brief = json_decode((string)($row['creative_brief_json'] ?? ''), true);
            if (!is_array($brief)) {
                $brief = [];
            }
            $angle = social_studio_slugify_social_signal((string)($brief['editorial_angle'] ?? ''), 80);
            $topic = social_studio_slugify_social_signal((string)($brief['subject_direction'] ?? ''), 80);
            $overlay = social_studio_slugify_social_signal((string)($brief['overlay_direction'] ?? ''), 80);
            $focusSignal = social_studio_slugify_social_signal((string)($brief['focus'] ?? ''), 80);
            $position = social_studio_slugify_social_signal((string)($brief['text_position'] ?? ''), 40);
            $voice = social_studio_slugify_social_signal((string)($brief['audience'] ?? ''), 40);
            $title = social_studio_slugify_social_signal((string)($row['title'] ?? ''), 60);
            $caption = social_studio_slugify_social_signal((string)($row['caption'] ?? ''), 90);

            if (!empty($angle)) $angleCounter[$angle] = ($angleCounter[$angle] ?? 0) + 1;
            if (!empty($topic)) $hookCounter[$topic] = ($hookCounter[$topic] ?? 0) + 1;
            if (!empty($overlay)) $angleCounter[$overlay] = ($angleCounter[$overlay] ?? 0) + 1;
            if (!empty($focusSignal)) $voiceCounter[$focusSignal] = ($voiceCounter[$focusSignal] ?? 0) + 1;
            if (!empty($position)) $positionCounter[$position] = ($positionCounter[$position] ?? 0) + 1;
            if (!empty($caption)) {
                $hookCounter[$caption] = ($hookCounter[$caption] ?? 0) + 1;
            }
            if (!empty($title)) {
                $hookCounter[$title] = ($hookCounter[$title] ?? 0) + 1;
            }

            $examples[] = [
                'draft_id' => (int)($row['id'] ?? 0),
                'date' => (string)($row['created_at'] ?? ''),
                'angle' => $angle,
                'position' => $position,
                'topic' => $topic,
                'voice' => $voice,
                'cta' => social_studio_slugify_social_signal((string)($brief['cta'] ?? ''), 70),
            ];
        }

        $topAngles = social_studio_signal_counters_to_top($angleCounter, 4);
        $topHooks = social_studio_signal_counters_to_top($hookCounter, 6);
        $topPosition = social_studio_signal_counters_to_top($positionCounter, 3);
        $topVoice = social_studio_signal_counters_to_top($voiceCounter, 3);
        if (count($rows) > 0) {
            $examples = array_slice($examples, 0, min(10, count($examples)));
        }
        return [
            'focus' => $focus,
            'window_days' => $windowDays,
            'sample_count' => count($rows),
            'top_angles' => $topAngles,
            'top_hooks' => $topHooks,
            'top_positions' => $topPosition,
            'top_voice' => $topVoice,
            'examples' => $examples,
        ];
    }

    function social_studio_novelty_instructions(array $brief, array $profile = [], int $batchCount = 1): string
    {
        $mode = social_studio_normalize_novelty_mode((string)($brief['novelty_mode'] ?? 'balanced'));
        $manualAvoid = trim((string)($brief['novelty_avoid'] ?? ''));
        $avoidLines = [];
        if ($manualAvoid !== '') {
            $avoidLines[] = 'User explicit avoid list: ' . $manualAvoid;
        }
        if ($profile === []) {
            $focus = in_array((string)($brief['focus'] ?? ''), ['veneers', 'implants', 'smile_makeover', 'lip_repositioning', 'dental_education'], true) ? (string)$brief['focus'] : 'veneers';
            $profile = social_studio_recent_novelty_profile($focus);
        }

        if (!empty($profile['top_hooks'])) {
            $avoidLines[] = 'Common recent hooks to rotate away from: ' . implode(', ', array_keys((array)$profile['top_hooks']));
        }
        if (!empty($profile['top_angles'])) {
            $avoidLines[] = 'Recent preferred angle patterns to rotate: ' . implode(', ', array_keys((array)$profile['top_angles']));
        }
        if (!empty($profile['top_positions'])) {
            $avoidLines[] = 'Common text positions in recent output: ' . implode(', ', array_keys((array)$profile['top_positions']));
        }
        if (!empty($profile['top_voice'])) {
            $avoidLines[] = 'Recent audience/voice defaults: ' . implode(', ', array_keys((array)$profile['top_voice']));
        }

        $modeRules = [
            'conservative' => 'Keep structure close to prior performance. Maintain the same post family, but change one meaningful variable (one angle, one opening question, one subject nuance).',
            'balanced' => 'Keep the Brand Book style. Avoid straight repetition by changing hook, opening line, and one visual direction in each post batch.',
            'fresh' => 'Prioritize originality: alternate at least two distinct hooks, two subject angles, and two callout directions across the batch.',
            'experimental' => 'Push novelty with controlled variation: at least three posts should differ in voice, structure, and visual angle, while staying strictly within the Brand Book and compliance rules.',
        ];
        $target = max(1, (int)$batchCount);
        return "Creative Agent novelty mode: {$mode}.\n"
            . "Required behavior: {$modeRules[$mode]}\n"
            . "For this request, generate {$target} draft(s) where each draft has a unique first-line hook and no repeated copy rhythm.\n"
            . ($avoidLines !== [] ? "Do not reuse these repeated patterns as first-class defaults: " . implode(' | ', $avoidLines) . "\n" : '')
            . 'Use different opening intent for at least half the batch: mix educational question, social proof-style reassurance, and practical planning language. Keep the tone premium, warm, and consultative.';
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
        $modelProfile = trim((string)($controls['model_profile'] ?? 'auto'));
        if (in_array($modelProfile, ['auto', 'woman', 'man', 'mixed', 'neutral'], true)) {
            $brief['model_profile'] = $modelProfile;
        }
        $colorMood = trim((string)($controls['color_mood'] ?? 'auto'));
        if (in_array($colorMood, ['auto', 'warm_ivory', 'neutral', 'dark_luxury', 'cool_minimal', 'studio'], true)) {
            $brief['color_mood'] = $colorMood;
        }
        $referenceMode = trim((string)($controls['style_reference_mode'] ?? 'style_anchor'));
        if (!in_array($referenceMode, ['style_anchor', 'photo_reference'], true)) {
            $referenceMode = 'style_anchor';
        }
        $brief['style_reference_mode'] = $referenceMode;
        $referenceCaption = trim((string)($controls['reference_caption'] ?? ''));
        if ($referenceCaption !== '') {
            $brief['reference_caption'] = $referenceCaption;
        }
        $brief['request'] = $instruction;
        $brief['novelty_mode'] = social_studio_normalize_novelty_mode((string)($controls['novelty_mode'] ?? 'balanced'));
        if (trim((string)($controls['novelty_avoid'] ?? '')) !== '') {
            $brief['novelty_avoid'] = trim((string)$controls['novelty_avoid']);
        }
        $brief['cta'] = trim((string)($brief['cta'] ?? '')) ?: 'Schedule a complimentary consultation.';
        $brief['location'] = 'Draper, Utah';
        $brief['novelty_profile'] = social_studio_recent_novelty_profile((string)$brief['focus']);
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
            $system = 'Select the single strongest approved Elite Smiles visual system for the structured brief. Match purpose, format, emotional angle, negative-space needs, audience, and the binding Brand Book. Choose only an ID supplied in the candidate list. The reference supplies design grammar and overlay geometry, never source-photo pixels. ' . social_studio_brand_book_prompt();
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
            $safeLineCapacity = max(4, (int)ceil($longestLine * 1.05));
            $editable[] = [
                'index' => $index,
                'text' => $text,
                'max_characters' => max(8, (int)ceil(mb_strlen($text) * 1.12)),
                'line_count' => max(1, count($sourceLines)),
                'max_characters_per_line' => max(4, $safeLineCapacity),
            ];
        }
        if ($editable === []) return ['ok' => false, 'message' => 'The visual system has no editable text blocks.'];
        $itemSchema = ['type' => 'object', 'additionalProperties' => false, 'properties' => ['index' => ['type' => 'integer'], 'text' => ['type' => 'string']], 'required' => ['index', 'text']];
        $schema = ['type' => 'object', 'additionalProperties' => false, 'properties' => ['replacements' => ['type' => 'array', 'minItems' => count($editable), 'maxItems' => count($editable), 'items' => $itemSchema]], 'required' => ['replacements']];
        $system = 'You are the Elite Smiles Master CMO writing ORIGINAL on-image copy inside an approved design system. Replace the source message with the new brief; do not preserve an old treatment, claim, benefit, or headline when it conflicts with the brief. Preserve only the block count, line count, capitalization pattern, CTA role, location role, financing qualification, and approximate character capacity. Obey max_characters_per_line and line_count. Short, elegant copy is better than copy that risks overflow. Keep Draper, Utah. Use a concise complimentary-consultation CTA. Return every supplied index exactly once. Never include a price, guarantee, invented outcome, testimonial, urgency, or availability. ' . social_studio_brand_book_prompt();
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
                $candidate['elements'][$index]['text'] = $replacement;
            }
            if ($valid) {
                return ['ok' => true, 'template' => social_studio_normalize_overlay_template($candidate)];
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
        $created = social_studio_seed_drafts((string)$brief['focus'], $count, $createdBy, $productionInstruction, $inspirationImageDataUrl, $remixTemplate, $ids, $brief);
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
            ['key' => 'overlay_fit', 'label' => 'Overlay geometry stays inside the approved canvas', 'pass' => $elements !== [] && social_studio_overlay_template_fits(is_array($template) ? $template : [])],
            ['key' => 'cta', 'label' => 'Consultation CTA present', 'pass' => (bool)preg_match('/consult|schedule|discover|book/i', (string)($draft['cta'] ?? '') . ' ' . $overlayText)],
            ['key' => 'claims', 'label' => 'No unsupported price or guarantee', 'pass' => !(bool)preg_match('/\$\s*\d|guaranteed|guarantee results|permanent results/i', (string)($draft['caption'] ?? '') . ' ' . $overlayText)],
            ['key' => 'image_text', 'label' => 'Clean image contains no text or logo', 'pass' => $visualReviewed && !empty($visual['no_text_or_logo'])],
            ['key' => 'focus', 'label' => 'Primary subject is sharp', 'pass' => $visualReviewed && !empty($visual['sharp_focus'])],
            ['key' => 'anatomy', 'label' => 'Face and dental anatomy are credible', 'pass' => $visualReviewed && !empty($visual['credible_anatomy'])],
            ['key' => 'realism', 'label' => 'Portrait looks naturally photographed, not AI-generated', 'pass' => $visualReviewed && !empty($visual['realistic_appearance'])],
            ['key' => 'dental_realism', 'label' => 'Teeth look bright, individual, and naturally translucent', 'pass' => $visualReviewed && !empty($visual['natural_dental_aesthetics'])],
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
                    'realistic_appearance' => ['type' => 'boolean'],
                    'natural_dental_aesthetics' => ['type' => 'boolean'],
                    'framing_pass' => ['type' => 'boolean'],
                    'notes' => ['type' => 'string'],
                ],
                'required' => ['no_text_or_logo', 'sharp_focus', 'credible_anatomy', 'realistic_appearance', 'natural_dental_aesthetics', 'framing_pass', 'notes'],
            ];
            $mime = (string)(@mime_content_type($rawPath) ?: 'image/png');
            $response = elite_openai_json_response('You are a strict visual QA reviewer for Elite Smiles social imagery. Check the clean generated image only. For a human portrait, realistic_appearance is true only when skin retains pores, fine expression lines, slight facial asymmetry, realistic eyes and hair, and an unforced photographic expression; fail plastic skin, beauty-filter smoothing, synthetic symmetry, uncanny eyes, or an obvious AI/stock-render appearance. natural_dental_aesthetics is true only when teeth have credible individual shape, subtle translucency and restrained natural tonal variation; fail opaque, perfectly uniform, oversized, over-whitened, duplicated, or synthetic-looking teeth. framing_pass is true when both eyes themselves are completely visible and sharp and the full smile is visible. Count the two visible eyes carefully. Editorial cropping of hair, ears, shoulders, or torso is acceptable, but an incomplete forehead/head crop that makes the portrait feel accidental should fail. For a non-human clinical/3D visual, realistic_appearance and natural_dental_aesthetics pass when the visual is intentionally clinical and anatomically credible. Fail readable words, logos, watermarks, blur, a hidden or cut-off eye, a cut-off smile, distorted teeth, or implausible anatomy. Enforce the active Brand Book: ' . social_studio_brand_book_prompt(), 'Review this generated asset against the Elite Smiles image guardrails.', $schema, 'social_studio_visual_guardrails', 'data:' . $mime . ';base64,' . base64_encode($bytes));
            if (!empty($response['ok']) && is_array($response['data'] ?? null)) $visual = $response['data'];
        }
        $guardrails = social_studio_draft_guardrails($draft, $visual);
        db_execute('UPDATE social_studio_drafts SET guardrail_json=:guardrails WHERE id=:id LIMIT 1', ['id' => $draftId, 'guardrails' => json_encode($guardrails, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        return $guardrails;
    }

}
