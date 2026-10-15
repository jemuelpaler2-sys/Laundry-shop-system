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

$services = require __DIR__ . '/../config/services.php';
$rules    = require __DIR__ . '/../config/ordering.php';

$minWeight  = (float)$rules['min_weight'];
$maxWeight  = (float)$rules['max_weight'];
$minPrice   = (float)$rules['min_price'];
$maxPrice   = (float)$rules['max_price'];
$fixedService = (string)$rules['default_service'];

// Formats 8 as "8" and 8.5 as "8.5" so the messages read naturally
$fmtWeight = fn($n) => rtrim(rtrim(number_format((float)$n, 2, '.', ''), '0'), '.');

$input      = json_decode(file_get_contents('php://input'), true) ?? [];
$id         = (int)($input['id'] ?? 0);
$isUpdate   = $id > 0;
$customerId = (int)($input['customer_id'] ?? 0);
// The shop only offers one service, so anything sent in is ignored.
$service    = $fixedService;
$weightRaw  = $input['weight'] ?? null;
$pickupDate = trim($input['estimated_pickup_date'] ?? '');
$notes      = trim($input['notes'] ?? '');

// ---------- Validation ----------
$errors = [];

// ----- Weight: required, and never below the shop minimum -----
if ($weightRaw === null || $weightRaw === '') {
    $errors[] = 'Please enter the weight in kg.';
} elseif (!is_numeric($weightRaw)) {
    $errors[] = 'Weight must be a number.';
} elseif ((float)$weightRaw < $minWeight) {
    $errors[] = 'Minimum weight is ' . $fmtWeight($minWeight) . ' kg.';
} elseif ((float)$weightRaw > $maxWeight) {
    $errors[] = 'Weight must be between ' . $fmtWeight($minWeight) . ' and ' . $fmtWeight($maxWeight) . ' kg.';
}
if ($pickupDate !== '') {
    $d = DateTime::createFromFormat('Y-m-d', $pickupDate);
    if (!$d || $d->format('Y-m-d') !== $pickupDate) {
        $errors[] = 'Estimated pickup date is not valid.';
    }
}
if (mb_strlen($notes) > 1000) {
    $errors[] = 'Notes are too long (max 1000 characters).';
}
if (!$isUpdate && $customerId <= 0) {
    $errors[] = 'Please choose a customer.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $errors[0], 'errors' => $errors]);
    exit;
}

$weight = round((float)$weightRaw, 2);

// The price is always worked out from the weight and the service rate,
// and is never allowed to fall below the shop minimum.
$price = max(round($weight * $services[$service], 2), $minPrice);
if ($price > $maxPrice) {
    $price = $maxPrice;
}

$pickupValue = $pickupDate === '' ? null : $pickupDate;
$notesValue  = $notes === '' ? null : $notes;

try {
    if ($isUpdate) {
        // ---------- Update an existing order ----------
        $stmt = $pdo->prepare('SELECT order_number, order_status FROM orders WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $existing = $stmt->fetch();

        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Order not found.']);
            exit;
        }
        if (in_array($existing['order_status'], ['completed', 'cancelled'], true)) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Completed or cancelled orders cannot be edited.']);
            exit;
        }

        $stmt = $pdo->prepare(
            'UPDATE orders
             SET service_type = ?, weight = ?, price = ?, estimated_pickup_date = ?, notes = ?
             WHERE id = ?'
        );
        $stmt->execute([$service, $weight, $price, $pickupValue, $notesValue, $id]);

        echo json_encode([
            'success' => true,
            'message' => 'Order ' . $existing['order_number'] . ' updated.',
        ]);
        exit;
    }

    // ---------- Create a new order ----------
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$customerId]);
    if (!$stmt->fetch()) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Customer not found or inactive.']);
        exit;
    }

    $orderNumber = null;

    // Try a few times in case two staff members save at the same moment
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $next = (int)$pdo->query(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(order_number, 5) AS UNSIGNED)), 1000) + 1 FROM orders"
        )->fetchColumn();
        $candidate = 'ORD-' . $next;

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO orders
                    (order_number, customer_id, service_type, weight, price,
                     order_status, estimated_pickup_date, notes)
                 VALUES (?, ?, ?, ?, ?, 'dropoff', ?, ?)"
            );
            $stmt->execute([$candidate, $customerId, $service, $weight, $price, $pickupValue, $notesValue]);
            $orderNumber = $candidate;
            break;
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e; // a real error, not a duplicate order number
            }
        }
    }

    if ($orderNumber === null) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not create the order. Please try again.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => "Order $orderNumber created."]);
} catch (PDOException $e) {
    error_log('Order save failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save the order. Please try again.']);
}