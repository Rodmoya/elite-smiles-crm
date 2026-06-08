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
            lead_email_add_column('leads', 'email_opt_status', "VARCHAR(30) NOT NULL DEFAULT 'subscribed'");
            lead_email_add_column('leads', 'email_opted_out_at', 'DATETIME NULL');
        } catch (Throwable $e) {
            esm_log('lead_email', 'Could not ensure lead_emails table.', [
                'error' => $e->getMessage(),
            ]);
        }
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
        $greeting = $firstName !== '' ? 'Hi ' . $firstName . ',' : 'Hi,';
        $procedure = trim((string)($lead['procedure_interest'] ?? ''));
        $serviceLine = $procedure !== ''
            ? 'I wanted to make sure we followed up on your ' . $procedure . ' consultation request.'
            : 'I wanted to make sure we followed up on your smile consultation request.';

        return [
            'subject' => 'Following up on your Elite Smiles consultation',
            'body' => implode("\n\n", [
                $greeting,
                $serviceLine,
                'The consultation with Dr. Meden is free. It gives us a chance to evaluate your case, review your options, and go over pricing and financing based on what you actually need. 0% interest may be available for qualified patients.',
                'Would mornings or afternoons usually work better for you to come in?',
                "Warmly,\nThe Elite Smiles Team\n(801) 572-6262",
            ]),
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
                <p style="margin:0;font-size:14px;line-height:1.6;color:#64748b;">Elite Smiles by Dr. Walter Meden<br>11762 South State, Suite 300, Draper, UT 84020<br>(801) 572-6262</p>
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
                    lead_id, direction, from_email, to_email, subject, body,
                    status, tracking_token, provider_response, created_by, created_at
                ) VALUES (
                    :lead_id, :direction, :from_email, :to_email, :subject, :body,
                    :status, :tracking_token, :provider_response, :created_by, :created_at
                )',
                [
                    'lead_id' => $leadId,
                    'direction' => (string)($email['direction'] ?? 'outbound'),
                    'from_email' => (string)($email['from_email'] ?? SMTP_FROM_EMAIL),
                    'to_email' => $to,
                    'subject' => $subject,
                    'body' => $body,
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
            if (lead_email_column_exists('leads', 'follow_up_status')) {
                $sets[] = "follow_up_status = 'needs_follow_up'";
            }
            db_execute('UPDATE leads SET ' . implode(', ', $sets) . ' WHERE id = :id LIMIT 1', $params);
        } catch (Throwable $e) {
            esm_log('lead_email', 'Could not update lead after bounced email.', [
                'lead_id' => $leadId,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'ok' => true,
            'message' => $alreadyBounced ? 'Bounce already recorded.' : 'Bounce recorded.',
            'lead_id' => $leadId,
            'email_id' => $emailId,
            'duplicate' => $alreadyBounced,
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
        if (!function_exists('elite_email_to_text_recipient') || !function_exists('elite_mail_from_address')) {
            return false;
        }

        $to = elite_email_to_text_recipient();
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $headers = [
            'From: Elite Smiles CRM <' . elite_mail_from_address() . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ];

        try {
            return @mail($to, 'CRM', lead_email_action_alert_message($lead, $event, $detail), implode("\r\n", $headers));
        } catch (Throwable $e) {
            esm_log('lead_email', 'Could not send email-to-text action alert.', [
                'lead_id' => (int)($lead['id'] ?? 0),
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
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
        $body = trim($body) !== '' ? trim($body) : '(empty email)';

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
            'status' => 'received',
            'provider_response' => $sourceId,
            'created_by' => 'Mailbox',
        ]);

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
            return db_all(
                'SELECT id, lead_id, direction, from_email, to_email, subject, body, status, created_by, created_at, opened_at
                 FROM lead_emails
                 WHERE lead_id = :lead_id
                 ORDER BY created_at DESC, id DESC
                 LIMIT ' . max(1, min(50, $limit)),
                ['lead_id' => $leadId]
            );
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

        if ((string)($lead['email_opt_status'] ?? 'subscribed') === 'unsubscribed') {
            return ['ok' => false, 'message' => 'Lead has unsubscribed from email follow-up.'];
        }

        $trackingToken = bin2hex(random_bytes(24));
        $leadForEmail = $lead;
        $leadForEmail['id'] = $leadId;
        $htmlBody = lead_email_html_template($leadForEmail, $subject, $body, $trackingToken);
        $unsubscribeUrl = lead_email_unsubscribe_url($leadId);
        $headers = [
            'List-Unsubscribe: <' . $unsubscribeUrl . '>',
            'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
        ];

        $send = elite_smtp_send_mail($to, $subject, $body, null, $htmlBody, $headers);
        $emailId = lead_email_insert([
            'lead_id' => $leadId,
            'direction' => 'outbound',
            'from_email' => SMTP_FROM_EMAIL,
            'to_email' => $to,
            'subject' => $subject,
            'body' => $body,
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
            if (function_exists('leads_has_column') && leads_has_column('status')) {
                $setParts[] = "status = 'opted_out'";
            }

            db_execute(
                'UPDATE leads SET ' . implode(', ', $setParts) . ' WHERE id = :id LIMIT 1',
                ['now' => now(), 'id' => $leadId]
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

if (!function_exists('lead_email_maybe_send_first_touch')) {
    function lead_email_maybe_send_first_touch(int $leadId): void
    {
        if (!defined('ELITE_EMAIL_AUTO_FIRST_TOUCH_ENABLED') || !ELITE_EMAIL_AUTO_FIRST_TOUCH_ENABLED) {
            return;
        }

        $lead = db_one('SELECT * FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
        if (!$lead || trim((string)($lead['email'] ?? '')) === '') {
            return;
        }

        $template = lead_email_default_first_touch($lead);
        $result = lead_email_send($leadId, $template['subject'], $template['body'], 'System');
        if (empty($result['ok'])) {
            esm_log('lead_email', 'Automatic first-touch email failed.', [
                'lead_id' => $leadId,
                'message' => $result['message'] ?? '',
            ]);
        }
    }
}
