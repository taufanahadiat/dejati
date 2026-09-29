<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/session.php';
session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/stock_threshold.php';

if (empty($_SESSION['loggedin']) || ($_SESSION['level'] ?? '') !== 'Administrator') {
    http_response_code(403);
    exit('Akses ditolak.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals((string)($_SESSION['stock_csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) {
    http_response_code(400);
    exit('Permintaan tidak valid.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function postedIds(string $key): array {
    return array_values(array_unique(array_filter(array_map('intval', (array)($_POST[$key] ?? [])), fn($id) => $id > 0)));
}

try {
    if (($_POST['action'] ?? '') === 'add_item') {
        $name = trim((string)($_POST['name'] ?? ''));
        $unit = trim((string)($_POST['unit'] ?? 'pcs'));
        $quantityText = str_replace(',', '.', trim((string)($_POST['quantity'] ?? '0')));
        if ($name === '' || mb_strlen($name) > 100 || !in_array($unit, ['pcs', 'gr'], true) || !is_numeric($quantityText) || abs((float)$quantityText) > 999999999) {
            throw new InvalidArgumentException('Nama dan jumlah awal stok tidak valid.');
        }
        $minimum = stockMinimumQuantity($_POST['minimum_quantity'] ?? null, $unit);
        $quantity = round((float)$quantityText, 3);
        $conn->begin_transaction();
        $zero = 0.0;
        $stmt = $conn->prepare('INSERT INTO stock_items (name,initial_quantity,unit,minimum_quantity) VALUES (?,?,?,?)');
        $stmt->bind_param('sdsd', $name, $zero, $unit, $minimum);
        $stmt->execute();
        $stockId = $conn->insert_id;
        if (abs($quantity) >= 0.0005) {
            $stockIn = max(0, $quantity);
            $key = 'adjustment:' . bin2hex(random_bytes(16));
            $note = 'Stok awal item baru';
            $userId = (int)($_SESSION['id_user'] ?? 0) ?: null;
            $stmt = $conn->prepare("INSERT INTO stock_movements (stock_item_id,movement_type,quantity_delta,stock_in_quantity,movement_key,note,created_by) VALUES (?,'adjustment',?,?,?,?,?)");
            $stmt->bind_param('iddssi', $stockId, $quantity, $stockIn, $key, $note, $userId);
            $stmt->execute();
        }
        $conn->commit();
        $_SESSION['stock_success'] = 'Item stok ' . $name . ' berhasil ditambahkan.';
    } elseif (($_POST['action'] ?? '') === 'edit_stock_item') {
        $stockId = filter_var($_POST['stock_item_id'] ?? null, FILTER_VALIDATE_INT);
        $name = trim((string)($_POST['name'] ?? ''));
        $unit = trim((string)($_POST['unit'] ?? 'pcs'));
        $adjustmentType = trim((string)($_POST['adjustment_type'] ?? 'surplus'));
        $adjustmentText = str_replace(',', '.', trim((string)($_POST['adjustment_quantity'] ?? '0')));
        $productIds = postedIds('product_ids');
        if (!$stockId || $name === '' || mb_strlen($name) > 100 || !in_array($unit, ['pcs', 'gr'], true) || !in_array($adjustmentType, ['surplus', 'deficit'], true) || !is_numeric($adjustmentText) || (float)$adjustmentText < 0 || (float)$adjustmentText > 999999999) {
            throw new InvalidArgumentException('Nama, perubahan jumlah, atau unit stok tidak valid.');
        }
        $adjustment = round((float)$adjustmentText, 3);
        $note = trim((string)($_POST['note'] ?? ''));
        if (strlen($note) > 180) throw new InvalidArgumentException('Catatan maksimal 180 karakter.');

        $conn->begin_transaction();
        $stmt = $conn->prepare('SELECT name,initial_quantity,minimum_quantity FROM stock_items WHERE id=? FOR UPDATE');
        $stmt->bind_param('i', $stockId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) throw new InvalidArgumentException('Item stok tidak ditemukan.');
        $minimum = stockMinimumQuantity($_POST['minimum_quantity'] ?? $row['minimum_quantity'], $unit);
        $stmt = $conn->prepare('UPDATE stock_items SET name=?,unit=?,minimum_quantity=? WHERE id=?');
        $stmt->bind_param('ssdi', $name, $unit, $minimum, $stockId);
        $stmt->execute();
        $stmt = $conn->prepare('SELECT COALESCE(SUM(quantity_delta),0) movement_total FROM stock_movements WHERE stock_item_id=?');
        $stmt->bind_param('i', $stockId);
        $stmt->execute();
        $current = (float)$row['initial_quantity'] + (float)$stmt->get_result()->fetch_assoc()['movement_total'];
        $delta = $adjustmentType === 'surplus' ? $adjustment : -$adjustment;
        $target = round($current + $delta, 3);
        if (abs($delta) >= 0.0005) {
            $stockIn = round(max(0, $target - max(0, $current)), 3);
            $key = 'adjustment:' . bin2hex(random_bytes(16));
            $description = $note !== '' ? $note : 'Koreksi stok dari ' . $current . ' menjadi ' . $target;
            $userId = (int)($_SESSION['id_user'] ?? 0) ?: null;
            $stmt = $conn->prepare("INSERT INTO stock_movements (stock_item_id,movement_type,quantity_delta,stock_in_quantity,movement_key,note,created_by) VALUES (?,'adjustment',?,?,?,?,?)");
            $stmt->bind_param('iddssi', $stockId, $delta, $stockIn, $key, $description, $userId);
            $stmt->execute();
        }

        $existingUsage = [];
        $stmt = $conn->prepare('SELECT product_id,quantity_per_sale FROM stock_product_bindings WHERE stock_item_id=?');
        $stmt->bind_param('i', $stockId);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $binding) $existingUsage[(int)$binding['product_id']] = (float)$binding['quantity_per_sale'];
        $stmt = $conn->prepare('DELETE FROM stock_product_bindings WHERE stock_item_id=?');
        $stmt->bind_param('i', $stockId);
        $stmt->execute();
        $validProduct = $conn->prepare('SELECT id_prod FROM tb_datacafe WHERE id_prod=?');
        $insertBinding = $conn->prepare('INSERT INTO stock_product_bindings (stock_item_id,product_id,quantity_per_sale) VALUES (?,?,?)');
        foreach ($productIds as $productId) {
            $validProduct->bind_param('i', $productId);
            $validProduct->execute();
            if (!$validProduct->get_result()->fetch_assoc()) throw new InvalidArgumentException('Produk cafe tidak ditemukan.');
            $usage = $existingUsage[$productId] ?? 1.0;
            $insertBinding->bind_param('iid', $stockId, $productId, $usage);
            $insertBinding->execute();
        }
        $conn->commit();
        $_SESSION['stock_success'] = 'Stok ' . $name . ' berhasil diperbarui.';
    } elseif (($_POST['action'] ?? '') === 'save_binding') {
        $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
        $usageText = str_replace(',', '.', trim((string)($_POST['quantity_per_sale'] ?? '1')));
        $stockIds = postedIds('stock_item_ids');
        if (!$productId || !is_numeric($usageText) || (float)$usageText <= 0 || (float)$usageText > 9999) {
            throw new InvalidArgumentException('Binding produk tidak valid.');
        }
        $usage = round((float)$usageText, 3);
        $conn->begin_transaction();
        $stmt = $conn->prepare('SELECT nama_prod FROM tb_datacafe WHERE id_prod=? LIMIT 1');
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        if (!$product) throw new InvalidArgumentException('Produk tidak ditemukan.');
        $stmt = $conn->prepare('DELETE FROM stock_product_bindings WHERE product_id=?');
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        if ($stockIds) {
            $valid = $conn->prepare('SELECT id FROM stock_items WHERE id=? AND active=1');
            $insert = $conn->prepare('INSERT INTO stock_product_bindings (stock_item_id,product_id,quantity_per_sale) VALUES (?,?,?)');
            foreach ($stockIds as $stockId) {
                $valid->bind_param('i', $stockId);
                $valid->execute();
                if (!$valid->get_result()->fetch_assoc()) throw new InvalidArgumentException('Item stok tidak ditemukan.');
                $insert->bind_param('iid', $stockId, $productId, $usage);
                $insert->execute();
            }
        }
        $conn->commit();
        $_SESSION['stock_success'] = 'Binding ' . $product['nama_prod'] . ' berhasil diperbarui.';
    } else {
        throw new InvalidArgumentException('Aksi tidak dikenali.');
    }
} catch (Throwable $error) {
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    $_SESSION['stock_error'] = $error instanceof InvalidArgumentException ? $error->getMessage() : 'Perubahan stok gagal disimpan.';
}

header('Location: /main.php?id=stockManagement');
exit;
