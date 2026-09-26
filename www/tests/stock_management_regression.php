<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function stockAssert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$orderId = 0;
$itemId = 0;
$stockId = (int)$conn->query("SELECT stock_item_id FROM stock_product_bindings WHERE product_id=190 LIMIT 1")->fetch_assoc()['stock_item_id'];
$before = (float)$conn->query("SELECT current_quantity FROM vw_stock_balances WHERE id=$stockId")->fetch_assoc()['current_quantity'];
$conn->begin_transaction();

try {
    $conn->query("INSERT INTO orders (table_number,payment_method,total_amount,paid_amount,change_amount,status_order,created_at) VALUES ('STOCK-TEST',NULL,10000,0,0,'OPEN BILL',NOW())");
    $orderId = $conn->insert_id;
    $conn->query("INSERT INTO order_items (id_tr,id_prod,item_name,item_price,quantity,total,order_type) VALUES ($orderId,'190','PAKET SE\'I SAPI',5000,2,10000,'dine-in')");
    $itemId = $conn->insert_id;

    $movementCount = (int)$conn->query("SELECT COUNT(*) total FROM stock_movements WHERE order_id=$orderId")->fetch_assoc()['total'];
    stockAssert($movementCount === 0, 'Open bill tidak boleh mengurangi stok.');

    $conn->query("UPDATE orders SET status_order='PAID',payment_method='cash',paid_amount=10000 WHERE id=$orderId");
    $afterPaid = (float)$conn->query("SELECT current_quantity FROM vw_stock_balances WHERE id=$stockId")->fetch_assoc()['current_quantity'];
    stockAssert(abs($afterPaid - ($before - 2)) < 0.0005, 'Pembayaran tidak mengurangi stok tepat 2 unit.');

    $conn->query("UPDATE orders SET status_order='PAID' WHERE id=$orderId");
    $sales = (int)$conn->query("SELECT COUNT(*) total FROM stock_movements WHERE order_id=$orderId AND movement_type='sale'")->fetch_assoc()['total'];
    stockAssert($sales === 1, 'Update pembayaran berulang membuat pengurangan ganda.');

    $conn->query("UPDATE orders SET status_order='CANCEL',cancel_reason='Regression test',canceled_at=NOW() WHERE id=$orderId");
    $afterCancel = (float)$conn->query("SELECT current_quantity FROM vw_stock_balances WHERE id=$stockId")->fetch_assoc()['current_quantity'];
    stockAssert(abs($afterCancel - $before) < 0.0005, 'Pembatalan tidak mengembalikan stok.');

    $conn->query("UPDATE orders SET status_order='CANCEL' WHERE id=$orderId");
    $cancels = (int)$conn->query("SELECT COUNT(*) total FROM stock_movements WHERE order_id=$orderId AND movement_type='cancel'")->fetch_assoc()['total'];
    stockAssert($cancels === 1, 'Pembatalan berulang membuat pengembalian ganda.');

    $conn->query("INSERT INTO stock_items (name,initial_quantity,unit) VALUES ('STOCK NEGATIVE TEST',-5,'pcs')");
    $negativeStockId = $conn->insert_id;
    $conn->query("INSERT INTO stock_movements (stock_item_id,movement_type,quantity_delta,stock_in_quantity,movement_key,note) VALUES ($negativeStockId,'adjustment',8,3,CONCAT('test:',UUID()),'Regression test')");
    $negativeResult = $conn->query("SELECT current_quantity FROM vw_stock_balances WHERE id=$negativeStockId")->fetch_assoc();
    stockAssert(abs((float)$negativeResult['current_quantity'] - 3) < 0.0005, 'Koreksi stok minus tidak menghasilkan saldo akhir yang benar.');

    echo json_encode(['ok'=>true,'open_bill_movements'=>0,'sale_movements'=>$sales,'cancel_movements'=>$cancels,'balance_restored'=>$afterCancel,'negative_to_three_stock_in'=>3], JSON_PRETTY_PRINT) . PHP_EOL;
} finally {
    $conn->rollback();
}
