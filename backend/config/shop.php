<?php
// backend/config/shop.php
//
// Shop information printed on receipts and email footers. Values come from
// backend/.env (see .env.example) so they can differ per installation.

require_once __DIR__ . '/env.php';

return [
    'name'           => env('SHOP_NAME', 'Laundry Shop'),
    'address'        => env('SHOP_ADDRESS', 'Tomas Oppus, Southern Leyte, Philippines'),
    'phone'          => env('SHOP_PHONE', '09703458210'),
    'email'          => env('SHOP_EMAIL', env('MAIL_FROM_EMAIL', '')),
    'currency'       => '₱',
    'receipt_prefix' => env('SHOP_RECEIPT_PREFIX', 'RCPT-'),
];
