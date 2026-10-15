<?php
    require_once __DIR__ . '/../config/cors.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/auth.php';

    requireCustomer();

    $stmt = $pdo->prepare(
        "SELECT id, customer_code, first_name, last_name, email, phone, address
        FROM customers WHERE id = ? AND status = 'active' LIMIT 1"
    );
    $stmt->execute([$_SESSION['customer_id']]);
    $customer = $stmt->fetch();

    if (!$customer) {
        unset($_SESSION['customer_id'], $_SESSION['customer_logged_in']);
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Account not found or inactive.']);
        exit;
    }

    echo json_encode(['success' => true, 'customer' => $customer]);