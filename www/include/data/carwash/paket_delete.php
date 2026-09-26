<?php
require_once __DIR__ . '/../../../config/session.php';
session_start();
require_once __DIR__ . '/../../../config/config.php';

if (empty($_SESSION['loggedin'])) {
    header('Location: /');
    exit;
}

if ($_SESSION['level'] === 'Kasir') {
    header('Location: /main?id=transaksi');
    exit;
}

$id_produk = (int)($_GET['id_produk'] ?? 0);
if ($id_produk <= 0) {
    header('Location: /main?id=carwashData');
    exit;
}

require_once __DIR__ . '/../cafe/cafe_image_helper.php';
$photoStmt = $conn->prepare('SELECT foto FROM tb_datacarwash WHERE id_produk = ?');
$photoStmt->bind_param('i', $id_produk);
$photoStmt->execute();
$photo = $photoStmt->get_result()->fetch_assoc()['foto'] ?? '';

$stmt = $conn->prepare('DELETE FROM tb_datacarwash WHERE id_produk = ?');
$stmt->bind_param('i', $id_produk);

if ($stmt->execute()) {
    if (str_starts_with($photo, 'service_carwash_')) cafe_delete_local_image($photo);
    $_SESSION['success'] = 'Data produk carwash berhasil dihapus.';
} else {
    $_SESSION['error'] = 'Data produk carwash gagal dihapus.';
}

header('Location: /main?id=carwashData');
