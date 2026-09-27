<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
$secret = trim((string) @file_get_contents(__DIR__ . '/../config/whatsapp-bot.token'));
$provided = $_SERVER['HTTP_X_STOCK_TOKEN'] ?? '';
if ($secret === '' || !hash_equals($secret, $provided)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit;
}
require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/whatsapp_reports.php';
try {
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
    $conn->query("SET time_zone = '+07:00'");
    $conn->begin_transaction(MYSQLI_TRANS_START_READ_ONLY | MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
    $rows = $conn->query('SELECT name,current_quantity,unit,minimum_quantity FROM vw_stock_balances WHERE active=1 ORDER BY name')->fetch_all(MYSQLI_ASSOC);
    $data = ['asOf' => $now->format(DATE_ATOM), 'items' => $rows];
    if (in_array($_GET['report'] ?? '', ['sales', 'bestsellers', 'summary'], true)) {
        $data['sales'] = whatsappDailySales($conn, $now->setTime(0,0)->format('Y-m-d H:i:s'), $now->modify('+1 day')->setTime(0,0)->format('Y-m-d H:i:s'));
    }
    $conn->commit();
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(503);
    echo json_encode(['error' => 'Laporan belum tersedia.']);
}
