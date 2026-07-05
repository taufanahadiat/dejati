<?php
include 'config/config.php';

header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$response = ['id_tr' => $id, 'order' => null, 'items' => [], 'carwash' => []];

if ($id > 0) {
    $order = mysqli_query($conn, "SELECT id, table_number, payment_method, total_amount, paid_amount, change_amount, created_at FROM orders WHERE id = $id LIMIT 1");
    if ($order && $row = mysqli_fetch_assoc($order)) {
        $response['order'] = $row;
    }

    $q1 = mysqli_query($conn, "SELECT id_prod, item_name, item_price, quantity, total FROM order_items WHERE id_tr = $id");
    if ($q1) while ($r = mysqli_fetch_assoc($q1)) $response['items'][] = $r;

    $q2 = mysqli_query($conn, "SELECT id_prod, item_name, unit_price, qty, total, nopol, service, ukuran, vacuum, profit_pegawai, profit_management FROM order_carwash WHERE id_tr = $id");
    if ($q2) while ($r = mysqli_fetch_assoc($q2)) $response['carwash'][] = $r;
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;