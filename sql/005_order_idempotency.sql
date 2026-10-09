-- Run once against liteup_db after backing up the database.
ALTER TABLE airtime_data_orders
    ADD COLUMN idempotency_key CHAR(64) NULL,
    ADD UNIQUE KEY uq_airtime_data_idempotency (idempotency_key);

ALTER TABLE electricity_orders
    ADD COLUMN idempotency_key CHAR(64) NULL,
    ADD UNIQUE KEY uq_electricity_idempotency (idempotency_key);
