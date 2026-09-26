ALTER TABLE mobile_report_requests
  ADD COLUMN closing_date DATE NULL AFTER request_id;

UPDATE mobile_report_requests
SET closing_date = STR_TO_DATE(
  JSON_UNQUOTE(JSON_EXTRACT(response_json, '$.closing.tanggal')),
  '%Y-%m-%d'
)
WHERE closing_date IS NULL
  AND JSON_VALID(response_json);

ALTER TABLE mobile_report_requests
  ADD UNIQUE KEY uq_mobile_report_requests_closing_date (closing_date);
