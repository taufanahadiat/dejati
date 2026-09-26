<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['order' => null, 'items' => [], 'carwash' => [], 'detailing' => []]);
    exit;
}

$stmt = $conn->prepare("SELECT id, table_number, payment_method, total_amount, paid_amount, change_amount, created_at,
                               cancel_reason, canceled_at,
                               COALESCE(NULLIF(status_order, ''), CASE WHEN paid_amount = 0 THEN 'OPEN BILL' ELSE 'PAID' END) AS status_order
                        FROM orders
                        WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$orderResult = $stmt->get_result();
$orderData = $orderResult ? $orderResult->fetch_assoc() : null;
$stmt->close();

$stmt = $conn->prepare('SELECT id_prod, item_name, item_price, quantity, total FROM order_items WHERE id_tr = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$itemsResult = $stmt->get_result();
$items = [];
while ($row = $itemsResult->fetch_assoc()) {
    $items[] = $row;
}
$stmt->close();

$stmt = $conn->prepare('SELECT id_prod, item_name, unit_price, qty, total, nopol, service, ukuran, vacuum, profit_pegawai, profit_management, variant_name FROM order_carwash WHERE id_tr = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$carwashResult = $stmt->get_result();
$carwash = [];
while ($row = $carwashResult->fetch_assoc()) {
    $carwash[] = $row;
}
$stmt->close();

$stmt = $conn->prepare('SELECT id_prod, item_name, unit_price, qty, total, nopol, service, ukuran, vacuum, profit_pegawai, profit_management, variant_name FROM order_detailing WHERE id_tr = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$detailingResult = $stmt->get_result();
$detailing = [];
while ($row = $detailingResult->fetch_assoc()) {
    $detailing[] = $row;
}
$stmt->close();

echo json_encode([
    'order' => $orderData,
    'items' => $items,
    'carwash' => $carwash,
    'detailing' => $detailing,
]);
