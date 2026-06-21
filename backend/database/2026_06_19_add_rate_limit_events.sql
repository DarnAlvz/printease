CREATE TABLE IF NOT EXISTS rate_limit_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(80) NOT NULL,
    identifier VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempt_count INT NOT NULL DEFAULT 0,
    window_started_at DATETIME NOT NULL,
    last_attempt_at DATETIME NOT NULL,
    blocked_until DATETIME NULL,
    UNIQUE KEY rate_limit_unique (action, identifier, ip_address),
    KEY rate_limit_action_identifier (action, identifier),
    KEY rate_limit_ip (ip_address),
    KEY rate_limit_blocked_until (blocked_until)
);
