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

$resultId = db_insert(
    "INSERT INTO real_result_cases (title, patient_label, procedure_label, style_label, story, created_by)
     VALUES (:title, :patient_label, :procedure_label, :style_label, :story, :created_by)",
    [
        'title' => trim((string)post('title')),
        'patient_label' => trim((string)post('patient_label')) ?: null,
        'procedure_label' => trim((string)post('procedure_label')) ?: null,
        'style_label' => trim((string)post('style_label')) ?: null,
        'story' => trim((string)post('story')) ?: null,
        'created_by' => auth_user_id(),
    ]
);

smile_design_audit(null, 'real_result_created', ['title' => trim((string)post('title'))], auth_user_id());

$incompletePairs = [];
$savedPairs = [];
foreach ([
    'full_head' => ['Full Head Shot', 'full_head_before', 'full_head_after'],
    'smile_close_up' => ['Smile Close-Up', 'smile_close_up_before', 'smile_close_up_after'],
] as $groupKey => [$caption, $beforeField, $afterField]) {
    $hasBefore = !empty($_FILES[$beforeField]['name']);
    $hasAfter = !empty($_FILES[$afterField]['name']);
    if ($hasBefore && $hasAfter) {
        $before = smile_design_store_private_image($_FILES[$beforeField], 'real-results/' . $resultId . '/' . $groupKey);
        $after = smile_design_store_private_image($_FILES[$afterField], 'real-results/' . $resultId . '/' . $groupKey);
        if (!empty($before['ok']) && !empty($after['ok'])) {
            db_insert(
                "INSERT INTO real_result_photo_pairs (result_case_id, photo_group, before_storage_key, before_mime_type, after_storage_key, after_mime_type, caption)
                 VALUES (:result_case_id, :photo_group, :before_storage_key, :before_mime_type, :after_storage_key, :after_mime_type, :caption)",
                [
                    'result_case_id' => $resultId,
                    'photo_group' => $groupKey,
                    'before_storage_key' => $before['storage_key'],
                    'before_mime_type' => $before['mime_type'],
                    'after_storage_key' => $after['storage_key'],
                    'after_mime_type' => $after['mime_type'],
                    'caption' => $caption,
                ]
            );
            smile_design_audit(null, 'real_result_photo_uploaded', ['result_case_id' => $resultId, 'photo_group' => $groupKey], auth_user_id());
            $savedPairs[] = $caption;
        } else {
            $incompletePairs[] = $caption . ' (upload failed)';
        }
    } elseif ($hasBefore || $hasAfter) {
        // Only one side of the pair was uploaded. The create form makes clear
        // a pair only saves when both sides arrive together, and there is no
        // edit page to add the missing side later - so flag this loudly
        // instead of silently dropping the one photo that was uploaded.
        $incompletePairs[] = $caption . ' (only ' . ($hasBefore ? 'Before' : 'After') . ' was uploaded - both are required together, so it was not saved)';
    }
}

if ($incompletePairs !== []) {
    $message = 'Real result saved, but ' . implode('; ', $incompletePairs) . '. There is no way to add these photos later - create a new result and upload both sides together.';
    flash_set($savedPairs === [] ? 'error' : 'success', $message);
} else {
    flash_set('success', 'Real result saved.');
}
redirect(base_url('smile-design/real-results'));
