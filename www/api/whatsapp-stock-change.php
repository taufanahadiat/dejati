<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
$secret=trim((string)@file_get_contents(__DIR__.'/../config/whatsapp-bot.token'));
if ($secret==='' || !hash_equals($secret,$_SERVER['HTTP_X_STOCK_TOKEN'] ?? '')) { http_response_code(403); exit(json_encode(['error'=>'Forbidden'])); }
if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); exit; }
require __DIR__.'/../config/config.php';
require __DIR__.'/../config/whatsapp_stock_changes.php';
try {
    $input=json_decode(file_get_contents('php://input'),true,32,JSON_THROW_ON_ERROR);
    if (!is_array($input)) throw new InvalidArgumentException('Permintaan tidak valid.');
    $conn->query("SET time_zone = '+07:00'");
    $conn->begin_transaction();
    $result=whatsappStockChange($conn,$input);
    $conn->commit();
    echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code($e instanceof InvalidArgumentException ? 422 : 503);
    echo json_encode(['error'=>$e instanceof InvalidArgumentException ? $e->getMessage() : 'Perubahan belum dapat dipastikan. Balas YA lagi untuk memeriksa; perubahan tidak akan digandakan.']);
}
