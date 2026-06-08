<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/_bootstrap.php';

smile_design_internal_boot('New Smile Case');
redirect(base_url('smile-design/staff-intake'));
