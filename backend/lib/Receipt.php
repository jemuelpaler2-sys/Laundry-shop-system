<?php
// backend/lib/Receipt.php
//
// Shared receipt logic used by the staff portal and the customer portal.
// It reads the real order, customer and payment rows, works out the totals
// using the shop's existing pricing config, and can render the receipt as
// HTML (screen/email) or as a PDF (download/attachment).
//
// One receipt per order: the receipt number is derived from the order number,
// so re-generating or re-sending a receipt never creates a new order or
// another payment.

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Builds the receipt number from an order number, e.g. "ORD-1001" -> "RCPT-1001".
 * Falls back to the order id when the order number has no digits.
 */
function receiptNumber(string $orderNumber, int $orderId = 0): string
{
    $shop   = require __DIR__ . '/../config/shop.php';
    $prefix = (string)$shop['receipt_prefix'];

    $digits = preg_replace('/\D+/', '', $orderNumber);
    if ($digits === null || $digits === '') {
        $digits = (string)$orderId;
    }

    return $prefix . $digits;
}

/**
 * Loads one receipt. Returns null when the order does not exist, or when it
 * belongs to a different customer than $scopeCustomerId (customer portal).
 * Staff pass $scopeCustomerId = null to read any order.
 */
function getReceipt(PDO $pdo, string $orderNumber, ?int $scopeCustomerId = null): ?array
{
    $sql = "SELECT o.id AS order_id, o.order_number, o.service_type, o.weight, o.price,
                   o.order_status, o.dropoff_date, o.actual_pickup_date,
                   c.id AS customer_id, c.customer_code, c.first_name, c.last_name,
                   c.email, c.phone
            FROM orders o
            JOIN customers c ON c.id = o.customer_id
            WHERE o.order_number = ?";
    $params = [$orderNumber];

    if ($scopeCustomerId !== null) {
        $sql .= ' AND o.customer_id = ?';
        $params[] = $scopeCustomerId;
    }
    $sql .= ' LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    $services = require __DIR__ . '/../config/services.php';
    $rate = isset($services[$row['service_type']])
        ? (float)$services[$row['service_type']]
        : (float)reset($services);

    $weight = (float)$row['weight'];
    $total  = (float)$row['price'];

    // Every recorded payment for this order, oldest first
    $stmt = $pdo->prepare(
        'SELECT amount, payment_method, payment_date, transaction_reference, gateway
         FROM payments
         WHERE order_id = ?
         ORDER BY payment_date ASC, id ASC'
    );
    $stmt->execute([(int)$row['order_id']]);
    $paymentRows = $stmt->fetchAll();

    $amountPaid = 0.0;
    $method     = null;
    $lastDate   = null;
    $reference  = null;
    $gateway    = null;
    $transactions = [];

    foreach ($paymentRows as $p) {
        $amount = (float)$p['amount'];
        $amountPaid += $amount;
        $method   = $p['payment_method'];
        $lastDate = $p['payment_date'];
        $reference = $p['transaction_reference'] ?? null;
        $gateway   = $p['gateway'] ?? null;
        $transactions[] = [
            'date'      => $p['payment_date'],
            'amount'    => round($amount, 2),
            'method'    => $p['payment_method'],
            'reference' => $p['transaction_reference'] ?? null,
        ];
    }
    $amountPaid = round($amountPaid, 2);

    $balance = max(0, round($total - $amountPaid, 2));
    $change  = max(0, round($amountPaid - $total, 2));

    if ($total <= 0) {
        $status = 'pending';
    } elseif ($balance <= 0) {
        $status = 'paid';
    } elseif ($amountPaid > 0) {
        $status = 'partial';
    } else {
        $status = 'unpaid';
    }

    $statusLabels = [
        'paid'    => 'Paid',
        'partial' => 'Partially Paid',
        'unpaid'  => 'Unpaid',
        'pending' => 'To be computed',
    ];

    $shop = require __DIR__ . '/../config/shop.php';

    return [
        'shop' => [
            'name'    => $shop['name'],
            'address' => $shop['address'],
            'phone'   => $shop['phone'],
            'email'   => $shop['email'],
        ],
        'receipt_number' => receiptNumber($row['order_number'], (int)$row['order_id']),
        'issued_at'      => date('Y-m-d H:i:s'),
        'customer' => [
            'id'    => (int)$row['customer_id'],
            'code'  => $row['customer_code'],
            'name'  => trim($row['first_name'] . ' ' . $row['last_name']),
            'email' => $row['email'],
            'phone' => $row['phone'],
        ],
        'order' => [
            'id'       => (int)$row['order_id'],
            'number'   => $row['order_number'],
            'date'     => $row['dropoff_date'],
            'service'  => $row['service_type'],
            'weight'   => $weight,
            'rate'     => $rate,
            'subtotal' => $total,
            'discount' => 0.0,
            'total'    => $total,
            'status'   => $row['order_status'],
        ],
        'payment' => [
            'amount_paid'  => $amountPaid,
            'method'       => $method,
            'status'       => $status,
            'status_label' => $statusLabels[$status],
            'balance'      => $balance,
            'change'       => $change,
            'last_date'    => $lastDate,
            'reference'    => $reference,
            'gateway'      => $gateway,
        ],
        'transactions' => $transactions,
    ];
}

