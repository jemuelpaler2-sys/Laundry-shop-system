<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

requireCustomer();

// The customer ID always comes from the session, never from the request
$customerId = (int)$_SESSION['customer_id'];

try {
    // 1. Payment summary for each order (cancelled orders are not counted)
    $stmt = $pdo->prepare(
        "SELECT
            o.order_number, o.service_type, o.price, o.order_status, o.dropoff_date,
            (SELECT COALESCE(SUM(p.amount), 0)
               FROM payments p WHERE p.order_id = o.id) AS amount_paid,
            (SELECT p.payment_method
               FROM payments p WHERE p.order_id = o.id
              ORDER BY p.payment_date DESC, p.id DESC LIMIT 1) AS payment_method,
            (SELECT p.payment_date
               FROM payments p WHERE p.order_id = o.id
              ORDER BY p.payment_date DESC, p.id DESC LIMIT 1) AS payment_date
         FROM orders o
         WHERE o.customer_id = ? AND o.order_status <> 'cancelled'
         ORDER BY o.dropoff_date DESC, o.id DESC
         LIMIT 500"
    );
    $stmt->execute([$customerId]);
    $orders = $stmt->fetchAll();

    $totalBilled  = 0.0;
    $totalPaid    = 0.0;
    $totalBalance = 0.0;

    foreach ($orders as &$o) {
        $o['price']       = (float)$o['price'];
        $o['amount_paid'] = (float)$o['amount_paid'];
        $o['balance']     = max(0, $o['price'] - $o['amount_paid']);

        if ($o['price'] <= 0) {
            $o['payment_status'] = 'pending';   // price not set yet
        } elseif ($o['balance'] <= 0) {
            $o['payment_status'] = 'paid';
        } elseif ($o['amount_paid'] > 0) {
            $o['payment_status'] = 'partial';
        } else {
            $o['payment_status'] = 'unpaid';
        }

        $totalBilled  += $o['price'];
        $totalPaid    += $o['amount_paid'];
        $totalBalance += $o['balance'];
    }
    unset($o);

    // 2. Payment history (every recorded payment of this customer)
    $stmt = $pdo->prepare(
        "SELECT o.order_number, p.amount, p.payment_method, p.payment_date
         FROM payments p
         JOIN orders o ON o.id = p.order_id
         WHERE o.customer_id = ?
         ORDER BY p.payment_date DESC, p.id DESC
         LIMIT 200"
    );
    $stmt->execute([$customerId]);
    $history = $stmt->fetchAll();

    foreach ($history as &$h) {
        $h['amount'] = (float)$h['amount'];
    }
    unset($h);

    echo json_encode([
        'success' => true,
        'summary' => [
            'total_amount'      => $totalBilled,
            'amount_paid'       => $totalPaid,
            'remaining_balance' => $totalBalance,
        ],
        'orders'  => $orders,
        'history' => $history,
    ]);
} catch (PDOException $e) {
    error_log('Customer payments failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load your payments.']);
}