<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

if (!function_exists('elite_gemini_is_configured')) {
    function elite_gemini_is_configured(): bool
    {
        return defined('GOOGLE_GEMINI_API_KEY') && trim((string) GOOGLE_GEMINI_API_KEY) !== '';
    }
}

if (!function_exists('elite_gemini_detect_image_mime_type')) {
    function elite_gemini_detect_image_mime_type(string $path): string
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

if (!function_exists('elite_gemini_generate_image_edit')) {
    function elite_gemini_generate_image_edit(array $imagePaths, string $prompt, array $options = []): array
    {
        if (!elite_gemini_is_configured()) {
            return ['ok' => false, 'message' => 'Google Gemini is not configured.'];
        }

        $imagePaths = array_values(array_filter(array_map(
            static function ($item): array {
                if (is_string($item)) {
                    return ['path' => trim($item), 'mime_type' => ''];
                }
                if (is_array($item)) {
                    return [
                        'path' => is_string($item['path'] ?? null) ? trim((string) $item['path']) : '',
                        'mime_type' => is_string($item['mime_type'] ?? null) ? trim((string) $item['mime_type']) : '',
                    ];
                }
                return ['path' => '', 'mime_type' => ''];
            },
            $imagePaths
        ), static fn(array $item): bool => $item['path'] !== ''));

        if ($imagePaths === []) {
            return ['ok' => false, 'message' => 'At least one source image is required for Gemini generation.'];
        }

        $parts = [['text' => $prompt]];
        foreach ($imagePaths as $imageFile) {
            if (!is_file($imageFile['path'])) {
                return ['ok' => false, 'message' => 'A source image could not be found for Gemini generation.'];
            }
            $bytes = @file_get_contents($imageFile['path']);
            if (!is_string($bytes) || $bytes === '') {
                return ['ok' => false, 'message' => 'A source image could not be read for Gemini generation.'];
            }
            $mimeType = $imageFile['mime_type'] !== '' ? $imageFile['mime_type'] : elite_gemini_detect_image_mime_type($imageFile['path']);
            $parts[] = [
                'inlineData' => [
                    'mimeType' => $mimeType,
                    'data' => base64_encode($bytes),
                ],
            ];
        }

        $payload = [
            'contents' => [[
                'role' => 'user',
                'parts' => $parts,
            ]],
            'generationConfig' => [
                'responseModalities' => ['TEXT', 'IMAGE'],
            ],
        ];

        $model = trim((string) ($options['model'] ?? (defined('GOOGLE_GEMINI_IMAGE_MODEL') ? GOOGLE_GEMINI_IMAGE_MODEL : 'gemini-2.5-flash-image')));
        $apiKey = defined('GOOGLE_GEMINI_API_KEY') ? trim((string) GOOGLE_GEMINI_API_KEY) : '';
        if ($apiKey === '') {
            return ['ok' => false, 'message' => 'Google Gemini API key is missing.'];
        }
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);
        $raw = '';
        $curlError = '';
        $statusCode = 0;
        $encodedPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return ['ok' => false, 'message' => 'Could not initialize Gemini image request.'];
            }

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $encodedPayload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                ],
            ]);

            $raw = (string) curl_exec($ch);
            $curlError = curl_error($ch);
            $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => $encodedPayload,
                    'timeout' => 120,
                    'ignore_errors' => true,
                ],
            ]);
            $raw = (string) @file_get_contents($url, false, $context);
            $headers = $http_response_header ?? [];
            foreach ($headers as $header) {
                if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', (string) $header, $matches)) {
                    $statusCode = (int) $matches[1];
                    break;
                }
            }
            if ($raw === '' && $statusCode === 0) {
                $curlError = 'stream request failed';
            }
        }

        $decoded = $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            esm_log('gemini', 'Gemini response was not valid JSON.', [
                'status_code' => $statusCode,
                'curl_error' => $curlError,
            ]);
            return ['ok' => false, 'message' => 'Gemini returned an invalid response.'];
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            esm_log('gemini', 'Gemini request failed.', [
                'status_code' => $statusCode,
                'error' => $decoded['error']['message'] ?? $curlError,
            ]);
            return [
                'ok' => false,
                'message' => (string) ($decoded['error']['message'] ?? 'Gemini request failed.'),
                'status_code' => $statusCode,
                'response' => $decoded,
            ];
        }

        foreach (($decoded['candidates'] ?? []) as $candidate) {
            foreach (($candidate['content']['parts'] ?? []) as $part) {
                $inline = $part['inlineData'] ?? null;
                if (is_array($inline) && !empty($inline['data'])) {
                    return [
                        'ok' => true,
                        'provider' => 'google_gemini',
                        'image_base64' => (string) $inline['data'],
                        'mime_type' => (string) ($inline['mimeType'] ?? 'image/png'),
                        'response' => $decoded,
                        'request' => [
                            'model' => $model,
                            'prompt' => $prompt,
                        ],
                        'revised_prompt' => '',
                    ];
                }
            }
        }

        esm_log('gemini', 'Gemini returned no image part.', [
            'status_code' => $statusCode,
            'response' => $decoded,
        ]);
        return ['ok' => false, 'message' => 'Gemini did not return an image.', 'response' => $decoded];
    }
}
