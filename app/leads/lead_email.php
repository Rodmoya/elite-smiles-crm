<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * Patient email helpers for SMTP follow-up while SMS is pending.
 */

require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/mailer.php';
require_once dirname(__DIR__) . '/core/smtp.php';
require_once __DIR__ . '/lead_meta.php';
require_once __DIR__ . '/lead_language.php';
require_once __DIR__ . '/lead_agent_observability.php';

if (!function_exists('lead_email_ensure_schema')) {
    function lead_email_ensure_schema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        try {
            db_query("
                CREATE TABLE IF NOT EXISTS lead_emails (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    lead_id INT UNSIGNED NOT NULL,
                    direction VARCHAR(20) NOT NULL DEFAULT 'outbound',
                    from_email VARCHAR(255) NOT NULL DEFAULT '',
                    to_email VARCHAR(255) NOT NULL DEFAULT '',
                    subject VARCHAR(255) NOT NULL DEFAULT '',
                    body MEDIUMTEXT NOT NULL,
                    body_html MEDIUMTEXT NULL,
                    status VARCHAR(50) NOT NULL DEFAULT 'sent',
                    tracking_token VARCHAR(100) NOT NULL DEFAULT '',
                    provider_response TEXT NULL,
                    created_by VARCHAR(190) NOT NULL DEFAULT '',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    opened_at DATETIME NULL,
                    PRIMARY KEY (id),
                    KEY idx_lead_created (lead_id, created_at),
                    KEY idx_to_email (to_email),
                    KEY idx_tracking_token (tracking_token),
                    KEY idx_status_created (status, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            lead_email_add_column('lead_emails', 'tracking_token', "VARCHAR(100) NOT NULL DEFAULT ''");
            lead_email_add_column('lead_emails', 'opened_at', 'DATETIME NULL');
            lead_email_add_column('lead_emails', 'body_html', 'MEDIUMTEXT NULL AFTER body');
            lead_email_add_column('leads', 'email_opt_status', "VARCHAR(30) NOT NULL DEFAULT 'subscribed'");
            lead_email_add_column('leads', 'email_opted_out_at', 'DATETIME NULL');
        } catch (Throwable $e) {
            esm_log('lead_email', 'Could not ensure lead_emails table.', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

if (!function_exists('lead_email_decode_transfer_body')) {
    function lead_email_decode_transfer_body(string $body, string $encoding): string
    {
        $encoding = strtolower(trim($encoding));
        if ($encoding === 'base64') {
            $decoded = base64_decode(preg_replace('/\s+/', '', $body) ?? '', true);
            return is_string($decoded) ? $decoded : $body;
        }
        return $encoding === 'quoted-printable' ? quoted_printable_decode($body) : $body;
    }
}

if (!function_exists('lead_email_parse_header_block')) {
    function lead_email_parse_header_block(string $headerText): array
    {
        $headerText = preg_replace("/\n[ \t]+/", ' ', str_replace("\r\n", "\n", $headerText)) ?? $headerText;
        $headers = [];
        foreach (explode("\n", $headerText) as $line) {
            $position = strpos($line, ':');
            if ($position === false) {
                continue;
            }
            $headers[strtolower(trim(substr($line, 0, $position)))] = trim(substr($line, $position + 1));
        }
        return $headers;
    }
}

if (!function_exists('lead_email_html_to_text')) {
    function lead_email_html_to_text(string $html): string
    {
        $html = preg_replace('/<(?:br|\/p|\/div|\/li|\/tr|\/h[1-6]|\/blockquote)\b[^>]*>/i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        return trim($text);
    }
}

if (!function_exists('lead_email_plain_to_html')) {
    function lead_email_plain_to_html(string $text): string
    {
        return '<div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.6;color:#334155;white-space:pre-wrap;">'
            . nl2br(htmlspecialchars(trim($text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false)
            . '</div>';
    }
}

if (!function_exists('lead_email_parse_mime_entity')) {
    function lead_email_parse_mime_entity(string $raw, int $depth = 0): array
    {
        if ($depth > 8) {
            return ['text' => trim($raw), 'html' => ''];
        }
        $raw = str_replace("\r\n", "\n", $raw);
        [$headerText, $body] = array_pad(explode("\n\n", $raw, 2), 2, '');
        $headers = lead_email_parse_header_block($headerText);
        if ($headers === [] && $body === '') {
            return ['text' => trim($raw), 'html' => ''];
        }

        $contentType = strtolower((string)($headers['content-type'] ?? 'text/plain'));
        $encoding = (string)($headers['content-transfer-encoding'] ?? '');
        if (str_contains($contentType, 'multipart/') && preg_match('/boundary\s*=\s*(?:"([^"]+)"|([^;\s]+))/i', $contentType, $matches)) {
            $boundary = (string)($matches[1] !== '' ? $matches[1] : ($matches[2] ?? ''));
            $segments = preg_split('/^--' . preg_quote($boundary, '/') . '(?:--)?[ \t]*$/m', $body) ?: [];
            $texts = [];
            $htmls = [];
            foreach ($segments as $segment) {
                $segment = trim($segment, "\n\r-");
                if ($segment === '' || !str_contains($segment, "\n\n")) {
                    continue;
                }
                $parsed = lead_email_parse_mime_entity($segment, $depth + 1);
                if (trim((string)($parsed['text'] ?? '')) !== '') {
                    $texts[] = trim((string)$parsed['text']);
                }
                if (trim((string)($parsed['html'] ?? '')) !== '') {
                    $htmls[] = trim((string)$parsed['html']);
                }
            }
            return [
                'text' => trim(implode("\n\n", $texts)),
                'html' => trim(implode("\n", $htmls)),
            ];
        }

        $decoded = lead_email_decode_transfer_body($body, $encoding);
        if (preg_match('/charset\s*=\s*(?:"([^"]+)"|([^;\s]+))/i', $contentType, $charsetMatch)) {
            $charset = strtoupper(trim((string)($charsetMatch[1] !== '' ? $charsetMatch[1] : ($charsetMatch[2] ?? ''))));
            if ($charset !== '' && $charset !== 'UTF-8' && function_exists('iconv')) {
                $converted = @iconv($charset, 'UTF-8//IGNORE', $decoded);
                $decoded = is_string($converted) ? $converted : $decoded;
            }
        }
        if (str_contains($contentType, 'text/html')) {
            return ['text' => lead_email_html_to_text($decoded), 'html' => trim($decoded)];
        }
        if (str_contains($contentType, 'message/rfc822')) {
            return lead_email_parse_mime_entity($decoded, $depth + 1);
        }
        return ['text' => trim($decoded), 'html' => ''];
    }
}

if (!function_exists('lead_email_parse_mime_body')) {
    function lead_email_parse_mime_body(string $body): array
    {
        $normalized = str_replace("\r\n", "\n", trim($body));
        if (preg_match('/^--([^\n]+)\nContent-Type:/i', $normalized, $matches)) {
            $boundary = trim((string)$matches[1]);
            $normalized = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"' . "\n\n" . $normalized;
        }
        if (preg_match('/^(?:Content-Type|MIME-Version|From|To|Subject):/i', $normalized)) {
            return lead_email_parse_mime_entity($normalized);
        }
        if (preg_match('/<\/?(?:html|body|table|div|p|blockquote)\b/i', $normalized)) {
            return ['text' => lead_email_html_to_text($normalized), 'html' => $normalized];
        }
        return ['text' => $normalized, 'html' => ''];
    }
}

if (!function_exists('lead_email_sanitize_display_html')) {
    function lead_email_sanitize_display_html(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        if (preg_match('/<body\b[^>]*>(.*)<\/body>/is', $html, $bodyMatch)) {
            $html = (string)$bodyMatch[1];
        }
        if (!class_exists('DOMDocument')) {
            return lead_email_plain_to_html(lead_email_html_to_text($html));
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div id="lead-email-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $document->getElementById('lead-email-root');
        if (!$root) {
            return lead_email_plain_to_html(lead_email_html_to_text($html));
        }

        $allowedTags = array_fill_keys(['div', 'span', 'p', 'br', 'table', 'tbody', 'thead', 'tfoot', 'tr', 'td', 'th', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'hr', 'a', 'img'], true);
        $allowedAttributes = array_fill_keys(['style', 'href', 'src', 'alt', 'title', 'width', 'height', 'align', 'role', 'cellspacing', 'cellpadding', 'colspan', 'rowspan', 'target'], true);
        $forbiddenTags = array_fill_keys(['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'option', 'link', 'meta', 'base', 'svg', 'video', 'audio', 'canvas'], true);
        $elements = [];
        foreach ($root->getElementsByTagName('*') as $element) {
            $elements[] = $element;
        }
        foreach (array_reverse($elements) as $element) {
            $tag = strtolower($element->nodeName);
            if (isset($forbiddenTags[$tag])) {
                $element->parentNode?->removeChild($element);
                continue;
            }
            if (!isset($allowedTags[$tag])) {
                $parent = $element->parentNode;
                if ($parent) {
                    while ($element->firstChild) {
                        $parent->insertBefore($element->firstChild, $element);
                    }
                    $parent->removeChild($element);
                }
                continue;
            }
            foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
                $name = strtolower($attribute->nodeName);
                if (!isset($allowedAttributes[$name]) || str_starts_with($name, 'on')) {
                    $element->removeAttribute($attribute->nodeName);
                }
            }
            if ($element->hasAttribute('style')) {
                $style = (string)$element->getAttribute('style');
                $style = preg_replace('/(?:url\s*\(|expression\s*\(|@import|behavior\s*:|position\s*:\s*(?:fixed|sticky)|z-index\s*:)[^;]*/i', '', $style) ?? '';
                $element->setAttribute('style', trim($style));
            }
            if ($tag === 'a' && $element->hasAttribute('href')) {
                $href = trim((string)$element->getAttribute('href'));
                if (!preg_match('#^(?:https?://|mailto:)#i', $href) || preg_match('/(?:email_open|unsubscribe)/i', $href)) {
                    $element->removeAttribute('href');
                } else {
                    $element->setAttribute('target', '_blank');
                    $element->setAttribute('rel', 'noopener noreferrer');
                }
            }
            if ($tag === 'img') {
                $src = trim((string)$element->getAttribute('src'));
                $width = (int)$element->getAttribute('width');
                $height = (int)$element->getAttribute('height');
                if (($width > 0 && $width <= 2) || ($height > 0 && $height <= 2) || !preg_match('#^(?:https://hi\.elitesmilesutah\.com/|data:image/(?:png|jpe?g|gif|webp);base64,)#i', $src)) {
                    $element->parentNode?->removeChild($element);
                }
            }
        }

        $safe = '';
        foreach ($root->childNodes as $child) {
            $safe .= $document->saveHTML($child);
        }
        return trim($safe);
    }
}

if (!function_exists('lead_email_prepare_content')) {
    function lead_email_prepare_content(string $body, string $bodyHtml = ''): array
    {
        $parsed = $bodyHtml !== ''
            ? ['text' => trim($body) !== '' ? trim($body) : lead_email_html_to_text($bodyHtml), 'html' => $bodyHtml]
            : lead_email_parse_mime_body($body);
        $text = trim((string)($parsed['text'] ?? ''));
        $html = trim((string)($parsed['html'] ?? ''));
        if ($text === '' && $html !== '') {
            $text = lead_email_html_to_text($html);
        }
        return [
            'text' => $text !== '' ? $text : '(empty email)',
            'html' => lead_email_sanitize_display_html($html !== '' ? $html : lead_email_plain_to_html($text)),
        ];
    }
}

if (!function_exists('lead_email_add_column')) {
    function lead_email_add_column(string $table, string $column, string $definition): void
    {
        try {
            $exists = (bool) db_value(
                'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column',
                ['table' => $table, 'column' => $column]
            );
            if (!$exists) {
                db_query("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
            }
        } catch (Throwable $e) {
            esm_log('lead_email', 'Could not add email schema column.', [
                'table' => $table,
                'column' => $column,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

if (!function_exists('lead_email_column_exists')) {
    function lead_email_column_exists(string $table, string $column): bool
    {
        try {
            return (bool) db_value(
                'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column',
                ['table' => $table, 'column' => $column]
            );
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('lead_email_token_secret')) {
    function lead_email_token_secret(): string
    {
        $secret = trim((string)(defined('APP_KEY') ? APP_KEY : ''));
        if ($secret === '') {
            $secret = trim((string)(defined('ELITE_QUICK_ACTION_SECRET') ? ELITE_QUICK_ACTION_SECRET : ''));
        }
        return $secret !== '' ? $secret : 'elite-smiles-email-fallback';
    }
}

if (!function_exists('lead_email_signed_token')) {
    function lead_email_signed_token(int $leadId, string $purpose): string
    {
        $payload = $leadId . '|' . $purpose;
        $sig = hash_hmac('sha256', $payload, lead_email_token_secret());
        return rtrim(strtr(base64_encode($payload . '|' . $sig), '+/', '-_'), '=');
    }
}

if (!function_exists('lead_email_verify_token')) {
    function lead_email_verify_token(string $token, string $purpose): int
    {
        $decoded = base64_decode(strtr($token, '-_', '+/'), true);
        if (!is_string($decoded) || $decoded === '') {
            return 0;
        }
        $parts = explode('|', $decoded);
        if (count($parts) !== 3) {
            return 0;
        }
        [$leadIdRaw, $tokenPurpose, $sig] = $parts;
        $leadId = (int)$leadIdRaw;
        if ($leadId <= 0 || $tokenPurpose !== $purpose) {
            return 0;
        }
        $expected = hash_hmac('sha256', $leadId . '|' . $purpose, lead_email_token_secret());
        return hash_equals($expected, $sig) ? $leadId : 0;
    }
}

if (!function_exists('lead_email_unsubscribe_url')) {
    function lead_email_unsubscribe_url(int $leadId): string
    {
        return base_url('app/api/email_unsubscribe.php?t=' . rawurlencode(lead_email_signed_token($leadId, 'unsubscribe')));
    }
}

if (!function_exists('lead_email_tracking_url')) {
    function lead_email_tracking_url(string $trackingToken): string
    {
        return base_url('app/api/email_open.php?t=' . rawurlencode($trackingToken));
    }
}

if (!function_exists('lead_email_user_label')) {
    function lead_email_user_label(): string
    {
        if (function_exists('auth_user')) {
            $user = auth_user();
            $name = trim(((string)($user['first_name'] ?? '')) . ' ' . ((string)($user['last_name'] ?? '')));
            if ($name !== '') {
                return $name;
            }
            $email = trim((string)($user['email'] ?? ''));
            if ($email !== '') {
                return $email;
            }
        }

        return 'System';
    }
}

if (!function_exists('lead_email_first_name')) {
    function lead_email_first_name(array $lead): string
    {
        $fullName = trim((string)($lead['full_name'] ?? ''));
        if ($fullName === '' || strtolower($fullName) === 'inbound sms lead') {
            return '';
        }

        $parts = preg_split('/\s+/', $fullName) ?: [];
        return trim((string)($parts[0] ?? ''));
    }
}

if (!function_exists('lead_email_default_first_touch')) {
    function lead_email_default_first_touch(array $lead): array
    {
        $firstName = lead_email_first_name($lead);
        if (lead_language_is_spanish($lead)) {
            $greeting = $firstName !== '' ? 'Hola ' . $firstName . ',' : 'Hola,';
            $procedure = trim((string)($lead['procedure_interest'] ?? ''));
            $serviceLine = $procedure !== ''
                ? 'Quería asegurarme de dar seguimiento a su solicitud de consulta sobre ' . $procedure . '.'
                : 'Quería asegurarme de dar seguimiento a su solicitud de consulta de sonrisa.';

            return [
                'subject' => 'La información que solicitó a Elite Smiles',
                'body' => implode("\n\n", [
                    $greeting,
                    'Aquí tiene la información que solicitó. ' . $serviceLine,
                    'Cada sonrisa se planifica de forma personalizada. El Dr. Meden revisa sus dientes, mordida y metas antes de recomendar opciones, para que no sea un plan genérico.',
                    'La consulta es gratis y sin presión. Puede responder este correo si tiene alguna pregunta; Rod también le enviará un mensaje de texto para que continuar la conversación sea fácil.',
                    "Con gusto,\nEl equipo de Elite Smiles",
                ]),
            ];
        }

        $greeting = $firstName !== '' ? 'Hi ' . $firstName . ',' : 'Hi,';
        $procedure = trim((string)($lead['procedure_interest'] ?? ''));
        $serviceLine = $procedure !== ''
            ? 'I wanted to make sure we followed up on your ' . $procedure . ' consultation request.'
            : 'I wanted to make sure we followed up on your smile consultation request.';

        $body = implode("\n\n", [
            $greeting,
            'Here is the information you requested. ' . $serviceLine,
            'Every smile case is custom. Dr. Meden reviews your teeth, bite, and goals before recommending options, so you are not getting a cookie-cutter plan.',
            'The consultation is complimentary and low pressure. You can reply here with any questions; Rod will also text you so it is easy to continue the conversation.',
            "Warmly,\nThe Elite Smiles Team",
        ]);

        return [
            'subject' => 'The information you requested from Elite Smiles',
            'body' => lead_language_maybe_add_email_offer($lead, $body),
        ];
    }
}

if (!function_exists('lead_email_html_template')) {
    function lead_email_html_template(array $lead, string $subject, string $plainBody, string $trackingToken = ''): string
    {
        $leadId = (int)($lead['id'] ?? 0);
        $unsubscribeUrl = $leadId > 0 ? lead_email_unsubscribe_url($leadId) : '';
        $trackingPixel = $trackingToken !== '' ? '<img src="' . htmlspecialchars(lead_email_tracking_url($trackingToken), ENT_QUOTES, 'UTF-8') . '" width="1" height="1" alt="" style="display:none;border:0;width:1px;height:1px;">' : '';
        $logoUrl = htmlspecialchars((string)ELITE_EMAIL_LOGO_URL, ENT_QUOTES, 'UTF-8');
        $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $paragraphs = array_filter(preg_split("/\n{2,}/", trim($plainBody)) ?: []);
        $bodyHtml = '';
        foreach ($paragraphs as $paragraph) {
            $bodyHtml .= '<p style="margin:0 0 16px;font-size:16px;line-height:1.65;color:#334155;">' . nl2br(htmlspecialchars(trim($paragraph), ENT_QUOTES, 'UTF-8')) . '</p>';
        }

        $unsubscribeHtml = $unsubscribeUrl !== ''
            ? '<a href="' . htmlspecialchars($unsubscribeUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#64748b;text-decoration:underline;">unsubscribe from follow-up emails</a>'
            : 'reply with unsubscribe';

        return '<!doctype html>
<html>
<body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
  <div style="display:none;max-height:0;overflow:hidden;color:transparent;">' . $safeSubject . '</div>
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f7fb;padding:28px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #e2e8f0;border-radius:18px;overflow:hidden;box-shadow:0 18px 45px rgba(15,23,42,0.08);">
          <tr>
            <td style="padding:28px 34px 18px;text-align:center;background:#ffffff;">
              <img src="' . $logoUrl . '" width="210" alt="Elite Smiles" style="max-width:210px;height:auto;border:0;">
            </td>
          </tr>
          <tr>
            <td style="padding:8px 34px 30px;">
              ' . $bodyHtml . '
              <div style="margin-top:26px;padding-top:20px;border-top:1px solid #e2e8f0;">
                <p style="margin:0;font-size:14px;line-height:1.6;color:#64748b;">Elite Smiles by Dr. Walter Meden<br>11762 South State, Suite 300, Draper, UT 84020</p>
              </div>
            </td>
          </tr>
        </table>
        <p style="max-width:640px;margin:14px auto 0;font-size:12px;line-height:1.5;color:#64748b;text-align:center;">
          You are receiving this because you requested information from Elite Smiles. If this was not you, you can ' . $unsubscribeHtml . '.
        </p>
      </td>
    </tr>
  </table>
  ' . $trackingPixel . '
</body>
</html>';
    }
}

if (!function_exists('lead_email_plain_text_with_compliance')) {
    function lead_email_plain_text_with_compliance(string $body, string $unsubscribeUrl): string
    {
        $optOut = trim($unsubscribeUrl) !== ''
            ? 'Unsubscribe from follow-up emails: ' . trim($unsubscribeUrl)
            : 'Reply with unsubscribe to stop follow-up emails.';
        return rtrim($body)
            . "\n\n---\nElite Smiles by Dr. Walter Meden\n"
            . "11762 South State, Suite 300, Draper, UT 84020\n"
            . "You are receiving this because you requested information from Elite Smiles.\n"
            . $optOut;
    }
}

if (!function_exists('lead_email_spf_records_authorize')) {
    /** Pure SPF check kept separate so policy tests do not depend on live DNS. */
    function lead_email_spf_records_authorize(array $records, string $requiredInclude): bool
    {
        $requiredInclude = strtolower(trim($requiredInclude));
        if ($requiredInclude === '') {
            return true;
        }
        $spfRecords = [];
        foreach ($records as $record) {
            $value = is_array($record)
                ? (string)($record['txt'] ?? $record['entries'][0] ?? '')
                : (string)$record;
            $value = strtolower(trim($value));
            if (str_starts_with($value, 'v=spf1')) {
                $spfRecords[] = $value;
            }
        }
        if (count($spfRecords) !== 1) {
            return false;
        }
        return str_contains($spfRecords[0], 'include:' . $requiredInclude);
    }
}

if (!function_exists('lead_email_automation_authentication_status')) {
    /** Fail closed for automated marketing follow-up when sender SPF is invalid. */
    function lead_email_automation_authentication_status(): array
    {
        static $status = null;
        if (is_array($status)) {
            return $status;
        }
        $requiredInclude = defined('ELITE_EMAIL_REQUIRED_SPF_INCLUDE')
            ? strtolower(trim((string)ELITE_EMAIL_REQUIRED_SPF_INCLUDE))
            : 'spf.jetsmtp.net';
        if ($requiredInclude === '') {
            return $status = ['ready' => true, 'reason' => 'spf_requirement_disabled'];
        }
        $fromEmail = strtolower(trim((string)SMTP_FROM_EMAIL));
        $domain = str_contains($fromEmail, '@') ? substr(strrchr($fromEmail, '@') ?: '', 1) : '';
        if ($domain === '' || !function_exists('dns_get_record')) {
            return $status = ['ready' => false, 'reason' => 'sender_spf_unverifiable', 'domain' => $domain];
        }
        try {
            $records = dns_get_record($domain, DNS_TXT);
        } catch (Throwable $e) {
            $records = false;
        }
        $ready = is_array($records) && lead_email_spf_records_authorize($records, $requiredInclude);
        return $status = [
            'ready' => $ready,
            'reason' => $ready ? 'sender_spf_authorized' : 'sender_spf_missing_required_include',
            'domain' => $domain,
            'required_include' => $requiredInclude,
        ];
    }
}

if (!function_exists('lead_email_insert')) {
    function lead_email_insert(array $email): int
    {
        lead_email_ensure_schema();

        $leadId = (int)($email['lead_id'] ?? 0);
        $to = trim((string)($email['to_email'] ?? ''));
        $subject = trim((string)($email['subject'] ?? ''));
        $body = trim((string)($email['body'] ?? ''));

        if ($leadId <= 0 || $to === '' || $subject === '' || $body === '') {
            return 0;
        }

        try {
            return db_insert(
                'INSERT INTO lead_emails (
                    lead_id, direction, from_email, to_email, subject, body, body_html,
                    status, tracking_token, provider_response, created_by, created_at
                ) VALUES (
                    :lead_id, :direction, :from_email, :to_email, :subject, :body, :body_html,
                    :status, :tracking_token, :provider_response, :created_by, :created_at
                )',
                [
                    'lead_id' => $leadId,
                    'direction' => (string)($email['direction'] ?? 'outbound'),
                    'from_email' => (string)($email['from_email'] ?? SMTP_FROM_EMAIL),
                    'to_email' => $to,
                    'subject' => $subject,
                    'body' => $body,
                    'body_html' => trim((string)($email['body_html'] ?? '')) !== '' ? (string)$email['body_html'] : null,
                    'status' => (string)($email['status'] ?? 'sent'),
                    'tracking_token' => (string)($email['tracking_token'] ?? ''),
                    'provider_response' => ($email['provider_response'] ?? null) !== null ? (string)$email['provider_response'] : null,
                    'created_by' => (string)($email['created_by'] ?? lead_email_user_label()),
                    'created_at' => (string)($email['created_at'] ?? now()),
                ]
            );
        } catch (Throwable $e) {
            esm_log('lead_email', 'Could not insert email record.', [
                'lead_id' => $leadId,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }
}

if (!function_exists('lead_email_find_lead_by_email')) {
    function lead_email_find_lead_by_email(string $email): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        try {
            return db_one(
                'SELECT * FROM leads WHERE LOWER(email) = :email ORDER BY updated_at DESC, id DESC LIMIT 1',
                ['email' => $email]
            );
        } catch (Throwable $e) {
            esm_log('lead_email', 'Could not find lead by inbound email.', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}

if (!function_exists('lead_email_record_bounce')) {
    function lead_email_record_bounce(string $recipientEmail, string $subject, string $body, string $sourceId = ''): array
    {
        lead_email_ensure_schema();

        $recipientEmail = strtolower(trim($recipientEmail));
        if ($recipientEmail === '' || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Bounce recipient is not a valid email address.', 'lead_id' => 0];
        }

        $lead = lead_email_find_lead_by_email($recipientEmail);
        if (!$lead) {
            return ['ok' => false, 'message' => 'No matching lead for bounced recipient.', 'lead_id' => 0];
        }

        $leadId = (int)($lead['id'] ?? 0);
        if ($leadId <= 0) {
            return ['ok' => false, 'message' => 'Matched lead is invalid.', 'lead_id' => 0];
        }

        $email = db_one(
            "SELECT id, status, provider_response
             FROM lead_emails
             WHERE lead_id = :lead_id
               AND direction = 'outbound'
               AND LOWER(to_email) = :to_email
             ORDER BY created_at DESC, id DESC
             LIMIT 1",
            ['lead_id' => $leadId, 'to_email' => $recipientEmail]
        );

        if (!$email) {
            return ['ok' => false, 'message' => 'No outbound email found for bounced recipient.', 'lead_id' => $leadId];
        }

        $emailId = (int)($email['id'] ?? 0);
        $alreadyBounced = (string)($email['status'] ?? '') === 'bounced';
        $agentWoken = 0;
        $providerNote = trim(implode("\n\n", array_filter([
            $sourceId !== '' ? 'Bounce source: ' . $sourceId : '',
            trim($subject) !== '' ? 'Bounce subject: ' . trim($subject) : '',
            mb_substr(trim(preg_replace('/\s+/', ' ', $body) ?? ''), 0, 700),
        ])));

        if (!$alreadyBounced) {
            db_execute(
                "UPDATE lead_emails
                 SET status = 'bounced',
                     provider_response = :provider_response
                 WHERE id = :id
                 LIMIT 1",
                ['provider_response' => $providerNote, 'id' => $emailId]
            );
            lead_agent_update_touchpoint_delivery('email', $emailId, 'bounced', $sourceId);

            $agentWoken = db_execute(
                "UPDATE lead_agent_states
                 SET next_action_at = NOW(), last_decision = 'email_bounced_switch_channel', lock_token = '', locked_at = NULL, updated_at = NOW()
                 WHERE lead_id = :lead_id AND status IN ('active', 'engaged') AND human_takeover = 0
                   AND (locked_at IS NULL OR locked_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE))",
                ['lead_id' => $leadId]
            );

            if (function_exists('lead_comm_insert_activity')) {
                lead_comm_insert_activity($leadId, 'email_bounced', 'Email bounced for ' . $recipientEmail . ': ' . (trim($subject) !== '' ? trim($subject) : 'delivery failure'), [
                    'email_id' => $emailId,
                    'source_id' => $sourceId,
                ], 'Mailbox');
            }
        }

        try {
            $sets = ['updated_at = :now'];
            $params = ['id' => $leadId, 'now' => now()];
            if (lead_email_column_exists('leads', 'email_opt_status')) {
                $sets[] = "email_opt_status = 'bounced'";
            }
            if (lead_email_column_exists('leads', 'follow_up_status')) {
                $sets[] = "follow_up_status = 'needs_follow_up'";
            }
            if (!empty($agentWoken) && lead_email_column_exists('leads', 'next_follow_up_at')) {
                $sets[] = 'next_follow_up_at = :next_follow_up_at';
                $params['next_follow_up_at'] = now();
            }
            db_execute('UPDATE leads SET ' . implode(', ', $sets) . ' WHERE id = :id LIMIT 1', $params);
        } catch (Throwable $e) {
            esm_log('lead_email', 'Could not update lead after bounced email.', [
                'lead_id' => $leadId,
                'error' => $e->getMessage(),
            ]);
        }

        $contactability = function_exists('lead_agent_reconcile_unreachable_contact')
            ? lead_agent_reconcile_unreachable_contact($leadId, 'email_bounce')
            : ['ok' => true, 'classified' => false, 'reason' => 'lead_agent_not_loaded'];

        return [
            'ok' => true,
            'message' => $alreadyBounced ? 'Bounce already recorded.' : 'Bounce recorded.',
            'lead_id' => $leadId,
            'email_id' => $emailId,
            'duplicate' => $alreadyBounced,
            'contactability' => $contactability,
        ];
    }
}

if (!function_exists('lead_email_action_alert_message')) {
    function lead_email_action_alert_message(array $lead, string $event, string $detail = ''): string
    {
        $name = trim((string)($lead['full_name'] ?? ''));
        if ($name === '') {
            $name = 'Unknown';
        }

        $phone = trim((string)($lead['phone'] ?? ''));
        $prefix = match ($event) {
            'inbound_reply' => 'Email reply',
            'opt_out' => 'Opt-out',
            default => 'CRM alert',
        };

        $message = $prefix . ': ' . $name;
        if ($phone !== '') {
            $message .= ' | ' . $phone;
        }
        if ($detail !== '') {
            $message .= ' | ' . $detail;
        }

        return mb_strlen($message) > 155 ? mb_substr($message, 0, 152) . '...' : $message;
    }
}

if (!function_exists('lead_email_send_action_alert')) {
    function lead_email_send_action_alert(array $lead, string $event, string $detail = ''): bool
    {
        return false;
    }
}

if (!function_exists('lead_email_new_reply_text')) {
    function lead_email_new_reply_text(string $subject, string $body): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($body));
        $lines = explode("\n", $text);
        $kept = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                $kept[] = '';
                continue;
            }

            if (str_starts_with($trimmed, '>')) {
                break;
            }

            if (preg_match('/^On .+wrote:\s*$/i', $trimmed)) {
                break;
            }

            if (preg_match('/^[-_ ]*Original Message[-_ ]*$/i', $trimmed)) {
                break;
            }

            if (preg_match('/^(From|Sent|To|Subject):\s+/i', $trimmed)) {
                break;
            }

            $kept[] = $line;
        }

        $reply = trim(implode("\n", $kept));
        return trim($subject . "\n" . ($reply !== '' ? $reply : $body));
    }
}

if (!function_exists('lead_email_is_unsubscribe_request')) {
    function lead_email_is_unsubscribe_request(string $subject, string $body): bool
    {
        $text = strtolower(lead_email_new_reply_text($subject, $body));
        $text = preg_replace('/https?:\/\/\S+/i', ' ', $text) ?? $text;

        return (bool) preg_match('/\b(stop|unsubscribe|remove me|opt out|do not email|don\'t email)\b/i', $text);
    }
}

if (!function_exists('lead_email_record_inbound')) {
    function lead_email_record_inbound(string $fromEmail, string $toEmail, string $subject, string $body, string $sourceId = ''): array
    {
        lead_email_ensure_schema();

        $fromEmail = strtolower(trim($fromEmail));
        $toEmail = strtolower(trim($toEmail));
        $subject = trim($subject) !== '' ? trim($subject) : '(no subject)';
        $preparedContent = lead_email_prepare_content($body);
        $body = (string)$preparedContent['text'];
        $bodyHtml = (string)$preparedContent['html'];

        $lead = lead_email_find_lead_by_email($fromEmail);
        if (!$lead) {
            return ['ok' => false, 'message' => 'No matching lead for inbound email.', 'lead_id' => 0];
        }

        $leadId = (int)($lead['id'] ?? 0);
        if ($leadId <= 0) {
            return ['ok' => false, 'message' => 'Matched lead is invalid.', 'lead_id' => 0];
        }

        if ($sourceId !== '') {
            $existing = (int) db_value(
                "SELECT COUNT(*) FROM lead_emails WHERE direction = 'inbound' AND provider_response = :source_id",
                ['source_id' => $sourceId]
            );
            if ($existing > 0) {
                return ['ok' => true, 'message' => 'Inbound email already logged.', 'lead_id' => $leadId, 'duplicate' => true];
            }
        }

        $emailId = lead_email_insert([
            'lead_id' => $leadId,
            'direction' => 'inbound',
            'from_email' => $fromEmail,
            'to_email' => $toEmail,
            'subject' => mb_substr($subject, 0, 255),
            'body' => $body,
            'body_html' => $bodyHtml,
            'status' => 'received',
            'provider_response' => $sourceId,
            'created_by' => 'Mailbox',
        ]);

        // Use only the patient's new reply, excluding quoted thread history,
        // so a prior outbound message cannot determine the saved language.
        $newReplyText = lead_email_new_reply_text($subject, $body);
        lead_language_record_inbound($leadId, $newReplyText);

        $isUnsubscribe = lead_email_is_unsubscribe_request($subject, $body);
        if ($isUnsubscribe) {
            lead_email_unsubscribe($leadId);
        }

        try {
            $sets = ['updated_at = :now'];
            $params = ['id' => $leadId, 'now' => now()];
            if (lead_email_column_exists('leads', 'last_inbound_at')) {
                $sets[] = 'last_inbound_at = :now';
            }
            if (lead_email_column_exists('leads', 'follow_up_status')) {
                $sets[] = "follow_up_status = :follow_up_status";
                $params['follow_up_status'] = $isUnsubscribe ? 'not_interested' : 'needs_follow_up';
            }
            db_execute('UPDATE leads SET ' . implode(', ', $sets) . ' WHERE id = :id LIMIT 1', $params);
            if (!$isUnsubscribe) {
                lead_lifecycle_mark_inbound_answer($leadId, 'lead_email_inbound');
            }
        } catch (Throwable $e) {
            esm_log('lead_email', 'Could not update lead after inbound email.', [
                'lead_id' => $leadId,
                'error' => $e->getMessage(),
            ]);
        }

        if (function_exists('lead_comm_insert_activity')) {
            lead_comm_insert_activity($leadId, $isUnsubscribe ? 'email_unsubscribe' : 'email_inbound', 'Received email from ' . $fromEmail . ': ' . $subject, [
                'email_id' => $emailId,
                'source_id' => $sourceId,
            ], 'Mailbox');
        }

        if (!$isUnsubscribe) {
            lead_email_send_action_alert($lead, 'inbound_reply', mb_substr($subject, 0, 60));
            try {
                $mobilePushPath = dirname(__DIR__) . '/core/mobile_ai_push.php';
                if (is_file($mobilePushPath)) {
                    require_once $mobilePushPath;
                }
                if (function_exists('mobile_ai_send_lead_event_push')) {
                    $freshLeadForPush = db_one('SELECT * FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
                    mobile_ai_send_lead_event_push($freshLeadForPush ?: $lead, [
                        'lead_id' => $leadId,
                        'type' => 'reply',
                        'message' => trim($subject . ' - ' . $newReplyText),
                        'notification_id' => 'email-' . $emailId,
                    ]);
                }
            } catch (Throwable $e) {
                esm_log('mobile_ai_push', 'Inbound email Elite AI push failed.', [
                    'lead_id' => $leadId,
                    'email_id' => $emailId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $leadAgentPath = __DIR__ . '/lead_agent.php';
        if (is_file($leadAgentPath)) {
            require_once $leadAgentPath;
        }
        if (function_exists('lead_agent_enabled') && lead_agent_enabled()) {
            lead_agent_handle_inbound(
                $leadId,
                $newReplyText,
                'email',
                'email-' . ($sourceId !== '' ? $sourceId : (string) $emailId)
            );
        }

        return ['ok' => true, 'message' => 'Inbound email logged.', 'lead_id' => $leadId, 'email_id' => $emailId];
    }
}

if (!function_exists('lead_email_recent')) {
    function lead_email_recent(int $leadId, int $limit = 20): array
    {
        lead_email_ensure_schema();
        if ($leadId <= 0) {
            return [];
        }

        try {
            $leadForTemplate = db_one('SELECT * FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
            $rows = db_all(
                'SELECT id, lead_id, direction, from_email, to_email, subject, body, body_html, status, tracking_token, created_by, created_at, opened_at
                 FROM lead_emails
                 WHERE lead_id = :lead_id
                 ORDER BY created_at DESC, id DESC
                 LIMIT ' . max(1, min(50, $limit)),
                ['lead_id' => $leadId]
            );
            return array_map(static function (array $email) use ($leadForTemplate): array {
                $storedHtml = (string)($email['body_html'] ?? '');
                if ($storedHtml === '' && (string)($email['direction'] ?? '') === 'outbound' && is_array($leadForTemplate)) {
                    $storedHtml = lead_email_html_template(
                        $leadForTemplate,
                        (string)($email['subject'] ?? ''),
                        (string)($email['body'] ?? ''),
                        (string)($email['tracking_token'] ?? '')
                    );
                }
                $prepared = lead_email_prepare_content((string)($email['body'] ?? ''), $storedHtml);
                $email['body'] = (string)$prepared['text'];
                $email['body_html_safe'] = (string)$prepared['html'];
                unset($email['tracking_token']);
                return $email;
            }, $rows);
        } catch (Throwable $e) {
            esm_log('lead_email', 'Could not load recent lead emails.', [
                'lead_id' => $leadId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
}

if (!function_exists('lead_email_send')) {
    function lead_email_send(int $leadId, string $subject, string $body, string $createdBy = ''): array
    {
        lead_email_ensure_schema();

        $lead = db_one('SELECT * FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
        if (!$lead) {
            return ['ok' => false, 'message' => 'Lead not found.'];
        }

        $to = strtolower(trim((string)($lead['email'] ?? '')));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Lead does not have a valid email address.'];
        }

        $subject = trim($subject);
        $body = trim($body);
        if ($subject === '' || $body === '') {
            return ['ok' => false, 'message' => 'Subject and email body are required.'];
        }

        if (in_array(strtolower(trim((string)($lead['email_opt_status'] ?? 'subscribed'))), [
            'unsubscribed', 'opted_out', 'bounced', 'blocked', 'dropped', 'invalid',
        ], true)) {
            return ['ok' => false, 'message' => 'Lead email is suppressed from follow-up.'];
        }

        $trackingToken = bin2hex(random_bytes(24));
        $leadForEmail = $lead;
        $leadForEmail['id'] = $leadId;
        $htmlBody = lead_email_html_template($leadForEmail, $subject, $body, $trackingToken);
        $unsubscribeUrl = lead_email_unsubscribe_url($leadId);
        $plainSendBody = lead_email_plain_text_with_compliance($body, $unsubscribeUrl);
        $headers = [
            'List-Unsubscribe: <' . $unsubscribeUrl . '>',
            'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
        ];

        $send = elite_smtp_send_mail($to, $subject, $plainSendBody, null, $htmlBody, $headers);
        $emailId = lead_email_insert([
            'lead_id' => $leadId,
            'direction' => 'outbound',
            'from_email' => SMTP_FROM_EMAIL,
            'to_email' => $to,
            'subject' => $subject,
            'body' => $body,
            'body_html' => $htmlBody,
            'status' => !empty($send['ok']) ? 'sent' : 'failed',
            'tracking_token' => $trackingToken,
            'provider_response' => (string)($send['smtp_response'] ?? $send['message'] ?? ''),
            'created_by' => $createdBy !== '' ? $createdBy : lead_email_user_label(),
        ]);

        if (function_exists('lead_comm_insert_activity')) {
            lead_comm_insert_activity($leadId, !empty($send['ok']) ? 'email_outbound' : 'email_failed', (!empty($send['ok']) ? 'Sent email to ' : 'Email failed to ') . $to . ': ' . $subject, [
                'email_id' => $emailId,
                'status' => !empty($send['ok']) ? 'sent' : 'failed',
                'message' => $send['message'] ?? '',
            ], $createdBy !== '' ? $createdBy : lead_email_user_label());
        }

        return [
            'ok' => !empty($send['ok']),
            'message' => (string)($send['message'] ?? (!empty($send['ok']) ? 'Email sent.' : 'Email failed.')),
            'email_id' => $emailId,
            'to' => $to,
        ];
    }
}

if (!function_exists('lead_email_mark_opened')) {
    function lead_email_mark_opened(string $trackingToken): bool
    {
        lead_email_ensure_schema();
        $trackingToken = trim($trackingToken);
        if ($trackingToken === '') {
            return false;
        }
        try {
            db_execute(
                "UPDATE lead_emails
                 SET opened_at = COALESCE(opened_at, :opened_at)
                 WHERE tracking_token = :tracking_token
                 LIMIT 1",
                ['opened_at' => now(), 'tracking_token' => $trackingToken]
            );
            $emailId = (int) db_value('SELECT id FROM lead_emails WHERE tracking_token = :tracking_token LIMIT 1', ['tracking_token' => $trackingToken]);
            if ($emailId > 0) {
                lead_agent_update_touchpoint_delivery('email', $emailId, 'opened');
            }
            return true;
        } catch (Throwable $e) {
            esm_log('lead_email', 'Could not mark email opened.', ['error' => $e->getMessage()]);
            return false;
        }
    }
}

if (!function_exists('lead_email_unsubscribe')) {
    function lead_email_unsubscribe(int $leadId): bool
    {
        lead_email_ensure_schema();
        if ($leadId <= 0) {
            return false;
        }
        try {
            $setParts = [
                "email_opt_status = 'unsubscribed'",
                'email_opted_out_at = :now',
                'updated_at = :now',
            ];

            db_execute(
                'UPDATE leads SET ' . implode(', ', $setParts) . ' WHERE id = :id LIMIT 1',
                ['now' => now(), 'id' => $leadId]
            );
            lead_lifecycle_transition_status(
                $leadId,
                'opted_out',
                'Lead revoked email consent.',
                'lead_email_unsubscribe',
                []
            );
            if (function_exists('lead_comm_insert_activity')) {
                lead_comm_insert_activity($leadId, 'email_unsubscribe', 'Patient unsubscribed from email follow-up.', [
                    'source' => 'email_unsubscribe_link',
                ], 'System');
            }
            $lead = db_one('SELECT * FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
            if ($lead) {
                lead_email_send_action_alert($lead, 'opt_out', 'unsubscribe link');
            }
            return true;
        } catch (Throwable $e) {
            esm_log('lead_email', 'Could not unsubscribe lead.', [
                'lead_id' => $leadId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

if (!function_exists('lead_email_first_touch_should_wait_for_sms')) {
    function lead_email_first_touch_should_wait_for_sms(array $lead, bool $smsWillBeAttempted = true): bool
    {
        if (!$smsWillBeAttempted
            || !defined('ELITE_LEAD_AGENT_ENABLED') || !ELITE_LEAD_AGENT_ENABLED
            || (defined('ELITE_LEAD_AGENT_MODE') && ELITE_LEAD_AGENT_MODE === 'off')
            || !elite_phone_is_valid_us((string)($lead['phone'] ?? ''))) {
            return false;
        }
        $smsStatus = strtolower(trim((string)($lead['sms_opt_status'] ?? 'unknown')));
        $leadStatus = strtolower(trim((string)($lead['status'] ?? '')));
        return !in_array($smsStatus, ['dnd', 'opted_out'], true) && $leadStatus !== 'opted_out';
    }
}

if (!function_exists('lead_email_maybe_send_first_touch')) {
    function lead_email_maybe_send_first_touch(int $leadId, bool $smsWillBeAttempted = true): array
    {
        if (!defined('ELITE_EMAIL_AUTO_FIRST_TOUCH_ENABLED') || !ELITE_EMAIL_AUTO_FIRST_TOUCH_ENABLED) {
            return [
                'attempted' => false,
                'sent' => false,
                'subject' => '',
                'body' => '',
                'status_label' => 'Auto first-touch email disabled.',
            ];
        }

        $lead = db_one('SELECT * FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
        if (!$lead || trim((string)($lead['email'] ?? '')) === '') {
            return [
                'attempted' => false,
                'sent' => false,
                'subject' => '',
                'body' => '',
                'status_label' => 'Lead has no valid email address.',
            ];
        }

        if (lead_email_first_touch_should_wait_for_sms($lead, $smsWillBeAttempted)) {
            return [
                'attempted' => false,
                'sent' => false,
                'subject' => '',
                'body' => '',
                'status_label' => 'Auto email deferred until the unanswered five-hour follow-up.',
            ];
        }

        $authentication = lead_email_automation_authentication_status();
        if (empty($authentication['ready'])) {
            esm_log('lead_email', 'Automatic first-touch email paused because sender authentication is not ready.', $authentication + ['lead_id' => $leadId]);
            return [
                'attempted' => false,
                'sent' => false,
                'subject' => '',
                'body' => '',
                'status_label' => 'Auto first-touch email paused until sender SPF is valid.',
            ];
        }

        $template = lead_email_default_first_touch($lead);
        $result = lead_email_send($leadId, $template['subject'], $template['body'], 'System');
        if (empty($result['ok'])) {
            esm_log('lead_email', 'Automatic first-touch email failed.', [
                'lead_id' => $leadId,
                'message' => $result['message'] ?? '',
            ]);
        } else {
            try {
                $updates = ['updated_at = :updated_at'];
                $params = [
                    'id' => $leadId,
                    'updated_at' => now(),
                ];
                if (lead_email_column_exists('leads', 'next_follow_up_at')) {
                    $currentFollowUp = trim((string)($lead['next_follow_up_at'] ?? ''));
                    if ($currentFollowUp === '') {
                        $updates[] = 'next_follow_up_at = :next_follow_up_at';
                        $params['next_follow_up_at'] = date('Y-m-d H:i:s', strtotime('+48 hours'));
                    }
                }
                if (lead_email_column_exists('leads', 'follow_up_status')) {
                    $updates[] = "follow_up_status = 'ok'";
                }
                db_execute('UPDATE leads SET ' . implode(', ', $updates) . ' WHERE id = :id LIMIT 1', $params);
            } catch (Throwable $e) {
                esm_log('lead_email', 'Could not schedule first-touch email follow-up.', [
                    'lead_id' => $leadId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'attempted' => true,
            'sent' => !empty($result['ok']),
            'subject' => $template['subject'],
            'body' => $template['body'],
            'status_label' => !empty($result['ok']) ? 'Auto email sent.' : ((string)($result['message'] ?? 'Auto email failed.')),
        ];
    }
}
