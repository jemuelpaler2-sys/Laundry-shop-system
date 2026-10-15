<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../staff/auth.php';

requireOwner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input        = json_decode(file_get_contents('php://input'), true) ?? [];
$id           = (int)($input['id'] ?? 0);
$isUpdate     = $id > 0;
$name         = trim($input['item_name'] ?? '');
$category     = trim($input['category'] ?? '');
$unit         = trim($input['unit'] ?? '');
$reorderRaw   = $input['reorder_level'] ?? 0;
$costRaw      = $input['unit_cost'] ?? 0;
$quantityRaw  = $input['quantity'] ?? 0;   // only used when creating an item

// ---------- Validation ----------
$errors = [];

if ($name === '' || mb_strlen($name) > 100) {
    $errors[] = 'Item name is required (max 100 characters).';
}
if ($category === '' || mb_strlen($category) > 50) {
    $errors[] = 'Category is required (max 50 characters).';
}
if ($unit === '' || mb_strlen($unit) > 20) {
    $errors[] = 'Unit is required (max 20 characters).';
}
if (!is_numeric($reorderRaw) || $reorderRaw < 0 || $reorderRaw > 1000000) {
    $errors[] = 'Reorder level must be a number of 0 or more.';
}
if (!is_numeric($costRaw) || $costRaw < 0 || $costRaw > 1000000) {
    $errors[] = 'Unit cost must be an amount of 0 or more.';
}
if (!$isUpdate && (!is_numeric($quantityRaw) || $quantityRaw < 0 || $quantityRaw > 1000000)) {
    $errors[] = 'Starting quantity must be a number of 0 or more.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $errors[0], 'errors' => $errors]);
    exit;
}

$reorder  = round((float)$reorderRaw, 2);
$cost     = round((float)$costRaw, 2);
$quantity = round((float)$quantityRaw, 3);

try {
    // Item names must be unique
    $stmt = $pdo->prepare('SELECT id FROM inventory WHERE item_name = ? AND id <> ? LIMIT 1');
    $stmt->execute([$name, $id]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'An item with this name already exists.']);
        exit;
    }

    if ($isUpdate) {
        $stmt = $pdo->prepare('SELECT id FROM inventory WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Item not found.']);
            exit;
        }

        // The quantity is NOT edited here. It only changes through stock.php.
        $stmt = $pdo->prepare(
            'UPDATE inventory
             SET item_name = ?, category = ?, unit = ?, reorder_level = ?, unit_cost = ?
             WHERE id = ?'
        );
        $stmt->execute([$name, $category, $unit, $reorder, $cost, $id]);

        echo json_encode(['success' => true, 'message' => "$name updated."]);
        exit;
    }

    // ---------- Create ----------
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO inventory (item_name, category, quantity, unit, reorder_level, unit_cost)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$name, $category, $quantity, $unit, $reorder, $cost]);
    $newId = (int)$pdo->lastInsertId();

    // Starting stock is recorded as a transaction too
    if ($quantity > 0) {
        $stmt = $pdo->prepare(
            "INSERT INTO inventory_transactions (inventory_id, transaction_type, quantity, reference)
             VALUES (?, 'stock_added', ?, 'Initial stock')"
        );
        $stmt->execute([$newId, $quantity]);
    }

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => "$name added to the inventory."]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Inventory save failed: ' . $e->getMessage());

    if ($e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'An item with this name already exists.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not save the item. Please try again.']);
    }
}