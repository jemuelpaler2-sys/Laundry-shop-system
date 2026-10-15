<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../staff/auth.php';

requireStaff();

$orderId = (int)($_GET['order_id'] ?? 0);

try {
    $stmt = $pdo->prepare(
        "SELECT o.order_number, o.price,
                CONCAT(c.first_name, ' ', c.last_name) AS customer_name
         FROM orders o JOIN customers c ON c.id = o.customer_id
         WHERE o.id = ? LIMIT 1"
    );
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found.']);
        exit;
    }
    $order['price'] = (float)$order['price'];

    $stmt = $pdo->prepare(
        'SELECT amount, payment_method, payment_status, payment_date
         FROM payments WHERE order_id = ?
         ORDER BY payment_date DESC, id DESC'
    );
    $stmt->execute([$orderId]);
    $payments = $stmt->fetchAll();

    $totalPaid = 0.0;
    foreach ($payments as &$p) {
        $p['amount'] = (float)$p['amount'];
        $totalPaid  += $p['amount'];
    }
    unset($p);

    echo json_encode([
        'success'  => true,
        'order'    => $order,
        'payments' => $payments,
        'total_paid' => round($totalPaid, 2),
    ]);
} catch (PDOException $e) {
    error_log('Payment history failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load the payment history.']);
}