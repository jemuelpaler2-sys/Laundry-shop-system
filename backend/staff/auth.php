<?php
// Shared session start + staff authorization helpers
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    function requireStaff(): void
    {
        if (
            empty($_SESSION['staff_logged_in']) ||
            $_SESSION['staff_logged_in'] !== true ||
            empty($_SESSION['staff_id'])
        ) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Staff login required.']);
            exit;
        }
    }

    function requireOwner(): void
    {
        requireStaff();
        if (($_SESSION['staff_role'] ?? '') !== 'owner') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Shop owner access only.']);
            exit;
        }
    }
