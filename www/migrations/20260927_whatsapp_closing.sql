CREATE TABLE IF NOT EXISTS whatsapp_closing_notifications (
  closing_date DATE PRIMARY KEY,
  group_id VARCHAR(128) NOT NULL,
  payload JSON NOT NULL,
  summary_state ENUM('pending','sending','sent') NOT NULL DEFAULT 'pending',
  stock_state ENUM('pending','sending','sent') NOT NULL DEFAULT 'pending',
  summary_body TEXT NULL,
  stock_body TEXT NULL,
  summary_sent_at DATETIME NULL,
  stock_sent_at DATETIME NULL,
  last_error VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
