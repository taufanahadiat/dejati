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

$produk = trim(filter_input(INPUT_POST, 'produk', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$biaya = (int)str_replace('.', '', filter_input(INPUT_POST, 'biaya', FILTER_SANITIZE_FULL_SPECIAL_CHARS));

$stmt = $conn->prepare('SELECT id_produk FROM tb_datacarwash WHERE produk = ?');
$stmt->bind_param('s', $produk);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $_SESSION['error'] = 'Nama produk sudah ada.';
    header('Location: ../../main.php?id=carwashData_add');
    exit;
}
$stmt->close();

$stmt = $conn->prepare('INSERT INTO tb_datacarwash (produk, biaya) VALUES (?, ?)');
$stmt->bind_param('si', $produk, $biaya);

if ($stmt->execute()) {
    $_SESSION['success'] = 'Data produk carwash berhasil ditambahkan.';
    header('Location: ../../main.php?id=carwashData');
    exit;
}

$_SESSION['error'] = 'Data produk carwash gagal ditambahkan.';
header('Location: ../../main.php?id=carwashData_add');
