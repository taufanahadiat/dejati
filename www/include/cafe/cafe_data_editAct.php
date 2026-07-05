<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($conn)) {
    include __DIR__ . '/../../config/config.php';
}
require_once __DIR__ . '/cafe_image_helper.php';

// Handle Dropzone image upload
if (isset($_POST['upload_only']) && $_POST['upload_only']) {
    $tmpName = cafe_sanitize_filename(pathinfo($_POST['foto'] ?? '', PATHINFO_FILENAME));
    if ($tmpName === '') {
        $tmpName = 'img_' . time();
    }

    $uploadFile = CAFE_PRODUCT_UPLOAD_DIR . $tmpName . '.jpg';
    $error = cafe_compress_uploaded_image($_FILES['imageFile'] ?? null, $uploadFile);
    echo $error === null ? "success" : $error;
    exit;
}

$id_prod    = (int)($_POST['id_prod'] ?? 0);
$nama_prod  = trim($_POST['nama_prod'] ?? '');
$id_cat     = $_POST['id_cat'] ?? null;
$variant    = (int)($_POST['variant'] ?? 0);
$nama_var   = $_POST['nama_var'] ?? null;
$biaya_var  = $_POST['biaya_var'] ?? null;
$biaya      = $variant === 1 ? null : ($_POST['biaya'] ?? null);
$temp_foto  = basename($_POST['foto'] ?? '');
$updated_by = $_SESSION['id_user'] ?? 0;
$updated_at = date('Y-m-d H:i:s');

$stmt = $conn->prepare("SELECT foto FROM tb_datacafe WHERE id_prod = ?");
$stmt->bind_param("i", $id_prod);
$stmt->execute();
$current = $stmt->get_result()->fetch_assoc();
$current_foto = $current['foto'] ?? '';
$stmt->close();

$final_foto = $current_foto;

if (isset($_POST['remove_foto']) && $_POST['remove_foto'] === "1" && $temp_foto === '') {
    cafe_delete_local_image($current_foto);
    $final_foto = '';
} elseif ($temp_foto !== '' && $temp_foto !== $current_foto && cafe_local_image_exists($temp_foto)) {
    $sanitizedName = cafe_sanitize_filename($nama_prod);
    $final_foto = "{$id_prod}_{$sanitizedName}.jpg";

    cafe_delete_local_image($current_foto);
    if ($temp_foto !== $final_foto) {
        rename(CAFE_PRODUCT_UPLOAD_DIR . basename($temp_foto), CAFE_PRODUCT_UPLOAD_DIR . $final_foto);
    }
}

$sql = "UPDATE tb_datacafe
        SET nama_prod = ?, id_cat = ?, variant = ?, nama_var = ?, biaya_var = ?, biaya = ?, foto = ?, updated_at = ?, updated_by = ?
        WHERE id_prod = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "siisssssii",
    $nama_prod,
    $id_cat,
    $variant,
    $nama_var,
    $biaya_var,
    $biaya,
    $final_foto,
    $updated_at,
    $updated_by,
    $id_prod
);

if (!$stmt->execute()) {
    echo "DB Error: " . $stmt->error;
    exit;
}

echo "success";
