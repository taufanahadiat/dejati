-- Repeatable: seed unit-specific defaults only when adding the new column.
SET @stock_minimum_missing = (SELECT COUNT(*) = 0 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'stock_items' AND column_name = 'minimum_quantity');
SET @stock_minimum_ddl = IF(@stock_minimum_missing, 'ALTER TABLE stock_items ADD COLUMN minimum_quantity DECIMAL(12,3) NOT NULL DEFAULT 5', 'SELECT 1');
PREPARE stock_minimum_stmt FROM @stock_minimum_ddl;
EXECUTE stock_minimum_stmt;
DEALLOCATE PREPARE stock_minimum_stmt;
UPDATE stock_items SET minimum_quantity = CASE WHEN unit = 'gr' THEN 50 ELSE 5 END WHERE @stock_minimum_missing;
CREATE OR REPLACE VIEW vw_stock_balances AS
SELECT s.id, s.name, s.initial_quantity, s.unit, s.active,
       s.initial_quantity + COALESCE(SUM(m.quantity_delta), 0) AS current_quantity,
       s.updated_at, s.minimum_quantity
FROM stock_items s
LEFT JOIN stock_movements m ON m.stock_item_id = s.id
GROUP BY s.id, s.name, s.initial_quantity, s.unit, s.active, s.updated_at, s.minimum_quantity;
