<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../staff/auth.php';

requireStaff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input     = json_decode(file_get_contents('php://input'), true) ?? [];
$id        = (int)($input['inventory_id'] ?? 0);
$type      = $input['type'] ?? '';
$qtyRaw    = $input['quantity'] ?? 0;
$reference = trim($input['reference'] ?? '');

if ($id <= 0 || !in_array($type, ['add', 'use'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

// Only the shop owner can add stock. Laundry staff can only record usage.
if ($type === 'add' && ($_SESSION['staff_role'] ?? '') !== 'owner') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only the shop owner can add stock.']);
    exit;
}

if (!is_numeric($qtyRaw) || $qtyRaw <= 0 || $qtyRaw > 1000000) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Quantity must be a number greater than 0.']);
    exit;
}
if (mb_strlen($reference) > 100) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'The note is too long (max 100 characters).']);
    exit;
}

$qty = round((float)$qtyRaw, 3);
if ($qty <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Quantity is too small.']);
    exit;
}
if ($reference === '') {
    $reference = $type === 'add' ? 'Stock added' : 'Manual usage';
}

try {
    $pdo->beginTransaction();

    // Lock the row so two people cannot change the same item at the same moment
    $stmt = $pdo->prepare('SELECT item_name, quantity, unit FROM inventory WHERE id = ? FOR UPDATE');
    $stmt->execute([$id]);
    $item = $stmt->fetch();

    if (!$item) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Item not found.']);
        exit;
    }

    $current = (float)$item['quantity'];

    if ($type === 'use' && $qty > $current) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Not enough stock. Only ' . rtrim(rtrim(number_format($current, 3, '.', ''), '0'), '.')
                         . ' ' . $item['unit'] . ' left.',
        ]);
        exit;
    }

    $newQty = $type === 'add' ? $current + $qty : $current - $qty;

    $stmt = $pdo->prepare('UPDATE inventory SET quantity = ? WHERE id = ?');
    $stmt->execute([round($newQty, 3), $id]);

    // Every change creates a transaction record
    $stmt = $pdo->prepare(
        'INSERT INTO inventory_transactions (inventory_id, transaction_type, quantity, reference)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$id, $type === 'add' ? 'stock_added' : 'stock_deducted', $qty, $reference]);

    $pdo->commit();

    $fmt = fn($n) => rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.');

    echo json_encode([
        'success' => true,
        'message' => ($type === 'add' ? 'Added ' : 'Recorded usage of ')
                     . $fmt($qty) . ' ' . $item['unit'] . ' of ' . $item['item_name']
                     . '. Now ' . $fmt($newQty) . ' ' . $item['unit'] . ' in stock.',
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Stock change failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not update the stock. Please try again.']);
}