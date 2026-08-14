<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/openai.php';
require_once dirname(__DIR__) . '/core/smtp.php';
require_once dirname(__DIR__) . '/social_studio/social_studio_service.php';

if (!function_exists('mailing_ensure_schema')) {
    function mailing_ensure_schema(): void
    {
        db_query("CREATE TABLE IF NOT EXISTS mailing_contacts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            first_name VARCHAR(120) NOT NULL DEFAULT '',
            last_name VARCHAR(120) NOT NULL DEFAULT '',
            full_name VARCHAR(255) NOT NULL DEFAULT '',
            phone VARCHAR(80) NOT NULL DEFAULT '',
            source VARCHAR(120) NOT NULL DEFAULT 'manual',
            language VARCHAR(12) NOT NULL DEFAULT 'en',
            tags TEXT NULL,
            opt_status VARCHAR(30) NOT NULL DEFAULT 'subscribed',
            opt_source VARCHAR(120) NOT NULL DEFAULT 'imported',
            opted_out_at DATETIME NULL,
            last_engaged_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_mailing_contact_email (email),
            KEY idx_mailing_contacts_opt (opt_status),
            KEY idx_mailing_contacts_source (source)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db_query("CREATE TABLE IF NOT EXISTS mailing_campaigns (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(180) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'draft',
            audience_filter VARCHAR(120) NOT NULL DEFAULT 'all_subscribed',
            goal VARCHAR(120) NOT NULL DEFAULT 'education',
            subject VARCHAR(255) NOT NULL DEFAULT '',
            preview_text VARCHAR(255) NOT NULL DEFAULT '',
            hero_title VARCHAR(255) NOT NULL DEFAULT '',
            body_html MEDIUMTEXT NOT NULL,
            body_text MEDIUMTEXT NOT NULL,
            cta_label VARCHAR(120) NOT NULL DEFAULT 'Schedule a Consultation',
            cta_url VARCHAR(500) NOT NULL DEFAULT '',
            image_prompt TEXT NULL,
            image_url VARCHAR(500) NOT NULL DEFAULT '',
            image_storage_key VARCHAR(255) NULL,
            ai_instruction TEXT NULL,
            scheduled_at DATETIME NULL,
            approved_at DATETIME NULL,
            sent_at DATETIME NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_mailing_campaigns_status (status),
            KEY idx_mailing_campaigns_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // MariaDB does not accept a bound parameter in SHOW COLUMNS ... LIKE.
        // The previous prepared statement made the entire module appear unavailable
        // after the tables were created successfully.
        if (!db_one("SHOW COLUMNS FROM mailing_campaigns LIKE 'image_storage_key'")) {
            db_query('ALTER TABLE mailing_campaigns ADD COLUMN image_storage_key VARCHAR(255) NULL AFTER image_url');
        }

        db_query("CREATE TABLE IF NOT EXISTS mailing_recipients (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            campaign_id INT UNSIGNED NOT NULL,
            contact_id INT UNSIGNED NOT NULL,
            email VARCHAR(255) NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'queued',
            tracking_token VARCHAR(120) NOT NULL DEFAULT '',
            provider_response TEXT NULL,
            sent_at DATETIME NULL,
            opened_at DATETIME NULL,
            clicked_at DATETIME NULL,
            unsubscribed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_mailing_recipient (campaign_id, contact_id),
            KEY idx_mailing_recipient_token (tracking_token),
            KEY idx_mailing_recipient_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db_query("CREATE TABLE IF NOT EXISTS mailing_events (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            campaign_id INT UNSIGNED NULL,
            contact_id INT UNSIGNED NULL,
            recipient_id INT UNSIGNED NULL,
            event_type VARCHAR(60) NOT NULL,
            payload_json TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_mailing_events_campaign (campaign_id, created_at),
            KEY idx_mailing_events_contact (contact_id, created_at),
            KEY idx_mailing_events_type (event_type, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

if (!function_exists('mailing_status_labels')) {
    function mailing_status_labels(): array
    {
        return [
            'draft' => 'Draft',
            'review' => 'Needs Review',
            'approved' => 'Approved',
            'scheduled' => 'Scheduled',
            'sending' => 'Sending',
            'sent' => 'Sent',
            'paused' => 'Paused',
        ];
    }
}

if (!function_exists('mailing_log_event')) {
    function mailing_log_event(?int $campaignId, ?int $contactId, ?int $recipientId, string $eventType, array $payload = []): void
    {
        try {
            mailing_ensure_schema();
            db_insert(
                'INSERT INTO mailing_events (campaign_id, contact_id, recipient_id, event_type, payload_json, created_at)
                 VALUES (:campaign_id, :contact_id, :recipient_id, :event_type, :payload_json, NOW())',
                [
                    'campaign_id' => $campaignId ?: null,
                    'contact_id' => $contactId ?: null,
                    'recipient_id' => $recipientId ?: null,
                    'event_type' => $eventType,
                    'payload_json' => $payload ? json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                ]
            );
        } catch (Throwable $e) {
            esm_log('mailings', 'Could not log mailing event.', ['error' => $e->getMessage()]);
        }
    }
}

if (!function_exists('mailing_token_secret')) {
    function mailing_token_secret(): string
    {
        $secret = trim((string)(defined('APP_KEY') ? APP_KEY : ''));
        if ($secret === '') {
            $secret = trim((string)(defined('ELITE_QUICK_ACTION_SECRET') ? ELITE_QUICK_ACTION_SECRET : ''));
        }
        if ($secret === '') {
            throw new RuntimeException('Mailing token secret is not configured. Set APP_KEY or ELITE_QUICK_ACTION_SECRET.');
        }

        return $secret;
    }
}

if (!function_exists('mailing_signed_token')) {
    function mailing_signed_token(int $contactId, string $purpose): string
    {
        $payload = $contactId . '|' . $purpose;
        $sig = hash_hmac('sha256', $payload, mailing_token_secret());
        return rtrim(strtr(base64_encode($payload . '|' . $sig), '+/', '-_'), '=');
    }
}

if (!function_exists('mailing_verify_contact_token')) {
    function mailing_verify_contact_token(string $token, string $purpose): int
    {
        $decoded = base64_decode(strtr($token, '-_', '+/'), true);
        if (!is_string($decoded) || $decoded === '') {
            return 0;
        }
        $parts = explode('|', $decoded);
        if (count($parts) !== 3) {
            return 0;
        }
        [$contactIdRaw, $tokenPurpose, $sig] = $parts;
        $contactId = (int)$contactIdRaw;
        if ($contactId <= 0 || $tokenPurpose !== $purpose) {
            return 0;
        }
        $expected = hash_hmac('sha256', $contactId . '|' . $purpose, mailing_token_secret());
        return hash_equals($expected, $sig) ? $contactId : 0;
    }
}

if (!function_exists('mailing_tracking_token')) {
    function mailing_tracking_token(int $campaignId, int $contactId): string
    {
        return hash_hmac('sha256', $campaignId . '|' . $contactId . '|' . microtime(true), mailing_token_secret());
    }
}

if (!function_exists('mailing_unsubscribe_url')) {
    function mailing_unsubscribe_url(int $contactId): string
    {
        return base_url('app/api/mailing_unsubscribe.php?t=' . rawurlencode(mailing_signed_token($contactId, 'unsubscribe')));
    }
}

if (!function_exists('mailing_open_url')) {
    function mailing_open_url(string $trackingToken): string
    {
        return base_url('app/api/mailing_open.php?t=' . rawurlencode($trackingToken));
    }
}

if (!function_exists('mailing_click_url')) {
    function mailing_click_url(string $trackingToken, string $targetUrl): string
    {
        return base_url('app/api/mailing_click.php?t=' . rawurlencode($trackingToken) . '&u=' . rawurlencode($targetUrl));
    }
}

if (!function_exists('mailing_upsert_contact')) {
    function mailing_upsert_contact(array $contact): int
    {
        mailing_ensure_schema();
        $email = strtolower(trim((string)($contact['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 0;
        }

        $fullName = trim((string)($contact['full_name'] ?? ''));
        $firstName = trim((string)($contact['first_name'] ?? ''));
        $lastName = trim((string)($contact['last_name'] ?? ''));
        if ($fullName === '') {
            $fullName = trim($firstName . ' ' . $lastName);
        }
        if ($fullName !== '' && $firstName === '') {
            $parts = preg_split('/\s+/', $fullName) ?: [];
            $firstName = trim((string)($parts[0] ?? ''));
            $lastName = trim(implode(' ', array_slice($parts, 1)));
        }

        $existing = db_one('SELECT id, opt_status FROM mailing_contacts WHERE email = :email LIMIT 1', ['email' => $email]);
        if ($existing) {
            db_execute(
                "UPDATE mailing_contacts
                 SET first_name = :first_name, last_name = :last_name, full_name = :full_name, phone = :phone, source = :source, language = :language, tags = :tags, updated_at = NOW()
                 WHERE id = :id LIMIT 1",
                [
                    'id' => (int)$existing['id'],
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'full_name' => $fullName,
                    'phone' => trim((string)($contact['phone'] ?? '')),
                    'source' => trim((string)($contact['source'] ?? 'manual')) ?: 'manual',
                    'language' => in_array((string)($contact['language'] ?? 'en'), ['en', 'es'], true) ? (string)$contact['language'] : 'en',
                    'tags' => trim((string)($contact['tags'] ?? '')),
                ]
            );
            return (int)$existing['id'];
        }

        return db_insert(
            "INSERT INTO mailing_contacts (email, first_name, last_name, full_name, phone, source, language, tags, opt_status, opt_source, created_at)
             VALUES (:email, :first_name, :last_name, :full_name, :phone, :source, :language, :tags, 'subscribed', :opt_source, NOW())",
            [
                'email' => $email,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'full_name' => $fullName,
                'phone' => trim((string)($contact['phone'] ?? '')),
                'source' => trim((string)($contact['source'] ?? 'manual')) ?: 'manual',
                'language' => in_array((string)($contact['language'] ?? 'en'), ['en', 'es'], true) ? (string)$contact['language'] : 'en',
                'tags' => trim((string)($contact['tags'] ?? '')),
                'opt_source' => trim((string)($contact['opt_source'] ?? 'imported')) ?: 'imported',
            ]
        );
    }
}

if (!function_exists('mailing_import_contacts_from_text')) {
    function mailing_import_contacts_from_text(string $raw, string $source = 'manual'): array
    {
        $createdOrUpdated = 0;
        $skipped = 0;
        foreach (preg_split('/\r\n|\r|\n/', trim($raw)) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = str_getcsv($line);
            $email = '';
            foreach ($parts as $part) {
                $candidate = strtolower(trim((string)$part));
                if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                    $email = $candidate;
                    break;
                }
            }
            if ($email === '') {
                $skipped++;
                continue;
            }
            $name = trim((string)($parts[0] ?? ''));
            if (str_contains($name, '@')) {
                $name = '';
            }
            $id = mailing_upsert_contact([
                'email' => $email,
                'full_name' => $name,
                'phone' => trim((string)($parts[2] ?? '')),
                'source' => $source,
                'opt_source' => $source === 'dentrix_import' ? 'dentrix_import' : 'manual_import',
            ]);
            $id > 0 ? $createdOrUpdated++ : $skipped++;
        }
        return ['imported' => $createdOrUpdated, 'skipped' => $skipped];
    }
}

if (!function_exists('mailing_dashboard_data')) {
    function mailing_dashboard_data(int $selectedCampaignId = 0): array
    {
        mailing_ensure_schema();
        $counts = [
            'contacts' => (int)(db_value("SELECT COUNT(*) FROM mailing_contacts WHERE opt_status = 'subscribed'") ?? 0),
            'unsubscribed' => (int)(db_value("SELECT COUNT(*) FROM mailing_contacts WHERE opt_status = 'unsubscribed'") ?? 0),
            'drafts' => (int)(db_value("SELECT COUNT(*) FROM mailing_campaigns WHERE status IN ('draft', 'review')") ?? 0),
            'sent' => (int)(db_value("SELECT COUNT(*) FROM mailing_campaigns WHERE status = 'sent'") ?? 0),
        ];
        $campaigns = db_all(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM mailing_recipients r WHERE r.campaign_id = c.id) AS recipient_count,
                    (SELECT COUNT(*) FROM mailing_recipients r WHERE r.campaign_id = c.id AND r.status = 'sent') AS delivered_count,
                    (SELECT COUNT(*) FROM mailing_recipients r WHERE r.campaign_id = c.id AND r.status = 'failed') AS failed_count,
                    (SELECT COUNT(*) FROM mailing_recipients r WHERE r.campaign_id = c.id AND r.opened_at IS NOT NULL) AS opened_count,
                    (SELECT COUNT(*) FROM mailing_recipients r WHERE r.campaign_id = c.id AND r.clicked_at IS NOT NULL) AS clicked_count
             FROM mailing_campaigns c
             ORDER BY c.created_at DESC, c.id DESC
             LIMIT 20"
        );
        $contacts = db_all('SELECT * FROM mailing_contacts ORDER BY updated_at DESC, id DESC LIMIT 12');
        $selected = null;
        if ($selectedCampaignId > 0) {
            foreach ($campaigns as $campaign) {
                if ((int)$campaign['id'] === $selectedCampaignId) {
                    $selected = $campaign;
                    break;
                }
            }
        }
        $selected ??= $campaigns[0] ?? null;
        return ['counts' => $counts, 'campaigns' => $campaigns, 'contacts' => $contacts, 'selected' => $selected];
    }
}

if (!function_exists('mailing_system_health')) {
    function mailing_system_health(bool $databaseReady = true): array
    {
        return [
            'database' => $databaseReady,
            'sender' => elite_smtp_is_configured(),
            'copy_ai' => elite_openai_is_configured(),
            'image_ai' => function_exists('social_studio_generate_image_binary')
                && defined('GOOGLE_GEMINI_API_KEY')
                && trim((string)GOOGLE_GEMINI_API_KEY) !== '',
        ];
    }
}

if (!function_exists('mailing_default_template_html')) {
    function mailing_default_template_html(array $campaign, string $trackingToken = '', int $contactId = 0): string
    {
        $title = e((string)($campaign['hero_title'] ?? $campaign['title'] ?? 'Elite Smiles'));
        $preview = e((string)($campaign['preview_text'] ?? ''));
        $bodyHtml = (string)($campaign['body_html'] ?? '');
        $ctaLabel = e((string)($campaign['cta_label'] ?? 'Schedule a Consultation'));
        $ctaUrl = trim((string)($campaign['cta_url'] ?? ''));
        $trackedCta = $trackingToken !== '' && $ctaUrl !== '' ? mailing_click_url($trackingToken, $ctaUrl) : $ctaUrl;
        $unsubscribe = $contactId > 0 ? mailing_unsubscribe_url($contactId) : base_url('app/api/mailing_unsubscribe.php');
        $imageUrl = mailing_campaign_image_url($campaign);
        $openPixel = $trackingToken !== '' ? '<img src="' . e(mailing_open_url($trackingToken)) . '" alt="" width="1" height="1" style="display:none;width:1px;height:1px">' : '';

        $image = $imageUrl !== ''
            ? '<img src="' . e($imageUrl) . '" alt="" style="display:block;width:100%;max-width:640px;height:auto;border:0;">'
            : '';

        return '<!doctype html><html><body style="margin:0;padding:0;background:#f6f2eb;font-family:Arial,Helvetica,sans-serif;color:#1f2933;">'
            . '<div style="display:none;max-height:0;overflow:hidden;">' . $preview . '</div>'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f6f2eb;padding:28px 14px;"><tr><td align="center">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #e8dfd1;">'
            . '<tr><td style="background:#0b0b0b;padding:28px 30px;text-align:center;"><img src="' . e((string)ELITE_EMAIL_LOGO_URL) . '" alt="Elite Smiles" style="max-width:220px;height:auto;"></td></tr>'
            . ($image !== '' ? '<tr><td>' . $image . '</td></tr>' : '')
            . '<tr><td style="padding:34px 34px 18px;"><h1 style="margin:0 0 12px;font-size:30px;line-height:1.15;color:#111827;">' . $title . '</h1>'
            . '<div style="font-size:16px;line-height:1.7;color:#4b5563;">' . $bodyHtml . '</div></td></tr>'
            . ($trackedCta !== '' ? '<tr><td style="padding:8px 34px 34px;"><a href="' . e($trackedCta) . '" style="display:inline-block;background:#111827;color:#ffffff;text-decoration:none;border-radius:12px;padding:14px 22px;font-weight:700;">' . $ctaLabel . '</a></td></tr>' : '')
            . '<tr><td style="border-top:1px solid #eee4d6;padding:20px 34px 28px;font-size:12px;line-height:1.6;color:#6b7280;">Elite Smiles by Walter Meden DDS<br>11762 South State, Suite 300, Draper, UT 84020<br><a href="' . e($unsubscribe) . '" style="color:#6b7280;text-decoration:underline;">Unsubscribe</a> from Elite Smiles news and offers.</td></tr>'
            . '</table></td></tr></table>' . $openPixel . '</body></html>';
    }
}

if (!function_exists('mailing_campaign_image_url')) {
    function mailing_campaign_image_url(array $campaign): string
    {
        $imageUrl = trim((string)($campaign['image_url'] ?? ''));
        if ($imageUrl !== '') {
            return $imageUrl;
        }
        $key = trim((string)($campaign['image_storage_key'] ?? ''));
        if ($key === '') {
            return '';
        }
        return base_url('app/api/mailing_image.php?campaign_id=' . rawurlencode((string)($campaign['id'] ?? '')));
    }
}

if (!function_exists('mailing_sanitize_body_html')) {
    function mailing_sanitize_body_html(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        return trim(strip_tags($html, '<p><br><ul><ol><li><strong><em><b><i>'));
    }
}

if (!function_exists('mailing_generate_campaign')) {
    function mailing_generate_campaign(string $goal, string $instruction, int $createdBy = 0): int
    {
        mailing_ensure_schema();
        $goal = trim($goal) !== '' ? trim($goal) : 'education';
        $instruction = trim($instruction);
        $fallback = [
            'title' => 'Elite Smiles Update',
            'subject' => 'A quick update from Elite Smiles',
            'preview_text' => 'A friendly note from our team.',
            'hero_title' => 'A Healthier, More Confident Smile Starts With a Conversation',
            'body_text' => "Hi,\n\nWe wanted to share a quick update from Elite Smiles. If you have been thinking about improving your smile, our team can help you understand your options clearly and comfortably.\n\nSchedule a complimentary consultation when you are ready.",
            'body_html' => '<p>Hi,</p><p>We wanted to share a quick update from Elite Smiles. If you have been thinking about improving your smile, our team can help you understand your options clearly and comfortably.</p><p>Schedule a complimentary consultation when you are ready.</p>',
            'cta_label' => 'Schedule a Consultation',
            'cta_url' => base_url('l/veneers-draper-google-v2?utm_source=patient_mailings&utm_medium=email&utm_campaign=elite_smiles_update'),
            'image_prompt' => 'Premium dental newsletter image for Elite Smiles, natural confident smile, elegant black white gold aesthetic, no text, no logo, no watermark.',
        ];

        $data = $fallback;
        if (elite_openai_is_configured()) {
            $schema = [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'title' => ['type' => 'string'],
                    'subject' => ['type' => 'string'],
                    'preview_text' => ['type' => 'string'],
                    'hero_title' => ['type' => 'string'],
                    'body_text' => ['type' => 'string'],
                    'body_html' => ['type' => 'string'],
                    'cta_label' => ['type' => 'string'],
                    'cta_url' => ['type' => 'string'],
                    'image_prompt' => ['type' => 'string'],
                ],
                'required' => ['title', 'subject', 'preview_text', 'hero_title', 'body_text', 'body_html', 'cta_label', 'cta_url', 'image_prompt'],
            ];
            $system = 'You write compliant patient newsletter campaigns for Elite Smiles by Walter Meden DDS in Utah. Be warm, premium, useful, and conversion-focused. Avoid guarantees, fake urgency, fake patient claims, and medical overpromising. Return body_html with simple paragraphs only, no full HTML document.';
            $user = 'Create one patient email newsletter campaign. Goal: ' . $goal . '. Operator direction: ' . ($instruction !== '' ? $instruction : 'Educational Elite Smiles newsletter. Include a consultation CTA. If relevant, mention 0% financing may be available for qualified patients.');
            $result = elite_openai_json_response($system, $user, $schema, 'mailing_campaign');
            if (!empty($result['ok']) && is_array($result['data'] ?? null)) {
                $data = array_merge($fallback, $result['data']);
            }
        }

        return db_insert(
            "INSERT INTO mailing_campaigns
                (title, status, goal, subject, preview_text, hero_title, body_html, body_text, cta_label, cta_url, image_prompt, ai_instruction, created_by, created_at)
             VALUES
                (:title, 'review', :goal, :subject, :preview_text, :hero_title, :body_html, :body_text, :cta_label, :cta_url, :image_prompt, :ai_instruction, :created_by, NOW())",
            [
                'title' => trim((string)$data['title']),
                'goal' => $goal,
                'subject' => trim((string)$data['subject']),
                'preview_text' => trim((string)$data['preview_text']),
                'hero_title' => trim((string)$data['hero_title']),
                'body_html' => mailing_sanitize_body_html((string)$data['body_html']),
                'body_text' => trim((string)$data['body_text']),
                'cta_label' => trim((string)$data['cta_label']),
                'cta_url' => trim((string)$data['cta_url']),
                'image_prompt' => trim((string)$data['image_prompt']),
                'ai_instruction' => $instruction,
                'created_by' => $createdBy > 0 ? $createdBy : null,
            ]
        );
    }
}

if (!function_exists('mailing_campaign')) {
    function mailing_campaign(int $campaignId): ?array
    {
        mailing_ensure_schema();
        $row = db_one('SELECT * FROM mailing_campaigns WHERE id = :id LIMIT 1', ['id' => $campaignId]);
        return $row ?: null;
    }
}

if (!function_exists('mailing_update_status')) {
    function mailing_update_status(int $campaignId, string $status): bool
    {
        $allowed = mailing_status_labels();
        if ($campaignId <= 0 || !isset($allowed[$status])) {
            return false;
        }
        $sets = ['status = :status', 'updated_at = NOW()'];
        $params = ['id' => $campaignId, 'status' => $status];
        if ($status === 'approved') {
            $sets[] = 'approved_at = NOW()';
        }
        return db_execute('UPDATE mailing_campaigns SET ' . implode(', ', $sets) . ' WHERE id = :id LIMIT 1', $params);
    }
}

if (!function_exists('mailing_update_campaign')) {
    function mailing_update_campaign(int $campaignId, array $input): array
    {
        $campaign = mailing_campaign($campaignId);
        if (!$campaign) {
            return ['ok' => false, 'message' => 'Campaign not found.'];
        }
        if (in_array((string)$campaign['status'], ['sending', 'sent'], true)) {
            return ['ok' => false, 'message' => 'Sent campaigns are locked to preserve the delivery record.'];
        }

        $title = trim((string)($input['title'] ?? ''));
        $subject = trim((string)($input['subject'] ?? ''));
        $previewText = trim((string)($input['preview_text'] ?? ''));
        $heroTitle = trim((string)($input['hero_title'] ?? ''));
        $bodyHtml = mailing_sanitize_body_html((string)($input['body_html'] ?? ''));
        $bodyText = trim((string)($input['body_text'] ?? strip_tags($bodyHtml)));
        $ctaLabel = trim((string)($input['cta_label'] ?? ''));
        $ctaUrl = trim((string)($input['cta_url'] ?? ''));

        if ($title === '' || $subject === '' || $heroTitle === '' || $bodyHtml === '' || $ctaLabel === '') {
            return ['ok' => false, 'message' => 'Title, subject, email headline, message, and button label are required.'];
        }
        if (!preg_match('#^https://#i', $ctaUrl)) {
            return ['ok' => false, 'message' => 'The button destination must be a secure https:// URL.'];
        }

        db_query(
            "UPDATE mailing_campaigns
             SET title = :title, subject = :subject, preview_text = :preview_text,
                 hero_title = :hero_title, body_html = :body_html, body_text = :body_text,
                 cta_label = :cta_label, cta_url = :cta_url,
                 status = 'review', approved_at = NULL, updated_at = NOW()
             WHERE id = :id LIMIT 1",
            [
                'id' => $campaignId,
                'title' => $title,
                'subject' => $subject,
                'preview_text' => $previewText,
                'hero_title' => $heroTitle,
                'body_html' => $bodyHtml,
                'body_text' => $bodyText,
                'cta_label' => $ctaLabel,
                'cta_url' => $ctaUrl,
            ]
        );
        return ['ok' => true, 'message' => 'Campaign saved and returned to review.'];
    }
}

if (!function_exists('mailing_send_test')) {
    function mailing_send_test(int $campaignId, string $to): array
    {
        $campaign = mailing_campaign($campaignId);
        if (!$campaign) {
            return ['ok' => false, 'message' => 'Campaign not found.'];
        }
        $to = trim($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Test email address is invalid.'];
        }
        $html = mailing_default_template_html($campaign);
        return elite_smtp_send_mail($to, '[TEST] ' . (string)$campaign['subject'], (string)$campaign['body_text'], null, $html);
    }
}

if (!function_exists('mailing_send_campaign')) {
    function mailing_send_campaign(int $campaignId, int $limit = 50): array
    {
        mailing_ensure_schema();
        $campaign = mailing_campaign($campaignId);
        if (!$campaign) {
            return ['ok' => false, 'message' => 'Campaign not found.'];
        }
        if (!in_array((string)$campaign['status'], ['approved', 'scheduled', 'sending'], true)) {
            return ['ok' => false, 'message' => 'Approve the campaign before sending it.'];
        }
        if (!elite_smtp_is_configured()) {
            return ['ok' => false, 'message' => 'SMTP is not configured. Configure a real sender before sending patient mailings.'];
        }
        $subscribedCount = (int)(db_value("SELECT COUNT(*) FROM mailing_contacts WHERE opt_status = 'subscribed'") ?? 0);
        if ($subscribedCount === 0) {
            return ['ok' => false, 'message' => 'Import at least one subscribed contact before sending.'];
        }

        db_execute("UPDATE mailing_campaigns SET status = 'sending', updated_at = NOW() WHERE id = :id LIMIT 1", ['id' => $campaignId]);
        $contacts = db_all(
            "SELECT c.*
             FROM mailing_contacts c
             LEFT JOIN mailing_recipients r
               ON r.contact_id = c.id
              AND r.campaign_id = :campaign_id
             WHERE c.opt_status = 'subscribed'
               AND r.id IS NULL
             ORDER BY c.id ASC
             LIMIT " . max(1, min(200, $limit)),
            ['campaign_id' => $campaignId]
        );
        $sent = 0;
        $failed = 0;
        foreach ($contacts as $contact) {
            $contactId = (int)$contact['id'];
            $email = trim((string)$contact['email']);
            $token = mailing_tracking_token($campaignId, $contactId);
            $recipientId = db_insert(
                "INSERT INTO mailing_recipients (campaign_id, contact_id, email, status, tracking_token, created_at)
                 VALUES (:campaign_id, :contact_id, :email, 'queued', :tracking_token, NOW())
                 ON DUPLICATE KEY UPDATE tracking_token = VALUES(tracking_token), status = 'queued'",
                ['campaign_id' => $campaignId, 'contact_id' => $contactId, 'email' => $email, 'tracking_token' => $token]
            );
            $existing = db_one('SELECT id FROM mailing_recipients WHERE campaign_id = :campaign_id AND contact_id = :contact_id LIMIT 1', ['campaign_id' => $campaignId, 'contact_id' => $contactId]);
            $recipientId = (int)($existing['id'] ?? $recipientId);
            $html = mailing_default_template_html($campaign, $token, $contactId);
            $unsubscribeUrl = mailing_unsubscribe_url($contactId);
            $headers = [
                'List-Unsubscribe: <' . $unsubscribeUrl . '>',
                'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
            ];
            $result = elite_smtp_send_mail($email, (string)$campaign['subject'], (string)$campaign['body_text'], null, $html, $headers);
            if (!empty($result['ok'])) {
                $sent++;
                db_execute(
                    "UPDATE mailing_recipients SET status = 'sent', sent_at = NOW(), provider_response = :response WHERE id = :id LIMIT 1",
                    ['id' => $recipientId, 'response' => json_encode($result, JSON_UNESCAPED_SLASHES)]
                );
                mailing_log_event($campaignId, $contactId, $recipientId, 'sent', ['email' => $email]);
            } else {
                $failed++;
                db_execute(
                    "UPDATE mailing_recipients SET status = 'failed', provider_response = :response WHERE id = :id LIMIT 1",
                    ['id' => $recipientId, 'response' => json_encode($result, JSON_UNESCAPED_SLASHES)]
                );
                mailing_log_event($campaignId, $contactId, $recipientId, 'failed', ['email' => $email, 'message' => $result['message'] ?? 'Failed']);
            }
        }

        $remaining = (int)(db_value(
            "SELECT COUNT(*)
             FROM mailing_contacts c
             LEFT JOIN mailing_recipients r
               ON r.contact_id = c.id AND r.campaign_id = :campaign_id
             WHERE c.opt_status = 'subscribed' AND r.id IS NULL",
            ['campaign_id' => $campaignId]
        ) ?? 0);
        $finished = $remaining === 0;
        db_query(
            "UPDATE mailing_campaigns
             SET status = :status, sent_at = IF(:finished = 1, NOW(), sent_at), updated_at = NOW()
             WHERE id = :id LIMIT 1",
            ['id' => $campaignId, 'status' => $finished ? 'sent' : 'sending', 'finished' => $finished ? 1 : 0]
        );
        $message = $finished
            ? "Delivery complete: {$sent} sent in this batch, {$failed} failed."
            : "Batch complete: {$sent} sent, {$failed} failed, {$remaining} still queued. Continue sending to process the next batch.";
        return ['ok' => true, 'message' => $message, 'sent' => $sent, 'failed' => $failed, 'remaining' => $remaining, 'finished' => $finished];
    }
}

if (!function_exists('mailing_generate_image_for_campaign')) {
    function mailing_generate_image_for_campaign(int $campaignId): array
    {
        mailing_ensure_schema();
        $campaign = mailing_campaign($campaignId);
        if (!$campaign) {
            return ['ok' => false, 'message' => 'Campaign not found.'];
        }

        $prompt = trim((string)($campaign['image_prompt'] ?? ''));
        if ($prompt === '') {
            $prompt = 'Premium Elite Smiles patient newsletter hero image, elegant dental cosmetic lifestyle, realistic confident smile, black white and warm gold aesthetic, no text, no logo, no watermark.';
        }
        $prompt .= "\n\nEmail hero image. No words, no letters, no logo, no watermark. Leave calm negative space for CRM branding. Realistic dental/cosmetic dentistry tone.";

        $generated = social_studio_generate_image_binary($prompt);
        if (empty($generated['ok']) || !is_string($generated['bytes'] ?? null) || $generated['bytes'] === '') {
            return ['ok' => false, 'message' => (string)($generated['message'] ?? 'Could not generate image.')];
        }

        $ext = (string)($generated['mime_type'] ?? '') === 'image/svg+xml' ? 'svg' : 'png';
        $key = 'mailings/' . $campaignId . '/hero-' . date('Ymd-His') . '.' . $ext;
        $path = social_studio_safe_storage_path($key);
        if (!$path || @file_put_contents($path, $generated['bytes']) === false) {
            return ['ok' => false, 'message' => 'Could not save generated image.'];
        }

        db_execute(
            'UPDATE mailing_campaigns SET image_prompt = :image_prompt, image_storage_key = :image_storage_key, updated_at = NOW() WHERE id = :id LIMIT 1',
            ['id' => $campaignId, 'image_prompt' => $prompt, 'image_storage_key' => $key]
        );

        return ['ok' => true, 'message' => 'Newsletter image generated.', 'image_storage_key' => $key];
    }
}
