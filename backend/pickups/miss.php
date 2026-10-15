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

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id    = (int)($input['id'] ?? 0);

try {
    $stmt = $pdo->prepare(
        "SELECT ps.id, o.order_number
         FROM pickup_schedules ps
         JOIN orders o ON o.id = ps.order_id
         WHERE ps.id = ? AND ps.status = 'scheduled'
         LIMIT 1"
    );
    $stmt->execute([$id]);
    $pickup = $stmt->fetch();

    if (!$pickup) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pickup not found.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE pickup_schedules SET status = 'missed' WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode([
        'success' => true,
        'message' => 'Pickup for order ' . $pickup['order_number'] . ' marked as missed.',
    ]);
} catch (PDOException $e) {
    error_log('Pickup miss failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not update the pickup.']);
}