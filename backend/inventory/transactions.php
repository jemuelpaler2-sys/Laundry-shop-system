<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../staff/auth.php';

requireStaff();

$id = (int)($_GET['inventory_id'] ?? 0);

try {
    $stmt = $pdo->prepare('SELECT id, item_name, quantity, unit FROM inventory WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $item = $stmt->fetch();

    if (!$item) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Item not found.']);
        exit;
    }
    $item['quantity'] = (float)$item['quantity'];

    $stmt = $pdo->prepare(
        'SELECT transaction_type, quantity, reference, created_at
         FROM inventory_transactions
         WHERE inventory_id = ?
         ORDER BY created_at DESC, id DESC
         LIMIT 30'
    );
    $stmt->execute([$id]);
    $transactions = $stmt->fetchAll();

    foreach ($transactions as &$t) {
        $t['quantity'] = (float)$t['quantity'];
    }
    unset($t);

    echo json_encode(['success' => true, 'item' => $item, 'transactions' => $transactions]);
} catch (PDOException $e) {
    error_log('Inventory history failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load the history.']);
}