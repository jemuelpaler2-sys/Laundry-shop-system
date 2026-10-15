<?php
    require_once __DIR__ . '/../config/cors.php';
    require_once __DIR__ . '/auth.php';

    unset($_SESSION['customer_id'], $_SESSION['customer_logged_in']);
    session_regenerate_id(true);

    echo json_encode(['success' => true, 'message' => 'Logged out.']);