<?php
    require_once __DIR__ . '/config/cors.php';
    require_once __DIR__ . '/config/database.php';

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $inventoryCount = $pdo->query('SELECT COUNT(*) FROM inventory')->fetchColumn();

    echo json_encode([
        'success'         => true,
        'message'         => 'Connected to laundry_shop_db',
        'table_count'     => count($tables),
        'tables'          => $tables,
        'inventory_items' => (int)$inventoryCount,
    ]);