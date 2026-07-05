-- Safe performance indexes for POS query paths.
-- Run once on the production database. The procedure skips indexes that already exist.

DELIMITER //
CREATE PROCEDURE add_index_if_missing(IN p_table VARCHAR(64), IN p_index VARCHAR(64), IN p_sql TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = p_table
      AND index_name = p_index
  ) THEN
    SET @ddl = p_sql;
    PREPARE stmt FROM @ddl;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END//
DELIMITER ;

CALL add_index_if_missing('orders', 'idx_orders_created_at', 'ALTER TABLE orders ADD INDEX idx_orders_created_at (created_at)');
CALL add_index_if_missing('orders', 'idx_orders_payment_method', 'ALTER TABLE orders ADD INDEX idx_orders_payment_method (payment_method)');
CALL add_index_if_missing('order_items', 'idx_order_items_id_tr', 'ALTER TABLE order_items ADD INDEX idx_order_items_id_tr (id_tr)');
CALL add_index_if_missing('order_carwash', 'idx_order_carwash_id_tr', 'ALTER TABLE order_carwash ADD INDEX idx_order_carwash_id_tr (id_tr)');
CALL add_index_if_missing('tb_datacafe', 'idx_tb_datacafe_name', 'ALTER TABLE tb_datacafe ADD INDEX idx_tb_datacafe_name (nama_prod)');
CALL add_index_if_missing('tb_datacafe', 'idx_tb_datacafe_category', 'ALTER TABLE tb_datacafe ADD INDEX idx_tb_datacafe_category (id_cat)');
CALL add_index_if_missing('tb_category', 'idx_tb_category_name', 'ALTER TABLE tb_category ADD INDEX idx_tb_category_name (name_cat)');
CALL add_index_if_missing('tb_datacarwash', 'idx_tb_datacarwash_produk', 'ALTER TABLE tb_datacarwash ADD INDEX idx_tb_datacarwash_produk (produk)');

DROP PROCEDURE add_index_if_missing;