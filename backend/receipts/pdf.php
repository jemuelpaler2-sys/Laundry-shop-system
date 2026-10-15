<?php
// backend/receipts/pdf.php
//
// Streams a receipt as a PDF. Staff may download any order; a customer may
// only download their own. Add ?download=1 for a save-as download instead of
// an inline preview.

require_once __DIR__ . '/guard.php';

$viewer = receiptViewer();

$orderNumber = trim($_GET['order_number'] ?? '');
if ($orderNumber === '' || mb_strlen($orderNumber) > 20) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid order number.']);
    exit;
}

try {
    $scope   = $viewer['is_staff'] ? null : $viewer['customer_id'];
    $receipt = getReceipt($pdo, $orderNumber, $scope);

    if (!$receipt) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Receipt not found for this order.']);
        exit;
    }

    $pdf = buildReceiptPdf($receipt);
} catch (Throwable $e) {
    error_log('Receipt PDF failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not generate the receipt PDF.']);
    exit;
}

$download = isset($_GET['download']) && $_GET['download'] !== '0';

// Replaces the default application/json header set by cors.php
header('Content-Type: application/pdf');
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline')
    . '; filename="' . receiptFilename($receipt) . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, max-age=0, must-revalidate');

echo $pdf;
