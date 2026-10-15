<?php
// backend/lib/Paymongo.php
//
// Thin PayMongo client. Only the pieces this shop needs:
//   - create a Hosted Checkout Session and get its checkout_url
//   - verify the Paymongo-Signature header on incoming webhooks
//   - turn a paid webhook payload into a small, safe array
//
// No secret ever leaves the server. Amounts in PayMongo are in centavos
// (integers); everything in this shop is in pesos, so we convert at the edges.

require_once __DIR__ . '/../config/paymongo.php';

/** PayMongo checkout session id prefix. */
const PAYMONGO_SESSION_PREFIX = 'cs_';

/** Config accessor (kept as a function so tests can call it directly). */
function paymongoConfig(): array
{
    return require __DIR__ . '/../config/paymongo.php';
}

/** Pesos -> centavos (integer). ₱160.00 -> 16000. */
function paymongoToCentavos(float $pesos): int
{
    return (int) round($pesos * 100);
}

/** Centavos -> pesos. 16000 -> 160.0. */
function paymongoToPesos(int $centavos): float
{
    return round($centavos / 100, 2);
}

/**
 * Maps a PayMongo payment source type to the friendly label stored on the
 * payments row. Unknown types fall back to "Online".
 */
function paymongoMethodLabel(string $sourceType): string
{
    $map = [
        'card'      => 'Card',
        'gcash'     => 'GCash',
        'paymaya'   => 'Maya',
        'maya'      => 'Maya',
        'grab_pay'  => 'GrabPay',
        'qrph'      => 'QR Ph',
        'dob'       => 'Online Banking',
        'billease'  => 'BillEase',
        'atome'     => 'Atome',
    ];

    $key = strtolower(trim($sourceType));
    return $map[$key] ?? 'Online';
}

/**
 * Creates a Hosted Checkout Session.
 *
 * @param array $order     Row with id, order_number, service_type, weight
 * @param array $customer  Row/array with name and email
 * @param float $amountPesos Amount to charge (usually the remaining balance)
 * @param string $referenceNumber Value we want echoed back in the webhook
 * @return array{ok:bool,id:?string,checkout_url:?string,error:?string,http_status:int}
 */
function paymongoCreateCheckoutSession(array $order, array $customer, float $amountPesos, string $referenceNumber): array
{
    $cfg = paymongoConfig();

    if ($cfg['secret_key'] === '') {
        return ['ok' => false, 'id' => null, 'checkout_url' => null, 'http_status' => 0,
                'error' => 'PayMongo is not configured. Set PAYMONGO_SECRET_KEY in backend/.env.'];
    }

    $weight = (float)($order['weight'] ?? 0);
    $label  = 'Laundry order ' . $order['order_number']
        . ' — ' . $order['service_type']
        . ($weight > 0 ? ' (' . rtrim(rtrim(number_format($weight, 2, '.', ''), '0'), '.') . ' kg)' : '');

    $body = [
        'data' => [
            'attributes' => [
                'line_items' => [[
                    'name'     => $label,
                    'amount'   => paymongoToCentavos($amountPesos),
                    'currency' => 'PHP',
                    'quantity' => 1,
                ]],
                'payment_method_types' => $cfg['payment_methods'],
                // We send our own branded receipt by email, so tell PayMongo not to.
                'send_email_receipt'   => false,
                'show_description'     => true,
                'show_line_items'      => true,
                'description'          => 'Payment for laundry order ' . $order['order_number'],
                'reference_number'     => $referenceNumber,
                'metadata' => [
                    'order_number' => (string) $order['order_number'],
                    'order_id'     => (string) $order['id'],
                    'customer_id'  => (string) ($customer['id'] ?? ''),
                ],
                'success_url' => $cfg['return_base'] . $cfg['success_path']
                    . '?paid=' . rawurlencode((string) $order['order_number']),
                'cancel_url'  => $cfg['return_base'] . $cfg['cancel_path']
                    . '?cancelled=' . rawurlencode((string) $order['order_number']),
            ],
        ],
    ];

    $url = $cfg['api_base'] . $cfg['checkout_endpoint'];
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($cfg['secret_key'] . ':'),
        ],
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log('PayMongo checkout request failed: ' . $curlErr);
        return ['ok' => false, 'id' => null, 'checkout_url' => null, 'http_status' => $httpCode,
                'error' => 'Could not reach PayMongo. Please try again.'];
    }

    $json = json_decode($response, true);

    if ($httpCode < 200 || $httpCode >= 300 || !is_array($json)) {
        // PayMongo returns errors as {errors:[{detail:...}]}. Log the detail
        // server-side but show the customer a short, safe message.
        $detail = $json['errors'][0]['detail'] ?? ('HTTP ' . $httpCode);
        error_log('PayMongo checkout error: ' . $detail);
        return ['ok' => false, 'id' => null, 'checkout_url' => null, 'http_status' => $httpCode,
                'error' => 'PayMongo could not start the payment. Please try again.'];
    }

    $id  = $json['data']['id'] ?? null;
    $url2 = $json['data']['attributes']['checkout_url'] ?? null;

    if (!$id || !$url2) {
        error_log('PayMongo checkout response missing id/checkout_url.');
        return ['ok' => false, 'id' => null, 'checkout_url' => null, 'http_status' => $httpCode,
                'error' => 'PayMongo returned an unexpected response.'];
    }

    return ['ok' => true, 'id' => (string) $id, 'checkout_url' => (string) $url2,
            'http_status' => $httpCode, 'error' => null];
}

