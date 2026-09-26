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

function cafe_decode_form_text($value)
{
    return trim(html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Invalid request method.";
    exit;
}

$id_prod    = (int)($_POST['id_prod'] ?? 0);
$nama_prod  = cafe_decode_form_text($_POST['nama_prod'] ?? '');
$id_cat     = (int)($_POST['id_cat'] ?? 0);
$variantRaw = $_POST['variant'] ?? 0;
$variant    = ($variantRaw === 'yes' || $variantRaw === '1' || $variantRaw === 1) ? 1 : 0;
$nama_var   = null;
$biaya_var  = null;
$biaya      = null;
$temp_foto  = basename($_POST['foto'] ?? '');
$updated_by = $_SESSION['id_user'] ?? 0;
$updated_at = date('Y-m-d H:i:s');

if ($variant === 1) {
    $variantNames = array_map(
        'cafe_decode_form_text',
        array_filter((array)($_POST['variant_name'] ?? []), static function ($value) {
            return trim((string)$value) !== '';
        })
    );
    $variantPrices = array_map(
        static function ($value) {
            return preg_replace('/\D+/', '', (string)$value);
        },
        array_filter((array)($_POST['variant_price'] ?? []), static function ($value) {
            return trim((string)$value) !== '';
        })
    );

    if (!$variantNames && isset($_POST['nama_var'])) {
        $variantNames = array_map('cafe_decode_form_text', explode(';', (string)$_POST['nama_var']));
    }

    if (!$variantPrices && isset($_POST['biaya_var'])) {
        $variantPrices = array_map(static function ($value) {
            return preg_replace('/\D+/', '', (string)$value);
        }, explode(';', (string)$_POST['biaya_var']));
    }

    $nama_var = implode(';', $variantNames);
    $biaya_var = implode(';', $variantPrices);
} else {
    $biayaInput = $_POST['biaya'] ?? ($_POST['harga_toko'] ?? '');
    $biaya = (int)preg_replace('/\D+/', '', (string)$biayaInput);
}

if ($id_prod <= 0 || $nama_prod === '' || $id_cat <= 0 || !in_array($variant, [0, 1], true)) {
    http_response_code(422);
    echo "Nama produk, kategori, dan varian wajib diisi dengan benar.";
    exit;
}

if ($variant === 1 && ($nama_var === '' || $biaya_var === '')) {
    http_response_code(422);
    echo "Nama dan harga varian wajib diisi.";
    exit;
}

if ($variant === 0 && $biaya <= 0) {
    http_response_code(422);
    echo "Harga produk wajib diisi dengan benar.";
    exit;
}

$stmt = $conn->prepare("SELECT foto FROM tb_datacafe WHERE id_prod = ? LIMIT 1");
$stmt->bind_param("i", $id_prod);
$stmt->execute();
$current = $stmt->get_result()->fetch_assoc();

if (!$current) {
    $stmt->close();
    http_response_code(404);
    echo "Data produk cafe tidak ditemukan.";
    exit;
}

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

$stmt->close();
echo "success";
