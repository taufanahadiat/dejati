<?php
header('Content-Type: application/json');
date_default_timezone_set("Asia/Jakarta");

function parse_money_value($value)
{
    return (int)preg_replace('/[^0-9]/', '', (string)$value);
}

$tableNumber = trim($_POST['tableNumber'] ?? '');
$paymentMethod = strtolower(trim($_POST['paymentMethod'] ?? ''));
$status = $_POST['status'] ?? '';
$postedPaid = parse_money_value($_POST['paid'] ?? '0');
$postedDiscountPercent = max(0, parse_money_value($_POST['discountPercent'] ?? $_POST['discount'] ?? '0'));
$items = json_decode($_POST['items'] ?? '[]', true);

if ($tableNumber === '' || $paymentMethod === '' || empty($items) || !is_array($items)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

if (!in_array($paymentMethod, ['cash', 'credit_card', 'qris'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid payment method']);
    exit;
}

$subtotal = 0;
foreach ($items as $item) {
    $qty = max(1, (int)($item['qty'] ?? 1));
    $unitPrice = max(0, (int)($item['unitPrice'] ?? 0));
    $finalPrice = max(0, (int)($item['finalPrice'] ?? $unitPrice));
    $cartType = $item['cartType'] ?? 'product';
    $linePrice = $cartType === 'carwash' ? $finalPrice : $unitPrice;
    $subtotal += $linePrice * $qty;
}

if ($subtotal <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Subtotal must be greater than zero']);
    exit;
}

if ($postedDiscountPercent > 100) {
    echo json_encode(['status' => 'error', 'message' => 'Discount percentage cannot be greater than 100%']);
    exit;
}

$postedDiscount = (int)floor($subtotal * $postedDiscountPercent / 100);
$total = max(0, $subtotal - $postedDiscount);
$isOpenBill = $status === 'open_bill';
$isNonCash = in_array($paymentMethod, ['credit_card', 'qris'], true);
$paid = $postedPaid;

if ($isNonCash) {
    $paid = $total;
}

if (!$isOpenBill && $paymentMethod === 'cash' && $paid < $total) {
    echo json_encode(['status' => 'error', 'message' => 'Customer pay cannot be lower than grand total']);
    exit;
}

if ($isOpenBill) {
    $paid = 0;
}

$change = max(0, $paid - $total);

$createdAt = date('Y-m-d H:i:s');
$orderSql = "INSERT INTO orders (table_number, payment_method, total_amount, paid_amount, change_amount, created_at)
             VALUES (?, ?, ?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $orderSql);
mysqli_stmt_bind_param($stmt, "ssiiis", $tableNumber, $paymentMethod, $total, $paid, $change, $createdAt);
$success = mysqli_stmt_execute($stmt);

if (!$success) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to insert order']);
    exit;
}

$orderId = mysqli_insert_id($conn);

foreach ($items as $item) {
    $productId = $item['id'] ?? '';
    $itemName = $item['name'] ?? '';
    $qty = max(1, (int)($item['qty'] ?? 1));
    $unitPrice = max(0, (int)($item['unitPrice'] ?? 0));
    $finalPrice = max(0, (int)($item['finalPrice'] ?? $unitPrice));

    if (($item['cartType'] ?? 'product') === 'carwash') {
        $nopol = $item['nopol'] ?? '';
        $service = $item['service'] ?? '';
        $ukuran = $item['ukuran'] ?? '';
        $vacuum = strtolower(trim($item['vacuum'] ?? 'no'));

        $profit_pegawai = (int)round($finalPrice * 0.30);
        $profit_management = $finalPrice - $profit_pegawai;

        $sql = "INSERT INTO `order_carwash` (
                `id_tr`, `id_prod`, `item_name`, `qty`, `unit_price`, `total`,
                `nopol`, `service`, `ukuran`, `vacuum`,
                `profit_pegawai`, `profit_management`
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param(
            $stmt,
            "issiiissssii",
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
        $itemTotal = $unitPrice * $qty;

        $sql = "INSERT INTO order_items (id_tr, id_prod, item_name, item_price, quantity, total)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "issiii", $orderId, $productId, $itemName, $unitPrice, $qty, $itemTotal);
        mysqli_stmt_execute($stmt);
    }
}

echo json_encode([
    'status' => 'success',
    'order_id' => $orderId,
    'subtotal' => $subtotal,
    'discount' => $postedDiscount,
    'discountPercent' => $postedDiscountPercent,
    'total' => $total,
    'paid' => $paid,
    'change' => $change
]);
