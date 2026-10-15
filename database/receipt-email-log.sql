-- =============================================================
-- Receipt email log
--
-- Records every attempt to email a receipt to a customer. The Gmail SMTP
-- server does not give a real delivery receipt, so we keep two separate
-- fields:
--   sending_status  = whether our server accepted/submitted the message
--                     ('submitted') or the send failed ('failed').
--   delivery_status = confirmed delivery, when/if we ever have it.
--                     SMTP alone cannot confirm delivery, so it stays
--                     'unknown' for now.
--
-- Run this once against laundry_shop_db. It is safe to run again.
-- =============================================================

CREATE TABLE IF NOT EXISTS email_logs (
  id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  order_id         INT UNSIGNED  NOT NULL,
  receipt_number   VARCHAR(30)   NOT NULL,
  recipient_email  VARCHAR(100)  NOT NULL,
  sending_status   ENUM('submitted','failed') NOT NULL,
  delivery_status  ENUM('unknown','delivered','bounced') NOT NULL DEFAULT 'unknown',
  error_message    TEXT          NULL DEFAULT NULL,
  staff_id         INT UNSIGNED  NULL DEFAULT NULL,
  created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_email_logs_order (order_id),
  KEY idx_email_logs_created (created_at),
  CONSTRAINT fk_email_logs_order FOREIGN KEY (order_id)
      REFERENCES orders (id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
