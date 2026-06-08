<?php
declare(strict_types=1);

/**
 * Elite Smiles Marketing CRM
 * File: /app/core/mailer.php
 *
 * Hosting-based mail helper using PHP mail()
 * plus:
 * - user invite emails
 * - lead notification emails
 * - premium HTML lead notification layout
 * - tap-to-text link generation for lead follow-up
 * - Pushover notifications
 * - signed quick-action URL generation
 */

require_once __DIR__ . '/../config/config.php';

if (!function_exists('elite_mail_from_address')) {
    function elite_mail_from_address(): string
    {
        if (defined('ELITE_LEAD_ALERT_FROM_EMAIL')) {
            $configured = trim((string) ELITE_LEAD_ALERT_FROM_EMAIL);
            if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_EMAIL)) {
                return $configured;
            }
        }

        return 'leads@elitesmilesutah.com';
    }
}

if (!function_exists('elite_mail_from_name')) {
    function elite_mail_from_name(): string
    {
        return 'Elite Smiles CRM';
    }
}

if (!function_exists('elite_lead_notification_recipient')) {
    function elite_lead_notification_recipient(): string
    {
        return 'leads@hi.elitesmilesutah.com';
    }
}

if (!function_exists('elite_lead_followup_device_label')) {
    function elite_lead_followup_device_label(): string
    {
        return 'Dedicated Lead iPhone (8016037011)';
    }
}

if (!function_exists('elite_app_url')) {
    function elite_app_url(): string
    {
        if (defined('APP_URL')) {
            return rtrim((string) APP_URL, '/');
        }

        return '';
    }
}

if (!function_exists('elite_string')) {
    function elite_string($value): string
    {
        return trim((string) ($value ?? ''));
    }
}

if (!function_exists('elite_escape_html')) {
    function elite_escape_html($value): string
    {
        return htmlspecialchars(trim((string) ($value ?? '')), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('elite_normalize_phone_digits')) {
    function elite_normalize_phone_digits(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return $digits;
        }

        if (strlen($digits) === 10) {
            return '1' . $digits;
        }

        return $digits;
    }
}

if (!function_exists('elite_format_phone_for_reading')) {
    function elite_format_phone_for_reading(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            return sprintf(
                '(%s) %s-%s',
                substr($digits, 0, 3),
                substr($digits, 3, 3),
                substr($digits, 6, 4)
            );
        }

        return trim($phone);
    }
}

if (!function_exists('elite_phone_for_link')) {
    function elite_phone_for_link(string $phone): string
    {
        $digits = elite_normalize_phone_digits($phone);

        if ($digits === '') {
            return '';
        }

        return '+' . $digits;
    }
}

if (!function_exists('elite_tel_link_for_phone')) {
    function elite_tel_link_for_phone(string $phone): string
    {
        $phoneLink = elite_phone_for_link($phone);
        return $phoneLink !== '' ? 'tel:' . $phoneLink : '';
    }
}

if (!function_exists('elite_sms_link_for_phone')) {
    function elite_sms_link_for_phone(string $phone, string $body = ''): string
    {
        $digits = elite_normalize_phone_digits($phone);

        if ($digits === '') {
            return '';
        }

        $url = 'sms:+' . $digits;

        if (trim($body) !== '') {
            $url .= '&body=' . rawurlencode($body);
        }

        return $url;
    }
}

if (!function_exists('elite_preferred_contact_label')) {
    function elite_preferred_contact_label(string $value): string
    {
        $value = strtolower(trim($value));

        if ($value === '') {
            return 'Not specified';
        }

        return match ($value) {
            'sms', 'text' => 'Text',
            'phone', 'call' => 'Call',
            'email' => 'Email',
            default => ucwords(str_replace(['_', '-'], ' ', $value)),
        };
    }
}

if (!function_exists('elite_yes_no_unsure_label')) {
    function elite_yes_no_unsure_label(string $value): string
    {
        $value = strtolower(trim($value));

        return match ($value) {
            'yes' => 'Yes',
            'no' => 'No',
            'unsure' => 'Unsure',
            default => $value !== '' ? ucwords(str_replace(['_', '-'], ' ', $value)) : 'Not specified',
        };
    }
}

if (!function_exists('elite_financing_option_label')) {
    function elite_financing_option_label(string $value): string
    {
        $value = strtolower(trim($value));

        return match ($value) {
            '' => 'Not specified',
            'none' => 'None',
            'mountain_america' => 'Mountain America',
            'sunbit' => 'Sunbit',
            'cherry' => 'Cherry',
            'carecredit' => 'CareCredit',
            default => ucwords(str_replace(['_', '-'], ' ', $value)),
        };
    }
}

if (!function_exists('elite_consultation_status_label')) {
    function elite_consultation_status_label(string $value): string
    {
        $value = strtolower(trim($value));

        return match ($value) {
            '' => 'Not specified',
            'requested' => 'Requested',
            'scheduled' => 'Scheduled',
            'completed' => 'Completed',
            'no_show' => 'No Show',
            'not_interested' => 'Not Interested',
            default => ucwords(str_replace(['_', '-'], ' ', $value)),
        };
    }
}

