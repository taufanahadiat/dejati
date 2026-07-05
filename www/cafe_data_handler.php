<?php
include('config/config.php');
require_once __DIR__ . '/include/cafe/cafe_image_helper.php';

if (isset($_GET['id_produk'])) {
    $id_prod = (int)$_GET['id_produk'];

    $stmt = $conn->prepare("SELECT foto FROM tb_datacafe WHERE id_prod = ?");
    $stmt->bind_param("i", $id_prod);
    $stmt->execute();
    $stmt->bind_result($foto);
    $stmt->fetch();
    $stmt->close();

    cafe_delete_local_image($foto ?? '');

    $stmt = $conn->prepare("DELETE FROM tb_datacafe WHERE id_prod = ?");
    $stmt->bind_param("i", $id_prod);
    $stmt->execute();
    $stmt->close();

    header("Location: /main.php?id=cafeData&msg=deleted");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include __DIR__ . '/include/cafe/cafe_data_addAct.php';
    exit;
}

echo "Invalid request.";
