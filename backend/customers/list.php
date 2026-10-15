<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../staff/auth.php';

requireStaff();

$search  = trim($_GET['search'] ?? '');
$status  = $_GET['status'] ?? 'all';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

$where  = [];
$params = [];

if ($search !== '') {
    // Escape the LIKE wildcards typed by the user
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $where[] = "(c.first_name LIKE ? OR c.last_name LIKE ?
                 OR CONCAT(c.first_name, ' ', c.last_name) LIKE ?
                 OR c.email LIKE ? OR c.phone LIKE ? OR c.customer_code LIKE ?)";
    array_push($params, $like, $like, $like, $like, $like, $like);
}

if (in_array($status, ['active', 'inactive'], true)) {
    $where[]  = 'c.status = ?';
    $params[] = $status;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    // Total number of matching customers
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM customers c $whereSql");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    // One page of customers
    $sql = "SELECT c.id, c.customer_code, c.first_name, c.last_name, c.email,
                   c.phone, c.address, c.status, c.created_at,
                   (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS order_count
            FROM customers c
            $whereSql
            ORDER BY c.created_at DESC, c.id DESC
            LIMIT ? OFFSET ?";

    $stmt = $pdo->prepare($sql);
    $i = 1;
    foreach ($params as $p) {
        $stmt->bindValue($i++, $p);
    }
    $stmt->bindValue($i++, $perPage, PDO::PARAM_INT);
    $stmt->bindValue($i, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $customers = $stmt->fetchAll();

    foreach ($customers as &$c) {
        $c['id']          = (int)$c['id'];
        $c['order_count'] = (int)$c['order_count'];
    }
    unset($c);

    echo json_encode([
        'success'     => true,
        'customers'   => $customers,
        'page'        => $page,
        'per_page'    => $perPage,
        'total'       => $total,
        'total_pages' => max(1, (int)ceil($total / $perPage)),
    ]);
} catch (PDOException $e) {
    error_log('Customer list failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load customers.']);
}