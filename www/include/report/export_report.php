<?php
require_once __DIR__ . '/../../config/order_type.php';
// Shared report model for Excel and PDF; amounts are integer rupiah.
function salesExportOptions(array $input): array
{
    $dates = [];
    foreach (['start', 'end'] as $field) {
        $value = $input[$field] ?? '';
        $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Pilih rentang tanggal yang valid.');
        }
        $dates[$field] = $value;
    }
    if ($dates['start'] > $dates['end']) throw new InvalidArgumentException('Tanggal akhir harus setelah tanggal awal.');
    $type = $input['type'] ?? '';
    if (!in_array($type, ['overview', 'transactions', 'items', 'categories'], true)) {
        throw new InvalidArgumentException('Jenis laporan tidak valid.');
    }
    $divisions = $input['divisions'] ?? [];
    if (!is_array($divisions) || !$divisions || count(array_filter($divisions, static function ($value) { return in_array($value, ['cafe', 'carwash', 'detailing'], true); })) !== count($divisions)) {
        throw new InvalidArgumentException('Pilih minimal satu layanan yang valid.');
    }
    return $dates + ['type' => $type, 'divisions' => array_values(array_unique($divisions))];
}

function salesExportQuery(mysqli $conn, string $sql, string $start, string $end): array
{
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// Largest-remainder allocation keeps selected divisions and all detail rows reconciled.
function salesExportAllocate(int $amount, array $weights): array
{
    $sum = array_sum($weights);
    $allocation = array_fill(0, count($weights), 0);
    if (!$weights || !$sum || !$amount) return $allocation;
    $remainders = [];
    foreach ($weights as $i => $weight) {
        $allocation[$i] = (int) floor($amount * $weight / $sum);
        $remainders[$i] = ($amount * $weight) % $sum;
    }
    $left = $amount - array_sum($allocation);
    arsort($remainders, SORT_NUMERIC);
    foreach ($remainders as $i => $remainder) {
        if ($left-- <= 0) break;
        $allocation[$i]++;
    }
    return $allocation;
}

function salesExportBuild(mysqli $conn, array $options): array
{
    $start = $options['start'] . ' 00:00:00';
    $end = $options['end'] . ' 23:59:59';
    $labels = ['cafe' => 'Cafe', 'carwash' => 'Carwash', 'detailing' => 'Detailing'];
    $titles = ['overview' => 'Keseluruhan Penjualan', 'transactions' => 'Rincian Penjualan per Transaksi',
        'items' => 'Penjualan per Item', 'categories' => 'Penjualan per Kategori'];
    $orders = salesExportQuery($conn, "SELECT o.*, UPPER(COALESCE(NULLIF(status_order, ''), CASE WHEN paid_amount = 0 THEN 'OPEN BILL' ELSE 'PAID' END)) AS status_order
        FROM orders o WHERE created_at BETWEEN ? AND ? ORDER BY created_at, id", $start, $end);
    $byOrder = [];
    $cafe = salesExportQuery($conn, "SELECT i.*, COALESCE(NULLIF(c.name_cat, ''), 'Tanpa kategori') AS category
        FROM order_items i JOIN orders o ON o.id=i.id_tr
        LEFT JOIN tb_datacafe p ON i.id_prod = CAST(p.id_prod AS CHAR)
        LEFT JOIN tb_category c ON c.id_cat=p.id_cat
        WHERE o.created_at BETWEEN ? AND ? ORDER BY i.id", $start, $end);
    foreach ($cafe as $row) {
        $byOrder[$row['id_tr']][] = ['division' => 'cafe', 'product_id' => $row['id_prod'], 'name' => $row['item_name'],
            'category' => $row['category'], 'order_type' => $row['order_type'], 'variant' => '', 'service' => '', 'vehicle' => '',
            'qty' => (int)$row['quantity'], 'gross' => (int)$row['total']];
    }
    foreach (['carwash' => 'order_carwash', 'detailing' => 'order_detailing'] as $division => $table) {
        $rows = salesExportQuery($conn, "SELECT i.* FROM $table i JOIN orders o ON o.id=i.id_tr
            WHERE o.created_at BETWEEN ? AND ? ORDER BY i.id", $start, $end);
        foreach ($rows as $row) {
            $byOrder[$row['id_tr']][] = ['division' => $division, 'product_id' => $row['id_prod'], 'name' => $row['item_name'],
                'category' => trim((string)$row['service']) ?: 'Tanpa service', 'variant' => $row['variant_name'] ?? '',
                'order_type' => null, 'service' => $row['service'], 'vehicle' => $row['nopol'], 'qty' => (int)$row['qty'],
                'gross' => (int)$row['total']];
        }
    }
    $paid = []; $canceled = []; $discounts = []; $payments = []; $transactionRows = []; $transactionSummary = [];
    $paidCount = 0; $openCount = 0; $missingCount = 0;
    foreach ($orders as $order) {
        $lines = $byOrder[$order['id']] ?? [];
        if (!$lines) { $missingCount++; continue; }
        $gross = array_sum(array_column($lines, 'gross'));
        $discount = max(0, $gross - (int)$order['total_amount']);
        $adjustment = max(0, (int)$order['total_amount'] - $gross);
        $weights = $gross > 0 ? array_column($lines, 'gross') : array_map(static function ($line) { return max(1, $line['qty']); }, $lines);
        $discountParts = salesExportAllocate($discount, $weights);
        $adjustmentParts = salesExportAllocate($adjustment, $weights);
        $selected = [];
        foreach ($lines as $i => $line) {
            if (!in_array($line['division'], $options['divisions'], true)) continue;
            $line['discount'] = $discountParts[$i];
            $line['adjustment'] = $adjustmentParts[$i];
            $line['net'] = $line['gross'] - $line['discount'] + $line['adjustment'];
            $selected[] = $line;
        }
        if (!$selected) continue;
        $selectedGross = array_sum(array_column($selected, 'gross'));
        $selectedDiscount = array_sum(array_column($selected, 'discount'));
        $selectedAdjustment = array_sum(array_column($selected, 'adjustment'));
        $selectedNet = array_sum(array_column($selected, 'net'));
        if ($order['status_order'] === 'CANCEL') {
            $canceled[] = [$order['id'], $order['created_at'], $order['table_number'], $order['cancel_reason'] ?: '-',
                $order['canceled_at'] ?: '-', $selectedGross, $selectedDiscount, $selectedNet];
            continue;
        }
        if ($order['status_order'] !== 'PAID') { $openCount++; continue; }
        $paidCount++;
        $method = ['cash' => 'Cash', 'qris' => 'QRIS', 'credit_card' => 'Kartu', 'debit' => 'Debit'][$order['payment_method']] ?? ($order['payment_method'] ?: 'Tidak tercatat');
        $transactionSummary[] = [$order['id'], $order['created_at'], $order['table_number'], $method, array_sum(array_column($selected, 'qty')), $selectedGross, $selectedDiscount, $selectedAdjustment, $selectedNet];
        if (!isset($payments[$method])) $payments[$method] = [$method, 0, 0, 0, 0, 0];
        $payments[$method][1]++;
        $payments[$method][2] += $selectedGross;
        $payments[$method][3] += $selectedDiscount;
        $payments[$method][4] += $selectedAdjustment;
        $payments[$method][5] += $selectedNet;
        if ($selectedDiscount) $discounts[] = [$order['id'], $order['created_at'], $order['table_number'], $selectedGross, $selectedDiscount, $selectedNet];
        foreach ($selected as $line) {
            $paid[] = $line;
            $transactionRows[] = [$order['id'], $order['created_at'], $order['table_number'], $method, $labels[$line['division']],
                $line['category'], $line['name'] . ($line['variant'] ? ' / ' . $line['variant'] : ''), $line['vehicle'] ?: '-',
                $line['qty'], $line['gross'], $line['discount'], $line['adjustment'], $line['net'],
                $line['division'] === 'cafe' ? transactionOrderTypeLabel($line['order_type']) : '-'];
        }
    }
    $sections = [];
    $add = static function ($title, $columns, $rows, $money = [], $totalColumns = []) use (&$sections) {
        if ($totalColumns && $rows) {
            $total = array_fill(0, count($columns), ''); $total[0] = 'Subtotal';
            foreach ($totalColumns as $i) $total[$i] = array_sum(array_column($rows, $i));
            $rows[] = $total;
        }
        $sections[] = compact('title', 'columns', 'rows', 'money');
    };
    $aggregate = static function (array $lines, bool $categories = false) use ($labels): array {
        $groups = [];
        foreach ($lines as $line) {
            $key = json_encode($categories ? [$line['division'], $line['category']] : [$line['division'], $line['category'], $line['product_id'], $line['name'], $line['variant']]);
            if (!isset($groups[$key])) {
                $groups[$key] = [$labels[$line['division']], $line['category']];
                if (!$categories) $groups[$key][] = $line['name'] . ($line['variant'] ? ' / ' . $line['variant'] : '');
                $groups[$key] = array_merge($groups[$key], [0, 0, 0, 0, 0]);
            }
            $offset = $categories ? 2 : 3;
            foreach (['qty', 'gross', 'discount', 'adjustment', 'net'] as $i => $field) $groups[$key][$offset + $i] += $line[$field];
        }
        $rows = array_values($groups);
        $netIndex = $categories ? 6 : 7;
        usort($rows, static function ($a, $b) use ($netIndex) { return ($b[$netIndex] <=> $a[$netIndex]) ?: strcmp(json_encode($a), json_encode($b)); });
        return $rows;
    };
    $itemColumns = ['Layanan', 'Kategori / Service', 'Produk / Varian', 'Qty', 'Bruto', 'Diskon', 'Penyesuaian', 'Neto'];
    $categoryColumns = ['Layanan', 'Kategori / Service', 'Qty', 'Bruto', 'Diskon', 'Penyesuaian', 'Neto'];
    $add('Ringkasan Penjualan', ['Pesanan PAID', 'Qty', 'Bruto', 'Diskon', 'Penyesuaian', 'Neto'], [[
        $paidCount, array_sum(array_column($paid, 'qty')), array_sum(array_column($paid, 'gross')),
        array_sum(array_column($paid, 'discount')), array_sum(array_column($paid, 'adjustment')), array_sum(array_column($paid, 'net'))
    ]], [2, 3, 4, 5]);
    if ($options['type'] === 'overview') {
        foreach ($options['divisions'] as $division) {
            $lines = array_values(array_filter($paid, static function ($line) use ($division) { return $line['division'] === $division; }));
            $rows = $aggregate($lines);
            // Division title already identifies the service.
            $rows = array_map(static function ($row) { return array_slice($row, 1); }, $rows);
            $columns = array_slice($itemColumns, 1);
            $add($labels[$division] . ' — Rekap Penjualan Produk', $columns, $rows,
                [3,4,5,6], [2,3,4,5,6]);
            if ($division === 'cafe') {
                $channels = [];
                foreach ($lines as $line) {
                    $key = $line['order_type'] ?? 'unknown';
                    if (!isset($channels[$key])) $channels[$key] = [transactionOrderTypeLabel($line['order_type']), 0, 0, 0, 0, 0];
                    foreach (['qty', 'gross', 'discount', 'adjustment', 'net'] as $i => $field) $channels[$key][$i + 1] += $line[$field];
                }
                $add('Cafe — Dine In / Take Away', ['Jenis Pesanan', 'Qty', 'Bruto', 'Diskon', 'Penyesuaian', 'Neto'], array_values($channels), [2,3,4,5], [1,2,3,4,5]);
            } else {
                $add($labels[$division] . ' — Rekap Service', $categoryColumns, $aggregate($lines, true), [3,4,5,6], [2,3,4,5,6]);
            }
        }
        $add('Top Penjualan berdasarkan Kategori (Neto)', $categoryColumns, array_slice($aggregate($paid, true), 0, 10), [3,4,5,6]);
        $add('Top Produk dari Semua Kategori Terpilih (Neto)', $itemColumns, array_slice($aggregate($paid), 0, 10), [4,5,6,7]);
    } elseif ($options['type'] === 'transactions') {
        $add('Rekap per Transaksi — PAID', ['Order', 'Tanggal', 'Meja', 'Pembayaran', 'Qty', 'Bruto', 'Diskon', 'Penyesuaian', 'Neto'], $transactionSummary, [5,6,7,8], [4,5,6,7,8]);
        $add('Rincian Item per Transaksi — PAID', ['Order', 'Tanggal', 'Meja', 'Pembayaran', 'Layanan', 'Kategori / Service', 'Produk / Varian', 'Nopol', 'Qty', 'Bruto', 'Diskon', 'Penyesuaian', 'Neto', 'Jenis Pesanan'], $transactionRows, [9,10,11,12], [8,9,10,11,12]);
    } elseif ($options['type'] === 'items') {
        $add('Penjualan per Item', $itemColumns, $aggregate($paid), [4,5,6,7], [3,4,5,6,7]);
    } else {
        $add('Penjualan per Kategori', $categoryColumns, $aggregate($paid, true), [3,4,5,6], [2,3,4,5,6]);
    }
    $add('Ringkasan Pesanan Dibatalkan — ' . count($canceled) . ' Pesanan', ['Order', 'Tanggal Pesanan', 'Meja', 'Alasan', 'Dibatalkan Pada', 'Bruto', 'Diskon', 'Nilai Dibatalkan'], $canceled, [5,6,7], [5,6,7]);
    $add('Ringkasan Diskon — ' . count($discounts) . ' Pesanan', ['Order', 'Tanggal', 'Meja', 'Bruto', 'Diskon', 'Neto'], $discounts, [3,4,5], [3,4,5]);
    $add('Ringkasan Metode Pembayaran', ['Metode', 'Pesanan', 'Bruto', 'Diskon', 'Penyesuaian', 'Neto'], array_values($payments), [2,3,4,5], [1,2,3,4,5]);
    return [
        'title' => $titles[$options['type']], 'start' => $options['start'], 'end' => $options['end'],
        'divisions' => array_map(static function ($d) use ($labels) { return $labels[$d]; }, $options['divisions']),
        'notes' => [], 'sections' => $sections
    ];
}
