<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * Small SMTP client for authenticated cPanel mailbox delivery.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

if (!function_exists('elite_smtp_is_configured')) {
    function elite_smtp_is_configured(): bool
    {
        return trim((string) SMTP_HOST) !== ''
            && trim((string) SMTP_USER) !== ''
            && trim((string) SMTP_PASS) !== ''
            && filter_var(SMTP_FROM_EMAIL, FILTER_VALIDATE_EMAIL);
    }
}

if (!function_exists('elite_smtp_read')) {
    function elite_smtp_read($stream): string
    {
        $response = '';
        while (!feof($stream)) {
            $line = fgets($stream, 515);
            if ($line === false) {
                break;
            }
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        return $response;
    }
}

if (!function_exists('elite_smtp_command')) {
    function elite_smtp_command($stream, string $command, array $expectedCodes): string
    {
        fwrite($stream, $command . "\r\n");
        $response = elite_smtp_read($stream);
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException('SMTP command failed: ' . trim($response));
        }
        return $response;
    }
}

if (!function_exists('elite_smtp_encode_header')) {
    function elite_smtp_encode_header(string $value): string
    {
        $value = trim(preg_replace('/[\r\n]+/', ' ', $value) ?? '');
        if ($value === '') {
            return '';
        }
        return preg_match('/[^\x20-\x7E]/', $value)
            ? '=?UTF-8?B?' . base64_encode($value) . '?='
            : $value;
    }
}

if (!function_exists('elite_smtp_mailbox')) {
    function elite_smtp_mailbox(string $email): string
    {
        return '<' . trim($email) . '>';
    }
}

if (!function_exists('elite_smtp_normalize_body')) {
    function elite_smtp_normalize_body(string $body, bool $dotStuff = true): string
    {
        $safeBody = str_replace(["\r\n", "\r"], "\n", trim($body));
        $safeBody = str_replace("\n", "\r\n", $safeBody);
        if ($dotStuff) {
            $safeBody = preg_replace('/^\./m', '..', $safeBody) ?? $safeBody;
        }
        return $safeBody;
    }
}

if (!function_exists('elite_smtp_message')) {
    function elite_smtp_message(string $to, string $subject, string $body, ?string $replyTo = null, string $htmlBody = '', array $extraHeaders = []): string
    {
        $fromName = elite_smtp_encode_header((string) SMTP_FROM_NAME);
        $fromEmail = (string) SMTP_FROM_EMAIL;
        $subject = elite_smtp_encode_header($subject);
        $messageIdHost = preg_replace('/^.*@/', '', $fromEmail) ?: 'elitesmilesutah.com';
        $messageId = sprintf('<%s.%s@%s>', bin2hex(random_bytes(8)), time(), $messageIdHost);
        $hasHtml = trim($htmlBody) !== '';
        $boundary = 'elite_boundary_' . bin2hex(random_bytes(12));

        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . ($fromName !== '' ? $fromName . ' ' : '') . elite_smtp_mailbox($fromEmail),
            'To: ' . elite_smtp_mailbox($to),
            'Subject: ' . $subject,
            'Message-ID: ' . $messageId,
            'MIME-Version: 1.0',
        ];

        if ($hasHtml) {
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: 8bit';
        }

        if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . elite_smtp_mailbox($replyTo);
        }

        foreach ($extraHeaders as $header) {
            $header = trim((string)$header);
            if ($header !== '' && !str_contains($header, "\r") && !str_contains($header, "\n")) {
                $headers[] = $header;
            }
        }

        if ($hasHtml) {
            $plain = elite_smtp_normalize_body($body, false);
            $html = elite_smtp_normalize_body($htmlBody, false);
            $content = '';
            $content .= '--' . $boundary . "\r\n";
            $content .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $content .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $content .= $plain . "\r\n\r\n";
            $content .= '--' . $boundary . "\r\n";
            $content .= "Content-Type: text/html; charset=UTF-8\r\n";
            $content .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $content .= $html . "\r\n\r\n";
            $content .= '--' . $boundary . "--";
            $content = preg_replace('/^\./m', '..', $content) ?? $content;
            return implode("\r\n", $headers) . "\r\n\r\n" . $content . "\r\n.";
        }

        return implode("\r\n", $headers) . "\r\n\r\n" . elite_smtp_normalize_body($body) . "\r\n.";
    }
}

if (!function_exists('elite_smtp_send_mail')) {
    function elite_smtp_send_mail(string $to, string $subject, string $body, ?string $replyTo = null, string $htmlBody = '', array $extraHeaders = []): array
    {
        $to = trim($to);
        $subject = trim($subject);
        $body = trim($body);

        if (!elite_smtp_is_configured()) {
            return ['ok' => false, 'message' => 'SMTP is not configured.'];
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Recipient email is invalid.'];
        }
        if ($subject === '' || $body === '') {
            return ['ok' => false, 'message' => 'Email subject and body are required.'];
        }

        $host = (string) SMTP_HOST;
        $port = (int) SMTP_PORT;
        $encryption = strtolower((string) SMTP_ENCRYPTION);
        $target = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;

        $stream = @stream_socket_client($target, $errno, $errstr, 25, STREAM_CLIENT_CONNECT);
        if (!is_resource($stream)) {
            return ['ok' => false, 'message' => 'SMTP connection failed: ' . $errstr];
        }

        stream_set_timeout($stream, 25);

        try {
            $greeting = elite_smtp_read($stream);
            if ((int) substr($greeting, 0, 3) !== 220) {
                throw new RuntimeException('SMTP greeting failed: ' . trim($greeting));
            }

            $localHost = $_SERVER['SERVER_NAME'] ?? 'hi.elitesmilesutah.com';
            elite_smtp_command($stream, 'EHLO ' . $localHost, [250]);

            if ($encryption === 'tls') {
                elite_smtp_command($stream, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Could not start SMTP TLS.');
                }
                elite_smtp_command($stream, 'EHLO ' . $localHost, [250]);
            }

            elite_smtp_command($stream, 'AUTH LOGIN', [334]);
            elite_smtp_command($stream, base64_encode((string) SMTP_USER), [334]);
            elite_smtp_command($stream, base64_encode((string) SMTP_PASS), [235]);
            elite_smtp_command($stream, 'MAIL FROM:' . elite_smtp_mailbox((string) SMTP_FROM_EMAIL), [250]);
            elite_smtp_command($stream, 'RCPT TO:' . elite_smtp_mailbox($to), [250, 251]);
            elite_smtp_command($stream, 'DATA', [354]);
            $response = elite_smtp_command($stream, elite_smtp_message($to, $subject, $body, $replyTo, $htmlBody, $extraHeaders), [250]);
            elite_smtp_command($stream, 'QUIT', [221]);
            fclose($stream);

            return ['ok' => true, 'message' => 'Email sent.', 'smtp_response' => trim($response)];
        } catch (Throwable $e) {
            if (is_resource($stream)) {
                @fwrite($stream, "QUIT\r\n");
                @fclose($stream);
            }
            esm_log('smtp', 'SMTP send failed.', [
                'to' => $to,
                'subject' => $subject,
                'message' => $e->getMessage(),
            ]);
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
