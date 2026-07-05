<?php
$id_produk = (int)($_GET['id_produk'] ?? $_GET['id_paket'] ?? 0);
$target = '../../main.php?id=carwashData';
if ($id_produk > 0) {
    $target = '../../main.php?id=carwashData_edit&id_produk=' . $id_produk;
}
header('Location: ' . $target);
exit;
