-- ============================================================
-- Elite Smiles CRM — Database Setup
-- Run this once on your MySQL/MariaDB database.
-- File: install.sql
-- ============================================================

-- ------------------------------------------------------------
-- landing_pages table
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `landing_pages` (
  `id`                     INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `slug`                   VARCHAR(255)     NOT NULL,
  `procedure_type`         VARCHAR(100)     NOT NULL DEFAULT 'general',
  `city`                   VARCHAR(100)     NOT NULL DEFAULT '',
  `angle`                  VARCHAR(100)     NOT NULL DEFAULT '',
  `layout_variant`         VARCHAR(50)      NOT NULL DEFAULT 'standard',
  `quiz_type`              VARCHAR(100)     NOT NULL DEFAULT 'general',
  `traffic_source_default` VARCHAR(100)     NOT NULL DEFAULT 'website',
  `is_active`              TINYINT(1)       NOT NULL DEFAULT 0,
  -- Content fields (optional overrides — router uses configs if empty)
  `hero_title`             VARCHAR(500)     NOT NULL DEFAULT '',
  `hero_subtitle`          TEXT             NOT NULL DEFAULT '',
  `hero_image`             VARCHAR(500)     NOT NULL DEFAULT '',
  `offer_badge`            VARCHAR(200)     NOT NULL DEFAULT '',
  `offer_title`            VARCHAR(300)     NOT NULL DEFAULT '',
  `offer_description`      TEXT             NOT NULL DEFAULT '',
  `offer_value_label`      VARCHAR(100)     NOT NULL DEFAULT '',
  `primary_cta_text`       VARCHAR(300)     NOT NULL DEFAULT '',
  `intro_title`            VARCHAR(300)     NOT NULL DEFAULT '',
  `intro_text`             TEXT             NOT NULL DEFAULT '',
  `benefit_1_title`        VARCHAR(300)     NOT NULL DEFAULT '',
  `benefit_1_text`         TEXT             NOT NULL DEFAULT '',
  `benefit_2_title`        VARCHAR(300)     NOT NULL DEFAULT '',
  `benefit_2_text`         TEXT             NOT NULL DEFAULT '',
  `benefit_3_title`        VARCHAR(300)     NOT NULL DEFAULT '',
  `benefit_3_text`         TEXT             NOT NULL DEFAULT '',
  `qualification_title`    VARCHAR(300)     NOT NULL DEFAULT '',
  `qualification_text`     TEXT             NOT NULL DEFAULT '',
  `question_set`           VARCHAR(255)     NOT NULL DEFAULT '',
  `notes`                  TEXT             NOT NULL DEFAULT '',
  `created_at`             DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_slug` (`slug`),
  KEY `idx_procedure_city_angle` (`procedure_type`, `city`, `angle`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- leads table (if not already created)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `leads` (
  `id`                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `full_name`          VARCHAR(255)  NOT NULL DEFAULT '',
  `first_name`         VARCHAR(120)  NOT NULL DEFAULT '',
  `last_name`          VARCHAR(120)  NOT NULL DEFAULT '',
  `email`              VARCHAR(255)  NOT NULL DEFAULT '',
  `phone`              VARCHAR(50)   NOT NULL DEFAULT '',
  `procedure_interest` VARCHAR(255)  NOT NULL DEFAULT '',
  `source`             VARCHAR(100)  NOT NULL DEFAULT 'website',
  `source_medium`      VARCHAR(100)  NOT NULL DEFAULT 'landing',
  `source_type`        VARCHAR(100)  NOT NULL DEFAULT 'quiz_form',
  `landing_page`       VARCHAR(255)  NOT NULL DEFAULT '',
  `campaign`           VARCHAR(255)  NOT NULL DEFAULT '',
  `status`             VARCHAR(100)  NOT NULL DEFAULT 'new_lead',
  `financing_needed`   VARCHAR(20)   NOT NULL DEFAULT 'unsure',
  `financing_option`   VARCHAR(100)  NOT NULL DEFAULT 'none',
  `lead_value`         DECIMAL(10,2) NOT NULL DEFAULT 10000.00,
  `external_lead_id`   VARCHAR(255)  NOT NULL DEFAULT '',
  `notes`              TEXT          NOT NULL DEFAULT '',
  `is_active`          TINYINT(1)    NOT NULL DEFAULT 1,
  `created_by`         INT UNSIGNED           DEFAULT NULL,
  `created_at`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login_at`      DATETIME               DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status`       (`status`),
  KEY `idx_source`       (`source`),
  KEY `idx_landing_page` (`landing_page`),
  KEY `idx_created_at`   (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- users table (if not already created)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `first_name`     VARCHAR(120)  NOT NULL DEFAULT '',
  `last_name`      VARCHAR(120)  NOT NULL DEFAULT '',
  `email`          VARCHAR(255)  NOT NULL,
  `password_hash`  VARCHAR(255)  NOT NULL DEFAULT '',
  `role`           VARCHAR(50)   NOT NULL DEFAULT 'viewer',
  `is_active`      TINYINT(1)    NOT NULL DEFAULT 1,
  `invite_token`   VARCHAR(64)            DEFAULT NULL,
  `invite_expires` DATETIME               DEFAULT NULL,
  `reset_token`    VARCHAR(64)            DEFAULT NULL,
  `reset_expires`  DATETIME               DEFAULT NULL,
  `last_login_at`  DATETIME               DEFAULT NULL,
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- mobile ai access tables
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_mobile_access_tokens` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NOT NULL,
  `token_hash`      CHAR(64)     NOT NULL,
  `token_plaintext` TEXT                  DEFAULT NULL,
  `expires_at`      DATETIME              DEFAULT NULL,
  `used_at`         DATETIME              DEFAULT NULL,
  `revoked_at`      DATETIME              DEFAULT NULL,
  `created_by`      INT UNSIGNED          DEFAULT NULL,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_mobile_access_hash` (`token_hash`),
  KEY `idx_mobile_access_user` (`user_id`),
  KEY `idx_mobile_access_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_mobile_sessions` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`            INT UNSIGNED NOT NULL,
  `session_token_hash` CHAR(64)     NOT NULL,
  `device_label`       VARCHAR(190) NOT NULL DEFAULT 'Mobile Device',
  `user_agent`         VARCHAR(255) NOT NULL DEFAULT '',
  `ip_address`         VARCHAR(64)  NOT NULL DEFAULT '',
  `last_seen_at`       DATETIME              DEFAULT NULL,
  `expires_at`         DATETIME              DEFAULT NULL,
  `revoked_at`         DATETIME              DEFAULT NULL,
  `created_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_mobile_session_hash` (`session_token_hash`),
  KEY `idx_mobile_session_user` (`user_id`),
  KEY `idx_mobile_session_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_push_subscriptions` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`            INT UNSIGNED NOT NULL,
  `endpoint_hash`      CHAR(64)     NOT NULL,
  `endpoint`           TEXT         NOT NULL,
  `subscription_json`  LONGTEXT              DEFAULT NULL,
  `browser`            VARCHAR(150) NOT NULL DEFAULT '',
  `device_label`       VARCHAR(190) NOT NULL DEFAULT '',
  `enabled`            TINYINT(1)   NOT NULL DEFAULT 1,
  `push_enabled`       TINYINT(1)   NOT NULL DEFAULT 1,
  `sound_enabled`      TINYINT(1)   NOT NULL DEFAULT 1,
  `quiet_hours_json`   VARCHAR(255) NOT NULL DEFAULT '',
  `high_priority_only` TINYINT(1)   NOT NULL DEFAULT 0,
  `last_seen_at`       DATETIME              DEFAULT NULL,
  `revoked_at`         DATETIME              DEFAULT NULL,
  `created_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_push_endpoint_hash` (`endpoint_hash`),
  KEY `idx_push_subscription_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `elite_ai_audit_logs` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`           INT UNSIGNED NOT NULL,
  `surface`           VARCHAR(32)  NOT NULL DEFAULT 'desktop',
  `prompt`            TEXT         NOT NULL,
  `tools_used_json`   LONGTEXT              DEFAULT NULL,
  `response_summary`  TEXT         NOT NULL,
  `lead_id`           INT UNSIGNED          DEFAULT NULL,
  `page_context_json` LONGTEXT              DEFAULT NULL,
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_elite_ai_audit_user` (`user_id`),
  KEY `idx_elite_ai_audit_surface` (`surface`),
  KEY `idx_elite_ai_audit_lead` (`lead_id`),
  KEY `idx_elite_ai_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `elite_ai_action_queue` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`           INT UNSIGNED NOT NULL,
  `surface`           VARCHAR(32)  NOT NULL DEFAULT 'desktop',
  `action_type`       VARCHAR(60)  NOT NULL,
  `lead_id`           INT UNSIGNED NOT NULL,
  `status`            VARCHAR(20)  NOT NULL DEFAULT 'pending_review',
  `request_prompt`    TEXT              DEFAULT NULL,
  `request_context_json` LONGTEXT       DEFAULT NULL,
  `request_payload_json` LONGTEXT       DEFAULT NULL,
  `draft_payload_json` LONGTEXT        DEFAULT NULL,
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at`      DATETIME             DEFAULT NULL,
  `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_elite_ai_action_queue_user` (`user_id`),
  KEY `idx_elite_ai_action_queue_status` (`status`),
  KEY `idx_elite_ai_action_queue_lead` (`lead_id`),
  KEY `idx_elite_ai_action_queue_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Seed all 200 landing pages (5 procedures x 8 cities x 5 variants)
-- All set is_active=0 — activate them from the admin panel
-- or by running: UPDATE landing_pages SET is_active=1 WHERE slug='your-slug';
-- ------------------------------------------------------------

INSERT IGNORE INTO `landing_pages` (`slug`, `procedure_type`, `city`, `angle`, `quiz_type`, `is_active`) VALUES
-- VENEERS - DRAPER
('veneers-draper-v1',                        'veneers', 'draper',       '',                   'veneers', 0),
('veneers-draper-premium-trust-v1',          'veneers', 'draper',       'premium_trust',      'veneers', 0),
('veneers-draper-financing-v1',              'veneers', 'draper',       'financing',          'veneers', 0),
('veneers-draper-transformation-v1',         'veneers', 'draper',       'transformation',     'veneers', 0),
('veneers-draper-education-comparison-v1',   'veneers', 'draper',       'education_comparison','veneers', 0),
-- VENEERS - LEHI
('veneers-lehi-v1',                          'veneers', 'lehi',         '',                   'veneers', 0),
('veneers-lehi-premium-trust-v1',            'veneers', 'lehi',         'premium_trust',      'veneers', 0),
('veneers-lehi-financing-v1',                'veneers', 'lehi',         'financing',          'veneers', 0),
('veneers-lehi-transformation-v1',           'veneers', 'lehi',         'transformation',     'veneers', 0),
('veneers-lehi-education-comparison-v1',     'veneers', 'lehi',         'education_comparison','veneers', 0),
-- VENEERS - SOUTH JORDAN
('veneers-south-jordan-v1',                  'veneers', 'south-jordan', '',                   'veneers', 0),
('veneers-south-jordan-premium-trust-v1',    'veneers', 'south-jordan', 'premium_trust',      'veneers', 0),
('veneers-south-jordan-financing-v1',        'veneers', 'south-jordan', 'financing',          'veneers', 0),
('veneers-south-jordan-transformation-v1',   'veneers', 'south-jordan', 'transformation',     'veneers', 0),
('veneers-south-jordan-education-comparison-v1','veneers','south-jordan','education_comparison','veneers', 0),
-- VENEERS - HIGHLAND
('veneers-highland-v1',                      'veneers', 'highland',     '',                   'veneers', 0),
('veneers-highland-premium-trust-v1',        'veneers', 'highland',     'premium_trust',      'veneers', 0),
('veneers-highland-financing-v1',            'veneers', 'highland',     'financing',          'veneers', 0),
('veneers-highland-transformation-v1',       'veneers', 'highland',     'transformation',     'veneers', 0),
('veneers-highland-education-comparison-v1', 'veneers', 'highland',     'education_comparison','veneers', 0),
-- VENEERS - ALPINE
('veneers-alpine-v1',                        'veneers', 'alpine',       '',                   'veneers', 0),
('veneers-alpine-premium-trust-v1',          'veneers', 'alpine',       'premium_trust',      'veneers', 0),
('veneers-alpine-financing-v1',              'veneers', 'alpine',       'financing',          'veneers', 0),
('veneers-alpine-transformation-v1',         'veneers', 'alpine',       'transformation',     'veneers', 0),
('veneers-alpine-education-comparison-v1',   'veneers', 'alpine',       'education_comparison','veneers', 0),
-- VENEERS - PARK CITY
('veneers-park-city-v1',                     'veneers', 'park-city',    '',                   'veneers', 0),
('veneers-park-city-premium-trust-v1',       'veneers', 'park-city',    'premium_trust',      'veneers', 0),
('veneers-park-city-financing-v1',           'veneers', 'park-city',    'financing',          'veneers', 0),
('veneers-park-city-transformation-v1',      'veneers', 'park-city',    'transformation',     'veneers', 0),
('veneers-park-city-education-comparison-v1','veneers', 'park-city',    'education_comparison','veneers', 0),
-- VENEERS - FARMINGTON
('veneers-farmington-v1',                    'veneers', 'farmington',   '',                   'veneers', 0),
('veneers-farmington-premium-trust-v1',      'veneers', 'farmington',   'premium_trust',      'veneers', 0),
('veneers-farmington-financing-v1',          'veneers', 'farmington',   'financing',          'veneers', 0),
('veneers-farmington-transformation-v1',     'veneers', 'farmington',   'transformation',     'veneers', 0),
('veneers-farmington-education-comparison-v1','veneers','farmington',   'education_comparison','veneers', 0),
-- VENEERS - CEDAR HILLS
('veneers-cedar-hills-v1',                   'veneers', 'cedar-hills',  '',                   'veneers', 0),
('veneers-cedar-hills-premium-trust-v1',     'veneers', 'cedar-hills',  'premium_trust',      'veneers', 0),
('veneers-cedar-hills-financing-v1',         'veneers', 'cedar-hills',  'financing',          'veneers', 0),
('veneers-cedar-hills-transformation-v1',    'veneers', 'cedar-hills',  'transformation',     'veneers', 0),
('veneers-cedar-hills-education-comparison-v1','veneers','cedar-hills', 'education_comparison','veneers', 0),

-- IMPLANTS - ALL CITIES
('implants-draper-v1',                       'implants','draper',       '',               'implants', 0),
('implants-draper-premium-trust-v1',         'implants','draper',       'premium_trust',  'implants', 0),
('implants-draper-financing-v1',             'implants','draper',       'financing',      'implants', 0),
('implants-draper-transformation-v1',        'implants','draper',       'transformation', 'implants', 0),
('implants-draper-education-comparison-v1',  'implants','draper',       'education_comparison','implants', 0),
('implants-lehi-v1',                         'implants','lehi',         '',               'implants', 0),
('implants-lehi-premium-trust-v1',           'implants','lehi',         'premium_trust',  'implants', 0),
('implants-lehi-financing-v1',               'implants','lehi',         'financing',      'implants', 0),
('implants-lehi-transformation-v1',          'implants','lehi',         'transformation', 'implants', 0),
('implants-lehi-education-comparison-v1',    'implants','lehi',         'education_comparison','implants', 0),
('implants-south-jordan-v1',                 'implants','south-jordan', '',               'implants', 0),
('implants-south-jordan-premium-trust-v1',   'implants','south-jordan', 'premium_trust',  'implants', 0),
('implants-south-jordan-financing-v1',       'implants','south-jordan', 'financing',      'implants', 0),
('implants-south-jordan-transformation-v1',  'implants','south-jordan', 'transformation', 'implants', 0),
('implants-south-jordan-education-comparison-v1','implants','south-jordan','education_comparison','implants', 0),
('implants-highland-v1',                     'implants','highland',     '',               'implants', 0),
('implants-highland-premium-trust-v1',       'implants','highland',     'premium_trust',  'implants', 0),
('implants-highland-financing-v1',           'implants','highland',     'financing',      'implants', 0),
('implants-highland-transformation-v1',      'implants','highland',     'transformation', 'implants', 0),
('implants-highland-education-comparison-v1','implants','highland',     'education_comparison','implants', 0),
('implants-alpine-v1',                       'implants','alpine',       '',               'implants', 0),
('implants-alpine-premium-trust-v1',         'implants','alpine',       'premium_trust',  'implants', 0),
('implants-alpine-financing-v1',             'implants','alpine',       'financing',      'implants', 0),
('implants-alpine-transformation-v1',        'implants','alpine',       'transformation', 'implants', 0),
('implants-alpine-education-comparison-v1',  'implants','alpine',       'education_comparison','implants', 0),
('implants-park-city-v1',                    'implants','park-city',    '',               'implants', 0),
('implants-park-city-premium-trust-v1',      'implants','park-city',    'premium_trust',  'implants', 0),
('implants-park-city-financing-v1',          'implants','park-city',    'financing',      'implants', 0),
('implants-park-city-transformation-v1',     'implants','park-city',    'transformation', 'implants', 0),
('implants-park-city-education-comparison-v1','implants','park-city',   'education_comparison','implants', 0),
('implants-farmington-v1',                   'implants','farmington',   '',               'implants', 0),
('implants-farmington-premium-trust-v1',     'implants','farmington',   'premium_trust',  'implants', 0),
('implants-farmington-financing-v1',         'implants','farmington',   'financing',      'implants', 0),
('implants-farmington-transformation-v1',    'implants','farmington',   'transformation', 'implants', 0),
('implants-farmington-education-comparison-v1','implants','farmington', 'education_comparison','implants', 0),
('implants-cedar-hills-v1',                  'implants','cedar-hills',  '',               'implants', 0),
('implants-cedar-hills-premium-trust-v1',    'implants','cedar-hills',  'premium_trust',  'implants', 0),
('implants-cedar-hills-financing-v1',        'implants','cedar-hills',  'financing',      'implants', 0),
('implants-cedar-hills-transformation-v1',   'implants','cedar-hills',  'transformation', 'implants', 0),
('implants-cedar-hills-education-comparison-v1','implants','cedar-hills','education_comparison','implants', 0),

-- ALL-ON-X - ALL CITIES
('all-on-x-draper-v1',                       'all_on_x','draper',       '',               'all_on_x', 0),
('all-on-x-draper-premium-trust-v1',         'all_on_x','draper',       'premium_trust',  'all_on_x', 0),
('all-on-x-draper-financing-v1',             'all_on_x','draper',       'financing',      'all_on_x', 0),
('all-on-x-draper-transformation-v1',        'all_on_x','draper',       'transformation', 'all_on_x', 0),
('all-on-x-draper-education-comparison-v1',  'all_on_x','draper',       'education_comparison','all_on_x', 0),
('all-on-x-lehi-v1',                         'all_on_x','lehi',         '',               'all_on_x', 0),
('all-on-x-lehi-premium-trust-v1',           'all_on_x','lehi',         'premium_trust',  'all_on_x', 0),
('all-on-x-lehi-financing-v1',               'all_on_x','lehi',         'financing',      'all_on_x', 0),
('all-on-x-lehi-transformation-v1',          'all_on_x','lehi',         'transformation', 'all_on_x', 0),
('all-on-x-lehi-education-comparison-v1',    'all_on_x','lehi',         'education_comparison','all_on_x', 0),
('all-on-x-south-jordan-v1',                 'all_on_x','south-jordan', '',               'all_on_x', 0),
('all-on-x-south-jordan-premium-trust-v1',   'all_on_x','south-jordan', 'premium_trust',  'all_on_x', 0),
('all-on-x-south-jordan-financing-v1',       'all_on_x','south-jordan', 'financing',      'all_on_x', 0),
('all-on-x-south-jordan-transformation-v1',  'all_on_x','south-jordan', 'transformation', 'all_on_x', 0),
('all-on-x-south-jordan-education-comparison-v1','all_on_x','south-jordan','education_comparison','all_on_x', 0),
('all-on-x-highland-v1',                     'all_on_x','highland',     '',               'all_on_x', 0),
('all-on-x-highland-premium-trust-v1',       'all_on_x','highland',     'premium_trust',  'all_on_x', 0),
('all-on-x-highland-financing-v1',           'all_on_x','highland',     'financing',      'all_on_x', 0),
('all-on-x-highland-transformation-v1',      'all_on_x','highland',     'transformation', 'all_on_x', 0),
('all-on-x-highland-education-comparison-v1','all_on_x','highland',     'education_comparison','all_on_x', 0),
('all-on-x-alpine-v1',                       'all_on_x','alpine',       '',               'all_on_x', 0),
('all-on-x-alpine-premium-trust-v1',         'all_on_x','alpine',       'premium_trust',  'all_on_x', 0),
('all-on-x-alpine-financing-v1',             'all_on_x','alpine',       'financing',      'all_on_x', 0),
('all-on-x-alpine-transformation-v1',        'all_on_x','alpine',       'transformation', 'all_on_x', 0),
('all-on-x-alpine-education-comparison-v1',  'all_on_x','alpine',       'education_comparison','all_on_x', 0),
('all-on-x-park-city-v1',                    'all_on_x','park-city',    '',               'all_on_x', 0),
('all-on-x-park-city-premium-trust-v1',      'all_on_x','park-city',    'premium_trust',  'all_on_x', 0),
('all-on-x-park-city-financing-v1',          'all_on_x','park-city',    'financing',      'all_on_x', 0),
('all-on-x-park-city-transformation-v1',     'all_on_x','park-city',    'transformation', 'all_on_x', 0),
('all-on-x-park-city-education-comparison-v1','all_on_x','park-city',   'education_comparison','all_on_x', 0),
('all-on-x-farmington-v1',                   'all_on_x','farmington',   '',               'all_on_x', 0),
('all-on-x-farmington-premium-trust-v1',     'all_on_x','farmington',   'premium_trust',  'all_on_x', 0),
('all-on-x-farmington-financing-v1',         'all_on_x','farmington',   'financing',      'all_on_x', 0),
('all-on-x-farmington-transformation-v1',    'all_on_x','farmington',   'transformation', 'all_on_x', 0),
('all-on-x-farmington-education-comparison-v1','all_on_x','farmington', 'education_comparison','all_on_x', 0),
('all-on-x-cedar-hills-v1',                  'all_on_x','cedar-hills',  '',               'all_on_x', 0),
('all-on-x-cedar-hills-premium-trust-v1',    'all_on_x','cedar-hills',  'premium_trust',  'all_on_x', 0),
('all-on-x-cedar-hills-financing-v1',        'all_on_x','cedar-hills',  'financing',      'all_on_x', 0),
('all-on-x-cedar-hills-transformation-v1',   'all_on_x','cedar-hills',  'transformation', 'all_on_x', 0),
('all-on-x-cedar-hills-education-comparison-v1','all_on_x','cedar-hills','education_comparison','all_on_x', 0),

-- SMILE MAKEOVER - ALL CITIES
('smile-makeover-draper-v1',                 'smile_makeover','draper','',                'general', 0),
('smile-makeover-draper-premium-trust-v1',   'smile_makeover','draper','premium_trust',   'general', 0),
('smile-makeover-draper-financing-v1',       'smile_makeover','draper','financing',       'general', 0),
('smile-makeover-draper-transformation-v1',  'smile_makeover','draper','transformation',  'general', 0),
('smile-makeover-draper-education-comparison-v1','smile_makeover','draper','education_comparison','general', 0),
('smile-makeover-lehi-v1',                   'smile_makeover','lehi',  '',                'general', 0),
('smile-makeover-lehi-premium-trust-v1',     'smile_makeover','lehi',  'premium_trust',   'general', 0),
('smile-makeover-lehi-financing-v1',         'smile_makeover','lehi',  'financing',       'general', 0),
('smile-makeover-lehi-transformation-v1',    'smile_makeover','lehi',  'transformation',  'general', 0),
('smile-makeover-lehi-education-comparison-v1','smile_makeover','lehi','education_comparison','general', 0),
('smile-makeover-south-jordan-v1',           'smile_makeover','south-jordan','',          'general', 0),
('smile-makeover-south-jordan-premium-trust-v1','smile_makeover','south-jordan','premium_trust','general', 0),
('smile-makeover-south-jordan-financing-v1', 'smile_makeover','south-jordan','financing', 'general', 0),
('smile-makeover-south-jordan-transformation-v1','smile_makeover','south-jordan','transformation','general', 0),
('smile-makeover-south-jordan-education-comparison-v1','smile_makeover','south-jordan','education_comparison','general', 0),
('smile-makeover-highland-v1',               'smile_makeover','highland','',              'general', 0),
('smile-makeover-highland-premium-trust-v1', 'smile_makeover','highland','premium_trust', 'general', 0),
('smile-makeover-highland-financing-v1',     'smile_makeover','highland','financing',     'general', 0),
('smile-makeover-highland-transformation-v1','smile_makeover','highland','transformation','general', 0),
('smile-makeover-highland-education-comparison-v1','smile_makeover','highland','education_comparison','general', 0),
('smile-makeover-alpine-v1',                 'smile_makeover','alpine', '',               'general', 0),
('smile-makeover-alpine-premium-trust-v1',   'smile_makeover','alpine', 'premium_trust',  'general', 0),
('smile-makeover-alpine-financing-v1',       'smile_makeover','alpine', 'financing',      'general', 0),
('smile-makeover-alpine-transformation-v1',  'smile_makeover','alpine', 'transformation', 'general', 0),
('smile-makeover-alpine-education-comparison-v1','smile_makeover','alpine','education_comparison','general', 0),
('smile-makeover-park-city-v1',              'smile_makeover','park-city','',             'general', 0),
('smile-makeover-park-city-premium-trust-v1','smile_makeover','park-city','premium_trust','general', 0),
('smile-makeover-park-city-financing-v1',    'smile_makeover','park-city','financing',    'general', 0),
('smile-makeover-park-city-transformation-v1','smile_makeover','park-city','transformation','general', 0),
('smile-makeover-park-city-education-comparison-v1','smile_makeover','park-city','education_comparison','general', 0),
('smile-makeover-farmington-v1',             'smile_makeover','farmington','',            'general', 0),
('smile-makeover-farmington-premium-trust-v1','smile_makeover','farmington','premium_trust','general', 0),
('smile-makeover-farmington-financing-v1',   'smile_makeover','farmington','financing',   'general', 0),
('smile-makeover-farmington-transformation-v1','smile_makeover','farmington','transformation','general', 0),
('smile-makeover-farmington-education-comparison-v1','smile_makeover','farmington','education_comparison','general', 0),
('smile-makeover-cedar-hills-v1',            'smile_makeover','cedar-hills','',           'general', 0),
('smile-makeover-cedar-hills-premium-trust-v1','smile_makeover','cedar-hills','premium_trust','general', 0),
('smile-makeover-cedar-hills-financing-v1',  'smile_makeover','cedar-hills','financing',  'general', 0),
('smile-makeover-cedar-hills-transformation-v1','smile_makeover','cedar-hills','transformation','general', 0),
('smile-makeover-cedar-hills-education-comparison-v1','smile_makeover','cedar-hills','education_comparison','general', 0),

-- LIP REPOSITIONING - ALL CITIES
('lip-repositioning-draper-v1',              'lip_repositioning','draper','',             'general', 0),
('lip-repositioning-draper-premium-trust-v1','lip_repositioning','draper','premium_trust','general', 0),
('lip-repositioning-draper-financing-v1',    'lip_repositioning','draper','financing',    'general', 0),
('lip-repositioning-draper-transformation-v1','lip_repositioning','draper','transformation','general', 0),
('lip-repositioning-draper-education-comparison-v1','lip_repositioning','draper','education_comparison','general', 0),
('lip-repositioning-lehi-v1',                'lip_repositioning','lehi', '',              'general', 0),
('lip-repositioning-lehi-premium-trust-v1',  'lip_repositioning','lehi', 'premium_trust', 'general', 0),
('lip-repositioning-lehi-financing-v1',      'lip_repositioning','lehi', 'financing',     'general', 0),
('lip-repositioning-lehi-transformation-v1', 'lip_repositioning','lehi', 'transformation','general', 0),
('lip-repositioning-lehi-education-comparison-v1','lip_repositioning','lehi','education_comparison','general', 0),
('lip-repositioning-south-jordan-v1',        'lip_repositioning','south-jordan','',       'general', 0),
('lip-repositioning-south-jordan-premium-trust-v1','lip_repositioning','south-jordan','premium_trust','general', 0),
('lip-repositioning-south-jordan-financing-v1','lip_repositioning','south-jordan','financing','general', 0),
('lip-repositioning-south-jordan-transformation-v1','lip_repositioning','south-jordan','transformation','general', 0),
('lip-repositioning-south-jordan-education-comparison-v1','lip_repositioning','south-jordan','education_comparison','general', 0),
('lip-repositioning-highland-v1',            'lip_repositioning','highland','',           'general', 0),
('lip-repositioning-highland-premium-trust-v1','lip_repositioning','highland','premium_trust','general', 0),
('lip-repositioning-highland-financing-v1',  'lip_repositioning','highland','financing',  'general', 0),
('lip-repositioning-highland-transformation-v1','lip_repositioning','highland','transformation','general', 0),
('lip-repositioning-highland-education-comparison-v1','lip_repositioning','highland','education_comparison','general', 0),
('lip-repositioning-alpine-v1',              'lip_repositioning','alpine', '',            'general', 0),
('lip-repositioning-alpine-premium-trust-v1','lip_repositioning','alpine', 'premium_trust','general', 0),
('lip-repositioning-alpine-financing-v1',    'lip_repositioning','alpine', 'financing',   'general', 0),
('lip-repositioning-alpine-transformation-v1','lip_repositioning','alpine','transformation','general', 0),
('lip-repositioning-alpine-education-comparison-v1','lip_repositioning','alpine','education_comparison','general', 0),
('lip-repositioning-park-city-v1',           'lip_repositioning','park-city','',          'general', 0),
('lip-repositioning-park-city-premium-trust-v1','lip_repositioning','park-city','premium_trust','general', 0),
('lip-repositioning-park-city-financing-v1', 'lip_repositioning','park-city','financing', 'general', 0),
('lip-repositioning-park-city-transformation-v1','lip_repositioning','park-city','transformation','general', 0),
('lip-repositioning-park-city-education-comparison-v1','lip_repositioning','park-city','education_comparison','general', 0),
('lip-repositioning-farmington-v1',          'lip_repositioning','farmington','',         'general', 0),
('lip-repositioning-farmington-premium-trust-v1','lip_repositioning','farmington','premium_trust','general', 0),
('lip-repositioning-farmington-financing-v1','lip_repositioning','farmington','financing', 'general', 0),
('lip-repositioning-farmington-transformation-v1','lip_repositioning','farmington','transformation','general', 0),
('lip-repositioning-farmington-education-comparison-v1','lip_repositioning','farmington','education_comparison','general', 0),
('lip-repositioning-cedar-hills-v1',         'lip_repositioning','cedar-hills','',        'general', 0),
('lip-repositioning-cedar-hills-premium-trust-v1','lip_repositioning','cedar-hills','premium_trust','general', 0),
('lip-repositioning-cedar-hills-financing-v1','lip_repositioning','cedar-hills','financing','general', 0),
('lip-repositioning-cedar-hills-transformation-v1','lip_repositioning','cedar-hills','transformation','general', 0),
('lip-repositioning-cedar-hills-education-comparison-v1','lip_repositioning','cedar-hills','education_comparison','general', 0);

-- Activate the first veneers Draper pages as a starting point
UPDATE `landing_pages` SET `is_active` = 1
WHERE `slug` IN (
  'veneers-draper-v1',
  'veneers-draper-premium-trust-v1',
  'veneers-draper-financing-v1',
  'veneers-draper-transformation-v1',
  'veneers-draper-education-comparison-v1'
);

-- ------------------------------------------------------------
-- Patient Experience foundation tables
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `patient_experience_kiosk_devices` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `device_label`      VARCHAR(190) NOT NULL DEFAULT 'Waiting Room iPad',
  `device_token_hash` CHAR(64)              DEFAULT NULL,
  `location_label`    VARCHAR(190) NOT NULL DEFAULT 'Front Desk',
  `is_active`         TINYINT(1)   NOT NULL DEFAULT 1,
  `registered_at`     DATETIME              DEFAULT NULL,
  `last_seen_at`      DATETIME              DEFAULT NULL,
  `created_by`        INT UNSIGNED          DEFAULT NULL,
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_patient_exp_device_token` (`device_token_hash`),
  KEY `idx_patient_exp_device_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `patient_experience_kiosk_setup_tokens` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kiosk_device_id` INT UNSIGNED NOT NULL,
  `token_hash`      CHAR(64)     NOT NULL,
  `expires_at`      DATETIME     NOT NULL,
  `used_at`         DATETIME              DEFAULT NULL,
  `revoked_at`      DATETIME              DEFAULT NULL,
  `created_by`      INT UNSIGNED          DEFAULT NULL,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_patient_exp_setup_token_hash` (`token_hash`),
  KEY `idx_patient_exp_setup_device` (`kiosk_device_id`),
  KEY `idx_patient_exp_setup_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `patient_experience_checkin_sessions` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kiosk_device_id`    INT UNSIGNED          DEFAULT NULL,
  `lead_id`            INT UNSIGNED          DEFAULT NULL,
  `patient_name`       VARCHAR(190) NOT NULL DEFAULT '',
  `session_token_hash` CHAR(64)     NOT NULL,
  `status`             VARCHAR(40)  NOT NULL DEFAULT 'waiting',
  `started_by_user_id` INT UNSIGNED          DEFAULT NULL,
  `expires_at`         DATETIME     NOT NULL,
  `started_at`         DATETIME              DEFAULT NULL,
  `completed_at`       DATETIME              DEFAULT NULL,
  `cancelled_at`       DATETIME              DEFAULT NULL,
  `expired_at`         DATETIME              DEFAULT NULL,
  `device_user_agent`  VARCHAR(255) NOT NULL DEFAULT '',
  `device_ip`          VARCHAR(80)  NOT NULL DEFAULT '',
  `staff_notes`        TEXT                  DEFAULT NULL,
  `created_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_patient_exp_session_token` (`session_token_hash`),
  KEY `idx_patient_exp_session_status` (`status`),
  KEY `idx_patient_exp_session_lead` (`lead_id`),
  KEY `idx_patient_exp_session_device` (`kiosk_device_id`),
  KEY `idx_patient_exp_session_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `patient_experience_form_templates` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `template_key` VARCHAR(120) NOT NULL,
  `title`        VARCHAR(190) NOT NULL,
  `description`  TEXT                  DEFAULT NULL,
  `category`     VARCHAR(80)  NOT NULL DEFAULT 'intake',
  `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
  `created_by`   INT UNSIGNED          DEFAULT NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_patient_exp_template_key` (`template_key`),
  KEY `idx_patient_exp_template_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `patient_experience_form_template_versions` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `template_id`    INT UNSIGNED NOT NULL,
  `version_number` INT UNSIGNED NOT NULL DEFAULT 1,
  `schema_json`    LONGTEXT              DEFAULT NULL,
  `consent_text`   LONGTEXT              DEFAULT NULL,
  `effective_at`   DATETIME              DEFAULT NULL,
  `retired_at`     DATETIME              DEFAULT NULL,
  `created_by`     INT UNSIGNED          DEFAULT NULL,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_patient_exp_template_version` (`template_id`, `version_number`),
  KEY `idx_patient_exp_template_versions_template` (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `patient_experience_packets` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `packet_key`  VARCHAR(120) NOT NULL,
  `title`       VARCHAR(190) NOT NULL,
  `description` TEXT                  DEFAULT NULL,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_by`  INT UNSIGNED          DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_patient_exp_packet_key` (`packet_key`),
  KEY `idx_patient_exp_packet_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `patient_experience_packet_sections` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `packet_id`           INT UNSIGNED NOT NULL,
  `template_version_id` INT UNSIGNED NOT NULL,
  `section_key`         VARCHAR(120) NOT NULL,
  `title`               VARCHAR(190) NOT NULL,
  `sort_order`          INT UNSIGNED NOT NULL DEFAULT 0,
  `is_required`         TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_patient_exp_packet_sections_packet` (`packet_id`),
  KEY `idx_patient_exp_packet_sections_version` (`template_version_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `patient_experience_packet_answers` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `checkin_session_id`  INT UNSIGNED NOT NULL,
  `packet_section_id`   INT UNSIGNED NOT NULL,
  `template_version_id` INT UNSIGNED NOT NULL,
  `field_key`           VARCHAR(190) NOT NULL,
  `answer_json`         LONGTEXT              DEFAULT NULL,
  `answer_label`        TEXT                  DEFAULT NULL,
  `is_sensitive`        TINYINT(1)   NOT NULL DEFAULT 0,
  `answered_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_patient_exp_answers_session` (`checkin_session_id`),
  KEY `idx_patient_exp_answers_section` (`packet_section_id`),
  KEY `idx_patient_exp_answers_field` (`field_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `patient_experience_signatures` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `checkin_session_id`    INT UNSIGNED NOT NULL,
  `packet_section_id`     INT UNSIGNED          DEFAULT NULL,
  `template_version_id`   INT UNSIGNED          DEFAULT NULL,
  `signer_name`           VARCHAR(190) NOT NULL,
  `signer_relationship`   VARCHAR(120) NOT NULL DEFAULT 'self',
  `signature_storage_key` VARCHAR(255)          DEFAULT NULL,
  `signature_hash`        CHAR(64)              DEFAULT NULL,
  `signed_at`             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address`            VARCHAR(80)  NOT NULL DEFAULT '',
  `user_agent`            VARCHAR(255) NOT NULL DEFAULT '',
  `device_label`          VARCHAR(190) NOT NULL DEFAULT '',
  `created_at`            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_patient_exp_signatures_session` (`checkin_session_id`),
  KEY `idx_patient_exp_signatures_section` (`packet_section_id`),
  KEY `idx_patient_exp_signatures_signed` (`signed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `patient_experience_signed_packets` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `checkin_session_id`  INT UNSIGNED NOT NULL,
  `packet_key`          VARCHAR(120) NOT NULL,
  `packet_version`      INT UNSIGNED NOT NULL DEFAULT 1,
  `packet_title`        VARCHAR(190) NOT NULL,
  `patient_name`        VARCHAR(190) NOT NULL DEFAULT '',
  `snapshot_hash`       CHAR(64) NOT NULL,
  `snapshot_json`       LONGTEXT NOT NULL,
  `signature_count`     INT UNSIGNED NOT NULL DEFAULT 0,
  `signed_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME              DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_patient_exp_signed_packet_session` (`checkin_session_id`),
  KEY `idx_patient_exp_signed_packet_signed` (`signed_at`),
  KEY `idx_patient_exp_signed_packet_key` (`packet_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `patient_experience_generated_files` (
  `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `checkin_session_id`   INT UNSIGNED NOT NULL,
  `file_type`            VARCHAR(80)  NOT NULL DEFAULT 'signed_packet_pdf',
  `storage_key`          VARCHAR(255) NOT NULL,
  `original_name`        VARCHAR(255)          DEFAULT NULL,
  `mime_type`            VARCHAR(120) NOT NULL DEFAULT 'application/pdf',
  `file_size`            INT UNSIGNED NOT NULL DEFAULT 0,
  `sha256_hash`          CHAR(64)              DEFAULT NULL,
  `protected_path`       VARCHAR(500) NOT NULL DEFAULT '',
  `generated_by_user_id` INT UNSIGNED          DEFAULT NULL,
  `generated_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_patient_exp_files_session` (`checkin_session_id`),
  KEY `idx_patient_exp_files_type` (`file_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `patient_experience_audit_events` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `checkin_session_id` INT UNSIGNED          DEFAULT NULL,
  `kiosk_device_id`    INT UNSIGNED          DEFAULT NULL,
  `lead_id`            INT UNSIGNED          DEFAULT NULL,
  `user_id`            INT UNSIGNED          DEFAULT NULL,
  `event_key`          VARCHAR(120) NOT NULL,
  `event_label`        VARCHAR(190) NOT NULL DEFAULT '',
  `payload_json`       LONGTEXT              DEFAULT NULL,
  `ip_address`         VARCHAR(80)  NOT NULL DEFAULT '',
  `user_agent`         VARCHAR(255) NOT NULL DEFAULT '',
  `created_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_patient_exp_audit_session` (`checkin_session_id`),
  KEY `idx_patient_exp_audit_event` (`event_key`),
  KEY `idx_patient_exp_audit_created` (`created_at`),
  KEY `idx_patient_exp_audit_lead` (`lead_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Patient Experience Phase 2 progress columns for existing installs.
ALTER TABLE `patient_experience_checkin_sessions`
  ADD COLUMN IF NOT EXISTS `current_step_key` VARCHAR(120) NOT NULL DEFAULT 'welcome' AFTER `staff_notes`;
ALTER TABLE `patient_experience_checkin_sessions`
  ADD COLUMN IF NOT EXISTS `progress_percent` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `current_step_key`;
ALTER TABLE `patient_experience_checkin_sessions`
  ADD COLUMN IF NOT EXISTS `review_status` VARCHAR(40) NOT NULL DEFAULT 'pending' AFTER `progress_percent`;
ALTER TABLE `patient_experience_checkin_sessions`
  ADD COLUMN IF NOT EXISTS `reviewed_at` DATETIME DEFAULT NULL AFTER `review_status`;
ALTER TABLE `patient_experience_checkin_sessions`
  ADD COLUMN IF NOT EXISTS `reviewed_by_user_id` INT UNSIGNED DEFAULT NULL AFTER `reviewed_at`;
ALTER TABLE `patient_experience_kiosk_devices`
  ADD COLUMN IF NOT EXISTS `registered_at` DATETIME DEFAULT NULL AFTER `is_active`;
