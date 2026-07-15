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

        $model = trim((string) ($options['model'] ?? (defined('GOOGLE_GEMINI_IMAGE_MODEL') ? GOOGLE_GEMINI_IMAGE_MODEL : 'gemini-3.1-flash-image')));
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

if (!function_exists('elite_gemini_http_json')) {
    function elite_gemini_http_json(string $url, array $payload = [], string $method = 'POST', int $timeout = 120): array
    {
        $apiKey = defined('GOOGLE_GEMINI_API_KEY') ? trim((string) GOOGLE_GEMINI_API_KEY) : '';
        if ($apiKey === '') {
            return ['ok' => false, 'message' => 'Google Gemini API key is missing.'];
        }

        $raw = '';
        $curlError = '';
        $statusCode = 0;
        $encodedPayload = $payload !== [] ? json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return ['ok' => false, 'message' => 'Could not initialize Gemini request.'];
            }
            $headers = ['Content-Type: application/json', 'x-goog-api-key: ' . $apiKey];
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            if ($encodedPayload !== '') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedPayload);
            }
            $raw = (string) curl_exec($ch);
            $curlError = curl_error($ch);
            $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => $method,
                    'header' => "Content-Type: application/json\r\nx-goog-api-key: {$apiKey}\r\n",
                    'content' => $encodedPayload,
                    'timeout' => $timeout,
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
            esm_log('gemini', 'Gemini JSON response was invalid.', [
                'status_code' => $statusCode,
                'curl_error' => $curlError,
            ]);
            return ['ok' => false, 'message' => 'Gemini returned an invalid response.', 'status_code' => $statusCode, 'raw' => $raw];
        }
        if ($statusCode < 200 || $statusCode >= 300) {
            esm_log('gemini', 'Gemini JSON request failed.', [
                'status_code' => $statusCode,
                'error' => $decoded['error']['message'] ?? $curlError,
            ]);
            return [
                'ok' => false,
                'message' => (string)($decoded['error']['message'] ?? 'Gemini request failed.'),
                'status_code' => $statusCode,
                'response' => $decoded,
            ];
        }

        return ['ok' => true, 'status_code' => $statusCode, 'response' => $decoded];
    }
}

if (!function_exists('elite_gemini_extract_text')) {
    function elite_gemini_extract_text(array $response): string
    {
        $parts = [];
        foreach (($response['candidates'] ?? []) as $candidate) {
            foreach (($candidate['content']['parts'] ?? []) as $part) {
                if (isset($part['text']) && is_string($part['text'])) {
                    $parts[] = trim($part['text']);
                }
            }
        }

        return trim(implode("\n", array_filter($parts, static fn ($part): bool => $part !== '')));
    }
}

if (!function_exists('elite_gemini_json_response')) {
    function elite_gemini_json_response(string $systemPrompt, string $userPrompt, array $schema, string $schemaName, ?string $model = null): array
    {
        if (!elite_gemini_is_configured()) {
            return ['ok' => false, 'message' => 'Google Gemini is not configured.'];
        }

        $model = trim((string) ($model ?? (defined('GOOGLE_GEMINI_TEXT_MODEL') ? GOOGLE_GEMINI_TEXT_MODEL : 'gemini-2.5-flash')));
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
        $payload = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemPrompt],
                ],
            ],
            'contents' => [[
                'role' => 'user',
                'parts' => [
                    ['text' => $userPrompt],
                ],
            ]],
            'generationConfig' => [
                'temperature' => 0.2,
                'responseMimeType' => 'application/json',
                'responseSchema' => $schema,
            ],
        ];

        $result = elite_gemini_http_json($url, $payload, 'POST', 60);
        if (empty($result['ok'])) {
            return $result;
        }

        $response = (array) ($result['response'] ?? []);
        $outputText = elite_gemini_extract_text($response);
        $json = $outputText !== '' ? json_decode($outputText, true) : null;
        if (!is_array($json)) {
            esm_log('gemini', 'Gemini structured output could not be parsed.', [
                'schema' => $schemaName,
                'model' => $model,
            ]);
            return ['ok' => false, 'message' => 'Gemini output could not be parsed.'];
        }

        return [
            'ok' => true,
            'data' => $json,
            'provider' => 'google_gemini',
            'model' => $model,
            'status_code' => (int) ($result['status_code'] ?? 200),
            'response' => $response,
        ];
    }
}

