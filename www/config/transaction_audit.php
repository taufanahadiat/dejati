<?php
declare(strict_types=1);

function transactionAudit(mysqli $conn, string $eventType, array $data = []): void
{
    $orderId = isset($data['order_id']) ? (int)$data['order_id'] : null;
    $clientOrderId = isset($data['client_order_id']) ? (string)$data['client_order_id'] : null;
    $userId = isset($data['user_id']) ? (int)$data['user_id'] : null;
    $source = (string)($data['source'] ?? 'server');
    $status = (string)($data['status'] ?? 'success');
    $table = isset($data['table_number']) ? (string)$data['table_number'] : null;
    $amount = isset($data['amount']) ? (int)$data['amount'] : null;
    $method = isset($data['payment_method']) ? (string)$data['payment_method'] : null;
    $clientEventAt = isset($data['client_event_at']) ? (string)$data['client_event_at'] : null;
    $details = $data['details'] ?? null;
    $detailsJson = $details === null ? null : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    $stmt = $conn->prepare('INSERT INTO transaction_audit_logs (event_type,order_id,client_order_id,user_id,source,event_status,table_number,amount,payment_method,client_event_at,details,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())');
    $stmt->bind_param('sisisssisss', $eventType, $orderId, $clientOrderId, $userId, $source, $status, $table, $amount, $method, $clientEventAt, $detailsJson);
    $stmt->execute();
}
