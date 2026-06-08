<?php
declare(strict_types=1);

/**
 * Elite Smiles Marketing CRM
 * File: /index.php
 *
 * Main entry point.
 * Routes guest users to login and authenticated users to dashboard.
 */

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/core/db.php';
require_once __DIR__ . '/app/core/auth.php';

/*
|--------------------------------------------------------------------------
| Optional basic DB health check
|--------------------------------------------------------------------------
| Not blocking for guests, but useful for future diagnostics.
*/
$dbOnline = db_test_connection();

/*
|--------------------------------------------------------------------------
| Simple route gate
|--------------------------------------------------------------------------
*/
if (auth_check()) {
    header('Location: ' . base_url('dashboard.php'));
    exit;
}

header('Location: ' . base_url('login.php'));
exit;