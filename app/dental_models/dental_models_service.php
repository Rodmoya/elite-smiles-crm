<?php
declare(strict_types=1);

/**
 * Elite Smiles Dental Models
 * File: app/dental_models/dental_models_service.php
 *
 * V1 data access + secure STL upload storage helpers for the internal
 * Dental Model Builder module.
 */

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/auth.php';

if (!function_exists('dental_models_allowed_staff_roles')) {
    function dental_models_allowed_staff_roles(): array
    {
        return ['admin', 'marketing_manager', 'staff'];
    }
}

if (!function_exists('dental_models_is_staff_request')) {
    function dental_models_is_staff_request(): bool
    {
        return auth_check() && auth_has_role(...dental_models_allowed_staff_roles());
    }
}

if (!function_exists('dental_models_staff_gate')) {
    function dental_models_staff_gate(): void
    {
        if (!dental_models_is_staff_request()) {
            http_response_code(403);
            exit('Forbidden');
        }
    }
}

if (!function_exists('dental_models_private_root')) {
    function dental_models_private_root(): string
    {
        return storage_path('dental-models');
    }
}

if (!function_exists('dental_models_ensure_schema')) {
    function dental_models_ensure_schema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        $charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        db_query(
            "CREATE TABLE IF NOT EXISTS dental_models (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                smile_design_case_id INT UNSIGNED NULL,
                patient_name VARCHAR(190) NULL,
                original_filename VARCHAR(255) NOT NULL,
                stored_path VARCHAR(255) NOT NULL,
                file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
                mime_type VARCHAR(120) NOT NULL,
                processing_status VARCHAR(60) NOT NULL DEFAULT 'original',
                settings_json JSON NULL,
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_dental_models_case (smile_design_case_id),
                INDEX idx_dental_models_status (processing_status),
                INDEX idx_dental_models_created_at (created_at),
                INDEX idx_dental_models_created_by (created_by)
            ) {$charset}"
        );

        $done = true;
    }
}

if (!function_exists('dental_models_parse_ini_size_bytes')) {
    function dental_models_parse_ini_size_bytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $last = strtoupper(substr($value, -1));
        $number = (float) $value;
        if (is_numeric(substr($value, 0, -1)) && in_array($last, ['K', 'M', 'G'], true)) {
            $number = (float)substr($value, 0, -1);
        } else {
            $last = '';
        }

        return match ($last) {
            'K' => (int)($number * 1024),
            'M' => (int)($number * 1024 * 1024),
            'G' => (int)($number * 1024 * 1024 * 1024),
            default => max(0, (int)$number),
        };
    }
}

if (!function_exists('dental_models_recommended_upload_limit_bytes')) {
    function dental_models_recommended_upload_limit_bytes(): int
    {
        return 250 * 1024 * 1024;
    }
}

if (!function_exists('dental_models_upload_settings')) {
    function dental_models_upload_settings(): array
    {
        $uploadMax = dental_models_parse_ini_size_bytes((string) ini_get('upload_max_filesize'));
        $postMax = dental_models_parse_ini_size_bytes((string) ini_get('post_max_size'));
        $maxExecution = (int) ini_get('max_execution_time');
        $memoryLimitRaw = strtolower(trim((string) ini_get('memory_limit')));
        $memoryLimit = $memoryLimitRaw === '-1' || $memoryLimitRaw === 'unlimited'
            ? -1
            : dental_models_parse_ini_size_bytes($memoryLimitRaw);

        return [
            'php_upload_max' => $uploadMax,
            'php_post_max' => $postMax,
            'php_execution_time' => $maxExecution > 0 ? $maxExecution : 0,
            'php_memory_limit' => $memoryLimit,
        ];
    }
}

