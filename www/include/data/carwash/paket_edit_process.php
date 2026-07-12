<?php
require_once __DIR__ . '/../../../config/session.php';
session_start();
require_once __DIR__ . '/../../../config/config.php';

if (empty($_SESSION['loggedin'])) {
    header('Location: ../../');
    exit;
}

if ($_SESSION['level'] === 'Kasir') {
    header('Location: ../../main.php?id=transaksi');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../main.php?id=carwashData');
    exit;
}

$id_produk = (int)($_POST['id_produk'] ?? 0);
$produk = trim((string)($_POST['produk'] ?? ''));
$biaya = (int)preg_replace('/\D+/', '', (string)($_POST['biaya'] ?? ''));
$updated_at = date('Y-m-d H:i:s');
$updated_by = $_SESSION['id_user'] ?? 0;

if ($id_produk <= 0 || $produk === '' || $biaya <= 0) {
    $_SESSION['error'] = 'Nama produk dan harga wajib diisi dengan benar.';
    header('Location: ../../main.php?id=carwashData_edit&id_produk=' . $id_produk);
    exit;
}

$stmt = $conn->prepare('SELECT id_produk FROM tb_datacarwash WHERE id_produk = ? LIMIT 1');
$stmt->bind_param('i', $id_produk);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    $stmt->close();
    $_SESSION['error'] = 'Data produk carwash tidak ditemukan.';
    header('Location: ../../main.php?id=carwashData');
    exit;
}
$stmt->close();

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
    $stmt->close();
    $_SESSION['success'] = 'Data produk carwash berhasil diperbarui.';
    header('Location: ../../main.php?id=carwashData');
    exit;
}

$error = $stmt->error ?: $conn->error;
$stmt->close();
$_SESSION['error'] = 'Data produk carwash gagal diperbarui' . ($error ? ': ' . $error : '.');
header('Location: ../../main.php?id=carwashData_edit&id_produk=' . $id_produk);
