<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function beansAssert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

function beansApiCall(string $path, string $token, array $payload): array {
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
    beansAssert($status >= 200 && $status < 300, 'API gagal: ' . $body);
    return $data;
}

function beansUuid4(): string {
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

$token = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $token);
$clientId = beansUuid4();
$orderId = 0;
$userId = (int)$conn->query("SELECT id_user FROM tb_user WHERE LOWER(status)='aktif' ORDER BY id_user LIMIT 1")->fetch_assoc()['id_user'];
$before = [];
foreach ($conn->query('SELECT id,current_quantity FROM vw_stock_balances WHERE id IN (31,32)') as $row) {
    $before[(int)$row['id']] = (float)$row['current_quantity'];
}

try {
    $stmt = $conn->prepare("INSERT INTO mobile_api_tokens (token_hash,user_id,expires_at,created_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE),NOW())");
    $stmt->bind_param('si', $tokenHash, $userId);
    $stmt->execute();
    $payload = [
        'client_order_id' => $clientId,
        'created_at' => date(DATE_ATOM),
        'table_number' => 'BEANS-STOCK-API-TEST',
        'payment_method' => 'cash',
        'status' => 'paid',
        'items' => [
            ['id' => '36', 'name' => 'AMERICANO', 'qty' => 1, 'unitPrice' => 10000, 'cartType' => 'product'],
            ['id' => '200', 'name' => 'V60', 'qty' => 1, 'unitPrice' => 10000, 'cartType' => 'product'],
        ],
    ];
    $first = beansApiCall('orders', $token, $payload);
    $second = beansApiCall('orders', $token, $payload);
    $orderId = (int)$first['order_id'];
    beansAssert($orderId > 0 && (int)$second['order_id'] === $orderId && ($second['duplicate'] ?? false) === true, 'Retry APK tidak idempoten.');

    $deltas = [];
    foreach ($conn->query("SELECT stock_item_id,SUM(quantity_delta) delta,COUNT(*) movements FROM stock_movements WHERE order_id=$orderId AND movement_type='sale' GROUP BY stock_item_id") as $row) {
        $deltas[(int)$row['stock_item_id']] = ['delta' => (float)$row['delta'], 'movements' => (int)$row['movements']];
    }
    beansAssert(($deltas[31]['delta'] ?? 0) === -20.0 && ($deltas[31]['movements'] ?? 0) === 1, 'Espresso tidak berkurang tepat 20 gr sekali.');
    beansAssert(($deltas[32]['delta'] ?? 0) === -15.0 && ($deltas[32]['movements'] ?? 0) === 1, 'Manual brew tidak berkurang tepat 15 gr sekali.');

    beansApiCall('order-cancel', $token, ['id' => $orderId, 'reason' => 'Beans stock regression']);
    $after = [];
    foreach ($conn->query('SELECT id,current_quantity FROM vw_stock_balances WHERE id IN (31,32)') as $row) {
        $after[(int)$row['id']] = (float)$row['current_quantity'];
    }
    beansAssert(abs($after[31] - $before[31]) < 0.0005 && abs($after[32] - $before[32]) < 0.0005, 'Pembatalan tidak mengembalikan saldo beans.');
    echo json_encode(['ok' => true, 'retry_duplicate' => true, 'espresso_delta' => -20, 'manual_brew_delta' => -15, 'balances_restored' => $after], JSON_PRETTY_PRINT) . PHP_EOL;
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
        $conn->query("DELETE FROM orders WHERE id=$orderId AND table_number='BEANS-STOCK-API-TEST'");
    }
    $stmt = $conn->prepare('DELETE FROM mobile_api_tokens WHERE token_hash=?');
    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
}
