-- Allows super admins to disable a print shop without deleting it.
-- Run this only if print_shops.permit_status is currently an ENUM-constrained column.
ALTER TABLE print_shops
    MODIFY permit_status ENUM('pending', 'verified', 'rejected', 'disabled') NOT NULL DEFAULT 'pending';
