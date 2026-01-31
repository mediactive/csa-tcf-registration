-- Migration: Add email confirmation tracking columns
-- Run this SQL on the server

ALTER TABLE tcf_registrations 
ADD COLUMN transaction_id VARCHAR(100) NULL AFTER payment_confirmed_at,
ADD COLUMN confirmation_email_sent TINYINT(1) DEFAULT 0 AFTER transaction_id,
ADD COLUMN confirmation_email_sent_at DATETIME NULL AFTER confirmation_email_sent;
