<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/social_studio/social_studio_service.php';

require_auth();
require_csrf();

$instruction = trim((string)post('creative_request', ''));
$count = max(1, min(7, (int)post('count', 1)));
$controls = [
    'focus' => (string)post('focus', 'auto'),
    'purpose' => (string)post('purpose', 'auto'),
    'audience' => (string)post('audience', 'auto'),
    'age_range' => (string)post('age_range', 'auto'),
    'text_position' => (string)post('text_position', 'auto'),
];
$inspirationDataUrl = '';
if (!empty($_FILES['inspiration_image']['tmp_name']) && is_uploaded_file($_FILES['inspiration_image']['tmp_name'])) {
    $mime = (string)($_FILES['inspiration_image']['type'] ?? '');
    if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) && (int)($_FILES['inspiration_image']['size'] ?? 0) <= 8 * 1024 * 1024) {
        $inspirationDataUrl = 'data:' . $mime . ';base64,' . base64_encode((string)file_get_contents($_FILES['inspiration_image']['tmp_name']));
    }
}
try {
    $ids = [];
    $created = social_studio_create_original_drafts($instruction, $controls, $count, (int)(auth_user_id() ?: 0), $inspirationDataUrl, $ids);
    flash_set('success', 'Created ' . $created . ' original social draft' . ($created === 1 ? '' : 's') . ' inside the Elite Smiles editorial line.');
    flash_set('social_auto_generate_ids', implode(',', $ids));
    $query = 'view=create&mode=original' . ($ids !== [] ? '&draft=' . (int)$ids[0] : '');
    redirect(base_url('social-studio.php?' . $query));
} catch (Throwable $error) {
    flash_set('error', $error->getMessage());
    redirect(base_url('social-studio.php?view=create&mode=original'));
}