/**
 * Verifies the Paymongo-Signature header against the raw request body.
 * Format: t=<ts>,te=<test sig>,li=<live sig>. Signature = HMAC-SHA256(secret, "t.body").
 * Uses te in test mode and li in live mode, with a timing-safe compare.
 */
function paymongoVerifySignature(string $rawBody, ?string $header, string $secret, string $mode, int $tolerance = 300): bool
{
    if ($secret === '' || $header === null || $header === '') {
        return false;
    }

    $timestamp = null;
    $te = '';
    $li = '';

    foreach (explode(',', $header) as $part) {
        $pair = explode('=', trim($part), 2);
        if (count($pair) !== 2) {
            continue;
        }
        [$k, $v] = $pair;
        if ($k === 't')  { $timestamp = $v; }
        if ($k === 'te') { $te = $v; }
        if ($k === 'li') { $li = $v; }
    }

    if ($timestamp === null) {
        return false;
    }

    // Optional replay guard: reject old/tampered timestamps.
    if ($tolerance > 0 && abs(time() - (int) $timestamp) > $tolerance) {
        error_log('PayMongo webhook timestamp outside tolerance.');
        return false;
    }

    $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
    $provided  = $mode === 'live' ? $li : $te;

    if ($provided === '') {
        return false;
    }

    return hash_equals($expected, $provided);
}

/**
 * Pulls the paid-payment details out of a webhook payload. Returns null when
 * the event is not a paid event we handle, or has no usable payment.
 *
 * @return array{
 *   event_id:string,event_type:string,session_id:?string,order_number:?string,
 *   payment_id:string,amount_pesos:float,method_raw:string,method_label:string,
 *   paid_at:?string,livemode:bool,billing_name:?string,billing_email:?string
 * }|null
 */
function paymongoParsePaidEvent(array $payload): ?array
{
    // Standard envelope: data.attributes.type + data.attributes.data.
    // Some deliveries wrap it as {event_type:"send.webhook", data:{type:...}}.
    $attrs = $payload['data']['attributes'] ?? null;

    if (!is_array($attrs)) {
        // Fallback: {event_type, data:{type, data}} shape.
        if (isset($payload['data']['type'])) {
            $attrs = [
                'type'     => $payload['data']['type'],
                'livemode' => $payload['data']['livemode'] ?? null,
                'data'     => $payload['data']['data'] ?? null,
            ];
        } else {
            return null;
        }
    }

    $eventType = (string) ($attrs['type'] ?? '');
    $handled = [
        'checkout_session.payment.paid',
        'payment.paid',
        'link.payment.paid',
    ];
    if (!in_array($eventType, $handled, true)) {
        return null;
    }

    $eventId = (string) ($payload['data']['id'] ?? '');
    $session = $attrs['data'] ?? null;
    if (!is_array($session)) {
        return null;
    }

    $sessionId     = $session['id'] ?? null;
    $sessionAttrs  = $session['attributes'] ?? [];
    $orderNumber   = $sessionAttrs['reference_number']
        ?? ($sessionAttrs['metadata']['order_number'] ?? null);

    // Find the one paid payment in the payments array.
    $paid = null;
    foreach (($sessionAttrs['payments'] ?? []) as $p) {
        if (($p['attributes']['status'] ?? '') === 'paid') {
            $paid = $p;
            break;
        }
    }

    // A bare payment.paid event puts the payment at the top level.
    if ($paid === null && ($session['type'] ?? '') === 'payment') {
        $paid = $session;
    }

    if ($paid === null) {
        return null;
    }

    $paidAttrs   = $paid['attributes'] ?? [];
    $sourceType  = (string) ($paidAttrs['source']['type'] ?? '');
    $amountCents = (int) ($paidAttrs['amount'] ?? 0);

    // PayMongo reports paid_at either as a Unix timestamp or an ISO-8601 string
    // depending on the event, so accept both and normalize to local time.
    $paidAtRaw = $paidAttrs['paid_at'] ?? null;
    $paidAt    = null;
    if (is_numeric($paidAtRaw)) {
        $paidAt = date('Y-m-d H:i:s', (int) $paidAtRaw);
    } elseif (is_string($paidAtRaw) && $paidAtRaw !== '') {
        $ts = strtotime($paidAtRaw);
        $paidAt = $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    return [
        'event_id'      => $eventId,
        'event_type'    => $eventType,
        'session_id'    => $sessionId ? (string) $sessionId : null,
        'order_number'  => $orderNumber !== null ? (string) $orderNumber : null,
        'payment_id'    => (string) ($paid['id'] ?? ''),
        'amount_pesos'  => paymongoToPesos($amountCents),
        'method_raw'    => $sourceType,
        'method_label'  => paymongoMethodLabel($sourceType),
        'paid_at'       => $paidAt,
        'livemode'      => (bool) ($attrs['livemode'] ?? false),
        'billing_name'  => $paidAttrs['billing']['name'] ?? null,
        'billing_email' => isset($paidAttrs['billing']['email'])
            ? strtolower(trim((string) $paidAttrs['billing']['email']))
            : null,
    ];
}
