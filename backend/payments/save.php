<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../staff/auth.php';

requireStaff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input      = json_decode(file_get_contents('php://input'), true) ?? [];
$orderId    = (int)($input['order_id'] ?? 0);
$amountRaw  = $input['amount'] ?? 0;
$method     = $input['payment_method'] ?? '';

if ($orderId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid order.']);
    exit;
}
if (!in_array($method, ['Cash', 'GCash', 'Other'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please choose a valid payment method.']);
    exit;
}
if (!is_numeric($amountRaw) || $amountRaw <= 0 || $amountRaw > 1000000) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Amount must be a number greater than 0.']);
    exit;
}

$amount = round((float)$amountRaw, 2);

try {
    $pdo->beginTransaction();

    // Lock the order so two staff members can't both overpay it at the same moment
    $stmt = $pdo->prepare(
        "SELECT order_number, price, order_status,
                (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE order_id = orders.id) AS amount_paid
         FROM orders WHERE id = ? FOR UPDATE"
    );
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found.']);
        exit;
    }
    if ($order['order_status'] === 'cancelled') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This order was cancelled and cannot receive payments.']);
        exit;
    }

    $price   = (float)$order['price'];
    $paid    = (float)$order['amount_paid'];
    $balance = max(0, $price - $paid);

    if ($price <= 0) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This order does not have a price set yet. Weigh the load first.']);
        exit;
    }
    if ($amount > $balance + 0.005) {
        $pdo->rollBack();
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'This payment is more than what is owed (₱' . number_format($balance, 2) . ').',
        ]);
        exit;
    }

    $newPaid    = round($paid + $amount, 2);
    $newStatus  = $newPaid + 0.005 >= $price ? 'paid' : ($newPaid > 0 ? 'partial' : 'unpaid');

    $stmt = $pdo->prepare(
        'INSERT INTO payments (order_id, amount, payment_method, payment_status, payment_date)
         VALUES (?, ?, ?, ?, NOW())'
    );
    $stmt->execute([$orderId, $amount, $method, $newStatus]);

    $pdo->commit();

    $remaining = max(0, $price - $newPaid);
    $message = 'Payment of ₱' . number_format($amount, 2) . ' recorded for order ' . $order['order_number'] . '.';
    $message .= $remaining > 0.005
        ? ' Remaining balance: ₱' . number_format($remaining, 2) . '.'
        : ' This order is now fully paid.';

    echo json_encode(['success' => true, 'message' => $message]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Payment save failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not record the payment. Please try again.']);
}