<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

requireOwner();

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? 'all';

$where  = [];
$params = [];

if ($search !== '') {
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $where[] = "(username LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?)";
    array_push($params, $like, $like);
}
if (in_array($status, ['active', 'inactive'], true)) {
    $where[]  = 'status = ?';
    $params[] = $status;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $stmt = $pdo->prepare(
        "SELECT id, username, first_name, last_name, role, status, created_at
         FROM staff $whereSql
         ORDER BY created_at ASC, id ASC
         LIMIT 200"
    );
    $stmt->execute($params);
    $staffList = $stmt->fetchAll();

    foreach ($staffList as &$s) {
        $s['id'] = (int)$s['id'];
    }
    unset($s);

    echo json_encode(['success' => true, 'staff' => $staffList]);
} catch (PDOException $e) {
    error_log('Staff list failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load staff accounts.']);
}