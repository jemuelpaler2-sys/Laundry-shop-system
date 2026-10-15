<?php
// backend/config/config.php

return [
    'dev_mode' => true, // Set to false to hide detailed errors from users
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