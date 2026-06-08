<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../smile_design/smile_design_service.php';

require_auth();
require_csrf();
smile_design_ensure_schema();

$caseId = (int)post('case_id', 0);
flash_set('error', 'AI-generated 45 reference angles are turned off. Please upload real left 45, right 45, or smile close-up photos when you have them.');

redirect(base_url('smile-design/cases/' . $caseId . '#source'));
