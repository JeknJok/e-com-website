CREATE TABLE IF NOT EXISTS RateLimits (
    bucket_key CHAR(64) CHARACTER SET ascii PRIMARY KEY,
    hits INT UNSIGNED NOT NULL,
    expires_at BIGINT UNSIGNED NOT NULL,
    KEY limiter_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS RecoveryCodes (
    token_hash CHAR(64) CHARACTER SET ascii PRIMARY KEY,
    account_kind VARCHAR(8) NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    expires_at DATETIME NOT NULL,
    KEY recovery_account (account_kind, account_id),
    KEY recovery_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS SecurityEvents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    event_name VARCHAR(64) NOT NULL,
    actor_kind VARCHAR(16) NOT NULL,
    actor_id INT UNSIGNED NULL,
    target_id INT UNSIGNED NULL,
    outcome VARCHAR(8) NOT NULL,
    request_id CHAR(24) CHARACTER SET ascii NOT NULL,
    KEY events_time (occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ErasureLedger (
    customer_id INT UNSIGNED PRIMARY KEY,
    erased_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
