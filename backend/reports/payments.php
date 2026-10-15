<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../staff/auth.php';

requireOwner();

$start = $_GET['start'] ?? '';
$end   = $_GET['end'] ?? '';

$sd = DateTime::createFromFormat('Y-m-d', $start);
$ed = DateTime::createFromFormat('Y-m-d', $end);
if (!$sd || !$ed || $sd > $ed) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid date range.']);
    exit;
}
$endInclusive = $end . ' 23:59:59';

try {
    $stmt = $pdo->prepare(
        "SELECT p.payment_date, p.amount, p.payment_method,
                o.order_number, c.customer_code,
                CONCAT(c.first_name, ' ', c.last_name) AS customer_name
         FROM payments p
         JOIN orders o ON o.id = p.order_id
         JOIN customers c ON c.id = o.customer_id
         WHERE p.payment_date BETWEEN ? AND ?
         ORDER BY p.payment_date DESC
         LIMIT 300"
    );
    $stmt->execute([$start . ' 00:00:00', $endInclusive]);
    $payments = $stmt->fetchAll();

    $byMethod = ['Cash' => 0.0, 'GCash' => 0.0, 'Other' => 0.0];
    $totalAmount = 0.0;
    foreach ($payments as &$p) {
        $p['amount'] = (float)$p['amount'];
        $byMethod[$p['payment_method']] = ($byMethod[$p['payment_method']] ?? 0) + $p['amount'];
        $totalAmount += $p['amount'];
    }
    unset($p);

    echo json_encode([
        'success'   => true,
        'payments'  => $payments,
        'by_method' => $byMethod,
        'total'     => round($totalAmount, 2),
    ]);
} catch (PDOException $e) {
    error_log('Payment history report failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load the payment history report.']);
}