if (!function_exists('elite_lead_text_message_template')) {
    function elite_lead_text_message_template(array $lead): string
    {
        $name = trim((string) ($lead['full_name'] ?? ''));
        $firstName = trim((string) ($lead['first_name'] ?? ''));

        if ($name === '' && $firstName !== '') {
            $name = $firstName;
        }

        if ($name === '') {
            $name = 'there';
        }

        return 'Hi ' . $name . ', this is Elite Smiles. Thank you for reaching out — we received your information and wanted to follow up with you about your smile consultation.';
    }
}

if (!function_exists('elite_mail_line')) {
    function elite_mail_line(string $label, string $value): string
    {
        $value = trim($value);
        return $label . ': ' . ($value !== '' ? $value : '-');
    }
}

if (!function_exists('elite_lead_crm_url')) {
    function elite_lead_crm_url(array $lead, array $context = []): string
    {
        $fromContext = elite_string($context['crm_url'] ?? '');
        if ($fromContext !== '') {
            return $fromContext;
        }

        $leadId = elite_string($context['lead_id'] ?? $lead['id'] ?? '');
        $base = elite_app_url();

        if ($base === '' || $leadId === '') {
            return '';
        }

        return $base . '/dashboard.php?lead_id=' . rawurlencode($leadId);
    }
}

if (!function_exists('elite_quick_action_secret')) {
    function elite_quick_action_secret(): string
    {
        if (defined('ELITE_QUICK_ACTION_SECRET')) {
            return elite_string((string) ELITE_QUICK_ACTION_SECRET);
        }

        return '';
    }
}

if (!function_exists('elite_quick_action_ttl_seconds')) {
    function elite_quick_action_ttl_seconds(): int
    {
        if (defined('ELITE_QUICK_ACTION_TTL_SECONDS')) {
            $ttl = (int) ELITE_QUICK_ACTION_TTL_SECONDS;
            if ($ttl > 0) {
                return $ttl;
            }
        }

        return 86400;
    }
}

if (!function_exists('elite_quick_action_signature_base')) {
    function elite_quick_action_signature_base(int $leadId, int $expiresAt): string
    {
        return $leadId . '|' . $expiresAt;
    }
}

if (!function_exists('elite_quick_action_signature')) {
    function elite_quick_action_signature(int $leadId, int $expiresAt): string
    {
        $secret = elite_quick_action_secret();

        if ($leadId <= 0 || $expiresAt <= 0 || $secret === '') {
            return '';
        }

        return hash_hmac(
            'sha256',
            elite_quick_action_signature_base($leadId, $expiresAt),
            $secret
        );
    }
}

if (!function_exists('elite_quick_action_url')) {
    function elite_quick_action_url(array $lead, array $context = []): string
    {
        $leadId = (int) elite_string($context['lead_id'] ?? $lead['id'] ?? 0);
        if ($leadId <= 0) {
            return elite_lead_crm_url($lead, $context);
        }

        $base = elite_app_url();
        if ($base === '') {
            return elite_lead_crm_url($lead, $context);
        }

        $secret = elite_quick_action_secret();
        if ($secret === '') {
            return elite_lead_crm_url($lead, $context);
        }

        $expiresAt = time() + elite_quick_action_ttl_seconds();
        $signature = elite_quick_action_signature($leadId, $expiresAt);

        if ($signature === '') {
            return elite_lead_crm_url($lead, $context);
        }

        return $base
            . '/lead-quick-action.php?lead=' . rawurlencode((string) $leadId)
            . '&exp=' . rawurlencode((string) $expiresAt)
            . '&sig=' . rawurlencode($signature);
    }
}

if (!function_exists('elite_notification_button_html')) {
    function elite_notification_button_html(string $label, string $href, string $background, string $color = '#ffffff'): string
    {
        if (trim($href) === '') {
            return '';
        }

        return '<a href="' . elite_escape_html($href) . '" style="display:inline-block;padding:12px 16px;border-radius:12px;background:' . $background . ';color:' . $color . ';text-decoration:none;font-weight:700;font-size:14px;font-family:Arial,sans-serif;margin:0 8px 8px 0;">' . elite_escape_html($label) . '</a>';
    }
}

if (!function_exists('elite_notification_row_html')) {
    function elite_notification_row_html(string $label, string $value): string
    {
        return '<tr>'
            . '<td style="padding:10px 12px;font-weight:700;width:210px;border-bottom:1px solid #ece7df;vertical-align:top;color:#2c241d;">' . elite_escape_html($label) . '</td>'
            . '<td style="padding:10px 12px;border-bottom:1px solid #ece7df;vertical-align:top;color:#5b5147;">' . nl2br(elite_escape_html($value !== '' ? $value : '-')) . '</td>'
            . '</tr>';
    }
}

