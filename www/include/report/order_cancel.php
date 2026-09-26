<?php
header('Content-Type: application/json');
date_default_timezone_set("Asia/Jakarta");
require_once __DIR__ . '/../../config/config.php';

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$reason = trim((string)($_POST['reason'] ?? ''));

if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid order id']);
    exit;
}

if ($reason === '') {
    echo json_encode(['status' => 'error', 'message' => 'Reason is required']);
    exit;
}

$checkStmt = $conn->prepare("SELECT id, COALESCE(NULLIF(status_order, ''), CASE WHEN paid_amount = 0 THEN 'OPEN BILL' ELSE 'PAID' END) AS status_order
                             FROM orders
                             WHERE id = ?
                             LIMIT 1");
$checkStmt->bind_param('i', $id);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
$order = $checkResult ? $checkResult->fetch_assoc() : null;
$checkStmt->close();

if (!$order) {
    echo json_encode(['status' => 'error', 'message' => 'Order not found']);
    exit;
}

if (strtoupper((string)$order['status_order']) === 'CANCEL') {
    echo json_encode(['status' => 'error', 'message' => 'Order is already canceled']);
    exit;
}

$statusOrder = 'CANCEL';
$canceledAt = date('Y-m-d H:i:s');
$stmt = $conn->prepare("UPDATE orders
                        SET status_order = ?, cancel_reason = ?, canceled_at = ?
                        WHERE id = ?");
$stmt->bind_param('sssi', $statusOrder, $reason, $canceledAt, $id);
$success = $stmt->execute();
$stmt->close();

if (!$success) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to cancel order']);
    exit;
}

echo json_encode([
    'status' => 'success',
    'order_id' => $id,
    'status_order' => $statusOrder,
    'cancel_reason' => $reason,
    'canceled_at' => $canceledAt,
]);
