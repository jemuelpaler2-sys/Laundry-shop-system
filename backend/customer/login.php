<?php
    require_once __DIR__ . '/../config/cors.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../config/response.php';
    require_once __DIR__ . '/auth.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Method not allowed.', 405);
    }

    $input      = json_decode(file_get_contents('php://input'), true) ?? [];
    $identifier = trim($input['identifier'] ?? '');
    $password   = $input['password'] ?? '';

    if ($identifier === '' || $password === '') {
        sendError('Email or Customer ID and password are required.', 400);
    }

    // The identifier can be an email or a customer code such as CUST-1001
    $stmt = $pdo->prepare(
        'SELECT id, customer_code, first_name, last_name, email, password, status
        FROM customers
        WHERE email = ? OR customer_code = ?
        LIMIT 1'
    );
    $stmt->execute([strtolower($identifier), strtoupper($identifier)]);
    $customer = $stmt->fetch();

    // Same message for wrong email/ID or wrong password
    if (!$customer || !password_verify($password, $customer['password'])) {
        sendError('Invalid email/Customer ID or password.', 401);
    }

    if ($customer['status'] !== 'active') {
        sendError('This account is inactive. Please contact the shop.', 403);
    }

    session_regenerate_id(true);

    // Make sure this browser is not also logged in as staff
    unset($_SESSION['staff_id'], $_SESSION['staff_role'], $_SESSION['staff_logged_in']);

    $_SESSION['customer_id']        = (int)$customer['id'];
    $_SESSION['customer_logged_in'] = true;

    sendSuccess([
        'customer' => [
            'id'            => (int)$customer['id'],
            'customer_code' => $customer['customer_code'],
            'first_name'    => $customer['first_name'],
            'last_name'     => $customer['last_name'],
            'email'         => $customer['email'],
        ],
    ], 'Login successful.');