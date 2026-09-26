ALTER TABLE tb_datacafe ENGINE=InnoDB;

ALTER TABLE stock_product_bindings
  ADD CONSTRAINT fk_stock_product_cafe
  FOREIGN KEY (product_id) REFERENCES tb_datacafe (id_prod) ON DELETE CASCADE;

ALTER TABLE mobile_sync_orders
  ADD UNIQUE KEY uq_mobile_sync_order_id (order_id),
  ADD CONSTRAINT fk_mobile_sync_order
  FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE;

DROP TRIGGER IF EXISTS trg_order_items_stock_insert;
DROP TRIGGER IF EXISTS trg_orders_stock_status_update;

DELIMITER $$
CREATE TRIGGER trg_order_items_stock_insert
AFTER INSERT ON order_items
FOR EACH ROW
BEGIN
  INSERT INTO stock_movements
    (stock_item_id, order_id, order_item_id, movement_type, quantity_delta, movement_key, note, created_at)
  SELECT b.stock_item_id, NEW.id_tr, NEW.id, 'sale', -(NEW.quantity * b.quantity_per_sale),
         CONCAT('sale:', NEW.id, ':', b.stock_item_id),
         CONCAT('Penjualan #', NEW.id_tr, ' - ', NEW.item_name), o.created_at
  FROM stock_product_bindings b
  JOIN orders o ON o.id = NEW.id_tr
  WHERE b.product_id = CAST(NEW.id_prod AS UNSIGNED)
    AND UPPER(COALESCE(NULLIF(o.status_order, ''), IF(o.paid_amount = 0, 'OPEN BILL', 'PAID'))) = 'PAID'
  ON DUPLICATE KEY UPDATE movement_key=stock_movements.movement_key;
END$$

CREATE TRIGGER trg_orders_stock_status_update
AFTER UPDATE ON orders
FOR EACH ROW
BEGIN
  DECLARE old_status VARCHAR(20);
  DECLARE new_status VARCHAR(20);
  SET old_status = UPPER(COALESCE(NULLIF(OLD.status_order, ''), IF(OLD.paid_amount = 0, 'OPEN BILL', 'PAID')));
  SET new_status = UPPER(COALESCE(NULLIF(NEW.status_order, ''), IF(NEW.paid_amount = 0, 'OPEN BILL', 'PAID')));

  IF old_status <> 'PAID' AND new_status = 'PAID' THEN
    INSERT INTO stock_movements
      (stock_item_id, order_id, order_item_id, movement_type, quantity_delta, movement_key, note, created_at)
    SELECT b.stock_item_id, oi.id_tr, oi.id, 'sale', -(oi.quantity * b.quantity_per_sale),
           CONCAT('sale:', oi.id, ':', b.stock_item_id),
           CONCAT('Pembayaran order #', oi.id_tr, ' - ', oi.item_name), NEW.created_at
    FROM order_items oi
    JOIN stock_product_bindings b ON b.product_id = CAST(oi.id_prod AS UNSIGNED)
    WHERE oi.id_tr = NEW.id
    ON DUPLICATE KEY UPDATE movement_key=stock_movements.movement_key;
  ELSEIF old_status = 'PAID' AND new_status = 'CANCEL' THEN
    INSERT INTO stock_movements
      (stock_item_id, order_id, order_item_id, movement_type, quantity_delta, movement_key, note, created_at)
    SELECT sale.stock_item_id, NEW.id, sale.order_item_id, 'cancel', -sale.quantity_delta,
           CONCAT('cancel:', sale.id), CONCAT('Pembatalan order #', NEW.id), NOW()
    FROM stock_movements sale
    WHERE sale.order_id = NEW.id AND sale.movement_type = 'sale'
    ON DUPLICATE KEY UPDATE movement_key=stock_movements.movement_key;
  END IF;
END$$
DELIMITER ;
