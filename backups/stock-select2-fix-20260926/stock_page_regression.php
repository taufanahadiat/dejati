<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
$_SESSION = ['level' => 'Administrator'];

ob_start();
include __DIR__ . '/../include/data/stock/index.php';
$html = ob_get_clean();

$checks = [
    'title' => str_contains($html, 'Stock Saat Ini'),
    'binding_tab' => str_contains($html, 'Binding Produk'),
    'history_tab' => str_contains($html, 'Riwayat'),
    'add_item' => str_contains($html, 'Tambah Item Stock'),
    'stock_in_card' => str_contains($html, 'Stock Masuk (Hari Ini)'),
    'out_of_stock_card' => str_contains($html, 'Stock Habis'),
    'edit_stock_modal' => str_contains($html, 'editStockProducts'),
    'edit_binding_modal' => str_contains($html, 'editBindingStocks'),
    'bound_product_badges' => str_contains($html, 'bound-product-badge'),
    'removable_selections' => str_contains($html, 'allowClear:true,closeOnSelect:false'),
    'correction_note_removed' => !str_contains($html, 'editStockNote'),
    'transaction_history' => str_contains($html, 'ID Transaksi'),
    'initial_item' => str_contains($html, 'Ayam Bakar'),
    'negative_stock' => str_contains($html, '-44'),
];
if (in_array(false, $checks, true)) {
    fwrite(STDERR, json_encode($checks, JSON_PRETTY_PRINT) . PHP_EOL);
    exit(1);
}
echo json_encode(['ok' => true, 'checks' => $checks, 'rendered_bytes' => strlen($html)], JSON_PRETTY_PRINT) . PHP_EOL;
