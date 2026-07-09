-- Add richer audit metadata to the existing activity_logs table.
-- Each block is idempotent so the migration can be run more than once.

SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'activity_logs'
      AND column_name = 'target_type'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE activity_logs ADD COLUMN target_type VARCHAR(50) NULL AFTER module',
    'SELECT ''activity_logs.target_type already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'activity_logs'
      AND column_name = 'target_id'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE activity_logs ADD COLUMN target_id INT NULL AFTER target_type',
    'SELECT ''activity_logs.target_id already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'activity_logs'
      AND column_name = 'old_value'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE activity_logs ADD COLUMN old_value TEXT NULL AFTER target_id',
    'SELECT ''activity_logs.old_value already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'activity_logs'
      AND column_name = 'new_value'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE activity_logs ADD COLUMN new_value TEXT NULL AFTER old_value',
    'SELECT ''activity_logs.new_value already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'activity_logs'
      AND column_name = 'ip_address'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE activity_logs ADD COLUMN ip_address VARCHAR(45) NULL AFTER new_value',
    'SELECT ''activity_logs.ip_address already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'activity_logs'
      AND column_name = 'user_agent'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE activity_logs ADD COLUMN user_agent VARCHAR(255) NULL AFTER ip_address',
    'SELECT ''activity_logs.user_agent already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'activity_logs'
      AND index_name = 'idx_activity_logs_target'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE activity_logs ADD INDEX idx_activity_logs_target (target_type, target_id)',
    'SELECT ''idx_activity_logs_target already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'activity_logs'
      AND index_name = 'idx_activity_logs_module_created'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE activity_logs ADD INDEX idx_activity_logs_module_created (module, created_at)',
    'SELECT ''idx_activity_logs_module_created already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'activity_logs'
      AND index_name = 'idx_activity_logs_user_created'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE activity_logs ADD INDEX idx_activity_logs_user_created (user_id, created_at)',
    'SELECT ''idx_activity_logs_user_created already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
