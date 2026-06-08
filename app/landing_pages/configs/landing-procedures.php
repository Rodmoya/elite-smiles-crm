<?php
declare(strict_types=1);

$procedures = [];
$procedureDir = __DIR__ . '/procedures';

foreach (glob($procedureDir . '/*.php') ?: [] as $file) {
    $key = basename($file, '.php');
    $config = require $file;
    if (is_array($config)) {
        $procedures[$key] = $config;
    }
}

return $procedures;
