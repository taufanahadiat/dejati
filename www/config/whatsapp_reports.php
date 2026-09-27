<?php
require_once __DIR__ . '/../include/report/export_report.php';
function whatsappDailySales(mysqli $conn, string $start, string $end): array {
    $paid = "UPPER(COALESCE(NULLIF(o.status_order, ''), CASE WHEN o.paid_amount = 0 THEN 'OPEN BILL' ELSE 'PAID' END)) = 'PAID'";
    $where = "o.created_at >= ? AND o.created_at < ? AND $paid";
    $stmt = $conn->prepare("SELECT COUNT(*) transactions, COALESCE(SUM(o.total_amount),0) revenue FROM orders o WHERE $where");
    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();
    $sales = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $stmt = $conn->prepare("SELECT COALESCE(NULLIF(i.id_prod,''),i.item_name) product_id, MAX(i.item_name) name, SUM(i.quantity) quantity
        FROM order_items i JOIN orders o ON o.id=i.id_tr WHERE $where
        GROUP BY COALESCE(NULLIF(i.id_prod,''),i.item_name) HAVING SUM(i.quantity)>0
        ORDER BY quantity DESC, name ASC, product_id ASC LIMIT 10");
    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();
    $sales['topProducts'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $sales['divisions'] = whatsappSalesDivisions($conn, $start, $end, $paid);
    return $sales;
}

function whatsappSalesDivisions(mysqli $conn, string $start, string $end, string $paid): array {
    $query = static function (string $sql) use ($conn, $start, $end): array {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $start, $end);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    };
    $where = "o.created_at >= ? AND o.created_at < ? AND $paid";
    $orders = $query("SELECT o.id,o.total_amount FROM orders o WHERE $where");
    $byOrder = []; $divisions = [];
    foreach (['cafe' => ['order_items','quantity'], 'carwash' => ['order_carwash','qty'], 'detailing' => ['order_detailing','qty']] as $division => [$table,$qty]) {
        $divisions[$division] = ['transactions'=>0,'quantity'=>0,'gross'=>0,'discount'=>0,'adjustment'=>0,'revenue'=>0];
        foreach ($query("SELECT i.id_tr,i.total,i.$qty quantity FROM $table i JOIN orders o ON o.id=i.id_tr WHERE $where ORDER BY i.id") as $row) {
            $byOrder[$row['id_tr']][] = ['division'=>$division,'gross'=>(int)$row['total'],'quantity'=>(int)$row['quantity']];
        }
    }
    foreach ($orders as $order) {
        $lines = $byOrder[$order['id']] ?? [];
        if (!$lines) continue;
        $gross = array_sum(array_column($lines, 'gross'));
        $weights = $gross > 0 ? array_column($lines,'gross') : array_map(static fn($line) => max(1,$line['quantity']), $lines);
        $discounts = salesExportAllocate(max(0,$gross-(int)$order['total_amount']),$weights);
        $adjustments = salesExportAllocate(max(0,(int)$order['total_amount']-$gross),$weights);
        $present = [];
        foreach ($lines as $i => $line) {
            $key=$line['division']; $present[$key]=true;
            $divisions[$key]['quantity'] += $line['quantity'];
            $divisions[$key]['gross'] += $line['gross'];
            $divisions[$key]['discount'] += $discounts[$i];
            $divisions[$key]['adjustment'] += $adjustments[$i];
            $divisions[$key]['revenue'] += $line['gross']-$discounts[$i]+$adjustments[$i];
        }
        foreach (array_keys($present) as $key) $divisions[$key]['transactions']++;
    }
    return $divisions;
}
