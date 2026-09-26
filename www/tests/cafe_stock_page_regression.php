<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
$_SESSION = ['level'=>'Administrator','id_user'=>1];
$tableSource = file_get_contents(__DIR__ . '/../include/data/cafe/cafe_data_table.php');

ob_start();
include __DIR__ . '/../include/data/cafe/cafe_data_table.php';
$table = ob_get_clean();

$_GET = ['id_produk'=>190];
ob_start();
include __DIR__ . '/../include/data/cafe/cafe_data_form.php';
$form = ob_get_clean();

$checks = [
    'stock_column' => str_contains($table, '<th>Stock Item</th>'),
    'large_stock_badge' => str_contains($table, 'cafe-stock-badge'),
    'current_stock_in_badge' => str_contains($tableSource, 'sb.current_quantity') && !str_contains($tableSource, "CAST(spb.quantity_per_sale AS CHAR)"),
    'stock_quantity_rendered' => preg_match('/cafe-stock-badge[^>]*>.*?: <strong>-?[0-9.,]+(?: [^<]+)?<\/strong>/s', $table) === 1,
    'management_yes_no' => str_contains($form, 'stockManagementYes') && str_contains($form, 'stockManagementNo'),
    'bound_product_is_enabled' => preg_match('/id="stockManagementYes"[^>]*checked/', $form) === 1,
    'stock_select_has_all_items' => substr_count($form, '<option value=') >= 24,
    'bound_items_are_selected' => preg_match('/<option value="\d+" selected>/', $form) === 1,
    'select2_searchable_multi' => str_contains($form, 'closeOnSelect: false') && str_contains($form, 'allowClear: true'),
    'select2_uses_page_theme' => str_contains($form, 'form-control select2bs4'),
    'select2_restores_selection' => str_contains($form, "stockSelect.val(selected).trigger('change.select2')"),
    'select2_initializes_after_footer' => str_contains($form, 'window.setTimeout(initializeStockSelect, 0)'),
];
if (in_array(false, $checks, true)) {
    fwrite(STDERR, json_encode($checks, JSON_PRETTY_PRINT).PHP_EOL);
    exit(1);
}
echo json_encode(['ok'=>true,'checks'=>$checks,'table_bytes'=>strlen($table),'form_bytes'=>strlen($form)], JSON_PRETTY_PRINT).PHP_EOL;
