<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

smile_design_internal_boot('Recent Check');

$rows = db_all(
    "SELECT smu.id,
            smu.photo_type,
            smu.original_name,
            smu.mime_type,
            smu.file_size,
            smu.width,
            smu.height,
            smu.imported_case_id,
            smu.imported_photo_id,
            smu.created_at,
            smu.imported_at,
            LEFT(smu.token_hash, 12) AS token_prefix,
            spl.created_at AS link_created_at,
            spl.expires_at AS link_expires_at
     FROM smile_mobile_uploads smu
     LEFT JOIN smile_preview_links spl
       ON spl.token_hash = smu.token_hash
      AND spl.purpose = 'mobile_upload'
     ORDER BY smu.created_at DESC, smu.id DESC
     LIMIT 30"
);

header('Content-Type: text/plain; charset=utf-8');
echo "Recent Smile Design mobile records\n";
echo "Generated: " . date('Y-m-d H:i:s') . "\n\n";
if (!$rows) {
    echo "No mobile records found.\n";
    exit;
}

foreach ($rows as $row) {
    echo '#' . (int)$row['id'] . ' | ' . (string)$row['created_at'] . ' | ' . (string)$row['photo_type'] . "\n";
    echo '  file: ' . (string)($row['original_name'] ?? '') . ' | ' . (string)($row['mime_type'] ?? '') . ' | ' . (int)($row['file_size'] ?? 0) . " bytes\n";
    echo '  size: ' . (string)($row['width'] ?? '') . 'x' . (string)($row['height'] ?? '') . ' | token: ' . (string)($row['token_prefix'] ?? '') . "\n";
    echo '  imported_case_id: ' . (string)($row['imported_case_id'] ?? '') . ' | imported_photo_id: ' . (string)($row['imported_photo_id'] ?? '') . ' | imported_at: ' . (string)($row['imported_at'] ?? '') . "\n";
    echo '  link_created_at: ' . (string)($row['link_created_at'] ?? '') . ' | link_expires_at: ' . (string)($row['link_expires_at'] ?? '') . "\n\n";
}