if (!function_exists('elite_gemini_download_video_bytes')) {
    function elite_gemini_download_video_bytes(string $uri, int $timeout = 180): array
    {
        $apiKey = defined('GOOGLE_GEMINI_API_KEY') ? trim((string) GOOGLE_GEMINI_API_KEY) : '';
        if ($apiKey === '') {
            return ['ok' => false, 'message' => 'Google Gemini API key is missing.'];
        }

        $url = $uri;
        if (!preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://generativelanguage.googleapis.com/v1beta/' . ltrim($url, '/');
        }
        if (!str_contains($url, 'key=')) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'key=' . rawurlencode($apiKey);
        }
        $raw = '';
        $statusCode = 0;
        $curlError = '';
        $contentType = 'video/mp4';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return ['ok' => false, 'message' => 'Could not initialize Gemini video download.'];
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_HTTPHEADER => ['x-goog-api-key: ' . $apiKey],
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
            ]);
            $raw = (string) curl_exec($ch);
            $curlError = curl_error($ch);
            $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $contentType = (string)(curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'video/mp4');
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "x-goog-api-key: {$apiKey}\r\n",
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                ],
            ]);
            $raw = (string) @file_get_contents($url, false, $context);
            $headers = $http_response_header ?? [];
            foreach ($headers as $header) {
                if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', (string) $header, $matches)) {
                    $statusCode = (int) $matches[1];
                } elseif (stripos((string)$header, 'Content-Type:') === 0) {
                    $contentType = trim(substr((string)$header, strlen('Content-Type:'))) ?: $contentType;
                }
            }
        }

        if ($statusCode < 200 || $statusCode >= 300 || $raw === '') {
            return ['ok' => false, 'message' => $curlError !== '' ? $curlError : 'Gemini video download failed.', 'status_code' => $statusCode];
        }

        return ['ok' => true, 'binary' => $raw, 'mime_type' => str_contains($contentType, 'video/') ? $contentType : 'video/mp4', 'status_code' => $statusCode];
    }
}

