<?php
if (PHP_SAPI!=='cli') exit;
require __DIR__.'/../config/config.php';require __DIR__.'/../config/whatsapp_closing.php';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
// Temporary clones never modify production inventory or send notifications.
foreach (['olsera_sync_jobs','olsera_imports','olsera_sales','olsera_stock_usage','stock_items','stock_product_bindings','stock_movements','whatsapp_closing_notifications'] as $t) {
    $conn->query("CREATE TEMPORARY TABLE temp_$t LIKE $t");$conn->query("CREATE TEMPORARY TABLE $t LIKE temp_$t");
}
$conn->query('CREATE TEMPORARY TABLE tb_datacafe(id_prod INT,nama_prod VARCHAR(200))');
$conn->query('CREATE TEMPORARY TABLE tb_datacarwash(id_produk INT,produk VARCHAR(200))');
$conn->query('CREATE TEMPORARY TABLE tb_datadetailing(id_produk INT,produk VARCHAR(200))');
$conn->query('CREATE TEMPORARY TABLE vw_stock_balances(id INT,name VARCHAR(100),current_quantity DECIMAL(12,3),unit VARCHAR(20),minimum_quantity DECIMAL(12,3),active INT)');
$conn->query("INSERT INTO tb_datacafe VALUES(1,'MIE DE\'JATI'),(2,'Kopi')");
$conn->query("INSERT INTO tb_datacarwash VALUES(3,'MOBIL - HIDROLIK')");
$conn->query("INSERT INTO tb_datadetailing VALUES(4,'Poles')");
$conn->query("INSERT INTO stock_items(id,name,initial_quantity,unit) VALUES(1,'Bahan',10,'pcs'),(2,'Beans',100,'gr')");
$conn->query("INSERT INTO stock_product_bindings(stock_item_id,product_id,quantity_per_sale) VALUES(1,1,0.5),(2,2,20)");
$conn->query("INSERT INTO vw_stock_balances VALUES(1,'Bahan',7,'pcs',5,1)");
$date=date('Y-m-d');
$payload=['date'=>$date,'total_penjualan'=>100,'cafe'=>90,'carwash'=>0,'detailing'=>10,'dejatiNet'=>['cafe'=>80,'carwash'=>0,'detailing'=>20],'stock'=>['items'=>[]]];
olseraQuery($conn,'INSERT INTO whatsapp_closing_notifications(closing_date,group_id,payload) VALUES (?,?,?)','sss',[$date,'test@g.us',json_encode($payload)]);
function verify($ok,$message) {if(!$ok)throw new RuntimeException($message);}
function row($name,$qty,$total,$group='Meals',$variant='') {return ['product'=>$name,'variant'=>$variant,'group'=>$group,'sku'=>'','currency'=>'IDR','quantity'=>$qty,'gross_sales'=>$total,'discount_amount'=>0,'return_amount'=>0,'total_sales'=>$total];}
function fixture($date,$lease,$rows) {return ['date'=>$date,'lease'=>$lease,'report_date'=>$date,'store'=>'dejaticoffeegarden.myolsera.com','file_name'=>'Penjualan berdasarkan SKU-'.$date.'__'.$date.'.xlsx','sha256'=>str_repeat('a',64),'fetched_at'=>date(DATE_ATOM),'rows'=>$rows];}
function runImport($db,$date,$rows) {$db->begin_transaction();olseraEnqueue($db,$date,true);$job=olseraClaim($db);verify($job!==null,'Missing job');$r=olseraImport($db,fixture($date,$job['lease'],$rows));$db->commit();return $r;}
$rows=[row('MIE DE&#039;JATI',6,600),row('Kopi',2,200,'Coffee','ICE'),row('MOBIL',1,500,'Dejati Car Wash','HIDROLIK'),row('Poles',1,900,'Detailing')];
$conn->begin_transaction();olseraEnqueue($conn,$date);$job=olseraClaim($conn);
verify(!waClosingAction($conn,['date'=>$date,'part'=>'summary','action'=>'claim','body'=>'early'])['claimed'],'Message sent early');
$wrong=fixture($date,'wrong',$rows);try{olseraImport($conn,$wrong);throw new Exception('Wrong lease accepted');}catch(RuntimeException $expected){}
$conn->rollback();
$r=runImport($conn,$date,$rows);verify($r['summary']['total']===2200.0,'Totals wrong');
verify((float)$conn->query('SELECT SUM(quantity_delta) FROM stock_movements WHERE stock_item_id=1')->fetch_row()[0]===-3.0,'Fractional rule not used');
verify((float)$conn->query('SELECT SUM(quantity_delta) FROM stock_movements WHERE stock_item_id=2')->fetch_row()[0]===-40.0,'Beans rule not used');
$p=json_decode($conn->query('SELECT payload FROM whatsapp_closing_notifications')->fetch_row()[0],true);
verify($p['combined']['total']==2300&&$p['combined']['cafe']==880,'Combined allocation wrong');
verify(count($p['stock']['items'])===1,'Stock not refreshed');
runImport($conn,$date,$rows);verify((int)$conn->query('SELECT COUNT(*) FROM stock_movements')->fetch_row()[0]===2,'Duplicate import deducted twice');
// Cumulative re-export adds only the difference, using original recipe even if binding changes.
$conn->query('UPDATE stock_product_bindings SET quantity_per_sale=99 WHERE product_id=1');
$rows[0]['quantity']=8;$rows[0]['total_sales']=800;$rows[0]['gross_sales']=800;
runImport($conn,$date,$rows);verify((float)$conn->query('SELECT SUM(quantity_delta) FROM stock_movements WHERE stock_item_id=1')->fetch_row()[0]===-4.0,'Delta or frozen recipe wrong');
// Removed sale reverses only its previous deduction.
array_splice($rows,1,1);runImport($conn,$date,$rows);
verify((float)$conn->query('SELECT SUM(quantity_delta) FROM stock_movements WHERE stock_item_id=2')->fetch_row()[0]===0.0,'Removed product not restored');
$before=$conn->query('SELECT COUNT(*) FROM olsera_imports')->fetch_row()[0];
$conn->begin_transaction();olseraEnqueue($conn,$date,true);$job=olseraClaim($conn);
try {olseraImport($conn,fixture($date,$job['lease'],[row('Tidak Ada',1,999)]));throw new Exception('Unknown accepted');}catch(InvalidArgumentException $expected){$conn->rollback();}
verify($before===$conn->query('SELECT COUNT(*) FROM olsera_imports')->fetch_row()[0],'Failed import persisted');
$conn->query("UPDATE whatsapp_closing_notifications SET summary_state='sent',stock_state='sent'");
$frozen=$conn->query('SELECT payload FROM whatsapp_closing_notifications')->fetch_row()[0];
runImport($conn,$date,$rows);verify($frozen===$conn->query('SELECT payload FROM whatsapp_closing_notifications')->fetch_row()[0],'Sent notification changed');
$bad=$rows;$bad[0]['return_amount']=100;
try{olseraValidateRows($bad,olseraCatalog($conn));throw new Exception('Return accepted without stock qty');}catch(InvalidArgumentException $expected){}
echo "PASS: matching, cafe/carwash/detailing, fractional/beans rules, replay, delta/reversal, atomic failure, lease, ordered notification, frozen sent messages\n";
