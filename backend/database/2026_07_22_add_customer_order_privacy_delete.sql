SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'orders'
      AND column_name = 'customer_deleted_at'
);

SET @alter_orders_customer_deleted := IF(
    @column_exists = 0,
    'ALTER TABLE orders ADD COLUMN customer_deleted_at TIMESTAMP NULL DEFAULT NULL AFTER order_status',
    'SELECT 1'
);

PREPARE stmt FROM @alter_orders_customer_deleted;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'orders'
      AND index_name = 'idx_orders_customer_privacy_status_created'
);

SET @add_orders_customer_privacy_index := IF(
    @index_exists = 0,
    'ALTER TABLE orders ADD INDEX idx_orders_customer_privacy_status_created (customer_id, customer_deleted_at, order_status, created_at)',
    'SELECT 1'
);

PREPARE stmt FROM @add_orders_customer_privacy_index;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
