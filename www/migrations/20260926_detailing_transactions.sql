CREATE TABLE IF NOT EXISTS order_detailing LIKE order_carwash;

-- Keep existing closing records compatible with the new division.
SET @has_detailing = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tb_closingan' AND COLUMN_NAME = 'detailing');
SET @ddl = IF(@has_detailing = 0,
    'ALTER TABLE tb_closingan ADD COLUMN detailing INT NOT NULL DEFAULT 0 AFTER carwash',
    'SELECT 1');
PREPARE detailing_migration FROM @ddl;
EXECUTE detailing_migration;
DEALLOCATE PREPARE detailing_migration;
