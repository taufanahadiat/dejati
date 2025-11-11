<?php
// filepath: /home/relia/automated-cafe/www/include/transaksi/print_order.php
<?php
require __DIR__ . '/vendor/autoload.php';

use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\BluetoothPrintConnector;

// Get order data from POST
$items = json_decode($_POST['items'], true);
$total = $_POST['total'];
$paid = $_POST['paid'];
$change = $_POST['change'];
$method = $_POST['paymentMethod'];
$tableNumber = $_POST['tableNumber'];

// Connect to Bluetooth printer (replace with your printer's MAC address)
$connector = new BluetoothPrintConnector("00:11:22:33:44:55"); // Change to your printer's MAC

$printer = new Printer($connector);

$printer->setJustification(Printer::JUSTIFY_CENTER);
$printer->text("Dejati Carwash\n");
$printer->text("Jl. Contoh No.123\nTelp: 0812-xxxx-xxxx\n");
$printer->text("-----------------------------\n");
$printer->text("Invoice\nTable: $tableNumber\n");
$printer->text("-----------------------------\n");

foreach ($items as $row) {
    $name = $row['name'];
    $qty = $row['qty'];
    $totalItem = number_format($row['finalPrice'] * $row['qty']);
    $printer->setJustification(Printer::JUSTIFY_LEFT);
    $printer->text("$name x$qty Rp $totalItem\n");
}

$printer->text("-----------------------------\n");
$printer->setJustification(Printer::JUSTIFY_RIGHT);
$printer->text("Total: Rp $total\n");
$printer->text("Bayar: Rp $paid\n");
$printer->text("Kembali: Rp $change\n");
$printer->text("Metode: $method\n");
$printer->text("-----------------------------\n");
$printer->setJustification(Printer::JUSTIFY_CENTER);
$printer->text("Terima kasih atas kunjungannya!\nSilakan datang kembali\n\n\n");
$printer->cut();
$printer->close();
?>