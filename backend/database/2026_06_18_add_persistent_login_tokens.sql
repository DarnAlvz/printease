CREATE TABLE IF NOT EXISTS user_remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    selector CHAR(36) NOT NULL,
    validator_hash CHAR(64) NOT NULL,
    auth_provider VARCHAR(20) NOT NULL DEFAULT 'password',
    remember_duration_days INT NOT NULL DEFAULT 1,
    expires_at DATETIME NOT NULL,
    last_used_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_remember_selector (selector),
    KEY idx_user_remember_user_id (user_id),
    KEY idx_user_remember_expires_at (expires_at),
    CONSTRAINT fk_user_remember_tokens_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE
);

SET @auth_provider_column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_remember_tokens'
      AND COLUMN_NAME = 'auth_provider'
);

SET @add_auth_provider_sql = IF(
    @auth_provider_column_exists = 0,
    'ALTER TABLE user_remember_tokens ADD COLUMN auth_provider VARCHAR(20) NOT NULL DEFAULT ''password'' AFTER validator_hash',
    'SELECT 1'
);

PREPARE add_auth_provider_stmt FROM @add_auth_provider_sql;
EXECUTE add_auth_provider_stmt;
DEALLOCATE PREPARE add_auth_provider_stmt;

SET @remember_duration_column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_remember_tokens'
      AND COLUMN_NAME = 'remember_duration_days'
);

SET @add_remember_duration_sql = IF(
    @remember_duration_column_exists = 0,
    'ALTER TABLE user_remember_tokens ADD COLUMN remember_duration_days INT NOT NULL DEFAULT 7 AFTER auth_provider',
    'SELECT 1'
);

PREPARE add_remember_duration_stmt FROM @add_remember_duration_sql;
EXECUTE add_remember_duration_stmt;
DEALLOCATE PREPARE add_remember_duration_stmt;
