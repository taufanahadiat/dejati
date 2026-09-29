<?php
require __DIR__.'/../config/config.php';require __DIR__.'/../config/whatsapp_stock_changes.php';
foreach(['stock_items','stock_movements','whatsapp_stock_changes'] as $table) {
    $conn->query("CREATE TEMPORARY TABLE test_copy_$table LIKE $table");
    $conn->query("CREATE TEMPORARY TABLE $table LIKE test_copy_$table");
}
$conn->query("INSERT INTO stock_items(id,name,initial_quantity,unit) VALUES(1,'Test Ayam',5,'pcs')");
function check($condition,$message){if(!$condition)throw new RuntimeException($message);}
function runChange($input){global $conn;$conn->begin_transaction();try{$r=whatsappStockChange($conn,$input);$conn->commit();return $r;}catch(Throwable $e){$conn->rollback();throw $e;}}
$base=['chat'=>'123@g.us','sender'=>'456@lid','token'=>hash('sha256','request1'),'action'=>'prepare','itemId'=>1,'mode'=>'add','amount'=>'2'];
$r=runChange($base);check($r['old']===5.0 && $r['new']===7.0,'Preview incorrect');
check((int)$conn->query('SELECT COUNT(*) n FROM stock_movements')->fetch_assoc()['n']===0,'Preview changed stock');
try{runChange(array_merge($base,['action'=>'confirm','sender'=>'999@lid','confirmationId'=>hash('sha256','c1')]));throw new RuntimeException('Wrong sender accepted');}catch(InvalidArgumentException $expected){}
$confirm=array_merge($base,['action'=>'confirm','confirmationId'=>hash('sha256','c1')]);
$r=runChange($confirm);check($r['status']==='applied' && $r['delta']===2.0,'Not applied');
runChange($confirm);runChange(array_merge($confirm,['confirmationId'=>hash('sha256','c2')]));
check((int)$conn->query('SELECT COUNT(*) n FROM stock_movements')->fetch_assoc()['n']===1,'Duplicate adjustment');
$base['token']=hash('sha256','request2');$base['mode']='subtract';$base['amount']='3';runChange($base);
$conn->query("INSERT INTO stock_movements(stock_item_id,movement_type,quantity_delta,movement_key) VALUES(1,'sale',-1,'test-sale')");
$confirm=array_merge($base,['action'=>'confirm','confirmationId'=>hash('sha256','changed1')]);
$r=runChange($confirm);check($r['status']==='changed' && $r['old']===6.0 && $r['new']===3.0,'Concurrent change not detected');
$r=runChange($confirm);check($r['status']==='changed','Duplicate confirmation applied refreshed preview');
$r=runChange(array_merge($confirm,['confirmationId'=>hash('sha256','changed2')]));check($r['status']==='applied' && $r['new']===3.0,'Fresh confirmation failed');
$base['token']=hash('sha256','request3');runChange($base);
$conn->query("UPDATE whatsapp_stock_changes SET expires_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE state='pending'");
$r=runChange(array_merge($base,['action'=>'confirm','confirmationId'=>hash('sha256','expired')]));check($r['status']==='expired','Expiry ignored');
$base['token']=hash('sha256','request4');runChange($base);runChange(array_merge($base,['action'=>'cancel']));
$r=runChange(array_merge($base,['action'=>'confirm','confirmationId'=>hash('sha256','cancelled')]));check($r['status']==='cancelled','Cancellation ignored');
echo "PASS: prepare is read-only for stock, actor binding, single ledger write, stale reconfirmation, expiry, cancellation\n";
