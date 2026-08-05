<?php
declare(strict_types=1);

if (!function_exists('meta_webhook_signature_matches')) {
    function meta_webhook_signature_matches(string $rawBody, string $signature, string $appSecret): bool
    {
        $signature = trim($signature);
        $appSecret = trim($appSecret);
        if ($rawBody === '' || $signature === '' || $appSecret === '') {
            return false;
        }
        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);
        return hash_equals($expected, $signature);
    }
}

if (!function_exists('meta_webhook_payload_valid')) {
    function meta_webhook_payload_valid(array $payload): bool
    {
        return meta_queue_extract_candidates($payload) !== [];
    }
}
