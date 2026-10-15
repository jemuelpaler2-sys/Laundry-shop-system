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
        "SELECT order_status, COUNT(*) AS total
         FROM orders
         WHERE dropoff_date BETWEEN ? AND ?
         GROUP BY order_status"
    );
    $stmt->execute([$start . ' 00:00:00', $endInclusive]);
    $rows = $stmt->fetchAll();

    $byStatus = [
        'dropoff' => 0, 'washing' => 0, 'drying' => 0, 'folding' => 0,
        'ready_for_pickup' => 0, 'completed' => 0, 'cancelled' => 0,
    ];
    foreach ($rows as $r) {
        $byStatus[$r['order_status']] = (int)$r['total'];
    }

    $inProgress = $byStatus['dropoff'] + $byStatus['washing']
                + $byStatus['drying'] + $byStatus['folding'];
    $pending    = $inProgress + $byStatus['ready_for_pickup'];
    $total      = array_sum($byStatus);

    echo json_encode([
        'success'   => true,
        'by_status' => $byStatus,
        'totals'    => [
            'total'     => $total,
            'completed' => $byStatus['completed'],
            'pending'   => $pending,
            'cancelled' => $byStatus['cancelled'],
        ],
    ]);
} catch (PDOException $e) {
    error_log('Orders report failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load the orders report.']);
}