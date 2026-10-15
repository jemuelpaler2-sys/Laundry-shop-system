<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Loyalty.php';
require_once __DIR__ . '/../staff/auth.php';

requireStaff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input    = json_decode(file_get_contents('php://input'), true) ?? [];
$rewardId = (int)($input['reward_id'] ?? 0);

if ($rewardId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid reward.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $result = redeemLoyaltyReward($pdo, $rewardId);

    $stmt = $pdo->prepare(
        'SELECT first_name, last_name, customer_code FROM customers WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$result['reward']['customer_id']]);
    $customer = $stmt->fetch() ?: ['first_name' => '', 'last_name' => '', 'customer_code' => ''];

    $pdo->commit();

    $name = trim($customer['first_name'] . ' ' . $customer['last_name']);

    echo json_encode([
        'success' => true,
        'message' => 'Reward ' . $result['reward']['reward_code'] . ' redeemed for ' . $name
                   . '. Their loyalty card is back to ' . $result['stamps'] . '/'
                   . loyaltyRequiredStamps() . ' washes for the next cycle.',
        'reward_code' => $result['reward']['reward_code'],
        'stamps'      => $result['stamps'],
    ]);
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Loyalty redeem failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not redeem the reward. Please try again.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Loyalty redeem failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not redeem the reward. Please try again.']);
}
