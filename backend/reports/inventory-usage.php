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
        "SELECT i.item_name, i.unit,
                SUM(t.quantity) AS total_used,
                SUM(CASE WHEN t.reference LIKE '%(auto)%' THEN t.quantity ELSE 0 END) AS auto_used,
                SUM(CASE WHEN t.reference NOT LIKE '%(auto)%' THEN t.quantity ELSE 0 END) AS manual_used
         FROM inventory_transactions t
         JOIN inventory i ON i.id = t.inventory_id
         WHERE t.transaction_type = 'stock_deducted'
           AND t.created_at BETWEEN ? AND ?
         GROUP BY i.id, i.item_name, i.unit
         ORDER BY total_used DESC"
    );
    $stmt->execute([$start . ' 00:00:00', $endInclusive]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $r['total_used']  = (float)$r['total_used'];
        $r['auto_used']   = (float)$r['auto_used'];
        $r['manual_used'] = (float)$r['manual_used'];
    }
    unset($r);

    echo json_encode(['success' => true, 'items' => $rows]);
} catch (PDOException $e) {
    error_log('Inventory usage report failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load the inventory usage report.']);
}