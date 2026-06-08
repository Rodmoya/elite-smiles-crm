<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../leads/lead_meta.php';
require_once __DIR__ . '/../leads/lead_service.php';
require_once __DIR__ . '/providers.php';

const SMILE_DESIGN_LOGO_URL = 'https://elitesmilesutah.com/wp-content/uploads/2025/03/ES-Logo-Stack-500-x-150-px.png';

function smile_design_ensure_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    db_query("CREATE TABLE IF NOT EXISTS smile_cases (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        lead_id INT UNSIGNED NULL,
        patient_name VARCHAR(190) NOT NULL,
        email VARCHAR(190) NULL,
        phone VARCHAR(80) NULL,
        procedure_interest VARCHAR(190) NULL,
        selected_style VARCHAR(80) NULL,
        lvi_style_key VARCHAR(80) NULL,
        shade_goal VARCHAR(80) NULL,
        notes TEXT NULL,
        doctor_notes TEXT NULL,
        status VARCHAR(80) NOT NULL DEFAULT 'draft',
        visibility VARCHAR(40) NOT NULL DEFAULT 'internal_only',
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_smile_cases_lead (lead_id),
        INDEX idx_smile_cases_status (status),
        INDEX idx_smile_cases_created (created_at)
    ) {$charset}");

    db_query("CREATE TABLE IF NOT EXISTS smile_case_photos (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        case_id INT UNSIGNED NOT NULL,
        kind VARCHAR(40) NOT NULL DEFAULT 'before',
        source_type VARCHAR(40) NOT NULL DEFAULT 'uploaded',
        storage_key VARCHAR(255) NOT NULL,
        original_name VARCHAR(255) NULL,
        mime_type VARCHAR(120) NOT NULL,
        file_size INT UNSIGNED NOT NULL DEFAULT 0,
        width INT UNSIGNED NULL,
        height INT UNSIGNED NULL,
        sort_order INT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_smile_case_photos_case (case_id),
        INDEX idx_smile_case_photos_kind (kind)
    ) {$charset}");

    db_query("CREATE TABLE IF NOT EXISTS real_result_cases (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(190) NOT NULL,
        patient_label VARCHAR(190) NULL,
        procedure_label VARCHAR(190) NULL,
        style_label VARCHAR(190) NULL,
        story TEXT NULL,
        is_featured TINYINT(1) NOT NULL DEFAULT 0,
        is_published TINYINT(1) NOT NULL DEFAULT 0,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    ) {$charset}");

    db_query("CREATE TABLE IF NOT EXISTS real_result_photo_pairs (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        result_case_id INT UNSIGNED NOT NULL,
        before_storage_key VARCHAR(255) NOT NULL,
        after_storage_key VARCHAR(255) NOT NULL,
        caption VARCHAR(255) NULL,
        sort_order INT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_real_result_pairs_case (result_case_id)
    ) {$charset}");

    db_query("CREATE TABLE IF NOT EXISTS lvi_style_samples (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        style_key VARCHAR(80) NOT NULL,
        title VARCHAR(190) NOT NULL,
        description TEXT NULL,
        sample_storage_key VARCHAR(255) NULL,
        sort_order INT UNSIGNED NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_lvi_style_key (style_key)
    ) {$charset}");

    db_query("CREATE TABLE IF NOT EXISTS smile_preview_links (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        case_id INT UNSIGNED NOT NULL,
        token_hash CHAR(64) NOT NULL,
        purpose VARCHAR(40) NOT NULL DEFAULT 'intake',
        expires_at DATETIME NULL,
        used_at DATETIME NULL,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_smile_preview_token (token_hash),
        INDEX idx_smile_preview_case (case_id),
        INDEX idx_smile_preview_purpose (purpose)
    ) {$charset}");

    db_query("CREATE TABLE IF NOT EXISTS smile_notifications (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        case_id INT UNSIGNED NULL,
        lead_id INT UNSIGNED NULL,
        channel VARCHAR(40) NOT NULL,
        provider VARCHAR(80) NOT NULL DEFAULT 'mock',
        template_key VARCHAR(120) NULL,
        recipient VARCHAR(190) NULL,
        payload_json MEDIUMTEXT NULL,
        status VARCHAR(40) NOT NULL DEFAULT 'placeholder',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_smile_notifications_case (case_id),
        INDEX idx_smile_notifications_lead (lead_id)
    ) {$charset}");

    db_query("CREATE TABLE IF NOT EXISTS ai_generation_jobs (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        case_id INT UNSIGNED NOT NULL,
        provider VARCHAR(80) NOT NULL DEFAULT 'mock',
        status VARCHAR(60) NOT NULL DEFAULT 'draft',
        prompt_summary TEXT NULL,
        request_json MEDIUMTEXT NULL,
        response_json MEDIUMTEXT NULL,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_ai_generation_jobs_case (case_id),
        INDEX idx_ai_generation_jobs_status (status)
    ) {$charset}");

    db_query("CREATE TABLE IF NOT EXISTS ai_generation_versions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        job_id INT UNSIGNED NOT NULL,
        case_id INT UNSIGNED NOT NULL,
        version_label VARCHAR(80) NOT NULL DEFAULT 'v1',
        storage_key VARCHAR(255) NULL,
        metadata_json MEDIUMTEXT NULL,
        is_selected TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ai_generation_versions_job (job_id),
        INDEX idx_ai_generation_versions_case (case_id)
    ) {$charset}");

    db_query("CREATE TABLE IF NOT EXISTS smile_after_versions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        case_id INT UNSIGNED NOT NULL,
        before_photo_id INT UNSIGNED NULL,
        storage_key VARCHAR(255) NOT NULL,
        version_title VARCHAR(190) NOT NULL,
        version_number INT UNSIGNED NOT NULL DEFAULT 1,
        source_type VARCHAR(80) NOT NULL DEFAULT 'manual_upload',
        procedure_label VARCHAR(190) NULL,
        lvi_style_key VARCHAR(80) NULL,
        photo_type VARCHAR(80) NULL,
        notes TEXT NULL,
        mime_type VARCHAR(120) NOT NULL,
        file_size INT UNSIGNED NOT NULL DEFAULT 0,
        width INT UNSIGNED NULL,
        height INT UNSIGNED NULL,
        created_by INT UNSIGNED NULL,
        selected_by_doctor TINYINT(1) NOT NULL DEFAULT 0,
        approved_for_patient_preview TINYINT(1) NOT NULL DEFAULT 0,
        approved_for_office_gallery TINYINT(1) NOT NULL DEFAULT 0,
        approved_for_marketing TINYINT(1) NOT NULL DEFAULT 0,
        archived TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_smile_after_case (case_id),
        INDEX idx_smile_after_selected (case_id, selected_by_doctor),
        INDEX idx_smile_after_preview (case_id, approved_for_patient_preview),
        INDEX idx_smile_after_archived (archived)
    ) {$charset}");

    db_query("CREATE TABLE IF NOT EXISTS lvi_sample_images (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        style_key VARCHAR(80) NOT NULL,
        title VARCHAR(190) NOT NULL,
        description TEXT NULL,
        storage_key VARCHAR(255) NOT NULL,
        mime_type VARCHAR(120) NOT NULL,
        file_size INT UNSIGNED NOT NULL DEFAULT 0,
        procedure_label VARCHAR(190) NULL,
        tags VARCHAR(255) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT UNSIGNED NOT NULL DEFAULT 0,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_lvi_sample_style (style_key),
        INDEX idx_lvi_sample_active (is_active)
    ) {$charset}");

    db_query("CREATE TABLE IF NOT EXISTS smile_sample_cases (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(190) NOT NULL,
        procedure_label VARCHAR(190) NULL,
        lvi_style_key VARCHAR(80) NULL,
        source_type VARCHAR(80) NOT NULL DEFAULT 'lvi_style_sample',
        photo_type VARCHAR(80) NULL,
        before_storage_key VARCHAR(255) NULL,
        after_storage_key VARCHAR(255) NULL,
        description TEXT NULL,
        tags VARCHAR(255) NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT UNSIGNED NOT NULL DEFAULT 0,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_sample_cases_active (active),
        INDEX idx_sample_cases_source (source_type)
    ) {$charset}");

    db_query("CREATE TABLE IF NOT EXISTS smile_audit_events (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        case_id INT UNSIGNED NULL,
        user_id INT UNSIGNED NULL,
        event_key VARCHAR(120) NOT NULL,
        payload_json MEDIUMTEXT NULL,
        ip_address VARCHAR(80) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_smile_audit_case (case_id),
        INDEX idx_smile_audit_event (event_key)
    ) {$charset}");

    db_query("CREATE TABLE IF NOT EXISTS smile_pair_alignments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        pair_type VARCHAR(40) NOT NULL,
        case_id INT UNSIGNED NULL,
        before_photo_id INT UNSIGNED NULL,
        after_version_id INT UNSIGNED NULL,
        real_pair_id INT UNSIGNED NULL,
        photo_type VARCHAR(80) NULL,
        before_zoom DECIMAL(5,2) NOT NULL DEFAULT 1.00,
        before_x DECIMAL(6,2) NOT NULL DEFAULT 0.00,
        before_y DECIMAL(6,2) NOT NULL DEFAULT 0.00,
        after_zoom DECIMAL(5,2) NOT NULL DEFAULT 1.00,
        after_x DECIMAL(6,2) NOT NULL DEFAULT 0.00,
        after_y DECIMAL(6,2) NOT NULL DEFAULT 0.00,
        crop_aspect_ratio VARCHAR(40) NULL,
        rotation DECIMAL(6,2) NOT NULL DEFAULT 0.00,
        saved_by INT UNSIGNED NULL,
        saved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_smile_align_after (after_version_id),
        UNIQUE KEY uniq_smile_align_real_pair (real_pair_id),
        INDEX idx_smile_align_case (case_id),
        INDEX idx_smile_align_pair_type (pair_type)
    ) {$charset}");

    smile_design_schema_upgrade_columns();
    smile_design_seed_lvi_samples();
    $done = true;
}

function smile_design_column_exists(string $table, string $column): bool
{
    return (bool)db_value(
        "SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = :schema
           AND TABLE_NAME = :table
           AND COLUMN_NAME = :column",
        ['schema' => DB_NAME, 'table' => $table, 'column' => $column]
    );
}

