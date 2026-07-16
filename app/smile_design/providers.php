<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/openai.php';
require_once dirname(__DIR__) . '/core/google_gemini.php';

interface SmileDesignImageProvider
{
    public function createPreview(array $case, array $photos, array $options = []): array;
}

if (!function_exists('smile_design_lip_repositioning_surgical_prompt')) {
    function smile_design_lip_repositioning_surgical_prompt(array $context): string
    {
        $stringList = static function ($items): string {
            $items = array_values(array_filter(array_map('trim', (array)$items), static fn(string $value): bool => $value !== ''));
            return $items === [] ? '' : implode('; ', $items);
        };

        $targetPhotoLabel = trim((string)($context['target_photo_label'] ?? 'Front'));
        $targetPhotoType = trim((string)($context['target_photo_type'] ?? 'front'));
        $procedure = trim((string)($context['procedure'] ?? 'Lip Repositioning'));
        $customRequest = trim((string)($context['custom_request'] ?? ''));
        $analysisSummary = trim((string)($context['analysis_summary'] ?? ''));
        $analysisFocus = trim((string)($context['analysis_focus'] ?? ''));
        $clinicalDirection = trim((string)($context['clinical_direction'] ?? ''));
        $gingivalDisplay = trim((string)($context['gingival_display'] ?? ''));
        $identityInstructions = trim((string)($context['identity_instructions'] ?? ''));
        $internalNotes = trim((string)($context['internal_notes'] ?? ''));
        $referenceTitle = trim((string)($context['reference_title'] ?? ''));
        $referenceNotes = trim((string)($context['reference_notes'] ?? ''));
        $qaFeedback = $context['qa_feedback'] ?? '';
        if (is_array($qaFeedback)) {
            $qaFeedback = $stringList($qaFeedback);
        }
        $qaFeedback = trim((string)$qaFeedback);
        $isRetry = !empty($context['is_retry']);

        return implode(' ', array_values(array_filter([
            'You are creating a surgical lip repositioning simulation for Elite Smiles.',
            'This is NOT a cosmetic smile design, NOT veneers, NOT whitening, NOT orthodontics, and NOT a portrait retouch.',
            'Use the first image as the source of truth for the patient, teeth, face, lighting, crop, camera angle, and expression.',
            'Additional images, if present, are only context references; do not copy tooth redesign, beauty retouching, or facial changes from them.',
            'Requested procedure: ' . $procedure . '.',
            'Target source angle for this generation: ' . $targetPhotoLabel . ' (' . $targetPhotoType . ').',
            'Edit the first image as the ' . $targetPhotoLabel . ' after preview and keep the same angle, pose, framing, aspect ratio, and lighting.',
            'ONLY EDIT: the upper lip (superior lip). Nothing else.',
            'ZERO CHANGES ALLOWED on: the lower lip (inferior lip), teeth, gums below the upper teeth, chin, cheeks, nose, eyes, skin, hair, background, lighting.',
            'The lower lip must be pixel-identical to the source photo. Do not reshape, plump, thin, move, recolor, or alter the lower lip in any way. If the lower lip looks different from the before, the edit is wrong.',
            'Surgical visual goal: the UPPER lip does not rise as high during the smile. Simulate this by redrawing the lower edge of the upper lip downward so it covers the exposed gum band.',
            'This must be a structural position change of the UPPER lip only, not a gum-color retouch and not a lip-plumping effect.',
            'Do not merely darken, desaturate, blur, shadow, or recolor the exposed gum. The actual pink upper-lip tissue must move downward over the gum line.',
            'The lower border of the UPPER lip must descend completely to or just past the cervical line / gingival-zenith of the upper teeth. ZERO pink gum tissue should be visible above the upper front teeth in the after image. If any gum band is still showing, the lip has not gone low enough — go lower. Do not stop at a partial correction.',
            $targetPhotoType === 'front'
                ? 'FRONT VIEW: the lower edge of the upper lip must land at or just past the gingival zeniths across the FULL visible arch width — left canine, lateral, central, central, lateral, right canine — all covered equally. The entire gum band must be hidden. Zero pink gum above any tooth.'
                : 'ANGLED VIEW (' . $targetPhotoLabel . '): the upper lip must descend along the FULL arch curve visible from this angle. The near-side corner AND the far-side lateral portion must BOTH be covered — do not only fix the near side. Trace the arch from near canine to far canine and drape the upper lip along that full curve so zero gum is exposed on either side.',
            'The surgical effect is an UNFOLDING of the upper lip: the tightly curled/rolled vermilion border opens outward and downward — like a scroll unrolling — so the lip looks fuller and taller because the previously hidden curled tissue is now visible and draped all the way down to where the teeth start (the cervical line). The lip stretches down to meet the tooth tops. This is what makes the lip appear 7 to 9 mm taller — not added volume, but unrolled tissue now visible. Do not be conservative: show the full unrolled result.',
            'The lip SHOULD look fuller and taller after — that is the correct visual outcome of unfolding. What is NOT allowed: filler-style swelling, puffiness, or volumetric inflation. The difference: unfolding makes the lip LONGER (more vermilion surface visible), not THICKER (same tissue depth). The result looks naturally full and soft, stretched gently down to the tooth line.',
            'Make the repositioned upper lip look naturally full, soft, and seamlessly draped down to the gum/tooth line — like the lip relaxed and unrolled into its natural resting position during the smile.',
            'Absolute tooth lock: preserve every tooth exactly — shape, size, color, shade, brightness, alignment, spacing, incisal edges, enamel texture, tooth count, smile width.',
            'Do not change global exposure, white balance, color temperature, contrast, or shadows.',
            'Do not make the patient look younger, slimmer, more glamorous, or like a different person.',
            'Do not create surgical marks, scars, sutures, labels, arrows, text, borders, split screens, or watermarks.',
            $isRetry ? 'This is a retry after QA rejection. Correct the specific QA issues while keeping the original photo as the source of truth. If QA says the upper lip is not visibly lower, the retry must move the actual upper-lip lower border down closer to the cervical tooth line; do not only reduce gum color. If the prior result was too subtle or left the gum band nearly unchanged, make a stronger lip-only lowering. If the prior lip looked overcorrected or unnatural, use a smaller correction and allow more natural gum reveal. If teeth or shading changed, copy the original teeth tone, texture, and brightness exactly.' : '',
            $qaFeedback !== '' ? 'QA feedback from the previous attempt: ' . $qaFeedback . '.' : '',
            $customRequest !== '' ? 'Doctor correction request, interpreted only as upper-lip/gum-display guidance: ' . $customRequest . '.' : '',
            $clinicalDirection !== '' ? 'Clinical direction from case analysis: ' . $clinicalDirection . '.' : '',
            $gingivalDisplay !== '' ? 'Gingival display notes from case analysis: ' . $gingivalDisplay . '.' : '',
            $analysisFocus !== '' ? 'Generation focus from case analysis: ' . $analysisFocus . '.' : '',
            $analysisSummary !== '' ? 'Case analysis summary: ' . $analysisSummary . '.' : '',
            $identityInstructions !== '' ? 'Identity lock instructions: ' . $identityInstructions . '.' : '',
            $stringList($context['constraints'] ?? []) !== '' ? 'Hard constraints from case analysis: ' . $stringList($context['constraints'] ?? []) . '.' : '',
            $stringList($context['risk_flags'] ?? []) !== '' ? 'Risks to avoid: ' . $stringList($context['risk_flags'] ?? []) . '.' : '',
            $stringList($context['doctor_notes'] ?? []) !== '' ? 'Doctor review notes: ' . $stringList($context['doctor_notes'] ?? []) . '.' : '',
            $referenceTitle !== '' ? 'Previous preview reference title: ' . $referenceTitle . '. Treat it as correction context only, not as the source of truth.' : '',
            $referenceNotes !== '' ? 'Previous preview notes: ' . $referenceNotes . '.' : '',
            $internalNotes !== '' ? 'Internal generation notes: ' . $internalNotes . '.' : '',
            'Do not apply AI beauty retouching, skin smoothing, noise reduction, or sharpening to any part of the image. Outside the upper lip zone, every pixel must stay exactly as it is in the source photo.',
            'Before finalizing: check that (1) ZERO pink gum tissue is visible above the upper teeth — the entire gum band is covered, not just reduced, (2) the LOWER lip is completely unchanged from the source photo — pixel-identical, (3) teeth are unchanged in shape, color, and count. If any gum is still showing above the teeth, move the upper lip down further and recheck.',
        ], static fn(string $value): bool => trim($value) !== '')));
    }
}

if (!function_exists('smile_design_data_url_to_temp_png')) {
    function smile_design_data_url_to_temp_png(string $dataUrl, string $prefix = 'esm-mask-'): array
    {
        $dataUrl = trim($dataUrl);
        if ($dataUrl === '' || !preg_match('/^data:image\/png;base64,(.+)$/', $dataUrl, $matches)) {
            return ['ok' => false, 'message' => 'Mask data URL is missing or invalid.'];
        }
        $binary = base64_decode((string)$matches[1], true);
        if (!is_string($binary) || $binary === '') {
            return ['ok' => false, 'message' => 'Mask data could not be decoded.'];
        }
        $tempPath = tempnam(sys_get_temp_dir(), $prefix);
        if ($tempPath === false) {
            return ['ok' => false, 'message' => 'Could not create a temporary mask file.'];
        }
        $pngPath = $tempPath . '.png';
        @unlink($tempPath);
        if (@file_put_contents($pngPath, $binary) === false) {
            return ['ok' => false, 'message' => 'Could not write the temporary mask file.'];
        }
        return ['ok' => true, 'path' => $pngPath];
    }
}

if (!function_exists('smile_design_normalize_selected_teeth')) {
    function smile_design_normalize_selected_teeth(string $selectedTeeth, string $fallback = '[8]'): string
    {
        $selectedTeeth = trim($selectedTeeth);
        if ($selectedTeeth === '') {
            return $fallback;
        }

        $decoded = json_decode($selectedTeeth, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $normalized = [];
            foreach ($decoded as $value) {
                $num = (int)$value;
                if ($num <= 0 || $num > 32) {
                    continue;
                }
                if (!in_array($num, $normalized, true)) {
                    $normalized[] = $num;
                }
            }
            return json_encode($normalized);
        }

        preg_match_all('/-?\d+/', $selectedTeeth, $matches);
        if (!empty($matches[0])) {
            $normalized = [];
            foreach ($matches[0] as $value) {
                $num = (int)$value;
                if ($num <= 0 || $num > 32) {
                    continue;
                }
                if (!in_array($num, $normalized, true)) {
                    $normalized[] = $num;
                }
            }
            return json_encode($normalized);
        }

        return $fallback;
    }
}

