<?php
    require_once __DIR__ . '/../config/cors.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/auth.php';

    requireCustomer();

    // The customer ID always comes from the session, never from the request
    $customerId = (int)$_SESSION['customer_id'];
    $requested  = trim($_GET['order_number'] ?? '');

    try {
        // 1. All orders of this customer (for the dropdown and search)
        $stmt = $pdo->prepare(
            "SELECT order_number, service_type, order_status, dropoff_date
            FROM orders
            WHERE customer_id = ?
            ORDER BY dropoff_date DESC, id DESC"
        );
        $stmt->execute([$customerId]);
        $orders = $stmt->fetchAll();

        if (empty($orders)) {
            echo json_encode(['success' => true, 'orders' => [], 'order' => null]);
            exit;
        }

        // 2. No order requested: pick the newest active order, otherwise the newest order
        if ($requested === '') {
            $requested = $orders[0]['order_number'];
            foreach ($orders as $o) {
                if (!in_array($o['order_status'], ['completed', 'cancelled'], true)) {
                    $requested = $o['order_number'];
                    break;
                }
            }
        }

        if (mb_strlen($requested) > 20) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Order not found.']);
            exit;
        }

        // 3. Details of one order, only if it belongs to this customer
        $stmt = $pdo->prepare(
            "SELECT
                o.order_number, o.service_type, o.weight, o.price, o.order_status,
                o.dropoff_date, o.estimated_pickup_date, o.actual_pickup_date,
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
            WHERE o.customer_id = ? AND o.order_number = ?
            LIMIT 1"
        );
        $stmt->execute([$customerId, $requested]);
        $order = $stmt->fetch();

        if (!$order) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Order not found.']);
            exit;
        }

        $order['weight']      = (float)$order['weight'];
        $order['price']       = (float)$order['price'];
        $order['amount_paid'] = (float)$order['amount_paid'];
        $order['balance']     = max(0, $order['price'] - $order['amount_paid']);

        echo json_encode(['success' => true, 'orders' => $orders, 'order' => $order]);
    } catch (PDOException $e) {
        error_log('Customer order status failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not load your order.']);
    }