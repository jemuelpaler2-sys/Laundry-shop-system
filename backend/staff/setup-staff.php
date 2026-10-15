<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

// Safety: this script only runs from the same computer (XAMPP on localhost)
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($ip, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'This script only runs on localhost.']);
    exit;
}

// username, password, first name, last name, role
$accounts = [
    ['owner',  'Owner@12345', 'Shop',    'Owner', 'owner'],
    ['staff1', 'Staff@12345', 'Laundry', 'Staff', 'staff'],
];

$find   = $pdo->prepare('SELECT id FROM staff WHERE username = ? LIMIT 1');
$insert = $pdo->prepare(
    "INSERT INTO staff (username, password, first_name, last_name, role, status)
     VALUES (?, ?, ?, ?, ?, 'active')"
);
$update = $pdo->prepare(
    "UPDATE staff
     SET password = ?, first_name = ?, last_name = ?, role = ?, status = 'active'
     WHERE id = ?"
);

$results = [];

foreach ($accounts as [$username, $password, $first, $last, $role]) {
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $find->execute([$username]);
    $existing = $find->fetch();

    if ($existing) {
        $update->execute([$hash, $first, $last, $role, $existing['id']]);
        $results[$username] = 'reset';
    } else {
        $insert->execute([$username, $hash, $first, $last, $role]);
        $results[$username] = 'created';
    }
}

echo json_encode([
    'success'  => true,
    'message'  => 'Staff accounts are ready. DELETE this file now.',
    'accounts' => $results,
]);