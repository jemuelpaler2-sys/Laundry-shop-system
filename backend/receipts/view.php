<?php
// backend/receipts/view.php
//
// Returns one receipt as JSON for a specific order. Staff may view any order;
// a customer may only view an order that belongs to them.

require_once __DIR__ . '/guard.php';

$viewer = receiptViewer();

$orderNumber = trim($_GET['order_number'] ?? '');
if ($orderNumber === '' || mb_strlen($orderNumber) > 20) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid order number.']);
    exit;
}

try {
    // A customer is scoped to their own orders; staff pass null for full access.
    $scope = $viewer['is_staff'] ? null : $viewer['customer_id'];

    $receipt = getReceipt($pdo, $orderNumber, $scope);

    if (!$receipt) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Receipt not found for this order.']);
        exit;
    }

    echo json_encode([
        'success'   => true,
        'receipt'   => $receipt,
        // The frontend uses this to show or hide the Email Receipt action.
        'can_email' => $viewer['is_staff'],
    ]);
} catch (PDOException $e) {
    error_log('Receipt view failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load the receipt.']);
}