if (!function_exists('smile_design_build_overlay_preview')) {
    function smile_design_build_overlay_preview(string $sourcePath, string $maskPath): array
    {
        if (!extension_loaded('gd') || !is_file($sourcePath) || !is_file($maskPath)) {
            return ['ok' => false, 'message' => 'Overlay preview dependencies are unavailable.'];
        }
        $sourceBytes = @file_get_contents($sourcePath);
        $maskBytes = @file_get_contents($maskPath);
        if (!is_string($sourceBytes) || $sourceBytes === '' || !is_string($maskBytes) || $maskBytes === '') {
            return ['ok' => false, 'message' => 'Overlay preview images could not be read.'];
        }
        $source = @imagecreatefromstring($sourceBytes);
        $mask = @imagecreatefromstring($maskBytes);
        if (!$source || !$mask) {
            return ['ok' => false, 'message' => 'Overlay preview images could not be initialized.'];
        }
        $width = imagesx($source);
        $height = imagesy($source);
        $overlay = imagecreatetruecolor($width, $height);
        imagealphablending($overlay, true);
        imagesavealpha($overlay, true);
        $transparent = imagecolorallocatealpha($overlay, 0, 0, 0, 127);
        imagefilledrectangle($overlay, 0, 0, $width, $height, $transparent);
        imagecopyresampled($overlay, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
        $tintColor = imagecolorallocatealpha($overlay, 244, 63, 94, 70);
        $maskWidth = imagesx($mask);
        $maskHeight = imagesy($mask);
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $sampleX = (int)floor(($x / max(1, $width)) * $maskWidth);
                $sampleY = (int)floor(($y / max(1, $height)) * $maskHeight);
                $sampleX = max(0, min($maskWidth - 1, $sampleX));
                $sampleY = max(0, min($maskHeight - 1, $sampleY));
                $rgba = imagecolorsforindex($mask, imagecolorat($mask, $sampleX, $sampleY));
                $active = (($rgba['red'] ?? 0) + ($rgba['green'] ?? 0) + ($rgba['blue'] ?? 0)) > 30;
                if ($active) {
                    imagesetpixel($overlay, $x, $y, $tintColor);
                }
            }
        }
        $tempPath = tempnam(sys_get_temp_dir(), 'esm-overlay-');
        if ($tempPath === false) {
            imagedestroy($source);
            imagedestroy($mask);
            imagedestroy($overlay);
            return ['ok' => false, 'message' => 'Could not create overlay preview path.'];
        }
        $pngPath = $tempPath . '.png';
        @unlink($tempPath);
        imagepng($overlay, $pngPath, 6);
        imagedestroy($source);
        imagedestroy($mask);
        imagedestroy($overlay);
        return is_file($pngPath) ? ['ok' => true, 'path' => $pngPath] : ['ok' => false, 'message' => 'Overlay preview could not be saved.'];
    }
}

if (!function_exists('smile_design_refine_edit_prompt_with_openai')) {
    function smile_design_refine_edit_prompt_with_openai(array $imagePaths, array $context = []): array
    {
        if (!function_exists('elite_openai_images_json_response') || !elite_openai_is_configured()) {
            return ['ok' => false, 'message' => 'OpenAI prompt refinement is not available.'];
        }
        $systemPrompt = 'You are a senior cosmetic dentistry image-edit prompt engineer. Convert a doctor or operator request into an exact technical prompt for an image-edit model. Respect the image selection mask. Preserve everything outside the selected region. Focus on dental morphology, veneer realism, shade, symmetry, tooth-specific shape changes, incisal edge behavior, and strict preservation rules.';
        $userPrompt = implode("\n", array_filter([
            'Procedure: ' . trim((string)($context['procedure'] ?? 'Veneers')),
            'Style: ' . trim((string)($context['style_name'] ?? 'Natural')),
            'Shade target: ' . trim((string)($context['shade_prompt'] ?? 'Chromascop bright white veneer')),
            'Treatment scope: ' . trim((string)($context['treatment_scope'] ?? 'Upper')),
            'Smile width goal: ' . trim((string)($context['smile_width_goal'] ?? 'Keep current smile width')),
            'Selection mode: ' . trim((string)($context['selection_mode'] ?? 'brush')),
            'Selected teeth: ' . trim((string)($context['selected_teeth'] ?? '[8]')),
            'User instruction: ' . trim((string)($context['custom_request'] ?? 'Refine the selected veneers only.')),
            trim((string)($context['analysis_summary'] ?? '')),
            'Image order: first image is the original patient photo, second image is the painted overlay showing the selected correction area, third image is the binary mask. Only the selected teeth region may change.',
            'Return one precise execution prompt for the image-edit model. It must explicitly preserve lips, gums when unselected, skin, face, lighting, crop, camera angle, and all unselected teeth.',
        ], static fn($value): bool => trim((string)$value) !== ''));
        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['refined_prompt', 'selection_summary', 'preserve_rules'],
            'properties' => [
                'refined_prompt' => ['type' => 'string'],
                'selection_summary' => ['type' => 'string'],
                'preserve_rules' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ];
        return elite_openai_images_json_response($imagePaths, $systemPrompt, $userPrompt, $schema, 'smile_edit_refinement', 'high');
    }
}

final class MockSmileDesignImageProvider implements SmileDesignImageProvider
{
    public function createPreview(array $case, array $photos, array $options = []): array
    {
        return [
            'ok' => true,
            'provider' => 'mock',
            'message' => 'AI generation is not connected in Phase 1.',
            'case_id' => (int)($case['id'] ?? 0),
        ];
    }
}

final class GoogleVertexSmileDesignImageProvider implements SmileDesignImageProvider
{
    public function createPreview(array $case, array $photos, array $options = []): array
    {
        return [
            'ok' => false,
            'provider' => 'google_vertex',
            'message' => 'Google Vertex image generation placeholder only. No external call was made.',
        ];
    }
}

