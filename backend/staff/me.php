<?php
    require_once __DIR__ . '/../config/cors.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/auth.php';

    requireStaff();

    $stmt = $pdo->prepare(
        "SELECT id, username, first_name, last_name, role
        FROM staff WHERE id = ? AND status = 'active' LIMIT 1"
    );
    $stmt->execute([$_SESSION['staff_id']]);
    $staff = $stmt->fetch();

    if (!$staff) {
        unset($_SESSION['staff_id'], $_SESSION['staff_role'], $_SESSION['staff_logged_in']);
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Account not found or inactive.']);
        exit;
    }

    echo json_encode(['success' => true, 'staff' => $staff]);