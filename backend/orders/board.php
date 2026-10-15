<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../staff/auth.php';

requireStaff();

$rules = require __DIR__ . '/../config/ordering.php';

try {
    // All open orders (not completed or cancelled), oldest first
    $stmt = $pdo->query(
        "SELECT o.id, o.order_number, o.service_type, o.weight, o.price, o.order_status,
                o.dropoff_date, o.estimated_pickup_date,
                CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
                c.customer_code,
                (SELECT COALESCE(SUM(p.amount), 0)
                   FROM payments p WHERE p.order_id = o.id) AS amount_paid
         FROM orders o
         JOIN customers c ON c.id = o.customer_id
         WHERE o.order_status NOT IN ('completed', 'cancelled')
         ORDER BY o.dropoff_date ASC, o.id ASC
         LIMIT 300"
    );
    $orders = $stmt->fetchAll();

    foreach ($orders as &$o) {
        $o['id']          = (int)$o['id'];
        $o['weight']      = (float)$o['weight'];
        $o['price']       = (float)$o['price'];
        $o['amount_paid'] = (float)$o['amount_paid'];
        $o['balance']     = max(0, $o['price'] - $o['amount_paid']);
    }
    unset($o);

    echo json_encode([
        'success' => true,
        'orders'  => $orders,
        'limits'  => [
            'min_weight' => (float)$rules['min_weight'],
            'max_weight' => (float)$rules['max_weight'],
        ],
    ]);
} catch (PDOException $e) {
    error_log('Load board failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load the board.']);
}