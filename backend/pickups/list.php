<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../staff/auth.php';

requireStaff();

$slots = require __DIR__ . '/../config/pickup-slots.php';

try {
    // 1. Open orders with no scheduled pickup yet
    $needsScheduling = $pdo->query(
        "SELECT o.id, o.order_number, o.order_status, o.weight, o.estimated_pickup_date,
                c.customer_code, CONCAT(c.first_name, ' ', c.last_name) AS customer_name, c.phone
         FROM orders o
         JOIN customers c ON c.id = o.customer_id
         WHERE o.order_status NOT IN ('completed', 'cancelled')
           AND NOT EXISTS (
               SELECT 1 FROM pickup_schedules ps
               WHERE ps.order_id = o.id AND ps.status = 'scheduled'
           )
         ORDER BY o.dropoff_date ASC, o.id ASC
         LIMIT 200"
    )->fetchAll();

    // 2. Upcoming scheduled pickups, soonest first
    $upcoming = $pdo->query(
        "SELECT ps.id, ps.order_id, ps.scheduled_date, ps.scheduled_time, ps.status,
                o.order_number, o.order_status,
                c.customer_code, CONCAT(c.first_name, ' ', c.last_name) AS customer_name, c.phone
         FROM pickup_schedules ps
         JOIN orders o ON o.id = ps.order_id
         JOIN customers c ON c.id = o.customer_id
         WHERE ps.status = 'scheduled'
         ORDER BY ps.scheduled_date ASC, ps.id ASC
         LIMIT 200"
    )->fetchAll();

    // 3. History (latest 50)
    $history = $pdo->query(
        "SELECT ps.id, ps.scheduled_date, ps.scheduled_time, ps.status,
                o.order_number,
                c.customer_code, CONCAT(c.first_name, ' ', c.last_name) AS customer_name
         FROM pickup_schedules ps
         JOIN orders o ON o.id = ps.order_id
         JOIN customers c ON c.id = o.customer_id
         WHERE ps.status <> 'scheduled'
         ORDER BY ps.scheduled_date DESC, ps.id DESC
         LIMIT 50"
    )->fetchAll();

    foreach ($needsScheduling as &$o) {
        $o['id']     = (int)$o['id'];
        $o['weight'] = (float)$o['weight'];
    }
    unset($o);
    foreach ($upcoming as &$p) {
        $p['id']       = (int)$p['id'];
        $p['order_id'] = (int)$p['order_id'];
    }
    unset($p);

    echo json_encode([
        'success'          => true,
        'needs_scheduling' => $needsScheduling,
        'upcoming'         => $upcoming,
        'history'          => $history,
        'slots'            => $slots,
    ]);
} catch (PDOException $e) {
    error_log('Pickup list failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load the pickup schedule.']);
}