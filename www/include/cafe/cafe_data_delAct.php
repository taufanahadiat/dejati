<?php
include '../../config/config.php'; // adjust path if needed

if (isset($_GET['id_produk'])) {
    $id_prod = intval($_GET['id_produk']);

    // 1. Get image filename from DB
    $stmt = $conn->prepare("SELECT foto FROM tb_datacafe WHERE id_prod = ?");
    $stmt->bind_param("i", $id_prod);
    $stmt->execute();
    $stmt->bind_result($foto);
    $stmt->fetch();
    $stmt->close();

    if ($foto) {
        // 2. Delete image file if exists
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] .  CDN_BASE . '/img/products/';
        $uploadFile = $uploadDir . basename($foto);

        if (file_exists($uploadFile) && is_file($uploadFile)) {
            unlink($uploadFile);
        }
    }

    // 3. Delete from DB
    $stmt = $conn->prepare("DELETE FROM tb_datacafe WHERE id_prod = ?");
    $stmt->bind_param("i", $id_prod);
    $stmt->execute();
    $stmt->close();

    // Redirect back to table page
    header("Location: ../../main.php?id=cafeData&msg=deleted");
    exit;
} else {
    echo "Invalid request.";
}
