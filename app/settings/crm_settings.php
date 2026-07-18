<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/db.php';

if (!function_exists('crm_settings_ensure_schema')) {
    function crm_settings_ensure_schema(): void
    {
        db_query("CREATE TABLE IF NOT EXISTS crm_settings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(120) NOT NULL,
            setting_value MEDIUMTEXT NULL,
            updated_by INT UNSIGNED NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_crm_settings_key (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}
if (!function_exists('crm_settings_get_json')) {
    function crm_settings_get_json(string $key, mixed $default = null): mixed
    {
        crm_settings_ensure_schema();
        $key = trim($key);
        if ($key === '') {
            return $default;
        }

        $row = db_one('SELECT setting_value FROM crm_settings WHERE setting_key = :key LIMIT 1', ['key' => $key]);
        if (!$row || trim((string)($row['setting_value'] ?? '')) === '') {
            return $default;
        }

        $decoded = json_decode((string)$row['setting_value'], true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
    }
}

if (!function_exists('crm_settings_set_json')) {
    function crm_settings_set_json(string $key, mixed $value, int $updatedBy = 0): void
    {
        crm_settings_ensure_schema();
        $key = trim($key);
        if ($key === '') {
            throw new InvalidArgumentException('Setting key is required.');
        }

        db_query(
            'INSERT INTO crm_settings (setting_key, setting_value, updated_by, updated_at, created_at)
             VALUES (:key, :value, :updated_by, NOW(), NOW())
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()',
            [
                'key' => $key,
                'value' => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'updated_by' => $updatedBy > 0 ? $updatedBy : null,
            ]
        );
    }
}
