<?php
// backend/lib/OnlinePayment.php
//
// The single place where a verified "paid" gateway event turns into money in
// the database and an automatic receipt email.
//
// Idempotency is enforced at three levels, in this order:
//   1. payment_webhook_events (UNIQUE gateway + event_id)
//        -> the same delivered event can never be processed twice.
//   2. payments.transaction_reference (UNIQUE)
//        -> one gateway payment can never create two payment rows.
//   3. email_logs.payment_id (UNIQUE)
//        -> one payment can never produce two automatic receipts.
//
// Payment recording + the webhook-event row are committed together. The email
// is sent AFTER the commit, so a mail failure can never roll back a payment;
// it only leaves a 'failed' email_logs row for the retry job.

require_once __DIR__ . '/Receipt.php';
require_once __DIR__ . '/ReceiptEmail.php';

/** Finds a checkout session row by its gateway reference (cs_...). */
function findOnlinePaymentByReference(PDO $pdo, string $reference): ?array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM online_payments WHERE gateway = ? AND reference = ? LIMIT 1'
    );
    $stmt->execute(['paymongo', $reference]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Finds the most recent pending session for an order number (webhook fallback). */
function findOnlinePaymentByOrderNumber(PDO $pdo, string $orderNumber): ?array
{
    $stmt = $pdo->prepare(
        "SELECT op.* FROM online_payments op
         JOIN orders o ON o.id = op.order_id
         WHERE o.order_number = ?
         ORDER BY op.id DESC LIMIT 1"
    );
    $stmt->execute([$orderNumber]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Updates the webhook-event row's outcome (best effort). */
function updateWebhookEvent(PDO $pdo, int $id, string $status, ?string $message): void
{
    $stmt = $pdo->prepare(
        'UPDATE payment_webhook_events SET processing_status = ?, message = ? WHERE id = ?'
    );
    $stmt->execute([$status, $message !== null ? mb_substr($message, 0, 2000) : null, $id]);
}

/**
 * Processes a normalized paid event. Returns:
 *   ['status' => 'processed'|'duplicate'|'ignored', 'message' => string,
 *    'order_id' => ?int, 'payment_id' => ?int]
 * Throws on a hard failure so the caller can return 5xx and let PayMongo retry.
 */
function processPaymongoPaidEvent(PDO $pdo, array $event, string $rawPayload): array
{
    $gateway = 'paymongo';
    $eventId = (string) ($event['event_id'] ?? '');
    if ($eventId === '') {
        return ['status' => 'ignored', 'message' => 'Event has no id.', 'order_id' => null, 'payment_id' => null];
    }

    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
    }

    try {
        // 1. Idempotency gate: claim this event id.
        $ins = $pdo->prepare(
            "INSERT INTO payment_webhook_events
                (gateway, event_id, event_type, reference, processing_status, message, payload)
             VALUES (?, ?, ?, ?, 'processed', NULL, ?)"
        );
        try {
            $ins->execute([
                $gateway,
                $eventId,
                (string) ($event['event_type'] ?? ''),
                $event['session_id'] ?? null,
                mb_substr($rawPayload, 0, 60000),
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $pdo->rollBack();
                return ['status' => 'duplicate', 'message' => 'Event already processed.', 'order_id' => null, 'payment_id' => null];
            }
            throw $e;
        }
        $webhookRowId = (int) $pdo->lastInsertId();

        // 2. Find the checkout session we created.
        $online = null;
        if (!empty($event['session_id'])) {
            $online = findOnlinePaymentByReference($pdo, (string) $event['session_id']);
        }
        if (!$online && !empty($event['order_number'])) {
            $online = findOnlinePaymentByOrderNumber($pdo, (string) $event['order_number']);
        }

        if (!$online) {
            updateWebhookEvent($pdo, $webhookRowId, 'ignored', 'No matching checkout session.');
            $pdo->commit();
            return ['status' => 'ignored', 'message' => 'No matching checkout session.', 'order_id' => null, 'payment_id' => null];
        }

        $orderId = (int) $online['order_id'];

        // 3. Lock the order and read its real state.
        $stmt = $pdo->prepare(
            'SELECT id, order_number, price, order_status FROM orders WHERE id = ? FOR UPDATE'
        );
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            updateWebhookEvent($pdo, $webhookRowId, 'ignored', 'Order missing.');
            $pdo->commit();
            return ['status' => 'ignored', 'message' => 'Order missing.', 'order_id' => null, 'payment_id' => null];
        }

        $paidAt = $event['paid_at'] ?: date('Y-m-d H:i:s');

        if ($order['order_status'] === 'cancelled') {
            $upd = $pdo->prepare("UPDATE online_payments SET status = 'cancelled' WHERE id = ?");
            $upd->execute([(int) $online['id']]);
            updateWebhookEvent($pdo, $webhookRowId, 'ignored', 'Order was cancelled; payment not recorded.');
            $pdo->commit();
            return ['status' => 'ignored', 'message' => 'Order was cancelled.', 'order_id' => $orderId, 'payment_id' => null];
        }

        // 4. Already settled by this session? Nothing to do.
        if ($online['status'] === 'paid') {
            updateWebhookEvent($pdo, $webhookRowId, 'ignored', 'Session already marked paid.');
            $pdo->commit();
            return ['status' => 'duplicate', 'message' => 'Session already paid.', 'order_id' => $orderId, 'payment_id' => $online['payment_id'] !== null ? (int) $online['payment_id'] : null];
        }

        // 5. Work out what is actually still owed.
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE order_id = ?');
        $stmt->execute([$orderId]);
        $alreadyPaid = (float) $stmt->fetchColumn();

        $price   = (float) $order['price'];
        $balance = max(0, round($price - $alreadyPaid, 2));

        $methodLabel = (string) ($event['method_label'] ?? 'Online');
        $reference   = (string) ($event['payment_id'] ?? '');

        // 6. Nothing left to pay: just close the session.
        if ($price <= 0 || $balance <= 0) {
            $upd = $pdo->prepare(
                "UPDATE online_payments
                 SET status = 'paid', transaction_reference = ?, payment_method = ?,
                     livemode = ?, paid_at = ?
                 WHERE id = ?"
            );
            $upd->execute([$reference ?: null, $methodLabel, (int) ($event['livemode'] ?? 0), $paidAt, (int) $online['id']]);
            updateWebhookEvent($pdo, $webhookRowId, 'processed', 'Order already settled; no new payment.');
            $pdo->commit();
            return ['status' => 'processed', 'message' => 'Order already settled.', 'order_id' => $orderId, 'payment_id' => null];
        }

        // Never record more than is owed.
        $amount = min((float) $event['amount_pesos'], $balance);
        $amount = round($amount, 2);
        if ($amount <= 0) {
            updateWebhookEvent($pdo, $webhookRowId, 'ignored', 'Event amount was zero.');
            $pdo->commit();
            return ['status' => 'ignored', 'message' => 'Zero amount.', 'order_id' => $orderId, 'payment_id' => null];
        }

        $newPaid   = round($alreadyPaid + $amount, 2);
        $newStatus = ($newPaid + 0.005 >= $price) ? 'paid' : 'partial';

        // 7. Insert the payment (guard the unique reference again).
        $paymentId = null;
        if ($reference !== '') {
            $chk = $pdo->prepare('SELECT id FROM payments WHERE transaction_reference = ? LIMIT 1');
            $chk->execute([$reference]);
            $existing = $chk->fetch();
            if ($existing) {
                $paymentId = (int) $existing['id'];
            }
        }

        if ($paymentId === null) {
            $insP = $pdo->prepare(
                'INSERT INTO payments
                    (order_id, amount, payment_method, gateway, transaction_reference, payment_status, payment_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $insP->execute([
                $orderId,
                $amount,
                $methodLabel,
                $gateway,
                $reference !== '' ? $reference : null,
                $newStatus,
                $paidAt,
            ]);
            $paymentId = (int) $pdo->lastInsertId();
        }

        // 8. Close out the checkout session.
        $upd = $pdo->prepare(
            "UPDATE online_payments
             SET status = 'paid', transaction_reference = ?, payment_method = ?,
                 payment_id = ?, livemode = ?, paid_at = ?
             WHERE id = ?"
        );
        $upd->execute([$reference ?: null, $methodLabel, $paymentId, (int) ($event['livemode'] ?? 0), $paidAt, (int) $online['id']]);

        updateWebhookEvent($pdo, $webhookRowId, 'processed', null);
        $pdo->commit();

        return [
            'status'     => 'processed',
            'message'    => 'Payment recorded.',
            'order_id'   => $orderId,
            'payment_id' => $paymentId,
            'amount'     => $amount,
            'method'     => $methodLabel,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Sends the automatic receipt for a just-recorded payment. Runs AFTER the
 * payment is committed. Returns false (and leaves a 'failed' log) when the
 * customer has no usable email or SMTP fails — the retry job can fix it.
 */
function sendAutomaticReceipt(PDO $pdo, int $orderId, int $paymentId): bool
{
    $stmt = $pdo->prepare('SELECT order_number FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $orderNumber = $stmt->fetchColumn();
    if (!$orderNumber) {
        return false;
    }

    $receipt = getReceipt($pdo, (string) $orderNumber, null);
    if (!$receipt) {
        return false;
    }

    $recipient = strtolower(trim((string) $receipt['customer']['email']));
    $receiptNumber = (string) $receipt['receipt_number'];

    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || mb_strlen($recipient) > 100) {
        // Log it so staff can see and fix the customer's address, then resend.
        logReceiptEmail(
            $pdo,
            (int) $receipt['order']['id'],
            $receiptNumber,
            $recipient !== '' ? $recipient : 'no-email@invalid',
            'automatic',
            $paymentId,
            null,
            false,
            'Customer does not have a valid email address on file.'
        );
        return false;
    }

    $error = null;
    return sendReceiptEmail(
        $pdo,
        $receipt,
        $recipient,
        (string) $receipt['customer']['name'],
        'automatic',
        $paymentId,
        null,
        true,           // use the "Payment Confirmation" subject/body
        $error
    );
}
