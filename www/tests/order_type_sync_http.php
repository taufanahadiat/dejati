<?php
// Run in lampp_web. Creates and removes an isolated database + HTTP server; never writes live sales.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../config/config.php';
$sourceDb = $conn->query('SELECT DATABASE() db')->fetch_assoc()['db'];
$suffix = bin2hex(random_bytes(5));
$testDb = 'test_order_type_' . $suffix;
$root = sys_get_temp_dir() . '/order-type-http-' . $suffix;
$process = null;
function expectSync($ok, $message) { if (!$ok) throw new RuntimeException($message); }
try {
    $conn->query("CREATE DATABASE `$testDb`");
    foreach (['orders','order_items','order_carwash','order_detailing','tb_closingan','pengeluaran'] as $table) {
        $conn->query("CREATE TABLE `$testDb`.`$table` LIKE `$sourceDb`.`$table`");
    }
    $conn->select_db($testDb);
    $conn->query('CREATE TABLE tb_user (id_user INT PRIMARY KEY, username VARCHAR(50), nama_user VARCHAR(50), level VARCHAR(50), status VARCHAR(20))');
    $conn->query("INSERT INTO tb_user VALUES (1,'test','Test','Administrator','Aktif')");
    $conn->query('CREATE TABLE mobile_api_tokens (token_hash CHAR(64) PRIMARY KEY, user_id INT, expires_at DATETIME, created_at DATETIME)');
    $token = bin2hex(random_bytes(32)); $hash = hash('sha256', $token);
    $conn->query("INSERT INTO mobile_api_tokens VALUES ('$hash',1,DATE_ADD(NOW(),INTERVAL 1 HOUR),NOW())");
    mkdir($root . '/api/mobile', 0700, true); mkdir($root . '/config', 0700, true);
    foreach (['index.php','mobile-reports.php'] as $file) copy(__DIR__ . '/../api/mobile/' . $file, $root . '/api/mobile/' . $file);
    copy(__DIR__ . '/../config/order_type.php', $root . '/config/order_type.php');
    file_put_contents($root . '/config/config.php', '<?php require ' . var_export(realpath(__DIR__ . '/../config/config.php'), true) . '; $conn->select_db(' . var_export($testDb, true) . ');');
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $address = stream_socket_get_name($socket, false); fclose($socket);
    $process = proc_open([PHP_BINARY,'-S',$address,'-t',$root], [0=>['pipe','r'],1=>['file',$root.'/server.log','a'],2=>['file',$root.'/server.log','a']], $pipes);
    for ($i=0; $i<50; $i++) { $probe=@stream_socket_client('tcp://'.$address); if ($probe) { fclose($probe); break; } usleep(20000); }
    $request = static function (string $path, ?array $body = null, int $expected = 200) use ($address,$token) {
        $context = stream_context_create(['http'=>['method'=>$body === null ? 'GET' : 'POST',
            'header'=>"Content-Type: application/json\r\nX-API-Token: $token", 'content'=>$body === null ? '' : json_encode($body), 'ignore_errors'=>true, 'timeout'=>10]]);
        $response = file_get_contents('http://'.$address.'/api/mobile/index.php?path='.$path, false, $context);
        expectSync(str_contains($http_response_header[0], (string)$expected), $path . ' status: ' . $http_response_header[0] . ' ' . $response);
        $json = json_decode($response, true);
        expectSync(is_array($json), 'Valid JSON response for ' . $path);
        return $json;
    };
    $payload = ['client_order_id'=>'00000000-0000-4000-8000-000000000001', 'table_number'=>'TEST', 'payment_method'=>'cash',
        'created_at'=>date(DATE_ATOM), 'items'=>[
            ['id'=>'1','name'=>'Coffee','qty'=>1,'unitPrice'=>10000,'cartType'=>'product','orderType'=>'dine-in'],
            ['id'=>'1','name'=>'Coffee','qty'=>2,'unitPrice'=>10000,'cartType'=>'product','order_type'=>'take_away'],
            ['id'=>'2','name'=>'Legacy','qty'=>1,'unitPrice'=>10000,'cartType'=>'product']
        ]];
    $saved = $request('orders', $payload);
    $history = $request('report-history');
    expectSync(array_column($history['orders'][0]['items'],'orderType') === ['dine-in','take-away',null], 'Android upload -> database -> download preserves types');
    expectSync($history['orders'][0]['clientOrderId'] === $payload['client_order_id'], 'Upload identity preserved');
    $duplicate = $request('orders', $payload);
    expectSync($duplicate['duplicate'] === true && $duplicate['order_id'] === $saved['order_id'], 'Idempotent retry');
    expectSync((int)$conn->query('SELECT COUNT(*) n FROM order_items')->fetch_assoc()['n'] === 3, 'Retry does not duplicate or replace item types');
    $orderId = (int)$saved['order_id'];
    $conn->query("UPDATE orders SET status_order='OPEN BILL',paid_amount=0,payment_method=NULL WHERE id=$orderId");
    $request('order-settle', ['id'=>$orderId, 'method'=>'cash', 'paid'=>40000]);
    $settled = $request('report-history');
    expectSync(array_column($settled['orders'][0]['items'],'orderType') === ['dine-in','take-away',null], 'Settlement preserves types');
    $payload['client_order_id'] = '00000000-0000-4000-8000-000000000002';
    $payload['items'][0]['orderType'] = 'invalid';
    $request('orders', $payload, 422);
    expectSync((int)$conn->query('SELECT COUNT(*) n FROM orders')->fetch_assoc()['n'] === 1, 'Invalid type rejected before writes');
    echo "PASS: Android upload/download, camel/snake aliases, legacy null, idempotent retry, settlement, invalid-type HTTP 422 without partial writes\n";
} finally {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    $conn->select_db($sourceDb);
    $conn->query("DROP DATABASE IF EXISTS `$testDb`");
    if (is_dir($root)) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($root);
    }
}
