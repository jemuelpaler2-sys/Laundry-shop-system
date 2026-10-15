<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../staff/auth.php';

requireOwner();

$start  = $_GET['start'] ?? '';
$end    = $_GET['end'] ?? '';
$search = trim($_GET['search'] ?? '');

$sd = DateTime::createFromFormat('Y-m-d', $start);
$ed = DateTime::createFromFormat('Y-m-d', $end);
if (!$sd || !$ed || $sd > $ed) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid date range.']);
    exit;
}
$endInclusive = $end . ' 23:59:59';

$where  = ['o.dropoff_date BETWEEN ? AND ?'];
$params = [$start . ' 00:00:00', $endInclusive];

if ($search !== '') {
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $where[] = "(CONCAT(c.first_name, ' ', c.last_name) LIKE ? OR c.customer_code LIKE ?)";
    array_push($params, $like, $like);
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

try {
    $stmt = $pdo->prepare(
        "SELECT c.customer_code, CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
                COUNT(o.id) AS order_count,
                COALESCE(SUM(CASE WHEN o.order_status <> 'cancelled' THEN o.price ELSE 0 END), 0) AS total_billed,
                COALESCE((SELECT SUM(p.amount) FROM payments p
                           JOIN orders oo ON oo.id = p.order_id
                          WHERE oo.customer_id = c.id
                            AND oo.dropoff_date BETWEEN ? AND ?), 0) AS total_paid
         FROM orders o
         JOIN customers c ON c.id = o.customer_id
         $whereSql
         GROUP BY c.id, c.customer_code, c.first_name, c.last_name
         ORDER BY total_billed DESC
         LIMIT 100"
    );
    // date range appears twice: once for the subquery, once for the main WHERE
    $stmt->execute(array_merge([$start . ' 00:00:00', $endInclusive], $params));
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $r['order_count']  = (int)$r['order_count'];
        $r['total_billed'] = (float)$r['total_billed'];
        $r['total_paid']   = (float)$r['total_paid'];
    }
    unset($r);

    echo json_encode(['success' => true, 'customers' => $rows]);
} catch (PDOException $e) {
    error_log('Customer transactions report failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load the customer transactions report.']);
}
    