<?php
// backend/config/paymongo.php
//
// PayMongo settings, read from backend/.env (git-ignored). Nothing here is
// ever exposed to the React app — the secret key and webhook signing secret
// must only live on the server.
//
// Dashboard setup (PayMongo):
//   1. Developers -> API Keys: copy the TEST secret key (sk_test_...) while
//      developing, and the LIVE key (sk_live_...) when you go live.
//   2. Developers -> Webhooks: add an endpoint pointing to
//      .../backend/payments/paymongo/webhook.php and subscribe to
//      checkout_session.payment.paid. Copy that endpoint's signing secret
//      (whsk_...) into PAYMONGO_WEBHOOK_SECRET.

require_once __DIR__ . '/env.php';

$mode = strtolower(trim((string) env('PAYMONGO_MODE', 'test')));
if ($mode !== 'live') {
    $mode = 'test';
}

// Payment methods offered on the hosted checkout page.
$methodsRaw = (string) env('PAYMONGO_PAYMENT_METHODS', 'card,gcash,paymaya,qrph');
$methods = array_values(array_filter(array_map('trim', explode(',', $methodsRaw))));

// Where PayMongo sends the customer back to after paying / cancelling. These
// must be absolute URLs to the React app.
$defaultReturn = env('PAYMONGO_RETURN_BASE', 'http://localhost:5173');

return [
    'mode'                => $mode,
    'secret_key'          => (string) env('PAYMONGO_SECRET_KEY', ''),
    'public_key'          => (string) env('PAYMONGO_PUBLIC_KEY', ''),
    'webhook_secret'      => (string) env('PAYMONGO_WEBHOOK_SECRET', ''),

    'api_base'            => 'https://api.paymongo.com',
    'checkout_endpoint'   => '/v2/checkout_sessions',

    // Local-only simulator so the whole flow can be exercised without real
    // PayMongo keys. Force-disabled unless we are explicitly in test mode.
    'simulate'            => $mode === 'test'
        && filter_var(env('PAYMONGO_SIMULATE', 'false'), FILTER_VALIDATE_BOOLEAN),

    // Seconds of clock skew allowed when checking the webhook timestamp.
    'timestamp_tolerance' => (int) env('PAYMONGO_TIMESTAMP_TOLERANCE', 300),

    'payment_methods'     => $methods !== [] ? $methods : ['card', 'gcash', 'paymaya', 'qrph'],

    'return_base'         => rtrim((string) $defaultReturn, '/'),
    'success_path'        => '/customer/payments',
    'cancel_path'         => '/customer/payments',

    // Used only by the local simulator to build its own redirect URL.
    'backend_base'        => rtrim((string) env('PAYMONGO_BACKEND_BASE', 'http://localhost/laundry-shop-system/backend'), '/'),
];
