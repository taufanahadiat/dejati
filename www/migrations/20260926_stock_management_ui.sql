SET @stock_in_column_sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'stock_movements'
      AND COLUMN_NAME = 'stock_in_quantity'
  ),
  'SELECT 1',
  'ALTER TABLE stock_movements ADD COLUMN stock_in_quantity DECIMAL(12,3) NOT NULL DEFAULT 0 AFTER quantity_delta'
);
PREPARE stock_in_column_stmt FROM @stock_in_column_sql;
EXECUTE stock_in_column_stmt;
DEALLOCATE PREPARE stock_in_column_stmt;
