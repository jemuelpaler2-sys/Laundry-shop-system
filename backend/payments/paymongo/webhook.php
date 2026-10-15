<?php
// backend/payments/paymongo/webhook.php
//
// Public endpoint that PayMongo calls when a payment succeeds.
//
// Security: the request is only accepted if the Paymongo-Signature header
// matches an HMAC-SHA256 of the RAW request body using the endpoint's signing
// secret. Nothing is parsed or written before that check passes.
//
// Idempotency + automatic receipt live in processPaymongoPaidEvent() /
// sendAutomaticReceipt() (backend/lib/OnlinePayment.php).
//
// NOTE: this file deliberately does NOT include config/cors.php. It is a
// server-to-server callback, not a browser request.

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../lib/Paymongo.php';
require_once __DIR__ . '/../../lib/OnlinePayment.php';

function webhookRespond(int $code, array $body): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($body);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    webhookRespond(405, ['success' => false, 'message' => 'Method not allowed.']);
}

// 1. Raw body first — never parse before verifying.
$rawBody = file_get_contents('php://input');
if ($rawBody === false || $rawBody === '') {
    webhookRespond(400, ['success' => false, 'message' => 'Empty payload.']);
}

// 2. Signature header (Apache exposes it as HTTP_PAYMONGO_SIGNATURE).
$signature = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? null;
if ($signature === null && function_exists('getallheaders')) {
    foreach (getallheaders() as $name => $value) {
        if (strcasecmp($name, 'Paymongo-Signature') === 0) {
            $signature = $value;
            break;
        }
    }
}

$cfg = paymongoConfig();

if ($cfg['webhook_secret'] === '') {
    error_log('PayMongo webhook rejected: PAYMONGO_WEBHOOK_SECRET is not set.');
    webhookRespond(500, ['success' => false, 'message' => 'Webhook is not configured.']);
}

if (!paymongoVerifySignature($rawBody, $signature, $cfg['webhook_secret'], $cfg['mode'], $cfg['timestamp_tolerance'])) {
    // Return 401 so forged/replayed requests are dropped and never processed.
    webhookRespond(401, ['success' => false, 'message' => 'Invalid signature.']);
}

// 3. Parse.
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    webhookRespond(400, ['success' => false, 'message' => 'Invalid JSON.']);
}

$event = paymongoParsePaidEvent($payload);

// 4. Not a paid event we handle: acknowledge so PayMongo stops retrying, but
//    record it for the audit trail (best effort).
if ($event === null) {
    $eventId   = (string) ($payload['data']['id'] ?? '');
    $eventType = (string) ($payload['data']['attributes']['type'] ?? ($payload['data']['type'] ?? 'unhandled'));
    if ($eventId !== '') {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO payment_webhook_events
                    (gateway, event_id, event_type, reference, processing_status, message, payload)
                 VALUES ('paymongo', ?, ?, NULL, 'ignored', 'Event type not handled.', ?)
                 ON DUPLICATE KEY UPDATE id = id"
            );
            $stmt->execute([$eventId, mb_substr($eventType, 0, 80), mb_substr($rawBody, 0, 60000)]);
        } catch (Throwable $e) {
            error_log('Webhook log failed: ' . $e->getMessage());
        }
    }
    webhookRespond(200, ['success' => true, 'status' => 'ignored']);
}

// 5. Process (idempotent). Any hard failure throws -> 500 -> PayMongo retries.
try {
    $result = processPaymongoPaidEvent($pdo, $event, $rawBody);

    // 6. Automatic receipt — only for a freshly recorded payment, after commit.
    if ($result['status'] === 'processed' && !empty($result['payment_id'])) {
        // A mail failure must never change the webhook result; it is logged
        // and picked up by the retry job.
        @sendAutomaticReceipt($pdo, (int) $result['order_id'], (int) $result['payment_id']);
    }

    webhookRespond(200, ['success' => true, 'status' => $result['status']]);
} catch (Throwable $e) {
    error_log('PayMongo webhook processing failed: ' . $e->getMessage());
    webhookRespond(500, ['success' => false, 'message' => 'Processing failed.']);
}
