<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function apiAssert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

function apiCall(string $path, string $token, array $payload): array {
    $curl = curl_init('http://127.0.0.1/api/mobile/?path=' . rawurlencode($path));
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['X-API-Token: ' . $token, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
    ]);
    $body = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    if ($body === false) throw new RuntimeException(curl_error($curl));
    $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    apiAssert($status >= 200 && $status < 300, 'API gagal: ' . $body);
    return $data;
}

function uuid4(): string {
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

$token = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $token);
$clientId = uuid4();
$orderId = 0;
$user = $conn->query("SELECT id_user FROM tb_user WHERE LOWER(status)='aktif' ORDER BY id_user LIMIT 1")->fetch_assoc();
apiAssert((bool)$user, 'Tidak ada user aktif untuk pengujian API.');
$userId = (int)$user['id_user'];
$before = (float)$conn->query("SELECT current_quantity FROM vw_stock_balances WHERE name='Sei'")->fetch_assoc()['current_quantity'];

try {
    $stmt = $conn->prepare("INSERT INTO mobile_api_tokens (token_hash,user_id,expires_at,created_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE),NOW())");
    $stmt->bind_param('si', $tokenHash, $userId);
    $stmt->execute();
    $payload = [
        'client_order_id'=>$clientId,'created_at'=>date(DATE_ATOM),'table_number'=>'STOCK-API-TEST',
        'payment_method'=>'cash','status'=>'paid','items'=>[['id'=>'190','name'=>"PAKET SE'I SAPI",'qty'=>2,'unitPrice'=>10000,'cartType'=>'product']],
    ];
    $first = apiCall('orders', $token, $payload);
    $second = apiCall('orders', $token, $payload);
    $orderId = (int)$first['order_id'];
    apiAssert($orderId > 0 && (int)$second['order_id'] === $orderId && ($second['duplicate'] ?? false) === true, 'Retry APK tidak idempoten.');
    $sales = (int)$conn->query("SELECT COUNT(*) total FROM stock_movements WHERE order_id=$orderId AND movement_type='sale'")->fetch_assoc()['total'];
    $afterSale = (float)$conn->query("SELECT current_quantity FROM vw_stock_balances WHERE name='Sei'")->fetch_assoc()['current_quantity'];
    apiAssert($sales === 1 && abs($afterSale - ($before - 2)) < 0.0005, 'Upload APK tidak mengurangi stok tepat sekali.');
    apiCall('order-cancel', $token, ['id'=>$orderId,'reason'=>'Stock API regression']);
    $cancels = (int)$conn->query("SELECT COUNT(*) total FROM stock_movements WHERE order_id=$orderId AND movement_type='cancel'")->fetch_assoc()['total'];
    $afterCancel = (float)$conn->query("SELECT current_quantity FROM vw_stock_balances WHERE name='Sei'")->fetch_assoc()['current_quantity'];
    apiAssert($cancels === 1 && abs($afterCancel - $before) < 0.0005, 'Cancel APK tidak mengembalikan stok tepat sekali.');
    echo json_encode(['ok'=>true,'same_order_on_retry'=>true,'sale_movements'=>$sales,'cancel_movements'=>$cancels,'balance_restored'=>$afterCancel], JSON_PRETTY_PRINT).PHP_EOL;
} finally {
    if ($orderId === 0) {
        $stmt = $conn->prepare('SELECT order_id FROM mobile_sync_orders WHERE client_order_id=?');
        $stmt->bind_param('s', $clientId);
        $stmt->execute();
        $orderId = (int)($stmt->get_result()->fetch_assoc()['order_id'] ?? 0);
    }
    if ($orderId > 0) {
        $conn->query("DELETE FROM stock_movements WHERE order_id=$orderId");
        $conn->query("DELETE FROM order_items WHERE id_tr=$orderId");
        $conn->query("DELETE FROM orders WHERE id=$orderId AND table_number='STOCK-API-TEST'");
    }
    $stmt = $conn->prepare('DELETE FROM mobile_api_tokens WHERE token_hash=?');
    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
}
