<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$options = [];
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--([a-z0-9_-]+)=(.*)$/i', $argument, $matches)) {
        $options[strtolower($matches[1])] = $matches[2];
    }
}

$credentialPath = (string)($options['credentials'] ?? dirname(__DIR__) . '/.secrets/codex-v1.json');
$credential = json_decode((string)@file_get_contents($credentialPath), true);
if (!is_array($credential) || empty($credential['token']) || empty($credential['endpoint'])) {
    fwrite(STDERR, "Valid Codex API credentials were not found.\n");
    exit(2);
}

$method = strtoupper((string)($options['method'] ?? 'GET'));
$action = trim((string)($options['action'] ?? 'health'));
$jsonFile = trim((string)($options['json-file'] ?? ''));
$json = $jsonFile !== '' ? trim((string)@file_get_contents($jsonFile)) : trim((string)($options['json'] ?? ''));
$body = $json !== '' ? json_decode($json, true) : [];
if (!is_array($body)) {
    fwrite(STDERR, "--json must contain a JSON object.\n");
    exit(2);
}
$body['action'] = $action;
$rawBody = $method === 'POST' ? json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
$endpoint = trim((string)($options['endpoint'] ?? $credential['endpoint']));
if ($method === 'GET') {
    $endpoint .= '?' . http_build_query(array_merge($body, ['action' => $action]));
}
$path = (string)(parse_url($endpoint, PHP_URL_PATH) ?: '/');
$query = (string)(parse_url($endpoint, PHP_URL_QUERY) ?: '');
$requestTarget = $path . ($query !== '' ? '?' . $query : '');
$timestamp = trim((string)($options['timestamp'] ?? '')) ?: (string)time();
$nonce = trim((string)($options['nonce'] ?? '')) ?: rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
$signaturePayload = $timestamp . "\n" . $nonce . "\n" . $method . "\n" . $requestTarget . "\n" . hash('sha256', $rawBody);
$signature = hash_hmac('sha256', $signaturePayload, (string)$credential['token']);

$headers = [
    'Authorization: Bearer ' . $credential['token'],
    'X-Elite-Timestamp: ' . $timestamp,
    'X-Elite-Nonce: ' . $nonce,
    'X-Elite-Signature: ' . $signature,
    'Accept: application/json',
];
if ($method === 'POST') {
    $headers[] = 'Content-Type: application/json';
    $headers[] = 'Idempotency-Key: ' . ($options['idempotency-key'] ?? rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '='));
}

if (function_exists('curl_init')) {
    $curl = curl_init($endpoint);
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HEADER => false,
    ]);
    if ($method === 'POST') {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $rawBody);
    }
    $response = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    if ($response === false) {
        fwrite(STDERR, 'Request failed: ' . curl_error($curl) . PHP_EOL);
        curl_close($curl);
        exit(1);
    }
    curl_close($curl);
} else {
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $method === 'POST' ? $rawBody : '',
            'ignore_errors' => true,
            'timeout' => 30,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $response = @file_get_contents($endpoint, false, $context);
    $status = 0;
    foreach (($http_response_header ?? []) as $responseHeader) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $responseHeader, $matches)) {
            $status = (int)$matches[1];
            break;
        }
    }
    if ($response === false) {
        fwrite(STDERR, "Request failed using the native HTTP transport.\n");
        exit(1);
    }
}
echo $response . PHP_EOL;
exit($status >= 200 && $status < 300 ? 0 : 1);
