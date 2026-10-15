<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

requireCustomer();

// The customer ID always comes from the session, never from the request
$customerId = (int)$_SESSION['customer_id'];

try {
    $stmt = $pdo->prepare(
        "SELECT order_number, service_type, weight, price, order_status, dropoff_date
         FROM orders
         WHERE customer_id = ?
         ORDER BY dropoff_date DESC, id DESC
         LIMIT 500"
    );
    $stmt->execute([$customerId]);
    $orders = $stmt->fetchAll();

    foreach ($orders as &$order) {
        $order['weight'] = (float)$order['weight'];
        $order['price']  = (float)$order['price'];
    }
    unset($order);

    echo json_encode(['success' => true, 'orders' => $orders]);
} catch (PDOException $e) {
    error_log('Customer orders failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load your orders.']);
}