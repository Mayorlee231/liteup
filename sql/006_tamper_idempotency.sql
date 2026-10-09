-- Run once against liteup_db after backing up the database.
ALTER TABLE tamper_codes
    ADD COLUMN idempotency_key CHAR(64) NULL,
    ADD UNIQUE KEY uq_tamper_codes_idempotency (idempotency_key);
