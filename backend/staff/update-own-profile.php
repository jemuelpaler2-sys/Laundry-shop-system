<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

requireStaff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// The account being edited always comes from the session, never from the request
$id = (int)$_SESSION['staff_id'];

$input      = json_decode(file_get_contents('php://input'), true) ?? [];
$first_name = trim($input['first_name'] ?? '');
$last_name  = trim($input['last_name'] ?? '');

if ($first_name === '' || mb_strlen($first_name) > 50) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'First name is required (max 50 characters).']);
    exit;
}
if ($last_name === '' || mb_strlen($last_name) > 50) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Last name is required (max 50 characters).']);
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE staff SET first_name = ?, last_name = ? WHERE id = ?');
    $stmt->execute([$first_name, $last_name, $id]);

    $stmt = $pdo->prepare('SELECT id, username, first_name, last_name, role FROM staff WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);

    echo json_encode(['success' => true, 'message' => 'Your profile was updated.', 'staff' => $stmt->fetch()]);
} catch (PDOException $e) {
    error_log('Staff self-update failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not update your profile.']);
}