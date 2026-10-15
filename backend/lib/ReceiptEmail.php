<?php
// backend/lib/ReceiptEmail.php
//
// One shared place that turns a receipt into an email and a PDF, sends it via
// the existing Gmail SMTP helper, and writes the attempt to email_logs.
//
// Used by BOTH:
//   - the manual "Email Receipt" button  (kind = manual,  payment_id = NULL)
//   - the automatic PayMongo receipt      (kind = automatic, payment_id = set)
//
// Duplicate protection: email_logs has a UNIQUE key on payment_id, so a single
// payment can only ever have ONE automatic receipt row. A retry UPDATES that
// row (attempts + 1) instead of inserting a new one. Manual rows have a NULL
// payment_id, so staff can resend as often as they like.

require_once __DIR__ . '/Receipt.php';
require_once __DIR__ . '/Mailer.php';

/** Subject line for a receipt email. */
function buildReceiptEmailSubject(array $receipt, bool $paidConfirmation): string
{
    $shop = $receipt['shop']['name'];
    if ($paidConfirmation) {
        return 'Payment Confirmation and Receipt #' . $receipt['receipt_number'] . ' - ' . $shop;
    }
    return 'Laundry Receipt #' . $receipt['receipt_number'] . ' - ' . $shop;
}

/** Weight shown as "8 kg" (or "—"). */
function receiptWeightText(array $receipt): string
{
    $w = (float) $receipt['order']['weight'];
    return $w > 0 ? rtrim(rtrim(number_format($w, 2, '.', ''), '0'), '.') . ' kg' : '—';
}

/**
 * The branded "payment received" body. All values come from the verified
 * receipt array — nothing is hardcoded.
 */
function buildPaidReceiptEmailHtml(array $receipt): string
{
    $h        = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $shop     = $receipt['shop'];
    $customer = $receipt['customer'];
    $order    = $receipt['order'];
    $payment  = $receipt['payment'];

    $rows = [
        'Receipt Number'      => $receipt['receipt_number'],
        'Order ID'            => $order['number'],
        'Service'             => $order['service'],
        'Laundry Weight'      => receiptWeightText($receipt),
        'Total Order Amount'  => receiptPeso($order['total']),
        'Amount Paid'         => receiptPeso($payment['amount_paid']),
        'Remaining Balance'   => receiptPeso($payment['balance']),
        'Payment Method'      => $payment['method'] ?: '—',
        'Transaction Reference' => $payment['reference'] ?: '—',
        'Payment Date'        => receiptDateTime($payment['last_date']),
        'Payment Status'      => $payment['status_label'],
    ];

    $rowsHtml = '';
    foreach ($rows as $label => $value) {
        $rowsHtml .= '<tr>'
            . '<td style="padding:5px 0;color:#6b7a8c;font-size:13px;">' . $h($label) . '</td>'
            . '<td style="padding:5px 0;text-align:right;font-weight:bold;font-size:13px;">' . $h($value) . '</td>'
            . '</tr>';
    }

    return '
        <div style="font-family:Arial,Helvetica,sans-serif;color:#1f2d3d;max-width:540px;">
            <p>Dear ' . $h($customer['name']) . ',</p>
            <p>Thank you for your payment!</p>
            <p>We have successfully received your payment for your laundry order.
               Your official transaction receipt is attached to this email as a PDF file.</p>

            <h3 style="color:#0b7e7a;font-size:15px;margin:18px 0 8px;">Payment Details</h3>
            <table style="width:100%;border-collapse:collapse;border-top:1px solid #d5e2e1;">'
            . $rowsHtml .
            '</table>

            <p style="margin-top:16px;">Please keep the attached receipt for your records.</p>
            <p>Thank you for choosing our laundry shop. We look forward to serving you again!</p>
            <p style="margin-top:16px;">Best regards,<br>' . $h($shop['name']) . '</p>
        </div>
    ';
}

/** The neutral body used for staff-initiated resends. */
function buildManualReceiptEmailHtml(array $receipt): string
{
    $h        = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $shop     = $receipt['shop'];
    $customer = $receipt['customer'];
    $order    = $receipt['order'];
    $payment  = $receipt['payment'];

    $rows = [
        'Receipt Number'    => $receipt['receipt_number'],
        'Order ID'          => $order['number'],
        'Service'           => $order['service'],
        'Weight'            => receiptWeightText($receipt),
        'Total Amount'      => receiptPeso($order['total']),
        'Amount Paid'       => receiptPeso($payment['amount_paid']),
        'Remaining Balance' => receiptPeso($payment['balance']),
        'Payment Status'    => $payment['status_label'],
    ];

    $rowsHtml = '';
    foreach ($rows as $label => $value) {
        $rowsHtml .= '<tr>'
            . '<td style="padding:4px 0;color:#6b7a8c;font-size:13px;">' . $h($label) . '</td>'
            . '<td style="padding:4px 0;text-align:right;font-weight:bold;font-size:13px;">' . $h($value) . '</td>'
            . '</tr>';
    }

    return '
        <div style="font-family:Arial,Helvetica,sans-serif;color:#1f2d3d;max-width:520px;">
            <p>Dear ' . $h($customer['name']) . ',</p>
            <p>Thank you for choosing our laundry service!</p>
            <p>Your laundry receipt is attached to this email for your reference.</p>
            <h3 style="color:#0b7e7a;font-size:15px;margin:18px 0 8px;">Receipt Summary</h3>
            <table style="width:100%;border-collapse:collapse;border-top:1px solid #d5e2e1;">'
            . $rowsHtml .
            '</table>
            <p style="margin-top:16px;">Please keep the attached receipt for your records.</p>
            <p>Thank you for choosing our laundry shop. We look forward to serving you again!</p>
            <p style="margin-top:16px;">Best regards,<br>' . $h($shop['name']) . '</p>
        </div>
    ';
}

