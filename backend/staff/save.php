<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

requireOwner();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input      = json_decode(file_get_contents('php://input'), true) ?? [];
$id         = (int)($input['id'] ?? 0);
$isUpdate   = $id > 0;
$username   = trim($input['username'] ?? '');
$first_name = trim($input['first_name'] ?? '');
$last_name  = trim($input['last_name'] ?? '');
$role       = $input['role'] ?? '';
$password   = $input['password'] ?? '';

// ---------- Validation ----------
$errors = [];

if (!preg_match('/^[a-zA-Z0-9_.]{3,50}$/', $username)) {
    $errors[] = 'Username must be 3-50 characters (letters, numbers, dot, underscore only).';
}
if ($first_name === '' || mb_strlen($first_name) > 50) {
    $errors[] = 'First name is required (max 50 characters).';
}
if ($last_name === '' || mb_strlen($last_name) > 50) {
    $errors[] = 'Last name is required (max 50 characters).';
}
if (!in_array($role, ['owner', 'staff'], true)) {
    $errors[] = 'Please choose a valid role.';
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
    // Username must be unique
    $stmt = $pdo->prepare('SELECT id FROM staff WHERE username = ? AND id <> ? LIMIT 1');
    $stmt->execute([$username, $id]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This username is already taken.']);
        exit;
    }

    if ($isUpdate) {
        $stmt = $pdo->prepare('SELECT id FROM staff WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Staff account not found.']);
            exit;
        }

        // Prevent the owner from demoting themself, which could lock the shop out of owner-only pages
        if ($id === (int)$_SESSION['staff_id'] && $role !== 'owner') {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'You cannot change your own role away from owner.']);
            exit;
        }

        if ($password !== '') {
            $stmt = $pdo->prepare(
                'UPDATE staff SET username = ?, first_name = ?, last_name = ?, role = ?, password = ? WHERE id = ?'
            );
            $stmt->execute([$username, $first_name, $last_name, $role,
                            password_hash($password, PASSWORD_DEFAULT), $id]);
        } else {
            $stmt = $pdo->prepare(
                'UPDATE staff SET username = ?, first_name = ?, last_name = ?, role = ? WHERE id = ?'
            );
            $stmt->execute([$username, $first_name, $last_name, $role, $id]);
        }

        echo json_encode(['success' => true, 'message' => 'Staff account updated.']);
        exit;
    }

    // ---------- Create ----------
    $stmt = $pdo->prepare(
        "INSERT INTO staff (username, password, first_name, last_name, role, status)
         VALUES (?, ?, ?, ?, ?, 'active')"
    );
    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $first_name, $last_name, $role]);

    echo json_encode(['success' => true, 'message' => "Staff account \"$username\" created."]);
} catch (PDOException $e) {
    error_log('Staff save failed: ' . $e->getMessage());

    if ($e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This username is already taken.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not save the staff account. Please try again.']);
    }
}