<?php
declare(strict_types=1);

/**
 * Meta webhook outbound notifications (email + optional SMS scaffold).
 */

require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/mailer.php';
require_once dirname(__DIR__) . '/core/twilio.php';
require_once __DIR__ . '/meta_config.php';

if (!function_exists('meta_notification_subject')) {
    function meta_notification_subject(array $lead): string
    {
        $name = trim((string)($lead['full_name'] ?? ($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? '')));
        $name = trim($name);
        if ($name === '') {
            $name = 'Unknown Lead';
        }

        return 'NEW META LEAD -- ' . $name . ' | Elite Smiles';
    }
}

if (!function_exists('meta_notification_body')) {
    function meta_notification_body(array $lead, array $meta = []): array
    {
        $name = trim((string)($lead['full_name'] ?? ''));
        if ($name === '') {
            $name = trim((string)(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? '')));
        }

        $phone = trim((string)($lead['phone'] ?? ''));
        $email = trim((string)($lead['email'] ?? ''));
        $timeline = trim((string)($meta['how_soon'] ?? $meta['timeline'] ?? $meta['consultation_status'] ?? ''));
        $source = trim((string)($lead['source'] ?? $meta['source'] ?? 'meta_lead_form'));
        $campaign = trim((string)($lead['campaign'] ?? $meta['campaign'] ?? 'Meta Form'));
        $leadId = (int)($lead['id'] ?? 0);
        $createdAt = trim((string)($meta['created_at'] ?? $lead['created_at'] ?? now()));
        $crmLink = rtrim((string)APP_URL, '/') . '/dashboard.php?lead_id=' . $leadId;

        $lines = [];
        $lines[] = 'NEW META LEAD RECEIVED';
        $lines[] = '-------------------';
        $lines[] = 'Name: ' . $name;
        $lines[] = 'Phone: ' . $phone;
        $lines[] = 'Email: ' . $email;
        $lines[] = 'Timeline: ' . ($timeline !== '' ? $timeline : 'Not provided');
        $lines[] = 'Source: ' . $source;
        $lines[] = 'Campaign: ' . ($campaign !== '' ? $campaign : 'Unknown campaign');
        $lines[] = 'Time: ' . $createdAt;
        $lines[] = 'CRM Link: ' . $crmLink;

        if (!empty($meta['notes'])) {
            $lines[] = 'Notes:';
            $lines[] = (string)$meta['notes'];
        }

        $body = implode(PHP_EOL, $lines);
        $html = nl2br($body);

        return [
            'text' => $body,
            'html' => $html,
            'crm_link' => $crmLink,
        ];
    }
}

if (!function_exists('meta_log_notification_issue')) {
    function meta_log_notification_issue(string $type, array $context = []): void
    {
        esm_log('meta_notifications', 'Meta notification issue: ' . $type, $context);
    }
}

if (!function_exists('meta_notify_lead')) {
    function meta_notify_lead(array $lead, array $meta = []): array
    {
        $result = [
            'email' => ['ok' => false, 'message' => 'Not sent'],
            'sms' => ['ok' => false, 'message' => 'Not sent'],
        ];

        $recipient = meta_cfg_notification_recipient();
        if ($recipient === '') {
            $result['email']['message'] = 'Notification recipient is not configured.';
            return $result;
        }

        $leadId = (int)($lead['id'] ?? 0);
        $message = meta_notification_body($lead, $meta);
        $subject = meta_notification_subject($lead);

        $bodyText = $message['text'];
        $bodyHtml = '<!doctype html><html><body style="font-family:Arial,sans-serif;">'
            . '<pre style="white-space:pre-wrap; font-size:13px;">' . $bodyText . '</pre>'
            . '</body></html>';

        try {
            if (function_exists('elite_send_mail_multipart')) {
                $sent = elite_send_mail_multipart(
                    $recipient,
                    $subject,
                    $bodyText,
                    $bodyHtml,
                    meta_cfg_notification_from_email(),
                    []
                );
                $result['email'] = ['ok' => (bool) $sent, 'message' => $sent ? 'Sent' : 'Email send failed.'];
            } elseif (function_exists('elite_send_mail')) {
                $sent = elite_send_mail($recipient, $subject, $bodyText, meta_cfg_notification_from_email(), []);
                $result['email'] = ['ok' => (bool) $sent, 'message' => $sent ? 'Sent' : 'Email send failed.'];
            } else {
                $result['email']['message'] = 'Mail helper unavailable.';
            }
        } catch (Throwable $e) {
            $result['email']['message'] = 'Email send exception.';
            meta_log_notification_issue('email_exception', [
                'lead_id' => $leadId,
                'message' => $e->getMessage(),
            ]);
        }

        $phone = trim((string)($lead['phone'] ?? ''));
        if ($phone !== '') {
            if (meta_cfg_twilio_enabled()) {
                $smsText = 'NEW META LEAD: ' . ($lead['full_name'] ?: 'Lead') . ' | ' . ($phone !== '' ? $phone : 'No phone')
                    . ' | ' . ($lead['email'] ?? '')
                    . ' | CRM: ' . (string)($message['crm_link'] ?? '');
                $smsResult = elite_twilio_send_sms($phone, $smsText);
                $result['sms'] = [
                    'ok' => (bool)($smsResult['ok'] ?? false),
                    'message' => (string)($smsResult['message'] ?? 'Twilio disabled/misconfigured.'),
                    'twilio_status_code' => (int)($smsResult['status_code'] ?? 0),
                ];
            } else {
                $result['sms']['message'] = 'Twilio is disabled by feature flag.';
            }
        }

        return $result;
    }
}

