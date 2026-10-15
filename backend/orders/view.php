<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../staff/auth.php';

requireStaff();

$orderNumber = trim($_GET['order_number'] ?? '');

try {
    $stmt = $pdo->prepare(
        "SELECT o.order_number, o.service_type, o.weight, o.price, o.order_status,
                o.dropoff_date, o.estimated_pickup_date, o.actual_pickup_date, o.notes,
                c.customer_code, c.phone,
                CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
                (SELECT COALESCE(SUM(p.amount), 0)
                   FROM payments p WHERE p.order_id = o.id) AS amount_paid,
                (SELECT ps.scheduled_date FROM pickup_schedules ps
                  WHERE ps.order_id = o.id AND ps.status = 'scheduled'
                  ORDER BY ps.id DESC LIMIT 1) AS pickup_date,
                (SELECT ps.scheduled_time FROM pickup_schedules ps
                  WHERE ps.order_id = o.id AND ps.status = 'scheduled'
                  ORDER BY ps.id DESC LIMIT 1) AS pickup_time
         FROM orders o
         JOIN customers c ON c.id = o.customer_id
         WHERE o.order_number = ?
         LIMIT 1"
    );
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch();

    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found.']);
        exit;
    }

    $order['weight']      = (float)$order['weight'];
    $order['price']       = (float)$order['price'];
    $order['amount_paid'] = (float)$order['amount_paid'];
    $order['balance']     = max(0, $order['price'] - $order['amount_paid']);

    echo json_encode(['success' => true, 'order' => $order]);
} catch (PDOException $e) {
    error_log('Order view failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load the order.']);
}