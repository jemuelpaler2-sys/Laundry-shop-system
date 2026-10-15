<?php
// backend/payments/paymongo/create-checkout.php
//
// Customer-only. Creates a PayMongo Hosted Checkout Session for the remaining
// balance of one of the customer's own orders and returns the checkout_url.
// No payment is recorded here — only the webhook (after signature verification)
// may ever record a payment.

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../customer/auth.php';
require_once __DIR__ . '/../../lib/Paymongo.php';
require_once __DIR__ . '/../../lib/OnlinePayment.php';

requireCustomer();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed.', 405);
}

$customerId = (int) $_SESSION['customer_id'];

$input       = json_decode(file_get_contents('php://input'), true) ?? [];
$orderNumber = trim((string) ($input['order_number'] ?? ''));

if ($orderNumber === '' || mb_strlen($orderNumber) > 20) {
    sendError('Invalid order number.', 422);
}

try {
    $stmt = $pdo->prepare(
        'SELECT o.id, o.order_number, o.service_type, o.weight, o.price, o.order_status,
                c.id AS customer_id, c.first_name, c.last_name, c.email
         FROM orders o
         JOIN customers c ON c.id = o.customer_id
         WHERE o.order_number = ? AND o.customer_id = ?
         LIMIT 1'
    );
    $stmt->execute([$orderNumber, $customerId]);
    $order = $stmt->fetch();

    if (!$order) {
        sendError('Order not found.', 404);
    }

    if ($order['order_status'] === 'cancelled') {
        sendError('This order was cancelled and can no longer be paid online.', 409);
    }

    $price = (float) $order['price'];
    if ($price <= 0) {
        sendError('This order does not have a price yet. Please contact the shop.', 409);
    }

    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE order_id = ?');
    $stmt->execute([(int) $order['id']]);
    $alreadyPaid = (float) $stmt->fetchColumn();
    $balance = max(0, round($price - $alreadyPaid, 2));

    if ($balance <= 0) {
        sendError('This order is already fully paid.', 409);
    }

    // Reuse a still-pending session for the same amount instead of piling up.
    $stmt = $pdo->prepare(
        "SELECT reference, checkout_url FROM online_payments
         WHERE order_id = ? AND status = 'pending' AND amount = ? AND checkout_url IS NOT NULL
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([(int) $order['id'], $balance]);
    $pending = $stmt->fetch();
    if ($pending) {
        sendSuccess([
            'checkout_url' => $pending['checkout_url'],
            'reference'    => $pending['reference'],
            'amount'       => $balance,
            'reused'       => true,
        ], 'Continue your existing payment.');
    }

    $cfg = paymongoConfig();

    // ---- Local simulator (dev only, test mode + PAYMONGO_SIMULATE=true) ----
    if (!empty($cfg['simulate'])) {
        $reference = 'cs_sim_' . bin2hex(random_bytes(8));
        $url = $cfg['backend_base'] . '/payments/paymongo/simulate.php?reference=' . rawurlencode($reference);

        $ins = $pdo->prepare(
            "INSERT INTO online_payments (order_id, gateway, reference, checkout_url, amount, currency, status, livemode)
             VALUES (?, 'paymongo', ?, ?, ?, 'PHP', 'pending', 0)"
        );
        $ins->execute([(int) $order['id'], $reference, $url, $balance]);

        sendSuccess([
            'checkout_url' => $url,
            'reference'    => $reference,
            'amount'       => $balance,
            'simulate'     => true,
        ], 'Opening the local payment simulator.');
    }

    // ---- Real PayMongo ----
    $result = paymongoCreateCheckoutSession(
        [
            'id'           => (int) $order['id'],
            'order_number' => $order['order_number'],
            'service_type' => $order['service_type'],
            'weight'       => (float) $order['weight'],
        ],
        [
            'id'   => $customerId,
            'name' => trim($order['first_name'] . ' ' . $order['last_name']),
            'email' => $order['email'],
        ],
        $balance,
        (string) $order['order_number']
    );

    if (!$result['ok']) {
        $appConfig  = require __DIR__ . '/../../config/config.php';
        $showDetail = !empty($appConfig['dev_mode']);

        error_log('PayMongo checkout failed for ' . $orderNumber . ': ' . ($result['error'] ?? ''));

        sendError(
            $result['error'] . ($showDetail && $result['http_status'] ? ' (HTTP ' . $result['http_status'] . ')' : ''),
            502
        );
    }

    $ins = $pdo->prepare(
        "INSERT INTO online_payments (order_id, gateway, reference, checkout_url, amount, currency, status, livemode)
         VALUES (?, 'paymongo', ?, ?, ?, 'PHP', 'pending', ?)"
    );
    $ins->execute([
        (int) $order['id'],
        $result['id'],
        $result['checkout_url'],
        $balance,
        $cfg['mode'] === 'live' ? 1 : 0,
    ]);

    sendSuccess([
        'checkout_url' => $result['checkout_url'],
        'reference'    => $result['id'],
        'amount'       => $balance,
    ], 'Redirecting you to the secure payment page.');
} catch (PDOException $e) {
    error_log('create-checkout DB error: ' . $e->getMessage());
    sendError('Could not start the payment. Please try again.', 500);
}
