<?php
require __DIR__.'/../config/config.php';require __DIR__.'/../config/whatsapp_closing.php';
$conn->query('CREATE TEMPORARY TABLE test_olsera_jobs LIKE olsera_sync_jobs');
$conn->query('CREATE TEMPORARY TABLE olsera_sync_jobs LIKE test_olsera_jobs');
foreach (['order_items','order_carwash','order_detailing'] as $t) $conn->query("CREATE TEMPORARY TABLE $t(id INT,id_tr INT,total INT,quantity INT,qty INT)");
$conn->query('CREATE TEMPORARY TABLE test_wa_queue LIKE whatsapp_closing_notifications');
$conn->query('CREATE TEMPORARY TABLE whatsapp_closing_notifications LIKE test_wa_queue');
$conn->query('CREATE TEMPORARY TABLE tb_closingan(id INT,tanggal DATE,created_at DATETIME,total_penjualan INT,cash INT,qris INT,card INT,cafe INT,carwash INT,detailing INT,detail_pengeluaran JSON)');
$conn->query('CREATE TEMPORARY TABLE orders(created_at DATETIME,status_order VARCHAR(20),paid_amount INT,total_amount INT,payment_method VARCHAR(30),id INT,cancel_reason VARCHAR(255),canceled_at DATETIME)');
$conn->query('CREATE TEMPORARY TABLE vw_stock_balances(id INT,name VARCHAR(100),current_quantity DECIMAL(12,3),unit VARCHAR(20),minimum_quantity DECIMAL(12,3),active INT)');
$date=date('Y-m-d');
waEnqueueClosing($conn,$date,false);
if((int)$conn->query('SELECT COUNT(*) FROM olsera_sync_jobs')->fetch_row()[0]!==0)throw new RuntimeException('Polling created a job without closing');
$conn->query("INSERT INTO tb_closingan VALUES(1,'$date','$date 21:00:00',100,20,70,10,60,30,10,JSON_ARRAY(JSON_OBJECT('keterangan','Beli es','total',15)))");
$conn->query("INSERT INTO orders(created_at,status_order,paid_amount,total_amount,payment_method) VALUES('$date 12:00:00','PAID',100,100,'cash'),('$date 13:00:00','CANCEL',50,50,'qris'),('$date 14:00:00','OPEN BILL',0,50,'qris'),('$date 22:00:00','PAID',50,50,'cash')");
$conn->query("UPDATE orders SET id=7,cancel_reason='Salah pesanan',canceled_at='$date 13:30:00' WHERE status_order='CANCEL'");
$conn->query("INSERT INTO orders VALUES('$date 14:00:00','CANCEL',10,10,'cash',8,'Sesudah closing','$date 22:00:00')");
$conn->query("INSERT INTO vw_stock_balances VALUES(1,'Stok tes',0,'pcs',5,1)");
$conn->begin_transaction();waEnqueueClosing($conn,$date);$conn->rollback();
if((int)$conn->query('SELECT COUNT(*) n FROM whatsapp_closing_notifications')->fetch_assoc()['n']!==0)throw new RuntimeException('Rollback leaked notification');
$conn->begin_transaction();waEnqueueClosing($conn,$date);waEnqueueClosing($conn,$date);$conn->commit();
$row=$conn->query('SELECT * FROM whatsapp_closing_notifications')->fetch_assoc();$p=json_decode($row['payload'],true);
if($p['transactions']!==1||$p['total_penjualan']!==100||count($p['stock']['items'])!==1)throw new RuntimeException('Snapshot incorrect');
if(count($p['cancelled'])!==1 || (int)$p['cancelled'][0]['id']!==7 || (int)$p['expenses'][0]['total']!==15)throw new RuntimeException('Cancelled/expense snapshot incorrect');
if((int)$conn->query('SELECT COUNT(*) n FROM whatsapp_closing_notifications')->fetch_assoc()['n']!==1)throw new RuntimeException('Duplicate queue');
$conn->begin_transaction();
$r=waClosingAction($conn,['date'=>$date,'part'=>'stock','action'=>'claim','body'=>'second']);if($r['claimed'])throw new RuntimeException('Second sent early');
$blocked=waClosingAction($conn,['date'=>$date,'part'=>'summary','action'=>'claim','body'=>'early']);if($blocked['claimed'])throw new RuntimeException('Sent before Olsera sync');
$conn->query("UPDATE olsera_sync_jobs SET state='succeeded'");
$r=waClosingAction($conn,['date'=>$date,'part'=>'summary','action'=>'claim','body'=>'first']);if(!$r['claimed'])throw new RuntimeException('First not claimed');
$r=waClosingAction($conn,['date'=>$date,'part'=>'summary','action'=>'claim','body'=>'first']);if($r['claimed'])throw new RuntimeException('Duplicate claim');
waClosingAction($conn,['date'=>$date,'part'=>'summary','action'=>'sent']);
$r=waClosingAction($conn,['date'=>$date,'part'=>'stock','action'=>'claim','body'=>'second']);if(!$r['claimed'])throw new RuntimeException('Second not claimed');
waClosingAction($conn,['date'=>$date,'part'=>'stock','action'=>'sent']);
$conn->commit();
// Saving the same date again must preserve the original snapshot and both sent states.
$conn->query('UPDATE tb_closingan SET total_penjualan=999');
waEnqueueClosing($conn,$date);
$after=$conn->query('SELECT * FROM whatsapp_closing_notifications')->fetch_assoc();
if($after['summary_state']!=='sent'||$after['stock_state']!=='sent'||$after['payload']!==$row['payload'])throw new RuntimeException('Reclosing reset notification');
foreach(['summary','stock'] as $part){
    $conn->begin_transaction();$r=waClosingAction($conn,['date'=>$date,'part'=>$part,'action'=>'claim','body'=>'duplicate']);$conn->commit();
    if($r['claimed'])throw new RuntimeException('Sent message claimed again');
}
echo "PASS: atomic queue, paid count at closing, frozen stock, once per date and ordered claims\n";
