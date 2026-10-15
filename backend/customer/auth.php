<?php
    // Shared session start + customer authorization helper
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    function requireCustomer(): void
    {
        if (
            empty($_SESSION['customer_logged_in']) ||
            $_SESSION['customer_logged_in'] !== true ||
            empty($_SESSION['customer_id'])
        ) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Customer login required.']);
            exit;
        }
    }