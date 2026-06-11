<?php
declare(strict_types=1);

/**
 * Meta webhook file-backed queue.
 *
 * Keeps the public webhook path independent from live DB availability.
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';

if (!function_exists('meta_queue_root_path')) {
    function meta_queue_root_path(): string
    {
        return storage_path('meta-webhooks');
    }
}

if (!function_exists('meta_queue_dir')) {
    function meta_queue_dir(string $status): string
    {
        return meta_queue_root_path() . '/' . trim($status, '/\\');
    }
}

if (!function_exists('meta_queue_ensure_directories')) {
    function meta_queue_ensure_directories(): void
    {
        ensure_directory(meta_queue_root_path());
        foreach (['pending', 'processing', 'done', 'failed'] as $status) {
            ensure_directory(meta_queue_dir($status));
        }
    }
}

if (!function_exists('meta_queue_event_id')) {
    function meta_queue_event_id(): string
    {
        return gmdate('Ymd_His') . '_' . bin2hex(random_bytes(6));
    }
}

if (!function_exists('meta_queue_is_loopback_ip')) {
    function meta_queue_is_loopback_ip(?string $value): bool
    {
        $value = trim((string) $value);
        return in_array($value, ['127.0.0.1', '::1'], true);
    }
}

if (!function_exists('meta_queue_allows_unsigned_loopback')) {
    function meta_queue_allows_unsigned_loopback(): bool
    {
        if (PHP_SAPI !== 'cli-server') {
            return false;
        }

        return meta_queue_is_loopback_ip($_SERVER['REMOTE_ADDR'] ?? '')
            && meta_queue_is_loopback_ip($_SERVER['SERVER_ADDR'] ?? ($_SERVER['LOCAL_ADDR'] ?? '127.0.0.1'));
    }
}

if (!function_exists('meta_queue_extract_candidates')) {
    function meta_queue_extract_candidates(array $payload): array
    {
        $results = [];

        if (is_array($payload['entry'] ?? null)) {
            foreach ($payload['entry'] as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $entryPageId = trim((string) ($entry['id'] ?? ''));
                $changes = $entry['changes'] ?? [];
                if (!is_array($changes)) {
                    continue;
                }

                foreach ($changes as $change) {
                    if (!is_array($change)) {
                        continue;
                    }

                    $field = strtolower(trim((string) ($change['field'] ?? '')));
                    if ($field !== 'leadgen' && $field !== 'leads') {
                        continue;
                    }

                    $value = is_array($change['value'] ?? null) ? $change['value'] : [];
                    $leadgenId = trim((string) ($value['leadgen_id'] ?? ''));
                    if ($leadgenId === '') {
                        continue;
                    }

                    $results[] = [
                        'leadgen_id' => $leadgenId,
                        'form_id' => trim((string) ($value['form_id'] ?? '')),
                        'page_id' => trim((string) ($value['page_id'] ?? $entryPageId)),
                    ];
                }
            }
        }

        if ($results !== []) {
            return $results;
        }

        $leadgenId = trim((string) ($payload['leadgen_id'] ?? $payload['id'] ?? ''));
        if ($leadgenId === '') {
            return [];
        }

        return [[
            'leadgen_id' => $leadgenId,
            'form_id' => trim((string) ($payload['form_id'] ?? '')),
            'page_id' => trim((string) ($payload['page_id'] ?? '')),
        ]];
    }
}

if (!function_exists('meta_queue_sanitize_value')) {
function meta_queue_sanitize_value(mixed $value, int $depth = 0): mixed
{
    if ($depth >= 10) {
        return '[max-depth]';
    }

        if (is_array($value)) {
            $sanitized = [];
            $count = 0;
            foreach ($value as $key => $item) {
                $count++;
                if ($count > 120) {
                    $sanitized['_truncated'] = true;
                    break;
                }

                $safeKey = is_string($key) ? substr($key, 0, 120) : $key;
                $sanitized[$safeKey] = meta_queue_sanitize_value($item, $depth + 1);
            }

            return $sanitized;
        }

        if (is_object($value)) {
            return meta_queue_sanitize_value((array) $value, $depth + 1);
        }

        if (is_string($value)) {
            $value = trim($value);
            if (strlen($value) > 4000) {
                return substr($value, 0, 4000) . '...[truncated]';
            }

            return $value;
        }

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        return (string) $value;
    }
}

if (!function_exists('meta_queue_build_record')) {
    function meta_queue_build_record(array $payload): array
    {
        $receivedAt = now();
        $candidates = meta_queue_extract_candidates($payload);
        $eventId = meta_queue_event_id();

        return [
            'event_id' => $eventId,
            'status' => 'pending',
            'received_at' => $receivedAt,
            'processed_at' => null,
            'last_attempt_at' => null,
            'attempts' => 0,
            'leadgen_id' => (string) ($candidates[0]['leadgen_id'] ?? ''),
            'form_id' => (string) ($candidates[0]['form_id'] ?? ''),
            'page_id' => (string) ($candidates[0]['page_id'] ?? ''),
            'candidate_count' => count($candidates),
            'candidates' => $candidates,
            'error_summary' => '',
            'payload' => meta_queue_sanitize_value($payload),
        ];
    }
}

if (!function_exists('meta_queue_record_path')) {
    function meta_queue_record_path(string $status, string $eventId): string
    {
        return meta_queue_dir($status) . '/' . $eventId . '.json';
    }
}

if (!function_exists('meta_queue_write_record')) {
    function meta_queue_write_record(string $path, array $record): bool
    {
        $directory = dirname($path);
        ensure_directory($directory);

        $json = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (!is_string($json) || $json === '') {
            return false;
        }

        $tempPath = $path . '.tmp-' . bin2hex(random_bytes(4));
        $written = @file_put_contents($tempPath, $json . PHP_EOL, LOCK_EX);
        if ($written === false) {
            @unlink($tempPath);
            return false;
        }

        if (!@rename($tempPath, $path)) {
            @unlink($tempPath);
            return false;
        }

        return true;
    }
}

if (!function_exists('meta_queue_enqueue')) {
    function meta_queue_enqueue(array $payload): array
    {
        meta_queue_ensure_directories();

        $record = meta_queue_build_record($payload);
        $path = meta_queue_record_path('pending', (string) $record['event_id']);
        $ok = meta_queue_write_record($path, $record);

        return [
            'ok' => $ok,
            'event_id' => (string) $record['event_id'],
            'path' => $path,
            'record' => $record,
        ];
    }
}

if (!function_exists('meta_queue_list_pending_paths')) {
    function meta_queue_list_pending_paths(int $limit = 20): array
    {
        meta_queue_ensure_directories();

        $paths = glob(meta_queue_dir('pending') . '/*.json') ?: [];
        sort($paths, SORT_STRING);

        return array_slice($paths, 0, max(1, $limit));
    }
}

if (!function_exists('meta_queue_load_record')) {
    function meta_queue_load_record(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $json = @file_get_contents($path);
        if (!is_string($json) || trim($json) === '') {
            return null;
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : null;
    }
}

if (!function_exists('meta_queue_claim_record')) {
    function meta_queue_claim_record(string $path): ?array
    {
        $record = meta_queue_load_record($path);
        if ($record === null) {
            return null;
        }

        $eventId = trim((string) ($record['event_id'] ?? ''));
        if ($eventId === '') {
            return null;
        }

        $processingPath = meta_queue_record_path('processing', $eventId);
        if (!@rename($path, $processingPath)) {
            return null;
        }

        $record = meta_queue_load_record($processingPath) ?? $record;
        return [
            'path' => $processingPath,
            'record' => $record,
        ];
    }
}

if (!function_exists('meta_queue_finalize_record')) {
    function meta_queue_finalize_record(string $currentPath, string $status, array $record): bool
    {
        $eventId = trim((string) ($record['event_id'] ?? ''));
        if ($eventId === '') {
            return false;
        }

        $record['status'] = $status;
        if (in_array($status, ['processed', 'ignored', 'failed'], true)) {
            $record['processed_at'] = now();
        }

        if (!meta_queue_write_record($currentPath, $record)) {
            return false;
        }

        $targetDir = $status === 'failed' ? 'failed' : 'done';
        $targetPath = meta_queue_record_path($targetDir, $eventId);

        return @rename($currentPath, $targetPath);
    }
}
