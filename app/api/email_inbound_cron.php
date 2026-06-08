<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * Polls the patient mailbox for unread replies and logs them to matched leads.
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/leads/lead_communications.php';
require_once dirname(__DIR__) . '/leads/lead_email.php';

header('Content-Type: application/json; charset=utf-8');

$secret = trim((string) input('secret'));
if (ELITE_EMAIL_INBOUND_CRON_SECRET === '' || !hash_equals((string)ELITE_EMAIL_INBOUND_CRON_SECRET, $secret)) {
    json_response(['ok' => false, 'message' => 'Unauthorized.'], 401);
}

if (trim((string)IMAP_HOST) === '' || trim((string)IMAP_USER) === '' || trim((string)IMAP_PASS) === '') {
    json_response(['ok' => false, 'message' => 'IMAP is not configured.', 'handled' => 0], 503);
}

function elite_email_decode_header_value(string $value): string
{
    if (function_exists('imap_mime_header_decode')) {
        $parts = imap_mime_header_decode($value);
        $decoded = '';
        foreach ($parts ?: [] as $part) {
            $charset = strtoupper((string)($part->charset ?? 'UTF-8'));
            $text = (string)($part->text ?? '');
            if ($charset !== '' && $charset !== 'DEFAULT' && $charset !== 'UTF-8') {
                $converted = @iconv($charset, 'UTF-8//IGNORE', $text);
                $text = $converted !== false ? $converted : $text;
            }
            $decoded .= $text;
        }
        return trim($decoded);
    }

    if (function_exists('iconv_mime_decode')) {
        $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
        if (is_string($decoded) && trim($decoded) !== '') {
            return trim($decoded);
        }
    }

    return trim($value);
}

function elite_email_decode_body_value(string $body, string $encoding): string
{
    $encoding = strtolower(trim($encoding));
    if ($encoding === 'base64') {
        $decoded = base64_decode(preg_replace('/\s+/', '', $body) ?? '', true);
        return is_string($decoded) ? $decoded : $body;
    }
    if ($encoding === 'quoted-printable') {
        return quoted_printable_decode($body);
    }
    return $body;
}

function elite_email_extract_address(string $value): string
{
    if (preg_match('/<([^>]+)>/', $value, $matches)) {
        return strtolower(trim($matches[1]));
    }
    if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $matches)) {
        return strtolower(trim($matches[0]));
    }
    return strtolower(trim($value));
}

function elite_email_is_delivery_failure(string $fromEmail, string $subject, string $body): bool
{
    $haystack = strtolower($fromEmail . "\n" . $subject . "\n" . mb_substr($body, 0, 3000));

    return str_contains($haystack, 'mailer-daemon')
        || str_contains($haystack, 'mail delivery subsystem')
        || str_contains($haystack, 'delivery status notification')
        || str_contains($haystack, 'undelivered mail returned')
        || str_contains($haystack, 'delivery failure')
        || str_contains($haystack, '550-5.7.26')
        || str_contains($haystack, 'unauthenticated sender');
}

function elite_email_bounce_recipients(string $body): array
{
    preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $body, $matches);
    $addresses = array_map(static fn (string $email): string => strtolower(trim($email)), $matches[0] ?? []);
    $ownMailbox = strtolower(trim((string)IMAP_USER));

    return array_values(array_unique(array_filter($addresses, static function (string $email) use ($ownMailbox): bool {
        if ($email === '' || $email === $ownMailbox) {
            return false;
        }
        if (str_contains($email, 'mailer-daemon') || str_contains($email, 'postmaster')) {
            return false;
        }
        return true;
    })));
}

function elite_email_record_delivery_failure(string $fromEmail, string $subject, string $body, string $sourceId): array
{
    if (!elite_email_is_delivery_failure($fromEmail, $subject, $body)) {
        return ['handled' => false, 'matched' => 0, 'unmatched' => 0];
    }

    $matched = 0;
    $unmatched = 0;
    foreach (elite_email_bounce_recipients($body) as $recipient) {
        $result = lead_email_record_bounce($recipient, $subject, $body, $sourceId);
        if (!empty($result['ok'])) {
            $matched++;
        } else {
            $unmatched++;
        }
    }

    return ['handled' => true, 'matched' => $matched, 'unmatched' => $unmatched];
}

