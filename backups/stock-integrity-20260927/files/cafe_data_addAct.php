<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../../config/session.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($conn)) {
    include __DIR__ . '/../../../config/config.php';
}
require_once __DIR__ . '/cafe_image_helper.php';
require_once __DIR__ . '/cafe_stock_helper.php';

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

// FORM SUBMISSION PROCESS
$nama_prod   = trim($_POST['nama_prod'] ?? '');
$id_cat      = $_POST['id_cat'] ?? null;
$variant     = (int)($_POST['variant'] ?? 0);
$nama_var    = $_POST['nama_var'] ?? null;
$biaya_var   = $_POST['biaya_var'] ?? null;
$biaya       = $variant === 1 ? null : ($_POST['biaya'] ?? null);
$temp_foto   = !empty($_POST['foto']) ? basename($_POST['foto']) : '';
$updated_by  = $_SESSION['id_user'] ?? 0;
$updated_at  = date('Y-m-d H:i:s');

try {
    $stockBinding = cafe_parse_stock_binding_input($conn);
} catch (InvalidArgumentException $error) {
    http_response_code(422);
    echo $error->getMessage();
    exit;
}

$sql = "INSERT INTO tb_datacafe (
    nama_prod, id_cat, variant, nama_var, biaya_var, biaya, foto, updated_at, updated_by
) VALUES (?, ?, ?, ?, ?, ?, '', ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "siissssi",
    $nama_prod,
    $id_cat,
    $variant,
    $nama_var,
    $biaya_var,
    $biaya,
    $updated_at,
    $updated_by
);

if (!$stmt->execute()) {
    echo "DB Error: " . $stmt->error;
    exit;
}

$id_prod = mysqli_insert_id($conn);
cafe_replace_stock_bindings($conn, $id_prod, $stockBinding);

if ($temp_foto !== '' && cafe_local_image_exists($temp_foto)) {
    $sanitizedName = cafe_sanitize_filename($nama_prod);
    $final_foto = "{$id_prod}_{$sanitizedName}.jpg";

    rename(CAFE_PRODUCT_UPLOAD_DIR . basename($temp_foto), CAFE_PRODUCT_UPLOAD_DIR . $final_foto);

    $updateFoto = $conn->prepare("UPDATE tb_datacafe SET foto = ? WHERE id_prod = ?");
    $updateFoto->bind_param("si", $final_foto, $id_prod);

    if (!$updateFoto->execute()) {
        echo "Failed to update photo.";
        exit;
    }
}

echo "success";