if (!function_exists('dental_models_upload_limit_bytes')) {
    function dental_models_upload_limit_bytes(): int
    {
        // Keep V1 bounded and aligned with practical upload constraints.
        $phpUploadMax = dental_models_parse_ini_size_bytes((string) ini_get('upload_max_filesize'));
        $phpPostMax = dental_models_parse_ini_size_bytes((string) ini_get('post_max_size'));
        $hardLimit = dental_models_recommended_upload_limit_bytes();

        $limits = array_values(array_filter([$phpUploadMax, $phpPostMax], static fn(int $value): bool => $value > 0));
        if (empty($limits)) {
            return $hardLimit;
        }

        $limits[] = $hardLimit;
        return (int) min($limits);
    }
}

if (!function_exists('dental_models_upload_limit_label')) {
    function dental_models_upload_limit_label(): string
    {
        $bytes = dental_models_upload_limit_bytes();
        return dental_models_format_bytes($bytes) . ' max';
    }
}

if (!function_exists('dental_models_upload_limit_warning')) {
    function dental_models_upload_limit_warning(): string
    {
        $limit = dental_models_upload_limit_bytes();
        $target = dental_models_recommended_upload_limit_bytes();
        if ($limit >= $target) {
            return '';
        }

        return 'Current effective limit is '
            . dental_models_format_bytes($limit)
            . '. Increase upload settings to at least '
            . dental_models_format_bytes($target)
            . ' for production STL testing.';
    }
}

if (!function_exists('dental_models_safe_filename')) {
    function dental_models_safe_filename(string $filename): string
    {
        $filename = trim((string) $filename);
        if ($filename === '') {
            return 'model.stl';
        }

        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename);
        return $name !== '' ? $name : 'model.stl';
    }
}

if (!function_exists('dental_models_build_stl_storage_path')) {
    function dental_models_build_stl_storage_path(string $filename): string
    {
        $prefix = 'dental-models/' . date('Y/m');
        return $prefix . '/' . $filename;
    }
}

if (!function_exists('dental_models_is_stl_upload')) {
    function dental_models_is_stl_upload(array $file): bool
    {
        $name = (string)($file['name'] ?? '');
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        return $extension === 'stl';
    }
}

if (!function_exists('dental_models_upload_error_message')) {
    function dental_models_upload_error_message(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The STL file is too large for server limits.',
            UPLOAD_ERR_PARTIAL => 'The STL upload was interrupted. Please retry.',
            UPLOAD_ERR_NO_FILE => 'Please upload an STL file.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server temporary directory is missing.',
            UPLOAD_ERR_CANT_WRITE => 'Server could not write the STL file.',
            UPLOAD_ERR_EXTENSION => 'Upload was blocked by a server extension.',
            default => 'Upload failed before the file reached server storage.',
        };
    }
}

if (!function_exists('dental_models_detect_mime_type')) {
    function dental_models_detect_mime_type(string $tmpPath): string
    {
        if (is_file($tmpPath) && function_exists('finfo_open')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $detected = (string)($finfo->file($tmpPath) ?: '');
            if ($detected !== '') {
                return strtolower($detected);
            }
        }

        return 'model/stl';
    }
}

if (!function_exists('dental_models_format_bytes')) {
    function dental_models_format_bytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $value = (float)$bytes;
        $index = 0;

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return number_format($value, $index === 0 ? 0 : 1) . ' ' . $units[$index];
    }
}

