<?php
    require_once __DIR__ . '/../config/cors.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/auth.php';

    requireCustomer();

    // The customer ID always comes from the session, never from the request
    $customerId = (int)$_SESSION['customer_id'];

    try {
        // 1. Summary cards
        $stmt = $pdo->prepare(
            "SELECT
                COUNT(*) AS total_orders,
                COALESCE(SUM(order_status IN ('dropoff','washing','drying','folding')), 0) AS in_progress,
                COALESCE(SUM(order_status = 'ready_for_pickup'), 0) AS ready_for_pickup,
                COALESCE(SUM(order_status = 'completed'), 0) AS completed
            FROM orders
            WHERE customer_id = ?"
        );
        $stmt->execute([$customerId]);
        $s = $stmt->fetch();

        $stats = [
            'total_orders'     => (int)$s['total_orders'],
            'in_progress'      => (int)$s['in_progress'],
            'ready_for_pickup' => (int)$s['ready_for_pickup'],
            'completed'        => (int)$s['completed'],
        ];

        // 2. Current order = newest order that is not completed or cancelled
        $stmt = $pdo->prepare(
            "SELECT
                o.id, o.order_number, o.service_type, o.weight, o.price,
                o.order_status, o.dropoff_date, o.estimated_pickup_date,
                (SELECT COALESCE(SUM(p.amount), 0)
                FROM payments p WHERE p.order_id = o.id) AS amount_paid,
                (SELECT ps.scheduled_date
                FROM pickup_schedules ps
                WHERE ps.order_id = o.id AND ps.status = 'scheduled'
                ORDER BY ps.id DESC LIMIT 1) AS pickup_date,
                (SELECT ps.scheduled_time
                FROM pickup_schedules ps
                WHERE ps.order_id = o.id AND ps.status = 'scheduled'
                ORDER BY ps.id DESC LIMIT 1) AS pickup_time
            FROM orders o
            WHERE o.customer_id = ?
            AND o.order_status NOT IN ('completed', 'cancelled')
            ORDER BY o.created_at DESC, o.id DESC
            LIMIT 1"
        );
        $stmt->execute([$customerId]);
        $current = $stmt->fetch();

        if ($current) {
            $current['id']          = (int)$current['id'];
            $current['weight']      = (float)$current['weight'];
            $current['price']       = (float)$current['price'];
            $current['amount_paid'] = (float)$current['amount_paid'];
            $current['balance']     = max(0, $current['price'] - $current['amount_paid']);
        }

        // 3. Recent orders (latest 5)
        $stmt = $pdo->prepare(
            "SELECT order_number, service_type, weight, order_status, dropoff_date
            FROM orders
            WHERE customer_id = ?
            ORDER BY dropoff_date DESC, id DESC
            LIMIT 5"
        );
        $stmt->execute([$customerId]);
        $recent = $stmt->fetchAll();

        foreach ($recent as &$row) {
            $row['weight'] = (float)$row['weight'];
        }
        unset($row);

        echo json_encode([
            'success'       => true,
            'stats'         => $stats,
            'current_order' => $current ?: null,
            'recent_orders' => $recent,
        ]);
    } catch (PDOException $e) {
        error_log('Customer dashboard failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not load your dashboard.']);
    }