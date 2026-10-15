<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Loyalty.php';
require_once __DIR__ . '/../staff/auth.php';

requireStaff();

$search  = trim($_GET['search'] ?? '');
$status  = $_GET['status'] ?? 'all';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

$where  = [];
$params = [];

if ($search !== '') {
    // Escape the LIKE wildcards typed by the user
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $where[] = "(c.first_name LIKE ? OR c.last_name LIKE ?
                 OR CONCAT(c.first_name, ' ', c.last_name) LIKE ?
                 OR c.email LIKE ? OR c.phone LIKE ? OR c.customer_code LIKE ?)";
    array_push($params, $like, $like, $like, $like, $like, $like);
}

switch ($status) {
    // A reward is unlocked and the customer has not claimed it yet
    case 'pending':
        $where[] = "EXISTS (SELECT 1 FROM loyalty_rewards r
                             WHERE r.customer_id = c.id AND r.reward_status = 'Unlocked')";
        break;

    // The customer has already claimed at least one reward
    case 'redeemed':
        $where[] = "EXISTS (SELECT 1 FROM loyalty_rewards r
                             WHERE r.customer_id = c.id AND r.reward_status = 'Redeemed')";
        break;

    // Still working towards the first reward
    case 'in_progress':
        $where[] = "COALESCE(lc.stamps, 0) > 0
                    AND NOT EXISTS (SELECT 1 FROM loyalty_rewards r
                                    WHERE r.customer_id = c.id AND r.reward_status = 'Unlocked')";
        break;

    // Never earned a stamp
    case 'no_card':
        $where[] = 'lc.id IS NULL OR COALESCE(lc.total_completed_washes, 0) = 0';
        break;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $required = loyaltyRequiredStamps();
    $config   = loyaltyConfig();

    // Total number of matching customers
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM customers c
         LEFT JOIN loyalty_cards lc ON lc.customer_id = c.id
         $whereSql"
    );
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    // One page of customers with their card
    $sql = "SELECT c.id, c.customer_code, c.first_name, c.last_name, c.email, c.status,
                   lc.id AS card_id,
                   COALESCE(lc.stamps, 0) AS stamps,
                   COALESCE(lc.total_completed_washes, 0) AS total_completed_washes
            FROM customers c
            LEFT JOIN loyalty_cards lc ON lc.customer_id = c.id
            $whereSql
            ORDER BY c.first_name ASC, c.last_name ASC
            LIMIT ? OFFSET ?";

    $stmt = $pdo->prepare($sql);
    $i = 1;
    foreach ($params as $p) {
        $stmt->bindValue($i++, $p);
    }
    $stmt->bindValue($i++, $perPage, PDO::PARAM_INT);
    $stmt->bindValue($i, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $customers = $stmt->fetchAll();

    // Reward totals for just the customers on this page
    $rewardSummary = [];
    if ($customers) {
        $ids = array_column($customers, 'id');

        $stmt = $pdo->prepare(
            "SELECT customer_id,
                    COALESCE(SUM(reward_status = 'Unlocked'), 0) AS rewards_pending,
                    COALESCE(SUM(reward_status = 'Redeemed'), 0) AS rewards_redeemed,
                    MAX(unlocked_at) AS last_unlocked_at,
                    MAX(redeemed_at) AS last_redeemed_at,
                    MAX(CASE WHEN reward_status = 'Unlocked' THEN id END)         AS pending_reward_id,
                    MAX(CASE WHEN reward_status = 'Unlocked' THEN reward_code END) AS pending_reward_code,
                    MAX(CASE WHEN reward_status = 'Unlocked' THEN unlocked_at END) AS pending_unlocked_at
             FROM loyalty_rewards
             WHERE customer_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
             GROUP BY customer_id"
        );
        $stmt->execute($ids);

        foreach ($stmt->fetchAll() as $row) {
            $rewardSummary[(int)$row['customer_id']] = $row;
        }
    }

    foreach ($customers as &$c) {
        $c['id']                      = (int)$c['id'];
        $c['card_id']                 = $c['card_id'] !== null ? (int)$c['card_id'] : null;
        $c['stamps']                  = (int)$c['stamps'];
        $c['total_completed_washes']  = (int)$c['total_completed_washes'];
        $c['stamps_required']         = $required;
        $c['remaining']               = max(0, $required - $c['stamps']);

        $summary = $rewardSummary[$c['id']] ?? null;

        $c['rewards_unlocked']   = $summary ? (int)$summary['rewards_pending'] + (int)$summary['rewards_redeemed'] : 0;
        $c['rewards_redeemed']   = $summary ? (int)$summary['rewards_redeemed'] : 0;
        $c['rewards_pending']    = $summary ? (int)$summary['rewards_pending'] : 0;
        $c['last_unlocked_at']   = $summary['last_unlocked_at']   ?? null;
        $c['last_redeemed_at']   = $summary['last_redeemed_at']   ?? null;
        $c['pending_reward_id']  = $summary && $summary['pending_reward_id'] !== null
            ? (int)$summary['pending_reward_id'] : null;
        $c['pending_reward_code']= $summary['pending_reward_code'] ?? null;

        $c['reward_status'] = $c['rewards_pending'] > 0
            ? 'Unlocked'
            : ($c['rewards_redeemed'] > 0 ? 'Redeemed' : 'In Progress');
    }
    unset($c);

    // Headline numbers for the top of the page
    $stmt = $pdo->query(
        "SELECT
            (SELECT COUNT(*) FROM loyalty_cards) AS cards,
            (SELECT COUNT(*) FROM loyalty_rewards WHERE reward_status = 'Unlocked') AS pending,
            (SELECT COUNT(*) FROM loyalty_rewards WHERE reward_status = 'Redeemed') AS redeemed"
    );
    $totals = $stmt->fetch();

    echo json_encode([
        'success'     => true,
        'customers'   => $customers,
        'page'        => $page,
        'per_page'    => $perPage,
        'total'       => $total,
        'total_pages' => max(1, (int)ceil($total / $perPage)),
        'required'    => $required,
        'reward_name' => $config['reward_name'],
        'summary'     => [
            'cards'            => (int)$totals['cards'],
            'rewards_pending'  => (int)$totals['pending'],
            'rewards_redeemed' => (int)$totals['redeemed'],
        ],
    ]);
} catch (PDOException $e) {
    error_log('Loyalty list failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load the loyalty cards.']);
}
