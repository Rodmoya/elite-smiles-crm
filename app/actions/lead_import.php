<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../leads/lead_meta.php';
require_once __DIR__ . '/../leads/lead_service.php';

header('Content-Type: application/json; charset=UTF-8');

function lead_import_json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function lead_import_clean_cell(string $value): string
{
    $value = trim($value);
    if (strlen($value) >= 2 && $value[0] === '"' && substr($value, -1) === '"') {
        $value = substr($value, 1, -1);
    }
    return trim(str_replace('""', '"', $value));
}

function lead_import_decode_content(string $content): string
{
    if ($content === '') {
        return '';
    }

    if (str_starts_with($content, "\xFF\xFE")) {
        if (function_exists('mb_convert_encoding')) {
            return (string) mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16LE');
        }
        if (function_exists('iconv')) {
            return (string) iconv('UTF-16LE', 'UTF-8//IGNORE', substr($content, 2));
        }
    }

    if (str_starts_with($content, "\xFE\xFF")) {
        if (function_exists('mb_convert_encoding')) {
            return (string) mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16BE');
        }
        if (function_exists('iconv')) {
            return (string) iconv('UTF-16BE', 'UTF-8//IGNORE', substr($content, 2));
        }
    }

    if (substr_count(substr($content, 0, min(strlen($content), 200)), "\x00") > 10) {
        if (function_exists('mb_convert_encoding')) {
            return (string) mb_convert_encoding($content, 'UTF-8', 'UTF-16LE');
        }
        if (function_exists('iconv')) {
            return (string) iconv('UTF-16LE', 'UTF-8//IGNORE', $content);
        }
    }

    return preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
}

function lead_import_parse_uploaded_file(array $file): array
{
    $path = (string) ($file['tmp_name'] ?? '');
    if ($path === '' || !is_uploaded_file($path)) {
        return [];
    }

    $content = lead_import_decode_content((string) file_get_contents($path));
    $content = trim(str_replace(["\r\n", "\r"], "\n", $content));
    if ($content === '') {
        return [];
    }

    $firstLine = strtok($content, "\n");
    $delimiter = is_string($firstLine) && str_contains($firstLine, "\t") ? "\t" : ',';
    $handle = fopen('php://temp', 'r+');
    if (!$handle) {
        return [];
    }

    fwrite($handle, $content);
    rewind($handle);
    $headers = fgetcsv($handle, 0, $delimiter);
    if (!is_array($headers) || $headers === []) {
        fclose($handle);
        return [];
    }

    $headers = array_map(static function ($header): string {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header) ?? (string) $header;
        return strtolower(lead_import_clean_cell($header));
    }, $headers);

    $rows = [];
    while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
        if (!is_array($values) || $values === []) {
            continue;
        }
        $row = [];
        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }
            $row[$header] = lead_import_clean_cell((string) ($values[$index] ?? ''));
        }
        if (array_filter($row, static fn ($value): bool => trim((string) $value) !== '') !== []) {
            $rows[] = $row;
        }
    }
    fclose($handle);

    return $rows;
}

if (!is_post()) {
    lead_import_json_response(['ok' => false, 'message' => 'Invalid request method.'], 405);
}

if (!is_logged_in()) {
    lead_import_json_response(['ok' => false, 'message' => 'Unauthorized.'], 401);
}

try {
    require_csrf();
} catch (Throwable $e) {
    lead_import_json_response(['ok' => false, 'message' => 'Invalid security token.'], 419);
}

$rawRows = trim((string) post('rows_json', ''));
$rows = $rawRows !== '' ? json_decode($rawRows, true) : [];
$fileRows = [];
if (is_array($_FILES['lead_file'] ?? null)) {
    $fileRows = lead_import_parse_uploaded_file($_FILES['lead_file']);
}

$rowsLookMalformed = false;
if (is_array($rows) && $rows !== []) {
    $sample = reset($rows);
    if (is_array($sample) && count($sample) === 1) {
        $sampleKey = (string) array_key_first($sample);
        $sampleValue = (string) ($sample[$sampleKey] ?? '');
        $rowsLookMalformed = str_contains($sampleKey, "\t") && str_contains($sampleValue, "\t");
    }
}

if ((!is_array($rows) || $rows === [] || $rowsLookMalformed) && $fileRows !== []) {
    $rows = $fileRows;
}
if (!is_array($rows) || $rows === []) {
    lead_import_json_response(['ok' => false, 'message' => 'No lead rows were provided.'], 422);
}

$user = auth_user();
$result = lead_import_meta_rows($rows, is_array($user) ? $user : []);

lead_import_json_response([
    'ok' => true,
    'message' => 'Lead import completed.',
    'result' => $result,
]);