/**
 * Writes one email attempt to email_logs.
 *
 * For automatic receipts (payment_id set) this is idempotent: the first call
 * INSERTs, later calls UPDATE the same row (attempts + 1). Manual rows always
 * INSERT.
 */
function logReceiptEmail(
    PDO $pdo,
    int $orderId,
    string $receiptNumber,
    string $recipient,
    string $kind,
    ?int $paymentId,
    ?int $staffId,
    bool $submitted,
    ?string $error
): void {
    $status = $submitted ? 'submitted' : 'failed';
    $err    = $submitted ? null : mb_substr((string) $error, 0, 2000);

    try {
        if ($paymentId !== null) {
            $stmt = $pdo->prepare(
                "INSERT INTO email_logs
                    (order_id, payment_id, receipt_number, recipient_email, kind,
                     sending_status, delivery_status, error_message, attempts, staff_id)
                 VALUES (?, ?, ?, ?, 'automatic', ?, 'unknown', ?, 1, ?)
                 ON DUPLICATE KEY UPDATE
                    attempts         = attempts + 1,
                    sending_status   = VALUES(sending_status),
                    error_message    = VALUES(error_message),
                    recipient_email  = VALUES(recipient_email),
                    receipt_number   = VALUES(receipt_number),
                    order_id         = VALUES(order_id),
                    staff_id         = VALUES(staff_id)"
            );
            $stmt->execute([$orderId, $paymentId, $receiptNumber, $recipient, $status, $err, $staffId]);
            return;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO email_logs
                (order_id, payment_id, receipt_number, recipient_email, kind,
                 sending_status, delivery_status, error_message, attempts, staff_id)
             VALUES (?, NULL, ?, ?, 'manual', ?, 'unknown', ?, 1, ?)"
        );
        $stmt->execute([$orderId, $receiptNumber, $recipient, $status, $err, $staffId]);
    } catch (PDOException $e) {
        error_log('Email log write failed: ' . $e->getMessage());
    }
}

/**
 * Builds the PDF, sends the email and logs the attempt. Returns true when the
 * mail server accepted it. Never throws.
 *
 * @param string $kind 'manual' | 'automatic'
 * @param bool $paidConfirmation Use the "payment received" subject/body
 */
function sendReceiptEmail(
    PDO $pdo,
    array $receipt,
    string $recipient,
    string $recipientName,
    string $kind,
    ?int $paymentId,
    ?int $staffId,
    bool $paidConfirmation,
    ?string &$error = null
): bool {
    $orderId       = (int) $receipt['order']['id'];
    $receiptNumber = (string) $receipt['receipt_number'];

    try {
        $subject = buildReceiptEmailSubject($receipt, $paidConfirmation);
        $bodyHtml = $paidConfirmation
            ? buildPaidReceiptEmailHtml($receipt)
            : buildManualReceiptEmailHtml($receipt);
        $pdf = buildReceiptPdf($receipt);
    } catch (Throwable $e) {
        error_log('Receipt email build failed: ' . $e->getMessage());
        $error = 'Could not build the receipt.';
        logReceiptEmail($pdo, $orderId, $receiptNumber, $recipient, $kind, $paymentId, $staffId, false, $error);
        return false;
    }

    $sent = sendMail(
        $recipient,
        $recipientName,
        $subject,
        $bodyHtml,
        [[
            'name' => receiptFilename($receipt),
            'data' => $pdf,
            'mime' => 'application/pdf',
        ]],
        $error
    );

    logReceiptEmail($pdo, $orderId, $receiptNumber, $recipient, $kind, $paymentId, $staffId, $sent, $error);

    return $sent;
}

/**
 * Retries every automatic receipt that previously failed. Safe to run again
 * and again: each retry updates the existing email_logs row. Returns
 * ['tried' => n, 'sent' => m].
 */
function retryFailedReceiptEmails(PDO $pdo, int $limit = 50): array
{
    $stmt = $pdo->prepare(
        "SELECT el.id, el.order_id, el.payment_id, el.recipient_email,
                o.order_number
         FROM email_logs el
         JOIN orders o ON o.id = el.order_id
         WHERE el.kind = 'automatic'
           AND el.sending_status = 'failed'
         ORDER BY el.id ASC
         LIMIT " . max(1, min(500, $limit))
    );
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $sent  = 0;
    $tried = 0;

    foreach ($rows as $row) {
        $receipt = getReceipt($pdo, (string) $row['order_number'], null);
        if (!$receipt) {
            continue;
        }
        $tried++;
        $err = null;
        if (sendReceiptEmail(
            $pdo,
            $receipt,
            (string) $row['recipient_email'],
            (string) $receipt['customer']['name'],
            'automatic',
            (int) $row['payment_id'],
            null,
            true,
            $err
        )) {
            $sent++;
        }
    }

    return ['tried' => $tried, 'sent' => $sent];
}