function smile_design_schema_upgrade_columns(): void
{
    $changes = [
        ['smile_cases', 'first_name', "ALTER TABLE smile_cases ADD COLUMN first_name VARCHAR(120) NULL AFTER lead_id"],
        ['smile_cases', 'last_name', "ALTER TABLE smile_cases ADD COLUMN last_name VARCHAR(120) NULL AFTER first_name"],
        ['smile_cases', 'consent_status', "ALTER TABLE smile_cases ADD COLUMN consent_status VARCHAR(80) NULL AFTER visibility"],
        ['smile_cases', 'ai_analysis_status', "ALTER TABLE smile_cases ADD COLUMN ai_analysis_status VARCHAR(40) NULL AFTER consent_status"],
        ['smile_cases', 'ai_analysis_provider', "ALTER TABLE smile_cases ADD COLUMN ai_analysis_provider VARCHAR(80) NULL AFTER ai_analysis_status"],
        ['smile_cases', 'ai_analysis_summary', "ALTER TABLE smile_cases ADD COLUMN ai_analysis_summary TEXT NULL AFTER ai_analysis_provider"],
        ['smile_cases', 'ai_analysis_json', "ALTER TABLE smile_cases ADD COLUMN ai_analysis_json MEDIUMTEXT NULL AFTER ai_analysis_summary"],
        ['smile_cases', 'ai_analysis_updated_at', "ALTER TABLE smile_cases ADD COLUMN ai_analysis_updated_at DATETIME NULL AFTER ai_analysis_json"],
        ['smile_case_photos', 'photo_type', "ALTER TABLE smile_case_photos ADD COLUMN photo_type VARCHAR(80) NULL AFTER kind"],
        ['smile_case_photos', 'source_type', "ALTER TABLE smile_case_photos ADD COLUMN source_type VARCHAR(40) NOT NULL DEFAULT 'uploaded' AFTER kind"],
        ['smile_preview_links', 'token_plaintext', "ALTER TABLE smile_preview_links ADD COLUMN token_plaintext VARCHAR(255) NULL AFTER token_hash"],
        ['smile_preview_links', 'view_count', "ALTER TABLE smile_preview_links ADD COLUMN view_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER used_at"],
        ['smile_preview_links', 'last_viewed_at', "ALTER TABLE smile_preview_links ADD COLUMN last_viewed_at DATETIME NULL AFTER view_count"],
        ['smile_preview_links', 'revoked_at', "ALTER TABLE smile_preview_links ADD COLUMN revoked_at DATETIME NULL AFTER last_viewed_at"],
        ['real_result_photo_pairs', 'before_mime_type', "ALTER TABLE real_result_photo_pairs ADD COLUMN before_mime_type VARCHAR(120) NULL AFTER before_storage_key"],
        ['real_result_photo_pairs', 'after_mime_type', "ALTER TABLE real_result_photo_pairs ADD COLUMN after_mime_type VARCHAR(120) NULL AFTER after_storage_key"],
        ['real_result_photo_pairs', 'photo_group', "ALTER TABLE real_result_photo_pairs ADD COLUMN photo_group VARCHAR(80) NULL AFTER result_case_id"],
        ['real_result_cases', 'office_gallery_ready', "ALTER TABLE real_result_cases ADD COLUMN office_gallery_ready TINYINT(1) NOT NULL DEFAULT 0 AFTER is_published"],
        ['real_result_cases', 'marketing_approved', "ALTER TABLE real_result_cases ADD COLUMN marketing_approved TINYINT(1) NOT NULL DEFAULT 0 AFTER office_gallery_ready"],
    ];

    foreach ($changes as [$table, $column, $sql]) {
        try {
            if (!smile_design_column_exists($table, $column)) {
                db_query($sql);
            }
        } catch (Throwable $e) {
            if (function_exists('esm_log')) {
                esm_log('smile_design', 'Schema column upgrade failed', [
                    'table' => $table,
                    'column' => $column,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
}

function smile_design_seed_lvi_samples(): void
{
    $samples = [
        ['natural', 'Natural', 'Soft incisal shape, believable brightness, and balanced symmetry.'],
        ['enhanced', 'Enhanced', 'Noticeable cosmetic improvement while staying proportional and refined.'],
        ['youthful', 'Youthful', 'Softer transitions, fresh proportions, and a brighter but natural finish.'],
        ['hollywood', 'Hollywood', 'High-value brightness with fuller contours and polished line angles.'],
        ['softened', 'Softened', 'Gentle contours and low visual tension for a softer smile line.'],
        ['oval', 'Oval', 'Rounded line angles and elegant tooth form with a calm presentation.'],
        ['dominant', 'Dominant', 'Stronger central presence and confident proportions.'],
        ['functional', 'Functional', 'Balanced esthetics with bite-conscious shape language.'],
        ['mature', 'Mature', 'Subtle refinement that preserves age-appropriate character.'],
        ['vigorous', 'Vigorous', 'Energetic contours and brighter presence without excess sharpness.'],
        ['focused', 'Focused', 'Clean symmetry and controlled brightness for a precise look.'],
        ['aggressive', 'Aggressive', 'Bold cosmetic presence with high contrast and assertive form.'],
    ];

    foreach ($samples as [$key, $title, $description]) {
        db_query(
            "INSERT INTO lvi_style_samples (style_key, title, description, sort_order)
             VALUES (:style_key, :title, :description, :sort_order)
             ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description), sort_order = VALUES(sort_order), is_active = 1",
            ['style_key' => $key, 'title' => $title, 'description' => $description, 'sort_order' => array_search($key, array_column($samples, 0), true) ?: 0]
        );
    }
}

function smile_design_status_labels(): array
{
    return [
        'draft' => 'Draft',
        'intake_sent' => 'Intake Sent',
        'intake_submitted' => 'Intake Submitted',
        'staff_intake_submitted' => 'Staff Intake Submitted',
        'doctor_review' => 'Doctor Review',
        'preview_pending' => 'Preview Pending',
        'preview_ready' => 'Preview Ready',
        'presented' => 'Presented',
        'archived' => 'Archived',
    ];
}

function smile_design_lvi_catalog(): array
{
    return [
        'aggressive' => ['title' => 'Aggressive', 'category' => 'Mature', 'description' => 'Bold, assertive tooth form with stronger line angles and a high-energy cosmetic presence.'],
        'vigorous' => ['title' => 'Vigorous', 'category' => 'Mature', 'description' => 'Strong, masculine-leaning contours with energetic shape and presence.'],
        'focused' => ['title' => 'Focused', 'category' => 'Mature', 'description' => 'Clean symmetry and controlled line angles for a precise, refined look.'],
        'functional' => ['title' => 'Functional', 'category' => 'Mature', 'description' => 'Balanced esthetics with practical, bite-conscious shape language and softer emphasis.'],
        'dominant' => ['title' => 'Dominant', 'category' => 'Mature', 'description' => 'Stronger central dominance and confident proportions for a powerful smile.'],
        'enhanced' => ['title' => 'Enhanced', 'category' => 'Mature', 'description' => 'Noticeable cosmetic refinement with balanced proportion and polished contours.'],
        'natural' => ['title' => 'Natural', 'category' => 'Natural', 'description' => 'Gentle rounded edges and believable anatomy for a soft, traditionally natural smile.'],
        'oval' => ['title' => 'Oval', 'category' => 'Natural', 'description' => 'Softer rounded line angles and curved tooth form for an elegant, calm look.'],
        'softened' => ['title' => 'Softened', 'category' => 'Natural', 'description' => 'Reduced visual tension and gentler contours for a subtle, approachable result.'],
        'hollywood' => ['title' => 'Hollywood', 'category' => 'Youthful', 'description' => 'Bright, symmetrical, polished veneer style with strong cosmetic impact.'],
        'youthful' => ['title' => 'Youthful', 'category' => 'Youthful', 'description' => 'Fresh, lively proportions with rounded form and rejuvenated smile energy.'],
        'mature' => ['title' => 'Mature', 'category' => 'Youthful', 'description' => 'Subtle refinement that preserves age-appropriate character while improving esthetics.'],
        'doctor_selected' => ['title' => 'Doctor Selected', 'category' => 'Custom', 'description' => 'Use doctor judgment to guide the final smile design direction.'],
    ];
}

function smile_design_style_options(): array
{
    $options = [];
    foreach (smile_design_lvi_catalog() as $key => $meta) {
        $options[$key] = (string)$meta['title'];
    }
    return $options;
}

function smile_design_style_detail(string $style): array
{
    $catalog = smile_design_lvi_catalog();
    return $catalog[strtolower(trim($style))] ?? $catalog['natural'];
}

function smile_design_style_map(string $style): string
{
    $detail = smile_design_style_detail($style);
    return $detail['title'] === 'Doctor Selected'
        ? 'Doctor Selected'
        : 'LVI ' . (string)$detail['title'];
}

function smile_design_procedure_options(): array
{
    return [
        'veneers' => 'Veneers',
        'lip_repositioning' => 'Lip Repositioning',
        'veneers_lip_repositioning' => 'Veneers + Lip Repositioning',
        'implants_restoration' => 'Implants / Restoration',
        'all_on_x' => 'All-on-X / Full Smile Restoration',
        'not_sure' => 'Not Sure Yet',
    ];
}

function smile_design_procedure_prompt_guidance(string $procedure): string
{
    $mode = smile_design_procedure_mode($procedure);

    if ($mode === 'veneers_lip_repositioning') {
        return implode(' ', [
            'Procedure-specific direction for Veneers + Lip Repositioning:',
            'This is a combined smile-design case, not lip repositioning alone.',
            'Apply the realistic veneer rules first: improve visible tooth shape, shade, symmetry, width-to-height proportions, incisal edge flow, and small visible spacing only within what porcelain veneers or crowns could plausibly change.',
            'Do not use veneers to simulate root movement, jaw surgery, major orthodontic arch expansion, implant replacement, or a totally different bite.',
            'Also simulate lip repositioning surgery for gummy-smile correction: reduce the visible effect of a hypermobile or short upper lip by limiting how far the upper lip elevates during a full smile.',
            'The after preview should show both a veneer-style dental improvement and a lower, more controlled upper-lip smile line with greatly reduced gingival display.',
            'Keep the gum architecture believable and avoid overdone ultra-white blocky veneers.',
            'Keep the result natural and identity-preserving; the patient should look like a realistic post-treatment version of the same person.',
        ]);
    }

    if ($mode === 'lip_repositioning') {
        return implode(' ', [
            'Procedure-specific direction for Lip Repositioning / Gummy Smile Correction:',
            'This is lip repositioning only, not veneers.',
            'The clinical cause is often a hypermobile or short upper lip: elevator muscles pull the lip too far upward when smiling and expose too much gum.',
            'The surgical concept is an internal upper-lip mucosa repositioning: a small strip of mucosa/connective tissue is removed inside the upper lip and the lip is sutured closer to the teeth, restricting elevator pull without external scarring.',
            'Translate that into the photo by limiting the upward smile travel of the upper lip, not by changing the teeth.',
            'Do not treat this as gum recoloring or gum shadowing; the actual lower border of the upper lip must be visibly lower and more naturally draped.',
            'The visual goal is a visible but natural reduction of excessive gingival display by moving the smiling upper-lip line downward enough for the patient to recognize the surgical change.',
            'If the before photo shows a broad gummy band, reduce that band by about half to two-thirds; the after should not look almost identical to the before.',
            'In the after preview, the inferior border of the upper lip should usually be about 2 to 3 mm lower, up to about 4 mm only for severe gum display when it still looks natural; avoid forcing the lip too far down.',
            'The upper lip should sit around the top edge, cervical contour, and arches of the upper teeth during the full smile; a tiny natural scalloped gum reveal may remain, but the broad central gum band should be substantially reduced.',
            'The superior lip should look less curled or retracted upward and more softly unfolded/draped while preserving the patient’s original lip color, texture, highlights, shadows, and mouth-corner shape.',
            'A slight natural increase in visible superior-lip fullness is expected after lip repositioning because the curled/retracted lip roll unfolds downward; this should read as the same lip relaxing, not cosmetic filler.',
            'Do not make the upper lip thinner, tighter, inflated, swollen, pasted on, or stretched downward; the corrected lip should look a little more unfolded when appropriate and softly/naturally draped over the cervical/top area of the upper teeth.',
            'Do not make this look like a veneer case.',
            'Do not enlarge, replace, straighten, brighten, over-whiten, reshape, recolor, or redesign the teeth just to hide gums.',
            'Preserve the patient identity, tooth shape, tooth color, smile width, face, skin, and expression; only adjust the vertical smile/lip-line relationship needed to simulate lip repositioning.',
            'The upper lip must look visibly lower, less retracted, and slightly more unfolded/fuller when appropriate in the smile, but it should remain the same person with a natural surgical-post-treatment result.',
            'Reduce the broad gummy band clearly, while still prioritizing natural lip drape and tooth preservation over total gum elimination.',
        ]);
    }

    if ($mode === 'restorative_implant') {
        return implode(' ', [
            'Procedure-specific direction for Implants / Restoration:',
            'This is a restorative/prosthetic preview, not a cosmetic-only veneer makeover.',
            'Focus on visible missing, broken, worn, failing, dark, or compromised teeth and restore only those areas that would clinically need crowns, bridges, implant crowns, or implant-supported restorations.',
            'If a tooth is missing, create a plausible replacement tooth emerging naturally from the gum line or bridge/prosthetic space; it must not look pasted on, floating, or disconnected from the bite.',
            'If a tooth is broken or heavily restored, simulate a crown/restoration with realistic contour, contact, shade, and gum emergence that blends with adjacent teeth.',
            'Do not replace every healthy visible tooth unless the case clearly indicates full-mouth rehabilitation.',
            'Do not close large skeletal or orthodontic discrepancies by warping the whole arch; keep bite relationships and occlusion believable.',
            'Keep shade improvement natural and consistent with restored teeth; avoid an overly uniform artificial white smile unless the case is clearly a broad prosthetic rehabilitation.',
            'Preserve patient identity, lip position, facial features, camera angle, and natural smile character.',
        ]);
    }

    if ($mode === 'all_on_x') {
        return implode(' ', [
            'Procedure-specific direction for All-on-X / Full Smile Restoration:',
            'This is a full-arch implant-supported prosthetic rehabilitation preview, not simple veneers.',
            'Use this direction only when the visible case suggests terminal dentition, extensive missing teeth, failing restorations, severe wear, or a full-arch replacement plan.',
            'Simulate a fixed full-arch prosthetic smile with coherent arch form, natural tooth proportions, realistic smile arc, believable midline, and appropriate lip support.',
            'A full-arch prosthesis typically reads as a coordinated set of prosthetic teeth rather than unrelated individual natural teeth; make it clean and harmonious but not fake, flat, or overly white.',
            'If gum tissue is visible, keep prosthetic/gum transitions plausible and do not create surgical wounds, sutures, blood, metal implants, screws, or technical components.',
            'Respect vertical dimension, bite plausibility, occlusal plane, and the patient lip envelope; do not change facial bone structure, age, cheeks, jaw, nose, eyes, skin, hair, or overall identity.',
            'If the source only shows one arch clearly, limit the preview to the visible treated arch and avoid inventing hidden lower or posterior anatomy.',
            'If the case does not visually justify full-arch replacement, keep the preview conservative and flag that doctor review is required rather than over-treating.',
        ]);
    }

    if ($mode === 'veneers') {
        return implode(' ', [
            'Procedure-specific direction for Veneers:',
            'This is a cosmetic veneer smile-design preview, not orthodontics, implants, or full-mouth reconstruction.',
            'Improve visible anterior tooth shape, shade, symmetry, proportion, incisal edge flow, line angles, and small visible chips or minor spacing in a way that porcelain veneers or minimal-prep restorations could realistically achieve.',
            'Keep tooth count, root position, jaw position, lip posture, and broad arch relationship believable; do not simulate major orthodontic movement, extraction, implant replacement, or gum surgery.',
            'Respect existing gum architecture and papillae unless a very small cosmetic contour refinement is clearly needed.',
            'Shade should be natural and patient-appropriate, not opaque neon white; retain lifelike translucency, texture, and individual tooth anatomy.',
            'Follow the selected LVI style as an aesthetic direction, but keep it within a realistic clinical envelope for the patient photo.',
            'Preserve patient identity, face, skin, hair, lips, camera angle, lighting, and natural expression.',
        ]);
    }

    return implode(' ', [
        'Procedure-specific direction for Not Sure Yet / Diagnostic Preview:',
        'The selected procedure is uncertain, so do not invent an aggressive or irreversible treatment plan.',
        'Use the visible photo and case analysis to choose the most conservative realistic preview direction.',
        'Improve only the clearly visible smile issues that are safe to preview cosmetically: modest shade, small shape refinements, minor edge symmetry, or obvious restoration needs.',
        'If missing teeth, failing teeth, severe wear, gum display, or full-arch problems are visible, describe the likely treatment direction in analysis and keep the image preview cautious.',
        'Do not simulate veneers, implants, lip surgery, or full-arch replacement unless the visible evidence and doctor notes support that direction.',
        'Preserve patient identity, facial features, lips, skin, lighting, and natural smile character.',
    ]);
}

function smile_design_procedure_mode(string $procedure): string
{
    $normalized = strtolower(trim($procedure));
    $normalized = str_replace(['_', '-', '/', '+'], ' ', $normalized);
    $hasVeneers = str_contains($normalized, 'veneer');
    $hasLipRepositioning = str_contains($normalized, 'lip reposition') || str_contains($normalized, 'gummy smile');
    $hasAllOnX = str_contains($normalized, 'all on') || str_contains($normalized, 'full smile restoration') || str_contains($normalized, 'full arch');

    if ($hasVeneers && $hasLipRepositioning) {
        return 'veneers_lip_repositioning';
    }
    if ($hasLipRepositioning) {
        return 'lip_repositioning';
    }
    if ($hasAllOnX) {
        return 'all_on_x';
    }
    if (str_contains($normalized, 'implant') || str_contains($normalized, 'restoration') || str_contains($normalized, 'all on')) {
        return 'restorative_implant';
    }
    if ($hasVeneers) {
        return 'veneers';
    }
    return 'general';
}

function smile_design_photo_type_options(): array
{
    return [
        'front' => 'Front',
        'smile_close_up' => 'Smile Close-Up',
        'close_up_smile' => 'Close-Up Smile',
        'left_45' => 'Left 45',
        'right_45' => 'Right 45',
        'full_head' => 'Full Head',
        'smile_close_up' => 'Smile Close-Up',
        'relaxed_lips' => 'Relaxed Lips',
        'retracted' => 'Retracted',
        'profile' => 'Profile',
    ];
}

function smile_design_source_type_labels(): array
{
    return [
        'manual_upload' => 'Manual Upload',
        'gem_test_output' => 'Gem Test Output',
        'ai_preview' => 'AI Preview Placeholder',
        'actual_clinical_after' => 'Actual Clinical After',
        'other' => 'Other',
    ];
}

function smile_design_photo_source_labels(): array
{
    return [
        'uploaded' => 'Uploaded',
        'ai_reference' => 'AI Reference',
    ];
}

function smile_design_after_label(array $version): array
{
    $source = (string)($version['source_type'] ?? '');
    if ($source === 'actual_clinical_after') {
        return ['Actual Patient Result', 'Individual results may vary.'];
    }
    if ($source === 'gem_test_output' || $source === 'ai_preview' || $source === 'manual_upload') {
        return ['AI Smile Preview', 'Cosmetic visualization. Not a clinical guarantee. Individual results may vary.'];
    }
    return ['Smile Design Preview', 'Cosmetic visualization. Not a clinical guarantee. Individual results may vary.'];
}

function smile_design_alignment_defaults(): array
{
    return [
        'before_zoom' => 1.0,
        'before_x' => 0.0,
        'before_y' => 0.0,
        'after_zoom' => 1.0,
        'after_x' => 0.0,
        'after_y' => 0.0,
        'crop_aspect_ratio' => '4:3',
        'rotation' => 0.0,
    ];
}

function smile_design_normalize_alignment(array $alignment = []): array
{
    $defaults = smile_design_alignment_defaults();
    $normalized = $defaults;
    foreach (['before_zoom', 'after_zoom'] as $key) {
        $value = (float)($alignment[$key] ?? $defaults[$key]);
        $normalized[$key] = max(0.5, min(2.5, $value));
    }
    foreach (['before_x', 'before_y', 'after_x', 'after_y'] as $key) {
        $value = (float)($alignment[$key] ?? $defaults[$key]);
        $normalized[$key] = max(-50.0, min(50.0, $value));
    }
    $normalized['rotation'] = max(-8.0, min(8.0, (float)($alignment['rotation'] ?? 0)));
    $aspect = trim((string)($alignment['crop_aspect_ratio'] ?? $defaults['crop_aspect_ratio']));
    $normalized['crop_aspect_ratio'] = $aspect !== '' ? $aspect : $defaults['crop_aspect_ratio'];
    foreach (['id', 'pair_type', 'case_id', 'before_photo_id', 'after_version_id', 'real_pair_id', 'photo_type', 'saved_by', 'saved_at', 'updated_at'] as $key) {
        if (array_key_exists($key, $alignment)) {
            $normalized[$key] = $alignment[$key];
        }
    }
    return $normalized;
}

function smile_design_alignment_for_after(array $version): array
{
    $row = null;
    if (!empty($version['id'])) {
        $row = db_one('SELECT * FROM smile_pair_alignments WHERE after_version_id = :after_version_id LIMIT 1', ['after_version_id' => (int)$version['id']]);
    }
    $alignment = smile_design_normalize_alignment($row ?: []);
    $alignment['pair_type'] = 'case_after';
    $alignment['case_id'] = (int)($version['case_id'] ?? 0);
    $alignment['before_photo_id'] = (int)($version['before_photo_id'] ?? 0) ?: null;
    $alignment['after_version_id'] = (int)($version['id'] ?? 0) ?: null;
    $alignment['photo_type'] = (string)($version['photo_type'] ?? '');
    return $alignment;
}

function smile_design_alignment_for_real_pair(int $realPairId): array
{
    $row = db_one('SELECT * FROM smile_pair_alignments WHERE real_pair_id = :real_pair_id LIMIT 1', ['real_pair_id' => $realPairId]);
    $alignment = smile_design_normalize_alignment($row ?: []);
    $alignment['pair_type'] = 'real_result';
    $alignment['real_pair_id'] = $realPairId;
    return $alignment;
}

function smile_design_save_alignment(array $data, ?int $userId = null): bool
{
    $pairType = (string)($data['pair_type'] ?? '');
    if (!in_array($pairType, ['case_after', 'real_result'], true)) {
        return false;
    }
    $alignment = smile_design_normalize_alignment($data);
    $caseId = (int)($data['case_id'] ?? 0) ?: null;
    $beforePhotoId = (int)($data['before_photo_id'] ?? 0) ?: null;
    $afterVersionId = (int)($data['after_version_id'] ?? 0) ?: null;
    $realPairId = (int)($data['real_pair_id'] ?? 0) ?: null;
    if ($pairType === 'case_after' && !$afterVersionId) {
        return false;
    }
    if ($pairType === 'real_result' && !$realPairId) {
        return false;
    }

    db_query(
        "INSERT INTO smile_pair_alignments
         (pair_type, case_id, before_photo_id, after_version_id, real_pair_id, photo_type, before_zoom, before_x, before_y, after_zoom, after_x, after_y, crop_aspect_ratio, rotation, saved_by, saved_at)
         VALUES
         (:pair_type, :case_id, :before_photo_id, :after_version_id, :real_pair_id, :photo_type, :before_zoom, :before_x, :before_y, :after_zoom, :after_x, :after_y, :crop_aspect_ratio, :rotation, :saved_by, NOW())
         ON DUPLICATE KEY UPDATE
           before_zoom = VALUES(before_zoom),
           before_x = VALUES(before_x),
           before_y = VALUES(before_y),
           after_zoom = VALUES(after_zoom),
           after_x = VALUES(after_x),
           after_y = VALUES(after_y),
           crop_aspect_ratio = VALUES(crop_aspect_ratio),
           rotation = VALUES(rotation),
           saved_by = VALUES(saved_by),
           saved_at = NOW()",
        [
            'pair_type' => $pairType,
            'case_id' => $caseId,
            'before_photo_id' => $beforePhotoId,
            'after_version_id' => $afterVersionId,
            'real_pair_id' => $realPairId,
            'photo_type' => trim((string)($data['photo_type'] ?? '')) ?: null,
            'before_zoom' => $alignment['before_zoom'],
            'before_x' => $alignment['before_x'],
            'before_y' => $alignment['before_y'],
            'after_zoom' => $alignment['after_zoom'],
            'after_x' => $alignment['after_x'],
            'after_y' => $alignment['after_y'],
            'crop_aspect_ratio' => $alignment['crop_aspect_ratio'],
            'rotation' => $alignment['rotation'],
            'saved_by' => $userId,
        ]
    );

    smile_design_audit($caseId, 'alignment_saved', ['pair_type' => $pairType, 'after_version_id' => $afterVersionId, 'real_pair_id' => $realPairId], $userId);
    return true;
}

function smile_design_reset_alignment(array $data, ?int $userId = null): bool
{
    $pairType = (string)($data['pair_type'] ?? '');
    $caseId = (int)($data['case_id'] ?? 0) ?: null;
    $afterVersionId = (int)($data['after_version_id'] ?? 0) ?: null;
    $realPairId = (int)($data['real_pair_id'] ?? 0) ?: null;
    if ($pairType === 'case_after' && $afterVersionId) {
        db_execute('DELETE FROM smile_pair_alignments WHERE after_version_id = :after_version_id', ['after_version_id' => $afterVersionId]);
    } elseif ($pairType === 'real_result' && $realPairId) {
        db_execute('DELETE FROM smile_pair_alignments WHERE real_pair_id = :real_pair_id', ['real_pair_id' => $realPairId]);
    } else {
        return false;
    }
    smile_design_audit($caseId, 'alignment_reset', ['pair_type' => $pairType, 'after_version_id' => $afterVersionId, 'real_pair_id' => $realPairId], $userId);
    return true;
}

function smile_design_private_root(): string
{
    return storage_path('smile-design');
}

function smile_design_token_hash(string $token): string
{
    $key = defined('APP_KEY') && APP_KEY !== '' ? APP_KEY : 'elite-smiles-crm';
    return hash_hmac('sha256', $token, $key);
}

function smile_design_generate_token(): string
{
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
}

function smile_design_create_link(int $caseId, string $purpose, ?int $createdBy = null, int $days = 30): string
{
    $token = smile_design_generate_token();
    $expiresAt = date('Y-m-d H:i:s', time() + (max(1, $days) * 86400));
    db_insert(
        "INSERT INTO smile_preview_links (case_id, token_hash, token_plaintext, purpose, expires_at, created_by)
         VALUES (:case_id, :token_hash, :token_plaintext, :purpose, :expires_at, :created_by)",
        [
            'case_id' => $caseId,
            'token_hash' => smile_design_token_hash($token),
            'token_plaintext' => $token,
            'purpose' => $purpose,
            'expires_at' => $expiresAt,
            'created_by' => $createdBy,
        ]
    );
    return $token;
}

function smile_design_preview_link_url(array $link): ?string
{
    $token = trim((string)($link['token_plaintext'] ?? ''));
    if ($token === '') {
        return null;
    }

    return base_url('smile-design/preview/' . rawurlencode($token));
}

function smile_design_active_preview_link(int $caseId): ?array
{
    return db_one(
        "SELECT *
         FROM smile_preview_links
         WHERE case_id = :case_id
           AND purpose = 'preview'
           AND revoked_at IS NULL
           AND (expires_at IS NULL OR expires_at > NOW())
         ORDER BY id DESC
         LIMIT 1",
        ['case_id' => $caseId]
    ) ?: null;
}

function smile_design_issue_or_reuse_preview_link(int $caseId, ?int $createdBy = null, int $days = 14): array
{
    $active = smile_design_active_preview_link($caseId);
    if ($active && trim((string)($active['token_plaintext'] ?? '')) !== '') {
        $active['preview_url'] = smile_design_preview_link_url($active);
        return ['created' => false, 'link' => $active];
    }

    if ($active) {
        smile_design_revoke_preview_links($caseId, $createdBy);
        smile_design_audit($caseId, 'preview_link_reissued_for_reuse', ['legacy_link_id' => (int)$active['id']], $createdBy);
    }

    $disabled = db_one(
        "SELECT *
         FROM smile_preview_links
         WHERE case_id = :case_id
           AND purpose = 'preview'
           AND token_plaintext IS NOT NULL
           AND token_plaintext <> ''
         ORDER BY id DESC
         LIMIT 1",
        ['case_id' => $caseId]
    );
    if ($disabled) {
        $expiresAt = date('Y-m-d H:i:s', time() + (max(1, $days) * 86400));
        db_execute(
            "UPDATE smile_preview_links
             SET revoked_at = NULL,
                 expires_at = :expires_at
             WHERE id = :id
               AND case_id = :case_id
               AND purpose = 'preview'",
            [
                'id' => (int)$disabled['id'],
                'case_id' => $caseId,
                'expires_at' => $expiresAt,
            ]
        );
        $link = db_one(
            "SELECT *
             FROM smile_preview_links
             WHERE id = :id
               AND case_id = :case_id
               AND purpose = 'preview'
             LIMIT 1",
            ['id' => (int)$disabled['id'], 'case_id' => $caseId]
        ) ?: $disabled;
        $link['preview_url'] = smile_design_preview_link_url($link);
        return ['created' => false, 'reactivated' => true, 'link' => $link];
    }

    $token = smile_design_create_link($caseId, 'preview', $createdBy, $days);
    $link = db_one(
        "SELECT *
         FROM smile_preview_links
         WHERE case_id = :case_id
           AND purpose = 'preview'
           AND token_hash = :token_hash
         ORDER BY id DESC
         LIMIT 1",
        ['case_id' => $caseId, 'token_hash' => smile_design_token_hash($token)]
    ) ?: null;

    if (!$link) {
        return ['created' => true, 'link' => null];
    }

    $link['preview_url'] = smile_design_preview_link_url($link);
    return ['created' => true, 'link' => $link];
}

function smile_design_verify_token(string $token, string $purpose): ?array
{
    $token = trim($token);
    if ($token === '') {
        return null;
    }

    $row = db_one(
        "SELECT spl.*, sc.patient_name, sc.email, sc.phone, sc.status, sc.visibility
         FROM smile_preview_links spl
         INNER JOIN smile_cases sc ON sc.id = spl.case_id
         WHERE spl.token_hash = :token_hash
           AND spl.purpose = :purpose
           AND spl.revoked_at IS NULL
           AND (spl.expires_at IS NULL OR spl.expires_at > NOW())
         LIMIT 1",
        ['token_hash' => smile_design_token_hash($token), 'purpose' => $purpose]
    );

    return $row ?: null;
}

function smile_design_record_preview_view(array $link, string $token): void
{
    db_execute(
        'UPDATE smile_preview_links SET view_count = view_count + 1, last_viewed_at = NOW() WHERE id = :id',
        ['id' => (int)$link['id']]
    );
    smile_design_audit((int)$link['case_id'], 'preview_viewed', ['link_id' => (int)$link['id']], null);
}

function smile_design_revoke_preview_links(int $caseId, ?int $userId = null): void
{
    db_execute(
        "UPDATE smile_preview_links SET revoked_at = NOW() WHERE case_id = :case_id AND purpose = 'preview' AND revoked_at IS NULL",
        ['case_id' => $caseId]
    );
    smile_design_audit($caseId, 'preview_link_revoked', [], $userId);
}

function smile_design_delete_preview_link(int $caseId, int $linkId, ?int $userId = null): bool
{
    $link = db_one(
        "SELECT * FROM smile_preview_links
         WHERE id = :id AND case_id = :case_id AND purpose = 'preview'
         LIMIT 1",
        ['id' => $linkId, 'case_id' => $caseId]
    );
    if (!$link) {
        return false;
    }

    db_execute(
        "DELETE FROM smile_preview_links
         WHERE id = :id AND case_id = :case_id AND purpose = 'preview'",
        ['id' => $linkId, 'case_id' => $caseId]
    );

    smile_design_audit($caseId, 'preview_link_deleted', ['link_id' => $linkId], $userId);
    return true;
}

function smile_design_audit(?int $caseId, string $eventKey, array $payload = [], ?int $userId = null): void
{
    db_insert(
        "INSERT INTO smile_audit_events (case_id, user_id, event_key, payload_json, ip_address)
         VALUES (:case_id, :user_id, :event_key, :payload_json, :ip_address)",
        [
            'case_id' => $caseId,
            'user_id' => $userId,
            'event_key' => $eventKey,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]
    );
}

function smile_design_match_or_create_lead(array $data, array $user = []): int
{
    $leadData = [
        'full_name' => trim((string)($data['patient_name'] ?? $data['full_name'] ?? '')),
        'phone' => trim((string)($data['phone'] ?? '')),
        'email' => strtolower(trim((string)($data['email'] ?? ''))),
        'procedure_interest' => trim((string)($data['procedure_interest'] ?? 'Smile Design Preview')),
        'source' => 'smile_design_intake',
        'source_medium' => 'crm',
        'source_type' => 'smile_design',
        'landing_page' => 'Smile Design Engine',
        'campaign' => 'Smile Design Engine',
        'status' => 'new_lead',
        'consultation_status' => 'requested',
        'lead_value' => '10000',
        'refresh_duplicate' => true,
        'notes' => trim((string)($data['notes'] ?? '')),
    ];

    $result = lead_create_minimal($leadData, $user);
    return !empty($result['ok']) ? (int)($result['lead_id'] ?? 0) : 0;
}

function smile_design_create_case(array $data, ?int $createdBy = null): int
{
    smile_design_ensure_schema();
    $patientName = trim((string)($data['patient_name'] ?? ''));
    if ($patientName === '') {
        $patientName = 'Smile Design Patient';
    }

    $procedureInterest = trim((string)($data['procedure_interest'] ?? 'Smile Design Preview'));
    $isLipRepositionOnly = smile_design_procedure_mode($procedureInterest) === 'lip_repositioning';
    $styleKey = strtolower(trim((string)($data['selected_style'] ?? 'natural')));
    if (!array_key_exists($styleKey, smile_design_style_options())) {
        $styleKey = 'natural';
    }
    if ($isLipRepositionOnly) {
        $styleKey = '';
    }

    $caseId = db_insert(
        "INSERT INTO smile_cases
         (lead_id, first_name, last_name, patient_name, email, phone, procedure_interest, selected_style, lvi_style_key, shade_goal, notes, status, visibility, consent_status, created_by)
         VALUES
         (:lead_id, :first_name, :last_name, :patient_name, :email, :phone, :procedure_interest, :selected_style, :lvi_style_key, :shade_goal, :notes, :status, :visibility, :consent_status, :created_by)",
        [
            'lead_id' => (int)($data['lead_id'] ?? 0) ?: null,
            'first_name' => trim((string)($data['first_name'] ?? '')) ?: null,
            'last_name' => trim((string)($data['last_name'] ?? '')) ?: null,
            'patient_name' => $patientName,
            'email' => strtolower(trim((string)($data['email'] ?? ''))) ?: null,
            'phone' => trim((string)($data['phone'] ?? '')) ?: null,
            'procedure_interest' => $procedureInterest ?: null,
            'selected_style' => $styleKey !== '' ? $styleKey : null,
            'lvi_style_key' => $styleKey !== '' ? smile_design_style_map($styleKey) : null,
            'shade_goal' => trim((string)($data['shade_goal'] ?? 'Natural bright')) ?: null,
            'notes' => trim((string)($data['notes'] ?? '')) ?: null,
            'status' => trim((string)($data['status'] ?? 'draft')) ?: 'draft',
            'visibility' => trim((string)($data['visibility'] ?? 'internal_only')) ?: 'internal_only',
            'consent_status' => trim((string)($data['consent_status'] ?? 'not_recorded')) ?: 'not_recorded',
            'created_by' => $createdBy,
        ]
    );

    smile_design_audit($caseId, 'case_created', ['status' => $data['status'] ?? 'draft'], $createdBy);
    return $caseId;
}

function smile_design_case(int $caseId): ?array
{
    $case = db_one('SELECT * FROM smile_cases WHERE id = :id LIMIT 1', ['id' => $caseId]);
    return $case ?: null;
}

function smile_design_case_analysis(int $caseId): ?array
{
    $case = smile_design_case($caseId);
    if (!$case) {
        return null;
    }

    $decoded = null;
    if (!empty($case['ai_analysis_json']) && is_string($case['ai_analysis_json'])) {
        $json = json_decode((string)$case['ai_analysis_json'], true);
        if (is_array($json)) {
            $decoded = $json;
        }
    }

    return [
        'status' => (string)($case['ai_analysis_status'] ?? ''),
        'provider' => (string)($case['ai_analysis_provider'] ?? ''),
        'summary' => trim((string)($case['ai_analysis_summary'] ?? '')),
        'analysis' => $decoded,
        'updated_at' => (string)($case['ai_analysis_updated_at'] ?? ''),
    ];
}

function smile_design_case_photos(int $caseId): array
{
    return db_all(
        "SELECT * FROM smile_case_photos
         WHERE case_id = :case_id
         ORDER BY sort_order ASC,
                  FIELD(COALESCE(photo_type, 'front'), 'front', 'left_45', 'right_45', 'smile_close_up', 'close_up_smile', 'relaxed_lips', 'retracted', 'profile', 'full_head'),
                  FIELD(COALESCE(source_type, 'uploaded'), 'uploaded', 'ai_reference'),
                  id ASC",
        ['case_id' => $caseId]
    );
}

function smile_design_before_photos(int $caseId): array
{
    return db_all(
        "SELECT * FROM smile_case_photos
         WHERE case_id = :case_id AND kind = 'before'
         ORDER BY sort_order ASC,
                  FIELD(COALESCE(photo_type, 'front'), 'front', 'left_45', 'right_45', 'smile_close_up', 'close_up_smile', 'relaxed_lips', 'retracted', 'profile', 'full_head'),
                  FIELD(COALESCE(source_type, 'uploaded'), 'uploaded', 'ai_reference'),
                  id ASC",
        ['case_id' => $caseId]
    );
}

function smile_design_primary_before_photo(int $caseId): ?array
{
    $photos = smile_design_before_photos($caseId);
    return $photos[0] ?? null;
}

function smile_design_find_before_photo_by_type(int $caseId, array|string $photoTypes, bool $preferUploaded = true): ?array
{
    $types = is_array($photoTypes) ? $photoTypes : [$photoTypes];
    $types = array_values(array_filter(array_map('strval', $types), static fn(string $value): bool => trim($value) !== ''));
    if ($types === []) {
        return null;
    }

    $photos = smile_design_before_photos($caseId);
    $matches = array_values(array_filter($photos, static function (array $photo) use ($types): bool {
        return in_array((string)($photo['photo_type'] ?? ''), $types, true);
    }));
    if ($matches === []) {
        return null;
    }
    if (!$preferUploaded) {
        return $matches[0];
    }
    foreach ($matches as $photo) {
        if ((string)($photo['source_type'] ?? 'uploaded') === 'uploaded') {
            return $photo;
        }
    }
    return $matches[0];
}

function smile_design_after_versions(int $caseId, bool $includeArchived = false): array
{
    $where = $includeArchived ? '' : ' AND archived = 0';
    return db_all("SELECT * FROM smile_after_versions WHERE case_id = :case_id {$where} ORDER BY selected_by_doctor DESC, approved_for_patient_preview DESC, version_number DESC, id DESC", ['case_id' => $caseId]);
}

function smile_design_real_after_versions(int $caseId, bool $includeArchived = false): array
{
    $where = $includeArchived ? '' : ' AND archived = 0';
    return db_all(
        "SELECT * FROM smile_after_versions
         WHERE case_id = :case_id
           AND source_type = 'actual_clinical_after'{$where}
         ORDER BY version_number DESC, id DESC",
        ['case_id' => $caseId]
    );
}

function smile_design_selected_after_version(int $caseId, ?string $photoType = null): ?array
{
    $params = ['case_id' => $caseId];
    $photoSql = '';
    if ($photoType !== null && $photoType !== '') {
        $photoSql = ' AND photo_type = :photo_type';
        $params['photo_type'] = $photoType;
    }
    $row = db_one("SELECT * FROM smile_after_versions WHERE case_id = :case_id {$photoSql} AND archived = 0 AND selected_by_doctor = 1 ORDER BY id DESC LIMIT 1", $params);
    if ($row) {
        return $row;
    }
    return db_one("SELECT * FROM smile_after_versions WHERE case_id = :case_id {$photoSql} AND archived = 0 ORDER BY approved_for_patient_preview DESC, id DESC LIMIT 1", $params) ?: null;
}

function smile_design_patient_preview_version(int $caseId): ?array
{
    return db_one("SELECT * FROM smile_after_versions WHERE case_id = :case_id AND archived = 0 AND approved_for_patient_preview = 1 ORDER BY selected_by_doctor DESC, id DESC LIMIT 1", ['case_id' => $caseId]) ?: null;
}

function smile_design_update_case_contact(int $caseId, array $data, ?int $userId = null): bool
{
    $case = smile_design_case($caseId);
    if (!$case) {
        return false;
    }

    $firstName = trim((string)($data['first_name'] ?? ''));
    $lastName = trim((string)($data['last_name'] ?? ''));
    $patientName = trim((string)($data['patient_name'] ?? ''));
    if ($patientName === '') {
        $patientName = trim($firstName . ' ' . $lastName);
    }
    if ($patientName === '') {
        $patientName = (string)($case['patient_name'] ?? 'Smile Design Patient');
    }

    $email = strtolower(trim((string)($data['email'] ?? '')));
    $phone = trim((string)($data['phone'] ?? ''));

    db_execute(
        "UPDATE smile_cases
         SET first_name = :first_name,
             last_name = :last_name,
             patient_name = :patient_name,
             email = :email,
             phone = :phone
         WHERE id = :id",
        [
            'id' => $caseId,
            'first_name' => $firstName !== '' ? $firstName : null,
            'last_name' => $lastName !== '' ? $lastName : null,
            'patient_name' => $patientName,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
        ]
    );

    smile_design_audit($caseId, 'case_contact_updated', [
        'patient_name' => $patientName,
        'email' => $email,
        'phone' => $phone,
    ], $userId);

    return true;
}

function smile_design_preview_links(int $caseId, bool $includeRevoked = false, int $limit = 20): array
{
    $limit = max(1, min(100, $limit));
    $where = $includeRevoked ? '' : ' AND revoked_at IS NULL';
    return db_all(
        "SELECT *
         FROM smile_preview_links
         WHERE case_id = :case_id
           AND purpose = 'preview'{$where}
         ORDER BY id DESC
         LIMIT {$limit}",
        ['case_id' => $caseId]
    );
}

function smile_design_case_activity(int $caseId, int $limit = 25): array
{
    $limit = max(1, min(100, $limit));
    return db_all(
        "SELECT sae.*,
                TRIM(CONCAT_WS(' ', u.first_name, u.last_name)) AS user_name
         FROM smile_audit_events sae
         LEFT JOIN users u ON u.id = sae.user_id
         WHERE sae.case_id = :case_id
         ORDER BY sae.id DESC
         LIMIT {$limit}",
        ['case_id' => $caseId]
    );
}

function smile_design_after_url(int $versionId, string $token = ''): string
{
    $query = 'after_id=' . $versionId;
    if ($token !== '') {
        $query .= '&token=' . rawurlencode($token);
    }
    return base_url('app/actions/smile_design_photo.php?' . $query);
}

function smile_design_recent_cases(int $limit = 12): array
{
    $limit = max(1, min(50, $limit));
    return db_all("SELECT * FROM smile_cases ORDER BY COALESCE(updated_at, created_at) DESC, id DESC LIMIT {$limit}");
}

function smile_design_search_cases(string $query = '', int $limit = 60): array
{
    $limit = max(1, min(100, $limit));
    $query = strtolower(trim($query));
    if ($query === '') {
        return smile_design_recent_cases($limit);
    }

    return db_all(
        "SELECT * FROM smile_cases
         WHERE LOWER(CONCAT_WS(' ', id, first_name, last_name, patient_name, email, phone, procedure_interest, status, lvi_style_key)) LIKE :query
         ORDER BY COALESCE(updated_at, created_at) DESC, id DESC
         LIMIT {$limit}",
        ['query' => '%' . $query . '%']
    );
}

function smile_design_counts(): array
{
    return [
        'cases' => (int)db_value('SELECT COUNT(*) FROM smile_cases'),
        'submitted' => (int)db_value("SELECT COUNT(*) FROM smile_cases WHERE status = 'intake_submitted'"),
        'preview_ready' => (int)db_value("SELECT COUNT(*) FROM smile_cases WHERE status = 'preview_ready'"),
        'real_results' => (int)db_value("SELECT COUNT(*) FROM smile_after_versions WHERE archived = 0 AND source_type = 'actual_clinical_after'"),
    ];
}

function smile_design_real_after_gallery_versions(int $limit = 24): array
{
    $limit = max(1, min(100, $limit));
    return db_all(
        "SELECT sav.*, sc.patient_name, sc.procedure_interest, sc.lvi_style_key
         FROM smile_after_versions sav
         INNER JOIN smile_cases sc ON sc.id = sav.case_id
         WHERE sav.archived = 0
           AND sav.source_type = 'actual_clinical_after'
           AND sav.approved_for_office_gallery = 1
         ORDER BY sav.updated_at DESC, sav.id DESC
         LIMIT {$limit}"
    );
}

function smile_design_safe_storage_path(string $storageKey): ?string
{
    $root = realpath(smile_design_private_root());
    if (!$root) {
        return null;
    }

    $path = realpath($root . DIRECTORY_SEPARATOR . ltrim($storageKey, '/\\'));
    if (!$path || !str_starts_with($path, $root)) {
        return null;
    }

    return $path;
}

function smile_design_apply_exif_orientation($image, string $sourcePath, string $mime)
{
    if (!$image || $mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
        return $image;
    }

    $exif = @exif_read_data($sourcePath);
    $orientation = (int)($exif['Orientation'] ?? 1);
    if ($orientation <= 1) {
        return $image;
    }

    return match ($orientation) {
        2 => imageflip($image, IMG_FLIP_HORIZONTAL) ? $image : $image,
        3 => imagerotate($image, 180, 0),
        4 => imageflip($image, IMG_FLIP_VERTICAL) ? $image : $image,
        5 => (function () use ($image) {
            imageflip($image, IMG_FLIP_VERTICAL);
            return imagerotate($image, -90, 0);
        })(),
        6 => imagerotate($image, -90, 0),
        7 => (function () use ($image) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
            return imagerotate($image, -90, 0);
        })(),
        8 => imagerotate($image, 90, 0),
        default => $image,
    };
}

function smile_design_upload_detect_mime(string $tmp, array $file): string
{
    $mime = '';
    if (is_file($tmp)) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)($finfo->file($tmp) ?: '');
    }

    $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (in_array($extension, ['heic', 'heif'], true)) {
        return $extension === 'heif' ? 'image/heif' : 'image/heic';
    }

    if ($mime === '' || $mime === 'application/octet-stream') {
        $handle = @fopen($tmp, 'rb');
        $header = $handle ? (string)@fread($handle, 32) : '';
        if ($handle) {
            @fclose($handle);
        }
        if (str_contains($header, 'ftypheic') || str_contains($header, 'ftypheix') || str_contains($header, 'ftyphevc') || str_contains($header, 'ftypmif1') || str_contains($header, 'ftypmsf1')) {
            return 'image/heic';
        }
    }

    return $mime;
}

function smile_design_is_heic_mime(string $mime): bool
{
    return in_array(strtolower($mime), ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'], true);
}

function smile_design_shell_command_exists(string $command): bool
{
    if (!function_exists('shell_exec')) {
        return false;
    }

    $path = @shell_exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null');
    return is_string($path) && trim($path) !== '';
}

function smile_design_converted_jpeg_result(string $target): array
{
    if (!is_file($target) || (int)filesize($target) <= 0) {
        return ['ok' => false, 'message' => 'Could not convert the HEIC photo to JPG.'];
    }

    $info = @getimagesize($target);
    return [
        'ok' => true,
        'mime_type' => 'image/jpeg',
        'extension' => 'jpg',
        'width' => isset($info[0]) ? (int)$info[0] : null,
        'height' => isset($info[1]) ? (int)$info[1] : null,
    ];
}

function smile_design_convert_heic_with_cli(string $tmp, string $target): array
{
    if (!function_exists('shell_exec')) {
        return ['ok' => false, 'message' => 'No server-side HEIC converter is available.'];
    }

    $commands = [];
    if (smile_design_shell_command_exists('magick')) {
        $commands[] = 'magick ' . escapeshellarg($tmp . '[0]') . ' -auto-orient -quality 92 ' . escapeshellarg($target);
    }
    if (smile_design_shell_command_exists('convert')) {
        $commands[] = 'convert ' . escapeshellarg($tmp . '[0]') . ' -auto-orient -quality 92 ' . escapeshellarg($target);
    }
    if (smile_design_shell_command_exists('heif-convert')) {
        $commands[] = 'heif-convert -q 92 ' . escapeshellarg($tmp) . ' ' . escapeshellarg($target);
    }
    if (smile_design_shell_command_exists('ffmpeg')) {
        $commands[] = 'ffmpeg -y -i ' . escapeshellarg($tmp) . ' -frames:v 1 -q:v 2 ' . escapeshellarg($target);
    }

    foreach ($commands as $command) {
        if (is_file($target)) {
            @unlink($target);
        }
        @shell_exec($command . ' 2>&1');
        $result = smile_design_converted_jpeg_result($target);
        if (!empty($result['ok'])) {
            return $result;
        }
    }

    return ['ok' => false, 'message' => 'This server cannot convert HEIC to JPG yet. Please upload JPG/PNG/WebP or enable HEIC support in ImageMagick/Imagick.'];
}

function smile_design_convert_heic_upload_to_jpeg(string $tmp, string $target): array
{
    if (class_exists('Imagick')) {
        try {
            $image = new Imagick();
            $image->readImage($tmp);
            if (method_exists($image, 'setIteratorIndex')) {
                $image->setIteratorIndex(0);
            }
            if (method_exists($image, 'autoOrientImage')) {
                $image->autoOrientImage();
            }
            $image->setImageBackgroundColor('white');
            if (method_exists($image, 'mergeImageLayers')) {
                $image = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            }
            $image->setImageFormat('jpeg');
            $image->setImageCompressionQuality(92);
            if (!$image->writeImage($target) || !is_file($target)) {
                return smile_design_convert_heic_with_cli($tmp, $target);
            }
            $width = $image->getImageWidth();
            $height = $image->getImageHeight();
            $image->clear();
            $image->destroy();

            return [
                'ok' => true,
                'mime_type' => 'image/jpeg',
                'extension' => 'jpg',
                'width' => $width,
                'height' => $height,
            ];
        } catch (Throwable $e) {
            if (is_file($target)) {
                @unlink($target);
            }
        }
    }

    return smile_design_convert_heic_with_cli($tmp, $target);
}

function smile_design_photo_url(int $photoId, string $token = ''): string
{
    $query = 'photo_id=' . $photoId;
    if ($token !== '') {
        $query .= '&token=' . rawurlencode($token);
    }
    return base_url('app/actions/smile_design_photo.php?' . $query);
}

function smile_design_store_upload(int $caseId, array $file, string $kind = 'before', string $photoType = 'front', string $sourceType = 'uploaded'): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'Please upload a photo.'];
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'message' => 'Upload failed before the file reached storage.'];
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 12 * 1024 * 1024) {
        return ['ok' => false, 'message' => 'Photo must be smaller than 12 MB.'];
    }

    $mime = smile_design_upload_detect_mime($tmp, $file);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $isHeic = smile_design_is_heic_mime($mime);
    if (!isset($allowed[$mime]) && !$isHeic) {
        return ['ok' => false, 'message' => 'Please upload a JPG, PNG, WebP, HEIC, or HEIF photo.'];
    }

    $directory = smile_design_private_root() . '/cases/' . $caseId;
    ensure_directory($directory);
    $base = $kind . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
    $ext = $isHeic ? 'jpg' : $allowed[$mime];
    $storageKey = 'cases/' . $caseId . '/' . $base . '.' . $ext;
    $target = smile_design_private_root() . '/' . $storageKey;
    $width = null;
    $height = null;
    $stored = false;

    if ($isHeic) {
        $converted = smile_design_convert_heic_upload_to_jpeg($tmp, $target);
        if (empty($converted['ok'])) {
            return $converted;
        }
        $mime = 'image/jpeg';
        $width = isset($converted['width']) ? (int)$converted['width'] : null;
        $height = isset($converted['height']) ? (int)$converted['height'] : null;
        $stored = true;
    } elseif (extension_loaded('gd')) {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($tmp),
            'image/png' => @imagecreatefrompng($tmp),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
            default => false,
        };

        if ($image) {
            $image = smile_design_apply_exif_orientation($image, $tmp, $mime);
            $width = imagesx($image);
            $height = imagesy($image);
            $max = 1800;
            if ($width > $max || $height > $max) {
                $scale = min($max / $width, $max / $height);
                $newWidth = max(1, (int)round($width * $scale));
                $newHeight = max(1, (int)round($height * $scale));
                $resized = imagecreatetruecolor($newWidth, $newHeight);
                imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                imagedestroy($image);
                $image = $resized;
                $width = $newWidth;
                $height = $newHeight;
            }

            if ($mime === 'image/png') {
                imagepng($image, $target, 6);
            } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
                imagewebp($image, $target, 86);
            } else {
                imagejpeg($image, $target, 86);
            }
            imagedestroy($image);
            $stored = is_file($target);
        }
    }

    if (!$stored) {
        $info = @getimagesize($tmp);
        $width = isset($info[0]) ? (int)$info[0] : null;
        $height = isset($info[1]) ? (int)$info[1] : null;
        $stored = move_uploaded_file($tmp, $target);
    }

    if (!$stored) {
        return ['ok' => false, 'message' => 'Could not save the uploaded photo.'];
    }

    if (!array_key_exists($photoType, smile_design_photo_type_options())) {
        $photoType = 'front';
    }
    if (!array_key_exists($sourceType, smile_design_photo_source_labels())) {
        $sourceType = 'uploaded';
    }

    $photoId = db_insert(
        "INSERT INTO smile_case_photos (case_id, kind, photo_type, source_type, storage_key, original_name, mime_type, file_size, width, height)
         VALUES (:case_id, :kind, :photo_type, :source_type, :storage_key, :original_name, :mime_type, :file_size, :width, :height)",
        [
            'case_id' => $caseId,
            'kind' => $kind,
            'photo_type' => $photoType,
            'source_type' => $sourceType,
            'storage_key' => $storageKey,
            'original_name' => basename((string)($file['name'] ?? 'photo')),
            'mime_type' => $mime,
            'file_size' => (int)filesize($target),
            'width' => $width,
            'height' => $height,
        ]
    );

    smile_design_audit($caseId, 'photo_uploaded', ['photo_id' => $photoId, 'kind' => $kind, 'photo_type' => $photoType, 'source_type' => $sourceType, 'mime' => $mime], null);
    return ['ok' => true, 'photo_id' => $photoId, 'storage_key' => $storageKey];
}

