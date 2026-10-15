<?php
// This script is meant to run from the command line (Task Scheduler), not a browser.
// It sends a reminder email for every pickup scheduled for TOMORROW.

// Safety: refuse to run through a web browser, only from the command line
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from the command line.');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Mailer.php';

$tomorrow = (new DateTime('tomorrow'))->format('Y-m-d');

$stmt = $pdo->prepare(
    "SELECT ps.id, ps.scheduled_date, ps.scheduled_time,
            o.order_number, o.service_type, o.weight,
            c.first_name, c.email
     FROM pickup_schedules ps
     JOIN orders o ON o.id = ps.order_id
     JOIN customers c ON c.id = o.customer_id
     WHERE ps.status = 'scheduled'
       AND ps.scheduled_date = ?
       AND ps.reminder_sent_at IS NULL"
);
$stmt->execute([$tomorrow]);
$pickups = $stmt->fetchAll();

echo "Found " . count($pickups) . " pickup(s) scheduled for $tomorrow.\n";

$sent = 0;
$markSent = $pdo->prepare('UPDATE pickup_schedules SET reminder_sent_at = NOW() WHERE id = ?');

foreach ($pickups as $p) {
    if (empty($p['email'])) {
        echo "Skipped order {$p['order_number']}: no email on file.\n";
        continue;
    }

    $subject = "Reminder: Pickup tomorrow for order {$p['order_number']}";
    $bodyHtml = "
        <div style='font-family: Arial, sans-serif; color: #1f2d3d; max-width: 480px;'>
            <h2 style='color: #0b7e7a;'>Hi {$p['first_name']}!</h2>
            <p>Just a reminder — your laundry pickup is scheduled for <strong>tomorrow</strong>:</p>
            <p style='font-size: 18px; font-weight: bold; color: #0f9f9a;'>
                {$p['scheduled_date']} &middot; {$p['scheduled_time']}
            </p>
            <p>Order {$p['order_number']} &middot; {$p['service_type']}" .
                ($p['weight'] > 0 ? " &middot; {$p['weight']} kg" : '') . "</p>
            <hr style='border: none; border-top: 1px solid #d5e2e1; margin: 20px 0;'>
            <p style='color: #6b7a8c; font-size: 13px;'>See you then!</p>
        </div>
    ";

    if (sendMail($p['email'], $p['first_name'], $subject, $bodyHtml)) {
        $markSent->execute([$p['id']]);
        $sent++;
        echo "Sent reminder for order {$p['order_number']} to {$p['email']}.\n";
    } else {
        echo "FAILED to send reminder for order {$p['order_number']}. Check error.log.\n";
    }
}

echo "Done. $sent of " . count($pickups) . " reminder(s) sent.\n";