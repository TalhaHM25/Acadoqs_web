-- Migration: 017_paymongo_checkout.sql
-- PayMongo hosted checkout support for payment_records

USE document_request_system;

ALTER TABLE payment_records
  MODIFY COLUMN payment_method ENUM('gcash','paymongo') NOT NULL DEFAULT 'paymongo',
  ADD COLUMN paymongo_checkout_session_id VARCHAR(100) NULL AFTER payment_method_id,
  ADD COLUMN paymongo_checkout_url VARCHAR(1000) NULL AFTER paymongo_checkout_session_id,
  ADD COLUMN paymongo_payment_intent_id VARCHAR(100) NULL AFTER paymongo_checkout_url,
  ADD COLUMN gateway_payload TEXT NULL AFTER paymongo_payment_intent_id,
  ADD UNIQUE INDEX uq_pr_paymongo_checkout_session (paymongo_checkout_session_id);
