<?php
function stockMinimumQuantity($value, string $unit): float {
    if ($value === null || $value === '') return $unit === 'gr' ? 50.0 : 5.0;
    if (!is_string($value) && !is_numeric($value)) throw new InvalidArgumentException('Batas stok minim tidak valid.');
    $text = str_replace(',', '.', trim((string)$value));
    if (!preg_match('/^\d+(?:\.\d{1,3})?$/D', $text) || (float)$text > 999999999) {
        throw new InvalidArgumentException('Batas stok minim harus angka 0 sampai 999999999, maksimal 3 angka desimal.');
    }
    return (float)$text;
}