function elite_email_parse_raw_message(string $raw): array
{
    $raw = str_replace("\r\n", "\n", $raw);
    [$headerText, $body] = array_pad(explode("\n\n", $raw, 2), 2, '');
    $headerText = preg_replace("/\n[ \t]+/", ' ', $headerText) ?? $headerText;
    $headers = [];
    foreach (explode("\n", $headerText) as $line) {
        $pos = strpos($line, ':');
        if ($pos === false) {
            continue;
        }
        $name = strtolower(trim(substr($line, 0, $pos)));
        $headers[$name] = trim(substr($line, $pos + 1));
    }

    $contentType = strtolower((string)($headers['content-type'] ?? ''));
    $encoding = (string)($headers['content-transfer-encoding'] ?? '');
    $plainBody = $body;

    if (str_contains($contentType, 'multipart/') && preg_match('/boundary="?([^";]+)"?/i', $contentType, $matches)) {
        $boundary = $matches[1];
        $parts = explode('--' . $boundary, $body);
        $htmlCandidate = '';
        foreach ($parts as $part) {
            if (!str_contains($part, "\n\n")) {
                continue;
            }
            [$partHeaders, $partBody] = explode("\n\n", $part, 2);
            $partHeadersLower = strtolower($partHeaders);
            $partEncoding = '';
            if (preg_match('/content-transfer-encoding:\s*([^\s;]+)/i', $partHeaders, $encodingMatch)) {
                $partEncoding = $encodingMatch[1];
            }
            $decodedPart = elite_email_decode_body_value(trim($partBody), $partEncoding);
            if (str_contains($partHeadersLower, 'content-type: text/plain')) {
                $plainBody = $decodedPart;
                break;
            }
            if ($htmlCandidate === '' && str_contains($partHeadersLower, 'content-type: text/html')) {
                $htmlCandidate = trim(html_entity_decode(strip_tags($decodedPart), ENT_QUOTES, 'UTF-8'));
            }
        }
        if (trim($plainBody) === '' && $htmlCandidate !== '') {
            $plainBody = $htmlCandidate;
        }
    } else {
        $plainBody = elite_email_decode_body_value($body, $encoding);
        if (str_contains($contentType, 'text/html')) {
            $plainBody = html_entity_decode(strip_tags($plainBody), ENT_QUOTES, 'UTF-8');
        }
    }

    return [
        'from' => elite_email_extract_address((string)($headers['from'] ?? '')),
        'to' => elite_email_extract_address((string)($headers['to'] ?? IMAP_USER)),
        'subject' => elite_email_decode_header_value((string)($headers['subject'] ?? '')),
        'message_id' => trim((string)($headers['message-id'] ?? '')),
        'body' => trim($plainBody),
    ];
}

function elite_email_imap_mailbox(): string
{
    $flags = '/imap';
    if (IMAP_ENCRYPTION === 'ssl') {
        $flags .= '/ssl';
    } elseif (IMAP_ENCRYPTION === 'tls') {
        $flags .= '/tls';
    } else {
        $flags .= '/notls';
    }
    return '{' . IMAP_HOST . ':' . (int)IMAP_PORT . $flags . '}INBOX';
}

function elite_email_collect_parts($imap, int $msgNo, object $part, string $partNo = ''): array
{
    $text = '';
    $html = '';

    if (!empty($part->parts) && is_array($part->parts)) {
        foreach ($part->parts as $index => $subPart) {
            $subNo = $partNo === '' ? (string)($index + 1) : $partNo . '.' . ($index + 1);
            $child = elite_email_collect_parts($imap, $msgNo, $subPart, $subNo);
            $text .= $child['text'];
            $html .= $child['html'];
        }
        return ['text' => $text, 'html' => $html];
    }

    $body = imap_fetchbody($imap, $msgNo, $partNo !== '' ? $partNo : '1', FT_PEEK);
    if ($body === false || $body === '') {
        $body = imap_body($imap, $msgNo, FT_PEEK) ?: '';
    }
    $encoding = (int)($part->encoding ?? 0);
    if ($encoding === ENCBASE64) {
        $body = (string)base64_decode($body, true);
    } elseif ($encoding === ENCQUOTEDPRINTABLE) {
        $body = quoted_printable_decode($body);
    }
    $subtype = strtolower((string)($part->subtype ?? ''));
    if ($subtype === 'plain') {
        $text .= $body;
    } elseif ($subtype === 'html') {
        $html .= $body;
    }

    return ['text' => $text, 'html' => $html];
}

