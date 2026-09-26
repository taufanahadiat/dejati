<?php
require_once __DIR__ . '/../../../config/session.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=UTF-8');
if (empty($_SESSION['loggedin']) || ($_SESSION['level'] ?? '') === 'Kasir') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Silakan login dengan akun yang dapat mengelola produk.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Metode tidak valid.']);
    exit;
}
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/product_helpers.php';
try {
    service_product_table($serviceType ?? '');
    if (!empty($_POST['upload_only'])) {
        $file = $_FILES['imageFile'] ?? null;
        if (!$file || ($file['size'] ?? 0) > 7 * 1024 * 1024) {
            throw new InvalidArgumentException('Pilih gambar JPG atau PNG maksimal 7 MB.');
        }
        $photo = 'service_' . $serviceType . '_' . bin2hex(random_bytes(16)) . '.jpg';
        $error = cafe_compress_uploaded_image($file, CAFE_PRODUCT_UPLOAD_DIR . $photo);
        if ($error !== null) throw new InvalidArgumentException('Unggah gambar gagal: ' . $error);
        $_SESSION['service_uploads'][$serviceType][$photo] = true;
        echo json_encode(['status' => 'success', 'foto' => $photo]);
    } else {
        $id = service_save_product($conn, $serviceType, $_POST, $_SESSION);
        $_SESSION['success'] = 'Produk ' . ucfirst($serviceType) . ' berhasil disimpan.';
        echo json_encode(['status' => 'success', 'id_produk' => $id]);
    }
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Service product save: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Produk gagal disimpan. Silakan coba kembali.']);
}
