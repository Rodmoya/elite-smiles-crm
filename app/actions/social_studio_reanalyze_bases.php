<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/social_studio/social_studio_service.php';

require_auth();
require_csrf();

$result = social_studio_reanalyze_base_creatives((int)post('limit', 1));
$message = 'Rebuilt ' . (int)$result['updated'] . ' locked template prompt' . ((int)$result['updated'] === 1 ? '' : 's') . '.';
if ((int)$result['failed'] > 0) {
    $message .= ' ' . (int)$result['failed'] . ' could not be analyzed in this batch.';
    if (!empty($result['errors'])) {
        $message .= ' ' . implode(' | ', array_map(static fn($error) => substr((string)$error, 0, 180), (array)$result['errors']));
    }
}
$message .= ' ' . (int)$result['remaining'] . ' remaining.';
flash_set((int)$result['updated'] > 0 ? 'success' : 'error', $message);
redirect(base_url('social-studio.php'));
