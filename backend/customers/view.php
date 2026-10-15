<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../staff/auth.php';

requireStaff();

$id = (int)($_GET['id'] ?? 0);

try {
    $stmt = $pdo->prepare(
        'SELECT id, customer_code, first_name, last_name, email, phone, address, status, created_at
         FROM customers WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $customer = $stmt->fetch();

    if (!$customer) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Customer not found.']);
        exit;
    }

    // Totals (cancelled orders are not counted as billed)
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS order_count,
                COALESCE(SUM(CASE WHEN order_status <> 'cancelled' THEN price ELSE 0 END), 0) AS total_billed
         FROM orders WHERE customer_id = ?"
    );
    $stmt->execute([$id]);
    $totals = $stmt->fetch();

    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(p.amount), 0)
         FROM payments p JOIN orders o ON o.id = p.order_id
         WHERE o.customer_id = ?'
    );
    $stmt->execute([$id]);
    $totalPaid = (float)$stmt->fetchColumn();

    // Latest 5 orders
    $stmt = $pdo->prepare(
        'SELECT order_number, service_type, weight, price, order_status, dropoff_date
         FROM orders WHERE customer_id = ?
         ORDER BY dropoff_date DESC, id DESC LIMIT 5'
    );
    $stmt->execute([$id]);
    $recent = $stmt->fetchAll();

    foreach ($recent as &$o) {
        $o['weight'] = (float)$o['weight'];
        $o['price']  = (float)$o['price'];
    }
    unset($o);

    echo json_encode([
        'success'  => true,
        'customer' => $customer,
        'stats'    => [
            'order_count'  => (int)$totals['order_count'],
            'total_billed' => (float)$totals['total_billed'],
            'total_paid'   => $totalPaid,
        ],
        'recent_orders' => $recent,
    ]);
} catch (PDOException $e) {
    error_log('Customer view failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load the customer.']);
}