<?php
// backend/payments/paymongo/simulate.php
//
// LOCAL DEVELOPMENT SIMULATOR ONLY.
//
// Lets the full "Pay Online" flow be exercised end to end without a PayMongo
// account. It runs the exact same processing path as the real webhook, but the
// "paid" event is generated locally instead of arriving from PayMongo.
//
// Hard-gated: it only runs when PAYMONGO_MODE=test AND PAYMONGO_SIMULATE=true,
// and it still requires a logged-in customer paying their OWN order. Never
// enable PAYMONGO_SIMULATE in production.

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../customer/auth.php';
require_once __DIR__ . '/../../lib/Paymongo.php';
require_once __DIR__ . '/../../lib/OnlinePayment.php';

$cfg = paymongoConfig();

function simRedirect(string $url): void
{
    header('Location: ' . $url, true, 302);
    exit;
}

$base = $cfg['return_base'] . $cfg['success_path'];

if (empty($cfg['simulate'])) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not available.']);
    exit;
}

requireCustomer();
$customerId = (int) $_SESSION['customer_id'];

$reference = trim((string) ($_GET['reference'] ?? ''));
if ($reference === '') {
    simRedirect($base . '?error=' . rawurlencode('Missing reference.'));
}

try {
    $stmt = $pdo->prepare(
        'SELECT op.id, op.order_id, op.reference, op.amount, op.status, o.order_number
         FROM online_payments op
         JOIN orders o ON o.id = op.order_id
         WHERE op.reference = ? AND o.customer_id = ?
         LIMIT 1'
    );
    $stmt->execute([$reference, $customerId]);
    $online = $stmt->fetch();

    if (!$online) {
        simRedirect($base . '?error=' . rawurlencode('Payment session not found.'));
    }

    if ($online['status'] === 'paid') {
        simRedirect($base . '?paid=' . rawurlencode((string) $online['order_number']));
    }

    // Build a synthetic paid event and process it exactly like the webhook.
    $event = [
        'event_id'     => 'evt_sim_' . bin2hex(random_bytes(8)),
        'event_type'   => 'checkout_session.payment.paid',
        'session_id'   => (string) $online['reference'],
        'order_number' => (string) $online['order_number'],
        'payment_id'   => 'pay_sim_' . bin2hex(random_bytes(8)),
        'amount_pesos' => (float) $online['amount'],
        'method_raw'   => 'gcash',
        'method_label' => 'GCash',
        'paid_at'      => date('Y-m-d H:i:s'),
        'livemode'     => false,
    ];

    $result = processPaymongoPaidEvent($pdo, $event, json_encode($event));

    if ($result['status'] === 'processed' && !empty($result['payment_id'])) {
        @sendAutomaticReceipt($pdo, (int) $result['order_id'], (int) $result['payment_id']);
    }

    simRedirect($base . '?paid=' . rawurlencode((string) $online['order_number']) . '&simulated=1');
} catch (Throwable $e) {
    error_log('Simulator failed: ' . $e->getMessage());
    simRedirect($base . '?error=' . rawurlencode('Simulated payment failed.'));
}
