SET NAMES utf8mb4;

-- Mobile order uploads and their stock movements must roll back as one unit.
ALTER TABLE orders ENGINE=InnoDB;
ALTER TABLE order_items ENGINE=InnoDB;
ALTER TABLE order_carwash ENGINE=InnoDB;
ALTER TABLE order_detailing ENGINE=InnoDB;
ALTER TABLE tb_datacafe ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stock_items (
  id INT NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  initial_quantity DECIMAL(12,3) NOT NULL DEFAULT 0,
  unit VARCHAR(20) NOT NULL DEFAULT 'pcs',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_stock_items_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS stock_product_bindings (
  id BIGINT NOT NULL AUTO_INCREMENT,
  stock_item_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity_per_sale DECIMAL(12,3) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_stock_product_binding (stock_item_id, product_id),
  KEY idx_stock_product_product (product_id),
  CONSTRAINT fk_stock_product_item FOREIGN KEY (stock_item_id) REFERENCES stock_items (id) ON DELETE CASCADE
  ,CONSTRAINT fk_stock_product_cafe FOREIGN KEY (product_id) REFERENCES tb_datacafe (id_prod) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS stock_movements (
  id BIGINT NOT NULL AUTO_INCREMENT,
  stock_item_id INT NOT NULL,
  order_id INT NULL,
  order_item_id INT NULL,
  movement_type ENUM('sale','cancel','adjustment') NOT NULL,
  quantity_delta DECIMAL(12,3) NOT NULL,
  stock_in_quantity DECIMAL(12,3) NOT NULL DEFAULT 0,
  movement_key VARCHAR(191) NOT NULL,
  note VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_stock_movement_key (movement_key),
  KEY idx_stock_movement_item_date (stock_item_id, created_at),
  KEY idx_stock_movement_order (order_id),
  CONSTRAINT fk_stock_movement_item FOREIGN KEY (stock_item_id) REFERENCES stock_items (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO stock_items (name, initial_quantity, unit) VALUES
  ('Sei', 6, 'pcs'),
  ('Iga', 14, 'pcs'),
  ('Ayam Kampung', -2, 'pcs'),
  ('Ayam Bakar', -44, 'pcs'),
  ('Ayam Geprek', -2, 'pcs'),
  ('Chicken Katsu', 15, 'pcs'),
  ('Pepes Ikan Mas', 12, 'pcs'),
  ('Bebek', 1, 'pcs'),
  ('Patin', 14, 'pcs'),
  ('Soto', 12, 'pcs'),
  ('Beef', 25, 'pcs'),
  ('Donat', 29, 'pcs'),
  ('Tahu Bakso', -4, 'pcs'),
  ('Dimsum', 0, 'pcs'),
  ('Sosis Bakar Besar', 3, 'pcs'),
  ('Singkong + Mix Snack', -13, 'pcs'),
  ('Cireng', 11, 'pcs'),
  ('Pempek', 16, 'pcs'),
  ('Sosis Bakar Kecil', 9, 'pcs'),
  ('Garang Asem', 7, 'pcs'),
  ('Fish & Potato', 4, 'pcs'),
  ('Dori', -5, 'pcs'),
  ('Tahu Walik + Mix Snack', -85, 'pcs'),
  ('Pizza', 2, 'pcs')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO stock_product_bindings (stock_item_id, product_id, quantity_per_sale)
SELECT s.id, seed.product_id, 1
FROM (
  SELECT 'Sei' stock_name, 190 product_id UNION ALL SELECT 'Sei', 189 UNION ALL SELECT 'Sei', 240
  UNION ALL SELECT 'Iga', 83 UNION ALL SELECT 'Iga', 89 UNION ALL SELECT 'Iga', 193 UNION ALL SELECT 'Iga', 18 UNION ALL SELECT 'Iga', 192 UNION ALL SELECT 'Iga', 188
  UNION ALL SELECT 'Ayam Kampung', 21 UNION ALL SELECT 'Ayam Kampung', 247
  UNION ALL SELECT 'Ayam Bakar', 249 UNION ALL SELECT 'Ayam Bakar', 88 UNION ALL SELECT 'Ayam Bakar', 29 UNION ALL SELECT 'Ayam Bakar', 109 UNION ALL SELECT 'Ayam Bakar', 111
  UNION ALL SELECT 'Ayam Geprek', 248 UNION ALL SELECT 'Ayam Geprek', 208 UNION ALL SELECT 'Ayam Geprek', 163 UNION ALL SELECT 'Ayam Geprek', 87 UNION ALL SELECT 'Ayam Geprek', 9 UNION ALL SELECT 'Ayam Geprek', 108 UNION ALL SELECT 'Ayam Geprek', 243
  UNION ALL SELECT 'Chicken Katsu', 245 UNION ALL SELECT 'Chicken Katsu', 12
  UNION ALL SELECT 'Pepes Ikan Mas', 191 UNION ALL SELECT 'Pepes Ikan Mas', 241
  UNION ALL SELECT 'Bebek', 199 UNION ALL SELECT 'Bebek', 195
  UNION ALL SELECT 'Patin', 161 UNION ALL SELECT 'Patin', 184 UNION ALL SELECT 'Patin', 239
  UNION ALL SELECT 'Soto', 90 UNION ALL SELECT 'Soto', 110 UNION ALL SELECT 'Soto', 183
  UNION ALL SELECT 'Beef', 16 UNION ALL SELECT 'Beef', 143 UNION ALL SELECT 'Beef', 187
  UNION ALL SELECT 'Donat', 95
  UNION ALL SELECT 'Tahu Bakso', 94
  UNION ALL SELECT 'Dimsum', 23
  UNION ALL SELECT 'Sosis Bakar Besar', 134
  UNION ALL SELECT 'Singkong + Mix Snack', 157 UNION ALL SELECT 'Singkong + Mix Snack', 206 UNION ALL SELECT 'Singkong + Mix Snack', 137 UNION ALL SELECT 'Singkong + Mix Snack', 28 UNION ALL SELECT 'Singkong + Mix Snack', 150 UNION ALL SELECT 'Singkong + Mix Snack', 205 UNION ALL SELECT 'Singkong + Mix Snack', 149 UNION ALL SELECT 'Singkong + Mix Snack', 204
  UNION ALL SELECT 'Cireng', 158 UNION ALL SELECT 'Cireng', 154 UNION ALL SELECT 'Cireng', 106 UNION ALL SELECT 'Cireng', 105 UNION ALL SELECT 'Cireng', 103
  UNION ALL SELECT 'Pempek', 24
  UNION ALL SELECT 'Garang Asem', 80
  UNION ALL SELECT 'Fish & Potato', 78
  UNION ALL SELECT 'Dori', 77 UNION ALL SELECT 'Dori', 76
  UNION ALL SELECT 'Tahu Walik + Mix Snack', 156 UNION ALL SELECT 'Tahu Walik + Mix Snack', 104 UNION ALL SELECT 'Tahu Walik + Mix Snack', 107 UNION ALL SELECT 'Tahu Walik + Mix Snack', 103 UNION ALL SELECT 'Tahu Walik + Mix Snack', 22 UNION ALL SELECT 'Tahu Walik + Mix Snack', 28 UNION ALL SELECT 'Tahu Walik + Mix Snack', 150 UNION ALL SELECT 'Tahu Walik + Mix Snack', 205 UNION ALL SELECT 'Tahu Walik + Mix Snack', 149 UNION ALL SELECT 'Tahu Walik + Mix Snack', 204
  UNION ALL SELECT 'Pizza', 82 UNION ALL SELECT 'Pizza', 253
) seed
JOIN stock_items s ON s.name = seed.stock_name
JOIN tb_datacafe p ON p.id_prod = seed.product_id
ON DUPLICATE KEY UPDATE quantity_per_sale = VALUES(quantity_per_sale);

CREATE OR REPLACE VIEW vw_stock_balances AS
SELECT s.id, s.name, s.initial_quantity, s.unit, s.active,
       s.initial_quantity + COALESCE(SUM(m.quantity_delta), 0) AS current_quantity,
       s.updated_at
FROM stock_items s
LEFT JOIN stock_movements m ON m.stock_item_id = s.id
GROUP BY s.id, s.name, s.initial_quantity, s.unit, s.active, s.updated_at;

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
