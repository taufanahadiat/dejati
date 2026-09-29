CREATE TABLE IF NOT EXISTS olsera_sync_jobs (
  sales_date DATE PRIMARY KEY,
  state ENUM('pending','running','succeeded','failed') NOT NULL DEFAULT 'pending',
  attempts INT NOT NULL DEFAULT 0,
  lease_token CHAR(64) NULL,
  lease_until DATETIME NULL,
  next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_error TEXT NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  KEY idx_olsera_queue (state,next_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS olsera_imports (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  sales_date DATE NOT NULL,
  file_sha256 CHAR(64) NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  fetched_at DATETIME NOT NULL,
  row_count INT NOT NULL,
  total_sales DECIMAL(16,2) NOT NULL,
  source_rows JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_olsera_import_date (sales_date,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS olsera_sales (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  sales_date DATE NOT NULL,
  row_key CHAR(64) NOT NULL,
  product_name VARCHAR(255) NOT NULL,
  variant VARCHAR(255) NOT NULL DEFAULT '',
  product_group VARCHAR(255) NOT NULL DEFAULT '',
  sku VARCHAR(255) NOT NULL DEFAULT '',
  business ENUM('cafe','carwash','detailing') NOT NULL,
  product_id INT NOT NULL,
  quantity DECIMAL(12,3) NOT NULL,
  gross_sales DECIMAL(16,2) NOT NULL,
  discount_amount DECIMAL(16,2) NOT NULL,
  return_amount DECIMAL(16,2) NOT NULL,
  total_sales DECIMAL(16,2) NOT NULL,
  import_id BIGINT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_olsera_sale (sales_date,row_key),
  KEY idx_olsera_business (sales_date,business),
  FOREIGN KEY (import_id) REFERENCES olsera_imports(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS olsera_stock_usage (
  sale_id BIGINT NOT NULL,
  stock_item_id INT NOT NULL,
  quantity_per_sale DECIMAL(12,3) NOT NULL,
  applied_quantity DECIMAL(12,3) NOT NULL DEFAULT 0,
  PRIMARY KEY (sale_id,stock_item_id),
  FOREIGN KEY (sale_id) REFERENCES olsera_sales(id),
  FOREIGN KEY (stock_item_id) REFERENCES stock_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