final class GoogleGeminiSmileDesignImageProvider implements SmileDesignImageProvider
{
    public function createPreview(array $case, array $photos, array $options = []): array
    {
        if (!function_exists('smile_design_safe_storage_path')) {
            return ['ok' => false, 'provider' => 'google_gemini', 'message' => 'Storage helpers are not available.'];
        }
        if (!elite_gemini_is_configured()) {
            return ['ok' => false, 'provider' => 'google_gemini', 'message' => 'Google Gemini is not configured.'];
        }

        $imagePaths = [];
        $primarySourcePath = '';
        $tempFiles = [];
        $referenceVersion = is_array($options['reference_after_version'] ?? null) ? $options['reference_after_version'] : null;
        foreach ($photos as $photo) {
            $storageKey = (string)($photo['storage_key'] ?? '');
            if ($storageKey === '') {
                continue;
            }
            $resolved = smile_design_safe_storage_path($storageKey);
            if ($resolved && is_file($resolved)) {
                if ($primarySourcePath === '') {
                    $primarySourcePath = $resolved;
                }
                $imagePaths[] = [
                    'path' => $resolved,
                    'mime_type' => elite_gemini_detect_image_mime_type($resolved),
                ];
            }
        }

        if ($imagePaths === []) {
            return ['ok' => false, 'provider' => 'google_gemini', 'message' => 'No usable source photo was found for Gemini generation.'];
        }

        $styleKey = trim((string)($options['lvi_style_key'] ?? $case['selected_style'] ?? $case['lvi_style_key'] ?? 'natural'));
        $normalizedStyleKey = strtolower(str_replace(' ', '_', preg_replace('/^lvi\s+/i', '', $styleKey) ?? $styleKey));
        $styleDetail = function_exists('smile_design_style_detail') ? smile_design_style_detail($normalizedStyleKey) : ['title' => ucfirst(str_replace('_', ' ', $normalizedStyleKey)), 'category' => 'Style', 'description' => ''];
        $styleName = (string)($styleDetail['title'] ?? ucfirst(str_replace('_', ' ', $normalizedStyleKey)));
        $styleCategory = trim((string)($styleDetail['category'] ?? ''));
        $styleDescription = trim((string)($styleDetail['description'] ?? ''));
        $styleMorphology = trim((string)($styleDetail['morphology'] ?? ''));
        $styleEffect = trim((string)($styleDetail['aesthetic_effect'] ?? ''));
        $procedure = trim((string)($options['procedure_label'] ?? $case['procedure_interest'] ?? 'smile design'));
        $procedureMode = function_exists('smile_design_procedure_mode') ? smile_design_procedure_mode($procedure) : 'general';
        $isLipRepositionOnly = $procedureMode === 'lip_repositioning';
        $isDiagnosticPreview = $procedureMode === 'general';
        $isVeneerSimulation = in_array($procedureMode, ['veneers', 'veneers_lip_repositioning'], true);
        $procedureGuidance = function_exists('smile_design_procedure_prompt_guidance') ? smile_design_procedure_prompt_guidance($procedure) : '';
        $shadeDetail = function_exists('smile_design_shade_detail')
            ? smile_design_shade_detail((string)($options['shade_goal'] ?? $case['shade_goal'] ?? ''), $normalizedStyleKey)
            : ['label' => 'Chromascop 210', 'title' => 'Bright White', 'description' => 'Premium bright white.', 'prompt' => 'Chromascop 210 bright white porcelain shade.'];
        $shadeScreenContract = trim((string)($shadeDetail['screen_contract'] ?? ''));
        $treatmentScope = function_exists('smile_design_normalize_treatment_scope')
            ? smile_design_normalize_treatment_scope((string)($options['treatment_scope'] ?? $case['treatment_scope'] ?? ''), $procedure)
            : 'upper';
        $treatmentScopeLabel = function_exists('smile_design_treatment_scope_label')
            ? smile_design_treatment_scope_label($treatmentScope, $procedure)
            : ucfirst($treatmentScope);
        $treatmentScopeGuidance = function_exists('smile_design_treatment_scope_prompt_guidance')
            ? smile_design_treatment_scope_prompt_guidance($treatmentScope, $procedure)
            : '';
        $smileWidthGoal = function_exists('smile_design_normalize_smile_width_goal')
            ? smile_design_normalize_smile_width_goal((string)($options['smile_width_goal'] ?? $case['smile_width_goal'] ?? ''))
            : 'keep_current';
        $smileWidthLabel = function_exists('smile_design_smile_width_label')
            ? smile_design_smile_width_label($smileWidthGoal)
            : 'Keep current smile width';
        $smileWidthGuidance = function_exists('smile_design_smile_width_prompt_guidance')
            ? smile_design_smile_width_prompt_guidance($smileWidthGoal)
            : '';
        $styleAnatomy = function_exists('smile_design_style_generation_guidance') ? smile_design_style_generation_guidance($normalizedStyleKey) : '';
        $customRequest = trim((string)($options['custom_request'] ?? ''));
        $precisionMode = trim((string)($options['precision_mode'] ?? ($options['precision_controls']['precision_mode'] ?? 'balanced')));
        $shapeScaleDelta = (int)($options['shape_scale_delta'] ?? ($options['precision_controls']['shape_scale_delta'] ?? 0));
        $smileLengthDelta = (int)($options['smile_length_delta'] ?? ($options['precision_controls']['smile_length_delta'] ?? 0));
        $smileWidthDelta = (int)($options['smile_width_delta'] ?? ($options['precision_controls']['smile_width_delta'] ?? 0));
        $shadeBrightnessDelta = (int)($options['shade_brightness_delta'] ?? ($options['precision_controls']['shade_brightness_delta'] ?? 0));
        $anchorPointsRaw = trim((string)($options['anchor_points'] ?? ($options['precision_controls']['anchor_points'] ?? '')));
        $contourPointsRaw = trim((string)($options['contour_points'] ?? ($options['precision_controls']['contour_points'] ?? '')));
        $selectionMode = trim((string)($options['selection_mode'] ?? 'contour'));
        $brushMaskData = trim((string)($options['brush_mask_data'] ?? ''));
        $brushOverlayData = trim((string)($options['brush_overlay_data'] ?? ''));
        $editorMode = trim((string)($options['editor_mode'] ?? 'automatic'));
        $selectedTeeth = smile_design_normalize_selected_teeth(trim((string)($options['selected_teeth'] ?? '')));
        $selectedTeethNumbers = json_decode($selectedTeeth, true);
        $selectedTeethNumbers = is_array($selectedTeethNumbers) ? array_map('intval', $selectedTeethNumbers) : [];
        $selectedPosteriorTeeth = array_values(array_intersect($selectedTeethNumbers, [4, 13]));
        $toothOffsets = trim((string)($options['tooth_offsets'] ?? '{}'));
        $toothAdjustments = trim((string)($options['tooth_adjustments'] ?? '{}'));
        $internalNotes = trim((string)($options['notes'] ?? ''));
        $targetPhotoLabel = trim((string)($options['target_photo_label'] ?? 'Front'));
        $targetPhotoType = trim((string)($options['target_photo_type'] ?? $options['photo_type'] ?? 'front'));
        $cameraMetadataSummary = trim((string)($options['camera_metadata_summary'] ?? ''));
        $analysis = is_array($options['case_analysis'] ?? null) ? $options['case_analysis'] : [];
        $analysisSummary = trim((string)($options['analysis_summary'] ?? ($analysis['summary'] ?? '')));
        $analysisFocus = trim((string)($analysis['recommended_generation_focus'] ?? ''));
        $identityInstructions = trim((string)($analysis['preserve_identity_instructions'] ?? ''));
        $constraints = array_values(array_filter(array_map('trim', (array)($analysis['constraints'] ?? []))));
        $primaryChanges = array_values(array_filter(array_map('trim', (array)($analysis['primary_changes'] ?? []))));
        $riskFlags = array_values(array_filter(array_map('trim', (array)($analysis['risk_flags'] ?? []))));
        $doctorNotes = array_values(array_filter(array_map('trim', (array)($analysis['doctor_review_notes'] ?? []))));
        $recommendedProcedure = trim((string)($analysis['recommended_procedure'] ?? ''));
        $clinicalDirection = trim((string)($analysis['clinical_direction'] ?? ''));
        $previewSuitability = trim((string)($analysis['preview_suitability'] ?? ''));
        $missingTeeth = trim((string)($analysis['missing_or_compromised_teeth'] ?? ''));
        $gingivalDisplay = trim((string)($analysis['gingival_display'] ?? ''));
        $scope = trim((string)($analysis['smile_scope'] ?? ''));
        $referenceTitle = trim((string)($referenceVersion['version_title'] ?? ''));
        $referenceNotes = trim((string)($referenceVersion['notes'] ?? ''));
        $sourceWidth = (int)($photos[0]['width'] ?? 0);
        $sourceHeight = (int)($photos[0]['height'] ?? 0);
        $sourceDimensionsInstruction = ($sourceWidth > 0 && $sourceHeight > 0)
            ? 'Output geometry lock: return the same photo dimensions and aspect as the source (' . $sourceWidth . 'x' . $sourceHeight . ' pixels after app normalization). Do not make the head, face, or mouth larger or smaller in the frame.'
            : 'Output geometry lock: return the same photo dimensions, aspect ratio, and visual scale as the source. Do not make the head, face, or mouth larger or smaller in the frame.';
        $styleReferenceCount = 0;
        if (!$isLipRepositionOnly && function_exists('smile_design_lvi_style_reference_assets')) {
            foreach (smile_design_lvi_style_reference_assets($normalizedStyleKey, 2) as $referenceAsset) {
                $mimeType = trim((string)($referenceAsset['mime_type'] ?? ''));
                $imagePaths[] = [
                    'path' => (string)$referenceAsset['path'],
                    'mime_type' => $mimeType !== '' ? $mimeType : elite_gemini_detect_image_mime_type((string)$referenceAsset['path']),
                ];
                $styleReferenceCount++;
            }
        }
        $porcelainFinishReferenceIncluded = false;
        if ($isVeneerSimulation && function_exists('smile_design_private_root')) {
            $porcelainFinishReferencePath = smile_design_private_root() . DIRECTORY_SEPARATOR . 'references' . DIRECTORY_SEPARATOR . 'flawless-veneer-finish.png';
            if (is_file($porcelainFinishReferencePath)) {
                $imagePaths[] = [
                    'path' => $porcelainFinishReferencePath,
                    'mime_type' => elite_gemini_detect_image_mime_type($porcelainFinishReferencePath),
                ];
                $porcelainFinishReferenceIncluded = true;
            }
        }
        $veneerAngleGuidance = '';
        if ($isVeneerSimulation && $targetPhotoType !== 'front') {
            $veneerAngleGuidance = 'For this angled veneer view, keep the visible laterals and canines in the same porcelain shade family and brightness level as the front view. Do not let side-angle shadow, perspective, or natural tooth warmth make the visible veneers look yellower, greyer, duller, or less finished. The side view must preserve the same premium bright-white impression as the front view, especially across the visible canine-to-canine segment.';
        }
        $widerSmileFrontGuidance = '';
        if ($isVeneerSimulation && $targetPhotoType === 'front' && smile_design_normalize_smile_width_goal($smileWidthGoal) === 'wider_smile') {
            $widerSmileFrontGuidance = implode(' ', [
                'Front-view wider-smile enforcement:',
                'the after must reduce the black buccal corridor shadows at both mouth corners.',
                'Extend the visible porcelain presence laterally with broader, better-supported canines and premolars so the last visible side teeth sit closer to the inner mouth corners.',
                'Use the Luke Thornberg-style failure case as the thing to avoid: do not leave dark holes or empty side spaces between the side veneers and the inside corners of the mouth.',
                'Make teeth #4 and #13, plus adjacent premolars where visible, read more exposed and more substantial without changing the lips, jaw, face width, or camera crop.',
                'If the front after still has obvious dark side gaps beside the posterior veneers, the wider-smile instruction has failed.',
            ]);
        }
        $brushMaskPath = '';
        $brushOverlayPath = '';
        $refinedSelectionPrompt = '';
        if ($isVeneerSimulation && $selectionMode === 'brush' && $brushMaskData !== '') {
            $maskResult = smile_design_data_url_to_temp_png($brushMaskData, 'esm-brush-mask-');
            if (!empty($maskResult['ok'])) {
                $brushMaskPath = (string)$maskResult['path'];
                $tempFiles[] = $brushMaskPath;
                if ($brushOverlayData !== '') {
                    $overlayResult = smile_design_data_url_to_temp_png($brushOverlayData, 'esm-brush-overlay-');
                    if (!empty($overlayResult['ok'])) {
                        $brushOverlayPath = (string)$overlayResult['path'];
                        $tempFiles[] = $brushOverlayPath;
                    }
                }
                if ($brushOverlayPath === '' && $primarySourcePath !== '') {
                    $overlayPreview = smile_design_build_overlay_preview($primarySourcePath, $brushMaskPath);
                    if (!empty($overlayPreview['ok'])) {
                        $brushOverlayPath = (string)$overlayPreview['path'];
                        $tempFiles[] = $brushOverlayPath;
                    }
                }
                if ($primarySourcePath !== '' && $brushOverlayPath !== '') {
                    $refinement = smile_design_refine_edit_prompt_with_openai([
                        $primarySourcePath,
                        $brushOverlayPath,
                        $brushMaskPath,
                    ], [
                        'procedure' => $procedure,
                        'style_name' => $styleName,
                        'shade_prompt' => (string)($shadeDetail['prompt'] ?? ''),
                        'treatment_scope' => $treatmentScopeLabel,
                        'smile_width_goal' => $smileWidthLabel,
                        'selection_mode' => $selectionMode,
                        'selected_teeth' => $selectedTeeth,
                        'custom_request' => $customRequest,
                        'analysis_summary' => $analysisSummary,
                    ]);
                    if (!empty($refinement['ok'])) {
                        $refinedData = (array)($refinement['data'] ?? []);
                        $refinedSelectionPrompt = trim((string)($refinedData['refined_prompt'] ?? ''));
                    }
                }
                if ($brushOverlayPath !== '') {
                    $imagePaths[] = [
                        'path' => $brushOverlayPath,
                        'mime_type' => elite_gemini_detect_image_mime_type($brushOverlayPath),
                    ];
                }
                $imagePaths[] = [
                    'path' => $brushMaskPath,
                    'mime_type' => elite_gemini_detect_image_mime_type($brushMaskPath),
                ];
            }
        }

        $promptParts = [
            'You are editing a dental consultation photo for Elite Smiles.',
            'Keep the exact same person and the exact same portrait.',
            'Do not change the face, cheeks, jawline, nose, eyes, eyebrows, skin texture, skin tone, lips, hair, age, expression, camera angle, framing, or lighting.',
            $isLipRepositionOnly
                ? 'For lip repositioning only, simulate the surgical outcome of restricting a hypermobile/short upper lip: the upper lip should elevate less during the smile, sit visibly lower over the gums, and reduce a broad gummy band by roughly half to two-thirds when present; keep the teeth themselves unchanged.'
                : 'Only change the mouth, smile, and visible teeth required for the dental preview.',
            $isLipRepositionOnly
                ? 'The output must show a clear reduction in gummy-smile display by lowering the upper-lip smile line toward the arch/incisal contour of the upper teeth. The inferior border of the superior lip should begin where the arches of the upper teeth start. The upper lip should look less curled upward and more unfolded/full as it drapes lower over the gum/upper-tooth line. The teeth should not be reshaped, whitened, straightened, enlarged, or replaced.'
                : ($isDiagnosticPreview ? 'The output should show a conservative diagnostic smile preview based on visible evidence. Do not over-treat or invent an aggressive irreversible plan.' : 'The output must show a clear procedure-specific dental improvement in the mouth area. Do not leave the teeth and smile unchanged.'),
            $isDiagnosticPreview
                ? 'Make any change modest, clinically plausible, and reversible-looking; everything outside the mouth stays the same.'
                : 'Make the change visible but realistic: the patient should clearly see the proposed smile improvement while everything outside the mouth stays the same.',
            'The first image is the original patient photo. Additional images are references and may include the current smile preview version.',
            'Target source angle for this generation: ' . $targetPhotoLabel . ' (' . $targetPhotoType . ').',
            'Edit the first image as the ' . $targetPhotoLabel . ' after preview and keep its same angle, pose, crop, and lighting.',
            ($cameraMetadataSummary !== '' ? $cameraMetadataSummary : ''),
            $sourceDimensionsInstruction,
            'Composition lock: do not zoom in, zoom out, crop tighter, crop wider, shift the head in frame, or change how much of the face is visible. The before and after should have the same framing and camera distance, with the mouth edited inside the existing composition only.',
            (!$isLipRepositionOnly ? 'Camera metadata, EXIF details, lighting, and composition instructions apply to the photo around the smile only. They must not be interpreted as instructions to preserve the original tooth shape, tooth color, enamel defects, or smile design.' : ''),
            ($isVeneerSimulation ? 'Critical veneer boundary: the face, lips, skin, hair, background, and lighting are locked, but the visible treated teeth are NOT locked. The treated teeth must be visibly redesigned and recolored into the selected LVI veneer outcome.' : ''),
            ($isVeneerSimulation && $brushMaskPath === '' ? 'No edit mask is provided in this pass. Perform a precise localized veneer redesign by editing only the visible teeth and the minimal mouth contact edge needed for realism; preserve the rest of the source photo as-is.' : ''),
            ($isVeneerSimulation && $brushMaskPath !== '' ? 'The final two reference images define the correction selection: first a painted overlay preview, then a binary mask. Modify only the selected teeth inside that masked region. Preserve all unselected teeth, lips, gums outside the selection, skin, facial identity, framing, and lighting exactly.' : ''),
            'Outside the smile zone, treat the image as locked. Forehead, eyes, brows, nose, cheeks, skin pores, hair, ears, jawline, neck, clothing, jewelry, and background must remain visually unchanged from the source photo.',
            'The after must read like the exact same photo with only the smile edited. In a before/after slider or opacity overlay, the face outside the mouth should align and appear unchanged.',
            'Do not retouch, smooth, relight, recolor, beautify, or reshape any non-dental region. Keep the lips unchanged except for the minimal natural contour contact needed around the visible teeth and smile line.',
            ($styleReferenceCount > 0 ? 'The last ' . $styleReferenceCount . ' reference image(s) are LVI ' . $styleName . ' sample smiles. Use them only for tooth anatomy, incisal step, embrasures, line angles, canine character, and smile-width expression. Do not copy the reference patient, lips, gingiva, face, lighting, or camera treatment.' : ''),
            ($porcelainFinishReferenceIncluded ? 'One additional reference image shows the desired final veneer material finish. Use it only for IPS e.max-style lithium disilicate glass-ceramic surface quality: flawless brand-new veneers, clean high-value body shade, glazed ceramic gloss, enamel-like optical depth, smooth polished finish, no yellow pigment, no stains, no mottling, and subtle translucent incisal edge only at the bottom tips. Do not copy that reference image crop, lips, skin, gums, smile shape, lighting, or face.' : ''),
            ($porcelainFinishReferenceIncluded ? 'Shade hierarchy rule: when the selected shade is Elite Smiles 100 / Ultra White, use the porcelain reference as the maximum wow-factor brightness anchor. When the selected shade is Chromascop 110, match a clinical Hollywood-white porcelain anchor just below Ultra White. For every other Chromascop shade, keep the same flawless porcelain material but reduce brightness according to the selected shade target. Do not let the material reference force every shade to 100 or 110.' : ''),
            ($isVeneerSimulation && $shadeScreenContract !== '' ? $shadeScreenContract : ''),
            $isLipRepositionOnly ? 'No LVI tooth style applies because this is Lip Repositioning only.' : 'Target smile style: ' . $styleName . '.',
            (!$isLipRepositionOnly && $styleCategory !== '' ? 'LVI style category: ' . $styleCategory . '.' : ''),
            (!$isLipRepositionOnly && $styleDescription !== '' ? 'LVI style guidance: ' . $styleDescription : ''),
            (!$isLipRepositionOnly && $styleMorphology !== '' ? 'LVI morphology target: ' . $styleMorphology : ''),
            (!$isLipRepositionOnly && $styleEffect !== '' ? 'Desired aesthetic effect: ' . $styleEffect : ''),
            (!$isLipRepositionOnly && $styleAnatomy !== '' ? 'LVI anatomy blueprint: ' . $styleAnatomy : ''),
            ($isVeneerSimulation ? 'Apply the LVI style as an actual tooth-design change, not just a label. Change the visible treated tooth forms to match the LVI morphology: line angles, incisal edges, embrasures, central/lateral/canine proportions, symmetry, and arch rhythm must all visibly improve compared with the original photo.' : ''),
            ($isVeneerSimulation ? 'For LVI Natural specifically, do not preserve the original tooth anatomy. Create clear premium Natural veneer anatomy: stronger square dominant centrals, cleaner vertical line angles, slightly shorter rounded laterals, gently defined canines, progressive embrasures, and a smoother smile arc. The result must remain human and natural, but the before/after should show an obvious design upgrade.' : ''),
            (!$isLipRepositionOnly ? 'Selected treatment scope: ' . $treatmentScopeLabel . '.' : ''),
            (!$isLipRepositionOnly && $treatmentScopeGuidance !== '' ? $treatmentScopeGuidance : ''),
            (!$isLipRepositionOnly ? 'Selected smile width goal: ' . $smileWidthLabel . '.' : ''),
            (!$isLipRepositionOnly && $smileWidthGuidance !== '' ? $smileWidthGuidance : ''),
            ($widerSmileFrontGuidance !== '' ? $widerSmileFrontGuidance : ''),
            ($isVeneerSimulation ? 'Selected veneer shade target: ' . (string)$shadeDetail['prompt'] : ''),
            ($isVeneerSimulation ? 'Shade enforcement: the selected shade is a finished porcelain target, not a filter over the original teeth. Replace the old tooth value entirely. Elite Smiles 100 / Ultra White is the brightest and cleanest result in the whole shade system; Chromascop 110 is the clinical Hollywood-white anchor just below it; all other shades step down from those anchors while remaining stain-free porcelain.' : ''),
            ($isVeneerSimulation && (string)($shadeDetail['code'] ?? '') === '100' ? 'Elite Smiles 100 front-view pass/fail rule: the visible anterior veneers must be obviously whiter, brighter, and cleaner than the source teeth and than a conservative prior generated set. Do not leave the front smile at the same value. Push the veneer body to luminous neutral white with crisp glazed ceramic highlights, zero yellow, zero cream, and only delicate incisal translucency at the bottom edge.' : ''),
            ($isVeneerSimulation ? 'Default veneer material: IPS e.max-style lithium disilicate glass-ceramic. The final teeth should show a glazed ceramic surface with realistic specular highlights, smooth polished body, enamel-like optical depth, and delicate incisal translucency, not flat digital paint or generic whitening.' : ''),
            ($isVeneerSimulation ? 'Veneer transformation strength: the after must look like new ceramic veneers were placed, not like the original teeth were simply cleaned. The visible treated teeth should be at least two obvious screen-value steps whiter than the before photo while still retaining porcelain dimension and highlights.' : ''),
            ($isVeneerSimulation ? 'Brand-new veneer finish target: the finished veneers must look clean and perfect like newly seated cosmetic porcelain. The main body shade should be luminous neutral white with no cream, no yellow, and no natural-tooth discoloration. Keep translucency delicate and limited to the incisal/bottom edge so the teeth stay dimensional without looking stained.' : ''),
            ($isVeneerSimulation ? 'For veneers, the visible anterior tooth surfaces must read as complete porcelain restorations in the selected shade. Do not leave behind natural yellowing, original tooth color, craze lines, mottling, stains, chips, asymmetry, uneven incisal edges, or patchy enamel bleed-through.' : ''),
            ($isVeneerSimulation ? 'Before-vs-after mandate: compared with the original photo, the after must have a clearly improved smile silhouette, cleaner tooth proportions, smoother incisal architecture, more even spacing, and a clearly whiter porcelain shade. If the tooth shape or shade looks almost the same as the before, the edit has failed.' : ''),
            ($isVeneerSimulation ? 'Similarity failure rule: if a patient looking at the Compare, B/A, or Zoom view would say the teeth barely changed, the image is unacceptable. Increase the LVI shape change and porcelain brightness until the dental improvement is unmistakable at normal viewing distance.' : ''),
            ($isVeneerSimulation ? 'Keep the veneers uniformly bright within the selected shade family while preserving natural-looking incisal translucency, subtle depth, and polished glaze. The result should look like high-end porcelain, not natural teeth with whitening.' : ''),
            ($isVeneerSimulation ? 'Visible veneer surfaces must be pristine and flawless: zero yellow pigmentation, zero brown undertone, zero white spots, zero stain halos, zero craze lines, zero cracks, zero mottling, and zero enamel blemishes. If any visible veneer area reads yellow, stained, or imperfect, the edit is wrong.' : ''),
            ($isVeneerSimulation ? 'Make the veneers look like newly delivered premium porcelain: perfectly clean value, consistent body shade, smooth finish, and no natural-tooth defects showing through anywhere on the visible veneer surfaces.' : ''),
            ($isVeneerSimulation ? 'Bias veneer previews toward premium cosmetic brightness rather than conservative blending. Natural and Enhanced styles should still read as clearly white veneers on screen, just with different shape language than Hollywood.' : ''),
            ($isVeneerSimulation ? 'The whitening jump must be immediately visible at normal screen viewing distance and in the Zoom view. If the after could be mistaken for only a mild cleanup or small whitening pass, it is too subtle - make the value increase stronger while keeping the same patient and realistic porcelain depth.' : ''),
            ($isVeneerSimulation ? 'If the model must choose between slightly too natural and slightly too white, choose the whiter result as long as it still looks like dimensional porcelain rather than flat paint.' : ''),
            ($veneerAngleGuidance !== '' ? $veneerAngleGuidance : ''),
            'Requested procedure: ' . $procedure . '.',
            ($procedureGuidance !== '' ? $procedureGuidance : ''),
            'Procedure realism rules are binding: stay inside the selected treatment scope, do not add unsupported procedures, and do not create a fantasy smile that could not plausibly be treated from this case.',
            ($recommendedProcedure !== '' ? 'Case analysis recommended procedure: ' . $recommendedProcedure . '.' : ''),
            ($clinicalDirection !== '' ? 'Clinical direction from case analysis: ' . $clinicalDirection . '.' : ''),
            ($previewSuitability !== '' ? 'Preview suitability from case analysis: ' . $previewSuitability . '.' : ''),
            ($scope !== '' ? 'Smile scope: ' . $scope . '.' : ''),
            ($analysisSummary !== '' ? 'Case analysis summary: ' . $analysisSummary : ''),
            ($analysisFocus !== '' ? 'Generation focus: ' . $analysisFocus : ''),
            ($missingTeeth !== '' ? 'Missing or compromised teeth notes: ' . $missingTeeth . '.' : ''),
            ($gingivalDisplay !== '' ? 'Gingival display notes: ' . $gingivalDisplay . '.' : ''),
            ($identityInstructions !== '' ? 'Identity lock instructions: ' . $identityInstructions : ''),
            ($doctorNotes !== [] ? 'Doctor review notes from case analysis: ' . implode('; ', $doctorNotes) . '.' : ''),
            ($internalNotes !== '' ? 'Internal generation notes: ' . $internalNotes . '.' : ''),
            'Keep the result realistic and consultation-grade.',
            'Do not add text, labels, watermarks, borders, split screens, or logos.',
        ];
        if ($referenceVersion) {
            $promptParts[] = 'Use the current preview reference to keep the same overall treatment direction and revise only the requested mouth details.';
            if ($referenceTitle !== '') {
                $promptParts[] = 'Current preview version: ' . $referenceTitle . '.';
            }
            if ($referenceNotes !== '') {
                $promptParts[] = 'Current preview notes: ' . $referenceNotes . '.';
            }
        }
        if ($primaryChanges !== []) {
            $promptParts[] = 'Primary requested dental changes: ' . implode('; ', $primaryChanges) . '.';
            $promptParts[] = 'These primary dental changes must be visible in the final image.';
        }
        if ($constraints !== []) {
            $promptParts[] = 'Hard constraints: ' . implode('; ', $constraints) . '.';
        }
        if ($riskFlags !== []) {
            $promptParts[] = 'Watch these risks: ' . implode('; ', $riskFlags) . '.';
        }
        if ($customRequest !== '') {
            $promptParts[] = 'Additional design request: ' . $customRequest . '.';
            $promptParts[] = 'Apply the additional design request only when it fits the selected procedure and visible case; keep changes in the teeth and smile area only.';
        }
        if ($refinedSelectionPrompt !== '') {
            $promptParts[] = 'OpenAI technical edit refinement: ' . $refinedSelectionPrompt . '.';
        }
        if ($precisionMode !== 'balanced') {
            $promptParts[] = 'Precision mode: ' . $precisionMode . '.';
        }
        if ($shapeScaleDelta !== 0) {
            $promptParts[] = 'Priority shape-scale adjustment: shift visible incisal morphology by about ' . $shapeScaleDelta . '% while preserving clinical anatomy.';
        }
        if ($smileLengthDelta !== 0) {
            $promptParts[] = 'Priority smile-length adjustment: alter vertical tooth/gingival proportion by about ' . $smileLengthDelta . '% where clinically plausible.';
        }
        if ($smileWidthDelta !== 0) {
            $promptParts[] = 'Priority smile-width adjustment: adjust visible smile breadth by about ' . $smileWidthDelta . '% while keeping face alignment and composition stable.';
            if ($smileWidthDelta > 0 && $selectedPosteriorTeeth !== []) {
                $promptParts[] = 'Posterior expansion priority: increase the visible facial display of tooth ' . implode(' and tooth ', $selectedPosteriorTeeth) . ' toward the corners of the smile. Make these posterior veneers visibly broader in the dental arch, reduce the dark buccal corridors on both sides, and create a wider full-arch smile. Preserve teeth #8 and #9, the lip position, face, head size, and camera framing.';
            }
        }
        if ($shadeBrightnessDelta !== 0) {
            $promptParts[] = 'Shade/brightness adjustment: increase or reduce veneer brightness by about ' . $shadeBrightnessDelta . ' points while preserving porcelain texture and incisal translucency.';
        }
        if ($anchorPointsRaw !== '') {
            $promptParts[] = 'Anchor guidance: use the provided edit anchors only for local constraint and deformation distribution, without changing unrelated non-dental regions. ' . $anchorPointsRaw . '.';
        }
        if ($editorMode !== '') {
            $promptParts[] = 'Editor mode: ' . $editorMode . '.';
        }
        if ($selectedTeeth !== '') {
            $promptParts[] = 'Selected teeth for this correction: ' . $selectedTeeth . '.';
        }
        if ($toothOffsets !== '' && $toothOffsets !== '{}') {
            $promptParts[] = 'Manual selected-tooth target displacement in image percentage points (positive x is right, positive y is down): ' . $toothOffsets . '. Move only the corresponding selected teeth toward these target positions.';
        }
        if ($toothAdjustments !== '' && $toothAdjustments !== '{}') {
            $promptParts[] = 'Per-tooth precision adjustments: ' . $toothAdjustments . '. Apply each shape, length, and width delta only to its numbered tooth; edge_smoothing describes the intended clean mask boundary and is not a request to blur the tooth. Do not copy one tooth adjustment across the rest of the smile.';
        }

        if ($primaryChanges === [] && $customRequest === '') {
            $promptParts[] = $isDiagnosticPreview
                ? 'Even without extra free-text instructions, keep this as a conservative diagnostic preview while preserving the same person.'
                : 'Even without extra free-text instructions, create a visible dental preview for the requested procedure while preserving the same person.';
        }

        if ($isLipRepositionOnly) {
            $promptParts = [smile_design_lip_repositioning_surgical_prompt([
                'procedure' => $procedure,
                'target_photo_label' => $targetPhotoLabel,
                'target_photo_type' => $targetPhotoType,
                'custom_request' => $customRequest,
                'analysis_summary' => $analysisSummary,
                'analysis_focus' => $analysisFocus,
                'clinical_direction' => $clinicalDirection,
                'gingival_display' => $gingivalDisplay,
                'identity_instructions' => $identityInstructions,
                'constraints' => $constraints,
                'risk_flags' => $riskFlags,
                'doctor_notes' => $doctorNotes,
                'internal_notes' => $internalNotes,
                'reference_title' => $referenceTitle,
                'reference_notes' => $referenceNotes,
                'qa_feedback' => $options['lip_qa_feedback'] ?? '',
                'is_retry' => !empty($options['lip_surgical_retry']),
            ])];
        }

        $prompt = implode(' ', array_values(array_filter($promptParts, static fn($value): bool => trim((string)$value) !== '')));
        try {
            $result = elite_gemini_generate_image_edit($imagePaths, $prompt, [
                'model' => GOOGLE_GEMINI_IMAGE_MODEL,
            ]);
        } finally {
            foreach ($tempFiles as $tempPath) {
                if (is_string($tempPath) && $tempPath !== '' && is_file($tempPath)) {
                    @unlink($tempPath);
                }
            }
        }

        if (empty($result['ok'])) {
            return [
                'ok' => false,
                'provider' => 'google_gemini',
                'message' => (string)($result['message'] ?? 'Google Gemini image generation failed.'),
                'request' => ['prompt' => $prompt],
                'response' => $result['response'] ?? null,
            ];
        }

        return [
            'ok' => true,
            'provider' => 'google_gemini',
            'prompt_summary' => $isLipRepositionOnly ? ($customRequest !== '' ? $customRequest : 'Surgical lip repositioning preview') : ($customRequest !== '' ? $customRequest : ($styleName . ' ' . $procedure . ' preview')),
            'request' => $result['request'] ?? ['prompt' => $prompt],
            'response' => $result['response'] ?? null,
            'image_base64' => (string)$result['image_base64'],
            'mime_type' => (string)($result['mime_type'] ?? 'image/png'),
            'revised_prompt' => $refinedSelectionPrompt,
        ];
    }
}

