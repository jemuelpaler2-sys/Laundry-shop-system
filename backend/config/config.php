<?php
// backend/config/config.php

require_once __DIR__ . '/env.php';

// Keep PHP's clock on the shop's local timezone so that timestamps PHP writes
// (e.g. online payment paid_at) match the ones MySQL writes with NOW(). The
// MySQL server here runs on the system clock, so a mismatch would shift times.
$timezone = env('APP_TIMEZONE', 'Asia/Manila');
date_default_timezone_set($timezone);

return [
    'dev_mode' => true, // Set to false to hide detailed errors from users
    'timezone' => $timezone,
    'db' => [
        'host' => 'localhost',
        'name' => 'laundry_shop_db',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name' => 'Laundry Shop System',
        'version' => '1.0.0',
    ]
];
?>