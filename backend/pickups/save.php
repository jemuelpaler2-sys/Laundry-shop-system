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

$slots = require __DIR__ . '/../config/pickup-slots.php';

$input    = json_decode(file_get_contents('php://input'), true) ?? [];
$id       = (int)($input['id'] ?? 0);      // pickup_schedules.id, when rescheduling
$orderId  = (int)($input['order_id'] ?? 0); // orders.id, when scheduling for the first time
$date     = trim($input['scheduled_date'] ?? '');
$time     = trim($input['scheduled_time'] ?? '');
$isReschedule = $id > 0;

// ---------- Validation ----------
$errors = [];

$d = DateTime::createFromFormat('Y-m-d', $date);
$today = new DateTime('today');
if (!$d || $d->format('Y-m-d') !== $date) {
    $errors[] = 'Please choose a valid pickup date.';
} elseif ($d < $today) {
    $errors[] = 'Pickup date cannot be in the past.';
}
if (!in_array($time, $slots, true)) {
    $errors[] = 'Please choose a valid time slot.';
}
if (!$isReschedule && $orderId <= 0) {
    $errors[] = 'Please choose an order.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $errors[0], 'errors' => $errors]);
    exit;
}

try {
    if ($isReschedule) {
        $stmt = $pdo->prepare(
            "SELECT ps.id, o.order_number
             FROM pickup_schedules ps
             JOIN orders o ON o.id = ps.order_id
             WHERE ps.id = ? AND ps.status = 'scheduled'
             LIMIT 1"
        );
        $stmt->execute([$id]);
        $existing = $stmt->fetch();

        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Pickup not found.']);
            exit;
        }

        $stmt = $pdo->prepare(
            'UPDATE pickup_schedules SET scheduled_date = ?, scheduled_time = ? WHERE id = ?'
        );
        $stmt->execute([$date, $time, $id]);

        echo json_encode([
            'success' => true,
            'message' => 'Pickup for order ' . $existing['order_number'] . ' rescheduled.',
        ]);
        exit;
    }

    // ---------- Schedule for the first time ----------
    $stmt = $pdo->prepare(
        "SELECT order_number, order_status FROM orders WHERE id = ? LIMIT 1"
    );
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found.']);
        exit;
    }
    if (in_array($order['order_status'], ['completed', 'cancelled'], true)) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This order is already completed or cancelled.']);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT id FROM pickup_schedules WHERE order_id = ? AND status = 'scheduled' LIMIT 1"
    );
    $stmt->execute([$orderId]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This order already has a scheduled pickup. Reschedule it instead.']);
        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO pickup_schedules (order_id, scheduled_date, scheduled_time, status)
         VALUES (?, ?, ?, 'scheduled')"
    );
    $stmt->execute([$orderId, $date, $time]);

    echo json_encode([
        'success' => true,
        'message' => 'Pickup scheduled for order ' . $order['order_number'] . '.',
    ]);
} catch (PDOException $e) {
    error_log('Pickup save failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save the pickup. Please try again.']);
}   