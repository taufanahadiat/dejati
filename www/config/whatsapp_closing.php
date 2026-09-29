<?php
require_once __DIR__.'/olsera.php';
require_once __DIR__.'/whatsapp_reports.php';
function waClosingQuery(mysqli $conn, string $sql, string $types='', array $params=[]): mysqli_stmt {
    $stmt=$conn->prepare($sql);
    if ($types!=='') $stmt->bind_param($types,...$params);
    $stmt->execute();return $stmt;
}
// Called inside the closing transaction; a rolled-back closing cannot create a notification.
function waEnqueueClosing(mysqli $conn, string $date, bool $refresh=true): void {
    if ($date!==date('Y-m-d')) return;
    $row=waClosingQuery($conn,'SELECT * FROM tb_closingan WHERE tanggal=? ORDER BY id DESC LIMIT 1','s',[$date])->get_result()->fetch_assoc();
    if (!$row) return;
    olseraEnqueue($conn,$date,$refresh);
    if (waClosingQuery($conn,'SELECT closing_date FROM whatsapp_closing_notifications WHERE closing_date=?','s',[$date])->get_result()->fetch_assoc()) return;
    $paid="UPPER(COALESCE(NULLIF(o.status_order,''),CASE WHEN o.paid_amount=0 THEN 'OPEN BILL' ELSE 'PAID' END))='PAID'";
    $end=$row['created_at'];
    $extra=waClosingQuery($conn,"SELECT COUNT(*) transactions, COALESCE(SUM(CASE WHEN LOWER(o.payment_method) IN ('debit','debit_card','card') THEN o.total_amount ELSE 0 END),0) other_card FROM orders o WHERE o.created_at>=? AND o.created_at<=? AND $paid",'ss',[$date.' 00:00:00',$end])->get_result()->fetch_assoc();
    $payload=['date'=>$date,'closedAt'=>$end,'transactions'=>(int)$extra['transactions']];
    foreach(['total_penjualan','cash','qris','card','cafe','carwash','detailing'] as $field) $payload[$field]=(int)$row[$field];
    $payload['card']+=(int)$extra['other_card'];
    $divisions=whatsappSalesDivisions($conn,$date.' 00:00:00',date('Y-m-d H:i:s',strtotime($end)+1),$paid);
    $payload['dejatiNet']=array_map(static fn($d)=>(int)$d['revenue'],$divisions);
    $payload['cancelled']=waClosingQuery($conn,"SELECT o.id,o.total_amount,o.cancel_reason FROM orders o
        WHERE UPPER(o.status_order)='CANCEL' AND COALESCE(o.canceled_at,o.created_at)>=? AND COALESCE(o.canceled_at,o.created_at)<=?
        ORDER BY COALESCE(o.canceled_at,o.created_at),o.id",'ss',[$date.' 00:00:00',$end])->get_result()->fetch_all(MYSQLI_ASSOC);
    $expenses=json_decode($row['detail_pengeluaran']??'[]',true);
    $payload['expenses']=is_array($expenses)?array_values(array_filter($expenses,static fn($expense)=>is_array($expense)&&(int)($expense['total']??0)>0)):[];

    $payload['stock']=['asOf'=>date(DATE_ATOM),'items'=>$conn->query('SELECT id,name,current_quantity,unit,minimum_quantity FROM vw_stock_balances WHERE active=1 ORDER BY name')->fetch_all(MYSQLI_ASSOC)];
    waClosingQuery($conn,'INSERT IGNORE INTO whatsapp_closing_notifications (closing_date,group_id,payload) VALUES (?,?,?)','sss',[$date,'6285711508770-1598438043@g.us',json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
}
function waClosingAction(mysqli $conn, array $input): array {
    $date=$input['date']??'';$part=$input['part']??'';$action=$input['action']??'';
    if (!is_string($date)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)||!in_array($part,['summary','stock'],true)||!in_array($action,['claim','sent','error'],true)) throw new InvalidArgumentException('Invalid notification action');
    $row=waClosingQuery($conn,'SELECT * FROM whatsapp_closing_notifications WHERE closing_date=? FOR UPDATE','s',[$date])->get_result()->fetch_assoc();
    if (!$row) throw new InvalidArgumentException('Notification missing');
    $state=$part.'_state';$body=$part.'_body';
    if ($action==='claim') {
        $sync=waClosingQuery($conn,'SELECT state FROM olsera_sync_jobs WHERE sales_date=?','s',[$date])->get_result()->fetch_assoc();
        if ($sync && $sync['state']!=='succeeded') return ['claimed'=>false];
        if ($row[$state]!=='pending'||($part==='stock'&&$row['summary_state']!=='sent')) return ['claimed'=>false];
        $text=$input['body']??'';
        if (!is_string($text)||$text===''||strlen($text)>60000) throw new InvalidArgumentException('Invalid message');
        waClosingQuery($conn,"UPDATE whatsapp_closing_notifications SET $state='sending',$body=?,last_error=NULL WHERE closing_date=?",'ss',[$text,$date]);
        return ['claimed'=>true];
    }
    if ($action==='sent') {
        if ($row[$state]==='sending') waClosingQuery($conn,"UPDATE whatsapp_closing_notifications SET $state='sent',{$part}_sent_at=NOW(),last_error=NULL WHERE closing_date=?",'s',[$date]);
    } elseif ($row[$state]!=='sent') {
        waClosingQuery($conn,'UPDATE whatsapp_closing_notifications SET last_error=? WHERE closing_date=?','ss',[substr((string)($input['error']??'Delivery uncertain'),0,255),$date]);
    }
    return ['ok'=>true];
}
