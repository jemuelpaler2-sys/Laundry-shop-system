-- =============================================================
-- database/paymongo-online-payments.sql
--
-- Online payment (PayMongo) support:
--   1. payments        -> gateway + unique transaction reference + created_at
--   2. email_logs      -> link a receipt email to a payment, and stop
--                         duplicate automatic receipts for one payment
--   3. online_payments -> one row per PayMongo Checkout Session (pending/paid)
--   4. payment_webhook_events -> raw webhook log used for idempotency
--
-- Run once against laundry_shop_db.
-- =============================================================

-- 1. payments -------------------------------------------------
-- Allow more payment methods than the original ('Cash','GCash','Other') so we
-- can store what the gateway reports (Card, Maya, GrabPay, QR Ph, ...).
ALTER TABLE payments
  MODIFY COLUMN payment_method VARCHAR(30) NOT NULL DEFAULT 'Cash';

-- Gateway name + the gateway's own transaction id. The unique key is what
-- stops one gateway payment from ever being recorded twice.
ALTER TABLE payments
  ADD COLUMN gateway VARCHAR(30) NULL DEFAULT NULL AFTER payment_method,
  ADD COLUMN transaction_reference VARCHAR(120) NULL DEFAULT NULL AFTER gateway,
  ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD UNIQUE KEY uq_payments_reference (transaction_reference);

-- 2. email_logs ----------------------------------------------
-- payment_id is set only for automatic (gateway-triggered) receipts. The unique
-- key on payment_id guarantees a payment can only ever produce ONE automatic
-- receipt row; retries UPDATE that row instead of inserting a new one.
-- Manual (staff) resends leave payment_id NULL, so they are never blocked.
ALTER TABLE email_logs
  ADD COLUMN payment_id INT UNSIGNED NULL DEFAULT NULL AFTER order_id,
  ADD COLUMN kind ENUM('manual','automatic') NOT NULL DEFAULT 'manual' AFTER recipient_email,
  ADD COLUMN attempts INT UNSIGNED NOT NULL DEFAULT 1 AFTER error_message,
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  ADD UNIQUE KEY uq_email_logs_payment (payment_id),
  ADD CONSTRAINT fk_email_logs_payment FOREIGN KEY (payment_id)
      REFERENCES payments (id) ON UPDATE CASCADE ON DELETE SET NULL;

-- 3. online_payments -----------------------------------------
-- One row for every Checkout Session we create. `reference` is the PayMongo
-- checkout session id (cs_...). We only trust the webhook to move it to 'paid'.
CREATE TABLE IF NOT EXISTS online_payments (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id              INT UNSIGNED NOT NULL,
  gateway               VARCHAR(30)  NOT NULL DEFAULT 'paymongo',
  reference             VARCHAR(120) NOT NULL,
  checkout_url          VARCHAR(255) NULL DEFAULT NULL,
  amount                DECIMAL(10,2) NOT NULL,
  currency              CHAR(3)      NOT NULL DEFAULT 'PHP',
  status                ENUM('pending','paid','failed','expired','cancelled') NOT NULL DEFAULT 'pending',
  transaction_reference VARCHAR(120) NULL DEFAULT NULL,
  payment_method        VARCHAR(30)  NULL DEFAULT NULL,
  payment_id            INT UNSIGNED NULL DEFAULT NULL,
  livemode              TINYINT(1)   NOT NULL DEFAULT 0,
  created_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  paid_at               DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_online_payments_reference (reference),
  KEY idx_online_payments_order (order_id),
  CONSTRAINT fk_online_payments_order FOREIGN KEY (order_id)
      REFERENCES orders (id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_online_payments_payment FOREIGN KEY (payment_id)
      REFERENCES payments (id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. payment_webhook_events ----------------------------------
-- Every webhook is recorded once. The unique key on (gateway, event_id) makes
-- replayed deliveries a no-op before any money is touched.
CREATE TABLE IF NOT EXISTS payment_webhook_events (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  gateway           VARCHAR(30)  NOT NULL DEFAULT 'paymongo',
  event_id          VARCHAR(120) NOT NULL,
  event_type        VARCHAR(80)  NOT NULL DEFAULT '',
  reference         VARCHAR(120) NULL DEFAULT NULL,
  processing_status ENUM('processed','ignored','failed') NOT NULL DEFAULT 'processed',
  message           TEXT NULL DEFAULT NULL,
  payload           MEDIUMTEXT NULL DEFAULT NULL,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gateway_event (gateway, event_id),
  KEY idx_webhook_reference (reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
