<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../staff/auth.php';

requireOwner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input      = json_decode(file_get_contents('php://input'), true) ?? [];
$id         = (int)($input['id'] ?? 0);
$isUpdate   = $id > 0;
$first_name = trim($input['first_name'] ?? '');
$last_name  = trim($input['last_name'] ?? '');
$email      = strtolower(trim($input['email'] ?? ''));
$phone      = trim($input['phone'] ?? '');
$address    = trim($input['address'] ?? '');
$password   = $input['password'] ?? '';

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
if (!$isUpdate && strlen($password) < 8) {
    $errors[] = 'Temporary password must be at least 8 characters.';
}
if ($isUpdate && $password !== '' && strlen($password) < 8) {
    $errors[] = 'New password must be at least 8 characters.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $errors[0], 'errors' => $errors]);
    exit;
}

try {
    if ($isUpdate) {
        $stmt = $pdo->prepare('SELECT id FROM customers WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Customer not found.']);
            exit;
        }
    }

    // Email must be unique (ignore this customer's own row)
    $stmt = $pdo->prepare('SELECT id FROM customers WHERE email = ? AND id <> ? LIMIT 1');
    $stmt->execute([$email, $id]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This email is already used by another customer.']);
        exit;
    }

    if ($isUpdate) {
        if ($password !== '') {
            $stmt = $pdo->prepare(
                'UPDATE customers
                 SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, password = ?
                 WHERE id = ?'
            );
            $stmt->execute([$first_name, $last_name, $email, $phone, $address,
                            password_hash($password, PASSWORD_DEFAULT), $id]);
        } else {
            $stmt = $pdo->prepare(
                'UPDATE customers
                 SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?
                 WHERE id = ?'
            );
            $stmt->execute([$first_name, $last_name, $email, $phone, $address, $id]);
        }
        $message = 'Customer updated.';
    } else {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "INSERT INTO customers
                (customer_code, first_name, last_name, email, phone, address, password, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'active')"
        );
        $stmt->execute([
            'TEMP-' . bin2hex(random_bytes(6)),
            $first_name, $last_name, $email, $phone, $address,
            password_hash($password, PASSWORD_DEFAULT),
        ]);

        $id   = (int)$pdo->lastInsertId();
        $code = 'CUST-' . (1000 + $id);

        $stmt = $pdo->prepare('UPDATE customers SET customer_code = ? WHERE id = ?');
        $stmt->execute([$code, $id]);

        $pdo->commit();
        $message = "Customer added. Customer ID: $code";
    }

    echo json_encode(['success' => true, 'message' => $message]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Customer save failed: ' . $e->getMessage());

    if ($e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This email is already used by another customer.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not save the customer. Please try again.']);
    }
}