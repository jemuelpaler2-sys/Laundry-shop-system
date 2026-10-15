<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../staff/auth.php';

requireStaff();

$services = require __DIR__ . '/../config/services.php';
$rules    = require __DIR__ . '/../config/ordering.php';

$search  = trim($_GET['search'] ?? '');
$status  = $_GET['status'] ?? 'all';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

$allowedStatuses = ['dropoff', 'washing', 'drying', 'folding',
                    'ready_for_pickup', 'completed', 'cancelled'];

$where  = [];
$params = [];

if ($search !== '') {
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $where[] = "(o.order_number LIKE ? OR c.customer_code LIKE ?
                 OR CONCAT(c.first_name, ' ', c.last_name) LIKE ?)";
    array_push($params, $like, $like, $like);
}

if ($status === 'in_progress') {
    $where[] = "o.order_status IN ('dropoff','washing','drying','folding')";
} elseif (in_array($status, $allowedStatuses, true)) {
    $where[]  = 'o.order_status = ?';
    $params[] = $status;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM orders o JOIN customers c ON c.id = o.customer_id $whereSql"
    );
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $sql = "SELECT o.id, o.order_number, o.service_type, o.weight, o.price, o.order_status,
                   o.dropoff_date, o.estimated_pickup_date, o.notes,
                   c.customer_code,
                   CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
                   (SELECT COALESCE(SUM(p.amount), 0)
                      FROM payments p WHERE p.order_id = o.id) AS amount_paid
            FROM orders o
            JOIN customers c ON c.id = o.customer_id
            $whereSql
            ORDER BY o.dropoff_date DESC, o.id DESC
            LIMIT ? OFFSET ?";

    $stmt = $pdo->prepare($sql);
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
        $o['weight']      = (float)$o['weight'];
        $o['price']       = (float)$o['price'];
        $o['amount_paid'] = (float)$o['amount_paid'];
    }
    unset($o);

    echo json_encode([
        'success'     => true,
        'orders'      => $orders,
        'services'    => $services,
        'limits'      => [
            'min_weight'      => (float)$rules['min_weight'],
            'max_weight'      => (float)$rules['max_weight'],
            'min_price'       => (float)$rules['min_price'],
            'max_price'       => (float)$rules['max_price'],
            'default_service' => (string)$rules['default_service'],
        ],
        'page'        => $page,
        'total'       => $total,
        'total_pages' => max(1, (int)ceil($total / $perPage)),
    ]);
} catch (PDOException $e) {
    error_log('Order list failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load orders.']);
}