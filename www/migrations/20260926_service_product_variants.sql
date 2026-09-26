-- Existing products remain non-variant with their current prices.
SET @present = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tb_datacarwash' AND COLUMN_NAME = 'variant');
SET @ddl = IF(@present = 0, 'ALTER TABLE tb_datacarwash ADD COLUMN variant TINYINT NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE variant_migration FROM @ddl;
EXECUTE variant_migration;
DEALLOCATE PREPARE variant_migration;

SET @present = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tb_datacarwash' AND COLUMN_NAME = 'nama_var');
SET @ddl = IF(@present = 0, 'ALTER TABLE tb_datacarwash ADD COLUMN nama_var TEXT NULL', 'SELECT 1');
PREPARE variant_migration FROM @ddl;
EXECUTE variant_migration;
DEALLOCATE PREPARE variant_migration;

SET @present = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tb_datacarwash' AND COLUMN_NAME = 'biaya_var');
SET @ddl = IF(@present = 0, 'ALTER TABLE tb_datacarwash ADD COLUMN biaya_var TEXT NULL', 'SELECT 1');
PREPARE variant_migration FROM @ddl;
EXECUTE variant_migration;
DEALLOCATE PREPARE variant_migration;

SET @present = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tb_datacarwash' AND COLUMN_NAME = 'foto');
SET @ddl = IF(@present = 0, 'ALTER TABLE tb_datacarwash ADD COLUMN foto VARCHAR(255) NOT NULL DEFAULT ''''', 'SELECT 1');
PREPARE variant_migration FROM @ddl;
EXECUTE variant_migration;
DEALLOCATE PREPARE variant_migration;

SET @present = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tb_datadetailing' AND COLUMN_NAME = 'variant');
SET @ddl = IF(@present = 0, 'ALTER TABLE tb_datadetailing ADD COLUMN variant TINYINT NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE variant_migration FROM @ddl;
EXECUTE variant_migration;
DEALLOCATE PREPARE variant_migration;

SET @present = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tb_datadetailing' AND COLUMN_NAME = 'nama_var');
SET @ddl = IF(@present = 0, 'ALTER TABLE tb_datadetailing ADD COLUMN nama_var TEXT NULL', 'SELECT 1');
PREPARE variant_migration FROM @ddl;
EXECUTE variant_migration;
DEALLOCATE PREPARE variant_migration;

SET @present = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tb_datadetailing' AND COLUMN_NAME = 'biaya_var');
SET @ddl = IF(@present = 0, 'ALTER TABLE tb_datadetailing ADD COLUMN biaya_var TEXT NULL', 'SELECT 1');
PREPARE variant_migration FROM @ddl;
EXECUTE variant_migration;
DEALLOCATE PREPARE variant_migration;

SET @present = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tb_datadetailing' AND COLUMN_NAME = 'foto');
SET @ddl = IF(@present = 0, 'ALTER TABLE tb_datadetailing ADD COLUMN foto VARCHAR(255) NOT NULL DEFAULT ''''', 'SELECT 1');
PREPARE variant_migration FROM @ddl;
EXECUTE variant_migration;
DEALLOCATE PREPARE variant_migration;

SET @present = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_carwash' AND COLUMN_NAME = 'variant_name');
SET @ddl = IF(@present = 0, 'ALTER TABLE order_carwash ADD COLUMN variant_name VARCHAR(100) NULL', 'SELECT 1');
PREPARE variant_migration FROM @ddl;
EXECUTE variant_migration;
DEALLOCATE PREPARE variant_migration;

SET @present = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_detailing' AND COLUMN_NAME = 'variant_name');
SET @ddl = IF(@present = 0, 'ALTER TABLE order_detailing ADD COLUMN variant_name VARCHAR(100) NULL', 'SELECT 1');
PREPARE variant_migration FROM @ddl;
EXECUTE variant_migration;
DEALLOCATE PREPARE variant_migration;
