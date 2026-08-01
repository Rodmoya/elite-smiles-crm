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
$visualReferences = social_studio_visual_references();
$visualReference = $visualReferences[$visualReferenceKey] ?? $visualReferences['none'];
$uploadedInspirationDataUrl = '';
if (!empty($_FILES['inspiration_image']['tmp_name']) && is_uploaded_file($_FILES['inspiration_image']['tmp_name'])) {
    $mime = (string)($_FILES['inspiration_image']['type'] ?? '');
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (isset($allowed[$mime]) && (int)($_FILES['inspiration_image']['size'] ?? 0) <= 8 * 1024 * 1024) {
        $uploadedInspirationDataUrl = 'data:' . $mime . ';base64,' . base64_encode((string)file_get_contents($_FILES['inspiration_image']['tmp_name']));
    }
}
$brief = implode("\n", [
    'Purpose: ' . ((string)post('purpose', 'educational') === 'social_ad' ? 'Social media ad' : 'Educational'),
    'Creative angle: ' . (string)post('angle', 'benefits'),
    'Audience: ' . (string)post('audience', 'any') . ', age ' . (string)post('age_range', 'any'),
    'Utah setting: ' . (string)post('location_style', 'draper'),
    'Financing: ' . ((string)post('financing', 'exclude') === 'include_0' ? 'Mention 0% financing for qualified patients' : 'Do not mention financing'),
    'Editable overlay logo: ' . (!empty($_POST['include_logo']) ? 'include logo' : 'no logo'),
    'Text position: ' . (string)post('text_position', 'left'),
    'Creative angle group: ' . ($visualReference['group'] ?? 'Other'),
    'Instagram visual inspiration: ' . $visualReference['label'],
    'Instagram reference window: March 16, 2026 through today only.',
    'Reference style direction: ' . $visualReference['description'],
    'Reference use rule: study typography scale, spacing, composition, palette, subject framing, and CTA treatment only; create an original asset and never copy the source image or bake text/logo into the generated image.',
]);
$instruction = $brief . "\n" . $instruction;
$created = social_studio_seed_drafts($focus, $count, (int)(auth_user_id() ?: 0), $instruction, $uploadedInspirationDataUrl);

flash_set('success', 'Created ' . $created . ' social draft' . ($created === 1 ? '' : 's') . ' for review.');
redirect(base_url('social-studio.php'));
