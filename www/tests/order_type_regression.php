<?php
// docker exec lampp_web php /var/www/html/tests/order_type_regression.php
// Temporary tables isolate all writes from production transactions.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/order_type.php';
define('MOBILE_REPORTS_TEST', true);
require __DIR__ . '/../api/mobile/mobile-reports.php';
require __DIR__ . '/../include/report/export_report.php';
function checkType($condition, $message) { if (!$condition) throw new RuntimeException($message); }
foreach (['orders','order_items','order_carwash','order_detailing','tb_closingan','pengeluaran','tb_datacafe','tb_category'] as $table) {
    $schema = $conn->query("SHOW CREATE TABLE `$table`")->fetch_assoc()['Create Table'];
    $conn->query(preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $schema));
}
$conn->query('CREATE TEMPORARY TABLE mobile_sync_orders (client_order_id CHAR(36), order_id INT)');
$_POST = ['tableNumber' => 'TYPE TEST', 'paymentMethod' => 'cash', 'paid' => '50000', 'items' => json_encode([
    ['id'=>'1', 'name'=>'Same Coffee', 'qty'=>1, 'unitPrice'=>10000, 'cartType'=>'product', 'orderType'=>'dine-in'],
    ['id'=>'1', 'name'=>'Same Coffee', 'qty'=>2, 'unitPrice'=>10000, 'cartType'=>'product', 'orderType'=>'take-away'],
    ['id'=>'2', 'name'=>'Legacy Unknown', 'qty'=>1, 'unitPrice'=>10000, 'cartType'=>'product']
])];
ob_start(); require __DIR__ . '/../include/transaksi/save_orderAct.php'; $result = json_decode(ob_get_clean(), true);
checkType($result['status'] === 'success', 'Web write succeeds');
$id = $result['order_id'];
$items = $conn->query("SELECT order_type FROM order_items WHERE id_tr=$id ORDER BY id")->fetch_all(MYSQLI_ASSOC);
checkType(array_column($items, 'order_type') === ['dine-in','take-away',null], 'Explicit types persisted, unknown preserved');
$_GET = ['id'=>$id];
ob_start(); require __DIR__ . '/../include/report/order_get_json.php'; $json = json_decode(ob_get_clean(), true);
checkType(array_column($json['items'], 'order_type') === ['dine-in','take-away',null], 'POS import JSON retains types');
ob_start(); require __DIR__ . '/../include/report/order_get.php'; $html = ob_get_clean();
checkType(str_contains($html,'Dine In') && str_contains($html,'Take Away') && str_contains($html,'Belum tercatat'), 'Detail modal labels');
$history = reportHistory($conn);
checkType(array_column($history['orders'][0]['items'], 'orderType') === ['dine-in','take-away',null], 'App history round trip');
$options = salesExportOptions(['start'=>date('Y-m-d'),'end'=>date('Y-m-d'),'type'=>'overview','divisions'=>['cafe']]);
$report = salesExportBuild($conn, $options);
foreach ($report['sections'] as $section) if ($section['title'] === 'Cafe — Dine In / Take Away') $channels = $section['rows'];
checkType($channels[0] === ['Dine In',1,10000,0,0,10000], 'Dine-in summary');
checkType($channels[1] === ['Take Away',2,20000,0,0,20000], 'Take-away summary');
checkType($channels[2] === ['Belum tercatat',1,10000,0,0,10000], 'Unknown not counted as dine-in');
checkType(transactionItemOrderType(['order_type'=>'take_away']) === 'take-away', 'Snake-case sync input');
checkType(transactionItemOrderType(['orderType'=>null]) === null, 'Legacy explicit null');
foreach (['invalid', [], 1] as $invalid) {
    try { transactionOrderType($invalid); throw new RuntimeException('Invalid type accepted'); }
    catch (InvalidArgumentException $expected) {}
}
echo "PASS: web write, mixed Cafe types, legacy null, POS import, app history, report totals and validation\n";