/** Formats a number as Philippine pesos, using the configured currency symbol. */
function receiptPeso($value): string
{
    return '₱' . number_format((float)$value, 2);
}

/** Human-friendly date+time for receipts. */
function receiptDateTime(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $ts = strtotime($value);
    return $ts ? date('M j, Y g:i A', $ts) : (string)$value;
}

/** Human-friendly date. */
function receiptDate(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $ts = strtotime($value);
    return $ts ? date('M j, Y', $ts) : (string)$value;
}

/**
 * Renders the receipt as standalone HTML using simple tables and inline styles,
 * so the same markup works for dompdf and for the email body.
 */
function buildReceiptHtml(array $receipt): string
{
    $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

    $shop     = $receipt['shop'];
    $customer = $receipt['customer'];
    $order    = $receipt['order'];
    $payment  = $receipt['payment'];

    $teal     = '#0f9f9a';
    $tealDark = '#0b7e7a';
    $muted    = '#6b7a8c';
    $border   = '#d5e2e1';

    $weightText = ((float)$order['weight']) > 0
        ? $h(rtrim(rtrim(number_format((float)$order['weight'], 2, '.', ''), '0'), '.')) . ' kg'
        : 'Not weighed yet';

    $serviceLine = $h($order['service']) . ' — ' . $weightText
        . ' × ' . receiptPeso($order['rate']) . '/kg';

    $totalText = ((float)$order['total']) > 0
        ? receiptPeso($order['total'])
        : 'To be computed';

    // Payment section rows
    $paymentRows = [];
    $paymentRows[] = ['Amount Paid', receiptPeso($payment['amount_paid'])];
    $paymentRows[] = ['Payment Method', $payment['method'] ? $h($payment['method']) : '—'];
    $paymentRows[] = ['Payment Status', $h($payment['status_label'])];
    if (!empty($payment['reference'])) {
        $paymentRows[] = ['Transaction Reference', $h($payment['reference'])];
    }
    if ((float)$order['total'] > 0) {
        $paymentRows[] = ['Remaining Balance', receiptPeso($payment['balance'])];
    }
    if ((float)$payment['change'] > 0) {
        $paymentRows[] = ['Change', receiptPeso($payment['change'])];
    }
    if ($payment['last_date']) {
        $paymentRows[] = ['Last Payment', $h(receiptDateTime($payment['last_date']))];
    }

    $payRowsHtml = '';
    foreach ($paymentRows as $r) {
        $payRowsHtml .= '<tr>'
            . '<td style="padding:6px 0;color:' . $muted . ';font-size:12px;">' . $r[0] . '</td>'
            . '<td style="padding:6px 0;text-align:right;font-weight:bold;font-size:13px;">' . $r[1] . '</td>'
            . '</tr>';
    }

    // Transaction history (skip when empty)
    $txHtml = '';
    if (!empty($receipt['transactions'])) {
        $txRows = '';
        foreach ($receipt['transactions'] as $t) {
            $txRows .= '<tr>'
                . '<td style="padding:5px 0;font-size:12px;">' . $h(receiptDateTime($t['date'])) . '</td>'
                . '<td style="padding:5px 0;font-size:12px;">' . $h($t['method']) . '</td>'
                . '<td style="padding:5px 0;text-align:right;font-size:12px;">' . receiptPeso($t['amount']) . '</td>'
                . '</tr>';
        }
        $txHtml = '<h3 style="font-size:13px;color:' . $tealDark . ';margin:18px 0 6px;">Payment History</h3>'
            . '<table style="width:100%;border-collapse:collapse;border-top:1px solid ' . $border . ';">'
            . '<thead><tr>'
            . '<th style="text-align:left;padding:5px 0;font-size:11px;color:' . $muted . ';text-transform:uppercase;">Date</th>'
            . '<th style="text-align:left;padding:5px 0;font-size:11px;color:' . $muted . ';text-transform:uppercase;">Method</th>'
            . '<th style="text-align:right;padding:5px 0;font-size:11px;color:' . $muted . ';text-transform:uppercase;">Amount</th>'
            . '</tr></thead><tbody>' . $txRows . '</tbody></table>';
    }

    $contactBits = [];
    if ($shop['address'] !== '') { $contactBits[] = $h($shop['address']); }
    if ($shop['phone'] !== '')   { $contactBits[] = $h($shop['phone']); }
    if ($shop['email'] !== '')   { $contactBits[] = $h($shop['email']); }
    $contactLine = implode(' • ', $contactBits);

    return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
        . '<title>' . $h($receipt['receipt_number']) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#f4f7f7;font-family:DejaVu Sans,Arial,Helvetica,sans-serif;color:#1f2d3d;">'
        . '<div style="max-width:640px;margin:0 auto;padding:24px;">'
        . '<div style="background:#ffffff;border:1px solid ' . $border . ';border-radius:12px;padding:28px;">'

        // Header
        . '<table style="width:100%;border-collapse:collapse;"><tr>'
        . '<td style="vertical-align:top;">'
        . '<div style="font-size:22px;font-weight:bold;color:' . $tealDark . ';">' . $h($shop['name']) . '</div>'
        . '<div style="font-size:12px;color:' . $muted . ';margin-top:4px;">' . $contactLine . '</div>'
        . '</td>'
        . '<td style="vertical-align:top;text-align:right;">'
        . '<div style="font-size:16px;font-weight:bold;letter-spacing:1px;color:' . $teal . ';">RECEIPT</div>'
        . '<div style="font-size:12px;color:' . $muted . ';margin-top:4px;">No. <strong>' . $h($receipt['receipt_number']) . '</strong></div>'
        . '<div style="font-size:12px;color:' . $muted . ';">Issued: ' . $h(receiptDateTime($receipt['issued_at'])) . '</div>'
        . '</td>'
        . '</tr></table>'

        . '<hr style="border:none;border-top:1px solid ' . $border . ';margin:16px 0;">'

        // Customer + order meta
        . '<table style="width:100%;border-collapse:collapse;"><tr style="vertical-align:top;">'
        . '<td style="width:50%;">'
        . '<div style="font-size:11px;color:' . $muted . ';text-transform:uppercase;font-weight:bold;">Bill To</div>'
        . '<div style="font-size:14px;font-weight:bold;margin-top:4px;">' . $h($customer['name']) . '</div>'
        . '<div style="font-size:12px;color:' . $muted . ';">Customer ID: ' . $h($customer['code']) . '</div>'
        . '<div style="font-size:12px;color:' . $muted . ';">' . $h($customer['email']) . '</div>'
        . '<div style="font-size:12px;color:' . $muted . ';">' . $h($customer['phone']) . '</div>'
        . '</td>'
        . '<td style="width:50%;text-align:right;">'
        . '<div style="font-size:11px;color:' . $muted . ';text-transform:uppercase;font-weight:bold;">Order Details</div>'
        . '<div style="font-size:12px;color:' . $muted . ';margin-top:4px;">Order ID: <strong>' . $h($order['number']) . '</strong></div>'
        . '<div style="font-size:12px;color:' . $muted . ';">Order Date: ' . $h(receiptDateTime($order['date'])) . '</div>'
        . '</td>'
        . '</tr></table>'

        . '<hr style="border:none;border-top:1px solid ' . $border . ';margin:16px 0;">'

        // Line items
        . '<table style="width:100%;border-collapse:collapse;">'
        . '<thead><tr>'
        . '<th style="text-align:left;padding:6px 0;font-size:11px;color:' . $muted . ';text-transform:uppercase;border-bottom:1px solid ' . $border . ';">Service</th>'
        . '<th style="text-align:right;padding:6px 0;font-size:11px;color:' . $muted . ';text-transform:uppercase;border-bottom:1px solid ' . $border . ';">Amount</th>'
        . '</tr></thead><tbody>'
        . '<tr>'
        . '<td style="padding:10px 0;font-size:13px;">' . $serviceLine . '</td>'
        . '<td style="padding:10px 0;text-align:right;font-size:13px;">' . $totalText . '</td>'
        . '</tr>'
        . '</tbody></table>'

        // Totals
        . '<table style="width:100%;border-collapse:collapse;margin-top:8px;border-top:1px solid ' . $border . ';">'
        . '<tr><td style="padding:6px 0;color:' . $muted . ';font-size:12px;text-align:right;">Subtotal</td>'
        . '<td style="padding:6px 0;text-align:right;font-size:13px;width:120px;">' . ($order['total'] > 0 ? receiptPeso($order['subtotal']) : '—') . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:' . $muted . ';font-size:12px;text-align:right;">Discount</td>'
        . '<td style="padding:6px 0;text-align:right;font-size:13px;">' . receiptPeso($order['discount']) . '</td></tr>'
        . '<tr><td style="padding:8px 0;font-weight:bold;font-size:14px;text-align:right;border-top:1px solid ' . $border . ';color:' . $tealDark . ';">Final Total</td>'
        . '<td style="padding:8px 0;text-align:right;font-weight:bold;font-size:15px;border-top:1px solid ' . $border . ';color:' . $tealDark . ';">' . $totalText . '</td></tr>'
        . '</table>'

        // Payment details
        . '<h3 style="font-size:13px;color:' . $tealDark . ';margin:20px 0 6px;">Payment Details</h3>'
        . '<table style="width:100%;border-collapse:collapse;">' . $payRowsHtml . '</table>'

        . $txHtml

        // Footer
        . '<hr style="border:none;border-top:1px solid ' . $border . ';margin:20px 0 12px;">'
        . '<p style="font-size:12px;color:' . $muted . ';margin:0;">Thank you for choosing ' . $h($shop['name']) . '!</p>'
        . '<p style="font-size:11px;color:' . $muted . ';margin:6px 0 0;">This is a system-generated receipt for Order ' . $h($order['number']) . '.</p>'

        . '</div></div></body></html>';
}

/** Renders the receipt HTML to a PDF and returns the raw bytes. */
function buildReceiptPdf(array $receipt): string
{
    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', false);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->loadHtml(buildReceiptHtml($receipt), 'UTF-8');
    $dompdf->render();

    return $dompdf->output();
}

/** Safe filename for the downloaded/attached receipt. */
function receiptFilename(array $receipt): string
{
    return 'Receipt-' . $receipt['receipt_number'] . '.pdf';
}
