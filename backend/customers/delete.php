<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../staff/auth.php';

// Only the shop owner may permanently delete a customer
requireOwner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id    = (int)($input['id'] ?? 0);

if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid customer ID.']);
    exit;
}

try {
    // Look the customer up by their unique ID (never by name or row position)
    $stmt = $pdo->prepare(
        'SELECT id, customer_code, first_name, last_name
         FROM customers WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $customer = $stmt->fetch();

    if (!$customer) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Customer not found.']);
        exit;
    }

    // Customers with transaction history must be kept. Orders use a RESTRICT
    // foreign key, and payments/pickup schedules hang off orders, so any order
    // means the record cannot be removed without breaking that history.
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE customer_id = ?');
    $stmt->execute([$id]);
    $orderCount = (int)$stmt->fetchColumn();

    // Loyalty stamps and rewards are part of the customer's history as well.
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM loyalty_stamps WHERE customer_id = ?');
    $stmt->execute([$id]);
    $stampCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM loyalty_rewards WHERE customer_id = ?');
    $stmt->execute([$id]);
    $rewardCount = (int)$stmt->fetchColumn();

    if ($orderCount > 0 || $stampCount > 0 || $rewardCount > 0) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'This customer has existing orders or loyalty records and cannot be '
                       . 'permanently deleted. Their transaction history must be kept. '
                       . 'Please deactivate the customer instead.',
        ]);
        exit;
    }

    $pdo->beginTransaction();

    // Any leftover (unused) loyalty card cascades away with the customer row.
    // No orders exist, so there are no payments, stamps, rewards or pickups
    // that could be orphaned.
    $stmt = $pdo->prepare('DELETE FROM customers WHERE id = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        // Another request deleted it between the lookup and the delete
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Customer not found. It may have already been deleted.']);
        exit;
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Customer ' . $customer['first_name'] . ' ' . $customer['last_name']
                   . ' (' . $customer['customer_code'] . ') has been permanently deleted.',
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Customer delete failed: ' . $e->getMessage());

    // 23000 = integrity constraint violation (e.g. a related record still references it)
    if ($e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'This customer still has related records and cannot be deleted. '
                       . 'Please deactivate the customer instead.',
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not delete the customer. Please try again.']);
    }
}
