<?php
// backend/payments/paymongo/status.php
//
// Returns the latest online (PayMongo) payment state for one order, so the
// customer page can show "waiting for confirmation" vs "paid". Staff may check
// any order; a customer only their own.

require_once __DIR__ . '/../../receipts/guard.php';

$viewer = receiptViewer();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$orderNumber = trim((string) ($_GET['order_number'] ?? ''));
if ($orderNumber === '' || mb_strlen($orderNumber) > 20) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid order number.']);
    exit;
}

try {
    $sql = "SELECT op.status, op.reference, op.checkout_url, op.amount, op.payment_method,
                   op.transaction_reference, op.paid_at, op.created_at
            FROM online_payments op
            JOIN orders o ON o.id = op.order_id
            WHERE o.order_number = ?";
    $params = [$orderNumber];

    if (!$viewer['is_staff']) {
        $sql .= ' AND o.customer_id = ?';
        $params[] = $viewer['customer_id'];
    }

    $sql .= ' ORDER BY op.id DESC LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    echo json_encode([
        'success'        => true,
        'online_payment' => $row ?: null,
    ]);
} catch (PDOException $e) {
    error_log('Online payment status failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load the payment status.']);
}
