SET @paid_at_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'paid_at') = 0,
  'ALTER TABLE orders ADD COLUMN paid_at DATETIME NULL AFTER canceled_at',
  'SELECT 1'
);
PREPARE paid_at_stmt FROM @paid_at_sql;
EXECUTE paid_at_stmt;
DEALLOCATE PREPARE paid_at_stmt;

SET @payload_hash_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mobile_sync_orders' AND COLUMN_NAME = 'payload_hash') = 0,
  'ALTER TABLE mobile_sync_orders ADD COLUMN payload_hash CHAR(64) NULL AFTER user_id',
  'SELECT 1'
);
PREPARE payload_hash_stmt FROM @payload_hash_sql;
EXECUTE payload_hash_stmt;
DEALLOCATE PREPARE payload_hash_stmt;

CREATE TABLE IF NOT EXISTS transaction_audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_type VARCHAR(50) NOT NULL,
  order_id INT NULL,
  client_order_id CHAR(36) NULL,
  user_id INT NULL,
  source VARCHAR(30) NOT NULL,
  event_status VARCHAR(20) NOT NULL,
  table_number VARCHAR(50) NULL,
  amount INT NULL,
  payment_method VARCHAR(50) NULL,
  client_event_at DATETIME NULL,
  details JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_transaction_audit_created (created_at),
  KEY idx_transaction_audit_order (order_id),
  KEY idx_transaction_audit_client (client_order_id),
  KEY idx_transaction_audit_event (event_type, event_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