function elite_email_poll_with_php_imap(): array
{
    $imap = @imap_open(elite_email_imap_mailbox(), (string)IMAP_USER, (string)IMAP_PASS);
    if (!$imap) {
        return ['ok' => false, 'message' => 'IMAP connection failed: ' . (imap_last_error() ?: 'Unknown error'), 'handled' => 0, 'unmatched' => 0, 'errors' => []];
    }

    $handled = 0;
    $unmatched = 0;
    $errors = [];

    try {
        $messages = imap_search($imap, 'UNSEEN') ?: [];
        foreach ($messages as $msgNo) {
            $header = imap_headerinfo($imap, (int)$msgNo);
            $fromEmail = strtolower(trim((string)($header->from[0]->mailbox ?? '') . '@' . (string)($header->from[0]->host ?? ''), '@'));
            $toEmail = strtolower(trim((string)($header->to[0]->mailbox ?? '') . '@' . (string)($header->to[0]->host ?? ''), '@')) ?: (string)IMAP_USER;
            $subject = elite_email_decode_header_value((string)($header->subject ?? ''));
            $messageId = trim((string)($header->message_id ?? ''));
            $uid = imap_uid($imap, (int)$msgNo);

            $structure = imap_fetchstructure($imap, (int)$msgNo);
            $parts = $structure ? elite_email_collect_parts($imap, (int)$msgNo, $structure) : ['text' => imap_body($imap, (int)$msgNo, FT_PEEK) ?: '', 'html' => ''];
            $body = trim((string)$parts['text']);
            if ($body === '' && trim((string)$parts['html']) !== '') {
                $body = trim(html_entity_decode(strip_tags((string)$parts['html']), ENT_QUOTES, 'UTF-8'));
            }

            $sourceId = 'imap:' . $uid . ':' . $messageId;
            $bounce = elite_email_record_delivery_failure($fromEmail, $subject, $body, $sourceId);
            if (!empty($bounce['handled'])) {
                if ((int)($bounce['matched'] ?? 0) > 0) {
                    $handled += (int)$bounce['matched'];
                } else {
                    $unmatched++;
                    esm_log('lead_email', 'Delivery failure did not match a CRM lead.', ['from' => $fromEmail, 'subject' => $subject, 'uid' => $uid]);
                }
            } else {
                $result = lead_email_record_inbound($fromEmail, $toEmail, $subject, $body, $sourceId);
                if (!empty($result['ok'])) {
                    $handled++;
                } else {
                    $unmatched++;
                    esm_log('lead_email', 'Inbound email did not match a CRM lead.', ['from' => $fromEmail, 'subject' => $subject, 'uid' => $uid]);
                }
            }
            if (!empty($bounce['unmatched'])) {
                $unmatched += (int)$bounce['unmatched'];
            }
            imap_setflag_full($imap, (string)$msgNo, '\\Seen');
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
        esm_log('lead_email', 'Inbound email cron failed.', ['error' => $e->getMessage()]);
    } finally {
        imap_close($imap);
    }

    return ['ok' => count($errors) === 0, 'message' => 'Checked with PHP IMAP.', 'handled' => $handled, 'unmatched' => $unmatched, 'errors' => $errors];
}

function elite_email_socket_read_line($socket): string
{
    $line = fgets($socket, 8192);
    return is_string($line) ? $line : '';
}

function elite_email_socket_command($socket, string $tag, string $command): array
{
    fwrite($socket, $tag . ' ' . $command . "\r\n");
    $lines = [];
    while (!feof($socket)) {
        $line = elite_email_socket_read_line($socket);
        if ($line === '') {
            break;
        }
        $lines[] = $line;
        if (str_starts_with($line, $tag . ' ')) {
            break;
        }
    }
    return $lines;
}

function elite_email_socket_fetch_raw($socket, string $tag, string $uid): string
{
    fwrite($socket, $tag . ' UID FETCH ' . $uid . " BODY.PEEK[]\r\n");
    $raw = '';
    while (!feof($socket)) {
        $line = elite_email_socket_read_line($socket);
        if ($line === '') {
            break;
        }
        if (preg_match('/\{(\d+)\}\r?\n$/', $line, $matches)) {
            $length = (int)$matches[1];
            $remaining = $length;
            while ($remaining > 0 && !feof($socket)) {
                $chunk = fread($socket, min(8192, $remaining));
                if (!is_string($chunk) || $chunk === '') {
                    break;
                }
                $raw .= $chunk;
                $remaining -= strlen($chunk);
            }
            continue;
        }
        if (str_starts_with($line, $tag . ' ')) {
            break;
        }
    }
    return $raw;
}

function elite_email_poll_with_socket_imap(): array
{
    $transport = IMAP_ENCRYPTION === 'ssl' ? 'ssl://' : '';
    $socket = @fsockopen($transport . IMAP_HOST, (int)IMAP_PORT, $errno, $errstr, 20);
    if (!$socket) {
        return ['ok' => false, 'message' => 'Socket IMAP connection failed: ' . $errstr, 'handled' => 0, 'unmatched' => 0, 'errors' => [$errstr]];
    }
    stream_set_timeout($socket, 30);
    elite_email_socket_read_line($socket);

    $login = elite_email_socket_command($socket, 'A001', 'LOGIN "' . addcslashes((string)IMAP_USER, "\\\"") . '" "' . addcslashes((string)IMAP_PASS, "\\\"") . '"');
    if (!preg_grep('/^A001 OK/i', $login)) {
        fclose($socket);
        return ['ok' => false, 'message' => 'Socket IMAP login failed.', 'handled' => 0, 'unmatched' => 0, 'errors' => ['login failed']];
    }

    elite_email_socket_command($socket, 'A002', 'SELECT INBOX');
    $search = elite_email_socket_command($socket, 'A003', 'UID SEARCH UNSEEN');
    $uids = [];
    foreach ($search as $line) {
        if (preg_match('/^\* SEARCH\s*(.*)$/i', trim($line), $matches)) {
            $uids = array_values(array_filter(preg_split('/\s+/', trim($matches[1])) ?: []));
        }
    }

    $handled = 0;
    $unmatched = 0;
    $errors = [];
    $counter = 4;
    foreach ($uids as $uid) {
        $raw = elite_email_socket_fetch_raw($socket, 'A' . str_pad((string)$counter++, 3, '0', STR_PAD_LEFT), $uid);
        $parsed = elite_email_parse_raw_message($raw);
        $sourceId = 'imap-socket:' . $uid . ':' . $parsed['message_id'];
        $bounce = elite_email_record_delivery_failure($parsed['from'], $parsed['subject'], $parsed['body'], $sourceId);
        if (!empty($bounce['handled'])) {
            if ((int)($bounce['matched'] ?? 0) > 0) {
                $handled += (int)$bounce['matched'];
            } else {
                $unmatched++;
                esm_log('lead_email', 'Delivery failure did not match a CRM lead.', ['from' => $parsed['from'], 'subject' => $parsed['subject'], 'uid' => $uid]);
            }
        } else {
            $result = lead_email_record_inbound($parsed['from'], $parsed['to'] ?: (string)IMAP_USER, $parsed['subject'], $parsed['body'], $sourceId);
            if (!empty($result['ok'])) {
                $handled++;
            } else {
                $unmatched++;
                esm_log('lead_email', 'Inbound email did not match a CRM lead.', ['from' => $parsed['from'], 'subject' => $parsed['subject'], 'uid' => $uid]);
            }
        }
        if (!empty($bounce['unmatched'])) {
            $unmatched += (int)$bounce['unmatched'];
        }
        elite_email_socket_command($socket, 'A' . str_pad((string)$counter++, 3, '0', STR_PAD_LEFT), 'UID STORE ' . $uid . ' +FLAGS.SILENT (\\Seen)');
    }

    elite_email_socket_command($socket, 'A999', 'LOGOUT');
    fclose($socket);

    return ['ok' => count($errors) === 0, 'message' => 'Checked with socket IMAP fallback.', 'handled' => $handled, 'unmatched' => $unmatched, 'errors' => $errors];
}

$result = function_exists('imap_open')
    ? elite_email_poll_with_php_imap()
    : elite_email_poll_with_socket_imap();

$intakeSecret = trim((string)(defined('ELITE_EMAIL_INBOUND_CRON_SECRET') ? ELITE_EMAIL_INBOUND_CRON_SECRET : ''));
if ($intakeSecret !== '') {
    $intakeUrl = base_url('app/api/intake_refresh_cron.php?secret=' . rawurlencode($intakeSecret));
    $intakeRaw = @file_get_contents($intakeUrl, false, stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 25,
            'ignore_errors' => true,
        ],
    ]));
    $intakeData = is_string($intakeRaw) ? json_decode($intakeRaw, true) : null;
    $result['intake_refresh'] = is_array($intakeData)
        ? $intakeData
        : ['ok' => false, 'message' => 'Intake refresh runner did not return JSON.'];
}

$reminderSecret = trim((string)(defined('ELITE_CONSULTATION_REMINDER_CRON_SECRET') ? ELITE_CONSULTATION_REMINDER_CRON_SECRET : ''));
if ($reminderSecret !== '') {
    $reminderUrl = base_url('app/api/consultation_reminder_cron.php?secret=' . rawurlencode($reminderSecret));
    $reminderRaw = @file_get_contents($reminderUrl, false, stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 25,
            'ignore_errors' => true,
        ],
    ]));
    $reminderData = is_string($reminderRaw) ? json_decode($reminderRaw, true) : null;
    $result['consultation_reminders'] = is_array($reminderData)
        ? $reminderData
        : ['ok' => false, 'message' => 'Consultation reminder runner did not return JSON.'];
}

json_response($result, !empty($result['ok']) ? 200 : 502);
