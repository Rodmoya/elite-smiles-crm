<?php
declare(strict_types=1);

$cities = [];
$cityDir = __DIR__ . '/cities';

foreach (glob($cityDir . '/*.php') ?: [] as $file) {
    $key = basename($file, '.php');
    $config = require $file;
    if (is_array($config)) {
        $cities[$key] = $config;
    }
}

return $cities;
