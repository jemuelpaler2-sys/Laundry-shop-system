<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';
require_once __DIR__ . '/auth.php';

requireCustomer();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed.', 405);
}

// The customer ID always comes from the session, never from the request
$customerId = (int)$_SESSION['customer_id'];

$input      = json_decode(file_get_contents('php://input'), true) ?? [];
$first_name = trim($input['first_name'] ?? '');
$last_name  = trim($input['last_name'] ?? '');
$email      = strtolower(trim($input['email'] ?? ''));
$phone      = trim($input['phone'] ?? '');
$address    = trim($input['address'] ?? '');

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

if (!empty($errors)) {
    sendError($errors[0], 422, ['errors' => $errors]);
}

try {
    // Email must not belong to another customer
    $check = $pdo->prepare('SELECT id FROM customers WHERE email = ? AND id <> ? LIMIT 1');
    $check->execute([$email, $customerId]);
    if ($check->fetch()) {
        sendError('This email is already used by another account.', 409);
    }

    $update = $pdo->prepare(
        'UPDATE customers
         SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?
         WHERE id = ?'
    );
    $update->execute([$first_name, $last_name, $email, $phone, $address, $customerId]);

    $stmt = $pdo->prepare(
        'SELECT id, customer_code, first_name, last_name, email, phone, address
         FROM customers WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$customerId]);

    sendSuccess([
        'customer' => $stmt->fetch(),
    ], 'Your profile was updated.');
} catch (PDOException $e) {
    error_log('Profile update failed: ' . $e->getMessage());

    if ($e->getCode() === '23000') {
        sendError('This email is already used by another account.', 409);
    } else {
        sendError('Could not update your profile. Please try again.', 500);
    }
}