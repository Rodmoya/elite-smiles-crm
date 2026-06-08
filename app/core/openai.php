<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: app/core/openai.php
 *
 * Thin OpenAI Responses API client for CRM automation.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

if (!function_exists('elite_openai_is_configured')) {
    function elite_openai_is_configured(): bool
    {
        return trim((string) OPENAI_API_KEY) !== '';
    }
}

if (!function_exists('elite_openai_extract_output_text')) {
    function elite_openai_extract_output_text(array $decoded): string
    {
        if (isset($decoded['output_text']) && is_string($decoded['output_text'])) {
            return trim($decoded['output_text']);
        }

        $parts = [];
        foreach (($decoded['output'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            foreach (($item['content'] ?? []) as $content) {
                if (is_array($content) && isset($content['text']) && is_string($content['text'])) {
                    $parts[] = $content['text'];
                }
            }
        }

        return trim(implode("\n", $parts));
    }
}

if (!function_exists('elite_openai_json_response')) {
    function elite_openai_json_response(string $systemPrompt, string $userPrompt, array $schema, string $schemaName): array
    {
        if (!elite_openai_is_configured()) {
            return ['ok' => false, 'message' => 'OpenAI is not configured.'];
        }

        $payload = [
            'model' => OPENAI_MODEL_CHAT,
            'input' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => $schemaName,
                    'schema' => $schema,
                    'strict' => true,
                ],
            ],
            'max_output_tokens' => 700,
        ];

        $ch = curl_init('https://api.openai.com/v1/responses');
        if ($ch === false) {
            return ['ok' => false, 'message' => 'Could not initialize OpenAI request.'];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . OPENAI_API_KEY,
                'Content-Type: application/json',
            ],
        ]);

        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            esm_log('openai', 'OpenAI response was not valid JSON.', [
                'status_code' => $statusCode,
                'curl_error' => $curlError,
            ]);
            return ['ok' => false, 'message' => 'OpenAI returned an invalid response.'];
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            esm_log('openai', 'OpenAI request failed.', [
                'status_code' => $statusCode,
                'error' => $decoded['error']['message'] ?? $curlError,
            ]);
            return [
                'ok' => false,
                'message' => (string)($decoded['error']['message'] ?? 'OpenAI request failed.'),
                'status_code' => $statusCode,
            ];
        }

        $outputText = elite_openai_extract_output_text($decoded);
        $json = json_decode($outputText, true);
        if (!is_array($json)) {
            esm_log('openai', 'OpenAI structured output could not be parsed.', [
                'status_code' => $statusCode,
            ]);
            return ['ok' => false, 'message' => 'OpenAI output could not be parsed.'];
        }

        return ['ok' => true, 'data' => $json, 'status_code' => $statusCode];
    }
}

if (!function_exists('elite_openai_image_json_response')) {
    function elite_openai_image_json_response(string $imagePath, string $systemPrompt, string $userPrompt, array $schema, string $schemaName, string $detail = 'high', ?string $model = null): array
    {
        if (!elite_openai_is_configured()) {
            return ['ok' => false, 'message' => 'OpenAI is not configured.'];
        }
        if (!is_file($imagePath)) {
            return ['ok' => false, 'message' => 'Source image for analysis was not found.'];
        }

        $mime = function_exists('elite_openai_detect_image_mime_type')
            ? elite_openai_detect_image_mime_type($imagePath)
            : ((function_exists('mime_content_type') ? @mime_content_type($imagePath) : '') ?: 'image/jpeg');
        $bytes = @file_get_contents($imagePath);
        if (!is_string($bytes) || $bytes === '') {
            return ['ok' => false, 'message' => 'Could not read source image for analysis.'];
        }

        $payload = [
            'model' => $model ?: OPENAI_MODEL_CHAT,
            'input' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'input_text', 'text' => $systemPrompt . "\n\n" . $userPrompt],
                    ['type' => 'input_image', 'image_url' => 'data:' . $mime . ';base64,' . base64_encode($bytes), 'detail' => $detail],
                ],
            ]],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => $schemaName,
                    'schema' => $schema,
                    'strict' => true,
                ],
            ],
            'max_output_tokens' => 900,
        ];

        $ch = curl_init('https://api.openai.com/v1/responses');
        if ($ch === false) {
            return ['ok' => false, 'message' => 'Could not initialize OpenAI vision request.'];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . OPENAI_API_KEY,
                'Content-Type: application/json',
            ],
        ]);

        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            esm_log('openai', 'OpenAI vision response was not valid JSON.', [
                'status_code' => $statusCode,
                'curl_error' => $curlError,
            ]);
            return ['ok' => false, 'message' => 'OpenAI vision returned an invalid response.'];
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            esm_log('openai', 'OpenAI vision request failed.', [
                'status_code' => $statusCode,
                'error' => $decoded['error']['message'] ?? $curlError,
            ]);
            return [
                'ok' => false,
                'message' => (string)($decoded['error']['message'] ?? 'OpenAI vision request failed.'),
                'status_code' => $statusCode,
            ];
        }

        $outputText = elite_openai_extract_output_text($decoded);
        $json = json_decode($outputText, true);
        if (!is_array($json)) {
            esm_log('openai', 'OpenAI vision structured output could not be parsed.', [
                'status_code' => $statusCode,
            ]);
            return ['ok' => false, 'message' => 'OpenAI vision output could not be parsed.'];
        }

        return ['ok' => true, 'data' => $json, 'status_code' => $statusCode, 'response' => $decoded];
    }
}

