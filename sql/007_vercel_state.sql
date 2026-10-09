CREATE TABLE IF NOT EXISTS app_sessions (
    session_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    payload MEDIUMBLOB NOT NULL,
    last_activity INT UNSIGNED NOT NULL,
    KEY idx_app_sessions_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_rate_limits (
    rate_key CHAR(64) CHARACTER SET ascii NOT NULL PRIMARY KEY,
    window_started_at INT UNSIGNED NOT NULL,
    attempt_count TINYINT UNSIGNED NOT NULL,
    KEY idx_login_rate_limits_window (window_started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
