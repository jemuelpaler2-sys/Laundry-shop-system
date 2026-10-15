<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

requireCustomer();

// The customer ID always comes from the session, never from the request
$customerId = (int)$_SESSION['customer_id'];

try {
    // 1. Upcoming pickups (soonest first)
    $stmt = $pdo->prepare(
        "SELECT o.order_number, o.order_status,
                ps.scheduled_date, ps.scheduled_time, ps.status
         FROM pickup_schedules ps
         JOIN orders o ON o.id = ps.order_id
         WHERE o.customer_id = ? AND ps.status = 'scheduled'
         ORDER BY ps.scheduled_date ASC, ps.id ASC"
    );
    $stmt->execute([$customerId]);
    $upcoming = $stmt->fetchAll();

    // 2. Active orders that have no scheduled pickup yet
    $stmt = $pdo->prepare(
        "SELECT o.order_number, o.order_status, o.estimated_pickup_date
         FROM orders o
         WHERE o.customer_id = ?
           AND o.order_status NOT IN ('completed', 'cancelled')
           AND NOT EXISTS (
               SELECT 1 FROM pickup_schedules ps
               WHERE ps.order_id = o.id AND ps.status = 'scheduled'
           )
         ORDER BY o.dropoff_date DESC, o.id DESC"
    );
    $stmt->execute([$customerId]);
    $unscheduled = $stmt->fetchAll();

    // 3. Pickup history (completed, cancelled or missed)
    $stmt = $pdo->prepare(
        "SELECT o.order_number,
                ps.scheduled_date, ps.scheduled_time, ps.status
         FROM pickup_schedules ps
         JOIN orders o ON o.id = ps.order_id
         WHERE o.customer_id = ? AND ps.status <> 'scheduled'
         ORDER BY ps.scheduled_date DESC, ps.id DESC
         LIMIT 100"
    );
    $stmt->execute([$customerId]);
    $history = $stmt->fetchAll();

    echo json_encode([
        'success'     => true,
        'upcoming'    => $upcoming,
        'unscheduled' => $unscheduled,
        'history'     => $history,
    ]);
} catch (PDOException $e) {
    error_log('Customer pickup failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load your pickup schedule.']);
}