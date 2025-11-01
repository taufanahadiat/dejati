<?php
include '../../config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name_cat = trim($_POST['name_cat']);
    $icon = trim($_POST['icon']);

    if ($name_cat && $icon) {
        // Prepare and execute insert
        $stmt = $conn->prepare("INSERT INTO tb_category (name_cat, icon) VALUES (?, ?)");
        $stmt->bind_param("ss", $name_cat, $icon);

        if ($stmt->execute()) {
            echo "Kategori berhasil disimpan!";
        } else {
            echo "Gagal menyimpan kategori.";
        }

        $stmt->close();
    } else {
        echo "Isi semua field!";
    }
}
