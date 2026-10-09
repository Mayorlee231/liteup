-- Run once against liteup_db after taking a backup.
ALTER TABLE users
    ADD COLUMN email_verified_at DATETIME NULL AFTER password;

CREATE TABLE verification_codes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    purpose ENUM('registration', 'login') NOT NULL,
    code_hash CHAR(64) NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_verification_user_purpose (user_id, purpose, used_at, expires_at),
    CONSTRAINT fk_verification_codes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE trusted_devices (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_trusted_device_token (token_hash),
    KEY idx_trusted_device_user (user_id, expires_at),
    CONSTRAINT fk_trusted_devices_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
