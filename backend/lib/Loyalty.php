<?php
// Shared loyalty card logic used by the customer portal, the staff dashboard
// and the order workflow. Every stamp goes through grantLoyaltyStamp() so the
// "one stamp per order" rule is enforced in exactly one place.

    function loyaltyConfig(): array
    {
        static $config = null;
        if ($config === null) {
            $config = require __DIR__ . '/../config/loyalty.php';
        }
        return $config;
    }

    function loyaltyRequiredStamps(): int
    {
        $config = loyaltyConfig();
        return (int)$config['stamps_required'];
    }

    // Order statuses that earn a stamp. Anything else (dropoff, washing,
    // drying, folding, cancelled) never does.
    function loyaltyQualifies(string $orderStatus): bool
    {
        $config = loyaltyConfig();
        return in_array($orderStatus, $config['qualifying_statuses'], true);
    }

    // Finds the customer's card, creating an empty one the first time.
    // Call this inside a transaction so that two simultaneous requests cannot
    // both create a card for the same customer.
    function getLoyaltyCard(PDO $pdo, int $customerId): array
    {
        $stmt = $pdo->prepare(
            'SELECT id, customer_id, stamps, total_completed_washes, created_at, updated_at
             FROM loyalty_cards
             WHERE customer_id = ?
             LIMIT 1'
        );
        $stmt->execute([$customerId]);
        $card = $stmt->fetch();

        if (!$card) {
            $stmt = $pdo->prepare(
                'INSERT INTO loyalty_cards (customer_id, stamps, total_completed_washes)
                 VALUES (?, 0, 0)'
            );
            $stmt->execute([$customerId]);

            $stmt = $pdo->prepare(
                'SELECT id, customer_id, stamps, total_completed_washes, created_at, updated_at
                 FROM loyalty_cards
                 WHERE id = ?'
            );
            $stmt->execute([$pdo->lastInsertId()]);
            $card = $stmt->fetch();
        }

        $card['id']                     = (int)$card['id'];
        $card['customer_id']            = (int)$card['customer_id'];
        $card['stamps']                 = (int)$card['stamps'];
        $card['total_completed_washes'] = (int)$card['total_completed_washes'];

        return $card;
    }

    // The reward the customer has unlocked but has not claimed yet, if any.
    function getPendingReward(PDO $pdo, int $cardId): ?array
    {
        $stmt = $pdo->prepare(
            "SELECT id, customer_id, loyalty_card_id, reward_code, cycle,
                    reward_status, unlocked_at, redeemed_at
             FROM loyalty_rewards
             WHERE loyalty_card_id = ? AND reward_status = 'Unlocked'
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->execute([$cardId]);
        $reward = $stmt->fetch();

        return $reward ?: null;
    }

    function lastLoyaltyReward(PDO $pdo, int $cardId): ?array
    {
        $stmt = $pdo->prepare(
            "SELECT id, customer_id, loyalty_card_id, reward_code, cycle,
                    reward_status, unlocked_at, redeemed_at
             FROM loyalty_rewards
             WHERE loyalty_card_id = ?
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->execute([$cardId]);
        $reward = $stmt->fetch();

        return $reward ?: null;
    }

    // Which 10-wash cycle the card is on right now.
    function loyaltyCurrentCycle(PDO $pdo, int $cardId): int
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM loyalty_rewards WHERE loyalty_card_id = ?');
        $stmt->execute([$cardId]);

        return max(1, (int)$stmt->fetchColumn() + 1);
    }

    // Creates the reward record when a card reaches the target. The card itself
    // is left full: it only resets when staff mark the reward Redeemed.
    function unlockLoyaltyReward(PDO $pdo, array $card): array
    {
        $cycle = loyaltyCurrentCycle($pdo, $card['id']);
        $code  = loyaltyRewardCode($pdo);

        $stmt = $pdo->prepare(
            "INSERT INTO loyalty_rewards (customer_id, loyalty_card_id, reward_code, cycle, reward_status)
             VALUES (?, ?, ?, ?, 'Unlocked')"
        );
        $stmt->execute([$card['customer_id'], $card['id'], $code, $cycle]);

        $stmt = $pdo->prepare(
            "SELECT id, customer_id, loyalty_card_id, reward_code, cycle,
                    reward_status, unlocked_at, redeemed_at
             FROM loyalty_rewards WHERE id = ?"
        );
        $stmt->execute([$pdo->lastInsertId()]);

        return $stmt->fetch();
    }

    // A short, unique reference the customer can show at the counter.
    function loyaltyRewardCode(PDO $pdo): string
    {
        $stmt = $pdo->prepare('SELECT 1 FROM loyalty_rewards WHERE reward_code = ? LIMIT 1');

        // Six random characters, retried in the very unlikely case of a clash
        for ($try = 0; $try < 5; $try++) {
            $code = 'RW-' . strtoupper(bin2hex(random_bytes(3)));
            $stmt->execute([$code]);
            if ($stmt->fetchColumn() === false) {
                return $code;
            }
        }

        // Fall back to a sequential reference rather than failing the order
        return 'RW-' . str_pad((string)($pdo->lastInsertId() + 1), 6, '0', STR_PAD_LEFT);
    }

    function recordLoyaltyStamp(PDO $pdo, int $orderId, array $card, int $cycle, int $stampsAdded): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO loyalty_stamps (customer_id, order_id, loyalty_card_id, cycle, stamps_added)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$card['customer_id'], $orderId, $card['id'], $cycle, $stampsAdded]);
    }

    // Gives one loyalty stamp for one order.
    //
    // Returns a short array describing what happened, so the caller can show
    // the right message. Must run inside the caller's transaction, together
    // with the order status change it belongs to.
    //
    //   stamped  true when the card went up by 1
    //   reason   stamped | already_stamped | reward_pending | not_qualifying
    //   card     the card after the change
    //   reward   the reward that is waiting, or was just created
    function grantLoyaltyStamp(PDO $pdo, array $order): array
    {
        $orderId     = (int)$order['id'];
        $customerId  = (int)$order['customer_id'];
        $orderStatus = (string)$order['order_status'];

        // A cancelled load, or one still in the wash, never earns a stamp
        if (!loyaltyQualifies($orderStatus)) {
            return [
                'stamped' => false,
                'reason'  => 'not_qualifying',
                'card'    => null,
                'reward'  => null,
            ];
        }

        $card     = getLoyaltyCard($pdo, $customerId);
        $required = loyaltyRequiredStamps();

        // The same order is never counted twice. The unique key on
        // loyalty_stamps.order_id backs this up at the database level, so two
        // staff members saving the same load at the same moment still only
        // produce one stamp.
        $stmt = $pdo->prepare('SELECT id FROM loyalty_stamps WHERE order_id = ? LIMIT 1');
        $stmt->execute([$orderId]);
        if ($stmt->fetch()) {
            return [
                'stamped' => false,
                'reason'  => 'already_stamped',
                'card'    => $card,
                'reward'  => null,
            ];
        }

        $pending = getPendingReward($pdo, $card['id']);
        $cycle   = loyaltyCurrentCycle($pdo, $card['id']);

        // Every completed wash is counted, even when its stamp has to wait
        $stmt = $pdo->prepare(
            'UPDATE loyalty_cards
             SET total_completed_washes = total_completed_washes + 1
             WHERE id = ?'
        );
        $stmt->execute([$card['id']]);
        $card['total_completed_washes']++;

        // A reward that has not been claimed yet keeps the card full, so this
        // wash is counted but its stamp waits for the redemption.
        if ($pending) {
            recordLoyaltyStamp($pdo, $orderId, $card, $cycle, 0);

            return [
                'stamped' => false,
                'reason'  => 'reward_pending',
                'card'    => $card,
                'reward'  => $pending,
            ];
        }

        $wasFull = $card['stamps'] >= $required;
        $stamps  = min($card['stamps'] + 1, $required);

        recordLoyaltyStamp($pdo, $orderId, $card, $cycle, 1);

        $stmt = $pdo->prepare('UPDATE loyalty_cards SET stamps = ? WHERE id = ?');
        $stmt->execute([$stamps, $card['id']]);
        $card['stamps'] = $stamps;

        // The promotion is complete. Create the reward record, but leave the
        // card at 10/10 so the customer can see what they earned.
        $reward = null;
        if (!$wasFull && $stamps >= $required) {
            $reward = unlockLoyaltyReward($pdo, $card);
        }

        return [
            'stamped' => true,
            'reason'  => 'stamped',
            'card'    => $card,
            'reward'  => $reward,
        ];
    }

    // Staff marks an unlocked reward as claimed. This is the only thing that
    // puts the card back to 0 for the next cycle.
    function redeemLoyaltyReward(PDO $pdo, int $rewardId): array
    {
        $stmt = $pdo->prepare(
            "SELECT id, customer_id, loyalty_card_id, reward_code, cycle,
                    reward_status, unlocked_at, redeemed_at
             FROM loyalty_rewards
             WHERE id = ?
             FOR UPDATE"
        );
        $stmt->execute([$rewardId]);
        $reward = $stmt->fetch();

        if (!$reward) {
            throw new RuntimeException('Reward not found.');
        }

        if ($reward['reward_status'] === 'Redeemed') {
            throw new RuntimeException('This reward was already redeemed.');
        }

        $stmt = $pdo->prepare(
            "UPDATE loyalty_rewards
             SET reward_status = 'Redeemed', redeemed_at = NOW()
             WHERE id = ? AND reward_status = 'Unlocked'"
        );
        $stmt->execute([$rewardId]);

        // The promotion is now spent, so the card starts again from zero
        $stmt = $pdo->prepare('UPDATE loyalty_cards SET stamps = 0 WHERE id = ?');
        $stmt->execute([$reward['loyalty_card_id']]);

        return [
            'reward'  => $reward,
            'card_id' => (int)$reward['loyalty_card_id'],
            'stamps'  => 0,
            'cycle'   => (int)$reward['cycle'] + 1,
        ];
    }

    // Everything the customer portal and the staff dashboard need to describe
    // a card, without either side having to know the rules.
    function loyaltyProgress(PDO $pdo, array $card): array
    {
        $config   = loyaltyConfig();
        $required = loyaltyRequiredStamps();
        $stamps   = (int)$card['stamps'];

        $pending = getPendingReward($pdo, $card['id']);
        $last    = lastLoyaltyReward($pdo, $card['id']);

        $stmt = $pdo->prepare(
            "SELECT
                COALESCE(SUM(reward_status = 'Unlocked'), 0) AS unlocked,
                COALESCE(SUM(reward_status = 'Redeemed'), 0) AS redeemed
             FROM loyalty_rewards
             WHERE loyalty_card_id = ?"
        );
        $stmt->execute([$card['id']]);
        $counts   = $stmt->fetch();
        $unlocked = (int)($counts['unlocked'] ?? 0);
        $redeemed = (int)($counts['redeemed'] ?? 0);

        $remaining = max(0, $required - $stamps);

        // The card only counts as finished while a reward is waiting to be claimed
        $status = $pending ? 'Unlocked' : ($redeemed > 0 ? 'Redeemed' : 'In Progress');

        // While a reward is unclaimed the card is still on that reward's cycle,
        // otherwise it has moved on to the next one
        $cycle = $pending
            ? (int)$pending['cycle']
            : loyaltyCurrentCycle($pdo, $card['id']);

        return [
            'card_id'                => (int)$card['id'],
            'stamps'                 => $stamps,
            'stamps_required'        => $required,
            'total_completed_washes' => (int)$card['total_completed_washes'],
            'remaining'              => $remaining,
            'progress_percent'       => $required > 0 ? min(100, (int)round($stamps * 100 / $required)) : 0,
            'reward_status'          => $status,
            'reward_name'            => $config['reward_name'],
            'card_title'             => $config['card_title'],
            'cycle'                  => $cycle,
            'rewards_unlocked'       => $unlocked + $redeemed,
            'rewards_claimed'        => $redeemed,
            'pending_reward'         => $pending ? formatLoyaltyReward($pending) : null,
            'latest_reward'          => $last ? formatLoyaltyReward($last) : null,
            'message'                => loyaltyMessage($status, $stamps, $required, $remaining, $config),
        ];
    }

    function formatLoyaltyReward(array $reward): array
    {
        return [
            'id'            => (int)$reward['id'],
            'reward_code'   => $reward['reward_code'],
            'cycle'         => (int)$reward['cycle'],
            'reward_status' => $reward['reward_status'],
            'unlocked_at'   => $reward['unlocked_at'],
            'redeemed_at'   => $reward['redeemed_at'],
        ];
    }

    // The progress message shown to the customer above the card.
    function loyaltyMessage(string $status, int $stamps, int $required, int $remaining, array $config): string
    {
        if ($status === 'Unlocked') {
            return "\u{1F389} Reward Unlocked! You have completed $stamps washes and unlocked your "
                 . $config['reward_name'] . ' reward. Show this card at the shop to claim it.';
        }

        $washText = $remaining === 1 ? 'wash' : 'washes';

        return "You have $stamps/$required washes! Only $remaining more $washText to unlock your reward.";
    }