if (!function_exists('elite_gemini_find_video_payload')) {
    function elite_gemini_video_empty_message(array $response): string
    {
        $blockedReasons = [];
        $nestedKeys = [];
        $stack = [$response];
        while ($stack !== []) {
            $node = array_pop($stack);
            if (!is_array($node)) {
                continue;
            }
            foreach (array_keys($node) as $key) {
                if (is_string($key) && preg_match('/video|sample|file|media|uri|url/i', $key)) {
                    $nestedKeys[] = $key;
                }
            }
            $raiReasons = $node['raiMediaFilteredReasons'] ?? $node['rai_media_filtered_reasons'] ?? null;
            if (is_array($raiReasons)) {
                foreach ($raiReasons as $reason) {
                    if (is_string($reason) && trim($reason) !== '') {
                        $blockedReasons[] = trim($reason);
                    } elseif (is_array($reason)) {
                        foreach (['reason', 'message', 'category'] as $reasonKey) {
                            if (isset($reason[$reasonKey]) && is_string($reason[$reasonKey]) && trim($reason[$reasonKey]) !== '') {
                                $blockedReasons[] = trim($reason[$reasonKey]);
                            }
                        }
                    }
                }
            } elseif (is_string($raiReasons) && trim($raiReasons) !== '') {
                $blockedReasons[] = trim($raiReasons);
            }
            foreach (['raiMediaFilteredReason', 'finishReason', 'blockReason', 'reason', 'message'] as $key) {
                if (isset($node[$key]) && is_string($node[$key]) && trim($node[$key]) !== '') {
                    $blockedReasons[] = trim($node[$key]);
                }
            }
            foreach ($node as $child) {
                if (is_array($child)) {
                    $stack[] = $child;
                }
            }
        }

        $blockedReasons = array_values(array_unique($blockedReasons));
        if ($blockedReasons !== []) {
            return 'Gemini finished the video operation but did not return a downloadable video. Provider detail: ' . implode(' · ', array_slice($blockedReasons, 0, 4)) . '.';
        }

        $topLevelKeys = implode(', ', array_slice(array_keys($response), 0, 8));
        $nestedKeys = implode(', ', array_slice(array_values(array_unique($nestedKeys)), 0, 12));
        return 'Gemini finished the video operation but did not return a downloadable video. Response keys: ' . ($topLevelKeys !== '' ? $topLevelKeys : 'none') . ($nestedKeys !== '' ? '. Nested video keys: ' . $nestedKeys : '') . '.';
    }

    function elite_gemini_find_video_payload(array $response): array
    {
        $videoNodes = [];

        $collectVideoNodes = static function ($node) use (&$collectVideoNodes, &$videoNodes): void {
            if (!is_array($node)) {
                return;
            }

            if (isset($node['video'])) {
                if (is_array($node['video'])) {
                    $videoNodes[] = $node['video'];
                } elseif (is_string($node['video']) && trim($node['video']) !== '') {
                    $videoNodes[] = ['uri' => trim($node['video'])];
                }
            }

            foreach (['generatedVideos', 'generated_videos', 'generatedSamples', 'generated_samples', 'samples'] as $key) {
                if (!is_array($node[$key] ?? null)) {
                    continue;
                }
                foreach ($node[$key] as $generatedVideo) {
                    if (!is_array($generatedVideo)) {
                        continue;
                    }
                    if (isset($generatedVideo['video'])) {
                        if (is_array($generatedVideo['video'])) {
                            $videoNodes[] = $generatedVideo['video'];
                        } elseif (is_string($generatedVideo['video']) && trim($generatedVideo['video']) !== '') {
                            $videoNodes[] = ['uri' => trim($generatedVideo['video'])];
                        }
                    }
                    $collectVideoNodes($generatedVideo);
                }
            }

            foreach (['generateVideoResponse', 'generate_video_response', 'response'] as $key) {
                if (is_array($node[$key] ?? null)) {
                    $collectVideoNodes($node[$key]);
                }
            }

            foreach ($node as $key => $child) {
                if (is_array($child) && is_string($key) && preg_match('/video|file|media/i', $key)) {
                    $collectVideoNodes($child);
                }
            }
        };

        $collectVideoNodes($response);

        foreach ($videoNodes as $videoNode) {
            foreach (['bytesBase64Encoded', 'videoBytes', 'video_bytes', 'data'] as $key) {
                if (isset($videoNode[$key]) && is_string($videoNode[$key]) && $videoNode[$key] !== '') {
                    $binary = base64_decode($videoNode[$key], true);
                    if (is_string($binary) && $binary !== '') {
                        return ['ok' => true, 'binary' => $binary, 'mime_type' => (string)($videoNode['mimeType'] ?? 'video/mp4')];
                    }
                }
            }
            foreach (['uri', 'fileUri', 'file_uri', 'downloadUri', 'download_uri', 'mediaUrl', 'media_url', 'url', 'gcsUri', 'gcs_uri', 'name'] as $key) {
                if (isset($videoNode[$key]) && is_string($videoNode[$key]) && $videoNode[$key] !== '') {
                    $download = elite_gemini_download_video_bytes($videoNode[$key]);
                    if (!empty($download['ok'])) {
                        return $download;
                    }
                }
            }
        }

        $stack = [$response];
        while ($stack !== []) {
            $node = array_pop($stack);
            if (!is_array($node)) {
                continue;
            }
            $inlineData = $node['inlineData'] ?? null;
            if (is_array($inlineData) && isset($inlineData['data']) && is_string($inlineData['data']) && $inlineData['data'] !== '') {
                $mimeType = (string)($inlineData['mimeType'] ?? '');
                if ($mimeType === '' || str_starts_with($mimeType, 'video/')) {
                    $binary = base64_decode($inlineData['data'], true);
                    if (is_string($binary) && $binary !== '') {
                        return ['ok' => true, 'binary' => $binary, 'mime_type' => $mimeType !== '' ? $mimeType : 'video/mp4'];
                    }
                }
            }
            foreach (['bytesBase64Encoded', 'videoBytes', 'video_bytes'] as $key) {
                if (isset($node[$key]) && is_string($node[$key]) && $node[$key] !== '') {
                    $binary = base64_decode($node[$key], true);
                    if (is_string($binary) && $binary !== '') {
                        return ['ok' => true, 'binary' => $binary, 'mime_type' => (string)($node['mimeType'] ?? 'video/mp4')];
                    }
                }
            }
            foreach (['uri', 'fileUri', 'file_uri', 'downloadUri', 'download_uri', 'mediaUrl', 'media_url', 'url', 'gcsUri', 'gcs_uri'] as $key) {
                if (isset($node[$key]) && is_string($node[$key]) && $node[$key] !== '') {
                    $download = elite_gemini_download_video_bytes($node[$key]);
                    if (!empty($download['ok'])) {
                        return $download;
                    }
                }
            }
            foreach ($node as $child) {
                if (is_array($child)) {
                    $stack[] = $child;
                }
            }
        }

        return ['ok' => false, 'message' => elite_gemini_video_empty_message($response)];
    }
}

