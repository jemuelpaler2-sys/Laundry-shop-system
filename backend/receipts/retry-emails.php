<?php
// backend/receipts/retry-emails.php
//
// Owner-only. Retries automatic receipt emails that previously failed
// (e.g. Gmail was briefly unavailable). Idempotent: each retry updates the
// existing email_logs row and never creates a payment or a duplicate receipt.

require_once __DIR__ . '/guard.php';
require_once __DIR__ . '/../lib/ReceiptEmail.php';

$viewer = receiptViewer();

if (!$viewer['is_staff'] || (($_SESSION['staff_role'] ?? '') !== 'owner')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Owner access only.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

try {
    $result = retryFailedReceiptEmails($pdo, 50);

    echo json_encode([
        'success' => true,
        'message' => "Retried {$result['tried']} failed receipt email(s); {$result['sent']} sent.",
        'tried'   => $result['tried'],
        'sent'    => $result['sent'],
    ]);
} catch (Throwable $e) {
    error_log('Retry receipt emails failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not retry the receipt emails.']);
}