final class OpenAISmileDesignImageProvider implements SmileDesignImageProvider
{
    public function createPreview(array $case, array $photos, array $options = []): array
    {
        if (!function_exists('smile_design_safe_storage_path')) {
            return ['ok' => false, 'provider' => 'openai', 'message' => 'Storage helpers are not available.'];
        }

        $imagePaths = [];
        $primarySourcePath = '';
        $tempFiles = [];
        $referenceVersion = is_array($options['reference_after_version'] ?? null) ? $options['reference_after_version'] : null;
        $primaryBeforeIncluded = false;
        foreach ($photos as $photo) {
            $kind = trim((string)($photo['kind'] ?? 'before'));
            if ($kind === 'before') {
                if ($primaryBeforeIncluded) {
                    continue;
                }
                $primaryBeforeIncluded = true;
            }
            $storageKey = (string)($photo['storage_key'] ?? '');
            if ($storageKey === '') {
                continue;
            }
            $resolved = smile_design_safe_storage_path($storageKey);
            if ($resolved && is_file($resolved)) {
                $normalized = $this->normalizeForOpenAI($resolved);
                if (!empty($normalized['path'])) {
                    if ($primarySourcePath === '') {
                        $primarySourcePath = (string)$normalized['path'];
                    }
                    $imagePaths[] = [
                        'path' => (string)$normalized['path'],
                        'mime_type' => (string)($normalized['mime_type'] ?? (@mime_content_type((string)$normalized['path']) ?: 'application/octet-stream')),
                    ];
                    if (!empty($normalized['temporary'])) {
                        $tempFiles[] = (string)$normalized['path'];
                    }
                }
            }
        }

        if ($imagePaths === []) {
            return ['ok' => false, 'provider' => 'openai', 'message' => 'No usable source photo was found for AI generation.'];
        }

        $styleKey = trim((string)($options['lvi_style_key'] ?? $case['selected_style'] ?? $case['lvi_style_key'] ?? 'natural'));
        $normalizedStyleKey = strtolower(str_replace(' ', '_', preg_replace('/^lvi\s+/i', '', $styleKey) ?? $styleKey));
        $styleDetail = function_exists('smile_design_style_detail') ? smile_design_style_detail($normalizedStyleKey) : ['title' => ucfirst(str_replace('_', ' ', $normalizedStyleKey)), 'category' => 'Style', 'description' => ''];
        $styleName = (string)($styleDetail['title'] ?? ucfirst(str_replace('_', ' ', $normalizedStyleKey)));
        $styleCategory = trim((string)($styleDetail['category'] ?? ''));
        $styleDescription = trim((string)($styleDetail['description'] ?? ''));
        $styleMorphology = trim((string)($styleDetail['morphology'] ?? ''));
        $styleEffect = trim((string)($styleDetail['aesthetic_effect'] ?? ''));
        $procedure = trim((string)($options['procedure_label'] ?? $case['procedure_interest'] ?? 'smile design'));
        $procedureMode = function_exists('smile_design_procedure_mode') ? smile_design_procedure_mode($procedure) : 'general';
        $isLipRepositionOnly = $procedureMode === 'lip_repositioning';
        $isDiagnosticPreview = $procedureMode === 'general';
        $isVeneerSimulation = in_array($procedureMode, ['veneers', 'veneers_lip_repositioning'], true);
        $procedureGuidance = function_exists('smile_design_procedure_prompt_guidance') ? smile_design_procedure_prompt_guidance($procedure) : '';
        $shadeDetail = function_exists('smile_design_shade_detail')
            ? smile_design_shade_detail((string)($options['shade_goal'] ?? $case['shade_goal'] ?? ''), $normalizedStyleKey)
            : ['label' => 'Chromascop 210', 'title' => 'Bright White', 'description' => 'Premium bright white.', 'prompt' => 'Chromascop 210 bright white porcelain shade.'];
        $styleAnatomy = function_exists('smile_design_style_generation_guidance') ? smile_design_style_generation_guidance($normalizedStyleKey) : '';
        $customRequest = trim((string)($options['custom_request'] ?? ''));
        $precisionMode = trim((string)($options['precision_mode'] ?? ($options['precision_controls']['precision_mode'] ?? 'balanced')));
        $shapeScaleDelta = (int)($options['shape_scale_delta'] ?? ($options['precision_controls']['shape_scale_delta'] ?? 0));
        $smileLengthDelta = (int)($options['smile_length_delta'] ?? ($options['precision_controls']['smile_length_delta'] ?? 0));
        $smileWidthDelta = (int)($options['smile_width_delta'] ?? ($options['precision_controls']['smile_width_delta'] ?? 0));
        $shadeBrightnessDelta = (int)($options['shade_brightness_delta'] ?? ($options['precision_controls']['shade_brightness_delta'] ?? 0));
        $smileWidthGoal = function_exists('smile_design_normalize_smile_width_goal')
            ? smile_design_normalize_smile_width_goal((string)($options['smile_width_goal'] ?? $case['smile_width_goal'] ?? ''))
            : 'keep_current';
        $selectedTeeth = smile_design_normalize_selected_teeth(trim((string)($options['selected_teeth'] ?? '')));
        $selectedTeethNumbers = json_decode($selectedTeeth, true);
        $selectedTeethNumbers = is_array($selectedTeethNumbers) ? array_map('intval', $selectedTeethNumbers) : [];
        $selectedPosteriorTeeth = array_values(array_intersect($selectedTeethNumbers, [4, 13]));
        $toothOffsets = trim((string)($options['tooth_offsets'] ?? '{}'));
        $toothAdjustments = trim((string)($options['tooth_adjustments'] ?? '{}'));
        $anchorPointsRaw = trim((string)($options['anchor_points'] ?? ($options['precision_controls']['anchor_points'] ?? '')));
        $internalNotes = trim((string)($options['notes'] ?? ''));
        $targetPhotoLabel = trim((string)($options['target_photo_label'] ?? 'Front'));
        $targetPhotoType = trim((string)($options['target_photo_type'] ?? $options['photo_type'] ?? 'front'));
        $cameraMetadataSummary = trim((string)($options['camera_metadata_summary'] ?? ''));
        $includeLower = !empty($options['include_lower_teeth']);
        $analysis = is_array($options['case_analysis'] ?? null) ? $options['case_analysis'] : [];
        $analysisSummary = trim((string)($options['analysis_summary'] ?? ($analysis['summary'] ?? '')));
        $analysisFocus = trim((string)($analysis['recommended_generation_focus'] ?? ''));
        $identityInstructions = trim((string)($analysis['preserve_identity_instructions'] ?? ''));
        $constraints = array_values(array_filter(array_map('trim', (array)($analysis['constraints'] ?? []))));
        $primaryChanges = array_values(array_filter(array_map('trim', (array)($analysis['primary_changes'] ?? []))));
        $riskFlags = array_values(array_filter(array_map('trim', (array)($analysis['risk_flags'] ?? []))));
        $doctorNotes = array_values(array_filter(array_map('trim', (array)($analysis['doctor_review_notes'] ?? []))));
        $recommendedProcedure = trim((string)($analysis['recommended_procedure'] ?? ''));
        $clinicalDirection = trim((string)($analysis['clinical_direction'] ?? ''));
        $previewSuitability = trim((string)($analysis['preview_suitability'] ?? ''));
        $missingTeeth = trim((string)($analysis['missing_or_compromised_teeth'] ?? ''));
        $gingivalDisplay = trim((string)($analysis['gingival_display'] ?? ''));
        $scope = trim((string)($analysis['smile_scope'] ?? ''));
        $referenceTitle = trim((string)($referenceVersion['version_title'] ?? ''));
        $referenceNotes = trim((string)($referenceVersion['notes'] ?? ''));
        $styleReferenceCount = 0;
        if (!$isLipRepositionOnly && function_exists('smile_design_lvi_style_reference_assets')) {
            foreach (smile_design_lvi_style_reference_assets($normalizedStyleKey, 2) as $referenceAsset) {
                $normalized = $this->normalizeForOpenAI((string)$referenceAsset['path']);
                if (empty($normalized['path'])) {
                    continue;
                }

                $imagePaths[] = [
                    'path' => (string)$normalized['path'],
                    'mime_type' => (string)($normalized['mime_type'] ?? (@mime_content_type((string)$normalized['path']) ?: 'application/octet-stream')),
                ];
                if (!empty($normalized['temporary'])) {
                    $tempFiles[] = (string)$normalized['path'];
                }
                $styleReferenceCount++;
            }
        }
        $veneerAngleGuidance = '';
        if ($isVeneerSimulation && $targetPhotoType !== 'front') {
            $veneerAngleGuidance = 'For this angled veneer view, keep the visible laterals and canines in the same porcelain shade family and brightness level as the front view. Do not let side-angle shadow, perspective, or natural tooth warmth make the visible veneers look yellower, greyer, duller, or less finished. The side view must preserve the same premium bright-white impression as the front view, especially across the visible canine-to-canine segment.';
        }
        $widerSmileFrontGuidance = '';
        if ($isVeneerSimulation && $targetPhotoType === 'front' && $smileWidthGoal === 'wider_smile') {
            $widerSmileFrontGuidance = implode(' ', [
                'Front-view wider-smile enforcement:',
                'reduce the black buccal corridor shadows at both mouth corners.',
                'Broaden the canine and premolar veneer presence so the smile fills the visible mouth opening from corner to corner.',
                'Do not leave dark empty spaces between the last visible side teeth and the inner corners of the mouth.',
                'Teeth #4/#5 and #12/#13 should read more present when visible, especially #4 and #13 as the outer smile-width supports.',
                'Do not change the lips, jaw, face width, crop, or camera angle to accomplish this; solve it through believable veneer arch fullness and posterior crown visibility.',
            ]);
        }

        $promptParts = [
            'Create a realistic cosmetic smile design preview from this patient photo for an Elite Smiles consultation.',
            'This is an identity-preserving dental photo edit, not a full portrait makeover.',
            'Preserve the exact same person, face, jawline, cheeks, nose, eyes, eyebrows, skin texture, skin tone, hair, lips, age, expression, camera angle, framing, and lighting.',
            'Do not make the person look younger, slimmer, more glamorous, or like a different patient.',
            $isLipRepositionOnly
                ? 'For lip repositioning only, keep the teeth themselves unchanged and simulate restricted upper-lip elevator movement: the lower border of the upper lip must descend to the cervical line of the upper teeth, covering the exposed gum band, and the lip will appear 5 to 6 mm taller due to the unfolding of the previously curled vermilion.'
                : 'Only change the smile and teeth needed for the requested dental outcome, with minimal gum changes only when required by the smile request.',
            'Target source angle for this generation: ' . $targetPhotoLabel . ' (' . $targetPhotoType . ').',
            'Edit the first image as the ' . $targetPhotoLabel . ' after preview and keep its same angle, pose, crop, and lighting.',
            ($cameraMetadataSummary !== '' ? $cameraMetadataSummary : ''),
            'Composition lock: do not zoom in, zoom out, crop tighter, crop wider, shift the head in frame, or change how much of the face is visible. The before and after should have the same framing and camera distance, with the mouth edited inside the existing composition only.',
            (!$isLipRepositionOnly ? 'Camera metadata, EXIF details, lighting, and composition instructions apply to the photo around the smile only. They must not be interpreted as instructions to preserve the original tooth shape, tooth color, enamel defects, or smile design.' : ''),
            ($isVeneerSimulation ? 'Critical veneer boundary: the face, lips, skin, hair, background, and lighting are locked, but the visible treated teeth are NOT locked. The treated teeth must be visibly redesigned and recolored into the selected LVI veneer outcome.' : ''),
            ($isVeneerSimulation ? 'A mouth-area edit mask is provided. Perform the veneer redesign only inside that editable mouth/smile region. Outside that mask, preserve the source photo as-is.' : ''),
            'Outside the smile zone, treat the image as locked. Forehead, eyes, brows, nose, cheeks, skin pores, hair, ears, jawline, neck, clothing, jewelry, and background must remain visually unchanged from the source photo.',
            'The after must read like the exact same photo with only the smile edited. In a before/after slider or opacity overlay, the face outside the mouth should align and appear unchanged.',
            'Do not retouch, smooth, relight, recolor, beautify, or reshape any non-dental region. Keep the lips unchanged except for the minimal natural contour contact needed around the visible teeth and smile line.',
            ($styleReferenceCount > 0 ? 'The last ' . $styleReferenceCount . ' reference image(s) are LVI ' . $styleName . ' sample smiles. Use them only for tooth morphology, embrasures, incisal step, line angles, canine energy, and smile-width feel. Never copy the reference face, lips, gingiva, crop, or lighting.' : ''),
            $isLipRepositionOnly
                ? 'Improve the visible smile by reducing gummy-smile display through lip repositioning only: make the upper lip appear less retracted and less curled upward, visibly lower, more softly unfolded/full, and with the bottom edge of the superior lip beginning around the arches/cervical contour of the upper teeth; do not apply an LVI tooth style.'
                : ($isDiagnosticPreview ? 'Create a conservative diagnostic smile preview based on visible evidence. Do not over-treat or invent an aggressive irreversible plan.' : 'Improve the visible smile to fit a ' . $styleName . ' style for ' . $procedure . '.'),
            (!$isLipRepositionOnly && $styleCategory !== '' ? 'LVI style category: ' . $styleCategory . '.' : ''),
            (!$isLipRepositionOnly && $styleDescription !== '' ? 'LVI style guidance: ' . $styleDescription : ''),
            (!$isLipRepositionOnly && $styleMorphology !== '' ? 'LVI morphology target: ' . $styleMorphology : ''),
            (!$isLipRepositionOnly && $styleEffect !== '' ? 'Desired aesthetic effect: ' . $styleEffect : ''),
            (!$isLipRepositionOnly && $styleAnatomy !== '' ? 'LVI anatomy blueprint: ' . $styleAnatomy : ''),
            ($isVeneerSimulation ? 'Apply the LVI style as an actual tooth-design change, not just a label. Change the visible treated tooth forms to match the LVI morphology: line angles, incisal edges, embrasures, central/lateral/canine proportions, symmetry, and arch rhythm must all visibly improve compared with the original photo.' : ''),
            ($isVeneerSimulation ? 'Selected veneer shade target: ' . (string)$shadeDetail['prompt'] : ''),
            ($isVeneerSimulation && $shadeScreenContract !== '' ? $shadeScreenContract : ''),
            ($isVeneerSimulation ? 'Shade-over-style rule: Chromascop controls the veneer color value for every LVI style. LVI Natural, Enhanced, Youthful, Hollywood, Vigorous, Mature, and Functional must all honor the selected shade exactly; only tooth anatomy, incisal shape, embrasures, and smile personality should change by style.' : ''),
            ($isVeneerSimulation ? 'When Elite Smiles 100 / Ultra White is selected, every LVI style must render as the whitest and brightest possible custom new-veneer result in this system: maximum-value bleach-white porcelain, glossy ceramic highlights, flawless clean body shade, zero yellow, zero cream warmth, and a dramatic wow factor. When Chromascop 110 is selected, render a clinical Hollywood-white porcelain shade just below Ultra White, still very bright and clearly whiter than the before teeth.' : ''),
            ($isVeneerSimulation && (string)($shadeDetail['code'] ?? '') === '100' ? 'Elite Smiles 100 front-view pass/fail rule: the corrected veneers must be visibly whiter and brighter than the current version being corrected. If the correction would look the same shade or darker, increase the porcelain value instead. Preserve face, lips, and lighting, but do not preserve the old tooth value.' : ''),
            ($isVeneerSimulation ? 'Default veneer material: IPS e.max-style lithium disilicate glass-ceramic. The final teeth should show a glazed ceramic surface with realistic specular highlights, smooth polished body, enamel-like optical depth, and delicate incisal translucency, not flat digital paint or generic whitening.' : ''),
            ($isVeneerSimulation ? 'For veneers, completely replace the visible anterior tooth surfaces with porcelain in the selected shade. Do not allow the original yellowing, original tooth color, dark fissures, cracks, stains, asymmetry, uneven incisal edges, or uneven enamel color to remain visible in the final result.' : ''),
            ($isVeneerSimulation ? 'Before-vs-after mandate: compared with the original photo, the after must have a clearly improved smile silhouette, cleaner tooth proportions, smoother incisal architecture, more even spacing, and a clearly whiter porcelain shade. If the tooth shape or shade looks almost the same as the before, the edit has failed.' : ''),
            ($isVeneerSimulation ? 'The result must read as polished porcelain veneers with consistent shade, clean value, natural incisal translucency, and realistic surface gloss. It must not look like simple whitening on the natural teeth.' : ''),
            ($isVeneerSimulation ? 'Visible veneer surfaces must be pristine and flawless: zero yellow pigmentation, zero brown undertone, zero white spots, zero stain halos, zero craze lines, zero cracks, zero mottling, and zero enamel blemishes. If any visible veneer area reads yellow, stained, or imperfect, the edit is wrong.' : ''),
            ($isVeneerSimulation ? 'Make the veneers look like newly delivered premium porcelain: perfectly clean value, consistent body shade, smooth finish, and no natural-tooth defects showing through anywhere on the visible veneer surfaces.' : ''),
            ($isVeneerSimulation ? 'Bias veneer previews toward premium cosmetic brightness rather than conservative blending. Natural and Enhanced styles should still read as clearly white veneers on screen, just with different shape language than Hollywood.' : ''),
            ($isVeneerSimulation ? 'The brightness increase must be obvious on screen and in the Zoom view. If the after only looks a little cleaner than the before, it is not enough. Make the veneers visibly whiter and more luminous while still dimensional and natural-looking.' : ''),
            ($isVeneerSimulation ? 'If the model must choose between slightly too natural and slightly too white, choose the whiter result as long as it still looks like dimensional porcelain rather than flat paint.' : ''),
            ($widerSmileFrontGuidance !== '' ? $widerSmileFrontGuidance : ''),
            ($veneerAngleGuidance !== '' ? $veneerAngleGuidance : ''),
            ($procedureGuidance !== '' ? $procedureGuidance : ''),
            'Procedure realism rules are binding: stay inside the selected treatment scope, do not add unsupported procedures, and do not create a fantasy smile that could not plausibly be treated from this case.',
            ($recommendedProcedure !== '' ? 'Case analysis recommended procedure: ' . $recommendedProcedure . '.' : ''),
            ($clinicalDirection !== '' ? 'Clinical direction from case analysis: ' . $clinicalDirection . '.' : ''),
            ($previewSuitability !== '' ? 'Preview suitability from case analysis: ' . $previewSuitability . '.' : ''),
            ($scope !== '' ? 'Recommended smile scope from case analysis: ' . $scope . '.' : ''),
            ($analysisSummary !== '' ? 'Case analysis summary: ' . $analysisSummary : ''),
            ($analysisFocus !== '' ? 'Generation focus: ' . $analysisFocus : ''),
            ($missingTeeth !== '' ? 'Missing or compromised teeth notes: ' . $missingTeeth . '.' : ''),
            ($gingivalDisplay !== '' ? 'Gingival display notes: ' . $gingivalDisplay . '.' : ''),
            ($identityInstructions !== '' ? 'Identity lock: ' . $identityInstructions : ''),
            ($doctorNotes !== [] ? 'Doctor review notes from case analysis: ' . implode('; ', $doctorNotes) . '.' : ''),
            ($internalNotes !== '' ? 'Internal generation notes: ' . $internalNotes . '.' : ''),
            $includeLower
                ? 'If lower teeth are naturally visible, refine them consistently with the smile design.'
                : 'Focus on the upper visible teeth unless the lower teeth are naturally prominent in the source photo.',
            'Keep the hairstyle, facial contours, skin, and all non-dental features untouched.',
            'Do not fabricate perfect model features, fake veneers on unrelated teeth, or dramatic beauty edits outside the requested smile treatment.',
            'Do not add text, labels, arrows, borders, split-screen layouts, or watermarks into the generated image.',
            'This should look like a tasteful consultation preview, not an exaggerated fantasy makeover.',
        ];
        if ($referenceVersion) {
            $promptParts[] = 'One of the reference images is the current smile preview version. Keep that same overall dental direction and only adjust the smile details requested below.';
            if ($referenceTitle !== '') {
                $promptParts[] = 'Current preview version reference: ' . $referenceTitle . '.';
            }
            if ($referenceNotes !== '') {
                $promptParts[] = 'Current preview notes: ' . $referenceNotes . '.';
            }
        }
        if ($primaryChanges !== []) {
            $promptParts[] = 'Primary requested dental changes: ' . implode('; ', $primaryChanges) . '.';
        }
        if ($constraints !== []) {
            $promptParts[] = 'Hard constraints: ' . implode('; ', $constraints) . '.';
        }
        if ($riskFlags !== []) {
            $promptParts[] = 'Be careful about these risks: ' . implode('; ', $riskFlags) . '.';
        }
        if ($customRequest !== '') {
            $promptParts[] = 'Additional design request: ' . $customRequest . '.';
        }
        if ($precisionMode !== 'balanced') {
            $promptParts[] = 'Precision mode: ' . $precisionMode . '.';
        }
        if ($shapeScaleDelta !== 0) {
            $promptParts[] = 'Priority shape-scale adjustment: shift visible incisal morphology by about ' . $shapeScaleDelta . '% while preserving clinical anatomy.';
        }
        if ($smileLengthDelta !== 0) {
            $promptParts[] = 'Priority smile-length adjustment: alter vertical tooth/gingival proportion by about ' . $smileLengthDelta . '% where clinically plausible.';
        }
        if ($smileWidthDelta !== 0) {
            $promptParts[] = 'Priority smile-width adjustment: adjust visible smile breadth by about ' . $smileWidthDelta . '% while keeping face alignment and composition stable.';
            if ($smileWidthDelta > 0 && $selectedPosteriorTeeth !== []) {
                $promptParts[] = 'Posterior expansion priority: increase the visible facial display of tooth ' . implode(' and tooth ', $selectedPosteriorTeeth) . ' toward the corners of the smile. Make these posterior veneers visibly broader in the dental arch, reduce the dark buccal corridors on both sides, and create a wider full-arch smile. Preserve teeth #8 and #9, the lip position, face, head size, and camera framing.';
            }
        }
        if ($shadeBrightnessDelta !== 0) {
            $promptParts[] = 'Shade/brightness adjustment: increase or reduce veneer brightness by about ' . $shadeBrightnessDelta . ' points while preserving porcelain texture and incisal translucency.';
        }
        if ($toothOffsets !== '' && $toothOffsets !== '{}') {
            $promptParts[] = 'Manual selected-tooth target displacement in image percentage points (positive x is right, positive y is down): ' . $toothOffsets . '. Move only the corresponding selected teeth toward these target positions.';
        }
        if ($toothAdjustments !== '' && $toothAdjustments !== '{}') {
            $promptParts[] = 'Per-tooth precision adjustments: ' . $toothAdjustments . '. Apply each shape, length, and width delta only to its numbered tooth; edge_smoothing describes the intended clean mask boundary and is not a request to blur the tooth. Do not copy one tooth adjustment across the rest of the smile.';
        }
        if ($anchorPointsRaw !== '') {
            $promptParts[] = 'Anchor guidance: use the provided edit anchors only for local constraint and deformation distribution, without changing unrelated non-dental regions. ' . $anchorPointsRaw . '.';
        }

        if ($isLipRepositionOnly) {
            $promptParts = [smile_design_lip_repositioning_surgical_prompt([
                'procedure' => $procedure,
                'target_photo_label' => $targetPhotoLabel,
                'target_photo_type' => $targetPhotoType,
                'custom_request' => $customRequest,
                'analysis_summary' => $analysisSummary,
                'analysis_focus' => $analysisFocus,
                'clinical_direction' => $clinicalDirection,
                'gingival_display' => $gingivalDisplay,
                'identity_instructions' => $identityInstructions,
                'constraints' => $constraints,
                'risk_flags' => $riskFlags,
                'doctor_notes' => $doctorNotes,
                'internal_notes' => $internalNotes,
                'reference_title' => $referenceTitle,
                'reference_notes' => $referenceNotes,
                'qa_feedback' => $options['lip_qa_feedback'] ?? '',
                'is_retry' => !empty($options['lip_surgical_retry']),
            ])];
        }

        $prompt = implode(' ', $promptParts);
        $editSize = $this->imageEditSizeForSource($primarySourcePath);
        $maskPath = $isVeneerSimulation && $primarySourcePath !== ''
            ? $this->createSmileEditMask($primarySourcePath, $targetPhotoType, $anchorPointsRaw, $contourPointsRaw)
            : '';
        try {
            $imageResult = elite_openai_image_edit($imagePaths, $prompt, [
                'model' => 'gpt-image-1.5',
                'size' => $editSize,
                'quality' => 'high',
                'output_format' => 'png',
                'background' => 'auto',
                'input_fidelity' => 'high',
                'mask_path' => $maskPath,
            ]);

            if (empty($imageResult['ok'])) {
                $imageResult = elite_openai_image_edit($imagePaths, $prompt, [
                    'model' => 'gpt-image-1',
                    'size' => $editSize,
                    'quality' => 'high',
                    'output_format' => 'png',
                    'background' => 'auto',
                    'input_fidelity' => 'high',
                    'mask_path' => $maskPath,
                ]);
            }
        } finally {
            if ($maskPath !== '' && is_file($maskPath)) {
                @unlink($maskPath);
            }
            foreach ($tempFiles as $tempPath) {
                if (is_file($tempPath)) {
                    @unlink($tempPath);
                }
            }
        }

        if (empty($imageResult['ok'])) {
            return [
                'ok' => false,
                'provider' => 'openai',
                'message' => (string)($imageResult['message'] ?? 'OpenAI image generation failed.'),
                'request' => ['prompt' => $prompt],
                'response' => $imageResult['response'] ?? null,
            ];
        }

        return [
            'ok' => true,
            'provider' => 'openai',
            'prompt_summary' => $isLipRepositionOnly ? ($customRequest !== '' ? $customRequest : 'Surgical lip repositioning preview') : ($customRequest !== '' ? $customRequest : ($styleName . ' ' . $procedure . ' preview')),
            'request' => [
                'prompt' => $prompt,
                'options' => $imageResult['request'] ?? [],
            ],
            'response' => $imageResult['response'] ?? null,
            'image_base64' => (string)$imageResult['image_base64'],
            'mime_type' => (string)($imageResult['mime_type'] ?? 'image/png'),
            'revised_prompt' => (string)($imageResult['revised_prompt'] ?? ''),
        ];
    }

