<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/social_studio/social_studio_service.php';

require_auth();
require_csrf();

$deleted = 0;
try {
    // The queue reset is intentionally database-first. Image files are optional
    // artifacts; a stale file must never prevent clearing the review queue.
    $deleted = (int) db_query('DELETE FROM social_studio_drafts')->rowCount();
} catch (Throwable $error) {
    esm_log('social_studio', 'Bulk draft reset failed.', ['message' => $error->getMessage()]);
    flash_set('error', 'The social review queue could not be cleared.');
    redirect(base_url('social-studio.php'));
}
flash_set('success', $deleted > 0 ? "Cleared {$deleted} social drafts." : 'The social review queue was already empty.');
redirect(base_url('social-studio.php'));
