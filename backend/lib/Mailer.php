<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Sends one email. Returns true/false and never throws — callers should not
// let a failed email stop anything else from happening.
function sendMail(string $toEmail, string $toName, string $subject, string $bodyHtml): bool
{
    $config = require __DIR__ . '/../config/mail.php';

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['username'];
        $mail->Password   = $config['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $config['port'];

        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $bodyHtml;
        $mail->AltBody  = strip_tags($bodyHtml);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Email send failed: ' . $mail->ErrorInfo);
        return false;
    }
}

// Status labels and short descriptions (mirrors frontend/src/utils/orderStatus.js)
const STATUS_LABELS = [
    'dropoff'          => 'Drop-off',
    'washing'          => 'Washing',
    'drying'           => 'Drying',
    'folding'          => 'Folding',
    'ready_for_pickup' => 'Ready for Pickup',
    'completed'        => 'Completed',
];

const STATUS_DESCRIPTIONS = [
    'washing'          => 'Your laundry is now being washed.',
    'drying'           => 'Your laundry is now being dried.',
    'folding'          => 'Your laundry is being folded and packed.',
    'ready_for_pickup' => 'Your laundry is clean and waiting for you!',
    'completed'        => 'You picked up your laundry. Thank you!',
];

// Looks up the order + customer, then sends the status-change email.
// Call this AFTER the status change is already committed to the database.
function sendOrderStatusEmail(PDO $pdo, int $orderId, string $newStatus): void
{
    // Only these statuses are worth emailing about (skip "dropoff", the starting state)
    if (!isset(STATUS_DESCRIPTIONS[$newStatus])) {
        return;
    }

    $stmt = $pdo->prepare(
        "SELECT o.order_number, o.service_type, o.weight,
                c.first_name, c.email
         FROM orders o
         JOIN customers c ON c.id = o.customer_id
         WHERE o.id = ? LIMIT 1"
    );
    $stmt->execute([$orderId]);
    $row = $stmt->fetch();

    if (!$row || empty($row['email'])) {
        return;
    }

    $label       = STATUS_LABELS[$newStatus];
    $description = STATUS_DESCRIPTIONS[$newStatus];
    $subject     = "Order {$row['order_number']}: {$label}";

    $bodyHtml = "
        <div style='font-family: Arial, sans-serif; color: #1f2d3d; max-width: 480px;'>
            <h2 style='color: #0b7e7a;'>Hi {$row['first_name']}!</h2>
            <p>Your laundry order <strong>{$row['order_number']}</strong> is now:</p>
            <p style='font-size: 20px; font-weight: bold; color: #0f9f9a;'>{$label}</p>
            <p>{$description}</p>
            <hr style='border: none; border-top: 1px solid #d5e2e1; margin: 20px 0;'>
            <p style='color: #6b7a8c; font-size: 13px;'>
                Service: {$row['service_type']}" .
                ($row['weight'] > 0 ? " · Weight: {$row['weight']} kg" : '') . "
            </p>
            <p style='color: #6b7a8c; font-size: 13px;'>
                Log in to your account to see full order details.
            </p>
        </div>
    ";

    sendMail($row['email'], $row['first_name'], $subject, $bodyHtml);
}