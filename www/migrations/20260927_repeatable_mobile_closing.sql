-- Preserve per-request idempotency, while allowing multiple closing refreshes per date.
SET @has_date_unique=(SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='mobile_report_requests' AND index_name='uq_mobile_report_requests_closing_date');
SET @ddl=IF(@has_date_unique>0,'ALTER TABLE mobile_report_requests DROP INDEX uq_mobile_report_requests_closing_date, ADD INDEX idx_mobile_report_requests_closing_date (closing_date)','SELECT 1');
PREPARE repeat_closing_stmt FROM @ddl;
EXECUTE repeat_closing_stmt;
DEALLOCATE PREPARE repeat_closing_stmt;
