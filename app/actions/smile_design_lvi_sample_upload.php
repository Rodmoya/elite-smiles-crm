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

$style = (string)post('style_key', 'natural');
$stored = smile_design_store_private_image($_FILES['sample_image'] ?? [], 'lvi-samples/' . $style);
if (empty($stored['ok'])) {
    flash_set('error', (string)($stored['message'] ?? 'Could not upload sample.'));
    redirect(base_url('smile-design/lvi-library'));
}

$sampleId = db_insert(
    "INSERT INTO lvi_sample_images (style_key, title, description, storage_key, mime_type, file_size, procedure_label, tags, is_active, sort_order, created_by)
     VALUES (:style_key, :title, :description, :storage_key, :mime_type, :file_size, :procedure_label, :tags, :is_active, :sort_order, :created_by)",
    [
        'style_key' => $style,
        'title' => trim((string)post('title', 'LVI Sample')),
        'description' => trim((string)post('description')),
        'storage_key' => $stored['storage_key'],
        'mime_type' => $stored['mime_type'],
        'file_size' => $stored['file_size'],
        'procedure_label' => trim((string)post('procedure_label')),
        'tags' => trim((string)post('tags')),
        'is_active' => post('is_active', '1') === '1' ? 1 : 0,
        'sort_order' => (int)post('sort_order', 0),
        'created_by' => auth_user_id(),
    ]
);
smile_design_audit(null, 'lvi_sample_uploaded', ['lvi_sample_id' => $sampleId, 'style_key' => $style], auth_user_id());
flash_set('success', 'LVI sample uploaded.');
redirect(base_url('smile-design/lvi-library'));