function smile_design_normalize_generated_image_binary(string $binary, string $mimeType, int $targetWidth, int $targetHeight): array
{
    if (!extension_loaded('gd') || $targetWidth <= 0 || $targetHeight <= 0) {
        return ['ok' => false, 'message' => 'Image normalization is not available.'];
    }

    $source = @imagecreatefromstring($binary);
    if (!$source) {
        return ['ok' => false, 'message' => 'Generated image could not be decoded for normalization.'];
    }

    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);
    if ($sourceWidth <= 0 || $sourceHeight <= 0) {
        imagedestroy($source);
        return ['ok' => false, 'message' => 'Generated image dimensions were unreadable.'];
    }

    $sourceRatio = $sourceWidth / $sourceHeight;
    $targetRatio = $targetWidth / $targetHeight;
    if ($sourceRatio > $targetRatio) {
        $cropHeight = $sourceHeight;
        $cropWidth = (int)round($sourceHeight * $targetRatio);
        $cropX = (int)max(0, floor(($sourceWidth - $cropWidth) / 2));
        $cropY = 0;
    } else {
        $cropWidth = $sourceWidth;
        $cropHeight = (int)round($sourceWidth / $targetRatio);
        $cropX = 0;
        $cropY = (int)max(0, floor(($sourceHeight - $cropHeight) / 2));
    }

    $target = imagecreatetruecolor($targetWidth, $targetHeight);
    imagealphablending($target, true);
    imagesavealpha($target, true);
    $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
    imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);
    imagecopyresampled($target, $source, 0, 0, $cropX, $cropY, $targetWidth, $targetHeight, $cropWidth, $cropHeight);

    ob_start();
    imagepng($target, null, 6);
    $normalized = ob_get_clean();
    imagedestroy($source);
    imagedestroy($target);

    if (!is_string($normalized) || $normalized === '') {
        return ['ok' => false, 'message' => 'Generated image normalization failed.'];
    }

    return [
        'ok' => true,
        'binary' => $normalized,
        'mime_type' => 'image/png',
        'width' => $targetWidth,
        'height' => $targetHeight,
    ];
}

