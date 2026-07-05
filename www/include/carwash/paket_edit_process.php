<?php
session_start();
require_once '../../config/config.php';

if (empty($_SESSION['loggedin'])) {
    header('Location: ../../');
    exit;
}

if ($_SESSION['level'] === 'Kasir') {
    header('Location: ../../main.php?id=transaksi');
    exit;
}

if (!isset($_POST['simpan'])) {
    header('Location: ../../main.php?id=carwashData');
    exit;
}

$id_produk = (int)($_POST['id_produk'] ?? 0);
$produk = trim(filter_input(INPUT_POST, 'produk', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$biaya = (int)str_replace('.', '', filter_input(INPUT_POST, 'biaya', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$updated_at = date('Y-m-d H:i:s');
$updated_by = $_SESSION['id_user'] ?? 0;

$stmt = $conn->prepare('SELECT id_produk FROM tb_datacarwash WHERE produk = ? AND id_produk <> ?');
$stmt->bind_param('si', $produk, $id_produk);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $_SESSION['error'] = 'Nama produk sudah ada.';
    header('Location: ../../main.php?id=carwashData_edit&id_produk=' . $id_produk);
    exit;
}
$stmt->close();

$stmt = $conn->prepare('UPDATE tb_datacarwash SET produk = ?, biaya = ?, updated_at = ?, updated_by = ? WHERE id_produk = ?');
$stmt->bind_param('sisii', $produk, $biaya, $updated_at, $updated_by, $id_produk);

if ($stmt->execute()) {
    $_SESSION['success'] = 'Data produk carwash berhasil diperbarui.';
    header('Location: ../../main.php?id=carwashData');
    exit;
}

$_SESSION['error'] = 'Data produk carwash gagal diperbarui.';
header('Location: ../../main.php?id=carwashData_edit&id_produk=' . $id_produk);
