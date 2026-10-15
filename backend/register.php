<?php
    require_once __DIR__ . '/../config/cors.php';
    require_once __DIR__ . '/../config/database.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $first_name       = trim($input['first_name'] ?? '');
    $last_name        = trim($input['last_name'] ?? '');
    $email            = strtolower(trim($input['email'] ?? ''));
    $phone            = trim($input['phone'] ?? '');
    $address          = trim($input['address'] ?? '');
    $password         = $input['password'] ?? '';
    $confirm_password = $input['confirm_password'] ?? '';

    // ---------- Validation ----------
    $errors = [];

    if ($first_name === '' || mb_strlen($first_name) > 50) {
        $errors[] = 'First name is required (max 50 characters).';
    }
    if ($last_name === '' || mb_strlen($last_name) > 50) {
        $errors[] = 'Last name is required (max 50 characters).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
        $errors[] = 'Please enter a valid phone number.';
    }
    if ($address === '' || mb_strlen($address) > 255) {
        $errors[] = 'Address is required (max 255 characters).';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => $errors[0], 'errors' => $errors]);
        exit;
    }

    // ---------- Check duplicate email ----------
    $check = $pdo->prepare('SELECT id FROM customers WHERE email = ? LIMIT 1');
    $check->execute([$email]);
    if ($check->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This email is already registered.']);
        exit;
    }

    // ---------- Save customer ----------
    try {
        $pdo->beginTransaction();

        // Temporary unique code first; we replace it once we know the new id
        $insert = $pdo->prepare(
            "INSERT INTO customers
                (customer_code, first_name, last_name, email, phone, address, password, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active')"
        );
        $insert->execute([
            'TEMP-' . bin2hex(random_bytes(6)),
            $first_name,
            $last_name,
            $email,
            $phone,
            $address,
            password_hash($password, PASSWORD_DEFAULT),
        ]);

        $id            = (int)$pdo->lastInsertId();
        $customer_code = 'CUST-' . (1000 + $id);

        $update = $pdo->prepare('UPDATE customers SET customer_code = ? WHERE id = ?');
        $update->execute([$customer_code, $id]);

        $pdo->commit();

        echo json_encode([
            'success'       => true,
            'message'       => 'Registration successful! You can now log in.',
            'customer_code' => $customer_code,
        ]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Customer registration failed: ' . $e->getMessage());

        // 23000 = duplicate key (e.g. someone registered the same email a split second earlier)
        if ($e->getCode() === '23000') {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'This email is already registered.']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
        }
    }