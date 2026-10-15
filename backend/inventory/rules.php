<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../staff/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    requireOwner();
} else {
    requireStaff();
}

try {
    // ---------- Read the rules ----------
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $rows = $pdo->query(
            "SELECT i.id AS inventory_id, i.item_name, i.unit,
                    r.usage_type, r.quantity_used
             FROM inventory i
             LEFT JOIN supply_usage_rules r ON r.inventory_id = i.id
             ORDER BY i.item_name ASC"
        )->fetchAll();

        foreach ($rows as &$r) {
            $r['inventory_id'] = (int)$r['inventory_id'];
            $r['usage_type']   = $r['usage_type'] ?? 'none';
            $r['quantity_used'] = $r['quantity_used'] === null ? 0 : (float)$r['quantity_used'];
        }
        unset($r);

        echo json_encode(['success' => true, 'rules' => $rows]);
        exit;
    }

    // ---------- Save the rules (owner only) ----------
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $rules = $input['rules'] ?? [];

    if (!is_array($rules)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }

    // Check every rule first, save afterwards
    $clean = [];
    foreach ($rules as $rule) {
        $inventoryId = (int)($rule['inventory_id'] ?? 0);
        $type        = $rule['usage_type'] ?? '';
        $qtyRaw      = $rule['quantity_used'] ?? 0;

        if ($inventoryId <= 0 || !in_array($type, ['none', 'per_kg', 'per_order'], true)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Invalid rule.']);
            exit;
        }
        if ($type !== 'none' && (!is_numeric($qtyRaw) || $qtyRaw <= 0 || $qtyRaw > 1000)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Each amount must be a number greater than 0.']);
            exit;
        }

        $clean[] = [$inventoryId, $type, round((float)$qtyRaw, 4)];
    }

    $pdo->beginTransaction();

    $delete = $pdo->prepare('DELETE FROM supply_usage_rules WHERE inventory_id = ?');
    $upsert = $pdo->prepare(
        'INSERT INTO supply_usage_rules (inventory_id, usage_type, quantity_used)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE
            usage_type = VALUES(usage_type),
            quantity_used = VALUES(quantity_used)'
    );

    foreach ($clean as [$inventoryId, $type, $qty]) {
        if ($type === 'none') {
            $delete->execute([$inventoryId]);
        } else {
            $upsert->execute([$inventoryId, $type, $qty]);
        }
    }

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Deduction rules saved.']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Usage rules failed: ' . $e->getMessage());

    if ($e->getCode() === '23000') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'One of the items no longer exists. Refresh the page.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not save the rules. Please try again.']);
    }
}