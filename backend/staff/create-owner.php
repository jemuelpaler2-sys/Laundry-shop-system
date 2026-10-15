<?php
    require_once __DIR__ . '/../config/cors.php';
    require_once __DIR__ . '/../config/database.php';

    $count = (int)$pdo->query('SELECT COUNT(*) FROM staff')->fetchColumn();
    if ($count > 0) {
        echo json_encode(['success' => false, 'message' => 'Staff accounts already exist. Nothing was created.']);
        exit;
    }

    $accounts = [
        ['owner',  'Owner@12345', 'Shop',    'Owner', 'owner'],
        ['staff1', 'Staff@12345', 'Laundry', 'Staff', 'staff'],
    ];

    $stmt = $pdo->prepare(
        "INSERT INTO staff (username, password, first_name, last_name, role, status)
        VALUES (?, ?, ?, ?, ?, 'active')"
    );

    foreach ($accounts as $a) {
        $stmt->execute([$a[0], password_hash($a[1], PASSWORD_DEFAULT), $a[2], $a[3], $a[4]]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Created owner and staff1. DELETE this file now.',
    ]);