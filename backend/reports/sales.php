<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../staff/auth.php';

requireOwner();

$start = $_GET['start'] ?? '';
$end   = $_GET['end'] ?? '';
$group = $_GET['group'] ?? 'daily'; // daily, weekly, monthly

$sd = DateTime::createFromFormat('Y-m-d', $start);
$ed = DateTime::createFromFormat('Y-m-d', $end);
if (!$sd || !$ed || $sd > $ed || !in_array($group, ['daily', 'weekly', 'monthly'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid date range.']);
    exit;
}
$endInclusive = $end . ' 23:59:59';

$groupExpr = [
    'daily'   => "DATE(payment_date)",
    'weekly'  => "DATE(DATE_SUB(payment_date, INTERVAL WEEKDAY(payment_date) DAY))",
    'monthly' => "DATE_FORMAT(payment_date, '%Y-%m-01')",
][$group];

try {
    // Revenue series (money actually collected, grouped by the chosen period)
    $stmt = $pdo->prepare(
        "SELECT $groupExpr AS bucket, SUM(amount) AS total
         FROM payments
         WHERE payment_date BETWEEN ? AND ?
         GROUP BY bucket
         ORDER BY bucket ASC"
    );
    $stmt->execute([$start . ' 00:00:00', $endInclusive]);
    $series = $stmt->fetchAll();

    foreach ($series as &$row) {
        $row['total'] = (float)$row['total'];
    }
    unset($row);

    // Totals for the whole range
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_date BETWEEN ? AND ?"
    );
    $stmt->execute([$start . ' 00:00:00', $endInclusive]);
    $totalRevenue = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM orders
         WHERE dropoff_date BETWEEN ? AND ? AND order_status <> 'cancelled'"
    );
    $stmt->execute([$start . ' 00:00:00', $endInclusive]);
    $totalOrders = (int)$stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'series'  => $series,
        'totals'  => ['revenue' => $totalRevenue, 'orders' => $totalOrders],
    ]);
} catch (PDOException $e) {
    error_log('Sales report failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load the sales report.']);
}