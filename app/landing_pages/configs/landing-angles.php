<?php
declare(strict_types=1);

$angles = [];
$root = __DIR__ . '/angles';

foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $procedureDir) {
    $procedureKey = basename($procedureDir);
    foreach (glob($procedureDir . '/*.php') ?: [] as $file) {
        $angleKey = basename($file, '.php');
        $config = require $file;
        if (is_array($config)) {
            $angles[$procedureKey][$angleKey] = $config;
        }
    }
}

return $angles;
