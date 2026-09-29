<?php
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');
$secret=trim((string)@file_get_contents(__DIR__.'/../config/whatsapp-bot.token'));
if($secret===''||!hash_equals($secret,$_SERVER['HTTP_X_STOCK_TOKEN']??'')){http_response_code(403);exit(json_encode(['error'=>'Forbidden']));}
require __DIR__.'/../config/config.php';require __DIR__.'/../config/whatsapp_closing.php';
try{
    $conn->query("SET time_zone = '+07:00'");$conn->begin_transaction();
    if($_SERVER['REQUEST_METHOD']==='GET'){
        waEnqueueClosing($conn,date('Y-m-d'),false);
        $rows=$conn->query("SELECT n.* FROM whatsapp_closing_notifications n LEFT JOIN olsera_sync_jobs j ON j.sales_date=n.closing_date WHERE (n.summary_state<>'sent' OR n.stock_state<>'sent') AND (j.sales_date IS NULL OR j.state='succeeded') ORDER BY n.closing_date LIMIT 30")->fetch_all(MYSQLI_ASSOC);
        foreach($rows as &$row)$row['payload']=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);
        unset($row);
        $result=['jobs'=>$rows];
    }elseif($_SERVER['REQUEST_METHOD']==='POST'){
        $result=waClosingAction($conn,json_decode(file_get_contents('php://input'),true,32,JSON_THROW_ON_ERROR));
    }else{http_response_code(405);exit;}
    $conn->commit();echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){$conn->rollback();http_response_code(503);echo json_encode(['error'=>'Notification service unavailable']);}
