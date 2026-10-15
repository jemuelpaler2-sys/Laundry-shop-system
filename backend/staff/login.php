<?php
    require_once __DIR__ . '/../config/cors.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/auth.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        exit;
    }

    $input    = json_decode(file_get_contents('php://input'), true);
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    if ($username === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT id, username, password, first_name, last_name, role, status
        FROM staff WHERE username = ? LIMIT 1'
    );
    $stmt->execute([$username]);
    $staff = $stmt->fetch();

    // Same message for wrong username or wrong password (do not reveal which one)
    if (!$staff || !password_verify($password, $staff['password'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
        exit;
    }

    if ($staff['status'] !== 'active') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'This account is inactive. Please contact the shop owner.']);
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['staff_id']        = (int)$staff['id'];
    $_SESSION['staff_role']      = $staff['role'];
    $_SESSION['staff_logged_in'] = true;

    echo json_encode([
        'success' => true,
        'message' => 'Login successful.',
        'staff'   => [
            'id'         => (int)$staff['id'],
            'username'   => $staff['username'],
            'first_name' => $staff['first_name'],
            'last_name'  => $staff['last_name'],
            'role'       => $staff['role'],
        ],
    ]);