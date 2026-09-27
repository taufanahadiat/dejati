<?php
require __DIR__.'/../config/config.php';
require __DIR__.'/../config/stock_threshold.php';
foreach ([[null,'pcs',5.0],[null,'gr',50.0],['0','gr',0.0],['12,5','pcs',12.5]] as [$input,$unit,$expected]) {
    if (stockMinimumQuantity($input,$unit)!==$expected) throw new RuntimeException('Threshold parsing failed');
}
foreach (['-1','abc','1000000000','1.0001',[]] as $input) {
    try { stockMinimumQuantity($input,'pcs'); } catch (InvalidArgumentException $e) { continue; }
    throw new RuntimeException('Invalid threshold accepted');
}
$conn->begin_transaction();
try {
    $before=$conn->query('SELECT id,current_quantity FROM vw_stock_balances ORDER BY id LIMIT 1')->fetch_assoc();
    $id=(int)$before['id'];
    $conn->query("UPDATE stock_items SET minimum_quantity=12.500 WHERE id=$id");
    $after=$conn->query("SELECT minimum_quantity,current_quantity FROM vw_stock_balances WHERE id=$id")->fetch_assoc();
    if ((float)$after['minimum_quantity']!==12.5 || $before['current_quantity']!==$after['current_quantity']) throw new RuntimeException('Threshold persistence changed balance or view missing threshold');
} finally { $conn->rollback(); }
echo "PASS: default thresholds, validation, custom threshold visible without balance changes\n";
