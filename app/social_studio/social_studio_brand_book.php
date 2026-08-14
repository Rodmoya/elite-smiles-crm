<?php
declare(strict_types=1);

if (!function_exists('social_studio_brand_book_default')) {
    function social_studio_brand_book_default(): array
    {
        return [
            'identity' => [
                'name' => 'Elite Smiles by Walter Meden DDS',
                'location' => 'Draper, Utah',
                'promise' => 'Premium cosmetic dentistry that helps patients understand their options and imagine a confident, natural-looking smile.',
                'voice' => ['Warm', 'Sincere', 'Premium', 'Clear', 'Confidence-led', 'Educational before promotional'],
            ],
            'colors' => [
                'ivory' => '#F5F1E9', 'warm_white' => '#FFFDFC', 'charcoal' => '#20252D',
                'black' => '#080B12', 'champagne_gold' => '#9B794E', 'burgundy' => '#A93455',
            ],
            'typography' => [
                'display_font' => 'Bodoni MT, Didot, Times New Roman, serif',
                'support_font' => 'Helvetica Neue, Helvetica, Arial, sans-serif',
                'accent_font' => 'Segoe Script, Brush Script MT, cursive',
                'sizes_percent_canvas_width' => ['eyebrow' => 2.0, 'headline' => 6.8, 'subhead' => 3.2, 'body' => 2.1, 'cta' => 2.4, 'location' => 1.7],
                'rules' => ['Display type is elegant serif', 'Support copy is clean sans-serif', 'Script is one short emotional accent only', 'Never use more than three font roles', 'Maintain strong contrast and intentional line breaks'],
            ],
            'composition' => [
                'canvas' => 'Instagram/Facebook portrait 4:5 by default; 1:1 only when inherited from an approved template',
                'safe_area' => 'Keep essential copy at least 6% from every edge',
                'text_positions' => ['left', 'right'],
                'subject_rule' => 'Subject occupies the side opposite the text; full smile and both eyes visible for portraits',
                'spacing' => 'Generous whitespace, one focal idea, 8-point rhythm, no crowded corners',
                'overlay' => 'CRM renders every word, line, panel, and CTA deterministically; generated images contain no typography or logos',
            ],
            'photography' => [
                'lighting' => 'Soft natural daylight with warm neutral whites and premium editorial polish',
                'portrait' => 'One confident adult, realistic skin texture, tack-sharp eyes, bright polished white teeth with credible anatomy',
                'clinical' => 'Complete, accurate 3D anatomy with one clear teaching focal point',
                'background' => 'Warm Draper lifestyle or refined clinical setting with clean negative space',
                'never' => ['Generic dental stock imagery', 'Soft focus or motion blur', 'Cut-off eye or smile', 'Distorted or extra teeth', 'Gray or yellow teeth', 'Neon colors', 'Loud gradients', 'Clutter', 'Artificial plastic skin', 'Words, logos, watermarks, badges, or icons'],
            ],
            'copy' => [
                'headline' => 'One specific patient benefit, question, or confidence moment; concise enough for the approved overlay box',
                'caption' => 'Short paragraphs, clear education, optional 3–5 benefit bullets, then one next step',
                'cta' => 'One complimentary-consultation CTA with Draper context',
                'financing' => 'Mention 0% or flexible financing only for qualified patients and only when useful',
                'never' => ['Treatment prices in social copy', 'Guaranteed outcomes', 'Invented testimonials', 'False urgency or availability', 'Aggressive sales language', 'Unsupported clinical claims'],
            ],
            'scenarios' => [
                'educational' => 'Teach one concrete question first. Use an editorial card, clinical 3D model, smile detail, or simple benefit diagram. Calm headline, 3–5 points maximum, consultation CTA last.',
                'social_ad' => 'Lead with one emotional or practical benefit. Use a premium portrait or smile detail, short overlay, strong visual contrast, and one direct complimentary-consultation CTA.',
                'premium_portrait' => 'Head-and-shoulders portrait opposite the copy zone. Both eyes and the complete smile visible; natural expression; warm neutral wardrobe and environment.',
                'smile_closeup' => 'Smile dominates without losing credible facial context. Teeth are bright white, even, polished, and anatomically believable; avoid isolated floating-mouth crops.',
                'clinical_3d' => 'Accurate complete anatomy, clean ivory or charcoal field, one highlighted concept, restrained labels applied later by CRM, no sensational medical imagery.',
                'benefit_list' => 'One headline plus 3–5 parallel benefits. Consistent icon/line treatment, generous vertical rhythm, no dense paragraphs.',
                'dark_luxury' => 'Charcoal or black field, warm highlights, restrained champagne-gold accents, minimal copy, premium—not nightclub or neon.',
                'before_after' => 'Only authorized clinical imagery. Match crop, angle, scale, exposure, and color. Use neutral educational language; never imply guaranteed results.',
                'life_experience' => 'Connect the smile to photos, conversations, weddings, work, dining, or everyday confidence without inventing a patient story.',
            ],
            'governance' => [
                'remix' => 'Selected approved post is a locked production template. Preserve copy, typography, sizing, positions, colors, and decorative geometry unless the user explicitly requests a permitted change.',
                'original' => 'Use this Brand Book plus one approved Brand Library system. Create new photography and copy; preserve the selected system’s design grammar and deterministic overlay geometry.',
                'approval' => 'Reject unreadable overlays, missing CTA, unsupported claims, embedded image text/logo, blur, implausible anatomy, missing eye, cut-off smile, or off-brand color/type.',
            ],
        ];
    }

    function social_studio_brand_book_ensure_schema(): void
    {
        db_query("CREATE TABLE IF NOT EXISTS social_studio_brand_books (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            version SMALLINT UNSIGNED NOT NULL,
            name VARCHAR(180) NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'active',
            rules_json LONGTEXT NOT NULL,
            change_note VARCHAR(500) NULL,
            created_by INT UNSIGNED NULL,
            activated_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_social_brand_version (version),
            INDEX idx_social_brand_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if (!db_one('SELECT id FROM social_studio_brand_books LIMIT 1')) {
            db_insert('INSERT INTO social_studio_brand_books (version,name,status,rules_json,change_note,activated_at) VALUES (1,:name,"active",:rules,:note,NOW())', [
                'name' => 'Elite Smiles Editorial System',
                'rules' => json_encode(social_studio_brand_book_default(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'note' => 'Initial system consolidated from approved Instagram posts and the Elite Smiles Master CMO.',
            ]);
        }
    }

    function social_studio_active_brand_book(): array
    {
        try {
            $row = db_one('SELECT * FROM social_studio_brand_books WHERE status="active" ORDER BY version DESC LIMIT 1');
            if ($row) {
                $rules = json_decode((string)$row['rules_json'], true);
                if (is_array($rules)) { $row['rules'] = $rules; return $row; }
            }
        } catch (Throwable $e) {
            // Install-time and isolated-test fallback.
        }
        return ['id' => 0, 'version' => 1, 'name' => 'Elite Smiles Editorial System', 'status' => 'active', 'rules' => social_studio_brand_book_default(), 'change_note' => 'Bundled default'];
    }

    function social_studio_brand_book_rules(): array
    {
        return (array)(social_studio_active_brand_book()['rules'] ?? social_studio_brand_book_default());
    }

    function social_studio_brand_book_prompt(): string
    {
        $book = social_studio_active_brand_book();
        return "ACTIVE ELITE SMILES VIRTUAL BRAND BOOK — VERSION " . (int)($book['version'] ?? 1)
            . "\nThis is binding production policy, not optional inspiration. If a user instruction conflicts with it, follow the Brand Book and surface the conflict.\n"
            . json_encode((array)$book['rules'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    function social_studio_save_brand_book(array $rules, string $changeNote, int $createdBy): int
    {
        social_studio_brand_book_ensure_schema();
        $version = (int)(db_one('SELECT COALESCE(MAX(version),0)+1 AS next_version FROM social_studio_brand_books')['next_version'] ?? 1);
        db_execute('UPDATE social_studio_brand_books SET status="archived" WHERE status="active"');
        return (int)db_insert('INSERT INTO social_studio_brand_books (version,name,status,rules_json,change_note,created_by,activated_at) VALUES (:version,:name,"active",:rules,:note,:created_by,NOW())', [
            'version' => $version,
            'name' => 'Elite Smiles Editorial System',
            'rules' => json_encode($rules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'note' => mb_substr(trim($changeNote), 0, 500),
            'created_by' => $createdBy > 0 ? $createdBy : null,
        ]);
    }
}
