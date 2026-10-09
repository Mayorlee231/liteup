-- Run once against liteup_db after taking a backup.
-- The application uses wallets as the canonical wallet table.

INSERT INTO wallets (user_id, balance)
SELECT u.id, 0.00
FROM users AS u
LEFT JOIN wallets AS w ON w.user_id = u.id
WHERE w.user_id IS NULL;

ALTER TABLE wallets
    ADD UNIQUE KEY uq_wallets_user_id (user_id);
