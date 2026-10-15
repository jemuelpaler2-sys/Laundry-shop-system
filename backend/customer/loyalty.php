<?php
    require_once __DIR__ . '/../config/cors.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../lib/Loyalty.php';
    require_once __DIR__ . '/auth.php';

    requireCustomer();

    // The customer ID always comes from the session, never from the request
    $customerId = (int)$_SESSION['customer_id'];

    try {
        $pdo->beginTransaction();

        // Every customer gets a card the first time they open this page
        $card   = getLoyaltyCard($pdo, $customerId);
        $result = loyaltyProgress($pdo, $card);

        $pdo->commit();

        $stmt = $pdo->prepare(
            'SELECT first_name, last_name, customer_code FROM customers WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$customerId]);
        $customer = $stmt->fetch() ?: [];

        // Every reward this customer has earned, newest first
        $stmt = $pdo->prepare(
            'SELECT id, reward_code, cycle, reward_status, unlocked_at, redeemed_at
             FROM loyalty_rewards
             WHERE customer_id = ?
             ORDER BY id DESC'
        );
        $stmt->execute([$customerId]);
        $rewards = array_map('formatLoyaltyReward', $stmt->fetchAll());

        // The orders that earned the stamps on this card
        $stmt = $pdo->prepare(
            'SELECT ls.order_id, ls.cycle, ls.stamps_added, ls.created_at, o.order_number, o.order_status
             FROM loyalty_stamps ls
             JOIN orders o ON o.id = ls.order_id
             WHERE ls.customer_id = ?
             ORDER BY ls.id DESC
             LIMIT 20'
        );
        $stmt->execute([$customerId]);
        $stamps = $stmt->fetchAll();

        foreach ($stamps as &$row) {
            $row['id']          = (int)$row['order_id'];
            $row['cycle']       = (int)$row['cycle'];
            $row['stamps_added']= (int)$row['stamps_added'];
        }
        unset($row);

        echo json_encode([
            'success' => true,
            'customer' => [
                'name'          => trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')),
                'first_name'    => $customer['first_name'] ?? '',
                'last_name'     => $customer['last_name'] ?? '',
                'customer_code' => $customer['customer_code'] ?? '',
            ],
            'card'    => $result,
            'rewards' => $rewards,
            'stamps'  => $stamps,
            'rule'    => '1 completed wash = 1 stamp. '
                       . $result['stamps_required'] . ' completed washes = 1 reward.',
        ]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Customer loyalty failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not load your loyalty card.']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Customer loyalty failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not load your loyalty card.']);
    }
