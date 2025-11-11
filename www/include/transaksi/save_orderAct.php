<?php
header('Content-Type: application/json');
date_default_timezone_set("Asia/Jakarta");

$tableNumber = $_POST['tableNumber'] ?? '';
$paymentMethod = $_POST['paymentMethod'] ?? '';
$paid = (int) str_replace(['Rp', ',', '.'], '', $_POST['paid'] ?? '0');
$change = (int) str_replace(['Rp', ',', '.'], '', $_POST['change'] ?? '0');
$total = (int) str_replace(['Rp', ',', '.'], '', $_POST['total'] ?? '0');
$items = json_decode($_POST['items'] ?? '[]', true);

if (!$tableNumber || !$paymentMethod || !$total || empty($items)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

// Insert order
$orderSql = "INSERT INTO orders (table_number, payment_method, total_amount, paid_amount, change_amount, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())";
$stmt = mysqli_prepare($conn, $orderSql);
mysqli_stmt_bind_param($stmt, "ssiii", $tableNumber, $paymentMethod, $total, $paid, $change);
$success = mysqli_stmt_execute($stmt);

if (!$success) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to insert order']);
    exit;
}

$orderId = mysqli_insert_id($conn);

foreach ($items as $item) {
    $productId = $item['id'];
    $itemName = $item['name'];
    $qty = (int) $item['qty'];
    $unitPrice = (int) $item['unitPrice'];
    $finalPrice = (int) $item['finalPrice'];

    if ($item['cartType'] === 'carwash') {
        $nopol = $item['nopol'] ?? '';
        $service = $item['service'] ?? '';
        $ukuran = $item['ukuran'] ?? '';
        $vacuum = $item['vacuum'] ?? 'no';
        $notes = $item['notes'] ?? '';

        // Calculate profits
        $profit_pegawai = (int) round($finalPrice * 0.30);
        $profit_management = $finalPrice - $profit_pegawai; // or use round($finalPrice * 0.70)

        $sql = "INSERT INTO order_carwash (
                id_tr, id_prod, item_name, qty, unit_price, total,
                nopol, service, ukuran, vacuum,
                profit_pegawai, profit_management
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param(
            $stmt,
            "issiiisssiii",
            $orderId,
            $productId,
            $itemName,
            $qty,
            $unitPrice,
            $finalPrice,
            $nopol,
            $service,
            $ukuran,
            $vacuum,
            $profit_pegawai,
            $profit_management
        );
        mysqli_stmt_execute($stmt);
    } else {
        // Save into order_items
        $itemTotal = $finalPrice * $qty;

        $sql = "INSERT INTO order_items (id_tr, id_prod, item_name, item_price, quantity, total)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "issiii", $orderId, $productId, $itemName, $unitPrice, $qty, $itemTotal);
        mysqli_stmt_execute($stmt);
    }
}

echo json_encode(['status' => 'success', 'order_id' => $orderId]);
