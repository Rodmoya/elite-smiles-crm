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
$result = smile_design_delete_case($caseId, auth_user_id());

flash_set($result['ok'] ? 'success' : 'error', (string)($result['ok'] ? 'Smile case deleted.' : ($result['message'] ?? 'Could not delete smile case.')));
redirect(base_url('smile-design/cases'));
