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

foreach ([
    'full_head' => ['Full Head Shot', 'full_head_before', 'full_head_after'],
    'smile_close_up' => ['Smile Close-Up', 'smile_close_up_before', 'smile_close_up_after'],
] as $groupKey => [$caption, $beforeField, $afterField]) {
    if (!empty($_FILES[$beforeField]['name']) && !empty($_FILES[$afterField]['name'])) {
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
        }
    }
}

flash_set('success', 'Real result saved.');
redirect(base_url('smile-design/real-results'));