if (!function_exists('elite_mail_headers_plain')) {
    function elite_mail_headers_plain(?string $replyTo = null, array $bcc = []): string
    {
        $fromName = elite_mail_from_name();
        $fromEmail = elite_mail_from_address();

        $headers = [];
        $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';

        if ($replyTo !== null && trim($replyTo) !== '') {
            $headers[] = 'Reply-To: ' . trim($replyTo);
        }

        foreach ($bcc as $bccEmail) {
            $bccEmail = trim((string) $bccEmail);
            if ($bccEmail !== '') {
                $headers[] = 'Bcc: ' . $bccEmail;
            }
        }

        return implode("\r\n", $headers);
    }
}

if (!function_exists('elite_mail_headers_multipart')) {
    function elite_mail_headers_multipart(string $boundary, ?string $replyTo = null, array $bcc = []): string
    {
        $fromName = elite_mail_from_name();
        $fromEmail = elite_mail_from_address();

        $headers = [];
        $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        if ($replyTo !== null && trim($replyTo) !== '') {
            $headers[] = 'Reply-To: ' . trim($replyTo);
        }

        foreach ($bcc as $bccEmail) {
            $bccEmail = trim((string) $bccEmail);
            if ($bccEmail !== '') {
                $headers[] = 'Bcc: ' . $bccEmail;
            }
        }

        return implode("\r\n", $headers);
    }
}

