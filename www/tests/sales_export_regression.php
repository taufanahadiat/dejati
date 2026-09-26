<?php
// docker exec lampp_web php /var/www/html/tests/sales_export_regression.php
// Connection-local temporary fixtures only; no live sales records are changed.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../include/report/export_report.php';
function verify($ok, $message) { if (!$ok) throw new RuntimeException($message); }
foreach (['orders', 'order_items', 'order_carwash', 'order_detailing', 'tb_datacafe', 'tb_category'] as $table) {
    $schema = $conn->query("SHOW CREATE TABLE `$table`")->fetch_assoc()['Create Table'];
    $conn->query(preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $schema));
}
$conn->query("INSERT INTO tb_category (id_cat, name_cat, icon) VALUES (1, 'Coffee', '')");
$conn->query("INSERT INTO tb_datacafe (id_prod, nama_prod, id_cat, biaya, variant, updated_at, updated_by) VALUES (1, 'Coffee', 1, 10000, 0, NOW(), 0)");
$conn->query("INSERT INTO orders (id, table_number, total_amount, paid_amount, status_order, payment_method, created_at, cancel_reason) VALUES
 (1, 'A1', 54000, 60000, 'PAID', 'cash', '2026-09-01 00:00:00', NULL),
 (2, 'A2', 9000, 9000, 'CANCEL', 'qris', '2026-09-01 12:00:00', 'Changed mind'),
 (3, 'A3', 10000, 0, 'OPEN BILL', NULL, '2026-09-01 13:00:00', NULL),
 (4, 'A4', 20000, 20000, 'PAID', 'qris', '2026-09-01 23:59:59', NULL),
 (5, 'A5', 999999, 999999, 'PAID', 'cash', '2026-09-02 00:00:00', NULL)");
$conn->query("INSERT INTO order_items (id_tr, id_prod, item_name, item_price, quantity, total) VALUES
 (1, '1', '=Coffee & Tea', 10000, 1, 10000), (2, '1', 'Coffee', 10000, 1, 10000),
 (3, '1', 'Coffee', 10000, 1, 10000), (4, '999', 'Deleted product', 10000, 2, 20000), (5, '1', 'Outside', 999999, 1, 999999)");
$conn->query("INSERT INTO order_carwash (id_tr, id_prod, item_name, qty, total, service, profit_management, profit_pegawai) VALUES (1, '1', 'Wash', 1, 20000, 'Cuci', 14000, 6000)");
$conn->query("INSERT INTO order_detailing (id_tr, id_prod, item_name, qty, total, service, profit_management, profit_pegawai) VALUES (1, '1', 'Detail', 1, 30000, 'Polish', 21000, 9000)");
$options = salesExportOptions(['start' => '2026-09-01', 'end' => '2026-09-01', 'type' => 'overview', 'divisions' => ['cafe','carwash','detailing']]);
$report = salesExportBuild($conn, $options);
verify($report['sections'][0]['rows'][0] === [2,5,80000,6000,0,74000], 'Paid totals and inclusive date boundaries');
function section($report, $prefix) { foreach ($report['sections'] as $s) if (strpos($s['title'], $prefix) === 0) return $s; throw new RuntimeException($prefix); }
$cafe = section($report, 'Cafe — Rekap');
verify(end($cafe['rows']) === ['Subtotal', '', 3, 30000, 1000, 0, 29000], 'Cafe subtotal and allocation');
$wash = section($report, 'Carwash — Rekap Service');
verify(end($wash['rows']) === ['Subtotal', '', 1, 20000, 2000, 0, 18000], 'Service subtotal');
$cancel = section($report, 'Ringkasan Pesanan Dibatalkan');
verify(count($cancel['rows']) === 2 && $cancel['rows'][0][7] === 9000, 'Cancellation excluded from paid sales');
$payments = section($report, 'Ringkasan Metode Pembayaran');
verify(end($payments['rows'])[5] === 74000, 'Payments reconcile');
verify(strpos(json_encode($report), 'Tanpa kategori') !== false, 'Deleted product remains reported');
file_put_contents('/tmp/sales-export-fixture.json', json_encode($report));
$sumSelected = 0;
foreach (['cafe','carwash','detailing'] as $division) {
    $selected = salesExportBuild($conn, array_merge($options, ['divisions' => [$division]]));
    $sumSelected += $selected['sections'][0]['rows'][0][5];
    verify($selected['divisions'] === [ucfirst($division)], 'Division filter label');
    $top = section($selected, 'Top Produk');
    foreach ($top['rows'] as $row) verify($row[0] === ucfirst($division), 'No unchecked services in rankings');
}
verify($sumSelected === 74000, 'Selected divisions reconcile to all sales');
foreach (['transactions','items','categories'] as $type) {
    $r = salesExportBuild($conn, array_merge($options, ['type' => $type]));
    verify($r['sections'][0]['rows'][0][5] === 74000, 'Report type totals: ' . $type);
    foreach ($r['sections'] as $s) foreach ($s['rows'] as $row) verify(count($row) === count($s['columns']), 'Consistent columns');
}
$empty = salesExportBuild($conn, array_merge($options, ['start' => '2020-01-01', 'end' => '2020-01-01']));
verify($empty['sections'][0]['rows'][0] === [0,0,0,0,0,0], 'Empty period');
verify(salesExportAllocate(2, [1,1,1]) === [1,1,0], 'Rounding preserves exact amount');
foreach ([['start' => '2026-02-30'], ['end' => '2026-08-01'], ['type' => 'bad'], ['divisions' => []], ['divisions' => ['bad']]] as $bad) {
    try { salesExportOptions(array_merge($options, $bad)); throw new RuntimeException('Validation accepted invalid input'); }
    catch (InvalidArgumentException $expected) {}
}
echo "PASS: date boundaries, all report types, selected services, discounts, service subtotals, cancellations, payments, missing products, empty reports, validation\n";
