<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
include '../../config/config.php';

file_put_contents("debug.log", "[" . date('Y-m-d H:i:s') . "] " . json_encode($_POST) . PHP_EOL, FILE_APPEND);

// Sanitize product name to lowercase, no special chars, underscores
function sanitize_filename($string)
{
    $string = strtolower(trim($string));
    $string = preg_replace('/[^a-z0-9]+/i', '_', $string);
    return trim($string, '_');
}

// Handle Dropzone image upload
if (isset($_POST['upload_only']) && $_POST['upload_only']) {
    if (isset($_FILES['imageFile']) && $_FILES['imageFile']['error'] === UPLOAD_ERR_OK) {
        $tmpName = basename($_POST['foto']);
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] .  CDN_BASE . '/img/products/';
        $uploadFile = $uploadDir . $tmpName;

        $allowedTypes = ['image/jpeg', 'image/png'];
        if (!in_array($_FILES['imageFile']['type'], $allowedTypes)) {
            echo "Invalid file type.";
            exit;
        }

        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        if (move_uploaded_file($_FILES['imageFile']['tmp_name'], $uploadFile)) {
            echo "success";
        } else {
            echo "failed";
        }
    } else {
        echo "upload error";
    }
    exit;
}

// FORM SUBMISSION PROCESS
$nama_prod   = $_POST['nama_prod'] ?? '';
$id_cat      = $_POST['id_cat'] ?? null;
$variant     = $_POST['variant'] ?? 0;
$nama_var    = $_POST['nama_var'] ?? null;
$biaya_var   = $_POST['biaya_var'] ?? null;
$biaya       = $variant == 1 ? null : ($_POST['biaya'] ?? null);
$temp_foto   = basename($_POST['foto'] ?? null);
$updated_by  = $_SESSION['id_user'] ?? 0;
$updated_at  = date('Y-m-d H:i:s');

// 1. Insert without final image name first
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


// 2. Get last inserted ID
$id_prod = mysqli_insert_id($conn);

// 3. Rename file on disk only if image uploaded
if (!empty($temp_foto)) {
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] .  CDN_BASE . '/img/products/';
    $ext = pathinfo($temp_foto, PATHINFO_EXTENSION);
    $sanitized_name = sanitize_filename($nama_prod);
    $final_foto = "{$id_prod}_{$sanitized_name}.{$ext}";

    if (file_exists($uploadDir . $temp_foto)) {
        rename($uploadDir . $temp_foto, $uploadDir . $final_foto);

        // 4. Update foto field with new name
        $updateFoto = $conn->prepare("UPDATE tb_datacafe SET foto = ? WHERE id_prod = ?");
        $updateFoto->bind_param("si", $final_foto, $id_prod);

        if (!$updateFoto->execute()) {
            echo "Failed to update photo.";
            exit;
        }
    }
}

echo "success";
