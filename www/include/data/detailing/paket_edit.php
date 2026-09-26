<?php
$id_produk = (int)($_GET['id_produk'] ?? $_GET['id_paket'] ?? 0);
$target = '/main?id=detailingData';
if ($id_produk > 0) {
    $target = '/main?id=detailingData_edit&id_produk=' . $id_produk;
}
header('Location: ' . $target);
exit;
