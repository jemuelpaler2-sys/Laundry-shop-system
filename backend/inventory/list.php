<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../staff/auth.php';

requireStaff();

try {
    $items = $pdo->query(
        "SELECT id, item_name, category, quantity, unit, reorder_level, unit_cost, updated_at
         FROM inventory
         ORDER BY item_name ASC
         LIMIT 500"
    )->fetchAll();

    $lowCount   = 0;
    $stockValue = 0.0;
    $categories = [];

    foreach ($items as &$item) {
        $item['id']            = (int)$item['id'];
        $item['quantity']      = (float)$item['quantity'];
        $item['reorder_level'] = (float)$item['reorder_level'];
        $item['unit_cost']     = (float)$item['unit_cost'];
        // Low stock rule from your spec: quantity <= reorder level
        $item['is_low']        = $item['quantity'] <= $item['reorder_level'];

        if ($item['is_low']) {
            $lowCount++;
        }
        $stockValue += $item['quantity'] * $item['unit_cost'];
        $categories[$item['category']] = true;
    }
    unset($item);

    $categoryList = array_keys($categories);
    sort($categoryList);

    echo json_encode([
        'success'    => true,
        'items'      => $items,
        'categories' => $categoryList,
        'stats'      => [
            'total_items' => count($items),
            'low_stock'   => $lowCount,
            'stock_value' => round($stockValue, 2),
        ],
    ]);
} catch (PDOException $e) {
    error_log('Inventory list failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load the inventory.']);
}