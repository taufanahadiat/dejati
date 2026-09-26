<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/session.php';
session_start();
require_once __DIR__ . '/../../../config/config.php';

if (empty($_SESSION['loggedin']) || ($_SESSION['level'] ?? '') !== 'Administrator') {
    http_response_code(403);
    exit('Akses ditolak.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals((string)($_SESSION['stock_csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) {
    http_response_code(400);
    exit('Permintaan tidak valid.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    if (($_POST['action'] ?? '') === 'add_item') {
        $name = trim((string)($_POST['name'] ?? ''));
        $quantityText = str_replace(',', '.', trim((string)($_POST['quantity'] ?? '0')));
        if ($name === '' || mb_strlen($name) > 100 || !is_numeric($quantityText) || abs((float)$quantityText) > 999999999) {
            throw new InvalidArgumentException('Nama dan jumlah awal stok tidak valid.');
        }
        $quantity = round((float)$quantityText, 3);
        $stmt = $conn->prepare("INSERT INTO stock_items (name,initial_quantity,unit) VALUES (?,?,'pcs')");
        $stmt->bind_param('sd', $name, $quantity);
        $stmt->execute();
        $_SESSION['stock_success'] = 'Item stok ' . $name . ' berhasil ditambahkan.';
    } elseif (($_POST['action'] ?? '') === 'set_quantity') {
        $stockId = filter_var($_POST['stock_item_id'] ?? null, FILTER_VALIDATE_INT);
        $quantityText = str_replace(',', '.', trim((string)($_POST['quantity'] ?? '')));
        if (!$stockId || !is_numeric($quantityText) || abs((float)$quantityText) > 999999999) {
            throw new InvalidArgumentException('Jumlah stok tidak valid.');
        }
        $target = round((float)$quantityText, 3);
        $note = trim((string)($_POST['note'] ?? ''));
        if (strlen($note) > 180) throw new InvalidArgumentException('Catatan maksimal 180 karakter.');

        $conn->begin_transaction();
        $stmt = $conn->prepare('SELECT s.name, s.initial_quantity + COALESCE(SUM(m.quantity_delta),0) current_quantity FROM stock_items s LEFT JOIN stock_movements m ON m.stock_item_id=s.id WHERE s.id=? GROUP BY s.id,s.name,s.initial_quantity FOR UPDATE');
        $stmt->bind_param('i', $stockId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) throw new InvalidArgumentException('Item stok tidak ditemukan.');
        $delta = round($target - (float)$row['current_quantity'], 3);
        if (abs($delta) >= 0.0005) {
            $key = 'adjustment:' . bin2hex(random_bytes(16));
            $description = $note !== '' ? $note : 'Koreksi stok manual menjadi ' . $target;
            $userId = (int)($_SESSION['id_user'] ?? 0) ?: null;
            $stmt = $conn->prepare("INSERT INTO stock_movements (stock_item_id,movement_type,quantity_delta,movement_key,note,created_by) VALUES (?,'adjustment',?,?,?,?)");
            $stmt->bind_param('idssi', $stockId, $delta, $key, $description, $userId);
            $stmt->execute();
        }
        $conn->commit();
        $_SESSION['stock_success'] = 'Stok ' . $row['name'] . ' berhasil diperbarui.';
    } elseif (($_POST['action'] ?? '') === 'save_binding') {
        $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
        $usageText = str_replace(',', '.', trim((string)($_POST['quantity_per_sale'] ?? '1')));
        $stockIds = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['stock_item_ids'] ?? [])))));
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