if (!function_exists('elite_gemini_generate_video_from_references')) {
    function elite_gemini_generate_video_from_references(array $imagePaths, string $prompt, array $options = []): array
    {
        if (!elite_gemini_is_configured()) {
            return ['ok' => false, 'message' => 'Google Gemini is not configured.'];
        }

        $references = [];
        foreach ($imagePaths as $imageFile) {
            $path = is_array($imageFile) ? trim((string)($imageFile['path'] ?? '')) : trim((string)$imageFile);
            if ($path === '' || !is_file($path)) {
                continue;
            }
            $bytes = @file_get_contents($path);
            if (!is_string($bytes) || $bytes === '') {
                continue;
            }
            $mimeType = is_array($imageFile) && !empty($imageFile['mime_type'])
                ? (string)$imageFile['mime_type']
                : elite_gemini_detect_image_mime_type($path);
            $references[] = [
                'image' => [
                    'bytesBase64Encoded' => base64_encode($bytes),
                    'mimeType' => $mimeType,
                ],
                'referenceType' => 'asset',
            ];
        }
        if ($references === []) {
            return ['ok' => false, 'message' => 'At least one reference image is required for video generation.'];
        }

        $model = trim((string)($options['model'] ?? (defined('GOOGLE_GEMINI_VIDEO_MODEL') ? GOOGLE_GEMINI_VIDEO_MODEL : 'veo-3.1-generate-preview')));
        $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model);
        $instance = [
            'prompt' => $prompt,
            'referenceImages' => array_slice($references, 0, 3),
        ];
        $payload = [
            'instances' => [$instance],
            'parameters' => [
                'aspectRatio' => (string)($options['aspect_ratio'] ?? '16:9'),
                'durationSeconds' => (int)($options['duration_seconds'] ?? 8),
                'sampleCount' => 1,
                'personGeneration' => (string)($options['person_generation'] ?? 'allow_adult'),
            ],
        ];
        if (array_key_exists('generate_audio', $options)) {
            $payload['parameters']['generateAudio'] = (bool)$options['generate_audio'];
        }
        if (!empty($options['resolution'])) {
            $payload['parameters']['resolution'] = (string)$options['resolution'];
        }

        $start = elite_gemini_http_json($baseUrl . ':predictLongRunning', $payload, 'POST', 180);
        if (empty($start['ok'])) {
            return $start + ['provider' => 'google_gemini', 'model' => $model];
        }
        $operation = $start['response'] ?? [];
        $operationName = (string)($operation['name'] ?? '');
        if ($operationName === '') {
            return ['ok' => false, 'provider' => 'google_gemini', 'model' => $model, 'message' => 'Gemini did not return a video operation name.', 'response' => $operation];
        }

        $maxWait = (int)($options['max_wait_seconds'] ?? 420);
        $deadline = time() + max(60, $maxWait);
        $poll = $operation;
        while (time() < $deadline) {
            if (!empty($poll['done'])) {
                if (isset($poll['error'])) {
                    return ['ok' => false, 'provider' => 'google_gemini', 'model' => $model, 'message' => (string)($poll['error']['message'] ?? 'Gemini video generation failed.'), 'response' => $poll];
                }
                $video = elite_gemini_find_video_payload($poll['response'] ?? $poll);
                if (!empty($video['ok'])) {
                    return [
                        'ok' => true,
                        'provider' => 'google_gemini',
                        'model' => $model,
                        'video_binary' => (string)$video['binary'],
                        'mime_type' => (string)($video['mime_type'] ?? 'video/mp4'),
                        'operation_name' => $operationName,
                        'request' => ['model' => $model, 'prompt' => $prompt],
                        'response' => $poll,
                    ];
                }
                return $video + ['provider' => 'google_gemini', 'model' => $model, 'operation_name' => $operationName, 'response' => $poll];
            }

            sleep(8);
            $status = elite_gemini_http_json('https://generativelanguage.googleapis.com/v1beta/' . ltrim($operationName, '/'), [], 'GET', 60);
            if (empty($status['ok'])) {
                return $status + ['provider' => 'google_gemini', 'model' => $model, 'operation_name' => $operationName];
            }
            $poll = $status['response'] ?? [];
        }

        return ['ok' => false, 'provider' => 'google_gemini', 'model' => $model, 'operation_name' => $operationName, 'message' => 'Video generation is still processing. Try again later.'];
    }
}
