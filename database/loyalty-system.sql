-- =============================================================
-- Digital Loyalty Card
--
-- The rule: 1 completed wash = 1 loyalty stamp.
-- 10 completed washes = 1 promotional reward.
--
-- Run this once against laundry_shop_db. It is safe to run again.
-- =============================================================

-- One card per customer.
--   stamps                 = progress in the cycle that is running now.
--                            Stays at 10 while a reward waits to be claimed,
--                            then goes back to 0 after the reward is redeemed.
--   total_completed_washes = every wash ever counted. Never reset.
CREATE TABLE IF NOT EXISTS loyalty_cards (
  id                      INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  customer_id             INT UNSIGNED     NOT NULL,
  stamps                  INT UNSIGNED     NOT NULL DEFAULT 0,
  total_completed_washes  INT UNSIGNED     NOT NULL DEFAULT 0,
  created_at              TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at              TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_loyalty_cards_customer (customer_id),
  CONSTRAINT fk_loyalty_cards_customer FOREIGN KEY (customer_id)
      REFERENCES customers (id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- A stamp ledger, one row per order that was counted.
-- The UNIQUE key on order_id is the hard guarantee that one order can never
-- give a second stamp, even if two staff members save the same load twice.
-- stamps_added is 0 when the wash was counted but its stamp had to wait,
-- because a reward from an earlier cycle was still not redeemed.
CREATE TABLE IF NOT EXISTS loyalty_stamps (
  id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  customer_id      INT UNSIGNED  NOT NULL,
  order_id         INT UNSIGNED  NOT NULL,
  loyalty_card_id  INT UNSIGNED  NOT NULL,
  cycle            INT UNSIGNED  NOT NULL DEFAULT 1,
  stamps_added     TINYINT UNSIGNED NOT NULL DEFAULT 1,
  created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_loyalty_stamps_order (order_id),
  KEY fk_loyalty_stamps_customer (customer_id),
  KEY fk_loyalty_stamps_card (loyalty_card_id),
  KEY fk_loyalty_stamps_created (created_at),
  CONSTRAINT fk_loyalty_stamps_customer FOREIGN KEY (customer_id)
      REFERENCES customers (id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_loyalty_stamps_order FOREIGN KEY (order_id)
      REFERENCES orders (id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_loyalty_stamps_card FOREIGN KEY (loyalty_card_id)
      REFERENCES loyalty_cards (id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- One row per reward the customer has earned. The card is not reset when a
-- reward is unlocked; staff mark the reward Redeemed when the customer claims
-- it, and that is when the card goes back to 0.
CREATE TABLE IF NOT EXISTS loyalty_rewards (
  id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  customer_id      INT UNSIGNED  NOT NULL,
  loyalty_card_id  INT UNSIGNED  NOT NULL,
  reward_code      VARCHAR(20)   NOT NULL COMMENT 'Redemption reference given to the customer',
  cycle            INT UNSIGNED  NOT NULL DEFAULT 1,
  reward_status    ENUM('Unlocked','Redeemed') NOT NULL DEFAULT 'Unlocked',
  unlocked_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  redeemed_at      DATETIME      NULL DEFAULT NULL,
  created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_loyalty_rewards_code (reward_code),
  UNIQUE KEY uq_loyalty_rewards_cycle (loyalty_card_id, cycle),
  KEY fk_loyalty_rewards_customer (customer_id),
  KEY fk_loyalty_rewards_status (reward_status),
  CONSTRAINT fk_loyalty_rewards_customer FOREIGN KEY (customer_id)
      REFERENCES customers (id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_loyalty_rewards_card FOREIGN KEY (loyalty_card_id)
      REFERENCES loyalty_cards (id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