function smile_design_store_generated_image(string $binary, string $mimeType, string $storagePrefix, string $extensionHint = '', array $options = []): array
{
    if ($binary === '') {
        return ['ok' => false, 'message' => 'Generated image payload was empty.'];
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mimeType])) {
        return ['ok' => false, 'message' => 'Generated image type is not supported.'];
    }

    $directory = smile_design_private_root() . '/' . trim($storagePrefix, '/');
    ensure_directory($directory);

    $targetWidth = (int)($options['target_width'] ?? 0);
    $targetHeight = (int)($options['target_height'] ?? 0);
    if ($targetWidth > 0 && $targetHeight > 0) {
        $normalized = smile_design_normalize_generated_image_binary($binary, $mimeType, $targetWidth, $targetHeight);
        if (!empty($normalized['ok'])) {
            $binary = (string)$normalized['binary'];
            $mimeType = (string)$normalized['mime_type'];
            $extensionHint = 'png';
        }
    }

    $ext = $extensionHint !== '' ? ltrim(strtolower($extensionHint), '.') : $allowed[$mimeType];
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        $ext = $allowed[$mimeType];
    }

    $storageKey = trim($storagePrefix, '/') . '/' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target = smile_design_private_root() . '/' . $storageKey;

    if (@file_put_contents($target, $binary) === false || !is_file($target)) {
        return ['ok' => false, 'message' => 'Could not save the generated image.'];
    }

    $info = @getimagesizefromstring($binary);
    return [
        'ok' => true,
        'storage_key' => $storageKey,
        'mime_type' => $mimeType,
        'file_size' => (int) filesize($target),
        'width' => isset($info[0]) ? (int) $info[0] : null,
        'height' => isset($info[1]) ? (int) $info[1] : null,
    ];
}

