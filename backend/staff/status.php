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

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$id     = (int)($input['id'] ?? 0);
$status = $input['status'] ?? '';

if ($id <= 0 || !in_array($status, ['active', 'inactive'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

// An owner can never deactivate their own account
if ($id === (int)$_SESSION['staff_id'] && $status === 'inactive') {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'You cannot deactivate your own account.']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id FROM staff WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Staff account not found.']);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE staff SET status = ? WHERE id = ?');
    $stmt->execute([$status, $id]);

    echo json_encode([
        'success' => true,
        'message' => $status === 'active' ? 'Account activated.' : 'Account deactivated.',
    ]);
} catch (PDOException $e) {
    error_log('Staff status failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not update the account.']);
}