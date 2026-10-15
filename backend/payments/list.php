<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../staff/auth.php';

requireStaff();

$search  = trim($_GET['search'] ?? '');
$status  = $_GET['status'] ?? 'all'; // all, unpaid, partial, paid
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

$where  = ["o.order_status <> 'cancelled'"];
$params = [];

if ($search !== '') {
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $where[] = "(o.order_number LIKE ? OR c.customer_code LIKE ?
                 OR CONCAT(c.first_name, ' ', c.last_name) LIKE ?)";
    array_push($params, $like, $like, $like);
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

// Payment status is computed from price vs. amount paid, so it's filtered with HAVING
$having = '';
if (in_array($status, ['unpaid', 'partial', 'paid'], true)) {
    $having = "HAVING payment_status = '" . $status . "'";
}

try {
    // Totals across every non-cancelled order (not just this page)
    $totals = $pdo->query(
        "SELECT
            COALESCE(SUM(o.price), 0) AS total_billed,
            COALESCE((SELECT SUM(p.amount) FROM payments p
                       JOIN orders oo ON oo.id = p.order_id
                      WHERE oo.order_status <> 'cancelled'), 0) AS total_collected
         FROM orders o WHERE o.order_status <> 'cancelled'"
    )->fetch();

    $totalBilled  = (float)$totals['total_billed'];
    $totalPaid    = (float)$totals['total_collected'];
    $outstanding  = max(0, $totalBilled - $totalPaid);

    $sql = "SELECT o.id, o.order_number, o.price, o.order_status, o.dropoff_date,
                   c.customer_code, CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
                   COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.order_id = o.id), 0) AS amount_paid,
                   CASE
                       WHEN o.price <= 0 THEN 'pending'
                       WHEN COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.order_id = o.id), 0) >= o.price THEN 'paid'
                       WHEN COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.order_id = o.id), 0) > 0 THEN 'partial'
                       ELSE 'unpaid'
                   END AS payment_status
            FROM orders o
            JOIN customers c ON c.id = o.customer_id
            $whereSql
            $having
            ORDER BY o.dropoff_date DESC, o.id DESC";

    // Count matching rows first (needed because of the HAVING filter on a computed column)
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM ($sql) AS filtered");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare($sql . ' LIMIT ? OFFSET ?');
    $i = 1;
    foreach ($params as $p) {
        $stmt->bindValue($i++, $p);
    }
    $stmt->bindValue($i++, $perPage, PDO::PARAM_INT);
    $stmt->bindValue($i, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll();

    foreach ($orders as &$o) {
        $o['id']          = (int)$o['id'];
        $o['price']       = (float)$o['price'];
        $o['amount_paid'] = (float)$o['amount_paid'];
        $o['balance']     = max(0, $o['price'] - $o['amount_paid']);
    }
    unset($o);

    echo json_encode([
        'success'     => true,
        'orders'      => $orders,
        'total'       => $total,
        'total_pages' => max(1, (int)ceil($total / $perPage)),
        'summary'     => [
            'total_billed' => $totalBilled,
            'total_paid'   => $totalPaid,
            'outstanding'  => $outstanding,
        ],
    ]);
} catch (PDOException $e) {
    error_log('Payment list failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load payments.']);
}