function smile_design_normalize_after_version_to_before_dimensions(int $afterVersionId): array
{
    $version = db_one('SELECT * FROM smile_after_versions WHERE id = :id LIMIT 1', ['id' => $afterVersionId]);
    if (!$version) {
        return ['ok' => false, 'message' => 'After version not found.'];
    }

    $beforePhotoId = (int)($version['before_photo_id'] ?? 0);
    if ($beforePhotoId <= 0) {
        return ['ok' => false, 'message' => 'After version is not linked to a before photo.'];
    }
    $before = db_one('SELECT * FROM smile_case_photos WHERE id = :id LIMIT 1', ['id' => $beforePhotoId]);
    if (!$before) {
        return ['ok' => false, 'message' => 'Linked before photo not found.'];
    }

    $targetWidth = (int)($before['width'] ?? 0);
    $targetHeight = (int)($before['height'] ?? 0);
    if ($targetWidth <= 0 || $targetHeight <= 0) {
        return ['ok' => false, 'message' => 'Linked before photo dimensions are missing.'];
    }

    $path = smile_design_safe_storage_path((string)($version['storage_key'] ?? ''));
    if (!$path || !is_file($path)) {
        return ['ok' => false, 'message' => 'After image file is missing.'];
    }

    $binary = @file_get_contents($path);
    if (!is_string($binary) || $binary === '') {
        return ['ok' => false, 'message' => 'After image file could not be read.'];
    }
    $mimeType = (string)($version['mime_type'] ?? (@mime_content_type($path) ?: 'image/png'));
    $normalized = smile_design_normalize_generated_image_binary($binary, $mimeType, $targetWidth, $targetHeight);
    if (empty($normalized['ok'])) {
        return $normalized;
    }
    if (@file_put_contents($path, (string)$normalized['binary']) === false) {
        return ['ok' => false, 'message' => 'Could not write normalized after image.'];
    }

    db_execute(
        'UPDATE smile_after_versions
         SET mime_type = :mime_type,
             file_size = :file_size,
             width = :width,
             height = :height,
             updated_at = NOW()
         WHERE id = :id',
        [
            'id' => $afterVersionId,
            'mime_type' => 'image/png',
            'file_size' => (int)filesize($path),
            'width' => $targetWidth,
            'height' => $targetHeight,
        ]
    );

    return ['ok' => true, 'width' => $targetWidth, 'height' => $targetHeight];
}

function smile_design_update_case_analysis(int $caseId, string $status, string $provider = 'openai', ?array $analysis = null, string $summary = ''): void
{
    db_execute(
        "UPDATE smile_cases
         SET ai_analysis_status = :status,
             ai_analysis_provider = :provider,
             ai_analysis_summary = :summary,
             ai_analysis_json = :analysis_json,
             ai_analysis_updated_at = NOW()
         WHERE id = :id",
        [
            'id' => $caseId,
            'status' => $status,
            'provider' => $provider,
            'summary' => $summary,
            'analysis_json' => $analysis ? json_encode($analysis, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
        ]
    );
}

function smile_design_run_case_analysis(int $caseId, int $beforePhotoId, ?int $userId = null, bool $force = false): array
{
    $case = smile_design_case($caseId);
    if (!$case) {
        return ['ok' => false, 'message' => 'Smile case not found.'];
    }

    $beforePhoto = db_one(
        "SELECT * FROM smile_case_photos WHERE id = :id AND case_id = :case_id AND kind = 'before' LIMIT 1",
        ['id' => $beforePhotoId, 'case_id' => $caseId]
    );
    if (!$beforePhoto) {
        return ['ok' => false, 'message' => 'Before photo not found for this case.'];
    }

    $existing = smile_design_case_analysis($caseId);
    $existingPhotoId = (int)($existing['analysis']['source_before_photo_id'] ?? 0);
    if (!$force && $existing && $existing['status'] === 'completed' && $existingPhotoId === $beforePhotoId) {
        return ['ok' => true, 'analysis' => $existing['analysis'], 'summary' => $existing['summary'], 'cached' => true];
    }

    $imagePath = smile_design_safe_storage_path((string)($beforePhoto['storage_key'] ?? ''));
    if (!$imagePath || !is_file($imagePath)) {
        return ['ok' => false, 'message' => 'Before photo file is missing from storage.'];
    }

    smile_design_update_case_analysis($caseId, 'processing', 'openai', null, 'Analyzing smile case...');

    $schema = [
        'type' => 'object',
        'additionalProperties' => false,
        'properties' => [
            'source_before_photo_id' => ['type' => 'integer'],
            'summary' => ['type' => 'string'],
            'case_type' => ['type' => 'string'],
            'clinical_direction' => ['type' => 'string'],
            'preview_suitability' => ['type' => 'string'],
            'recommended_procedure' => ['type' => 'string'],
            'smile_scope' => ['type' => 'string'],
            'upper_teeth_visibility' => ['type' => 'string'],
            'lower_teeth_visibility' => ['type' => 'string'],
            'missing_or_compromised_teeth' => ['type' => 'string'],
            'gingival_display' => ['type' => 'string'],
            'recommended_generation_focus' => ['type' => 'string'],
            'preserve_identity_instructions' => ['type' => 'string'],
            'primary_changes' => ['type' => 'array', 'items' => ['type' => 'string']],
            'constraints' => ['type' => 'array', 'items' => ['type' => 'string']],
            'risk_flags' => ['type' => 'array', 'items' => ['type' => 'string']],
            'doctor_review_notes' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => [
            'source_before_photo_id',
            'summary',
            'case_type',
            'clinical_direction',
            'preview_suitability',
            'recommended_procedure',
            'smile_scope',
            'upper_teeth_visibility',
            'lower_teeth_visibility',
            'missing_or_compromised_teeth',
            'gingival_display',
            'recommended_generation_focus',
            'preserve_identity_instructions',
            'primary_changes',
            'constraints',
            'risk_flags',
            'doctor_review_notes',
        ],
    ];

    $requestedProcedure = trim((string)($case['procedure_interest'] ?? 'Smile Design Preview'));
    $procedureGuidance = smile_design_procedure_prompt_guidance($requestedProcedure);

    $systemPrompt = <<<PROMPT
You are acting as Dr. Meden Clinical Authority / Surgeon inside the Elite Smiles CRM.

You are the final clinical authority for smile-preview case review.
Your responsibility is clinical truth:
- determine treatment direction
- identify whether the case is cosmetic, surgical, restorative, or needs further review
- decide whether an AI preview is suitable for patient presentation
- identify when a case is not ideal for cosmetic-only simulation
- recommend what the after picture should and should not attempt to show

Rules:
- Do not invent beauty edits unrelated to dentistry.
- Do not treat an AI preview as a guaranteed outcome.
- Preserve patient identity.
- Focus on visible dental presentation and treatment-fit judgment.
- If the case appears questionable for veneers-only simulation, say so clearly.
- If lip repositioning, restorative, full-mouth, or further evaluation appears more appropriate, say so clearly.
- The requested procedure selected at case creation is clinically important context. Use it as the intended treatment direction unless visible evidence strongly contradicts it.
- Treat every procedure option as its own clinical/prosthetic path with realistic limits. Do not use one generic makeover prompt for all cases.
- Treat Veneers, Lip Repositioning, Veneers + Lip Repositioning, Implants / Restoration, All-on-X / Full Smile Restoration, and Not Sure Yet as different procedure paths.
- For Veneers, focus on realistic porcelain/restorative changes to visible tooth shape, shade, symmetry, proportions, and small spacing/chip issues. Do not simulate major orthodontics, implant replacement, jaw changes, or broad gum surgery.
- For Lip Repositioning alone, the analysis and generation focus should be surgical gummy-smile correction only: simulate restricted upper-lip elevator pull, a visibly lower smile lip line, and a realistic but meaningful reduction of gum display. When a broad gummy band is visible, target roughly half to two-thirds reduction while preserving a natural lip drape. Tooth preservation is more important than complete gum elimination. Do not recommend tooth reshaping, whitening, or veneers as part of the preview unless the selected procedure includes veneers or visible evidence clearly requires changing the plan.
- For Veneers + Lip Repositioning, include both tooth design changes and upper-lip/gum-display correction.
- For Implants / Restoration, focus on missing, broken, compromised, dark, worn, or failing teeth and realistic crowns, bridges, implant crowns, or implant-supported restorations. Do not replace healthy teeth or convert the case into a full-arch smile unless visible evidence supports it.
- For All-on-X / Full Smile Restoration, preview a realistic full-arch implant-supported prosthetic outcome only when visible evidence supports terminal dentition, extensive missing teeth, severe wear, failing restorations, or full-arch rehabilitation. Keep lip support, vertical dimension, bite plane, and prosthetic gum/tooth transitions believable.
- For Not Sure Yet, stay conservative: identify likely treatment direction, but do not invent an aggressive irreversible plan.
- When procedure-specific direction is provided, include it in recommended_generation_focus, primary_changes, constraints, and doctor_review_notes where relevant.

Return only structured JSON matching the schema.
PROMPT;
    $userPrompt = implode(' ', array_values(array_filter([
        'Review this Elite Smiles case photo as Dr. Meden would for clinical direction and smile-preview suitability.',
        'Requested procedure selected at case creation: ' . $requestedProcedure . '.',
        $procedureGuidance !== '' ? $procedureGuidance : '',
        'Determine what treatment direction appears most appropriate from visible evidence, whether a cosmetic AI preview is appropriate, whether the preview should be upper-only or broader, whether lower teeth should remain untouched, whether missing or compromised teeth are visible, and the main constraints the image-generation step must obey.',
        'If this is Lip Repositioning only, explicitly evaluate gingival display and whether the after preview should simulate restricted upper-lip elevation: the upper lip should travel less upward when smiling, sit visibly lower by about 2 to 3 mm when natural, reduce a broad gummy band by roughly half to two-thirds when present, and move the bottom edge of the superior lip closer to the arch/cervical contour of the upper teeth while preserving the teeth themselves.',
        'If this is Veneers + Lip Repositioning, evaluate both the tooth design needs and the lip-line / gum-display correction.',
        'Add concise doctor review notes that would help staff prepare the case.',
        'Set source_before_photo_id to ' . $beforePhotoId . '.',
    ], static fn(string $value): bool => trim($value) !== '')));

    $result = elite_openai_image_json_response($imagePath, $systemPrompt, $userPrompt, $schema, 'smile_case_analysis', 'high');
    if (empty($result['ok']) || !is_array($result['data'] ?? null)) {
        smile_design_update_case_analysis($caseId, 'failed', 'openai', null, 'AI case analysis failed.');
        smile_design_audit($caseId, 'ai_case_analysis_failed', ['before_photo_id' => $beforePhotoId], $userId);
        return ['ok' => false, 'message' => (string)($result['message'] ?? 'AI case analysis failed.')];
    }

    $analysis = $result['data'];
    $summary = trim((string)($analysis['summary'] ?? 'AI case analysis completed.'));
    smile_design_update_case_analysis($caseId, 'completed', 'openai', $analysis, $summary);
    smile_design_audit($caseId, 'ai_case_analysis_completed', ['before_photo_id' => $beforePhotoId], $userId);

    return ['ok' => true, 'analysis' => $analysis, 'summary' => $summary];
}

function smile_design_store_private_image(array $file, string $storagePrefix): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'Please upload a photo.'];
    }
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'message' => 'Upload failed before the file reached storage.'];
    }
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 12 * 1024 * 1024) {
        return ['ok' => false, 'message' => 'Photo must be smaller than 12 MB.'];
    }
    $mime = smile_design_upload_detect_mime($tmp, $file);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $isHeic = smile_design_is_heic_mime($mime);
    if (!isset($allowed[$mime]) && !$isHeic) {
        return ['ok' => false, 'message' => 'Please upload a JPG, PNG, WebP, HEIC, or HEIF photo.'];
    }
    $directory = smile_design_private_root() . '/' . trim($storagePrefix, '/');
    ensure_directory($directory);
    $storageKey = trim($storagePrefix, '/') . '/' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . ($isHeic ? 'jpg' : $allowed[$mime]);
    $target = smile_design_private_root() . '/' . $storageKey;
    $width = null;
    $height = null;
    $stored = false;
    if ($isHeic) {
        $converted = smile_design_convert_heic_upload_to_jpeg($tmp, $target);
        if (empty($converted['ok'])) {
            return $converted;
        }
        $mime = 'image/jpeg';
        $width = isset($converted['width']) ? (int)$converted['width'] : null;
        $height = isset($converted['height']) ? (int)$converted['height'] : null;
        $stored = true;
    } elseif (extension_loaded('gd')) {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($tmp),
            'image/png' => @imagecreatefrompng($tmp),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
            default => false,
        };
        if ($image) {
            $image = smile_design_apply_exif_orientation($image, $tmp, $mime);
            $width = imagesx($image);
            $height = imagesy($image);
            $max = 1800;
            if ($width > $max || $height > $max) {
                $scale = min($max / $width, $max / $height);
                $newWidth = max(1, (int)round($width * $scale));
                $newHeight = max(1, (int)round($height * $scale));
                $resized = imagecreatetruecolor($newWidth, $newHeight);
                imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                imagedestroy($image);
                $image = $resized;
                $width = $newWidth;
                $height = $newHeight;
            }
            if ($mime === 'image/png') {
                imagepng($image, $target, 6);
            } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
                imagewebp($image, $target, 86);
            } else {
                imagejpeg($image, $target, 86);
            }
            imagedestroy($image);
            $stored = is_file($target);
        }
    }
    if (!$stored) {
        $info = @getimagesize($tmp);
        $width = isset($info[0]) ? (int)$info[0] : null;
        $height = isset($info[1]) ? (int)$info[1] : null;
        $stored = move_uploaded_file($tmp, $target);
    }
    if (!$stored) {
        return ['ok' => false, 'message' => 'Could not save the uploaded photo.'];
    }
    return ['ok' => true, 'storage_key' => $storageKey, 'mime_type' => $mime, 'file_size' => (int)filesize($target), 'width' => $width, 'height' => $height];
}

