<?php
include "config/config.php";
date_default_timezone_set("Asia/Jakarta");

$today = date('Y-m-d');

// Get totals
function getTotal($conn, $table, $condition = '1=1') {
  // map correct column name based on table
  $column = match ($table) {
    'orders' => 'total_amount',
    'order_items' => 'total',
    'order_carwash' => 'total',
    default => 'total'
  };

  $sql = "SELECT SUM($column) AS total FROM $table WHERE DATE(created_at) = CURDATE() AND $condition";
  $result = mysqli_query($conn, $sql);
  $row = mysqli_fetch_assoc($result);
  return (int)($row['total'] ?? 0);
}

// Get all components
$total_penjualan = getTotal($conn, 'orders');
$cash            = getTotal($conn, 'orders', "payment_method='cash'");
$qris            = getTotal($conn, 'orders', "payment_method='qris'");
$card            = getTotal($conn, 'orders', "payment_method='credit_card'");
$carwash         = getTotal($conn, 'order_carwash');
$cafe            = getTotal($conn, 'order_items');

// Get pengeluaran
$pengeluaran = mysqli_query($conn, "SELECT * FROM pengeluaran WHERE DATE(created_at)=CURDATE()");
$pengeluaran_rows = [];
while ($row = mysqli_fetch_assoc($pengeluaran)) $pengeluaran_rows[] = $row;

if (isset($_GET['print'])) {
  // Build ESC/POS
  $escpos  = "\x1B\x40\x1B\x61\x01";
  $escpos .= "Dejati Coffee Garden\nLaporan Closingan\n-----------------------------\n";
  $escpos .= "Tanggal: " . date('d-m-Y H:i') . "\n";
  $escpos .= "-----------------------------\n";
  $escpos .= "Total Penjualan : Rp " . number_format($total_penjualan, 0, ',', '.') . "\n";
  $escpos .= "Cash            : Rp " . number_format($cash, 0, ',', '.') . "\n";
  $escpos .= "Qris            : Rp " . number_format($qris, 0, ',', '.') . "\n";
  $escpos .= "Kartu Kredit    : Rp " . number_format($card, 0, ',', '.') . "\n";
  $escpos .= "-----------------------------\n";
  $escpos .= "Pendapatan [+]\n";
  $escpos .= "Cafe     : Rp " . number_format($cafe, 0, ',', '.') . "\n";
  $escpos .= "Carwash  : Rp " . number_format($carwash, 0, ',', '.') . "\n";
  $escpos .= "-----------------------------\n";
  $escpos .= "Pengeluaran [-]\n";

  foreach ($pengeluaran_rows as $p) {
    $escpos .= "{$p['keterangan']} : Rp " . number_format($p['total'], 0, ',', '.') . "\n";
  }

  $escpos .= "-----------------------------\n";
  $escpos .= "Terima kasih!\n\n\n";
  $escpos .= "\x1D\x56\x00";

  echo $escpos;
  exit;
}

// Preview (for modal)
?>
<div>
  <h6>--- Total Transaksi ---</h6>
  <p>Total Penjualan: Rp <?= number_format($total_penjualan, 0, ',', '.') ?></p>
  <p>Cash: Rp <?= number_format($cash, 0, ',', '.') ?></p>
  <p>Qris: Rp <?= number_format($qris, 0, ',', '.') ?></p>
  <p>Kartu Kredit: Rp <?= number_format($card, 0, ',', '.') ?></p>

  <h6>--- Pendapatan [+] ---</h6>
  <p>Cafe: Rp <?= number_format($cafe, 0, ',', '.') ?></p>
  <p>Carwash: Rp <?= number_format($carwash, 0, ',', '.') ?></p>

  <h6>--- Pengeluaran [-] ---</h6>
  <?php if ($pengeluaran_rows): ?>
    <ul>
      <?php foreach ($pengeluaran_rows as $p): ?>
        <li><?= htmlspecialchars($p['keterangan']) ?> - Rp <?= number_format($p['total'], 0, ',', '.') ?></li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p><i>Belum ada pengeluaran hari ini.</i></p>
  <?php endif; ?>
</div>
