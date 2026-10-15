<?php
    require_once __DIR__ . '/../config/cors.php';
    require_once __DIR__ . '/auth.php';

    unset($_SESSION['staff_id'], $_SESSION['staff_role'], $_SESSION['staff_logged_in']);
    session_regenerate_id(true);

    echo json_encode(['success' => true, 'message' => 'Logged out.']);