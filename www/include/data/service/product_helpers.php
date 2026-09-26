<?php
require_once __DIR__ . '/../cafe/cafe_image_helper.php';

function service_product_table(string $type): string
{
    if (!in_array($type, ['carwash', 'detailing'], true)) {
        throw new InvalidArgumentException('Jenis produk tidak valid.');
    }
    return 'tb_data' . $type;
}

function service_product_values(array $input): array
{
    $name = trim((string)($input['nama_prod'] ?? $input['produk'] ?? ''));
    if ($name === '' || mb_strlen($name) > 100) {
        throw new InvalidArgumentException('Nama produk wajib diisi, maksimal 100 karakter.');
    }
    $variant = in_array($input['variant'] ?? '0', ['1', 1, 'yes'], true) ? 1 : 0;
    $names = $prices = [];
    $price = 0;
    if ($variant) {
        $names = $input['variant_name'] ?? [];
        $prices = $input['variant_price'] ?? [];
        if (!is_array($names) || !is_array($prices) || count($names) < 1 || count($names) > 50 || count($names) !== count($prices)) {
            throw new InvalidArgumentException('Isi nama dan harga untuk setiap varian (maksimal 50).');
        }
        $names = array_values($names);
        $prices = array_values($prices);
        foreach ($names as $i => $value) {
            $names[$i] = trim((string)$value);
            if ($names[$i] === '' || mb_strlen($names[$i]) > 100 || str_contains($names[$i], ';')) {
                throw new InvalidArgumentException('Nama varian wajib diisi, maksimal 100 karakter, tanpa titik koma.');
            }
            if (!ctype_digit((string)$prices[$i]) || (int)$prices[$i] <= 0 || (float)$prices[$i] > 2147483647) {
                throw new InvalidArgumentException('Harga setiap varian harus berupa rupiah bulat lebih dari nol.');
            }
            $prices[$i] = (int)$prices[$i];
        }
        if (count(array_unique(array_map('mb_strtolower', $names))) !== count($names)) {
            throw new InvalidArgumentException('Nama varian tidak boleh sama.');
        }
    } else {
        $rawPrice = str_replace('.', '', (string)($input['biaya'] ?? $input['harga_toko'] ?? ''));
        if (!ctype_digit($rawPrice) || (int)$rawPrice <= 0 || (float)$rawPrice > 2147483647) {
            throw new InvalidArgumentException('Harga produk harus berupa rupiah bulat lebih dari nol.');
        }
        $price = (int)$rawPrice;
    }
    return [$name, $price, $variant, $variant ? implode(';', $names) : null, $variant ? implode(';', $prices) : null];
}

function service_save_product(mysqli $conn, string $type, array $input, array &$session): int
{
    $table = service_product_table($type);
    [$name, $price, $variant, $names, $prices] = service_product_values($input);
    $id = (int)($input['id_prod'] ?? $input['id_produk'] ?? 0);
    $existingPhoto = '';
    if ($id > 0) {
        $stmt = $conn->prepare("SELECT foto FROM $table WHERE id_produk = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $current = $stmt->get_result()->fetch_assoc();
        if (!$current) throw new InvalidArgumentException('Produk tidak ditemukan.');
        $existingPhoto = $current['foto'] ?? '';
    }
    $stmt = $conn->prepare("SELECT id_produk FROM $table WHERE produk = ? AND id_produk <> ? LIMIT 1");
    $stmt->bind_param('si', $name, $id);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) throw new InvalidArgumentException('Nama produk sudah ada.');

    $photo = $existingPhoto;
    $uploaded = basename((string)($input['foto'] ?? ''));
    if (($input['remove_foto'] ?? '') === '1') $photo = '';
    if ($uploaded !== '' && $uploaded !== $existingPhoto) {
        if (empty($session['service_uploads'][$type][$uploaded]) || !cafe_local_image_exists($uploaded)) {
            throw new InvalidArgumentException('Gambar belum berhasil diunggah. Silakan unggah kembali.');
        }
        $photo = $uploaded;
    }
    $updatedBy = (int)($session['id_user'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE $table SET produk = ?, biaya = ?, variant = ?, nama_var = ?, biaya_var = ?, foto = ?, updated_by = ?, updated_at = NOW() WHERE id_produk = ?");
        $stmt->bind_param('siisssii', $name, $price, $variant, $names, $prices, $photo, $updatedBy, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO $table (produk, biaya, variant, nama_var, biaya_var, foto, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('siisssi', $name, $price, $variant, $names, $prices, $photo, $updatedBy);
    }
    $stmt->execute();
    if ($id === 0) $id = $conn->insert_id;
    if ($existingPhoto !== $photo && str_starts_with($existingPhoto, 'service_' . $type . '_')) {
        cafe_delete_local_image($existingPhoto);
    }
    unset($session['service_uploads'][$type][$photo]);
    return $id;
}
