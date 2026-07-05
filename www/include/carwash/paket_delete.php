<?php
require_once __DIR__ . '/../../config/session.php';
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

$id_produk = (int)($_GET['id_produk'] ?? 0);
if ($id_produk <= 0) {
    header('Location: ../../main.php?id=carwashData');
    exit;
}

$stmt = $conn->prepare('DELETE FROM tb_datacarwash WHERE id_produk = ?');
$stmt->bind_param('i', $id_produk);

if ($stmt->execute()) {
    $_SESSION['success'] = 'Data produk carwash berhasil dihapus.';
} else {
    $_SESSION['error'] = 'Data produk carwash gagal dihapus.';
}

header('Location: ../../main.php?id=carwashData');
