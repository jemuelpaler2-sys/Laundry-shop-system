<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../staff/auth.php';
require_once __DIR__ . '/../lib/Mailer.php';
require_once __DIR__ . '/../lib/Loyalty.php';

requireStaff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$services = require __DIR__ . '/../config/services.php';
$rules    = require __DIR__ . '/../config/ordering.php';

$minWeight  = (float)$rules['min_weight'];
$maxWeight  = (float)$rules['max_weight'];
$minPrice   = (float)$rules['min_price'];
$fixedService = (string)$rules['default_service'];

// The order every load follows. A load can only move one step at a time.
$flow = ['dropoff', 'washing', 'drying', 'folding', 'ready_for_pickup', 'completed'];

$labels = [
    'dropoff'          => 'Drop-off',
    'washing'          => 'Washing',
    'drying'           => 'Drying',
    'folding'          => 'Folding',
    'ready_for_pickup' => 'Ready for Pickup',
    'completed'        => 'Completed',
];

$input       = json_decode(file_get_contents('php://input'), true) ?? [];
$id          = (int)($input['id'] ?? 0);
$action      = $input['action'] ?? '';
$weightInput = $input['weight'] ?? null;

if ($id <= 0 || !in_array($action, ['next', 'back'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

// Formats 0.175 as "0.175" and 5.000 as "5"
$fmt = fn($n) => rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.');

try {
    $stmt = $pdo->prepare(
        "SELECT o.id, o.order_number, o.customer_id, o.service_type, o.weight, o.price, o.order_status,
                (SELECT COALESCE(SUM(p.amount), 0)
                   FROM payments p WHERE p.order_id = o.id) AS amount_paid
         FROM orders o
         WHERE o.id = ?
         LIMIT 1"
    );
    $stmt->execute([$id]);
    $order = $stmt->fetch();

    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found.']);
        exit;
    }

    $current = $order['order_status'];
    $index   = array_search($current, $flow, true);

    // Cancelled orders are not in the flow, and completed orders are finished
    if ($index === false || $current === 'completed') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Only open orders can change status.']);
        exit;
    }

    if ($action === 'back') {
        // Only the shop owner can correct a mistake, and only before "Ready for Pickup"
        if (($_SESSION['staff_role'] ?? '') !== 'owner') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only the shop owner can move a load back.']);
            exit;
        }
        // Washing, Drying and Folding can be undone. Drop-off cannot, and
        // nothing can be undone once a load is Ready for Pickup.
        if ($index < 1 || $index > 3) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'This order cannot be moved back from its current status.']);
            exit;
        }
        $target = $flow[$index - 1];
    } else {
        $target = $flow[$index + 1];
    }

    $weight = (float)$order['weight'];
    $price  = (float)$order['price'];

    // ----- Rule: a load is weighed when it leaves Drop-off for Washing -----
    if ($action === 'next' && $target === 'washing') {
        if ($weightInput !== null && $weightInput !== '') {
            if (!is_numeric($weightInput)) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Weight must be a number.']);
                exit;
            }
            $weight = round((float)$weightInput, 2);
            if ($weight < $minWeight || $weight > $maxWeight) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => 'The weight must be between ' . $fmt($minWeight) . ' and ' . $fmt($maxWeight) . ' kg.',
                ]);
                exit;
            }
        } elseif ($weight < $minWeight || $weight > $maxWeight) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => $weight <= 0
                    ? 'Please enter the weight before starting the wash.'
                    : 'The weight must be between ' . $fmt($minWeight) . ' and ' . $fmt($maxWeight) . ' kg.',
            ]);
            exit;
        }
        // Always price the load from the weight and the service rate, and never
        // charge less than the shop minimum.
        $rate = $services[$order['service_type']] ?? $services[$fixedService] ?? 0;
        $price = $rate > 0 ? max(round($weight * $rate, 2), $minPrice) : $minPrice;
    }

    // ----- Rule: an order must be fully paid before it is completed -----
    if ($action === 'next' && $target === 'completed') {
        if ($price <= 0) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Set a price for this order before completing it.']);
            exit;
        }
        if ((float)$order['amount_paid'] + 0.005 < $price) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Record the full payment before completing this order.']);
            exit;
        }
    }

    $pdo->beginTransaction();

    if ($target === 'completed') {
        $stmt = $pdo->prepare(
            "UPDATE orders SET order_status = ?, actual_pickup_date = NOW()
             WHERE id = ? AND order_status = ?"
        );
        $stmt->execute([$target, $id, $current]);
    } elseif ($target === 'washing' && $action === 'next') {
        $stmt = $pdo->prepare(
            "UPDATE orders SET order_status = ?, weight = ?, price = ?
             WHERE id = ? AND order_status = ?"
        );
        $stmt->execute([$target, $weight, $price, $id, $current]);
    } else {
        $stmt = $pdo->prepare(
            "UPDATE orders SET order_status = ? WHERE id = ? AND order_status = ?"
        );
        $stmt->execute([$target, $id, $current]);
    }

    // If someone else already changed this order, nothing was updated
    if ($stmt->rowCount() !== 1) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This order was already updated. The board has been refreshed.']);
        exit;
    }

    // A completed order means the pickup happened
    if ($target === 'completed') {
        $stmt = $pdo->prepare(
            "UPDATE pickup_schedules SET status = 'completed'
             WHERE order_id = ? AND status = 'scheduled'"
        );
        $stmt->execute([$id]);
    }

    // ----- Loyalty card: a load earns one stamp when it is finished -----
    // Only "Ready for Pickup" and "Completed" count. grantLoyaltyStamp()
    // ignores an order that has already given a stamp, so a load that is
    // marked ready and then collected still only counts once.
    $loyaltyNote = '';

    if ($action === 'next' && in_array($target, ['ready_for_pickup', 'completed'], true)) {
        $loyalty = grantLoyaltyStamp($pdo, [
            'id'            => (int)$order['id'],
            'customer_id'   => (int)$order['customer_id'],
            'order_status'  => $target,
        ]);

        if ($loyalty['reward']) {
            $loyaltyNote = $loyalty['reward']['reward_status'] === 'Unlocked'
                ? ' Loyalty reward unlocked (' . $loyalty['reward']['reward_code'] . ')!'
                : '';
        } elseif ($loyalty['reason'] === 'stamped') {
            $progress = loyaltyProgress($pdo, $loyalty['card']);
            $loyaltyNote = ' Loyalty card: ' . $progress['stamps'] . '/'
                         . $progress['stamps_required'] . ' washes.';
        }
    }

    $pdo->commit();

    // Email the customer after the change is safely saved.
    // A failed email never blocks or undoes the status change itself.
    sendOrderStatusEmail($pdo, $id, $target);

    $message = 'Order ' . $order['order_number'] . ' moved to ' . $labels[$target] . '.';
    $message .= $loyaltyNote;

    echo json_encode([
        'success'    => true,
        'message'    => $message,
        'new_status' => $target,
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Status update failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not update the order. Please try again.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Status update failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not update the order. Please try again.']);
}