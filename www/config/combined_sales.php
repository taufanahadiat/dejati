<?php
require_once __DIR__.'/olsera.php';
require_once __DIR__.'/whatsapp_reports.php';
function combinedSales(mysqli $db, string $start, string $end): array {
    olseraDate($start);olseraDate($end);
    if ($start>$end || (strtotime($end)-strtotime($start))/86400>366) throw new InvalidArgumentException('Pilih rentang maksimal 367 hari.');
    $from=$start.' 00:00:00';$until=date('Y-m-d',strtotime($end.' +1 day')).' 00:00:00';
    $paid="UPPER(COALESCE(NULLIF(o.status_order,''),CASE WHEN o.paid_amount=0 THEN 'OPEN BILL' ELSE 'PAID' END))='PAID'";
    $dejati=olseraQuery($db,"SELECT COUNT(*) transactions,COALESCE(SUM(total_amount),0) total FROM orders o WHERE o.created_at>=? AND o.created_at<? AND $paid",'ss',[$from,$until])->get_result()->fetch_assoc();
    $divisions=whatsappSalesDivisions($db,$from,$until,$paid);
    $olsera=['cafe'=>0.0,'carwash'=>0.0,'detailing'=>0.0,'total'=>0.0];
    foreach (olseraQuery($db,'SELECT business,SUM(total_sales) total FROM olsera_sales WHERE sales_date BETWEEN ? AND ? GROUP BY business','ss',[$start,$end])->get_result() as $r) {$olsera[$r['business']]=(float)$r['total'];$olsera['total']+=(float)$r['total'];}
    $jobs=olseraQuery($db,'SELECT j.*,i.file_name,i.fetched_at,i.row_count,i.total_sales FROM olsera_sync_jobs j LEFT JOIN olsera_imports i ON i.id=(SELECT MAX(i2.id) FROM olsera_imports i2 WHERE i2.sales_date=j.sales_date) WHERE j.sales_date BETWEEN ? AND ? ORDER BY j.sales_date DESC','ss',[$start,$end])->get_result()->fetch_all(MYSQLI_ASSOC);
    $items=olseraQuery($db,'SELECT sales_date,product_name,variant,business,quantity,total_sales FROM olsera_sales WHERE sales_date BETWEEN ? AND ? AND (quantity<>0 OR total_sales<>0) ORDER BY sales_date DESC,business,product_name,variant','ss',[$start,$end])->get_result()->fetch_all(MYSQLI_ASSOC);
    return compact('dejati','divisions','olsera','jobs','items');
}
