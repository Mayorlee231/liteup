-- Run once against liteup_db after backing up the database.
ALTER TABLE transactions
    MODIFY type ENUM('deposit', 'utility_purchase', 'refund') NOT NULL;