if (!function_exists('elite_send_mail')) {
    function elite_send_mail(
        string $to,
        string $subject,
        string $message,
        ?string $replyTo = null,
        array $bcc = []
    ): bool {
        $to = trim($to);
        $subject = trim($subject);
        $message = trim($message);

        if ($to === '' || $subject === '' || $message === '') {
            return false;
        }

        $headers = elite_mail_headers_plain($replyTo, $bcc);

        try {
            $sent = @mail($to, $subject, $message, $headers);

            if (!$sent) {
                error_log('Elite Smiles mail() failed for: ' . $to . ' / subject: ' . $subject);
            }

            return $sent;
        } catch (Throwable $e) {
            error_log('Elite Smiles mail exception: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('elite_send_mail_multipart')) {
    function elite_send_mail_multipart(
        string $to,
        string $subject,
        string $plainTextMessage,
        string $htmlMessage,
        ?string $replyTo = null,
        array $bcc = []
    ): bool {
        $to = trim($to);
        $subject = trim($subject);
        $plainTextMessage = trim($plainTextMessage);
        $htmlMessage = trim($htmlMessage);

        if ($to === '' || $subject === '' || $plainTextMessage === '' || $htmlMessage === '') {
            return false;
        }

        $boundary = 'elite_boundary_' . md5((string) microtime(true) . $to . $subject);
        $headers = elite_mail_headers_multipart($boundary, $replyTo, $bcc);

        $body = '';
        $body .= '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $plainTextMessage . "\r\n\r\n";

        $body .= '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $htmlMessage . "\r\n\r\n";

        $body .= '--' . $boundary . "--\r\n";

        try {
            $sent = @mail($to, $subject, $body, $headers);

            if (!$sent) {
                error_log('Elite Smiles multipart mail() failed for: ' . $to . ' / subject: ' . $subject);
            }

            return $sent;
        } catch (Throwable $e) {
            error_log('Elite Smiles multipart mail exception: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('elite_compose_lead_notification_message')) {
    function elite_compose_lead_notification_message(array $lead, array $context = []): string
    {
        $leadId = elite_string($context['lead_id'] ?? $lead['id'] ?? '');
        $createdAt = elite_string($context['created_at'] ?? $lead['created_at'] ?? date('Y-m-d H:i:s'));
        $crmUrl = elite_lead_crm_url($lead, $context);
        $quickActionUrl = elite_quick_action_url($lead, $context);

        $fullName = elite_string($lead['full_name'] ?? '');
        $firstName = elite_string($lead['first_name'] ?? '');
        $lastName = elite_string($lead['last_name'] ?? '');
        $phone = elite_string($lead['phone'] ?? '');
        $email = elite_string($lead['email'] ?? '');
        $procedureInterest = elite_string($lead['procedure_interest'] ?? '');
        $financingNeeded = elite_yes_no_unsure_label(elite_string($lead['financing_needed'] ?? ''));
        $financingOption = elite_financing_option_label(elite_string($lead['financing_option'] ?? ''));
        $preferredContact = elite_preferred_contact_label(elite_string($lead['preferred_contact'] ?? ''));
        $consultationStatus = elite_consultation_status_label(elite_string($lead['consultation_status'] ?? ''));
        $consultationDate = elite_string($lead['consultation_date'] ?? '');
        $landingPage = elite_string($lead['landing_page'] ?? $context['landing_page'] ?? '');
        $source = elite_string($lead['source'] ?? '');
        $sourceMedium = elite_string($lead['source_medium'] ?? '');
        $sourceType = elite_string($lead['source_type'] ?? '');
        $campaign = elite_string($lead['campaign'] ?? ($lead['source_campaign'] ?? ''));
        $keyword = elite_string($lead['trigger_keyword'] ?? '');
        $instagramUsername = elite_string($lead['instagram_username'] ?? '');
        $notes = elite_string($lead['notes'] ?? '');
        $transcription = elite_string($lead['transcription'] ?? ($lead['message'] ?? ''));
        $smsBody = elite_lead_text_message_template($lead);
        $smsLink = elite_sms_link_for_phone($phone, $smsBody);
        $callLink = elite_tel_link_for_phone($phone);

        if ($fullName === '') {
            $fullName = trim($firstName . ' ' . $lastName);
        }

        if ($fullName === '') {
            $fullName = 'Unknown Lead';
        }

        $lines = [];
        $lines[] = 'ELITE SMILES — NEW LEAD ALERT';
        $lines[] = str_repeat('=', 38);
        $lines[] = '';
        $lines[] = 'PATIENT / LEAD SUMMARY';
        $lines[] = '----------------------';
        $lines[] = elite_mail_line('Lead ID', $leadId);
        $lines[] = elite_mail_line('Received At', $createdAt);
        $lines[] = elite_mail_line('Full Name', $fullName);
        $lines[] = elite_mail_line('Phone', $phone !== '' ? elite_format_phone_for_reading($phone) : '');
        $lines[] = elite_mail_line('Email', $email);
        $lines[] = elite_mail_line('Procedure Interest', $procedureInterest);
        $lines[] = elite_mail_line('Preferred Contact', $preferredContact);
        $lines[] = '';
        $lines[] = 'CONSULTATION / FINANCING';
        $lines[] = '------------------------';
        $lines[] = elite_mail_line('Consultation Status', $consultationStatus);
        $lines[] = elite_mail_line('Consultation Date', $consultationDate);
        $lines[] = elite_mail_line('Financing Needed', $financingNeeded);
        $lines[] = elite_mail_line('Financing Option', $financingOption);
        $lines[] = '';
        $lines[] = 'ATTRIBUTION';
        $lines[] = '-----------';
        $lines[] = elite_mail_line('Landing Page', $landingPage);
        $lines[] = elite_mail_line('Source', $source);
        $lines[] = elite_mail_line('Source Medium', $sourceMedium);
        $lines[] = elite_mail_line('Source Type', $sourceType);
        $lines[] = elite_mail_line('Campaign', $campaign);
        $lines[] = elite_mail_line('Trigger Keyword', $keyword);
        $lines[] = elite_mail_line('Instagram Username', $instagramUsername);
        $lines[] = '';
        $lines[] = 'QUICK ACTIONS';
        $lines[] = '-------------';
        $lines[] = elite_mail_line('Open Lead Actions', $quickActionUrl);
        $lines[] = elite_mail_line('Open in CRM', $crmUrl);
        $lines[] = elite_mail_line('Call Lead', $callLink);
        $lines[] = elite_mail_line('Text Lead', $smsLink);
        $lines[] = '';
        $lines[] = 'FOLLOW-UP TEXT';
        $lines[] = '--------------';
        $lines[] = 'Use on: ' . elite_lead_followup_device_label();
        $lines[] = $smsBody;
        $lines[] = '';
        $lines[] = 'TRANSCRIPTION';
        $lines[] = '-------------';
        $lines[] = $transcription !== '' ? $transcription : 'No transcription provided.';
        $lines[] = '';
        $lines[] = 'NOTES';
        $lines[] = '-----';
        $lines[] = $notes !== '' ? $notes : 'No notes provided.';

        return implode("\n", $lines);
    }
}

if (!function_exists('elite_compose_lead_notification_html')) {
    function elite_compose_lead_notification_html(array $lead, array $context = []): string
    {
        $leadId = elite_string($context['lead_id'] ?? $lead['id'] ?? '');
        $createdAt = elite_string($context['created_at'] ?? $lead['created_at'] ?? date('Y-m-d H:i:s'));
        $crmUrl = elite_lead_crm_url($lead, $context);
        $quickActionUrl = elite_quick_action_url($lead, $context);

        $fullName = elite_string($lead['full_name'] ?? '');
        $firstName = elite_string($lead['first_name'] ?? '');
        $lastName = elite_string($lead['last_name'] ?? '');
        $phone = elite_string($lead['phone'] ?? '');
        $email = elite_string($lead['email'] ?? '');
        $procedureInterest = elite_string($lead['procedure_interest'] ?? '');
        $financingNeeded = elite_yes_no_unsure_label(elite_string($lead['financing_needed'] ?? ''));
        $financingOption = elite_financing_option_label(elite_string($lead['financing_option'] ?? ''));
        $preferredContact = elite_preferred_contact_label(elite_string($lead['preferred_contact'] ?? ''));
        $consultationStatus = elite_consultation_status_label(elite_string($lead['consultation_status'] ?? ''));
        $consultationDate = elite_string($lead['consultation_date'] ?? '');
        $landingPage = elite_string($lead['landing_page'] ?? $context['landing_page'] ?? '');
        $source = elite_string($lead['source'] ?? '');
        $sourceMedium = elite_string($lead['source_medium'] ?? '');
        $sourceType = elite_string($lead['source_type'] ?? '');
        $campaign = elite_string($lead['campaign'] ?? ($lead['source_campaign'] ?? ''));
        $keyword = elite_string($lead['trigger_keyword'] ?? '');
        $instagramUsername = elite_string($lead['instagram_username'] ?? '');
        $notes = elite_string($lead['notes'] ?? '');
        $transcription = elite_string($lead['transcription'] ?? ($lead['message'] ?? ''));
        $smsBody = elite_lead_text_message_template($lead);
        $smsUrl = elite_sms_link_for_phone($phone, $smsBody);
        $callUrl = elite_tel_link_for_phone($phone);

        if ($fullName === '') {
            $fullName = trim($firstName . ' ' . $lastName);
        }

        if ($fullName === '') {
            $fullName = 'Unknown Lead';
        }

        $buttonQuick = elite_notification_button_html('Open Lead Actions', $quickActionUrl, '#111111');
        $buttonOpenCrm = elite_notification_button_html('Open in CRM', $crmUrl, '#3f3a33');
        $buttonCall = elite_notification_button_html('Call Lead', $callUrl, '#8a6a45');
        $buttonText = elite_notification_button_html('Text Lead', $smsUrl, '#c9a46a', '#2c241d');

        $html  = '<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>';
        $html .= '<body style="margin:0;padding:24px;background:#f7f4ef;font-family:Arial,sans-serif;color:#2c241d;">';
        $html .= '<div style="max-width:760px;margin:0 auto;background:#ffffff;border:1px solid #ece7df;border-radius:20px;overflow:hidden;">';

        $html .= '<div style="background:#111111;color:#ffffff;padding:28px 30px;">';
        $html .= '<div style="font-size:12px;letter-spacing:2px;text-transform:uppercase;opacity:0.75;">Elite Smiles</div>';
        $html .= '<div style="margin-top:8px;font-size:28px;font-weight:800;line-height:1.2;">New Lead Alert</div>';
        $html .= '<div style="margin-top:10px;font-size:15px;color:#e8ded0;">Premium intake received for ' . elite_escape_html($fullName) . '</div>';
        $html .= '</div>';

        $html .= '<div style="padding:28px 30px;">';

        $html .= '<div style="font-size:18px;font-weight:800;margin-bottom:12px;color:#2c241d;">Quick Actions</div>';
        $html .= '<div style="margin-bottom:22px;">' . $buttonQuick . $buttonOpenCrm . $buttonCall . $buttonText . '</div>';

        $html .= '<div style="font-size:18px;font-weight:800;margin-bottom:12px;color:#2c241d;">Lead Summary</div>';
        $html .= '<table style="width:100%;border-collapse:collapse;margin-bottom:24px;">';
        $html .= elite_notification_row_html('Lead ID', $leadId);
        $html .= elite_notification_row_html('Received At', $createdAt);
        $html .= elite_notification_row_html('Full Name', $fullName);
        $html .= elite_notification_row_html('Phone', $phone !== '' ? elite_format_phone_for_reading($phone) : '');
        $html .= elite_notification_row_html('Email', $email);
        $html .= elite_notification_row_html('Procedure Interest', $procedureInterest);
        $html .= elite_notification_row_html('Preferred Contact', $preferredContact);
        $html .= '</table>';

        $html .= '<div style="font-size:18px;font-weight:800;margin-bottom:12px;color:#2c241d;">Consultation & Financing</div>';
        $html .= '<table style="width:100%;border-collapse:collapse;margin-bottom:24px;">';
        $html .= elite_notification_row_html('Consultation Status', $consultationStatus);
        $html .= elite_notification_row_html('Consultation Date', $consultationDate);
        $html .= elite_notification_row_html('Financing Needed', $financingNeeded);
        $html .= elite_notification_row_html('Financing Option', $financingOption);
        $html .= '</table>';

        $html .= '<div style="font-size:18px;font-weight:800;margin-bottom:12px;color:#2c241d;">Attribution</div>';
        $html .= '<table style="width:100%;border-collapse:collapse;margin-bottom:24px;">';
        $html .= elite_notification_row_html('Landing Page', $landingPage);
        $html .= elite_notification_row_html('Source', $source);
        $html .= elite_notification_row_html('Source Medium', $sourceMedium);
        $html .= elite_notification_row_html('Source Type', $sourceType);
        $html .= elite_notification_row_html('Campaign', $campaign);
        $html .= elite_notification_row_html('Trigger Keyword', $keyword);
        $html .= elite_notification_row_html('Instagram Username', $instagramUsername);
        $html .= '</table>';

        $html .= '<div style="font-size:18px;font-weight:800;margin-bottom:12px;color:#2c241d;">Suggested First Text</div>';
        $html .= '<div style="background:#faf7f2;border:1px solid #ece7df;border-radius:16px;padding:16px 18px;margin-bottom:24px;font-size:15px;line-height:1.6;color:#5b5147;">';
        $html .= nl2br(elite_escape_html($smsBody));
        $html .= '</div>';

        $html .= '<div style="font-size:18px;font-weight:800;margin-bottom:12px;color:#2c241d;">Transcription</div>';
        $html .= '<div style="background:#faf7f2;border:1px solid #ece7df;border-radius:16px;padding:16px 18px;margin-bottom:24px;font-size:15px;line-height:1.6;color:#5b5147;">';
        $html .= nl2br(elite_escape_html($transcription !== '' ? $transcription : 'No transcription provided.'));
        $html .= '</div>';

        $html .= '<div style="font-size:18px;font-weight:800;margin-bottom:12px;color:#2c241d;">Notes</div>';
        $html .= '<div style="background:#faf7f2;border:1px solid #ece7df;border-radius:16px;padding:16px 18px;margin-bottom:8px;font-size:15px;line-height:1.6;color:#5b5147;">';
        $html .= nl2br(elite_escape_html($notes !== '' ? $notes : 'No notes provided.'));
        $html .= '</div>';

        $html .= '</div>';

        $html .= '<div style="padding:18px 30px;background:#f3ede4;border-top:1px solid #ece7df;font-size:12px;color:#7b6f63;">';
        $html .= 'Elite Smiles CRM notification';
        if ($quickActionUrl !== '') {
            $html .= ' • <a href="' . elite_escape_html($quickActionUrl) . '" style="color:#7b6f63;">Open lead actions</a>';
        }
        if ($crmUrl !== '') {
            $html .= ' • <a href="' . elite_escape_html($crmUrl) . '" style="color:#7b6f63;">Open CRM lead</a>';
        }
        $html .= '</div>';

        $html .= '</div></body></html>';

        return $html;
    }
}

if (!function_exists('elite_pushover_app_token')) {
    function elite_pushover_app_token(): string
    {
        return defined('ELITE_PUSHOVER_APP_TOKEN') ? trim((string) ELITE_PUSHOVER_APP_TOKEN) : '';
    }
}

if (!function_exists('elite_pushover_user_key')) {
    function elite_pushover_user_key(): string
    {
        return defined('ELITE_PUSHOVER_USER_KEY') ? trim((string) ELITE_PUSHOVER_USER_KEY) : '';
    }
}

if (!function_exists('elite_pushover_is_enabled')) {
    function elite_pushover_is_enabled(): bool
    {
        return elite_pushover_app_token() !== ''
            && elite_pushover_user_key() !== ''
            && function_exists('curl_init');
    }
}

if (!function_exists('elite_send_pushover_notification')) {
    function elite_send_pushover_notification(
        string $title,
        string $message,
        ?string $url = null,
        ?string $urlTitle = null
    ): bool {
        if (!elite_pushover_is_enabled()) {
            return false;
        }

        $postFields = [
            'token'   => elite_pushover_app_token(),
            'user'    => elite_pushover_user_key(),
            'title'   => trim($title),
            'message' => trim($message),
            'sound'   => 'pushover',
            'priority'=> '0',
        ];

        if ($url !== null && trim($url) !== '') {
            $postFields['url'] = trim($url);
        }

        if ($urlTitle !== null && trim($urlTitle) !== '') {
            $postFields['url_title'] = trim($urlTitle);
        }

        try {
            $ch = curl_init('https://api.pushover.net/1/messages.json');

            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query($postFields),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/x-www-form-urlencoded',
                ],
            ]);

            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response === false || $curlError !== '') {
                error_log('Elite Smiles Pushover cURL error: ' . $curlError);
                return false;
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                error_log('Elite Smiles Pushover HTTP ' . $httpCode . ' response: ' . (string) $response);
                return false;
            }

            $decoded = json_decode((string) $response, true);
            if (!is_array($decoded) || (int) ($decoded['status'] ?? 0) !== 1) {
                error_log('Elite Smiles Pushover invalid response: ' . (string) $response);
                return false;
            }

            return true;
        } catch (Throwable $e) {
            error_log('Elite Smiles Pushover exception: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('elite_send_lead_notification_pushover')) {
    function elite_send_lead_notification_pushover(array $lead, array $context = []): bool
    {
        $fullName = elite_string($lead['full_name'] ?? '');
        if ($fullName === '') {
            $fullName = trim(
                elite_string($lead['first_name'] ?? '') . ' ' . elite_string($lead['last_name'] ?? '')
            );
        }
        if ($fullName === '') {
            $fullName = 'Unknown Lead';
        }

        $phone = elite_string($lead['phone'] ?? '');
        $procedureInterest = elite_string($lead['procedure_interest'] ?? '');
        $preferredContact = elite_preferred_contact_label(elite_string($lead['preferred_contact'] ?? ''));
        $source = elite_string($lead['source'] ?? '');
        $quickActionUrl = elite_quick_action_url($lead, $context);

        $title = 'New Elite Smiles Lead • ' . $fullName;

        $lines = [];
        if ($procedureInterest !== '') {
            $lines[] = 'Procedure: ' . $procedureInterest;
        }
        if ($phone !== '') {
            $lines[] = 'Phone: ' . elite_format_phone_for_reading($phone);
        }
        if ($preferredContact !== 'Not specified') {
            $lines[] = 'Prefers: ' . $preferredContact;
        }
        if ($source !== '') {
            $lines[] = 'Source: ' . $source;
        }
        $lines[] = '';
        $lines[] = 'Tap to open quick actions.';

        return elite_send_pushover_notification(
            $title,
            implode("\n", $lines),
            $quickActionUrl !== '' ? $quickActionUrl : null,
            $quickActionUrl !== '' ? 'Open Lead Actions' : null
        );
    }
}

if (!function_exists('elite_send_lead_notification_email')) {
    function elite_send_lead_notification_email(array $lead, array $context = []): bool
    {
        $to = trim((string) ($context['to'] ?? elite_lead_notification_recipient()));
        if ($to === '') {
            return false;
        }

        $fullName = trim((string) ($lead['full_name'] ?? ''));
        if ($fullName === '') {
            $fullName = trim(
                ((string) ($lead['first_name'] ?? '')) . ' ' . ((string) ($lead['last_name'] ?? ''))
            );
        }

        if ($fullName === '') {
            $fullName = 'Unknown Lead';
        }

        $procedureInterest = trim((string) ($lead['procedure_interest'] ?? ''));
        $source = trim((string) ($lead['source'] ?? 'landing_page'));

        $subjectParts = ['New Elite Smiles Lead', $fullName];

        if ($procedureInterest !== '') {
            $subjectParts[] = $procedureInterest;
        }

        if ($source !== '') {
            $subjectParts[] = $source;
        }

        $subject = implode(' | ', $subjectParts);

        $plainMessage = elite_compose_lead_notification_message($lead, $context);
        $htmlMessage = elite_compose_lead_notification_html($lead, $context);

        $replyTo = null;
        $leadEmail = trim((string) ($lead['email'] ?? ''));
        if ($leadEmail !== '' && filter_var($leadEmail, FILTER_VALIDATE_EMAIL)) {
            $replyTo = $leadEmail;
        }

        $emailSent = elite_send_mail_multipart($to, $subject, $plainMessage, $htmlMessage, $replyTo);
        $pushSent = elite_send_lead_notification_pushover($lead, $context);

        if (!$emailSent) {
            return false;
        }

        return $emailSent || $pushSent;
    }
}

if (!function_exists('elite_send_invite_email')) {
    function elite_send_invite_email(string $toEmail, string $firstName, string $inviteLink): bool
    {
        $toEmail = trim($toEmail);
        $firstName = trim($firstName);
        $inviteLink = trim($inviteLink);

        if ($toEmail === '' || $inviteLink === '') {
            return false;
        }

        $displayName = $firstName !== '' ? $firstName : 'there';

        $subject = 'Your Elite Smiles CRM invite';
        $message = "Hi {$displayName},\n\n"
            . "You have been invited to access the Elite Smiles CRM.\n\n"
            . "Use the secure link below to set your password and activate your account:\n\n"
            . $inviteLink . "\n\n"
            . "For security, this invitation link expires automatically.\n\n"
            . "If you were not expecting this email, you can ignore it.\n\n"
            . "Elite Smiles CRM";

        return elite_send_mail($toEmail, $subject, $message);
    }
}



if (!function_exists('elite_email_to_text_recipient')) {

    function elite_email_to_text_recipient(): string

    {

        if (defined('ELITE_LEAD_EMAIL_TO_TEXT_RECIPIENT')) {
            $configured = trim((string) ELITE_LEAD_EMAIL_TO_TEXT_RECIPIENT);
            if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_EMAIL)) {
                return $configured;
            }
        }

        return '8016037011@txt.att.net';

    }

}



if (!function_exists('elite_compose_email_to_text_lead_alert')) {

    function elite_compose_email_to_text_lead_alert(array $lead, array $context = []): string

    {

        $fullName = elite_string($lead['full_name'] ?? '');
        if ($fullName === '') {
            $fullName = trim(elite_string($lead['first_name'] ?? '') . ' ' . elite_string($lead['last_name'] ?? ''));
        }
        if ($fullName === '') {
            $fullName = 'Unknown';
        }

        $phone = elite_string($lead['phone'] ?? '');
        $source = elite_string($lead['source'] ?? 'website');
        $campaign = elite_string($lead['campaign'] ?? ($lead['source_campaign'] ?? $context['campaign'] ?? ''));
        if ($campaign === '') {
            $campaign = elite_string($lead['landing_page'] ?? $context['landing_page'] ?? 'website');
        }

        $message = 'New lead: ' . $fullName
            . ' | ' . ($phone !== '' ? elite_format_phone_for_reading($phone) : 'no phone')
            . ' | Source: ' . ($source !== '' ? $source : 'website')
            . ' | Campaign: ' . ($campaign !== '' ? $campaign : 'website');

        if (mb_strlen($message) > 155) {
            $message = mb_substr($message, 0, 152) . '...';
        }

        return $message;

    }

}



if (!function_exists('elite_send_lead_email_to_text_alert')) {

    function elite_send_lead_email_to_text_alert(array $lead, array $context = []): bool

    {

        $to = elite_string($context['email_to_text_to'] ?? elite_email_to_text_recipient());
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $message = elite_compose_email_to_text_lead_alert($lead, $context);
        if ($message === '') {
            return false;
        }

        $headers = [];
        $headers[] = 'From: ' . elite_mail_from_name() . ' <' . elite_mail_from_address() . '>';
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';

        try {
            $sent = @mail($to, 'Lead', $message, implode("\r\n", $headers));
            if (!$sent) {
                error_log('Elite Smiles email-to-text alert failed for lead.');
            }
            return $sent;
        } catch (Throwable $e) {
            error_log('Elite Smiles email-to-text exception: ' . $e->getMessage());
            return false;
        }

    }

}



if (!function_exists('elite_send_manual_sms_followup_email')) {

    function elite_send_manual_sms_followup_email(array $lead, string $smsBody, array $context = []): array

    {

        $to = trim((string) ($context['to'] ?? elite_lead_notification_recipient()));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Manual SMS action recipient is not configured.'];
        }

        $smsBody = trim($smsBody);
        if ($smsBody === '') {
            return ['ok' => false, 'message' => 'SMS follow-up message cannot be empty.'];
        }

        $phone = elite_string($lead['phone'] ?? '');
        $smsLink = elite_sms_link_for_phone($phone, $smsBody);
        if ($smsLink === '') {
            return ['ok' => false, 'message' => 'Lead does not have a usable phone number.'];
        }

        $leadId = elite_string($context['lead_id'] ?? $lead['id'] ?? '');
        $fullName = elite_string($lead['full_name'] ?? '');
        if ($fullName === '') {
            $fullName = trim(elite_string($lead['first_name'] ?? '') . ' ' . elite_string($lead['last_name'] ?? ''));
        }
        if ($fullName === '') {
            $fullName = 'Unknown Lead';
        }

        $phoneReadable = elite_format_phone_for_reading($phone);
        $crmUrl = elite_lead_crm_url($lead, $context);
        $procedureInterest = elite_string($lead['procedure_interest'] ?? '');
        $campaign = elite_string($lead['campaign'] ?? ($lead['source_campaign'] ?? $context['campaign'] ?? ''));
        $source = elite_string($lead['source'] ?? '');
        $subject = 'Text follow-up needed: ' . $fullName;

        $plainLines = [
            'Text follow-up needed',
            '',
            'Lead: ' . $fullName . ($leadId !== '' ? ' (#' . $leadId . ')' : ''),
            'Phone: ' . $phoneReadable,
        ];
        if ($procedureInterest !== '') {
            $plainLines[] = 'Interest: ' . $procedureInterest;
        }
        if ($campaign !== '') {
            $plainLines[] = 'Campaign: ' . $campaign;
        }
        if ($source !== '') {
            $plainLines[] = 'Source: ' . $source;
        }
        if ($crmUrl !== '') {
            $plainLines[] = 'CRM: ' . $crmUrl;
        }
        $plainLines[] = '';
        $plainLines[] = 'Suggested text:';
        $plainLines[] = $smsBody;
        $plainLines[] = '';
        $plainLines[] = 'Tap to text:';
        $plainLines[] = $smsLink;
        $plainMessage = implode("\n", $plainLines);

        $htmlMessage = '<!doctype html><html><body style="font-family:Arial,sans-serif;color:#17202a;line-height:1.5;">'
            . '<h2 style="margin:0 0 12px;">Text follow-up needed</h2>'
            . '<p><strong>Lead:</strong> ' . elite_escape_html($fullName) . ($leadId !== '' ? ' #' . elite_escape_html($leadId) : '') . '<br>'
            . '<strong>Phone:</strong> ' . elite_escape_html($phoneReadable) . '</p>';
        if ($procedureInterest !== '' || $campaign !== '' || $source !== '') {
            $htmlMessage .= '<p>';
            if ($procedureInterest !== '') {
                $htmlMessage .= '<strong>Interest:</strong> ' . elite_escape_html($procedureInterest) . '<br>';
            }
            if ($campaign !== '') {
                $htmlMessage .= '<strong>Campaign:</strong> ' . elite_escape_html($campaign) . '<br>';
            }
            if ($source !== '') {
                $htmlMessage .= '<strong>Source:</strong> ' . elite_escape_html($source);
            }
            $htmlMessage .= '</p>';
        }
        $htmlMessage .= '<p style="margin-top:18px;"><a href="' . elite_escape_html($smsLink) . '" style="display:inline-block;background:#123f5d;color:#fff;text-decoration:none;padding:12px 18px;border-radius:6px;font-weight:bold;">Open text message</a></p>'
            . '<p><strong>Suggested text:</strong></p>'
            . '<div style="white-space:pre-wrap;border:1px solid #d8e0e7;border-radius:6px;padding:12px;background:#f7fafc;">' . elite_escape_html($smsBody) . '</div>';
        if ($crmUrl !== '') {
            $htmlMessage .= '<p style="margin-top:18px;"><a href="' . elite_escape_html($crmUrl) . '">Open CRM lead</a></p>';
        }
        $htmlMessage .= '</body></html>';

        $sent = elite_send_mail_multipart($to, $subject, $plainMessage, $htmlMessage, null);
        if (!$sent) {
            return ['ok' => false, 'message' => 'Manual SMS action email failed to send.', 'to' => $to];
        }

        return [
            'ok' => true,
            'message' => 'Manual SMS action email sent.',
            'to' => $to,
            'sms_link' => $smsLink,
            'phone' => elite_phone_for_link($phone),
            'sms_body' => $smsBody,
        ];

    }

}
