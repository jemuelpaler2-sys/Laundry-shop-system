<?php
// backend/receipts/guard.php
//
// Shared bootstrap for the receipt endpoints: CORS, database, session and the
// receipt library. The helper below works out who is asking (staff or
// customer) without forcing one role, because staff may view any order while
// a customer may only view their own.

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../staff/auth.php';   // starts the session, defines requireStaff()
require_once __DIR__ . '/../lib/Receipt.php';

/**
 * Returns the current viewer, or exits with 401 when nobody is logged in.
 *
 * @return array{staff_id:?int, customer_id:?int, is_staff:bool}
 */
function receiptViewer(): array
{
    $staffId = (!empty($_SESSION['staff_logged_in']) && !empty($_SESSION['staff_id']))
        ? (int)$_SESSION['staff_id']
        : null;

    $customerId = (!empty($_SESSION['customer_logged_in']) && !empty($_SESSION['customer_id']))
        ? (int)$_SESSION['customer_id']
        : null;

    if ($staffId === null && $customerId === null) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Login required.']);
        exit;
    }

    return [
        'staff_id'    => $staffId,
        'customer_id' => $customerId,
        'is_staff'    => $staffId !== null,
    ];
}
