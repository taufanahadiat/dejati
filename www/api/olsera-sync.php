<?php
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');
$secret=trim((string)@file_get_contents(__DIR__.'/../config/whatsapp-bot.token'));
if ($secret===''||!hash_equals($secret,$_SERVER['HTTP_X_STOCK_TOKEN']??'')) {http_response_code(403);exit(json_encode(['error'=>'Forbidden']));}
if ($_SERVER['REQUEST_METHOD']!=='POST') {http_response_code(405);exit;}
require __DIR__.'/../config/config.php';require __DIR__.'/../config/olsera.php';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
try {
    $input=json_decode(file_get_contents('php://input'),true,32,JSON_THROW_ON_ERROR);
    $conn->query("SET time_zone='+07:00'");$conn->begin_transaction();
    switch ($input['action']??'') {
        case 'claim': $result=['job'=>olseraClaim($conn)];break;
        case 'complete': $result=olseraImport($conn,$input);break;
        case 'fail':
            $date=olseraDate((string)($input['date']??''));
            olseraQuery($conn,"UPDATE olsera_sync_jobs SET state='failed',last_error=?,next_attempt_at=DATE_ADD(NOW(),INTERVAL LEAST(60,attempts*5) MINUTE),lease_until=NULL,lease_token=NULL WHERE sales_date=? AND state='running' AND lease_token=?",'sss',[substr((string)($input['error']??'Pengambilan Excel gagal'),0,2000),$date,(string)($input['lease']??'')]);
            $result=['ok'=>true];break;
        default: throw new InvalidArgumentException('Aksi tidak valid.');
    }
    $conn->commit();echo json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $conn->rollback();http_response_code($e instanceof InvalidArgumentException?422:503);
    error_log('Olsera sync: '.$e->getMessage());
    echo json_encode(['error'=>$e instanceof InvalidArgumentException?$e->getMessage():'Impor belum selesai; akan dicoba ulang.']);
}
