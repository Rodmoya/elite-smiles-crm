<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/social_studio/social_studio_service.php';

require_auth();
require_csrf();

$focus = (string)post('focus', 'veneers');
$count = (int)post('count', 7);
$instruction = (string)post('instruction', '');
$visualReferenceKey = (string)post('visual_reference', 'none');
$creationMode = (string)post('creation_mode', 'remix');
$visualReferences = social_studio_visual_references();
$visualReference = $visualReferences[$visualReferenceKey] ?? null;
if ($creationMode !== 'manual' && (!$visualReference || !str_starts_with($visualReferenceKey, 'base_'))) {
    flash_set('error', 'Select an Instagram base post before generating a remix.');
    redirect(base_url('social-studio.php'));
}
$baseAnalysis = null;
if (str_starts_with($visualReferenceKey, 'base_')) {
    $baseId = (int)substr($visualReferenceKey, 5);
    $baseAnalysis = $baseId > 0 ? db_one('SELECT title, source_url, source_post_id, group_name, analysis_json, base_prompt, overlay_spec FROM social_studio_base_creatives WHERE id = :id AND status = "active" LIMIT 1', ['id' => $baseId]) : null;
    if ($creationMode !== 'manual' && !$baseAnalysis) {
        flash_set('error', 'That Instagram base post is no longer available.');
        redirect(base_url('social-studio.php'));
    }
}
$uploadedInspirationDataUrl = '';
if (!empty($_FILES['inspiration_image']['tmp_name']) && is_uploaded_file($_FILES['inspiration_image']['tmp_name'])) {
    $mime = (string)($_FILES['inspiration_image']['type'] ?? '');
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (isset($allowed[$mime]) && (int)($_FILES['inspiration_image']['size'] ?? 0) <= 8 * 1024 * 1024) {
        $uploadedInspirationDataUrl = 'data:' . $mime . ';base64,' . base64_encode((string)file_get_contents($_FILES['inspiration_image']['tmp_name']));
    }
}
$brief = implode("\n", [
    'Creation mode: ' . ($creationMode === 'manual' ? 'Manual brief' : 'Remix selected post'),
    'Purpose: ' . ((string)post('purpose', 'educational') === 'social_ad' ? 'Social media ad' : 'Educational'),
    'Focus: ' . $focus,
    'Audience: ' . (string)post('audience', 'any'),
    'Age range: ' . (string)post('age_range', 'any'),
    'Text position: ' . (string)post('text_position', 'left'),
    'Instagram base post: ' . ($visualReference['label'] ?? 'Manual brief'),
    'Instagram reference window: March 16, 2026 through today only.',
    $creationMode === 'manual'
        ? 'Manual mode: treat the user instruction as the primary creative direction and use the Master CMO system for quality, compliance, and consistency.'
        : 'LOCKED REMIX MODE: the selected Instagram ad is the immutable template. Preserve its composition, crop, subject scale, negative space, palette, typography families, font scale, line breaks, content-block count, hierarchy, benefit format, CTA treatment, and overall visual rhythm. The ONLY substitutions allowed are Focus, Purpose, Audience, Age range, and Text position. Rewrite treatment-specific words only where required by those five substitutions. Do not invent a new layout, font system, CTA style, or content structure.',
    'Reference style direction: ' . ($visualReference['description'] ?? 'Use the Elite Smiles Master CMO system.'),
    'Reference use rule: study typography scale, spacing, composition, palette, subject framing, and CTA treatment only; create an original asset and never copy the source image or bake text/logo into the generated image.',
]);
if ($baseAnalysis) {
    $instruction .= "\nBASE POST ANALYSIS (source of truth):\n" . (string)$baseAnalysis['analysis_json'];
    $instruction .= "\nBASE POST PROMPT:\n" . (string)$baseAnalysis['base_prompt'];
    $instruction .= "\nBASE OVERLAY SPEC:\n" . (string)$baseAnalysis['overlay_spec'];
}
$instruction = $brief . "\n" . $instruction;
$remixTemplate = $baseAnalysis ? [
    'reference_key' => $visualReferenceKey,
    'title' => (string)$baseAnalysis['title'],
    'source_post_id' => (string)$baseAnalysis['source_post_id'],
    'analysis_json' => (string)$baseAnalysis['analysis_json'],
    'base_prompt' => (string)$baseAnalysis['base_prompt'],
    'overlay_spec' => (string)$baseAnalysis['overlay_spec'],
    'focus' => $focus,
    'purpose' => (string)post('purpose', 'educational'),
    'audience' => (string)post('audience', 'any'),
    'age_range' => (string)post('age_range', 'any'),
    'text_position' => (string)post('text_position', 'left'),
    'replica_mode' => $creationMode === 'replica',
] : [];
$created = social_studio_seed_drafts($focus, $count, (int)(auth_user_id() ?: 0), $instruction, $uploadedInspirationDataUrl, $remixTemplate);

flash_set('success', 'Created ' . $created . ' social draft' . ($created === 1 ? '' : 's') . ' for review.');
redirect(base_url('social-studio.php'));
