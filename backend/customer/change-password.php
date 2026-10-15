<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

requireCustomer();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// The customer ID always comes from the session, never from the request
$customerId = (int)$_SESSION['customer_id'];

$input            = json_decode(file_get_contents('php://input'), true) ?? [];
$current_password = $input['current_password'] ?? '';
$new_password     = $input['new_password'] ?? '';
$confirm_password = $input['confirm_password'] ?? '';

// ---------- Validation ----------
if ($current_password === '' || $new_password === '' || $confirm_password === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please fill in all password fields.']);
    exit;
}
if (strlen($new_password) < 8) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters.']);
    exit;
}
if ($new_password !== $confirm_password) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'New passwords do not match.']);
    exit;
}
if ($new_password === $current_password) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'New password must be different from the current one.']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT password FROM customers WHERE id = ? LIMIT 1');
    $stmt->execute([$customerId]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($current_password, $row['password'])) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Your current password is incorrect.']);
        exit;
    }

    $update = $pdo->prepare('UPDATE customers SET password = ? WHERE id = ?');
    $update->execute([password_hash($new_password, PASSWORD_DEFAULT), $customerId]);

    // New session ID after a password change (security best practice)
    session_regenerate_id(true);

    echo json_encode(['success' => true, 'message' => 'Your password was changed.']);
} catch (PDOException $e) {
    error_log('Password change failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not change your password. Please try again.']);
}