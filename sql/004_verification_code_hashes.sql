-- Run once against liteup_db after taking a backup.
-- Invalidate existing codes because their old SHA-256 representation is being retired.
UPDATE verification_codes SET used_at = NOW() WHERE used_at IS NULL;
ALTER TABLE verification_codes MODIFY code_hash VARCHAR(255) NOT NULL;
