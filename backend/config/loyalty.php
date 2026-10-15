<?php
// Loyalty card rules for the shop. Change the numbers here, no other file
// needs to change.
//
// The rule the shop runs on: 1 completed wash = 1 loyalty stamp, and
// stamps_required completed washes = 1 promotional reward.
return [
    // Completed washes a customer needs before a reward is unlocked.
    'stamps_required' => 10,

    // Heading printed on the digital card.
    'card_title' => 'Wash & Fold Loyalty Card',

    // What the customer receives once the promotion is complete.
    'reward_name' => 'Free Wash & Fold',

    // Order statuses that count as a completed wash. Cancelled loads and
    // loads that are still being processed are deliberately not in this list.
    'qualifying_statuses' => ['ready_for_pickup', 'completed'],
];
