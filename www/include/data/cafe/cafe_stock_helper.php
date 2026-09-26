<?php
declare(strict_types=1);

function cafe_parse_stock_binding_input(mysqli $conn): array
{
    if (($_POST['stock_management'] ?? 'no') !== 'yes') {
        return ['enabled' => false, 'stock_ids' => [], 'usage' => 1.0];
    }

    $stockIds = array_values(array_unique(array_filter(
        array_map('intval', (array)($_POST['stock_item_ids'] ?? [])),
        static fn(int $id): bool => $id > 0
    )));
    $usageText = str_replace(',', '.', trim((string)($_POST['stock_usage'] ?? '1')));
    if (!$stockIds || !is_numeric($usageText) || (float)$usageText <= 0 || (float)$usageText > 9999) {
        throw new InvalidArgumentException('Pilih minimal satu item stok dan isi jumlah pemakaian dengan benar.');
    }

    $valid = $conn->prepare('SELECT id FROM stock_items WHERE id=? AND active=1');
    foreach ($stockIds as $stockId) {
        $valid->bind_param('i', $stockId);
        $valid->execute();
        if (!$valid->get_result()->fetch_assoc()) {
            throw new InvalidArgumentException('Item stok yang dipilih tidak valid.');
        }
    }

    return ['enabled' => true, 'stock_ids' => $stockIds, 'usage' => round((float)$usageText, 3)];
}

function cafe_replace_stock_bindings(mysqli $conn, int $productId, array $binding): void
{
    $delete = $conn->prepare('DELETE FROM stock_product_bindings WHERE product_id=?');
    $delete->bind_param('i', $productId);
    $delete->execute();
    if (!$binding['enabled']) return;

    $insert = $conn->prepare('INSERT INTO stock_product_bindings (stock_item_id,product_id,quantity_per_sale) VALUES (?,?,?)');
    foreach ($binding['stock_ids'] as $stockId) {
        $usage = $binding['usage'];
        $insert->bind_param('iid', $stockId, $productId, $usage);
        $insert->execute();
    }
}
