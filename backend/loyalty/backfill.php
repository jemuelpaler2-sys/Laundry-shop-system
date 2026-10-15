<?php
// This script is meant to run from the command line, not a browser.
//
// It gives the existing customers the loyalty stamps they already earned, so
// their cards are not starting from zero on a shop that has been running.
// Only loads that are already "Ready for Pickup" or "Completed" are counted;
// cancelled loads and loads still being processed are skipped.
//
// It is safe to run more than once: loyalty_stamps has one row per order, so
// an order can never be counted twice.
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from the command line.');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Loyalty.php';

$config   = loyaltyConfig();
$required = (int)$config['stamps_required'];

// Oldest wash first, so a customer with 12 completed loads earns their first
// reward on wash 10 and their second on wash 20.
$stmt = $pdo->query(
    "SELECT o.id, o.order_number, o.customer_id, o.order_status
     FROM orders o
     WHERE o.order_status IN ('ready_for_pickup', 'completed')
     ORDER BY o.id ASC"
);
$orders = $stmt->fetchAll();

echo "Found " . count($orders) . " order(s) that qualify for a loyalty stamp.\n";
echo "Rule: $required completed washes = 1 " . $config['reward_name'] . "\n\n";

$stamped = 0;
$skipped = 0;

foreach ($orders as $order) {
    $pdo->beginTransaction();

    try {
        $result = grantLoyaltyStamp($pdo, $order);
        $pdo->commit();

        $orderId = (int)$order['id'];

        if ($result['reason'] === 'already_stamped') {
            $skipped++;
            echo "  Order {$order['order_number']}: already counted, skipped.\n";
            continue;
        }

        if ($result['reason'] === 'reward_pending') {
            $stamped++;
            echo "  Order {$order['order_number']}: wash counted, stamp held until reward is redeemed.\n";
            continue;
        }

        $stamped++;
        $card  = $result['card'];
        $extra = $result['reward']
            ? '  REWARD UNLOCKED (' . $result['reward']['reward_code'] . ')'
            : '';

        echo "  Order {$order['order_number']}: card now {$card['stamps']}/$required"
           . " (lifetime {$card['total_completed_washes']}).$extra\n";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "  Order {$order['order_number']}: FAILED - " . $e->getMessage() . "\n";
    }
}

echo "\nDone. $stamped wash(es) counted, $skipped already on file.\n";