function smile_design_insert_before_photo_record(int $caseId, array $stored, array $data, ?int $userId = null): array
{
    $photoType = (string)($data['photo_type'] ?? 'front');
    if (!array_key_exists($photoType, smile_design_photo_type_options())) {
        $photoType = 'front';
    }
    $sourceType = (string)($data['source_type'] ?? 'uploaded');
    if (!array_key_exists($sourceType, smile_design_photo_source_labels())) {
        $sourceType = 'uploaded';
    }

    $photoId = db_insert(
        "INSERT INTO smile_case_photos (case_id, kind, photo_type, source_type, storage_key, original_name, mime_type, file_size, width, height)
         VALUES (:case_id, 'before', :photo_type, :source_type, :storage_key, :original_name, :mime_type, :file_size, :width, :height)",
        [
            'case_id' => $caseId,
            'photo_type' => $photoType,
            'source_type' => $sourceType,
            'storage_key' => (string)$stored['storage_key'],
            'original_name' => trim((string)($data['original_name'] ?? 'before-photo')) ?: 'before-photo',
            'mime_type' => (string)$stored['mime_type'],
            'file_size' => (int)($stored['file_size'] ?? 0),
            'width' => isset($stored['width']) ? (int)$stored['width'] : null,
            'height' => isset($stored['height']) ? (int)$stored['height'] : null,
        ]
    );

    smile_design_audit($caseId, 'before_photo_added', [
        'photo_id' => $photoId,
        'photo_type' => $photoType,
        'source_type' => $sourceType,
    ], $userId);

    return ['ok' => true, 'photo_id' => $photoId];
}

function smile_design_insert_after_version_record(int $caseId, array $stored, array $data, ?int $userId = null): array
{
    $versionNumber = ((int)db_value('SELECT COALESCE(MAX(version_number), 0) + 1 FROM smile_after_versions WHERE case_id = :case_id', ['case_id' => $caseId])) ?: 1;
    $source = (string)($data['source_type'] ?? 'manual_upload');
    if (!array_key_exists($source, smile_design_source_type_labels())) {
        $source = 'manual_upload';
    }
    $photoType = (string)($data['photo_type'] ?? 'front');
    if (!array_key_exists($photoType, smile_design_photo_type_options())) {
        $photoType = 'front';
    }
    $versionId = db_insert(
        "INSERT INTO smile_after_versions
         (case_id, before_photo_id, storage_key, version_title, version_number, source_type, procedure_label, lvi_style_key, photo_type, notes, mime_type, file_size, width, height, created_by)
         VALUES
         (:case_id, :before_photo_id, :storage_key, :version_title, :version_number, :source_type, :procedure_label, :lvi_style_key, :photo_type, :notes, :mime_type, :file_size, :width, :height, :created_by)",
        [
            'case_id' => $caseId,
            'before_photo_id' => (int)($data['before_photo_id'] ?? 0) ?: null,
            'storage_key' => $stored['storage_key'],
            'version_title' => trim((string)($data['version_title'] ?? 'Manual After Version')) ?: 'Manual After Version',
            'version_number' => $versionNumber,
            'source_type' => $source,
            'procedure_label' => trim((string)($data['procedure_label'] ?? '')) ?: null,
            'lvi_style_key' => trim((string)($data['lvi_style_key'] ?? '')) ?: null,
            'photo_type' => $photoType,
            'notes' => trim((string)($data['notes'] ?? '')) ?: null,
            'mime_type' => (string)$stored['mime_type'],
            'file_size' => (int)($stored['file_size'] ?? 0),
            'width' => isset($stored['width']) ? (int)$stored['width'] : null,
            'height' => isset($stored['height']) ? (int)$stored['height'] : null,
            'created_by' => $userId,
        ]
    );
    smile_design_audit($caseId, 'after_version_uploaded', ['after_version_id' => $versionId, 'source_type' => $source], $userId);
    return ['ok' => true, 'after_version_id' => $versionId];
}

function smile_design_create_after_version(int $caseId, array $file, array $data, ?int $userId = null): array
{
    $stored = smile_design_store_private_image($file, 'cases/' . $caseId . '/after');
    if (empty($stored['ok'])) {
        return $stored;
    }
    return smile_design_insert_after_version_record($caseId, $stored, $data, $userId);
}

function smile_design_lip_repositioning_preview_qa(array $beforePhoto, array $generationResult, string $targetPhotoLabel, string $targetPhotoType): array
{
    if (!function_exists('elite_openai_images_json_response')) {
        return ['ok' => false, 'message' => 'OpenAI image comparison helper is not available.'];
    }

    $beforePath = smile_design_safe_storage_path((string)($beforePhoto['storage_key'] ?? ''));
    if (!$beforePath || !is_file($beforePath)) {
        return ['ok' => false, 'message' => 'Before photo for lip repositioning QA is missing.'];
    }

    $binary = base64_decode((string)($generationResult['image_base64'] ?? ''), true);
    if (!is_string($binary) || $binary === '') {
        return ['ok' => false, 'message' => 'Generated image for lip repositioning QA is unreadable.'];
    }

    $tempBase = tempnam(sys_get_temp_dir(), 'esm-lipqa-');
    if ($tempBase === false) {
        return ['ok' => false, 'message' => 'Could not create lip repositioning QA temp file.'];
    }

    $extension = match (strtolower((string)($generationResult['mime_type'] ?? 'image/png'))) {
        'image/jpeg', 'image/jpg' => '.jpg',
        'image/webp' => '.webp',
        default => '.png',
    };
    $afterPath = $tempBase . $extension;
    @unlink($tempBase);
    if (@file_put_contents($afterPath, $binary) === false) {
        return ['ok' => false, 'message' => 'Could not write lip repositioning QA image.'];
    }

    $schema = [
        'type' => 'object',
        'additionalProperties' => false,
        'properties' => [
            'approved' => ['type' => 'boolean'],
            'score' => ['type' => 'integer'],
            'gum_reduction_visible' => ['type' => 'boolean'],
            'change_strength_sufficient' => ['type' => 'boolean'],
            'lip_position_target_met' => ['type' => 'boolean'],
            'teeth_preserved' => ['type' => 'boolean'],
            'face_preserved' => ['type' => 'boolean'],
            'lip_only_edit' => ['type' => 'boolean'],
            'surgical_naturalness' => ['type' => 'boolean'],
            'assessment' => ['type' => 'string'],
            'issues' => ['type' => 'array', 'items' => ['type' => 'string']],
            'retry_instruction' => ['type' => 'string'],
        ],
        'required' => [
            'approved',
            'score',
            'gum_reduction_visible',
            'change_strength_sufficient',
            'lip_position_target_met',
            'teeth_preserved',
            'face_preserved',
            'lip_only_edit',
            'surgical_naturalness',
            'assessment',
            'issues',
            'retry_instruction',
        ],
    ];

    $systemPrompt = <<<PROMPT
You are the QA reviewer for Elite Smiles surgical lip repositioning preview images.

Image 1 is the original before photo. Image 2 is the generated after preview.
Approve only if the after looks like the same photograph with a surgical gummy-smile/lip-repositioning simulation.

Pass criteria:
- The upper lip appears visibly lower during the smile, with a realistic and meaningful reduction of visible gum above the upper teeth.
- If the before has a broad gummy band, the after reduces it substantially, roughly half to two-thirds, without requiring total gum elimination.
- The lower edge of the upper lip is clearly closer to the natural arch/cervical/top-edge area of the upper teeth.
- The upper lip looks less retracted/curling upward and naturally draped. A slight increase in visible superior-lip fullness is acceptable when it comes from the lip roll unfolding downward.
- A small natural gingival reveal may remain; do not require total gum elimination if the lip would look unnatural.
- Teeth are preserved: shape, size, shade, brightness, alignment, spacing, incisal edges, enamel texture, smile width, and tooth count remain essentially the same.
- Tiny compression or lighting differences can pass only if the teeth are not materially whitened, reshaped, smoothed, enlarged, or redesigned.
- Face, skin, hair, eyes, nose, cheeks, jawline, lower lip, lighting, crop, and identity remain essentially the same.

Fail criteria:
- Teeth were materially whitened, brightened, darkened, reshaped, straightened, enlarged, replaced, smoothed, cloned, or otherwise redesigned.
- The result looks like veneers, orthodontics, beauty retouching, a new smile, or a different person.
- Gum display is not meaningfully reduced, the broad gummy band is still almost the same height, or the upper lip is not visibly lower/less retracted.
- The lip correction looks unnatural, swollen, injected/filler-like, scarred, labeled, pasted on, or overdone.

Return only structured JSON matching the schema.
PROMPT;

    $userPrompt = implode(' ', [
        'Review the generated lip repositioning preview for target angle ' . $targetPhotoLabel . ' (' . $targetPhotoType . ').',
        'The intended surgical effect is a visibly lower upper-lip smile line, usually about 2 to 3 mm when natural, with a broad gummy band reduced by roughly half to two-thirds and unchanged teeth.',
        'Natural lip drape is more important than complete gum coverage; a slightly fuller superior lip from natural unfolding should pass, but filler-like swelling should fail.',
        'Also reject undercorrection: if the after looks almost identical to the before around the upper lip and gum band, mark change_strength_sufficient false and provide a retry instruction for stronger lip-only lowering.',
        'Use strict judgment: if the after changed the teeth or face to hide the gums, reject it and write a concise retry instruction.',
        'Set score from 0 to 10 where 10 is excellent surgical lip-only simulation.',
    ]);

    try {
        $result = elite_openai_images_json_response([$beforePath, $afterPath], $systemPrompt, $userPrompt, $schema, 'lip_repositioning_preview_qa', 'high');
    } finally {
        if (is_file($afterPath)) {
            @unlink($afterPath);
        }
    }

    if (empty($result['ok']) || !is_array($result['data'] ?? null)) {
        return ['ok' => false, 'message' => (string)($result['message'] ?? 'Lip repositioning QA failed.')];
    }

    return [
        'ok' => true,
        'provider' => 'openai',
        'data' => $result['data'],
        'status_code' => $result['status_code'] ?? null,
    ];
}

function smile_design_lip_repositioning_qa_requires_retry(array $qaResult): bool
{
    if (empty($qaResult['ok']) || !is_array($qaResult['data'] ?? null)) {
        return true;
    }

    $data = $qaResult['data'];
    if (array_key_exists('approved', $data) && empty($data['approved'])) {
        return true;
    }

    foreach (['gum_reduction_visible', 'change_strength_sufficient', 'lip_position_target_met', 'teeth_preserved', 'face_preserved', 'lip_only_edit', 'surgical_naturalness'] as $field) {
        if (array_key_exists($field, $data) && empty($data[$field])) {
            return true;
        }
    }

    return (int)($data['score'] ?? 10) < 7;
}

function smile_design_lip_repositioning_qa_feedback(array $qaResult): string
{
    if (empty($qaResult['ok']) || !is_array($qaResult['data'] ?? null)) {
        $message = trim((string)($qaResult['message'] ?? 'Lip repositioning QA did not return a usable review.'));
        return $message . ' Retry or saving should not proceed without a successful lip-only QA review.';
    }

    $data = is_array($qaResult['data'] ?? null) ? $qaResult['data'] : [];
    $issueLabels = [
        'change_strength_sufficient' => 'Lip repositioning effect is too subtle; the upper lip/gum band is still too similar to the before image',
        'gum_reduction_visible' => 'Gum reduction is not clearly visible',
        'lip_position_target_met' => 'Upper lip is not visibly lower or closer to the tooth cervical line',
        'teeth_preserved' => 'Teeth were changed',
        'face_preserved' => 'Face or identity changed',
        'lip_only_edit' => 'Changes went outside the allowed lip/gum region',
        'surgical_naturalness' => 'Lip drape does not look natural',
    ];
    $issues = array_values(array_filter(array_map(
        static fn($issue): string => $issueLabels[trim((string)$issue)] ?? trim((string)$issue),
        (array)($data['issues'] ?? [])
    )));
    $retryInstruction = trim((string)($data['retry_instruction'] ?? ''));
    $assessment = trim((string)($data['assessment'] ?? ''));

    return trim(implode(' ', array_values(array_filter([
        $assessment !== '' ? 'Assessment: ' . $assessment : '',
        $issues !== [] ? 'Issues: ' . implode('; ', $issues) . '.' : '',
        $retryInstruction !== '' ? 'Retry instruction: ' . $retryInstruction : '',
        'Retry must preserve teeth and face while changing only the upper-lip/gum-display relationship.',
    ], static fn(string $value): bool => trim($value) !== ''))));
}

function smile_design_image_provider(string $provider = 'openai'): SmileDesignImageProvider
{
    return match (strtolower(trim($provider))) {
        'openai' => new OpenAISmileDesignImageProvider(),
        'google_gemini', 'gemini', 'nano_banana' => new GoogleGeminiSmileDesignImageProvider(),
        'google_vertex' => new GoogleVertexSmileDesignImageProvider(),
        default => new MockSmileDesignImageProvider(),
    };
}

