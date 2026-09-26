<?php
// docker exec --user www-data lampp_web php /var/www/html/tests/service_products_regression.php
// Product writes use temporary tables. Only uniquely named test images are created and removed.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
chdir(dirname(__DIR__));
require 'config/config.php';
require 'include/data/service/product_helpers.php';
function verify($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$session = ['id_user' => 0];
$files = [];
try {
    foreach (['carwash', 'detailing'] as $type) {
        $table = service_product_table($type);
        $schema = $conn->query("SHOW CREATE TABLE $table")->fetch_assoc()['Create Table'];
        $conn->query(preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $schema));
        $input = ['nama_prod' => "Driver's Package", 'variant' => '1', 'variant_name' => ['Small', 'Large'], 'variant_price' => ['50000', '100000']];
        $id = service_save_product($conn, $type, $input, $session);
        $row = $conn->query("SELECT * FROM $table WHERE id_produk = $id")->fetch_assoc();
        verify($row['nama_var'] === 'Small;Large' && $row['biaya_var'] === '50000;100000', "$type variant persistence");
        verify((int)$row['variant'] === 1, "$type variant flag");
        foreach ([array_merge($input, ['variant_price' => ['50000']]), array_merge($input, ['variant_price' => ['0','100000']]), array_merge($input, ['variant_name' => ['Small','Small']]), $input] as $bad) {
            try { service_save_product($conn, $type, $bad, $session); throw new RuntimeException('Invalid or duplicate data accepted'); }
            catch (InvalidArgumentException $expected) {}
        }
        $input['id_prod'] = $id;
        $input['variant_price'] = ['60000','110000'];
        service_save_product($conn, $type, $input, $session);
        verify($conn->query("SELECT biaya_var FROM $table WHERE id_produk = $id")->fetch_assoc()['biaya_var'] === '60000;110000', "$type variant edit");
        $photo = 'service_' . $type . '_regression_' . bin2hex(random_bytes(8)) . '.jpg';
        $files[] = $photo;
        $image = imagecreatetruecolor(8,8);
        imagejpeg($image, CAFE_PRODUCT_UPLOAD_DIR . $photo); imagedestroy($image);
        $session['service_uploads'][$type][$photo] = true;
        $input['foto'] = $photo;
        service_save_product($conn, $type, $input, $session);
        verify($conn->query("SELECT foto FROM $table WHERE id_produk = $id")->fetch_assoc()['foto'] === $photo, "$type photo persistence");
        verify(str_contains(cafe_product_thumb_url($photo), '/thumbs/'), "$type thumbnail");
        $serviceType = $type; $_GET = ['id_produk' => $id];
        ob_start(); require 'include/data/service/product_form.php'; $html = ob_get_clean();
        verify(str_contains($html, "Driver&#039;s Package") && str_contains($html, 'serviceVariantFields'), "$type form escaping and fields");
        file_put_contents('/tmp/' . $type . '-product-form-test.html', $html);
        $input['foto'] = ''; $input['remove_foto'] = '1';
        $input['variant'] = '0'; $input['biaya'] = '75000';
        service_save_product($conn, $type, $input, $session);
        $row = $conn->query("SELECT * FROM $table WHERE id_produk = $id")->fetch_assoc();
        verify($row['foto'] === '' && !cafe_local_image_exists($photo), "$type remove photo");
        verify((int)$row['variant'] === 0 && (int)$row['biaya'] === 75000 && $row['nama_var'] === null, "$type switch to fixed price");
    }
    echo "PASS: both catalogs, variant create/edit/validation, duplicate names, photo and thumbnail lifecycle, form escaping, fixed-price conversion\n";
} finally {
    foreach ($files as $file) cafe_delete_local_image($file);
}