    private function imageEditSizeForSource(string $path): string
    {
        $info = $path !== '' && is_file($path) ? @getimagesize($path) : false;
        $width = is_array($info) ? (int)($info[0] ?? 0) : 0;
        $height = is_array($info) ? (int)($info[1] ?? 0) : 0;
        if ($width <= 0 || $height <= 0) {
            return 'auto';
        }

        $ratio = $width / $height;
        if ($ratio > 1.2) {
            return '1536x1024';
        }
        if ($ratio < 0.84) {
            return '1024x1536';
        }
        return '1024x1024';
    }

    private function createSmileEditMask(string $path, string $photoType, string $anchorPointsRaw = '', string $contourPointsRaw = ''): string
    {
        if (!extension_loaded('gd') || !is_file($path)) {
            return '';
        }

        $info = @getimagesize($path);
        $width = is_array($info) ? (int)($info[0] ?? 0) : 0;
        $height = is_array($info) ? (int)($info[1] ?? 0) : 0;
        if ($width <= 0 || $height <= 0) {
            return '';
        }

        $mask = imagecreatetruecolor($width, $height);
        imagealphablending($mask, false);
        imagesavealpha($mask, true);
        $locked = imagecolorallocatealpha($mask, 255, 255, 255, 0);
        $editable = imagecolorallocatealpha($mask, 255, 255, 255, 127);
        imagefilledrectangle($mask, 0, 0, $width, $height, $locked);

        $contourPoints = $this->parseSmileContourPoints($contourPointsRaw);
        $polygonPoints = $this->parseSmileAnchorPoints($anchorPointsRaw);
        if ($contourPoints !== [] || $polygonPoints !== []) {
            $maskPolygonPoints = $contourPoints !== [] ? $contourPoints : $this->buildSmileMaskPolygonFromAnchors($polygonPoints);
            $drawablePoints = $maskPolygonPoints !== [] ? $maskPolygonPoints : $polygonPoints;
            $gdPoints = [];
            $minX = $width;
            $maxX = 0;
            $minY = $height;
            $maxY = 0;
            foreach ($drawablePoints as $point) {
                $x = (int)round(($point['x'] / 100) * $width);
                $y = (int)round(($point['y'] / 100) * $height);
                $gdPoints[] = $x;
                $gdPoints[] = $y;
                $minX = min($minX, $x);
                $maxX = max($maxX, $x);
                $minY = min($minY, $y);
                $maxY = max($maxY, $y);
            }
            if (count($gdPoints) >= 6) {
                imagefilledpolygon($mask, $gdPoints, count($gdPoints) / 2, $editable);
                $strokeThickness = max(6, (int)round(min($width, $height) * 0.008));
                imagesetthickness($mask, $strokeThickness);
                $pointCount = count($gdPoints) / 2;
                for ($index = 0; $index < $pointCount; $index++) {
                    $nextIndex = ($index + 1) % $pointCount;
                    $x1 = $gdPoints[$index * 2];
                    $y1 = $gdPoints[($index * 2) + 1];
                    $x2 = $gdPoints[$nextIndex * 2];
                    $y2 = $gdPoints[($nextIndex * 2) + 1];
                    imageline($mask, $x1, $y1, $x2, $y2, $editable);
                    imagefilledellipse($mask, $x1, $y1, $strokeThickness, $strokeThickness, $editable);
                }
            }
        } else {
            $type = strtolower(trim($photoType));
            $bounds = match ($type) {
                'left_45', 'left45' => [0.16, 0.43, 0.74, 0.78],
                'right_45', 'right45' => [0.26, 0.43, 0.84, 0.78],
                default => [0.18, 0.43, 0.82, 0.78],
            };

            $x1 = (int)round($width * $bounds[0]);
            $y1 = (int)round($height * $bounds[1]);
            $x2 = (int)round($width * $bounds[2]);
            $y2 = (int)round($height * $bounds[3]);
            $centerX = (int)round(($x1 + $x2) / 2);
            $centerY = (int)round(($y1 + $y2) / 2);
            $ellipseW = max(40, $x2 - $x1);
            $ellipseH = max(30, $y2 - $y1);
            imagefilledellipse($mask, $centerX, $centerY, $ellipseW, $ellipseH, $editable);
        }

        $maskPath = tempnam(sys_get_temp_dir(), 'esm-smile-mask-');
        if ($maskPath === false) {
            imagedestroy($mask);
            return '';
        }
        $pngPath = $maskPath . '.png';
        @unlink($maskPath);
        imagepng($mask, $pngPath, 6);
        imagedestroy($mask);

        return is_file($pngPath) ? $pngPath : '';
    }

