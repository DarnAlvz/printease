CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255),
    ip_address VARCHAR(45),
    attempts INT DEFAULT 1,
    last_attempt DATETIME,
    blocked_until DATETIME NULL
);