if (!function_exists('elite_openai_images_json_response')) {
    function elite_openai_images_json_response(array $imagePaths, string $systemPrompt, string $userPrompt, array $schema, string $schemaName, string $detail = 'high', ?string $model = null): array
    {
        if (!elite_openai_is_configured()) {
            return ['ok' => false, 'message' => 'OpenAI is not configured.'];
        }

        $content = [['type' => 'input_text', 'text' => $systemPrompt . "\n\n" . $userPrompt]];
        foreach (array_values($imagePaths) as $index => $imagePath) {
            $imagePath = trim((string)$imagePath);
            if ($imagePath === '' || !is_file($imagePath)) {
                return ['ok' => false, 'message' => 'Image #' . (string)($index + 1) . ' for analysis was not found.'];
            }

            $mime = function_exists('elite_openai_detect_image_mime_type')
                ? elite_openai_detect_image_mime_type($imagePath)
                : ((function_exists('mime_content_type') ? @mime_content_type($imagePath) : '') ?: 'image/jpeg');
            $bytes = @file_get_contents($imagePath);
            if (!is_string($bytes) || $bytes === '') {
                return ['ok' => false, 'message' => 'Could not read image #' . (string)($index + 1) . ' for analysis.'];
            }

            $content[] = [
                'type' => 'input_image',
                'image_url' => 'data:' . $mime . ';base64,' . base64_encode($bytes),
                'detail' => $detail,
            ];
        }

        if (count($content) < 3) {
            return ['ok' => false, 'message' => 'At least two images are required for comparison analysis.'];
        }

        $payload = [
            'model' => $model ?: OPENAI_MODEL_CHAT,
            'input' => [[
                'role' => 'user',
                'content' => $content,
            ]],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => $schemaName,
                    'schema' => $schema,
                    'strict' => true,
                ],
            ],
            'max_output_tokens' => 900,
        ];

        $ch = curl_init('https://api.openai.com/v1/responses');
        if ($ch === false) {
            return ['ok' => false, 'message' => 'Could not initialize OpenAI comparison request.'];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 70,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . OPENAI_API_KEY,
                'Content-Type: application/json',
            ],
        ]);

        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            esm_log('openai', 'OpenAI comparison response was not valid JSON.', [
                'status_code' => $statusCode,
                'curl_error' => $curlError,
            ]);
            return ['ok' => false, 'message' => 'OpenAI comparison returned an invalid response.'];
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            esm_log('openai', 'OpenAI comparison request failed.', [
                'status_code' => $statusCode,
                'error' => $decoded['error']['message'] ?? $curlError,
            ]);
            return [
                'ok' => false,
                'message' => (string)($decoded['error']['message'] ?? 'OpenAI comparison request failed.'),
                'status_code' => $statusCode,
            ];
        }

        $outputText = elite_openai_extract_output_text($decoded);
        $json = json_decode($outputText, true);
        if (!is_array($json)) {
            esm_log('openai', 'OpenAI comparison structured output could not be parsed.', [
                'status_code' => $statusCode,
            ]);
            return ['ok' => false, 'message' => 'OpenAI comparison output could not be parsed.'];
        }

        return ['ok' => true, 'data' => $json, 'status_code' => $statusCode, 'response' => $decoded];
    }
}