    /**
     * @return array<int, array{key: string, x: float, y: float}>
     */
    private function parseSmileAnchorPoints(string $anchorPointsRaw): array
    {
        if ($anchorPointsRaw === '') {
            return [];
        }

        $decoded = json_decode($anchorPointsRaw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $points = [];
        foreach ($decoded as $point) {
            if (!is_array($point)) {
                continue;
            }
            $x = isset($point['x']) ? (float)$point['x'] : (isset($point['px']) ? (float)$point['px'] : null);
            $y = isset($point['y']) ? (float)$point['y'] : (isset($point['py']) ? (float)$point['py'] : null);
            if ($x === null || $y === null) {
                continue;
            }
            $points[] = [
                'key' => (string)($point['key'] ?? ''),
                'x' => max(0.0, min(100.0, $x)),
                'y' => max(0.0, min(100.0, $y)),
            ];
        }

        return count($points) >= 3 ? $points : [];
    }

    /**
     * @return array<int, array{x: float, y: float}>
     */
    private function parseSmileContourPoints(string $contourPointsRaw): array
    {
        if ($contourPointsRaw === '') {
            return [];
        }

        $decoded = json_decode($contourPointsRaw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $points = [];
        foreach ($decoded as $point) {
            if (!is_array($point)) {
                continue;
            }
            $x = isset($point['x']) ? (float)$point['x'] : null;
            $y = isset($point['y']) ? (float)$point['y'] : null;
            if ($x === null || $y === null) {
                continue;
            }
            $points[] = [
                'x' => max(0.0, min(100.0, $x)),
                'y' => max(0.0, min(100.0, $y)),
            ];
        }

        return count($points) >= 3 ? $points : [];
    }

    /**
     * @param array<int, array{key: string, x: float, y: float}> $points
     * @return array<int, array{x: float, y: float}>
     */
    private function buildSmileMaskPolygonFromAnchors(array $points): array
    {
        $keyed = [];
        foreach ($points as $point) {
            $key = trim((string)($point['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $keyed[$key] = $point;
        }

        $required = ['upper_left', 'upper_center', 'upper_right', 'right_inner', 'lower_right', 'lower_center', 'lower_left', 'left_inner'];
        foreach ($required as $requiredKey) {
            if (!isset($keyed[$requiredKey])) {
                return [];
            }
        }

        return [
            $keyed['left_inner'],
            [
                'x' => $keyed['left_inner']['x'] + (($keyed['upper_left']['x'] - $keyed['left_inner']['x']) * 0.55),
                'y' => $keyed['left_inner']['y'] + (($keyed['upper_left']['y'] - $keyed['left_inner']['y']) * 0.55),
            ],
            $keyed['upper_left'],
            [
                'x' => $keyed['upper_left']['x'] + (($keyed['upper_center']['x'] - $keyed['upper_left']['x']) * 0.5),
                'y' => $keyed['upper_left']['y'] + (($keyed['upper_center']['y'] - $keyed['upper_left']['y']) * 0.5),
            ],
            $keyed['upper_center'],
            [
                'x' => $keyed['upper_center']['x'] + (($keyed['upper_right']['x'] - $keyed['upper_center']['x']) * 0.5),
                'y' => $keyed['upper_center']['y'] + (($keyed['upper_right']['y'] - $keyed['upper_center']['y']) * 0.5),
            ],
            $keyed['upper_right'],
            [
                'x' => $keyed['upper_right']['x'] + (($keyed['right_inner']['x'] - $keyed['upper_right']['x']) * 0.55),
                'y' => $keyed['upper_right']['y'] + (($keyed['right_inner']['y'] - $keyed['upper_right']['y']) * 0.55),
            ],
            $keyed['right_inner'],
            $keyed['lower_right'],
            $keyed['lower_center'],
            $keyed['lower_left'],
        ];
    }

    private function normalizeForOpenAI(string $path): array
    {
        if (!extension_loaded('gd')) {
            return ['path' => $path, 'temporary' => false, 'mime_type' => function_exists('elite_openai_detect_image_mime_type') ? elite_openai_detect_image_mime_type($path) : (string)(@mime_content_type($path) ?: 'application/octet-stream')];
        }

        $bytes = @file_get_contents($path);
        if (!is_string($bytes) || $bytes === '') {
            return ['path' => $path, 'temporary' => false, 'mime_type' => function_exists('elite_openai_detect_image_mime_type') ? elite_openai_detect_image_mime_type($path) : (string)(@mime_content_type($path) ?: 'application/octet-stream')];
        }

        $image = @imagecreatefromstring($bytes);
        if (!$image) {
            return ['path' => $path, 'temporary' => false, 'mime_type' => function_exists('elite_openai_detect_image_mime_type') ? elite_openai_detect_image_mime_type($path) : (string)(@mime_content_type($path) ?: 'application/octet-stream')];
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'esm-smile-');
        if ($tempPath === false) {
            imagedestroy($image);
            return ['path' => $path, 'temporary' => false, 'mime_type' => function_exists('elite_openai_detect_image_mime_type') ? elite_openai_detect_image_mime_type($path) : (string)(@mime_content_type($path) ?: 'application/octet-stream')];
        }

        $pngPath = $tempPath . '.png';
        @unlink($tempPath);
        if (function_exists('imagepalettetotruecolor')) {
            @imagepalettetotruecolor($image);
        }
        imagealphablending($image, true);
        imagesavealpha($image, true);
        imagepng($image, $pngPath, 6);
        imagedestroy($image);

        return is_file($pngPath)
            ? ['path' => $pngPath, 'temporary' => true, 'mime_type' => 'image/png']
            : ['path' => $path, 'temporary' => false, 'mime_type' => function_exists('elite_openai_detect_image_mime_type') ? elite_openai_detect_image_mime_type($path) : (string)(@mime_content_type($path) ?: 'application/octet-stream')];
    }
}

interface SmileDesignNotificationProvider
{
    public function send(array $recipient, string $templateKey, array $context = []): array;
}

final class MockSmileDesignNotificationProvider implements SmileDesignNotificationProvider
{
    public function send(array $recipient, string $templateKey, array $context = []): array
    {
        return [
            'ok' => true,
            'provider' => 'mock',
            'message' => 'Notification placeholder recorded. No external call was made.',
            'template_key' => $templateKey,
        ];
    }
}

final class EmailSmileDesignNotificationProvider implements SmileDesignNotificationProvider
{
    public function send(array $recipient, string $templateKey, array $context = []): array
    {
        return [
            'ok' => false,
            'provider' => 'email',
            'message' => 'Email provider placeholder only. No external call was made.',
        ];
    }
}

final class TwilioSmsSmileDesignNotificationProvider implements SmileDesignNotificationProvider
{
    public function send(array $recipient, string $templateKey, array $context = []): array
    {
        return [
            'ok' => false,
            'provider' => 'twilio_sms',
            'message' => 'Twilio SMS provider placeholder only. No external call was made.',
        ];
    }
}
