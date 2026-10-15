<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

requireStaff();

try {
    $c = $pdo->query(
        "SELECT
            (SELECT COUNT(*) FROM customers WHERE status = 'active') AS total_customers,
            (SELECT COUNT(*) FROM orders
              WHERE order_status IN ('dropoff','washing','drying','folding')) AS active_orders,
            (SELECT COUNT(*) FROM orders WHERE order_status = 'ready_for_pickup') AS ready_for_pickup"
    )->fetch();

    $stats = [
        'total_customers'  => (int)$c['total_customers'],
        'active_orders'    => (int)$c['active_orders'],
        'ready_for_pickup' => (int)$c['ready_for_pickup'],
    ];

    $recent = $pdo->query(
        "SELECT o.order_number,
                CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
                o.service_type, o.weight, o.order_status, o.dropoff_date
         FROM orders o
         JOIN customers c ON c.id = o.customer_id
         ORDER BY o.dropoff_date DESC, o.id DESC
         LIMIT 8"
    )->fetchAll();

    foreach ($recent as &$row) {
        $row['weight'] = (float)$row['weight'];
    }
    unset($row);

    echo json_encode([
        'success'       => true,
        'stats'         => $stats,
        'recent_orders' => $recent,
    ]);
} catch (PDOException $e) {
    error_log('Staff dashboard failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load the dashboard.']);
}