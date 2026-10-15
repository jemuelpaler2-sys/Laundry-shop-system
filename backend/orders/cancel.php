<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../staff/auth.php';

requireOwner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id    = (int)($input['id'] ?? 0);

try {
    $stmt = $pdo->prepare('SELECT order_number, order_status FROM orders WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $order = $stmt->fetch();

    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found.']);
        exit;
    }
    if (in_array($order['order_status'], ['completed', 'cancelled'], true)) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This order is already completed or cancelled.']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM payments WHERE order_id = ?');
    $stmt->execute([$id]);
    if ((int)$stmt->fetchColumn() > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This order has payments recorded and cannot be cancelled.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE orders SET order_status = 'cancelled' WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true, 'message' => 'Order ' . $order['order_number'] . ' cancelled.']);
} catch (PDOException $e) {
    error_log('Order cancel failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not cancel the order.']);
}