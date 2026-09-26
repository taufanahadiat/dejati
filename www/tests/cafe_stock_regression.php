<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../include/data/cafe/cafe_stock_helper.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function cafeStockAssert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$productId = 190;
$stockIds = array_map('intval', array_column(
    $conn->query('SELECT id FROM stock_items WHERE active=1 ORDER BY id LIMIT 2')->fetch_all(MYSQLI_ASSOC),
    'id'
));

$conn->begin_transaction();
try {
    $_POST = ['stock_management'=>'yes','stock_item_ids'=>$stockIds,'stock_usage'=>'1.5'];
    $binding = cafe_parse_stock_binding_input($conn);
    cafe_replace_stock_bindings($conn, $productId, $binding);
    $rows = $conn->query("SELECT stock_item_id,quantity_per_sale FROM stock_product_bindings WHERE product_id=$productId ORDER BY stock_item_id")->fetch_all(MYSQLI_ASSOC);
    cafeStockAssert(count($rows) === 2, 'Multi-binding produk tidak tersimpan.');
    cafeStockAssert(count(array_filter($rows, fn($row) => abs((float)$row['quantity_per_sale'] - 1.5) < 0.0005)) === 2, 'Jumlah pemakaian tidak tersimpan.');

    $_POST = ['stock_management'=>'no'];
    cafe_replace_stock_bindings($conn, $productId, cafe_parse_stock_binding_input($conn));
    $remaining = (int)$conn->query("SELECT COUNT(*) total FROM stock_product_bindings WHERE product_id=$productId")->fetch_assoc()['total'];
    cafeStockAssert($remaining === 0, 'Pilihan Tidak tidak menghapus binding.');
    echo json_encode(['ok'=>true,'multi_binding'=>2,'usage'=>1.5,'disable_removes_bindings'=>true], JSON_PRETTY_PRINT).PHP_EOL;
} finally {
    $conn->rollback();
}
