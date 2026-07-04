-- Performance indexes for high-traffic dashboards, order lists, reports, notifications, and payments.
-- Each block is idempotent: if the named index already exists on the table, it becomes a no-op.

SET @index_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'orders'
      AND index_name = 'idx_orders_shop_created'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE orders ADD INDEX idx_orders_shop_created (shop_id, created_at, order_id)',
    'SELECT ''idx_orders_shop_created already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'orders'
      AND index_name = 'idx_orders_shop_status_created'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE orders ADD INDEX idx_orders_shop_status_created (shop_id, order_status, created_at)',
    'SELECT ''idx_orders_shop_status_created already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'orders'
      AND index_name = 'idx_orders_customer_created'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE orders ADD INDEX idx_orders_customer_created (customer_id, created_at, order_id)',
    'SELECT ''idx_orders_customer_created already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'orders'
      AND index_name = 'idx_orders_customer_status_created'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE orders ADD INDEX idx_orders_customer_status_created (customer_id, order_status, created_at)',
    'SELECT ''idx_orders_customer_status_created already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'notifications'
      AND index_name = 'idx_notifications_user_read_created'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE notifications ADD INDEX idx_notifications_user_read_created (user_id, is_read, created_at)',
    'SELECT ''idx_notifications_user_read_created already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'notifications'
      AND index_name = 'idx_notifications_user_created'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE notifications ADD INDEX idx_notifications_user_created (user_id, created_at)',
    'SELECT ''idx_notifications_user_created already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'payments'
      AND index_name = 'idx_payments_order_created'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE payments ADD INDEX idx_payments_order_created (order_id, created_at, payment_id)',
    'SELECT ''idx_payments_order_created already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'payments'
      AND index_name = 'idx_payments_customer_status'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE payments ADD INDEX idx_payments_customer_status (customer_id, payment_status, verification_status)',
    'SELECT ''idx_payments_customer_status already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'payments'
      AND index_name = 'idx_payments_status_created'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE payments ADD INDEX idx_payments_status_created (verification_status, created_at)',
    'SELECT ''idx_payments_status_created already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'uploaded_files'
      AND index_name = 'idx_uploaded_files_order_file'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE uploaded_files ADD INDEX idx_uploaded_files_order_file (order_id, file_id)',
    'SELECT ''idx_uploaded_files_order_file already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'print_shops'
      AND index_name = 'idx_print_shops_owner'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE print_shops ADD INDEX idx_print_shops_owner (owner_id)',
    'SELECT ''idx_print_shops_owner already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'print_shops'
      AND index_name = 'idx_print_shops_permit_status'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE print_shops ADD INDEX idx_print_shops_permit_status (permit_status, created_at)',
    'SELECT ''idx_print_shops_permit_status already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'shop_services'
      AND index_name = 'idx_shop_services_shop_available'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE shop_services ADD INDEX idx_shop_services_shop_available (shop_id, is_available)',
    'SELECT ''idx_shop_services_shop_available already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