function smile_design_create_ai_after_version(int $caseId, int $beforePhotoId, array $options = [], ?int $userId = null): array
{
    $case = smile_design_case($caseId);
    if (!$case) {
        return ['ok' => false, 'message' => 'Smile case not found.'];
    }

    $beforePhoto = db_one(
        "SELECT * FROM smile_case_photos WHERE id = :id AND case_id = :case_id AND kind = 'before' LIMIT 1",
        ['id' => $beforePhotoId, 'case_id' => $caseId]
    );
    if (!$beforePhoto) {
        return ['ok' => false, 'message' => 'Before photo not found for this case.'];
    }

    if (($options['auto_prepare_missing_angles'] ?? true) !== false) {
        $prepareResult = smile_design_prepare_missing_reference_views($caseId, $beforePhotoId, $userId);
        if (empty($prepareResult['ok'])) {
            return $prepareResult;
        }
    }

    $providerName = strtolower(trim((string)($options['provider'] ?? 'openai')));
    $provider = smile_design_image_provider($providerName);
    $procedureForMode = trim((string)($options['procedure_label'] ?? $case['procedure_interest'] ?? ''));
    $isLipRepositionOnlyGeneration = smile_design_procedure_mode($procedureForMode) === 'lip_repositioning';
    $promptSummary = trim((string)($options['custom_request'] ?? ''));
    if ($promptSummary === '') {
        $summaryProcedure = (string)($options['procedure_label'] ?? $case['procedure_interest'] ?? 'smile design');
        if (smile_design_procedure_mode($summaryProcedure) === 'lip_repositioning') {
            $promptSummary = trim($summaryProcedure . ' surgical lip-line preview');
        } else {
            $promptSummary = trim((string)($options['lvi_style_key'] ?? $case['selected_style'] ?? 'natural') . ' ' . $summaryProcedure);
        }
    }

    if (($options['auto_analyze'] ?? true) !== false) {
        $analysisResult = smile_design_run_case_analysis($caseId, $beforePhotoId, $userId, !empty($options['refresh_analysis']));
        if (empty($analysisResult['ok'])) {
            return $analysisResult;
        }
        $options['case_analysis'] = $analysisResult['analysis'] ?? null;
        $options['analysis_summary'] = $analysisResult['summary'] ?? '';
    }

    $jobId = db_insert(
        "INSERT INTO ai_generation_jobs (case_id, provider, status, prompt_summary, request_json, created_by)
         VALUES (:case_id, :provider, :status, :prompt_summary, :request_json, :created_by)",
        [
            'case_id' => $caseId,
            'provider' => $providerName,
            'status' => 'processing',
            'prompt_summary' => $promptSummary,
            'request_json' => json_encode($options, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_by' => $userId,
        ]
    );

    $beforePhotos = smile_design_before_photos($caseId);
    usort($beforePhotos, static function (array $a, array $b) use ($beforePhotoId): int {
        $aRank = ((int)$a['id'] === $beforePhotoId) ? 0 : 1;
        $bRank = ((int)$b['id'] === $beforePhotoId) ? 0 : 1;
        if ($aRank !== $bRank) {
            return $aRank <=> $bRank;
        }
        return ((int)$a['id']) <=> ((int)$b['id']);
    });

    $sourcePhotos = $beforePhotos ?: [$beforePhoto];
    $referenceAfterVersionId = (int)($options['reference_after_version_id'] ?? 0);
    if ($referenceAfterVersionId > 0) {
        $referenceVersion = db_one(
            'SELECT * FROM smile_after_versions WHERE id = :id AND case_id = :case_id LIMIT 1',
            ['id' => $referenceAfterVersionId, 'case_id' => $caseId]
        );
        if ($referenceVersion) {
            $sourcePhotos[] = [
                'id' => (int)$referenceVersion['id'],
                'case_id' => (int)$referenceVersion['case_id'],
                'kind' => 'after_reference',
                'photo_type' => (string)($referenceVersion['photo_type'] ?? 'front'),
                'storage_key' => (string)($referenceVersion['storage_key'] ?? ''),
                'version_title' => (string)($referenceVersion['version_title'] ?? ''),
                'notes' => (string)($referenceVersion['notes'] ?? ''),
            ];
            $options['reference_after_version'] = $referenceVersion;
        }
    }

    $result = $provider->createPreview($case, $sourcePhotos, $options);

    if (empty($result['ok'])) {
        db_execute(
            "UPDATE ai_generation_jobs
             SET status = 'failed', response_json = :response_json, updated_at = NOW()
             WHERE id = :id",
            [
                'id' => $jobId,
                'response_json' => json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]
        );
        smile_design_audit($caseId, 'ai_generation_failed', ['job_id' => $jobId, 'provider' => $providerName], $userId);
        return $result;
    }

    if ($isLipRepositionOnlyGeneration && (($options['lip_preview_qa'] ?? true) !== false)) {
        $targetPhotoLabel = trim((string)($options['target_photo_label'] ?? smile_design_photo_type_options()[(string)($beforePhoto['photo_type'] ?? 'front')] ?? 'Front'));
        $targetPhotoType = trim((string)($options['target_photo_type'] ?? $options['photo_type'] ?? $beforePhoto['photo_type'] ?? 'front'));
        $qaResult = smile_design_lip_repositioning_preview_qa($beforePhoto, $result, $targetPhotoLabel, $targetPhotoType);
        $result['lip_repositioning_qa'] = $qaResult;

        if (smile_design_lip_repositioning_qa_requires_retry($qaResult) && empty($options['lip_surgical_retry'])) {
            $retryOptions = $options;
            $retryOptions['lip_surgical_retry'] = true;
            $retryOptions['lip_qa_feedback'] = smile_design_lip_repositioning_qa_feedback($qaResult);
            $retryOptions['custom_request'] = trim(implode("\n", array_values(array_filter([
                trim((string)($options['custom_request'] ?? '')),
                'Automatic QA retry emphasis: this attempt must visibly lower the actual upper-lip lower border closer to the cervical/top-edge line of the upper teeth. Do not only darken, recolor, blur, shadow, or minimize the gum band. The gum reduction must happen because the superior lip is lower and more naturally draped, while teeth and face stay unchanged.',
            ], static fn(string $value): bool => trim($value) !== ''))));
            $retryResult = $provider->createPreview($case, $sourcePhotos, $retryOptions);

            if (empty($retryResult['ok'])) {
                $retryResult['lip_repositioning_retry'] = [
                    'used' => true,
                    'first_qa' => $qaResult,
                ];
                db_execute(
                    "UPDATE ai_generation_jobs
                     SET status = 'failed', response_json = :response_json, updated_at = NOW()
                     WHERE id = :id",
                    [
                        'id' => $jobId,
                        'response_json' => json_encode($retryResult, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ]
                );
                smile_design_audit($caseId, 'ai_generation_failed', ['job_id' => $jobId, 'provider' => $providerName, 'reason' => 'lip_qa_retry_failed'], $userId);
                return $retryResult;
            }

            $retryQaResult = smile_design_lip_repositioning_preview_qa($beforePhoto, $retryResult, $targetPhotoLabel, $targetPhotoType);
            $retryResult['lip_repositioning_qa'] = $retryQaResult;
            $retryResult['lip_repositioning_retry'] = [
                'used' => true,
                'first_qa' => $qaResult,
            ];
            $result = $retryResult;
        }

        if (smile_design_lip_repositioning_qa_requires_retry((array)($result['lip_repositioning_qa'] ?? []))) {
            $feedback = smile_design_lip_repositioning_qa_feedback((array)$result['lip_repositioning_qa']);
            $failure = [
                'ok' => false,
                'provider' => $providerName,
                'message' => 'Lip repositioning QA rejected this preview. ' . ($feedback !== '' ? $feedback : 'The after did not preserve the teeth/face or did not meet the lip-line target.'),
                'lip_repositioning_qa' => $result['lip_repositioning_qa'] ?? null,
                'lip_repositioning_retry' => $result['lip_repositioning_retry'] ?? null,
                'request' => $result['request'] ?? null,
                'response' => $result['response'] ?? null,
            ];
            db_execute(
                "UPDATE ai_generation_jobs
                 SET status = 'failed', response_json = :response_json, updated_at = NOW()
                 WHERE id = :id",
                [
                    'id' => $jobId,
                    'response_json' => json_encode($failure, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]
            );
            smile_design_audit($caseId, 'ai_generation_failed', ['job_id' => $jobId, 'provider' => $providerName, 'reason' => 'lip_qa_rejected'], $userId);
            return $failure;
        }
    }

    $binary = base64_decode((string)($result['image_base64'] ?? ''), true);
    if (!is_string($binary) || $binary === '') {
        db_execute(
            "UPDATE ai_generation_jobs
             SET status = 'failed', response_json = :response_json, updated_at = NOW()
             WHERE id = :id",
            [
                'id' => $jobId,
                'response_json' => json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]
        );
        return ['ok' => false, 'message' => 'OpenAI returned an unreadable image payload.'];
    }

    $stored = smile_design_store_generated_image(
        $binary,
        (string)($result['mime_type'] ?? 'image/png'),
        'cases/' . $caseId . '/after',
        'png',
        [
            'target_width' => (int)($beforePhoto['width'] ?? 0),
            'target_height' => (int)($beforePhoto['height'] ?? 0),
        ]
    );
    if (empty($stored['ok'])) {
        db_execute(
            "UPDATE ai_generation_jobs
             SET status = 'failed', response_json = :response_json, updated_at = NOW()
             WHERE id = :id",
            [
                'id' => $jobId,
                'response_json' => json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]
        );
        return $stored;
    }

    $versionTitle = trim((string)($options['version_title'] ?? 'AI Smile Preview'));
    $version = smile_design_insert_after_version_record($caseId, $stored, [
        'before_photo_id' => $beforePhotoId,
        'version_title' => $versionTitle,
        'source_type' => 'ai_preview',
        'procedure_label' => $options['procedure_label'] ?? $case['procedure_interest'] ?? '',
        'lvi_style_key' => $options['lvi_style_key'] ?? $case['lvi_style_key'] ?? '',
        'photo_type' => $options['photo_type'] ?? ($beforePhoto['photo_type'] ?? 'front'),
        'notes' => $options['notes'] ?? ($result['revised_prompt'] ?? ''),
    ], $userId);

    if (empty($version['ok'])) {
        db_execute(
            "UPDATE ai_generation_jobs
             SET status = 'failed', response_json = :response_json, updated_at = NOW()
             WHERE id = :id",
            [
                'id' => $jobId,
                'response_json' => json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]
        );
        return $version;
    }

    $autoSelected = false;
    if (($options['auto_select_generated'] ?? true) !== false) {
        $autoSelected = smile_design_set_after_flag((int)$version['after_version_id'], 'selected_by_doctor', true, $userId);
    }

    db_insert(
        "INSERT INTO ai_generation_versions (job_id, case_id, version_label, storage_key, metadata_json, is_selected)
         VALUES (:job_id, :case_id, :version_label, :storage_key, :metadata_json, :is_selected)",
        [
            'job_id' => $jobId,
            'case_id' => $caseId,
            'version_label' => 'v1',
            'storage_key' => $stored['storage_key'],
            'metadata_json' => json_encode([
                'provider' => $providerName,
                'request' => $result['request'] ?? null,
                'revised_prompt' => $result['revised_prompt'] ?? '',
                'mime_type' => $stored['mime_type'],
                'after_version_id' => $version['after_version_id'],
                'auto_selected' => $autoSelected,
                'lip_repositioning_qa' => $result['lip_repositioning_qa'] ?? null,
                'lip_repositioning_retry' => $result['lip_repositioning_retry'] ?? null,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'is_selected' => $autoSelected ? 1 : 0,
        ]
    );

    db_execute(
        "UPDATE ai_generation_jobs
         SET status = 'completed', response_json = :response_json, updated_at = NOW()
         WHERE id = :id",
        [
            'id' => $jobId,
            'response_json' => json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]
    );

    smile_design_audit($caseId, 'ai_generation_completed', ['job_id' => $jobId, 'after_version_id' => $version['after_version_id']], $userId);

    return [
        'ok' => true,
        'job_id' => $jobId,
        'after_version_id' => $version['after_version_id'],
        'provider' => $providerName,
        'message' => 'AI smile preview generated.',
    ];
}

function smile_design_set_after_flag(int $versionId, string $flag, bool $enabled, ?int $userId = null): bool
{
    $allowed = ['selected_by_doctor', 'approved_for_patient_preview', 'approved_for_office_gallery', 'approved_for_marketing', 'archived'];
    if (!in_array($flag, $allowed, true)) {
        return false;
    }
    $version = db_one('SELECT * FROM smile_after_versions WHERE id = :id LIMIT 1', ['id' => $versionId]);
    if (!$version) {
        return false;
    }
    if ($flag === 'selected_by_doctor' && $enabled) {
        db_query('UPDATE smile_after_versions SET selected_by_doctor = 0 WHERE case_id = :case_id AND photo_type <=> :photo_type', ['case_id' => (int)$version['case_id'], 'photo_type' => $version['photo_type']]);
    }
    db_query("UPDATE smile_after_versions SET {$flag} = :value, updated_at = NOW() WHERE id = :id", ['value' => $enabled ? 1 : 0, 'id' => $versionId]);
    $event = match ($flag) {
        'selected_by_doctor' => 'after_version_selected',
        'approved_for_patient_preview' => 'after_version_approved_patient_preview',
        'approved_for_office_gallery' => 'after_version_approved_office_gallery',
        'archived' => 'after_version_archived',
        default => 'after_version_updated',
    };
    smile_design_audit((int)$version['case_id'], $event, ['after_version_id' => $versionId, 'enabled' => $enabled], $userId);
    return true;
}

function smile_design_delete_after_version(int $versionId, ?int $userId = null): array
{
    $version = db_one('SELECT * FROM smile_after_versions WHERE id = :id LIMIT 1', ['id' => $versionId]);
    if (!$version) {
        return ['ok' => false, 'message' => 'AFTER version not found.'];
    }

    $storageKey = (string)($version['storage_key'] ?? '');
    $filePath = $storageKey !== '' ? smile_design_safe_storage_path($storageKey) : null;

    db_execute('DELETE FROM ai_generation_versions WHERE case_id = :case_id AND storage_key = :storage_key', [
        'case_id' => (int)$version['case_id'],
        'storage_key' => $storageKey,
    ]);
    db_execute('DELETE FROM smile_pair_alignments WHERE after_version_id = :after_version_id', ['after_version_id' => $versionId]);
    db_execute('DELETE FROM smile_after_versions WHERE id = :id', ['id' => $versionId]);

    if ($filePath && is_file($filePath)) {
        @unlink($filePath);
    }

    smile_design_audit((int)$version['case_id'], 'after_version_deleted', [
        'after_version_id' => $versionId,
        'storage_key' => $storageKey,
    ], $userId);

    return ['ok' => true, 'case_id' => (int)$version['case_id']];
}

function smile_design_after_versions_by_case_ids(int $caseId, array $versionIds): array
{
    $versionIds = array_values(array_unique(array_filter(array_map('intval', $versionIds), static fn(int $id): bool => $id > 0)));
    if ($versionIds === []) {
        return [];
    }

    $params = ['case_id' => $caseId];
    $placeholders = [];
    foreach ($versionIds as $index => $versionId) {
        $key = 'id' . (string)$index;
        $params[$key] = $versionId;
        $placeholders[] = ':' . $key;
    }

    return db_all(
        'SELECT * FROM smile_after_versions WHERE case_id = :case_id AND id IN (' . implode(',', $placeholders) . ') ORDER BY version_number ASC, id ASC',
        $params
    );
}

function smile_design_delete_after_set(int $caseId, array $versionIds, ?int $userId = null): array
{
    $case = smile_design_case($caseId);
    if (!$case) {
        return ['ok' => false, 'message' => 'Smile case not found.'];
    }

    $versionIds = array_values(array_unique(array_filter(array_map('intval', $versionIds), static fn(int $id): bool => $id > 0)));
    if ($versionIds === []) {
        return ['ok' => false, 'message' => 'No after versions were included in this set.'];
    }

    $versions = smile_design_after_versions_by_case_ids($caseId, $versionIds);
    if (count($versions) !== count($versionIds)) {
        return ['ok' => false, 'message' => 'This set could not be found. Refresh the case and try again.'];
    }

    $deletedIds = [];
    foreach ($versions as $version) {
        $result = smile_design_delete_after_version((int)$version['id'], $userId);
        if (empty($result['ok'])) {
            return ['ok' => false, 'message' => (string)($result['message'] ?? 'Could not delete this set.')];
        }
        $deletedIds[] = (int)$version['id'];
    }

    smile_design_audit($caseId, 'after_set_deleted', [
        'after_version_ids' => $deletedIds,
    ], $userId);

    return ['ok' => true, 'deleted_count' => count($deletedIds)];
}

function smile_design_copy_after_version_file(int $caseId, array $version): array
{
    $sourceKey = trim((string)($version['storage_key'] ?? ''));
    $sourcePath = $sourceKey !== '' ? smile_design_safe_storage_path($sourceKey) : null;
    if (!$sourcePath || !is_file($sourcePath)) {
        return ['ok' => false, 'message' => 'The source after image could not be found.'];
    }

    $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = trim((string)($version['mime_type'] ?? ''));
    if (!isset($allowedMimes[$mime]) && function_exists('mime_content_type')) {
        $detectedMime = (string)@mime_content_type($sourcePath);
        if (isset($allowedMimes[$detectedMime])) {
            $mime = $detectedMime;
        }
    }
    if (!isset($allowedMimes[$mime])) {
        $mime = 'image/jpeg';
    }

    $ext = strtolower((string)pathinfo($sourcePath, PATHINFO_EXTENSION));
    if ($ext === 'jpeg') {
        $ext = 'jpg';
    }
    if (!in_array($ext, ['jpg', 'png', 'webp'], true)) {
        $ext = $allowedMimes[$mime];
    }

    $storagePrefix = 'cases/' . $caseId . '/after';
    $directory = smile_design_private_root() . '/' . $storagePrefix;
    ensure_directory($directory);
    $storageKey = $storagePrefix . '/' . date('YmdHis') . '-clean-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetPath = smile_design_private_root() . '/' . $storageKey;

    if (!@copy($sourcePath, $targetPath) || !is_file($targetPath)) {
        return ['ok' => false, 'message' => 'Could not copy the after image into a clean set.'];
    }

    $info = @getimagesize($targetPath);
    return [
        'ok' => true,
        'storage_key' => $storageKey,
        'mime_type' => $mime,
        'file_size' => (int)filesize($targetPath),
        'width' => isset($info[0]) ? (int)$info[0] : ((int)($version['width'] ?? 0) ?: null),
        'height' => isset($info[1]) ? (int)$info[1] : ((int)($version['height'] ?? 0) ?: null),
        'target_path' => $targetPath,
    ];
}

function smile_design_clone_active_after_set(int $caseId, ?int $userId = null): array
{
    $case = smile_design_case($caseId);
    if (!$case) {
        return ['ok' => false, 'message' => 'Smile case not found.'];
    }

    $angleLabels = [
        'front' => 'Front',
        'left_45' => 'Left 45',
        'right_45' => 'Right 45',
    ];
    $sourceVersions = [];
    foreach ($angleLabels as $photoType => $label) {
        $version = smile_design_selected_after_version($caseId, $photoType);
        if ($version && (int)($version['case_id'] ?? 0) === $caseId) {
            $sourceVersions[$photoType] = $version;
        }
    }
    if ($sourceVersions === []) {
        return ['ok' => false, 'message' => 'No active after angles are available to save as a clean set.'];
    }

    $copiedPaths = [];
    $newIds = [];
    $sourceIds = [];
    try {
        db_begin();

        foreach ($sourceVersions as $photoType => $sourceVersion) {
            $stored = smile_design_copy_after_version_file($caseId, $sourceVersion);
            if (empty($stored['ok'])) {
                throw new RuntimeException((string)($stored['message'] ?? 'Could not copy the active after image.'));
            }
            $copiedPaths[] = (string)$stored['target_path'];
            $sourceIds[$photoType] = (int)$sourceVersion['id'];

            $sourceTitle = trim((string)($sourceVersion['version_title'] ?? ''));
            $sourceNumber = (int)($sourceVersion['version_number'] ?? 0);
            $sourceNote = trim((string)($sourceVersion['notes'] ?? ''));
            $notes = 'Clean set copy from #' . (string)$sourceNumber . ($sourceTitle !== '' ? ' ' . $sourceTitle : '') . '.';
            if ($sourceNote !== '') {
                $notes .= "\n\n" . $sourceNote;
            }

            $insert = smile_design_insert_after_version_record($caseId, $stored, [
                'before_photo_id' => (int)($sourceVersion['before_photo_id'] ?? 0),
                'version_title' => 'Clean Set - ' . (string)$angleLabels[$photoType],
                'source_type' => (string)($sourceVersion['source_type'] ?? 'ai_preview'),
                'procedure_label' => (string)($sourceVersion['procedure_label'] ?? $case['procedure_interest'] ?? ''),
                'lvi_style_key' => (string)($sourceVersion['lvi_style_key'] ?? $case['lvi_style_key'] ?? ''),
                'photo_type' => $photoType,
                'notes' => $notes,
            ], $userId);

            if (empty($insert['ok'])) {
                throw new RuntimeException((string)($insert['message'] ?? 'Could not create the clean set version.'));
            }

            $newId = (int)$insert['after_version_id'];
            $newIds[$photoType] = $newId;

            $sourceAlignment = db_one(
                'SELECT * FROM smile_pair_alignments WHERE after_version_id = :after_version_id LIMIT 1',
                ['after_version_id' => (int)$sourceVersion['id']]
            );
            if ($sourceAlignment) {
                $sourceAlignment['pair_type'] = 'case_after';
                $sourceAlignment['case_id'] = $caseId;
                $sourceAlignment['before_photo_id'] = (int)($sourceVersion['before_photo_id'] ?? 0) ?: null;
                $sourceAlignment['after_version_id'] = $newId;
                $sourceAlignment['real_pair_id'] = null;
                $sourceAlignment['photo_type'] = $photoType;
                smile_design_save_alignment($sourceAlignment, $userId);
            }
        }

        foreach ($newIds as $newId) {
            smile_design_set_after_flag((int)$newId, 'selected_by_doctor', true, $userId);
        }

        smile_design_audit($caseId, 'after_set_cloned_from_active', [
            'source_after_version_ids' => $sourceIds,
            'new_after_version_ids' => $newIds,
        ], $userId);

        db_commit();
    } catch (Throwable $e) {
        db_rollBack();
        foreach ($copiedPaths as $copiedPath) {
            if ($copiedPath !== '' && is_file($copiedPath)) {
                @unlink($copiedPath);
            }
        }
        if (function_exists('esm_log')) {
            esm_log('smile_design', 'Clean after set clone failed', [
                'case_id' => $caseId,
                'message' => $e->getMessage(),
            ]);
        }
        return ['ok' => false, 'message' => 'Could not create a clean set from the active angles.'];
    }

    return ['ok' => true, 'after_version_ids' => $newIds, 'created_count' => count($newIds)];
}

function smile_design_replace_before_photo(int $photoId, array $file, ?int $userId = null): array
{
    $photo = db_one("SELECT * FROM smile_case_photos WHERE id = :id AND kind = 'before' LIMIT 1", ['id' => $photoId]);
    if (!$photo) {
        return ['ok' => false, 'message' => 'Before photo not found.'];
    }

    $caseId = (int)$photo['case_id'];
    $stored = smile_design_store_private_image($file, 'cases/' . $caseId . '/before');
    if (empty($stored['ok'])) {
        return $stored;
    }

    $oldStorageKey = (string)($photo['storage_key'] ?? '');
    $oldFilePath = $oldStorageKey !== '' ? smile_design_safe_storage_path($oldStorageKey) : null;

    db_execute(
        "UPDATE smile_case_photos
         SET storage_key = :storage_key,
             original_name = :original_name,
             mime_type = :mime_type,
             file_size = :file_size,
             width = :width,
             height = :height
         WHERE id = :id",
        [
            'id' => $photoId,
            'storage_key' => $stored['storage_key'],
            'original_name' => basename((string)($file['name'] ?? 'photo')),
            'mime_type' => (string)$stored['mime_type'],
            'file_size' => (int)($stored['file_size'] ?? 0),
            'width' => isset($stored['width']) ? (int)$stored['width'] : null,
            'height' => isset($stored['height']) ? (int)$stored['height'] : null,
        ]
    );

    if ($oldFilePath && is_file($oldFilePath)) {
        @unlink($oldFilePath);
    }

    smile_design_audit($caseId, 'before_photo_replaced', [
        'photo_id' => $photoId,
        'photo_type' => (string)($photo['photo_type'] ?? 'front'),
        'old_storage_key' => $oldStorageKey,
        'new_storage_key' => $stored['storage_key'],
    ], $userId);

    return ['ok' => true, 'case_id' => $caseId, 'photo_id' => $photoId];
}

function smile_design_delete_before_photo(int $photoId, ?int $userId = null): array
{
    $photo = db_one("SELECT * FROM smile_case_photos WHERE id = :id AND kind = 'before' LIMIT 1", ['id' => $photoId]);
    if (!$photo) {
        return ['ok' => false, 'message' => 'Before photo not found.'];
    }

    $caseId = (int)$photo['case_id'];
    $linkedAfterCount = (int)db_value(
        'SELECT COUNT(*) FROM smile_after_versions WHERE before_photo_id = :before_photo_id',
        ['before_photo_id' => $photoId]
    );
    if ($linkedAfterCount > 0) {
        return ['ok' => false, 'message' => 'This before photo is linked to after versions. Replace it instead of deleting it.'];
    }

    $beforeCount = (int)db_value(
        "SELECT COUNT(*) FROM smile_case_photos WHERE case_id = :case_id AND kind = 'before'",
        ['case_id' => $caseId]
    );
    if ($beforeCount <= 1) {
        return ['ok' => false, 'message' => 'A smile case needs at least one before photo. Replace this photo instead of deleting it.'];
    }

    $storageKey = (string)($photo['storage_key'] ?? '');
    $filePath = $storageKey !== '' ? smile_design_safe_storage_path($storageKey) : null;

    db_execute('DELETE FROM smile_case_photos WHERE id = :id', ['id' => $photoId]);

    if ($filePath && is_file($filePath)) {
        @unlink($filePath);
    }

    smile_design_audit($caseId, 'before_photo_deleted', [
        'photo_id' => $photoId,
        'photo_type' => (string)($photo['photo_type'] ?? 'front'),
        'storage_key' => $storageKey,
    ], $userId);

    return ['ok' => true, 'case_id' => $caseId];
}

function smile_design_delete_case(int $caseId, ?int $userId = null): array
{
    $case = smile_design_case($caseId);
    if (!$case) {
        return ['ok' => false, 'message' => 'Smile case not found.'];
    }

    $storageKeys = [];
    foreach (smile_design_case_photos($caseId) as $photo) {
        $key = trim((string)($photo['storage_key'] ?? ''));
        if ($key !== '') {
            $storageKeys[] = $key;
        }
    }
    foreach (smile_design_after_versions($caseId, true) as $version) {
        $key = trim((string)($version['storage_key'] ?? ''));
        if ($key !== '') {
            $storageKeys[] = $key;
        }
    }
    $storageKeys = array_values(array_unique($storageKeys));

    try {
        db_begin();

        db_execute(
            'DELETE spa FROM smile_pair_alignments spa INNER JOIN smile_after_versions sav ON sav.id = spa.after_version_id WHERE sav.case_id = :case_id',
            ['case_id' => $caseId]
        );
        db_execute('DELETE FROM smile_pair_alignments WHERE case_id = :case_id', ['case_id' => $caseId]);
        db_execute('DELETE FROM ai_generation_versions WHERE case_id = :case_id', ['case_id' => $caseId]);
        db_execute('DELETE FROM ai_generation_jobs WHERE case_id = :case_id', ['case_id' => $caseId]);
        db_execute('DELETE FROM smile_preview_links WHERE case_id = :case_id', ['case_id' => $caseId]);
        db_execute('DELETE FROM smile_notifications WHERE case_id = :case_id', ['case_id' => $caseId]);
        db_execute('DELETE FROM smile_audit_events WHERE case_id = :case_id', ['case_id' => $caseId]);
        db_execute('DELETE FROM smile_after_versions WHERE case_id = :case_id', ['case_id' => $caseId]);
        db_execute("DELETE FROM smile_case_photos WHERE case_id = :case_id AND kind = 'before'", ['case_id' => $caseId]);
        db_execute('DELETE FROM smile_cases WHERE id = :id', ['id' => $caseId]);

        db_commit();
    } catch (Throwable $e) {
        db_rollBack();
        if (function_exists('esm_log')) {
            esm_log('smile_design', 'Case delete failed', [
                'case_id' => $caseId,
                'message' => $e->getMessage(),
            ]);
        }
        return ['ok' => false, 'message' => 'Could not delete this smile case right now.'];
    }

    foreach ($storageKeys as $storageKey) {
        $filePath = smile_design_safe_storage_path($storageKey);
        if ($filePath && is_file($filePath)) {
            @unlink($filePath);
        }
    }

    smile_design_audit($caseId, 'case_deleted', [
        'deleted_case_id' => $caseId,
        'patient_name' => (string)($case['patient_name'] ?? ''),
    ], $userId);

    return ['ok' => true];
}

function smile_design_prepare_missing_reference_views(int $caseId, int $frontPhotoId, ?int $userId = null): array
{
    $case = smile_design_case($caseId);
    if (!$case) {
        return ['ok' => false, 'message' => 'Smile case not found.'];
    }
    $frontPhoto = db_one(
        "SELECT * FROM smile_case_photos WHERE id = :id AND case_id = :case_id AND kind = 'before' LIMIT 1",
        ['id' => $frontPhotoId, 'case_id' => $caseId]
    );
    if (!$frontPhoto) {
        return ['ok' => false, 'message' => 'Front before photo not found for this case.'];
    }
    if (!elite_gemini_is_configured()) {
        return ['ok' => false, 'message' => 'Google Gemini is not configured for AI reference views.'];
    }

    $frontPath = smile_design_safe_storage_path((string)($frontPhoto['storage_key'] ?? ''));
    if (!$frontPath || !is_file($frontPath)) {
        return ['ok' => false, 'message' => 'Front before photo file is missing from storage.'];
    }

    $targets = [
        [
            'types' => ['left_45'],
            'save_type' => 'left_45',
            'title' => 'Left 45',
            'prompt' => 'Create a realistic left 45-degree dental reference photo from this exact same patient portrait. Preserve the exact same person, face, hair, skin, lighting, expression, and identity. Change only the camera perspective to a believable left 45 smile reference for treatment planning. Do not beautify, retouch, or change the dental condition.',
        ],
        [
            'types' => ['right_45'],
            'save_type' => 'right_45',
            'title' => 'Right 45',
            'prompt' => 'Create a realistic right 45-degree dental reference photo from this exact same patient portrait. Preserve the exact same person, face, hair, skin, lighting, expression, and identity. This must show the opposite side from a left 45 view, with the patient turned so the other cheek is more prominent and the face is angled toward the left side of the frame. Change only the camera perspective to a believable right 45 smile reference for treatment planning. Do not beautify, retouch, or change the dental condition.',
        ],
        [
            'types' => ['smile_close_up', 'close_up_smile'],
            'save_type' => 'smile_close_up',
            'title' => 'Smile Close-Up',
            'prompt' => 'Create a realistic close-up dental smile reference from this exact same patient portrait. Preserve the exact same person, identity, and real dental condition. Crop in tighter to the mouth and smile area while keeping the result believable for treatment planning. Do not change the teeth, face, or cosmetic condition.',
        ],
    ];

    $created = [];
    foreach ($targets as $target) {
        if (smile_design_find_before_photo_by_type($caseId, $target['types'], false)) {
            continue;
        }

        $result = elite_gemini_generate_image_edit([
            ['path' => $frontPath, 'mime_type' => elite_gemini_detect_image_mime_type($frontPath)],
        ], $target['prompt'], [
            'model' => GOOGLE_GEMINI_IMAGE_MODEL,
        ]);

        if (empty($result['ok']) || empty($result['image_base64'])) {
            return ['ok' => false, 'message' => 'Could not prepare missing reference angles right now.'];
        }

        $binary = base64_decode((string)$result['image_base64'], true);
        if (!is_string($binary) || $binary === '') {
            return ['ok' => false, 'message' => 'A generated reference view came back unreadable.'];
        }

        $stored = smile_design_store_generated_image(
            $binary,
            (string)($result['mime_type'] ?? 'image/png'),
            'cases/' . $caseId . '/before',
            'png'
        );
        if (empty($stored['ok'])) {
            return $stored;
        }

        $insert = smile_design_insert_before_photo_record($caseId, $stored, [
            'photo_type' => $target['save_type'],
            'source_type' => 'ai_reference',
            'original_name' => 'AI Reference ' . $target['title'],
        ], $userId);
        if (empty($insert['ok'])) {
            return $insert;
        }

        $created[] = [
            'photo_id' => (int)$insert['photo_id'],
            'photo_type' => $target['save_type'],
            'title' => $target['title'],
        ];
    }

    if ($created !== []) {
        smile_design_audit($caseId, 'missing_reference_views_prepared', [
            'front_photo_id' => $frontPhotoId,
            'created' => $created,
        ], $userId);
    }

    return ['ok' => true, 'created' => $created];
}

function smile_design_required_tables(): array
{
    return ['smile_cases', 'smile_case_photos', 'smile_after_versions', 'real_result_cases', 'real_result_photo_pairs', 'lvi_style_samples', 'lvi_sample_images', 'smile_sample_cases', 'smile_preview_links', 'smile_notifications', 'ai_generation_jobs', 'ai_generation_versions', 'smile_audit_events', 'smile_pair_alignments'];
}

function smile_design_health(): array
{
    $tables = [];
    foreach (smile_design_required_tables() as $table) {
        $tables[$table] = (bool)db_value(
            "SELECT COUNT(*)
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = :schema
               AND TABLE_NAME = :table",
            ['schema' => DB_NAME, 'table' => $table]
        );
    }

    $root = smile_design_private_root();
    ensure_directory($root);

    return [
        'database' => true,
        'tables' => $tables,
        'storage_path' => $root,
        'storage_exists' => is_dir($root),
        'storage_writable' => is_writable($root),
        'photo_endpoint' => base_url('app/actions/smile_design_photo.php'),
        'gd_available' => extension_loaded('gd'),
        'max_upload_size' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'allowed_types' => ['JPG', 'PNG', 'WebP'],
        'token_system' => defined('APP_KEY') && APP_KEY !== '',
        'manual_after_upload_enabled' => true,
        'alignment_schema_ok' => !empty($tables['smile_pair_alignments']),
        'after_versions' => (int)db_value('SELECT COUNT(*) FROM smile_after_versions'),
        'cases' => (int)db_value('SELECT COUNT(*) FROM smile_cases'),
        'real_result_cases' => (int)db_value('SELECT COUNT(*) FROM real_result_cases'),
        'real_result_pairs' => (int)db_value('SELECT COUNT(*) FROM real_result_photo_pairs'),
        'lvi_samples' => (int)db_value('SELECT COUNT(*) FROM lvi_sample_images'),
        'sample_cases' => (int)db_value('SELECT COUNT(*) FROM smile_sample_cases'),
        'preview_links' => (int)db_value('SELECT COUNT(*) FROM smile_preview_links'),
        'saved_alignments' => (int)db_value('SELECT COUNT(*) FROM smile_pair_alignments'),
        'approved_preview_cases' => (int)db_value('SELECT COUNT(DISTINCT case_id) FROM smile_after_versions WHERE archived = 0 AND approved_for_patient_preview = 1'),
        'recent_audit_events' => db_all('SELECT * FROM smile_audit_events ORDER BY id DESC LIMIT 20'),
    ];
}
