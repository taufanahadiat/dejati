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
    'add_unit_options' => str_contains($html, 'id="stockUnit"') && str_contains($html, '<option value="gr">gr</option>'),
    'edit_unit_options' => str_contains($html, 'id="editStockUnit"') && str_contains($html, "$('#editStockUnit').val(button.attr('data-unit'))"),
    'edit_binding_modal' => str_contains($html, 'editBindingStocks'),
    'bound_product_badges' => str_contains($html, 'bound-product-badge'),
    'removable_selections' => str_contains($html, 'allowClear:true,closeOnSelect:false'),
    'selects_initialize_on_modal_open' => str_contains($html, "on('show.bs.modal'") && str_contains($html, "select2('destroy')"),
    'correction_note_removed' => !str_contains($html, 'editStockNote'),
    'binding_category_removed' => !str_contains($html, '<th>Kategori</th>'),
    'binding_usage_removed' => !str_contains($html, '<th>Pemakaian</th>'),
    'stock_quantity_third' => str_contains($html, '<th>Produk Terikat</th><th class="text-right">Jumlah</th>'),
    'product_select_has_options' => substr_count($html, '<option value=') > 100,
    'transaction_history' => str_contains($html, 'ID Transaksi'),
    'note_column_removed' => !str_contains($html, '<th>Catatan</th>'),
    'initial_item' => str_contains($html, 'Ayam Bakar'),
    'negative_stock' => str_contains($html, '-44'),
];
if (in_array(false, $checks, true)) {
    fwrite(STDERR, json_encode($checks, JSON_PRETTY_PRINT) . PHP_EOL);
    exit(1);
}
echo json_encode(['ok' => true, 'checks' => $checks, 'rendered_bytes' => strlen($html)], JSON_PRETTY_PRINT) . PHP_EOL;