if (!function_exists('dental_models_resolve_stored_path')) {
    function dental_models_resolve_stored_path(string $storedPath): ?string
    {
        if (!preg_match('~^[A-Za-z0-9._/-]+\.stl$~', $storedPath)) {
            return null;
        }
        if (!str_starts_with($storedPath, 'dental-models/')) {
            return null;
        }

        $root = realpath(dental_models_private_root());
        if (!$root) {
            return null;
        }

        $candidate = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $storedPath);
        $candidate = $root . DIRECTORY_SEPARATOR . ltrim($candidate, "/\\");
        $resolved = realpath($candidate);

        if (!$resolved || !str_starts_with($resolved, $root . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $resolved;
    }
}

if (!function_exists('dental_models_resolve_model_file')) {
    function dental_models_resolve_model_file(array $model): ?string
    {
        $storedPath = (string)($model['stored_path'] ?? '');
        if ($storedPath === '') {
            return null;
        }

        return dental_models_resolve_stored_path($storedPath);
    }
}

if (!function_exists('dental_models_normalize_mime_type')) {
    function dental_models_normalize_mime_type(string $mimeType): string
    {
        $mimeType = strtolower(trim($mimeType));
        $allowed = [
            'model/stl' => 'model/stl',
            'application/sla' => 'application/sla',
            'application/octet-stream' => 'application/octet-stream',
            'binary/octet-stream' => 'application/octet-stream',
            'application/x-binary' => 'application/octet-stream',
        ];

        return $allowed[$mimeType] ?? 'application/octet-stream';
    }
}

if (!function_exists('dental_models_list')) {
    function dental_models_list(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        return db_all(
            "SELECT dm.*
             FROM dental_models dm
             ORDER BY dm.created_at DESC, dm.id DESC
             LIMIT {$limit}"
        );
    }
}

if (!function_exists('dental_models_find')) {
    function dental_models_find(int $id): ?array
    {
        $row = db_one(
            'SELECT * FROM dental_models WHERE id = :id LIMIT 1',
            ['id' => $id]
        );

        return $row ?: null;
    }
}

if (!function_exists('dental_models_create_from_upload')) {
    function dental_models_create_from_upload(array $file, string $patientName = '', ?int $smileDesignCaseId = null, ?int $createdBy = null): array
    {
        $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'message' => dental_models_upload_error_message($errorCode)];
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return ['ok' => false, 'message' => 'Upload never reached secure staging storage.'];
        }

        if (!dental_models_is_stl_upload($file)) {
            return ['ok' => false, 'message' => 'Only .stl files are supported. Please upload a dental STL export.'];
        }

        $size = (int)($file['size'] ?? 0);
        if ($size <= 0) {
            return ['ok' => false, 'message' => 'The STL file is empty.'];
        }
        $detectedMimeType = dental_models_detect_mime_type($tmpName) ?: 'model/stl';

        $max = dental_models_upload_limit_bytes();
        if ($size > $max) {
            return ['ok' => false, 'message' => 'That STL is too large (' . dental_models_format_bytes($size) . '). Max is ' . dental_models_format_bytes($max) . '.'];
        }

        $originalName = trim((string)($file['name'] ?? ''));
        $originalName = $originalName !== '' ? dental_models_safe_filename($originalName) : 'dental-model.stl';

        $root = dental_models_private_root();
        $subdir = date('Y/m');
        $directory = $root . DIRECTORY_SEPARATOR . $subdir;
        ensure_directory($directory);

        $storageName = date('Ymd_His') . '-' . bin2hex(random_bytes(8)) . '.stl';
        $storedPath = dental_models_build_stl_storage_path($storageName);
        $target = $root . DIRECTORY_SEPARATOR . $subdir . DIRECTORY_SEPARATOR . $storageName;

        if (!move_uploaded_file($tmpName, $target)) {
            return ['ok' => false, 'message' => 'Could not save the STL to protected storage.'];
        }

        $patient = trim($patientName);
        $recordId = db_insert(
            "INSERT INTO dental_models
                (smile_design_case_id, patient_name, original_filename, stored_path, file_size, mime_type, created_by)
             VALUES
                (:smile_design_case_id, :patient_name, :original_filename, :stored_path, :file_size, :mime_type, :created_by)",
            [
                'smile_design_case_id' => $smileDesignCaseId,
                'patient_name' => $patient !== '' ? $patient : null,
                'original_filename' => $originalName,
                'stored_path' => $storedPath,
                'file_size' => $size,
                'mime_type' => $detectedMimeType,
                'created_by' => $createdBy,
            ]
        );

        if ($recordId <= 0) {
            if (is_file($target)) {
                @unlink($target);
            }
            return ['ok' => false, 'message' => 'Unable to create the model record in the CRM database.'];
        }

        return [
            'ok' => true,
            'model_id' => $recordId,
            'original_filename' => $originalName,
            'stored_path' => $storedPath,
            'file_size' => $size,
        ];
    }
}
