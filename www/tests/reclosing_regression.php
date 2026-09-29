<?php
if (PHP_SAPI !== 'cli') exit;
require __DIR__.'/../config/config.php';
define('MOBILE_REPORTS_TEST', true);
require __DIR__.'/../api/mobile/mobile-reports.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
// Every write is isolated to connection-local temporary tables.
foreach (['tb_closingan','mobile_report_requests','whatsapp_closing_notifications','olsera_sync_jobs'] as $table) {
    $ddl=$conn->query("SHOW CREATE TABLE `$table`")->fetch_row()[1];
    $conn->query(preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $ddl));
}
$conn->query('CREATE TEMPORARY TABLE orders(id INT PRIMARY KEY,created_at DATETIME,status_order VARCHAR(20),paid_amount INT,total_amount INT,payment_method VARCHAR(30),cancel_reason VARCHAR(255),canceled_at DATETIME)');
foreach (['order_items','order_carwash','order_detailing'] as $table) $conn->query("CREATE TEMPORARY TABLE $table(id_tr INT,total INT,id INT DEFAULT 1,quantity INT DEFAULT 1,qty INT DEFAULT 1)");
$conn->query('CREATE TEMPORARY TABLE pengeluaran(id INT AUTO_INCREMENT PRIMARY KEY,keterangan VARCHAR(255),total INT,created_at DATETIME)');
$conn->query('CREATE TEMPORARY TABLE vw_stock_balances(id INT,name VARCHAR(100),current_quantity DECIMAL(12,3),unit VARCHAR(20),minimum_quantity DECIMAL(12,3),active INT)');
function checkReclosing(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$date=date('Y-m-d');
$conn->query("INSERT INTO orders VALUES(1,'$date 00:00:00','PAID',100,100,'cash',NULL,NULL)");
$conn->query('INSERT INTO order_items(id_tr,total) VALUES(1,100)');
$id=saveClosingSnapshot($conn,reportClosing($conn,$date));
waEnqueueClosing($conn,$date);
$conn->query("UPDATE whatsapp_closing_notifications SET summary_state='sent',stock_state='sent',summary_sent_at=NOW(),stock_sent_at=NOW()");
$originalQueue=$conn->query('SELECT * FROM whatsapp_closing_notifications')->fetch_assoc();
$input=['date'=>$date,'request_id'=>'11111111-1111-4111-8111-111111111111','expenses'=>[['keterangan'=>'Es uji','total'=>10]]];
$conn->query("INSERT INTO orders VALUES(2,'$date 00:01:00','PAID',200,200,'qris',NULL,NULL)");
$conn->query('INSERT INTO order_carwash(id_tr,total) VALUES(2,200)');
$result=reportSaveClosing($conn,$input,1)['closing'];
checkReclosing($result['id']===$id && $result['total_penjualan']===300 && $result['carwash']===200,'Mobile after web must refresh original ID');
$conn->query("INSERT INTO orders VALUES(3,'$date 00:02:00','PAID',300,300,'credit_card',NULL,NULL)");
$conn->query('INSERT INTO order_detailing(id_tr,total) VALUES(3,300)');
$result=reportSaveClosing($conn,$input,1)['closing'];
checkReclosing($result['id']===$id && $result['total_penjualan']===600 && $result['detailing']===300,'Same request retry must refresh latest sales');
$input['request_id']='22222222-2222-4222-8222-222222222222';
reportSaveClosing($conn,$input,2);
checkReclosing((int)$conn->query('SELECT COUNT(*) FROM pengeluaran')->fetch_row()[0]===1,'Repeated payload duplicated expense');
checkReclosing(saveClosingSnapshot($conn,reportClosing($conn,$date))===$id,'Web after mobile changed ID');
waEnqueueClosing($conn,$date);
checkReclosing((int)$conn->query('SELECT COUNT(*) FROM tb_closingan')->fetch_row()[0]===1,'Duplicate closing row');
checkReclosing((int)$conn->query('SELECT total_penjualan FROM tb_closingan')->fetch_row()[0]===600,'Closing not updated');
checkReclosing($conn->query('SELECT * FROM whatsapp_closing_notifications')->fetch_assoc()===$originalQueue,'Reclosing changed sent notification');
foreach (['summary','stock'] as $part) {
    $claim=waClosingAction($conn,['date'=>$date,'part'=>$part,'action'=>'claim','body'=>'duplicate']);
    checkReclosing(!$claim['claimed'],'Reclosing permitted duplicate WhatsApp');
}
$input['expenses'][0]['total']=20;
try { reportSaveClosing($conn,$input,2); throw new RuntimeException('Changed retry was accepted'); }
catch (InvalidArgumentException $expected) {}
echo "PASS: web/mobile same ID, latest sales on retry, expense deduplication, no WhatsApp resend\n";
