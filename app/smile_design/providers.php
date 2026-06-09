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
            'The lower border of the UPPER lip must descend to the cervical line / gingival-zenith level of the upper teeth — the point where the gum meets the tooth tops — or slightly past it, so the exposed gum band is fully covered. Err on the side of slightly lower rather than not low enough.',
            $targetPhotoType === 'front'
                ? 'FRONT VIEW: the lower edge of the upper lip must land squarely at or just past the gingival zeniths of the upper central and lateral incisors and canines across the full visible arch width. The entire gum band above the front teeth must be covered — left side, center, and right side equally.'
                : 'ANGLED VIEW (' . $targetPhotoLabel . '): from this angle the upper lip curves away from camera. The near-side corner of the upper lip AND the far-side lateral portion must both descend — the entire visible upper-lip edge must lower uniformly along the visible tooth arch. Do not only lower the center or near side while leaving the far-side gum band exposed. Trace the curve of the upper arch from the near canine to the far canine and drape the upper lip along that full curve.',
            'The upper lip should look less curled upward and less retracted — the previously tightly curled vermilion unrolls downward, making the upper lip appear 6 to 7 mm taller (longer vertically) in the smile. This is a HEIGHT/POSITION change only, not a volume change. The upper lip is the same thickness — it just covers more vertical distance because the curl is gone.',
            'Do NOT inflate, plump, swell, or add volume to any lip. The upper lip change is purely positional — it unrolls downward, which makes it look taller, not thicker.',
            'Make the repositioned upper lip softly draped and seamless against the gum/tooth line. It must not look swollen, injected, pasted on, or stretched.',
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
            'Before finalizing: check that (1) the UPPER lip is visibly lower and covers the gum band, (2) the LOWER lip is completely unchanged from the source photo, (3) teeth are unchanged. If the lower lip looks different at all, redo the edit.',
        ], static fn(string $value): bool => trim($value) !== '')));
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
        $referenceVersion = is_array($options['reference_after_version'] ?? null) ? $options['reference_after_version'] : null;
        foreach ($photos as $photo) {
            $storageKey = (string)($photo['storage_key'] ?? '');
            if ($storageKey === '') {
                continue;
            }
            $resolved = smile_design_safe_storage_path($storageKey);
            if ($resolved && is_file($resolved)) {
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
        $procedure = trim((string)($options['procedure_label'] ?? $case['procedure_interest'] ?? 'smile design'));
        $procedureMode = function_exists('smile_design_procedure_mode') ? smile_design_procedure_mode($procedure) : 'general';
        $isLipRepositionOnly = $procedureMode === 'lip_repositioning';
        $isDiagnosticPreview = $procedureMode === 'general';
        $procedureGuidance = function_exists('smile_design_procedure_prompt_guidance') ? smile_design_procedure_prompt_guidance($procedure) : '';
        $customRequest = trim((string)($options['custom_request'] ?? ''));
        $internalNotes = trim((string)($options['notes'] ?? ''));
        $targetPhotoLabel = trim((string)($options['target_photo_label'] ?? 'Front'));
        $targetPhotoType = trim((string)($options['target_photo_type'] ?? $options['photo_type'] ?? 'front'));
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
            $isLipRepositionOnly ? 'No LVI tooth style applies because this is Lip Repositioning only.' : 'Target smile style: ' . $styleName . '.',
            (!$isLipRepositionOnly && $styleCategory !== '' ? 'LVI style category: ' . $styleCategory . '.' : ''),
            (!$isLipRepositionOnly && $styleDescription !== '' ? 'LVI style guidance: ' . $styleDescription : ''),
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
            'Do not apply AI beauty retouching, skin smoothing, or noise reduction to any area. Outside the edited dental zone, every pixel must stay exactly as it is in the source photo.',
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
        $result = elite_gemini_generate_image_edit($imagePaths, $prompt, [
            'model' => GOOGLE_GEMINI_IMAGE_MODEL,
        ]);

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
            'revised_prompt' => '',
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
        $tempFiles = [];
        $referenceVersion = is_array($options['reference_after_version'] ?? null) ? $options['reference_after_version'] : null;
        foreach ($photos as $photo) {
            $storageKey = (string)($photo['storage_key'] ?? '');
            if ($storageKey === '') {
                continue;
            }
            $resolved = smile_design_safe_storage_path($storageKey);
            if ($resolved && is_file($resolved)) {
                $normalized = $this->normalizeForOpenAI($resolved);
                if (!empty($normalized['path'])) {
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
        $procedure = trim((string)($options['procedure_label'] ?? $case['procedure_interest'] ?? 'smile design'));
        $procedureMode = function_exists('smile_design_procedure_mode') ? smile_design_procedure_mode($procedure) : 'general';
        $isLipRepositionOnly = $procedureMode === 'lip_repositioning';
        $isDiagnosticPreview = $procedureMode === 'general';
        $procedureGuidance = function_exists('smile_design_procedure_prompt_guidance') ? smile_design_procedure_prompt_guidance($procedure) : '';
        $customRequest = trim((string)($options['custom_request'] ?? ''));
        $internalNotes = trim((string)($options['notes'] ?? ''));
        $targetPhotoLabel = trim((string)($options['target_photo_label'] ?? 'Front'));
        $targetPhotoType = trim((string)($options['target_photo_type'] ?? $options['photo_type'] ?? 'front'));
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
            $isLipRepositionOnly
                ? 'Improve the visible smile by reducing gummy-smile display through lip repositioning only: make the upper lip appear less retracted and less curled upward, visibly lower, more softly unfolded/full, and with the bottom edge of the superior lip beginning around the arches/cervical contour of the upper teeth; do not apply an LVI tooth style.'
                : ($isDiagnosticPreview ? 'Create a conservative diagnostic smile preview based on visible evidence. Do not over-treat or invent an aggressive irreversible plan.' : 'Improve the visible smile to fit a ' . $styleName . ' style for ' . $procedure . '.'),
            (!$isLipRepositionOnly && $styleCategory !== '' ? 'LVI style category: ' . $styleCategory . '.' : ''),
            (!$isLipRepositionOnly && $styleDescription !== '' ? 'LVI style guidance: ' . $styleDescription : ''),
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
            'Do not apply AI beauty retouching, skin smoothing, or noise reduction anywhere. Outside the edited dental zone, every pixel must stay exactly as it is in the source photo.',
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
        try {
        $imageResult = elite_openai_image_edit($imagePaths, $prompt, [
                'model' => 'gpt-image-1',
                'size' => '1024x1536',
                'quality' => 'medium',
                'output_format' => 'png',
                'background' => 'auto',
            ]);
        } finally {
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
