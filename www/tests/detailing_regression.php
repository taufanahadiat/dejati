<?php
// Run with: docker exec lampp_web php /var/www/html/tests/detailing_regression.php <scenario>
// Every write targets connection-local temporary tables, never live sales records.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
chdir(dirname(__DIR__));
require 'config/config.php';
require 'config/session.php';
session_start();
$_SESSION = ['loggedin' => true, 'level' => 'Administrator', 'nama_user' => 'Regression'];
session_write_close();
register_shutdown_function(function () {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    session_destroy();
});

function check($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
foreach (['orders', 'order_items', 'order_carwash', 'order_detailing', 'tb_closingan', 'pengeluaran', 'tb_datadetailing'] as $table) {
    $schema = $conn->query("SHOW CREATE TABLE `$table`")->fetch_assoc()['Create Table'];
    $conn->query(preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $schema));
}
$_SERVER['REQUEST_METHOD'] = 'POST';
$vehicle = ['id' => '1', 'name' => 'Detailing Test', 'unitPrice' => 100000, 'finalPrice' => 105000,
    'qty' => 2, 'cartType' => 'detailing', 'nopol' => 'B 1234 TEST', 'service' => 'Polish',
    'ukuran' => 'Mobil Sedang', 'vacuum' => 'yes', 'variantName' => 'Large'];
$_POST = ['tableNumber' => 'TEST', 'paymentMethod' => 'cash', 'paid' => '300000',
    'items' => json_encode([
        ['id' => '1', 'name' => 'Cafe Test', 'unitPrice' => 10000, 'qty' => 2, 'cartType' => 'product'],
        array_merge($vehicle, ['name' => 'Carwash Test', 'cartType' => 'carwash', 'unitPrice' => 25000, 'finalPrice' => 30000, 'qty' => 1]),
        $vehicle
    ])];
ob_start();
require 'include/transaksi/save_orderAct.php';
$response = json_decode(ob_get_clean(), true);
check($response['status'] === 'success' && $response['total'] === 260000, 'Mixed order total');
$orderId = $response['order_id'];
foreach (['order_items' => 20000, 'order_carwash' => 30000, 'order_detailing' => 210000] as $table => $expected) {
    $sum = $conn->query("SELECT SUM(total) AS total FROM $table WHERE id_tr = $orderId")->fetch_assoc()['total'];
    check((int)$sum === $expected, "$table amount / quantity / isolation");
}
$row = $conn->query("SELECT * FROM order_detailing WHERE id_tr = $orderId")->fetch_assoc();
check($row['variant_name'] === 'Large', 'Variant snapshot stored');
check($row['nopol'] === $vehicle['nopol'] && $row['service'] === 'Polish', 'Vehicle details');
check((int)$row['profit_pegawai'] + (int)$row['profit_management'] === 210000, 'Service amount split');

// Unpaid and canceled sales must not increase closing revenue.
foreach (['OPEN BILL', 'CANCEL'] as $status) {
    $conn->query("INSERT INTO orders (table_number, total_amount, paid_amount, status_order, created_at) VALUES ('EXCLUDED', 999999, 0, '$status', NOW())");
    $excludedId = $conn->insert_id;
    $conn->query("INSERT INTO order_detailing (id_tr, total) VALUES ($excludedId, 999999)");
}
$scenario = $argv[1] ?? 'read';
if ($scenario === 'read') {
    $_GET = ['id' => $orderId];
    ob_start(); require 'include/report/order_get_json.php'; $data = json_decode(ob_get_clean(), true);
    check(count($data['items']) === 1 && count($data['carwash']) === 1 && count($data['detailing']) === 1, 'JSON division separation');
    check($data['detailing'][0]['variant_name'] === 'Large' && $data['carwash'][0]['variant_name'] === 'Large', 'Both service variants in JSON');
    check((int)$data['detailing'][0]['total'] === 210000, 'JSON detailing total');
    ob_start(); require 'include/report/order_get.php'; $html = ob_get_clean();
    check(str_contains($html, 'Detailing Test') && str_contains($html, 'B 1234 TEST') && str_contains($html, 'Varian: Large'), 'Order detail display');
    $_GET = [];
    ob_start(); require 'include/report/index.php'; $html = ob_get_clean();
    check(str_contains($html, '<th>Detailing</th>') && str_contains($html, '210.000'), 'History column and amount');
    file_put_contents('/tmp/detailing-report-test.html', $html);
    $_SERVER['REQUEST_METHOD'] = 'GET';
    ob_start(); require 'include/report/closingan_preview.php'; $html = ob_get_clean();
    check(str_contains($html, 'Detailing: Rp 210.000') && str_contains($html, 'Total Penjualan: Rp 260.000'), 'Closing excludes open/canceled');
    $conn->query("INSERT INTO tb_closingan (tanggal, total_penjualan, cash, cafe, carwash, detailing) VALUES (CURDATE(), 260000, 260000, 20000, 30000, 210000)");
    // Closing history uses a self-join, unsupported for MySQL temporary tables; checked over HTTP.
    $conn->query("INSERT INTO tb_datadetailing (produk, biaya) VALUES ('Test Detailing Product', 100000)");
    // Check the POS renders and imports detailing as a service, keeping vehicle fields.
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['order_details' => json_encode($data)];
    ob_start(); require 'include/transaksi/index.php'; $html = ob_get_clean();
    check(str_contains($html, "100000, 'detailing')") && str_contains($html, 'Test Detailing Product'), 'POS category');
    file_put_contents('/tmp/detailing-pos-test.html', $html);
    echo "PASS: mixed sale, service quantity, JSON, details, history, closing preview, POS import\n";
} elseif (in_array($scenario, ['closing-insert', 'closing-update'], true)) {
    if ($scenario === 'closing-update') {
        $conn->query("INSERT INTO tb_closingan (tanggal, total_penjualan, detailing) VALUES (CURDATE(), 1, 1)");
    }
    $_POST = ['save' => '1'];
    ob_start();
    register_shutdown_function(function () use ($conn, $scenario) {
        $printed = ob_get_clean();
        $row = $conn->query('SELECT * FROM tb_closingan')->fetch_assoc();
        check((int)$row['detailing'] === 210000 && (int)$row['total_penjualan'] === 260000, 'Persisted closing values');
        check(str_contains($printed, 'Detailing: Rp 210.000'), 'Printed detailing closing');
        check((int)$conn->query('SELECT COUNT(*) AS n FROM tb_closingan')->fetch_assoc()['n'] === 1, 'Single closing row');
        echo "PASS: $scenario and printed division total\n";
    });
    require 'include/report/closingan_preview.php';
} else {
    throw new RuntimeException('Unknown scenario');
}
