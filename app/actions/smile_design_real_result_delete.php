<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../smile_design/smile_design_service.php';

require_auth();
require_csrf();
smile_design_ensure_schema();

$resultId = (int)post('result_id', 0);
$result = db_one('SELECT * FROM real_result_cases WHERE id = :id LIMIT 1', ['id' => $resultId]);
if (!$result) {
    flash_set('error', 'Real result not found.');
    redirect(base_url('smile-design/real-results'));
}

$pairs = db_all('SELECT * FROM real_result_photo_pairs WHERE result_case_id = :id', ['id' => $resultId]);
$storageKeys = [];
foreach ($pairs as $pair) {
    foreach (['before_storage_key', 'after_storage_key'] as $field) {
        $key = trim((string)($pair[$field] ?? ''));
        if ($key !== '') {
            $storageKeys[] = $key;
        }
    }
}
$storageKeys = array_values(array_unique($storageKeys));

try {
    db_begin();

    db_execute(
        'DELETE spa FROM smile_pair_alignments spa INNER JOIN real_result_photo_pairs rrp ON rrp.id = spa.real_pair_id WHERE rrp.result_case_id = :result_case_id',
        ['result_case_id' => $resultId]
    );
    db_execute('DELETE FROM real_result_photo_pairs WHERE result_case_id = :result_case_id', ['result_case_id' => $resultId]);
    db_execute('DELETE FROM real_result_cases WHERE id = :id', ['id' => $resultId]);

    db_commit();
} catch (Throwable $e) {
    db_rollBack();
    if (function_exists('esm_log')) {
        esm_log('smile_design', 'Real result delete failed', [
            'result_id' => $resultId,
            'message' => $e->getMessage(),
        ]);
    }
    flash_set('error', 'Could not delete this real result right now.');
    redirect(base_url('smile-design/real-results'));
}

foreach ($storageKeys as $storageKey) {
    $filePath = smile_design_safe_storage_path($storageKey);
    if ($filePath && is_file($filePath)) {
        @unlink($filePath);
    }
}

smile_design_audit(null, 'real_result_deleted', [
    'result_id' => $resultId,
    'title' => (string)($result['title'] ?? ''),
], auth_user_id());

flash_set('success', 'Real result deleted.');
redirect(base_url('smile-design/real-results'));
