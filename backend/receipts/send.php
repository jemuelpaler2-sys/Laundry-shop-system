<?php
// backend/receipts/send.php
//
// Emails a receipt (as a PDF attachment) to the customer. Staff only.
// The recipient defaults to the customer's registered email but staff may
// override it after reviewing it in the UI. Every attempt is written to
// email_logs — no order or payment is ever created here.
//
// Uses the shared sendReceiptEmail() so the manual resend and the automatic
// PayMongo receipt stay perfectly in sync (same PDF, same logging).

require_once __DIR__ . '/guard.php';
require_once __DIR__ . '/../lib/ReceiptEmail.php';

$viewer = receiptViewer();

if (!$viewer['is_staff']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Staff access only.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input       = json_decode(file_get_contents('php://input'), true) ?? [];
$orderNumber = trim($input['order_number'] ?? '');
$emailInput  = strtolower(trim($input['email'] ?? ''));

if ($orderNumber === '' || mb_strlen($orderNumber) > 20) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid order number.']);
    exit;
}

try {
    // Staff scope: they may email a receipt for any order.
    $receipt = getReceipt($pdo, $orderNumber, null);

    if (!$receipt) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Receipt not found for this order.']);
        exit;
    }

    // Recipient: the staff-provided address, otherwise the registered one.
    $recipient = $emailInput !== '' ? $emailInput : strtolower(trim((string)$receipt['customer']['email']));

    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || mb_strlen($recipient) > 100) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Please enter a valid recipient email address.',
        ]);
        exit;
    }

    $smtpError = null;
    $sent = sendReceiptEmail(
        $pdo,
        $receipt,
        $recipient,
        (string) $receipt['customer']['name'],
        'manual',
        null,
        $viewer['staff_id'],
        false,          // manual resend uses the neutral body
        $smtpError
    );

    if ($sent) {
        // SMTP acceptance is not the same as confirmed delivery, so we say so.
        echo json_encode([
            'success' => true,
            'message' => 'Receipt emailed to ' . $recipient . '. It was accepted by the mail server.',
        ]);
        exit;
    }

    // Read dev_mode from the main config so local setups see the real reason.
    $appConfig  = require __DIR__ . '/../config/config.php';
    $showDetail = !empty($appConfig['dev_mode']);

    http_response_code(502);
    echo json_encode([
        'success' => false,
        'message' => 'Could not send the email. Please check the Gmail SMTP configuration in backend/.env.'
                   . ($showDetail && $smtpError ? ' (' . $smtpError . ')' : ''),
    ]);
} catch (Throwable $e) {
    error_log('Receipt send failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not send the receipt. Please try again.']);
}
