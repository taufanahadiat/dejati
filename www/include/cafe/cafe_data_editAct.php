<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
// include '../../config/config.php';

file_put_contents("debug_edit.log", "[" . date('Y-m-d H:i:s') . "] " . json_encode($_POST) . PHP_EOL, FILE_APPEND);

// sanitize product name for filename
function sanitize_filename($string)
{
    $string = strtolower(trim($string));
    $string = preg_replace('/[^a-z0-9]+/i', '_', $string);
    return trim($string, '_');
}

// ==== Handle Dropzone upload only ====
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

// ==== Normal edit form submission ====
$id_prod    = $_POST['id_prod'] ?? 0;
$nama_prod  = $_POST['nama_prod'] ?? '';
$id_cat     = $_POST['id_cat'] ?? null;
$variant    = $_POST['variant'] ?? 0;
$nama_var   = $_POST['nama_var'] ?? null;
$biaya_var  = $_POST['biaya_var'] ?? null;
$biaya      = $variant == 1 ? null : ($_POST['biaya'] ?? null);
$temp_foto  = basename($_POST['foto'] ?? '');
$updated_by = $_SESSION['id_user'] ?? 0;
$updated_at = date('Y-m-d H:i:s');

// === 1. fetch current foto from DB ===
$stmt = $conn->prepare("SELECT foto FROM tb_datacafe WHERE id_prod = ?");
$stmt->execute([$id_prod]);
$current = $stmt->get_result()->fetch_assoc();
$current_foto = $current['foto'] ?? null;

// === 2. build final foto filename if new file uploaded ===
$final_foto = $current_foto; // default keep old one
$uploadDir  = $_SERVER['DOCUMENT_ROOT'] .  CDN_BASE . '/img/products/';

if (!empty($temp_foto) && $temp_foto !== $current_foto) {
    // only handle if Dropzone uploaded a new temp file
    $ext = pathinfo($temp_foto, PATHINFO_EXTENSION);
    $sanitized_name = sanitize_filename($nama_prod);
    $final_foto = "{$id_prod}_{$sanitized_name}.{$ext}";

    if (file_exists($uploadDir . $temp_foto)) {
        // remove old file if exists
        if (!empty($current_foto) && file_exists($uploadDir . $current_foto)) {
            unlink($uploadDir . $current_foto);
        }
        rename($uploadDir . $temp_foto, $uploadDir . $final_foto);
    }
} elseif (isset($_POST['remove_foto']) && $_POST['remove_foto'] == "1") {
    // user removed image
    if (!empty($current_foto) && file_exists($uploadDir . $current_foto)) {
        unlink($uploadDir . $current_foto);
    }
    $final_foto = ""; // clear from DB
}


// === 3. update DB ===
$sql = "UPDATE tb_datacafe
        SET nama_prod = ?, id_cat = ?, variant = ?, nama_var = ?, biaya_var = ?, biaya = ?, foto = ?, updated_at = ?, updated_by = ?
        WHERE id_prod = ?";
$stmt = $conn->prepare($sql);
$ok = $stmt->execute([
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
]);

if (!$ok) {
    echo "DB Error: " . $stmt->error;
    exit;
}

echo "success";
