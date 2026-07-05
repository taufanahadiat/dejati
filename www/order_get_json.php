<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['order' => null, 'items' => [], 'carwash' => []]);
    exit;
}

$stmt = $conn->prepare('SELECT id, table_number, payment_method, total_amount, paid_amount, change_amount, created_at FROM orders WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$order = $stmt->get_result();
$orderData = $order ? mysqli_fetch_assoc($order) : null;

$stmt = $conn->prepare('SELECT id_prod, item_name, item_price, quantity, total FROM order_items WHERE id_tr = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$q1 = $stmt->get_result();
$items = [];
while ($row = mysqli_fetch_assoc($q1)) $items[] = $row;

$stmt = $conn->prepare('SELECT id_prod, item_name, unit_price, qty, total, nopol, service, ukuran, vacuum, profit_pegawai, profit_management FROM order_carwash WHERE id_tr = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$q2 = $stmt->get_result();
$carwash = [];
while ($row = mysqli_fetch_assoc($q2)) $carwash[] = $row;

echo json_encode([
    'order' => $orderData,
    'items' => $items,
    'carwash' => $carwash
]);