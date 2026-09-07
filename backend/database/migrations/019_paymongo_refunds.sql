-- Store PayMongo payment/refund IDs for document request refund handling.

ALTER TABLE payment_records
  ADD COLUMN paymongo_payment_id VARCHAR(100) NULL AFTER paymongo_payment_intent_id,
  ADD COLUMN paymongo_refund_id VARCHAR(100) NULL AFTER paymongo_payment_id,
  ADD COLUMN refund_payload TEXT NULL AFTER paymongo_refund_id,
  ADD COLUMN refunded_at DATETIME NULL AFTER refund_payload,
  ADD INDEX idx_pr_paymongo_payment_id (paymongo_payment_id),
  ADD INDEX idx_pr_paymongo_refund_id (paymongo_refund_id);