if (!function_exists('elite_openai_image_edit')) {
    function elite_openai_detect_image_mime_type(string $path): string
    {
        $mime = '';
        if (function_exists('mime_content_type')) {
            $mime = (string) (@mime_content_type($path) ?: '');
        }

        if (($mime === '' || $mime === 'application/octet-stream') && function_exists('exif_imagetype')) {
            $imagetype = @exif_imagetype($path);
            $mime = match ($imagetype) {
                IMAGETYPE_JPEG => 'image/jpeg',
                IMAGETYPE_PNG => 'image/png',
                IMAGETYPE_WEBP => 'image/webp',
                default => $mime,
            };
        }

        if ($mime === '' || $mime === 'application/octet-stream') {
            $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                default => 'application/octet-stream',
            };
        }

        return $mime;
    }
}

if (!function_exists('elite_openai_image_edit')) {
    function elite_openai_image_edit(array $imagePaths, string $prompt, array $options = []): array
    {
        if (!elite_openai_is_configured()) {
            return ['ok' => false, 'message' => 'OpenAI is not configured.'];
        }

        $imagePaths = array_values(array_filter(array_map(
            static function ($item): array {
                if (is_string($item)) {
                    return [
                        'path' => trim($item),
                        'mime_type' => '',
                    ];
                }
                if (is_array($item)) {
                    return [
                        'path' => is_string($item['path'] ?? null) ? trim((string)$item['path']) : '',
                        'mime_type' => is_string($item['mime_type'] ?? null) ? trim((string)$item['mime_type']) : '',
                    ];
                }
                return ['path' => '', 'mime_type' => ''];
            },
            $imagePaths
        ), static fn(array $item): bool => $item['path'] !== ''));

        if ($imagePaths === []) {
            return ['ok' => false, 'message' => 'At least one source image is required.'];
        }

        foreach ($imagePaths as $imageFile) {
            if (!is_file($imageFile['path'])) {
                return ['ok' => false, 'message' => 'A source image could not be found for OpenAI editing.'];
            }
        }

        $payload = [
            'model' => (string)($options['model'] ?? 'gpt-image-1'),
            'prompt' => $prompt,
            'size' => (string)($options['size'] ?? '1024x1536'),
            'quality' => (string)($options['quality'] ?? 'medium'),
            'background' => (string)($options['background'] ?? 'auto'),
            'output_format' => (string)($options['output_format'] ?? 'png'),
        ];

        if (!empty($options['n']) && is_numeric($options['n'])) {
            $payload['n'] = max(1, min(4, (int)$options['n']));
        }

        if (!empty($options['compression']) && is_numeric($options['compression'])) {
            $payload['output_compression'] = max(0, min(100, (int)$options['compression']));
        }

        $useCurlFile = function_exists('curl_init') && class_exists('CURLFile');
        if ($useCurlFile) {
            if (count($imagePaths) === 1) {
                $mimeType = $imagePaths[0]['mime_type'] !== '' ? $imagePaths[0]['mime_type'] : elite_openai_detect_image_mime_type($imagePaths[0]['path']);
                $payload['image'] = new CURLFile($imagePaths[0]['path'], $mimeType, basename($imagePaths[0]['path']));
            } else {
                foreach ($imagePaths as $index => $imageFile) {
                    $mimeType = $imageFile['mime_type'] !== '' ? $imageFile['mime_type'] : elite_openai_detect_image_mime_type($imageFile['path']);
                    $payload["image[{$index}]"] = new CURLFile($imageFile['path'], $mimeType, basename($imageFile['path']));
                }
            }
        }

        $raw = '';
        $curlError = '';
        $statusCode = 0;

        if ($useCurlFile) {
            $ch = curl_init('https://api.openai.com/v1/images/edits');
            if ($ch === false) {
                return ['ok' => false, 'message' => 'Could not initialize OpenAI image request.'];
            }

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . OPENAI_API_KEY,
                ],
            ]);

            $raw = (string) curl_exec($ch);
            $curlError = curl_error($ch);
            $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
        } else {
            $boundary = '----EliteSmilesOpenAI' . bin2hex(random_bytes(12));
            $eol = "\r\n";
            $body = '';

            foreach ($payload as $key => $value) {
                $body .= '--' . $boundary . $eol;
                $body .= 'Content-Disposition: form-data; name="' . $key . '"' . $eol . $eol;
                $body .= (string)$value . $eol;
            }

            foreach ($imagePaths as $index => $imageFile) {
                $field = count($imagePaths) === 1 ? 'image' : 'image[' . $index . ']';
                $path = $imageFile['path'];
                $filename = basename($path);
                $mimeType = $imageFile['mime_type'] !== '' ? $imageFile['mime_type'] : elite_openai_detect_image_mime_type($path);
                $body .= '--' . $boundary . $eol;
                $body .= 'Content-Disposition: form-data; name="' . $field . '"; filename="' . addslashes($filename) . '"' . $eol;
                $body .= 'Content-Type: ' . $mimeType . $eol . $eol;
                $body .= file_get_contents($path) . $eol;
            }

            $body .= '--' . $boundary . '--' . $eol;

            $headers = [
                'Authorization: Bearer ' . OPENAI_API_KEY,
                'Content-Type: multipart/form-data; boundary=' . $boundary,
                'Content-Length: ' . strlen($body),
            ];

            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => implode("\r\n", $headers),
                    'content' => $body,
                    'timeout' => 120,
                    'ignore_errors' => true,
                ],
            ]);

            $raw = @file_get_contents('https://api.openai.com/v1/images/edits', false, $context);
            $raw = is_string($raw) ? $raw : '';
            $responseHeaders = function_exists('http_get_last_response_headers')
                ? (http_get_last_response_headers() ?: [])
                : [];
            if (!empty($responseHeaders[0]) && preg_match('/\s(\d{3})\s/', $responseHeaders[0], $match)) {
                $statusCode = (int)$match[1];
            }
            if ($raw === '' && $statusCode === 0) {
                $curlError = 'OpenAI image request failed before a response was returned.';
            }
        }

        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            esm_log('openai', 'OpenAI image response was not valid JSON.', [
                'status_code' => $statusCode,
                'curl_error' => $curlError,
            ]);
            return ['ok' => false, 'message' => 'OpenAI image generation returned an invalid response.'];
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            esm_log('openai', 'OpenAI image request failed.', [
                'status_code' => $statusCode,
                'error' => $decoded['error']['message'] ?? $curlError,
                'images' => array_map(static fn(array $file): array => [
                    'path' => $file['path'],
                    'mime_type' => $file['mime_type'] !== '' ? $file['mime_type'] : elite_openai_detect_image_mime_type($file['path']),
                    'size' => @filesize($file['path']) ?: 0,
                ], $imagePaths),
            ]);
            return [
                'ok' => false,
                'message' => (string)($decoded['error']['message'] ?? 'OpenAI image request failed.'),
                'status_code' => $statusCode,
                'response' => $decoded,
            ];
        }

        $first = $decoded['data'][0] ?? null;
        $imageBase64 = is_array($first) ? (string)($first['b64_json'] ?? '') : '';
        if ($imageBase64 === '') {
            esm_log('openai', 'OpenAI image response missing image data.', [
                'status_code' => $statusCode,
            ]);
            return ['ok' => false, 'message' => 'OpenAI did not return image data.'];
        }

        return [
            'ok' => true,
            'status_code' => $statusCode,
            'image_base64' => $imageBase64,
            'mime_type' => match (strtolower((string)$payload['output_format'])) {
                'jpeg', 'jpg' => 'image/jpeg',
                'webp' => 'image/webp',
                default => 'image/png',
            },
            'revised_prompt' => (string)($first['revised_prompt'] ?? ''),
            'response' => $decoded,
            'request' => [
                'model' => $payload['model'],
                'size' => $payload['size'],
                'quality' => $payload['quality'],
                'background' => $payload['background'],
                'output_format' => $payload['output_format'],
            ],
        ];
    }
}
