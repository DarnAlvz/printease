-- Allows super admins to deactivate users without deleting accounts.
-- Run this only if users.account_status is currently an ENUM-constrained column.
ALTER TABLE users
    MODIFY account_status ENUM('incomplete', 'pending', 'verified', 'rejected', 'inactive') NOT NULL DEFAULT 'incomplete';
